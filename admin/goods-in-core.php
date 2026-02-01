<?php
/**
 * Stock Order Plugin - Phase 5 (Goods-In v1) - Core (admin only)
 * File version: 1.0.30
 * - Use capability helper for Stock Order UI access.
 *
 * - Receive against ordered (locked) preorder sheets.
 * - Save goods-in progress, apply stock increases, and complete goods-in.
 * - Uses JSON payload to avoid max_input_vars on large sheets.
 * - 1.0.29 - Allow Goods-In stock corrections (decrease) when accepted qty is lower.
 * - 1.0.28 - Remove duplicate payload line normalisation calls in Goods-In save handler.
 * - 1.0.02 - Add live display hydration helper for Goods-In lines (display only).
 * - 1.0.03 - Key Goods-In handlers by product_id (SKU fallback) and normalise POST maps.
 * - 1.0.04 - Apply product_id normalisation across all Goods-In handlers (SKU fallback).
 * - 1.0.05 - Lazy-migrate legacy lines/maps to product_id and warn on unresolved SKUs.
 * - 1.0.06 - Add completed-only Goods-In Issues XLSX export (missing/reject only).
 * - 1.0.07 - Harden Goods-In Issues export (completed gate, locked FX, issue data build).
 * - 1.0.08 - Add dispute summary helper for completed goods-in view.
 * - 1.0.09 - Persist missing/reject from payload (canonical keys) without dropping values.
 * - 1.0.10 - Persist Reject even when Received is blank by enforcing received >= stock_added + reject (no snapshot changes).
 * - 1.0.11 - Hydrate issue export lines with live product fields; keep locked FX and base columns alignment.
 * - 1.0.12 - Add XLSX export preflight handling for Goods-In Issues.
 * - 1.0.15 - Derive non-RMB credit totals from RMB using balance FX + SOP rates.
 * - 1.0.16 - Align dispute summary FX/cost resolution with Issues XLSX (non-RMB from RMB via FX).
 * - 1.0.17 - Hydrate Issues export lines with supplier currency cost (GBP/EUR/USD) from RMB via balance FX/SOP rates.
 * - 1.0.18 - Core: redirect with sheet_readonly for received Goods-In sheets.
 * - 1.0.19 - Core: accept legacy issues export params (sheet_id/nonce).
 * - 1.0.21 - Version bump for Goods-In UI/core.
 * - 1.0.22 - Stop setting receiving status; allow legacy receiving.
 * - 1.0.27 - AJAX apply-stock adds SKU/reason labels + retry on stock update failure.
 * - 1.0.26 - AJAX apply-stock returns outstanding/is_complete for row UI.
 * - 1.0.25 - Add AJAX apply-stock endpoint for sequential Goods-In updates.
 * - 1.0.24 - Harden Issues XLSX download streaming (clear output buffers) to prevent Excel repair warnings.
 * - 1.0.23 - Keep goods-in on locked sheets; do not set receiving on save/apply.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parse Goods-In JSON payload from POST.
 *
 * @return array|null
 */
function sop_goodsin_get_payload_from_post() {
    $raw = isset( $_POST['sop_goodsin_payload_json'] ) ? wp_unslash( $_POST['sop_goodsin_payload_json'] ) : '';
    if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
        return null;
    }

    $decoded = json_decode( $raw, true );
    if ( ! is_array( $decoded ) ) {
        return null;
    }

    return $decoded;
}

/**
 * Get preorder sheet header by ID for goods-in.
 *
 * @param int $sheet_id
 * @return array|null
 */
function sop_goodsin_get_sheet( $sheet_id ) {
    if ( ! function_exists( 'sop_get_preorder_sheet' ) ) {
        return null;
    }

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return null;
    }

    $sheet = sop_get_preorder_sheet( $sheet_id );
    return is_array( $sheet ) ? $sheet : null;
}

/**
 * Get goods-in lines for a sheet as a map keyed by line ID.
 *
 * @param int $sheet_id
 * @return array<int,array>
 */
function sop_goodsin_get_sheet_lines_map( $sheet_id ) {
    global $wpdb;

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return array();
    }

    $tbl_lines = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';
    if ( '' === $tbl_lines ) {
        $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
    }

    $sql  = "SELECT *
             FROM {$tbl_lines}
             WHERE sheet_id = %d";
    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $sheet_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ( ! is_array( $rows ) ) {
        return array();
    }

    $map = array();
    foreach ( $rows as $row ) {
        $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
        if ( $id <= 0 ) {
            continue;
        }
        $map[ $id ] = $row;
    }

    return $map;
}

/**
 * Migrate goods-in lines map to ensure product_id is set (lazy migration).
 *
 * @param array $lines_map
 * @return array array( $lines_map, $unresolved_skus )
 */
function sop_goodsin_migrate_lines_map_to_pid( array $lines_map ) {
    $unresolved = array();
    $changed    = false;

    foreach ( $lines_map as $lid => $row ) {
        $pid = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
        $sku = isset( $row['sku_owner'] ) ? (string) $row['sku_owner'] : '';

        if ( $pid <= 0 && '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
            $resolved = wc_get_product_id_by_sku( $sku );
            if ( $resolved > 0 ) {
                $lines_map[ $lid ]['product_id'] = (int) $resolved;
                $changed = true;
            } else {
                $unresolved[] = $sku;
            }
        }
    }

    // Persist the migration when changes occurred.
    if ( $changed ) {
        global $wpdb;
        $tbl_lines = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';
        if ( '' === $tbl_lines ) {
            $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
        }
        foreach ( $lines_map as $lid => $row ) {
            $pid = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
            if ( $lid > 0 && $pid > 0 ) {
                $wpdb->update(
                    $tbl_lines,
                    array( 'product_id' => $pid ),
                    array( 'id' => $lid ),
                    array( '%d' ),
                    array( '%d' )
                );
            }
        }
    }

    return array( $lines_map, $unresolved );
}

/**
 * Helper to fetch a numeric field from a goods-in line using a list of candidate keys.
 *
 * @param array $line
 * @param array $keys
 * @return float
 */
function sop_goodsin_get_number_from_line( array $line, array $keys ) {
	foreach ( $keys as $key ) {
		if ( isset( $line[ $key ] ) && '' !== $line[ $key ] ) {
			return (float) $line[ $key ];
		}
	}
	return 0.0;
}

/**
 * Get first positive numeric value from a line for the given keys.
 *
 * @param array $line Line data.
 * @param array $keys Keys to inspect.
 * @return float
 */
function sop_goodsin_get_positive_number_from_line( array $line, array $keys ) {
	foreach ( $keys as $key ) {
		if ( isset( $line[ $key ] ) && '' !== $line[ $key ] ) {
			$val = (float) $line[ $key ];
			if ( $val > 0 ) {
				return $val;
			}
		}
	}
	return 0.0;
}

/**
 * Read SOP FX settings (with legacy key fallback).
 *
 * @return array
 */
function sop_goodsin_get_sop_settings_fx_rates() {
	$opt = get_option( 'sop_settings', array() );
	$opt = is_array( $opt ) ? $opt : array();
	$read = function ( $keys ) use ( $opt ) {
		foreach ( (array) $keys as $key ) {
			if ( isset( $opt[ $key ] ) && '' !== $opt[ $key ] ) {
				return (float) $opt[ $key ];
			}
		}
		return 0.0;
	};

	return array(
		'rmb_to_gbp' => $read( array( 'rmb_to_gbp_rate', 'rmb to gbp rate' ) ),
		'eur_to_gbp' => $read( array( 'eur_to_gbp_rate', 'eur to gbp rate' ) ),
		'usd_to_gbp' => $read( array( 'usd_to_gbp_rate', 'usd to gbp rate' ) ),
		'usd_to_rmb' => $read( array( 'usd_to_rmb_rate', 'usd to rmb rate' ) ),
	);
}

/**
 * Get balance FX (USD->RMB) from sheet header notes.
 *
 * @param array $sheet Sheet header.
 * @return float
 */
function sop_goodsin_get_balance_fx_rate_from_sheet( array $sheet ) {
	$rate = 0.0;
	if ( ! empty( $sheet['header_notes_owner'] ) && is_string( $sheet['header_notes_owner'] ) ) {
		$decoded = json_decode( $sheet['header_notes_owner'], true );
		if ( is_array( $decoded ) && isset( $decoded['balance_fx_rate'] ) && (float) $decoded['balance_fx_rate'] > 0 ) {
			$rate = (float) $decoded['balance_fx_rate'];
		}
	}
	return $rate;
}

/**
 * Convert RMB to target currency using sheet + SOP rates.
 *
 * @param float  $rmb              RMB amount.
 * @param string $target_currency  Currency code.
 * @param float  $sheet_usd_to_rmb Sheet FX (RMB per USD).
 * @param array  $rates            SOP settings rates.
 * @return float
 */
function sop_goodsin_convert_rmb_to_currency( $rmb, $target_currency, $sheet_usd_to_rmb, $rates ) {
	$rmb             = (float) $rmb;
	$target_currency = strtoupper( trim( (string) $target_currency ) );
	if ( $rmb <= 0 ) {
		return 0.0;
	}
	$usd_to_rmb = ( $sheet_usd_to_rmb > 0 ) ? $sheet_usd_to_rmb : ( isset( $rates['usd_to_rmb'] ) ? (float) $rates['usd_to_rmb'] : 0.0 );
	$rmb_to_gbp = isset( $rates['rmb_to_gbp'] ) ? (float) $rates['rmb_to_gbp'] : 0.0;
	$usd_to_gbp = isset( $rates['usd_to_gbp'] ) ? (float) $rates['usd_to_gbp'] : 0.0;
	$eur_to_gbp = isset( $rates['eur_to_gbp'] ) ? (float) $rates['eur_to_gbp'] : 0.0;

	if ( 'USD' === $target_currency ) {
		if ( $usd_to_rmb > 0 ) {
			return $rmb / $usd_to_rmb;
		}
		if ( $rmb_to_gbp > 0 && $usd_to_gbp > 0 ) {
			$gbp = $rmb * $rmb_to_gbp;
			return $gbp / $usd_to_gbp;
		}
		return 0.0;
	}

	if ( 'GBP' === $target_currency ) {
		if ( $usd_to_rmb > 0 && $usd_to_gbp > 0 ) {
			return ( $rmb / $usd_to_rmb ) * $usd_to_gbp;
		}
		if ( $rmb_to_gbp > 0 ) {
			return $rmb * $rmb_to_gbp;
		}
		return 0.0;
	}

	if ( 'EUR' === $target_currency ) {
		$gbp = 0.0;
		if ( $usd_to_rmb > 0 && $usd_to_gbp > 0 ) {
			$gbp = ( $rmb / $usd_to_rmb ) * $usd_to_gbp;
		} elseif ( $rmb_to_gbp > 0 ) {
			$gbp = $rmb * $rmb_to_gbp;
		}
		if ( $gbp > 0 && $eur_to_gbp > 0 ) {
			return $gbp / $eur_to_gbp;
		}
		return 0.0;
	}

	return 0.0;
}

/**
 * Resolve supplier-currency and RMB unit costs for summary/credit calculations.
 *
 * @param array  $line             Line data.
 * @param string $supplier_currency Supplier currency.
 * @param float  $balance_fx_rate  Sheet balance FX (RMB per USD).
 * @param array  $rates            SOP FX settings.
 * @return array
 */
function sop_goodsin_resolve_unit_cost_for_summary( array $line, $supplier_currency, $balance_fx_rate, $rates ) {
	$currency_upper     = strtoupper( trim( (string) $supplier_currency ) );
	$rmb_cost           = sop_goodsin_get_positive_number_from_line( $line, array( 'cost_rmb_owner', 'cost_rmb', 'cost_per_unit_rmb', 'cost_rmb_per_unit' ) );
	$supplier_cost_keys = array(
		'cost_supplier_owner',
		'cost_supplier',
		'supplier_cost_owner',
		'supplier_cost',
		'unit_cost',
		'cost_per_unit',
		'cost_owner',
		'cost',
		'cost_' . strtolower( $currency_upper ) . '_owner',
		'cost_' . strtolower( $currency_upper ),
	);
	$supplier_cost      = sop_goodsin_get_positive_number_from_line( $line, $supplier_cost_keys );

	if ( 'RMB' === $currency_upper ) {
		if ( $supplier_cost <= 0 && $rmb_cost > 0 ) {
			$supplier_cost = $rmb_cost;
		}
		return array(
			'supplier' => $supplier_cost,
			'rmb'      => $rmb_cost,
		);
	}

	if ( $supplier_cost <= 0 && $rmb_cost > 0 ) {
		$converted = sop_goodsin_convert_rmb_to_currency( $rmb_cost, $currency_upper, $balance_fx_rate, $rates );
		if ( $converted > 0 ) {
			$supplier_cost = $converted;
		}
	}

	return array(
		'supplier' => $supplier_cost,
		'rmb'      => $rmb_cost,
	);
}

/**
 * Compute Goods-In issues summary (missing/reject + credit totals).
 *
 * @param array $sheet      Sheet header.
 * @param array $lines_map  Lines array (e.g. from sop_get_preorder_sheet_lines()).
 * @return array
 */
function sop_goodsin_get_issues_summary_for_sheet( array $sheet, array $lines_map ) {
    $supplier_currency = 'GBP';
    if ( isset( $sheet['supplier_id'] ) && function_exists( 'sop_preorder_resolve_supplier_params' ) ) {
        $ctx = sop_preorder_resolve_supplier_params( (int) $sheet['supplier_id'] );
        if ( ! empty( $ctx['currency_code'] ) ) {
            $supplier_currency = strtoupper( trim( (string) $ctx['currency_code'] ) );
        }
    }

    $is_rmb            = ( 'RMB' === $supplier_currency );
    $fx_rmb_per_usd    = 0.0;
    $sheet_balance_fx  = sop_goodsin_get_balance_fx_rate_from_sheet( $sheet );
    $sop_fx_settings   = sop_goodsin_get_sop_settings_fx_rates();
    $supplier_effective_fx = 0.0;
    if ( isset( $sheet['supplier_id'] ) && function_exists( 'sop_get_supplier_effective_usd_to_rmb_rate' ) ) {
        $supplier_effective_fx = (float) sop_get_supplier_effective_usd_to_rmb_rate( (int) $sheet['supplier_id'] );
    }
    if ( $sheet_balance_fx > 0 ) {
        $fx_rmb_per_usd = $sheet_balance_fx;
    } elseif ( $is_rmb && $supplier_effective_fx > 0 ) {
        $fx_rmb_per_usd = $supplier_effective_fx;
    }

    $summary = array(
        'issue_line_count'           => 0,
        'total_missing'              => 0.0,
        'total_reject'               => 0.0,
        'total_credit_qty'           => 0.0,
        'total_credit_total_supplier'=> 0.0,
        'total_credit_total_usd'     => 0.0,
        'supplier_currency'          => $supplier_currency,
        'is_rmb'                     => $is_rmb,
        'fx_rmb_per_usd'             => $fx_rmb_per_usd,
    );

    if ( empty( $lines_map ) ) {
        return $summary;
    }

    foreach ( $lines_map as $line ) {
        $missing = sop_goodsin_get_number_from_line( $line, array( 'goods_in_missing_qty_owner', 'goods_in_missing_qty' ) );
        $reject  = sop_goodsin_get_number_from_line( $line, array( 'goods_in_reject_qty_owner', 'goods_in_reject_qty' ) );
        if ( $missing <= 0 && $reject <= 0 ) {
            continue;
        }

        $summary['issue_line_count']++;
        $credit_qty  = max( 0.0, $missing + $reject );
        $costs       = sop_goodsin_resolve_unit_cost_for_summary( $line, $supplier_currency, $sheet_balance_fx, $sop_fx_settings );
        $unit_cost   = $costs['supplier'];
        $credit_total = $credit_qty * $unit_cost;

        $summary['total_missing']  += $missing;
        $summary['total_reject']   += $reject;
        $summary['total_credit_qty'] += $credit_qty;
        $summary['total_credit_total_supplier'] += $credit_total;

        if ( $summary['is_rmb'] && $summary['fx_rmb_per_usd'] > 0 && $credit_total > 0 ) {
            $summary['total_credit_total_usd'] += ( $credit_qty > 0 && $costs['rmb'] > 0 )
                ? ( ( $credit_qty * $costs['rmb'] ) / $summary['fx_rmb_per_usd'] )
                : 0;
        }
    }

    return $summary;
}

/**
 * Hydrate saved goods-in lines with live product display data (non-destructive).
 *
 * @param array $lines_map Map of lines keyed by line ID.
 * @param int   $supplier_id Supplier ID for currency-aware fields.
 * @return array
 */
function sop_goodsin_hydrate_lines_with_live_data( array $lines_map, $supplier_id = 0 ) {
    if ( empty( $lines_map ) || ! function_exists( 'sop_hydrate_line_with_live_product_fields' ) ) {
        return $lines_map;
    }

    $supplier_id = (int) $supplier_id;
    foreach ( $lines_map as $lid => $line ) {
        $lines_map[ $lid ] = sop_hydrate_line_with_live_product_fields( $line, $supplier_id );
    }

    return $lines_map;
}

/**
 * Normalise an input array keyed by product_id or SKU into product_id keys.
 *
 * @param array $raw
 * @return array
 */
function sop_goodsin_normalize_pid_map( $raw ) {
    $raw = is_array( $raw ) ? $raw : array();
    $out = array();

    foreach ( $raw as $key => $value ) {
        $pid = is_numeric( $key ) ? (int) $key : 0;
        if ( $pid <= 0 && is_string( $key ) && '' !== $key && function_exists( 'wc_get_product_id_by_sku' ) ) {
            $pid = wc_get_product_id_by_sku( (string) $key );
        }
        if ( $pid <= 0 ) {
            continue;
        }
        $out[ $pid ] = $value;
    }

    return $out;
}

/**
 * Normalise payload lines to ensure product_id is set (fallback from SKU/db row).
 *
 * @param array $payload_lines
 * @param array $lines_map Map of DB rows keyed by line ID.
 * @return array
 */
function sop_goodsin_normalize_payload_lines( $payload_lines, $lines_map ) {
    $payload_lines = is_array( $payload_lines ) ? $payload_lines : array();
    $lines_map     = is_array( $lines_map ) ? $lines_map : array();

    foreach ( $payload_lines as $idx => $line_in ) {
        if ( ! is_array( $line_in ) ) {
            unset( $payload_lines[ $idx ] );
            continue;
        }

        $line_id    = isset( $line_in['line_id'] ) ? (int) $line_in['line_id'] : 0;
        $product_id = isset( $line_in['product_id'] ) ? (int) $line_in['product_id'] : 0;
        $sku        = isset( $line_in['sku'] ) ? (string) $line_in['sku'] : '';

        if ( $product_id <= 0 && $line_id > 0 && isset( $lines_map[ $line_id ] ) ) {
            $db_row = $lines_map[ $line_id ];
            if ( isset( $db_row['product_id'] ) && (int) $db_row['product_id'] > 0 ) {
                $product_id = (int) $db_row['product_id'];
            } elseif ( '' === $sku && isset( $db_row['sku_owner'] ) ) {
                $sku = (string) $db_row['sku_owner'];
            }
        }

        if ( $product_id <= 0 && '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
            $maybe_pid = wc_get_product_id_by_sku( $sku );
            if ( $maybe_pid > 0 ) {
                $product_id = (int) $maybe_pid;
            }
        }

        $payload_lines[ $idx ]['product_id'] = $product_id;
        if ( '' === $sku && isset( $lines_map[ $line_id ]['sku_owner'] ) ) {
            $payload_lines[ $idx ]['sku'] = (string) $lines_map[ $line_id ]['sku_owner'];
        }
    }

    return $payload_lines;
}

/**
 * Normalise incoming goods-in line payload values against DB row.
 *
 * @param array $line_in
 * @param array $db_row
 * @param array $opts
 * @return array
 */
function sop_goodsin_normalize_line_payload( array $line_in, array $db_row, array $opts = array() ) {
    $ordered_qty = isset( $db_row['qty_owner'] ) ? (float) $db_row['qty_owner'] : 0.0;
    $stock_added = isset( $db_row['goods_in_stock_added_qty'] ) ? (float) $db_row['goods_in_stock_added_qty'] : 0.0;
    if ( $ordered_qty < 0 ) {
        $ordered_qty = 0.0;
    }
    if ( $stock_added < 0 ) {
        $stock_added = 0.0;
    }

    $received_qty = isset( $line_in['received_qty'] ) ? (float) $line_in['received_qty'] : ( isset( $db_row['goods_in_received_qty'] ) ? (float) $db_row['goods_in_received_qty'] : 0.0 );
    $missing_qty  = isset( $line_in['goods_in_missing_qty'] ) ? (float) $line_in['goods_in_missing_qty'] : ( isset( $line_in['missing_qty'] ) ? (float) $line_in['missing_qty'] : ( isset( $db_row['goods_in_missing_qty'] ) ? (float) $db_row['goods_in_missing_qty'] : 0.0 ) );
    $reject_qty   = isset( $line_in['goods_in_reject_qty'] ) ? (float) $line_in['goods_in_reject_qty'] : ( isset( $line_in['reject_qty'] ) ? (float) $line_in['reject_qty'] : ( isset( $db_row['goods_in_reject_qty'] ) ? (float) $db_row['goods_in_reject_qty'] : 0.0 ) );
    $reject_reason = isset( $line_in['reject_reason'] ) ? sanitize_text_field( (string) $line_in['reject_reason'] ) : '';
    $notes         = isset( $line_in['notes'] ) ? wp_kses_post( (string) $line_in['notes'] ) : '';

    if ( $received_qty < 0 ) {
        $received_qty = 0.0;
    }
    if ( $missing_qty < 0 ) {
        $missing_qty = 0.0;
    }
    if ( $reject_qty < 0 ) {
        $reject_qty = 0.0;
    }

    if ( $received_qty > $ordered_qty ) {
        $received_qty = $ordered_qty;
    }
    if ( $missing_qty > $ordered_qty ) {
        $missing_qty = $ordered_qty;
    }
    if ( $reject_qty > $ordered_qty ) {
        $reject_qty = $ordered_qty;
    }

    $allow_lower_received = ! empty( $opts['allow_lower_received'] );
    $accepted_input = max( 0.0, $received_qty - $reject_qty );
    $effective_stock_added = $allow_lower_received ? min( $stock_added, $accepted_input ) : $stock_added;
    $max_other = max( 0.0, $ordered_qty - $effective_stock_added );
    if ( ( $missing_qty + $reject_qty ) > $max_other ) {
        $reject_qty  = min( $reject_qty, $max_other );
        $missing_qty = max( 0.0, $max_other - $reject_qty );
    }

    if ( ! $allow_lower_received ) {
        // Ensure received cannot be below the already applied + rejected amount.
        $min_received = min( $ordered_qty, $stock_added + $reject_qty );
        if ( $received_qty < $min_received ) {
            $received_qty = $min_received;
        }
    }

    return array(
        'ordered_qty'   => $ordered_qty,
        'stock_added'   => $stock_added,
        'received_qty'  => $received_qty,
        'missing_qty'   => $missing_qty,
        'reject_qty'    => $reject_qty,
        'reject_reason' => $reject_reason,
        'notes'         => $notes,
        'update'        => array(
            'goods_in_received_qty' => $received_qty,
            'goods_in_missing_qty'  => $missing_qty,
            'goods_in_reject_qty'   => $reject_qty,
            'goods_in_reject_reason'=> $reject_reason,
            'goods_in_notes'        => ( '' !== $notes ) ? $notes : null,
            'goods_in_updated_at'   => current_time( 'mysql', true ),
        ),
    );
}

/**
 * Save receiving progress for a sheet.
 */
function sop_handle_goodsin_save() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access Goods In.', 'sop' ) );
    }

    check_admin_referer( 'sop_goodsin_action', 'sop_goodsin_nonce' );

    $payload = sop_goodsin_get_payload_from_post();
    $sheet_id = isset( $payload['sheet_id'] ) ? (int) $payload['sheet_id'] : 0;

    $redirect = add_query_arg(
        array(
            'page'     => 'sop-goods-in',
            'sheet_id' => $sheet_id,
        ),
        admin_url( 'admin.php' )
    );

    if ( $sheet_id <= 0 || ! is_array( $payload ) || empty( $payload['lines'] ) || ! is_array( $payload['lines'] ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'invalid_payload', $redirect ) );
        exit;
    }

    $sheet = sop_goodsin_get_sheet( $sheet_id );
    if ( ! $sheet ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_found', $redirect ) );
        exit;
    }

    $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
    if ( in_array( $status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_readonly', $redirect ) );
        exit;
    }
    if ( 'locked' !== $status && 'receiving' !== $status ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_lockable', $redirect ) );
        exit;
    }

    $lines_map = sop_goodsin_get_sheet_lines_map( $sheet_id );
    if ( ! empty( $lines_map ) && function_exists( 'sop_goodsin_migrate_lines_map_to_pid' ) ) {
        list( $lines_map, $unresolved_skus ) = sop_goodsin_migrate_lines_map_to_pid( $lines_map );
        if ( ! empty( $unresolved_skus ) && current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
            add_action(
                'admin_notices',
                static function() use ( $unresolved_skus, $sheet_id ) {
                    $limited = array_slice( $unresolved_skus, 0, 20 );
                    $more    = max( 0, count( $unresolved_skus ) - count( $limited ) );
                    $msg     = sprintf(
                        /* translators: 1: sheet id, 2: skus, 3: more count */
                        esc_html__( 'Goods-In sheet #%1$d: Could not resolve product IDs for SKUs: %2$s%3$s', 'sop' ),
                        (int) $sheet_id,
                        esc_html( implode( ', ', $limited ) ),
                        $more > 0 ? esc_html( sprintf( ' (+%d more)', $more ) ) : ''
                    );
                    echo '<div class="notice notice-warning"><p>' . $msg . '</p></div>';
                }
            );
        }
    }
    if ( empty( $lines_map ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'no_lines', $redirect ) );
        exit;
    }
    // Ensure product_id is present for each payload line (SKU fallback).
    $payload['lines'] = sop_goodsin_normalize_payload_lines( $payload['lines'], $lines_map );

    global $wpdb;
    $tbl_lines = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';
    if ( '' === $tbl_lines ) {
        $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
    }

    $updated_count = 0;

    foreach ( $payload['lines'] as $line_in ) {
        if ( ! is_array( $line_in ) ) {
            continue;
        }

        $line_id    = isset( $line_in['line_id'] ) ? (int) $line_in['line_id'] : 0;
        $product_id = isset( $line_in['product_id'] ) ? (int) $line_in['product_id'] : 0;
        if ( $line_id <= 0 || ! isset( $lines_map[ $line_id ] ) ) {
            continue;
        }

        $db_row = $lines_map[ $line_id ];
        $db_pid = isset( $db_row['product_id'] ) ? (int) $db_row['product_id'] : 0;
        if ( $product_id <= 0 && $db_pid > 0 ) {
            $product_id = $db_pid;
        } elseif ( $product_id > 0 && $db_pid > 0 && $db_pid !== $product_id ) {
            continue;
        }

        $norm = sop_goodsin_normalize_line_payload( $line_in, $db_row );
        $update = $norm['update'];

        $formats = array(
            '%f',
            '%f',
            '%f',
            '%s',
            '%s',
            '%s',
        );

        $result = $wpdb->update(
            $tbl_lines,
            $update,
            array( 'id' => $line_id, 'sheet_id' => $sheet_id ),
            $formats,
            array( '%d', '%d' )
        );

        if ( false !== $result ) {
            $updated_count++;
        }
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'sop_msg'     => 'saved',
                'sop_updated' => $updated_count,
            ),
            $redirect
        )
    );
    exit;
}

/**
 * Apply received stock to WooCommerce for selected/all lines.
 */
function sop_handle_goodsin_apply_stock() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access Goods In.', 'sop' ) );
    }

    check_admin_referer( 'sop_goodsin_action', 'sop_goodsin_nonce' );

    $payload  = sop_goodsin_get_payload_from_post();
    $sheet_id = isset( $payload['sheet_id'] ) ? (int) $payload['sheet_id'] : 0;

    $redirect = add_query_arg(
        array(
            'page'     => 'sop-goods-in',
            'sheet_id' => $sheet_id,
        ),
        admin_url( 'admin.php' )
    );

    if ( $sheet_id <= 0 || ! is_array( $payload ) || empty( $payload['lines'] ) || ! is_array( $payload['lines'] ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'invalid_payload', $redirect ) );
        exit;
    }

    $sheet = sop_goodsin_get_sheet( $sheet_id );
    if ( ! $sheet ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_found', $redirect ) );
        exit;
    }

    $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
    if ( in_array( $status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_readonly', $redirect ) );
        exit;
    }
    if ( 'locked' !== $status && 'receiving' !== $status ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_lockable', $redirect ) );
        exit;
    }

    $lines_map = sop_goodsin_get_sheet_lines_map( $sheet_id );
    if ( ! empty( $lines_map ) && function_exists( 'sop_goodsin_migrate_lines_map_to_pid' ) ) {
        list( $lines_map, $unresolved_skus ) = sop_goodsin_migrate_lines_map_to_pid( $lines_map );
        if ( ! empty( $unresolved_skus ) && current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
            add_action(
                'admin_notices',
                static function() use ( $unresolved_skus, $sheet_id ) {
                    $limited = array_slice( $unresolved_skus, 0, 20 );
                    $more    = max( 0, count( $unresolved_skus ) - count( $limited ) );
                    $msg     = sprintf(
                        /* translators: 1: sheet id, 2: skus, 3: more count */
                        esc_html__( 'Goods-In sheet #%1$d: Could not resolve product IDs for SKUs: %2$s%3$s', 'sop' ),
                        (int) $sheet_id,
                        esc_html( implode( ', ', $limited ) ),
                        $more > 0 ? esc_html( sprintf( ' (+%d more)', $more ) ) : ''
                    );
                    echo '<div class="notice notice-warning"><p>' . $msg . '</p></div>';
                }
            );
        }
    }
    if ( empty( $lines_map ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'no_lines', $redirect ) );
        exit;
    }

    global $wpdb;
    $tbl_lines = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';
    if ( '' === $tbl_lines ) {
        $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
    }

    $applied_lines = 0;
    $applied_qty   = 0.0;
    $skipped       = array();

    foreach ( $payload['lines'] as $line_in ) {
        if ( ! is_array( $line_in ) ) {
            continue;
        }

        $selected = ! empty( $line_in['selected'] );
        if ( ! $selected ) {
            continue;
        }

        $line_id = isset( $line_in['line_id'] ) ? (int) $line_in['line_id'] : 0;
        if ( $line_id <= 0 || ! isset( $lines_map[ $line_id ] ) ) {
            continue;
        }

        $db_row    = $lines_map[ $line_id ];
        $norm      = sop_goodsin_normalize_line_payload( $line_in, $db_row, array( 'allow_lower_received' => true ) );
        $product_id = isset( $line_in['product_id'] ) ? (int) $line_in['product_id'] : 0;
        $db_pid     = isset( $db_row['product_id'] ) ? (int) $db_row['product_id'] : 0;
        if ( $product_id <= 0 && $db_pid > 0 ) {
            $product_id = $db_pid;
        } elseif ( $product_id > 0 && $db_pid > 0 && $db_pid !== $product_id ) {
            continue;
        }
        $ordered_qty = $norm['ordered_qty'];
        $received_qty = $norm['received_qty'];
        $missing_qty  = $norm['missing_qty'];
        $reject_qty   = $norm['reject_qty'];
        $stock_added  = $norm['stock_added'];

        if ( $ordered_qty <= 0 || $product_id <= 0 ) {
            continue;
        }

        // Save latest values before applying.
        $wpdb->update(
            $tbl_lines,
            $norm['update'],
            array( 'id' => $line_id, 'sheet_id' => $sheet_id ),
            array( '%f', '%f', '%f', '%s', '%s', '%s' ),
            array( '%d', '%d' )
        );

        $accepted_qty   = max( 0.0, $received_qty - $reject_qty );
        $max_stockable  = max( 0.0, $ordered_qty - $missing_qty - $reject_qty );
        $target_stocked = min( $accepted_qty, $max_stockable );
        $stock_added_int = (int) round( $stock_added );
        $target_int      = (int) round( $target_stocked );
        $delta_int       = $target_int - $stock_added_int;
        if ( 0 === $delta_int ) {
            continue;
        }

        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
        if ( ! $product ) {
            $skipped[] = array(
                'line_id'    => $line_id,
                'product_id' => $product_id,
                'reason'     => 'missing_product',
            );
            continue;
        }

        if ( method_exists( $product, 'managing_stock' ) && ! $product->managing_stock() ) {
            $skipped[] = array(
                'line_id'    => $line_id,
                'product_id' => $product_id,
                'reason'     => 'not_managing_stock',
            );
            continue;
        }

        if ( ! function_exists( 'wc_update_product_stock' ) ) {
            $skipped[] = array(
                'line_id'    => $line_id,
                'product_id' => $product_id,
                'reason'     => 'stock_api_missing',
            );
            continue;
        }

        $delta_qty = abs( $delta_int );
        $direction = ( $delta_int > 0 ) ? 'increase' : 'decrease';
        $result = wc_update_product_stock( $product, $delta_qty, $direction );
        if ( is_wp_error( $result ) ) {
            $skipped[] = array(
                'line_id'    => $line_id,
                'product_id' => $product_id,
                'reason'     => 'stock_update_failed',
                'message'    => $result->get_error_message(),
            );
            continue;
        }

        $new_stock_added = $target_int;

        $wpdb->update(
            $tbl_lines,
            array(
                'goods_in_stock_added_qty' => $new_stock_added,
                'goods_in_updated_at'      => current_time( 'mysql', true ),
            ),
            array( 'id' => $line_id, 'sheet_id' => $sheet_id ),
            array( '%f', '%s' ),
            array( '%d', '%d' )
        );

        $applied_lines++;
        $applied_qty += $delta_qty;
    }

    // Store last apply report for UI.
    $transient_key = 'sop_goodsin_last_apply_' . get_current_user_id() . '_' . $sheet_id;
    set_transient(
        $transient_key,
        array(
            'applied_lines' => $applied_lines,
            'applied_qty'   => $applied_qty,
            'skipped'       => $skipped,
        ),
        HOUR_IN_SECONDS
    );

    wp_safe_redirect(
        add_query_arg(
            array(
                'sop_msg'        => 'applied',
                'sop_applied'    => $applied_lines,
                'sop_applied_qty'=> $applied_qty,
                'sop_skipped'    => count( $skipped ),
            ),
            $redirect
        )
    );
    exit;
}

/**
 * Complete goods-in for a sheet when all ordered qty is accounted for.
 */
function sop_handle_goodsin_complete() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access Goods In.', 'sop' ) );
    }

    check_admin_referer( 'sop_goodsin_action', 'sop_goodsin_nonce' );

    $payload  = sop_goodsin_get_payload_from_post();
    $sheet_id = isset( $payload['sheet_id'] ) ? (int) $payload['sheet_id'] : 0;

    $redirect = add_query_arg(
        array(
            'page'     => 'sop-goods-in',
            'sheet_id' => $sheet_id,
        ),
        admin_url( 'admin.php' )
    );

    if ( $sheet_id <= 0 ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'invalid_payload', $redirect ) );
        exit;
    }

    $sheet = sop_goodsin_get_sheet( $sheet_id );
    if ( ! $sheet ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_found', $redirect ) );
        exit;
    }

    $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
    if ( in_array( $status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_readonly', $redirect ) );
        exit;
    }
    if ( 'locked' !== $status && 'receiving' !== $status ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_lockable', $redirect ) );
        exit;
    }

    $lines_map = sop_goodsin_get_sheet_lines_map( $sheet_id );
    if ( empty( $lines_map ) ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'no_lines', $redirect ) );
        exit;
    }

    $outstanding_lines = 0;
    foreach ( $lines_map as $row ) {
        $ordered_qty = isset( $row['qty_owner'] ) ? (float) $row['qty_owner'] : 0.0;
        if ( $ordered_qty <= 0 ) {
            continue;
        }

        $missing_qty = isset( $row['goods_in_missing_qty'] ) ? (float) $row['goods_in_missing_qty'] : 0.0;
        $reject_qty  = isset( $row['goods_in_reject_qty'] ) ? (float) $row['goods_in_reject_qty'] : 0.0;
        $stock_added = isset( $row['goods_in_stock_added_qty'] ) ? (float) $row['goods_in_stock_added_qty'] : 0.0;

        $outstanding = $ordered_qty - $stock_added - $missing_qty - $reject_qty;
        if ( $outstanding > 0.0001 ) {
            $outstanding_lines++;
        }
    }

    if ( $outstanding_lines > 0 ) {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'sop_msg' => 'cannot_complete',
                    'sop_outstanding_lines' => $outstanding_lines,
                ),
                $redirect
            )
        );
        exit;
    }

    if ( function_exists( 'sop_update_preorder_sheet' ) ) {
        sop_update_preorder_sheet( $sheet_id, array( 'status' => 'received' ) );
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'sop_msg' => 'completed',
                'view'    => 'report',
            ),
            $redirect
        )
    );
    exit;
}

/**
 * AJAX: Apply stock for a single Goods-In line.
 */
function sop_ajax_goodsin_apply_stock_line() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to apply stock.', 'sop' ) ), 403 );
    }

    check_ajax_referer( 'sop_goodsin_ajax', 'nonce' );

    $sheet_id   = isset( $_POST['sheet_id'] ) ? (int) $_POST['sheet_id'] : 0;
    $line_json  = isset( $_POST['line_json'] ) ? wp_unslash( $_POST['line_json'] ) : '';
    $reset_report = ! empty( $_POST['reset_report'] );

    if ( $sheet_id <= 0 || '' === $line_json ) {
        wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'sop' ) ), 400 );
    }

    $sheet = sop_goodsin_get_sheet( $sheet_id );
    if ( ! $sheet ) {
        wp_send_json_error( array( 'message' => __( 'Sheet not found.', 'sop' ) ), 404 );
    }

    $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
    if ( in_array( $status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Sheet is read-only.', 'sop' ) ), 400 );
    }
    if ( 'locked' !== $status && 'receiving' !== $status ) {
        wp_send_json_error( array( 'message' => __( 'Sheet must be Ordered to apply stock.', 'sop' ) ), 400 );
    }

    $line_in = json_decode( $line_json, true );
    if ( ! is_array( $line_in ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid line payload.', 'sop' ) ), 400 );
    }

    $line_id    = isset( $line_in['line_id'] ) ? (int) $line_in['line_id'] : 0;
    $product_id = isset( $line_in['product_id'] ) ? (int) $line_in['product_id'] : 0;
    if ( $line_id <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Missing line ID.', 'sop' ) ), 400 );
    }

    $lines_map = sop_goodsin_get_sheet_lines_map( $sheet_id );
    if ( ! empty( $lines_map ) && function_exists( 'sop_goodsin_migrate_lines_map_to_pid' ) ) {
        list( $lines_map ) = sop_goodsin_migrate_lines_map_to_pid( $lines_map );
    }
    if ( empty( $lines_map ) || ! isset( $lines_map[ $line_id ] ) ) {
        wp_send_json_error( array( 'message' => __( 'Line not found for sheet.', 'sop' ) ), 404 );
    }

    $db_row = $lines_map[ $line_id ];
    $sku    = '';
    if ( ! empty( $db_row['sku_owner'] ) ) {
        $sku = (string) $db_row['sku_owner'];
    } elseif ( ! empty( $line_in['sku'] ) ) {
        $sku = (string) $line_in['sku'];
    }
    $db_pid = isset( $db_row['product_id'] ) ? (int) $db_row['product_id'] : 0;
    if ( $product_id <= 0 && $db_pid > 0 ) {
        $product_id = $db_pid;
    } elseif ( $product_id > 0 && $db_pid > 0 && $db_pid !== $product_id ) {
        wp_send_json_error( array( 'message' => __( 'Product mismatch for line.', 'sop' ) ), 400 );
    }

    $norm         = sop_goodsin_normalize_line_payload( $line_in, $db_row, array( 'allow_lower_received' => true ) );
    $ordered_qty  = $norm['ordered_qty'];
    $received_qty = $norm['received_qty'];
    $missing_qty  = $norm['missing_qty'];
    $reject_qty   = $norm['reject_qty'];
    $stock_added  = $norm['stock_added'];

    global $wpdb;
    $tbl_lines = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';
    if ( '' === $tbl_lines ) {
        $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
    }

    $wpdb->update(
        $tbl_lines,
        $norm['update'],
        array( 'id' => $line_id, 'sheet_id' => $sheet_id ),
        array( '%f', '%f', '%f', '%s', '%s', '%s' ),
        array( '%d', '%d' )
    );

    $stock_added_qty = $stock_added;
    $result_data = array(
        'line_id'     => $line_id,
        'product_id'  => $product_id,
        'status'      => 'noop',
        'applied_qty' => 0.0,
        'stock_added_qty' => $stock_added_qty,
        'outstanding_qty' => 0.0,
        'is_complete' => false,
        'sku'         => $sku,
    );

    if ( $ordered_qty <= 0 || $product_id <= 0 ) {
        $result_data['status'] = 'skipped';
        $result_data['reason'] = 'invalid_qty_or_product';
    } else {
        $accepted_qty   = max( 0.0, $received_qty - $reject_qty );
        $max_stockable  = max( 0.0, $ordered_qty - $missing_qty - $reject_qty );
        $target_stocked = min( $accepted_qty, $max_stockable );
        $stock_added_int = (int) round( $stock_added );
        $target_int      = (int) round( $target_stocked );
        $delta_int       = $target_int - $stock_added_int;

        if ( 0 === $delta_int ) {
            $result_data['status'] = 'noop';
        } else {
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
            if ( ! $product ) {
                $result_data['status']  = 'skipped';
                $result_data['reason']  = 'missing_product';
            } elseif ( method_exists( $product, 'managing_stock' ) && ! $product->managing_stock() ) {
                $result_data['status']  = 'skipped';
                $result_data['reason']  = 'not_managing_stock';
            } elseif ( ! function_exists( 'wc_update_product_stock' ) ) {
                $result_data['status']  = 'skipped';
                $result_data['reason']  = 'stock_api_missing';
            } else {
                $delta_qty = abs( $delta_int );
                $direction = ( $delta_int > 0 ) ? 'increase' : 'decrease';
                $result = wc_update_product_stock( $product, $delta_qty, $direction );
                if ( is_wp_error( $result ) ) {
                    $product_retry = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
                    if ( $product_retry && function_exists( 'wc_update_product_stock' ) ) {
                        $result = wc_update_product_stock( $product_retry, $delta_qty, $direction );
                    }
                }
                if ( is_wp_error( $result ) ) {
                    $result_data['status']  = 'skipped';
                    $result_data['reason']  = 'stock_update_failed';
                    $result_data['message'] = $result->get_error_message();
                } else {
                    $stock_added_qty = $target_int;
                    $wpdb->update(
                        $tbl_lines,
                        array(
                            'goods_in_stock_added_qty' => $stock_added_qty,
                            'goods_in_updated_at'      => current_time( 'mysql', true ),
                        ),
                        array( 'id' => $line_id, 'sheet_id' => $sheet_id ),
                        array( '%f', '%s' ),
                        array( '%d', '%d' )
                    );
                    $result_data['status']      = 'applied';
                    $result_data['applied_qty'] = $delta_qty;
                    $result_data['applied_direction'] = ( $delta_int > 0 ) ? 'increase' : 'decrease';
                }
            }
        }
    }

    $outstanding_qty = max( 0.0, $ordered_qty - $stock_added_qty - $missing_qty - $reject_qty );
    $result_data['stock_added_qty'] = $stock_added_qty;
    $result_data['outstanding_qty'] = $outstanding_qty;
    $result_data['is_complete']     = ( $outstanding_qty <= 0.0001 );
    $reason_labels = array(
        'invalid_qty_or_product' => __( 'Invalid qty / product', 'sop' ),
        'missing_product'        => __( 'Missing product', 'sop' ),
        'not_managing_stock'     => __( 'Stock management disabled', 'sop' ),
        'stock_api_missing'      => __( 'Woo stock API missing', 'sop' ),
        'stock_update_failed'    => __( 'Woo stock update failed', 'sop' ),
    );
    if ( ! empty( $result_data['reason'] ) && isset( $reason_labels[ $result_data['reason'] ] ) ) {
        $result_data['reason_label'] = $reason_labels[ $result_data['reason'] ];
    }

    $transient_key = 'sop_goodsin_last_apply_' . get_current_user_id() . '_' . $sheet_id;
    $report = array(
        'applied_lines' => 0,
        'applied_qty'   => 0.0,
        'skipped'       => array(),
    );
    if ( ! $reset_report ) {
        $existing = get_transient( $transient_key );
        if ( is_array( $existing ) ) {
            $report = array_merge( $report, $existing );
            if ( empty( $report['skipped'] ) ) {
                $report['skipped'] = array();
            }
        }
    }

    if ( 'applied' === $result_data['status'] ) {
        $report['applied_lines'] = isset( $report['applied_lines'] ) ? (int) $report['applied_lines'] + 1 : 1;
        $report['applied_qty']   = isset( $report['applied_qty'] ) ? (float) $report['applied_qty'] + (float) $result_data['applied_qty'] : (float) $result_data['applied_qty'];
    } elseif ( 'noop' !== $result_data['status'] ) {
        $report['skipped'][] = array(
            'line_id'    => $line_id,
            'product_id' => $product_id,
            'sku'        => $sku,
            'reason'     => isset( $result_data['reason'] ) ? $result_data['reason'] : 'skipped',
            'message'    => isset( $result_data['message'] ) ? $result_data['message'] : '',
        );
    }

    set_transient( $transient_key, $report, HOUR_IN_SECONDS );

    wp_send_json_success(
        array(
            'result' => $result_data,
            'report' => array(
                'applied_lines' => isset( $report['applied_lines'] ) ? (int) $report['applied_lines'] : 0,
                'applied_qty'   => isset( $report['applied_qty'] ) ? (float) $report['applied_qty'] : 0.0,
                'skipped_count' => isset( $report['skipped'] ) && is_array( $report['skipped'] ) ? count( $report['skipped'] ) : 0,
            ),
        )
    );
}

add_action( 'admin_post_sop_goodsin_save', 'sop_handle_goodsin_save' );
add_action( 'admin_post_sop_goodsin_apply_stock', 'sop_handle_goodsin_apply_stock' );
add_action( 'admin_post_sop_goodsin_complete', 'sop_handle_goodsin_complete' );
add_action( 'admin_post_sop_export_goodsin_issues_xlsx', 'sop_handle_export_goodsin_issues_xlsx' );
add_action( 'wp_ajax_sop_goodsin_apply_stock_line', 'sop_ajax_goodsin_apply_stock_line' );
function sop_handle_export_goodsin_issues_xlsx() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You are not allowed to export goods-in issues.', 'sop' ) );
    }

    $nonce = isset( $_REQUEST['sop_goodsin_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['sop_goodsin_export_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( '' === $nonce && isset( $_REQUEST['sop_export_goodsin_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['sop_export_goodsin_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    if ( ! wp_verify_nonce( $nonce, 'sop_export_goodsin_issues_xlsx' ) ) {
        wp_die( esc_html__( 'Invalid export request.', 'sop' ) );
    }

    $sheet_id = isset( $_REQUEST['sop_sheet_id'] ) ? (int) $_REQUEST['sop_sheet_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( $sheet_id <= 0 && isset( $_REQUEST['sheet_id'] ) ) {
        $sheet_id = (int) $_REQUEST['sheet_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    if ( $sheet_id <= 0 ) {
        wp_die( esc_html__( 'Missing sheet ID.', 'sop' ) );
    }

    if ( ! function_exists( 'sop_get_preorder_sheet' ) || ! function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        wp_die( esc_html__( 'Sheet helpers not available.', 'sop' ) );
    }

    $sheet = sop_get_preorder_sheet( $sheet_id );
    if ( ! is_array( $sheet ) ) {
        wp_die( esc_html__( 'Sheet not found.', 'sop' ) );
    }

    $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
    if ( 'received' !== $status ) {
        wp_die( esc_html__( 'Goods-In is not completed yet.', 'sop' ) );
    }

    $lines = sop_get_preorder_sheet_lines( $sheet_id, true );
    $lines = is_array( $lines ) ? $lines : array();
    $supplier_id = isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0;
    $supplier_currency = 'GBP';
    if ( $supplier_id > 0 && function_exists( 'sop_preorder_resolve_supplier_params' ) ) {
        $ctx = sop_preorder_resolve_supplier_params( $supplier_id );
        if ( ! empty( $ctx['currency_code'] ) ) {
            $supplier_currency = strtoupper( trim( (string) $ctx['currency_code'] ) );
        }
    }

    $sheet_balance_fx = sop_goodsin_get_balance_fx_rate_from_sheet( $sheet );
    $sop_fx_settings  = sop_goodsin_get_sop_settings_fx_rates();

    if ( ! empty( $lines ) && function_exists( 'sop_hydrate_line_with_live_product_fields' ) ) {
        foreach ( $lines as $idx => $line ) {
            $lines[ $idx ] = sop_hydrate_line_with_live_product_fields( $line, $supplier_id );
        }
    }

    $issue_lines = array();
    foreach ( $lines as $line ) {
        $missing = sop_goodsin_get_number_from_line( $line, array( 'goods_in_missing_qty_owner', 'goods_in_missing_qty' ) );
        $reject  = sop_goodsin_get_number_from_line( $line, array( 'goods_in_reject_qty_owner', 'goods_in_reject_qty' ) );
        if ( $missing <= 0 && $reject <= 0 ) {
            continue;
        }

        // Start from full line to preserve preorder/base fields.
        $row = $line;
        $row['ordered_qty']          = sop_goodsin_get_number_from_line( $line, array( 'qty_owner', 'qty', 'ordered_qty' ) );
        $row['received_qty']         = sop_goodsin_get_number_from_line( $line, array( 'goods_in_received_qty_owner', 'goods_in_received_qty' ) );
        $row['goods_in_missing_qty'] = $missing;
        $row['goods_in_reject_qty']  = $reject;
        $row['missing_qty']          = $missing;
        $row['reject_qty']           = $reject;
        $row['reject_reason']        = isset( $line['goods_in_reject_reason'] ) ? (string) $line['goods_in_reject_reason'] : ( isset( $line['reject_reason'] ) ? (string) $line['reject_reason'] : '' );
        $row['goods_in_notes']       = isset( $line['goods_in_notes'] ) ? (string) $line['goods_in_notes'] : ( isset( $line['goods_in_notes_owner'] ) ? (string) $line['goods_in_notes_owner'] : '' );
        if ( empty( $row['carton_no'] ) && ! empty( $line['carton_number'] ) ) {
            $row['carton_no'] = (string) $line['carton_number'];
        }
        $row['qty'] = $row['ordered_qty'];

        // Hydrate missing display fields from live product if available.
        if ( empty( $row['product_id'] ) && ! empty( $row['sku'] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
            $pid = (int) wc_get_product_id_by_sku( (string) $row['sku'] );
            if ( $pid > 0 ) {
                $row['product_id'] = $pid;
            }
        }
        if ( function_exists( 'sop_get_live_product_display_fields' ) && ! empty( $row['product_id'] ) ) {
            $live = sop_get_live_product_display_fields( (int) $row['product_id'], $supplier_id );
            if ( is_array( $live ) ) {
                $row['sku']          = ! empty( $row['sku'] ) ? $row['sku'] : ( isset( $live['sku'] ) ? $live['sku'] : '' );
                $row['brand']        = ! empty( $row['brand'] ) ? $row['brand'] : ( isset( $live['brand'] ) ? $live['brand'] : '' );
                $row['product_name'] = ! empty( $row['product_name'] ) ? $row['product_name'] : ( isset( $live['product_name'] ) ? $live['product_name'] : '' );
                $row['categories']   = ! empty( $row['categories'] ) ? $row['categories'] : ( isset( $live['category'] ) ? $live['category'] : '' );
                $row['product_notes'] = ! empty( $row['product_notes'] ) ? $row['product_notes'] : ( isset( $live['product_notes'] ) ? $live['product_notes'] : '' );
                if ( empty( $row['cm3_per_unit'] ) && isset( $live['cubic_cm'] ) ) {
                    $row['cm3_per_unit'] = $live['cubic_cm'];
                }
                if ( empty( $row['line_cbm'] ) && isset( $live['cbm_total'] ) ) {
                    $row['line_cbm'] = $live['cbm_total'];
                }
                if ( empty( $row['image_id'] ) && isset( $live['image_id'] ) ) {
                    $row['image_id'] = (int) $live['image_id'];
                }
            }
        }

        // Resolve supplier-currency unit cost for non-RMB suppliers so XLSX unit price/total are populated.
        $unit_cost_supplier = 0.0;
        $unit_cost_rmb      = sop_goodsin_get_positive_number_from_line(
            $row,
            array(
                'cost_rmb_owner',
                'cost_rmb',
                'cost_per_unit_rmb',
                'cost_rmb_per_unit',
                '_sop_cost_rmb',
            )
        );

        // Supplier-currency costs already on the line.
        $unit_cost_supplier = sop_goodsin_get_positive_number_from_line(
            $row,
            array(
                'cost_supplier_owner',
                'cost_supplier',
                'supplier_cost_owner',
                'supplier_cost',
                'unit_cost',
                'cost_per_unit',
                'cost_owner',
                'cost',
                'cost_' . strtolower( $supplier_currency ) . '_owner',
                'cost_' . strtolower( $supplier_currency ),
                'supplier_cost_' . strtolower( $supplier_currency ) . '_owner',
                'supplier_cost_' . strtolower( $supplier_currency ),
                'unit_cost_' . strtolower( $supplier_currency ),
            )
        );

        // If still missing, try product meta (COGS).
        if ( $unit_cost_supplier <= 0 && ! empty( $row['product_id'] ) && function_exists( 'get_post_meta' ) ) {
            $cog_meta_keys = array( '_cogs_value', '_cost_of_goods', '_cogs_total_value' );
            foreach ( $cog_meta_keys as $meta_key ) {
                $meta_val = get_post_meta( (int) $row['product_id'], $meta_key, true );
                if ( '' !== $meta_val ) {
                    $parsed = (float) $meta_val;
                    if ( $parsed > 0 ) {
                        $unit_cost_supplier = $parsed;
                        break;
                    }
                }
            }
            // Also try RMB product meta if supplier cost still missing.
            if ( $unit_cost_supplier <= 0 ) {
                $meta_rmb = get_post_meta( (int) $row['product_id'], '_sop_cost_rmb', true );
                if ( '' !== $meta_rmb && $unit_cost_rmb <= 0 ) {
                    $parsed_rmb = (float) $meta_rmb;
                    if ( $parsed_rmb > 0 ) {
                        $unit_cost_rmb = $parsed_rmb;
                    }
                }
            }
        }

        // Convert RMB to supplier currency if needed (GBP/EUR/USD).
        if ( 'RMB' !== $supplier_currency && $unit_cost_supplier <= 0 && $unit_cost_rmb > 0 ) {
            $unit_cost_supplier = sop_goodsin_convert_rmb_to_currency( $unit_cost_rmb, $supplier_currency, $sheet_balance_fx, $sop_fx_settings );
        }

        // Persist costs back onto the row for the exporter.
        if ( $unit_cost_supplier > 0 ) {
            $row['cost_supplier_owner'] = $unit_cost_supplier;
        }
        if ( $unit_cost_rmb > 0 ) {
            $row['cost_rmb_owner'] = $unit_cost_rmb;
        }

        $issue_lines[] = $row;
    }

    if ( empty( $issue_lines ) ) {
        wp_die( esc_html__( 'No missing/rejected lines to export.', 'sop' ) );
    }

    if ( ! class_exists( 'SOP_Preorder_XLSX_Exporter' ) ) {
        $exporter_path = trailingslashit( dirname( __DIR__ ) ) . 'includes/class-sop-preorder-exporter-xlsx.php';
        if ( file_exists( $exporter_path ) ) {
            require_once $exporter_path;
        }
    }

    if ( ! class_exists( 'SOP_Preorder_XLSX_Exporter' ) ) {
        wp_die( esc_html__( 'Exporter class missing.', 'sop' ) );
    }

    $xlsx_path = SOP_Preorder_XLSX_Exporter::build_goodsin_issues_xlsx_file( $sheet, $issue_lines );
    if ( is_wp_error( $xlsx_path ) ) {
        wp_die(
            '<strong>' . esc_html__( 'XLSX Export Error', 'sop' ) . '</strong><br />' . esc_html( $xlsx_path->get_error_message() )
        );
    }

    $supplier_slug = 'supplier';
    if ( function_exists( 'sop_supplier_get_by_id' ) && isset( $sheet['supplier_id'] ) ) {
        $s = sop_supplier_get_by_id( (int) $sheet['supplier_id'] );
        if ( is_object( $s ) && isset( $s->slug ) ) {
            $supplier_slug = sanitize_title( $s->slug );
        } elseif ( is_array( $s ) && isset( $s['slug'] ) ) {
            $supplier_slug = sanitize_title( $s['slug'] );
        }
    }

    $filename = sprintf( 'goods-in-issues-%s-%d.xlsx', $supplier_slug, (int) $sheet_id );

    if ( function_exists( 'sop_export_send_file_and_exit' ) ) {
        sop_export_send_file_and_exit(
            $xlsx_path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $xlsx_path ) );

    readfile( $xlsx_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
    @unlink( $xlsx_path );
    exit;
}

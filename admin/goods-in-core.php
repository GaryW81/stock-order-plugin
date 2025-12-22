<?php
/**
 * Stock Order Plugin - Phase 5 (Goods-In v1) - Core (admin only)
 * File version: 1.0.05
 *
 * - Receive against locked/receiving preorder sheets.
 * - Save receiving progress, apply stock increases, and complete goods-in.
 * - Uses JSON payload to avoid max_input_vars on large sheets.
 * - 1.0.02 - Add live display hydration helper for Goods-In lines (display only).
 * - 1.0.03 - Key Goods-In handlers by product_id (SKU fallback) and normalise POST maps.
 * - 1.0.04 - Apply product_id normalisation across all Goods-In handlers (SKU fallback).
 * - 1.0.05 - Lazy-migrate legacy lines/maps to product_id and warn on unresolved SKUs.
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
 * @return array
 */
function sop_goodsin_normalize_line_payload( array $line_in, array $db_row ) {
    $ordered_qty = isset( $db_row['qty_owner'] ) ? (float) $db_row['qty_owner'] : 0.0;
    $stock_added = isset( $db_row['goods_in_stock_added_qty'] ) ? (float) $db_row['goods_in_stock_added_qty'] : 0.0;
    if ( $ordered_qty < 0 ) {
        $ordered_qty = 0.0;
    }
    if ( $stock_added < 0 ) {
        $stock_added = 0.0;
    }

    $received_qty = isset( $line_in['received_qty'] ) ? (float) $line_in['received_qty'] : 0.0;
    $missing_qty  = isset( $line_in['missing_qty'] ) ? (float) $line_in['missing_qty'] : 0.0;
    $reject_qty   = isset( $line_in['reject_qty'] ) ? (float) $line_in['reject_qty'] : 0.0;
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
    if ( $reject_qty > $received_qty ) {
        $reject_qty = $received_qty;
    }

    $max_other = max( 0.0, $ordered_qty - $stock_added );
    if ( ( $missing_qty + $reject_qty ) > $max_other ) {
        $reject_qty  = min( $reject_qty, $max_other );
        $missing_qty = max( 0.0, $max_other - $reject_qty );
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
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
    if ( 'locked' !== $status && 'receiving' !== $status ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_lockable', $redirect ) );
        exit;
    }

    $lines_map = sop_goodsin_get_sheet_lines_map( $sheet_id );
    if ( ! empty( $lines_map ) && function_exists( 'sop_goodsin_migrate_lines_map_to_pid' ) ) {
        list( $lines_map, $unresolved_skus ) = sop_goodsin_migrate_lines_map_to_pid( $lines_map );
        if ( ! empty( $unresolved_skus ) && current_user_can( 'manage_woocommerce' ) ) {
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
    // Ensure product_id is present for each payload line (SKU fallback).
    $payload['lines'] = sop_goodsin_normalize_payload_lines( $payload['lines'], $lines_map );
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

    // Move sheet to receiving on first save.
    if ( 'locked' === $status && function_exists( 'sop_update_preorder_sheet' ) ) {
        sop_update_preorder_sheet( $sheet_id, array( 'status' => 'receiving' ) );
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
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
    if ( 'locked' !== $status && 'receiving' !== $status ) {
        wp_safe_redirect( add_query_arg( 'sop_msg', 'sheet_not_lockable', $redirect ) );
        exit;
    }

    $lines_map = sop_goodsin_get_sheet_lines_map( $sheet_id );
    if ( ! empty( $lines_map ) && function_exists( 'sop_goodsin_migrate_lines_map_to_pid' ) ) {
        list( $lines_map, $unresolved_skus ) = sop_goodsin_migrate_lines_map_to_pid( $lines_map );
        if ( ! empty( $unresolved_skus ) && current_user_can( 'manage_woocommerce' ) ) {
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
        $norm      = sop_goodsin_normalize_line_payload( $line_in, $db_row );
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

        $accepted_qty = max( 0.0, $received_qty - $reject_qty );
        $to_apply     = max( 0.0, $accepted_qty - $stock_added );
        $remaining_for_apply = max( 0.0, $ordered_qty - $stock_added - $missing_qty - $reject_qty );
        if ( $to_apply > $remaining_for_apply ) {
            $to_apply = $remaining_for_apply;
        }

        if ( $to_apply <= 0 ) {
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

        $result = wc_update_product_stock( $product, $to_apply, 'increase' );
        if ( is_wp_error( $result ) ) {
            $skipped[] = array(
                'line_id'    => $line_id,
                'product_id' => $product_id,
                'reason'     => 'stock_update_failed',
                'message'    => $result->get_error_message(),
            );
            continue;
        }

        $new_stock_added = $stock_added + $to_apply;

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
        $applied_qty += $to_apply;
    }

    // Move sheet to receiving when applying stock.
    if ( 'locked' === $status && function_exists( 'sop_update_preorder_sheet' ) ) {
        sop_update_preorder_sheet( $sheet_id, array( 'status' => 'receiving' ) );
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
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
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

add_action( 'admin_post_sop_goodsin_save', 'sop_handle_goodsin_save' );
add_action( 'admin_post_sop_goodsin_apply_stock', 'sop_handle_goodsin_apply_stock' );
add_action( 'admin_post_sop_goodsin_complete', 'sop_handle_goodsin_complete' );

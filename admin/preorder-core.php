<?php
/**
 * Stock Order Plugin - Phase 4.1 - Pre-Order Sheet Core (admin only)
 * File version: 11.70
 * - 11.70 - Harden XLSX download streaming (clear output buffers) to prevent Excel repair warnings.
 * - 11.69 - Canonicalise product notes meta key to _sop_product_notes.
 * - 11.68 - Save internal product notes from preorder sheet.
 * - 11.67 - Fix In Progress status pill selector for contrast styles.
 * - 11.66 - UI: improve In Progress status pill contrast.
 * - 11.65 - Use underscored supplier ID field name.
 * - 11.64 - Store removed and qty state per sheet line (no product meta).
 * - 11.63 - Add top padding to status pill dashicons for vertical centering.
 * - 11.62 - Align status pill dashicons vertically with label text.
 * - UI: 3-stage status labels (In Progress/Ordered/Completed) + GI started indicator.
 * - Migrate legacy receiving sheets to locked and keep Ordered/Completed wording.
 * - Persist supplier preorder_hidden_columns.
 * - Persist preorder container planning values in PO payload for saved sheets.
 * - Saved sheets: include receiving/received, allow unlock for receiving, add Goods-In link.
 * - Enforce readonly for non-draft sheets (view-only actions and server-side guard).
 * - Saved sheets: default all suppliers and show status badges.
 * - Saved sheets: glossy status pills for clearer visibility.
 * - Remove legacy XLS export endpoints (XLSX only).
 * - Remove Labels (CSV) export for saved Pre-Order sheets.
 * - Hydrate saved sheet display/export lines with live product data (preserve saved stock snapshot).
 * - Inbound: treat locked sheet quantities as inbound stock (single grouped query) and pass into forecast so SOQ accounts for inbound.
 * - GBP suppliers: COGS resolver reads Woo meta + postmeta (and parent for variations); missing cost returns blank (NULL) for display.
 * - Cleanup: remove sop_debug_costs tooling; keep minimal COGS key list.
 * - GBP suppliers: cost priority = COGS → RMB converted → blank.
 * - Export: exclude removed and zero-qty lines from order sheet XLS.
 * - Carry container planning params (pallet/allowance) through save redirects and accept pallet layer from save form.
 * - Add SOQ forecast context for tooltip ("Why" trust SOQ) and accept sop_lines_json payload to avoid max_input_vars truncation on large sheets.
 * - Add PO XLS export handler (order sheet export unchanged).
 * - Add embedded XLSX export (images embedded, no external URLs).
 * - Add Purchase Order XLSX export handler.
 * - Clear balance FX/ USD when deposit FX is not locked.
 * - Add Purchase Order header fields (dates, deposits, PO extras) with FX and holiday dates for saved sheets, centralised parsing.
 * - 11.17 - Ensure Purchase Order modal fields are explicitly persisted on save (insert/update).
 * - 11.18 - Parse PO JSON payload (sop_po_payload) and log last POST for debugging.
 * - 11.19 - Persist PO extras within header_notes_owner.
 * - 11.20 - Ensure PO extras are normalised and stored under header_notes_owner['po_extras'].
 * - 11.21 - Add PO extras debug count and JSON payload persistence tweaks.
 * - 11.45 - PO XLSX uses committed template for Order Summary.
 * - Under Stock Order main menu.
 * - Supplier filter via _sop_supplier_id.
 * - Supplier currency-aware costs using plugin meta:
 *      _sop_cost_rmb, _sop_cost_usd, _sop_cost_eur, fallback _cogs_value for GBP.
 * - Editable & persisted per product:
 *      Order SKU (sheet-only) -> meta: _sop_preorder_order_sku
 *      Notes              -> meta: _sop_product_notes
 *      Min order qty      -> meta: _sop_min_order_qty
 *      Manual order qty   -> sheet line qty_owner
 *      Cost per unit      -> meta: _sop_cost_rmb / _sop_cost_usd / _sop_cost_eur / _cogs_value
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sop_preorder_get_stock_order_parent_slug() {
    global $menu;

    $parent_slug = 'woocommerce';

    if ( is_array( $menu ) ) {
        foreach ( $menu as $item ) {
            if ( ! isset( $item[0], $item[2] ) ) {
                continue;
            }

            $title = trim( wp_strip_all_tags( $item[0] ) );
            if ( $title === 'Stock Order' ) {
                $parent_slug = $item[2];
                break;
            }
        }
    }

    return $parent_slug;
}

/**
 * Migrate saved sheet lines to ensure product_id is present (lazy migration).
 *
 * @param array $sheet Sheet array with 'id' and 'supplier_id'.
 * @return array Two-item array: array( $sheet, $migration_meta ).
 */
function sop_preorder_migrate_saved_sheet_lines_to_pid( array $sheet ) {
    $migration_meta = array(
        'changed'         => false,
        'unresolved_skus' => array(),
    );

    if ( empty( $sheet['id'] ) || ! function_exists( 'sop_get_preorder_sheet_lines' ) || ! function_exists( 'sop_replace_preorder_sheet_lines' ) ) {
        return array( $sheet, $migration_meta );
    }

    $sheet_id = (int) $sheet['id'];
    $lines    = sop_get_preorder_sheet_lines( $sheet_id );
    $lines    = is_array( $lines ) ? $lines : array();

    if ( empty( $lines ) ) {
        return array( $sheet, $migration_meta );
    }

    $protected_keys = array(
        'stock_on_hand',
        'current_stock',
        'stock_qty',
        'saved_stock',
        'stock_at_save',
        'stock_snapshot',
        'stock_on_hand_saved',
    );

    $unresolved = array();
    $changed    = false;

    foreach ( $lines as $idx => $line ) {
        $pid = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
        $sku = isset( $line['sku_owner'] ) ? (string) $line['sku_owner'] : '';

        if ( $pid <= 0 && '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
            $resolved = wc_get_product_id_by_sku( $sku );
            if ( $resolved > 0 ) {
                $lines[ $idx ]['product_id'] = (int) $resolved;
                $changed = true;
            } else {
                $unresolved[] = $sku;
            }
        }

        // Ensure protected snapshot keys remain untouched (no action needed here other than awareness).
        foreach ( $protected_keys as $pkey ) {
            if ( array_key_exists( $pkey, $line ) && ! array_key_exists( $pkey, $lines[ $idx ] ) ) {
                $lines[ $idx ][ $pkey ] = $line[ $pkey ];
            }
        }
    }

    if ( $changed ) {
        $result = sop_replace_preorder_sheet_lines( $sheet_id, $lines );
        if ( ! is_wp_error( $result ) ) {
            $migration_meta['changed'] = true;
        }
    }

    if ( ! empty( $unresolved ) ) {
        $migration_meta['unresolved_skus'] = $unresolved;
    }

    return array( $sheet, $migration_meta );
}

/**
 * Normalise an input array keyed by product_id or SKU into product_id keys.
 *
 * @param array $raw
 * @return array
 */
function sop_preorder_normalize_pid_map( $raw ) {
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

add_action( 'admin_menu', 'sop_preorder_register_admin_menu', 99 );
function sop_preorder_register_admin_menu() {
    $parent_slug = sop_preorder_get_stock_order_parent_slug();

    add_submenu_page(
        $parent_slug,
        __( 'Pre-Order Sheet', 'sop' ),
        __( 'Pre-Order Sheet', 'sop' ),
        'manage_woocommerce',
        'sop-preorder-sheet',
        'sop_preorder_render_admin_page'
    );
}

/**
 * Update Purchase Order specific header fields from POST data.
 *
 * @param int $sheet_id Saved sheet ID.
 *
 * @return void
 */
function sop_preorder_update_po_header_from_post( $sheet_id ) {
    if ( $sheet_id <= 0 || ! function_exists( 'sop_update_preorder_sheet' ) ) {
        return;
    }

    $po_order_date        = '';
    $po_load_date         = '';
    $po_arrival_date      = '';
    $po_holiday_start     = '';
    $po_holiday_end       = '';
    $po_deposit_rmb       = 0.0;
    $po_deposit_usd       = 0.0;
    $po_deposit_fx_rate   = 0.0;
    $po_deposit_fx_locked = 0;
    $po_balance_fx_rate   = 0.0;
    $po_balance_usd       = 0.0;
    $po_balance_fx_locked = 0;
    $po_extras            = array();

    // Prefer JSON payload if present.
    $payload_raw = isset( $_POST['sop_po_payload'] ) ? wp_unslash( $_POST['sop_po_payload'] ) : '';
    $payload     = array();
    if ( '' !== $payload_raw ) {
        $decoded = json_decode( $payload_raw, true );
        if ( is_array( $decoded ) ) {
            $payload = $decoded;
        }
    }

    if ( ! empty( $payload ) ) {
        $po_order_date   = isset( $payload['order_date'] ) ? sanitize_text_field( $payload['order_date'] ) : '';
        $po_load_date    = isset( $payload['load_date'] ) ? sanitize_text_field( $payload['load_date'] ) : '';
        $po_arrival_date = isset( $payload['arrival_date'] ) ? sanitize_text_field( $payload['arrival_date'] ) : '';

        $po_holiday_start = isset( $payload['holiday_start'] ) ? sanitize_text_field( $payload['holiday_start'] ) : '';
        $po_holiday_end   = isset( $payload['holiday_end'] ) ? sanitize_text_field( $payload['holiday_end'] ) : '';

        $po_deposit_usd = isset( $payload['deposit_usd'] ) ? (float) $payload['deposit_usd'] : 0.0;
        $po_deposit_rmb = isset( $payload['deposit_rmb'] ) ? (float) $payload['deposit_rmb'] : 0.0;

        $po_deposit_fx_rate   = isset( $payload['deposit_fx_rate'] ) ? (float) $payload['deposit_fx_rate'] : 0.0;
        $po_deposit_fx_locked = ! empty( $payload['deposit_fx_locked'] ) ? 1 : 0;

        $po_balance_fx_rate   = isset( $payload['balance_fx_rate'] ) ? (float) $payload['balance_fx_rate'] : 0.0;
        $po_balance_usd       = isset( $payload['balance_usd'] ) ? (float) $payload['balance_usd'] : 0.0;
        $po_balance_fx_locked = ! empty( $payload['balance_fx_locked'] ) ? 1 : 0;

        $extras_source = array();
        if ( isset( $payload['po_extras'] ) && is_array( $payload['po_extras'] ) ) {
            $extras_source = $payload['po_extras'];
        } elseif ( ! empty( $payload['extras'] ) && is_array( $payload['extras'] ) ) {
            $extras_source = $payload['extras'];
        }

        if ( ! empty( $extras_source ) ) {
            foreach ( $extras_source as $extra_row ) {
                $label  = isset( $extra_row['label'] ) ? sanitize_text_field( $extra_row['label'] ) : '';
                $amount = isset( $extra_row['amount_rmb'] ) ? (float) $extra_row['amount_rmb'] : 0.0;
                if ( '' === $label && 0.0 === $amount ) {
                    continue;
                }
                $po_extras[] = array(
                    'label'      => $label,
                    'amount_rmb' => $amount,
                );
            }
        }
    } else {
        // Fallback to individual fields.
        $po_order_date   = isset( $_POST['sop_po_order_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_order_date'] ) ) : '';
        $po_load_date    = isset( $_POST['sop_po_load_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_load_date'] ) ) : '';
        $po_arrival_date = isset( $_POST['sop_po_arrival_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_arrival_date'] ) ) : '';
        $po_holiday_start = isset( $_POST['sop_po_holiday_start'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_holiday_start'] ) ) : '';
        $po_holiday_end   = isset( $_POST['sop_po_holiday_end'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_holiday_end'] ) ) : '';

        $po_deposit_rmb = isset( $_POST['sop_po_deposit_rmb'] ) ? (float) wp_unslash( $_POST['sop_po_deposit_rmb'] ) : 0.0;
        $po_deposit_usd = isset( $_POST['sop_po_deposit_usd'] ) ? (float) wp_unslash( $_POST['sop_po_deposit_usd'] ) : 0.0;
        $po_deposit_fx_rate = isset( $_POST['sop_po_deposit_fx_rate'] ) ? (float) wp_unslash( $_POST['sop_po_deposit_fx_rate'] ) : 0.0;
        $po_balance_fx_rate   = isset( $_POST['sop_po_balance_fx_rate'] ) ? (float) wp_unslash( $_POST['sop_po_balance_fx_rate'] ) : 0.0;
        $po_balance_usd       = isset( $_POST['sop_po_balance_usd'] ) ? (float) wp_unslash( $_POST['sop_po_balance_usd'] ) : 0.0;
        $po_balance_fx_locked = ! empty( $_POST['sop_po_balance_fx_locked'] ) ? 1 : 0;
        $po_deposit_fx_locked = ! empty( $_POST['sop_po_deposit_fx_locked'] ) ? 1 : 0;

        $labels_raw  = isset( $_POST['sop_po_extra_label'] ) && is_array( $_POST['sop_po_extra_label'] ) ? array_map( 'wp_unslash', (array) $_POST['sop_po_extra_label'] ) : array();
        $amounts_raw = isset( $_POST['sop_po_extra_amount'] ) && is_array( $_POST['sop_po_extra_amount'] ) ? array_map( 'wp_unslash', (array) $_POST['sop_po_extra_amount'] ) : array();

        $max_extras  = max( count( $labels_raw ), count( $amounts_raw ) );
        for ( $i = 0; $i < $max_extras; $i++ ) {
            $label  = isset( $labels_raw[ $i ] ) ? sanitize_text_field( $labels_raw[ $i ] ) : '';
            $amount = isset( $amounts_raw[ $i ] ) ? (float) $amounts_raw[ $i ] : 0.0;

            if ( '' === $label && 0.0 === $amount ) {
                continue;
            }

            $po_extras[] = array(
                'label'      => $label,
                'amount_rmb' => $amount,
            );
        }
    }

    if ( $po_deposit_rmb < 0 ) {
        $po_deposit_rmb = 0.0;
    }
    if ( $po_deposit_usd < 0 ) {
        $po_deposit_usd = 0.0;
    }
    if ( $po_deposit_fx_rate < 0 ) {
        $po_deposit_fx_rate = 0.0;
    }
    if ( $po_balance_fx_rate < 0 ) {
        $po_balance_fx_rate = 0.0;
    }
    $po_deposit_fx_rate = round( (float) $po_deposit_fx_rate, 3 );
    $po_balance_fx_rate = round( (float) $po_balance_fx_rate, 3 );
    $po_deposit_fx_rate = round( (float) $po_deposit_fx_rate, 3 );
    $po_balance_fx_rate = round( (float) $po_balance_fx_rate, 3 );
    if ( $po_balance_usd < 0 ) {
        $po_balance_usd = 0.0;
    }

    if ( $po_deposit_rmb <= 0 && $po_deposit_usd > 0 && $po_deposit_fx_rate > 0 ) {
        $po_deposit_rmb = $po_deposit_usd * $po_deposit_fx_rate;
    }

    // If deposit FX is not locked, balance FX/ USD should be treated as unset.
    if ( ! $po_deposit_fx_locked ) {
        $po_balance_fx_rate = 0.0;
        $po_balance_usd     = 0.0;
    }

    if ( $po_deposit_rmb < 0 ) {
        $po_deposit_rmb = 0.0;
    }
    if ( $po_deposit_usd < 0 ) {
        $po_deposit_usd = 0.0;
    }
    if ( $po_deposit_fx_rate < 0 ) {
        $po_deposit_fx_rate = 0.0;
    }
    if ( $po_balance_fx_rate < 0 ) {
        $po_balance_fx_rate = 0.0;
    }
    if ( $po_balance_usd < 0 ) {
        $po_balance_usd = 0.0;
    }

    if ( $po_deposit_rmb <= 0 && $po_deposit_usd > 0 && $po_deposit_fx_rate > 0 ) {
        $po_deposit_rmb = $po_deposit_usd * $po_deposit_fx_rate;
    }

    // Normalise PO extras array.
    $po_extras_normalised = array();
    if ( ! empty( $po_extras ) && is_array( $po_extras ) ) {
        foreach ( $po_extras as $row ) {
            $label  = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
            $amount = isset( $row['amount_rmb'] ) ? (float) $row['amount_rmb'] : 0.0;

            if ( '' === $label && 0.0 === $amount ) {
                continue;
            }

            $po_extras_normalised[] = array(
                'label'      => $label,
                'amount_rmb' => $amount,
            );
        }
    }
    $po_extras = $po_extras_normalised;

    // Always write normalised extras back into the payload for storage.
    if ( ! is_array( $payload ) ) {
        $payload = array();
    }

    // Ensure we always work with a simple, zero-indexed array of extras.
    $po_extras = is_array( $po_extras ) ? array_values( $po_extras ) : array();

    // Canonical key for extras in the payload.
    $payload['po_extras'] = $po_extras;

    // Back-compat alias for any legacy readers that still look at "extras".
    $payload['extras'] = $po_extras;

    // Normalise payload before storing as header_notes_owner.
    if ( ! is_array( $payload ) ) {
        $payload = array();
    }

    $planning_container = isset( $_POST['sop_container_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_container_type'] ) ) : '';
    $allowed_containers = array( '', '20ft', '40ft', '40ft_hc' );
    if ( ! in_array( $planning_container, $allowed_containers, true ) ) {
        $planning_container = '';
    }
    $planning_allowance = isset( $_POST['sop_allowance_percent'] ) ? (float) wp_unslash( $_POST['sop_allowance_percent'] ) : 0.0;
    if ( $planning_allowance < -50 ) {
        $planning_allowance = -50;
    } elseif ( $planning_allowance > 50 ) {
        $planning_allowance = 50;
    }
    $planning_pallet = ! empty( $_POST['sop_pallet_layer'] ) ? 1 : 0;

    $payload['preorder_planning'] = array(
        'container_type'    => $planning_container,
        'allowance_percent' => (float) $planning_allowance,
        'pallet_layer'      => (int) $planning_pallet,
    );

    // Ensure expected keys are present for consistent UI behaviour.
    $payload['order_date']     = $po_order_date;
    $payload['load_date']      = $po_load_date;
    $payload['arrival_date']   = $po_arrival_date;
    $payload['holiday_start']  = $po_holiday_start;
    $payload['holiday_end']    = $po_holiday_end;
    $payload['deposit_usd']    = $po_deposit_usd;
    $payload['deposit_rmb']    = $po_deposit_rmb;
    $payload['deposit_fx_rate']   = (float) $po_deposit_fx_rate;
    $payload['deposit_fx_locked'] = (bool) $po_deposit_fx_locked;
    $payload['balance_fx_rate']   = (float) $po_balance_fx_rate;
    $payload['balance_usd']       = (float) $po_balance_usd;
    $payload['balance_fx_locked'] = (bool) $po_balance_fx_locked;
    $payload['po_extras']         = is_array( $po_extras ) ? array_values( $po_extras ) : array();

    $header_notes_owner = wp_json_encode( $payload );

    $update = array(
        'order_date_owner'          => ( '' !== $po_order_date ) ? $po_order_date : null,
        'container_load_date_owner' => ( '' !== $po_load_date ) ? $po_load_date : null,
        'arrival_date_owner'        => ( '' !== $po_arrival_date ) ? $po_arrival_date : null,
        'deposit_fx_owner'          => $po_deposit_rmb,
        'balance_fx_owner'          => $po_deposit_usd,
        'header_notes_owner'        => $header_notes_owner,
    );

    $result = sop_update_preorder_sheet( $sheet_id, $update );
    if ( is_wp_error( $result ) ) {
        return;
    }
}

/**
 * Render the Saved Sheets admin page.
 */
function sop_render_preorder_sheets_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to view preorder sheets.', 'sop' ) );
    }

    $supplier_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $suppliers = function_exists( 'sop_preorder_get_suppliers' ) ? sop_preorder_get_suppliers() : array();
    $sheets    = array();
    $status_filters = array( 'draft', 'locked', 'receiving', 'received' );

    if ( isset( $_GET['sop_deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $deleted_flag = (int) $_GET['sop_deleted']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 1 === $deleted_flag ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Draft pre-order sheet deleted.', 'sop' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete pre-order sheet.', 'sop' ) . '</p></div>';
        }
    }

    if ( $supplier_id > 0 && function_exists( 'sop_get_preorder_sheets_for_supplier' ) ) {
        $sheets = sop_get_preorder_sheets_for_supplier(
            $supplier_id,
            array(
                'status' => $status_filters,
            )
        );
    } elseif ( function_exists( 'sop_preorder_get_sheets_all' ) ) {
        $sheets = sop_preorder_get_sheets_all( $status_filters );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Saved pre-order sheets', 'sop' ); ?></h1>

        <form method="get" action="">
            <input type="hidden" name="page" value="sop-preorder-sheets" />
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Supplier', 'sop' ); ?></th>
                    <td>
                        <select name="supplier_id">
                            <option value="0"><?php esc_html_e( 'All suppliers', 'sop' ); ?></option>
                            <?php foreach ( $suppliers as $supplier ) : ?>
                                <option value="<?php echo esc_attr( $supplier['id'] ); ?>" <?php selected( (int) $supplier['id'], $supplier_id ); ?>>
                                    <?php echo esc_html( $supplier['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'sop' ); ?></button>
                    </td>
                </tr>
            </table>
        </form>

        <?php if ( empty( $sheets ) ) : ?>
            <?php if ( $supplier_id > 0 ) : ?>
                <p><?php esc_html_e( 'No saved sheets found for this supplier.', 'sop' ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'No saved sheets found.', 'sop' ); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <style>
                .sop-status-pill{
                    display:inline-flex;
                    align-items:center;
                    padding:5px 12px;
                    border-radius:999px;
                    font-weight:700;
                    font-size:12px;
                    line-height:1;
                    color:#fff;
                    letter-spacing:.2px;
                    text-shadow:0 1px 0 rgba(0,0,0,.35);
                    box-shadow:inset 0 1px 0 rgba(255,255,255,.7), inset 0 -2px 6px rgba(0,0,0,.35), 0 1px 2px rgba(0,0,0,.25);
                    border:1px solid rgba(0,0,0,.25);
                }
                .sop-status-in_progress,
                .sop-status-in-progress{
                    background:linear-gradient(180deg,#2f6dd1 0%,#1f55b6 55%,#103a8a 100%);
                    border-color:#103a8a;
                    color:#fff;
                }
                .sop-status-in_progress .dashicons,
                .sop-status-in-progress .dashicons{
                    color:#fff;
                }
                .sop-status-ordered{
                    background:linear-gradient(180deg,#ff7a7a 0%,#ff2a2a 55%,#b50000 100%);
                    border-color:#b50000;
                }
                .sop-status-completed{
                    background:linear-gradient(180deg,#7dff5b 0%,#29c324 55%,#0b6f1a 100%);
                    border-color:#0b6f1a;
                }
                .sop-status-pill .dashicons{
                    margin-right:6px;
                    width:14px;
                    height:14px;
                    font-size:14px;
                    line-height:14px;
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    flex:0 0 auto;
                    padding-top:3px;
                    box-sizing:border-box;
                }
                .sop-status-pill .dashicons:before{
                    font-size:14px;
                    line-height:14px;
                }
                .sop-status-gi{
                    margin-left:8px;
                    font-size:11px;
                    font-weight:600;
                    padding:2px 6px;
                    border-radius:999px;
                    background:rgba(255,255,255,0.25);
                    border:1px solid rgba(255,255,255,0.35);
                }
            </style>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Supplier', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Order #', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Version', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Order date', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Container', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Last updated', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'sop' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $sheets as $sheet ) : ?>
                        <?php
                        $sheet_status = strtolower( trim( (string) ( isset( $sheet['status'] ) ? $sheet['status'] : '' ) ) );
                        $gi_started = false;
                        if ( function_exists( 'sop_get_preorder_sheet_stage_info' ) ) {
                            $stage_info = sop_get_preorder_sheet_stage_info( $sheet_status );
                            if ( ! empty( $stage_info['goods_in_started'] ) ) {
                                $gi_started = true;
                            } elseif ( 'locked' === $sheet_status && function_exists( 'sop_preorder_sheet_has_goodsin_activity' ) ) {
                                $gi_started = sop_preorder_sheet_has_goodsin_activity( (int) $sheet['id'] );
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( $sheet['id'] ); ?></td>
                            <td><?php echo esc_html( function_exists( 'sop_get_supplier_label' ) ? sop_get_supplier_label( $sheet['supplier_id'] ) : $sheet['supplier_id'] ); ?></td>
                            <td><?php echo sop_preorder_render_status_pill( $sheet_status, $gi_started ); ?></td>
                            <td><?php echo ! empty( $sheet['order_number_label'] ) ? esc_html( $sheet['order_number_label'] ) : '&mdash;'; ?></td>
                            <td><?php echo ! empty( $sheet['edit_version'] ) ? (int) $sheet['edit_version'] : 1; ?></td>
                            <td><?php echo esc_html( isset( $sheet['order_date_owner'] ) ? $sheet['order_date_owner'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $sheet['container_type'] ) ? $sheet['container_type'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $sheet['updated_at'] ) ? $sheet['updated_at'] : '' ); ?></td>
                            <td>
                                <?php
                                $open_url = add_query_arg(
                                    array(
                                        'page'         => 'sop-preorder-sheet',
                                        'supplier_id'  => isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0,
                                        'sop_sheet_id' => isset( $sheet['id'] ) ? (int) $sheet['id'] : 0,
                                    ),
                                    admin_url( 'admin.php' )
                                );
                                ?>
                                <?php
                                $open_label = ( empty( $sheet_status ) || 'draft' === $sheet_status )
                                    ? __( 'Open', 'sop' )
                                    : __( 'View', 'sop' );
                                ?>
                                <a class="button" href="<?php echo esc_url( $open_url ); ?>">
                                    <?php echo esc_html( $open_label ); ?>
                                </a>
                                <?php if ( empty( $sheet_status ) || 'draft' === $sheet_status ) : ?>
                                    <?php
                                    $lock_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'      => 'sop_preorder_lock_sheet',
                                                'sheet_id'    => isset( $sheet['id'] ) ? (int) $sheet['id'] : 0,
                                                'supplier_id' => isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0,
                                            ),
                                            admin_url( 'admin-post.php' )
                                        ),
                                        'sop_preorder_lock_sheet_' . ( isset( $sheet['id'] ) ? (int) $sheet['id'] : 0 )
                                    );
                                    ?>
                                    <a class="button" href="<?php echo esc_url( $lock_url ); ?>"
                                       onclick="return confirm('<?php echo esc_js( __( 'Lock this saved pre-order sheet? You will need to unlock it to edit.', 'sop' ) ); ?>');">
                                        <?php esc_html_e( 'Lock', 'sop' ); ?>
                                    </a>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="sop_delete_preorder_sheet" />
                                        <input type="hidden" name="sheet_id" value="<?php echo esc_attr( $sheet['id'] ); ?>" />
                                        <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier_id ); ?>" />
                                        <?php wp_nonce_field( 'sop_delete_preorder_sheet_' . (int) $sheet['id'] ); ?>
                                        <button type="submit"
                                                class="button button-link-delete"
                                                onclick="return confirm('<?php echo esc_js( __( 'Delete this draft sheet? This cannot be undone.', 'sop' ) ); ?>');">
                                            <?php esc_html_e( 'Delete', 'sop' ); ?>
                                        </button>
                                    </form>
                                <?php elseif ( in_array( $sheet_status, array( 'locked', 'receiving', 'received', 'completed', 'complete', 'closed' ), true ) ) : ?>
                                    <?php
                                    $unlock_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'      => 'sop_preorder_unlock_sheet',
                                                'sheet_id'    => isset( $sheet['id'] ) ? (int) $sheet['id'] : 0,
                                                'supplier_id' => isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0,
                                            ),
                                            admin_url( 'admin-post.php' )
                                        ),
                                        'sop_preorder_unlock_sheet_' . ( isset( $sheet['id'] ) ? (int) $sheet['id'] : 0 )
                                    );
                                    $unlock_confirm = __( 'Unlock this saved pre-order sheet to allow editing?', 'sop' );
                                    if ( in_array( $sheet_status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) {
                                        $unlock_confirm = __( "Revert this RECEIVED sheet back to DRAFT?\n\nStock already added will NOT be automatically reversed.\nContinue?", 'sop' );
                                    }
                                    ?>
                                    <a class="button" href="<?php echo esc_url( $unlock_url ); ?>"
                                       onclick="return confirm('<?php echo esc_js( $unlock_confirm ); ?>');">
                                        <?php esc_html_e( 'Unlock', 'sop' ); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ( ! empty( $sheet_status ) && 'draft' !== $sheet_status ) : ?>
                                    <?php
                                    $goodsin_args = array(
                                        'page'     => 'sop-goods-in',
                                        'sheet_id' => isset( $sheet['id'] ) ? (int) $sheet['id'] : 0,
                                    );
                                    if ( 'received' === $sheet_status ) {
                                        $goodsin_args['view'] = 'report';
                                    }
                                    $goodsin_url = add_query_arg( $goodsin_args, admin_url( 'admin.php' ) );
                                    ?>
                                    <a class="button" href="<?php echo esc_url( $goodsin_url ); ?>">
                                        <?php esc_html_e( 'Goods-In', 'sop' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render a status pill badge for saved sheets.
 *
 * @param string $status Sheet status.
 * @return string
 */
function sop_preorder_render_status_pill( $status, $goods_in_started = false ) {
    $status = (string) $status;
    $info   = function_exists( 'sop_get_preorder_sheet_stage_info' )
        ? sop_get_preorder_sheet_stage_info( $status )
        : array(
            'stage_key'        => 'in_progress',
            'stage_label'      => __( 'In Progress', 'sop' ),
            'goods_in_started' => false,
        );

    $stage_key   = isset( $info['stage_key'] ) ? (string) $info['stage_key'] : 'in_progress';
    $stage_label = isset( $info['stage_label'] ) ? (string) $info['stage_label'] : __( 'In Progress', 'sop' );
    $gi_started  = ! empty( $goods_in_started ) || ! empty( $info['goods_in_started'] );

    $icon = 'dashicons-unlock';
    if ( 'completed' === $stage_key ) {
        $icon = 'dashicons-yes';
    } elseif ( 'ordered' === $stage_key ) {
        $icon = 'dashicons-lock';
    }

    $class = 'sop-status-pill sop-status-' . sanitize_key( $stage_key );
    $label_html = '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>' . esc_html( $stage_label );
    if ( $gi_started && 'ordered' === $stage_key ) {
        $label_html .= '<span class="sop-status-gi" title="' . esc_attr__( 'Goods-In started', 'sop' ) . '">' . esc_html__( 'GI started', 'sop' ) . '</span>';
    }

    return '<span class="' . esc_attr( $class ) . '">' . $label_html . '</span>';
}

/**
 * Fetch saved sheets across all suppliers with status filtering.
 *
 * @param array $statuses Statuses to include.
 * @return array
 */
function sop_preorder_get_sheets_all( array $statuses ) {
    global $wpdb;

    $table_sheets = function_exists( 'sop_get_preorder_sheet_table_name' ) ? sop_get_preorder_sheet_table_name() : '';
    if ( '' === $table_sheets ) {
        $table_sheets = $wpdb->prefix . 'sop_preorder_sheet';
    }

    $status_list = array();
    foreach ( $statuses as $status ) {
        $status_list[] = sanitize_key( $status );
    }
    $status_list = array_values( array_filter( $status_list ) );
    if ( empty( $status_list ) ) {
        return array();
    }

    $placeholders = implode( ',', array_fill( 0, count( $status_list ), '%s' ) );
    $sql = "SELECT *
            FROM {$table_sheets}
            WHERE status IN ( {$placeholders} )
            ORDER BY updated_at DESC, id DESC";

    $prepared = $wpdb->prepare( $sql, $status_list );
    $rows = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    return is_array( $rows ) ? $rows : array();
}

/**
 * Handle filter submissions for the Pre-Order Sheet, including container updates on saved sheets.
 */
add_action( 'admin_post_sop_preorder_filter', 'sop_handle_preorder_filter' );
add_action( 'admin_post_sop_preorder_lock_sheet', 'sop_preorder_handle_lock_sheet' );
add_action( 'admin_post_sop_preorder_unlock_sheet', 'sop_preorder_handle_unlock_sheet' );
function sop_handle_preorder_filter() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to update preorder filters.', 'sop' ) );
    }

    check_admin_referer( 'sop_preorder_filter', 'sop_preorder_filter_nonce' );

    $supplier_id    = isset( $_POST['_sop_supplier_id'] ) ? (int) $_POST['_sop_supplier_id'] : 0;
    $sheet_id       = isset( $_POST['sop_preorder_sheet_id'] ) ? (int) $_POST['sop_preorder_sheet_id'] : 0;
    $container_type = isset( $_POST['sop_container'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_container'] ) ) : '';
    $pallet_layer   = ! empty( $_POST['sop_pallet_layer'] ) ? 1 : 0;
    $allowance      = isset( $_POST['sop_allowance'] ) ? floatval( wp_unslash( $_POST['sop_allowance'] ) ) : 0;
    $sku_filter     = isset( $_POST['sop_sku_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_sku_filter'] ) ) : '';

    if ( $sheet_id <= 0 && isset( $_POST['sop_sheet_id'] ) ) {
        $sheet_id = (int) $_POST['sop_sheet_id'];
    }

    if ( $allowance < -50 ) {
        $allowance = -50;
    } elseif ( $allowance > 50 ) {
        $allowance = 50;
    }

    $redirect_args = array(
        'page'             => 'sop-preorder-sheet',
        '_sop_supplier_id' => $supplier_id,
        'sop_container'   => $container_type,
        'sop_allowance'   => $allowance,
    );

    if ( $pallet_layer ) {
        $redirect_args['sop_pallet_layer'] = 1;
    }

    if ( '' !== $sku_filter ) {
        $redirect_args['sop_sku_filter'] = $sku_filter;
    }

    if ( $sheet_id > 0 ) {
        $redirect_args['sop_sheet_id'] = $sheet_id;
    }

    $is_update_container = isset( $_POST['sop_preorder_update_container'] );

    if ( $is_update_container && $sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) && function_exists( 'sop_update_preorder_sheet' ) ) {
        $existing_sheet = sop_get_preorder_sheet( $sheet_id );

        if ( $existing_sheet && is_array( $existing_sheet ) ) {
            $sheet_supplier_id = isset( $existing_sheet['supplier_id'] ) ? (int) $existing_sheet['supplier_id'] : 0;
            if ( $sheet_supplier_id > 0 ) {
                $redirect_args['_sop_supplier_id'] = $sheet_supplier_id;
            }

            $update_data = array(
                'container_type' => $container_type,
            );

            if ( isset( $existing_sheet['edit_version'] ) ) {
                $update_data['edit_version'] = max( 1, (int) $existing_sheet['edit_version'] + 1 );
            }

            $update_result = sop_update_preorder_sheet( $sheet_id, $update_data );

            if ( is_wp_error( $update_result ) ) {
                $redirect_args['sop_saved'] = '0';
            }
        }
    }

    $redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
    wp_safe_redirect( $redirect );
    exit;
}

/**
 * Handler for saving a preorder sheet (insert or update).
 */
add_action( 'admin_post_sop_save_preorder_sheet', 'sop_handle_save_preorder_sheet' );
function sop_handle_save_preorder_sheet() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to save preorder sheets.', 'sop' ) );
    }

    $nonce = isset( $_POST['sop_save_preorder_sheet_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['sop_save_preorder_sheet_nonce'] ) )
        : '';

    if ( ! wp_verify_nonce( $nonce, 'sop_save_preorder_sheet' ) ) {
        wp_die( esc_html__( 'Security check failed while saving preorder sheet.', 'sop' ) );
    }

    $supplier_id = isset( $_POST['_sop_supplier_id'] ) ? (int) $_POST['_sop_supplier_id'] : 0;
    $sheet_id    = isset( $_POST['sop_sheet_id'] ) ? (int) $_POST['sop_sheet_id'] : 0;

    $existing_sheet = null;

    if ( $sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) ) {
        $existing_sheet = sop_get_preorder_sheet( $sheet_id );
        if ( is_array( $existing_sheet ) && ! empty( $existing_sheet['status'] ) && 'draft' !== $existing_sheet['status'] ) {
            $redirect = add_query_arg(
                array(
                    'page'                 => 'sop-preorder-sheet',
                    'sop_sheet_id'         => (int) $sheet_id,
                    'sop_preorder_readonly'=> '1',
                ),
                admin_url( 'admin.php' )
            );
            wp_safe_redirect( $redirect );
            exit;
        }

        // For existing sheets, always trust the stored supplier.
        if ( $existing_sheet && isset( $existing_sheet['supplier_id'] ) ) {
            $supplier_id = (int) $existing_sheet['supplier_id'];
        }
    }

    if ( $supplier_id < 1 ) {
        $redirect_args = $redirect_common;
        $redirect_args['sop_saved'] = '0';
        $redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    $now_utc        = current_time( 'mysql', true );
    $container_type = isset( $_POST['sop_container_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_container_type'] ) ) : '';
    $allowance      = isset( $_POST['sop_allowance_percent'] ) ? floatval( wp_unslash( $_POST['sop_allowance_percent'] ) ) : 0;
    $pallet_layer   = ! empty( $_POST['sop_pallet_layer'] ) ? 1 : 0;

    $allowed_containers = array( '', '20ft', '40ft', '40ft_hc' );
    if ( ! in_array( $container_type, $allowed_containers, true ) ) {
        $container_type = '';
    }

    if ( $allowance > 50 ) {
        $allowance = 50;
    } elseif ( $allowance < -50 ) {
        $allowance = -50;
    }
    $order_number_label = isset( $_POST['sop_header_order_number'] )
        ? sanitize_text_field( wp_unslash( $_POST['sop_header_order_number'] ) )
        : '';

    $redirect_common = array(
        'page'            => 'sop-preorder-sheet',
        '_sop_supplier_id' => $supplier_id,
        'sop_container'   => $container_type,
        'sop_allowance'   => $allowance,
    );
    if ( $pallet_layer ) {
        $redirect_common['sop_pallet_layer'] = 1;
    }

    $header_data = array(
        'supplier_id'      => $supplier_id,
        'status'           => 'draft',
        'container_type'   => $container_type,
        'created_at'       => $now_utc,
        'updated_at'       => $now_utc,
        'order_number_label' => $order_number_label,
        'edit_version'       => 1,
    );

    if ( ! empty( $_POST['sop_supplier_name'] ) ) {
        $header_data['title'] = sanitize_text_field( wp_unslash( $_POST['sop_supplier_name'] ) );
    }

    $lines      = array();
    $sort_index = 0;

    $lines_json_raw = isset( $_POST['sop_lines_json'] ) ? wp_unslash( $_POST['sop_lines_json'] ) : '';
    $has_lines_json = is_string( $lines_json_raw ) && '' !== trim( $lines_json_raw );

    if ( $has_lines_json ) {
        $decoded = json_decode( $lines_json_raw, true );
        if ( ! is_array( $decoded ) || ! isset( $decoded['lines'] ) || ! is_array( $decoded['lines'] ) || empty( $decoded['lines'] ) ) {
            $redirect_args = $redirect_common;
            $redirect_args['sop_saved']    = '0';
            $redirect_args['sop_sheet_id'] = (int) $sheet_id;
            $redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        foreach ( $decoded['lines'] as $line ) {
            $product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
            if ( $product_id <= 0 ) {
                continue;
            }

            $removed_val = 0;
            if ( is_array( $line ) ) {
                if ( array_key_exists( 'is_removed_owner', $line ) ) {
                    $removed_val = ! empty( $line['is_removed_owner'] ) ? 1 : 0;
                } elseif ( array_key_exists( 'removed', $line ) ) {
                    $removed_val = ! empty( $line['removed'] ) ? 1 : 0;
                }
            }

            $sku       = isset( $line['sku'] ) ? sanitize_text_field( $line['sku'] ) : '';
            $qty       = isset( $line['qty'] ) ? floatval( $line['qty'] ) : 0;
            $moq       = isset( $line['moq'] ) ? floatval( $line['moq'] ) : 0;
            $cost_rmb  = isset( $line['cost_rmb'] ) ? floatval( $line['cost_rmb'] ) : 0;
            $p_notes   = isset( $line['product_notes'] ) ? wp_kses_post( $line['product_notes'] ) : '';
            $i_notes   = isset( $line['internal_product_notes'] ) ? wp_kses_post( $line['internal_product_notes'] ) : '';
            $o_notes   = isset( $line['order_notes'] ) ? sanitize_textarea_field( $line['order_notes'] ) : '';
            $carton_no = isset( $line['carton_no'] ) ? sanitize_text_field( $line['carton_no'] ) : '';
            if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) ) {
                $carton_norm = sop_normalize_carton_numbers_for_display( $carton_no );
                $carton_no   = isset( $carton_norm['value'] ) ? $carton_norm['value'] : $carton_no;
            }
            $image_id  = isset( $line['image_id'] ) ? (int) $line['image_id'] : 0;
            $location  = isset( $line['location'] ) ? sanitize_text_field( $line['location'] ) : '';
            $cbm_unit  = isset( $line['cbm_per_unit'] ) ? floatval( $line['cbm_per_unit'] ) : 0;
            $cbm_total = isset( $line['cbm_total'] ) ? floatval( $line['cbm_total'] ) : 0;

            update_post_meta( $product_id, '_sop_internal_product_notes', $i_notes );

            $lines[] = array(
                'product_id'          => $product_id,
                'sku_owner'           => $sku,
                'qty_owner'           => $qty,
                'cost_rmb_owner'      => $cost_rmb,
                'moq_owner'           => $moq,
                'product_notes_owner' => $p_notes,
                'order_notes_owner'   => $o_notes,
                'carton_no'           => $carton_no,
                'is_removed_owner'    => $removed_val,
                'image_id'            => $image_id,
                'location'            => $location,
                'cbm_per_unit'        => $cbm_unit,
                'cbm_total_owner'     => $cbm_total,
                'sort_index'          => $sort_index++,
            );
        }
    } else {
        // Collect line arrays (legacy fallback).
        $product_ids   = isset( $_POST['sop_line_product_id'] ) ? (array) $_POST['sop_line_product_id'] : array();
        $skus          = isset( $_POST['sop_line_sku'] ) ? (array) $_POST['sop_line_sku'] : array();
        $qtys          = isset( $_POST['sop_line_qty'] ) ? (array) $_POST['sop_line_qty'] : array();
        $moqs          = isset( $_POST['sop_line_moq'] ) ? (array) $_POST['sop_line_moq'] : array();
        $costs_rmb     = isset( $_POST['sop_line_cost_rmb'] ) ? (array) $_POST['sop_line_cost_rmb'] : array();
        $product_notes = isset( $_POST['sop_line_product_notes'] ) ? (array) $_POST['sop_line_product_notes'] : array();
        $internal_notes = isset( $_POST['sop_line_internal_product_notes'] ) ? (array) $_POST['sop_line_internal_product_notes'] : array();
        $order_notes   = isset( $_POST['sop_line_order_notes'] ) ? (array) wp_unslash( $_POST['sop_line_order_notes'] ) : array();
        $carton_nos    = isset( $_POST['sop_line_carton_no'] ) ? (array) wp_unslash( $_POST['sop_line_carton_no'] ) : array();
        $image_ids     = isset( $_POST['sop_line_image_id'] ) ? (array) $_POST['sop_line_image_id'] : array();
        $locations     = isset( $_POST['sop_line_location'] ) ? (array) $_POST['sop_line_location'] : array();
        $cbm_units     = isset( $_POST['sop_line_cbm_per_unit'] ) ? (array) $_POST['sop_line_cbm_per_unit'] : array();
        $cbm_totals    = isset( $_POST['sop_line_cbm_total'] ) ? (array) $_POST['sop_line_cbm_total'] : array();
        $removed_flags = isset( $_POST['sop_removed'] ) ? (array) $_POST['sop_removed'] : array();

        $all_keys = array_keys( $product_ids + $skus + $qtys + $moqs + $costs_rmb + $product_notes + $internal_notes + $order_notes + $carton_nos + $image_ids + $locations + $cbm_units + $cbm_totals + $removed_flags );
        $all_keys = sop_preorder_normalize_pid_map( array_fill_keys( $all_keys, 1 ) );
        $all_keys = array_keys( $all_keys );

        foreach ( $all_keys as $pid ) {
            $product_id = (int) $pid;
            if ( $product_id <= 0 ) {
                continue;
            }

            $sku       = isset( $skus[ $pid ] ) ? sanitize_text_field( wp_unslash( $skus[ $pid ] ) ) : '';
            $qty       = isset( $qtys[ $pid ] ) ? floatval( wp_unslash( $qtys[ $pid ] ) ) : 0;
            $moq       = isset( $moqs[ $pid ] ) ? floatval( wp_unslash( $moqs[ $pid ] ) ) : 0;
            $cost_rmb  = isset( $costs_rmb[ $pid ] ) ? floatval( wp_unslash( $costs_rmb[ $pid ] ) ) : 0;
            $p_notes   = isset( $product_notes[ $pid ] ) ? wp_kses_post( wp_unslash( $product_notes[ $pid ] ) ) : '';
            $i_notes   = isset( $internal_notes[ $pid ] ) ? wp_kses_post( wp_unslash( $internal_notes[ $pid ] ) ) : '';
            $o_notes   = isset( $order_notes[ $pid ] ) ? sanitize_textarea_field( $order_notes[ $pid ] ) : '';
            $carton_no = isset( $carton_nos[ $pid ] ) ? sanitize_text_field( $carton_nos[ $pid ] ) : '';
            if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) ) {
                $carton_norm = sop_normalize_carton_numbers_for_display( $carton_no );
                $carton_no   = isset( $carton_norm['value'] ) ? $carton_norm['value'] : $carton_no;
            }
            $image_id  = isset( $image_ids[ $pid ] ) ? (int) $image_ids[ $pid ] : 0;
            $location  = isset( $locations[ $pid ] ) ? sanitize_text_field( wp_unslash( $locations[ $pid ] ) ) : '';
            $cbm_unit  = isset( $cbm_units[ $pid ] ) ? floatval( wp_unslash( $cbm_units[ $pid ] ) ) : 0;
            $cbm_total = isset( $cbm_totals[ $pid ] ) ? floatval( wp_unslash( $cbm_totals[ $pid ] ) ) : 0;

            $removed_val = isset( $removed_flags[ $pid ] ) && ! empty( $removed_flags[ $pid ] ) ? 1 : 0;

            update_post_meta( $product_id, '_sop_internal_product_notes', $i_notes );

            $lines[] = array(
                'product_id'          => $product_id,
                'sku_owner'           => $sku,
                'qty_owner'           => $qty,
                'cost_rmb_owner'      => $cost_rmb,
                'moq_owner'           => $moq,
                'product_notes_owner' => $p_notes,
                'order_notes_owner'   => $o_notes,
                'carton_no'           => $carton_no,
                'is_removed_owner'    => $removed_val,
                'image_id'            => $image_id,
                'location'            => $location,
                'cbm_per_unit'        => $cbm_unit,
                'cbm_total_owner'     => $cbm_total,
                'sort_index'          => $sort_index++,
            );
        }
    }

    $is_update = false;

    if ( $sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) ) {
        $existing_sheet = sop_get_preorder_sheet( $sheet_id );
        if ( $existing_sheet && is_array( $existing_sheet ) ) {
            if ( ! isset( $existing_sheet['supplier_id'] ) || (int) $existing_sheet['supplier_id'] !== $supplier_id ) {
                $sheet_id = 0;
            } else {
                if ( function_exists( 'sop_update_preorder_sheet' ) ) {
                    $existing_version          = ! empty( $existing_sheet['edit_version'] ) ? (int) $existing_sheet['edit_version'] : 0;
                    $header_data['edit_version'] = max( 1, $existing_version + 1 );
                    $header_data['updated_at'] = current_time( 'mysql', true );
                    $update_result              = sop_update_preorder_sheet( $sheet_id, $header_data );
                    if ( is_wp_error( $update_result ) ) {
                        $redirect_args = $redirect_common;
                        $redirect_args['sop_saved']    = '0';
                        $redirect_args['sop_sheet_id'] = (int) $sheet_id;
                        $redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                    $is_update = true;
                } else {
                    $sheet_id = 0;
                }
            }
        } else {
            $sheet_id = 0;
        }
    }

    if ( ! $is_update ) {
        $sheet_id = sop_insert_preorder_sheet( $header_data );
        if ( is_wp_error( $sheet_id ) || ! $sheet_id ) {
            $redirect_args = $redirect_common;
            $redirect_args['sop_saved'] = '0';
            $redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
            wp_safe_redirect( $redirect );
            exit;
        }
        $sheet_id = (int) $sheet_id;
    }

    if ( $sheet_id > 0 ) {
        sop_preorder_update_po_header_from_post( (int) $sheet_id );
    }

    // Preserve saved stock snapshots when updating existing lines.
    if ( $is_update && function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        $existing_lines    = sop_get_preorder_sheet_lines( $sheet_id );
        $existing_lines    = is_array( $existing_lines ) ? $existing_lines : array();
        $existing_by_pid   = array();
        $existing_by_sku   = array();
        $protected_snapshots = array(
            'stock_on_hand',
            'current_stock',
            'stock_qty',
            'saved_stock',
            'stock_at_save',
            'stock_snapshot',
            'stock_on_hand_saved',
        );

        foreach ( $existing_lines as $eline ) {
            $epid = isset( $eline['product_id'] ) ? (int) $eline['product_id'] : 0;
            $esku = isset( $eline['sku_owner'] ) ? (string) $eline['sku_owner'] : '';
            if ( $epid > 0 ) {
                $existing_by_pid[ $epid ] = $eline;
            }
            if ( '' !== $esku ) {
                $existing_by_sku[ $esku ] = $eline;
            }
        }

        foreach ( $lines as &$line_update ) {
            $pid = isset( $line_update['product_id'] ) ? (int) $line_update['product_id'] : 0;
            if ( $pid <= 0 && ! empty( $line_update['sku_owner'] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
                $pid = wc_get_product_id_by_sku( (string) $line_update['sku_owner'] );
                if ( $pid > 0 ) {
                    $line_update['product_id'] = $pid;
                }
            }

            $existing_line = null;
            if ( $pid > 0 && isset( $existing_by_pid[ $pid ] ) ) {
                $existing_line = $existing_by_pid[ $pid ];
            } elseif ( ! empty( $line_update['sku_owner'] ) && isset( $existing_by_sku[ $line_update['sku_owner'] ] ) ) {
                $existing_line = $existing_by_sku[ $line_update['sku_owner'] ];
                if ( $pid <= 0 && isset( $existing_line['product_id'] ) ) {
                    $line_update['product_id'] = (int) $existing_line['product_id'];
                }
            }

            if ( $existing_line && is_array( $existing_line ) ) {
                foreach ( $protected_snapshots as $snap_key ) {
                    if ( isset( $existing_line[ $snap_key ] ) && ! isset( $line_update[ $snap_key ] ) ) {
                        $line_update[ $snap_key ] = $existing_line[ $snap_key ];
                    }
                }
            }
        }
        unset( $line_update );
    }

    $lines_result = function_exists( 'sop_replace_preorder_sheet_lines' )
        ? sop_replace_preorder_sheet_lines( (int) $sheet_id, $lines )
        : sop_insert_preorder_sheet_lines( (int) $sheet_id, $lines );
    if ( is_wp_error( $lines_result ) ) {
        $redirect_args = $redirect_common;
        $redirect_args['sop_saved']    = '0';
        $redirect_args['sop_sheet_id'] = (int) $sheet_id;
        $redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    $hidden_columns_raw = isset( $_POST['sop_preorder_hidden_columns'] ) ? wp_unslash( $_POST['sop_preorder_hidden_columns'] ) : '';
    $hidden_columns_list = array();
    if ( is_array( $hidden_columns_raw ) ) {
        $hidden_columns_list = $hidden_columns_raw;
    } elseif ( is_string( $hidden_columns_raw ) && '' !== trim( $hidden_columns_raw ) ) {
        $decoded_hidden = json_decode( $hidden_columns_raw, true );
        if ( is_array( $decoded_hidden ) ) {
            $hidden_columns_list = $decoded_hidden;
        } else {
            $hidden_columns_list = array_map( 'trim', explode( ',', $hidden_columns_raw ) );
        }
    }

    $allowed_columns = array(
        'image',
        'location',
        'sku',
        'supplier_skus',
        'brand',
        'category',
        'product',
        'cost_supplier',
        'cost_usd',
        'stock',
        'inbound',
        'min_order',
        'soq',
        'order_qty',
        'line_total',
        'cubic',
        'line_cbm',
        'regular_unit',
        'regular_line',
        'notes',
        'order_notes',
        'carton_no',
    );
    $allowed_map = array_fill_keys( $allowed_columns, true );
    $hidden_columns_sanitized = array();
    foreach ( $hidden_columns_list as $hidden_col ) {
        $clean_key = sanitize_key( $hidden_col );
        if ( '' === $clean_key || ! isset( $allowed_map[ $clean_key ] ) ) {
            continue;
        }
        $hidden_columns_sanitized[] = $clean_key;
    }
    $hidden_columns_sanitized = array_values( array_unique( $hidden_columns_sanitized ) );

    if ( function_exists( 'sop_db_get_row' ) && function_exists( 'sop_db_update' ) ) {
        $supplier_row = sop_db_get_row( 'suppliers', array( 'id' => $supplier_id ), ARRAY_A );
        if ( is_array( $supplier_row ) ) {
            $settings_json_raw = isset( $supplier_row['settings_json'] ) ? (string) $supplier_row['settings_json'] : '';
            $settings          = array();
            if ( '' !== $settings_json_raw ) {
                $decoded_settings = json_decode( $settings_json_raw, true );
                if ( is_array( $decoded_settings ) ) {
                    $settings = $decoded_settings;
                } else {
                    $settings = null;
                }
            }

            if ( is_array( $settings ) ) {
                if ( empty( $hidden_columns_sanitized ) ) {
                    unset( $settings['preorder_hidden_columns'] );
                } else {
                    $settings['preorder_hidden_columns'] = $hidden_columns_sanitized;
                }

                if ( empty( $settings ) && '' === $settings_json_raw ) {
                    // No existing settings and nothing to save.
                } else {
                    $update_settings = array(
                        'settings_json' => wp_json_encode( $settings ),
                        'updated_at'    => current_time( 'mysql' ),
                    );
                    sop_db_update(
                        'suppliers',
                        $update_settings,
                        array( 'id' => $supplier_id ),
                        array( '%s', '%s' ),
                        array( '%d' )
                    );
                }
            }
        }
    }

    $redirect_args = $redirect_common;
    $redirect_args['sop_saved']    = '1';
    $redirect_args['sop_sheet_id'] = (int) $sheet_id;
    $redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );

    wp_safe_redirect( $redirect );
    exit;
}

add_action( 'admin_post_sop_export_preorder_sheet_xlsx', 'sop_handle_export_preorder_sheet_xlsx' );
if ( ! function_exists( 'sop_export_clean_output_buffers' ) ) {
    function sop_export_clean_output_buffers() {
        if ( function_exists( 'ini_set' ) ) {
            @ini_set( 'zlib.output_compression', 'Off' );
        }
        if ( function_exists( 'session_write_close' ) ) {
            @session_write_close();
        }
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
    }
}

if ( ! function_exists( 'sop_export_send_file_and_exit' ) ) {
    function sop_export_send_file_and_exit( $file_path, $download_name, $content_type ) {
        $file_path = (string) $file_path;
        if ( '' === $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            wp_die( esc_html__( 'Export file not found.', 'sop' ) );
        }

        if ( headers_sent() ) {
            wp_die( esc_html__( 'Export headers already sent.', 'sop' ) );
        }

        $download_name = (string) $download_name;
        if ( '' === $download_name ) {
            $download_name = basename( $file_path );
        }

        sop_export_clean_output_buffers();
        nocache_headers();

        header( 'Content-Type: ' . $content_type );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $download_name ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Transfer-Encoding: binary' );

        $length = filesize( $file_path );
        if ( $length && $length > 0 ) {
            header( 'Content-Length: ' . $length );
        }

        readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        @unlink( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        exit;
    }
}

function sop_handle_export_preorder_sheet_xlsx() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You are not allowed to export pre-order sheets.', 'sop' ) );
    }

    $nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( ! wp_verify_nonce( $nonce, 'sop_export_preorder_sheet_xlsx' ) ) {
        wp_die( esc_html__( 'Invalid export request.', 'sop' ) );
    }

    $sheet_id    = isset( $_REQUEST['sop_sheet_id'] ) ? (int) $_REQUEST['sop_sheet_id'] : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $supplier_id = isset( $_REQUEST['supplier_id'] ) ? (int) $_REQUEST['supplier_id'] : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

    if ( $sheet_id <= 0 || ! function_exists( 'sop_get_preorder_sheet' ) || ! function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        wp_die( esc_html__( 'Pre-order sheet not found for export.', 'sop' ) );
    }

    $dataset = sop_preorder_build_export_dataset( $sheet_id, $supplier_id );
    if ( is_wp_error( $dataset ) ) {
        wp_die( esc_html( $dataset->get_error_message() ) );
    }

    list( $sheet, $lines ) = $dataset;

    $supplier_slug = '';
    if ( function_exists( 'sop_get_supplier_label' ) && ! empty( $sheet['supplier_id'] ) ) {
        $supplier_label = sop_get_supplier_label( (int) $sheet['supplier_id'] );
        $supplier_slug  = sanitize_title( $supplier_label );
    } elseif ( ! empty( $sheet['title'] ) ) {
        $supplier_slug = sanitize_title( $sheet['title'] );
    } elseif ( ! empty( $sheet['supplier_id'] ) ) {
        $supplier_slug = 'supplier-' . (int) $sheet['supplier_id'];
    } else {
        $supplier_slug = 'supplier';
    }

    $order_number = ! empty( $sheet['order_number_label'] ) ? preg_replace( '/[^0-9A-Za-z\-_]/', '', $sheet['order_number_label'] ) : (string) (int) $sheet_id;
    $version      = ! empty( $sheet['edit_version'] ) ? (int) $sheet['edit_version'] : 1;
    $order_date   = ! empty( $sheet['order_date_owner'] ) ? preg_replace( '/[^0-9\-]/', '', $sheet['order_date_owner'] ) : gmdate( 'Y-m-d' );

    $filename = sprintf(
        '%s-order-%s-v%d-%s.xlsx',
        $supplier_slug,
        $order_number,
        $version,
        $order_date
    );

    if ( ! class_exists( 'SOP_Preorder_XLSX_Exporter' ) ) {
        wp_die( esc_html__( 'XLSX exporter is not available.', 'sop' ) );
    }

    $xlsx_path = SOP_Preorder_XLSX_Exporter::build_xlsx_file( $sheet, $lines );
    if ( is_wp_error( $xlsx_path ) ) {
        wp_die(
            '<strong>' . esc_html__( 'XLSX Export Error', 'sop' ) . '</strong><br />' . esc_html( $xlsx_path->get_error_message() )
        );
    }

    sop_export_send_file_and_exit(
        $xlsx_path,
        $filename,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
}

add_action( 'admin_post_sop_export_purchase_order_xlsx', 'sop_handle_export_purchase_order_xlsx' );
function sop_handle_export_purchase_order_xlsx() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You are not allowed to export purchase orders.', 'sop' ) );
    }

    $nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( ! wp_verify_nonce( $nonce, 'sop_export_purchase_order_xlsx' ) ) {
        wp_die( esc_html__( 'Invalid PO export request.', 'sop' ) );
    }

    $sheet_id = isset( $_REQUEST['sop_sheet_id'] ) ? (int) $_REQUEST['sop_sheet_id'] : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( $sheet_id <= 0 ) {
        wp_die( esc_html__( 'Purchase Order not found for export.', 'sop' ) );
    }

    $dataset = sop_preorder_build_export_dataset( $sheet_id );
    if ( is_wp_error( $dataset ) ) {
        wp_die( esc_html( $dataset->get_error_message() ) );
    }

    list( $sheet_header, $line_rows ) = $dataset;

    $supplier_slug = '';
    if ( function_exists( 'sop_get_supplier_label' ) && ! empty( $sheet_header['supplier_id'] ) ) {
        $supplier_label = sop_get_supplier_label( (int) $sheet_header['supplier_id'] );
        $supplier_slug  = sanitize_title( $supplier_label );
    } elseif ( ! empty( $sheet_header['supplier_name'] ) ) {
        $supplier_slug = sanitize_title( $sheet_header['supplier_name'] );
    } elseif ( ! empty( $sheet_header['supplier_id'] ) ) {
        $supplier_slug = 'supplier-' . (int) $sheet_header['supplier_id'];
    } else {
        $supplier_slug = 'supplier';
    }

    if ( ! class_exists( 'SOP_Preorder_XLSX_Exporter' ) ) {
        wp_die( esc_html__( 'XLSX exporter is not available.', 'sop' ) );
    }

    $xlsx_path = SOP_Preorder_XLSX_Exporter::build_purchase_order_xlsx_from_template( $sheet_header, $line_rows );
    if ( is_wp_error( $xlsx_path ) ) {
        wp_die(
            '<strong>' . esc_html__( 'XLSX Export Error', 'sop' ) . '</strong><br />' . esc_html( $xlsx_path->get_error_message() )
        );
    }

    $filename = sanitize_file_name( sprintf( 'purchase-order-%s-%d.xlsx', $supplier_slug, (int) $sheet_id ) );

    sop_export_send_file_and_exit(
        $xlsx_path,
        $filename,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
}

/**
 * Build export dataset (header + lines).
 *
 * @param int $sheet_id Sheet ID.
 * @param int $supplier_id Supplier ID.
 * @return array|WP_Error
 */
function sop_preorder_build_export_dataset( $sheet_id, $supplier_id = 0 ) {
    if ( ! function_exists( 'sop_get_preorder_sheet' ) || ! function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        return new WP_Error( 'sop_export_missing_helpers', __( 'Export helpers unavailable.', 'sop' ) );
    }

    $sheet = sop_get_preorder_sheet( $sheet_id );
    $migration_meta = array( 'changed' => false, 'unresolved_skus' => array() );
    if ( is_array( $sheet ) && function_exists( 'sop_preorder_migrate_saved_sheet_lines_to_pid' ) ) {
        list( $sheet, $migration_meta ) = sop_preorder_migrate_saved_sheet_lines_to_pid( $sheet );
        if ( ! empty( $migration_meta['unresolved_skus'] ) && current_user_can( 'manage_woocommerce' ) ) {
            add_action(
                'admin_notices',
                static function() use ( $migration_meta, $sheet_id ) {
                    $unresolved = array_slice( $migration_meta['unresolved_skus'], 0, 20 );
                    $more       = max( 0, count( $migration_meta['unresolved_skus'] ) - count( $unresolved ) );
                    $msg        = sprintf(
                        /* translators: 1: sheet id, 2: CSV list of SKUs, 3: remaining count */
                        esc_html__( 'Preorder sheet #%1$d: Could not resolve product IDs for SKUs: %2$s%3$s', 'sop' ),
                        (int) $sheet_id,
                        esc_html( implode( ', ', $unresolved ) ),
                        $more > 0 ? esc_html( sprintf( ' (+%d more)', $more ) ) : ''
                    );
                    echo '<div class="notice notice-warning"><p>' . $msg . '</p></div>';
                }
            );
        }
    }
    if ( empty( $sheet ) ) {
        return new WP_Error( 'sop_export_sheet_missing', __( 'Pre-order sheet not found.', 'sop' ) );
    }

    if ( $supplier_id > 0 && isset( $sheet['supplier_id'] ) && (int) $sheet['supplier_id'] !== (int) $supplier_id ) {
        return new WP_Error( 'sop_export_supplier_mismatch', __( 'Supplier mismatch for export.', 'sop' ) );
    }

    $lines = sop_get_preorder_sheet_lines( $sheet_id );
    $lines = is_array( $lines ) ? $lines : array();

    $filtered_lines = array();
    foreach ( $lines as $line ) {
        $qty = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : 0.0;
        if ( $qty <= 0 ) {
            continue;
        }

        if ( ! empty( $line['is_removed_owner'] ) ) {
            continue;
        }

        $filtered_lines[] = $line;
    }

    $lines = $filtered_lines;

    // Hydrate display fields with live product data (display-only; preserve saved snapshots).
    if ( function_exists( 'sop_hydrate_line_with_live_product_fields' ) ) {
        $sheet_supplier_id = isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0;
        foreach ( $lines as $lidx => $line ) {
            $lines[ $lidx ] = sop_hydrate_line_with_live_product_fields( $line, $sheet_supplier_id );
        }
    }

    if ( empty( $lines ) ) {
        return new WP_Error( 'sop_export_no_orderable_lines', __( 'No orderable lines found (Qty > 0).', 'sop' ) );
    }

    $supplier_name = '';
    if ( ! empty( $sheet['title'] ) ) {
        $supplier_name = $sheet['title'];
    } elseif ( function_exists( 'sop_preorder_get_suppliers' ) && ! empty( $sheet['supplier_id'] ) ) {
        $suppliers = sop_preorder_get_suppliers();
        foreach ( $suppliers as $row ) {
            if ( (int) $row['id'] === (int) $sheet['supplier_id'] ) {
                $supplier_name = $row['name'];
                break;
            }
        }
    }

    $sheet_header = array(
        'id'                => isset( $sheet['id'] ) ? (int) $sheet['id'] : 0,
        'supplier_id'       => isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0,
        'supplier_name'     => $supplier_name,
        'order_number_label'=> isset( $sheet['order_number_label'] ) ? $sheet['order_number_label'] : '',
        'edit_version'      => isset( $sheet['edit_version'] ) ? (int) $sheet['edit_version'] : 1,
        'order_date_owner'  => isset( $sheet['order_date_owner'] ) ? $sheet['order_date_owner'] : '',
        'header_notes_owner'=> isset( $sheet['header_notes_owner'] ) ? $sheet['header_notes_owner'] : '',
        'header_payment_terms_owner' => isset( $sheet['header_payment_terms_owner'] ) ? $sheet['header_payment_terms_owner'] : '',
    );

    $line_rows = array();
    foreach ( $lines as $line ) {
        $product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
        $product    = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;

        $brand      = '';
        if ( $product && function_exists( 'wc_get_product_terms' ) ) {
            $terms = wc_get_product_terms( $product_id, 'product_brand', array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $brand = implode( ', ', $terms );
            }
        }

        $categories = '';
        if ( function_exists( 'sop_get_product_category_path_below_root' ) ) {
            $categories = sop_get_product_category_path_below_root( $product_id );
        }

        $carton_no = isset( $line['carton_no'] ) ? $line['carton_no'] : '';
        if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) ) {
            $carton_norm = sop_normalize_carton_numbers_for_display( $carton_no );
            $carton_no   = isset( $carton_norm['value'] ) ? $carton_norm['value'] : $carton_no;
        }

        $cm3_per_unit = isset( $line['cbm_per_unit'] ) ? (float) $line['cbm_per_unit'] : 0;
        $line_cbm     = isset( $line['cbm_total_owner'] ) ? (float) $line['cbm_total_owner'] : 0;

        if ( ( $cm3_per_unit <= 0 || $line_cbm <= 0 ) && $product ) {
            $length = (float) $product->get_length();
            $width  = (float) $product->get_width();
            $height = (float) $product->get_height();
            if ( $length > 0 && $width > 0 && $height > 0 ) {
                $cm3_per_unit = $cm3_per_unit > 0 ? $cm3_per_unit : $length * $width * $height;
                if ( $line_cbm <= 0 && isset( $line['qty_owner'] ) ) {
                    $line_cbm = ( $cm3_per_unit * (float) $line['qty_owner'] ) / 1000000;
                }
            }
        }

        $line_rows[] = array(
            'product_id'    => $product_id,
            'sku'           => isset( $line['sku_owner'] ) ? $line['sku_owner'] : '',
            'product_name'  => $product ? $product->get_name() : '',
            'brand'         => $brand,
            'categories'    => $categories,
            'location'      => isset( $line['location'] ) ? $line['location'] : '',
            'moq'           => isset( $line['moq_owner'] ) ? (float) $line['moq_owner'] : 0,
            'soq'           => isset( $line['suggested_qty_owner'] ) ? (float) $line['suggested_qty_owner'] : 0,
            'qty'           => isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : 0,
            'cost_rmb'      => isset( $line['cost_rmb_owner'] ) ? (float) $line['cost_rmb_owner'] : 0,
            'line_total_rmb'=> ( isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : 0 ) * ( isset( $line['cost_rmb_owner'] ) ? (float) $line['cost_rmb_owner'] : 0 ),
            'product_notes' => isset( $line['product_notes_owner'] ) ? $line['product_notes_owner'] : '',
            'order_notes'   => isset( $line['order_notes_owner'] ) ? $line['order_notes_owner'] : '',
            'carton_no'     => $carton_no,
            'cm3_per_unit'  => $cm3_per_unit,
            'line_cbm'      => $line_cbm,
            'image_id'      => $product_id ? get_post_thumbnail_id( $product_id ) : 0,
        );
    }

    return array( $sheet_header, $line_rows );
}

/**
 * Fallback CSV export.
 *
 * @param array  $sheet_header Header data.
 * Handle deletion of a draft preorder sheet.
 *
 * @return void
 */
add_action( 'admin_post_sop_delete_preorder_sheet', 'sop_handle_delete_preorder_sheet' );
function sop_handle_delete_preorder_sheet() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to delete pre-order sheets.', 'sop' ) );
    }

    $sheet_id    = isset( $_POST['sheet_id'] ) ? (int) $_POST['sheet_id'] : 0;
    $supplier_id = isset( $_POST['supplier_id'] ) ? (int) $_POST['supplier_id'] : 0;

    $nonce_action = 'sop_delete_preorder_sheet_' . $sheet_id;
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) ) {
        wp_die( esc_html__( 'Invalid delete request.', 'sop' ) );
    }

    if ( $sheet_id <= 0 || ! function_exists( 'sop_get_preorder_sheet' ) ) {
        wp_die( esc_html__( 'Pre-order sheet not found.', 'sop' ) );
    }

    $sheet = sop_get_preorder_sheet( $sheet_id );
    if ( ! is_array( $sheet ) || empty( $sheet['id'] ) ) {
        wp_die( esc_html__( 'Pre-order sheet not found.', 'sop' ) );
    }

    if ( ! empty( $sheet['status'] ) && 'draft' !== $sheet['status'] ) {
        wp_die( esc_html__( 'Only draft sheets can be deleted.', 'sop' ) );
    }

    if ( ! function_exists( 'sop_delete_preorder_sheet' ) ) {
        wp_die( esc_html__( 'Delete helper not available.', 'sop' ) );
    }

    $result = sop_delete_preorder_sheet( $sheet_id );
    $flag   = ( is_wp_error( $result ) || ! $result ) ? '0' : '1';

    $redirect = add_query_arg(
        array(
            'page'        => 'sop-preorder-sheets',
            'supplier_id' => $supplier_id,
            'sop_deleted' => $flag,
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect );
    exit;
}

/**
 * Lock a saved pre-order sheet by setting its status to 'locked'.
 *
 * @return void
 */
function sop_preorder_handle_lock_sheet() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to lock pre-order sheets.', 'sop' ) );
    }

    $sheet_id    = isset( $_GET['sheet_id'] ) ? (int) $_GET['sheet_id'] : 0;
    $supplier_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0;

    $nonce_action = 'sop_preorder_lock_sheet_' . $sheet_id;
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], $nonce_action ) ) {
        wp_die( esc_html__( 'Invalid lock request.', 'sop' ) );
    }

    if ( $sheet_id <= 0 || ! function_exists( 'sop_get_preorder_sheet' ) || ! function_exists( 'sop_update_preorder_sheet' ) ) {
        wp_die( esc_html__( 'Pre-order sheet not found.', 'sop' ) );
    }

    $sheet = sop_get_preorder_sheet( $sheet_id );
    if ( ! is_array( $sheet ) || empty( $sheet['id'] ) ) {
        wp_die( esc_html__( 'Pre-order sheet not found.', 'sop' ) );
    }

    $status = isset( $sheet['status'] ) ? $sheet['status'] : '';
    if ( empty( $status ) || 'draft' === $status ) {
        $update_data = array(
            'status' => 'locked',
        );
        sop_update_preorder_sheet( $sheet_id, $update_data );
    }

    if ( empty( $supplier_id ) && isset( $sheet['supplier_id'] ) ) {
        $supplier_id = (int) $sheet['supplier_id'];
    }

    $redirect = add_query_arg(
        array(
            'page'        => 'sop-preorder-sheets',
            'supplier_id' => $supplier_id,
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect );
    exit;
}

/**
 * Unlock a saved pre-order sheet by setting its status back to 'draft'.
 *
 * @return void
 */
function sop_preorder_handle_unlock_sheet() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to unlock pre-order sheets.', 'sop' ) );
    }

    $sheet_id    = isset( $_GET['sheet_id'] ) ? (int) $_GET['sheet_id'] : 0;
    $supplier_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0;

    $nonce_action = 'sop_preorder_unlock_sheet_' . $sheet_id;
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], $nonce_action ) ) {
        wp_die( esc_html__( 'Invalid unlock request.', 'sop' ) );
    }

    if ( $sheet_id <= 0 || ! function_exists( 'sop_get_preorder_sheet' ) || ! function_exists( 'sop_update_preorder_sheet' ) ) {
        wp_die( esc_html__( 'Pre-order sheet not found.', 'sop' ) );
    }

    $sheet = sop_get_preorder_sheet( $sheet_id );
    if ( ! is_array( $sheet ) || empty( $sheet['id'] ) ) {
        wp_die( esc_html__( 'Pre-order sheet not found.', 'sop' ) );
    }

    $status = strtolower( trim( (string) ( isset( $sheet['status'] ) ? $sheet['status'] : '' ) ) );
    if ( in_array( $status, array( 'locked', 'receiving', 'received', 'completed', 'complete', 'closed' ), true ) ) {
        $update_data = array(
            'status' => 'draft',
        );
        sop_update_preorder_sheet( $sheet_id, $update_data );
    }

    if ( empty( $supplier_id ) && isset( $sheet['supplier_id'] ) ) {
        $supplier_id = (int) $sheet['supplier_id'];
    }

    $redirect = add_query_arg(
        array(
            'page'        => 'sop-preorder-sheets',
            'supplier_id' => $supplier_id,
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect );
    exit;
}

function sop_preorder_get_settings() {
    $defaults = [
        'preorder_enabled'      => true,
        'default_buffer_days'   => 0,
        'rounding_mode'         => 'none',
        'currencies'            => [ 'GBP', 'RMB', 'USD', 'EUR' ],
        'currency_rates'        => [
            'RMB' => 9.1,
            'USD' => 1.25,
            'EUR' => 1.15,
        ],
        'supplier_currency_map' => [],
    ];

    $stored = get_option( 'sop_preorder_settings', [] );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $settings = array_merge( $defaults, $stored );

    if ( empty( $settings['currencies'] ) || ! is_array( $settings['currencies'] ) ) {
        $settings['currencies'] = $defaults['currencies'];
    }

    if ( empty( $settings['currency_rates'] ) || ! is_array( $settings['currency_rates'] ) ) {
        $settings['currency_rates'] = $defaults['currency_rates'];
    }

    if ( empty( $settings['supplier_currency_map'] ) || ! is_array( $settings['supplier_currency_map'] ) ) {
        $settings['supplier_currency_map'] = [];
    }

    return $settings;
}

function sop_preorder_normalise_currency( $currency ) {
    $currency = strtoupper( (string) $currency );

    if ( ! in_array( $currency, [ 'GBP', 'RMB', 'USD', 'EUR' ], true ) ) {
        return 'GBP';
    }

    return $currency;
}

if ( ! function_exists( 'sop_preorder_get_suppliers' ) ) {
function sop_preorder_get_suppliers() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sop_suppliers';

    // Make sure the table exists.
    $table_check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
    if ( ! $table_check ) {
        return array();
    }

    // Simple, schema-aligned query: id, name, currency.
    $sql  = "SELECT id, name, currency FROM {$table_name} ORDER BY name ASC";
    $rows = $wpdb->get_results( $sql, ARRAY_A );

    // If the DB threw an error (e.g. bad column), fail safely.
    if ( ! empty( $wpdb->last_error ) ) {
        return array();
    }

    if ( empty( $rows ) ) {
        return array();
    }

    $suppliers = array();

    foreach ( $rows as $row ) {
        $id   = isset( $row['id'] ) ? (int) $row['id'] : 0;
        $name = isset( $row['name'] ) ? (string) $row['name'] : '';

        if ( $id <= 0 || '' === $name ) {
            continue;
        }

        $currency_raw = isset( $row['currency'] ) ? (string) $row['currency'] : 'GBP';

        $suppliers[] = array(
            'id'            => $id,
            'name'          => $name,
            'currency_code' => sop_preorder_normalise_currency( $currency_raw ),
        );
    }

    return $suppliers;
}
}

function sop_preorder_resolve_supplier_params( $preferred_supplier_id = 0 ) {
    $suppliers = sop_preorder_get_suppliers();
    $settings  = sop_preorder_get_settings();

    $requested_supplier_id = (int) $preferred_supplier_id;
    if ( $requested_supplier_id <= 0 ) {
        $requested_supplier_id = isset( $_GET['_sop_supplier_id'] ) ? (int) $_GET['_sop_supplier_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    $supplier = null;
    foreach ( $suppliers as $row ) {
        if ( (int) $row['id'] === $requested_supplier_id ) {
            $supplier = $row;
            break;
        }
    }

    if ( ! $supplier && ! empty( $suppliers ) ) {
        $supplier = $suppliers[0];
    }

    if ( $supplier ) {
        $supplier['id']            = (int) $supplier['id'];
        $supplier['currency_code'] = sop_preorder_normalise_currency( $supplier['currency_code'] );
    }

    $currency = 'GBP';
    if ( $supplier ) {
        $supplier_id = (int) $supplier['id'];
        $map         = $settings['supplier_currency_map'] ?? [];
        if ( isset( $map[ $supplier_id ] ) ) {
            $currency = sop_preorder_normalise_currency( $map[ $supplier_id ] );
        } else {
            $currency = $supplier['currency_code'];
        }
    }

    return [
        'supplier'      => $supplier,
        'currency_code' => $currency,
        'settings'      => $settings,
    ];
}

/**
 * Read WooCommerce cost-of-goods meta for a product (GBP).
 *
 * Returns NULL when no value is present.
 *
 * @param int $product_id Product ID.
 *
 * @return float|null Cost-of-goods value in GBP, or NULL when missing.
 */
function sop_preorder_get_cogs_value_gbp( $product_id ) {
    $product_id = (int) $product_id;

    $keys = array(
        '_cogs_total_value',
        '_cogs_value',
        '_cost_of_goods',
    );

    $parse_decimal = static function ( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return null;
        }

        if ( function_exists( 'wc_format_decimal' ) ) {
            $norm = wc_format_decimal( $raw );
        } else {
            $norm = str_replace( ' ', '', $raw );
            if ( false !== strpos( $norm, ',' ) && false !== strpos( $norm, '.' ) ) {
                $norm = str_replace( ',', '', $norm );
            } elseif ( false !== strpos( $norm, ',' ) ) {
                $norm = str_replace( ',', '.', $norm );
            }
        }

        if ( '' !== $norm && is_numeric( $norm ) ) {
            return (float) $norm;
        }

        return null;
    };

    $try_product_id = static function ( $try_product_id ) use ( $keys, $parse_decimal ) {
        $try_product_id = (int) $try_product_id;
        if ( $try_product_id <= 0 ) {
            return null;
        }

        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $try_product_id );
            if ( $product ) {
                foreach ( $keys as $key ) {
                    $raw = $product->get_meta( $key, true );
                    $val = $parse_decimal( $raw );
                    if ( null !== $val ) {
                        return $val;
                    }
                }
            }
        }

        foreach ( $keys as $key ) {
            $raw = get_post_meta( $try_product_id, $key, true );
            $val = $parse_decimal( $raw );
            if ( null !== $val ) {
                return $val;
            }
        }

        return null;
    };

    $val = $try_product_id( $product_id );
    if ( null !== $val ) {
        return $val;
    }

    if ( function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $product_id );
        if ( $product && method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) && method_exists( $product, 'get_parent_id' ) ) {
            $parent_id = (int) $product->get_parent_id();
            if ( $parent_id > 0 ) {
                $parent_val = $try_product_id( $parent_id );
                if ( null !== $parent_val ) {
                    return $parent_val;
                }
            }
        }
    }

    return null;
}

function sop_preorder_get_cost_gbp_for_product( $product_id, $settings = null ) {
    if ( ! $settings ) {
        $settings = sop_preorder_get_settings();
    }

    $product_id = (int) $product_id;

    $rate_rmb = (float) ( $settings['currency_rates']['RMB'] ?? 0 );
    $rate_usd = (float) ( $settings['currency_rates']['USD'] ?? 0 );
    $rate_eur = (float) ( $settings['currency_rates']['EUR'] ?? 0 );

    $cost_rmb = get_post_meta( $product_id, '_sop_cost_rmb', true );
    $cost_usd = get_post_meta( $product_id, '_sop_cost_usd', true );
    $cost_eur = get_post_meta( $product_id, '_sop_cost_eur', true );
    $cost_gbp = sop_preorder_get_cogs_value_gbp( $product_id );

    $cost_rmb = $cost_rmb !== '' ? (float) $cost_rmb : null;
    $cost_usd = $cost_usd !== '' ? (float) $cost_usd : null;
    $cost_eur = $cost_eur !== '' ? (float) $cost_eur : null;
    $cost_gbp = $cost_gbp !== null ? (float) $cost_gbp : null;

    if ( $cost_rmb !== null && $rate_rmb > 0 ) {
        return $cost_rmb / $rate_rmb;
    }

    if ( $cost_usd !== null && $rate_usd > 0 ) {
        return $cost_usd / $rate_usd;
    }

    if ( $cost_eur !== null && $rate_eur > 0 ) {
        return $cost_eur / $rate_eur;
    }

    if ( $cost_gbp !== null ) {
        return $cost_gbp;
    }

    return 0.0;
}

function sop_preorder_get_cost_for_supplier_currency( $product_id, $supplier_currency, $settings = null ) {
    if ( ! $settings ) {
        $settings = sop_preorder_get_settings();
    }

    $product_id        = (int) $product_id;
    $supplier_currency = sop_preorder_normalise_currency( $supplier_currency );

    $rate_rmb = (float) ( $settings['currency_rates']['RMB'] ?? 0 );
    $rate_usd = (float) ( $settings['currency_rates']['USD'] ?? 0 );
    $rate_eur = (float) ( $settings['currency_rates']['EUR'] ?? 0 );

    if ( 'GBP' === $supplier_currency ) {
        $cogs = sop_preorder_get_cogs_value_gbp( $product_id );
        if ( null !== $cogs ) {
            return $cogs;
        }

        $cost_rmb = get_post_meta( $product_id, '_sop_cost_rmb', true );
        if ( '' !== $cost_rmb && is_numeric( $cost_rmb ) && $rate_rmb > 0 ) {
            return (float) $cost_rmb / $rate_rmb;
        }

        return null;
    }

    if ( 'RMB' === $supplier_currency ) {
        $cost_rmb = get_post_meta( $product_id, '_sop_cost_rmb', true );
        if ( $cost_rmb !== '' ) {
            return (float) $cost_rmb;
        }
    }

    if ( 'USD' === $supplier_currency ) {
        $cost_usd = get_post_meta( $product_id, '_sop_cost_usd', true );
        if ( $cost_usd !== '' ) {
            return (float) $cost_usd;
        }
    }

    if ( 'EUR' === $supplier_currency ) {
        $cost_eur = get_post_meta( $product_id, '_sop_cost_eur', true );
        if ( $cost_eur !== '' ) {
            return (float) $cost_eur;
        }
    }

    $cost_gbp = sop_preorder_get_cost_gbp_for_product( $product_id, $settings );

    switch ( $supplier_currency ) {
        case 'RMB':
            $rate = $rate_rmb;
            break;
        case 'USD':
            $rate = $rate_usd;
            break;
        case 'EUR':
            $rate = $rate_eur;
            break;
        case 'GBP':
        default:
            $rate = 1.0;
            break;
    }

    if ( $rate <= 0 ) {
        return $cost_gbp;
    }

    return $cost_gbp * $rate;
}

function sop_preorder_get_container_cbm_from_selection( $selection ) {
    $selection = (string) $selection;

    switch ( $selection ) {
        case '20ft':
            // 20ft standard ~33.2 CBM internal volume.
            return 33.2;
        case '40ft':
            // 40ft standard ~67.7 CBM internal volume.
            return 67.7;
        case '40ft_hc':
            // 40ft high cube ~76.3 CBM internal volume.
            return 76.3;
    }

    return 0.0;
}


function sop_preorder_get_lock_timestamp( $supplier_id ) {
    $supplier_id = (int) $supplier_id;

    if ( $supplier_id <= 0 ) {
        return 0;
    }

    $option_key = 'sop_preorder_lock_' . $supplier_id;
    $value      = get_option( $option_key, 0 );

    return (int) $value;
}

function sop_preorder_lock_sheet( $supplier_id ) {
    $supplier_id = (int) $supplier_id;

    if ( $supplier_id <= 0 ) {
        return;
    }

    $option_key = 'sop_preorder_lock_' . $supplier_id;
    update_option( $option_key, time() );
}

function sop_preorder_unlock_sheet( $supplier_id ) {
    $supplier_id = (int) $supplier_id;

    if ( $supplier_id <= 0 ) {
        return;
    }

    $option_key = 'sop_preorder_lock_' . $supplier_id;
    delete_option( $option_key );
}

function sop_preorder_build_rows_for_supplier( $supplier_id, $supplier_currency, $settings = null ) {
    if ( ! $settings ) {
        $settings = sop_preorder_get_settings();
    }

    $supplier_id      = (int) $supplier_id;
    $supplier_currency = sop_preorder_normalise_currency( $supplier_currency );

    if ( $supplier_id <= 0 ) {
        return [];
    }

    // Inbound stock from locked sheets: exclude current draft sheet (so it doesn't count itself).
    $exclude_sheet_id = 0;
    $current_sheet_id = isset( $_GET['sop_sheet_id'] ) ? (int) $_GET['sop_sheet_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( 0 === $current_sheet_id && isset( $_GET['sop_preorder_sheet_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_sheet_id = (int) $_GET['sop_preorder_sheet_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    if ( $current_sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) ) {
        $sheet = sop_get_preorder_sheet( $current_sheet_id );
        if ( is_array( $sheet ) ) {
            $sheet_status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
            $is_locked    = ( 'locked' === $sheet_status );
            if ( isset( $sheet['is_locked'] ) && (int) $sheet['is_locked'] === 1 ) {
                $is_locked = true;
            }

            if ( ! $is_locked ) {
                $exclude_sheet_id = (int) $current_sheet_id;
            }
        }
    }

    $inbound_map = array();
    if ( function_exists( 'sop_db_get_inbound_qty_map' ) ) {
        $inbound_map = sop_db_get_inbound_qty_map( $exclude_sheet_id );
        $inbound_map = is_array( $inbound_map ) ? $inbound_map : array();
    }

    // Preload forecast rows for this supplier and index by product ID.
    $forecast_by_product = array();

    if ( function_exists( 'sop_core_engine' ) ) {
        $engine = sop_core_engine();

        if ( $engine && method_exists( $engine, 'get_supplier_forecast' ) ) {
            $forecast_rows = array();

            // Protect against any runtime errors inside the forecast engine.
            try {
                $forecast_rows = $engine->get_supplier_forecast(
                    $supplier_id,
                    array(
                        'inbound_map' => $inbound_map,
                    )
                );
            } catch ( \Throwable $t ) { // PHP 7+.
                // Log the error but do not break the Pre-Order Sheet.
                error_log(
                    sprintf(
                        'SOP forecast error for supplier %d: %s in %s:%d',
                        (int) $supplier_id,
                        $t->getMessage(),
                        $t->getFile(),
                        $t->getLine()
                    )
                );
                $forecast_rows = array();
            }

            if ( is_array( $forecast_rows ) ) {
                foreach ( $forecast_rows as $frow ) {
                    if ( empty( $frow['product_id'] ) ) {
                        continue;
                    }

                    $pid = (int) $frow['product_id'];
                    if ( $pid <= 0 ) {
                        continue;
                    }

                    $forecast_by_product[ $pid ] = $frow;
                }
            }
        }
    }

    $q = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_sop_supplier_id',
                    'value' => $supplier_id,
                ],
            ],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]
    );

    if ( ! $q->have_posts() ) {
        return [];
    }

    $rows = [];

    while ( $q->have_posts() ) {
        $q->the_post();

        $product_id = get_the_ID();
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            continue;
        }

        $sku = $product->get_sku();

        $notes = get_post_meta( $product_id, '_sop_product_notes', true );
        $min   = get_post_meta( $product_id, '_sop_min_order_qty', true );
        $order = 0.0;
        $order_sku_override = get_post_meta( $product_id, '_sop_preorder_order_sku', true );

        $notes = is_string( $notes ) ? $notes : '';
        $min   = $min !== '' ? (float) $min : 0.0;
        $order_sku = ( '' !== $order_sku_override ) ? (string) $order_sku_override : $sku;
        $removed   = 0;

        // Stock on hand via WC product object (wc_get_stock_quantity may not exist on this setup).
        $stock_on_hand = $product->get_stock_quantity();
        if ( null === $stock_on_hand ) {
            $stock_on_hand = 0;
        }
        $stock_on_hand = (float) $stock_on_hand;
        if ( $stock_on_hand < 0 ) {
            $stock_on_hand = 0;
        }


        $inbound_qty = isset( $inbound_map[ $product_id ] ) ? (float) $inbound_map[ $product_id ] : 0.0;
        if ( $inbound_qty < 0 ) {
            $inbound_qty = 0.0;
        }

        $cost_supplier = sop_preorder_get_cost_for_supplier_currency( $product_id, $supplier_currency, $settings );
        if ( null === $cost_supplier ) {
            $cost_supplier = '';
        }
        $cost_gbp      = sop_preorder_get_cost_gbp_for_product( $product_id, $settings );

        // Location (warehouse bin/shelf), using SOP bin location with fallback to existing Woo meta.
        $location = get_post_meta( $product_id, '_sop_bin_location', true );
        if ( '' === $location ) {
            $location = get_post_meta( $product_id, '_product_location', true );
        }
        $location = is_string( $location ) ? $location : '';

        // Brand from taxonomy only (WooCommerce Brands uses 'product_brand' taxonomy).
        $brand = '';
        $brand_terms = wp_get_post_terms( $product_id, 'product_brand', array( 'fields' => 'names' ) );
        if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
            // If multiple brands are assigned, join them with commas.
            if ( is_array( $brand_terms ) ) {
                $brand = implode( ', ', array_map( 'strval', $brand_terms ) );
            } else {
                $brand = (string) $brand_terms;
            }
        }
        $brand = is_string( $brand ) ? $brand : '';

        $category_path = '';
        if ( function_exists( 'sop_get_product_category_path_below_root' ) ) {
            $category_path = sop_get_product_category_path_below_root( $product_id );
        }

        // Weight in KG from Woo meta.
        $weight = get_post_meta( $product_id, '_weight', true );
        $weight = $weight !== '' ? (float) $weight : 0.0;
        if ( $weight < 0 ) {
            $weight = 0.0;
        }
        $line_weight = $weight * $order;

        // Dimensions in CM from Woo meta.
        $length = get_post_meta( $product_id, '_length', true );
        $width  = get_post_meta( $product_id, '_width', true );
        $height = get_post_meta( $product_id, '_height', true );

        $length   = $length !== '' ? (float) $length : 0.0;
        $width    = $width !== '' ? (float) $width : 0.0;
        $height   = $height !== '' ? (float) $height : 0.0;
        $cubic_cm = 0.0;

        if ( $length > 0 && $width > 0 && $height > 0 ) {
            // Store is confirmed to use CM, so simple L×W×H in cm³.
            $cubic_cm = $length * $width * $height;
        }

        $line_cbm = 0.0;
        if ( $cubic_cm > 0 && $order > 0 ) {
            // Convert cm³ to m³: divide by 1,000,000.
            $line_cbm = ( $cubic_cm * $order ) / 1000000;
        }

        // Regular price ex VAT.
        // Store uses _price as ex-VAT; fallback to _regular_price / 1.2 if needed.
        $price_ex_raw       = get_post_meta( $product_id, '_price', true );
        $price_ex_raw       = $price_ex_raw !== '' ? (float) $price_ex_raw : 0.0;
        $regular_price_raw  = get_post_meta( $product_id, '_regular_price', true );
        $regular_price_raw  = $regular_price_raw !== '' ? (float) $regular_price_raw : 0.0;
        $regular_unit_price = 0.0;

        if ( $price_ex_raw > 0 ) {
            $regular_unit_price = $price_ex_raw;
        } elseif ( $regular_price_raw > 0 ) {
            // Assume 20% VAT when only regular (incl.) is available.
            $regular_unit_price = $regular_price_raw / 1.2;
        }

        if ( $regular_unit_price < 0 ) {
            $regular_unit_price = 0.0;
        }

        $regular_line_price  = $regular_unit_price * $order;
        $suggested_order_qty = 0.0;
        $soq_qty_sold            = null;
        $soq_total_days          = null;
        $soq_demand_per_day      = null;
        $soq_stock_at_arrival    = null;
        $soq_buffer_target_units = null;
        $soq_reason              = '';
        $soq_fallback_applied    = 0;
        $soq_suggested_raw       = 0.0;
        $soq_current_stock       = (float) $stock_on_hand;

        if ( isset( $forecast_by_product[ $product_id ] ) && is_array( $forecast_by_product[ $product_id ] ) ) {
            $forecast_row = $forecast_by_product[ $product_id ];

            // Pre-Order Sheet uses Suggested (Raw) as SOQ.
            if ( isset( $forecast_row['suggested_raw'] ) ) {
                $suggested_order_qty = (float) $forecast_row['suggested_raw'];
                $soq_suggested_raw   = (float) $forecast_row['suggested_raw'];
            } elseif ( isset( $forecast_row['suggested_capped'] ) ) {
                // Backwards compatibility if only capped is present.
                $suggested_order_qty = (float) $forecast_row['suggested_capped'];
                $soq_suggested_raw   = (float) $forecast_row['suggested_capped'];
            }

            if ( $suggested_order_qty < 0 ) {
                $suggested_order_qty = 0.0;
            }

            $soq_qty_sold            = isset( $forecast_row['qty_sold'] ) ? (int) $forecast_row['qty_sold'] : null;
            $soq_total_days          = isset( $forecast_row['total_days'] ) ? (float) $forecast_row['total_days'] : null;
            $soq_demand_per_day      = isset( $forecast_row['demand_per_day'] ) ? (float) $forecast_row['demand_per_day'] : null;
            $soq_stock_at_arrival    = isset( $forecast_row['stock_at_arrival'] ) ? (float) $forecast_row['stock_at_arrival'] : null;
            $soq_buffer_target_units = isset( $forecast_row['buffer_target_units'] ) ? (float) $forecast_row['buffer_target_units'] : null;
            $soq_current_stock       = isset( $forecast_row['current_stock'] ) ? (float) $forecast_row['current_stock'] : $soq_current_stock;
            if ( isset( $forecast_row['inbound_qty'] ) ) {
                $inbound_qty = (float) $forecast_row['inbound_qty'];
                if ( $inbound_qty < 0 ) {
                    $inbound_qty = 0.0;
                }
            }
        }

        // SOQ should be rounded up to the nearest whole number.
        $suggested_order_qty = ceil( $suggested_order_qty );

        $soq_fallback_applied = ( $soq_current_stock <= 0 && ( $soq_demand_per_day === null || $soq_demand_per_day <= 0 ) && ( $soq_buffer_target_units === null || $soq_buffer_target_units <= 0 ) && $soq_suggested_raw > 0 ) ? 1 : 0;
        if ( $soq_fallback_applied ) {
            $soq_reason = __( 'Fallback applied', 'sop' );
        } elseif ( $soq_qty_sold === null || $soq_qty_sold <= 0 || $soq_demand_per_day === null || $soq_demand_per_day <= 0 ) {
            $soq_reason = __( 'No sales in window', 'sop' );
        } elseif ( $soq_suggested_raw <= 0 ) {
            $soq_reason = __( 'Stock covers buffer', 'sop' );
        } else {
            $soq_reason = __( 'Order required to reach buffer', 'sop' );
        }

        $rows[] = [
            'product_id'          => $product_id,
            'name'                => $product->get_name(),
            'sku'                 => $sku,
            'product_sku'         => $sku,
            'order_sku'           => $order_sku,
            'notes'               => $notes,
            'min_order_qty'       => $min,
            'manual_order_qty'    => $order,
            'removed'             => $removed,
            'stock_on_hand'       => $stock_on_hand,
            'inbound_qty'         => $inbound_qty,
            'cost_supplier'       => $cost_supplier,
            'cost_gbp'            => $cost_gbp,
            'location'            => $location,
            'brand'               => $brand,
            'category'            => $category_path,
            'category_path'       => $category_path,
            'weight'              => $weight,
            'line_weight'         => $line_weight,
            'suggested_order_qty' => $suggested_order_qty,
            'cubic_cm'            => $cubic_cm,
            'cbm_per_unit'        => $cubic_cm,
            'line_cbm'            => $line_cbm,
            'regular_unit_price'  => $regular_unit_price,
            'regular_line_price'  => $regular_line_price,
            'carton_no'           => '',
            'carton_sort_min'     => null,
            'soq_qty_sold'            => $soq_qty_sold,
            'soq_total_days'          => $soq_total_days,
            'soq_demand_per_day'      => $soq_demand_per_day,
            'soq_stock_at_arrival'    => $soq_stock_at_arrival,
            'soq_buffer_target_units' => $soq_buffer_target_units,
            'soq_reason'              => $soq_reason,
            'soq_fallback_applied'    => $soq_fallback_applied,
        ];

    }

    wp_reset_postdata();

    return $rows;
}

add_action( 'admin_init', 'sop_preorder_migrate_receiving_to_locked' );
add_action( 'admin_init', 'sop_preorder_handle_post' );
function sop_preorder_migrate_receiving_to_locked() {
    if ( get_option( 'sop_migrated_receiving_to_locked' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }
    global $wpdb;
    $table_sheets = function_exists( 'sop_get_preorder_sheet_table_name' ) ? sop_get_preorder_sheet_table_name() : '';
    if ( '' === $table_sheets ) {
        $table_sheets = $wpdb->prefix . 'sop_preorder_sheet';
    }
    $wpdb->query( "UPDATE {$table_sheets} SET status = 'locked' WHERE status = 'receiving'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    update_option( 'sop_migrated_receiving_to_locked', 1, false );
}
function sop_preorder_handle_post() {
    if ( ! is_admin() ) {
        return;
    }

    if ( ! isset( $_POST['sop_preorder_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['sop_preorder_nonce'], 'sop_preorder_save' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $supplier_id = isset( $_POST['_sop_supplier_id'] ) ? (int) $_POST['_sop_supplier_id'] : 0;

    if ( $supplier_id <= 0 ) {
        return;
    }

    $skus          = isset( $_POST['sop_sku'] ) && is_array( $_POST['sop_sku'] ) ? $_POST['sop_sku'] : [];
    $notes         = isset( $_POST['sop_notes'] ) && is_array( $_POST['sop_notes'] ) ? $_POST['sop_notes'] : [];
    $mins          = isset( $_POST['sop_min_order_qty'] ) && is_array( $_POST['sop_min_order_qty'] ) ? $_POST['sop_min_order_qty'] : [];
    $costs         = isset( $_POST['sop_cost_unit_supplier'] ) && is_array( $_POST['sop_cost_unit_supplier'] ) ? $_POST['sop_cost_unit_supplier'] : [];
    $product_ids   = isset( $_POST['sop_product_id'] ) && is_array( $_POST['sop_product_id'] ) ? $_POST['sop_product_id'] : [];

    foreach ( $product_ids as $index => $raw_product_id ) {
        $product_id = (int) $raw_product_id;
        if ( $product_id <= 0 ) {
            continue;
        }

        $sku_val     = isset( $skus[ $index ] ) ? wc_clean( wp_unslash( $skus[ $index ] ) ) : '';
        $note_val    = isset( $notes[ $index ] ) ? wp_kses_post( wp_unslash( $notes[ $index ] ) ) : '';
        $min_val     = isset( $mins[ $index ] ) ? (float) $mins[ $index ] : 0.0;
        $cost_val    = isset( $costs[ $index ] ) ? (float) $costs[ $index ] : 0.0;

        if ( '' !== $sku_val ) {
            update_post_meta( $product_id, '_sop_preorder_order_sku', $sku_val );
        } else {
            delete_post_meta( $product_id, '_sop_preorder_order_sku' );
        }

        update_post_meta( $product_id, '_sop_product_notes', $note_val );
        update_post_meta( $product_id, '_sop_min_order_qty', $min_val );

        $ctx      = sop_preorder_resolve_supplier_params( $supplier_id );
        $supplier = $ctx['supplier'];
        if ( $supplier ) {
            $currency = sop_preorder_normalise_currency( $supplier['currency_code'] );

            if ( $cost_val > 0 ) {
                switch ( $currency ) {
                    case 'RMB':
                        update_post_meta( $product_id, '_sop_cost_rmb', $cost_val );
                        break;
                    case 'USD':
                        update_post_meta( $product_id, '_sop_cost_usd', $cost_val );
                        break;
                    case 'EUR':
                        update_post_meta( $product_id, '_sop_cost_eur', $cost_val );
                        break;
                    case 'GBP':
                    default:
                        update_post_meta( $product_id, '_cogs_value', $cost_val );
                        break;
                }
            }
        }
    }
}

<?php
/**
 * Stock Order Plugin - Phase 4.1 - Pre-Order Sheet Core (admin only)
 * File version: 11.17
 * - Add Purchase Order header fields (dates, deposits, PO extras) with FX and holiday dates for saved sheets, centralised parsing.
 * - 11.17 - Ensure Purchase Order modal fields are explicitly persisted on save (insert/update).
 * - Under Stock Order main menu.
 * - Supplier filter via _sop_supplier_id.
 * - Supplier currency-aware costs using plugin meta:
 *      _sop_cost_rmb, _sop_cost_usd, _sop_cost_eur, fallback _cogs_value for GBP.
 * - Editable & persisted per product:
 *      Order SKU (sheet-only) -> meta: _sop_preorder_order_sku
 *      Notes              -> meta: _sop_preorder_notes
 *      Min order qty      -> meta: _sop_min_order_qty
 *      Manual order qty   -> meta: _sop_preorder_order_qty
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
 * Render the Saved Sheets admin page.
 */
function sop_render_preorder_sheets_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to view preorder sheets.', 'sop' ) );
    }

    $supplier_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $suppliers = function_exists( 'sop_preorder_get_suppliers' ) ? sop_preorder_get_suppliers() : array();
    $sheets    = array();

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
                'status' => array( 'draft', 'locked' ),
            )
        );
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
                            <option value="0"><?php esc_html_e( 'Select a supplier', 'sop' ); ?></option>
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

        <?php if ( $supplier_id <= 0 ) : ?>
            <p><?php esc_html_e( 'Select a supplier to view saved sheets.', 'sop' ); ?></p>
        <?php elseif ( empty( $sheets ) ) : ?>
            <p><?php esc_html_e( 'No saved sheets found for this supplier.', 'sop' ); ?></p>
        <?php else : ?>
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
                        <tr>
                            <td><?php echo esc_html( $sheet['id'] ); ?></td>
                            <td><?php echo esc_html( function_exists( 'sop_get_supplier_label' ) ? sop_get_supplier_label( $sheet['supplier_id'] ) : $sheet['supplier_id'] ); ?></td>
                            <td><?php echo esc_html( isset( $sheet['status'] ) ? $sheet['status'] : '' ); ?></td>
                            <td><?php echo ! empty( $sheet['order_number_label'] ) ? esc_html( $sheet['order_number_label'] ) : '&mdash;'; ?></td>
                            <td><?php echo ! empty( $sheet['edit_version'] ) ? (int) $sheet['edit_version'] : 1; ?></td>
                            <td><?php echo esc_html( isset( $sheet['order_date_owner'] ) ? $sheet['order_date_owner'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $sheet['container_type'] ) ? $sheet['container_type'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $sheet['updated_at'] ) ? $sheet['updated_at'] : '' ); ?></td>
                            <td>
                                <?php
                                $sheet_status = isset( $sheet['status'] ) ? $sheet['status'] : '';
                                $open_url = add_query_arg(
                                    array(
                                        'page'         => 'sop-preorder-sheet',
                                        'supplier_id'  => isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0,
                                        'sop_sheet_id' => isset( $sheet['id'] ) ? (int) $sheet['id'] : 0,
                                    ),
                                    admin_url( 'admin.php' )
                                );
                                ?>
                                <a class="button" href="<?php echo esc_url( $open_url ); ?>">
                                    <?php esc_html_e( 'Open', 'sop' ); ?>
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
                                <?php elseif ( 'locked' === $sheet_status ) : ?>
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
                                    ?>
                                    <a class="button" href="<?php echo esc_url( $unlock_url ); ?>"
                                       onclick="return confirm('<?php echo esc_js( __( 'Unlock this saved pre-order sheet to allow editing?', 'sop' ) ); ?>');">
                                        <?php esc_html_e( 'Unlock', 'sop' ); ?>
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

    $supplier_id    = isset( $_POST['sop_supplier_id'] ) ? (int) $_POST['sop_supplier_id'] : 0;
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
        'page'            => 'sop-preorder-sheet',
        'sop_supplier_id' => $supplier_id,
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
                $redirect_args['sop_supplier_id'] = $sheet_supplier_id;
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

    $supplier_id = isset( $_POST['sop_supplier_id'] ) ? (int) $_POST['sop_supplier_id'] : 0;
    $sheet_id    = isset( $_POST['sop_sheet_id'] ) ? (int) $_POST['sop_sheet_id'] : 0;

    $existing_sheet = null;

    if ( $sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) ) {
        $existing_sheet = sop_get_preorder_sheet( $sheet_id );
        if ( is_array( $existing_sheet ) && ! empty( $existing_sheet['status'] ) && 'draft' !== $existing_sheet['status'] ) {
            wp_die( esc_html__( 'This saved pre-order sheet is locked and cannot be edited. Please unlock it first.', 'sop' ) );
        }
    }

    if ( $supplier_id < 1 ) {
        $redirect = add_query_arg(
            array(
                'page'      => 'sop-preorder-sheet',
                'sop_saved' => '0',
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    $now_utc        = current_time( 'mysql', true );
    $container_type = isset( $_POST['sop_container_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_container_type'] ) ) : '';
    $allowance      = isset( $_POST['sop_allowance_percent'] ) ? floatval( wp_unslash( $_POST['sop_allowance_percent'] ) ) : 0;
    $order_number_label = isset( $_POST['sop_header_order_number'] )
        ? sanitize_text_field( wp_unslash( $_POST['sop_header_order_number'] ) )
        : '';

    // Purchase Order header fields.
    $po_order_date   = isset( $_POST['sop_po_order_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_order_date'] ) ) : '';
    $po_load_date    = isset( $_POST['sop_po_load_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_load_date'] ) ) : '';
    $po_arrival_date = isset( $_POST['sop_po_arrival_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_arrival_date'] ) ) : '';

    $po_deposit_rmb = isset( $_POST['sop_po_deposit_rmb'] ) ? (float) wp_unslash( $_POST['sop_po_deposit_rmb'] ) : 0.0;
    $po_deposit_usd = isset( $_POST['sop_po_deposit_usd'] ) ? (float) wp_unslash( $_POST['sop_po_deposit_usd'] ) : 0.0;
    $po_deposit_fx_rate = isset( $_POST['sop_po_deposit_fx_rate'] ) ? (float) wp_unslash( $_POST['sop_po_deposit_fx_rate'] ) : 0.0;
    if ( $po_deposit_fx_rate < 0 ) {
        $po_deposit_fx_rate = 0.0;
    }
    $po_deposit_fx_locked = ! empty( $_POST['sop_po_deposit_fx_locked'] ) ? 1 : 0;

    $po_balance_fx_rate = isset( $_POST['sop_po_balance_fx_rate'] ) ? (float) wp_unslash( $_POST['sop_po_balance_fx_rate'] ) : 0.0;
    if ( $po_balance_fx_rate < 0 ) {
        $po_balance_fx_rate = 0.0;
    }
    $po_balance_usd = isset( $_POST['sop_po_balance_usd'] ) ? (float) wp_unslash( $_POST['sop_po_balance_usd'] ) : 0.0;
    if ( $po_balance_usd < 0 ) {
        $po_balance_usd = 0.0;
    }
    $po_holiday_start = isset( $_POST['sop_po_holiday_start'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_holiday_start'] ) ) : '';
    $po_holiday_end   = isset( $_POST['sop_po_holiday_end'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_po_holiday_end'] ) ) : '';

    if ( $po_deposit_rmb < 0 ) {
        $po_deposit_rmb = 0.0;
    }
    if ( $po_deposit_usd < 0 ) {
        $po_deposit_usd = 0.0;
    }

    if ( $po_deposit_rmb <= 0 && $po_deposit_usd > 0 && $po_deposit_fx_rate > 0 ) {
        $po_deposit_rmb = $po_deposit_usd * $po_deposit_fx_rate;
    }

    $extra_labels  = isset( $_POST['sop_po_extra_label'] ) && is_array( $_POST['sop_po_extra_label'] ) ? array_map( 'wp_unslash', $_POST['sop_po_extra_label'] ) : array();
    $extra_amounts = isset( $_POST['sop_po_extra_amount'] ) && is_array( $_POST['sop_po_extra_amount'] ) ? array_map( 'wp_unslash', $_POST['sop_po_extra_amount'] ) : array();

    $po_extras = array();
    $max_count = max( count( $extra_labels ), count( $extra_amounts ) );
    for ( $i = 0; $i < $max_count; $i++ ) {
        $label_raw  = isset( $extra_labels[ $i ] ) ? $extra_labels[ $i ] : '';
        $amount_raw = isset( $extra_amounts[ $i ] ) ? $extra_amounts[ $i ] : '';

        $label  = trim( sanitize_text_field( $label_raw ) );
        $amount = (float) $amount_raw;

        if ( '' === $label && 0.0 === $amount ) {
            continue;
        }

        $po_extras[] = array(
            'label'      => $label,
            'amount_rmb' => $amount,
        );
    }

    // Build header_notes_owner JSON with extras + FX metadata (preserve existing keys when possible).
    $header_notes_owner_data = array();
    $existing_header_notes_owner = '';
    if ( $existing_sheet && is_array( $existing_sheet ) && ! empty( $existing_sheet['header_notes_owner'] ) ) {
        $existing_header_notes_owner = $existing_sheet['header_notes_owner'];
    }

    if ( is_string( $existing_header_notes_owner ) && '' !== trim( $existing_header_notes_owner ) ) {
        $decoded_notes = json_decode( $existing_header_notes_owner, true );
        if ( is_array( $decoded_notes ) ) {
            $header_notes_owner_data = $decoded_notes;
        }
    }

    if ( ! empty( $po_extras ) ) {
        $header_notes_owner_data['po_extras'] = $po_extras;
    } else {
        unset( $header_notes_owner_data['po_extras'] );
    }

    if ( $po_deposit_fx_rate > 0 ) {
        $header_notes_owner_data['deposit_fx_rate'] = $po_deposit_fx_rate;
    } else {
        unset( $header_notes_owner_data['deposit_fx_rate'] );
    }

    $header_notes_owner_data['deposit_fx_locked'] = (bool) $po_deposit_fx_locked;

    if ( $po_balance_fx_rate > 0 ) {
        $header_notes_owner_data['balance_fx_rate'] = $po_balance_fx_rate;
    } else {
        unset( $header_notes_owner_data['balance_fx_rate'] );
    }

    if ( $po_balance_usd > 0 ) {
        $header_notes_owner_data['balance_usd'] = $po_balance_usd;
    } else {
        unset( $header_notes_owner_data['balance_usd'] );
    }

    if ( '' !== $po_holiday_start ) {
        $header_notes_owner_data['po_holiday_start'] = $po_holiday_start;
    } else {
        unset( $header_notes_owner_data['po_holiday_start'] );
    }

    if ( '' !== $po_holiday_end ) {
        $header_notes_owner_data['po_holiday_end'] = $po_holiday_end;
    } else {
        unset( $header_notes_owner_data['po_holiday_end'] );
    }

    $header_notes_owner = '';
    if ( ! empty( $header_notes_owner_data ) ) {
        $header_notes_owner = wp_json_encode( $header_notes_owner_data );
    }

    $header_data = array(
        'supplier_id'      => $supplier_id,
        'status'           => 'draft',
        'order_date_owner' => ( '' !== $po_order_date ) ? $po_order_date : null,
        'container_load_date_owner' => ( '' !== $po_load_date ) ? $po_load_date : null,
        'arrival_date_owner' => ( '' !== $po_arrival_date ) ? $po_arrival_date : null,
        'deposit_fx_owner' => $po_deposit_rmb,
        'balance_fx_owner' => $po_deposit_usd,
        'header_notes_owner' => $header_notes_owner,
        'container_type'   => $container_type,
        'created_at'       => $now_utc,
        'updated_at'       => $now_utc,
        'order_number_label' => $order_number_label,
        'edit_version'       => 1,
    );

    if ( ! empty( $_POST['sop_supplier_name'] ) ) {
        $header_data['title'] = sanitize_text_field( wp_unslash( $_POST['sop_supplier_name'] ) );
    }

    // Collect line arrays.
    $product_ids   = isset( $_POST['sop_line_product_id'] ) ? (array) $_POST['sop_line_product_id'] : array();
    $skus          = isset( $_POST['sop_line_sku'] ) ? (array) $_POST['sop_line_sku'] : array();
    $qtys          = isset( $_POST['sop_line_qty'] ) ? (array) $_POST['sop_line_qty'] : array();
    $moqs          = isset( $_POST['sop_line_moq'] ) ? (array) $_POST['sop_line_moq'] : array();
    $costs_rmb     = isset( $_POST['sop_line_cost_rmb'] ) ? (array) $_POST['sop_line_cost_rmb'] : array();
    $product_notes = isset( $_POST['sop_line_product_notes'] ) ? (array) $_POST['sop_line_product_notes'] : array();
    $order_notes   = isset( $_POST['sop_line_order_notes'] ) ? (array) wp_unslash( $_POST['sop_line_order_notes'] ) : array();
    $carton_nos    = isset( $_POST['sop_line_carton_no'] ) ? (array) wp_unslash( $_POST['sop_line_carton_no'] ) : array();
    $image_ids     = isset( $_POST['sop_line_image_id'] ) ? (array) $_POST['sop_line_image_id'] : array();
    $locations     = isset( $_POST['sop_line_location'] ) ? (array) $_POST['sop_line_location'] : array();
    $cbm_units     = isset( $_POST['sop_line_cbm_per_unit'] ) ? (array) $_POST['sop_line_cbm_per_unit'] : array();
    $cbm_totals    = isset( $_POST['sop_line_cbm_total'] ) ? (array) $_POST['sop_line_cbm_total'] : array();

    $lines      = array();
    $sort_index = 0;

    foreach ( $product_ids as $key => $product_id_raw ) {
        $product_id = (int) $product_id_raw;
        if ( $product_id <= 0 ) {
            continue;
        }

        $sku       = isset( $skus[ $key ] ) ? sanitize_text_field( wp_unslash( $skus[ $key ] ) ) : '';
        $qty       = isset( $qtys[ $key ] ) ? floatval( wp_unslash( $qtys[ $key ] ) ) : 0;
        $moq       = isset( $moqs[ $key ] ) ? floatval( wp_unslash( $moqs[ $key ] ) ) : 0;
        $cost_rmb  = isset( $costs_rmb[ $key ] ) ? floatval( wp_unslash( $costs_rmb[ $key ] ) ) : 0;
        $p_notes   = isset( $product_notes[ $key ] ) ? wp_kses_post( wp_unslash( $product_notes[ $key ] ) ) : '';
        $o_notes   = isset( $order_notes[ $key ] ) ? sanitize_textarea_field( $order_notes[ $key ] ) : '';
        $carton_no = isset( $carton_nos[ $key ] ) ? sanitize_text_field( $carton_nos[ $key ] ) : '';
        if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) ) {
            $carton_norm = sop_normalize_carton_numbers_for_display( $carton_no );
            $carton_no   = isset( $carton_norm['value'] ) ? $carton_norm['value'] : $carton_no;
        }
        $image_id  = isset( $image_ids[ $key ] ) ? (int) $image_ids[ $key ] : 0;
        $location  = isset( $locations[ $key ] ) ? sanitize_text_field( wp_unslash( $locations[ $key ] ) ) : '';
        $cbm_unit  = isset( $cbm_units[ $key ] ) ? floatval( wp_unslash( $cbm_units[ $key ] ) ) : 0;
        $cbm_total = isset( $cbm_totals[ $key ] ) ? floatval( wp_unslash( $cbm_totals[ $key ] ) ) : 0;

        $lines[] = array(
            'product_id'          => $product_id,
            'sku_owner'           => $sku,
            'qty_owner'           => $qty,
            'cost_rmb_owner'      => $cost_rmb,
            'moq_owner'           => $moq,
            'product_notes_owner' => $p_notes,
            'order_notes_owner'   => $o_notes,
            'carton_no'           => $carton_no,
            'image_id'            => $image_id,
            'location'            => $location,
            'cbm_per_unit'        => $cbm_unit,
            'cbm_total_owner'     => $cbm_total,
            'sort_index'          => $sort_index++,
        );
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
                        $redirect = add_query_arg(
                            array(
                                'page'        => 'sop-preorder-sheet',
                                'supplier_id' => $supplier_id,
                                'sop_saved'   => '0',
                                'sop_sheet_id'=> (int) $sheet_id,
                            ),
                            admin_url( 'admin.php' )
                        );
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
            $redirect = add_query_arg(
                array(
                    'page'        => 'sop-preorder-sheet',
                    'supplier_id' => $supplier_id,
                    'sop_saved'   => '0',
                ),
                admin_url( 'admin.php' )
            );
            wp_safe_redirect( $redirect );
            exit;
        }
        $sheet_id = (int) $sheet_id;
    }

    // Explicitly persist Purchase Order header fields for both inserts and updates.
    if ( $sheet_id > 0 && function_exists( 'sop_update_preorder_sheet' ) ) {
        $po_header_update = array(
            'order_date_owner'          => ( '' !== $po_order_date ) ? $po_order_date : null,
            'container_load_date_owner' => ( '' !== $po_load_date ) ? $po_load_date : null,
            'arrival_date_owner'        => ( '' !== $po_arrival_date ) ? $po_arrival_date : null,
            'deposit_fx_owner'          => $po_deposit_rmb,
            'balance_fx_owner'          => $po_deposit_usd,
            'header_notes_owner'        => $header_notes_owner,
        );

        $po_update_result = sop_update_preorder_sheet( $sheet_id, $po_header_update );
        if ( is_wp_error( $po_update_result ) ) {
            $redirect = add_query_arg(
                array(
                    'page'        => 'sop-preorder-sheet',
                    'supplier_id' => $supplier_id,
                    'sop_saved'   => '0',
                    'sop_sheet_id'=> (int) $sheet_id,
                ),
                admin_url( 'admin.php' )
            );
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    $lines_result = function_exists( 'sop_replace_preorder_sheet_lines' )
        ? sop_replace_preorder_sheet_lines( (int) $sheet_id, $lines )
        : sop_insert_preorder_sheet_lines( (int) $sheet_id, $lines );
    if ( is_wp_error( $lines_result ) ) {
        $redirect = add_query_arg(
            array(
                'page'        => 'sop-preorder-sheet',
                'supplier_id' => $supplier_id,
                'sop_saved'   => '0',
                'sop_sheet_id'=> (int) $sheet_id,
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    $redirect = add_query_arg(
        array(
            'page'        => 'sop-preorder-sheet',
            'supplier_id' => $supplier_id,
            'sop_saved'   => '1',
            'sop_sheet_id'=> (int) $sheet_id,
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect );
    exit;
}

/**
 * Handle CSV export for a saved pre-order sheet.
 *
 * @return void
 */
add_action( 'admin_post_sop_export_preorder_sheet_csv', 'sop_handle_export_preorder_sheet_csv' );
function sop_handle_export_preorder_sheet_csv() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You are not allowed to export pre-order sheets.', 'sop' ) );
    }

    $nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( ! wp_verify_nonce( $nonce, 'sop_export_preorder_sheet_csv' ) ) {
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
        '%s-order-%s-v%d-%s.xls',
        $supplier_slug,
        $order_number,
        $version,
        $order_date
    );

    $html = class_exists( 'SOP_Preorder_Excel_Exporter' )
        ? SOP_Preorder_Excel_Exporter::build_html_table( $sheet, $lines )
        : new WP_Error( 'sop_export_no_exporter', __( 'Excel exporter is not available.', 'sop' ) );

    if ( is_wp_error( $html ) ) {
        $csv_filename = str_replace( '.xls', '.csv', $filename );
        sop_preorder_stream_csv_export( $sheet, $lines, $csv_filename );
        exit;
    }

    nocache_headers();
    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    echo $html;
    exit;
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
    if ( empty( $sheet ) ) {
        return new WP_Error( 'sop_export_sheet_missing', __( 'Pre-order sheet not found.', 'sop' ) );
    }

    if ( $supplier_id > 0 && isset( $sheet['supplier_id'] ) && (int) $sheet['supplier_id'] !== (int) $supplier_id ) {
        return new WP_Error( 'sop_export_supplier_mismatch', __( 'Supplier mismatch for export.', 'sop' ) );
    }

    $lines = sop_get_preorder_sheet_lines( $sheet_id );
    $lines = is_array( $lines ) ? $lines : array();

    if ( empty( $lines ) ) {
        return new WP_Error( 'sop_export_no_lines', __( 'No lines found for this sheet.', 'sop' ) );
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
 * @param array  $lines        Line rows.
 * @param string $filename     Filename.
 * @return void
 */
function sop_preorder_stream_csv_export( $sheet_header, $lines, $filename ) {
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );

    $output = fopen( 'php://output', 'w' );

    fputcsv(
        $output,
        array(
            'Product ID',
            'SKU',
            'Product name',
            'Location',
            'MOQ',
            'SOQ',
            'Qty',
            'Cost per unit',
            'Line total',
            'Notes',
        )
    );

    foreach ( $lines as $line ) {
        fputcsv(
            $output,
            array(
                isset( $line['product_id'] ) ? $line['product_id'] : '',
                isset( $line['sku'] ) ? $line['sku'] : '',
                isset( $line['product_name'] ) ? $line['product_name'] : '',
                isset( $line['location'] ) ? $line['location'] : '',
                isset( $line['moq'] ) ? $line['moq'] : '',
                isset( $line['soq'] ) ? $line['soq'] : '',
                isset( $line['qty'] ) ? $line['qty'] : '',
                isset( $line['cost_per_unit'] ) ? $line['cost_per_unit'] : '',
                isset( $line['line_total'] ) ? $line['line_total'] : '',
                isset( $line['notes'] ) ? $line['notes'] : '',
            )
        );
    }

    fclose( $output );
}

/**
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

    $status = isset( $sheet['status'] ) ? $sheet['status'] : '';
    if ( 'locked' === $status ) {
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

function sop_preorder_resolve_supplier_params() {
    $suppliers = sop_preorder_get_suppliers();
    $settings  = sop_preorder_get_settings();

    $requested_supplier_id = isset( $_GET['sop_supplier_id'] ) ? (int) $_GET['sop_supplier_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
    $cost_gbp = get_post_meta( $product_id, '_cogs_value', true );

    $cost_rmb = $cost_rmb !== '' ? (float) $cost_rmb : null;
    $cost_usd = $cost_usd !== '' ? (float) $cost_usd : null;
    $cost_eur = $cost_eur !== '' ? (float) $cost_eur : null;
    $cost_gbp = $cost_gbp !== '' ? (float) $cost_gbp : null;

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

    // Preload forecast rows for this supplier and index by product ID.
    $forecast_by_product = array();

    if ( function_exists( 'sop_core_engine' ) ) {
        $engine = sop_core_engine();

        if ( $engine && method_exists( $engine, 'get_supplier_forecast' ) ) {
            $forecast_rows = array();

            // Protect against any runtime errors inside the forecast engine.
            try {
                $forecast_rows = $engine->get_supplier_forecast( $supplier_id, array() );
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

        $notes = get_post_meta( $product_id, '_sop_preorder_notes', true );
        $min   = get_post_meta( $product_id, '_sop_min_order_qty', true );
        $order = get_post_meta( $product_id, '_sop_preorder_order_qty', true );
        $order_sku_override = get_post_meta( $product_id, '_sop_preorder_order_sku', true );
        $removed_flag       = get_post_meta( $product_id, '_sop_preorder_removed', true );

        $notes = is_string( $notes ) ? $notes : '';
        $min   = $min !== '' ? (float) $min : 0.0;
        $order = $order !== '' ? (float) $order : 0.0;
        $order_sku = ( '' !== $order_sku_override ) ? (string) $order_sku_override : $sku;
        $removed   = ! empty( $removed_flag ) ? 1 : 0;

        // Stock on hand via WC product object (wc_get_stock_quantity may not exist on this setup).
        $stock_on_hand = $product->get_stock_quantity();
        if ( null === $stock_on_hand ) {
            $stock_on_hand = 0;
        }
        $stock_on_hand = (float) $stock_on_hand;
        if ( $stock_on_hand < 0 ) {
            $stock_on_hand = 0;
        }


        $inbound_qty = 0.0;

        $cost_supplier = sop_preorder_get_cost_for_supplier_currency( $product_id, $supplier_currency, $settings );
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

        if ( isset( $forecast_by_product[ $product_id ] ) && is_array( $forecast_by_product[ $product_id ] ) ) {
            $forecast_row = $forecast_by_product[ $product_id ];

            // Pre-Order Sheet uses Suggested (Raw) as SOQ.
            if ( isset( $forecast_row['suggested_raw'] ) ) {
                $suggested_order_qty = (float) $forecast_row['suggested_raw'];
            } elseif ( isset( $forecast_row['suggested_capped'] ) ) {
                // Backwards compatibility if only capped is present.
                $suggested_order_qty = (float) $forecast_row['suggested_capped'];
            }

            if ( $suggested_order_qty < 0 ) {
                $suggested_order_qty = 0.0;
            }
        }

        // SOQ should be rounded up to the nearest whole number.
        $suggested_order_qty = ceil( $suggested_order_qty );

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
        ];

    }

    wp_reset_postdata();

    return $rows;
}

add_action( 'admin_init', 'sop_preorder_handle_post' );
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

    $supplier_id = isset( $_POST['sop_supplier_id'] ) ? (int) $_POST['sop_supplier_id'] : 0;

    if ( $supplier_id <= 0 ) {
        return;
    }

    $skus          = isset( $_POST['sop_sku'] ) && is_array( $_POST['sop_sku'] ) ? $_POST['sop_sku'] : [];
    $notes         = isset( $_POST['sop_notes'] ) && is_array( $_POST['sop_notes'] ) ? $_POST['sop_notes'] : [];
    $mins          = isset( $_POST['sop_min_order_qty'] ) && is_array( $_POST['sop_min_order_qty'] ) ? $_POST['sop_min_order_qty'] : [];
    $orders        = isset( $_POST['sop_preorder_order_qty'] ) && is_array( $_POST['sop_preorder_order_qty'] ) ? $_POST['sop_preorder_order_qty'] : [];
    $costs         = isset( $_POST['sop_cost_unit_supplier'] ) && is_array( $_POST['sop_cost_unit_supplier'] ) ? $_POST['sop_cost_unit_supplier'] : [];
    $removed_flags = isset( $_POST['sop_removed'] ) && is_array( $_POST['sop_removed'] ) ? $_POST['sop_removed'] : [];
    $product_ids   = isset( $_POST['sop_product_id'] ) && is_array( $_POST['sop_product_id'] ) ? $_POST['sop_product_id'] : [];

    foreach ( $product_ids as $index => $raw_product_id ) {
        $product_id = (int) $raw_product_id;
        if ( $product_id <= 0 ) {
            continue;
        }

        $sku_val     = isset( $skus[ $index ] ) ? wc_clean( wp_unslash( $skus[ $index ] ) ) : '';
        $note_val    = isset( $notes[ $index ] ) ? wp_kses_post( wp_unslash( $notes[ $index ] ) ) : '';
        $min_val     = isset( $mins[ $index ] ) ? (float) $mins[ $index ] : 0.0;
        $order_val   = isset( $orders[ $index ] ) ? (float) $orders[ $index ] : 0.0;
        $cost_val    = isset( $costs[ $index ] ) ? (float) $costs[ $index ] : 0.0;
        $removed_val = ! empty( $removed_flags[ $index ] ) ? 1 : 0;

        if ( '' !== $sku_val ) {
            update_post_meta( $product_id, '_sop_preorder_order_sku', $sku_val );
        } else {
            delete_post_meta( $product_id, '_sop_preorder_order_sku' );
        }

        update_post_meta( $product_id, '_sop_preorder_notes', $note_val );
        update_post_meta( $product_id, '_sop_min_order_qty', $min_val );
        update_post_meta( $product_id, '_sop_preorder_order_qty', $order_val );
        update_post_meta( $product_id, '_sop_preorder_removed', $removed_val );

        $ctx      = sop_preorder_resolve_supplier_params();
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

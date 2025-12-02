<?php
/**
 * Stock Order Plugin - Phase 4.1 - Pre-Order Sheet Core (admin only)
 * File version: 10.22
 * - Under Stock Order main menu.
 * - Supplier filter via _sop_supplier_id.
 * - Supplier currency-aware costs using plugin meta:
 *      _sop_cost_rmb, _sop_cost_usd, _sop_cost_eur, fallback _cogs_value for GBP.
 * - Editable & persisted per product (when sheet is not locked):
 *      Order SKU (sheet-only) -> meta: _sop_preorder_order_sku
 *      Notes              -> meta: _sop_preorder_notes
 *      Min order qty      -> meta: _sop_min_order_qty
 *      Manual order qty   -> meta: _sop_preorder_order_qty
 *      Cost per unit      -> meta: _sop_cost_rmb / _sop_cost_usd / _sop_cost_eur / _cogs_value
 * - Sheet lock / unlock per supplier:
 *      Option: sop_preorder_lock_{supplier_id} stores lock timestamp.
 *      When locked, inputs are disabled, rounding buttons are hidden, and Save is disabled.
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

    if ( $supplier_id > 0 && function_exists( 'sop_get_preorder_sheets_for_supplier' ) ) {
        $sheets = sop_get_preorder_sheets_for_supplier( $supplier_id );
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
                            <td><?php echo esc_html( $sheet['supplier_id'] ); ?></td>
                            <td><?php echo esc_html( isset( $sheet['status'] ) ? $sheet['status'] : '' ); ?></td>
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
                                <a class="button" href="<?php echo esc_url( $open_url ); ?>">
                                    <?php esc_html_e( 'Open', 'sop' ); ?>
                                </a>
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

    // For now, default order date to today (UTC); this can be made editable later.
    $order_date = gmdate( 'Y-m-d' );

    $header_data = array(
        'supplier_id'      => $supplier_id,
        'status'           => 'draft',
        'order_date_owner' => $order_date,
        'container_type'   => $container_type,
        'created_at'       => $now_utc,
        'updated_at'       => $now_utc,
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
            'order_notes_owner'   => '',
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
            'weight'              => $weight,
            'line_weight'         => $line_weight,
            'suggested_order_qty' => $suggested_order_qty,
            'cubic_cm'            => $cubic_cm,
            'line_cbm'            => $line_cbm,
            'regular_unit_price'  => $regular_unit_price,
            'regular_line_price'  => $regular_line_price,
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

    if ( isset( $_POST['sop_lock_sheet'] ) && $_POST['sop_lock_sheet'] === '1' ) {
        sop_preorder_lock_sheet( $supplier_id );
        return;
    }

    if ( isset( $_POST['sop_unlock_sheet'] ) && $_POST['sop_unlock_sheet'] === '1' ) {
        sop_preorder_unlock_sheet( $supplier_id );
        return;
    }

    $locked_ts = sop_preorder_get_lock_timestamp( $supplier_id );
    if ( $locked_ts > 0 ) {
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

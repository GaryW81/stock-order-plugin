<?php
/*** Stock Order Plugin - Phase 4.1 - Pre-Order Sheet UI (admin only) V10.82 *
 * - Under Stock Order main menu.
 * - Supplier filter via _sop_supplier_id.
 * - 90vh scroll, sticky header, sortable columns, column visibility, rounding, CBM bar.
 * - Supplier currency-aware costs using plugin meta:
 *      _sop_cost_rmb, _sop_cost_usd, _sop_cost_eur, fallback _cogs_value for GBP.
 * - Editable & persisted per product (when sheet is not locked):
 *      SKU                -> meta: _sku
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

function sop_preorder_render_admin_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'sop' ) );
    }

    $suppliers = sop_preorder_get_suppliers();
    $settings  = sop_preorder_get_settings();

    $selected_supplier_id = isset( $_GET['sop_supplier_id'] )
        ? (int) $_GET['sop_supplier_id']
        : 0;
    $current_sheet_id     = isset( $_GET['sop_sheet_id'] ) ? (int) $_GET['sop_sheet_id'] : 0;
    if ( 0 === $current_sheet_id && isset( $_GET['sop_preorder_sheet_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_sheet_id = (int) $_GET['sop_preorder_sheet_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    $current_sheet        = null;
    $current_lines        = array();

    $supplier = null;
    foreach ( $suppliers as $row ) {
        if ( (int) $row['id'] === $selected_supplier_id ) {
            $supplier = $row;
            break;
        }
    }

    if ( ! $supplier && ! empty( $suppliers ) ) {
        $supplier             = $suppliers[0];
        $selected_supplier_id = (int) $supplier['id'];
    }

    $supplier_currency = 'GBP';

    if ( $supplier ) {
        $ctx = sop_preorder_resolve_supplier_params();

        if ( ! empty( $ctx['currency_code'] ) ) {
            $supplier_currency = $ctx['currency_code'];
        }
    }

    $rmb_to_usd_rate = 0.0;
    if ( 'RMB' === $supplier_currency && $selected_supplier_id > 0 && function_exists( 'sop_get_rmb_to_usd_rate_for_supplier' ) ) {
        $rmb_to_usd_rate = sop_get_rmb_to_usd_rate_for_supplier( $selected_supplier_id );
    }

    $container_selection = isset( $_GET['sop_container'] )
        ? sanitize_text_field( wp_unslash( $_GET['sop_container'] ) )
        : '';

    // Additional container controls.
    $pallet_layer = isset( $_GET['sop_pallet_layer'] ) ? 1 : 0;

    $allowance = isset( $_GET['sop_allowance'] )
        ? (float) $_GET['sop_allowance']
        : 5.0;
    if ( $allowance < -50 ) {
        $allowance = -50;
    } elseif ( $allowance > 50 ) {
        $allowance = 50;
    }

    // SKU filter (substring match, case-insensitive).
    $sku_filter = '';
    if ( isset( $_GET['sop_sku_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $sku_filter = sanitize_text_field( wp_unslash( $_GET['sop_sku_filter'] ) );
    }

    // Container metrics are resolved after sheet context is loaded.
    $base_cbm      = 0.0;
    $floor_area    = 0.0;
    $container_cbm = 0.0;
    $effective_cbm = 0.0;

    $lock_timestamp = $supplier ? sop_preorder_get_lock_timestamp( $supplier['id'] ) : 0;
    $is_locked      = $lock_timestamp > 0;

    $rows = [];
    $overlay_stats = array(
        'matched_rows' => 0,
        'total_lines'  => 0,
    );
    if ( $supplier ) {
        $rows = sop_preorder_build_rows_for_supplier( $supplier['id'], $supplier_currency, $settings );
    }

    if ( '' !== $sku_filter ) {
        $filter_value = strtolower( $sku_filter );
        $rows         = array_values(
            array_filter(
                $rows,
                static function ( $row ) use ( $filter_value ) {
                    $sku = '';

                    if ( ! empty( $row['order_sku'] ) ) {
                        $sku = (string) $row['order_sku'];
                    } elseif ( ! empty( $row['sku'] ) ) {
                        $sku = (string) $row['sku'];
                    } elseif ( ! empty( $row['product_sku'] ) ) {
                        $sku = (string) $row['product_sku'];
                    }

                    if ( '' === $sku ) {
                        return false;
                    }

                    return false !== strpos( strtolower( $sku ), $filter_value );
                }
            )
        );
    }

    // Overlay saved sheet data if present.
    if ( $current_sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) && function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        $current_sheet = sop_get_preorder_sheet( $current_sheet_id );

        if ( ! $current_sheet || ! is_array( $current_sheet ) ) {
            $current_sheet_id = 0;
            $current_sheet    = null;
        } elseif ( isset( $current_sheet['supplier_id'] ) && ( (int) $current_sheet['supplier_id'] !== $selected_supplier_id ) ) {
            $current_sheet_id = 0;
            $current_sheet    = null;
        } else {
            $current_lines = sop_get_preorder_sheet_lines( $current_sheet_id );
            if ( ! is_array( $current_lines ) ) {
                $current_lines = array();
            }

            if ( '' === $container_selection && ! empty( $current_sheet['container_type'] ) ) {
                $container_selection = (string) $current_sheet['container_type'];
            }

            if ( ! empty( $current_lines ) ) {
                $lines_by_product = array();
                foreach ( $current_lines as $line ) {
                    $pid = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
                    if ( $pid <= 0 ) {
                        continue;
                    }
                    $lines_by_product[ $pid ] = $line;
                }

                $overlay_stats['total_lines'] = count( $lines_by_product );

                foreach ( $rows as &$row ) {
                    $pid = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
                    if ( $pid <= 0 || ! isset( $lines_by_product[ $pid ] ) ) {
                        continue;
                    }

                    $line = $lines_by_product[ $pid ];

                    if ( isset( $line['qty_owner'] ) ) {
                        $row['manual_order_qty'] = (float) $line['qty_owner'];
                    }

                    if ( isset( $line['moq_owner'] ) && $line['moq_owner'] > 0 ) {
                        $row['min_order_qty'] = (float) $line['moq_owner'];
                    }

                    if ( isset( $line['cost_rmb_owner'] ) && $line['cost_rmb_owner'] > 0 ) {
                        $row['cost_supplier'] = (float) $line['cost_rmb_owner'];
                    }

                    if ( isset( $line['product_notes_owner'] ) ) {
                        $row['notes'] = $line['product_notes_owner'];
                    }

                    if ( isset( $line['carton_no'] ) ) {
                        $carton_val = (string) $line['carton_no'];
                        if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) ) {
                            $carton_norm = sop_normalize_carton_numbers_for_display( $carton_val );
                            $carton_val  = isset( $carton_norm['value'] ) ? $carton_norm['value'] : $carton_val;
                            $row['carton_sort_min'] = isset( $carton_norm['sort_min'] ) ? $carton_norm['sort_min'] : null;
                        }
                        $row['carton_no'] = $carton_val;
                    }

                    $overlay_stats['matched_rows']++;
                }
                unset( $row );
            }
        }
    }

    // Base CBM and floor area per container type using real internal dimensions.
    switch ( $container_selection ) {
        case '20ft':
            // 20ft GP: 5.90m x 2.35m x 2.39m ~ 33.2 CBM.
            $base_cbm   = 33.2;
            $floor_area = 5.90 * 2.35;
            break;
        case '40ft':
            // 40ft GP: 12.03m x 2.35m x 2.39m ~ 67.7 CBM.
            $base_cbm   = 67.7;
            $floor_area = 12.03 * 2.35;
            break;
        case '40ft_hc':
            // 40ft HQ: 12.03m x 2.35m x 2.69m ~ 76.3 CBM.
            $base_cbm   = 76.3;
            $floor_area = 12.03 * 2.35;
            break;
        default:
            $base_cbm   = 0.0;
            $floor_area = 0.0;
            break;
    }

    // Apply 150mm pallet layer if enabled.
    $container_cbm = $base_cbm;
    if ( $pallet_layer && $floor_area > 0 ) {
        $lost_cubic    = $floor_area * 0.15; // 150mm height.
        $container_cbm = max( 0.0, $container_cbm - $lost_cubic );
    }

    // Apply allowance percentage.
    $effective_cbm = $container_cbm;
    if ( $effective_cbm > 0 && 0.0 !== $allowance ) {
        $effective_cbm = $effective_cbm * ( 1 - ( $allowance / 100 ) );
    }
    if ( $effective_cbm < 0 ) {
        $effective_cbm = 0.0;
    }

    $total_units          = 0.0;
    $total_cost_gbp       = 0.0;
    $total_cost_supplier  = 0.0;
    $total_cbm            = 0.0;
    $total_skus           = 0;

    foreach ( $rows as $row ) {
        if ( ! empty( $row['removed'] ) ) {
            continue;
        }
        $qty = (float) $row['manual_order_qty'];
        if ( $qty <= 0 ) {
            continue;
        }

        $total_units         += $qty;
        $total_cost_gbp      += $qty * (float) $row['cost_gbp'];
        $total_cost_supplier += $qty * (float) $row['cost_supplier'];

        if ( isset( $row['line_cbm'] ) ) {
            $total_cbm += (float) $row['line_cbm'];
        }

        $total_skus++;
    }

    $used_cbm     = 0.0; // raw percent, may exceed 100.
    $used_cbm_bar = 0.0; // clamped percent for bar width.

    if ( $effective_cbm > 0 && $total_cbm > 0 ) {
        $used_cbm = ( $total_cbm / $effective_cbm ) * 100.0;

        if ( $used_cbm < 0.0 ) {
            $used_cbm_bar = 0.0;
        } elseif ( $used_cbm > 100.0 ) {
            $used_cbm_bar = 100.0;
        } else {
            $used_cbm_bar = $used_cbm;
        }
    }
    $currency_symbol = 'GBP';
    switch ( $supplier_currency ) {
        case 'RMB':
            $currency_symbol = 'RMB';
            break;
        case 'USD':
            $currency_symbol = 'USD';
            break;
        case 'EUR':
            $currency_symbol = 'EUR';
            break;
        case 'GBP':
        default:
            $currency_symbol = 'GBP';
            break;
    }

    $order_number_value = '';
    $current_version    = 1;
    $current_status     = '';
    $current_updated    = '';
    if ( $current_sheet && is_array( $current_sheet ) ) {
        $order_number_value = ! empty( $current_sheet['order_number_label'] ) ? $current_sheet['order_number_label'] : '';
        $current_version    = ! empty( $current_sheet['edit_version'] ) ? (int) $current_sheet['edit_version'] : 1;
        $current_status     = ! empty( $current_sheet['status'] ) ? $current_sheet['status'] : '';
        $current_updated    = ! empty( $current_sheet['updated_at'] ) ? $current_sheet['updated_at'] : '';
    }
    ?>
    <div id="sop-preorder-wrapper"
         class="wrap sop-preorder-wrap"
         data-rmb-to-usd-rate="<?php echo esc_attr( $rmb_to_usd_rate ); ?>">
        <h1><?php esc_html_e( 'Pre-Order Sheet', 'sop' ); ?></h1>
        <?php
        $sop_saved    = isset( $_GET['sop_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['sop_saved'] ) ) : '';
        $sop_sheet_id = isset( $_GET['sop_sheet_id'] ) ? absint( $_GET['sop_sheet_id'] ) : 0;

        if ( '1' === $sop_saved ) {
            $message = $sop_sheet_id
                ? sprintf( __( 'Pre-order sheet saved (ID %d).', 'sop' ), $sop_sheet_id )
                : __( 'Pre-order sheet saved.', 'sop' );

            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html( $message )
            );
        } elseif ( '0' === $sop_saved ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__( 'There was a problem saving the pre-order sheet. Please try again.', 'sop' )
            );
        }
        ?>

        <?php if ( $current_sheet_id > 0 && $current_sheet ) : ?>
            <div class="notice notice-info sop-preorder-sheet-banner">
                <p>
                    <?php
                    printf(
                        /* translators: 1: sheet ID, 2: order number, 3: version, 4: status, 5: updated date */
                        esc_html__( 'Editing saved pre-order sheet #%1$d. Order: %2$s. Version: %3$d. Status: %4$s. Last updated: %5$s', 'sop' ),
                        (int) $current_sheet_id,
                        $order_number_value ? esc_html( $order_number_value ) : esc_html__( 'N/A', 'sop' ),
                        (int) $current_version,
                        esc_html( $current_status ? $current_status : 'draft' ),
                        esc_html( $current_updated )
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="sop-preorder-header">
            <form id="sop-preorder-filter-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sop_preorder_filter', 'sop_preorder_filter_nonce' ); ?>
                <input type="hidden" name="action" value="sop_preorder_filter" />
                <input type="hidden" name="page" value="sop-preorder-sheet" />
                <?php if ( $current_sheet_id > 0 ) : ?>
                    <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                    <input type="hidden" name="sop_preorder_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                <?php endif; ?>
            </form>

            <?php if ( $current_sheet_id > 0 ) : ?>
                <form id="sop-preorder-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
                    <input type="hidden" name="action" value="sop_export_preorder_sheet_csv" />
                    <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                    <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $selected_supplier_id ); ?>" />
                    <?php wp_nonce_field( 'sop_export_preorder_sheet_csv' ); ?>
                </form>
            <?php endif; ?>

            <div class="sop-preorder-card sop-preorder-card--top">
                <div class="sop-preorder-card-row sop-preorder-top-row">
                    <div class="sop-preorder-top-left">
                        <label>
                            <?php esc_html_e( 'Supplier:', 'sop' ); ?>
                            <select name="sop_supplier_id" form="sop-preorder-filter-form">
                                <?php foreach ( $suppliers as $row ) : ?>
                                    <option value="<?php echo esc_attr( $row['id'] ); ?>" <?php selected( (int) $row['id'], $selected_supplier_id ); ?>>
                                        <?php echo esc_html( $row['name'] ); ?> (<?php echo esc_html( $row['currency_code'] ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label for="sop-header-order-number" class="sop-preorder-order-label">
                            <?php esc_html_e( 'Order #', 'sop' ); ?>
                            <input type="text"
                                   id="sop-header-order-number"
                                   name="sop_header_order_number"
                                   value="<?php echo esc_attr( $order_number_value ); ?>"
                                   style="width: 110px;"
                                   form="sop-preorder-sheet-form" />
                        </label>
                    </div>
                    <div class="sop-preorder-top-right">
                        <?php if ( $current_sheet_id > 0 ) : ?>
                            <button type="submit"
                                    class="button"
                                    form="sop-preorder-export-form">
                                <?php esc_html_e( 'Export Excel (.xls)', 'sop' ); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ( $selected_supplier_id > 0 ) : ?>
                            <?php
                            $saved_sheets_url = add_query_arg(
                                array(
                                    'page'        => 'sop-preorder-sheets',
                                    'supplier_id' => (int) $selected_supplier_id,
                                ),
                                admin_url( 'admin.php' )
                            );
                            ?>
                            <a class="button button-secondary" href="<?php echo esc_url( $saved_sheets_url ); ?>">
                                <?php esc_html_e( 'View saved sheets', 'sop' ); ?>
                            </a>
                        <?php endif; ?>

                        <button type="submit" class="button button-primary" name="sop_preorder_save" form="sop-preorder-sheet-form">
                            <?php
                            echo ( $current_sheet_id > 0 )
                                ? esc_html__( 'Update sheet', 'sop' )
                                : esc_html__( 'Save sheet', 'sop' );
                            ?>
                        </button>

                        <?php if ( $is_locked ) : ?>
                            <span class="sop-lock-status sop-locked"><?php esc_html_e( 'Sheet is LOCKED', 'sop' ); ?></span>
                        <?php else : ?>
                            <span class="sop-lock-status sop-unlocked"><?php esc_html_e( 'Sheet is UNLOCKED', 'sop' ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sop-preorder-card sop-preorder-card--planning">
                <div class="sop-preorder-card__row sop-preorder-card__row--container-top">
                    <div class="sop-preorder-container-item sop-preorder-container-item--select">
                        <label>
                            <?php esc_html_e( 'Container:', 'sop' ); ?>
                            <select name="sop_container" form="sop-preorder-filter-form">
                                <option value=""><?php esc_html_e( 'None', 'sop' ); ?></option>
                                <option value="20ft" <?php selected( $container_selection, '20ft' ); ?>>20&#39; (33.2 CBM)</option>
                                <option value="40ft" <?php selected( $container_selection, '40ft' ); ?>>40&#39; (67.7 CBM)</option>
                                <option value="40ft_hc" <?php selected( $container_selection, '40ft_hc' ); ?>>40&#39; HQ (76.3 CBM)</option>
                            </select>
                        </label>
                    </div>

                    <div class="sop-preorder-container-item sop-preorder-container-item--pallet">
                        <label class="sop-pallet-layer-label">
                            <input type="checkbox" name="sop_pallet_layer" value="1" <?php checked( $pallet_layer ); ?> form="sop-preorder-filter-form" />
                            <?php esc_html_e( '150mm pallet layer', 'sop' ); ?>
                        </label>
                    </div>

                    <div class="sop-preorder-container-item sop-preorder-container-item--allowance">
                        <label class="sop-allowance-label">
                            <?php esc_html_e( 'Allowance:', 'sop' ); ?>
                            <input type="number" name="sop_allowance" value="<?php echo esc_attr( $allowance ); ?>" step="1" min="-50" max="50" form="sop-preorder-filter-form" />
                            %
                        </label>
                    </div>

                    <div class="sop-preorder-container-item sop-preorder-container-item--button">
                        <button type="submit" class="button button-secondary" name="sop_preorder_update_container" value="1" form="sop-preorder-filter-form">
                            <?php esc_html_e( 'Update container', 'sop' ); ?>
                        </button>
                    </div>
                </div>

                <div class="sop-preorder-card-row sop-preorder-middle-bottom">
                    <div class="sop-preorder-totals">
                        <span><strong><?php esc_html_e( 'Total Units', 'sop' ); ?>:</strong> <span id="sop-total-units"><?php echo esc_html( number_format_i18n( $total_units, 0 ) ); ?></span></span>
                        <span><strong><?php esc_html_e( 'Total SKUs', 'sop' ); ?>:</strong> <span id="sop-total-skus"><?php echo esc_html( number_format_i18n( $total_skus, 0 ) ); ?></span></span>
                        <span><strong><?php esc_html_e( 'Total Cost (GBP)', 'sop' ); ?>:</strong> <span id="sop-total-cost-gbp"><?php echo esc_html( wc_price( $total_cost_gbp ) ); ?></span></span>
                        <span><strong><?php printf( esc_html__( 'Total Cost (%s)', 'sop' ), esc_html( $supplier_currency ) ); ?>:</strong> <span id="sop-total-cost-supplier"><?php echo esc_html( $currency_symbol . ' ' . number_format_i18n( $total_cost_supplier, 2 ) ); ?></span></span>
                    </div>
                    <div class="sop-preorder-fill">
                        <strong><?php esc_html_e( 'Container Fill', 'sop' ); ?>:</strong>
                        <div class="sop-cbm-bar-wrapper" title="<?php echo esc_attr( $used_cbm ); ?>%">
                            <div class="sop-cbm-bar" style="width: <?php echo esc_attr( $used_cbm ); ?>%;"></div>
                        </div>
                        <span class="sop-cbm-label" id="sop-cbm-label"><?php echo esc_html( number_format_i18n( $used_cbm, 1 ) ); ?>%</span>
                    </div>
                </div>
            </div>

            <div class="sop-preorder-card sop-preorder-card--tools">
                <div class="sop-preorder-card-row sop-preorder-bottom-row">
                    <div class="sop-preorder-bottom-left">
                        <span><?php esc_html_e( 'Rounding:', 'sop' ); ?></span>
                        <?php if ( ! $is_locked ) : ?>
                            <label class="sop-round-step-label">
                                <?php esc_html_e( 'Step:', 'sop' ); ?>
                                <select class="sop-round-step">
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                </select>
                            </label>
                            <button type="button" class="button" data-round-mode="up"><?php esc_html_e( 'Round Up', 'sop' ); ?></button>
                            <button type="button" class="button" data-round-mode="down"><?php esc_html_e( 'Round Down', 'sop' ); ?></button>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e( 'Rounding disabled for locked sheet.', 'sop' ); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! $is_locked ) : ?>
                        <div class="sop-preorder-bottom-middle">
                            <button type="button" class="button" id="sop-apply-soq-to-qty"><?php esc_html_e( 'Apply SOQ to Qty', 'sop' ); ?></button>
                            <button type="button" class="button" id="sop-preorder-remove-selected"><?php esc_html_e( 'Remove selected', 'sop' ); ?></button>
                            <label for="sop-preorder-show-removed" class="sop-preorder-show-removed">
                                <input type="checkbox" id="sop-preorder-show-removed" />
                                <?php esc_html_e( 'Show removed rows', 'sop' ); ?>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="sop-preorder-bottom-right">
                        <div class="sop-preorder-filter-sku">
                            <label for="sop_sku_filter" class="screen-reader-text">
                                <?php esc_html_e( 'Search by SKU', 'sop' ); ?>
                            </label>
                            <div class="sop-preorder-filter-sku-field">
                                <div class="sop-preorder-sku-search">
                                    <input type="text"
                                           id="sop_sku_filter"
                                           name="sop_sku_filter"
                                           value="<?php echo esc_attr( $sku_filter ); ?>"
                                           placeholder="<?php esc_attr_e( 'Search SKU', 'stock-order-plugin' ); ?>"
                                           class="regular-text sop-preorder-sku-input"
                                           form="sop-preorder-filter-form" />
                                    <span class="dashicons dashicons-search sop-preorder-sku-icon" aria-hidden="true"></span>
                                </div>
                            </div>
                        </div>
                        <div class="sop-preorder-columns">
                            <button
                                type="button"
                                class="button sop-preorder-columns-toggle"
                                aria-expanded="false"
                            >
                                <?php esc_html_e( 'Columns', 'sop' ); ?>
                            </button>

                            <div class="sop-preorder-columns-popover" aria-hidden="true">
                                <div class="sop-preorder-columns-panel">
                                    <ul class="sop-preorder-columns-list">
                                        <?php
                                        $sop_column_labels = array(
                                            'image'         => __( 'Image', 'sop' ),
                                            'location'      => __( 'Location', 'sop' ),
                                            'sku'           => __( 'SKU', 'sop' ),
                                            'brand'         => __( 'Brand', 'sop' ),
                                            'category'      => __( 'Category', 'sop' ),
                                            'product'       => __( 'Product', 'sop' ),
                                            'cost_supplier' => __( 'Cost per unit', 'sop' ),
                                            'cost_usd'      => __( 'Unit price (USD)', 'sop' ),
                                            'stock'         => __( 'Stock', 'sop' ),
                                            'inbound'       => __( 'Inbound', 'sop' ),
                                            'min_order'     => __( 'MOQ', 'sop' ),
                                            'soq'           => __( 'SOQ', 'sop' ),
                                            'order_qty'     => __( 'Qty', 'sop' ),
                                            'line_total'    => __( 'Line total', 'sop' ),
                                            'cubic'         => __( 'cm3 per unit', 'sop' ),
                                            'line_cbm'      => __( 'Line CBM', 'sop' ),
                                            'regular_unit'  => __( 'Price excl.', 'sop' ),
                                            'regular_line'  => __( 'Line excl.', 'sop' ),
                                            'notes'         => __( 'Product notes', 'sop' ),
                                            'order_notes'   => __( 'Order notes', 'sop' ),
                                            'carton_no'     => __( 'Carton no.', 'sop' ),
                                        );

                                        foreach ( $sop_column_labels as $column_key => $column_label ) :
                                            ?>
                                            <li>
                                                <label>
                                                    <input
                                                        type="checkbox"
                                                        data-column="<?php echo esc_attr( $column_key ); ?>"
                                                        checked="checked"
                                                    />
                                                    <?php echo esc_html( $column_label ); ?>
                                                </label>
                                            </li>
                                            <?php
                                        endforeach;
                                        ?>
                                    </ul>
                                </div><!-- .sop-preorder-columns-panel -->
                            </div><!-- .sop-preorder-columns-popover -->
                        </div><!-- .sop-preorder-columns -->
                    </div>
                </div>
            </div>

        </div>

        <form id="sop-preorder-sheet-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sop_save_preorder_sheet', 'sop_save_preorder_sheet_nonce' ); ?>
                <input type="hidden" name="action" value="sop_save_preorder_sheet" />
                <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                <input type="hidden" name="sop_supplier_id" value="<?php echo esc_attr( $selected_supplier_id ); ?>" />
                <input type="hidden" name="sop_supplier_name" value="<?php echo isset( $supplier['name'] ) ? esc_attr( $supplier['name'] ) : ''; ?>" />
                <input type="hidden" name="sop_container_type" value="<?php echo esc_attr( $container_selection ); ?>" />
                <input type="hidden" name="sop_allowance_percent" value="<?php echo esc_attr( $allowance ); ?>" />

                <div class="sop-preorder-table-wrapper">
                <table class="wp-list-table widefat fixed striped sop-preorder-table">
                    <thead>
                        <tr>
                            <th class="sop-preorder-col-select">
                                <input type="checkbox" id="sop-preorder-select-all" />
                            </th>
                            <th class="column-image" data-column="image"><?php esc_html_e( 'Image', 'sop' ); ?></th>
                            <th class="column-location" data-column="location" data-sort="location" title="<?php esc_attr_e( 'Warehouse location / bin', 'sop' ); ?>"><?php esc_html_e( 'Location', 'sop' ); ?></th>
                            <th class="column-sku" data-column="sku" data-sort="sku" data-sort-key="sku" title="<?php esc_attr_e( 'SKU (stock-keeping unit)', 'sop' ); ?>"><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                            <th class="column-brand" data-column="brand" data-sort="brand" title="<?php esc_attr_e( 'Brand / manufacturer', 'sop' ); ?>"><?php esc_html_e( 'Brand', 'sop' ); ?></th>
                            <th class="column-category" data-column="category" data-sort="category" data-sort-key="category" title="<?php esc_attr_e( 'Product categories', 'sop' ); ?>"><?php esc_html_e( 'Category', 'sop' ); ?></th>
                            <th class="column-name" data-column="product" data-sort="name" title="<?php esc_attr_e( 'Product name', 'sop' ); ?>"><?php esc_html_e( 'Product', 'sop' ); ?></th>
                            <th class="column-cost-supplier" data-column="cost_supplier" data-sort="cost" title="<?php esc_attr_e( 'Cost per unit in supplier currency (GBP, USD, EUR, RMB)', 'sop' ); ?>">
                                <?php
                                printf(
                                    /* translators: %s: supplier currency code. */
                                    esc_html__( 'Cost per unit (%s)', 'sop' ),
                                    esc_html( $supplier_currency )
                                );
                                ?>
                            </th>
                            <?php if ( 'RMB' === $supplier_currency ) : ?>
                                <th class="column-cost-usd" data-column="cost_usd" data-sort="unit_price_usd" data-sort-key="unit_price_usd">
                                    <span class="sop-preorder-header-label sop-preorder-header-label--wrap-2">
                                        <?php echo esc_html__( 'Unit price', 'stock-order-plugin' ); ?><br>
                                        <?php echo esc_html__( '(USD)', 'stock-order-plugin' ); ?>
                                    </span>
                                </th>
                            <?php endif; ?>
                            <th class="column-stock" data-column="stock" data-sort="stock" title="<?php esc_attr_e( 'Stock on hand', 'sop' ); ?>"><?php esc_html_e( 'Stock', 'sop' ); ?></th>
                            <th class="column-inbound" data-column="inbound" data-sort="inbound" title="<?php esc_attr_e( 'Inbound quantity on purchase orders', 'sop' ); ?>"><?php esc_html_e( 'Inbound', 'sop' ); ?></th>
                            <th class="column-min-order" data-column="min_order" data-sort="moq" title="<?php esc_attr_e( 'Minimum order quantity', 'sop' ); ?>"><?php esc_html_e( 'MOQ', 'sop' ); ?></th>
                            <th class="column-suggested" data-column="soq" data-sort="soq" title="<?php esc_attr_e( 'Suggested order quantity', 'sop' ); ?>"><?php esc_html_e( 'SOQ', 'sop' ); ?></th>
                            <th class="column-order-qty" data-column="order_qty" data-sort="order_qty" title="<?php esc_attr_e( 'Manual order quantity for this shipment', 'sop' ); ?>"><?php esc_html_e( 'Qty', 'sop' ); ?></th>
                            <th class="column-line-total-supplier" data-column="line_total" data-sort="total" title="<?php esc_attr_e( 'Line total in supplier currency', 'sop' ); ?>">
                                <?php echo esc_html__( 'Line total', 'sop' ); ?>
                                <br />
                                <?php
                                    printf(
                                        '(%s)',
                                    esc_html( $supplier_currency )
                                );
                                ?>
                            </th>
                            <th class="column-cubic-item" data-column="cubic" data-sort="cubic" title="<?php esc_attr_e( 'Cubic centimetres per unit', 'sop' ); ?>">
                                <span class="sop-preorder-header-label sop-preorder-header-label--wrap-2">
                                    <?php echo esc_html__( 'cm3', 'stock-order-plugin' ); ?><br>
                                    <?php echo esc_html__( 'per unit', 'stock-order-plugin' ); ?>
                                </span>
                            </th>
                            <th class="column-line-cbm" data-column="line_cbm" data-sort="line_cbm" title="<?php esc_attr_e( 'Line volume in cubic metres', 'sop' ); ?>"><?php esc_html_e( 'Line CBM', 'sop' ); ?></th>
                            <th class="column-regular-unit" data-column="regular_unit" data-sort="price_ex" title="<?php esc_attr_e( 'Regular WooCommerce price per unit excluding VAT', 'sop' ); ?>"><?php esc_html_e( 'Price excl.', 'sop' ); ?></th>
                            <th class="column-regular-line" data-column="regular_line" data-sort="line_ex" title="<?php esc_attr_e( 'Regular WooCommerce line price excluding VAT', 'sop' ); ?>"><?php esc_html_e( 'Line excl.', 'sop' ); ?></th>
                            <th class="column-notes" data-column="notes" data-sort="notes" title="<?php esc_attr_e( 'Internal notes for this product.', 'sop' ); ?>"><?php esc_html_e( 'Product notes', 'sop' ); ?></th>
                            <th class="column-order-notes" data-column="order_notes" data-sort="order_notes" title="<?php esc_attr_e( 'Order-specific notes', 'sop' ); ?>"><?php esc_html_e( 'Order notes', 'sop' ); ?></th>
                            <th class="column-carton-no"
                                data-column="carton_no"
                                data-sort="carton_no"
                                data-sort-key="carton_no"
                                title="<?php esc_attr_e( 'Carton numbers only. Use numbers & ranges: e.g. 4,7,12-13. Other packing info ? Order notes.', 'sop' ); ?>">
                                <?php esc_html_e( 'Carton no.', 'sop' ); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                            <tr>
                                <td colspan="<?php echo ( 'RMB' === $supplier_currency ) ? '22' : '21'; ?>">
                                    <?php esc_html_e( 'No products found for this supplier.', 'sop' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php $sop_row_index = 0; ?>
                            <?php foreach ( $rows as $index => $row ) :
                                $product_id           = (int) $row['product_id'];
                                $name                 = $row['name'];
                                $order_sku            = '';
                                if ( isset( $row['order_sku'] ) && '' !== $row['order_sku'] ) {
                                    $order_sku = $row['order_sku'];
                                } elseif ( isset( $row['sku'] ) ) {
                                    $order_sku = $row['sku'];
                                }
                                $sku                  = isset( $row['sku'] ) ? $row['sku'] : '';
                                $notes                = $row['notes'];
                                $min_order_qty        = (float) $row['min_order_qty'];
                                $order_qty            = (float) $row['manual_order_qty'];
                                $stock_on_hand        = (float) $row['stock_on_hand'];
                                $inbound_qty          = (float) $row['inbound_qty'];
                                $cost_gbp             = (float) $row['cost_gbp'];
                                $cost_supplier        = (float) $row['cost_supplier'];

                                $location             = isset( $row['location'] ) ? $row['location'] : '';
                                $brand                = isset( $row['brand'] ) ? $row['brand'] : '';
                                $suggested_order_qty  = isset( $row['suggested_order_qty'] ) ? (float) $row['suggested_order_qty'] : 0.0;
                                $cubic_cm             = isset( $row['cubic_cm'] ) ? (float) $row['cubic_cm'] : 0.0;
                                $line_cbm             = isset( $row['line_cbm'] ) ? (float) $row['line_cbm'] : 0.0;
                                $categories           = '';
                                if ( isset( $row['category_path'] ) ) {
                                    $categories = $row['category_path'];
                                } elseif ( isset( $row['category'] ) ) {
                                    $categories = $row['category'];
                                } elseif ( isset( $row['categories'] ) ) {
                                    $categories = $row['categories'];
                                }

                                $product              = wc_get_product( $product_id );

                                // Regular WooCommerce price: _regular_price (incl. VAT in your setup).
                                $regular_gross        = $product ? (float) $product->get_regular_price() : 0.0;
                                $regular_unit_price   = $regular_gross > 0 ? $regular_gross / 1.2 : 0.0;
                                $regular_line_price   = $regular_unit_price * $order_qty;

                                $line_total_gbp       = $order_qty * $cost_gbp;
                                $line_total_sup       = $order_qty * $cost_supplier;
                                $row_classes          = array( 'sop-preorder-row' );
                                if ( ! empty( $row['removed'] ) ) {
                                    $row_classes[] = 'sop-preorder-row-removed';
                                }
                                $row_key = $product_id;
                                $row_index = $sop_row_index;
                                $sku_sort_source = ( '' !== $order_sku ) ? $order_sku : $sku;
                                $sku_sort_value  = trim( preg_replace( '/\s+/', ' ', (string) $sku_sort_source ) );
                                $category_sort_value = trim( (string) $categories );
                                $carton_value = isset( $row['carton_no'] ) ? (string) $row['carton_no'] : '';
                                $carton_sort_value = isset( $row['carton_sort_min'] ) ? $row['carton_sort_min'] : null;
                                if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) && '' !== $carton_value ) {
                                    $carton_norm        = sop_normalize_carton_numbers_for_display( $carton_value );
                                    $carton_value       = isset( $carton_norm['value'] ) ? (string) $carton_norm['value'] : $carton_value;
                                    $carton_sort_value  = isset( $carton_norm['sort_min'] ) ? $carton_norm['sort_min'] : $carton_sort_value;
                                }
                                $carton_sort_attr = ( '' === $carton_value || null === $carton_sort_value ) ? '' : $carton_sort_value;

                                $image_id = 0;
                                if ( $product ) {
                                    $image_id = $product->get_image_id();
                                }
                                ?>
                                <tr data-index="<?php echo esc_attr( $index ); ?>" class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
                                    <input type="hidden" name="sop_line_product_id[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $product_id ); ?>" />
                                    <input type="hidden" name="sop_line_sku[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $sku ); ?>" />
                                    <input type="hidden" name="sop_line_image_id[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $image_id ); ?>" />
                                    <input type="hidden" name="sop_line_location[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $location ); ?>" />
                                    <input type="hidden" name="sop_line_cbm_per_unit[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( isset( $row['cbm_per_unit'] ) ? $row['cbm_per_unit'] : 0 ); ?>" />
                                    <input type="hidden" name="sop_line_cbm_total[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $line_cbm ); ?>" />
                                    <td class="sop-preorder-col-select">
                                        <input
                                            type="checkbox"
                                            class="sop-preorder-select-row"
                                            data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                        />
                                        <button type="button"
                                                class="button-link sop-preorder-restore-row"
                                                data-row-key="<?php echo esc_attr( $row_key ); ?>">
                                            <?php esc_html_e( 'Restore', 'sop' ); ?>
                                        </button>
                                    </td>
                                    <td class="column-image" data-column="image">
                                        <?php
                                        if ( $image_id ) {
                                            // Use the smaller WooCommerce gallery thumbnail (typically 100x100).
                                            echo wp_get_attachment_image(
                                                $image_id,
                                                'woocommerce_gallery_thumbnail'
                                            );
                                        }
                                        ?>
                                    </td>
                                    <td class="column-location" data-column="location">
                                        <?php echo esc_html( $location ); ?>
                                    </td>
                                    <td class="column-sku" data-column="sku" data-sort-key="sku" data-sort-value="<?php echo esc_attr( $sku_sort_value ); ?>" data-sort-text="<?php echo esc_attr( $sku_sort_value ); ?>">
                                        <input type="hidden" name="sop_product_id[]" value="<?php echo esc_attr( $product_id ); ?>" />
                                        <textarea
                                            name="sop_sku[]"
                                            rows="2"
                                            class="sop-preorder-sku small-text"
                                            title="<?php echo esc_attr( $order_sku ); ?>"
                                            <?php disabled( $is_locked ); ?>
                                        ><?php echo esc_textarea( $order_sku ); ?></textarea>
                                    </td>
                                    <td class="column-brand" data-column="brand">
                                        <?php echo esc_html( $brand ); ?>
                                    </td>
                                    <td class="column-category" data-column="category" data-sort-key="category" data-sort-value="<?php echo esc_attr( $category_sort_value ); ?>">
                                        <?php echo esc_html( $categories ); ?>
                                    </td>
                                    <td class="column-name" data-column="product">
                                        <?php echo esc_html( $name ); ?>
                                    </td>
                                    <td class="column-cost-supplier" data-column="cost_supplier">
                                        <input type="number" name="sop_line_cost_rmb[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $cost_supplier ); ?>" step="0.01" min="0" class="sop-cost-supplier-input sop-preorder-cost-rmb" <?php disabled( $is_locked ); ?> />
                                    </td>
                                    <?php if ( 'RMB' === $supplier_currency ) : ?>
                                        <?php
                                        $unit_cost_rmb  = $cost_supplier;
                                        $unit_cost_usd  = 0.0;
                                        if ( $unit_cost_rmb > 0 ) {
                                            if ( $rmb_to_usd_rate > 0 ) {
                                                $unit_cost_usd = $unit_cost_rmb * $rmb_to_usd_rate;
                                            } elseif ( function_exists( 'sop_convert_rmb_unit_cost_to_usd' ) ) {
                                                $unit_cost_usd = sop_convert_rmb_unit_cost_to_usd( $unit_cost_rmb );
                                            }
                                        }
                                        $usd_sort_value = ( $unit_cost_usd > 0 ) ? number_format( $unit_cost_usd, 6, '.', '' ) : '';
                                        ?>
                                        <td class="column-cost-usd" data-column="cost_usd" data-sort-key="unit_price_usd" data-sort-value="<?php echo esc_attr( $usd_sort_value ); ?>">
                                            <span class="sop-preorder-cost-usd">
                                                <?php
                                                if ( $unit_cost_usd > 0 ) {
                                                    echo esc_html( wc_format_decimal( $unit_cost_usd, 2 ) );
                                                } else {
                                                    echo '&ndash;';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="column-stock" data-column="stock">
                                        <?php echo esc_html( number_format_i18n( $stock_on_hand, 0 ) ); ?>
                                    </td>
                                    <td class="column-inbound" data-column="inbound">
                                        <?php echo esc_html( number_format_i18n( $inbound_qty, 0 ) ); ?>
                                    </td>
                                    <td class="column-min-order" data-column="min_order">
                                        <input type="number" name="sop_line_moq[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $min_order_qty ); ?>" step="1" min="0" class="sop-preorder-moq" <?php disabled( $is_locked ); ?> />
                                    </td>
                                    <td class="column-suggested" data-column="soq">
                                        <span class="sop-preorder-soq" data-soq="<?php echo esc_attr( $suggested_order_qty ); ?>">
                                            <?php echo esc_html( number_format_i18n( $suggested_order_qty, 0 ) ); ?>
                                        </span>
                                    </td>
                                    <td class="column-order-qty" data-column="order_qty" data-sort="order_qty">
                                        <input type="number" name="sop_line_qty[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $order_qty ); ?>" step="1" min="0" class="sop-order-qty-input sop-preorder-qty" <?php disabled( $is_locked ); ?> />
                                    </td>
                                    <td class="column-line-total-supplier" data-column="line_total">
                                        <span class="sop-line-total-gbp" data-cost-gbp="<?php echo esc_attr( $cost_gbp ); ?>" style="display:none;">
                                            <?php echo esc_html( number_format_i18n( $line_total_gbp, 2 ) ); ?>
                                        </span>
                                        <span class="sop-line-total-supplier" data-cost-supplier="<?php echo esc_attr( $cost_supplier ); ?>">
                                            <?php echo esc_html( number_format_i18n( $line_total_sup, 2 ) ); ?>
                                        </span>
                                    </td>
                                    <td class="column-cubic-item" data-column="cubic" data-cubic-cm="<?php echo esc_attr( $cubic_cm ); ?>">
                                        <?php echo esc_html( number_format_i18n( $cubic_cm, 0 ) ); ?>
                                    </td>
                                    <td class="column-line-cbm" data-column="line_cbm">
                                        <span class="sop-line-cbm-value">
                                            <?php echo esc_html( number_format_i18n( $line_cbm, 3 ) ); ?>
                                        </span>
                                    </td>
                                    <td class="column-regular-unit" data-column="regular_unit">
                                        <?php echo esc_html( number_format_i18n( $regular_unit_price, 2 ) ); ?>
                                    </td>
                                    <td class="column-regular-line" data-column="regular_line">
                                        <?php echo esc_html( number_format_i18n( $regular_line_price, 2 ) ); ?>
                                    </td>
                                    <td class="column-notes" data-column="notes">
                                        <div class="sop-preorder-notes-wrapper">
                                            <textarea
                                                name="sop_line_product_notes[<?php echo esc_attr( $row_index ); ?>]"
                                                rows="3"
                                                class="sop-preorder-notes sop-preorder-notes-product"
                                                style="width: 100%; resize: none;"
                                                title="<?php echo esc_attr( $notes ); ?>"
                                                data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                data-notes-type="product"
                                                <?php disabled( $is_locked ); ?>
                                            ><?php echo esc_textarea( $notes ); ?></textarea>

                                            <button type="button"
                                                    class="sop-preorder-notes-edit-icon"
                                                    data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                                    data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                    data-notes-type="product"
                                                    aria-label="<?php esc_attr_e( 'Edit product notes', 'sop' ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                        </div>

                                        <input
                                            type="hidden"
                                            name="sop_removed[]"
                                            value="<?php echo ! empty( $row['removed'] ) ? '1' : '0'; ?>"
                                            class="sop-preorder-removed-flag"
                                        />
                                    </td>
                                    <td class="column-order-notes" data-column="order_notes">
                                        <div class="sop-preorder-notes-wrapper">
                                            <textarea
                                                name="sop_line_order_notes[<?php echo esc_attr( $row_index ); ?>]"
                                                rows="3"
                                                class="sop-preorder-notes sop-preorder-notes-order"
                                                style="width: 100%; resize: none;"
                                                data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                data-notes-type="order"
                                                <?php disabled( $is_locked ); ?>
                                            ><?php echo isset( $row['order_notes'] ) ? esc_textarea( $row['order_notes'] ) : ''; ?></textarea>

                                            <button type="button"
                                                    class="sop-preorder-notes-edit-icon"
                                                    data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                                    data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                    data-notes-type="order"
                                                    aria-label="<?php esc_attr_e( 'Edit order notes', 'sop' ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="column-carton-no" data-column="carton_no" data-sort-key="carton_no" data-sort-value="<?php echo esc_attr( $carton_sort_attr ); ?>">
                                        <input
                                            type="text"
                                            name="sop_line_carton_no[<?php echo esc_attr( $row_index ); ?>]"
                                            value="<?php echo esc_attr( $carton_value ); ?>"
                                            class="sop-preorder-carton-input"
                                            data-original-value="<?php echo esc_attr( $carton_value ); ?>"
                                            style="width: 80px;"
                                            <?php disabled( $is_locked ); ?>
                                        />
                                    </td>
                                </tr>
                                <?php $sop_row_index++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="sop-preorder-notes-overlay" class="sop-preorder-notes-overlay" style="display:none;">
                <div class="sop-preorder-notes-overlay-backdrop"></div>
                <div class="sop-preorder-notes-overlay-inner">
                    <button type="button"
                            class="button-link sop-preorder-notes-overlay-close"
                            aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">
                        &times;
                    </button>
                    <h3 class="sop-preorder-notes-overlay-title">
                        <?php esc_html_e( 'Product notes', 'sop' ); ?>
                    </h3>
                    <p class="sop-preorder-notes-overlay-product"></p>
                    <textarea class="sop-preorder-notes-overlay-textarea" rows="8"></textarea>
                    <p>
                        <button type="button"
                                class="button button-primary sop-preorder-notes-overlay-save">
                            <?php esc_html_e( 'Save notes', 'sop' ); ?>
                        </button>
                    </p>
                </div>
            </div>

            <div class="sop-preorder-actions">
                <?php if ( ! $is_locked ) : ?>
                    <button type="submit" name="sop_save_sheet" value="1" class="button button-primary">
                        <?php
                        echo ( $current_sheet_id > 0 )
                            ? esc_html__( 'Update sheet', 'sop' )
                            : esc_html__( 'Save sheet', 'sop' );
                        ?>
                    </button>
                    <button type="submit" name="sop_lock_sheet" value="1" class="button button-secondary sop-lock-button">
                        <?php esc_html_e( 'Lock Sheet', 'sop' ); ?>
                    </button>
                <?php else : ?>
                    <button type="submit" name="sop_unlock_sheet" value="1" class="button button-secondary sop-unlock-button">
                        <?php esc_html_e( 'Unlock Sheet', 'sop' ); ?></button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <style>
        .sop-preorder-wrap {
            max-width: 100%;
        }

        .sop-preorder-header {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 12px;
        }

        .sop-preorder-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .sop-preorder-card-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            justify-content: space-between;
        }

        .sop-preorder-top-left,
        .sop-preorder-top-right,
        .sop-preorder-middle-top,
        .sop-preorder-middle-bottom,
        .sop-preorder-bottom-left,
        .sop-preorder-bottom-middle,
        .sop-preorder-bottom-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .sop-preorder-top-left {
            gap: 16px;
        }

        .sop-preorder-top-right {
            margin-left: auto;
            gap: 12px;
        }

        .sop-preorder-middle-top,
        .sop-preorder-middle-bottom,
        .sop-preorder-bottom-row {
            width: 100%;
        }

        .sop-preorder-bottom-right {
            margin-left: auto;
        }

        /* Columns dropdown wrapper */
        .sop-preorder-columns {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* The popover itself – single column with scroll */
        .sop-preorder-columns-popover {
            position: absolute;
            top: 100%;
            right: 0;
            left: auto;
            margin-top: 4px;
            background: #fff;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            padding: 4px 0;
            z-index: 1000;
            max-height: 260px;
            overflow-y: auto;
            min-width: 220px;
            display: none;
        }

        .sop-preorder-columns-popover.is-open {
            display: block;
        }

        /* The inner list of options */
        .sop-preorder-columns-list {
            list-style: none;
            margin: 0;
            padding: 0 8px;
        }

        /* Each row = one checkbox + label on a single line */
        .sop-preorder-columns-list li {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 2px 0;
            white-space: nowrap;
        }

        .sop-preorder-columns-list input[type="checkbox"] {
            margin: 0;
        }

        .sop-preorder-sku-search {
            position: relative;
            display: inline-flex;
            align-items: center;
            max-width: 260px;
            width: 100%;
        }

        .sop-preorder-sku-search .sop-preorder-sku-input {
            width: 100%;
            padding-right: 28px;
            padding-left: 0.6rem;
            box-sizing: border-box;
        }

        .sop-preorder-sku-search .sop-preorder-sku-input::placeholder {
            color: #9ca3af;
        }

        .sop-preorder-sku-search .sop-preorder-sku-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            line-height: 1;
            width: 18px;
            height: 18px;
            color: #000;
            pointer-events: none;
        }

        .sop-preorder-header select[name="sop_supplier_id"] {
            min-width: 240px;
        }

        .sop-preorder-top-row,
        .sop-preorder-middle-top,
        .sop-preorder-middle-bottom,
        .sop-preorder-bottom-row {
            justify-content: space-between;
            gap: 24px;
        }

        .sop-preorder-top-left,
        .sop-preorder-top-right {
            gap: 16px;
        }

        .sop-preorder-filter-sku {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .sop-preorder-card__row--container-top {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            width: 100%;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sop-preorder-container-item--button {
            justify-content: flex-end;
        }

        .sop-preorder-filter-sku-field {
            display: inline-flex;
            align-items: center;
        }

        .sop-preorder-filter-sku-field .regular-text {
            width: 170px;
            max-width: 220px;
            margin-right: 0;
        }

        .sop-preorder-summary-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 0;
            background: transparent;
            border: 0;
            border-radius: 0;
            margin: 0;
        }

        .sop-preorder-totals span + span {
            margin-left: 16px;
        }

        .sop-preorder-fill {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 260px;
            justify-content: flex-end;
            flex: 1 0 auto;
        }

        .sop-cbm-bar-wrapper {
            position: relative;
            flex: 1;
            height: 10px;
            background: #e5e5e5;
            border-radius: 999px;
            overflow: hidden;
        }

        .sop-cbm-bar {
            height: 100%;
            background: #46b450;
            width: 0;
            transition: width 0.25s ease;
        }

        .sop-cbm-label {
            min-width: 50px;
            text-align: right;
        }

        .sop-lock-status {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .sop-locked {
            background: #d63638;
            color: #fff;
        }

        .sop-unlocked {
            background: #46b450;
            color: #fff;
        }

        .sop-preorder-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            margin-bottom: 8px;
            padding: 0;
            border: 0;
            background-color: transparent;
        }

        .sop-preorder-table-toolbar {
            width: 100%;
        }

        .sop-preorder-toolbar-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .sop-preorder-toolbar-columns {
            margin-left: auto;
            text-align: right;
        }

        .sop-round-step-label {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-right: 0.5rem;
        }

        .sop-round-step {
            min-width: 60px;
        }

        .sop-row-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding-left: 1rem;
            border-left: 1px solid #e2e4e7;
        }

        .sop-preorder-columns-label {
            font-weight: 500;
        }

        .sop-preorder-table-wrapper {
            max-height: 90vh;
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid #ccd0d4;
        }

        .sop-preorder-table thead th {
            position: sticky;
            top: 0;
            background: #f1f1f1;
            z-index: 2;
            cursor: pointer;
            white-space: normal;
            word-break: normal;
            overflow-wrap: break-word;
            padding-right: 14px;
        }

        /* Pre-Order Sheet header column dividers */
        .sop-preorder-table thead th + th::before {
            content: '';
            position: absolute;
            left: 0;
            top: 6px;
            bottom: 6px;
            width: 1px;
            background-color: #e3e3e3;
            pointer-events: none;
        }

        .sop-preorder-table th .sop-preorder-header-label--wrap-2 {
            display: inline-block;
            max-width: 100%;
            white-space: normal;
            word-break: normal;
            line-height: 1.2;
            max-height: calc(1.2em * 2);
            overflow: hidden;
        }

        .sop-preorder-table tbody td {
            vertical-align: top;
        }

        .sop-preorder-table .column-image {
            width: 80px;
            text-align: center;
        }

        .sop-preorder-table td.column-image {
            padding: 1px;
        }

        .sop-preorder-table .column-image img {
            height: 60px !important;
            width: auto;
            max-height: 60px;
            max-width: 60px;
            object-fit: contain;
        }

        .sop-preorder-table td.column-image img.attachment-woocommerce_gallery_thumbnail {
            width: 78px !important;
            height: 78px !important;
            max-width: none;
            max-height: none;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .sop-preorder-table th.column-cost-usd,
        .sop-preorder-table td.column-cost-usd {
            width: 90px;
            min-width: 90px;
        }

        .sop-preorder-table th.column-cubic-item,
        .sop-preorder-table td.column-cubic-item {
            width: 80px;
            min-width: 80px;
        }

        .sop-preorder-table {
            table-layout: auto;
            width: auto;
            min-width: 1600px;
        }

        .sop-preorder-table .column-location {
            width: 90px;
            white-space: nowrap;
        }

        .sop-preorder-table .column-name {
            min-width: 35ch;
            max-width: 35ch;
            white-space: normal;
            word-wrap: break-word;
            word-break: break-word;
        }

        .sop-preorder-table .column-sku {
            width: 120px;
            white-space: nowrap;
        }

        .sop-preorder-table td.column-sku textarea.sop-preorder-sku {
            width: 12ch;
            min-width: 12ch;
            max-width: 12ch;
            min-height: 3.2em;
            resize: vertical;
            padding-top: 2px;
            padding-bottom: 2px;
            box-sizing: border-box;
            vertical-align: top;
        }

        .sop-preorder-table th.column-order-qty,
        .sop-preorder-table td.column-order-qty {
            width: 90px;
        }

        /* Qty and MOQ: ~8 characters, fixed so they don't squash */
        .sop-preorder-table .sop-order-qty-input,
        .sop-preorder-table .sop-preorder-moq-input,
        .sop-preorder-table .column-min-order input {
            width: 8ch;
            min-width: 8ch;
            max-width: 8ch;
        }

        /* Cost: ~11 characters to show decimal + extra digits, fixed width */
        .sop-preorder-table .sop-cost-supplier-input,
        .sop-preorder-table .column-cost-supplier input {
            width: 11ch;
            min-width: 11ch;
            max-width: 11ch;
        }

        .sop-preorder-table input[type="text"],
        .sop-preorder-table input[type="number"],
        .sop-preorder-table textarea {
            font-size: inherit;
        }


        .sop-preorder-table .column-notes textarea {
            width: 100%;
            min-height: 3em;
            box-sizing: border-box;
        }

        .sop-preorder-table input[type="number"],
        .sop-preorder-table input[type="text"] {
            width: 100%;
        }

        .sop-preorder-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem 1rem;
            margin-top: 1rem;
        }

        .sop-preorder-actions-left,
        .sop-preorder-actions-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .sop-preorder-actions-right {
            margin-left: auto;
        }

        .sop-preorder-table th[data-sort] {
            position: sticky;
        }

        .sop-preorder-table th[data-sort]::after {
            content: '\25B2';
            opacity: 0.3;
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 0;
            font-size: 10px;
        }

        .sop-preorder-table th[data-sort].sorted-asc::after {
            content: '\25B2';
            opacity: 1;
        }

        .sop-preorder-table th[data-sort].sorted-desc::after {
            content: '\25BC';
            opacity: 1;
        }

        .sop-preorder-table input.sop-preorder-carton-input {
            width: auto;
            min-width: 90px;
        }

        .sop-preorder-carton-tooltip {
            position: absolute;
            background: #23282d;
            color: #fff;
            padding: 6px 8px;
            border-radius: 3px;
            font-size: 12px;
            line-height: 1.4;
            max-width: 220px;
            white-space: normal;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
        }

        .sop-preorder-table [data-column] {
            transition: opacity 0.15s ease;
        }

        .sop-preorder-table .column-line-total-supplier {
            white-space: nowrap;
        }

        .sop-preorder-col-select {
            width: 40px;
            text-align: center;
        }

        .sop-preorder-table th.sop-preorder-col-select,
        .sop-preorder-table td.sop-preorder-col-select {
            width: 40px;
        }

        .sop-preorder-table th.sop-preorder-col-select input[type="checkbox"] {
            margin-left: 0;
            margin-right: 0;
        }

        .sop-preorder-table td.sop-preorder-col-select input[type="checkbox"] {
            margin-left: 0;
            margin-right: 0;
        }

        .sop-preorder-row-removed {
            opacity: 0.6;
        }

        .sop-preorder-col-select .sop-preorder-restore-row {
            display: none;
            margin-top: 2px;
        }

        .sop-preorder-row-removed .sop-preorder-col-select .sop-preorder-restore-row {
            display: block;
            pointer-events: auto;
            opacity: 1;
        }

        .sop-preorder-table th.column-notes,
        .sop-preorder-table td.column-notes,
        .sop-preorder-table th.column-order-notes,
        .sop-preorder-table td.column-order-notes {
            min-width: 40ch;
        }

        .sop-preorder-notes-wrapper {
            position: relative;
        }

        .sop-preorder-notes-edit-icon {
            position: absolute;
            top: 4px;
            right: 4px;
            padding: 0;
            border: none;
            background: transparent;
            cursor: pointer;
        }

        .sop-preorder-notes-edit-icon .dashicons {
            font-size: 16px;
            line-height: 1;
        }

        .sop-preorder-notes-overlay {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: none;
        }

        .sop-preorder-notes-overlay-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
        }

        .sop-preorder-notes-overlay-inner {
            position: absolute;
            top: 10%;
            left: 50%;
            transform: translateX(-50%);
            max-width: 700px;
            width: 90%;
            background: #fff;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .sop-preorder-notes-overlay-textarea {
            width: 100%;
            min-height: 180px;
            resize: vertical;
            box-sizing: border-box;
        }
    </style>

    <script>
        jQuery(function($) {
            var $table = $('.sop-preorder-table');
            var containerCbm = <?php echo json_encode( $effective_cbm ); ?>;
            var $rowCheckboxes       = $table.find('.sop-preorder-select-row');
            var $selectAllCheckbox   = $('#sop-preorder-select-all');
            var $removeSelectedBtn   = $('#sop-preorder-remove-selected');
            var $showRemovedCheckbox = $('#sop-preorder-show-removed');
            var $notesOverlay        = $('#sop-preorder-notes-overlay');
            var $notesOverlayInner   = $notesOverlay.find('.sop-preorder-notes-overlay-inner');
            var $notesOverlayTitle   = $notesOverlay.find('.sop-preorder-notes-overlay-title');
            var $notesOverlayProduct = $notesOverlay.find('.sop-preorder-notes-overlay-product');
            var $notesOverlayTextarea = $notesOverlay.find('.sop-preorder-notes-overlay-textarea');
            var currentNotesTextarea = null;
            var currentNotesType     = 'product';
            var currentNotesRowIndex = null;
            var lastClickedIndex     = null;
            var hasUnsavedChanges    = false;
            var $sheetForm           = $('#sop-preorder-sheet-form');
            var $columnsWrapper      = $('.sop-preorder-columns');
            var $columnsToggleButton = $columnsWrapper.find('.sop-preorder-columns-toggle');
            var $columnsPanel        = $columnsWrapper.find('.sop-preorder-columns-popover');
            var $columnCheckboxes    = $columnsPanel.find('input[type="checkbox"]');

            function sopPreorderApplyColumnVisibility() {
                $columnCheckboxes.each(function() {
                    var columnKey = $(this).data('column');
                    if ( ! columnKey ) {
                        return;
                    }
                    var show = $(this).is(':checked');
                    $table.find('[data-column="' + columnKey + '"]').toggle(show);
                });
            }

            // Any text/number input or textarea inside the table is considered an edit.
            $table.on('change input', 'input[type="text"], input[type="number"], textarea', function() {
                hasUnsavedChanges = true;
            });

            $(document).on('change input', '.sop-preorder-notes-overlay textarea', function() {
                hasUnsavedChanges = true;
            });

            // Removing or restoring rows also creates unsaved changes.
            $( document ).on( 'click', '#sop-preorder-remove-selected', function() {
                hasUnsavedChanges = true;
            } );

            $table.on( 'click', '.sop-preorder-restore-row', function() {
                hasUnsavedChanges = true;
            } );

            // Clear unsaved flag on save.
            var $saveButton = $('[name="sop_preorder_save"]');
            if ( $saveButton.length ) {
                $saveButton.on('click', function() {
                    hasUnsavedChanges = false;
                });
            }

            // Intercept filter form submits if unsaved changes exist.
            var $filterForm = $('#sop-preorder-filter-form');
            if ( $filterForm.length ) {
                $filterForm.on('submit', function(e) {
                    if ( ! hasUnsavedChanges ) {
                        return;
                    }

                    var message = 'You have unsaved changes on this sheet. Changing the filter (supplier, container, allowance, etc.) will rebuild the sheet and those changes will be lost.\n\nClick Cancel to stay on this page, or OK to discard changes and continue.';
                    if ( ! window.confirm( message ) ) {
                        e.preventDefault();
                        return false;
                    }

                    hasUnsavedChanges = false;
                });
            }

            if ( $sheetForm.length ) {
                $sheetForm.on( 'submit', function() {
                    hasUnsavedChanges = false;
                } );
            }

            // Warn on browser navigation if unsaved changes exist.
            window.addEventListener('beforeunload', function(e) {
                if ( ! hasUnsavedChanges ) {
                    return;
                }

                e.preventDefault();
                e.returnValue = '';
            });

            // Columns dropdown: open/close and update count.
            function sopPreorderUpdateColumnsToggleLabel() {
                var selectedCount = $columnCheckboxes.filter(':checked').length;
                var labelText = selectedCount + ' columns selected';
                if ( $columnsToggleButton.length ) {
                    $columnsToggleButton.text( labelText );
                }
            }

            if ( $columnsWrapper.length && $columnsToggleButton.length && $columnsPanel.length ) {
                sopPreorderUpdateColumnsToggleLabel();
                sopPreorderApplyColumnVisibility();

                $columnsToggleButton.on( 'click', function( e ) {
                    e.preventDefault();
                    var isOpen = $columnsPanel.hasClass( 'is-open' );
                    var height = $columnsToggleButton.outerHeight();
                    $columnsPanel.css( {
                        top: height + 8,
                        left: 0
                    } );
                    $columnsPanel.toggleClass( 'is-open', ! isOpen );
                    $columnsToggleButton.attr( 'aria-expanded', ! isOpen );
                } );

                $columnCheckboxes.on( 'change', function() {
                    sopPreorderUpdateColumnsToggleLabel();
                    sopPreorderApplyColumnVisibility();
                } );

                $( document ).on( 'click', function( e ) {
                    if ( ! $( e.target ).closest( '.sop-preorder-columns-popover, .sop-preorder-columns-toggle' ).length ) {
                        if ( $columnsPanel.hasClass( 'is-open' ) ) {
                            $columnsPanel.removeClass( 'is-open' );
                            $columnsToggleButton.attr( 'aria-expanded', 'false' );
                        }
                    }
                } );
            }

            function sopPreorderGetRowFromChild( el ) {
                var $el = $( el );
                return $el.closest( 'tr.sop-preorder-row' );
            }

            function sopPreorderGetSelectedRows() {
                var rows = [];
                $rowCheckboxes.each( function( index, checkbox ) {
                    if ( ! checkbox.checked ) {
                        return;
                    }
                    var $row = sopPreorderGetRowFromChild( checkbox );
                    if ( $row.length && ! $row.hasClass( 'sop-preorder-row-removed' ) ) {
                        rows.push( $row );
                    }
                } );
                return rows;
            }

            function sopPreorderOpenNotesOverlayForRow( $row, notesType ) {
                if ( ! $row || ! $row.length ) {
                    return;
                }

                var type = notesType || 'product';
                var $textarea = 'order' === type
                    ? $row.find( '.sop-preorder-notes-order' )
                    : $row.find( '.sop-preorder-notes-product' );
                if ( ! $textarea.length ) {
                    return;
                }

                currentNotesTextarea = $textarea.get( 0 );
                currentNotesType     = type;
                currentNotesRowIndex = $textarea.data( 'row-index' );

                var productText = $.trim( $row.find( 'td.column-name' ).text() || '' );
                $notesOverlayProduct.text( productText );

                if ( 'order' === type ) {
                    $notesOverlayTitle.text( '<?php echo esc_js( __( 'Order notes', 'sop' ) ); ?>' );
                } else {
                    $notesOverlayTitle.text( '<?php echo esc_js( __( 'Product notes', 'sop' ) ); ?>' );
                }

                $notesOverlayTextarea.val( currentNotesTextarea.value );

                $notesOverlay.show();
                $notesOverlayTextarea.focus();
            }

            function sopPreorderCloseNotesOverlay( saveChanges ) {
                if ( saveChanges && currentNotesTextarea && $notesOverlayTextarea.length ) {
                    currentNotesTextarea.value = $notesOverlayTextarea.val();
                }

                currentNotesTextarea = null;
                currentNotesType     = 'product';
                currentNotesRowIndex = null;
                $notesOverlay.hide();
            }

            // Selection: select-all and shift-click range.
            if ( $selectAllCheckbox.length && $rowCheckboxes.length ) {
                $selectAllCheckbox.on( 'change', function() {
                    var checked = this.checked;
                    $rowCheckboxes.each( function() {
                        this.checked = checked;
                    } );
                } );

                $rowCheckboxes.each( function( index ) {
                    $( this ).on( 'click', function( event ) {
                        if ( event.shiftKey && lastClickedIndex !== null ) {
                            var start   = Math.min( lastClickedIndex, index );
                            var end     = Math.max( lastClickedIndex, index );
                            var checked = this.checked;

                            $rowCheckboxes.each( function( i ) {
                                if ( i >= start && i <= end ) {
                                    this.checked = checked;
                                }
                            } );
                        }
                        lastClickedIndex = index;
                    } );
                } );
            }

            // Remove selected rows.
            if ( $removeSelectedBtn.length && $rowCheckboxes.length ) {
                $removeSelectedBtn.on( 'click', function( e ) {
                    e.preventDefault();

                    $rowCheckboxes.each( function() {
                        if ( ! this.checked ) {
                            return;
                        }
                        var $row = sopPreorderGetRowFromChild( this );
                        if ( ! $row.length ) {
                            return;
                        }

                        var $removedInput = $row.find( '.sop-preorder-removed-flag' );
                        if ( $removedInput.length ) {
                            $removedInput.val( '1' );
                        }

                        var $qtyInput = $row.find( '.sop-order-qty-input' );
                        if ( $qtyInput.length ) {
                            var currentVal = $qtyInput.val();
                            if ( currentVal && ! $row.data( 'sopLastQty' ) ) {
                                $row.data( 'sopLastQty', currentVal );
                            }
                            $qtyInput.val( '0' );
                        }

                        $row.addClass( 'sop-preorder-row-removed' );

                        if ( ! $showRemovedCheckbox.length || ! $showRemovedCheckbox.prop( 'checked' ) ) {
                            $row.hide();
                        }
                    } );

                    recalcTotals();
                } );
            }

            // Show removed toggle.
            if ( $showRemovedCheckbox.length ) {
                if ( ! $showRemovedCheckbox.prop( 'checked' ) ) {
                    $table.find( 'tr.sop-preorder-row-removed' ).hide();
                }

                $showRemovedCheckbox.on( 'change', function() {
                    var show = $( this ).prop( 'checked' );
                    var $removedRows = $table.find( 'tr.sop-preorder-row-removed' );

                    if ( show ) {
                        $removedRows.show();
                    } else {
                        $removedRows.hide();
                    }
                } );
            }

            // Open overlay for notes.
            $table.on( 'click', '.sop-preorder-notes, .sop-preorder-notes-edit-icon', function( e ) {
                e.preventDefault();
                var $row = $( this ).closest( 'tr.sop-preorder-row' );
                var notesType = $( this ).data( 'notes-type' ) || ( $( this ).hasClass( 'sop-preorder-notes-order' ) ? 'order' : 'product' );
                sopPreorderOpenNotesOverlayForRow( $row, notesType );
            } );

            // Save notes from overlay.
            $notesOverlay.on( 'click', '.sop-preorder-notes-overlay-save', function( e ) {
                e.preventDefault();
                sopPreorderCloseNotesOverlay( true );
            } );

            // Close overlay without saving.
            $notesOverlay.on( 'click', '.sop-preorder-notes-overlay-close, .sop-preorder-notes-overlay-backdrop', function( e ) {
                e.preventDefault();
                sopPreorderCloseNotesOverlay( false );
            } );

            // Restore row.
            $table.on( 'click', '.sop-preorder-restore-row', function( e ) {
                e.preventDefault();

                var $row = sopPreorderGetRowFromChild( this );
                if ( ! $row.length ) {
                    return;
                }

                var $removedInput = $row.find( '.sop-preorder-removed-flag' );
                if ( $removedInput.length ) {
                    $removedInput.val( '0' );
                }

                $row.removeClass( 'sop-preorder-row-removed' ).show();

                var $qtyInput = $row.find( '.sop-order-qty-input' );
                var lastQty   = $row.data( 'sopLastQty' );
                if ( $qtyInput.length && typeof lastQty !== 'undefined' && lastQty !== null && lastQty !== '' ) {
                    $qtyInput.val( lastQty );
                }

                hasUnsavedChanges = true;

                recalcTotals();
            } );

            function recalcTotals() {
                var totalUnits = 0;
                var totalSkus = 0;
                var totalCostGbp = 0;
                var totalCostSupplier = 0;
                var totalCbm = 0;

                $table.find('tbody tr').each(function() {
                    var $row = $(this);
                    var removedFlag = $row.find('.sop-preorder-removed-flag').val();
                    if ( removedFlag === '1' ) {
                        return;
                    }

                    var qty = parseFloat($row.find('.sop-order-qty-input').val()) || 0;
                    if ( qty <= 0 ) {
                        return;
                    }

                    var costGbp = parseFloat($row.find('.sop-line-total-gbp').data('cost-gbp')) || 0;
                    var costSupplier = parseFloat($row.find('.sop-line-total-supplier').data('cost-supplier')) || 0;
                    var cubicCm = parseFloat($row.find('.column-cubic-item').data('cubic-cm')) || 0;

                    var lineTotalGbp = qty * costGbp;
                    var lineTotalSupplier = qty * costSupplier;

                    $row.find('.sop-line-total-gbp').text(lineTotalGbp.toFixed(2));
                    $row.find('.sop-line-total-supplier').text(lineTotalSupplier.toFixed(2));

                    var lineCbm = 0;
                    if ( cubicCm > 0 ) {
                        lineCbm = ( cubicCm * qty ) / 1000000;
                    }

                    var $lineCbmSpan = $row.find('.column-line-cbm .sop-line-cbm-value');
                    if ( $lineCbmSpan.length ) {
                        $lineCbmSpan.text( lineCbm.toFixed(3) );
                    }

                    totalUnits += qty;
                    totalCostGbp += lineTotalGbp;
                    totalCostSupplier += lineTotalSupplier;
                    totalCbm += lineCbm;
                    totalSkus += 1;
                });

                $('#sop-total-units').text(Math.round(totalUnits));
                $('#sop-total-skus').text(totalSkus);
                $('#sop-total-cost-gbp').text(wc_price_format(totalCostGbp));
                $('#sop-total-cost-supplier').text(totalCostSupplier.toFixed(2));

                var usedCbmPercent = 0;
                if ( containerCbm > 0 && totalCbm > 0 ) {
                    usedCbmPercent = ( totalCbm / containerCbm ) * 100;
                }

                // Clamp bar width between 0 and 100, but show the raw percentage in the label.
                var usedCbmBar = usedCbmPercent;
                if ( usedCbmBar < 0 ) {
                    usedCbmBar = 0;
                } else if ( usedCbmBar > 100 ) {
                    usedCbmBar = 100;
                }

                $('.sop-cbm-bar').css('width', usedCbmBar + '%');
                $('#sop-cbm-label').text(usedCbmPercent.toFixed(1) + '%');
            }

            function wc_price_format(amount) {
                return '<?php echo esc_js( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?> ' + amount.toFixed(2);
            }

            function sopPreorderParseNumber(val) {
                var num = parseFloat(String(val).replace(/,/g, ''));
                return isNaN(num) ? 0 : num;
            }

            function sopPreorderAdjustCartonWidth(inputEl) {
                if ( ! inputEl ) {
                    return;
                }
                var len = inputEl.value.length || 1;
                var px = Math.min(260, Math.max(90, 10 + len * 8));
                inputEl.style.width = px + 'px';
            }

            function sopPreorderShowCartonTooltip(inputEl, message) {
                if ( ! inputEl || ! message ) {
                    return;
                }
                var rect = inputEl.getBoundingClientRect();
                var tooltip = document.createElement('div');
                tooltip.className = 'sop-preorder-carton-tooltip';
                tooltip.textContent = message;
                document.body.appendChild(tooltip);

                var top = window.scrollY + rect.top - tooltip.offsetHeight - 6;
                var left = window.scrollX + rect.left;
                tooltip.style.top = top + 'px';
                tooltip.style.left = left + 'px';

                // Clamp tooltip horizontally within the current viewport so it doesn't go off-screen.
                (function() {
                    var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft || 0;
                    var viewportWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || 0;
                    var tooltipWidth = tooltip.offsetWidth || 0;
                    var margin = 8;

                    var currentLeft = parseFloat(tooltip.style.left) || 0;
                    var minLeft = scrollLeft + margin;
                    var maxLeft = scrollLeft + viewportWidth - tooltipWidth - margin;

                    // If the viewport is very narrow, avoid inverting the range.
                    if ( maxLeft < minLeft ) {
                        maxLeft = minLeft;
                    }

                    if ( currentLeft < minLeft ) {
                        currentLeft = minLeft;
                    } else if ( currentLeft > maxLeft ) {
                        currentLeft = maxLeft;
                    }

                    tooltip.style.left = currentLeft + 'px';
                })();

                requestAnimationFrame(function() {
                    tooltip.style.opacity = '1';
                });

                setTimeout(function() {
                    tooltip.style.opacity = '0';
                    setTimeout(function() {
                        if ( tooltip && tooltip.parentNode ) {
                            tooltip.parentNode.removeChild(tooltip);
                        }
                    }, 200);
                }, 5000);
            }

            function sopPreorderShowCartonInvalidRangeTooltip(inputEl, silent) {
                if ( silent || ! inputEl ) {
                    return;
                }
                sopPreorderShowCartonTooltip(
                    inputEl,
                    '<?php echo esc_js( __( 'Invalid range, use e.g. 1-5,8,11-13', 'sop' ) ); ?>'
                );
            }

            function sopPreorderNormalizeCartonValue(inputEl, options) {
                if ( ! inputEl ) {
                    return null;
                }

                var opts = options || {};
                var silent = !!opts.silent;
                var raw = inputEl.value || '';

                var cleaned = raw.toString().replace(/[^0-9,\-]+/gi, '');
                cleaned = cleaned.replace(/,+/g, ',').replace(/-+/g, '-');
                cleaned = cleaned.replace(/^,|,$/g, '');

                var tokens = cleaned.split(',').filter(Boolean);
                var parsed = [];

                tokens.forEach(function(token) {
                    var singleMatch = token.match(/^(\d+)$/);
                    if ( singleMatch ) {
                        var num = parseInt(singleMatch[1], 10);
                        parsed.push({ start: num, end: num, display: String(num) });
                        return;
                    }

                    var rangeMatch = token.match(/^(\d+)-(\d+)$/);
                    if ( rangeMatch ) {
                        var start = parseInt(rangeMatch[1], 10);
                        var end = parseInt(rangeMatch[2], 10);
                        if ( start > end ) {
                            var tmp = start;
                            start = end;
                            end = tmp;
                        }
                        parsed.push({ start: start, end: end, display: start + '-' + end });
                    }
                });

                if ( parsed.length ) {
                    parsed.sort(function(a, b) {
                        return a.start - b.start;
                    });
                }

                var rebuiltTokens = parsed.map(function(item) {
                    return item.display;
                });
                var rebuilt = rebuiltTokens.join(',');
                var sortMin = parsed.length ? parsed[0].start : null;

                var row = inputEl.closest('tr.sop-preorder-row');
                var td = inputEl.closest('td');

                if ( parsed.length === 0 ) {
                    inputEl.value = '';
                    if ( td ) {
                        td.dataset.sortValue = '';
                    }
                    if ( row ) {
                        row.dataset.sortCartonNo = '';
                    }
                    sopPreorderShowCartonInvalidRangeTooltip(inputEl, silent);
                    return null;
                }

                var strippedLetters = raw !== cleaned;
                if ( rebuilt !== cleaned || strippedLetters ) {
                    inputEl.value = rebuilt;
                    if ( strippedLetters ) {
                        sopPreorderShowCartonInvalidRangeTooltip(inputEl, silent);
                    }
                }

                if ( td ) {
                    td.dataset.sortValue = ( sortMin !== null ) ? sortMin : '';
                }
                if ( row ) {
                    row.dataset.sortCartonNo = ( sortMin !== null ) ? sortMin : '';
                }

                return sortMin;
            }

            function sopPreorderGetSortValue($row, sortKey, columnIndex) {
                var $cell = $row.find('td[data-sort-key="' + sortKey + '"]').first();

                if ( ! $cell.length && typeof columnIndex === 'number' ) {
                    $cell = $row.children('td').eq(columnIndex);
                }

                if ( $cell.length ) {
                    var dataValue = $cell.data('sortValue');
                    if ( typeof dataValue !== 'undefined' ) {
                        return dataValue;
                    }

                    var dataNum = $cell.data('sortNum');
                    if ( typeof dataNum !== 'undefined' ) {
                        return dataNum;
                    }

                    var dataText = $cell.data('sortText');
                    if ( typeof dataText !== 'undefined' ) {
                        return dataText;
                    }
                }

                switch ( sortKey ) {
                    case 'carton_no':
                        var rowNode = $row.get( 0 );
                        if ( rowNode && typeof rowNode.dataset.sortCartonNo !== 'undefined' ) {
                            return rowNode.dataset.sortCartonNo;
                        }
                        return $row.find('td[data-sort-key="carton_no"]').data('sortValue');
                    case 'sku':
                        return ($row.find('.column-sku textarea').val() || '').replace(/\s+/g, ' ').trim();
                    case 'name':
                        return $row.find('.column-name').text() || '';
                    case 'location':
                        return $row.find('.column-location').text() || '';
                    case 'brand':
                        return $row.find('.column-brand').text() || '';
                    case 'category':
                        return $row.find('.column-category').text() || '';
                    case 'notes':
                        return $row.find('.column-notes textarea').val() || '';
                    case 'order_notes':
                        return $row.find('.column-order-notes textarea').val() || '';
                    case 'cost':
                        return parseFloat($row.find('.column-cost-supplier input').val()) || 0;
                    case 'stock':
                        return sopPreorderParseNumber($row.find('.column-stock').text());
                    case 'inbound':
                        return sopPreorderParseNumber($row.find('.column-inbound').text());
                    case 'moq':
                        return parseFloat($row.find('.column-min-order input').val()) || 0;
                    case 'soq':
                        return sopPreorderParseNumber($row.find('.column-suggested').text());
                    case 'order_qty':
                        return parseFloat($row.find('.sop-order-qty-input').val()) || 0;
                    case 'total':
                        return sopPreorderParseNumber($row.find('.sop-line-total-supplier').text());
                    case 'cubic':
                        return sopPreorderParseNumber($row.find('.column-cubic-item').text());
                    case 'line_cbm':
                        return sopPreorderParseNumber($row.find('.column-line-cbm').text());
                    case 'price_ex':
                        return sopPreorderParseNumber($row.find('.column-regular-unit').text());
                    case 'line_ex':
                        return sopPreorderParseNumber($row.find('.column-regular-line').text());
                    case 'unit_price_usd':
                    case 'cost_usd':
                        return parseFloat($row.find('.column-cost-usd').data('sort-value')) || 0;
                    default:
                        return 0;
                }
            }

            var preorderWrapper = document.getElementById('sop-preorder-wrapper');
            var rmbToUsdRate = preorderWrapper ? parseFloat(preorderWrapper.getAttribute('data-rmb-to-usd-rate') || '0') : 0;

            if ( preorderWrapper ) {
                preorderWrapper.addEventListener('input', function( e ) {
                    var input = e.target.closest('.sop-preorder-cost-rmb');
                    if ( ! input || ! rmbToUsdRate ) {
                        return;
                    }

                    var row = input.closest('.sop-preorder-row');
                    if ( ! row ) {
                        return;
                    }

                    var rmbValue = parseFloat(String(input.value).replace(',', '.')) || 0;
                    var usdValue = rmbValue * rmbToUsdRate;

                    var usdDisplay = row.querySelector('.sop-preorder-cost-usd');
                    if ( usdDisplay ) {
                        usdDisplay.textContent = usdValue > 0 ? usdValue.toFixed(2) : '-';
                    }

                    var usdTd = usdDisplay ? usdDisplay.closest('td') : null;
                    if ( usdTd ) {
                        usdTd.dataset.sortValue = usdValue > 0 ? usdValue.toFixed(6) : '0';
                    }
                });
            }

            $table.on('input', '.sop-preorder-carton-input', function() {
                sopPreorderAdjustCartonWidth(this);
            });

            $table.on('blur change', '.sop-preorder-carton-input', function() {
                sopPreorderNormalizeCartonValue(this);
                sopPreorderAdjustCartonWidth(this);
            });

            $table.find('.sop-preorder-carton-input').each(function() {
                sopPreorderNormalizeCartonValue(this, { silent: true });
                sopPreorderAdjustCartonWidth(this);
            });

            $table.on('input', '.sop-order-qty-input, .sop-cost-supplier-input', function() {
                recalcTotals();
            });

            $('.sop-rounding-controls button[data-round-mode]').on('click', function(e) {
                e.preventDefault();

                var mode = $(this).data('round-mode'); // 'up' or 'down'
                var step = parseInt($('.sop-round-step').val(), 10) || 1;
                var selectedRows = sopPreorderGetSelectedRows();
                var $qtyInputs;

                if ( selectedRows.length > 0 ) {
                    $qtyInputs = $();
                    $.each( selectedRows, function( i, $row ) {
                        $qtyInputs = $qtyInputs.add( $row.find( '.sop-order-qty-input' ) );
                    } );
                } else {
                    $qtyInputs = $table.find( '.sop-order-qty-input' );
                }

                $qtyInputs.each(function() {
                    var val = parseFloat($(this).val()) || 0;
                    var rounded = val;

                    if (mode === 'up') {
                        rounded = Math.ceil(val / step) * step;
                    } else if (mode === 'down') {
                        rounded = Math.floor(val / step) * step;
                    }

                    if (rounded < 0) {
                        rounded = 0;
                    }

                    $(this).val(rounded);
                    hasUnsavedChanges = true;
                    $(this).trigger('change');
                });

                recalcTotals();
            });

            $('#sop-apply-soq-to-qty').on('click', function(e) {
                e.preventDefault();

                $table.find('tbody tr').each(function() {
                    var $row = $( this );

                    if ( $row.hasClass( 'sop-preorder-row-removed' ) ) {
                        return;
                    }

                    var $qtyInput = $row.find( 'input.sop-preorder-qty' );
                    var $soqEl    = $row.find( '.sop-preorder-soq' );
                    var $moqInput = $row.find( 'input.sop-preorder-moq' );

                    if ( ! $qtyInput.length || ! $soqEl.length ) {
                        return;
                    }

                    var qtyVal = parseFloat( $qtyInput.val() );
                    if ( isNaN( qtyVal ) ) {
                        qtyVal = 0;
                    }

                    if ( qtyVal !== 0 ) {
                        return;
                    }

                    var soqVal = parseFloat( $soqEl.data( 'soq' ) );
                    var moqVal = $moqInput.length ? parseFloat( $moqInput.val() ) : 0;

                    if ( isNaN( soqVal ) || soqVal < 0 ) {
                        soqVal = 0;
                    }
                    if ( isNaN( moqVal ) || moqVal < 0 ) {
                        moqVal = 0;
                    }

                    // If Suggested Order Qty is zero or less, skip this row (ignore MOQ).
                    if ( soqVal <= 0 ) {
                        return;
                    }

                    var targetQty = Math.max( soqVal, moqVal );
                    if ( targetQty > 0 ) {
                        targetQty = Math.ceil( targetQty );
                        $qtyInput.val( targetQty );
                        hasUnsavedChanges = true;
                        $qtyInput.trigger('change');
                    }
                });

                recalcTotals();
            });

            var sopNumericSortKeys = {
                carton_no: true,
                cost: true,
                stock: true,
                inbound: true,
                moq: true,
                soq: true,
                order_qty: true,
                total: true,
                cubic: true,
                line_cbm: true,
                price_ex: true,
                line_ex: true,
                unit_price_usd: true,
                cost_usd: true
            };

            $table.find('th[data-sort]').on('click', function() {
                var $th = $(this);
                var sortKey = $th.data('sort-key') || $th.data('sort');
                var isAsc = !$th.hasClass('sorted-asc');
                var columnIndex = $th.index();

                $table.find('th[data-sort]').removeClass('sorted-asc sorted-desc');
                $th.addClass(isAsc ? 'sorted-asc' : 'sorted-desc');

                var rows = $table.find('tbody tr').get();

                rows.sort(function(a, b) {
                    var rawA = sopPreorderGetSortValue($(a), sortKey, columnIndex);
                    var rawB = sopPreorderGetSortValue($(b), sortKey, columnIndex);

                    if ( sopNumericSortKeys[sortKey] ) {
                        if ( sortKey === 'carton_no' ) {
                            var numA = parseFloat(rawA);
                            var numB = parseFloat(rawB);
                            if ( ! isFinite(numA) ) {
                                numA = Number.POSITIVE_INFINITY;
                            }
                            if ( ! isFinite(numB) ) {
                                numB = Number.POSITIVE_INFINITY;
                            }
                            return isAsc ? numA - numB : numB - numA;
                        }

                        var numA = sopPreorderParseNumber(rawA);
                        var numB = sopPreorderParseNumber(rawB);
                        return isAsc ? numA - numB : numB - numA;
                    }

                    var textA = $.trim(String(rawA || '')).toLowerCase();
                    var textB = $.trim(String(rawB || '')).toLowerCase();

                    return isAsc ? textA.localeCompare(textB) : textB.localeCompare(textA);
                });

                $.each(rows, function(index, row) {
                    $table.find('tbody').append(row);
                });
            });

            recalcTotals();
        });
    </script>
    <?php
}

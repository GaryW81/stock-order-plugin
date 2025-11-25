<?php
/*** Stock Order Plugin - Phase 4.1 - Pre-Order Sheet UI (admin only) V10.14 *
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
        $ctx               = sop_preorder_resolve_supplier_params();
        $supplier_currency = $ctx['currency_code'];
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

    // Base CBM and floor area per container type using real internal dimensions.
    $base_cbm   = 0.0;
    $floor_area = 0.0;

    switch ( $container_selection ) {
        case '20ft':
            // 20ft GP: 5.90m x 2.35m x 2.39m ≈ 33.2 CBM.
            $base_cbm   = 33.2;
            $floor_area = 5.90 * 2.35;
            break;
        case '40ft':
            // 40ft GP: 12.03m x 2.35m x 2.39m ≈ 67.7 CBM.
            $base_cbm   = 67.7;
            $floor_area = 12.03 * 2.35;
            break;
        case '40ft_hc':
            // 40ft HQ: 12.03m x 2.35m x 2.69m ≈ 76.3 CBM.
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

    $lock_timestamp = $supplier ? sop_preorder_get_lock_timestamp( $supplier['id'] ) : 0;
    $is_locked      = $lock_timestamp > 0;

    $rows = [];
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

    $used_cbm = 0.0;
    if ( $effective_cbm > 0 && $total_cbm > 0 ) {
        $used_cbm = min( 100.0, ( $total_cbm / $effective_cbm ) * 100.0 );
    }

    $currency_symbol = '£';
    switch ( $supplier_currency ) {
        case 'RMB':
            $currency_symbol = '¥';
            break;
        case 'USD':
            $currency_symbol = '$';
            break;
        case 'EUR':
            $currency_symbol = '€';
            break;
        case 'GBP':
        default:
            $currency_symbol = '£';
            break;
    }
    ?>
    <div class="wrap sop-preorder-wrap">
        <h1><?php esc_html_e( 'Pre-Order Sheet', 'sop' ); ?></h1>

        <form method="get" action="">
            <input type="hidden" name="page" value="sop-preorder-sheet" />

            <div class="sop-preorder-controls">
                <label>
                    <?php esc_html_e( 'Supplier:', 'sop' ); ?>
                    <select name="sop_supplier_id">
                        <?php foreach ( $suppliers as $row ) : ?>
                            <option value="<?php echo esc_attr( $row['id'] ); ?>" <?php selected( (int) $row['id'], $selected_supplier_id ); ?>>
                                <?php echo esc_html( $row['name'] ); ?> (<?php echo esc_html( $row['currency_code'] ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <?php esc_html_e( 'Container:', 'sop' ); ?>
                    <select name="sop_container">
                        <option value=""><?php esc_html_e( 'None', 'sop' ); ?></option>
                        <option value="20ft" <?php selected( $container_selection, '20ft' ); ?>>20ft (33.2 CBM)</option>
                        <option value="40ft" <?php selected( $container_selection, '40ft' ); ?>>40ft (67.7 CBM)</option>
                        <option value="40ft_hc" <?php selected( $container_selection, '40ft_hc' ); ?>>40ft (76.3 CBM)</option>
                    </select>
                </label>

                <label class="sop-pallet-layer-label">
                    <input type="checkbox" name="sop_pallet_layer" value="1" <?php checked( $pallet_layer ); ?> />
                    <?php esc_html_e( '150mm pallet layer', 'sop' ); ?>
                </label>

                <label class="sop-allowance-label">
                    <?php esc_html_e( 'Allowance:', 'sop' ); ?>
                    <input type="number" name="sop_allowance" value="<?php echo esc_attr( $allowance ); ?>" step="1" min="-50" max="50" />
                    %
                </label>

                <div class="sop-preorder-filter-sku">
                    <label for="sop_sku_filter" class="screen-reader-text">
                        <?php esc_html_e( 'Search by SKU', 'sop' ); ?>
                    </label>
                    <div class="sop-preorder-filter-sku-field">
                        <input type="text"
                               id="sop_sku_filter"
                               name="sop_sku_filter"
                               value="<?php echo esc_attr( $sku_filter ); ?>"
                               placeholder="<?php esc_attr_e( 'Input SKU', 'sop' ); ?>"
                               class="regular-text" />
                        <button type="submit"
                                class="button sop-preorder-sku-search-button"
                                aria-label="<?php esc_attr_e( 'Search by SKU', 'sop' ); ?>">
                            <span class="dashicons dashicons-search"></span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="button button-secondary">
                    <?php esc_html_e( 'Update Filter', 'sop' ); ?>
                </button>
            </div>
        </form>

        <div class="sop-preorder-summary-bar">
            <div class="sop-summary-item">
                <strong><?php esc_html_e( 'Total Units', 'sop' ); ?>:</strong>
                <span id="sop-total-units"><?php echo esc_html( number_format_i18n( $total_units, 0 ) ); ?></span>
            </div>
            <div class="sop-summary-item">
                <strong><?php esc_html_e( 'Total SKUs', 'sop' ); ?>:</strong>
                <span id="sop-total-skus"><?php echo esc_html( number_format_i18n( $total_skus, 0 ) ); ?></span>
            </div>
            <div class="sop-summary-item">
                <strong><?php esc_html_e( 'Total Cost (GBP)', 'sop' ); ?>:</strong>
                <span id="sop-total-cost-gbp"><?php echo esc_html( wc_price( $total_cost_gbp ) ); ?></span>
            </div>
            <div class="sop-summary-item">
                <strong><?php printf( esc_html__( 'Total Cost (%s)', 'sop' ), esc_html( $supplier_currency ) ); ?>:</strong>
                <span id="sop-total-cost-supplier">
                    <?php echo esc_html( $currency_symbol . ' ' . number_format_i18n( $total_cost_supplier, 2 ) ); ?>
                </span>
            </div>
            <div class="sop-summary-item sop-summary-cbm">
                <strong><?php esc_html_e( 'Container Fill', 'sop' ); ?>:</strong>
                <div class="sop-cbm-bar-wrapper" title="<?php echo esc_attr( $used_cbm ); ?>%">
                    <div class="sop-cbm-bar" style="width: <?php echo esc_attr( $used_cbm ); ?>%;"></div>
                </div>
                <span class="sop-cbm-label" id="sop-cbm-label"><?php echo esc_html( number_format_i18n( $used_cbm, 1 ) ); ?>%</span>
            </div>
            <div class="sop-summary-item sop-summary-lock">
                <?php if ( $is_locked ) : ?>
                    <span class="sop-lock-status sop-locked"><?php esc_html_e( 'Sheet is LOCKED', 'sop' ); ?></span>
                <?php else : ?>
                    <span class="sop-lock-status sop-unlocked"><?php esc_html_e( 'Sheet is UNLOCKED', 'sop' ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field( 'sop_preorder_save', 'sop_preorder_nonce' ); ?>
            <input type="hidden" name="sop_supplier_id" value="<?php echo esc_attr( $selected_supplier_id ); ?>" />

            <div class="sop-preorder-table-toolbar">
                <div class="sop-rounding-controls">
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
                        <button type="button" class="button" id="sop-preorder-remove-selected"><?php esc_html_e( 'Remove selected', 'sop' ); ?></button>
                        <label for="sop-preorder-show-removed" style="margin-left: 10px;">
                            <input type="checkbox" id="sop-preorder-show-removed" />
                            <?php esc_html_e( 'Show removed rows', 'sop' ); ?>
                        </label>
                    <?php else : ?>
                        <span class="description"><?php esc_html_e( 'Rounding disabled for locked sheet.', 'sop' ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="sop-column-visibility">
                    <span><?php esc_html_e( 'Columns:', 'sop' ); ?></span>
                    <label><input type="checkbox" data-column="cost_supplier" checked /> <?php esc_html_e( 'Cost (Supplier)', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="min_order" checked /> <?php esc_html_e( 'Min Order', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="stock" checked /> <?php esc_html_e( 'Stock', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="inbound" checked /> <?php esc_html_e( 'Inbound', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="cubic" checked /> <?php esc_html_e( 'Cubic', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="line_cbm" checked /> <?php esc_html_e( 'Line CBM', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="regular_unit" checked /> <?php esc_html_e( 'Unit (ex VAT)', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="regular_line" checked /> <?php esc_html_e( 'Line (ex VAT)', 'sop' ); ?></label>
                    <label><input type="checkbox" data-column="notes" checked /> <?php esc_html_e( 'Notes', 'sop' ); ?></label>
                </div>
            </div>

            <div class="sop-preorder-table-wrapper">
                <table class="wp-list-table widefat fixed striped sop-preorder-table">
                    <thead>
                        <tr>
                            <th class="sop-preorder-col-select">
                                <input type="checkbox" id="sop-preorder-select-all" />
                            </th>
                            <th class="column-image"><?php esc_html_e( 'Image', 'sop' ); ?></th>
                            <th class="column-location" data-sort="location" title="<?php esc_attr_e( 'Warehouse location / bin', 'sop' ); ?>"><?php esc_html_e( 'Location', 'sop' ); ?></th>
                            <th class="column-sku" data-sort="sku" title="<?php esc_attr_e( 'SKU (stock-keeping unit)', 'sop' ); ?>"><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                            <th class="column-name" data-sort="name" title="<?php esc_attr_e( 'Product name', 'sop' ); ?>"><?php esc_html_e( 'Product', 'sop' ); ?></th>
                            <th class="column-cost-supplier" data-column="cost_supplier" data-sort="cost" title="<?php esc_attr_e( 'Cost per unit in supplier currency (GBP, USD, EUR, RMB)', 'sop' ); ?>"><?php esc_html_e( 'Cost', 'sop' ); ?></th>
                            <th class="column-brand" data-sort="brand" title="<?php esc_attr_e( 'Brand / manufacturer', 'sop' ); ?>"><?php esc_html_e( 'Brand', 'sop' ); ?></th>
                            <th class="column-stock" data-column="stock" data-sort="stock" title="<?php esc_attr_e( 'Stock on hand', 'sop' ); ?>"><?php esc_html_e( 'Stock', 'sop' ); ?></th>
                            <th class="column-inbound" data-column="inbound" data-sort="inbound" title="<?php esc_attr_e( 'Inbound quantity on purchase orders', 'sop' ); ?>"><?php esc_html_e( 'Inbound', 'sop' ); ?></th>
                            <th class="column-min-order" data-column="min_order" data-sort="moq" title="<?php esc_attr_e( 'Minimum order quantity', 'sop' ); ?>"><?php esc_html_e( 'MOQ', 'sop' ); ?></th>
                            <th class="column-suggested" data-sort="soq" title="<?php esc_attr_e( 'Suggested order quantity', 'sop' ); ?>"><?php esc_html_e( 'SOQ', 'sop' ); ?></th>
                            <th class="column-order-qty" data-sort="order_qty" title="<?php esc_attr_e( 'Manual order quantity for this shipment', 'sop' ); ?>"><?php esc_html_e( 'Qty', 'sop' ); ?></th>
                            <th class="column-line-total-supplier" data-sort="total" title="<?php esc_attr_e( 'Line total in supplier currency', 'sop' ); ?>"><?php esc_html_e( 'Total', 'sop' ); ?></th>
                            <th class="column-cubic-item" data-column="cubic" data-sort="cubic" title="<?php esc_attr_e( 'Cubic centimetres per unit', 'sop' ); ?>"><?php esc_html_e( 'cm3 per unit', 'sop' ); ?></th>
                            <th class="column-line-cbm" data-column="line_cbm" data-sort="line_cbm" title="<?php esc_attr_e( 'Line volume in cubic metres', 'sop' ); ?>"><?php esc_html_e( 'Line CBM', 'sop' ); ?></th>
                            <th class="column-regular-unit" data-column="regular_unit" data-sort="price_ex" title="<?php esc_attr_e( 'Regular WooCommerce price per unit excluding VAT', 'sop' ); ?>"><?php esc_html_e( 'Price excl.', 'sop' ); ?></th>
                            <th class="column-regular-line" data-column="regular_line" data-sort="line_ex" title="<?php esc_attr_e( 'Regular WooCommerce line price excluding VAT', 'sop' ); ?>"><?php esc_html_e( 'Line excl.', 'sop' ); ?></th>
                            <th class="column-notes" data-column="notes" data-sort="notes" title="<?php esc_attr_e( 'Internal notes for this product / supplier', 'sop' ); ?>"><?php esc_html_e( 'Notes', 'sop' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                            <tr>
                                <td colspan="17">
                                    <?php esc_html_e( 'No products found for this supplier.', 'sop' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
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
                                ?>
                                <tr data-index="<?php echo esc_attr( $index ); ?>" class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
                                    <td class="sop-preorder-col-select">
                                        <input
                                            type="checkbox"
                                            class="sop-preorder-select-row"
                                            data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                        />
                                        <?php if ( ! empty( $row['removed'] ) ) : ?>
                                            <button type="button"
                                                    class="button-link sop-preorder-restore-row"
                                                    data-row-key="<?php echo esc_attr( $row_key ); ?>">
                                                <?php esc_html_e( 'Restore', 'sop' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-image">
                                        <?php
                                        if ( $product ) {
                                            $image_id = $product->get_image_id();
                                            if ( $image_id ) {
                                                echo wp_get_attachment_image(
                                                    $image_id,
                                                    'thumbnail',
                                                    false,
                                                    [
                                                        'style' => 'width:40px;height:40px;object-fit:contain;',
                                                    ]
                                                );
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td class="column-location">
                                        <?php echo esc_html( $location ); ?>
                                    </td>
                                    <td class="column-sku">
                                        <input type="hidden" name="sop_product_id[]" value="<?php echo esc_attr( $product_id ); ?>" />
                                        <input
                                            type="text"
                                            name="sop_sku[]"
                                            value="<?php echo esc_attr( $order_sku ); ?>"
                                            title="<?php echo esc_attr( $order_sku ); ?>"
                                            class="sop-preorder-sku small-text"
                                            <?php disabled( $is_locked ); ?>
                                        />
                                    </td>
                                    <td class="column-name">
                                        <?php echo esc_html( $name ); ?>
                                    </td>
                                    <td class="column-cost-supplier" data-column="cost_supplier">
                                        <input type="number" name="sop_cost_unit_supplier[]" value="<?php echo esc_attr( $cost_supplier ); ?>" step="0.01" min="0" class="sop-cost-supplier-input" <?php disabled( $is_locked ); ?> />
                                    </td>
                                    <td class="column-brand">
                                        <?php echo esc_html( $brand ); ?>
                                    </td>
                                    <td class="column-stock" data-column="stock">
                                        <?php echo esc_html( number_format_i18n( $stock_on_hand, 0 ) ); ?>
                                    </td>
                                    <td class="column-inbound" data-column="inbound">
                                        <?php echo esc_html( number_format_i18n( $inbound_qty, 0 ) ); ?>
                                    </td>
                                    <td class="column-min-order" data-column="min_order">
                                        <input type="number" name="sop_min_order_qty[]" value="<?php echo esc_attr( $min_order_qty ); ?>" step="1" min="0" <?php disabled( $is_locked ); ?> />
                                    </td>
                                    <td class="column-suggested">
                                        <?php echo esc_html( number_format_i18n( $suggested_order_qty, 0 ) ); ?>
                                    </td>
                                    <td class="column-order-qty" data-sort="order_qty">
                                        <input type="number" name="sop_preorder_order_qty[]" value="<?php echo esc_attr( $order_qty ); ?>" step="1" min="0" class="sop-order-qty-input" <?php disabled( $is_locked ); ?> />
                                    </td>
                                    <td class="column-line-total-supplier">
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
                                                name="sop_notes[]"
                                                rows="3"
                                                class="sop-preorder-notes"
                                                style="width: 100%; resize: none;"
                                                title="<?php echo esc_attr( $notes ); ?>"
                                                <?php disabled( $is_locked ); ?>
                                            ><?php echo esc_textarea( $notes ); ?></textarea>

                                            <button type="button"
                                                    class="sop-preorder-notes-edit-icon"
                                                    data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                                    aria-label="<?php esc_attr_e( 'Edit notes', 'sop' ); ?>">
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
                                </tr>
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
                        <?php esc_html_e( 'Pre-order notes', 'sop' ); ?>
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
                        <?php esc_html_e( 'Save Sheet', 'sop' ); ?>
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

        .sop-preorder-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .sop-preorder-controls select[name="sop_supplier_id"] {
            min-width: 260px;
        }

        .sop-preorder-filter-sku {
            display: inline-block;
            margin-left: 8px;
        }

        .sop-preorder-filter-sku-field {
            display: inline-flex;
            align-items: center;
        }

        .sop-preorder-filter-sku-field .regular-text {
            width: 120px;
            max-width: 160px;
            margin-right: 2px;
        }

        .sop-preorder-sku-search-button {
            padding: 0 4px;
            min-width: 28px;
        }

        .sop-preorder-sku-search-button .dashicons {
            line-height: 1;
        }

        .sop-preorder-summary-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: #f6f7f7;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .sop-summary-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sop-summary-cbm {
            flex: 1;
            min-width: 200px;
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

        .sop-preorder-table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0;
            gap: 1rem;
            flex-wrap: wrap;
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

        .sop-column-visibility {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .sop-column-visibility label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-right: 0.5rem;
        }

        .sop-column-visibility input[type="checkbox"] {
            margin: 0;
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
        }

        .sop-preorder-table tbody td {
            vertical-align: top;
        }

        .sop-preorder-table .column-image {
            width: 80px;
            text-align: center;
        }

        .sop-preorder-table .column-image img {
            width: 60px !important;
            height: auto;
            max-height: 60px;
        }

        .sop-preorder-table {
            table-layout: auto;
            width: auto;
            min-width: 1400px;
        }

        .sop-preorder-table .column-location {
            width: 90px;
            white-space: nowrap;
        }

        .sop-preorder-table .column-name {
            min-width: 200px;
            max-width: 320px;
        }

        .sop-preorder-table .column-sku {
            width: 120px;
            white-space: nowrap;
        }

        .sop-preorder-table th.column-order-qty,
        .sop-preorder-table td.column-order-qty {
            width: 90px;
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
            margin-top: 1rem;
        }

        .sop-preorder-table th[data-sort]::after {
            content: '\25B2';
            opacity: 0.3;
            margin-left: 4px;
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

        .sop-preorder-row-removed {
            opacity: 0.6;
        }

        .sop-preorder-col-select .sop-preorder-restore-row {
            display: block;
            margin-top: 2px;
        }

        .sop-preorder-row-removed .sop-preorder-restore-row {
            pointer-events: auto;
            opacity: 1;
        }

        .sop-preorder-table th.column-notes,
        .sop-preorder-table td.column-notes {
            width: 320px;
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
            var lastClickedIndex     = null;

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

            function sopPreorderOpenNotesOverlayForRow( $row ) {
                if ( ! $row || ! $row.length ) {
                    return;
                }

                var $textarea = $row.find( '.sop-preorder-notes' );
                if ( ! $textarea.length ) {
                    return;
                }

                currentNotesTextarea = $textarea.get( 0 );

                var productText = $.trim( $row.find( 'td.column-name' ).text() || '' );
                $notesOverlayProduct.text( productText );

                $notesOverlayTextarea.val( currentNotesTextarea.value );

                $notesOverlay.show();
                $notesOverlayTextarea.focus();
            }

            function sopPreorderCloseNotesOverlay( saveChanges ) {
                if ( saveChanges && currentNotesTextarea && $notesOverlayTextarea.length ) {
                    currentNotesTextarea.value = $notesOverlayTextarea.val();
                }

                currentNotesTextarea = null;
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
                sopPreorderOpenNotesOverlayForRow( $row );
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

                var usedCbm = 0;
                if ( containerCbm > 0 && totalCbm > 0 ) {
                    usedCbm = Math.min(100, ( totalCbm / containerCbm ) * 100);
                }
                $('.sop-cbm-bar').css('width', usedCbm + '%');
                $('#sop-cbm-label').text(usedCbm.toFixed(1) + '%');
            }

            function wc_price_format(amount) {
                return '<?php echo esc_js( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?> ' + amount.toFixed(2);
            }

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
                });

                recalcTotals();
            });

            $('.sop-column-visibility input[type="checkbox"]').on('change', function() {
                var columnKey = $(this).data('column');
                var show = $(this).is(':checked');

                $table.find('[data-column="' + columnKey + '"]').toggle(show);
            });

            $table.find('th[data-sort]').on('click', function() {
                var $th = $(this);
                var sortKey = $th.data('sort');
                var isAsc = !$th.hasClass('sorted-asc');

                $table.find('th[data-sort]').removeClass('sorted-asc sorted-desc');
                $th.addClass(isAsc ? 'sorted-asc' : 'sorted-desc');

                var rows = $table.find('tbody tr').get();

                rows.sort(function(a, b) {
                    var $a = $(a);
                    var $b = $(b);

                    var valA, valB, numeric = false;

                    switch (sortKey) {
                        case 'sku':
                            valA = $a.find('.column-sku input[type="text"]').val() || '';
                            valB = $b.find('.column-sku input[type="text"]').val() || '';
                            break;
                        case 'name':
                            valA = $a.find('.column-name').text() || '';
                            valB = $b.find('.column-name').text() || '';
                            break;
                        case 'location':
                            valA = $a.find('.column-location').text() || '';
                            valB = $b.find('.column-location').text() || '';
                            break;
                        case 'brand':
                            valA = $a.find('.column-brand').text() || '';
                            valB = $b.find('.column-brand').text() || '';
                            break;
                        case 'notes':
                            valA = $a.find('.column-notes textarea').val() || '';
                            valB = $b.find('.column-notes textarea').val() || '';
                            break;
                        case 'cost':
                            valA = parseFloat($a.find('.column-cost-supplier input').val()) || 0;
                            valB = parseFloat($b.find('.column-cost-supplier input').val()) || 0;
                            numeric = true;
                            break;
                        case 'stock':
                            valA = parseFloat($a.find('.column-stock').text()) || 0;
                            valB = parseFloat($b.find('.column-stock').text()) || 0;
                            numeric = true;
                            break;
                        case 'inbound':
                            valA = parseFloat($a.find('.column-inbound').text()) || 0;
                            valB = parseFloat($b.find('.column-inbound').text()) || 0;
                            numeric = true;
                            break;
                        case 'moq':
                            valA = parseFloat($a.find('.column-min-order input').val()) || 0;
                            valB = parseFloat($b.find('.column-min-order input').val()) || 0;
                            numeric = true;
                            break;
                        case 'soq':
                            valA = parseFloat($a.find('.column-suggested').text()) || 0;
                            valB = parseFloat($b.find('.column-suggested').text()) || 0;
                            numeric = true;
                            break;
                        case 'order_qty':
                            valA = parseFloat($a.find('.sop-order-qty-input').val()) || 0;
                            valB = parseFloat($b.find('.sop-order-qty-input').val()) || 0;
                            numeric = true;
                            break;
                        case 'total':
                            valA = parseFloat($a.find('.sop-line-total-supplier').text()) || 0;
                            valB = parseFloat($b.find('.sop-line-total-supplier').text()) || 0;
                            numeric = true;
                            break;
                        case 'cubic':
                            valA = parseFloat($a.find('.column-cubic-item').text()) || 0;
                            valB = parseFloat($b.find('.column-cubic-item').text()) || 0;
                            numeric = true;
                            break;
                        case 'line_cbm':
                            valA = parseFloat($a.find('.column-line-cbm').text()) || 0;
                            valB = parseFloat($b.find('.column-line-cbm').text()) || 0;
                            numeric = true;
                            break;
                        case 'price_ex':
                            valA = parseFloat($a.find('.column-regular-unit').text()) || 0;
                            valB = parseFloat($b.find('.column-regular-unit').text()) || 0;
                            numeric = true;
                            break;
                        case 'line_ex':
                            valA = parseFloat($a.find('.column-regular-line').text()) || 0;
                            valB = parseFloat($b.find('.column-regular-line').text()) || 0;
                            numeric = true;
                            break;
                        default:
                            valA = 0;
                            valB = 0;
                            numeric = true;
                            break;
                    }

                    if (numeric) {
                        return isAsc ? valA - valB : valB - valA;
                    }

                    return isAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
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

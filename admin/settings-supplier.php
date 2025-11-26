<?php
/**
 * Stock Order Plugin â€“ Phase 2 (Updated with USD)
 * Admin Settings & Supplier UI (General + Suppliers)
 * File version: 1.5.20
 *
 * - Adds "Stock Order" top-level admin menu.
 * - General Settings tab stores global options in `sop_settings`.
 * - Suppliers tab manages rows in `sop_suppliers` via Phase 1 helpers.
 * - Implements global stock buffer months + per-supplier override (months).
 * - Supplier currency options: GBP, RMB, EUR, USD.
 * - Future phases: plugin will own its own productâ†’supplier links and foreign prices
 *   (your existing meta like "supplier" and "rmb" can be migrated via a one-off tool).
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

// Require Phase 1 DB + helper layer.
if ( ! class_exists( 'sop_DB' ) || ! function_exists( 'sop_supplier_get_all' ) ) {
    return;
}

if ( ! class_exists( 'sop_Admin_Settings' ) ) :

class sop_Admin_Settings {

    /**
     * Option name for global settings.
     */
    const OPTION_KEY = 'sop_settings';

    /**
     * Constructor â€“ hook into admin.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register the "Stock Order" admin menu + single page.
     */
    public function register_menu() {
        // Only for users who can manage WooCommerce.
        $capability     = 'manage_woocommerce';
        $dashboard_slug = 'sop_stock_order_dashboard';
        $settings_slug  = 'sop_stock_order';

        // Prevent legacy submenu registrations from attaching to the old parent slug.
        remove_action( 'admin_menu', 'sop_register_products_by_supplier_submenu' );
        remove_action( 'admin_menu', 'sop_preorder_register_admin_menu', 99 );

        add_menu_page(
            __( 'Stock Order', 'sop' ),
            __( 'Stock Order', 'sop' ),
            $capability,
            $dashboard_slug,
            array( $this, 'render_dashboard_page' ),
            'dashicons-products',
            56
        );

        // Products by Supplier.
        add_submenu_page(
            $dashboard_slug,
            __( 'Products by Supplier', 'sop' ),
            __( 'Products by Supplier', 'sop' ),
            $capability,
            'sop_products_by_supplier',
            'sop_render_products_by_supplier_page'
        );

        // Pre-Order Sheet.
        add_submenu_page(
            $dashboard_slug,
            __( 'Pre-Order Sheet', 'sop' ),
            __( 'Pre-Order Sheet', 'sop' ),
            $capability,
            'sop-preorder-sheet',
            'sop_preorder_render_admin_page'
        );

        // General Settings (existing settings UI).
        add_submenu_page(
            $dashboard_slug,
            __( 'General Settings', 'sop' ),
            __( 'General Settings', 'sop' ),
            $capability,
            $settings_slug,
            array( $this, 'render_page' )
        );
    }

    /**
     * Register the global settings with the options API.
     */
    public function register_settings() {
        register_setting(
            'sop_settings_group',
            self::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize the global settings before saving.
     *
     * @param array $input Raw input from form.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $defaults = self::get_default_settings();
        $output   = $defaults;

        $input = is_array( $input ) ? $input : array();

        // Analysis lookback days.
        if ( isset( $input['analysis_lookback_days'] ) ) {
            $output['analysis_lookback_days'] = max( 1, (int) $input['analysis_lookback_days'] );
        }

        // Global buffer months.
        if ( isset( $input['buffer_months_global'] ) ) {
            $buffer = (float) $input['buffer_months_global'];
            $output['buffer_months_global'] = ( $buffer < 0 ) ? 0 : $buffer;
        }

        // RMB â†’ GBP rate (allow string, we'll cast when using).
        if ( isset( $input['rmb_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['rmb_to_gbp_rate'] );
            $output['rmb_to_gbp_rate'] = $rate;
        }

        // EUR ï¿½ï¿½' GBP rate (allow string, we'll cast when using).
        if ( isset( $input['eur_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['eur_to_gbp_rate'] );
            $output['eur_to_gbp_rate'] = $rate;
        }

        // USD ï¿½ï¿½' GBP rate (allow string, we'll cast when using).
        if ( isset( $input['usd_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['usd_to_gbp_rate'] );
            $output['usd_to_gbp_rate'] = $rate;
        }

        // Show suggested vs max order toggle.
        $output['show_suggested_vs_max'] = ! empty( $input['show_suggested_vs_max'] ) ? 1 : 0;

        return $output;
    }

    /**
     * Get default global settings (used for merge + initial display).
     *
     * @return array
     */
    public static function get_default_settings() {
        return array(
            'analysis_lookback_days'   => 365, // 12 months as default.
            'buffer_months_global'     => 6,   // Global stock buffer period in months.
            'rmb_to_gbp_rate'          => '',  // Optional, manual entry.
            'eur_to_gbp_rate'          => '',  // Optional, manual entry.
            'usd_to_gbp_rate'          => '',  // Optional, manual entry.
            'show_suggested_vs_max'    => 1,   // Show comparison by default.
        );
    }

    /**
     * Fetch current settings from the DB with defaults merged.
     *
     * @return array
     */
    public static function get_settings() {
        $stored   = get_option( self::OPTION_KEY, array() );
        $defaults = self::get_default_settings();

        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        return wp_parse_args( $stored, $defaults );
    }

    /**
     * Main page renderer â€“ handles tab switching.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        if ( ! in_array( $active_tab, array( 'general', 'suppliers' ), true ) ) {
            $active_tab = 'general';
        }

        echo '<div class="wrap sop-wrap">';
        echo '<h1>' . esc_html__( 'Stock Order Plugin', 'sop' ) . '</h1>';

        // Render tabs.
        echo '<h2 class="nav-tab-wrapper">';
        printf(
            '<a href="%s" class="nav-tab %s">%s</a>',
            esc_url( admin_url( 'admin.php?page=sop_stock_order&tab=general' ) ),
            ( 'general' === $active_tab ? 'nav-tab-active' : '' ),
            esc_html__( 'General Settings', 'sop' )
        );
        printf(
            '<a href="%s" class="nav-tab %s">%s</a>',
            esc_url( admin_url( 'admin.php?page=sop_stock_order&tab=suppliers' ) ),
            ( 'suppliers' === $active_tab ? 'nav-tab-active' : '' ),
            esc_html__( 'Suppliers', 'sop' )
        );
        echo '</h2>';

        // Tab content.
        if ( 'suppliers' === $active_tab ) {
            $this->handle_supplier_actions();
            $this->render_suppliers_tab();
        } else {
            $this->render_general_tab();
        }

        echo '</div>'; // .wrap
    }

    /**
     * Render the Stock Order Dashboard.
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Ensure WooCommerce enhanced select assets are available on the dashboard.
        if ( class_exists( 'WooCommerce' ) ) {
            wp_enqueue_style( 'woocommerce_admin_styles' );
            wp_enqueue_script( 'selectWoo' );
            wp_enqueue_script( 'wc-enhanced-select' );
        }

        $dashboard_category_ids = array();
        if ( ! empty( $_GET['sop_dashboard_cats'] ) && is_array( $_GET['sop_dashboard_cats'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $dashboard_category_ids = array_map( 'absint', wp_unslash( $_GET['sop_dashboard_cats'] ) );
        }

        $settings = function_exists( 'sop_get_settings' ) ? sop_get_settings() : array();

        /**
         * Get unit cost in GBP for dashboard metrics (reuses existing SOP cost logic).
         *
         * @param WC_Product $product Product instance.
         * @return float
         */
        $get_cost_gbp = function( $product ) use ( $settings ) {
            if ( ! $product instanceof WC_Product ) {
                return 0.0;
            }

            $product_id    = $product->get_id();
            $unit_cost_gbp = (float) get_post_meta( $product_id, '_cogs_value', true );

            if ( $unit_cost_gbp > 0 ) {
                return $unit_cost_gbp;
            }

            $supplier_id = (int) get_post_meta( $product_id, '_sop_supplier_id', true );
            $currency    = '';

            if ( $supplier_id > 0 && function_exists( 'sop_supplier_get_by_id' ) ) {
                $supplier = sop_supplier_get_by_id( $supplier_id );
                if ( $supplier && ! empty( $supplier->currency ) ) {
                    $currency = strtoupper( (string) $supplier->currency );
                }
            }

            $rate_rmb = isset( $settings['rmb_to_gbp_rate'] ) ? (float) $settings['rmb_to_gbp_rate'] : 0.0;
            $rate_eur = isset( $settings['eur_to_gbp_rate'] ) ? (float) $settings['eur_to_gbp_rate'] : 0.0;
            $rate_usd = isset( $settings['usd_to_gbp_rate'] ) ? (float) $settings['usd_to_gbp_rate'] : 0.0;

            switch ( $currency ) {
                case 'GBP':
                    $unit_cost_gbp = (float) get_post_meta( $product_id, '_sop_cost_gbp', true );
                    break;
                case 'RMB':
                    $unit_cost_gbp = (float) get_post_meta( $product_id, '_sop_cost_rmb', true );
                    if ( $unit_cost_gbp > 0 && $rate_rmb > 0 ) {
                        $unit_cost_gbp = $unit_cost_gbp * $rate_rmb;
                    } else {
                        $unit_cost_gbp = 0.0;
                    }
                    break;
                case 'USD':
                    $unit_cost_gbp = (float) get_post_meta( $product_id, '_sop_cost_usd', true );
                    if ( $unit_cost_gbp > 0 && $rate_usd > 0 ) {
                        $unit_cost_gbp = $unit_cost_gbp * $rate_usd;
                    } else {
                        $unit_cost_gbp = 0.0;
                    }
                    break;
                case 'EUR':
                    $unit_cost_gbp = (float) get_post_meta( $product_id, '_sop_cost_eur', true );
                    if ( $unit_cost_gbp > 0 && $rate_eur > 0 ) {
                        $unit_cost_gbp = $unit_cost_gbp * $rate_eur;
                    } else {
                        $unit_cost_gbp = 0.0;
                    }
                    break;
                default:
                    $unit_cost_gbp = 0.0;
                    break;
            }

            if ( $unit_cost_gbp < 0 ) {
                $unit_cost_gbp = 0.0;
            }

            return $unit_cost_gbp;
        };

        $stock_value_retail_ex_vat  = 0.0;
        $stock_value_retail_inc_vat = 0.0;
        $stock_units_total          = 0;
        $stock_cost_total           = 0.0;
        $stockout_products          = array();
        $supplier_ids               = array();
        $total_products             = 0;

        $tax_query = array();
        if ( ! empty( $dashboard_category_ids ) ) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $dashboard_category_ids,
                'operator' => 'IN',
            );
        }

        $query_args = array(
            'status'       => 'publish',
            'limit'        => -1,
            'type'         => array( 'simple', 'variation' ),
            'return'       => 'objects',
            'stock_status' => array( 'instock', 'outofstock' ),
        );

        if ( ! empty( $tax_query ) ) {
            $query_args['tax_query'] = $tax_query;
        }

        $product_query = new WC_Product_Query( $query_args );
        $products      = $product_query->get_products();

        if ( ! empty( $products ) ) {
            foreach ( $products as $product ) {
                if ( ! $product instanceof WC_Product ) {
                    continue;
                }

                if ( ! $product->managing_stock() ) {
                    continue;
                }

                $total_products++;

                $qty = $product->get_stock_quantity();
                if ( null === $qty ) {
                    $qty = 0;
                }

                $qty = (int) $qty;

                if ( $qty > 0 ) {
                    $price_ex_vat  = wc_get_price_excluding_tax( $product );
                    $price_inc_vat = wc_get_price_including_tax( $product );

                    $stock_units_total          += $qty;
                    $stock_value_retail_ex_vat  += $qty * $price_ex_vat;
                    $stock_value_retail_inc_vat += $qty * $price_inc_vat;

                    $cost_price_gbp = $get_cost_gbp( $product );
                    if ( $cost_price_gbp > 0 ) {
                        $stock_cost_total += $qty * $cost_price_gbp;
                    }
                }

                $supplier_id_for_count = (int) get_post_meta( $product->get_id(), '_sop_supplier_id', true );
                if ( $supplier_id_for_count > 0 ) {
                    $supplier_ids[ $supplier_id_for_count ] = true;
                }

                if ( 'outofstock' === $product->get_stock_status() ) {
                    if ( count( $stockout_products ) < 15 ) {
                        $stockout_products[] = array(
                            'id'    => $product->get_id(),
                            'name'  => $product->get_name(),
                            'sku'   => $product->get_sku(),
                            'image' => $product->get_image( 'woocommerce_gallery_thumbnail' ),
                        );
                    }
                }
            }
        }

        $stockout_count = count( $stockout_products );

        $average_cost_ex_vat    = 0.0;
        $average_retail_ex_vat  = 0.0;
        $average_retail_inc_vat = 0.0;

        if ( $stock_units_total > 0 ) {
            $average_cost_ex_vat    = $stock_cost_total / $stock_units_total;
            $average_retail_ex_vat  = $stock_value_retail_ex_vat / $stock_units_total;
            $average_retail_inc_vat = $stock_value_retail_inc_vat / $stock_units_total;
        }

        $supplier_count = count( $supplier_ids );

        ?>
        <div class="wrap sop-dashboard-wrap">
            <h1 class="sop-dashboard-title"><?php esc_html_e( 'Stock Order Dashboard', 'sop' ); ?></h1>

            <form method="get" class="sop-dashboard-filter-form">
                <?php
                foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ( in_array( $key, array( 'sop_dashboard_cats', 'page' ), true ) ) {
                        continue;
                    }

                    if ( is_array( $value ) ) {
                        foreach ( $value as $sub_value ) {
                            printf(
                                '<input type="hidden" name="%s[]" value="%s" />',
                                esc_attr( $key ),
                                esc_attr( $sub_value )
                            );
                        }
                    } else {
                        printf(
                            '<input type="hidden" name="%s" value="%s" />',
                            esc_attr( $key ),
                            esc_attr( $value )
                        );
                    }
                }
                ?>
                <input type="hidden" name="page" value="sop_stock_order_dashboard" />

                <div class="sop-dashboard-filter-field">
                    <label for="sop-dashboard-category-select"><?php esc_html_e( 'Category filter:', 'sop' ); ?></label>
                    <select id="sop-dashboard-category-select"
                            name="sop_dashboard_cats[]"
                            class="sop-dashboard-category-select wc-enhanced-select"
                            multiple="multiple"
                            data-placeholder="<?php esc_attr_e( 'All categories', 'sop' ); ?>"
                            data-minimum-results-for-search="-1">
                    <?php
                    $categories = get_terms(
                        array(
                            'taxonomy'   => 'product_cat',
                            'hide_empty' => false,
                        )
                    );
                    if ( ! is_wp_error( $categories ) ) :
                        foreach ( $categories as $category ) :
                            ?>
                            <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( in_array( $category->term_id, $dashboard_category_ids, true ), true ); ?>>
                                <?php echo esc_html( $category->name ); ?>
                            </option>
                            <?php
                        endforeach;
                    endif;
                    ?>
                    </select>
                </div>
                <button type="submit" class="button"><?php esc_html_e( 'Apply filter', 'sop' ); ?></button>
            </form>

            <div class="sop-dashboard-row sop-dashboard-row-cards">
                <div class="sop-dashboard-card sop-dashboard-card-overview">
                    <h2 class="sop-dashboard-card-title">
                        <?php esc_html_e( 'Stock overview', 'sop' ); ?>
                        <span class="dashicons dashicons-info sop-dashboard-tooltip" title="<?php esc_attr_e( 'Includes current sale prices.', 'sop' ); ?>"></span>
                    </h2>

                    <div class="sop-dashboard-retail-total">
                        <div class="sop-dashboard-retail-value js-sop-stock-value-display"
                             data-display-ex="<?php echo esc_attr( wc_price( $stock_value_retail_ex_vat ) ); ?>"
                             data-display-inc="<?php echo esc_attr( wc_price( $stock_value_retail_inc_vat ) ); ?>">
                            <?php echo wp_kses_post( wc_price( $stock_value_retail_ex_vat ) ); ?>
                        </div>
                        <div class="sop-dashboard-retail-toggle">
                            <button type="button" class="button button-small sop-stock-value-toggle is-active" data-mode="ex"><?php esc_html_e( 'Ex VAT', 'sop' ); ?></button>
                            <button type="button" class="button button-small sop-stock-value-toggle" data-mode="inc"><?php esc_html_e( 'Inc VAT', 'sop' ); ?></button>
                        </div>
                    </div>

                    <div class="sop-dashboard-metric-grid">
                        <div class="sop-dashboard-metric">
                            <div class="sop-dashboard-metric-label"><?php esc_html_e( 'Total stock value (cost)', 'sop' ); ?></div>
                            <div class="sop-dashboard-metric-value"><?php echo wp_kses_post( wc_price( $stock_cost_total ) ); ?></div>
                        </div>
                        <div class="sop-dashboard-metric sop-dashboard-metric-units">
                            <div class="sop-dashboard-metric-label"><?php esc_html_e( 'Total units', 'sop' ); ?></div>
                            <div class="sop-dashboard-metric-value"><?php echo esc_html( number_format_i18n( $stock_units_total, 0 ) ); ?></div>
                            <div class="sop-dashboard-metric-subline">
                                <span class="sop-dashboard-metric-sub-label"><?php esc_html_e( 'Products', 'sop' ); ?></span>
                                <span class="sop-dashboard-metric-sub-value"><?php echo esc_html( number_format_i18n( $total_products, 0 ) ); ?></span>
                            </div>
                        </div>
                        <div class="sop-dashboard-metric">
                            <div class="sop-dashboard-metric-label"><?php esc_html_e( 'Average cost per unit (ex VAT)', 'sop' ); ?></div>
                            <div class="sop-dashboard-metric-value">
                                <?php echo ( $average_cost_ex_vat > 0 ) ? wp_kses_post( wc_price( $average_cost_ex_vat ) ) : '&mdash;'; ?>
                            </div>
                        </div>
                        <div class="sop-dashboard-metric">
                            <div class="sop-dashboard-metric-label"><?php esc_html_e( 'Average retail per unit (ex VAT)', 'sop' ); ?></div>
                            <div class="sop-dashboard-metric-value">
                                <?php echo ( $average_retail_ex_vat > 0 ) ? wp_kses_post( wc_price( $average_retail_ex_vat ) ) : '&mdash;'; ?>
                            </div>
                        </div>
                        <div class="sop-dashboard-metric">
                            <div class="sop-dashboard-metric-label"><?php esc_html_e( 'Average retail per unit (inc VAT)', 'sop' ); ?></div>
                            <div class="sop-dashboard-metric-value">
                                <?php echo ( $average_retail_inc_vat > 0 ) ? wp_kses_post( wc_price( $average_retail_inc_vat ) ) : '&mdash;'; ?>
                            </div>
                        </div>
                        <div class="sop-dashboard-metric">
                            <div class="sop-dashboard-metric-label"><?php esc_html_e( 'Suppliers', 'sop' ); ?></div>
                            <div class="sop-dashboard-metric-value"><?php echo esc_html( number_format_i18n( $supplier_count, 0 ) ); ?></div>
                        </div>
                    </div>
                </div>

                <div class="sop-dashboard-card sop-dashboard-card-stockouts">
                    <h2 class="sop-dashboard-card-title"><?php esc_html_e( 'Stock-out watchlist', 'sop' ); ?></h2>
                    <p><?php printf( esc_html__( 'Products currently out of stock: %s', 'sop' ), esc_html( number_format_i18n( $stockout_count, 0 ) ) ); ?></p>
                    <?php if ( ! empty( $stockout_products ) ) : ?>
                        <ul class="sop-dashboard-watchlist">
                            <?php foreach ( array_slice( $stockout_products, 0, 3 ) as $stockout_row ) : ?>
                                <li>
                                    <span class="sop-dashboard-watchlist-sku"><?php echo esc_html( $stockout_row['sku'] ? $stockout_row['sku'] : $stockout_row['name'] ); ?></span>
                                    <span class="sop-dashboard-watchlist-name"><?php echo esc_html( $stockout_row['name'] ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'No products are currently out of stock.', 'sop' ); ?></p>
                    <?php endif; ?>
                    <p class="sop-dashboard-stockout-toggle">
                        <a href="#" class="sop-dashboard-toggle-stockout-table">
                            <?php esc_html_e( 'View out-of-stock products', 'sop' ); ?>
                        </a>
                    </p>
                </div>
            </div>

            <div class="sop-dashboard-row sop-dashboard-row-stockouts">
                <div id="sop-dashboard-stockout-table-wrapper" class="sop-dashboard-stockout-table-wrapper">
                    <h2><?php esc_html_e( 'Out-of-stock products', 'sop' ); ?></h2>
                    <?php if ( 0 === $stockout_count ) : ?>
                        <p><?php esc_html_e( 'Good news - no products are currently out of stock.', 'sop' ); ?></p>
                    <?php else : ?>
                        <table class="widefat fixed striped sop-dashboard-stockout-table">
                            <thead>
                                <tr>
                                    <th class="column-image"><?php esc_html_e( 'Image', 'sop' ); ?></th>
                                    <th class="column-sku"><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                                    <th class="column-product"><?php esc_html_e( 'Product', 'sop' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $stockout_products as $stockout_row ) : ?>
                                    <tr>
                                        <td class="column-image"><?php echo wp_kses_post( $stockout_row['image'] ); ?></td>
                                        <td class="column-sku"><?php echo esc_html( $stockout_row['sku'] ); ?></td>
                                        <td class="column-product"><?php echo esc_html( $stockout_row['name'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            .sop-dashboard-wrap {
                max-width: 100%;
                margin-top: 20px;
            }

            .sop-dashboard-filter-form {
                margin: 12px 0 0;
                display: flex;
                flex-wrap: wrap;
                gap: 8px 16px;
                align-items: center;
            }

            .sop-dashboard-filter-field {
                display: flex;
                flex-direction: column;
                min-width: 260px;
                max-width: 420px;
            }

            .sop-dashboard-filter-field label {
                font-weight: 600;
                margin-bottom: 4px;
            }

            .sop-dashboard-category-select {
                min-width: 260px;
                max-width: 420px;
            }

            .sop-dashboard-filter-form .select2-container {
                min-width: 260px;
                max-width: 420px;
            }

            .sop-dashboard-filter-form .select2-search--dropdown {
                display: none !important;
            }

            .sop-dashboard-row {
                margin-top: 20px;
            }

            .sop-dashboard-row-cards {
                display: grid;
                grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
                gap: 20px;
            }

            @media ( max-width: 960px ) {
                .sop-dashboard-row-cards {
                    grid-template-columns: 1fr;
                }
            }

            .sop-dashboard-card {
                background: #fff;
                border-radius: 10px;
                border: 1px solid #e2e4e7;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
                padding: 18px 20px;
            }

            .sop-dashboard-card-title {
                margin: 0 0 12px;
                font-size: 16px;
                font-weight: 600;
            }

            .sop-dashboard-retail-value {
                font-size: 1.6em;
                font-weight: 700;
                margin: 6px 0;
            }

            .sop-dashboard-retail-toggle .button {
                margin-right: 6px;
            }

            .sop-stock-value-toggle.is-active {
                background: #2271b1;
                color: #fff;
                border-color: #1d5f8d;
            }

            .sop-dashboard-metric-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 10px 16px;
                margin-top: 16px;
            }

            .sop-dashboard-metric-label {
                font-size: 13px;
                color: #556070;
            }

            .sop-dashboard-metric-value {
                font-size: 16px;
                font-weight: 600;
                margin-top: 4px;
            }

            .sop-dashboard-metric-subline {
                display: flex;
                align-items: baseline;
                gap: 4px;
                margin-top: 3px;
                font-size: 11px;
                color: #6c757d;
            }

            .sop-dashboard-metric-sub-label {
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }

            .sop-dashboard-metric-sub-value {
                font-weight: 600;
            }

            .sop-dashboard-tooltip {
                margin-left: 6px;
                vertical-align: middle;
                cursor: help;
            }

            .sop-dashboard-card-stockouts .sop-dashboard-watchlist {
                margin: 0 0 8px;
                padding-left: 18px;
            }

            .sop-dashboard-card-stockouts .sop-dashboard-watchlist li {
                margin-bottom: 4px;
            }

            .sop-dashboard-stockout-table-wrapper {
                display: none;
            }

            .sop-dashboard-stockout-table-wrapper.is-open {
                display: block;
            }

            .sop-dashboard-stockout-table .column-image {
                width: 70px;
            }

            .sop-dashboard-stockout-table img {
                max-width: 50px;
                height: auto;
            }
        </style>

        <script>
            jQuery(function($) {
                $('.sop-dashboard-card-overview').on('click', '.sop-stock-value-toggle', function(e) {
                    e.preventDefault();
                    var $btn   = $(this);
                    var mode   = $btn.data('mode');
                    var $wrap  = $btn.closest('.sop-dashboard-card-overview');
                    var $value = $wrap.find('.js-sop-stock-value-display');

                    if ( ! mode || ! $value.length ) {
                        return;
                    }

                    $wrap.find('.sop-stock-value-toggle').removeClass('is-active');
                    $btn.addClass('is-active');

                    if ( 'inc' === mode ) {
                        $value.html( $value.data('display-inc') );
                    } else {
                        $value.html( $value.data('display-ex') );
                    }
                });

                $('.sop-dashboard-card-stockouts').on('click', '.sop-dashboard-toggle-stockout-table', function(e) {
                    e.preventDefault();
                    var $wrapper = $('#sop-dashboard-stockout-table-wrapper');
                    $wrapper.toggleClass('is-open');
                });

                if ( $.fn.selectWoo || $.fn.wc_enhanced_select ) {
                    $('.sop-dashboard-category-select').each(function() {
                        var $el = $( this );

                        if ( $el.data( 'select2' ) ) {
                            return;
                        }

                        if ( $.fn.selectWoo ) {
                            $el.selectWoo({
                                placeholder: $el.data( 'placeholder' ) || '',
                                minimumResultsForSearch: $el.data( 'minimum-results-for-search' ) || -1
                            });
                        } else if ( $.fn.wc_enhanced_select ) {
                            $el.wc_enhanced_select();
                        }
                    });
                }
            });
        </script>
        <?php
    }
    protected function render_general_tab() {
        $settings = self::get_settings();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'sop_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="sop_analysis_lookback_days">
                                <?php esc_html_e( 'Default analysis period (days)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="sop_analysis_lookback_days"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[analysis_lookback_days]"
                                   value="<?php echo esc_attr( (int) $settings['analysis_lookback_days'] ); ?>"
                                   min="1"
                                   class="small-text"
                                   size="20"
                                   style="width: 8em;" />
                            <p class="description">
                                <?php esc_html_e( 'Used by the forecast engine as the default lookback window (e.g. 365 = last 12 months).', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_buffer_months_global">
                                <?php esc_html_e( 'Global stock buffer period (months)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   step="0.1"
                                   id="sop_buffer_months_global"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[buffer_months_global]"
                                   value="<?php echo esc_attr( $settings['buffer_months_global'] ); ?>"
                                   min="0"
                                   class="small-text"
                                   size="20"
                                   style="width: 8em;" />
                            <p class="description">
                                <?php esc_html_e( 'Base buffer period used in demand projections. Individual suppliers can override this.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_rmb_to_gbp_rate">
                                <?php esc_html_e( 'RMB â†’ GBP rate (optional)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sop_rmb_to_gbp_rate"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rmb_to_gbp_rate]"
                                   value="<?php echo esc_attr( $settings['rmb_to_gbp_rate'] ); ?>"
                                   class="small-text"
                                   size="20"
                                   style="width: 8em;" />
                            <p class="description">
                                <?php esc_html_e( 'Optional manual exchange rate for cost comparisons. Leave blank if not needed.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_eur_to_gbp_rate">
                                <?php esc_html_e( 'EUR to GBP rate (optional)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sop_eur_to_gbp_rate"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[eur_to_gbp_rate]"
                                   value="<?php echo esc_attr( $settings['eur_to_gbp_rate'] ); ?>"
                                   class="small-text"
                                   size="20"
                                   style="width: 8em;" />
                            <p class="description">
                                <?php esc_html_e( 'Optional manual exchange rate for cost comparisons. Leave blank if not needed.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_usd_to_gbp_rate">
                                <?php esc_html_e( 'USD to GBP rate (optional)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sop_usd_to_gbp_rate"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[usd_to_gbp_rate]"
                                   value="<?php echo esc_attr( $settings['usd_to_gbp_rate'] ); ?>"
                                   class="small-text"
                                   size="20"
                                   style="width: 8em;" />
                            <p class="description">
                                <?php esc_html_e( 'Optional manual exchange rate for cost comparisons. Leave blank if not needed.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Show suggested vs max order comparison', 'sop' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_suggested_vs_max]"
                                       value="1" <?php checked( 1, (int) $settings['show_suggested_vs_max'] ); ?> />
                                <?php esc_html_e( 'Display plugin suggested qty alongside max_order_qty_per_month on forecast screens.', 'sop' ); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Handle supplier create/update actions.
     */
    protected function handle_supplier_actions() {
        if ( ! isset( $_POST['sop_supplier_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        check_admin_referer( 'sop_save_supplier', 'sop_supplier_nonce' );

        $action = sanitize_key( wp_unslash( $_POST['sop_supplier_action'] ) );
        if ( 'save' !== $action ) {
            return;
        }

        $id              = isset( $_POST['sop_supplier_id'] ) ? (int) $_POST['sop_supplier_id'] : 0;
        $name            = isset( $_POST['sop_supplier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_name'] ) ) : '';
        $slug            = isset( $_POST['sop_supplier_slug'] ) ? sanitize_title( wp_unslash( $_POST['sop_supplier_slug'] ) ) : '';
        $currency        = isset( $_POST['sop_supplier_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_currency'] ) ) : 'GBP';
        $lead_time_weeks = isset( $_POST['sop_supplier_lead_time_weeks'] ) ? (int) $_POST['sop_supplier_lead_time_weeks'] : 0;
        $is_active       = ! empty( $_POST['sop_supplier_is_active'] ) ? 1 : 0;

        // Per-supplier buffer override (months).
        $buffer_override_raw = isset( $_POST['sop_supplier_buffer_months_override'] )
            ? trim( wp_unslash( $_POST['sop_supplier_buffer_months_override'] ) )
            : '';

        $buffer_override = ( '' === $buffer_override_raw ) ? '' : (float) $buffer_override_raw;
        if ( is_numeric( $buffer_override ) && $buffer_override < 0 ) {
            $buffer_override = 0;
        }

        // Preserve existing settings_json if editing.
        $settings_array = array();

        if ( $id > 0 ) {
            $existing = sop_supplier_get_by_id( $id );
            if ( $existing && ! empty( $existing->settings_json ) ) {
                $decoded = json_decode( $existing->settings_json, true );
                if ( is_array( $decoded ) ) {
                    $settings_array = $decoded;
                }
            }
        }

        if ( '' === $buffer_override_raw ) {
            // Empty input = remove override (fall back to global).
            unset( $settings_array['buffer_months_override'] );
        } else {
            $settings_array['buffer_months_override'] = (float) $buffer_override;
        }

        $settings_json = ! empty( $settings_array ) ? wp_json_encode( $settings_array ) : null;

        $args = array(
            'id'              => $id,
            'name'            => $name,
            'slug'            => $slug,
            'currency'        => $currency,
            'lead_time_weeks' => $lead_time_weeks,
            'is_active'       => $is_active,
            'settings_json'   => $settings_json,
        );

        $result = sop_supplier_upsert( $args );

        if ( $result ) {
            // Redirect to avoid resubmission on refresh.
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'        => 'sop_stock_order',
                        'tab'         => 'suppliers',
                        'updated'     => '1',
                        'supplier_id' => $result,
                        'sop_edited'  => '1',
                    ),
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
    }

    /**
     * Render the Suppliers tab: list + add/edit form.
     */
    protected function render_suppliers_tab() {
        $editing_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0;
        $editing    = null;

        if ( $editing_id > 0 ) {
            $editing = sop_supplier_get_by_id( $editing_id );
        }

        // Feedback message.
        if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            esc_html_e( 'Supplier saved.', 'sop' );
            echo '</p></div>';
        }

        // Fetch all suppliers for list.
        $suppliers = sop_supplier_get_all();

        ?>
        <div class="sop-suppliers-wrap">
            <h2><?php esc_html_e( 'Suppliers', 'sop' ); ?></h2>

            <p><?php esc_html_e( 'Manage suppliers used by the Stock Order plugin. Each supplier can have its own lead time, currency, and optional stock buffer override.', 'sop' ); ?></p>

            <h3><?php esc_html_e( 'Supplier list', 'sop' ); ?></h3>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Currency', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Lead time (weeks)', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Buffer override (months)', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Active', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'sop' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( ! empty( $suppliers ) ) :
                        foreach ( $suppliers as $supplier ) :
                            $settings_json   = isset( $supplier->settings_json ) ? $supplier->settings_json : null;
                            $settings_arr    = $settings_json ? json_decode( $settings_json, true ) : array();
                            $buffer_override = ( is_array( $settings_arr ) && isset( $settings_arr['buffer_months_override'] ) )
                                ? (float) $settings_arr['buffer_months_override']
                                : '';

                            ?>
                            <tr>
                                <td><?php echo esc_html( $supplier->name ); ?></td>
                                <td><?php echo esc_html( $supplier->slug ); ?></td>
                                <td><?php echo esc_html( $supplier->currency ); ?></td>
                                <td><?php echo esc_html( (int) $supplier->lead_time_weeks ); ?></td>
                                <td>
                                    <?php
                                    if ( '' === $buffer_override ) {
                                        esc_html_e( 'Global', 'sop' );
                                    } else {
                                        echo esc_html( $buffer_override );
                                    }
                                    ?>
                                </td>
                                <td><?php echo $supplier->is_active ? esc_html__( 'Yes', 'sop' ) : esc_html__( 'No', 'sop' ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg(
                                        array(
                                            'page'        => 'sop_stock_order',
                                            'tab'         => 'suppliers',
                                            'supplier_id' => (int) $supplier->id,
                                        ),
                                        admin_url( 'admin.php' )
                                    ) ); ?>">
                                        <?php esc_html_e( 'Edit', 'sop' ); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    else :
                        ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No suppliers found yet. Use the form below to add one.', 'sop' ); ?></td>
                        </tr>
                        <?php
                    endif;
                    ?>
                </tbody>
            </table>

            <hr />

            <h3>
                <?php
                if ( $editing ) {
                    esc_html_e( 'Edit supplier', 'sop' );
                } else {
                    esc_html_e( 'Add new supplier', 'sop' );
                }
                ?>
            </h3>

            <?php
            $editing_id_val      = 0;
            $name_val            = '';
            $slug_val            = '';
            $currency_val        = 'GBP';
            $lead_time_val       = 0;
            $active_val          = 1;
            $buffer_override_val = '';

            if ( $editing ) {
                $editing_id_val    = (int) $editing->id;
                $name_val          = $editing->name;
                $slug_val          = $editing->slug;
                $currency_val      = $editing->currency;
                $lead_time_val     = (int) $editing->lead_time_weeks;
                $active_val        = (int) $editing->is_active;
                $settings_arr = $editing->settings_json ? json_decode( $editing->settings_json, true ) : array();
                if ( is_array( $settings_arr ) && isset( $settings_arr['buffer_months_override'] ) ) {
                    $buffer_override_val = (float) $settings_arr['buffer_months_override'];
                }
            }
            ?>

            <form method="post">
                <?php wp_nonce_field( 'sop_save_supplier', 'sop_supplier_nonce' ); ?>
                <input type="hidden" name="sop_supplier_action" value="save" />
                <input type="hidden" name="sop_supplier_id" value="<?php echo esc_attr( $editing_id_val ); ?>" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_name">
                                    <?php esc_html_e( 'Name', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_name"
                                       name="sop_supplier_name"
                                       value="<?php echo esc_attr( $name_val ); ?>"
                                       class="regular-text"
                                       required />
                                <p class="description">
                                    <?php esc_html_e( 'Internal supplier name (e.g. "Shiny International").', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_slug">
                                    <?php esc_html_e( 'Slug', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_slug"
                                       name="sop_supplier_slug"
                                       value="<?php echo esc_attr( $slug_val ); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Optional. If left blank, it is generated from the name.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_currency">
                                    <?php esc_html_e( 'Currency', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="sop_supplier_currency" name="sop_supplier_currency">
                                    <option value="GBP" <?php selected( $currency_val, 'GBP' ); ?>>GBP</option>
                                    <option value="RMB" <?php selected( $currency_val, 'RMB' ); ?>>RMB</option>
                                    <option value="EUR" <?php selected( $currency_val, 'EUR' ); ?>>EUR</option>
                                    <option value="USD" <?php selected( $currency_val, 'USD' ); ?>>USD</option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Supplier billing currency. Used for future cost and export logic.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_lead_time_weeks">
                                    <?php esc_html_e( 'Lead time (weeks)', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="sop_supplier_lead_time_weeks"
                                       name="sop_supplier_lead_time_weeks"
                                       value="<?php echo esc_attr( $lead_time_val ); ?>"
                                       min="0"
                                       class="small-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Approximate time from placing order to goods arriving, in weeks.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_buffer_months_override">
                                    <?php esc_html_e( 'Stock buffer override (months)', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       step="0.1"
                                       id="sop_supplier_buffer_months_override"
                                       name="sop_supplier_buffer_months_override"
                                       value="<?php echo esc_attr( $buffer_override_val ); ?>"
                                       min="0"
                                       class="small-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Optional override for this supplier. Leave blank to use the global buffer months.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Active', 'sop' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="sop_supplier_is_active"
                                           value="1" <?php checked( 1, (int) $active_val ); ?> />
                                    <?php esc_html_e( 'Supplier is active and should be included in forecast/order workflows.', 'sop' ); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                if ( $editing ) {
                    submit_button( __( 'Update supplier', 'sop' ) );
                } else {
                    submit_button( __( 'Add supplier', 'sop' ) );
                }
                ?>
            </form>
        </div>
        <?php
    }
}

endif; // class_exists

// Bootstrap the admin settings class.
if ( is_admin() ) {
    new sop_Admin_Settings();
}

/**
 * Global helper to get Stock Order Plugin settings (with defaults).
 *
 * @return array
 */
function sop_get_settings() {

    if ( class_exists( 'sop_Admin_Settings' ) ) {
        return sop_Admin_Settings::get_settings();
    }

    // Fallback (should not normally happen if class exists).
    return array(
        'analysis_lookback_days'   => 365,
        'buffer_months_global'     => 6,
        'rmb_to_gbp_rate'          => '',
        'eur_to_gbp_rate'          => '',
        'usd_to_gbp_rate'          => '',
        'show_suggested_vs_max'    => 1,
    );
}

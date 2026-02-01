<?php
/**
 * Stock Order Plugin Ã¢â‚¬â€œ Phase 2 (Updated with USD)
 * Admin Settings & Supplier UI (General + Suppliers)
 * File version: 1.5.52
 * - Add System Status tab routing and use capability helper.
 * - UI copy: rename Stock Order plugin wording to Stock Order.
 * - Remove BOM/whitespace to prevent activation output.
 * - Add Data Export tab routing for settings.
 * - Show error when Data Export module is missing.
 * - Remove TEMP Shiny CSV importer tool.
 * - Add Carton CSV importer submenu.
 * - Add direct USDâ†’RMB base FX and swap FX/lead time rows.
 * - Adds supplier-level defaults for Pre-Order container settings.
 * - Adds company profile + supplier PI details for Rates & Dates view.
 * - Add per-supplier toggle to show Supplier SKUs column.
 * - Add Labels & Barcodes settings tab + supplier label size override.
 * - Remove supplier label size override fields (global labels only).
 * - Adds supplier holiday/shipping settings (multiple periods + units) for PO date suggestions.
 * - Use underscored supplier ID field name for supplier actions.
 * - Remove legacy settings heading/tabs (use grouped admin tabs only).
 * - Remove Company details block from General Settings (moved to PO Details).
 * - Register PO Details submenu so tab URL is accessible.
 * - Remove legacy dashboard import block.
 *
 * - Adds "Stock Order" top-level admin menu.
 * - General Settings tab stores global options in `sop_settings`.
 * - Suppliers tab manages rows in `sop_suppliers` via Phase 1 helpers.
 * - Implements global stock buffer months + per-supplier override (months).
 * - Supplier currency options: GBP, RMB, EUR, USD.
 * - Future phases: plugin will own its own productÃ¢â€ â€™supplier links and foreign prices
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
     * Constructor Ã¢â‚¬â€œ hook into admin.
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
        $capability     = function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce';
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

        // Carton CSV Importer.
        add_submenu_page(
            $dashboard_slug,
            __( 'Carton CSV Import', 'sop' ),
            __( 'Carton CSV Import', 'sop' ),
            $capability,
            'sop-carton-csv-import',
            'sop_render_carton_csv_import_page'
        );

        // PO Details.
        add_submenu_page(
            $dashboard_slug,
            __( 'PO Details', 'sop' ),
            __( 'PO Details', 'sop' ),
            $capability,
            'sop-po-details',
            'sop_render_po_details_page'
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

        // Labels & Barcodes settings (separate option).
        if ( function_exists( 'sop_labels_sanitize_settings' ) ) {
            register_setting(
                'sop_labels_settings_group',
                'sop_labels_settings',
                array(
                    'sanitize_callback' => 'sop_labels_sanitize_settings',
                )
            );
        }
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

        // RMB to GBP rate (allow string, we'll cast when using).
        if ( isset( $input['rmb_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['rmb_to_gbp_rate'] );
            $output['rmb_to_gbp_rate'] = $rate;
        }

        // EUR Ã¯Â¿Â½Ã¯Â¿Â½' GBP rate (allow string, we'll cast when using).
        if ( isset( $input['eur_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['eur_to_gbp_rate'] );
            $output['eur_to_gbp_rate'] = $rate;
        }

        // USD Ã¯Â¿Â½Ã¯Â¿Â½' GBP rate (allow string, we'll cast when using).
        if ( isset( $input['usd_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['usd_to_gbp_rate'] );
            $output['usd_to_gbp_rate'] = $rate;
        }

        if ( isset( $input['usd_to_rmb_rate'] ) ) {
            $rate = (float) $input['usd_to_rmb_rate'];
            if ( $rate < 0 ) {
                $rate = 0.0;
            }
            $output['usd_to_rmb_rate'] = $rate;
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
            'usd_to_rmb_rate'          => '',  // Optional, manual entry.
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
     * Main page renderer Ã¢â‚¬â€œ handles tab switching.
     */
    public function render_page() {
        if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        if ( ! in_array( $active_tab, array( 'general', 'suppliers', 'labels', 'export', 'status' ), true ) ) {
            $active_tab = 'general';
        }

        echo '<div class="wrap sop-wrap">';

        // Tab content.
        if ( 'suppliers' === $active_tab ) {
            $this->handle_supplier_actions();
            $this->render_suppliers_tab();
        } elseif ( 'export' === $active_tab && function_exists( 'sop_render_data_export_tab' ) ) {
            sop_render_data_export_tab();
        } elseif ( 'export' === $active_tab ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Data Export module not loaded (admin/data-export.php).', 'sop' ) . '</p></div>';
        } elseif ( 'status' === $active_tab && function_exists( 'sop_render_system_status_tab' ) ) {
            sop_render_system_status_tab();
        } elseif ( 'status' === $active_tab ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'System Status module not loaded (admin/system-status.php).', 'sop' ) . '</p></div>';
        } elseif ( 'labels' === $active_tab && function_exists( 'sop_labels_render_settings_tab' ) ) {
            sop_labels_render_settings_tab();
        } else {
            $this->render_general_tab();
        }

        echo '</div>'; // .wrap
    }

    /**
     * Render the Stock Order Dashboard.
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
            return;
        }

        global $wpdb;

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
        $overstock_rows             = array();
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

        if ( function_exists( 'sop_get_overstock_report' ) ) {
            $overstock_rows = sop_get_overstock_report(
                array(
                    'limit' => 50,
                )
            );
        }

        $overstock_summary = array(
            'count'       => 0,
            'total_units' => 0.0,
            'avg_pct'     => 0.0,
            'worst_sku'   => '',
            'worst_pct'   => 0.0,
        );

        if ( ! empty( $overstock_rows ) && is_array( $overstock_rows ) ) {
            $overstock_summary['count'] = count( $overstock_rows );

            $sum_pct = 0.0;

            foreach ( $overstock_rows as $idx => $row ) {
                $units = isset( $row['over_units'] ) ? (float) $row['over_units'] : 0.0;
                $pct   = isset( $row['over_pct'] ) ? (float) $row['over_pct'] : 0.0;

                if ( $units > 0 ) {
                    $overstock_summary['total_units'] += $units;
                }

                if ( $pct > 0 ) {
                    $sum_pct += $pct;
                }

                if ( 0 === $idx ) {
                    $overstock_summary['worst_sku'] = isset( $row['sku'] ) ? (string) $row['sku'] : '';
                    $overstock_summary['worst_pct'] = $pct;
                }
            }

            if ( $overstock_summary['count'] > 0 && $sum_pct > 0 ) {
                $overstock_summary['avg_pct'] = $sum_pct / $overstock_summary['count'];
            }
        }

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

            <form method="get" class="sop-dashboard-filter-form sop-dashboard-filter-row">
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

                <label for="sop-dashboard-category-select"><?php esc_html_e( 'Category filter:', 'sop' ); ?></label>
                <div class="sop-dashboard-filter-select sop-dashboard-filter-select-wrap">
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
                            <div class="sop-dashboard-metric-label"><?php esc_html_e( 'Products', 'sop' ); ?></div>
                            <div class="sop-dashboard-metric-value"><?php echo esc_html( number_format_i18n( $total_products, 0 ) ); ?></div>
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

                <div class="sop-dashboard-card sop-dashboard-card-overstock">
                    <h2 class="sop-dashboard-card-title"><?php esc_html_e( 'Overstock report', 'sop' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Snapshot of products currently above their buffer-based target stock. Use this to spot SKUs that may need promotions or further investigation.', 'sop' ); ?>
                    </p>

                    <?php if ( empty( $overstock_rows ) ) : ?>
                        <p><?php esc_html_e( 'No overstock detected within the current lookback window.', 'sop' ); ?></p>
                    <?php else : ?>
                        <ul class="sop-overstock-summary-list">
                            <li>
                                <strong><?php esc_html_e( 'Overstocked SKUs:', 'sop' ); ?></strong>
                                <?php echo esc_html( number_format_i18n( $overstock_summary['count'], 0 ) ); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e( 'Total overstock units:', 'sop' ); ?></strong>
                                <?php echo esc_html( number_format_i18n( $overstock_summary['total_units'], 1 ) ); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e( 'Average % over target:', 'sop' ); ?></strong>
                                <?php echo esc_html( number_format_i18n( $overstock_summary['avg_pct'], 1 ) ); ?>%
                            </li>
                            <?php if ( ! empty( $overstock_summary['worst_sku'] ) ) : ?>
                                <li>
                                    <strong><?php esc_html_e( 'Worst offender:', 'sop' ); ?></strong>
                                    <?php
                                    echo esc_html( $overstock_summary['worst_sku'] );
                                    echo ' (' . esc_html( number_format_i18n( $overstock_summary['worst_pct'], 1 ) ) . '%)';
                                    ?>
                                </li>
                            <?php endif; ?>
                        </ul>

                        <p>
                            <button type="button" class="button button-primary sop-overstock-open">
                                <?php esc_html_e( 'See products', 'sop' ); ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! empty( $overstock_rows ) ) : ?>
                <div id="sop-overstock-modal" class="sop-overstock-modal sop-hidden" aria-hidden="true">
                    <div class="sop-overstock-modal-backdrop"></div>
                    <div class="sop-overstock-modal-inner" role="dialog" aria-modal="true" aria-labelledby="sop-overstock-modal-title">
                        <button type="button" class="button-link sop-overstock-close" aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">&times;</button>
                        <h2 id="sop-overstock-modal-title"><?php esc_html_e( 'Overstock products', 'sop' ); ?></h2>
                        <p class="description">
                            <?php esc_html_e( 'Top overstocked products by percentage above target stock. Use this list to decide where to run promotions or adjust pricing.', 'sop' ); ?>
                        </p>

                        <div class="sop-overstock-table-wrapper">
                            <table class="widefat striped sop-overstock-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Image', 'sop' ); ?></th>
                                        <th><?php esc_html_e( 'Location', 'sop' ); ?></th>
                                        <th><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                                        <th><?php esc_html_e( 'Stock', 'sop' ); ?></th>
                                        <th><?php esc_html_e( 'Overstock (units)', 'sop' ); ?></th>
                                        <th><?php esc_html_e( '% over target', 'sop' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $overstock_index = 0;
                                    foreach ( $overstock_rows as $over_row ) :
                                        $overstock_index++;
                                        $extra_class = ( $overstock_index > 10 ) ? ' sop-overstock-extra sop-hidden' : '';
                                        ?>
                                        <tr class="<?php echo esc_attr( trim( $extra_class ) ); ?>">
                                            <td class="sop-overstock-image-cell"><?php echo $over_row['image_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                            <td><?php echo esc_html( $over_row['location'] ); ?></td>
                                            <td><?php echo esc_html( $over_row['sku'] ); ?></td>
                                            <td><?php echo esc_html( number_format_i18n( $over_row['stock'], 0 ) ); ?></td>
                                            <td><?php echo esc_html( number_format_i18n( $over_row['over_units'], 1 ) ); ?></td>
                                            <td><?php echo esc_html( number_format_i18n( $over_row['over_pct'], 1 ) ); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ( count( $overstock_rows ) > 10 ) : ?>
                                <p>
                                    <a href="#" id="sop-overstock-toggle">
                                        <?php esc_html_e( 'Show more', 'sop' ); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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
                align-items: center;
                gap: 12px;
                max-width: 100%;
            }

            .sop-dashboard-filter-row {
                display: flex;
                align-items: center;
                gap: 12px;
                width: 100%;
            }

            .sop-dashboard-filter-row label {
                margin: 0;
                white-space: nowrap;
            }

            .sop-dashboard-filter-row .button {
                white-space: nowrap;
            }

            .sop-dashboard-filter-select-wrap {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .sop-dashboard-filter-select-wrap .select2-container {
                width: 100% !important;
            }

            .sop-dashboard-filter-select-wrap .select2-selection--multiple {
                min-height: 36px;
                padding-top: 4px;
                padding-bottom: 6px;
                box-sizing: border-box;
            }

            .sop-dashboard-filter-select-wrap .select2-selection--multiple .select2-selection__rendered {
                display: flex;
                flex-wrap: nowrap;
                gap: 4px;
                overflow-x: auto;
                overflow-y: hidden;
            }

            .sop-dashboard-filter-select-wrap .select2-selection--multiple .select2-selection__choice {
                margin-top: 0;
                padding: 2px 6px;
            }

            .sop-dashboard-filter-select-wrap .select2-search--dropdown {
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
                font-weight: 600;
                margin-bottom: 2px;
            }

            .sop-dashboard-metric-value {
                font-size: 20px;
                font-weight: 600;
                margin-top: 0;
                margin-bottom: 2px;
            }

            .sop-dashboard-metric-meta {
                margin-top: 0;
                font-size: 11px;
                color: #666666;
            }

            .sop-dashboard-metric-meta-label {
                text-transform: uppercase;
                letter-spacing: 0.03em;
                margin-right: 4px;
            }

            .sop-dashboard-metric-meta-value {
                font-weight: 500;
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

            .sop-overstock-modal {
                position: fixed;
                inset: 0;
                z-index: 100000;
            }

            .sop-overstock-modal-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.4);
            }

            .sop-overstock-modal-inner {
                position: absolute;
                top: 5%;
                left: 50%;
                transform: translateX(-50%);
                max-width: 960px;
                width: 95%;
                max-height: 90%;
                overflow: auto;
                background: #fff;
                padding: 20px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
            }

            .sop-overstock-close {
                float: right;
                font-size: 20px;
                line-height: 1;
                margin: 0;
            }

            .sop-overstock-image-cell img {
                max-width: 60px;
                height: auto;
            }

            .sop-overstock-summary-list {
                margin: 0 0 10px;
                padding-left: 18px;
            }

            .sop-overstock-summary-list li {
                margin: 2px 0;
            }

            .sop-hidden {
                display: none;
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

                $('#sop-overstock-toggle').on('click', function(e) {
                    e.preventDefault();

                    var $extraRows = $('.sop-overstock-extra');
                    if ( ! $extraRows.length ) {
                        return;
                    }

                    var isHidden = $extraRows.first().hasClass('sop-hidden');

                    if ( isHidden ) {
                        $extraRows.removeClass('sop-hidden');
                        $( this ).text('<?php echo esc_js( __( 'Show less', 'sop' ) ); ?>');
                    } else {
                        $extraRows.addClass('sop-hidden');
                        $( this ).text('<?php echo esc_js( __( 'Show more', 'sop' ) ); ?>');
                    }
                });

                $('.sop-overstock-open').on('click', function(e) {
                    e.preventDefault();
                    $('#sop-overstock-modal').removeClass('sop-hidden').attr('aria-hidden', 'false');
                });

                $( document ).on( 'click', '.sop-overstock-close, .sop-overstock-modal-backdrop', function(e) {
                    e.preventDefault();
                    $('#sop-overstock-modal').addClass('sop-hidden').attr('aria-hidden', 'true');
                } );

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
                                <?php esc_html_e( 'RMB to GBP rate (optional)', 'sop' ); ?>
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
                            <label for="sop_usd_to_rmb_rate">
                                <?php esc_html_e( 'USD to RMB rate (optional)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sop_usd_to_rmb_rate"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[usd_to_rmb_rate]"
                                   value="<?php echo esc_attr( isset( $settings['usd_to_rmb_rate'] ) ? $settings['usd_to_rmb_rate'] : '' ); ?>"
                                   class="small-text"
                                   size="20"
                                   style="width: 8em;" />
                            <p class="description">
                                <?php esc_html_e( 'Base USDâ†’RMB rate. If set, this is used as the primary FX for USDâ†”RMB; leave blank to derive it from RMBâ†’GBP and USDâ†’GBP.', 'sop' ); ?>
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
                                <?php esc_html_e( 'Display suggested qty alongside max_order_qty_per_month on forecast screens.', 'sop' ); ?>
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

        if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
            return;
        }

        check_admin_referer( 'sop_save_supplier', 'sop_supplier_nonce' );

        $action = sanitize_key( wp_unslash( $_POST['sop_supplier_action'] ) );
        if ( 'save' !== $action ) {
            return;
        }

        $id              = isset( $_POST['_sop_supplier_id'] ) ? (int) $_POST['_sop_supplier_id'] : 0;
        $name            = isset( $_POST['sop_supplier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_name'] ) ) : '';
        $slug            = isset( $_POST['sop_supplier_slug'] ) ? sanitize_title( wp_unslash( $_POST['sop_supplier_slug'] ) ) : '';
        $currency = isset( $_POST['sop_supplier_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_currency'] ) ) : 'GBP';
        $lead_time_value = isset( $_POST['sop_supplier_lead_time_value'] ) ? (int) $_POST['sop_supplier_lead_time_value'] : 0;
        $lead_time_unit  = isset( $_POST['sop_supplier_lead_time_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_lead_time_unit'] ) ) : 'weeks';
        if ( ! in_array( $lead_time_unit, array( 'days', 'weeks' ), true ) ) {
            $lead_time_unit = 'weeks';
        }
        if ( $lead_time_value < 0 ) {
            $lead_time_value = 0;
        }
        $lead_time_weeks = 0;
        if ( 'weeks' === $lead_time_unit ) {
            $lead_time_weeks = $lead_time_value;
        } elseif ( 'days' === $lead_time_unit ) {
            $lead_time_weeks = ( $lead_time_value > 0 ) ? (int) ceil( $lead_time_value / 7 ) : 0;
        }
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

        // Lead time value/unit for supplier (stored in settings_json; lead_time_weeks saved separately).
        if ( $lead_time_value > 0 ) {
            $settings_array['lead_time_value'] = $lead_time_value;
            $settings_array['lead_time_unit']  = $lead_time_unit;
        } else {
            unset( $settings_array['lead_time_value'], $settings_array['lead_time_unit'] );
        }

        // Pre-Order container defaults.
        $container_type_raw = isset( $_POST['sop_supplier_preorder_container_type'] )
            ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_preorder_container_type'] ) )
            : '';
        $allowed_container_types = array( '', '20ft', '40ft', '40ft_hc' );
        if ( ! in_array( $container_type_raw, $allowed_container_types, true ) ) {
            $container_type_raw = '';
        }

        if ( '' === $container_type_raw ) {
            unset( $settings_array['preorder_default_container_type'] );
        } else {
            $settings_array['preorder_default_container_type'] = $container_type_raw;
        }

        $pallet_default = ! empty( $_POST['sop_supplier_preorder_pallet_layer'] ) ? 1 : 0;
        if ( $pallet_default ) {
            $settings_array['preorder_default_pallet_layer'] = 1;
        } else {
            unset( $settings_array['preorder_default_pallet_layer'] );
        }

        $allowance_raw = isset( $_POST['sop_supplier_preorder_allowance'] )
            ? trim( wp_unslash( $_POST['sop_supplier_preorder_allowance'] ) )
            : '';

        if ( '' === $allowance_raw ) {
            unset( $settings_array['preorder_default_container_allowance'] );
        } else {
            $allowance_val = (float) $allowance_raw;
            if ( $allowance_val < -50 ) {
                $allowance_val = -50;
            } elseif ( $allowance_val > 50 ) {
                $allowance_val = 50;
            }
            $settings_array['preorder_default_container_allowance'] = $allowance_val;
        }

        $show_supplier_skus_column = ! empty( $_POST['sop_show_supplier_skus_column'] ) ? 1 : 0;
        if ( $show_supplier_skus_column ) {
            $settings_array['show_supplier_skus_column'] = 1;
        } else {
            unset( $settings_array['show_supplier_skus_column'] );
        }

        // Supplier PI / Rates & Dates defaults.
        $pi_company_name   = isset( $_POST['sop_supplier_pi_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_pi_company_name'] ) ) : '';
        $pi_company_address = isset( $_POST['sop_supplier_pi_company_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sop_supplier_pi_company_address'] ) ) : '';
        $pi_company_phone  = isset( $_POST['sop_supplier_pi_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_pi_phone'] ) ) : '';
        $pi_company_email  = isset( $_POST['sop_supplier_pi_email'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_pi_email'] ) ) : '';
        $pi_contact_name   = isset( $_POST['sop_supplier_pi_contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_pi_contact_name'] ) ) : '';
        $pi_bank_details   = isset( $_POST['sop_supplier_pi_bank_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sop_supplier_pi_bank_details'] ) ) : '';
        $pi_payment_terms  = isset( $_POST['sop_supplier_pi_payment_terms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sop_supplier_pi_payment_terms'] ) ) : '';

        if ( '' === $pi_company_name ) {
            unset( $settings_array['pi_company_name'] );
        } else {
            $settings_array['pi_company_name'] = $pi_company_name;
        }

        if ( '' === $pi_company_address ) {
            unset( $settings_array['pi_company_address'] );
        } else {
            $settings_array['pi_company_address'] = $pi_company_address;
        }

        if ( '' === $pi_company_phone ) {
            unset( $settings_array['pi_company_phone'] );
        } else {
            $settings_array['pi_company_phone'] = $pi_company_phone;
        }

        if ( '' === $pi_company_email ) {
            unset( $settings_array['pi_company_email'] );
        } else {
            $settings_array['pi_company_email'] = $pi_company_email;
        }

        if ( '' === $pi_contact_name ) {
            unset( $settings_array['pi_contact_name'] );
        } else {
            $settings_array['pi_contact_name'] = $pi_contact_name;
        }

        if ( '' === $pi_bank_details ) {
            unset( $settings_array['pi_bank_details'] );
        } else {
            $settings_array['pi_bank_details'] = $pi_bank_details;
        }

        if ( '' === $pi_payment_terms ) {
            unset( $settings_array['pi_payment_terms'] );
        } else {
            $settings_array['pi_payment_terms'] = $pi_payment_terms;
        }

        // Supplier holiday + shipping settings (multiple periods + units).
        $holiday_start_days   = isset( $_POST['sop_supplier_holiday_start_day'] ) && is_array( $_POST['sop_supplier_holiday_start_day'] ) ? array_map( 'intval', $_POST['sop_supplier_holiday_start_day'] ) : array();
        $holiday_start_months = isset( $_POST['sop_supplier_holiday_start_month'] ) && is_array( $_POST['sop_supplier_holiday_start_month'] ) ? array_map( 'intval', $_POST['sop_supplier_holiday_start_month'] ) : array();
        $holiday_end_days     = isset( $_POST['sop_supplier_holiday_end_day'] ) && is_array( $_POST['sop_supplier_holiday_end_day'] ) ? array_map( 'intval', $_POST['sop_supplier_holiday_end_day'] ) : array();
        $holiday_end_months   = isset( $_POST['sop_supplier_holiday_end_month'] ) && is_array( $_POST['sop_supplier_holiday_end_month'] ) ? array_map( 'intval', $_POST['sop_supplier_holiday_end_month'] ) : array();

        $holiday_periods = array();
        $max_holidays    = max(
            count( $holiday_start_days ),
            count( $holiday_start_months ),
            count( $holiday_end_days ),
            count( $holiday_end_months )
        );

        for ( $i = 0; $i < $max_holidays; $i++ ) {
            $sd = isset( $holiday_start_days[ $i ] ) ? (int) $holiday_start_days[ $i ] : 0;
            $sm = isset( $holiday_start_months[ $i ] ) ? (int) $holiday_start_months[ $i ] : 0;
            $ed = isset( $holiday_end_days[ $i ] ) ? (int) $holiday_end_days[ $i ] : 0;
            $em = isset( $holiday_end_months[ $i ] ) ? (int) $holiday_end_months[ $i ] : 0;

            if ( $sd < 1 || $sd > 31 || $sm < 1 || $sm > 12 || $ed < 1 || $ed > 31 || $em < 1 || $em > 12 ) {
                continue;
            }

            $holiday_periods[] = array(
                'start_day'   => $sd,
                'start_month' => $sm,
                'end_day'     => $ed,
                'end_month'   => $em,
            );
        }

        if ( ! empty( $holiday_periods ) ) {
            $settings_array['holiday_periods'] = $holiday_periods;
        } else {
            unset( $settings_array['holiday_periods'] );
        }

        unset(
            $settings_array['holiday_start_day'],
            $settings_array['holiday_start_month'],
            $settings_array['holiday_end_day'],
            $settings_array['holiday_end_month']
        );

        $shipping_value_raw = isset( $_POST['sop_supplier_shipping_value'] ) ? (int) $_POST['sop_supplier_shipping_value'] : 0;
        $shipping_unit_raw  = isset( $_POST['sop_supplier_shipping_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_shipping_unit'] ) ) : 'days';
        $fx_adjust_percent  = 0.0;
        if ( isset( $_POST['sop_supplier_fx_adjust_percent'] ) ) {
            $fx_adjust_percent = (float) wp_unslash( $_POST['sop_supplier_fx_adjust_percent'] );
            if ( $fx_adjust_percent < -20 ) {
                $fx_adjust_percent = -20;
            } elseif ( $fx_adjust_percent > 20 ) {
                $fx_adjust_percent = 20;
            }
        }

        if ( $shipping_value_raw < 0 ) {
            $shipping_value_raw = 0;
        }

        $shipping_unit = in_array( $shipping_unit_raw, array( 'days', 'weeks' ), true ) ? $shipping_unit_raw : 'days';

        if ( $shipping_value_raw > 0 ) {
            $settings_array['shipping_value'] = $shipping_value_raw;
            $settings_array['shipping_unit']  = $shipping_unit;
            $settings_array['shipping_days']  = ( 'weeks' === $shipping_unit )
                ? ( $shipping_value_raw * 7 )
                : $shipping_value_raw;
        } else {
            unset( $settings_array['shipping_value'], $settings_array['shipping_unit'], $settings_array['shipping_days'] );
        }

        // FX adjustment percent.
        $settings_array['fx_adjust_percent'] = $fx_adjust_percent;

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
        // Handle company profile save.
        if ( ! empty( $_POST['sop_company_profile_action'] ) && 'save' === $_POST['sop_company_profile_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            check_admin_referer( 'sop_company_profile_save', 'sop_company_profile_nonce' );

            $profile_data = array(
                'company_name'       => isset( $_POST['sop_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_name'] ) ) : '',
                'billing_address'    => isset( $_POST['sop_company_billing_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sop_company_billing_address'] ) ) : '',
                'shipping_address'   => isset( $_POST['sop_company_shipping_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sop_company_shipping_address'] ) ) : '',
                'email'              => isset( $_POST['sop_company_email'] ) ? sanitize_email( wp_unslash( $_POST['sop_company_email'] ) ) : '',
                'phone_landline'     => isset( $_POST['sop_company_phone_landline'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_phone_landline'] ) ) : '',
                'phone_mobile'       => isset( $_POST['sop_company_phone_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_phone_mobile'] ) ) : '',
                'company_reg_number' => isset( $_POST['sop_company_crn'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_crn'] ) ) : '',
                'vat_number'         => isset( $_POST['sop_company_vat'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_vat'] ) ) : '',
            );

            if ( function_exists( 'sop_update_company_profile' ) ) {
                sop_update_company_profile( $profile_data );
                add_settings_error( 'sop_company_profile', 'sop_company_profile_saved', __( 'Company details saved.', 'sop' ), 'updated' );
            }
        }

        $company_profile = function_exists( 'sop_get_company_profile' ) ? sop_get_company_profile() : array();
        $company_name_val = isset( $company_profile['company_name'] ) ? $company_profile['company_name'] : '';
        $company_billing_val = isset( $company_profile['billing_address'] ) ? $company_profile['billing_address'] : '';
        $company_shipping_val = isset( $company_profile['shipping_address'] ) ? $company_profile['shipping_address'] : '';
        $company_email_val = isset( $company_profile['email'] ) ? $company_profile['email'] : '';
        $company_phone_landline_val = isset( $company_profile['phone_landline'] ) ? $company_profile['phone_landline'] : '';
        $company_phone_mobile_val = isset( $company_profile['phone_mobile'] ) ? $company_profile['phone_mobile'] : '';
        $company_crn_val = isset( $company_profile['company_reg_number'] ) ? $company_profile['company_reg_number'] : '';
        $company_vat_val = isset( $company_profile['vat_number'] ) ? $company_profile['vat_number'] : '';

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

            <p><?php esc_html_e( 'Manage suppliers used by Stock Order. Each supplier can have its own lead time, currency, and optional stock buffer override.', 'sop' ); ?></p>

            <h3><?php esc_html_e( 'Supplier list', 'sop' ); ?></h3>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Currency', 'sop' ); ?></th>
                                <th><?php esc_html_e( 'Lead time', 'sop' ); ?></th>
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
                                <td>
                                    <?php
                                    $lead_time_display = '';
                                    $lead_time_unit_display = 'weeks';
                                    if ( $settings_json ) {
                                        $settings_arr    = $settings_json ? json_decode( $settings_json, true ) : array();
                                        if ( is_array( $settings_arr ) && isset( $settings_arr['lead_time_value'] ) && isset( $settings_arr['lead_time_unit'] ) && in_array( $settings_arr['lead_time_unit'], array( 'days', 'weeks' ), true ) ) {
                                            $lt_val = (float) $settings_arr['lead_time_value'];
                                            $lt_unit = $settings_arr['lead_time_unit'];
                                            if ( $lt_val > 0 ) {
                                                $lead_time_unit_display = ( 'days' === $lt_unit ) ? 'days' : 'weeks';
                                                $lead_time_display      = $lt_val . ' ' . $lead_time_unit_display;
                                            }
                                        }
                                    }
                                    if ( '' === $lead_time_display ) {
                                        $lead_time_display = ( isset( $supplier->lead_time_weeks ) ? (float) $supplier->lead_time_weeks : 0 ) . ' ' . __( 'weeks', 'sop' );
                                    }
                                    echo esc_html( $lead_time_display );
                                    ?>
                                </td>
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
            $lead_time_value_val = 0;
            $lead_time_unit_val  = 'weeks';
            $active_val          = 1;
            $buffer_override_val = '';
            $preorder_container_type_val      = '';
            $preorder_pallet_layer_val        = 0;
            $preorder_container_allowance_val = '';
            $pi_company_name_val              = '';
            $pi_company_address_val           = '';
            $pi_company_phone_val             = '';
            $pi_company_email_val             = '';
            $pi_contact_name_val              = '';
            $pi_bank_details_val              = '';
            $pi_payment_terms_val             = '';
            $holiday_periods_val              = array(
                array(
                    'start_day'   => '',
                    'start_month' => 0,
                    'end_day'     => '',
                    'end_month'   => 0,
                ),
            );
            $shipping_value_val = 0;
            $shipping_unit_val  = 'days';
            $fx_adjust_percent_val = 0.0;
            $show_supplier_skus_column_val = 0;

            if ( $editing ) {
                $editing_id_val    = (int) $editing->id;
                $name_val          = $editing->name;
                $slug_val          = $editing->slug;
                $currency_val      = $editing->currency;
                $lead_time_value_val = isset( $editing->lead_time_weeks ) ? (float) $editing->lead_time_weeks : 0;
                $lead_time_unit_val  = 'weeks';
                $active_val          = (int) $editing->is_active;
                $settings_arr = $editing->settings_json ? json_decode( $editing->settings_json, true ) : array();
                if ( is_array( $settings_arr ) && isset( $settings_arr['buffer_months_override'] ) ) {
                    $buffer_override_val = (float) $settings_arr['buffer_months_override'];
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'lead_time_value', $settings_arr ) ) {
                    $lead_time_value_val = (float) $settings_arr['lead_time_value'];
                }
                if ( is_array( $settings_arr ) && isset( $settings_arr['lead_time_unit'] ) && in_array( $settings_arr['lead_time_unit'], array( 'days', 'weeks' ), true ) ) {
                    $lead_time_unit_val = $settings_arr['lead_time_unit'];
                }
                if ( is_array( $settings_arr ) && ! empty( $settings_arr['preorder_default_container_type'] ) ) {
                    $preorder_container_type_val = (string) $settings_arr['preorder_default_container_type'];
                }
                if ( is_array( $settings_arr ) && ! empty( $settings_arr['preorder_default_pallet_layer'] ) ) {
                    $preorder_pallet_layer_val = 1;
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'preorder_default_container_allowance', $settings_arr ) ) {
                    $preorder_container_allowance_val = (float) $settings_arr['preorder_default_container_allowance'];
                    if ( $preorder_container_allowance_val < -50 ) {
                        $preorder_container_allowance_val = -50;
                    } elseif ( $preorder_container_allowance_val > 50 ) {
                        $preorder_container_allowance_val = 50;
                    }
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'pi_company_name', $settings_arr ) ) {
                    $pi_company_name_val = (string) $settings_arr['pi_company_name'];
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'pi_company_address', $settings_arr ) ) {
                    $pi_company_address_val = (string) $settings_arr['pi_company_address'];
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'pi_company_phone', $settings_arr ) ) {
                    $pi_company_phone_val = (string) $settings_arr['pi_company_phone'];
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'pi_company_email', $settings_arr ) ) {
                    $pi_company_email_val = (string) $settings_arr['pi_company_email'];
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'pi_contact_name', $settings_arr ) ) {
                    $pi_contact_name_val = (string) $settings_arr['pi_contact_name'];
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'pi_bank_details', $settings_arr ) ) {
                    $pi_bank_details_val = (string) $settings_arr['pi_bank_details'];
                }
                if ( is_array( $settings_arr ) && array_key_exists( 'pi_payment_terms', $settings_arr ) ) {
                    $pi_payment_terms_val = (string) $settings_arr['pi_payment_terms'];
                }
                if ( is_array( $settings_arr ) && isset( $settings_arr['holiday_periods'] ) && is_array( $settings_arr['holiday_periods'] ) ) {
                    $holiday_periods_val = array();
                    foreach ( $settings_arr['holiday_periods'] as $period ) {
                        $holiday_periods_val[] = array(
                            'start_day'   => isset( $period['start_day'] ) ? (int) $period['start_day'] : 0,
                            'start_month' => isset( $period['start_month'] ) ? (int) $period['start_month'] : 0,
                            'end_day'     => isset( $period['end_day'] ) ? (int) $period['end_day'] : 0,
                            'end_month'   => isset( $period['end_month'] ) ? (int) $period['end_month'] : 0,
                        );
                    }
                } else {
                    $legacy_sd = isset( $settings_arr['holiday_start_day'] ) ? (int) $settings_arr['holiday_start_day'] : 0;
                    $legacy_sm = isset( $settings_arr['holiday_start_month'] ) ? (int) $settings_arr['holiday_start_month'] : 0;
                    $legacy_ed = isset( $settings_arr['holiday_end_day'] ) ? (int) $settings_arr['holiday_end_day'] : 0;
                    $legacy_em = isset( $settings_arr['holiday_end_month'] ) ? (int) $settings_arr['holiday_end_month'] : 0;

                    if ( $legacy_sd && $legacy_sm && $legacy_ed && $legacy_em ) {
                        $holiday_periods_val = array(
                            array(
                                'start_day'   => $legacy_sd,
                                'start_month' => $legacy_sm,
                                'end_day'     => $legacy_ed,
                                'end_month'   => $legacy_em,
                            ),
                        );
                    }
                }

                if ( empty( $holiday_periods_val ) ) {
                    $holiday_periods_val = array(
                        array(
                            'start_day'   => '',
                            'start_month' => 0,
                            'end_day'     => '',
                            'end_month'   => 0,
                        ),
                    );
                }

                if ( is_array( $settings_arr ) && isset( $settings_arr['shipping_value'] ) ) {
                    $shipping_value_val = (int) $settings_arr['shipping_value'];
                } elseif ( is_array( $settings_arr ) && isset( $settings_arr['shipping_days'] ) ) {
                    $shipping_value_val = (int) $settings_arr['shipping_days'];
                }

                if ( is_array( $settings_arr ) && isset( $settings_arr['shipping_unit'] ) && in_array( $settings_arr['shipping_unit'], array( 'days', 'weeks' ), true ) ) {
                    $shipping_unit_val = $settings_arr['shipping_unit'];
                }
                if ( is_array( $settings_arr ) && isset( $settings_arr['fx_adjust_percent'] ) ) {
                    $fx_adjust_percent_val = (float) $settings_arr['fx_adjust_percent'];
                }
                if ( is_array( $settings_arr ) && ! empty( $settings_arr['show_supplier_skus_column'] ) ) {
                    $show_supplier_skus_column_val = 1;
                }
            }
            ?>

            <form method="post">
                <?php wp_nonce_field( 'sop_save_supplier', 'sop_supplier_nonce' ); ?>
                <input type="hidden" name="sop_supplier_action" value="save" />
                <input type="hidden" name="_sop_supplier_id" value="<?php echo esc_attr( $editing_id_val ); ?>" />

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
                                <label for="sop_supplier_fx_adjust_percent">
                                    <?php esc_html_e( 'FX adjustment (%)', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="sop_supplier_fx_adjust_percent"
                                       name="sop_supplier_fx_adjust_percent"
                                       class="small-text"
                                       step="0.1"
                                       min="-20"
                                       max="20"
                                       value="<?php echo esc_attr( $fx_adjust_percent_val ); ?>" />
                                <p class="description">
                                    <?php esc_html_e( 'Positive values reduce the base USDâ†’RMB rate by this %, e.g. 0.5 â†’ base 7.287 becomes ~7.25 to cover the supplierâ€™s bank FX fees.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_show_supplier_skus_column">
                                    <?php esc_html_e( 'Show Supplier SKUs column', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           id="sop_show_supplier_skus_column"
                                           name="sop_show_supplier_skus_column"
                                           value="1" <?php checked( $show_supplier_skus_column_val, 1 ); ?> />
                                    <?php esc_html_e( 'Display Supplier SKUs column on Pre-Order / Goods-In and exports for this supplier.', 'sop' ); ?>
                                </label>
                            </td>
                        </tr>


                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_lead_time_value">
                                    <?php esc_html_e( 'Lead time', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="sop_supplier_lead_time_value"
                                       name="sop_supplier_lead_time_value"
                                       value="<?php echo esc_attr( $lead_time_value_val ); ?>"
                                       min="0"
                                       class="small-text" />
                                <select id="sop_supplier_lead_time_unit" name="sop_supplier_lead_time_unit">
                                    <option value="days" <?php selected( $lead_time_unit_val, 'days' ); ?>><?php esc_html_e( 'Days', 'sop' ); ?></option>
                                    <option value="weeks" <?php selected( $lead_time_unit_val, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'sop' ); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Approximate time from placing order to goods arriving.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Holiday periods', 'sop' ); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e( 'Holiday periods', 'sop' ); ?></legend>
                                    <table class="widefat striped" id="sop-supplier-holiday-periods">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'From (day / month)', 'sop' ); ?></th>
                                                <th><?php esc_html_e( 'To (day / month)', 'sop' ); ?></th>
                                                <th class="column-actions"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $holiday_periods_val as $period ) : ?>
                                                <?php
                                                $start_day_display = ( isset( $period['start_day'] ) && (int) $period['start_day'] >= 1 ) ? (int) $period['start_day'] : '';
                                                $end_day_display   = ( isset( $period['end_day'] ) && (int) $period['end_day'] >= 1 ) ? (int) $period['end_day'] : '';
                                                ?>
                                                <tr class="sop-supplier-holiday-row">
                                                    <td>
                                                        <input type="number"
                                                               name="sop_supplier_holiday_start_day[]"
                                                               class="small-text"
                                                               min="1"
                                                               max="31"
                                                               value="<?php echo esc_attr( $start_day_display ); ?>" />
                                                        <select name="sop_supplier_holiday_start_month[]">
                                                            <option value="0"><?php esc_html_e( 'Month', 'sop' ); ?></option>
                                                            <?php
                                                            for ( $m = 1; $m <= 12; $m++ ) {
                                                                printf(
                                                                    '<option value="%1$d"%2$s>%3$s</option>',
                                                                    $m,
                                                                    selected( $period['start_month'], $m, false ),
                                                                    esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) )
                                                                );
                                                            }
                                                            ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="number"
                                                               name="sop_supplier_holiday_end_day[]"
                                                               class="small-text"
                                                               min="1"
                                                               max="31"
                                                               value="<?php echo esc_attr( $end_day_display ); ?>" />
                                                        <select name="sop_supplier_holiday_end_month[]">
                                                            <option value="0"><?php esc_html_e( 'Month', 'sop' ); ?></option>
                                                            <?php
                                                            for ( $m = 1; $m <= 12; $m++ ) {
                                                                printf(
                                                                    '<option value="%1$d"%2$s>%3$s</option>',
                                                                    $m,
                                                                    selected( $period['end_month'], $m, false ),
                                                                    esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) )
                                                                );
                                                            }
                                                            ?>
                                                        </select>
                                                    </td>
                                                    <td class="column-actions">
                                                        <button type="button" class="button-link sop-supplier-holiday-remove">&times;</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <p>
                                        <button type="button" class="button sop-supplier-holiday-add">
                                            <?php esc_html_e( 'Add extra dates', 'sop' ); ?>
                                        </button>
                                    </p>

                                    <p class="description">
                                        <?php esc_html_e( 'Recurring holiday periods (no year). Used when calculating Purchase Order dates if the lead window overlaps. Add multiple ranges for New Year, Golden Week, etc.', 'sop' ); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_shipping_value">
                                    <?php esc_html_e( 'Shipping time', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="sop_supplier_shipping_value"
                                       name="sop_supplier_shipping_value"
                                       class="small-text"
                                       min="0"
                                       value="<?php echo esc_attr( $shipping_value_val ); ?>" />
                                <select id="sop_supplier_shipping_unit" name="sop_supplier_shipping_unit">
                                    <option value="days"<?php selected( $shipping_unit_val, 'days' ); ?>><?php esc_html_e( 'Days', 'sop' ); ?></option>
                                    <option value="weeks"<?php selected( $shipping_unit_val, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'sop' ); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Typical time from container load to goods arriving in the UK. Used to suggest container load and ETA dates on Purchase Orders.', 'sop' ); ?>
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
                                <label for="sop_supplier_preorder_container_type">
                                    <?php esc_html_e( 'Default Pre-Order container', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="sop_supplier_preorder_container_type" name="sop_supplier_preorder_container_type">
                                    <option value=""><?php esc_html_e( 'None (no default)', 'sop' ); ?></option>
                                    <option value="20ft" <?php selected( $preorder_container_type_val, '20ft' ); ?>>20&#39; (33.2 CBM)</option>
                                    <option value="40ft" <?php selected( $preorder_container_type_val, '40ft' ); ?>>40&#39; (67.7 CBM)</option>
                                    <option value="40ft_hc" <?php selected( $preorder_container_type_val, '40ft_hc' ); ?>>40&#39; HQ (76.3 CBM)</option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Optional default container type when starting a new Pre-Order sheet for this supplier.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_preorder_pallet_layer">
                                    <?php esc_html_e( 'Default 150mm pallet layer', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           id="sop_supplier_preorder_pallet_layer"
                                           name="sop_supplier_preorder_pallet_layer"
                                           value="1" <?php checked( 1, (int) $preorder_pallet_layer_val ); ?> />
                                    <?php esc_html_e( 'Apply the 150mm pallet layer by default on new Pre-Order sheets for this supplier.', 'sop' ); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_preorder_allowance">
                                    <?php esc_html_e( 'Default container allowance (%)', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="sop_supplier_preorder_allowance"
                                       name="sop_supplier_preorder_allowance"
                                       value="<?php echo esc_attr( $preorder_container_allowance_val ); ?>"
                                       step="1"
                                       min="-50"
                                       max="50"
                                       class="small-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Allowance for container planning on new Pre-Order sheets (e.g. 5 = 5% spare, -5 = slight overfill). Leave blank to use the default (5%).', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row" colspan="2">
                                <h4><?php esc_html_e( 'Proforma / Rates & Dates details', 'sop' ); ?></h4>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_pi_company_name">
                                    <?php esc_html_e( 'PI company name', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_pi_company_name"
                                       name="sop_supplier_pi_company_name"
                                       class="regular-text"
                                       value="<?php echo esc_attr( $pi_company_name_val ); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_pi_company_address">
                                    <?php esc_html_e( 'PI address', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="sop_supplier_pi_company_address"
                                          name="sop_supplier_pi_company_address"
                                          rows="3"
                                          class="large-text"><?php echo esc_textarea( $pi_company_address_val ); ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_pi_contact_name">
                                    <?php esc_html_e( 'PI contact name', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_pi_contact_name"
                                       name="sop_supplier_pi_contact_name"
                                       class="regular-text"
                                       value="<?php echo esc_attr( $pi_contact_name_val ); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_pi_phone">
                                    <?php esc_html_e( 'PI telephone', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_pi_phone"
                                       name="sop_supplier_pi_phone"
                                       class="regular-text"
                                       value="<?php echo esc_attr( $pi_company_phone_val ); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_pi_email">
                                    <?php esc_html_e( 'PI email', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_pi_email"
                                       name="sop_supplier_pi_email"
                                       class="regular-text"
                                       value="<?php echo esc_attr( $pi_company_email_val ); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_pi_bank_details">
                                    <?php esc_html_e( 'Bank details', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="sop_supplier_pi_bank_details"
                                          name="sop_supplier_pi_bank_details"
                                          rows="3"
                                          class="large-text"><?php echo esc_textarea( $pi_bank_details_val ); ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_pi_payment_terms">
                                    <?php esc_html_e( 'Payment terms / notes', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="sop_supplier_pi_payment_terms"
                                          name="sop_supplier_pi_payment_terms"
                                          rows="4"
                                          class="large-text"><?php echo esc_textarea( $pi_payment_terms_val ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Use this for Rates & Dates notes such as payment terms, FOB/EXW, deposit schedules, RMB locks, etc.', 'sop' ); ?>
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

<style type="text/css">
    #sop-supplier-holiday-periods th,
    #sop-supplier-holiday-periods td {
        padding: 8px 10px;
    }
    #sop-supplier-holiday-periods .column-actions {
        width: 40px;
        text-align: center;
    }
    .sop-company-details {
        margin-top: 20px;
    }
    .sop-company-details summary {
        cursor: pointer;
        padding-left: 0;
    }
    .sop-company-summary .button {
        margin-top: 10px;
    }
</style>

<script type="text/javascript">
jQuery(function($) {
    var $holidayTable = $('#sop-supplier-holiday-periods');
    var $tbody        = $holidayTable.find('tbody');

    if (! $holidayTable.length || ! $tbody.length) {
        return;
    }

    // Add extra dates row.
    $('.sop-supplier-holiday-add').on('click', function(e) {
        e.preventDefault();

        var $rows = $tbody.find('tr.sop-supplier-holiday-row');
        var $last = $rows.last();
        var $clone = $last.clone();

        $clone.find('input[type="number"]').val('');
        $clone.find('select').val('0');

        $tbody.append($clone);
    });

    // Remove row (delegated so it works on new rows too).
    $tbody.on('click', '.sop-supplier-holiday-remove', function(e) {
        e.preventDefault();

        var $rows = $tbody.find('tr.sop-supplier-holiday-row');

        if ($rows.length > 1) {
            $(this).closest('tr.sop-supplier-holiday-row').remove();
        } else {
            // If we only have one row, just clear it instead of removing.
            $rows.find('input[type="number"]').val('');
            $rows.find('select').val('0');
        }
    });
});
</script>
        <?php
    }
}

endif; // class_exists

// Bootstrap the admin settings class.
if ( is_admin() ) {
    new sop_Admin_Settings();
}

/**
 * Handle company profile save from General Settings form.
 */
function sop_handle_company_profile_save_request() {
    if ( ! isset( $_POST['sop_company_profile_action'] ) || 'save' !== $_POST['sop_company_profile_action'] ) {
        return;
    }

    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        return;
    }

    check_admin_referer( 'sop_company_profile_save', 'sop_company_profile_nonce' );

    $profile_data = array(
        'company_name'       => isset( $_POST['sop_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_name'] ) ) : '',
        'billing_address'    => isset( $_POST['sop_company_billing_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sop_company_billing_address'] ) ) : '',
        'shipping_address'   => isset( $_POST['sop_company_shipping_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sop_company_shipping_address'] ) ) : '',
        'email'              => isset( $_POST['sop_company_email'] ) ? sanitize_email( wp_unslash( $_POST['sop_company_email'] ) ) : '',
        'phone_landline'     => isset( $_POST['sop_company_phone_landline'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_phone_landline'] ) ) : '',
        'phone_mobile'       => isset( $_POST['sop_company_phone_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_phone_mobile'] ) ) : '',
        'company_reg_number' => isset( $_POST['sop_company_crn'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_crn'] ) ) : '',
        'vat_number'         => isset( $_POST['sop_company_vat'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_company_vat'] ) ) : '',
    );

    if ( function_exists( 'sop_update_company_profile' ) ) {
        sop_update_company_profile( $profile_data );
        add_settings_error( 'sop_company_profile', 'sop_company_profile_saved', __( 'Company details saved.', 'sop' ), 'updated' );
    }
}
add_action( 'admin_init', 'sop_handle_company_profile_save_request' );

/**
 * Global helper to get Stock Order Plugin settings (with defaults).
 *
 * @return array
 */
if ( ! function_exists( 'sop_get_settings' ) ) {
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
        'usd_to_rmb_rate'          => '',
        'show_suggested_vs_max'    => 1,
    );
}
}


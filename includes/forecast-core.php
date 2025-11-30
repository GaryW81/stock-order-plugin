<?php
/**
 * Stock Order Plugin – Phase 3/4 – Forecast Core & Debug UI
 *
 * - Core forecast engine (demand per day, lead time, buffer, max-per-month cap).
 * - Uses Phase 1/2 helpers:
 *     - sop_supplier_get_by_id(), sop_supplier_get_all()
 *     - sop_get_supplier_effective_buffer_months()
 *     - sop_get_analysis_lookback_days()
 * - Submenu: Stock Order → Forecast (Debug).
 * - Supplier dropdown shows supplier name only (no [ID: X] suffix).
 * File version: 1.0.12
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require core helpers. If they are missing, bail quietly so we don't fatal.
if ( ! class_exists( 'Stock_Order_Plugin_Core_Engine' ) ) :

/**
 * Core forecast engine.
 */
class Stock_Order_Plugin_Core_Engine {

    /**
     * Singleton instance.
     *
     * @var Stock_Order_Plugin_Core_Engine|null
     */
    protected static $instance = null;

    /**
     * Default analysis lookback days (from global settings).
     *
     * @var int
     */
    protected $default_lookback_days = 365;

    /**
     * Default order cycle in months (used for max-per-month capping).
     *
     * @var int
     */
    protected $default_order_cycle_months = 6;

    /**
     * Resolve lookback days from helper or default.
     *
     * Uses sop_get_analysis_lookback_days() when available, otherwise falls back
     * to a sensible default (365 days for a 12-month analysis window).
     *
     * @param int|null $supplier_id Optional supplier ID.
     * @return int
     */
    private function get_effective_lookback_days( $supplier_id = null ) {
        if ( function_exists( 'sop_get_analysis_lookback_days' ) ) {
            $days = (int) sop_get_analysis_lookback_days( $supplier_id );
            if ( $days > 0 ) {
                return $days;
            }
        }

        return 365;
    }

    /**
     * Get singleton instance.
     *
     * @return Stock_Order_Plugin_Core_Engine
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        $this->default_lookback_days = max( 1, (int) $this->get_effective_lookback_days( null ) );
    }

    /**
     * Resolve supplier settings from DB and helpers.
     *
     * @param int $supplier_id Supplier ID.
     * @return array
     */
    public function get_supplier_settings( $supplier_id ) {
        $supplier_id = (int) $supplier_id;

        if ( $supplier_id <= 0 ) {
            error_log( 'SOP: get_supplier_settings() called with invalid supplier ID.' );

            return array(
                'lead_time_weeks'    => 0,
                'buffer_months'      => 0,
                'holiday_extra_days' => 0,
                'lookback_days'      => $this->get_effective_lookback_days( null ),
                'currency'           => 'GBP',
            );
        }

        if ( ! function_exists( 'sop_supplier_get_by_id' ) || ! function_exists( 'sop_get_settings' ) ) {
            error_log( sprintf( 'SOP: supplier/settings helpers missing in get_supplier_settings() for supplier %d.', $supplier_id ) );

            return array(
                'lead_time_weeks'    => 0,
                'buffer_months'      => 0,
                'holiday_extra_days' => 0,
                'lookback_days'      => $this->get_effective_lookback_days( $supplier_id ),
                'currency'           => 'GBP',
            );
        }

        $supplier = sop_supplier_get_by_id( $supplier_id );

        if ( ! $supplier || ( ! is_object( $supplier ) && ! is_array( $supplier ) ) ) {
            error_log( sprintf( 'SOP: supplier %d not found in get_supplier_settings().', $supplier_id ) );

            return array(
                'lead_time_weeks'    => 0,
                'buffer_months'      => 0,
                'holiday_extra_days' => 0,
                'lookback_days'      => $this->get_effective_lookback_days( $supplier_id ),
                'currency'           => 'GBP',
            );
        }

        $settings = sop_get_settings();
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $lead_time_weeks = 0;
        $currency        = 'GBP';
        $holiday_weeks   = 0;

        if ( is_object( $supplier ) ) {
            $lead_time_weeks = isset( $supplier->lead_time_weeks ) ? (int) $supplier->lead_time_weeks : 0;
            $currency        = ! empty( $supplier->currency ) ? (string) $supplier->currency : 'GBP';
            $holiday_weeks   = isset( $supplier->holiday_weeks ) ? (int) $supplier->holiday_weeks : 0;
            if ( isset( $supplier->holiday_extra_days ) && $supplier->holiday_extra_days ) {
                // Preserve legacy holiday_extra_days field when present.
                $holiday_weeks = (int) ceil( (int) $supplier->holiday_extra_days / 7 );
            }
        } elseif ( is_array( $supplier ) ) {
            $lead_time_weeks = isset( $supplier['lead_time_weeks'] ) ? (int) $supplier['lead_time_weeks'] : 0;
            $currency        = ! empty( $supplier['currency'] ) ? (string) $supplier['currency'] : 'GBP';
            $holiday_weeks   = isset( $supplier['holiday_weeks'] ) ? (int) $supplier['holiday_weeks'] : 0;
            if ( isset( $supplier['holiday_extra_days'] ) && $supplier['holiday_extra_days'] ) {
                $holiday_weeks = (int) ceil( (int) $supplier['holiday_extra_days'] / 7 );
            }
        }

        $lookback_days = $this->get_effective_lookback_days( $supplier_id );

        $buffer_months = 0.0;
        if ( function_exists( 'sop_get_supplier_effective_buffer_months' ) ) {
            $buffer_months = (float) sop_get_supplier_effective_buffer_months( $supplier_id );
        }

        return array(
            'lead_time_weeks'    => $lead_time_weeks,
            'buffer_months'      => $buffer_months,
            'holiday_extra_days' => $holiday_weeks * 7,
            'lookback_days'      => $lookback_days,
            'currency'           => $currency,
        );
    }

    /**
     * Convert supplier lead time settings to days.
     *
     * @param array $supplier_settings Supplier settings.
     * @return int
     */
    public function get_supplier_lead_days( array $supplier_settings ) {
        $weeks   = isset( $supplier_settings['lead_time_weeks'] ) ? (int) $supplier_settings['lead_time_weeks'] : 0;
        $holiday = isset( $supplier_settings['holiday_extra_days'] ) ? (int) $supplier_settings['holiday_extra_days'] : 0;

        return max( 0, ( $weeks * 7 ) + $holiday );
    }

    /**
     * Get product IDs for a supplier by simple ID meta:
     * - _sop_supplier_id = supplier ID
     * - sop_supplier_id  = supplier ID
     *
     * @param int $supplier_id Supplier ID.
     * @return int[]
     */
    public function get_supplier_product_ids( $supplier_id ) {
        global $wpdb;

        $supplier_id = (int) $supplier_id;
        if ( $supplier_id <= 0 ) {
            return array();
        }

        // Allow overrides via filter, if needed.
        $filtered = apply_filters( 'sop_get_supplier_product_ids', null, $supplier_id );
        if ( is_array( $filtered ) ) {
            return array_map( 'absint', $filtered );
        }

        $posts_table = $wpdb->posts;
        the_meta: // NOT REAL - remove
        $meta_table  = $wpdb->postmeta;

        $sql = "
            SELECT DISTINCT p.ID
            FROM {$posts_table} p
            INNER JOIN {$meta_table} pm
                ON pm.post_id = p.ID
            WHERE p.post_type IN ( 'product', 'product_variation' )
              AND p.post_status IN ( 'publish', 'private' )
              AND (
                    ( pm.meta_key = '_sop_supplier_id' AND pm.meta_value = %d )
                 OR ( pm.meta_key = 'sop_supplier_id'  AND pm.meta_value = %d )
                  )
        ";

        $prepared = $wpdb->prepare( $sql, $supplier_id, $supplier_id );
        $ids      = $wpdb->get_col( $prepared );

        return array_map( 'absint', (array) $ids );
    }

    /**
     * Get sales summary for a product (qty sold, days on sale, demand per day).
     *
     * This uses WooCommerce order data for quantity sold, combines live stockout
     * logs from sop_stockout_log with scaled legacy stockout metrics, and computes
     * an adjusted days-on-sale figure.
     *
     * @param int   $product_id Product ID.
     * @param array $args       Optional overrides (lookback_days, date_to).
     * @return array {
     *     @type int   $qty_sold             Total quantity sold in the window.
     *     @type float $days_on_sale         Adjusted days on sale (total_days - stockout_days_total).
     *     @type float $demand_per_day       Qty sold per adjusted day.
     *     @type float $total_days           Total days in the analysis window.
     *     @type float $stockout_days_total  Combined live + legacy stockout days.
     *     @type float $stockout_days_live   Live stockout days from sop_stockout_log.
     *     @type float $stockout_days_legacy Legacy stockout days (scaled to the window).
     *     @type float $legacy_total_days    Total legacy-covered days inside the window.
     * }
     */
    public function get_product_sales_summary( $product_id, array $args = array() ) {
        global $wpdb;

        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            return array(
                'qty_sold'             => 0,
                'days_on_sale'         => 0.0,
                'demand_per_day'       => 0.0,
                'total_days'           => 0.0,
                'stockout_days_total'  => 0.0,
                'stockout_days_live'   => 0.0,
                'stockout_days_legacy' => 0.0,
                'legacy_total_days'    => 0.0,
            );
        }

        $defaults = array(
            'lookback_days' => $this->default_lookback_days,
            'date_to'       => current_time( 'Y-m-d' ),
        );
        $args = wp_parse_args( $args, $defaults );

        $lookback_days = max( 1, (int) $args['lookback_days'] );
        $date_to       = $args['date_to'];

        $to_ts   = strtotime( $date_to . ' 23:59:59' );
        $from_ts = $to_ts - ( $lookback_days * DAY_IN_SECONDS );

        // Base window span in days (e.g. 365).
        $window_span_days = max( 1.0, ( $to_ts - $from_ts ) / DAY_IN_SECONDS );

        // Derive product creation timestamp (use parent for variations).
        $product_created_ts = 0;
        $product_post       = get_post( $product_id );

        if ( $product_post instanceof WP_Post ) {
            if ( 'product_variation' === $product_post->post_type && $product_post->post_parent ) {
                $parent_post = get_post( $product_post->post_parent );
                if ( $parent_post instanceof WP_Post ) {
                    $product_created_ts = get_post_time( 'U', true, $parent_post );
                }
            } else {
                $product_created_ts = get_post_time( 'U', true, $product_post );
            }
        }

        // Effective start for this product inside the window:
        // - if we know its creation date, use max(window start, created),
        // - otherwise fall back to the full window.
        if ( $product_created_ts > 0 ) {
            $product_start_ts = max( $from_ts, $product_created_ts );
            $product_life_days = max( 1.0, ( $to_ts - $product_start_ts ) / DAY_IN_SECONDS );
            $total_days        = min( $window_span_days, $product_life_days );
        } else {
            $total_days = $window_span_days;
        }

        // 1) Quantity sold from WooCommerce order lookup tables.
        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $orders_table = $wpdb->prefix . 'wc_orders';

        $allowed_statuses = array( 'wc-processing', 'wc-completed', 'wc-on-hold' );
        $placeholders     = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );

        $sql = "
            SELECT COALESCE( SUM( l.product_qty ), 0 ) AS qty_sold
            FROM {$lookup_table} AS l
            INNER JOIN {$orders_table} AS o
                ON l.order_id = o.id
            WHERE l.product_id = %d
              AND o.status IN ( {$placeholders} )
              AND o.date_created_gmt BETWEEN %s AND %s
        ";

        $params = array_merge(
            array( $product_id ),
            $allowed_statuses,
            array(
                gmdate( 'Y-m-d 00:00:00', $from_ts ),
                gmdate( 'Y-m-d 23:59:59', $to_ts ),
            )
        );

        $qty_sold = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

        // 2) Live stockout days from sop_stockout_log.
        $stockout_days_live = 0.0;
        if ( function_exists( 'sop_stockout_get_days_in_window' ) ) {
            $stockout_days_live = (float) sop_stockout_get_days_in_window( $product_id, 0, $from_ts, $to_ts );
            if ( $stockout_days_live < 0 ) {
                $stockout_days_live = 0.0;
            }
        }

        // 3) Legacy stockout days (scaled to the portion of the window before import).
        $stockout_days_legacy = 0.0;
        $legacy_total_days    = 0.0;

        if ( function_exists( 'sop_legacy_get_scaled_days_for_window' ) ) {
            $legacy = sop_legacy_get_scaled_days_for_window( $product_id, $from_ts, $to_ts );
            if ( is_array( $legacy ) ) {
                if ( isset( $legacy['stockout_days'] ) ) {
                    $stockout_days_legacy = max( 0.0, (float) $legacy['stockout_days'] );
                }
                if ( isset( $legacy['total_days'] ) ) {
                    $legacy_total_days = max( 0.0, (float) $legacy['total_days'] );
                }
            }
        }

        // 4) Combine live + legacy stockout days and clamp to the product's effective days.
        $stockout_days_total = max( 0.0, $stockout_days_live + $stockout_days_legacy );
        $stockout_days_total = min( $stockout_days_total, $total_days );

        // 5) Days on sale are the product's effective days minus stockout.
        $days_on_sale = max( 1.0, $total_days - $stockout_days_total );

        // Demand per day uses days on sale (OOS-adjusted).
        if ( $qty_sold > 0 && $days_on_sale > 0 ) {
            $demand_per_day = $qty_sold / $days_on_sale;
        } else {
            $demand_per_day = 0.0;
        }

        return array(
            'qty_sold'             => $qty_sold,
            'days_on_sale'         => (float) $days_on_sale,
            'demand_per_day'       => (float) $demand_per_day,
            'total_days'           => (float) $total_days,
            'stockout_days_total'  => (float) $stockout_days_total,
            'stockout_days_live'   => (float) $stockout_days_live,
            'stockout_days_legacy' => (float) $stockout_days_legacy,
            'legacy_total_days'    => (float) $legacy_total_days,
        );
    }

    /**
     * Build a forecast row for a single product.
     *
     * @param int   $product_id         Product ID.
     * @param array $supplier_settings  Supplier settings.
     * @param array $args               Optional overrides (lookback_days, order_cycle_months).
     * @return array Empty array on failure.
     */
    public function get_product_forecast( $product_id, array $supplier_settings = array(), array $args = array() ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            return array();
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return array();
        }

        $defaults = array(
            'lookback_days'      => $this->default_lookback_days,
            'order_cycle_months' => $this->default_order_cycle_months,
        );
        $args = wp_parse_args( $args, $defaults );

        $lookback_days      = max( 1, (int) $args['lookback_days'] );
        $order_cycle_months = max( 1, (int) $args['order_cycle_months'] );

        $sales = $this->get_product_sales_summary( $product_id, array( 'lookback_days' => $lookback_days ) );

        $demand_per_day       = isset( $sales['demand_per_day'] ) ? (float) $sales['demand_per_day'] : 0.0;
        $days_on_sale         = isset( $sales['days_on_sale'] ) ? (float) $sales['days_on_sale'] : 0.0;
        $total_days           = isset( $sales['total_days'] ) ? (float) $sales['total_days'] : 0.0;
        $stockout_days_total  = isset( $sales['stockout_days_total'] ) ? (float) $sales['stockout_days_total'] : 0.0;
        $stockout_days_live   = isset( $sales['stockout_days_live'] ) ? (float) $sales['stockout_days_live'] : 0.0;
        $stockout_days_legacy = isset( $sales['stockout_days_legacy'] ) ? (float) $sales['stockout_days_legacy'] : 0.0;

        $lead_days = $this->get_supplier_lead_days( $supplier_settings );
        $lead_days = max( 0.0, (float) $lead_days );

        $buffer_months = isset( $supplier_settings['buffer_months'] ) ? (float) $supplier_settings['buffer_months'] : 0.0;
        if ( $buffer_months < 0 ) {
            $buffer_months = 0.0;
        }

        $buffer_days = max( 0.0, $buffer_months * 30.4375 );

        $lead_demand   = $demand_per_day * $lead_days;
        $buffer_demand = $demand_per_day * $buffer_days;

        $current_stock = (int) $product->get_stock_quantity();
        if ( $current_stock < 0 ) {
            $current_stock = 0;
        }

        // Stock remaining when the shipment lands (before new order), clamped at zero.
        $stock_at_arrival = max( 0.0, (float) $current_stock - $lead_demand );

        // Target stock on arrival is based solely on buffer coverage.
        $target_at_arrival   = $buffer_demand;
        $buffer_target_units = $target_at_arrival;

        // Informational forecast metrics.
        $forecast_days   = max( 0.0, (float) $lead_days + $buffer_days );
        $forecast_demand = $demand_per_day * $forecast_days;

        // Suggested order is what we need to reach the buffer target at arrival.
        $suggested_raw = max( 0.0, $target_at_arrival - $stock_at_arrival );

        // Optional per-product max-per-month cap, read directly from product/parent meta.
        // We support two keys:
        // - 'max_order_qty_per_month' (primary)
        // - 'max_qty_per_month'       (legacy)
        $max_per_month = 0.0;

        if ( $product instanceof \WC_Product ) {
            $ids_to_check = array( $product->get_id() );
            $parent_id    = $product->get_parent_id();

            if ( $parent_id ) {
                $ids_to_check[] = $parent_id;
            }

            $meta_keys = array(
                'max_order_qty_per_month',   // canonical key
                'max_qty_per_month',         // legacy key
                'max_order_qty_per month',   // legacy key with space before "month"
            );

            foreach ( $ids_to_check as $cap_post_id ) {
                foreach ( $meta_keys as $meta_key ) {
                    $raw = get_post_meta( $cap_post_id, $meta_key, true );
                    if ( '' === $raw ) {
                        continue;
                    }

                    $val = (float) str_replace( ',', '.', (string) $raw );
                    if ( $val > 0 ) {
                        $max_per_month = $val;
                        break 2; // break out of both loops.
                    }
                }
            }
        }

        // Use the supplier's effective buffer months as the cap horizon where possible.
        $effective_cycle_months = $buffer_months > 0 ? (float) $buffer_months : (float) $order_cycle_months;
        if ( $effective_cycle_months < 0 ) {
            $effective_cycle_months = 0.0;
        }

        // Max for the full forecast cycle = monthly cap × buffer months (or fallback cycle).
        $max_for_cycle = ( $max_per_month > 0 && $effective_cycle_months > 0 )
            ? ( $max_per_month * $effective_cycle_months )
            : 0.0;

        // Suggested (Capped) is used for comparison in Forecast (Debug) only.
        // The Pre-Order Sheet uses Suggested (Raw) as its suggested order quantity.
        $suggested_capped = ( $max_for_cycle > 0 )
            ? min( $suggested_raw, $max_for_cycle )
            : $suggested_raw;

        return array(
            'product_id'        => $product_id,
            'sku'               => $product->get_sku(),
            'name'              => $product->get_name(),
            'current_stock'     => $current_stock,
            'qty_sold'          => (int) $sales['qty_sold'],
            'demand_per_day'    => (float) $demand_per_day,
            'forecast_days'     => (float) $forecast_days,
            'forecast_demand'   => (float) $forecast_demand,
            'max_order_per_month' => (float) $max_per_month,
            'max_for_cycle'     => (float) $max_for_cycle,
            'suggested_raw'     => (float) $suggested_raw,
            'suggested_capped'  => (float) $suggested_capped,
            'days_on_sale'      => (float) $days_on_sale,
            'total_days'        => (float) $total_days,
            'stockout_days'     => (float) $stockout_days_total,
            'stockout_days_live'  => (float) $stockout_days_live,
            'stockout_days_legacy'=> (float) $stockout_days_legacy,
            'lead_days'         => (float) $lead_days,
            'buffer_days'       => (float) $buffer_days,
            'demand_during_lead'=> (float) $demand_during_lead,
            'buffer_target_units'=> (float) $buffer_target_units,
            'stock_at_arrival'  => (float) $stock_at_arrival,
        );
    }

    /**
     * Build all forecast rows for a supplier.
     *
     * @param int   $supplier_id Supplier ID.
     * @param array $args        Optional overrides.
     * @return array[]
     */
    public function get_supplier_forecast( $supplier_id, array $args = array() ) {
        $supplier_id = (int) $supplier_id;
        if ( $supplier_id <= 0 ) {
            return array();
        }

        $supplier_settings = $this->get_supplier_settings( $supplier_id );
        $product_ids       = $this->get_supplier_product_ids( $supplier_id );

        if ( empty( $product_ids ) ) {
            return array();
        }

        $rows = array();
        foreach ( $product_ids as $pid ) {
            $row = $this->get_product_forecast( $pid, $supplier_settings, $args );
            if ( ! empty( $row ) ) {
                $rows[] = $row;
            }
        }

        return apply_filters( 'sop_supplier_forecast_rows', $rows, $supplier_id, $supplier_settings, $args );
    }
}

endif; // class exists.

/**
 * Helper to access the forecast engine singleton.
 *
 * @return Stock_Order_Plugin_Core_Engine|null
 */
function sop_core_engine() {
    if ( ! class_exists( 'Stock_Order_Plugin_Core_Engine' ) ) {
        if ( function_exists( 'error_log' ) ) {
            error_log( '[SOP] sop_core_engine() called but Stock_Order_Plugin_Core_Engine is not available.' );
        }
        return null;
    }

    return Stock_Order_Plugin_Core_Engine::get_instance();
}

/**
 * -------------------------------------------------------
 * Admin UI – Stock Order → Forecast (Debug)
 * -------------------------------------------------------
 */
add_action(
    'admin_menu',
    function () {
        add_submenu_page(
            'sop_stock_order_dashboard',
            __( 'Stock Order Forecast (Debug)', 'sop' ),
            __( 'Forecast (Debug)', 'sop' ),
            'manage_woocommerce',
            'sop-forecast-debug',
            'sop_render_forecast_debug_page'
        );
    },
    30
);

/**
 * Render the Forecast (Debug) page.
 */
function sop_render_forecast_debug_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'sop' ) );
    }

    $suppliers = function_exists( 'sop_supplier_get_all' ) ? sop_supplier_get_all() : array();

    $selected_supplier_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $engine               = function_exists( 'sop_core_engine' ) ? sop_core_engine() : null;

    $rows    = array();
    $ran_run = false;

    if ( ! $engine || ! is_object( $engine ) ) {
        echo '<div class="wrap sop-forecast-debug">';
        echo '<h1>' . esc_html__( 'Stock Order Forecast (Debug)', 'sop' ) . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Forecast engine is not available. Please check that the Stock Order Plugin core helpers are loaded.', 'sop' ) . '</p></div>';
        echo '</div>';
        return;
    }

    if ( $selected_supplier_id > 0 && method_exists( $engine, 'get_supplier_forecast' ) ) {
        try {
            $rows    = $engine->get_supplier_forecast( $selected_supplier_id );
            $ran_run = true;
        } catch ( \Throwable $t ) {
            error_log(
                sprintf(
                    'SOP: Forecast debug run failed for supplier %d: %s in %s:%d',
                    (int) $selected_supplier_id,
                    $t->getMessage(),
                    $t->getFile(),
                    $t->getLine()
                )
            );
            $rows    = array();
            $ran_run = true;
        }
    }
    ?>
    <div class="wrap sop-forecast-debug">
        <h1><?php esc_html_e( 'Stock Order Forecast (Debug)', 'sop' ); ?></h1>

        <form method="get" style="margin-bottom: 1em;">
            <input type="hidden" name="page" value="sop-forecast-debug" />
            <label for="sop_supplier_select">
                <strong><?php esc_html_e( 'Supplier:', 'sop' ); ?></strong>
            </label>
            <select name="supplier_id" id="sop_supplier_select">
                <option value="0"><?php esc_html_e( 'Select a supplier…', 'sop' ); ?></option>
                <?php if ( ! empty( $suppliers ) ) : ?>
                    <?php foreach ( $suppliers as $supplier ) : ?>
                        <?php
                        $sid   = isset( $supplier->id ) ? (int) $supplier->id : 0;
                        $label = isset( $supplier->name ) ? (string) $supplier->name : '';
                        ?>
                        <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $selected_supplier_id, $sid ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <?php submit_button( __( 'Run Forecast', 'sop' ), 'primary', '', false, array( 'style' => 'margin-left:8px;' ) ); ?>
        </form>

        <?php
        if ( ! $ran_run ) {
            echo '<p>' . esc_html__( 'Choose a supplier and click "Run Forecast" to see suggested quantities.', 'sop' ) . '</p>';
            echo '</div>';
            return;
        }

        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No products found for this supplier or no sales data in the selected analysis window.', 'sop' ) . '</p>';
            echo '</div>';
            return;
        }

        $supplier_settings = $engine->get_supplier_settings( $selected_supplier_id );
        $lookback_days     = max( 1, (int) ( function_exists( 'sop_get_analysis_lookback_days' ) ? sop_get_analysis_lookback_days() : 365 ) );
        $lead_days         = $engine->get_supplier_lead_days( $supplier_settings );
        $buffer_months     = isset( $supplier_settings['buffer_months'] ) ? (float) $supplier_settings['buffer_months'] : 0.0;
        ?>
        <p>
            <?php
            printf(
                /* translators: 1: supplier name, 2: supplier ID */
                esc_html__( 'Forecast for %1$s (ID %2$d)', 'sop' ),
                esc_html( isset( $supplier_settings['name'] ) ? $supplier_settings['name'] : '' ),
                (int) ( isset( $supplier_settings['id'] ) ? $supplier_settings['id'] : $selected_supplier_id )
            );
            ?>
            <br />
            <?php
            printf(
                /* translators: 1: days, 2: months, 3: days */
                esc_html__( 'Lookback: %1$d days. Buffer: %2$s months. Lead time: %3$d days.', 'sop' ),
                $lookback_days,
                number_format_i18n( $buffer_months, 1 ),
                $lead_days
            );
            ?>
        </p>

        <style>
            .sop-forecast-table-wrapper {
                max-height: 90vh;
                overflow: auto;
                border: 1px solid #ddd;
                background: #fff;
            }
            .sop-forecast-table-wrapper table {
                margin: 0;
            }
            .sop-forecast-table-wrapper thead th {
                position: sticky;
                top: 0;
                background: #f9f9f9;
                z-index: 2;
            }
        </style>

        <div class="sop-forecast-table-wrapper">
            <table class="widefat striped sop-forecast-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Product ID', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Current Stock', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Qty Sold (Period)', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Days the product was in stock during the lookback window after removing stockout days. Used to calculate demand per day.', 'sop' ); ?>"><?php esc_html_e( 'Days on sale (adj.)', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Total days in the lookback window where stock level was zero for this product.', 'sop' ); ?>"><?php esc_html_e( 'Stockout days', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Average units sold per adjusted day on sale. Quantity sold divided by Days on sale.', 'sop' ); ?>"><?php esc_html_e( 'Demand / Day', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Total days covered by the forecast. Supplier lead time in days plus buffer period in days.', 'sop' ); ?>"><?php esc_html_e( 'Forecast Days', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Expected units sold over the forecast window based on Demand per Day multiplied by Forecast Days.', 'sop' ); ?>"><?php esc_html_e( 'Forecast Demand', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Estimated units left when the shipment arrives with no new order placed. Current stock minus demand during lead time, never less than zero.', 'sop' ); ?>"><?php esc_html_e( 'Stock at arrival', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Units we aim to have when the shipment lands, based on buffer months and demand per day.', 'sop' ); ?>"><?php esc_html_e( 'Buffer target', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Manual per-product ceiling (max_order_qty_per_month) representing the maximum units per month you will order.', 'sop' ); ?>"><?php esc_html_e( 'Max / Month', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Max / Month multiplied by this supplier\'s buffer months (lead-time buffer horizon). Shown as a reference cap for this forecast run.', 'sop' ); ?>"><?php esc_html_e( 'Max / Cycle', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Suggested order quantity before applying Max / Month caps or rounding.', 'sop' ); ?>"><?php esc_html_e( 'Suggested (Raw)', 'sop' ); ?></th>
                        <th title="<?php echo esc_attr__( 'Suggested order quantity after applying the Max / Month × buffer-month cap. Used for comparison only – the Pre-Order Sheet uses Suggested (Raw) as its suggested order quantity.', 'sop' ); ?>"><?php esc_html_e( 'Suggested (Capped)', 'sop' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['product_id'] ); ?></td>
                            <td><?php echo esc_html( $row['sku'] ); ?></td>
                            <td><?php echo esc_html( $row['name'] ); ?></td>
                            <td><?php echo esc_html( $row['current_stock'] ); ?></td>
                            <td><?php echo esc_html( $row['qty_sold'] ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( isset( $row['days_on_sale'] ) ? $row['days_on_sale'] : 0, 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( isset( $row['stockout_days'] ) ? $row['stockout_days'] : 0, 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $row['demand_per_day'], 3 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $row['forecast_days'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $row['forecast_demand'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( isset( $row['stock_at_arrival'] ) ? $row['stock_at_arrival'] : 0, 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( isset( $row['buffer_target_units'] ) ? $row['buffer_target_units'] : 0, 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $row['max_order_per_month'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $row['max_for_cycle'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $row['suggested_raw'], 1 ) ); ?></td>
                            <td><strong><?php echo esc_html( number_format_i18n( $row['suggested_capped'], 1 ) ); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

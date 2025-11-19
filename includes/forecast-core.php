<?php
/**
 * Stock Order Plugin – Phase 3 (Simple ID Mapping + 90vh Height)
 *
 * - Core forecast engine (demand/day, lead time, buffer, max-per-month cap).
 * - Submenu: Stock Order → Forecast (Debug).
 * - Supplier dropdown uses Name [ID: X].
 * - Products mapped ONLY by supplier ID via:
 *       _sop_supplier_id = supplier ID
 *       sop_supplier_id   = supplier ID
 *   (No use of legacy "supplier" meta or supplier "code".)
 * - Sticky header inside scrollable wrapper with max-height: 90vh.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * -------------------------------------------------------
 *  Core Engine
 * -------------------------------------------------------
 */

if ( ! class_exists( 'Stock_Order_Plugin_Core_Engine' ) ) :

class Stock_Order_Plugin_Core_Engine {

    protected static $instance = null;
    protected $global_settings = array();

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        $this->global_settings = $this->load_global_settings();
    }

    protected function load_global_settings() {
        $option = get_option( 'sop_global_settings', array() );

        $defaults = array(
            'lookback_months'    => 12,
            'buffer_months'      => 6,
            'order_cycle_months' => 6,
        );

        $settings = wp_parse_args( is_array( $option ) ? $option : array(), $defaults );

        $settings['lookback_months']    = max( 1, absint( $settings['lookback_months'] ) );
        $settings['buffer_months']      = max( 0, absint( $settings['buffer_months'] ) );
        $settings['order_cycle_months'] = max( 1, absint( $settings['order_cycle_months'] ) );

        return $settings;
    }

    public function get_global_settings() {
        return $this->global_settings;
    }

    /**
     * Supplier settings via Phase 2 helper / filters / option.
     */
    public function get_supplier_settings( $supplier_id ) {
        $supplier_id = absint( $supplier_id );

        // Phase 2 DB helper.
        if ( function_exists( 'sop_supplier_get_by_id' ) ) {
            $row = sop_supplier_get_by_id( $supplier_id );
            if ( $row ) {
                return array(
                    'id'                => (int) $row->id,
                    'name'              => $row->name,
                    'lead_time_weeks'   => isset( $row->lead_time_weeks ) ? (int) $row->lead_time_weeks : 16,
                    'holiday_extra_days'=> isset( $row->holiday_extra_days ) ? (int) $row->holiday_extra_days : 0,
                    'buffer_months'     => ( $row->buffer_months === '' ? null : (int) $row->buffer_months ),
                    'currency'          => ! empty( $row->currency ) ? $row->currency : 'RMB',
                );
            }
        }

        // Custom filter.
        $settings = apply_filters( 'sop_get_supplier_settings', null, $supplier_id );
        if ( is_array( $settings ) ) {
            $settings['id'] = isset( $settings['id'] ) ? (int) $settings['id'] : $supplier_id;
            return $settings;
        }

        // Option fallback.
        $all = get_option( 'sop_supplier_settings', array() );
        if ( isset( $all[ $supplier_id ] ) && is_array( $all[ $supplier_id ] ) ) {
            $base       = $all[ $supplier_id ];
            $base['id'] = $supplier_id;
            return $base;
        }

        // Last resort default.
        return array(
            'id'                => $supplier_id,
            'name'              => 'Supplier ID: ' . $supplier_id,
            'lead_time_weeks'   => 16,
            'holiday_extra_days'=> 0,
            'buffer_months'     => null,
            'currency'          => 'RMB',
        );
    }

    public function get_supplier_lead_days( array $supplier_settings ) {
        $lead_weeks = isset( $supplier_settings['lead_time_weeks'] ) ? absint( $supplier_settings['lead_time_weeks'] ) : 0;
        $holiday    = isset( $supplier_settings['holiday_extra_days'] ) ? absint( $supplier_settings['holiday_extra_days'] ) : 0;
        return ( $lead_weeks * 7 ) + $holiday;
    }

    public function get_buffer_months( array $supplier_settings ) {
        if ( isset( $supplier_settings['buffer_months'] ) && $supplier_settings['buffer_months'] !== '' ) {
            return max( 0, absint( $supplier_settings['buffer_months'] ) );
        }
        return isset( $this->global_settings['buffer_months'] )
            ? max( 0, absint( $this->global_settings['buffer_months'] ) )
            : 6;
    }

    /**
     * Get product IDs for a supplier by simple ID meta:
     *  - _sop_supplier_id = supplier ID
     *  - sop_supplier_id   = supplier ID
     *
     * No legacy "supplier" or "code" mapping here.
     */
    public function get_supplier_product_ids( $supplier_id ) {
        $supplier_id = absint( $supplier_id );

        // Allow override.
        $ids = apply_filters( 'sop_get_supplier_product_ids', null, $supplier_id );
        if ( is_array( $ids ) ) {
            return array_map( 'absint', $ids );
        }

        if ( ! $supplier_id ) {
            return array();
        }

        global $wpdb;

        $posts_table = $wpdb->posts;
        $meta_table  = $wpdb->postmeta;

        $sql = "
            SELECT DISTINCT p.ID
            FROM {$posts_table} p
            INNER JOIN {$meta_table} pm
                ON pm.post_id = p.ID
            WHERE p.post_type IN ('product','product_variation')
              AND p.post_status IN ('publish','private')
              AND (
                    (pm.meta_key = '_sop_supplier_id' AND pm.meta_value = %d)
                 OR (pm.meta_key = 'sop_supplier_id'  AND pm.meta_value = %d)
                  )
        ";

        $prepared = $wpdb->prepare( $sql, $supplier_id, $supplier_id );
        $results  = $wpdb->get_col( $prepared );

        return array_map( 'absint', (array) $results );
    }

    public function get_product_sales_summary( $product_id, array $args = array() ) {
        global $wpdb;

        $product_id = absint( $product_id );
        if ( ! $product_id ) {
            return array(
                'qty_sold'       => 0,
                'days_on_sale'   => 0.0,
                'demand_per_day' => 0.0,
            );
        }

        $defaults = array(
            'lookback_months' => $this->global_settings['lookback_months'],
            'date_to'         => current_time( 'Y-m-d' ),
        );
        $args = wp_parse_args( $args, $defaults );

        $lookback_months = max( 1, absint( $args['lookback_months'] ) );
        $date_to         = $args['date_to'];

        $to_ts   = strtotime( $date_to . ' 23:59:59' );
        $from_ts = strtotime( '-' . $lookback_months . ' months', $to_ts );

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $orders_table = $wpdb->prefix . 'wc_orders';

        $allowed_statuses = array( 'wc-processing', 'wc-completed', 'wc-on-hold' );
        $placeholders     = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );

        $sql = "
            SELECT COALESCE( SUM( product_qty ), 0 ) AS qty_sold
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

        $total_days    = max( 1, ( $to_ts - $from_ts ) / DAY_IN_SECONDS );
        $stockout_days = $this->get_product_stockout_days( $product_id, $from_ts, $to_ts ); // stub for now
        $days_on_sale  = max( 1.0, $total_days - $stockout_days );

        $demand_per_day = $qty_sold > 0 ? ( $qty_sold / $days_on_sale ) : 0.0;

        return array(
            'qty_sold'       => $qty_sold,
            'days_on_sale'   => (float) $days_on_sale,
            'demand_per_day' => (float) $demand_per_day,
        );
    }

    protected function get_product_stockout_days( $product_id, $from_ts, $to_ts ) {
        // Will use sop_stockout_log later.
        return 0.0;
    }

    public function get_product_forecast( $product_id, array $supplier_settings = array(), array $args = array() ) {
        $product_id = absint( $product_id );
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            return array();
        }

        $defaults = array(
            'lookback_months'    => $this->global_settings['lookback_months'],
            'order_cycle_months' => $this->global_settings['order_cycle_months'],
        );
        $args = wp_parse_args( $args, $defaults );

        $lookback_months    = max( 1, absint( $args['lookback_months'] ) );
        $order_cycle_months = max( 1, absint( $args['order_cycle_months'] ) );

        $sales_summary = $this->get_product_sales_summary(
            $product_id,
            array( 'lookback_months' => $lookback_months )
        );

        $demand_per_day = $sales_summary['demand_per_day'];

        $lead_days     = $this->get_supplier_lead_days( $supplier_settings );
        $buffer_months = $this->get_buffer_months( $supplier_settings );
        $buffer_days   = $buffer_months * 30;

        $forecast_days   = $lead_days + $buffer_days;
        $forecast_demand = $forecast_days > 0 ? ( $demand_per_day * $forecast_days ) : 0.0;

        $current_stock = (int) $product->get_stock_quantity();
        if ( $current_stock < 0 ) {
            $current_stock = 0;
        }

        $suggested_raw = max( 0.0, $forecast_demand - $current_stock );

        $max_per_month = get_post_meta( $product_id, 'max_order_qty_per_month', true );
        $max_per_month = $max_per_month !== '' ? (float) $max_per_month : 0.0;

        $max_for_cycle = $max_per_month > 0 ? ( $max_per_month * $order_cycle_months ) : 0.0;

        $suggested_capped = ( $max_for_cycle > 0 )
            ? min( $suggested_raw, $max_for_cycle )
            : $suggested_raw;

        return array(
            'product_id'          => $product_id,
            'sku'                 => $product->get_sku(),
            'name'                => $product->get_name(),
            'current_stock'       => $current_stock,
            'qty_sold'            => (int) $sales_summary['qty_sold'],
            'demand_per_day'      => (float) $demand_per_day,
            'forecast_days'       => (float) $forecast_days,
            'forecast_demand'     => (float) $forecast_demand,
            'max_order_per_month' => (float) $max_per_month,
            'max_for_cycle'       => (float) $max_for_cycle,
            'suggested_raw'       => (float) $suggested_raw,
            'suggested_capped'    => (float) $suggested_capped,
        );
    }

    public function get_supplier_forecast( $supplier_id, array $args = array() ) {
        $supplier_id = absint( $supplier_id );
        if ( ! $supplier_id ) {
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

function sop_core_engine() {
    return Stock_Order_Plugin_Core_Engine::get_instance();
}

endif;

/**
 * -------------------------------------------------------
 *  Admin UI – Stock Order → Forecast (Debug)
 * -------------------------------------------------------
 */

add_action( 'admin_menu', function () {
    add_submenu_page(
        'sop_stock_order',
        __( 'Stock Order Forecast (Debug)', 'sop' ),
        __( 'Forecast (Debug)', 'sop' ),
        'manage_woocommerce',
        'sop-forecast-debug',
        'sop_render_forecast_debug_page'
    );
}, 30);

function sop_render_forecast_debug_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'sop' ) );
    }

    $suppliers = array();
    if ( function_exists( 'sop_supplier_get_all' ) ) {
        $suppliers = sop_supplier_get_all( array() );
    }

    $supplier_id = isset( $_GET['supplier_id'] ) ? absint( $_GET['supplier_id'] ) : 0;
    $engine      = sop_core_engine();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Stock Order Forecast (Debug)', 'sop' ); ?></h1>

        <form method="get" style="margin-bottom: 1em;">
            <input type="hidden" name="page" value="sop-forecast-debug" />
            <label for="sop_supplier_id"><strong><?php esc_html_e( 'Supplier:', 'sop' ); ?></strong></label>
            <select name="supplier_id" id="sop_supplier_id">
                <option value="0"><?php esc_html_e( 'Select a supplier…', 'sop' ); ?></option>
                <?php foreach ( $suppliers as $s ) : ?>
                    <?php
                    $sid   = (int) $s->id;
                    $label = $s->name . ' [ID: ' . $sid . ']';
                    ?>
                    <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $supplier_id, $sid ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button( __( 'Run Forecast', 'sop' ), 'primary', '', false, array( 'style' => 'margin-left:8px;' ) ); ?>
        </form>
    <?php

    if ( empty( $suppliers ) ) {
        echo '<p>' . esc_html__( 'No suppliers found. Configure suppliers in the Stock Order settings first.', 'sop' ) . '</p></div>';
        return;
    }

    if ( ! $supplier_id ) {
        echo '<p>' . esc_html__( 'Choose a supplier and click "Run Forecast" to see suggested quantities.', 'sop' ) . '</p></div>';
        return;
    }

    $supplier_settings = $engine->get_supplier_settings( $supplier_id );
    $rows              = $engine->get_supplier_forecast( $supplier_id );
    $count             = count( $rows );

    $lookback  = $engine->get_global_settings()['lookback_months'];
    $buffer_m  = $engine->get_buffer_months( $supplier_settings );
    $lead_days = $engine->get_supplier_lead_days( $supplier_settings );
    ?>
        <p>
            <?php
            printf(
                esc_html__( 'Supplier: %1$s [ID: %2$d]', 'sop' ),
                esc_html( $supplier_settings['name'] ),
                absint( $supplier_settings['id'] )
            );
            ?>
            <br />
            <?php
            printf(
                esc_html__( 'Lookback: %1$d months. Lead time: %2$d days. Buffer: %3$d months.', 'sop' ),
                absint( $lookback ),
                absint( $lead_days ),
                absint( $buffer_m )
            );
            ?>
            <br />
            <?php
            printf(
                esc_html__( 'Found %1$d products for this supplier.', 'sop' ),
                absint( $count )
            );
            ?>
        </p>
    <?php

    if ( empty( $rows ) ) {
        echo '<p>' . esc_html__( 'No products with this supplier ID were found, or there is no sales data yet.', 'sop' ) . '</p></div>';
        return;
    }
    ?>
        <style>
            .sop-forecast-table-wrapper {
                max-height: 90vh;  /* you can tweak this */
                min-height: 40vh;
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
                        <th><?php esc_html_e( 'Demand / Day', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Forecast Days', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Forecast Demand', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Max / Month', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Max / Cycle', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Suggested (Raw)', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Suggested (Capped)', 'sop' ); ?></th>
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
                            <td><?php echo esc_html( number_format( $row['demand_per_day'], 3 ) ); ?></td>
                            <td><?php echo esc_html( number_format( $row['forecast_days'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format( $row['forecast_demand'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format( $row['max_order_per_month'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format( $row['max_for_cycle'], 1 ) ); ?></td>
                            <td><?php echo esc_html( number_format( $row['suggested_raw'], 1 ) ); ?></td>
                            <td><strong><?php echo esc_html( number_format( $row['suggested_capped'], 1 ) ); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php
}
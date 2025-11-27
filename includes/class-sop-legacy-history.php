<?php
/**
 * Stock Order Plugin - Phase 4
 * Legacy product history storage and importer helpers
 * File version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles storage of legacy product history data for SOP.
 */
class SOP_Legacy_History {

    /**
     * Get the fully qualified legacy history table name.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'sop_legacy_product_history';
    }

    /**
     * List of allowed columns for the legacy history table.
     *
     * @return array
     */
    public static function get_columns() {
        return array(
            'id',
            'product_id',
            'sku',
            'product_name',
            'supplier_id',
            'engine_kit_group',
            'current_stock',
            'max_order_qty_per_month',
            'units_sold_12m',
            'revenue_12m',
            'first_order_date_12m',
            'last_order_date_12m',
            'order_count_12m',
            'avg_units_per_day_raw_12m',
            'days_span_12m',
            'avg_units_per_day_span_12m',
            'alert_count_12m',
            'zero_alert_count_12m',
            'first_zero_alert_12m',
            'last_zero_alert_12m',
            'delivered_units_from_containers_12m',
            'container_delivery_count_12m',
            'stockout_days_12m_legacy',
            'days_on_sale_12m_legacy',
            'avg_units_per_day_in_stock_12m_legacy',
            'lost_units_12m_legacy',
            'lost_revenue_12m_legacy',
            'legacy_source_version',
            'imported_at',
        );
    }

    /**
     * Install or update the legacy history table.
     */
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table           = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            sku VARCHAR(100) NULL,
            product_name VARCHAR(255) NULL,
            supplier_id BIGINT(20) UNSIGNED NULL,
            engine_kit_group VARCHAR(50) NULL,
            current_stock INT(11) NULL,
            max_order_qty_per_month INT(11) NULL,
            units_sold_12m INT(11) NULL,
            revenue_12m DECIMAL(18,4) NULL,
            first_order_date_12m DATETIME NULL,
            last_order_date_12m DATETIME NULL,
            order_count_12m INT(11) NULL,
            avg_units_per_day_raw_12m DECIMAL(12,6) NULL,
            days_span_12m INT(11) NULL,
            avg_units_per_day_span_12m DECIMAL(12,6) NULL,
            alert_count_12m INT(11) NULL,
            zero_alert_count_12m INT(11) NULL,
            first_zero_alert_12m DATETIME NULL,
            last_zero_alert_12m DATETIME NULL,
            delivered_units_from_containers_12m INT(11) NULL,
            container_delivery_count_12m INT(11) NULL,
            stockout_days_12m_legacy INT(11) NULL,
            days_on_sale_12m_legacy INT(11) NULL,
            avg_units_per_day_in_stock_12m_legacy DECIMAL(12,6) NULL,
            lost_units_12m_legacy DECIMAL(18,4) NULL,
            lost_revenue_12m_legacy DECIMAL(18,4) NULL,
            legacy_source_version VARCHAR(50) NULL,
            imported_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY sop_legacy_product (product_id)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Insert or update a legacy history row keyed by product_id.
     *
     * @param array $data Column => value.
     * @return int|false Rows affected or false on failure.
     */
    public static function upsert_row( array $data ) {
        global $wpdb;

        $product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
        if ( $product_id <= 0 ) {
            return false;
        }

        $allowed  = array_flip( self::get_columns() );
        $filtered = array_intersect_key( $data, $allowed );

        $date_fields = array(
            'first_order_date_12m',
            'last_order_date_12m',
            'first_zero_alert_12m',
            'last_zero_alert_12m',
            'imported_at',
        );

        foreach ( $filtered as $column => $value ) {
            if ( in_array( $column, $date_fields, true ) ) {
                $filtered[ $column ] = self::normalize_date( $value );
                continue;
            }

            switch ( $column ) {
                case 'product_id':
                case 'supplier_id':
                case 'current_stock':
                case 'max_order_qty_per_month':
                case 'units_sold_12m':
                case 'order_count_12m':
                case 'alert_count_12m':
                case 'zero_alert_count_12m':
                case 'delivered_units_from_containers_12m':
                case 'container_delivery_count_12m':
                case 'stockout_days_12m_legacy':
                case 'days_on_sale_12m_legacy':
                case 'days_span_12m':
                    $filtered[ $column ] = ( '' === $value || null === $value ) ? null : (int) $value;
                    break;
                case 'revenue_12m':
                case 'avg_units_per_day_raw_12m':
                case 'avg_units_per_day_span_12m':
                case 'avg_units_per_day_in_stock_12m_legacy':
                case 'lost_units_12m_legacy':
                case 'lost_revenue_12m_legacy':
                    $filtered[ $column ] = ( '' === $value || null === $value ) ? null : (float) $value;
                    break;
                default:
                    $filtered[ $column ] = ( '' === $value ) ? null : $value;
                    break;
            }
        }

        if ( ! isset( $filtered['imported_at'] ) || ! $filtered['imported_at'] ) {
            $filtered['imported_at'] = current_time( 'mysql' );
        }

        $table = self::get_table_name();

        return $wpdb->replace( $table, $filtered );
    }

    /**
     * Normalise incoming date strings to MySQL format.
     *
     * @param string|null $value Raw date string.
     * @return string|null
     */
    protected static function normalize_date( $value ) {
        if ( empty( $value ) ) {
            return null;
        }

        if ( $value instanceof DateTime ) {
            return $value->format( 'Y-m-d H:i:s' );
        }

        $timestamp = strtotime( (string) $value );
        if ( false === $timestamp ) {
            return null;
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }
}

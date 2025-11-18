/**
 * Stock Order Plugin - Phase 1 (DB + Helpers)
 *
 * - Declares sop_DB class (schema + helpers).
 * - Defines all core Stock Order Plugin tables.
 * - Installs/updates tables using dbDelta() when the DB version changes.
 * - Stores current schema version in 'sop_db_version' option.
 * - Adds generic CRUD helpers for all SOP tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

if ( ! class_exists( 'sop_DB' ) ) {

    /**
     * Core DB helper for Stock Order Plugin.
     */
    class sop_DB {

        /**
         * Current schema version for this project.
         * Bump this when tables/columns change in future phases.
         */
        const VERSION = '1.0.0';

        /**
         * Return list of logical table keys => physical table names.
         *
         * @return array
         */
        public static function get_tables() {
            global $wpdb;

            $prefix = $wpdb->prefix;

            return array(
                'suppliers'            => $prefix . 'sop_suppliers',
                'stockout_log'         => $prefix . 'sop_stockout_log',
                'forecast_cache'       => $prefix . 'sop_forecast_cache',
                'forecast_cache_item'  => $prefix . 'sop_forecast_cache_items',
                'goods_in_session'     => $prefix . 'sop_goods_in_sessions',
                'goods_in_item'        => $prefix . 'sop_goods_in_items',
                'supplier_layouts'     => $prefix . 'sop_supplier_layouts',
            );
        }

        /**
         * Get a single table name by logical key.
         *
         * @param string $key
         * @return string|null
         */
        public static function get_table_name( $key ) {
            $tables = self::get_tables();
            return isset( $tables[ $key ] ) ? $tables[ $key ] : null;
        }

        /**
         * Ensure DB schema is installed / upgraded to current VERSION.
         */
        public static function maybe_install() {
            $current_version = get_option( 'sop_db_version', '' );

            if ( $current_version === self::VERSION ) {
                return;
            }

            self::install();

            update_option( 'sop_db_version', self::VERSION );
        }

        /**
         * Run dbDelta() for all plugin tables.
         */
        public static function install() {
            global $wpdb;

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $charset_collate = $wpdb->get_charset_collate();
            $tables          = self::get_tables();

            $sql = array();

            // 1. Suppliers (per-supplier settings & metadata).
            $sql[] = "CREATE TABLE {$tables['suppliers']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                wc_term_id BIGINT(20) UNSIGNED DEFAULT NULL,
                supplier_code VARCHAR(100) DEFAULT NULL,
                currency VARCHAR(10) NOT NULL DEFAULT 'GBP',
                lead_time_weeks SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
                holiday_rules LONGTEXT NULL,
                container_config LONGTEXT NULL,
                settings_json LONGTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY slug (slug),
                KEY wc_term_id (wc_term_id),
                KEY is_active (is_active)
            ) $charset_collate;";

            // 2. Stockout log (records periods when product stock is zero).
            $sql[] = "CREATE TABLE {$tables['stockout_log']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id BIGINT(20) UNSIGNED NOT NULL,
                variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                date_start DATETIME NOT NULL,
                date_end DATETIME DEFAULT NULL,
                source VARCHAR(50) DEFAULT 'runtime',
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY product_id (product_id),
                KEY variation_id (variation_id),
                KEY date_start (date_start),
                KEY date_end (date_end)
            ) $charset_collate;";

            // 3. Forecast cache (per supplier + parameter set).
            $sql[] = "CREATE TABLE {$tables['forecast_cache']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                supplier_id BIGINT(20) UNSIGNED NOT NULL,
                run_key VARCHAR(64) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'completed',
                params_hash VARCHAR(64) NOT NULL,
                params_json LONGTEXT NULL,
                lookback_days INT(11) NOT NULL DEFAULT 365,
                lead_time_days INT(11) NOT NULL DEFAULT 0,
                buffer_days INT(11) NOT NULL DEFAULT 0,
                analysis_start_date DATE DEFAULT NULL,
                analysis_end_date DATE DEFAULT NULL,
                created_at DATETIME NOT NULL,
                completed_at DATETIME DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY run_key (run_key),
                KEY supplier_id (supplier_id),
                KEY status (status),
                KEY params_hash (params_hash),
                KEY analysis_period (analysis_start_date, analysis_end_date)
            ) $charset_collate;";

            // 4. Forecast cache items (per product within a forecast run).
            $sql[] = "CREATE TABLE {$tables['forecast_cache_item']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                cache_id BIGINT(20) UNSIGNED NOT NULL,
                product_id BIGINT(20) UNSIGNED NOT NULL,
                variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                current_stock INT(11) NOT NULL DEFAULT 0,
                qty_sold BIGINT(20) NOT NULL DEFAULT 0,
                days_on_sale INT(11) NOT NULL DEFAULT 0,
                avg_per_day DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                avg_per_month DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                projected_stock_on_arrival INT(11) NOT NULL DEFAULT 0,
                suggested_order_qty INT(11) NOT NULL DEFAULT 0,
                max_order_qty_per_month INT(11) NOT NULL DEFAULT 0,
                flags_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY cache_id (cache_id),
                KEY product_id (product_id),
                KEY variation_id (variation_id)
            ) $charset_collate;";

            // 5. Goods-in sessions (header table for receiving containers/shipments).
            $sql[] = "CREATE TABLE {$tables['goods_in_session']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                supplier_id BIGINT(20) UNSIGNED NOT NULL,
                session_key VARCHAR(64) NOT NULL,
                reference VARCHAR(191) DEFAULT NULL,
                container_label VARCHAR(191) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                expected_arrival_date DATE DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                notes LONGTEXT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY session_key (session_key),
                KEY supplier_id (supplier_id),
                KEY status (status),
                KEY expected_arrival_date (expected_arrival_date)
            ) $charset_collate;";

            // 6. Goods-in items (line items within a goods-in session).
            $sql[] = "CREATE TABLE {$tables['goods_in_item']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                session_id BIGINT(20) UNSIGNED NOT NULL,
                product_id BIGINT(20) UNSIGNED NOT NULL,
                variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                ordered_qty INT(11) NOT NULL DEFAULT 0,
                received_qty INT(11) NOT NULL DEFAULT 0,
                damaged_qty INT(11) NOT NULL DEFAULT 0,
                missing_qty INT(11) NOT NULL DEFAULT 0,
                carton_number VARCHAR(100) DEFAULT NULL,
                line_notes LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY session_id (session_id),
                KEY product_id (product_id),
                KEY variation_id (variation_id),
                KEY carton_number (carton_number)
            ) $charset_collate;";

            // 7. Supplier layouts (per-supplier column layout configs).
            $sql[] = "CREATE TABLE {$tables['supplier_layouts']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                supplier_id BIGINT(20) UNSIGNED NOT NULL,
                context VARCHAR(50) NOT NULL DEFAULT 'order_export',
                layout_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_context (supplier_id, context),
                KEY context (context)
            ) $charset_collate;";

            foreach ( $sql as $statement ) {
                dbDelta( $statement );
            }
        }

        /*
         * ---------------------------------------------------------------------
         * Generic CRUD helpers
         * ---------------------------------------------------------------------
         */

        /**
         * Insert a row into one of the SOP tables.
         *
         * - Auto-fills created_at if not provided (all SOP tables have created_at).
         *
         * @param string $table_key Logical table key (e.g. 'suppliers').
         * @param array  $data      Column => value pairs.
         * @param array|string|null $format Optional wpdb format.
         * @return int|false Inserted row ID on success, false on failure.
         */
        public static function insert( $table_key, array $data, $format = null ) {
            global $wpdb;

            $table = self::get_table_name( $table_key );
            if ( ! $table ) {
                return false;
            }

            if ( ! isset( $data['created_at'] ) ) {
                $data['created_at'] = current_time( 'mysql' );
            }

            $result = $wpdb->insert( $table, $data, $format );

            if ( false === $result ) {
                return false;
            }

            return (int) $wpdb->insert_id;
        }

        /**
         * Update rows in one of the SOP tables.
         *
         * NOTE: Requires a non-empty $where to avoid accidental mass-updates.
         *
         * @param string $table_key
         * @param array  $data
         * @param array  $where
         * @param array|string|null $format
         * @param array|string|null $where_format
         * @return int|false Number of rows affected, or false on failure.
         */
        public static function update( $table_key, array $data, array $where, $format = null, $where_format = null ) {
            global $wpdb;

            $table = self::get_table_name( $table_key );
            if ( ! $table || empty( $where ) ) {
                return false;
            }

            $result = $wpdb->update( $table, $data, $where, $format, $where_format );

            return ( false === $result ) ? false : (int) $result;
        }

        /**
         * Delete rows from one of the SOP tables.
         *
         * @param string $table_key
         * @param array  $where
         * @param array|string|null $where_format
         * @return int|false Number of rows deleted, or false on failure.
         */
        public static function delete( $table_key, array $where, $where_format = null ) {
            global $wpdb;

            $table = self::get_table_name( $table_key );
            if ( ! $table || empty( $where ) ) {
                return false;
            }

            $result = $wpdb->delete( $table, $where, $where_format );

            return ( false === $result ) ? false : (int) $result;
        }

        /**
         * Get a single row from one of the SOP tables by simple equality WHERE.
         *
         * Example:
         *   sop_DB::get_row( 'suppliers', array( 'id' => 5 ) );
         *
         * @param string $table_key
         * @param array  $where Column => value pairs for equality.
         * @param string $output OBJECT, ARRAY_A, or ARRAY_N.
         * @return mixed|null
         */
        public static function get_row( $table_key, array $where, $output = OBJECT ) {
            global $wpdb;

            $table = self::get_table_name( $table_key );
            if ( ! $table || empty( $where ) ) {
                return null;
            }

            $conditions = array();
            $values     = array();

            foreach ( $where as $column => $value ) {
                $conditions[] = $column . ' = %s';
                $values[]     = $value;
            }

            $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $conditions ) . ' LIMIT 1';

            $prepared = $wpdb->prepare( $sql, $values );

            return $wpdb->get_row( $prepared, $output );
        }

        /**
         * Get multiple rows from one of the SOP tables by simple equality WHERE.
         *
         * Example:
         *   sop_DB::get_results( 'stockout_log', array( 'product_id' => 123 ) );
         *
         * @param string $table_key
         * @param array  $where   Column => value pairs (all ANDed together). Empty = no WHERE.
         * @param string $output  OBJECT, ARRAY_A, or ARRAY_N.
         * @return array
         */
        public static function get_results( $table_key, array $where = array(), $output = OBJECT ) {
            global $wpdb;

            $table = self::get_table_name( $table_key );
            if ( ! $table ) {
                return array();
            }

            $conditions = array();
            $values     = array();

            if ( ! empty( $where ) ) {
                foreach ( $where as $column => $value ) {
                    $conditions[] = $column . ' = %s';
                    $values[]     = $value;
                }
            }

            $sql = "SELECT * FROM {$table}";

            if ( ! empty( $conditions ) ) {
                $sql .= ' WHERE ' . implode( ' AND ', $conditions );
            }

            $prepared = ! empty( $values ) ? $wpdb->prepare( $sql, $values ) : $sql;

            return $wpdb->get_results( $prepared, $output );
        }
    }

    /**
     * Global convenience: get table name by key.
     *
     * @param string $key
     * @return string|null
     */
    function sop_get_table_name( $key ) {
        return sop_DB::get_table_name( $key );
    }

    /**
     * Global convenience: insert row into SOP table.
     */
    function sop_db_insert( $table_key, array $data, $format = null ) {
        return sop_DB::insert( $table_key, $data, $format );
    }

    /**
     * Global convenience: update rows in SOP table.
     */
    function sop_db_update( $table_key, array $data, array $where, $format = null, $where_format = null ) {
        return sop_DB::update( $table_key, $data, $where, $format, $where_format );
    }

    /**
     * Global convenience: delete rows from SOP table.
     */
    function sop_db_delete( $table_key, array $where, $where_format = null ) {
        return sop_DB::delete( $table_key, $where, $where_format );
    }

    /**
     * Global convenience: fetch single row from SOP table.
     */
    function sop_db_get_row( $table_key, array $where, $output = OBJECT ) {
        return sop_DB::get_row( $table_key, $where, $output );
    }

    /**
     * Global convenience: fetch multiple rows from SOP table.
     */
    function sop_db_get_results( $table_key, array $where = array(), $output = OBJECT ) {
        return sop_DB::get_results( $table_key, $where, $output );
    }

    /**
     * Hook DB installer into admin_init so it runs on first admin page load
     * after this snippet is activated or when VERSION changes.
     */
    add_action( 'admin_init', function () {
        if ( current_user_can( 'manage_options' ) ) {
            sop_DB::maybe_install();
        }
    } );
}

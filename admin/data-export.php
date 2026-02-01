<?php
/**
 * Stock Order Plugin - Phase 4 (Data Export)
 * Admin Settings - Data Export tab + CSV streaming
 *
 * File version: 1.0.3
 * - Use capability helper for Stock Order UI access.
 * - Add row count hints and bundle README row counts for exports.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_data_export_get_suppliers' ) ) {
    /**
     * Fetch suppliers for dropdowns and metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    function sop_data_export_get_suppliers() {
        global $wpdb;

        $table = $wpdb->prefix . 'sop_suppliers';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $exists ) {
            return array();
        }

        $rows = $wpdb->get_results( "SELECT id, name, currency FROM {$table} ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return is_array( $rows ) ? $rows : array();
    }
}

if ( ! function_exists( 'sop_data_export_table_exists' ) ) {
    /**
     * Check if a SOP table exists.
     *
     * @param string $table Table name.
     * @return bool
     */
    function sop_data_export_table_exists( $table ) {
        global $wpdb;

        if ( '' === $table ) {
            return false;
        }

        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }
}

if ( ! function_exists( 'sop_data_export_table_row_count' ) ) {
    /**
     * Get row count for a SOP table.
     *
     * @param string $table Table name.
     * @return int|null
     */
    function sop_data_export_table_row_count( $table ) {
        global $wpdb;

        if ( '' === $table || ! sop_data_export_table_exists( $table ) ) {
            return null;
        }

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( null === $count ) {
            return null;
        }

        return (int) $count;
    }
}

if ( ! function_exists( 'sop_data_export_product_snapshot_count' ) ) {
    /**
     * Estimate product snapshot rows for a supplier filter.
     *
     * @param int $supplier_id Supplier ID or 0 for all.
     * @return int
     */
    function sop_data_export_product_snapshot_count( $supplier_id ) {
        global $wpdb;

        $supplier_id = (int) $supplier_id;
        $posts = $wpdb->posts;
        $meta  = $wpdb->postmeta;

        if ( $supplier_id > 0 ) {
            $sql = "
                SELECT COUNT(DISTINCT p.ID)
                FROM {$posts} p
                INNER JOIN {$meta} pm ON pm.post_id = p.ID
                WHERE p.post_type = 'product'
                  AND p.post_status IN ( 'publish', 'private' )
                  AND pm.meta_key = '_sop_supplier_id'
                  AND pm.meta_value = %d
            ";
            $prepared = $wpdb->prepare( $sql, $supplier_id );
        } else {
            $sql = "
                SELECT COUNT(DISTINCT p.ID)
                FROM {$posts} p
                INNER JOIN {$meta} pm ON pm.post_id = p.ID
                WHERE p.post_type = 'product'
                  AND p.post_status IN ( 'publish', 'private' )
                  AND pm.meta_key = '_sop_supplier_id'
                  AND CAST(pm.meta_value AS SIGNED) > 0
            ";
            $prepared = $sql;
        }

        $count = $wpdb->get_var( $prepared ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $count;
    }
}

if ( ! function_exists( 'sop_render_data_export_tab' ) ) {
    /**
     * Render the Data Export settings tab.
     *
     * @return void
     */
    function sop_render_data_export_tab() {
        if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
            return;
        }

        $suppliers = sop_data_export_get_suppliers();
        $action_url = admin_url( 'admin-post.php' );
        $format_rows = static function ( $count ) {
            if ( null === $count ) {
                return __( 'Rows: —', 'sop' );
            }
            return sprintf( __( 'Rows: %s', 'sop' ), number_format_i18n( (int) $count ) );
        };

        $table_counts = array();
        $table_map = array(
            'suppliers'            => 'suppliers',
            'preorder_sheet'       => 'preorder_sheet',
            'preorder_sheet_lines' => 'preorder_sheet_lines',
            'goods_in_sessions'    => 'goods_in_session',
            'goods_in_items'       => 'goods_in_item',
            'stockout_log'         => 'stockout_log',
            'forecast_cache'       => 'forecast_cache',
            'forecast_cache_items' => 'forecast_cache_item',
            'supplier_layouts'     => 'supplier_layouts',
        );

        foreach ( $table_map as $dataset => $table_key ) {
            $table = function_exists( 'sop_get_table_name' ) ? sop_get_table_name( $table_key ) : '';
            if ( '' === $table ) {
                $table = $GLOBALS['wpdb']->prefix . 'sop_' . $table_key;
            }
            $table_counts[ $dataset ] = sop_data_export_table_row_count( $table );
        }

        $legacy_table = $GLOBALS['wpdb']->prefix . 'sop_legacy_product_history';
        $legacy_count = sop_data_export_table_row_count( $legacy_table );
        $product_snapshot_count = sop_data_export_product_snapshot_count( 0 );
        $bundle_counts = array_merge( array( $product_snapshot_count ), array_values( $table_counts ) );
        $bundle_unknown = in_array( null, $bundle_counts, true );
        $bundle_total = $bundle_unknown ? null : array_sum( array_map( 'intval', $bundle_counts ) );
        ?>
        <div class="sop-settings-section">
            <h2><?php esc_html_e( 'Data Export', 'sop' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Exports may contain sensitive business data. Store files securely and share only with approved staff.', 'sop' ); ?>
            </p>

            <hr />

            <h3><?php esc_html_e( 'AI bundle (ZIP)', 'sop' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'Bundle multiple CSVs into one ZIP for external analysis. Treat exported data as sensitive business information.', 'sop' ); ?>
            </p>
            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_bundle_zip" />
                <?php wp_nonce_field( 'sop_data_export_bundle_zip', 'sop_data_export_bundle_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sop_bundle_supplier_id"><?php esc_html_e( 'Supplier filter', 'sop' ); ?></label>
                        </th>
                        <td>
                            <select name="supplier_id" id="sop_bundle_supplier_id">
                                <option value="0"><?php esc_html_e( 'All suppliers', 'sop' ); ?></option>
                                <?php foreach ( $suppliers as $supplier ) : ?>
                                    <?php
                                    $sid = isset( $supplier['id'] ) ? (int) $supplier['id'] : 0;
                                    $label = isset( $supplier['name'] ) ? (string) $supplier['name'] : '';
                                    ?>
                                    <option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Inbound schedule columns', 'sop' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_inbound_schedule" value="1" checked="checked" />
                                <?php esc_html_e( 'Include inbound schedule columns', 'sop' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sop_bundle_days_back"><?php esc_html_e( 'Stockout log days back', 'sop' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="sop_bundle_days_back" name="days_back" min="1" value="365" class="small-text" />
                        </td>
                    </tr>
                </table>

                <?php
                submit_button( __( 'Download AI bundle ZIP', 'sop' ), 'primary' );
                ?>
                <span class="description"><?php echo esc_html( $format_rows( $bundle_total ) ); ?></span>
            </form>

            <hr />

            <h3><?php esc_html_e( 'Product snapshot (AI-friendly)', 'sop' ); ?></h3>
            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="products_snapshot" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sop_export_supplier_id"><?php esc_html_e( 'Supplier filter', 'sop' ); ?></label>
                        </th>
                        <td>
                            <select name="supplier_id" id="sop_export_supplier_id">
                                <option value="0"><?php esc_html_e( 'All suppliers', 'sop' ); ?></option>
                                <?php foreach ( $suppliers as $supplier ) : ?>
                                    <?php
                                    $sid = isset( $supplier['id'] ) ? (int) $supplier['id'] : 0;
                                    $label = isset( $supplier['name'] ) ? (string) $supplier['name'] : '';
                                    ?>
                                    <option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Inbound schedule columns', 'sop' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_inbound_schedule" value="1" checked="checked" />
                                <?php esc_html_e( 'Include inbound schedule columns', 'sop' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Download CSV', 'sop' ), 'primary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $product_snapshot_count ) ); ?></span>
            </form>

            <hr />

            <h3><?php esc_html_e( 'SOP tables', 'sop' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'If a CSV downloads with only headers, that dataset currently has 0 rows.', 'sop' ); ?>
            </p>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="suppliers" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download suppliers CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['suppliers'] ) ); ?></span>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="preorder_sheet" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download preorder_sheet CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['preorder_sheet'] ) ); ?></span>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="preorder_sheet_lines" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <p>
                    <label for="sop_export_sheet_id"><?php esc_html_e( 'Sheet ID (optional)', 'sop' ); ?></label>
                    <input type="number" id="sop_export_sheet_id" name="sheet_id" min="0" class="small-text" />
                </p>
                <?php submit_button( __( 'Download preorder_sheet_lines CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['preorder_sheet_lines'] ) ); ?></span>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="goods_in_sessions" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download goods_in_sessions CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['goods_in_sessions'] ) ); ?></span>
                <?php if ( null !== $table_counts['goods_in_sessions'] && 0 === (int) $table_counts['goods_in_sessions'] ) : ?>
                    <p class="description"><?php esc_html_e( '0 rows currently.', 'sop' ); ?></p>
                <?php endif; ?>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="goods_in_items" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <p>
                    <label for="sop_export_session_id"><?php esc_html_e( 'Session ID (optional)', 'sop' ); ?></label>
                    <input type="number" id="sop_export_session_id" name="session_id" min="0" class="small-text" />
                </p>
                <?php submit_button( __( 'Download goods_in_items CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['goods_in_items'] ) ); ?></span>
                <?php if ( null !== $table_counts['goods_in_items'] && 0 === (int) $table_counts['goods_in_items'] ) : ?>
                    <p class="description"><?php esc_html_e( '0 rows currently.', 'sop' ); ?></p>
                <?php endif; ?>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="stockout_log" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <p>
                    <label for="sop_export_days_back"><?php esc_html_e( 'Days back', 'sop' ); ?></label>
                    <input type="number" id="sop_export_days_back" name="days_back" min="1" value="365" class="small-text" />
                </p>
                <p>
                    <label for="sop_export_product_id"><?php esc_html_e( 'Product ID (optional)', 'sop' ); ?></label>
                    <input type="number" id="sop_export_product_id" name="product_id" min="0" class="small-text" />
                </p>
                <?php submit_button( __( 'Download stockout_log CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['stockout_log'] ) ); ?></span>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="forecast_cache" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download forecast_cache CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['forecast_cache'] ) ); ?></span>
                <?php if ( null !== $table_counts['forecast_cache'] && 0 === (int) $table_counts['forecast_cache'] ) : ?>
                    <p class="description"><?php esc_html_e( '0 rows currently.', 'sop' ); ?></p>
                <?php endif; ?>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="forecast_cache_items" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download forecast_cache_items CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['forecast_cache_items'] ) ); ?></span>
                <?php if ( null !== $table_counts['forecast_cache_items'] && 0 === (int) $table_counts['forecast_cache_items'] ) : ?>
                    <p class="description"><?php esc_html_e( '0 rows currently.', 'sop' ); ?></p>
                <?php endif; ?>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="supplier_layouts" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download supplier_layouts CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $table_counts['supplier_layouts'] ) ); ?></span>
                <?php if ( null !== $table_counts['supplier_layouts'] && 0 === (int) $table_counts['supplier_layouts'] ) : ?>
                    <p class="description"><?php esc_html_e( '0 rows currently.', 'sop' ); ?></p>
                <?php endif; ?>
            </form>

            <hr />

            <h3><?php esc_html_e( 'Legacy history', 'sop' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'Exports legacy history if the table exists. Otherwise a friendly message is returned.', 'sop' ); ?>
            </p>
            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="legacy_product_history" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download legacy_product_history CSV', 'sop' ), 'secondary' ); ?>
                <span class="description"><?php echo esc_html( $format_rows( $legacy_count ) ); ?></span>
            </form>
        </div>
        <?php
    }
}

if ( ! function_exists( 'sop_data_export_write_message' ) ) {
    /**
     * Write a single-row message CSV.
     *
     * @param resource $out Output handle.
     * @param string   $message Message text.
     * @return void
     */
    function sop_data_export_write_message( $out, $message ) {
        fputcsv( $out, array( 'message' ) );
        fputcsv( $out, array( $message ) );
    }
}

if ( ! function_exists( 'sop_data_export_get_table_columns' ) ) {
    /**
     * Get column names for a table.
     *
     * @param string $table Table name.
     * @return array
     */
    function sop_data_export_get_table_columns( $table ) {
        global $wpdb;

        $columns = array();
        $rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( isset( $row['Field'] ) ) {
                    $columns[] = $row['Field'];
                }
            }
        }

        return $columns;
    }
}

if ( ! function_exists( 'sop_data_export_stream_table' ) ) {
    /**
     * Stream a table as CSV.
     *
     * @param string   $table Table name.
     * @param string   $where_sql Optional WHERE SQL without "WHERE".
     * @param array    $params SQL parameters for WHERE.
     * @param resource $out Output handle.
     * @return int
     */
    function sop_data_export_stream_table( $table, $where_sql, array $params, $out ) {
        global $wpdb;

        $columns = sop_data_export_get_table_columns( $table );
        if ( empty( $columns ) ) {
            sop_data_export_write_message( $out, 'No columns found for export.' );
            return 0;
        }

        fputcsv( $out, $columns );

        $limit  = 500;
        $offset = 0;
        $row_count = 0;

        do {
            $sql = "SELECT * FROM {$table}";
            if ( '' !== $where_sql ) {
                $sql .= ' WHERE ' . $where_sql;
            }
            $sql .= ' LIMIT %d OFFSET %d';

            $params_exec = array_merge( $params, array( $limit, $offset ) );
            $prepared    = $wpdb->prepare( $sql, $params_exec );
            $rows        = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ( empty( $rows ) || ! is_array( $rows ) ) {
                break;
            }

            foreach ( $rows as $row ) {
                $line = array();
                foreach ( $columns as $col ) {
                    $line[] = isset( $row[ $col ] ) ? $row[ $col ] : '';
                }
                fputcsv( $out, $line );
                $row_count++;
            }

            $offset += $limit;
        } while ( count( $rows ) === $limit );

        return $row_count;
    }
}

if ( ! function_exists( 'sop_data_export_format_schedule' ) ) {
    /**
     * Format inbound schedule entries for CSV.
     *
     * @param array $entries Schedule entries.
     * @return string
     */
    function sop_data_export_format_schedule( $entries ) {
        if ( empty( $entries ) || ! is_array( $entries ) ) {
            return '';
        }

        $parts = array();
        foreach ( $entries as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $date = isset( $entry['arrival_date'] ) ? (string) $entry['arrival_date'] : '';
            if ( '' === $date ) {
                $date = 'unknown';
            }
            $qty = isset( $entry['qty'] ) ? (float) $entry['qty'] : 0.0;
            if ( $qty <= 0 ) {
                continue;
            }
            $parts[] = $date . ':' . $qty;
        }

        return implode( '|', $parts );
    }
}

if ( ! function_exists( 'sop_data_export_normalize_meta_value' ) ) {
    /**
     * Normalize meta values for CSV output.
     *
     * @param mixed $value Meta value.
     * @return string
     */
    function sop_data_export_normalize_meta_value( $value ) {
        if ( is_array( $value ) ) {
            return implode( '|', array_map( 'strval', $value ) );
        }
        if ( is_scalar( $value ) ) {
            return (string) $value;
        }
        return '';
    }
}

if ( ! function_exists( 'sop_data_export_stream_dataset_csv' ) ) {
    /**
     * Stream a dataset CSV to a provided file handle.
     *
     * @param string   $dataset Dataset key.
     * @param array    $args    Dataset arguments.
     * @param resource $out     Output handle.
     * @return int
     */
    function sop_data_export_stream_dataset_csv( $dataset, array $args, $out ) {
        $dataset = sanitize_key( $dataset );
        if ( '' === $dataset ) {
            sop_data_export_write_message( $out, 'Missing export dataset.' );
            return 0;
        }

        $supplier_filter   = isset( $args['supplier_id'] ) ? (int) $args['supplier_id'] : 0;
        $include_schedule  = ! empty( $args['include_inbound_schedule'] );
        $sheet_id          = isset( $args['sheet_id'] ) ? (int) $args['sheet_id'] : 0;
        $session_id        = isset( $args['session_id'] ) ? (int) $args['session_id'] : 0;
        $days_back         = isset( $args['days_back'] ) ? (int) $args['days_back'] : 365;
        $product_id_filter = isset( $args['product_id'] ) ? (int) $args['product_id'] : 0;

        if ( $days_back <= 0 ) {
            $days_back = 365;
        }

        global $wpdb;

        if ( 'products_snapshot' === $dataset ) {
            $supplier_rows = sop_data_export_get_suppliers();
            $supplier_map = array();
            foreach ( $supplier_rows as $supplier ) {
                $sid = isset( $supplier['id'] ) ? (int) $supplier['id'] : 0;
                if ( $sid > 0 ) {
                    $supplier_map[ $sid ] = array(
                        'name'     => isset( $supplier['name'] ) ? (string) $supplier['name'] : '',
                        'currency' => isset( $supplier['currency'] ) ? (string) $supplier['currency'] : '',
                    );
                }
            }

            $inbound_map = function_exists( 'sop_db_get_inbound_qty_map' ) ? sop_db_get_inbound_qty_map( 0 ) : array();
            if ( ! is_array( $inbound_map ) ) {
                $inbound_map = array();
            }

            $schedule_map = array();
            if ( $include_schedule ) {
                if ( function_exists( 'sop_db_get_inbound_schedule_by_arrival_date' ) ) {
                    try {
                        $ref = new ReflectionFunction( 'sop_db_get_inbound_schedule_by_arrival_date' );
                        if ( $ref->getNumberOfRequiredParameters() > 0 ) {
                            $schedule_map = sop_db_get_inbound_schedule_by_arrival_date( 0 );
                        } else {
                            $schedule_map = sop_db_get_inbound_schedule_by_arrival_date();
                        }
                    } catch ( Throwable $e ) {
                        $schedule_map = array();
                    }
                } elseif ( function_exists( 'sop_db_get_inbound_schedule_map' ) ) {
                    $schedule_map = sop_db_get_inbound_schedule_map( 0 );
                }
            }
            if ( ! is_array( $schedule_map ) ) {
                $schedule_map = array();
            }

            $columns = array(
                'product_id',
                'sku',
                'product_name',
                'supplier_id',
                'supplier_name',
                'supplier_currency',
                'manage_stock',
                'stock_qty',
                'stock_status',
                'cost_gbp',
                'cost_rmb',
                'cost_usd',
                'cost_eur',
                'price',
                'regular_price',
                'sale_price',
                'location',
                'min_order_qty',
                'max_order_qty_per_month',
                'supplier_skus',
                'inbound_qty_total',
            );

            if ( $include_schedule ) {
                $columns[] = 'inbound_schedule';
            }

            fputcsv( $out, $columns );

            $paged = 1;
            $per_page = 200;

            $row_count = 0;
            do {
                $meta_query = array();
                if ( $supplier_filter > 0 ) {
                    $meta_query[] = array(
                        'key'     => '_sop_supplier_id',
                        'value'   => $supplier_filter,
                        'compare' => '=',
                    );
                } else {
                    $meta_query[] = array(
                        'key'     => '_sop_supplier_id',
                        'compare' => 'EXISTS',
                    );
                }

                $query = new WP_Query(
                    array(
                        'post_type'      => array( 'product' ),
                        'post_status'    => array( 'publish', 'private' ),
                        'posts_per_page' => $per_page,
                        'paged'          => $paged,
                        'fields'         => 'ids',
                        'no_found_rows'  => true,
                        'meta_query'     => $meta_query,
                    )
                );

                if ( empty( $query->posts ) ) {
                    break;
                }

                foreach ( $query->posts as $product_id ) {
                    $product_id = (int) $product_id;
                    if ( $product_id <= 0 ) {
                        continue;
                    }

                    $product = wc_get_product( $product_id );
                    if ( ! $product ) {
                        continue;
                    }

                    $supplier_id = (int) get_post_meta( $product_id, '_sop_supplier_id', true );
                    if ( $supplier_id <= 0 ) {
                        continue;
                    }
                    if ( $supplier_filter > 0 && $supplier_id !== $supplier_filter ) {
                        continue;
                    }

                    $supplier_name = isset( $supplier_map[ $supplier_id ]['name'] ) ? $supplier_map[ $supplier_id ]['name'] : '';
                    $supplier_currency = isset( $supplier_map[ $supplier_id ]['currency'] ) ? $supplier_map[ $supplier_id ]['currency'] : '';

                    $manage_stock = $product->managing_stock() ? 1 : 0;
                    $stock_qty = $product->get_stock_quantity();
                    if ( null === $stock_qty ) {
                        $stock_qty = 0;
                    }
                    $stock_status = $product->get_stock_status();

                    $location = get_post_meta( $product_id, '_sop_bin_location', true );
                    if ( '' === $location ) {
                        $location = get_post_meta( $product_id, '_product_location', true );
                    }
                    $location = sop_data_export_normalize_meta_value( $location );
                    $supplier_skus = sop_data_export_normalize_meta_value( get_post_meta( $product_id, '_sop_supplier_skus', true ) );

                    $row = array(
                        $product_id,
                        $product->get_sku(),
                        $product->get_name(),
                        $supplier_id,
                        $supplier_name,
                        $supplier_currency,
                        $manage_stock,
                        (int) $stock_qty,
                        $stock_status,
                        get_post_meta( $product_id, '_cogs_value', true ),
                        get_post_meta( $product_id, '_sop_cost_rmb', true ),
                        get_post_meta( $product_id, '_sop_cost_usd', true ),
                        get_post_meta( $product_id, '_sop_cost_eur', true ),
                        get_post_meta( $product_id, '_price', true ),
                        get_post_meta( $product_id, '_regular_price', true ),
                        get_post_meta( $product_id, '_sale_price', true ),
                        $location,
                        get_post_meta( $product_id, '_sop_min_order_qty', true ),
                        get_post_meta( $product_id, 'max_order_qty_per_month', true ),
                        $supplier_skus,
                        isset( $inbound_map[ $product_id ] ) ? (float) $inbound_map[ $product_id ] : 0.0,
                    );

                    if ( $include_schedule ) {
                        $schedule_entries = isset( $schedule_map[ $product_id ] ) ? $schedule_map[ $product_id ] : array();
                        $row[] = sop_data_export_format_schedule( $schedule_entries );
                    }

                    fputcsv( $out, $row );
                    $row_count++;
                }

                $paged++;
            } while ( true );

            return $row_count;
        }

        $table_map = array(
            'suppliers'            => 'suppliers',
            'preorder_sheet'       => 'preorder_sheet',
            'preorder_sheet_lines' => 'preorder_sheet_lines',
            'goods_in_sessions'    => 'goods_in_session',
            'goods_in_items'       => 'goods_in_item',
            'stockout_log'         => 'stockout_log',
            'forecast_cache'       => 'forecast_cache',
            'forecast_cache_items' => 'forecast_cache_item',
            'supplier_layouts'     => 'supplier_layouts',
        );

        if ( isset( $table_map[ $dataset ] ) ) {
            $table_key = $table_map[ $dataset ];
            $table = function_exists( 'sop_get_table_name' ) ? sop_get_table_name( $table_key ) : '';
            if ( '' === $table ) {
                $table = $wpdb->prefix . 'sop_' . $table_key;
            }

            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( ! $table_exists ) {
                sop_data_export_write_message( $out, 'Table not found: ' . $table );
                return 0;
            }

            $where_sql = '';
            $params = array();

            if ( 'preorder_sheet_lines' === $dataset && $sheet_id > 0 ) {
                $where_sql = 'sheet_id = %d';
                $params[] = $sheet_id;
            } elseif ( 'goods_in_items' === $dataset && $session_id > 0 ) {
                $where_sql = 'session_id = %d';
                $params[] = $session_id;
            } elseif ( 'stockout_log' === $dataset ) {
                $from_ts = current_time( 'timestamp', true ) - ( $days_back * DAY_IN_SECONDS );
                $from_date = gmdate( 'Y-m-d H:i:s', $from_ts );

                $where_parts = array();
                $where_parts[] = 'date_start >= %s';
                $params[] = $from_date;
                if ( $product_id_filter > 0 ) {
                    $where_parts[] = 'product_id = %d';
                    $params[] = $product_id_filter;
                }
                $where_sql = implode( ' AND ', $where_parts );
            }

            return sop_data_export_stream_table( $table, $where_sql, $params, $out );
        }

        if ( 'legacy_product_history' === $dataset ) {
            $table = $wpdb->prefix . 'sop_legacy_product_history';
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( ! $table_exists ) {
                sop_data_export_write_message( $out, 'Legacy history table not found.' );
                return 0;
            }

            return sop_data_export_stream_table( $table, '', array(), $out );
        }

        sop_data_export_write_message( $out, 'Unknown dataset: ' . $dataset );
        return 0;
    }
}

/**
 * Handle CSV export requests for SOP datasets.
 *
 * @return void
 */
function sop_handle_data_export_csv() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to export data.', 'sop' ) );
    }

    check_admin_referer( 'sop_data_export_csv', 'sop_data_export_nonce' );

    $dataset = isset( $_POST['dataset'] ) ? sanitize_key( wp_unslash( $_POST['dataset'] ) ) : '';
    if ( '' === $dataset ) {
        wp_die( esc_html__( 'Missing export dataset.', 'sop' ) );
    }

    $filename = 'sop-export-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv';
    $filename = sanitize_file_name( $filename );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );

    echo "\xEF\xBB\xBF";
    $out = fopen( 'php://output', 'w' );
    if ( ! $out ) {
        wp_die( esc_html__( 'Unable to open export stream.', 'sop' ) );
    }

    $args = array(
        'supplier_id'              => isset( $_POST['supplier_id'] ) ? absint( wp_unslash( $_POST['supplier_id'] ) ) : 0,
        'include_inbound_schedule' => ! empty( $_POST['include_inbound_schedule'] ),
        'sheet_id'                 => isset( $_POST['sheet_id'] ) ? absint( wp_unslash( $_POST['sheet_id'] ) ) : 0,
        'session_id'               => isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0,
        'days_back'                => isset( $_POST['days_back'] ) ? absint( wp_unslash( $_POST['days_back'] ) ) : 365,
        'product_id'               => isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0,
    );

    sop_data_export_stream_dataset_csv( $dataset, $args, $out );
    exit;
}

add_action( 'admin_post_sop_data_export_csv', 'sop_handle_data_export_csv' );

/**
 * Handle ZIP bundle export for SOP datasets.
 *
 * @return void
 */
function sop_handle_data_export_bundle_zip() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to export data.', 'sop' ) );
    }

    check_admin_referer( 'sop_data_export_bundle_zip', 'sop_data_export_bundle_nonce' );

    if ( ! class_exists( 'ZipArchive' ) ) {
        wp_die( esc_html__( 'ZIP export is not available on this server. Please ask your host to enable ZipArchive.', 'sop' ) );
    }

    $supplier_id = isset( $_POST['supplier_id'] ) ? absint( wp_unslash( $_POST['supplier_id'] ) ) : 0;
    $include_schedule = ! empty( $_POST['include_inbound_schedule'] );
    $days_back = isset( $_POST['days_back'] ) ? absint( wp_unslash( $_POST['days_back'] ) ) : 365;
    $sheet_id = isset( $_POST['sheet_id'] ) ? absint( wp_unslash( $_POST['sheet_id'] ) ) : 0;
    $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
    $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;

    if ( $days_back <= 0 ) {
        $days_back = 365;
    }

    $args_base = array(
        'supplier_id'              => $supplier_id,
        'include_inbound_schedule' => $include_schedule,
        'days_back'                => $days_back,
        'sheet_id'                 => $sheet_id,
        'session_id'               => $session_id,
        'product_id'               => $product_id,
    );

    $zip_path = wp_tempnam( 'sop-ai-bundle' );
    if ( ! $zip_path ) {
        wp_die( esc_html__( 'Unable to create temporary ZIP file.', 'sop' ) );
    }
    if ( substr( $zip_path, -4 ) !== '.zip' ) {
        $renamed = $zip_path . '.zip';
        @rename( $zip_path, $renamed );
        $zip_path = $renamed;
    }

    $zip = new ZipArchive();
    $opened = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
    if ( true !== $opened ) {
        @unlink( $zip_path );
        wp_die( esc_html__( 'Unable to open ZIP archive for writing.', 'sop' ) );
    }

    global $wpdb;
    $legacy_table = $wpdb->prefix . 'sop_legacy_product_history';
    $legacy_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) );

    $bundle_items = array(
        array( 'dataset' => 'products_snapshot',    'filename' => '01-product_snapshot.csv' ),
        array( 'dataset' => 'suppliers',            'filename' => '02-suppliers.csv' ),
        array( 'dataset' => 'preorder_sheet',       'filename' => '03-preorder_sheet.csv' ),
        array( 'dataset' => 'preorder_sheet_lines', 'filename' => '04-preorder_sheet_lines.csv' ),
        array( 'dataset' => 'goods_in_sessions',    'filename' => '05-goods_in_sessions.csv' ),
        array( 'dataset' => 'goods_in_items',       'filename' => '06-goods_in_items.csv' ),
        array( 'dataset' => 'stockout_log',         'filename' => '07-stockout_log.csv' ),
        array( 'dataset' => 'forecast_cache',       'filename' => '08-forecast_cache.csv' ),
        array( 'dataset' => 'forecast_cache_items', 'filename' => '09-forecast_cache_items.csv' ),
        array( 'dataset' => 'supplier_layouts',     'filename' => '10-supplier_layouts.csv' ),
    );

    if ( $legacy_exists ) {
        $bundle_items[] = array( 'dataset' => 'legacy_product_history', 'filename' => '11-legacy_product_history.csv' );
    }

    $temp_files = array();
    $included_files = array();
    $skipped_files = array();
    $file_row_counts = array();

    foreach ( $bundle_items as $item ) {
        $dataset = $item['dataset'];
        $filename = $item['filename'];

        $csv_path = wp_tempnam( 'sop-export-' . $dataset );
        if ( ! $csv_path ) {
            $skipped_files[] = $filename . ' (temp file failed)';
            continue;
        }
        if ( substr( $csv_path, -4 ) !== '.csv' ) {
            $csv_renamed = $csv_path . '.csv';
            @rename( $csv_path, $csv_renamed );
            $csv_path = $csv_renamed;
        }

        $fh = fopen( $csv_path, 'w' );
        if ( ! $fh ) {
            $skipped_files[] = $filename . ' (open failed)';
            @unlink( $csv_path );
            continue;
        }

        $row_count = sop_data_export_stream_dataset_csv( $dataset, $args_base, $fh );
        fclose( $fh );

        if ( ! $zip->addFile( $csv_path, $filename ) ) {
            $skipped_files[] = $filename . ' (zip add failed)';
            @unlink( $csv_path );
            continue;
        }

        $temp_files[] = $csv_path;
        $included_files[] = $filename;
        $file_row_counts[ $filename ] = (int) $row_count;
    }

    $readme_lines = array(
        'SOP AI Bundle Export',
        'Timestamp (UTC): ' . gmdate( 'Y-m-d H:i:s' ),
        'Supplier filter: ' . ( $supplier_id > 0 ? (string) $supplier_id : 'All' ),
        'Include inbound schedule: ' . ( $include_schedule ? 'Yes' : 'No' ),
        'Stockout days back: ' . (string) $days_back,
        'Files:',
    );

    if ( ! empty( $included_files ) ) {
        foreach ( $included_files as $included_file ) {
            $count = isset( $file_row_counts[ $included_file ] ) ? (int) $file_row_counts[ $included_file ] : 0;
            $readme_lines[] = '- ' . $included_file . ' (' . $count . ' rows)';
        }
    } else {
        $readme_lines[] = '- None';
    }
    if ( ! empty( $skipped_files ) ) {
        $readme_lines[] = 'Skipped files: ' . implode( ', ', $skipped_files );
    }

    $zip->addFromString( 'README.txt', implode( "\n", $readme_lines ) . "\n" );
    $zip->close();

    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }

    $zip_filename = 'sop-ai-bundle-' . gmdate( 'Ymd-His' ) . '.zip';
    $zip_filename = sanitize_file_name( $zip_filename );

    nocache_headers();
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename=' . $zip_filename );
    header( 'Content-Length: ' . filesize( $zip_path ) );

    readfile( $zip_path );

    foreach ( $temp_files as $temp_path ) {
        @unlink( $temp_path );
    }
    @unlink( $zip_path );
    exit;
}

add_action( 'admin_post_sop_data_export_bundle_zip', 'sop_handle_data_export_bundle_zip' );

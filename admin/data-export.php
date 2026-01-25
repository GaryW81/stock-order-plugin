<?php
/**
 * Stock Order Plugin - Phase 4 (Data Export)
 * Admin Settings - Data Export tab + CSV streaming
 *
 * File version: 1.0.0
 * - Add Data Export settings tab with CSV exports for SOP datasets.
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

if ( ! function_exists( 'sop_render_data_export_tab' ) ) {
    /**
     * Render the Data Export settings tab.
     *
     * @return void
     */
    function sop_render_data_export_tab() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $suppliers = sop_data_export_get_suppliers();
        $action_url = admin_url( 'admin-post.php' );
        ?>
        <div class="sop-settings-section">
            <h2><?php esc_html_e( 'Data Export', 'sop' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Exports may contain sensitive business data. Store files securely and share only with approved staff.', 'sop' ); ?>
            </p>

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
            </form>

            <hr />

            <h3><?php esc_html_e( 'SOP tables', 'sop' ); ?></h3>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="suppliers" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download suppliers CSV', 'sop' ), 'secondary' ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="preorder_sheet" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download preorder_sheet CSV', 'sop' ), 'secondary' ); ?>
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
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="goods_in_sessions" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download goods_in_sessions CSV', 'sop' ), 'secondary' ); ?>
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
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="forecast_cache" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download forecast_cache CSV', 'sop' ), 'secondary' ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="forecast_cache_items" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download forecast_cache_items CSV', 'sop' ), 'secondary' ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="sop_data_export_csv" />
                <input type="hidden" name="dataset" value="supplier_layouts" />
                <?php wp_nonce_field( 'sop_data_export_csv', 'sop_data_export_nonce' ); ?>
                <?php submit_button( __( 'Download supplier_layouts CSV', 'sop' ), 'secondary' ); ?>
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
     * @return void
     */
    function sop_data_export_stream_table( $table, $where_sql, array $params, $out ) {
        global $wpdb;

        $columns = sop_data_export_get_table_columns( $table );
        if ( empty( $columns ) ) {
            sop_data_export_write_message( $out, 'No columns found for export.' );
            return;
        }

        fputcsv( $out, $columns );

        $limit  = 500;
        $offset = 0;

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
            }

            $offset += $limit;
        } while ( count( $rows ) === $limit );
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

/**
 * Handle CSV export requests for SOP datasets.
 *
 * @return void
 */
function sop_handle_data_export_csv() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
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

    global $wpdb;

    if ( 'products_snapshot' === $dataset ) {
        $supplier_filter = isset( $_POST['supplier_id'] ) ? absint( wp_unslash( $_POST['supplier_id'] ) ) : 0;
        $include_schedule = ! empty( $_POST['include_inbound_schedule'] );

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
            }

            $paged++;
        } while ( true );

        exit;
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
            exit;
        }

        $where_sql = '';
        $params = array();

        if ( 'preorder_sheet_lines' === $dataset ) {
            $sheet_id = isset( $_POST['sheet_id'] ) ? absint( wp_unslash( $_POST['sheet_id'] ) ) : 0;
            if ( $sheet_id > 0 ) {
                $where_sql = 'sheet_id = %d';
                $params[] = $sheet_id;
            }
        } elseif ( 'goods_in_items' === $dataset ) {
            $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
            if ( $session_id > 0 ) {
                $where_sql = 'session_id = %d';
                $params[] = $session_id;
            }
        } elseif ( 'stockout_log' === $dataset ) {
            $days_back = isset( $_POST['days_back'] ) ? absint( wp_unslash( $_POST['days_back'] ) ) : 365;
            if ( $days_back <= 0 ) {
                $days_back = 365;
            }
            $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
            $from_ts = current_time( 'timestamp', true ) - ( $days_back * DAY_IN_SECONDS );
            $from_date = gmdate( 'Y-m-d H:i:s', $from_ts );

            $where_parts = array();
            $where_parts[] = 'date_start >= %s';
            $params[] = $from_date;
            if ( $product_id > 0 ) {
                $where_parts[] = 'product_id = %d';
                $params[] = $product_id;
            }
            $where_sql = implode( ' AND ', $where_parts );
        }

        sop_data_export_stream_table( $table, $where_sql, $params, $out );
        exit;
    }

    if ( 'legacy_product_history' === $dataset ) {
        $table = $wpdb->prefix . 'sop_legacy_product_history';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            sop_data_export_write_message( $out, 'Legacy history table not found.' );
            exit;
        }

        sop_data_export_stream_table( $table, '', array(), $out );
        exit;
    }

    sop_data_export_write_message( $out, 'Unknown dataset: ' . $dataset );
    exit;
}

add_action( 'admin_post_sop_data_export_csv', 'sop_handle_data_export_csv' );

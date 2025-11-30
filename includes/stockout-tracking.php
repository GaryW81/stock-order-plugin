<?php
/**
 * Stock Order Plugin - Phase 4
 * Stockout tracking + maintenance hooks
 * File version: 1.0.2
 *
 * - Hooks WooCommerce stock changes to stockout open/close helpers.
 * - Ensures a daily maintenance cron runs to prune old stockout logs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle stock changes to open/close stockout logs.
 *
 * @param WC_Product $product_with_stock Product instance from Woo hooks.
 */
function sop_handle_product_stock_change( $product_with_stock ) {
    if ( ! $product_with_stock instanceof WC_Product ) {
        return;
    }

    $parent_id    = (int) $product_with_stock->get_parent_id();
    $product_id   = $parent_id > 0 ? $parent_id : (int) $product_with_stock->get_id();
    $variation_id = $parent_id > 0 ? (int) $product_with_stock->get_id() : 0;

    if ( $product_id <= 0 ) {
        return;
    }

    if ( ! $product_with_stock->managing_stock() ) {
        if ( function_exists( 'sop_stockout_close' ) ) {
            sop_stockout_close( $product_id, $variation_id, 'wc_stock', 'Auto close: stock management disabled' );
        }
        return;
    }

    $qty    = $product_with_stock->get_stock_quantity();
    $qty    = ( null === $qty ) ? 0 : (int) $qty;
    $status = (string) $product_with_stock->get_stock_status();

    if ( 'outofstock' === $status || $qty <= 0 ) {
        if ( function_exists( 'sop_stockout_open' ) ) {
            sop_stockout_open( $product_id, $variation_id, 'wc_stock', 'Auto open from WC stock change' );
        }
    } else {
        if ( function_exists( 'sop_stockout_close' ) ) {
            sop_stockout_close( $product_id, $variation_id, 'wc_stock', 'Auto close from WC stock change' );
        }
    }
}

/**
 * Handle stock status changes to ensure stockout logs reflect status-only updates.
 *
 * @param int         $product_id   Product ID.
 * @param string      $stock_status New stock status.
 * @param WC_Product  $product      Product instance if provided.
 */
function sop_handle_product_stock_status_change( $product_id, $stock_status, $product ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    if ( $product instanceof WC_Product ) {
        sop_handle_product_stock_change( $product );
        return;
    }

    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return;
    }

    $maybe_product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
    if ( $maybe_product instanceof WC_Product ) {
        sop_handle_product_stock_change( $maybe_product );
    }
}

/**
 * Ensure the daily maintenance cron is scheduled.
 */
function sop_ensure_daily_maintenance_cron() {
    if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
        return;
    }

    if ( ! wp_next_scheduled( 'sop_daily_maintenance' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sop_daily_maintenance' );
    }
}

/**
 * Run daily maintenance tasks, including stockout log pruning.
 */
function sop_run_daily_maintenance_tasks() {
    $years = (int) apply_filters( 'sop_log_retention_years', 5 );
    if ( $years < 1 ) {
        $years = 1;
    }

    if ( function_exists( 'sop_prune_old_stockout_logs' ) ) {
        sop_prune_old_stockout_logs( $years );
    }

    // Backfill open stockout intervals for any products currently at zero stock.
    if ( function_exists( 'sop_backfill_open_stockouts_for_zero_stock_products' ) ) {
        sop_backfill_open_stockouts_for_zero_stock_products();
    }
}

add_action( 'woocommerce_product_set_stock', 'sop_handle_product_stock_change', 20 );
add_action( 'woocommerce_product_set_stock_status', 'sop_handle_product_stock_status_change', 20, 3 );
add_action( 'sop_daily_maintenance', 'sop_run_daily_maintenance_tasks' );

/**
 * Register Stockout Log (Debug) admin page.
 *
 * @return void
 */
function sop_register_stockout_log_debug_page() {
    $parent_slug = 'sop_stock_order_dashboard';

    add_submenu_page(
        $parent_slug,
        __( 'Stockout Log (Debug)', 'sop' ),
        __( 'Stockout Log (Debug)', 'sop' ),
        'manage_woocommerce',
        'sop_stockout_log_debug',
        'sop_render_stockout_log_debug_page'
    );
}
add_action( 'admin_menu', 'sop_register_stockout_log_debug_page', 30 );

/**
 * Render the Stockout Log (Debug) admin page.
 *
 * Shows recent stockout intervals from the sop_stockout_log table.
 *
 * @return void
 */
function sop_render_stockout_log_debug_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'sop_stockout_log';

    $days_back  = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $product_id = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $days_back  = $days_back > 0 ? $days_back : 30;
    $limit      = 200;

    $from_date = gmdate( 'Y-m-d H:i:s', time() - ( $days_back * DAY_IN_SECONDS ) );

    $where = $wpdb->prepare( 'WHERE date_start >= %s', $from_date );
    if ( $product_id > 0 ) {
        $where .= $wpdb->prepare( ' AND product_id = %d', $product_id );
    }

    $sql = "
        SELECT *
        FROM {$table_name}
        {$where}
        ORDER BY date_start DESC
        LIMIT %d
    ";
    $sql = $wpdb->prepare( $sql, $limit );

    $rows = $wpdb->get_results( $sql );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Stockout Log (Debug)', 'sop' ); ?></h1>

        <form method="get" style="margin-bottom: 1em;">
            <input type="hidden" name="page" value="sop_stockout_log_debug" />
            <label for="sop_stockout_days"><?php esc_html_e( 'Days back:', 'sop' ); ?></label>
            <input type="number" id="sop_stockout_days" name="days" value="<?php echo esc_attr( $days_back ); ?>" min="1" step="1" />
            <label for="sop_stockout_product_id"><?php esc_html_e( 'Product ID (optional):', 'sop' ); ?></label>
            <input type="number" id="sop_stockout_product_id" name="product_id" value="<?php echo $product_id > 0 ? esc_attr( $product_id ) : ''; ?>" min="0" step="1" />
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'sop' ); ?></button>
        </form>

        <?php if ( empty( $rows ) ) : ?>
            <p><?php esc_html_e( 'No stockout log entries found for the selected period.', 'sop' ); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Product ID', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Product', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Date start', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Date end', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Duration (days)', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Notes', 'sop' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <?php
                        $pid  = isset( $row->product_id ) ? (int) $row->product_id : 0;
                        $post = ( $pid > 0 ) ? get_post( $pid ) : null;
                        $sku  = '';

                        if ( $pid > 0 && function_exists( 'wc_get_product' ) ) {
                            $product_obj = wc_get_product( $pid );
                            if ( $product_obj ) {
                                $sku = $product_obj->get_sku();
                            }
                        }

                        $name         = ( $post instanceof WP_Post ) ? $post->post_title : '';
                        $start_ts     = ! empty( $row->date_start ) ? strtotime( $row->date_start ) : false;
                        $end_ts       = ! empty( $row->date_end ) ? strtotime( $row->date_end ) : false;
                        $now_ts       = time();
                        $duration_days = 0;

                        if ( $start_ts ) {
                            $end_for_calc = $end_ts ? $end_ts : $now_ts;
                            if ( $end_for_calc >= $start_ts ) {
                                $duration_days = ( $end_for_calc - $start_ts ) / DAY_IN_SECONDS;
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( $pid ); ?></td>
                            <td><?php echo esc_html( $sku ); ?></td>
                            <td>
                                <?php
                                if ( $post instanceof WP_Post ) {
                                    echo esc_html( $name );
                                } else {
                                    esc_html_e( '(unknown)', 'sop' );
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ( ! empty( $row->date_start ) ) {
                                    echo esc_html( $row->date_start );
                                } else {
                                    esc_html_e( '-', 'sop' );
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ( ! empty( $row->date_end ) ) {
                                    echo esc_html( $row->date_end );
                                } else {
                                    esc_html_e( '(open)', 'sop' );
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html( number_format_i18n( $duration_days, 2 ) ); ?></td>
                            <td>
                                <?php
                                if ( isset( $row->source ) && $row->source !== '' ) {
                                    echo esc_html( $row->source );
                                } else {
                                    esc_html_e( '-', 'sop' );
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ( isset( $row->notes ) && $row->notes !== '' ) {
                                    echo esc_html( $row->notes );
                                } else {
                                    esc_html_e( '-', 'sop' );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

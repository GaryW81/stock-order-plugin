<?php
/**
 * Stock Order Plugin - Phase 4.1
 * System Status (admin only)
 * File version: 1.0.1
 * - Add diagnostics tab with environment, DB, cron, templates, and last bootstrap error.
 * - Add debug report download and polish last error display.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_render_system_status_tab' ) ) {
    function sop_render_system_status_tab() {
        if ( function_exists( 'sop_get_admin_capability' ) && ! current_user_can( sop_get_admin_capability() ) ) {
            return;
        }
        global $wpdb;

        $plugin_version = defined( 'SOP_PLUGIN_VERSION' ) ? SOP_PLUGIN_VERSION : '';
        $wp_version     = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '';
        $php_version    = function_exists( 'phpversion' ) ? phpversion() : '';
        $wc_active      = function_exists( 'sop_is_woocommerce_active' ) ? sop_is_woocommerce_active() : ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) );
        $wc_version     = defined( 'WC_VERSION' ) ? WC_VERSION : '';
        $is_multisite   = is_multisite();
        $is_main        = $is_multisite ? is_main_site() : true;
        $blog_id        = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
        $host           = wp_parse_url( home_url(), PHP_URL_HOST );
        $host           = is_string( $host ) ? $host : '';
        $suffixes       = apply_filters( 'sop_allowed_site_host_suffixes', array( 'wilson-organisation.com' ) );
        $suffixes       = is_array( $suffixes ) ? $suffixes : array( 'wilson-organisation.com' );
        $site_allowed   = function_exists( 'sop_is_allowed_site_context' ) ? sop_is_allowed_site_context() : true;
        $next_cron      = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( 'sop_daily_maintenance' ) : false;
        $next_cron_str  = $next_cron ? date_i18n( 'Y-m-d H:i:s', $next_cron ) : __( 'Not scheduled', 'sop' );

        $tables = array();
        if ( class_exists( 'sop_DB' ) && method_exists( 'sop_DB', 'get_tables' ) ) {
            $tables = sop_DB::get_tables();
        }
        if ( ! is_array( $tables ) ) {
            $tables = array();
        }

        $templates = array(
            'purchase-order-summary-template.xlsx'      => trailingslashit( SOP_PLUGIN_DIR ) . 'includes/templates/purchase-order-summary-template.xlsx',
            'purchase-order-summary-rmb-template.xlsx'  => trailingslashit( SOP_PLUGIN_DIR ) . 'includes/templates/purchase-order-summary-rmb-template.xlsx',
        );

        $last_error = function_exists( 'sop_get_last_bootstrap_error' ) ? sop_get_last_bootstrap_error() : array();
        $last_time  = isset( $last_error['time'] ) ? (int) $last_error['time'] : 0;
        $last_when  = $last_time ? date_i18n( 'Y-m-d H:i:s', $last_time ) : '';
        $has_last_error = ( ! empty( $last_error['code'] ) || ! empty( $last_error['message'] ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'System Status', 'sop' ); ?></h1>

            <h2><?php esc_html_e( 'Last Bootstrap Error', 'sop' ); ?></h2>
            <div class="notice notice-info" style="padding:12px 16px;">
                <?php if ( ! empty( $last_error ) ) : ?>
                    <p><strong><?php esc_html_e( 'Code:', 'sop' ); ?></strong> <?php echo esc_html( isset( $last_error['code'] ) ? $last_error['code'] : '' ); ?></p>
                    <p><strong><?php esc_html_e( 'Message:', 'sop' ); ?></strong> <?php echo esc_html( isset( $last_error['message'] ) ? $last_error['message'] : '' ); ?></p>
                    <p><strong><?php esc_html_e( 'Time:', 'sop' ); ?></strong> <?php echo esc_html( $last_when ); ?></p>
                <?php else : ?>
                    <p><?php esc_html_e( 'No bootstrap errors recorded.', 'sop' ); ?></p>
                <?php endif; ?>
            </div>
            <?php if ( $has_last_error ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sop_clear_last_bootstrap_error' ); ?>
                    <input type="hidden" name="action" value="sop_clear_last_bootstrap_error" />
                    <button type="submit" class="button"><?php esc_html_e( 'Clear last error', 'sop' ); ?></button>
                </form>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Debug Export', 'sop' ); ?></h2>
            <p><?php esc_html_e( 'Download a JSON snapshot of this System Status page for diagnostics.', 'sop' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sop_download_sop_debug_report' ); ?>
                <input type="hidden" name="action" value="sop_download_sop_debug_report" />
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Download debug report (.json)', 'sop' ); ?></button>
            </form>

            <h2><?php esc_html_e( 'Environment', 'sop' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr><th><?php esc_html_e( 'Plugin version', 'sop' ); ?></th><td><?php echo esc_html( $plugin_version ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'WordPress version', 'sop' ); ?></th><td><?php echo esc_html( $wp_version ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'PHP version', 'sop' ); ?></th><td><?php echo esc_html( $php_version ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'WooCommerce active', 'sop' ); ?></th><td><?php echo esc_html( $wc_active ? 'Yes' : 'No' ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'WooCommerce version', 'sop' ); ?></th><td><?php echo esc_html( $wc_version ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Multisite', 'sop' ); ?></th><td><?php echo esc_html( $is_multisite ? 'Yes' : 'No' ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Main site', 'sop' ); ?></th><td><?php echo esc_html( $is_main ? 'Yes' : 'No' ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Blog ID', 'sop' ); ?></th><td><?php echo esc_html( (string) $blog_id ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Host', 'sop' ); ?></th><td><?php echo esc_html( $host ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Allowed host suffixes', 'sop' ); ?></th><td><?php echo esc_html( implode( ', ', $suffixes ) ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Site allowed', 'sop' ); ?></th><td><?php echo esc_html( $site_allowed ? 'Yes' : 'No' ); ?></td></tr>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Cron', 'sop' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr><th><?php esc_html_e( 'Next SOP maintenance', 'sop' ); ?></th><td><?php echo esc_html( $next_cron_str ); ?><?php echo $next_cron ? ' (' . esc_html( (string) $next_cron ) . ')' : ''; ?></td></tr>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Database tables', 'sop' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <thead><tr><th><?php esc_html_e( 'Table', 'sop' ); ?></th><th><?php esc_html_e( 'Present', 'sop' ); ?></th></tr></thead>
                <tbody>
                <?php if ( empty( $tables ) ) : ?>
                    <tr><td colspan="2"><?php esc_html_e( 'No table metadata available.', 'sop' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $tables as $table_name ) : ?>
                        <?php
                        $present = false;
                        if ( ! empty( $table_name ) && isset( $wpdb ) ) {
                            $present = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
                        }
                        ?>
                        <tr><td><?php echo esc_html( $table_name ); ?></td><td><?php echo esc_html( $present ? 'Yes' : 'No' ); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Templates', 'sop' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <thead><tr><th><?php esc_html_e( 'Template', 'sop' ); ?></th><th><?php esc_html_e( 'Present', 'sop' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $templates as $label => $path ) : ?>
                    <tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( file_exists( $path ) ? 'Yes' : 'No' ); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if ( ! function_exists( 'sop_handle_clear_last_bootstrap_error' ) ) {
    function sop_handle_clear_last_bootstrap_error() {
        if ( ! current_user_can( 'manage_options' ) ) {
            if ( function_exists( 'sop_get_admin_capability' ) && ! current_user_can( sop_get_admin_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to do that.', 'sop' ) );
            }
        }
        check_admin_referer( 'sop_clear_last_bootstrap_error' );
        if ( function_exists( 'sop_clear_last_bootstrap_error' ) ) {
            sop_clear_last_bootstrap_error();
        }
        wp_safe_redirect( admin_url( 'admin.php?page=sop_stock_order&tab=status' ) );
        exit;
    }
}
add_action( 'admin_post_sop_clear_last_bootstrap_error', 'sop_handle_clear_last_bootstrap_error' );

if ( ! function_exists( 'sop_handle_download_sop_debug_report' ) ) {
    function sop_handle_download_sop_debug_report() {
        if ( ! current_user_can( 'manage_options' ) ) {
            if ( function_exists( 'sop_get_admin_capability' ) && ! current_user_can( sop_get_admin_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to do that.', 'sop' ) );
            }
        }
        check_admin_referer( 'sop_download_sop_debug_report' );

        $wc_active  = function_exists( 'sop_is_woocommerce_active' ) ? sop_is_woocommerce_active() : ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) );
        $wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : '';
        $host       = wp_parse_url( home_url(), PHP_URL_HOST );
        $host       = is_string( $host ) ? $host : '';
        $suffixes   = apply_filters( 'sop_allowed_site_host_suffixes', array( 'wilson-organisation.com' ) );
        $suffixes   = is_array( $suffixes ) ? $suffixes : array( 'wilson-organisation.com' );
        $site_ok    = function_exists( 'sop_is_allowed_site_context' ) ? sop_is_allowed_site_context() : true;
        $cron_next  = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( 'sop_daily_maintenance' ) : false;

        $tables = array();
        if ( class_exists( 'sop_DB' ) && method_exists( 'sop_DB', 'get_tables' ) ) {
            $tables = sop_DB::get_tables();
        }
        if ( ! is_array( $tables ) ) {
            $tables = array();
        }
        $table_presence = array();
        global $wpdb;
        foreach ( $tables as $table_name ) {
            $present = false;
            if ( ! empty( $table_name ) && isset( $wpdb ) ) {
                $present = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
            }
            $table_presence[ $table_name ] = $present;
        }

        $templates = array(
            'purchase-order-summary-template.xlsx'     => trailingslashit( SOP_PLUGIN_DIR ) . 'includes/templates/purchase-order-summary-template.xlsx',
            'purchase-order-summary-rmb-template.xlsx' => trailingslashit( SOP_PLUGIN_DIR ) . 'includes/templates/purchase-order-summary-rmb-template.xlsx',
        );
        $template_presence = array();
        foreach ( $templates as $label => $path ) {
            $template_presence[ $label ] = file_exists( $path );
        }

        $payload = array(
            'generated_at_utc'                          => gmdate( 'c' ),
            'plugin_version'                            => defined( 'SOP_PLUGIN_VERSION' ) ? SOP_PLUGIN_VERSION : '',
            'wordpress_version'                         => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
            'php_version'                               => PHP_VERSION,
            'woocommerce_active'                        => $wc_active ? 'Yes' : 'No',
            'woocommerce_version'                       => $wc_version,
            'multisite'                                 => is_multisite(),
            'is_main_site'                              => is_multisite() ? is_main_site() : true,
            'blog_id'                                   => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0,
            'home_url_host'                             => $host,
            'allowed_host_suffixes'                     => $suffixes,
            'site_allowed'                              => $site_ok,
            'cron_next_sop_daily_maintenance_timestamp' => $cron_next ? (int) $cron_next : 0,
            'cron_next_sop_daily_maintenance_local'     => $cron_next ? date_i18n( 'Y-m-d H:i:s', $cron_next ) : '',
            'db_tables_presence'                        => $table_presence,
            'templates_presence'                        => $template_presence,
            'last_bootstrap_error'                      => function_exists( 'sop_get_last_bootstrap_error' ) ? sop_get_last_bootstrap_error() : array(),
            'php_ini'                                   => array(
                'memory_limit'       => (string) ini_get( 'memory_limit' ),
                'max_execution_time' => (string) ini_get( 'max_execution_time' ),
                'upload_max_filesize'=> (string) ini_get( 'upload_max_filesize' ),
                'post_max_size'      => (string) ini_get( 'post_max_size' ),
            ),
        );

        $timestamp = gmdate( 'Ymd-His' );
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=stock-order-debug-' . $timestamp . '.json' );
        echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
        exit;
    }
}
add_action( 'admin_post_sop_download_sop_debug_report', 'sop_handle_download_sop_debug_report' );

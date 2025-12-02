<?php
/**
 * Plugin Name: Stock Order Plugin (SOP)
 * Description: Internal tool for supplier management, forecasting, pre-order sheets, and stock control.
 * Version: 0.1
 * Author: Wilson Organisation Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SOP_PLUGIN_VERSION' ) ) {
    define( 'SOP_PLUGIN_VERSION', '0.1' );
}

if ( ! defined( 'SOP_PLUGIN_DIR' ) ) {
    define( 'SOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SOP_PLUGIN_URL' ) ) {
    define( 'SOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Core includes.
require_once SOP_PLUGIN_DIR . 'includes/db-helpers.php';
require_once SOP_PLUGIN_DIR . 'includes/domain-helpers.php';
require_once SOP_PLUGIN_DIR . 'includes/stockout-tracking.php';
require_once SOP_PLUGIN_DIR . 'includes/helper-buffer.php';
require_once SOP_PLUGIN_DIR . 'includes/forecast-core.php';
require_once SOP_PLUGIN_DIR . 'includes/class-sop-legacy-history.php';
require_once SOP_PLUGIN_DIR . 'includes/supplier-meta-box.php';

// Admin-only includes.
if ( is_admin() ) {
    require_once SOP_PLUGIN_DIR . 'admin/settings-supplier.php';
    require_once SOP_PLUGIN_DIR . 'admin/product-mapping.php';
    require_once SOP_PLUGIN_DIR . 'admin/preorder-core.php';
    require_once SOP_PLUGIN_DIR . 'admin/preorder-ui.php';

    /**
     * Register Saved sheets submenu.
     */
    add_action(
        'admin_menu',
        function () {
            $parent_slug = function_exists( 'sop_preorder_get_stock_order_parent_slug' )
                ? sop_preorder_get_stock_order_parent_slug()
                : 'woocommerce';

            add_submenu_page(
                $parent_slug,
                __( 'Saved sheets', 'sop' ),
                __( 'Saved sheets', 'sop' ),
                'manage_woocommerce',
                'sop-preorder-sheets',
                'sop_render_preorder_sheets_page'
            );
        },
        100
    );
}

/**
 * Fired during plugin activation.
 */
function sop_activate_plugin() {
    if ( class_exists( 'sop_DB' ) ) {
        sop_DB::maybe_install();
    }

    if ( class_exists( 'SOP_Legacy_History' ) ) {
        SOP_Legacy_History::install();
    }

    if ( function_exists( 'sop_ensure_daily_maintenance_cron' ) ) {
        sop_ensure_daily_maintenance_cron();
    }

    // TODO: Add DB install routine when loader class is introduced.
}

/**
 * Fired during plugin deactivation.
 */
function sop_deactivate_plugin() {
    if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
        wp_clear_scheduled_hook( 'sop_daily_maintenance' );
    }

    // TODO: Add cleanup logic or scheduled event removal when available.
}

register_activation_hook( __FILE__, 'sop_activate_plugin' );
register_deactivation_hook( __FILE__, 'sop_deactivate_plugin' );

add_action(
    'admin_init',
    function () {
        if ( class_exists( 'SOP_Legacy_History' ) ) {
            SOP_Legacy_History::install();
        }

        if ( function_exists( 'sop_ensure_daily_maintenance_cron' ) ) {
            sop_ensure_daily_maintenance_cron();
        }
    }
);

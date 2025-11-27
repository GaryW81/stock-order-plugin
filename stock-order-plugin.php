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

    // TODO: Add DB install routine when loader class is introduced.
}

/**
 * Fired during plugin deactivation.
 */
function sop_deactivate_plugin() {
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
    }
);

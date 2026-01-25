<?php
/**
 * Main loader for the Stock Order Plugin.
 *
 * File version: 1.0.04
 * - Load stockout tracking module in core bootstrap.
 * - Goods-In v1: load goods-in admin core/UI.
 * - Add data export admin tab wiring.
 * - Remove unused setup_* placeholder methods.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coordinates loading SOP components.
 */
class sop_Loader {

    /**
     * Bootstraps plugin components.
     */
    public function init() {
        $this->load_core();

        if ( is_admin() ) {
            $this->load_admin();
        }
    }

    /**
     * Load shared/core dependencies.
     */
    protected function load_core() {
        require_once SOP_PLUGIN_DIR . 'includes/db-helpers.php';
        require_once SOP_PLUGIN_DIR . 'includes/domain-helpers.php';
        require_once SOP_PLUGIN_DIR . 'includes/stockout-tracking.php';
        require_once SOP_PLUGIN_DIR . 'includes/helper-buffer.php';
        require_once SOP_PLUGIN_DIR . 'includes/forecast-core.php';
        require_once SOP_PLUGIN_DIR . 'includes/supplier-meta-box.php';
    }

    /**
     * Load admin-specific code.
     */
    protected function load_admin() {
        require_once SOP_PLUGIN_DIR . 'admin/settings-supplier.php';
        require_once SOP_PLUGIN_DIR . 'admin/data-export.php';
        require_once SOP_PLUGIN_DIR . 'admin/product-mapping.php';
        require_once SOP_PLUGIN_DIR . 'admin/preorder-core.php';
        require_once SOP_PLUGIN_DIR . 'admin/preorder-ui.php';
        require_once SOP_PLUGIN_DIR . 'admin/goods-in-core.php';
        require_once SOP_PLUGIN_DIR . 'admin/goods-in-ui.php';
    }

}

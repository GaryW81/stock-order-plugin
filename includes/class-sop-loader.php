<?php
/**
 * Main loader for the Stock Order Plugin.
 *
 * File version: 1.0.03
 * - Load stockout tracking module in core bootstrap.
 * - Goods-In v1: load goods-in admin core/UI.
 * - Add data export admin tab wiring.
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

        $this->setup_suppliers();
        $this->setup_forecasting();
        $this->setup_preorder();
        $this->setup_goods_in();
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

    /**
     * Placeholder hook-up for supplier features.
     *
     * TODO: Integrate supplier helpers and admin pages via dedicated classes.
     */
    protected function setup_suppliers() {
        // Placeholder for future supplier feature wiring (menus, forms, integrations).
    }

    /**
     * Placeholder hook-up for forecasting features.
     *
     * TODO: Instantiate forecasting services and connect to cron/events.
     */
    protected function setup_forecasting() {
        // Placeholder for future forecasting bootstrap logic.
    }

    /**
     * Placeholder for preorder sheet / UI wiring.
     *
     * TODO: Register preorder admin pages, AJAX endpoints, and helpers.
     */
    protected function setup_preorder() {
        // Placeholder for future preorder sheet logic.
    }

    /**
     * Placeholder for goods-in / stock intake features.
     *
     * TODO: Wire up goods-in records, status tracking, and notifications.
     */
    protected function setup_goods_in() {
        // Placeholder for future goods-in handling.
    }
}

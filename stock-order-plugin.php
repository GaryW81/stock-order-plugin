<?php
/**
 * Plugin Name: Stock Order Plugin (SOP)
 * Description: Internal tool for supplier management, forecasting, pre-order sheets, and stock control.
 * Version: 0.1
 * Author: Wilson Organisation Ltd
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include core files
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/domain-helpers.php';
require_once __DIR__ . '/includes/helpers-buffer.php';
require_once __DIR__ . '/includes/forecast-core.php';
require_once __DIR__ . '/includes/meta-box-supplier.php';

// Admin-only files
if ( is_admin() ) {
    require_once __DIR__ . '/admin/settings-supplier.php';
    require_once __DIR__ . '/admin/product-mapping.php';
    require_once __DIR__ . '/admin/preorder-core.php';
    require_once __DIR__ . '/admin/preorder-ui.php';
}

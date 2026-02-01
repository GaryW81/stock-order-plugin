<?php
/**
 * Plugin Name: Stock Order
 * Description: Internal tool for suppliers, forecasting, purchase orders, container planning, goods-in, and labels/barcodes.
 * Version: 1.0.0
 * Author: Wilson Organisation Ltd
 * Text Domain: sop
 */

/**
 * Stock Order Plugin - Core Bootstrap & Lifecycle Hooks
 *
 * File version: 1.0.14
 * - Release: set official plugin name and v1.0.0 metadata.
 * - Contextual admin module loading to reduce wp-admin overhead and prevent 3rd-party AJAX UI interference.
 * - Load notes helpers for rich notes sanitization/rendering.
 * - Load Data Export module in admin bootstrap.
 * - Ensure sop_daily_maintenance cron is scheduled on activation and cleared on deactivation.
 * - Run sop_DB::maybe_install() on admin_init for safe schema upgrades.
 * - Remove TEMP Shiny CSV importer tool.
 * - Add Carton CSV importer admin page include.
 * - Add admin tabs grouping and hide secondary submenu items.
 * - Enforce Stock Order submenu layout and hide Stockout Log (Debug).
 * - Render grouped admin tabs on Stock Order screens.
 * - Hide submenu children after access check so tab links remain accessible.
 * - Add PO Details admin page include + hide from submenu list.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SOP_PLUGIN_VERSION' ) ) {
    define( 'SOP_PLUGIN_VERSION', '1.0.0' );
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
require_once SOP_PLUGIN_DIR . 'includes/notes-helpers.php';
require_once SOP_PLUGIN_DIR . 'includes/stockout-tracking.php';
require_once SOP_PLUGIN_DIR . 'includes/helper-buffer.php';
require_once SOP_PLUGIN_DIR . 'includes/forecast-core.php';
require_once SOP_PLUGIN_DIR . 'includes/class-sop-legacy-history.php';
require_once SOP_PLUGIN_DIR . 'includes/supplier-meta-box.php';
require_once SOP_PLUGIN_DIR . 'includes/barcode-code128.php';
require_once SOP_PLUGIN_DIR . 'includes/labels-core.php';
require_once SOP_PLUGIN_DIR . 'includes/class-sop-preorder-exporter-xlsx.php';

// Admin-only includes.
if ( is_admin() ) {
    $pagenow       = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    $is_doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
    $action        = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
    $is_sop_action = ( '' !== $action && 0 === strpos( $action, 'sop_' ) );
    $page          = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $is_sop_page   = ( '' !== $page && 0 === strpos( $page, 'sop' ) );
    $is_admin_post = ( 'admin-post.php' === $pagenow );

    if ( ( $is_doing_ajax && ! $is_sop_action ) || ( $is_admin_post && ! $is_sop_action ) ) {
        // Skip SOP admin modules on non-SOP AJAX/admin-post requests.
    } else {
        require_once SOP_PLUGIN_DIR . 'admin/settings-supplier.php';
        require_once SOP_PLUGIN_DIR . 'admin/admin-tabs.php';

        if ( $is_sop_page || $is_sop_action ) {
            require_once SOP_PLUGIN_DIR . 'admin/settings-labels.php';
            require_once SOP_PLUGIN_DIR . 'admin/data-export.php';
            require_once SOP_PLUGIN_DIR . 'admin/product-mapping.php';
            require_once SOP_PLUGIN_DIR . 'admin/preorder-core.php';
            require_once SOP_PLUGIN_DIR . 'admin/goods-in-core.php';
            require_once SOP_PLUGIN_DIR . 'admin/po-details.php';

            if ( $is_sop_page ) {
                require_once SOP_PLUGIN_DIR . 'admin/preorder-ui.php';
                require_once SOP_PLUGIN_DIR . 'admin/goods-in-ui.php';
                require_once SOP_PLUGIN_DIR . 'admin/carton-csv-importer.php';
            }
        }

        /**
         * Register Saved sheets submenu.
         */
        add_action(
            'admin_menu',
            function () {
                $parent_slug = 'sop_stock_order_dashboard';

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

        add_action( 'admin_notices', 'sop_admin_tabs_render_if_sop_screen', 1 );
        add_action( 'admin_menu', 'sop_admin_menu_register_group_links', 95 );
        add_action( 'admin_menu', 'sop_admin_menu_hide_group_children', 10000 );
        add_action( 'admin_head', 'sop_admin_menu_hide_group_children_late', 0 );
        add_filter( 'submenu_file', 'sop_admin_tabs_fix_submenu_highlight', 10, 2 );
    }
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
    } elseif ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_event' ) ) {
        if ( ! wp_next_scheduled( 'sop_daily_maintenance' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sop_daily_maintenance' );
        }
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
        if ( class_exists( 'sop_DB' ) ) {
            sop_DB::maybe_install();
        }

        if ( class_exists( 'SOP_Legacy_History' ) ) {
            SOP_Legacy_History::install();
        }

        if ( function_exists( 'sop_ensure_daily_maintenance_cron' ) ) {
            sop_ensure_daily_maintenance_cron();
        }
    }
);

// Hide default WP admin footer text on Stock Order Plugin screens only.
if ( ! function_exists( 'sop_should_hide_footer_for_screen' ) ) {
    /**
     * Determine if the current screen is a Stock Order Plugin screen.
     *
     * @param WP_Screen|null $screen Current screen.
     * @return bool
     */
    function sop_should_hide_footer_for_screen( $screen ) {
        if ( ! $screen || ! isset( $screen->id ) ) {
            return false;
        }

        $screen_id = (string) $screen->id;
        $matches   = array(
            'sop_',
            'sop-',
            'stock_order',
            'stock-order',
        );

        foreach ( $matches as $needle ) {
            if ( false !== strpos( $screen_id, $needle ) ) {
                return true;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'sop_admin_tabs_render_if_sop_screen' ) ) {
    /**
     * Render grouped admin tabs only on Stock Order screens.
     *
     * @return void
     */
    function sop_admin_tabs_render_if_sop_screen() {
        if ( ! function_exists( 'get_current_screen' ) || ! function_exists( 'sop_should_hide_footer_for_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! sop_should_hide_footer_for_screen( $screen ) ) {
            return;
        }

        if ( function_exists( 'sop_admin_tabs_render_for_current_page' ) ) {
            sop_admin_tabs_render_for_current_page();
        }
    }
}

if ( ! function_exists( 'sop_admin_menu_register_group_links' ) ) {
    /**
     * Register visible group links under the Stock Order menu.
     *
     * @return void
     */
    function sop_admin_menu_register_group_links() {
        $parent_slug = function_exists( 'sop_preorder_get_stock_order_parent_slug' )
            ? sop_preorder_get_stock_order_parent_slug()
            : 'sop_stock_order_dashboard';

        add_submenu_page(
            $parent_slug,
            __( 'Suppliers', 'sop' ),
            __( 'Suppliers', 'sop' ),
            'manage_woocommerce',
            'sop_stock_order_suppliers',
            'sop_admin_tabs_render_suppliers_redirect'
        );
    }
}

if ( ! function_exists( 'sop_admin_menu_hide_group_children' ) ) {
    /**
     * Hide secondary submenu items and rename primary menu labels.
     *
     * @return void
     */
    function sop_admin_menu_hide_group_children() {
        $parent_slug = function_exists( 'sop_preorder_get_stock_order_parent_slug' )
            ? sop_preorder_get_stock_order_parent_slug()
            : 'sop_stock_order_dashboard';

        global $submenu;
        if ( empty( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
            return;
        }

        foreach ( $submenu[ $parent_slug ] as $index => $item ) {
            if ( ! isset( $item[2] ) ) {
                continue;
            }

            switch ( $item[2] ) {
                case $parent_slug:
                    $submenu[ $parent_slug ][ $index ][0] = __( 'Dashboard', 'sop' );
                    break;
                case 'sop_stock_order':
                    $submenu[ $parent_slug ][ $index ][0] = __( 'Settings', 'sop' );
                    break;
                case 'sop_stock_order_suppliers':
                    $submenu[ $parent_slug ][ $index ][0] = __( 'Suppliers', 'sop' );
                    break;
                case 'sop-preorder-sheet':
                    $submenu[ $parent_slug ][ $index ][0] = __( 'Purchase Orders', 'sop' );
                    break;
                case 'sop-forecast-debug':
                    $submenu[ $parent_slug ][ $index ][0] = __( 'Forecasting', 'sop' );
                    break;
            }
        }

        $desired_order = array(
            $parent_slug,
            'sop_stock_order',
            'sop_stock_order_suppliers',
            'sop-preorder-sheet',
            'sop-goods-in',
            'sop-forecast-debug',
        );

        $ordered = array();
        $used    = array();

        foreach ( $desired_order as $slug ) {
            foreach ( $submenu[ $parent_slug ] as $item ) {
                if ( isset( $item[2] ) && $item[2] === $slug ) {
                    $ordered[]     = $item;
                    $used[ $slug ] = true;
                    break;
                }
            }
        }

        foreach ( $submenu[ $parent_slug ] as $item ) {
            if ( empty( $item[2] ) ) {
                continue;
            }
            if ( isset( $used[ $item[2] ] ) ) {
                continue;
            }
            $ordered[] = $item;
        }

        if ( ! empty( $ordered ) ) {
            $submenu[ $parent_slug ] = $ordered;
        }
    }
}

if ( ! function_exists( 'sop_admin_menu_hide_group_children_late' ) ) {
    /**
     * Hide submenu children after access checks run.
     *
     * @return void
     */
    function sop_admin_menu_hide_group_children_late() {
        $parent_slug = function_exists( 'sop_preorder_get_stock_order_parent_slug' )
            ? sop_preorder_get_stock_order_parent_slug()
            : 'sop_stock_order_dashboard';

        global $submenu;
        if ( empty( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
            return;
        }

        $hidden = array(
            'sop_products_by_supplier',
            'sop-preorder-sheets',
            'sop-carton-csv-import',
            'sop-po-details',
            'sop_stockout_log_debug',
        );

        $submenu[ $parent_slug ] = array_values(
            array_filter(
                $submenu[ $parent_slug ],
                function ( $item ) use ( $hidden ) {
                    if ( ! isset( $item[2] ) ) {
                        return true;
                    }
                    return ! in_array( $item[2], $hidden, true );
                }
            )
        );
    }
}

add_filter(
    'admin_footer_text',
    function ( $text ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( sop_should_hide_footer_for_screen( $screen ) ) {
            return '';
        }
        return $text;
    },
    999
);

add_filter(
    'update_footer',
    function ( $text ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( sop_should_hide_footer_for_screen( $screen ) ) {
            return '';
        }
        return $text;
    },
    999
);

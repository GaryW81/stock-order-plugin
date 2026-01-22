<?php
/**
 * Stock Order Plugin - Phase 2
 * Admin tab navigation + submenu highlight helpers
 *
 * File version: 1.0.3
 * - Wire Purchase Orders tabs (Saved POs + Carton Import) to group + menu highlight.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_admin_tabs_get_groups' ) ) {
    /**
     * Get tab groups for Stock Order admin pages.
     *
     * @return array
     */
    function sop_admin_tabs_get_groups() {
        return array(
            'settings'        => array(
                'title' => __( 'Settings', 'sop' ),
                'pages' => array(
                    array(
                        'slug' => 'sop_stock_order',
                        'label' => __( 'General Settings', 'sop' ),
                        'args' => array( 'tab' => 'general' ),
                    ),
                    array(
                        'slug' => 'sop_stock_order',
                        'label' => __( 'Labels & Barcodes', 'sop' ),
                        'args' => array( 'tab' => 'labels' ),
                    ),
                ),
            ),
            'suppliers'       => array(
                'title' => __( 'Suppliers', 'sop' ),
                'pages' => array(
                    array(
                        'slug' => 'sop_stock_order',
                        'label' => __( 'Supplier Settings', 'sop' ),
                        'args' => array( 'tab' => 'suppliers' ),
                    ),
                    array(
                        'slug' => 'sop_products_by_supplier',
                        'label' => __( 'Products by Supplier', 'sop' ),
                    ),
                ),
            ),
            'purchase_orders' => array(
                'title' => __( 'Purchase Orders', 'sop' ),
                'pages' => array(
                    array(
                        'slug' => 'sop-preorder-sheet',
                        'label' => __( 'Build PO', 'sop' ),
                    ),
                    array(
                        'slug' => 'sop-preorder-sheets',
                        'label' => __( 'Saved POs', 'sop' ),
                    ),
                    array(
                        'slug' => 'sop-carton-csv-import',
                        'label' => __( 'Carton Import', 'sop' ),
                    ),
                ),
            ),
            'forecasting'     => array(
                'title' => __( 'Forecasting', 'sop' ),
                'pages' => array(
                    array(
                        'slug' => 'sop-forecast-debug',
                        'label' => __( 'Forecast', 'sop' ),
                    ),
                    array(
                        'slug' => 'sop_stockout_log_debug',
                        'label' => __( 'Stock Log', 'sop' ),
                    ),
                ),
            ),
        );
    }
}

if ( ! function_exists( 'sop_admin_tabs_render_for_current_page' ) ) {
    /**
     * Render nav tabs for the current Stock Order admin page.
     *
     * @return void
     */
    function sop_admin_tabs_render_for_current_page() {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }

        if ( ! is_admin() ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( '' === $page ) {
            return;
        }

        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
        $groups      = sop_admin_tabs_get_groups();
        $active      = null;
        $active_page = null;

        foreach ( $groups as $group_key => $group ) {
            foreach ( $group['pages'] as $page_def ) {
                if ( $page_def['slug'] !== $page ) {
                    continue;
                }
                if ( empty( $page_def['args'] ) ) {
                    $active      = $group_key;
                    $active_page = $page_def;
                    break 2;
                }
                if ( isset( $page_def['args']['tab'] ) && $page_def['args']['tab'] === $current_tab ) {
                    $active      = $group_key;
                    $active_page = $page_def;
                    break 2;
                }
            }
        }

        if ( null === $active ) {
            foreach ( $groups as $group_key => $group ) {
                foreach ( $group['pages'] as $page_def ) {
                    if ( $page_def['slug'] === $page ) {
                        $active      = $group_key;
                        $active_page = $page_def;
                        break 2;
                    }
                }
            }
        }

        if ( null === $active ) {
            return;
        }

        $tabs = $groups[ $active ]['pages'];
        if ( empty( $tabs ) ) {
            return;
        }

        $rendered = true;

        $sheet_id_arg = '';
        if ( 'purchase_orders' === $active && ! empty( $_GET['sheet_id'] ) && is_numeric( $_GET['sheet_id'] ) ) {
            $sheet_id_arg = (string) (int) $_GET['sheet_id'];
        }

        echo '<div class="sop-admin-tabs">';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $tab ) {
            $url = admin_url( 'admin.php?page=' . $tab['slug'] );
            if ( ! empty( $tab['args'] ) ) {
                $url = add_query_arg( $tab['args'], $url );
            }
            if ( '' !== $sheet_id_arg && 'purchase_orders' === $active ) {
                $url = add_query_arg( array( 'sheet_id' => $sheet_id_arg ), $url );
            }

            $is_active = ( $tab['slug'] === $page );
            if ( $is_active && ! empty( $tab['args'] ) ) {
                $is_active = ( isset( $tab['args']['tab'] ) && $tab['args']['tab'] === $current_tab );
            }

            printf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                esc_url( $url ),
                $is_active ? 'nav-tab-active' : '',
                esc_html( $tab['label'] )
            );
        }
        echo '</h2>';
        echo '</div>';
    }
}

if ( ! function_exists( 'sop_admin_tabs_get_visible_parent_slug_for_page' ) ) {
    /**
     * Map a hidden page slug to its visible parent submenu slug.
     *
     * @param string $page_slug Current page slug.
     * @return string
     */
    function sop_admin_tabs_get_visible_parent_slug_for_page( $page_slug ) {
        $page_slug = sanitize_key( $page_slug );

        if ( 'sop_products_by_supplier' === $page_slug ) {
            return 'sop_stock_order_suppliers';
        }

        if ( in_array( $page_slug, array( 'sop-preorder-sheets', 'sop-carton-csv-import', 'sop-preorder-sheet' ), true ) ) {
            return 'sop-preorder-sheet';
        }

        if ( 'sop-forecast-debug' === $page_slug ) {
            return 'sop-forecast-debug';
        }

        if ( 'sop_stockout_log_debug' === $page_slug ) {
            return 'sop-forecast-debug';
        }

        if ( 'sop-goods-in' === $page_slug ) {
            return 'sop-goods-in';
        }

        if ( in_array( $page_slug, array( 'sop_stock_order', 'sop_stock_order_suppliers' ), true ) ) {
            return $page_slug;
        }

        return '';
    }
}

if ( ! function_exists( 'sop_admin_tabs_fix_submenu_highlight' ) ) {
    /**
     * Keep submenu highlight on the visible group parent.
     *
     * @param string $submenu_file Current submenu file.
     * @param string $parent_file Current parent file.
     * @return string
     */
    function sop_admin_tabs_fix_submenu_highlight( $submenu_file, $parent_file ) {
        if ( empty( $_GET['page'] ) ) {
            return $submenu_file;
        }

        $page_slug = sanitize_key( $_GET['page'] );

        if ( 'sop_stock_order' === $page_slug ) {
            $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
            if ( 'suppliers' === $tab ) {
                return 'sop_stock_order_suppliers';
            }
            return 'sop_stock_order';
        }

        $mapped = sop_admin_tabs_get_visible_parent_slug_for_page( $page_slug );
        if ( '' !== $mapped ) {
            return $mapped;
        }

        return $submenu_file;
    }
}

if ( ! function_exists( 'sop_admin_tabs_render_suppliers_redirect' ) ) {
    /**
     * Redirect the Suppliers submenu to the supplier settings tab.
     *
     * @return void
     */
    function sop_admin_tabs_render_suppliers_redirect() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sop_stock_order&tab=suppliers' ) );
        exit;
    }
}

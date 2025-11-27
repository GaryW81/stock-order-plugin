<?php
/**
 * Stock Order Plugin - Phase 4
 * Stockout tracking + maintenance hooks
 * File version: 1.0.0
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
}

add_action( 'woocommerce_product_set_stock', 'sop_handle_product_stock_change', 20 );
add_action( 'woocommerce_product_set_stock_status', 'sop_handle_product_stock_status_change', 20, 3 );
add_action( 'sop_daily_maintenance', 'sop_run_daily_maintenance_tasks' );

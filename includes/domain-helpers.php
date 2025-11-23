<?php
/**
 * Stock Order Plugin - Phase 1
 * Domain-level helpers on top of sop_DB
 * File version: 1.0.1
 *
 * Requires:
 * - The main sop_DB class + generic CRUD helpers snippet to be active.
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

// If core DB layer isn't loaded yet, bail early to avoid fatal errors.
if ( ! class_exists( 'sop_DB' ) ) {
    return;
}

/* -------------------------------------------------------------------------
 * Supplier helpers
 * ---------------------------------------------------------------------- */

/**
 * Create or update a supplier.
 *
 * Priority:
 * - If 'id' is provided and matches an existing row → update that row.
 * - Else if 'slug' is provided and exists → update that row.
 * - Else → insert new row.
 *
 * @param array $args
 * @return int|false Supplier ID on success, false on failure.
 */
function sop_supplier_upsert( array $args ) {
    $now = current_time( 'mysql' );

    $defaults = array(
        'id'               => 0,
        'name'             => '',
        'slug'             => '',
        'wc_term_id'       => null,
        'currency'         => 'GBP',
        'lead_time_weeks'  => 0,
        'holiday_rules'    => null,
        'container_config' => null,
        'settings_json'    => null,
        'is_active'        => 1,
    );

    $data = wp_parse_args( $args, $defaults );

    // Require a name at minimum.
    if ( '' === trim( $data['name'] ) ) {
        return false;
    }

    // Generate slug from name if missing.
    if ( '' === trim( $data['slug'] ) ) {
        $data['slug'] = sanitize_title( $data['name'] );
    }

    $data['slug'] = sanitize_title( $data['slug'] );

    // Normalise types.
    $data['lead_time_weeks'] = (int) $data['lead_time_weeks'];
    $data['is_active']       = (int) $data['is_active'];

    // Base row data (common to insert/update).
    $row = array(
        'name'             => $data['name'],
        'slug'             => $data['slug'],
        'wc_term_id'       => ( $data['wc_term_id'] !== null ) ? (int) $data['wc_term_id'] : null,
        'currency'         => $data['currency'],
        'lead_time_weeks'  => $data['lead_time_weeks'],
        'holiday_rules'    => $data['holiday_rules'],
        'container_config' => $data['container_config'],
        'settings_json'    => $data['settings_json'],
        'is_active'        => $data['is_active'],
        'updated_at'       => $now,
    );

    $table_key = 'suppliers';

    // 1. Update by ID if provided.
    $id = (int) $data['id'];
    if ( $id > 0 ) {
        $existing = sop_db_get_row( $table_key, array( 'id' => $id ) );
        if ( $existing ) {
            $result = sop_db_update( $table_key, $row, array( 'id' => $id ) );
            return ( false === $result ) ? false : $id;
        }
    }

    // 2. Update by slug if exists.
    $existing = sop_db_get_row( $table_key, array( 'slug' => $data['slug'] ) );
    if ( $existing && ! empty( $existing->id ) ) {
        $id     = (int) $existing->id;
        $result = sop_db_update( $table_key, $row, array( 'id' => $id ) );
        return ( false === $result ) ? false : $id;
    }

    // 3. Insert new supplier.
    $row['created_at'] = $now;

    $new_id = sop_db_insert( $table_key, $row );
    return $new_id ?: false;
}

/**
 * Get a supplier row by ID.
 *
 * @param int $id
 * @return object|array|null
 */
function sop_supplier_get_by_id( $id ) {
    $id = (int) $id;
    if ( $id <= 0 ) {
        return null;
    }

    return sop_db_get_row( 'suppliers', array( 'id' => $id ) );
}

/**
 * Get a supplier row by slug.
 *
 * @param string $slug
 * @return object|array|null
 */
function sop_supplier_get_by_slug( $slug ) {
    $slug = sanitize_title( $slug );
    if ( '' === $slug ) {
        return null;
    }

    return sop_db_get_row( 'suppliers', array( 'slug' => $slug ) );
}

/**
 * Get all suppliers, with optional filters.
 *
 * Supported $args:
 * - is_active (int|null)   → filter by active flag.
 *
 * @param array $args
 * @return array
 */
function sop_supplier_get_all( array $args = array() ) {
    $defaults = array(
        'is_active' => null,
    );

    $args = wp_parse_args( $args, $defaults );
    $where = array();

    if ( null !== $args['is_active'] ) {
        $where['is_active'] = (int) $args['is_active'];
    }

    return sop_db_get_results( 'suppliers', $where );
}

/* -------------------------------------------------------------------------
 * Stockout helpers
 * ---------------------------------------------------------------------- */

/**
 * Open a stockout record for a product/variation if one is not already open.
 *
 * "Open" = row exists with date_end IS NULL.
 *
 * @param int    $product_id
 * @param int    $variation_id
 * @param string $source
 * @param string $notes
 * @return int|false Stockout log ID if opened, false if already open or failed.
 */
function sop_stockout_open( $product_id, $variation_id = 0, $source = 'runtime', $notes = '' ) {
    global $wpdb;

    $product_id   = (int) $product_id;
    $variation_id = (int) $variation_id;

    if ( $product_id <= 0 ) {
        return false;
    }

    $table = sop_get_table_name( 'stockout_log' );
    if ( ! $table ) {
        return false;
    }

    // Check if there is already an open stockout (date_end IS NULL).
    $sql = $wpdb->prepare(
        "SELECT id FROM {$table} WHERE product_id = %d AND variation_id = %d AND date_end IS NULL LIMIT 1",
        $product_id,
        $variation_id
    );

    $existing_id = (int) $wpdb->get_var( $sql );
    if ( $existing_id > 0 ) {
        // Already open, no new record.
        return false;
    }

    $now = current_time( 'mysql' );

    $data = array(
        'product_id'   => $product_id,
        'variation_id' => $variation_id,
        'date_start'   => $now,
        'date_end'     => null,
        'source'       => $source,
        'notes'        => $notes,
        'created_at'   => $now,
        'updated_at'   => $now,
    );

    $insert_id = sop_db_insert( 'stockout_log', $data );
    return $insert_id ?: false;
}

/**
 * Close any open stockout record for a product/variation.
 *
 * @param int    $product_id
 * @param int    $variation_id
 * @param string $source         Optional source label (for appending).
 * @param string $notes_append   Optional extra notes to append to existing notes.
 * @return int|false Number of rows closed, or false on failure.
 */
function sop_stockout_close( $product_id, $variation_id = 0, $source = 'runtime', $notes_append = '' ) {
    global $wpdb;

    $product_id   = (int) $product_id;
    $variation_id = (int) $variation_id;

    if ( $product_id <= 0 ) {
        return false;
    }

    $table = sop_get_table_name( 'stockout_log' );
    if ( ! $table ) {
        return false;
    }

    // Get all open stockouts.
    $sql = $wpdb->prepare(
        "SELECT id, notes FROM {$table} WHERE product_id = %d AND variation_id = %d AND date_end IS NULL",
        $product_id,
        $variation_id
    );

    $rows = $wpdb->get_results( $sql );
    if ( empty( $rows ) ) {
        return 0;
    }

    $now = current_time( 'mysql' );
    $closed_count = 0;

    foreach ( $rows as $row ) {
        $id = (int) $row->id;
        if ( $id <= 0 ) {
            continue;
        }

        $combined_notes = $row->notes;

        if ( '' !== $notes_append ) {
            $append_str = trim( $notes_append );
            if ( '' !== $source ) {
                $append_str = '[' . $source . '] ' . $append_str;
            }

            $combined_notes = trim( $combined_notes . "\n" . $append_str );
        }

        $updated = sop_db_update(
            'stockout_log',
            array(
                'date_end'   => $now,
                'updated_at' => $now,
                'notes'      => $combined_notes,
            ),
            array( 'id' => $id )
        );

        if ( false !== $updated ) {
            $closed_count += (int) $updated;
        }
    }

    return $closed_count;
}

/* -------------------------------------------------------------------------
 * Goods-in helpers
 * ---------------------------------------------------------------------- */

/**
 * Start a goods-in session for a supplier.
 *
 * @param int   $supplier_id
 * @param array $args Optional args: reference, container_label, expected_arrival_date (Y-m-d), notes
 * @return int|false Session ID on success, false on failure.
 */
function sop_goods_in_start_session( $supplier_id, array $args = array() ) {
    $supplier_id = (int) $supplier_id;
    if ( $supplier_id <= 0 ) {
        return false;
    }

    $now = current_time( 'mysql' );

    $defaults = array(
        'reference'             => null,
        'container_label'       => null,
        'expected_arrival_date' => null, // 'Y-m-d' or null
        'notes'                 => null,
    );

    $args = wp_parse_args( $args, $defaults );

    // Use UUID as session_key for uniqueness.
    if ( function_exists( 'wp_generate_uuid4' ) ) {
        $session_key = wp_generate_uuid4();
    } else {
        $session_key = uniqid( 'sop_session_', true );
    }

    $data = array(
        'supplier_id'          => $supplier_id,
        'session_key'          => $session_key,
        'reference'            => $args['reference'],
        'container_label'      => $args['container_label'],
        'status'               => 'open',
        'expected_arrival_date'=> $args['expected_arrival_date'],
        'notes'                => $args['notes'],
        'created_at'           => $now,
        'updated_at'           => $now,
    );

    $id = sop_db_insert( 'goods_in_session', $data );
    return $id ?: false;
}

/**
 * Close a goods-in session (mark as completed).
 *
 * @param int $session_id
 * @return bool True on success, false on failure.
 */
function sop_goods_in_close_session( $session_id ) {
    $session_id = (int) $session_id;
    if ( $session_id <= 0 ) {
        return false;
    }

    $now = current_time( 'mysql' );

    $updated = sop_db_update(
        'goods_in_session',
        array(
            'status'     => 'completed',
            'updated_at' => $now,
        ),
        array( 'id' => $session_id )
    );

    return ( false !== $updated );
}

/**
 * Add an item to a goods-in session.
 *
 * @param int   $session_id
 * @param int   $product_id
 * @param int   $variation_id
 * @param int   $ordered_qty
 * @param int   $received_qty
 * @param array $extra_args Optional: damaged_qty, missing_qty, carton_number, line_notes
 * @return int|false Item ID on success, false on failure.
 */
function sop_goods_in_add_item( $session_id, $product_id, $variation_id, $ordered_qty, $received_qty = 0, array $extra_args = array() ) {
    $session_id   = (int) $session_id;
    $product_id   = (int) $product_id;
    $variation_id = (int) $variation_id;
    $ordered_qty  = (int) $ordered_qty;
    $received_qty = (int) $received_qty;

    if ( $session_id <= 0 || $product_id <= 0 ) {
        return false;
    }

    $defaults = array(
        'damaged_qty'   => 0,
        'missing_qty'   => 0,
        'carton_number' => null,
        'line_notes'    => null,
    );

    $extra = wp_parse_args( $extra_args, $defaults );

    $now = current_time( 'mysql' );

    $data = array(
        'session_id'   => $session_id,
        'product_id'   => $product_id,
        'variation_id' => $variation_id,
        'ordered_qty'  => $ordered_qty,
        'received_qty' => $received_qty,
        'damaged_qty'  => (int) $extra['damaged_qty'],
        'missing_qty'  => (int) $extra['missing_qty'],
        'carton_number'=> $extra['carton_number'],
        'line_notes'   => $extra['line_notes'],
        'created_at'   => $now,
        'updated_at'   => $now,
    );

    $id = sop_db_insert( 'goods_in_item', $data );
    return $id ?: false;
}

/* -------------------------------------------------------------------------
 * Forecast cache helpers
 * ---------------------------------------------------------------------- */

/**
 * Start a forecast run for a supplier.
 *
 * @param int   $supplier_id
 * @param array $params Arbitrary parameters (will be JSON-encoded).
 * @return int|false Cache ID on success, false on failure.
 */
function sop_forecast_start_run( $supplier_id, array $params = array() ) {
    $supplier_id = (int) $supplier_id;
    if ( $supplier_id <= 0 ) {
        return false;
    }

    $now = current_time( 'mysql' );

    // Use params hash to detect identical runs later if needed.
    $params_json = ! empty( $params ) ? wp_json_encode( $params ) : null;
    $params_hash = $params_json ? md5( $params_json ) : md5( 'empty' );

    // Simple derived values, safe if missing (fallbacks).
    $lookback_days   = isset( $params['lookback_days'] ) ? (int) $params['lookback_days'] : 365;
    $lead_time_days  = isset( $params['lead_time_days'] ) ? (int) $params['lead_time_days'] : 0;
    $buffer_days     = isset( $params['buffer_days'] ) ? (int) $params['buffer_days'] : 0;
    $analysis_start  = isset( $params['analysis_start_date'] ) ? $params['analysis_start_date'] : null;
    $analysis_end    = isset( $params['analysis_end_date'] ) ? $params['analysis_end_date'] : null;

    // Generate run_key.
    if ( function_exists( 'wp_generate_uuid4' ) ) {
        $run_key = wp_generate_uuid4();
    } else {
        $run_key = uniqid( 'sop_forecast_', true );
    }

    $data = array(
        'supplier_id'         => $supplier_id,
        'run_key'             => $run_key,
        'status'              => 'running',
        'params_hash'         => $params_hash,
        'params_json'         => $params_json,
        'lookback_days'       => $lookback_days,
        'lead_time_days'      => $lead_time_days,
        'buffer_days'         => $buffer_days,
        'analysis_start_date' => $analysis_start,
        'analysis_end_date'   => $analysis_end,
        'created_at'          => $now,
        'completed_at'        => null,
    );

    $id = sop_db_insert( 'forecast_cache', $data );
    return $id ?: false;
}

/**
 * Mark a forecast run as completed.
 *
 * @param int $cache_id
 * @return bool
 */
function sop_forecast_complete_run( $cache_id ) {
    $cache_id = (int) $cache_id;
    if ( $cache_id <= 0 ) {
        return false;
    }

    $now = current_time( 'mysql' );

    $updated = sop_db_update(
        'forecast_cache',
        array(
            'status'       => 'completed',
            'completed_at' => $now,
        ),
        array( 'id' => $cache_id )
    );

    return ( false !== $updated );
}

/**
 * Add a product-level forecast item to a forecast run.
 *
 * @param int   $cache_id
 * @param array $item_data
 * @return int|false Item ID on success, false on failure.
 */
function sop_forecast_add_item( $cache_id, array $item_data ) {
    $cache_id = (int) $cache_id;
    if ( $cache_id <= 0 ) {
        return false;
    }

    $now = current_time( 'mysql' );

    $defaults = array(
        'product_id'               => 0,
        'variation_id'             => 0,
        'current_stock'            => 0,
        'qty_sold'                 => 0,
        'days_on_sale'             => 0,
        'avg_per_day'              => 0,
        'avg_per_month'            => 0,
        'projected_stock_on_arrival'=> 0,
        'suggested_order_qty'      => 0,
        'max_order_qty_per_month'  => 0,
        'flags_json'               => null,
    );

    $data = wp_parse_args( $item_data, $defaults );

    $data['product_id']               = (int) $data['product_id'];
    $data['variation_id']             = (int) $data['variation_id'];
    $data['current_stock']            = (int) $data['current_stock'];
    $data['qty_sold']                 = (int) $data['qty_sold'];
    $data['days_on_sale']             = (int) $data['days_on_sale'];
    $data['projected_stock_on_arrival']= (int) $data['projected_stock_on_arrival'];
    $data['suggested_order_qty']      = (int) $data['suggested_order_qty'];
    $data['max_order_qty_per_month']  = (int) $data['max_order_qty_per_month'];

    // Ensure decimals are float-ish; dbDelta schema is DECIMAL(12,4).
    $data['avg_per_day']   = (float) $data['avg_per_day'];
    $data['avg_per_month'] = (float) $data['avg_per_month'];

    if ( $data['product_id'] <= 0 ) {
        return false;
    }

    $row = array(
        'cache_id'                 => $cache_id,
        'product_id'               => $data['product_id'],
        'variation_id'             => $data['variation_id'],
        'current_stock'            => $data['current_stock'],
        'qty_sold'                 => $data['qty_sold'],
        'days_on_sale'             => $data['days_on_sale'],
        'avg_per_day'              => $data['avg_per_day'],
        'avg_per_month'            => $data['avg_per_month'],
        'projected_stock_on_arrival'=> $data['projected_stock_on_arrival'],
        'suggested_order_qty'      => $data['suggested_order_qty'],
        'max_order_qty_per_month'  => $data['max_order_qty_per_month'],
        'flags_json'               => $data['flags_json'],
        'created_at'               => $now,
    );

    $id = sop_db_insert( 'forecast_cache_item', $row );
    return $id ?: false;
}

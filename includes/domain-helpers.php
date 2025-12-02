<?php
/**
 * Stock Order Plugin - Phase 1
 * Domain-level helpers on top of sop_DB
 * File version: 1.0.16
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
 * Get the preorder sheet header table name.
 *
 * @return string
 */
if ( ! function_exists( 'sop_get_preorder_sheet_table_name' ) ) {
    function sop_get_preorder_sheet_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sop_preorder_sheet';
    }
}

/**
 * Get the preorder sheet lines table name.
 *
 * @return string
 */
if ( ! function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ) {
    function sop_get_preorder_sheet_lines_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sop_preorder_sheet_lines';
    }
}

/**
 * Create a new preorder sheet header row.
 *
 * @param array $data Associative array of fields to insert.
 * @return int|WP_Error Insert ID on success, WP_Error on failure.
 */
function sop_insert_preorder_sheet( $data ) {
    global $wpdb;

    $table = sop_get_preorder_sheet_table_name();
    if ( ! $table ) {
        return new WP_Error( 'sop_insert_preorder_sheet_failed', __( 'Failed to insert preorder sheet.', 'sop' ) );
    }

    $now_utc = current_time( 'mysql', true );

    $defaults = array(
        'supplier_id'               => 0,
        'status'                    => 'draft',
        'title'                     => '',
        'order_number'              => '',
        'order_number_label'        => '',
        'edit_version'              => 1,
        'order_date_owner'          => null,
        'container_load_date_owner' => null,
        'arrival_date_owner'        => null,
        'deposit_fx_owner'          => null,
        'balance_fx_owner'          => null,
        'header_notes_owner'        => null,
        'currency_code'             => '',
        'container_type'            => '',
        'public_token'              => null,
        'portal_enabled'            => 0,
        'created_at'                => $now_utc,
        'updated_at'                => $now_utc,
    );

    $data = wp_parse_args( $data, $defaults );

    $insert = array(
        'supplier_id'               => (int) $data['supplier_id'],
        'status'                    => ( $data['status'] !== '' ) ? $data['status'] : 'draft',
        'title'                     => (string) $data['title'],
        'order_number'              => (string) $data['order_number'],
        'order_number_label'        => (string) $data['order_number_label'],
        'edit_version'              => (int) $data['edit_version'],
        'order_date_owner'          => $data['order_date_owner'],
        'container_load_date_owner' => $data['container_load_date_owner'],
        'arrival_date_owner'        => $data['arrival_date_owner'],
        'deposit_fx_owner'          => $data['deposit_fx_owner'],
        'balance_fx_owner'          => $data['balance_fx_owner'],
        'header_notes_owner'        => $data['header_notes_owner'],
        'currency_code'             => (string) $data['currency_code'],
        'container_type'            => (string) $data['container_type'],
        'public_token'              => $data['public_token'],
        'portal_enabled'            => (int) $data['portal_enabled'],
        'created_at'                => ( $data['created_at'] ) ? $data['created_at'] : $now_utc,
        'updated_at'                => ( $data['updated_at'] ) ? $data['updated_at'] : $now_utc,
    );

    $format = array(
        '%d', // supplier_id.
        '%s', // status.
        '%s', // title.
        '%s', // order_number.
        '%s', // order_number_label.
        '%d', // edit_version.
        '%s', // order_date_owner.
        '%s', // container_load_date_owner.
        '%s', // arrival_date_owner.
        '%f', // deposit_fx_owner.
        '%f', // balance_fx_owner.
        '%s', // header_notes_owner.
        '%s', // currency_code.
        '%s', // container_type.
        '%s', // public_token.
        '%d', // portal_enabled.
        '%s', // created_at.
        '%s', // updated_at.
    );

    $result = $wpdb->insert( $table, $insert, $format );
    if ( false === $result ) {
        return new WP_Error( 'sop_insert_preorder_sheet_failed', __( 'Failed to insert preorder sheet.', 'sop' ) );
    }

    return (int) $wpdb->insert_id;
}

/**
 * Fetch a preorder sheet header by ID.
 *
 * @param int $sheet_id Sheet ID.
 * @return array|null
 */
function sop_get_preorder_sheet( $sheet_id ) {
    global $wpdb;

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return null;
    }

    $table = sop_get_preorder_sheet_table_name();
    if ( ! $table ) {
        return null;
    }

    $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $sheet_id );
    $row = $wpdb->get_row( $sql, ARRAY_A );

    return $row ? $row : null;
}

/**
 * Get preorder sheets for a supplier.
 *
 * @param int   $supplier_id Supplier ID.
 * @param array $args {
 *     @type int   $limit  Max rows to return.
 *     @type array $status Statuses to include (defaults to array('draft')).
 * }
 * @return array[]
 */
function sop_get_preorder_sheets_for_supplier( $supplier_id, $args = array() ) {
    global $wpdb;

    $supplier_id = (int) $supplier_id;
    if ( $supplier_id <= 0 ) {
        return array();
    }

    $args = wp_parse_args(
        $args,
        array(
            'limit'  => 20,
            'status' => array( 'draft' ),
        )
    );

    $table = sop_get_preorder_sheet_table_name();
    if ( ! $table ) {
        return array();
    }

    $where   = array( $wpdb->prepare( 'supplier_id = %d', $supplier_id ) );
    $values  = array();
    $limit   = (int) $args['limit'];
    $limit   = ( $limit > 0 ) ? $limit : 20;
    $status  = $args['status'];
    $status_sql = '';

    if ( is_array( $status ) && ! empty( $status ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $status ), '%s' ) );
        $where[]      = "status IN ( {$placeholders} )";
        $values       = array_merge( $values, $status );
    }

    $where_sql = implode( ' AND ', $where );

    $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC, created_at DESC LIMIT %d";
    $values[] = $limit;

    $prepared = $wpdb->prepare( $sql, $values );

    return $wpdb->get_results( $prepared, ARRAY_A );
}

/**
 * Update an existing preorder sheet header row.
 *
 * @param int   $sheet_id Sheet ID.
 * @param array $data     Data to update.
 * @return true|WP_Error
 */
function sop_update_preorder_sheet( $sheet_id, $data ) {
    global $wpdb;

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return new WP_Error( 'sop_update_preorder_sheet_failed', __( 'Invalid preorder sheet ID.', 'sop' ) );
    }

    $table = sop_get_preorder_sheet_table_name();
    if ( ! $table ) {
        return new WP_Error( 'sop_update_preorder_sheet_failed', __( 'Failed to update preorder sheet.', 'sop' ) );
    }

    $now_utc = current_time( 'mysql', true );

    $allowed = array(
        'status',
        'title',
        'order_number',
        'order_number_label',
        'edit_version',
        'order_date_owner',
        'container_load_date_owner',
        'arrival_date_owner',
        'deposit_fx_owner',
        'balance_fx_owner',
        'header_notes_owner',
        'currency_code',
        'container_type',
        'portal_enabled',
    );

    $update = array();

    foreach ( $allowed as $key ) {
        if ( array_key_exists( $key, $data ) ) {
            $update[ $key ] = $data[ $key ];
        }
    }

    $update['updated_at'] = $now_utc;

    if ( empty( $update ) ) {
        return true;
    }

    $formats = array();
    foreach ( $update as $key => $value ) {
        switch ( $key ) {
            case 'portal_enabled':
                $formats[] = '%d';
                break;
            case 'deposit_fx_owner':
            case 'balance_fx_owner':
                $formats[] = '%f';
                break;
            case 'edit_version':
                $formats[] = '%d';
                break;
            default:
                $formats[] = '%s';
        }
    }

    $result = $wpdb->update(
        $table,
        $update,
        array( 'id' => $sheet_id ),
        $formats,
        array( '%d' )
    );

    if ( false === $result ) {
        return new WP_Error( 'sop_update_preorder_sheet_failed', __( 'Failed to update preorder sheet.', 'sop' ) );
    }

    return true;
}

/**
 * Insert preorder sheet lines (replace existing).
 *
 * @param int   $sheet_id Sheet ID.
 * @param array $lines    List of associative arrays per line.
 * @return true|WP_Error
 */
function sop_insert_preorder_sheet_lines( $sheet_id, array $lines ) {
    global $wpdb;

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return new WP_Error( 'sop_insert_preorder_lines_failed', __( 'Invalid preorder sheet ID.', 'sop' ) );
    }

    $table = sop_get_preorder_sheet_lines_table_name();
    if ( ! $table ) {
        return new WP_Error( 'sop_insert_preorder_lines_failed', __( 'Failed to insert preorder lines.', 'sop' ) );
    }

    $wpdb->delete( $table, array( 'sheet_id' => $sheet_id ), array( '%d' ) );

    foreach ( $lines as $line ) {
        $insert = array(
            'sheet_id'              => $sheet_id,
            'product_id'            => isset( $line['product_id'] ) ? (int) $line['product_id'] : 0,
            'sku_owner'             => isset( $line['sku_owner'] ) ? (string) $line['sku_owner'] : '',
            'qty_owner'             => isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : 0.0,
            'cost_rmb_owner'        => isset( $line['cost_rmb_owner'] ) ? (float) $line['cost_rmb_owner'] : 0.0,
            'moq_owner'             => isset( $line['moq_owner'] ) ? (float) $line['moq_owner'] : 0.0,
            'product_notes_owner'   => isset( $line['product_notes_owner'] ) ? $line['product_notes_owner'] : null,
            'order_notes_owner'     => isset( $line['order_notes_owner'] ) ? $line['order_notes_owner'] : null,
            'sku_supplier'          => isset( $line['sku_supplier'] ) ? (string) $line['sku_supplier'] : '',
            'qty_supplier'          => isset( $line['qty_supplier'] ) ? (float) $line['qty_supplier'] : 0.0,
            'cost_rmb_supplier'     => isset( $line['cost_rmb_supplier'] ) ? (float) $line['cost_rmb_supplier'] : 0.0,
            'moq_supplier'          => isset( $line['moq_supplier'] ) ? (float) $line['moq_supplier'] : 0.0,
            'product_notes_supplier'=> isset( $line['product_notes_supplier'] ) ? $line['product_notes_supplier'] : null,
            'order_notes_supplier'  => isset( $line['order_notes_supplier'] ) ? $line['order_notes_supplier'] : null,
            'image_id'              => isset( $line['image_id'] ) ? (int) $line['image_id'] : null,
            'location'              => isset( $line['location'] ) ? (string) $line['location'] : '',
            'cbm_per_unit'          => isset( $line['cbm_per_unit'] ) ? (float) $line['cbm_per_unit'] : 0.0,
            'cbm_total_owner'       => isset( $line['cbm_total_owner'] ) ? (float) $line['cbm_total_owner'] : 0.0,
            'sort_index'            => isset( $line['sort_index'] ) ? (int) $line['sort_index'] : 0,
        );

        $format = array(
            '%d', // sheet_id.
            '%d', // product_id.
            '%s', // sku_owner.
            '%f', // qty_owner.
            '%f', // cost_rmb_owner.
            '%f', // moq_owner.
            '%s', // product_notes_owner.
            '%s', // order_notes_owner.
            '%s', // sku_supplier.
            '%f', // qty_supplier.
            '%f', // cost_rmb_supplier.
            '%f', // moq_supplier.
            '%s', // product_notes_supplier.
            '%s', // order_notes_supplier.
            '%d', // image_id.
            '%s', // location.
            '%f', // cbm_per_unit.
            '%f', // cbm_total_owner.
            '%d', // sort_index.
        );

        $result = $wpdb->insert( $table, $insert, $format );
        if ( false === $result ) {
            return new WP_Error( 'sop_insert_preorder_lines_failed', __( 'Failed to insert preorder lines.', 'sop' ) );
        }
    }

    return true;
}

/**
 * Get all lines for a preorder sheet.
 *
 * @param int $sheet_id Sheet ID.
 * @return array[] List of associative rows.
 */
function sop_get_preorder_sheet_lines( $sheet_id ) {
    global $wpdb;

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return array();
    }

    $table = sop_get_preorder_sheet_lines_table_name();
    if ( ! $table ) {
        return array();
    }

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE sheet_id = %d ORDER BY sort_index ASC, id ASC",
        $sheet_id
    );

    return $wpdb->get_results( $sql, ARRAY_A );
}

/**
 * Delete a preorder sheet and its lines.
 *
 * @param int $sheet_id Sheet ID.
 * @return bool|WP_Error
 */
function sop_delete_preorder_sheet( $sheet_id ) {
    global $wpdb;

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return false;
    }

    $header_table = sop_get_preorder_sheet_table_name();
    $lines_table  = sop_get_preorder_sheet_lines_table_name();

    $wpdb->delete(
        $lines_table,
        array( 'sheet_id' => $sheet_id ),
        array( '%d' )
    );

    $deleted_header = $wpdb->delete(
        $header_table,
        array( 'id' => $sheet_id ),
        array( '%d' )
    );

    if ( false === $deleted_header ) {
        return new WP_Error( 'sop_delete_preorder_sheet_failed', __( 'Failed to delete pre-order sheet.', 'sop' ) );
    }

    return true;
}

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

/**
 * Convert a unit cost in RMB to USD using manual FX rates from SOP settings.
 *
 * @param float $rmb_cost Unit cost in RMB.
 * @return float Converted USD value or 0.0 on failure.
 */
function sop_convert_rmb_unit_cost_to_usd( $rmb_cost ) {
    $rmb_cost = (float) $rmb_cost;

    $settings   = get_option( 'sop_settings', array() );
    $rmb_to_gbp = isset( $settings['rmb_to_gbp_rate'] ) ? (float) $settings['rmb_to_gbp_rate'] : 0.0;
    $usd_to_gbp = isset( $settings['usd_to_gbp_rate'] ) ? (float) $settings['usd_to_gbp_rate'] : 0.0;

    if ( $rmb_cost <= 0 || $rmb_to_gbp <= 0 || $usd_to_gbp <= 0 ) {
        return 0.0;
    }

    $gbp_value = $rmb_cost * $rmb_to_gbp;
    if ( $gbp_value <= 0 ) {
        return 0.0;
    }

    $usd_value = $gbp_value / $usd_to_gbp;

    return $usd_value;
}

if ( ! function_exists( 'sop_get_product_primary_category_name' ) ) {
    /**
     * Get the primary WooCommerce product category name for a product.
     *
     * For display/export only; does not modify taxonomy assignments.
     *
     * @param int $product_id Product or variation ID.
     * @return string Category name or empty string.
     */
    function sop_get_product_primary_category_name( $product_id ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            return '';
        }

        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product && $product->is_type( 'variation' ) ) {
                $parent_id = $product->get_parent_id();
                if ( $parent_id ) {
                    $product_id = (int) $parent_id;
                }
            }
        }

        $terms = wp_get_post_terms( $product_id, 'product_cat' );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '';
        }

        usort(
            $terms,
            static function ( $a, $b ) {
                return strcasecmp( $a->name, $b->name );
            }
        );

        $term = reset( $terms );
        return ( $term && isset( $term->name ) ) ? (string) $term->name : '';
    }
}

/**
 * Get the configured max order quantity per month for a product, if any.
 *
 * Priority:
 * 1. Product meta 'max_order_qty_per_month'.
 * 2. Product meta 'max_qty_per_month' (legacy alias).
 * 3. Parent product meta (same keys) if this is a variation.
 *
 * Returns a non-negative float; 0.0 means "no cap".
 *
 * @param int|\WC_Product $product Product ID or product object.
 * @return float
 */
function sop_get_product_max_order_qty_per_month( $product ) {
    if ( $product instanceof \WC_Product ) {
        $wc_product = $product;
    } else {
        $product_id = (int) $product;
        if ( $product_id <= 0 ) {
            return 0.0;
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return 0.0;
        }

        $wc_product = wc_get_product( $product_id );
        if ( ! $wc_product ) {
            return 0.0;
        }
    }

    $product_id = $wc_product->get_id();
    $parent_id  = $wc_product->get_parent_id();

    $meta_keys = array(
        'max_order_qty_per_month',   // canonical
        'max_qty_per_month',         // legacy without "order"
        'max_order_qty_per month',   // legacy with space before "month"
    );

    foreach ( $meta_keys as $meta_key ) {
        $raw = get_post_meta( $product_id, $meta_key, true );
        if ( '' !== $raw ) {
            $val = (float) str_replace( ',', '.', (string) $raw );
            if ( $val > 0 ) {
                return $val;
            }
        }
    }

    if ( $parent_id ) {
        foreach ( $meta_keys as $meta_key ) {
            $raw = get_post_meta( $parent_id, $meta_key, true );
            if ( '' !== $raw ) {
                $val = (float) str_replace( ',', '.', (string) $raw );
                if ( $val > 0 ) {
                    return $val;
                }
            }
        }
    }

    return 0.0;
}

/**
 * Get an overstock report across products.
 *
 * For each product with a supplier and positive demand + stock,
 * we estimate a recommended stock "target" using the same demand-per-day
 * and buffer-days structure as the forecast engine. Any stock above that
 * target is treated as overstock.
 *
 * Returns rows sorted by overstock percentage (descending).
 *
 * @param array $args {
 *     @type int $limit Max number of rows to return (for UI).
 * }
 * @return array[] List of associative arrays with product_id, sku, name, location, stock, over_units, over_pct, image_html.
 */
function sop_get_overstock_report( $args = array() ) {
    $args = wp_parse_args(
        $args,
        array(
            'limit' => 50,
        )
    );

    $rows = array();

    if ( ! function_exists( 'sop_core_engine' ) || ! function_exists( 'sop_supplier_get_all' ) ) {
        return $rows;
    }

    $engine = sop_core_engine();
    if ( ! $engine || ! method_exists( $engine, 'get_supplier_forecast' ) ) {
        return $rows;
    }

    $suppliers = sop_supplier_get_all( array( 'is_active' => 1 ) );
    if ( empty( $suppliers ) ) {
        return $rows;
    }

    foreach ( $suppliers as $supplier ) {
        $supplier_id = 0;
        if ( is_object( $supplier ) && isset( $supplier->id ) ) {
            $supplier_id = (int) $supplier->id;
        } elseif ( is_array( $supplier ) && isset( $supplier['id'] ) ) {
            $supplier_id = (int) $supplier['id'];
        }

        if ( $supplier_id <= 0 ) {
            continue;
        }

        $forecast_rows = $engine->get_supplier_forecast( $supplier_id );
        if ( empty( $forecast_rows ) || ! is_array( $forecast_rows ) ) {
            continue;
        }

        foreach ( $forecast_rows as $row ) {
            $product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
            if ( $product_id <= 0 ) {
                continue;
            }

            $current_stock = isset( $row['current_stock'] ) ? (float) $row['current_stock'] : 0.0;
            if ( $current_stock <= 0 ) {
                continue;
            }

            $target_stock = 0.0;
            if ( isset( $row['buffer_target_units'] ) ) {
                $target_stock = (float) $row['buffer_target_units'];
            } elseif ( isset( $row['buffer_days'] ) && isset( $row['demand_per_day'] ) ) {
                $target_stock = (float) $row['buffer_days'] * (float) $row['demand_per_day'];
            }

            $target_stock = max( 0.0, $target_stock );

            if ( $target_stock <= 0.0 ) {
                continue;
            }

            if ( $current_stock <= $target_stock ) {
                continue;
            }

            $over_units = $current_stock - $target_stock;
            $over_pct   = ( $target_stock > 0.0 ) ? ( $over_units / $target_stock * 100.0 ) : 0.0;

            $product_obj = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
            $sku         = isset( $row['sku'] ) ? (string) $row['sku'] : '';
            $name        = isset( $row['name'] ) ? (string) $row['name'] : '';

            if ( $product_obj ) {
                if ( '' === $sku ) {
                    $sku = $product_obj->get_sku();
                }

                $name = $product_obj->get_name();
            }

            $location = get_post_meta( $product_id, '_product_location', true );

            $image_html = '';
            if ( $product_obj ) {
                $image_id = $product_obj->get_image_id();
                if ( $image_id ) {
                    $image_html = wp_get_attachment_image( $image_id, array( 60, 60 ) );
                }
            }

            if ( '' === $image_html && function_exists( 'wc_placeholder_img' ) ) {
                $image_html = wc_placeholder_img( array( 60, 60 ) );
            }

            $rows[] = array(
                'product_id' => $product_id,
                'sku'        => $sku,
                'name'       => $name,
                'location'   => $location,
                'stock'      => (int) round( $current_stock ),
                'over_units' => (float) $over_units,
                'over_pct'   => (float) $over_pct,
                'image_html' => $image_html,
            );
        }
    }

    if ( ! empty( $rows ) ) {
        usort(
            $rows,
            function ( $a, $b ) {
                if ( $a['over_pct'] === $b['over_pct'] ) {
                    return 0;
                }
                return ( $a['over_pct'] < $b['over_pct'] ) ? 1 : -1;
            }
        );
    }

    $limit = isset( $args['limit'] ) ? (int) $args['limit'] : 0;
    if ( $limit > 0 && count( $rows ) > $limit ) {
        $rows = array_slice( $rows, 0, $limit );
    }

    return $rows;
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

/**
 * Calculate total stockout days for a product/variation within a window.
 *
 * @param int $product_id
 * @param int $variation_id
 * @param int $from_ts      Start timestamp (inclusive).
 * @param int $to_ts        End timestamp (exclusive).
 * @return float Stockout days within the window.
 */
function sop_stockout_get_days_in_window( $product_id, $variation_id = 0, $from_ts = 0, $to_ts = 0 ) {
    global $wpdb;

    $product_id   = (int) $product_id;
    $variation_id = (int) $variation_id;
    $from_ts      = (int) $from_ts;
    $to_ts        = (int) $to_ts;

    if ( $product_id <= 0 ) {
        return 0.0;
    }

    if ( $from_ts <= 0 || $to_ts <= 0 || $from_ts >= $to_ts ) {
        return 0.0;
    }

    $table = sop_get_table_name( 'stockout_log' );
    if ( ! $table ) {
        return 0.0;
    }

    $window_start = date( 'Y-m-d H:i:s', $from_ts );
    $window_end   = date( 'Y-m-d H:i:s', $to_ts );

    $sql = $wpdb->prepare(
        "SELECT date_start, date_end FROM {$table}
        WHERE product_id = %d
          AND variation_id = %d
          AND date_start <= %s
          AND ( date_end IS NULL OR date_end >= %s )
        ORDER BY date_start ASC",
        $product_id,
        $variation_id,
        $window_end,
        $window_start
    );

    $rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    if ( empty( $rows ) ) {
        return 0.0;
    }

    $total_seconds = 0.0;

    foreach ( $rows as $row ) {
        $start_ts = strtotime( $row->date_start );
        $end_ts   = $row->date_end ? strtotime( $row->date_end ) : current_time( 'timestamp' );

        if ( false === $start_ts ) {
            continue;
        }

        if ( false === $end_ts ) {
            continue;
        }

        if ( $end_ts <= $from_ts || $start_ts >= $to_ts ) {
            continue;
        }

        $segment_start = max( $from_ts, $start_ts );
        $segment_end   = min( $to_ts, $end_ts );

        if ( $segment_end > $segment_start ) {
            $total_seconds += ( $segment_end - $segment_start );
        }
    }

    $days        = $total_seconds / DAY_IN_SECONDS;
    $window_days = max( 0.0, ( $to_ts - $from_ts ) / DAY_IN_SECONDS );

    if ( $days < 0 ) {
        $days = 0.0;
    }

    if ( $days > $window_days ) {
        $days = $window_days;
    }

    return (float) $days;
}

/**
 * Get effective legacy stockout/in-stock days for a product within an analysis window.
 *
 * Legacy impact fades linearly over the lookback window based on the import timestamp,
 * preserving the original stockout ratio for the portion still covered by legacy data.
 *
 * @param int $product_id Product ID.
 * @param int $from_ts    Analysis window start (Unix timestamp).
 * @param int $to_ts      Analysis window end (Unix timestamp).
 * @return array {
 *     @type float $stockout_days Effective legacy stockout days inside the window.
 *     @type float $in_stock_days Effective legacy in-stock days inside the window.
 *     @type float $total_days    Total legacy-covered days inside the window.
 * }
 */
function sop_legacy_get_scaled_days_for_window( $product_id, $from_ts, $to_ts ) {
    global $wpdb;

    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return array(
            'stockout_days' => 0.0,
            'in_stock_days' => 0.0,
            'total_days'    => 0.0,
        );
    }

    $from_ts = (int) $from_ts;
    $to_ts   = (int) $to_ts;

    if ( $to_ts <= $from_ts ) {
        return array(
            'stockout_days' => 0.0,
            'in_stock_days' => 0.0,
            'total_days'    => 0.0,
        );
    }

    $lookback_days = max( 1, (int) floor( ( $to_ts - $from_ts ) / DAY_IN_SECONDS ) );

    $table = $wpdb->prefix . 'sop_legacy_product_history';

    $sql = $wpdb->prepare(
        "SELECT stockout_days_12m_legacy, days_on_sale_12m_legacy, imported_at
         FROM {$table}
         WHERE product_id = %d
         LIMIT 1",
        $product_id
    );

    $row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    if ( ! $row ) {
        return array(
            'stockout_days' => 0.0,
            'in_stock_days' => 0.0,
            'total_days'    => 0.0,
        );
    }

    $legacy_stockout = isset( $row->stockout_days_12m_legacy ) ? (float) $row->stockout_days_12m_legacy : 0.0;
    $legacy_on_sale  = isset( $row->days_on_sale_12m_legacy ) ? (float) $row->days_on_sale_12m_legacy : 0.0;

    $legacy_total = $legacy_stockout + $legacy_on_sale;
    if ( $legacy_total <= 0 ) {
        return array(
            'stockout_days' => 0.0,
            'in_stock_days' => 0.0,
            'total_days'    => 0.0,
        );
    }

    $imported_at_raw = isset( $row->imported_at ) ? (string) $row->imported_at : '';
    $import_ts       = $imported_at_raw ? strtotime( $imported_at_raw ) : 0;

    $stockout_ratio = max( 0.0, min( 1.0, $legacy_stockout / $legacy_total ) );

    if ( $import_ts <= 0 ) {
        $stockout_days = $lookback_days * $stockout_ratio;
        $in_stock_days = $lookback_days - $stockout_days;

        return array(
            'stockout_days' => (float) $stockout_days,
            'in_stock_days' => (float) $in_stock_days,
            'total_days'    => (float) $lookback_days,
        );
    }

    $days_since_import = (int) floor( max( 0, ( $to_ts - $import_ts ) / DAY_IN_SECONDS ) );

    if ( $days_since_import >= $lookback_days ) {
        return array(
            'stockout_days' => 0.0,
            'in_stock_days' => 0.0,
            'total_days'    => 0.0,
        );
    }

    $pre_import_days = (float) ( $lookback_days - $days_since_import );
    if ( $pre_import_days <= 0 ) {
        return array(
            'stockout_days' => 0.0,
            'in_stock_days' => 0.0,
            'total_days'    => 0.0,
        );
    }

    $stockout_days = $pre_import_days * $stockout_ratio;
    $in_stock_days = $pre_import_days - $stockout_days;

    return array(
        'stockout_days' => (float) $stockout_days,
        'in_stock_days' => (float) $in_stock_days,
        'total_days'    => (float) $pre_import_days,
    );
}

/**
 * Prune stockout log rows older than the specified number of years.
 *
 * Intended to enforce a rolling retention policy (default 5 years).
 *
 * @param int $max_age_years Number of years to keep (minimum 1).
 * @return int Rows affected.
 */
function sop_prune_old_stockout_logs( $max_age_years = 5 ) {
    global $wpdb;

    $max_age_years = (int) $max_age_years;
    if ( $max_age_years < 1 ) {
        $max_age_years = 1;
    }

    $table = sop_get_table_name( 'stockout_log' );
    if ( ! $table ) {
        return 0;
    }

    $cutoff_ts = time() - ( $max_age_years * YEAR_IN_SECONDS );
    $cutoff    = gmdate( 'Y-m-d H:i:s', $cutoff_ts );

    $sql = $wpdb->prepare( "DELETE FROM {$table} WHERE date_start < %s", $cutoff );
    $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    return (int) $wpdb->rows_affected;
}

/**
 * Backfill open stockout intervals for any products currently at zero stock.
 *
 * This is intended as a safety net for products that were already out of stock
 * before live stockout logging was enabled. It will:
 * - Find all WooCommerce products/variations with manage_stock = yes and _stock <= 0.
 * - Skip any product that already has an open stockout row (date_end IS NULL).
 * - Call sop_stockout_open() to create an open interval for the remaining IDs.
 *
 * @return void
 */
function sop_backfill_open_stockouts_for_zero_stock_products() {
    global $wpdb;

    // Ensure core helpers exist.
    if ( ! function_exists( 'sop_stockout_open' ) ) {
        return;
    }

    $posts_table    = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;

    // Find managed products/variations with stock <= 0.
    $sql_zero_stock = "
        SELECT p.ID
        FROM {$posts_table} p
        INNER JOIN {$postmeta_table} ms
            ON ms.post_id = p.ID
           AND ms.meta_key = '_manage_stock'
           AND ms.meta_value = 'yes'
        INNER JOIN {$postmeta_table} st
            ON st.post_id = p.ID
           AND st.meta_key = '_stock'
        WHERE p.post_type IN ('product','product_variation')
          AND p.post_status NOT IN ('trash','auto-draft')
          AND CAST(st.meta_value AS SIGNED) <= 0
    ";

    $zero_stock_ids = $wpdb->get_col( $sql_zero_stock ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    if ( empty( $zero_stock_ids ) ) {
        return;
    }

    // Table name for stockout logs.
    $table = sop_get_table_name( 'stockout_log' );
    if ( ! $table ) {
        return;
    }

    // Get product IDs that already have an open stockout row.
    $sql_open = "
        SELECT DISTINCT product_id
        FROM {$table}
        WHERE date_end IS NULL
    ";
    $open_ids = $wpdb->get_col( $sql_open ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    if ( ! is_array( $open_ids ) ) {
        $open_ids = array();
    }

    $open_map = array();
    foreach ( $open_ids as $open_id ) {
        $open_map[ (int) $open_id ] = true;
    }

    foreach ( $zero_stock_ids as $product_id ) {
        $product_id = (int) $product_id;

        if ( $product_id <= 0 ) {
            continue;
        }

        // Skip if we already have an open interval for this product.
        if ( isset( $open_map[ $product_id ] ) ) {
            continue;
        }

        // Open a backfilled stockout interval at "now".
        sop_stockout_open(
            $product_id,
            0,
            'backfill',
            'Opened by maintenance for existing zero stock.'
        );
    }
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

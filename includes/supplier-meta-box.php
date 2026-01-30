<?php
/**
 * Stock Order Plugin - Phase 2
 * Product Stock Order meta box (supplier + SOP fields).
 * File version: 1.0.24
 * - UI: add rich notes editor for product/internal notes (bold/strike/red).
 * - UI: add internal product notes field on product edit screen.
 * - Allow max_order_qty_per_month to save decimals (2dp), accept comma, and never block product save.
 * - Add Supplier SKUs meta (multi-line) for optional Supplier SKUs column.
 * - Canonicalise max_order_qty_per_month meta key.
 *
 * - Adds a "Stock Order" meta box to WooCommerce products.
 * - Uses sop_suppliers table via sop_supplier_get_all().
 * - Stores selected supplier ID in product meta: _sop_supplier_id.
 * - Manages SOP product meta:
 *     - Location: _sop_bin_location (primary), mirrored to _product_location.
 *     - Supplier costs (per unit): _sop_cost_rmb, _sop_cost_usd, _sop_cost_eur.
 *     - Minimum order quantity: _sop_min_order_qty.
 *     - Product notes: _sop_product_notes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_parse_decimal_2dp' ) ) {
    /**
     * Parse a raw decimal string to a normalised 2dp string.
     *
     * @param string $raw Raw input.
     * @return string|null 2dp string or null if empty/invalid.
     */
    function sop_parse_decimal_2dp( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return null;
        }

        $raw = str_replace( ',', '.', $raw );

        if ( ! is_numeric( $raw ) ) {
            return null;
        }

        if ( function_exists( 'wc_format_decimal' ) ) {
            return wc_format_decimal( $raw, 2 );
        }

        return number_format( (float) $raw, 2, '.', '' );
    }
}

if ( ! function_exists( 'sop_notes_should_enable_editor' ) ) {
    /**
     * Check if SOP notes editor enhancements should load.
     *
     * @return bool
     */
    function sop_notes_should_enable_editor() {
        if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();
        if ( ! $screen || empty( $screen->post_type ) ) {
            return false;
        }

        return ( 'product' === $screen->post_type );
    }
}

if ( ! function_exists( 'sop_notes_register_tinymce_plugin' ) ) {
    /**
     * Register TinyMCE plugin for SOP red notes button.
     *
     * @param array $plugins Plugins map.
     * @return array
     */
    function sop_notes_register_tinymce_plugin( $plugins ) {
        if ( ! sop_notes_should_enable_editor() ) {
            return $plugins;
        }

        if ( defined( 'SOP_PLUGIN_URL' ) ) {
            $plugins['sopred'] = SOP_PLUGIN_URL . 'admin/js/sop-notes-tinymce.js';
        }

        return $plugins;
    }
    add_filter( 'mce_external_plugins', 'sop_notes_register_tinymce_plugin' );
}

// Require DB + domain helpers from Phase 1.
if ( ! class_exists( 'sop_DB' ) || ! function_exists( 'sop_supplier_get_all' ) ) {
    return;
}

/**
 * Register meta box on WooCommerce product edit screen.
 */
function sop_register_product_supplier_metabox() {
    add_meta_box(
        'sop_product_supplier',
        __( 'Stock Order', 'sop' ),
        'sop_render_product_supplier_metabox',
        'product',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'sop_register_product_supplier_metabox' );

/**
 * Render the supplier and SOP fields in the meta box.
 *
 * @param WP_Post $post Current product post object.
 */
function sop_render_product_supplier_metabox( $post ) {

    // Nonce for save verification.
    wp_nonce_field( 'sop_save_product_supplier_' . $post->ID, 'sop_product_supplier_nonce' );

    // Current supplier assignment.
    $current_supplier_id = sop_get_product_supplier_id( $post->ID );

    // Supplier costs per unit in supplier currencies.
    $cost_rmb = get_post_meta( $post->ID, '_sop_cost_rmb', true );
    $cost_usd = get_post_meta( $post->ID, '_sop_cost_usd', true );
    $cost_eur = get_post_meta( $post->ID, '_sop_cost_eur', true );

    // SOP minimum order quantity and pre-order notes.
    $min_order_qty  = get_post_meta( $post->ID, '_sop_min_order_qty', true );
    $min_order_qty  = '' !== $min_order_qty ? (float) $min_order_qty : '';

    // Optional max order quantity per month (cap for forecast suggestions).
    // Key: max_order_qty_per_month (canonical).
    $max_order_qty_per_month = get_post_meta( $post->ID, 'max_order_qty_per_month', true );

    if ( '' !== $max_order_qty_per_month ) {
        $parsed_max_order_qty_per_month = sop_parse_decimal_2dp( $max_order_qty_per_month );
        if ( null !== $parsed_max_order_qty_per_month ) {
            $max_float = (float) $parsed_max_order_qty_per_month;
            if ( $max_float < 0 ) {
                $parsed_max_order_qty_per_month = '0.00';
            }
            $max_order_qty_per_month = $parsed_max_order_qty_per_month;
        } else {
            $max_order_qty_per_month = '';
        }
    }

    $product_notes = get_post_meta( $post->ID, '_sop_product_notes', true );
    $product_notes = is_string( $product_notes ) ? $product_notes : '';

    $internal_product_notes = get_post_meta( $post->ID, '_sop_internal_product_notes', true );
    $internal_product_notes = is_string( $internal_product_notes ) ? $internal_product_notes : '';

    if ( function_exists( 'sop_notes_sanitize_html' ) ) {
        $product_notes = sop_notes_sanitize_html( $product_notes );
        $internal_product_notes = sop_notes_sanitize_html( $internal_product_notes );
    }

    $supplier_skus = get_post_meta( $post->ID, '_sop_supplier_skus', true );
    $supplier_skus = is_string( $supplier_skus ) ? $supplier_skus : '';
    // Get active suppliers.
    $suppliers = sop_supplier_get_all(
        array(
            'is_active' => 1,
        )
    );
    ?>
    <p>
        <label for="_sop_supplier_id">
            <?php esc_html_e( 'Supplier', 'sop' ); ?>
        </label>
    </p>

    <p>
        <select name="_sop_supplier_id" id="_sop_supplier_id" style="width:100%;">
            <option value="0">
                <?php esc_html_e( '— No supplier (exclude from Stock Order) —', 'sop' ); ?>
            </option>
            <?php if ( ! empty( $suppliers ) ) : ?>
                <?php foreach ( $suppliers as $supplier ) : ?>
                    <?php
                    $sid   = (int) $supplier->id;
                    $label = $supplier->name;
                    ?>
                    <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $current_supplier_id, $sid ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </p>

    <hr />

    <div class="sop-supplier-costs">
        <strong><?php esc_html_e( 'Supplier costs (per unit)', 'sop' ); ?></strong>
        <div class="sop-supplier-cost-row">
            <label for="sop_cost_rmb"><?php esc_html_e( 'RMB', 'sop' ); ?></label>
            <input type="text"
                   name="sop_cost_rmb"
                   id="sop_cost_rmb"
                   value="<?php echo esc_attr( $cost_rmb ); ?>"
                   class="small-text"
                   size="20" />
        </div>
        <div class="sop-supplier-cost-row">
            <label for="sop_cost_usd"><?php esc_html_e( 'USD', 'sop' ); ?></label>
            <input type="text"
                   name="sop_cost_usd"
                   id="sop_cost_usd"
                   value="<?php echo esc_attr( $cost_usd ); ?>"
                   class="small-text"
                   size="20" />
        </div>
        <div class="sop-supplier-cost-row">
            <label for="sop_cost_eur"><?php esc_html_e( 'EUR', 'sop' ); ?></label>
            <input type="text"
                   name="sop_cost_eur"
                   id="sop_cost_eur"
                   value="<?php echo esc_attr( $cost_eur ); ?>"
                   class="small-text"
                   size="20" />
        </div>
    </div>

    <p>
        <label for="sop_min_order_qty">
            <?php esc_html_e( 'SOP minimum order quantity', 'sop' ); ?>
        </label>
        <input type="number"
               step="0.01"
               min="0"
               name="sop_min_order_qty"
               id="sop_min_order_qty"
               value="<?php echo esc_attr( $min_order_qty ); ?>"
               class="small-text" />
    </p>

    <p>
        <label for="sop_max_order_qty_per_month">
            <?php esc_html_e( 'Max order quantity per month', 'sop' ); ?>
        </label>
        <input type="number"
               step="0.01"
               min="0"
               name="sop_max_order_qty_per_month"
               id="sop_max_order_qty_per_month"
               value="<?php echo esc_attr( $max_order_qty_per_month ); ?>"
               class="small-text" />
        <span class="description" style="display:block;margin-top:2px;">
            <?php esc_html_e( 'Optional ceiling for this SKU. Used for Max / Month and Max / Cycle in Forecast (Debug). Leave blank for no cap.', 'sop' ); ?>
        </span>
    </p>

    <p>
        <label for="sop_supplier_skus">
            <?php esc_html_e( 'Supplier SKUs (one per line)', 'sop' ); ?>
        </label>
        <textarea name="sop_supplier_skus"
                  id="sop_supplier_skus"
                  rows="4"
                  class="widefat"><?php echo esc_textarea( $supplier_skus ); ?></textarea>
        <span class="description" style="display:block;margin-top:2px;">
            <?php esc_html_e( 'Optional list of supplier/ERP codes for this product. One per line.', 'sop' ); ?>
        </span>
    </p>

    <p>
        <label for="sop_product_notes_editor">
            <?php esc_html_e( 'Product notes', 'sop' ); ?>
        </label>
        <?php
        wp_editor(
            $product_notes,
            'sop_product_notes_editor',
            array(
                'textarea_name' => 'sop_product_notes',
                'textarea_rows' => 4,
                'media_buttons' => false,
                'quicktags'     => false,
                'wpautop'       => false,
                'tinymce'       => array(
                    'toolbar1'          => 'bold,strikethrough,sopred',
                    'toolbar2'          => '',
                    'forced_root_block' => false,
                    'force_br_newlines' => true,
                    'force_p_newlines'  => false,
                    'content_style'     => '.sop-note-red{color:#d63638;}',
                ),
            )
        );
        ?>
    </p>
    <p>
        <label for="sop_internal_product_notes_editor">
            <?php esc_html_e( 'Internal product notes', 'sop' ); ?>
        </label>
        <?php
        wp_editor(
            $internal_product_notes,
            'sop_internal_product_notes_editor',
            array(
                'textarea_name' => 'sop_internal_product_notes',
                'textarea_rows' => 4,
                'media_buttons' => false,
                'quicktags'     => false,
                'wpautop'       => false,
                'tinymce'       => array(
                    'toolbar1'          => 'bold,strikethrough,sopred',
                    'toolbar2'          => '',
                    'forced_root_block' => false,
                    'force_br_newlines' => true,
                    'force_p_newlines'  => false,
                    'content_style'     => '.sop-note-red{color:#d63638;}',
                ),
            )
        );
        ?>
        <span class="description" style="display:block;margin-top:2px;">
            <?php esc_html_e( 'Private internal notes shown in Pre-Order / Goods-In.', 'sop' ); ?>
        </span>
    </p>

    <p style="font-size:11px;color:#666;">
        <?php esc_html_e( 'Only products with a supplier will be included in Stock Order forecasts and order sheets.', 'sop' ); ?>
    </p>
    <style>
        .sop-supplier-costs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            align-items: center;
            margin: 0 0 6px;
        }
        .sop-supplier-costs > strong {
            flex-basis: 100%;
        }
        .sop-supplier-cost-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sop-supplier-cost-row input[type="text"] {
            width: 8em;
        }

        /* Sidebar (narrow) layout: stack currency fields vertically. */
        #side-sortables #sop_product_supplier .sop-supplier-costs {
            flex-direction: column;
            align-items: stretch;
        }
        #side-sortables #sop_product_supplier .sop-supplier-cost-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            width: 100%;
        }
        #side-sortables #sop_product_supplier .sop-supplier-cost-row input[type="text"] {
            width: 100%;
            box-sizing: border-box;
        }
    </style>
    <?php
}

/**
 * Save product supplier and SOP meta when the product is saved.
 *
 * @param int $post_id Post ID.
 */
function sop_save_product_supplier_meta( $post_id ) {

    // Only run on product post type.
    if ( get_post_type( $post_id ) !== 'product' ) {
        return;
    }

    // Autosave / revisions: bail.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check nonce.
    if (
        ! isset( $_POST['sop_product_supplier_nonce'] )
        || ! wp_verify_nonce( $_POST['sop_product_supplier_nonce'], 'sop_save_product_supplier_' . $post_id )
    ) {
        return;
    }

    // Permission check.
    if ( ! current_user_can( 'edit_product', $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Read supplier ID from POST.
    $supplier_id = isset( $_POST['_sop_supplier_id'] ) ? (int) $_POST['_sop_supplier_id'] : 0;

    // Normalise: 0 or positive int only.
    if ( $supplier_id > 0 ) {
        update_post_meta( $post_id, '_sop_supplier_id', $supplier_id );
    } else {
        // 0 / empty = "no supplier" - delete meta to keep DB clean.
        delete_post_meta( $post_id, '_sop_supplier_id' );
    }

    // Location: write to SOP bin location (primary) and mirror to _product_location.
    if ( isset( $_POST['sop_bin_location'] ) ) {
        $location = trim( wp_unslash( (string) $_POST['sop_bin_location'] ) );
        if ( '' !== $location ) {
            update_post_meta( $post_id, '_sop_bin_location', $location );
            update_post_meta( $post_id, '_product_location', $location );
        } else {
            delete_post_meta( $post_id, '_sop_bin_location' );
            delete_post_meta( $post_id, '_product_location' );
        }
    }

    // Supplier costs per unit: RMB, USD, EUR.
    $cost_fields = array(
        '_sop_cost_rmb' => 'sop_cost_rmb',
        '_sop_cost_usd' => 'sop_cost_usd',
        '_sop_cost_eur' => 'sop_cost_eur',
    );

    foreach ( $cost_fields as $meta_key => $post_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            $raw = trim( (string) wp_unslash( $_POST[ $post_key ] ) );
            if ( '' === $raw ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                $raw = str_replace( ',', '.', $raw );
                $val = (float) $raw;
                update_post_meta( $post_id, $meta_key, $val );
            }
        }
    }

    // SOP minimum order quantity.
    if ( isset( $_POST['sop_min_order_qty'] ) ) {
        $raw = trim( (string) wp_unslash( $_POST['sop_min_order_qty'] ) );
        if ( '' === $raw ) {
            delete_post_meta( $post_id, '_sop_min_order_qty' );
        } else {
            $min_qty = (float) str_replace( ',', '.', $raw );
            $min_qty = max( 0, $min_qty );
            update_post_meta( $post_id, '_sop_min_order_qty', $min_qty );
        }
    }

    // Max order quantity per month (manual cap for forecast suggestions).
    if ( isset( $_POST['sop_max_order_qty_per_month'] ) ) {
        $raw_max = trim( (string) wp_unslash( $_POST['sop_max_order_qty_per_month'] ) );

        $parsed_max = sop_parse_decimal_2dp( $raw_max );

        if ( null === $parsed_max ) {
            // Empty or invalid removes the cap (primary meta only).
            delete_post_meta( $post_id, 'max_order_qty_per_month' );
        } else {
            $parsed_float = (float) $parsed_max;
            if ( $parsed_float < 0 ) {
                $parsed_max = '0.00';
            }

            update_post_meta( $post_id, 'max_order_qty_per_month', $parsed_max );
        }
    }

    // SOP product notes.
    if ( isset( $_POST['sop_product_notes'] ) ) {
        $notes_raw = wp_unslash( (string) $_POST['sop_product_notes'] );
        $notes     = function_exists( 'sop_notes_sanitize_html' ) ? sop_notes_sanitize_html( $notes_raw ) : sanitize_textarea_field( $notes_raw );
        if ( '' === $notes ) {
            delete_post_meta( $post_id, '_sop_product_notes' );
        } else {
            update_post_meta( $post_id, '_sop_product_notes', $notes );
        }
    }

    // SOP internal product notes.
    if ( isset( $_POST['sop_internal_product_notes'] ) ) {
        $notes_raw = wp_unslash( (string) $_POST['sop_internal_product_notes'] );
        $notes     = function_exists( 'sop_notes_sanitize_html' ) ? sop_notes_sanitize_html( $notes_raw ) : sanitize_textarea_field( $notes_raw );
        if ( '' === $notes ) {
            delete_post_meta( $post_id, '_sop_internal_product_notes' );
        } else {
            update_post_meta( $post_id, '_sop_internal_product_notes', $notes );
        }
    }

    if ( isset( $_POST['sop_supplier_skus'] ) ) {
        $skus_raw = sanitize_textarea_field( wp_unslash( (string) $_POST['sop_supplier_skus'] ) );
        if ( '' === $skus_raw ) {
            delete_post_meta( $post_id, '_sop_supplier_skus' );
        } else {
            update_post_meta( $post_id, '_sop_supplier_skus', $skus_raw );
        }
    }
}
add_action( 'save_post', 'sop_save_product_supplier_meta', 20 );

/**
 * Helper: Get the assigned Stock Order supplier ID for a product.
 *
 * @param int $product_id Product ID.
 * @return int Supplier ID or 0 if none.
 */
function sop_get_product_supplier_id( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return 0;
    }

    $supplier_id = get_post_meta( $product_id, '_sop_supplier_id', true );

    return (int) $supplier_id;
}

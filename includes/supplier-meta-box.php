<?php
/**
 * Stock Order Plugin - Phase 2
 * Product to Supplier assignment meta box (fixed for sop_supplier_get_all)
 * File version: 1.0.1
 *
 * - Adds a "Stock Order - Supplier" meta box to WooCommerce products.
 * - Uses sop_suppliers table via sop_supplier_get_all().
 * - Stores selected supplier ID in product meta: _sop_supplier_id.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        __( 'Stock Order – Supplier', 'sop' ),
        'sop_render_product_supplier_metabox',
        'product',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'sop_register_product_supplier_metabox' );

/**
 * Render the supplier select in the meta box.
 *
 * @param WP_Post $post
 */
function sop_render_product_supplier_metabox( $post ) {

    // Nonce for save verification.
    wp_nonce_field( 'sop_save_product_supplier_' . $post->ID, 'sop_product_supplier_nonce' );

    // Current value.
    $current_supplier_id = sop_get_product_supplier_id( $post->ID );

    // Get active suppliers.
    $suppliers = sop_supplier_get_all(
        array(
            'is_active' => 1,
        )
    );
    ?>
    <p>
        <label for="sop_supplier_id">
            <?php esc_html_e( 'Assign this product to a supplier for Stock Order calculations.', 'sop' ); ?>
        </label>
    </p>

    <p>
        <select name="sop_supplier_id" id="sop_supplier_id" style="width:100%;">
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

    <p style="font-size:11px;color:#666;">
        <?php esc_html_e( 'Only products with a supplier will be included in Stock Order forecasts and order sheets.', 'sop' ); ?>
    </p>
    <?php
}

/**
 * Save product supplier meta when the product is saved.
 *
 * @param int $post_id
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
    $supplier_id = isset( $_POST['sop_supplier_id'] ) ? (int) $_POST['sop_supplier_id'] : 0;

    // Normalise: 0 or positive int only.
    if ( $supplier_id > 0 ) {
        update_post_meta( $post_id, '_sop_supplier_id', $supplier_id );
    } else {
        // 0 / empty = "no supplier" → delete meta to keep DB clean.
        delete_post_meta( $post_id, '_sop_supplier_id' );
    }
}
add_action( 'save_post', 'sop_save_product_supplier_meta', 20 );

/**
 * Helper: Get the assigned Stock Order supplier ID for a product.
 *
 * @param int $product_id
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



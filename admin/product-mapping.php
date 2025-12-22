<?php
/**
 * Stock Order Plugin – Phase 2
 * Supplier Product Mapping Screen (paginated + totals)
 * File version: 1.0.01
 *
 * - Adds "Products by Supplier" submenu under Stock Order.
 * - Lets you select a supplier (or "Unassigned") and see products linked to it.
 * - Uses product meta _sop_supplier_id (set via the Stock Order – Supplier meta box).
 * - Paginated (200 per page by default) with total product count.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require DB + supplier helpers from previous phases.
if ( ! class_exists( 'sop_DB' ) || ! function_exists( 'sop_supplier_get_all' ) ) {
    return;
}

/**
 * Fallback helper: get product's supplier ID from meta
 * if sop_get_product_supplier_id() is not already defined elsewhere.
 */
if ( ! function_exists( 'sop_get_product_supplier_id' ) ) {
    function sop_get_product_supplier_id( $product_id ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            return 0;
        }

        $meta = get_post_meta( $product_id, '_sop_supplier_id', true );
        return ( '' === $meta ) ? 0 : (int) $meta;
    }
}

/**
 * Register the "Products by Supplier" submenu under Stock Order.
 */
function sop_register_products_by_supplier_submenu() {
    add_submenu_page(
        'sop_stock_order',
        __( 'Products by Supplier', 'sop' ),
        __( 'Products by Supplier', 'sop' ),
        'manage_woocommerce',
        'sop_products_by_supplier',
        'sop_render_products_by_supplier_page'
    );
}
add_action( 'admin_menu', 'sop_register_products_by_supplier_submenu' );

/**
 * Render the Products by Supplier screen.
 */
function sop_render_products_by_supplier_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    // Selected view: supplier ID or "unassigned".
    $selected = isset( $_GET['sop_supplier_view'] )
        ? sanitize_text_field( wp_unslash( $_GET['sop_supplier_view'] ) )
        : '';

    // Current page for pagination.
    $paged = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;
    if ( $paged < 1 ) {
        $paged = 1;
    }

    // How many products per page.
    $per_page = 200; // Adjust if needed.

    // Get all suppliers for the dropdown.
    $suppliers = sop_supplier_get_all();

    // Resolve current supplier object (if a numeric supplier is selected).
    $current_supplier    = null;
    $current_supplier_id = 0;
    $is_unassigned_view  = false;

    if ( 'unassigned' === $selected ) {
        $is_unassigned_view = true;
    } elseif ( is_numeric( $selected ) && (int) $selected > 0 ) {
        $current_supplier_id = (int) $selected;
        $current_supplier    = sop_supplier_get_by_id( $current_supplier_id );
    }

    echo '<div class="wrap sop-wrap">';
    echo '<h1>' . esc_html__( 'Products by Supplier', 'sop' ) . '</h1>';

    ?>
    <p><?php esc_html_e( 'Use this screen to confirm which products are linked to each supplier, and to find products that are currently unassigned (and therefore excluded from Stock Order calculations).', 'sop' ); ?></p>

    <form method="get" style="margin-bottom: 1em;">
        <input type="hidden" name="page" value="sop_products_by_supplier" />
        <label for="sop_supplier_view">
            <strong><?php esc_html_e( 'Select supplier:', 'sop' ); ?></strong>
        </label>
        <select name="sop_supplier_view" id="sop_supplier_view">
            <option value=""><?php esc_html_e( '— Choose a view —', 'sop' ); ?></option>
            <option value="unassigned" <?php selected( $is_unassigned_view ); ?>>
                <?php esc_html_e( 'Unassigned products (no supplier)', 'sop' ); ?>
            </option>
            <?php if ( ! empty( $suppliers ) ) : ?>
                <optgroup label="<?php esc_attr_e( 'Suppliers', 'sop' ); ?>">
                    <?php foreach ( $suppliers as $supplier ) : ?>
                        <?php
                        $sid   = (int) $supplier->id;
                        $label = $supplier->name; // No [Code: XYZ] appended – cleaner for user.
                        ?>
                        <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $current_supplier_id, $sid ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        </select>

        <?php submit_button( __( 'Filter', 'sop' ), 'secondary', '', false ); ?>
    </form>
    <?php

    // If no selection yet, stop here.
    if ( ! $is_unassigned_view && ! $current_supplier ) {
        echo '<p><em>' . esc_html__( 'Choose a supplier (or Unassigned) and click Filter to see products.', 'sop' ) . '</em></p>';
        echo '</div>';
        return;
    }

    // Build query for products.
    $query_args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    if ( $is_unassigned_view ) {
        // Products with NO _sop_supplier_id meta at all.
        $query_args['meta_query'] = array(
            array(
                'key'     => '_sop_supplier_id',
                'compare' => 'NOT EXISTS',
            ),
        );
    } else {
        // Products explicitly assigned to this supplier.
        $query_args['meta_query'] = array(
            array(
                'key'     => '_sop_supplier_id',
                'value'   => $current_supplier_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        );
    }

    $products_q = new WP_Query( $query_args );

    // Heading + description.
    if ( $is_unassigned_view ) {
        echo '<h2>' . esc_html__( 'Unassigned products', 'sop' ) . '</h2>';
        echo '<p>' . esc_html__( 'These products have no Stock Order supplier assigned and will be ignored by the forecasting and order generation logic.', 'sop' ) . '</p>';
    } else {
        $supplier_label = $current_supplier ? $current_supplier->name : '';
        echo '<h2>' . sprintf(
            /* translators: %s: supplier name */
            esc_html__( 'Products for supplier: %s', 'sop' ),
            esc_html( $supplier_label )
        ) . '</h2>';
    }

    // Total count & range info.
    $total_products = (int) $products_q->found_posts;

    if ( $total_products > 0 ) {
        $start_num = ( ( $paged - 1 ) * $per_page ) + 1;
        $end_num   = min( $total_products, $paged * $per_page );

        echo '<p><strong>' . sprintf(
            /* translators: 1: first item, 2: last item, 3: total */
            esc_html__( 'Showing %1$d–%2$d of %3$d products.', 'sop' ),
            $start_num,
            $end_num,
            $total_products
        ) . '</strong></p>';
    }

    if ( $products_q->have_posts() ) {
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Thumbnail', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Product', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Supplier', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'sop' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ( $products_q->have_posts() ) :
                    $products_q->the_post();
                    $product_id = get_the_ID();
                    $product    = wc_get_product( $product_id );

                    if ( ! $product ) {
                        continue;
                    }

                    $sku          = $product->get_sku();
                    $supplier_id  = sop_get_product_supplier_id( $product_id );
                    $supplier_obj = $supplier_id ? sop_supplier_get_by_id( $supplier_id ) : null;
                    ?>
                    <tr>
                        <td style="width:60px;">
                            <?php
                            echo get_the_post_thumbnail(
                                $product_id,
                                array( 50, 50 ),
                                array( 'style' => 'max-width:50px;height:auto;' )
                            );
                            ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html( $product->get_name() ); ?></strong><br />
                            <small><?php esc_html_e( 'ID:', 'sop' ); ?> <?php echo esc_html( $product_id ); ?></small>
                        </td>
                        <td>
                            <?php
                            if ( $sku ) {
                                echo esc_html( $sku );
                            } else {
                                echo '<span style="color:#999;">' . esc_html__( '(no SKU)', 'sop' ) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ( $supplier_obj ) {
                                // Show supplier name only – no [Code: XYZ] clutter.
                                echo esc_html( $supplier_obj->name );
                            } else {
                                echo '<span style="color:#999;">' . esc_html__( '— Unassigned —', 'sop' ) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $product_id, '' ) ); ?>">
                                <?php esc_html_e( 'Edit product', 'sop' ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php
                endwhile;
                wp_reset_postdata();
                ?>
            </tbody>
        </table>
        <?php

        // Pagination.
        $total_pages = (int) $products_q->max_num_pages;

        if ( $total_pages > 1 ) {
            $base = add_query_arg(
                array(
                    'page'              => 'sop_products_by_supplier',
                    'sop_supplier_view' => $selected,
                    'paged'             => '%#%',
                ),
                admin_url( 'admin.php' )
            );

            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links(
                array(
                    'base'      => $base,
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                )
            );
            echo '</div></div>';
        }
    } else {
        echo '<p><em>' . esc_html__( 'No products found for this view.', 'sop' ) . '</em></p>';
    }

    echo '</div>';
}

/**
 * Get live product display fields for one product (cached per request).
 *
 * @param int $product_id Product ID.
 * @param int $supplier_id Supplier ID for currency-specific fields.
 * @return array
 */
function sop_get_live_product_display_fields( $product_id, $supplier_id = 0 ) {
    static $cache = array();

    $product_id  = (int) $product_id;
    $supplier_id = (int) $supplier_id;

    if ( $product_id <= 0 ) {
        return array();
    }

    if ( isset( $cache[ $product_id ][ $supplier_id ] ) ) {
        return $cache[ $product_id ][ $supplier_id ];
    }

    $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
    if ( ! $product ) {
        $cache[ $product_id ][ $supplier_id ] = array();
        return array();
    }

    $sku           = (string) $product->get_sku();
    $product_name  = (string) $product->get_name();
    $regular_price = (float) $product->get_regular_price();
    if ( $regular_price < 0 ) {
        $regular_price = 0.0;
    }

    // Location (SOP bin location with fallback).
    $location = get_post_meta( $product_id, '_sop_bin_location', true );
    if ( '' === $location ) {
        $location = get_post_meta( $product_id, '_product_location', true );
    }
    $location = is_string( $location ) ? $location : '';

    // Product notes.
    $product_notes = get_post_meta( $product_id, '_sop_preorder_notes', true );
    $product_notes = is_string( $product_notes ) ? $product_notes : '';

    // Brand from taxonomy.
    $brand       = '';
    $brand_terms = wp_get_post_terms( $product_id, 'product_brand', array( 'fields' => 'names' ) );
    if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
        $brand = is_array( $brand_terms ) ? implode( ', ', $brand_terms ) : (string) $brand_terms;
    }

    // Category path using existing helper when available.
    $category = '';
    if ( function_exists( 'sop_get_product_category_path_below_root' ) ) {
        $category = (string) sop_get_product_category_path_below_root( $product_id );
    }

    // MOQ (min order qty).
    $moq = get_post_meta( $product_id, '_sop_min_order_qty', true );
    $moq = ( '' !== $moq ) ? (float) $moq : 0.0;
    if ( $moq < 0 ) {
        $moq = 0.0;
    }

    // Supplier currency cost (reuse preorder resolver when available).
    $cost_supplier = '';
    if ( function_exists( 'sop_preorder_resolve_supplier_params' ) && function_exists( 'sop_preorder_get_cost_for_supplier_currency' ) ) {
        $ctx      = sop_preorder_resolve_supplier_params( $supplier_id );
        $currency = isset( $ctx['supplier']['currency_code'] ) ? sop_preorder_normalise_currency( $ctx['supplier']['currency_code'] ) : 'GBP';
        $settings = function_exists( 'sop_preorder_get_settings' ) ? sop_preorder_get_settings() : null;
        $cost     = sop_preorder_get_cost_for_supplier_currency( $product_id, $currency, $settings );
        if ( null !== $cost && '' !== $cost ) {
            $cost_supplier = (float) $cost;
        }
    }

    // cm3 per unit from product dimensions (CM).
    $length = (float) $product->get_length();
    $width  = (float) $product->get_width();
    $height = (float) $product->get_height();
    $cm3_per_unit = 0.0;
    if ( $length > 0 && $width > 0 && $height > 0 ) {
        $cm3_per_unit = $length * $width * $height;
    }

    $data = array(
        'sku'                 => $sku,
        'product_name'        => $product_name,
        'regular_price'       => $regular_price,
        'brand'               => $brand,
        'category'            => $category,
        'location'            => $location,
        'product_notes'       => $product_notes,
        'cm3_per_unit'        => $cm3_per_unit,
        'moq'                 => $moq,
        'cost_per_unit'       => $cost_supplier,
    );

    $cache[ $product_id ][ $supplier_id ] = $data;
    return $data;
}

/**
 * Hydrate a line array with live product display fields (non-destructive).
 *
 * @param array $line Saved line data.
 * @param int   $supplier_id Supplier ID for currency lookups.
 * @return array
 */
function sop_hydrate_line_with_live_product_fields( array $line, $supplier_id = 0 ) {
    $supplier_id = (int) $supplier_id;

    $product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
    if ( $product_id <= 0 && ! empty( $line['sku_owner'] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
        $maybe_pid = wc_get_product_id_by_sku( (string) $line['sku_owner'] );
        if ( $maybe_pid > 0 ) {
            $product_id = $maybe_pid;
        }
    }

    if ( $product_id <= 0 ) {
        return $line;
    }

    $live = sop_get_live_product_display_fields( $product_id, $supplier_id );
    if ( empty( $live ) ) {
        return $line;
    }

    // Keys that must never be overwritten (saved snapshots).
    $protected_keys = array(
        'current_stock',
        'stock_qty',
        'saved_stock',
        'stock_at_save',
        'stock_snapshot',
        'stock_on_hand_saved',
    );

    $map = array(
        'sku_owner'            => 'sku',
        'sku'                  => 'sku',
        'product_name'         => 'product_name',
        'name'                 => 'product_name',
        'brand'                => 'brand',
        'category'             => 'category',
        'category_path'        => 'category',
        'location'             => 'location',
        'product_notes_owner'  => 'product_notes',
        'product_notes'        => 'product_notes',
        'moq_owner'            => 'moq',
        'moq'                  => 'moq',
        'cost_rmb_owner'       => 'cost_per_unit',
        'cost_owner'           => 'cost_per_unit',
        'cost_unit_supplier'   => 'cost_per_unit',
        'cbm_per_unit'         => 'cm3_per_unit',
        'cm3_per_unit'         => 'cm3_per_unit',
        'regular_unit_price'   => 'regular_price',
        'price_ex'             => 'regular_price',
    );

    foreach ( $map as $line_key => $live_key ) {
        if ( in_array( $line_key, $protected_keys, true ) ) {
            continue;
        }
        if ( array_key_exists( $line_key, $line ) && isset( $live[ $live_key ] ) ) {
            $line[ $line_key ] = $live[ $live_key ];
        }
    }

    return $line;
}

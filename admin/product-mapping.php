/**
 * Stock Order Plugin – Phase 2
 * Supplier Product Mapping Screen (paginated + totals + clearer labels)
 *
 * - Adds "Products by Supplier" submenu under Stock Order.
 * - Lets you select a supplier (or "Unassigned") and see products linked to it.
 * - Uses product meta _sop_supplier_id (set via the Stock Order – Supplier meta box).
 * - Paginated (200 per page by default) with total product count.
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

// Require DB + supplier helpers + product meta helper from previous snippets.
if ( ! class_exists( 'sop_DB' ) || ! function_exists( 'sop_supplier_get_all' ) || ! function_exists( 'sop_get_product_supplier_id' ) ) {
    return;
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
    $selected = isset( $_GET['sop_supplier_view'] ) ? sanitize_text_field( wp_unslash( $_GET['sop_supplier_view'] ) ) : '';

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
    $current_supplier      = null;
    $current_supplier_id   = 0;
    $is_unassigned_view    = false;

    if ( $selected === 'unassigned' ) {
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
                        $label = $supplier->name;

                        // Show code clearly labelled to avoid confusion with counts.
                        if ( ! empty( $supplier->supplier_code ) ) {
                            $label .= ' [Code: ' . $supplier->supplier_code . ']';
                        }
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
                            <small>ID: <?php echo esc_html( $product_id ); ?></small>
                        </td>
                        <td>
                            <?php echo $sku ? esc_html( $sku ) : '<span style="color:#999;">' . esc_html__( '(no SKU)', 'sop' ) . '</span>'; ?>
                        </td>
                        <td>
                            <?php
                            if ( $supplier_obj ) {
                                $s_label = $supplier_obj->name;
                                if ( ! empty( $supplier_obj->supplier_code ) ) {
                                    $s_label .= ' [Code: ' . $supplier_obj->supplier_code . ']';
                                }
                                echo esc_html( $s_label );
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
            echo paginate_links( array(
                'base'      => $base,
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ) );
            echo '</div></div>';
        }

    } else {
        echo '<p><em>' . esc_html__( 'No products found for this view.', 'sop' ) . '</em></p>';
    }

    echo '</div>';
}

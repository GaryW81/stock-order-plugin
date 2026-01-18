<?php
/**
 * Stock Order Plugin - TEMP Tool - Shiny CSV Import
 * File Version: 1.0.0
 * - TEMP: Import Shiny CSV to create a preorder sheet for supplier #1.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_temp_shiny_get_supplier_id_for_product' ) ) {
    /**
     * Resolve supplier ID for a product or variation.
     *
     * @param int $product_id Product or variation ID.
     * @return int
     */
    function sop_temp_shiny_get_supplier_id_for_product( $product_id ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            return 0;
        }

        $supplier_id = (int) get_post_meta( $product_id, '_sop_supplier_id', true );
        if ( $supplier_id > 0 ) {
            return $supplier_id;
        }

        $parent_id = wp_get_post_parent_id( $product_id );
        if ( $parent_id > 0 ) {
            $supplier_id = (int) get_post_meta( $parent_id, '_sop_supplier_id', true );
        }

        return (int) $supplier_id;
    }
}

if ( ! function_exists( 'sop_render_temp_shiny_csv_import_page' ) ) {
    /**
     * Render TEMP Shiny CSV importer admin page.
     *
     * @return void
     */
    function sop_render_temp_shiny_csv_import_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'sop' ) );
        }

        $result = array(
            'status'   => '',
            'messages' => array(),
            'sheet_id' => 0,
            'imported' => 0,
            'skipped'  => 0,
            'warnings' => array(),
        );

        $order_number = '16';
        $require_supplier = true;
        $set_ordered = true;

        if ( isset( $_POST['sop_temp_shiny_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $nonce = isset( $_POST['sop_temp_shiny_import_nonce'] )
                ? sanitize_text_field( wp_unslash( $_POST['sop_temp_shiny_import_nonce'] ) )
                : '';

            if ( ! wp_verify_nonce( $nonce, 'sop_temp_shiny_import' ) ) {
                $result['status'] = 'error';
                $result['messages'][] = __( 'Security check failed.', 'sop' );
            } else {
                $order_number = isset( $_POST['sop_temp_order_number'] )
                    ? sanitize_text_field( wp_unslash( $_POST['sop_temp_order_number'] ) )
                    : '16';
                $require_supplier = ! empty( $_POST['sop_temp_require_supplier'] );
                $set_ordered = ! empty( $_POST['sop_temp_set_ordered'] );

                if ( empty( $_FILES['sop_temp_csv_file']['tmp_name'] ) ) {
                    $result['status'] = 'error';
                    $result['messages'][] = __( 'Please upload a CSV file.', 'sop' );
                } else {
                    $upload = wp_handle_upload(
                        $_FILES['sop_temp_csv_file'],
                        array(
                            'test_form' => false,
                            'mimes'     => array(
                                'csv' => 'text/csv',
                                'txt' => 'text/plain',
                            ),
                        )
                    );

                    if ( isset( $upload['error'] ) ) {
                        $result['status'] = 'error';
                        $result['messages'][] = sprintf( __( 'Upload failed: %s', 'sop' ), $upload['error'] );
                    } else {
                        $file_path = isset( $upload['file'] ) ? (string) $upload['file'] : '';
                        if ( '' === $file_path || ! file_exists( $file_path ) ) {
                            $result['status'] = 'error';
                            $result['messages'][] = __( 'Uploaded file could not be read.', 'sop' );
                        } else {
                            $handle = fopen( $file_path, 'r' );
                            if ( false === $handle ) {
                                $result['status'] = 'error';
                                $result['messages'][] = __( 'Failed to open the uploaded CSV.', 'sop' );
                            } else {
                                $header = fgetcsv( $handle );
                                if ( ! is_array( $header ) ) {
                                    $result['status'] = 'error';
                                    $result['messages'][] = __( 'CSV header row is missing.', 'sop' );
                                } else {
                                    $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
                                    $map = array();
                                    foreach ( $header as $idx => $col ) {
                                        $key = strtolower( trim( (string) $col ) );
                                        $map[ $key ] = $idx;
                                    }

                                    if ( ! isset( $map['id'] ) || ! isset( $map['qty'] ) ) {
                                        $result['status'] = 'error';
                                        $result['messages'][] = __( 'CSV must contain ID and QTY columns.', 'sop' );
                                    } else {
                                        $items = array();
                                        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                                            $id_val  = isset( $row[ $map['id'] ] ) ? (int) $row[ $map['id'] ] : 0;
                                            $qty_val = isset( $row[ $map['qty'] ] ) ? (int) $row[ $map['qty'] ] : 0;
                                            if ( $id_val <= 0 ) {
                                                continue;
                                            }
                                            if ( $qty_val < 0 ) {
                                                $qty_val = 0;
                                            }
                                            if ( ! isset( $items[ $id_val ] ) ) {
                                                $items[ $id_val ] = 0;
                                            }
                                            $items[ $id_val ] += $qty_val;
                                        }

                                        if ( empty( $items ) ) {
                                            $result['status'] = 'error';
                                            $result['messages'][] = __( 'No valid rows found in the CSV.', 'sop' );
                                        } else {
                                            $lines = array();
                                            $sort_index = 0;
                                            $warnings = array();
                                            $imported = 0;
                                            $skipped = 0;

                                            foreach ( $items as $product_id => $qty ) {
                                                $qty = (int) $qty;
                                                if ( $qty <= 0 ) {
                                                    $skipped++;
                                                    $warnings[] = sprintf( __( 'ID %d skipped (qty 0).', 'sop' ), (int) $product_id );
                                                    continue;
                                                }

                                                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
                                                if ( ! $product ) {
                                                    $skipped++;
                                                    $warnings[] = sprintf( __( 'ID %d skipped (product not found).', 'sop' ), (int) $product_id );
                                                    continue;
                                                }

                                                $sku = (string) $product->get_sku();
                                                if ( '' === $sku ) {
                                                    $skipped++;
                                                    $warnings[] = sprintf( __( 'ID %d skipped (missing SKU).', 'sop' ), (int) $product_id );
                                                    continue;
                                                }

                                                if ( $require_supplier ) {
                                                    $supplier_id = sop_temp_shiny_get_supplier_id_for_product( $product_id );
                                                    if ( 1 !== (int) $supplier_id ) {
                                                        $skipped++;
                                                        $warnings[] = sprintf( __( 'ID %d skipped (supplier mismatch).', 'sop' ), (int) $product_id );
                                                        continue;
                                                    }
                                                }

                                                $lines[] = array(
                                                    'product_id'       => (int) $product_id,
                                                    'sku_owner'        => $sku,
                                                    'qty_owner'        => (float) $qty,
                                                    'is_removed_owner' => 0,
                                                    'sort_index'       => $sort_index++,
                                                );
                                                $imported++;
                                            }

                                            if ( empty( $lines ) ) {
                                                $result['status'] = 'error';
                                                $result['messages'][] = __( 'No valid rows to import after validation.', 'sop' );
                                            } else {
                                                $supplier_label = function_exists( 'sop_get_supplier_label' ) ? sop_get_supplier_label( 1 ) : 'Supplier #1';
                                                $now_utc = current_time( 'mysql', true );

                                                $header_data = array(
                                                    'supplier_id'       => 1,
                                                    'status'            => $set_ordered ? 'locked' : 'draft',
                                                    'title'             => $supplier_label,
                                                    'order_number_label'=> $order_number,
                                                    'edit_version'      => 1,
                                                    'created_at'        => $now_utc,
                                                    'updated_at'        => $now_utc,
                                                );

                                                $sheet_id = function_exists( 'sop_insert_preorder_sheet' )
                                                    ? sop_insert_preorder_sheet( $header_data )
                                                    : 0;

                                                if ( is_wp_error( $sheet_id ) || ! $sheet_id ) {
                                                    $result['status'] = 'error';
                                                    $result['messages'][] = __( 'Failed to create preorder sheet.', 'sop' );
                                                } else {
                                                    $sheet_id = (int) $sheet_id;
                                                    $lines_result = function_exists( 'sop_insert_preorder_sheet_lines' )
                                                        ? sop_insert_preorder_sheet_lines( $sheet_id, $lines )
                                                        : new WP_Error( 'sop_insert_preorder_lines_failed', __( 'Preorder line insert function missing.', 'sop' ) );

                                                    if ( is_wp_error( $lines_result ) ) {
                                                        $result['status'] = 'error';
                                                        $result['messages'][] = __( 'Failed to insert preorder sheet lines.', 'sop' );
                                                    } else {
                                                        $result['status'] = 'success';
                                                        $result['sheet_id'] = $sheet_id;
                                                        $result['imported'] = $imported;
                                                        $result['skipped'] = $skipped;
                                                        $result['warnings'] = $warnings;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                fclose( $handle );
                            }
                        }
                    }
                }
            }
        }

        $open_sheet_url = '';
        $goodsin_url = '';
        if ( ! empty( $result['sheet_id'] ) ) {
            $open_sheet_url = add_query_arg(
                array(
                    'page' => 'sop-preorder-sheet',
                    '_sop_supplier_id' => 1,
                    'sop_sheet_id' => (int) $result['sheet_id'],
                ),
                admin_url( 'admin.php' )
            );
            $goodsin_url = add_query_arg(
                array(
                    'page' => 'sop-goods-in',
                    'sop_sheet_id' => (int) $result['sheet_id'],
                ),
                admin_url( 'admin.php' )
            );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'TEMP: Import Shiny International Order CSV', 'sop' ); ?></h1>

            <div class="notice notice-warning">
                <p><strong><?php esc_html_e( 'TEMP TOOL - DELETE AFTER USE.', 'sop' ); ?></strong></p>
            </div>

            <?php if ( 'error' === $result['status'] ) : ?>
                <div class="notice notice-error">
                    <?php foreach ( $result['messages'] as $msg ) : ?>
                        <p><?php echo esc_html( $msg ); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php elseif ( 'success' === $result['status'] ) : ?>
                <div class="notice notice-success">
                    <p><?php printf( esc_html__( 'Created sheet ID: %d', 'sop' ), (int) $result['sheet_id'] ); ?></p>
                    <?php if ( $open_sheet_url ) : ?>
                        <p><a href="<?php echo esc_url( $open_sheet_url ); ?>" class="button button-primary"><?php esc_html_e( 'Open sheet', 'sop' ); ?></a>
                        <a href="<?php echo esc_url( $goodsin_url ); ?>" class="button"><?php esc_html_e( 'Go to Goods-In', 'sop' ); ?></a></p>
                    <?php endif; ?>
                    <p><?php printf( esc_html__( 'Imported lines: %d. Skipped: %d.', 'sop' ), (int) $result['imported'], (int) $result['skipped'] ); ?></p>
                    <?php if ( ! empty( $result['warnings'] ) ) : ?>
                        <p><strong><?php esc_html_e( 'Warnings (first 20):', 'sop' ); ?></strong></p>
                        <ul>
                            <?php
                            $shown = 0;
                            foreach ( $result['warnings'] as $warning ) :
                                $shown++;
                                if ( $shown > 20 ) {
                                    break;
                                }
                                ?>
                                <li><?php echo esc_html( $warning ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ( count( $result['warnings'] ) > 20 ) : ?>
                            <p><?php printf( esc_html__( '...and %d more warnings.', 'sop' ), (int) ( count( $result['warnings'] ) - 20 ) ); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'sop_temp_shiny_import', 'sop_temp_shiny_import_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sop-temp-csv"><?php esc_html_e( 'CSV file', 'sop' ); ?></label></th>
                        <td><input type="file" id="sop-temp-csv" name="sop_temp_csv_file" accept=".csv,text/csv" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop-temp-order-number"><?php esc_html_e( 'Order #', 'sop' ); ?></label></th>
                        <td><input type="text" id="sop-temp-order-number" name="sop_temp_order_number" value="<?php echo esc_attr( $order_number ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Require supplier match', 'sop' ); ?></th>
                        <td><label><input type="checkbox" name="sop_temp_require_supplier" value="1" <?php checked( $require_supplier ); ?>> <?php esc_html_e( 'Require _sop_supplier_id = 1', 'sop' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Set sheet status to Ordered', 'sop' ); ?></th>
                        <td><label><input type="checkbox" name="sop_temp_set_ordered" value="1" <?php checked( $set_ordered ); ?>> <?php esc_html_e( 'Set status to Ordered (Goods-In ready)', 'sop' ); ?></label></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary" name="sop_temp_shiny_submit" value="1"><?php esc_html_e( 'Import CSV', 'sop' ); ?></button></p>
            </form>
        </div>
        <?php
    }
}

<?php
/**
 * Stock Order Plugin - Preorder Excel Exporter
 * File version: 1.1.8
 *
 * Excel-compatible HTML export (with embedded images) for saved Pre-Order sheets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOP_Preorder_Excel_Exporter {

    /**
     * Build an Excel-compatible HTML table for a saved pre-order sheet.
     *
     * @param array $header Sheet header data.
     * @param array $lines  Line rows.
     * @return string
     */
    public static function build_html_table( $header, $lines ) {
        $image_cell_size_px  = 72; // Outer dimension for the image column.
        $image_padding_px    = 1;  // Padding inside the image cell.
        $row_height_px       = 72; // Row height to match image cell.
        $image_display_size_px = 60; // Actual image size inside the cell.

        $html  = '<html><head><meta charset="utf-8" /></head><body>';
        $html .= '<table border="1" cellspacing="0" cellpadding="3">';
        $html .= '<colgroup>';
        $html .= '<col style="width:' . (int) $image_cell_size_px . 'px;" />';
        for ( $i = 0; $i < 14; $i++ ) {
            $html .= '<col />';
        }
        $html .= '</colgroup>';

        $html .= '<tr>';
        $columns = array(
            __( 'Image', 'sop' ),
            __( 'SKU', 'sop' ),
            __( 'Brand', 'sop' ),
            __( 'Product name', 'sop' ),
            __( 'Categories', 'sop' ),
            __( 'MOQ', 'sop' ),
            __( 'Qty', 'sop' ),
            __( 'Unit price (RMB)', 'sop' ),
            __( 'Unit price (USD)', 'sop' ),
            __( 'Total (RMB)', 'sop' ),
            __( 'Product notes', 'sop' ),
            __( 'Order notes', 'sop' ),
            __( 'Carton no.', 'sop' ),
            __( 'cm3 per unit', 'sop' ),
            __( 'Line CBM', 'sop' ),
        );
        foreach ( $columns as $col ) {
            $html .= '<th>' . esc_html( $col ) . '</th>';
        }
        $html .= '</tr>';

        foreach ( $lines as $line ) {
            $product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
            $sku         = isset( $line['sku'] ) ? $line['sku'] : '';
            $brand       = isset( $line['brand'] ) ? $line['brand'] : '';
            $name        = isset( $line['product_name'] ) ? $line['product_name'] : '';
            $categories  = isset( $line['categories'] ) ? $line['categories'] : '';
            $moq         = isset( $line['moq'] ) ? (float) $line['moq'] : 0;
            $qty         = isset( $line['qty'] ) ? (float) $line['qty'] : 0;
            $cost_rmb    = isset( $line['cost_rmb'] ) ? (float) $line['cost_rmb'] : ( isset( $line['cost_per_unit'] ) ? (float) $line['cost_per_unit'] : 0 );
            $cost_usd    = '';
            if ( $cost_rmb > 0 && function_exists( 'sop_convert_rmb_unit_cost_to_usd' ) ) {
                $converted = sop_convert_rmb_unit_cost_to_usd( $cost_rmb );
                if ( $converted > 0 ) {
                    $cost_usd = number_format_i18n( $converted, 2 );
                }
            }
            $line_total_rmb = isset( $line['line_total_rmb'] ) ? $line['line_total_rmb'] : ( isset( $line['line_total'] ) ? $line['line_total'] : ( $qty * $cost_rmb ) );
            $product_notes  = isset( $line['product_notes'] ) ? $line['product_notes'] : '';
            $order_notes    = isset( $line['order_notes'] ) ? $line['order_notes'] : '';
            $carton_number  = isset( $line['carton_number'] ) ? $line['carton_number'] : '';
            $cm3_per_unit   = isset( $line['cm3_per_unit'] ) ? $line['cm3_per_unit'] : '';
            $line_cbm       = isset( $line['line_cbm'] ) ? $line['line_cbm'] : '';
            $image_id       = 0;

            if ( isset( $line['image_id'] ) ) {
                $image_id = (int) $line['image_id'];
            } elseif ( $product_id ) {
                $image_id = get_post_thumbnail_id( $product_id );
            }

            $thumb_url = '';
            if ( $image_id ) {
                $src = wp_get_attachment_image_src( $image_id, 'woocommerce_gallery_thumbnail' );
                if ( $src && ! empty( $src[0] ) ) {
                    $thumb_url = $src[0];
                }
                if ( empty( $thumb_url ) ) {
                    $src = wp_get_attachment_image_src( $image_id, 'thumbnail' );
                    if ( $src && ! empty( $src[0] ) ) {
                        $thumb_url = $src[0];
                    }
                }
            }

            $img_td_style = sprintf(
                'width:%dpx;height:%dpx;border:1px solid #000;vertical-align:middle;text-align:center;',
                (int) $image_cell_size_px,
                (int) $image_cell_size_px
            );

            $html .= '<tr style="height:' . (int) $row_height_px . 'px;">';
            $html .= '<td style="' . $img_td_style . '">';
            if ( $thumb_url ) {
                $html .= '<img src="' . esc_url( $thumb_url ) . '" alt="" width="' . (int) $image_display_size_px . '" height="' . (int) $image_display_size_px . '" style="display:block;margin:1px auto;" />';
            }
            $html .= '</td>';
            $html .= '<td>' . esc_html( $sku ) . '</td>';
            $html .= '<td>' . esc_html( $brand ) . '</td>';
            $html .= '<td>' . esc_html( $name ) . '</td>';
            $html .= '<td>' . esc_html( $categories ) . '</td>';
            $html .= '<td>' . esc_html( $moq ) . '</td>';
            $html .= '<td>' . esc_html( $qty ) . '</td>';
            $html .= '<td>' . esc_html( $cost_rmb ) . '</td>';
            $html .= '<td>' . esc_html( $cost_usd ) . '</td>';
            $html .= '<td>' . esc_html( $line_total_rmb ) . '</td>';
            $html .= '<td>' . esc_html( $product_notes ) . '</td>';
            $html .= '<td>' . esc_html( $order_notes ) . '</td>';
            $html .= '<td>' . esc_html( $carton_number ) . '</td>';
            $html .= '<td>' . esc_html( $cm3_per_unit ) . '</td>';
            $html .= '<td>' . esc_html( $line_cbm ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        return $html;
    }
}

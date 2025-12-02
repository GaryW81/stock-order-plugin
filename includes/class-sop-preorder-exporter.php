<?php
/**
 * Stock Order Plugin - Preorder Excel Exporter
 * File version: 1.1.4
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
        $image_cell_size_px  = 62; // Outer dimension for the image column.
        $image_padding_px    = 1;  // Padding inside the image cell.
        $row_height_px       = 62; // Row height to match image cell.

        $html  = '<html><head><meta charset="utf-8" /></head><body>';
        $html .= '<table border="1" cellspacing="0" cellpadding="3">';
        $html .= '<colgroup>';
        $html .= '<col style="width:' . (int) $image_cell_size_px . 'px;" />';
        for ( $i = 0; $i < 10; $i++ ) {
            $html .= '<col />';
        }
        $html .= '</colgroup>';

        $html .= '<tr>';
        $columns = array(
            'Image',
            'Product ID',
            'SKU',
            'Product name',
            'Location',
            'MOQ',
            'SOQ',
            'Qty',
            'Cost per unit',
            'Line total',
            'Notes',
        );
        foreach ( $columns as $col ) {
            $html .= '<th>' . esc_html( $col ) . '</th>';
        }
        $html .= '</tr>';

        foreach ( $lines as $line ) {
            $product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
            $sku        = isset( $line['sku'] ) ? $line['sku'] : '';
            $name       = isset( $line['product_name'] ) ? $line['product_name'] : '';
            $location   = isset( $line['location'] ) ? $line['location'] : '';
            $moq        = isset( $line['moq'] ) ? (float) $line['moq'] : 0;
            $soq        = isset( $line['soq'] ) ? (float) $line['soq'] : 0;
            $qty        = isset( $line['qty'] ) ? (float) $line['qty'] : 0;
            $cost_unit  = isset( $line['cost_per_unit'] ) ? (float) $line['cost_per_unit'] : 0;
            $line_total = isset( $line['line_total'] ) ? (float) $line['line_total'] : ( $qty * $cost_unit );
            $notes      = isset( $line['notes'] ) ? $line['notes'] : '';
            $image_id   = 0;

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
                $html .= '<img src="' . esc_url( $thumb_url ) . '" alt="" width="60" height="60" style="display:block;margin:1px auto;" />';
            }
            $html .= '</td>';
            $html .= '<td>' . (int) $product_id . '</td>';
            $html .= '<td>' . esc_html( $sku ) . '</td>';
            $html .= '<td>' . esc_html( $name ) . '</td>';
            $html .= '<td>' . esc_html( $location ) . '</td>';
            $html .= '<td>' . esc_html( $moq ) . '</td>';
            $html .= '<td>' . esc_html( $soq ) . '</td>';
            $html .= '<td>' . esc_html( $qty ) . '</td>';
            $html .= '<td>' . esc_html( $cost_unit ) . '</td>';
            $html .= '<td>' . esc_html( $line_total ) . '</td>';
            $html .= '<td>' . esc_html( $notes ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        return $html;
    }
}

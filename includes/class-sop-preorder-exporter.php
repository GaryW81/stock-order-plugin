<?php
/**
 * Stock Order Plugin - Preorder Excel Exporter
 * File version: 1.1.2
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
        $image_cell_size_px  = 60; // Outer dimension for the image column.
        $image_padding_px    = 1;  // Padding inside the image cell.
        $image_inner_max_px  = $image_cell_size_px - ( 2 * $image_padding_px );
        $row_height_px       = $image_cell_size_px + 2; // Slightly taller than the image.

        $html  = '<html><head><meta charset="utf-8" /></head><body>';
        $html .= '<table border="1" cellspacing="0" cellpadding="3">';
        $html .= '<colgroup>';
        $html .= '<col style="width:' . (int) $image_cell_size_px . 'px;" />';
        // Remaining columns use default sizing.
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

            $image_url = '';
            if ( $image_id ) {
                $image_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
            }

            $html .= '<tr style="height:' . (int) $row_height_px . 'px;">';

            $img_td_style = sprintf(
                'width:%dpx;height:%dpx;padding:%dpx;text-align:center;vertical-align:middle;',
                (int) $image_cell_size_px,
                (int) $image_cell_size_px,
                (int) $image_padding_px
            );
            $html .= '<td style="' . $img_td_style . '">';
            if ( $image_url ) {
                $html .= '<img src="' . esc_url( $image_url ) . '" alt="" width="58" height="58" style="display:block;margin:0 auto;" />';
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

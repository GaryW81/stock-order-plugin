<?php
/**
 * Stock Order Plugin - Preorder Excel Exporter
 * File version: 1.1.11
 * - Add PO-only HTML export (order sheet export unchanged).
 * - Use Balance FX or supplier-effective FX for USD values in export.
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
        $image_cell_size_px  = 80; // Outer dimension for the image column.
        $image_padding_px    = 1;  // Padding inside the image cell.
        $row_height_px       = 80; // Row height to match image cell.
        $image_display_size_px = 60; // Actual image size inside the cell.

        // Determine sheet-level FX for USD display: Balance FX (payload) > supplier effective FX.
        $sheet_fx_for_usd = 0.0;
        $po_payload       = array();

        if ( ! empty( $header['header_notes_owner'] ) && is_string( $header['header_notes_owner'] ) ) {
            $decoded = json_decode( $header['header_notes_owner'], true );
            if ( is_array( $decoded ) ) {
                $po_payload = $decoded;
            }
        }

        $sheet_balance_fx_rate = isset( $po_payload['balance_fx_rate'] ) ? (float) $po_payload['balance_fx_rate'] : 0.0;

        $sheet_supplier_effective_fx = 0.0;
        if ( isset( $header['supplier_id'] ) && function_exists( 'sop_get_supplier_effective_usd_to_rmb_rate' ) ) {
            $sheet_supplier_effective_fx = (float) sop_get_supplier_effective_usd_to_rmb_rate( (int) $header['supplier_id'] );
        }

        if ( $sheet_balance_fx_rate > 0 ) {
            $sheet_fx_for_usd = $sheet_balance_fx_rate;
        } elseif ( $sheet_supplier_effective_fx > 0 ) {
            $sheet_fx_for_usd = $sheet_supplier_effective_fx;
        }

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
            if ( $cost_rmb > 0 && $sheet_fx_for_usd > 0 ) {
                $cost_usd = number_format_i18n( $cost_rmb / $sheet_fx_for_usd, 2 );
            } elseif ( $cost_rmb > 0 && function_exists( 'sop_convert_rmb_unit_cost_to_usd' ) ) {
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
                $html .= '<img src="' . esc_url( $thumb_url ) . '" alt="" width="' . (int) $image_display_size_px . '" height="' . (int) $image_display_size_px . '" style="display:block;margin:5px auto;" />';
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

    /**
     * Build a Purchase Order-only HTML export (Excel-compatible).
     *
     * @param array $sheet_header Sheet header data.
     * @param array $line_rows    Line rows.
     * @return string|WP_Error
     */
    public static function build_purchase_order_html( array $sheet_header, array $line_rows ) {
        $po_payload = array();
        if ( ! empty( $sheet_header['header_notes_owner'] ) && is_string( $sheet_header['header_notes_owner'] ) ) {
            $decoded = json_decode( $sheet_header['header_notes_owner'], true );
            if ( is_array( $decoded ) ) {
                $po_payload = $decoded;
            }
        }

        $supplier_id   = isset( $sheet_header['supplier_id'] ) ? (int) $sheet_header['supplier_id'] : 0;
        $supplier_name = isset( $sheet_header['supplier_name'] ) ? $sheet_header['supplier_name'] : '';

        $supplier_params = function_exists( 'sop_preorder_resolve_supplier_params' )
            ? sop_preorder_resolve_supplier_params( $supplier_id )
            : array();

        $supplier_currency = ! empty( $supplier_params['currency_code'] ) ? $supplier_params['currency_code'] : 'GBP';
        $currency_label    = $supplier_currency;

        $supplier_pi = array(
            'company_name'    => $supplier_name,
            'company_address' => '',
            'company_phone'   => '',
            'company_email'   => '',
            'contact_name'    => '',
            'bank_details'    => '',
            'payment_terms'   => '',
        );

        if ( $supplier_id > 0 && function_exists( 'sop_supplier_get_by_id' ) ) {
            $supplier_obj = sop_supplier_get_by_id( $supplier_id );
            if ( $supplier_obj && ! empty( $supplier_obj->settings_json ) ) {
                $settings_arr = json_decode( $supplier_obj->settings_json, true );
                if ( is_array( $settings_arr ) ) {
                    if ( ! empty( $settings_arr['pi_company_name'] ) ) {
                        $supplier_pi['company_name'] = (string) $settings_arr['pi_company_name'];
                    }
                    if ( ! empty( $settings_arr['pi_company_address'] ) ) {
                        $supplier_pi['company_address'] = (string) $settings_arr['pi_company_address'];
                    }
                    if ( ! empty( $settings_arr['pi_company_phone'] ) ) {
                        $supplier_pi['company_phone'] = (string) $settings_arr['pi_company_phone'];
                    }
                    if ( ! empty( $settings_arr['pi_company_email'] ) ) {
                        $supplier_pi['company_email'] = (string) $settings_arr['pi_company_email'];
                    }
                    if ( ! empty( $settings_arr['pi_contact_name'] ) ) {
                        $supplier_pi['contact_name'] = (string) $settings_arr['pi_contact_name'];
                    }
                    if ( ! empty( $settings_arr['pi_bank_details'] ) ) {
                        $supplier_pi['bank_details'] = (string) $settings_arr['pi_bank_details'];
                    }
                    if ( ! empty( $settings_arr['pi_payment_terms'] ) ) {
                        $supplier_pi['payment_terms'] = (string) $settings_arr['pi_payment_terms'];
                    }
                }
            }
        }

        $buyer_profile = function_exists( 'sop_get_company_profile' ) ? sop_get_company_profile() : array();

        $order_date    = isset( $po_payload['order_date'] ) ? $po_payload['order_date'] : '';
        $load_date     = isset( $po_payload['load_date'] ) ? $po_payload['load_date'] : '';
        $arrival_date  = isset( $po_payload['arrival_date'] ) ? $po_payload['arrival_date'] : '';
        $holiday_start = isset( $po_payload['holiday_start'] ) ? $po_payload['holiday_start'] : '';
        $holiday_end   = isset( $po_payload['holiday_end'] ) ? $po_payload['holiday_end'] : '';

        $payment_terms = '';
        if ( ! empty( $sheet_header['header_payment_terms_owner'] ) ) {
            $payment_terms = (string) $sheet_header['header_payment_terms_owner'];
        } elseif ( ! empty( $supplier_pi['payment_terms'] ) ) {
            $payment_terms = (string) $supplier_pi['payment_terms'];
        }

        $base_total = 0.0;
        foreach ( $line_rows as $line ) {
            $qty        = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : ( isset( $line['qty'] ) ? (float) $line['qty'] : 0 );
            $cost_rmb   = isset( $line['cost_rmb'] ) ? (float) $line['cost_rmb'] : ( isset( $line['cost'] ) ? (float) $line['cost'] : 0.0 );
            $line_total = isset( $line['line_total_rmb'] ) ? (float) $line['line_total_rmb'] : ( isset( $line['line_total'] ) ? (float) $line['line_total'] : ( $qty * $cost_rmb ) );
            $base_total += $line_total;
        }

        $extras_total = 0.0;
        $extras_rows  = array();
        if ( isset( $po_payload['po_extras'] ) && is_array( $po_payload['po_extras'] ) ) {
            foreach ( $po_payload['po_extras'] as $extra ) {
                $label  = isset( $extra['label'] ) ? $extra['label'] : '';
                $amount = isset( $extra['amount_rmb'] ) ? (float) $extra['amount_rmb'] : 0.0;
                if ( '' !== $label || 0.0 !== $amount ) {
                    $extras_rows[] = array(
                        'label'  => $label,
                        'amount' => $amount,
                    );
                    $extras_total += $amount;
                }
            }
        }

        $total_with_extras = $base_total + $extras_total;

        $deposit_display = '';
        $balance_display = '';
        if ( 'RMB' === $supplier_currency ) {
            $deposit_usd   = isset( $po_payload['deposit_usd'] ) ? (float) $po_payload['deposit_usd'] : 0.0;
            $deposit_fx    = isset( $po_payload['deposit_fx_rate'] ) ? (float) $po_payload['deposit_fx_rate'] : 0.0;
            $deposit_rmb   = isset( $po_payload['deposit_rmb'] ) ? (float) $po_payload['deposit_rmb'] : 0.0;
            if ( $deposit_rmb <= 0 && $deposit_usd > 0 && $deposit_fx > 0 ) {
                $deposit_rmb = $deposit_usd * $deposit_fx;
            }
            $balance_rmb = isset( $po_payload['balance_rmb'] ) ? (float) $po_payload['balance_rmb'] : ( $total_with_extras - $deposit_rmb );
            if ( $balance_rmb < 0 ) {
                $balance_rmb = 0.0;
            }
            $deposit_display = sprintf(
                /* translators: 1: deposit USD, 2: FX rate RMB per USD, 3: deposit RMB */
                __( 'Deposit: %1$.2f USD @ %2$.3f = %3$.2f RMB', 'sop' ),
                $deposit_usd,
                $deposit_fx,
                $deposit_rmb
            );
            $balance_display = sprintf(
                __( 'Balance: %1$.2f RMB', 'sop' ),
                $balance_rmb
            );
        } else {
            $deposit_simple = isset( $po_payload['deposit_usd'] ) ? (float) $po_payload['deposit_usd'] : 0.0;
            $balance_simple = $total_with_extras - $deposit_simple;
            if ( $balance_simple < 0 ) {
                $balance_simple = 0.0;
            }
            $deposit_display = sprintf(
                __( 'Deposit: %1$.2f %2$s', 'sop' ),
                $deposit_simple,
                $currency_label
            );
            $balance_display = sprintf(
                __( 'Balance: %1$.2f %2$s', 'sop' ),
                $balance_simple,
                $currency_label
            );
        }

        $html  = '<html><head><meta charset="utf-8" /></head><body>';
        $html .= '<h2 style="margin:0 0 10px 0;">' . esc_html__( 'Purchase Order', 'sop' ) . '</h2>';

        $html .= '<table cellspacing="0" cellpadding="6" style="width:100%; border:1px solid #ccc; margin-bottom:12px;"><tr>';
        $html .= '<td style="width:50%; vertical-align:top;">';
        $html .= '<strong>' . esc_html__( 'Buyer', 'sop' ) . '</strong><br />';
        $html .= esc_html( isset( $buyer_profile['company_name'] ) ? $buyer_profile['company_name'] : '' ) . '<br />';
        $html .= nl2br( esc_html( isset( $buyer_profile['billing_address'] ) ? $buyer_profile['billing_address'] : '' ) ) . '<br />';
        $html .= esc_html( isset( $buyer_profile['email'] ) ? $buyer_profile['email'] : '' ) . '<br />';
        $html .= esc_html( isset( $buyer_profile['phone_landline'] ) ? $buyer_profile['phone_landline'] : '' );
        $html .= '</td>';

        $html .= '<td style="width:50%; vertical-align:top;">';
        $html .= '<strong>' . esc_html__( 'Seller', 'sop' ) . '</strong><br />';
        $html .= esc_html( $supplier_pi['company_name'] ) . '<br />';
        if ( $supplier_pi['company_address'] ) {
            $html .= nl2br( esc_html( $supplier_pi['company_address'] ) ) . '<br />';
        }
        if ( $supplier_pi['company_email'] ) {
            $html .= esc_html( $supplier_pi['company_email'] ) . '<br />';
        }
        if ( $supplier_pi['company_phone'] ) {
            $html .= esc_html( $supplier_pi['company_phone'] ) . '<br />';
        }
        if ( $supplier_pi['contact_name'] ) {
            $html .= esc_html__( 'Contact: ', 'sop' ) . esc_html( $supplier_pi['contact_name'] ) . '<br />';
        }
        if ( $supplier_pi['bank_details'] ) {
            $html .= '<br /><strong>' . esc_html__( 'Bank', 'sop' ) . '</strong><br />';
            $html .= nl2br( esc_html( $supplier_pi['bank_details'] ) );
        }
        $html .= '</td>';
        $html .= '</tr></table>';

        $html .= '<table cellspacing="0" cellpadding="6" style="width:100%; border:1px solid #ccc; margin-bottom:12px;">';
        $html .= '<tr><td style="width:25%;"><strong>' . esc_html__( 'PO #', 'sop' ) . '</strong></td><td>' . esc_html( isset( $sheet_header['id'] ) ? $sheet_header['id'] : '' ) . '</td></tr>';
        $html .= '<tr><td><strong>' . esc_html__( 'Order date', 'sop' ) . '</strong></td><td>' . esc_html( $order_date ) . '</td></tr>';
        $html .= '<tr><td><strong>' . esc_html__( 'Holiday start', 'sop' ) . '</strong></td><td>' . esc_html( $holiday_start ) . '</td></tr>';
        $html .= '<tr><td><strong>' . esc_html__( 'Holiday end', 'sop' ) . '</strong></td><td>' . esc_html( $holiday_end ) . '</td></tr>';
        $html .= '<tr><td><strong>' . esc_html__( 'Load date', 'sop' ) . '</strong></td><td>' . esc_html( $load_date ) . '</td></tr>';
        $html .= '<tr><td><strong>' . esc_html__( 'ETA / Delivery', 'sop' ) . '</strong></td><td>' . esc_html( $arrival_date ) . '</td></tr>';
        if ( $payment_terms ) {
            $html .= '<tr><td><strong>' . esc_html__( 'Payment terms', 'sop' ) . '</strong></td><td>' . nl2br( esc_html( $payment_terms ) ) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '<table cellspacing="0" cellpadding="6" style="width:100%; border-collapse:collapse; border:1px solid #ccc; margin-bottom:12px;">';
        $html .= '<tr style="background:#f0f0f0;">';
        $html .= '<th style="border:1px solid #ccc;">' . esc_html__( 'SKU', 'sop' ) . '</th>';
        $html .= '<th style="border:1px solid #ccc;">' . esc_html__( 'Product', 'sop' ) . '</th>';
        $html .= '<th style="border:1px solid #ccc; text-align:right;">' . esc_html__( 'Qty', 'sop' ) . '</th>';
        $html .= '<th style="border:1px solid #ccc; text-align:right;">' . esc_html__( 'Unit cost', 'sop' ) . ' (' . esc_html( $currency_label ) . ')</th>';
        $html .= '<th style="border:1px solid #ccc; text-align:right;">' . esc_html__( 'Line total', 'sop' ) . ' (' . esc_html( $currency_label ) . ')</th>';
        $html .= '</tr>';

        foreach ( $line_rows as $line ) {
            $sku        = isset( $line['sku'] ) ? $line['sku'] : '';
            $name       = isset( $line['product_name'] ) ? $line['product_name'] : '';
            $qty        = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : ( isset( $line['qty'] ) ? (float) $line['qty'] : 0 );
            $cost_rmb   = isset( $line['cost_rmb'] ) ? (float) $line['cost_rmb'] : ( isset( $line['cost'] ) ? (float) $line['cost'] : 0.0 );
            $line_total = isset( $line['line_total_rmb'] ) ? (float) $line['line_total_rmb'] : ( isset( $line['line_total'] ) ? (float) $line['line_total'] : ( $qty * $cost_rmb ) );

            $html .= '<tr>';
            $html .= '<td style="border:1px solid #ccc;">' . esc_html( $sku ) . '</td>';
            $html .= '<td style="border:1px solid #ccc;">' . esc_html( $name ) . '</td>';
            $html .= '<td style="border:1px solid #ccc; text-align:right;">' . esc_html( number_format( $qty, 2 ) ) . '</td>';
            $html .= '<td style="border:1px solid #ccc; text-align:right;">' . esc_html( number_format( $cost_rmb, 2 ) ) . '</td>';
            $html .= '<td style="border:1px solid #ccc; text-align:right;">' . esc_html( number_format( $line_total, 2 ) ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        $html .= '<table cellspacing="0" cellpadding="6" style="width:100%; border:1px solid #ccc; margin-bottom:12px;">';
        $html .= '<tr><td style="width:50%;"><strong>' . esc_html__( 'Base total', 'sop' ) . '</strong></td><td style="text-align:right;">' . esc_html( number_format( $base_total, 2 ) ) . ' ' . esc_html( $currency_label ) . '</td></tr>';
        if ( ! empty( $extras_rows ) ) {
            foreach ( $extras_rows as $extra_row ) {
                $html .= '<tr><td>' . esc_html( $extra_row['label'] ) . '</td><td style="text-align:right;">' . esc_html( number_format( $extra_row['amount'], 2 ) ) . ' ' . esc_html( $currency_label ) . '</td></tr>';
            }
        }
        $html .= '<tr><td><strong>' . esc_html__( 'Total', 'sop' ) . '</strong></td><td style="text-align:right;"><strong>' . esc_html( number_format( $total_with_extras, 2 ) ) . ' ' . esc_html( $currency_label ) . '</strong></td></tr>';
        $html .= '</table>';

        $html .= '<table cellspacing="0" cellpadding="6" style="width:100%; border:1px solid #ccc;">';
        $html .= '<tr><td style="width:50%;">' . esc_html( $deposit_display ) . '</td><td>' . esc_html( $balance_display ) . '</td></tr>';
        $html .= '</table>';

        $html .= '</body></html>';

        return $html;
    }
}

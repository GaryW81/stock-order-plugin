<?php
/**
 * Stock Order Plugin - Preorder XLSX Exporter (embedded images)
 * File version: 1.0.46
 *
 * Build a real XLSX with embedded images (no external URLs) for pre-order sheets.
 * - Column widths + wrap text + 1.6cm images + preserve SKU spaces.
 * - Increase XLSX row height to ~80px.
 * - Set XLSX data row height to 48pt (~80px).
 * - Harden XML + fix Excel repair + enforce 80px row height.
 * - Fix sheet1.xml structure to stop Excel repair warnings.
 * - Show USD columns only for RMB suppliers; label supplier currency dynamically.
 * - Set XLSX cells vertical align to middle (center).
 * - Force vertical middle-align for all cells + center images with 1px margin.
 * - Use explicit default-centered style index (fix columns still top-aligned).
 * - Revert images to 1.6cm + force vertical middle align via row style.
 * - Set Image column to 80px width.
 * - Center-align Brand column horizontally.
 * - Center-align columns F–N.
 * - Align SKU/notes/carton left; align numeric columns right.
 * - Increase Image column width to target ~80px.
 * - Add Purchase Order (Order Summary) XLSX export mirroring HTML layout.
 * - Align PO XLSX layout to match legacy Order Summary (XLS) exactly.
 * - Enforce PO XLSX row-by-row layout with expanded address lines.
 * - Align PO Buyer/Seller rows to match legacy XLS block offsets.
 * - Hide PO gridlines and confine borders to table area.
 * - Ensure PO rows fill A–E with bordered cells (borders visible on blanks).
 * - Temporary: PO sheet outputs no merges; full A1:E30 bordered grid with uniform borders (buyer block outlines).
 * - PO XML post-pass enforces borders on A1:E30 to prevent missed styles.
 * - PO border enforcement now uses DOM/XPath to fill/create cells and styles reliably.
 * - PO Order Summary XLSX now filled from committed template (no layout generation).
 * - Dynamic PO currency labels (Amount/Total/Deposit/Balance) based on supplier currency.
 * - RMB deposit/balance table matches legacy (header row, USD/FX rows, Terms shifted).
 * - Fix PO template borders in RMB deposit/balance table; force font size 10.
 * - Default row height 15pt and align yellow(center)/green(right) cells in PO template.
 * - Center-align RMB table B/C/D cells; right-align E27.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOP_Preorder_XLSX_Exporter {

    /**
     * Build an XLSX file with embedded images.
     *
     * @param array $sheet_header Sheet header data.
     * @param array $lines        Line rows.
     * @return string|WP_Error    Path to XLSX temp file or error.
     */
    public static function build_xlsx_file( array $sheet_header, array $lines ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'sop_export_zip_missing', __( 'XLSX export requires ZipArchive.', 'sop' ) );
        }

        $tmp_base = wp_tempnam( 'sop-preorder-xlsx' );
        if ( ! $tmp_base ) {
            return new WP_Error( 'sop_export_tmp_failed', __( 'Could not create temp file for XLSX export.', 'sop' ) );
        }
        $xlsx_path = $tmp_base . '.xlsx';
        @rename( $tmp_base, $xlsx_path );

        $zip = new ZipArchive();
        if ( true !== $zip->open( $xlsx_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            return new WP_Error( 'sop_export_zip_open_failed', __( 'Could not open XLSX archive for writing.', 'sop' ) );
        }

        $images             = array();
        $media_files        = array();
        $image_index        = 1;
        $row_index          = 2; // Data rows start at 2 (row 1 is header).
        $img_cx             = 576000; // 1.6cm in EMUs.
        $img_cy             = 576000; // 1.6cm in EMUs.
        $img_margin_emu     = 9525; // 1px in EMUs.
        $supplier_currency  = 'GBP';
        if ( isset( $sheet_header['supplier_id'] ) && function_exists( 'sop_preorder_resolve_supplier_params' ) ) {
            $ctx = sop_preorder_resolve_supplier_params( (int) $sheet_header['supplier_id'] );
            if ( ! empty( $ctx['currency_code'] ) ) {
                $supplier_currency = strtoupper( trim( (string) $ctx['currency_code'] ) );
            }
        }
        $show_usd_column = ( 'RMB' === $supplier_currency );

        // Determine sheet-level FX for USD display: Balance FX (payload) > supplier effective FX > converter helper.
        $sheet_fx_for_usd = 0.0;
        if ( $show_usd_column ) {
            $po_payload = array();
            if ( ! empty( $sheet_header['header_notes_owner'] ) && is_string( $sheet_header['header_notes_owner'] ) ) {
                $decoded = json_decode( $sheet_header['header_notes_owner'], true );
                if ( is_array( $decoded ) ) {
                    $po_payload = $decoded;
                }
            }
            $sheet_balance_fx_rate = isset( $po_payload['balance_fx_rate'] ) ? (float) $po_payload['balance_fx_rate'] : 0.0;
            $sheet_supplier_effective_fx = 0.0;
            if ( isset( $sheet_header['supplier_id'] ) && function_exists( 'sop_get_supplier_effective_usd_to_rmb_rate' ) ) {
                $sheet_supplier_effective_fx = (float) sop_get_supplier_effective_usd_to_rmb_rate( (int) $sheet_header['supplier_id'] );
            }
            if ( $sheet_balance_fx_rate > 0 ) {
                $sheet_fx_for_usd = $sheet_balance_fx_rate;
            } elseif ( $sheet_supplier_effective_fx > 0 ) {
                $sheet_fx_for_usd = $sheet_supplier_effective_fx;
            }
        }

        $sheet_rows_xml = '';
        $columns = array(
            'Image',
            'SKU',
            'Brand',
            'Product name',
            'Categories',
            'MOQ',
            'Qty',
            'Unit price (' . $supplier_currency . ')',
        );
        if ( $show_usd_column ) {
            $columns[] = 'Unit price (USD)';
        }
        $columns[] = 'Total (' . $supplier_currency . ')';
        $columns[] = 'Product notes';
        $columns[] = 'Order notes';
        $columns[] = 'Carton no.';
        $columns[] = 'cm3 per unit';
        $columns[] = 'Line CBM';

        // Header row.
        $sheet_rows_xml .= self::build_row_xml( 1, array_map( 'esc_html', $columns ), true, array(), $row_index - 2 );

        foreach ( $lines as $line ) {
            $product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
            $sku_to_output = isset( $line['sku'] ) ? (string) $line['sku'] : '';

            $brand       = isset( $line['brand'] ) ? $line['brand'] : '';
            $name        = isset( $line['product_name'] ) ? $line['product_name'] : '';
            $categories  = isset( $line['categories'] ) ? $line['categories'] : '';
            $moq         = isset( $line['moq'] ) ? (float) $line['moq'] : 0;
            $qty         = isset( $line['qty'] ) ? (float) $line['qty'] : 0;
            $cost_rmb    = isset( $line['cost_rmb'] ) ? (float) $line['cost_rmb'] : 0;
            $cost_usd    = '';
            if ( $show_usd_column ) {
                if ( $cost_rmb > 0 && $sheet_fx_for_usd > 0 ) {
                    $cost_usd = $cost_rmb / $sheet_fx_for_usd;
                } elseif ( $cost_rmb > 0 && function_exists( 'sop_convert_rmb_unit_cost_to_usd' ) ) {
                    $converted = sop_convert_rmb_unit_cost_to_usd( $cost_rmb );
                    if ( $converted > 0 ) {
                        $cost_usd = $converted;
                    }
                }
            }
            $line_total_rmb = $qty * $cost_rmb;
            $product_notes  = isset( $line['product_notes'] ) ? $line['product_notes'] : '';
            $order_notes    = isset( $line['order_notes'] ) ? $line['order_notes'] : '';
            $carton_number  = isset( $line['carton_no'] ) ? $line['carton_no'] : '';
            $cm3_per_unit   = isset( $line['cm3_per_unit'] ) ? $line['cm3_per_unit'] : '';
            $line_cbm       = isset( $line['line_cbm'] ) ? $line['line_cbm'] : '';

            $row_cells = array(
                '', // Image placeholder.
                $sku_to_output,
                $brand,
                $name,
                $categories,
                self::format_number_cell( $moq ),
                self::format_number_cell( $qty ),
                self::format_number_cell( $cost_rmb, 4 ),
                self::format_number_cell( $line_total_rmb, 4 ),
                $product_notes,
                $order_notes,
                $carton_number,
                self::format_number_cell( $cm3_per_unit, 4 ),
                self::format_number_cell( $line_cbm, 6 ),
            );
            if ( $show_usd_column ) {
                array_splice( $row_cells, 8, 0, array( self::format_number_cell( $cost_usd, 4 ) ) );
            }

            $row_styles = array(
                null, // Image placeholder.
                6,    // SKU left align.
                5,    // Brand center.
                2,    // Product name wrap (centered vertically).
                2,    // Categories wrap (centered vertically).
                7,    // MOQ right.
                7,    // Qty right.
                7,    // Unit price (supplier currency) right.
                7,    // Total (supplier currency) right.
                6,    // Product notes left.
                6,    // Order notes left.
                6,    // Carton no. left.
                7,    // cm3 per unit right.
                7,    // Line CBM right.
            );
            if ( $show_usd_column ) {
                array_splice( $row_styles, 8, 0, array( 7 ) ); // Unit price USD right.
            }

            $sheet_rows_xml .= self::build_row_xml( $row_index, $row_cells, false, $row_styles );

            // Handle image embedding.
            $image_id = 0;
            if ( isset( $line['image_id'] ) ) {
                $image_id = (int) $line['image_id'];
            } elseif ( $product_id ) {
                $image_id = get_post_thumbnail_id( $product_id );
            }

            $image_path = self::resolve_image_path( $image_id );
            if ( $image_path ) {
                $media_name = 'image' . $image_index;
                $ext        = strtolower( pathinfo( $image_path, PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, array( 'png', 'jpg', 'jpeg' ), true ) ) {
                    $ext = 'png';
                }
                $media_filename = $media_name . '.' . $ext;
                $media_files[]  = array(
                    'path'     => $image_path,
                    'zip_path' => 'xl/media/' . $media_filename,
                    'ext'      => $ext,
                );
                $images[] = array(
                    'rel_id'  => 'rId' . $image_index,
                    'row'     => $row_index - 1, // zero-index for anchor.
                    'col'     => 0, // Image column A.
                    'cx'      => $img_cx,
                    'cy'      => $img_cy,
                    'col_off' => $img_margin_emu,
                    'row_off' => $img_margin_emu,
                    'ext'     => $ext,
                );
                $image_index++;
            }

            $row_index++;
        }

        // Build XML parts.
        $content_types = self::build_content_types_xml( ! empty( $images ) );
        $rels_root     = self::build_root_rels_xml();
        $workbook      = self::build_workbook_xml();
        $workbook_rels = self::build_workbook_rels_xml();
        $styles        = self::build_styles_xml();
        $sheet_rels    = self::build_sheet_rels_xml( ! empty( $images ) );
        $max_row       = $row_index - 1;
        $sheet_xml     = self::build_sheet_xml( $sheet_rows_xml, ! empty( $images ), $max_row, $show_usd_column, count( $columns ) );
        $drawing_xml   = ! empty( $images ) ? self::build_drawing_xml( $images ) : '';
        $drawing_rels  = ! empty( $images ) ? self::build_drawing_rels_xml( $images ) : '';
        $app_xml       = self::build_app_xml();
        $core_xml      = self::build_core_xml();

        // Add files to ZIP.
        if ( 0 !== strpos( $sheet_xml, '<?xml' ) ) {
            return new WP_Error( 'sop_xlsx_sheet_invalid', __( 'Generated sheet XML invalid.', 'sop' ) );
        }

        $zip->addFromString( '[Content_Types].xml', $content_types );
        $zip->addFromString( '_rels/.rels', $rels_root );
        $zip->addFromString( 'docProps/app.xml', $app_xml );
        $zip->addFromString( 'docProps/core.xml', $core_xml );
        $zip->addFromString( 'xl/workbook.xml', $workbook );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
        $zip->addFromString( 'xl/styles.xml', $styles );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
        $zip->addFromString( 'xl/worksheets/_rels/sheet1.xml.rels', $sheet_rels );

        if ( ! empty( $images ) ) {
            $zip->addFromString( 'xl/drawings/drawing1.xml', $drawing_xml );
            $zip->addFromString( 'xl/drawings/_rels/drawing1.xml.rels', $drawing_rels );
        }

        foreach ( $media_files as $media_file ) {
            $zip->addFile( $media_file['path'], $media_file['zip_path'] );
        }

        $zip->close();

        // Cleanup converted temp images (non-original paths).
        foreach ( $media_files as $media_file ) {
            if ( strpos( $media_file['path'], sys_get_temp_dir() ) !== false ) {
                @unlink( $media_file['path'] );
            }
        }

        return $xlsx_path;
    }

    /**
     * Build a Purchase Order / Order Summary XLSX from a committed template.
     *
     * @param array $sheet_header Sheet header data.
     * @param array $line_rows    Line rows.
     * @return string|WP_Error    Path to XLSX temp file or error.
     */
    public static function build_purchase_order_xlsx_from_template( array $sheet_header, array $line_rows ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'sop_export_zip_missing', __( 'XLSX export requires ZipArchive.', 'sop' ) );
        }

        $template_path = trailingslashit( SOP_PLUGIN_DIR ) . 'includes/templates/purchase-order-summary-template.xlsx';
        if ( ! file_exists( $template_path ) || ! is_readable( $template_path ) ) {
            return new WP_Error( 'sop_po_template_missing', __( 'Purchase Order XLSX template is missing or unreadable.', 'sop' ) );
        }

        $tmp_base = wp_tempnam( 'sop-po-template' );
        if ( ! $tmp_base ) {
            return new WP_Error( 'sop_export_tmp_failed', __( 'Could not create temp file for PO XLSX export.', 'sop' ) );
        }
        $xlsx_path = $tmp_base . '.xlsx';
        @rename( $tmp_base, $xlsx_path );

        if ( ! copy( $template_path, $xlsx_path ) ) {
            return new WP_Error( 'sop_po_template_copy_failed', __( 'Could not copy PO XLSX template.', 'sop' ) );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $xlsx_path ) ) {
            return new WP_Error( 'sop_export_zip_open_failed', __( 'Could not open XLSX archive for writing.', 'sop' ) );
        }

        $sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
        if ( false === $sheet_xml ) {
            $zip->close();
            return new WP_Error( 'sop_po_template_sheet_missing', __( 'PO template worksheet is missing.', 'sop' ) );
        }

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
        $currency_label    = self::sop_po_normalize_currency_code( $supplier_currency );

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
        $payment_terms = self::po_normalize_multiline_block( $payment_terms );

        $format_amount = function( $value, $allow_blank = false ) {
            if ( '' === $value || null === $value ) {
                return $allow_blank ? '' : number_format( 0, 2, '.', '' );
            }
            $num = (float) $value;
            if ( $allow_blank && $num <= 0 ) {
                return '';
            }
            return number_format( $num, 2, '.', '' );
        };

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

        $sku_keys  = array();
        $pcs_total = 0.0;
        foreach ( $line_rows as $line ) {
            $qty = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : ( isset( $line['qty'] ) ? (float) $line['qty'] : 0 );
            if ( $qty <= 0 ) {
                continue;
            }
            $pcs_total += $qty;
            if ( isset( $line['sku'] ) && '' !== $line['sku'] ) {
                $sku_keys[ $line['sku'] ] = true;
            }
        }
        $sku_count = count( $sku_keys );

        $deposit_usd    = isset( $po_payload['deposit_usd'] ) ? (float) $po_payload['deposit_usd'] : 0.0;
        $deposit_rmb    = isset( $po_payload['deposit_rmb'] ) ? (float) $po_payload['deposit_rmb'] : 0.0;
        $deposit_fx     = isset( $po_payload['deposit_fx_rate'] ) ? (float) $po_payload['deposit_fx_rate'] : 0.0;
        $balance_usd    = isset( $po_payload['balance_usd'] ) ? (float) $po_payload['balance_usd'] : 0.0;
        $balance_fx     = isset( $po_payload['balance_fx_rate'] ) ? (float) $po_payload['balance_fx_rate'] : 0.0;

        if ( $deposit_rmb <= 0 && $deposit_usd > 0 && $deposit_fx > 0 ) {
            $deposit_rmb = $deposit_usd * $deposit_fx;
        }
        $balance_rmb = isset( $po_payload['balance_rmb'] ) ? (float) $po_payload['balance_rmb'] : 0.0;
        if ( $balance_rmb <= 0 ) {
            $balance_rmb = $total_with_extras - $deposit_rmb;
            if ( $balance_rmb < 0 ) {
                $balance_rmb = 0.0;
            }
        }
        if ( $balance_usd <= 0 && $balance_rmb > 0 && $balance_fx > 0 ) {
            $balance_usd = $balance_rmb / $balance_fx;
        }

        $summary_label = sprintf(
            /* translators: 1: PO number, 2: SKU count, 3: total pieces */
            __( 'Purchase order #%1$s - %2$d SKUs / %3$.0f pcs', 'sop' ),
            isset( $sheet_header['id'] ) ? $sheet_header['id'] : '',
            (int) $sku_count,
            $pcs_total
        );

        $buyer_company     = isset( $buyer_profile['company_name'] ) ? $buyer_profile['company_name'] : '';
        $billing_block     = self::po_normalize_multiline_block( isset( $buyer_profile['billing_address'] ) ? $buyer_profile['billing_address'] : '' );
        $buyer_email       = isset( $buyer_profile['email'] ) ? $buyer_profile['email'] : '';
        $buyer_phone       = isset( $buyer_profile['phone_landline'] ) ? $buyer_profile['phone_landline'] : '';

        $shipping_addr = '';
        if ( ! empty( $buyer_profile['shipping_address'] ) ) {
            $shipping_addr = $buyer_profile['shipping_address'];
        } elseif ( ! empty( $buyer_profile['billing_address'] ) ) {
            $shipping_addr = $buyer_profile['billing_address'];
        }
        $shipping_block = self::po_normalize_multiline_block( $shipping_addr );

        $seller_company  = $supplier_pi['company_name'];
        $seller_address  = isset( $supplier_pi['company_address'] ) ? $supplier_pi['company_address'] : '';
        $seller_address_block = self::po_normalize_multiline_block( $seller_address );
        $seller_email    = ! empty( $supplier_pi['company_email'] ) ? $supplier_pi['company_email'] : __( 'TBC', 'sop' );
        $seller_phone    = ! empty( $supplier_pi['company_phone'] ) ? $supplier_pi['company_phone'] : __( 'TBC', 'sop' );
        $contact_line    = $supplier_pi['contact_name'] ? sprintf( '%s %s', __( 'Contact:', 'sop' ), $supplier_pi['contact_name'] ) : __( 'Contact:', 'sop' );
        $bank_block      = self::po_normalize_multiline_block( isset( $supplier_pi['bank_details'] ) ? $supplier_pi['bank_details'] : '' );
        $bank_line       = $bank_block ? sprintf( '%s %s', __( 'Bank:', 'sop' ), $bank_block ) : __( 'Bank:', 'sop' );

        $safe_order    = $order_date ? self::format_po_date_display( $order_date ) : '';
        $safe_hol_from = $holiday_start ? self::format_po_date_display( $holiday_start ) : '';
        $safe_hol_to   = $holiday_end ? self::format_po_date_display( $holiday_end ) : '';
        $safe_load     = $load_date ? self::format_po_date_display( $load_date ) : '';
        $safe_eta      = $arrival_date ? self::format_po_date_display( $arrival_date ) : '';

        // Load sheet XML into DOM for reliable cell updates.
        $doc                     = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = false;
        if ( ! @$doc->loadXML( $sheet_xml, LIBXML_NOERROR | LIBXML_NOWARNING ) ) {
            $zip->close();
            return new WP_Error( 'sop_po_xml_load_failed', __( 'Failed to parse PO template sheet XML.', 'sop' ) );
        }
        $xpath = new DOMXPath( $doc );
        $xpath->registerNamespace( 's', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );

        // Ensure dimension covers A1:E31 (terms row may move for RMB).
        $dimension = $xpath->query( '/s:worksheet/s:dimension' )->item( 0 );
        if ( $dimension ) {
            $dimension->setAttribute( 'ref', 'A1:E31' );
        }

        // Ensure sheetData exists.
        $sheet_data = $xpath->query( '/s:worksheet/s:sheetData' )->item( 0 );
        if ( ! $sheet_data ) {
            $worksheet = $xpath->query( '/s:worksheet' )->item( 0 );
            $sheet_data = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheetData' );
            $worksheet->appendChild( $sheet_data );
        }

        // Ensure default row height is 15 and raise any smaller custom heights to 15.
        $sheet_format = $xpath->query( '/s:worksheet/s:sheetFormatPr' )->item( 0 );
        if ( ! $sheet_format ) {
            $sheet_format = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheetFormatPr' );
            $worksheet    = $xpath->query( '/s:worksheet' )->item( 0 );
            if ( $worksheet && $worksheet->firstChild ) {
                $worksheet->insertBefore( $sheet_format, $worksheet->firstChild );
            } elseif ( $worksheet ) {
                $worksheet->appendChild( $sheet_format );
            }
        }
        if ( $sheet_format ) {
            $sheet_format->setAttribute( 'defaultRowHeight', '15' );
            $sheet_format->setAttribute( 'customHeight', '1' );
        }

        $row_nodes = $xpath->query( '/s:worksheet/s:sheetData/s:row' );
        if ( $row_nodes ) {
            foreach ( $row_nodes as $row_node ) {
                if ( $row_node->hasAttribute( 'ht' ) ) {
                    $ht = (float) $row_node->getAttribute( 'ht' );
                    if ( $ht > 0 && $ht < 15 ) {
                        $row_node->setAttribute( 'ht', '15' );
                        $row_node->setAttribute( 'customHeight', '1' );
                    }
                }
            }
        }

        // Helper to set cells.
        $get_style = function( $cell_ref ) use ( $xpath ) {
            return self::po_template_get_style_index( $xpath, $cell_ref );
        };

        $style_a4 = $get_style( 'A4' );
        if ( null === $style_a4 ) {
            $style_a4 = $get_style( 'A3' );
        }
        if ( null === $style_a4 ) {
            $style_a4 = '1';
        }

        // Derive Terms base style (A30) and prepare a wrapped clone with preserved borders.
        $style_a30 = $get_style( 'A30' );
        if ( null === $style_a30 ) {
            $style_a30 = '2';
        }
        $style_terms_wrapped = $style_a30;
        $styles_path         = 'xl/styles.xml';
        if ( false !== $zip->locateName( $styles_path ) ) {
            $styles_xml = $zip->getFromName( $styles_path );
            if ( false !== $styles_xml ) {
                $styles_doc                     = new DOMDocument();
                $styles_doc->preserveWhiteSpace = false;
                $styles_doc->formatOutput       = false;
                if ( @$styles_doc->loadXML( $styles_xml, LIBXML_NOERROR | LIBXML_NOWARNING ) ) {
                    $sxp = new DOMXPath( $styles_doc );
                    $sxp->registerNamespace( 's', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );

                    // Force all font sizes to 10pt.
                    $sz_nodes = $sxp->query( '/s:styleSheet/s:fonts/s:font/s:sz' );
                    if ( $sz_nodes ) {
                        foreach ( $sz_nodes as $sz ) {
                            $sz->setAttribute( 'val', '10' );
                        }
                    }
                    // Add missing sz nodes with 10 if absent.
                    $font_nodes = $sxp->query( '/s:styleSheet/s:fonts/s:font' );
                    if ( $font_nodes ) {
                        foreach ( $font_nodes as $font_node ) {
                            $has_sz = false;
                            foreach ( $font_node->childNodes as $child ) {
                                if ( 'sz' === $child->nodeName ) {
                                    $has_sz = true;
                                    break;
                                }
                            }
                            if ( ! $has_sz ) {
                                $new_sz = $styles_doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sz' );
                                $new_sz->setAttribute( 'val', '10' );
                                $font_node->insertBefore( $new_sz, $font_node->firstChild );
                            }
                        }
                    }

                    // Detect yellow/green fills.
                    $yellow_fill_ids = array();
                    $green_fill_ids  = array();
                    $fills           = $sxp->query( '/s:styleSheet/s:fills/s:fill' );
                    if ( $fills && $fills->length ) {
                        foreach ( $fills as $idx => $fill_node ) {
                            $fg = $sxp->query( './/s:patternFill[@patternType="solid"]/s:fgColor[@rgb]', $fill_node )->item( 0 );
                            if ( ! $fg || ! $fg->hasAttribute( 'rgb' ) ) {
                                continue;
                            }
                            $rgb = strtoupper( $fg->getAttribute( 'rgb' ) );
                            if ( strlen( $rgb ) === 8 ) {
                                $rgb = substr( $rgb, 2 );
                            }
                            if ( strlen( $rgb ) !== 6 ) {
                                continue;
                            }
                            $r = hexdec( substr( $rgb, 0, 2 ) );
                            $g = hexdec( substr( $rgb, 2, 2 ) );
                            $b = hexdec( substr( $rgb, 4, 2 ) );
                            if ( ( $r > 200 && $g > 200 && $b < 140 ) || in_array( $rgb, array( 'FFFF00', 'FFEB9C', 'FFF2CC' ), true ) ) {
                                $yellow_fill_ids[] = $idx;
                            } elseif ( ( $g > 160 && $r < 170 && $b < 170 ) || in_array( $rgb, array( '92D050', 'A9D08E', 'C6EFCE' ), true ) ) {
                                $green_fill_ids[] = $idx;
                            }
                        }
                    }

                    $cell_xfs = $sxp->query( '/s:styleSheet/s:cellXfs' )->item( 0 );
                    $xf_nodes = $sxp->query( '/s:styleSheet/s:cellXfs/s:xf' );
                    $clone_cache = array();
                    $clone_xf_with_horizontal = function( $base_idx, $horizontal ) use ( &$clone_cache, $xf_nodes, $cell_xfs, $styles_doc ) {
                        if ( ! $cell_xfs || ! $xf_nodes || $xf_nodes->length === 0 ) {
                            return $base_idx;
                        }
                        $key = $base_idx . '|' . $horizontal;
                        if ( isset( $clone_cache[ $key ] ) ) {
                            return $clone_cache[ $key ];
                        }
                        $base_idx = (int) $base_idx;
                        if ( $base_idx < 0 || $base_idx >= $xf_nodes->length ) {
                            $base_idx = 0;
                        }
                        $base_xf = $xf_nodes->item( $base_idx );
                        if ( ! $base_xf ) {
                            return $base_idx;
                        }
                        $new_xf = $base_xf->cloneNode( true );
                        $new_xf->setAttribute( 'applyAlignment', '1' );
                        $alignment = null;
                        foreach ( $new_xf->childNodes as $child ) {
                            if ( $child->nodeName === 'alignment' ) {
                                $alignment = $child;
                                break;
                            }
                        }
                        if ( ! $alignment ) {
                            $alignment = $styles_doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'alignment' );
                            $new_xf->appendChild( $alignment );
                        }
                        $alignment->setAttribute( 'horizontal', $horizontal );
                        $cell_xfs->appendChild( $new_xf );
                        $new_count = $xf_nodes->length + 1;
                        $cell_xfs->setAttribute( 'count', (string) $new_count );
                        $clone_cache[ $key ] = $new_count - 1;
                        return $clone_cache[ $key ];
                    };
                    if ( $cell_xfs && $xf_nodes && $xf_nodes->length > 0 ) {
                        // Apply alignment tweaks for yellow/green fills.
                        if ( ! empty( $yellow_fill_ids ) || ! empty( $green_fill_ids ) ) {
                            foreach ( $xf_nodes as $xf_node ) {
                                $fill_id = (int) $xf_node->getAttribute( 'fillId' );
                                $align   = null;
                                foreach ( $xf_node->childNodes as $child ) {
                                    if ( 'alignment' === $child->nodeName ) {
                                        $align = $child;
                                        break;
                                    }
                                }
                                if ( ! $align ) {
                                    $align = $styles_doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'alignment' );
                                    $xf_node->appendChild( $align );
                                }
                                $xf_node->setAttribute( 'applyAlignment', '1' );
                                if ( in_array( $fill_id, $yellow_fill_ids, true ) ) {
                                    $align->setAttribute( 'horizontal', 'center' );
                                } elseif ( in_array( $fill_id, $green_fill_ids, true ) ) {
                                    $align->setAttribute( 'horizontal', 'right' );
                                }
                            }
                        }

                        $base_idx = (int) $style_a30;
                        if ( $base_idx < 0 || $base_idx >= $xf_nodes->length ) {
                            $base_idx = 0;
                        }
                        $base_xf = $xf_nodes->item( $base_idx );
                        if ( $base_xf ) {
                            $new_xf = $base_xf->cloneNode( true );
                            $new_xf->setAttribute( 'applyAlignment', '1' );
                            // Ensure alignment child exists and has wrapText/horizontal/vertical.
                            $alignment = null;
                            foreach ( $new_xf->childNodes as $child ) {
                                if ( $child->nodeName === 'alignment' ) {
                                    $alignment = $child;
                                    break;
                                }
                            }
                            if ( ! $alignment ) {
                                $alignment = $styles_doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'alignment' );
                                $new_xf->appendChild( $alignment );
                            }
                            $alignment->setAttribute( 'wrapText', '1' );
                            if ( ! $alignment->hasAttribute( 'horizontal' ) ) {
                                $alignment->setAttribute( 'horizontal', 'left' );
                            }
                            if ( ! $alignment->hasAttribute( 'vertical' ) ) {
                                $alignment->setAttribute( 'vertical', 'top' );
                            }

                            $cell_xfs->appendChild( $new_xf );
                            $new_count = $xf_nodes->length + 1;
                            $cell_xfs->setAttribute( 'count', (string) $new_count );
                            $new_styles_xml    = $styles_doc->saveXML();
                            $style_terms_wrapped = (string) ( $new_count - 1 );
                            if ( false !== $new_styles_xml ) {
                                $zip->addFromString( $styles_path, $new_styles_xml );
                            } else {
                                $style_terms_wrapped = $style_a30;
                            }
                        }
                    }
                }
            }
        }

        $set_inline = function( $cell_ref, $text, $style_override = '' ) use ( $doc, $xpath ) {
            return self::po_template_set_inline_cell( $doc, $xpath, $cell_ref, $text, $style_override );
        };
        $set_number = function( $cell_ref, $number, $style_override = '' ) use ( $doc, $xpath, $format_amount ) {
            $formatted = $format_amount( $number );
            return self::po_template_set_number_cell( $doc, $xpath, $cell_ref, $formatted, $style_override );
        };

        $result = $set_inline( 'A3', $buyer_company );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'A4', $billing_block );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'A9', $buyer_email );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'A10', $buyer_phone );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'A12', $shipping_block, $style_a4 );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

        $result = $set_inline( 'C3', $seller_company );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'C4', $seller_address_block, $style_a4 );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'C9', $seller_email );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'C10', $seller_phone );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'C11', $contact_line );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'C12', $bank_line, $style_a4 );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

        $result = $set_inline( 'B19', isset( $sheet_header['id'] ) ? $sheet_header['id'] : '' );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'E19', $safe_order );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'B20', $safe_hol_from );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'E20', $safe_hol_to );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'B21', $safe_load );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'E21', $safe_eta );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

        $result = $set_inline( 'A24', $summary_label );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_number( 'E24', $base_total );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_number( 'E25', $total_with_extras );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

        if ( 'RMB' === $currency_label ) {
            // Remove conflicting merges and adjust terms positioning.
            $merge_nodes = $xpath->query( '/s:worksheet/s:mergeCells/s:mergeCell' );
            $to_remove   = array( 'A27:D27', 'A28:D28', 'A29:E29' );
            $removed     = 0;
            if ( $merge_nodes && $merge_nodes->length ) {
                foreach ( $merge_nodes as $merge_node ) {
                    $ref = $merge_node->getAttribute( 'ref' );
                    if ( in_array( $ref, $to_remove, true ) ) {
                        $merge_node->parentNode->removeChild( $merge_node );
                        $removed++;
                    }
                }
            }

            // Ensure merge A31:E31 exists.
            $merge_root = $xpath->query( '/s:worksheet/s:mergeCells' )->item( 0 );
            if ( $merge_root ) {
                $existing_merges = $xpath->query( 's:mergeCell[@ref="A31:E31"]', $merge_root );
                if ( 0 === $existing_merges->length ) {
                    $new_merge = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'mergeCell' );
                    $new_merge->setAttribute( 'ref', 'A31:E31' );
                    $merge_root->appendChild( $new_merge );
                }
                $remaining = $xpath->query( 's:mergeCell', $merge_root );
                $merge_root->setAttribute( 'count', (string) ( $remaining ? $remaining->length : 0 ) );
            }

            // Prepare FX display helpers.
            $format_fx = function( $val ) {
                if ( $val <= 0 ) {
                    return '';
                }
                return number_format( (float) $val, 3, '.', '' );
            };

            // Header row (27) and styles.
            $style_a27 = $get_style( 'A27' );
            $style_e27 = $get_style( 'E27' );
            $style_a28 = $get_style( 'A28' );
            $style_e28 = $get_style( 'E28' );
            if ( null === $style_a27 ) { $style_a27 = $style_a4; }
            if ( null === $style_e27 ) { $style_e27 = $style_a4; }
            if ( null === $style_a28 ) { $style_a28 = $style_a27; }
            if ( null === $style_e28 ) { $style_e28 = $style_e27; }
            $style_a27 = $style_a27 ? $style_a27 : '1';
            $style_e27 = $style_e27 ? $style_e27 : '1';
            $style_a28 = $style_a28 ? $style_a28 : '1';
            $style_e28 = $style_e28 ? $style_e28 : '1';

            $style_center_common = $clone_xf_with_horizontal( $style_a27, 'center' );
            $style_center_header = $style_center_common;
            $style_center_label  = $style_center_common;
            $style_center_value  = $style_center_common;
            $style_right_e27     = $clone_xf_with_horizontal( $style_e27, 'right' );

            $set_inline( 'A27', __( 'Payment', 'sop' ), $style_a27 );
            $set_inline( 'B27', __( 'Value', 'sop' ), $style_center_header );
            $set_inline( 'C27', __( 'Deposit FX (RMB/USD)', 'sop' ), $style_center_header );
            $set_inline( 'D27', __( 'Value', 'sop' ), $style_center_header );
            $set_inline( 'E27', __( 'Deposit (RMB)', 'sop' ), $style_right_e27 );

            // Deposit row (28).
            $fx_display = ( $deposit_fx > 0 ) ? $format_fx( $deposit_fx ) : '';
            $set_inline( 'A28', __( 'Deposit (USD)', 'sop' ), $style_a28 );
            $set_number( 'B28', $deposit_usd, $style_center_value );
            $set_inline( 'C28', $deposit_fx > 0 ? sprintf( __( '1 USD = %s RMB', 'sop' ), $fx_display ) : '', $style_center_label );
            $set_inline( 'D28', $fx_display, $style_center_value );
            $result = $set_number( 'E28', $deposit_rmb, $style_e27 );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

            // Balance row (29).
            $balance_fx_display = ( $balance_fx > 0 ) ? $format_fx( $balance_fx ) : '';
            $set_inline( 'A29', __( 'Balance (USD)', 'sop' ), $style_a28 );
            $set_number( 'B29', $balance_usd > 0 ? $balance_usd : '', $style_center_value );
            if ( $balance_fx > 0 ) {
                $set_inline( 'C29', sprintf( __( '1 USD = %s RMB', 'sop' ), $balance_fx_display ), $style_center_label );
                $set_inline( 'D29', $balance_fx_display, $style_center_value );
            } else {
                $set_inline( 'C29', '', $style_center_label );
                $set_inline( 'D29', '', $style_center_value );
            }
            $result = $set_number( 'E29', $balance_rmb, $style_e28 );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

            // Terms shift for RMB: row 30 header, row 31 content.
            $set_inline( 'A30', __( 'Terms', 'sop' ), $style_a27 );
            $result = $set_inline( 'A31', $payment_terms, $style_terms_wrapped );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

            // Adjust row height for Terms content (row 31).
            $terms_for_height = rtrim( (string) $payment_terms, "\r\n" );
            $line_count       = '' === $terms_for_height ? 1 : ( substr_count( $terms_for_height, "\n" ) + 1 );
            $per_line         = 15;
            $height           = ( $line_count * $per_line ) + 2;
            if ( $height < 15 ) {
                $height = 15;
            } elseif ( $height > 240 ) {
                $height = 240;
            }
            $row31 = $xpath->query( '/s:worksheet/s:sheetData/s:row[@r="31"]' )->item( 0 );
            if ( ! $row31 ) {
                self::po_template_get_or_create_cell( $doc, $xpath, 'A31', $style_terms_wrapped );
                $row31 = $xpath->query( '/s:worksheet/s:sheetData/s:row[@r="31"]' )->item( 0 );
            }
            if ( $row31 ) {
                $row31->setAttribute( 'ht', (string) $height );
                $row31->setAttribute( 'customHeight', '1' );
            }
        } else {
            // Non-RMB template values: deposit and balance in supplier currency.
            $deposit_simple = $deposit_usd;
            $balance_simple = $total_with_extras - $deposit_simple;
            if ( $balance_simple < 0 ) {
                $balance_simple = 0.0;
            }
            $result = $set_number( 'E27', $deposit_simple );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            $result = $set_number( 'E28', $balance_simple );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        }

        if ( 'RMB' !== $currency_label ) {
            $result = $set_inline( 'A30', $payment_terms, $style_terms_wrapped );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

            // Adjust row height for Terms (row 30) based on line count so all lines are visible.
            $terms_for_height = rtrim( (string) $payment_terms, "\r\n" );
            $line_count       = '' === $terms_for_height ? 1 : ( substr_count( $terms_for_height, "\n" ) + 1 );
            $per_line         = 15;
            $height           = ( $line_count * $per_line ) + 2;
            if ( $height < 15 ) {
                $height = 15;
            } elseif ( $height > 240 ) {
                $height = 240;
            }
            $row30 = $xpath->query( '/s:worksheet/s:sheetData/s:row[@r="30"]' )->item( 0 );
            if ( ! $row30 ) {
                self::po_template_get_or_create_cell( $doc, $xpath, 'A30', $style_a4 );
                $row30 = $xpath->query( '/s:worksheet/s:sheetData/s:row[@r="30"]' )->item( 0 );
            }
            if ( $row30 ) {
                $row30->setAttribute( 'ht', (string) $height );
                $row30->setAttribute( 'customHeight', '1' );
            }
        }

        $new_sheet_xml = $doc->saveXML();
        if ( false === $new_sheet_xml ) {
            $zip->close();
            return new WP_Error( 'sop_po_xml_save_failed', __( 'Could not build PO sheet XML.', 'sop' ) );
        }

        $cur_code = self::sop_po_normalize_currency_code( $supplier_currency );

        // Update sharedStrings if present.
        $shared_path = 'xl/sharedStrings.xml';
        if ( false !== $zip->locateName( $shared_path ) ) {
            $shared_strings = $zip->getFromName( $shared_path );
            if ( false !== $shared_strings ) {
                $replace_dep_bal = ( 'RMB' !== $cur_code );
                $updated_shared = self::sop_po_replace_currency_labels_in_xml( $shared_strings, $cur_code, $replace_dep_bal );
                if ( null !== $updated_shared ) {
                    $zip->addFromString( $shared_path, $updated_shared );
                }
            }
        }

        $replace_dep_bal_sheet = ( 'RMB' !== $cur_code );
        $new_sheet_xml = self::sop_po_replace_currency_labels_in_xml( $new_sheet_xml, $cur_code, $replace_dep_bal_sheet );
        if ( false === $new_sheet_xml || null === $new_sheet_xml ) {
            $zip->close();
            return new WP_Error( 'sop_po_xml_save_failed', __( 'Could not build PO sheet XML.', 'sop' ) );
        }

        // Write back and close.
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $new_sheet_xml );
        $zip->close();

        return $xlsx_path;
    }

    /**
     * Build a Purchase Order / Order Summary XLSX (no images) mirroring the HTML layout.
     *
     * @param array $sheet_header Sheet header data.
     * @param array $line_rows    Line rows.
     * @return string|WP_Error    Path to XLSX temp file or error.
     */
    public static function build_purchase_order_xlsx_file( array $sheet_header, array $line_rows ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'sop_export_zip_missing', __( 'XLSX export requires ZipArchive.', 'sop' ) );
        }

        $tmp_base = wp_tempnam( 'sop-po-xlsx' );
        if ( ! $tmp_base ) {
            return new WP_Error( 'sop_export_tmp_failed', __( 'Could not create temp file for PO XLSX export.', 'sop' ) );
        }
        $xlsx_path = $tmp_base . '.xlsx';
        @rename( $tmp_base, $xlsx_path );

        $zip = new ZipArchive();
        if ( true !== $zip->open( $xlsx_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            return new WP_Error( 'sop_export_zip_open_failed', __( 'Could not open XLSX archive for writing.', 'sop' ) );
        }

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

        $format_amount = function( $value, $allow_blank = false ) {
            if ( '' === $value || null === $value ) {
                return $allow_blank ? '' : number_format( 0, 2, '.', '' );
            }
            $num = (float) $value;
            if ( $allow_blank && $num <= 0 ) {
                return '';
            }
            return number_format( $num, 2, '.', '' );
        };

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

        $sku_keys  = array();
        $pcs_total = 0.0;
        foreach ( $line_rows as $line ) {
            $qty = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : ( isset( $line['qty'] ) ? (float) $line['qty'] : 0 );
            if ( $qty <= 0 ) {
                continue;
            }
            $key = '';
            if ( isset( $line['product_id'] ) && $line['product_id'] ) {
                $key = 'p-' . (int) $line['product_id'];
            } elseif ( isset( $line['sku'] ) ) {
                $key = 's-' . $line['sku'];
            }
            if ( $key ) {
                $sku_keys[ $key ] = true;
            }
            $pcs_total += $qty;
        }
        $sku_count = count( $sku_keys );

        $deposit_usd    = isset( $po_payload['deposit_usd'] ) ? (float) $po_payload['deposit_usd'] : 0.0;
        $deposit_fx     = isset( $po_payload['deposit_fx_rate'] ) ? (float) $po_payload['deposit_fx_rate'] : 0.0;
        $deposit_rmb    = isset( $po_payload['deposit_rmb'] ) ? (float) $po_payload['deposit_rmb'] : 0.0;
        $balance_usd    = isset( $po_payload['balance_usd'] ) ? (float) $po_payload['balance_usd'] : 0.0;
        $balance_fx     = isset( $po_payload['balance_fx_rate'] ) ? (float) $po_payload['balance_fx_rate'] : 0.0;
        $balance_rmb    = isset( $po_payload['balance_rmb'] ) ? (float) $po_payload['balance_rmb'] : 0.0;

        if ( $deposit_rmb <= 0 && $deposit_usd > 0 && $deposit_fx > 0 ) {
            $deposit_rmb = $deposit_usd * $deposit_fx;
        }
        if ( $balance_rmb <= 0 ) {
            $balance_rmb = $total_with_extras - $deposit_rmb;
            if ( $balance_rmb < 0 ) {
                $balance_rmb = 0.0;
            }
        }
        if ( $balance_usd <= 0 && $balance_rmb > 0 && $balance_fx > 0 ) {
            $balance_usd = $balance_rmb / $balance_fx;
        }

        $summary_label = sprintf(
            /* translators: 1: PO number, 2: SKU count, 3: total pieces */
            __( 'Purchase order #%1$s - %2$d SKUs / %3$.0f pcs', 'sop' ),
            isset( $sheet_header['id'] ) ? $sheet_header['id'] : '',
            (int) $sku_count,
            $pcs_total
        );

        $buyer_company = isset( $buyer_profile['company_name'] ) ? $buyer_profile['company_name'] : '';
        $billing_lines = self::po_expand_block_lines( isset( $buyer_profile['billing_address'] ) ? $buyer_profile['billing_address'] : '' );
        $buyer_email   = isset( $buyer_profile['email'] ) ? $buyer_profile['email'] : '';
        $buyer_phone   = isset( $buyer_profile['phone_landline'] ) ? $buyer_profile['phone_landline'] : '';
        $shipping_label = __( 'Shipping address:', 'sop' );
        $shipping_addr  = '';
        if ( ! empty( $buyer_profile['shipping_address'] ) ) {
            $shipping_addr = $buyer_profile['shipping_address'];
        } elseif ( ! empty( $buyer_profile['billing_address'] ) ) {
            $shipping_addr = $buyer_profile['billing_address'];
        }
        $shipping_lines = self::po_expand_block_lines( $shipping_addr );

        $seller_company = $supplier_pi['company_name'];
        $seller_address = isset( $supplier_pi['company_address'] ) ? $supplier_pi['company_address'] : '';
        $seller_email   = isset( $supplier_pi['company_email'] ) ? $supplier_pi['company_email'] : '';
        $seller_phone   = isset( $supplier_pi['company_phone'] ) ? $supplier_pi['company_phone'] : '';
        $seller_contact = $supplier_pi['contact_name'] ? sprintf( '%s %s', __( 'Contact:', 'sop' ), $supplier_pi['contact_name'] ) : '';
        $seller_bank    = $supplier_pi['bank_details'] ? sprintf( '%s %s', __( 'Bank:', 'sop' ), $supplier_pi['bank_details'] ) : '';

        $rows_xml     = '';
        $merge_cells  = array();
        $row_num      = 1;

        // Dedicated bordered grid style index for PO sheet (see build_styles_xml_purchase_order cellXfs index 12).
        $po_grid_style = 12;

        // Title.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 5, 'v' => __( 'Purchase Order', 'sop' ), 's' => 1 ),
            ),
            $merge_cells,
            16,
            null,
            $po_grid_style
        );

        // Buyer/Seller headers.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 2, 'v' => __( 'Buyer', 'sop' ), 's' => 2 ),
                array( 'col' => 2, 'span' => 3, 'v' => __( 'Seller', 'sop' ), 's' => 2 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        // Company row.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 2, 'v' => $buyer_company, 's' => 0 ),
                array( 'col' => 2, 'span' => 3, 'v' => $seller_company, 's' => 0 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        // Billing block rows (per-row merges, no vertical merge).
        $seller_address_lines = self::po_expand_block_lines( $seller_address );
        $billing_max          = max( count( $billing_lines ), count( $seller_address_lines ) );
        $billing_max          = max( 1, $billing_max );
        for ( $i = 0; $i < $billing_max; $i++ ) {
            $buyer_val  = isset( $billing_lines[ $i ] ) ? $billing_lines[ $i ] : '';
            $seller_val = isset( $seller_address_lines[ $i ] ) ? $seller_address_lines[ $i ] : '';
            $rows_xml  .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 2, 'v' => $buyer_val, 's' => 0 ),
                    array( 'col' => 2, 'span' => 3, 'v' => $seller_val, 's' => 9 ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );
        }

        // Email row.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 2, 'v' => $buyer_email, 's' => 0 ),
                array( 'col' => 2, 'span' => 3, 'v' => $seller_email, 's' => 0 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        // Phone row.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 2, 'v' => $buyer_phone, 's' => 0 ),
                array( 'col' => 2, 'span' => 3, 'v' => $seller_phone, 's' => 0 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        // Shipping label / contact row.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 2, 'v' => $shipping_label, 's' => 0 ),
                array( 'col' => 2, 'span' => 3, 'v' => $seller_contact, 's' => 0 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        // Shipping block rows (per-row merges).
        $seller_bank_lines = self::po_expand_block_lines( $seller_bank );
        $ship_max          = max( count( $shipping_lines ), count( $seller_bank_lines ) );
        $ship_max          = max( 1, $ship_max );
        for ( $i = 0; $i < $ship_max; $i++ ) {
            $buyer_val  = isset( $shipping_lines[ $i ] ) ? $shipping_lines[ $i ] : '';
            $seller_val = isset( $seller_bank_lines[ $i ] ) ? $seller_bank_lines[ $i ] : '';
            $rows_xml  .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 2, 'v' => $buyer_val, 's' => 0 ),
                    array( 'col' => 2, 'span' => 3, 'v' => $seller_val, 's' => 9 ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );
        }

        // PO Details heading.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 5, 'v' => __( 'PO Details', 'sop' ), 's' => 2 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        $po_number     = isset( $sheet_header['id'] ) ? $sheet_header['id'] : '';
        $safe_order    = $order_date ? self::format_po_date_display( $order_date ) : '';
        $safe_hol_from = $holiday_start ? self::format_po_date_display( $holiday_start ) : '';
        $safe_hol_to   = $holiday_end ? self::format_po_date_display( $holiday_end ) : '';
        $safe_load     = $load_date ? self::format_po_date_display( $load_date ) : '';
        $safe_eta      = $arrival_date ? self::format_po_date_display( $arrival_date ) : '';

        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 1, 'v' => __( 'PO #', 'sop' ), 's' => 1 ),
                array( 'col' => 1, 'span' => 1, 'v' => $po_number, 's' => 0 ),
                array( 'col' => 2, 'span' => 2, 'v' => __( 'Order date', 'sop' ), 's' => 1 ),
                array( 'col' => 4, 'span' => 1, 'v' => $safe_order, 's' => 0 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 1, 'v' => __( 'Holiday start', 'sop' ), 's' => 1 ),
                array( 'col' => 1, 'span' => 1, 'v' => $safe_hol_from, 's' => 0 ),
                array( 'col' => 2, 'span' => 2, 'v' => __( 'Holiday end', 'sop' ), 's' => 1 ),
                array( 'col' => 4, 'span' => 1, 'v' => $safe_hol_to, 's' => 0 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 1, 'v' => __( 'Load date', 'sop' ), 's' => 1 ),
                array( 'col' => 1, 'span' => 1, 'v' => $safe_load, 's' => 0 ),
                array( 'col' => 2, 'span' => 2, 'v' => __( 'ETA / Delivery', 'sop' ), 's' => 1 ),
                array( 'col' => 4, 'span' => 1, 'v' => $safe_eta, 's' => 0 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        // Purchase order values heading + header.
        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 5, 'v' => __( 'Purchase order values', 'sop' ), 's' => 2 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 4, 'v' => __( 'Description', 'sop' ), 's' => 3 ),
                array( 'col' => 4, 'span' => 1, 'v' => sprintf( __( 'Amount (%s)', 'sop' ), $currency_label ), 's' => 8 ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 4, 'v' => $summary_label, 's' => 0 ),
                array( 'col' => 4, 'span' => 1, 'v' => $format_amount( $base_total ), 's' => 4, 'type' => 'num' ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        if ( ! empty( $extras_rows ) ) {
            foreach ( $extras_rows as $extra_row ) {
                $rows_xml .= self::po_row_from_specs(
                    $row_num++,
                    array(
                        array( 'col' => 0, 'span' => 4, 'v' => $extra_row['label'], 's' => 0 ),
                        array( 'col' => 4, 'span' => 1, 'v' => $format_amount( $extra_row['amount'] ), 's' => 4, 'type' => 'num' ),
                    ),
                    $merge_cells,
                    null,
                    $po_grid_style
                );
            }
        }

        $rows_xml .= self::po_row_from_specs(
            $row_num++,
            array(
                array( 'col' => 0, 'span' => 4, 'v' => sprintf( __( 'Total (%s)', 'sop' ), $currency_label ), 's' => 11 ),
                array( 'col' => 4, 'span' => 1, 'v' => $format_amount( $total_with_extras ), 's' => 5, 'type' => 'num' ),
            ),
            $merge_cells,
            null,
            $po_grid_style
        );

        // Deposit / Balance block.
        if ( 'RMB' === $supplier_currency ) {
            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 5, 'v' => __( 'Deposit / Balance', 'sop' ), 's' => 10 ),
                ),
                $merge_cells,
                15,
                null,
                $po_grid_style
            );

            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 1, 'v' => __( 'Payment', 'sop' ), 's' => 7 ),
                    array( 'col' => 1, 'span' => 1, 'v' => __( 'Value', 'sop' ), 's' => 7 ),
                    array( 'col' => 2, 'span' => 1, 'v' => __( 'Deposit FX (RMB/USD)', 'sop' ), 's' => 7 ),
                    array( 'col' => 3, 'span' => 1, 'v' => __( 'Value', 'sop' ), 's' => 7 ),
                    array( 'col' => 4, 'span' => 1, 'v' => __( 'Deposit (RMB)', 'sop' ), 's' => 8 ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );

            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 1, 'v' => __( 'Deposit (USD)', 'sop' ), 's' => 0 ),
                    array( 'col' => 1, 'span' => 1, 'v' => $format_amount( $deposit_usd ), 's' => 4, 'type' => 'num' ),
                    array( 'col' => 2, 'span' => 1, 'v' => $deposit_fx > 0 ? sprintf( __( '1 USD = %s RMB', 'sop' ), number_format( $deposit_fx, 3 ) ) : '', 's' => 6 ),
                    array( 'col' => 3, 'span' => 1, 'v' => $deposit_fx > 0 ? number_format( $deposit_fx, 3, '.', '' ) : '', 's' => 6, 'type' => 'str' ),
                    array( 'col' => 4, 'span' => 1, 'v' => $format_amount( $deposit_rmb ), 's' => 4, 'type' => 'num' ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );

            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 1, 'v' => __( 'Balance (USD)', 'sop' ), 's' => 0 ),
                    array( 'col' => 1, 'span' => 1, 'v' => $balance_usd > 0 ? $format_amount( $balance_usd ) : '', 's' => 4, 'type' => 'num' ),
                    array( 'col' => 2, 'span' => 1, 'v' => $balance_fx > 0 ? sprintf( __( '1 USD = %s RMB', 'sop' ), number_format( $balance_fx, 3 ) ) : '', 's' => 6 ),
                    array( 'col' => 3, 'span' => 1, 'v' => $balance_fx > 0 ? number_format( $balance_fx, 3, '.', '' ) : '', 's' => 6, 'type' => 'str' ),
                    array( 'col' => 4, 'span' => 1, 'v' => $format_amount( $balance_rmb ), 's' => 4, 'type' => 'num' ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );
        } else {
            $deposit_simple = $deposit_usd;
            $balance_simple = $total_with_extras - $deposit_simple;
            if ( $balance_simple < 0 ) {
                $balance_simple = 0.0;
            }

            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 5, 'v' => __( 'Deposit / Balance', 'sop' ), 's' => 2 ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );

            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 4, 'v' => sprintf( __( 'Deposit (%s)', 'sop' ), $currency_label ), 's' => 0 ),
                    array( 'col' => 4, 'span' => 1, 'v' => $format_amount( $deposit_simple ), 's' => 4, 'type' => 'num' ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );
            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 4, 'v' => sprintf( __( 'Balance (%s)', 'sop' ), $currency_label ), 's' => 0 ),
                    array( 'col' => 4, 'span' => 1, 'v' => $format_amount( $balance_simple ), 's' => 4, 'type' => 'num' ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );
        }

        if ( $payment_terms ) {
            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 5, 'v' => __( 'Terms', 'sop' ), 's' => 2 ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );
            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(
                    array( 'col' => 0, 'span' => 5, 'v' => $payment_terms, 's' => 9 ),
                ),
                $merge_cells,
                null,
                $po_grid_style
            );
        }

        // Pad out to row 30 with bordered blank rows.
        while ( $row_num <= 30 ) {
            $rows_xml .= self::po_row_from_specs(
                $row_num++,
                array(),
                $merge_cells,
                null,
                $po_grid_style
            );
        }

        // Ignore merges for this build (no mergeCells output).
        $merge_cells = array();

        $max_row = $row_num - 1;

        $content_types = self::build_content_types_xml( false );
        $rels_root     = self::build_root_rels_xml();
        $workbook      = self::build_purchase_order_workbook_xml();
        $workbook_rels = self::build_workbook_rels_xml();
        $styles        = self::build_styles_xml_purchase_order();
        $sheet_rels    = self::build_sheet_rels_xml( false );
        $sheet_xml     = self::build_purchase_order_sheet_xml( $rows_xml, $merge_cells, $max_row );

        // Enforce bordered grid style across A1:E30 as a final safety pass using DOM/XPath.
        $sheet_xml = self::po_dom_force_borders_grid( $sheet_xml, $po_grid_style, 'A', 'E', 1, 30 );
        if ( is_wp_error( $sheet_xml ) ) {
            wp_die( esc_html( $sheet_xml->get_error_message() ) );
        }
        $app_xml       = self::build_app_xml_for_title( 'Purchase Order' );
        $core_xml      = self::build_core_xml();

        if ( 0 !== strpos( $sheet_xml, '<?xml' ) ) {
            return new WP_Error( 'sop_xlsx_sheet_invalid', __( 'Generated PO sheet XML invalid.', 'sop' ) );
        }

        $zip->addFromString( '[Content_Types].xml', $content_types );
        $zip->addFromString( '_rels/.rels', $rels_root );
        $zip->addFromString( 'docProps/app.xml', $app_xml );
        $zip->addFromString( 'docProps/core.xml', $core_xml );
        $zip->addFromString( 'xl/workbook.xml', $workbook );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
        $zip->addFromString( 'xl/styles.xml', $styles );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
        $zip->addFromString( 'xl/worksheets/_rels/sheet1.xml.rels', $sheet_rels );

        $zip->close();

        return $xlsx_path;
    }

    private static function esc_xml( $value ) {
        return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
    }

    private static function sanitize_xml_text( $value ) {
        $value = (string) $value;
        $value = str_replace( array( "\r\n", "\r" ), "\n", $value );
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value );
        $value = htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
        return str_replace( "\n", '&#10;', $value );
    }

    private static function sanitize_po_text( $value ) {
        $value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return self::sanitize_xml_text( $value );
    }

    private static function sanitize_po_inline_text_preserve_newlines( $value ) {
        $value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $value = str_replace( array( "\r\n", "\r" ), "\n", $value );
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value );
        // Do not replace newlines with entities; keep literal newlines and escape XML.
        return htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
    }

    private static function po_expand_lines( $text ) {
        $text = (string) $text;
        if ( '' === trim( $text ) ) {
            return array();
        }

        // Normalise <br> to newlines.
        $text = str_ireplace( array( '<br>', '<br/>', '<br />' ), "\n", $text );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
        $parts = explode( "\n", $text );
        $lines = array();
        foreach ( $parts as $part ) {
            $line = trim( $part );
            if ( '' !== $line ) {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    private static function po_expand_block_lines( $text ) {
        $lines = self::po_expand_lines( $text );
        if ( empty( $lines ) ) {
            return array( '' );
        }
        return $lines;
    }

    private static function po_normalize_multiline_block( $text ) {
        $text = (string) $text;
        $text = str_ireplace( array( '<br>', '<br/>', '<br />' ), "\n", $text );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
        $parts = explode( "\n", $text );
        $lines = array();
        foreach ( $parts as $part ) {
            $line = trim( $part );
            if ( '' !== $line ) {
                $lines[] = $line;
            }
        }
        if ( empty( $lines ) ) {
            return '';
        }
        return implode( "\n", $lines );
    }

    private static function po_merge_ref( $col_start, $row_start, $col_end, $row_end ) {
        $cs = self::column_letter( min( $col_start, $col_end ) );
        $ce = self::column_letter( max( $col_start, $col_end ) );
        $rs = (int) min( $row_start, $row_end );
        $re = (int) max( $row_start, $row_end );
        return $cs . $rs . ':' . $ce . $re;
    }

    private static function po_merge_parse( $ref ) {
        if ( ! is_string( $ref ) || strpos( $ref, ':' ) === false ) {
            return null;
        }
        list( $a, $b ) = explode( ':', $ref, 2 );
        $parse = function( $cell ) {
            if ( ! preg_match( '/^([A-Z]+)([0-9]+)$/', $cell, $m ) ) {
                return null;
            }
            $col = 0;
            $letters = str_split( $m[1] );
            foreach ( $letters as $ch ) {
                $col = $col * 26 + ( ord( $ch ) - 64 );
            }
            // zero-index for comparisons.
            return array( 'col' => $col - 1, 'row' => (int) $m[2] );
        };
        $p1 = $parse( $a );
        $p2 = $parse( $b );
        if ( ! $p1 || ! $p2 ) {
            return null;
        }
        return array(
            'c1' => min( $p1['col'], $p2['col'] ),
            'c2' => max( $p1['col'], $p2['col'] ),
            'r1' => min( $p1['row'], $p2['row'] ),
            'r2' => max( $p1['row'], $p2['row'] ),
        );
    }

    private static function po_merge_overlaps( $ref_a, $ref_b ) {
        $a = self::po_merge_parse( $ref_a );
        $b = self::po_merge_parse( $ref_b );
        if ( ! $a || ! $b ) {
            return false;
        }
        $h_overlap = $a['c1'] <= $b['c2'] && $b['c1'] <= $a['c2'];
        $v_overlap = $a['r1'] <= $b['r2'] && $b['r1'] <= $a['r2'];
        return $h_overlap && $v_overlap;
    }

    private static function po_merge_add( array &$merges, $ref ) {
        if ( ! $ref ) {
            return;
        }
        foreach ( $merges as $existing ) {
            if ( $existing === $ref ) {
                return;
            }
            if ( self::po_merge_overlaps( $existing, $ref ) ) {
                return;
            }
        }
        $merges[] = $ref;
    }

    private static function po_merge_sort_unique( array $merges ) {
        $unique = array();
        foreach ( $merges as $ref ) {
            if ( ! in_array( $ref, $unique, true ) ) {
                $unique[] = $ref;
            }
        }
        usort(
            $unique,
            function ( $a, $b ) {
                $pa = self::po_merge_parse( $a );
                $pb = self::po_merge_parse( $b );
                if ( ! $pa || ! $pb ) {
                    return strcmp( $a, $b );
                }
                if ( $pa['r1'] !== $pb['r1'] ) {
                    return $pa['r1'] - $pb['r1'];
                }
                return $pa['c1'] - $pb['c1'];
            }
        );
        return $unique;
    }

    private static function po_clean_merges( array $merges ) {
        $sorted = self::po_merge_sort_unique( $merges );
        $clean  = array();
        foreach ( $sorted as $ref ) {
            self::po_merge_add( $clean, $ref );
        }
        return $clean;
    }

    /**
     * Force a specific style index across a rectangular cell range in the PO worksheet XML.
     *
     * @param string $sheet_xml Sheet XML.
     * @param int    $style_idx Style index to enforce.
     * @param string $min_col   Starting column letter.
     * @param string $max_col   Ending column letter.
     * @param int    $min_row   Starting row number.
     * @param int    $max_row   Ending row number.
     * @return string|WP_Error  Modified XML or error.
     */
    private static function po_dom_force_borders_grid( $sheet_xml, $style_idx, $min_col, $max_col, $min_row, $max_row ) {
        $style_idx   = (int) $style_idx;
        $min_col_idx = ord( strtoupper( $min_col ) ) - 65;
        $max_col_idx = ord( strtoupper( $max_col ) ) - 65;
        if ( $min_col_idx < 0 || $max_col_idx < $min_col_idx ) {
            return new WP_Error( 'sop_po_invalid_range', 'Invalid PO grid enforcement range.' );
        }

        $doc                     = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = false;
        if ( ! @$doc->loadXML( $sheet_xml, LIBXML_NOERROR | LIBXML_NOWARNING ) ) {
            return new WP_Error( 'sop_po_xml_load_failed', 'Failed to parse PO sheet XML for border enforcement.' );
        }

        $xpath = new DOMXPath( $doc );
        $xpath->registerNamespace( 's', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );

        $worksheet = $xpath->query( '/s:worksheet' )->item( 0 );
        if ( ! $worksheet ) {
            return new WP_Error( 'sop_po_missing_worksheet', 'PO sheet XML missing worksheet node.' );
        }

        $sheet_data = $xpath->query( '/s:worksheet/s:sheetData' )->item( 0 );
        if ( ! $sheet_data ) {
            $sheet_data = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheetData' );
            // Insert sheetData before drawing or at end.
            $inserted = false;
            foreach ( array( 'mergeCells', 'pageMargins', 'drawing' ) as $tag ) {
                $target = $xpath->query( '/s:worksheet/s:' . $tag )->item( 0 );
                if ( $target && $target->parentNode === $worksheet ) {
                    $worksheet->insertBefore( $sheet_data, $target );
                    $inserted = true;
                    break;
                }
            }
            if ( ! $inserted ) {
                $worksheet->appendChild( $sheet_data );
            }
        }

        $row_nodes = array();
        foreach ( $xpath->query( 's:row', $sheet_data ) as $row_node ) {
            $r_attr = $row_node->getAttribute( 'r' );
            if ( $r_attr !== '' ) {
                $row_nodes[ (int) $r_attr ] = $row_node;
            }
        }

        $col_letters = array( 'A', 'B', 'C', 'D', 'E' );
        $col_index_map = array( 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4 );

        for ( $row = (int) $min_row; $row <= (int) $max_row; $row++ ) {
            if ( isset( $row_nodes[ $row ] ) ) {
                $row_node = $row_nodes[ $row ];
            } else {
                $row_node = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row' );
                $row_node->setAttribute( 'r', (string) $row );
                // Insert in order.
                $inserted = false;
                foreach ( $row_nodes as $existing_r => $existing_node ) {
                    if ( $existing_r > $row ) {
                        $sheet_data->insertBefore( $row_node, $existing_node );
                        $inserted = true;
                        break;
                    }
                }
                if ( ! $inserted ) {
                    $sheet_data->appendChild( $row_node );
                }
                $row_nodes[ $row ] = $row_node;
                ksort( $row_nodes );
            }

            // Map existing cells by coordinate; assign r if missing based on position.
            $cells_by_coord = array();
            $cells          = $xpath->query( 's:c', $row_node );
            $pos_counter    = 0;
            foreach ( $cells as $cell_node ) {
                $coord = $cell_node->getAttribute( 'r' );
                if ( '' === $coord ) {
                    if ( $pos_counter < count( $col_letters ) ) {
                        $coord = $col_letters[ $pos_counter ] . $row;
                        $cell_node->setAttribute( 'r', $coord );
                    }
                }
                if ( $coord ) {
                    $cells_by_coord[ $coord ] = $cell_node;
                }
                $pos_counter++;
            }

            // Ensure required cells exist and set style.
            foreach ( $col_letters as $col_letter ) {
                $coord = $col_letter . $row;
                if ( isset( $cells_by_coord[ $coord ] ) ) {
                    $cell_node = $cells_by_coord[ $coord ];
                } else {
                    $cell_node = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c' );
                    $cell_node->setAttribute( 'r', $coord );
                    $row_node->appendChild( $cell_node );
                    $cells_by_coord[ $coord ] = $cell_node;
                }
                $cell_node->setAttribute( 's', (string) $style_idx );
            }

            // Reorder cells A->E within the row.
            $children = iterator_to_array( $row_node->childNodes );
            foreach ( $children as $child ) {
                $row_node->removeChild( $child );
            }
            ksort( $cells_by_coord, SORT_NATURAL );
            foreach ( $cells_by_coord as $cell_node ) {
                $row_node->appendChild( $cell_node );
            }
        }

        // Final sanity check via DOM.
        for ( $row = (int) $min_row; $row <= (int) $max_row; $row++ ) {
            foreach ( $col_letters as $col_letter ) {
                $coord = $col_letter . $row;
                $nodes = $xpath->query( '/s:worksheet/s:sheetData/s:row[@r="' . $row . '"]/s:c[@r="' . $coord . '"]' );
                if ( 0 === $nodes->length ) {
                    return new WP_Error( 'sop_po_missing_cell', 'SOP PO XLSX export failed sanity check: missing cell ' . $coord );
                }
                $node = $nodes->item( 0 );
                if ( $node->getAttribute( 's' ) !== (string) $style_idx ) {
                    return new WP_Error( 'sop_po_style_enforce_failed', 'SOP PO XLSX export failed sanity check: missing enforced border style on cell ' . $coord );
                }
            }
        }

        $doc->encoding       = 'UTF-8';
        $doc->xmlStandalone  = true;
        return $doc->saveXML();
    }

    private static function po_column_index_from_letter( $letters ) {
        $letters = strtoupper( $letters );
        $len     = strlen( $letters );
        $num     = 0;
        for ( $i = 0; $i < $len; $i++ ) {
            $num = $num * 26 + ( ord( $letters[ $i ] ) - 64 );
        }
        return $num - 1;
    }

    private static function po_template_get_or_create_cell( DOMDocument $doc, DOMXPath $xpath, $cell_ref, $default_style = '' ) {
        if ( ! preg_match( '/^([A-Z]+)([0-9]+)$/', strtoupper( $cell_ref ), $m ) ) {
            return new WP_Error( 'sop_po_cell_ref_invalid', 'Invalid cell reference ' . $cell_ref );
        }
        $col_letters = $m[1];
        $row_num     = (int) $m[2];
        $col_index   = self::po_column_index_from_letter( $col_letters );

        $worksheet = $xpath->query( '/s:worksheet' )->item( 0 );
        if ( ! $worksheet ) {
            return new WP_Error( 'sop_po_missing_worksheet', 'PO sheet XML missing worksheet node.' );
        }

        $sheet_data = $xpath->query( '/s:worksheet/s:sheetData' )->item( 0 );
        if ( ! $sheet_data ) {
            $sheet_data = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheetData' );
            $worksheet->appendChild( $sheet_data );
        }

        $row_node = $xpath->query( 's:row[@r="' . $row_num . '"]', $sheet_data )->item( 0 );
        if ( ! $row_node ) {
            $row_node = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row' );
            $row_node->setAttribute( 'r', (string) $row_num );
            // Insert in order.
            $inserted = false;
            foreach ( $xpath->query( 's:row', $sheet_data ) as $existing_row ) {
                $existing_r = (int) $existing_row->getAttribute( 'r' );
                if ( $existing_r > $row_num ) {
                    $sheet_data->insertBefore( $row_node, $existing_row );
                    $inserted = true;
                    break;
                }
            }
            if ( ! $inserted ) {
                $sheet_data->appendChild( $row_node );
            }
        }

        $cell_node = $xpath->query( 's:c[@r="' . $col_letters . $row_num . '"]', $row_node )->item( 0 );
        if ( ! $cell_node ) {
            $cell_node = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c' );
            $cell_node->setAttribute( 'r', $col_letters . $row_num );
            if ( '' !== $default_style ) {
                $cell_node->setAttribute( 's', (string) $default_style );
            }
            $row_node->appendChild( $cell_node );
        } elseif ( '' === $cell_node->getAttribute( 's' ) && '' !== $default_style ) {
            $cell_node->setAttribute( 's', (string) $default_style );
        }

        // Reorder cells in the row (A..).
        $cells_in_row = iterator_to_array( $row_node->childNodes );
        while ( $row_node->firstChild ) {
            $row_node->removeChild( $row_node->firstChild );
        }
        usort(
            $cells_in_row,
            function( $a, $b ) {
                $ra = $a->attributes->getNamedItem( 'r' ) ? $a->attributes->getNamedItem( 'r' )->nodeValue : '';
                $rb = $b->attributes->getNamedItem( 'r' ) ? $b->attributes->getNamedItem( 'r' )->nodeValue : '';
                if ( ! preg_match( '/^([A-Z]+)([0-9]+)$/', $ra, $ma ) || ! preg_match( '/^([A-Z]+)([0-9]+)$/', $rb, $mb ) ) {
                    return 0;
                }
                $ia = self::po_column_index_from_letter( $ma[1] );
                $ib = self::po_column_index_from_letter( $mb[1] );
                return $ia - $ib;
            }
        );
        foreach ( $cells_in_row as $c_node ) {
            $row_node->appendChild( $c_node );
        }

        return $cell_node;
    }

    private static function po_template_get_style_index( DOMXPath $xpath, $cell_ref ) {
        $node = $xpath->query( '/s:worksheet/s:sheetData/s:row/s:c[@r="' . $cell_ref . '"]' )->item( 0 );
        if ( $node && $node->hasAttribute( 's' ) ) {
            return $node->getAttribute( 's' );
        }
        return null;
    }

    private static function po_template_set_inline_cell( DOMDocument $doc, DOMXPath $xpath, $cell_ref, $text, $style_override = '' ) {
        $cell = self::po_template_get_or_create_cell( $doc, $xpath, $cell_ref, '' === $style_override ? '1' : $style_override );
        if ( is_wp_error( $cell ) ) {
            return $cell;
        }
        while ( $cell->firstChild ) {
            $cell->removeChild( $cell->firstChild );
        }
        $cell->setAttribute( 't', 'inlineStr' );
        if ( '' !== $style_override ) {
            $cell->setAttribute( 's', (string) $style_override );
        } elseif ( '' === $cell->getAttribute( 's' ) ) {
            $cell->setAttribute( 's', '1' );
        }
        $is = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'is' );
        $clean_text = self::sanitize_po_inline_text_preserve_newlines( $text );
        $t  = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't', $clean_text );
        $t->setAttribute( 'xml:space', 'preserve' );
        $is->appendChild( $t );
        $cell->appendChild( $is );
        return true;
    }

    private static function po_template_set_number_cell( DOMDocument $doc, DOMXPath $xpath, $cell_ref, $number, $style_override = '' ) {
        $cell = self::po_template_get_or_create_cell( $doc, $xpath, $cell_ref, '' === $style_override ? '1' : $style_override );
        if ( is_wp_error( $cell ) ) {
            return $cell;
        }
        while ( $cell->firstChild ) {
            $cell->removeChild( $cell->firstChild );
        }
        if ( $cell->hasAttribute( 't' ) ) {
            $cell->removeAttribute( 't' );
        }
        if ( '' !== $style_override ) {
            $cell->setAttribute( 's', (string) $style_override );
        } elseif ( '' === $cell->getAttribute( 's' ) ) {
            $cell->setAttribute( 's', '1' );
        }
        $v = $doc->createElementNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'v', self::esc_xml( $number ) );
        $cell->appendChild( $v );
        return true;
    }

    private static function sop_po_normalize_currency_code( $currency ) {
        $currency = strtoupper( trim( (string) $currency ) );
        if ( in_array( $currency, array( 'CNY', 'CNH' ), true ) ) {
            return 'RMB';
        }
        if ( in_array( $currency, array( 'RMB', 'GBP', 'USD', 'EUR' ), true ) ) {
            return $currency;
        }
        return 'GBP';
    }

    private static function sop_po_replace_currency_labels_in_xml( $xml, $cur, $replace_deposit_balance = true ) {
        $patterns = array(
            '/Amount \\((GBP|USD|EUR|RMB)\\)/',
            '/Total \\((GBP|USD|EUR|RMB)\\)/',
        );
        $replacements = array(
            'Amount (' . $cur . ')',
            'Total (' . $cur . ')',
        );
        if ( $replace_deposit_balance ) {
            $patterns[]     = '/Deposit \\((GBP|USD|EUR|RMB)\\)/';
            $patterns[]     = '/Balance \\((GBP|USD|EUR|RMB)\\)/';
            $replacements[] = 'Deposit (' . $cur . ')';
            $replacements[] = 'Balance (' . $cur . ')';
        }
        return preg_replace( $patterns, $replacements, $xml );
    }

    private static function po_fill_row_ae( array $specs, $default_style = 0 ) {
        $cells = array();
        for ( $i = 0; $i < 5; $i++ ) {
            $cells[ $i ] = array(
                'col'        => $i,
                'v'          => '',
                's'          => (int) $default_style,
                'colspan'    => 1,
                'type'       => 'str',
                'skip_merge' => true,
            );
        }

        foreach ( $specs as $spec ) {
            $col   = isset( $spec['col'] ) ? max( 0, min( 4, (int) $spec['col'] ) ) : 0;
            $span  = isset( $spec['span'] ) ? max( 1, (int) $spec['span'] ) : 1;
            $style = isset( $spec['s'] ) ? (int) $spec['s'] : (int) $default_style;
            $cells[ $col ] = array(
                'col'        => $col,
                'v'          => isset( $spec['v'] ) ? $spec['v'] : '',
                's'          => $style,
                'colspan'    => $span,
                'type'       => isset( $spec['type'] ) ? $spec['type'] : 'str',
                'skip_merge' => ! empty( $spec['skip_merge'] ),
            );

            for ( $i = 1; $i < $span && ( $col + $i ) < 5; $i++ ) {
                $cells[ $col + $i ] = array(
                    'col'        => $col + $i,
                    'v'          => '',
                    's'          => $style,
                    'colspan'    => 1,
                    'type'       => 'str',
                    'skip_merge' => true,
                );
            }
        }

        ksort( $cells );
        return array_values( $cells );
    }

    private static function po_row_from_specs( $row_num, array $specs, &$merge_cells, $row_height = null, $row_style = null, $force_style = null ) {
        $default_style = ( null === $force_style ) ? 0 : (int) $force_style;
        $cells         = self::po_fill_row_ae( $specs, $default_style );
        if ( null !== $force_style ) {
            foreach ( $cells as &$c ) {
                $c['s'] = (int) $force_style;
            }
            unset( $c );
        }
        return self::build_po_row_xml( $row_num, $cells, $merge_cells, $row_height, $row_style );
    }

    private static function format_po_date_display( $value ) {
        $value = (string) $value;
        if ( '' === trim( $value ) ) {
            return '';
        }

        // If already contains '/' assume it is user-formatted.
        if ( strpos( $value, '/' ) !== false ) {
            return $value;
        }

        // Try to parse common YYYY-MM-DD formats.
        $dt = date_create( $value );
        if ( $dt ) {
            return $dt->format( 'd/m/Y' );
        }

        return $value;
    }

    private static function column_letter( $index ) {
        $index = (int) $index;
        $letter = '';
        while ( $index >= 0 ) {
            $letter = chr( $index % 26 + 65 ) . $letter;
            $index  = floor( $index / 26 ) - 1;
        }
        return $letter;
    }

    private static function build_row_xml( $row_num, $cells, $is_header = false, $styles = array(), $row_offset_for_height = 0 ) {
        $row_style_attr = ' s="4" customFormat="1"';
        $row_height_attr = $is_header ? '' : ' ht="48" customHeight="1"';
        $xml = '<row r="' . (int) $row_num . '"' . $row_style_attr . $row_height_attr . '>';
        $col_index = 0;
        foreach ( $cells as $cell_value ) {
            $col_letter = self::column_letter( $col_index ) . $row_num;
            $style_idx  = isset( $styles[ $col_index ] ) ? $styles[ $col_index ] : null;

            if ( null === $style_idx ) {
                $style_idx = 4;
            } else {
                $style_idx = (int) $style_idx;
            }

            if ( is_numeric( $cell_value ) ) {
                $xml .= '<c r="' . $col_letter . '" s="' . $style_idx . '"><v>' . $cell_value . '</v></c>';
            } else {
                $xml .= '<c r="' . $col_letter . '" t="inlineStr" s="' . $style_idx . '"><is><t xml:space="preserve">' . self::sanitize_xml_text( $cell_value ) . '</t></is></c>';
            }

            $col_index++;
        }
        $xml .= '</row>';
        return $xml;
    }

    private static function build_po_row_xml( $row_num, $cells, &$merge_cells, $row_height = null, $row_style = null ) {
        $attrs = ' r="' . (int) $row_num . '"';
        if ( null !== $row_style ) {
            $attrs .= ' s="' . (int) $row_style . '" customFormat="1"';
        }
        if ( null !== $row_height ) {
            $attrs .= ' ht="' . (float) $row_height . '" customHeight="1"';
        }

        $xml       = '<row' . $attrs . '>';
        $col_index = 0;

        foreach ( $cells as $cell ) {
            $value      = isset( $cell['v'] ) ? $cell['v'] : '';
            $style      = isset( $cell['s'] ) ? (int) $cell['s'] : 0;
            $colspan    = isset( $cell['colspan'] ) ? max( 1, (int) $cell['colspan'] ) : 1;
            $type       = isset( $cell['type'] ) ? $cell['type'] : 'str';
            $skip_merge = ! empty( $cell['skip_merge'] );
            $current_col = $col_index;
            if ( isset( $cell['col'] ) ) {
                $current_col = (int) $cell['col'];
                if ( $current_col < $col_index ) {
                    $col_index = $current_col;
                }
            }
            $col_ref = self::column_letter( $current_col ) . $row_num;
            $end_col = self::column_letter( $current_col + $colspan - 1 );

            if ( $colspan > 1 && ! $skip_merge ) {
                $merge_ref = $col_ref . ':' . $end_col . $row_num;
                self::po_merge_add( $merge_cells, $merge_ref );
            }

            if ( 'num' === $type && '' !== $value && null !== $value ) {
                $xml .= '<c r="' . $col_ref . '" s="' . $style . '"><v>' . self::esc_xml( $value ) . '</v></c>';
            } else {
                $xml .= '<c r="' . $col_ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . self::sanitize_po_text( $value ) . '</t></is></c>';
            }

            $col_index = $current_col + $colspan;
        }

        $xml .= '</row>';
        return $xml;
    }

    private static function format_number_cell( $val, $decimals = 2 ) {
        if ( '' === $val || null === $val ) {
            return '';
        }
        if ( ! is_numeric( $val ) ) {
            return '';
        }
        return round( (float) $val, $decimals );
    }

    private static function resolve_image_path( $image_id ) {
        $image_id = (int) $image_id;
        if ( $image_id <= 0 ) {
            return '';
        }

        $path = get_attached_file( $image_id );
        if ( ! $path || ! file_exists( $path ) ) {
            return '';
        }

        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( in_array( $ext, array( 'png', 'jpg', 'jpeg' ), true ) ) {
            return $path;
        }

        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            return '';
        }

        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            return '';
        }

        $editor->resize( 60, 60, true );
        $tmp_converted = wp_tempnam( 'sop-img' );
        if ( ! $tmp_converted ) {
            return '';
        }
        $save = $editor->save( $tmp_converted, 'image/png' );
        if ( is_wp_error( $save ) ) {
            @unlink( $tmp_converted );
            return '';
        }
        return $tmp_converted;
    }

    private static function build_content_types_xml( $has_images ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Default Extension="jpeg" ContentType="image/jpeg"/>';
        $xml .= '<Default Extension="jpg" ContentType="image/jpeg"/>';
        $xml .= '<Default Extension="png" ContentType="image/png"/>';
        $xml .= '<Override PartName="/_rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $xml .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $xml .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $xml .= '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        if ( $has_images ) {
            $xml .= '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }
        $xml .= '</Types>';
        return $xml;
    }

    private static function build_root_rels_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $xml .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
        $xml .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private static function build_workbook_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        $xml .= '<sheet name="Order Sheet" sheetId="1" r:id="rId1"/>';
        $xml .= '</sheets>';
        $xml .= '</workbook>';
        return $xml;
    }

    private static function build_purchase_order_workbook_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        $xml .= '<sheet name="Purchase Order" sheetId="1" r:id="rId1"/>';
        $xml .= '</sheets>';
        $xml .= '</workbook>';
        return $xml;
    }

    private static function build_workbook_rels_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
        $xml .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $xml . '</Relationships>';
    }

    private static function build_styles_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<fonts count="1"><font/></fonts>';
        $xml .= '<fills count="1"><fill/></fills>';
        $xml .= '<borders count="1"><border/></borders>';
        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $xml .= '<cellXfs count="8">';
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>';
        $xml .= '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="center"/></xf>'; // Text format.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>';
        $xml .= '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'; // Text + wrap.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'; // Default centered (explicit).
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'; // Center horizontal + vertical.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'; // Left align.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'; // Right align.
        $xml .= '</cellXfs>';
        $xml .= '</styleSheet>';
        return $xml;
    }

    private static function build_styles_xml_purchase_order() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<fonts count="2">';
        $xml .= '<font><sz val="11"/><name val="Calibri"/></font>';
        $xml .= '<font><b/><sz val="11"/><name val="Calibri"/></font>';
        $xml .= '</fonts>';
        $xml .= '<fills count="3">';
        $xml .= '<fill><patternFill patternType="none"/></fill>';
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFF5F5F5"/><bgColor indexed="64"/></patternFill></fill>';
        $xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FFF0F0F0"/><bgColor indexed="64"/></patternFill></fill>';
        $xml .= '</fills>';
        $xml .= '<borders count="2">';
        $xml .= '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $xml .= '<border><left style="thin"><color rgb="FFCCCCCC"/></left><right style="thin"><color rgb="FFCCCCCC"/></right><top style="thin"><color rgb="FFCCCCCC"/></top><bottom style="thin"><color rgb="FFCCCCCC"/></bottom><diagonal/></border>';
        $xml .= '</borders>';
        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $xml .= '<cellXfs count="13">';
        // 0: normal left (top).
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="left" vertical="top"/></xf>';
        // 1: bold left (top).
        $xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="left" vertical="top"/></xf>';
        // 2: section header (fill1) bold left.
        $xml .= '<xf numFmtId="0" fontId="1" fillId="1" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>';
        // 3: subheader (fill2) bold left.
        $xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>';
        // 4: amount right (number).
        $xml .= '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>';
        // 5: amount right bold (number).
        $xml .= '<xf numFmtId="4" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>';
        // 6: center.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>';
        // 7: subheader center (fill2) bold.
        $xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>';
        // 8: subheader right (fill2) bold.
        $xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>';
        // 9: wrap left (top).
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>';
        //10: section header center (fill1) bold.
        $xml .= '<xf numFmtId="0" fontId="1" fillId="1" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>';
        //11: right bold (text).
        $xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>';
        //12: PO grid enforced border style.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="left" vertical="top"/></xf>';
        $xml .= '</cellXfs>';
        $xml .= '</styleSheet>';
        return $xml;
    }

    private static function build_sheet_rels_xml( $has_drawing ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        if ( $has_drawing ) {
            $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>';
        }
        $xml .= '</Relationships>';
        return $xml;
    }

    private static function build_sheet_xml( $rows_xml, $has_drawing, $max_row, $show_usd_column = true, $column_count = 0 ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $last_col_index  = $column_count > 0 ? ( $column_count - 1 ) : ( $show_usd_column ? 14 : 13 );
        $last_col_letter = self::column_letter( $last_col_index );
        $xml .= '<dimension ref="A1:' . $last_col_letter . (int) $max_row . '"/>';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="48" customHeight="1"/>';
        $xml .= self::build_cols_xml( $show_usd_column );
        $xml .= '<sheetData>' . $rows_xml . '</sheetData>';
        if ( $has_drawing ) {
            $xml .= '<drawing r:id="rId1"/>';
        }
        $xml .= '</worksheet>';
        return $xml;
    }

    private static function build_purchase_order_sheet_xml( $rows_xml, $merge_cells, $max_row ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<dimension ref="A1:E30"/>';
        $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15" customHeight="1"/>';
        $xml .= self::build_purchase_order_cols_xml();
        $xml .= '<sheetData>' . $rows_xml . '</sheetData>';

        $xml .= '</worksheet>';
        return $xml;
    }

    private static function build_cols_xml( $show_usd_column = true ) {
        $xml  = '<cols>';
        $xml .= '<col min="1" max="1" width="8.94" customWidth="1"/>'; // Image (A).
        $xml .= '<col min="2" max="2" width="10.34" customWidth="1"/>'; // SKU (B).
        $xml .= '<col min="4" max="4" width="32.60" customWidth="1"/>'; // Product name (D).
        $xml .= '<col min="5" max="5" width="32.60" customWidth="1"/>'; // Categories (E).
        $product_notes_col = $show_usd_column ? 11 : 10;
        $xml .= '<col min="' . (int) $product_notes_col . '" max="' . (int) $product_notes_col . '" width="27.15" customWidth="1"/>'; // Product notes.
        $xml .= '</cols>';
        return $xml;
    }

    private static function build_purchase_order_cols_xml() {
        $xml  = '<cols>';
        $xml .= '<col min="1" max="1" width="18.61" customWidth="1"/>';
        $xml .= '<col min="2" max="2" width="18.61" customWidth="1"/>';
        $xml .= '<col min="3" max="3" width="17.36" customWidth="1"/>';
        $xml .= '<col min="4" max="4" width="13.44" customWidth="1"/>';
        $xml .= '<col min="5" max="5" width="13.44" customWidth="1"/>';
        $xml .= '</cols>';
        return $xml;
    }

    private static function build_drawing_xml( $images ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $idx = 0;
        foreach ( $images as $img ) {
            $idx++;
            $xml .= '<xdr:oneCellAnchor>';
            $col_off = isset( $img['col_off'] ) ? (int) $img['col_off'] : 0;
            $row_off = isset( $img['row_off'] ) ? (int) $img['row_off'] : 0;
            $xml .= '<xdr:from><xdr:col>' . (int) $img['col'] . '</xdr:col><xdr:colOff>' . $col_off . '</xdr:colOff><xdr:row>' . (int) $img['row'] . '</xdr:row><xdr:rowOff>' . $row_off . '</xdr:rowOff></xdr:from>';
            $xml .= '<xdr:ext cx="' . (int) $img['cx'] . '" cy="' . (int) $img['cy'] . '"/>';
            $xml .= '<xdr:pic>';
            $xml .= '<xdr:nvPicPr><xdr:cNvPr id="' . (1000 + $idx) . '" name="Picture ' . $idx . '"/><xdr:cNvPicPr/></xdr:nvPicPr>';
            $xml .= '<xdr:blipFill><a:blip r:embed="' . self::esc_xml( $img['rel_id'] ) . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>';
            $xml .= '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . (int) $img['cx'] . '" cy="' . (int) $img['cy'] . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>';
            $xml .= '</xdr:pic>';
            $xml .= '<xdr:clientData/>';
            $xml .= '</xdr:oneCellAnchor>';
        }
        $xml .= '</xdr:wsDr>';
        return $xml;
    }

    private static function build_drawing_rels_xml( $images ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ( $images as $index => $img ) {
            $ext = isset( $img['ext'] ) ? $img['ext'] : 'png';
            $target = '../media/image' . ( $index + 1 ) . '.' . $ext;
            $xml .= '<Relationship Id="' . self::esc_xml( $img['rel_id'] ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . self::esc_xml( $target ) . '"/>';
        }
        $xml .= '</Relationships>';
        return $xml;
    }

    private static function build_app_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">';
        $xml .= '<Application>Microsoft Excel</Application>';
        $xml .= '<DocSecurity>0</DocSecurity>';
        $xml .= '<ScaleCrop>false</ScaleCrop>';
        $xml .= '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>';
        $xml .= '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Order Sheet</vt:lpstr></vt:vector></TitlesOfParts>';
        $xml .= '<Company></Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0300</AppVersion>';
        $xml .= '</Properties>';
        return $xml;
    }

    private static function build_app_xml_for_title( $title ) {
        $title = $title ? (string) $title : 'Sheet1';
        $xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml  .= '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">';
        $xml  .= '<Application>Microsoft Excel</Application>';
        $xml  .= '<DocSecurity>0</DocSecurity>';
        $xml  .= '<ScaleCrop>false</ScaleCrop>';
        $xml  .= '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>';
        $xml  .= '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . self::esc_xml( $title ) . '</vt:lpstr></vt:vector></TitlesOfParts>';
        $xml  .= '<Company></Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0300</AppVersion>';
        $xml  .= '</Properties>';
        return $xml;
    }

    private static function build_core_xml() {
        $now = gmdate( 'Y-m-d\\TH:i:s\\Z' );
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $xml .= '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>';
        $xml .= '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>';
        $xml .= '<dc:creator>Stock Order Plugin</dc:creator>';
        $xml .= '<cp:lastModifiedBy>Stock Order Plugin</cp:lastModifiedBy>';
        $xml .= '</cp:coreProperties>';
        return $xml;
    }
}

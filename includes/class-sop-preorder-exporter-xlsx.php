<?php
/**
 * Stock Order Plugin - Preorder XLSX Exporter (embedded images)
 * File version: 1.0.57
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
 * - Use committed templates for PO (standard + RMB) without runtime styling hacks.
 * - RMB PO template selection and mapping (including USD/FX deposit/balance rows).
 * - Terms written as a single multiline block into the template cell.
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

        $supplier_id   = isset( $sheet_header['supplier_id'] ) ? (int) $sheet_header['supplier_id'] : 0;
        $supplier_name = isset( $sheet_header['supplier_name'] ) ? $sheet_header['supplier_name'] : '';

        $supplier_params = function_exists( 'sop_preorder_resolve_supplier_params' )
            ? sop_preorder_resolve_supplier_params( $supplier_id )
            : array();

        $supplier_currency = ! empty( $supplier_params['currency_code'] ) ? $supplier_params['currency_code'] : 'GBP';
        $currency_label    = self::sop_po_normalize_currency_code( $supplier_currency );

        $template_filename = ( 'RMB' === $currency_label )
            ? 'purchase-order-summary-rmb-template.xlsx'
            : 'purchase-order-summary-template.xlsx';
        $template_path     = trailingslashit( SOP_PLUGIN_DIR ) . 'includes/templates/' . $template_filename;
        if ( ! file_exists( $template_path ) || ! is_readable( $template_path ) ) {
            return new WP_Error( 'sop_po_template_missing', sprintf( __( 'Purchase Order XLSX template %s is missing or unreadable.', 'sop' ), $template_filename ) );
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
        $balance_fx_locked = isset( $po_payload['balance_fx_locked'] ) ? (int) $po_payload['balance_fx_locked'] : 0;

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

        // Effective FX derived from deposit for balance USD fallback.
        $effective_deposit_fx = $deposit_fx;
        if ( $effective_deposit_fx <= 0 && $deposit_usd > 0 && $deposit_rmb > 0 ) {
            $effective_deposit_fx = $deposit_rmb / $deposit_usd;
        }

        $balance_usd_for_export = $balance_usd;
        if ( $balance_usd_for_export <= 0 && $balance_rmb > 0 && $effective_deposit_fx > 0 ) {
            $balance_usd_for_export = $balance_rmb / $effective_deposit_fx;
        }

        // Effective FX to display on balance row: locked balance FX if provided, otherwise deposit FX fallback.
        $effective_balance_fx_for_export = ( $balance_fx_locked && $balance_fx > 0 ) ? $balance_fx : $effective_deposit_fx;

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

        // Derive Terms base style (A30).
        $style_a30 = $get_style( 'A30' );
        if ( null === $style_a30 ) {
            $style_a30 = '2';
        }
        $style_terms_wrapped = $style_a30;

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
            // RMB template mapping (no structural changes).
            $format_fx = function( $val ) {
                if ( $val <= 0 ) {
                    return '';
                }
                return number_format( (float) $val, 3, '.', '' );
            };

            // Deposit row (template defines layout).
            $fx_display = ( $deposit_fx > 0 ) ? $format_fx( $deposit_fx ) : '';
            $set_number( 'B28', $deposit_usd );
            $set_inline( 'C28', $deposit_fx > 0 ? sprintf( __( '1 USD = %s RMB', 'sop' ), $fx_display ) : '', '' );
            $set_inline( 'D28', $fx_display, '' );
            $result = $set_number( 'E28', $deposit_rmb );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

            // Balance row (template defines layout). Only show USD/FX when FX rate provided.
            $balance_fx_display = ( $effective_balance_fx_for_export > 0 ) ? $format_fx( $effective_balance_fx_for_export ) : '';
            if ( $balance_usd_for_export > 0 ) {
                $set_number( 'B29', $balance_usd_for_export );
            } else {
                $set_inline( 'B29', '', '' );
            }
            if ( $effective_balance_fx_for_export > 0 ) {
                $set_inline( 'C29', sprintf( __( '1 USD = %s RMB', 'sop' ), $balance_fx_display ), '' );
                $style_d29 = $get_style( 'D29' );
                $set_inline( 'D29', $balance_fx_display, $style_d29 );
            } else {
                $style_d29 = $get_style( 'D29' );
                $set_inline( 'C29', '', '' );
                $set_inline( 'D29', '', $style_d29 );
            }
            $result = $set_number( 'E29', $balance_rmb );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
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

        // Terms: single multiline block into one cell (prefer A31 if present, else A30).
        $terms_text  = trim( self::po_normalize_multiline_block( $payment_terms ) );
        $terms_cell  = ( null !== $get_style( 'A31' ) ) ? 'A31' : 'A30';
        $terms_style = $get_style( $terms_cell );
        $set_inline( $terms_cell, $terms_text, $terms_style ? $terms_style : $style_terms_wrapped );

        $new_sheet_xml = $doc->saveXML();
        if ( false === $new_sheet_xml ) {
            $zip->close();
            return new WP_Error( 'sop_po_xml_save_failed', __( 'Could not build PO sheet XML.', 'sop' ) );
        }

        $cur_code = self::sop_po_normalize_currency_code( $supplier_currency );

        if ( 'RMB' !== $cur_code ) {
            // Update sharedStrings if present.
            $shared_path = 'xl/sharedStrings.xml';
            if ( false !== $zip->locateName( $shared_path ) ) {
                $shared_strings = $zip->getFromName( $shared_path );
                if ( false !== $shared_strings ) {
                    $updated_shared = self::sop_po_replace_currency_labels_in_xml( $shared_strings, $cur_code, true );
                    if ( null !== $updated_shared ) {
                        $zip->addFromString( $shared_path, $updated_shared );
                    }
                }
            }

            $new_sheet_xml = self::sop_po_replace_currency_labels_in_xml( $new_sheet_xml, $cur_code, true );
        }
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
    private static function po_template_get_style_index( DOMXPath $xpath, $cell_ref ) {
        $cell_ref = strtoupper( trim( (string) $cell_ref ) );
        if ( '' === $cell_ref ) {
            return null;
        }

        // Use local-name() so it works even if namespace prefixes differ.
        $nodes = $xpath->query( '//*[local-name()="c" and @r="' . $cell_ref . '"]' );
        if ( ! $nodes || $nodes->length < 1 ) {
            return null;
        }

        $cell = $nodes->item( 0 );
        if ( ! $cell || ! $cell->hasAttribute( 's' ) ) {
            return null;
        }

        $style = trim( (string) $cell->getAttribute( 's' ) );
        return ( '' === $style ) ? null : $style;
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
        $cell_ref = strtoupper( (string) $cell_ref );
        if ( ! preg_match( '/^([A-Z]+)([0-9]+)$/', $cell_ref, $m ) ) {
            return new WP_Error( 'sop_po_cell_ref_invalid', 'Invalid cell reference ' . $cell_ref );
        }

        $col_letters = $m[1];
        $row_num     = (int) $m[2];
        $coord       = $col_letters . $row_num;
        $ns          = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        $worksheet = $xpath->query( '/*[local-name()="worksheet"]' )->item( 0 );
        if ( ! $worksheet ) {
            return new WP_Error( 'sop_po_missing_worksheet', 'PO sheet XML missing worksheet node.' );
        }

        $sheet_data = $xpath->query( '/*[local-name()="worksheet"]/*[local-name()="sheetData"]' )->item( 0 );
        if ( ! $sheet_data ) {
            $sheet_data = $doc->createElementNS( $ns, 'sheetData' );
            $worksheet->appendChild( $sheet_data );
        }

        $row_node = $xpath->query( '*[local-name()="row" and @r="' . $row_num . '"]', $sheet_data )->item( 0 );
        if ( ! $row_node ) {
            $row_node = $doc->createElementNS( $ns, 'row' );
            $row_node->setAttribute( 'r', (string) $row_num );

            $inserted = false;
            foreach ( $xpath->query( '*[local-name()="row"]', $sheet_data ) as $existing_row ) {
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

        $cell_node = $xpath->query( '*[local-name()="c" and @r="' . $coord . '"]', $row_node )->item( 0 );
        if ( ! $cell_node ) {
            $cell_node = $doc->createElementNS( $ns, 'c' );
            $cell_node->setAttribute( 'r', $coord );
            if ( '' !== $default_style ) {
                $cell_node->setAttribute( 's', (string) $default_style );
            }
            $row_node->appendChild( $cell_node );
        } elseif ( '' === $cell_node->getAttribute( 's' ) && '' !== $default_style ) {
            $cell_node->setAttribute( 's', (string) $default_style );
        }

        $cells_in_row = array();
        foreach ( $xpath->query( '*[local-name()="c"]', $row_node ) as $c_node ) {
            $cells_in_row[] = $c_node;
        }

        usort(
            $cells_in_row,
            function ( $a, $b ) {
                $ra = $a->getAttribute( 'r' );
                $rb = $b->getAttribute( 'r' );
                if ( ! preg_match( '/^([A-Z]+)([0-9]+)$/', $ra, $ma ) || ! preg_match( '/^([A-Z]+)([0-9]+)$/', $rb, $mb ) ) {
                    return 0;
                }
                $ia = self::po_column_index_from_letter( $ma[1] );
                $ib = self::po_column_index_from_letter( $mb[1] );
                return $ia <=> $ib;
            }
        );

        foreach ( $cells_in_row as $c_node ) {
            if ( $c_node->parentNode === $row_node ) {
                $row_node->removeChild( $c_node );
            }
        }
        foreach ( $cells_in_row as $c_node ) {
            $row_node->appendChild( $c_node );
        }

        return $cell_node;
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

    private static function sanitize_po_inline_text_preserve_newlines( $text ) {
        $text = (string) $text;

        // Preserve intended line breaks.
        $text = str_ireplace( array( '<br>', '<br/>', '<br />' ), "\n", $text );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );

        // Remove characters invalid in XML 1.0 (keep tab/newline).
        $text = preg_replace(
            '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $text
        );

        return $text;
    }

    private static function esc_xml( $text ) {
        $text = (string) $text;

        // Remove invalid XML 1.0 characters (tab/newline retained).
        $text = preg_replace(
            '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $text
        );

        $flags = ENT_QUOTES;
        if ( defined( 'ENT_XML1' ) ) {
            $flags |= ENT_XML1;
        }

        return htmlspecialchars( $text, $flags, 'UTF-8' );
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

    private static function format_number_cell( $val, $decimals = 2 ) {
        if ( '' === $val || null === $val ) {
            return '';
        }
        if ( ! is_numeric( $val ) ) {
            return '';
        }
        return round( (float) $val, $decimals );
    }

    private static function format_po_date_display( $value ) {
        $value = (string) $value;
        if ( '' === trim( $value ) ) {
            return '';
        }

        if ( strpos( $value, '/' ) !== false ) {
            return $value;
        }

        try {
            $ts = strtotime( $value );
            if ( false === $ts ) {
                return $value;
            }
            return gmdate( 'd/m/Y', $ts );
        } catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return $value;
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


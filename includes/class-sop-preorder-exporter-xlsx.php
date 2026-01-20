<?php
/**
 * Stock Order Plugin - Preorder XLSX Exporter (embedded images)
 * File version: 1.0.93
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
 * - Add Goods-In Issues XLSX export (missing/reject lines only).
 * - Align Goods-In Issues export to preorder columns + locked FX credit columns.
 * - Update image sizing (78px in 80px cell), row height, and Goods-In issues columns/widths.
 * - 1.0.93 - Fix: Order Summary XLSX mapping updated for new template rows (extras/total/deposit/terms).
 * - 1.0.92 - Update PO template mapping for 4-column (A-D) layouts.
 * - 1.0.91 - Tweak: Increase SKU column width to 28.
 * - 1.0.90 - Tweak: Widen key MOQ/Qty/price/CBM columns by 10%.
 * - 1.0.89 - Tweak: Order Sheet header height + column widths (MOQ/Qty/prices/CBM fields).
 * - 1.0.88 - Fix: Enable wrapText for multi-line header cells so Excel renders red notes on a new line.
 * - 1.0.87 - Fix: Header red notes now break onto a new line in XLSX (rich text newline handling).
 * - 1.0.86 - Fix: Preserve newlines in rich header text so red notes wrap to next line in Excel.
 * - 1.0.85 - Fix: XLSX styles.xml schema order to prevent Excel repair prompt.
 * - 1.0.84 - XLSX: fix styles.xml to prevent Excel repair prompt.
 * - 1.0.83 - Inline header notes in row 1 for SKU/order/carton; update SKU/carton widths.
 * - 1.0.82 - Slim ID column; add 2-row header notes; force 2dp for unit/total prices.
 * - 1.0.81 - Add Product ID column to Order Sheet XLSX; widen carton column; match order notes width to product notes.
 * - 1.0.80 - Version bump after Goods-In issues XLSX updates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOP_Preorder_XLSX_Exporter {

    /**
     * Ensure required methods exist before exporting.
     *
     * @param array  $methods       Method names to check.
     * @param string $context_label Context for error messaging.
     * @return true|WP_Error
     */
    private static function sop_require_methods( array $methods, $context_label ) {
        $missing = array();
        foreach ( $methods as $method ) {
            if ( ! method_exists( __CLASS__, $method ) || ! is_callable( array( __CLASS__, $method ) ) ) {
                $missing[] = $method;
            }
        }
        if ( empty( $missing ) ) {
            return true;
        }
        return new WP_Error(
            'sop_xlsx_missing_method',
            sprintf(
                /* translators: 1: context label, 2: method list */
                __( '%1$s XLSX export failed. Missing helpers: %2$s', 'sop' ),
                $context_label,
                implode( ', ', $missing )
            )
        );
    }

    /**
     * Required methods for preorder order sheet export.
     *
     * @return array
     */
    private static function sop_get_required_methods_for_order_sheet_export() {
        return array(
            'build_content_types_xml',
            'build_root_rels_xml',
            'build_workbook_xml',
            'build_workbook_rels_xml',
            'build_styles_xml',
            'build_sheet_rels_xml',
            'build_sheet_xml',
            'build_cols_xml',
            'build_row_xml',
            'build_drawing_xml',
            'build_drawing_rels_xml',
            'build_app_xml',
            'build_core_xml',
            'format_number_cell',
            'resolve_image_path',
            'column_letter',
            'sanitize_xml_text',
            'esc_xml',
            'get_order_sheet_base_columns',
            'sop_sanitize_xlsx_sheet_name',
            'build_order_sheet_row_base_cells',
            'get_line_float',
            'get_sheet_balance_fx_rate_from_header',
            'resolve_unit_costs_for_export',
            'sop_get_sop_settings_fx_rates',
            'get_line_positive_float',
            'sop_convert_rmb_to_currency',
        );
    }

    /**
     * Required methods for Goods-In Issues export.
     *
     * @return array
     */
    private static function sop_get_required_methods_for_goodsin_issues_export() {
        return array(
            'get_order_sheet_base_columns',
            'build_content_types_xml',
            'build_root_rels_xml',
            'build_workbook_xml',
            'build_workbook_rels_xml',
            'build_styles_xml',
            'build_sheet_rels_xml',
            'build_sheet_xml',
            'build_cols_xml',
            'build_row_xml',
            'build_drawing_xml',
            'build_drawing_rels_xml',
            'build_app_xml',
            'build_core_xml',
            'format_number_cell',
            'resolve_image_path',
            'column_letter',
            'sanitize_xml_text',
            'esc_xml',
            'sop_sanitize_xlsx_sheet_name',
            'build_order_sheet_row_base_cells',
            'get_line_float',
            'get_sheet_balance_fx_rate_from_header',
            'resolve_unit_costs_for_export',
            'sop_get_sop_settings_fx_rates',
            'get_line_positive_float',
            'sop_convert_rmb_to_currency',
        );
    }

    /**
     * Required methods for PO template export.
     *
     * @return array
     */
    private static function sop_get_required_methods_for_po_template_export() {
        return array(
            'po_template_get_style_index',
            'po_template_get_or_create_cell',
            'po_template_set_inline_cell',
            'po_template_set_number_cell',
            'po_normalize_multiline_block',
            'sanitize_po_inline_text_preserve_newlines',
            'esc_xml',
            'po_column_index_from_letter',
            'po_template_get_style_index', // already listed, but harmless to ensure presence.
            'sop_po_replace_currency_labels_in_xml',
            'sop_sanitize_xlsx_sheet_name',
        );
    }

    /**
     * Build an XLSX file with embedded images.
     *
     * @param array $sheet_header Sheet header data.
     * @param array $lines        Line rows.
     * @return string|WP_Error    Path to XLSX temp file or error.
     */
    public static function build_xlsx_file( array $sheet_header, array $lines ) {
        $preflight = self::sop_require_methods( self::sop_get_required_methods_for_order_sheet_export(), 'Preorder Order Sheet' );
        if ( is_wp_error( $preflight ) ) {
            return $preflight;
        }
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
        $img_cx             = 742950; // 78px in EMUs.
        $img_cy             = 742950; // 78px in EMUs.
        $img_margin_emu     = 9525; // 1px in EMUs.
        $supplier_currency  = 'GBP';
        if ( isset( $sheet_header['supplier_id'] ) && function_exists( 'sop_preorder_resolve_supplier_params' ) ) {
            $ctx = sop_preorder_resolve_supplier_params( (int) $sheet_header['supplier_id'] );
            if ( ! empty( $ctx['currency_code'] ) ) {
                $supplier_currency = strtoupper( trim( (string) $ctx['currency_code'] ) );
            }
        }
        $show_usd_column = ( 'RMB' === $supplier_currency );
        $include_supplier_skus = false;
        if ( isset( $sheet_header['supplier_id'] ) && function_exists( 'sop_supplier_show_supplier_skus_column' ) ) {
            $include_supplier_skus = sop_supplier_show_supplier_skus_column( (int) $sheet_header['supplier_id'] );
        }

        $sheet_balance_fx_rate = self::get_sheet_balance_fx_rate_from_header( $sheet_header );
        // Determine sheet-level FX for USD display: Balance FX (payload) > supplier effective FX > converter helper.
        $sheet_fx_for_usd = 0.0;
        if ( $show_usd_column ) {
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

        $include_supplier_skus = false;
        if ( isset( $sheet_header['supplier_id'] ) && function_exists( 'sop_supplier_show_supplier_skus_column' ) ) {
            $include_supplier_skus = sop_supplier_show_supplier_skus_column( (int) $sheet_header['supplier_id'] );
        }

        $sheet_rows_xml = '';
        $columns        = self::get_order_sheet_base_columns( $supplier_currency, $show_usd_column, $include_supplier_skus );

        // Header row.
        $header_cells  = $columns;
        $header_styles = array_fill( 0, count( $columns ), null );
        $column_index  = array();
        foreach ( $columns as $idx => $label ) {
            $column_index[ $label ] = $idx;
        }

        if ( isset( $column_index['SKU'] ) ) {
            $header_cells[ $column_index['SKU'] ] = array(
                'type' => 'rich',
                'runs' => array(
                    array( 'text' => "SKU\n", 'bold' => true ),
                    array( 'text' => '(for barcode 128 sticker label)', 'bold' => true, 'color' => 'FFFF0000' ),
                ),
            );
            $header_styles[ $column_index['SKU'] ] = 3;
        }
        if ( isset( $column_index['Order notes'] ) ) {
            $header_cells[ $column_index['Order notes'] ] = array(
                'type' => 'rich',
                'runs' => array(
                    array( 'text' => "Order notes\n", 'bold' => true ),
                    array( 'text' => '(for buyer and supplier notes)', 'bold' => true, 'color' => 'FFFF0000' ),
                ),
            );
            $header_styles[ $column_index['Order notes'] ] = 3;
        }
        if ( isset( $column_index['Carton no.'] ) ) {
            $header_cells[ $column_index['Carton no.'] ] = array(
                'type' => 'rich',
                'runs' => array(
                    array( 'text' => "Carton no.\n", 'bold' => true ),
                    array( 'text' => '(use e.g. 1-5,8,11-13)', 'bold' => true, 'color' => 'FFFF0000' ),
                ),
            );
            $header_styles[ $column_index['Carton no.'] ] = 3;
        }

        $sheet_rows_xml .= self::build_row_xml( 1, $header_cells, true, $header_styles, 0, array(), 30 );

        foreach ( $lines as $line ) {
            $balance_rate_for_row = $show_usd_column ? $sheet_fx_for_usd : $sheet_balance_fx_rate;
            $base = self::build_order_sheet_row_base_cells( $line, $supplier_currency, $balance_rate_for_row, $show_usd_column, $include_supplier_skus );
            $sheet_rows_xml .= self::build_row_xml( $row_index, $base['cells'], false, $base['styles'], 0, array( 2 ) );

            if ( ! empty( $base['image_path'] ) ) {
                $media_name     = 'image' . $image_index;
                $ext            = $base['image_ext'];
                $media_filename = $media_name . '.' . $ext;
                $media_files[]  = array(
                    'path'     => $base['image_path'],
                    'zip_path' => 'xl/media/' . $media_filename,
                    'ext'      => $ext,
                );
                $images[] = array(
                    'rel_id'  => 'rId' . $image_index,
                    'row'     => $row_index - 1, // zero-index for anchor.
                    'col'     => 1, // Image column B.
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
        $workbook      = self::build_workbook_xml( 'Order Sheet' );
        $workbook_rels = self::build_workbook_rels_xml();
        $styles        = self::build_styles_xml();
        $sheet_rels    = self::build_sheet_rels_xml( ! empty( $images ) );
        $max_row       = $row_index - 1;
        $sheet_xml     = self::build_sheet_xml( $sheet_rows_xml, ! empty( $images ), $max_row, $show_usd_column, count( $columns ), $include_supplier_skus, 1 );
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
     * Base preorder Order Sheet columns (header only).
     *
     * @param string $supplier_currency Supplier currency code.
     * @param bool   $show_usd_column   Whether USD column should be shown.
     * @return array
     */
    private static function get_order_sheet_base_columns( $supplier_currency, $show_usd_column, $include_supplier_skus = false ) {
        $columns = array(
            'ID',
            'Image',
            'SKU',
        );
        if ( $include_supplier_skus ) {
            $columns[] = 'Supplier SKUs';
        }
        $columns = array_merge(
            $columns,
            array(
            'Brand',
            'Product name',
            'Categories',
            'MOQ',
            'Qty',
            'Unit price (' . $supplier_currency . ')',
            )
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

        return $columns;
    }

    /**
     * Build base row cells/styles for order sheet style exports.
     *
     * @param array  $line               Line data.
     * @param string $supplier_currency  Supplier currency code.
     * @param float  $sheet_fx_for_usd   FX rate RMB/USD if applicable.
     * @param bool   $show_usd_column    Whether USD columns are shown.
     * @return array {
     *     @type array  $cells
     *     @type array  $styles
     *     @type string $image_path
     *     @type string $image_ext
     *     @type int    $product_id
     * }
     */
    private static function get_line_float( $line, $keys, $default = 0.0 ) {
        if ( ! is_array( $line ) ) {
            return $default;
        }
        foreach ( (array) $keys as $key ) {
            if ( isset( $line[ $key ] ) && '' !== $line[ $key ] ) {
                $raw = trim( (string) $line[ $key ] );
                $raw = str_replace( ',', '', $raw );
                if ( is_numeric( $raw ) ) {
                    return (float) $raw;
                }
            }
        }
        return $default;
    }

    /**
     * Get first positive float from a list of keys.
     *
     * @param array $line Line data.
     * @param array $keys Keys to inspect.
     * @return float
     */
    private static function get_line_positive_float( $line, $keys ) {
        $val = self::get_line_float( $line, $keys, 0.0 );
        return ( $val > 0 ) ? $val : 0.0;
    }

    /**
     * Resolve balance FX rate from sheet header (RMB per currency unit).
     *
     * @param array $sheet_header Sheet header.
     * @return float
     */
    private static function get_sheet_balance_fx_rate_from_header( $sheet_header ) {
        $rate        = 0.0;
        $po_payload  = array();
        $notes_field = isset( $sheet_header['header_notes_owner'] ) ? $sheet_header['header_notes_owner'] : '';
        if ( is_string( $notes_field ) && '' !== $notes_field ) {
            $decoded = json_decode( $notes_field, true );
            if ( is_array( $decoded ) ) {
                $po_payload = $decoded;
            }
        }
        $candidates = array(
            'balance_fx_rate',
            'balance_fx_rate_owner',
            'locked_balance_fx_rate',
        );
        foreach ( $candidates as $candidate ) {
            if ( isset( $po_payload[ $candidate ] ) && (float) $po_payload[ $candidate ] > 0 ) {
                $rate = (float) $po_payload[ $candidate ];
                break;
            }
        }
        return $rate;
    }

    /**
     * Resolve unit costs for export (supplier + RMB with fallbacks).
     *
     * @param array  $line              Line data.
     * @param string $supplier_currency Supplier currency code.
     * @param float  $balance_fx_rate   Balance FX (RMB per supplier currency or RMB per USD).
     * @return array
     */
    private static function resolve_unit_costs_for_export( array $line, $supplier_currency, $balance_fx_rate ) {
        $currency_upper = strtoupper( trim( (string) $supplier_currency ) );
        $unit_cost_rmb  = self::get_line_positive_float( $line, array( 'cost_rmb_owner', 'cost_rmb', 'cost_per_unit_rmb', 'cost_rmb_per_unit' ) );

        // Supplier currency specific keys.
        $supplier_keys = array(
            'cost_supplier_owner',
            'cost_supplier',
            'supplier_cost_owner',
            'supplier_cost',
            'unit_cost',
            'cost_per_unit',
            'cost_owner',
            'cost',
        );

        $currency_lower = strtolower( $supplier_currency );
        $supplier_keys  = array_merge(
            array(
                'cost_' . $currency_lower . '_owner',
                'cost_' . $currency_lower,
                'supplier_cost_' . $currency_lower . '_owner',
                'supplier_cost_' . $currency_lower,
                'unit_cost_' . $currency_lower,
            ),
            $supplier_keys
        );

        $unit_cost_supplier_raw = self::get_line_positive_float( $line, $supplier_keys );
        $unit_cost_supplier     = 0.0;

        $rates = self::sop_get_sop_settings_fx_rates();

        if ( 'RMB' === $currency_upper ) {
            $unit_cost_supplier = ( $unit_cost_rmb > 0 ) ? $unit_cost_rmb : $unit_cost_supplier_raw;
        } else {
            if ( $unit_cost_supplier_raw > 0 ) {
                $unit_cost_supplier = $unit_cost_supplier_raw;
            } elseif ( $unit_cost_rmb > 0 ) {
                $converted = self::sop_convert_rmb_to_currency( $unit_cost_rmb, $currency_upper, $balance_fx_rate, $rates );
                if ( $converted > 0 ) {
                    $unit_cost_supplier = $converted;
                }
            }
        }

        return array(
            'unit_cost_supplier' => $unit_cost_supplier,
            'unit_cost_rmb'      => $unit_cost_rmb,
        );
    }

    /**
     * Get SOP FX rates from settings.
     *
     * @return array
     */
    private static function sop_get_sop_settings_fx_rates() {
        $opt = get_option( 'sop_settings', array() );
        $opt = is_array( $opt ) ? $opt : array();

        $read_rate = function ( $keys ) use ( $opt ) {
            foreach ( (array) $keys as $key ) {
                if ( isset( $opt[ $key ] ) && '' !== $opt[ $key ] ) {
                    return (float) $opt[ $key ];
                }
            }
            return 0.0;
        };

        return array(
            'rmb_to_gbp' => $read_rate( array( 'rmb_to_gbp_rate', 'rmb to gbp rate' ) ),
            'eur_to_gbp' => $read_rate( array( 'eur_to_gbp_rate', 'eur to gbp rate' ) ),
            'usd_to_gbp' => $read_rate( array( 'usd_to_gbp_rate', 'usd to gbp rate' ) ),
            'usd_to_rmb' => $read_rate( array( 'usd_to_rmb_rate', 'usd to rmb rate' ) ),
        );
    }

    /**
     * Convert RMB to target currency using sheet + SOP rates.
     *
     * @param float  $rmb             RMB amount.
     * @param string $target_currency Target currency.
     * @param float  $sheet_usd_to_rmb Sheet FX (RMB per USD) if available.
     * @param array  $rates           SOP rates array.
     * @return float
     */
    private static function sop_convert_rmb_to_currency( $rmb, $target_currency, $sheet_usd_to_rmb, $rates ) {
        $rmb             = (float) $rmb;
        $target_currency = strtoupper( trim( (string) $target_currency ) );
        if ( $rmb <= 0 ) {
            return 0.0;
        }
        $usd_to_rmb = ( $sheet_usd_to_rmb > 0 ) ? $sheet_usd_to_rmb : ( isset( $rates['usd_to_rmb'] ) ? (float) $rates['usd_to_rmb'] : 0.0 );
        $rmb_to_gbp = isset( $rates['rmb_to_gbp'] ) ? (float) $rates['rmb_to_gbp'] : 0.0;
        $usd_to_gbp = isset( $rates['usd_to_gbp'] ) ? (float) $rates['usd_to_gbp'] : 0.0;
        $eur_to_gbp = isset( $rates['eur_to_gbp'] ) ? (float) $rates['eur_to_gbp'] : 0.0;

        if ( 'USD' === $target_currency ) {
            if ( $usd_to_rmb > 0 ) {
                return $rmb / $usd_to_rmb;
            }
            if ( $rmb_to_gbp > 0 && $usd_to_gbp > 0 ) {
                $gbp = $rmb * $rmb_to_gbp;
                return $gbp / $usd_to_gbp;
            }
            return 0.0;
        }

        if ( 'GBP' === $target_currency ) {
            if ( $usd_to_rmb > 0 && $usd_to_gbp > 0 ) {
                return ( $rmb / $usd_to_rmb ) * $usd_to_gbp;
            }
            if ( $rmb_to_gbp > 0 ) {
                return $rmb * $rmb_to_gbp;
            }
            return 0.0;
        }

        if ( 'EUR' === $target_currency ) {
            $gbp = 0.0;
            if ( $usd_to_rmb > 0 && $usd_to_gbp > 0 ) {
                $gbp = ( $rmb / $usd_to_rmb ) * $usd_to_gbp;
            } elseif ( $rmb_to_gbp > 0 ) {
                $gbp = $rmb * $rmb_to_gbp;
            }
            if ( $gbp > 0 && $eur_to_gbp > 0 ) {
                return $gbp / $eur_to_gbp;
            }
            return 0.0;
        }

        return 0.0;
    }

    private static function build_order_sheet_row_base_cells( array $line, $supplier_currency, $balance_fx_rate, $show_usd_column, $include_supplier_skus = false ) {
        $product_id    = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
        $sku_to_output = isset( $line['sku'] ) ? (string) $line['sku'] : '';
        if ( '' === $sku_to_output && isset( $line['sku_owner'] ) ) {
            $sku_to_output = (string) $line['sku_owner'];
        }
        if ( $product_id <= 0 && '' !== $sku_to_output && function_exists( 'wc_get_product_id_by_sku' ) ) {
            $resolved_id = (int) wc_get_product_id_by_sku( $sku_to_output );
            if ( $resolved_id > 0 ) {
                $product_id = $resolved_id;
            }
        }
        $brand         = isset( $line['brand'] ) ? $line['brand'] : ( isset( $line['brand_name'] ) ? $line['brand_name'] : '' );
        $name          = isset( $line['product_name'] ) ? $line['product_name'] : ( isset( $line['name'] ) ? $line['name'] : ( isset( $line['title'] ) ? $line['title'] : ( isset( $line['product'] ) ? $line['product'] : '' ) ) );
        $categories    = isset( $line['categories'] ) ? $line['categories'] : ( isset( $line['category'] ) ? $line['category'] : ( isset( $line['category_names'] ) ? $line['category_names'] : '' ) );
        if ( is_array( $categories ) ) {
            $categories = implode( ', ', $categories );
        }
        $moq           = self::get_line_float( $line, array( 'moq_owner', 'moq', 'min_order_qty', 'supplier_moq' ), 0.0 );
        $qty           = self::get_line_float( $line, array( 'qty_owner', 'qty', 'manual_order_qty', 'ordered_qty' ), 0.0 );
        $costs         = self::resolve_unit_costs_for_export( $line, $supplier_currency, $balance_fx_rate );
        $unit_cost     = $costs['unit_cost_supplier'];
        $unit_cost_rmb = $costs['unit_cost_rmb'];
        $product_notes = isset( $line['product_notes'] ) ? $line['product_notes'] : ( isset( $line['product_notes_owner'] ) ? $line['product_notes_owner'] : ( isset( $line['notes'] ) ? $line['notes'] : '' ) );
        $order_notes   = isset( $line['order_notes'] ) ? $line['order_notes'] : ( isset( $line['order_notes_owner'] ) ? $line['order_notes_owner'] : '' );
        $carton_number = isset( $line['carton_no'] ) ? $line['carton_no'] : '';
        $cm3_per_unit  = self::get_line_float( $line, array( 'cbm_per_unit', 'cm3_per_unit', 'cubic_cm' ), 0.0 );
        $line_cbm      = self::get_line_float( $line, array( 'cbm_total_owner', 'line_cbm', 'cbm_total' ), 0.0 );
        if ( $line_cbm <= 0 && $cm3_per_unit > 0 && $qty > 0 ) {
            $line_cbm = ( $cm3_per_unit * $qty ) / 1000000;
        }

        $cost_usd = '';
        if ( $show_usd_column ) {
            $cost_usd = self::get_line_float( $line, array( 'unit_price_usd', 'regular_price', 'price_usd', 'price' ), 0.0 );
            if ( $cost_usd <= 0 && $unit_cost_rmb > 0 && $balance_fx_rate > 0 ) {
                $cost_usd = $unit_cost_rmb / $balance_fx_rate;
            } elseif ( $cost_usd <= 0 && $unit_cost_rmb > 0 && function_exists( 'sop_convert_rmb_unit_cost_to_usd' ) ) {
                $converted = sop_convert_rmb_unit_cost_to_usd( $unit_cost_rmb );
                if ( $converted > 0 ) {
                    $cost_usd = $converted;
                }
            }
        }
        $line_total_supplier = $qty * $unit_cost;

        $row_cells = array(
            $product_id,
            '', // Image placeholder.
            $sku_to_output,
        );
        if ( $include_supplier_skus ) {
            $supplier_skus_val = '';
            if ( $product_id > 0 ) {
                $supplier_skus_val = get_post_meta( $product_id, '_sop_supplier_skus', true );
                $supplier_skus_val = is_string( $supplier_skus_val ) ? $supplier_skus_val : '';
            }
            $row_cells[] = $supplier_skus_val;
        }
        $row_cells[] = $brand;
        $row_cells[] = $name;
        $row_cells[] = $categories;
        $row_cells[] = self::format_number_cell( $moq );
        $row_cells[] = self::format_number_cell( $qty );
        $row_cells[] = self::format_number_cell( $unit_cost, 2 );
        if ( $show_usd_column ) {
            $row_cells[] = self::format_number_cell( $cost_usd, 2 );
        }
        $row_cells[] = self::format_number_cell( $line_total_supplier, 2 );
        $row_cells[] = $product_notes;
        $row_cells[] = $order_notes;
        $row_cells[] = $carton_number;
        $row_cells[] = self::format_number_cell( $cm3_per_unit, 4 );
        $row_cells[] = self::format_number_cell( $line_cbm, 6 );

        $row_styles = array(
            7,    // ID right.
            0,    // Image placeholder.
            3,    // SKU text + wrap.
        );
        if ( $include_supplier_skus ) {
            $row_styles[] = 2; // Supplier SKUs wrap.
        }
        $row_styles[] = 5;    // Brand center.
        $row_styles[] = 2;    // Product name wrap.
        $row_styles[] = 2;    // Categories wrap.
        $row_styles[] = 7;    // MOQ right.
        $row_styles[] = 7;    // Qty right.
        $row_styles[] = 8;    // Unit price (supplier currency) 2dp.
        if ( $show_usd_column ) {
            $row_styles[] = 8; // Unit price USD 2dp.
        }
        $row_styles[] = 8; // Total (supplier currency) 2dp.
        $row_styles[] = 6; // Product notes left.
        $row_styles[] = 6; // Order notes left.
        $row_styles[] = 6; // Carton left.
        $row_styles[] = 7; // cm3 right.
        $row_styles[] = 7; // cbm right.

        // Resolve image path.
        $image_id   = isset( $line['image_id'] ) ? (int) $line['image_id'] : 0;
        $image_path = self::resolve_image_path( $image_id );
        if ( ! $image_path && $product_id ) {
            $thumb_id = function_exists( 'get_post_thumbnail_id' ) ? (int) get_post_thumbnail_id( $product_id ) : 0;
            $image_path = self::resolve_image_path( $thumb_id );
        }
        $image_ext = '';
        if ( $image_path ) {
            $image_ext = strtolower( pathinfo( $image_path, PATHINFO_EXTENSION ) );
            if ( '' === $image_ext ) {
                $image_ext = 'png';
            }
        }

        return array(
            'cells'      => $row_cells,
            'styles'     => $row_styles,
            'image_path' => $image_path,
            'image_ext'  => $image_ext,
            'product_id' => $product_id,
        );
    }

    /**
     * Build an XLSX file for Goods-In issues (missing/rejected lines only).
     *
     * @param array $sheet_header Sheet header data.
     * @param array $issue_lines  Issue lines.
     * @return string|WP_Error    Path to XLSX temp file or error.
     */
    public static function build_goodsin_issues_xlsx_file( array $sheet_header, array $issue_lines ) {
        $preflight = self::sop_require_methods( self::sop_get_required_methods_for_goodsin_issues_export(), 'Goods-In Issues' );
        if ( is_wp_error( $preflight ) ) {
            return $preflight;
        }
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'sop_export_zip_missing', __( 'XLSX export requires ZipArchive.', 'sop' ) );
        }

        $tmp_base = wp_tempnam( 'sop-goodsin-issues-xlsx' );
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
        $img_cx             = 742950; // 78px in EMUs.
        $img_cy             = 742950; // 78px in EMUs.
        $img_margin_emu     = 9525; // 1px in EMUs.
        $supplier_currency  = 'GBP';
        if ( isset( $sheet_header['supplier_id'] ) && function_exists( 'sop_preorder_resolve_supplier_params' ) ) {
            $ctx = sop_preorder_resolve_supplier_params( (int) $sheet_header['supplier_id'] );
            if ( ! empty( $ctx['currency_code'] ) ) {
                $supplier_currency = strtoupper( trim( (string) $ctx['currency_code'] ) );
            }
        }
        $show_usd_column = ( 'RMB' === $supplier_currency );

        $sheet_balance_fx_rate = self::get_sheet_balance_fx_rate_from_header( $sheet_header );
        // Determine sheet-level FX for USD display: Balance FX (payload) > supplier effective FX > converter helper.
        $sheet_fx_for_usd = 0.0;
        if ( $show_usd_column ) {
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

        $include_supplier_skus = false;
        if ( isset( $sheet_header['supplier_id'] ) && function_exists( 'sop_supplier_show_supplier_skus_column' ) ) {
            $include_supplier_skus = sop_supplier_show_supplier_skus_column( (int) $sheet_header['supplier_id'] );
        }

        $columns       = self::get_order_sheet_base_columns( $supplier_currency, $show_usd_column, $include_supplier_skus );
        foreach ( $columns as $idx => $label ) {
            if ( 'Qty' === $label ) {
                $columns[ $idx ] = 'Qty ordered';
                break;
            }
        }
        $issue_columns = array(
            'Received',
            'Missing',
            'Reject',
            'Reason',
            'GI notes',
            'Credit Qty',
            'Credit total (' . $supplier_currency . ')',
        );
        if ( $show_usd_column ) {
            $issue_columns[] = 'Credit total (USD)';
            $issue_columns[] = 'FX used (RMB/USD)';
        }
        $columns = array_merge( $columns, $issue_columns );

        $sheet_rows_xml = '';
        $sheet_rows_xml .= self::build_row_xml( 1, array_map( 'esc_html', $columns ), true, array(), $row_index - 2 );

        $total_missing         = 0.0;
        $total_reject          = 0.0;
        $total_credit_qty      = 0.0;
        $total_credit_currency = 0.0;
        $total_credit_usd      = 0.0;

        foreach ( $issue_lines as $line ) {
            $ordered        = isset( $line['ordered_qty'] ) ? (float) $line['ordered_qty'] : 0.0;
            $received       = isset( $line['received_qty'] ) ? (float) $line['received_qty'] : 0.0;
            $missing        = isset( $line['goods_in_missing_qty'] ) ? (float) $line['goods_in_missing_qty'] : ( isset( $line['missing_qty'] ) ? (float) $line['missing_qty'] : 0.0 );
            $reject         = isset( $line['goods_in_reject_qty'] ) ? (float) $line['goods_in_reject_qty'] : ( isset( $line['reject_qty'] ) ? (float) $line['reject_qty'] : 0.0 );
            $reason_key     = isset( $line['reject_reason'] ) ? $line['reject_reason'] : '';
            $goods_in_notes = isset( $line['goods_in_notes'] ) ? $line['goods_in_notes'] : '';
            $unit_cost      = self::get_line_float( $line, array( 'cost_rmb_owner', 'cost_rmb', 'cost_supplier_owner', 'cost_supplier', 'supplier_cost_owner', 'supplier_cost', 'unit_cost', 'cost_per_unit', 'cost_owner', 'cost' ), 0.0 );

            // Base columns reuse preorder mapping (ordered qty in base "Qty").
            $line_for_base         = $line;
            // Backfill product context from WC if missing.
            $product_id_for_base = isset( $line_for_base['product_id'] ) ? (int) $line_for_base['product_id'] : 0;
            $sku_candidate        = '';
            if ( empty( $product_id_for_base ) ) {
                if ( isset( $line_for_base['sku'] ) && '' !== $line_for_base['sku'] ) {
                    $sku_candidate = (string) $line_for_base['sku'];
                } elseif ( isset( $line_for_base['sku_owner'] ) && '' !== $line_for_base['sku_owner'] ) {
                    $sku_candidate = (string) $line_for_base['sku_owner'];
                }
                if ( '' !== $sku_candidate && function_exists( 'wc_get_product_id_by_sku' ) ) {
                    $pid = (int) wc_get_product_id_by_sku( $sku_candidate );
                    if ( $pid > 0 ) {
                        $product_id_for_base            = $pid;
                        $line_for_base['product_id']    = $pid;
                        $line_for_base['sku']           = $sku_candidate;
                    }
                }
            }
            if ( $product_id_for_base > 0 && function_exists( 'wc_get_product' ) ) {
                $product_obj = wc_get_product( $product_id_for_base );
                if ( $product_obj ) {
                    if ( empty( $line_for_base['product_name'] ) ) {
                        $line_for_base['product_name'] = $product_obj->get_name();
                    }
                    if ( empty( $line_for_base['categories'] ) && function_exists( 'sop_get_product_category_path_below_root' ) ) {
                        $line_for_base['categories'] = sop_get_product_category_path_below_root( $product_id_for_base );
                    }
                    if ( empty( $line_for_base['brand'] ) && function_exists( 'wc_get_product_terms' ) ) {
                        $brands = wc_get_product_terms( $product_id_for_base, 'product_brand', array( 'fields' => 'names' ) );
                        if ( is_array( $brands ) && ! empty( $brands ) ) {
                            $line_for_base['brand'] = implode( ', ', $brands );
                        }
                    }
                }
            }
            $line_for_base['qty']  = $ordered;
            $balance_rate_for_row  = $show_usd_column ? $sheet_fx_for_usd : $sheet_balance_fx_rate;
            $base                  = self::build_order_sheet_row_base_cells( $line_for_base, $supplier_currency, $balance_rate_for_row, $show_usd_column, $include_supplier_skus );

            $credit_qty    = max( 0, $missing + $reject );
            $costs         = self::resolve_unit_costs_for_export( $line, $supplier_currency, $balance_rate_for_row );
            $unit_cost     = $costs['unit_cost_supplier'];
            $unit_cost_rmb = $costs['unit_cost_rmb'];
            $credit_total  = $credit_qty * $unit_cost;
            $credit_total_usd = '';
            if ( $show_usd_column && $sheet_fx_for_usd > 0 ) {
                $credit_total_usd = ( $credit_qty > 0 && $unit_cost_rmb > 0 ) ? ( ( $credit_qty * $unit_cost_rmb ) / $sheet_fx_for_usd ) : '';
            }

            $row_cells  = $base['cells'];
            $row_styles = $base['styles'];

            // Append Goods-In issue columns.
            $row_cells[]  = self::format_number_cell( $received );
            $row_styles[] = 7;
            $row_cells[]  = self::format_number_cell( $missing );
            $row_styles[] = 7;
            $row_cells[]  = self::format_number_cell( $reject );
            $row_styles[] = 7;
            $reason_label = '';
            if ( $reason_key ) {
                $reason_map = array(
                    'wrong_spec'   => __( 'Wrong spec', 'sop' ),
                    'wrong_colour' => __( 'Wrong colour', 'sop' ),
                    'damaged'      => __( 'Damaged', 'sop' ),
                    'other'        => __( 'Other', 'sop' ),
                );
                $reason_label = isset( $reason_map[ $reason_key ] ) ? $reason_map[ $reason_key ] : (string) $reason_key;
            }
            $row_cells[]  = $reason_label;
            $row_styles[] = 2;
            $row_cells[]  = $goods_in_notes;
            $row_styles[] = 2;
            $row_cells[]  = self::format_number_cell( $credit_qty );
            $row_styles[] = 7;
            $row_cells[]  = self::format_number_cell( $credit_total, 4 );
            $row_styles[] = 7;
            if ( $show_usd_column ) {
                $row_cells[]  = self::format_number_cell( $credit_total_usd, 4 );
                $row_styles[] = 7;
                $row_cells[]  = ( $sheet_fx_for_usd > 0 ) ? number_format( (float) $sheet_fx_for_usd, 3, '.', '' ) : '';
                $row_styles[] = 7;
            }

            $sheet_rows_xml .= self::build_row_xml( $row_index, $row_cells, false, $row_styles, 0, array( 2 ) );

            if ( ! empty( $base['image_path'] ) ) {
                $media_name     = 'image' . $image_index;
                $ext            = $base['image_ext'];
                $media_filename = $media_name . '.' . $ext;
                $media_files[]  = array(
                    'path'     => $base['image_path'],
                    'zip_path' => 'xl/media/' . $media_filename,
                    'ext'      => $ext,
                );
                $images[] = array(
                    'rel_id'  => 'rId' . $image_index,
                    'row'     => $row_index - 1, // zero-index for anchor.
                    'col'     => 1, // Image column B.
                    'cx'      => $img_cx,
                    'cy'      => $img_cy,
                    'col_off' => $img_margin_emu,
                    'row_off' => $img_margin_emu,
                    'ext'     => $ext,
                );
                $image_index++;
            }

            $row_index++;

            $total_missing         += $missing;
            $total_reject          += $reject;
            $total_credit_qty      += $credit_qty;
            $total_credit_currency += $credit_total;
            if ( $show_usd_column ) {
                $total_credit_usd += (float) $credit_total_usd;
            }
        }

        // Totals row.
        if ( $row_index > 2 ) {
            $column_index = array();
            foreach ( $columns as $idx => $label ) {
                $column_index[ $label ] = $idx;
            }

            $totals_cells  = array_fill( 0, count( $columns ), '' );
            $totals_styles = array_fill( 0, count( $columns ), null );

            if ( isset( $column_index['Product name'] ) ) {
                $totals_cells[ $column_index['Product name'] ]  = 'TOTALS';
                $totals_styles[ $column_index['Product name'] ] = 6;
            } elseif ( isset( $column_index['SKU'] ) ) {
                $totals_cells[ $column_index['SKU'] ]  = 'TOTALS';
                $totals_styles[ $column_index['SKU'] ] = 6;
            }

            if ( isset( $column_index['Missing'] ) ) {
                $totals_cells[ $column_index['Missing'] ]  = self::format_number_cell( $total_missing, 2 );
                $totals_styles[ $column_index['Missing'] ] = 7;
            }
            if ( isset( $column_index['Reject'] ) ) {
                $totals_cells[ $column_index['Reject'] ]  = self::format_number_cell( $total_reject, 2 );
                $totals_styles[ $column_index['Reject'] ] = 7;
            }
            if ( isset( $column_index['Credit Qty'] ) ) {
                $totals_cells[ $column_index['Credit Qty'] ]  = self::format_number_cell( $total_credit_qty, 2 );
                $totals_styles[ $column_index['Credit Qty'] ] = 7;
            }

            $credit_label = 'Credit total (' . $supplier_currency . ')';
            if ( isset( $column_index[ $credit_label ] ) ) {
                $totals_cells[ $column_index[ $credit_label ] ]  = self::format_number_cell( $total_credit_currency, 4 );
                $totals_styles[ $column_index[ $credit_label ] ] = 7;
            }

            if ( $show_usd_column && isset( $column_index['Credit total (USD)'] ) ) {
                $totals_cells[ $column_index['Credit total (USD)'] ]  = self::format_number_cell( $total_credit_usd, 4 );
                $totals_styles[ $column_index['Credit total (USD)'] ] = 7;
            }
            if ( $show_usd_column && isset( $column_index['FX used (RMB/USD)'] ) ) {
                $totals_cells[ $column_index['FX used (RMB/USD)'] ]  = ( $sheet_fx_for_usd > 0 ) ? number_format( (float) $sheet_fx_for_usd, 3, '.', '' ) : '';
                $totals_styles[ $column_index['FX used (RMB/USD)'] ] = 7;
            }

            $sheet_rows_xml .= self::build_row_xml( $row_index, $totals_cells, false, $totals_styles, 0, array( 2 ) );
            $row_index++;
        }

        $has_images = ! empty( $images );
        $sheet_xml  = self::build_sheet_xml( $sheet_rows_xml, $has_images, $row_index - 1, $show_usd_column, count( $columns ), $include_supplier_skus, 1 );
        $sheet_rels = self::build_sheet_rels_xml( $has_images );
        $drawing_xml = '';
        $drawing_rels = '';

        if ( $has_images ) {
            $drawing_xml  = self::build_drawing_xml( $images );
            $drawing_rels = self::build_drawing_rels_xml( $images );
        }

        $zip->addFromString( '[Content_Types].xml', self::build_content_types_xml( $has_images ) );
        $zip->addFromString( '_rels/.rels', self::build_root_rels_xml() );
        $zip->addFromString( 'xl/workbook.xml', self::build_workbook_xml( 'Issues' ) );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', self::build_workbook_rels_xml() );
        $zip->addFromString( 'xl/styles.xml', self::build_styles_xml() );
        $zip->addFromString( 'xl/worksheets/_rels/sheet1.xml.rels', $sheet_rels );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
        if ( $has_images ) {
            $zip->addFromString( 'xl/drawings/drawing1.xml', $drawing_xml );
            $zip->addFromString( 'xl/drawings/_rels/drawing1.xml.rels', $drawing_rels );
        }
        $zip->addFromString( 'docProps/app.xml', self::build_app_xml() );
        $zip->addFromString( 'docProps/core.xml', self::build_core_xml() );

        // Add images to zip.
        foreach ( $media_files as $media_file ) {
            if ( empty( $media_file['path'] ) || empty( $media_file['zip_path'] ) ) {
                continue;
            }
            if ( ! is_string( $media_file['path'] ) || ! is_string( $media_file['zip_path'] ) ) {
                continue;
            }
            if ( ! file_exists( $media_file['path'] ) ) {
                continue;
            }
            $zip->addFile( $media_file['path'], $media_file['zip_path'] );
        }

        $zip->close();

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
        $preflight = self::sop_require_methods( self::sop_get_required_methods_for_po_template_export(), 'Order Summary (PO) template' );
        if ( is_wp_error( $preflight ) ) {
            return $preflight;
        }
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

        // Ensure dimension covers A1:D40 (terms row may move for RMB).
        $dimension = $xpath->query( '/s:worksheet/s:dimension' )->item( 0 );
        if ( $dimension ) {
            $dimension->setAttribute( 'ref', 'A1:D40' );
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
        $is_new_template_layout = ( null !== $get_style( 'D34' ) && null !== $get_style( 'A40' ) );

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
        $result = $set_inline( 'D19', $safe_order );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'B20', $safe_hol_from );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'D20', $safe_hol_to );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'B21', $safe_load );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_inline( 'D21', $safe_eta );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }

        $result = $set_inline( 'A24', $summary_label );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        $result = $set_number( 'D24', $base_total );
        if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        if ( $is_new_template_layout ) {
            $extras_start_row = 25;
            $extras_end_row   = 33;
            $extras_row       = $extras_start_row;
            $extras_remaining = 0.0;
            foreach ( $extras_rows as $extra ) {
                if ( $extras_row > $extras_end_row ) {
                    $extras_remaining += (float) $extra['amount'];
                    continue;
                }
                $result = $set_inline( 'A' . $extras_row, $extra['label'] );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
                $result = $set_number( 'D' . $extras_row, $extra['amount'] );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
                $extras_row++;
            }
            if ( $extras_remaining > 0 ) {
                $result = $set_inline( 'A' . $extras_end_row, __( 'Other extras', 'sop' ) );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
                $result = $set_number( 'D' . $extras_end_row, $extras_remaining );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            }
            $result = $set_number( 'D34', $total_with_extras );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        } else {
            $result = $set_number( 'D25', $total_with_extras );
            if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
        }

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
            if ( $is_new_template_layout ) {
                if ( $deposit_usd > 0 ) {
                    $set_number( 'B37', $deposit_usd );
                }
                if ( $deposit_fx > 0 ) {
                    $set_inline( 'C37', sprintf( __( '1 USD = %s RMB', 'sop' ), $fx_display ), '' );
                }
                $result = $set_number( 'D37', $deposit_rmb );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            } else {
                $set_number( 'B28', $deposit_usd );
                $set_inline( 'C28', $deposit_fx > 0 ? sprintf( __( '1 USD = %s RMB', 'sop' ), $fx_display ) : '', '' );
                $result = $set_number( 'D28', $deposit_rmb );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            }

            // Balance row (template defines layout). Only show USD/FX when FX rate provided.
            $balance_fx_display = ( $effective_balance_fx_for_export > 0 ) ? $format_fx( $effective_balance_fx_for_export ) : '';
            if ( $is_new_template_layout ) {
                if ( $balance_usd_for_export > 0 ) {
                    $set_number( 'B38', $balance_usd_for_export );
                }
                if ( $effective_balance_fx_for_export > 0 ) {
                    $set_inline( 'C38', sprintf( __( '1 USD = %s RMB', 'sop' ), $balance_fx_display ), '' );
                }
                $result = $set_number( 'D38', $balance_rmb );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            } else {
                if ( $balance_usd_for_export > 0 ) {
                    $set_number( 'B29', $balance_usd_for_export );
                } else {
                    $set_inline( 'B29', '', '' );
                }
                if ( $effective_balance_fx_for_export > 0 ) {
                    $set_inline( 'C29', sprintf( __( '1 USD = %s RMB', 'sop' ), $balance_fx_display ), '' );
                } else {
                    $set_inline( 'C29', '', '' );
                }
                $result = $set_number( 'D29', $balance_rmb );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            }
        } else {
            // Non-RMB template values: deposit and balance in supplier currency.
            $deposit_simple = $deposit_usd;
            $balance_simple = $total_with_extras - $deposit_simple;
            if ( $balance_simple < 0 ) {
                $balance_simple = 0.0;
            }
            if ( $is_new_template_layout && null !== $get_style( 'D37' ) && null !== $get_style( 'D38' ) ) {
                $result = $set_number( 'D37', $deposit_simple );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
                $result = $set_number( 'D38', $balance_simple );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            } else {
                $result = $set_number( 'D27', $deposit_simple );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
                $result = $set_number( 'D28', $balance_simple );
                if ( is_wp_error( $result ) ) { $zip->close(); return $result; }
            }
        }

        // Terms: single multiline block into one cell (prefer A31 if present, else A30).
        $terms_text  = trim( self::po_normalize_multiline_block( $payment_terms ) );
        if ( null !== $get_style( 'A40' ) ) {
            $terms_cell = 'A40';
        } elseif ( null !== $get_style( 'A31' ) ) {
            $terms_cell = 'A31';
        } else {
            $terms_cell = 'A30';
        }
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

    private static function sanitize_xml_text( $value ) {
        $value = (string) $value;
        // Decode entities so &amp; etc. render correctly before escaping.
        $value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $value = str_replace( array( "\r\n", "\r" ), "\n", $value );
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value );
        $value = htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
        return str_replace( "\n", '&#10;', $value );
    }

    private static function sanitize_xml_text_preserve_newlines( $value ) {
        $value = (string) $value;
        $value = str_replace( array( "\r\n", "\r" ), "\n", $value );
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value );
        $value = htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
        return str_replace( "\n", '&#10;', $value );
    }

    private static function column_letter( $index ) {
        $index  = (int) $index;
        $letter = '';
        while ( $index >= 0 ) {
            $letter = chr( $index % 26 + 65 ) . $letter;
            $index  = floor( $index / 26 ) - 1;
        }
        return $letter;
    }

    private static function build_row_xml( $row_num, $cells, $is_header = false, $styles = array(), $row_offset_for_height = 0, $force_inline_cols = array(), $row_height_override = 0 ) {
        $row_style_attr  = ' s="4" customFormat="1"';
        $row_height_attr = $is_header ? '' : ' ht="60" customHeight="1"';
        if ( $row_height_override > 0 ) {
            $row_height_attr = ' ht="' . (float) $row_height_override . '" customHeight="1"';
        }
        $xml             = '<row r="' . (int) $row_num . '"' . $row_style_attr . $row_height_attr . '>';
        $col_index       = 0;

        foreach ( $cells as $cell_value ) {
            $col_letter = self::column_letter( $col_index ) . $row_num;
            $style_idx  = isset( $styles[ $col_index ] ) ? $styles[ $col_index ] : null;

            if ( null === $style_idx ) {
                $style_idx = 4;
            } else {
                $style_idx = (int) $style_idx;
            }

            $force_inline = in_array( (int) $col_index, (array) $force_inline_cols, true );
            $is_rich      = is_array( $cell_value ) && isset( $cell_value['type'] ) && 'rich' === $cell_value['type'];

            $is_text_style = in_array( $style_idx, array( 1, 3 ), true );

            if ( ! $is_rich && ! $force_inline && ! $is_text_style && is_numeric( $cell_value ) ) {
                $xml .= '<c r="' . $col_letter . '" s="' . $style_idx . '"><v>' . $cell_value . '</v></c>';
            } else {
                $xml .= '<c r="' . $col_letter . '" t="inlineStr" s="' . $style_idx . '">';
                if ( $is_rich ) {
                    $xml .= '<is>';
                    $runs = isset( $cell_value['runs'] ) && is_array( $cell_value['runs'] ) ? $cell_value['runs'] : array();
                    foreach ( $runs as $run ) {
                        $text  = isset( $run['text'] ) ? (string) $run['text'] : '';
                        $bold  = ! empty( $run['bold'] );
                        $color = isset( $run['color'] ) ? (string) $run['color'] : '';
                        $xml  .= '<r><rPr>';
                        if ( $bold ) {
                            $xml .= '<b/>';
                        }
                        if ( '' !== $color ) {
                            $xml .= '<color rgb="' . self::esc_xml( $color ) . '"/>';
                        }
                        $xml .= '</rPr><t xml:space="preserve">' . self::sanitize_xml_text_preserve_newlines( $text ) . '</t></r>';
                    }
                    $xml .= '</is>';
                } else {
                    $xml .= '<is><t xml:space="preserve">' . self::sanitize_xml_text( $cell_value ) . '</t></is>';
                }
                $xml .= '</c>';
            }

            $col_index++;
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

        $editor->resize( 78, 78, true );
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

    private static function sop_sanitize_xlsx_sheet_name( $name ) {
        $name = (string) $name;
        $name = trim( $name );
        $name = str_replace( array( ':', '\\', '/', '?', '*', '[', ']' ), '', $name );
        if ( '' === $name ) {
            $name = 'Sheet1';
        }
        if ( function_exists( 'mb_substr' ) ) {
            $name = mb_substr( $name, 0, 31 );
        } else {
            $name = substr( $name, 0, 31 );
        }
        return $name;
    }

    private static function build_workbook_xml( $sheet_name = 'Sheet1' ) {
        $sheet_name = self::sop_sanitize_xlsx_sheet_name( $sheet_name );
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        $xml .= '<sheet name="' . esc_attr( $sheet_name ) . '" sheetId="1" r:id="rId1"/>';
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

    private static function build_sheet_rels_xml( $has_images ) {
        $has_images = (bool) $has_images;

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        if ( $has_images ) {
            $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>';
        }
        $xml .= '</Relationships>';
        return $xml;
    }

    private static function build_cols_xml( $show_usd_column = true, $include_supplier_skus = false, $column_count = 0 ) {
        $xml  = '<cols>';
        $xml .= '<col min="1" max="1" width="6.15" customWidth="1"/>'; // ID (A).
        $xml .= '<col min="2" max="2" width="11.5" customWidth="1"/>'; // Image (B).
        $xml .= '<col min="3" max="3" width="28" customWidth="1"/>'; // SKU (C).
        $current_col = 4;
        if ( $include_supplier_skus ) {
            $xml         .= '<col min="' . $current_col . '" max="' . $current_col . '" width="16" customWidth="1"/>'; // Supplier SKUs.
            $current_col++;
        }
        $product_name_col = $current_col + 1; // If no supplier skus, this becomes 4; else 5.
        $categories_col   = $current_col + 2;
        $xml             .= '<col min="' . $product_name_col . '" max="' . $product_name_col . '" width="40" customWidth="1"/>';
        $xml             .= '<col min="' . $categories_col . '" max="' . $categories_col . '" width="30" customWidth="1"/>';
        $moq_col          = $categories_col + 1;
        $qty_col          = $moq_col + 1;
        $unit_price_col   = $moq_col + 2;
        $usd_col          = $show_usd_column ? ( $unit_price_col + 1 ) : 0;
        $total_col        = $show_usd_column ? ( $unit_price_col + 2 ) : ( $unit_price_col + 1 );
        $xml             .= '<col min="' . (int) $moq_col . '" max="' . (int) $moq_col . '" width="7.08" customWidth="1"/>'; // MOQ.
        $xml             .= '<col min="' . (int) $qty_col . '" max="' . (int) $qty_col . '" width="6.53" customWidth="1"/>'; // Qty.
        $xml             .= '<col min="' . (int) $unit_price_col . '" max="' . (int) $unit_price_col . '" width="14.17" customWidth="1"/>'; // Unit price (supplier).
        if ( $show_usd_column ) {
            $xml         .= '<col min="' . (int) $usd_col . '" max="' . (int) $usd_col . '" width="14.17" customWidth="1"/>'; // Unit price (USD).
        }
        $xml             .= '<col min="' . (int) $total_col . '" max="' . (int) $total_col . '" width="14.17" customWidth="1"/>'; // Total (supplier).
        $product_notes_col = ( $show_usd_column ? 12 : 11 ) + ( $include_supplier_skus ? 1 : 0 );
        $order_notes_col   = $product_notes_col + 1;
        $carton_col        = $product_notes_col + 2;
        $xml .= '<col min="' . (int) $product_notes_col . '" max="' . (int) $product_notes_col . '" width="60" customWidth="1"/>'; // Product notes.
        $xml .= '<col min="' . (int) $order_notes_col . '" max="' . (int) $order_notes_col . '" width="60" customWidth="1"/>'; // Order notes.
        $xml .= '<col min="' . (int) $carton_col . '" max="' . (int) $carton_col . '" width="20.70" customWidth="1"/>'; // Carton no. (190px).
        $cm3_col = $carton_col + 1;
        $cbm_col = $carton_col + 2;
        $xml .= '<col min="' . (int) $cm3_col . '" max="' . (int) $cm3_col . '" width="10.90" customWidth="1"/>'; // cm3 per unit.
        $xml .= '<col min="' . (int) $cbm_col . '" max="' . (int) $cbm_col . '" width="10.90" customWidth="1"/>'; // Line CBM.
        if ( $column_count > 0 ) {
            $base_count = count( self::get_order_sheet_base_columns( 'GBP', $show_usd_column, $include_supplier_skus ) );
            if ( $column_count > $base_count ) {
                $issue_col = $base_count + 1;
                $issue_widths = array( 12, 12, 12, 20, 50, 12, 14 );
                foreach ( $issue_widths as $width ) {
                    if ( $issue_col > $column_count ) {
                        break;
                    }
                    $xml .= '<col min="' . $issue_col . '" max="' . $issue_col . '" width="' . $width . '" customWidth="1"/>';
                    $issue_col++;
                }
                if ( $show_usd_column ) {
                    if ( $issue_col <= $column_count ) {
                        $xml .= '<col min="' . $issue_col . '" max="' . $issue_col . '" width="14" customWidth="1"/>';
                        $issue_col++;
                    }
                    if ( $issue_col <= $column_count ) {
                        $xml .= '<col min="' . $issue_col . '" max="' . $issue_col . '" width="12" customWidth="1"/>';
                    }
                }
            }
        }
        $xml .= '</cols>';
        return $xml;
    }

    private static function build_sheet_xml( $rows_xml, $has_drawing, $max_row, $show_usd_column = true, $column_count = 0, $include_supplier_skus = false, $header_rows = 1 ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $last_col_index  = $column_count > 0 ? ( $column_count - 1 ) : ( $show_usd_column ? 14 : 13 );
        $last_col_letter = self::column_letter( max( 0, $last_col_index ) );
        $max_row         = max( 1, (int) $max_row );
        $header_rows     = max( 1, (int) $header_rows );
        $top_left_row    = $header_rows + 1;
        $xml .= '<dimension ref="A1:' . $last_col_letter . $max_row . '"/>';
        $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $header_rows . '" topLeftCell="A' . $top_left_row . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A' . $top_left_row . '" sqref="A' . $top_left_row . '"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="60" customHeight="1"/>';
        $xml .= self::build_cols_xml( $show_usd_column, $include_supplier_skus, $column_count );
        $xml .= '<sheetData>' . $rows_xml . '</sheetData>';
        $xml .= '<autoFilter ref="A1:' . $last_col_letter . $max_row . '"/>';
        if ( $has_drawing ) {
            $xml .= '<drawing r:id="rId1"/>';
        }
        $xml .= '</worksheet>';
        return $xml;
    }

    private static function build_styles_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts>';
        $xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font><font><b/><color rgb="FFFF0000"/><sz val="11"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font></fonts>';
        $xml .= '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>';
        $xml .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $xml .= '<cellXfs count="10">';
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>';
        $xml .= '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'; // Text format.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>';
        $xml .= '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'; // Text + wrap.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'; // Default centered (explicit).
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'; // Center horizontal + vertical.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'; // Left align.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'; // Right align.
        $xml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'; // Right align 2dp.
        $xml .= '<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment wrapText="1" horizontal="center" vertical="center"/></xf>'; // Header note (red bold).
        $xml .= '</cellXfs>';
        $xml .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';
        $xml .= '<dxfs count="0"/>';
        $xml .= '<tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/>';
        $xml .= '</styleSheet>';
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


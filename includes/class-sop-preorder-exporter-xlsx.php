<?php
/**
 * Stock Order Plugin - Preorder XLSX Exporter (embedded images)
 * File version: 1.0.02
 *
 * Build a real XLSX with embedded images (no external URLs) for pre-order sheets.
 * - Column widths + wrap text + 1.6cm images + preserve SKU spaces.
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

        $images      = array();
        $media_files = array();
        $image_index = 1;
        $row_index   = 2; // Data rows start at 2 (row 1 is header).
        $img_cx      = 576000; // 1.6cm in EMUs.
        $img_cy      = 576000; // 1.6cm in EMUs.

        // Determine sheet-level FX for USD display: Balance FX (payload) > supplier effective FX > converter helper.
        $sheet_fx_for_usd = 0.0;
        $po_payload       = array();
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

        $sheet_rows_xml = '';
        $columns = array(
            'Image',
            'SKU',
            'Brand',
            'Product name',
            'Categories',
            'MOQ',
            'Qty',
            'Unit price (RMB)',
            'Unit price (USD)',
            'Total (RMB)',
            'Product notes',
            'Order notes',
            'Carton no.',
            'cm3 per unit',
            'Line CBM',
        );

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
            if ( $cost_rmb > 0 && $sheet_fx_for_usd > 0 ) {
                $cost_usd = $cost_rmb / $sheet_fx_for_usd;
            } elseif ( $cost_rmb > 0 && function_exists( 'sop_convert_rmb_unit_cost_to_usd' ) ) {
                $converted = sop_convert_rmb_unit_cost_to_usd( $cost_rmb );
                if ( $converted > 0 ) {
                    $cost_usd = $converted;
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
                self::format_number_cell( $cost_usd, 4 ),
                self::format_number_cell( $line_total_rmb, 4 ),
                $product_notes,
                $order_notes,
                $carton_number,
                self::format_number_cell( $cm3_per_unit, 4 ),
                self::format_number_cell( $line_cbm, 6 ),
            );

            $row_styles = array(
                null, // Image placeholder.
                3,    // SKU: wrap + text format preserved.
                null, // Brand.
                2,    // Product name wrap.
                2,    // Categories wrap.
                null, // MOQ.
                null, // Qty.
                null, // Unit price RMB.
                null, // Unit price USD.
                null, // Total RMB.
                2,    // Product notes wrap.
                null, // Order notes.
                null, // Carton no.
                null, // cm3 per unit.
                null, // Line CBM.
            );

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
        $sheet_xml     = self::build_sheet_xml( $sheet_rows_xml, ! empty( $images ) );
        $drawing_xml   = ! empty( $images ) ? self::build_drawing_xml( $images ) : '';
        $drawing_rels  = ! empty( $images ) ? self::build_drawing_rels_xml( $images ) : '';
        $app_xml       = self::build_app_xml();
        $core_xml      = self::build_core_xml();

        // Add files to ZIP.
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

    private static function esc_xml( $value ) {
        return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
    }

    private static function esc_xml_text( $value ) {
        $value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
        $value = htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
        return str_replace( "\n", '&#10;', $value );
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
        $xml = '<row r="' . (int) $row_num . '"' . ( $is_header ? '' : ' ht="45.35" customHeight="1"' ) . '>';
        $col_index = 0;
        foreach ( $cells as $cell_value ) {
            $col_letter = self::column_letter( $col_index ) . $row_num;
            $style_idx  = isset( $styles[ $col_index ] ) ? $styles[ $col_index ] : null;

            if ( is_numeric( $cell_value ) ) {
                $xml .= '<c r="' . $col_letter . '"' . ( null !== $style_idx ? ' s="' . (int) $style_idx . '"' : '' ) . '><v>' . $cell_value . '</v></c>';
            } else {
                $xml .= '<c r="' . $col_letter . '" t="inlineStr"' . ( null !== $style_idx ? ' s="' . (int) $style_idx . '"' : '' ) . '><is><t xml:space="preserve">' . self::esc_xml_text( $cell_value ) . '</t></is></c>';
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
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
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
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $xml .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
        $xml .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private static function build_workbook_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        $xml .= '<sheet name="Order Sheet" sheetId="1" r:id="rId1"/>';
        $xml .= '</sheets>';
        $xml .= '</workbook>';
        return $xml;
    }

    private static function build_workbook_rels_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
        $xml .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $xml . '</Relationships>';
    }

    private static function build_styles_xml() {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<fonts count="1"><font/></fonts>';
        $xml .= '<fills count="1"><fill/></fills>';
        $xml .= '<borders count="1"><border/></borders>';
        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $xml .= '<cellXfs count="4">';
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
        $xml .= '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'; // Text format.
        $xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
        $xml .= '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'; // Text + wrap.
        $xml .= '</cellXfs>';
        $xml .= '</styleSheet>';
        return $xml;
    }

    private static function build_sheet_rels_xml( $has_drawing ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        if ( $has_drawing ) {
            $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>';
        }
        $xml .= '</Relationships>';
        return $xml;
    }

    private static function build_sheet_xml( $rows_xml, $has_drawing ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= self::build_cols_xml();
        $xml .= '<sheetData>' . $rows_xml . '</sheetData>';
        if ( $has_drawing ) {
            $xml .= '<drawing r:id="rId1"/>';
        }
        $xml .= '</worksheet>';
        return $xml;
    }

    private static function build_cols_xml() {
        $xml  = '<cols>';
        $xml .= '<col min="2" max="2" width="10.34" customWidth="1"/>'; // SKU (B).
        $xml .= '<col min="4" max="4" width="32.60" customWidth="1"/>'; // Product name (D).
        $xml .= '<col min="5" max="5" width="32.60" customWidth="1"/>'; // Categories (E).
        $xml .= '<col min="11" max="11" width="27.15" customWidth="1"/>'; // Product notes (K).
        $xml .= '</cols>';
        return $xml;
    }

    private static function build_drawing_xml( $images ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $idx = 0;
        foreach ( $images as $img ) {
            $idx++;
            $xml .= '<xdr:oneCellAnchor>';
            $xml .= '<xdr:from><xdr:col>' . (int) $img['col'] . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . (int) $img['row'] . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>';
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
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
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
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
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

    private static function build_core_xml() {
        $now = gmdate( 'Y-m-d\\TH:i:s\\Z' );
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $xml .= '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>';
        $xml .= '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>';
        $xml .= '<dc:creator>Stock Order Plugin</dc:creator>';
        $xml .= '<cp:lastModifiedBy>Stock Order Plugin</cp:lastModifiedBy>';
        $xml .= '</cp:coreProperties>';
        return $xml;
    }
}

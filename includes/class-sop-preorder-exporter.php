<?php
/**
 * Stock Order Plugin - Preorder Excel Exporter
 * File version: 1.0.0
 *
 * Minimal XLSX builder (with image support) for saved Pre-Order sheets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOP_Preorder_Excel_Exporter {

    /**
     * Export a pre-order sheet to an XLSX file.
     *
     * @param array  $sheet    Header data.
     * @param array  $lines    Line rows.
     * @param string $filename Download filename (including .xlsx).
     * @return true|WP_Error
     */
    public static function export_sheet( $sheet, $lines, $filename ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'sop_export_zip_missing', __( 'ZipArchive not available for XLSX export.', 'sop' ) );
        }

        $zip = new ZipArchive();
        $tmp = wp_tempnam( 'sop-preorder-export' );
        if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE ) ) {
            return new WP_Error( 'sop_export_zip_open_failed', __( 'Failed to create XLSX export.', 'sop' ) );
        }

        $images          = array();
        $image_relations = array();
        $drawing_xml     = '';
        $drawing_rels    = '';

        // Prepare media files.
        $image_index = 1;
        foreach ( $lines as $idx => $line ) {
            $image_id = isset( $line['image_id'] ) ? (int) $line['image_id'] : 0;
            if ( $image_id <= 0 ) {
                continue;
            }

            $file_path = self::get_image_path( $image_id );
            if ( ! $file_path || ! file_exists( $file_path ) ) {
                continue;
            }

            $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
                continue;
            }

            $media_name            = 'image' . $image_index . '.' . $ext;
            $images[ $idx ]        = array(
                'path' => $file_path,
                'name' => $media_name,
            );
            $image_relations[ $idx ] = 'rIdImg' . $image_index;
            $image_index++;
        }

        // Worksheet XML with inline strings.
        $sheet_xml = self::build_sheet_xml( $lines, $images, $image_relations );

        // Drawing parts if we have images.
        if ( ! empty( $images ) ) {
            $drawing_xml  = self::build_drawing_xml( $images, $image_relations );
            $drawing_rels = self::build_drawing_rels( $images, $image_relations );
        }

        // Core XML parts.
        $zip->addFromString( '[Content_Types].xml', self::get_content_types_xml( ! empty( $images ) ) );
        $zip->addFromString( '_rels/.rels', self::get_root_rels_xml() );
        $zip->addFromString( 'xl/workbook.xml', self::get_workbook_xml() );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', self::get_workbook_rels_xml( ! empty( $images ) ) );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
        $zip->addFromString( 'xl/styles.xml', self::get_styles_xml() );
        $zip->addFromString( 'docProps/app.xml', self::get_app_xml() );
        $zip->addFromString( 'docProps/core.xml', self::get_core_xml() );

        if ( ! empty( $images ) ) {
            $zip->addFromString( 'xl/drawings/drawing1.xml', $drawing_xml );
            $zip->addFromString( 'xl/drawings/_rels/drawing1.xml.rels', $drawing_rels );
            foreach ( $images as $img ) {
                $zip->addFile( $img['path'], 'xl/media/' . $img['name'] );
            }
        }

        $zip->close();

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Transfer-Encoding: binary' );

        readfile( $tmp );
        @unlink( $tmp );

        return true;
    }

    /**
     * Build sheet XML with inline strings.
     */
    private static function build_sheet_xml( $lines, $images, $image_relations ) {
        $headers = array(
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

        $rows_xml = array();
        // Header row (r=1).
        $rows_xml[] = self::build_row_xml( 1, $headers, true );

        $row_num = 2;
        foreach ( $lines as $index => $line ) {
            $product_id   = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
            $sku          = isset( $line['sku'] ) ? $line['sku'] : '';
            $name         = isset( $line['product_name'] ) ? $line['product_name'] : '';
            $location     = isset( $line['location'] ) ? $line['location'] : '';
            $moq          = isset( $line['moq'] ) ? (float) $line['moq'] : 0;
            $soq          = isset( $line['soq'] ) ? (float) $line['soq'] : 0;
            $qty          = isset( $line['qty'] ) ? (float) $line['qty'] : 0;
            $cost         = isset( $line['cost_per_unit'] ) ? (float) $line['cost_per_unit'] : 0;
            $line_total   = isset( $line['line_total'] ) ? (float) $line['line_total'] : $qty * $cost;
            $notes        = isset( $line['notes'] ) ? $line['notes'] : '';

            $row_values = array(
                '', // Image placeholder.
                $product_id,
                $sku,
                $name,
                $location,
                $moq,
                $soq,
                $qty,
                $cost,
                $line_total,
                $notes,
            );

            $rows_xml[] = self::build_row_xml( $row_num, $row_values, false );
            $row_num++;
        }

        $drawing_tag = '';
        if ( ! empty( $images ) ) {
            $drawing_tag = '<drawing r:id="rId2"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $xml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $xml .= ' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';
        $xml .= ' mc:Ignorable="x14ac" xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac">';
        $xml .= '<sheetData>' . implode( '', $rows_xml ) . '</sheetData>';
        if ( $drawing_tag ) {
            $xml .= $drawing_tag;
        }
        $xml .= '</worksheet>';

        return $xml;
    }

    /**
     * Build a single row with inline strings.
     *
     * @param int   $row_num Row number.
     * @param array $values  Cell values.
     * @param bool  $is_header Whether this is header row.
     * @return string
     */
    private static function build_row_xml( $row_num, $values, $is_header = false ) {
        $cells = array();
        foreach ( $values as $col_index => $value ) {
            $cell_ref = self::col_num_to_name( $col_index + 1 ) . $row_num;
            $cells[]  = self::build_cell_xml( $cell_ref, $value, $is_header );
        }

        return '<row r="' . $row_num . '">' . implode( '', $cells ) . '</row>';
    }

    /**
     * Build a single cell.
     */
    private static function build_cell_xml( $ref, $value, $is_header ) {
        $type = '';
        if ( is_numeric( $value ) && ! $is_header ) {
            $type = '<v>' . $value . '</v>';
            return '<c r="' . $ref . '">' . $type . '</c>';
        }

        $escaped = htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
        return '<c r="' . $ref . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
    }

    /**
     * Build drawing XML (positions images).
     */
    private static function build_drawing_xml( $images, $image_relations ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">';

        $row = 2; // first data row.
        foreach ( $images as $idx => $img ) {
            $rId     = isset( $image_relations[ $idx ] ) ? $image_relations[ $idx ] : '';
            $emu_h   = 60 * 9525; // 60px height.
            $emu_w   = 60 * 9525; // simple square.
            $xml    .= '<xdr:twoCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . ( $row - 1 ) . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>1</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $row . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $row . '" name="Image ' . $row . '"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="' . esc_attr( $rId ) . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:ext cx="' . $emu_w . '" cy="' . $emu_h . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:twoCellAnchor>';
            $row++;
        }

        $xml .= '</xdr:wsDr>';
        return $xml;
    }

    /**
     * Build drawing relationships.
     */
    private static function build_drawing_rels( $images, $image_relations ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $index = 1;
        foreach ( $images as $idx => $img ) {
            $xml .= '<Relationship Id="' . $image_relations[ $idx ] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $img['name'] . '"/>';
            $index++;
        }
        $xml .= '</Relationships>';
        return $xml;
    }

    /**
     * Convert column number to Excel column name.
     */
    private static function col_num_to_name( $num ) {
        $name = '';
        while ( $num > 0 ) {
            $num--;
            $name = chr( 65 + ( $num % 26 ) ) . $name;
            $num  = (int) ( $num / 26 );
        }
        return $name;
    }

    private static function get_content_types_xml( $has_images ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        if ( $has_images ) {
            $xml .= '<Default Extension="png" ContentType="image/png"/>';
            $xml .= '<Default Extension="jpg" ContentType="image/jpeg"/>';
            $xml .= '<Default Extension="jpeg" ContentType="image/jpeg"/>';
        }
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $xml .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        if ( $has_images ) {
            $xml .= '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }
        $xml .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $xml .= '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        $xml .= '</Types>';
        return $xml;
    }

    private static function get_root_rels_xml() {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function get_workbook_xml() {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function get_workbook_rels_xml( $has_images ) {
        $rels = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
        if ( $has_images ) {
            $rels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="drawings/drawing1.xml"/>';
        }
        $rels .= '</Relationships>';
        return $rels;
    }

    private static function get_styles_xml() {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function get_app_xml() {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Stock Order Plugin</Application>'
            . '</Properties>';
    }

    private static function get_core_xml() {
        $now = gmdate( 'Y-m-d\TH:i:s\Z' );
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Stock Order Plugin</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dc:title>Preorder Sheet</dc:title>'
            . '</cp:coreProperties>';
    }

    /**
     * Resolve attachment path from ID.
     *
     * @param int $image_id Attachment ID.
     * @return string|false
     */
    private static function get_image_path( $image_id ) {
        $file = get_attached_file( $image_id );
        if ( $file && file_exists( $file ) ) {
            return $file;
        }

        return false;
    }
}

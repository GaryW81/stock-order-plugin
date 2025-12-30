<?php
/**
 * Stock Order Plugin - Labels & Barcodes core helpers
 * File version: 1.0.3
 *
 * Provides defaults, sanitization, and helper accessors for label settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_labels_get_default_settings' ) ) {
    /**
     * Defaults for label settings.
     *
     * @return array
     */
    function sop_labels_get_default_settings() {
        return array(
            'default_label_width_mm'  => 50,
            'default_label_height_mm' => 25,
            'include_qty'             => 1,
            'include_order_number'    => 1,
        );
    }
}

if ( ! function_exists( 'sop_labels_stream_preorder_labels_csv' ) ) {
    /**
     * Stream a Labels (CSV) export for a pre-order sheet.
     *
     * @param array  $sheet_header Sheet header data.
     * @param array  $line_rows    Line rows for export.
     * @param string $filename     Filename to send (optional, can be blank).
     */
    function sop_labels_stream_preorder_labels_csv( $sheet_header, $line_rows, $filename = '' ) {
        $sheet_header = is_array( $sheet_header ) ? $sheet_header : array();
        $line_rows    = is_array( $line_rows ) ? $line_rows : array();

        $sheet_id      = isset( $sheet_header['id'] ) ? (int) $sheet_header['id'] : 0;
        $order_number  = '';
        if ( ! empty( $sheet_header['order_number_label'] ) ) {
            $order_number = sanitize_text_field( (string) $sheet_header['order_number_label'] );
        }

        // Filename can still fall back to sheet_id for readability.
        $filename_ref = ( '' !== $order_number ) ? $order_number : ( ( $sheet_id > 0 ) ? (string) $sheet_id : 'sheet' );
        $filename     = ( '' !== $filename ) ? $filename : 'labels-' . $filename_ref . '.csv';

        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        }

        // Output BOM for Excel.
        echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) {
            return;
        }

        // Header row.
        fputcsv( $out, array( 'sku', 'product_name', 'qty', 'order_number' ) );

        foreach ( $line_rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $sku = isset( $row['sku'] ) ? (string) $row['sku'] : '';
            if ( '' === $sku ) {
                continue;
            }

            $qty = isset( $row['qty'] ) ? (float) $row['qty'] : 0;
            if ( $qty <= 0 ) {
                continue;
            }

            $product_name = isset( $row['product_name'] ) ? (string) $row['product_name'] : '';

            // Render qty as int when appropriate.
            $qty_out = ( (int) $qty === $qty ) ? (int) $qty : rtrim( rtrim( number_format( $qty, 4, '.', '' ), '0' ), '.' );

            fputcsv(
                $out,
                array(
                    $sku,
                    $product_name,
                    $qty_out,
                    $order_number,
                )
            );
        }

        fclose( $out );
    }
}

if ( ! function_exists( 'sop_labels_sanitize_settings' ) ) {
    /**
     * Sanitize label settings input.
     *
     * @param array $input Raw input.
     * @return array Clean settings.
     */
    function sop_labels_sanitize_settings( $input ) {
        $defaults = sop_labels_get_default_settings();
        $output   = $defaults;

        $input = is_array( $input ) ? $input : array();

        $min_dim = 10;
        $max_dim = 150;

        if ( isset( $input['default_label_width_mm'] ) ) {
            $width = (float) $input['default_label_width_mm'];
            if ( $width < $min_dim ) {
                $width = $min_dim;
            } elseif ( $width > $max_dim ) {
                $width = $max_dim;
            }
            $output['default_label_width_mm'] = $width;
        }

        if ( isset( $input['default_label_height_mm'] ) ) {
            $height = (float) $input['default_label_height_mm'];
            if ( $height < $min_dim ) {
                $height = $min_dim;
            } elseif ( $height > $max_dim ) {
                $height = $max_dim;
            }
            $output['default_label_height_mm'] = $height;
        }

        $output['include_qty'] = empty( $input['include_qty'] ) ? 0 : 1;
        if ( array_key_exists( 'include_order_number', $input ) ) {
            $output['include_order_number'] = empty( $input['include_order_number'] ) ? 0 : 1;
        } elseif ( array_key_exists( 'include_sheet_number', $input ) ) {
            // Backward compatibility for legacy key.
            $output['include_order_number'] = empty( $input['include_sheet_number'] ) ? 0 : 1;
        } else {
            $output['include_order_number'] = 1;
        }

        return $output;
    }
}

if ( ! function_exists( 'sop_labels_get_settings' ) ) {
    /**
     * Get labels settings merged with defaults.
     *
     * @return array
     */
    function sop_labels_get_settings() {
        $defaults = sop_labels_get_default_settings();
        $stored   = get_option( 'sop_labels_settings', array() );
        $stored   = is_array( $stored ) ? $stored : array();
        // Back-compat: map legacy include_sheet_number to include_order_number if needed.
        if ( ! isset( $stored['include_order_number'] ) && isset( $stored['include_sheet_number'] ) ) {
            $stored['include_order_number'] = ! empty( $stored['include_sheet_number'] ) ? 1 : 0;
            unset( $stored['include_sheet_number'] );
        }
        return wp_parse_args( $stored, $defaults );
    }
}

if ( ! function_exists( 'sop_labels_get_global_label_size_mm' ) ) {
    /**
     * Get global label size in mm.
     *
     * @return array{width_mm:float,height_mm:float}
     */
    function sop_labels_get_global_label_size_mm() {
        $settings = sop_labels_get_settings();
        return array(
            'width_mm'  => isset( $settings['default_label_width_mm'] ) ? (float) $settings['default_label_width_mm'] : 50.0,
            'height_mm' => isset( $settings['default_label_height_mm'] ) ? (float) $settings['default_label_height_mm'] : 25.0,
        );
    }
}

if ( ! function_exists( 'sop_labels_get_supplier_label_size_mm' ) ) {
    /**
     * Get supplier-specific label size, falling back to global defaults.
     *
     * @param int $supplier_id Supplier ID.
     * @return array{width_mm:float,height_mm:float}
     */
    function sop_labels_get_supplier_label_size_mm( $supplier_id ) {
        $supplier_id = (int) $supplier_id;
        $global      = sop_labels_get_global_label_size_mm();
        if ( $supplier_id <= 0 || ! function_exists( 'sop_supplier_get_by_id' ) ) {
            return $global;
        }

        $supplier = sop_supplier_get_by_id( $supplier_id );
        if ( ! $supplier || empty( $supplier->settings_json ) ) {
            return $global;
        }

        $settings = json_decode( $supplier->settings_json, true );
        if ( ! is_array( $settings ) ) {
            return $global;
        }

        $min_dim = 10;
        $max_dim = 150;
        $width   = isset( $settings['label_width_mm'] ) ? (float) $settings['label_width_mm'] : 0;
        $height  = isset( $settings['label_height_mm'] ) ? (float) $settings['label_height_mm'] : 0;

        if ( $width >= $min_dim && $width <= $max_dim ) {
            $global['width_mm'] = $width;
        }
        if ( $height >= $min_dim && $height <= $max_dim ) {
            $global['height_mm'] = $height;
        }

        return $global;
    }
}

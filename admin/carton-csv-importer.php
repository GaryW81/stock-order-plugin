<?php
/**
 * Stock Order Plugin - Phase 4.1 - Carton CSV Importer (admin only)
 * File version: 1.1.1
 * - 1.1.1 - Improve column guessing + preview UX; default annotation append off.
 * - 1.1.0 - Add dry run/undo support and safer carton parsing for supplier format.
 * - 1.0.0 - Initial carton CSV importer for saved preorder sheets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_carton_csv_importer_upload_dir' ) ) {
    function sop_carton_csv_importer_upload_dir( $dirs ) {
        $dirs['subdir'] = '/sop-imports';
        $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
        return $dirs;
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_get_allowed_supplier_ids' ) ) {
    function sop_carton_csv_importer_get_allowed_supplier_ids() {
        return array( 1, 4 );
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_is_sheet_allowed' ) ) {
    function sop_carton_csv_importer_is_sheet_allowed( array $sheet ) {
        $supplier_id = isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0;
        if ( ! in_array( $supplier_id, sop_carton_csv_importer_get_allowed_supplier_ids(), true ) ) {
            return false;
        }

        $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
        $status = strtolower( trim( $status ) );

        if ( function_exists( 'sop_get_preorder_sheet_stage_info' ) ) {
            $stage = sop_get_preorder_sheet_stage_info( $status );
            if ( ! empty( $stage['goods_in_started'] ) || 'completed' === $stage['stage_key'] ) {
                return false;
            }
        }

        if ( 'locked' === $status && function_exists( 'sop_preorder_sheet_has_goodsin_activity' ) ) {
            if ( sop_preorder_sheet_has_goodsin_activity( (int) $sheet['id'] ) ) {
                return false;
            }
        }

        return in_array( $status, array( 'draft', 'locked' ), true );
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_get_allowed_sheets' ) ) {
    function sop_carton_csv_importer_get_allowed_sheets() {
        if ( ! function_exists( 'sop_preorder_get_sheets_all' ) ) {
            return array();
        }

        $raw = sop_preorder_get_sheets_all(
            array( 'draft', 'locked', 'receiving', 'received', 'completed', 'complete', 'closed' )
        );

        $out = array();
        foreach ( $raw as $sheet ) {
            if ( sop_carton_csv_importer_is_sheet_allowed( $sheet ) ) {
                $out[] = $sheet;
            }
        }

        return $out;
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_get_sheet_label' ) ) {
    function sop_carton_csv_importer_get_sheet_label( array $sheet ) {
        $supplier_label = isset( $sheet['supplier_id'] ) ? (string) $sheet['supplier_id'] : '';
        if ( function_exists( 'sop_get_supplier_label' ) ) {
            $supplier_label = sop_get_supplier_label( (int) $sheet['supplier_id'] );
        }

        $order_label = isset( $sheet['order_number_label'] ) ? (string) $sheet['order_number_label'] : '';
        $order_label = ( '' !== $order_label ) ? $order_label : __( 'No order number', 'sop' );

        return sprintf(
            '#%d - %s - %s',
            (int) $sheet['id'],
            (string) $supplier_label,
            (string) $order_label
        );
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_upload_csv' ) ) {
    function sop_carton_csv_importer_upload_csv( $file ) {
        if ( empty( $file ) || empty( $file['name'] ) ) {
            return new WP_Error( 'sop_carton_csv_upload_missing', __( 'No file was uploaded.', 'sop' ) );
        }

        add_filter( 'upload_dir', 'sop_carton_csv_importer_upload_dir' );
        $overrides = array(
            'test_form' => false,
            'mimes'     => array(
                'csv' => 'text/csv',
                'txt' => 'text/plain',
            ),
        );

        $uploaded = wp_handle_upload( $file, $overrides );
        remove_filter( 'upload_dir', 'sop_carton_csv_importer_upload_dir' );

        if ( isset( $uploaded['error'] ) ) {
            return new WP_Error( 'sop_carton_csv_upload_failed', (string) $uploaded['error'] );
        }

        if ( empty( $uploaded['file'] ) ) {
            return new WP_Error( 'sop_carton_csv_upload_failed', __( 'Upload failed.', 'sop' ) );
        }

        return $uploaded['file'];
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_is_safe_upload_path' ) ) {
    function sop_carton_csv_importer_is_safe_upload_path( $file_path ) {
        $uploads = wp_upload_dir();
        $base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
        if ( '' === $base ) {
            return false;
        }

        $target_dir = $base . '/sop-imports';
        $real_base  = realpath( $target_dir );
        $real_file  = realpath( (string) $file_path );

        if ( false === $real_base || false === $real_file ) {
            return false;
        }

        return ( 0 === strpos( $real_file, $real_base ) );
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_normalize_header' ) ) {
    function sop_carton_csv_importer_normalize_header( $value ) {
        $value = strtolower( trim( (string) $value ) );
        $value = preg_replace( '/\s+/', ' ', $value );
        $value = str_replace( array( '_', '-', ' ' ), '', $value );
        $value = preg_replace( '/[^a-z0-9]/', '', $value );
        return $value;
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_is_header_row' ) ) {
    function sop_carton_csv_importer_is_header_row( array $row ) {
        $candidates = array(
            'id',
            'productid',
            'product_id',
            'product id',
            'carton',
            'cartonno',
            'carton_no',
            'carton no',
            'carton number',
            'cartons',
            'ordernotes',
            'order_notes',
            'order notes',
            'notes',
            'remarks',
        );

        foreach ( $row as $cell ) {
            $cell_norm = sop_carton_csv_importer_normalize_header( $cell );
            if ( '' === $cell_norm ) {
                continue;
            }
            foreach ( $candidates as $candidate ) {
                if ( $cell_norm === sop_carton_csv_importer_normalize_header( $candidate ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_read_csv' ) ) {
    function sop_carton_csv_importer_read_csv( $file_path, $max_rows = 0 ) {
        $file_path = (string) $file_path;
        if ( '' === $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error( 'sop_carton_csv_missing', __( 'CSV file not found.', 'sop' ) );
        }

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'sop_carton_csv_open_failed', __( 'Unable to read CSV file.', 'sop' ) );
        }

        $headers = array();
        $rows    = array();
        $has_header = false;

        $first = fgetcsv( $handle );
        if ( false === $first ) {
            fclose( $handle );
            return new WP_Error( 'sop_carton_csv_empty', __( 'CSV file appears to be empty.', 'sop' ) );
        }

        if ( isset( $first[0] ) ) {
            $first[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $first[0] );
        }

        $has_header = sop_carton_csv_importer_is_header_row( $first );
        if ( $has_header ) {
            $headers = $first;
        } else {
            $headers = array();
            foreach ( $first as $idx => $value ) {
                $headers[] = sprintf( __( 'Column %d', 'sop' ), ( $idx + 1 ) );
            }
            $rows[] = $first;
        }

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( $max_rows > 0 && count( $rows ) >= $max_rows ) {
                break;
            }
            $rows[] = $row;
        }

        fclose( $handle );

        return array(
            'headers'    => $headers,
            'rows'       => $rows,
            'has_header' => $has_header,
        );
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_guess_mappings' ) ) {
    function sop_carton_csv_importer_guess_mappings( array $headers, $has_header ) {
        $guess = array(
            'product_id' => -1,
            'carton_no'  => -1,
            'notes'      => -1,
        );

        if ( ! $has_header ) {
            return $guess;
        }

        $header_norm = array();
        foreach ( $headers as $idx => $header ) {
            $header_norm[ $idx ] = sop_carton_csv_importer_normalize_header( $header );
        }

        $product_candidates = array( 'id', 'productid', 'product_id', 'productid' );
        $carton_candidates  = array( 'carton', 'cartonno', 'carton_no', 'cartonnumber', 'cartons' );
        $notes_candidates   = array( 'ordernotes', 'order_notes', 'ordernote', 'notes', 'remarks' );

        foreach ( $header_norm as $idx => $header ) {
            if ( -1 === $guess['product_id'] && in_array( $header, $product_candidates, true ) ) {
                $guess['product_id'] = $idx;
            }
            if ( -1 === $guess['carton_no'] && in_array( $header, $carton_candidates, true ) ) {
                $guess['carton_no'] = $idx;
            }
            if ( -1 === $guess['notes'] && in_array( $header, $notes_candidates, true ) ) {
                $guess['notes'] = $idx;
            }
        }

        return $guess;
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_extract_cell' ) ) {
    function sop_carton_csv_importer_extract_cell( array $row, $index ) {
        if ( $index < 0 ) {
            return '';
        }
        return isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_append_notes' ) ) {
    function sop_carton_csv_importer_append_notes( $existing, $append ) {
        $existing = (string) $existing;
        $append   = (string) $append;
        $append   = trim( $append );

        if ( '' === $append ) {
            return array( 'notes' => $existing, 'appended' => false );
        }

        if ( '' !== $existing && false !== strpos( $existing, $append ) ) {
            return array( 'notes' => $existing, 'appended' => false );
        }

        $new_notes = ( '' === $existing ) ? $append : ( $existing . "\n" . $append );
        return array( 'notes' => $new_notes, 'appended' => true );
    }
}

if ( ! function_exists( 'sop_normalize_carton_and_annotation' ) ) {
    function sop_normalize_carton_and_annotation( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return array(
                'carton'    => '',
                'annotation'=> '',
                'invalid'   => false,
            );
        }

        $original = $raw;
        $working  = $raw;

        $working = preg_replace( '/\bno\.?\b/i', '', $working );
        $working = str_replace( array( "\r\n", "\r", "\n" ), ',', $working );
        $working = str_replace( array( "\xE2\x80\x93", "\xE2\x80\x94" ), '-', $working );
        $working = preg_replace( '/(\d)\s*\/\s*(\d)/', '$1-$2', $working );
        $working = preg_replace( '/\s+/', ' ', $working );

        $numbers = array();
        $ranges  = array();
        $annotation_parts = array();

        if ( preg_match_all( '/(\d+)\s*each\b/i', $working, $each_matches, PREG_SET_ORDER ) ) {
            foreach ( $each_matches as $match ) {
                $annotation_parts[] = trim( $match[0] );
            }
            $working = preg_replace( '/(\d+)\s*each\b/i', '', $working );
        }

        if ( preg_match_all( '/(\d{3,})\s+(\d{1,3})(?=\D|$)/', $working, $qty_matches, PREG_SET_ORDER ) ) {
            foreach ( $qty_matches as $match ) {
                $carton_num = (int) $match[1];
                $qty_count  = (int) $match[2];
                if ( $carton_num > 0 && $qty_count > 0 ) {
                    $annotation_parts[] = $carton_num . ' = ' . $qty_count . 'pcs';
                }
            }
            $working = preg_replace( '/(\d{3,})\s+(\d{1,3})(?=\D|$)/', '$1', $working );
        }

        if ( preg_match_all( '/(\d+)\s*(\d+)\s*pcs\b/i', $working, $pcs_matches, PREG_SET_ORDER ) ) {
            foreach ( $pcs_matches as $match ) {
                $carton_num = (int) $match[1];
                $pcs_count  = (int) $match[2];
                if ( $carton_num > 0 ) {
                    $numbers[] = $carton_num;
                }
                if ( $carton_num > 0 && $pcs_count > 0 ) {
                    $annotation_parts[] = $carton_num . ' = ' . $pcs_count . 'pcs';
                }
            }
            $working = preg_replace( '/(\d+)\s*(\d+)\s*pcs\b/i', '', $working );
        }

        if ( preg_match_all( '/(\d+)\s*-\s*(\d+)/', $working, $range_matches, PREG_SET_ORDER ) ) {
            foreach ( $range_matches as $match ) {
                $start = (int) $match[1];
                $end   = (int) $match[2];
                if ( $start > 0 && $end > 0 ) {
                    $ranges[] = array( min( $start, $end ), max( $start, $end ) );
                }
            }
            $working = preg_replace( '/(\d+)\s*-\s*(\d+)/', '', $working );
        }

        if ( preg_match_all( '/\d+/', $working, $num_matches ) ) {
            foreach ( $num_matches[0] as $num ) {
                $val = (int) $num;
                if ( $val > 0 ) {
                    $numbers[] = $val;
                }
            }
            $working = preg_replace( '/\d+/', '', $working );
        }

        $annotation_text = trim( preg_replace( '/\s+/', ' ', $working ) );
        $annotation_text = trim( str_replace( array( ',', '-' ), ' ', $annotation_text ) );
        $annotation_text = trim( preg_replace( '/\s+/', ' ', $annotation_text ) );
        if ( '' !== $annotation_text ) {
            $annotation_parts[] = $annotation_text;
        }

        $intervals = array();
        foreach ( $numbers as $num ) {
            $intervals[] = array( $num, $num );
        }
        foreach ( $ranges as $range ) {
            $intervals[] = array( (int) $range[0], (int) $range[1] );
        }

        $has_digits = (bool) preg_match( '/\d/', $original );
        if ( empty( $intervals ) ) {
            return array(
                'carton'     => '',
                'annotation' => implode( '; ', array_filter( $annotation_parts ) ),
                'invalid'    => $has_digits,
            );
        }

        usort(
            $intervals,
            function ( $a, $b ) {
                if ( $a[0] === $b[0] ) {
                    return $a[1] <=> $b[1];
                }
                return $a[0] <=> $b[0];
            }
        );

        $merged = array();
        foreach ( $intervals as $interval ) {
            if ( empty( $merged ) ) {
                $merged[] = $interval;
                continue;
            }
            $last_index = count( $merged ) - 1;
            $last       = $merged[ $last_index ];
            if ( $interval[0] <= ( $last[1] + 1 ) ) {
                $merged[ $last_index ][1] = max( $last[1], $interval[1] );
            } else {
                $merged[] = $interval;
            }
        }

        $tokens = array();
        foreach ( $merged as $interval ) {
            $start = (int) $interval[0];
            $end   = (int) $interval[1];
            if ( $start === $end ) {
                $tokens[] = (string) $start;
            } else {
                $tokens[] = $start . '-' . $end;
            }
        }

        $carton = implode( ',', $tokens );
        $valid  = (bool) preg_match( '/^\d+(?:-\d+)?(?:,\d+(?:-\d+)?)*$/', $carton );

        return array(
            'carton'     => $valid ? $carton : '',
            'annotation' => implode( '; ', array_filter( $annotation_parts ) ),
            'invalid'    => ! $valid,
        );
    }
}

if ( ! function_exists( 'sop_render_carton_csv_import_page' ) ) {
    function sop_render_carton_csv_import_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this tool.', 'sop' ) );
        }

        $sheets   = sop_carton_csv_importer_get_allowed_sheets();
        $notices  = array();
        $errors   = array();
        $results  = array();
        $state    = array(
            'step'      => 'load',
            'sheet_id'  => 0,
            'file_path' => '',
            'headers'   => array(),
            'rows'      => array(),
            'has_header'=> false,
        );

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $action = isset( $_POST['sop_carton_csv_action'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_carton_csv_action'] ) ) : '';
            $nonce  = isset( $_POST['sop_carton_csv_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_carton_csv_nonce'] ) ) : '';

            if ( ! wp_verify_nonce( $nonce, 'sop_carton_csv_import' ) ) {
                $errors[] = __( 'Security check failed. Please try again.', 'sop' );
            } elseif ( 'load' === $action ) {
                $sheet_id = isset( $_POST['sop_carton_sheet_id'] ) ? (int) $_POST['sop_carton_sheet_id'] : 0;
                $state['sheet_id'] = $sheet_id;

                $sheet = null;
                foreach ( $sheets as $candidate ) {
                    if ( (int) $candidate['id'] === $sheet_id ) {
                        $sheet = $candidate;
                        break;
                    }
                }

                if ( ! $sheet ) {
                    $errors[] = __( 'Selected sheet is not available for import.', 'sop' );
                } else {
                    $upload = sop_carton_csv_importer_upload_csv( isset( $_FILES['sop_carton_csv_file'] ) ? $_FILES['sop_carton_csv_file'] : array() );
                    if ( is_wp_error( $upload ) ) {
                        $errors[] = $upload->get_error_message();
                    } else {
                        $state['file_path'] = $upload;
                        $parsed = sop_carton_csv_importer_read_csv( $upload, 10 );
                        if ( is_wp_error( $parsed ) ) {
                            $errors[] = $parsed->get_error_message();
                            if ( '' !== $state['file_path'] && file_exists( $state['file_path'] ) ) {
                                @unlink( $state['file_path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                            }
                            $state['file_path'] = '';
                        } else {
                            $state['headers']    = $parsed['headers'];
                            $state['rows']       = $parsed['rows'];
                            $state['has_header'] = $parsed['has_header'];
                            $state['step']       = 'map';
                        }
                    }
                }
            } elseif ( 'import' === $action || 'preview' === $action ) {
                $sheet_id = isset( $_POST['sop_carton_sheet_id'] ) ? (int) $_POST['sop_carton_sheet_id'] : 0;
                $state['sheet_id'] = $sheet_id;

                $sheet = null;
                foreach ( $sheets as $candidate ) {
                    if ( (int) $candidate['id'] === $sheet_id ) {
                        $sheet = $candidate;
                        break;
                    }
                }

                if ( ! $sheet ) {
                    $errors[] = __( 'Selected sheet is not available for import.', 'sop' );
                } else {
                    $file_path = isset( $_POST['sop_carton_csv_file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_carton_csv_file_path'] ) ) : '';
                    if ( '' === $file_path || ! sop_carton_csv_importer_is_safe_upload_path( $file_path ) ) {
                        $errors[] = __( 'Uploaded CSV file could not be verified.', 'sop' );
                    } else {
                        $parsed = sop_carton_csv_importer_read_csv( $file_path );
                        if ( is_wp_error( $parsed ) ) {
                            $errors[] = $parsed->get_error_message();
                        } else {
                            $rows    = $parsed['rows'];

                            $product_col = isset( $_POST['sop_map_product_id'] ) ? (int) $_POST['sop_map_product_id'] : -1;
                            $carton_col  = isset( $_POST['sop_map_carton_no'] ) ? (int) $_POST['sop_map_carton_no'] : -1;
                            $notes_col   = isset( $_POST['sop_map_order_notes'] ) ? (int) $_POST['sop_map_order_notes'] : -1;

                            $overwrite_carton = ! empty( $_POST['sop_carton_overwrite'] );
                            $append_notes     = ! empty( $_POST['sop_carton_append_notes'] );
                            $dry_run          = ! empty( $_POST['sop_carton_dry_run'] );

                            if ( $product_col < 0 ) {
                                $errors[] = __( 'Please map the Product ID column before importing.', 'sop' );
                            } else {
                                $lines = function_exists( 'sop_get_preorder_sheet_lines' )
                                    ? sop_get_preorder_sheet_lines( $sheet_id )
                                    : array();
                                $lines = is_array( $lines ) ? $lines : array();

                                $line_index = array();
                                foreach ( $lines as $idx => $line ) {
                                    $pid = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
                                    if ( $pid > 0 ) {
                                        $line_index[ $pid ] = $idx;
                                    }
                                }

                                $processed_rows = 0;
                                $updated_carton = 0;
                                $notes_appended = 0;
                                $skipped_rows   = 0;
                                $not_found      = array();
                                $invalid_carton = array();
                                $backup_changes = array();

                                foreach ( $rows as $row ) {
                                    $processed_rows++;
                                    $product_id_raw = sop_carton_csv_importer_extract_cell( $row, $product_col );
                                    $product_id     = (int) $product_id_raw;

                                    if ( $product_id <= 0 ) {
                                        $skipped_rows++;
                                        continue;
                                    }

                                    if ( ! isset( $line_index[ $product_id ] ) ) {
                                        $not_found[] = $product_id;
                                        continue;
                                    }

                                    $carton_raw = sop_carton_csv_importer_extract_cell( $row, $carton_col );
                                    $notes_raw  = sop_carton_csv_importer_extract_cell( $row, $notes_col );

                                    $notes_raw  = sanitize_textarea_field( $notes_raw );
                                    $carton_raw = trim( (string) $carton_raw );

                                    $normalized = sop_normalize_carton_and_annotation( $carton_raw );
                                    if ( ! empty( $normalized['invalid'] ) ) {
                                        $invalid_carton[] = array(
                                            'product_id' => $product_id,
                                            'raw'        => $carton_raw,
                                        );
                                    }

                                    $annotation = (string) $normalized['annotation'];
                                    $carton     = (string) $normalized['carton'];

                                    $notes_parts = array();
                                    if ( '' !== $notes_raw ) {
                                        $notes_parts[] = $notes_raw;
                                    }
                                    if ( $append_notes && '' !== $annotation ) {
                                        $notes_parts[] = $annotation;
                                    }
                                    $notes_to_append = trim( implode( "\n", array_filter( $notes_parts ) ) );

                                    $line_idx = $line_index[ $product_id ];
                                    $line     = $lines[ $line_idx ];

                                    $changed = false;

                                    if ( '' !== $carton ) {
                                        $existing_carton = isset( $line['carton_no'] ) ? (string) $line['carton_no'] : '';
                                        if ( $overwrite_carton || '' === $existing_carton ) {
                                            if ( ! isset( $backup_changes[ $product_id ] ) ) {
                                                $backup_changes[ $product_id ] = array(
                                                    'carton_no'         => $existing_carton,
                                                    'order_notes_owner' => isset( $line['order_notes_owner'] ) ? (string) $line['order_notes_owner'] : '',
                                                );
                                            }
                                            $line['carton_no'] = $carton;
                                            $updated_carton++;
                                            $changed = true;
                                        }
                                    }

                                    if ( '' !== $notes_to_append ) {
                                        $existing_notes = isset( $line['order_notes_owner'] ) ? (string) $line['order_notes_owner'] : '';
                                        $append_result  = sop_carton_csv_importer_append_notes( $existing_notes, $notes_to_append );
                                        $line['order_notes_owner'] = $append_result['notes'];
                                        if ( $append_result['appended'] ) {
                                            if ( ! isset( $backup_changes[ $product_id ] ) ) {
                                                $backup_changes[ $product_id ] = array(
                                                    'carton_no'         => isset( $line['carton_no'] ) ? (string) $line['carton_no'] : '',
                                                    'order_notes_owner' => $existing_notes,
                                                );
                                            }
                                            $notes_appended++;
                                            $changed = true;
                                        }
                                    }

                                    if ( $changed ) {
                                        $lines[ $line_idx ] = $line;
                                    } else {
                                        $skipped_rows++;
                                    }
                                }

                                if ( empty( $errors ) ) {
                                    if ( 'preview' === $action ) {
                                        $state['step'] = 'map';
                                        $state['file_path'] = $file_path;
                                        $state['headers'] = $parsed['headers'];
                                        $state['rows'] = $parsed['rows'];
                                        $state['has_header'] = $parsed['has_header'];
                                        $notices[] = __( 'Preview updated. No changes were saved.', 'sop' );
                                    } elseif ( $dry_run ) {
                                        $results = array(
                                            'processed'     => $processed_rows,
                                            'updated_carton'=> $updated_carton,
                                            'notes_appended'=> $notes_appended,
                                            'skipped'       => $skipped_rows,
                                            'not_found'     => $not_found,
                                            'invalid'       => $invalid_carton,
                                        );
                                        $notices[] = __( 'Dry run complete. No changes were saved.', 'sop' );
                                    } elseif ( function_exists( 'sop_insert_preorder_sheet_lines' ) ) {
                                        $save = sop_insert_preorder_sheet_lines( $sheet_id, $lines );
                                        if ( is_wp_error( $save ) ) {
                                            $errors[] = $save->get_error_message();
                                        } else {
                                            if ( function_exists( 'sop_update_preorder_sheet' ) ) {
                                                sop_update_preorder_sheet(
                                                    $sheet_id,
                                                    array(
                                                        'updated_at' => current_time( 'mysql', true ),
                                                    )
                                                );
                                            }

                                            $results = array(
                                                'processed'     => $processed_rows,
                                                'updated_carton'=> $updated_carton,
                                                'notes_appended'=> $notes_appended,
                                                'skipped'       => $skipped_rows,
                                                'not_found'     => $not_found,
                                                'invalid'       => $invalid_carton,
                                            );
                                            $notices[] = __( 'Import complete.', 'sop' );
                                            if ( ! empty( $backup_changes ) ) {
                                                update_option(
                                                    'sop_carton_import_backup_' . $sheet_id,
                                                    array(
                                                        'created_at' => current_time( 'mysql', true ),
                                                        'user_id'    => get_current_user_id(),
                                                        'changes'    => $backup_changes,
                                                    ),
                                                    false
                                                );
                                            } else {
                                                delete_option( 'sop_carton_import_backup_' . $sheet_id );
                                            }
                                        }
                                    } else {
                                        $errors[] = __( 'Sheet line helper is unavailable.', 'sop' );
                                    }
                                }
                            }
                        }
                    }

                    if ( 'preview' !== $action && '' !== $file_path && file_exists( $file_path ) ) {
                        @unlink( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    }
                }
            } elseif ( 'undo' === $action ) {
                $sheet_id = isset( $_POST['sop_carton_sheet_id'] ) ? (int) $_POST['sop_carton_sheet_id'] : 0;
                $state['sheet_id'] = $sheet_id;
                $backup = get_option( 'sop_carton_import_backup_' . $sheet_id );
                if ( empty( $backup['changes'] ) || ! is_array( $backup['changes'] ) ) {
                    $errors[] = __( 'No backup data found to undo.', 'sop' );
                } elseif ( function_exists( 'sop_get_preorder_sheet_lines' ) ) {
                    $lines = sop_get_preorder_sheet_lines( $sheet_id );
                    $lines = is_array( $lines ) ? $lines : array();
                    $line_index = array();
                    foreach ( $lines as $idx => $line ) {
                        $pid = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
                        if ( $pid > 0 ) {
                            $line_index[ $pid ] = $idx;
                        }
                    }
                    foreach ( $backup['changes'] as $pid => $original ) {
                        $pid = (int) $pid;
                        if ( $pid <= 0 || ! isset( $line_index[ $pid ] ) ) {
                            continue;
                        }
                        $line_idx = $line_index[ $pid ];
                        $lines[ $line_idx ]['carton_no'] = isset( $original['carton_no'] ) ? (string) $original['carton_no'] : '';
                        $lines[ $line_idx ]['order_notes_owner'] = isset( $original['order_notes_owner'] ) ? (string) $original['order_notes_owner'] : '';
                    }
                    if ( function_exists( 'sop_insert_preorder_sheet_lines' ) ) {
                        $save = sop_insert_preorder_sheet_lines( $sheet_id, $lines );
                        if ( is_wp_error( $save ) ) {
                            $errors[] = $save->get_error_message();
                        } else {
                            if ( function_exists( 'sop_update_preorder_sheet' ) ) {
                                sop_update_preorder_sheet(
                                    $sheet_id,
                                    array(
                                        'updated_at' => current_time( 'mysql', true ),
                                    )
                                );
                            }
                            delete_option( 'sop_carton_import_backup_' . $sheet_id );
                            $notices[] = __( 'Undo complete.', 'sop' );
                        }
                    }
                } else {
                    $errors[] = __( 'Sheet line helper is unavailable.', 'sop' );
                }
            }
        }

        $state['sheet_id'] = $state['sheet_id'] ? $state['sheet_id'] : ( isset( $_GET['sop_sheet_id'] ) ? (int) $_GET['sop_sheet_id'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Carton CSV Import', 'sop' ) . '</h1>';
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'This tool updates carton numbers and order notes for saved pre-order sheets. Only suppliers Shiny (1) and BSE (4) are supported.', 'sop' ) . '</p></div>';

        foreach ( $errors as $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        }
        foreach ( $notices as $notice ) {
            echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
        }

        if ( ! empty( $results ) ) {
            echo '<h2>' . esc_html__( 'Import summary', 'sop' ) . '</h2>';
            echo '<ul>';
            echo '<li>' . esc_html__( 'Rows processed:', 'sop' ) . ' ' . esc_html( (string) $results['processed'] ) . '</li>';
            echo '<li>' . esc_html__( 'Cartons updated:', 'sop' ) . ' ' . esc_html( (string) $results['updated_carton'] ) . '</li>';
            echo '<li>' . esc_html__( 'Notes appended:', 'sop' ) . ' ' . esc_html( (string) $results['notes_appended'] ) . '</li>';
            echo '<li>' . esc_html__( 'Rows skipped:', 'sop' ) . ' ' . esc_html( (string) $results['skipped'] ) . '</li>';
            echo '</ul>';

            if ( ! empty( $results['not_found'] ) ) {
                $not_found = array_slice( array_unique( $results['not_found'] ), 0, 20 );
                echo '<p><strong>' . esc_html__( 'Not found in sheet:', 'sop' ) . '</strong> ' . esc_html( implode( ', ', $not_found ) ) . '</p>';
            }

            if ( ! empty( $results['invalid'] ) ) {
                $invalid = array_slice( $results['invalid'], 0, 20 );
                echo '<p><strong>' . esc_html__( 'Invalid carton values:', 'sop' ) . '</strong></p>';
                echo '<ul>';
                foreach ( $invalid as $row ) {
                    echo '<li>' . esc_html( $row['product_id'] ) . ': ' . esc_html( $row['raw'] ) . '</li>';
                }
                echo '</ul>';
            }
        }

        $sheet_options = $sheets;
        $selected_sheet_id = (int) $state['sheet_id'];
        $undo_backup = $selected_sheet_id ? get_option( 'sop_carton_import_backup_' . $selected_sheet_id ) : null;

        echo '<h2>' . esc_html__( 'Step 1 - Select sheet and upload CSV', 'sop' ) . '</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( 'sop_carton_csv_import', 'sop_carton_csv_nonce' );
        echo '<input type="hidden" name="sop_carton_csv_action" value="load" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="sop_carton_sheet_id">' . esc_html__( 'Saved sheet', 'sop' ) . '</label></th><td>';
        echo '<select name="sop_carton_sheet_id" id="sop_carton_sheet_id" required>';
        echo '<option value="">' . esc_html__( 'Select a sheet', 'sop' ) . '</option>';
        foreach ( $sheet_options as $sheet ) {
            $label = sop_carton_csv_importer_get_sheet_label( $sheet );
            $selected = (int) $sheet['id'] === $selected_sheet_id ? 'selected' : '';
            echo '<option value="' . esc_attr( (int) $sheet['id'] ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="sop_carton_csv_file">' . esc_html__( 'CSV file', 'sop' ) . '</label></th><td>';
        echo '<input type="file" name="sop_carton_csv_file" id="sop_carton_csv_file" accept=".csv" required />';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button( __( 'Load file', 'sop' ) );
        echo '</form>';

        if ( $selected_sheet_id && ! empty( $undo_backup['changes'] ) ) {
            echo '<h2>' . esc_html__( 'Undo last import', 'sop' ) . '</h2>';
            echo '<form method="post">';
            wp_nonce_field( 'sop_carton_csv_import', 'sop_carton_csv_nonce' );
            echo '<input type="hidden" name="sop_carton_csv_action" value="undo" />';
            echo '<input type="hidden" name="sop_carton_sheet_id" value="' . esc_attr( $selected_sheet_id ) . '" />';
            submit_button( __( 'Undo last import', 'sop' ), 'secondary' );
            echo '</form>';
        }

        if ( 'map' === $state['step'] && ! empty( $state['file_path'] ) ) {
            $file_path = $state['file_path'];
            $parsed    = sop_carton_csv_importer_read_csv( $file_path, 10 );
            if ( is_wp_error( $parsed ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $parsed->get_error_message() ) . '</p></div>';
            } else {
                $headers = $parsed['headers'];
                $rows    = $parsed['rows'];
                $guess   = sop_carton_csv_importer_guess_mappings( $headers, $parsed['has_header'] );

                echo '<h2>' . esc_html__( 'Step 2 - Map columns and import', 'sop' ) . '</h2>';
                echo '<form method="post">';
                wp_nonce_field( 'sop_carton_csv_import', 'sop_carton_csv_nonce' );
                echo '<input type="hidden" name="sop_carton_sheet_id" value="' . esc_attr( $selected_sheet_id ) . '" />';
                echo '<input type="hidden" name="sop_carton_csv_file_path" value="' . esc_attr( $file_path ) . '" />';

                echo '<table class="form-table"><tbody>';
                echo '<tr><th scope="row">' . esc_html__( 'Product ID column', 'sop' ) . '</th><td>';
                $preview_product_col = isset( $_POST['sop_map_product_id'] ) ? (int) $_POST['sop_map_product_id'] : $guess['product_id'];
                $preview_carton_col  = isset( $_POST['sop_map_carton_no'] ) ? (int) $_POST['sop_map_carton_no'] : $guess['carton_no'];
                $preview_notes_col   = isset( $_POST['sop_map_order_notes'] ) ? (int) $_POST['sop_map_order_notes'] : $guess['notes'];
                $preview_append_annotations = ! empty( $_POST['sop_carton_append_notes'] );
                $preview_dry_run = array_key_exists( 'sop_carton_dry_run', $_POST ) ? ! empty( $_POST['sop_carton_dry_run'] ) : true;
                echo sop_carton_csv_importer_render_column_select( 'sop_map_product_id', $headers, $preview_product_col, false );
                echo '</td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Carton no. column', 'sop' ) . '</th><td>';
                echo sop_carton_csv_importer_render_column_select( 'sop_map_carton_no', $headers, $preview_carton_col, true );
                echo '</td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Order notes column', 'sop' ) . '</th><td>';
                echo sop_carton_csv_importer_render_column_select( 'sop_map_order_notes', $headers, $preview_notes_col, true );
                echo '</td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Options', 'sop' ) . '</th><td>';
                echo '<label><input type="checkbox" name="sop_carton_overwrite" value="1" /> ' . esc_html__( 'Overwrite existing carton values', 'sop' ) . '</label><br />';
                $append_checked = $preview_append_annotations ? 'checked' : '';
                $dry_run_checked = $preview_dry_run ? 'checked' : '';
                echo '<label><input type="checkbox" name="sop_carton_append_notes" value="1" ' . $append_checked . ' /> ' . esc_html__( 'Append carton annotations into Order notes', 'sop' ) . '</label>';
                echo '<p class="description">' . esc_html__( 'If your Carton no. column contains extra info (e.g. “1555 = 40pcs”, “10 EACH”, “Handlebar”), this will append that extra text into Order notes. Leave this OFF if you already mapped an Order notes column or you only want carton numbers saved.', 'sop' ) . '</p>';
                echo '<label><input type="checkbox" name="sop_carton_dry_run" value="1" ' . $dry_run_checked . ' /> ' . esc_html__( 'Dry run (no changes saved)', 'sop' ) . '</label>';
                echo '</td></tr>';
                echo '</tbody></table>';

                echo '<h3>' . esc_html__( 'Preview (first 10 rows)', 'sop' ) . '</h3>';
                echo '<table class="widefat striped"><thead><tr>';
                echo '<th>' . esc_html__( 'Product ID', 'sop' ) . '</th>';
                echo '<th>' . esc_html__( 'Raw carton', 'sop' ) . '</th>';
                echo '<th>' . esc_html__( 'Normalized carton', 'sop' ) . '</th>';
                echo '<th>' . esc_html__( 'Notes to append', 'sop' ) . '</th>';
                echo '</tr></thead><tbody>';

                $preview_rows = array_slice( $rows, 0, 10 );
                foreach ( $preview_rows as $row ) {
                    $product_id = sop_carton_csv_importer_extract_cell( $row, $preview_product_col );
                    $carton_raw = sop_carton_csv_importer_extract_cell( $row, $preview_carton_col );
                    $notes_raw  = sop_carton_csv_importer_extract_cell( $row, $preview_notes_col );

                    $normalized = sop_normalize_carton_and_annotation( $carton_raw );
                    $notes_parts = array();
                    if ( '' !== $notes_raw ) {
                        $notes_parts[] = sanitize_textarea_field( $notes_raw );
                    }
                    if ( $preview_append_annotations && '' !== $normalized['annotation'] ) {
                        $notes_parts[] = $normalized['annotation'];
                    }
                    $notes_to_append = trim( implode( "\n", array_filter( $notes_parts ) ) );

                    echo '<tr>';
                    echo '<td>' . esc_html( $product_id ) . '</td>';
                    echo '<td>' . esc_html( $carton_raw ) . '</td>';
                    echo '<td>' . esc_html( $normalized['carton'] ) . '</td>';
                    echo '<td>' . esc_html( $notes_to_append ) . '</td>';
                    echo '</tr>';
                }

                if ( empty( $preview_rows ) ) {
                    echo '<tr><td colspan="4">' . esc_html__( 'No rows found in CSV.', 'sop' ) . '</td></tr>';
                }

                echo '</tbody></table>';
                echo '<button type="submit" class="button" name="sop_carton_csv_action" value="preview">' . esc_html__( 'Update preview', 'sop' ) . '</button> ';
                echo '<button type="submit" class="button button-primary" name="sop_carton_csv_action" value="import">' . esc_html__( 'Import cartons', 'sop' ) . '</button>';
                echo '</form>';
            }
        }

        echo '</div>';
    }
}

if ( ! function_exists( 'sop_carton_csv_importer_render_column_select' ) ) {
    function sop_carton_csv_importer_render_column_select( $name, array $headers, $selected_index, $allow_empty ) {
        $html = '<select name="' . esc_attr( $name ) . '">';
        if ( $allow_empty ) {
            $html .= '<option value="-1">' . esc_html__( 'Not mapped', 'sop' ) . '</option>';
        } else {
            $html .= '<option value="-1">' . esc_html__( 'Select column', 'sop' ) . '</option>';
        }

        foreach ( $headers as $idx => $header ) {
            $label = ( '' !== trim( (string) $header ) ) ? (string) $header : sprintf( __( 'Column %d', 'sop' ), ( $idx + 1 ) );
            $selected = ( (int) $selected_index === (int) $idx ) ? 'selected' : '';
            $html .= '<option value="' . esc_attr( (int) $idx ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }

        $html .= '</select>';
        return $html;
    }
}


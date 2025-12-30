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

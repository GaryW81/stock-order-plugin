<?php
/**
 * Stock Order Plugin - Phase 4
 * Notes HTML helpers (admin-safe rendering)
 * File version: 1.0.02
 * - Allow inline color style for red notes (forecolor).
 * - Allow strike tag and keep red class allowlist stable.
 * - Initial helpers for SOP notes sanitization and rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

if ( ! function_exists( 'sop_notes_allowed_html' ) ) {
    /**
     * Allowed HTML tags and attributes for SOP notes.
     *
     * @return array
     */
    function sop_notes_allowed_html() {
        return array(
            'br'     => array(),
            'strong' => array(),
            'b'      => array(),
            's'      => array(),
            'strike' => array(),
            'del'    => array(),
            'span'   => array(
                'class' => array(),
                'style' => array(),
            ),
        );
    }
}

if ( ! function_exists( 'sop_notes_normalize_span_styles' ) ) {
    /**
     * Normalize span classes/styles to only allow sop-note-red and red color.
     *
     * @param string $html HTML string.
     * @return string
     */
    function sop_notes_normalize_span_styles( $html ) {
        return preg_replace_callback(
            '/<span([^>]*)>/i',
            function ( $matches ) {
                $attrs = isset( $matches[1] ) ? $matches[1] : '';
                $has_red_class = false;
                $has_red_style = false;

                if ( preg_match( '/class\s*=\s*("|\')(.*?)\1/i', $attrs, $class_match ) ) {
                    $class_raw = isset( $class_match[2] ) ? $class_match[2] : '';
                    if ( preg_match( '/(^|\s)sop-note-red(\s|$)/', $class_raw ) ) {
                        $has_red_class = true;
                    }
                }

                if ( preg_match( '/style\s*=\s*("|\')(.*?)\1/i', $attrs, $style_match ) ) {
                    $style_raw = strtolower( (string) ( $style_match[2] ?? '' ) );
                    if ( preg_match( '/color\s*:\s*([^;]+)/', $style_raw, $color_match ) ) {
                        $color_val = trim( (string) ( $color_match[1] ?? '' ) );
                        $color_val = str_replace( ' ', '', $color_val );
                        if ( in_array( $color_val, array( '#d63638', 'd63638', 'rgb(214,54,56)', 'rgba(214,54,56,1)' ), true ) ) {
                            $has_red_style = true;
                        }
                    }
                }

                $parts = array();
                if ( $has_red_class ) {
                    $parts[] = 'class="sop-note-red"';
                }
                if ( $has_red_style ) {
                    $parts[] = 'style="color:#d63638"';
                }

                if ( ! empty( $parts ) ) {
                    return '<span ' . implode( ' ', $parts ) . '>';
                }

                return '<span>';
            },
            (string) $html
        );
    }
}

if ( ! function_exists( 'sop_notes_sanitize_html' ) ) {
    /**
     * Sanitize notes HTML for safe storage.
     *
     * @param string $raw Raw input string.
     * @return string
     */
    function sop_notes_sanitize_html( $raw ) {
        $raw = wp_unslash( (string) $raw );
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return '';
        }

        if ( false === strpos( $raw, '<' ) ) {
            return nl2br( esc_html( $raw ) );
        }

        $clean = wp_kses( $raw, sop_notes_allowed_html() );
        return sop_notes_normalize_span_styles( $clean );
    }
}

if ( ! function_exists( 'sop_notes_render_admin_html' ) ) {
    /**
     * Render stored notes safely for admin output.
     *
     * @param string $stored Stored note value.
     * @return string
     */
    function sop_notes_render_admin_html( $stored ) {
        $stored = trim( (string) $stored );
        if ( '' === $stored ) {
            return '';
        }

        if ( false === strpos( $stored, '<' ) ) {
            return nl2br( esc_html( $stored ) );
        }

        $clean = wp_kses( $stored, sop_notes_allowed_html() );
        return sop_notes_normalize_span_styles( $clean );
    }
}

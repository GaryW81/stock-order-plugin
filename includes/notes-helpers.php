<?php
/**
 * Stock Order Plugin - Phase 4
 * Notes HTML helpers (admin-safe rendering)
 * File version: 1.0.03
 * - Fix: persist red notes by converting inline styles to sop-note-red class.
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
            ),
        );
    }
}

if ( ! function_exists( 'sop_notes_canonicalize_red_spans' ) ) {
    /**
     * Convert inline red styles into sop-note-red class.
     *
     * @param string $html HTML string.
     * @return string
     */
    function sop_notes_canonicalize_red_spans( $html ) {
        return preg_replace_callback(
            '/<span([^>]*)>/i',
            function ( $matches ) {
                $attrs = isset( $matches[1] ) ? $matches[1] : '';
                $has_red = false;

                if ( preg_match( '/class\s*=\s*("|\')(.*?)\1/i', $attrs, $class_match ) ) {
                    $class_raw = isset( $class_match[2] ) ? $class_match[2] : '';
                    if ( preg_match( '/(^|\s)sop-note-red(\s|$)/', $class_raw ) ) {
                        $has_red = true;
                    }
                }

                if ( ! $has_red && preg_match( '/style\s*=\s*("|\')(.*?)\1/i', $attrs, $style_match ) ) {
                    $style_raw = strtolower( (string) ( $style_match[2] ?? '' ) );
                    if ( preg_match( '/color\s*:\s*([^;]+)/', $style_raw, $color_match ) ) {
                        $color_val = trim( (string) ( $color_match[1] ?? '' ) );
                        $color_val = str_replace( ' ', '', $color_val );
                        if ( in_array( $color_val, array( '#d63638', 'd63638', 'rgb(214,54,56)', 'rgba(214,54,56,1)' ), true ) ) {
                            $has_red = true;
                        }
                    }
                }

                if ( $has_red ) {
                    return '<span class="sop-note-red">';
                }

                return '<span>';
            },
            (string) $html
        );
    }
}

if ( ! function_exists( 'sop_notes_normalize_span_classes' ) ) {
    /**
     * Normalize span classes to only allow sop-note-red.
     *
     * @param string $html HTML string.
     * @return string
     */
    function sop_notes_normalize_span_classes( $html ) {
        return preg_replace_callback(
            '/<span([^>]*)>/i',
            function ( $matches ) {
                $attrs = isset( $matches[1] ) ? $matches[1] : '';
                if ( preg_match( '/class\s*=\s*("|\')(.*?)\1/i', $attrs, $class_match ) ) {
                    $class_raw = isset( $class_match[2] ) ? $class_match[2] : '';
                    if ( preg_match( '/(^|\s)sop-note-red(\s|$)/', $class_raw ) ) {
                        return '<span class="sop-note-red">';
                    }
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

        $canon = sop_notes_canonicalize_red_spans( $raw );
        $clean = wp_kses( $canon, sop_notes_allowed_html() );
        return sop_notes_normalize_span_classes( $clean );
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
        return sop_notes_normalize_span_classes( $clean );
    }
}

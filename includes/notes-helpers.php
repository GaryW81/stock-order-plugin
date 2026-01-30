<?php
/**
 * Stock Order Plugin - Phase 4
 * Notes HTML helpers (admin-safe rendering)
 * File version: 1.0.01
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
                if ( preg_match( '/class\s*=\s*("|\")(.*?)\1/i', $attrs, $class_match ) ) {
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

        $clean = wp_kses( $raw, sop_notes_allowed_html() );
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

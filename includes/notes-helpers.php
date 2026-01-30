<?php
/**
 * Stock Order Plugin - Phase 4
 * Notes HTML helpers (admin-safe rendering)
 * File version: 1.0.05
 * - Fix red canonicaliser to handle rgb()/rgba() and TinyMCE attributes.
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
        $html = (string) $html;
        if ( '' === trim( $html ) || false === strpos( $html, '<' ) ) {
            return $html;
        }

        if ( ! class_exists( 'DOMDocument' ) ) {
            return $html;
        }

        $prev_errors = libxml_use_internal_errors( true );
        $dom         = new DOMDocument();
        $wrapped     = '<div id="sop-notes-wrapper">' . $html . '</div>';

        if ( ! $dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
            libxml_clear_errors();
            libxml_use_internal_errors( $prev_errors );
            return $html;
        }

        $wrapper = $dom->getElementById( 'sop-notes-wrapper' );
        if ( ! $wrapper ) {
            libxml_clear_errors();
            libxml_use_internal_errors( $prev_errors );
            return $html;
        }

        $xpath = new DOMXPath( $dom );
        $nodes = $xpath->query( './/span|.//font', $wrapper );

        if ( $nodes instanceof DOMNodeList ) {
            foreach ( $nodes as $node ) {
                if ( ! ( $node instanceof DOMElement ) ) {
                    continue;
                }

                $is_red = false;
                $style  = strtolower( (string) $node->getAttribute( 'style' ) );
                $mce    = strtolower( (string) $node->getAttribute( 'data-mce-style' ) );
                $color  = strtolower( (string) $node->getAttribute( 'color' ) );

                if ( '' !== $style || '' !== $mce ) {
                    $style_blob = str_replace( ' ', '', $style . ';' . $mce );
                    if ( preg_match( '/color:(#d63638|d63638|rgb\(214,54,56\)|rgba\(214,54,56,1(?:\.0)?\))/i', $style_blob ) ) {
                        $is_red = true;
                    }
                }

                if ( ! $is_red && '' !== $color ) {
                    $color_clean = str_replace( ' ', '', $color );
                    if ( preg_match( '/^(#d63638|d63638|rgb\(214,54,56\)|rgba\(214,54,56,1(?:\.0)?\)|red)$/i', $color_clean ) ) {
                        $is_red = true;
                    }
                }

                if ( ! $is_red ) {
                    continue;
                }

                if ( 'font' === strtolower( $node->nodeName ) ) {
                    $replacement = $dom->createElement( 'span' );
                    while ( $node->firstChild ) {
                        $replacement->appendChild( $node->firstChild );
                    }
                    $node->parentNode->replaceChild( $replacement, $node );
                    $node = $replacement;
                }

                $node->setAttribute( 'class', 'sop-note-red' );
                $node->removeAttribute( 'style' );
                $node->removeAttribute( 'data-mce-style' );
                $node->removeAttribute( 'color' );
            }
        }

        $output = '';
        foreach ( $wrapper->childNodes as $child ) {
            $output .= $dom->saveHTML( $child );
        }

        libxml_clear_errors();
        libxml_use_internal_errors( $prev_errors );

        return $output;
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







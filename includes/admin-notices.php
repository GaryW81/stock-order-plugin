<?php
/**
 * Stock Order Plugin - Phase 4.1
 * Admin Notices Manager
 * File version: 1.0.0
 * - Centralize admin notices and last bootstrap error persistence.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_admin_notices_init' ) ) {
    function sop_admin_notices_init() {
        static $initialized = false;
        if ( $initialized ) {
            return;
        }
        $initialized = true;
        add_action( 'admin_notices', 'sop_admin_notices_render' );
    }
}

if ( ! function_exists( 'sop_admin_notices_add' ) ) {
    function sop_admin_notices_add( $message, $type = 'error', $dismissible = true ) {
        global $sop_admin_notices;
        if ( ! is_array( $sop_admin_notices ) ) {
            $sop_admin_notices = array();
        }
        $type = is_string( $type ) ? strtolower( trim( $type ) ) : 'error';
        if ( ! in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ) {
            $type = 'info';
        }
        $sop_admin_notices[] = array(
            'message'     => (string) $message,
            'type'        => $type,
            'dismissible' => (bool) $dismissible,
        );
    }
}

if ( ! function_exists( 'sop_admin_notices_render' ) ) {
    function sop_admin_notices_render() {
        global $sop_admin_notices;
        if ( ! is_admin() || empty( $sop_admin_notices ) || ! is_array( $sop_admin_notices ) ) {
            return;
        }
        $cap_ok = false;
        if ( current_user_can( 'manage_options' ) ) {
            $cap_ok = true;
        } elseif ( function_exists( 'sop_get_admin_capability' ) ) {
            $cap_ok = current_user_can( sop_get_admin_capability() );
        } else {
            $cap_ok = current_user_can( 'manage_woocommerce' );
        }
        if ( ! $cap_ok ) {
            return;
        }

        foreach ( $sop_admin_notices as $notice ) {
            $message     = isset( $notice['message'] ) ? (string) $notice['message'] : '';
            $type        = isset( $notice['type'] ) ? (string) $notice['type'] : 'info';
            $dismissible = ! empty( $notice['dismissible'] );
            $classes     = array( 'notice', 'notice-' . esc_attr( $type ) );
            if ( $dismissible ) {
                $classes[] = 'is-dismissible';
            }
            echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><p>' . esc_html( $message ) . '</p></div>';
        }
    }
}

if ( ! function_exists( 'sop_set_last_bootstrap_error' ) ) {
    function sop_set_last_bootstrap_error( $code, $message ) {
        $payload = array(
            'code'    => sanitize_key( $code ),
            'message' => sanitize_text_field( $message ),
            'time'    => time(),
        );
        update_option( 'sop_last_bootstrap_error', $payload, false );
    }
}

if ( ! function_exists( 'sop_get_last_bootstrap_error' ) ) {
    function sop_get_last_bootstrap_error() {
        $payload = get_option( 'sop_last_bootstrap_error', array() );
        return is_array( $payload ) ? $payload : array();
    }
}

if ( ! function_exists( 'sop_clear_last_bootstrap_error' ) ) {
    function sop_clear_last_bootstrap_error() {
        delete_option( 'sop_last_bootstrap_error' );
    }
}
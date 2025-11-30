<?php
/**
 * Stock Order Plugin – Core Helpers (buffer & analysis)
 *
 * Small helper layer on top of:
 * - Global settings (`sop_settings` via sop_Admin_Settings / sop_get_settings()).
 * - Supplier records (`sop_suppliers` via sop_supplier_get_by_id()).
 *
 * Provides:
 * - sop_get_global_buffer_months()
 * - sop_get_supplier_buffer_override_months( $supplier_id )
 * - sop_get_supplier_effective_buffer_months( $supplier_id )
 * - sop_get_analysis_lookback_days()
 * File version: 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

// Require supplier helper function (DB layer). Global settings are read directly from options.
if ( ! function_exists( 'sop_supplier_get_by_id' ) ) {
    return;
}

/**
 * Get the global buffer months from settings.
 *
 * @return float
 */
if ( ! function_exists( 'sop_get_global_buffer_months' ) ) {
    /**
     * Get the global buffer months from the sop_settings option.
     *
     * @return float Non-negative buffer months.
     */
    function sop_get_global_buffer_months() {
        // Read the raw option directly; fall back to an empty array.
        $settings = get_option( 'sop_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $value = isset( $settings['buffer_months_global'] ) ? (float) $settings['buffer_months_global'] : 0.0;

        if ( $value < 0 ) {
            $value = 0.0;
        }

        return $value;
    }
}

/**
 * Get the supplier-specific buffer override in months, if any.
 *
 * @param int $supplier_id
 * @return float|null  Float override if set, otherwise null.
 */
if ( ! function_exists( 'sop_get_supplier_buffer_override_months' ) ) {
    function sop_get_supplier_buffer_override_months( $supplier_id ) {
        $supplier_id = (int) $supplier_id;
        if ( $supplier_id <= 0 ) {
            return null;
        }

        $supplier = sop_supplier_get_by_id( $supplier_id );
        if ( ! $supplier ) {
            return null;
        }

        $settings_json = isset( $supplier->settings_json ) ? $supplier->settings_json : '';
        if ( '' === $settings_json ) {
            return null;
        }

        $settings = json_decode( $settings_json, true );
        if ( ! is_array( $settings ) ) {
            return null;
        }

        // If the key isn't present at all, there is no override; fall back to global.
        if ( ! array_key_exists( 'buffer_months_override', $settings ) ) {
            return null;
        }

        $raw = $settings['buffer_months_override'];

        // Treat an explicit empty string as "no override".
        if ( '' === $raw ) {
            return null;
        }

        $override = (float) $raw;
        if ( $override < 0 ) {
            $override = 0.0;
        }

        return $override;
    }
}

/**
 * Get the effective buffer months for a supplier:
 * - Uses supplier override if present.
 * - Falls back to global buffer months otherwise.
 *
 * @param int $supplier_id
 * @return float
 */
if ( ! function_exists( 'sop_get_supplier_effective_buffer_months' ) ) {
    function sop_get_supplier_effective_buffer_months( $supplier_id ) {
        $override = sop_get_supplier_buffer_override_months( $supplier_id );

        if ( null !== $override ) {
            return $override;
        }

        return sop_get_global_buffer_months();
    }
}

/**
 * Get the default analysis lookback days from settings.
 *
 * @return int
 */
if ( ! function_exists( 'sop_get_analysis_lookback_days' ) ) {
    function sop_get_analysis_lookback_days() {
        $settings = sop_get_settings();
        $days     = isset( $settings['analysis_lookback_days'] ) ? (int) $settings['analysis_lookback_days'] : 365;

        return ( $days < 1 ) ? 1 : $days;
    }
}

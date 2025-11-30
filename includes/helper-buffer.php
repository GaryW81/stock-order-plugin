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
 * File version: 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

// Require that Phase 1 & 2 infrastructure is present.
if ( ! function_exists( 'sop_get_settings' ) || ! function_exists( 'sop_supplier_get_by_id' ) ) {
    return;
}

/**
 * Get the global buffer months from settings.
 *
 * @return float
 */
if ( ! function_exists( 'sop_get_global_buffer_months' ) ) {
    function sop_get_global_buffer_months() {
        $settings = sop_get_settings();
        $value    = isset( $settings['buffer_months_global'] ) ? (float) $settings['buffer_months_global'] : 0.0;

        return ( $value < 0 ) ? 0.0 : $value;
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
        if ( ! $supplier || empty( $supplier->settings_json ) ) {
            return null;
        }

        $settings = json_decode( $supplier->settings_json, true );
        if ( ! is_array( $settings ) ) {
            return null;
        }

        if ( ! array_key_exists( 'buffer_months_override', $settings ) ) {
            return null;
        }

        $override = (float) $settings['buffer_months_override'];
        if ( $override < 0 ) {
            $override = 0;
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
        $supplier_id = (int) $supplier_id;
        if ( $supplier_id < 0 ) {
            $supplier_id = 0;
        }

        // Default to the global buffer months setting.
        $global_buffer = sop_get_global_buffer_months();
        $effective     = max( 0.0, (float) $global_buffer );

        // Apply supplier override only when explicitly set.
        $override_buffer = sop_get_supplier_buffer_override_months( $supplier_id );
        if ( null !== $override_buffer && $override_buffer >= 0 ) {
            $effective = (float) $override_buffer;
        }

        return $effective;
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

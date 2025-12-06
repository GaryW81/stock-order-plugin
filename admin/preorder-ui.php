<?php
/*** Stock Order Plugin - Phase 4.1 - Pre-Order Sheet UI (admin only) V11.87 *
 * - Implement saved sheet locking (UI disable/hide when status is locked).
 * - Uses supplier-level defaults for container type, pallet layer, and allowance when starting new sheets.
 * - Purchase Order modal refined (compact buyer/seller, PO items table, deposit/balance with FX and holiday-driven dates).
 * - Fix shipping time unit handling for PO date suggestions and adjust PO date calc so holidays only extend handling days.
 * - PO details grid layout and explicit PO field wiring for saved sheets.
 * - PO details row: PO# then single-line dates.
 * - V11.87 - PO details row spacing tweak and explicit PO modal load/save wiring.
 * - Under Stock Order main menu.
 * - Supplier filter via _sop_supplier_id.
 * - 90vh scroll, sticky header, sortable columns, column visibility, rounding, CBM bar.
 * - Supplier currency-aware costs using plugin meta:
 *      _sop_cost_rmb, _sop_cost_usd, _sop_cost_eur, fallback _cogs_value for GBP.
 * - Editable & persisted per product:
 *      SKU                -> meta: _sku
 *      Notes              -> meta: _sop_preorder_notes
 *      Min order qty      -> meta: _sop_min_order_qty
 *      Manual order qty   -> meta: _sop_preorder_order_qty
 *      Cost per unit      -> meta: _sop_cost_rmb / _sop_cost_usd / _sop_cost_eur / _cogs_value
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sop_preorder_render_admin_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'sop' ) );
    }

    $suppliers = sop_preorder_get_suppliers();
    $settings  = sop_preorder_get_settings();

    $selected_supplier_id = isset( $_GET['sop_supplier_id'] )
        ? (int) $_GET['sop_supplier_id']
        : 0;
    $current_sheet_id     = isset( $_GET['sop_sheet_id'] ) ? (int) $_GET['sop_sheet_id'] : 0;
    if ( 0 === $current_sheet_id && isset( $_GET['sop_preorder_sheet_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_sheet_id = (int) $_GET['sop_preorder_sheet_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    $current_sheet        = null;
    $current_lines        = array();

    $supplier = null;
    foreach ( $suppliers as $row ) {
        if ( (int) $row['id'] === $selected_supplier_id ) {
            $supplier = $row;
            break;
        }
    }

    if ( ! $supplier && ! empty( $suppliers ) ) {
        $supplier             = $suppliers[0];
        $selected_supplier_id = (int) $supplier['id'];
    }

    $supplier_currency = 'GBP';

    if ( $supplier ) {
        $ctx = sop_preorder_resolve_supplier_params();

        if ( ! empty( $ctx['currency_code'] ) ) {
            $supplier_currency = $ctx['currency_code'];
        }
    }

    $rmb_to_usd_rate = 0.0;
    if ( 'RMB' === $supplier_currency && $selected_supplier_id > 0 && function_exists( 'sop_get_rmb_to_usd_rate_for_supplier' ) ) {
        $rmb_to_usd_rate = sop_get_rmb_to_usd_rate_for_supplier( $selected_supplier_id );
    }
    $po_rmb_per_usd = ( $rmb_to_usd_rate > 0 ) ? $rmb_to_usd_rate : 1.0;

    // Company profile (buyer) details.
    $sop_company_profile   = function_exists( 'sop_get_company_profile' ) ? sop_get_company_profile() : array();
    $company_name          = isset( $sop_company_profile['company_name'] ) ? $sop_company_profile['company_name'] : '';
    $company_billing       = isset( $sop_company_profile['billing_address'] ) ? $sop_company_profile['billing_address'] : '';
    $company_shipping      = isset( $sop_company_profile['shipping_address'] ) ? $sop_company_profile['shipping_address'] : '';
    $company_email         = isset( $sop_company_profile['email'] ) ? $sop_company_profile['email'] : '';
    $company_phone_land    = isset( $sop_company_profile['phone_landline'] ) ? $sop_company_profile['phone_landline'] : '';
    $company_phone_mob     = isset( $sop_company_profile['phone_mobile'] ) ? $sop_company_profile['phone_mobile'] : '';
    $company_crn           = isset( $sop_company_profile['company_reg_number'] ) ? $sop_company_profile['company_reg_number'] : '';
    $company_vat           = isset( $sop_company_profile['vat_number'] ) ? $sop_company_profile['vat_number'] : '';

    // Supplier-level defaults for new sheets (not applied to saved sheets).
    $sop_default_container_type       = '';
    $sop_default_pallet_layer         = 0;
    $sop_default_container_allowance  = 5.0;
    $supplier_settings                = array();
    $holiday_periods                  = array();
    $shipping_days                    = 0;
    $supplier_lead_weeks              = 0;

    // Supplier PI / Rates & Dates values.
    $pi_company_name    = '';
    $pi_company_address = '';
    $pi_company_phone   = '';
    $pi_company_email   = '';
    $pi_contact_name    = '';
    $pi_bank_details    = '';
    $pi_payment_terms   = '';

    if ( $current_sheet_id <= 0 && $selected_supplier_id > 0 && function_exists( 'sop_supplier_get_by_id' ) ) {
        $supplier_obj = sop_supplier_get_by_id( (int) $selected_supplier_id );
        if ( $supplier_obj && ! empty( $supplier_obj->settings_json ) ) {
            $supplier_settings = json_decode( $supplier_obj->settings_json, true );
            if ( is_array( $supplier_settings ) ) {
                if ( ! empty( $supplier_settings['preorder_default_container_type'] ) ) {
                    $allowed_types = array( '20ft', '40ft', '40ft_hc' );
                    if ( in_array( $supplier_settings['preorder_default_container_type'], $allowed_types, true ) ) {
                        $sop_default_container_type = (string) $supplier_settings['preorder_default_container_type'];
                    }
                }

                if ( ! empty( $supplier_settings['preorder_default_pallet_layer'] ) ) {
                    $sop_default_pallet_layer = 1;
                }

                if ( array_key_exists( 'preorder_default_container_allowance', $supplier_settings ) ) {
                    $tmp_allowance = (float) $supplier_settings['preorder_default_container_allowance'];
                    if ( $tmp_allowance < -50 ) {
                        $tmp_allowance = -50;
                    } elseif ( $tmp_allowance > 50 ) {
                        $tmp_allowance = 50;
                    }
                    $sop_default_container_allowance = $tmp_allowance;
                }

                if ( array_key_exists( 'pi_company_name', $supplier_settings ) ) {
                    $pi_company_name = (string) $supplier_settings['pi_company_name'];
                }
                if ( array_key_exists( 'pi_company_address', $supplier_settings ) ) {
                    $pi_company_address = (string) $supplier_settings['pi_company_address'];
                }
                if ( array_key_exists( 'pi_company_phone', $supplier_settings ) ) {
                    $pi_company_phone = (string) $supplier_settings['pi_company_phone'];
                }
                if ( array_key_exists( 'pi_company_email', $supplier_settings ) ) {
                    $pi_company_email = (string) $supplier_settings['pi_company_email'];
                }
                if ( array_key_exists( 'pi_contact_name', $supplier_settings ) ) {
                    $pi_contact_name = (string) $supplier_settings['pi_contact_name'];
                }
                if ( array_key_exists( 'pi_bank_details', $supplier_settings ) ) {
                    $pi_bank_details = (string) $supplier_settings['pi_bank_details'];
                }
                if ( array_key_exists( 'pi_payment_terms', $supplier_settings ) ) {
                    $pi_payment_terms = (string) $supplier_settings['pi_payment_terms'];
                }
                if ( isset( $supplier_settings['holiday_periods'] ) && is_array( $supplier_settings['holiday_periods'] ) ) {
                    foreach ( $supplier_settings['holiday_periods'] as $period ) {
                        $sd = isset( $period['start_day'] ) ? (int) $period['start_day'] : 0;
                        $sm = isset( $period['start_month'] ) ? (int) $period['start_month'] : 0;
                        $ed = isset( $period['end_day'] ) ? (int) $period['end_day'] : 0;
                        $em = isset( $period['end_month'] ) ? (int) $period['end_month'] : 0;

                        if ( $sd >= 1 && $sd <= 31 && $sm >= 1 && $sm <= 12 && $ed >= 1 && $ed <= 31 && $em >= 1 && $em <= 12 ) {
                            $holiday_periods[] = array(
                                'start_day'   => $sd,
                                'start_month' => $sm,
                                'end_day'     => $ed,
                                'end_month'   => $em,
                            );
                        }
                    }
                } else {
                    $legacy_sd = isset( $supplier_settings['holiday_start_day'] ) ? (int) $supplier_settings['holiday_start_day'] : 0;
                    $legacy_sm = isset( $supplier_settings['holiday_start_month'] ) ? (int) $supplier_settings['holiday_start_month'] : 0;
                    $legacy_ed = isset( $supplier_settings['holiday_end_day'] ) ? (int) $supplier_settings['holiday_end_day'] : 0;
                    $legacy_em = isset( $supplier_settings['holiday_end_month'] ) ? (int) $supplier_settings['holiday_end_month'] : 0;

                    if ( $legacy_sd && $legacy_sm && $legacy_ed && $legacy_em ) {
                        $holiday_periods[] = array(
                            'start_day'   => $legacy_sd,
                            'start_month' => $legacy_sm,
                            'end_day'     => $legacy_ed,
                            'end_month'   => $legacy_em,
                        );
                    }
                }

                // Effective shipping days, preferring stored shipping_days.
                $shipping_days = 0;

                // First use explicit shipping_days if present.
                if ( isset( $supplier_settings['shipping_days'] ) ) {
                    $shipping_days = (int) $supplier_settings['shipping_days'];
                }

                // If shipping_days is not set or zero, derive it from shipping_value/unit.
                if ( $shipping_days <= 0 ) {
                    $shipping_value = isset( $supplier_settings['shipping_value'] ) ? (int) $supplier_settings['shipping_value'] : 0;
                    $shipping_unit  = isset( $supplier_settings['shipping_unit'] ) ? (string) $supplier_settings['shipping_unit'] : 'days';

                    if ( $shipping_value < 0 ) {
                        $shipping_value = 0;
                    }
                    if ( ! in_array( $shipping_unit, array( 'days', 'weeks' ), true ) ) {
                        $shipping_unit = 'days';
                    }

                    if ( $shipping_value > 0 ) {
                        $shipping_days = ( 'weeks' === $shipping_unit ) ? ( $shipping_value * 7 ) : $shipping_value;
                    }
                }

                if ( $shipping_days < 0 ) {
                    $shipping_days = 0;
                }
            }
        }
        if ( $supplier_obj && isset( $supplier_obj->lead_time_weeks ) ) {
            $supplier_lead_weeks = (int) $supplier_obj->lead_time_weeks;
        }
    } elseif ( $selected_supplier_id > 0 && function_exists( 'sop_supplier_get_by_id' ) ) {
        $supplier_obj = sop_supplier_get_by_id( (int) $selected_supplier_id );
        if ( $supplier_obj && ! empty( $supplier_obj->settings_json ) ) {
            $supplier_settings = json_decode( $supplier_obj->settings_json, true );
            if ( is_array( $supplier_settings ) ) {
                if ( array_key_exists( 'pi_company_name', $supplier_settings ) ) {
                    $pi_company_name = (string) $supplier_settings['pi_company_name'];
                }
                if ( array_key_exists( 'pi_company_address', $supplier_settings ) ) {
                    $pi_company_address = (string) $supplier_settings['pi_company_address'];
                }
                if ( array_key_exists( 'pi_company_phone', $supplier_settings ) ) {
                    $pi_company_phone = (string) $supplier_settings['pi_company_phone'];
                }
                if ( array_key_exists( 'pi_company_email', $supplier_settings ) ) {
                    $pi_company_email = (string) $supplier_settings['pi_company_email'];
                }
                if ( array_key_exists( 'pi_contact_name', $supplier_settings ) ) {
                    $pi_contact_name = (string) $supplier_settings['pi_contact_name'];
                }
                if ( array_key_exists( 'pi_bank_details', $supplier_settings ) ) {
                    $pi_bank_details = (string) $supplier_settings['pi_bank_details'];
                }
                if ( array_key_exists( 'pi_payment_terms', $supplier_settings ) ) {
                    $pi_payment_terms = (string) $supplier_settings['pi_payment_terms'];
                }
                if ( isset( $supplier_settings['holiday_periods'] ) && is_array( $supplier_settings['holiday_periods'] ) ) {
                    foreach ( $supplier_settings['holiday_periods'] as $period ) {
                        $sd = isset( $period['start_day'] ) ? (int) $period['start_day'] : 0;
                        $sm = isset( $period['start_month'] ) ? (int) $period['start_month'] : 0;
                        $ed = isset( $period['end_day'] ) ? (int) $period['end_day'] : 0;
                        $em = isset( $period['end_month'] ) ? (int) $period['end_month'] : 0;

                        if ( $sd >= 1 && $sd <= 31 && $sm >= 1 && $sm <= 12 && $ed >= 1 && $ed <= 31 && $em >= 1 && $em <= 12 ) {
                            $holiday_periods[] = array(
                                'start_day'   => $sd,
                                'start_month' => $sm,
                                'end_day'     => $ed,
                                'end_month'   => $em,
                            );
                        }
                    }
                } else {
                    $legacy_sd = isset( $supplier_settings['holiday_start_day'] ) ? (int) $supplier_settings['holiday_start_day'] : 0;
                    $legacy_sm = isset( $supplier_settings['holiday_start_month'] ) ? (int) $supplier_settings['holiday_start_month'] : 0;
                    $legacy_ed = isset( $supplier_settings['holiday_end_day'] ) ? (int) $supplier_settings['holiday_end_day'] : 0;
                    $legacy_em = isset( $supplier_settings['holiday_end_month'] ) ? (int) $supplier_settings['holiday_end_month'] : 0;

                    if ( $legacy_sd && $legacy_sm && $legacy_ed && $legacy_em ) {
                        $holiday_periods[] = array(
                            'start_day'   => $legacy_sd,
                            'start_month' => $legacy_sm,
                            'end_day'     => $legacy_ed,
                            'end_month'   => $legacy_em,
                        );
                    }
                }

                // Effective shipping days, preferring stored shipping_days.
                $shipping_days = 0;

                // First use explicit shipping_days if present.
                if ( isset( $supplier_settings['shipping_days'] ) ) {
                    $shipping_days = (int) $supplier_settings['shipping_days'];
                }

                // If shipping_days is not set or zero, derive it from shipping_value/unit.
                if ( $shipping_days <= 0 ) {
                    $shipping_value = isset( $supplier_settings['shipping_value'] ) ? (int) $supplier_settings['shipping_value'] : 0;
                    $shipping_unit  = isset( $supplier_settings['shipping_unit'] ) ? (string) $supplier_settings['shipping_unit'] : 'days';

                    if ( $shipping_value < 0 ) {
                        $shipping_value = 0;
                    }
                    if ( ! in_array( $shipping_unit, array( 'days', 'weeks' ), true ) ) {
                        $shipping_unit = 'days';
                    }

                    if ( $shipping_value > 0 ) {
                        $shipping_days = ( 'weeks' === $shipping_unit ) ? ( $shipping_value * 7 ) : $shipping_value;
                    }
                }

                if ( $shipping_days < 0 ) {
                    $shipping_days = 0;
                }
            }
        }
        if ( $supplier_obj && isset( $supplier_obj->lead_time_weeks ) ) {
            $supplier_lead_weeks = (int) $supplier_obj->lead_time_weeks;
        }
    }

    // Flatten holiday periods for JS (month-day pairs).
    $holiday_periods_md = array();
    foreach ( $holiday_periods as $period ) {
        if (
            isset( $period['start_month'], $period['start_day'], $period['end_month'], $period['end_day'] )
            && $period['start_month'] >= 1
            && $period['start_month'] <= 12
            && $period['start_day'] >= 1
            && $period['start_day'] <= 31
            && $period['end_month'] >= 1
            && $period['end_month'] <= 12
            && $period['end_day'] >= 1
            && $period['end_day'] <= 31
        ) {
            $holiday_periods_md[] = array(
                'start' => sprintf( '%02d-%02d', (int) $period['start_month'], (int) $period['start_day'] ),
                'end'   => sprintf( '%02d-%02d', (int) $period['end_month'], (int) $period['end_day'] ),
            );
        }
    }

    // Container selection: GET overrides supplier defaults.
    if ( isset( $_GET['sop_container'] ) ) {
        $container_selection = sanitize_text_field( wp_unslash( $_GET['sop_container'] ) );
    } else {
        $container_selection = $sop_default_container_type;
    }

    // Additional container controls.
    if ( isset( $_GET['sop_pallet_layer'] ) ) {
        $pallet_layer = 1;
    } else {
        $pallet_layer = (int) $sop_default_pallet_layer;
    }

    if ( isset( $_GET['sop_allowance'] ) ) {
        $allowance = (float) $_GET['sop_allowance'];
    } else {
        $allowance = (float) $sop_default_container_allowance;
    }
    if ( $allowance < -50 ) {
        $allowance = -50;
    } elseif ( $allowance > 50 ) {
        $allowance = 50;
    }

    // SKU filter (substring match, case-insensitive).
    $sku_filter = '';
    if ( isset( $_GET['sop_sku_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $sku_filter = sanitize_text_field( wp_unslash( $_GET['sop_sku_filter'] ) );
    }

    // Container metrics are resolved after sheet context is loaded.
    $base_cbm      = 0.0;
    $floor_area    = 0.0;
    $container_cbm = 0.0;
    $effective_cbm = 0.0;

    $rows = [];
    $overlay_stats = array(
        'matched_rows' => 0,
        'total_lines'  => 0,
    );
    if ( $supplier ) {
        $rows = sop_preorder_build_rows_for_supplier( $supplier['id'], $supplier_currency, $settings );
    }

    if ( '' !== $sku_filter ) {
        $filter_value = strtolower( $sku_filter );
        $rows         = array_values(
            array_filter(
                $rows,
                static function ( $row ) use ( $filter_value ) {
                    $sku = '';

                    if ( ! empty( $row['order_sku'] ) ) {
                        $sku = (string) $row['order_sku'];
                    } elseif ( ! empty( $row['sku'] ) ) {
                        $sku = (string) $row['sku'];
                    } elseif ( ! empty( $row['product_sku'] ) ) {
                        $sku = (string) $row['product_sku'];
                    }

                    if ( '' === $sku ) {
                        return false;
                    }

                    return false !== strpos( strtolower( $sku ), $filter_value );
                }
            )
        );
    }

    // Overlay saved sheet data if present.
    if ( $current_sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) && function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        $current_sheet = sop_get_preorder_sheet( $current_sheet_id );

        if ( ! $current_sheet || ! is_array( $current_sheet ) ) {
            $current_sheet_id = 0;
            $current_sheet    = null;
        } elseif ( isset( $current_sheet['supplier_id'] ) && ( (int) $current_sheet['supplier_id'] !== $selected_supplier_id ) ) {
            $current_sheet_id = 0;
            $current_sheet    = null;
        } else {
            $current_lines = sop_get_preorder_sheet_lines( $current_sheet_id );
            if ( ! is_array( $current_lines ) ) {
                $current_lines = array();
            }

            if ( '' === $container_selection && ! empty( $current_sheet['container_type'] ) ) {
                $container_selection = (string) $current_sheet['container_type'];
            }

            if ( ! empty( $current_lines ) ) {
                $lines_by_product = array();
                foreach ( $current_lines as $line ) {
                    $pid = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
                    if ( $pid <= 0 ) {
                        continue;
                    }
                    $lines_by_product[ $pid ] = $line;
                }

                $overlay_stats['total_lines'] = count( $lines_by_product );

                foreach ( $rows as &$row ) {
                    $pid = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
                    if ( $pid <= 0 || ! isset( $lines_by_product[ $pid ] ) ) {
                        continue;
                    }

                    $line = $lines_by_product[ $pid ];

                    if ( isset( $line['qty_owner'] ) ) {
                        $row['manual_order_qty'] = (float) $line['qty_owner'];
                    }

                    if ( isset( $line['moq_owner'] ) && $line['moq_owner'] > 0 ) {
                        $row['min_order_qty'] = (float) $line['moq_owner'];
                    }

                    if ( isset( $line['cost_rmb_owner'] ) && $line['cost_rmb_owner'] > 0 ) {
                        $row['cost_supplier'] = (float) $line['cost_rmb_owner'];
                    }

                    if ( isset( $line['product_notes_owner'] ) ) {
                        $row['notes'] = $line['product_notes_owner'];
                    }

                    if ( isset( $line['carton_no'] ) ) {
                        $carton_val = (string) $line['carton_no'];
                        if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) ) {
                            $carton_norm = sop_normalize_carton_numbers_for_display( $carton_val );
                            $carton_val  = isset( $carton_norm['value'] ) ? $carton_norm['value'] : $carton_val;
                            $row['carton_sort_min'] = isset( $carton_norm['sort_min'] ) ? $carton_norm['sort_min'] : null;
                        }
                        $row['carton_no'] = $carton_val;
                    }

                    $overlay_stats['matched_rows']++;
                }
                unset( $row );
            }
        }
    }

    // Base CBM and floor area per container type using real internal dimensions.
    switch ( $container_selection ) {
        case '20ft':
            // 20ft GP: 5.90m x 2.35m x 2.39m ~ 33.2 CBM.
            $base_cbm   = 33.2;
            $floor_area = 5.90 * 2.35;
            break;
        case '40ft':
            // 40ft GP: 12.03m x 2.35m x 2.39m ~ 67.7 CBM.
            $base_cbm   = 67.7;
            $floor_area = 12.03 * 2.35;
            break;
        case '40ft_hc':
            // 40ft HQ: 12.03m x 2.35m x 2.69m ~ 76.3 CBM.
            $base_cbm   = 76.3;
            $floor_area = 12.03 * 2.35;
            break;
        default:
            $base_cbm   = 0.0;
            $floor_area = 0.0;
            break;
    }

    // Apply 150mm pallet layer if enabled.
    $container_cbm = $base_cbm;
    if ( $pallet_layer && $floor_area > 0 ) {
        $lost_cubic    = $floor_area * 0.15; // 150mm height.
        $container_cbm = max( 0.0, $container_cbm - $lost_cubic );
    }

    // Apply allowance percentage.
    $effective_cbm = $container_cbm;
    if ( $effective_cbm > 0 && 0.0 !== $allowance ) {
        $effective_cbm = $effective_cbm * ( 1 - ( $allowance / 100 ) );
    }
    if ( $effective_cbm < 0 ) {
        $effective_cbm = 0.0;
    }

    $total_units          = 0.0;
    $total_cost_gbp       = 0.0;
    $total_cost_supplier  = 0.0;
    $total_cbm            = 0.0;
    $total_skus           = 0;

    foreach ( $rows as $row ) {
        if ( ! empty( $row['removed'] ) ) {
            continue;
        }
        $qty = (float) $row['manual_order_qty'];
        if ( $qty <= 0 ) {
            continue;
        }

        $total_units         += $qty;
        $total_cost_gbp      += $qty * (float) $row['cost_gbp'];
        $total_cost_supplier += $qty * (float) $row['cost_supplier'];

        if ( isset( $row['line_cbm'] ) ) {
            $total_cbm += (float) $row['line_cbm'];
        }

        $total_skus++;
    }

    $used_cbm     = 0.0; // raw percent, may exceed 100.
    $used_cbm_bar = 0.0; // clamped percent for bar width.

    if ( $effective_cbm > 0 && $total_cbm > 0 ) {
        $used_cbm = ( $total_cbm / $effective_cbm ) * 100.0;

        if ( $used_cbm < 0.0 ) {
            $used_cbm_bar = 0.0;
        } elseif ( $used_cbm > 100.0 ) {
            $used_cbm_bar = 100.0;
        } else {
            $used_cbm_bar = $used_cbm;
        }
    }
    $currency_symbol = 'GBP';
    switch ( $supplier_currency ) {
        case 'RMB':
            $currency_symbol = 'RMB';
            break;
        case 'USD':
            $currency_symbol = 'USD';
            break;
        case 'EUR':
            $currency_symbol = 'EUR';
            break;
        case 'GBP':
        default:
            $currency_symbol = 'GBP';
            break;
    }

    $order_number_value = '';
    $current_version    = 1;
    $current_status     = '';
    $current_updated    = '';
    if ( $current_sheet && is_array( $current_sheet ) ) {
        $order_number_value = ! empty( $current_sheet['order_number_label'] ) ? $current_sheet['order_number_label'] : '';
        $current_version    = ! empty( $current_sheet['edit_version'] ) ? (int) $current_sheet['edit_version'] : 1;
        $current_status     = ! empty( $current_sheet['status'] ) ? $current_sheet['status'] : '';
        $current_updated    = ! empty( $current_sheet['updated_at'] ) ? $current_sheet['updated_at'] : '';
    }
    $sop_sheet_is_locked = ( $current_sheet_id > 0 && 'locked' === $current_status );
    $sop_disabled_attr   = $sop_sheet_is_locked ? ' disabled="disabled"' : '';
    $po_disabled_attr    = $sop_disabled_attr;

    // Purchase Order (Saved Sheet) values.
    $po_order_date   = '';
    $po_load_date    = '';
    $po_arrival_date = '';
    $po_deposit_rmb  = 0.0;
    $po_deposit_usd  = 0.0;
    $po_deposit_fx_rate   = 0.0;
    $po_deposit_fx_locked = 0;
    $po_balance_fx_rate   = 0.0;
    $po_balance_usd       = 0.0;
    $po_extras       = array();
    $po_holiday_start = '';
    $po_holiday_end   = '';

    $header_notes_owner = '';
    if ( $current_sheet_id > 0 && $current_sheet ) {
        $po_order_date   = isset( $current_sheet['order_date_owner'] ) ? (string) $current_sheet['order_date_owner'] : '';
        $po_load_date    = isset( $current_sheet['container_load_date_owner'] ) ? (string) $current_sheet['container_load_date_owner'] : '';
        $po_arrival_date = isset( $current_sheet['arrival_date_owner'] ) ? (string) $current_sheet['arrival_date_owner'] : '';
        $po_deposit_rmb  = isset( $current_sheet['deposit_fx_owner'] ) ? (float) $current_sheet['deposit_fx_owner'] : 0.0;
        $po_deposit_usd  = isset( $current_sheet['balance_fx_owner'] ) ? (float) $current_sheet['balance_fx_owner'] : 0.0;
        $header_notes_owner = isset( $current_sheet['header_notes_owner'] ) ? $current_sheet['header_notes_owner'] : '';

        if ( is_string( $header_notes_owner ) && '' !== trim( $header_notes_owner ) ) {
            $decoded = json_decode( $header_notes_owner, true );
            if ( is_array( $decoded ) && isset( $decoded['po_extras'] ) && is_array( $decoded['po_extras'] ) ) {
                foreach ( $decoded['po_extras'] as $extra_row ) {
                    if ( ! is_array( $extra_row ) ) {
                        continue;
                    }
                    $label  = isset( $extra_row['label'] ) ? (string) $extra_row['label'] : '';
                    $amount = isset( $extra_row['amount_rmb'] ) ? (float) $extra_row['amount_rmb'] : 0.0;
                    $po_extras[] = array(
                        'label'      => $label,
                        'amount_rmb' => $amount,
                    );
                }
            }
            if ( is_array( $decoded ) ) {
                if ( isset( $decoded['deposit_fx_rate'] ) ) {
                    $po_deposit_fx_rate = (float) $decoded['deposit_fx_rate'];
                }
                if ( isset( $decoded['deposit_fx_locked'] ) ) {
                    $po_deposit_fx_locked = (bool) $decoded['deposit_fx_locked'];
                }
                if ( isset( $decoded['balance_fx_rate'] ) ) {
                    $po_balance_fx_rate = (float) $decoded['balance_fx_rate'];
                }
                if ( isset( $decoded['balance_usd'] ) ) {
                    $po_balance_usd = (float) $decoded['balance_usd'];
                }
                if ( isset( $decoded['po_holiday_start'] ) ) {
                    $po_holiday_start = (string) $decoded['po_holiday_start'];
                }
                if ( isset( $decoded['po_holiday_end'] ) ) {
                    $po_holiday_end = (string) $decoded['po_holiday_end'];
                }
            }
        }
    }

    $po_base_total_rmb   = isset( $total_cost_supplier ) ? (float) $total_cost_supplier : 0.0;
    $po_extras_total_rmb = 0.0;
    foreach ( $po_extras as $extra_row ) {
        if ( isset( $extra_row['amount_rmb'] ) ) {
            $po_extras_total_rmb += (float) $extra_row['amount_rmb'];
        }
    }
    $po_total_rmb = $po_base_total_rmb + $po_extras_total_rmb;
    if ( $po_total_rmb < 0 ) {
        $po_total_rmb = 0.0;
    }
    $po_balance_rmb = $po_total_rmb - $po_deposit_rmb;
    if ( $po_balance_rmb < 0 ) {
        $po_balance_rmb = 0.0;
    }

    if ( $po_deposit_fx_rate <= 0 && $po_deposit_usd > 0 && $po_deposit_rmb > 0 ) {
        $po_deposit_fx_rate = $po_deposit_rmb / $po_deposit_usd;
    }
    if ( $po_deposit_fx_rate <= 0 ) {
        $po_deposit_fx_rate = $po_rmb_per_usd;
    }
    if ( $po_balance_fx_rate <= 0 ) {
        $po_balance_fx_rate = $po_rmb_per_usd;
    }
    if ( $po_balance_fx_rate > 0 ) {
        $po_balance_usd = $po_balance_rmb / $po_balance_fx_rate;
    }
    $sheet_order_number_label = $order_number_value ? $order_number_value : $current_sheet_id;
    ?>
    <div id="sop-preorder-wrapper"
         class="wrap sop-preorder-wrap"
         data-rmb-to-usd-rate="<?php echo esc_attr( $rmb_to_usd_rate ); ?>">
        <h1>
            <?php
            if ( $current_sheet_id > 0 ) {
                esc_html_e( 'Purchase Order', 'sop' );
            } else {
                esc_html_e( 'Pre-Order Sheet', 'sop' );
            }
            ?>
        </h1>
        <?php
        $sop_saved    = isset( $_GET['sop_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['sop_saved'] ) ) : '';
        $sop_sheet_id = isset( $_GET['sop_sheet_id'] ) ? absint( $_GET['sop_sheet_id'] ) : 0;

        if ( '1' === $sop_saved ) {
            $message = $sop_sheet_id
                ? sprintf( __( 'Pre-order sheet saved (ID %d).', 'sop' ), $sop_sheet_id )
                : __( 'Pre-order sheet saved.', 'sop' );

            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html( $message )
            );
        } elseif ( '0' === $sop_saved ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__( 'There was a problem saving the pre-order sheet. Please try again.', 'sop' )
            );
        }
        ?>

        <?php if ( $current_sheet_id > 0 && $current_sheet ) : ?>
            <div class="notice notice-info sop-preorder-sheet-banner">
                <p>
                    <?php
                    printf(
                        /* translators: 1: sheet ID, 2: order number, 3: version, 4: status, 5: updated date */
                        esc_html__( 'Editing saved pre-order sheet #%1$d. Order: %2$s. Version: %3$d. Status: %4$s. Last updated: %5$s', 'sop' ),
                        (int) $current_sheet_id,
                        $order_number_value ? esc_html( $order_number_value ) : esc_html__( 'N/A', 'sop' ),
                        (int) $current_version,
                        esc_html( $current_status ? $current_status : 'draft' ),
                        esc_html( $current_updated )
                    );
                    ?>
                </p>
            </div>
            <?php if ( $sop_sheet_is_locked ) : ?>
                <div class="notice notice-warning sop-preorder-sheet-locked-banner">
                    <p>
                        <?php esc_html_e( 'This saved pre-order sheet is locked. You can view and export it, but cannot edit until you unlock it from the Saved sheets list.', 'sop' ); ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="sop-preorder-header">
            <form id="sop-preorder-filter-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sop_preorder_filter', 'sop_preorder_filter_nonce' ); ?>
                <input type="hidden" name="action" value="sop_preorder_filter" />
                <input type="hidden" name="page" value="sop-preorder-sheet" />
                <?php if ( $current_sheet_id > 0 ) : ?>
                    <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                    <input type="hidden" name="sop_preorder_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                <?php endif; ?>
            </form>

            <?php if ( $current_sheet_id > 0 ) : ?>
                <form id="sop-preorder-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
                    <input type="hidden" name="action" value="sop_export_preorder_sheet_csv" />
                    <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                    <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $selected_supplier_id ); ?>" />
                    <?php wp_nonce_field( 'sop_export_preorder_sheet_csv' ); ?>
                </form>
            <?php endif; ?>

            <div class="sop-preorder-card sop-preorder-card--top">
                <div class="sop-preorder-card-icon sop-preorder-card-icon--supplier" aria-hidden="true">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="sop-preorder-card-main sop-preorder-card-main--top">
                    <div class="sop-preorder-card-row sop-preorder-top-row">
                        <div class="sop-preorder-top-left">
                            <label>
                                <?php esc_html_e( 'Supplier:', 'sop' ); ?>
                                <select name="sop_supplier_id" form="sop-preorder-filter-form">
                                    <?php foreach ( $suppliers as $row ) : ?>
                                        <option value="<?php echo esc_attr( $row['id'] ); ?>" <?php selected( (int) $row['id'], $selected_supplier_id ); ?>>
                                            <?php echo esc_html( $row['name'] ); ?> (<?php echo esc_html( $row['currency_code'] ); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label for="sop-header-order-number" class="sop-preorder-order-label">
                                <?php esc_html_e( 'Order #', 'sop' ); ?>
                                <input type="text"
                                       id="sop-header-order-number"
                                       name="sop_header_order_number"
                                       value="<?php echo esc_attr( $order_number_value ); ?>"
                                       style="width: 110px;"
                                       <?php echo $sop_disabled_attr; ?>
                                       form="sop-preorder-sheet-form" />
                            </label>
                        </div>
                        <div class="sop-preorder-top-right">
                            <?php if ( $current_sheet_id > 0 ) : ?>
                                <button type="submit"
                                        class="button"
                                        form="sop-preorder-export-form">
                                    <?php esc_html_e( 'Export Excel (.xls)', 'sop' ); ?>
                                </button>
                                <button type="button" class="button sop-rates-dates-toggle">
                                    <?php esc_html_e( 'Purchase Order', 'sop' ); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ( $selected_supplier_id > 0 ) : ?>
                                <?php
                                $saved_sheets_url = add_query_arg(
                                    array(
                                        'page'        => 'sop-preorder-sheets',
                                        'supplier_id' => (int) $selected_supplier_id,
                                    ),
                                    admin_url( 'admin.php' )
                                );
                                ?>
                                <a class="button button-secondary" href="<?php echo esc_url( $saved_sheets_url ); ?>">
                                    <?php esc_html_e( 'View saved sheets', 'sop' ); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ( ! $sop_sheet_is_locked ) : ?>
                                <button type="submit" class="button button-primary" name="sop_preorder_save" form="sop-preorder-sheet-form">
                                    <?php
                                echo ( $current_sheet_id > 0 )
                                    ? esc_html__( 'Update sheet', 'sop' )
                                    : esc_html__( 'Save sheet', 'sop' );
                                ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sop-preorder-card sop-preorder-card--planning">
                <div class="sop-preorder-card-icon sop-preorder-card-icon--container" aria-hidden="true">
                    <span class="dashicons dashicons-admin-multisite"></span>
                </div>
                <div class="sop-preorder-card-main sop-preorder-card-main--middle">
                    <div class="sop-preorder-card__row sop-preorder-card__row--container-top">
                        <div class="sop-preorder-container-item sop-preorder-container-item--select">
                            <label>
                                <?php esc_html_e( 'Container:', 'sop' ); ?>
                                <select name="sop_container" form="sop-preorder-filter-form" <?php echo $sop_disabled_attr; ?>>
                                    <option value=""><?php esc_html_e( 'None', 'sop' ); ?></option>
                                    <option value="20ft" <?php selected( $container_selection, '20ft' ); ?>>20&#39; (33.2 CBM)</option>
                                    <option value="40ft" <?php selected( $container_selection, '40ft' ); ?>>40&#39; (67.7 CBM)</option>
                                    <option value="40ft_hc" <?php selected( $container_selection, '40ft_hc' ); ?>>40&#39; HQ (76.3 CBM)</option>
                                </select>
                            </label>
                        </div>

                        <div class="sop-preorder-container-item sop-preorder-container-item--pallet">
                            <label class="sop-pallet-layer-label">
                                <input type="checkbox" name="sop_pallet_layer" value="1" <?php checked( $pallet_layer ); ?> form="sop-preorder-filter-form" <?php echo $sop_disabled_attr; ?> />
                                <?php esc_html_e( '150mm pallet layer', 'sop' ); ?>
                            </label>
                        </div>

                        <div class="sop-preorder-container-item sop-preorder-container-item--allowance">
                            <label class="sop-allowance-label">
                                <?php esc_html_e( 'Allowance:', 'sop' ); ?>
                                <input type="number" name="sop_allowance" value="<?php echo esc_attr( $allowance ); ?>" step="1" min="-50" max="50" form="sop-preorder-filter-form" <?php echo $sop_disabled_attr; ?> />
                                %
                            </label>
                        </div>

                        <div class="sop-preorder-container-item sop-preorder-container-item--button">
                            <button type="submit" class="button button-secondary" name="sop_preorder_update_container" value="1" form="sop-preorder-filter-form" <?php echo $sop_disabled_attr; ?>>
                                <?php esc_html_e( 'Update container', 'sop' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="sop-preorder-card-row sop-preorder-middle-bottom">
                        <div class="sop-preorder-totals">
                            <span><strong><?php esc_html_e( 'Total Units', 'sop' ); ?>:</strong> <span id="sop-total-units"><?php echo esc_html( number_format_i18n( $total_units, 0 ) ); ?></span></span>
                            <span><strong><?php esc_html_e( 'Total SKUs', 'sop' ); ?>:</strong> <span id="sop-total-skus"><?php echo esc_html( number_format_i18n( $total_skus, 0 ) ); ?></span></span>
                            <span><strong><?php esc_html_e( 'Total Cost (GBP)', 'sop' ); ?>:</strong> <span id="sop-total-cost-gbp"><?php echo esc_html( wc_price( $total_cost_gbp ) ); ?></span></span>
                            <span><strong><?php printf( esc_html__( 'Total Cost (%s)', 'sop' ), esc_html( $supplier_currency ) ); ?>:</strong> <span id="sop-total-cost-supplier"><?php echo esc_html( $currency_symbol . ' ' . number_format_i18n( $total_cost_supplier, 2 ) ); ?></span></span>
                        </div>
                        <div class="sop-preorder-fill">
                            <strong><?php esc_html_e( 'Container Fill', 'sop' ); ?>:</strong>
                            <div class="sop-cbm-bar-wrapper" title="<?php echo esc_attr( $used_cbm ); ?>%">
                                <div class="sop-cbm-bar" style="width: <?php echo esc_attr( $used_cbm ); ?>%;"></div>
                            </div>
                            <span class="sop-cbm-label" id="sop-cbm-label"><?php echo esc_html( number_format_i18n( $used_cbm, 1 ) ); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sop-preorder-card sop-preorder-card--tools">
                <div class="sop-preorder-card-icon sop-preorder-card-icon--planner" aria-hidden="true">
                    <span class="dashicons dashicons-clipboard"></span>
                </div>
                    <div class="sop-preorder-card-main sop-preorder-card-main--tools">
                        <div class="sop-preorder-card-row sop-preorder-bottom-row">
                            <div class="sop-preorder-bottom-left">
                                <span><?php esc_html_e( 'Rounding:', 'sop' ); ?></span>
                                <label class="sop-round-step-label">
                                    <?php esc_html_e( 'Step:', 'sop' ); ?>
                                    <select class="sop-round-step" <?php echo $sop_disabled_attr; ?>>
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                    </select>
                                </label>
                                <button type="button" class="button" data-round-mode="up" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Round Up', 'sop' ); ?></button>
                                <button type="button" class="button" data-round-mode="down" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Round Down', 'sop' ); ?></button>
                            </div>

                            <div class="sop-preorder-bottom-middle">
                                <button type="button" class="button" id="sop-apply-soq-to-qty" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Apply SOQ to Qty', 'sop' ); ?></button>
                                <button type="button" class="button" id="sop-preorder-remove-selected" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Remove selected', 'sop' ); ?></button>
                                <label for="sop-preorder-show-removed" class="sop-preorder-show-removed">
                                    <input type="checkbox" id="sop-preorder-show-removed" <?php echo $sop_disabled_attr; ?> />
                                    <?php esc_html_e( 'Show removed rows', 'sop' ); ?>
                                </label>
                            </div>

                            <div class="sop-preorder-bottom-right">
                            <div class="sop-preorder-filter-sku">
                                <label for="sop_sku_filter" class="screen-reader-text">
                                    <?php esc_html_e( 'Search by SKU', 'sop' ); ?>
                                </label>
                                <div class="sop-preorder-filter-sku-field">
                                    <div class="sop-preorder-sku-search">
                                        <input type="text"
                                               id="sop_sku_filter"
                                               name="sop_sku_filter"
                                               value="<?php echo esc_attr( $sku_filter ); ?>"
                                               placeholder="<?php esc_attr_e( 'Search SKU', 'stock-order-plugin' ); ?>"
                                               class="regular-text sop-preorder-sku-input" />
                                        <span class="dashicons dashicons-search sop-preorder-sku-icon" aria-hidden="true"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="sop-preorder-columns">
                                <button
                                    type="button"
                                    class="button sop-preorder-columns-toggle"
                                    aria-expanded="false"
                                >
                                    <?php esc_html_e( 'Columns', 'sop' ); ?>
                                </button>

                                <div class="sop-preorder-columns-popover" aria-hidden="true">
                                    <div class="sop-preorder-columns-panel">
                                        <ul class="sop-preorder-columns-list">
                                            <?php
                                            $sop_column_labels = array(
                                                'image'         => __( 'Image', 'sop' ),
                                                'location'      => __( 'Location', 'sop' ),
                                                'sku'           => __( 'SKU', 'sop' ),
                                                'brand'         => __( 'Brand', 'sop' ),
                                                'category'      => __( 'Category', 'sop' ),
                                                'product'       => __( 'Product', 'sop' ),
                                                'cost_supplier' => __( 'Cost per unit', 'sop' ),
                                                'cost_usd'      => __( 'Unit price (USD)', 'sop' ),
                                                'stock'         => __( 'Stock', 'sop' ),
                                                'inbound'       => __( 'Inbound', 'sop' ),
                                                'min_order'     => __( 'MOQ', 'sop' ),
                                                'soq'           => __( 'SOQ', 'sop' ),
                                                'order_qty'     => __( 'Qty', 'sop' ),
                                                'line_total'    => __( 'Line total', 'sop' ),
                                                'cubic'         => __( 'cm3 per unit', 'sop' ),
                                                'line_cbm'      => __( 'Line CBM', 'sop' ),
                                                'regular_unit'  => __( 'Price excl.', 'sop' ),
                                                'regular_line'  => __( 'Line excl.', 'sop' ),
                                                'notes'         => __( 'Product notes', 'sop' ),
                                                'order_notes'   => __( 'Order notes', 'sop' ),
                                                'carton_no'     => __( 'Carton no.', 'sop' ),
                                            );

                                            foreach ( $sop_column_labels as $column_key => $column_label ) :
                                                ?>
                                                <li>
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            data-column="<?php echo esc_attr( $column_key ); ?>"
                                                            checked="checked"
                                                        />
                                                        <?php echo esc_html( $column_label ); ?>
                                                    </label>
                                                </li>
                                                <?php
                                            endforeach;
                                            ?>
                                        </ul>
                                    </div><!-- .sop-preorder-columns-panel -->
                                </div><!-- .sop-preorder-columns-popover -->
                            </div><!-- .sop-preorder-columns -->
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <form id="sop-preorder-sheet-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sop_save_preorder_sheet', 'sop_save_preorder_sheet_nonce' ); ?>
                <input type="hidden" name="action" value="sop_save_preorder_sheet" />
                <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                <input type="hidden" name="sop_supplier_id" value="<?php echo esc_attr( $selected_supplier_id ); ?>" />
                <input type="hidden" name="sop_supplier_name" value="<?php echo isset( $supplier['name'] ) ? esc_attr( $supplier['name'] ) : ''; ?>" />
                <input type="hidden" name="sop_container_type" value="<?php echo esc_attr( $container_selection ); ?>" />
                <input type="hidden" name="sop_allowance_percent" value="<?php echo esc_attr( $allowance ); ?>" />

                <div class="sop-preorder-table-wrapper">
                <table class="wp-list-table widefat fixed striped sop-preorder-table">
                    <thead>
                        <tr>
                            <th class="sop-preorder-col-select">
                                <input type="checkbox" id="sop-preorder-select-all" <?php echo $sop_disabled_attr; ?> />
                            </th>
                            <th class="column-image" data-column="image"><?php esc_html_e( 'Image', 'sop' ); ?></th>
                            <th class="column-location" data-column="location" data-sort="location" title="<?php esc_attr_e( 'Warehouse location / bin', 'sop' ); ?>"><?php esc_html_e( 'Location', 'sop' ); ?></th>
                            <th class="column-sku" data-column="sku" data-sort="sku" data-sort-key="sku" title="<?php esc_attr_e( 'SKU (stock-keeping unit)', 'sop' ); ?>"><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                            <th class="column-brand" data-column="brand" data-sort="brand" title="<?php esc_attr_e( 'Brand / manufacturer', 'sop' ); ?>"><?php esc_html_e( 'Brand', 'sop' ); ?></th>
                            <th class="column-category" data-column="category" data-sort="category" data-sort-key="category" title="<?php esc_attr_e( 'Product categories', 'sop' ); ?>"><?php esc_html_e( 'Category', 'sop' ); ?></th>
                            <th class="column-name" data-column="product" data-sort="name" title="<?php esc_attr_e( 'Product name', 'sop' ); ?>"><?php esc_html_e( 'Product', 'sop' ); ?></th>
                            <th class="column-cost-supplier" data-column="cost_supplier" data-sort="cost" title="<?php esc_attr_e( 'Cost per unit in supplier currency (GBP, USD, EUR, RMB)', 'sop' ); ?>">
                                <?php
                                printf(
                                    /* translators: %s: supplier currency code. */
                                    esc_html__( 'Cost per unit (%s)', 'sop' ),
                                    esc_html( $supplier_currency )
                                );
                                ?>
                            </th>
                            <?php if ( 'RMB' === $supplier_currency ) : ?>
                                <th class="column-cost-usd" data-column="cost_usd" data-sort="unit_price_usd" data-sort-key="unit_price_usd">
                                    <span class="sop-preorder-header-label sop-preorder-header-label--wrap-2">
                                        <?php echo esc_html__( 'Unit price', 'stock-order-plugin' ); ?><br>
                                        <?php echo esc_html__( '(USD)', 'stock-order-plugin' ); ?>
                                    </span>
                                </th>
                            <?php endif; ?>
                            <th class="column-stock" data-column="stock" data-sort="stock" title="<?php esc_attr_e( 'Stock on hand', 'sop' ); ?>"><?php esc_html_e( 'Stock', 'sop' ); ?></th>
                            <th class="column-inbound" data-column="inbound" data-sort="inbound" title="<?php esc_attr_e( 'Inbound quantity on purchase orders', 'sop' ); ?>"><?php esc_html_e( 'Inbound', 'sop' ); ?></th>
                            <th class="column-min-order" data-column="min_order" data-sort="moq" title="<?php esc_attr_e( 'Minimum order quantity', 'sop' ); ?>"><?php esc_html_e( 'MOQ', 'sop' ); ?></th>
                            <th class="column-suggested" data-column="soq" data-sort="soq" title="<?php esc_attr_e( 'Suggested order quantity', 'sop' ); ?>"><?php esc_html_e( 'SOQ', 'sop' ); ?></th>
                            <th class="column-order-qty" data-column="order_qty" data-sort="order_qty" title="<?php esc_attr_e( 'Manual order quantity for this shipment', 'sop' ); ?>"><?php esc_html_e( 'Qty', 'sop' ); ?></th>
                            <th class="column-line-total-supplier" data-column="line_total" data-sort="total" title="<?php esc_attr_e( 'Line total in supplier currency', 'sop' ); ?>">
                                <?php echo esc_html__( 'Line total', 'sop' ); ?>
                                <br />
                                <?php
                                    printf(
                                        '(%s)',
                                    esc_html( $supplier_currency )
                                );
                                ?>
                            </th>
                            <th class="column-cubic-item" data-column="cubic" data-sort="cubic" title="<?php esc_attr_e( 'Cubic centimetres per unit', 'sop' ); ?>">
                                <span class="sop-preorder-header-label sop-preorder-header-label--wrap-2">
                                    <?php echo esc_html__( 'cm3', 'stock-order-plugin' ); ?><br>
                                    <?php echo esc_html__( 'per unit', 'stock-order-plugin' ); ?>
                                </span>
                            </th>
                            <th class="column-line-cbm" data-column="line_cbm" data-sort="line_cbm" title="<?php esc_attr_e( 'Line volume in cubic metres', 'sop' ); ?>"><?php esc_html_e( 'Line CBM', 'sop' ); ?></th>
                            <th class="column-regular-unit" data-column="regular_unit" data-sort="price_ex" title="<?php esc_attr_e( 'Regular WooCommerce price per unit excluding VAT', 'sop' ); ?>"><?php esc_html_e( 'Price excl.', 'sop' ); ?></th>
                            <th class="column-regular-line" data-column="regular_line" data-sort="line_ex" title="<?php esc_attr_e( 'Regular WooCommerce line price excluding VAT', 'sop' ); ?>"><?php esc_html_e( 'Line excl.', 'sop' ); ?></th>
                            <th class="column-notes" data-column="notes" data-sort="notes" title="<?php esc_attr_e( 'Internal notes for this product.', 'sop' ); ?>"><?php esc_html_e( 'Product notes', 'sop' ); ?></th>
                            <th class="column-order-notes" data-column="order_notes" data-sort="order_notes" title="<?php esc_attr_e( 'Order-specific notes', 'sop' ); ?>"><?php esc_html_e( 'Order notes', 'sop' ); ?></th>
                            <th class="column-carton-no"
                                data-column="carton_no"
                                data-sort="carton_no"
                                data-sort-key="carton_no"
                                title="<?php esc_attr_e( 'Carton numbers only. Use numbers & ranges: e.g. 4,7,12-13. Other packing info ? Order notes.', 'sop' ); ?>">
                                <?php esc_html_e( 'Carton no.', 'sop' ); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                            <tr>
                                <td colspan="<?php echo ( 'RMB' === $supplier_currency ) ? '22' : '21'; ?>">
                                    <?php esc_html_e( 'No products found for this supplier.', 'sop' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php $sop_row_index = 0; ?>
                            <?php foreach ( $rows as $index => $row ) :
                                $product_id           = (int) $row['product_id'];
                                $name                 = $row['name'];
                                $order_sku            = '';
                                if ( isset( $row['order_sku'] ) && '' !== $row['order_sku'] ) {
                                    $order_sku = $row['order_sku'];
                                } elseif ( isset( $row['sku'] ) ) {
                                    $order_sku = $row['sku'];
                                }
                                $sku                  = isset( $row['sku'] ) ? $row['sku'] : '';
                                $notes                = $row['notes'];
                                $min_order_qty        = (float) $row['min_order_qty'];
                                $order_qty            = (float) $row['manual_order_qty'];
                                $stock_on_hand        = (float) $row['stock_on_hand'];
                                $inbound_qty          = (float) $row['inbound_qty'];
                                $cost_gbp             = (float) $row['cost_gbp'];
                                $cost_supplier        = (float) $row['cost_supplier'];

                                $location             = isset( $row['location'] ) ? $row['location'] : '';
                                $brand                = isset( $row['brand'] ) ? $row['brand'] : '';
                                $suggested_order_qty  = isset( $row['suggested_order_qty'] ) ? (float) $row['suggested_order_qty'] : 0.0;
                                $cubic_cm             = isset( $row['cubic_cm'] ) ? (float) $row['cubic_cm'] : 0.0;
                                $line_cbm             = isset( $row['line_cbm'] ) ? (float) $row['line_cbm'] : 0.0;
                                $categories           = '';
                                if ( isset( $row['category_path'] ) ) {
                                    $categories = $row['category_path'];
                                } elseif ( isset( $row['category'] ) ) {
                                    $categories = $row['category'];
                                } elseif ( isset( $row['categories'] ) ) {
                                    $categories = $row['categories'];
                                }

                                $product              = wc_get_product( $product_id );

                                // Regular WooCommerce price: _regular_price (incl. VAT in your setup).
                                $regular_gross        = $product ? (float) $product->get_regular_price() : 0.0;
                                $regular_unit_price   = $regular_gross > 0 ? $regular_gross / 1.2 : 0.0;
                                $regular_line_price   = $regular_unit_price * $order_qty;

                                $line_total_gbp       = $order_qty * $cost_gbp;
                                $line_total_sup       = $order_qty * $cost_supplier;
                                $row_classes          = array( 'sop-preorder-row' );
                                if ( ! empty( $row['removed'] ) ) {
                                    $row_classes[] = 'sop-preorder-row-removed';
                                }
                                $row_key = $product_id;
                                $row_index = $sop_row_index;
                                $sku_sort_source = ( '' !== $order_sku ) ? $order_sku : $sku;
                                $sku_sort_value  = trim( preg_replace( '/\s+/', ' ', (string) $sku_sort_source ) );
                                $category_sort_value = trim( (string) $categories );
                                $carton_value = isset( $row['carton_no'] ) ? (string) $row['carton_no'] : '';
                                $carton_sort_value = isset( $row['carton_sort_min'] ) ? $row['carton_sort_min'] : null;
                                if ( function_exists( 'sop_normalize_carton_numbers_for_display' ) && '' !== $carton_value ) {
                                    $carton_norm        = sop_normalize_carton_numbers_for_display( $carton_value );
                                    $carton_value       = isset( $carton_norm['value'] ) ? (string) $carton_norm['value'] : $carton_value;
                                    $carton_sort_value  = isset( $carton_norm['sort_min'] ) ? $carton_norm['sort_min'] : $carton_sort_value;
                                }
                                $carton_sort_attr = ( '' === $carton_value || null === $carton_sort_value ) ? '' : $carton_sort_value;

                                $image_id = 0;
                                if ( $product ) {
                                    $image_id = $product->get_image_id();
                                }
                                ?>
                                <tr data-index="<?php echo esc_attr( $index ); ?>" class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
                                    <input type="hidden" name="sop_line_product_id[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $product_id ); ?>" />
                                    <input type="hidden" name="sop_line_sku[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $sku ); ?>" />
                                    <input type="hidden" name="sop_line_image_id[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $image_id ); ?>" />
                                    <input type="hidden" name="sop_line_location[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $location ); ?>" />
                                    <input type="hidden" name="sop_line_cbm_per_unit[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( isset( $row['cbm_per_unit'] ) ? $row['cbm_per_unit'] : 0 ); ?>" />
                                    <input type="hidden" name="sop_line_cbm_total[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $line_cbm ); ?>" />
                                    <td class="sop-preorder-col-select">
                                        <input
                                            type="checkbox"
                                            class="sop-preorder-select-row"
                                            data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                            <?php echo $sop_disabled_attr; ?>
                                        />
                                        <button type="button"
                                                class="button-link sop-preorder-restore-row"
                                                data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                                <?php echo $sop_disabled_attr; ?>>
                                            <?php esc_html_e( 'Restore', 'sop' ); ?>
                                        </button>
                                    </td>
                                    <td class="column-image" data-column="image">
                                        <?php
                                        if ( $image_id ) {
                                            // Use the smaller WooCommerce gallery thumbnail (typically 100x100).
                                            echo wp_get_attachment_image(
                                                $image_id,
                                                'woocommerce_gallery_thumbnail'
                                            );
                                        }
                                        ?>
                                    </td>
                                    <td class="column-location" data-column="location">
                                        <?php echo esc_html( $location ); ?>
                                    </td>
                                    <td class="column-sku" data-column="sku" data-sort-key="sku" data-sort-value="<?php echo esc_attr( $sku_sort_value ); ?>" data-sort-text="<?php echo esc_attr( $sku_sort_value ); ?>">
                                        <input type="hidden" name="sop_product_id[]" value="<?php echo esc_attr( $product_id ); ?>" />
                                        <textarea
                                            name="sop_sku[]"
                                            rows="2"
                                            class="sop-preorder-sku small-text"
                                            title="<?php echo esc_attr( $order_sku ); ?>"
                                            <?php echo $sop_disabled_attr; ?>
                                        ><?php echo esc_textarea( $order_sku ); ?></textarea>
                                    </td>
                                    <td class="column-brand" data-column="brand">
                                        <?php echo esc_html( $brand ); ?>
                                    </td>
                                    <td class="column-category" data-column="category" data-sort-key="category" data-sort-value="<?php echo esc_attr( $category_sort_value ); ?>">
                                        <?php echo esc_html( $categories ); ?>
                                    </td>
                                    <td class="column-name" data-column="product">
                                        <?php echo esc_html( $name ); ?>
                                    </td>
                                    <td class="column-cost-supplier" data-column="cost_supplier">
                                        <input type="number" name="sop_line_cost_rmb[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $cost_supplier ); ?>" step="0.01" min="0" class="sop-cost-supplier-input sop-preorder-cost-rmb" <?php echo $sop_disabled_attr; ?> />
                                    </td>
                                    <?php if ( 'RMB' === $supplier_currency ) : ?>
                                        <?php
                                        $unit_cost_rmb  = $cost_supplier;
                                        $unit_cost_usd  = 0.0;
                                        if ( $unit_cost_rmb > 0 ) {
                                            if ( $rmb_to_usd_rate > 0 ) {
                                                $unit_cost_usd = $unit_cost_rmb * $rmb_to_usd_rate;
                                            } elseif ( function_exists( 'sop_convert_rmb_unit_cost_to_usd' ) ) {
                                                $unit_cost_usd = sop_convert_rmb_unit_cost_to_usd( $unit_cost_rmb );
                                            }
                                        }
                                        $usd_sort_value = ( $unit_cost_usd > 0 ) ? number_format( $unit_cost_usd, 6, '.', '' ) : '';
                                        ?>
                                        <td class="column-cost-usd" data-column="cost_usd" data-sort-key="unit_price_usd" data-sort-value="<?php echo esc_attr( $usd_sort_value ); ?>">
                                            <span class="sop-preorder-cost-usd">
                                                <?php
                                                if ( $unit_cost_usd > 0 ) {
                                                    echo esc_html( wc_format_decimal( $unit_cost_usd, 2 ) );
                                                } else {
                                                    echo '&ndash;';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="column-stock" data-column="stock">
                                        <?php echo esc_html( number_format_i18n( $stock_on_hand, 0 ) ); ?>
                                    </td>
                                    <td class="column-inbound" data-column="inbound">
                                        <?php echo esc_html( number_format_i18n( $inbound_qty, 0 ) ); ?>
                                    </td>
                                    <td class="column-min-order" data-column="min_order">
                                        <input type="number" name="sop_line_moq[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $min_order_qty ); ?>" step="1" min="0" class="sop-preorder-moq" <?php echo $sop_disabled_attr; ?> />
                                    </td>
                                    <td class="column-suggested" data-column="soq">
                                        <span class="sop-preorder-soq" data-soq="<?php echo esc_attr( $suggested_order_qty ); ?>">
                                            <?php echo esc_html( number_format_i18n( $suggested_order_qty, 0 ) ); ?>
                                        </span>
                                    </td>
                                    <td class="column-order-qty" data-column="order_qty" data-sort="order_qty">
                                        <input type="number" name="sop_line_qty[<?php echo esc_attr( $row_index ); ?>]" value="<?php echo esc_attr( $order_qty ); ?>" step="1" min="0" class="sop-order-qty-input sop-preorder-qty" <?php echo $sop_disabled_attr; ?> />
                                    </td>
                                    <td class="column-line-total-supplier" data-column="line_total">
                                        <span class="sop-line-total-gbp" data-cost-gbp="<?php echo esc_attr( $cost_gbp ); ?>" style="display:none;">
                                            <?php echo esc_html( number_format_i18n( $line_total_gbp, 2 ) ); ?>
                                        </span>
                                        <span class="sop-line-total-supplier" data-cost-supplier="<?php echo esc_attr( $cost_supplier ); ?>">
                                            <?php echo esc_html( number_format_i18n( $line_total_sup, 2 ) ); ?>
                                        </span>
                                    </td>
                                    <td class="column-cubic-item" data-column="cubic" data-cubic-cm="<?php echo esc_attr( $cubic_cm ); ?>">
                                        <?php echo esc_html( number_format_i18n( $cubic_cm, 0 ) ); ?>
                                    </td>
                                    <td class="column-line-cbm" data-column="line_cbm">
                                        <span class="sop-line-cbm-value">
                                            <?php echo esc_html( number_format_i18n( $line_cbm, 3 ) ); ?>
                                        </span>
                                    </td>
                                    <td class="column-regular-unit" data-column="regular_unit">
                                        <?php echo esc_html( number_format_i18n( $regular_unit_price, 2 ) ); ?>
                                    </td>
                                    <td class="column-regular-line" data-column="regular_line">
                                        <?php echo esc_html( number_format_i18n( $regular_line_price, 2 ) ); ?>
                                    </td>
                                    <td class="column-notes" data-column="notes">
                                        <div class="sop-preorder-notes-wrapper">
                                            <textarea
                                                name="sop_line_product_notes[<?php echo esc_attr( $row_index ); ?>]"
                                                rows="3"
                                                class="sop-preorder-notes sop-preorder-notes-product"
                                                style="width: 100%; resize: none;"
                                                title="<?php echo esc_attr( $notes ); ?>"
                                                data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                data-notes-type="product"
                                                <?php echo $sop_disabled_attr; ?>
                                            ><?php echo esc_textarea( $notes ); ?></textarea>

                                            <button type="button"
                                                    class="sop-preorder-notes-edit-icon"
                                                    data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                                    data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                    data-notes-type="product"
                                                    aria-label="<?php esc_attr_e( 'Edit product notes', 'sop' ); ?>"
                                                    <?php echo $sop_disabled_attr; ?>>
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                        </div>

                                        <input
                                            type="hidden"
                                            name="sop_removed[]"
                                            value="<?php echo ! empty( $row['removed'] ) ? '1' : '0'; ?>"
                                            class="sop-preorder-removed-flag"
                                        />
                                    </td>
                                    <td class="column-order-notes" data-column="order_notes">
                                        <div class="sop-preorder-notes-wrapper">
                                            <textarea
                                                name="sop_line_order_notes[<?php echo esc_attr( $row_index ); ?>]"
                                                rows="3"
                                                class="sop-preorder-notes sop-preorder-notes-order"
                                                style="width: 100%; resize: none;"
                                                data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                data-notes-type="order"
                                                <?php echo $sop_disabled_attr; ?>
                                            ><?php echo isset( $row['order_notes'] ) ? esc_textarea( $row['order_notes'] ) : ''; ?></textarea>

                                            <button type="button"
                                                    class="sop-preorder-notes-edit-icon"
                                                    data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                                    data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                    data-notes-type="order"
                                                    aria-label="<?php esc_attr_e( 'Edit order notes', 'sop' ); ?>"
                                                    <?php echo $sop_disabled_attr; ?>>
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="column-carton-no" data-column="carton_no" data-sort-key="carton_no" data-sort-value="<?php echo esc_attr( $carton_sort_attr ); ?>">
                                        <input
                                            type="text"
                                            name="sop_line_carton_no[<?php echo esc_attr( $row_index ); ?>]"
                                            value="<?php echo esc_attr( $carton_value ); ?>"
                                            class="sop-preorder-carton-input"
                                            data-original-value="<?php echo esc_attr( $carton_value ); ?>"
                                            style="width: 80px;"
                                            <?php echo $sop_disabled_attr; ?>
                                        />
                                    </td>
                                </tr>
                                <?php $sop_row_index++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="sop-preorder-notes-overlay" class="sop-preorder-notes-overlay" style="display:none;">
                <div class="sop-preorder-notes-overlay-backdrop"></div>
                <div class="sop-preorder-notes-overlay-inner">
                    <button type="button"
                            class="button-link sop-preorder-notes-overlay-close"
                            aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">
                        &times;
                    </button>
                    <h3 class="sop-preorder-notes-overlay-title">
                        <?php esc_html_e( 'Product notes', 'sop' ); ?>
                    </h3>
                    <p class="sop-preorder-notes-overlay-product"></p>
                    <textarea class="sop-preorder-notes-overlay-textarea" rows="8" <?php echo $sop_disabled_attr; ?>></textarea>
                    <p>
                        <button type="button"
                                class="button button-primary sop-preorder-notes-overlay-save"
                                <?php echo $sop_disabled_attr; ?>>
                            <?php esc_html_e( 'Save notes', 'sop' ); ?>
                        </button>
                    </p>
                </div>
            </div>

            <div class="sop-preorder-actions">
                <?php if ( ! $sop_sheet_is_locked ) : ?>
                    <button type="submit" name="sop_save_sheet" value="1" class="button button-primary">
                        <?php
                        echo ( $current_sheet_id > 0 )
                            ? esc_html__( 'Update sheet', 'sop' )
                            : esc_html__( 'Save sheet', 'sop' );
                        ?>
                    </button>
                <?php endif; ?>
            </div>
            <div id="sop-rates-dates-overlay" class="sop-rates-dates-overlay" style="display:none;">
                <div class="sop-rates-dates-modal">
                    <button type="button" class="sop-rates-dates-close notice-dismiss" aria-label="<?php esc_attr_e( 'Close Purchase Order', 'sop' ); ?>">
                        <span class="screen-reader-text"><?php esc_html_e( 'Close', 'sop' ); ?></span>
                    </button>
                    <h2><?php esc_html_e( 'Purchase Order', 'sop' ); ?></h2>

                    <div class="sop-rates-dates-columns">
                        <div class="sop-rates-dates-column">
                            <h3><?php esc_html_e( 'Buyer', 'sop' ); ?></h3>
                            <p><strong><?php esc_html_e( 'Company:', 'sop' ); ?></strong> <?php echo esc_html( $company_name ); ?></p>
                            <p><strong><?php esc_html_e( 'Billing address:', 'sop' ); ?></strong><br /><?php echo nl2br( esc_html( $company_billing ) ); ?></p>
                            <p><strong><?php esc_html_e( 'Shipping address:', 'sop' ); ?></strong><br /><?php echo nl2br( esc_html( $company_shipping ) ); ?></p>
                            <p><strong><?php esc_html_e( 'Email:', 'sop' ); ?></strong> <?php echo esc_html( $company_email ); ?></p>
                            <p><strong><?php esc_html_e( 'Phone (landline):', 'sop' ); ?></strong> <?php echo esc_html( $company_phone_land ); ?></p>
                            <p><strong><?php esc_html_e( 'Phone (mobile):', 'sop' ); ?></strong> <?php echo esc_html( $company_phone_mob ); ?></p>
                            <p><strong><?php esc_html_e( 'Company reg no.:', 'sop' ); ?></strong> <?php echo esc_html( $company_crn ); ?></p>
                            <p><strong><?php esc_html_e( 'VAT no.:', 'sop' ); ?></strong> <?php echo esc_html( $company_vat ); ?></p>
                        </div>
                        <div class="sop-rates-dates-column">
                            <h3><?php esc_html_e( 'Seller', 'sop' ); ?></h3>
                            <p><strong><?php esc_html_e( 'Company:', 'sop' ); ?></strong> <?php echo esc_html( $pi_company_name ); ?></p>
                            <p><strong><?php esc_html_e( 'Address:', 'sop' ); ?></strong><br /><?php echo nl2br( esc_html( $pi_company_address ) ); ?></p>
                            <p><strong><?php esc_html_e( 'Contact:', 'sop' ); ?></strong> <?php echo esc_html( $pi_contact_name ); ?></p>
                            <p><strong><?php esc_html_e( 'Telephone:', 'sop' ); ?></strong> <?php echo esc_html( $pi_company_phone ); ?></p>
                            <p><strong><?php esc_html_e( 'Email:', 'sop' ); ?></strong> <?php echo esc_html( $pi_company_email ); ?></p>
                            <p><strong><?php esc_html_e( 'Bank details:', 'sop' ); ?></strong><br /><?php echo nl2br( esc_html( $pi_bank_details ) ); ?></p>
                        </div>
                    </div>
                    <div class="sop-po-section sop-po-details">
                        <h3><?php esc_html_e( 'Purchase Order details', 'sop' ); ?></h3>

                        <div class="sop-po-details-grid">
                            <div class="sop-po-field sop-po-field--po-number">
                                <label><?php esc_html_e( 'Purchase order #', 'sop' ); ?></label>
                                <span><?php echo esc_html( $sheet_order_number_label ); ?></span>
                            </div>

                            <div class="sop-po-field sop-po-field--order-date">
                                <label><?php esc_html_e( 'Order date', 'sop' ); ?></label>
                                <input type="date"
                                       name="sop_po_order_date"
                                       value="<?php echo esc_attr( $po_order_date ); ?>"<?php echo $po_disabled_attr; ?> />
                            </div>

                            <div class="sop-po-field sop-po-field--holiday">
                                <label><?php esc_html_e( 'Holiday period', 'sop' ); ?></label>
                                <div class="sop-po-holiday-range">
                                    <input type="date"
                                           name="sop_po_holiday_start"
                                           value="<?php echo esc_attr( $po_holiday_start ); ?>"<?php echo $po_disabled_attr; ?> />
                                    <span class="sop-po-holiday-separator">–</span>
                                    <input type="date"
                                           name="sop_po_holiday_end"
                                           value="<?php echo esc_attr( $po_holiday_end ); ?>"<?php echo $po_disabled_attr; ?> />
                                </div>
                            </div>

                            <div class="sop-po-field sop-po-field--load-date">
                                <label><?php esc_html_e( 'Container load date', 'sop' ); ?></label>
                                <input type="date"
                                       name="sop_po_load_date"
                                       value="<?php echo esc_attr( $po_load_date ); ?>"<?php echo $po_disabled_attr; ?> />
                            </div>

                            <div class="sop-po-field sop-po-field--eta-date">
                                <label><?php esc_html_e( 'ETA UK / delivery date', 'sop' ); ?></label>
                                <input type="date"
                                       name="sop_po_arrival_date"
                                       value="<?php echo esc_attr( $po_arrival_date ); ?>"<?php echo $po_disabled_attr; ?> />
                            </div>
                        </div>
                    </div>

                    <div class="sop-po-section sop-po-values">
                        <h3><?php esc_html_e( 'Purchase order values', 'sop' ); ?></h3>
                        <table class="widefat fixed striped sop-po-items-table" data-locked="<?php echo esc_attr( $sop_sheet_is_locked ? '1' : '0' ); ?>">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Description', 'sop' ); ?></th>
                                    <th class="column-amount"><?php esc_html_e( 'Amount (RMB)', 'sop' ); ?></th>
                                    <?php if ( ! $sop_sheet_is_locked ) : ?>
                                        <th class="column-actions"></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="sop-po-items-body">
                                <tr class="sop-po-base-row">
                                    <td>
                                        <?php
                                        $po_total_skus  = isset( $total_skus ) ? (int) $total_skus : 0;
                                        $po_total_units = isset( $total_units ) ? (int) $total_units : 0;
                                        printf(
                                            esc_html__( 'Purchase order #%1$s – %2$d SKUs / %3$d pcs', 'sop' ),
                                            esc_html( $sheet_order_number_label ),
                                            $po_total_skus,
                                            $po_total_units
                                        );
                                        ?>
                                    </td>
                                    <td class="column-amount">
                                        <span id="sop-po-base-total-rmb"
                                              data-base-total-rmb="<?php echo esc_attr( $po_base_total_rmb ); ?>">
                                            <?php echo esc_html( number_format( $po_base_total_rmb, 2 ) ); ?>
                                        </span>
                                    </td>
                                    <?php if ( ! $sop_sheet_is_locked ) : ?>
                                        <td></td>
                                    <?php endif; ?>
                                </tr>

                                <?php
                                $po_extras_rows = ! empty( $po_extras ) ? $po_extras : array( array( 'label' => '', 'amount_rmb' => 0.0 ) );
                                foreach ( $po_extras_rows as $extra_row ) :
                                    $extra_label  = isset( $extra_row['label'] ) ? (string) $extra_row['label'] : '';
                                    $extra_amount = isset( $extra_row['amount_rmb'] ) ? (float) $extra_row['amount_rmb'] : 0.0;
                                    ?>
                                    <tr class="sop-po-extra-row">
                                        <td>
                                            <input type="text"
                                                   name="sop_po_extra_label[]"
                                                   value="<?php echo esc_attr( $extra_label ); ?>"<?php echo $po_disabled_attr; ?> />
                                        </td>
                                        <td class="column-amount">
                                            <input type="number"
                                                   step="0.01"
                                                   class="sop-po-extra-amount"
                                                   name="sop_po_extra_amount[]"
                                                   value="<?php echo esc_attr( $extra_amount ); ?>"<?php echo $po_disabled_attr; ?> />
                                        </td>
                                        <?php if ( ! $sop_sheet_is_locked ) : ?>
                                            <td class="column-actions">
                                                <button type="button" class="button-link sop-po-extra-remove">&times;</button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="sop-po-total-row">
                                    <th><?php esc_html_e( 'Total (RMB)', 'sop' ); ?></th>
                                    <th class="column-amount">
                                        <span id="sop-po-total-rmb"><?php echo esc_html( number_format( $po_total_rmb, 2 ) ); ?></span>
                                    </th>
                                    <?php if ( ! $sop_sheet_is_locked ) : ?>
                                        <th></th>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                        <?php if ( ! $sop_sheet_is_locked ) : ?>
                            <p>
                                <button type="button" class="button sop-po-add-extra">
                                    <?php esc_html_e( 'Add item', 'sop' ); ?>
                                </button>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="sop-po-section sop-po-deposit">
                        <div class="sop-po-field">
                            <label><?php esc_html_e( 'Deposit (USD)', 'sop' ); ?></label>
                            <input type="number"
                                   step="0.01"
                                   name="sop_po_deposit_usd"
                                   value="<?php echo esc_attr( $po_deposit_usd ); ?>"<?php echo $po_disabled_attr; ?> />
                        </div>

                        <div class="sop-po-field">
                            <label><?php esc_html_e( 'Deposit FX rate (RMB per USD)', 'sop' ); ?></label>
                            <input type="number"
                                   step="0.0001"
                                   name="sop_po_deposit_fx_rate"
                                   id="sop-po-deposit-fx-rate"
                                   value="<?php echo esc_attr( $po_deposit_fx_rate ); ?>"<?php echo $po_disabled_attr; ?> />
                            <label class="sop-po-inline">
                                <input type="checkbox"
                                       name="sop_po_deposit_fx_locked"
                                       value="1"
                                       <?php checked( $po_deposit_fx_locked ); ?>
                                       <?php echo $po_disabled_attr ? ' disabled="disabled"' : ''; ?>
                                />
                                <?php esc_html_e( 'Lock deposit FX rate (deposit paid)', 'sop' ); ?>
                            </label>
                        </div>

                        <div class="sop-po-field">
                            <label><?php esc_html_e( 'Deposit (RMB)', 'sop' ); ?></label>
                            <input type="number"
                                   step="0.01"
                                   id="sop-po-deposit-rmb"
                                   name="sop_po_deposit_rmb"
                                   value="<?php echo esc_attr( $po_deposit_rmb ); ?>"<?php echo $po_disabled_attr; ?> />
                        </div>
                    </div>

                    <div class="sop-po-section sop-po-balance">
                        <div class="sop-po-field">
                            <label><?php esc_html_e( 'Balance (RMB)', 'sop' ); ?></label>
                            <span id="sop-po-balance-rmb">
                                <?php echo esc_html( number_format( $po_balance_rmb, 2 ) ); ?>
                            </span>
                        </div>

                        <div class="sop-po-field">
                            <label><?php esc_html_e( 'Balance FX rate (RMB per USD)', 'sop' ); ?></label>
                            <input type="number"
                                   step="0.0001"
                                   name="sop_po_balance_fx_rate"
                                   id="sop-po-balance-fx-rate"
                                   value="<?php echo esc_attr( $po_balance_fx_rate ); ?>"<?php echo $po_disabled_attr; ?> />
                        </div>

                        <div class="sop-po-field">
                            <label><?php esc_html_e( 'Balance (USD)', 'sop' ); ?></label>
                            <span id="sop-po-balance-usd">
                                <?php echo esc_html( number_format( $po_balance_usd, 2 ) ); ?>
                            </span>
                            <input type="hidden"
                                   name="sop_po_balance_usd"
                                   id="sop-po-balance-usd-input"
                                   value="<?php echo esc_attr( $po_balance_usd ); ?>" />
                        </div>

                        <input type="hidden" id="sop-po-rmb-per-usd" value="<?php echo esc_attr( $po_rmb_per_usd ); ?>" />
                        <input type="hidden" id="sop-po-lead-weeks" value="<?php echo esc_attr( $supplier_lead_weeks ); ?>" />
                        <input type="hidden" id="sop-po-shipping-days" value="<?php echo esc_attr( $shipping_days ); ?>" />
                        <input type="hidden" id="sop-po-supplier-holiday-periods" value="<?php echo esc_attr( wp_json_encode( $holiday_periods_md ) ); ?>" />
                    </div>

                    <div class="sop-rates-dates-terms">
                        <h3><?php esc_html_e( 'Payment terms', 'sop' ); ?></h3>
                        <p><?php echo nl2br( esc_html( $pi_payment_terms ) ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        </form>
    </div>

    <style>
        .sop-preorder-wrap {
            max-width: 100%;
        }

        .sop-preorder-header {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 12px;
        }

        .sop-preorder-card {
            background: #ffffff;
            border: 1px solid #c3c4c7; /* match main table border */
            border-radius: 8px;
            padding: 16px 20px 18px;
            margin-bottom: 12px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
            display: flex;
            align-items: stretch;
        }

        .sop-preorder-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 10px;
            border-right: 1px solid #c3c4c7;
            background-color: #f7f7f7;
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }

        .sop-preorder-card-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-left: 16px;
        }

        .sop-preorder-card-icon .dashicons {
            font-size: 28px;
            width: 28px;
            height: 28px;
            color: #111827;
        }

        .sop-preorder-card-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            justify-content: space-between;
        }

        .sop-preorder-top-left,
        .sop-preorder-top-right,
        .sop-preorder-middle-top,
        .sop-preorder-middle-bottom,
        .sop-preorder-bottom-left,
        .sop-preorder-bottom-middle,
        .sop-preorder-bottom-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .sop-preorder-top-left {
            gap: 16px;
        }

        .sop-preorder-top-right {
            margin-left: auto;
            gap: 12px;
        }

        .sop-preorder-middle-top,
        .sop-preorder-middle-bottom,
        .sop-preorder-bottom-row {
            width: 100%;
        }

        /* Add vertical space between the top and bottom rows of Tile 2 (container planning) */
        .sop-preorder-middle-bottom {
            margin-top: 10px;
        }

        .sop-preorder-bottom-right {
            margin-left: auto;
        }

        /* Columns toggle + popover */
        .sop-preorder-columns {
            position: relative;
            display: inline-block;
        }

        .sop-preorder-columns-toggle {
            min-width: 180px;
        }

        /* Popover is hidden by default */
        .sop-preorder-columns-popover {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            left: auto;
            z-index: 1000;

            width: 220px;
            max-height: 260px;
            overflow-y: auto;

            padding: 8px 10px;
            margin: 0;

            background: #ffffff;
            border: 1px solid #ccd0d4;
            border-radius: 3px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.18);

            display: none;
        }

        /* When wrapper has .is-open, show the popover */
        .sop-preorder-columns.is-open .sop-preorder-columns-popover {
            display: block;
        }

        /* List layout: single column, no wrapping */
        .sop-preorder-columns-popover ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sop-preorder-columns-popover li {
            margin: 0;
            padding: 2px 0;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
        }

        .sop-preorder-columns-popover label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
        }

        .sop-preorder-sku-search {
            position: relative;
            display: inline-flex;
            align-items: center;
            max-width: 260px;
            width: 100%;
        }

        .sop-preorder-sku-search .sop-preorder-sku-input {
            width: 100%;
            padding-right: 28px;
            padding-left: 0.6rem;
            box-sizing: border-box;
        }

        .sop-preorder-sku-search .sop-preorder-sku-input::placeholder {
            color: #9ca3af;
        }

        .sop-preorder-sku-search .sop-preorder-sku-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            line-height: 1;
            width: 18px;
            height: 18px;
            color: #000;
            pointer-events: none;
        }

        .sop-preorder-header select[name="sop_supplier_id"] {
            min-width: 240px;
        }

        .sop-preorder-top-row,
        .sop-preorder-middle-top,
        .sop-preorder-middle-bottom,
        .sop-preorder-bottom-row {
            justify-content: space-between;
            gap: 24px;
        }

        .sop-preorder-top-left,
        .sop-preorder-top-right {
            gap: 16px;
        }

        .sop-preorder-filter-sku {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .sop-preorder-card__row--container-top {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            width: 100%;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sop-preorder-container-item--button {
            justify-content: flex-end;
        }

        .sop-preorder-filter-sku-field {
            display: inline-flex;
            align-items: center;
        }

        .sop-preorder-filter-sku-field .regular-text {
            width: 170px;
            max-width: 220px;
            margin-right: 0;
        }

        .sop-preorder-summary-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 0;
            background: transparent;
            border: 0;
            border-radius: 0;
            margin: 0;
        }

        .sop-preorder-totals span + span {
            margin-left: 16px;
        }

        .sop-preorder-fill {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 260px;
            justify-content: flex-end;
            flex: 1 0 auto;
        }

        .sop-cbm-bar-wrapper {
            position: relative;
            flex: 1;
            height: 10px;
            background: #e5e5e5;
            border-radius: 999px;
            overflow: hidden;
        }

        .sop-cbm-bar {
            height: 100%;
            background: #46b450;
            width: 0;
            transition: width 0.25s ease;
        }

        .sop-cbm-label {
            min-width: 50px;
            text-align: right;
        }

        .sop-lock-status {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .sop-locked {
            background: #d63638;
            color: #fff;
        }

        .sop-unlocked {
            background: #46b450;
            color: #fff;
        }

        .sop-preorder-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            margin-bottom: 8px;
            padding: 0;
            border: 0;
            background-color: transparent;
        }

        .sop-preorder-table-toolbar {
            width: 100%;
        }

        .sop-preorder-toolbar-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .sop-preorder-toolbar-columns {
            margin-left: auto;
            text-align: right;
        }

        .sop-round-step-label {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-right: 0.5rem;
        }

        .sop-round-step {
            min-width: 60px;
        }

        .sop-row-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding-left: 1rem;
            border-left: 1px solid #e2e4e7;
        }

        .sop-preorder-columns-label {
            font-weight: 500;
        }

        .sop-preorder-table-wrapper {
            max-height: 90vh;
            overflow-x: auto;
            overflow-y: auto;
            border: 1px solid #ccd0d4;
        }

        .sop-preorder-table thead th {
            position: sticky;
            top: 0;
            background: #f1f1f1;
            z-index: 2;
            cursor: pointer;
            white-space: normal;
            word-break: normal;
            overflow-wrap: break-word;
            padding-right: 14px;
        }

        /* Pre-Order Sheet header column dividers */
        .sop-preorder-table thead th + th::before {
            content: '';
            position: absolute;
            left: 0;
            top: 6px;
            bottom: 6px;
            width: 1px;
            background-color: #e3e3e3;
            pointer-events: none;
        }

        .sop-preorder-table tr.sop-preorder-sku-hit {
            background-color: #fff8d7;
            transition: background-color 0.4s ease;
        }

        /* Rates & Dates modal */
        .sop-rates-dates-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            display: none;
        }

        .sop-rates-dates-modal {
            position: relative;
            max-width: 1000px;
            width: 100%;
            background: #fff;
            padding: 20px 24px;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            max-height: 90vh;
            overflow-y: auto;
        }

        .sop-rates-dates-close {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .sop-rates-dates-modal .sop-rates-dates-columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 12px;
        }

        .sop-rates-dates-modal .sop-rates-dates-column {
            font-size: 12px;
            line-height: 1.4;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 220px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .sop-po-items-table input[type="text"] {
            width: 100%;
            box-sizing: border-box;
        }

        .sop-rates-dates-terms {
            margin-top: 16px;
        }

        .sop-po-section {
            margin-top: 10px;
        }

        .sop-po-details-grid {
            display: grid;
            grid-template-columns: 1fr 1.8fr 1fr 1fr;
            gap: 16px 32px;
            margin: 6px 0 16px;
            align-items: flex-end;
        }

        .sop-po-field--po-number {
            grid-column: 1 / -1;
        }

        .sop-po-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
        }

        @media (max-width: 1200px) {
            .sop-po-details-grid {
                grid-template-columns: repeat(2, minmax(200px, 1fr));
            }
        }

        @media (max-width: 782px) {
            .sop-po-details-grid {
                grid-template-columns: 1fr;
            }
        }

        .sop-po-holiday-range {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sop-po-field--holiday .sop-po-holiday-range input[type="date"] {
            flex: 1 1 0;
            min-width: 0;
            max-width: 180px;
        }

        .sop-po-field--order-date input[type="date"],
        .sop-po-field--load-date input[type="date"],
        .sop-po-field--eta-date input[type="date"] {
            width: 100%;
            max-width: 180px;
        }

        .sop-po-holiday-separator {
            padding: 0 2px;
        }

        .sop-po-items-table .column-amount {
            text-align: right;
            width: 140px;
        }

        .sop-po-items-table .column-actions {
            width: 40px;
            text-align: center;
        }

        .sop-po-items-table tfoot th {
            font-weight: 700;
        }

        .sop-po-deposit {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .sop-po-holiday-range {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .sop-po-holiday-range input[type="date"] {
            max-width: 150px;
        }

        .sop-po-holiday-separator {
            padding: 0 2px;
        }

        .sop-preorder-table th .sop-preorder-header-label--wrap-2 {
            display: inline-block;
            max-width: 100%;
            white-space: normal;
            word-break: normal;
            line-height: 1.2;
            max-height: calc(1.2em * 2);
            overflow: hidden;
        }

        .sop-preorder-table tbody td {
            vertical-align: top;
        }

        .sop-preorder-table .column-image {
            width: 80px;
            text-align: center;
        }

        .sop-preorder-table td.column-image {
            padding: 1px;
        }

        .sop-preorder-table .column-image img {
            height: 60px !important;
            width: auto;
            max-height: 60px;
            max-width: 60px;
            object-fit: contain;
        }

        .sop-preorder-table td.column-image img.attachment-woocommerce_gallery_thumbnail {
            width: 78px !important;
            height: 78px !important;
            max-width: none;
            max-height: none;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .sop-preorder-table th.column-cost-usd,
        .sop-preorder-table td.column-cost-usd {
            width: 90px;
            min-width: 90px;
        }

        .sop-preorder-table th.column-cubic-item,
        .sop-preorder-table td.column-cubic-item {
            width: 80px;
            min-width: 80px;
        }

        .sop-preorder-table {
            table-layout: auto;
            width: auto;
            min-width: 1600px;
        }

        .sop-preorder-table .column-location {
            width: 90px;
            white-space: nowrap;
        }

        .sop-preorder-table .column-name {
            min-width: 35ch;
            max-width: 35ch;
            white-space: normal;
            word-wrap: break-word;
            word-break: break-word;
        }

        .sop-preorder-table .column-sku {
            width: 120px;
            white-space: nowrap;
        }

        .sop-preorder-table td.column-sku textarea.sop-preorder-sku {
            width: 12ch;
            min-width: 12ch;
            max-width: 12ch;
            min-height: 3.2em;
            resize: vertical;
            padding-top: 2px;
            padding-bottom: 2px;
            box-sizing: border-box;
            vertical-align: top;
        }

        .sop-preorder-table th.column-order-qty,
        .sop-preorder-table td.column-order-qty {
            width: 90px;
        }

        /* Qty and MOQ: ~8 characters, fixed so they don't squash */
        .sop-preorder-table .sop-order-qty-input,
        .sop-preorder-table .sop-preorder-moq-input,
        .sop-preorder-table .column-min-order input {
            width: 8ch;
            min-width: 8ch;
            max-width: 8ch;
        }

        /* Cost: ~11 characters to show decimal + extra digits, fixed width */
        .sop-preorder-table .sop-cost-supplier-input,
        .sop-preorder-table .column-cost-supplier input {
            width: 11ch;
            min-width: 11ch;
            max-width: 11ch;
        }

        .sop-preorder-table input[type="text"],
        .sop-preorder-table input[type="number"],
        .sop-preorder-table textarea {
            font-size: inherit;
        }


        .sop-preorder-table .column-notes textarea {
            width: 100%;
            min-height: 3em;
            box-sizing: border-box;
        }

        .sop-preorder-table input[type="number"],
        .sop-preorder-table input[type="text"] {
            width: 100%;
        }

        .sop-preorder-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem 1rem;
            margin-top: 1rem;
        }

        .sop-preorder-actions-left,
        .sop-preorder-actions-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .sop-preorder-actions-right {
            margin-left: auto;
        }

        .sop-preorder-table th[data-sort] {
            position: sticky;
        }

        .sop-preorder-table th[data-sort]::after {
            content: '\25B2';
            opacity: 0.3;
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 0;
            font-size: 10px;
        }

        .sop-preorder-table th[data-sort].sorted-asc::after {
            content: '\25B2';
            opacity: 1;
        }

        .sop-preorder-table th[data-sort].sorted-desc::after {
            content: '\25BC';
            opacity: 1;
        }

        .sop-preorder-table input.sop-preorder-carton-input {
            width: auto;
            min-width: 90px;
        }

        .sop-preorder-carton-tooltip {
            position: absolute;
            background: #23282d;
            color: #fff;
            padding: 6px 8px;
            border-radius: 3px;
            font-size: 12px;
            line-height: 1.4;
            max-width: 220px;
            white-space: normal;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
        }

        .sop-preorder-table [data-column] {
            transition: opacity 0.15s ease;
        }

        .sop-preorder-table .column-line-total-supplier {
            white-space: nowrap;
        }

        .sop-preorder-col-select {
            width: 40px;
            text-align: center;
        }

        .sop-preorder-table th.sop-preorder-col-select,
        .sop-preorder-table td.sop-preorder-col-select {
            width: 40px;
        }

        .sop-preorder-table th.sop-preorder-col-select input[type="checkbox"] {
            margin-left: 0;
            margin-right: 0;
        }

        .sop-preorder-table td.sop-preorder-col-select input[type="checkbox"] {
            margin-left: 0;
            margin-right: 0;
        }

        .sop-preorder-row-removed {
            opacity: 0.6;
        }

        .sop-preorder-col-select .sop-preorder-restore-row {
            display: none;
            margin-top: 2px;
        }

        .sop-preorder-row-removed .sop-preorder-col-select .sop-preorder-restore-row {
            display: block;
            pointer-events: auto;
            opacity: 1;
        }

        .sop-preorder-table th.column-notes,
        .sop-preorder-table td.column-notes,
        .sop-preorder-table th.column-order-notes,
        .sop-preorder-table td.column-order-notes {
            min-width: 40ch;
        }

        .sop-preorder-notes-wrapper {
            position: relative;
        }

        .sop-preorder-notes-edit-icon {
            position: absolute;
            top: 4px;
            right: 4px;
            padding: 0;
            border: none;
            background: transparent;
            cursor: pointer;
        }

        .sop-preorder-notes-edit-icon .dashicons {
            font-size: 16px;
            line-height: 1;
        }

        .sop-preorder-notes-overlay {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: none;
        }

        .sop-preorder-notes-overlay-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
        }

        .sop-preorder-notes-overlay-inner {
            position: absolute;
            top: 10%;
            left: 50%;
            transform: translateX(-50%);
            max-width: 700px;
            width: 90%;
            background: #fff;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .sop-preorder-notes-overlay-textarea {
            width: 100%;
            min-height: 180px;
            resize: vertical;
            box-sizing: border-box;
        }
    </style>

    <script>
        jQuery(function($) {
            var $table = $('.sop-preorder-table');
            var containerCbm = <?php echo json_encode( $effective_cbm ); ?>;
            var $rowCheckboxes       = $table.find('.sop-preorder-select-row');
            var $selectAllCheckbox   = $('#sop-preorder-select-all');
            var $removeSelectedBtn   = $('#sop-preorder-remove-selected');
            var $showRemovedCheckbox = $('#sop-preorder-show-removed');
            var $notesOverlay        = $('#sop-preorder-notes-overlay');
            var $notesOverlayInner   = $notesOverlay.find('.sop-preorder-notes-overlay-inner');
            var $notesOverlayTitle   = $notesOverlay.find('.sop-preorder-notes-overlay-title');
            var $notesOverlayProduct = $notesOverlay.find('.sop-preorder-notes-overlay-product');
            var $notesOverlayTextarea = $notesOverlay.find('.sop-preorder-notes-overlay-textarea');
            var currentNotesTextarea = null;
            var currentNotesType     = 'product';
            var currentNotesRowIndex = null;
            var lastClickedIndex     = null;
            var hasUnsavedChanges    = false;
            var $sheetForm           = $('#sop-preorder-sheet-form');
            var $columnsWrapper      = $('.sop-preorder-columns');
            var $columnsToggleButton = $columnsWrapper.find('.sop-preorder-columns-toggle');
            var $columnsPanel        = $columnsWrapper.find('.sop-preorder-columns-popover');
            var $columnCheckboxes    = $columnsPanel.find('input[type="checkbox"]');

            function sopPreorderApplyColumnVisibility() {
                $columnCheckboxes.each(function() {
                    var columnKey = $(this).data('column');
                    if ( ! columnKey ) {
                        return;
                    }
                    var show = $(this).is(':checked');
                    $table.find('[data-column="' + columnKey + '"]').toggle(show);
                });
            }

            // Any text/number input or textarea inside the table is considered an edit.
            $table.on('change input', 'input[type="text"], input[type="number"], textarea', function() {
                hasUnsavedChanges = true;
            });

            $(document).on('change input', '.sop-preorder-notes-overlay textarea', function() {
                hasUnsavedChanges = true;
            });

            // Removing or restoring rows also creates unsaved changes.
            $( document ).on( 'click', '#sop-preorder-remove-selected', function() {
                hasUnsavedChanges = true;
            } );

            $table.on( 'click', '.sop-preorder-restore-row', function() {
                hasUnsavedChanges = true;
            } );

            // Clear unsaved flag on save.
            var $saveButton = $('[name="sop_preorder_save"]');
            if ( $saveButton.length ) {
                $saveButton.on('click', function() {
                    hasUnsavedChanges = false;
                });
            }

            // Intercept filter form submits if unsaved changes exist.
            var $filterForm = $('#sop-preorder-filter-form');
            if ( $filterForm.length ) {
                $filterForm.on('submit', function(e) {
                    if ( ! hasUnsavedChanges ) {
                        return;
                    }

                    var message = 'You have unsaved changes on this sheet. Changing the filter (supplier, container, allowance, etc.) will rebuild the sheet and those changes will be lost.\n\nClick Cancel to stay on this page, or OK to discard changes and continue.';
                    if ( ! window.confirm( message ) ) {
                        e.preventDefault();
                        return false;
                    }

                    hasUnsavedChanges = false;
                });
            }

            if ( $sheetForm.length ) {
                $sheetForm.on( 'submit', function() {
                    hasUnsavedChanges = false;
                } );
            }

            // Warn on browser navigation if unsaved changes exist.
            window.addEventListener('beforeunload', function(e) {
                if ( ! hasUnsavedChanges ) {
                    return;
                }

                e.preventDefault();
                e.returnValue = '';
            });

            // Columns dropdown: open/close and update count.
            function sopPreorderUpdateColumnsToggleLabel() {
                var selectedCount = $columnCheckboxes.filter(':checked').length;
                var labelText = selectedCount + ' columns selected';
                if ( $columnsToggleButton.length ) {
                    $columnsToggleButton.text( labelText );
                }
            }

            if ( $columnsWrapper.length && $columnsToggleButton.length && $columnsPanel.length ) {
                sopPreorderUpdateColumnsToggleLabel();
                sopPreorderApplyColumnVisibility();

                $columnsToggleButton.on( 'click', function( e ) {
                    e.preventDefault();
                    var $columnsContainer = $columnsToggleButton.closest( '.sop-preorder-columns' );
                    var isOpen = $columnsContainer.hasClass( 'is-open' );

                    $columnsContainer.toggleClass( 'is-open', ! isOpen );
                    $columnsToggleButton.attr( 'aria-expanded', ! isOpen );
                } );

                $columnCheckboxes.on( 'change', function() {
                    sopPreorderUpdateColumnsToggleLabel();
                    sopPreorderApplyColumnVisibility();
                } );

                $( document ).on( 'click', function( e ) {
                    if ( ! $( e.target ).closest( '.sop-preorder-columns-popover, .sop-preorder-columns-toggle' ).length ) {
                        var $columnsContainer = $columnsToggleButton.closest( '.sop-preorder-columns' );
                        if ( $columnsContainer.hasClass( 'is-open' ) ) {
                            $columnsContainer.removeClass( 'is-open' );
                            $columnsToggleButton.attr( 'aria-expanded', 'false' );
                        }
                    }
                } );
            }

            function sopPreorderGetRowFromChild( el ) {
                var $el = $( el );
                return $el.closest( 'tr.sop-preorder-row' );
            }

            function sopPreorderGetSelectedRows() {
                var rows = [];
                $rowCheckboxes.each( function( index, checkbox ) {
                    if ( ! checkbox.checked ) {
                        return;
                    }
                    var $row = sopPreorderGetRowFromChild( checkbox );
                    if ( $row.length && ! $row.hasClass( 'sop-preorder-row-removed' ) ) {
                        rows.push( $row );
                    }
                } );
                return rows;
            }

            function sopPreorderOpenNotesOverlayForRow( $row, notesType ) {
                if ( ! $row || ! $row.length ) {
                    return;
                }

                var type = notesType || 'product';
                var $textarea = 'order' === type
                    ? $row.find( '.sop-preorder-notes-order' )
                    : $row.find( '.sop-preorder-notes-product' );
                if ( ! $textarea.length ) {
                    return;
                }

                currentNotesTextarea = $textarea.get( 0 );
                currentNotesType     = type;
                currentNotesRowIndex = $textarea.data( 'row-index' );

                var productText = $.trim( $row.find( 'td.column-name' ).text() || '' );
                $notesOverlayProduct.text( productText );

                if ( 'order' === type ) {
                    $notesOverlayTitle.text( '<?php echo esc_js( __( 'Order notes', 'sop' ) ); ?>' );
                } else {
                    $notesOverlayTitle.text( '<?php echo esc_js( __( 'Product notes', 'sop' ) ); ?>' );
                }

                $notesOverlayTextarea.val( currentNotesTextarea.value );

                $notesOverlay.show();
                $notesOverlayTextarea.focus();
            }

            function sopPreorderCloseNotesOverlay( saveChanges ) {
                if ( saveChanges && currentNotesTextarea && $notesOverlayTextarea.length ) {
                    currentNotesTextarea.value = $notesOverlayTextarea.val();
                }

                currentNotesTextarea = null;
                currentNotesType     = 'product';
                currentNotesRowIndex = null;
                $notesOverlay.hide();
            }

            // Selection: select-all and shift-click range.
            if ( $selectAllCheckbox.length && $rowCheckboxes.length ) {
                $selectAllCheckbox.on( 'change', function() {
                    var checked = this.checked;
                    $rowCheckboxes.each( function() {
                        this.checked = checked;
                    } );
                } );

                $rowCheckboxes.each( function( index ) {
                    $( this ).on( 'click', function( event ) {
                        if ( event.shiftKey && lastClickedIndex !== null ) {
                            var start   = Math.min( lastClickedIndex, index );
                            var end     = Math.max( lastClickedIndex, index );
                            var checked = this.checked;

                            $rowCheckboxes.each( function( i ) {
                                if ( i >= start && i <= end ) {
                                    this.checked = checked;
                                }
                            } );
                        }
                        lastClickedIndex = index;
                    } );
                } );
            }

            // Remove selected rows.
            if ( $removeSelectedBtn.length && $rowCheckboxes.length ) {
                $removeSelectedBtn.on( 'click', function( e ) {
                    e.preventDefault();

                    $rowCheckboxes.each( function() {
                        if ( ! this.checked ) {
                            return;
                        }
                        var $row = sopPreorderGetRowFromChild( this );
                        if ( ! $row.length ) {
                            return;
                        }

                        var $removedInput = $row.find( '.sop-preorder-removed-flag' );
                        if ( $removedInput.length ) {
                            $removedInput.val( '1' );
                        }

                        var $qtyInput = $row.find( '.sop-order-qty-input' );
                        if ( $qtyInput.length ) {
                            var currentVal = $qtyInput.val();
                            if ( currentVal && ! $row.data( 'sopLastQty' ) ) {
                                $row.data( 'sopLastQty', currentVal );
                            }
                            $qtyInput.val( '0' );
                        }

                        $row.addClass( 'sop-preorder-row-removed' );

                        if ( ! $showRemovedCheckbox.length || ! $showRemovedCheckbox.prop( 'checked' ) ) {
                            $row.hide();
                        }
                    } );

                    recalcTotals();
                } );
            }

            // Show removed toggle.
            if ( $showRemovedCheckbox.length ) {
                if ( ! $showRemovedCheckbox.prop( 'checked' ) ) {
                    $table.find( 'tr.sop-preorder-row-removed' ).hide();
                }

                $showRemovedCheckbox.on( 'change', function() {
                    var show = $( this ).prop( 'checked' );
                    var $removedRows = $table.find( 'tr.sop-preorder-row-removed' );

                    if ( show ) {
                        $removedRows.show();
                    } else {
                        $removedRows.hide();
                    }
                } );
            }

            // Open overlay for notes.
            $table.on( 'click', '.sop-preorder-notes, .sop-preorder-notes-edit-icon', function( e ) {
                e.preventDefault();
                var $row = $( this ).closest( 'tr.sop-preorder-row' );
                var notesType = $( this ).data( 'notes-type' ) || ( $( this ).hasClass( 'sop-preorder-notes-order' ) ? 'order' : 'product' );
                sopPreorderOpenNotesOverlayForRow( $row, notesType );
            } );

            // Save notes from overlay.
            $notesOverlay.on( 'click', '.sop-preorder-notes-overlay-save', function( e ) {
                e.preventDefault();
                sopPreorderCloseNotesOverlay( true );
            } );

            // Close overlay without saving.
            $notesOverlay.on( 'click', '.sop-preorder-notes-overlay-close, .sop-preorder-notes-overlay-backdrop', function( e ) {
                e.preventDefault();
                sopPreorderCloseNotesOverlay( false );
            } );

            // Restore row.
            $table.on( 'click', '.sop-preorder-restore-row', function( e ) {
                e.preventDefault();

                var $row = sopPreorderGetRowFromChild( this );
                if ( ! $row.length ) {
                    return;
                }

                var $removedInput = $row.find( '.sop-preorder-removed-flag' );
                if ( $removedInput.length ) {
                    $removedInput.val( '0' );
                }

                $row.removeClass( 'sop-preorder-row-removed' ).show();

                var $qtyInput = $row.find( '.sop-order-qty-input' );
                var lastQty   = $row.data( 'sopLastQty' );
                if ( $qtyInput.length && typeof lastQty !== 'undefined' && lastQty !== null && lastQty !== '' ) {
                    $qtyInput.val( lastQty );
                }

                hasUnsavedChanges = true;

                recalcTotals();
            } );

            function recalcTotals() {
                var totalUnits = 0;
                var totalSkus = 0;
                var totalCostGbp = 0;
                var totalCostSupplier = 0;
                var totalCbm = 0;

                $table.find('tbody tr').each(function() {
                    var $row = $(this);
                    var removedFlag = $row.find('.sop-preorder-removed-flag').val();
                    if ( removedFlag === '1' ) {
                        return;
                    }

                    var qty = parseFloat($row.find('.sop-order-qty-input').val()) || 0;
                    if ( qty <= 0 ) {
                        return;
                    }

                    var costGbp = parseFloat($row.find('.sop-line-total-gbp').data('cost-gbp')) || 0;
                    var costSupplier = parseFloat($row.find('.sop-line-total-supplier').data('cost-supplier')) || 0;
                    var cubicCm = parseFloat($row.find('.column-cubic-item').data('cubic-cm')) || 0;

                    var lineTotalGbp = qty * costGbp;
                    var lineTotalSupplier = qty * costSupplier;

                    $row.find('.sop-line-total-gbp').text(lineTotalGbp.toFixed(2));
                    $row.find('.sop-line-total-supplier').text(lineTotalSupplier.toFixed(2));

                    var lineCbm = 0;
                    if ( cubicCm > 0 ) {
                        lineCbm = ( cubicCm * qty ) / 1000000;
                    }

                    var $lineCbmSpan = $row.find('.column-line-cbm .sop-line-cbm-value');
                    if ( $lineCbmSpan.length ) {
                        $lineCbmSpan.text( lineCbm.toFixed(3) );
                    }

                    totalUnits += qty;
                    totalCostGbp += lineTotalGbp;
                    totalCostSupplier += lineTotalSupplier;
                    totalCbm += lineCbm;
                    totalSkus += 1;
                });

                $('#sop-total-units').text(Math.round(totalUnits));
                $('#sop-total-skus').text(totalSkus);
                $('#sop-total-cost-gbp').text(wc_price_format(totalCostGbp));
                $('#sop-total-cost-supplier').text(totalCostSupplier.toFixed(2));

                var usedCbmPercent = 0;
                if ( containerCbm > 0 && totalCbm > 0 ) {
                    usedCbmPercent = ( totalCbm / containerCbm ) * 100;
                }

                // Clamp bar width between 0 and 100, but show the raw percentage in the label.
                var usedCbmBar = usedCbmPercent;
                if ( usedCbmBar < 0 ) {
                    usedCbmBar = 0;
                } else if ( usedCbmBar > 100 ) {
                    usedCbmBar = 100;
                }

                $('.sop-cbm-bar').css('width', usedCbmBar + '%');
                $('#sop-cbm-label').text(usedCbmPercent.toFixed(1) + '%');
            }

            function wc_price_format(amount) {
                return '<?php echo esc_js( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?> ' + amount.toFixed(2);
            }

            function sopPreorderParseNumber(val) {
                var num = parseFloat(String(val).replace(/,/g, ''));
                return isNaN(num) ? 0 : num;
            }

            function sopPreorderAdjustCartonWidth(inputEl) {
                if ( ! inputEl ) {
                    return;
                }
                var len = inputEl.value.length || 1;
                var px = Math.min(260, Math.max(90, 10 + len * 8));
                inputEl.style.width = px + 'px';
            }

            function sopPreorderShowCartonTooltip(inputEl, message) {
                if ( ! inputEl || ! message ) {
                    return;
                }
                var rect = inputEl.getBoundingClientRect();
                var tooltip = document.createElement('div');
                tooltip.className = 'sop-preorder-carton-tooltip';
                tooltip.textContent = message;
                document.body.appendChild(tooltip);

                var top = window.scrollY + rect.top - tooltip.offsetHeight - 6;
                var left = window.scrollX + rect.left;
                tooltip.style.top = top + 'px';
                tooltip.style.left = left + 'px';

                // Clamp tooltip horizontally within the current viewport so it doesn't go off-screen.
                (function() {
                    var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft || 0;
                    var viewportWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || 0;
                    var tooltipWidth = tooltip.offsetWidth || 0;
                    var margin = 8;

                    var currentLeft = parseFloat(tooltip.style.left) || 0;
                    var minLeft = scrollLeft + margin;
                    var maxLeft = scrollLeft + viewportWidth - tooltipWidth - margin;

                    // If the viewport is very narrow, avoid inverting the range.
                    if ( maxLeft < minLeft ) {
                        maxLeft = minLeft;
                    }

                    if ( currentLeft < minLeft ) {
                        currentLeft = minLeft;
                    } else if ( currentLeft > maxLeft ) {
                        currentLeft = maxLeft;
                    }

                    tooltip.style.left = currentLeft + 'px';
                })();

                requestAnimationFrame(function() {
                    tooltip.style.opacity = '1';
                });

                setTimeout(function() {
                    tooltip.style.opacity = '0';
                    setTimeout(function() {
                        if ( tooltip && tooltip.parentNode ) {
                            tooltip.parentNode.removeChild(tooltip);
                        }
                    }, 200);
                }, 5000);
            }

            function sopPreorderShowCartonInvalidRangeTooltip(inputEl, silent) {
                if ( silent || ! inputEl ) {
                    return;
                }
                sopPreorderShowCartonTooltip(
                    inputEl,
                    '<?php echo esc_js( __( 'Invalid range, use e.g. 1-5,8,11-13', 'sop' ) ); ?>'
                );
            }

            function sopPreorderNormalizeCartonValue(inputEl, options) {
                if ( ! inputEl ) {
                    return null;
                }

                var opts = options || {};
                var silent = !!opts.silent;
                var raw = inputEl.value || '';

                var cleaned = raw.toString().replace(/[^0-9,\-]+/gi, '');
                cleaned = cleaned.replace(/,+/g, ',').replace(/-+/g, '-');
                cleaned = cleaned.replace(/^,|,$/g, '');

                var tokens = cleaned.split(',').filter(Boolean);
                var parsed = [];

                tokens.forEach(function(token) {
                    var singleMatch = token.match(/^(\d+)$/);
                    if ( singleMatch ) {
                        var num = parseInt(singleMatch[1], 10);
                        parsed.push({ start: num, end: num, display: String(num) });
                        return;
                    }

                    var rangeMatch = token.match(/^(\d+)-(\d+)$/);
                    if ( rangeMatch ) {
                        var start = parseInt(rangeMatch[1], 10);
                        var end = parseInt(rangeMatch[2], 10);
                        if ( start > end ) {
                            var tmp = start;
                            start = end;
                            end = tmp;
                        }
                        parsed.push({ start: start, end: end, display: start + '-' + end });
                    }
                });

                if ( parsed.length ) {
                    parsed.sort(function(a, b) {
                        return a.start - b.start;
                    });
                }

                var rebuiltTokens = parsed.map(function(item) {
                    return item.display;
                });
                var rebuilt = rebuiltTokens.join(',');
                var sortMin = parsed.length ? parsed[0].start : null;

                var row = inputEl.closest('tr.sop-preorder-row');
                var td = inputEl.closest('td');

                if ( parsed.length === 0 ) {
                    inputEl.value = '';
                    if ( td ) {
                        td.dataset.sortValue = '';
                    }
                    if ( row ) {
                        row.dataset.sortCartonNo = '';
                    }
                    sopPreorderShowCartonInvalidRangeTooltip(inputEl, silent);
                    return null;
                }

                var strippedLetters = raw !== cleaned;
                if ( rebuilt !== cleaned || strippedLetters ) {
                    inputEl.value = rebuilt;
                    if ( strippedLetters ) {
                        sopPreorderShowCartonInvalidRangeTooltip(inputEl, silent);
                    }
                }

                if ( td ) {
                    td.dataset.sortValue = ( sortMin !== null ) ? sortMin : '';
                }
                if ( row ) {
                    row.dataset.sortCartonNo = ( sortMin !== null ) ? sortMin : '';
                }

                return sortMin;
            }

            function sopPreorderGetSortValue($row, sortKey, columnIndex) {
                var $cell = $row.find('td[data-sort-key="' + sortKey + '"]').first();

                if ( ! $cell.length && typeof columnIndex === 'number' ) {
                    $cell = $row.children('td').eq(columnIndex);
                }

                if ( $cell.length ) {
                    var dataValue = $cell.data('sortValue');
                    if ( typeof dataValue !== 'undefined' ) {
                        return dataValue;
                    }

                    var dataNum = $cell.data('sortNum');
                    if ( typeof dataNum !== 'undefined' ) {
                        return dataNum;
                    }

                    var dataText = $cell.data('sortText');
                    if ( typeof dataText !== 'undefined' ) {
                        return dataText;
                    }
                }

                switch ( sortKey ) {
                    case 'carton_no':
                        var rowNode = $row.get( 0 );
                        if ( rowNode && typeof rowNode.dataset.sortCartonNo !== 'undefined' ) {
                            return rowNode.dataset.sortCartonNo;
                        }
                        return $row.find('td[data-sort-key="carton_no"]').data('sortValue');
                    case 'sku':
                        return ($row.find('.column-sku textarea').val() || '').replace(/\s+/g, ' ').trim();
                    case 'name':
                        return $row.find('.column-name').text() || '';
                    case 'location':
                        return $row.find('.column-location').text() || '';
                    case 'brand':
                        return $row.find('.column-brand').text() || '';
                    case 'category':
                        return $row.find('.column-category').text() || '';
                    case 'notes':
                        return $row.find('.column-notes textarea').val() || '';
                    case 'order_notes':
                        return $row.find('.column-order-notes textarea').val() || '';
                    case 'cost':
                        return parseFloat($row.find('.column-cost-supplier input').val()) || 0;
                    case 'stock':
                        return sopPreorderParseNumber($row.find('.column-stock').text());
                    case 'inbound':
                        return sopPreorderParseNumber($row.find('.column-inbound').text());
                    case 'moq':
                        return parseFloat($row.find('.column-min-order input').val()) || 0;
                    case 'soq':
                        return sopPreorderParseNumber($row.find('.column-suggested').text());
                    case 'order_qty':
                        return parseFloat($row.find('.sop-order-qty-input').val()) || 0;
                    case 'total':
                        return sopPreorderParseNumber($row.find('.sop-line-total-supplier').text());
                    case 'cubic':
                        return sopPreorderParseNumber($row.find('.column-cubic-item').text());
                    case 'line_cbm':
                        return sopPreorderParseNumber($row.find('.column-line-cbm').text());
                    case 'price_ex':
                        return sopPreorderParseNumber($row.find('.column-regular-unit').text());
                    case 'line_ex':
                        return sopPreorderParseNumber($row.find('.column-regular-line').text());
                    case 'unit_price_usd':
                    case 'cost_usd':
                        return parseFloat($row.find('.column-cost-usd').data('sort-value')) || 0;
                    default:
                        return 0;
                }
            }

            var preorderWrapper = document.getElementById('sop-preorder-wrapper');
            var rmbToUsdRate = preorderWrapper ? parseFloat(preorderWrapper.getAttribute('data-rmb-to-usd-rate') || '0') : 0;

            if ( preorderWrapper ) {
                preorderWrapper.addEventListener('input', function( e ) {
                    var input = e.target.closest('.sop-preorder-cost-rmb');
                    if ( ! input || ! rmbToUsdRate ) {
                        return;
                    }

                    var row = input.closest('.sop-preorder-row');
                    if ( ! row ) {
                        return;
                    }

                    var rmbValue = parseFloat(String(input.value).replace(',', '.')) || 0;
                    var usdValue = rmbValue * rmbToUsdRate;

                    var usdDisplay = row.querySelector('.sop-preorder-cost-usd');
                    if ( usdDisplay ) {
                        usdDisplay.textContent = usdValue > 0 ? usdValue.toFixed(2) : '-';
                    }

                    var usdTd = usdDisplay ? usdDisplay.closest('td') : null;
                    if ( usdTd ) {
                        usdTd.dataset.sortValue = usdValue > 0 ? usdValue.toFixed(6) : '0';
                    }
                });
            }

            $table.on('input', '.sop-preorder-carton-input', function() {
                sopPreorderAdjustCartonWidth(this);
            });

            $table.on('blur change', '.sop-preorder-carton-input', function() {
                sopPreorderNormalizeCartonValue(this);
                sopPreorderAdjustCartonWidth(this);
            });

            $table.find('.sop-preorder-carton-input').each(function() {
                sopPreorderNormalizeCartonValue(this, { silent: true });
                sopPreorderAdjustCartonWidth(this);
            });

            $table.on('input', '.sop-order-qty-input, .sop-cost-supplier-input', function() {
                recalcTotals();
            });

            $('.sop-rounding-controls button[data-round-mode]').on('click', function(e) {
                e.preventDefault();

                var mode = $(this).data('round-mode'); // 'up' or 'down'
                var step = parseInt($('.sop-round-step').val(), 10) || 1;
                var selectedRows = sopPreorderGetSelectedRows();
                var $qtyInputs;

                if ( selectedRows.length > 0 ) {
                    $qtyInputs = $();
                    $.each( selectedRows, function( i, $row ) {
                        $qtyInputs = $qtyInputs.add( $row.find( '.sop-order-qty-input' ) );
                    } );
                } else {
                    $qtyInputs = $table.find( '.sop-order-qty-input' );
                }

                $qtyInputs.each(function() {
                    var val = parseFloat($(this).val()) || 0;
                    var rounded = val;

                    if (mode === 'up') {
                        rounded = Math.ceil(val / step) * step;
                    } else if (mode === 'down') {
                        rounded = Math.floor(val / step) * step;
                    }

                    if (rounded < 0) {
                        rounded = 0;
                    }

                    $(this).val(rounded);
                    hasUnsavedChanges = true;
                    $(this).trigger('change');
                });

                recalcTotals();
            });

            $('#sop-apply-soq-to-qty').on('click', function(e) {
                e.preventDefault();

                $table.find('tbody tr').each(function() {
                    var $row = $( this );

                    if ( $row.hasClass( 'sop-preorder-row-removed' ) ) {
                        return;
                    }

                    var $qtyInput = $row.find( 'input.sop-preorder-qty' );
                    var $soqEl    = $row.find( '.sop-preorder-soq' );
                    var $moqInput = $row.find( 'input.sop-preorder-moq' );

                    if ( ! $qtyInput.length || ! $soqEl.length ) {
                        return;
                    }

                    var qtyVal = parseFloat( $qtyInput.val() );
                    if ( isNaN( qtyVal ) ) {
                        qtyVal = 0;
                    }

                    if ( qtyVal !== 0 ) {
                        return;
                    }

                    var soqVal = parseFloat( $soqEl.data( 'soq' ) );
                    var moqVal = $moqInput.length ? parseFloat( $moqInput.val() ) : 0;

                    if ( isNaN( soqVal ) || soqVal < 0 ) {
                        soqVal = 0;
                    }
                    if ( isNaN( moqVal ) || moqVal < 0 ) {
                        moqVal = 0;
                    }

                    // If Suggested Order Qty is zero or less, skip this row (ignore MOQ).
                    if ( soqVal <= 0 ) {
                        return;
                    }

                    var targetQty = Math.max( soqVal, moqVal );
                    if ( targetQty > 0 ) {
                        targetQty = Math.ceil( targetQty );
                        $qtyInput.val( targetQty );
                        hasUnsavedChanges = true;
                        $qtyInput.trigger('change');
                    }
                });

                recalcTotals();
            });

            var sopNumericSortKeys = {
                carton_no: true,
                cost: true,
                stock: true,
                inbound: true,
                moq: true,
                soq: true,
                order_qty: true,
                total: true,
                cubic: true,
                line_cbm: true,
                price_ex: true,
                line_ex: true,
                unit_price_usd: true,
                cost_usd: true
            };

            $table.find('th[data-sort]').on('click', function() {
                var $th = $(this);
                var sortKey = $th.data('sort-key') || $th.data('sort');
                var isAsc = !$th.hasClass('sorted-asc');
                var columnIndex = $th.index();

                $table.find('th[data-sort]').removeClass('sorted-asc sorted-desc');
                $th.addClass(isAsc ? 'sorted-asc' : 'sorted-desc');

                var rows = $table.find('tbody tr').get();

                rows.sort(function(a, b) {
                    var rawA = sopPreorderGetSortValue($(a), sortKey, columnIndex);
                    var rawB = sopPreorderGetSortValue($(b), sortKey, columnIndex);

                    if ( sopNumericSortKeys[sortKey] ) {
                        if ( sortKey === 'carton_no' ) {
                            var numA = parseFloat(rawA);
                            var numB = parseFloat(rawB);
                            if ( ! isFinite(numA) ) {
                                numA = Number.POSITIVE_INFINITY;
                            }
                            if ( ! isFinite(numB) ) {
                                numB = Number.POSITIVE_INFINITY;
                            }
                            return isAsc ? numA - numB : numB - numA;
                        }

                        var numA = sopPreorderParseNumber(rawA);
                        var numB = sopPreorderParseNumber(rawB);
                        return isAsc ? numA - numB : numB - numA;
                    }

                    var textA = $.trim(String(rawA || '')).toLowerCase();
                    var textB = $.trim(String(rawB || '')).toLowerCase();

                    return isAsc ? textA.localeCompare(textB) : textB.localeCompare(textA);
                });

                $.each(rows, function(index, row) {
                    $table.find('tbody').append(row);
                });
            });

            // ------------------------------------------------------------------
            // Quick SKU finder: behave like Ctrl+F on the current table
            // ------------------------------------------------------------------
            (function() {
                var $skuInput = $('#sop_sku_filter');
                if ( ! $skuInput.length ) {
                    return;
                }

                var $tableFrame = $('.sop-preorder-table-frame');
                if ( ! $tableFrame.length ) {
                    $tableFrame = $('.sop-preorder-table-wrapper');
                }

                var $tableLocal = $tableFrame.find('table.sop-preorder-table');
                if ( ! $tableLocal.length ) {
                    return;
                }

                function sopScrollRowIntoView( $row ) {
                    if ( ! $row || ! $row.length ) {
                        return;
                    }

                    var rowEl = $row[0];

                    // Find the scrollable wrapper for the pre-order table.
                    var wrapper = document.querySelector( '.sop-preorder-table-wrapper' );
                    if ( ! wrapper ) {
                        if ( rowEl && rowEl.scrollIntoView ) {
                            rowEl.scrollIntoView( { behavior: 'auto', block: 'start', inline: 'nearest' } );
                        }
                        return;
                    }

                    // Get bounding rects for wrapper and row.
                    var wrapperRect = wrapper.getBoundingClientRect();
                    var rowRect     = rowEl.getBoundingClientRect();

                    // Row's current position inside the wrapper viewport (can be negative if above).
                    var rowTopInsideWrapper = rowRect.top - wrapperRect.top;

                    // Convert that to a content-space coordinate by adding the current scrollTop.
                    // This gives us a stable "row top within the scrollable content".
                    var rowTopInContent = wrapper.scrollTop + rowTopInsideWrapper;

                    // We want the row to sit right at (or just below) the top of the wrapper.
                    // Use a small padding so it's fully visible and not touching the border.
                    var targetScrollTop = Math.max( rowTopInContent - 4, 0 );

                    wrapper.scrollTop = targetScrollTop;
                }

                function scrollToSku( rawSku ) {
                    var sku = $.trim( rawSku || '' );
                    if ( ! sku ) {
                        return;
                    }

                    var $skuCell = $tableLocal.find( 'td.column-sku input, td.column-sku' ).filter( function() {
                        var $el = $( this );
                        var val = $el.is( 'input' ) ? $el.val() : $el.text();
                        return $.trim( val ) === sku;
                    } ).first();

                    if ( ! $skuCell.length ) {
                        return;
                    }

                    var $row = $skuCell.closest( 'tr' );

                    $tableLocal.find( 'tr.sop-preorder-sku-hit' ).removeClass( 'sop-preorder-sku-hit' );
                    $row.addClass( 'sop-preorder-sku-hit' );

                    sopScrollRowIntoView( $row );
                }

                $skuInput.on( 'keydown', function( e ) {
                    if ( e.key === 'Enter' || e.keyCode === 13 ) {
                        e.preventDefault();
                        scrollToSku( $skuInput.val() );
                    }
                } );

                $( '.sop-preorder-sku-icon' ).on( 'click', function( e ) {
                    e.preventDefault();
                    scrollToSku( $skuInput.val() );
                } );
            })();

            // ------------------------------------------------------------------
            // Rates & Dates modal
            // ------------------------------------------------------------------
            (function() {
                var $overlay = $( '#sop-rates-dates-overlay' );
                var $toggle  = $( '.sop-rates-dates-toggle' );
                if ( ! $overlay.length || ! $toggle.length ) {
                    return;
                }

                var $close = $overlay.find( '.sop-rates-dates-close' );

                var $poBaseLabel        = $( '#sop-po-base-total-rmb' );
                var $poTotalLabel       = $( '#sop-po-total-rmb' );
                var $poBalanceLabel     = $( '#sop-po-balance-rmb' );
                var $balanceUsdLabel    = $( '#sop-po-balance-usd' );
                var $balanceUsdInput    = $( '#sop-po-balance-usd-input' );
                var $depositUsdInput    = $( 'input[name=\"sop_po_deposit_usd\"]' );
                var $depositRmbInput    = $( '#sop-po-deposit-rmb' );
                var $depositFxRateInput = $( '#sop-po-deposit-fx-rate' );
                var $balanceFxRateInput = $( '#sop-po-balance-fx-rate' );
                var $extrasTable        = $( '.sop-po-items-table' );
                var $extrasAmountInputs = $extrasTable.find( '.sop-po-extra-amount' );
                var baseTotalRmb        = parseFloat( $poBaseLabel.data( 'base-total-rmb' ) ) || 0;
                var rmbPerUsd           = parseFloat( $( '#sop-po-rmb-per-usd' ).val() ) || 1;
                var isLockedExtras      = $extrasTable.data( 'locked' ) === 1 || $extrasTable.data( 'locked' ) === '1';

                function recalcPoTotals() {
                    var extrasTotalRmb = 0;
                    $extrasAmountInputs.each( function() {
                        var v = parseFloat( $( this ).val() );
                        if ( ! isNaN( v ) ) {
                            extrasTotalRmb += v;
                        }
                    } );

                    var poTotal = baseTotalRmb + extrasTotalRmb;
                    if ( poTotal < 0 ) {
                        poTotal = 0;
                    }

                    var depositUsd = parseFloat( $depositUsdInput.val() );
                    if ( isNaN( depositUsd ) ) {
                        depositUsd = 0;
                    }

                    var depositFxRate = parseFloat( $depositFxRateInput.val() );
                    if ( isNaN( depositFxRate ) || depositFxRate <= 0 ) {
                        depositFxRate = rmbPerUsd;
                    }

                    var depositRmbFromUsd = depositUsd * depositFxRate;
                    $depositRmbInput.val( depositRmbFromUsd ? depositRmbFromUsd.toFixed( 2 ) : '' );

                    var depositRmbVal = parseFloat( $depositRmbInput.val() );
                    if ( isNaN( depositRmbVal ) ) {
                        depositRmbVal = 0;
                    }

                    var balanceRmb = poTotal - depositRmbVal;
                    if ( balanceRmb < 0 ) {
                        balanceRmb = 0;
                    }
                    $poTotalLabel.text( poTotal.toFixed( 2 ) );
                    $poBalanceLabel.text( balanceRmb.toFixed( 2 ) );

                    var balanceFxRate = parseFloat( $balanceFxRateInput.val() );
                    if ( isNaN( balanceFxRate ) || balanceFxRate <= 0 ) {
                        balanceFxRate = rmbPerUsd;
                    }
                    var balanceUsd = ( balanceRmb > 0 && balanceFxRate > 0 ) ? ( balanceRmb / balanceFxRate ) : 0;
                    $balanceUsdLabel.text( balanceUsd.toFixed( 2 ) );
                    $balanceUsdInput.val( balanceUsd.toFixed( 2 ) );
                }

                function bindExtras() {
                    $extrasAmountInputs = $extrasTable.find( '.sop-po-extra-amount' );
                    $extrasAmountInputs.off( 'input change' ).on( 'input change', recalcPoTotals );
                }

                if ( ! isLockedExtras ) {
                    $( '.sop-po-add-extra' ).on( 'click', function() {
                        var removeCell = '<td class=\"column-actions\"><button type=\"button\" class=\"button-link sop-po-extra-remove\">&times;</button></td>';
                        var rowHtml = '<tr class=\"sop-po-extra-row\">' +
                            '<td><input type=\"text\" name=\"sop_po_extra_label[]\" value=\"\" /></td>' +
                            '<td class=\"column-amount\"><input type=\"number\" step=\"0.01\" class=\"sop-po-extra-amount\" name=\"sop_po_extra_amount[]\" value=\"0\" /></td>' +
                            removeCell +
                            '</tr>';
                        $( '#sop-po-items-body' ).append( rowHtml );
                        bindExtras();
                        recalcPoTotals();
                    } );

                    $extrasTable.on( 'click', '.sop-po-extra-remove', function( e ) {
                        e.preventDefault();
                        $( this ).closest( 'tr' ).remove();
                        bindExtras();
                        recalcPoTotals();
                    } );
                }

                bindExtras();
                $( 'input[name=\"sop_po_deposit_usd\"], #sop-po-deposit-fx-rate, #sop-po-balance-fx-rate' ).on( 'input change', recalcPoTotals );
                recalcPoTotals();

                function closeModal() {
                    $overlay.css( 'display', 'none' );
                }

                $toggle.on( 'click', function() {
                    $overlay.css( 'display', 'flex' );
                    recalcPoTotals();
                } );

                $close.on( 'click', function( e ) {
                    e.preventDefault();
                    closeModal();
                } );

                $overlay.on( 'click', function( e ) {
                    if ( e.target === $overlay.get( 0 ) ) {
                        closeModal();
                    }
                } );
            })();

            // ------------------------------------------------------------------
            // PO dates auto-suggest (load/arrival based on order date + holidays + shipping)
            // ------------------------------------------------------------------
            (function() {
                var $orderDate    = $( 'input[name=\"sop_po_order_date\"]' );
                var $loadDate     = $( 'input[name=\"sop_po_load_date\"]' );
                var $arrivalDate  = $( 'input[name=\"sop_po_arrival_date\"]' );
                var $holidayStart = $( 'input[name=\"sop_po_holiday_start\"]' );
                var $holidayEnd   = $( 'input[name=\"sop_po_holiday_end\"]' );

                var leadWeeks = parseInt( $( '#sop-po-lead-weeks' ).val(), 10 ) || 0;
                var supplierShippingDays = parseInt( $( '#sop-po-shipping-days' ).val(), 10 );
                if ( isNaN( supplierShippingDays ) || supplierShippingDays < 0 ) {
                    supplierShippingDays = 30;
                }

                var holidayPeriodsMd = [];
                try {
                    var rawMd = $( '#sop-po-supplier-holiday-periods' ).val();
                    if ( rawMd ) {
                        var decoded = JSON.parse( rawMd );
                        if ( Array.isArray( decoded ) ) {
                            holidayPeriodsMd = decoded;
                        }
                    }
                } catch ( e ) {
                    holidayPeriodsMd = [];
                }

                function sopAddDaysToDate( ymd, days ) {
                    if ( ! ymd ) {
                        return '';
                    }
                    var parts = ymd.split( '-' );
                    if ( parts.length !== 3 ) {
                        return ymd;
                    }
                    var year  = parseInt( parts[0], 10 );
                    var month = parseInt( parts[1], 10 ) - 1;
                    var day   = parseInt( parts[2], 10 );
                    var d     = new Date( year, month, day );
                    if ( isNaN( d.getTime() ) ) {
                        return ymd;
                    }
                    d.setDate( d.getDate() + days );
                    var m  = '' + ( d.getMonth() + 1 );
                    var dd = '' + d.getDate();
                    var yyyy = d.getFullYear();
                    if ( m.length < 2 ) { m = '0' + m; }
                    if ( dd.length < 2 ) { dd = '0' + dd; }
                    return yyyy + '-' + m + '-' + dd;
                }

                function sopBuildHolidayYmdFromMd( orderYmd, md ) {
                    if ( ! orderYmd || ! md ) {
                        return '';
                    }
                    var parts = orderYmd.split( '-' );
                    if ( parts.length !== 3 ) {
                        return '';
                    }
                    var year  = parseInt( parts[0], 10 );
                    var mdParts = md.split( '-' );
                    if ( mdParts.length !== 2 ) {
                        return '';
                    }
                    var month = parseInt( mdParts[0], 10 );
                    var day   = parseInt( mdParts[1], 10 );
                    if ( ! month || ! day ) {
                        return '';
                    }
                    var m  = ( month < 10 ? '0' + month : '' + month );
                    var dd = ( day < 10 ? '0' + day : '' + day );
                    return year + '-' + m + '-' + dd;
                }

                function sopCountHolidayDays( orderYmd, baseArrivalYmd, holidayStartYmd, holidayEndYmd ) {
                    if ( ! orderYmd || ! baseArrivalYmd || ! holidayStartYmd || ! holidayEndYmd ) {
                        return 0;
                    }

                    var start = new Date( orderYmd );
                    var end   = new Date( baseArrivalYmd );
                    var hStart = new Date( holidayStartYmd );
                    var hEnd   = new Date( holidayEndYmd );

                    if ( isNaN( start.getTime() ) || isNaN( end.getTime() ) || isNaN( hStart.getTime() ) || isNaN( hEnd.getTime() ) ) {
                        return 0;
                    }

                    if ( hEnd < hStart ) {
                        return 0;
                    }

                    var dayMs = 24 * 60 * 60 * 1000;
                    var overlapStart = start > hStart ? start : hStart;
                    var overlapEnd   = end < hEnd ? end : hEnd;

                    if ( overlapEnd <= overlapStart ) {
                        return 0;
                    }

                    var diffMs = overlapEnd.getTime() - overlapStart.getTime();
                    var days   = Math.round( diffMs / dayMs );
                    return days > 0 ? days : 0;
                }

                function sopRecalcPoDatesFromOrder() {
                    if ( ! $orderDate.length || ! $loadDate.length || ! $arrivalDate.length ) {
                        return;
                    }

                    var orderYmd = $orderDate.val();
                    if ( ! orderYmd ) {
                        return;
                    }

                var baseLeadDays = leadWeeks * 7;
                if ( ! baseLeadDays ) {
                    return;
                }

                // Handling portion excludes shipping.
                var handlingDays = baseLeadDays - supplierShippingDays;
                if ( handlingDays < 0 ) {
                    handlingDays = 0;
                }

                // Holiday overrides for this PO.
                var holidayStartYmd = $holidayStart.val();
                var holidayEndYmd   = $holidayEnd.val();

                // Prefill PO holiday fields from supplier periods if blank.
                if ( ! holidayStartYmd && holidayPeriodsMd.length ) {
                    var first = holidayPeriodsMd[0];
                    if ( first && first.start ) {
                        holidayStartYmd = sopBuildHolidayYmdFromMd( orderYmd, first.start );
                        if ( holidayStartYmd ) {
                            $holidayStart.val( holidayStartYmd );
                        }
                    }
                }
                if ( ! holidayEndYmd && holidayPeriodsMd.length ) {
                    var firstEndMd = holidayPeriodsMd[0].end || '';
                    if ( firstEndMd ) {
                        var tmpEnd = sopBuildHolidayYmdFromMd( orderYmd, firstEndMd );
                        if ( tmpEnd && holidayStartYmd ) {
                            var startDate = new Date( holidayStartYmd );
                            var endDate   = new Date( tmpEnd );
                            if ( endDate < startDate ) {
                                var parts = tmpEnd.split( '-' );
                                var ny    = startDate.getFullYear() + 1;
                                tmpEnd    = ny + '-' + parts[1] + '-' + parts[2];
                            }
                        }
                        holidayEndYmd = tmpEnd;
                        $holidayEnd.val( holidayEndYmd );
                    }
                }

                // Handling window end (ignoring holidays).
                var handlingEndYmd = sopAddDaysToDate( orderYmd, handlingDays );

                var totalHolidayDays = 0;

                // Holidays only extend handling, not shipping.
                if ( holidayPeriodsMd.length && handlingDays > 0 ) {
                    for ( var i = 0; i < holidayPeriodsMd.length; i++ ) {
                        var hp = holidayPeriodsMd[ i ];
                        if ( ! hp || ! hp.start || ! hp.end ) {
                            continue;
                        }
                        var hsYmd = sopBuildHolidayYmdFromMd( orderYmd, hp.start );
                        var heYmd = sopBuildHolidayYmdFromMd( orderYmd, hp.end );
                        if ( hsYmd && heYmd ) {
                            var hsDate = new Date( hsYmd );
                            var heDate = new Date( heYmd );
                            if ( heDate < hsDate ) {
                                var partsEnd = heYmd.split( '-' );
                                var ny2      = hsDate.getFullYear() + 1;
                                heYmd        = ny2 + '-' + partsEnd[1] + '-' + partsEnd[2];
                            }
                            totalHolidayDays += sopCountHolidayDays( orderYmd, handlingEndYmd, hsYmd, heYmd );
                        }
                    }
                }

                if ( totalHolidayDays < 0 ) {
                    totalHolidayDays = 0;
                }

                var adjustedHandlingDays = handlingDays + totalHolidayDays;

                // Container load date: order date + adjusted handling.
                var loadYmd = sopAddDaysToDate( orderYmd, adjustedHandlingDays );
                $loadDate.val( loadYmd );

                // ETA: load date + shipping days (holidays do not affect shipping).
                var etaYmd = sopAddDaysToDate( loadYmd, supplierShippingDays );
                $arrivalDate.val( etaYmd );
            }

            if ( $orderDate.length ) {
                $orderDate.on( 'change', sopRecalcPoDatesFromOrder );
                // Recalculate on load if an order date already exists.
                if ( $orderDate.val() ) {
                    sopRecalcPoDatesFromOrder();
                }
            }
        })();

            recalcTotals();
        });
    </script>
    <?php
}



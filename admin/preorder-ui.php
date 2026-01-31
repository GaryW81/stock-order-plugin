<?php
/*** Stock Order Plugin - Phase 4.1 - Pre-Order Sheet UI (admin only) V13.06 *
 * - V13.06 - UI: fix container controls overlap by enforcing wrap-friendly flex sizing.
 * - V13.05 - No-op: version bump to record reverted baseline for Pre-Order UI.
 * - V13.04 - UI: prevent container controls overlap on narrow screens with safer wrapping.
 * - V13.03 - UI: set Allowance input width to 60px for header alignment.
 * - V13.02 - UI: reduce Additional items CBM input width to 110px for header alignment.
 * - V13.01 - UI: align container controls row with stacked labels + top-aligned pallet checkbox.
 * - V13.00 - UI: prevent duplicate header icons by removing data-icon background layer (single <img> render).
 * - V12.99 - UI: prevent header SVG icon first-paint blowout by constraining icon dimensions.
 * - V12.98 - Notes: render rich product/internal notes with kses-sanitised HTML.
 * - V12.97 - Notes: clamp preview height to prevent row expansion.
 * - V12.95 - Fix: constrain header icon <img> sizing to prevent layout blowout.
 * - V12.94 - Fix sop_preorder_render_header_icon() to return icon HTML (data URI or dashicon fallback).
 * - V12.93 - UI: clip header icon background to content box so divider spacing is respected.
 * - V12.92 - UI: add balanced padding around header icon divider line (8px each side).
 * - V12.91 - Add Additional items CBM field and include it in container fill calculations.
 * - V12.90 - Pass inbound schedule map into forecast for ETA-aware inbound.
 * - V12.89 - Wire inbound_qty to open/locked/GI sheets via inbound qty map.
 * - V12.88 - UI: raise Columns button z-index so full area is clickable.
 * - V12.87 - Readonly saved sheets now hide removed/zero-qty rows to preserve sheet memory.
 * - V12.86 - Persist table sort state across reload/update (per saved sheet).
 * - V12.85 - Canonicalise product notes meta key to _sop_product_notes.
 * - V12.84 - UI: add internal product notes column (preorder + goods-in).
 * - V12.83 - UI: fix mobile rounding toolbar overflow (disable column wrapping).
 * - V12.82 - UI: mobile rounding card stack bulk actions (fix off-screen controls).
 * - V12.81 - UI: fix mobile rounding card overflow.
 * - V12.80 - UI: mobile rounding card spacing fix.
 * - V12.79 - UI: mobile layout fixes for header cards.
 * - V12.78 - UI: Supplier SKUs '+more' shown on 3rd line.
 * - V12.77 - UI: compact Supplier SKUs display + widen column.
 * - V12.76 - Use underscored supplier ID field name.
 * - V12.75 - Use sheet-line removed state (no product meta).
 * - V12.74 - Store removed state per sheet line.
 * - V12.73 - UI: consolidate deferred header SVG logic.
 * - V12.72 - UI: defer header SVG background to prevent first-paint splash.
 * - V12.71 - UI: gate header icon opacity on initial paint.
 * - V12.70 - UI: contain header SVG icons on initial paint.
 * - V12.69 - Fix header icon MIME type for SVG data URIs.
 * - V12.68 - Switch header icons to SVG assets.
 * - V12.67 - Bulk actions respect selected rows.
 * - V12.66 - UI: enforce rounded indicator circle geometry.
 * - V12.65 - UI: rounded tick as green circle.
 * - V12.64 - UI: improve rounded tick visibility.
 * - V12.63 - UI: neon CBM fill thresholds + rounded tick indicator.
 * - V12.62 - UI: use 3-stage labels (In Progress/Ordered/Completed) for saved sheet status.
 * - V12.61 - Enforce read-only view for non-draft sheets.
 * - V12.60 - Restore saved sheet container planning values (allowance/pallet) on open.
 * - V12.59 - Fix: SKU search icon click triggers filtering.
 * - V12.58 - Persist column visibility per supplier.
 * - V12.57 - Saved sheets: overlay per-line order notes + carton no displays correctly after reload.
 * - V12.56 - PO holiday period selects overlapping supplier range (multi-period safe) and avoids mutating supplier holiday list.
 * - V12.55 - PO holiday override: only keep when overlaps handling window; clear irrelevant saved first-holiday; fix holiday separator text.
 * - V12.54 - Version bump after PO holiday period fixes.
 * - V12.53 - PO holiday period: resolve next-year occurrence + allow clearing without re-autofill.
 * - V12.52 - Remove Labels (CSV) download for saved sheets.
 * - V12.51 - Add Labels (CSV) download for saved sheets.
 * - V12.50 - Add optional Supplier SKUs column when enabled per supplier.
 * - V12.49 - Live preorder inputs keyed by product_id (SKU display-only; disable when missing pid).
 * - V12.48 - Hydrate saved sheet display rows with live WC data (preserve saved stock snapshot).
 * - V12.47 - Remove legacy XLS download options (XLSX only).
 * - V12.46 - Add Order Summary (XLSX) download option.
* - V12.45 - SOQ tooltip: restore custom tooltip + improve hover + fix scroll/hover dropouts.
 * - V12.44 - SOQ tooltip: suppress native title tooltip + improve hover hit area.
 * - V12.43 - Fix SOQ order advice tooltip hover.
 * - V12.42 - Force saved sheet container fill to use saved/derived CBM and cm3 values.
 * - V12.41 - Fix saved sheet container fill % by applying saved CBM/cm3 line data to totals.
 * - V12.40 - Fix saved-sheet container fill CBM fallbacks.
 * - V12.39 - Fix totals rendering (wc_price HTML).
 * - V12.38 - Add simple PO totals for non-RMB suppliers and keep hidden date fields always rendered.
 * - V12.37 - PO modal holiday overrides recalc load/ETA; add YMDâ‡„MD helper.
 * - V12.36 - Product title links to product edit screen.
 * - V12.35 - Fix SKU search scroll so matched row sits below sticky table header.
 * - V12.34 - Fix SKU search scroll offset so first match sits below sticky header.
 * - V12.33 - Remove View saved sheets button from header.
 * - V12.32 - New sheets always start from supplier defaults for container/pallet/allowance.
 * - V12.28 - Restore Round Up/Down actions on selected rows using current round step.
 * - V12.27 - Saved sheets always use stored supplier; new sheets use selected supplier.
 * - V12.26 - Honor saved sheet supplier when reopening; new sheets use selected supplier.
 * - V12.25 - Do not show leave-site warning when saving/updating the sheet.
 * - V12.24 - Suppress leave-site warning while saving/updating the sheet.
 * - V12.23 - New-sheet supplier change submits filter immediately to reload products.
 * - V12.22 - Supplier change on new sheets triggers native filter submit to reload products immediately.
 * - V12.21 - Supplier change on new sheets submits filter instantly to reload products.
 * - V12.20 - Auto-refresh container when supplier changes on new sheets (no extra click needed).
 * - V12.19 - Auto-refresh container when supplier changes on new sheets.
 * - V12.18 - Make "sheet saved" notice one-shot (strip sop_saved after first load).
 * - V12.16 - Use supplier-effective FX (base + FX adjustment) for PO defaults.
 * - V12.15 - PO FX defaults now follow current settings until locked.
 * - V12.14 - PO auto-dates treat holidays as non-working handling days (shipping unchanged).
 * - Implement saved sheet locking (UI disable/hide when status is locked).
 * - Uses supplier-level defaults for container type, pallet layer, and allowance when starting new sheets.
 * - Purchase Order modal refined (compact buyer/seller, PO items table, deposit/balance with FX and holiday-driven dates).
 * - Fix shipping time unit handling for PO date suggestions and adjust PO date calc so holidays only extend handling days.
 * - PO details grid layout and explicit PO field wiring for saved sheets.
 * - PO details row: PO# then single-line dates.
 * - V12.00 - Round PO FX to 3dp, right-align FX inputs, balance row layout; USD display uses balance/supplier FX.
 * - V12.01 - PO FX totals panel aligned right; balance mirrors deposit; balance FX stays empty until deposit locked.
 * - V12.02 - PO FX rounding/behaviour tweaks, right-aligned inputs, balance panel mirrors deposit and hides until locked.
 * - V11.92 - PO modal: enable inputs for drafts, save button inside modal, JSON payload + debug line, load PO extras from header notes.
 * - V11.93 - Ensure PO extras load/persist reliably; debug shows extras count.
 * - V11.94 - Treat header_notes_owner as PO payload JSON (with legacy fallback).
 * - V11.95 - Load PO payload extras directly; persist reliably.
 * - V11.97 - Supplier FX defaults with balance lock and FX summaries for PO modal.
 * - V11.96 - Version bump to reflect latest persistence fixes.
 * - Under Stock Order main menu.
 * - Supplier filter via _sop_supplier_id.
 * - 90vh scroll, sticky header, sortable columns, column visibility, rounding, CBM bar.
 * - Supplier currency-aware costs using plugin meta:
 *      _sop_cost_rmb, _sop_cost_usd, _sop_cost_eur, fallback _cogs_value for GBP.
 * - Editable & persisted per product:
 *      SKU                -> meta: _sku
 *      Notes              -> meta: _sop_product_notes
 *      Min order qty      -> meta: _sop_min_order_qty
 *      Manual order qty   -> sheet line: qty_owner
 *      Cost per unit      -> meta: _sop_cost_rmb / _sop_cost_usd / _sop_cost_eur / _cogs_value
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_preorder_render_header_icon' ) ) {
    /**
     * Render a header icon using a custom icon (SVG/PNG) when available, falling back to dashicons.
     *
     * @param string $filename               PNG filename inside assets/icons.
     * @param string $fallback_dashicon_class Dashicon class to use when PNG is missing.
     * @param string $alt                    Alt text for the icon.
     * @return string                        HTML for the icon.
     */
    function sop_preorder_render_header_icon( $filename, $fallback_dashicon_class, $alt ) {
        $data_uri = sop_preorder_get_header_icon_data_uri( $filename );

        if ( '' !== $data_uri ) {
            $icon_size = 80;
            $style     = 'width:' . $icon_size . 'px;height:' . $icon_size . 'px;max-width:' . $icon_size . 'px;max-height:' . $icon_size . 'px;';
            return '<img class="sop-preorder-header-icon-img" src="' . esc_attr( $data_uri ) . '" alt="' . esc_attr( $alt ) . '" width="' . (int) $icon_size . '" height="' . (int) $icon_size . '" style="' . esc_attr( $style ) . '" />';
        }

        if ( '' !== $fallback_dashicon_class ) {
            $icon = '<span class="dashicons ' . esc_attr( $fallback_dashicon_class ) . ' sop-preorder-header-icon-fallback" aria-hidden="true"></span>';
            if ( '' !== $alt ) {
                $icon .= '<span class="screen-reader-text">' . esc_html( $alt ) . '</span>';
            }
            return $icon;
        }

        return '';
    }
}

if ( ! function_exists( 'sop_preorder_get_header_icon_data_uri' ) ) {
    /**
     * Get a base64 data URI for a header icon PNG, with size cap.
     *
     * @param string $filename Filename inside assets/icons.
     * @return string Data URI or empty string on failure.
     */
    function sop_preorder_get_header_icon_data_uri( $filename ) {
        $root_dir = defined( 'SOP_PLUGIN_DIR' ) && SOP_PLUGIN_DIR ? trailingslashit( SOP_PLUGIN_DIR ) : trailingslashit( dirname( __FILE__, 2 ) );
        $path     = $root_dir . 'assets/icons/' . ltrim( $filename, '/' );

        if ( ! file_exists( $path ) ) {
            return '';
        }

        $size = @filesize( $path );
        if ( false === $size || $size <= 0 || $size > 250000 ) {
            return '';
        }

        $bin = @file_get_contents( $path );
        if ( ! $bin ) {
            return '';
        }

        $ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        $mime = ( 'svg' === $ext ) ? 'image/svg+xml' : 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode( $bin );
    }
}

if ( ! function_exists( 'sop_preorder_parse_supplier_sku_entries' ) ) {
    /**
     * Normalize supplier SKU text into a list of displayable entries.
     *
     * @param mixed $raw Raw meta value.
     * @return string[]
     */
    function sop_preorder_parse_supplier_sku_entries( $raw ) {
        $lines = array();

        if ( is_array( $raw ) ) {
            foreach ( $raw as $item ) {
                if ( is_string( $item ) ) {
                    $lines[] = $item;
                }
            }
        } elseif ( is_string( $raw ) ) {
            $lines[] = $raw;
        }

        if ( empty( $lines ) ) {
            return array();
        }

        $merged = implode( "\n", $lines );
        $merged = str_ireplace( array( '<br />', '<br/>', '<br>' ), "\n", $merged );

        $raw_lines = preg_split( "/\r\n|\r|\n/", $merged );
        if ( ! is_array( $raw_lines ) ) {
            return array();
        }

        $clean = array();
        foreach ( $raw_lines as $line ) {
            $line = trim( (string) $line );
            if ( '' !== $line ) {
                $clean[] = $line;
            }
        }

        if ( empty( $clean ) ) {
            return array();
        }

        $entries = array();
        $count   = count( $clean );
        for ( $i = 0; $i < $count; $i++ ) {
            $sku_line = $clean[ $i ];
            $next     = ( $i + 1 < $count ) ? $clean[ $i + 1 ] : '';

            if ( '' !== $next && preg_match( '/^\s*\d+(?:\.\d+)?\s*(x|pcs|pc|qty|units)?\s*$/i', $next ) ) {
                $entries[] = $sku_line . ' (' . trim( $next ) . ')';
                $i++;
            } else {
                $entries[] = $sku_line;
            }
        }

        return $entries;
    }
}

function sop_preorder_render_admin_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'sop' ) );
    }

    $suppliers = sop_preorder_get_suppliers();
    $settings  = sop_preorder_get_settings();

    $current_sheet_id     = isset( $_GET['sop_sheet_id'] ) ? (int) $_GET['sop_sheet_id'] : 0;
    if ( 0 === $current_sheet_id && isset( $_GET['sop_preorder_sheet_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_sheet_id = (int) $_GET['sop_preorder_sheet_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    $current_sheet        = null;
    $current_lines        = array();

    if ( $current_sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) ) {
        $current_sheet = sop_get_preorder_sheet( $current_sheet_id );
        if ( ! $current_sheet || ! is_array( $current_sheet ) ) {
            $current_sheet_id = 0;
            $current_sheet    = null;
        }
    }

    $current_supplier_id = 0;
    if ( $current_sheet_id > 0 && ! empty( $current_sheet['supplier_id'] ) ) {
        $current_supplier_id = (int) $current_sheet['supplier_id'];
    } else {
        if ( isset( $_GET['supplier_id'] ) ) {
            $current_supplier_id = (int) $_GET['supplier_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        } elseif ( isset( $_GET['_sop_supplier_id'] ) ) {
            $current_supplier_id = (int) $_GET['_sop_supplier_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ( $current_supplier_id <= 0 && ! empty( $suppliers ) ) {
            $first = reset( $suppliers );
            if ( is_array( $first ) && isset( $first['id'] ) ) {
                $current_supplier_id = (int) $first['id'];
            }
        }
    }

    $supplier = null;
    foreach ( $suppliers as $row ) {
        if ( (int) $row['id'] === $current_supplier_id ) {
            $supplier = $row;
            break;
        }
    }

    if ( ! $supplier && ! empty( $suppliers ) ) {
        $supplier            = $suppliers[0];
        $current_supplier_id = (int) $supplier['id'];
    }

    $show_supplier_skus_column = false;
    if ( function_exists( 'sop_supplier_show_supplier_skus_column' ) ) {
        $show_supplier_skus_column = sop_supplier_show_supplier_skus_column( $current_supplier_id );
    }

    $supplier_currency = 'GBP';

    if ( $supplier ) {
        $ctx = sop_preorder_resolve_supplier_params( $current_supplier_id );

        if ( ! empty( $ctx['currency_code'] ) ) {
            $supplier_currency = $ctx['currency_code'];
        }
    }

    $rmb_to_usd_rate = 0.0;
    if ( 'RMB' === $supplier_currency && $current_supplier_id > 0 && function_exists( 'sop_get_rmb_to_usd_rate_for_supplier' ) ) {
        $rmb_to_usd_rate = sop_get_rmb_to_usd_rate_for_supplier( $current_supplier_id );
    }
    $po_rmb_per_usd = ( $rmb_to_usd_rate > 0 ) ? $rmb_to_usd_rate : 1.0;
    $sop_supplier_effective_fx = 0.0;
    if ( function_exists( 'sop_get_supplier_effective_usd_to_rmb_rate' ) ) {
        $sop_supplier_effective_fx = sop_get_supplier_effective_usd_to_rmb_rate( $supplier );
    }

    $sop_icon_supplier_uri  = sop_preorder_get_header_icon_data_uri( 'supplier.svg' );
    $sop_icon_container_uri = sop_preorder_get_header_icon_data_uri( 'container.svg' );
    $sop_icon_rounding_uri  = sop_preorder_get_header_icon_data_uri( 'rounding.svg' );
    $sop_icon_ai_uri        = sop_preorder_get_header_icon_data_uri( 'ai-logo.svg' );

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
    $supplier_lead_weeks              = 0.0;
    $supplier_defaults_container      = '';
    $supplier_defaults_pallet         = false;
    $supplier_defaults_allowance      = 0;
    $lead_time_value_setting          = 0.0;
    $lead_time_unit_setting           = 'weeks';

    // Supplier PI / Rates & Dates values.
    $pi_company_name    = '';
    $pi_company_address = '';
    $pi_company_phone   = '';
    $pi_company_email   = '';
    $pi_contact_name    = '';
    $pi_bank_details    = '';
    $pi_payment_terms   = '';

    if ( $current_sheet_id <= 0 && $current_supplier_id > 0 && function_exists( 'sop_supplier_get_by_id' ) ) {
        $supplier_obj = sop_supplier_get_by_id( (int) $current_supplier_id );
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
                $lead_time_value_setting = 0.0;
                $lead_time_unit_setting  = 'weeks';

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

                if ( isset( $supplier_settings['preorder_default_container_type'] ) ) {
                    $supplier_defaults_container = (string) $supplier_settings['preorder_default_container_type'];
                }
                if ( ! empty( $supplier_settings['preorder_default_pallet_layer'] ) ) {
                    $supplier_defaults_pallet = true;
                }
                if ( array_key_exists( 'preorder_default_container_allowance', $supplier_settings ) ) {
                    $supplier_defaults_allowance = (int) $supplier_settings['preorder_default_container_allowance'];
                }

                if ( array_key_exists( 'lead_time_value', $supplier_settings ) ) {
                    $lead_time_value_setting = (float) $supplier_settings['lead_time_value'];
                }
                if ( array_key_exists( 'lead_time_unit', $supplier_settings ) && in_array( $supplier_settings['lead_time_unit'], array( 'days', 'weeks' ), true ) ) {
                    $lead_time_unit_setting = $supplier_settings['lead_time_unit'];
                }
            }
        }
        if ( $lead_time_value_setting > 0 ) {
            $supplier_lead_weeks = ( 'days' === $lead_time_unit_setting ) ? ( $lead_time_value_setting / 7 ) : $lead_time_value_setting;
        } elseif ( $supplier_obj && isset( $supplier_obj->lead_time_weeks ) ) {
            $supplier_lead_weeks = (float) $supplier_obj->lead_time_weeks;
        }
    } elseif ( $current_supplier_id > 0 && function_exists( 'sop_supplier_get_by_id' ) ) {
        $supplier_obj = sop_supplier_get_by_id( (int) $current_supplier_id );
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

                if ( isset( $supplier_settings['preorder_default_container_type'] ) ) {
                    $supplier_defaults_container = (string) $supplier_settings['preorder_default_container_type'];
                }
                if ( ! empty( $supplier_settings['preorder_default_pallet_layer'] ) ) {
                    $supplier_defaults_pallet = true;
                }
                if ( array_key_exists( 'preorder_default_container_allowance', $supplier_settings ) ) {
                    $supplier_defaults_allowance = (int) $supplier_settings['preorder_default_container_allowance'];
                }

                $lead_time_value_setting = 0.0;
                $lead_time_unit_setting  = 'weeks';
                if ( array_key_exists( 'lead_time_value', $supplier_settings ) ) {
                    $lead_time_value_setting = (float) $supplier_settings['lead_time_value'];
                }
                if ( array_key_exists( 'lead_time_unit', $supplier_settings ) && in_array( $supplier_settings['lead_time_unit'], array( 'days', 'weeks' ), true ) ) {
                    $lead_time_unit_setting = $supplier_settings['lead_time_unit'];
                }
                if ( $lead_time_value_setting > 0 ) {
                    $supplier_lead_weeks = ( 'days' === $lead_time_unit_setting ) ? ( $lead_time_value_setting / 7 ) : $lead_time_value_setting;
                }
            }
        }
        if ( $supplier_lead_weeks <= 0 && $supplier_obj && isset( $supplier_obj->lead_time_weeks ) ) {
            $supplier_lead_weeks = (float) $supplier_obj->lead_time_weeks;
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

    $is_new_sheet = ( $current_sheet_id <= 0 );

    // Defaults base.
    $container_selection = '';
    $pallet_layer        = 0;
    $allowance           = 0;
    $additional_cbm      = 0.0;

    // Supplier defaults (none/false/0 if not set).
    $default_container_type = (string) $supplier_defaults_container;
    $default_pallet_layer   = ! empty( $supplier_defaults_pallet );
    $default_allowance      = (int) $supplier_defaults_allowance;
    if ( $default_allowance > 50 ) {
        $default_allowance = 50;
    } elseif ( $default_allowance < -50 ) {
        $default_allowance = -50;
    }

    $sop_hidden_columns = array();
    if ( isset( $supplier_settings['preorder_hidden_columns'] ) ) {
        $hidden_columns_raw = $supplier_settings['preorder_hidden_columns'];
        if ( is_string( $hidden_columns_raw ) ) {
            $decoded_hidden = json_decode( $hidden_columns_raw, true );
            $hidden_columns_raw = is_array( $decoded_hidden ) ? $decoded_hidden : array();
        }
        if ( is_array( $hidden_columns_raw ) ) {
            foreach ( $hidden_columns_raw as $hidden_key ) {
                $clean_key = sanitize_key( $hidden_key );
                if ( '' === $clean_key ) {
                    continue;
                }
                $sop_hidden_columns[] = $clean_key;
            }
        }
    }
    $sop_hidden_columns = array_values( array_unique( $sop_hidden_columns ) );
    $sop_hidden_columns_json = wp_json_encode( $sop_hidden_columns );
    if ( ! $sop_hidden_columns_json ) {
        $sop_hidden_columns_json = '[]';
    }

    if ( $is_new_sheet ) {
        // Always start from supplier defaults on a brand new sheet (no GET overrides).
        $defaults = array(
            'container_type' => '',
            'pallet_layer'   => false,
            'allowance'      => 0,
        );

        if ( $current_supplier_id > 0 && function_exists( 'sop_get_supplier_preorder_defaults' ) ) {
            $supplier_defaults = sop_get_supplier_preorder_defaults( $current_supplier_id );
            if ( is_array( $supplier_defaults ) ) {
                $defaults = array_merge( $defaults, $supplier_defaults );
            }
        }

        $container_selection = isset( $defaults['container_type'] ) ? (string) $defaults['container_type'] : '';
        $pallet_layer        = ! empty( $defaults['pallet_layer'] ) ? 1 : 0;
        $allowance           = isset( $defaults['allowance'] ) ? (int) $defaults['allowance'] : 0;
        $additional_cbm      = 0.0;
    } else {
        // Existing sheets: keep current behaviour (sheet/header or prior defaults/GET).
        $planning_payload = array();
        if ( $current_sheet && ! empty( $current_sheet['header_notes_owner'] ) ) {
            $planning_raw = $current_sheet['header_notes_owner'];
            if ( is_string( $planning_raw ) && '' !== trim( $planning_raw ) ) {
                $planning_decoded = json_decode( $planning_raw, true );
                if ( is_array( $planning_decoded ) && isset( $planning_decoded['preorder_planning'] ) && is_array( $planning_decoded['preorder_planning'] ) ) {
                    $planning_payload = $planning_decoded['preorder_planning'];
                }
            }
        }

        if ( isset( $_GET['sop_container'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $container_selection = sanitize_text_field( wp_unslash( $_GET['sop_container'] ) );
        } elseif ( ! empty( $current_sheet['container_type'] ) ) {
            $container_selection = (string) $current_sheet['container_type'];
        } else {
            $container_selection = $default_container_type;
        }

        if ( isset( $_GET['sop_pallet_layer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $pallet_layer = 1;
        } elseif ( array_key_exists( 'pallet_layer', $planning_payload ) ) {
            $pallet_layer = ! empty( $planning_payload['pallet_layer'] ) ? 1 : 0;
        } else {
            $pallet_layer = $default_pallet_layer ? 1 : 0;
        }

        if ( isset( $_GET['sop_allowance'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $allowance = (float) $_GET['sop_allowance'];
        } elseif ( isset( $planning_payload['allowance_percent'] ) && is_numeric( $planning_payload['allowance_percent'] ) ) {
            $allowance = (float) $planning_payload['allowance_percent'];
        } else {
            $allowance = (float) $default_allowance;
        }

        if ( isset( $_GET['sop_additional_cbm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $additional_cbm = (float) $_GET['sop_additional_cbm'];
        } elseif ( isset( $planning_payload['additional_items_cbm'] ) && is_numeric( $planning_payload['additional_items_cbm'] ) ) {
            $additional_cbm = (float) $planning_payload['additional_items_cbm'];
        } else {
            $additional_cbm = 0.0;
        }
    }

    if ( $allowance < -50 ) {
        $allowance = -50;
    } elseif ( $allowance > 50 ) {
        $allowance = 50;
    }
    if ( $additional_cbm < 0 ) {
        $additional_cbm = 0.0;
    } elseif ( $additional_cbm > 9999 ) {
        $additional_cbm = 9999;
    }
    $additional_cbm = round( (float) $additional_cbm, 3 );

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

    $inbound_map = array();
    $inbound_schedule_map = array();
    if ( $current_supplier_id > 0 && function_exists( 'sop_db_get_inbound_qty_map' ) ) {
        $exclude_sheet_id = ( $current_sheet_id > 0 ) ? (int) $current_sheet_id : 0;
        $inbound_map      = sop_db_get_inbound_qty_map( (int) $current_supplier_id, $exclude_sheet_id );
        if ( ! is_array( $inbound_map ) ) {
            $inbound_map = array();
        }
        $inbound_schedule_map = function_exists( 'sop_db_get_inbound_schedule_map' )
            ? sop_db_get_inbound_schedule_map( $exclude_sheet_id )
            : array();
    }

    if ( ! empty( $rows ) ) {
        foreach ( $rows as $row_index => $row ) {
            $pid = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
            $rows[ $row_index ]['inbound_qty'] = ( $pid > 0 && isset( $inbound_map[ $pid ] ) )
                ? (float) $inbound_map[ $pid ]
                : 0.0;
        }
    }

    // Overlay saved sheet data if present.
    if ( $current_sheet_id > 0 && function_exists( 'sop_get_preorder_sheet' ) && function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        $current_sheet = sop_get_preorder_sheet( $current_sheet_id );

        if ( ! $current_sheet || ! is_array( $current_sheet ) ) {
            $current_sheet_id = 0;
            $current_sheet    = null;
        } else {
            $current_lines = sop_get_preorder_sheet_lines( $current_sheet_id );
            if ( ! is_array( $current_lines ) ) {
                $current_lines = array();
            }

            // Hydrate saved lines with live product display fields for viewing (do not alter saved snapshots).
            if ( function_exists( 'sop_hydrate_line_with_live_product_fields' ) ) {
                $sheet_supplier_id = isset( $current_sheet['supplier_id'] ) ? (int) $current_sheet['supplier_id'] : 0;
                foreach ( $current_lines as $cidx => $cline ) {
                    $current_lines[ $cidx ] = sop_hydrate_line_with_live_product_fields( $cline, $sheet_supplier_id );
                }
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

                    if ( isset( $line['order_notes_owner'] ) ) {
                        $row['order_notes'] = $line['order_notes_owner'];
                    }

                    if ( isset( $line['is_removed_owner'] ) ) {
                        $row['removed'] = ! empty( $line['is_removed_owner'] );
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

                    // Apply saved CBM/cm3 values for container fill on saved sheets.
                    if ( isset( $line['cbm_per_unit'] ) && is_numeric( $line['cbm_per_unit'] ) && (float) $line['cbm_per_unit'] > 0 ) {
                        $row['cubic_cm'] = (float) $line['cbm_per_unit'];
                    } elseif ( isset( $line['cm3_per_unit'] ) && is_numeric( $line['cm3_per_unit'] ) && (float) $line['cm3_per_unit'] > 0 ) {
                        $row['cubic_cm'] = (float) $line['cm3_per_unit'];
                    }

                    if ( isset( $line['cbm_total_owner'] ) && is_numeric( $line['cbm_total_owner'] ) && (float) $line['cbm_total_owner'] > 0 ) {
                        $row['line_cbm']        = (float) $line['cbm_total_owner'];
                        $row['cbm_total_owner'] = (float) $line['cbm_total_owner'];
                    } else {
                        $cubic_cm_overlay = isset( $row['cubic_cm'] ) ? (float) $row['cubic_cm'] : 0.0;
                        $qty_overlay      = isset( $row['manual_order_qty'] ) ? (float) $row['manual_order_qty'] : 0.0;
                        if ( $cubic_cm_overlay > 0 && $qty_overlay > 0 ) {
                            $computed_line_cbm     = ( $cubic_cm_overlay * $qty_overlay ) / 1000000;
                            $row['line_cbm']        = $computed_line_cbm;
                            $row['cbm_total_owner'] = $computed_line_cbm;
                        }
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

        $line_cbm_for_total = 0.0;
        if ( isset( $row['line_cbm'] ) && is_numeric( $row['line_cbm'] ) && (float) $row['line_cbm'] > 0 ) {
            $line_cbm_for_total = (float) $row['line_cbm'];
        } elseif ( isset( $row['cubic_cm'] ) && is_numeric( $row['cubic_cm'] ) && (float) $row['cubic_cm'] > 0 ) {
            $line_cbm_for_total = ( (float) $row['cubic_cm'] * $qty ) / 1000000;
        } elseif ( isset( $row['cm3_per_unit'] ) && is_numeric( $row['cm3_per_unit'] ) && (float) $row['cm3_per_unit'] > 0 ) {
            $line_cbm_for_total = ( (float) $row['cm3_per_unit'] * $qty ) / 1000000;
        }

        $total_cbm += $line_cbm_for_total;

        $total_skus++;
    }

    $total_cbm += $additional_cbm;

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
    $cbm_bar_class = 'sop-cbm-bar--yellow';
    if ( $used_cbm > 100.0 ) {
        $cbm_bar_class = 'sop-cbm-bar--red';
    } elseif ( $used_cbm >= 90.0 ) {
        $cbm_bar_class = 'sop-cbm-bar--green';
    } elseif ( $used_cbm >= 70.0 ) {
        $cbm_bar_class = 'sop-cbm-bar--orange';
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

    $order_number_value   = '';
    $current_version      = 1;
    $current_status       = '';
    $current_updated      = '';
    $current_stage_info   = array(
        'stage_key'   => 'in_progress',
        'stage_label' => __( 'In Progress', 'sop' ),
    );
    $current_stage_label  = __( 'In Progress', 'sop' );
    $current_gi_started   = false;
    if ( $current_sheet && is_array( $current_sheet ) ) {
        $order_number_value = ! empty( $current_sheet['order_number_label'] ) ? $current_sheet['order_number_label'] : '';
        $current_version    = ! empty( $current_sheet['edit_version'] ) ? (int) $current_sheet['edit_version'] : 1;
        $current_status     = ! empty( $current_sheet['status'] ) ? $current_sheet['status'] : '';
        $current_updated    = ! empty( $current_sheet['updated_at'] ) ? $current_sheet['updated_at'] : '';
        if ( function_exists( 'sop_get_preorder_sheet_stage_info' ) ) {
            $current_stage_info = sop_get_preorder_sheet_stage_info( $current_status );
        }
        $current_stage_label = isset( $current_stage_info['stage_label'] ) ? (string) $current_stage_info['stage_label'] : $current_stage_label;
        if ( 'locked' === $current_status && function_exists( 'sop_preorder_sheet_has_goodsin_activity' ) ) {
            $current_gi_started = sop_preorder_sheet_has_goodsin_activity( $current_sheet_id );
        } elseif ( 'receiving' === $current_status ) {
            $current_gi_started = true;
        }
        if ( $current_gi_started && isset( $current_stage_info['stage_key'] ) && 'ordered' === $current_stage_info['stage_key'] ) {
            $current_stage_label .= ' (' . __( 'Goods-In started', 'sop' ) . ')';
        }
    }
    $sop_sheet_is_readonly = ( $current_sheet_id > 0 && $current_status && 'draft' !== $current_status );
    $sop_sheet_is_locked   = $sop_sheet_is_readonly;
    $sop_disabled_attr     = $sop_sheet_is_readonly ? ' disabled="disabled"' : '';
    $po_disabled_attr      = $sop_sheet_is_readonly ? ' disabled="disabled"' : '';
    $is_existing_sheet   = ( $current_sheet_id > 0 );
    $save_button_label   = $is_existing_sheet ? esc_html__( 'Update Sheet', 'sop' ) : esc_html__( 'Save sheet', 'sop' );

    // Purchase Order (Saved Sheet) values.
    $po_order_date   = '';
    $po_load_date    = '';
    $po_arrival_date = '';
    $po_deposit_rmb  = 0.0;
    $po_deposit_usd  = 0.0;
    $po_deposit_fx_rate   = 0.0;
    $po_deposit_fx_locked = 0;
    $po_balance_fx_rate   = 0.0;
    $po_balance_fx_locked = 0;
    $po_balance_usd       = 0.0;
    $po_payload       = array();
    $po_extras        = array();
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
            $po_payload = json_decode( $header_notes_owner, true );
            if ( ! is_array( $po_payload ) ) {
                $po_payload = array();
            }
        }
    }

    // Prefer payload-style storage for PO values.
    if ( ! empty( $po_payload ) ) {
        $po_order_date   = isset( $po_payload['order_date'] ) ? (string) $po_payload['order_date'] : $po_order_date;
        $po_load_date    = isset( $po_payload['load_date'] ) ? (string) $po_payload['load_date'] : $po_load_date;
        $po_arrival_date = isset( $po_payload['arrival_date'] ) ? (string) $po_payload['arrival_date'] : $po_arrival_date;

        $po_holiday_start = isset( $po_payload['holiday_start'] ) ? (string) $po_payload['holiday_start'] : $po_holiday_start;
        $po_holiday_end   = isset( $po_payload['holiday_end'] ) ? (string) $po_payload['holiday_end'] : $po_holiday_end;

        if ( isset( $po_payload['deposit_fx_rate'] ) ) {
            $po_deposit_fx_rate = (float) $po_payload['deposit_fx_rate'];
        }
        if ( isset( $po_payload['deposit_fx_locked'] ) ) {
            $po_deposit_fx_locked = (bool) $po_payload['deposit_fx_locked'];
        }
        if ( isset( $po_payload['balance_fx_rate'] ) ) {
            $po_balance_fx_rate = (float) $po_payload['balance_fx_rate'];
        }
        if ( isset( $po_payload['balance_fx_locked'] ) ) {
            $po_balance_fx_locked = ! empty( $po_payload['balance_fx_locked'] );
        }
        if ( isset( $po_payload['balance_usd'] ) ) {
            $po_balance_usd = (float) $po_payload['balance_usd'];
        }
        if ( isset( $po_payload['deposit_rmb'] ) ) {
            $po_deposit_rmb = (float) $po_payload['deposit_rmb'];
        }
        if ( isset( $po_payload['deposit_usd'] ) ) {
            $po_deposit_usd = (float) $po_payload['deposit_usd'];
        }

        $extras_source = array();
        if ( isset( $po_payload['po_extras'] ) && is_array( $po_payload['po_extras'] ) ) {
            $extras_source = $po_payload['po_extras'];
        } elseif ( isset( $po_payload['extras'] ) && is_array( $po_payload['extras'] ) ) {
            // Backwards compatibility for older payload key.
            $extras_source = $po_payload['extras'];
        }

        if ( ! empty( $extras_source ) ) {
            foreach ( $extras_source as $extra_row ) {
                if ( ! is_array( $extra_row ) ) {
                    continue;
                }
                $label  = isset( $extra_row['label'] ) ? (string) $extra_row['label'] : '';
                $amount = isset( $extra_row['amount_rmb'] ) ? (string) $extra_row['amount_rmb'] : '';
                if ( '' === $label && '' === $amount ) {
                    continue;
                }
                $po_extras[] = array(
                    'label'      => $label,
                    'amount_rmb' => $amount,
                );
            }
        }
    } elseif ( ! empty( $header_notes_owner ) ) {
        // Backward compatibility for legacy structure.
        $decoded = json_decode( $header_notes_owner, true );
        if ( is_array( $decoded ) ) {
            if ( isset( $decoded['po_extras'] ) && is_array( $decoded['po_extras'] ) ) {
                foreach ( $decoded['po_extras'] as $extra_row ) {
                    if ( ! is_array( $extra_row ) ) {
                        continue;
                    }
                    $label  = isset( $extra_row['label'] ) ? (string) $extra_row['label'] : '';
                    $amount = isset( $extra_row['amount_rmb'] ) ? (string) $extra_row['amount_rmb'] : '';
                    if ( '' === $label && '' === $amount ) {
                        continue;
                    }
                    $po_extras[] = array(
                        'label'      => $label,
                        'amount_rmb' => $amount,
                    );
                }
            }
            if ( isset( $decoded['deposit_fx_rate'] ) ) {
                $po_deposit_fx_rate = (float) $decoded['deposit_fx_rate'];
            }
            if ( isset( $decoded['deposit_fx_locked'] ) ) {
                $po_deposit_fx_locked = (bool) $decoded['deposit_fx_locked'];
            }
            if ( isset( $decoded['balance_fx_rate'] ) ) {
                $po_balance_fx_rate = (float) $decoded['balance_fx_rate'];
            }
            if ( isset( $decoded['balance_fx_locked'] ) ) {
                $po_balance_fx_locked = (bool) $decoded['balance_fx_locked'];
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
    $po_extras_loaded_count  = is_array( $po_extras ) ? count( $po_extras ) : 0;
    $po_extras_header_count  = isset( $po_payload['po_extras'] ) && is_array( $po_payload['po_extras'] ) ? count( $po_payload['po_extras'] ) : 0;
    if ( empty( $po_extras ) ) {
        $po_extras = array(
            array(
                'label'      => '',
                'amount_rmb' => '',
            ),
        );
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
    if ( empty( $po_deposit_fx_locked ) && $sop_supplier_effective_fx > 0 ) {
        $po_deposit_fx_rate = $sop_supplier_effective_fx;
    }
    if ( $po_deposit_fx_rate <= 0 ) {
        $po_deposit_fx_rate = $po_rmb_per_usd;
    }
    if ( empty( $po_balance_fx_locked ) && $sop_supplier_effective_fx > 0 ) {
        $po_balance_fx_rate = $sop_supplier_effective_fx;
    }
    if ( $po_balance_fx_rate <= 0 ) {
        $po_balance_fx_rate = $po_rmb_per_usd;
    }
    if ( $po_balance_fx_rate > 0 ) {
        $po_balance_usd = $po_balance_rmb / $po_balance_fx_rate;
    }

    // Sheet-level FX for USD display (Balance FX takes precedence).
    $sheet_balance_fx_rate       = isset( $po_payload['balance_fx_rate'] ) ? (float) $po_payload['balance_fx_rate'] : 0.0;
    $sheet_supplier_effective_fx = $sop_supplier_effective_fx;
    $sheet_fx_for_usd            = 0.0;
    if ( $sheet_balance_fx_rate > 0 ) {
        $sheet_fx_for_usd = $sheet_balance_fx_rate;
    } elseif ( $sheet_supplier_effective_fx > 0 ) {
        $sheet_fx_for_usd = $sheet_supplier_effective_fx;
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
        $sop_saved            = isset( $_GET['sop_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['sop_saved'] ) ) : '';
        $sop_sheet_id         = isset( $_GET['sop_sheet_id'] ) ? absint( $_GET['sop_sheet_id'] ) : 0;
        $sop_preorder_readonly = isset( $_GET['sop_preorder_readonly'] ) ? sanitize_text_field( wp_unslash( $_GET['sop_preorder_readonly'] ) ) : '';

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
        if ( '1' === $sop_preorder_readonly ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html( sprintf( __( 'This sheet is %s and is read-only. Unlock to edit.', 'sop' ), $current_stage_label ) )
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
                        esc_html( $current_stage_label ? $current_stage_label : __( 'In Progress', 'sop' ) ),
                        esc_html( $current_updated )
                    );
                    ?>
                </p>
            </div>
            <?php if ( $sop_sheet_is_readonly ) : ?>
                <div class="notice notice-warning sop-preorder-sheet-locked-banner">
                    <p>
                        <?php
                        printf(
                            esc_html__( 'This saved pre-order sheet is %s. You can view and export it, but cannot edit until you unlock it from the Saved sheets list.', 'sop' ),
                            esc_html( $current_stage_label ? $current_stage_label : __( 'Ordered', 'sop' ) )
                        );
                        ?>
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
                <form id="sop-preorder-export-xlsx-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
                    <input type="hidden" name="action" value="sop_export_preorder_sheet_xlsx" />
                    <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                    <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $current_supplier_id ); ?>" />
                    <?php wp_nonce_field( 'sop_export_preorder_sheet_xlsx' ); ?>
                </form>
                <form id="sop-preorder-export-po-xlsx-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
                    <input type="hidden" name="action" value="sop_export_purchase_order_xlsx" />
                    <input type="hidden" name="sop_sheet_id" value="<?php echo esc_attr( $current_sheet_id ); ?>" />
                    <?php wp_nonce_field( 'sop_export_purchase_order_xlsx' ); ?>
                </form>
            <?php endif; ?>

            <style>
                .sop-preorder-card-icon {
                    width: 92px;
                    height: 92px;
                    min-width: 92px;
                    min-height: 92px;
                    overflow: hidden;
                }
                .sop-preorder-header-icon-img {
                    width: 80px;
                    height: 80px;
                    max-width: 80px;
                    max-height: 80px;
                    display: block;
                }
            </style>
            <div class="sop-preorder-card sop-preorder-card--top">
                <?php
                $sop_icon_supplier_class = $sop_icon_supplier_uri ? ' sop-has-custom-icon' : '';
                ?>
                <div class="sop-preorder-card-icon sop-preorder-card-icon--supplier<?php echo esc_attr( $sop_icon_supplier_class ); ?>" aria-hidden="true">
                    <?php echo sop_preorder_render_header_icon( 'supplier.svg', 'dashicons-admin-users', __( 'Supplier', 'sop' ) ); ?>
                </div>
                <div class="sop-preorder-card-main sop-preorder-card-main--top">
                    <div class="sop-preorder-card-row sop-preorder-top-row">
                        <div class="sop-preorder-top-left">
                            <label>
                                <?php esc_html_e( 'Supplier:', 'sop' ); ?>
                                <select
                                    id="sop-preorder-supplier"
                                    name="_sop_supplier_id"
                                    form="sop-preorder-filter-form"
                                >
                                    <?php foreach ( $suppliers as $row ) : ?>
                                        <option value="<?php echo esc_attr( $row['id'] ); ?>" <?php selected( (int) $row['id'], $current_supplier_id ); ?>>
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
                                <details class="sop-download-dropdown">
                                    <summary class="button"><?php echo esc_html__( 'Download', 'sop' ) . ' &#9662;'; ?></summary>
                                    <div class="sop-download-menu">
                                        <button type="submit" form="sop-preorder-export-xlsx-form">
                                            <?php esc_html_e( 'Order Sheet (XLSX)', 'sop' ); ?>
                                        </button>
                                        <button type="submit" form="sop-preorder-export-po-xlsx-form">
                                            <?php esc_html_e( 'Order Summary (XLSX)', 'sop' ); ?>
                                        </button>
                                    </div>
                                </details>
                                <button type="button" class="button sop-rates-dates-toggle">
                                    <?php esc_html_e( 'Order Summary', 'sop' ); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ( ! $sop_sheet_is_readonly ) : ?>
                                <button type="button" class="button button-primary" id="sop-update-sheet-top">
                                    <?php echo $save_button_label; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sop-preorder-card sop-preorder-card--planning">
                <?php
                $sop_icon_container_class = $sop_icon_container_uri ? ' sop-has-custom-icon' : '';
            ?>
            <div class="sop-preorder-card-icon sop-preorder-card-icon--container<?php echo esc_attr( $sop_icon_container_class ); ?>" aria-hidden="true">
                    <?php echo sop_preorder_render_header_icon( 'container.svg', 'dashicons-admin-multisite', __( 'Container', 'sop' ) ); ?>
                </div>
                <div class="sop-preorder-card-main sop-preorder-card-main--middle">
                    <div class="sop-preorder-card__row sop-preorder-card__row--container-top">
                        <div class="sop-preorder-container-item sop-preorder-container-item--select">
                            <div class="sop-preorder-control">
                                <div class="sop-preorder-control-label"><?php esc_html_e( 'Container:', 'sop' ); ?></div>
                                <div class="sop-preorder-control-field">
                                    <select name="sop_container" form="sop-preorder-filter-form" <?php echo $sop_disabled_attr; ?>>
                                        <option value=""><?php esc_html_e( 'None', 'sop' ); ?></option>
                                        <option value="20ft" <?php selected( $container_selection, '20ft' ); ?>>20&#39; (33.2 CBM)</option>
                                        <option value="40ft" <?php selected( $container_selection, '40ft' ); ?>>40&#39; (67.7 CBM)</option>
                                        <option value="40ft_hc" <?php selected( $container_selection, '40ft_hc' ); ?>>40&#39; HQ (76.3 CBM)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="sop-preorder-container-item sop-preorder-container-item--pallet">
                            <div class="sop-preorder-control">
                                <div class="sop-preorder-control-label sop-preorder-control-label--spacer">.</div>
                                <div class="sop-preorder-control-field">
                                    <label class="sop-preorder-checkbox-label">
                                        <input type="checkbox" name="sop_pallet_layer" value="1" <?php checked( $pallet_layer ); ?> form="sop-preorder-filter-form" <?php echo $sop_disabled_attr; ?> />
                                        <?php esc_html_e( '150mm pallet layer', 'sop' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="sop-preorder-container-item sop-preorder-container-item--allowance">
                            <div class="sop-preorder-control">
                                <div class="sop-preorder-control-label"><?php esc_html_e( 'Allowance:', 'sop' ); ?></div>
                                <div class="sop-preorder-control-field">
                                    <input type="number" name="sop_allowance" value="<?php echo esc_attr( $allowance ); ?>" step="1" min="-50" max="50" form="sop-preorder-filter-form" class="sop-preorder-allowance-input" <?php echo $sop_disabled_attr; ?> />
                                    %
                                </div>
                            </div>
                        </div>

                        <div class="sop-preorder-container-item sop-preorder-container-item--additional-cbm">
                            <div class="sop-preorder-control">
                                <div class="sop-preorder-control-label"><?php esc_html_e( 'Additional items CBM:', 'sop' ); ?></div>
                                <div class="sop-preorder-control-field">
                                    <input type="number" name="sop_additional_cbm" value="<?php echo esc_attr( $additional_cbm ); ?>" step="0.001" min="0" form="sop-preorder-filter-form" class="sop-preorder-additional-cbm-input" <?php echo $sop_disabled_attr; ?> />
                                </div>
                            </div>
                        </div>

                        <div class="sop-preorder-container-item sop-preorder-container-item--update">
                            <div class="sop-preorder-control">
                                <div class="sop-preorder-control-label sop-preorder-control-label--spacer">.</div>
                                <div class="sop-preorder-control-field">
                                    <button type="submit" class="button button-secondary" name="sop_preorder_update_container" value="1" form="sop-preorder-filter-form" <?php echo $sop_disabled_attr; ?>>
                                        <?php esc_html_e( 'Update container', 'sop' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sop-preorder-card-row sop-preorder-middle-bottom">
                        <div class="sop-preorder-totals">
                            <span><strong><?php esc_html_e( 'Total Units', 'sop' ); ?>:</strong> <span id="sop-total-units"><?php echo esc_html( number_format_i18n( $total_units, 0 ) ); ?></span></span>
                            <span><strong><?php esc_html_e( 'Total SKUs', 'sop' ); ?>:</strong> <span id="sop-total-skus"><?php echo esc_html( number_format_i18n( $total_skus, 0 ) ); ?></span></span>
                            <span><strong><?php esc_html_e( 'Total Cost (GBP)', 'sop' ); ?>:</strong> <span id="sop-total-cost-gbp"><?php echo wp_kses_post( wc_price( $total_cost_gbp ) ); ?></span></span>
                            <span><strong><?php printf( esc_html__( 'Total Cost (%s)', 'sop' ), esc_html( $supplier_currency ) ); ?>:</strong> <span id="sop-total-cost-supplier"><?php echo esc_html( $currency_symbol . ' ' . number_format_i18n( $total_cost_supplier, 2 ) ); ?></span></span>
                            <span><strong><?php esc_html_e( 'Total Retail (GBP excl.)', 'sop' ); ?>:</strong> <span id="sop-total-retail-gbp-excl"><?php echo wp_kses_post( wc_price( 0 ) ); ?></span></span>
                            <span><strong><?php esc_html_e( 'Est. Profit (GBP)', 'sop' ); ?>:</strong> <span id="sop-total-profit-gbp"><?php echo wp_kses_post( wc_price( 0 ) ); ?></span></span>
                            <span><strong><?php esc_html_e( 'Margin', 'sop' ); ?>:</strong> <span id="sop-total-margin-pct"><?php echo esc_html( number_format_i18n( 0, 1 ) ); ?>%</span></span>
                        </div>
                        <div class="sop-preorder-fill">
                            <strong><?php esc_html_e( 'Container Fill', 'sop' ); ?>:</strong>
                            <div class="sop-cbm-bar-wrapper" title="<?php echo esc_attr( number_format_i18n( $used_cbm, 1 ) ); ?>%">
                                <div class="sop-cbm-bar <?php echo esc_attr( $cbm_bar_class ); ?>" style="width: <?php echo esc_attr( $used_cbm_bar ); ?>%;"></div>
                            </div>
                            <span class="sop-cbm-label" id="sop-cbm-label"><?php echo esc_html( number_format_i18n( $used_cbm, 1 ) ); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sop-preorder-card sop-preorder-card--tools sop-preorder-card--rounding">
                <?php
                $sop_icon_rounding_class = $sop_icon_rounding_uri ? ' sop-has-custom-icon' : '';
            ?>
            <div class="sop-preorder-card-icon sop-preorder-card-icon--planner<?php echo esc_attr( $sop_icon_rounding_class ); ?>" aria-hidden="true">
                    <?php echo sop_preorder_render_header_icon( 'rounding.svg', 'dashicons-clipboard', __( 'Rounding', 'sop' ) ); ?>
                </div>
                    <div class="sop-preorder-card-main sop-preorder-card-main--tools">
                        <div class="sop-preorder-card-row sop-preorder-bottom-row">
                            <div class="sop-preorder-bottom-left sop-preorder-toolbar-row sop-preorder-toolbar-row--rounding">
                                <span><?php esc_html_e( 'Rounding:', 'sop' ); ?></span>
                                <label class="sop-round-step-label">
                                    <?php esc_html_e( 'Step:', 'sop' ); ?>
                                    <select class="sop-round-step" <?php echo $sop_disabled_attr; ?>>
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                    </select>
                                </label>
                                <button type="button" class="button" id="sop-round-up" data-round-mode="up" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Round Up', 'sop' ); ?></button>
                                <button type="button" class="button" id="sop-round-down" data-round-mode="down" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Round Down', 'sop' ); ?></button>
                            </div>

                            <div class="sop-preorder-bottom-middle sop-preorder-toolbar-row sop-preorder-toolbar-row--actions">
                                <button type="button" class="button" id="sop-apply-soq-to-qty" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Apply SOQ to Qty', 'sop' ); ?></button>
                                <button type="button" class="button" id="sop-preorder-remove-selected" <?php echo $sop_disabled_attr; ?>><?php esc_html_e( 'Remove selected', 'sop' ); ?></button>
                                <label for="sop-preorder-show-removed" class="sop-preorder-show-removed">
                                    <input type="checkbox" id="sop-preorder-show-removed" <?php echo $sop_disabled_attr; ?> />
                                    <?php esc_html_e( 'Show removed rows', 'sop' ); ?>
                                </label>
                            </div>

                            <div class="sop-preorder-bottom-right sop-preorder-toolbar-row sop-preorder-toolbar-row--search">
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
                                        <button type="button" id="sop_sku_filter_btn" class="dashicons dashicons-search sop-preorder-sku-icon" aria-label="<?php esc_attr_e( 'Search SKU', 'sop' ); ?>"></button>
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
            'internal_product_notes' => __( 'Internal notes', 'sop' ),
            'order_notes'   => __( 'Order notes', 'sop' ),
            'carton_no'     => __( 'Carton no.', 'sop' ),
        );
        if ( $show_supplier_skus_column ) {
            $before_brand = array_slice( $sop_column_labels, 0, 3, true );
            $after_brand  = array_slice( $sop_column_labels, 3, null, true );
            $sop_column_labels = $before_brand + array( 'supplier_skus' => __( 'Supplier SKUs', 'sop' ) ) + $after_brand;
        }

                                            foreach ( $sop_column_labels as $column_key => $column_label ) :
                                                ?>
                                                <li>
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            data-column="<?php echo esc_attr( $column_key ); ?>"
                                                            <?php echo checked( ! in_array( $column_key, $sop_hidden_columns, true ), true, false ); ?>
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
                <input type="hidden" name="_sop_supplier_id" value="<?php echo esc_attr( $current_supplier_id ); ?>" />
                <input type="hidden" name="sop_supplier_name" value="<?php echo isset( $supplier['name'] ) ? esc_attr( $supplier['name'] ) : ''; ?>" />
                <input type="hidden" name="sop_container_type" value="<?php echo esc_attr( $container_selection ); ?>" />
                <input type="hidden" name="sop_allowance_percent" value="<?php echo esc_attr( $allowance ); ?>" />
                <input type="hidden" name="sop_pallet_layer" value="<?php echo esc_attr( $pallet_layer ? 1 : 0 ); ?>" />
                <input type="hidden" name="sop_additional_cbm" value="<?php echo esc_attr( $additional_cbm ); ?>" />
                <input type="hidden" name="sop_po_payload" id="sop-po-payload" value="" />
                <input type="hidden" name="sop_lines_json" id="sop-lines-json" value="" />
                <input type="hidden" name="sop_preorder_hidden_columns" id="sop_preorder_hidden_columns" value="<?php echo esc_attr( $sop_hidden_columns_json ); ?>" />

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
                            <?php if ( $show_supplier_skus_column ) : ?>
                                <th class="column-supplier-skus" data-column="supplier_skus" data-sort="supplier_skus" data-sort-key="supplier_skus" title="<?php esc_attr_e( 'Supplier SKUs', 'sop' ); ?>"><?php esc_html_e( 'Supplier SKUs', 'sop' ); ?></th>
                            <?php endif; ?>
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
                            <th class="column-internal-product-notes" data-column="internal_product_notes" data-sort="internal_product_notes" title="<?php esc_attr_e( 'Internal product notes.', 'sop' ); ?>"><?php esc_html_e( 'Internal notes', 'sop' ); ?></th>
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
                                <td colspan="<?php echo ( 'RMB' === $supplier_currency ) ? '23' : '22'; ?>">
                                    <?php esc_html_e( 'No products found for this supplier.', 'sop' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php $sop_row_index = 0; ?>
                            <?php foreach ( $rows as $index => $row ) :
                                $product_id           = (int) $row['product_id'];
                                $display_product_id   = $product_id;
                                $name                 = $row['name'];
                                $order_sku            = '';
                                if ( isset( $row['order_sku'] ) && '' !== $row['order_sku'] ) {
                                    $order_sku = $row['order_sku'];
                                } elseif ( isset( $row['sku'] ) ) {
                                    $order_sku = $row['sku'];
                                }
                                $sku                  = isset( $row['sku'] ) ? $row['sku'] : '';
                                if ( $display_product_id <= 0 && '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
                                    $resolved_pid = wc_get_product_id_by_sku( (string) $sku );
                                    if ( $resolved_pid > 0 ) {
                                        $display_product_id = (int) $resolved_pid;
                                    }
                                }
                                $inputs_disabled_attr = $sop_disabled_attr;
                                $missing_pid_label    = '';
                                if ( $display_product_id <= 0 ) {
                                    $inputs_disabled_attr .= ' disabled="disabled"';
                                    $missing_pid_label = ' ' . esc_html__( '(missing product ID)', 'sop' );
                                }
                                $notes                = $row['notes'];
                                $internal_product_notes = '';
                                if ( $display_product_id > 0 ) {
                                    $internal_product_notes = get_post_meta( $display_product_id, '_sop_internal_product_notes', true );
                                    $internal_product_notes = is_string( $internal_product_notes ) ? $internal_product_notes : '';
                                }
                                $min_order_qty        = (float) $row['min_order_qty'];
                                $order_qty            = (float) $row['manual_order_qty'];
                                if ( $sop_sheet_is_readonly && ( ! empty( $row['removed'] ) || $order_qty <= 0 ) ) {
                                    continue;
                                }
                                $stock_on_hand        = (float) $row['stock_on_hand'];
                                $inbound_qty          = (float) $row['inbound_qty'];
                                $cost_gbp = isset( $row['cost_gbp'] ) ? (float) $row['cost_gbp'] : 0.0;

                                $cost_supplier_raw     = isset( $row['cost_supplier'] ) ? $row['cost_supplier'] : '';
                                $cost_supplier_num     = is_numeric( $cost_supplier_raw ) ? (float) $cost_supplier_raw : 0.0;
                                $cost_supplier_display = is_numeric( $cost_supplier_raw ) ? (string) $cost_supplier_raw : '';

                                $location             = isset( $row['location'] ) ? $row['location'] : '';
                                $brand                = isset( $row['brand'] ) ? $row['brand'] : '';
                                $suggested_order_qty  = isset( $row['suggested_order_qty'] ) ? (float) $row['suggested_order_qty'] : 0.0;
                                $cubic_cm             = isset( $row['cubic_cm'] ) ? (float) $row['cubic_cm'] : 0.0;
                                $cbm_per_unit         = isset( $row['cbm_per_unit'] ) ? (float) $row['cbm_per_unit'] : 0.0;
                                if ( $cbm_per_unit <= 0 && isset( $row['cm3_per_unit'] ) && is_numeric( $row['cm3_per_unit'] ) ) {
                                    $cbm_per_unit = (float) $row['cm3_per_unit'] / 1000000;
                                }
                                if ( $cbm_per_unit <= 0 && $cubic_cm > 0 ) {
                                    $cbm_per_unit = $cubic_cm / 1000000;
                                }

                                $line_cbm = 0.0;
                                if ( isset( $row['line_cbm'] ) && is_numeric( $row['line_cbm'] ) ) {
                                    $line_cbm = (float) $row['line_cbm'];
                                } elseif ( isset( $row['cbm_total_owner'] ) && is_numeric( $row['cbm_total_owner'] ) ) {
                                    $line_cbm = (float) $row['cbm_total_owner'];
                                } elseif ( isset( $row['cbm_total'] ) && is_numeric( $row['cbm_total'] ) ) {
                                    $line_cbm = (float) $row['cbm_total'];
                                } elseif ( $cbm_per_unit > 0 && $order_qty > 0 ) {
                                    $line_cbm = $cbm_per_unit * $order_qty;
                                }

                                if ( $cbm_per_unit <= 0 && $line_cbm > 0 && $order_qty > 0 ) {
                                    $cbm_per_unit = $line_cbm / $order_qty;
                                }

                                if ( $cubic_cm <= 0 && $cbm_per_unit > 0 ) {
                                    $cubic_cm = $cbm_per_unit * 1000000;
                                }
                                $soq_qty_sold            = isset( $row['soq_qty_sold'] ) ? (int) $row['soq_qty_sold'] : null;
                                $soq_total_days          = isset( $row['soq_total_days'] ) ? (float) $row['soq_total_days'] : null;
                                $soq_demand_per_day      = isset( $row['soq_demand_per_day'] ) ? (float) $row['soq_demand_per_day'] : null;
                                $soq_stock_at_arrival    = isset( $row['soq_stock_at_arrival'] ) ? (float) $row['soq_stock_at_arrival'] : null;
                                $soq_buffer_target_units = isset( $row['soq_buffer_target_units'] ) ? (float) $row['soq_buffer_target_units'] : null;
                                $soq_reason              = isset( $row['soq_reason'] ) ? (string) $row['soq_reason'] : '';
                                $soq_tooltip             = '';
                                if ( null !== $soq_total_days && $soq_total_days > 0 ) {
                                    $soq_tooltip_parts = array();
                                    $soq_tooltip_parts[] = sprintf(
                                        /* translators: 1: lookback days, 2: qty sold */
                                        __( 'Qty sold (%1$sd): %2$s', 'sop' ),
                                        (int) round( $soq_total_days ),
                                        number_format_i18n( $soq_qty_sold, 0 )
                                    );
                                    $soq_tooltip_parts[] = sprintf(
                                        __( 'Demand/day: %s', 'sop' ),
                                        number_format_i18n( $soq_demand_per_day, 3 )
                                    );
                                    $soq_tooltip_parts[] = sprintf(
                                        __( 'Stock at arrival: %s', 'sop' ),
                                        number_format_i18n( $soq_stock_at_arrival, 1 )
                                    );
                                    $soq_tooltip_parts[] = sprintf(
                                        __( 'Buffer target: %s', 'sop' ),
                                        number_format_i18n( $soq_buffer_target_units, 1 )
                                    );
                                    if ( '' !== $soq_reason ) {
                                        $soq_tooltip_parts[] = sprintf(
                                            __( 'Reason: %s', 'sop' ),
                                            $soq_reason
                                        );
                                    }
                                    $soq_tooltip = implode( ' | ', $soq_tooltip_parts );
                                }
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
                                $line_total_sup       = $order_qty * $cost_supplier_num;
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
                                    <input type="hidden" name="sop_line_product_id[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $display_product_id ); ?>" />
                                    <input type="hidden" name="sop_line_sku[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $sku ); ?>" />
                                    <input type="hidden" name="sop_line_image_id[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $image_id ); ?>" />
                                    <input type="hidden" name="sop_line_location[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $location ); ?>" />
                                    <input type="hidden" name="sop_line_cbm_per_unit[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $cbm_per_unit ); ?>" />
                                    <input type="hidden" name="sop_line_cbm_total[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $line_cbm ); ?>" />
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
                                        <input type="hidden" name="sop_product_id[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $display_product_id ); ?>" />
                                        <textarea
                                            name="sop_sku[<?php echo esc_attr( $display_product_id ); ?>]"
                                            rows="2"
                                            class="sop-preorder-sku small-text"
                                            title="<?php echo esc_attr( $order_sku ); ?>"
                                            <?php echo $inputs_disabled_attr; ?>
                                        ><?php echo esc_textarea( $order_sku ); ?></textarea>
                                        <?php if ( '' !== $missing_pid_label ) : ?>
                                            <div class="sop-preorder-missing-pid"><?php echo esc_html( $missing_pid_label ); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ( $show_supplier_skus_column ) : ?>
                                        <?php
                                        $supplier_skus_val   = get_post_meta( $display_product_id, '_sop_supplier_skus', true );
                                        $supplier_skus_val   = is_string( $supplier_skus_val ) ? $supplier_skus_val : '';
                                        $supplier_skus_sort  = trim( str_replace( array( "\r\n", "\r", "\n" ), ' ', $supplier_skus_val ) );
                                        $supplier_skus_list  = sop_preorder_parse_supplier_sku_entries( $supplier_skus_val );
                                        $supplier_skus_count = count( $supplier_skus_list );
                                        $supplier_skus_shown = array_slice( $supplier_skus_list, 0, 2 );
                                        $supplier_skus_more  = max( 0, $supplier_skus_count - 2 );
                                        $supplier_skus_full  = $supplier_skus_list ? implode( "\n", $supplier_skus_list ) : '';
                                        $supplier_skus_line1 = isset( $supplier_skus_shown[0] ) ? $supplier_skus_shown[0] : '';
                                        $supplier_skus_line2 = isset( $supplier_skus_shown[1] ) ? $supplier_skus_shown[1] : '';
                                        ?>
                                        <td class="column-supplier-skus" data-column="supplier_skus" data-sort-key="supplier_skus" data-sort-value="<?php echo esc_attr( $supplier_skus_sort ); ?>" data-sort-text="<?php echo esc_attr( $supplier_skus_sort ); ?>">
                                            <?php
                                            if ( ! empty( $supplier_skus_full ) ) {
                                                ?>
                                                <span class="sop-supplier-skus-compact" title="<?php echo esc_attr( $supplier_skus_full ); ?>">
                                                    <?php if ( '' !== $supplier_skus_line1 ) : ?>
                                                        <span class="sop-supplier-skus-line"><?php echo esc_html( $supplier_skus_line1 ); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ( '' !== $supplier_skus_line2 ) : ?>
                                                        <span class="sop-supplier-skus-line"><?php echo esc_html( $supplier_skus_line2 ); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ( $supplier_skus_more > 0 ) : ?>
                                                        <span class="sop-supplier-skus-more"><?php echo esc_html( '+' . $supplier_skus_more . ' more...' ); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="sop-hidden"><?php echo esc_html( $supplier_skus_full ); ?></span>
                                                <?php
                                            }
                                            ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="column-brand" data-column="brand">
                                        <?php echo esc_html( $brand ); ?>
                                    </td>
                                    <td class="column-category" data-column="category" data-sort-key="category" data-sort-value="<?php echo esc_attr( $category_sort_value ); ?>">
                                        <?php echo esc_html( $categories ); ?>
                                    </td>
                                    <td class="column-name" data-column="product">
                                        <?php
                                        $product_id    = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
                                        $product_title = isset( $row['product_name'] ) ? $row['product_name'] : $name;

                                        $edit_link = $product_id ? get_edit_post_link( $product_id, '' ) : '';

                                        if ( $edit_link ) {
                                            echo '<a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener noreferrer">';
                                            echo esc_html( $product_title );
                                            echo '</a>';
                                        } else {
                                            echo esc_html( $product_title );
                                        }
                                        ?>
                                    </td>
                                    <td class="column-cost-supplier" data-column="cost_supplier">
                                    <input type="number" name="sop_line_cost_rmb[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $cost_supplier_display ); ?>" step="0.01" min="0" class="sop-cost-supplier-input sop-preorder-cost-rmb" <?php echo $inputs_disabled_attr; ?> />
                                    </td>
                                    <?php if ( 'RMB' === $supplier_currency ) : ?>
                                        <?php
                                        $unit_cost_rmb  = $cost_supplier_num;
                                        $unit_cost_usd  = 0.0;
                                        if ( $unit_cost_rmb > 0 ) {
                                            if ( $sheet_fx_for_usd > 0 ) {
                                                $unit_cost_usd = $unit_cost_rmb / $sheet_fx_for_usd;
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
                                        <input type="number" name="sop_line_moq[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $min_order_qty ); ?>" step="1" min="0" class="sop-preorder-moq" <?php echo $inputs_disabled_attr; ?> />
                                    </td>
                                    <td class="column-suggested" data-column="soq">
                                        <span class="sop-preorder-soq" data-soq="<?php echo esc_attr( $suggested_order_qty ); ?>">
                                            <span class="sop-preorder-soq__num"><?php echo esc_html( number_format_i18n( $suggested_order_qty, 0 ) ); ?></span>
                                            <?php if ( $soq_tooltip ) : ?>
                                                <span class="dashicons dashicons-editor-help sop-soq-why<?php echo ! empty( $sop_icon_ai_uri ) ? ' sop-soq-why-ai' : ''; ?>" data-soq-why="<?php echo esc_attr( $soq_tooltip ); ?>" aria-label="<?php echo esc_attr( $soq_tooltip ); ?>">?</span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="column-order-qty" data-column="order_qty" data-sort="order_qty">
                                    <input type="number" name="sop_line_qty[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $order_qty ); ?>" step="1" min="0" class="sop-order-qty-input sop-preorder-qty" <?php echo $inputs_disabled_attr; ?> />
                                    <span class="sop-rounded-indicator" title="<?php echo esc_attr__( 'Rounded', 'sop' ); ?>" aria-label="<?php echo esc_attr__( 'Rounded', 'sop' ); ?>" role="img">✔</span>
                                </td>
                                    <td class="column-line-total-supplier" data-column="line_total">
                                        <span class="sop-line-total-gbp" data-cost-gbp="<?php echo esc_attr( $cost_gbp ); ?>" style="display:none;">
                                            <?php echo esc_html( number_format_i18n( $line_total_gbp, 2 ) ); ?>
                                        </span>
                                        <span class="sop-line-total-supplier" data-cost-supplier="<?php echo esc_attr( $cost_supplier_num ); ?>">
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
                                    <td class="column-regular-unit" data-column="regular_unit" data-price-excl="<?php echo esc_attr( number_format( $regular_unit_price, 4, '.', '' ) ); ?>">
                                        <?php echo esc_html( number_format_i18n( $regular_unit_price, 2 ) ); ?>
                                    </td>
                                    <td class="column-regular-line" data-column="regular_line">
                                        <?php echo esc_html( number_format_i18n( $regular_line_price, 2 ) ); ?>
                                    </td>
                                    <td class="column-notes" data-column="notes">
                                        <div class="sop-preorder-notes-wrapper">
                                            <div class="sop-notes-preview" data-sop-notes-title="<?php esc_attr_e( 'Product notes', 'sop' ); ?>">
                                                <div class="sop-notes-html">
                                                <?php
                                                $notes_html = function_exists( 'sop_notes_sanitize_html' )
                                                    ? sop_notes_sanitize_html( $notes )
                                                    : wp_kses_post( $notes );
                                                echo $notes_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ?>
                                                </div>
                                            </div>
                                            <input type="hidden" name="sop_line_product_notes[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $notes ); ?>" />
                                        </div>

                                        <input
                                            type="hidden"
                                            name="sop_removed[<?php echo esc_attr( $display_product_id ); ?>]"
                                            value="<?php echo ! empty( $row['removed'] ) ? '1' : '0'; ?>"
                                            class="sop-preorder-removed-flag"
                                        />
                                    </td>
                                    <td class="column-internal-product-notes" data-column="internal_product_notes">
                                        <div class="sop-preorder-notes-wrapper">
                                            <div class="sop-notes-preview" data-sop-notes-title="<?php esc_attr_e( 'Internal notes', 'sop' ); ?>">
                                                <div class="sop-notes-html">
                                                <?php
                                                $internal_html = function_exists( 'sop_notes_sanitize_html' )
                                                    ? sop_notes_sanitize_html( $internal_product_notes )
                                                    : wp_kses_post( $internal_product_notes );
                                                echo $internal_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ?>
                                                </div>
                                            </div>
                                            <input type="hidden" name="sop_line_internal_product_notes[<?php echo esc_attr( $display_product_id ); ?>]" value="<?php echo esc_attr( $internal_product_notes ); ?>" />
                                        </div>
                                    </td>
                                    <td class="column-order-notes" data-column="order_notes">
                                        <div class="sop-preorder-notes-wrapper">
                                            <textarea
                                                name="sop_line_order_notes[<?php echo esc_attr( $display_product_id ); ?>]"
                                                rows="3"
                                                class="sop-preorder-notes sop-preorder-notes-order"
                                                style="width: 100%; resize: none;"
                                                data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                data-notes-type="order"
                                                <?php echo $inputs_disabled_attr; ?>
                                            ><?php echo isset( $row['order_notes'] ) ? esc_textarea( $row['order_notes'] ) : ''; ?></textarea>

                                            <button type="button"
                                                    class="sop-preorder-notes-edit-icon"
                                                    data-row-key="<?php echo esc_attr( $row_key ); ?>"
                                                    data-row-index="<?php echo esc_attr( $row_index ); ?>"
                                                    data-notes-type="order"
                                                    aria-label="<?php esc_attr_e( 'Edit order notes', 'sop' ); ?>"
                                                    <?php echo $inputs_disabled_attr; ?>>
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="column-carton-no" data-column="carton_no" data-sort-key="carton_no" data-sort-value="<?php echo esc_attr( $carton_sort_attr ); ?>">
                                        <input
                                            type="text"
                                            name="sop_line_carton_no[<?php echo esc_attr( $display_product_id ); ?>]"
                                            value="<?php echo esc_attr( $carton_value ); ?>"
                                            class="sop-preorder-carton-input"
                                            data-original-value="<?php echo esc_attr( $carton_value ); ?>"
                                            style="width: 80px;"
                                            <?php echo $inputs_disabled_attr; ?>
                                        />
                                    </td>
                                </tr>
                                <?php $sop_row_index++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="sop-soq-tooltip" class="sop-soq-tooltip" aria-live="polite"></div>

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

            <div id="sop-notes-preview-modal" class="sop-notes-preview-modal" style="display:none;">
                <div class="sop-notes-preview-modal-backdrop"></div>
                <div class="sop-notes-preview-modal-inner" role="dialog" aria-modal="true" aria-labelledby="sop-notes-preview-modal-title">
                    <button type="button" class="button-link sop-notes-preview-modal-close" aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">&times;</button>
                    <h3 id="sop-notes-preview-modal-title" class="sop-notes-preview-modal-title"></h3>
                    <div class="sop-notes-preview-modal-body"></div>
                </div>
            </div>

            <div class="sop-preorder-actions">
                <?php if ( ! $sop_sheet_is_locked ) : ?>
                    <button type="submit" name="sop_save_sheet" value="1" class="button button-primary">
                        <?php echo $save_button_label; ?>
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
                                    <span class="sop-po-holiday-separator"><?php esc_html_e( 'to', 'sop' ); ?></span>
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

                    <div class="sop-po-section sop-po-values sop-po-values-grid">
                        <h3><?php esc_html_e( 'Purchase order values', 'sop' ); ?></h3>
                        <table class="widefat fixed striped sop-po-items-table" data-locked="<?php echo esc_attr( $sop_sheet_is_locked ? '1' : '0' ); ?>">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Description', 'sop' ); ?></th>
                                    <th class="column-amount">
                                        <?php
                                        printf(
                                            /* translators: %s: supplier currency code. */
                                            esc_html__( 'Amount (%s)', 'sop' ),
                                            esc_html( $supplier_currency )
                                        );
                                        ?>
                                    </th>
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
                                            esc_html__( 'Purchase order #%1$s - %2$d SKUs / %3$d pcs', 'sop' ),
                                            esc_html( $sheet_order_number_label ),
                                            $po_total_skus,
                                            $po_total_units
                                        );
                                        ?>
                                    </td>
                                    <td class="column-amount">
                    <span id="sop-po-base-total-rmb" class="sop-po-amount"
                          data-base-total-rmb="<?php echo esc_attr( $po_base_total_rmb ); ?>">
                        <?php echo esc_html( number_format( $po_base_total_rmb, 2 ) ); ?>
                    </span>
                                    </td>
                                    <?php if ( ! $sop_sheet_is_locked ) : ?>
                                        <td></td>
                                    <?php endif; ?>
                                </tr>

                                <?php
                                $po_extras_rows = $po_extras;
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
                                                   class="sop-po-extra-amount sop-po-amount"
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
                                    <th>
                                        <?php
                                        printf(
                                            /* translators: %s: supplier currency code. */
                                            esc_html__( 'Total (%s)', 'sop' ),
                                            esc_html( $supplier_currency )
                                        );
                                        ?>
                                    </th>
                                    <th class="column-amount">
                        <span id="sop-po-total-rmb" class="sop-po-amount"><?php echo esc_html( number_format( $po_total_rmb, 2 ) ); ?></span>
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

                    <?php if ( 'RMB' === $supplier_currency ) : ?>
                    <div class="sop-po-fx-panel sop-po-totals-panel">
                        <div class="sop-po-section sop-po-deposit sop-po-fx-row sop-po-totals-row">
                            <div class="sop-po-field sop-po-totals-field">
                                <label><?php esc_html_e( 'Deposit (USD)', 'sop' ); ?></label>
                                <input type="number"
                                       step="0.01"
                                       name="sop_po_deposit_usd"
                                       value="<?php echo esc_attr( $po_deposit_usd ); ?>"<?php echo $po_disabled_attr; ?> />
                            </div>

                            <div class="sop-po-field sop-po-totals-field">
                                <label><?php esc_html_e( 'Deposit FX rate (RMB per USD)', 'sop' ); ?></label>
                                <input type="text"
                                       inputmode="decimal"
                                       autocomplete="off"
                                       step="0.0001"
                                       name="sop_po_deposit_fx_rate"
                                       class="sop-po-fx-input"
                                       id="sop-po-deposit-fx-rate"
                                       value="<?php echo esc_attr( $po_deposit_fx_rate ); ?>"<?php echo $po_disabled_attr; ?> />
                                <label class="sop-po-inline">
                                    <input type="checkbox"
                                           name="sop_po_deposit_fx_locked"
                                           value="1"
                                           <?php checked( $po_deposit_fx_locked ); ?>
                                           <?php echo $po_disabled_attr ? ' disabled="disabled"' : ''; ?>
                                    />
                                    <?php esc_html_e( 'Lock deposit FX rate', 'sop' ); ?>
                                </label>
                                <div class="sop-po-fx-summary-row">
                                    <span id="sop-po-deposit-fx-summary" class="sop-po-fx-summary"></span>
                                </div>
                            </div>

                            <div class="sop-po-field sop-po-totals-field">
                                <label><?php esc_html_e( 'Deposit (RMB)', 'sop' ); ?></label>
                                <input type="number"
                                       step="0.01"
                                       id="sop-po-deposit-rmb"
                                       name="sop_po_deposit_rmb"
                                       class="sop-po-amount"
                                       value="<?php echo esc_attr( $po_deposit_rmb ); ?>"<?php echo $po_disabled_attr; ?> />
                            </div>
                        </div>

                        <div class="sop-po-section sop-po-deposit sop-po-fx-row sop-po-totals-row">
                            <div class="sop-po-field sop-po-totals-field">
                                <label><?php esc_html_e( 'Balance (USD)', 'sop' ); ?></label>
                                <span id="sop-po-balance-usd" class="sop-po-amount-readonly">
                                    <?php echo $po_balance_fx_locked && $po_balance_fx_rate > 0 ? esc_html( number_format( $po_balance_usd, 2 ) ) : ''; ?>
                                </span>
                                <input type="hidden"
                                       name="sop_po_balance_usd"
                                       id="sop-po-balance-usd-input"
                                       value="<?php echo esc_attr( $po_balance_fx_locked && $po_balance_fx_rate > 0 ? $po_balance_usd : 0 ); ?>" />
                            </div>

                            <div class="sop-po-field sop-po-totals-field">
                                <label><?php esc_html_e( 'Balance FX rate (RMB per USD)', 'sop' ); ?></label>
                                <input type="text"
                                       inputmode="decimal"
                                       autocomplete="off"
                                       step="0.0001"
                                       name="sop_po_balance_fx_rate"
                                       class="sop-po-fx-input"
                                       id="sop-po-balance-fx-rate"
                                       value="<?php echo esc_attr( ( $po_deposit_fx_locked && $po_balance_fx_rate > 0 ) ? $po_balance_fx_rate : '' ); ?>"<?php echo $po_disabled_attr; ?> />
                                <div id="sop-po-balance-fx-help" class="description" style="display:none; margin-top:6px;">
                                    <?php esc_html_e( 'Lock deposit FX rate to enable balance FX.', 'sop' ); ?>
                                </div>
                                <label class="sop-po-inline">
                                    <input type="checkbox"
                                           name="sop_po_balance_fx_locked"
                                           value="1"
                                           <?php checked( ! empty( $po_balance_fx_locked ) ); ?>
                                           <?php echo $po_disabled_attr ? ' disabled="disabled"' : ''; ?>
                                    />
                                    <?php esc_html_e( 'Lock balance FX rate', 'sop' ); ?>
                                </label>
                                <div class="sop-po-fx-summary-row">
                                    <span id="sop-po-balance-fx-summary" class="sop-po-fx-summary"></span>
                                </div>
                            </div>

                            <div class="sop-po-field sop-po-totals-field">
                                <label><?php esc_html_e( 'Balance (RMB)', 'sop' ); ?></label>
                                <span id="sop-po-balance-rmb" class="sop-po-amount sop-po-amount-readonly">
                                    <?php echo esc_html( number_format( $po_balance_rmb, 2 ) ); ?>
                                </span>
                            </div>
                        </div>

                    </div>
                    <?php else : ?>
                    <div class="sop-po-simple-panel sop-po-totals-panel">
                        <div class="sop-po-section sop-po-simple-row sop-po-totals-row">
                            <div class="sop-po-field sop-po-totals-field">
                                <?php
                                printf(
                                    /* translators: %s: supplier currency code. */
                                    '<label>%s</label>',
                                    esc_html(
                                        sprintf(
                                            /* translators: %s: supplier currency code. */
                                            __( 'Deposit (%s)', 'sop' ),
                                            $supplier_currency
                                        )
                                    )
                                );
                                ?>
                                <input type="number"
                                       step="0.01"
                                       name="sop_po_deposit_usd"
                                       class="sop-po-deposit-input"
                                       value="<?php echo esc_attr( $po_deposit_usd ); ?>"<?php echo $po_disabled_attr; ?> />
                            </div>
                        </div>

                        <div class="sop-po-section sop-po-simple-row sop-po-totals-row">
                            <div class="sop-po-field sop-po-totals-field">
                                <?php
                                printf(
                                    /* translators: %s: supplier currency code. */
                                    '<label>%s</label>',
                                    esc_html(
                                        sprintf(
                                            __( 'Balance (%s)', 'sop' ),
                                            $supplier_currency
                                        )
                                    )
                                );
                                ?>
                                <span id="sop-po-balance-usd" class="sop-po-amount sop-po-amount-readonly">
                                    <?php echo esc_html( number_format( $po_balance_usd, 2 ) ); ?>
                                </span>
                                <input type="hidden"
                                       name="sop_po_balance_usd"
                                       id="sop-po-balance-usd-input"
                                       value="<?php echo esc_attr( $po_balance_usd ); ?>" />
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <input type="hidden" id="sop-po-rmb-per-usd" value="<?php echo esc_attr( $sop_supplier_effective_fx > 0 ? $sop_supplier_effective_fx : $po_rmb_per_usd ); ?>" />
                    <input type="hidden" id="sop-po-lead-weeks" value="<?php echo esc_attr( $supplier_lead_weeks ); ?>" />
                    <input type="hidden" id="sop-po-shipping-days" value="<?php echo esc_attr( $shipping_days ); ?>" />
                    <input type="hidden" id="sop-po-supplier-holiday-periods" value="<?php echo esc_attr( wp_json_encode( $holiday_periods_md ) ); ?>" />

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
            padding: 3px;
            padding-right: 8px;
            border-right: 1px solid #c3c4c7;
            background-color: #fff;
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
            min-width: 0;
            min-height: 0;
            width: 92px;
            height: 92px;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            overflow: hidden;
            opacity: 0;
        }

        body.wp-admin .sop-preorder-card-icon {
            opacity: 1;
        }

        .sop-preorder-card-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-left: 8px;
        }

        .sop-preorder-card-icon .dashicons {
            font-size: 80px;
            width: 80px;
            height: 80px;
            color: #111827;
            line-height: 80px;
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

        .column-suggested {
            text-align: center;
        }

        .sop-preorder-soq {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
        }

        .sop-preorder-soq__num {
            display: block;
            line-height: 1.1;
        }

        .sop-soq-why {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            margin-left: 0;
            vertical-align: middle;
            cursor: help;
            line-height: 1;
            width: 28px;
            height: 28px;
            padding: 4px;
        }
        .sop-soq-why.dashicons {
            font-size: 20px;
            line-height: 1;
        }
        .sop-soq-why svg,
        .sop-soq-why svg * {
            pointer-events: none;
        }

        .sop-soq-tooltip {
            position: fixed;
            z-index: 999999;
            pointer-events: none;
            background: #111827;
            color: #fff;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 12px;
            line-height: 1.4;
            max-width: 320px;
            display: none;
        }

        .sop-soq-tooltip-lines {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sop-soq-tooltip-line--nowrap {
            white-space: nowrap;
        }

        .sop-soq-tooltip-line--reason {
            white-space: normal;
        }

        <?php if ( ! empty( $sop_icon_ai_uri ) ) : ?>
        .sop-soq-why-ai {
            background-image: url('<?php echo esc_attr( $sop_icon_ai_uri ); ?>');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 20px 20px;
            color: transparent;
            text-indent: -9999px;
            overflow: hidden;
        }
        <?php endif; ?>

        .sop-preorder-header-icon-fallback {
            font-size: 80px;
            width: 80px;
            height: 80px;
            line-height: 80px;
            display: block;
            margin: 0 auto;
        }

        .sop-preorder-card-icon.sop-has-custom-icon {
            background-position: center;
            background-origin: content-box;
            background-clip: content-box;
        }

        .sop-preorder-card-icon.sop-has-custom-icon .dashicons {
            display: none;
        }

        .sop-preorder-header-icon-img {
            display: block;
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .sop-download-dropdown {
            position: relative;
            display: inline-block;
        }
        .sop-download-dropdown summary {
            list-style: none;
            cursor: pointer;
        }
        .sop-download-dropdown summary::-webkit-details-marker {
            display: none;
        }
        .sop-download-dropdown summary:focus {
            outline: none;
        }
        .sop-download-dropdown .sop-download-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            min-width: 0;
            width: auto;
            display: inline-block;
            padding: 6px 0;
            z-index: 50;
            white-space: nowrap;
        }
        .sop-download-dropdown .sop-download-menu button {
            display: block;
            width: auto;
            text-align: left;
            padding: 6px 12px;
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        .sop-download-dropdown .sop-download-menu button:hover {
            background: #f0f0f1;
        }

        .sop-preorder-middle-top,
        .sop-preorder-middle-bottom,
        .sop-preorder-bottom-row {
            width: 100%;
        }

        .sop-preorder-bottom-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px 24px;
        }

        /* Add vertical space between the top and bottom rows of Tile 2 (container planning) */
        .sop-preorder-middle-bottom {
            margin-top: 10px;
        }

        .sop-preorder-bottom-right {
            margin-left: auto;
        }

        .sop-preorder-toolbar-row--rounding {
            flex: 1 1 100%;
        }

        .sop-preorder-toolbar-row--actions {
            flex: 0 1 auto;
        }

        .sop-preorder-toolbar-row--search {
            flex: 0 1 auto;
            margin-left: auto;
        }

        /* Columns toggle + popover */
        .sop-preorder-columns {
            position: relative;
            display: inline-block;
            z-index: 10;
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
            pointer-events: auto;
            background: transparent;
            border: 0;
            padding: 0;
            margin: 0;
            cursor: pointer;
        }

        .sop-preorder-header select[name="_sop_supplier_id"] {
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
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            width: 100%;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item {
            display: flex;
            align-items: stretch;
            gap: 8px;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item--select {
            flex: 1 1 270px;
            min-width: 240px;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item--pallet {
            flex: 0 1 170px;
            min-width: 150px;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item--allowance {
            flex: 0 0 auto;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item--additional-cbm {
            flex: 0 0 auto;
        }

        .sop-preorder-card__row--container-top .sop-preorder-container-item--update {
            flex: 0 0 auto;
        }

        .sop-preorder-card__row--container-top .sop-preorder-control {
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 100%;
            min-width: 0;
        }

        .sop-preorder-card__row--container-top .sop-preorder-control-label {
            font-size: 12px;
            line-height: 1.1;
        }

        .sop-preorder-card__row--container-top .sop-preorder-control-label--spacer {
            visibility: hidden;
        }

        .sop-preorder-card__row--container-top .sop-preorder-control-field {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .sop-preorder-card__row--container-top .sop-preorder-control-field select {
            width: 100%;
        }

        .sop-preorder-card__row--container-top .sop-preorder-checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .sop-preorder-card__row--container-top .sop-preorder-checkbox-label input[type="checkbox"] {
            margin-top: 2px;
        }

        .sop-preorder-card__row--container-top .sop-preorder-additional-cbm-input {
            box-sizing: border-box;
            width: 110px;
            max-width: 110px;
        }

        .sop-preorder-card__row--container-top .sop-preorder-allowance-input {
            box-sizing: border-box;
            width: 60px;
            max-width: 60px;
        }

        .sop-preorder-container-item--update {
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
            width: 0;
            transition: width 0.25s ease;
        }

        .sop-cbm-bar--yellow {
            background: #FFF200;
        }

        .sop-cbm-bar--orange {
            background: #FF7A00;
        }

        .sop-cbm-bar--green {
            background: #39FF14;
        }

        .sop-cbm-bar--red {
            background: #FF073A;
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
            width: var(--sop-po-amount-width, 160px);
        }

        .sop-po-amount {
            text-align: right;
            width: 100%;
            display: inline-block;
        }

        .sop-po-items-table .column-actions {
            width: 40px;
            text-align: center;
        }

        .sop-po-items-table tfoot th {
            font-weight: 700;
        }

        .sop-po-values-grid {
            --sop-po-amount-width: 160px;
        }

        @media (max-width: 782px) {
            .sop-preorder-card {
                padding: 12px 12px 14px;
            }

            .sop-preorder-card-icon {
                min-width: 0;
                min-height: 0;
                width: 64px;
                height: 64px;
                padding: 2px;
                padding-right: 8px;
            }

            .sop-preorder-card-icon .dashicons,
            .sop-preorder-header-icon-fallback {
                font-size: 52px;
                width: 52px;
                height: 52px;
                line-height: 52px;
            }

            .sop-preorder-card-main {
                padding-left: 8px;
            }

            .sop-preorder-card__row--container-top {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .sop-preorder-card__row--container-top .sop-preorder-container-item,
            .sop-preorder-card__row--container-top label {
                width: 100%;
            }

            .sop-pallet-layer-label {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .sop-allowance-label {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }

            .sop-allowance-label input[type="number"] {
                flex: 1 1 0;
                min-width: 80px;
            }

            .sop-additional-cbm-label {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }

            .sop-additional-cbm-label input[type="number"] {
                flex: 1 1 0;
                min-width: 80px;
            }

            .sop-preorder-container-item--update .button {
                width: 100%;
            }

            .sop-preorder-bottom-row {
                flex-direction: column;
                flex-wrap: nowrap;
                align-items: stretch;
                align-content: stretch;
                gap: 10px;
            }

            .sop-preorder-toolbar-row--rounding,
            .sop-preorder-toolbar-row--actions,
            .sop-preorder-toolbar-row--search {
                width: 100%;
            }

            .sop-preorder-toolbar-row--rounding {
                gap: 8px;
            }

            #sop-round-up,
            #sop-round-down,
            #sop-apply-soq-to-qty,
            #sop-preorder-remove-selected {
                flex: 1 1 calc(50% - 6px);
            }

            .sop-preorder-bottom-left,
            .sop-preorder-bottom-middle {
                gap: 8px;
            }

            .sop-preorder-bottom-middle .sop-preorder-show-removed {
                width: 100%;
            }

            .sop-preorder-bottom-right,
            .sop-preorder-toolbar-row--search {
                margin-left: 0;
            }

            .sop-preorder-sku-search {
                max-width: 100%;
            }

            .sop-preorder-columns-toggle {
                width: 100%;
                min-width: 0;
            }

            .sop-preorder-card--tools .sop-preorder-card-main,
            .sop-preorder-card--tools .sop-preorder-card-main--tools {
                justify-content: flex-start;
                min-height: 0;
                height: auto;
                max-width: 100%;
                box-sizing: border-box;
            }

            .sop-preorder-card--tools .sop-preorder-bottom-row {
                justify-content: flex-start;
                align-items: stretch;
                min-height: 0;
                height: auto;
                gap: 8px;
            }

            .sop-preorder-card--tools .sop-preorder-bottom-left {
                align-items: flex-start;
                justify-content: flex-start;
                gap: 8px;
            }

            .sop-preorder-card--tools .sop-preorder-toolbar-row--rounding {
                margin-top: 0;
            }

            .sop-preorder-card--rounding {
                max-width: 100%;
                width: 100%;
                box-sizing: border-box;
                overflow-x: visible;
            }

            .sop-preorder-card--rounding .sop-preorder-card-row,
            .sop-preorder-card--rounding .sop-preorder-bottom-left,
            .sop-preorder-card--rounding .sop-preorder-bottom-middle,
            .sop-preorder-card--rounding .sop-preorder-bottom-right {
                min-width: 0;
                max-width: 100%;
                box-sizing: border-box;
            }

            .sop-preorder-card--rounding .sop-preorder-card-row {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start;
                gap: 12px;
            }

            .sop-preorder-card--rounding .sop-preorder-toolbar-row--rounding,
            .sop-preorder-card--rounding .sop-preorder-toolbar-row--actions,
            .sop-preorder-card--rounding .sop-preorder-toolbar-row--search {
                min-width: 0;
            }

            .sop-preorder-card--rounding .sop-preorder-bottom-middle {
                width: 100%;
                max-width: 100%;
                margin-left: 0;
                align-items: stretch;
                justify-content: flex-start;
            }

            .sop-preorder-card--rounding .sop-preorder-bottom-middle .button {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .sop-preorder-card--rounding .sop-preorder-show-removed {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
            }

            .sop-preorder-card--rounding .button {
                max-width: 100%;
                box-sizing: border-box;
            }
        }

        .sop-po-totals-panel {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            max-width: 640px;
            margin-left: auto;
            padding-right: 60px;
            --sop-po-amount-width: 160px;
        }

        .sop-po-totals-row {
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            gap: 24px;
            margin-top: 12px;
            width: 100%;
            padding-right: 10px;
        }
        .sop-po-totals-row .sop-po-totals-field:last-child {
            min-width: var(--sop-po-amount-width);
            text-align: right;
        }

        .sop-po-totals-field input[type="number"],
        .sop-po-totals-field input[type="text"],
        .sop-po-amount-readonly,
        .sop-po-amount {
            text-align: right;
            width: 100%;
        }

        .sop-po-totals-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sop-po-totals-field label {
            display: block;
            margin-bottom: 0;
        }

        #sop-po-deposit-rmb.sop-po-amount,
        #sop-po-balance-rmb.sop-po-amount {
            width: 179px;
            max-width: 179px;
        }

        .sop-po-totals-row > .sop-po-totals-field {
            min-width: 200px;
        }

        .sop-po-totals-field label {
            display: block;
            margin-bottom: 4px;
        }

        .sop-po-fx-input {
            text-align: right;
        }

        .sop-po-fx-summary-row {
            margin-top: 2px;
        }

        .sop-po-fx-summary {
            font-size: 11px;
            opacity: 0.85;
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

        .sop-preorder-table th.column-supplier-skus,
        .sop-preorder-table td.column-supplier-skus {
            width: 90px;
            min-width: 90px;
            max-width: 90px;
        }

        .sop-supplier-skus-compact {
            display: flex;
            flex-direction: column;
            gap: 2px;
            cursor: help;
        }

        .sop-supplier-skus-line {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sop-supplier-skus-more {
            display: block;
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            font-size: 11px;
        }

        .sop-hidden {
            display: none;
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

        .sop-rounded-indicator {
            display: none;
            margin-left: 6px;
            padding: 0;
            line-height: 1;
            cursor: help;
            vertical-align: middle;
            box-sizing: border-box;
            user-select: none;
            white-space: nowrap;
            position: relative;
            top: -1px;
        }

        .sop-rounded-indicator[style*="display: inline"],
        .sop-rounded-indicator[style*="display:inline"] {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            min-width: 18px;
            min-height: 18px;
            border-radius: 50%;
            background-color: #2EAD4A;
            color: #ffffff;
            font-size: 13px;
            font-weight: 900;
            border: none;
            box-shadow: none;
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
        .sop-preorder-table th.column-internal-product-notes,
        .sop-preorder-table td.column-internal-product-notes,
        .sop-preorder-table th.column-order-notes,
        .sop-preorder-table td.column-order-notes {
            min-width: 40ch;
        }

        .sop-preorder-notes-wrapper {
            position: relative;
        }

        .sop-notes-preview {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 4;
            overflow: hidden;
            white-space: normal;
            max-height: 80px;
        }

        .sop-notes-html {
            white-space: normal;
            word-break: break-word;
        }

        .sop-notes-preview.is-truncated {
            cursor: pointer;
        }

        .sop-note-red {
            color: #d63638;
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

        .sop-notes-preview-modal {
            position: fixed;
            inset: 0;
            z-index: 100005;
            display: none;
        }

        .sop-notes-preview-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
        }

        .sop-notes-preview-modal-inner {
            position: absolute;
            top: 10%;
            left: 50%;
            transform: translateX(-50%);
            max-width: 700px;
            width: 90%;
            background: #fff;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            max-height: 70vh;
            overflow: hidden;
        }

        .sop-notes-preview-modal-body {
            max-height: 55vh;
            overflow: auto;
            white-space: normal;
        }
    </style>

    <script>
        jQuery(function($) {
            // One-shot "sheet saved" notice: remove sop_saved from URL after first load.
            (function() {
                if ( window.location.search.indexOf( 'sop_saved=' ) === -1 ) {
                    return;
                }
                try {
                    var url = new URL( window.location.href );
                    url.searchParams.delete( 'sop_saved' );
                    window.history.replaceState( {}, '', url.toString() );
                } catch ( e ) {
                    // Older browsers can safely ignore.
                }
            })();

            var $table = $('.sop-preorder-table');
            var containerCbm = <?php echo json_encode( $effective_cbm ); ?>;
            var $selectAllCheckbox   = $('#sop-preorder-select-all');
            var $removeSelectedBtn   = $('#sop-preorder-remove-selected');
            var $showRemovedCheckbox = $('#sop-preorder-show-removed');
            var $notesOverlay        = $('#sop-preorder-notes-overlay');
            var $notesOverlayInner   = $notesOverlay.find('.sop-preorder-notes-overlay-inner');
            var $notesOverlayTitle   = $notesOverlay.find('.sop-preorder-notes-overlay-title');
            var $notesOverlayProduct = $notesOverlay.find('.sop-preorder-notes-overlay-product');
            var $notesOverlayTextarea = $notesOverlay.find('.sop-preorder-notes-overlay-textarea');
            var $notesPreviewModal   = $('#sop-notes-preview-modal');
            var $notesPreviewBackdrop = $notesPreviewModal.find('.sop-notes-preview-modal-backdrop');
            var $notesPreviewTitle   = $notesPreviewModal.find('.sop-notes-preview-modal-title');
            var $notesPreviewBody    = $notesPreviewModal.find('.sop-notes-preview-modal-body');
            var currentNotesTextarea = null;
            var currentNotesType     = 'product';
            var currentNotesRowIndex = null;
            var lastClickedCheckbox  = null;
            var hasUnsavedChanges    = false;
            var $sheetForm           = $('#sop-preorder-sheet-form');
            var $columnsWrapper      = $('.sop-preorder-columns');
            var $columnsToggleButton = $columnsWrapper.find('.sop-preorder-columns-toggle');
            var $columnsPanel        = $columnsWrapper.find('.sop-preorder-columns-popover');
            var $columnCheckboxes    = $columnsPanel.find('input[type="checkbox"]');
            var $hiddenColumnsInput  = $('#sop_preorder_hidden_columns');
            var sopPreorderIsSubmittingSheet = false;
            var sopPreorderIsReadOnly = <?php echo $sop_sheet_is_readonly ? 'true' : 'false'; ?>;
            var $saveUpdateButtons   = $( '#sop-update-sheet-top, #sop-update-sheet-bottom, .sop-preorder-save-sheet, .sop-preorder-update-sheet' );
            var $tableWrapper        = $('.sop-preorder-table-wrapper');
            if ( ! $tableWrapper.length ) {
                $tableWrapper = $('.sop-preorder-table-frame');
            }
            var $soqTooltip          = $('#sop-soq-tooltip');

            // Header icons now render via <img> only (no background-image layer).
            var lastMouseClientX     = null;
            var lastMouseClientY     = null;
            var soqScrollTimer       = null;
            var soqTooltipActiveEl   = null;

            if ( sopPreorderIsReadOnly ) {
                $table.find('tbody').find('input, select, textarea').prop('disabled', true);
                $( '[name="sop_container_type"],[name="sop_allowance_percent"],[name="sop_pallet_layer"],[name="sop_additional_cbm"]' ).prop('disabled', true);
            }

            function sopMarkUnsavedChanges() {
                hasUnsavedChanges = true;
            }

            if ( $soqTooltip.length ) {
                $soqTooltip.appendTo( document.body );
            }

            function sopPreorderInitSoqTooltipData() {
                $table.find( '.sop-soq-why' ).each( function() {
                    var $el        = $( this );
                    var tooltipVal = $el.attr( 'data-soq-why' );
                    var titleVal   = $el.attr( 'title' );
                    if ( ! tooltipVal && titleVal ) {
                        $el.attr( 'data-soq-why', titleVal );
                    }
                    $el.removeAttr( 'title' );
                    $el.find( '[title]' ).removeAttr( 'title' );
                } );
            }

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

            function sopPreorderSyncHiddenColumnsInput() {
                if ( ! $hiddenColumnsInput.length ) {
                    return;
                }
                var hiddenColumns = [];
                $columnCheckboxes.each(function() {
                    var columnKey = $(this).data('column');
                    if ( ! columnKey ) {
                        return;
                    }
                    if ( ! $(this).is(':checked') ) {
                        hiddenColumns.push( String( columnKey ) );
                    }
                });
                $hiddenColumnsInput.val( JSON.stringify( hiddenColumns ) );
            }

            // Any text/number input or textarea inside the table is considered an edit.
            $table.on('change input', 'input[type="text"], input[type="number"], textarea', function() {
                sopMarkUnsavedChanges();
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

            <?php if ( empty( $current_sheet_id ) ) : ?>
            // On a brand-new sheet, changing the supplier submits the filter to reload products.
            $('#sop-preorder-supplier').on('change', function () {
                var $form = $('#sop-preorder-filter-form');
                if ( $form.length ) {
                    var $containerSelect = $form.find('select[name="sop_container"]');
                    var $palletCheckbox  = $form.find('input[name="sop_pallet_layer"]');
                    var $allowanceInput  = $form.find('input[name="sop_allowance"]');
                    var $additionalInput = $form.find('input[name="sop_additional_cbm"]');

                    if ( $containerSelect.length ) {
                        $containerSelect.val('');
                    }
                    if ( $palletCheckbox.length ) {
                        $palletCheckbox.prop( 'checked', false );
                    }
                    if ( $allowanceInput.length ) {
                        $allowanceInput.val('');
                    }
                    if ( $additionalInput.length ) {
                        $additionalInput.val('');
                    }

                    $form.trigger('submit');
                }
            });
            <?php endif; ?>

            if ( $sheetForm.length ) {
                $sheetForm.on( 'submit', function() {
                    sopPreorderSyncHiddenColumnsInput();
                    sopPreorderIsSubmittingSheet = true;
                    hasUnsavedChanges = false;
                } );
            }

            if ( $saveUpdateButtons.length ) {
                $saveUpdateButtons.on( 'click', function() {
                    sopPreorderIsSubmittingSheet = true;
                    hasUnsavedChanges = false;
                } );
            }

            // Warn on browser navigation if unsaved changes exist.
            window.addEventListener('beforeunload', function(e) {
                if ( ! hasUnsavedChanges || sopPreorderIsSubmittingSheet ) {
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
                sopPreorderSyncHiddenColumnsInput();

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
                    sopPreorderSyncHiddenColumnsInput();
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

            function sopPreorderGetRowCheckboxesForSelection() {
                return $table.find('tbody tr:visible:not(.sop-preorder-row-removed) .sop-preorder-select-row');
            }

            function sopPreorderGetSelectedRows() {
                var rows = [];
                var $checkboxes = sopPreorderGetRowCheckboxesForSelection();
                $checkboxes.each( function( index, checkbox ) {
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

            function sopPreorderGetTargetRowsForBulkAction() {
                var $checked = $table.find( 'tbody .sop-preorder-select-row:checked' );
                if ( $checked.length ) {
                    return $checked.closest( 'tr.sop-preorder-row' ).not( '.sop-preorder-row-removed' );
                }
                return $table.find( 'tbody tr.sop-preorder-row' ).not( '.sop-preorder-row-removed' );
            }

            function sopPreorderHideSoqTooltip() {
                if ( ! $soqTooltip.length ) {
                    return;
                }
                soqTooltipActiveEl = null;
                $soqTooltip.hide().css( 'visibility', 'hidden' ).text( '' );
            }

            function sopPreorderShowSoqTooltip( triggerEl ) {
                if ( ! $soqTooltip.length || ! triggerEl ) {
                    return;
                }
                var $trigger = $( triggerEl );
                var tooltipText = $trigger.data( 'soq-why' ) || $trigger.attr( 'aria-label' ) || '';
                if ( ! tooltipText ) {
                    return;
                }

                soqTooltipActiveEl = triggerEl;
                var parts = String( tooltipText ).split( '|' ).map( function( part ) {
                    return $.trim( part );
                } ).filter( function( part ) {
                    return part.length > 0;
                } );

                $soqTooltip.empty();

                if ( parts.length === 5 ) {
                    var $linesWrap = $( '<div class="sop-soq-tooltip-lines"></div>' );
                    var $line1 = $( '<div class="sop-soq-tooltip-line sop-soq-tooltip-line--nowrap"></div>' ).text( parts[0] + ' | ' + parts[1] );
                    var $line2 = $( '<div class="sop-soq-tooltip-line sop-soq-tooltip-line--nowrap"></div>' ).text( parts[2] + ' | ' + parts[3] );
                    var $line3 = $( '<div class="sop-soq-tooltip-line sop-soq-tooltip-line--reason"></div>' ).text( parts[4] );
                    $linesWrap.append( $line1, $line2, $line3 );
                    $soqTooltip.append( $linesWrap );
                } else if ( parts.length > 0 ) {
                    var $wrap = $( '<div class="sop-soq-tooltip-lines"></div>' );
                    parts.forEach( function( part ) {
                        $wrap.append( $( '<div class="sop-soq-tooltip-line"></div>' ).text( part ) );
                    } );
                    $soqTooltip.append( $wrap );
                } else {
                    $soqTooltip.text( tooltipText );
                }

                $soqTooltip.css( { display: 'block', visibility: 'hidden' } );

                var rect = triggerEl.getBoundingClientRect();
                var tooltipWidth = $soqTooltip.outerWidth();
                var tooltipHeight = $soqTooltip.outerHeight();
                var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
                var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

                var left = rect.left + ( rect.width / 2 ) - ( tooltipWidth / 2 );
                left = Math.max( 8, Math.min( left, viewportWidth - tooltipWidth - 8 ) );

                var top = rect.bottom + 8;
                if ( top + tooltipHeight + 8 > viewportHeight ) {
                    top = rect.top - tooltipHeight - 8;
                }
                top = Math.max( 8, top );

                $soqTooltip.css( {
                    left: left + 'px',
                    top: top + 'px',
                    visibility: 'visible'
                } );
            }

            function sopPreorderOpenNotesOverlayForRow( $row, notesType ) {
                if ( ! $row || ! $row.length ) {
                    return;
                }

                var type = notesType || 'product';
                var $textarea = 'order' === type
                    ? $row.find( '.sop-preorder-notes-order' )
                    : ( 'internal' === type
                        ? $row.find( '.sop-preorder-notes-internal' )
                        : $row.find( '.sop-preorder-notes-product' ) );
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
                } else if ( 'internal' === type ) {
                    $notesOverlayTitle.text( '<?php echo esc_js( __( 'Internal notes', 'sop' ) ); ?>' );
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

            function sopNotesPreviewIsTruncated( el ) {
                if ( ! el ) {
                    return false;
                }
                return el.scrollHeight > el.clientHeight + 1;
            }

            function sopNotesPreviewInit() {
                $table.find( '.sop-notes-preview' ).each( function() {
                    var $el = $( this );
                    if ( sopNotesPreviewIsTruncated( this ) ) {
                        $el.addClass( 'is-truncated' );
                    } else {
                        $el.removeClass( 'is-truncated' );
                    }
                } );
            }

            function sopNotesPreviewOpen( $el ) {
                if ( ! $notesPreviewModal.length || ! $el || ! $el.length ) {
                    return;
                }
                var title = $el.data( 'sop-notes-title' ) || '';
                $notesPreviewTitle.text( title );
                $notesPreviewBody.html( $el.html() || '' );
                $notesPreviewModal.show();
            }

            function sopNotesPreviewClose() {
                if ( ! $notesPreviewModal.length ) {
                    return;
                }
                $notesPreviewModal.hide();
                $notesPreviewTitle.text( '' );
                $notesPreviewBody.empty();
            }

            sopNotesPreviewInit();
            $( window ).on( 'resize', sopNotesPreviewInit );

            // Selection: select-all and shift-click range.
            if ( $selectAllCheckbox.length ) {
                $selectAllCheckbox.on( 'change', function() {
                    var checked = this.checked;
                    var $checkboxes = sopPreorderGetRowCheckboxesForSelection();
                    $checkboxes.prop( 'checked', checked );
                    lastClickedCheckbox = null;
                } );

                $table.on( 'click', '.sop-preorder-select-row', function( event ) {
                    var $checkboxes   = sopPreorderGetRowCheckboxesForSelection();
                    var currentIndex  = $checkboxes.index( this );
                    var lastIndex     = lastClickedCheckbox ? $checkboxes.index( lastClickedCheckbox ) : -1;

                    if ( event.shiftKey && lastIndex !== -1 && currentIndex !== -1 ) {
                        var start   = Math.min( lastIndex, currentIndex );
                        var end     = Math.max( lastIndex, currentIndex );
                        var checked = this.checked;

                        $checkboxes.slice( start, end + 1 ).prop( 'checked', checked );
                    }

                    lastClickedCheckbox = this;
                } );
            }

            // SOQ tooltip: delegated hover tooltip using custom container; suppress native titles.
            sopPreorderInitSoqTooltipData();
            $( document ).on( 'mousemove', function( e ) {
                lastMouseClientX = e.clientX;
                lastMouseClientY = e.clientY;
            } );
            $table.on( 'mouseenter focus', '.sop-soq-why', function() {
                var $el = $( this );
                $el.removeAttr( 'title' );
                $el.find( '[title]' ).removeAttr( 'title' );
                sopPreorderShowSoqTooltip( this );
            } );
            $table.on( 'mouseleave blur', '.sop-soq-why', function() {
                sopPreorderHideSoqTooltip();
            } );
            if ( $tableWrapper.length ) {
                $tableWrapper.on( 'scroll', function() {
                    sopPreorderHideSoqTooltip();
                    if ( soqScrollTimer ) {
                        clearTimeout( soqScrollTimer );
                    }
                    soqScrollTimer = setTimeout( function() {
                        if ( null === lastMouseClientX || null === lastMouseClientY ) {
                            return;
                        }
                        var el = document.elementFromPoint( lastMouseClientX, lastMouseClientY );
                        if ( ! el ) {
                            return;
                        }
                        var $target = $( el ).closest( '.sop-soq-why' );
                        if ( $target.length ) {
                            $target.removeAttr( 'title' );
                            $target.find( '[title]' ).removeAttr( 'title' );
                            sopPreorderShowSoqTooltip( $target.get( 0 ) );
                        }
                    }, 120 );
                } );
            }

            // Remove selected rows.
            if ( $removeSelectedBtn.length ) {
                $removeSelectedBtn.on( 'click', function( e ) {
                    e.preventDefault();

                    var $checkboxes = sopPreorderGetRowCheckboxesForSelection();
                    if ( ! $checkboxes.length ) {
                        return;
                    }

                    $checkboxes.each( function() {
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
                var notesType = $( this ).data( 'notes-type' ) || ( $( this ).hasClass( 'sop-preorder-notes-order' ) ? 'order' : ( $( this ).hasClass( 'sop-preorder-notes-internal' ) ? 'internal' : 'product' ) );
                sopPreorderOpenNotesOverlayForRow( $row, notesType );
            } );

            $table.on( 'click', '.sop-notes-preview.is-truncated', function( e ) {
                e.preventDefault();
                sopNotesPreviewOpen( $( this ) );
            } );

            $notesPreviewModal.on( 'click', '.sop-notes-preview-modal-close, .sop-notes-preview-modal-backdrop', function( e ) {
                e.preventDefault();
                sopNotesPreviewClose();
            } );

            $( document ).on( 'keydown', function( e ) {
                if ( 27 === e.which && $notesPreviewModal.is( ':visible' ) ) {
                    e.preventDefault();
                    sopNotesPreviewClose();
                }
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
                var totalRetailExcl = 0;

                var roundStep = parseInt( $('.sop-round-step').val(), 10 ) || 0;
                var roundTolerance = 1e-9;

                $table.find('tbody tr').each(function() {
                    var $row = $(this);
                    var removedFlag = $row.find('.sop-preorder-removed-flag').val();
                    var $roundedIndicator = $row.find( '.sop-rounded-indicator' );
                    if ( removedFlag === '1' ) {
                        if ( $roundedIndicator.length ) {
                            $roundedIndicator.hide();
                        }
                        return;
                    }

                    var qty = parseFloat($row.find('.sop-order-qty-input').val()) || 0;
                    if ( qty <= 0 ) {
                        if ( $roundedIndicator.length ) {
                            $roundedIndicator.hide();
                        }
                        return;
                    }

                    if ( $roundedIndicator.length ) {
                        var ratio = roundStep > 0 ? ( qty / roundStep ) : 0;
                        var isRounded = roundStep > 0 && qty > 0 && Math.abs( ratio - Math.round( ratio ) ) < roundTolerance;
                        if ( isRounded ) {
                            $roundedIndicator.show();
                        } else {
                            $roundedIndicator.hide();
                        }
                    }

                    var costGbp = parseFloat($row.find('.sop-line-total-gbp').data('cost-gbp')) || 0;
                    var costSupplier = parseFloat($row.find('.sop-line-total-supplier').data('cost-supplier')) || 0;
                    var cubicCm = parseFloat($row.find('.column-cubic-item').data('cubic-cm')) || 0;
                    var priceExcl = parseFloat($row.find('.column-regular-unit').data('price-excl')) || 0;

                    var lineTotalGbp = qty * costGbp;
                    var lineTotalSupplier = qty * costSupplier;
                    var lineRetailExcl = qty * priceExcl;

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
                    totalRetailExcl += lineRetailExcl;
                });

                $('#sop-total-units').text(Math.round(totalUnits));
                $('#sop-total-skus').text(totalSkus);
                $('#sop-total-cost-gbp').text(wc_price_format(totalCostGbp));
                $('#sop-total-cost-supplier').text(totalCostSupplier.toFixed(2));

                var totalProfit = totalRetailExcl - totalCostGbp;
                var marginPct = totalRetailExcl > 0 ? ( totalProfit / totalRetailExcl ) * 100 : 0;

                $('#sop-total-retail-gbp-excl').text(wc_price_format(totalRetailExcl));
                $('#sop-total-profit-gbp').text(wc_price_format(totalProfit));
                $('#sop-total-margin-pct').text(marginPct.toFixed(1) + '%');

                var additionalCbm = 0;
                var $additionalInput = $('input[name="sop_additional_cbm"][form="sop-preorder-filter-form"]');
                if ( $additionalInput.length ) {
                    var additionalVal = parseFloat( $additionalInput.val() );
                    if ( ! isNaN( additionalVal ) && additionalVal > 0 ) {
                        additionalCbm = additionalVal;
                    }
                }
                if ( additionalCbm < 0 ) {
                    additionalCbm = 0;
                }

                var usedCbmPercent = 0;
                if ( containerCbm > 0 && ( totalCbm + additionalCbm ) > 0 ) {
                    usedCbmPercent = ( ( totalCbm + additionalCbm ) / containerCbm ) * 100;
                }

                // Clamp bar width between 0 and 100, but show the raw percentage in the label.
                var usedCbmBar = usedCbmPercent;
                if ( usedCbmBar < 0 ) {
                    usedCbmBar = 0;
                } else if ( usedCbmBar > 100 ) {
                    usedCbmBar = 100;
                }

                var $cbmBar = $('.sop-cbm-bar');
                var cbmStateClass = 'sop-cbm-bar--yellow';
                if ( usedCbmPercent > 100 ) {
                    cbmStateClass = 'sop-cbm-bar--red';
                } else if ( usedCbmPercent >= 90 ) {
                    cbmStateClass = 'sop-cbm-bar--green';
                } else if ( usedCbmPercent >= 70 ) {
                    cbmStateClass = 'sop-cbm-bar--orange';
                }

                $cbmBar
                    .removeClass( 'sop-cbm-bar--yellow sop-cbm-bar--orange sop-cbm-bar--green sop-cbm-bar--red' )
                    .addClass( cbmStateClass )
                    .css('width', usedCbmBar + '%');
                $('.sop-cbm-bar-wrapper').attr('title', usedCbmPercent.toFixed(1) + '%');
                $('#sop-cbm-label').text(usedCbmPercent.toFixed(1) + '%');
            }

            $( document ).on( 'change input', 'input[name="sop_additional_cbm"][form="sop-preorder-filter-form"]', function() {
                recalcTotals();
            } );

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
                        return $row.find('.column-notes .sop-notes-preview').text() || $row.find('input[name^="sop_line_product_notes"]').val() || '';
                    case 'internal_product_notes':
                        return $row.find('.column-internal-product-notes .sop-notes-preview').text() || $row.find('input[name^="sop_line_internal_product_notes"]').val() || '';
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

            $( document ).on( 'change', '.sop-round-step', function() {
                recalcTotals();
            } );

            function sopPreorderApplyRounding( direction ) {
                var step = parseInt( $('.sop-round-step').val(), 10 ) || 0;
                if ( step <= 0 ) {
                    return;
                }

                var $rows = sopPreorderGetTargetRowsForBulkAction();
                $rows.each(function() {
                    var $row = $( this );
                    if ( $row.hasClass( 'sop-preorder-row-removed' ) ) {
                        return;
                    }

                    var $qtyInput = $row.find( '.sop-order-qty-input' );
                    if ( ! $qtyInput.length || $qtyInput.prop( 'disabled' ) ) {
                        return;
                    }

                    var val = parseFloat( $qtyInput.val() );
                    if ( isNaN( val ) || val <= 0 ) {
                        return;
                    }

                    var rounded = val;

                    if ( 'up' === direction ) {
                        rounded = Math.ceil(val / step) * step;
                    } else if ( 'down' === direction ) {
                        rounded = Math.floor(val / step) * step;
                    }

                    if (rounded < 0) {
                        rounded = 0;
                    }

                    $qtyInput.val( rounded );
                    hasUnsavedChanges = true;
                    $qtyInput.trigger('change');
                });

                recalcTotals();
            }

            $('#sop-round-up').on('click', function(e) {
                e.preventDefault();
                sopPreorderApplyRounding('up');
            });

            $('#sop-round-down').on('click', function(e) {
                e.preventDefault();
                sopPreorderApplyRounding('down');
            });

            $('#sop-apply-soq-to-qty').on('click', function(e) {
                e.preventDefault();

                var $rows = sopPreorderGetTargetRowsForBulkAction();

                $rows.each(function() {
                    var $row = $( this );

                    if ( $row.hasClass( 'sop-preorder-row-removed' ) ) {
                        return;
                    }

                    var $qtyInput = $row.find( 'input.sop-preorder-qty' );
                    var $soqEl    = $row.find( '.sop-preorder-soq' );
                    var $moqInput = $row.find( 'input.sop-preorder-moq' );

                    if ( ! $qtyInput.length || ! $soqEl.length || $qtyInput.prop( 'disabled' ) ) {
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

            function sopPreorderGetSortStorageKey() {
                var sheetId = parseInt( $('input[name="sop_sheet_id"]').first().val(), 10 ) || 0;
                var supplierId = parseInt( $('#sop-preorder-supplier').val(), 10 )
                    || parseInt( $('input[name="_sop_supplier_id"]').first().val(), 10 )
                    || 0;
                if ( sheetId > 0 ) {
                    return 'sop_preorder_sort_sheet_' + sheetId;
                }
                if ( supplierId > 0 ) {
                    return 'sop_preorder_sort_supplier_' + supplierId;
                }
                return 'sop_preorder_sort';
            }

            function sopPreorderSaveSortState(sortKey, isAsc) {
                if ( ! sortKey ) {
                    return;
                }
                try {
                    var payload = JSON.stringify({ key: sortKey, dir: isAsc ? 'asc' : 'desc' });
                    localStorage.setItem(sopPreorderGetSortStorageKey(), payload);
                } catch (e) {
                }
            }

            function sopPreorderLoadSortState() {
                try {
                    var raw = localStorage.getItem(sopPreorderGetSortStorageKey());
                    if ( ! raw ) {
                        return null;
                    }
                    var parsed = JSON.parse(raw);
                    if ( ! parsed || ! parsed.key ) {
                        return null;
                    }
                    return parsed;
                } catch (e) {
                    return null;
                }
            }

            function sopPreorderApplySort($th, sortKey, isAsc) {
                if ( ! sortKey || ! $th || ! $th.length ) {
                    return;
                }
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
            }

            $table.find('th[data-sort]').on('click', function() {
                var $th = $(this);
                var sortKey = $th.data('sort-key') || $th.data('sort');
                var isAsc = !$th.hasClass('sorted-asc');

                sopPreorderApplySort($th, sortKey, isAsc);
                sopPreorderSaveSortState(sortKey, isAsc);
            });

            if ( $table.length ) {
                var savedSort = sopPreorderLoadSortState();
                if ( savedSort && savedSort.key ) {
                    var $savedTh = $table.find('th[data-sort-key="' + savedSort.key + '"], th[data-sort="' + savedSort.key + '"]').first();
                    if ( $savedTh.length ) {
                        sopPreorderApplySort($savedTh, savedSort.key, savedSort.dir === 'asc');
                    }
                }
            }

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

                    var rowEl   = $row[0];
                    var wrapper = document.querySelector('.sop-preorder-table-wrapper');

                    // Work out the sticky header height (table thead) + a little padding.
                    var headerHeight = 0;
                    var $thead = $('.sop-preorder-table thead:visible').first();
                    if ( $thead.length ) {
                        headerHeight = $thead.outerHeight() || 0;
                    }
                    var padding = 4;

                    if ( wrapper ) {
                        // Use bounding rects to find the row's position inside the scrollable wrapper.
                        var wrapperRect = wrapper.getBoundingClientRect();
                        var rowRect     = rowEl.getBoundingClientRect();

                        // Row position within the wrapper viewport (can be negative if above).
                        var rowTopInsideWrapper = rowRect.top - wrapperRect.top;

                        // Convert to content space by adding current scrollTop.
                        var rowTopInContent = wrapper.scrollTop + rowTopInsideWrapper;

                        // Target so the row sits just below the sticky header.
                        var targetScrollTop = Math.max(
                            rowTopInContent - headerHeight - padding,
                            0
                        );

                        wrapper.scrollTop = targetScrollTop;
                        return;
                    }

                    // Fallback: if wrapper not found, scroll the window instead.
                    var rowOffset    = $row.offset().top;
                    var targetWindow = Math.max(
                        rowOffset - headerHeight - padding,
                        0
                    );

                    $('html, body').stop(true).animate({ scrollTop: targetWindow }, 200);
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

                $( '.sop-preorder-sku-icon, #sop_sku_filter_btn' ).on( 'click', function( e ) {
                    e.preventDefault();
                    e.stopPropagation();
                    var skuVal = $skuInput.val();
                    if ( ! skuVal ) {
                        $skuInput.trigger( 'focus' );
                        return;
                    }
                    scrollToSku( skuVal );
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
                var $depositFxLocked    = $( 'input[name=\"sop_po_deposit_fx_locked\"]' );
                var $balanceFxLocked    = $( 'input[name=\"sop_po_balance_fx_locked\"]' );
                var $balanceFxHelp      = $( '#sop-po-balance-fx-help' );
                var $extrasTable        = $( '.sop-po-items-table' );
                var $extrasAmountInputs = $extrasTable.find( '.sop-po-extra-amount' );
                var baseTotalRmb        = parseFloat( $poBaseLabel.data( 'base-total-rmb' ) ) || 0;
                var rmbPerUsd           = parseFloat( $( '#sop-po-rmb-per-usd' ).val() ) || 1;
                var isLockedExtras      = $extrasTable.data( 'locked' ) === 1 || $extrasTable.data( 'locked' ) === '1';
                var $depositFxSummary   = $( '#sop-po-deposit-fx-summary' );
                var $balanceFxSummary   = $( '#sop-po-balance-fx-summary' );
                var isRmbSupplier       = $depositRmbInput.length > 0 && $depositFxRateInput.length > 0;
                var balanceFxRateInitiallyDisabled = $balanceFxRateInput.length ? $balanceFxRateInput.is( ':disabled' ) : false;
                var balanceFxLockInitiallyDisabled = $balanceFxLocked.length ? $balanceFxLocked.is( ':disabled' ) : false;

                function sopPoRoundFx( value ) {
                    var num = parseFloat( value );
                    if ( isNaN( num ) || ! isFinite( num ) ) {
                        return 0;
                    }
                    num = Math.round( num * 1000 ) / 1000;
                    return num;
                }

                function sopNormaliseFxRateOnBlur( $input ) {
                    if ( ! $input || ! $input.length ) {
                        return;
                    }
                    var raw = $input.val();
                    if ( raw === '' || raw === null || typeof raw === 'undefined' ) {
                        return;
                    }
                    raw = String( raw ).trim().replace( ',', '.' );
                    if ( raw === '.' || raw === '-' ) {
                        return;
                    }
                    var n = parseFloat( raw );
                    if ( isNaN( n ) ) {
                        $input.val( '' );
                        return;
                    }
                    $input.val( n.toFixed( 3 ) );
                }

                function toggleBalanceFxAvailability() {
                    var depositLocked = $depositFxLocked.is( ':checked' );
                    var shouldDisableRate = balanceFxRateInitiallyDisabled || ! depositLocked;

                    if ( $balanceFxRateInput.length ) {
                        $balanceFxRateInput.prop( 'disabled', shouldDisableRate );
                        if ( ! depositLocked && ! balanceFxRateInitiallyDisabled ) {
                            $balanceFxRateInput.val( '' );
                        }
                    }

                    if ( $balanceFxLocked.length ) {
                        var shouldDisableLock = balanceFxLockInitiallyDisabled || ! depositLocked;
                        $balanceFxLocked.prop( 'disabled', shouldDisableLock );
                        if ( shouldDisableLock ) {
                            $balanceFxLocked.prop( 'checked', false );
                        }
                    }

                    if ( $balanceFxHelp.length ) {
                        if ( depositLocked || balanceFxRateInitiallyDisabled ) {
                            $balanceFxHelp.hide();
                        } else {
                            $balanceFxHelp.show();
                        }
                    }
                }

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

                    // Non-RMB suppliers: simple deposit/balance in supplier currency.
                    if ( ! isRmbSupplier ) {
                        var depositSimple = parseFloat( $depositUsdInput.val() );
                        if ( isNaN( depositSimple ) ) {
                            depositSimple = 0;
                        }

                        var balanceSimple = poTotal - depositSimple;
                        if ( balanceSimple < 0 ) {
                            balanceSimple = 0;
                        }

                        $poTotalLabel.text( poTotal.toFixed( 2 ) );

                        if ( $balanceUsdLabel.length ) {
                            $balanceUsdLabel.text( balanceSimple.toFixed( 2 ) );
                        }
                        if ( $balanceUsdInput.length ) {
                            $balanceUsdInput.val( balanceSimple.toFixed( 2 ) );
                        }

                        // No FX logic for non-RMB suppliers.
                        return;
                    }

                    var depositUsd = parseFloat( $depositUsdInput.val() );
                    if ( isNaN( depositUsd ) ) {
                        depositUsd = 0;
                    }

                    var depositFxRate = sopPoRoundFx( $depositFxRateInput.val() );
                    if ( depositFxRate <= 0 ) {
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

                    var depositLocked = $depositFxLocked.is( ':checked' );

                    var balanceFxRate = sopPoRoundFx( $balanceFxRateInput.val() );
                    if ( ! depositLocked ) {
                        balanceFxRate = 0;
                        $balanceFxRateInput.val( '' );
                    }
                    var balanceUsd = ( depositLocked && balanceRmb > 0 && balanceFxRate > 0 ) ? ( balanceRmb / balanceFxRate ) : 0;
                    $balanceUsdLabel.text( ( depositLocked && balanceFxRate > 0 ) ? balanceUsd.toFixed( 2 ) : '' );
                    $balanceUsdInput.val( ( depositLocked && balanceFxRate > 0 ) ? balanceUsd.toFixed( 2 ) : '' );

                    // Update FX summaries for quick reference.
                    if ( $depositFxSummary.length ) {
                        if ( depositFxRate > 0 ) {
                            $depositFxSummary.text( '1 USD = ' + depositFxRate.toFixed( 3 ) + ' RMB' );
                        } else {
                            $depositFxSummary.text( '' );
                        }
                    }
                    if ( $balanceFxSummary.length ) {
                        if ( depositLocked && balanceFxRate > 0 ) {
                            $balanceFxSummary.text( '1 USD = ' + balanceFxRate.toFixed( 3 ) + ' RMB' );
                        } else {
                            $balanceFxSummary.text( '' );
                        }
                    }
                }

                function toggleBalanceFxAvailability() {
                    if ( ! isRmbSupplier || ! $balanceFxRateInput.length || ! $depositFxLocked.length ) {
                        return;
                    }

                    var depositLocked = $depositFxLocked.is( ':checked' );

                    if ( ! depositLocked ) {
                        $balanceFxRateInput.prop( 'disabled', true );
                        if ( $balanceFxLocked.length ) {
                            $balanceFxLocked.prop( 'disabled', true );
                        }
                        if ( $balanceFxHelp.length ) {
                            $balanceFxHelp.show();
                        }
                    } else {
                        if ( $balanceFxRateInput.length ) {
                            $balanceFxRateInput.prop( 'disabled', balanceFxRateInitiallyDisabled ? true : false );
                        }
                        if ( $balanceFxLocked.length ) {
                            $balanceFxLocked.prop( 'disabled', balanceFxLockInitiallyDisabled ? true : false );
                        }
                        if ( $balanceFxHelp.length ) {
                            $balanceFxHelp.hide();
                        }
                    }
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
                            '<td class=\"column-amount\"><input type=\"number\" step=\"0.01\" class=\"sop-po-extra-amount sop-po-amount\" name=\"sop_po_extra_amount[]\" value=\"0\" /></td>' +
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

                if ( $depositFxLocked.length ) {
                    $depositFxLocked.on( 'change', function() {
                        toggleBalanceFxAvailability();
                        recalcPoTotals();
                    } );
                }

                if ( $depositFxRateInput.length ) {
                    $depositFxRateInput.on( 'blur', function() {
                        sopNormaliseFxRateOnBlur( $depositFxRateInput );
                        recalcPoTotals();
                    } );
                }
                if ( $balanceFxRateInput.length ) {
                    $balanceFxRateInput.on( 'blur', function() {
                        sopNormaliseFxRateOnBlur( $balanceFxRateInput );
                        recalcPoTotals();
                    } );
                }

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
                var leadWeeks = parseFloat( $( '#sop-po-lead-weeks' ).val() );
                if ( isNaN( leadWeeks ) ) {
                    leadWeeks = 0;
                }
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
                var sopPoIsNewSheet = <?php echo $is_new_sheet ? 'true' : 'false'; ?>;
                var sopPoHolidayAutofillDisabled = false;

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

                function sopBuildHolidayMdFromYmd( ymd ) {
                    if ( ! ymd ) {
                        return '';
                    }
                    var parts = ymd.split( '-' );
                    if ( parts.length !== 3 ) {
                        return '';
                    }
                    var month = parseInt( parts[1], 10 );
                    var day   = parseInt( parts[2], 10 );
                    if ( ! month || ! day ) {
                        return '';
                    }
                    var mm = ( month < 10 ? '0' + month : '' + month );
                    var dd = ( day < 10 ? '0' + day : '' + day );
                    return mm + '-' + dd;
                }

                function sopResolveHolidayYmdRange( orderYmd, startMd, endMd ) {
                    if ( ! orderYmd || ! startMd || ! endMd ) {
                        return { startYmd: '', endYmd: '' };
                    }
                    var orderParts = orderYmd.split( '-' );
                    if ( orderParts.length !== 3 ) {
                        return { startYmd: '', endYmd: '' };
                    }
                    var orderYear = parseInt( orderParts[0], 10 );
                    if ( ! orderYear ) {
                        return { startYmd: '', endYmd: '' };
                    }
                    var startParts = startMd.split( '-' );
                    var endParts   = endMd.split( '-' );
                    if ( startParts.length !== 2 || endParts.length !== 2 ) {
                        return { startYmd: '', endYmd: '' };
                    }
                    var sm = parseInt( startParts[0], 10 );
                    var sd = parseInt( startParts[1], 10 );
                    var em = parseInt( endParts[0], 10 );
                    var ed = parseInt( endParts[1], 10 );
                    if ( ! sm || ! sd || ! em || ! ed ) {
                        return { startYmd: '', endYmd: '' };
                    }

                    var buildYmd = function( year, month, day ) {
                        if ( ! year || ! month || ! day ) {
                            return '';
                        }
                        var mm = ( month < 10 ? '0' + month : '' + month );
                        var dd = ( day < 10 ? '0' + day : '' + day );
                        return year + '-' + mm + '-' + dd;
                    };

                    var startMdNum = ( sm * 100 ) + sd;
                    var endMdNum   = ( em * 100 ) + ed;
                    var startYear  = orderYear;
                    var endYear    = orderYear;
                    if ( startMdNum > endMdNum ) {
                        endYear = orderYear + 1;
                    }

                    var startYmd = buildYmd( startYear, sm, sd );
                    var endYmd   = buildYmd( endYear, em, ed );
                    if ( ! startYmd || ! endYmd ) {
                        return { startYmd: '', endYmd: '' };
                    }

                    var orderDate = new Date( orderYmd );
                    var endDate   = new Date( endYmd );
                    if ( ! isNaN( orderDate.getTime() ) && ! isNaN( endDate.getTime() ) && endDate < orderDate ) {
                        startYear = orderYear + 1;
                        endYear   = startYear + ( startMdNum > endMdNum ? 1 : 0 );
                        startYmd  = buildYmd( startYear, sm, sd );
                        endYmd    = buildYmd( endYear, em, ed );
                    }

                    return {
                        startYmd: startYmd,
                        endYmd: endYmd
                    };
                }

                function sopFindFirstOverlappingSupplierHoliday( orderYmd, orderDate, baselineLoadDate, holidayPeriodsMd ) {
                    if ( ! orderYmd || ! orderDate || ! baselineLoadDate || isNaN( orderDate.getTime() ) || isNaN( baselineLoadDate.getTime() ) ) {
                        return null;
                    }
                    if ( ! Array.isArray( holidayPeriodsMd ) || ! holidayPeriodsMd.length ) {
                        return null;
                    }
                    var match = null;
                    for ( var i = 0; i < holidayPeriodsMd.length; i++ ) {
                        var period = holidayPeriodsMd[ i ] || {};
                        var startMd = period.start_md || period.start || '';
                        var endMd   = period.end_md || period.end || '';
                        if ( ! startMd || ! endMd ) {
                            continue;
                        }
                        var resolved = sopResolveHolidayYmdRange( orderYmd, startMd, endMd );
                        if ( ! resolved.startYmd || ! resolved.endYmd ) {
                            continue;
                        }
                        var startDate = new Date( resolved.startYmd );
                        var endDate   = new Date( resolved.endYmd );
                        if ( isNaN( startDate.getTime() ) || isNaN( endDate.getTime() ) ) {
                            continue;
                        }
                        if ( startDate <= baselineLoadDate && endDate >= orderDate ) {
                            if ( ! match || startDate < match.startDate ) {
                                match = {
                                    startMd: startMd,
                                    endMd: endMd,
                                    startYmd: resolved.startYmd,
                                    endYmd: resolved.endYmd,
                                    startDate: startDate,
                                    endDate: endDate
                                };
                            }
                        }
                    }
                    if ( ! match ) {
                        return null;
                    }
                    return {
                        startMd: match.startMd,
                        endMd: match.endMd,
                        startYmd: match.startYmd,
                        endYmd: match.endYmd
                    };
                }

                function sopIsDayInHolidayPeriod( month, day, period ) {
                    if ( ! period || ( ! period.start && ! period.start_md ) || ( ! period.end && ! period.end_md ) ) {
                        return false;
                    }

                    var startVal   = period.start || period.start_md;
                    var endVal     = period.end || period.end_md;
                    var startParts = startVal.split( '-' );
                    var endParts   = endVal.split( '-' );

                    if ( startParts.length !== 2 || endParts.length !== 2 ) {
                        return false;
                    }

                    var sm = parseInt( startParts[0], 10 );
                    var sd = parseInt( startParts[1], 10 );
                    var em = parseInt( endParts[0], 10 );
                    var ed = parseInt( endParts[1], 10 );

                    if ( ! sm || ! sd || ! em || ! ed ) {
                        return false;
                    }

                    // Build comparable MMDD numbers to handle wrap-around periods.
                    var current = ( month * 100 ) + day;
                    var startMd = ( sm * 100 ) + sd;
                    var endMd   = ( em * 100 ) + ed;

                    if ( startMd <= endMd ) {
                        // Normal period within the same year.
                        return current >= startMd && current <= endMd;
                    }

                    // Wrap-around period (e.g. Dec -> Jan).
                    return current >= startMd || current <= endMd;
                }

                function sopIsHolidayDay( dateObj, holidayPeriodsMd ) {
                    if ( ! dateObj || isNaN( dateObj.getTime() ) || ! Array.isArray( holidayPeriodsMd ) ) {
                        return false;
                    }

                    var month = dateObj.getMonth() + 1;
                    var day   = dateObj.getDate();

                    for ( var i = 0; i < holidayPeriodsMd.length; i++ ) {
                        if ( sopIsDayInHolidayPeriod( month, day, holidayPeriodsMd[ i ] ) ) {
                            return true;
                        }
                    }
                    return false;
                }

                function sopAddHandlingWorkingDays( orderDate, handlingDays, holidayPeriodsMd ) {
                    var current = new Date( orderDate.getTime() );
                    var worked  = 0;
                    var guard   = 0;
                    var maxDays = handlingDays + 366; // prevents runaway if holidays cover all dates.

                    if ( isNaN( current.getTime() ) || handlingDays <= 0 ) {
                        return current;
                    }

                    while ( worked < handlingDays && guard < maxDays ) {
                        current.setDate( current.getDate() + 1 );
                        if ( ! sopIsHolidayDay( current, holidayPeriodsMd ) ) {
                            worked++;
                        }
                        guard++;
                    }

                    return current;
                }

                function sopDateToYmd( dateObj ) {
                    if ( ! dateObj || isNaN( dateObj.getTime() ) ) {
                        return '';
                    }
                    var m  = '' + ( dateObj.getMonth() + 1 );
                    var dd = '' + dateObj.getDate();
                    var yyyy = dateObj.getFullYear();
                    if ( m.length < 2 ) { m = '0' + m; }
                    if ( dd.length < 2 ) { dd = '0' + dd; }
                    return yyyy + '-' + m + '-' + dd;
                }

                function sopRecalcPoDatesFromOrder() {
                    var $orderDate    = $( 'input[name=\"sop_po_order_date\"]' );
                    var $loadDate     = $( 'input[name=\"sop_po_load_date\"]' );
                    var $arrivalDate  = $( 'input[name=\"sop_po_arrival_date\"]' );
                    var $holidayStart = $( 'input[name=\"sop_po_holiday_start\"]' );
                    var $holidayEnd   = $( 'input[name=\"sop_po_holiday_end\"]' );

                    if ( ! $orderDate.length || ! $loadDate.length || ! $arrivalDate.length ) {
                        return;
                    }

                    var orderYmd = $orderDate.val();
                    if ( ! orderYmd ) {
                        return;
                    }

                    var orderDate = new Date( orderYmd );
                    if ( isNaN( orderDate.getTime() ) ) {
                        return;
                    }

                    var leadDays = Math.round( leadWeeks * 7 );
                    if ( ! leadDays ) {
                        return;
                    }

                    // Handling portion excludes shipping.
                    var handlingDays = leadDays - supplierShippingDays;
                    if ( handlingDays < 0 ) {
                        handlingDays = 0;
                    }

                    // Holiday overrides for this PO.
                    var holidayStartYmd = $holidayStart.length ? $holidayStart.val() : '';
                    var holidayEndYmd   = $holidayEnd.length ? $holidayEnd.val() : '';

                    var baselineLoadDate = sopAddHandlingWorkingDays( orderDate, handlingDays, holidayPeriodsMd );
                    var overlappingSupplierHoliday = sopFindFirstOverlappingSupplierHoliday( orderYmd, orderDate, baselineLoadDate, holidayPeriodsMd );

                    var savedStartMd = holidayStartYmd ? sopBuildHolidayMdFromYmd( holidayStartYmd ) : '';
                    var savedEndMd   = holidayEndYmd ? sopBuildHolidayMdFromYmd( holidayEndYmd ) : '';
                    var savedMatchesSupplier = false;
                    if ( savedStartMd && savedEndMd && holidayPeriodsMd.length ) {
                        for ( var smi = 0; smi < holidayPeriodsMd.length; smi++ ) {
                            var savedPeriod = holidayPeriodsMd[ smi ] || {};
                            var savedPeriodStart = savedPeriod.start_md || savedPeriod.start || '';
                            var savedPeriodEnd   = savedPeriod.end_md || savedPeriod.end || '';
                            if ( savedPeriodStart === savedStartMd && savedPeriodEnd === savedEndMd ) {
                                savedMatchesSupplier = true;
                                break;
                            }
                        }
                    }

                    if ( savedMatchesSupplier ) {
                        var resolvedSaved = sopResolveHolidayYmdRange( orderYmd, savedStartMd, savedEndMd );
                        if ( resolvedSaved.startYmd && resolvedSaved.endYmd ) {
                            if ( holidayStartYmd !== resolvedSaved.startYmd || holidayEndYmd !== resolvedSaved.endYmd ) {
                                holidayStartYmd = resolvedSaved.startYmd;
                                holidayEndYmd   = resolvedSaved.endYmd;
                                if ( $holidayStart.length ) {
                                    $holidayStart.val( holidayStartYmd );
                                }
                                if ( $holidayEnd.length ) {
                                    $holidayEnd.val( holidayEndYmd );
                                }
                            }
                            var savedStartDate = new Date( holidayStartYmd );
                            var savedEndDate   = new Date( holidayEndYmd );
                            var savedOverlapsHandling = false;
                            if ( ! isNaN( savedStartDate.getTime() ) && ! isNaN( savedEndDate.getTime() ) ) {
                                savedOverlapsHandling = ( savedStartDate <= baselineLoadDate && savedEndDate >= orderDate );
                            }
                            if ( ! savedOverlapsHandling ) {
                                holidayStartYmd = '';
                                holidayEndYmd   = '';
                                if ( $holidayStart.length ) {
                                    $holidayStart.val( '' );
                                }
                                if ( $holidayEnd.length ) {
                                    $holidayEnd.val( '' );
                                }
                            }
                        }
                    }

                    // Prefill PO holiday fields from supplier periods if blank.
                    if ( ! holidayStartYmd && ! holidayEndYmd && ! sopPoHolidayAutofillDisabled && overlappingSupplierHoliday ) {
                        holidayStartYmd = overlappingSupplierHoliday.startYmd;
                        holidayEndYmd   = overlappingSupplierHoliday.endYmd;
                        if ( $holidayStart.length ) {
                            $holidayStart.val( holidayStartYmd );
                        }
                        if ( $holidayEnd.length ) {
                            $holidayEnd.val( holidayEndYmd );
                        }
                        sopPoHolidayAutofillDisabled = true;
                    }

                    // If a holiday period is set on this PO, build an override period in month/day format.
                    var overridePeriod = null;
                    if ( holidayStartYmd && holidayEndYmd ) {
                        var startMdOverride = sopBuildHolidayMdFromYmd( holidayStartYmd );
                        var endMdOverride   = sopBuildHolidayMdFromYmd( holidayEndYmd );
                        if ( startMdOverride && endMdOverride ) {
                            overridePeriod = {
                                start_md: startMdOverride,
                                end_md:   endMdOverride
                            };
                        }
                    }

                    var workingHolidayPeriods = holidayPeriodsMd.slice();
                    if ( overridePeriod ) {
                        var replaced = false;
                        if ( overlappingSupplierHoliday && overlappingSupplierHoliday.startMd && overlappingSupplierHoliday.endMd ) {
                            for ( var wi = 0; wi < workingHolidayPeriods.length; wi++ ) {
                                var workingPeriod = workingHolidayPeriods[ wi ] || {};
                                var workingStart = workingPeriod.start_md || workingPeriod.start || '';
                                var workingEnd   = workingPeriod.end_md || workingPeriod.end || '';
                                if ( workingStart === overlappingSupplierHoliday.startMd && workingEnd === overlappingSupplierHoliday.endMd ) {
                                    workingHolidayPeriods[ wi ] = overridePeriod;
                                    replaced = true;
                                    break;
                                }
                            }
                        }
                        if ( ! replaced ) {
                            workingHolidayPeriods.push( overridePeriod );
                        }
                    }

                    // Container load date: add handling working days (holidays extend handling).
                    var loadDate = sopAddHandlingWorkingDays( orderDate, handlingDays, workingHolidayPeriods );
                    var loadYmd = sopDateToYmd( loadDate );
                    if ( loadYmd && $loadDate.length ) {
                        $loadDate.val( loadYmd );
                    }

                    // ETA: load date + shipping days (holidays do not affect shipping).
                    var etaDate = new Date( loadDate.getTime() );
                    etaDate.setDate( etaDate.getDate() + supplierShippingDays );
                    var etaYmd = sopDateToYmd( etaDate );
                    if ( etaYmd && $arrivalDate.length ) {
                        $arrivalDate.val( etaYmd );
                    }
                }

                $( document ).on( 'change', 'input[name=\"sop_po_order_date\"]', sopRecalcPoDatesFromOrder );
                $( document ).on( 'change', 'input[name=\"sop_po_holiday_start\"], input[name=\"sop_po_holiday_end\"]', function() {
                    var startVal = $( 'input[name=\"sop_po_holiday_start\"]' ).val() || '';
                    var endVal   = $( 'input[name=\"sop_po_holiday_end\"]' ).val() || '';
                    if ( ! startVal && ! endVal ) {
                        sopPoHolidayAutofillDisabled = true;
                    }
                    sopRecalcPoDatesFromOrder();
                } );

                // Recalculate on load if an order date already exists.
                if ( $( 'input[name=\"sop_po_order_date\"]' ).length && $( 'input[name=\"sop_po_order_date\"]' ).val() ) {
                    sopRecalcPoDatesFromOrder();
                }
            })();

            recalcTotals();

            // ------------------------------------------------------------------
            // PO payload bundling: pack modal fields into JSON before submit
            // ------------------------------------------------------------------
            (function() {
                var $form = $( '#sop-preorder-sheet-form' );

                function sopPoBuildPayload() {
                    var orderDate    = $( 'input[name=\"sop_po_order_date\"]' ).val() || '';
                    var loadDate     = $( 'input[name=\"sop_po_load_date\"]' ).val() || '';
                    var arrivalDate  = $( 'input[name=\"sop_po_arrival_date\"]' ).val() || '';
                    var holidayStart = $( 'input[name=\"sop_po_holiday_start\"]' ).val() || '';
                    var holidayEnd   = $( 'input[name=\"sop_po_holiday_end\"]' ).val() || '';

                    var depositUsd   = $( 'input[name=\"sop_po_deposit_usd\"]' ).val() || '';
                    var depositRmb   = $( 'input[name=\"sop_po_deposit_rmb\"]' ).val() || '';
                    var depositFx    = $( 'input[name=\"sop_po_deposit_fx_rate\"]' ).val() || '';
                    var depositLocked = $( 'input[name=\"sop_po_deposit_fx_locked\"]' ).is( ':checked' ) ? 1 : 0;

                    var balanceFx    = $( 'input[name=\"sop_po_balance_fx_rate\"]' ).val() || '';
                    var balanceUsd   = $( 'input[name=\"sop_po_balance_usd\"]' ).val() || '';
                    var balanceLocked = $( 'input[name=\"sop_po_balance_fx_locked\"]' ).is( ':checked' ) ? 1 : 0;

                    var extras = [];
                    var $extraLabels  = $( 'input[name=\"sop_po_extra_label[]\"]' );
                    var $extraAmounts = $( 'input[name=\"sop_po_extra_amount[]\"]' );
                    $extraLabels.each( function( index ) {
                        var label  = $( this ).val() || '';
                        var amount = '';
                        if ( $extraAmounts.length > index ) {
                            amount = $extraAmounts.eq( index ).val() || '';
                        }

                        if ( '' !== label || '' !== amount ) {
                            extras.push( {
                                label: label,
                                amount_rmb: amount
                            } );
                        }
                    } );

                    var payload = {
                        order_date: orderDate,
                        load_date: loadDate,
                        arrival_date: arrivalDate,
                        holiday_start: holidayStart,
                        holiday_end: holidayEnd,
                        deposit_usd: depositUsd,
                        deposit_rmb: depositRmb,
                        deposit_fx_rate: depositFx,
                        deposit_fx_locked: depositLocked,
                        balance_fx_rate: balanceFx,
                        balance_fx_locked: balanceLocked,
                        balance_usd: balanceUsd,
                        po_extras: extras
                    };

                    $( '#sop-po-payload' ).val( JSON.stringify( payload ) );
                }

                function sopPreorderBuildLinesPayload() {
                    var $linesField = $( '#sop-lines-json' );
                    if ( ! $linesField.length ) {
                        return true;
                    }
                    try {
                        var lines = [];
                        $table.find( 'tbody tr.sop-preorder-row' ).each( function() {
                            var $row = $( this );
                            var productId = parseInt( $row.find( 'input[name^="sop_line_product_id"]' ).val(), 10 );
                            if ( isNaN( productId ) || productId <= 0 ) {
                                return;
                            }
                            var sku = $row.find( 'input[name^="sop_line_sku"]' ).val() || '';
                            var imageId = parseInt( $row.find( 'input[name^="sop_line_image_id"]' ).val(), 10 );
                            if ( isNaN( imageId ) ) { imageId = 0; }
                            var location = $row.find( 'input[name^="sop_line_location"]' ).val() || '';
                            var qty = parseFloat( $row.find( 'input[name^="sop_line_qty"]' ).val() );
                            if ( isNaN( qty ) ) { qty = 0; }
                            var moq = parseFloat( $row.find( 'input[name^="sop_line_moq"]' ).val() );
                            if ( isNaN( moq ) ) { moq = 0; }
                            var costRmb = parseFloat( $row.find( 'input[name^="sop_line_cost_rmb"]' ).val() );
                            if ( isNaN( costRmb ) ) { costRmb = 0; }
                            var productNotes = $row.find( 'input[name^=\"sop_line_product_notes\"]' ).val() || '';
                            var internalNotes = $row.find( 'input[name^=\"sop_line_internal_product_notes\"]' ).val() || '';
                            var orderNotes = $row.find( 'textarea[name^="sop_line_order_notes"]' ).val() || '';
                            var cartonNo = $row.find( 'input[name^="sop_line_carton_no"]' ).val() || '';
                            var cubicCm = parseFloat( $row.find( '.column-cubic-item' ).data( 'cubic-cm' ) );
                            if ( isNaN( cubicCm ) ) { cubicCm = 0; }
                            var cbmTotal = ( cubicCm * qty ) / 1000000;
                            var removedVal = 0;
                            var $removedInput = $row.find( '.sop-preorder-removed-flag' );
                            if ( $removedInput.length && String( $removedInput.val() ) === '1' ) {
                                removedVal = 1;
                            } else if ( $row.hasClass( 'sop-preorder-row-removed' ) ) {
                                removedVal = 1;
                            }

                            lines.push( {
                                product_id: productId,
                                sku: sku,
                                image_id: imageId,
                                location: location,
                                qty: qty,
                                moq: moq,
                                cost_rmb: costRmb,
                                product_notes: productNotes,
                                internal_product_notes: internalNotes,
                                order_notes: orderNotes,
                                carton_no: cartonNo,
                                cbm_per_unit: cubicCm,
                                cbm_total: cbmTotal,
                                is_removed_owner: removedVal
                            } );
                    } );
                        var payloadLines = {
                            v: 1,
                            lines: lines
                        };
                        $linesField.val( JSON.stringify( payloadLines ) );
                        return true;
                    } catch ( err ) {
                        alert( 'Could not prepare line items for saving. Please retry.' );
                        return false;
                    }
                }

            function sopPreorderStripLineInputNames() {
                $( '[name^="sop_line_"]' ).removeAttr( 'name' );
                $( '[name="sop_product_id[]"]' ).removeAttr( 'name' );
                $( '[name="sop_sku[]"]' ).removeAttr( 'name' );
                $( '[name="sop_removed[]"]' ).removeAttr( 'name' );
            }

            function sopPreorderSyncPlanningFieldsToSheetForm() {
                var $sheetForm = $( '#sop-preorder-sheet-form' );
                if ( ! $sheetForm.length ) {
                    return;
                }

                var $hiddenContainer = $sheetForm.find( 'input[name="sop_container_type"]' );
                var $hiddenAllowance = $sheetForm.find( 'input[name="sop_allowance_percent"]' );
                var $hiddenPallet    = $sheetForm.find( 'input[name="sop_pallet_layer"]' );
                var $hiddenAdditional = $sheetForm.find( 'input[name="sop_additional_cbm"]' );

                var containerType = $hiddenContainer.length ? $hiddenContainer.val() : '';
                var allowanceVal  = $hiddenAllowance.length ? $hiddenAllowance.val() : 0;
                var palletOn      = $hiddenPallet.length ? parseInt( $hiddenPallet.val(), 10 ) : 0;
                var additionalVal = $hiddenAdditional.length ? $hiddenAdditional.val() : 0;
                if ( isNaN( palletOn ) ) {
                    palletOn = 0;
                }

                var $containerSelect = $( 'select[name="sop_container"][form="sop-preorder-filter-form"]' );
                var $allowanceInput  = $( 'input[name="sop_allowance"][form="sop-preorder-filter-form"]' );
                var $palletCheckbox  = $( 'input[type="checkbox"][name="sop_pallet_layer"][form="sop-preorder-filter-form"]' );
                var $additionalInput = $( 'input[name="sop_additional_cbm"][form="sop-preorder-filter-form"]' );

                if ( !$containerSelect.length && !$allowanceInput.length && !$palletCheckbox.length && !$additionalInput.length ) {
                    return;
                }

                if ( $containerSelect.length ) {
                    var selectedContainer = $containerSelect.val();
                    if ( typeof selectedContainer !== 'undefined' && selectedContainer !== null && selectedContainer !== '' ) {
                        containerType = selectedContainer;
                    }
                }

                if ( $allowanceInput.length ) {
                    var inputAllowance = $allowanceInput.val();
                    if ( typeof inputAllowance !== 'undefined' && inputAllowance !== null && inputAllowance !== '' ) {
                        allowanceVal = inputAllowance;
                    }
                }

                if ( $palletCheckbox.length ) {
                    palletOn = $palletCheckbox.is( ':checked' ) ? 1 : 0;
                }

                if ( $additionalInput.length ) {
                    var inputAdditional = $additionalInput.val();
                    if ( typeof inputAdditional !== 'undefined' && inputAdditional !== null && inputAdditional !== '' ) {
                        additionalVal = inputAdditional;
                    }
                }

                allowanceVal = parseFloat( allowanceVal );
                if ( isNaN( allowanceVal ) ) {
                    allowanceVal = 0;
                } else if ( allowanceVal > 50 ) {
                    allowanceVal = 50;
                } else if ( allowanceVal < -50 ) {
                    allowanceVal = -50;
                }

                additionalVal = parseFloat( additionalVal );
                if ( isNaN( additionalVal ) || additionalVal < 0 ) {
                    additionalVal = 0;
                } else if ( additionalVal > 9999 ) {
                    additionalVal = 9999;
                }
                additionalVal = Math.round( additionalVal * 1000 ) / 1000;

                if ( $hiddenContainer.length ) {
                    $hiddenContainer.val( containerType );
                }
                if ( $hiddenAllowance.length ) {
                    $hiddenAllowance.val( allowanceVal );
                }
                if ( $hiddenPallet.length ) {
                    $hiddenPallet.val( palletOn );
                }
                if ( $hiddenAdditional.length ) {
                    $hiddenAdditional.val( additionalVal );
                }
            }

            function sopPreorderPrepareSheetSubmit() {
                sopPreorderSyncPlanningFieldsToSheetForm();
                sopPoBuildPayload();
                var okLines = sopPreorderBuildLinesPayload();
                if ( ! okLines ) {
                    return false;
                }
                    sopPreorderStripLineInputNames();
                    return true;
                }

                if ( $form.length ) {
                    $form.on( 'submit', function() {
                        return sopPreorderPrepareSheetSubmit();
                    } );
                }

                var $topUpdate = $( '#sop-update-sheet-top' );
                if ( $topUpdate.length && $form.length ) {
                    $topUpdate.on( 'click', function( e ) {
                        e.preventDefault();
                        if ( ! sopPreorderPrepareSheetSubmit() ) {
                            return;
                        }
                        if ( $form[0] && typeof $form[0].submit === 'function' ) {
                            $form[0].submit();
                        }
                    } );
                }
            })();
        });
    </script>
    <?php
}


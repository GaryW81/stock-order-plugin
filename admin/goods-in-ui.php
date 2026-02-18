<?php
/**
 * Stock Order Plugin - Phase 5 (Goods-In v1) - Admin UI
 * File version: 1.1.35
 * - Add 2-step confirmation modal for Complete Goods-In.
 * - Use capability helper for Stock Order UI access.
 *
 * - 1.1.33 - Goods-In: show correct +/- delta in apply-stock progress status.
 * - 1.1.32 - Notes: render rich product/internal notes with kses-sanitised HTML.
 * - 1.1.31 - Notes: clamp preview height to prevent row expansion.
 * - 1.1.30 - Notes: render product/internal notes with rich preview + modal.
 *
 * - 1.1.29 - Goods-In: update apply-stock wording for correction support.
 * - 1.1.28 - Goods-In list: count only ordered/active lines and show open lines + outstanding units.
 * - 1.1.27 - Improve carton filter: numeric/range match + multi-carton support (avoid substring matches like "1").
 *
 * - 1.1.26 - Add skipped-line badges + summary for AJAX apply stock.
 * - 1.1.25 - Show per-line completion tick for ordered lines (AJAX + server render).
 * - 1.1.24 - Keep Goods-In apply stock results in-page (no redirect) to avoid beforeunload prompt.
 * - 1.1.23 - Apply stock via AJAX batches with progress UI to prevent timeouts.
 * - 1.1.22 - Persist Goods-In table sort state across reload (per sheet/session).
 * - 1.1.21 - UI: force Goods-In notes popup true vertical centering on mobile.
 * - 1.1.20 - UI: center notes popup vertically on mobile (Goods-In modal).
 * - 1.1.19 - UI: Goods-In modal — grey missing-note pills + desktop name/SKU in one row.
 * - 1.1.18 - UI: center qty +/- glyphs on desktop (Goods-In modal).
 * - 1.1.17 - UI: fine-tune Goods-In header icon nudge for extreme zoom (desktop).
 * - 1.1.16 - UI: make Goods-In header icon alignment zoom-robust (em-based nudge).
 * - 1.1.15 - UI: nudge Goods-In modal header icons further up on desktop.
 * - 1.1.13 - UI: fix Goods-In modal header button icon vertical alignment (desktop).
 * - 1.1.12 - UI: refine Goods-In notes buttons (pill style + full-width row).
 * - 1.1.11 - UI: compress Goods-In modal notes into single button row (desktop), stacked on mobile.
 * - 1.1.10 - UI: show internal notes + current/buffer stock in Goods-In modal.
 * - 1.1.09 - UI: show internal notes + current/buffer stock in Goods-In modal.
 * - 1.1.08 - UI: add internal product notes column.
 * - 1.1.07 - Use per-sheet removed flag for Goods-In lines.
 * - 1.1.06 - UI: 3-stage labels (In Progress/Ordered/Completed) + GI started indicator.
 * - 1.1.05 - Version bump for Goods-In UI/core.
 * - 1.1.03 - Goods-In: add Issues XLSX export button + align export params.
 * - 1.1.02 - UI: make received Goods-In sheets read-only (disable edits/actions).
 * - 1.1.01 - UI: consolidate scan overlay UI (remove legacy scan modal chrome).
 * - 1.1.00 - UI: scanner overlay frame/scan line + beep/vibrate on success.
 * - 1.0.99 - Mobile: allow vertical scroll inside Goods-In table wrapper (portrait).
 * - 1.0.98 - Mobile: ensure scan overlay above product modal and lock interaction while scanning.
 * - 1.0.97 - UI: desktop center +/- icons in qty stepper buttons.
 * - 1.0.96 - Mobile: modal height uses visual viewport var to remove bottom gap.
 * - 1.0.95 - Mobile: hide WP admin bar during Goods-In modals; remove top gap.
 * - 1.0.94 - UI: Goods-In modals respect WP adminbar height + reduce mobile bounce.
 * - 1.0.93 - UI: Goods-In product modal full-screen on mobile/tablet + full-height desktop.
 * - 1.0.92 - Goods-In: notes title/centering + typed qty input in product modal.
 * - 1.0.91 - Goods-In: use carton text for search/filter/modal after input removal.
 * - 1.0.90 - Goods-In: carton text display + numeric sort by first carton number.
 * - 1.0.89 - Goods-In: load carton_no from saved lines and render carton read-only.
 * - 1.0.88 - Scan input opens modal without altering search filter (prev/next stays active).
 * - 1.0.87 - Mobile: modal prev/next navigation respects visible Goods-In rows.
 * - 1.0.86 - Goods-In list: toggle completed sheets and preserve filter in links.
 * - 1.0.85 - Desktop: restore Goods-In toolbar layout; keep mobile grid rules scoped to <= 782px.
* - 1.0.84 - Fix modal prev/next navigation + correct carton/stock wiring.
* - 1.0.83 - Version bump after verifying modal prev/next navigation wiring.
* - 1.0.82 - Fix modal prev/next navigation (visible-row order + correct enable/disable).
* - 1.0.81 - Fix product modal carton/stock wiring + prev/next navigation.
* - 1.0.80 - Goods-In product modal: fix prev/next navigation + carton/stock rendering.
* - 1.0.79 - Goods-In product modal: fix prev/next, carton, and stock data plumbing.
* - 1.0.78 - Goods-In product modal: close icon, carton/location/stock meta order, prev/next navigation.
* - 1.0.77 - Mobile product modal: header bar + scan-next stays in modal.
* - 1.0.76 - Mobile: add admin-bar-safe top offset for product modal.
* - 1.0.75 - Product modal matches mobile design reference (Step 1: layout + bindings).
* - 1.0.74 - Product modal matches mobile design; qty +/- updates row; scan opens modal.
* - Layout polish: tighter checkbox, 80x80 images (78x78 display), sortable columns, required notes columns.
 * - Remove "Add all" button; use keyed inputs to keep rows stable when sorting.
 * - Add unsaved changes warning for edited goods-in forms; column toggle dropdown; location column reposition/wrapping.
 * - Adjusted Location/SKU/Product widths and always-visible sort indicators.
 * - Confine horizontal scrolling to table container (prevent full-page scrollbar).
 * - Mobile: add product modal scaffold (tap-to-open) and scroll helper.
 * - 1.0.73 - Open product modal after scan input/camera (scan lock guard).
 * - 1.0.72 - Open product modal automatically after scans (camera/Bluetooth) with scan lock guard.
 * - 1.0.71 - Open product modal after scan (camera + scan input) with scan lock reset.
 * - 1.0.70 - Open product modal after scan (camera + scan input), with scan lock to avoid double triggers.
 * - 1.0.69 - Mobile: product modal scaffold open/close + row data attributes and tap-to-open.
 * - 1.0.68 - Mobile: add product modal open/close JS (tap row to open; populate from row dataset).
 * - 1.0.67 - Mobile: add product modal open/close JS (tap row to open; populate from row dataset).
 * - 1.0.66 - Mobile: add product modal scaffold (tap-to-open) with row dataset and modal wiring.
 * - 1.0.65 - Mobile: add product modal scaffold (tap-to-open).
 * - 1.0.64 - Mobile: add product modal scaffold (tap-to-open).
 * - 1.0.63 - Mobile: add product modal scaffold (tap-to-open).
 * - 1.0.05 - Hydrate Goods-In display fields with live WooCommerce data (preserve saved stock snapshot).
 * - 1.0.06 - Key Goods-In inputs by product_id (SKU display-only; disable inputs when product_id missing).
 * - 1.0.07 - Apply preorder-style tablecloth wrapper (sticky header + scroll container) to Goods-In list.
 * - 1.0.08 - Fix Goods-In right-side column widths (Ordered → Outstanding) in tablecloth layout.
 * - 1.0.09 - Set qty columns to 80px and rows to 80px height in Goods-In tablecloth layout.
 * - 1.0.10 - Tighten Goods-In image and column widths (narrow/reason/stocked/outstanding).
 * - 1.0.11 - Set Location/SKU/Product column widths and padding for Goods-In table.
 * - 1.0.12 - Enforce 80px rows; remove vertical padding; truncate long text cells with hover tooltips.
 * - 1.0.13 - Header checkbox padding + location/carton/notes column width updates.
 * - 1.0.14 - Header tick column sizing/padding adjustments.
 * - 1.0.15 - Resize header select-all checkbox to 16px.
 * - 1.0.16 - Adjust Goods-In check column to 16px with 10px side padding.
 * - 1.0.17 - Allow product link wrapping within fixed-height wrapper (rows remain 80px).
 * - 1.0.18 - Vertically center wrapped product link; clamp product/order notes to 4 lines with tooltips.
 * - 1.0.19 - Goods-In notes modal with 3-line preview; product link wrap stays centered.
 * - 1.0.20 - Goods-In notes preview styled as textbox (3-line clamp, modal click area).
 * - 1.0.21 - Set Goods-In notes modal to 700x342 and widen Location column to 56px.
 * - 1.0.22 - Align Goods-In notes modal to Pre-Order styling; auto-grow textarea (no modal scroll).
 * - 1.0.23 - Match Pre-Order notes modal UX (overlay position, close/X, product line, resizable textarea).
 * - 1.0.24 - Match Location column sizing/padding to Pre-Order sheet.
 * - 1.0.25 - Add Outstanding-only filter (auto-hide completed lines, toggle + counter).
 * - 1.0.26 - Add Goods-In search filter + Enter-to-jump (SKU/Product/Carton/Location).
 * - 1.0.27 - Widen search box to 250px; add Scan SKU jump/focus input.
 * - 1.0.28 - Hotfix parse error: ensure JS stays inside script; maintain search/scan features.
 * - 1.0.29 - Enter in qty inputs ticks row, updates, and returns focus to Scan.
 * - 1.0.30 - Add Carton filter (carton mode) stacking with search/completed filters.
 * - 1.0.31 - Add completed-only Goods-In Issues XLSX export button.
 * - 1.0.32 - Harden Goods-In Issues button gating (completed + has issues).
 * - 1.0.33 - Finalise Goods-In Issues XLSX export gating and data plumbing.
 * - 1.0.34 - Add Goods-In "Issues only" filter toggle (missing/reject > 0).
 * - 1.0.35 - Show dispute summary (missing/reject/credit totals with RMB FX) on completed goods-in.
 * - 1.0.36 - Fix Missing/Reject persistence (prefill + payload + handler key alignment).
 * - 1.0.37 - Optional Supplier SKUs column (per-supplier toggle).
 * - 1.0.38 - Fix Supplier SKUs column toggle scope in Goods-In UI.
 * - 1.0.39 - Fix Goods-In column widths when Supplier SKUs column is enabled (data-column width rules).
 * - 1.0.40 - Compact Supplier SKUs preview to a single line to prevent row height growth.
 * - 1.0.41 - Supplier SKUs two-line preview and product link opens in a new tab.
 * - 1.0.42 - Mobile tap-to-view modal for Supplier SKUs (keeps 2-line compact preview).
 * - 1.0.43 - Supplier SKUs modal shows product + SKU context.
 * - 1.0.44 - Improve Goods-In mobile responsiveness (toolbar/filter/table).
 * - 1.0.45 - Mobile: stack filters cleanly and force horizontal table scroll (no column squish).
 * - 1.0.46 - Mobile polish: force table horizontal scroll; stack filters with clear spacing.
 * - 1.0.47 - Mobile grid layout tightened (7-line layout).
 * - 1.0.48 - Refine mobile 7-line grid wrapper (header/actions/filters).
 * - 1.0.49 - Mobile grid enforces two-column rows for header/actions/filters.
 * - 1.0.50 - Mobile: search input height 40px; header select-all checkbox 25x15.
 * - 1.0.51 - Mobile: set search/carton/scan inputs to 40px height; header select-all checkbox 25px high.
 * - 1.0.52 - Mobile: add camera Scan button beside search (Code128 -> Scan SKU input).
 * - 1.0.53 - Mobile: wire camera scan modal (Code128) + rename bulk label button text.
 * - 1.0.54 - Mobile: ensure scan modal open/close handlers wired (ESC/backdrop/btn).
 * - 1.0.55 - Mobile scan: wait for video frames before detect; handle InvalidStateError gracefully.
 * - 1.0.56 - Mobile: add product modal scaffold (tap-to-open).
 * - 1.0.60 - Mobile: product modal scaffold wiring (row data + modal shell).
 * - 1.0.61 - Mobile: product modal open/close JS, row tap-to-open, and scroll helper.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'sop_goodsin_register_menu', 99 );
function sop_goodsin_register_menu() {
    add_submenu_page(
        'sop_stock_order_dashboard',
        __( 'Goods In', 'sop' ),
        __( 'Goods In', 'sop' ),
        function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce',
        'sop-goods-in',
        'sop_render_goods_in_page'
    );
}

/**
 * Get open goods-in sheets (locked/ordered, legacy receiving) with outstanding totals.
 *
 * @return array
 */
function sop_goodsin_get_open_sheets( $include_received = false ) {
    global $wpdb;

    $tbl_sheets = function_exists( 'sop_get_preorder_sheet_table_name' ) ? sop_get_preorder_sheet_table_name() : '';
    $tbl_lines  = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';

    if ( '' === $tbl_sheets ) {
        $tbl_sheets = $wpdb->prefix . 'sop_preorder_sheet';
    }
    if ( '' === $tbl_lines ) {
        $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
    }

    $statuses = array( 'locked', 'receiving' );
    if ( $include_received ) {
        $statuses[] = 'received';
    }
    $status_sql = "'" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "'";

    $sql = "SELECT
                s.id,
                s.supplier_id,
                s.status,
                s.title,
                s.order_number_label,
                s.updated_at,
                SUM(
                    CASE
                        WHEN l.qty_owner > 0 AND ( l.is_removed_owner = 0 OR l.is_removed_owner IS NULL )
                        THEN 1
                        ELSE 0
                    END
                ) AS total_lines,
                MAX(
                    CASE
                        WHEN (
                            l.goods_in_updated_at IS NOT NULL
                            OR COALESCE(l.goods_in_received_qty, 0) > 0
                            OR COALESCE(l.goods_in_missing_qty, 0) > 0
                            OR COALESCE(l.goods_in_reject_qty, 0) > 0
                            OR COALESCE(l.goods_in_stock_added_qty, 0) > 0
                        ) THEN 1
                        ELSE 0
                    END
                ) AS gi_started,
                SUM(
                    CASE
                        WHEN l.qty_owner > 0 AND ( l.is_removed_owner = 0 OR l.is_removed_owner IS NULL ) THEN
                            CASE
                                WHEN (
                                    l.qty_owner
                                    - COALESCE(l.goods_in_stock_added_qty, 0)
                                    - COALESCE(l.goods_in_missing_qty, 0)
                                    - COALESCE(l.goods_in_reject_qty, 0)
                                ) > 0
                                THEN (
                                    l.qty_owner
                                    - COALESCE(l.goods_in_stock_added_qty, 0)
                                    - COALESCE(l.goods_in_missing_qty, 0)
                                    - COALESCE(l.goods_in_reject_qty, 0)
                                )
                                ELSE 0
                            END
                        ELSE 0
                    END
                ) AS outstanding_qty,
                SUM(
                    CASE
                        WHEN (
                            l.qty_owner > 0
                            AND ( l.is_removed_owner = 0 OR l.is_removed_owner IS NULL )
                            AND (
                                l.qty_owner
                                - COALESCE(l.goods_in_stock_added_qty, 0)
                                - COALESCE(l.goods_in_missing_qty, 0)
                                - COALESCE(l.goods_in_reject_qty, 0)
                            ) > 0
                        )
                        THEN 1
                        ELSE 0
                    END
                ) AS outstanding_lines
            FROM {$tbl_sheets} s
            LEFT JOIN {$tbl_lines} l ON l.sheet_id = s.id
            WHERE s.status IN ( {$status_sql} )
            GROUP BY s.id
            ORDER BY s.updated_at DESC, s.id DESC";

    $rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return is_array( $rows ) ? $rows : array();
}

/**
 * Get goods-in lines for a sheet (filtered to qty_owner > 0 and not removed).
 *
 * @param int $sheet_id
 * @return array
 */
function sop_goodsin_get_sheet_lines_for_ui( $sheet_id ) {
    global $wpdb;

    $sheet_id = (int) $sheet_id;
    if ( $sheet_id <= 0 ) {
        return array();
    }

    $tbl_lines = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';
    if ( '' === $tbl_lines ) {
        $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
    }

    $select_carton = '';
    try {
        $carton_col = $wpdb->get_var( "SHOW COLUMNS FROM {$tbl_lines} LIKE 'carton_no'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( ! empty( $carton_col ) ) {
            $select_carton = ", l.carton_no";
        }
    } catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
    }

    $sql = "SELECT
                l.id AS line_id,
                l.sheet_id,
                l.product_id,
                l.sku_owner,
                l.qty_owner,
                l.image_id,
                l.goods_in_received_qty,
                l.goods_in_missing_qty,
                l.location,
                l.product_notes_owner,
                l.order_notes_owner,
                l.goods_in_reject_qty,
                l.goods_in_reject_reason,
                l.goods_in_notes,
                l.goods_in_stock_added_qty,
                p.post_title AS product_name{$select_carton}
            FROM {$tbl_lines} l
            LEFT JOIN {$wpdb->posts} p ON p.ID = l.product_id
            WHERE l.sheet_id = %d
              AND l.qty_owner > 0
              AND ( l.is_removed_owner = 0 OR l.is_removed_owner IS NULL )
            ORDER BY l.sort_index ASC, l.id ASC";

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $sheet_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = is_array( $rows ) ? $rows : array();

    // Hydrate display fields with live product data (do not alter saved stock snapshots).
    $supplier_id = 0;
    if ( function_exists( 'sop_goodsin_get_sheet' ) ) {
        $sheet = sop_goodsin_get_sheet( $sheet_id );
        if ( isset( $sheet['supplier_id'] ) ) {
            $supplier_id = (int) $sheet['supplier_id'];
        }
    }
    if ( function_exists( 'sop_hydrate_line_with_live_product_fields' ) ) {
        foreach ( $rows as $idx => $row ) {
            $rows[ $idx ] = sop_hydrate_line_with_live_product_fields( $row, $supplier_id );
        }
    }

    return $rows;
}

/**
 * Format supplier SKUs for compact, two-line display with tooltip and mobile modal support.
 *
 * Line 1: first SKU line.
 * Line 2: second SKU line (if only two) or "+N SKUs" when more than two.
 *
 * @param string $raw           Raw supplier SKUs (possibly multiline).
 * @param string $product_name  Product name for modal context.
 * @param string $product_sku   Product SKU for modal context.
 * @return string HTML span with compact display and full tooltip.
 */
function sop_goodsin_format_supplier_skus_compact_html( $raw, $product_name = '', $product_sku = '' ) {
    $lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
    $clean = array();
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( '' !== $line ) {
            $clean[] = $line;
        }
    }

    if ( empty( $clean ) ) {
        return '';
    }

    $count = count( $clean );
    $line1 = $clean[0];
    $line2 = '';
    if ( 2 === $count ) {
        $line2 = $clean[1];
    } elseif ( $count >= 3 ) {
        $line2 = '+' . ( $count - 1 ) . ' SKUs';
    }

    $title = implode( "\n", $clean );
    $html  = '<div class="sop-supplier-skus-compact" title="' . esc_attr( $title ) . '" data-full-skus="' . esc_attr( $title ) . '" data-product-name="' . esc_attr( $product_name ) . '" data-product-sku="' . esc_attr( $product_sku ) . '" role="button" tabindex="0" aria-label="' . esc_attr__( 'View supplier SKUs', 'sop' ) . '">';
    $html .= '<div class="sop-supplier-skus-line sop-supplier-skus-line1">' . esc_html( $line1 ) . '</div>';
    if ( '' !== $line2 ) {
        $html .= '<div class="sop-supplier-skus-line sop-supplier-skus-line2">' . esc_html( $line2 ) . '</div>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * Get buffer target units for a product using the forecast engine.
 *
 * @param int $product_id  Product ID.
 * @param int $supplier_id Supplier ID.
 * @return float|null Buffer target units or null if unavailable.
 */
function sop_goodsin_get_buffer_target_units( $product_id, $supplier_id ) {
    $product_id  = (int) $product_id;
    $supplier_id = (int) $supplier_id;
    if ( $product_id <= 0 || $supplier_id <= 0 ) {
        return null;
    }

    if ( ! function_exists( 'sop_core_engine' ) ) {
        return null;
    }

    $engine = sop_core_engine();
    if ( ! $engine || ! method_exists( $engine, 'get_supplier_settings' ) || ! method_exists( $engine, 'get_product_forecast' ) ) {
        return null;
    }

    $settings = $engine->get_supplier_settings( $supplier_id );
    $row      = $engine->get_product_forecast( $product_id, $settings );
    if ( ! is_array( $row ) ) {
        return null;
    }

    if ( isset( $row['buffer_target_units'] ) ) {
        return (float) $row['buffer_target_units'];
    }

    if ( isset( $row['buffer_days'] ) && isset( $row['demand_per_day'] ) ) {
        return (float) $row['buffer_days'] * (float) $row['demand_per_day'];
    }

    return null;
}

/**
 * Render a status pill for Goods-In list/header using 3-stage labels.
 *
 * @param string $status Raw status.
 * @param bool   $goods_in_started Whether goods-in activity exists.
 * @return string
 */
function sop_goodsin_render_stage_pill( $status, $goods_in_started = false ) {
    if ( function_exists( 'sop_preorder_render_status_pill' ) ) {
        return sop_preorder_render_status_pill( $status, $goods_in_started );
    }

    $stage_info = function_exists( 'sop_get_preorder_sheet_stage_info' )
        ? sop_get_preorder_sheet_stage_info( $status )
        : array(
            'stage_key'        => 'in_progress',
            'stage_label'      => __( 'In Progress', 'sop' ),
            'goods_in_started' => false,
        );

    $stage_key   = isset( $stage_info['stage_key'] ) ? (string) $stage_info['stage_key'] : 'in_progress';
    $label       = isset( $stage_info['stage_label'] ) ? (string) $stage_info['stage_label'] : __( 'In Progress', 'sop' );
    $gi_started  = ( ! empty( $stage_info['goods_in_started'] ) || $goods_in_started );
    $icon_class  = 'dashicons-unlock';
    if ( 'ordered' === $stage_key ) {
        $icon_class = 'dashicons-lock';
    } elseif ( 'completed' === $stage_key ) {
        $icon_class = 'dashicons-yes';
    }

    $class = 'sop-status-pill sop-status-' . sanitize_key( $stage_key );
    $html  = '<span class="' . esc_attr( $class ) . '">';
    $html .= '<span class="dashicons ' . esc_attr( $icon_class ) . '" aria-hidden="true"></span>';
    $html .= esc_html( $label );
    if ( $gi_started && 'ordered' === $stage_key ) {
        $html .= '<span class="sop-status-gi" title="' . esc_attr__( 'Goods-In started', 'sop' ) . '">' . esc_html__( 'GI started', 'sop' ) . '</span>';
    }
    $html .= '</span>';

    return $html;
}

function sop_render_goods_in_page() {
    if ( ! current_user_can( function_exists( 'sop_get_admin_capability' ) ? sop_get_admin_capability() : 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access Goods In.', 'sop' ) );
    }

    $sheet_id = isset( $_GET['sheet_id'] ) ? (int) $_GET['sheet_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $msg = isset( $_GET['sop_msg'] ) ? sanitize_key( wp_unslash( $_GET['sop_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    echo '<div class="wrap">';

    if ( $msg ) {
        $notice_class = 'notice notice-info';
        $text         = '';

        switch ( $msg ) {
            case 'saved':
                $notice_class = 'notice notice-success';
                $updated = isset( $_GET['sop_updated'] ) ? (int) $_GET['sop_updated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $text    = sprintf( __( 'Progress saved (%d lines updated).', 'sop' ), $updated );
                break;
            case 'applied':
                $notice_class = 'notice notice-success';
                $applied = isset( $_GET['sop_applied'] ) ? (int) $_GET['sop_applied'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $skipped = isset( $_GET['sop_skipped'] ) ? (int) $_GET['sop_skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $errors  = isset( $_GET['sop_errors'] ) ? (int) $_GET['sop_errors'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $errors > 0 ) {
                    $text = sprintf( __( 'Stock updated for %1$d lines (%2$d skipped, %3$d errors).', 'sop' ), $applied, $skipped, $errors );
                } else {
                    $text = sprintf( __( 'Stock updated for %1$d lines (%2$d skipped).', 'sop' ), $applied, $skipped );
                }
                break;
            case 'completed':
                $notice_class = 'notice notice-success';
                $text = __( 'Goods-In completed. Sheet marked Completed.', 'sop' );
                break;
            case 'cannot_complete':
                $notice_class = 'notice notice-error';
                $out = isset( $_GET['sop_outstanding_lines'] ) ? (int) $_GET['sop_outstanding_lines'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $text = sprintf( __( 'Cannot complete: %d lines still have outstanding quantity.', 'sop' ), $out );
                break;
            case 'confirm_complete_required':
                $notice_class = 'notice notice-error';
                $text = __( 'Please confirm completion before completing Goods-In.', 'sop' );
                break;
            case 'invalid_payload':
                $notice_class = 'notice notice-error';
                $text = __( 'Invalid payload; please reload and try again.', 'sop' );
                break;
            case 'sheet_not_found':
                $notice_class = 'notice notice-error';
                $text = __( 'Sheet not found.', 'sop' );
                break;
            case 'sheet_not_lockable':
                $notice_class = 'notice notice-error';
                $text = __( 'Sheet must be Ordered to use Goods-In.', 'sop' );
                break;
            case 'sheet_readonly':
                $notice_class = 'notice notice-info';
                $text = __( 'This sheet is Completed and is read-only.', 'sop' );
                break;
            case 'no_lines':
                $notice_class = 'notice notice-warning';
                $text = __( 'No lines found for this sheet.', 'sop' );
                break;
        }

        if ( $text ) {
            echo '<div class="' . esc_attr( $notice_class ) . '"><p>' . esc_html( $text ) . '</p></div>';
        }
    }

    if ( $sheet_id <= 0 ) {
        $show_completed = ( isset( $_GET['show_completed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['show_completed'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $sheets = sop_goodsin_get_open_sheets( $show_completed );

        echo '<h1>' . esc_html__( 'Goods In', 'sop' ) . '</h1>';
        if ( $show_completed ) {
            echo '<p>' . esc_html__( 'Select an Ordered or Completed sheet to view or receive stock against it.', 'sop' ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'Select an Ordered sheet to receive stock against it.', 'sop' ) . '</p>';
        }
        echo '<form method="get" action="" class="sop-goodsin-completed-filter">';
        echo '<input type="hidden" name="page" value="sop-goods-in" />';
        echo '<label><input type="checkbox" name="show_completed" value="1" ' . checked( $show_completed, true, false ) . ' /> ' . esc_html__( 'Show completed sheets', 'sop' ) . '</label> ';
        echo '<button type="submit" class="button">' . esc_html__( 'Apply', 'sop' ) . '</button>';
        echo '</form>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Sheet ID', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Supplier', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Title / Order', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Lines (ordered)', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Open lines', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Outstanding units', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'sop' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $sheets ) ) {
            if ( $show_completed ) {
                echo '<tr><td colspan="8">' . esc_html__( 'No Ordered/Completed sheets found.', 'sop' ) . '</td></tr>';
            } else {
                echo '<tr><td colspan="8">' . esc_html__( 'No Ordered sheets found.', 'sop' ) . '</td></tr>';
            }
        } else {
            foreach ( $sheets as $sheet ) {
                $sid = isset( $sheet['id'] ) ? (int) $sheet['id'] : 0;
                $supplier_id = isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0;
                $supplier_name = (string) $supplier_id;
                if ( function_exists( 'sop_supplier_get_by_id' ) ) {
                    $s = sop_supplier_get_by_id( $supplier_id );
                    if ( is_object( $s ) && isset( $s->name ) ) {
                        $supplier_name = (string) $s->name;
                    } elseif ( is_array( $s ) && isset( $s['name'] ) ) {
                        $supplier_name = (string) $s['name'];
                    }
                }

                $title = isset( $sheet['title'] ) ? (string) $sheet['title'] : '';
                $order_label = isset( $sheet['order_number_label'] ) ? (string) $sheet['order_number_label'] : '';
                $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
                $gi_started = ! empty( $sheet['gi_started'] );
                if ( 'receiving' === strtolower( $status ) ) {
                    $gi_started = true;
                }
                $lines = isset( $sheet['total_lines'] ) ? (int) $sheet['total_lines'] : 0;
                $open_lines = isset( $sheet['outstanding_lines'] ) ? (int) $sheet['outstanding_lines'] : 0;
                $outstanding = isset( $sheet['outstanding_qty'] ) ? (float) $sheet['outstanding_qty'] : 0.0;

                $open_args = array(
                    'page'     => 'sop-goods-in',
                    'sheet_id' => $sid,
                );
                if ( $show_completed ) {
                    $open_args['show_completed'] = '1';
                }
                $open_url = add_query_arg( $open_args, admin_url( 'admin.php' ) );

                echo '<tr>';
                echo '<td>' . esc_html( $sid ) . '</td>';
                echo '<td>' . esc_html( $supplier_name ) . '</td>';
                echo '<td>' . esc_html( trim( $title . ' ' . $order_label ) ) . '</td>';
                echo '<td>' . sop_goodsin_render_stage_pill( $status, $gi_started ) . '</td>';
                echo '<td>' . esc_html( $lines ) . '</td>';
                echo '<td>' . esc_html( $open_lines ) . '</td>';
                echo '<td>' . esc_html( number_format_i18n( (int) round( $outstanding ) ) ) . '</td>';
                echo '<td><a class="button" href="' . esc_url( $open_url ) . '">' . esc_html__( 'Open', 'sop' ) . '</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
        return;
    }

    $sheet = function_exists( 'sop_get_preorder_sheet' ) ? sop_get_preorder_sheet( $sheet_id ) : null;
    if ( ! is_array( $sheet ) ) {
        echo '<p>' . esc_html__( 'Sheet not found.', 'sop' ) . '</p></div>';
        return;
    }

    $supplier_id = isset( $sheet['supplier_id'] ) ? (int) $sheet['supplier_id'] : 0;
    $supplier_name = (string) $supplier_id;
    if ( function_exists( 'sop_supplier_get_by_id' ) ) {
        $s = sop_supplier_get_by_id( $supplier_id );
        if ( is_object( $s ) && isset( $s->name ) ) {
            $supplier_name = (string) $s->name;
        } elseif ( is_array( $s ) && isset( $s['name'] ) ) {
            $supplier_name = (string) $s['name'];
        }
    }

    $status = isset( $sheet['status'] ) ? (string) $sheet['status'] : '';
    $sop_gi_status = strtolower( $status );
    $sop_gi_is_readonly = in_array( $sop_gi_status, array( 'received', 'completed', 'complete', 'closed' ), true );

    $lines = sop_goodsin_get_sheet_lines_for_ui( $sheet_id );

    if ( empty( $lines ) ) {
        echo '<p>' . esc_html__( 'No orderable lines found for this sheet.', 'sop' ) . '</p></div>';
        return;
    }

    $form_action = admin_url( 'admin-post.php' );
    $show_supplier_skus_column = false;
    if ( $supplier_id > 0 && function_exists( 'sop_supplier_show_supplier_skus_column' ) ) {
        $show_supplier_skus_column = sop_supplier_show_supplier_skus_column( $supplier_id );
    }
    $has_issue_lines = false;
    $gi_started = false;
    foreach ( $lines as $line_check ) {
        $miss = isset( $line_check['goods_in_missing_qty_owner'] ) ? (float) $line_check['goods_in_missing_qty_owner'] : ( isset( $line_check['goods_in_missing_qty'] ) ? (float) $line_check['goods_in_missing_qty'] : ( isset( $line_check['missing_qty'] ) ? (float) $line_check['missing_qty'] : 0.0 ) );
        $rej  = isset( $line_check['goods_in_reject_qty_owner'] ) ? (float) $line_check['goods_in_reject_qty_owner'] : ( isset( $line_check['goods_in_reject_qty'] ) ? (float) $line_check['goods_in_reject_qty'] : ( isset( $line_check['reject_qty'] ) ? (float) $line_check['reject_qty'] : 0.0 ) );
        $rec  = isset( $line_check['goods_in_received_qty_owner'] ) ? (float) $line_check['goods_in_received_qty_owner'] : ( isset( $line_check['goods_in_received_qty'] ) ? (float) $line_check['goods_in_received_qty'] : ( isset( $line_check['received_qty'] ) ? (float) $line_check['received_qty'] : 0.0 ) );
        $stocked = isset( $line_check['goods_in_stock_added_qty'] ) ? (float) $line_check['goods_in_stock_added_qty'] : 0.0;
        if ( $miss > 0 || $rej > 0 ) {
            $has_issue_lines = true;
        }
        if ( $rec > 0 || $miss > 0 || $rej > 0 || $stocked > 0 ) {
            $gi_started = true;
        }
        if ( $has_issue_lines && $gi_started ) {
            break;
        }
    }
    if ( 'receiving' === $sop_gi_status ) {
        $gi_started = true;
    }
    $issue_summary = null;
    if ( function_exists( 'sop_goodsin_get_issues_summary_for_sheet' ) && function_exists( 'sop_get_preorder_sheet_lines' ) ) {
        $summary_lines = sop_get_preorder_sheet_lines( $sheet_id, true );
        if ( is_array( $summary_lines ) ) {
            $issue_summary = sop_goodsin_get_issues_summary_for_sheet( $sheet, $summary_lines );
            if ( isset( $issue_summary['issue_line_count'] ) && $issue_summary['issue_line_count'] > 0 ) {
                $has_issue_lines = true;
            }
        }
    }

    $columns_config = array(
        array(
            'key'     => 'image',
            'label'   => __( 'Image', 'sop' ),
            'visible' => true,
        ),
        array(
            'key'     => 'location',
            'label'   => __( 'Location', 'sop' ),
            'visible' => true,
        ),
        array(
            'key'     => 'sku',
            'label'   => __( 'SKU', 'sop' ),
            'visible' => true,
        ),
    );

    if ( $show_supplier_skus_column ) {
        $columns_config[] = array(
            'key'     => 'supplier_skus',
            'label'   => __( 'Supplier SKUs', 'sop' ),
            'visible' => true,
        );
    }

    $columns_config = array_merge(
        $columns_config,
        array(
            array(
                'key'     => 'product',
                'label'   => __( 'Product', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'ordered',
                'label'   => __( 'Ordered', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'received',
                'label'   => __( 'Received', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'missing',
                'label'   => __( 'Missing', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'reject',
                'label'   => __( 'Reject', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'reason',
                'label'   => __( 'Reason', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'carton',
                'label'   => __( 'Carton no.', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'product_notes',
                'label'   => __( 'Product notes', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'internal_product_notes',
                'label'   => __( 'Internal notes', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'order_notes',
                'label'   => __( 'Order notes', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'goodsin_notes',
                'label'   => __( 'Goods-In Notes', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'stocked',
                'label'   => __( 'Stocked', 'sop' ),
                'visible' => true,
            ),
            array(
                'key'     => 'outstanding',
                'label'   => __( 'Outstanding', 'sop' ),
                'visible' => true,
            ),
        )
    );

    $issue_export_url = '';
    if ( $has_issue_lines && in_array( $sop_gi_status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) {
        $issue_export_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action'       => 'sop_export_goodsin_issues_xlsx',
                    'sop_sheet_id' => $sheet_id,
                ),
                admin_url( 'admin-post.php' )
            ),
            'sop_export_goodsin_issues_xlsx',
            'sop_goodsin_export_nonce'
        );
    }

    $stage_info = function_exists( 'sop_get_preorder_sheet_stage_info' )
        ? sop_get_preorder_sheet_stage_info( $status )
        : array(
            'stage_key'   => 'in_progress',
            'stage_label' => __( 'In Progress', 'sop' ),
        );
    $stage_label = isset( $stage_info['stage_label'] ) ? (string) $stage_info['stage_label'] : __( 'In Progress', 'sop' );
    if ( 'ordered' === ( isset( $stage_info['stage_key'] ) ? (string) $stage_info['stage_key'] : '' ) && $gi_started ) {
        $stage_label .= ' (' . __( 'Goods-In started', 'sop' ) . ')';
    }

    if ( $sop_gi_is_readonly ) {
        echo '<div class="notice notice-info"><p>' . esc_html( sprintf( __( 'This sheet is %s and is read-only.', 'sop' ), $stage_label ) ) . '</p></div>';
    }

    $show_row_select = ! $sop_gi_is_readonly;
    $reason_labels = array(
        'wrong_spec'   => __( 'Wrong spec', 'sop' ),
        'wrong_colour' => __( 'Wrong colour', 'sop' ),
        'damaged'      => __( 'Damaged', 'sop' ),
        'other'        => __( 'Other', 'sop' ),
    );

    ?>
    <form id="sop-goodsin-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
        <?php wp_nonce_field( 'sop_goodsin_action', 'sop_goodsin_nonce' ); ?>
        <input type="hidden" name="action" value="sop_goodsin_save" id="sop-goodsin-action" />
        <input type="hidden" name="sheet_id" value="<?php echo (int) $sheet_id; ?>" />
        <input type="hidden" name="sop_goodsin_payload_json" id="sop-goodsin-payload-json" value="" />
        <input type="hidden" name="sop_goodsin_confirm_complete" id="sop-goodsin-confirm-complete" value="0" />

        <div class="sop-goodsin-mobile-grid">
            <div class="sop-goodsin-mg-title">
                <h1><?php esc_html_e( 'Goods In', 'sop' ); ?></h1>
            </div>
            <div class="sop-goodsin-mg-back">
                <?php
                $back_args = array( 'page' => 'sop-goods-in' );
                if ( isset( $_GET['show_completed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['show_completed'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $back_args['show_completed'] = '1';
                } elseif ( in_array( $sop_gi_status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) {
                    $back_args['show_completed'] = '1';
                }
                $back_url = add_query_arg( $back_args, admin_url( 'admin.php' ) );
                ?>
                <a class="button" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Back to list', 'sop' ); ?></a>
            </div>
            <div class="sop-goodsin-mg-sheet">
                <strong><?php echo esc_html( sprintf( __( 'Sheet #%1$d (%2$s)', 'sop' ), $sheet_id, $supplier_name ) ); ?></strong>
                <span class="sop-goodsin-sheet-status"><?php echo sop_goodsin_render_stage_pill( $status, $gi_started ); ?></span>
            </div>
            <?php if ( ! $sop_gi_is_readonly ) : ?>
                <div class="sop-goodsin-mg-save">
                    <button type="button" class="button button-primary sop-goodsin-submit" data-action="save"><?php esc_html_e( 'Save progress', 'sop' ); ?></button>
                </div>
                <div class="sop-goodsin-mg-add">
                    <button type="button" class="button sop-goodsin-submit" data-action="apply_stock"><?php esc_html_e( 'Apply selected stock', 'sop' ); ?></button>
                </div>
                <div class="sop-goodsin-mg-progress">
                    <div id="sop-goodsin-apply-progress" class="sop-goodsin-apply-progress" style="display:none;">
                        <div class="sop-goodsin-apply-progress-text"><?php esc_html_e( 'Applying stock: 0 of 0', 'sop' ); ?></div>
                        <div class="sop-goodsin-apply-progress-bar">
                            <div class="sop-goodsin-apply-progress-bar-inner"></div>
                        </div>
                        <div class="sop-goodsin-apply-progress-status" aria-live="polite"></div>
                        <div class="sop-goodsin-apply-progress-skipped"></div>
                    </div>
                </div>
                <div class="sop-goodsin-mg-complete">
                    <button type="button" class="button button-primary sop-goodsin-submit" data-action="complete"><?php esc_html_e( 'Complete Goods-In', 'sop' ); ?></button>
                </div>
            <?php else : ?>
                <div class="sop-goodsin-mg-save">
                    <span class="sop-goodsin-readonly-badge"><?php esc_html_e( 'View only', 'sop' ); ?></span>
                </div>
            <?php endif; ?>
            <div class="sop-goodsin-mg-columns">
                <div class="sop-goodsin-columns" id="sop-goodsin-columns">
                    <button type="button" class="button sop-goodsin-columns-toggle" aria-expanded="false" aria-controls="sop-goodsin-columns-popover"><?php esc_html_e( 'Columns', 'sop' ); ?></button>
                    <div class="sop-goodsin-columns-popover" id="sop-goodsin-columns-popover" aria-hidden="true">
                        <ul class="sop-goodsin-columns-list">
                            <?php foreach ( $columns_config as $col ) : ?>
                                <li>
                                    <label>
                                        <input type="checkbox" data-column="<?php echo esc_attr( $col['key'] ); ?>" <?php checked( ! empty( $col['visible'] ) ); ?> />
                                        <?php echo esc_html( $col['label'] ); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="sop-goodsin-mg-show">
                <label><input type="checkbox" id="sop-goodsin-show-completed" /> <?php esc_html_e( 'Show completed lines', 'sop' ); ?></label>
            </div>
            <div class="sop-goodsin-mg-issues">
                <label><input type="checkbox" id="sop-goodsin-issues-only" /> <?php esc_html_e( 'Issues only', 'sop' ); ?></label>
            </div>
            <div class="sop-goodsin-mg-carton">
                <div class="sop-goodsin-filter-row">
                    <input type="text" id="sop-goodsin-carton" placeholder="<?php esc_attr_e( 'Carton(s) (Enter)', 'sop' ); ?>" title="<?php esc_attr_e( 'Enter one or more cartons (e.g. 1550,1561 or 1550-1561).', 'sop' ); ?>" autocomplete="off" />
                    <button type="button" class="button-link" id="sop-goodsin-carton-clear"><?php esc_html_e( 'Clear', 'sop' ); ?></button>
                </div>
            </div>
            <?php if ( ! $sop_gi_is_readonly ) : ?>
                <div class="sop-goodsin-mg-scan">
                    <div class="sop-goodsin-filter-row">
                        <input type="text" id="sop-goodsin-scan" placeholder="<?php esc_attr_e( 'Scan SKU (Enter)', 'sop' ); ?>" autocomplete="off" />
                    </div>
                </div>
            <?php endif; ?>
            <div class="sop-goodsin-mg-search">
                <div class="sop-goodsin-mobile-searchrow">
                    <div class="sop-goodsin-filter-row">
                        <input type="text" id="sop-goodsin-search" placeholder="<?php esc_attr_e( 'Search SKU / Product / Carton / Location', 'sop' ); ?>" autocomplete="off" />
                        <button type="button" class="button-link" id="sop-goodsin-search-clear"><?php esc_html_e( 'Clear', 'sop' ); ?></button>
                    </div>
                    <?php if ( ! $sop_gi_is_readonly ) : ?>
                        <button type="button" class="button sop-goodsin-scan-btn" id="sop-goodsin-scan-btn"><?php esc_html_e( 'Scan', 'sop' ); ?></button>
                    <?php endif; ?>
                </div>
                <span id="sop-goodsin-filter-summary" aria-live="polite"></span>
            </div>
        </div>

        <?php if ( $issue_summary && in_array( $sop_gi_status, array( 'received', 'completed', 'complete', 'closed' ), true ) && isset( $issue_summary['issue_line_count'] ) && $issue_summary['issue_line_count'] > 0 ) : ?>
            <div class="notice notice-info sop-goodsin-dispute-summary">
                <p><strong><?php esc_html_e( 'Dispute summary', 'sop' ); ?></strong></p>
                <ul>
                    <li><?php printf( esc_html__( 'Issue lines: %d', 'sop' ), (int) $issue_summary['issue_line_count'] ); ?></li>
                    <li><?php printf( esc_html__( 'Missing units: %d', 'sop' ), (int) $issue_summary['total_missing'] ); ?></li>
                    <li><?php printf( esc_html__( 'Reject units: %d', 'sop' ), (int) $issue_summary['total_reject'] ); ?></li>
                    <li><?php printf( esc_html__( 'Credit qty: %d', 'sop' ), (int) $issue_summary['total_credit_qty'] ); ?></li>
                    <li><?php printf( esc_html__( 'Credit total (%1$s): %2$s', 'sop' ), esc_html( $issue_summary['supplier_currency'] ), esc_html( number_format_i18n( (float) $issue_summary['total_credit_total_supplier'], 2 ) ) ); ?></li>
                    <?php if ( ! empty( $issue_summary['is_rmb'] ) && ! empty( $issue_summary['fx_rmb_per_usd'] ) && ! empty( $issue_summary['total_credit_total_usd'] ) ) : ?>
                        <li><?php printf( esc_html__( 'Credit total (USD): %s', 'sop' ), esc_html( number_format_i18n( (float) $issue_summary['total_credit_total_usd'], 2 ) ) ); ?></li>
                        <li><?php printf( esc_html__( 'FX used (RMB/USD): %s', 'sop' ), esc_html( number_format_i18n( (float) $issue_summary['fx_rmb_per_usd'], 3 ) ) ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="sop-preorder-table-wrapper" aria-label="Goods-In table scroll">
        <table class="wp-list-table widefat fixed striped sop-preorder-table sop-goodsin-table" id="sop-goodsin-lines">
            <thead>
            <tr>
                <?php if ( $show_row_select ) : ?>
                    <th class="check-column" data-sortable="false"><input type="checkbox" id="sop-goodsin-select-all" /></th>
                <?php endif; ?>
                <th class="sop-goodsin-col-image" data-sortable="false" data-column="image"><?php esc_html_e( 'Image', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-location column-location" data-sort-key="location" data-sort-type="text" data-column="location"><?php esc_html_e( 'Location', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-sku" data-sort-key="sku" data-sort-type="text" data-column="sku"><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                <?php if ( $show_supplier_skus_column ) : ?>
                    <th class="sop-goodsin-sort sop-goodsin-col-supplier-skus" data-sort-key="supplier_skus" data-sort-type="text" data-column="supplier_skus"><?php esc_html_e( 'Supplier SKUs', 'sop' ); ?></th>
                <?php endif; ?>
                <th class="sop-goodsin-sort sop-goodsin-col-product" data-sort-key="product" data-sort-type="text" data-column="product"><?php esc_html_e( 'Product', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="ordered" data-sort-type="number" data-column="ordered"><?php esc_html_e( 'Ordered', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-narrow" data-sort-key="received" data-sort-type="number" data-column="received"><?php esc_html_e( 'Received', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-narrow" data-sort-key="missing" data-sort-type="number" data-column="missing"><?php esc_html_e( 'Missing', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-narrow" data-sort-key="reject" data-sort-type="number" data-column="reject"><?php esc_html_e( 'Reject', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="reason" data-sort-type="text" data-column="reason"><?php esc_html_e( 'Reason', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="carton" data-sort-type="text" data-column="carton"><?php esc_html_e( 'Carton no.', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="product_notes" data-sort-type="text" data-column="product_notes"><?php esc_html_e( 'Product notes', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="internal_product_notes" data-sort-type="text" data-column="internal_product_notes"><?php esc_html_e( 'Internal notes', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="order_notes" data-sort-type="text" data-column="order_notes"><?php esc_html_e( 'Order notes', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="goodsin_notes" data-sort-type="text" data-column="goodsin_notes"><?php esc_html_e( 'Goods-In Notes', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="stocked" data-sort-type="number" data-column="stocked"><?php esc_html_e( 'Stocked', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="outstanding" data-sort-type="number" data-column="outstanding"><?php esc_html_e( 'Outstanding', 'sop' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $lines as $line ) :
                $line_id  = isset( $line['line_id'] ) ? (int) $line['line_id'] : 0;
                $pid      = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
                $sku      = isset( $line['sku_owner'] ) ? (string) $line['sku_owner'] : '';
                if ( $pid <= 0 && '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
                    $resolved_pid = wc_get_product_id_by_sku( $sku );
                    if ( $resolved_pid > 0 ) {
                        $pid = (int) $resolved_pid;
                    }
                }
                $inputs_disabled_attr = '';
                $missing_pid_warning  = '';
                if ( $pid <= 0 ) {
                    $inputs_disabled_attr = ' disabled="disabled"';
                    $missing_pid_warning  = ' (' . esc_html__( 'missing product_id', 'sop' ) . ')';
                }
                if ( $sop_gi_is_readonly ) {
                    $inputs_disabled_attr = ' disabled="disabled"';
                }
                $name     = isset( $line['product_name'] ) ? (string) $line['product_name'] : '';
                $location = isset( $line['location'] ) ? (string) $line['location'] : '';
                $ordered  = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : 0.0;
                $received = isset( $line['goods_in_received_qty'] ) ? (float) $line['goods_in_received_qty'] : 0.0;
                $missing  = isset( $line['goods_in_missing_qty'] ) ? (float) $line['goods_in_missing_qty'] : ( isset( $line['missing_qty'] ) ? (float) $line['missing_qty'] : 0.0 );
                $reject   = isset( $line['goods_in_reject_qty'] ) ? (float) $line['goods_in_reject_qty'] : ( isset( $line['reject_qty'] ) ? (float) $line['reject_qty'] : 0.0 );
                $reason   = isset( $line['goods_in_reject_reason'] ) ? (string) $line['goods_in_reject_reason'] : '';
                $notes    = isset( $line['goods_in_notes'] ) ? (string) $line['goods_in_notes'] : '';
                $stocked  = isset( $line['goods_in_stock_added_qty'] ) ? (float) $line['goods_in_stock_added_qty'] : 0.0;
                $product_notes = isset( $line['product_notes_owner'] ) ? (string) $line['product_notes_owner'] : '';
                $order_notes   = isset( $line['order_notes_owner'] ) ? (string) $line['order_notes_owner'] : '';
                $internal_notes = '';
                if ( $pid > 0 ) {
                    $internal_notes = get_post_meta( $pid, '_sop_internal_product_notes', true );
                    $internal_notes = is_string( $internal_notes ) ? $internal_notes : '';
                }
                $outstanding = max( 0.0, $ordered - $stocked - $missing - $reject );
                $is_complete = ( $outstanding <= 0.0001 );
                $supplier_skus_val = '';
                if ( $pid > 0 ) {
                    $supplier_skus_val = get_post_meta( $pid, '_sop_supplier_skus', true );
                    $supplier_skus_val = is_string( $supplier_skus_val ) ? $supplier_skus_val : '';
                }

                $product      = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
                $image_html   = '';
                $image_url    = '';
                if ( $product && method_exists( $product, 'get_image_id' ) ) {
                    $img_id = $product->get_image_id();
                    if ( $img_id ) {
                        $image_html = wp_get_attachment_image( $img_id, array( 100, 100 ), false, array( 'class' => 'sop-goodsin-img' ) );
                        $src        = wp_get_attachment_image_src( $img_id, 'full' );
                        if ( $src && isset( $src[0] ) ) {
                            $image_url = $src[0];
                        }
                    }
                }
                if ( '' === $image_html && ! empty( $line['image_id'] ) ) {
                    $image_html = wp_get_attachment_image( (int) $line['image_id'], array( 100, 100 ), false, array( 'class' => 'sop-goodsin-img' ) );
                    $src        = wp_get_attachment_image_src( (int) $line['image_id'], 'full' );
                    if ( $src && isset( $src[0] ) ) {
                        $image_url = $src[0];
                    }
                }
                if ( '' === $image_html ) {
                    $placeholder = function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '';
                    if ( $placeholder ) {
                        $image_html = '<img class="sop-goodsin-img" src="' . esc_url( $placeholder ) . '" alt="" />';
                        $image_url  = $placeholder;
                    }
                }
                $product_link = $pid > 0 ? get_edit_post_link( $pid, '' ) : '';
                $carton = isset( $line['carton_number'] ) ? (string) $line['carton_number'] : '';
                if ( isset( $line['carton_no'] ) && '' !== (string) $line['carton_no'] ) {
                    $carton = (string) $line['carton_no'];
                }
                $carton_segments = array();
                if ( '' !== trim( $carton ) ) {
                    $carton_segments = array_filter( array_map( 'trim', explode( ',', $carton ) ), 'strlen' );
                }
                if ( ! empty( $carton_segments ) ) {
                    $carton_display = implode( '<br />', array_map( 'esc_html', $carton_segments ) );
                } else {
                    $carton_display = '&mdash;';
                }
                $carton_sort_key = 999999999;
                if ( preg_match( '/\d+/', $carton, $carton_match ) ) {
                    $carton_sort_key = (int) $carton_match[0];
                }
                $stock_qty = null;
                $stock_keys = array( 'current_stock', 'stock_qty', 'stock_snapshot', 'stock_on_hand', 'stock_at_save', 'saved_stock' );
                foreach ( $stock_keys as $stock_key ) {
                    if ( isset( $line[ $stock_key ] ) && '' !== $line[ $stock_key ] && null !== $line[ $stock_key ] ) {
                        $stock_qty = (int) $line[ $stock_key ];
                        break;
                    }
                }
                if ( null === $stock_qty && $pid > 0 && function_exists( 'wc_get_product' ) ) {
                    $product = wc_get_product( $pid );
                    if ( $product ) {
                        $stock_raw = $product->get_stock_quantity();
                        if ( null !== $stock_raw && '' !== $stock_raw ) {
                            $stock_qty = (int) $stock_raw;
                        }
                    }
                }
                if ( null === $stock_qty ) {
                    $stock_qty = '';
                }
                $buffer_target_units = '';
                if ( $pid > 0 && $supplier_id > 0 ) {
                    $buffer_target = sop_goodsin_get_buffer_target_units( $pid, $supplier_id );
                    if ( null !== $buffer_target ) {
                        $buffer_target_units = (string) $buffer_target;
                    }
                }
                                ?>
                                <tr data-line-id="<?php echo esc_attr( $line_id ); ?>" data-product-id="<?php echo esc_attr( $pid ); ?>" data-sop-row="1"
                                    class="<?php echo $is_complete ? 'sop-goodsin-line-complete' : ''; ?>"
                                    data-sku="<?php echo esc_attr( trim( $sku ) ); ?>"
                                    data-product-name="<?php echo esc_attr( $name ); ?>"
                                    data-location="<?php echo esc_attr( $location ); ?>"
                                    data-carton="<?php echo esc_attr( $carton ); ?>"
                                    data-stock-qty="<?php echo esc_attr( $stock_qty ); ?>"
                                    data-buffer-target="<?php echo esc_attr( $buffer_target_units ); ?>"
                                    data-ordered="<?php echo esc_attr( $ordered ); ?>"
                                    data-edit-url="<?php echo esc_url( $product_link ); ?>"
                                    data-image-url="<?php echo esc_url( $image_url ); ?>"
                    data-has-product-notes="<?php echo $product_notes ? '1' : '0'; ?>"
                    data-has-order-notes="<?php echo $order_notes ? '1' : '0'; ?>"
                    data-sort-sku="<?php echo esc_attr( mb_strtolower( $sku ) ); ?>"
                    data-sort-product="<?php echo esc_attr( mb_strtolower( $name ) ); ?>"
                    data-sort-location="<?php echo esc_attr( mb_strtolower( $location ) ); ?>"
                    data-sort-ordered="<?php echo esc_attr( $ordered ); ?>"
                    data-sop-ordered="<?php echo esc_attr( $ordered ); ?>"
                    data-sort-received="<?php echo esc_attr( $received ); ?>"
                    data-sort-missing="<?php echo esc_attr( $missing ); ?>"
                    data-sort-reject="<?php echo esc_attr( $reject ); ?>"
                    data-sort-reason="<?php echo esc_attr( mb_strtolower( $reason ) ); ?>"
                    data-sort-carton="<?php echo esc_attr( mb_strtolower( $carton ) ); ?>"
                    data-sort-product_notes="<?php echo esc_attr( mb_strtolower( wp_strip_all_tags( $product_notes ) ) ); ?>"
                    data-sort-internal_product_notes="<?php echo esc_attr( mb_strtolower( wp_strip_all_tags( $internal_notes ) ) ); ?>"
                    data-sort-order_notes="<?php echo esc_attr( mb_strtolower( wp_strip_all_tags( $order_notes ) ) ); ?>"
                    data-sort-goodsin_notes="<?php echo esc_attr( mb_strtolower( wp_strip_all_tags( $notes ) ) ); ?>"
                    data-sort-stocked="<?php echo esc_attr( $stocked ); ?>"
                    data-sort-outstanding="<?php echo esc_attr( $outstanding ); ?>">
                    <?php if ( $show_row_select ) : ?>
                        <td class="check-column">
                            <input type="checkbox" class="sop-goodsin-select" name="selected_lines[<?php echo esc_attr( $line_id ); ?>]" value="1" />
                        </td>
                    <?php endif; ?>
                    <td class="sop-goodsin-col-image" data-column="image"><div class="sop-goodsin-img-wrap"><?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></td>
                    <td class="sop-goodsin-col-location column-location" data-column="location"><?php echo esc_html( $location ); ?></td>
                    <td class="sop-goodsin-col-sku" data-column="sku"><?php echo esc_html( $sku . $missing_pid_warning ); ?></td>
                    <?php if ( $show_supplier_skus_column ) : ?>
                        <?php
                        $supplier_skus_lines = array();
                        if ( '' !== $supplier_skus_val ) {
                            $supplier_skus_lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $supplier_skus_val ) ), 'strlen' );
                        }
                        $supplier_skus_sort = trim( implode( ' | ', $supplier_skus_lines ) );
                        $supplier_skus_compact_html = sop_goodsin_format_supplier_skus_compact_html( $supplier_skus_val, $name, $sku );
                        ?>
                        <td class="sop-goodsin-col-supplier-skus" data-column="supplier_skus" data-sort-key="supplier_skus" data-sort-value="<?php echo esc_attr( $supplier_skus_sort ); ?>" data-sort-text="<?php echo esc_attr( $supplier_skus_sort ); ?>">
                            <?php
                            if ( '' !== $supplier_skus_compact_html ) {
                                echo wp_kses_post( $supplier_skus_compact_html );
                            } else {
                                echo '&ndash;';
                            }
                            ?>
                        </td>
                    <?php endif; ?>
                    <td class="sop-goodsin-col-product" data-column="product" title="<?php echo esc_attr( wp_strip_all_tags( $name ) ); ?>">
                        <div class="sop-goodsin-product-wrap">
                            <?php
                            if ( $product_link ) {
                                echo '<a class="sop-goodsin-product-link" href="' . esc_url( $product_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            } else {
                                echo '<span class="sop-goodsin-product-link">' . esc_html( $name ) . '</span>';
                            }
                            ?>
                        </div>
                    </td>
                    <td data-column="ordered">
                        <?php echo esc_html( number_format_i18n( $ordered, 0 ) ); ?>
                        <span class="sop-goodsin-stockdone" aria-hidden="true"></span>
                        <span class="sop-goodsin-skip-badge" aria-hidden="true"></span>
                    </td>
                    <td data-column="received">
                        <?php if ( $sop_gi_is_readonly ) : ?>
                            <span class="sop-goodsin-readonly-val"><?php echo esc_html( number_format_i18n( $received, 0 ) ); ?></span>
                        <?php else : ?>
                            <input type="number" class="sop-goodsin-received sop-goodsin-narrow" step="1" min="0" value="<?php echo esc_attr( $received ); ?>" name="received_qty[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?> />
                        <?php endif; ?>
                    </td>
                    <td data-column="missing">
                        <?php if ( $sop_gi_is_readonly ) : ?>
                            <span class="sop-goodsin-readonly-val"><?php echo esc_html( number_format_i18n( $missing, 0 ) ); ?></span>
                        <?php else : ?>
                            <input type="number" class="sop-goodsin-missing sop-goodsin-narrow" step="1" min="0" value="<?php echo esc_attr( $missing ); ?>" name="missing_qty[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?> />
                        <?php endif; ?>
                    </td>
                    <td data-column="reject">
                        <?php if ( $sop_gi_is_readonly ) : ?>
                            <span class="sop-goodsin-readonly-val"><?php echo esc_html( number_format_i18n( $reject, 0 ) ); ?></span>
                        <?php else : ?>
                            <input type="number" class="sop-goodsin-reject sop-goodsin-narrow" step="1" min="0" value="<?php echo esc_attr( $reject ); ?>" name="reject_qty[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?> />
                        <?php endif; ?>
                    </td>
                    <td data-column="reason">
                        <?php if ( $sop_gi_is_readonly ) : ?>
                            <?php
                            $reason_text = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : '';
                            if ( '' === $reason_text ) {
                                $reason_text = '—';
                            }
                            ?>
                            <span class="sop-goodsin-readonly-val"><?php echo esc_html( $reason_text ); ?></span>
                        <?php else : ?>
                            <select class="sop-goodsin-reject-reason" name="reject_reason[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?>>
                                <option value=""><?php esc_html_e( '—', 'sop' ); ?></option>
                                <option value="wrong_spec" <?php selected( $reason, 'wrong_spec' ); ?>><?php esc_html_e( 'Wrong spec', 'sop' ); ?></option>
                                <option value="wrong_colour" <?php selected( $reason, 'wrong_colour' ); ?>><?php esc_html_e( 'Wrong colour', 'sop' ); ?></option>
                                <option value="damaged" <?php selected( $reason, 'damaged' ); ?>><?php esc_html_e( 'Damaged', 'sop' ); ?></option>
                                <option value="other" <?php selected( $reason, 'other' ); ?>><?php esc_html_e( 'Other', 'sop' ); ?></option>
                            </select>
                        <?php endif; ?>
                    </td>
                    <td class="sop-goodsin-carton sop-goodsin-cell-truncate" data-column="carton" data-carton-sort="<?php echo esc_attr( $carton_sort_key ); ?>" title="<?php echo esc_attr( $carton ); ?>">
                        <div class="sop-goodsin-carton-text"><?php echo wp_kses_post( $carton_display ); ?></div>
                    </td>
                    <td class="sop-goodsin-text-col" data-column="product_notes">
                        <div class="sop-goodsin-notes-wrap">
                            <div class="sop-notes-preview" data-sop-notes-title="<?php esc_attr_e( 'Product notes', 'sop' ); ?>">
                                <div class="sop-notes-html">
                                <?php
                                $product_notes_html = function_exists( 'sop_notes_sanitize_html' )
                                    ? sop_notes_sanitize_html( $product_notes )
                                    : wp_kses_post( $product_notes );
                                echo $product_notes_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="sop-goodsin-text-col" data-column="internal_product_notes">
                        <div class="sop-goodsin-notes-wrap">
                            <div class="sop-notes-preview" data-sop-notes-title="<?php esc_attr_e( 'Internal notes', 'sop' ); ?>">
                                <div class="sop-notes-html">
                                <?php
                                $internal_notes_html = function_exists( 'sop_notes_sanitize_html' )
                                    ? sop_notes_sanitize_html( $internal_notes )
                                    : wp_kses_post( $internal_notes );
                                echo $internal_notes_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="sop-goodsin-text-col" data-column="order_notes" title="<?php echo esc_attr( $order_notes ); ?>">
                        <div class="sop-goodsin-notes-wrap">
                            <div class="sop-goodsin-notes-text"><?php echo esc_html( $order_notes ); ?></div>
                        </div>
                    </td>
                    <td class="sop-goodsin-col-goodsin-notes" data-column="goodsin_notes">
                        <div class="sop-goodsin-notes-cell"<?php echo $inputs_disabled_attr ? ' aria-disabled="true"' : ''; ?>>
                            <div class="sop-goodsin-notes-preview-box" title="<?php echo esc_attr( $notes ); ?>">
                                <div class="sop-goodsin-notes-preview sop-goodsin-clamp-3">
                                    <?php echo esc_html( $notes ); ?>
                                </div>
                                <?php if ( '' === $inputs_disabled_attr ) : ?>
                                    <button type="button" class="button-link sop-goodsin-notes-edit" aria-label="<?php esc_attr_e( 'Edit goods-in notes', 'sop' ); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <textarea class="sop-goodsin-notes" name="goods_in_notes[<?php echo esc_attr( $pid ); ?>]" style="display:none;"<?php echo $inputs_disabled_attr; ?>><?php echo esc_textarea( $notes ); ?></textarea>
                        </div>
                    </td>
                    <td data-column="stocked"><?php echo esc_html( number_format_i18n( $stocked, 0 ) ); ?></td>
                    <td data-column="outstanding"><?php echo esc_html( number_format_i18n( $outstanding, 0 ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="sop-goodsin-notes-modal-backdrop" id="sop-goodsin-notes-modal-backdrop" aria-hidden="true"></div>
        <div class="sop-goodsin-notes-modal" id="sop-goodsin-notes-modal" role="dialog" aria-modal="true" aria-labelledby="sop-goodsin-notes-title" aria-hidden="true">
            <button type="button"
                    class="button-link sop-goodsin-notes-close"
                    id="sop-goodsin-notes-close"
                    aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">
                &times;
            </button>
            <h3 id="sop-goodsin-notes-title"><?php esc_html_e( 'Goods-In notes', 'sop' ); ?></h3>
            <p class="sop-goodsin-notes-product" id="sop-goodsin-notes-product"></p>
            <div class="sop-goodsin-notes-modal-body">
                <textarea id="sop-goodsin-notes-editor"></textarea>
            </div>
            <div class="sop-goodsin-notes-modal-actions">
                <button type="button" class="button button-primary" id="sop-goodsin-notes-save"><?php esc_html_e( 'Save notes', 'sop' ); ?></button>
            </div>
        </div>

        <div class="sop-goodsin-info-modal-backdrop" id="sop-goodsin-info-modal-backdrop" aria-hidden="true"></div>
        <div class="sop-goodsin-info-modal" id="sop-goodsin-info-modal" role="dialog" aria-modal="true" aria-labelledby="sop-goodsin-info-modal-title" aria-hidden="true">
            <button type="button"
                    class="button-link sop-goodsin-info-close"
                    data-sop-info-close="1"
                    aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">
                &times;
            </button>
            <h3 id="sop-goodsin-info-modal-title"><?php esc_html_e( 'Product notes', 'sop' ); ?></h3>
            <div class="sop-goodsin-info-modal-body" id="sop-goodsin-info-modal-body"></div>
        </div>

        <div class="sop-goodsin-complete-modal-backdrop" id="sop-goodsin-complete-modal-backdrop" aria-hidden="true"></div>
        <div class="sop-goodsin-complete-modal" id="sop-goodsin-complete-modal" role="dialog" aria-modal="true" aria-labelledby="sop-goodsin-complete-modal-title" aria-hidden="true">
            <button type="button"
                    class="button-link sop-goodsin-info-close"
                    data-sop-complete-close="1"
                    aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">
                &times;
            </button>
            <h3 id="sop-goodsin-complete-modal-title"><?php esc_html_e( 'Complete Goods-In?', 'sop' ); ?></h3>
            <p><?php esc_html_e( 'This will mark the sheet as completed/read-only. It does not apply stock. Ensure stock has already been applied.', 'sop' ); ?></p>
            <p>
                <label for="sop-goodsin-complete-confirm-check">
                    <input type="checkbox" id="sop-goodsin-complete-confirm-check" />
                    <?php esc_html_e( 'I confirm this Goods-In is ready to be completed.', 'sop' ); ?>
                </label>
            </p>
            <div class="sop-goodsin-complete-actions">
                <button type="button" class="button button-primary" id="sop-goodsin-complete-confirm" disabled><?php esc_html_e( 'Confirm complete', 'sop' ); ?></button>
                <button type="button" class="button" data-sop-complete-close="1"><?php esc_html_e( 'Cancel', 'sop' ); ?></button>
            </div>
        </div>

        <?php if ( 'report' === $view || in_array( $sop_gi_status, array( 'received', 'completed', 'complete', 'closed' ), true ) ) : ?>
            <div class="sop-goodsin-report-header">
                <h3><?php esc_html_e( 'Goods-In Report (Issues)', 'sop' ); ?></h3>
                <?php if ( $issue_export_url ) : ?>
                    <a class="button button-secondary" href="<?php echo esc_url( $issue_export_url ); ?>">
                        <?php esc_html_e( 'Download Issues XLSX', 'sop' ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Product', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Missing', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Rejected', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Reason', 'sop' ); ?></th>
                    <th><?php esc_html_e( 'Notes', 'sop' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $issue_rows = 0;
                foreach ( $lines as $line ) {
                    $missing = isset( $line['goods_in_missing_qty'] ) ? (float) $line['goods_in_missing_qty'] : 0.0;
                    $reject  = isset( $line['goods_in_reject_qty'] ) ? (float) $line['goods_in_reject_qty'] : 0.0;
                    $notes   = isset( $line['goods_in_notes'] ) ? (string) $line['goods_in_notes'] : '';
                    if ( $missing <= 0 && $reject <= 0 && '' === trim( $notes ) ) {
                        continue;
                    }
                    $issue_rows++;
                    $sku   = isset( $line['sku_owner'] ) ? (string) $line['sku_owner'] : '';
                    $name  = isset( $line['product_name'] ) ? (string) $line['product_name'] : '';
                    $reason = isset( $line['goods_in_reject_reason'] ) ? (string) $line['goods_in_reject_reason'] : '';
                    echo '<tr>';
                    echo '<td>' . esc_html( $sku ) . '</td>';
                    echo '<td>' . esc_html( $name ) . '</td>';
                    echo '<td>' . esc_html( number_format_i18n( $missing, 0 ) ) . '</td>';
                    echo '<td>' . esc_html( number_format_i18n( $reject, 0 ) ) . '</td>';
                    echo '<td>' . esc_html( $reason ) . '</td>';
                    echo '<td>' . esc_html( $notes ) . '</td>';
                    echo '</tr>';
                }
                if ( 0 === $issue_rows ) {
                    echo '<tr><td colspan="6">' . esc_html__( 'No issues recorded.', 'sop' ) . '</td></tr>';
                }
                ?>
                </tbody>
            </table>
        <?php endif; ?>
    </form>

    <style>
        #sop-goodsin-lines th,
        #sop-goodsin-lines td {
            vertical-align: middle;
        }
        .sop-goodsin-apply-progress {
            margin-top: 6px;
            max-width: 360px;
        }
        .sop-goodsin-apply-progress-text {
            font-size: 12px;
            margin-bottom: 6px;
            color: #1d2327;
        }
        .sop-goodsin-apply-progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e4e7;
            border-radius: 999px;
            overflow: hidden;
        }
        .sop-goodsin-apply-progress-bar-inner {
            width: 0%;
            height: 100%;
            background: #2271b1;
            transition: width 0.2s ease-in-out;
        }
        .sop-goodsin-apply-progress-status {
            margin-top: 6px;
            font-size: 12px;
            color: #3c434a;
        }
        .sop-goodsin-row-applied {
            background-color: #e8f7ed;
        }
        .sop-goodsin-row-skipped {
            background-color: #fff4e5;
        }
        .sop-goodsin-row-error {
            background-color: #fde8e8;
        }
        .sop-goodsin-skip-badge {
            display: none;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            margin-left: 6px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            background: #f0b429;
            color: #1d2327;
            vertical-align: middle;
        }
        .sop-goodsin-skip-badge::before {
            content: "!";
        }
        .sop-goodsin-row-skipped .sop-goodsin-skip-badge {
            display: inline-flex;
        }
        .sop-goodsin-stockdone {
            display: none;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            margin-left: 6px;
            border-radius: 50%;
            background: #2e8b57;
            color: #fff;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            vertical-align: middle;
        }
        .sop-goodsin-stockdone::before {
            content: "✓";
        }
        tr.sop-goodsin-line-complete .sop-goodsin-stockdone {
            display: inline-flex;
        }
        .sop-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
            line-height: 1;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: #f6f7f7;
            color: #1d2327;
        }
        .sop-status-in-progress {
            background: #f0f0f1;
            border-color: #dcdcde;
        }
        .sop-status-ordered {
            background: #e9f2ff;
            border-color: #c6dbff;
            color: #1d4ed8;
        }
        .sop-status-completed {
            background: #e8f7ed;
            border-color: #bfe6cc;
            color: #116329;
        }
        .sop-status-pill .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        .sop-status-gi {
            margin-left: 6px;
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #fff3cd;
            color: #8a5a00;
            border: 1px solid #f3d48c;
        }
        .sop-goodsin-sheet-status {
            margin-left: 8px;
            vertical-align: middle;
        }
        #sop-goodsin-lines .check-column {
            width: 16px;
            padding: 0 10px;
        }
        .sop-goodsin-img-wrap {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #sop-goodsin-lines tbody td {
            height: 80px;
        }
        .sop-goodsin-img {
            width: 78px;
            height: 78px;
            max-width: 78px;
            max-height: 78px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .sop-goodsin-col-narrow {
            width: 60px;
            min-width: 60px;
            max-width: 60px;
            white-space: nowrap;
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
        /* Goods-In specific table width + header behaviour */
        .sop-goodsin-table thead th {
            white-space: nowrap;
            word-break: normal;
            overflow-wrap: normal;
        }
        .sop-goodsin-table .sop-goodsin-col-image {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
            padding: 0 !important;
            text-align: center;
            vertical-align: middle;
        }
        .sop-goodsin-img {
            width: 78px;
            height: 78px;
            max-width: 78px;
            max-height: 78px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        /* Ordered / Received / Missing / Reject (narrow numeric columns) */
        .sop-goodsin-table th[data-column="ordered"],
        .sop-goodsin-table td[data-column="ordered"],
        .sop-goodsin-table th[data-column="received"],
        .sop-goodsin-table td[data-column="received"],
        .sop-goodsin-table th[data-column="missing"],
        .sop-goodsin-table td[data-column="missing"],
        .sop-goodsin-table th[data-column="reject"],
        .sop-goodsin-table td[data-column="reject"] {
            width: 80px;
            min-width: 80px;
        }
        /* Reason + Carton no. */
        .sop-goodsin-table th[data-column="reason"],
        .sop-goodsin-table td[data-column="reason"] {
            width: 88px;
            min-width: 88px;
            max-width: 88px;
        }
        .sop-goodsin-table th[data-column="carton"],
        .sop-goodsin-table td[data-column="carton"] {
            width: 120px;
            min-width: 120px;
        }
        .sop-goodsin-carton-text {
            white-space: normal;
            word-break: break-word;
            line-height: 1.2;
        }
        html.sop-goodsin-modal-open,
        body.sop-goodsin-modal-open {
            overflow: hidden !important;
            height: 100%;
        }
        /* Notes columns */
        .sop-goodsin-table th[data-column="product_notes"],
        .sop-goodsin-table td[data-column="product_notes"],
        .sop-goodsin-table th[data-column="internal_product_notes"],
        .sop-goodsin-table td[data-column="internal_product_notes"],
        .sop-goodsin-table th[data-column="order_notes"],
        .sop-goodsin-table td[data-column="order_notes"],
        .sop-goodsin-table th[data-column="goodsin_notes"],
        .sop-goodsin-table td[data-column="goodsin_notes"] {
            width: 300px;
            min-width: 300px;
            max-width: 300px;
        }
        /* Stocked / Outstanding */
        .sop-goodsin-table th[data-column="stocked"],
        .sop-goodsin-table td[data-column="stocked"] {
            width: 50px;
            min-width: 50px;
        }
        .sop-goodsin-table th[data-column="outstanding"],
        .sop-goodsin-table td[data-column="outstanding"] {
            width: 80px;
            min-width: 80px;
        }
        /* Supplier SKUs (optional column) */
        .sop-goodsin-table th[data-column="supplier_skus"],
        .sop-goodsin-table td[data-column="supplier_skus"] {
            width: 140px;
            min-width: 140px;
            max-width: 180px;
            white-space: normal;
        }
        .sop-goodsin-table td[data-column="supplier_skus"] .sop-supplier-skus-compact {
            display: block;
            cursor: pointer;
        }
        .sop-goodsin-table td[data-column="supplier_skus"] .sop-supplier-skus-line {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            max-width: 100%;
        }
        .sop-goodsin-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }
        .sop-goodsin-filter-checks {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .sop-goodsin-filter-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .sop-goodsin-filter-row input[type="text"] {
            max-width: 100%;
            box-sizing: border-box;
        }
        .sop-goodsin-filter-summary {
            display: inline-block;
        }
        .sop-goodsin-mobile-searchrow {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        /* Desktop toolbar layout (restore after mobile grid changes) */
        @media (min-width: 783px) {
            .sop-goodsin-mobile-grid {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px 12px;
                margin: 0 0 10px 0;
            }
            .sop-goodsin-mg-title {
                flex: 1 1 auto;
            }
            .sop-goodsin-mg-title h1 {
                margin: 0;
                padding: 0;
            }
            .sop-goodsin-mg-back {
                flex: 0 0 auto;
                margin-left: auto;
            }
            .sop-goodsin-mg-sheet {
                flex: 0 0 100%;
            }
            /* Keep action buttons on their own row by pushing Columns to the right and forcing wrap after it */
            .sop-goodsin-mg-columns {
                flex: 0 0 auto;
                margin-left: auto;
            }
            /* Let search consume remaining space on the filter row (wraps naturally on smaller desktops) */
            .sop-goodsin-mg-search {
                flex: 1 1 420px;
                min-width: 320px;
            }
            /* Make Scan input width match Carton input for consistent desktop toolbar sizing */
            #sop-goodsin-scan {
                width: 150px;
                box-sizing: border-box;
            }
        }
        /* Mobile responsive tweaks */
        @media (max-width: 782px) {
            html.sop-goodsin-modal-open #wpadminbar,
            body.sop-goodsin-modal-open #wpadminbar {
                display: none !important;
            }
            .sop-goodsin-product-modal,
            .sop-goodsin-notes-modal-backdrop,
            .sop-goodsin-info-modal-backdrop {
                top: 0 !important;
                height: var(--sop-goodsin-vvh) !important;
                z-index: 1000000;
            }
            .sop-goodsin-info-modal-backdrop {
                position: fixed;
                inset: 0;
                width: 100vw;
                height: 100vh;
                box-sizing: border-box;
            }
            .sop-goodsin-info-modal {
                position: fixed !important;
                left: 50% !important;
                top: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: calc(100vw - 32px);
                max-width: 520px;
                margin: 0 !important;
                max-height: calc(100vh - 32px);
                overflow: auto;
                box-sizing: border-box;
            }
            .sop-goodsin-product-modal__inner {
                width: 100%;
                height: 100%;
                max-height: none;
                margin: 0;
                border-radius: 0;
            }
            .sop-goodsin-toolbar { gap: 8px; }
            .sop-goodsin-toolbar-actions {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .sop-goodsin-toolbar-actions .button,
            .sop-goodsin-toolbar-actions a.button {
                width: 100%;
                text-align: center;
                padding: 10px 8px;
                box-sizing: border-box;
            }
            .sop-goodsin-toolbar-columns,
            .sop-goodsin-columns-toggle {
                width: 100%;
            }

            /* Mobile grid for header/actions/filters: fixed 2-column layout */
            .sop-goodsin-mobile-grid{
                display: grid;
                grid-template-columns: minmax(0,1fr) minmax(0,1fr);
                grid-template-areas:
                    "title back"
                    "sheet sheet"
                    "save add"
                    "complete columns"
                    "show issues"
                    "carton scan"
                    "search search";
                gap: 8px 10px;
                align-items: start;
            }
            .sop-goodsin-mg-title    { grid-area: title; }
            .sop-goodsin-mg-back     { grid-area: back; justify-self: end; }
            .sop-goodsin-mg-sheet    { grid-area: sheet; }
            .sop-goodsin-mg-save     { grid-area: save; }
            .sop-goodsin-mg-add      { grid-area: add; }
            .sop-goodsin-mg-complete { grid-area: complete; }
            .sop-goodsin-mg-columns  { grid-area: columns; justify-self: end; }
            .sop-goodsin-mg-show     { grid-area: show; }
            .sop-goodsin-mg-issues   { grid-area: issues; }
            .sop-goodsin-mg-carton   { grid-area: carton; }
            .sop-goodsin-mg-scan     { grid-area: scan; }
            .sop-goodsin-mg-search   { grid-area: search; }

            .sop-goodsin-mobile-grid .button,
            .sop-goodsin-mobile-grid button{
                width: 100%;
                box-sizing: border-box;
            }
            .sop-goodsin-mg-back .button,
            .sop-goodsin-mg-back a.button,
            .sop-goodsin-mg-columns .button,
            .sop-goodsin-mg-columns button{
                width: auto;
            }

            .sop-goodsin-filter-row {
                width: 100%;
                flex-wrap: nowrap;
            }
            .sop-goodsin-mobile-searchrow {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 8px;
                align-items: center;
                width: 100%;
            }
            .sop-goodsin-filter-row input[type="text"] {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                padding: 10px;
                font-size: 16px;
            }
            .sop-goodsin-filter-row .button-link {
                white-space: nowrap;
                padding: 0;
            }
            #sop-goodsin-filter-summary {
                display: block;
                margin-top: 4px;
            }
            .sop-preorder-table-wrapper {
                padding: 4px;
                border-left: 0;
                border-right: 0;
                overflow-x: auto;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }
            .sop-preorder-table-wrapper .sop-goodsin-table {
                width: max-content;
                min-width: 1100px;
                table-layout: auto;
            }
            #sop-goodsin-search {
                height: 40px;
                min-height: 40px;
                box-sizing: border-box;
            }
            .sop-goodsin-scan-btn {
                height: 40px;
                min-height: 40px;
                box-sizing: border-box;
            }
            #sop-goodsin-carton,
            #sop-goodsin-scan {
                height: 40px;
                min-height: 40px;
                box-sizing: border-box;
            }
            #sop-goodsin-select-all {
                width: 25px;
                height: 25px;
                vertical-align: middle;
            }
            .sop-goodsin-columns-popover {
                max-height: 60vh;
                overflow: auto;
            }
        }
        .sop-skus-modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 10000;
        }
        .sop-skus-modal.is-open {
            display: block;
        }
        .sop-skus-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
        }
        .sop-skus-modal__panel {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 16px;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            min-width: 280px;
            max-width: 480px;
            max-height: 70vh;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sop-skus-modal__title {
            font-weight: 700;
            margin: 0;
        }
        .sop-skus-modal__context {
            margin: 0;
            font-size: 13px;
            line-height: 1.3;
            color: #1d2327;
        }
        .sop-skus-modal__context-sku {
            font-family: Consolas, Monaco, monospace;
            color: #50575e;
            word-break: break-word;
        }
        .sop-skus-modal__content {
            white-space: pre-line;
            margin: 0;
            padding: 8px;
            border: 1px solid #dcdcde;
            border-radius: 3px;
            background: #f6f7f7;
            overflow: auto;
            flex: 1 1 auto;
        }
        .sop-skus-modal__close {
            position: absolute;
            top: 6px;
            right: 6px;
            background: transparent;
            border: 0;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
        }
        :root {
            --sop-wpadminbar-h: 0px;
            --sop-goodsin-vvh: 100vh;
        }
        .sop-goodsin-product-modal {
            position: fixed;
            left: 0;
            right: 0;
            top: var(--sop-wpadminbar-h);
            height: calc(var(--sop-goodsin-vvh) - var(--sop-wpadminbar-h));
            display: none;
            z-index: 10002;
            overscroll-behavior: none;
        }
        .sop-goodsin-product-modal.is-open {
            display: block;
        }
        .sop-goodsin-product-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.45);
        }
        .sop-goodsin-product-modal__inner {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            padding: 16px 18px 20px;
            max-width: 520px;
            width: 95vw;
            height: 100%;
            max-height: none;
            overflow: hidden;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        .sop-goodsin-product-modal__header {
            display: grid;
            grid-template-columns: 44px 1fr 88px;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .sop-goodsin-product-modal__header-title {
            justify-self: center;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            color: #1d2327;
        }
        .sop-goodsin-product-modal__header-btn {
            background: none;
            border: 0;
            padding: 0;
            cursor: pointer;
            min-width: 40px;
            min-height: 40px;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            box-sizing: border-box;
        }
        .sop-goodsin-product-modal__close {
            border: 1px solid #c3c4c7;
            border-radius: 999px;
            background: #fff;
            color: #1d2327;
            font-size: 20px;
            line-height: 1;
            text-align: center;
        }
        .sop-goodsin-product-modal__header-nav {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
        }
        .sop-goodsin-product-modal__nav-btn {
            border: 1px solid #c3c4c7;
            border-radius: 999px;
            background: #fff;
            color: #1d2327;
            font-size: 18px;
            line-height: 1;
            text-align: center;
        }
        @media (min-width: 783px) {
            .sop-goodsin-product-modal__header-btn .sop-goodsin-modal-header-icon {
                display: inline-block;
                line-height: 1;
                transform: translateY(-0.10em);
            }
            .sop-goodsin-product-modal__close .sop-goodsin-modal-header-icon {
                transform: translateY(-0.11em);
            }
        }
        .sop-goodsin-product-modal__nav-btn.is-disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        .sop-goodsin-product-modal__card {
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1 1 auto;
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
            overscroll-behavior: contain;
        }
        .sop-goodsin-product-modal__card::-webkit-scrollbar {
            width: 0;
            height: 0;
        }
        .sop-goodsin-product-modal__block {
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 12px 14px;
            background: #fff;
            font-size: 16px;
            color: #1d2327;
            line-height: 1.35;
        }
        .sop-goodsin-product-modal__block--sku {
            background: #f6f7f7;
            font-size: 14px;
            color: #1d2327;
        }
        @media (min-width: 783px) {
            .sop-goodsin-product-modal__title-sku-row {
                display: flex;
                gap: 12px;
                width: 100%;
            }
            .sop-goodsin-product-modal__title-sku-row > .sop-goodsin-product-modal__block {
                flex: 1 1 0;
                min-width: 0;
            }
        }
        .sop-goodsin-product-modal__media {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .sop-goodsin-product-modal__image-wrap {
            flex: 0 0 44%;
            max-width: 44%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        .sop-goodsin-product-modal__image {
            max-width: 100%;
            max-height: 180px;
            width: auto;
            height: auto;
            border: 1px solid #e2e4e7;
            border-radius: 6px;
            background: #fff;
        }
        .sop-goodsin-product-modal__meta {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 15px;
            color: #1d2327;
        }
        .sop-goodsin-product-modal__edit {
            color: #2271b1;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-top: 4px;
        }
        .sop-goodsin-product-modal__notes {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 4px;
        }
        .sop-goodsin-product-modal__notes--buttons {
            width: 100%;
            gap: 8px;
        }
        .sop-goodsin-product-modal__notes-buttons {
            display: flex;
            align-items: center;
            width: 100%;
            gap: 10px;
            flex-wrap: nowrap;
        }
        .sop-goodsin-product-modal__note-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid rgba(0, 0, 0, 0.25);
            color: #fff;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            cursor: pointer;
            flex: 1 1 0;
            min-width: 0;
            text-align: center;
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.35);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), inset 0 -2px 6px rgba(0, 0, 0, 0.35), 0 1px 2px rgba(0, 0, 0, 0.25);
            background: #c3c4c7;
        }
        .sop-goodsin-product-modal__note-chip.is-disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .sop-goodsin-product-modal__note-chip-status {
            font-weight: 700;
            width: 14px;
            text-align: center;
            color: #fff;
        }
        .sop-goodsin-product-modal__note-chip.is-no {
            background: linear-gradient(180deg, #b3b3b3 0%, #8d8d8d 55%, #707070 100%);
            border-color: #8d8d8d;
            opacity: 0.8;
            cursor: default;
        }
        .sop-goodsin-product-modal__note-chip.is-yes {
            background: linear-gradient(180deg, #7dff5b 0%, #29c324 55%, #0b6f1a 100%);
            border-color: #0b6f1a;
        }
        .sop-goodsin-product-modal__note-chip.is-no .sop-goodsin-product-modal__note-chip-status::before {
            content: "\2715";
        }
        .sop-goodsin-product-modal__note-chip.is-yes .sop-goodsin-product-modal__note-chip-status::before {
            content: "\2713";
        }
        .sop-goodsin-product-modal__note-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
        }
        .sop-goodsin-product-modal__note-label {
            flex: 1 1 auto;
        }
        .sop-goodsin-product-modal__note-status {
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }
        .sop-goodsin-product-modal__note-status.is-no {
            color: #d63638;
        }
        .sop-goodsin-product-modal__note-status.is-yes {
            color: #1f9d55;
        }
        .sop-goodsin-product-modal__note-status.is-no::before {
            content: "X";
        }
        .sop-goodsin-product-modal__note-status.is-yes::before {
            content: "\2713";
        }
        .sop-goodsin-product-modal__note-view {
            border: 1px solid #50575e;
            background: #fff;
            color: #1d2327;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 15px;
            line-height: 1.2;
            cursor: pointer;
        }
        .sop-goodsin-product-modal__note-view.is-disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .sop-goodsin-product-modal__qty-ordered {
            border: 1px solid #e2e4e7;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            font-size: 17px;
            font-weight: 600;
            background: #fff;
        }
        .sop-goodsin-product-modal__qty-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 22px;
            margin-top: 6px;
        }
        .sop-goodsin-product-modal__qty-btn {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: 3px solid transparent;
            background: #fff;
            font-size: 30px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sop-goodsin-product-modal__qty-btn--minus {
            border-color: #d63638;
            color: #d63638;
        }
        .sop-goodsin-product-modal__qty-btn--plus {
            border-color: #2ea2cc;
            color: #2ea2cc;
        }
        .sop-goodsin-product-modal__qty-value {
            font-size: 24px;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
            width: 84px;
            border: 1px solid #dcdcde;
            border-radius: 12px;
            padding: 6px 8px;
            background: #fff;
        }
        .sop-goodsin-product-modal__summary {
            text-align: center;
            font-size: 16px;
            line-height: 1.4;
            color: #1d2327;
        }
        .sop-goodsin-product-modal__actions {
            display: flex;
            gap: 14px;
            margin-top: 6px;
            position: sticky;
            bottom: 0;
            background: #fff;
            padding-bottom: env(safe-area-inset-bottom);
            border-top: 1px solid rgba(0,0,0,0.08);
            z-index: 2;
        }
        .sop-goodsin-product-modal__action-btn {
            flex: 1 1 50%;
            padding: 12px 14px;
            border-radius: 16px;
            border: 0;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .sop-goodsin-product-modal__action-btn--scan {
            background: #9da0a6;
            color: #fff;
        }
        .sop-goodsin-product-modal__action-btn--confirm {
            background: #2ea2cc;
            color: #fff;
        }
        .sop-goodsin-product-modal__image.is-hidden,
        .sop-goodsin-product-modal__edit.is-hidden,
        .sop-goodsin-product-modal__stock.is-hidden {
            display: none;
        }
        @media (max-width: 782px) {
            .sop-goodsin-product-modal__inner {
                top: 0;
                left: 0;
                transform: none;
                width: 100%;
                max-width: 100%;
                height: 100%;
                max-height: none;
                overflow: hidden;
                border-radius: 0;
            }
            .sop-goodsin-product-modal__notes--buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .sop-goodsin-product-modal__notes-buttons {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            .sop-goodsin-product-modal__note-chip {
                width: 100%;
                justify-content: center;
            }
        }
        @media (max-width: 1024px) {
            .sop-goodsin-product-modal__inner {
                top: 0;
                left: 0;
                transform: none;
                width: 100%;
                height: 100%;
                max-width: none;
                max-height: none;
                border-radius: 0;
                padding: 16px 18px 20px;
            }
        }
        @media (min-width: 1025px) {
            .sop-goodsin-product-modal__inner {
                height: 100%;
                max-height: none;
            }
        }
        @media (min-width: 783px) {
            .sop-goodsin-product-modal__qty-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                padding: 0;
            }
            .sop-goodsin-product-modal__qty-btn .sop-goodsin-modal-qty-icon {
                display: inline-block;
                line-height: 1;
                transform: translateY(-0.08em);
            }
        }
        @media (max-width: 480px) {
            .sop-goodsin-product-modal__media {
                gap: 12px;
            }
            .sop-goodsin-product-modal__image-wrap {
                flex: 0 0 42%;
                max-width: 42%;
                min-height: 110px;
            }
        }
        .sop-scan-modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 1000005;
        }
        .sop-scan-modal.is-open {
            display: block;
        }
        .sop-scan-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
        }
        .sop-scan-modal__panel {
            position: absolute;
            inset: 0;
            background: transparent;
            padding: 0;
            border-radius: 0;
            box-shadow: none;
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            display: flex;
            flex-direction: column;
        }
        .sop-scan-modal__header,
        .sop-scan-modal__footer {
            position: absolute;
            left: 0;
            right: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            color: #fff;
            pointer-events: auto;
        }
        .sop-scan-modal__header {
            top: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.75), rgba(0,0,0,0));
        }
        .sop-scan-modal__footer {
            bottom: 0;
            justify-content: flex-end;
            background: linear-gradient(0deg, rgba(0,0,0,0.75), rgba(0,0,0,0));
        }
        .sop-scan-modal__title {
            font-weight: 700;
            margin: 0;
            color: #fff;
        }
        .sop-scan-modal__status {
            min-height: 18px;
            font-size: 13px;
            color: #dfe3e8;
        }
        .sop-goodsin-scan-viewport {
            position: relative;
            width: 100%;
            flex: 1 1 auto;
            min-height: 180px;
            max-height: 60vh;
            background: #000;
        }
        .sop-scan-modal__video {
            width: 100%;
            height: 100%;
            background: #000;
            object-fit: cover;
            display: block;
        }
        .sop-goodsin-scan-frame {
            position: absolute;
            left: 50%;
            top: 40%;
            transform: translate(-50%, -50%);
            width: min(86vw, 560px);
            aspect-ratio: 3.6 / 1;
            border: 2px solid rgba(255,255,255,0.85);
            border-radius: 14px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);
            overflow: hidden;
            pointer-events: none;
        }
        .sop-goodsin-scan-line {
            position: absolute;
            left: 8%;
            right: 8%;
            height: 3px;
            top: 10%;
            background: rgba(0, 255, 120, 0.35);
            box-shadow: 0 0 10px rgba(0, 255, 120, 0.35);
            animation: sopScanLineMove 1.2s linear infinite;
        }
        .sop-scan-modal.sop-goodsin-scan-hit .sop-goodsin-scan-frame {
            border-color: rgba(0,255,120,0.95);
        }
        .sop-scan-modal.sop-goodsin-scan-hit .sop-goodsin-scan-line {
            background: rgba(0,255,120,0.95);
            box-shadow: 0 0 18px rgba(0,255,120,0.95);
        }
        @keyframes sopScanLineMove {
            0% { top: 12%; }
            100% { top: 88%; }
        }
        .sop-scan-modal__actions {
            text-align: right;
        }
        .sop-scan-modal__close {
            background: transparent;
            border: 0;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            color: #fff;
        }
        html.sop-goodsin-scan-open .sop-goodsin-product-modal,
        body.sop-goodsin-scan-open .sop-goodsin-product-modal {
            pointer-events: none !important;
        }
        .sop-goodsin-readonly-val {
            display: inline-block;
            min-width: 44px;
            padding: 6px 8px;
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            text-align: center;
        }
        .sop-goodsin-readonly-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            font-weight: 600;
        }
        .sop-goodsin-report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .sop-goodsin-table td input[type="text"],
        .sop-goodsin-table td input[type="number"],
        .sop-goodsin-table td select,
        .sop-goodsin-table td textarea {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .sop-goodsin-table tbody tr {
            height: 80px;
        }
        .sop-goodsin-table tbody td {
            height: 80px;
            vertical-align: middle;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .sop-goodsin-table tbody td img {
            max-height: 78px;
            width: auto;
        }
        .sop-goodsin-table textarea {
            height: 60px;
            resize: vertical;
        }
        .sop-goodsin-table thead th.check-column {
            width: 16px;
            min-width: 16px;
            max-width: 16px;
            padding: 0 10px !important;
            text-align: center;
            vertical-align: middle;
        }
        .sop-goodsin-table thead th.check-column input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin: 0;
            vertical-align: middle;
        }
        .sop-goodsin-table .sop-goodsin-cell-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sop-goodsin-table td.sop-goodsin-col-product a,
        .sop-goodsin-table td.sop-goodsin-col-product .sop-goodsin-product-link {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 4;
            overflow: hidden;
            white-space: normal !important;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.2;
            max-height: 80px;
            text-align: left;
        }
        .sop-goodsin-table .sop-notes-preview {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 4;
            overflow: hidden;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.2;
            max-height: 4.8em;
            text-align: left;
        }
        .sop-goodsin-table .sop-notes-html {
            white-space: normal;
            word-break: break-word;
        }
        .sop-goodsin-table .sop-notes-preview.is-truncated {
            cursor: pointer;
        }
        .sop-note-red {
            color: #d63638;
        }
        .sop-goodsin-table .sop-goodsin-notes-preview-box {
            background: #fff;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            box-sizing: border-box;
            width: 100%;
            height: 66px;
            padding: 6px 30px 6px 8px;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .sop-goodsin-table .sop-goodsin-notes-preview {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
            height: 100%;
            text-align: left;
        }
        .sop-goodsin-table .sop-goodsin-notes-preview-box .sop-goodsin-notes-edit {
            position: absolute;
            top: 6px;
            right: 6px;
            padding: 0;
            margin: 0;
            width: 18px;
            height: 18px;
            line-height: 18px;
        }
        .sop-goodsin-table .sop-goodsin-notes-cell {
            height: 80px;
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
        }
        .sop-goodsin-notes-modal-backdrop {
            position: fixed;
            left: 0;
            right: 0;
            top: var(--sop-wpadminbar-h);
            height: calc(var(--sop-goodsin-vvh) - var(--sop-wpadminbar-h));
            background: rgba(0, 0, 0, 0.4);
            z-index: 100000;
            display: none;
        }
        .sop-goodsin-notes-modal {
            position: fixed;
            top: calc(var(--sop-wpadminbar-h) + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 16px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 100001;
            width: 700px;
            max-width: 700px;
            box-sizing: border-box;
            display: none;
        }
        .sop-goodsin-notes-modal h3 {
            margin-top: 0;
        }
        .sop-goodsin-info-modal-backdrop {
            position: fixed;
            left: 0;
            right: 0;
            top: var(--sop-wpadminbar-h);
            height: calc(var(--sop-goodsin-vvh) - var(--sop-wpadminbar-h));
            background: rgba(0, 0, 0, 0.4);
            z-index: 100000;
            display: none;
        }
        .sop-goodsin-info-modal {
            position: fixed;
            top: calc(var(--sop-wpadminbar-h) + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 16px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 100001;
            width: 560px;
            max-width: 90%;
            box-sizing: border-box;
            display: none;
        }
        .sop-goodsin-info-modal h3 {
            margin-top: 0;
        }
        .sop-goodsin-complete-modal-backdrop {
            position: fixed;
            left: 0;
            right: 0;
            top: var(--sop-wpadminbar-h);
            height: calc(var(--sop-goodsin-vvh) - var(--sop-wpadminbar-h));
            background: rgba(0, 0, 0, 0.4);
            z-index: 100000;
            display: none;
        }
        .sop-goodsin-complete-modal {
            position: fixed;
            top: calc(var(--sop-wpadminbar-h) + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 16px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 100001;
            width: 560px;
            max-width: 90%;
            box-sizing: border-box;
            display: none;
        }
        .sop-goodsin-complete-modal h3 {
            margin-top: 0;
        }
        .sop-goodsin-complete-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
        }
        .sop-goodsin-info-modal-body {
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.4;
            max-height: 60vh;
            overflow: auto;
        }
        .sop-goodsin-info-close {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 0;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
        }
        @media (min-width: 783px) {
            .sop-goodsin-notes-modal,
            .sop-goodsin-info-modal,
            .sop-goodsin-complete-modal {
                top: calc(var(--sop-wpadminbar-h) + 50%);
                transform: translate(-50%, -50%);
                max-height: 80vh;
                overflow: auto;
            }
        }
        .sop-goodsin-notes-modal-body {
            margin-bottom: 10px;
        }
        .sop-goodsin-notes-modal textarea {
            width: 100%;
            min-height: 180px;
            resize: vertical;
            overflow: auto;
            box-sizing: border-box;
            border: 1px solid #8c8f94;
            box-shadow: none;
            outline: 0;
        }
        .sop-goodsin-notes-modal textarea:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }
        .sop-goodsin-notes-modal-actions {
            display: flex;
            justify-content: flex-start;
            gap: 8px;
            margin-top: 12px;
        }
        .sop-goodsin-notes-close {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 0;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
        }
        /* Location / SKU / Product widths + padding */
        /* Location column — match Pre-Order sheet */
        .sop-goodsin-table thead th.column-location {
            width: 90px;
            white-space: nowrap;
            padding-left: 8px;
            padding-right: 8px;
        }
        .sop-goodsin-table tbody td.column-location {
            width: 90px;
            white-space: nowrap;
            padding-left: 8px !important;
            padding-right: 8px !important;
        }
        .sop-goodsin-table th.sop-goodsin-col-sku,
        .sop-goodsin-table td.sop-goodsin-col-sku {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
            padding-left: 10px !important;
            padding-right: 14px !important;
        }
        .sop-goodsin-table th.sop-goodsin-col-product,
        .sop-goodsin-table td.sop-goodsin-col-product {
            width: 264px;
            min-width: 264px;
            max-width: 264px;
            padding-left: 10px !important;
            padding-right: 14px !important;
        }
        .sop-goodsin-table .sop-goodsin-product-wrap {
            height: 80px;
            display: flex;
            align-items: center;
            overflow: hidden;
            text-align: left;
        }
        .sop-goodsin-filter {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
        }
        .sop-goodsin-search-wrap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        #sop-goodsin-search {
            width: 250px;
            box-sizing: border-box;
        }
        .sop-goodsin-row-hidden {
            display: none;
        }
        .sop-goodsin-row-search-hidden {
            display: none;
        }
        .sop-goodsin-row-issues-hidden {
            display: none;
        }
        .sop-goodsin-dispute-summary {
            margin-top: 10px;
        }
        .sop-goodsin-dispute-summary ul {
            margin: 0 0 0 18px;
        }
        .sop-goodsin-row-jump-highlight {
            outline: 2px solid #2271b1;
            outline-offset: -2px;
        }
        .sop-goodsin-row-carton-hidden {
            display: none;
        }
        #sop-goodsin-carton {
            width: 150px;
            box-sizing: border-box;
        }
        .sop-goodsin-row-hidden {
            display: none;
        }
        .sop-goodsin-table td.sop-goodsin-col-product {
            white-space: normal !important;
            overflow: hidden;
        }
        .sop-goodsin-table td.sop-goodsin-col-product a,
        .sop-goodsin-table td.sop-goodsin-col-product .sop-goodsin-product-link {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 4;
            overflow: hidden;
            white-space: normal !important;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.2;
            max-height: 4.8em;
            text-align: left;
        }
        .sop-goodsin-col-narrow {
            white-space: nowrap;
        }
        .sop-goodsin-narrow {
            width: 7ch;
        }
        .sop-goodsin-text-col {
            max-width: 260px;
            word-break: break-word;
        }
        .sop-goodsin-sort {
            cursor: pointer;
            white-space: nowrap;
            position: relative;
            padding-right: 14px;
        }
        .sop-goodsin-sort::after {
            content: '\25B2';
            opacity: 0.3;
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
        }
        .sop-goodsin-sort.sorted-asc::after {
            content: '\25B2';
            opacity: 1;
        }
        .sop-goodsin-sort.sorted-desc::after {
            content: '\25BC';
            opacity: 1;
        }
        #sop-goodsin-lines .check-column input[type="checkbox"] {
            margin: 0 !important;
        }
        .sop-goodsin-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .sop-goodsin-columns {
            position: relative;
            display: inline-block;
        }
        .sop-goodsin-columns-popover {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 6px;
            z-index: 1000;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            padding: 10px;
            min-width: 240px;
        }
        .sop-goodsin-columns.is-open .sop-goodsin-columns-popover {
            display: block;
        }
        .sop-goodsin-columns-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 320px;
            overflow: auto;
        }
        .sop-goodsin-columns-list li {
            margin: 0 0 6px 0;
        }
    </style>

    <div id="sop-skus-modal" class="sop-skus-modal" aria-hidden="true">
        <div class="sop-skus-modal__backdrop" data-sop-close="1"></div>
        <div class="sop-skus-modal__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Supplier SKUs', 'sop' ); ?>">
            <button type="button" class="sop-skus-modal__close" data-sop-close="1" aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">×</button>
            <div class="sop-skus-modal__title"><?php esc_html_e( 'Supplier SKUs', 'sop' ); ?></div>
            <div class="sop-skus-modal__context">
                <div class="sop-skus-modal__context-name"></div>
                <div class="sop-skus-modal__context-sku"></div>
            </div>
		<pre class="sop-skus-modal__content"></pre>
	</div>
</div>

<div id="sop-goodsin-product-modal" class="sop-modal sop-goodsin-product-modal" aria-hidden="true">
	<div class="sop-goodsin-product-modal__backdrop" data-sop-prod-close="1"></div>
    <div class="sop-modal__inner sop-goodsin-product-modal__inner" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Product details', 'stock-order-plugin' ); ?>">
        <div class="sop-goodsin-product-modal__header">
            <button type="button" class="sop-goodsin-product-modal__header-btn sop-goodsin-product-modal__close" data-sop-prod-close="1" aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>"><span class="sop-goodsin-modal-header-icon" aria-hidden="true">&times;</span></button>
            <div class="sop-goodsin-product-modal__header-title"><?php esc_html_e( 'Goods-In', 'sop' ); ?></div>
            <div class="sop-goodsin-product-modal__header-nav">
                <button type="button" class="sop-goodsin-product-modal__header-btn sop-goodsin-product-modal__nav-btn sop-goodsin-pm-nav-prev" id="sop-goodsin-modal-prev" aria-label="<?php esc_attr_e( 'Previous product', 'sop' ); ?>"><span class="sop-goodsin-modal-header-icon" aria-hidden="true">&lsaquo;</span></button>
                <button type="button" class="sop-goodsin-product-modal__header-btn sop-goodsin-product-modal__nav-btn sop-goodsin-pm-nav-next" id="sop-goodsin-modal-next" aria-label="<?php esc_attr_e( 'Next product', 'sop' ); ?>"><span class="sop-goodsin-modal-header-icon" aria-hidden="true">&rsaquo;</span></button>
            </div>
        </div>
        <div class="sop-goodsin-product-modal__card">
			<div class="sop-goodsin-product-modal__title-sku-row">
				<div id="sop-product-modal-name" class="sop-goodsin-product-modal__block sop-goodsin-product-modal__name"></div>
				<div id="sop-product-modal-sku" class="sop-goodsin-product-modal__block sop-goodsin-product-modal__block--sku sop-goodsin-product-modal__sku"></div>
			</div>

			<div class="sop-goodsin-product-modal__media">
				<div class="sop-goodsin-product-modal__image-wrap">
					<img id="sop-product-modal-image" class="sop-goodsin-product-modal__image" alt="">
				</div>
                <div class="sop-goodsin-product-modal__meta">
                    <div id="sop-product-modal-carton" class="sop-goodsin-product-modal__carton"></div>
                    <div id="sop-product-modal-location" class="sop-goodsin-product-modal__location"></div>
                    <div id="sop-product-modal-stock" class="sop-goodsin-product-modal__stock is-hidden"></div>
                    <div id="sop-product-modal-buffer-stock" class="sop-goodsin-product-modal__stock sop-goodsin-product-modal__stock--buffer is-hidden"></div>
                    <a id="sop-product-modal-edit" class="sop-goodsin-product-modal__edit" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit product', 'sop' ); ?></a>
                </div>
			</div>

			<div class="sop-goodsin-product-modal__notes sop-goodsin-product-modal__notes--buttons">
				<div class="sop-goodsin-product-modal__notes-buttons">
					<button type="button" id="sop-product-modal-notes-product-btn" class="sop-goodsin-product-modal__note-chip">
						<span class="sop-goodsin-product-modal__note-chip-text"><?php esc_html_e( 'Product notes', 'sop' ); ?></span>
						<span class="sop-goodsin-product-modal__note-chip-status" aria-hidden="true"></span>
					</button>
					<button type="button" id="sop-product-modal-notes-internal-btn" class="sop-goodsin-product-modal__note-chip">
						<span class="sop-goodsin-product-modal__note-chip-text"><?php esc_html_e( 'Internal notes', 'sop' ); ?></span>
						<span class="sop-goodsin-product-modal__note-chip-status" aria-hidden="true"></span>
					</button>
					<button type="button" id="sop-product-modal-notes-order-btn" class="sop-goodsin-product-modal__note-chip">
						<span class="sop-goodsin-product-modal__note-chip-text"><?php esc_html_e( 'Order notes', 'sop' ); ?></span>
						<span class="sop-goodsin-product-modal__note-chip-status" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<div id="sop-product-modal-qty-ordered" class="sop-goodsin-product-modal__qty-ordered"></div>
			<div class="sop-goodsin-product-modal__qty-control">
				<button type="button" id="sop-product-modal-btn-minus" class="sop-goodsin-product-modal__qty-btn sop-goodsin-product-modal__qty-btn--minus" aria-label="<?php esc_attr_e( 'Decrease received quantity', 'sop' ); ?>"><span class="sop-goodsin-modal-qty-icon" aria-hidden="true">−</span></button>
				<input type="number" id="sop-product-modal-qty-value" class="sop-goodsin-product-modal__qty-value" inputmode="numeric" pattern="[0-9]*" step="1" min="0" value="0" />
				<button type="button" id="sop-product-modal-btn-plus" class="sop-goodsin-product-modal__qty-btn sop-goodsin-product-modal__qty-btn--plus" aria-label="<?php esc_attr_e( 'Increase received quantity', 'sop' ); ?>"><span class="sop-goodsin-modal-qty-icon" aria-hidden="true">+</span></button>
			</div>

			<div class="sop-goodsin-product-modal__summary">
				<div id="sop-product-modal-added"></div>
				<div id="sop-product-modal-outstanding"></div>
			</div>

			<div class="sop-goodsin-product-modal__actions">
				<button type="button" id="sop-product-modal-btn-scan-next" class="sop-goodsin-product-modal__action-btn sop-goodsin-product-modal__action-btn--scan"><?php esc_html_e( 'Scan next', 'sop' ); ?></button>
				<button type="button" id="sop-product-modal-btn-confirm" class="sop-goodsin-product-modal__action-btn sop-goodsin-product-modal__action-btn--confirm"><?php esc_html_e( 'Confirm', 'sop' ); ?></button>
			</div>
		</div>
	</div>
</div>

    <div id="sop-goodsin-scan-modal" class="sop-scan-modal" aria-hidden="true">
        <div class="sop-scan-modal__backdrop" data-sop-scan-close="1"></div>
        <div class="sop-scan-modal__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Scan barcode', 'sop' ); ?>">
            <div class="sop-scan-modal__header">
                <div>
                    <div class="sop-scan-modal__title"><?php esc_html_e( 'Scan barcode', 'sop' ); ?></div>
                    <div class="sop-scan-modal__status" id="sop-goodsin-scan-status"></div>
                </div>
                <button type="button" class="sop-scan-modal__close" data-sop-scan-close="1" aria-label="<?php esc_attr_e( 'Close', 'sop' ); ?>">×</button>
            </div>
            <div class="sop-goodsin-scan-viewport">
                <video id="sop-goodsin-scan-video" autoplay playsinline class="sop-scan-modal__video"></video>
                <div class="sop-goodsin-scan-frame" aria-hidden="true">
                    <div class="sop-goodsin-scan-line" aria-hidden="true"></div>
                </div>
            </div>
            <div class="sop-scan-modal__footer">
                <button type="button" class="button" data-sop-scan-close="1"><?php esc_html_e( 'Cancel', 'sop' ); ?></button>
            </div>
        </div>
    </div>

    <script>
        (function($){
            function sopGoodsinSyncAdminbarHeightVar() {
                var bar = document.getElementById('wpadminbar');
                var h = 0;
                if ( bar && bar.getBoundingClientRect ) {
                    h = Math.round( bar.getBoundingClientRect().height || 0 );
                }
                if ( window.matchMedia && window.matchMedia('(max-width: 782px)').matches && document.documentElement.classList.contains('sop-goodsin-modal-open') ) {
                    h = 0;
                }
                document.documentElement.style.setProperty('--sop-wpadminbar-h', h + 'px');
            }

            function sopGoodsinSyncVisualViewportVar() {
                var h = 0;
                if ( window.visualViewport && window.visualViewport.height ) {
                    h = Math.round( window.visualViewport.height );
                } else if ( window.innerHeight ) {
                    h = Math.round( window.innerHeight );
                }
                if ( h > 0 ) {
                    document.documentElement.style.setProperty('--sop-goodsin-vvh', h + 'px');
                }
            }

            function sopGoodsinBeep() {
                try {
                    var now = Date.now();
                    if ( window.__sop_goodsin_last_beep_at && ( now - window.__sop_goodsin_last_beep_at ) < 300 ) {
                        return;
                    }
                    window.__sop_goodsin_last_beep_at = now;
                    var Ctx = window.AudioContext || window.webkitAudioContext;
                    if ( ! Ctx ) {
                        return;
                    }
                    if ( ! window.__sop_goodsin_audio_ctx ) {
                        window.__sop_goodsin_audio_ctx = new Ctx();
                    }
                    var ctx = window.__sop_goodsin_audio_ctx;
                    var o = ctx.createOscillator();
                    var g = ctx.createGain();
                    o.type = 'sine';
                    o.frequency.value = 880;
                    g.gain.value = 0.08;
                    o.connect(g);
                    g.connect(ctx.destination);
                    o.start();
                    setTimeout(function(){ o.stop(); }, 90);
                } catch (e) {}
                try {
                    if ( navigator.vibrate ) {
                        navigator.vibrate(60);
                    }
                } catch (e) {}
            }

            var $form = $('#sop-goodsin-form');
            var $payload = $('#sop-goodsin-payload-json');
            var $actionField = $('#sop-goodsin-action');
            var dirty = false;
            var $columnsToggle = $('.sop-goodsin-columns-toggle');
            var $columnsWrapper = $('.sop-goodsin-columns');
            var $columnCheckboxes = $('.sop-goodsin-columns-list input[type="checkbox"]');
            var $notesModal = $('#sop-goodsin-notes-modal');
            var $notesModalBackdrop = $('#sop-goodsin-notes-modal-backdrop');
            var $notesModalEditor = $('#sop-goodsin-notes-editor');
            var $notesModalProduct = $('#sop-goodsin-notes-product');
            var $notesModalSave = $('#sop-goodsin-notes-save');
            var $notesModalClose = $('#sop-goodsin-notes-close');
            var $infoModal = $('#sop-goodsin-info-modal');
            var $infoModalBackdrop = $('#sop-goodsin-info-modal-backdrop');
            var $infoModalTitle = $('#sop-goodsin-info-modal-title');
            var $infoModalBody = $('#sop-goodsin-info-modal-body');
            var $completeModal = $('#sop-goodsin-complete-modal');
            var $completeModalBackdrop = $('#sop-goodsin-complete-modal-backdrop');
            var $completeConfirmCheck = $('#sop-goodsin-complete-confirm-check');
            var $completeConfirmButton = $('#sop-goodsin-complete-confirm');
            var $confirmCompleteField = $('#sop-goodsin-confirm-complete');
            var $skusModal = $('#sop-skus-modal');
            var $skusModalContent = $('#sop-skus-modal .sop-skus-modal__content');
            var $skusModalContextName = $('#sop-skus-modal .sop-skus-modal__context-name');
            var $skusModalContextSku = $('#sop-skus-modal .sop-skus-modal__context-sku');
            var $showCompleted = $('#sop-goodsin-show-completed');
            var $filterSummary = $('#sop-goodsin-filter-summary');
            var $searchInput = $('#sop-goodsin-search');
            var $searchClear = $('#sop-goodsin-search-clear');
            var $scanInput = $('#sop-goodsin-scan');
            var $scanButton = $('#sop-goodsin-scan-btn');
            var $scanModal = $('#sop-goodsin-scan-modal');
            var $scanVideo = $('#sop-goodsin-scan-video');
            var $scanStatus = $('#sop-goodsin-scan-status');
            var $productModal = $('#sop-goodsin-product-modal');
            var $productModalName = $('#sop-product-modal-name');
            var $productModalSku = $('#sop-product-modal-sku');
            var $productModalLocation = $('#sop-product-modal-location');
            var $productModalCarton = $('#sop-product-modal-carton');
            var $productModalStock = $('#sop-product-modal-stock');
            var $productModalBufferStock = $('#sop-product-modal-buffer-stock');
            var $productModalEdit = $('#sop-product-modal-edit');
            var $productModalImage = $('#sop-product-modal-image');
            var $productModalQtyOrdered = $('#sop-product-modal-qty-ordered');
            var $productModalQtyValue = $('#sop-product-modal-qty-value');
            var $productModalAdded = $('#sop-product-modal-added');
            var $productModalOutstanding = $('#sop-product-modal-outstanding');
            var $productModalNotesProductBtn = $('#sop-product-modal-notes-product-btn');
            var $productModalNotesInternalBtn = $('#sop-product-modal-notes-internal-btn');
            var $productModalNotesOrderBtn = $('#sop-product-modal-notes-order-btn');
            var $productModalBtnMinus = $('#sop-product-modal-btn-minus');
            var $productModalBtnPlus = $('#sop-product-modal-btn-plus');
            var $productModalBtnScanNext = $('#sop-product-modal-btn-scan-next');
            var $productModalBtnConfirm = $('#sop-product-modal-btn-confirm');
            var $productModalHeaderPrev = $('#sop-goodsin-modal-prev');
            var $productModalHeaderNext = $('#sop-goodsin-modal-next');
            var $issuesOnly = $('#sop-goodsin-issues-only');
            var notesActiveRow = null;
            var activeProductRow = null;
            var sopGoodsinFilterTimer = null;
            var sopScanStream = null;
            var sopScanRaf = null;
            var sopScanReadyTimer = null;
            var sopScanActive = false;
            var sopScanLock = false;
            if ( typeof window.__sop_goodsin_scan_from_product_modal === 'undefined' ) {
                window.__sop_goodsin_scan_from_product_modal = false;
            }

            sopGoodsinSyncAdminbarHeightVar();
            sopGoodsinSyncVisualViewportVar();
            $(window).on('resize orientationchange', function(){
                clearTimeout(window.__sopGoodsinAdminbarTimer);
                window.__sopGoodsinAdminbarTimer = setTimeout(function(){
                    sopGoodsinSyncAdminbarHeightVar();
                    sopGoodsinSyncVisualViewportVar();
                }, 100);
            });

            function markDirty() {
                dirty = true;
            }

            function sopGoodsinParseNumber(val) {
                var n = parseFloat( (val || '').toString().replace(/,/g, '') );
                return isNaN(n) ? 0 : n;
            }

            function sopGoodsinNormalizeQuery(str) {
                return (str || '').toString().trim().toLowerCase().replace(/\s+/g, ' ');
            }

            function sopGoodsinParseCartonRanges(raw) {
                var cleaned = (raw || '').toString().replace(/\r?\n/g, ',');
                cleaned = cleaned.replace(/[^\d,\-\s]/g, ' ');
                cleaned = cleaned.replace(/\s*-\s*/g, '-');
                var tokens = cleaned.split(/[,\s]+/);
                var ranges = [];

                tokens.forEach(function(token){
                    var part = (token || '').toString().trim();
                    if ( ! part ) {
                        return;
                    }

                    if ( part.indexOf('-') !== -1 ) {
                        var bits = part.split('-').map(function(val){
                            return (val || '').toString().trim();
                        }).filter(function(val){
                            return val !== '';
                        });
                        if ( bits.length >= 2 ) {
                            var start = parseInt(bits[0], 10);
                            var end = parseInt(bits[1], 10);
                            if ( ! isNaN(start) && ! isNaN(end) ) {
                                if ( start > end ) {
                                    var tmp = start;
                                    start = end;
                                    end = tmp;
                                }
                                ranges.push({ start: start, end: end });
                            }
                        }
                        return;
                    }

                    var single = parseInt(part, 10);
                    if ( ! isNaN(single) ) {
                        ranges.push({ start: single, end: single });
                    }
                });

                return ranges;
            }

            function sopGoodsinGetRowCartonRanges($tr) {
                var cached = $tr.data('sopCartonRanges');
                if ( cached ) {
                    return cached;
                }
                var raw = ($tr.attr('data-carton') || '').toString();
                if ( ! raw ) {
                    var $cell = $tr.find('td[data-column="carton"]').first();
                    if ( $cell.length ) {
                        raw = ($cell.attr('title') || '').toString();
                        if ( ! raw ) {
                            raw = ($cell.text() || '').toString();
                        }
                    }
                }
                var ranges = sopGoodsinParseCartonRanges(raw);
                $tr.data('sopCartonRanges', ranges);
                return ranges;
            }

            function sopGoodsinRowMatchesCartonRanges($tr, queryRanges) {
                var rowRanges = sopGoodsinGetRowCartonRanges($tr);
                if ( ! rowRanges.length || ! queryRanges.length ) {
                    return false;
                }
                for ( var i = 0; i < queryRanges.length; i++ ) {
                    var q = queryRanges[i];
                    for ( var r = 0; r < rowRanges.length; r++ ) {
                        var row = rowRanges[r];
                        if ( q.start <= row.end && q.end >= row.start ) {
                            return true;
                        }
                    }
                }
                return false;
            }

            function sopGoodsinJumpToFirstVisible(target) {
                var el = target;
                if ( typeof target === 'string' ) {
                    el = document.querySelector(target);
                }
                if ( ! el && ! target ) {
                    var firstRow = $('#sop-goodsin-lines tbody tr:visible').first();
                    if ( firstRow.length ) {
                        el = firstRow.get(0);
                    }
                }
                if ( ! el || ! el.scrollIntoView ) {
                    return;
                }
                try {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                } catch (e) {
                    try {
                        el.scrollIntoView();
                    } catch (e2) {}
                }
            }

            function sopStopScanLoop() {
                sopScanActive = false;
                if ( sopScanReadyTimer ) {
                    clearTimeout( sopScanReadyTimer );
                    sopScanReadyTimer = null;
                }
                if ( sopScanRaf ) {
                    cancelAnimationFrame( sopScanRaf );
                    sopScanRaf = null;
                }
            }

            function sopStopScanStream() {
                if ( sopScanStream ) {
                    try {
                        sopScanStream.getTracks().forEach(function(track){
                            if ( track && track.stop ) {
                                track.stop();
                            }
                        });
                    } catch (e) {}
                    sopScanStream = null;
                }
                if ( $scanVideo && $scanVideo.length ) {
                    $scanVideo[0].srcObject = null;
                }
            }

            function sopCloseScanModal() {
                sopStopScanLoop();
                sopStopScanStream();
                if ( $scanStatus && $scanStatus.length ) {
                    $scanStatus.text('');
                }
                if ( $scanModal && $scanModal.length ) {
                    $scanModal.removeClass('sop-goodsin-scan-hit');
                    $scanModal.removeClass('is-open').attr('aria-hidden', 'true');
                }
                document.documentElement.classList.remove('sop-goodsin-scan-open');
                document.body.classList.remove('sop-goodsin-scan-open');
            }

            function sopTriggerScanEnter(val) {
                if ( ! $scanInput || ! $scanInput.length ) {
                    return;
                }
                var cleaned = (val || '').toString().trim();
                if ( ! cleaned ) {
                    return;
                }
                $scanInput.val( cleaned );
                var evt = $.Event('keydown', { key: 'Enter', which: 13, keyCode: 13 });
                $scanInput.trigger(evt);
            }

            function sopGetBarcodeFormats() {
                var fallback = ['code_128'];
                if ( typeof BarcodeDetector === 'undefined' ) {
                    return Promise.resolve([]);
                }
                if ( typeof BarcodeDetector.getSupportedFormats === 'function' ) {
                    try {
                        var res = BarcodeDetector.getSupportedFormats();
                        if ( res && typeof res.then === 'function' ) {
                            return res.then(function(list){
                                if ( Array.isArray(list) && list.length ) {
                                    return list.indexOf('code_128') !== -1 ? ['code_128'] : list;
                                }
                                return fallback;
                            }).catch(function(){
                                return fallback;
                            });
                        }
                        if ( Array.isArray(res) && res.length ) {
                            return Promise.resolve( res.indexOf('code_128') !== -1 ? ['code_128'] : res );
                        }
                    } catch (e) {
                        return Promise.resolve( fallback );
                    }
                }
                return Promise.resolve( fallback );
            }

            function sopWaitForVideoReady(videoEl, cb, attempt) {
                if ( ! sopScanActive ) {
                    return;
                }
                attempt = attempt || 0;
                if ( videoEl && videoEl.readyState >= 2 && videoEl.videoWidth > 0 && videoEl.videoHeight > 0 ) {
                    cb();
                    return;
                }
                sopScanReadyTimer = setTimeout(function(){
                    sopWaitForVideoReady(videoEl, cb, attempt + 1);
                }, 120);
            }

            function sopStartScanLoop(detector) {
                var detectLoop = function(){
                    if ( ! sopScanActive ) {
                        return;
                    }
                    var videoEl = ( $scanVideo && $scanVideo.length ) ? $scanVideo[0] : null;
                    if ( ! videoEl ) {
                        sopScanRaf = requestAnimationFrame( detectLoop );
                        return;
                    }
                    if ( videoEl.readyState < 2 || ! videoEl.videoWidth || ! videoEl.videoHeight ) {
                        sopScanRaf = requestAnimationFrame( detectLoop );
                        return;
                    }
                    detector.detect( videoEl ).then(function(barcodes){
                        if ( ! sopScanActive ) {
                            return;
                        }
                        if ( barcodes && barcodes.length ) {
                            var raw = ( barcodes[0].rawValue || '' ).toString().trim();
                            var fromProductModal = !!window.__sop_goodsin_scan_from_product_modal;
                            sopScanActive = false;
                            sopStopScanLoop();
                            sopStopScanStream();
                            if ( $scanModal && $scanModal.length ) {
                                $scanModal.addClass('sop-goodsin-scan-hit');
                            }
                            sopGoodsinBeep();
                            setTimeout(function(){
                                sopCloseScanModal();
                            }, 180);
                            setTimeout(function(){
                                if ( $scanModal && $scanModal.length ) {
                                    $scanModal.removeClass('sop-goodsin-scan-hit');
                                }
                            }, 300);
                            if ( fromProductModal ) {
                                window.__sop_goodsin_scan_from_product_modal = false;
                            }
                            if ( raw ) {
                                sopTriggerScanEnter( raw );
                            }
                            return;
                        }
                        sopScanRaf = requestAnimationFrame( detectLoop );
                    }).catch(function(err){
                        if ( ! sopScanActive ) {
                            return;
                        }
                        var name = err && err.name ? err.name : '';
                        var msg = err && err.message ? err.message : '';
                        if ( name === 'InvalidStateError' || ( msg && msg.indexOf('Invalid element or state') !== -1 ) ) {
                            sopScanRaf = requestAnimationFrame( detectLoop );
                            return;
                        }
                        if ( name === 'NotSupportedError' ) {
                            if ( $scanStatus && $scanStatus.length ) {
                                $scanStatus.text('<?php echo esc_js( __( 'Camera barcode scanning is not supported on this device. Use Bluetooth scanner or type SKU.', 'sop' ) ); ?>');
                            }
                            sopStopScanLoop();
                            sopStopScanStream();
                            return;
                        }
                        if ( $scanStatus && $scanStatus.length ) {
                            $scanStatus.text( '<?php echo esc_js( __( 'Scan error: ', 'sop' ) ); ?>' + ( msg || name || err ) );
                        }
                        sopScanRaf = requestAnimationFrame( detectLoop );
                    });
                };
                sopScanActive = true;
                sopScanRaf = requestAnimationFrame( detectLoop );
            }

            function sopOpenScanModal() {
                if ( ! $scanModal || ! $scanModal.length ) {
                    return;
                }
                sopCloseScanModal();
                if ( $scanStatus && $scanStatus.length ) {
                    $scanStatus.text('');
                }
                $scanModal.addClass('is-open').attr('aria-hidden', 'false');
                document.documentElement.classList.add('sop-goodsin-scan-open');
                document.body.classList.add('sop-goodsin-scan-open');

                if ( typeof navigator === 'undefined' || ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia ) {
                    $scanStatus.text('<?php echo esc_js( __( 'Camera access is not available in this browser.', 'sop' ) ); ?>');
                    return;
                }

                if ( typeof BarcodeDetector === 'undefined' ) {
                    $scanStatus.text('<?php echo esc_js( __( 'Camera barcode scanning is not supported on this device.', 'sop' ) ); ?>');
                    return;
                }

                sopScanActive = true;

                navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } }).then(function(stream){
                    sopScanStream = stream;
                    var videoEl = ( $scanVideo && $scanVideo.length ) ? $scanVideo[0] : null;
                    if ( videoEl ) {
                        videoEl.srcObject = stream;
                        var playPromise = videoEl.play();
                        if ( playPromise && typeof playPromise.catch === 'function' ) {
                            playPromise.catch(function(){});
                        }
                    }
                    sopGetBarcodeFormats().then(function(formats){
                        var detector;
                        try {
                            detector = ( formats && formats.length ) ? new BarcodeDetector({ formats: formats }) : new BarcodeDetector();
                        } catch (e) {
                            if ( $scanStatus && $scanStatus.length ) {
                                $scanStatus.text('<?php echo esc_js( __( 'Camera barcode scanning is not supported on this device. Use Bluetooth scanner or type SKU.', 'sop' ) ); ?>');
                            }
                            sopStopScanStream();
                            sopStopScanLoop();
                            return;
                        }
                        sopWaitForVideoReady(videoEl, function(){
                            sopStartScanLoop( detector );
                        });
                    }).catch(function(){
                        sopWaitForVideoReady(videoEl, function(){
                            try {
                                sopStartScanLoop( new BarcodeDetector({ formats: ['code_128'] }) );
                            } catch (e) {
                                if ( $scanStatus && $scanStatus.length ) {
                                    $scanStatus.text('<?php echo esc_js( __( 'Camera barcode scanning is not supported on this device. Use Bluetooth scanner or type SKU.', 'sop' ) ); ?>');
                                }
                                sopStopScanStream();
                                sopStopScanLoop();
                            }
                        });
                    });
                }).catch(function(err){
                    sopStopScanLoop();
                    sopStopScanStream();
                    var name = err && err.name ? err.name : '';
                    if ( name === 'NotAllowedError' || name === 'SecurityError' ) {
                        $scanStatus.text('<?php echo esc_js( __( 'Camera permission denied. Allow camera access to scan barcodes.', 'sop' ) ); ?>');
                    } else {
                        $scanStatus.text('<?php echo esc_js( __( 'Camera access denied or unavailable.', 'sop' ) ); ?>');
                    }
                });
            }

            if ( $scanButton.length ) {
                $scanButton.on('click', function(e){
                    e.preventDefault();
                    sopOpenScanModal();
                });
            }

            $(document).on('click', '[data-sop-scan-close="1"]', function(e){
                e.preventDefault();
                sopCloseScanModal();
            });

            function sopGoodsinGetRowSearchText($tr) {
                var cached = $tr.data('sopSearchText');
                if ( cached ) {
                    return cached;
                }
                var parts = [];
                parts.push( ($tr.find('td.column-location').text() || '').toString() );
                parts.push( ($tr.find('td[data-column="sku"]').text() || '').toString() );
                parts.push( ($tr.find('.sop-goodsin-product-link').text() || '').toString() );
                var cartonText = ($tr.attr('data-carton') || '').toString();
                if ( ! cartonText ) {
                    cartonText = ($tr.find('.sop-goodsin-carton-text').text() || '').toString();
                }
                parts.push( cartonText );
                var text = parts.join(' ').toLowerCase();
                $tr.data('sopSearchText', text);
                return text;
            }

            function sopGoodsinIsCompleteClean($tr) {
                var ordered = sopGoodsinParseNumber( $tr.data('sop-ordered') );
                var stocked = sopGoodsinParseNumber( $tr.find('td[data-column="stocked"]').text() );
                var missing = sopGoodsinParseNumber( $tr.find('.sop-goodsin-missing').val() );
                var reject  = sopGoodsinParseNumber( $tr.find('.sop-goodsin-reject').val() );

                return ordered > 0 && stocked === ordered && missing === 0 && reject === 0;
            }

            function sopGoodsinApplyFilterAll() {
                var showCompleted = $showCompleted.is(':checked');
                var issuesOnly = $issuesOnly.is(':checked');
                var query = sopGoodsinNormalizeQuery( $searchInput.val() || '' );
                var terms = query ? query.split(' ') : [];
                var cartonQueryRaw = $cartonInput ? ($cartonInput.val() || '').toString().trim() : '';
                var cartonQueryNorm = sopGoodsinNormalizeQuery( cartonQueryRaw );
                var cartonRanges = sopGoodsinParseCartonRanges( cartonQueryRaw );
                var total = 0;
                var hidden = 0;
                var hiddenSearch = 0;
                var hiddenCarton = 0;
                var hiddenIssues = 0;

                $('#sop-goodsin-lines tbody tr').each(function(){
                    total++;
                    var $tr = $(this);
                    var complete = sopGoodsinIsCompleteClean($tr);
                    var matchesSearch = true;
                    if ( terms.length ) {
                        var rowText = sopGoodsinGetRowSearchText($tr);
                        for ( var i = 0; i < terms.length; i++ ) {
                            if ( rowText.indexOf( terms[i] ) === -1 ) {
                                matchesSearch = false;
                                break;
                            }
                        }
                    }

                    var rowCarton = sopGoodsinNormalizeQuery( ($tr.attr('data-carton') || $tr.find('.sop-goodsin-carton-text').text() || '') );
                    var matchesCarton = true;
                    if ( cartonQueryNorm ) {
                        if ( cartonRanges.length ) {
                            matchesCarton = sopGoodsinRowMatchesCartonRanges($tr, cartonRanges);
                        } else {
                            matchesCarton = ( rowCarton && rowCarton.indexOf( cartonQueryNorm ) !== -1 );
                        }
                    }
                    var rowMissing = sopGoodsinParseNumber( $tr.find('.sop-goodsin-missing').val() );
                    var rowReject  = sopGoodsinParseNumber( $tr.find('.sop-goodsin-reject').val() );
                    var isIssue    = ( rowMissing > 0 || rowReject > 0 );

                    var hideCompleted = ( ! showCompleted && complete );
                    var hideSearch    = ( terms.length && ! matchesSearch );
                    var hideCarton    = ( cartonQueryNorm && ! matchesCarton );
                    var hideIssues    = ( issuesOnly && ! isIssue );

                    if ( hideCompleted ) {
                        $tr.addClass('sop-goodsin-row-hidden');
                        hidden++;
                        $tr.find('input[type="checkbox"]').first().prop('checked', false);
                    } else {
                        $tr.removeClass('sop-goodsin-row-hidden');
                    }

                    if ( hideSearch ) {
                        $tr.addClass('sop-goodsin-row-search-hidden');
                        hiddenSearch++;
                        $tr.find('input[type="checkbox"]').first().prop('checked', false);
                    } else {
                        $tr.removeClass('sop-goodsin-row-search-hidden');
                    }

                    if ( hideCarton ) {
                        $tr.addClass('sop-goodsin-row-carton-hidden');
                        hiddenCarton++;
                        $tr.find('input[type="checkbox"]').first().prop('checked', false);
                    } else {
                        $tr.removeClass('sop-goodsin-row-carton-hidden');
                    }

                    if ( hideIssues ) {
                        $tr.addClass('sop-goodsin-row-issues-hidden');
                        hiddenIssues++;
                        $tr.find('input[type="checkbox"]').first().prop('checked', false);
                    } else {
                        $tr.removeClass('sop-goodsin-row-issues-hidden');
                    }
                });

                var visible = total - hidden - hiddenSearch - hiddenCarton - hiddenIssues;
                var summary = 'Showing ' + visible + ' of ' + total + ' lines (' + hidden + ' completed hidden';
                if ( hiddenSearch ) {
                    summary += ', ' + hiddenSearch + ' filtered by search';
                }
                if ( hiddenCarton ) {
                    summary += ', ' + hiddenCarton + ' filtered by carton';
                }
                if ( hiddenIssues ) {
                    summary += ', ' + hiddenIssues + ' filtered by issues';
                }
                summary += ')';
                $filterSummary.text( summary );
                if ( $productModal.length && $productModal.hasClass('is-open') ) {
                    sopGoodsinProductModalUpdateNavButtons();
                }
            }

            function sopGoodsinScheduleFilterRefresh() {
                if ( sopGoodsinFilterTimer ) {
                    clearTimeout( sopGoodsinFilterTimer );
                }
                sopGoodsinFilterTimer = setTimeout( sopGoodsinApplyFilterAll, 50 );
            }

            function buildPayload(actionType) {
                var lines = [];
                $('#sop-goodsin-lines tbody tr').each(function(){
                    var $tr = $(this);
                    var lineId = parseInt($tr.data('line-id'), 10) || 0;
                    var productId = parseInt($tr.data('product-id'), 10) || 0;
                    if (!lineId || !productId) { return; }

                    lines.push({
                        line_id: lineId,
                        product_id: productId,
                        received_qty: $tr.find('.sop-goodsin-received').val(),
                        goods_in_missing_qty: $tr.find('.sop-goodsin-missing').val(),
                        goods_in_reject_qty: $tr.find('.sop-goodsin-reject').val(),
                        reject_reason: $tr.find('.sop-goodsin-reject-reason').val() || '',
                        notes: $tr.find('.sop-goodsin-notes').val() || '',
                        selected: $tr.find('.sop-goodsin-select').is(':checked')
                    });
                });

                return {
                    sheet_id: <?php echo (int) $sheet_id; ?>,
                    action: actionType,
                    lines: lines
                };
            }

            var goodsinAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var goodsinAjaxNonce = '<?php echo esc_js( wp_create_nonce( 'sop_goodsin_ajax' ) ); ?>';
            var $progressWrap = $('#sop-goodsin-apply-progress');
            var $progressText = $progressWrap.find('.sop-goodsin-apply-progress-text');
            var $progressBar = $progressWrap.find('.sop-goodsin-apply-progress-bar-inner');
            var $progressStatus = $progressWrap.find('.sop-goodsin-apply-progress-status');
            var $progressSkipped = $progressWrap.find('.sop-goodsin-apply-progress-skipped');

            function setAction(actionType) {
                if (actionType === 'save') {
                    $actionField.val('sop_goodsin_save');
                } else if (actionType === 'complete') {
                    $actionField.val('sop_goodsin_complete');
                } else {
                    $actionField.val('sop_goodsin_apply_stock');
                }
            }

            function openCompleteModal() {
                if ( ! $completeModal.length ) {
                    return;
                }
                $completeConfirmCheck.prop('checked', false);
                $completeConfirmButton.prop('disabled', true);
                $confirmCompleteField.val('0');
                $completeModalBackdrop.show().attr('aria-hidden', 'false');
                $completeModal.show().attr('aria-hidden', 'false');
                document.documentElement.classList.add('sop-goodsin-modal-open');
                document.body.classList.add('sop-goodsin-modal-open');
                sopGoodsinSyncVisualViewportVar();
            }

            function closeCompleteModal(resetConfirmFlag) {
                if ( typeof resetConfirmFlag === 'undefined' ) {
                    resetConfirmFlag = true;
                }
                if ( ! $completeModal.length ) {
                    return;
                }
                $completeModal.hide().attr('aria-hidden', 'true');
                $completeModalBackdrop.hide().attr('aria-hidden', 'true');
                $completeConfirmCheck.prop('checked', false);
                $completeConfirmButton.prop('disabled', true);
                if ( resetConfirmFlag ) {
                    $confirmCompleteField.val('0');
                }
                if ( ! $productModal.hasClass('is-open') && ! $notesModal.is(':visible') && ! $infoModal.is(':visible') ) {
                    document.documentElement.classList.remove('sop-goodsin-modal-open');
                    document.body.classList.remove('sop-goodsin-modal-open');
                }
            }

            function sopGoodsinApplyStockSequential(payloadObj) {
                var selectedLines = payloadObj.lines.filter(function(line) {
                    return !!line.selected;
                });

                if (!selectedLines.length) {
                    alert('<?php echo esc_js( __( 'Select at least one line to apply stock.', 'sop' ) ); ?>');
                    return;
                }

                var total = selectedLines.length;
                var applied = 0;
                var skipped = 0;
                var errors = 0;
                var skippedItems = [];

                $('.sop-goodsin-submit').prop('disabled', true);

                $progressWrap.show();
                $progressText.text('Applying stock: 0 of ' + total);
                $progressBar.css('width', '0%');
                $progressStatus.text('');
                $progressSkipped.empty();

                selectedLines.forEach(function(line) {
                    $('#sop-goodsin-lines tr[data-line-id="' + line.line_id + '"]')
                        .removeClass('sop-goodsin-row-applied sop-goodsin-row-skipped sop-goodsin-row-error');
                });

                function updateProgress(done) {
                    var percent = total > 0 ? Math.round((done / total) * 100) : 0;
                    $progressText.text('Applying stock: ' + done + ' of ' + total);
                    $progressBar.css('width', percent + '%');
                }

                function finishApply() {
                    $('.sop-goodsin-submit').prop('disabled', false);
                    updateProgress(total);
                    $progressStatus.text('Done. Applied: ' + applied + ', Skipped: ' + skipped + ', Errors: ' + errors + '.');
                    if (skippedItems.length) {
                        var html = '<strong>Skipped lines</strong><br />';
                        skippedItems.forEach(function(item) {
                            html += (item.sku || 'Unknown SKU') + ' — ' + item.reason;
                            if (item.message) {
                                html += ' (' + item.message + ')';
                            }
                            html += '<br />';
                        });
                        $progressSkipped.html(html);
                    }
                }

                function applyNext(index) {
                    if (index >= total) {
                        finishApply();
                        return;
                    }

                    var line = selectedLines[index];
                    $.ajax({
                        url: goodsinAjaxUrl,
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'sop_goodsin_apply_stock_line',
                            nonce: goodsinAjaxNonce,
                            sheet_id: payloadObj.sheet_id,
                            reset_report: index === 0 ? 1 : 0,
                            line_json: JSON.stringify(line)
                        }
                    }).done(function(resp) {
                        var status = 'error';
                        var appliedQty = 0;
                        var isComplete = false;
                        var outstanding = null;
                        if (resp && resp.success && resp.data && resp.data.result) {
                            status = resp.data.result.status || 'noop';
                            appliedQty = parseFloat(resp.data.result.applied_qty) || 0;
                            if (resp.data.result.sku) {
                                line.sku = resp.data.result.sku;
                            }
                            if (typeof resp.data.result.is_complete !== 'undefined') {
                                isComplete = !!resp.data.result.is_complete;
                            }
                            if (typeof resp.data.result.outstanding_qty !== 'undefined') {
                                outstanding = parseFloat(resp.data.result.outstanding_qty);
                                if (!isNaN(outstanding)) {
                                    isComplete = outstanding <= 0.0001;
                                }
                            }
                        } else {
                            errors++;
                        }

                        var $row = $('#sop-goodsin-lines tr[data-line-id="' + line.line_id + '"]');
                        if ($row.length) {
                            if (isComplete) {
                                $row.addClass('sop-goodsin-line-complete');
                            } else {
                                $row.removeClass('sop-goodsin-line-complete');
                            }
                        }
                        if (status === 'applied') {
                            applied++;
                            $row.addClass('sop-goodsin-row-applied');
                            var appliedDirection = (resp && resp.data && resp.data.result && resp.data.result.applied_direction) ? resp.data.result.applied_direction : 'increase';
                            var appliedSign = (appliedDirection === 'decrease') ? '-' : '+';
                            $progressStatus.text('Line ' + line.line_id + ' applied (' + appliedSign + appliedQty + ').');
                        } else if (status === 'noop' || status === 'skipped') {
                            skipped++;
                            $row.addClass('sop-goodsin-row-skipped');
                            var reasonLabel = (resp && resp.data && resp.data.result && resp.data.result.reason_label) ? resp.data.result.reason_label : (resp && resp.data && resp.data.result && resp.data.result.reason ? resp.data.result.reason : 'Skipped');
                            var message = (resp && resp.data && resp.data.result && resp.data.result.message) ? resp.data.result.message : '';
                            var skuText = line.sku || $row.data('sku') || '';
                            skippedItems.push({
                                line_id: line.line_id,
                                sku: skuText,
                                reason: reasonLabel,
                                message: message
                            });
                            $row.find('.sop-goodsin-skip-badge').attr('title', 'Skipped: ' + skuText + ' — ' + reasonLabel + (message ? ': ' + message : ''));
                            $progressStatus.text('Line ' + line.line_id + ' skipped.');
                        } else {
                            errors++;
                            $row.addClass('sop-goodsin-row-error');
                            $progressStatus.text('Line ' + line.line_id + ' error.');
                        }
                    }).fail(function() {
                        errors++;
                        $('#sop-goodsin-lines tr[data-line-id="' + line.line_id + '"]').addClass('sop-goodsin-row-error');
                        $progressStatus.text('Line ' + line.line_id + ' error.');
                    }).always(function() {
                        updateProgress(index + 1);
                        applyNext(index + 1);
                    });
                }

                applyNext(0);
            }

            $('.sop-goodsin-submit').on('click', function(){
                var actionType = $(this).data('action') || 'save';
                if (actionType === 'apply_stock') {
                    $confirmCompleteField.val('0');
                    var payloadObj = buildPayload(actionType);
                    sopGoodsinApplyStockSequential(payloadObj);
                    return;
                }
                if (actionType === 'complete') {
                    openCompleteModal();
                    return;
                }
                $confirmCompleteField.val('0');
                setAction(actionType);
                var payloadObj = buildPayload(actionType);
                $payload.val(JSON.stringify(payloadObj));
                dirty = false;
                $form.trigger('submit');
            });

            $completeConfirmCheck.on('change', function(){
                $completeConfirmButton.prop('disabled', ! $(this).is(':checked'));
            });

            $completeConfirmButton.on('click', function(e){
                e.preventDefault();
                if ( ! $completeConfirmCheck.is(':checked') ) {
                    return;
                }
                $confirmCompleteField.val('1');
                closeCompleteModal(false);
                setAction('complete');
                var payloadObj = buildPayload('complete');
                $payload.val(JSON.stringify(payloadObj));
                dirty = false;
                $form.trigger('submit');
            });

            $(document).on('click', '[data-sop-complete-close="1"]', function(e){
                e.preventDefault();
                closeCompleteModal();
            });

            $completeModalBackdrop.on('click', function(e){
                e.preventDefault();
                closeCompleteModal();
            });

            $('#sop-goodsin-select-all').on('change', function(){
                var checked = $(this).is(':checked');
                $('.sop-goodsin-select').prop('checked', checked);
                markDirty();
            });

            $('#sop-goodsin-lines').on('input change', 'input, select, textarea', markDirty);

            $(window).on('beforeunload', function(e){
                if (!dirty) {
                    return;
                }
                e.preventDefault();
                e.returnValue = '';
            });

            function updateRowSortData($tr) {
                if (!$tr || !$tr.length) {
                    return;
                }
                var receivedVal = parseFloat($tr.find('.sop-goodsin-received').val()) || 0;
                var missingVal = parseFloat($tr.find('.sop-goodsin-missing').val()) || 0;
                var rejectVal = parseFloat($tr.find('.sop-goodsin-reject').val()) || 0;
                var reasonVal = ($tr.find('.sop-goodsin-reject-reason').val() || '').toString().toLowerCase();
                var notesVal = ($tr.find('.sop-goodsin-notes').val() || '').toString().toLowerCase();
                var orderedVal = parseFloat($tr.data('sort-ordered')) || 0;
                var stockedVal = parseFloat($tr.data('sort-stocked')) || 0;
                var outstandingVal = Math.max(0, orderedVal - (stockedVal + missingVal + rejectVal));
                $tr.data('sort-received', receivedVal);
                $tr.data('sort-missing', missingVal);
                $tr.data('sort-reject', rejectVal);
                $tr.data('sort-reason', reasonVal);
                $tr.data('sort-goodsin_notes', notesVal);
                $tr.data('sort-outstanding', outstandingVal);
            }

            $('#sop-goodsin-lines').on('input change', '.sop-goodsin-received, .sop-goodsin-missing, .sop-goodsin-reject, .sop-goodsin-reject-reason, .sop-goodsin-notes', function(){
                var $tr = $(this).closest('tr');
                updateRowSortData($tr);
                markDirty();
                sopGoodsinScheduleFilterRefresh();
            });
            $('#sop-goodsin-lines').on('input change', '.sop-goodsin-carton-text, .sop-goodsin-carton', function(){
                var $tr = $(this).closest('tr');
                $tr.removeData('sopSearchText');
                sopGoodsinScheduleFilterRefresh();
            });

            function openNotesModal($tr) {
                notesActiveRow = $tr;
                var productLabel = $tr.find('.sop-goodsin-col-product .sop-goodsin-product-link').text() || ($tr.data('sort-product') || '');
                var currentNotes = $tr.find('.sop-goodsin-notes').val() || '';
                $notesModalProduct.text(productLabel);
                $notesModalEditor.val(currentNotes);
                $notesModalBackdrop.show().attr('aria-hidden', 'false');
                $notesModal.show().attr('aria-hidden', 'false');
                $notesModalEditor.focus();
                document.documentElement.classList.add('sop-goodsin-modal-open');
                document.body.classList.add('sop-goodsin-modal-open');
                sopGoodsinSyncVisualViewportVar();
            }

            function closeNotesModal() {
                notesActiveRow = null;
                $notesModal.hide().attr('aria-hidden', 'true');
                $notesModalBackdrop.hide().attr('aria-hidden', 'true');
                if ( ! $productModal.hasClass('is-open') && ! $infoModal.is(':visible') ) {
                    document.documentElement.classList.remove('sop-goodsin-modal-open');
                    document.body.classList.remove('sop-goodsin-modal-open');
                }
            }

            function openInfoModal(titleText, bodyText, bodyIsHtml) {
                if ( ! $infoModal.length ) {
                    return;
                }
                $infoModalTitle.text(titleText || '');
                if ( bodyIsHtml ) {
                    $infoModalBody.html(bodyText || '');
                } else {
                    $infoModalBody.text(bodyText || '');
                }
                $infoModalBackdrop.show().attr('aria-hidden', 'false');
                $infoModal.show().attr('aria-hidden', 'false');
                document.documentElement.classList.add('sop-goodsin-modal-open');
                document.body.classList.add('sop-goodsin-modal-open');
                sopGoodsinSyncVisualViewportVar();
            }

            function closeInfoModal() {
                if ( ! $infoModal.length ) {
                    return;
                }
                $infoModal.hide().attr('aria-hidden', 'true');
                $infoModalBackdrop.hide().attr('aria-hidden', 'true');
                $infoModalTitle.text('');
                $infoModalBody.empty();
                if ( ! $productModal.hasClass('is-open') && ! $notesModal.is(':visible') ) {
                    document.documentElement.classList.remove('sop-goodsin-modal-open');
                    document.body.classList.remove('sop-goodsin-modal-open');
                }
            }

            function sopGoodsinNotesPreviewIsTruncated( el ) {
                if ( ! el ) {
                    return false;
                }
                return el.scrollHeight > el.clientHeight + 1;
            }

            function sopGoodsinInitNotesPreviews() {
                $('#sop-goodsin-lines').find('.sop-notes-preview').each(function() {
                    var $el = $(this);
                    if ( sopGoodsinNotesPreviewIsTruncated( this ) ) {
                        $el.addClass('is-truncated');
                    } else {
                        $el.removeClass('is-truncated');
                    }
                });
            }

            function saveNotesModal() {
                if ( ! notesActiveRow ) { return; }
                var newNotes = $notesModalEditor.val() || '';
                var $textarea = notesActiveRow.find('.sop-goodsin-notes');
                var $preview = notesActiveRow.find('.sop-goodsin-notes-preview');
                $textarea.val(newNotes);
                $preview.text(newNotes);
                $preview.attr('title', newNotes);
                updateRowSortData(notesActiveRow);
                markDirty();
                sopGoodsinScheduleFilterRefresh();
                closeNotesModal();
            }

            $('#sop-goodsin-lines').on('click', '.sop-goodsin-notes-preview, .sop-goodsin-notes-edit, .sop-goodsin-notes-preview-box', function(e){
                e.preventDefault();
                var $tr = $(this).closest('tr');
                var $textarea = $tr.find('.sop-goodsin-notes');
                if ( $textarea.is(':disabled') || $tr.find('.sop-goodsin-notes-edit').length === 0 ) {
                    return;
                }
                openNotesModal($tr);
            });

            $notesModalSave.on('click', function(e){
                e.preventDefault();
                saveNotesModal();
            });

            $notesModalBackdrop.on('click', function(e){
                e.preventDefault();
                closeNotesModal();
            });

            $notesModalClose.on('click', function(e){
                e.preventDefault();
                closeNotesModal();
            });

            sopGoodsinInitNotesPreviews();
            $(window).on('resize', sopGoodsinInitNotesPreviews);

            $('#sop-goodsin-lines').on('click', '.sop-notes-preview.is-truncated', function(e){
                e.preventDefault();
                var $el = $(this);
                var titleText = $el.data('sop-notes-title') || '';
                openInfoModal(titleText, $el.html() || '', true);
            });

            $(document).on('click', '[data-sop-info-close="1"]', function(e){
                e.preventDefault();
                closeInfoModal();
            });

            $infoModalBackdrop.on('click', function(e){
                e.preventDefault();
                closeInfoModal();
            });

            $(document).on('keydown', function(e){
                if ( 27 === e.which && $notesModal.is(':visible') ) {
                    e.preventDefault();
                    closeNotesModal();
                }
                if ( 27 === e.which && $infoModal.is(':visible') ) {
                    e.preventDefault();
                    closeInfoModal();
                }
                if ( 27 === e.which && $completeModal.is(':visible') ) {
                    e.preventDefault();
                    closeCompleteModal();
                }
                if ( e.key === 'Enter' && $(e.target).is($searchInput) ) {
                    e.preventDefault();
                    sopGoodsinJumpToFirstVisible();
                }
            });

            // Filter toggle persistence + initial apply.
            (function(){
                var stored = null;
                try {
                    stored = window.localStorage.getItem('sop_goodsin_show_completed');
                } catch (e) {}
                if ( stored === '1' ) {
                    $showCompleted.prop('checked', true);
                }
            })();

            $showCompleted.on('change', function(){
                try {
                    window.localStorage.setItem('sop_goodsin_show_completed', $(this).is(':checked') ? '1' : '0');
                } catch (e) {}
                sopGoodsinApplyFilterAll();
            });

            // Search input persistence + behaviour.
            (function(){
                var stored = null;
                try {
                    stored = window.localStorage.getItem('sop_goodsin_search_query');
                } catch (e) {}
                if ( stored ) {
                    $searchInput.val( stored );
                }
            })();

            // Carton input persistence + behaviour.
            var $cartonInput = $('#sop-goodsin-carton');
            var $cartonClear = $('#sop-goodsin-carton-clear');
            (function(){
                var storedCarton = null;
                try {
                    storedCarton = window.localStorage.getItem('sop_goodsin_carton_filter');
                } catch (e) {}
                if ( storedCarton ) {
                    $cartonInput.val( storedCarton );
                }
            })();

            $searchInput.on('input', function(){
                var val = $(this).val() || '';
                try {
                    window.localStorage.setItem('sop_goodsin_search_query', val);
                } catch (e) {}
                sopGoodsinScheduleFilterRefresh();
            });

            // Issues-only persistence.
            (function(){
                var storedIssues = null;
                try {
                    storedIssues = window.localStorage.getItem('sop_goodsin_issues_only');
                } catch (e) {}
                if ( storedIssues === '1' ) {
                    $issuesOnly.prop('checked', true);
                }
            })();

            $issuesOnly.on('change', function(){
                try {
                    window.localStorage.setItem('sop_goodsin_issues_only', $(this).is(':checked') ? '1' : '0');
                } catch (e) {}
                sopGoodsinScheduleFilterRefresh();
            });

            $searchClear.on('click', function(e){
                e.preventDefault();
                $searchInput.val('');
                try {
                    window.localStorage.removeItem('sop_goodsin_search_query');
                } catch (e2) {}
                sopGoodsinScheduleFilterRefresh();
                $searchInput.focus();
            });

            $scanInput.on('keydown', function(e){
                if ( e.key !== 'Enter' ) {
                    return;
                }
                e.preventDefault();
                var scanVal = ($scanInput.val() || '').toString();
                var scanValTrim = scanVal.trim();
                if ( ! scanVal ) {
                    return;
                }
                setTimeout(function(){
                    sopGoodsinOpenProductModalForSku( scanValTrim );
                }, 20);
                $scanInput.val('');
            });

            $cartonInput.on('input', function(){
                var val = $(this).val() || '';
                try {
                    window.localStorage.setItem('sop_goodsin_carton_filter', val);
                } catch (e) {}
                sopGoodsinScheduleFilterRefresh();
            });

            $cartonInput.on('keydown', function(e){
                if ( e.key !== 'Enter' ) {
                    return;
                }
                e.preventDefault();
                var val = $(this).val() || '';
                try {
                    window.localStorage.setItem('sop_goodsin_carton_filter', val);
                } catch (e2) {}
                sopGoodsinApplyFilterAll();
                sopGoodsinJumpToFirstVisible();
                if ( $scanInput.length ) {
                    $scanInput.focus().select();
                } else {
                    $searchInput.focus().select();
                }
            });

            // Initial filter apply after loading persisted states.
            sopGoodsinApplyFilterAll();

            $cartonClear.on('click', function(e){
                e.preventDefault();
                $cartonInput.val('');
                try {
                    window.localStorage.removeItem('sop_goodsin_carton_filter');
                } catch (e2) {}
                sopGoodsinScheduleFilterRefresh();
                if ( $scanInput.length ) {
                    $scanInput.focus().select();
                } else {
                    $searchInput.focus().select();
                }
            });

            function sopGoodsinGetSortStorageKey() {
                var sheetId = parseInt( $('input[name="sop_sheet_id"]').first().val(), 10 ) || 0;
                var params = new URLSearchParams(window.location.search || '');
                if ( ! sheetId ) {
                    sheetId = parseInt(params.get('sheet_id'), 10) || 0;
                }
                var sessionId = parseInt(params.get('session_id'), 10) || 0;
                if ( sheetId > 0 ) {
                    return 'sop_goodsin_sort_sheet_' + sheetId;
                }
                if ( sessionId > 0 ) {
                    return 'sop_goodsin_sort_session_' + sessionId;
                }
                return 'sop_goodsin_sort';
            }

            function sopGoodsinSaveSortState(sortKey, isAsc) {
                if ( ! sortKey ) {
                    return;
                }
                try {
                    var payload = JSON.stringify({ key: sortKey, dir: isAsc ? 'asc' : 'desc' });
                    localStorage.setItem(sopGoodsinGetSortStorageKey(), payload);
                } catch (e) {
                }
            }

            function sopGoodsinLoadSortState() {
                try {
                    var raw = localStorage.getItem(sopGoodsinGetSortStorageKey());
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

            function sopGoodsinApplySort($th, sortKey, isAsc) {
                if ( ! sortKey || ! $th || ! $th.length ) {
                    return;
                }
                var sortType = $th.data('sort-type') || 'text';
                var newDir = isAsc ? 'asc' : 'desc';

                $('.sop-goodsin-sort').removeClass('sorted-asc sorted-desc');
                $th.addClass(newDir === 'asc' ? 'sorted-asc' : 'sorted-desc');

                var $rows = $('#sop-goodsin-lines tbody tr');
                var rowsArr = $rows.get();

                rowsArr.sort(function(a, b){
                    var $rowA = $(a);
                    var $rowB = $(b);
                    var aVal = $rowA.data('sort-' + sortKey);
                    var bVal = $rowB.data('sort-' + sortKey);
                    if ( sortKey === 'carton' ) {
                        var aCartonKey = parseInt( $rowA.find('td[data-column="carton"]').attr('data-carton-sort'), 10 );
                        var bCartonKey = parseInt( $rowB.find('td[data-column="carton"]').attr('data-carton-sort'), 10 );
                        if ( isNaN( aCartonKey ) ) { aCartonKey = 999999999; }
                        if ( isNaN( bCartonKey ) ) { bCartonKey = 999999999; }
                        if ( aCartonKey !== bCartonKey ) {
                            return newDir === 'asc' ? ( aCartonKey - bCartonKey ) : ( bCartonKey - aCartonKey );
                        }
                    }

                    if (sortType === 'number') {
                        aVal = parseFloat(aVal) || 0;
                        bVal = parseFloat(bVal) || 0;
                    } else {
                        aVal = (aVal || '').toString().toLowerCase();
                        bVal = (bVal || '').toString().toLowerCase();
                    }

                    if (aVal < bVal) {
                        return newDir === 'asc' ? -1 : 1;
                    }
                    if (aVal > bVal) {
                        return newDir === 'asc' ? 1 : -1;
                    }
                    return 0;
                });

                $('#sop-goodsin-lines tbody').append(rowsArr);
            }

            $('#sop-goodsin-lines').on('click', '.sop-goodsin-sort', function(){
                var $th = $(this);
                var sortKey = $th.data('sort-key') || $th.data('sort');
                var isAsc = !$th.hasClass('sorted-asc');
                sopGoodsinApplySort($th, sortKey, isAsc);
                sopGoodsinSaveSortState(sortKey, isAsc);
            });

            if ( $('#sop-goodsin-lines').length ) {
                var savedSort = sopGoodsinLoadSortState();
                if ( savedSort && savedSort.key ) {
                    var $savedTh = $('#sop-goodsin-lines').find('th[data-sort-key="' + savedSort.key + '"], th[data-sort="' + savedSort.key + '"]').first();
                    if ( $savedTh.length ) {
                        sopGoodsinApplySort($savedTh, savedSort.key, savedSort.dir === 'asc');
                    }
                }
            }

            function sopGoodsinUpdateColumnsToggleLabel() {
                if (!$columnsToggle.length || !$columnCheckboxes.length) {
                    return;
                }
                var checkedCount = $columnCheckboxes.filter(':checked').length;
                $columnsToggle.text(checkedCount + ' ' + '<?php echo esc_js( __( 'columns selected', 'sop' ) ); ?>');
            }

            function sopGoodsinApplyColumnVisibility() {
                $columnCheckboxes.each(function(){
                    var $cb = $(this);
                    var col = $cb.data('column');
                    if (!col) { return; }
                    var show = $cb.is(':checked');
                    $('#sop-goodsin-lines [data-column="' + col + '"]').toggle(show);
                });
            }

            $columnsToggle.on('click', function(e){
                e.preventDefault();
                var isOpen = $columnsWrapper.hasClass('is-open');
                $columnsWrapper.toggleClass('is-open', !isOpen);
                $columnsToggle.attr('aria-expanded', !isOpen);
                $columnsWrapper.find('.sop-goodsin-columns-popover').attr('aria-hidden', isOpen);
            });

            $columnCheckboxes.on('change', function(){
                sopGoodsinUpdateColumnsToggleLabel();
                sopGoodsinApplyColumnVisibility();
            });

            $('#sop-goodsin-lines').on('keydown', '.sop-goodsin-received, .sop-goodsin-missing, .sop-goodsin-reject', function(e){
                if ( e.key !== 'Enter' && e.which !== 13 ) {
                    return;
                }
                e.preventDefault();
                var $tr = $(this).closest('tr');
                $tr.find('td.check-column input.sop-goodsin-select').first().prop('checked', true);
                updateRowSortData($tr);
                markDirty();
                sopGoodsinScheduleFilterRefresh();
                if ( $scanInput.length ) {
                    $scanInput.focus().select();
                } else {
                    $searchInput.focus().select();
                }
            });

            $(document).on('click', function(e){
                if (!$(e.target).closest('.sop-goodsin-columns').length) {
                    $columnsWrapper.removeClass('is-open');
                    $columnsToggle.attr('aria-expanded', 'false');
                    $columnsWrapper.find('.sop-goodsin-columns-popover').attr('aria-hidden', 'true');
                }
            });

            sopGoodsinUpdateColumnsToggleLabel();
            sopGoodsinApplyColumnVisibility();
            $('#sop-goodsin-lines tbody tr').each(function(){ updateRowSortData($(this)); });
            sopGoodsinApplyFilterAll();

            function sopGoodsinProductModalBeginScanNext() {
                var isMobile = window.matchMedia && window.matchMedia('(max-width: 782px)').matches;
                var canCamera = typeof navigator !== 'undefined' && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && typeof BarcodeDetector !== 'undefined';
                if ( isMobile && canCamera ) {
                    window.__sop_goodsin_scan_from_product_modal = true;
                    sopOpenScanModal();
                    return;
                }
                window.__sop_goodsin_scan_from_product_modal = false;
                if ( $scanInput.length ) {
                    $scanInput.focus().select();
                } else {
                    $searchInput.focus().select();
                }
            }

            function sopGoodsinGetRowModalData($tr) {
                var name = ($tr.data('productName') || '').toString();
                var sku = ($tr.data('sku') || '').toString();
                var location = ($tr.data('location') || '').toString();
                var cartonVal = ($tr.attr('data-carton') || '').toString().trim();
                if ( ! cartonVal ) {
                    cartonVal = ($tr.find('.sop-goodsin-carton-text').text() || '').toString().trim();
                }
                var cartonDataAttr = ($tr.attr('data-carton') || '').toString().trim();
                var cartonCellText = ($tr.find('td[data-column="carton"]').text() || '').toString().trim();
                var cartonFallback = ($cartonInput.length ? $cartonInput.val() : '');
                cartonFallback = (cartonFallback || '').toString().trim();
                var carton = cartonVal || cartonDataAttr || cartonCellText || cartonFallback || '\u2014';
                var stockRaw = $tr.attr('data-stock-qty');
                var stockVal = null;
                if ( typeof stockRaw !== 'undefined' && stockRaw !== '' ) {
                    stockVal = parseInt( stockRaw, 10 );
                    if ( isNaN( stockVal ) ) {
                        stockVal = null;
                    }
                }
                var editUrl = ($tr.data('editUrl') || '').toString();
                var imageUrl = ($tr.data('imageUrl') || '').toString();
                var orderedVal = parseFloat($tr.data('sopOrdered')) || parseFloat($tr.data('sort-ordered')) || 0;
                var receivedVal = parseFloat($tr.find('.sop-goodsin-received').val()) || 0;
                var missingVal = parseFloat($tr.find('.sop-goodsin-missing').val()) || 0;
                var rejectVal = parseFloat($tr.find('.sop-goodsin-reject').val()) || 0;
                var stockedVal = parseFloat($tr.find('td[data-column="stocked"]').text()) || parseFloat($tr.data('sort-stocked')) || 0;
                var outstandingVal = Math.max(0, orderedVal - receivedVal - missingVal - rejectVal);
                var productNotesHtml = ($tr.find('td[data-column="product_notes"] .sop-notes-preview').html() || '').toString();
                var internalNotesHtml = ($tr.find('td[data-column="internal_product_notes"] .sop-notes-preview').html() || '').toString();
                var productNotesText = ($tr.find('td[data-column="product_notes"] .sop-notes-preview').text() || '').toString().trim();
                var internalNotesText = ($tr.find('td[data-column="internal_product_notes"] .sop-notes-preview').text() || '').toString().trim();
                var orderNotes = ($tr.find('td[data-column="order_notes"] .sop-goodsin-notes-text').text() || '').toString().trim();
                var bufferRaw = $tr.attr('data-buffer-target');
                var bufferTarget = null;
                if ( typeof bufferRaw !== 'undefined' && bufferRaw !== '' ) {
                    bufferTarget = parseFloat( bufferRaw );
                    if ( isNaN( bufferTarget ) ) {
                        bufferTarget = null;
                    }
                }

                if ( ! name ) {
                    name = ($tr.find('.sop-goodsin-product-link').text() || '').toString().trim();
                }
                if ( ! location ) {
                    location = ($tr.find('td[data-column="location"]').text() || '').toString().trim();
                }
                if ( ! sku ) {
                    sku = ($tr.find('td[data-column="sku"]').text() || '').toString().trim();
                }

                return {
                    product_name: name,
                    sku: sku,
                    image_url: imageUrl,
                    edit_url: editUrl,
                    carton_no: carton,
                    location: location,
                    stock_qty: stockVal,
                    qty_ordered: orderedVal,
                    qty_received: receivedVal,
                    added_to_stock: stockedVal,
                    outstanding: outstandingVal,
                    product_notes_text: productNotesText,
                    internal_notes_text: internalNotesText,
                    product_notes_html: productNotesHtml,
                    internal_notes_html: internalNotesHtml,
                    buffer_target: bufferTarget,
                    order_notes_text: orderNotes
                };
            }

            function sopGoodsinRenderProductModal(data) {
                if ( ! $productModal.length ) {
                    return;
                }
                var nameText = data.product_name || '<?php echo esc_js( __( '(Unknown product)', 'sop' ) ); ?>';
                var skuText = data.sku ? ('SKU: ' + data.sku) : '';
                var cartonValue = (data.carton_no || '').toString().trim();
                if ( ! cartonValue ) {
                    cartonValue = '\u2014';
                }
                var cartonText = '<?php echo esc_js( __( 'Carton No.:', 'sop' ) ); ?> ' + cartonValue;
                var locationText = data.location ? ('<?php echo esc_js( __( 'Location:', 'sop' ) ); ?> ' + data.location) : '';
                var stockQty = data.stock_qty;
                var stockLabel = '\u2014';
                if ( stockQty !== null && stockQty !== '' && typeof stockQty !== 'undefined' ) {
                    var parsedStock = parseInt( stockQty, 10 );
                    if ( ! isNaN( parsedStock ) ) {
                        stockLabel = parsedStock;
                    }
                }
                var stockText = '<?php echo esc_js( __( 'Current stock:', 'sop' ) ); ?> ' + stockLabel;
                var bufferText = '';
                if ( data.buffer_target !== null && data.buffer_target !== '' && typeof data.buffer_target !== 'undefined' ) {
                    var parsedBuffer = parseFloat( data.buffer_target );
                    if ( ! isNaN( parsedBuffer ) ) {
                        var bufferDisplay = Math.abs( parsedBuffer - Math.round( parsedBuffer ) ) < 0.01 ? Math.round( parsedBuffer ) : parsedBuffer.toFixed( 1 );
                        bufferText = '<?php echo esc_js( __( 'Buffer stock:', 'sop' ) ); ?> ' + bufferDisplay;
                    }
                }
                var qtyOrderedText = '<?php echo esc_js( __( 'Qty Ordered:', 'sop' ) ); ?> ' + ( Math.round( data.qty_ordered ) || 0 );
                var qtyReceivedText = ( Math.round( data.qty_received ) || 0 );
                var addedVal = ! isNaN( data.added_to_stock ) ? Math.round( data.added_to_stock ) : qtyReceivedText;
                var outstandingVal = Math.max(0, Math.round( data.outstanding ) || 0);

                $productModalName.text( nameText );
                $productModalSku.text( skuText );
                $productModalCarton.text( cartonText );
                $productModalLocation.text( locationText );
                $productModalStock.text( stockText ).removeClass('is-hidden');
                if ( bufferText ) {
                    $productModalBufferStock.text( bufferText ).removeClass('is-hidden');
                } else {
                    $productModalBufferStock.text( '' ).addClass('is-hidden');
                }
                $productModalQtyOrdered.text( qtyOrderedText );
                $productModalQtyValue.val( qtyReceivedText );
                $productModalAdded.text( '<?php echo esc_js( __( 'Added to stock:', 'sop' ) ); ?> ' + addedVal );
                $productModalOutstanding.text( '<?php echo esc_js( __( 'Outstanding:', 'sop' ) ); ?> ' + outstandingVal );

                $productModalStock.removeClass('is-hidden');

                if ( data.edit_url ) {
                    $productModalEdit.attr('href', data.edit_url).removeClass('is-hidden');
                } else {
                    $productModalEdit.attr('href', '#').addClass('is-hidden');
                }

                if ( data.image_url ) {
                    $productModalImage.attr('src', data.image_url).removeClass('is-hidden');
                } else {
                    $productModalImage.attr('src', '').addClass('is-hidden');
                }

                $productModalNotesProductBtn.removeClass('is-yes is-no').addClass( data.product_notes_text ? 'is-yes' : 'is-no' );
                $productModalNotesInternalBtn.removeClass('is-yes is-no').addClass( data.internal_notes_text ? 'is-yes' : 'is-no' );
                $productModalNotesOrderBtn.removeClass('is-yes is-no').addClass( data.order_notes_text ? 'is-yes' : 'is-no' );

                $productModalNotesProductBtn.data('noteHtml', data.product_notes_html || '');
                $productModalNotesInternalBtn.data('noteHtml', data.internal_notes_html || '');
                $productModalNotesOrderBtn.data('noteText', data.order_notes_text || '');

                $productModalNotesProductBtn.toggleClass('is-disabled', ! data.product_notes_text).prop('disabled', ! data.product_notes_text);
                $productModalNotesInternalBtn.toggleClass('is-disabled', ! data.internal_notes_text).prop('disabled', ! data.internal_notes_text);
                $productModalNotesOrderBtn.toggleClass('is-disabled', ! data.order_notes_text).prop('disabled', ! data.order_notes_text);

                sopGoodsinProductModalUpdateNavButtons();
            }

            function sopGoodsinGetVisibleGoodsRows() {
                return $('#sop-goodsin-lines tbody tr').filter(function(){
                    var $tr = $(this);
                    var lineId = $tr.attr('data-line-id');
                    if ( typeof lineId === 'undefined' || lineId === '' ) {
                        return false;
                    }
                    if ( isNaN( parseInt( lineId, 10 ) ) ) {
                        return false;
                    }
                    if ( $tr.hasClass('sop-goodsin-row-hidden') || $tr.hasClass('sop-goodsin-row-search-hidden') || $tr.hasClass('sop-goodsin-row-carton-hidden') || $tr.hasClass('sop-goodsin-row-issues-hidden') ) {
                        return false;
                    }
                    if ( $tr.is(':visible') ) {
                        return true;
                    }
                    if ( this.getClientRects && this.getClientRects().length ) {
                        return true;
                    }
                    return false;
                });
            }

            function sopGoodsinProductModalNavigate(delta) {
                var rows = sopGoodsinGetVisibleGoodsRows().get();
                if ( ! rows.length ) {
                    sopGoodsinProductModalUpdateNavButtons();
                    return;
                }
                var currentEl = $productModal.data('currentRowEl') || null;
                if ( ! currentEl && activeProductRow && activeProductRow.length ) {
                    currentEl = activeProductRow.get(0);
                }
                if ( ! currentEl ) {
                    var currentLineId = parseInt( $productModal.data('currentLineId'), 10 );
                    if ( currentLineId ) {
                        for ( var ri = 0; ri < rows.length; ri++ ) {
                            if ( parseInt( $( rows[ ri ] ).attr('data-line-id'), 10 ) === currentLineId ) {
                                currentEl = rows[ ri ];
                                break;
                            }
                        }
                    }
                }
                if ( ! currentEl ) {
                    sopGoodsinProductModalUpdateNavButtons();
                    return;
                }
                var currentIndex = rows.indexOf( currentEl );
                if ( currentIndex < 0 ) {
                    sopGoodsinProductModalUpdateNavButtons();
                    return;
                }
                var targetIndex = currentIndex + delta;
                if ( targetIndex < 0 || targetIndex >= rows.length ) {
                    sopGoodsinProductModalUpdateNavButtons();
                    return;
                }
                var $targetRow = $( rows[ targetIndex ] );
                sopGoodsInOpenProductModal( $targetRow );
                if ( $targetRow.length && $targetRow.get(0) && typeof $targetRow.get(0).scrollIntoView === 'function' ) {
                    $targetRow.get(0).scrollIntoView({ block: 'center' });
                }
            }

            function sopGoodsinProductModalUpdateNavButtons() {
                if ( ! $productModalHeaderPrev.length || ! $productModalHeaderNext.length ) {
                    return;
                }
                var rows = sopGoodsinGetVisibleGoodsRows().get();
                var setNavState = function( $btn, enabled ) {
                    $btn.toggleClass('is-disabled', ! enabled)
                        .attr('aria-disabled', enabled ? 'false' : 'true')
                        .removeAttr('disabled');
                    if ( enabled ) {
                        $btn.removeAttr('tabindex');
                    } else {
                        $btn.attr('tabindex', '-1');
                    }
                };
                if ( ! rows.length ) {
                    setNavState( $productModalHeaderPrev, false );
                    setNavState( $productModalHeaderNext, false );
                    return;
                }
                var currentEl = $productModal.data('currentRowEl') || null;
                if ( ! currentEl && activeProductRow && activeProductRow.length ) {
                    currentEl = activeProductRow.get(0);
                }
                if ( ! currentEl ) {
                    var currentLineId = parseInt( $productModal.data('currentLineId'), 10 );
                    if ( currentLineId ) {
                        for ( var ri = 0; ri < rows.length; ri++ ) {
                            if ( parseInt( $( rows[ ri ] ).attr('data-line-id'), 10 ) === currentLineId ) {
                                currentEl = rows[ ri ];
                                break;
                            }
                        }
                    }
                }
                if ( ! currentEl ) {
                    setNavState( $productModalHeaderPrev, false );
                    setNavState( $productModalHeaderNext, false );
                    return;
                }
                var currentIndex = rows.indexOf( currentEl );
                if ( currentIndex < 0 ) {
                    setNavState( $productModalHeaderPrev, false );
                    setNavState( $productModalHeaderNext, false );
                    return;
                }
                var hasPrev = currentIndex > 0;
                var hasNext = currentIndex < ( rows.length - 1 );
                setNavState( $productModalHeaderPrev, hasPrev );
                setNavState( $productModalHeaderNext, hasNext );
            }

            function sopGoodsinBindProductModalActions() {
                if ( ! $productModal.length || $productModal.data('sopBound') ) {
                    return;
                }
                $productModal.data('sopBound', '1');

                var getModalQtyMax = function() {
                    if ( ! activeProductRow || ! activeProductRow.length ) {
                        return 0;
                    }
                    var ordered = parseFloat( activeProductRow.data('sop-ordered') );
                    if ( isNaN( ordered ) ) {
                        ordered = 0;
                    }
                    var missing = parseFloat( activeProductRow.find('.sop-goodsin-missing').val() );
                    if ( isNaN( missing ) ) {
                        missing = 0;
                    }
                    var reject = parseFloat( activeProductRow.find('.sop-goodsin-reject').val() );
                    if ( isNaN( reject ) ) {
                        reject = 0;
                    }
                    return Math.max( 0, ordered - missing - reject );
                };

                var applyModalQtyValue = function(nextVal) {
                    if ( ! activeProductRow || ! activeProductRow.length ) {
                        return;
                    }
                    var maxVal = getModalQtyMax();
                    var value = parseInt( nextVal, 10 );
                    if ( isNaN( value ) || value < 0 ) {
                        value = 0;
                    }
                    if ( maxVal >= 0 && value > maxVal ) {
                        value = maxVal;
                    }
                    var $rowInput = activeProductRow.find('.sop-goodsin-received');
                    $productModalQtyValue.val( value );
                    $rowInput.val( value ).trigger('input').trigger('change');
                    sopGoodsinRenderProductModal( sopGoodsinGetRowModalData( activeProductRow ) );
                };

                $productModalBtnMinus.on('click', function(e){
                    e.preventDefault();
                    if ( ! activeProductRow || ! activeProductRow.length ) {
                        return;
                    }
                    var current = parseFloat( $productModalQtyValue.val() ) || 0;
                    applyModalQtyValue( Math.max( 0, current - 1 ) );
                });

                $productModalBtnPlus.on('click', function(e){
                    e.preventDefault();
                    if ( ! activeProductRow || ! activeProductRow.length ) {
                        return;
                    }
                    var current = parseFloat( $productModalQtyValue.val() ) || 0;
                    applyModalQtyValue( current + 1 );
                });

                $productModalQtyValue.on('input change', function(){
                    if ( ! activeProductRow || ! activeProductRow.length ) {
                        return;
                    }
                    applyModalQtyValue( $(this).val() );
                });

                $productModalBtnConfirm.on('click', function(e){
                    e.preventDefault();
                    sopGoodsInCloseProductModal();
                });

                $productModalBtnScanNext.on('click', function(e){
                    e.preventDefault();
                    sopGoodsinProductModalBeginScanNext();
                });

                $productModalNotesProductBtn.on('click', function(e){
                    e.preventDefault();
                    var noteHtml = ($(this).data('noteHtml') || '').toString();
                    if ( ! noteHtml ) {
                        return;
                    }
                    openInfoModal('<?php echo esc_js( __( 'Product notes', 'sop' ) ); ?>', noteHtml, true);
                });

                $productModalNotesInternalBtn.on('click', function(e){
                    e.preventDefault();
                    var noteHtml = ($(this).data('noteHtml') || '').toString();
                    if ( ! noteHtml ) {
                        return;
                    }
                    openInfoModal('<?php echo esc_js( __( 'Internal product notes', 'sop' ) ); ?>', noteHtml, true);
                });

                $productModalNotesOrderBtn.on('click', function(e){
                    e.preventDefault();
                    var noteText = ($(this).data('noteText') || '').toString();
                    if ( ! noteText ) {
                        return;
                    }
                    openInfoModal('<?php echo esc_js( __( 'Order notes', 'sop' ) ); ?>', noteText, false);
                });
            }

            function sopGoodsInCloseProductModal() {
                if ( ! $productModal.length ) {
                    return;
                }
                closeInfoModal();
                activeProductRow = null;
                window.sopGoodsinModalCurrentRow = null;
                sopScanLock = false;
                $productModal.removeClass('is-open').attr('aria-hidden', 'true');
                document.documentElement.classList.remove('sop-goodsin-modal-open');
                document.body.classList.remove('sop-goodsin-modal-open');
                $productModal.removeAttr('data-sop-current-sku');
                $productModal.removeData('currentRowEl');
                $productModal.removeData('currentLineId');
                $productModalName.text('');
                $productModalSku.text('');
                $productModalLocation.text('');
                $productModalCarton.text('');
                $productModalStock.text('').addClass('is-hidden');
                $productModalBufferStock.text('').addClass('is-hidden');
                $productModalQtyOrdered.text('');
                $productModalQtyValue.val('0');
                $productModalAdded.text('');
                $productModalOutstanding.text('');
                $productModalNotesProductBtn.removeClass('is-yes is-no is-disabled').data('noteHtml', '').prop('disabled', false);
                $productModalNotesInternalBtn.removeClass('is-yes is-no is-disabled').data('noteHtml', '').prop('disabled', false);
                $productModalNotesOrderBtn.removeClass('is-yes is-no is-disabled').data('noteText', '').prop('disabled', false);
                $productModalEdit.attr('href', '#').addClass('is-hidden');
                $productModalImage.attr('src', '').addClass('is-hidden');
                sopGoodsinProductModalUpdateNavButtons();
            }

            function sopGoodsInOpenProductModal(rowEl) {
                var $row = $(rowEl || []);
                if ( ! $row.length || ! $productModal.length ) {
                    return;
                }
                document.documentElement.classList.add('sop-goodsin-modal-open');
                document.body.classList.add('sop-goodsin-modal-open');
                sopGoodsinSyncVisualViewportVar();
                activeProductRow = $row;
                window.sopGoodsinModalCurrentRow = $row;
                $productModal.data('currentRowEl', $row.get(0));
                $productModal.data('currentLineId', parseInt( $row.attr('data-line-id'), 10 ) || 0);
                var currentSku = ($row.data('sku') || '').toString().trim();
                if ( ! currentSku ) {
                    currentSku = ($row.find('td[data-column="sku"]').text() || '').toString().trim();
                }
                $productModal.attr('data-sop-current-sku', currentSku );
                sopGoodsinBindProductModalActions();
                sopGoodsinRenderProductModal( sopGoodsinGetRowModalData( $row ) );
                $productModal.addClass('is-open').attr('aria-hidden', 'false');
            }

            function sopGoodsinOpenProductModalForSku(sku) {
                var cleaned = (sku || '').toString().trim();
                if ( ! cleaned || sopScanLock ) {
                    return false;
                }
                var $rows = sopGoodsinGetVisibleGoodsRows();
                var $match = $rows.filter(function(){
                    var rowSku = ($(this).data('sku') || '').toString().trim();
                    return rowSku.toLowerCase() === cleaned.toLowerCase();
                }).first();
                if ( ! $match.length ) {
                    if ( $scanStatus && $scanStatus.length ) {
                        $scanStatus.text('<?php echo esc_js( __( 'SKU not found in visible rows.', 'sop' ) ); ?>');
                    }
                    window.alert('<?php echo esc_js( __( 'SKU not found in current list.', 'sop' ) ); ?>');
                    return false;
                }
                sopScanLock = true;
                requestAnimationFrame(function(){
                    sopGoodsinJumpToFirstVisible( $match.get(0) );
                    sopGoodsInOpenProductModal( $match );
                });
                return true;
            }

            function sopCloseSkusModal() {
                if ( $skusModal.length ) {
                    $skusModal.removeClass('is-open').attr('aria-hidden', 'true');
                }
                if ( $skusModalContent.length ) {
                    $skusModalContent.text('');
                }
                if ( $skusModalContextName.length ) {
                    $skusModalContextName.text('');
                }
                if ( $skusModalContextSku.length ) {
                    $skusModalContextSku.text('');
                }
            }

            $(document).on('click', '.sop-supplier-skus-compact', function(){
                var full = $(this).data('full-skus') || $(this).attr('title') || '';
                var prodName = $(this).data('product-name') || '';
                var prodSku = $(this).data('product-sku') || '';
                full = (full || '').toString();
                if ( ! full ) {
                    return;
                }
                if ( $skusModalContent.length ) {
                    $skusModalContent.text( full );
                }
                if ( $skusModalContextName.length ) {
                    $skusModalContextName.text( prodName || '<?php echo esc_js( __( '(Unknown product)', 'sop' ) ); ?>' );
                }
                if ( $skusModalContextSku.length ) {
                    $skusModalContextSku.text( prodSku ? ('SKU: ' + prodSku) : '' );
                }
                if ( $skusModal.length ) {
                    $skusModal.addClass('is-open').attr('aria-hidden', 'false');
                }
            });

            $(document).on('click', '[data-sop-close="1"]', function(){
                sopCloseSkusModal();
            });

            $(document).on('click', '[data-sop-prod-close="1"]', function(e){
                e.preventDefault();
                sopGoodsInCloseProductModal();
            });

            $(document).on('click', '.sop-goodsin-pm-nav-prev', function(e){
                e.preventDefault();
                if ( $(this).hasClass('is-disabled') || $(this).attr('aria-disabled') === 'true' ) {
                    return;
                }
                sopGoodsinProductModalNavigate(-1);
            });

            $(document).on('click', '.sop-goodsin-pm-nav-next', function(e){
                e.preventDefault();
                if ( $(this).hasClass('is-disabled') || $(this).attr('aria-disabled') === 'true' ) {
                    return;
                }
                sopGoodsinProductModalNavigate(1);
            });

            $('#sop-goodsin-lines').on('click', 'td[data-column="sku"], td[data-column="product"], td[data-column="image"]', function(e){
                var $cell = $(this);
                if ( $cell.is('td[data-column="product"]') && $(e.target).is('a') ) {
                    return;
                }
                var $tr = $cell.closest('tr');
                if ( $tr.length ) {
                    e.preventDefault();
                    sopGoodsInOpenProductModal( $tr );
                }
            });

            $(document).on('keydown', function(e){
                if ( e.key === 'Escape' || e.keyCode === 27 ) {
                    sopCloseSkusModal();
                    sopCloseScanModal();
                    sopGoodsInCloseProductModal();
                }
            });
        })(jQuery);
    </script>
    <?php

    echo '</div>';
}

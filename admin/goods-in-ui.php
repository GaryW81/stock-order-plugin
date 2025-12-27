<?php
/**
 * Stock Order Plugin - Phase 5 (Goods-In v1) - Admin UI
 * File version: 1.0.35
 *
 * - Layout polish: tighter checkbox, 80x80 images (78x78 display), sortable columns, required notes columns.
 * - Remove "Add all" button; use keyed inputs to keep rows stable when sorting.
 * - Add unsaved changes warning for edited goods-in forms; column toggle dropdown; location column reposition/wrapping.
 * - Adjusted Location/SKU/Product widths and always-visible sort indicators.
 * - Confine horizontal scrolling to table container (prevent full-page scrollbar).
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
        'manage_woocommerce',
        'sop-goods-in',
        'sop_render_goods_in_page'
    );
}

/**
 * Get open goods-in sheets (locked/receiving) with outstanding totals.
 *
 * @return array
 */
function sop_goodsin_get_open_sheets() {
    global $wpdb;

    $tbl_sheets = function_exists( 'sop_get_preorder_sheet_table_name' ) ? sop_get_preorder_sheet_table_name() : '';
    $tbl_lines  = function_exists( 'sop_get_preorder_sheet_lines_table_name' ) ? sop_get_preorder_sheet_lines_table_name() : '';

    if ( '' === $tbl_sheets ) {
        $tbl_sheets = $wpdb->prefix . 'sop_preorder_sheet';
    }
    if ( '' === $tbl_lines ) {
        $tbl_lines = $wpdb->prefix . 'sop_preorder_sheet_lines';
    }

    $sql = "SELECT
                s.id,
                s.supplier_id,
                s.status,
                s.title,
                s.order_number_label,
                s.updated_at,
                COUNT(l.id) AS total_lines,
                SUM(
                    CASE
                        WHEN l.qty_owner > 0 THEN
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
                ) AS outstanding_qty
            FROM {$tbl_sheets} s
            LEFT JOIN {$tbl_lines} l ON l.sheet_id = s.id
            WHERE s.status IN ( 'locked', 'receiving' )
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
                p.post_title AS product_name
            FROM {$tbl_lines} l
            LEFT JOIN {$wpdb->posts} p ON p.ID = l.product_id
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = l.product_id AND pm.meta_key = %s
            WHERE l.sheet_id = %d
              AND l.qty_owner > 0
              AND ( pm.meta_value IS NULL OR pm.meta_value <> '1' )
            ORDER BY l.sort_index ASC, l.id ASC";

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, '_sop_preorder_removed', $sheet_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

function sop_render_goods_in_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to access Goods In.', 'sop' ) );
    }

    $sheet_id = isset( $_GET['sheet_id'] ) ? (int) $_GET['sheet_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $msg = isset( $_GET['sop_msg'] ) ? sanitize_key( wp_unslash( $_GET['sop_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Goods In', 'sop' ) . '</h1>';

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
                $text    = sprintf( __( 'Stock applied for %1$d lines (%2$d skipped).', 'sop' ), $applied, $skipped );
                break;
            case 'completed':
                $notice_class = 'notice notice-success';
                $text = __( 'Goods-in completed. Sheet marked received.', 'sop' );
                break;
            case 'cannot_complete':
                $notice_class = 'notice notice-error';
                $out = isset( $_GET['sop_outstanding_lines'] ) ? (int) $_GET['sop_outstanding_lines'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $text = sprintf( __( 'Cannot complete: %d lines still have outstanding quantity.', 'sop' ), $out );
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
                $text = __( 'Sheet must be locked or receiving to use Goods In.', 'sop' );
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
        $sheets = sop_goodsin_get_open_sheets();

        echo '<p>' . esc_html__( 'Select a locked/receiving sheet to receive stock against it.', 'sop' ) . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Sheet ID', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Supplier', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Title / Order', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Lines', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Outstanding', 'sop' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'sop' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $sheets ) ) {
            echo '<tr><td colspan="7">' . esc_html__( 'No locked/receiving sheets found.', 'sop' ) . '</td></tr>';
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
                $lines = isset( $sheet['total_lines'] ) ? (int) $sheet['total_lines'] : 0;
                $outstanding = isset( $sheet['outstanding_qty'] ) ? (float) $sheet['outstanding_qty'] : 0.0;

                $open_url = add_query_arg(
                    array(
                        'page'     => 'sop-goods-in',
                        'sheet_id' => $sid,
                    ),
                    admin_url( 'admin.php' )
                );

                echo '<tr>';
                echo '<td>' . esc_html( $sid ) . '</td>';
                echo '<td>' . esc_html( $supplier_name ) . '</td>';
                echo '<td>' . esc_html( trim( $title . ' ' . $order_label ) ) . '</td>';
                echo '<td>' . esc_html( $status ) . '</td>';
                echo '<td>' . esc_html( $lines ) . '</td>';
                echo '<td>' . esc_html( number_format_i18n( $outstanding, 0 ) ) . '</td>';
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
    echo '<h2>' . esc_html( sprintf( __( 'Sheet #%1$d (%2$s) - %3$s', 'sop' ), $sheet_id, $supplier_name, $status ) ) . '</h2>';

    $back_url = add_query_arg( array( 'page' => 'sop-goods-in' ), admin_url( 'admin.php' ) );
    echo '<p><a class="button" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Back to list', 'sop' ) . '</a></p>';

    $lines = sop_goodsin_get_sheet_lines_for_ui( $sheet_id );

    if ( empty( $lines ) ) {
        echo '<p>' . esc_html__( 'No orderable lines found for this sheet.', 'sop' ) . '</p></div>';
        return;
    }

    $form_action = admin_url( 'admin-post.php' );
    $has_issue_lines = false;
    foreach ( $lines as $line_check ) {
        $miss = isset( $line_check['goods_in_missing_qty_owner'] ) ? (float) $line_check['goods_in_missing_qty_owner'] : ( isset( $line_check['goods_in_missing_qty'] ) ? (float) $line_check['goods_in_missing_qty'] : 0.0 );
        $rej  = isset( $line_check['goods_in_reject_qty_owner'] ) ? (float) $line_check['goods_in_reject_qty_owner'] : ( isset( $line_check['goods_in_reject_qty'] ) ? (float) $line_check['goods_in_reject_qty'] : 0.0 );
        if ( $miss > 0 || $rej > 0 ) {
            $has_issue_lines = true;
            break;
        }
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
    ?>
    <form id="sop-goodsin-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
        <?php wp_nonce_field( 'sop_goodsin_action', 'sop_goodsin_nonce' ); ?>
        <input type="hidden" name="action" id="sop-goodsin-action" value="sop_goodsin_save" />
        <input type="hidden" name="sop_goodsin_payload_json" id="sop-goodsin-payload-json" value="" />

        <div class="sop-goodsin-toolbar">
            <div class="sop-goodsin-toolbar-actions">
                <button type="button" class="button button-primary sop-goodsin-submit" data-action="save"><?php esc_html_e( 'Save progress', 'sop' ); ?></button>
                <button type="button" class="button sop-goodsin-submit" data-action="apply_selected"><?php esc_html_e( 'Add selected to stock', 'sop' ); ?></button>
                <button type="button" class="button button-secondary sop-goodsin-submit" data-action="complete"><?php esc_html_e( 'Complete Goods-In', 'sop' ); ?></button>
                <?php
                $is_completed = ( isset( $sheet['status'] ) && 'received' === $sheet['status'] );
                if ( $is_completed && $has_issue_lines ) {
                    $issues_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'action'       => 'sop_export_goodsin_issues_xlsx',
                                'sop_sheet_id' => (int) $sheet_id,
                            ),
                            admin_url( 'admin-post.php' )
                        ),
                        'sop_export_goodsin_issues_xlsx',
                        'sop_goodsin_export_nonce'
                    );
                    echo '<a class="button" href="' . esc_url( $issues_url ) . '">' . esc_html__( 'Export Issues (XLSX)', 'sop' ) . '</a>';
                }
                ?>
            </div>
            <div class="sop-goodsin-toolbar-columns">
                <?php
                $columns_config = array(
                    'image'          => __( 'Image', 'sop' ),
                    'location'       => __( 'Location', 'sop' ),
                    'sku'            => __( 'SKU', 'sop' ),
                    'product'        => __( 'Product', 'sop' ),
                    'ordered'        => __( 'Ordered', 'sop' ),
                    'received'       => __( 'Received', 'sop' ),
                    'missing'        => __( 'Missing', 'sop' ),
                    'reject'         => __( 'Reject', 'sop' ),
                    'reason'         => __( 'Reason', 'sop' ),
                    'carton'         => __( 'Carton no.', 'sop' ),
                    'product_notes'  => __( 'Product notes', 'sop' ),
                    'order_notes'    => __( 'Order notes', 'sop' ),
                    'goodsin_notes'  => __( 'Goods-In Notes', 'sop' ),
                    'stocked'        => __( 'Stocked', 'sop' ),
                    'outstanding'    => __( 'Outstanding', 'sop' ),
                );
                ?>
                <div class="sop-goodsin-columns">
                    <button type="button" class="button sop-goodsin-columns-toggle" aria-expanded="false"><?php esc_html_e( 'Columns', 'sop' ); ?></button>
                    <div class="sop-goodsin-columns-popover" aria-hidden="true">
                        <div class="sop-goodsin-columns-panel">
                            <ul class="sop-goodsin-columns-list">
                                <?php foreach ( $columns_config as $col_key => $col_label ) : ?>
                                    <li>
                                        <label>
                                            <input type="checkbox" data-column="<?php echo esc_attr( $col_key ); ?>" checked="checked" />
                                            <?php echo esc_html( $col_label ); ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ( $is_completed && $has_issue_lines && is_array( $issue_summary ) && isset( $issue_summary['issue_line_count'] ) && $issue_summary['issue_line_count'] > 0 ) : ?>
            <div class="notice notice-info sop-goodsin-dispute-summary">
                <p><strong><?php esc_html_e( 'Dispute summary', 'sop' ); ?></strong></p>
                <ul>
                    <li><?php printf( esc_html__( 'Issue lines: %d', 'sop' ), (int) $issue_summary['issue_line_count'] ); ?></li>
                    <li><?php printf( esc_html__( 'Missing units: %s', 'sop' ), esc_html( number_format_i18n( $issue_summary['total_missing'], 0 ) ) ); ?></li>
                    <li><?php printf( esc_html__( 'Reject units: %s', 'sop' ), esc_html( number_format_i18n( $issue_summary['total_reject'], 0 ) ) ); ?></li>
                    <li><?php printf( esc_html__( 'Credit qty: %s', 'sop' ), esc_html( number_format_i18n( $issue_summary['total_credit_qty'], 0 ) ) ); ?></li>
                    <li><?php printf( esc_html__( 'Credit total (%s): %s', 'sop' ), esc_html( $issue_summary['supplier_currency'] ), esc_html( number_format_i18n( $issue_summary['total_credit_total_supplier'], 2 ) ) ); ?></li>
                    <?php if ( ! empty( $issue_summary['is_rmb'] ) && $issue_summary['fx_rmb_per_usd'] > 0 ) : ?>
                        <li><?php printf( esc_html__( 'Credit total (USD): %s', 'sop' ), esc_html( number_format_i18n( $issue_summary['total_credit_total_usd'], 2 ) ) ); ?></li>
                        <li><?php printf( esc_html__( 'FX used (RMB/USD): %s', 'sop' ), esc_html( number_format_i18n( $issue_summary['fx_rmb_per_usd'], 3 ) ) ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="sop-goodsin-filter">
            <label>
                <input type="checkbox" id="sop-goodsin-show-completed" />
                <?php esc_html_e( 'Show completed lines', 'sop' ); ?>
            </label>
            <label>
                <input type="checkbox" id="sop-goodsin-issues-only" />
                <?php esc_html_e( 'Issues only', 'sop' ); ?>
            </label>
            <label class="sop-goodsin-search-wrap">
                <input type="text" id="sop-goodsin-carton" placeholder="<?php esc_attr_e( 'Carton (Enter)', 'sop' ); ?>" autocomplete="off" />
                <button type="button" class="button-link" id="sop-goodsin-carton-clear"><?php esc_html_e( 'Clear', 'sop' ); ?></button>
                <input type="text" id="sop-goodsin-scan" placeholder="<?php esc_attr_e( 'Scan SKU (Enter)', 'sop' ); ?>" autocomplete="off" />
                <input type="text" id="sop-goodsin-search" placeholder="<?php esc_attr_e( 'Search SKU / Product / Carton / Location', 'sop' ); ?>" autocomplete="off" />
                <button type="button" class="button-link" id="sop-goodsin-search-clear"><?php esc_html_e( 'Clear', 'sop' ); ?></button>
            </label>
            <span id="sop-goodsin-filter-summary" aria-live="polite"></span>
        </div>

        <div class="sop-preorder-table-wrapper" aria-label="Goods-In table scroll">
        <table class="wp-list-table widefat fixed striped sop-preorder-table sop-goodsin-table" id="sop-goodsin-lines">
            <thead>
            <tr>
                <th class="check-column" data-sortable="false"><input type="checkbox" id="sop-goodsin-select-all" /></th>
                <th class="sop-goodsin-col-image" data-sortable="false" data-column="image"><?php esc_html_e( 'Image', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-location column-location" data-sort-key="location" data-sort-type="text" data-column="location"><?php esc_html_e( 'Location', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-sku" data-sort-key="sku" data-sort-type="text" data-column="sku"><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-product" data-sort-key="product" data-sort-type="text" data-column="product"><?php esc_html_e( 'Product', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="ordered" data-sort-type="number" data-column="ordered"><?php esc_html_e( 'Ordered', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-narrow" data-sort-key="received" data-sort-type="number" data-column="received"><?php esc_html_e( 'Received', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-narrow" data-sort-key="missing" data-sort-type="number" data-column="missing"><?php esc_html_e( 'Missing', 'sop' ); ?></th>
                <th class="sop-goodsin-sort sop-goodsin-col-narrow" data-sort-key="reject" data-sort-type="number" data-column="reject"><?php esc_html_e( 'Reject', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="reason" data-sort-type="text" data-column="reason"><?php esc_html_e( 'Reason', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="carton" data-sort-type="text" data-column="carton"><?php esc_html_e( 'Carton no.', 'sop' ); ?></th>
                <th class="sop-goodsin-sort" data-sort-key="product_notes" data-sort-type="text" data-column="product_notes"><?php esc_html_e( 'Product notes', 'sop' ); ?></th>
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
                $name     = isset( $line['product_name'] ) ? (string) $line['product_name'] : '';
                $location = isset( $line['location'] ) ? (string) $line['location'] : '';
                $ordered  = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : 0.0;
                $received = isset( $line['goods_in_received_qty'] ) ? (float) $line['goods_in_received_qty'] : 0.0;
                $missing  = isset( $line['goods_in_missing_qty'] ) ? (float) $line['goods_in_missing_qty'] : 0.0;
                $reject   = isset( $line['goods_in_reject_qty'] ) ? (float) $line['goods_in_reject_qty'] : 0.0;
                $reason   = isset( $line['goods_in_reject_reason'] ) ? (string) $line['goods_in_reject_reason'] : '';
                $notes    = isset( $line['goods_in_notes'] ) ? (string) $line['goods_in_notes'] : '';
                $stocked  = isset( $line['goods_in_stock_added_qty'] ) ? (float) $line['goods_in_stock_added_qty'] : 0.0;
                $product_notes = isset( $line['product_notes_owner'] ) ? (string) $line['product_notes_owner'] : '';
                $order_notes   = isset( $line['order_notes_owner'] ) ? (string) $line['order_notes_owner'] : '';
                $outstanding = max( 0.0, $ordered - $stocked - $missing - $reject );

                $product      = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
                $image_html   = '';
                if ( $product && method_exists( $product, 'get_image_id' ) ) {
                    $img_id = $product->get_image_id();
                    if ( $img_id ) {
                        $image_html = wp_get_attachment_image( $img_id, array( 100, 100 ), false, array( 'class' => 'sop-goodsin-img' ) );
                    }
                }
                if ( '' === $image_html && ! empty( $line['image_id'] ) ) {
                    $image_html = wp_get_attachment_image( (int) $line['image_id'], array( 100, 100 ), false, array( 'class' => 'sop-goodsin-img' ) );
                }
                if ( '' === $image_html ) {
                    $placeholder = function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '';
                    if ( $placeholder ) {
                        $image_html = '<img class="sop-goodsin-img" src="' . esc_url( $placeholder ) . '" alt="" />';
                    }
                }
                $product_link = $pid > 0 ? get_edit_post_link( $pid, '' ) : '';
                $carton = isset( $line['carton_number'] ) ? (string) $line['carton_number'] : '';
                ?>
                <tr data-line-id="<?php echo esc_attr( $line_id ); ?>" data-product-id="<?php echo esc_attr( $pid ); ?>"
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
                    data-sort-order_notes="<?php echo esc_attr( mb_strtolower( wp_strip_all_tags( $order_notes ) ) ); ?>"
                    data-sort-goodsin_notes="<?php echo esc_attr( mb_strtolower( wp_strip_all_tags( $notes ) ) ); ?>"
                    data-sort-stocked="<?php echo esc_attr( $stocked ); ?>"
                    data-sort-outstanding="<?php echo esc_attr( $outstanding ); ?>">
                    <td class="check-column">
                        <input type="checkbox" class="sop-goodsin-select" name="selected_lines[<?php echo esc_attr( $line_id ); ?>]" value="1" />
                    </td>
                    <td class="sop-goodsin-col-image" data-column="image"><div class="sop-goodsin-img-wrap"><?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></td>
                    <td class="sop-goodsin-col-location column-location" data-column="location"><?php echo esc_html( $location ); ?></td>
                    <td class="sop-goodsin-col-sku" data-column="sku"><?php echo esc_html( $sku . $missing_pid_warning ); ?></td>
                    <td class="sop-goodsin-col-product" data-column="product" title="<?php echo esc_attr( wp_strip_all_tags( $name ) ); ?>">
                        <div class="sop-goodsin-product-wrap">
                            <?php
                            if ( $product_link ) {
                                echo '<a class="sop-goodsin-product-link" href="' . esc_url( $product_link ) . '">' . esc_html( $name ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            } else {
                                echo '<span class="sop-goodsin-product-link">' . esc_html( $name ) . '</span>';
                            }
                            ?>
                        </div>
                    </td>
                    <td data-column="ordered"><?php echo esc_html( number_format_i18n( $ordered, 0 ) ); ?></td>
                    <td data-column="received"><input type="number" class="sop-goodsin-received sop-goodsin-narrow" step="1" min="0" value="<?php echo esc_attr( $received ); ?>" name="received_qty[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?> /></td>
                    <td data-column="missing"><input type="number" class="sop-goodsin-missing sop-goodsin-narrow" step="1" min="0" value="<?php echo esc_attr( $missing ); ?>" name="missing_qty[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?> /></td>
                    <td data-column="reject"><input type="number" class="sop-goodsin-reject sop-goodsin-narrow" step="1" min="0" value="<?php echo esc_attr( $reject ); ?>" name="reject_qty[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?> /></td>
                    <td data-column="reason">
                        <select class="sop-goodsin-reject-reason" name="reject_reason[<?php echo esc_attr( $pid ); ?>]" <?php echo $inputs_disabled_attr; ?>>
                            <option value=""><?php esc_html_e( '—', 'sop' ); ?></option>
                            <option value="wrong_spec" <?php selected( $reason, 'wrong_spec' ); ?>><?php esc_html_e( 'Wrong spec', 'sop' ); ?></option>
                            <option value="wrong_colour" <?php selected( $reason, 'wrong_colour' ); ?>><?php esc_html_e( 'Wrong colour', 'sop' ); ?></option>
                            <option value="damaged" <?php selected( $reason, 'damaged' ); ?>><?php esc_html_e( 'Damaged', 'sop' ); ?></option>
                            <option value="other" <?php selected( $reason, 'other' ); ?>><?php esc_html_e( 'Other', 'sop' ); ?></option>
                        </select>
                    </td>
                    <td class="sop-goodsin-carton sop-goodsin-cell-truncate" data-column="carton" title="<?php echo esc_attr( $carton ); ?>">
                        <input type="text" class="sop-goodsin-carton-no" value="<?php echo esc_attr( $carton ); ?>" <?php echo $inputs_disabled_attr; ?> />
                    </td>
                    <td class="sop-goodsin-text-col" data-column="product_notes" title="<?php echo esc_attr( $product_notes ); ?>">
                        <div class="sop-goodsin-notes-wrap">
                            <div class="sop-goodsin-notes-text"><?php echo esc_html( $product_notes ); ?></div>
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

        <?php if ( 'report' === $view || 'received' === $status ) : ?>
            <h3><?php esc_html_e( 'Goods-In Report (Issues)', 'sop' ); ?></h3>
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
        .sop-goodsin-table th:nth-child(6),
        .sop-goodsin-table td:nth-child(6) { width: 80px; min-width: 80px; }
        .sop-goodsin-table th:nth-child(7),
        .sop-goodsin-table td:nth-child(7) { width: 80px; min-width: 80px; }
        .sop-goodsin-table th:nth-child(8),
        .sop-goodsin-table td:nth-child(8) { width: 80px; min-width: 80px; }
        .sop-goodsin-table th:nth-child(9),
        .sop-goodsin-table td:nth-child(9) { width: 80px; min-width: 80px; }
        /* Reason + Carton no. */
        .sop-goodsin-table th:nth-child(10),
        .sop-goodsin-table td:nth-child(10) { width: 88px; min-width: 88px; max-width: 88px; }
        .sop-goodsin-table th:nth-child(11),
        .sop-goodsin-table td:nth-child(11) { width: 120px; min-width: 120px; }
        /* Notes columns */
        .sop-goodsin-table th:nth-child(12),
        .sop-goodsin-table td:nth-child(12) { width: 300px; min-width: 300px; max-width: 300px; }
        .sop-goodsin-table th:nth-child(13),
        .sop-goodsin-table td:nth-child(13) { width: 300px; min-width: 300px; max-width: 300px; }
        .sop-goodsin-table th:nth-child(14),
        .sop-goodsin-table td:nth-child(14) { width: 300px; min-width: 300px; max-width: 300px; }
        /* Stocked / Outstanding */
        .sop-goodsin-table th:nth-child(15),
        .sop-goodsin-table td:nth-child(15) { width: 50px; min-width: 50px; }
        .sop-goodsin-table th:nth-child(16),
        .sop-goodsin-table td:nth-child(16) { width: 80px; min-width: 80px; }
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
            max-height: 4.8em;
            text-align: left;
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
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 100000;
            display: none;
        }
        .sop-goodsin-notes-modal {
            position: fixed;
            top: 10%;
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

    <script>
        (function($){
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
            var $showCompleted = $('#sop-goodsin-show-completed');
            var $filterSummary = $('#sop-goodsin-filter-summary');
            var $searchInput = $('#sop-goodsin-search');
            var $searchClear = $('#sop-goodsin-search-clear');
            var $scanInput = $('#sop-goodsin-scan');
            var $issuesOnly = $('#sop-goodsin-issues-only');
            var notesActiveRow = null;
            var sopGoodsinFilterTimer = null;

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

            function sopGoodsinGetRowSearchText($tr) {
                var cached = $tr.data('sopSearchText');
                if ( cached ) {
                    return cached;
                }
                var parts = [];
                parts.push( ($tr.find('td.column-location').text() || '').toString() );
                parts.push( ($tr.find('td[data-column="sku"]').text() || '').toString() );
                parts.push( ($tr.find('.sop-goodsin-product-link').text() || '').toString() );
                parts.push( ($tr.find('.sop-goodsin-carton-no').val() || '').toString() );
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
                var cartonQuery = sopGoodsinNormalizeQuery( $cartonInput ? $cartonInput.val() : '' );
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

                    var rowCarton = sopGoodsinNormalizeQuery( $tr.find('.sop-goodsin-carton-no').val() || '' );
                    var matchesCarton = true;
                    if ( cartonQuery ) {
                        matchesCarton = ( rowCarton.indexOf( cartonQuery ) !== -1 );
                    }
                    var rowMissing = sopGoodsinParseNumber( $tr.find('.sop-goodsin-missing').val() );
                    var rowReject  = sopGoodsinParseNumber( $tr.find('.sop-goodsin-reject').val() );
                    var isIssue    = ( rowMissing > 0 || rowReject > 0 );

                    var hideCompleted = ( ! showCompleted && complete );
                    var hideSearch    = ( terms.length && ! matchesSearch );
                    var hideCarton    = ( cartonQuery && ! matchesCarton );
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
                        missing_qty: $tr.find('.sop-goodsin-missing').val(),
                        reject_qty: $tr.find('.sop-goodsin-reject').val(),
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

            function setAction(actionType) {
                if (actionType === 'save') {
                    $actionField.val('sop_goodsin_save');
                } else if (actionType === 'complete') {
                    $actionField.val('sop_goodsin_complete');
                } else {
                    $actionField.val('sop_goodsin_apply_stock');
                }
            }

            $('.sop-goodsin-submit').on('click', function(){
                var actionType = $(this).data('action') || 'save';
                setAction(actionType);
                var payloadObj = buildPayload(actionType);
                $payload.val(JSON.stringify(payloadObj));
                dirty = false;
                $form.trigger('submit');
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
            $('#sop-goodsin-lines').on('input change', '.sop-goodsin-carton input, .sop-goodsin-carton', function(){
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
            }

            function closeNotesModal() {
                notesActiveRow = null;
                $notesModal.hide().attr('aria-hidden', 'true');
                $notesModalBackdrop.hide().attr('aria-hidden', 'true');
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

            $(document).on('keydown', function(e){
                if ( 27 === e.which && $notesModal.is(':visible') ) {
                    e.preventDefault();
                    closeNotesModal();
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
                var scanVal = ($scanInput.val() || '').toString().trim();
                if ( ! scanVal ) {
                    return;
                }
                $searchInput.val( scanVal );
                try {
                    window.localStorage.setItem('sop_goodsin_search_query', scanVal);
                } catch (e2) {}
                sopGoodsinApplyFilterAll();
                sopGoodsinJumpToFirstVisible();
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

            function sortTable($th) {
                var sortKey = $th.data('sort-key');
                var sortType = $th.data('sort-type') || 'text';
                if (!sortKey) { return; }

                var currentDir = $th.hasClass('sorted-asc') ? 'asc' : ($th.hasClass('sorted-desc') ? 'desc' : '');
                var newDir = currentDir === 'asc' ? 'desc' : 'asc';

                $('.sop-goodsin-sort').removeClass('sorted-asc sorted-desc');
                $th.addClass(newDir === 'asc' ? 'sorted-asc' : 'sorted-desc');

                var $rows = $('#sop-goodsin-lines tbody tr');
                var rowsArr = $rows.get();

                rowsArr.sort(function(a, b){
                    var aVal = $(a).data('sort-' + sortKey);
                    var bVal = $(b).data('sort-' + sortKey);

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
                sortTable($(this));
            });

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
        })(jQuery);
    </script>
    <?php

    echo '</div>';
}

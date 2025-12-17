<?php
/**
 * Stock Order Plugin - Phase 5 (Goods-In v1) - Admin UI
 * File version: 1.0.00
 *
 * - List locked/receiving sheets.
 * - Receive against a sheet using JSON payload to avoid max_input_vars.
 * - Save progress, apply stock, and complete with a simple issues report.
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
    return is_array( $rows ) ? $rows : array();
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
    ?>
    <form id="sop-goodsin-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
        <?php wp_nonce_field( 'sop_goodsin_action', 'sop_goodsin_nonce' ); ?>
        <input type="hidden" name="action" id="sop-goodsin-action" value="sop_goodsin_save" />
        <input type="hidden" name="sop_goodsin_payload_json" id="sop-goodsin-payload-json" value="" />

        <p>
            <button type="button" class="button button-primary sop-goodsin-submit" data-action="save"><?php esc_html_e( 'Save progress', 'sop' ); ?></button>
            <button type="button" class="button sop-goodsin-submit" data-action="apply_selected"><?php esc_html_e( 'Add selected to stock', 'sop' ); ?></button>
            <button type="button" class="button sop-goodsin-submit" data-action="apply_all"><?php esc_html_e( 'Add all to stock', 'sop' ); ?></button>
            <button type="button" class="button button-secondary sop-goodsin-submit" data-action="complete"><?php esc_html_e( 'Complete Goods-In', 'sop' ); ?></button>
        </p>

        <table class="widefat striped" id="sop-goodsin-lines">
            <thead>
            <tr>
                <th class="check-column"><input type="checkbox" id="sop-goodsin-select-all" /></th>
                <th><?php esc_html_e( 'SKU', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Product', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Ordered', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Received', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Missing', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Reject', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Reason', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Notes', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Stocked', 'sop' ); ?></th>
                <th><?php esc_html_e( 'Outstanding', 'sop' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $lines as $line ) :
                $line_id = isset( $line['line_id'] ) ? (int) $line['line_id'] : 0;
                $pid     = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
                $sku     = isset( $line['sku_owner'] ) ? (string) $line['sku_owner'] : '';
                $name    = isset( $line['product_name'] ) ? (string) $line['product_name'] : '';
                $ordered = isset( $line['qty_owner'] ) ? (float) $line['qty_owner'] : 0.0;
                $received = isset( $line['goods_in_received_qty'] ) ? (float) $line['goods_in_received_qty'] : 0.0;
                $missing  = isset( $line['goods_in_missing_qty'] ) ? (float) $line['goods_in_missing_qty'] : 0.0;
                $reject   = isset( $line['goods_in_reject_qty'] ) ? (float) $line['goods_in_reject_qty'] : 0.0;
                $reason   = isset( $line['goods_in_reject_reason'] ) ? (string) $line['goods_in_reject_reason'] : '';
                $notes    = isset( $line['goods_in_notes'] ) ? (string) $line['goods_in_notes'] : '';
                $stocked  = isset( $line['goods_in_stock_added_qty'] ) ? (float) $line['goods_in_stock_added_qty'] : 0.0;
                $outstanding = max( 0.0, $ordered - $stocked - $missing - $reject );
                ?>
                <tr data-line-id="<?php echo esc_attr( $line_id ); ?>" data-product-id="<?php echo esc_attr( $pid ); ?>">
                    <td class="check-column">
                        <input type="checkbox" class="sop-goodsin-select" />
                    </td>
                    <td><?php echo esc_html( $sku ); ?></td>
                    <td><?php echo esc_html( $name ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( $ordered, 0 ) ); ?></td>
                    <td><input type="number" class="sop-goodsin-received" step="1" min="0" value="<?php echo esc_attr( $received ); ?>" /></td>
                    <td><input type="number" class="sop-goodsin-missing" step="1" min="0" value="<?php echo esc_attr( $missing ); ?>" /></td>
                    <td><input type="number" class="sop-goodsin-reject" step="1" min="0" value="<?php echo esc_attr( $reject ); ?>" /></td>
                    <td>
                        <select class="sop-goodsin-reject-reason">
                            <option value=""><?php esc_html_e( '—', 'sop' ); ?></option>
                            <option value="wrong_spec" <?php selected( $reason, 'wrong_spec' ); ?>><?php esc_html_e( 'Wrong spec', 'sop' ); ?></option>
                            <option value="wrong_colour" <?php selected( $reason, 'wrong_colour' ); ?>><?php esc_html_e( 'Wrong colour', 'sop' ); ?></option>
                            <option value="damaged" <?php selected( $reason, 'damaged' ); ?>><?php esc_html_e( 'Damaged', 'sop' ); ?></option>
                            <option value="other" <?php selected( $reason, 'other' ); ?>><?php esc_html_e( 'Other', 'sop' ); ?></option>
                        </select>
                    </td>
                    <td><input type="text" class="sop-goodsin-notes" value="<?php echo esc_attr( $notes ); ?>" /></td>
                    <td><?php echo esc_html( number_format_i18n( $stocked, 0 ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( $outstanding, 0 ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

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

    <script>
        (function($){
            var $form = $('#sop-goodsin-form');
            var $payload = $('#sop-goodsin-payload-json');
            var $actionField = $('#sop-goodsin-action');

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
                $form.trigger('submit');
            });

            $('#sop-goodsin-select-all').on('change', function(){
                var checked = $(this).is(':checked');
                $('.sop-goodsin-select').prop('checked', checked);
            });
        })(jQuery);
    </script>
    <?php

    echo '</div>';
}


<?php
/**
 * Main loader for the Stock Order Plugin.
 *
 * File version: 1.0.10
 * - Fix WMS debug panel detection for existing product edit screens; throttle render.
 * - Add opt-in WMS debug panel (JS/AJAX capture) behind sop_debug_wms=1.
 * - Skip SOP bootstrap on non-SOP AJAX requests.
 * - Remove BOM/whitespace to prevent activation output.
 * - Skip admin module load on non-SOP AJAX requests.
 * - Load SOP UI modules only on SOP admin pages.
 * - Load stockout tracking module in core bootstrap.
 * - Goods-In v1: load goods-in admin core/UI.
 * - Add data export admin tab wiring.
 * - Remove unused setup_* placeholder methods.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coordinates loading SOP components.
 */
class sop_Loader {

    /**
     * Bootstraps plugin components.
     */
    public function init() {
        if ( ! $this->should_bootstrap_for_request() ) {
            return;
        }

        $this->load_core();

        if ( is_admin() && $this->should_load_admin_modules() ) {
            $this->load_admin();
        }
    }

    /**
     * Load shared/core dependencies.
     */
    protected function load_core() {
        require_once SOP_PLUGIN_DIR . 'includes/db-helpers.php';
        require_once SOP_PLUGIN_DIR . 'includes/domain-helpers.php';
        require_once SOP_PLUGIN_DIR . 'includes/stockout-tracking.php';
        require_once SOP_PLUGIN_DIR . 'includes/helper-buffer.php';
        require_once SOP_PLUGIN_DIR . 'includes/forecast-core.php';
        require_once SOP_PLUGIN_DIR . 'includes/supplier-meta-box.php';
    }

    /**
     * Load admin-specific code.
     */
    protected function load_admin() {
        require_once SOP_PLUGIN_DIR . 'admin/settings-supplier.php';
        require_once SOP_PLUGIN_DIR . 'admin/data-export.php';
        require_once SOP_PLUGIN_DIR . 'admin/product-mapping.php';
        require_once SOP_PLUGIN_DIR . 'admin/preorder-core.php';
        require_once SOP_PLUGIN_DIR . 'admin/goods-in-core.php';

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $is_sop_page = ( '' !== $page && 0 === strpos( $page, 'sop' ) );
        if ( $is_sop_page ) {
            require_once SOP_PLUGIN_DIR . 'admin/preorder-ui.php';
            require_once SOP_PLUGIN_DIR . 'admin/goods-in-ui.php';
        }

        if ( $this->sop_debug_wms_is_enabled() && $this->sop_debug_wms_is_product_edit_screen() ) {
            add_action( 'admin_footer', array( $this, 'sop_debug_wms_render_panel' ), 9999 );
        }
    }

    /**
     * Read the current AJAX action (sanitised).
     *
     * @return string
     */
    protected function get_current_ajax_action() {
        return isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
    }

    /**
     * Decide whether the plugin should bootstrap for this request.
     *
     * @return bool
     */
    protected function should_bootstrap_for_request() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            $action = $this->get_current_ajax_action();
            if ( '' === $action || 0 !== strpos( $action, 'sop_' ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Decide whether admin modules should load for the current request.
     *
     * @return bool
     */
    protected function should_load_admin_modules() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            $action = $this->get_current_ajax_action();
            if ( '' === $action || 0 !== strpos( $action, 'sop_' ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether WMS debug panel is enabled.
     *
     * @return bool
     */
    protected function sop_debug_wms_is_enabled() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return false;
        }

        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $flag = isset( $_GET['sop_debug_wms'] ) ? sanitize_text_field( wp_unslash( $_GET['sop_debug_wms'] ) ) : '';
        return ( '1' === $flag );
    }

    /**
     * Check if current screen is product edit/new screen.
     *
     * @return bool
     */
    protected function sop_debug_wms_is_product_edit_screen() {
        $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
        if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
            return false;
        }

        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen && isset( $screen->post_type ) && 'product' === $screen->post_type ) {
                return true;
            }
        }

        if ( 'post.php' === $pagenow ) {
            $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
            if ( $post_id > 0 && function_exists( 'get_post_type' ) ) {
                return ( 'product' === get_post_type( $post_id ) );
            }
        }

        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
        return ( 'product' === $post_type );
    }

    /**
     * Render opt-in WMS debug panel.
     *
     * @return void
     */
    public function sop_debug_wms_render_panel() {
        ?>
        <style>
            .sop-wms-debug-panel {
                position: fixed;
                bottom: 16px;
                right: 16px;
                width: 360px;
                max-width: 90vw;
                z-index: 100000;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 6px;
                box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
                font-family: inherit;
            }
            .sop-wms-debug-panel header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 10px;
                background: #f6f7f7;
                border-bottom: 1px solid #dcdcde;
                font-weight: 600;
                cursor: pointer;
            }
            .sop-wms-debug-panel .sop-wms-debug-body {
                padding: 8px 10px;
            }
            .sop-wms-debug-panel pre {
                max-height: 220px;
                overflow: auto;
                background: #f9f9f9;
                border: 1px solid #e5e5e5;
                padding: 6px;
                font-size: 11px;
                margin: 0 0 8px;
                white-space: pre-wrap;
                word-break: break-word;
            }
            .sop-wms-debug-panel .sop-wms-debug-actions {
                display: flex;
                gap: 6px;
            }
            .sop-wms-debug-panel button {
                font-size: 12px;
            }
            .sop-wms-debug-panel.is-collapsed .sop-wms-debug-body {
                display: none;
            }
        </style>
        <div class="sop-wms-debug-panel" id="sop-wms-debug-panel">
            <header>
                <span>SOP Debug (WMS)</span>
                <span aria-hidden="true">▾</span>
            </header>
            <div class="sop-wms-debug-body">
                <pre id="sop-wms-debug-log"></pre>
                <div class="sop-wms-debug-actions">
                    <button type="button" class="button button-secondary" id="sop-wms-debug-copy">Copy debug log</button>
                    <button type="button" class="button" id="sop-wms-debug-clear">Clear</button>
                </div>
            </div>
        </div>
        <script>
            (function() {
                var panel = document.getElementById('sop-wms-debug-panel');
                var logEl = document.getElementById('sop-wms-debug-log');
                var copyBtn = document.getElementById('sop-wms-debug-copy');
                var clearBtn = document.getElementById('sop-wms-debug-clear');
                if (!panel || !logEl || !copyBtn || !clearBtn) { return; }

                var logs = [];
                var maxEntries = 200;
                var renderScheduled = false;

                function addLog(entry) {
                    logs.push(entry);
                    if (logs.length > maxEntries) {
                        logs = logs.slice(logs.length - maxEntries);
                    }
                    if (!renderScheduled) {
                        renderScheduled = true;
                        setTimeout(function() {
                            renderScheduled = false;
                            renderLogs();
                        }, 200);
                    }
                }

                function renderLogs() {
                    var slice = logs.slice(-50);
                    logEl.textContent = JSON.stringify(slice, null, 2);
                }

                panel.querySelector('header').addEventListener('click', function() {
                    panel.classList.toggle('is-collapsed');
                });

                window.addEventListener('error', function(e) {
                    addLog({
                        type: 'error',
                        message: e.message || '',
                        source: e.filename || '',
                        line: e.lineno || 0,
                        col: e.colno || 0,
                        stack: e.error && e.error.stack ? e.error.stack : ''
                    });
                });

                window.addEventListener('unhandledrejection', function(e) {
                    var reason = e.reason || '';
                    addLog({
                        type: 'unhandledrejection',
                        message: reason && reason.message ? reason.message : String(reason),
                        stack: reason && reason.stack ? reason.stack : ''
                    });
                });

                if (window.jQuery) {
                    jQuery(document).on('ajaxSend', function(event, xhr, settings) {
                        if (!settings || !settings.url || settings.url.indexOf('admin-ajax.php') === -1) { return; }
                        addLog({
                            type: 'ajaxSend',
                            url: settings.url,
                            data: settings.data || ''
                        });
                    });
                    jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
                        if (!settings || !settings.url || settings.url.indexOf('admin-ajax.php') === -1) { return; }
                        var responseText = xhr && xhr.responseText ? String(xhr.responseText) : '';
                        var bomDetected = responseText.indexOf('\ufeff') === 0 || responseText.indexOf('ï»¿') !== -1;
                        addLog({
                            type: 'ajaxComplete',
                            url: settings.url,
                            status: xhr && xhr.status ? xhr.status : 0,
                            data: settings.data || '',
                            response: responseText.substring(0, 500),
                            flags: bomDetected ? ['BOM_DETECTED'] : []
                        });
                    });
                    jQuery(document).on('ajaxError', function(event, xhr, settings, error) {
                        if (!settings || !settings.url || settings.url.indexOf('admin-ajax.php') === -1) { return; }
                        var responseText = xhr && xhr.responseText ? String(xhr.responseText) : '';
                        var bomDetected = responseText.indexOf('\ufeff') === 0 || responseText.indexOf('ï»¿') !== -1;
                        addLog({
                            type: 'ajaxError',
                            url: settings.url,
                            status: xhr && xhr.status ? xhr.status : 0,
                            error: error || '',
                            data: settings.data || '',
                            response: responseText.substring(0, 500),
                            flags: bomDetected ? ['BOM_DETECTED'] : []
                        });
                    });
                }

                copyBtn.addEventListener('click', function() {
                    var payload = JSON.stringify(logs, null, 2);
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(payload);
                    } else {
                        window.prompt('Copy debug log:', payload);
                    }
                });

                clearBtn.addEventListener('click', function() {
                    logs = [];
                    renderLogs();
                });

                renderLogs();
            })();
        </script>
        <?php
    }

}


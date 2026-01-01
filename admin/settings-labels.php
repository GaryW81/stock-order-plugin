<?php
/**
 * Stock Order Plugin - Labels & Barcodes settings tab
 * File version: 1.0.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_labels_render_settings_tab' ) ) {
    /**
     * Render the Labels & Barcodes settings tab.
     */
    function sop_labels_render_settings_tab() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $settings = function_exists( 'sop_labels_get_settings' ) ? sop_labels_get_settings() : array();
        $settings = is_array( $settings ) ? $settings : array();

        $width        = isset( $settings['default_label_width_mm'] ) ? (float) $settings['default_label_width_mm'] : 50;
        $height       = isset( $settings['default_label_height_mm'] ) ? (float) $settings['default_label_height_mm'] : 25;
        $include_date = ! empty( $settings['include_date'] ) ? 1 : 0;
        // Back-compat: if legacy key present, treat as enabled.
        if ( ! $include_date && ( ! empty( $settings['include_order_number'] ) || ! empty( $settings['include_sheet_number'] ) ) ) {
            $include_date = 1;
        }

        $done = isset( $_GET['sop_barcode_warm_done'] ) ? (int) $_GET['sop_barcode_warm_done'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $generated = isset( $_GET['generated'] ) ? (int) $_GET['generated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $skipped   = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $missing   = isset( $_GET['missing'] ) ? (int) $_GET['missing'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $errors    = isset( $_GET['errors'] ) ? (int) $_GET['errors'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Labels & Barcodes', 'sop' ); ?></h2>
            <p><?php esc_html_e( 'Set global defaults for in-house label sizes and content.', 'sop' ); ?></p>

            <?php if ( $done ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: generated, 2: skipped, 3: missing, 4: errors */
                            esc_html__( 'Barcode cache warm-up finished. Generated: %1$d, Skipped: %2$d, Missing SKU: %3$d, Errors: %4$d', 'sop' ),
                            $generated,
                            $skipped,
                            $missing,
                            $errors
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'sop_labels_settings_group' );
                ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Default label width (mm)', 'sop' ); ?></th>
                            <td>
                                <input type="number" step="0.1" min="10" max="150" name="sop_labels_settings[default_label_width_mm]" value="<?php echo esc_attr( $width ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Default label height (mm)', 'sop' ); ?></th>
                            <td>
                                <input type="number" step="0.1" min="10" max="150" name="sop_labels_settings[default_label_height_mm]" value="<?php echo esc_attr( $height ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Include date on label (MM/YY)', 'sop' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sop_labels_settings[include_date]" value="1" <?php checked( $include_date, 1 ); ?> />
                                    <?php esc_html_e( 'Show the current date (MM/YY) on labels', 'sop' ); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>

            <h3><?php esc_html_e( 'Divi Button Link URL', 'sop' ); ?></h3>
            <p><?php esc_html_e( 'Add a Divi Button module on product pages and set Button Link URL to:', 'sop' ); ?></p>
            <p>
                <input type="text" readonly class="regular-text" value="?sop_print_label=1" onclick="this.select();" />
                <br />
                <span class="description"><?php esc_html_e( 'Set it to open in a new tab for easier printing.', 'sop' ); ?></span>
            </p>

            <h3><?php esc_html_e( 'Bulk label printing', 'sop' ); ?></h3>
            <p><?php esc_html_e( 'Place this shortcode on a private page for staff to print bulk labels (login with read capability required):', 'sop' ); ?></p>
            <p>
                <input type="text" readonly class="regular-text" value="[sop_a4_labels_print_form]" onclick="this.select();" />
            </p>

            <hr />
            <h3><?php esc_html_e( 'Barcode cache', 'sop' ); ?></h3>
            <p><?php esc_html_e( 'Cached SVG barcodes are required for labels and AJAX access. Warm the cache after enabling barcodes or when SKUs change.', 'sop' ); ?></p>
            <?php
            $warm_url = wp_nonce_url(
                add_query_arg(
                    array(
                        'action' => 'sop_barcode_warm_cache',
                        'offset' => 0,
                        'batch'  => 100,
                    ),
                    admin_url( 'admin-post.php' )
                ),
                'sop_barcode_warm_cache'
            );
            ?>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( $warm_url ); ?>"><?php esc_html_e( 'Warm barcode cache', 'sop' ); ?></a>
            </p>

            <h3><?php esc_html_e( 'Barcode Contract (Pick & Pack)', 'sop' ); ?></h3>
            <ul style="list-style: disc; padding-left: 20px;">
                <li><?php esc_html_e( 'Symbology: Code 128', 'sop' ); ?></li>
                <li><?php esc_html_e( 'Barcode data: Product SKU (exact string, case preserved)', 'sop' ); ?></li>
                <li><?php esc_html_e( 'Whitespace: trim leading/trailing only; preserve internal spaces', 'sop' ); ?></li>
                <li><?php esc_html_e( 'Match: exact string only (reject partial)', 'sop' ); ?></li>
                <li><?php esc_html_e( 'Source of truth: SOP (Pick & Pack must not implement its own barcode generator)', 'sop' ); ?></li>
            </ul>

            <?php $admin_ajax = esc_url( admin_url( 'admin-ajax.php' ) ); ?>
            <p><strong><?php esc_html_e( 'PHP scan normaliser:', 'sop' ); ?></strong></p>
            <textarea readonly class="large-text code" rows="1" onclick="this.select();">sop_normalise_scan_input( $raw_scan );</textarea>

            <p><strong><?php esc_html_e( 'PHP barcode getter (SVG only):', 'sop' ); ?></strong></p>
            <textarea readonly class="large-text code" rows="3" onclick="this.select();">$svg = sop_get_barcode_svg( $sku );
if ( is_wp_error( $svg ) ) { /* handle */ }</textarea>

            <p><strong><?php esc_html_e( 'AJAX endpoint example:', 'sop' ); ?></strong></p>
            <input type="text" readonly class="regular-text" onclick="this.select();" value="<?php echo esc_attr( $admin_ajax . '?action=sop_barcode&sku=KRZ1234' ); ?>" />

            <p><strong><?php esc_html_e( 'Barcode tester (opens SVG in new tab):', 'sop' ); ?></strong></p>
            <form method="get" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" target="_blank" style="margin-bottom:20px;">
                <input type="hidden" name="action" value="sop_barcode" />
                <input type="text" name="sku" placeholder="<?php esc_attr_e( 'Enter SKU (exact)', 'sop' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Open barcode SVG', 'sop' ); ?></button>
            </form>

            <p><strong><?php esc_html_e( 'Scanner assumptions:', 'sop' ); ?></strong><br />
                <?php esc_html_e( 'HID keyboard mode; terminator Enter/newline; no prefix/suffix assumed; single focused input model recommended.', 'sop' ); ?>
            </p>
        </div>
        <?php
    }
}

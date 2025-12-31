<?php
/**
 * Stock Order Plugin - Labels & Barcodes settings tab
 * File version: 1.0.6
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

            <h3><?php esc_html_e( 'A4 batch label printing', 'sop' ); ?></h3>
            <p><?php esc_html_e( 'Place this shortcode on a private page for staff to print A4 label sheets (login with read capability required):', 'sop' ); ?></p>
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
        </div>
        <?php
    }
}

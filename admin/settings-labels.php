<?php
/**
 * Stock Order Plugin - Labels & Barcodes settings tab
 * File version: 1.0.3
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

        $width  = isset( $settings['default_label_width_mm'] ) ? (float) $settings['default_label_width_mm'] : 50;
        $height = isset( $settings['default_label_height_mm'] ) ? (float) $settings['default_label_height_mm'] : 25;
        $include_date = ! empty( $settings['include_date'] ) ? 1 : 0;
        // Back-compat: if legacy key present, treat as enabled.
        if ( ! $include_date && ( ! empty( $settings['include_order_number'] ) || ! empty( $settings['include_sheet_number'] ) ) ) {
            $include_date = 1;
        }
        ?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Labels & Barcodes', 'sop' ); ?></h2>
            <p><?php esc_html_e( 'Set global defaults for in-house label sizes and content.', 'sop' ); ?></p>

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
        </div>
        <?php
    }
}

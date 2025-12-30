<?php
/**
 * Stock Order Plugin - Labels & Barcodes settings tab
 * File version: 1.0.0
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
        $include_qty = ! empty( $settings['include_qty'] ) ? 1 : 0;
        $include_sheet_number = ! empty( $settings['include_sheet_number'] ) ? 1 : 0;
        ?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Labels & Barcodes', 'sop' ); ?></h2>
            <p><?php esc_html_e( 'Set global defaults for label sizes and content. Supplier-specific overrides can be set in Supplier settings and are used for supplier label packs.', 'sop' ); ?></p>

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
                            <th scope="row"><?php esc_html_e( 'Include quantity on label', 'sop' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sop_labels_settings[include_qty]" value="1" <?php checked( $include_qty, 1 ); ?> />
                                    <?php esc_html_e( 'Show ordered quantity on labels', 'sop' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Include sheet number on label', 'sop' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sop_labels_settings[include_sheet_number]" value="1" <?php checked( $include_sheet_number, 1 ); ?> />
                                    <?php esc_html_e( 'Show the preorder sheet number on labels', 'sop' ); ?>
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


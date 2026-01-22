<?php
/**
 * Stock Order Plugin - Phase 2
 * PO Details admin page (Company profile for PO exports)
 *
 * File version: 1.0.0
 * - Add PO Details page for company profile fields used in PO exports.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_render_po_details_page' ) ) {
    /**
     * Render the PO Details admin page.
     *
     * @return void
     */
    function sop_render_po_details_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'sop' ) );
        }

        $company_profile = function_exists( 'sop_get_company_profile' ) ? sop_get_company_profile() : array();
        $company_name_val = isset( $company_profile['company_name'] ) ? $company_profile['company_name'] : '';
        $company_billing_val = isset( $company_profile['billing_address'] ) ? $company_profile['billing_address'] : '';
        $company_shipping_val = isset( $company_profile['shipping_address'] ) ? $company_profile['shipping_address'] : '';
        $company_email_val = isset( $company_profile['email'] ) ? $company_profile['email'] : '';
        $company_phone_landline_val = isset( $company_profile['phone_landline'] ) ? $company_profile['phone_landline'] : '';
        $company_phone_mobile_val = isset( $company_profile['phone_mobile'] ) ? $company_profile['phone_mobile'] : '';
        $company_crn_val = isset( $company_profile['company_reg_number'] ) ? $company_profile['company_reg_number'] : '';
        $company_vat_val = isset( $company_profile['vat_number'] ) ? $company_profile['vat_number'] : '';

        echo '<div class="wrap sop-wrap">';
        echo '<h1>' . esc_html__( 'PO Details', 'sop' ) . '</h1>';
        echo '<p>' . esc_html__( 'Used on PO Summary exports (Buyer section).', 'sop' ) . '</p>';

        settings_errors( 'sop_company_profile' );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'sop_company_profile_save', 'sop_company_profile_nonce' ); ?>
            <input type="hidden" name="sop_company_profile_action" value="save" />

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="sop_company_name"><?php esc_html_e( 'Company name', 'sop' ); ?></label></th>
                        <td>
                            <input type="text" id="sop_company_name" name="sop_company_name" class="regular-text" value="<?php echo esc_attr( $company_name_val ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop_company_billing_address"><?php esc_html_e( 'Billing address', 'sop' ); ?></label></th>
                        <td>
                            <textarea id="sop_company_billing_address" name="sop_company_billing_address" rows="3" class="large-text"><?php echo esc_textarea( $company_billing_val ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop_company_shipping_address"><?php esc_html_e( 'Shipping address', 'sop' ); ?></label></th>
                        <td>
                            <textarea id="sop_company_shipping_address" name="sop_company_shipping_address" rows="3" class="large-text"><?php echo esc_textarea( $company_shipping_val ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop_company_email"><?php esc_html_e( 'Email', 'sop' ); ?></label></th>
                        <td>
                            <input type="email" id="sop_company_email" name="sop_company_email" class="regular-text" value="<?php echo esc_attr( $company_email_val ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop_company_phone_landline"><?php esc_html_e( 'Phone (landline)', 'sop' ); ?></label></th>
                        <td>
                            <input type="text" id="sop_company_phone_landline" name="sop_company_phone_landline" class="regular-text" value="<?php echo esc_attr( $company_phone_landline_val ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop_company_phone_mobile"><?php esc_html_e( 'Phone (mobile)', 'sop' ); ?></label></th>
                        <td>
                            <input type="text" id="sop_company_phone_mobile" name="sop_company_phone_mobile" class="regular-text" value="<?php echo esc_attr( $company_phone_mobile_val ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop_company_crn"><?php esc_html_e( 'Company registration number (CRN)', 'sop' ); ?></label></th>
                        <td>
                            <input type="text" id="sop_company_crn" name="sop_company_crn" class="regular-text" value="<?php echo esc_attr( $company_crn_val ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sop_company_vat"><?php esc_html_e( 'VAT number', 'sop' ); ?></label></th>
                        <td>
                            <input type="text" id="sop_company_vat" name="sop_company_vat" class="regular-text" value="<?php echo esc_attr( $company_vat_val ); ?>" />
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( __( 'Save Changes', 'sop' ) ); ?>
        </form>
        <?php
        echo '</div>';
    }
}

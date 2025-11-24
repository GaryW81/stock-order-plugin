<?php
/**
 * Stock Order Plugin – Phase 2 (Updated with USD)
 * Admin Settings & Supplier UI (General + Suppliers)
 * File version: 1.5.10
 *
 * - Adds "Stock Order" top-level admin menu.
 * - General Settings tab stores global options in `sop_settings`.
 * - Suppliers tab manages rows in `sop_suppliers` via Phase 1 helpers.
 * - Implements global stock buffer months + per-supplier override (months).
 * - Supplier currency options: GBP, RMB, EUR, USD.
 * - Future phases: plugin will own its own product→supplier links and foreign prices
 *   (your existing meta like "supplier" and "rmb" can be migrated via a one-off tool).
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

// Require Phase 1 DB + helper layer.
if ( ! class_exists( 'sop_DB' ) || ! function_exists( 'sop_supplier_get_all' ) ) {
    return;
}

if ( ! class_exists( 'sop_Admin_Settings' ) ) :

class sop_Admin_Settings {

    /**
     * Option name for global settings.
     */
    const OPTION_KEY = 'sop_settings';

    /**
     * Constructor – hook into admin.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register the "Stock Order" admin menu + single page.
     */
    public function register_menu() {
        // Only for users who can manage WooCommerce.
        $capability = 'manage_woocommerce';

        add_menu_page(
            __( 'Stock Order', 'sop' ),
            __( 'Stock Order', 'sop' ),
            $capability,
            'sop_stock_order',
            array( $this, 'render_page' ),
            'dashicons-products',
            56
        );

        // Rename the default first submenu entry to "General Settings".
        if ( isset( $GLOBALS['submenu']['sop_stock_order'][0] ) ) {
            $GLOBALS['submenu']['sop_stock_order'][0][0] = __( 'General Settings', 'sop' );
            $GLOBALS['submenu']['sop_stock_order'][0][3] = __( 'General Settings', 'sop' );
        }
    }

    /**
     * Register the global settings with the options API.
     */
    public function register_settings() {
        register_setting(
            'sop_settings_group',
            self::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize the global settings before saving.
     *
     * @param array $input Raw input from form.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $defaults = self::get_default_settings();
        $output   = $defaults;

        $input = is_array( $input ) ? $input : array();

        // Analysis lookback days.
        if ( isset( $input['analysis_lookback_days'] ) ) {
            $output['analysis_lookback_days'] = max( 1, (int) $input['analysis_lookback_days'] );
        }

        // Global buffer months.
        if ( isset( $input['buffer_months_global'] ) ) {
            $buffer = (float) $input['buffer_months_global'];
            $output['buffer_months_global'] = ( $buffer < 0 ) ? 0 : $buffer;
        }

        // RMB → GBP rate (allow string, we'll cast when using).
        if ( isset( $input['rmb_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['rmb_to_gbp_rate'] );
            $output['rmb_to_gbp_rate'] = $rate;
        }

        // EUR ��' GBP rate (allow string, we'll cast when using).
        if ( isset( $input['eur_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['eur_to_gbp_rate'] );
            $output['eur_to_gbp_rate'] = $rate;
        }

        // USD ��' GBP rate (allow string, we'll cast when using).
        if ( isset( $input['usd_to_gbp_rate'] ) ) {
            $rate = trim( (string) $input['usd_to_gbp_rate'] );
            $output['usd_to_gbp_rate'] = $rate;
        }

        // Show suggested vs max order toggle.
        $output['show_suggested_vs_max'] = ! empty( $input['show_suggested_vs_max'] ) ? 1 : 0;

        return $output;
    }

    /**
     * Get default global settings (used for merge + initial display).
     *
     * @return array
     */
    public static function get_default_settings() {
        return array(
            'analysis_lookback_days'   => 365, // 12 months as default.
            'buffer_months_global'     => 6,   // Global stock buffer period in months.
            'rmb_to_gbp_rate'          => '',  // Optional, manual entry.
            'eur_to_gbp_rate'          => '',  // Optional, manual entry.
            'usd_to_gbp_rate'          => '',  // Optional, manual entry.
            'show_suggested_vs_max'    => 1,   // Show comparison by default.
        );
    }

    /**
     * Fetch current settings from the DB with defaults merged.
     *
     * @return array
     */
    public static function get_settings() {
        $stored   = get_option( self::OPTION_KEY, array() );
        $defaults = self::get_default_settings();

        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        return wp_parse_args( $stored, $defaults );
    }

    /**
     * Main page renderer – handles tab switching.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        if ( ! in_array( $active_tab, array( 'general', 'suppliers' ), true ) ) {
            $active_tab = 'general';
        }

        echo '<div class="wrap sop-wrap">';
        echo '<h1>' . esc_html__( 'Stock Order Plugin', 'sop' ) . '</h1>';

        // Render tabs.
        echo '<h2 class="nav-tab-wrapper">';
        printf(
            '<a href="%s" class="nav-tab %s">%s</a>',
            esc_url( admin_url( 'admin.php?page=sop_stock_order&tab=general' ) ),
            ( 'general' === $active_tab ? 'nav-tab-active' : '' ),
            esc_html__( 'General Settings', 'sop' )
        );
        printf(
            '<a href="%s" class="nav-tab %s">%s</a>',
            esc_url( admin_url( 'admin.php?page=sop_stock_order&tab=suppliers' ) ),
            ( 'suppliers' === $active_tab ? 'nav-tab-active' : '' ),
            esc_html__( 'Suppliers', 'sop' )
        );
        echo '</h2>';

        // Tab content.
        if ( 'suppliers' === $active_tab ) {
            $this->handle_supplier_actions();
            $this->render_suppliers_tab();
        } else {
            $this->render_general_tab();
        }

        echo '</div>'; // .wrap
    }

    /**
     * Render the General Settings tab.
     */
    protected function render_general_tab() {
        $settings = self::get_settings();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'sop_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="sop_analysis_lookback_days">
                                <?php esc_html_e( 'Default analysis period (days)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="sop_analysis_lookback_days"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[analysis_lookback_days]"
                                   value="<?php echo esc_attr( (int) $settings['analysis_lookback_days'] ); ?>"
                                   min="1"
                                   class="small-text"
                                   size="20" />
                            <p class="description">
                                <?php esc_html_e( 'Used by the forecast engine as the default lookback window (e.g. 365 = last 12 months).', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_buffer_months_global">
                                <?php esc_html_e( 'Global stock buffer period (months)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   step="0.1"
                                   id="sop_buffer_months_global"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[buffer_months_global]"
                                   value="<?php echo esc_attr( $settings['buffer_months_global'] ); ?>"
                                   min="0"
                                   class="small-text"
                                   size="20" />
                            <p class="description">
                                <?php esc_html_e( 'Base buffer period used in demand projections. Individual suppliers can override this.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_rmb_to_gbp_rate">
                                <?php esc_html_e( 'RMB → GBP rate (optional)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sop_rmb_to_gbp_rate"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rmb_to_gbp_rate]"
                                   value="<?php echo esc_attr( $settings['rmb_to_gbp_rate'] ); ?>"
                                   class="small-text"
                                   size="20" />
                            <p class="description">
                                <?php esc_html_e( 'Optional manual exchange rate for cost comparisons. Leave blank if not needed.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_eur_to_gbp_rate">
                                <?php esc_html_e( 'EUR to GBP rate (optional)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sop_eur_to_gbp_rate"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[eur_to_gbp_rate]"
                                   value="<?php echo esc_attr( $settings['eur_to_gbp_rate'] ); ?>"
                                   class="small-text"
                                   size="20" />
                            <p class="description">
                                <?php esc_html_e( 'Optional manual exchange rate for cost comparisons. Leave blank if not needed.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sop_usd_to_gbp_rate">
                                <?php esc_html_e( 'USD to GBP rate (optional)', 'sop' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sop_usd_to_gbp_rate"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[usd_to_gbp_rate]"
                                   value="<?php echo esc_attr( $settings['usd_to_gbp_rate'] ); ?>"
                                   class="small-text"
                                   size="20" />
                            <p class="description">
                                <?php esc_html_e( 'Optional manual exchange rate for cost comparisons. Leave blank if not needed.', 'sop' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Show suggested vs max order comparison', 'sop' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_suggested_vs_max]"
                                       value="1" <?php checked( 1, (int) $settings['show_suggested_vs_max'] ); ?> />
                                <?php esc_html_e( 'Display plugin suggested qty alongside max_order_qty_per_month on forecast screens.', 'sop' ); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Handle supplier create/update actions.
     */
    protected function handle_supplier_actions() {
        if ( ! isset( $_POST['sop_supplier_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        check_admin_referer( 'sop_save_supplier', 'sop_supplier_nonce' );

        $action = sanitize_key( wp_unslash( $_POST['sop_supplier_action'] ) );
        if ( 'save' !== $action ) {
            return;
        }

        $id              = isset( $_POST['sop_supplier_id'] ) ? (int) $_POST['sop_supplier_id'] : 0;
        $name            = isset( $_POST['sop_supplier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_name'] ) ) : '';
        $slug            = isset( $_POST['sop_supplier_slug'] ) ? sanitize_title( wp_unslash( $_POST['sop_supplier_slug'] ) ) : '';
        $currency        = isset( $_POST['sop_supplier_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['sop_supplier_currency'] ) ) : 'GBP';
        $lead_time_weeks = isset( $_POST['sop_supplier_lead_time_weeks'] ) ? (int) $_POST['sop_supplier_lead_time_weeks'] : 0;
        $is_active       = ! empty( $_POST['sop_supplier_is_active'] ) ? 1 : 0;

        // Per-supplier buffer override (months).
        $buffer_override_raw = isset( $_POST['sop_supplier_buffer_months_override'] )
            ? trim( wp_unslash( $_POST['sop_supplier_buffer_months_override'] ) )
            : '';

        $buffer_override = ( '' === $buffer_override_raw ) ? '' : (float) $buffer_override_raw;
        if ( is_numeric( $buffer_override ) && $buffer_override < 0 ) {
            $buffer_override = 0;
        }

        // Preserve existing settings_json if editing.
        $settings_array = array();

        if ( $id > 0 ) {
            $existing = sop_supplier_get_by_id( $id );
            if ( $existing && ! empty( $existing->settings_json ) ) {
                $decoded = json_decode( $existing->settings_json, true );
                if ( is_array( $decoded ) ) {
                    $settings_array = $decoded;
                }
            }
        }

        if ( '' === $buffer_override_raw ) {
            // Empty input = remove override (fall back to global).
            unset( $settings_array['buffer_months_override'] );
        } else {
            $settings_array['buffer_months_override'] = (float) $buffer_override;
        }

        $settings_json = ! empty( $settings_array ) ? wp_json_encode( $settings_array ) : null;

        $args = array(
            'id'              => $id,
            'name'            => $name,
            'slug'            => $slug,
            'currency'        => $currency,
            'lead_time_weeks' => $lead_time_weeks,
            'is_active'       => $is_active,
            'settings_json'   => $settings_json,
        );

        $result = sop_supplier_upsert( $args );

        if ( $result ) {
            // Redirect to avoid resubmission on refresh.
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'        => 'sop_stock_order',
                        'tab'         => 'suppliers',
                        'updated'     => '1',
                        'supplier_id' => $result,
                        'sop_edited'  => '1',
                    ),
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
    }

    /**
     * Render the Suppliers tab: list + add/edit form.
     */
    protected function render_suppliers_tab() {
        $editing_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0;
        $editing    = null;

        if ( $editing_id > 0 ) {
            $editing = sop_supplier_get_by_id( $editing_id );
        }

        // Feedback message.
        if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            esc_html_e( 'Supplier saved.', 'sop' );
            echo '</p></div>';
        }

        // Fetch all suppliers for list.
        $suppliers = sop_supplier_get_all();

        ?>
        <div class="sop-suppliers-wrap">
            <h2><?php esc_html_e( 'Suppliers', 'sop' ); ?></h2>

            <p><?php esc_html_e( 'Manage suppliers used by the Stock Order plugin. Each supplier can have its own lead time, currency, and optional stock buffer override.', 'sop' ); ?></p>

            <h3><?php esc_html_e( 'Supplier list', 'sop' ); ?></h3>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Currency', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Lead time (weeks)', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Buffer override (months)', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Active', 'sop' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'sop' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( ! empty( $suppliers ) ) :
                        foreach ( $suppliers as $supplier ) :
                            $settings_json   = isset( $supplier->settings_json ) ? $supplier->settings_json : null;
                            $settings_arr    = $settings_json ? json_decode( $settings_json, true ) : array();
                            $buffer_override = ( is_array( $settings_arr ) && isset( $settings_arr['buffer_months_override'] ) )
                                ? (float) $settings_arr['buffer_months_override']
                                : '';

                            ?>
                            <tr>
                                <td><?php echo esc_html( $supplier->name ); ?></td>
                                <td><?php echo esc_html( $supplier->slug ); ?></td>
                                <td><?php echo esc_html( $supplier->currency ); ?></td>
                                <td><?php echo esc_html( (int) $supplier->lead_time_weeks ); ?></td>
                                <td>
                                    <?php
                                    if ( '' === $buffer_override ) {
                                        esc_html_e( 'Global', 'sop' );
                                    } else {
                                        echo esc_html( $buffer_override );
                                    }
                                    ?>
                                </td>
                                <td><?php echo $supplier->is_active ? esc_html__( 'Yes', 'sop' ) : esc_html__( 'No', 'sop' ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg(
                                        array(
                                            'page'        => 'sop_stock_order',
                                            'tab'         => 'suppliers',
                                            'supplier_id' => (int) $supplier->id,
                                        ),
                                        admin_url( 'admin.php' )
                                    ) ); ?>">
                                        <?php esc_html_e( 'Edit', 'sop' ); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    else :
                        ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No suppliers found yet. Use the form below to add one.', 'sop' ); ?></td>
                        </tr>
                        <?php
                    endif;
                    ?>
                </tbody>
            </table>

            <hr />

            <h3>
                <?php
                if ( $editing ) {
                    esc_html_e( 'Edit supplier', 'sop' );
                } else {
                    esc_html_e( 'Add new supplier', 'sop' );
                }
                ?>
            </h3>

            <?php
            $editing_id_val      = 0;
            $name_val            = '';
            $slug_val            = '';
            $currency_val        = 'GBP';
            $lead_time_val       = 0;
            $active_val          = 1;
            $buffer_override_val = '';

            if ( $editing ) {
                $editing_id_val    = (int) $editing->id;
                $name_val          = $editing->name;
                $slug_val          = $editing->slug;
                $currency_val      = $editing->currency;
                $lead_time_val     = (int) $editing->lead_time_weeks;
                $active_val        = (int) $editing->is_active;
                $settings_arr = $editing->settings_json ? json_decode( $editing->settings_json, true ) : array();
                if ( is_array( $settings_arr ) && isset( $settings_arr['buffer_months_override'] ) ) {
                    $buffer_override_val = (float) $settings_arr['buffer_months_override'];
                }
            }
            ?>

            <form method="post">
                <?php wp_nonce_field( 'sop_save_supplier', 'sop_supplier_nonce' ); ?>
                <input type="hidden" name="sop_supplier_action" value="save" />
                <input type="hidden" name="sop_supplier_id" value="<?php echo esc_attr( $editing_id_val ); ?>" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_name">
                                    <?php esc_html_e( 'Name', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_name"
                                       name="sop_supplier_name"
                                       value="<?php echo esc_attr( $name_val ); ?>"
                                       class="regular-text"
                                       required />
                                <p class="description">
                                    <?php esc_html_e( 'Internal supplier name (e.g. "Shiny International").', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_slug">
                                    <?php esc_html_e( 'Slug', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="sop_supplier_slug"
                                       name="sop_supplier_slug"
                                       value="<?php echo esc_attr( $slug_val ); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Optional. If left blank, it is generated from the name.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_currency">
                                    <?php esc_html_e( 'Currency', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="sop_supplier_currency" name="sop_supplier_currency">
                                    <option value="GBP" <?php selected( $currency_val, 'GBP' ); ?>>GBP</option>
                                    <option value="RMB" <?php selected( $currency_val, 'RMB' ); ?>>RMB</option>
                                    <option value="EUR" <?php selected( $currency_val, 'EUR' ); ?>>EUR</option>
                                    <option value="USD" <?php selected( $currency_val, 'USD' ); ?>>USD</option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Supplier billing currency. Used for future cost and export logic.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_lead_time_weeks">
                                    <?php esc_html_e( 'Lead time (weeks)', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="sop_supplier_lead_time_weeks"
                                       name="sop_supplier_lead_time_weeks"
                                       value="<?php echo esc_attr( $lead_time_val ); ?>"
                                       min="0"
                                       class="small-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Approximate time from placing order to goods arriving, in weeks.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sop_supplier_buffer_months_override">
                                    <?php esc_html_e( 'Stock buffer override (months)', 'sop' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       step="0.1"
                                       id="sop_supplier_buffer_months_override"
                                       name="sop_supplier_buffer_months_override"
                                       value="<?php echo esc_attr( $buffer_override_val ); ?>"
                                       min="0"
                                       class="small-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Optional override for this supplier. Leave blank to use the global buffer months.', 'sop' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Active', 'sop' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="sop_supplier_is_active"
                                           value="1" <?php checked( 1, (int) $active_val ); ?> />
                                    <?php esc_html_e( 'Supplier is active and should be included in forecast/order workflows.', 'sop' ); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                if ( $editing ) {
                    submit_button( __( 'Update supplier', 'sop' ) );
                } else {
                    submit_button( __( 'Add supplier', 'sop' ) );
                }
                ?>
            </form>
        </div>
        <?php
    }
}

endif; // class_exists

// Bootstrap the admin settings class.
if ( is_admin() ) {
    new sop_Admin_Settings();
}

/**
 * Global helper to get Stock Order Plugin settings (with defaults).
 *
 * @return array
 */
function sop_get_settings() {

    if ( class_exists( 'sop_Admin_Settings' ) ) {
        return sop_Admin_Settings::get_settings();
    }

    // Fallback (should not normally happen if class exists).
    return array(
        'analysis_lookback_days'   => 365,
        'buffer_months_global'     => 6,
        'rmb_to_gbp_rate'          => '',
        'eur_to_gbp_rate'          => '',
        'usd_to_gbp_rate'          => '',
        'show_suggested_vs_max'    => 1,
    );
}

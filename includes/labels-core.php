<?php
/**
 * Stock Order Plugin - Labels & Barcodes core helpers
 * File version: 1.0.5
 *
 * Provides defaults, sanitization, and helper accessors for label settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_labels_get_default_settings' ) ) {
    /**
     * Defaults for label settings.
     *
     * @return array
     */
    function sop_labels_get_default_settings() {
        return array(
            'default_label_width_mm'  => 50,
            'default_label_height_mm' => 25,
            'include_date'            => 1,
        );
    }
}

if ( ! function_exists( 'sop_labels_sanitize_settings' ) ) {
    /**
     * Sanitize label settings input.
     *
     * @param array $input Raw input.
     * @return array Clean settings.
     */
    function sop_labels_sanitize_settings( $input ) {
        $defaults = sop_labels_get_default_settings();
        $output   = $defaults;

        $input = is_array( $input ) ? $input : array();

        $min_dim = 10;
        $max_dim = 150;

        if ( isset( $input['default_label_width_mm'] ) ) {
            $width = (float) $input['default_label_width_mm'];
            if ( $width < $min_dim ) {
                $width = $min_dim;
            } elseif ( $width > $max_dim ) {
                $width = $max_dim;
            }
            $output['default_label_width_mm'] = $width;
        }

        if ( isset( $input['default_label_height_mm'] ) ) {
            $height = (float) $input['default_label_height_mm'];
            if ( $height < $min_dim ) {
                $height = $min_dim;
            } elseif ( $height > $max_dim ) {
                $height = $max_dim;
            }
            $output['default_label_height_mm'] = $height;
        }

        if ( array_key_exists( 'include_date', $input ) ) {
            $output['include_date'] = empty( $input['include_date'] ) ? 0 : 1;
        } elseif ( array_key_exists( 'include_order_number', $input ) ) {
            // Backward compatibility for legacy key.
            $output['include_date'] = empty( $input['include_order_number'] ) ? 0 : 1;
        } elseif ( array_key_exists( 'include_sheet_number', $input ) ) {
            // Legacy legacy key.
            $output['include_date'] = empty( $input['include_sheet_number'] ) ? 0 : 1;
        } else {
            $output['include_date'] = 1;
        }

        return $output;
    }
}

if ( ! function_exists( 'sop_labels_get_settings' ) ) {
    /**
     * Get labels settings merged with defaults.
     *
     * @return array
     */
    function sop_labels_get_settings() {
        $defaults = sop_labels_get_default_settings();
        $stored   = get_option( 'sop_labels_settings', array() );
        $stored   = is_array( $stored ) ? $stored : array();
        // Back-compat: map legacy toggles to include_date if needed.
        if ( ! isset( $stored['include_date'] ) ) {
            if ( isset( $stored['include_order_number'] ) ) {
                $stored['include_date'] = ! empty( $stored['include_order_number'] ) ? 1 : 0;
            } elseif ( isset( $stored['include_sheet_number'] ) ) {
                $stored['include_date'] = ! empty( $stored['include_sheet_number'] ) ? 1 : 0;
            }
        }
        unset( $stored['include_order_number'], $stored['include_sheet_number'], $stored['include_qty'] );
        return wp_parse_args( $stored, $defaults );
    }
}

if ( ! function_exists( 'sop_labels_get_global_label_size_mm' ) ) {
    /**
     * Get global label size in mm.
     *
     * @return array{width_mm:float,height_mm:float}
     */
    function sop_labels_get_global_label_size_mm() {
        $settings = sop_labels_get_settings();
        return array(
            'width_mm'  => isset( $settings['default_label_width_mm'] ) ? (float) $settings['default_label_width_mm'] : 50.0,
            'height_mm' => isset( $settings['default_label_height_mm'] ) ? (float) $settings['default_label_height_mm'] : 25.0,
        );
    }
}

if ( ! function_exists( 'sop_labels_get_current_date_mm_yy' ) ) {
    /**
     * Get current date in MM/YY format.
     *
     * @return string
     */
    function sop_labels_get_current_date_mm_yy() {
        $ts = current_time( 'timestamp' );
        return date_i18n( 'm/y', $ts );
    }
}

/**
 * Frontend: render a print-ready product label when requested.
 */
add_action( 'template_redirect', 'sop_labels_maybe_render_product_label' );

if ( ! function_exists( 'sop_labels_maybe_render_product_label' ) ) {
    /**
     * Output a single product label view when ?sop_print_label=1 is present.
     *
     * @return void
     */
    function sop_labels_maybe_render_product_label() {
        if ( ! isset( $_GET['sop_print_label'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        if ( ! function_exists( 'is_singular' ) || ! is_singular( 'product' ) ) {
            return;
        }

        $product_id = get_the_ID();
        if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        $sku = (string) $product->get_sku();
        if ( '' === $sku ) {
            wp_die( esc_html__( 'This product has no SKU; label cannot be generated.', 'sop' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        $name         = (string) $product->get_name();
        $settings     = sop_labels_get_settings();
        $include_date = ! empty( $settings['include_date'] );
        $date_display = $include_date ? sop_labels_get_current_date_mm_yy() : '';
        $size         = sop_labels_get_global_label_size_mm();
        $w_mm         = isset( $size['width_mm'] ) ? (float) $size['width_mm'] : 50.0;
        $h_mm         = isset( $size['height_mm'] ) ? (float) $size['height_mm'] : 25.0;

        $brand_logo_html = '';
        $brand_name      = '';

        $terms = wp_get_post_terms( $product_id, 'product_brand' );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $brand        = $terms[0];
            $brand_name   = isset( $brand->name ) ? (string) $brand->name : '';
            $possible_ids = array(
                get_term_meta( $brand->term_id, 'thumbnail_id', true ),
                get_term_meta( $brand->term_id, 'brand_logo_id', true ),
                get_term_meta( $brand->term_id, 'logo_id', true ),
                get_term_meta( $brand->term_id, 'image_id', true ),
            );
            foreach ( $possible_ids as $maybe_id ) {
                $maybe_id = absint( $maybe_id );
                if ( $maybe_id > 0 ) {
                    $src = wp_get_attachment_image_src( $maybe_id, 'full' );
                    if ( ! empty( $src[0] ) ) {
                        $brand_logo_html = '<img src="' . esc_url( $src[0] ) . '" alt="' . esc_attr( $brand_name ) . '" />';
                        break;
                    }
                }
            }
        }

        if ( '' === $brand_logo_html && '' !== $brand_name ) {
            $brand_logo_html = '<span class="sop-label-brand-text">' . esc_html( strtoupper( $brand_name ) ) . '</span>';
        }

        $barcode_svg = function_exists( 'sop_barcode_code128_svg' ) ? sop_barcode_code128_svg( $sku, array( 'quiet_zone_modules' => 10, 'height_modules' => 60 ) ) : '';

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page { size: <?php echo esc_html( $w_mm ); ?>mm <?php echo esc_html( $h_mm ); ?>mm; margin: 0; }
        body {
            margin: 0;
            background: #f7f7f7;
            font-family: "Helvetica Neue", Arial, sans-serif;
        }
        .sop-label-page {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 12px;
        }
        .sop-label {
            box-sizing: border-box;
            width: <?php echo esc_html( $w_mm ); ?>mm;
            height: <?php echo esc_html( $h_mm ); ?>mm;
            border: 1px solid #000;
            border-radius: 2mm;
            background: #fffef4;
            padding: 1.5mm 2mm 2mm;
            position: relative;
        }
        .sop-label-top {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 8mm;
        }
        .sop-label-logo img {
            max-height: 7mm;
            width: auto;
            display: block;
            margin: 0 auto;
        }
        .sop-label-brand-text {
            font-weight: 700;
            letter-spacing: 0.05em;
            font-size: 12px;
        }
        .sop-label-date {
            position: absolute;
            right: 0;
            top: 0;
            font-weight: 700;
            font-size: 12px;
        }
        .sop-label-title {
            margin-top: 1mm;
            margin-bottom: 2mm;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .sop-label-barcode {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1mm;
        }
        .sop-label-barcode svg {
            width: 90%;
            height: 10mm;
            display: block;
        }
        .sop-label-sku {
            text-align: center;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
            white-space: pre;
        }
        .sop-print-toolbar {
            padding: 8px 12px;
            background: #f0f0f0;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sop-print-toolbar button {
            padding: 6px 10px;
            font-size: 14px;
            cursor: pointer;
        }
        @media print {
            body { background: #fff; }
            .sop-print-toolbar { display: none; }
            .sop-label-page { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="sop-print-toolbar">
        <button type="button" onclick="window.print();"><?php esc_html_e( 'Print label', 'sop' ); ?></button>
    </div>
    <div class="sop-label-page">
        <div class="sop-label">
            <div class="sop-label-top">
                <div class="sop-label-logo"><?php echo wp_kses_post( $brand_logo_html ); ?></div>
                <?php if ( $include_date && $date_display ) : ?>
                    <div class="sop-label-date"><?php echo esc_html( $date_display ); ?></div>
                <?php endif; ?>
            </div>
            <div class="sop-label-title"><?php echo esc_html( $name ); ?></div>
            <div class="sop-label-barcode">
                <?php echo $barcode_svg ? $barcode_svg : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="sop-label-sku"><?php echo esc_html( $sku ); ?></div>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

<?php
/**
 * Stock Order Plugin - Labels & Barcodes core helpers
 * File version: 1.0.13
 *
 * Provides defaults, sanitization, helper accessors, SVG barcode cache/API, and in-house print label view.
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

/* -------------------------------------------------------------------------
 * Barcode core (SVG-only, Code128) with disk cache
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'sop_barcode_get_cache_dir' ) ) {
    /**
     * Get (and ensure) the barcode cache directory.
     *
     * @return array{dir:string,url:string}|WP_Error
     */
    function sop_barcode_get_cache_dir() {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return new WP_Error( 'sop_barcode_cache_dir', $uploads['error'] );
        }

        $base_dir = trailingslashit( $uploads['basedir'] ) . 'sop-barcodes/';
        $base_url = trailingslashit( $uploads['baseurl'] ) . 'sop-barcodes/';

        if ( ! file_exists( $base_dir ) ) {
            wp_mkdir_p( $base_dir );
        }

        if ( ! is_dir( $base_dir ) || ! is_writable( $base_dir ) ) {
            return new WP_Error( 'sop_barcode_cache_dir', __( 'Barcode cache directory is not writable.', 'sop' ) );
        }

        return array(
            'dir' => $base_dir,
            'url' => $base_url,
        );
    }
}

if ( ! function_exists( 'sop_barcode_normalise_args' ) ) {
    /**
     * Normalise barcode args (only accepted keys are kept).
     *
     * @param array $args Raw args.
     * @return array Normalised args.
     */
    function sop_barcode_normalise_args( $args ) {
        $args = is_array( $args ) ? $args : array();

        $defaults = array(
            'width'              => '100%',
            'height'             => '100%',
            'dpi'                => 96,
            'quiet_zone_modules' => 10,
            'height_modules'     => 60,
        );

        $out = $defaults;

        // width/height can be ints or percent strings.
        foreach ( array( 'width', 'height' ) as $dim ) {
            if ( isset( $args[ $dim ] ) ) {
                $val = $args[ $dim ];
                if ( is_numeric( $val ) ) {
                    $out[ $dim ] = (string) (int) $val;
                } elseif ( is_string( $val ) && preg_match( '/^\d+%$/', $val ) ) {
                    $out[ $dim ] = $val;
                }
            }
        }

        if ( isset( $args['dpi'] ) ) {
            $dpi = (int) $args['dpi'];
            $dpi = min( 600, max( 72, $dpi ) );
            $out['dpi'] = $dpi;
        }

        if ( isset( $args['quiet_zone_modules'] ) ) {
            $qz = (int) $args['quiet_zone_modules'];
            $qz = min( 30, max( 0, $qz ) );
            $out['quiet_zone_modules'] = $qz;
        }

        if ( isset( $args['height_modules'] ) ) {
            $hm = (int) $args['height_modules'];
            $hm = min( 200, max( 10, $hm ) );
            $out['height_modules'] = $hm;
        }

        return $out;
    }
}

if ( ! function_exists( 'sop_barcode_build_cache_key' ) ) {
    /**
     * Build cache key hash.
     *
     * @param string $sku  SKU.
     * @param array  $args Args.
     * @return string
     */
    function sop_barcode_build_cache_key( $sku, array $args ) {
        $normalized = sop_barcode_normalise_args( $args );
        $payload    = wp_json_encode( array( 'sku' => $sku, 'args' => $normalized ) );
        return md5( $payload );
    }
}

if ( ! function_exists( 'sop_barcode_get_cache_path' ) ) {
    /**
     * Get cache file path/url for a SKU + args.
     *
     * @param string $sku  SKU.
     * @param array  $args Args.
     * @return array{path:string,url:string}|WP_Error
     */
    function sop_barcode_get_cache_path( $sku, array $args ) {
        $cache_dir = sop_barcode_get_cache_dir();
        if ( is_wp_error( $cache_dir ) ) {
            return $cache_dir;
        }

        $hash      = sop_barcode_build_cache_key( $sku, $args );
        $safe_sku  = sanitize_title( $sku );
        if ( '' === $safe_sku ) {
            $safe_sku = 'sku';
        }
        $filename  = $safe_sku . '--' . $hash . '.svg';

        return array(
            'path' => $cache_dir['dir'] . $filename,
            'url'  => $cache_dir['url'] . $filename,
        );
    }
}

if ( ! function_exists( 'sop_barcode_read_cached_svg' ) ) {
    /**
     * Read cached SVG.
     *
     * @param string $sku  SKU.
     * @param array  $args Args.
     * @return string|false
     */
    function sop_barcode_read_cached_svg( $sku, array $args ) {
        $paths = sop_barcode_get_cache_path( $sku, $args );
        if ( is_wp_error( $paths ) ) {
            return false;
        }

        if ( ! file_exists( $paths['path'] ) ) {
            return false;
        }

        $svg = file_get_contents( $paths['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( ! $svg ) {
            return false;
        }

        return $svg;
    }
}

if ( ! function_exists( 'sop_barcode_write_cached_svg' ) ) {
    /**
     * Write SVG to cache.
     *
     * @param string $sku  SKU.
     * @param array  $args Args.
     * @param string $svg  SVG contents.
     * @return bool
     */
    function sop_barcode_write_cached_svg( $sku, array $args, $svg ) {
        $paths = sop_barcode_get_cache_path( $sku, $args );
        if ( is_wp_error( $paths ) ) {
            return false;
        }

        $svg = (string) $svg;
        if ( '' === trim( $svg ) ) {
            return false;
        }

        $bytes = file_put_contents( $paths['path'], $svg, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
        return ( false !== $bytes );
    }
}

if ( ! function_exists( 'sop_get_barcode_svg' ) ) {
    /**
     * Retrieve cached barcode SVG for SKU + args. Does NOT generate on miss.
     *
     * @param string $sku  SKU (raw input).
     * @param array  $args Barcode args.
     * @return string|WP_Error SVG string or error.
     */
    function sop_get_barcode_svg( $sku, $args = array() ) {
        $sku = (string) $sku;
        if ( function_exists( 'sop_normalise_scan_input' ) ) {
            $sku = sop_normalise_scan_input( $sku );
        } else {
            $sku = trim( $sku );
        }

        if ( '' === $sku ) {
            return new WP_Error( 'sop_barcode_empty_sku', __( 'Empty SKU; cannot load barcode.', 'sop' ) );
        }

        // Validate SKU exists.
        if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
            $pid = wc_get_product_id_by_sku( $sku );
            if ( ! $pid ) {
                return new WP_Error( 'sop_barcode_unknown_sku', __( 'Unknown SKU; cannot load barcode.', 'sop' ) );
            }
        }

        $args = sop_barcode_normalise_args( $args );
        $svg  = sop_barcode_read_cached_svg( $sku, $args );

        if ( false === $svg ) {
            return new WP_Error( 'sop_barcode_not_cached', __( 'Barcode not cached yet. Generate before use.', 'sop' ) );
        }

        return $svg;
    }
}

if ( ! function_exists( 'sop_barcode_generate_and_cache_svg' ) ) {
    /**
     * Generate and cache a barcode SVG (explicit use; NOT called automatically).
     *
     * @param string $sku  SKU.
     * @param array  $args Args for generator/cache.
     * @return string|WP_Error SVG string or error.
     */
    function sop_barcode_generate_and_cache_svg( $sku, $args = array() ) {
        $sku = (string) $sku;
        if ( function_exists( 'sop_normalise_scan_input' ) ) {
            $sku = sop_normalise_scan_input( $sku );
        } else {
            $sku = trim( $sku );
        }

        if ( '' === $sku ) {
            return new WP_Error( 'sop_barcode_empty_sku', __( 'Empty SKU; cannot generate barcode.', 'sop' ) );
        }

        $args = sop_barcode_normalise_args( $args );

        if ( ! function_exists( 'sop_barcode_code128_svg' ) ) {
            return new WP_Error( 'sop_barcode_generator_missing', __( 'Barcode generator unavailable.', 'sop' ) );
        }

        $svg = sop_barcode_code128_svg(
            $sku,
            array(
                'quiet_zone_modules' => $args['quiet_zone_modules'],
                'height_modules'     => $args['height_modules'],
            )
        );

        if ( '' === $svg ) {
            return new WP_Error( 'sop_barcode_generate_failed', __( 'Failed to generate barcode.', 'sop' ) );
        }

        $written = sop_barcode_write_cached_svg( $sku, $args, $svg );
        if ( ! $written ) {
            return new WP_Error( 'sop_barcode_cache_write_failed', __( 'Failed to write barcode cache.', 'sop' ) );
        }

        return $svg;
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
                        $brand_logo_html = '<img class="sop-label-logo-img" src="' . esc_url( $src[0] ) . '" alt="' . esc_attr( $brand_name ) . '" />';
                        break;
                    }
                }
            }
        }

        if ( '' === $brand_logo_html && '' !== $brand_name ) {
            $brand_logo_html = '<span class="sop-label-logo-text">' . esc_html( strtoupper( $brand_name ) ) . '</span>';
        }

        $barcode_args = array(
            'width'              => '100%',
            'height'             => '100%',
            'dpi'                => 96,
            'quiet_zone_modules' => 10,
            'height_modules'     => 60,
        );
        $barcode_svg  = function_exists( 'sop_get_barcode_svg' ) ? sop_get_barcode_svg( $sku, $barcode_args ) : new WP_Error( 'sop_barcode_api_missing', __( 'Barcode API unavailable.', 'sop' ) );

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --label-w: <?php echo esc_html( $w_mm ); ?>mm;
            --label-h: <?php echo esc_html( $h_mm ); ?>mm;
            --pad: 0.5mm;
            --toprow-h: 6mm;
            --barcode-h: 10mm;
            --sku-h: 3mm;
            --title-h: calc(var(--label-h) - (var(--pad) * 2) - var(--toprow-h) - var(--barcode-h) - var(--sku-h));
        }
        @page {
            size: var(--label-w) var(--label-h);
            margin: 0;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            font-family: "Helvetica Neue", Arial, sans-serif;
        }
        .sop-print-toolbar {
            padding: 6px 10px;
            background: #f0f0f0;
            border-bottom: 1px solid #ddd;
        }
        .sop-print-toolbar button {
            padding: 6px 10px;
            font-size: 14px;
            cursor: pointer;
        }
        .sop-label-stage {
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 12px;
            box-sizing: border-box;
        }
        .sop-label-page {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            width: var(--label-w);
            height: auto;
        }
        .sop-label {
            width: var(--label-w);
            height: var(--label-h);
            box-sizing: border-box;
            display: grid;
            grid-template-rows: var(--toprow-h) var(--title-h) var(--barcode-h) var(--sku-h);
            padding: var(--pad);
            background: #fff;
            overflow: hidden;
        }
        @media screen {
            .sop-label {
                outline: 1px solid rgba(0,0,0,0.15);
                transform: scale(2);
                transform-origin: top center;
            }
            .sop-label-stage {
                overflow: visible;
            }
        }
        @media print {
            html, body {
                width: var(--label-w);
                height: var(--label-h);
                overflow: hidden;
            }
            .sop-print-toolbar { display: none !important; }
            .sop-label-stage {
                padding: 0;
                min-height: 0;
            }
            .sop-label-page {
                width: var(--label-w);
                height: var(--label-h);
            }
            .sop-label {
                outline: none !important;
                transform: none !important;
            }
            body { background: #fff; }
        }
        .sop-label__top {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .sop-label__logo-viewport {
            width: 70%;
            height: 100%;
            margin: 0 auto;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sop-label-logo-img {
            height: 100%;
            width: auto;
            display: block;
            transform: scale(2.6);
            transform-origin: 50% 50%;
        }
        .sop-label-logo-text {
            font-weight: 700;
            letter-spacing: 0.04em;
            font-size: 2.2mm;
            text-align: center;
            line-height: 1;
        }
        .sop-label-date {
            position: absolute;
            right: 0;
            top: 0;
            font-weight: 700;
            font-size: 1.8mm;
            line-height: 1;
        }
        .sop-label-title {
            text-align: center;
            font-weight: 700;
            font-size: 2.2mm;
            line-height: 1.05;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            align-self: center;
        }
        .sop-label-barcode {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 0;
        }
        .sop-label-barcode svg {
            width: 90%;
            height: 10mm;
            display: block;
        }
        .sop-label-sku {
            text-align: center;
            font-weight: 700;
            font-size: 2.5mm;
            letter-spacing: 0.02em;
            white-space: pre;
            line-height: 1;
            align-self: center;
        }
    </style>
</head>
<body>
    <div class="sop-print-toolbar">
        <button type="button" onclick="window.print();"><?php esc_html_e( 'Print label', 'sop' ); ?></button>
    </div>
    <div class="sop-label-stage">
        <div class="sop-label-page">
            <div class="sop-label">
                <div class="sop-label__top">
                    <div class="sop-label__logo-viewport">
                        <?php echo wp_kses_post( $brand_logo_html ); ?>
                    </div>
                    <?php if ( $include_date && $date_display ) : ?>
                        <div class="sop-label-date"><?php echo esc_html( $date_display ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="sop-label-title"><?php echo esc_html( $name ); ?></div>
            <div class="sop-label-barcode">
                <?php
                if ( is_wp_error( $barcode_svg ) ) {
                    echo '<div style="width:90%;text-align:center;font-size:2.2mm;line-height:1.2;">' . esc_html__( 'Barcode not cached yet — update product to generate.', 'sop' ) . '</div>';
                } else {
                    echo $barcode_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </div>
                <div class="sop-label-sku"><?php echo esc_html( $sku ); ?></div>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

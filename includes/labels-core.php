<?php
/**
 * Stock Order Plugin - Labels & Barcodes core helpers
 * File version: 1.0.23
 *
 * Provides defaults, sanitization, helper accessors, SVG barcode cache/API, AJAX barcode access, cache warm-up, batch labels, and in-house print label view.
 * Changelog:
 * - 1.0.23 - Fix bulk label print splitting (hide headers in print, prevent page breaks inside labels).
 * - 1.0.22 - Bulk labels reuse single template/CSS; fix print sizing and barcode rendering.
 * - 1.0.21 - Bulk labels reuse single template (label-sized pages), shared label renderer; alias bulk action.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_labels_get_brand_logo_html' ) ) {
    /**
     * Get brand logo HTML and brand name for a product.
     *
     * @param int $product_id Product ID.
     * @return array{html:string,name:string}
     */
    function sop_labels_get_brand_logo_html( $product_id ) {
        $product_id = (int) $product_id;
        $html       = '';
        $brand_name = '';

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
                        $html = '<img class="sop-label-logo-img" src="' . esc_url( $src[0] ) . '" alt="' . esc_attr( $brand_name ) . '" />';
                        break;
                    }
                }
            }
        }

        if ( '' === $html && '' !== $brand_name ) {
            $html = '<span class="sop-label-logo-text">' . esc_html( strtoupper( $brand_name ) ) . '</span>';
        }

        return array(
            'html' => $html,
            'name' => $brand_name,
        );
    }
}

if ( ! function_exists( 'sop_labels_render_label_html' ) ) {
    /**
     * Render the HTML for a single label using the shared template.
     *
     * @param array $data Label data (sku, product_name, brand_logo_html, include_date, date_mm_yy, barcode_svg).
     * @return string
     */
    function sop_labels_render_label_html( $data ) {
        $sku             = isset( $data['sku'] ) ? (string) $data['sku'] : '';
        $name            = isset( $data['product_name'] ) ? (string) $data['product_name'] : '';
        $brand_logo_html = isset( $data['brand_logo_html'] ) ? (string) $data['brand_logo_html'] : '';
        $include_date    = ! empty( $data['include_date'] );
        $date_display    = isset( $data['date_mm_yy'] ) ? (string) $data['date_mm_yy'] : '';
        $barcode_svg     = isset( $data['barcode_svg'] ) ? $data['barcode_svg'] : '';

        ob_start();
        ?>
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
                    echo '<div style="width:90%;text-align:center;font-size:2.2mm;line-height:1.2;">' . esc_html__( 'Barcode not cached yet - update product to generate.', 'sop' ) . '</div>';
                } else {
                    echo $barcode_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </div>
            <div class="sop-label-sku"><?php echo esc_html( $sku ); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if ( ! function_exists( 'sop_labels_get_label_css' ) ) {
    /**
     * Get the shared label CSS for single/bulk labels.
     *
     * @param float $w_mm Label width in mm.
     * @param float $h_mm Label height in mm.
     * @return string CSS wrapped in <style> tag.
     */
    function sop_labels_get_label_css( $w_mm, $h_mm ) {
        $w_mm = (float) $w_mm;
        $h_mm = (float) $h_mm;

        ob_start();
        ?>
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
            * { box-sizing: border-box; }
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
        <?php
        return ob_get_clean();
    }
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

if ( ! function_exists( 'sop_barcode_delete_cached_svgs_for_sku' ) ) {
    /**
     * Delete all cached barcode SVGs for a SKU.
     *
     * @param string $sku SKU.
     * @return int Number of files deleted.
     */
    function sop_barcode_delete_cached_svgs_for_sku( $sku ) {
        if ( function_exists( 'sop_normalise_scan_input' ) ) {
            $sku = sop_normalise_scan_input( $sku );
        } else {
            $sku = trim( (string) $sku );
        }

        if ( '' === $sku ) {
            return 0;
        }

        $cache_dir = sop_barcode_get_cache_dir();
        if ( is_wp_error( $cache_dir ) ) {
            return 0;
        }

        $default_args = function_exists( 'sop_barcode_normalise_args' ) ? sop_barcode_normalise_args( array() ) : array();
        $sample       = sop_barcode_get_cache_path( $sku, $default_args );
        if ( is_wp_error( $sample ) || empty( $sample['path'] ) ) {
            return 0;
        }

        $basename = basename( $sample['path'] );
        $prefix   = strstr( $basename, '--', true );
        if ( false === $prefix || '' === $prefix ) {
            return 0;
        }

        $pattern = trailingslashit( $cache_dir['dir'] ) . $prefix . '--*.svg';
        $files   = glob( $pattern ); // phpcs:ignore WordPress.WP.AlternativeFunctions.glob_glob
        if ( ! is_array( $files ) ) {
            return 0;
        }

        $deleted = 0;
        foreach ( $files as $file ) {
            $file = (string) $file;
            if ( '' === $file ) {
                continue;
            }
            // Safety: ensure file is within cache dir.
            if ( 0 !== strpos( realpath( $file ), realpath( $cache_dir['dir'] ) ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_realpath
                continue;
            }
            if ( file_exists( $file ) ) {
                unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                $deleted++;
            }
        }

        return $deleted;
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

if ( ! function_exists( 'sop_labels_get_product_location' ) ) {
    /**
     * Get product location string from common meta keys.
     *
     * @param int $product_id Product ID.
     * @return string
     */
    function sop_labels_get_product_location( $product_id ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            return '';
        }

        $keys = array(
            '_product_location',
            'product_location',
            '_sop_location',
            'sop_location',
            '_location',
            'location',
            '_warehouse_location',
            'warehouse_location',
        );

        $found = '';
        foreach ( $keys as $key ) {
            $val = get_post_meta( $product_id, $key, true );
            if ( '' !== trim( (string) $val ) ) {
                $found = trim( (string) $val );
                break;
            }
        }

        // Variation -> parent fallback.
        if ( '' === $found ) {
            $parent_id = wp_get_post_parent_id( $product_id );
            if ( $parent_id ) {
                foreach ( $keys as $key ) {
                    $val = get_post_meta( $parent_id, $key, true );
                    if ( '' !== trim( (string) $val ) ) {
                        $found = trim( (string) $val );
                        break;
                    }
                }
            }
        }

        return apply_filters( 'sop_labels_product_location', $found, $product_id );
    }
}

/**
 * AJAX: return cached barcode SVG (no inline generation).
 */
add_action( 'wp_ajax_sop_barcode', 'sop_ajax_sop_barcode' );

/**
 * Warm barcode cache via admin-post.
 */
add_action( 'admin_post_sop_barcode_warm_cache', 'sop_handle_barcode_warm_cache' );

/**
 * Admin-post: A4 batch label print.
 */
add_action( 'admin_post_sop_print_labels_a4', 'sop_handle_print_labels_a4' );
add_action( 'admin_post_sop_print_labels_bulk', 'sop_handle_print_labels_a4' );

/**
 * Generate cached barcodes on product save (products + variations).
 */
add_action( 'save_post_product', 'sop_barcode_maybe_cache_on_save', 20, 3 );
add_action( 'save_post_product_variation', 'sop_barcode_maybe_cache_on_save', 20, 3 );

/**
 * Shortcode for front-end A4 label print form.
 */
add_shortcode( 'sop_a4_labels_print_form', 'sop_shortcode_a4_labels_print_form' );

/**
 * Frontend: render a print-ready product label when requested.
 */
add_action( 'template_redirect', 'sop_labels_maybe_render_product_label' );

/**
 * Clean up cache on delete.
 */
add_action( 'before_delete_post', 'sop_barcode_cleanup_on_delete', 10, 1 );

if ( ! function_exists( 'sop_ajax_sop_barcode' ) ) {
    /**
     * AJAX handler for cached barcode SVG.
     *
     * @return void
     */
    function sop_ajax_sop_barcode() {
        if ( ! current_user_can( 'read' ) ) {
            status_header( 403 );
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo 'Forbidden';
            exit;
        }

        $raw_sku = isset( $_REQUEST['sku'] ) ? (string) wp_unslash( $_REQUEST['sku'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( function_exists( 'sop_normalise_scan_input' ) ) {
            $sku = sop_normalise_scan_input( $raw_sku );
        } else {
            $sku = trim( $raw_sku );
        }

        if ( '' === $sku ) {
            status_header( 400 );
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo 'Empty SKU';
            exit;
        }

        $raw_args  = array();
        $arg_keys  = array( 'width', 'height', 'dpi', 'quiet_zone_modules', 'height_modules' );
        foreach ( $arg_keys as $key ) {
            if ( isset( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $raw_args[ $key ] = wp_unslash( $_REQUEST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            }
        }
        if ( function_exists( 'sop_barcode_normalise_args' ) ) {
            $args = sop_barcode_normalise_args( $raw_args );
        } else {
            $args = $raw_args;
        }

        $svg = sop_get_barcode_svg( $sku, $args );
        if ( is_wp_error( $svg ) ) {
            $code = $svg->get_error_code();
            $msg  = $svg->get_error_message();
            switch ( $code ) {
                case 'sop_barcode_empty_sku':
                    $status = 400;
                    break;
                case 'sop_barcode_unknown_sku':
                    $status = 404;
                    break;
                case 'sop_barcode_not_cached':
                    $status = 409;
                    break;
                default:
                    $status = 500;
                    break;
            }
            status_header( $status );
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo esc_html( $msg );
            exit;
        }

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        status_header( 200 );
        header( 'Content-Type: image/svg+xml; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Cache-Control: private, max-age=86400' );
        echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}

if ( ! function_exists( 'sop_barcode_maybe_cache_on_save' ) ) {
    /**
     * Generate and cache barcode on product/variation save (no fatal on error).
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     * @return void
     */
    function sop_barcode_maybe_cache_on_save( $post_id, $post, $update ) {
        unset( $update );

        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $post_type = isset( $post->post_type ) ? $post->post_type : get_post_type( $post_id );
        if ( ! in_array( $post_type, array( 'product', 'product_variation' ), true ) ) {
            return;
        }

        $sku_raw = (string) get_post_meta( $post_id, '_sku', true );
        if ( function_exists( 'sop_normalise_scan_input' ) ) {
            $sku = sop_normalise_scan_input( $sku_raw );
        } else {
            $sku = trim( $sku_raw );
        }

        $prev_raw = (string) get_post_meta( $post_id, '_sop_barcode_last_sku', true );
        if ( function_exists( 'sop_normalise_scan_input' ) ) {
            $prev_sku = sop_normalise_scan_input( $prev_raw );
        } else {
            $prev_sku = trim( $prev_raw );
        }

        if ( '' === $sku ) {
            if ( '' !== $prev_sku ) {
                $owner = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $prev_sku ) : 0;
                if ( ! $owner || (int) $owner === (int) $post_id ) {
                    sop_barcode_delete_cached_svgs_for_sku( $prev_sku );
                }
                delete_post_meta( $post_id, '_sop_barcode_last_sku' );
            }
            return;
        }

        if ( '' !== $prev_sku && $prev_sku !== $sku ) {
            $owner = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $prev_sku ) : 0;
            if ( ! $owner || (int) $owner === (int) $post_id ) {
                sop_barcode_delete_cached_svgs_for_sku( $prev_sku );
            }
        }

        $args = function_exists( 'sop_barcode_normalise_args' ) ? sop_barcode_normalise_args( array() ) : array();

        $cached = false;
        if ( function_exists( 'sop_barcode_read_cached_svg' ) ) {
            $cached = ( false !== sop_barcode_read_cached_svg( $sku, $args ) );
        } elseif ( function_exists( 'sop_barcode_get_cache_path' ) ) {
            $paths  = sop_barcode_get_cache_path( $sku, $args );
            $cached = ( ! is_wp_error( $paths ) && ! empty( $paths['path'] ) && file_exists( $paths['path'] ) );
        }

        if ( $cached ) {
            return;
        }

        if ( function_exists( 'sop_barcode_generate_and_cache_svg' ) ) {
            $maybe = sop_barcode_generate_and_cache_svg( $sku, $args );
            if ( is_wp_error( $maybe ) ) {
                return;
            }
        }

        update_post_meta( $post_id, '_sop_barcode_last_sku', $sku );
    }
}

if ( ! function_exists( 'sop_handle_barcode_warm_cache' ) ) {
    /**
     * Admin-post handler to warm barcode cache in batches.
     *
     * @return void
     */
    function sop_handle_barcode_warm_cache() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Forbidden', 'sop' ) );
        }

        check_admin_referer( 'sop_barcode_warm_cache' );

        $offset = isset( $_GET['offset'] ) ? max( 0, (int) $_GET['offset'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $batch  = isset( $_GET['batch'] ) ? max( 10, min( 200, (int) $_GET['batch'] ) ) : 100; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $args_defaults = function_exists( 'sop_barcode_normalise_args' ) ? sop_barcode_normalise_args( array() ) : array();

        $query = new WP_Query(
            array(
                'post_type'      => array( 'product', 'product_variation' ),
                'post_status'    => array( 'publish', 'private' ),
                'posts_per_page' => $batch,
                'offset'         => $offset,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'fields'         => 'ids',
                'no_found_rows'  => false,
            )
        );

        $ids        = $query->posts;
        $total      = (int) $query->found_posts;
        $processed  = count( $ids );
        $generated  = isset( $_GET['generated'] ) ? (int) $_GET['generated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $skipped    = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $missing    = isset( $_GET['missing'] ) ? (int) $_GET['missing'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $errors_cnt = isset( $_GET['errors'] ) ? (int) $_GET['errors'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        foreach ( $ids as $id ) {
            $sku_raw = (string) get_post_meta( $id, '_sku', true );
            if ( function_exists( 'sop_normalise_scan_input' ) ) {
                $sku = sop_normalise_scan_input( $sku_raw );
            } else {
                $sku = trim( $sku_raw );
            }

            if ( '' === $sku ) {
                $missing++;
                continue;
            }

            update_post_meta( $id, '_sop_barcode_last_sku', $sku );

            $has_cache = false;
            if ( function_exists( 'sop_barcode_read_cached_svg' ) ) {
                $has_cache = ( false !== sop_barcode_read_cached_svg( $sku, $args_defaults ) );
            } elseif ( function_exists( 'sop_barcode_get_cache_path' ) ) {
                $paths     = sop_barcode_get_cache_path( $sku, $args_defaults );
                $has_cache = ( ! is_wp_error( $paths ) && ! empty( $paths['path'] ) && file_exists( $paths['path'] ) );
            }

            if ( $has_cache ) {
                $skipped++;
                continue;
            }

            if ( function_exists( 'sop_barcode_generate_and_cache_svg' ) ) {
                $result = sop_barcode_generate_and_cache_svg( $sku, $args_defaults );
                if ( is_wp_error( $result ) ) {
                    $errors_cnt++;
                } else {
                    $generated++;
                }
            } else {
                $errors_cnt++;
            }
        }

        $next = $offset + $processed;
        if ( $processed > 0 && $next < $total ) {
            $redirect = add_query_arg(
                array(
                    'action'    => 'sop_barcode_warm_cache',
                    'offset'    => $next,
                    'batch'     => $batch,
                    'generated' => $generated,
                    'skipped'   => $skipped,
                    'missing'   => $missing,
                    'errors'    => $errors_cnt,
                    '_wpnonce'  => isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '',
                ),
                admin_url( 'admin-post.php' )
            );
            wp_safe_redirect( $redirect );
            exit;
        }

        $redirect_target = wp_get_referer();
        if ( ! $redirect_target ) {
            $redirect_target = add_query_arg(
                array(
                    'page' => 'sop_stock_order',
                    'tab'  => 'labels',
                ),
                admin_url( 'admin.php' )
            );
        }

        $redirect = add_query_arg(
            array(
                'sop_barcode_warm_done' => 1,
                'generated'             => $generated,
                'skipped'               => $skipped,
                'missing'               => $missing,
                'errors'                => $errors_cnt,
            ),
            $redirect_target
        );

        wp_safe_redirect( $redirect );
        exit;
    }
}

if ( ! function_exists( 'sop_handle_print_labels_a4' ) ) {
    /**
     * Admin-post handler to render bulk labels (one label per page) reusing the single label template.
     *
     * @return void
     */
    function sop_handle_print_labels_a4() {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            status_header( 403 );
            wp_die( esc_html__( 'Forbidden', 'sop' ) );
        }

        // Optional nonce: if present, verify; if missing/invalid, continue (site is gated by capability).
        if ( isset( $_REQUEST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $maybe_nonce = (string) wp_unslash( $_REQUEST['_wpnonce'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wp_verify_nonce( $maybe_nonce, 'sop_print_labels_a4' );
        }

        $raw_lines    = isset( $_POST['sop_a4_skus'] ) ? (string) wp_unslash( $_POST['sop_a4_skus'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $default_qty  = isset( $_POST['sop_a4_default_qty'] ) ? (int) $_POST['sop_a4_default_qty'] : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $default_qty  = min( 100, max( 1, $default_qty ) );
        $lines        = preg_split( '/\\r\\n|\\r|\\n/', $raw_lines );
        $lines        = is_array( $lines ) ? $lines : array();
        $max_total    = 5000;
        $args_defaults = function_exists( 'sop_barcode_normalise_args' ) ? sop_barcode_normalise_args( array() ) : array(
            'quiet_zone_modules' => 10,
            'height_modules'     => 60,
        );
        $settings      = sop_labels_get_settings();
        $include_date  = ! empty( $settings['include_date'] );
        $date_display  = $include_date ? sop_labels_get_current_date_mm_yy() : '';

        $labels         = array();
        $unknown        = array();
        $barcode_errors = array();
        $seen_generation = array();

        foreach ( $lines as $raw_line ) {
            $raw_line = (string) $raw_line;
            $raw_line = trim( $raw_line );
            if ( '' === $raw_line ) {
                continue;
            }

            $qty = $default_qty;
            if ( preg_match( '/^(.+?)[,xX]\\s*(\\d+)$/', $raw_line, $m ) ) {
                $raw_line = trim( $m[1] );
                $qty      = (int) $m[2];
            }
            $qty = min( 500, max( 1, $qty ) );

            if ( function_exists( 'sop_normalise_scan_input' ) ) {
                $sku = sop_normalise_scan_input( $raw_line );
            } else {
                $sku = trim( $raw_line );
            }

            if ( '' === $sku ) {
                $unknown[] = $raw_line;
                continue;
            }

            $pid = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $sku ) : 0;
            if ( ! $pid ) {
                $unknown[] = $sku;
                continue;
            }

            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
            $name    = $product ? (string) $product->get_name() : '';

            $brand_logo   = sop_labels_get_brand_logo_html( $pid );
            $barcode_svg  = function_exists( 'sop_get_barcode_svg' ) ? sop_get_barcode_svg( $sku, $args_defaults ) : new WP_Error( 'sop_barcode_api_missing', __( 'Barcode API unavailable.', 'sop' ) );
            if ( is_wp_error( $barcode_svg ) && 'sop_barcode_not_cached' === $barcode_svg->get_error_code() && function_exists( 'sop_barcode_generate_and_cache_svg' ) ) {
                if ( ! isset( $seen_generation[ $sku ] ) ) {
                    $gen                      = sop_barcode_generate_and_cache_svg( $sku, $args_defaults );
                    $seen_generation[ $sku ] = true;
                    if ( ! is_wp_error( $gen ) ) {
                        $barcode_svg = sop_get_barcode_svg( $sku, $args_defaults );
                    }
                } else {
                    $barcode_svg = sop_get_barcode_svg( $sku, $args_defaults );
                }
            }

            if ( is_wp_error( $barcode_svg ) ) {
                $barcode_errors[] = $sku;
                continue;
            }

            $labels[] = array(
                'sku'             => $sku,
                'name'            => $name,
                'brand_logo_html' => $brand_logo['html'],
                'qty'             => $qty,
                'barcode_svg'     => $barcode_svg,
            );
        }

        // Expand qty respecting max_total.
        $expanded = array();
        foreach ( $labels as $label ) {
            $copies = (int) $label['qty'];
            for ( $i = 0; $i < $copies; $i++ ) {
                $expanded[] = $label;
                if ( count( $expanded ) >= $max_total ) {
                    break 2;
                }
            }
        }

        if ( empty( $expanded ) ) {
            wp_die( esc_html__( 'No labels to print.', 'sop' ) );
        }

        $size   = sop_labels_get_global_label_size_mm();
        $w_mm   = isset( $size['width_mm'] ) ? (float) $size['width_mm'] : 50.0;
        $h_mm   = isset( $size['height_mm'] ) ? (float) $size['height_mm'] : 25.0;

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <?php echo sop_labels_get_label_css( $w_mm, $h_mm ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <style>
        /* Bulk-only adjustments */
        .sop-bulk-stage {
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .sop-label-page {
            width: var(--label-w);
            height: var(--label-h);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            page-break-after: always;
            break-after: page;
            overflow: hidden;
        }
        .sop-label-page:last-child { page-break-after: auto; break-after: auto; }
        @media screen {
            .sop-label-page { margin-bottom: var(--label-h); }
        }
        @media print {
            /* Override single-label html/body clamp so multiple labels can print */
            html, body {
                width: auto !important;
                height: auto !important;
                overflow: visible !important;
                margin: 0;
                padding: 0;
            }
            .sop-bulk-stage { display: block !important; padding: 0 !important; margin: 0 !important; }
            .sop-label-page {
                display: block !important;
                page-break-inside: avoid;
                break-inside: avoid;
                overflow: hidden;
                margin: 0 !important;
                padding: 0 !important;
            }
            .sop-print-toolbar,
            .sop-bulk-warnings {
                display: none !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="sop-print-toolbar">
        <button type="button" onclick="window.print();"><?php esc_html_e( 'Print', 'sop' ); ?></button>
    </div>
    <div class="sop-bulk-stage">
        <?php if ( ! empty( $unknown ) || ! empty( $barcode_errors ) ) : ?>
            <div class="sop-bulk-warnings" style="font-size:13px;padding:8px 10px;background:#fff8e5;border:1px solid #f0d48a;color:#705300;width:100%;max-width:640px;">
                <?php if ( ! empty( $unknown ) ) : ?>
                    <div><?php printf( esc_html__( 'Unknown SKUs: %d', 'sop' ), count( $unknown ) ); ?></div>
                <?php endif; ?>
                <?php if ( ! empty( $barcode_errors ) ) : ?>
                    <div><?php printf( esc_html__( 'Barcode errors: %d', 'sop' ), count( $barcode_errors ) ); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach ( $expanded as $label ) : ?>
            <div class="sop-label-page">
                <?php
                echo sop_labels_render_label_html(
                    array(
                        'sku'             => $label['sku'],
                        'product_name'    => $label['name'],
                        'brand_logo_html' => $label['brand_logo_html'],
                        'include_date'    => $include_date,
                        'date_mm_yy'      => $date_display,
                        'barcode_svg'     => $label['barcode_svg'],
                    )
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

if ( ! function_exists( 'sop_shortcode_a4_labels_print_form' ) ) {
    /**
     * Shortcode: renders bulk labels print form for logged-in users.
     *
     * @param array $atts Shortcode atts.
     * @return string
     */
    function sop_shortcode_a4_labels_print_form( $atts ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return '<p>' . esc_html__( 'Please log in to print labels.', 'sop' ) . '</p>';
        }

        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" class="sop-a4-labels-form">
            <?php wp_nonce_field( 'sop_print_labels_a4' ); ?>
            <input type="hidden" name="action" value="sop_print_labels_a4" />
            <p>
                <label><?php esc_html_e( 'SKUs (one per line; optional ,qty or x qty)', 'sop' ); ?></label><br />
                <textarea name="sop_a4_skus" rows="8" cols="50" placeholder="SKU123,2&#10;SKU456 x 3"></textarea>
            </p>
            <p>
                <label><?php esc_html_e( 'Default quantity per SKU', 'sop' ); ?></label><br />
                <input type="number" name="sop_a4_default_qty" min="1" max="100" value="1" />
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Generate bulk labels', 'sop' ); ?></button>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }
}

if ( ! function_exists( 'sop_barcode_cleanup_on_delete' ) ) {
    /**
     * Delete cached barcodes when a product/variation is permanently deleted.
     *
     * @param int $post_id Post ID being deleted.
     * @return void
     */
    function sop_barcode_cleanup_on_delete( $post_id ) {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        $post_type = get_post_type( $post_id );
        if ( ! in_array( $post_type, array( 'product', 'product_variation' ), true ) ) {
            return;
        }

        $sku_current = (string) get_post_meta( $post_id, '_sku', true );
        $sku_prev    = (string) get_post_meta( $post_id, '_sop_barcode_last_sku', true );

        if ( function_exists( 'sop_normalise_scan_input' ) ) {
            $sku_current = sop_normalise_scan_input( $sku_current );
            $sku_prev    = sop_normalise_scan_input( $sku_prev );
        } else {
            $sku_current = trim( $sku_current );
            $sku_prev    = trim( $sku_prev );
        }

        $targets = array();
        if ( '' !== $sku_current ) {
            $targets[ $sku_current ] = true;
        }
        if ( '' !== $sku_prev ) {
            $targets[ $sku_prev ] = true;
        }

        foreach ( array_keys( $targets ) as $sku ) {
            $owner = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $sku ) : 0;
            if ( ! $owner || (int) $owner === (int) $post_id ) {
                sop_barcode_delete_cached_svgs_for_sku( $sku );
            }
        }

        delete_post_meta( $post_id, '_sop_barcode_last_sku' );
    }
}

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

        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            status_header( 403 );
            wp_die( esc_html__( 'Forbidden', 'sop' ) );
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

        $brand_logo      = sop_labels_get_brand_logo_html( $product_id );
        $brand_logo_html = $brand_logo['html'];

        $barcode_args = array(
            'width'              => '100%',
            'height'             => '100%',
            'dpi'                => 96,
            'quiet_zone_modules' => 10,
            'height_modules'     => 60,
        );
        $barcode_svg  = function_exists( 'sop_get_barcode_svg' ) ? sop_get_barcode_svg( $sku, $barcode_args ) : new WP_Error( 'sop_barcode_api_missing', __( 'Barcode API unavailable.', 'sop' ) );
        $label_html   = sop_labels_render_label_html(
            array(
                'sku'             => $sku,
                'product_name'    => $name,
                'brand_logo_html' => $brand_logo_html,
                'include_date'    => $include_date,
                'date_mm_yy'      => $date_display,
                'barcode_svg'     => $barcode_svg,
            )
        );

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo sop_labels_get_label_css( $w_mm, $h_mm ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</head>
<body>
    <div class="sop-print-toolbar">
        <button type="button" onclick="window.print();"><?php esc_html_e( 'Print label', 'sop' ); ?></button>
    </div>
    <div class="sop-label-stage">
        <div class="sop-label-page">
            <?php echo $label_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}



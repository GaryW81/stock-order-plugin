<?php
/**
 * Stock Order Plugin - Code128 (Subset B) barcode generator.
 * Phase: 0 - Utilities
 * File Version: 1.0.0
 *
 * Outputs Code128-B barcodes as SVG strings (no echo/print).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sop_barcode_code128_svg' ) ) {
    /**
     * Generate a Code128 Subset B barcode as SVG.
     *
     * @param string $data Data to encode (ASCII 32..126; spaces preserved).
     * @param array  $args {
     *     Optional args.
     *
     *     @type int $quiet_zone_modules Quiet zone on each side in modules. Default 10.
     *     @type int $height_modules     Barcode height in modules. Default 60.
     * }
     * @return string SVG markup.
     */
    function sop_barcode_code128_svg( $data, $args = array() ) {
        $args = is_array( $args ) ? $args : array();

        $quiet_zone = isset( $args['quiet_zone_modules'] ) ? max( 0, (int) $args['quiet_zone_modules'] ) : 10;
        $bar_height = isset( $args['height_modules'] ) ? max( 10, (int) $args['height_modules'] ) : 60;

        // Strip invalid characters but keep spaces.
        $filtered = '';
        $len      = strlen( $data );
        for ( $i = 0; $i < $len; $i++ ) {
            $ord = ord( $data[ $i ] );
            if ( $ord >= 32 && $ord <= 126 ) {
                $filtered .= $data[ $i ];
            }
        }

        if ( '' === $filtered ) {
            return '';
        }

        // Code set B patterns.
        $patterns = array(
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213', // 0-9.
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132', // 10-19.
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211', // 20-29.
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313', // 30-39.
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331', // 40-49.
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111', // 50-59.
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214', // 60-69.
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111', // 70-79.
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141', // 80-89.
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141', // 90-99.
            '114131','311141','411131','211412','211214','211232','2331112' // 100-106 (106 is stop code).
        );

        $start_code = 104; // Start B.
        $stop_code  = 106;

        $codes = array( $start_code );

        $position = 1;
        $data_len = strlen( $filtered );
        for ( $i = 0; $i < $data_len; $i++ ) {
            $code      = ord( $filtered[ $i ] ) - 32;
            $codes[]   = $code;
            $position++;
        }

        // Checksum.
        $checksum = $start_code;
        for ( $i = 1; $i < count( $codes ); $i++ ) {
            $checksum += $codes[ $i ] * $i;
        }
        $checksum = $checksum % 103;

        $codes[] = $checksum;
        $codes[] = $stop_code;

        // Build module sequence.
        $modules     = array();
        $total_width = $quiet_zone * 2; // quiet zones.
        foreach ( $codes as $code ) {
            if ( ! isset( $patterns[ $code ] ) ) {
                continue;
            }
            $pattern = $patterns[ $code ];
            $digits  = str_split( $pattern );
            $is_bar  = true;
            foreach ( $digits as $digit ) {
                $width = (int) $digit;
                if ( $is_bar ) {
                    $modules[] = $width;
                } else {
                    $modules[] = - $width; // negative for space.
                }
                $total_width += $width;
                $is_bar       = ! $is_bar;
            }
        }

        if ( $total_width <= 0 ) {
            return '';
        }

        // SVG build.
        $svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . esc_attr( $total_width ) . ' ' . esc_attr( $bar_height ) . '" preserveAspectRatio="none" role="img" aria-label="' . esc_attr( $filtered ) . '">';
        $svg .= '<rect width="100%" height="100%" fill="#fff"/>';

        $x = $quiet_zone;
        foreach ( $modules as $width ) {
            if ( $width > 0 ) {
                $svg .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $bar_height . '" fill="#000" />';
                $x   += $width;
            } else {
                $x += abs( $width );
            }
        }

        $svg .= '</svg>';

        return $svg;
    }
}

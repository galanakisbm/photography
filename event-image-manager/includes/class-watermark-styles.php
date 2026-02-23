<?php
/**
 * Class EIM_Watermark_Styles
 *
 * Provides multiple watermark templates that can be applied to images.
 *
 * Supported styles:
 *  - text        : Simple text watermark (custom text, size, color, opacity).
 *  - diagonal    : Diagonal text across the image.
 *  - corner      : Text or mini-logo in a chosen corner.
 *  - full-overlay: Semi-transparent overlay across the entire image.
 *  - logo        : Logo/image watermark composited onto the photo.
 *
 * The active style and its options are read from plugin settings
 * (stored via EIM_Admin_Settings) and can be overridden per-event via
 * the 'eim_watermark_style_options' filter.
 */
class EIM_Watermark_Styles {

    /**
     * Apply a watermark to a GD image resource according to plugin settings.
     *
     * @param  resource $image      GD image resource (already loaded).
     * @param  int      $post_id    Optional event post ID for per-event overrides.
     * @return resource             The same resource with the watermark applied.
     */
    public static function apply( $image, $post_id = 0 ) {
        $options = self::get_options( $post_id );
        $style   = isset( $options['style'] ) ? $options['style'] : 'text';

        switch ( $style ) {
            case 'diagonal':
                return self::apply_diagonal_text( $image, $options );
            case 'corner':
                return self::apply_corner( $image, $options );
            case 'full-overlay':
                return self::apply_full_overlay( $image, $options );
            case 'logo':
                return self::apply_logo( $image, $options );
            case 'text':
            default:
                return self::apply_text( $image, $options );
        }
    }

    // -------------------------------------------------------------------------
    // Style implementations
    // -------------------------------------------------------------------------

    /**
     * Simple text watermark at a configurable position.
     *
     * @param  resource $image
     * @param  array    $opts
     * @return resource
     */
    private static function apply_text( $image, array $opts ) {
        $text    = isset( $opts['text'] ) ? $opts['text'] : get_bloginfo( 'name' );
        // GD alpha: 0 = fully opaque, 127 = fully transparent (inverted from CSS opacity).
        $opacity = isset( $opts['opacity'] ) ? (int) $opts['opacity'] : 60;
        $color   = self::parse_color( isset( $opts['color'] ) ? $opts['color'] : '#ffffff', $opacity );
        $r       = $color[0]; $g = $color[1]; $b = $color[2]; $a = $color[3];

        $img_w    = imagesx( $image );
        $img_h    = imagesy( $image );
        $font_pct = isset( $opts['font_size_pct'] ) ? (int) $opts['font_size_pct'] : 5;
        $font     = 5; // GD built-in
        $char_w   = imagefontwidth( $font );
        $char_h   = imagefontheight( $font );
        $text_w   = strlen( $text ) * $char_w;
        $text_h   = $char_h;
        $padding  = (int) ( max( 10, $img_w * $font_pct / 100 ) * 0.5 );
        $position = isset( $opts['position'] ) ? $opts['position'] : 'bottom-right';

        list( $x, $y ) = self::corner_position( $position, $img_w, $img_h, $text_w, $text_h, $padding );

        $gdcolor = imagecolorallocatealpha( $image, $r, $g, $b, $a );
        imagestring( $image, $font, $x, $y, $text, $gdcolor );

        return $image;
    }

    /**
     * Diagonal text watermark repeating across the image (uses TTF if available).
     *
     * @param  resource $image
     * @param  array    $opts
     * @return resource
     */
    private static function apply_diagonal_text( $image, array $opts ) {
        $text    = isset( $opts['text'] ) ? $opts['text'] : get_bloginfo( 'name' );
        $opacity = isset( $opts['opacity'] ) ? (int) $opts['opacity'] : 80;
        $color   = self::parse_color( isset( $opts['color'] ) ? $opts['color'] : '#ffffff', $opacity );
        $r = $color[0]; $g = $color[1]; $b = $color[2]; $a = $color[3];

        $img_w  = imagesx( $image );
        $img_h  = imagesy( $image );
        $gdcolor = imagecolorallocatealpha( $image, $r, $g, $b, $a );

        $font    = 5;
        $char_w  = imagefontwidth( $font );
        $char_h  = imagefontheight( $font );
        $text_w  = strlen( $text ) * $char_w;
        $step_x  = $text_w + 60;
        $step_y  = $char_h + 60;

        // Tile the text diagonally.
        for ( $y = -$img_h; $y < $img_h * 2; $y += $step_y ) {
            for ( $x = -$img_w; $x < $img_w * 2; $x += $step_x ) {
                imagestring( $image, $font, $x + ( $y / 2 ), $y, $text, $gdcolor );
            }
        }

        return $image;
    }

    /**
     * Corner placement watermark (text in one of the four corners).
     *
     * @param  resource $image
     * @param  array    $opts
     * @return resource
     */
    private static function apply_corner( $image, array $opts ) {
        $opts['position'] = isset( $opts['corner'] ) ? $opts['corner'] : 'bottom-right';
        return self::apply_text( $image, $opts );
    }

    /**
     * Full semi-transparent overlay across the entire image.
     *
     * @param  resource $image
     * @param  array    $opts
     * @return resource
     */
    private static function apply_full_overlay( $image, array $opts ) {
        $img_w   = imagesx( $image );
        $img_h   = imagesy( $image );
        $opacity = isset( $opts['opacity'] ) ? (int) $opts['opacity'] : 100; // 0–127 alpha
        $color   = self::parse_color( isset( $opts['color'] ) ? $opts['color'] : '#000000', $opacity );

        $gdcolor = imagecolorallocatealpha( $image, $color[0], $color[1], $color[2], $color[3] );
        imagefilledrectangle( $image, 0, 0, $img_w - 1, $img_h - 1, $gdcolor );

        // Additionally stamp the watermark text on top.
        $text = isset( $opts['text'] ) ? $opts['text'] : get_bloginfo( 'name' );
        if ( $text ) {
            $opts['opacity'] = 0; // Fully opaque text on the overlay.
            $opts['color']   = '#ffffff';
            $opts['position'] = 'center';
            self::apply_text( $image, $opts );
        }

        return $image;
    }

    /**
     * Logo/image watermark composited onto the photo.
     *
     * @param  resource $image
     * @param  array    $opts  Expects 'logo_path' key with absolute path to logo file.
     * @return resource
     */
    private static function apply_logo( $image, array $opts ) {
        $logo_path = isset( $opts['logo_path'] ) ? $opts['logo_path'] : '';

        if ( ! $logo_path || ! file_exists( $logo_path ) ) {
            // Fall back to text watermark.
            return self::apply_text( $image, $opts );
        }

        $mime = wp_check_filetype( $logo_path )['type'];
        switch ( $mime ) {
            case 'image/png':
                $logo = imagecreatefrompng( $logo_path );
                break;
            case 'image/gif':
                $logo = imagecreatefromgif( $logo_path );
                break;
            case 'image/jpeg':
                $logo = imagecreatefromjpeg( $logo_path );
                break;
            default:
                return self::apply_text( $image, $opts );
        }

        if ( ! $logo ) {
            return self::apply_text( $image, $opts );
        }

        $img_w  = imagesx( $image );
        $img_h  = imagesy( $image );
        $logo_w = imagesx( $logo );
        $logo_h = imagesy( $logo );

        // Scale logo to max 20 % of image width while preserving aspect ratio.
        $max_w = (int) ( $img_w * 0.20 );
        if ( $logo_w > $max_w ) {
            $scale  = $max_w / $logo_w;
            $logo_w = $max_w;
            $logo_h = (int) ( $logo_h * $scale );
            $resized = imagescale( $logo, $logo_w, $logo_h );
            if ( $resized ) {
                imagedestroy( $logo );
                $logo = $resized;
            }
        }

        $position = isset( $opts['position'] ) ? $opts['position'] : 'bottom-right';
        $padding  = 15;
        list( $x, $y ) = self::corner_position( $position, $img_w, $img_h, $logo_w, $logo_h, $padding );

        imagecopy( $image, $logo, $x, $y, 0, 0, $logo_w, $logo_h );
        imagedestroy( $logo );

        return $image;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the effective watermark options for a given event post.
     *
     * @param  int   $post_id
     * @return array
     */
    private static function get_options( $post_id = 0 ) {
        $defaults = array(
            'style'        => get_option( 'eim_watermark_style', 'text' ),
            'text'         => get_option( 'eim_watermark_text', get_bloginfo( 'name' ) ),
            'color'        => get_option( 'eim_watermark_color', '#ffffff' ),
            'opacity'      => (int) get_option( 'eim_watermark_opacity', 60 ),
            'font_size_pct' => (int) get_option( 'eim_watermark_font_size_pct', 5 ),
            'position'     => get_option( 'eim_watermark_position', 'bottom-right' ),
            'logo_path'    => get_option( 'eim_watermark_logo_path', '' ),
            'corner'       => get_option( 'eim_watermark_corner', 'bottom-right' ),
        );

        if ( $post_id ) {
            $per_event = get_post_meta( $post_id, '_eim_watermark_options', true );
            if ( is_array( $per_event ) ) {
                $defaults = array_merge( $defaults, $per_event );
            }
        }

        /**
         * Filter watermark style options.
         *
         * @param array $defaults  Merged options array.
         * @param int   $post_id   Event post ID (0 if not applicable).
         */
        return apply_filters( 'eim_watermark_style_options', $defaults, $post_id );
    }

    /**
     * Parse a hex color string and return [ R, G, B, alpha ] where alpha is
     * a GD alpha value (0 = fully opaque, 127 = fully transparent).
     *
     * @param  string $hex     e.g. '#ff0000' or 'ff0000'.
     * @param  int    $opacity 0–127 alpha value.
     * @return int[]           [ r, g, b, alpha ]
     */
    private static function parse_color( $hex, $opacity = 60 ) {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        // Clamp alpha to valid GD range (0–127).
        $alpha = max( 0, min( 127, (int) $opacity ) );

        return array( (int) $r, (int) $g, (int) $b, $alpha );
    }

    /**
     * Calculate the top-left (x, y) for an element given a named position.
     *
     * @param  string $position  'top-left' | 'top-right' | 'bottom-left' | 'bottom-right' | 'center'
     * @param  int    $canvas_w
     * @param  int    $canvas_h
     * @param  int    $elem_w
     * @param  int    $elem_h
     * @param  int    $padding
     * @return int[]  [ x, y ]
     */
    private static function corner_position( $position, $canvas_w, $canvas_h, $elem_w, $elem_h, $padding ) {
        switch ( $position ) {
            case 'top-left':
                return array( $padding, $padding );
            case 'top-right':
                return array( $canvas_w - $elem_w - $padding, $padding );
            case 'bottom-left':
                return array( $padding, $canvas_h - $elem_h - $padding );
            case 'center':
                return array(
                    (int) ( ( $canvas_w - $elem_w ) / 2 ),
                    (int) ( ( $canvas_h - $elem_h ) / 2 ),
                );
            case 'bottom-right':
            default:
                return array(
                    $canvas_w - $elem_w - $padding,
                    $canvas_h - $elem_h - $padding,
                );
        }
    }
}

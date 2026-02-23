<?php
/**
 * Class Image_Manager
 *
 * Handles watermark application and storage of watermarked images.
 * Watermarked copies are written to a dedicated sub-directory inside the
 * WordPress uploads folder so that the originals are never overwritten.
 */
class Image_Manager {

    /** Sub-directory (relative to the uploads base) for watermarked images. */
    const WM_DIR = 'eim-watermarked';

    /**
     * Watermark text drawn onto images.
     *
     * Filterable via the 'eim_watermark_text' filter.
     *
     * @var string
     */
    private $watermark_text;

    /**
     * Watermark position.
     *
     * Accepted values: 'center', 'bottom-right', 'bottom-left',
     *                  'top-right', 'top-left'.
     * Filterable via the 'eim_watermark_position' filter.
     *
     * @var string
     */
    private $watermark_position;

    /**
     * Font size as a percentage of the image width (0–100).
     *
     * Filterable via the 'eim_watermark_font_size_pct' filter.
     *
     * @var int
     */
    private $font_size_pct;

    public function __construct() {
        $this->watermark_text     = apply_filters( 'eim_watermark_text', get_bloginfo( 'name' ) );
        $this->watermark_position = apply_filters( 'eim_watermark_position', 'bottom-right' );
        $this->font_size_pct      = (int) apply_filters( 'eim_watermark_font_size_pct', 5 );
    }

    /**
     * Create a watermarked copy of the given image file.
     *
     * @param  string $original_path Absolute path to the source image.
     * @return array|false  Associative array with 'original_path',
     *                      'watermarked_path', 'watermarked_url' on success;
     *                      false on failure.
     */
    public function create_watermarked( $original_path ) {
        if ( ! file_exists( $original_path ) ) {
            return false;
        }

        // Load the image resource.
        $image = $this->load_image( $original_path );
        if ( false === $image ) {
            return false;
        }

        // Apply the watermark.
        $image = $this->apply_watermark( $image );

        // Determine the output path.
        $dest = $this->get_watermarked_path( $original_path );
        if ( false === $dest ) {
            imagedestroy( $image );
            return false;
        }

        // Save the watermarked image.
        $saved = $this->save_image( $image, $dest, $original_path );
        imagedestroy( $image );

        if ( ! $saved ) {
            return false;
        }

        // Optionally strip EXIF metadata from the original file.
        if ( get_option( 'eim_strip_exif', 0 ) && class_exists( 'EIM_Image_Optimizer' ) ) {
            EIM_Image_Optimizer::strip_exif_and_save( $original_path );
        }

        $upload_dir = wp_upload_dir();
        $url        = str_replace(
            trailingslashit( $upload_dir['basedir'] ),
            trailingslashit( $upload_dir['baseurl'] ),
            $dest
        );

        return array(
            'original_path'    => $original_path,
            'watermarked_path' => $dest,
            'watermarked_url'  => $url,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load an image resource from a file path.
     *
     * @param  string   $path Absolute file path.
     * @return resource|false GD image resource or false on failure.
     */
    private function load_image( $path ) {
        if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
            return false;
        }

        $mime = wp_check_filetype( $path )['type'];

        switch ( $mime ) {
            case 'image/jpeg':
                return imagecreatefromjpeg( $path );
            case 'image/png':
                return imagecreatefrompng( $path );
            case 'image/gif':
                return imagecreatefromgif( $path );
            default:
                return false;
        }
    }

    /**
     * Draw the watermark text onto the image resource.
     *
     * Delegates to EIM_Watermark_Styles when available so that the active
     * watermark style (text, diagonal, logo, etc.) is applied.  Falls back
     * to the original simple text watermark if the class is not loaded.
     *
     * @param  resource $image GD image resource.
     * @return resource        The same resource with the watermark applied.
     */
    private function apply_watermark( $image ) {
        if ( class_exists( 'EIM_Watermark_Styles' ) ) {
            return EIM_Watermark_Styles::apply( $image );
        }

        // ── Legacy fallback (original behaviour) ─────────────────────────────
        $img_w = imagesx( $image );
        $img_h = imagesy( $image );

        // Semi-transparent white text.
        $color = imagecolorallocatealpha( $image, 255, 255, 255, 60 );

        // Calculate font size (pixel height).
        $font_size = max( 10, (int) ( $img_w * $this->font_size_pct / 100 ) );
        $padding   = (int) ( $font_size * 0.5 );

        // Use the built-in GD font when no TTF font is available.
        $text = $this->watermark_text;

        // GD built-in fonts: 1-5.  Choose the largest (5) and scale by repeating.
        // For a more polished look, use imagettftext when a font file is provided.
        $font        = 5;
        $char_w      = imagefontwidth( $font );
        $char_h      = imagefontheight( $font );
        $text_w      = strlen( $text ) * $char_w;
        $text_h      = $char_h;

        list( $x, $y ) = $this->calculate_position(
            $img_w, $img_h, $text_w, $text_h, $padding
        );

        imagestring( $image, $font, $x, $y, $text, $color );

        return $image;
    }

    /**
     * Calculate the top-left corner for the watermark text.
     *
     * @param int $img_w    Image width.
     * @param int $img_h    Image height.
     * @param int $text_w   Text bounding-box width.
     * @param int $text_h   Text bounding-box height.
     * @param int $padding  Padding from the edge.
     * @return int[]        [$x, $y].
     */
    private function calculate_position( $img_w, $img_h, $text_w, $text_h, $padding ) {
        switch ( $this->watermark_position ) {
            case 'center':
                $x = (int) ( ( $img_w - $text_w ) / 2 );
                $y = (int) ( ( $img_h - $text_h ) / 2 );
                break;
            case 'bottom-left':
                $x = $padding;
                $y = $img_h - $text_h - $padding;
                break;
            case 'top-right':
                $x = $img_w - $text_w - $padding;
                $y = $padding;
                break;
            case 'top-left':
                $x = $padding;
                $y = $padding;
                break;
            case 'bottom-right':
            default:
                $x = $img_w - $text_w - $padding;
                $y = $img_h - $text_h - $padding;
                break;
        }

        return array( max( 0, $x ), max( 0, $y ) );
    }

    /**
     * Determine the output path for the watermarked image.
     *
     * The watermarked file is placed inside uploads/eim-watermarked/ with the
     * same filename as the original.
     *
     * @param  string      $original_path Absolute path to the source image.
     * @return string|false Destination path or false on failure.
     */
    private function get_watermarked_path( $original_path ) {
        $upload_dir = wp_upload_dir();
        $wm_base    = trailingslashit( $upload_dir['basedir'] ) . self::WM_DIR;

        if ( ! file_exists( $wm_base ) ) {
            wp_mkdir_p( $wm_base );

            // Protect the directory from direct access.
            $htaccess = $wm_base . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                file_put_contents( $htaccess, "Options -Indexes\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [F,L]\n</IfModule>\n" );
            }
        }

        if ( ! is_writable( $wm_base ) ) {
            return false;
        }

        return trailingslashit( $wm_base ) . wp_basename( $original_path );
    }

    /**
     * Save a GD image resource to disk.
     *
     * @param  resource $image         GD resource.
     * @param  string   $dest          Absolute destination path.
     * @param  string   $original_path Used to determine the output MIME type.
     * @return bool
     */
    private function save_image( $image, $dest, $original_path ) {
        $mime = wp_check_filetype( $original_path )['type'];

        switch ( $mime ) {
            case 'image/png':
                return imagepng( $image, $dest );
            case 'image/gif':
                return imagegif( $image, $dest );
            case 'image/jpeg':
            default:
                return imagejpeg( $image, $dest, 90 );
        }
    }
}

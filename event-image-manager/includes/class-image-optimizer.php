<?php
/**
 * Class EIM_Image_Optimizer
 *
 * Provides image compression and EXIF metadata stripping.
 *
 * - EXIF removal: rebuilds the image using GD, which discards all metadata
 *   automatically (GD does not carry EXIF through read/write cycles).
 * - Compression: JPEG quality is configurable; PNG uses a compression level.
 */
class EIM_Image_Optimizer {

    /** Default JPEG quality (0–100). */
    const DEFAULT_JPEG_QUALITY = 85;

    /** Default PNG compression level (0–9). */
    const DEFAULT_PNG_COMPRESSION = 6;

    /**
     * Strip EXIF data from an image file and optionally compress it.
     *
     * The source file is overwritten in place.  If you need to keep the
     * original, copy it before calling this method.
     *
     * @param  string $path    Absolute path to the image file.
     * @param  bool   $compress Whether to apply compression as well.
     * @return bool   True on success, false on failure.
     */
    public static function strip_exif_and_save( $path, $compress = true ) {
        if ( ! file_exists( $path ) ) {
            return false;
        }

        $mime = wp_check_filetype( $path )['type'];
        $image = self::load( $path, $mime );
        if ( ! $image ) {
            return false;
        }

        $result = self::save( $image, $path, $mime, $compress );
        imagedestroy( $image );

        return $result;
    }

    /**
     * Compress an image file without stripping EXIF (still uses GD so EXIF
     * is actually stripped as a side-effect, but intent is compression).
     *
     * @param  string $path  Absolute path.
     * @param  int    $quality JPEG quality override (0–100).
     * @return bool
     */
    public static function compress( $path, $quality = self::DEFAULT_JPEG_QUALITY ) {
        if ( ! file_exists( $path ) ) {
            return false;
        }

        $mime  = wp_check_filetype( $path )['type'];
        $image = self::load( $path, $mime );
        if ( ! $image ) {
            return false;
        }

        $result = false;
        switch ( $mime ) {
            case 'image/jpeg':
                $result = imagejpeg( $image, $path, max( 0, min( 100, $quality ) ) );
                break;
            case 'image/png':
                $result = imagepng( $image, $path, self::DEFAULT_PNG_COMPRESSION );
                break;
            case 'image/gif':
                $result = imagegif( $image, $path );
                break;
        }

        imagedestroy( $image );
        return $result;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load an image resource from a file.
     *
     * @param  string   $path
     * @param  string   $mime
     * @return resource|false
     */
    private static function load( $path, $mime ) {
        if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
            return false;
        }

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
     * Save a GD resource to disk.
     *
     * @param  resource $image
     * @param  string   $path
     * @param  string   $mime
     * @param  bool     $compress
     * @return bool
     */
    private static function save( $image, $path, $mime, $compress ) {
        $jpeg_quality = $compress
            ? (int) apply_filters( 'eim_jpeg_quality', self::DEFAULT_JPEG_QUALITY )
            : 100;
        $png_compression = $compress
            ? (int) apply_filters( 'eim_png_compression', self::DEFAULT_PNG_COMPRESSION )
            : 0;

        switch ( $mime ) {
            case 'image/jpeg':
                return imagejpeg( $image, $path, $jpeg_quality );
            case 'image/png':
                return imagepng( $image, $path, $png_compression );
            case 'image/gif':
                return imagegif( $image, $path );
            default:
                return false;
        }
    }
}

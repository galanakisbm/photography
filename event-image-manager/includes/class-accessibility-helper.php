<?php
/**
 * Class EIM_Accessibility_Helper
 *
 * WCAG 2.1 compliance improvements for gallery pages.
 *
 * - Adds proper alt text and aria-labels to gallery images via a filter.
 * - Injects a "skip to content" link in the page footer.
 * - Enqueues a small keyboard-navigation JS snippet for the lightbox.
 */
class EIM_Accessibility_Helper {

    public function __construct() {
        add_filter( 'eim_gallery_image_attrs', array( $this, 'improve_image_attrs' ), 10, 3 );
        add_action( 'wp_footer',              array( $this, 'add_skip_link' ) );
        add_action( 'wp_enqueue_scripts',     array( $this, 'improve_keyboard_nav' ) );
    }

    // -------------------------------------------------------------------------
    // Filter callback
    // -------------------------------------------------------------------------

    /**
     * Ensure gallery images have meaningful alt text and aria labels.
     *
     * @param  array  $attrs     Existing HTML attribute key/value pairs.
     * @param  array  $image     Image data array from post meta.
     * @param  int    $index     Zero-based image index.
     * @return array             Modified attributes.
     */
    public function improve_image_attrs( $attrs, $image, $index ) {
        if ( empty( $attrs['alt'] ) || empty( trim( $attrs['alt'] ) ) ) {
            $attrs['alt'] = ! empty( $image['caption'] )
                /* translators: %d: image number */
                ? sanitize_text_field( $image['caption'] )
                : sprintf( __( 'Gallery photo %d', 'event-image-manager' ), $index + 1 );
        }

        $attrs['aria-label'] = $attrs['alt'];

        if ( empty( $attrs['role'] ) ) {
            $attrs['role'] = 'img';
        }

        return $attrs;
    }

    // -------------------------------------------------------------------------
    // Skip link
    // -------------------------------------------------------------------------

    /** Inject a visually-hidden skip-to-content link before the page body content. */
    public function add_skip_link() {
        ?>
        <a
            class="eim-skip-link screen-reader-text"
            href="#eim-gallery-content"
            style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;"
            onfocus="this.style.left='6px';this.style.width='auto';this.style.height='auto';"
            onblur="this.style.left='-9999px';this.style.width='1px';this.style.height='1px';"
        ><?php esc_html_e( 'Skip to gallery', 'event-image-manager' ); ?></a>
        <?php
    }

    // -------------------------------------------------------------------------
    // Keyboard navigation
    // -------------------------------------------------------------------------

    /** Enqueue keyboard navigation enhancements for the gallery lightbox. */
    public function improve_keyboard_nav() {
        // Only enqueue on pages that contain the gallery.
        $post = get_post();
        if ( ! is_singular( 'event' ) && ( ! $post || ! has_shortcode( $post->post_content, 'eim_print_view' ) ) ) {
            return;
        }

        $js = "
(function() {
    document.addEventListener('keydown', function(e) {
        var lightbox = document.querySelector('.eim-lightbox.is-open');
        if (!lightbox) return;
        if (e.key === 'ArrowRight') lightbox.dispatchEvent(new Event('eim:next'));
        if (e.key === 'ArrowLeft')  lightbox.dispatchEvent(new Event('eim:prev'));
        if (e.key === 'Escape')     lightbox.dispatchEvent(new Event('eim:close'));
    });
    document.querySelectorAll('.eim-gallery-item').forEach(function(item) {
        if (!item.getAttribute('tabindex')) item.setAttribute('tabindex', '0');
    });
})();
";
        wp_register_script( 'eim-a11y-nav', '', array(), '', true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
        wp_enqueue_script( 'eim-a11y-nav' );
        wp_add_inline_script( 'eim-a11y-nav', $js );
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Calculate the contrast ratio between a foreground and background colour.
     * Follows the WCAG 2.1 relative luminance formula.
     *
     * @param  string $fg Hex colour for foreground (e.g. '#ffffff').
     * @param  string $bg Hex colour for background (e.g. '#000000').
     * @return float      Contrast ratio (e.g. 21 for black on white).
     */
    public function check_color_contrast( $fg, $bg ) {
        $fg_lum = $this->relative_luminance( $fg );
        $bg_lum = $this->relative_luminance( $bg );

        $lighter = max( $fg_lum, $bg_lum );
        $darker  = min( $fg_lum, $bg_lum );

        return round( ( $lighter + 0.05 ) / ( $darker + 0.05 ), 2 );
    }

    /**
     * Calculate relative luminance for a hex colour.
     *
     * @param  string $hex Hex colour string.
     * @return float       Relative luminance value between 0 and 1.
     */
    private function relative_luminance( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) ) / 255;
        $g = hexdec( substr( $hex, 2, 2 ) ) / 255;
        $b = hexdec( substr( $hex, 4, 2 ) ) / 255;

        $to_linear = function( $c ) {
            return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
        };

        return 0.2126 * $to_linear( $r ) + 0.7152 * $to_linear( $g ) + 0.0722 * $to_linear( $b );
    }
}

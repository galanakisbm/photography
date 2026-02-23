<?php
/**
 * Class EIM_Print_Manager
 *
 * Renders a clean, print-friendly gallery view.
 *
 * - Shortcode `[eim_print_view id="POST_ID"]` embeds the printable gallery.
 * - The `?eim_print=1` query parameter triggers a standalone print preview page.
 */
class EIM_Print_Manager {

    public function __construct() {
        add_shortcode( 'eim_print_view', array( $this, 'shortcode_print_view' ) );
        add_action( 'template_redirect',  array( $this, 'handle_print_preview' ) );
    }

    // -------------------------------------------------------------------------
    // Shortcode
    // -------------------------------------------------------------------------

    /**
     * Render the print-friendly gallery shortcode.
     *
     * @param  array $atts Shortcode attributes.
     * @return string      HTML output.
     */
    public function shortcode_print_view( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'eim_print_view' );
        $post_id = absint( $atts['id'] );

        if ( ! $post_id ) {
            return '';
        }

        return $this->render_print_view( $post_id );
    }

    // -------------------------------------------------------------------------
    // Print preview request handler
    // -------------------------------------------------------------------------

    /** Handle the `?eim_print=1&post_id=X` request for a standalone print page. */
    public function handle_print_preview() {
        if ( ! isset( $_GET['eim_print'] ) || '1' !== $_GET['eim_print'] ) {
            return;
        }

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        if ( ! $post_id ) {
            return;
        }

        echo '<!DOCTYPE html><html><head>';
        echo '<meta charset="utf-8">';
        echo '<title>' . esc_html( get_the_title( $post_id ) ) . ' — ' . esc_html__( 'Print Gallery', 'event-image-manager' ) . '</title>';
        echo '<style>body{margin:0;padding:20px;font-family:sans-serif;}img{max-width:100%;height:auto;display:block;margin:0 auto 16px;}h1{text-align:center;}</style>';
        echo '</head><body>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->render_print_view( $post_id );
        echo '<script>window.print();</script>';
        echo '</body></html>';
        exit;
    }

    // -------------------------------------------------------------------------
    // Renderer
    // -------------------------------------------------------------------------

    /**
     * Generate a clean printable HTML gallery for the given post.
     *
     * @param  int    $post_id Event post ID.
     * @return string          HTML string.
     */
    public function render_print_view( $post_id ) {
        $images = get_post_meta( $post_id, '_eim_gallery_images', true );

        if ( ! is_array( $images ) || empty( $images ) ) {
            return '<p>' . esc_html__( 'No images available.', 'event-image-manager' ) . '</p>';
        }

        $title = get_the_title( $post_id );
        $upload_dir = wp_upload_dir();

        ob_start();
        ?>
        <div class="eim-print-gallery">
            <h1><?php echo esc_html( $title ); ?></h1>
            <div class="eim-print-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                <?php foreach ( $images as $index => $img ) : ?>
                    <?php
                    $url = '';
                    if ( ! empty( $img['watermarked_path'] ) && file_exists( $img['watermarked_path'] ) ) {
                        $rel  = str_replace( $upload_dir['basedir'], '', $img['watermarked_path'] );
                        $url  = $upload_dir['baseurl'] . $rel;
                    } elseif ( ! empty( $img['original_path'] ) && file_exists( $img['original_path'] ) ) {
                        $rel  = str_replace( $upload_dir['basedir'], '', $img['original_path'] );
                        $url  = $upload_dir['baseurl'] . $rel;
                    }
                    if ( ! $url ) {
                        continue;
                    }
                    $alt = ! empty( $img['caption'] ) ? $img['caption'] : sprintf( __( 'Photo %d', 'event-image-manager' ), $index + 1 );
                    ?>
                    <div class="eim-print-item" style="break-inside:avoid;">
                        <img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
                        <?php if ( ! empty( $img['caption'] ) ) : ?>
                            <p style="text-align:center;font-size:0.85em;color:#555;"><?php echo esc_html( $img['caption'] ); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

<?php
/**
 * Class Image_Protector
 *
 * Serves watermarked images via a custom PHP endpoint, hiding the real file
 * paths from the browser and preventing direct access to the uploads directory.
 *
 * Usage:  .../wp-admin/admin-ajax.php?action=eim_serve_image&token=<token>&id=<post_id>&img=<index>
 * Or via the rewrite rule added by this class:
 *         .../eim-image/<token>/<post_id>/<index>
 */
class Image_Protector {

    /** Rewrite tag used in URL. */
    const REWRITE_TAG   = 'eim_image_request';

    /** Query var forwarded to WordPress. */
    const QUERY_VAR     = 'eim_image';

    /** Transient prefix for per-image tokens. */
    const TOKEN_PREFIX  = 'eim_token_';

    /** Token lifetime in seconds (1 hour). */
    const TOKEN_TTL     = 3600;

    public function __construct() {
        add_action( 'init',              array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars',        array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_serve_image' ) );
        add_action( 'wp_ajax_eim_serve_image',        array( $this, 'serve_image_ajax' ) );
        add_action( 'wp_ajax_nopriv_eim_serve_image', array( $this, 'serve_image_ajax' ) );
    }

    // -------------------------------------------------------------------------
    // Public URL generation
    // -------------------------------------------------------------------------

    /**
     * Generate a time-limited, signed URL for a specific image in an event.
     *
     * @param  int $post_id   Event post ID.
     * @param  int $img_index Zero-based index of the image in the gallery meta.
     * @return string         Protected image URL.
     */
    public static function get_protected_url( $post_id, $img_index ) {
        $token = self::generate_token( $post_id, $img_index );
        return add_query_arg(
            array(
                'action' => 'eim_serve_image',
                'token'  => rawurlencode( $token ),
                'id'     => absint( $post_id ),
                'img'    => absint( $img_index ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    // -------------------------------------------------------------------------
    // Rewrite rules
    // -------------------------------------------------------------------------

    /** Add a pretty rewrite rule (optional — the ajax endpoint always works). */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^eim-image/([^/]+)/([0-9]+)/([0-9]+)/?$',
            'index.php?' . self::QUERY_VAR . '=1&eim_token=$matches[1]&eim_post_id=$matches[2]&eim_img_index=$matches[3]',
            'top'
        );
    }

    /** Register custom query vars with WordPress. */
    public function add_query_vars( $vars ) {
        $vars[] = self::QUERY_VAR;
        $vars[] = 'eim_token';
        $vars[] = 'eim_post_id';
        $vars[] = 'eim_img_index';
        return $vars;
    }

    // -------------------------------------------------------------------------
    // Image serving
    // -------------------------------------------------------------------------

    /** Handle requests via the pretty rewrite URL. */
    public function maybe_serve_image() {
        if ( ! get_query_var( self::QUERY_VAR ) ) {
            return;
        }

        $token     = sanitize_text_field( get_query_var( 'eim_token' ) );
        $post_id   = absint( get_query_var( 'eim_post_id' ) );
        $img_index = absint( get_query_var( 'eim_img_index' ) );

        $this->output_image( $token, $post_id, $img_index );
        exit;
    }

    /** Handle requests via the admin-ajax endpoint. */
    public function serve_image_ajax() {
        $token     = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $post_id   = isset( $_GET['id'] )    ? absint( $_GET['id'] )    : 0;
        $img_index = isset( $_GET['img'] )   ? absint( $_GET['img'] )   : 0;

        $this->output_image( $token, $post_id, $img_index );
        exit;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Validate the token and stream the image to the browser.
     *
     * @param string $token
     * @param int    $post_id
     * @param int    $img_index
     */
    private function output_image( $token, $post_id, $img_index ) {
        if ( ! self::verify_token( $token, $post_id, $img_index ) ) {
            status_header( 403 );
            exit( 'Forbidden' );
        }

        $images = get_post_meta( $post_id, Event_Admin::META_KEY, true );
        if ( ! is_array( $images ) || ! isset( $images[ $img_index ] ) ) {
            status_header( 404 );
            exit( 'Not Found' );
        }

        $path = $images[ $img_index ]['watermarked_path'];
        if ( ! file_exists( $path ) ) {
            status_header( 404 );
            exit( 'Not Found' );
        }

        $mime = wp_check_filetype( $path )['type'];
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
            status_header( 415 );
            exit( 'Unsupported Media Type' );
        }

        // Security / cache headers.
        header( 'Content-Type: ' . $mime );
        header( 'Content-Length: ' . filesize( $path ) );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'X-Robots-Tag: noindex, nofollow' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        readfile( $path );
    }

    /**
     * Generate a signed token for a specific post/image combination and store
     * it in a transient so it can be verified later.
     *
     * @param  int    $post_id
     * @param  int    $img_index
     * @return string Token string.
     */
    private static function generate_token( $post_id, $img_index ) {
        $token = wp_generate_password( 32, false );
        $key   = self::TOKEN_PREFIX . $post_id . '_' . $img_index . '_' . $token;
        set_transient( $key, 1, self::TOKEN_TTL );
        return $token;
    }

    /**
     * Verify a token against the transient store.
     *
     * @param  string $token
     * @param  int    $post_id
     * @param  int    $img_index
     * @return bool
     */
    private static function verify_token( $token, $post_id, $img_index ) {
        if ( empty( $token ) || ! $post_id ) {
            return false;
        }
        $key = self::TOKEN_PREFIX . $post_id . '_' . $img_index . '_' . $token;
        return (bool) get_transient( $key );
    }
}

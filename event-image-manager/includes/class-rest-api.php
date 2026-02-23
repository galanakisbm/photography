<?php
/**
 * Class EIM_REST_API
 *
 * Registers REST API endpoints under /wp-json/eim/v1/.
 *
 * Authentication: Bearer token via the Authorization header, validated
 * through EIM_API_Tokens::validate_token().
 *
 * Endpoints:
 *   GET    /events
 *   GET    /events/{id}
 *   GET    /events/{id}/images
 *   POST   /events/{id}/images
 *   DELETE /events/{id}/images/{idx}
 *   GET    /notifications
 *   POST   /notifications/{id}/read
 *   GET    /messages
 *   POST   /messages
 */
class EIM_REST_API {

    /** API namespace. */
    const NAMESPACE = 'eim/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    /** Register all REST API routes. */
    public function register_routes() {
        register_rest_route( self::NAMESPACE, '/events', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_events' ),
            'permission_callback' => array( $this, 'auth_check' ),
        ) );

        register_rest_route( self::NAMESPACE, '/events/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_event' ),
            'permission_callback' => array( $this, 'auth_check' ),
            'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
        ) );

        register_rest_route( self::NAMESPACE, '/events/(?P<id>\d+)/images', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_event_images' ),
                'permission_callback' => array( $this, 'auth_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_event_image' ),
                'permission_callback' => array( $this, 'auth_check' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/events/(?P<id>\d+)/images/(?P<idx>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_event_image' ),
            'permission_callback' => array( $this, 'auth_check' ),
        ) );

        register_rest_route( self::NAMESPACE, '/notifications', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_notifications' ),
            'permission_callback' => array( $this, 'auth_check' ),
        ) );

        register_rest_route( self::NAMESPACE, '/notifications/(?P<id>\d+)/read', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'mark_notification_read' ),
            'permission_callback' => array( $this, 'auth_check' ),
        ) );

        register_rest_route( self::NAMESPACE, '/messages', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_messages' ),
                'permission_callback' => array( $this, 'auth_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'send_message' ),
                'permission_callback' => array( $this, 'auth_check' ),
            ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Validate the Bearer token from the Authorization header.
     * Also allows standard WP cookie auth for logged-in users.
     *
     * @param  \WP_REST_Request $request Incoming REST request.
     * @return bool|\WP_Error            True if authorized, WP_Error otherwise.
     */
    public function auth_check( $request ) {
        // Allow logged-in WP users (cookie auth).
        if ( is_user_logged_in() ) {
            return true;
        }

        $auth_header = $request->get_header( 'authorization' );
        if ( $auth_header && 0 === strpos( $auth_header, 'Bearer ' ) ) {
            $token   = substr( $auth_header, 7 );
            $tokens  = new EIM_API_Tokens();
            $user_id = $tokens->validate_token( $token );
            if ( $user_id ) {
                wp_set_current_user( $user_id );
                return true;
            }
        }

        return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'event-image-manager' ), array( 'status' => 401 ) );
    }

    // -------------------------------------------------------------------------
    // Endpoint callbacks
    // -------------------------------------------------------------------------

    /** GET /events */
    public function get_events( $request ) {
        $posts = get_posts( array(
            'post_type'      => 'event',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ) );

        $data = array_map( function( $post ) {
            return array(
                'id'    => $post->ID,
                'title' => $post->post_title,
                'url'   => get_permalink( $post->ID ),
                'date'  => $post->post_date,
            );
        }, $posts );

        return rest_ensure_response( $data );
    }

    /** GET /events/{id} */
    public function get_event( $request ) {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'event' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Event not found.', 'event-image-manager' ), array( 'status' => 404 ) );
        }
        return rest_ensure_response( array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'url'     => get_permalink( $post->ID ),
            'date'    => $post->post_date,
        ) );
    }

    /** GET /events/{id}/images */
    public function get_event_images( $request ) {
        $images = get_post_meta( (int) $request['id'], '_eim_gallery_images', true );
        return rest_ensure_response( is_array( $images ) ? $images : array() );
    }

    /** POST /events/{id}/images */
    public function add_event_image( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'forbidden', __( 'Permission denied.', 'event-image-manager' ), array( 'status' => 403 ) );
        }

        $post_id = (int) $request['id'];
        $params  = $request->get_json_params();
        $url     = isset( $params['url'] ) ? esc_url_raw( $params['url'] ) : '';

        if ( ! $url ) {
            return new WP_Error( 'bad_request', __( 'URL is required.', 'event-image-manager' ), array( 'status' => 400 ) );
        }

        $images   = get_post_meta( $post_id, '_eim_gallery_images', true );
        $images   = is_array( $images ) ? $images : array();
        $images[] = array( 'watermarked_path' => $url, 'original_path' => $url );
        update_post_meta( $post_id, '_eim_gallery_images', $images );

        return rest_ensure_response( array( 'index' => count( $images ) - 1 ) );
    }

    /** DELETE /events/{id}/images/{idx} */
    public function delete_event_image( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'forbidden', __( 'Permission denied.', 'event-image-manager' ), array( 'status' => 403 ) );
        }

        $post_id = (int) $request['id'];
        $idx     = (int) $request['idx'];
        $images  = get_post_meta( $post_id, '_eim_gallery_images', true );

        if ( ! is_array( $images ) || ! isset( $images[ $idx ] ) ) {
            return new WP_Error( 'not_found', __( 'Image not found.', 'event-image-manager' ), array( 'status' => 404 ) );
        }

        array_splice( $images, $idx, 1 );
        update_post_meta( $post_id, '_eim_gallery_images', $images );

        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /** GET /notifications */
    public function get_notifications( $request ) {
        $in_app = new EIM_In_App_Notifications();
        global $wpdb;
        $table = $wpdb->prefix . 'eim_notifications';
        $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                get_current_user_id()
            )
        );
        return rest_ensure_response( $rows );
    }

    /** POST /notifications/{id}/read */
    public function mark_notification_read( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_notifications';
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array( 'is_read' => 1 ),
            array( 'id' => (int) $request['id'], 'user_id' => get_current_user_id() ),
            array( '%d' ),
            array( '%d', '%d' )
        );
        return rest_ensure_response( array( 'marked_read' => true ) );
    }

    /** GET /messages */
    public function get_messages( $request ) {
        $messaging = new EIM_Messaging_System();
        global $wpdb;
        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'eim_messages';
        $rows    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE to_user_id = %d OR from_user_id = %d ORDER BY created_at DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id, $user_id
            )
        );
        return rest_ensure_response( $rows );
    }

    /** POST /messages */
    public function send_message( $request ) {
        $params     = $request->get_json_params();
        $to_user_id = isset( $params['to_user_id'] ) ? absint( $params['to_user_id'] ) : 0;
        $subject    = isset( $params['subject'] )    ? sanitize_text_field( $params['subject'] ) : '';
        $body       = isset( $params['body'] )       ? sanitize_textarea_field( $params['body'] ) : '';

        if ( ! $to_user_id || ! $body ) {
            return new WP_Error( 'bad_request', __( 'to_user_id and body are required.', 'event-image-manager' ), array( 'status' => 400 ) );
        }

        $messaging  = new EIM_Messaging_System();
        $message_id = $messaging->send_message( get_current_user_id(), $to_user_id, $subject, $body );

        return rest_ensure_response( array( 'message_id' => $message_id ) );
    }
}

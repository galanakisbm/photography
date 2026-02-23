<?php
/**
 * Class EIM_Password_Protection
 *
 * Provides optional password protection for individual event galleries.
 *
 * - Admin sets a password per event (stored as hashed post meta).
 * - Visitors submit the password via a short form; a session flag is set on
 *   success so the gallery is accessible for the remainder of the session.
 * - CSRF-protected via WordPress nonces.
 */
class EIM_Password_Protection {

    /** Post meta key that stores the hashed gallery password. */
    const META_KEY = '_eim_gallery_password';

    /** Session key prefix used to track authorised events. */
    const SESSION_KEY_PREFIX = 'eim_gallery_auth_';

    public function __construct() {
        add_action( 'wp_ajax_eim_verify_gallery_password',        array( $this, 'ajax_verify_password' ) );
        add_action( 'wp_ajax_nopriv_eim_verify_gallery_password', array( $this, 'ajax_verify_password' ) );
        add_action( 'save_post_event', array( $this, 'save_password_meta' ) );
        add_action( 'add_meta_boxes',  array( $this, 'add_meta_box' ) );
    }

    // -------------------------------------------------------------------------
    // Meta box
    // -------------------------------------------------------------------------

    /** Register the password meta box on the event edit screen. */
    public function add_meta_box() {
        add_meta_box(
            'eim_gallery_password',
            __( 'Gallery Password Protection', 'event-image-manager' ),
            array( $this, 'render_meta_box' ),
            'event',
            'side',
            'default'
        );
    }

    /** Render the password meta box. */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'eim_save_gallery_password', 'eim_gallery_password_nonce' );
        $has_password = (bool) get_post_meta( $post->ID, self::META_KEY, true );
        ?>
        <p>
            <label for="eim_gallery_new_password">
                <?php esc_html_e( 'New password (leave blank to keep existing):', 'event-image-manager' ); ?>
            </label><br>
            <input type="password" id="eim_gallery_new_password"
                   name="eim_gallery_new_password" value="" autocomplete="new-password"
                   style="width:100%;margin-top:4px;">
        </p>
        <?php if ( $has_password ) : ?>
            <p>
                <label>
                    <input type="checkbox" name="eim_gallery_remove_password" value="1">
                    <?php esc_html_e( 'Remove password protection', 'event-image-manager' ); ?>
                </label>
            </p>
            <p><em><?php esc_html_e( 'This gallery is currently password-protected.', 'event-image-manager' ); ?></em></p>
        <?php else : ?>
            <p><em><?php esc_html_e( 'This gallery is not password-protected.', 'event-image-manager' ); ?></em></p>
        <?php endif; ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Save / remove password meta
    // -------------------------------------------------------------------------

    /** Save or remove the gallery password when the event post is saved. */
    public function save_password_meta( $post_id ) {
        if ( ! isset( $_POST['eim_gallery_password_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['eim_gallery_password_nonce'] ) ),
                'eim_save_gallery_password'
            )
        ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Remove password if checkbox is ticked.
        if ( ! empty( $_POST['eim_gallery_remove_password'] ) ) {
            delete_post_meta( $post_id, self::META_KEY );
            return;
        }

        // Save new password if provided.
        $new_password = isset( $_POST['eim_gallery_new_password'] )
            ? wp_unslash( $_POST['eim_gallery_new_password'] )
            : '';

        if ( '' !== $new_password ) {
            $hashed = wp_hash_password( $new_password );
            update_post_meta( $post_id, self::META_KEY, $hashed );
        }
    }

    // -------------------------------------------------------------------------
    // Access checks
    // -------------------------------------------------------------------------

    /**
     * Determine whether the current user/session is allowed to view an event's
     * gallery.
     *
     * Admins and users with `eim_manage_events` always have access.
     *
     * @param  int $post_id Event post ID.
     * @return bool
     */
    public static function is_authorised( $post_id ) {
        // Privileged users bypass the password check.
        if ( current_user_can( 'manage_options' ) || current_user_can( 'eim_manage_events' ) ) {
            return true;
        }

        $hash = get_post_meta( $post_id, self::META_KEY, true );
        if ( ! $hash ) {
            return true; // No password set.
        }

        self::maybe_start_session();
        $key = self::SESSION_KEY_PREFIX . $post_id;
        return ! empty( $_SESSION[ $key ] );
    }

    /**
     * Start a PHP session if one is not already active.
     *
     * Using PHP sessions is pragmatic here; for environments that cannot use
     * sessions, the same result can be achieved with signed cookies.
     */
    private static function maybe_start_session() {
        if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
            session_start();
        }
    }

    // -------------------------------------------------------------------------
    // AJAX password verification
    // -------------------------------------------------------------------------

    /** AJAX handler: verify the submitted gallery password. */
    public function ajax_verify_password() {
        check_ajax_referer( 'eim_verify_password_nonce', 'nonce' );

        $post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

        if ( ! $post_id || '' === $password ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $hash = get_post_meta( $post_id, self::META_KEY, true );
        if ( ! $hash ) {
            wp_send_json_success(); // No password; grant access.
            return;
        }

        if ( wp_check_password( $password, $hash ) ) {
            self::maybe_start_session();
            $_SESSION[ self::SESSION_KEY_PREFIX . $post_id ] = true;
            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => __( 'Incorrect password. Please try again.', 'event-image-manager' ) ) );
        }
    }
}

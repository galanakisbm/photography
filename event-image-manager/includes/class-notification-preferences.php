<?php
/**
 * Class EIM_Notification_Preferences
 *
 * Stores per-user notification preferences in user meta.
 *
 * Preference keys:
 *   - email_new_images : receive email when new images are added.
 *   - email_new_events : receive email when a new event is created.
 *   - in_app_messages  : receive in-app notification for new messages.
 *   - in_app_events    : receive in-app notification for new events.
 */
class EIM_Notification_Preferences {

    /** User meta key used to store preferences. */
    const META_KEY = 'eim_notification_prefs';

    /** Default preference values. */
    const DEFAULTS = array(
        'email_new_images' => true,
        'email_new_events' => false,
        'in_app_messages'  => true,
        'in_app_events'    => true,
    );

    public function __construct() {
        add_action( 'wp_ajax_eim_save_notification_prefs', array( $this, 'ajax_save_prefs' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: save the current user's notification preferences. */
    public function ajax_save_prefs() {
        check_ajax_referer( 'eim_notifications_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'event-image-manager' ) ) );
        }

        $raw_prefs = isset( $_POST['prefs'] ) && is_array( $_POST['prefs'] ) ? wp_unslash( $_POST['prefs'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $prefs = array();
        foreach ( array_keys( self::DEFAULTS ) as $key ) {
            $prefs[ $key ] = ! empty( $raw_prefs[ $key ] );
        }

        $this->update_user_prefs( $user_id, $prefs );

        wp_send_json_success( array( 'message' => __( 'Preferences saved.', 'event-image-manager' ) ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Get a user's notification preferences, merging with defaults.
     *
     * @param  int   $user_id WordPress user ID.
     * @return array           Associative array of preference key => bool.
     */
    public function get_user_prefs( $user_id ) {
        $stored = get_user_meta( absint( $user_id ), self::META_KEY, true );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        return array_merge( self::DEFAULTS, $stored );
    }

    /**
     * Save a user's notification preferences.
     *
     * @param int   $user_id WordPress user ID.
     * @param array $prefs   Associative array of preference key => bool.
     */
    public function update_user_prefs( $user_id, $prefs ) {
        $clean = array();
        foreach ( array_keys( self::DEFAULTS ) as $key ) {
            $clean[ $key ] = isset( $prefs[ $key ] ) ? (bool) $prefs[ $key ] : self::DEFAULTS[ $key ];
        }
        update_user_meta( absint( $user_id ), self::META_KEY, $clean );
    }
}

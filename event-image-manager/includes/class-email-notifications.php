<?php
/**
 * Class EIM_Email_Notifications
 *
 * Sends email notifications for new images and new events.
 *
 * - Users can subscribe/unsubscribe to per-event updates.
 * - Admins and photographers receive notifications for new events.
 * - Subscription records stored in `{prefix}eim_email_subscriptions`.
 */
class EIM_Email_Notifications {

    public function __construct() {
        add_action( 'wp_ajax_eim_subscribe_event',   array( $this, 'ajax_subscribe_event' ) );
        add_action( 'wp_ajax_eim_unsubscribe_event', array( $this, 'ajax_unsubscribe_event' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: subscribe the current user to an event's image notifications. */
    public function ajax_subscribe_event() {
        check_ajax_referer( 'eim_notifications_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $user_id = get_current_user_id();

        if ( ! $post_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_email_subscriptions';

        $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id, $post_id
            )
        );

        if ( $existing ) {
            wp_send_json_success( array( 'message' => __( 'Already subscribed.', 'event-image-manager' ) ) );
        }

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'user_id'    => $user_id,
                'post_id'    => $post_id,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s' )
        );

        wp_send_json_success( array( 'message' => __( 'Subscribed to event updates.', 'event-image-manager' ) ) );
    }

    /** AJAX: unsubscribe the current user from an event's image notifications. */
    public function ajax_unsubscribe_event() {
        check_ajax_referer( 'eim_notifications_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $user_id = get_current_user_id();

        if ( ! $post_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_email_subscriptions';

        $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array( 'user_id' => $user_id, 'post_id' => $post_id ),
            array( '%d', '%d' )
        );

        wp_send_json_success( array( 'message' => __( 'Unsubscribed from event updates.', 'event-image-manager' ) ) );
    }

    // -------------------------------------------------------------------------
    // Notification senders
    // -------------------------------------------------------------------------

    /**
     * Send email to all subscribers of an event when new images are added.
     *
     * @param int $post_id Event post ID.
     * @param int $count   Number of new images added.
     */
    public function send_new_images_notification( $post_id, $count ) {
        global $wpdb;
        $table       = $wpdb->prefix . 'eim_email_subscriptions';
        $subscribers = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT user_id FROM {$table} WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id
            )
        );

        if ( empty( $subscribers ) ) {
            return;
        }

        $event_title = get_the_title( $post_id );
        $event_url   = get_permalink( $post_id );
        /* translators: %1$s: event title */
        $subject = sprintf( __( 'New photos added to %1$s', 'event-image-manager' ), $event_title );
        /* translators: %1$d: number of photos, %2$s: event title, %3$s: event URL */
        $body = sprintf(
            __( '%1$d new photo(s) have been added to the event "%2$s". View them here: %3$s', 'event-image-manager' ),
            $count, $event_title, $event_url
        );

        foreach ( $subscribers as $user_id ) {
            $user = get_userdata( (int) $user_id );
            if ( $user && $user->user_email ) {
                wp_mail( $user->user_email, $subject, $body );
            }
        }
    }

    /**
     * Send email to all photographers and admins when a new event is created.
     *
     * @param int $post_id New event post ID.
     */
    public function send_new_event_notification( $post_id ) {
        $event_title = get_the_title( $post_id );
        $event_url   = get_permalink( $post_id );
        /* translators: %1$s: event title */
        $subject = sprintf( __( 'New event created: %1$s', 'event-image-manager' ), $event_title );
        /* translators: %1$s: event title, %2$s: event URL */
        $body = sprintf(
            __( 'A new event "%1$s" has been created. View it here: %2$s', 'event-image-manager' ),
            $event_title, $event_url
        );

        $recipients = get_users( array( 'role__in' => array( 'administrator', 'photographer' ) ) );
        foreach ( $recipients as $user ) {
            wp_mail( $user->user_email, $subject, $body );
        }
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the email subscriptions table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_email_subscriptions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL,
            post_id    BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_post (user_id, post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

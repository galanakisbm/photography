<?php
/**
 * Class EIM_In_App_Notifications
 *
 * Stores and serves in-app notifications for WordPress users.
 *
 * - `eim_get_notifications`          : fetch unread notifications for the current user.
 * - `eim_mark_notification_read`     : mark a single notification as read.
 * - `eim_mark_all_notifications_read`: mark all of the user's notifications as read.
 * - Records stored in `{prefix}eim_notifications`.
 */
class EIM_In_App_Notifications {

    public function __construct() {
        add_action( 'wp_ajax_eim_get_notifications',           array( $this, 'ajax_get_notifications' ) );
        add_action( 'wp_ajax_eim_mark_notification_read',      array( $this, 'ajax_mark_read' ) );
        add_action( 'wp_ajax_eim_mark_all_notifications_read', array( $this, 'ajax_mark_all_read' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: return all unread notifications for the current user. */
    public function ajax_get_notifications() {
        check_ajax_referer( 'eim_notifications_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_notifications';
        $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id
            )
        );

        wp_send_json_success( array( 'notifications' => $rows ) );
    }

    /** AJAX: mark a single notification as read. */
    public function ajax_mark_read() {
        check_ajax_referer( 'eim_notifications_nonce', 'nonce' );

        $notification_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $user_id         = get_current_user_id();

        if ( ! $notification_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_notifications';

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array( 'is_read' => 1 ),
            array( 'id' => $notification_id, 'user_id' => $user_id ),
            array( '%d' ),
            array( '%d', '%d' )
        );

        wp_send_json_success();
    }

    /** AJAX: mark all of the current user's notifications as read. */
    public function ajax_mark_all_read() {
        check_ajax_referer( 'eim_notifications_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_notifications';

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array( 'is_read' => 1 ),
            array( 'user_id' => $user_id ),
            array( '%d' ),
            array( '%d' )
        );

        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Create a new in-app notification for a user.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $type    Notification type slug (e.g. 'new_images', 'message').
     * @param string $message Human-readable notification message.
     * @param string $link    Optional URL the notification links to.
     * @return int|false      Inserted row ID or false on failure.
     */
    public function add_notification( $user_id, $type, $message, $link = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_notifications';

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'user_id'    => absint( $user_id ),
                'type'       => sanitize_text_field( $type ),
                'message'    => sanitize_text_field( $message ),
                'link'       => esc_url_raw( $link ),
                'is_read'    => 0,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the notifications table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_notifications';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL,
            type       VARCHAR(64) NOT NULL DEFAULT '',
            message    VARCHAR(255) NOT NULL DEFAULT '',
            link       VARCHAR(255) NOT NULL DEFAULT '',
            is_read    TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id  (user_id),
            KEY is_read  (is_read)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

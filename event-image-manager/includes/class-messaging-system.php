<?php
/**
 * Class EIM_Messaging_System
 *
 * Private messaging between photographers and clients.
 *
 * - `eim_send_message`      : send a message to another user.
 * - `eim_get_messages`      : retrieve messages in a conversation.
 * - `eim_get_conversations` : list all conversation threads for the current user.
 * - Messages stored in `{prefix}eim_messages`.
 */
class EIM_Messaging_System {

    public function __construct() {
        add_action( 'wp_ajax_eim_send_message',       array( $this, 'ajax_send_message' ) );
        add_action( 'wp_ajax_eim_get_messages',       array( $this, 'ajax_get_messages' ) );
        add_action( 'wp_ajax_eim_get_conversations',  array( $this, 'ajax_get_conversations' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: send a private message to another user. */
    public function ajax_send_message() {
        check_ajax_referer( 'eim_messaging_nonce', 'nonce' );

        $to_user_id = isset( $_POST['to_user_id'] ) ? absint( $_POST['to_user_id'] )                              : 0;
        $subject    = isset( $_POST['subject'] )    ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body       = isset( $_POST['body'] )       ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

        if ( ! $to_user_id || '' === $body ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $from_user_id = get_current_user_id();
        if ( ! $from_user_id ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to send messages.', 'event-image-manager' ) ) );
        }

        $message_id = $this->send_message( $from_user_id, $to_user_id, $subject, $body );

        if ( ! $message_id ) {
            wp_send_json_error( array( 'message' => __( 'Could not send message.', 'event-image-manager' ) ) );
        }

        wp_send_json_success( array( 'message_id' => $message_id ) );
    }

    /** AJAX: retrieve messages between the current user and another user. */
    public function ajax_get_messages() {
        check_ajax_referer( 'eim_messaging_nonce', 'nonce' );

        $other_user_id = isset( $_GET['with'] ) ? absint( $_GET['with'] ) : 0;
        $current_user  = get_current_user_id();

        if ( ! $other_user_id || ! $current_user ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_messages';

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE ( from_user_id = %d AND to_user_id = %d )
                    OR ( from_user_id = %d AND to_user_id = %d )
                 ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $current_user, $other_user_id,
                $other_user_id, $current_user
            )
        );

        // Mark received messages as read.
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array( 'is_read' => 1 ),
            array( 'from_user_id' => $other_user_id, 'to_user_id' => $current_user, 'is_read' => 0 ),
            array( '%d' ),
            array( '%d', '%d', '%d' )
        );

        wp_send_json_success( array( 'messages' => $rows ) );
    }

    /** AJAX: list all unique conversation partners for the current user. */
    public function ajax_get_conversations() {
        check_ajax_referer( 'eim_messaging_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_messages';

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT DISTINCT
                    CASE WHEN from_user_id = %d THEN to_user_id ELSE from_user_id END AS other_user_id,
                    MAX(created_at) AS last_message_at
                 FROM {$table}
                 WHERE from_user_id = %d OR to_user_id = %d
                 GROUP BY other_user_id
                 ORDER BY last_message_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id, $user_id, $user_id
            )
        );

        wp_send_json_success( array( 'conversations' => $rows ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Store a private message.
     *
     * @param int    $from_user_id Sender's user ID.
     * @param int    $to_user_id   Recipient's user ID.
     * @param string $subject      Message subject.
     * @param string $body         Message body.
     * @return int|false           Inserted message ID or false on failure.
     */
    public function send_message( $from_user_id, $to_user_id, $subject, $body ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_messages';

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'from_user_id' => absint( $from_user_id ),
                'to_user_id'   => absint( $to_user_id ),
                'subject'      => sanitize_text_field( $subject ),
                'body'         => sanitize_textarea_field( $body ),
                'is_read'      => 0,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the messages table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_messages';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            from_user_id BIGINT UNSIGNED NOT NULL,
            to_user_id   BIGINT UNSIGNED NOT NULL,
            subject      VARCHAR(255) NOT NULL DEFAULT '',
            body         TEXT NOT NULL,
            is_read      TINYINT(1) NOT NULL DEFAULT 0,
            created_at   DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY from_user (from_user_id),
            KEY to_user   (to_user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

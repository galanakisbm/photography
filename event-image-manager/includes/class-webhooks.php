<?php
/**
 * Class EIM_Webhooks
 *
 * Event-based webhook delivery system.
 *
 * Supported events: image.uploaded, download.completed, event.created, purchase.completed.
 * Webhook registrations stored in `{prefix}eim_webhooks`.
 *
 * AJAX handlers:
 *   - `eim_add_webhook`    : register a new webhook.
 *   - `eim_remove_webhook` : delete a webhook by ID.
 *   - `eim_list_webhooks`  : list the current user's webhooks.
 *   - `eim_test_webhook`   : send a test payload to a webhook.
 */
class EIM_Webhooks {

    public function __construct() {
        add_action( 'wp_ajax_eim_add_webhook',    array( $this, 'ajax_add' ) );
        add_action( 'wp_ajax_eim_remove_webhook', array( $this, 'ajax_remove' ) );
        add_action( 'wp_ajax_eim_list_webhooks',  array( $this, 'ajax_list' ) );
        add_action( 'wp_ajax_eim_test_webhook',   array( $this, 'ajax_test' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: register a new webhook. */
    public function ajax_add() {
        check_ajax_referer( 'eim_webhooks_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $url    = isset( $_POST['url'] )    ? esc_url_raw( wp_unslash( $_POST['url'] ) )                       : '';
        $events = isset( $_POST['events'] ) ? sanitize_text_field( wp_unslash( $_POST['events'] ) )            : '[]';
        $secret = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) )            : wp_generate_password( 24, false );

        if ( ! $url ) {
            wp_send_json_error( array( 'message' => __( 'A valid URL is required.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_webhooks';

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'user_id'    => get_current_user_id(),
                'url'        => $url,
                'events'     => $events,
                'secret'     => $secret,
                'is_active'  => 1,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s' )
        );

        wp_send_json_success( array(
            'webhook_id' => $wpdb->insert_id,
            'secret'     => $secret,
        ) );
    }

    /** AJAX: remove a webhook. */
    public function ajax_remove() {
        check_ajax_referer( 'eim_webhooks_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;
        if ( ! $webhook_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_webhooks';

        $wpdb->delete( $table, array( 'id' => $webhook_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        wp_send_json_success();
    }

    /** AJAX: list all webhooks for the current user. */
    public function ajax_list() {
        check_ajax_referer( 'eim_webhooks_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'eim_webhooks';
        $user_id  = get_current_user_id();

        $webhooks = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT id, url, events, is_active, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id
            )
        );

        wp_send_json_success( array( 'webhooks' => $webhooks ) );
    }

    /** AJAX: send a test payload to a specific webhook. */
    public function ajax_test() {
        check_ajax_referer( 'eim_webhooks_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;
        if ( ! $webhook_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'eim_webhooks';
        $webhook = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $webhook_id
            )
        );

        if ( ! $webhook ) {
            wp_send_json_error( array( 'message' => __( 'Webhook not found.', 'event-image-manager' ) ) );
        }

        $result = $this->send_webhook( $webhook->url, 'test', array( 'message' => 'Test payload from EIM.' ), $webhook->secret );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Test payload sent.', 'event-image-manager' ) ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Trigger all active webhooks that subscribe to a given event.
     *
     * @param string $event Event slug (e.g. 'image.uploaded').
     * @param array  $data  Payload data.
     */
    public function trigger( $event, $data = array() ) {
        global $wpdb;
        $table    = $wpdb->prefix . 'eim_webhooks';
        $webhooks = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT * FROM {$table} WHERE is_active = 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        foreach ( $webhooks as $webhook ) {
            $subscribed = json_decode( $webhook->events, true );
            if ( ! is_array( $subscribed ) || ! in_array( $event, $subscribed, true ) ) {
                continue;
            }
            $this->send_webhook( $webhook->url, $event, $data, $webhook->secret );
        }
    }

    /**
     * Deliver a single webhook payload via HTTP POST.
     *
     * @param  string      $url    Destination URL.
     * @param  string      $event  Event slug.
     * @param  array       $data   Payload.
     * @param  string      $secret Signing secret for the X-EIM-Signature header.
     * @return true|\WP_Error
     */
    public function send_webhook( $url, $event, $data, $secret = '' ) {
        $payload = wp_json_encode( array(
            'event'     => $event,
            'timestamp' => time(),
            'data'      => $data,
        ) );

        $headers = array( 'Content-Type' => 'application/json' );
        if ( $secret ) {
            $headers['X-EIM-Signature'] = 'sha256=' . hash_hmac( 'sha256', $payload, $secret );
        }

        $response = wp_remote_post( esc_url_raw( $url ), array(
            'body'    => $payload,
            'headers' => $headers,
            'timeout' => 10,
        ) );

        return is_wp_error( $response ) ? $response : true;
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the webhooks table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_webhooks';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            url        VARCHAR(512) NOT NULL,
            events     TEXT NOT NULL,
            secret     VARCHAR(128) NOT NULL DEFAULT '',
            is_active  TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

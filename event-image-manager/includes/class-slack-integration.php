<?php
/**
 * Class EIM_Slack_Integration
 *
 * Sends Slack notifications and handles incoming slash commands.
 *
 * Options:
 *   - `eim_slack_webhook_url` : Incoming Webhook URL for the Slack workspace.
 *   - `eim_slack_channel`     : Default channel (e.g. #photography).
 *
 * Hooks:
 *   - `eim_images_added`   : notify Slack when images are added.
 *   - `save_post_event`    : notify Slack when a new event is created.
 *   - `eim_order_complete` : notify Slack when a purchase completes.
 */
class EIM_Slack_Integration {

    public function __construct() {
        add_action( 'eim_images_added',   array( $this, 'on_images_added' ), 10, 2 );
        add_action( 'save_post_event',    array( $this, 'on_new_event' ), 10, 3 );
        add_action( 'eim_order_complete', array( $this, 'on_purchase_complete' ), 10, 2 );

        // Incoming Slack slash command endpoint.
        add_action( 'wp_ajax_nopriv_eim_slack_command', array( $this, 'handle_slash_command' ) );
    }

    // -------------------------------------------------------------------------
    // Hook callbacks
    // -------------------------------------------------------------------------

    /**
     * Notify Slack when new images are added to an event.
     *
     * @param int $post_id Event post ID.
     * @param int $count   Number of images added.
     */
    public function on_images_added( $post_id, $count ) {
        /* translators: %1$d: count, %2$s: event title, %3$s: event URL */
        $message = sprintf(
            __( ':frame_with_picture: %1$d new photo(s) added to <%3$s|%2$s>.', 'event-image-manager' ),
            $count,
            get_the_title( $post_id ),
            get_permalink( $post_id )
        );
        $this->send_notification( $message );
    }

    /**
     * Notify Slack when a new event post is published.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     */
    public function on_new_event( $post_id, $post, $update ) {
        if ( $update || 'publish' !== $post->post_status ) {
            return;
        }
        /* translators: %1$s: event title, %2$s: event URL */
        $message = sprintf(
            __( ':calendar: New event created: <%2$s|%1$s>.', 'event-image-manager' ),
            get_the_title( $post_id ),
            get_permalink( $post_id )
        );
        $this->send_notification( $message );
    }

    /**
     * Notify Slack when a purchase is complete.
     *
     * @param int $order_id EIM order ID.
     * @param int $user_id  Purchaser's user ID.
     */
    public function on_purchase_complete( $order_id, $user_id ) {
        $user    = get_userdata( $user_id );
        $name    = $user ? $user->display_name : __( 'Guest', 'event-image-manager' );
        /* translators: %1$s: customer name, %2$d: order ID */
        $message = sprintf(
            __( ':moneybag: New purchase by %1$s (Order #%2$d).', 'event-image-manager' ),
            $name, $order_id
        );
        $this->send_notification( $message );
    }

    // -------------------------------------------------------------------------
    // Slash command handler
    // -------------------------------------------------------------------------

    /**
     * Handle an incoming Slack slash command (POST to wp-admin/admin-ajax.php?action=eim_slack_command).
     * Performs basic token verification via a stored option.
     */
    public function handle_slash_command() {
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        $expected = get_option( 'eim_slack_verification_token', '' );

        if ( ! $expected || ! hash_equals( $expected, $token ) ) {
            wp_send_json( array( 'text' => __( 'Unauthorized.', 'event-image-manager' ) ), 403 );
        }

        $command = isset( $_POST['command'] ) ? sanitize_text_field( wp_unslash( $_POST['command'] ) ) : '';
        $text    = isset( $_POST['text'] )    ? sanitize_text_field( wp_unslash( $_POST['text'] ) )    : '';

        switch ( $command ) {
            case '/eim-stats':
                $response_text = $this->stats_response();
                break;
            default:
                /* translators: %s: command name */
                $response_text = sprintf( __( 'Unknown command: %s', 'event-image-manager' ), esc_html( $command ) );
        }

        wp_send_json( array( 'text' => $response_text ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Send a message to Slack.
     *
     * @param string $message Message text (supports Slack mrkdwn).
     * @param string $channel Optional channel override (e.g. '#alerts').
     * @return true|\WP_Error
     */
    public function send_notification( $message, $channel = '' ) {
        $webhook_url = get_option( 'eim_slack_webhook_url', '' );
        if ( ! $webhook_url ) {
            return new WP_Error( 'not_configured', __( 'Slack webhook URL is not set.', 'event-image-manager' ) );
        }

        if ( ! $channel ) {
            $channel = get_option( 'eim_slack_channel', '#general' );
        }

        $payload = array(
            'text'    => $message,
            'channel' => sanitize_text_field( $channel ),
        );

        $response = wp_remote_post( esc_url_raw( $webhook_url ), array(
            'body'    => wp_json_encode( $payload ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 10,
        ) );

        return is_wp_error( $response ) ? $response : true;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a simple stats string for the /eim-stats command.
     *
     * @return string
     */
    private function stats_response() {
        $event_count = wp_count_posts( 'event' );
        /* translators: %d: number of published events */
        return sprintf( __( 'There are currently %d published events.', 'event-image-manager' ), (int) $event_count->publish );
    }
}

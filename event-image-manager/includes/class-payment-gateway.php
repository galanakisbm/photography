<?php
/**
 * Class EIM_Payment_Gateway
 *
 * Payment processing via Stripe and PayPal.
 *
 * API keys are stored in WordPress options:
 *   - `eim_stripe_api_key`   : Stripe secret key.
 *   - `eim_paypal_client_id` : PayPal client ID.
 *
 * Orders are recorded in `{prefix}eim_orders`.
 */
class EIM_Payment_Gateway {

    public function __construct() {
        add_action( 'wp_ajax_eim_create_payment_intent', array( $this, 'ajax_create_payment_intent' ) );
        add_action( 'wp_ajax_eim_confirm_payment',       array( $this, 'ajax_confirm_payment' ) );
        add_action( 'wp_ajax_eim_process_paypal',        array( $this, 'ajax_process_paypal' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: create a Stripe PaymentIntent and return the client secret. */
    public function ajax_create_payment_intent() {
        check_ajax_referer( 'eim_payment_nonce', 'nonce' );

        $amount   = isset( $_POST['amount'] )   ? absint( $_POST['amount'] )                                     : 0;
        $currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'usd';
        $items    = isset( $_POST['items'] )    ? sanitize_text_field( wp_unslash( $_POST['items'] ) )    : '[]';

        if ( ! $amount ) {
            wp_send_json_error( array( 'message' => __( 'Invalid amount.', 'event-image-manager' ) ) );
        }

        $result = $this->process_stripe(
            $amount,
            $currency,
            array( 'user_id' => get_current_user_id(), 'items' => $items )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /** AJAX: confirm a payment and record the order. */
    public function ajax_confirm_payment() {
        check_ajax_referer( 'eim_payment_nonce', 'nonce' );

        $transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
        $items          = isset( $_POST['items'] )          ? sanitize_text_field( wp_unslash( $_POST['items'] ) )          : '[]';

        if ( ! $transaction_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $order_id = $this->complete_order( $transaction_id, get_current_user_id(), $items );

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'Could not save order.', 'event-image-manager' ) ) );
        }

        wp_send_json_success( array( 'order_id' => $order_id ) );
    }

    /** AJAX: process a PayPal payment. */
    public function ajax_process_paypal() {
        check_ajax_referer( 'eim_payment_nonce', 'nonce' );

        $amount     = isset( $_POST['amount'] )     ? absint( $_POST['amount'] )                                         : 0;
        $currency   = isset( $_POST['currency'] )   ? sanitize_text_field( wp_unslash( $_POST['currency'] ) )   : 'USD';
        $return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) )          : '';
        $cancel_url = isset( $_POST['cancel_url'] ) ? esc_url_raw( wp_unslash( $_POST['cancel_url'] ) )          : '';

        if ( ! $amount ) {
            wp_send_json_error( array( 'message' => __( 'Invalid amount.', 'event-image-manager' ) ) );
        }

        $result = $this->process_paypal( $amount, $currency, $return_url, $cancel_url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    // -------------------------------------------------------------------------
    // Payment processors
    // -------------------------------------------------------------------------

    /**
     * Initiate a Stripe PaymentIntent.
     *
     * @param  int    $amount   Amount in the smallest currency unit (e.g. cents).
     * @param  string $currency ISO 4217 currency code.
     * @param  array  $metadata Key/value metadata to attach.
     * @return array|\WP_Error  Response array with `client_secret`, or WP_Error.
     */
    public function process_stripe( $amount, $currency, $metadata = array() ) {
        $api_key = get_option( 'eim_stripe_api_key', '' );
        if ( ! $api_key ) {
            return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'event-image-manager' ) );
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount'   => absint( $amount ),
                'currency' => sanitize_text_field( $currency ),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['client_secret'] ) ) {
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Stripe error.', 'event-image-manager' );
            return new WP_Error( 'stripe_error', sanitize_text_field( $message ) );
        }

        return array(
            'client_secret'      => $body['client_secret'],
            'payment_intent_id'  => $body['id'],
        );
    }

    /**
     * Create a PayPal order via the PayPal Orders API v2.
     *
     * @param  int    $amount     Amount in minor units.
     * @param  string $currency   ISO 4217 currency code.
     * @param  string $return_url Success redirect URL.
     * @param  string $cancel_url Cancel redirect URL.
     * @return array|\WP_Error    Response array with `approve_url`, or WP_Error.
     */
    public function process_paypal( $amount, $currency, $return_url, $cancel_url ) {
        $client_id = get_option( 'eim_paypal_client_id', '' );
        if ( ! $client_id ) {
            return new WP_Error( 'paypal_not_configured', __( 'PayPal is not configured.', 'event-image-manager' ) );
        }

        // Format amount as decimal string.
        $formatted = number_format( $amount / 100, 2, '.', '' );

        $response = wp_remote_post( 'https://api-m.paypal.com/v2/checkout/orders', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $client_id,
            ),
            'body' => wp_json_encode( array(
                'intent'         => 'CAPTURE',
                'purchase_units' => array( array(
                    'amount' => array(
                        'currency_code' => strtoupper( sanitize_text_field( $currency ) ),
                        'value'         => $formatted,
                    ),
                ) ),
                'application_context' => array(
                    'return_url' => esc_url_raw( $return_url ),
                    'cancel_url' => esc_url_raw( $cancel_url ),
                ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $approve_url = '';
        if ( ! empty( $body['links'] ) ) {
            foreach ( $body['links'] as $link ) {
                if ( 'approve' === $link['rel'] ) {
                    $approve_url = $link['href'];
                    break;
                }
            }
        }

        if ( ! $approve_url ) {
            return new WP_Error( 'paypal_error', __( 'Could not create PayPal order.', 'event-image-manager' ) );
        }

        return array(
            'order_id'    => $body['id'],
            'approve_url' => $approve_url,
        );
    }

    /**
     * Record a completed order in the database.
     *
     * @param  string     $transaction_id Gateway transaction identifier.
     * @param  int        $user_id        WordPress user ID.
     * @param  string     $items          JSON-encoded items array.
     * @return int|false                  Inserted order ID or false.
     */
    public function complete_order( $transaction_id, $user_id, $items ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_orders';

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'user_id'        => absint( $user_id ),
                'transaction_id' => sanitize_text_field( $transaction_id ),
                'gateway'        => 'stripe',
                'amount'         => 0,
                'currency'       => 'usd',
                'status'         => 'complete',
                'items'          => $items,
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the orders table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_orders';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id        BIGINT UNSIGNED NOT NULL DEFAULT 0,
            transaction_id VARCHAR(255) NOT NULL DEFAULT '',
            gateway        ENUM('stripe','paypal') NOT NULL DEFAULT 'stripe',
            amount         DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            currency       VARCHAR(8) NOT NULL DEFAULT 'usd',
            status         VARCHAR(32) NOT NULL DEFAULT 'pending',
            items          TEXT NOT NULL,
            created_at     DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status  (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

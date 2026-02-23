<?php
/**
 * Class EIM_Print_On_Demand
 *
 * Integration with Printful print-on-demand service.
 *
 * The Printful API key is stored in option `eim_printful_api_key`.
 *
 * AJAX handlers:
 *   - `eim_create_pod_order`    : create a print order.
 *   - `eim_get_pod_products`    : list available print products.
 *   - `eim_get_pod_order_status`: get status of a print order.
 */
class EIM_Print_On_Demand {

    /** Printful API base URL. */
    const API_BASE = 'https://api.printful.com';

    public function __construct() {
        add_action( 'wp_ajax_eim_create_pod_order',      array( $this, 'ajax_create_order' ) );
        add_action( 'wp_ajax_eim_get_pod_products',      array( $this, 'ajax_get_products' ) );
        add_action( 'wp_ajax_eim_get_pod_order_status',  array( $this, 'ajax_get_order_status' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: create a print-on-demand order. */
    public function ajax_create_order() {
        check_ajax_referer( 'eim_pod_nonce', 'nonce' );

        $user_id      = get_current_user_id();
        $img_path     = isset( $_POST['img_path'] )     ? sanitize_text_field( wp_unslash( $_POST['img_path'] ) )     : '';
        $product_type = isset( $_POST['product_type'] ) ? sanitize_text_field( wp_unslash( $_POST['product_type'] ) ) : '';
        $size         = isset( $_POST['size'] )         ? sanitize_text_field( wp_unslash( $_POST['size'] ) )         : '';
        $quantity     = isset( $_POST['quantity'] )     ? absint( $_POST['quantity'] )                                : 1;

        if ( ! $img_path || ! $product_type ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $result = $this->create_order( $user_id, $img_path, $product_type, $size, $quantity );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'order' => $result ) );
    }

    /** AJAX: return available print products from Printful. */
    public function ajax_get_products() {
        check_ajax_referer( 'eim_pod_nonce', 'nonce' );

        $products = $this->get_available_products();

        if ( is_wp_error( $products ) ) {
            wp_send_json_error( array( 'message' => $products->get_error_message() ) );
        }

        wp_send_json_success( array( 'products' => $products ) );
    }

    /** AJAX: get the status of a Printful order by external ID. */
    public function ajax_get_order_status() {
        check_ajax_referer( 'eim_pod_nonce', 'nonce' );

        $order_id = isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) : '';
        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $status = $this->get_sync_status_by_order( $order_id );

        if ( is_wp_error( $status ) ) {
            wp_send_json_error( array( 'message' => $status->get_error_message() ) );
        }

        wp_send_json_success( array( 'status' => $status ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Create a print order via the Printful API.
     *
     * @param  int    $user_id      WordPress user ID.
     * @param  string $img_path     Absolute or URL path to the image.
     * @param  string $product_type Printful product variant ID or slug.
     * @param  string $size         Size string.
     * @param  int    $quantity     Order quantity.
     * @return array|\WP_Error      Printful order response or WP_Error.
     */
    public function create_order( $user_id, $img_path, $product_type, $size, $quantity ) {
        $api_key = get_option( 'eim_printful_api_key', '' );
        if ( ! $api_key ) {
            return new WP_Error( 'not_configured', __( 'Printful API key is not set.', 'event-image-manager' ) );
        }

        $body = wp_json_encode( array(
            'recipient' => array(
                'name'         => 'Customer',
                'address1'     => '',
                'city'         => '',
                'country_code' => 'US',
            ),
            'items' => array(
                array(
                    'variant_id' => (int) $product_type,
                    'quantity'   => absint( $quantity ),
                    'files'      => array(
                        array( 'url' => esc_url_raw( $img_path ) ),
                    ),
                ),
            ),
        ) );

        $response = wp_remote_post( self::API_BASE . '/orders', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                'Content-Type'  => 'application/json',
            ),
            'body' => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['result'] ) ) {
            $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Printful error.', 'event-image-manager' );
            return new WP_Error( 'printful_error', sanitize_text_field( $msg ) );
        }

        return $data['result'];
    }

    /**
     * Fetch available products from Printful.
     *
     * @return array|\WP_Error Product list or WP_Error.
     */
    public function get_available_products() {
        $api_key = get_option( 'eim_printful_api_key', '' );
        if ( ! $api_key ) {
            return new WP_Error( 'not_configured', __( 'Printful API key is not set.', 'event-image-manager' ) );
        }

        $response = wp_remote_get( self::API_BASE . '/products', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['result'] ) ? $data['result'] : array();
    }

    /**
     * Get order status from Printful by order ID.
     *
     * @param  string       $order_id Printful order ID.
     * @return array|\WP_Error
     */
    private function get_sync_status_by_order( $order_id ) {
        $api_key = get_option( 'eim_printful_api_key', '' );
        if ( ! $api_key ) {
            return new WP_Error( 'not_configured', __( 'Printful API key is not set.', 'event-image-manager' ) );
        }

        $response = wp_remote_get( self::API_BASE . '/orders/' . rawurlencode( $order_id ), array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['result'] ) ? $data['result'] : array();
    }
}

<?php
/**
 * Class EIM_Product_System
 *
 * Manages purchasable photo products tied to individual gallery images.
 *
 * - `eim_get_product`  : retrieve product details.
 * - `eim_add_to_cart`  : add a product to the user's session cart.
 * - `eim_get_cart`     : return the current cart contents.
 * - Products stored in `{prefix}eim_products`.
 */
class EIM_Product_System {

    /** Transient key prefix for per-user carts. */
    const CART_TRANSIENT_PREFIX = 'eim_cart_';

    public function __construct() {
        add_action( 'wp_ajax_eim_get_product',        array( $this, 'ajax_get_product' ) );
        add_action( 'wp_ajax_nopriv_eim_get_product', array( $this, 'ajax_get_product' ) );
        add_action( 'wp_ajax_eim_add_to_cart',        array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_eim_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_eim_get_cart',           array( $this, 'ajax_get_cart' ) );
        add_action( 'wp_ajax_nopriv_eim_get_cart',    array( $this, 'ajax_get_cart' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: return product details by ID. */
    public function ajax_get_product() {
        check_ajax_referer( 'eim_products_nonce', 'nonce' );

        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $product = $this->get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'event-image-manager' ) ) );
        }

        wp_send_json_success( array( 'product' => $product ) );
    }

    /** AJAX: add a product to the session cart. */
    public function ajax_add_to_cart() {
        check_ajax_referer( 'eim_products_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $product = $this->get_product( $product_id );
        if ( ! $product || ! $product->is_active ) {
            wp_send_json_error( array( 'message' => __( 'Product unavailable.', 'event-image-manager' ) ) );
        }

        $cart_key = $this->get_cart_key();
        $cart     = get_transient( $cart_key );
        if ( ! is_array( $cart ) ) {
            $cart = array();
        }

        // Avoid duplicates.
        if ( ! in_array( $product_id, array_column( $cart, 'product_id' ), true ) ) {
            $cart[] = array(
                'product_id' => $product_id,
                'price'      => (float) $product->price,
            );
        }

        set_transient( $cart_key, $cart, DAY_IN_SECONDS );

        wp_send_json_success( array(
            'cart'    => $cart,
            'message' => __( 'Added to cart.', 'event-image-manager' ),
        ) );
    }

    /** AJAX: return the current cart. */
    public function ajax_get_cart() {
        check_ajax_referer( 'eim_products_nonce', 'nonce' );

        $cart_key = $this->get_cart_key();
        $cart     = get_transient( $cart_key );
        if ( ! is_array( $cart ) ) {
            $cart = array();
        }
        wp_send_json_success( array( 'cart' => $cart ) );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return a per-user (or per-browser) cart transient key.
     *
     * @return string
     */
    private function get_cart_key() {
        $user_id = get_current_user_id();
        if ( $user_id ) {
            return self::CART_TRANSIENT_PREFIX . $user_id;
        }
        // Guest: key by hashed REMOTE_ADDR + user agent for a rough fingerprint.
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        return self::CART_TRANSIENT_PREFIX . 'guest_' . md5( $ip . $ua );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Create a new product entry.
     *
     * @param int    $post_id      Event post ID.
     * @param int    $img_index    Image index within the gallery.
     * @param float  $price        Sale price.
     * @param string $license_type License type string (e.g. 'personal', 'commercial').
     * @return int|false           Inserted row ID or false.
     */
    public function create_product( $post_id, $img_index, $price, $license_type ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_products';

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'post_id'      => absint( $post_id ),
                'img_index'    => absint( $img_index ),
                'price'        => round( (float) $price, 2 ),
                'license_type' => sanitize_text_field( $license_type ),
                'is_active'    => 1,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%f', '%s', '%d', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Retrieve a single product by its row ID.
     *
     * @param  int        $product_id Row ID.
     * @return object|null             Product row object or null.
     */
    public function get_product( $product_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_products';

        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $product_id )
            )
        );
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the products table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_products';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id      BIGINT UNSIGNED NOT NULL,
            img_index    INT UNSIGNED NOT NULL,
            price        DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            license_type VARCHAR(64) NOT NULL DEFAULT 'personal',
            is_active    TINYINT(1) NOT NULL DEFAULT 1,
            created_at   DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id  (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

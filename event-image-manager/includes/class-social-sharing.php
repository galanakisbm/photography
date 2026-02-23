<?php
/**
 * Class EIM_Social_Sharing
 *
 * Adds social-sharing buttons for gallery images and events.
 *
 * - Shortcode `[eim_share_buttons]` renders share buttons for the current post.
 * - Supports Facebook, Twitter/X, Pinterest, and WhatsApp.
 * - Share clicks are tracked in `{prefix}eim_share_logs`.
 */
class EIM_Social_Sharing {

    public function __construct() {
        add_shortcode( 'eim_share_buttons', array( $this, 'shortcode_share_buttons' ) );
        add_action( 'wp_ajax_eim_track_share',        array( $this, 'ajax_track_share' ) );
        add_action( 'wp_ajax_nopriv_eim_track_share', array( $this, 'ajax_track_share' ) );
    }

    // -------------------------------------------------------------------------
    // Shortcode
    // -------------------------------------------------------------------------

    /**
     * Render share buttons for the current or specified post.
     *
     * @param  array $atts Shortcode attributes.
     * @return string      HTML output.
     */
    public function shortcode_share_buttons( $atts ) {
        $atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'eim_share_buttons' );
        $post_id = absint( $atts['id'] ) ?: get_the_ID();

        if ( ! $post_id ) {
            return '';
        }

        $url   = rawurlencode( get_permalink( $post_id ) );
        $title = rawurlencode( get_the_title( $post_id ) );

        $networks = array(
            'facebook'  => array(
                'label' => __( 'Share on Facebook', 'event-image-manager' ),
                'url'   => $this->get_share_url( 'facebook', $url, $title ),
                'color' => '#1877f2',
            ),
            'twitter'   => array(
                'label' => __( 'Share on X (Twitter)', 'event-image-manager' ),
                'url'   => $this->get_share_url( 'twitter', $url, $title ),
                'color' => '#000000',
            ),
            'pinterest' => array(
                'label' => __( 'Share on Pinterest', 'event-image-manager' ),
                'url'   => $this->get_share_url( 'pinterest', $url, $title ),
                'color' => '#e60023',
            ),
            'whatsapp'  => array(
                'label' => __( 'Share on WhatsApp', 'event-image-manager' ),
                'url'   => $this->get_share_url( 'whatsapp', $url, $title ),
                'color' => '#25d366',
            ),
        );

        $nonce = wp_create_nonce( 'eim_share_nonce' );

        ob_start();
        ?>
        <div class="eim-share-buttons" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php foreach ( $networks as $network => $info ) : ?>
                <a
                    href="<?php echo esc_url( $info['url'] ); ?>"
                    class="eim-share-btn eim-share-<?php echo esc_attr( $network ); ?>"
                    data-network="<?php echo esc_attr( $network ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    style="background:<?php echo esc_attr( $info['color'] ); ?>;color:#fff;padding:6px 14px;border-radius:4px;text-decoration:none;display:inline-block;margin:2px;"
                    aria-label="<?php echo esc_attr( $info['label'] ); ?>"
                ><?php echo esc_html( $info['label'] ); ?></a>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: record a share event. */
    public function ajax_track_share() {
        check_ajax_referer( 'eim_share_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] )                                  : 0;
        $network = isset( $_POST['network'] ) ? sanitize_text_field( wp_unslash( $_POST['network'] ) ) : '';

        $allowed_networks = array( 'facebook', 'twitter', 'pinterest', 'whatsapp' );
        if ( ! $post_id || ! in_array( $network, $allowed_networks, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'eim_share_logs';
        $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'post_id'    => $post_id,
                'network'    => $network,
                'user_id'    => get_current_user_id(),
                'ip_address' => $ip,
                'shared_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%s', '%s' )
        );

        wp_send_json_success( array( 'message' => __( 'Share tracked.', 'event-image-manager' ) ) );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the share URL for a given social network.
     *
     * @param  string $network  Network slug (facebook|twitter|pinterest|whatsapp).
     * @param  string $url      URL-encoded page URL.
     * @param  string $title    URL-encoded page title.
     * @return string           Full share URL.
     */
    public function get_share_url( $network, $url, $title ) {
        switch ( $network ) {
            case 'facebook':
                return 'https://www.facebook.com/sharer/sharer.php?u=' . $url;
            case 'twitter':
                return 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title;
            case 'pinterest':
                return 'https://pinterest.com/pin/create/button/?url=' . $url . '&description=' . $title;
            case 'whatsapp':
                return 'https://wa.me/?text=' . $title . '%20' . $url;
            default:
                return '';
        }
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the share logs table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_share_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id    BIGINT UNSIGNED NOT NULL,
            network    VARCHAR(32) NOT NULL DEFAULT '',
            user_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            shared_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY network (network)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

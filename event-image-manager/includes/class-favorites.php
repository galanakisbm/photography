<?php
/**
 * Class EIM_Favorites
 *
 * Provides a favorites / likes system for individual gallery images.
 *
 * - Logged-in users can toggle a favorite on any image.
 * - Guest favorites are tracked by IP address (optional).
 * - Favorite counts are stored in `{prefix}event_image_favorites`
 *   and denormalized into `{prefix}event_image_metadata` for fast sorting.
 */
class EIM_Favorites {

    public function __construct() {
        add_action( 'wp_ajax_eim_toggle_favorite',        array( $this, 'ajax_toggle' ) );
        add_action( 'wp_ajax_nopriv_eim_toggle_favorite', array( $this, 'ajax_toggle' ) );
        add_action( 'wp_ajax_eim_get_favorites',          array( $this, 'ajax_get_favorites' ) );
        add_action( 'wp_ajax_nopriv_eim_get_favorites',   array( $this, 'ajax_get_favorites' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: toggle a favorite for the current user / IP. */
    public function ajax_toggle() {
        check_ajax_referer( 'eim_favorites_nonce', 'nonce' );

        $post_id   = isset( $_POST['post_id'] )   ? absint( $_POST['post_id'] )   : 0;
        $img_index = isset( $_POST['img_index'] ) ? absint( $_POST['img_index'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $user_id = get_current_user_id();
        $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        global $wpdb;
        $table = $wpdb->prefix . 'event_image_favorites';

        // Check if already favorited.
        if ( $user_id ) {
            $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE post_id = %d AND img_index = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $post_id, $img_index, $user_id
                )
            );
        } else {
            $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE post_id = %d AND img_index = %d AND ip_address = %s AND user_id = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $post_id, $img_index, $ip
                )
            );
        }

        if ( $existing ) {
            // Remove favorite.
            $wpdb->delete( $table, array( 'id' => $existing ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $favorited = false;
        } else {
            // Add favorite.
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $table,
                array(
                    'post_id'    => $post_id,
                    'img_index'  => $img_index,
                    'user_id'    => $user_id,
                    'ip_address' => $ip,
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%s', '%s' )
            );
            $favorited = true;
        }

        // Update denormalized count in metadata table.
        $count = $this->get_count( $post_id, $img_index );
        $this->update_metadata_count( $post_id, $img_index, $count );

        wp_send_json_success( array(
            'favorited' => $favorited,
            'count'     => $count,
        ) );
    }

    /** AJAX: get the current user's favorites for an event. */
    public function ajax_get_favorites() {
        check_ajax_referer( 'eim_favorites_nonce', 'nonce' );

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $user_id = get_current_user_id();
        $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        global $wpdb;
        $table = $wpdb->prefix . 'event_image_favorites';

        if ( $user_id ) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT img_index FROM {$table} WHERE post_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $post_id, $user_id
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT img_index FROM {$table} WHERE post_id = %d AND ip_address = %s AND user_id = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $post_id, $ip
                ),
                ARRAY_A
            );
        }

        $indices = is_array( $rows ) ? array_column( $rows, 'img_index' ) : array();
        wp_send_json_success( array( 'favorites' => $indices ) );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the total favorite count for an image.
     *
     * @param  int $post_id
     * @param  int $img_index
     * @return int
     */
    public function get_count( $post_id, $img_index ) {
        global $wpdb;
        $table = $wpdb->prefix . 'event_image_favorites';
        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE post_id = %d AND img_index = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id, $img_index
            )
        );
    }

    /**
     * Update the denormalized favorites_count in the metadata table.
     *
     * @param int $post_id
     * @param int $img_index
     * @param int $count
     */
    private function update_metadata_count( $post_id, $img_index, $count ) {
        global $wpdb;
        $table = $wpdb->prefix . 'event_image_metadata';

        $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE post_id = %d AND img_index = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id, $img_index
            )
        );

        if ( $existing ) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $table,
                array( 'favorites_count' => $count ),
                array( 'post_id' => $post_id, 'img_index' => $img_index ),
                array( '%d' ),
                array( '%d', '%d' )
            );
        } else {
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $table,
                array(
                    'post_id'        => $post_id,
                    'img_index'      => $img_index,
                    'favorites_count' => $count,
                    'uploaded_at'    => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%s' )
            );
        }
    }

    // -------------------------------------------------------------------------
    // Database table creation
    // -------------------------------------------------------------------------

    /** Create the favorites table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'event_image_favorites';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id    BIGINT UNSIGNED NOT NULL,
            img_index  INT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_img  (post_id, img_index),
            KEY user_post (user_id, post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

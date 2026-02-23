<?php
/**
 * Class EIM_Download_Queue
 *
 * Manages a per-user batch download queue.
 *
 * - `eim_add_to_queue`    : add images to the user's queue.
 * - `eim_get_queue_status`: check the status of queued items.
 * - `eim_clear_queue`     : remove all completed/failed items for the user.
 */
class EIM_Download_Queue {

    public function __construct() {
        add_action( 'wp_ajax_eim_add_to_queue',       array( $this, 'ajax_add_to_queue' ) );
        add_action( 'wp_ajax_eim_get_queue_status',   array( $this, 'ajax_get_queue_status' ) );
        add_action( 'wp_ajax_eim_clear_queue',        array( $this, 'ajax_clear_queue' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: add images to the current user's download queue. */
    public function ajax_add_to_queue() {
        check_ajax_referer( 'eim_download_nonce', 'nonce' );

        $post_id  = isset( $_POST['post_id'] )  ? absint( $_POST['post_id'] )                                 : 0;
        $raw_idxs = isset( $_POST['indices'] )  ? sanitize_text_field( wp_unslash( $_POST['indices'] ) ) : '';

        if ( ! $post_id || '' === $raw_idxs ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'eim_download_queue';

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'user_id'     => $user_id,
                'post_id'     => $post_id,
                'img_indices' => $raw_idxs,
                'status'      => 'pending',
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );

        wp_send_json_success( array(
            'queue_id' => $wpdb->insert_id,
            'message'  => __( 'Added to download queue.', 'event-image-manager' ),
        ) );
    }

    /** AJAX: return the current user's queue items with their statuses. */
    public function ajax_get_queue_status() {
        check_ajax_referer( 'eim_download_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $items   = $this->get_user_queue( $user_id );

        wp_send_json_success( array( 'queue' => $items ) );
    }

    /** AJAX: remove all completed or failed queue items for the current user. */
    public function ajax_clear_queue() {
        check_ajax_referer( 'eim_download_nonce', 'nonce' );

        $user_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'eim_download_queue';

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE user_id = %d AND status IN ('complete','failed')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id
            )
        );

        wp_send_json_success( array( 'message' => __( 'Queue cleared.', 'event-image-manager' ) ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return all pending queue items for a user.
     *
     * @param  int   $user_id WordPress user ID.
     * @return array           Array of queue row objects.
     */
    public function get_user_queue( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_download_queue';

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $user_id )
            )
        );
    }
}

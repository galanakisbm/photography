<?php
/**
 * Class EIM_Comments
 *
 * Provides an image-level comments system for event galleries.
 *
 * Comments are stored in a dedicated table `{prefix}event_image_comments`.
 * Logged-in users (or guests if allowed) can post comments on individual
 * gallery images.  Admins can moderate (approve / delete) comments.
 *
 * Security: All inputs are sanitized and CSRF-protected via nonces.
 * Moderation flag defaults to 'pending' so comments are not shown until
 * approved.
 */
class EIM_Comments {

    /** Status values. */
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_SPAM     = 'spam';

    public function __construct() {
        add_action( 'wp_ajax_eim_post_comment',        array( $this, 'ajax_post_comment' ) );
        add_action( 'wp_ajax_nopriv_eim_post_comment', array( $this, 'ajax_post_comment' ) );
        add_action( 'wp_ajax_eim_delete_comment',      array( $this, 'ajax_delete_comment' ) );
        add_action( 'wp_ajax_eim_approve_comment',     array( $this, 'ajax_approve_comment' ) );
        add_action( 'wp_ajax_eim_get_comments',        array( $this, 'ajax_get_comments' ) );
        add_action( 'wp_ajax_nopriv_eim_get_comments', array( $this, 'ajax_get_comments' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: post a new comment on an image. */
    public function ajax_post_comment() {
        check_ajax_referer( 'eim_comments_nonce', 'nonce' );

        $post_id   = isset( $_POST['post_id'] )  ? absint( $_POST['post_id'] )  : 0;
        $img_index = isset( $_POST['img_index'] ) ? absint( $_POST['img_index'] ) : 0;
        $content   = isset( $_POST['content'] )  ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
        $author    = isset( $_POST['author'] )   ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '';

        if ( ! $post_id || '' === $content ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a comment.', 'event-image-manager' ) ) );
        }

        // Require login if guests are not allowed.
        $allow_guests = (bool) get_option( 'eim_comments_allow_guests', false );
        if ( ! $allow_guests && ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to comment.', 'event-image-manager' ) ) );
        }

        $user_id = get_current_user_id();
        if ( $user_id ) {
            $user   = get_userdata( $user_id );
            $author = $user ? $user->display_name : $author;
        }

        // Auto-approve comments from admins; others need moderation.
        $status = current_user_can( 'manage_options' ) ? self::STATUS_APPROVED : self::STATUS_PENDING;

        global $wpdb;
        $table = $wpdb->prefix . 'event_image_comments';
        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'post_id'    => $post_id,
                'img_index'  => $img_index,
                'user_id'    => $user_id,
                'author'     => $author,
                'content'    => $content,
                'status'     => $status,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( ! $inserted ) {
            wp_send_json_error( array( 'message' => __( 'Could not save comment. Please try again.', 'event-image-manager' ) ) );
        }

        $message = self::STATUS_PENDING === $status
            ? __( 'Your comment has been submitted and is awaiting moderation.', 'event-image-manager' )
            : __( 'Your comment has been posted.', 'event-image-manager' );

        wp_send_json_success( array(
            'message' => $message,
            'status'  => $status,
        ) );
    }

    /** AJAX: delete a comment (admins only). */
    public function ajax_delete_comment() {
        check_ajax_referer( 'eim_comments_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
        if ( ! $comment_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid comment.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'event_image_comments';
        $wpdb->delete( $table, array( 'id' => $comment_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        wp_send_json_success();
    }

    /** AJAX: approve a comment (admins only). */
    public function ajax_approve_comment() {
        check_ajax_referer( 'eim_comments_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
        if ( ! $comment_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid comment.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'event_image_comments';
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array( 'status' => self::STATUS_APPROVED ),
            array( 'id'     => $comment_id ),
            array( '%s' ),
            array( '%d' )
        );

        wp_send_json_success();
    }

    /** AJAX: retrieve approved comments for an image. */
    public function ajax_get_comments() {
        check_ajax_referer( 'eim_comments_nonce', 'nonce' );

        $post_id   = isset( $_GET['post_id'] )   ? absint( $_GET['post_id'] )   : 0;
        $img_index = isset( $_GET['img_index'] ) ? absint( $_GET['img_index'] ) : 0;

        $comments = self::get_comments( $post_id, $img_index );
        wp_send_json_success( $comments );
    }

    // -------------------------------------------------------------------------
    // Public helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve approved comments for a specific image.
     *
     * @param  int $post_id
     * @param  int $img_index
     * @return array
     */
    public static function get_comments( $post_id, $img_index ) {
        global $wpdb;

        $table = $wpdb->prefix . 'event_image_comments';
        $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT id, author, content, created_at FROM {$table} WHERE post_id = %d AND img_index = %d AND status = %s ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id,
                $img_index,
                self::STATUS_APPROVED
            ),
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : array();
    }

    // -------------------------------------------------------------------------
    // Database table creation
    // -------------------------------------------------------------------------

    /** Create the comments table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'event_image_comments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id    BIGINT UNSIGNED NOT NULL,
            img_index  INT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            author     VARCHAR(100) NOT NULL DEFAULT '',
            content    TEXT NOT NULL,
            status     VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_img (post_id, img_index),
            KEY status  (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

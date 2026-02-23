<?php
/**
 * Class EIM_Categories
 *
 * Provides a category / tag taxonomy system for classifying gallery images.
 *
 * Categories and tags are stored as comma-separated strings in the
 * `{prefix}event_image_metadata` table (shared with EIM_Search_Filter).
 *
 * Categories and tags can be set per-image from the admin gallery metabox.
 * A REST-like AJAX interface is also provided for client-side updates.
 */
class EIM_Categories {

    public function __construct() {
        add_action( 'wp_ajax_eim_set_image_meta',  array( $this, 'ajax_set_image_meta' ) );
        add_action( 'wp_ajax_eim_get_image_meta',  array( $this, 'ajax_get_image_meta' ) );
        add_action( 'wp_ajax_nopriv_eim_get_image_meta', array( $this, 'ajax_get_image_meta' ) );
        add_action( 'wp_ajax_eim_list_categories', array( $this, 'ajax_list_categories' ) );
        add_action( 'wp_ajax_nopriv_eim_list_categories', array( $this, 'ajax_list_categories' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: set the title, tags, and categories for a specific image. */
    public function ajax_set_image_meta() {
        check_ajax_referer( 'eim_categories_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $post_id    = isset( $_POST['post_id'] )   ? absint( $_POST['post_id'] )   : 0;
        $img_index  = isset( $_POST['img_index'] ) ? absint( $_POST['img_index'] ) : 0;
        $title      = isset( $_POST['title'] )     ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $tags_raw   = isset( $_POST['tags'] )      ? sanitize_text_field( wp_unslash( $_POST['tags'] ) )  : '';
        $cats_raw   = isset( $_POST['categories'] ) ? sanitize_text_field( wp_unslash( $_POST['categories'] ) ) : '';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid image.', 'event-image-manager' ) ) );
        }

        // Sanitize comma-separated lists.
        $tags = implode( ',', array_map( 'sanitize_text_field', array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) ) ) );
        $cats = implode( ',', array_map( 'sanitize_text_field', array_filter( array_map( 'trim', explode( ',', $cats_raw ) ) ) ) );

        self::upsert_metadata( $post_id, $img_index, $title, $tags, $cats );

        wp_send_json_success();
    }

    /** AJAX: get metadata for a specific image. */
    public function ajax_get_image_meta() {
        check_ajax_referer( 'eim_categories_nonce', 'nonce' );

        $post_id   = isset( $_GET['post_id'] )   ? absint( $_GET['post_id'] )   : 0;
        $img_index = isset( $_GET['img_index'] ) ? absint( $_GET['img_index'] ) : 0;

        $meta = self::get_metadata( $post_id, $img_index );
        wp_send_json_success( $meta );
    }

    /** AJAX: list all unique categories used across a given event. */
    public function ajax_list_categories() {
        check_ajax_referer( 'eim_categories_nonce', 'nonce' );

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        $cats    = self::get_all_categories( $post_id );
        wp_send_json_success( $cats );
    }

    // -------------------------------------------------------------------------
    // Public database helpers
    // -------------------------------------------------------------------------

    /**
     * Upsert image metadata (title, tags, categories).
     *
     * @param int    $post_id
     * @param int    $img_index
     * @param string $title
     * @param string $tags       Comma-separated.
     * @param string $categories Comma-separated.
     */
    public static function upsert_metadata( $post_id, $img_index, $title, $tags, $categories ) {
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
                array(
                    'title'      => $title,
                    'tags'       => $tags,
                    'categories' => $categories,
                ),
                array( 'post_id' => $post_id, 'img_index' => $img_index ),
                array( '%s', '%s', '%s' ),
                array( '%d', '%d' )
            );
        } else {
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $table,
                array(
                    'post_id'    => $post_id,
                    'img_index'  => $img_index,
                    'title'      => $title,
                    'tags'       => $tags,
                    'categories' => $categories,
                    'uploaded_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s' )
            );
        }
    }

    /**
     * Get metadata for a specific image.
     *
     * @param  int   $post_id
     * @param  int   $img_index
     * @return array
     */
    public static function get_metadata( $post_id, $img_index ) {
        global $wpdb;

        $table = $wpdb->prefix . 'event_image_metadata';
        $row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT title, tags, categories FROM {$table} WHERE post_id = %d AND img_index = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id, $img_index
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return array( 'title' => '', 'tags' => array(), 'categories' => array() );
        }

        return array(
            'title'      => $row['title'],
            'tags'       => $row['tags'] ? array_map( 'trim', explode( ',', $row['tags'] ) ) : array(),
            'categories' => $row['categories'] ? array_map( 'trim', explode( ',', $row['categories'] ) ) : array(),
        );
    }

    /**
     * Get all unique category values used by images of a given event.
     *
     * @param  int   $post_id
     * @return array Flat list of unique category strings.
     */
    public static function get_all_categories( $post_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'event_image_metadata';
        $rows  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT categories FROM {$table} WHERE post_id = %d AND categories != ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id
            )
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $all = array();
        foreach ( $rows as $row ) {
            foreach ( explode( ',', $row ) as $cat ) {
                $cat = trim( $cat );
                if ( $cat ) {
                    $all[] = $cat;
                }
            }
        }

        return array_values( array_unique( $all ) );
    }
}

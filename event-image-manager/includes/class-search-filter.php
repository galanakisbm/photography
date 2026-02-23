<?php
/**
 * Class EIM_Search_Filter
 *
 * Provides search and filter functionality for event galleries.
 *
 * Features:
 *  - Search by image title / filename stored in metadata.
 *  - Filter by date range (upload date stored in metadata).
 *  - Filter by category / tag (stored in the metadata table).
 *  - Sort by date (newest/oldest), name (A–Z / Z–A), popularity (favorites).
 *  - Multiple criteria can be combined.
 *
 * Image metadata is read from the `{prefix}event_image_metadata` table.
 * Gallery images can also be filtered purely in PHP from the post meta array.
 */
class EIM_Search_Filter {

    public function __construct() {
        add_action( 'wp_ajax_eim_search_images',        array( $this, 'ajax_search' ) );
        add_action( 'wp_ajax_nopriv_eim_search_images', array( $this, 'ajax_search' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handler
    // -------------------------------------------------------------------------

    /** AJAX handler: search & filter images for a given event. */
    public function ajax_search() {
        check_ajax_referer( 'eim_search_nonce', 'nonce' );

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid event.', 'event-image-manager' ) ) );
        }

        $args    = $this->parse_args();
        $results = $this->query( $post_id, $args );

        wp_send_json_success( $results );
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    /**
     * Query images for an event, applying search / filter / sort criteria.
     *
     * @param  int   $post_id Event post ID.
     * @param  array $args    Parsed query arguments.
     * @return array          Array of image data arrays with 'index' key added.
     */
    public function query( $post_id, array $args = array() ) {
        $images = get_post_meta( $post_id, Event_Admin::META_KEY, true );
        if ( ! is_array( $images ) ) {
            return array();
        }

        // Merge with database metadata.
        $meta_map = $this->get_metadata_map( $post_id );

        $results = array();
        foreach ( $images as $index => $img ) {
            $meta  = isset( $meta_map[ $index ] ) ? $meta_map[ $index ] : array();
            $entry = array_merge( $img, $meta, array( 'index' => $index ) );

            if ( ! $this->matches( $entry, $args ) ) {
                continue;
            }

            $results[] = $entry;
        }

        $this->sort( $results, $args );

        return $results;
    }

    // -------------------------------------------------------------------------
    // Argument parsing
    // -------------------------------------------------------------------------

    /**
     * Parse and sanitize query arguments from $_GET / $_POST.
     *
     * @return array
     */
    private function parse_args() {
        return array(
            'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
            'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
            'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
            'category'  => isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '',
            'tag'       => isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( $_GET['tag'] ) ) : '',
            'orderby'   => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date_desc',
        );
    }

    // -------------------------------------------------------------------------
    // Filtering
    // -------------------------------------------------------------------------

    /**
     * Determine whether a single image entry matches the given filter criteria.
     *
     * @param  array $entry Merged image + metadata array.
     * @param  array $args  Query arguments.
     * @return bool
     */
    private function matches( array $entry, array $args ) {
        // Search by title / filename.
        if ( '' !== $args['search'] ) {
            $haystack = strtolower(
                ( isset( $entry['title'] ) ? $entry['title'] : '' ) . ' ' .
                wp_basename( isset( $entry['original_path'] ) ? $entry['original_path'] : '' )
            );
            if ( false === strpos( $haystack, strtolower( $args['search'] ) ) ) {
                return false;
            }
        }

        // Filter by date range.
        if ( '' !== $args['date_from'] || '' !== $args['date_to'] ) {
            $upload_ts = isset( $entry['uploaded_at'] ) ? strtotime( $entry['uploaded_at'] ) : 0;
            if ( '' !== $args['date_from'] && $upload_ts < strtotime( $args['date_from'] ) ) {
                return false;
            }
            if ( '' !== $args['date_to'] && $upload_ts > strtotime( $args['date_to'] . ' 23:59:59' ) ) {
                return false;
            }
        }

        // Filter by category.
        if ( '' !== $args['category'] ) {
            $cats = isset( $entry['categories'] ) ? (array) $entry['categories'] : array();
            if ( ! in_array( $args['category'], $cats, true ) ) {
                return false;
            }
        }

        // Filter by tag.
        if ( '' !== $args['tag'] ) {
            $tags = isset( $entry['tags'] ) ? (array) $entry['tags'] : array();
            if ( ! in_array( $args['tag'], $tags, true ) ) {
                return false;
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    /**
     * Sort a results array by the specified orderby value.
     *
     * @param  array  &$results Results array (modified in place).
     * @param  array   $args    Query arguments.
     */
    private function sort( array &$results, array $args ) {
        $orderby = isset( $args['orderby'] ) ? $args['orderby'] : 'date_desc';

        usort( $results, function ( $a, $b ) use ( $orderby ) {
            switch ( $orderby ) {
                case 'date_asc':
                    $ta = isset( $a['uploaded_at'] ) ? strtotime( $a['uploaded_at'] ) : 0;
                    $tb = isset( $b['uploaded_at'] ) ? strtotime( $b['uploaded_at'] ) : 0;
                    return $ta - $tb;

                case 'name_asc':
                    return strcmp(
                        strtolower( isset( $a['title'] ) ? $a['title'] : wp_basename( $a['original_path'] ?? '' ) ),
                        strtolower( isset( $b['title'] ) ? $b['title'] : wp_basename( $b['original_path'] ?? '' ) )
                    );

                case 'name_desc':
                    return strcmp(
                        strtolower( isset( $b['title'] ) ? $b['title'] : wp_basename( $b['original_path'] ?? '' ) ),
                        strtolower( isset( $a['title'] ) ? $a['title'] : wp_basename( $a['original_path'] ?? '' ) )
                    );

                case 'popularity':
                    $fa = isset( $a['favorites_count'] ) ? (int) $a['favorites_count'] : 0;
                    $fb = isset( $b['favorites_count'] ) ? (int) $b['favorites_count'] : 0;
                    return $fb - $fa;

                case 'date_desc':
                default:
                    $ta = isset( $a['uploaded_at'] ) ? strtotime( $a['uploaded_at'] ) : 0;
                    $tb = isset( $b['uploaded_at'] ) ? strtotime( $b['uploaded_at'] ) : 0;
                    return $tb - $ta;
            }
        } );
    }

    // -------------------------------------------------------------------------
    // Database helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve image metadata rows for a given event, keyed by image index.
     *
     * @param  int   $post_id
     * @return array  [ index => metadata_array ]
     */
    private function get_metadata_map( $post_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'event_image_metadata';
        $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare( "SELECT id, post_id, img_index, title, tags, categories, favorites_count, uploaded_at FROM {$table} WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $map = array();
        foreach ( $rows as $row ) {
            $idx          = (int) $row['img_index'];
            $map[ $idx ]  = array(
                'title'          => $row['title'],
                'tags'           => $row['tags'] ? explode( ',', $row['tags'] ) : array(),
                'categories'     => $row['categories'] ? explode( ',', $row['categories'] ) : array(),
                'favorites_count' => (int) $row['favorites_count'],
                'uploaded_at'    => $row['uploaded_at'],
            );
        }

        return $map;
    }

    // -------------------------------------------------------------------------
    // Database table creation
    // -------------------------------------------------------------------------

    /** Create the image metadata table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'event_image_metadata';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id         BIGINT UNSIGNED NOT NULL,
            img_index       INT UNSIGNED NOT NULL,
            title           VARCHAR(255) NOT NULL DEFAULT '',
            tags            TEXT NOT NULL DEFAULT '',
            categories      TEXT NOT NULL DEFAULT '',
            favorites_count INT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_at     DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_img (post_id, img_index)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

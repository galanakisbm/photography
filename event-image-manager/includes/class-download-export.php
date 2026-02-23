<?php
/**
 * Class EIM_Download_Export
 *
 * Handles ZIP export of all event images and manages a download queue
 * for batch download operations.
 *
 * - `eim_export_zip`     : exports every image in an event as a single ZIP.
 * - `eim_queue_download` : queues a batch of images for deferred download.
 * - Queue state stored in `{prefix}eim_download_queue`.
 */
class EIM_Download_Export {

    public function __construct() {
        add_action( 'wp_ajax_eim_export_zip',          array( $this, 'ajax_export_zip' ) );
        add_action( 'wp_ajax_nopriv_eim_export_zip',   array( $this, 'ajax_export_zip' ) );
        add_action( 'wp_ajax_eim_queue_download',      array( $this, 'ajax_queue_download' ) );
        add_action( 'wp_ajax_nopriv_eim_queue_download', array( $this, 'ajax_queue_download' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: export all images in a post as a single ZIP file. */
    public function ajax_export_zip() {
        check_ajax_referer( 'eim_download_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            wp_send_json_error( array( 'message' => __( 'ZIP exports are not available on this server.', 'event-image-manager' ) ) );
        }

        $images = get_post_meta( $post_id, '_eim_gallery_images', true );
        if ( ! is_array( $images ) || empty( $images ) ) {
            wp_send_json_error( array( 'message' => __( 'No images found for this event.', 'event-image-manager' ) ) );
        }

        $use_original = current_user_can( 'manage_options' ) || current_user_can( 'photographer' );
        $zip_file     = wp_tempnam( 'eim-export' ) . '.zip';
        $zip          = new ZipArchive();

        if ( true !== $zip->open( $zip_file, ZipArchive::CREATE ) ) {
            wp_send_json_error( array( 'message' => __( 'Could not create ZIP archive.', 'event-image-manager' ) ) );
        }

        foreach ( $images as $img ) {
            $path = $use_original && ! empty( $img['original_path'] ) ? $img['original_path'] : ( $img['watermarked_path'] ?? '' );
            if ( $path && file_exists( $path ) ) {
                $zip->addFile( $path, wp_basename( $path ) );
            }
        }

        $zip->close();

        $zip_path_ref = &$zip_file;
        register_shutdown_function( function() use ( &$zip_path_ref ) {
            if ( $zip_path_ref && file_exists( $zip_path_ref ) ) {
                unlink( $zip_path_ref ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            }
        } );

        $event_title = sanitize_file_name( get_the_title( $post_id ) );
        $safe_title  = rawurlencode( str_replace( array( '"', "\r", "\n" ), '', $event_title ) );
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $safe_title . '-all-photos.zip"' );
        header( 'Content-Length: ' . filesize( $zip_file ) );
        header( 'Cache-Control: no-store' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        readfile( $zip_file );
        unlink( $zip_file );
        exit;
    }

    /** AJAX: queue a set of images for batch download. */
    public function ajax_queue_download() {
        check_ajax_referer( 'eim_download_nonce', 'nonce' );

        $post_id    = isset( $_POST['post_id'] )    ? absint( $_POST['post_id'] )    : 0;
        $raw_idxs   = isset( $_POST['indices'] )    ? sanitize_text_field( wp_unslash( $_POST['indices'] ) ) : '';

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
            'message'  => __( 'Images queued for download.', 'event-image-manager' ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the download queue table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_download_queue';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            post_id      BIGINT UNSIGNED NOT NULL,
            img_indices  TEXT NOT NULL,
            status       ENUM('pending','processing','complete','failed') NOT NULL DEFAULT 'pending',
            created_at   DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id  (user_id),
            KEY post_id  (post_id),
            KEY status   (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

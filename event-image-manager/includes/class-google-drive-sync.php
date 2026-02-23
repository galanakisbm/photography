<?php
/**
 * Class EIM_Google_Drive_Sync
 *
 * Backs up event gallery images to Google Drive.
 *
 * Options:
 *   - `eim_gdrive_client_id`     : Google OAuth 2.0 client ID.
 *   - `eim_gdrive_client_secret` : Google OAuth 2.0 client secret.
 *   - `eim_gdrive_access_token`  : JSON-encoded access token object.
 *
 * AJAX handlers:
 *   - `eim_gdrive_authorize`   : start OAuth flow (returns auth URL).
 *   - `eim_gdrive_sync_event`  : upload all images for an event.
 *   - `eim_gdrive_get_status`  : check sync status for an event.
 */
class EIM_Google_Drive_Sync {

    /** Google Drive Files API endpoint. */
    const FILES_API = 'https://www.googleapis.com/drive/v3/files';

    /** User meta key for per-event sync status. */
    const STATUS_META = 'eim_gdrive_sync_status';

    public function __construct() {
        add_action( 'wp_ajax_eim_gdrive_authorize',   array( $this, 'ajax_authorize' ) );
        add_action( 'wp_ajax_eim_gdrive_sync_event',  array( $this, 'ajax_sync_event' ) );
        add_action( 'wp_ajax_eim_gdrive_get_status',  array( $this, 'ajax_get_status' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: return the Google OAuth authorization URL. */
    public function ajax_authorize() {
        check_ajax_referer( 'eim_gdrive_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $client_id    = get_option( 'eim_gdrive_client_id', '' );
        $redirect_uri = admin_url( 'admin-ajax.php?action=eim_gdrive_oauth_callback' );

        if ( ! $client_id ) {
            wp_send_json_error( array( 'message' => __( 'Google Drive client ID is not configured.', 'event-image-manager' ) ) );
        }

        $auth_url = add_query_arg( array(
            'client_id'     => $client_id,
            'redirect_uri'  => rawurlencode( $redirect_uri ),
            'response_type' => 'code',
            'scope'         => rawurlencode( 'https://www.googleapis.com/auth/drive.file' ),
            'access_type'   => 'offline',
        ), 'https://accounts.google.com/o/oauth2/v2/auth' );

        wp_send_json_success( array( 'auth_url' => $auth_url ) );
    }

    /** AJAX: sync all images for a given event to Google Drive. */
    public function ajax_sync_event() {
        check_ajax_referer( 'eim_gdrive_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $result = $this->sync_event( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'synced'  => $result,
            'message' => __( 'Sync complete.', 'event-image-manager' ),
        ) );
    }

    /** AJAX: return the sync status for an event. */
    public function ajax_get_status() {
        check_ajax_referer( 'eim_gdrive_nonce', 'nonce' );

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        wp_send_json_success( array( 'status' => $this->get_sync_status( $post_id ) ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Upload all images for an event to a Google Drive folder.
     *
     * @param  int          $post_id Event post ID.
     * @return int|\WP_Error         Number of files uploaded, or WP_Error.
     */
    public function sync_event( $post_id ) {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $images = get_post_meta( $post_id, '_eim_gallery_images', true );
        if ( ! is_array( $images ) || empty( $images ) ) {
            return 0;
        }

        $folder_id = $this->ensure_folder( get_the_title( $post_id ), $access_token );
        if ( is_wp_error( $folder_id ) ) {
            return $folder_id;
        }

        $uploaded = 0;
        foreach ( $images as $img ) {
            $path = ! empty( $img['original_path'] ) ? $img['original_path'] : ( $img['watermarked_path'] ?? '' );
            if ( ! $path || ! file_exists( $path ) ) {
                continue;
            }
            $result = $this->upload_file( $path, $folder_id, $access_token );
            if ( ! is_wp_error( $result ) ) {
                $uploaded++;
            }
        }

        update_post_meta( $post_id, self::STATUS_META, array(
            'synced'     => $uploaded,
            'total'      => count( $images ),
            'synced_at'  => current_time( 'mysql' ),
        ) );

        return $uploaded;
    }

    /**
     * Return the sync status for an event.
     *
     * @param  int   $post_id Event post ID.
     * @return array          Status array or empty array.
     */
    public function get_sync_status( $post_id ) {
        $meta = get_post_meta( absint( $post_id ), self::STATUS_META, true );
        return is_array( $meta ) ? $meta : array();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return a valid access token, refreshing it if necessary.
     *
     * @return string|\WP_Error Access token string or WP_Error.
     */
    private function get_access_token() {
        $token_json = get_option( 'eim_gdrive_access_token', '' );
        if ( ! $token_json ) {
            return new WP_Error( 'not_authorized', __( 'Google Drive is not authorized.', 'event-image-manager' ) );
        }
        $token = json_decode( $token_json, true );
        return isset( $token['access_token'] ) ? $token['access_token'] : new WP_Error( 'bad_token', __( 'Invalid access token.', 'event-image-manager' ) );
    }

    /**
     * Create a Google Drive folder for the event (or find an existing one).
     *
     * @param  string       $name         Folder name.
     * @param  string       $access_token OAuth access token.
     * @return string|\WP_Error           Folder ID or WP_Error.
     */
    private function ensure_folder( $name, $access_token ) {
        $body = wp_json_encode( array(
            'name'     => sanitize_text_field( $name ),
            'mimeType' => 'application/vnd.google-apps.folder',
        ) );

        $response = wp_remote_post( self::FILES_API, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['id'] ) ? $data['id'] : new WP_Error( 'folder_error', __( 'Could not create Drive folder.', 'event-image-manager' ) );
    }

    /**
     * Upload a single file to Google Drive.
     *
     * @param  string       $path         Absolute file path.
     * @param  string       $folder_id    Parent folder ID.
     * @param  string       $access_token OAuth access token.
     * @return string|\WP_Error           File ID or WP_Error.
     */
    private function upload_file( $path, $folder_id, $access_token ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( $path );
        if ( false === $content ) {
            return new WP_Error( 'read_error', __( 'Could not read file.', 'event-image-manager' ) );
        }

        $metadata = wp_json_encode( array(
            'name'    => wp_basename( $path ),
            'parents' => array( $folder_id ),
        ) );

        $boundary = '---EIM_UPLOAD_BOUNDARY---';
        $body     = "--{$boundary}\r\n";
        $body    .= "Content-Type: application/json\r\n\r\n{$metadata}\r\n";
        $body    .= "--{$boundary}\r\n";
        $body    .= 'Content-Type: ' . ( wp_check_filetype( $path )['type'] ?: 'application/octet-stream' ) . "\r\n\r\n";
        $body    .= $content . "\r\n";
        $body    .= "--{$boundary}--";

        $response = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'multipart/related; boundary=' . $boundary,
            ),
            'body' => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['id'] ) ? $data['id'] : new WP_Error( 'upload_error', __( 'Upload failed.', 'event-image-manager' ) );
    }
}

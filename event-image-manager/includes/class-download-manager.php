<?php
/**
 * Class EIM_Download_Manager
 *
 * Controls how and whether gallery images can be downloaded.
 *
 * Download permission levels (stored as post meta '_eim_download_permission'):
 *  - none            : Downloads are disabled.
 *  - watermarked     : Users can download the watermarked version.
 *  - original        : Photographers / admins can download the original file.
 *  - selective       : Users choose which images to download (zip bundle).
 *
 * Rate limiting: maximum downloads per IP per hour stored in a transient.
 * Download events are recorded in the `{prefix}event_download_logs` table.
 */
class EIM_Download_Manager {

    /** Post meta key for per-event download permission level. */
    const META_KEY = '_eim_download_permission';

    /** Transient prefix for rate-limit counters (per IP). */
    const RL_PREFIX = 'eim_dl_rl_';

    /** Maximum downloads allowed per IP per hour. */
    const RL_LIMIT = 50;

    /** Rate-limit window in seconds (1 hour). */
    const RL_WINDOW = 3600;

    public function __construct() {
        add_action( 'wp_ajax_eim_download_image',        array( $this, 'ajax_download_image' ) );
        add_action( 'wp_ajax_nopriv_eim_download_image', array( $this, 'ajax_download_image' ) );
        add_action( 'wp_ajax_eim_download_zip',          array( $this, 'ajax_download_zip' ) );
        add_action( 'wp_ajax_nopriv_eim_download_zip',   array( $this, 'ajax_download_zip' ) );
        add_action( 'add_meta_boxes',   array( $this, 'add_meta_box' ) );
        add_action( 'save_post_event',  array( $this, 'save_meta_box' ) );
    }

    // -------------------------------------------------------------------------
    // Meta box
    // -------------------------------------------------------------------------

    /** Register the download-permission meta box on the event edit screen. */
    public function add_meta_box() {
        add_meta_box(
            'eim_download_permission',
            __( 'Download Permissions', 'event-image-manager' ),
            array( $this, 'render_meta_box' ),
            'event',
            'side',
            'default'
        );
    }

    /** Render the download-permission meta box. */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'eim_save_download_permission', 'eim_download_permission_nonce' );
        $current = get_post_meta( $post->ID, self::META_KEY, true );
        if ( ! $current ) {
            $current = 'none';
        }
        $options = array(
            'none'        => __( 'No Download', 'event-image-manager' ),
            'watermarked' => __( 'Download with Watermark', 'event-image-manager' ),
            'original'    => __( 'Download without Watermark (Photographers)', 'event-image-manager' ),
            'selective'   => __( 'Selective Download (user chooses)', 'event-image-manager' ),
        );
        ?>
        <p>
            <label for="eim_download_permission"><?php esc_html_e( 'Permission level:', 'event-image-manager' ); ?></label><br>
            <select id="eim_download_permission" name="eim_download_permission" style="width:100%;margin-top:4px;">
                <?php foreach ( $options as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    /** Save the download permission level. */
    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['eim_download_permission_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['eim_download_permission_nonce'] ) ),
                'eim_save_download_permission'
            )
        ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $allowed = array( 'none', 'watermarked', 'original', 'selective' );
        $value   = isset( $_POST['eim_download_permission'] )
            ? sanitize_text_field( wp_unslash( $_POST['eim_download_permission'] ) )
            : 'none';

        if ( ! in_array( $value, $allowed, true ) ) {
            $value = 'none';
        }

        update_post_meta( $post_id, self::META_KEY, $value );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: download a single image. */
    public function ajax_download_image() {
        check_ajax_referer( 'eim_download_nonce', 'nonce' );

        $post_id   = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        $img_index = isset( $_GET['img'] )     ? absint( $_GET['img'] )     : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        if ( ! $this->check_rate_limit() ) {
            status_header( 429 );
            wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'event-image-manager' ) ) );
        }

        $permission = $this->get_effective_permission( $post_id );
        if ( 'none' === $permission ) {
            status_header( 403 );
            wp_send_json_error( array( 'message' => __( 'Downloads are not allowed for this gallery.', 'event-image-manager' ) ) );
        }

        $images = get_post_meta( $post_id, Event_Admin::META_KEY, true );
        if ( ! is_array( $images ) || ! isset( $images[ $img_index ] ) ) {
            status_header( 404 );
            wp_send_json_error( array( 'message' => __( 'Image not found.', 'event-image-manager' ) ) );
        }

        $img = $images[ $img_index ];
        if ( 'original' === $permission && EIM_User_Roles::current_user_can_download() ) {
            $path = $img['original_path'];
        } else {
            $path = $img['watermarked_path'];
        }

        if ( ! file_exists( $path ) ) {
            status_header( 404 );
            wp_send_json_error( array( 'message' => __( 'File not found.', 'event-image-manager' ) ) );
        }

        $this->log_download( $post_id, $img_index );
        $this->serve_file_download( $path );
        exit;
    }

    /** AJAX: download a zip of selected images. */
    public function ajax_download_zip() {
        check_ajax_referer( 'eim_download_nonce', 'nonce' );

        $post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $raw_idxs = isset( $_POST['indices'] ) ? wp_unslash( $_POST['indices'] ) : '';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $permission = $this->get_effective_permission( $post_id );
        if ( 'none' === $permission ) {
            status_header( 403 );
            wp_send_json_error( array( 'message' => __( 'Downloads are not allowed for this gallery.', 'event-image-manager' ) ) );
        }

        if ( ! $this->check_rate_limit() ) {
            status_header( 429 );
            wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'event-image-manager' ) ) );
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            wp_send_json_error( array( 'message' => __( 'ZIP downloads are not available on this server.', 'event-image-manager' ) ) );
        }

        $indices = array_filter( array_map( 'absint', explode( ',', $raw_idxs ) ) );
        $images  = get_post_meta( $post_id, Event_Admin::META_KEY, true );

        if ( ! is_array( $images ) || empty( $indices ) ) {
            status_header( 400 );
            wp_send_json_error( array( 'message' => __( 'No images selected.', 'event-image-manager' ) ) );
        }

        $use_original = ( 'original' === $permission && EIM_User_Roles::current_user_can_download() );

        $zip_file = wp_tempnam( 'eim-gallery' ) . '.zip';
        $zip      = new ZipArchive();
        if ( $zip->open( $zip_file, ZipArchive::CREATE ) !== true ) {
            wp_send_json_error( array( 'message' => __( 'Could not create ZIP archive.', 'event-image-manager' ) ) );
        }

        foreach ( $indices as $idx ) {
            if ( ! isset( $images[ $idx ] ) ) {
                continue;
            }
            $path = $use_original ? $images[ $idx ]['original_path'] : $images[ $idx ]['watermarked_path'];
            if ( file_exists( $path ) ) {
                $zip->addFile( $path, wp_basename( $path ) );
                $this->log_download( $post_id, $idx );
            }
        }

        $zip->close();

        $event_title = sanitize_file_name( get_the_title( $post_id ) );
        $safe_title  = str_replace( array( '"', "\r", "\n" ), '', $event_title );
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $safe_title . '-photos.zip"' );
        header( 'Content-Length: ' . filesize( $zip_file ) );
        header( 'Cache-Control: no-store' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        readfile( $zip_file );
        unlink( $zip_file );
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the effective download permission for a post, respecting the
     * user's role (admins/photographers can always access originals).
     *
     * @param  int    $post_id
     * @return string Permission level string.
     */
    public function get_effective_permission( $post_id ) {
        $permission = get_post_meta( $post_id, self::META_KEY, true );
        if ( ! $permission ) {
            $permission = 'none';
        }

        // Admins can always download originals regardless of per-event setting.
        if ( current_user_can( 'manage_options' ) ) {
            return 'original';
        }

        return $permission;
    }

    /**
     * Stream a file to the browser as a download.
     *
     * @param string $path Absolute path to the file.
     */
    private function serve_file_download( $path ) {
        $mime     = wp_check_filetype( $path )['type'];
        $filename = str_replace( array( '"', "\r", "\n" ), '', wp_basename( $path ) );

        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        header( 'Cache-Control: no-store' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        readfile( $path );
    }

    /**
     * Simple IP-based rate limiting using WordPress transients.
     *
     * @return bool True if the request is within the rate limit.
     */
    private function check_rate_limit() {
        $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $key = self::RL_PREFIX . md5( $ip );
        $count = (int) get_transient( $key );

        if ( $count >= self::RL_LIMIT ) {
            return false;
        }

        if ( 0 === $count ) {
            set_transient( $key, 1, self::RL_WINDOW );
        } else {
            set_transient( $key, $count + 1, self::RL_WINDOW );
        }

        return true;
    }

    /**
     * Log a download event to the custom database table.
     *
     * @param int $post_id   Event post ID.
     * @param int $img_index Image index within the gallery.
     */
    private function log_download( $post_id, $img_index ) {
        global $wpdb;

        $table = $wpdb->prefix . 'event_download_logs';
        $ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'post_id'    => $post_id,
                'img_index'  => $img_index,
                'user_id'    => get_current_user_id(),
                'ip_address' => $ip,
                'downloaded_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s' )
        );
    }

    // -------------------------------------------------------------------------
    // Database table creation
    // -------------------------------------------------------------------------

    /** Create the download logs table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table      = $wpdb->prefix . 'event_download_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id       BIGINT UNSIGNED NOT NULL,
            img_index     INT UNSIGNED NOT NULL,
            user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_address    VARCHAR(45) NOT NULL DEFAULT '',
            downloaded_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id  (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

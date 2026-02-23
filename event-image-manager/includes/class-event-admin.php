<?php
/**
 * Class Event_Admin
 *
 * Adds a metabox to the event editor for uploading, managing, and deleting
 * event images.  Uploaded images are watermarked via Image_Manager before
 * being stored.
 */
class Event_Admin {

    /** Meta key used to store the list of watermarked image paths. */
    const META_KEY = '_eim_gallery_images';

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_event',  array( $this, 'save_meta_box' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_eim_delete_image', array( $this, 'ajax_delete_image' ) );
    }

    /** Register the gallery metabox on the event edit screen. */
    public function add_meta_box() {
        add_meta_box(
            'eim_gallery',
            __( 'Event Gallery', 'event-image-manager' ),
            array( $this, 'render_meta_box' ),
            'event',
            'normal',
            'default'
        );
    }

    /** Enqueue media uploader scripts and plugin admin JS on event screens. */
    public function enqueue_scripts( $hook ) {
        global $post;
        if ( ( 'post.php' === $hook || 'post-new.php' === $hook )
            && isset( $post ) && 'event' === $post->post_type ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'eim-admin',
                plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/event-admin.js',
                array( 'jquery' ),
                '1.0.0',
                true
            );
        }
    }

    /** Render the metabox HTML. */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'eim_save_gallery', 'eim_gallery_nonce' );
        $images = get_post_meta( $post->ID, self::META_KEY, true );
        if ( ! is_array( $images ) ) {
            $images = array();
        }
        ?>
        <div id="eim-gallery-wrap">
            <div id="eim-image-list">
                <?php foreach ( $images as $index => $img ) : ?>
                    <div class="eim-image-item" data-index="<?php echo esc_attr( $index ); ?>">
                        <img src="<?php echo esc_url( $img['watermarked_url'] ); ?>" style="max-width:120px;max-height:90px;">
                        <input type="hidden" name="eim_images[<?php echo esc_attr( $index ); ?>][original_path]"
                               value="<?php echo esc_attr( $img['original_path'] ); ?>">
                        <input type="hidden" name="eim_images[<?php echo esc_attr( $index ); ?>][watermarked_path]"
                               value="<?php echo esc_attr( $img['watermarked_path'] ); ?>">
                        <input type="hidden" name="eim_images[<?php echo esc_attr( $index ); ?>][watermarked_url]"
                               value="<?php echo esc_attr( $img['watermarked_url'] ); ?>">
                        <button type="button" class="button eim-remove-image"
                                data-path="<?php echo esc_attr( $img['watermarked_path'] ); ?>"
                                data-original="<?php echo esc_attr( $img['original_path'] ); ?>">
                            <?php esc_html_e( 'Remove', 'event-image-manager' ); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <p>
                <button type="button" id="eim-add-images" class="button button-primary">
                    <?php esc_html_e( 'Add Images', 'event-image-manager' ); ?>
                </button>
            </p>

            <!-- Hidden field accumulates newly selected attachment IDs -->
            <input type="hidden" id="eim-new-attachment-ids" name="eim_new_attachment_ids" value="">
        </div>
        <?php
    }

    /**
     * Save metabox data.
     *
     * Processes any newly uploaded attachment IDs, generates watermarked
     * copies and stores the paths/URLs in post meta.
     */
    public function save_meta_box( $post_id ) {
        // Nonce & permissions check.
        if ( ! isset( $_POST['eim_gallery_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eim_gallery_nonce'] ) ), 'eim_save_gallery' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Retain existing images that were not removed in the UI.
        $existing = array();
        if ( isset( $_POST['eim_images'] ) && is_array( $_POST['eim_images'] ) ) {
            foreach ( $_POST['eim_images'] as $img ) {
                $existing[] = array(
                    'original_path'   => sanitize_text_field( wp_unslash( $img['original_path'] ) ),
                    'watermarked_path' => sanitize_text_field( wp_unslash( $img['watermarked_path'] ) ),
                    'watermarked_url'  => esc_url_raw( wp_unslash( $img['watermarked_url'] ) ),
                );
            }
        }

        // Process newly added attachment IDs.
        $new_ids_raw = isset( $_POST['eim_new_attachment_ids'] )
            ? sanitize_text_field( wp_unslash( $_POST['eim_new_attachment_ids'] ) )
            : '';

        if ( '' !== $new_ids_raw ) {
            $ids = array_filter( array_map( 'absint', explode( ',', $new_ids_raw ) ) );
            $manager = new Image_Manager();
            foreach ( $ids as $attachment_id ) {
                $original_path = get_attached_file( $attachment_id );
                if ( ! $original_path || ! file_exists( $original_path ) ) {
                    continue;
                }
                $result = $manager->create_watermarked( $original_path );
                if ( $result ) {
                    $existing[] = $result;
                }
            }
        }

        update_post_meta( $post_id, self::META_KEY, $existing );
    }

    /**
     * AJAX handler: delete a single image (both watermarked and original files).
     */
    public function ajax_delete_image() {
        check_ajax_referer( 'eim_delete_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $watermarked_path = isset( $_POST['watermarked_path'] )
            ? sanitize_text_field( wp_unslash( $_POST['watermarked_path'] ) )
            : '';
        $original_path = isset( $_POST['original_path'] )
            ? sanitize_text_field( wp_unslash( $_POST['original_path'] ) )
            : '';

        $upload_dir = wp_upload_dir();
        $base       = trailingslashit( $upload_dir['basedir'] );

        // Only allow deletion of files inside the uploads directory.
        $real_base = realpath( $base );
        foreach ( array( $watermarked_path, $original_path ) as $path ) {
            if ( '' === $path ) {
                continue;
            }
            $real_path = realpath( $path );
            if ( false !== $real_path && false !== $real_base
                && strpos( $real_path, $real_base ) === 0
                && file_exists( $real_path ) ) {
                wp_delete_file( $real_path );
            }
        }

        wp_send_json_success();
    }
}

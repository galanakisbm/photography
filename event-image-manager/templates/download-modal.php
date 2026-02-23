<?php
/**
 * Template: Download Modal
 *
 * Shown when the user clicks the download button on a gallery image.
 * The modal allows single-image or multi-image (selective) downloads.
 *
 * This template expects to be included from gallery-with-filters.php
 * where $post_id and $permission are already defined.
 */
?>
<div id="eim-download-modal" class="eim-modal" role="dialog" aria-modal="true" aria-labelledby="eim-download-modal-title" style="display:none;">
    <div class="eim-modal-overlay"></div>
    <div class="eim-modal-content">
        <button type="button" class="eim-modal-close" aria-label="<?php esc_attr_e( 'Close', 'event-image-manager' ); ?>">&times;</button>

        <h2 id="eim-download-modal-title"><?php esc_html_e( 'Download Photo', 'event-image-manager' ); ?></h2>

        <p><?php esc_html_e( 'Choose how you would like to download this photo.', 'event-image-manager' ); ?></p>

        <?php
        $permission = get_post_meta( $post_id ?? 0, EIM_Download_Manager::META_KEY, true ) ?: 'none';
        ?>

        <div class="eim-download-options">
            <?php if ( in_array( $permission, array( 'watermarked', 'selective', 'original' ), true ) ) : ?>
                <button type="button" class="button button-primary eim-do-download" data-type="watermarked">
                    <?php esc_html_e( 'Download with Watermark', 'event-image-manager' ); ?>
                </button>
            <?php endif; ?>

            <?php if ( 'original' === $permission && EIM_User_Roles::current_user_can_download() ) : ?>
                <button type="button" class="button eim-do-download" data-type="original">
                    <?php esc_html_e( 'Download Original (no watermark)', 'event-image-manager' ); ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- Hidden fields updated by JS when the download button is clicked. -->
        <input type="hidden" id="eim-download-post-id"   value="">
        <input type="hidden" id="eim-download-img-index" value="">

        <p class="eim-download-status" aria-live="polite"></p>
    </div>
</div>

<?php
/**
 * Template: Advanced Download Modal
 *
 * Provides advanced download options including image selection for ZIP,
 * download queue, progress tracking, watermark toggle, and share buttons.
 *
 * Expects $post_id to be defined by including code.
 */

$post_id    = isset( $post_id ) ? absint( $post_id ) : 0;
$permission = get_post_meta( $post_id, EIM_Download_Manager::META_KEY, true ) ?: 'none';
$images     = get_post_meta( $post_id, Event_Admin::META_KEY, true );
$images     = is_array( $images ) ? $images : array();
$can_orig   = EIM_User_Roles::current_user_can_download();
?>
<div id="eim-download-advanced-modal" class="eim-modal" role="dialog" aria-modal="true"
     aria-labelledby="eim-adv-modal-title" style="display:none;">
    <div class="eim-modal-overlay"></div>
    <div class="eim-modal-content eim-modal-content--wide">
        <button type="button" class="eim-modal-close"
                aria-label="<?php esc_attr_e( 'Close', 'event-image-manager' ); ?>">&times;</button>

        <h2 id="eim-adv-modal-title"><?php esc_html_e( 'Advanced Download Options', 'event-image-manager' ); ?></h2>

        <?php if ( 'none' !== $permission && ! empty( $images ) ) : ?>

            <?php if ( in_array( $permission, array( 'selective', 'original' ), true ) ) : ?>
            <!-- Image selection grid -->
            <div class="eim-adv-select-bar">
                <label>
                    <input type="checkbox" id="eim-adv-select-all">
                    <?php esc_html_e( 'Select All', 'event-image-manager' ); ?>
                </label>
                <span id="eim-adv-selected-count"><?php esc_html_e( '0 selected', 'event-image-manager' ); ?></span>
            </div>

            <div class="eim-adv-image-grid">
                <?php foreach ( $images as $idx => $img ) :
                    $thumb = is_array( $img ) ? ( $img['url'] ?? '' ) : $img;
                ?>
                <label class="eim-adv-image-item">
                    <input type="checkbox" class="eim-adv-img-check" value="<?php echo esc_attr( $idx ); ?>">
                    <img src="<?php echo esc_url( $thumb ); ?>"
                         alt="<?php echo esc_attr( sprintf( __( 'Image %d', 'event-image-manager' ), $idx + 1 ) ); ?>"
                         loading="lazy">
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Watermark toggle -->
            <?php if ( 'original' === $permission && $can_orig ) : ?>
            <div class="eim-adv-watermark-toggle">
                <label>
                    <input type="checkbox" id="eim-adv-no-watermark" value="1">
                    <?php esc_html_e( 'Download originals (no watermark)', 'event-image-manager' ); ?>
                </label>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="eim-adv-actions">
                <button type="button" id="eim-adv-download-zip" class="button button-primary"
                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                    <?php esc_html_e( 'Download as ZIP', 'event-image-manager' ); ?>
                </button>
                <button type="button" id="eim-adv-add-to-queue" class="button"
                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                    <?php esc_html_e( 'Add to Download Queue', 'event-image-manager' ); ?>
                </button>
            </div>

            <!-- Progress bar -->
            <div id="eim-adv-progress-wrap" class="eim-progress-wrap" style="display:none;" aria-live="polite">
                <div class="eim-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                    <div class="eim-progress-fill" style="width:0%"></div>
                </div>
                <p class="eim-progress-label"></p>
            </div>

            <!-- Share buttons -->
            <div class="eim-adv-share">
                <span><?php esc_html_e( 'Share:', 'event-image-manager' ); ?></span>
                <a href="#" class="eim-share-btn eim-share-btn--facebook"
                   data-network="facebook"
                   aria-label="<?php esc_attr_e( 'Share on Facebook', 'event-image-manager' ); ?>">Facebook</a>
                <a href="#" class="eim-share-btn eim-share-btn--twitter"
                   data-network="twitter"
                   aria-label="<?php esc_attr_e( 'Share on Twitter', 'event-image-manager' ); ?>">Twitter</a>
                <a href="#" class="eim-share-btn eim-share-btn--copy"
                   data-network="copy"
                   aria-label="<?php esc_attr_e( 'Copy link', 'event-image-manager' ); ?>"><?php esc_html_e( 'Copy Link', 'event-image-manager' ); ?></a>
            </div>

        <?php else : ?>
            <p><?php esc_html_e( 'Downloads are not available for this event.', 'event-image-manager' ); ?></p>
        <?php endif; ?>

        <p class="eim-adv-status" aria-live="polite"></p>
    </div>
</div>

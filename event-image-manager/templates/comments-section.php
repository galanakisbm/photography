<?php
/**
 * Template: Comments Section Modal
 *
 * Rendered as a modal overlay.  Opened when the user clicks the 💬 button
 * on a gallery image.  Loads and posts comments via AJAX.
 */
?>
<div id="eim-comments-modal" class="eim-modal" role="dialog" aria-modal="true" aria-labelledby="eim-comments-modal-title" style="display:none;">
    <div class="eim-modal-overlay"></div>
    <div class="eim-modal-content eim-comments-modal-content">
        <button type="button" class="eim-modal-close" aria-label="<?php esc_attr_e( 'Close', 'event-image-manager' ); ?>">&times;</button>

        <h2 id="eim-comments-modal-title"><?php esc_html_e( 'Image Comments', 'event-image-manager' ); ?></h2>

        <!-- Existing comments list -->
        <div id="eim-comments-list" class="eim-comments-list">
            <p class="eim-comments-loading"><?php esc_html_e( 'Loading comments…', 'event-image-manager' ); ?></p>
        </div>

        <!-- New comment form -->
        <form id="eim-comment-form" class="eim-comment-form" novalidate>
            <?php wp_nonce_field( 'eim_comments_nonce', 'eim_comments_nonce' ); ?>
            <input type="hidden" id="eim-comment-post-id"   name="post_id"   value="">
            <input type="hidden" id="eim-comment-img-index" name="img_index" value="">

            <?php if ( ! is_user_logged_in() ) : ?>
                <p>
                    <label for="eim-comment-author"><?php esc_html_e( 'Your name:', 'event-image-manager' ); ?></label>
                    <input type="text" id="eim-comment-author" name="author" required>
                </p>
            <?php endif; ?>

            <p>
                <label for="eim-comment-content"><?php esc_html_e( 'Comment:', 'event-image-manager' ); ?></label>
                <textarea id="eim-comment-content" name="content" rows="3" required></textarea>
            </p>

            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Post Comment', 'event-image-manager' ); ?>
            </button>

            <p class="eim-comment-status" aria-live="polite" style="display:none;"></p>
        </form>
    </div>
</div>

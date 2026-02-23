<?php
/**
 * Template: Direct Messaging Interface
 *
 * Standalone template for the messaging admin page.
 * Included in admin pages.
 */
?>
<div class="eim-messaging-wrap" role="main">
    <div class="eim-messaging-layout">

        <!-- Conversation list panel -->
        <aside class="eim-conversation-list" aria-label="<?php esc_attr_e( 'Conversations', 'event-image-manager' ); ?>">
            <div class="eim-conversation-list-header">
                <h2><?php esc_html_e( 'Messages', 'event-image-manager' ); ?></h2>
                <button type="button" id="eim-new-message-btn" class="button button-primary button-small"
                        aria-haspopup="dialog">
                    <?php esc_html_e( 'New Message', 'event-image-manager' ); ?>
                </button>
            </div>

            <ul id="eim-conversations" class="eim-conversations" aria-live="polite" aria-label="<?php esc_attr_e( 'Conversation list', 'event-image-manager' ); ?>">
                <li class="eim-conversations-loading"><?php esc_html_e( 'Loading conversations…', 'event-image-manager' ); ?></li>
            </ul>
        </aside>

        <!-- Message thread panel -->
        <section class="eim-message-thread" aria-label="<?php esc_attr_e( 'Message thread', 'event-image-manager' ); ?>">
            <div id="eim-thread-header" class="eim-thread-header">
                <h3 id="eim-thread-title"><?php esc_html_e( 'Select a conversation', 'event-image-manager' ); ?></h3>
            </div>

            <div id="eim-messages" class="eim-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Messages', 'event-image-manager' ); ?>">
                <p class="eim-messages-placeholder"><?php esc_html_e( 'Select a conversation to view messages.', 'event-image-manager' ); ?></p>
            </div>

            <!-- Reply form -->
            <form id="eim-reply-form" class="eim-reply-form" style="display:none;" novalidate>
                <?php wp_nonce_field( 'eim_messaging_nonce', 'eim_messaging_nonce' ); ?>
                <input type="hidden" id="eim-reply-thread-id" name="thread_id" value="">
                <label for="eim-reply-text" class="screen-reader-text">
                    <?php esc_html_e( 'Your message', 'event-image-manager' ); ?>
                </label>
                <textarea id="eim-reply-text" name="message" class="eim-reply-textarea"
                          placeholder="<?php esc_attr_e( 'Type a message…', 'event-image-manager' ); ?>"
                          rows="3" required></textarea>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Send', 'event-image-manager' ); ?>
                </button>
                <p class="eim-reply-status" aria-live="polite"></p>
            </form>
        </section>
    </div>

    <!-- New message compose dialog -->
    <div id="eim-compose-modal" class="eim-modal" role="dialog" aria-modal="true"
         aria-labelledby="eim-compose-title" style="display:none;">
        <div class="eim-modal-overlay"></div>
        <div class="eim-modal-content">
            <button type="button" class="eim-modal-close"
                    aria-label="<?php esc_attr_e( 'Close', 'event-image-manager' ); ?>">&times;</button>

            <h2 id="eim-compose-title"><?php esc_html_e( 'New Message', 'event-image-manager' ); ?></h2>

            <form id="eim-compose-form" novalidate>
                <?php wp_nonce_field( 'eim_messaging_nonce', 'eim_compose_nonce' ); ?>

                <p>
                    <label for="eim-compose-recipient">
                        <?php esc_html_e( 'To:', 'event-image-manager' ); ?>
                    </label>
                    <input type="text" id="eim-compose-recipient" name="recipient"
                           class="regular-text"
                           placeholder="<?php esc_attr_e( 'Username or email', 'event-image-manager' ); ?>"
                           required autocomplete="off">
                </p>

                <p>
                    <label for="eim-compose-subject">
                        <?php esc_html_e( 'Subject:', 'event-image-manager' ); ?>
                    </label>
                    <input type="text" id="eim-compose-subject" name="subject" class="regular-text"
                           placeholder="<?php esc_attr_e( 'Optional subject', 'event-image-manager' ); ?>">
                </p>

                <p>
                    <label for="eim-compose-body">
                        <?php esc_html_e( 'Message:', 'event-image-manager' ); ?>
                    </label>
                    <textarea id="eim-compose-body" name="message" class="large-text" rows="5" required></textarea>
                </p>

                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Send Message', 'event-image-manager' ); ?>
                </button>

                <p class="eim-compose-status" aria-live="polite"></p>
            </form>
        </div>
    </div>
</div>

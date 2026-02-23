<?php
/**
 * Template: Notification Center
 *
 * Page template for the notifications center.
 * Displays All, Unread, and Messages tabs with notification items.
 */
?>
<div class="eim-notification-center" role="main">
    <div class="eim-notification-header">
        <h1><?php esc_html_e( 'Notifications', 'event-image-manager' ); ?></h1>
        <button type="button" id="eim-mark-all-read" class="button">
            <?php esc_html_e( 'Mark All as Read', 'event-image-manager' ); ?>
        </button>
    </div>

    <!-- Tabs -->
    <nav class="eim-notification-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Notification filters', 'event-image-manager' ); ?>">
        <button role="tab" aria-selected="true" aria-controls="eim-tab-all"
                id="eim-tabbtn-all" class="eim-tab-btn eim-tab-btn--active" data-tab="all">
            <?php esc_html_e( 'All', 'event-image-manager' ); ?>
            <span class="eim-tab-count" id="eim-count-all">0</span>
        </button>
        <button role="tab" aria-selected="false" aria-controls="eim-tab-unread"
                id="eim-tabbtn-unread" class="eim-tab-btn" data-tab="unread">
            <?php esc_html_e( 'Unread', 'event-image-manager' ); ?>
            <span class="eim-tab-count" id="eim-count-unread">0</span>
        </button>
        <button role="tab" aria-selected="false" aria-controls="eim-tab-messages"
                id="eim-tabbtn-messages" class="eim-tab-btn" data-tab="messages">
            <?php esc_html_e( 'Messages', 'event-image-manager' ); ?>
            <span class="eim-tab-count" id="eim-count-messages">0</span>
        </button>
    </nav>

    <!-- Tab panels -->
    <div id="eim-tab-all" role="tabpanel" aria-labelledby="eim-tabbtn-all" class="eim-tab-panel eim-tab-panel--active">
        <ul id="eim-notifications-all" class="eim-notification-list" aria-live="polite">
            <li class="eim-notification-loading"><?php esc_html_e( 'Loading notifications…', 'event-image-manager' ); ?></li>
        </ul>
    </div>

    <div id="eim-tab-unread" role="tabpanel" aria-labelledby="eim-tabbtn-unread"
         class="eim-tab-panel" style="display:none;">
        <ul id="eim-notifications-unread" class="eim-notification-list" aria-live="polite">
            <li class="eim-notification-loading"><?php esc_html_e( 'Loading notifications…', 'event-image-manager' ); ?></li>
        </ul>
    </div>

    <div id="eim-tab-messages" role="tabpanel" aria-labelledby="eim-tabbtn-messages"
         class="eim-tab-panel" style="display:none;">
        <ul id="eim-notifications-messages" class="eim-notification-list" aria-live="polite">
            <li class="eim-notification-loading"><?php esc_html_e( 'Loading notifications…', 'event-image-manager' ); ?></li>
        </ul>
    </div>

    <!-- Notification item template (rendered by JS) -->
    <template id="eim-notification-tmpl">
        <li class="eim-notification-item" data-id="">
            <span class="eim-notification-icon" aria-hidden="true"></span>
            <div class="eim-notification-body">
                <p class="eim-notification-message"></p>
                <time class="eim-notification-time"></time>
            </div>
            <button type="button" class="eim-notification-mark-read button button-small"
                    aria-label="<?php esc_attr_e( 'Mark as read', 'event-image-manager' ); ?>">
                <?php esc_html_e( 'Mark Read', 'event-image-manager' ); ?>
            </button>
        </li>
    </template>

    <!-- Empty state template -->
    <template id="eim-notification-empty-tmpl">
        <li class="eim-notification-empty">
            <span class="eim-notification-empty-icon" aria-hidden="true">&#x1F514;</span>
            <p><?php esc_html_e( 'No notifications here.', 'event-image-manager' ); ?></p>
        </li>
    </template>
</div>

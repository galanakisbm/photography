<?php
/**
 * Class EIM_Notification_Triggers
 *
 * Listens for plugin events and triggers the appropriate email and
 * in-app notifications respecting each user's preferences.
 *
 * Hooks:
 *   - `save_post_event`  : fires when a new event post is saved/published.
 *   - `eim_images_added` : fires when new images are added to an event.
 */
class EIM_Notification_Triggers {

    public function __construct() {
        add_action( 'save_post_event', array( $this, 'on_new_event' ), 10, 3 );
        add_action( 'eim_images_added', array( $this, 'on_images_added' ), 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Hook callbacks
    // -------------------------------------------------------------------------

    /**
     * Triggered when an event post is saved.
     * Sends notifications for new (auto-draft → publish) transitions only.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     */
    public function on_new_event( $post_id, $post, $update ) {
        if ( $update ) {
            return;
        }

        if ( 'publish' !== $post->post_status ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $this->dispatch_new_event_notifications( $post_id );
    }

    /**
     * Triggered when images are added to an event.
     *
     * @param int $post_id Event post ID.
     * @param int $count   Number of images added.
     */
    public function on_images_added( $post_id, $count ) {
        $this->dispatch_new_images_notifications( $post_id, $count );
    }

    // -------------------------------------------------------------------------
    // Dispatchers
    // -------------------------------------------------------------------------

    /**
     * Dispatch notifications for a new event to photographers/admins.
     *
     * @param int $post_id Event post ID.
     */
    private function dispatch_new_event_notifications( $post_id ) {
        $email_notifs = new EIM_Email_Notifications();
        $in_app       = new EIM_In_App_Notifications();
        $prefs_mgr    = new EIM_Notification_Preferences();

        $recipients = get_users( array( 'role__in' => array( 'administrator', 'photographer' ) ) );
        foreach ( $recipients as $user ) {
            $prefs = $prefs_mgr->get_user_prefs( $user->ID );

            if ( ! empty( $prefs['in_app_events'] ) ) {
                /* translators: %s: event title */
                $message = sprintf( __( 'New event created: %s', 'event-image-manager' ), get_the_title( $post_id ) );
                $in_app->add_notification( $user->ID, 'new_event', $message, get_permalink( $post_id ) );
            }
        }

        // Bulk email is handled by EIM_Email_Notifications.
        $email_notifs->send_new_event_notification( $post_id );
    }

    /**
     * Dispatch new-image notifications to subscribers of the event.
     *
     * @param int $post_id Event post ID.
     * @param int $count   Number of new images.
     */
    private function dispatch_new_images_notifications( $post_id, $count ) {
        $email_notifs = new EIM_Email_Notifications();
        $in_app       = new EIM_In_App_Notifications();
        $prefs_mgr    = new EIM_Notification_Preferences();

        global $wpdb;
        $table       = $wpdb->prefix . 'eim_email_subscriptions';
        $subscriber_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT user_id FROM {$table} WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id
            )
        );

        foreach ( $subscriber_ids as $user_id ) {
            $prefs = $prefs_mgr->get_user_prefs( (int) $user_id );
            if ( ! empty( $prefs['in_app_events'] ) ) {
                /* translators: %1$d: count, %2$s: event title */
                $message = sprintf(
                    __( '%1$d new photo(s) added to %2$s', 'event-image-manager' ),
                    $count, get_the_title( $post_id )
                );
                $in_app->add_notification( (int) $user_id, 'new_images', $message, get_permalink( $post_id ) );
            }
        }

        $email_notifs->send_new_images_notification( $post_id, $count );
    }
}

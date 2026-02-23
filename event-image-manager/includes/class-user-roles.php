<?php
/**
 * Class EIM_User_Roles
 *
 * Registers custom user roles (Photographer, Viewer) and capabilities
 * for the Event Image Manager plugin.
 */
class EIM_User_Roles {

    /** Custom capabilities added to the administrator role. */
    const CAPS = array(
        'eim_manage_events',       // Create / edit / delete any event.
        'eim_upload_images',       // Upload images to own events.
        'eim_delete_images',       // Delete images from any event.
        'eim_manage_settings',     // Access the plugin settings page.
        'eim_view_protected',      // View password-protected galleries.
        'eim_download_images',     // Download images (permission level checked separately).
        'eim_manage_roles',        // Manage plugin-specific roles.
    );

    public function __construct() {
        add_action( 'init', array( $this, 'register_roles' ) );
    }

    /**
     * Register custom roles and assign capabilities.
     * Called on 'init' and also directly from the activation hook.
     */
    public function register_roles() {
        $this->add_photographer_role();
        $this->add_viewer_role();
        $this->grant_admin_caps();
    }

    // -------------------------------------------------------------------------
    // Role definitions
    // -------------------------------------------------------------------------

    /** Add (or update) the Photographer role. */
    private function add_photographer_role() {
        $existing = get_role( 'eim_photographer' );
        if ( $existing ) {
            return; // Already registered; caps updated via grant_admin_caps().
        }

        add_role(
            'eim_photographer',
            __( 'Photographer', 'event-image-manager' ),
            array(
                'read'              => true,
                'eim_upload_images' => true,
                'eim_manage_events' => true,
                'eim_download_images' => true,
            )
        );
    }

    /** Add (or update) the Viewer role. */
    private function add_viewer_role() {
        $existing = get_role( 'eim_viewer' );
        if ( $existing ) {
            return;
        }

        add_role(
            'eim_viewer',
            __( 'Viewer', 'event-image-manager' ),
            array(
                'read'               => true,
                'eim_view_protected' => true,
            )
        );
    }

    /** Grant all plugin capabilities to the Administrator role. */
    private function grant_admin_caps() {
        $admin = get_role( 'administrator' );
        if ( ! $admin ) {
            return;
        }
        foreach ( self::CAPS as $cap ) {
            $admin->add_cap( $cap );
        }
    }

    // -------------------------------------------------------------------------
    // Cleanup (called from deactivation / uninstall hooks)
    // -------------------------------------------------------------------------

    /**
     * Remove custom roles and capabilities added by this plugin.
     * Should be called on plugin uninstall, not on deactivation.
     */
    public static function remove_roles() {
        remove_role( 'eim_photographer' );
        remove_role( 'eim_viewer' );

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::CAPS as $cap ) {
                $admin->remove_cap( $cap );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether the current user can manage events (create / edit / delete).
     *
     * @return bool
     */
    public static function current_user_can_manage() {
        return current_user_can( 'eim_manage_events' ) || current_user_can( 'manage_options' );
    }

    /**
     * Check whether the current user can upload images.
     *
     * @return bool
     */
    public static function current_user_can_upload() {
        return current_user_can( 'eim_upload_images' ) || current_user_can( 'manage_options' );
    }

    /**
     * Check whether the current user can download images.
     *
     * @return bool
     */
    public static function current_user_can_download() {
        return current_user_can( 'eim_download_images' ) || current_user_can( 'manage_options' );
    }
}

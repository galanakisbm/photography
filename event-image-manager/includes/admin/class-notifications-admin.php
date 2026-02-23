<?php
/**
 * Class EIM_Notifications_Admin
 *
 * Admin settings page for notification configuration.
 *
 * Settings (stored in WordPress options):
 *   - `eim_notify_sender_name`   : email sender display name.
 *   - `eim_notify_sender_email`  : email sender address.
 *   - `eim_notify_enable_email`  : enable/disable email notifications (1|0).
 *   - `eim_notify_enable_in_app` : enable/disable in-app notifications (1|0).
 *   - `eim_notify_template_new_images` : email template for new image notifications.
 *   - `eim_notify_template_new_event`  : email template for new event notifications.
 */
class EIM_Notifications_Admin {

    /** Settings group/section identifiers. */
    const OPTION_GROUP   = 'eim_notifications_options';
    const SETTINGS_PAGE  = 'eim-notifications';
    const SETTINGS_SECTION = 'eim_notifications_section';

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    /** Add submenu under the EIM settings. */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            __( 'Notification Settings', 'event-image-manager' ),
            __( 'Notifications', 'event-image-manager' ),
            'manage_options',
            self::SETTINGS_PAGE,
            array( $this, 'render_page' )
        );
    }

    // -------------------------------------------------------------------------
    // Settings API
    // -------------------------------------------------------------------------

    /** Register settings, sections, and fields. */
    public function register_settings() {
        register_setting( self::OPTION_GROUP, 'eim_notify_sender_name',          'sanitize_text_field' );
        register_setting( self::OPTION_GROUP, 'eim_notify_sender_email',         'sanitize_email' );
        register_setting( self::OPTION_GROUP, 'eim_notify_enable_email',         'absint' );
        register_setting( self::OPTION_GROUP, 'eim_notify_enable_in_app',        'absint' );
        register_setting( self::OPTION_GROUP, 'eim_notify_template_new_images',  'wp_kses_post' );
        register_setting( self::OPTION_GROUP, 'eim_notify_template_new_event',   'wp_kses_post' );

        add_settings_section(
            self::SETTINGS_SECTION,
            __( 'Notification Settings', 'event-image-manager' ),
            '__return_false',
            self::SETTINGS_PAGE
        );

        $fields = array(
            'eim_notify_sender_name'          => __( 'Sender Name', 'event-image-manager' ),
            'eim_notify_sender_email'         => __( 'Sender Email', 'event-image-manager' ),
            'eim_notify_enable_email'         => __( 'Enable Email Notifications', 'event-image-manager' ),
            'eim_notify_enable_in_app'        => __( 'Enable In-App Notifications', 'event-image-manager' ),
            'eim_notify_template_new_images'  => __( 'New Images Email Template', 'event-image-manager' ),
            'eim_notify_template_new_event'   => __( 'New Event Email Template', 'event-image-manager' ),
        );

        foreach ( $fields as $key => $label ) {
            add_settings_field(
                $key, $label,
                array( $this, 'render_field' ),
                self::SETTINGS_PAGE,
                self::SETTINGS_SECTION,
                array( 'key' => $key )
            );
        }
    }

    /**
     * Render a single settings field.
     *
     * @param array $args Field arguments including 'key'.
     */
    public function render_field( $args ) {
        $key   = $args['key'];
        $value = get_option( $key, '' );

        if ( in_array( $key, array( 'eim_notify_enable_email', 'eim_notify_enable_in_app' ), true ) ) {
            ?>
            <input type="checkbox" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $value, '1' ); ?>>
            <?php
        } elseif ( in_array( $key, array( 'eim_notify_template_new_images', 'eim_notify_template_new_event' ), true ) ) {
            ?>
            <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="4" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
            <?php
        } else {
            ?>
            <input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
            <?php
        }
    }

    // -------------------------------------------------------------------------
    // Page renderer
    // -------------------------------------------------------------------------

    /** Render the notifications settings page. */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'event-image-manager' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Notification Settings', 'event-image-manager' ); ?></h1>
            <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'event-image-manager' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::SETTINGS_PAGE );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

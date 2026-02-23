<?php
/**
 * Class EIM_Security_Admin
 *
 * Security settings and audit log admin page.
 *
 * Settings:
 *   - `eim_require_2fa`           : require 2FA for all photographers/admins (1|0).
 *   - `eim_ip_whitelist_mode`     : IP whitelist enforcement mode (disabled|warn|block).
 *   - `eim_audit_log_retention`   : number of days to retain audit log entries.
 *
 * Also displays recent audit log entries.
 */
class EIM_Security_Admin {

    const OPTION_GROUP    = 'eim_security_options';
    const SETTINGS_PAGE   = 'eim-security';
    const SETTINGS_SECTION = 'eim_security_section';

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    /** Register the Security submenu under Events. */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            __( 'Security Settings', 'event-image-manager' ),
            __( 'Security', 'event-image-manager' ),
            'manage_options',
            self::SETTINGS_PAGE,
            array( $this, 'render_page' )
        );
    }

    // -------------------------------------------------------------------------
    // Settings API
    // -------------------------------------------------------------------------

    /** Register security settings. */
    public function register_settings() {
        register_setting( self::OPTION_GROUP, 'eim_require_2fa',         'absint' );
        register_setting( self::OPTION_GROUP, 'eim_ip_whitelist_mode',   'sanitize_text_field' );
        register_setting( self::OPTION_GROUP, 'eim_audit_log_retention', 'absint' );

        add_settings_section(
            self::SETTINGS_SECTION,
            __( 'Security Settings', 'event-image-manager' ),
            '__return_false',
            self::SETTINGS_PAGE
        );

        add_settings_field( 'eim_require_2fa', __( 'Require 2FA', 'event-image-manager' ),
            array( $this, 'field_require_2fa' ), self::SETTINGS_PAGE, self::SETTINGS_SECTION );
        add_settings_field( 'eim_ip_whitelist_mode', __( 'IP Whitelist Mode', 'event-image-manager' ),
            array( $this, 'field_ip_mode' ), self::SETTINGS_PAGE, self::SETTINGS_SECTION );
        add_settings_field( 'eim_audit_log_retention', __( 'Audit Log Retention (days)', 'event-image-manager' ),
            array( $this, 'field_retention' ), self::SETTINGS_PAGE, self::SETTINGS_SECTION );
    }

    /** Field: require 2FA checkbox. */
    public function field_require_2fa() {
        $value = get_option( 'eim_require_2fa', 0 );
        ?>
        <input type="checkbox" id="eim_require_2fa" name="eim_require_2fa" value="1" <?php checked( $value, 1 ); ?>>
        <label for="eim_require_2fa"><?php esc_html_e( 'Require 2FA for all photographers and admins', 'event-image-manager' ); ?></label>
        <?php
    }

    /** Field: IP whitelist mode select. */
    public function field_ip_mode() {
        $value = get_option( 'eim_ip_whitelist_mode', 'disabled' );
        $modes = array(
            'disabled' => __( 'Disabled', 'event-image-manager' ),
            'warn'     => __( 'Warn only', 'event-image-manager' ),
            'block'    => __( 'Block access', 'event-image-manager' ),
        );
        ?>
        <select id="eim_ip_whitelist_mode" name="eim_ip_whitelist_mode">
            <?php foreach ( $modes as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /** Field: audit log retention in days. */
    public function field_retention() {
        $value = get_option( 'eim_audit_log_retention', 90 );
        ?>
        <input type="number" id="eim_audit_log_retention" name="eim_audit_log_retention" value="<?php echo absint( $value ); ?>" min="1" max="3650" class="small-text">
        <?php esc_html_e( 'days', 'event-image-manager' ); ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Page renderer
    // -------------------------------------------------------------------------

    /** Render the security settings + audit log page. */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'event-image-manager' ) );
        }

        $logs = EIM_Audit_Logger::get_logs( array(), 25, 0 );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Security Settings', 'event-image-manager' ); ?></h1>
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

            <h2><?php esc_html_e( 'Recent Audit Log', 'event-image-manager' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'User', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Object', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'IP', 'event-image-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( $log->created_at ); ?></td>
                            <td>
                                <?php
                                $user = get_userdata( (int) $log->user_id );
                                echo $user ? esc_html( $user->display_name ) : esc_html( $log->user_id );
                                ?>
                            </td>
                            <td><?php echo esc_html( $log->action ); ?></td>
                            <td><?php echo esc_html( $log->object_type . ' #' . $log->object_id ); ?></td>
                            <td><?php echo esc_html( $log->ip_address ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

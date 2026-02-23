<?php
/**
 * Class EIM_Admin_Roles
 *
 * Provides a role management interface in the WordPress admin under
 * Settings → Event Image Manager → Roles.
 *
 * Allows administrators to assign the 'eim_photographer' and 'eim_viewer'
 * roles to existing WordPress users.
 */
class EIM_Admin_Roles {

    /** Sub-page slug. */
    const PAGE_SLUG = 'eim-roles';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_roles_page' ) );
        add_action( 'admin_post_eim_save_user_role', array( $this, 'handle_save_role' ) );
    }

    // -------------------------------------------------------------------------
    // Menu registration
    // -------------------------------------------------------------------------

    /** Add the Roles sub-page under the EIM settings page. */
    public function add_roles_page() {
        add_submenu_page(
            'options-general.php',
            __( 'EIM – User Roles', 'event-image-manager' ),
            __( 'EIM Roles', 'event-image-manager' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_roles_page' )
        );
    }

    // -------------------------------------------------------------------------
    // Page render
    // -------------------------------------------------------------------------

    /** Render the Roles management page. */
    public function render_roles_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $message = '';
        if ( isset( $_GET['updated'] ) ) {
            $message = __( 'User role updated.', 'event-image-manager' );
        }

        $users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'EIM – User Role Management', 'event-image-manager' ); ?></h1>

            <?php if ( $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>

            <p><?php esc_html_e( 'Assign Event Image Manager roles to WordPress users.', 'event-image-manager' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'eim_save_user_role', 'eim_roles_nonce' ); ?>
                <input type="hidden" name="action" value="eim_save_user_role">

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'User', 'event-image-manager' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'event-image-manager' ); ?></th>
                            <th><?php esc_html_e( 'Current Role', 'event-image-manager' ); ?></th>
                            <th><?php esc_html_e( 'EIM Role', 'event-image-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $users as $user ) :
                            $current_eim_role = $this->get_eim_role( $user );
                        ?>
                            <tr>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
                                <td>
                                    <select name="eim_roles[<?php echo esc_attr( $user->ID ); ?>]">
                                        <option value=""><?php esc_html_e( '— None —', 'event-image-manager' ); ?></option>
                                        <option value="eim_photographer" <?php selected( $current_eim_role, 'eim_photographer' ); ?>>
                                            <?php esc_html_e( 'Photographer', 'event-image-manager' ); ?>
                                        </option>
                                        <option value="eim_viewer" <?php selected( $current_eim_role, 'eim_viewer' ); ?>>
                                            <?php esc_html_e( 'Viewer', 'event-image-manager' ); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" class="button-primary"
                           value="<?php esc_attr_e( 'Save Roles', 'event-image-manager' ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Form handler
    // -------------------------------------------------------------------------

    /** Handle the role-save form submission. */
    public function handle_save_role() {
        check_admin_referer( 'eim_save_user_role', 'eim_roles_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'event-image-manager' ) );
        }

        $roles_data = isset( $_POST['eim_roles'] ) && is_array( $_POST['eim_roles'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['eim_roles'] ) )
            : array();

        $valid_eim_roles = array( '', 'eim_photographer', 'eim_viewer' );

        foreach ( $roles_data as $user_id => $new_role ) {
            $user_id  = absint( $user_id );
            $new_role = in_array( $new_role, $valid_eim_roles, true ) ? $new_role : '';
            $user_obj = get_userdata( $user_id );

            if ( ! $user_obj ) {
                continue;
            }

            // Remove any existing EIM roles.
            foreach ( array( 'eim_photographer', 'eim_viewer' ) as $r ) {
                $user_obj->remove_role( $r );
            }

            // Add the chosen role.
            if ( $new_role ) {
                $user_obj->add_role( $new_role );
            }
        }

        wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether a user has an EIM-specific role and return it.
     *
     * @param  WP_User $user
     * @return string  'eim_photographer', 'eim_viewer', or ''.
     */
    private function get_eim_role( WP_User $user ) {
        foreach ( array( 'eim_photographer', 'eim_viewer' ) as $role ) {
            if ( in_array( $role, $user->roles, true ) ) {
                return $role;
            }
        }
        return '';
    }
}

<?php
/**
 * Class EIM_Monetization_Admin
 *
 * Monetization settings page and revenue dashboard.
 *
 * Settings:
 *   - `eim_stripe_api_key`       : Stripe secret key.
 *   - `eim_stripe_publishable_key`: Stripe publishable key.
 *   - `eim_paypal_client_id`     : PayPal client ID.
 *   - `eim_paypal_client_secret` : PayPal client secret.
 *   - `eim_commission_rate`      : Default photographer commission rate (0–1).
 *
 * Dashboard shows:
 *   - Total revenue.
 *   - Recent orders.
 *   - Commission summary per photographer.
 */
class EIM_Monetization_Admin {

    const OPTION_GROUP    = 'eim_monetization_options';
    const SETTINGS_PAGE   = 'eim-monetization';
    const SETTINGS_SECTION = 'eim_monetization_section';

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    /** Register Monetization submenu under Events. */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            __( 'Monetization', 'event-image-manager' ),
            __( 'Monetization', 'event-image-manager' ),
            'manage_options',
            self::SETTINGS_PAGE,
            array( $this, 'render_page' )
        );
    }

    // -------------------------------------------------------------------------
    // Settings API
    // -------------------------------------------------------------------------

    /** Register monetization settings. */
    public function register_settings() {
        register_setting( self::OPTION_GROUP, 'eim_stripe_api_key',        'sanitize_text_field' );
        register_setting( self::OPTION_GROUP, 'eim_stripe_publishable_key','sanitize_text_field' );
        register_setting( self::OPTION_GROUP, 'eim_paypal_client_id',      'sanitize_text_field' );
        register_setting( self::OPTION_GROUP, 'eim_paypal_client_secret',  'sanitize_text_field' );
        register_setting( self::OPTION_GROUP, 'eim_commission_rate',       'floatval' );

        add_settings_section(
            self::SETTINGS_SECTION,
            __( 'Payment Gateway Settings', 'event-image-manager' ),
            '__return_false',
            self::SETTINGS_PAGE
        );

        $fields = array(
            'eim_stripe_api_key'         => __( 'Stripe Secret Key', 'event-image-manager' ),
            'eim_stripe_publishable_key' => __( 'Stripe Publishable Key', 'event-image-manager' ),
            'eim_paypal_client_id'       => __( 'PayPal Client ID', 'event-image-manager' ),
            'eim_paypal_client_secret'   => __( 'PayPal Client Secret', 'event-image-manager' ),
            'eim_commission_rate'        => __( 'Default Commission Rate (0–1)', 'event-image-manager' ),
        );

        foreach ( $fields as $key => $label ) {
            add_settings_field( $key, $label, array( $this, 'render_text_field' ), self::SETTINGS_PAGE, self::SETTINGS_SECTION, array( 'key' => $key ) );
        }
    }

    /**
     * Render a text input for a settings field.
     *
     * @param array $args Field args including 'key'.
     */
    public function render_text_field( $args ) {
        $key   = $args['key'];
        $value = get_option( $key, '' );
        // Mask key fields when set.
        $is_key   = false !== strpos( $key, 'key' ) || false !== strpos( $key, 'secret' );
        $type     = $is_key ? 'password' : 'text';
        ?>
        <input
            type="<?php echo esc_attr( $type ); ?>"
            id="<?php echo esc_attr( $key ); ?>"
            name="<?php echo esc_attr( $key ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        >
        <?php
    }

    // -------------------------------------------------------------------------
    // Page renderer
    // -------------------------------------------------------------------------

    /** Render the monetization settings page and dashboard. */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'event-image-manager' ) );
        }

        global $wpdb;

        $orders_table      = $wpdb->prefix . 'eim_orders';
        $commissions_table = $wpdb->prefix . 'eim_commissions';

        // Total revenue.
        $total_revenue = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT SUM(amount) FROM {$orders_table} WHERE status = 'complete'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        // Recent orders.
        $recent_orders = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$orders_table} ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                10
            )
        );

        // Commission summary per photographer.
        $commission_summary = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT photographer_id, SUM(commission_amount) AS total_commission FROM {$commissions_table} GROUP BY photographer_id ORDER BY total_commission DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                10
            )
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Monetization', 'event-image-manager' ); ?></h1>
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

            <h2><?php esc_html_e( 'Revenue Dashboard', 'event-image-manager' ); ?></h2>
            <p>
                <strong><?php esc_html_e( 'Total Revenue:', 'event-image-manager' ); ?></strong>
                <?php echo esc_html( '$' . number_format( $total_revenue, 2 ) ); ?>
            </p>

            <h3><?php esc_html_e( 'Recent Orders', 'event-image-manager' ); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Order ID', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'User', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Gateway', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Amount', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'event-image-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_orders as $order ) : ?>
                        <tr>
                            <td><?php echo absint( $order->id ); ?></td>
                            <td>
                                <?php
                                $user = get_userdata( (int) $order->user_id );
                                echo $user ? esc_html( $user->display_name ) : esc_html__( 'Guest', 'event-image-manager' );
                                ?>
                            </td>
                            <td><?php echo esc_html( $order->gateway ); ?></td>
                            <td><?php echo esc_html( '$' . number_format( (float) $order->amount, 2 ) . ' ' . strtoupper( $order->currency ) ); ?></td>
                            <td><?php echo esc_html( $order->status ); ?></td>
                            <td><?php echo esc_html( $order->created_at ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Commission Summary', 'event-image-manager' ); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Photographer', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Total Commission', 'event-image-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $commission_summary as $row ) : ?>
                        <tr>
                            <td>
                                <?php
                                $user = get_userdata( (int) $row->photographer_id );
                                echo $user ? esc_html( $user->display_name ) : absint( $row->photographer_id );
                                ?>
                            </td>
                            <td><?php echo esc_html( '$' . number_format( (float) $row->total_commission, 2 ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

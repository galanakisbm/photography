<?php
/**
 * Class EIM_Downloads_Admin
 *
 * Admin page for download management, statistics, and logs.
 *
 * Registers a submenu page under the Events post-type menu and displays:
 *   - Total download counts per event.
 *   - Top downloaded images.
 *   - Raw download log entries from `{prefix}event_download_logs`.
 */
class EIM_Downloads_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    /** Register submenu page under Events. */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            __( 'Download Logs', 'event-image-manager' ),
            __( 'Download Logs', 'event-image-manager' ),
            'manage_options',
            'eim-download-logs',
            array( $this, 'render_page' )
        );
    }

    // -------------------------------------------------------------------------
    // Page renderer
    // -------------------------------------------------------------------------

    /** Render the downloads admin page. */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'event-image-manager' ) );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'event_download_logs';
        $paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per_page = 20;
        $offset  = ( $paged - 1 ) * $per_page;

        // Top events by download count.
        $top_events = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT post_id, COUNT(*) AS download_count FROM {$table} GROUP BY post_id ORDER BY download_count DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                10
            )
        );

        // Top images.
        $top_images = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT post_id, img_index, COUNT(*) AS download_count FROM {$table} GROUP BY post_id, img_index ORDER BY download_count DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                10
            )
        );

        // Recent log entries.
        $logs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY downloaded_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $per_page, $offset
            )
        );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Download Logs', 'event-image-manager' ); ?></h1>

            <h2><?php esc_html_e( 'Top Events by Downloads', 'event-image-manager' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Event', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Downloads', 'event-image-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $top_events as $row ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>">
                                    <?php echo esc_html( get_the_title( $row->post_id ) ); ?>
                                </a>
                            </td>
                            <td><?php echo absint( $row->download_count ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Recent Downloads', 'event-image-manager' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Event', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Image #', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'User', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'IP Address', 'event-image-manager' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'event-image-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( get_the_title( $log->post_id ) ); ?></td>
                            <td><?php echo absint( $log->img_index ); ?></td>
                            <td>
                                <?php
                                $user = get_userdata( (int) $log->user_id );
                                echo $user ? esc_html( $user->display_name ) : esc_html__( 'Guest', 'event-image-manager' );
                                ?>
                            </td>
                            <td><?php echo esc_html( $log->ip_address ); ?></td>
                            <td><?php echo esc_html( $log->downloaded_at ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $pagination_args = array(
                'base'    => add_query_arg( 'paged', '%#%' ),
                'format'  => '',
                'current' => $paged,
                'total'   => ceil( $total / $per_page ),
            );
            echo wp_kses_post( paginate_links( $pagination_args ) );
            ?>
        </div>
        <?php
    }
}

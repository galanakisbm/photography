<?php
/**
 * Class EIM_Invoice_Generator
 *
 * Automatically generates and emails invoices for completed orders.
 *
 * Invoice records are stored in `{prefix}eim_invoices`.
 * Invoice numbers follow the pattern INV-{YEAR}-{ID}.
 */
class EIM_Invoice_Generator {

    public function __construct() {
        // Hook into order completion to auto-generate invoices.
        add_action( 'eim_order_complete', array( $this, 'on_order_complete' ), 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Hook callbacks
    // -------------------------------------------------------------------------

    /**
     * Called when an order is marked complete.
     *
     * @param int $order_id WordPress-side order row ID.
     * @param int $user_id  Purchaser's user ID.
     */
    public function on_order_complete( $order_id, $user_id ) {
        $this->send_invoice_email( $order_id, $user_id );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate invoice HTML for an order.
     *
     * @param  int    $order_id EIM order ID.
     * @return string           Invoice HTML string.
     */
    public function generate( $order_id ) {
        global $wpdb;
        $orders_table   = $wpdb->prefix . 'eim_orders';
        $invoices_table = $wpdb->prefix . 'eim_invoices';

        $order = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$orders_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $order_id )
            )
        );

        if ( ! $order ) {
            return '';
        }

        // Create or retrieve the invoice record.
        $invoice = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM {$invoices_table} WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $order_id )
            )
        );

        if ( ! $invoice ) {
            $year           = current_time( 'Y' );
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $invoices_table,
                array(
                    'order_id'       => absint( $order_id ),
                    'user_id'        => absint( $order->user_id ),
                    'invoice_number' => '',  // placeholder until we have the ID.
                    'subtotal'       => (float) $order->amount,
                    'tax'            => 0.00,
                    'total'          => (float) $order->amount,
                    'status'         => 'pending',
                    'issued_at'      => current_time( 'mysql' ),
                    'due_at'         => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
            );
            $invoice_id             = $wpdb->insert_id;
            $invoice_number         = 'INV-' . $year . '-' . str_pad( (string) $invoice_id, 5, '0', STR_PAD_LEFT );
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $invoices_table,
                array( 'invoice_number' => $invoice_number ),
                array( 'id' => $invoice_id ),
                array( '%s' ),
                array( '%d' )
            );
            $invoice = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT * FROM {$invoices_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $invoice_id
                )
            );
        }

        $user      = get_userdata( (int) $order->user_id );
        $site_name = esc_html( get_bloginfo( 'name' ) );

        ob_start();
        ?>
        <div class="eim-invoice" style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;">
            <h1><?php echo esc_html( $site_name ); ?></h1>
            <h2><?php echo esc_html__( 'Invoice', 'event-image-manager' ); ?>
                #<?php echo esc_html( $invoice->invoice_number ); ?></h2>
            <p>
                <?php esc_html_e( 'Issued:', 'event-image-manager' ); ?>
                <?php echo esc_html( $invoice->issued_at ); ?><br>
                <?php esc_html_e( 'Billed to:', 'event-image-manager' ); ?>
                <?php echo $user ? esc_html( $user->display_name . ' <' . $user->user_email . '>' ) : esc_html__( 'Guest', 'event-image-manager' ); ?>
            </p>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="padding:8px;text-align:left;"><?php esc_html_e( 'Description', 'event-image-manager' ); ?></th>
                        <th style="padding:8px;text-align:right;"><?php esc_html_e( 'Amount', 'event-image-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding:8px;"><?php esc_html_e( 'Photo purchase', 'event-image-manager' ); ?></td>
                        <td style="padding:8px;text-align:right;"><?php echo esc_html( number_format( (float) $invoice->subtotal, 2 ) . ' ' . strtoupper( $order->currency ) ); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th style="padding:8px;text-align:right;"><?php esc_html_e( 'Total', 'event-image-manager' ); ?></th>
                        <th style="padding:8px;text-align:right;"><?php echo esc_html( number_format( (float) $invoice->total, 2 ) . ' ' . strtoupper( $order->currency ) ); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Send an invoice email to a user.
     *
     * @param int $order_id EIM order ID.
     * @param int $user_id  WordPress user ID.
     */
    public function send_invoice_email( $order_id, $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return;
        }

        $html    = $this->generate( $order_id );
        $subject = __( 'Your Invoice', 'event-image-manager' );

        $content_type_cb = static function() {
            return 'text/html';
        };
        add_filter( 'wp_mail_content_type', $content_type_cb );
        wp_mail( $user->user_email, $subject, $html );
        remove_filter( 'wp_mail_content_type', $content_type_cb );
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the invoices table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_invoices';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id       BIGINT UNSIGNED NOT NULL,
            user_id        BIGINT UNSIGNED NOT NULL DEFAULT 0,
            invoice_number VARCHAR(32) NOT NULL DEFAULT '',
            subtotal       DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            tax            DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            total          DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            status         ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
            issued_at      DATETIME NOT NULL,
            due_at         DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY user_id  (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

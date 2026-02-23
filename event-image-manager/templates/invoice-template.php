<?php
/**
 * Template: Invoice
 *
 * HTML invoice for printing or PDF generation.
 * PHP variables available:
 *   $invoice  array  Invoice data (number, date, due_date, status, tax_rate)
 *   $order    array  Order data (id, created_at)
 *   $user     array  Customer data (name, email, address)
 *   $items    array  Line items (description, quantity, unit_price)
 */

$invoice = isset( $invoice ) && is_array( $invoice ) ? $invoice : array();
$order   = isset( $order )   && is_array( $order )   ? $order   : array();
$user    = isset( $user )    && is_array( $user )     ? $user    : array();
$items   = isset( $items )   && is_array( $items )    ? $items   : array();

$invoice_number = isset( $invoice['number'] )   ? $invoice['number']   : 'INV-0001';
$invoice_date   = isset( $invoice['date'] )     ? $invoice['date']     : wp_date( 'Y-m-d' );
$due_date       = isset( $invoice['due_date'] ) ? $invoice['due_date'] : '';
$status         = isset( $invoice['status'] )   ? $invoice['status']   : 'pending';
$tax_rate       = isset( $invoice['tax_rate'] ) ? (float) $invoice['tax_rate'] : 0.0;

$customer_name  = isset( $user['name'] )    ? $user['name']    : '';
$customer_email = isset( $user['email'] )   ? $user['email']   : '';
$customer_addr  = isset( $user['address'] ) ? $user['address'] : '';

$subtotal = 0.0;
foreach ( $items as $item ) {
    $qty        = isset( $item['quantity'] )   ? (int) $item['quantity']     : 1;
    $unit_price = isset( $item['unit_price'] ) ? (float) $item['unit_price'] : 0.0;
    $subtotal  += $qty * $unit_price;
}
$tax_amount = $subtotal * ( $tax_rate / 100 );
$total      = $subtotal + $tax_amount;

$status_labels = array(
    'paid'    => __( 'Paid', 'event-image-manager' ),
    'pending' => __( 'Pending', 'event-image-manager' ),
    'overdue' => __( 'Overdue', 'event-image-manager' ),
    'void'    => __( 'Void', 'event-image-manager' ),
);
$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <title><?php echo esc_html( sprintf( __( 'Invoice %s', 'event-image-manager' ), $invoice_number ) ); ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; background: #fff; padding: 40px; }

        /* ── Header ── */
        .eim-invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
        .eim-invoice-logo { font-size: 24px; font-weight: bold; color: #111; }
        .eim-invoice-meta { text-align: right; }
        .eim-invoice-meta h1 { font-size: 28px; color: #444; text-transform: uppercase; letter-spacing: 2px; }
        .eim-invoice-meta table td { padding: 2px 8px 2px 0; }

        /* ── Billing info ── */
        .eim-invoice-billing { display: flex; justify-content: space-between; margin-bottom: 32px; }
        .eim-invoice-billing-block h3 { font-size: 12px; text-transform: uppercase; color: #888; margin-bottom: 6px; }

        /* ── Line items ── */
        .eim-invoice-items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .eim-invoice-items th, .eim-invoice-items td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; text-align: left; }
        .eim-invoice-items th { background: #f5f5f5; font-size: 12px; text-transform: uppercase; color: #666; }
        .eim-invoice-items td.eim-amount { text-align: right; }
        .eim-invoice-items th.eim-amount { text-align: right; }

        /* ── Totals ── */
        .eim-invoice-totals { width: 280px; margin-left: auto; }
        .eim-invoice-totals table { width: 100%; border-collapse: collapse; }
        .eim-invoice-totals td { padding: 6px 0; }
        .eim-invoice-totals td:last-child { text-align: right; }
        .eim-invoice-totals .eim-total-row td { font-weight: bold; font-size: 16px; border-top: 2px solid #333; padding-top: 10px; }

        /* ── Status badge ── */
        .eim-status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .eim-status-badge--paid    { background: #e8f5e9; color: #2e7d32; }
        .eim-status-badge--pending { background: #fff8e1; color: #f57f17; }
        .eim-status-badge--overdue { background: #fce4ec; color: #c62828; }
        .eim-status-badge--void    { background: #f5f5f5; color: #757575; }

        @media print {
            body { padding: 20px; }
            .eim-invoice-no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="eim-invoice-header">
        <div class="eim-invoice-logo">
            <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
        </div>
        <div class="eim-invoice-meta">
            <h1><?php esc_html_e( 'Invoice', 'event-image-manager' ); ?></h1>
            <table>
                <tr>
                    <td><?php esc_html_e( 'Invoice #:', 'event-image-manager' ); ?></td>
                    <td><?php echo esc_html( $invoice_number ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Date:', 'event-image-manager' ); ?></td>
                    <td><?php echo esc_html( $invoice_date ); ?></td>
                </tr>
                <?php if ( $due_date ) : ?>
                <tr>
                    <td><?php esc_html_e( 'Due Date:', 'event-image-manager' ); ?></td>
                    <td><?php echo esc_html( $due_date ); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><?php esc_html_e( 'Status:', 'event-image-manager' ); ?></td>
                    <td>
                        <span class="eim-status-badge eim-status-badge--<?php echo esc_attr( $status ); ?>">
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Billing info -->
    <div class="eim-invoice-billing">
        <div class="eim-invoice-billing-block">
            <h3><?php esc_html_e( 'Bill To', 'event-image-manager' ); ?></h3>
            <?php if ( $customer_name ) : ?>
            <p><?php echo esc_html( $customer_name ); ?></p>
            <?php endif; ?>
            <?php if ( $customer_email ) : ?>
            <p><?php echo esc_html( $customer_email ); ?></p>
            <?php endif; ?>
            <?php if ( $customer_addr ) : ?>
            <p><?php echo nl2br( esc_html( $customer_addr ) ); ?></p>
            <?php endif; ?>
        </div>
        <div class="eim-invoice-billing-block">
            <h3><?php esc_html_e( 'From', 'event-image-manager' ); ?></h3>
            <p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
            <p><?php echo esc_html( get_bloginfo( 'admin_email' ) ); ?></p>
        </div>
    </div>

    <!-- Line items table -->
    <table class="eim-invoice-items">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Description', 'event-image-manager' ); ?></th>
                <th class="eim-amount"><?php esc_html_e( 'Qty', 'event-image-manager' ); ?></th>
                <th class="eim-amount"><?php esc_html_e( 'Unit Price', 'event-image-manager' ); ?></th>
                <th class="eim-amount"><?php esc_html_e( 'Total', 'event-image-manager' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $items ) ) :
                foreach ( $items as $item ) :
                    $desc       = isset( $item['description'] ) ? $item['description'] : '';
                    $qty        = isset( $item['quantity'] )    ? (int) $item['quantity']     : 1;
                    $unit_price = isset( $item['unit_price'] )  ? (float) $item['unit_price'] : 0.0;
                    $line_total = $qty * $unit_price;
            ?>
            <tr>
                <td><?php echo esc_html( $desc ); ?></td>
                <td class="eim-amount"><?php echo esc_html( $qty ); ?></td>
                <td class="eim-amount">$<?php echo esc_html( number_format( $unit_price, 2 ) ); ?></td>
                <td class="eim-amount">$<?php echo esc_html( number_format( $line_total, 2 ) ); ?></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr>
                <td colspan="4"><?php esc_html_e( 'No items.', 'event-image-manager' ); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="eim-invoice-totals">
        <table>
            <tr>
                <td><?php esc_html_e( 'Subtotal', 'event-image-manager' ); ?></td>
                <td>$<?php echo esc_html( number_format( $subtotal, 2 ) ); ?></td>
            </tr>
            <?php if ( $tax_rate > 0 ) : ?>
            <tr>
                <td><?php echo esc_html( sprintf( __( 'Tax (%s%%)', 'event-image-manager' ), $tax_rate ) ); ?></td>
                <td>$<?php echo esc_html( number_format( $tax_amount, 2 ) ); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="eim-total-row">
                <td><?php esc_html_e( 'Total', 'event-image-manager' ); ?></td>
                <td>$<?php echo esc_html( number_format( $total, 2 ) ); ?></td>
            </tr>
        </table>
    </div>

    <div class="eim-invoice-no-print" style="margin-top:32px;">
        <button onclick="window.print();" class="button button-primary"><?php esc_html_e( 'Print Invoice', 'event-image-manager' ); ?></button>
    </div>

</body>
</html>

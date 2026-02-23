<?php
/**
 * Template: Purchase Modal
 *
 * Dialog for purchasing a photo license.
 * PHP variable available: $post_id.
 */

$post_id = isset( $post_id ) ? absint( $post_id ) : 0;
?>
<div id="eim-purchase-modal" class="eim-modal" role="dialog" aria-modal="true"
     aria-labelledby="eim-purchase-modal-title" style="display:none;">
    <div class="eim-modal-overlay"></div>
    <div class="eim-modal-content eim-modal-content--purchase">
        <button type="button" class="eim-modal-close"
                aria-label="<?php esc_attr_e( 'Close', 'event-image-manager' ); ?>">&times;</button>

        <h2 id="eim-purchase-modal-title"><?php esc_html_e( 'Purchase Photo', 'event-image-manager' ); ?></h2>

        <div class="eim-purchase-layout">

            <!-- Photo preview -->
            <div class="eim-purchase-preview">
                <img id="eim-purchase-preview-img" src="" alt="<?php esc_attr_e( 'Photo preview', 'event-image-manager' ); ?>">
            </div>

            <!-- Purchase form -->
            <div class="eim-purchase-form-wrap">
                <form id="eim-purchase-form" novalidate>
                    <?php wp_nonce_field( 'eim_payment_nonce', 'eim_payment_nonce' ); ?>
                    <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
                    <input type="hidden" id="eim-purchase-img-index" name="img_index" value="">

                    <!-- License type -->
                    <fieldset class="eim-purchase-fieldset">
                        <legend><?php esc_html_e( 'License Type', 'event-image-manager' ); ?></legend>
                        <label class="eim-purchase-option">
                            <input type="radio" name="license_type" value="personal" checked>
                            <span class="eim-purchase-option-label">
                                <?php esc_html_e( 'Personal', 'event-image-manager' ); ?>
                                <small><?php esc_html_e( 'For personal use only', 'event-image-manager' ); ?></small>
                            </span>
                            <span class="eim-purchase-option-price" data-license="personal">$9.99</span>
                        </label>
                        <label class="eim-purchase-option">
                            <input type="radio" name="license_type" value="commercial">
                            <span class="eim-purchase-option-label">
                                <?php esc_html_e( 'Commercial', 'event-image-manager' ); ?>
                                <small><?php esc_html_e( 'For business or commercial use', 'event-image-manager' ); ?></small>
                            </span>
                            <span class="eim-purchase-option-price" data-license="commercial">$49.99</span>
                        </label>
                    </fieldset>

                    <!-- Payment method -->
                    <fieldset class="eim-purchase-fieldset">
                        <legend><?php esc_html_e( 'Payment Method', 'event-image-manager' ); ?></legend>
                        <label class="eim-purchase-option">
                            <input type="radio" name="payment_method" value="card" checked>
                            <?php esc_html_e( 'Credit / Debit Card', 'event-image-manager' ); ?>
                        </label>
                        <label class="eim-purchase-option">
                            <input type="radio" name="payment_method" value="paypal">
                            <?php esc_html_e( 'PayPal', 'event-image-manager' ); ?>
                        </label>
                    </fieldset>

                    <!-- Stripe card element -->
                    <div id="eim-stripe-card-wrap" class="eim-payment-method-section">
                        <label for="eim-card-element"><?php esc_html_e( 'Card Details', 'event-image-manager' ); ?></label>
                        <div id="eim-card-element" class="eim-stripe-card-element"></div>
                        <div id="eim-card-errors" class="eim-card-errors" role="alert"></div>
                    </div>

                    <!-- PayPal button container -->
                    <div id="eim-paypal-button-wrap" class="eim-payment-method-section" style="display:none;">
                        <div id="eim-paypal-button-container"></div>
                    </div>

                    <!-- Order summary -->
                    <div class="eim-order-summary">
                        <h3><?php esc_html_e( 'Order Summary', 'event-image-manager' ); ?></h3>
                        <table class="eim-order-table">
                            <tr>
                                <td><?php esc_html_e( 'License', 'event-image-manager' ); ?></td>
                                <td id="eim-summary-license"><?php esc_html_e( 'Personal', 'event-image-manager' ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Price', 'event-image-manager' ); ?></td>
                                <td id="eim-summary-price">$9.99</td>
                            </tr>
                            <tr class="eim-order-total">
                                <td><strong><?php esc_html_e( 'Total', 'event-image-manager' ); ?></strong></td>
                                <td id="eim-summary-total"><strong>$9.99</strong></td>
                            </tr>
                        </table>
                    </div>

                    <button type="submit" id="eim-purchase-submit" class="button button-primary">
                        <?php esc_html_e( 'Complete Purchase', 'event-image-manager' ); ?>
                    </button>
                </form>

                <p class="eim-purchase-status" aria-live="polite"></p>
            </div>
        </div>
    </div>
</div>

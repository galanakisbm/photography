<?php
/**
 * Template: Pricing Plans Table
 *
 * Displays Free, Basic, Pro, and Enterprise plan cards.
 * PHP variable available: $current_plan (string plan slug).
 */

$current_plan = isset( $current_plan ) ? sanitize_key( $current_plan ) : 'free';

$plans = array(
    'free' => array(
        'name'     => __( 'Free', 'event-image-manager' ),
        'price'    => 0,
        'currency' => '$',
        'popular'  => false,
        'features' => array(
            __( '1 Event', 'event-image-manager' ),
            __( 'Up to 50 photos', 'event-image-manager' ),
            __( 'Basic watermark', 'event-image-manager' ),
            __( 'Standard gallery', 'event-image-manager' ),
            __( 'Email support', 'event-image-manager' ),
        ),
    ),
    'basic' => array(
        'name'     => __( 'Basic', 'event-image-manager' ),
        'price'    => 9,
        'currency' => '$',
        'popular'  => false,
        'features' => array(
            __( '10 Events', 'event-image-manager' ),
            __( 'Up to 500 photos', 'event-image-manager' ),
            __( 'Custom watermark', 'event-image-manager' ),
            __( 'Advanced gallery', 'event-image-manager' ),
            __( 'Download manager', 'event-image-manager' ),
            __( 'Priority email support', 'event-image-manager' ),
        ),
    ),
    'pro' => array(
        'name'     => __( 'Pro', 'event-image-manager' ),
        'price'    => 29,
        'currency' => '$',
        'popular'  => true,
        'features' => array(
            __( 'Unlimited Events', 'event-image-manager' ),
            __( 'Unlimited photos', 'event-image-manager' ),
            __( 'Custom watermark', 'event-image-manager' ),
            __( 'Advanced gallery + print', 'event-image-manager' ),
            __( 'Download export & queue', 'event-image-manager' ),
            __( 'Monetization tools', 'event-image-manager' ),
            __( 'Client messaging', 'event-image-manager' ),
            __( '2FA security', 'event-image-manager' ),
            __( 'Priority support', 'event-image-manager' ),
        ),
    ),
    'enterprise' => array(
        'name'     => __( 'Enterprise', 'event-image-manager' ),
        'price'    => 99,
        'currency' => '$',
        'popular'  => false,
        'features' => array(
            __( 'Everything in Pro', 'event-image-manager' ),
            __( 'White-label branding', 'event-image-manager' ),
            __( 'Custom domain', 'event-image-manager' ),
            __( 'API access', 'event-image-manager' ),
            __( 'Dedicated account manager', 'event-image-manager' ),
            __( 'SLA guarantee', 'event-image-manager' ),
            __( 'Custom integrations', 'event-image-manager' ),
        ),
    ),
);
?>
<div class="eim-pricing-table" role="region" aria-label="<?php esc_attr_e( 'Pricing Plans', 'event-image-manager' ); ?>">
    <?php foreach ( $plans as $slug => $plan ) :
        $is_current = ( $slug === $current_plan );
        $is_popular = $plan['popular'];
        $card_class = 'eim-pricing-card';
        if ( $is_popular ) {
            $card_class .= ' eim-pricing-card--popular';
        }
        if ( $is_current ) {
            $card_class .= ' eim-pricing-card--current';
        }
    ?>
    <div class="<?php echo esc_attr( $card_class ); ?>">
        <?php if ( $is_popular ) : ?>
        <div class="eim-pricing-badge"><?php esc_html_e( 'Most Popular', 'event-image-manager' ); ?></div>
        <?php endif; ?>

        <h3 class="eim-pricing-plan-name"><?php echo esc_html( $plan['name'] ); ?></h3>

        <div class="eim-pricing-price">
            <?php if ( 0 === $plan['price'] ) : ?>
                <span class="eim-pricing-amount"><?php esc_html_e( 'Free', 'event-image-manager' ); ?></span>
            <?php else : ?>
                <span class="eim-pricing-currency"><?php echo esc_html( $plan['currency'] ); ?></span>
                <span class="eim-pricing-amount"><?php echo esc_html( $plan['price'] ); ?></span>
                <span class="eim-pricing-period"><?php esc_html_e( '/ month', 'event-image-manager' ); ?></span>
            <?php endif; ?>
        </div>

        <ul class="eim-pricing-features" aria-label="<?php echo esc_attr( sprintf( __( '%s plan features', 'event-image-manager' ), $plan['name'] ) ); ?>">
            <?php foreach ( $plan['features'] as $feature ) : ?>
            <li class="eim-pricing-feature">
                <span class="eim-pricing-check" aria-hidden="true">&#x2713;</span>
                <?php echo esc_html( $feature ); ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="eim-pricing-action">
            <?php if ( $is_current ) : ?>
            <button type="button" class="button button-secondary eim-pricing-btn eim-pricing-btn--current" disabled
                    aria-label="<?php echo esc_attr( sprintf( __( 'Current plan: %s', 'event-image-manager' ), $plan['name'] ) ); ?>">
                <?php esc_html_e( 'Current Plan', 'event-image-manager' ); ?>
            </button>
            <?php else : ?>
            <button type="button" class="button button-primary eim-pricing-btn eim-pricing-btn--upgrade"
                    data-plan="<?php echo esc_attr( $slug ); ?>"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Upgrade to %s', 'event-image-manager' ), $plan['name'] ) ); ?>">
                <?php esc_html_e( 'Upgrade', 'event-image-manager' ); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

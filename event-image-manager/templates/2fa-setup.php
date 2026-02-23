<?php
/**
 * Template: Two-Factor Authentication Setup
 *
 * PHP variables available:
 *   $user_id      int     Current user ID
 *   $secret       string  TOTP secret key
 *   $backup_codes array   One-time backup codes
 *   $is_enabled   bool    Whether 2FA is currently active
 */

$user_id      = isset( $user_id )      ? absint( $user_id )       : get_current_user_id();
$secret       = isset( $secret )       ? (string) $secret         : '';
$backup_codes = isset( $backup_codes ) && is_array( $backup_codes ) ? $backup_codes : array();
$is_enabled   = isset( $is_enabled )   ? (bool) $is_enabled       : false;

$site_name    = rawurlencode( get_bloginfo( 'name' ) );
$user_email   = rawurlencode( get_userdata( $user_id )->user_email ?? '' );
$otpauth_url  = 'otpauth://totp/' . $site_name . ':' . $user_email . '?secret=' . rawurlencode( $secret ) . '&issuer=' . $site_name;
?>
<div class="eim-2fa-setup-wrap">
    <h2><?php esc_html_e( 'Two-Factor Authentication', 'event-image-manager' ); ?></h2>

    <?php if ( $is_enabled ) : ?>
    <div class="eim-2fa-status eim-2fa-status--enabled">
        <span class="eim-2fa-status-icon" aria-hidden="true">&#x2705;</span>
        <?php esc_html_e( '2FA is currently enabled on your account.', 'event-image-manager' ); ?>
    </div>
    <?php else : ?>
    <div class="eim-2fa-status eim-2fa-status--disabled">
        <span class="eim-2fa-status-icon" aria-hidden="true">&#x26A0;</span>
        <?php esc_html_e( '2FA is not yet enabled. Follow the steps below to set it up.', 'event-image-manager' ); ?>
    </div>
    <?php endif; ?>

    <!-- Step 1: Scan QR code -->
    <section class="eim-2fa-section">
        <h3><?php esc_html_e( 'Step 1: Scan the QR Code', 'event-image-manager' ); ?></h3>
        <p><?php esc_html_e( 'Use an authenticator app (e.g. Google Authenticator or Authy) to scan this QR code.', 'event-image-manager' ); ?></p>

        <!-- QR code container; JS will generate the QR image using data-otpauth -->
        <div id="eim-qrcode"
             class="eim-qrcode"
             data-otpauth="<?php echo esc_attr( $otpauth_url ); ?>"
             aria-label="<?php esc_attr_e( 'QR code for authenticator app', 'event-image-manager' ); ?>">
        </div>
    </section>

    <!-- Step 2: Manual entry key -->
    <section class="eim-2fa-section">
        <h3><?php esc_html_e( 'Step 2: Or Enter the Key Manually', 'event-image-manager' ); ?></h3>
        <p class="eim-2fa-manual-key">
            <span class="screen-reader-text"><?php esc_html_e( 'Manual entry key:', 'event-image-manager' ); ?></span>
            <code id="eim-2fa-secret"><?php echo esc_html( $secret ); ?></code>
            <button type="button" id="eim-2fa-copy-secret" class="button button-small"
                    data-clipboard-target="#eim-2fa-secret">
                <?php esc_html_e( 'Copy', 'event-image-manager' ); ?>
            </button>
        </p>
    </section>

    <!-- Step 3: Verify -->
    <section class="eim-2fa-section">
        <h3><?php esc_html_e( 'Step 3: Verify Setup', 'event-image-manager' ); ?></h3>
        <form id="eim-2fa-verify-form" novalidate>
            <?php wp_nonce_field( 'eim_2fa_nonce', 'eim_2fa_nonce' ); ?>
            <input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">

            <p>
                <label for="eim-2fa-code">
                    <?php esc_html_e( 'Verification Code:', 'event-image-manager' ); ?>
                </label>
                <input type="text" id="eim-2fa-code" name="code"
                       class="regular-text" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" autocomplete="one-time-code"
                       placeholder="<?php esc_attr_e( '6-digit code', 'event-image-manager' ); ?>"
                       required>
            </p>

            <div class="eim-2fa-actions">
                <?php if ( ! $is_enabled ) : ?>
                <button type="submit" name="eim_2fa_action" value="enable" class="button button-primary">
                    <?php esc_html_e( 'Enable 2FA', 'event-image-manager' ); ?>
                </button>
                <?php else : ?>
                <button type="submit" name="eim_2fa_action" value="disable" class="button">
                    <?php esc_html_e( 'Disable 2FA', 'event-image-manager' ); ?>
                </button>
                <?php endif; ?>
            </div>

            <p class="eim-2fa-status" aria-live="polite"></p>
        </form>
    </section>

    <!-- Backup codes -->
    <?php if ( ! empty( $backup_codes ) ) : ?>
    <section class="eim-2fa-section">
        <h3><?php esc_html_e( 'Backup Codes', 'event-image-manager' ); ?></h3>
        <p><?php esc_html_e( 'Save these one-time backup codes in a safe place. Each code can only be used once.', 'event-image-manager' ); ?></p>
        <ul class="eim-backup-codes" aria-label="<?php esc_attr_e( 'Backup codes list', 'event-image-manager' ); ?>">
            <?php foreach ( $backup_codes as $code ) : ?>
            <li><code><?php echo esc_html( $code ); ?></code></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" id="eim-print-backup-codes" class="button button-small">
            <?php esc_html_e( 'Print Backup Codes', 'event-image-manager' ); ?>
        </button>
    </section>
    <?php endif; ?>
</div>

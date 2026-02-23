<?php
/**
 * Class EIM_Two_Factor_Auth
 *
 * TOTP-based two-factor authentication for WordPress users.
 *
 * - Users can enable/verify/disable 2FA from their account.
 * - Secrets and backup codes are stored in user meta and the
 *   `{prefix}eim_2fa_backup_codes` database table.
 * - The login flow is intercepted via the `wp_authenticate_user` filter.
 */
class EIM_Two_Factor_Auth {

    /** User meta key for the TOTP secret. */
    const SECRET_META    = 'eim_2fa_secret';

    /** User meta key indicating 2FA is fully enabled. */
    const ENABLED_META   = 'eim_2fa_enabled';

    /** Transient prefix for pending 2FA verification. */
    const PENDING_PREFIX = 'eim_2fa_pending_';

    public function __construct() {
        add_action( 'wp_ajax_eim_2fa_setup',   array( $this, 'ajax_setup' ) );
        add_action( 'wp_ajax_eim_2fa_verify',  array( $this, 'ajax_verify' ) );
        add_action( 'wp_ajax_eim_2fa_disable', array( $this, 'ajax_disable' ) );
        add_filter( 'wp_authenticate_user',    array( $this, 'intercept_login' ), 10, 2 );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: generate and return a new TOTP secret for setup. */
    public function ajax_setup() {
        check_ajax_referer( 'eim_2fa_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'event-image-manager' ) ) );
        }

        $secret = $this->generate_secret();
        update_user_meta( $user_id, self::SECRET_META, $secret );

        $user       = get_userdata( $user_id );
        $issuer     = rawurlencode( get_bloginfo( 'name' ) );
        $account    = rawurlencode( $user->user_email );
        $otpauth    = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}";

        wp_send_json_success( array(
            'secret'  => $secret,
            'otpauth' => $otpauth,
        ) );
    }

    /** AJAX: verify a TOTP code and enable 2FA if correct. */
    public function ajax_verify() {
        check_ajax_referer( 'eim_2fa_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

        if ( ! $user_id || '' === $code ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $secret = $this->get_user_secret( $user_id );
        if ( ! $secret || ! $this->verify_totp( $secret, $code ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid code. Please try again.', 'event-image-manager' ) ) );
        }

        update_user_meta( $user_id, self::ENABLED_META, 1 );

        wp_send_json_success( array( 'message' => __( 'Two-factor authentication enabled.', 'event-image-manager' ) ) );
    }

    /** AJAX: disable 2FA for the current user. */
    public function ajax_disable() {
        check_ajax_referer( 'eim_2fa_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'event-image-manager' ) ) );
        }

        delete_user_meta( $user_id, self::SECRET_META );
        delete_user_meta( $user_id, self::ENABLED_META );

        wp_send_json_success( array( 'message' => __( 'Two-factor authentication disabled.', 'event-image-manager' ) ) );
    }

    // -------------------------------------------------------------------------
    // Login filter
    // -------------------------------------------------------------------------

    /**
     * Intercept normal login and require a TOTP code if 2FA is enabled.
     * Uses a short-lived transient to carry the user ID to the verification step.
     *
     * @param  \WP_User|\WP_Error $user     User or error from previous auth.
     * @param  string             $password Password supplied (unused here).
     * @return \WP_User|\WP_Error
     */
    public function intercept_login( $user, $password ) {
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        if ( ! get_user_meta( $user->ID, self::ENABLED_META, true ) ) {
            return $user;
        }

        // Store pending user and send back a soft error to trigger the 2FA form.
        $token = wp_generate_password( 32, false );
        set_transient( self::PENDING_PREFIX . $token, $user->ID, 5 * MINUTE_IN_SECONDS );

        $error = new WP_Error(
            'eim_2fa_required',
            __( 'Please enter your two-factor authentication code.', 'event-image-manager' ),
            array( 'token' => $token )
        );

        return $error;
    }

    // -------------------------------------------------------------------------
    // TOTP helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a cryptographically random Base32-encoded TOTP secret.
     *
     * @return string 16-character Base32 secret.
     */
    public function generate_secret() {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ( $i = 0; $i < 16; $i++ ) {
            $secret .= $chars[ random_int( 0, 31 ) ];
        }
        return $secret;
    }

    /**
     * Verify a 6-digit TOTP code against a Base32 secret.
     * Allows a ±1 time-step window to account for clock drift.
     *
     * @param  string $secret Base32-encoded TOTP secret.
     * @param  string $code   6-digit code entered by the user.
     * @return bool
     */
    public function verify_totp( $secret, $code ) {
        $timestamp = (int) floor( time() / 30 );

        for ( $offset = -1; $offset <= 1; $offset++ ) {
            $expected = $this->generate_totp_code( $secret, $timestamp + $offset );
            if ( hash_equals( (string) $expected, (string) $code ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for a given time counter.
     *
     * @param  string $secret    Base32 secret.
     * @param  int    $counter   Time counter (floor(time/30)).
     * @return string            Zero-padded 6-digit code.
     */
    private function generate_totp_code( $secret, $counter ) {
        $key     = $this->base32_decode( $secret );
        $message = pack( 'N*', 0 ) . pack( 'N*', $counter );
        $hash    = hash_hmac( 'sha1', $message, $key, true );
        $offset  = ord( $hash[19] ) & 0x0f;
        $otp     = ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 )
                 | ( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 )
                 | ( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 )
                 |   ( ord( $hash[ $offset + 3 ] ) & 0xff );
        return str_pad( (string) ( $otp % 1000000 ), 6, '0', STR_PAD_LEFT );
    }

    /**
     * Decode a Base32-encoded string to binary.
     *
     * @param  string $input Base32 string.
     * @return string        Binary string.
     */
    private function base32_decode( $input ) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input    = strtoupper( $input );
        $output   = '';
        $buffer   = 0;
        $bits     = 0;

        for ( $i = 0, $len = strlen( $input ); $i < $len; $i++ ) {
            $pos = strpos( $alphabet, $input[ $i ] );
            if ( false === $pos ) {
                continue;
            }
            $buffer = ( $buffer << 5 ) | $pos;
            $bits  += 5;
            if ( $bits >= 8 ) {
                $bits  -= 8;
                $output .= chr( ( $buffer >> $bits ) & 0xff );
            }
        }

        return $output;
    }

    // -------------------------------------------------------------------------
    // User meta helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve the stored TOTP secret for a user.
     *
     * @param  int         $user_id WordPress user ID.
     * @return string|false         Secret string or false if not set.
     */
    public function get_user_secret( $user_id ) {
        return get_user_meta( absint( $user_id ), self::SECRET_META, true ) ?: false;
    }

    /**
     * Persist a TOTP secret for a user.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $secret  Base32 secret to store.
     */
    public function save_user_secret( $user_id, $secret ) {
        update_user_meta( absint( $user_id ), self::SECRET_META, sanitize_text_field( $secret ) );
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the backup codes table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_2fa_backup_codes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL,
            code_hash  VARCHAR(255) NOT NULL,
            is_used    TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

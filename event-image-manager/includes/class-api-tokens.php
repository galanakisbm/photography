<?php
/**
 * Class EIM_API_Tokens
 *
 * Generate and manage API tokens for external integrations.
 *
 * - Tokens are hashed before storage; only the plain-text token is returned
 *   at creation time.
 * - `eim_generate_api_token` : create a new token.
 * - `eim_revoke_api_token`   : revoke an existing token by ID.
 * - `eim_list_api_tokens`    : list the current user's active tokens.
 * - Token records stored in `{prefix}eim_api_tokens`.
 */
class EIM_API_Tokens {

    public function __construct() {
        add_action( 'wp_ajax_eim_generate_api_token', array( $this, 'ajax_generate_token' ) );
        add_action( 'wp_ajax_eim_revoke_api_token',   array( $this, 'ajax_revoke_token' ) );
        add_action( 'wp_ajax_eim_list_api_tokens',    array( $this, 'ajax_list_tokens' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: generate a new API token for the current user. */
    public function ajax_generate_token() {
        check_ajax_referer( 'eim_api_tokens_nonce', 'nonce' );

        $user_id     = get_current_user_id();
        $name        = isset( $_POST['name'] )        ? sanitize_text_field( wp_unslash( $_POST['name'] ) )        : '';
        $permissions = isset( $_POST['permissions'] ) ? sanitize_text_field( wp_unslash( $_POST['permissions'] ) ) : '[]';

        if ( ! $user_id || '' === $name ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        $plain_token = $this->generate_token( $user_id, $name, $permissions );

        if ( ! $plain_token ) {
            wp_send_json_error( array( 'message' => __( 'Could not generate token.', 'event-image-manager' ) ) );
        }

        wp_send_json_success( array(
            'token'   => $plain_token,
            'message' => __( 'Token generated. Copy it now — it will not be shown again.', 'event-image-manager' ),
        ) );
    }

    /** AJAX: revoke a token by its row ID. */
    public function ajax_revoke_token() {
        check_ajax_referer( 'eim_api_tokens_nonce', 'nonce' );

        $token_id = isset( $_POST['token_id'] ) ? absint( $_POST['token_id'] ) : 0;
        $user_id  = get_current_user_id();

        if ( ! $token_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_api_tokens';

        // Ensure the token belongs to the current user.
        $owner = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT user_id FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $token_id
            )
        );

        if ( (int) $owner !== $user_id && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $this->revoke_token( $token_id );

        wp_send_json_success( array( 'message' => __( 'Token revoked.', 'event-image-manager' ) ) );
    }

    /** AJAX: list active (non-revoked) tokens for the current user. */
    public function ajax_list_tokens() {
        check_ajax_referer( 'eim_api_tokens_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'event-image-manager' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eim_api_tokens';

        $tokens = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT id, name, permissions, last_used, created_at FROM {$table} WHERE user_id = %d AND revoked_at IS NULL ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id
            )
        );

        wp_send_json_success( array( 'tokens' => $tokens ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Create a new API token for a user.
     *
     * @param  int    $user_id     WordPress user ID.
     * @param  string $name        Human-readable token name.
     * @param  string $permissions JSON-encoded permissions array.
     * @return string|false        Plain-text token on success, false on failure.
     */
    public function generate_token( $user_id, $name, $permissions ) {
        $plain_token = wp_generate_password( 48, false );
        $token_hash  = wp_hash_password( $plain_token );

        global $wpdb;
        $table = $wpdb->prefix . 'eim_api_tokens';

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'user_id'     => absint( $user_id ),
                'name'        => sanitize_text_field( $name ),
                'token_hash'  => $token_hash,
                'permissions' => $permissions,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        return $result ? $plain_token : false;
    }

    /**
     * Validate a plain-text API token and return the owning user ID.
     *
     * @param  string   $token Plain-text token.
     * @return int|false       User ID on success, false on failure.
     */
    public function validate_token( $token ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_api_tokens';

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT id, user_id, token_hash FROM {$table} WHERE revoked_at IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        foreach ( $rows as $row ) {
            if ( wp_check_password( $token, $row->token_hash ) ) {
                // Update last_used timestamp.
                $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $table,
                    array( 'last_used' => current_time( 'mysql' ) ),
                    array( 'id' => $row->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                return (int) $row->user_id;
            }
        }

        return false;
    }

    /**
     * Revoke a token by its row ID.
     *
     * @param int $token_id Row ID.
     */
    public function revoke_token( $token_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_api_tokens';

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array( 'revoked_at' => current_time( 'mysql' ) ),
            array( 'id' => absint( $token_id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the API tokens table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_api_tokens';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            name        VARCHAR(128) NOT NULL DEFAULT '',
            token_hash  VARCHAR(255) NOT NULL,
            permissions TEXT NOT NULL,
            last_used   DATETIME DEFAULT NULL,
            created_at  DATETIME NOT NULL,
            revoked_at  DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

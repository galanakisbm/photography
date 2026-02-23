<?php
/**
 * Class EIM_IP_Whitelist
 *
 * Optional IP address restrictions, configurable per-user or globally.
 *
 * - Global whitelist stored in option `eim_global_ip_whitelist` (comma-separated CIDRs/IPs).
 * - Per-user whitelist stored in user meta `eim_ip_whitelist`.
 * - `eim_add_ip_whitelist`    : add an IP to the current user's whitelist.
 * - `eim_remove_ip_whitelist` : remove an IP from the current user's whitelist.
 * - `eim_get_ip_whitelist`    : retrieve the current user's whitelist.
 */
class EIM_IP_Whitelist {

    /** User meta key for per-user IP whitelist (comma-separated). */
    const USER_META_KEY = 'eim_ip_whitelist';

    /** Options key for the global IP whitelist. */
    const GLOBAL_OPTION = 'eim_global_ip_whitelist';

    public function __construct() {
        add_action( 'wp_ajax_eim_add_ip_whitelist',    array( $this, 'ajax_add' ) );
        add_action( 'wp_ajax_eim_remove_ip_whitelist', array( $this, 'ajax_remove' ) );
        add_action( 'wp_ajax_eim_get_ip_whitelist',    array( $this, 'ajax_get' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: add an IP address to the current user's whitelist. */
    public function ajax_add() {
        check_ajax_referer( 'eim_ip_whitelist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : get_current_user_id();
        $ip      = isset( $_POST['ip'] )      ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';

        if ( ! $ip ) {
            wp_send_json_error( array( 'message' => __( 'Invalid IP address.', 'event-image-manager' ) ) );
        }

        $current = $this->get_user_list( $user_id );
        if ( ! in_array( $ip, $current, true ) ) {
            $current[] = $ip;
            $this->save_user_list( $user_id, $current );
        }

        wp_send_json_success( array( 'whitelist' => $current ) );
    }

    /** AJAX: remove an IP address from the current user's whitelist. */
    public function ajax_remove() {
        check_ajax_referer( 'eim_ip_whitelist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : get_current_user_id();
        $ip      = isset( $_POST['ip'] )      ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';

        if ( ! $ip ) {
            wp_send_json_error( array( 'message' => __( 'Invalid IP address.', 'event-image-manager' ) ) );
        }

        $current = array_filter( $this->get_user_list( $user_id ), function( $entry ) use ( $ip ) {
            return $entry !== $ip;
        } );

        $this->save_user_list( $user_id, array_values( $current ) );

        wp_send_json_success( array( 'whitelist' => array_values( $current ) ) );
    }

    /** AJAX: retrieve the whitelist for a user. */
    public function ajax_get() {
        check_ajax_referer( 'eim_ip_whitelist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'event-image-manager' ) ) );
        }

        $user_id  = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : get_current_user_id();
        $list     = $this->get_user_list( $user_id );
        $global   = $this->get_global_list();

        wp_send_json_success( array(
            'user_whitelist'   => $list,
            'global_whitelist' => $global,
        ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether an IP is allowed for a given user.
     * If neither global nor user lists are configured, all IPs are allowed.
     *
     * @param  string $ip      IP address to check.
     * @param  int    $user_id WordPress user ID (0 = guest).
     * @return bool            True if allowed.
     */
    public function is_allowed( $ip, $user_id = 0 ) {
        $global = $this->get_global_list();
        $user   = $user_id ? $this->get_user_list( $user_id ) : array();

        $combined = array_unique( array_merge( $global, $user ) );

        if ( empty( $combined ) ) {
            return true;
        }

        foreach ( $combined as $allowed ) {
            if ( $this->ip_matches( $ip, $allowed ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate the current HTTP request's IP against the active whitelists.
     *
     * @return bool True if the request IP is allowed.
     */
    public function check_current_request() {
        $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $user_id = get_current_user_id();
        return $this->is_allowed( $ip, $user_id );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether an IP matches an entry (exact or simple CIDR /24).
     *
     * @param  string $ip    IP to check.
     * @param  string $entry Allowed IP or CIDR.
     * @return bool
     */
    private function ip_matches( $ip, $entry ) {
        if ( false !== strpos( $entry, '/' ) ) {
            list( $range_ip, $prefix ) = explode( '/', $entry, 2 );
            $prefix = (int) $prefix;
            $range_long = ip2long( $range_ip );
            $ip_long    = ip2long( $ip );
            if ( false === $range_long || false === $ip_long ) {
                return false;
            }
            $mask = -1 << ( 32 - $prefix );
            return ( $ip_long & $mask ) === ( $range_long & $mask );
        }
        return $ip === $entry;
    }

    /**
     * @param  int   $user_id
     * @return array
     */
    private function get_user_list( $user_id ) {
        $raw = get_user_meta( absint( $user_id ), self::USER_META_KEY, true );
        return is_array( $raw ) ? $raw : array();
    }

    /**
     * @param int   $user_id
     * @param array $list
     */
    private function save_user_list( $user_id, $list ) {
        update_user_meta( absint( $user_id ), self::USER_META_KEY, $list );
    }

    /**
     * @return array
     */
    private function get_global_list() {
        $raw = get_option( self::GLOBAL_OPTION, '' );
        if ( ! $raw ) {
            return array();
        }
        return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    }
}

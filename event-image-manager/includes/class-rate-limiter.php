<?php
/**
 * Class EIM_Rate_Limiter
 *
 * Configurable rate limiting for various plugin actions using WordPress transients.
 *
 * Usage:
 *   $limiter = new EIM_Rate_Limiter();
 *   if ( ! $limiter->check( 'login', $ip, EIM_Rate_Limiter::LOGIN_LIMIT, EIM_Rate_Limiter::LOGIN_WINDOW ) ) {
 *       // rate limit exceeded
 *   }
 */
class EIM_Rate_Limiter {

    /** Maximum login attempts per window. */
    const LOGIN_LIMIT = 5;

    /** Login rate-limit window in seconds (5 minutes). */
    const LOGIN_WINDOW = 300;

    /** Maximum API requests per window. */
    const API_LIMIT = 100;

    /** API rate-limit window in seconds (1 hour). */
    const API_WINDOW = 3600;

    /** Transient key prefix. */
    const KEY_PREFIX = 'eim_rl_';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether the given identifier is within the allowed rate limit.
     * Also increments the counter if within limit.
     *
     * @param  string $action     Action slug (e.g. 'login', 'api_request').
     * @param  string $identifier Unique identifier (e.g. IP address or user ID).
     * @param  int    $limit      Maximum number of occurrences allowed.
     * @param  int    $window     Time window in seconds.
     * @return bool               True if within limit, false if exceeded.
     */
    public function check( $action, $identifier, $limit, $window ) {
        $key   = $this->transient_key( $action, $identifier );
        $count = (int) get_transient( $key );

        if ( $count >= $limit ) {
            return false;
        }

        $this->increment( $action, $identifier, $window );
        return true;
    }

    /**
     * Increment the hit counter for an action/identifier pair.
     * Uses a dedicated option key to preserve the original expiry window.
     *
     * @param string $action     Action slug.
     * @param string $identifier Unique identifier.
     * @param int    $window     Time window in seconds (used only when creating the entry).
     */
    public function increment( $action, $identifier, $window ) {
        $key   = $this->transient_key( $action, $identifier );
        $count = (int) get_transient( $key );

        if ( 0 === $count ) {
            // First hit: set with the full window TTL.
            set_transient( $key, 1, $window );
        } else {
            // Subsequent hits: update the value WITHOUT refreshing the TTL by
            // writing directly to the underlying option so the original expiry
            // is preserved.
            $option_name = '_transient_' . $key;
            $current     = get_option( $option_name );
            if ( false !== $current ) {
                update_option( $option_name, $count + 1, false );
            } else {
                // Fallback if the transient option is missing.
                set_transient( $key, $count + 1, $window );
            }
        }
    }

    /**
     * Reset the counter for an action/identifier pair.
     *
     * @param string $action     Action slug.
     * @param string $identifier Unique identifier.
     */
    public function reset( $action, $identifier ) {
        delete_transient( $this->transient_key( $action, $identifier ) );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a deterministic transient key.
     *
     * @param  string $action     Action slug.
     * @param  string $identifier Unique identifier.
     * @return string             Transient key (max 172 chars).
     */
    private function transient_key( $action, $identifier ) {
        return self::KEY_PREFIX . sanitize_key( $action ) . '_' . md5( $identifier );
    }
}

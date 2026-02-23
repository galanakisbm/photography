<?php
/**
 * Class EIM_Pricing_Plans
 *
 * Subscription plan management for the Event Image Manager plugin.
 *
 * Plans: Free, Basic, Pro, Enterprise.
 * Each plan defines: name, price (USD/month), download_limit (-1 = unlimited),
 * watermark_free (bool), and a features array.
 *
 * The user's active plan is stored in user meta `eim_pricing_plan`.
 */
class EIM_Pricing_Plans {

    /** User meta key for the active plan. */
    const META_KEY = 'eim_pricing_plan';

    /** Plan definitions. */
    const PLANS = array(
        'free'       => array(
            'name'           => 'Free',
            'price'          => 0,
            'download_limit' => 5,
            'watermark_free' => false,
            'features'       => array( 'Gallery view', '5 downloads/month' ),
        ),
        'basic'      => array(
            'name'           => 'Basic',
            'price'          => 9,
            'download_limit' => 50,
            'watermark_free' => false,
            'features'       => array( 'Gallery view', '50 downloads/month', 'Email notifications' ),
        ),
        'pro'        => array(
            'name'           => 'Pro',
            'price'          => 29,
            'download_limit' => 500,
            'watermark_free' => true,
            'features'       => array( 'Gallery view', '500 downloads/month', 'Watermark-free', 'Priority support' ),
        ),
        'enterprise' => array(
            'name'           => 'Enterprise',
            'price'          => 99,
            'download_limit' => -1,
            'watermark_free' => true,
            'features'       => array( 'Gallery view', 'Unlimited downloads', 'Watermark-free', 'API access', 'Dedicated support' ),
        ),
    );

    public function __construct() {
        add_action( 'wp_ajax_eim_get_plan_info',        array( $this, 'ajax_get_plan_info' ) );
        add_action( 'wp_ajax_nopriv_eim_get_plan_info', array( $this, 'ajax_get_plan_info' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /** AJAX: return details for a specific plan or all plans. */
    public function ajax_get_plan_info() {
        check_ajax_referer( 'eim_plans_nonce', 'nonce' );

        $plan_name = isset( $_GET['plan'] ) ? sanitize_text_field( wp_unslash( $_GET['plan'] ) ) : '';

        if ( $plan_name ) {
            $plan = $this->get_plan( $plan_name );
            if ( ! $plan ) {
                wp_send_json_error( array( 'message' => __( 'Plan not found.', 'event-image-manager' ) ) );
            }
            wp_send_json_success( array( 'plan' => $plan ) );
        } else {
            wp_send_json_success( array( 'plans' => self::PLANS ) );
        }
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return plan details by name.
     *
     * @param  string     $plan_name Plan slug (free|basic|pro|enterprise).
     * @return array|null             Plan array or null.
     */
    public function get_plan( $plan_name ) {
        return isset( self::PLANS[ $plan_name ] ) ? self::PLANS[ $plan_name ] : null;
    }

    /**
     * Return the active plan for a user.
     *
     * @param  int   $user_id WordPress user ID.
     * @return array           Plan details array (defaults to 'free').
     */
    public function get_user_plan( $user_id ) {
        $plan_name = get_user_meta( absint( $user_id ), self::META_KEY, true );
        if ( ! $plan_name || ! isset( self::PLANS[ $plan_name ] ) ) {
            $plan_name = 'free';
        }
        return array_merge( array( 'slug' => $plan_name ), self::PLANS[ $plan_name ] );
    }

    /**
     * Check whether the user has remaining downloads this month.
     *
     * @param  int  $user_id WordPress user ID.
     * @return bool          True if the user can still download.
     */
    public function check_download_limit( $user_id ) {
        $plan  = $this->get_user_plan( $user_id );
        $limit = (int) $plan['download_limit'];

        if ( -1 === $limit ) {
            return true;
        }

        // Count downloads this calendar month from the download logs table.
        global $wpdb;
        $table = $wpdb->prefix . 'event_download_logs';
        $month = current_time( 'Y-m' );

        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND downloaded_at LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $user_id ),
                $wpdb->esc_like( $month ) . '%'
            )
        );

        return $count < $limit;
    }
}

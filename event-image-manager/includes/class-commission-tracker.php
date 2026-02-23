<?php
/**
 * Class EIM_Commission_Tracker
 *
 * Tracks revenue and commissions per photographer and event.
 *
 * Records stored in `{prefix}eim_commissions`.
 */
class EIM_Commission_Tracker {

    public function __construct() {
        // Intentionally empty — commission recording is triggered programmatically.
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Record a sale and calculate the photographer's commission.
     *
     * @param int    $photographer_id WordPress user ID of the photographer.
     * @param int    $post_id         Event post ID.
     * @param float  $amount          Sale amount.
     * @param float  $commission_rate Commission rate as a decimal (e.g. 0.30 = 30 %).
     * @return int|false              Inserted row ID or false.
     */
    public function record_sale( $photographer_id, $post_id, $amount, $commission_rate ) {
        global $wpdb;
        $table             = $wpdb->prefix . 'eim_commissions';
        $commission_amount = round( (float) $amount * (float) $commission_rate, 2 );

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'photographer_id'   => absint( $photographer_id ),
                'post_id'           => absint( $post_id ),
                'order_id'          => 0,
                'amount'            => round( (float) $amount, 2 ),
                'commission_rate'   => (float) $commission_rate,
                'commission_amount' => $commission_amount,
                'status'            => 'pending',
                'created_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Return total earnings for a photographer within a date range.
     *
     * @param int    $photographer_id WordPress user ID.
     * @param string $from            MySQL date string (YYYY-MM-DD).
     * @param string $to              MySQL date string (YYYY-MM-DD).
     * @return float                   Total commission amount.
     */
    public function get_photographer_earnings( $photographer_id, $from, $to ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_commissions';

        return (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT SUM(commission_amount) FROM {$table}
                 WHERE photographer_id = %d
                   AND created_at >= %s
                   AND created_at <= %s
                   AND status != 'cancelled'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $photographer_id ),
                sanitize_text_field( $from ),
                sanitize_text_field( $to )
            )
        );
    }

    /**
     * Return total revenue for an event.
     *
     * @param  int   $post_id Event post ID.
     * @return float          Total sale amount.
     */
    public function get_event_revenue( $post_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_commissions';

        return (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$table} WHERE post_id = %d AND status != 'cancelled'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $post_id )
            )
        );
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the commissions table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_commissions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            photographer_id   BIGINT UNSIGNED NOT NULL,
            post_id           BIGINT UNSIGNED NOT NULL,
            order_id          BIGINT UNSIGNED NOT NULL DEFAULT 0,
            amount            DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            commission_rate   DECIMAL(5,4) NOT NULL DEFAULT '0.0000',
            commission_amount DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            status            VARCHAR(32) NOT NULL DEFAULT 'pending',
            created_at        DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY photographer_id (photographer_id),
            KEY post_id         (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

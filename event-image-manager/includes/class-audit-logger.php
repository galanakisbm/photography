<?php
/**
 * Class EIM_Audit_Logger
 *
 * Records all important plugin actions to the `{prefix}eim_audit_log` table.
 *
 * Common action slugs:
 *   login, logout, image_download, image_delete, event_create, event_delete,
 *   settings_change, payment, 2fa_enabled, 2fa_disabled, api_token_created, etc.
 */
class EIM_Audit_Logger {

    // -------------------------------------------------------------------------
    // Public API (static for convenience)
    // -------------------------------------------------------------------------

    /**
     * Log an audit event.
     *
     * @param string $action      Action slug (e.g. 'image_download').
     * @param string $object_type Object type (e.g. 'post', 'user', 'token').
     * @param int    $object_id   Object identifier.
     * @param array  $data        Additional structured data stored as JSON.
     */
    public static function log( $action, $object_type = '', $object_id = 0, $data = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'eim_audit_log';

        $ip         = isset( $_SERVER['REMOTE_ADDR'] )     ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )     : '';
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            array(
                'user_id'     => get_current_user_id(),
                'action'      => sanitize_text_field( $action ),
                'object_type' => sanitize_text_field( $object_type ),
                'object_id'   => absint( $object_id ),
                'data'        => wp_json_encode( $data ),
                'ip_address'  => $ip,
                'user_agent'  => $user_agent,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Query audit log entries with optional filters.
     *
     * @param array $filters Associative array: action, object_type, object_id, user_id, from_date, to_date.
     * @param int   $limit   Maximum rows to return.
     * @param int   $offset  Pagination offset.
     * @return array          Array of row objects.
     */
    public static function get_logs( $filters = array(), $limit = 50, $offset = 0 ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'eim_audit_log';
        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $filters['action'] ) ) {
            $where[]  = 'action = %s';
            $params[] = sanitize_text_field( $filters['action'] );
        }

        if ( ! empty( $filters['object_type'] ) ) {
            $where[]  = 'object_type = %s';
            $params[] = sanitize_text_field( $filters['object_type'] );
        }

        if ( ! empty( $filters['object_id'] ) ) {
            $where[]  = 'object_id = %d';
            $params[] = absint( $filters['object_id'] );
        }

        if ( ! empty( $filters['user_id'] ) ) {
            $where[]  = 'user_id = %d';
            $params[] = absint( $filters['user_id'] );
        }

        if ( ! empty( $filters['from_date'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = sanitize_text_field( $filters['from_date'] );
        }

        if ( ! empty( $filters['to_date'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = sanitize_text_field( $filters['to_date'] );
        }

        $where_sql = implode( ' AND ', $where );
        $params[]  = absint( $limit );
        $params[]  = absint( $offset );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                ...$params
            )
        );
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /** Create the audit log table on plugin activation. */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'eim_audit_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action      VARCHAR(64) NOT NULL DEFAULT '',
            object_type VARCHAR(64) NOT NULL DEFAULT '',
            object_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            data        TEXT NOT NULL,
            ip_address  VARCHAR(45) NOT NULL DEFAULT '',
            user_agent  VARCHAR(255) NOT NULL DEFAULT '',
            created_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id    (user_id),
            KEY action     (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

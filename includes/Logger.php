<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaLogger {

    const TABLE = 'mpwa_send_logs';
    private static $table_checked = false;

    /** Ensure table exists — runs once per request */
    private static function ensure_table() {
        if ( self::$table_checked ) return;
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
            self::create_table();
        }
        self::$table_checked = true;
    }

    public static function create_table() {
        global $wpdb;
        $table           = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            recipient   VARCHAR(30)         NOT NULL DEFAULT '',
            event_type  VARCHAR(40)         NOT NULL DEFAULT '',
            message     TEXT                NOT NULL,
            status      VARCHAR(20)         NOT NULL DEFAULT 'pending',
            http_code   SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
            api_response TEXT               NOT NULL,
            error_msg   TEXT                NOT NULL,
            created_at  DATETIME            NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function write( array $data ) {
        self::ensure_table();
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'order_id'     => absint( $data['order_id']     ?? 0 ),
                'recipient'    => sanitize_text_field( $data['recipient']    ?? '' ),
                'event_type'   => sanitize_text_field( $data['event_type']   ?? '' ),
                'message'      => sanitize_textarea_field( $data['message']  ?? '' ),
                'status'       => sanitize_text_field( $data['status']       ?? 'unknown' ),
                'http_code'    => absint( $data['http_code']    ?? 0 ),
                'api_response' => sanitize_textarea_field( $data['api_response'] ?? '' ),
                'error_msg'    => sanitize_text_field( $data['error_msg']    ?? '' ),
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );
        return $wpdb->insert_id;
    }

    public static function get_logs( $limit = 100, $offset = 0 ) {
        self::ensure_table();
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset )
        );
    }

    public static function get_log_by_id( $id ) {
        self::ensure_table();
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
    }

    public static function count_total()   { return self::count_where(); }
    public static function count_success() { return self::count_where( "status = 'success'" ); }
    public static function count_failed()  { return self::count_where( "status IN ('failed','error')" ); }

    private static function count_where( $where = '' ) {
        self::ensure_table();
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $sql   = "SELECT COUNT(*) FROM {$table}" . ( $where ? " WHERE {$where}" : '' );
        return (int) $wpdb->get_var( $sql );
    }

    public static function clear_logs() {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::TABLE );
    }
}

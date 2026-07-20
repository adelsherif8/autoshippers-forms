<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* Analytics event store — same pattern as contact-form-ghl / sleep-apnea-ghl:
   one row per view / start / step / complete milestone, deduped in the queries
   (COUNT DISTINCT session_id), never at insert time. */
class AS_Events {

    const DB_VERSION = '1.1'; /* 1.1: added page_url so analytics can filter by landing page */
    const OPTION_KEY = 'as_events_db_version';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'as_events';
    }

    public static function maybe_create_table(): void {
        if ( get_option( self::OPTION_KEY ) === self::DB_VERSION ) return;
        self::create_table();
    }

    public static function create_table(): void {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        /* No IF NOT EXISTS: dbDelta's parser needs plain CREATE TABLE to diff
           the schema and add new columns (page_url) to existing installs. */
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type  VARCHAR(20)  NOT NULL DEFAULT '',
            step_key    VARCHAR(100) NOT NULL DEFAULT '',
            session_id  VARCHAR(64)  NOT NULL DEFAULT '',
            page_url    VARCHAR(500) NOT NULL DEFAULT '',
            created_at  DATETIME     NOT NULL,
            PRIMARY KEY (id),
            KEY idx_created (created_at),
            KEY idx_event   (event_type),
            KEY idx_session (session_id)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( self::OPTION_KEY, self::DB_VERSION );
    }

    public static function insert( string $event_type, string $step_key, string $session_id, string $page_url = '' ): void {
        global $wpdb;
        if ( get_option( self::OPTION_KEY ) !== self::DB_VERSION ) {
            self::create_table();
        }
        $wpdb->insert( self::table_name(), [
            'event_type' => $event_type,
            'step_key'   => substr( $step_key, 0, 100 ),
            'session_id' => substr( $session_id, 0, 64 ),
            'page_url'   => substr( $page_url, 0, 500 ),
            'created_at' => current_time( 'mysql' ),
        ] );
    }
}

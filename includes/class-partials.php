<?php
/**
 * Unfinished form fills — what someone typed before leaving without submitting.
 *
 * Deliberately internal: nothing here is sent to GoHighLevel, and nothing here
 * is a lead. It exists to show which step loses people and what they had
 * already entered, so the form can be fixed. Someone who did not press submit
 * has not consented to being contacted.
 *
 * One row per browser session, overwritten as they go. The row is marked
 * completed the moment the form is actually submitted, so the admin screen
 * only ever lists genuine abandonments. Rows are deleted after a set number of
 * days — keeping half-typed personal details indefinitely has no purpose.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class AS_Partials {

    const DB_VERSION = '1.0.0';
    const OPTION_KEY = 'as_partials_db_version';
    const KEEP_DAYS  = 60;

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'as_partials';
    }

    public static function maybe_create_table(): void {
        if ( get_option( self::OPTION_KEY ) === self::DB_VERSION ) return;
        self::create_table();
    }

    public static function create_table(): void {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id  VARCHAR(64)  NOT NULL DEFAULT '',
            step_key    VARCHAR(100) NOT NULL DEFAULT '',
            first_name  VARCHAR(100) NOT NULL DEFAULT '',
            last_name   VARCHAR(100) NOT NULL DEFAULT '',
            email       VARCHAR(190) NOT NULL DEFAULT '',
            phone       VARCHAR(60)  NOT NULL DEFAULT '',
            data        LONGTEXT     NULL,
            page_url    VARCHAR(500) NOT NULL DEFAULT '',
            completed   TINYINT(1)   NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL,
            updated_at  DATETIME     NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_session (session_id),
            KEY idx_completed (completed),
            KEY idx_updated (updated_at)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( self::OPTION_KEY, self::DB_VERSION );
    }

    /* The answers worth keeping. Anything not listed here is ignored, so a
       stray or renamed input can never quietly end up in the database. */
    public static function allowed_keys(): array {
        return [
            'move_type', 'pickup_date', 'from_city', 'from_other', 'to_city', 'to_other',
            'vehicle_year', 'vehicle_make', 'vehicle_model', 'vehicle_type', 'vehicle_status',
            'referral_source', 'referral_other', 'is_dealer', 'company_name',
            'first_name', 'last_name', 'email', 'phone',
        ];
    }

    /* Create or update this session's row. */
    public static function save( string $session_id, string $step_key, array $fields, string $page_url = '' ): void {
        global $wpdb;
        if ( $session_id === '' ) return;
        self::maybe_create_table();

        $now   = current_time( 'mysql' );
        $table = self::table_name();

        /* Never overwrite a finished fill: once submitted it is a real lead. */
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, completed, data FROM {$table} WHERE session_id = %s",
            $session_id
        ), ARRAY_A );
        if ( $existing && (int) $existing['completed'] === 1 ) return;

        /* Merge over what is already stored: a later step posts only what is on
           screen, and must not blank an answer given earlier. */
        $stored = [];
        if ( $existing && ! empty( $existing['data'] ) ) {
            $decoded = json_decode( $existing['data'], true );
            if ( is_array( $decoded ) ) $stored = $decoded;
        }
        foreach ( $fields as $k => $v ) {
            if ( $v !== '' ) $stored[ $k ] = $v;
        }

        $row = [
            'session_id' => substr( $session_id, 0, 64 ),
            'step_key'   => substr( $step_key, 0, 100 ),
            'first_name' => substr( $stored['first_name'] ?? '', 0, 100 ),
            'last_name'  => substr( $stored['last_name']  ?? '', 0, 100 ),
            'email'      => substr( $stored['email']      ?? '', 0, 190 ),
            'phone'      => substr( $stored['phone']      ?? '', 0, 60 ),
            'data'       => wp_json_encode( $stored ),
            'page_url'   => substr( $page_url, 0, 500 ),
            'updated_at' => $now,
        ];

        if ( $existing ) {
            $wpdb->update( $table, $row, [ 'id' => (int) $existing['id'] ] );
        } else {
            $row['created_at'] = $now;
            $row['completed']  = 0;
            $wpdb->insert( $table, $row );
            self::prune();
        }
    }

    /* Called when the form is actually submitted — this fill is no longer an
       abandonment, so it drops off the Unfinished screen. */
    public static function mark_completed( string $session_id ): void {
        global $wpdb;
        if ( $session_id === '' ) return;
        self::maybe_create_table();
        $wpdb->update(
            self::table_name(),
            [ 'completed' => 1, 'updated_at' => current_time( 'mysql' ) ],
            [ 'session_id' => substr( $session_id, 0, 64 ) ]
        );
    }

    /* Drop anything older than KEEP_DAYS, finished or not. */
    public static function prune(): void {
        global $wpdb;
        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-' . self::KEEP_DAYS . ' days', current_time( 'timestamp' ) ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM " . self::table_name() . " WHERE updated_at < %s",
            $cutoff
        ) );
    }

    public static function delete( int $id ): void {
        global $wpdb;
        $wpdb->delete( self::table_name(), [ 'id' => $id ], [ '%d' ] );
    }

    public static function delete_all(): void {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . self::table_name() );
    }

    /* Abandoned fills, newest first. */
    public static function unfinished( int $limit = 200 ): array {
        global $wpdb;
        self::maybe_create_table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table_name() . " WHERE completed = 0 ORDER BY updated_at DESC LIMIT %d",
            $limit
        ), ARRAY_A );
        return $rows ?: [];
    }

    public static function count_unfinished(): int {
        global $wpdb;
        self::maybe_create_table();
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE completed = 0" );
    }

    /* How many abandoned fills left a usable phone number or email. */
    public static function count_with_contact(): int {
        global $wpdb;
        self::maybe_create_table();
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::table_name() . " WHERE completed = 0 AND ( email <> '' OR phone <> '' )"
        );
    }
}

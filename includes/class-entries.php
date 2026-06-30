<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AS_Entries {

    const DB_VERSION  = '1.0';
    const OPTION_KEY  = 'as_entries_db_version';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'as_entries';
    }

    /* ── Create / upgrade table ── */
    public static function maybe_create_table(): void {
        if ( get_option( self::OPTION_KEY ) === self::DB_VERSION ) return;

        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name VARCHAR(100) NOT NULL DEFAULT '',
            last_name VARCHAR(100) NOT NULL DEFAULT '',
            email VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            tag VARCHAR(100) NOT NULL DEFAULT '',
            data LONGTEXT NOT NULL,
            ghl_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            ghl_message TEXT,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY email (email)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::OPTION_KEY, self::DB_VERSION );
    }

    public static function insert( array $row ): int {
        global $wpdb;
        $wpdb->insert(
            self::table_name(),
            [
                'first_name'  => $row['first_name']  ?? '',
                'last_name'   => $row['last_name']   ?? '',
                'email'       => $row['email']       ?? '',
                'phone'       => $row['phone']       ?? '',
                'tag'         => $row['tag']         ?? '',
                'data'        => wp_json_encode( $row['data'] ?? [] ),
                'ghl_status'  => $row['ghl_status']  ?? 'pending',
                'ghl_message' => $row['ghl_message'] ?? '',
                'ip'          => $row['ip']          ?? '',
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
        );
        return (int) $wpdb->insert_id;
    }

    public static function update_ghl_status( int $id, string $status, string $message = '', string $request = '', string $response = '', int $http = 0 ): void {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT data FROM " . self::table_name() . " WHERE id=%d", $id ), ARRAY_A );
        $data = $row ? ( json_decode( $row['data'], true ) ?: [] ) : [];
        $data['_ghl_request']  = $request;
        $data['_ghl_response'] = $response;
        $data['_ghl_http']     = $http;
        $wpdb->update(
            self::table_name(),
            [ 'ghl_status' => $status, 'ghl_message' => $message, 'data' => wp_json_encode( $data ) ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    public static function fetch( int $page = 1, int $per_page = 25 ): array {
        global $wpdb;
        $table   = self::table_name();
        $offset  = max( 0, ( $page - 1 ) * $per_page );
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        return [ 'rows' => $rows ?: [], 'total' => $total ];
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table_name() . " WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::table_name(), [ 'id' => $id ], [ '%d' ] );
    }
}

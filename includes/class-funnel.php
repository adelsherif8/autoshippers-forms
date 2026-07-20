<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* Funnel analytics — same engine as the CFT Group forms funnel. */
class AS_Funnel {

    const DB_VERSION = '1.0';
    const OPTION_KEY = 'as_funnel_db_version';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'as_funnel';
    }

    /* ── Create / upgrade table ── */
    public static function maybe_create_table(): void {
        if ( get_option( self::OPTION_KEY ) === self::DB_VERSION ) return;

        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(40) NOT NULL DEFAULT '',
            form_type VARCHAR(32) NOT NULL DEFAULT '',
            event VARCHAR(20) NOT NULL DEFAULT '',
            step_num SMALLINT NOT NULL DEFAULT 0,
            page_url VARCHAR(500) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            ua VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY form_type (form_type),
            KEY session_id (session_id),
            KEY event (event),
            KEY created_at (created_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::OPTION_KEY, self::DB_VERSION );
    }

    /* ── Record an event. De-dupes view/step events per session ── */
    public static function track( string $session_id, string $form_type, string $event, int $step_num = 0, string $page_url = '', string $ip = '', string $ua = '' ): void {
        if ( $session_id === '' || $form_type === '' || $event === '' ) return;

        global $wpdb;
        $table = self::table_name();

        /* Skip duplicate view/step events for same session — only count first reach */
        if ( in_array( $event, [ 'view', 'step' ], true ) ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE session_id=%s AND form_type=%s AND event=%s AND step_num=%d LIMIT 1",
                $session_id, $form_type, $event, $step_num
            ) );
            if ( $exists ) return;
        }

        $wpdb->insert( $table, [
            'session_id' => substr( $session_id, 0, 40 ),
            'form_type'  => substr( $form_type, 0, 32 ),
            'event'      => substr( $event, 0, 20 ),
            'step_num'   => $step_num,
            'page_url'   => substr( $page_url, 0, 500 ),
            'ip'         => substr( $ip, 0, 45 ),
            'ua'         => substr( $ua, 0, 255 ),
            'created_at' => current_time( 'mysql' ),
        ], [ '%s','%s','%s','%d','%s','%s','%s','%s' ] );
    }

    /* ── Funnel summary for a form type within a date range ──
       Returns: [ 'views' => N, 'steps' => [ 1 => N, 2 => N, ... ], 'submits' => N ] */
    public static function summary( string $form_type, string $start, string $end, string $page_url = '' ): array {
        global $wpdb;
        $table = self::table_name();

        $where = $wpdb->prepare( "form_type=%s AND created_at BETWEEN %s AND %s", $form_type, $start, $end );
        if ( $page_url !== '' ) {
            $where .= $wpdb->prepare( ' AND page_url=%s', $page_url );
        }

        $views = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM $table WHERE $where AND event='view'" );

        $step_rows = $wpdb->get_results( "SELECT step_num, COUNT(DISTINCT session_id) c FROM $table WHERE $where AND event='step' GROUP BY step_num ORDER BY step_num", ARRAY_A );
        $steps = [];
        foreach ( $step_rows ?: [] as $r ) $steps[ intval( $r['step_num'] ) ] = intval( $r['c'] );

        $submits = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM $table WHERE $where AND event='submit'" );

        return [ 'views' => $views, 'steps' => $steps, 'submits' => $submits ];
    }

    /* ── Drop-off summary — for each session that did NOT submit, find
       the last step they reached. Returns [ step_num => sessions_left ]. */
    public static function dropoffs( string $form_type, string $start, string $end, string $page_url = '' ): array {
        global $wpdb;
        $table = self::table_name();

        $page_clause = $page_url !== '' ? $wpdb->prepare( ' AND page_url=%s', $page_url ) : '';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT MAX(step_num) AS last_step
             FROM $table
             WHERE form_type=%s AND event='step' AND created_at BETWEEN %s AND %s $page_clause
               AND session_id NOT IN (
                 SELECT DISTINCT session_id FROM $table
                 WHERE form_type=%s AND event='submit' AND created_at BETWEEN %s AND %s $page_clause
               )
             GROUP BY session_id",
            $form_type, $start, $end, $form_type, $start, $end
        ), ARRAY_A );

        $out = [];
        foreach ( $rows ?: [] as $r ) {
            $s = intval( $r['last_step'] );
            $out[ $s ] = ( $out[ $s ] ?? 0 ) + 1;
        }
        ksort( $out );
        return $out;
    }

    /* ── List distinct page URLs that have seen events. Empty form_type
       means "across all forms". ── */
    public static function distinct_pages( string $form_type, string $start, string $end ): array {
        global $wpdb;
        $table = self::table_name();
        if ( $form_type !== '' ) {
            $rows = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT page_url FROM $table WHERE form_type=%s AND created_at BETWEEN %s AND %s AND page_url<>'' ORDER BY page_url",
                $form_type, $start, $end
            ) );
        } else {
            $rows = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT page_url FROM $table WHERE created_at BETWEEN %s AND %s AND page_url<>'' ORDER BY page_url",
                $start, $end
            ) );
        }
        return $rows ?: [];
    }
}

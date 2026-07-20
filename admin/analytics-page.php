<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════════
   ANALYTICS — funnel + per-day submissions + summary stats.
   Visual style copied 1:1 from sleep-apnea-ghl / contact-form-ghl;
   only the prefix, accent colour and step labels differ.
   ═══════════════════════════════════════════════════════════════ */

function as_analytics_resolve_range( $preset = '1m', $custom_from = '', $custom_to = '' ) {
    $today = current_time( 'Y-m-d' );
    $ranges = [
        'today'     => [ $today, $today, 'Today' ],
        'yesterday' => [ date( 'Y-m-d', strtotime( $today . ' -1 day' ) ), date( 'Y-m-d', strtotime( $today . ' -1 day' ) ), 'Yesterday' ],
        '1w'        => [ date( 'Y-m-d', strtotime( $today . ' -6 days' ) ),  $today, 'Last 7 days' ],
        '2w'        => [ date( 'Y-m-d', strtotime( $today . ' -13 days' ) ), $today, 'Last 14 days' ],
        '1m'        => [ date( 'Y-m-d', strtotime( $today . ' -29 days' ) ), $today, 'Last 30 days' ],
        '3m'        => [ date( 'Y-m-d', strtotime( $today . ' -89 days' ) ), $today, 'Last 90 days' ],
        '1y'        => [ date( 'Y-m-d', strtotime( $today . ' -364 days' ) ),$today, 'Last 12 months' ],
    ];
    if ( $preset === 'custom' && $custom_from && $custom_to ) {
        return [ 'from' => $custom_from, 'to' => $custom_to, 'label' => $custom_from . ' → ' . $custom_to, 'preset' => 'custom' ];
    }
    if ( ! isset( $ranges[ $preset ] ) ) $preset = '1m';
    $r = $ranges[ $preset ];
    return [ 'from' => $r[0], 'to' => $r[1], 'label' => $r[2], 'preset' => $preset ];
}

function as_render_analytics_tab() {
    $preset = sanitize_key( $_GET['as_range'] ?? '1m' );
    $from   = sanitize_text_field( $_GET['as_from'] ?? '' );
    $to     = sanitize_text_field( $_GET['as_to']   ?? '' );
    $range  = as_analytics_resolve_range( $preset, $from, $to );
    ?>
    <div class="wrap as-analytics">
        <h2 style="margin-top:0;">Analytics <span style="font-size:13px;font-weight:400;color:#94a3b8;">— <?= esc_html( $range['label'] ) ?></span></h2>
        <p style="color:#64748b;font-size:13px;margin-top:-6px;">Conversion funnel + submission volume for the vehicle shipping quote form.</p>

        <!-- Range picker -->
        <div class="as-an-range-bar">
            <span>Date range</span>
            <?php
            $base = admin_url( 'admin.php?page=as-analytics' );
            $presets = [ 'today'=>'Today','yesterday'=>'Yesterday','1w'=>'Last 7 days','2w'=>'Last 14 days','1m'=>'Last 30 days','3m'=>'Last 90 days','1y'=>'Last 12 months' ];
            foreach ( $presets as $pk => $pl ):
                $active = ( $range['preset'] === $pk );
                $url    = add_query_arg( 'as_range', $pk, $base );
            ?>
            <a href="<?= esc_url( $url ) ?>" class="<?= $active ? 'active' : '' ?>"><?= esc_html( $pl ) ?></a>
            <?php endforeach; ?>
        </div>
        <style>
        .as-an-range-bar{display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin:16px 0 22px;font-size:12px;}
        .as-an-range-bar > span:first-child{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-right:8px;}
        .as-an-range-bar a{font-size:11.5px;padding:5px 11px;border-radius:14px;color:#475569;border:1px solid #e5e7eb;background:#fff;text-decoration:none;font-weight:500;transition:all .12s;}
        .as-an-range-bar a:hover{background:#f8fafc;border-color:#cbd5e1;color:#1d2327;}
        .as-an-range-bar a.active{background:#ee7a00;color:#fff;border-color:#ee7a00;font-weight:600;}
        .as-an-card{background:#fff;border:1px solid #e8eaed;border-radius:12px;padding:22px 24px;margin-bottom:18px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
        .as-an-grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:18px;}
        .as-an-tile{background:#f8fafc;border-radius:10px;padding:16px;text-align:center;}
        .as-an-tile .num{font-size:26px;font-weight:700;color:#1d2327;line-height:1;}
        .as-an-tile .lbl{font-size:10px;font-weight:600;color:#9ca3af;margin-top:6px;text-transform:uppercase;letter-spacing:.07em;}
        .as-an-tile.hi .num{color:#ee7a00;}
        .as-src-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
        .as-src-row .lbl{width:340px;flex-shrink:0;font-size:12.5px;color:#374151;line-height:1.4;}
        .as-src-row .bar{flex:1;background:#f3f4f6;border-radius:6px;height:10px;overflow:hidden;}
        .as-src-row .fill{height:100%;border-radius:6px;background:#ee7a00;transition:width .3s;}
        .as-src-row .cnt{font-size:13px;font-weight:600;color:#1d2327;width:36px;text-align:right;flex-shrink:0;}
        .as-src-row.is-zero .lbl,.as-src-row.is-zero .cnt{color:#cbd5e1;}
        .as-src-row.is-zero .fill{opacity:.15;}
        .as-bar-chart{display:flex;align-items:flex-end;gap:2px;height:80px;margin-top:8px;}
        .as-bar-col{flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;min-width:0;}
        .as-bar-col .b{width:100%;background:#ee7a00;border-radius:3px 3px 0 0;min-height:4px;}
        .as-bar-col.zero .b{background:#e5e7eb;}
        </style>

        <?php
        global $wpdb;
        $ev_table  = $wpdb->prefix . 'as_events';
        $en_table  = $wpdb->prefix . 'as_entries';
        $ev_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$ev_table}'" ) === $ev_table;
        $an_from   = $range['from'];
        $an_to     = $range['to'];
        $an_where  = $wpdb->prepare( 'DATE(created_at) BETWEEN %s AND %s', $an_from, $an_to );

        // Actual submitted entries (source of truth for "completed")
        $entries_cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$en_table} WHERE {$an_where}" );
        $entries_all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$en_table}" );
        $entries_ok  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$en_table} WHERE ghl_status='sent' AND {$an_where}" );
        $entries_err = $entries_cnt - $entries_ok;

        // Funnel counts (unique sessions)
        $views    = 0; $starts = 0;
        $step_cnts = [];
        if ( $ev_exists ) {
            $views   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$ev_table} WHERE event_type='view'  AND {$an_where}" );
            $starts  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$ev_table} WHERE event_type='start' AND {$an_where}" );
            $rows    = $wpdb->get_results( "SELECT step_key, COUNT(DISTINCT session_id) AS cnt FROM {$ev_table} WHERE event_type='step' AND {$an_where} AND step_key<>'' GROUP BY step_key", ARRAY_A );
            foreach ( $rows as $r ) $step_cnts[ $r['step_key'] ] = (int) $r['cnt'];
        }

        // Canonical question order + friendly labels
        $canon = [
            'shipping' => 'Shipping details (route & date)',
            'vehicle'  => 'Vehicle details (type & status)',
            'contact'  => 'Contact form reached',
        ];
        $conv = $views > 0 ? round( $entries_cnt / $views * 100 ) : 0;

        // Daily submissions
        $days = max( 1, (int) round( ( strtotime( $an_to ) - strtotime( $an_from ) ) / 86400 ) + 1 );
        $daily = $wpdb->get_results( "SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM {$en_table} WHERE {$an_where} GROUP BY DATE(created_at)", ARRAY_A );
        $daily_map = [];
        foreach ( $daily as $r ) $daily_map[ $r['day'] ] = (int) $r['cnt'];
        $daily_filled = [];
        for ( $i = 0; $i < $days; $i++ ) {
            $d = date( 'Y-m-d', strtotime( "+{$i} days", strtotime( $an_from ) ) );
            $daily_filled[] = [ 'day' => date( 'M j', strtotime( $d ) ), 'cnt' => $daily_map[ $d ] ?? 0 ];
        }
        $daily_max = max( 1, max( array_column( $daily_filled, 'cnt' ) ) );
        ?>

        <?php if ( ! $ev_exists ): ?>
        <div class="as-an-card" style="background:#fffbeb;border-color:#fde68a;color:#92400e;">
            ⏳ Analytics event tracking table is being set up. Data will appear here after the first form view is recorded.
        </div>
        <?php else: ?>

        <!-- Summary tiles -->
        <div class="as-an-grid">
            <div class="as-an-tile"><div class="num"><?= $views ?></div><div class="lbl">Unique visitors</div></div>
            <div class="as-an-tile"><div class="num"><?= $starts ?></div><div class="lbl">Started the form</div></div>
            <div class="as-an-tile hi"><div class="num"><?= $entries_cnt ?></div><div class="lbl">Submitted</div></div>
            <div class="as-an-tile"><div class="num"><?= $conv ?>%</div><div class="lbl">Views → submit</div></div>
        </div>

        <!-- Funnel -->
        <div class="as-an-card">
            <div style="font-size:15px;font-weight:700;color:#ee7a00;margin-bottom:3px;">Funnel — Vehicle Shipping Quote</div>
            <div style="font-size:11.5px;color:#9ca3af;margin-bottom:16px;">How each visitor moves from reaching the page to submitting the form. <?= esc_html( $range['label'] ) ?>.</div>
            <?php
            $funnel_rows = [
                [ 'lbl' => 'Reached the vehicle shipping quote page', 'cnt' => $views ],
                [ 'lbl' => 'Started the form (first interaction)',   'cnt' => $starts ],
            ];
            foreach ( $canon as $key => $lbl ) {
                $funnel_rows[] = [ 'lbl' => $lbl, 'cnt' => $step_cnts[ $key ] ?? 0 ];
            }
            $funnel_rows[] = [ 'lbl' => 'Submitted form (became a lead)', 'cnt' => $entries_cnt ];
            $fmax = max( 1, max( array_column( $funnel_rows, 'cnt' ) ) );
            foreach ( $funnel_rows as $row ):
                $cnt  = (int) $row['cnt'];
                $pct  = round( $cnt / $fmax * 100 );
                $zero = $cnt === 0;
            ?>
            <div class="as-src-row <?= $zero ? 'is-zero' : '' ?>">
                <span class="lbl"><?= esc_html( $row['lbl'] ) ?></span>
                <div class="bar"><div class="fill" style="width:<?= max( $pct, 2 ) ?>%;"></div></div>
                <span class="cnt"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Daily submissions -->
        <div class="as-an-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;gap:20px;">
                <div>
                    <div style="font-size:15px;font-weight:700;color:#1d2327;">Daily Submissions</div>
                    <div style="font-size:11.5px;color:#9ca3af;">Form submissions per day · <?= esc_html( $range['label'] ) ?></div>
                </div>
                <div style="display:flex;gap:20px;">
                    <div style="text-align:right;"><div style="font-size:24px;font-weight:700;color:#ee7a00;line-height:1;"><?= $entries_cnt ?></div><div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-top:5px;font-weight:600;">Total</div></div>
                    <div style="text-align:right;"><div style="font-size:24px;font-weight:700;color:#1d2327;line-height:1;"><?= (int) $daily_max ?></div><div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-top:5px;font-weight:600;">Peak day</div></div>
                </div>
            </div>
            <div class="as-bar-chart">
            <?php foreach ( $daily_filled as $d ): ?>
                <div class="as-bar-col <?= $d['cnt'] === 0 ? 'zero' : '' ?>" title="<?= esc_attr( $d['day'] ) ?>: <?= $d['cnt'] ?>">
                    <div class="b" style="height:<?= $d['cnt'] > 0 ? round( $d['cnt'] / $daily_max * 100 ) : 4 ?>%;"></div>
                </div>
            <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:6px;">
                <span><?= esc_html( $daily_filled[0]['day'] ) ?></span>
                <span><?= esc_html( $daily_filled[ count( $daily_filled ) - 1 ]['day'] ) ?></span>
            </div>
        </div>

        <!-- GHL send health -->
        <?php $ok_pct = $entries_cnt > 0 ? round( $entries_ok / $entries_cnt * 100 ) : 100; ?>
        <div class="as-an-card">
            <div style="font-size:15px;font-weight:700;color:#1d2327;margin-bottom:3px;">GHL Send Health</div>
            <div style="font-size:11.5px;color:#9ca3af;margin-bottom:14px;">Percentage of submissions that reached GoHighLevel successfully. <?= esc_html( $range['label'] ) ?>.</div>
            <div style="display:flex;align-items:center;gap:24px;">
                <div style="position:relative;width:72px;height:72px;flex-shrink:0;">
                    <svg viewBox="0 0 36 36" style="width:72px;height:72px;transform:rotate(-90deg);">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f3f4f6" stroke-width="3.5"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $ok_pct>=90?'#16a34a':($ok_pct>=70?'#f59e0b':'#dc2626') ?>" stroke-width="3.5" stroke-dasharray="<?= $ok_pct ?> 100" stroke-linecap="round"/>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:<?= $ok_pct>=90?'#16a34a':($ok_pct>=70?'#f59e0b':'#dc2626') ?>;"><?= $ok_pct ?>%</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <div style="font-size:13px;color:#374151;">✓ <strong><?= $entries_ok ?></strong> sent successfully</div>
                    <div style="font-size:13px;color:#374151;">✗ <strong><?= $entries_err ?></strong> failed</div>
                    <div style="font-size:11px;color:#9ca3af;">All time: <?= $entries_all ?> submissions</div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
    <?php
}

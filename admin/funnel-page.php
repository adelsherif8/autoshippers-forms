<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Form meta ── */
function as_funnel_meta(): array {
    return [
        'vehicle_quote' => [
            'label'    => 'Vehicle Shipping Quote',
            'subtitle' => 'Every visitor who reached the vehicle shipping quote form.',
            'steps'    => 3,
        ],
    ];
}

/* ── Step labels per form ── */
function as_funnel_step_labels(): array {
    return [
        'vehicle_quote' => [
            1 => 'Shipping Details (route & date)',
            2 => 'Vehicle Details (type & status)',
            3 => 'Get Quote (contact details)',
        ],
    ];
}

function as_render_funnel_page(): void {
    $forms      = as_funnel_meta();
    $labels_all = as_funnel_step_labels();

    $form_filter = sanitize_key( $_GET['form_filter'] ?? '' );
    if ( $form_filter !== '' && ! isset( $forms[ $form_filter ] ) ) $form_filter = '';

    $page_filter = esc_url_raw( $_GET['page_filter'] ?? '' );

    /* Default range: last 30 days */
    $end_default   = current_time( 'Y-m-d' );
    $start_default = date( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) );
    $start = sanitize_text_field( $_GET['start'] ?? $start_default );
    $end   = sanitize_text_field( $_GET['end']   ?? $end_default );
    $start_dt = $start . ' 00:00:00';
    $end_dt   = $end   . ' 23:59:59';

    $shown_forms = $form_filter ? [ $form_filter => $forms[ $form_filter ] ] : $forms;
    ?>
    <style>
        .as-funnel-wrap { max-width: 1180px; }

        /* Controls */
        .as-fnl-controls {
            display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;
            background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            padding:14px 18px; margin:18px 0 24px;
            box-shadow:0 1px 3px rgba(0,0,0,0.04);
        }
        .as-fnl-controls label {
            font-size:11px; font-weight:700; color:#6b7280;
            text-transform:uppercase; letter-spacing:0.05em;
            display:block; margin-bottom:4px;
        }
        .as-fnl-controls input[type="date"],
        .as-fnl-controls select {
            height:34px; min-width:160px;
            border:1px solid #d1d5db; border-radius:6px; padding:0 10px;
        }
        .as-fnl-controls .button { height:34px; }

        /* Section */
        .as-fnl-section {
            background:#fff; border:1px solid #e5e7eb; border-radius:12px;
            padding:24px 28px; margin-bottom:24px;
            box-shadow:0 1px 3px rgba(0,0,0,0.04);
        }
        .as-fnl-section + .as-fnl-section {
            border-top: 3px solid #fee2e2;
        }
        .as-fnl-title {
            font-size:14px; font-weight:800; color:#0a0a0a;
            text-transform:none; letter-spacing:0;
            margin:0 0 4px;
        }
        .as-fnl-title.drop { color:#dc2626; }
        .as-fnl-subtitle {
            font-size:12px; color:#6b7280;
            margin:0 0 22px;
        }
        .as-fnl-stats {
            font-size:13px; color:#374151; font-weight:600; margin:0 0 16px;
        }

        /* Funnel rows */
        .as-fnl-row {
            display:grid;
            grid-template-columns:300px 1fr 70px;
            align-items:center;
            gap:14px;
            padding:6px 0;
        }
        .as-fnl-label {
            font-size:12px; color:#374151; font-weight:500;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .as-fnl-bar-track {
            background:#f3f4f6;
            border-radius:4px;
            height:18px;
            overflow:hidden;
        }
        .as-fnl-bar {
            height:100%;
            background:#ee7a00; /* AutoShippers brand orange */
            border-radius:4px;
            transition:width 0.3s ease;
        }
        .as-fnl-count {
            text-align:right;
            font-size:13px;
            font-weight:700;
            color:#111827;
        }

        /* Drop-off rows */
        .as-fnl-drop-row {
            display:grid;
            grid-template-columns:300px 1fr 60px 80px;
            align-items:center;
            gap:14px;
            padding:6px 0;
        }
        .as-fnl-drop-bar {
            background:#374151; /* dark gray for normal */
        }
        .as-fnl-drop-bar.hi { background:linear-gradient(90deg, #dc2626 0%, #b91c1c 100%); } /* red for biggest drop-off */
        .as-fnl-drop-pct {
            text-align:right;
            font-size:12px; font-weight:700; color:#111827;
        }
        .as-fnl-drop-pct.hi { color:#dc2626; }
        .as-fnl-drop-left {
            text-align:right;
            font-size:11px; color:#6b7280;
        }
        .as-fnl-drop-left.hi { color:#dc2626; font-weight:600; }

        .as-fnl-empty {
            padding:36px; text-align:center; color:#9ca3af; font-size:13px;
        }

        /* Form heading bar */
        .as-fnl-formhead {
            display:flex; align-items:center; gap:10px;
            margin:32px 0 10px;
        }
        .as-fnl-formhead h2 {
            margin:0; font-size:20px; font-weight:800; color:#111827;
            border-left:4px solid #ee7a00;
            padding-left:12px;
        }
        .as-fnl-formhead .date-range {
            margin-left:auto; font-size:12px; color:#6b7280;
        }
    </style>

    <div class="wrap as-funnel-wrap">
        <h1>
            <img src="https://autoshippers.ca/wp-content/uploads/2024/10/Favicon-1.png" alt="AutoShippers" style="height:36px;vertical-align:middle;margin-right:10px">
            Form Funnel
        </h1>

        <form method="get" class="as-fnl-controls">
            <input type="hidden" name="page" value="as-funnel">
            <div>
                <label>From</label>
                <input type="date" name="start" value="<?php echo esc_attr( $start ); ?>">
            </div>
            <div>
                <label>To</label>
                <input type="date" name="end" value="<?php echo esc_attr( $end ); ?>">
            </div>
            <?php $pages = AS_Funnel::distinct_pages( $form_filter, $start_dt, $end_dt ); ?>
            <div style="flex:1;min-width:240px">
                <label>Landing page</label>
                <select name="page_filter">
                    <option value="">All pages</option>
                    <?php foreach ( $pages as $p ): ?>
                        <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $page_filter, $p ); ?>><?php echo esc_html( $p ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ( empty( $pages ) ): ?>
                    <small style="display:block;color:#9ca3af;margin-top:4px">No tracked pages yet</small>
                <?php endif; ?>
            </div>
            <button type="submit" class="button button-primary">Apply</button>
            <?php if ( $page_filter || $start !== $start_default || $end !== $end_default ): ?>
                <a href="?page=as-funnel" class="button">Reset</a>
            <?php endif; ?>
        </form>

        <?php foreach ( $shown_forms as $ft => $meta ):
            $summary      = AS_Funnel::summary( $ft, $start_dt, $end_dt, $page_filter );
            $drops        = AS_Funnel::dropoffs( $ft, $start_dt, $end_dt, $page_filter );
            $views        = $summary['views'];
            $submits      = $summary['submits'];
            $steps        = $summary['steps'];
            $labels       = $labels_all[ $ft ] ?? [];
            $abandoned    = array_sum( $drops );

            /* Build funnel rows in order */
            $rows = [];
            $landing_label = $page_filter
                ? 'Visitor reached ' . $page_filter
                : 'Visitor reached the landing page';
            $rows[] = [ 'label' => $landing_label, 'count' => $views ];
            for ( $i = 1; $i <= ( $meta['steps'] ?? 1 ); $i++ ) {
                $rows[] = [
                    'label' => $labels[ $i ] ?? ( 'Step ' . $i ),
                    'count' => $steps[ $i ] ?? 0,
                ];
            }
            $rows[] = [ 'label' => 'Submitted form (became a lead)', 'count' => $submits ];

            $max = max( 1, $views );

            /* Drop-off rows: only for steps 1..N. Highest drop = red. */
            $drop_rows = [];
            for ( $i = 1; $i <= ( $meta['steps'] ?? 1 ); $i++ ) {
                $c = $drops[ $i ] ?? 0;
                $pct = $abandoned > 0 ? round( ( $c / $abandoned ) * 100 ) : 0;
                $drop_rows[ $i ] = [
                    'label' => $labels[ $i ] ?? ( 'Step ' . $i ),
                    'count' => $c,
                    'pct'   => $pct,
                ];
            }
            /* Find highest drop step (1-based) */
            $hi_step = 0;
            $hi_pct  = 0;
            foreach ( $drop_rows as $i => $r ) {
                if ( $r['pct'] > $hi_pct ) { $hi_pct = $r['pct']; $hi_step = $i; }
            }
        ?>

        <div class="as-fnl-formhead">
            <h2><?php echo esc_html( $meta['label'] ); ?></h2>
            <span class="date-range"><?php echo esc_html( $start ); ?> → <?php echo esc_html( $end ); ?></span>
        </div>

        <!-- ── Funnel ── -->
        <div class="as-fnl-section">
            <h3 class="as-fnl-title">Funnel — Direct Visitors</h3>
            <p class="as-fnl-subtitle"><?php echo esc_html( $meta['subtitle'] ); ?> Last <?php echo esc_html( (string) ( ( strtotime( $end ) - strtotime( $start ) ) / 86400 + 1 ) ); ?> days.</p>

            <?php if ( $views === 0 && $submits === 0 && empty( $steps ) ): ?>
                <div class="as-fnl-empty">
                    <div style="font-size:28px;margin-bottom:6px">📊</div>
                    No tracked events for this form in the selected range yet.
                </div>
            <?php else:
                foreach ( $rows as $r ):
                    $pct = $max > 0 ? round( ( intval( $r['count'] ) / $max ) * 100, 1 ) : 0;
            ?>
                <div class="as-fnl-row">
                    <div class="as-fnl-label" title="<?php echo esc_attr( $r['label'] ); ?>"><?php echo esc_html( $r['label'] ); ?></div>
                    <div class="as-fnl-bar-track">
                        <div class="as-fnl-bar" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
                    </div>
                    <div class="as-fnl-count"><?php echo intval( $r['count'] ); ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- ── Drop-off Points ── -->
        <div class="as-fnl-section">
            <h3 class="as-fnl-title drop">Drop-off Points</h3>
            <p class="as-fnl-subtitle">The last step each visitor reached before abandoning the form.</p>

            <p class="as-fnl-stats"><?php echo intval( $abandoned ); ?> sessions left without completing.</p>

            <?php if ( $abandoned === 0 ): ?>
                <div class="as-fnl-empty" style="padding:18px">
                    No abandonments recorded yet.
                </div>
            <?php else:
                foreach ( $drop_rows as $i => $r ):
                    $is_hi = ( $i === $hi_step && $r['pct'] > 0 );
            ?>
                <div class="as-fnl-drop-row">
                    <div class="as-fnl-label" title="<?php echo esc_attr( $r['label'] ); ?>"><?php echo esc_html( $r['label'] ); ?></div>
                    <div class="as-fnl-bar-track">
                        <div class="as-fnl-bar as-fnl-drop-bar <?php echo $is_hi ? 'hi' : ''; ?>" style="width:<?php echo esc_attr( max( 0, $r['pct'] ) ); ?>%"></div>
                    </div>
                    <div class="as-fnl-drop-pct <?php echo $is_hi ? 'hi' : ''; ?>"><?php echo intval( $r['pct'] ); ?>%</div>
                    <div class="as-fnl-drop-left <?php echo $is_hi ? 'hi' : ''; ?>"><?php echo intval( $r['count'] ); ?> left</div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <?php endforeach; ?>
    </div>
    <?php
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── CSV export ── */
add_action( 'admin_post_as_export_entries', 'as_export_entries' );
function as_export_entries(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    check_admin_referer( 'as_export_entries' );

    global $wpdb;
    $table = AS_Entries::table_name();
    $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC", ARRAY_A );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=autoshippers-entries-' . date( 'Y-m-d' ) . '.csv' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'ID','Date','First Name','Last Name','Email','Phone','Tag','GHL Status','GHL Message','IP','Data (JSON)' ] );
    foreach ( $rows as $r ) {
        fputcsv( $out, [
            $r['id'], $r['created_at'],
            $r['first_name'], $r['last_name'], $r['email'], $r['phone'],
            $r['tag'], $r['ghl_status'], $r['ghl_message'], $r['ip'], $r['data'],
        ] );
    }
    fclose( $out );
    exit;
}

/* ── Delete ── */
add_action( 'admin_post_as_delete_entry', 'as_delete_entry' );
function as_delete_entry(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $id = intval( $_GET['entry'] ?? 0 );
    check_admin_referer( 'as_delete_entry_' . $id );
    if ( $id ) AS_Entries::delete( $id );
    wp_safe_redirect( admin_url( 'admin.php?page=as-entries&deleted=1' ) );
    exit;
}

function as_pretty_json( string $raw ): string {
    $decoded = json_decode( $raw, true );
    if ( $decoded === null ) return $raw;
    return json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}

/* ── Page router ── */
function as_render_entries_page(): void {
    $view_id = isset( $_GET['view'] ) ? intval( $_GET['view'] ) : 0;
    if ( $view_id && ( $entry = AS_Entries::get( $view_id ) ) ) {
        as_render_entry_detail( $entry );
        return;
    }
    as_render_entries_list();
}

function as_render_entries_list(): void {
    $page        = max( 1, intval( $_GET['paged'] ?? 1 ) );
    $per_page    = 25;
    $result      = AS_Entries::fetch( $page, $per_page );
    $rows        = $result['rows'];
    $total       = $result['total'];
    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
    ?>
    <style>
        .as-entries-wrap { max-width: 1400px; }
        .as-entries-header {
            display:flex; align-items:center; justify-content:space-between;
            gap:16px; flex-wrap:wrap; margin:18px 0 22px;
            padding:18px 22px; background:#fff;
            border:1px solid #e5e7eb; border-radius:10px;
            box-shadow:0 1px 3px rgba(0,0,0,0.04);
        }
        .as-stat { display:flex; flex-direction:column; }
        .as-stat-num { font-size:28px; font-weight:800; color:#111827; line-height:1; }
        .as-stat-label { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.08em; font-weight:600; margin-top:4px; }
        .as-entries-table {
            background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.04);
        }
        .as-entries-table table { width:100%; border-collapse:collapse; }
        .as-entries-table thead th {
            background:#f9fafb; border-bottom:1px solid #e5e7eb;
            font-size:11px; font-weight:700; text-transform:uppercase;
            letter-spacing:0.06em; color:#6b7280; text-align:left;
            padding:12px 14px; white-space:nowrap;
        }
        .as-entries-table tbody td {
            padding:14px; border-bottom:1px solid #f3f4f6;
            font-size:13px; color:#111827; vertical-align:middle;
        }
        .as-entries-table tbody tr { cursor:pointer; transition:background 0.12s ease; }
        .as-entries-table tbody tr:hover { background:#fff4ea; }
        .as-entries-table tbody tr:last-child td { border-bottom:none; }
        .as-entries-table tbody tr.as-empty { cursor:default; }
        .as-entries-table tbody tr.as-empty:hover { background:#fff; }
        .as-entries-table tbody tr.as-empty td { text-align:center; padding:50px 20px; color:#9ca3af; }
        .as-ghl-badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; }
        .as-ghl-sent   { color:#16a34a; }
        .as-ghl-failed { color:#dc2626; }
        .as-ghl-pending{ color:#9ca3af; }
        .as-tag-pill { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; background:#fff4ea; color:#9a4a00; border:1px solid #fed7aa; }
        .as-delete-btn { color:#dc2626 !important; border-color:#fecaca !important; background:#fef2f2 !important; }
        .as-delete-btn:hover { background:#fee2e2 !important; }
        .as-pagination { padding:14px 16px; border-top:1px solid #e5e7eb; background:#f9fafb; }
        .as-pagination .page-numbers {
            display:inline-block; padding:6px 12px; margin:0 2px;
            background:#fff; border:1px solid #d1d5db; border-radius:6px;
            color:#374151; text-decoration:none; font-size:13px;
        }
        .as-pagination .page-numbers.current { background:#ee7a00; color:#fff; border-color:#ee7a00; }
        .as-pagination .page-numbers:hover { background:#f3f4f6; }
    </style>

    <div class="wrap as-admin as-entries-wrap">
        <h1 class="as-admin-title">
            <img src="https://autoshippers.ca/wp-content/uploads/2024/10/Favicon-1.png" alt="AutoShippers" style="height:36px;vertical-align:middle;margin-right:10px;object-fit:contain">
            Form Entries
        </h1>

        <?php if ( isset( $_GET['deleted'] ) ): ?>
            <div class="notice notice-success is-dismissible" style="margin-top:10px"><p>Entry deleted.</p></div>
        <?php endif; ?>

        <div class="as-entries-header">
            <div class="as-stat">
                <span class="as-stat-num"><?php echo intval( $total ); ?></span>
                <span class="as-stat-label">Total entries</span>
            </div>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=as_export_entries' ), 'as_export_entries' ) ); ?>"
               class="button button-secondary">
                <span class="dashicons dashicons-download"></span> Export CSV
            </a>
        </div>

        <div class="as-entries-table">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px">ID</th>
                        <th style="width:150px">Date</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th style="width:140px">Phone</th>
                        <th style="width:100px">GHL</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ): ?>
                        <tr class="as-empty"><td colspan="7">
                            <div style="font-size:32px;margin-bottom:8px">📭</div>
                            <div style="font-size:14px;font-weight:600;color:#6b7280">No entries yet</div>
                            <div style="font-size:12px;margin-top:4px">Submissions will appear here as they come in.</div>
                        </td></tr>
                    <?php else: foreach ( $rows as $r ):
                        $view_url = esc_url( add_query_arg( [ 'view' => intval( $r['id'] ) ] ) );
                        $del_url  = esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=as_delete_entry&entry=' . intval( $r['id'] ) ), 'as_delete_entry_' . intval( $r['id'] ) ) );
                    ?>
                    <tr data-href="<?php echo $view_url; ?>">
                        <td style="color:#9ca3af;font-family:monospace">#<?php echo intval( $r['id'] ); ?></td>
                        <td style="color:#6b7280;font-size:12px;white-space:nowrap"><?php echo esc_html( mysql2date( 'M j, g:i a', $r['created_at'] ) ); ?></td>
                        <td style="font-weight:600"><?php echo esc_html( trim( $r['first_name'] . ' ' . $r['last_name'] ) ) ?: '<span style="color:#9ca3af">—</span>'; ?></td>
                        <td><a href="mailto:<?php echo esc_attr( $r['email'] ); ?>" onclick="event.stopPropagation()" style="color:#ee7a00;text-decoration:none"><?php echo esc_html( $r['email'] ); ?></a></td>
                        <td style="color:#6b7280"><?php echo esc_html( $r['phone'] ) ?: '—'; ?></td>
                        <td>
                            <?php if ( $r['ghl_status'] === 'sent' ): ?>
                                <span class="as-ghl-badge as-ghl-sent">● Sent</span>
                            <?php elseif ( $r['ghl_status'] === 'failed' ): ?>
                                <span class="as-ghl-badge as-ghl-failed" title="<?php echo esc_attr( $r['ghl_message'] ); ?>">● Failed</span>
                            <?php else: ?>
                                <span class="as-ghl-badge as-ghl-pending">○ Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right">
                            <a href="<?php echo $del_url; ?>" class="button button-small as-delete-btn"
                               onclick="event.stopPropagation(); return confirm('Delete this entry?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ): ?>
            <div class="as-pagination">
                <?php
                $base = admin_url( 'admin.php?page=as-entries' );
                echo paginate_links( [
                    'base'      => add_query_arg( 'paged', '%#%', $base ),
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $total_pages,
                    'prev_text' => '‹ Prev',
                    'next_text' => 'Next ›',
                ] );
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener( 'click', function( e ) {
        const row = e.target.closest( 'tr[data-href]' );
        if ( ! row ) return;
        if ( e.target.closest( 'a, button' ) ) return;
        window.location.href = row.dataset.href;
    } );
    </script>
    <?php
}

function as_render_entry_detail( array $entry ): void {
    $data  = json_decode( $entry['data'], true ) ?: [];
    $back  = esc_url( admin_url( 'admin.php?page=as-entries' ) );
    $del   = esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=as_delete_entry&entry=' . intval( $entry['id'] ) ), 'as_delete_entry_' . intval( $entry['id'] ) ) );

    $label_map = [
        'move_type'         => 'Move type',
        'pickup_date'       => 'Pickup date',
        'from_city'         => 'From city',
        'to_city'           => 'To city',
        'vehicle_type'      => 'Vehicle type',
        'vehicle_status'    => 'Vehicle status',
        'utmcampaign_custom'=> 'UTM Campaign',
        'utmmedium_custom'  => 'UTM Medium',
        'utmcontent_custom' => 'UTM Content',
        'utmkeyword_custom' => 'UTM Keyword',
        'utmterm_custom'    => 'UTM Term',
        'gclid_custom'      => 'GCLID',
    ];
    $pretty = fn( $k ) => $label_map[ $k ] ?? ucwords( str_replace( '_', ' ', $k ) );
    ?>
    <style>
        .as-detail-wrap { max-width:1100px; }
        .as-detail-top { display:flex; align-items:center; justify-content:space-between; gap:16px; margin:18px 0 22px; flex-wrap:wrap; }
        .as-detail-top .left { display:flex; align-items:center; gap:12px; }
        .as-back-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:#fff; border:1px solid #d1d5db; border-radius:8px; color:#374151; text-decoration:none; font-weight:600; }
        .as-back-btn:hover { background:#fff4ea; color:#ee7a00; }
        .as-detail-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
        .as-detail-hero { padding:24px 28px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
        .as-detail-hero h2 { margin:0; font-size:22px; font-weight:800; color:#111827; }
        .as-detail-section { padding:22px 28px; border-bottom:1px solid #f3f4f6; }
        .as-detail-section:last-child { border-bottom:none; }
        .as-detail-section h3 { margin:0 0 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#6b7280; }
        .as-detail-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px 24px; }
        .as-detail-grid .item { display:flex; flex-direction:column; gap:3px; }
        .as-detail-grid .label { font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; font-weight:600; }
        .as-detail-grid .value { font-size:14px; color:#111827; word-break:break-word; }
        .as-detail-grid .value a { color:#ee7a00; text-decoration:none; }
        .as-detail-grid .value code { background:#f3f4f6; padding:2px 6px; border-radius:4px; font-size:12px; }
        .as-ghl-box { padding:14px 18px; border-radius:8px; display:flex; align-items:flex-start; gap:12px; }
        .as-ghl-box.sent    { background:#dcfce7; border:1px solid #86efac; color:#166534; }
        .as-ghl-box.failed  { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }
        .as-ghl-box.pending { background:#f3f4f6; border:1px solid #d1d5db; color:#6b7280; }
        .as-tag-pill { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; background:#fff4ea; color:#9a4a00; border:1px solid #fed7aa; }
    </style>

    <div class="wrap as-admin as-detail-wrap">
        <div class="as-detail-top">
            <div class="left">
                <a href="<?php echo $back; ?>" class="as-back-btn">
                    <span class="dashicons dashicons-arrow-left-alt" style="margin-top:1px"></span> Back to entries
                </a>
                <h1 style="margin:0;font-size:18px;color:#374151;">Entry <span style="color:#9ca3af">#<?php echo intval( $entry['id'] ); ?></span></h1>
            </div>
            <a href="<?php echo $del; ?>" class="button" style="color:#dc2626;border-color:#fecaca;background:#fef2f2" onclick="return confirm('Delete this entry?')">
                <span class="dashicons dashicons-trash" style="vertical-align:middle"></span> Delete
            </a>
        </div>

        <div class="as-detail-card">
            <div class="as-detail-hero">
                <h2><?php echo esc_html( trim( $entry['first_name'] . ' ' . $entry['last_name'] ) ) ?: 'Anonymous'; ?></h2>
                <div style="margin-left:auto;color:#6b7280;font-size:13px">
                    <?php echo esc_html( mysql2date( 'F j, Y', $entry['created_at'] ) ); ?> · <strong><?php echo esc_html( mysql2date( 'g:i a', $entry['created_at'] ) ); ?></strong>
                </div>
            </div>

            <div class="as-detail-section">
                <h3>Contact</h3>
                <div class="as-detail-grid">
                    <div class="item"><span class="label">Name</span><span class="value"><?php echo esc_html( trim( $entry['first_name'] . ' ' . $entry['last_name'] ) ) ?: '—'; ?></span></div>
                    <div class="item"><span class="label">Email</span><span class="value"><?php if ( $entry['email'] ): ?><a href="mailto:<?php echo esc_attr( $entry['email'] ); ?>"><?php echo esc_html( $entry['email'] ); ?></a><?php else: ?>—<?php endif; ?></span></div>
                    <div class="item"><span class="label">Phone</span><span class="value"><?php if ( $entry['phone'] ): ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $entry['phone'] ) ); ?>"><?php echo esc_html( $entry['phone'] ); ?></a><?php else: ?>—<?php endif; ?></span></div>
                </div>
            </div>

            <?php
            $form_keys = [ 'move_type','pickup_date','from_city','to_city','vehicle_type','vehicle_status' ];
            $has_form  = false;
            foreach ( $form_keys as $k ) { if ( ! empty( $data[ $k ] ) ) { $has_form = true; break; } }
            ?>
            <?php if ( $has_form ): ?>
            <div class="as-detail-section">
                <h3>Shipping details</h3>
                <div class="as-detail-grid">
                    <?php foreach ( $form_keys as $k ): if ( empty( $data[ $k ] ) ) continue; ?>
                    <div class="item">
                        <span class="label"><?php echo esc_html( $pretty( $k ) ); ?></span>
                        <span class="value"><?php echo esc_html( is_array( $data[ $k ] ) ? implode( ', ', $data[ $k ] ) : (string) $data[ $k ] ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $utm_keys = [ 'utmcampaign_custom','utmmedium_custom','utmcontent_custom','utmkeyword_custom','utmterm_custom','gclid_custom' ];
            $has_utm  = false;
            foreach ( $utm_keys as $k ) { if ( ! empty( $data[ $k ] ) ) { $has_utm = true; break; } }
            ?>
            <?php if ( $has_utm ): ?>
            <div class="as-detail-section">
                <h3>Tracking</h3>
                <div class="as-detail-grid">
                    <?php foreach ( $utm_keys as $k ): if ( empty( $data[ $k ] ) ) continue; ?>
                    <div class="item">
                        <span class="label"><?php echo esc_html( $pretty( $k ) ); ?></span>
                        <span class="value"><code><?php echo esc_html( (string) $data[ $k ] ); ?></code></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="as-detail-section">
                <h3>Metadata</h3>
                <div class="as-detail-grid">
                    <div class="item"><span class="label">Tag sent to GHL</span><span class="value"><?php echo $entry['tag'] ? '<span class="as-tag-pill">' . esc_html( $entry['tag'] ) . '</span>' : '—'; ?></span></div>
                    <div class="item"><span class="label">IP address</span><span class="value"><code><?php echo esc_html( $entry['ip'] ) ?: '—'; ?></code></span></div>
                </div>
            </div>

            <?php if ( ! empty( $data['_ghl_request'] ) || ! empty( $data['_ghl_response'] ) ): ?>
            <div class="as-detail-section">
                <h3>GHL Debug</h3>
                <?php if ( ! empty( $data['_ghl_http'] ) ): ?>
                    <div style="margin-bottom:10px;font-size:13px"><strong>HTTP:</strong> <code><?php echo intval( $data['_ghl_http'] ); ?></code></div>
                <?php endif; ?>
                <?php if ( ! empty( $data['_ghl_request'] ) ): ?>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;font-weight:700;margin-bottom:6px">Request sent to GHL</div>
                    <pre style="background:#0b1020;color:#a7f3d0;padding:14px;border-radius:8px;font-size:12px;overflow:auto;max-height:300px;margin:0 0 14px"><?php echo esc_html( as_pretty_json( $data['_ghl_request'] ) ); ?></pre>
                <?php endif; ?>
                <?php if ( ! empty( $data['_ghl_response'] ) ): ?>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;font-weight:700;margin-bottom:6px">Response from GHL</div>
                    <pre style="background:#0b1020;color:#fbbf24;padding:14px;border-radius:8px;font-size:12px;overflow:auto;max-height:300px;margin:0"><?php echo esc_html( as_pretty_json( $data['_ghl_response'] ) ); ?></pre>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="as-detail-section">
                <h3>GoHighLevel</h3>
                <?php if ( $entry['ghl_status'] === 'sent' ): ?>
                    <div class="as-ghl-box sent"><span style="font-size:18px">✓</span><div><strong>Sent successfully</strong><br>Contact created or updated in GoHighLevel.</div></div>
                <?php elseif ( $entry['ghl_status'] === 'failed' ): ?>
                    <div class="as-ghl-box failed"><span style="font-size:18px">✗</span><div><strong>Failed to send</strong><br><?php echo esc_html( $entry['ghl_message'] ) ?: 'Unknown error.'; ?></div></div>
                <?php else: ?>
                    <div class="as-ghl-box pending"><span style="font-size:18px">○</span><div><strong>Pending</strong><br>Saved locally; GHL status was never updated.</div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

<?php
/**
 * "Unfinished" — form fills that were started and abandoned.
 * Internal only: nothing on this screen has been sent to GoHighLevel.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Row actions: delete one, or clear the lot ── */
add_action( 'admin_post_as_partial_delete', 'as_partial_delete' );
function as_partial_delete(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    check_admin_referer( 'as_partial_delete' );
    AS_Partials::delete( intval( $_POST['id'] ?? 0 ) );
    wp_redirect( admin_url( 'admin.php?page=as-partials&deleted=1' ) );
    exit;
}

add_action( 'admin_post_as_partial_clear', 'as_partial_clear' );
function as_partial_clear(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    check_admin_referer( 'as_partial_clear' );
    AS_Partials::delete_all();
    wp_redirect( admin_url( 'admin.php?page=as-partials&cleared=1' ) );
    exit;
}

/* Field key → label, in the order the form asks them */
function as_partial_labels(): array {
    return [
        'move_type'       => 'Move type',
        'pickup_date'     => 'Pickup date',
        'from_city'       => 'From',
        'from_other'      => 'From (typed)',
        'to_city'         => 'To',
        'to_other'        => 'To (typed)',
        'vehicle_year'    => 'Year',
        'vehicle_make'    => 'Make',
        'vehicle_model'   => 'Model',
        'vehicle_type'    => 'Vehicle type',
        'vehicle_status'  => 'Status',
        'referral_source' => 'Found us via',
        'referral_other'  => 'Found us (typed)',
        'is_dealer'       => 'Dealer',
        'company_name'    => 'Company',
    ];
}

/* Which step they were on when they gave up */
function as_partial_step_label( string $key ): string {
    return [
        'shipping' => 'Shipping details',
        'vehicle'  => 'Vehicle details',
        'contact'  => 'Contact details',
    ][ $key ] ?? 'Just started';
}

function as_render_partials_page(): void {
    $rows       = AS_Partials::unfinished();
    $total      = AS_Partials::count_unfinished();
    $with_reach = AS_Partials::count_with_contact();
    $labels     = as_partial_labels();
    ?>
    <div class="wrap as-admin">
        <h1 class="as-admin-title">Unfinished Form Fills</h1>

        <?php if ( isset( $_GET['deleted'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Entry deleted.</p></div>
        <?php endif; ?>
        <?php if ( isset( $_GET['cleared'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>All unfinished fills deleted.</p></div>
        <?php endif; ?>

        <div class="as-an-card" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:14px 16px;margin:14px 0;font-size:13px;line-height:1.6;max-width:900px">
            <strong>These people did not submit the form.</strong>
            Nothing here has been sent to GoHighLevel, and none of it is a lead. It is here to show
            which step loses people and what they had already entered. Someone who did not press
            submit has not agreed to be contacted — check with the client's own legal advice before
            using any of these details for calls, texts or email.
            Rows are deleted automatically after <?php echo intval( AS_Partials::KEEP_DAYS ); ?> days.
        </div>

        <div style="display:flex;gap:12px;margin:16px 0;max-width:620px">
            <div style="flex:1;background:#f8fafc;border-radius:10px;padding:16px;text-align:center">
                <div style="font-size:26px;font-weight:700;color:#1d2327;line-height:1"><?php echo intval( $total ); ?></div>
                <div style="font-size:10px;font-weight:600;color:#9ca3af;margin-top:6px;text-transform:uppercase;letter-spacing:.07em">Unfinished fills</div>
            </div>
            <div style="flex:1;background:#f8fafc;border-radius:10px;padding:16px;text-align:center">
                <div style="font-size:26px;font-weight:700;color:#ee7a00;line-height:1"><?php echo intval( $with_reach ); ?></div>
                <div style="font-size:10px;font-weight:600;color:#9ca3af;margin-top:6px;text-transform:uppercase;letter-spacing:.07em">Left a phone or email</div>
            </div>
        </div>

        <?php if ( empty( $rows ) ) : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:28px;text-align:center;color:#6b7280;font-size:13px;max-width:900px">
                No unfinished fills recorded yet. They appear here as soon as someone starts the form and leaves without submitting.
            </div>
        <?php else : ?>
        <table class="widefat striped" style="max-width:1100px">
            <thead>
                <tr>
                    <th style="width:140px">Last activity</th>
                    <th style="width:130px">Gave up on</th>
                    <th style="width:200px">Who (if given)</th>
                    <th>What they had entered</th>
                    <th style="width:70px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $r ) :
                $data  = json_decode( (string) $r['data'], true );
                $data  = is_array( $data ) ? $data : [];
                $name  = trim( ( $r['first_name'] ?? '' ) . ' ' . ( $r['last_name'] ?? '' ) );
                $bits  = [];
                foreach ( $labels as $k => $label ) {
                    if ( ! empty( $data[ $k ] ) ) $bits[] = $label . ': ' . $data[ $k ];
                }
            ?>
                <tr>
                    <td>
                        <?php echo esc_html( mysql2date( 'j M, g:ia', $r['updated_at'] ) ); ?><br>
                        <span style="font-size:11px;color:#9ca3af">
                            <?php echo esc_html( human_time_diff( strtotime( $r['updated_at'] ), current_time( 'timestamp' ) ) ); ?> ago
                        </span>
                    </td>
                    <td><?php echo esc_html( as_partial_step_label( (string) $r['step_key'] ) ); ?></td>
                    <td>
                        <?php if ( $name !== '' ) : ?><strong><?php echo esc_html( $name ); ?></strong><br><?php endif; ?>
                        <?php if ( ! empty( $r['email'] ) ) : ?><span style="font-size:12px"><?php echo esc_html( $r['email'] ); ?></span><br><?php endif; ?>
                        <?php if ( ! empty( $r['phone'] ) ) : ?><span style="font-size:12px"><?php echo esc_html( $r['phone'] ); ?></span><?php endif; ?>
                        <?php if ( $name === '' && empty( $r['email'] ) && empty( $r['phone'] ) ) : ?>
                            <span style="color:#9ca3af;font-size:12px">— never reached the contact step —</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;line-height:1.7;color:#374151">
                        <?php echo $bits ? esc_html( implode( ' · ', $bits ) ) : '<span style="color:#9ca3af">nothing filled in</span>'; ?>
                        <?php if ( ! empty( $r['page_url'] ) ) : ?>
                            <br><span style="font-size:11px;color:#9ca3af"><?php echo esc_html( $r['page_url'] ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" onsubmit="return confirm('Delete this unfinished fill?')">
                            <?php wp_nonce_field( 'as_partial_delete' ); ?>
                            <input type="hidden" name="action" value="as_partial_delete">
                            <input type="hidden" name="id" value="<?php echo intval( $r['id'] ); ?>">
                            <button class="button button-small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top:16px"
              onsubmit="return confirm('Delete every unfinished fill? This cannot be undone.')">
            <?php wp_nonce_field( 'as_partial_clear' ); ?>
            <input type="hidden" name="action" value="as_partial_clear">
            <button class="button">Delete all unfinished fills</button>
        </form>
        <?php endif; ?>
    </div>
    <?php
}

<?php
/**
 * The banner above the quote form: editable in Settings → Banner Text, with a
 * log of who changed what and when.
 *
 * An empty saved value falls back to the default below, so clearing a box in
 * the admin restores the original wording rather than leaving a blank banner.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Slot => [ label shown in the admin, default copy ] */
function as_hero_slots(): array {
    return [
        'eyebrow' => [ 'Small label', 'FREE QUOTE' ],
        'title'   => [ 'Headline',    'Reserve within 24hrs & save $100 more' ],
        'sub'     => [ 'Sub-heading', "Get your instant quote online and save an extra $100 if you reserve online. Cancel for free at any time before your car is loaded" ],
    ];
}

function as_hero_option( string $slot ): string {
    return 'as_hero_' . $slot;
}

/* The live text for one slot — saved value, or the default when empty. */
function as_hero_text( string $slot ): string {
    $slots = as_hero_slots();
    if ( ! isset( $slots[ $slot ] ) ) return '';
    $saved = trim( (string) get_option( as_hero_option( $slot ), '' ) );
    return $saved !== '' ? $saved : $slots[ $slot ][1];
}

/* Newest change first. Each entry: time, user, slot, label, from, to. */
function as_hero_history(): array {
    $log = get_option( 'as_hero_history', [] );
    return is_array( $log ) ? $log : [];
}

/* Save the banner text and log every slot that actually changed. Returns the
   number of slots changed, so the admin can say so after a save. */
function as_hero_save( array $posted ): int {
    $log     = as_hero_history();
    $user    = wp_get_current_user();
    $changed = 0;

    foreach ( as_hero_slots() as $slot => [ $label, $default ] ) {
        $before = as_hero_text( $slot );
        $raw    = (string) ( $posted[ as_hero_option( $slot ) ] ?? '' );
        $clean  = trim( sanitize_textarea_field( wp_unslash( $raw ) ) );

        update_option( as_hero_option( $slot ), $clean );

        $after = as_hero_text( $slot );   /* re-read: empty means back to default */
        if ( $after === $before ) continue;

        $changed++;
        array_unshift( $log, [
            'time'  => current_time( 'mysql' ),
            'user'  => $user && $user->exists() ? $user->display_name : 'unknown',
            'slot'  => $slot,
            'label' => $label,
            'from'  => $before,
            'to'    => $after,
        ] );
    }

    if ( $changed > 0 ) {
        update_option( 'as_hero_history', array_slice( $log, 0, 30 ) );
    }
    return $changed;
}

/* "3 hours ago · Headline — by Adel", or '' when nothing has been changed. */
function as_hero_last_change_summary(): string {
    $log = as_hero_history();
    if ( empty( $log ) ) return '';
    $last = $log[0];
    $ago  = human_time_diff( strtotime( $last['time'] ), current_time( 'timestamp' ) );
    return sprintf( '%s ago · %s — by %s', $ago, $last['label'], $last['user'] );
}

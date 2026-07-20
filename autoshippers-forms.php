<?php
/**
 * Plugin Name:  AutoShippers Forms
 * Plugin URI:   https://upwork.com/freelancers/adelsherif8
 * Description:  Multi-step Vehicle Shipping Quote form with GoHighLevel CRM integration.
 * Version:      1.0.37
 * Author:       Adel Emad
 * Author URI:   https://upwork.com/freelancers/adelsherif8
 * License:      GPL-2.0+
 * Text Domain:  autoshippers-forms
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AS_VERSION',  '1.0.37' );
define( 'AS_ITI_VERSION', '18.5.3' );
define( 'AS_DIR',         plugin_dir_path( __FILE__ ) );
define( 'AS_URL',         plugin_dir_url( __FILE__ ) );
define( 'AS_BASENAME',    plugin_basename( __FILE__ ) );
define( 'AS_GITHUB_REPO', 'adelsherif8/autoshippers-forms' );

/* ── Includes ──────────────────────────────────────────────── */
require_once AS_DIR . 'includes/class-updater.php';
require_once AS_DIR . 'includes/class-ghl-api.php';
require_once AS_DIR . 'includes/class-entries.php';
require_once AS_DIR . 'includes/class-funnel.php';
require_once AS_DIR . 'includes/class-form-handler.php';

new AS_Updater( AS_GITHUB_REPO, __FILE__, AS_VERSION );
require_once AS_DIR . 'admin/admin-page.php';
require_once AS_DIR . 'admin/entries-page.php';
require_once AS_DIR . 'admin/funnel-page.php';
require_once AS_DIR . 'admin/instructions-page.php';

/* ── Entries + funnel DB tables ── */
register_activation_hook( __FILE__, [ 'AS_Entries', 'maybe_create_table' ] );
add_action( 'plugins_loaded',       [ 'AS_Entries', 'maybe_create_table' ] );
register_activation_hook( __FILE__, [ 'AS_Funnel', 'maybe_create_table' ] );
add_action( 'plugins_loaded',       [ 'AS_Funnel', 'maybe_create_table' ] );

/* ── Shortcodes ─────────────────────────────────────────────
   Both shortcodes share the same template and JS. They only differ
   in the default set of chrome elements shown around the form card.
   Every element can be individually toggled with attributes:
     hero="true|false"     — big hero banner above the form
     logo="true|false"     — AutoShippers logo
     trust="true|false"    — Fully Insured / Fast Response strip
     contact="true|false"  — Phone / Email / Address cards below the form

   [as_vehicle_quote]                             — everything on
   [as_vehicle_quote hero="false"]                — full page minus hero
   [as_vehicle_quote_embed]                       — just the form card
   [as_vehicle_quote_embed logo="true"]           — form card + logo above it */
add_shortcode( 'as_vehicle_quote',        'as_shortcode_vehicle_quote' );
add_shortcode( 'as_vehicle_quote_embed',  'as_shortcode_vehicle_quote_embed' );

function as_shortcode_vehicle_quote( $atts ) {
    return as_render_vehicle_quote( $atts, [ 'hero' => 1, 'logo' => 1, 'trust' => 1, 'contact' => 1 ] );
}
function as_shortcode_vehicle_quote_embed( $atts ) {
    return as_render_vehicle_quote( $atts, [ 'hero' => 0, 'logo' => 0, 'trust' => 0, 'contact' => 0 ] );
}
function as_render_vehicle_quote( $atts, array $defaults ): string {
    $atts = shortcode_atts( [
        'hero'    => $defaults['hero'],
        'logo'    => $defaults['logo'],
        'trust'   => $defaults['trust'],
        'contact' => $defaults['contact'],
    ], (array) $atts );

    /* Expose as vars the template can check */
    $show_hero    = filter_var( $atts['hero'],    FILTER_VALIDATE_BOOLEAN );
    $show_logo    = filter_var( $atts['logo'],    FILTER_VALIDATE_BOOLEAN );
    $show_trust   = filter_var( $atts['trust'],   FILTER_VALIDATE_BOOLEAN );
    $show_contact = filter_var( $atts['contact'], FILTER_VALIDATE_BOOLEAN );

    ob_start();
    include AS_DIR . 'templates/form-vehicle-quote.php';
    return ob_get_clean();
}

/* ── Front-end assets ──────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', 'as_enqueue_frontend' );
function as_enqueue_frontend() {
    wp_enqueue_style( 'as-fa',    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], null );
    wp_enqueue_style( 'as-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap', [], null );
    /* intl-tel-input: country flag dropdown + per-country phone formatting.
       Served from the plugin AND namespaced, because other plugins on the site
       (e.g. requestquote) load their own older copy from a CDN:
       - intlTelInput.scoped.js exposes our copy as window.asIntlTelInput and
         leaves window.intlTelInput to whoever else wants it, so another
         version can never initialise our field.
       - intlTelInput.scoped.css prefixes every rule with .as-wrapper, so a
         foreign intl-tel-input stylesheet can't restyle our widget (mismatched
         flag sprite offsets were rendering the wrong country's flag). */
    $iti = AS_URL . 'assets/vendor/intl-tel-input/';
    wp_enqueue_style(  'as-iti', $iti . 'css/intlTelInput.scoped.css', [], AS_ITI_VERSION );
    wp_enqueue_script( 'as-iti', $iti . 'js/intlTelInput.scoped.js',   [], AS_ITI_VERSION, true );

    /* The library points at its flag sprite with a relative path (../img/flags.png).
       Any plugin that combines or relocates CSS breaks that path and the flags
       render as the wrong country. Pin it to an absolute URL instead. */
    wp_add_inline_style( 'as-iti', sprintf(
        '.as-wrapper .iti__flag{background-image:url(%1$simg/flags.png)!important}' .
        '@media (-webkit-min-device-pixel-ratio:2),(min-resolution:192dpi){' .
        '.as-wrapper .iti__flag{background-image:url(%1$simg/flags@2x.png)!important}}',
        esc_url( $iti )
    ) );

    wp_enqueue_style( 'as-forms', AS_URL . 'assets/css/forms.css', [ 'as-fa', 'as-iti' ], AS_VERSION );
    wp_enqueue_script( 'as-forms', AS_URL . 'assets/js/forms.js', [ 'as-iti' ], AS_VERSION, true );
    wp_localize_script( 'as-forms', 'asData', [
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'as_submit' ),
        'itiUtils'  => $iti . 'js/utils.js',
    ] );
}

/* ── Admin assets ───────────────────────────────────────────── */
add_action( 'admin_enqueue_scripts', 'as_enqueue_admin' );
function as_enqueue_admin( $hook ) {
    if ( strpos( $hook, 'as-' ) === false ) return;
    wp_enqueue_style( 'as-admin', AS_URL . 'assets/css/admin.css', [], AS_VERSION );
    wp_enqueue_script( 'as-admin', AS_URL . 'assets/js/admin.js', [], AS_VERSION, true );
    wp_localize_script( 'as-admin', 'asAdmin', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'as_admin' ),
    ] );
}

/* ── Admin menu ─────────────────────────────────────────────── */
add_action( 'admin_menu', 'as_register_admin_menu' );
function as_register_admin_menu() {
    add_menu_page(
        'AutoShippers Forms',
        'AS Forms',
        'manage_options',
        'as-settings',
        'as_render_settings_page',
        'dashicons-car',
        31
    );
    add_submenu_page( 'as-settings', 'Settings',     'Settings',     'manage_options', 'as-settings',     'as_render_settings_page' );
    add_submenu_page( 'as-settings', 'Entries',      'Entries',      'manage_options', 'as-entries',      'as_render_entries_page' );
    add_submenu_page( 'as-settings', 'Funnel',       'Funnel',       'manage_options', 'as-funnel',       'as_render_funnel_page' );
    add_submenu_page( 'as-settings', 'Instructions', 'Instructions', 'manage_options', 'as-instructions', 'as_render_instructions_page' );
}

/* ── Plugin action links ────────────────────────────────────── */
add_filter( 'plugin_action_links_' . AS_BASENAME, 'as_action_links' );
function as_action_links( $links ) {
    $links[] = '<a href="' . admin_url( 'admin.php?page=as-settings' ) . '">Settings</a>';
    return $links;
}

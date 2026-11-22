<?php
/**
 * Plugin Name:  AutoShippers Forms
 * Plugin URI:   https://upwork.com/freelancers/adelsherif8
 * Description:  Multi-step Vehicle Shipping Quote form with GoHighLevel CRM integration.
 * Version:      1.0.53
 * Author:       Adel Emad
 * Author URI:   https://upwork.com/freelancers/adelsherif8
 * License:      GPL-2.0+
 * Text Domain:  autoshippers-forms
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AS_VERSION',  '1.0.53' );
define( 'AS_ITI_VERSION', '18.5.3' );
define( 'AS_DIR',         plugin_dir_path( __FILE__ ) );
define( 'AS_URL',         plugin_dir_url( __FILE__ ) );
define( 'AS_BASENAME',    plugin_basename( __FILE__ ) );
define( 'AS_GITHUB_REPO', 'adelsherif8/autoshippers-forms' );

/* ── Includes ──────────────────────────────────────────────── */
require_once AS_DIR . 'includes/class-updater.php';
require_once AS_DIR . 'includes/class-ghl-api.php';
require_once AS_DIR . 'includes/class-entries.php';
require_once AS_DIR . 'includes/class-events.php';
require_once AS_DIR . 'includes/class-partials.php';
require_once AS_DIR . 'includes/class-form-handler.php';
require_once AS_DIR . 'includes/auto-provision.php';
require_once AS_DIR . 'includes/hero-text.php';

new AS_Updater( AS_GITHUB_REPO, __FILE__, AS_VERSION );
require_once AS_DIR . 'admin/admin-page.php';
require_once AS_DIR . 'admin/entries-page.php';
require_once AS_DIR . 'admin/partials-page.php';
require_once AS_DIR . 'admin/analytics-page.php';
require_once AS_DIR . 'admin/instructions-page.php';

/* ── Entries + analytics events DB tables ── */
register_activation_hook( __FILE__, [ 'AS_Entries', 'maybe_create_table' ] );
add_action( 'plugins_loaded',       [ 'AS_Entries', 'maybe_create_table' ] );
register_activation_hook( __FILE__, [ 'AS_Events', 'maybe_create_table' ] );
register_activation_hook( __FILE__, [ 'AS_Partials', 'maybe_create_table' ] );
add_action( 'plugins_loaded',       [ 'AS_Events', 'maybe_create_table' ] );
add_action( 'plugins_loaded',       [ 'AS_Partials', 'maybe_create_table' ] );

/* ── Shortcodes ─────────────────────────────────────────────
   Both shortcodes share the same template and JS. They only differ
   in the default set of chrome elements shown around the form card.
   Every element can be individually toggled with attributes:
     hero="true|false"     — big hero banner above the form
     logo="true|false"     — AutoShippers logo
     trust="true|false"    — Fully Insured / Fast Response strip
     contact="true|false"  — Phone / Email / Address cards below the form

   [as_vehicle_quote]                             — hero + form + trust + contact
   [as_vehicle_quote hero="false"]                — full page minus hero
   [as_vehicle_quote_embed]                       — just the form card
   [as_vehicle_quote_embed logo="true"]           — form card + logo above it */
add_shortcode( 'as_vehicle_quote',        'as_shortcode_vehicle_quote' );
add_shortcode( 'as_vehicle_quote_embed',  'as_shortcode_vehicle_quote_embed' );

function as_shortcode_vehicle_quote( $atts ) {
    /* logo off: the site header already shows it, and it pushed the form down */
    return as_render_vehicle_quote( $atts, [ 'hero' => 1, 'logo' => 0, 'trust' => 1, 'contact' => 1 ] );
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
        'modified' => '',   /* cards | checkbox | options — hidden when empty */
    ], (array) $atts );
    $modified_style = in_array( $atts['modified'], [ 'cards', 'checkbox', 'options' ], true ) ? $atts['modified'] : '';

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
        'pageId'    => (int) get_queried_object_id(),
        'preview'   => isset( $_GET['as_preview'] ) ? 1 : 0,   /* preview page: no analytics / unfinished fills */
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
    add_submenu_page( 'as-settings', 'Analytics',    'Analytics',    'manage_options', 'as-analytics',    'as_render_analytics_tab' );
    add_submenu_page( 'as-settings', 'Unfinished',   'Unfinished',   'manage_options', 'as-partials',     'as_render_partials_page' );
    add_submenu_page( 'as-settings', 'Instructions', 'Instructions', 'manage_options', 'as-instructions', 'as_render_instructions_page' );
}

/* ── Plugin action links ────────────────────────────────────── */
add_filter( 'plugin_action_links_' . AS_BASENAME, 'as_action_links' );
function as_action_links( $links ) {
    $links[] = '<a href="' . admin_url( 'admin.php?page=as-settings' ) . '">Settings</a>';
    return $links;
}

/* ── Preview page: ?as_preview=modified ─────────────────────────
   Shows the three "Is the vehicle modified?" versions side by side so the
   client can pick one. Not linked anywhere, not indexed, no page created. */
add_action( 'template_redirect', 'as_render_modified_preview' );
function as_render_modified_preview() {
    if ( ( $_GET['as_preview'] ?? '' ) !== 'modified' ) return;
    nocache_headers();
    $versions = [
        'cards'    => [ 'Option A — Yes / No (required)', 'Visitor must pick one before getting a quote.' ],
        'checkbox' => [ 'Option B — one checkbox (optional)', 'Simplest. Unticked = not modified.' ],
        'options'  => [ 'Option C — what is modified (optional)', 'Tells dispatch exactly what was changed. Nothing ticked = not modified.' ],
    ];
    ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Vehicle modified — form options</title>
<?php wp_head(); ?>
<style>
body.as-preview{background:#f6f7f9;margin:0;padding:32px 16px 64px;font-family:Inter,system-ui,sans-serif}
.as-preview-intro{max-width:760px;margin:0 auto 28px;text-align:center}
.as-preview-intro h1{font-size:26px;margin:0 0 8px;color:#111}
.as-preview-intro p{color:#555;margin:0}
.as-preview-block{max-width:760px;margin:0 auto 48px}
.as-preview-block h2{font-size:19px;margin:0 0 4px;color:#ee7a00}
.as-preview-block p{margin:0 0 14px;color:#555;font-size:14px}
</style>
</head>
<body class="as-preview">
<div class="as-preview-intro">
  <h1>“Is the vehicle modified?” — 3 options</h1>
  <p>Fill in step 1 and click Continue — the question is on step 2 of each form.</p>
</div>
<?php foreach ( $versions as $style => [ $title, $desc ] ) : ?>
<section class="as-preview-block">
  <h2><?php echo esc_html( $title ); ?></h2>
  <p><?php echo esc_html( $desc ); ?></p>
  <?php echo as_render_vehicle_quote( [ 'modified' => $style ], [ 'hero' => 0, 'logo' => 0, 'trust' => 0, 'contact' => 0 ] ); ?>
</section>
<?php endforeach; ?>
<?php wp_footer(); ?>
</body>
</html><?php
    exit;
}


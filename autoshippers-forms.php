<?php
/**
 * Plugin Name:  AutoShippers Forms
 * Plugin URI:   https://upwork.com/freelancers/adelsherif8
 * Description:  Multi-step Vehicle Shipping Quote form with GoHighLevel CRM integration.
 * Version:      1.0.15
 * Author:       Adel Emad
 * Author URI:   https://upwork.com/freelancers/adelsherif8
 * License:      GPL-2.0+
 * Text Domain:  autoshippers-forms
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AS_VERSION',  '1.0.15' );
define( 'AS_DIR',         plugin_dir_path( __FILE__ ) );
define( 'AS_URL',         plugin_dir_url( __FILE__ ) );
define( 'AS_BASENAME',    plugin_basename( __FILE__ ) );
define( 'AS_GITHUB_REPO', 'adelsherif8/autoshippers-forms' );

/* ── Includes ──────────────────────────────────────────────── */
require_once AS_DIR . 'includes/class-updater.php';
require_once AS_DIR . 'includes/class-ghl-api.php';
require_once AS_DIR . 'includes/class-form-handler.php';

new AS_Updater( AS_GITHUB_REPO, __FILE__, AS_VERSION );
require_once AS_DIR . 'admin/admin-page.php';
require_once AS_DIR . 'admin/instructions-page.php';

/* ── Shortcode ──────────────────────────────────────────────── */
add_shortcode( 'as_vehicle_quote', 'as_shortcode_vehicle_quote' );

function as_shortcode_vehicle_quote( $atts ) {
    ob_start();
    include AS_DIR . 'templates/form-vehicle-quote.php';
    return ob_get_clean();
}

/* ── Front-end assets ──────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', 'as_enqueue_frontend' );
function as_enqueue_frontend() {
    wp_enqueue_style( 'as-fa',    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], null );
    wp_enqueue_style( 'as-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap', [], null );
    wp_enqueue_style( 'as-forms', AS_URL . 'assets/css/forms.css', [ 'as-fa' ], AS_VERSION );
    wp_enqueue_script( 'as-forms', AS_URL . 'assets/js/forms.js', [], AS_VERSION, true );
    wp_localize_script( 'as-forms', 'asData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'as_submit' ),
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
    add_submenu_page( 'as-settings', 'Instructions', 'Instructions', 'manage_options', 'as-instructions', 'as_render_instructions_page' );
}

/* ── Plugin action links ────────────────────────────────────── */
add_filter( 'plugin_action_links_' . AS_BASENAME, 'as_action_links' );
function as_action_links( $links ) {
    $links[] = '<a href="' . admin_url( 'admin.php?page=as-settings' ) . '">Settings</a>';
    return $links;
}

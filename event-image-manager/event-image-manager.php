<?php
/**
 * Plugin Name: Event Image Manager
 * Plugin URI:  https://github.com/galanakisbm/photography
 * Description: Manages event photos with watermarking, download protection, and a responsive lightbox gallery.
 * Version:     1.0.0
 * Author:      galanakisbm
 * Text Domain: event-image-manager
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Autoload ──────────────────────────────────────────────────────────────────
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-post-type.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-image-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-image-protector.php';

// ── Bootstrap ─────────────────────────────────────────────────────────────────
function eim_init() {
    new Event_Post_Type();
    new Event_Admin();
    new Image_Protector();
}
add_action( 'plugins_loaded', 'eim_init' );

// ── Frontend assets ───────────────────────────────────────────────────────────
function eim_enqueue_frontend_assets() {
    $post            = get_post();
    $post_content    = $post ? $post->post_content : '';
    if ( ! is_singular( 'event' ) && ! has_shortcode( $post_content, 'event_gallery' ) ) {
        return;
    }

    wp_enqueue_style(
        'eim-gallery',
        plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'eim-image-protection',
        plugin_dir_url( __FILE__ ) . 'assets/js/image-protection.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'eim_enqueue_frontend_assets' );

// ── Admin assets ──────────────────────────────────────────────────────────────
function eim_enqueue_admin_assets( $hook ) {
    global $post;
    if ( ( 'post.php' === $hook || 'post-new.php' === $hook )
        && isset( $post ) && 'event' === $post->post_type ) {

        wp_enqueue_script(
            'eim-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/event-admin.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_localize_script(
            'eim-admin',
            'eimAdmin',
            array(
                'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
                'deleteNonce'       => wp_create_nonce( 'eim_delete_nonce' ),
                'selectImagesTitle' => __( 'Select Event Images', 'event-image-manager' ),
                'addImagesButton'   => __( 'Add to Gallery', 'event-image-manager' ),
                'removeLabel'       => __( 'Remove', 'event-image-manager' ),
            )
        );
    }
}
add_action( 'admin_enqueue_scripts', 'eim_enqueue_admin_assets' );

// ── Shortcode: [event_gallery id="POST_ID"] ───────────────────────────────────
function eim_event_gallery_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'id' => get_the_ID() ), $atts, 'event_gallery' );

    $post_id = absint( $atts['id'] );
    if ( ! $post_id ) {
        return '';
    }

    $images = get_post_meta( $post_id, Event_Admin::META_KEY, true );
    if ( empty( $images ) || ! is_array( $images ) ) {
        return '';
    }

    // Enqueue assets on shortcode use (in case the page is not a singular event).
    wp_enqueue_style( 'eim-gallery' );
    wp_enqueue_script( 'eim-image-protection' );

    ob_start();
    include plugin_dir_path( __FILE__ ) . 'templates/event-gallery.php';
    return ob_get_clean();
}
add_shortcode( 'event_gallery', 'eim_event_gallery_shortcode' );

// ── Activation / deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__, 'eim_activate' );
function eim_activate() {
    // Flush rewrite rules so the Image_Protector rewrite rule takes effect.
    ( new Event_Post_Type() )->register();
    ( new Image_Protector() )->add_rewrite_rules();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'eim_deactivate' );
function eim_deactivate() {
    flush_rewrite_rules();
}

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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-user-roles.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-password-protection.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-watermark-styles.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-download-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-image-optimizer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-search-filter.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-comments.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-favorites.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-categories.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-roles.php';

// ── Bootstrap ─────────────────────────────────────────────────────────────────
function eim_init() {
    new Event_Post_Type();
    new Event_Admin();
    new Image_Protector();
    new EIM_User_Roles();
    new EIM_Password_Protection();
    new EIM_Download_Manager();
    new EIM_Search_Filter();
    new EIM_Comments();
    new EIM_Favorites();
    new EIM_Categories();
    new EIM_Admin_Settings();
    new EIM_Admin_Roles();
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

    wp_enqueue_style(
        'eim-advanced-gallery',
        plugin_dir_url( __FILE__ ) . 'assets/css/advanced-gallery.css',
        array( 'eim-gallery' ),
        '1.0.0'
    );

    wp_enqueue_script(
        'eim-image-protection',
        plugin_dir_url( __FILE__ ) . 'assets/js/image-protection.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'eim-search-filter',
        plugin_dir_url( __FILE__ ) . 'assets/js/search-filter.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'eim-download-manager',
        plugin_dir_url( __FILE__ ) . 'assets/js/download-manager.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'eim-favorites',
        plugin_dir_url( __FILE__ ) . 'assets/js/favorites.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'eim-comments',
        plugin_dir_url( __FILE__ ) . 'assets/js/comments.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    $post_id = $post ? $post->ID : 0;

    wp_localize_script(
        'eim-search-filter',
        'eimSearch',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'eim_search_nonce' ),
            'postId'  => $post_id,
            'useAjax' => false,
        )
    );

    $dl_manager = new EIM_Download_Manager();
    $permission = $dl_manager->get_effective_permission( $post_id );
    wp_localize_script(
        'eim-download-manager',
        'eimDownload',
        array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'eim_download_nonce' ),
            'postId'     => $post_id,
            'permission' => $permission,
            'i18n'       => array(
                'preparing'            => __( 'Preparing download…', 'event-image-manager' ),
                'downloadSelected'     => __( 'Download %d selected', 'event-image-manager' ),
                'downloadSelectedEmpty' => __( 'Download Selected', 'event-image-manager' ),
                'error'                => __( 'An error occurred.', 'event-image-manager' ),
            ),
        )
    );

    wp_localize_script(
        'eim-favorites',
        'eimFavorites',
        array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'eim_favorites_nonce' ),
            'postId'    => $post_id,
            'favorites' => array(), // populated on first load via AJAX.
        )
    );

    wp_localize_script(
        'eim-comments',
        'eimComments',
        array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'eim_comments_nonce' ),
            'postId'   => $post_id,
            'loggedIn' => is_user_logged_in(),
            'i18n'     => array(
                'loading'    => __( 'Loading comments…', 'event-image-manager' ),
                'noComments' => __( 'No comments yet. Be the first!', 'event-image-manager' ),
                'error'      => __( 'An error occurred. Please try again.', 'event-image-manager' ),
            ),
        )
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

    // Enqueue assets on shortcode use (in case the page is not a singular event).
    wp_enqueue_style( 'eim-gallery' );
    wp_enqueue_style( 'eim-advanced-gallery' );
    wp_enqueue_script( 'eim-image-protection' );
    wp_enqueue_script( 'eim-search-filter' );
    wp_enqueue_script( 'eim-download-manager' );
    wp_enqueue_script( 'eim-favorites' );
    wp_enqueue_script( 'eim-comments' );

    // Check password protection.
    if ( ! EIM_Password_Protection::is_authorised( $post_id ) ) {
        ob_start();
        include plugin_dir_path( __FILE__ ) . 'templates/password-form.php';
        return ob_get_clean();
    }

    $images = get_post_meta( $post_id, Event_Admin::META_KEY, true );
    if ( empty( $images ) || ! is_array( $images ) ) {
        return '';
    }

    ob_start();
    include plugin_dir_path( __FILE__ ) . 'templates/gallery-with-filters.php';
    return ob_get_clean();
}
add_shortcode( 'event_gallery', 'eim_event_gallery_shortcode' );

// ── Activation / deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__, 'eim_activate' );
function eim_activate() {
    // Flush rewrite rules so the Image_Protector rewrite rule takes effect.
    ( new Event_Post_Type() )->register();
    ( new Image_Protector() )->add_rewrite_rules();

    // Register custom user roles and capabilities.
    ( new EIM_User_Roles() )->register_roles();

    // Create custom database tables.
    EIM_Download_Manager::create_table();
    EIM_Search_Filter::create_table();
    EIM_Comments::create_table();
    EIM_Favorites::create_table();

    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'eim_deactivate' );
function eim_deactivate() {
    flush_rewrite_rules();
}

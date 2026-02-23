<?php
/**
 * Plugin Name: Event Image Manager
 * Description: A plugin to manage event images, custom post types, and more.
 */

// Include necessary files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-post-type.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-admin.php';

// Initialize the plugin
function eim_init() {
    // Custom post type registration
    new Event_Post_Type();
    // Admin functionality
    new Event_Admin();
}
add_action( 'init', 'eim_init' );

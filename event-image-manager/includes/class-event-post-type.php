<?php
/**
 * Class Event_Post_Type
 *
 * Registers the 'event' custom post type.
 */
class Event_Post_Type {

    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );
    }

    public function register() {
        $labels = array(
            'name'               => __( 'Events', 'event-image-manager' ),
            'singular_name'      => __( 'Event', 'event-image-manager' ),
            'add_new'            => __( 'Add New', 'event-image-manager' ),
            'add_new_item'       => __( 'Add New Event', 'event-image-manager' ),
            'edit_item'          => __( 'Edit Event', 'event-image-manager' ),
            'new_item'           => __( 'New Event', 'event-image-manager' ),
            'view_item'          => __( 'View Event', 'event-image-manager' ),
            'search_items'       => __( 'Search Events', 'event-image-manager' ),
            'not_found'          => __( 'No events found', 'event-image-manager' ),
            'not_found_in_trash' => __( 'No events found in Trash', 'event-image-manager' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'event' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-camera',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
        );

        register_post_type( 'event', $args );
    }
}

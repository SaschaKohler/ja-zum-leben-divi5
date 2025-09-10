<?php
/**
 * Divi Child Theme functions
 *
 * @package divi-child
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue parent and child theme styles.
 */
function divi_child_enqueue_styles() {
    // Get versions to help with cache busting during development
    $parent_version = wp_get_theme( 'Divi' )->get( 'Version' );
    $child_version  = wp_get_theme()->get( 'Version' );

    // Parent style
    wp_enqueue_style(
        'divi-parent-style',
        get_template_directory_uri() . '/style.css',
        array(),
        $parent_version
    );

    // Child style
    wp_enqueue_style(
        'divi-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'divi-parent-style' ),
        $child_version
    );
}
add_action( 'wp_enqueue_scripts', 'divi_child_enqueue_styles' );

/**
 * Load child theme textdomain.
 */
function divi_child_load_textdomain() {
    load_child_theme_textdomain( 'divi-child', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'divi_child_load_textdomain' );

/**
 * Register Custom Post Type: Events
 */
function divi_child_register_events_cpt() {
    $labels = array(
        'name'                  => _x( 'Events', 'Post type general name', 'divi-child' ),
        'singular_name'         => _x( 'Event', 'Post type singular name', 'divi-child' ),
        'menu_name'             => _x( 'Events', 'Admin Menu text', 'divi-child' ),
        'name_admin_bar'        => _x( 'Event', 'Add New on Toolbar', 'divi-child' ),
        'add_new'               => __( 'Neu hinzufügen', 'divi-child' ),
        'add_new_item'          => __( 'Neues Event hinzufügen', 'divi-child' ),
        'new_item'              => __( 'Neues Event', 'divi-child' ),
        'edit_item'             => __( 'Event bearbeiten', 'divi-child' ),
        'view_item'             => __( 'Event ansehen', 'divi-child' ),
        'all_items'             => __( 'Alle Events', 'divi-child' ),
        'search_items'          => __( 'Events durchsuchen', 'divi-child' ),
        'parent_item_colon'     => __( 'Übergeordnete Events:', 'divi-child' ),
        'not_found'             => __( 'Keine Events gefunden.', 'divi-child' ),
        'not_found_in_trash'    => __( 'Keine Events im Papierkorb gefunden.', 'divi-child' ),
        'featured_image'        => _x( 'Event-Titelbild', 'Overrides the "Featured Image" phrase', 'divi-child' ),
        'set_featured_image'    => _x( 'Titelbild festlegen', 'Overrides the "Set featured image" phrase', 'divi-child' ),
        'remove_featured_image' => _x( 'Titelbild entfernen', 'Overrides the "Remove featured image" phrase', 'divi-child' ),
        'use_featured_image'    => _x( 'Als Titelbild verwenden', 'Overrides the "Use as featured image" phrase', 'divi-child' ),
        'archives'              => _x( 'Event-Archiv', 'The post type archive label', 'divi-child' ),
        'insert_into_item'      => _x( 'In Event einfügen', 'Overrides the "Insert into post" phrase', 'divi-child' ),
        'uploaded_to_this_item' => _x( 'Zu diesem Event hochgeladen', 'Overrides the "Uploaded to this post" phrase', 'divi-child' ),
        'filter_items_list'     => _x( 'Event-Liste filtern', 'Screen reader text', 'divi-child' ),
        'items_list_navigation' => _x( 'Navigation der Event-Liste', 'Screen reader text', 'divi-child' ),
        'items_list'            => _x( 'Event-Liste', 'Screen reader text', 'divi-child' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true, // Gutenberg + REST API
        'rest_base'          => 'events',
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'events', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-calendar-alt',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
    );

    register_post_type( 'events', $args );
}
add_action( 'init', 'divi_child_register_events_cpt' );

/**
 * Register meta fields for Events CPT with REST exposure
 */
function divi_child_register_events_meta() {
    $post_type = 'events';

    register_post_meta( $post_type, 'event_date', array(
        'single'       => true,
        'type'         => 'string',
        'show_in_rest' => true,
        'auth_callback'=> '__return_true',
    ) );

    register_post_meta( $post_type, 'event_day', array(
        'single'       => true,
        'type'         => 'string',
        'show_in_rest' => true,
        'auth_callback'=> '__return_true',
    ) );

    register_post_meta( $post_type, 'event_day_number', array(
        'single'       => true,
        'type'         => 'integer',
        'show_in_rest' => true,
        'auth_callback'=> '__return_true',
    ) );

    register_post_meta( $post_type, 'event_location', array(
        'single'       => true,
        'type'         => 'string',
        'show_in_rest' => true,
        'auth_callback'=> '__return_true',
    ) );

    register_post_meta( $post_type, 'event_time', array(
        'single'       => true,
        'type'         => 'string',
        'show_in_rest' => true,
        'auth_callback'=> '__return_true',
    ) );
}
add_action( 'init', 'divi_child_register_events_meta' );

/**
 * Flush rewrite rules on theme switch to register CPT permalinks.
 */
function divi_child_flush_rewrite_on_switch() {
    divi_child_register_events_cpt();
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'divi_child_flush_rewrite_on_switch' );

/**
 * ACF Local JSON (Auto-Sync): save and load JSON in child theme directory
 */
function divi_child_acf_json_save_point( $path ) {
    return get_stylesheet_directory() . '/acf-json';
}
add_filter( 'acf/settings/save_json', 'divi_child_acf_json_save_point' );

function divi_child_acf_json_load_point( $paths ) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
}
add_filter( 'acf/settings/load_json', 'divi_child_acf_json_load_point' );

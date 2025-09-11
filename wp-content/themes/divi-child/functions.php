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

/**
 * Output JSON-LD schema for Events on single event pages
 */
function divi_child_output_event_schema() {
    if ( ! is_singular( 'events' ) ) {
        return;
    }

    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return;
    }

    // Collect core data
    $name        = get_the_title( $post_id );
    $description = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 40 );
    $image       = get_the_post_thumbnail_url( $post_id, 'full' );

    // ACF meta
    $date = get_post_meta( $post_id, 'event_date', true );      // Y-m-d
    $time = get_post_meta( $post_id, 'event_time', true );      // H:i
    $loc  = get_post_meta( $post_id, 'event_location', true );  // string city/location

    // Build startDate in ISO 8601
    $start = null;
    if ( $date ) {
        $start = $date . ( $time ? 'T' . $time . ':00' : 'T00:00:00' );
    }

    $location = null;
    if ( $loc ) {
        $location = array(
            '@type' => 'Place',
            'name'  => $loc,
            // You can expand with address later via extra ACF fields
        );
    }

    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'Event',
        'name'       => $name,
        'description'=> $description,
        'startDate'  => $start,
        'eventStatus'=> 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    );

    if ( $image ) {
        $schema['image'] = array( $image );
    }
    if ( $location ) {
        $schema['location'] = $location;
    }

    // Offer placeholder (free event by default). Extend with price ACF later if needed.
    $schema['offers'] = array(
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'EUR',
        'availability' => 'https://schema.org/InStock',
        'url' => get_permalink( $post_id ),
    );

    echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'divi_child_output_event_schema', 30 );

/**
 * Output Organization & WebSite schema on homepage
 */
function divi_child_output_site_schema() {
    if ( ! is_front_page() && ! is_home() ) {
        return;
    }

    // Skip if typical SEO plugins are active (they will handle schema)
    if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
        return;
    }

    $site_name = get_bloginfo( 'name' );
    $site_url  = home_url( '/' );
    $logo_id   = get_theme_mod( 'custom_logo' );
    $logo_src  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

    $schemas = array();

    $schemas[] = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => $site_name,
        'url'      => $site_url,
        'logo'     => $logo_src ?: null,
    );

    $schemas[] = array(
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => $site_name,
        'url'      => $site_url,
        'potentialAction' => array(
            '@type' => 'SearchAction',
            'target' => $site_url . '?s={search_term_string}',
            'query-input' => 'required name=search_term_string'
        )
    );

    echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'divi_child_output_site_schema', 25 );

/**
 * Basic Open Graph & Twitter Card tags (if no SEO plugin is active)
 */
function divi_child_output_social_meta() {
    if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
        return; // SEO plugin will handle
    }

    $title = wp_get_document_title();
    $desc  = get_bloginfo( 'description' );
    $url   = ( is_singular() ) ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
    $image = '';
    if ( is_singular() && has_post_thumbnail() ) {
        $image = get_the_post_thumbnail_url( null, 'full' );
    } elseif ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
        $image = get_site_icon_url( 512 );
    }

    echo "\n<meta property=\"og:title\" content=\"" . esc_attr( $title ) . "\" />\n";
    echo "<meta property=\"og:description\" content=\"" . esc_attr( $desc ) . "\" />\n";
    echo "<meta property=\"og:type\" content=\"" . ( is_singular() ? 'article' : 'website' ) . "\" />\n";
    echo "<meta property=\"og:url\" content=\"" . esc_url( $url ) . "\" />\n";
    if ( $image ) {
        echo "<meta property=\"og:image\" content=\"" . esc_url( $image ) . "\" />\n";
    }
    echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    echo "<meta name=\"twitter:title\" content=\"" . esc_attr( $title ) . "\" />\n";
    echo "<meta name=\"twitter:description\" content=\"" . esc_attr( $desc ) . "\" />\n";
    if ( $image ) {
        echo "<meta name=\"twitter:image\" content=\"" . esc_url( $image ) . "\" />\n";
    }
}
add_action( 'wp_head', 'divi_child_output_social_meta', 5 );

/**
 * Provide GTM container ID to jzl-consent via filter
 */
add_filter( 'jzl_consent_gtm_id', function( $id ) {
    // If already defined elsewhere, keep it; otherwise set our container.
    if ( ! $id ) {
        $id = 'GTM-P5GZQHVF';
    }
    return $id;
} );

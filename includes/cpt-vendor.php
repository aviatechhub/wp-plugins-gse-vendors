<?php
// Register the Vendor custom post type.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_register_cpt' ) ) {
    function gse_vendors_register_cpt() {
        $labels = array(
            'name' => 'Vendors',
            'singular_name' => 'Vendor',
            'menu_name' => 'Vendors',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Vendor',
            'edit_item' => 'Edit Vendor',
            'new_item' => 'New Vendor',
            'view_item' => 'View Vendor',
            'search_items' => 'Search Vendors',
            'not_found' => 'No vendors found',
            'not_found_in_trash' => 'No vendors found in Trash',
            'all_items' => 'All Vendors',
            'archives' => 'Vendor Archives',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'rest_base' => 'vendors',
            'supports' => array( 'title', 'editor', 'thumbnail' ),
            'rewrite' => array(
                'slug' => 'vendors',
                'with_front' => false,
            ),
            'has_archive' => 'vendors',
        );

        if ( function_exists( 'register_post_type' ) ) {
            register_post_type( 'vendor', $args );
        }
    }
}




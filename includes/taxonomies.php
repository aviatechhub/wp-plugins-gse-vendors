<?php
// Register taxonomies for Vendor CPT.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_register_taxonomies' ) ) {
    function gse_vendors_register_taxonomies() {
        $location_labels = array(
            'name' => 'Locations',
            'singular_name' => 'Location',
        );

        $cert_labels = array(
            'name' => 'Certifications',
            'singular_name' => 'Certification',
        );

        $location_args = array(
            'labels' => $location_labels,
            'hierarchical' => true,
            'show_in_rest' => true,
        );

        $cert_args = array(
            'labels' => $cert_labels,
            'hierarchical' => false,
            'show_in_rest' => true,
        );

        register_taxonomy( 'gse_location', array( 'vendor' ), $location_args );
        register_taxonomy( 'gse_certification', array( 'vendor' ), $cert_args );
    }
}



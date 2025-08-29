<?php
/*
Plugin Name: GSE Vendors
Description: Admin-only UI and REST endpoints for managing vendor basic information.
Version: 0.1.0
Author: GSE
License: GPLv2 or later
Text Domain: gse-vendors
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Include plugin components from the includes directory if present.
$gse_vendors_includes_dir = __DIR__ . '/includes/';

$gse_vendors_files = array(
    // Custom Post Type and Taxonomies.
    'cpt-vendor.php',
    'taxonomies.php',

    // Meta registration and sanitizers.
    'meta.php',

    // REST endpoints.
    'rest.php',

    // WP Admin UI.
    'admin.php',

    // Roles/Capabilities mapping and helpers.
    'roles-caps.php',

    // Activation routines (db/migrations).
    'activator.php',
);

foreach ( $gse_vendors_files as $gse_vendors_file ) {
    $gse_vendors_path = $gse_vendors_includes_dir . $gse_vendors_file;
    if ( file_exists( $gse_vendors_path ) ) {
        require_once $gse_vendors_path;
    }
}



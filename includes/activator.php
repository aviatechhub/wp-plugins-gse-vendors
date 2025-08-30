<?php
// Activation routines: create custom tables.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_activate' ) ) {
    function gse_vendors_activate() {
        global $wpdb;

        $table_name = isset( $wpdb ) && isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
        $charset_collate = isset( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';

        if ( defined( 'ABSPATH' ) ) {
            $upgrade_path = rtrim( constant( 'ABSPATH' ), '/\\' ) . '/wp-admin/includes/upgrade.php';
            if ( file_exists( $upgrade_path ) ) {
                require_once $upgrade_path;
            }
        }

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(32) NOT NULL,
            assigned_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY vendor_user_unique (vendor_id, user_id),
            KEY vendor_id (vendor_id),
            KEY user_id (user_id),
            KEY role (role)
        ) {$charset_collate};";

        if ( function_exists( 'dbDelta' ) ) {
            call_user_func( 'dbDelta', $sql );
        }
    }
}



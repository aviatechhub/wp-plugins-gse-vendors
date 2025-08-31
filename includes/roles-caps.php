<?php
// Roles, capabilities, and membership seeding for Vendors.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_seed_owner' ) ) {
    function gse_vendors_seed_owner( $post_id, $post ) {
        global $wpdb;

        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
            return;
        }

        if ( ! $post || ! is_object( $post ) ) {
            return;
        }

        $author_id = isset( $post->post_author ) ? (int) $post->post_author : 0;
        if ( $author_id <= 0 ) {
            return;
        }

        $table_name = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';

        // Check if any membership exists for this vendor.
        $count = 0;
        if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
            $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE vendor_id = %d", (int) $post_id );
            $count = (int) $wpdb->get_var( $sql );
        }

        if ( $count > 0 ) {
            return; // Membership already exists; do not seed.
        }

        // Seed owner membership.
        $now_gmt = gmdate( 'Y-m-d H:i:s' );
        if ( method_exists( $wpdb, 'insert' ) ) {
            $wpdb->insert(
                $table_name,
                array(
                    'vendor_id' => (int) $post_id,
                    'user_id' => $author_id,
                    'role' => 'owner',
                    'assigned_at' => $now_gmt,
                ),
                array( '%d', '%d', '%s', '%s' )
            );
        }
    }
}

// Hook on publish of vendor posts to seed owner if missing.
if ( function_exists( 'add_action' ) ) {
    call_user_func( 'add_action', 'publish_vendor', 'gse_vendors_seed_owner', 10, 2 );
}


// Internal role catalog (filterable)
if ( ! function_exists( 'gse_vendors_get_role_catalog' ) ) {
    function gse_vendors_get_role_catalog() {
        $roles = array( 'owner', 'manager', 'editor', 'viewer' );
        if ( function_exists( 'apply_filters' ) ) {
            return (array) call_user_func( 'apply_filters', 'gse_vendors_role_catalog', $roles );
        }
        return $roles;
    }
}



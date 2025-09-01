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
add_action( 'publish_vendor', 'gse_vendors_seed_owner', 10, 2 );


// Internal role catalog (filterable)
if ( ! function_exists( 'gse_vendors_get_role_catalog' ) ) {
    function gse_vendors_get_role_catalog() {
        $roles = array( 'owner', 'manager', 'editor', 'viewer' );
        return (array) apply_filters( 'gse_vendors_role_catalog', $roles );
    }
}

// Capability matrix: role -> capabilities (filterable)
if ( ! function_exists( 'gse_vendors_get_capability_matrix' ) ) {
    function gse_vendors_get_capability_matrix() {
        $matrix = array(
            'owner' => array(
                'can_manage_members' => true,
                'can_edit_basic' => true,
                'can_delete_vendor' => true,
            ),
            'manager' => array(
                'can_manage_members' => true,
                'can_edit_basic' => true,
                'can_delete_vendor' => false,
            ),
            'editor' => array(
                'can_manage_members' => false,
                'can_edit_basic' => true,
                'can_delete_vendor' => false,
            ),
            'viewer' => array(
                'can_manage_members' => false,
                'can_edit_basic' => false,
                'can_delete_vendor' => false,
            ),
        );

        return (array) apply_filters( 'gse_vendors_capability_matrix', $matrix );
    }
}

// Guard function for REST permissions: does a user have a capability on a vendor?
if ( ! function_exists( 'gse_vendors_user_can_vendor' ) ) {
    function gse_vendors_user_can_vendor( $user_id, $vendor_id, $capability ) {
        $user_id = (int) $user_id;
        $vendor_id = (int) $vendor_id;
        $capability = is_string( $capability ) ? $capability : '';

        if ( $user_id <= 0 || $vendor_id <= 0 || $capability === '' ) {
            return false;
        }

        // Admins always allowed.
        if ( user_can( $user_id, 'administrator' ) ) {
            return true;
        }

        // Look up membership role.
        global $wpdb;
        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
            return false;
        }

        $table_name = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
        $role = '';
        if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
            $sql = $wpdb->prepare( "SELECT role FROM {$table_name} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $vendor_id, $user_id );
            $role = (string) $wpdb->get_var( $sql );
        }

        if ( $role === '' ) {
            return false;
        }

        // Check capability matrix.
        if ( function_exists( 'gse_vendors_get_capability_matrix' ) ) {
            $matrix = gse_vendors_get_capability_matrix();
            if ( isset( $matrix[ $role ] ) && isset( $matrix[ $role ][ $capability ] ) ) {
                return (bool) $matrix[ $role ][ $capability ];
            }
        }

        return false;
    }
}



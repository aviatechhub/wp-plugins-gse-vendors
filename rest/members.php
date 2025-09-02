<?php
// REST routes for managing vendor members (placeholder for future implementation).
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_register_members_rest_routes' ) ) {
    function gse_vendors_register_members_rest_routes() {
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\\d+)/members', array(
            'methods' => 'GET',
            'permission_callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                if ( $post_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                }
                // Admins allowed; otherwise require capability via role system
                if ( current_user_can( 'administrator' ) ) {
                    return true;
                }
                if ( get_current_user_id() && gse_vendors_user_can_vendor( get_current_user_id(), $post_id, 'can_manage_members' ) ) {
                    return true;
                }
                return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
            },
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'required' => false,
                    'default' => 100,
                    'minimum' => 1,
                    'maximum' => 200,
                ),
                'page' => array(
                    'type' => 'integer',
                    'required' => false,
                    'default' => 1,
                    'minimum' => 1,
                ),
            ),
            'callback' => function ( $request ) {
                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    return new WP_Error( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                }

                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $per_page = isset( $request['per_page'] ) ? max( 1, min( 200, (int) $request['per_page'] ) ) : 100;
                $page = isset( $request['page'] ) ? max( 1, (int) $request['page'] ) : 1;
                $offset = ( $page - 1 ) * $per_page;

                $members = GSE_Vendor::list_members( $post_id, $per_page, $offset );
                if ( is_wp_error( $members ) ) {
                    return $members;
                }

                return array( 'items' => $members );
            },
        ) );

        // Add member: POST /gse/v1/vendors/{id}/members
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\\d+)/members', array(
            'methods' => 'POST',
            'permission_callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                if ( $post_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                }

                // Admins allowed
                if ( current_user_can( 'administrator' ) ) {
                    return true;
                }

                $current_user_id = (int) get_current_user_id();
                if ( $current_user_id <= 0 ) {
                    return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
                }

                // Must be able to manage members at minimum.
                if ( ! gse_vendors_user_can_vendor( $current_user_id, $post_id, 'can_manage_members' ) ) {
                    return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
                }

                // If creating another owner, only owners may do so.
                $requested_role = isset( $request['role'] ) ? (string) $request['role'] : '';
                if ( $requested_role === 'owner' ) {
                    // Look up the caller's role for this vendor
                    global $wpdb;
                    $caller_role = '';
                    if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
                        $table_name = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
                        $sql = $wpdb->prepare( "SELECT role FROM {$table_name} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $post_id, $current_user_id );
                        $caller_role = (string) $wpdb->get_var( $sql );
                    }
                    if ( $caller_role !== 'owner' ) {
                        return new WP_Error( 'gse_forbidden', 'Only an owner can assign the owner role', array( 'status' => 403 ) );
                    }
                }

                return true;
            },
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
                'role' => array(
                    'type' => 'string',
                    'required' => true,
                ),
                'email' => array(
                    'type' => 'string',
                    'format' => 'email',
                    'required' => true,
                ),
                'display_name' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'password' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
            'callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $role = isset( $request['role'] ) ? (string) $request['role'] : '';
                $email = isset( $request['email'] ) ? (string) $request['email'] : '';
                $display_name = isset( $request['display_name'] ) ? (string) $request['display_name'] : '';
                $password = isset( $request['password'] ) ? (string) $request['password'] : '';

                // Validate email when WordPress helper not available
                if ( $email === '' ) {
                    return new WP_Error( 'gse_validation_error', 'Email is required', array( 'status' => 422 ) );
                }

                $result = GSE_Vendor::add_member( $post_id, array(
                    'role' => $role,
                    'email' => $email,
                    'display_name' => $display_name,
                    'password' => $password,
                ) );

                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                return $result;
            },
        ) );

        // Update member role: PATCH /gse/v1/vendors/{id}/members/{user_id}
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\\d+)/members/(?P<user_id>\\d+)', array(
            'methods' => 'PATCH',
            'permission_callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $target_user_id = isset( $request['user_id'] ) ? (int) $request['user_id'] : 0;
                if ( $post_id <= 0 || $target_user_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor or user id', array( 'status' => 400 ) );
                }

                // Admins always allowed
                if ( current_user_can( 'administrator' ) ) {
                    return true;
                }

                $current_user_id = (int) get_current_user_id();
                if ( $current_user_id <= 0 ) {
                    return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
                }

                $new_role = isset( $request['role'] ) ? (string) $request['role'] : '';
                global $wpdb;
                $table_name = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
                $caller_role = '';
                $target_current_role = '';
                if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
                    $sql_caller = $wpdb->prepare( "SELECT role FROM {$table_name} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $post_id, $current_user_id );
                    $caller_role = (string) $wpdb->get_var( $sql_caller );
                    $sql_target = $wpdb->prepare( "SELECT role FROM {$table_name} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $post_id, $target_user_id );
                    $target_current_role = (string) $wpdb->get_var( $sql_target );
                }

                // If promotion to owner or demotion from owner, caller must be owner
                if ( $new_role === 'owner' || $target_current_role === 'owner' ) {
                    if ( $caller_role !== 'owner' ) {
                        return new WP_Error( 'gse_forbidden', 'Only an owner can change ownership roles', array( 'status' => 403 ) );
                    }
                    return true;
                }

                // Managers (and owners) can change non-owner roles
                if ( gse_vendors_user_can_vendor( $current_user_id, $post_id, 'can_manage_members' ) ) {
                    return true;
                }

                return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
            },
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
                'user_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
                'role' => array(
                    'type' => 'string',
                    'required' => true,
                ),
            ),
            'callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $user_id = isset( $request['user_id'] ) ? (int) $request['user_id'] : 0;
                $role = isset( $request['role'] ) ? (string) $request['role'] : '';

                $result = GSE_Vendor::update_member_role( $post_id, $user_id, $role );
                return $result;
            },
        ) );

        // Remove member: DELETE /gse/v1/vendors/{id}/members/{user_id}
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\\d+)/members/(?P<user_id>\\d+)', array(
            'methods' => 'DELETE',
            'permission_callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $target_user_id = isset( $request['user_id'] ) ? (int) $request['user_id'] : 0;
                if ( $post_id <= 0 || $target_user_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor or user id', array( 'status' => 400 ) );
                }

                // Admins always allowed
                if ( current_user_can( 'administrator' ) ) {
                    return true;
                }

                $current_user_id = (int) get_current_user_id();
                if ( $current_user_id <= 0 ) {
                    return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
                }

                // Retrieve target's current role
                global $wpdb;
                $target_role = '';
                $caller_role = '';
                if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
                    $table_name = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
                    $sql_target = $wpdb->prepare( "SELECT role FROM {$table_name} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $post_id, $target_user_id );
                    $target_role = (string) $wpdb->get_var( $sql_target );
                    $sql_caller = $wpdb->prepare( "SELECT role FROM {$table_name} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $post_id, $current_user_id );
                    $caller_role = (string) $wpdb->get_var( $sql_caller );
                }

                if ( $target_role === '' ) {
                    return new WP_Error( 'gse_not_found', 'Membership not found', array( 'status' => 404 ) );
                }

                // If target is owner, only an owner can remove; last-owner guard enforced in model
                if ( $target_role === 'owner' && $caller_role !== 'owner' ) {
                    return new WP_Error( 'gse_forbidden', 'Only an owner can remove an owner', array( 'status' => 403 ) );
                }

                // Otherwise require can_manage_members
                if ( ! gse_vendors_user_can_vendor( $current_user_id, $post_id, 'can_manage_members' ) ) {
                    return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
                }

                return true;
            },
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
                'user_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
            ),
            'callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $user_id = isset( $request['user_id'] ) ? (int) $request['user_id'] : 0;

                $result = GSE_Vendor::remove_member( $post_id, $user_id );
                return $result;
            },
        ) );

        // Get my role: GET /gse/v1/vendors/{id}/my-role
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\\d+)/my-role', array(
            'methods' => 'GET',
            'permission_callback' => function ( $request ) {
                // Any logged-in user can query their own role; admins allowed. Anonymous gets null.
                return true;
            },
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
            ),
            'callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $user_id = (int) get_current_user_id();

                $role = GSE_Vendor::get_member_role( $post_id, $user_id );
                if ( is_wp_error( $role ) ) {
                    return $role;
                }
                return array( 'role' => $role );
            },
        ) );
    }
}



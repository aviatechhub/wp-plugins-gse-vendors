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
    }
}



<?php
// REST enhancements for Vendor CPT.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_register_rest_routes' ) ) {
    function gse_vendors_register_rest_routes() {
        // Computed field: basic_info_summary
        register_rest_field( 'vendor', 'basic_info_summary', array(
            'get_callback' => function ( $object, $field_name, $request ) {
                $post_id = isset( $object['id'] ) ? (int) $object['id'] : 0;
                if ( $post_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                }
                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    return new WP_Error( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                }

                $vendor = GSE_Vendor::getById( $post_id, false );
                if ( ! $vendor ) {
                    return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
                }

                return $vendor->get_basic_info_summary();
            },
            'schema' => array(
                'type' => 'object',
                'readonly' => true,
            ),
        ) );

        // Custom route: get single vendor by ID
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\d+)', array(
            'methods' => 'GET',
            'permission_callback' => function () { return true; },
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ),
            ),
            'callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                if ( $post_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                }

                $post = null;
                $post = get_post( $post_id );

                $is_vendor = $post && isset( $post->post_type ) && $post->post_type === 'vendor';
                $is_published = $post && isset( $post->post_status ) && $post->post_status === 'publish';
                if ( ! $is_vendor || ! $is_published ) {
                    return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
                }

                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    return new WP_Error( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                }

                $vendor = GSE_Vendor::getById( $post_id, true );
                if ( ! $vendor ) {
                    return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
                }

                return $vendor->to_array();
            },
        ) );

        // Custom route: create vendor
        register_rest_route( 'gse/v1', '/vendors', array(
            'methods' => 'POST',
            'permission_callback' => function () {
                if ( function_exists( 'current_user_can' ) && call_user_func( 'current_user_can', 'administrator' ) ) {
                    return true;
                }
                return new WP_Error( 'gse_forbidden', 'Forbidden', array( 'status' => 403 ) );
            },
            'args' => array(
                'title' => array(
                    'type' => 'string',
                    'required' => true,
                ),
                'status' => array(
                    'type' => 'string',
                    'required' => false,
                    'default' => 'publish',
                ),
                'meta' => array(
                    'type' => 'object',
                    'required' => false,
                ),
                'locations' => array(
                    'type' => 'array',
                    'required' => false,
                ),
                'certifications' => array(
                    'type' => 'array',
                    'required' => false,
                ),
            ),
            'callback' => function ( $request ) {
                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    return new WP_Error( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                }

                $args = array(
                    'title' => isset( $request['title'] ) ? (string) $request['title'] : '',
                    'status' => isset( $request['status'] ) ? (string) $request['status'] : 'publish',
                    'meta' => isset( $request['meta'] ) && is_array( $request['meta'] ) ? $request['meta'] : array(),
                    'locations' => isset( $request['locations'] ) && is_array( $request['locations'] ) ? $request['locations'] : array(),
                    'certifications' => isset( $request['certifications'] ) && is_array( $request['certifications'] ) ? $request['certifications'] : array(),
                );

                $vendor = GSE_Vendor::create( $args );
                if ( is_object( $vendor ) && isset( $vendor->id ) ) {
                    return $vendor->to_array();
                }
                if ( is_wp_error( $vendor ) ) {
                    return $vendor;
                }
                if ( class_exists( 'WP_Error' ) ) {
                    return new WP_Error( 'gse_create_failed', 'Failed to create vendor', array( 'status' => 500 ) );
                }
            },
        ) );

        // Custom route: update vendor
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\\d+)', array(
            'methods' => 'PATCH',
            'permission_callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                if ( $post_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                }
                // Admins allowed; otherwise require capability via role system
                if ( current_user_can( 'administrator' ) ) {
                    return true;
                }
                if ( get_current_user_id() && gse_vendors_user_can_vendor( get_current_user_id(), $post_id, 'can_edit_basic' ) ) {
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
                'title' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'status' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'meta' => array(
                    'type' => 'object',
                    'required' => false,
                ),
                'locations' => array(
                    'type' => 'array',
                    'required' => false,
                ),
                'certifications' => array(
                    'type' => 'array',
                    'required' => false,
                ),
            ),
            'callback' => function ( $request ) {
                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    return new WP_Error( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                }

                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $args = array(
                    'title' => array_key_exists( 'title', $request ) ? (string) $request['title'] : null,
                    'status' => array_key_exists( 'status', $request ) ? (string) $request['status'] : null,
                    'meta' => isset( $request['meta'] ) && is_array( $request['meta'] ) ? $request['meta'] : null,
                    'locations' => isset( $request['locations'] ) && is_array( $request['locations'] ) ? $request['locations'] : null,
                    'certifications' => isset( $request['certifications'] ) && is_array( $request['certifications'] ) ? $request['certifications'] : null,
                );

                $vendor = GSE_Vendor::update( $post_id, $args );
                if ( is_object( $vendor ) && isset( $vendor->id ) ) {
                    return $vendor->to_array();
                }
                if ( is_wp_error( $vendor ) ) {
                    return $vendor;
                }
                if ( class_exists( 'WP_Error' ) ) {
                    return new WP_Error( 'gse_update_failed', 'Failed to update vendor', array( 'status' => 500 ) );
                }
            },
        ) );

        // Custom route: delete vendor
        register_rest_route( 'gse/v1', '/vendors/(?P<id>\\d+)', array(
            'methods' => 'DELETE',
            'permission_callback' => function ( $request ) {
                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                if ( $post_id <= 0 ) {
                    return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                }
                if ( current_user_can( 'administrator' ) ) {
                    return true;
                }
                if ( get_current_user_id() && gse_vendors_user_can_vendor( get_current_user_id(), $post_id, 'can_delete_vendor' ) ) {
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
                'force' => array(
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                ),
            ),
            'callback' => function ( $request ) {
                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    return new WP_Error( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                }

                $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                $force = isset( $request['force'] ) ? (bool) $request['force'] : true;

                // Ensure exists before attempting delete
                $post = get_post( $post_id );
                if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                    return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
                }

                $result = GSE_Vendor::delete( $post_id, $force );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                return $result; // { deleted: true, id }
            },
        ) );

        // Custom route: search vendors
        register_rest_route( 'gse/v1', '/vendors/search', array(
            'methods' => 'GET',
            'permission_callback' => function () { return true; },
            'args' => array(
                'q' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'location' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'cert' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'required' => false,
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ),
                'page' => array(
                    'type' => 'integer',
                    'required' => false,
                    'default' => 1,
                    'minimum' => 1,
                ),
            ),
            'callback' => function ( $request ) {
                $q = isset( $request['q'] ) ? (string) $request['q'] : '';
                $location = isset( $request['location'] ) ? (string) $request['location'] : '';
                $cert = isset( $request['cert'] ) ? (string) $request['cert'] : '';
                $per_page = isset( $request['per_page'] ) ? max( 1, min( 100, (int) $request['per_page'] ) ) : 10;
                $page = isset( $request['page'] ) ? max( 1, (int) $request['page'] ) : 1;

                $tax_query = array( 'relation' => 'AND' );
                if ( $location !== '' ) {
                    $field = is_numeric( $location ) ? 'term_id' : 'slug';
                    $tax_query[] = array(
                        'taxonomy' => 'gse_location',
                        'field' => $field,
                        'terms' => is_numeric( $location ) ? (int) $location : $location,
                    );
                }
                if ( $cert !== '' ) {
                    $field = is_numeric( $cert ) ? 'term_id' : 'slug';
                    $tax_query[] = array(
                        'taxonomy' => 'gse_certification',
                        'field' => $field,
                        'terms' => is_numeric( $cert ) ? (int) $cert : $cert,
                    );
                }

                $query_args = array(
                    'post_type' => 'vendor',
                    'post_status' => 'publish',
                    's' => $q,
                    'posts_per_page' => $per_page,
                    'paged' => $page,
                );
                if ( count( $tax_query ) > 1 ) {
                    $query_args['tax_query'] = $tax_query;
                }

                $items = array();
                $total = 0;
                $pages = 0;

                // Run query
                $wp_query = new WP_Query( $query_args );
                $total = (int) ( isset( $wp_query->found_posts ) ? $wp_query->found_posts : 0 );
                $pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

                if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
                    foreach ( $wp_query->posts as $post ) {
                        $post_id = isset( $post->ID ) ? (int) $post->ID : 0;
                        if ( $post_id <= 0 ) { continue; }

                        if ( ! class_exists( 'GSE_Vendor' ) ) {
                            return new WP_Error( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                        }

                        $vendor = GSE_Vendor::getById( $post_id, true );
                        if ( ! $vendor ) {
                            return new WP_Error( 'gse_not_found', 'Vendor not found during search item build', array( 'status' => 404, 'post_id' => $post_id ) );
                        }

                        $items[] = array(
                            'id' => $vendor->id,
                            'title' => $vendor->title,
                            'permalink' => $vendor->permalink,
                            'basic_info_summary' => $vendor->get_basic_info_summary(),
                        );
                    }
                }

                return array(
                    'items' => $items,
                    'total' => $total,
                    'pages' => $pages,
                );
            },
        ) );
    }
}




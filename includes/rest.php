<?php
// REST enhancements for Vendor CPT.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_register_rest_routes' ) ) {
    function gse_vendors_register_rest_routes() {
        if ( ! function_exists( 'register_rest_field' ) ) {
            return;
        }

        // Computed field: basic_info_summary
        call_user_func( 'register_rest_field', 'vendor', 'basic_info_summary', array(
            'get_callback' => function ( $object, $field_name, $request ) {
                $post_id = isset( $object['id'] ) ? (int) $object['id'] : 0;
                if ( $post_id <= 0 ) {
                    if ( class_exists( 'WP_Error' ) ) {
                        $wp_error_class = 'WP_Error';
                        return new $wp_error_class( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                    }
                    return array( 'error' => 'Invalid vendor id', 'status' => 400 );
                }
                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    if ( class_exists( 'WP_Error' ) ) {
                        $wp_error_class = 'WP_Error';
                        return new $wp_error_class( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                    }
                    return array( 'error' => 'Vendor model unavailable', 'status' => 500 );
                }

                $vendor = call_user_func( array( 'GSE_Vendor', 'load' ), $post_id, false );
                if ( ! $vendor ) {
                    if ( class_exists( 'WP_Error' ) ) {
                        $wp_error_class = 'WP_Error';
                        return new $wp_error_class( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
                    }
                    return array( 'error' => 'Vendor not found', 'status' => 404 );
                }

                return $vendor->get_basic_info_summary();
            },
            'schema' => array(
                'type' => 'object',
                'readonly' => true,
            ),
        ) );

        // Custom route: get single vendor by ID
        call_user_func( 'register_rest_route', 'gse/v1', '/vendors/(?P<id>\d+)', array(
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
                    if ( class_exists( 'WP_Error' ) ) {
                        $wp_error_class = 'WP_Error';
                        return new $wp_error_class( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
                    }
                    return array( 'error' => 'Invalid vendor id', 'status' => 400 );
                }

                $post = null;
                if ( function_exists( 'get_post' ) ) {
                    $post = call_user_func( 'get_post', $post_id );
                }

                $is_vendor = $post && isset( $post->post_type ) && $post->post_type === 'vendor';
                $is_published = $post && isset( $post->post_status ) && $post->post_status === 'publish';
                if ( ! $is_vendor || ! $is_published ) {
                    if ( class_exists( 'WP_Error' ) ) {
                        $wp_error_class = 'WP_Error';
                        return new $wp_error_class( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
                    }
                    return array( 'error' => 'Vendor not found', 'status' => 404 );
                }

                if ( ! class_exists( 'GSE_Vendor' ) ) {
                    $wp_error_class = 'WP_Error';
                    return new $wp_error_class( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                }

                $vendor = call_user_func( array( 'GSE_Vendor', 'load' ), $post_id, true );
                if ( ! $vendor ) {
                    if ( class_exists( 'WP_Error' ) ) {
                        $wp_error_class = 'WP_Error';
                        return new $wp_error_class( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
                    }
                    return array( 'error' => 'Vendor not found', 'status' => 404 );
                }

                return $vendor->to_array();
            },
        ) );

        // Custom route: search vendors
        call_user_func( 'register_rest_route', 'gse/v1', '/vendors/search', array(
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
                if ( class_exists( 'WP_Query' ) ) {
                    $wp_query_class = 'WP_Query';
                    $wp_query = new $wp_query_class( $query_args );
                    $total = (int) ( isset( $wp_query->found_posts ) ? $wp_query->found_posts : 0 );
                    $pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

                    if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
                        foreach ( $wp_query->posts as $post ) {
                            $post_id = isset( $post->ID ) ? (int) $post->ID : 0;
                            if ( $post_id <= 0 ) { continue; }

                            if ( ! class_exists( 'GSE_Vendor' ) ) {
                                if ( class_exists( 'WP_Error' ) ) {
                                    $wp_error_class = 'WP_Error';
                                    return new $wp_error_class( 'gse_dependency_missing', 'Vendor model unavailable', array( 'status' => 500 ) );
                                }
                                return array( 'error' => 'Vendor model unavailable', 'status' => 500 );
                            }

                            $vendor = call_user_func( array( 'GSE_Vendor', 'load' ), $post_id, true );
                            if ( ! $vendor ) {
                                if ( class_exists( 'WP_Error' ) ) {
                                    $wp_error_class = 'WP_Error';
                                    return new $wp_error_class( 'gse_not_found', 'Vendor not found during search item build', array( 'status' => 404, 'post_id' => $post_id ) );
                                }
                                return array( 'error' => 'Vendor not found', 'status' => 404 );
                            }

                            $items[] = array(
                                'id' => $vendor->id,
                                'title' => $vendor->title,
                                'permalink' => $vendor->permalink,
                                'basic_info_summary' => $vendor->get_basic_info_summary(),
                            );
                        }
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



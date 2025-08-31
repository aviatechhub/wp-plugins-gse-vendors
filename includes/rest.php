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
                    return null;
                }

                // Meta
                $headquarters = '';
                $years_in_operation = 0;
                $website_url = '';
                $contact = array();
                if ( function_exists( 'get_post_meta' ) ) {
                    $headquarters = (string) call_user_func( 'get_post_meta', $post_id, 'headquarters', true );
                    $years_in_operation = (int) call_user_func( 'get_post_meta', $post_id, 'years_in_operation', true );
                    $website_url = (string) call_user_func( 'get_post_meta', $post_id, 'website_url', true );
                    $contact = call_user_func( 'get_post_meta', $post_id, 'contact', true );
                    if ( ! is_array( $contact ) ) {
                        $contact = array();
                    }
                }
                $meta = array(
                    'headquarters' => $headquarters,
                    'years_in_operation' => $years_in_operation,
                    'website_url' => $website_url,
                    'contact' => $contact,
                );

                // Taxonomies: gse_location (names), gse_certification (names)
                $locations = array();
                $certifications = array();
                if ( function_exists( 'wp_get_post_terms' ) ) {
                    $loc_terms = call_user_func( 'wp_get_post_terms', $post_id, 'gse_location', array( 'fields' => 'names' ) );
                    if ( function_exists( 'is_wp_error' ) && call_user_func( 'is_wp_error', $loc_terms ) ) {
                        $locations = array();
                    } else {
                        $locations = (array) $loc_terms;
                    }

                    $cert_terms = call_user_func( 'wp_get_post_terms', $post_id, 'gse_certification', array( 'fields' => 'names' ) );
                    if ( function_exists( 'is_wp_error' ) && call_user_func( 'is_wp_error', $cert_terms ) ) {
                        $certifications = array();
                    } else {
                        $certifications = (array) $cert_terms;
                    }
                }

                // Featured image id (logo)
                $logo_id = 0;
                if ( function_exists( 'get_post_thumbnail_id' ) ) {
                    $logo_id = (int) call_user_func( 'get_post_thumbnail_id', $post_id );
                }

                return array(
                    'meta' => $meta,
                    'locations' => array_values( array_filter( $locations ) ),
                    'certifications' => array_values( array_filter( $certifications ) ),
                    'logo_media_id' => $logo_id,
                );
            },
            'schema' => array(
                'type' => 'object',
                'readonly' => true,
            ),
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

                            $title = isset( $post->post_title ) ? (string) $post->post_title : '';
                            $permalink = '';
                            if ( function_exists( 'get_permalink' ) ) {
                                $permalink = (string) call_user_func( 'get_permalink', $post_id );
                            }

                            // Build basic_info_summary (duplicate of field logic)
                            $headquarters = '';
                            $years_in_operation = 0;
                            $website_url = '';
                            $contact = array();
                            if ( function_exists( 'get_post_meta' ) ) {
                                $headquarters = (string) call_user_func( 'get_post_meta', $post_id, 'headquarters', true );
                                $years_in_operation = (int) call_user_func( 'get_post_meta', $post_id, 'years_in_operation', true );
                                $website_url = (string) call_user_func( 'get_post_meta', $post_id, 'website_url', true );
                                $contact = call_user_func( 'get_post_meta', $post_id, 'contact', true );
                                if ( ! is_array( $contact ) ) { $contact = array(); }
                            }

                            $locations = array();
                            $certifications = array();
                            if ( function_exists( 'wp_get_post_terms' ) ) {
                                $loc_terms = call_user_func( 'wp_get_post_terms', $post_id, 'gse_location', array( 'fields' => 'names' ) );
                                $locations = ( function_exists( 'is_wp_error' ) && call_user_func( 'is_wp_error', $loc_terms ) ) ? array() : (array) $loc_terms;

                                $cert_terms = call_user_func( 'wp_get_post_terms', $post_id, 'gse_certification', array( 'fields' => 'names' ) );
                                $certifications = ( function_exists( 'is_wp_error' ) && call_user_func( 'is_wp_error', $cert_terms ) ) ? array() : (array) $cert_terms;
                            }

                            $logo_id = function_exists( 'get_post_thumbnail_id' ) ? (int) call_user_func( 'get_post_thumbnail_id', $post_id ) : 0;

                            $items[] = array(
                                'id' => $post_id,
                                'title' => $title,
                                'permalink' => $permalink,
                                'basic_info_summary' => array(
                                    'meta' => array(
                                        'headquarters' => $headquarters,
                                        'years_in_operation' => $years_in_operation,
                                        'website_url' => $website_url,
                                        'contact' => $contact,
                                    ),
                                    'locations' => array_values( array_filter( $locations ) ),
                                    'certifications' => array_values( array_filter( $certifications ) ),
                                    'logo_media_id' => $logo_id,
                                ),
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



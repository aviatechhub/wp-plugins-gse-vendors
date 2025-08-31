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
    }
}



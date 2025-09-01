<?php
// Register basic info post meta for Vendor CPT.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gse_vendors_register_meta' ) ) {
    function gse_vendors_register_meta() {
        if ( ! function_exists( 'register_post_meta' ) ) {
            return;
        }

        // Sanitizers
        if ( ! function_exists( 'gse_vendors_sanitize_text_meta' ) ) {
            function gse_vendors_sanitize_text_meta( $value ) {
                if ( is_string( $value ) ) {
                    $value = wp_strip_all_tags( $value );
                }
                return $value;
            }
        }

        if ( ! function_exists( 'gse_vendors_sanitize_absint_meta' ) ) {
            function gse_vendors_sanitize_absint_meta( $value ) {
                return absint( $value );
            }
        }

        if ( ! function_exists( 'gse_vendors_sanitize_url_meta' ) ) {
            function gse_vendors_sanitize_url_meta( $value ) {
                if ( is_string( $value ) ) {
                    $value = trim( $value );
                    $value = esc_url_raw( $value );
                }
                return $value;
            }
        }

        if ( ! function_exists( 'gse_vendors_sanitize_contact_meta' ) ) {
            function gse_vendors_sanitize_contact_meta( $value ) {
                if ( ! is_array( $value ) ) {
                    return array();
                }
                $raw_email = isset( $value['email'] ) && is_string( $value['email'] ) ? $value['email'] : '';
                $email = sanitize_email( $raw_email );
                $phone = isset( $value['phone'] ) && is_string( $value['phone'] ) ? preg_replace( '/[^0-9+]/', '', $value['phone'] ) : '';
                $whatsapp = isset( $value['whatsapp'] ) && is_string( $value['whatsapp'] ) ? preg_replace( '/[^0-9+]/', '', $value['whatsapp'] ) : '';
                return array(
                    'email' => $email,
                    'phone' => $phone,
                    'whatsapp' => $whatsapp,
                );
            }
        }

        // Auth: require ability to edit the vendor post for updates
        if ( ! function_exists( 'gse_vendors_auth_can_edit_post_meta' ) ) {
            function gse_vendors_auth_can_edit_post_meta( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) {
                return (bool) current_user_can( 'edit_post', $post_id );
            }
        }

        // Headquarters (string)
        register_post_meta( 'vendor', 'headquarters', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'gse_vendors_sanitize_text_meta',
            'auth_callback' => 'gse_vendors_auth_can_edit_post_meta',
        ) );

        // Years in Operation (integer)
        register_post_meta( 'vendor', 'years_in_operation', array(
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'gse_vendors_sanitize_absint_meta',
            'auth_callback' => 'gse_vendors_auth_can_edit_post_meta',
        ) );

        // Website URL (string with uri format)
        register_post_meta( 'vendor', 'website_url', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'string',
                    'format' => 'uri',
                ),
            ),
            'sanitize_callback' => 'gse_vendors_sanitize_url_meta',
            'auth_callback' => 'gse_vendors_auth_can_edit_post_meta',
        ) );

        // Contact (object: email, phone, whatsapp)
        register_post_meta( 'vendor', 'contact', array(
            'type' => 'object',
            'single' => true,
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'email' => array(
                            'type' => 'string',
                            'format' => 'email',
                        ),
                        'phone' => array(
                            'type' => 'string',
                        ),
                        'whatsapp' => array(
                            'type' => 'string',
                        ),
                    ),
                    'additionalProperties' => false,
                ),
            ),
            'sanitize_callback' => 'gse_vendors_sanitize_contact_meta',
            'auth_callback' => 'gse_vendors_auth_can_edit_post_meta',
        ) );
    }
}



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
                    if ( function_exists( 'wp_strip_all_tags' ) ) {
                        $value = call_user_func( 'wp_strip_all_tags', $value );
                    } else {
                        $value = strip_tags( $value );
                    }
                }
                return $value;
            }
        }

        if ( ! function_exists( 'gse_vendors_sanitize_absint_meta' ) ) {
            function gse_vendors_sanitize_absint_meta( $value ) {
                if ( function_exists( 'absint' ) ) {
                    return call_user_func( 'absint', $value );
                }
                $n = is_numeric( $value ) ? (int) $value : 0;
                return $n < 0 ? 0 : $n;
            }
        }

        if ( ! function_exists( 'gse_vendors_sanitize_url_meta' ) ) {
            function gse_vendors_sanitize_url_meta( $value ) {
                if ( is_string( $value ) ) {
                    $value = trim( $value );
                    if ( function_exists( 'esc_url_raw' ) ) {
                        $value = call_user_func( 'esc_url_raw', $value );
                    } else {
                        $value = filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
                    }
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
                if ( function_exists( 'sanitize_email' ) ) {
                    $email = call_user_func( 'sanitize_email', $raw_email );
                } else {
                    $email = filter_var( $raw_email, FILTER_VALIDATE_EMAIL ) ? $raw_email : '';
                }
                $phone = isset( $value['phone'] ) && is_string( $value['phone'] ) ? preg_replace( '/[^0-9+]/', '', $value['phone'] ) : '';
                $whatsapp = isset( $value['whatsapp'] ) && is_string( $value['whatsapp'] ) ? preg_replace( '/[^0-9+]/', '', $value['whatsapp'] ) : '';
                return array(
                    'email' => $email,
                    'phone' => $phone,
                    'whatsapp' => $whatsapp,
                );
            }
        }

        // Headquarters (string)
        call_user_func( 'register_post_meta', 'vendor', 'headquarters', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'gse_vendors_sanitize_text_meta',
        ) );

        // Years in Operation (integer)
        call_user_func( 'register_post_meta', 'vendor', 'years_in_operation', array(
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'gse_vendors_sanitize_absint_meta',
        ) );

        // Website URL (string with uri format)
        call_user_func( 'register_post_meta', 'vendor', 'website_url', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'string',
                    'format' => 'uri',
                ),
            ),
            'sanitize_callback' => 'gse_vendors_sanitize_url_meta',
        ) );

        // Contact (object: email, phone, whatsapp)
        call_user_func( 'register_post_meta', 'vendor', 'contact', array(
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
        ) );
    }
}



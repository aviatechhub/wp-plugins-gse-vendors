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

        // Headquarters (string)
        call_user_func( 'register_post_meta', 'vendor', 'headquarters', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );

        // Years in Operation (integer)
        call_user_func( 'register_post_meta', 'vendor', 'years_in_operation', array(
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
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
        ) );
    }
}



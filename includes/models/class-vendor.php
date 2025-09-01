<?php
// Reusable Vendor domain model for retrieving vendor data consistently.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'GSE_Vendor' ) ) {
    class GSE_Vendor {
        /** @var int */
        public $id = 0;
        /** @var string */
        public $title = '';
        /** @var string */
        public $permalink = '';
        /** @var array */
        public $meta = array();
        /** @var array */
        public $locations = array();
        /** @var array */
        public $certifications = array();
        /** @var int */
        public $logo_media_id = 0;

        private function __construct( $post_id ) {
            $this->id = (int) $post_id;
            $this->title = '';
            $this->permalink = '';
            $this->meta = array(
                'headquarters' => '',
                'years_in_operation' => 0,
                'website_url' => '',
                'contact' => array(),
            );
            $this->locations = array();
            $this->certifications = array();
            $this->logo_media_id = 0;

            $this->load_from_wordpress();
        }

        public static function load( $post_id, $require_published = true ) {
            $post_id = (int) $post_id;
            if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
                return null;
            }
            $post = call_user_func( 'get_post', $post_id );
            if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                return null;
            }
            if ( $require_published && ( ! isset( $post->post_status ) || $post->post_status !== 'publish' ) ) {
                return null;
            }
            return new self( $post_id );
        }

        public static function getById( $post_id, $require_published = true ) {
            return self::load( $post_id, $require_published );
        }

        /**
         * Create a new Vendor post with optional meta and tax terms.
         *
         * @param array $args { title (string, required), status (string), meta (array), locations (array), certifications (array) }
         * @return GSE_Vendor|WP_Error|null
         */
        public static function create( $args ) {
            $title = isset( $args['title'] ) ? (string) $args['title'] : '';
            if ( $title === '' ) {
                if ( class_exists( 'WP_Error' ) ) {
                    $wp_error_class = 'WP_Error';
                    return new $wp_error_class( 'gse_validation_error', 'Title is required', array( 'status' => 422 ) );
                }
                return null;
            }

            $status = isset( $args['status'] ) ? (string) $args['status'] : 'publish';
            $status = in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ? $status : 'publish';

            if ( ! function_exists( 'wp_insert_post' ) ) {
                if ( class_exists( 'WP_Error' ) ) {
                    $wp_error_class = 'WP_Error';
                    return new $wp_error_class( 'gse_dependency_missing', 'WordPress functions unavailable', array( 'status' => 500 ) );
                }
                return null;
            }

            $postarr = array(
                'post_type' => 'vendor',
                'post_status' => $status,
                'post_title' => $title,
            );

            $post_id = call_user_func( 'wp_insert_post', $postarr, true );
            if ( function_exists( 'is_wp_error' ) && call_user_func( 'is_wp_error', $post_id ) ) {
                return $post_id;
            }

            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                if ( class_exists( 'WP_Error' ) ) {
                    $wp_error_class = 'WP_Error';
                    return new $wp_error_class( 'gse_create_failed', 'Failed to create vendor', array( 'status' => 500 ) );
                }
                return null;
            }

            // Meta
            $meta = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
            $hq = isset( $meta['headquarters'] ) ? (string) $meta['headquarters'] : '';
            $years = isset( $meta['years_in_operation'] ) ? $meta['years_in_operation'] : 0;
            $url = isset( $meta['website_url'] ) ? (string) $meta['website_url'] : '';
            $contact = isset( $meta['contact'] ) && is_array( $meta['contact'] ) ? $meta['contact'] : array();

            if ( function_exists( 'gse_vendors_sanitize_text_meta' ) ) {
                $hq = call_user_func( 'gse_vendors_sanitize_text_meta', $hq );
            }
            if ( function_exists( 'gse_vendors_sanitize_absint_meta' ) ) {
                $years = call_user_func( 'gse_vendors_sanitize_absint_meta', $years );
            } else {
                $years = is_numeric( $years ) ? (int) $years : 0;
            }
            if ( function_exists( 'gse_vendors_sanitize_url_meta' ) ) {
                $url = call_user_func( 'gse_vendors_sanitize_url_meta', $url );
            }
            if ( function_exists( 'gse_vendors_sanitize_contact_meta' ) ) {
                $contact = call_user_func( 'gse_vendors_sanitize_contact_meta', $contact );
            } else {
                $contact = array();
            }

            if ( function_exists( 'update_post_meta' ) ) {
                call_user_func( 'update_post_meta', $post_id, 'headquarters', $hq );
                call_user_func( 'update_post_meta', $post_id, 'years_in_operation', $years );
                call_user_func( 'update_post_meta', $post_id, 'website_url', $url );
                call_user_func( 'update_post_meta', $post_id, 'contact', $contact );
            }

            // Taxonomies
            $locations = isset( $args['locations'] ) && is_array( $args['locations'] ) ? $args['locations'] : array();
            $certifications = isset( $args['certifications'] ) && is_array( $args['certifications'] ) ? $args['certifications'] : array();

            if ( function_exists( 'wp_set_object_terms' ) ) {
                if ( ! empty( $locations ) ) {
                    $loc_ids = array();
                    foreach ( $locations as $t ) { $loc_ids[] = (int) $t; }
                    call_user_func( 'wp_set_object_terms', $post_id, $loc_ids, 'gse_location', false );
                }
                if ( ! empty( $certifications ) ) {
                    $cert_ids = array();
                    foreach ( $certifications as $t ) { $cert_ids[] = (int) $t; }
                    call_user_func( 'wp_set_object_terms', $post_id, $cert_ids, 'gse_certification', false );
                }
            }

            return self::getById( $post_id, false );
        }

        public function get_basic_info_summary() {
            return array(
                'meta' => $this->meta,
                'locations' => array_values( array_filter( $this->locations ) ),
                'certifications' => array_values( array_filter( $this->certifications ) ),
                'logo_media_id' => $this->logo_media_id,
            );
        }

        public function to_array() {
            $summary = $this->get_basic_info_summary();
            return array(
                'id' => $this->id,
                'title' => $this->title,
                'permalink' => $this->permalink,
                'meta' => $summary['meta'],
                'tax' => array(
                    'locations' => $summary['locations'],
                    'certifications' => $summary['certifications'],
                ),
                'logo_media_id' => $summary['logo_media_id'],
                'basic_info_summary' => $summary,
            );
        }

        private function load_from_wordpress() {
            // Title & Permalink
            if ( function_exists( 'get_post' ) ) {
                $post = call_user_func( 'get_post', $this->id );
                if ( $post && isset( $post->post_title ) ) {
                    $this->title = (string) $post->post_title;
                }
            }
            if ( function_exists( 'get_permalink' ) ) {
                $this->permalink = (string) call_user_func( 'get_permalink', $this->id );
            }

            // Meta
            if ( function_exists( 'get_post_meta' ) ) {
                $this->meta['headquarters'] = (string) call_user_func( 'get_post_meta', $this->id, 'headquarters', true );
                $this->meta['years_in_operation'] = (int) call_user_func( 'get_post_meta', $this->id, 'years_in_operation', true );
                $this->meta['website_url'] = (string) call_user_func( 'get_post_meta', $this->id, 'website_url', true );
                $contact = call_user_func( 'get_post_meta', $this->id, 'contact', true );
                $this->meta['contact'] = is_array( $contact ) ? $contact : array();
            }

            // Taxonomies
            if ( function_exists( 'wp_get_post_terms' ) ) {
                $loc_terms = call_user_func( 'wp_get_post_terms', $this->id, 'gse_location', array( 'fields' => 'names' ) );
                if ( function_exists( 'is_wp_error' ) && call_user_func( 'is_wp_error', $loc_terms ) ) {
                    $this->locations = array();
                } else {
                    $this->locations = (array) $loc_terms;
                }
                $cert_terms = call_user_func( 'wp_get_post_terms', $this->id, 'gse_certification', array( 'fields' => 'names' ) );
                if ( function_exists( 'is_wp_error' ) && call_user_func( 'is_wp_error', $cert_terms ) ) {
                    $this->certifications = array();
                } else {
                    $this->certifications = (array) $cert_terms;
                }
            }

            // Logo media id
            if ( function_exists( 'get_post_thumbnail_id' ) ) {
                $this->logo_media_id = (int) call_user_func( 'get_post_thumbnail_id', $this->id );
            }
        }
    }
}



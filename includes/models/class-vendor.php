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



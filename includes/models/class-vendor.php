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

            $post = get_post( $post_id );
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
                return new WP_Error( 'gse_validation_error', 'Title is required', array( 'status' => 422 ) );
            }

            $status = isset( $args['status'] ) ? (string) $args['status'] : 'publish';
            $status = in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ? $status : 'publish';

            if ( ! function_exists( 'wp_insert_post' ) ) {
                return new WP_Error( 'gse_dependency_missing', 'WordPress functions unavailable', array( 'status' => 500 ) );
            }

            $postarr = array(
                'post_type' => 'vendor',
                'post_status' => $status,
                'post_title' => $title,
            );

            $post_id = wp_insert_post( $postarr, true );
            if ( is_wp_error( $post_id ) ) {
                return $post_id;
            }

            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                return new WP_Error( 'gse_create_failed', 'Failed to create vendor', array( 'status' => 500 ) );
            }

            // Meta
            $meta = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
            $hq = isset( $meta['headquarters'] ) ? (string) $meta['headquarters'] : '';
            $years = isset( $meta['years_in_operation'] ) ? $meta['years_in_operation'] : 0;
            $url = isset( $meta['website_url'] ) ? (string) $meta['website_url'] : '';
            $contact = isset( $meta['contact'] ) && is_array( $meta['contact'] ) ? $meta['contact'] : array();

            $hq = gse_vendors_sanitize_text_meta( $hq );
            $years = gse_vendors_sanitize_absint_meta( $years );
            $url = gse_vendors_sanitize_url_meta( $url );
            $contact = gse_vendors_sanitize_contact_meta( $contact );
            $contact = array();

            if ( function_exists( 'update_post_meta' ) ) {
                update_post_meta( $post_id, 'headquarters', $hq );
                update_post_meta( $post_id, 'years_in_operation', $years );
                update_post_meta( $post_id, 'website_url', $url );
                update_post_meta( $post_id, 'contact', $contact );
            }

            // Taxonomies
            $locations = isset( $args['locations'] ) && is_array( $args['locations'] ) ? $args['locations'] : array();
            $certifications = isset( $args['certifications'] ) && is_array( $args['certifications'] ) ? $args['certifications'] : array();

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

            return self::getById( $post_id, false );
        }

        /**
         * Update an existing Vendor with partial fields.
         *
         * @param int   $post_id Vendor post ID
         * @param array $args { title (string), status (string), meta (array), locations (array), certifications (array) }
         * @return GSE_Vendor|WP_Error|null
         */
        public static function update( $post_id, $args ) {
            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
            }

            $post = get_post( $post_id );
            if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
            }

            // Update title/status if provided
            $do_update_post = false;
            $postarr = array( 'ID' => $post_id );
            if ( isset( $args['title'] ) ) {
                $postarr['post_title'] = (string) $args['title'];
                $do_update_post = true;
            }
            if ( isset( $args['status'] ) ) {
                $status = (string) $args['status'];
                $postarr['post_status'] = in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ? $status : 'publish';
                $do_update_post = true;
            }
            if ( $do_update_post ) {
                $result = wp_update_post( $postarr, true );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
            }

            // Meta (partial)
            if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
                $meta = $args['meta'];
                if ( array_key_exists( 'headquarters', $meta ) ) {
                    $hq = (string) $meta['headquarters'];
                    $hq = gse_vendors_sanitize_text_meta( $hq );
                    update_post_meta( $post_id, 'headquarters', $hq );
                }
                if ( array_key_exists( 'years_in_operation', $meta ) ) {
                    $years = $meta['years_in_operation'];
                    $years = gse_vendors_sanitize_absint_meta( $years );
                    update_post_meta( $post_id, 'years_in_operation', $years );
                }
                if ( array_key_exists( 'website_url', $meta ) ) {
                    $url = (string) $meta['website_url'];
                    $url = gse_vendors_sanitize_url_meta( $url );
                    update_post_meta( $post_id, 'website_url', $url );
                }
                if ( array_key_exists( 'contact', $meta ) ) {
                    $contact = is_array( $meta['contact'] ) ? $meta['contact'] : array();
                    $contact = gse_vendors_sanitize_contact_meta( $contact );
                    update_post_meta( $post_id, 'contact', $contact );
                }
            }

            // Taxonomies (partial)
            if ( isset( $args['locations'] ) && is_array( $args['locations'] ) ) {
                $loc_ids = array(); foreach ( $args['locations'] as $t ) { $loc_ids[] = (int) $t; }
                wp_set_object_terms( $post_id, $loc_ids, 'gse_location', false );
            }
            if ( isset( $args['certifications'] ) && is_array( $args['certifications'] ) ) {
                $cert_ids = array(); foreach ( $args['certifications'] as $t ) { $cert_ids[] = (int) $t; }
                wp_set_object_terms( $post_id, $cert_ids, 'gse_certification', false );
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
            $post = get_post( $this->id );
            if ( $post && isset( $post->post_title ) ) {
                $this->title = (string) $post->post_title;
            }
            $this->permalink = (string) get_permalink( $this->id );

            // Meta
            $this->meta['headquarters'] = (string) get_post_meta( $this->id, 'headquarters', true );
            $this->meta['years_in_operation'] = (int) get_post_meta( $this->id, 'years_in_operation', true );
            $this->meta['website_url'] = (string) get_post_meta( $this->id, 'website_url', true );
            $contact = get_post_meta( $this->id, 'contact', true );
            $this->meta['contact'] = is_array( $contact ) ? $contact : array();

            // Taxonomies
            $loc_terms = wp_get_post_terms( $this->id, 'gse_location', array( 'fields' => 'names' ) );
            if ( is_wp_error( $loc_terms ) ) {
                $this->locations = array();
            } else {
                $this->locations = (array) $loc_terms;
            }

            $cert_terms = wp_get_post_terms( $this->id, 'gse_certification', array( 'fields' => 'names' ) );
            if ( is_wp_error( $cert_terms ) ) {
                $this->certifications = array();
            } else {
                $this->certifications = (array) $cert_terms;
            }

            // Logo media id
            $this->logo_media_id = (int) get_post_thumbnail_id( $this->id );
        }
    }
}



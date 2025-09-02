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

        /**
         * List members for a vendor.
         *
         * @param int $post_id Vendor post ID
         * @param int $limit   Maximum number of rows to return (default 100)
         * @param int $offset  Row offset for pagination (default 0)
         * @return array|WP_Error Array of members or WP_Error on failure
         */
        public static function list_members( $post_id, $limit = 100, $offset = 0 ) {
            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
            }

            $post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
            if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
            }

            global $wpdb;
            if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
                return new WP_Error( 'gse_dependency_missing', 'Database unavailable', array( 'status' => 500 ) );
            }

            $limit = (int) $limit; if ( $limit <= 0 ) { $limit = 100; }
            $offset = (int) $offset; if ( $offset < 0 ) { $offset = 0; }

            $table_members = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
            $users_table = isset( $wpdb->users ) ? $wpdb->users : ( isset( $wpdb->prefix ) ? $wpdb->prefix . 'users' : 'wp_users' );

            if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_results' ) ) {
                $sql = $wpdb->prepare(
                    "SELECT vur.user_id AS user_id, u.display_name AS display_name, u.user_email AS email, vur.role AS role, vur.assigned_at AS assigned_at\n                     FROM {$table_members} AS vur\n                     LEFT JOIN {$users_table} AS u ON u.ID = vur.user_id\n                     WHERE vur.vendor_id = %d\n                     ORDER BY vur.assigned_at ASC\n                     LIMIT %d OFFSET %d",
                    $post_id,
                    $limit,
                    $offset
                );
                $rows = $wpdb->get_results( $sql, ARRAY_A );
            } else {
                $rows = array();
            }

            $members = array();
            if ( is_array( $rows ) ) {
                foreach ( $rows as $row ) {
                    $members[] = array(
                        'user_id' => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
                        'display_name' => isset( $row['display_name'] ) ? (string) $row['display_name'] : '',
                        'email' => isset( $row['email'] ) ? (string) $row['email'] : '',
                        'role' => isset( $row['role'] ) ? (string) $row['role'] : '',
                        'assigned_at' => isset( $row['assigned_at'] ) ? (string) $row['assigned_at'] : '',
                    );
                }
            }

            return $members;
        }

        /**
         * Add a member to a vendor, optionally creating the WP User by email.
         *
         * @param int   $post_id Vendor post ID
         * @param array $args { role (string, required), email (string, required), display_name (string), password (string) }
         * @return array|WP_Error { user_id, display_name, email, role, assigned_at } or WP_Error
         */
        public static function add_member( $post_id, $args ) {
            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
            }

            $post = get_post( $post_id );
            if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
            }

            $role = isset( $args['role'] ) ? (string) $args['role'] : '';
            $email = isset( $args['email'] ) ? (string) $args['email'] : '';
            $display_name = isset( $args['display_name'] ) ? (string) $args['display_name'] : '';
            $password = isset( $args['password'] ) ? (string) $args['password'] : '';

            // Validate role
            $catalog = gse_vendors_get_role_catalog();
            if ( ! in_array( $role, (array) $catalog, true ) ) {
                return new WP_Error( 'gse_validation_error', 'Invalid role', array( 'status' => 422 ) );
            }
            if ( $role === '' ) {
                return new WP_Error( 'gse_validation_error', 'Invalid role', array( 'status' => 422 ) );
            }

            // Validate email
            if ( $email === '' || ! is_email( $email ) ) {
                return new WP_Error( 'gse_validation_error', 'A valid email is required', array( 'status' => 422 ) );
            }

            // Get or create user by email
            $user_id = 0;
            $user = get_user_by( 'email', $email );
            if ( $user && isset( $user->ID ) ) {
                $user_id = (int) $user->ID;
            }

            if ( $user_id <= 0 ) {
                if ( $password === '' ) {
                    return new WP_Error( 'gse_validation_error', 'Password is required to create a new user', array( 'status' => 422 ) );
                }

                // Derive a user_login from email
                $login_base = $email;
                if ( function_exists( 'sanitize_user' ) ) {
                    $at_pos = strpos( $email, '@' );
                    $login_candidate = $at_pos !== false ? substr( $email, 0, $at_pos ) : $email;
                    $login_base = sanitize_user( $login_candidate, true );
                    if ( $login_base === '' ) {
                        $login_base = preg_replace( '/[^a-z0-9_\-]/i', '', $login_candidate );
                    }
                }

                $user_login = $login_base !== '' ? $login_base : $email;
                if ( username_exists( $user_login ) ) {
                    $user_login = $user_login . '_' . substr( md5( $email ), 0, 6 );
                }

                $user_data = array(
                    'user_login' => $user_login,
                    'user_email' => $email,
                    'user_pass' => $password,
                );
                if ( $display_name !== '' ) {
                    $user_data['display_name'] = $display_name;
                }

                $created = wp_insert_user( $user_data );
                if ( is_wp_error( $created ) ) {
                    return new WP_Error( 'gse_create_failed', 'Failed to create user', array( 'status' => 500, 'data' => $created->get_error_message() ) );
                }
                $user_id = (int) $created;
            }

            // Ensure not already a member
            global $wpdb;
            if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
                return new WP_Error( 'gse_dependency_missing', 'Database unavailable', array( 'status' => 500 ) );
            }

            $table_members = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
            $existing_count = 0;
            if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
                $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_members} WHERE vendor_id = %d AND user_id = %d", $post_id, $user_id );
                $existing_count = (int) $wpdb->get_var( $sql );
            }
            if ( $existing_count > 0 ) {
                return new WP_Error( 'gse_conflict', 'User is already a member of this vendor', array( 'status' => 409 ) );
            }

            // Insert membership
            $now_gmt = gmdate( 'Y-m-d H:i:s' );
            $inserted = false;
            if ( method_exists( $wpdb, 'insert' ) ) {
                $inserted = (bool) $wpdb->insert(
                    $table_members,
                    array(
                        'vendor_id' => $post_id,
                        'user_id' => $user_id,
                        'role' => $role,
                        'assigned_at' => $now_gmt,
                    ),
                    array( '%d', '%d', '%s', '%s' )
                );
            }

            if ( ! $inserted ) {
                return new WP_Error( 'gse_create_failed', 'Failed to add member', array( 'status' => 500 ) );
            }

            // Build response
            $user_display_name = $display_name;
            if ( $user_display_name === '' ) {
                $wp_user = get_user_by( 'id', $user_id );
                if ( $wp_user && isset( $wp_user->display_name ) ) {
                    $user_display_name = (string) $wp_user->display_name;
                }
                if ( $email === '' && isset( $wp_user->user_email ) ) {
                    $email = (string) $wp_user->user_email;
                }
            }

            return array(
                'user_id' => $user_id,
                'display_name' => $user_display_name,
                'email' => $email,
                'role' => $role,
                'assigned_at' => $now_gmt,
            );
        }

        /**
         * Update a member's role for a vendor.
         *
         * Prevents demoting the last remaining owner.
         *
         * @param int    $post_id  Vendor post ID
         * @param int    $user_id  User ID of the member
         * @param string $new_role New role (must be in role catalog)
         * @return array|WP_Error { user_id, display_name, email, role, assigned_at } or WP_Error
         */
        public static function update_member_role( $post_id, $user_id, $new_role ) {
            $post_id = (int) $post_id;
            $user_id = (int) $user_id;
            $new_role = (string) $new_role;

            if ( $post_id <= 0 || $user_id <= 0 ) {
                return new WP_Error( 'gse_invalid_id', 'Invalid vendor or user id', array( 'status' => 400 ) );
            }

            $post = get_post( $post_id );
            if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
            }

            // Validate role against catalog
            $catalog = gse_vendors_get_role_catalog();
            if ( ! in_array( $new_role, (array) $catalog, true ) || $new_role === '' ) {
                return new WP_Error( 'gse_validation_error', 'Invalid role', array( 'status' => 422 ) );
            }

            global $wpdb;
            if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
                return new WP_Error( 'gse_dependency_missing', 'Database unavailable', array( 'status' => 500 ) );
            }

            $table_members = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';

            // Fetch current membership row
            $current_role = '';
            $assigned_at = '';
            if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_row' ) ) {
                $sql = $wpdb->prepare( "SELECT role, assigned_at FROM {$table_members} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $post_id, $user_id );
                $row = $wpdb->get_row( $sql, ARRAY_A );
                if ( is_array( $row ) ) {
                    $current_role = isset( $row['role'] ) ? (string) $row['role'] : '';
                    $assigned_at = isset( $row['assigned_at'] ) ? (string) $row['assigned_at'] : '';
                }
            }

            if ( $current_role === '' ) {
                return new WP_Error( 'gse_not_found', 'Membership not found', array( 'status' => 404 ) );
            }

            // Prevent demoting the last owner
            if ( $current_role === 'owner' && $new_role !== 'owner' ) {
                $owner_count = 0;
                if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
                    $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_members} WHERE vendor_id = %d AND role = 'owner'", $post_id );
                    $owner_count = (int) $wpdb->get_var( $sql );
                }
                if ( $owner_count <= 1 ) {
                    return new WP_Error( 'gse_conflict', 'Cannot demote the last owner', array( 'status' => 409 ) );
                }
            }

            // No-op if same role
            if ( $current_role === $new_role ) {
                $user_display_name = '';
                $email = '';
                $wp_user = get_user_by( 'id', $user_id );
                if ( $wp_user ) {
                    $user_display_name = isset( $wp_user->display_name ) ? (string) $wp_user->display_name : '';
                    $email = isset( $wp_user->user_email ) ? (string) $wp_user->user_email : '';
                }
                return array(
                    'user_id' => $user_id,
                    'display_name' => $user_display_name,
                    'email' => $email,
                    'role' => $current_role,
                    'assigned_at' => $assigned_at,
                );
            }

            // Update role; keep assigned_at unchanged to reflect original membership date
            $updated = false;
            if ( method_exists( $wpdb, 'update' ) ) {
                $updated = (bool) $wpdb->update(
                    $table_members,
                    array( 'role' => $new_role ),
                    array( 'vendor_id' => $post_id, 'user_id' => $user_id ),
                    array( '%s' ),
                    array( '%d', '%d' )
                );
            }

            if ( ! $updated ) {
                return new WP_Error( 'gse_update_failed', 'Failed to update member role', array( 'status' => 500 ) );
            }

            // Load user info for response
            $user_display_name = '';
            $email = '';
            $wp_user = get_user_by( 'id', $user_id );
            if ( $wp_user ) {
                $user_display_name = isset( $wp_user->display_name ) ? (string) $wp_user->display_name : '';
                $email = isset( $wp_user->user_email ) ? (string) $wp_user->user_email : '';
            }

            return array(
                'user_id' => $user_id,
                'display_name' => $user_display_name,
                'email' => $email,
                'role' => $new_role,
                'assigned_at' => $assigned_at,
            );
        }

        /**
         * Remove a member from a vendor.
         *
         * Prevent removing the last remaining owner.
         *
         * @param int $post_id Vendor post ID
         * @param int $user_id User ID of the member to remove
         * @return array|WP_Error { removed: true, user_id, role } or WP_Error
         */
        public static function remove_member( $post_id, $user_id ) {
            $post_id = (int) $post_id;
            $user_id = (int) $user_id;

            if ( $post_id <= 0 || $user_id <= 0 ) {
                return new WP_Error( 'gse_invalid_id', 'Invalid vendor or user id', array( 'status' => 400 ) );
            }

            $post = get_post( $post_id );
            if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
            }

            global $wpdb;
            if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
                return new WP_Error( 'gse_dependency_missing', 'Database unavailable', array( 'status' => 500 ) );
            }

            $table_members = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';

            // Fetch current membership
            $current_role = '';
            if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
                $sql = $wpdb->prepare( "SELECT role FROM {$table_members} WHERE vendor_id = %d AND user_id = %d LIMIT 1", $post_id, $user_id );
                $current_role = (string) $wpdb->get_var( $sql );
            }

            if ( $current_role === '' ) {
                return new WP_Error( 'gse_not_found', 'Membership not found', array( 'status' => 404 ) );
            }

            // Prevent removing the last owner
            if ( $current_role === 'owner' ) {
                $owner_count = 0;
                if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
                    $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_members} WHERE vendor_id = %d AND role = 'owner'", $post_id );
                    $owner_count = (int) $wpdb->get_var( $sql );
                }
                if ( $owner_count <= 1 ) {
                    return new WP_Error( 'gse_conflict', 'Cannot remove the last owner', array( 'status' => 409 ) );
                }
            }

            // Perform deletion
            $deleted = false;
            if ( method_exists( $wpdb, 'delete' ) ) {
                $result = $wpdb->delete( $table_members, array( 'vendor_id' => $post_id, 'user_id' => $user_id ), array( '%d', '%d' ) );
                $deleted = ( $result !== false && $result > 0 );
            }

            if ( ! $deleted ) {
                return new WP_Error( 'gse_delete_failed', 'Failed to remove member', array( 'status' => 500 ) );
            }

            return array(
                'removed' => true,
                'user_id' => $user_id,
                'role' => $current_role,
            );
        }

        /**
         * Delete a Vendor post and clean up related membership rows.
         *
         * @param int  $post_id       Vendor post ID
         * @param bool $force_delete  Whether to bypass trash (default true)
         * @return array|WP_Error { deleted (bool), id (int) } on success or WP_Error on failure
         */
        public static function delete( $post_id, $force_delete = true ) {
            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                return new WP_Error( 'gse_invalid_id', 'Invalid vendor id', array( 'status' => 400 ) );
            }

            $post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
            if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== 'vendor' ) {
                return new WP_Error( 'gse_not_found', 'Vendor not found', array( 'status' => 404 ) );
            }

            if ( ! function_exists( 'wp_delete_post' ) ) {
                return new WP_Error( 'gse_dependency_missing', 'WordPress functions unavailable', array( 'status' => 500 ) );
            }

            $result = wp_delete_post( $post_id, (bool) $force_delete );
            if ( ! $result ) {
                return new WP_Error( 'gse_delete_failed', 'Failed to delete vendor', array( 'status' => 500 ) );
            }

            // Best-effort cleanup of membership rows in custom table.
            global $wpdb;
            if ( isset( $wpdb ) && is_object( $wpdb ) ) {
                $table_name = isset( $wpdb->prefix ) ? $wpdb->prefix . 'gse_vendor_user_roles' : 'wp_gse_vendor_user_roles';
                if ( method_exists( $wpdb, 'delete' ) ) {
                    $wpdb->delete( $table_name, array( 'vendor_id' => $post_id ), array( '%d' ) );
                } elseif ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'query' ) ) {
                    $sql = $wpdb->prepare( "DELETE FROM {$table_name} WHERE vendor_id = %d", $post_id );
                    $wpdb->query( $sql );
                }
            }

            return array( 'deleted' => true, 'id' => $post_id );
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



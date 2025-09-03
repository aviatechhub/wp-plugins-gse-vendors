<?php
// Admin-only UI bootstrapping for Vendors.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gse_vendors_current_user_is_admin' ) ) {
	function gse_vendors_current_user_is_admin() {
		// Use capability check rather than role name comparisons for future flexibility.
		if ( function_exists( 'current_user_can' ) ) {
			return (bool) call_user_func( 'current_user_can', 'administrator' );
		}
		return false;
	}
}

// Boot admin behaviors and restrictions.
if ( ! function_exists( 'gse_vendors_admin_boot' ) ) {
	function gse_vendors_admin_boot() {
		// Hide menu entries for non-admins once admin menu is loaded.
		if ( function_exists( 'add_action' ) ) {
			call_user_func( 'add_action', 'admin_menu', 'gse_vendors_admin_hide_vendor_menu', 99 );
		}

		// Block direct access to vendor edit screens for non-admins as early as possible in admin.
		if ( function_exists( 'add_action' ) ) {
			call_user_func( 'add_action', 'admin_init', 'gse_vendors_admin_block_direct_vendor_access' );
		}
	}
}

// Remove the Vendor CPT menu/submenu entries for non-admins.
if ( ! function_exists( 'gse_vendors_admin_hide_vendor_menu' ) ) {
	function gse_vendors_admin_hide_vendor_menu() {
		$is_admin = function_exists( 'is_admin' ) ? (bool) call_user_func( 'is_admin' ) : false;
		if ( $is_admin && ! gse_vendors_current_user_is_admin() ) {
			// Top-level: post type menu for Vendors (added by CPT via show_ui => true)
			if ( function_exists( 'remove_menu_page' ) ) {
				call_user_func( 'remove_menu_page', 'edit.php?post_type=vendor' );
			}
			// Common submenus (All Vendors, Add New)
			if ( function_exists( 'remove_submenu_page' ) ) {
				call_user_func( 'remove_submenu_page', 'edit.php?post_type=vendor', 'edit.php?post_type=vendor' );
				call_user_func( 'remove_submenu_page', 'edit.php?post_type=vendor', 'post-new.php?post_type=vendor' );
			}
		}
	}
}

// Block direct access to vendor post type screens for non-admins via 403.
if ( ! function_exists( 'gse_vendors_admin_block_direct_vendor_access' ) ) {
	function gse_vendors_admin_block_direct_vendor_access() {
		$is_admin = function_exists( 'is_admin' ) ? (bool) call_user_func( 'is_admin' ) : false;
		if ( ! $is_admin ) {
			return;
		}

		if ( gse_vendors_current_user_is_admin() ) {
			return; // Admins are allowed.
		}

		$screen = function_exists( 'get_current_screen' ) ? call_user_func( 'get_current_screen' ) : null;

		// If we have a screen object, use it to quickly determine the post type context.
		if ( is_object( $screen ) && isset( $screen->post_type ) && $screen->post_type === 'vendor' ) {
			gse_vendors_send_admin_forbidden();
			return;
		}

		// Fallback heuristics using request vars for early admin_init stage.
		$requested_post_type = isset( $_GET['post_type'] ) ? ( function_exists( 'sanitize_key' ) && function_exists( 'wp_unslash' ) ? call_user_func( 'sanitize_key', call_user_func( 'wp_unslash', $_GET['post_type'] ) ) : strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $_GET['post_type'] ) ) ) : '';
		$action = isset( $_GET['action'] ) ? ( function_exists( 'sanitize_key' ) && function_exists( 'wp_unslash' ) ? call_user_func( 'sanitize_key', call_user_func( 'wp_unslash', $_GET['action'] ) ) : strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $_GET['action'] ) ) ) : '';
		$post_id = isset( $_GET['post'] ) ? ( function_exists( 'absint' ) ? (int) call_user_func( 'absint', $_GET['post'] ) : max( 0, (int) $_GET['post'] ) ) : 0;
		$page = isset( $_GET['page'] ) ? ( function_exists( 'sanitize_key' ) && function_exists( 'wp_unslash' ) ? call_user_func( 'sanitize_key', call_user_func( 'wp_unslash', $_GET['page'] ) ) : strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $_GET['page'] ) ) ) : '';

		// List and add screens for the vendor CPT.
		if ( $requested_post_type === 'vendor' ) {
			gse_vendors_send_admin_forbidden();
			return;
		}

		// Classic edit.php?post_type=vendor and post-new.php?post_type=vendor handled above.
		// When editing a specific post, resolve its type.
		if ( $post_id > 0 ) {
			$post = function_exists( 'get_post' ) ? call_user_func( 'get_post', $post_id ) : null;
			if ( $post && $post->post_type === 'vendor' ) {
				gse_vendors_send_admin_forbidden();
				return;
			}
		}

		// Block Gutenberg post editor route as well.
		if ( isset( $_GET['postType'] ) ) {
			$post_type_param = function_exists( 'sanitize_key' ) && function_exists( 'wp_unslash' ) ? call_user_func( 'sanitize_key', call_user_func( 'wp_unslash', $_GET['postType'] ) ) : strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $_GET['postType'] ) );
			if ( $post_type_param === 'vendor' ) {
				gse_vendors_send_admin_forbidden();
				return;
			}
		}

		// Prevent access via direct options pages if ever added under Vendors.
		if ( $page === 'vendor' ) {
			gse_vendors_send_admin_forbidden();
			return;
		}
	}
}

if ( ! function_exists( 'gse_vendors_send_admin_forbidden' ) ) {
	function gse_vendors_send_admin_forbidden() {
		// Send a 403 and present a minimal message.
		if ( function_exists( 'status_header' ) ) {
			call_user_func( 'status_header', 403 );
		} else {
			// Best-effort header in non-WordPress context.
			if ( ! headers_sent() ) {
				header( 'HTTP/1.1 403 Forbidden' );
			}
		}
		if ( function_exists( 'nocache_headers' ) ) {
			call_user_func( 'nocache_headers' );
		}

		$message = function_exists( 'esc_html__' ) ? call_user_func( 'esc_html__', 'You do not have permission to access this page.', 'gse-vendors' ) : 'You do not have permission to access this page.';
		if ( function_exists( 'wp_die' ) ) {
			call_user_func( 'wp_die', $message, 403 );
		} else {
			echo $message;
			exit;
		}
	}
}



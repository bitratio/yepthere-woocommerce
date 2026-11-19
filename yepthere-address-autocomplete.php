<?php
/**
 * Plugin Name: yepthere Address Autocomplete
 * Plugin URI: https://yepthere.au
 * Description: Australian address autocomplete for WooCommerce checkout and account address forms.
 * Version: 0.2.0
 * Author: Sessional Limited
 * Author URI: https://yepthere.au
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yepthere-address-autocomplete
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 11.3
 */

defined( 'ABSPATH' ) || exit;

define( 'YEPTHERE_AA_VERSION', '0.2.0' );

add_action( 'before_woocommerce_init', 'yepthere_aa_compatibility' );
function yepthere_aa_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

/** Never send a server credential to the browser, even if an option was edited externally. */
function yepthere_aa_browser_key() {
	$key = get_option( 'yepthere_aa_browser_key', '' );
	return is_string( $key ) && preg_match( '/\Asab_live_[A-Za-z0-9_-]+\z/', $key ) ? $key : '';
}

function yepthere_aa_settings_url() {
	return admin_url( 'admin.php?page=wc-settings&tab=yepthere' );
}

add_filter( 'woocommerce_get_settings_pages', 'yepthere_aa_settings_page' );
function yepthere_aa_settings_page( $pages ) {
	require_once __DIR__ . '/includes/class-yepthere-aa-settings.php';
	$pages[] = new yepthere_aa_Settings();
	return $pages;
}

/** Register only after WooCommerce has loaded its optional provider API. */
add_filter( 'woocommerce_address_providers', 'yepthere_aa_address_providers' );
function yepthere_aa_address_providers( $providers ) {
	if ( class_exists( 'WC_Address_Provider' ) && yepthere_aa_browser_key() ) {
		require_once __DIR__ . '/includes/class-yepthere-aa-provider.php';
		$providers[] = new yepthere_aa_Provider();
	}
	return $providers;
}

/** Return a context only for front-end pages with an enabled address form. */
function yepthere_aa_context() {
	if ( is_admin() || ! function_exists( 'is_checkout' ) || ! yepthere_aa_browser_key() ) {
		return '';
	}
	if ( is_account_page() && is_wc_endpoint_url( 'edit-address' ) ) {
		$address = get_query_var( 'edit-address' );
		return is_user_logged_in() && 'yes' === get_option( 'yepthere_aa_account', 'yes' ) &&
			in_array( $address, array( 'billing', 'shipping' ), true ) ? 'account' : '';
	}
	if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
		return '';
	}
	$post      = get_post();
	$block     = has_block( 'woocommerce/checkout', $post );
	$shortcode = $post && has_shortcode( $post->post_content, 'woocommerce_checkout' );
	if ( is_checkout() || $block || $shortcode ) {
		if ( WC()->cart && WC()->cart->is_empty() ) {
			return '';
		}
		return $block ? 'block' : 'classic';
	}
	return '';
}

/** The hosted widget is used only when WooCommerce has no provider API. */
function yepthere_aa_register_assets( $context ) {
	$handle = 'yepthere-aa-forms';
	if ( wp_script_is( $handle, 'registered' ) ) {
		return;
	}
	wp_register_script( 'yepthere-aa-widget', 'https://yepthere.au/widget.js', array(), null, array( 'strategy' => 'defer', 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_register_script( $handle, plugins_url( 'assets/forms.js', __FILE__ ), array( 'yepthere-aa-widget', 'jquery' ), YEPTHERE_AA_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	$config = array(
		'key'     => yepthere_aa_browser_key(),
		'limit'   => max( 1, min( 10, (int) get_option( 'yepthere_aa_limit', 6 ) ) ),
		'context' => $context,
	);
	// This intentionally public browser key is JSON-escaped and never put in a URL.
	wp_add_inline_script( $handle, 'window.yepthereAA = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
	wp_register_style( 'yepthere-aa-forms', plugins_url( 'assets/forms.css', __FILE__ ), array(), YEPTHERE_AA_VERSION );
}

add_action( 'wp_enqueue_scripts', 'yepthere_aa_enqueue', 20 );
function yepthere_aa_enqueue() {
	$context = yepthere_aa_context();
	if ( ! $context || class_exists( 'WC_Address_Provider' ) || 'block' === $context ) {
		return;
	}
	yepthere_aa_register_assets( $context );
	wp_enqueue_style( 'yepthere-aa-forms' );
	wp_enqueue_script( 'yepthere-aa-forms' );
}

add_action( 'admin_init', 'yepthere_aa_privacy' );
function yepthere_aa_privacy() {
	if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
		wp_add_privacy_policy_content(
			__( 'yepthere Address Autocomplete', 'yepthere-address-autocomplete' ),
			'<p>' . esc_html__( 'When you search for an Australian address at checkout or in an account address form, the address search text you type is sent to yepthere (Sessional Limited) to return address suggestions. Your browser connects directly to yepthere, which also receives connection information such as your IP address and site origin. This plugin adds no tracking. You can enter your address manually without choosing a suggestion.', 'yepthere-address-autocomplete' ) . '</p>'
		);
	}
}

add_action( 'admin_notices', 'yepthere_aa_key_notice' );
function yepthere_aa_key_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! class_exists( 'WooCommerce' ) || yepthere_aa_browser_key() || get_option( 'yepthere_aa_notice_dismissed' ) ) {
		return;
	}
	$screen = get_current_screen();
	$tab    = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $screen || ( 'plugins' !== $screen->id && ( 'woocommerce_page_wc-settings' !== $screen->id || 'yepthere' !== $tab ) ) ) {
		return;
	}
	$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=yepthere_aa_dismiss_notice' ), 'yepthere_aa_dismiss_notice' );
	// An explicit dismiss link persists dismissal without adding an admin script.
	echo '<div class="notice notice-info"><p>' . esc_html__( 'Add your yepthere browser key to enable Australian address suggestions.', 'yepthere-address-autocomplete' ) . ' ';
	echo '<a href="' . esc_url( yepthere_aa_settings_url() ) . '">' . esc_html__( 'Settings', 'yepthere-address-autocomplete' ) . '</a> · ';
	echo '<a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss this notice', 'yepthere-address-autocomplete' ) . '</a></p></div>';
}

add_action( 'admin_post_yepthere_aa_dismiss_notice', 'yepthere_aa_dismiss_notice' );
function yepthere_aa_dismiss_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to change these settings.', 'yepthere-address-autocomplete' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'yepthere_aa_dismiss_notice' );
	update_option( 'yepthere_aa_notice_dismissed', 'yes', false );
	wp_safe_redirect( yepthere_aa_settings_url() );
	exit;
}

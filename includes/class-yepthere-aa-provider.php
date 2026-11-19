<?php
/** Native WooCommerce provider; loaded only when its base class exists. */
defined( 'ABSPATH' ) || exit;

class yepthere_aa_Provider extends WC_Address_Provider {

	public function __construct() {
		$this->id            = 'yepthere';
		$this->name          = 'yepthere';
		// span is permitted by both classic and block branding sanitisers.
		$this->branding_html = '<span>Incorporates or developed using G-NAF © Geoscape Australia</span>';
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ), 20 );
	}

	public function load_scripts() {
		if ( ! yepthere_aa_browser_key() || ! yepthere_aa_context() ||
			! wc_string_to_bool( get_option( 'woocommerce_address_autocomplete_enabled', 'no' ) ) ) {
			return;
		}
		$handle = 'yepthere-aa-provider';
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}
		// Match the core provider's dependency. It brings in the common registry
		// and DOMPurify; its classic consumer skips initialization on block checkout.
		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/provider.js', dirname( __DIR__ ) . '/yepthere-address-autocomplete.php' ),
			array( 'wc-address-autocomplete' ),
			YEPTHERE_AA_VERSION,
			array( 'strategy' => 'defer', 'in_footer' => true )
		);
		wp_add_inline_script(
			$handle,
			'window.yepthereAAProvider = ' . wp_json_encode(
				array(
					'key'   => yepthere_aa_browser_key(),
					'limit' => max( 1, min( 10, (int) get_option( 'yepthere_aa_limit', 6 ) ) ),
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			) . ';',
			'before'
		);
	}
}

<?php
/** WooCommerce Settings API tab; core renders and escapes all field values. */
defined( 'ABSPATH' ) || exit;

class yepthere_aa_Settings extends WC_Settings_Page {
	public function __construct() {
		$this->id    = 'yepthere';
		$this->label = __( 'yepthere', 'yepthere-address-autocomplete' );
		parent::__construct();
		add_filter( 'woocommerce_admin_settings_sanitize_option_yepthere_aa_browser_key', array( $this, 'sanitize_key' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_yepthere_aa_limit', array( $this, 'sanitize_limit' ) );
	}

	public function get_settings( $current_section = '' ) {
		return array(
			array(
				'title' => __( 'yepthere address autocomplete', 'yepthere-address-autocomplete' ),
				'type'  => 'title',
				'desc'  => '<a href="' . esc_url( 'https://yepthere.au/account' ) . '">' . esc_html__( 'Get a free key', 'yepthere-address-autocomplete' ) . '</a>',
				'id'    => 'yepthere_aa_section',
			),
			array(
				'title'             => __( 'Browser key', 'yepthere-address-autocomplete' ),
				'id'                => 'yepthere_aa_browser_key',
				'type'              => 'password',
				'default'           => '',
				'custom_attributes' => array( 'autocomplete' => 'new-password', 'spellcheck' => 'false' ),
				'desc'              => __( 'Use a sab_live_ browser key restricted to this site’s hostname. It is visible in the page source. Server keys (sak_live_) must never be put in a page.', 'yepthere-address-autocomplete' ),
			),
			array(
				'title'   => __( 'My Account', 'yepthere-address-autocomplete' ),
				'desc'    => __( 'Enable on My Account address forms', 'yepthere-address-autocomplete' ),
				'id'      => 'yepthere_aa_account',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'             => __( 'Result limit', 'yepthere-address-autocomplete' ),
				'id'                => 'yepthere_aa_limit',
				'type'              => 'number',
				'default'           => 6,
				'desc'              => __( 'Maximum results requested from yepthere. WooCommerce may show fewer (both checkouts show at most five in WooCommerce 11.3).', 'yepthere-address-autocomplete' ),
				'custom_attributes' => array( 'min' => 1, 'max' => 10, 'step' => 1 ),
			),
			array( 'type' => 'sectionend', 'id' => 'yepthere_aa_section' ),
		);
	}

	public function output() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			WC_Admin_Settings::output_fields( $this->get_settings() );
			echo '<p>' . esc_html__( 'Billing and shipping switches were removed in 0.2.0: WooCommerce controls the checkout address forms. Existing switch values are no longer used. This plugin never changes WooCommerce’s own autocomplete settings, including on first key save.', 'yepthere-address-autocomplete' ) . '</p>';
			if ( class_exists( 'WC_Address_Provider' ) ) {
				$general_url = admin_url( 'admin.php?page=wc-settings&tab=general#woocommerce_address_autocomplete_enabled' );
				if ( yepthere_aa_browser_key() && ! wc_string_to_bool( get_option( 'woocommerce_address_autocomplete_enabled', 'no' ) ) ) {
					echo '<p><strong>' . esc_html__( 'To enable yepthere suggestions, tick “Enable predictive address search” under Address autocomplete in WooCommerce > Settings > General.', 'yepthere-address-autocomplete' ) . '</strong> ';
				} else {
					echo '<p>';
				}
				echo '<a href="' . esc_url( $general_url ) . '">' . esc_html__( 'Open WooCommerce General settings', 'yepthere-address-autocomplete' ) . '</a> ';
				echo esc_html__( 'If multiple providers are available, select yepthere as the preferred address autocomplete provider. This setting also enables native suggestions on My Account when the switch above is on.', 'yepthere-address-autocomplete' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'This WooCommerce version has no address provider API. The widget supports classic checkout and enabled My Account address forms; the Checkout block uses manual entry.', 'yepthere-address-autocomplete' ) . '</p>';
			}
		}
	}

	public function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// WooCommerce supplies this nonce on its settings form and also checks it centrally.
		check_admin_referer( 'woocommerce-settings' );
		WC_Admin_Settings::save_fields( $this->get_settings() );
	}

	public function sanitize_key( $value, $option, $raw_value ) {
		$key = is_string( $raw_value ) ? trim( $raw_value ) : '';
		if ( is_string( $raw_value ) && '' === $key ) {
			return '';
		}
		if ( 0 === strpos( $key, 'sak_live_' ) ) {
			WC_Admin_Settings::add_error( __( 'Server keys (sak_live_) must never be put in a page. This key was not saved. Create a hostname-restricted sab_live_ browser key in your yepthere account.', 'yepthere-address-autocomplete' ) );
			return yepthere_aa_browser_key();
		}
		if ( ! preg_match( '/\Asab_live_[A-Za-z0-9_-]+\z/', $key ) ) {
			WC_Admin_Settings::add_error( __( 'The browser key must start with sab_live_ and contain a key value without spaces. The previous valid key has been kept.', 'yepthere-address-autocomplete' ) );
			return yepthere_aa_browser_key();
		}
		return sanitize_text_field( $key );
	}

	public function sanitize_limit( $value ) {
		return is_scalar( $value ) && is_numeric( $value ) ? max( 1, min( 10, (int) $value ) ) : 6;
	}
}

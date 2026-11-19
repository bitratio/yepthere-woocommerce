<?php
/** Remove 0.2.0 options and retired 0.1.0 switches; preserve WooCommerce options and address records. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function yepthere_aa_delete_options() {
	foreach ( array( 'browser_key', 'billing', 'shipping', 'account', 'limit', 'notice_dismissed' ) as $option ) {
		delete_option( 'yepthere_aa_' . $option );
	}
}

if ( is_multisite() ) {
	$yepthere_aa_offset = 0;
	do {
		$yepthere_aa_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $yepthere_aa_offset ) );
		foreach ( $yepthere_aa_sites as $yepthere_aa_site ) {
			switch_to_blog( (int) $yepthere_aa_site );
			yepthere_aa_delete_options();
			restore_current_blog();
		}
		$yepthere_aa_offset += 100;
	} while ( count( $yepthere_aa_sites ) === 100 );
} else {
	yepthere_aa_delete_options();
}

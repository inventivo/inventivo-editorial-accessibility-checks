<?php
/**
 * Entfernt ausschließlich die Daten dieses Plugins.
 *
 * @package Editorial_Accessibility_Checks
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! current_user_can( 'delete_plugins' ) ) {
	return;
}

function editorial_accessibility_checks_uninstall_site() {
	delete_post_meta_by_key( '_editorial_accessibility_checks' );
	delete_post_meta_by_key( '_editorial_accessibility_checks_time' );
	foreach ( array( 'missing_alt', 'unclear_link_text', 'skipped_heading', 'input_without_label', 'table_without_header', 'total' ) as $rule ) {
		delete_post_meta_by_key( '_editorial_accessibility_checks_count_' . $rule );
	}
	wp_clear_scheduled_hook( 'editorial_accessibility_checks_scan' );
	delete_option( 'editorial_accessibility_checks_cursor' );
	delete_option( 'editorial_accessibility_checks_lock' );
}

if ( is_multisite() ) {
	$editorial_accessibility_checks_offset = 0;
	do {
		$editorial_accessibility_checks_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $editorial_accessibility_checks_offset ) );
		foreach ( $editorial_accessibility_checks_sites as $editorial_accessibility_checks_site ) {
			switch_to_blog( $editorial_accessibility_checks_site );
			editorial_accessibility_checks_uninstall_site();
			restore_current_blog();
		}
		$editorial_accessibility_checks_offset += 100;
	} while ( count( $editorial_accessibility_checks_sites ) === 100 );
} else {
	editorial_accessibility_checks_uninstall_site();
}

<?php
/**
 * Uninstall. DEFAULT: preserve everything (the key, settings, registry rows and, above all,
 * published QR assets inside already-rendered pages). FULL removal only when the user opted
 * in via Settings before deleting the plugin.
 *
 * @package OpenQR
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$openqr_settings = get_option( 'openqr_settings', array() );
$openqr_full     = is_array( $openqr_settings ) && ! empty( $openqr_settings['delete_on_uninstall'] );

require_once __DIR__ . '/includes/class-autoloader.php';

if ( ! $openqr_full ) {
	return;
}

// Full removal. Multisite-aware: enumerate sites in batches (get_sites() defaults to 100).
if ( is_multisite() ) {
	$openqr_main_site = get_current_blog_id();
	$openqr_paged     = 1;
	do {
		$openqr_sites = get_sites(
			array(
				'number' => 100,
				'paged'  => $openqr_paged,
				'fields' => 'ids',
			)
		);
		foreach ( $openqr_sites as $openqr_site_id ) {
			switch_to_blog( (int) $openqr_site_id );
			OpenQR_Lifecycle::wipe_site_data();
			restore_current_blog();
		}
		++$openqr_paged;
		$openqr_site_count = is_countable( $openqr_sites ) ? count( $openqr_sites ) : 0;
	} while ( 100 === $openqr_site_count );

	switch_to_blog( $openqr_main_site );
	OpenQR_Lifecycle::wipe_site_data();
	restore_current_blog();
} else {
	OpenQR_Lifecycle::wipe_site_data();
}

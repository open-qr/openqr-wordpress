<?php
/**
 * PHPUnit bootstrap for the OpenQR plugin suite (runs inside wp-env's tests container).
 *
 * @package OpenQR
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WP test suite not found at {$_tests_dir}. Set WP_TESTS_DIR.\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/openqr.php';
	}
);

tests_add_filter(
	'init',
	static function () {
		// The registry table must exist before any test touches it.
		OpenQR_Registry::maybe_upgrade();
	},
	1
);

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require_once dirname( __DIR__ ) . '/tests/mock-http.php';
		MockHttp::install();
	},
	5
);

require $_tests_dir . '/includes/bootstrap.php';

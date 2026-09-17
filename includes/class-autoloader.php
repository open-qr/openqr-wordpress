<?php
/**
 * Class autoloader. Maps OpenQR_Foo_Bar to includes/class-foo-bar.php.
 *
 * Deliberately not Composer: zero runtime dependencies, plain WP-style files a
 * wp.org reviewer can read top to bottom.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	/**
	 * Autoload one OpenQR_ class from includes/.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'OpenQR_' ) ) {
			return;
		}
		// OpenQR_Marketing_Link -> includes/class-openqr-marketing-link.php (the full class
		// name maps into the file name, per the WordPress file-naming convention).
		$relative = strtolower( str_replace( '_', '-', $class ) );
		$file     = OPENQR_DIR . '/includes/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

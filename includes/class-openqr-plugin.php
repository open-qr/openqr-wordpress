<?php
/**
 * Composition root. Registers every hook in one place.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap.
 */
final class OpenQR_Plugin {

	/**
	 * Boot the plugin on plugins_loaded.
	 *
	 * @return void
	 */
	public static function boot(): void {
		self::load_textdomain();

		OpenQR_Registry::maybe_upgrade();
		OpenQR_Shortcode::register();
		OpenQR_Blocks::register();
		OpenQR_Frontend::register();
		OpenQR_Rest_Proxy::register();
		OpenQR_Lifecycle::register();
		OpenQR_Post_Actions::register();

		if ( is_admin() ) {
			OpenQR_Admin::register();
			OpenQR_Admin_Dashboard::register();
			OpenQR_Admin_Codes::register();
			OpenQR_Admin_Create::register();
			OpenQR_Admin_Analytics::register();
			OpenQR_Admin_Settings::register();
			OpenQR_Post_Editor::register();
		}
	}

	/**
	 * Load the text domain. The domain MUST equal the wp.org slug.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'openqr', false, dirname( plugin_basename( OPENQR_FILE ) ) . '/languages' );
	}
}

<?php
/**
 * Frontend. Published QR images are durable files under uploads/, so a public page load runs
 * NO plugin PHP and makes NO API call. This class only registers the small stylesheet that
 * gives embedded figures sane defaults.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public-facing registration.
 */
final class OpenQR_Frontend {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Register (not enqueue) the frontend stylesheet; blocks/shortcodes enqueue when used.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		wp_register_style( 'openqr-frontend', OPENQR_URL . 'build/frontend.css', array(), OPENQR_VERSION );
	}
}

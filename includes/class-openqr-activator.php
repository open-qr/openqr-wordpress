<?php
/**
 * Activation/deactivation. Activation seeds defaults, capabilities and the registry table;
 * deactivation touches nothing remote and removes nothing local.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle hooks for activation/deactivation.
 */
final class OpenQR_Activator {

	/**
	 * Seed defaults + capabilities + registry schema.
	 *
	 * @return void
	 */
	public static function activate(): void {
		add_option( OpenQR_Settings::OPT_SETTINGS, array(), '', true );
		add_option( OpenQR_Settings::OPT_VERSION, OPENQR_VERSION, '', true );

		OpenQR_Registry::maybe_upgrade();
		OpenQR_Capabilities::seed_roles();

		if ( version_compare( (string) get_option( OpenQR_Settings::OPT_VERSION, '0' ), OPENQR_VERSION, '<' ) ) {
			update_option( OpenQR_Settings::OPT_VERSION, OPENQR_VERSION, true );
		}
	}

	/**
	 * Nothing remote, nothing local. Assets and settings stay exactly as they are.
	 *
	 * @return void
	 */
	public static function deactivate(): void {}
}

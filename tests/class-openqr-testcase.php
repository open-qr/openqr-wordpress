<?php
/**
 * Base test case: clean slate per test (options, registry table, mock transport, role caps).
 *
 * @package OpenQR
 */

abstract class OpenQR_TestCase extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		MockHttp::reset();

		// Clean slate. The container reports a non-production environment (wp-env sets
		// WP_ENVIRONMENT_TYPE=local), so tests default to the staging override ON; the staging
		// test flips it back off explicitly.
		global $wpdb;
		update_option( OpenQR_Settings::OPT_SETTINGS, array(
			'delete_on_uninstall'       => 0,
			'editors_can_manage'        => 0,
			'staging_mutations_enabled' => 1,
			'default_size'              => 512,
			'per_user_create_limit'     => 10,
		), true );
		delete_option( OpenQR_Settings::OPT_KEY );
		delete_option( OpenQR_Settings::OPT_ACCOUNT );
		delete_option( OpenQR_Settings::OPT_AUTH_FAIL );
		delete_option( OpenQR_Cache::INDEX_OPTION );
		$wpdb->query( 'DELETE FROM ' . OpenQR_Registry::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		// A verified connection most tests can assume; tests of the connect flow overwrite it.
		OpenQR_Settings::store_connection(
			'oqr_testkey0000000000000000000000000000000000',
			array(
				'id'       => 'u_test',
				'email'    => 'owner@example.com',
				'name'     => 'Owner',
				'plan'     => 'free',
				'enforced' => true,
				'limits'   => array(
					'dynamic_codes'       => 1,
					'scan_analytics_days' => 7,
					'detailed_analytics'  => false,
				),
				'usage'    => array( 'active_dynamic' => 0 ),
				'features' => array(
					'protection' => false,
					'aliases'    => false,
					'api'        => true,
				),
			)
		);
	}

	public function tear_down(): void {
		MockHttp::reset();
		parent::tear_down();
	}

	/** Grant/revoke the plugin capabilities on a test user. */
	protected function grant( int $user_id, bool $connection, bool $codes ): void {
		$user = get_userdata( $user_id );
		if ( $connection ) {
			$user->add_cap( 'openqr_manage_connection' );
		}
		if ( $codes ) {
			$user->add_cap( 'openqr_manage_codes' );
		}
	}

	/** Dispatch a REST request through the server with the current user. */
	protected function rest( string $method, string $route, array $body = array(), ?int $user_id = null ): WP_REST_Response {
		if ( null !== $user_id ) {
			wp_set_current_user( $user_id );
		}
		$query = array();
		$path  = '/openqr/v1' . $route;
		$qpos  = strpos( $path, '?' );
		if ( false !== $qpos ) {
			parse_str( substr( $path, $qpos + 1 ), $query );
			$path = substr( $path, 0, $qpos );
		}
		$request = new WP_REST_Request( $method, $path );
		foreach ( $query as $k => $v ) {
			$request->set_param( $k, $v );
		}
		if ( $body ) {
			$request->set_body_params( $body );
		}
		return rest_get_server()->dispatch( $request );
	}

	/** A one-by-one PNG pixel (89 bytes), valid enough for storage tests. */
	protected function fake_png(): string {
		return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
}

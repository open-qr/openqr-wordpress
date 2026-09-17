<?php
/**
 * Option schema and accessors.
 *
 * The API key lives in exactly one option, autoload off, and is never echoed to a browser
 * after save: the UI reads OpenQR_Settings::account() (last 4 only).
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings + connection storage.
 */
final class OpenQR_Settings {

	/**
	 * Option names.
	 */
	const OPT_SETTINGS  = 'openqr_settings';
	const OPT_KEY       = 'openqr_api_key';
	const OPT_ACCOUNT   = 'openqr_account';
	const OPT_VERSION   = 'openqr_version';
	const OPT_AUTH_FAIL = 'openqr_auth_failed_at';

	/**
	 * Production API base. Filterable so e2e runs can point at a local OpenQR dev server.
	 *
	 * @return string
	 */
	public static function api_base_url(): string {
		$url = apply_filters( 'openqr_api_base_url', 'https://openqr.uk' );
		return untrailingslashit( esc_url_raw( $url ) );
	}

	/**
	 * Marketing site base (docs/pricing/signup links; never used for QR payloads).
	 *
	 * @return string
	 */
	public static function site_url(): string {
		return untrailingslashit( self::api_base_url() );
	}

	/**
	 * Default size for generated/embedded QR images, in px.
	 *
	 * @return int
	 */
	public static function default_size(): int {
		$s = self::all();
		return max( 96, min( 2048, (int) ( $s['default_size'] ?? 512 ) ) );
	}

	/**
	 * Per-user create safeguard (protects the shared OpenQR rate budget). 0 disables it.
	 *
	 * @return int
	 */
	public static function per_user_create_limit(): int {
		$s = self::all();
		return max( 0, min( 300, (int) ( $s['per_user_create_limit'] ?? 10 ) ) );
	}

	/**
	 * Whether everything is removed on uninstall.
	 *
	 * @return bool
	 */
	public static function delete_on_uninstall(): bool {
		$s = self::all();
		return ! empty( $s['delete_on_uninstall'] );
	}

	/**
	 * Deliberate delegation: editors may manage this site's linked codes (never the connection).
	 *
	 * @return bool
	 */
	public static function editors_can_manage(): bool {
		$s = self::all();
		return ! empty( $s['editors_can_manage'] );
	}

	/**
	 * Remote mutations re-enabled on non-production clones only by explicit override.
	 *
	 * @return bool
	 */
	public static function staging_mutations_enabled(): bool {
		$s = self::all();
		return ! empty( $s['staging_mutations_enabled'] );
	}

	/**
	 * The whole settings array with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$defaults = array(
			'delete_on_uninstall'       => 0,
			'editors_can_manage'        => 0,
			'staging_mutations_enabled' => 0,
			'default_size'              => 512,
			'per_user_create_limit'     => 10,
		);
		$stored   = get_option( self::OPT_SETTINGS, array() );
		return is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
	}

	/**
	 * Sanitise and store settings; syncs the editor-role capability with the toggle.
	 *
	 * @param array<string, mixed> $incoming Raw settings from the form.
	 * @return void
	 */
	public static function update_all( array $incoming ): void {
		$clean = array(
			'delete_on_uninstall'       => empty( $incoming['delete_on_uninstall'] ) ? 0 : 1,
			'editors_can_manage'        => empty( $incoming['editors_can_manage'] ) ? 0 : 1,
			'staging_mutations_enabled' => empty( $incoming['staging_mutations_enabled'] ) ? 0 : 1,
			'default_size'              => max( 96, min( 2048, (int) ( $incoming['default_size'] ?? 512 ) ) ),
			'per_user_create_limit'     => max( 0, min( 300, (int) ( $incoming['per_user_create_limit'] ?? 10 ) ) ),
		);
		update_option( self::OPT_SETTINGS, $clean, true );
		self::sync_role_capabilities( (bool) $clean['editors_can_manage'] );
	}

	/**
	 * Grant/revoke openqr_manage_codes on the editor role when the toggle flips.
	 *
	 * @param bool $grant Whether editors may manage codes.
	 * @return void
	 */
	private static function sync_role_capabilities( bool $grant ): void {
		$role = get_role( 'editor' );
		if ( ! $role ) {
			return;
		}
		if ( $grant ) {
			$role->add_cap( 'openqr_manage_codes' );
		} else {
			$role->remove_cap( 'openqr_manage_codes' );
		}
	}

	/**
	 * The stored API key. Only OpenQR_Api_Client reads this.
	 *
	 * @return string
	 */
	public static function api_key(): string {
		$key = get_option( self::OPT_KEY, '' );
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Store a key + the verified account. The raw key is written once here and read only by
	 * OpenQR_Api_Client; every screen reads the masked account record instead.
	 *
	 * @param string               $key     Raw `oqr_…` key.
	 * @param array<string, mixed> $account Verified /v1/me payload.
	 * @return void
	 */
	public static function store_connection( string $key, array $account ): void {
		update_option( self::OPT_KEY, $key, false );
		delete_option( self::OPT_AUTH_FAIL );
		update_option(
			self::OPT_ACCOUNT,
			array(
				'id'           => isset( $account['id'] ) ? (string) $account['id'] : '',
				'email'        => isset( $account['email'] ) ? (string) $account['email'] : '',
				'name'         => isset( $account['name'] ) ? (string) $account['name'] : '',
				'key_last4'    => substr( $key, -4 ),
				'plan'         => isset( $account['plan'] ) ? (string) $account['plan'] : '',
				'enforced'     => ! empty( $account['enforced'] ),
				'limits'       => isset( $account['limits'] ) && is_array( $account['limits'] ) ? $account['limits'] : array(),
				'usage'        => isset( $account['usage'] ) && is_array( $account['usage'] ) ? $account['usage'] : array(),
				'features'     => isset( $account['features'] ) && is_array( $account['features'] ) ? $account['features'] : array(),
				'raw'          => $account,
				'account_id'   => isset( $account['id'] ) ? (string) $account['id'] : '',
				'connected_at' => gmdate( 'c' ),
			),
			false
		);
		OpenQR_Cache::flush_all();
	}

	/**
	 * Remove the connection. Assets and registry rows survive on purpose.
	 *
	 * @return void
	 */
	public static function disconnect(): void {
		delete_option( self::OPT_KEY );
		delete_option( self::OPT_ACCOUNT );
		delete_option( self::OPT_AUTH_FAIL );
		OpenQR_Cache::flush_all();
	}

	/**
	 * The stored, verified account record as a SAFE projection: never raw payload, never any
	 * key material.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function account(): ?array {
		$account = get_option( self::OPT_ACCOUNT, null );
		if ( ! is_array( $account ) || empty( $account['email'] ) ) {
			return null;
		}
		return array(
			'email'        => $account['email'],
			'name'         => $account['name'],
			'key_last4'    => isset( $account['key_last4'] ) ? (string) $account['key_last4'] : '',
			'plan'         => isset( $account['plan'] ) ? $account['plan'] : '',
			'enforced'     => ! empty( $account['enforced'] ),
			'limits'       => isset( $account['limits'] ) ? $account['limits'] : array(),
			'usage'        => isset( $account['usage'] ) ? $account['usage'] : array(),
			'features'     => isset( $account['features'] ) ? $account['features'] : array(),
			'account_id'   => isset( $account['account_id'] ) ? $account['account_id'] : '',
			'connected_at' => isset( $account['connected_at'] ) ? $account['connected_at'] : '',
		);
	}

	/**
	 * Whether a verified connection exists.
	 *
	 * @return bool
	 */
	public static function is_connected(): bool {
		return null !== self::account() && '' !== self::api_key();
	}

	/**
	 * Epoch of the last 401, for the reconnect banner + read backoff.
	 *
	 * @return int
	 */
	public static function auth_failed_at(): int {
		return (int) get_option( self::OPT_AUTH_FAIL, 0 );
	}

	/**
	 * Record that the last API call hit a 401.
	 *
	 * @return void
	 */
	public static function mark_auth_failed(): void {
		update_option( self::OPT_AUTH_FAIL, time(), false );
	}

	/**
	 * Clear the 401 marker.
	 *
	 * @return void
	 */
	public static function clear_auth_failed(): void {
		delete_option( self::OPT_AUTH_FAIL );
	}

	/**
	 * The active-dynamic-code cap, or null for unlimited. Missing fields (older API) resolve
	 * PERMISSIVE so the plugin never shows a paid-gate UI it cannot verify.
	 *
	 * @return int|null
	 */
	public static function limit_dynamic_codes(): ?int {
		$a = self::account();
		if ( ! $a || ! array_key_exists( 'dynamic_codes', $a['limits'] ) ) {
			return null;
		}
		$v = $a['limits']['dynamic_codes'];
		return null === $v ? null : max( 0, (int) $v );
	}

	/**
	 * How many active dynamic codes the account currently uses, when known.
	 *
	 * @return int|null
	 */
	public static function usage_active_dynamic(): ?int {
		$a = self::account();
		if ( ! $a || ! isset( $a['usage']['active_dynamic'] ) ) {
			return null;
		}
		return max( 0, (int) $a['usage']['active_dynamic'] );
	}

	/**
	 * Whether scan protection (PIN/password) is available. Unknown = true (permissive).
	 *
	 * @return bool
	 */
	public static function feature_protection(): bool {
		$a = self::account();
		return ! $a || ! isset( $a['features']['protection'] ) || ! empty( $a['features']['protection'] );
	}

	/**
	 * Whether detailed analytics (device/referrer/place) is included. Unknown = true, so the
	 * upgrade card is driven by entitlements and never by an absent analytics field.
	 *
	 * @return bool
	 */
	public static function feature_detailed_analytics(): bool {
		$a = self::account();
		return ! $a || ! isset( $a['limits']['detailed_analytics'] ) || ! empty( $a['limits']['detailed_analytics'] );
	}
}

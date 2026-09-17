<?php
/**
 * Code service: one path for create/edit/delete/refresh used by admin screens, the REST
 * proxy, the block and the row actions. Wraps API responses into registry rows + assets.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Business logic over the OpenQR code lifecycle.
 */
final class OpenQR_Codes {

	/**
	 * Create a dynamic code (optionally linked to a post) and persist its asset.
	 *
	 * @param string $destination Public http(s) URL.
	 * @param string $label       Placement label.
	 * @param int    $post_id     Local post link (0 = none).
	 * @param string $theme       Saved OpenQR theme name (optional; colours + margin only).
	 * @return array<string, mixed> ok, error/code on failure, row on success.
	 */
	public static function create_dynamic( string $destination, string $label, int $post_id = 0, string $theme = '' ): array {
		$guard = OpenQR_Lifecycle::mutations_allowed();
		if ( is_wp_error( $guard ) ) {
			return array(
				'ok'    => false,
				'error' => $guard->get_error_message(),
				'code'  => 'staging_locked',
			);
		}
		if ( ! OpenQR_Settings::is_connected() ) {
			return array(
				'ok'    => false,
				'error' => __( 'Connect your OpenQR account first.', 'openqr' ),
				'code'  => 'reconnect',
			);
		}
		$throttle = self::throttle_check();
		if ( is_wp_error( $throttle ) ) {
			return array(
				'ok'    => false,
				'error' => $throttle->get_error_message(),
				'code'  => 'throttled',
			);
		}

		$res = OpenQR_Api_Client::create_dynamic( $destination, $label );
		if ( ! $res->is_ok() ) {
			return self::error_result( $res );
		}
		$body = $res->body() ?? array();

		$row              = OpenQR_Registry::upsert(
			array(
				'code_id'         => (string) ( $body['id'] ?? '' ),
				'kind'            => 'dynamic',
				'slug'            => (string) ( $body['slug'] ?? '' ),
				'short_url'       => (string) ( $body['short_url'] ?? '' ),
				'encoded_url'     => (string) ( $body['short_url'] ?? '' ),
				'style_hash'      => OpenQR_Assets::style_hash( (string) ( $body['short_url'] ?? '' ), '', '', $theme ),
				'style_theme'     => $theme,
				'post_id'         => $post_id,
				'placement_label' => '' !== $label ? $label : null,
				'status_mirror'   => 'active',
			)
		);
		$row['asset_url'] = OpenQR_Assets::ensure( $row );
		self::store_asset_path( (string) $row['code_id'], $row );
		OpenQR_Cache::flush_codes();
		return array(
			'ok'  => true,
			'row' => $row,
		);
	}

	/**
	 * Create a static code. The API builds the payload from fields; v1 supports all nine types.
	 *
	 * @param string               $type    url|text|email|phone|sms|whatsapp|wifi|geo|vcard.
	 * @param array<string, mixed> $fields  Type-specific fields.
	 * @param string               $label   Placement label.
	 * @param int                  $post_id Local post link (0 = none).
	 * @param string               $theme   Saved theme name.
	 * @return array<string, mixed>
	 */
	public static function create_static( string $type, array $fields, string $label, int $post_id = 0, string $theme = '' ): array {
		$guard = OpenQR_Lifecycle::mutations_allowed();
		if ( is_wp_error( $guard ) ) {
			return array(
				'ok'    => false,
				'error' => $guard->get_error_message(),
				'code'  => 'staging_locked',
			);
		}
		if ( ! OpenQR_Settings::is_connected() ) {
			return array(
				'ok'    => false,
				'error' => __( 'Connect your OpenQR account first.', 'openqr' ),
				'code'  => 'reconnect',
			);
		}
		$throttle = self::throttle_check();
		if ( is_wp_error( $throttle ) ) {
			return array(
				'ok'    => false,
				'error' => $throttle->get_error_message(),
				'code'  => 'throttled',
			);
		}

		$res = OpenQR_Api_Client::create_static( $type, $fields, $label );
		if ( ! $res->is_ok() ) {
			return self::error_result( $res );
		}
		$body    = $res->body() ?? array();
		$payload = (string) ( $body['payload'] ?? '' );

		$row              = OpenQR_Registry::upsert(
			array(
				'code_id'         => (string) ( $body['id'] ?? '' ),
				'kind'            => 'static',
				'payload'         => $payload,
				'style_hash'      => OpenQR_Assets::style_hash( $payload, '', '', $theme ),
				'style_theme'     => $theme,
				'post_id'         => $post_id,
				'placement_label' => '' !== $label ? $label : null,
				'status_mirror'   => 'active',
			)
		);
		$row['asset_url'] = OpenQR_Assets::ensure( $row );
		self::store_asset_path( (string) $row['code_id'], $row );
		OpenQR_Cache::flush_codes();
		return array(
			'ok'      => true,
			'row'     => $row,
			'payload' => $payload,
		);
	}

	/**
	 * Edit a dynamic code. Destination + label + status only in v1: slug renaming retires the
	 * old link (anything printed stops resolving) and is deliberately not offered here. A
	 * destination edit does NOT regenerate the asset: the encoded short URL is unchanged.
	 *
	 * @param string               $code_id OpenQR code ID.
	 * @param array<string, mixed> $patch   destination, label, status.
	 * @return array<string, mixed>
	 */
	public static function update( string $code_id, array $patch ): array {
		$guard = OpenQR_Lifecycle::mutations_allowed();
		if ( is_wp_error( $guard ) ) {
			return array(
				'ok'    => false,
				'error' => $guard->get_error_message(),
				'code'  => 'staging_locked',
			);
		}
		$res = OpenQR_Api_Client::update_code( $code_id, $patch );
		if ( ! $res->is_ok() ) {
			return self::error_result( $res );
		}
		$fields = array();
		if ( isset( $patch['label'] ) ) {
			$fields['placement_label'] = '' !== $patch['label'] ? $patch['label'] : null;
		}
		if ( isset( $patch['destination'] ) ) {
			$fields['encoded_url'] = (string) $patch['destination'];
		}
		if ( isset( $patch['status'] ) ) {
			$fields['status_mirror'] = (string) $patch['status'];
		}
		if ( $fields ) {
			OpenQR_Registry::update( $code_id, $fields );
		}
		OpenQR_Cache::flush_codes();
		return array(
			'ok'  => true,
			'row' => OpenQR_Registry::get_by_code_id( $code_id ),
		);
	}

	/**
	 * Delete the remote code, the registry row and the local assets (explicit user action).
	 *
	 * @param string $code_id OpenQR code ID.
	 * @return array<string, mixed>
	 */
	public static function delete( string $code_id ): array {
		$guard = OpenQR_Lifecycle::mutations_allowed();
		if ( is_wp_error( $guard ) ) {
			return array(
				'ok'    => false,
				'error' => $guard->get_error_message(),
				'code'  => 'staging_locked',
			);
		}
		$res = OpenQR_Api_Client::delete_code( $code_id );
		if ( ! $res->is_ok() && 404 !== $res->status() ) {
			return self::error_result( $res );
		}
		OpenQR_Assets::delete_code_assets( $code_id );
		OpenQR_Registry::forget( $code_id );
		OpenQR_Cache::flush_codes();
		return array( 'ok' => true );
	}

	/**
	 * Refresh a row from the API (resolves divergence: destination changed elsewhere). Never
	 * overwrites locally-observed post links.
	 *
	 * @param string $code_id OpenQR code ID.
	 * @return array<string, mixed>|null
	 */
	public static function refresh( string $code_id ): ?array {
		$res = OpenQR_Api_Client::get_code( $code_id );
		if ( ! $res->is_ok() ) {
			return null;
		}
		$body = $res->body() ?? array();
		return OpenQR_Registry::upsert(
			array(
				'code_id'         => (string) ( $body['id'] ?? $code_id ),
				'kind'            => 'dynamic',
				'slug'            => (string) ( $body['slug'] ?? '' ),
				'short_url'       => (string) ( $body['short_url'] ?? '' ),
				'encoded_url'     => (string) ( $body['destination'] ?? '' ),
				'placement_label' => isset( $body['label'] ) ? (string) $body['label'] : null,
				'status_mirror'   => (string) ( $body['status'] ?? 'active' ),
			)
		);
	}

	/**
	 * Scan headline counts for a code, via cache with stale fallback.
	 *
	 * @param string $code_id OpenQR code ID.
	 * @return array<string, mixed>
	 */
	public static function scan_summary( string $code_id ): array {
		$cached = OpenQR_Cache::remember(
			'scans',
			$code_id,
			static function () use ( $code_id ) {
				$res = OpenQR_Api_Client::scans( $code_id, 7 );
				if ( ! $res->is_ok() ) {
					return array( 'unreachable' => true );
				}
				$body = $res->body() ?? array();
				return array(
					'total'       => isset( $body['scans']['total'] ) ? (int) $body['scans']['total'] : 0,
					'last7'       => isset( $body['scans']['last7'] ) ? (int) $body['scans']['last7'] : 0,
					'top_country' => isset( $body['scans']['topCountry'] ) ? (string) $body['scans']['topCountry'] : '',
				);
			}
		);
		if ( is_array( $cached ) && empty( $cached['unreachable'] ) ) {
			return array( 'ok' => true ) + $cached;
		}
		$stale = OpenQR_Cache::stale( 'scans', $code_id );
		if ( is_array( $stale ) && empty( $stale['unreachable'] ) ) {
			return array(
				'ok'    => true,
				'stale' => true,
			) + $stale;
		}
		return array( 'ok' => false );
	}

	/**
	 * Per-user create safeguard. Configurable; 0 disables.
	 *
	 * @return WP_Error|null
	 */
	private static function throttle_check(): ?WP_Error {
		$limit   = OpenQR_Settings::per_user_create_limit();
		$user_id = get_current_user_id();
		$slot    = 'openqr:create:' . $user_id . ':' . gmdate( 'YmdG' );
		$count   = (int) get_transient( $slot );
		if ( $limit > 0 && $count >= $limit ) {
			return new WP_Error(
				'openqr_throttled',
				sprintf(
					/* translators: %d: per-user hourly limit. */
					__( 'You have created %d codes in the last hour. Wait a little, or raise the safeguard in Settings.', 'openqr' ),
					$limit
				)
			);
		}
		set_transient( $slot, $count + 1, HOUR_IN_SECONDS );
		return null;
	}

	/**
	 * Remember the produced asset path on the row.
	 *
	 * @param string               $code_id OpenQR code ID.
	 * @param array<string, mixed> $row     Row with asset_url.
	 * @return void
	 */
	private static function store_asset_path( string $code_id, array $row ): void {
		if ( ! empty( $row['asset_url'] ) ) {
			$uploads = wp_upload_dir();
			$path    = str_replace( $uploads['baseurl'], '', (string) $row['asset_url'] );
			OpenQR_Registry::update( $code_id, array( 'asset_path' => ltrim( $path, '/' ) ) );
		}
	}

	/**
	 * Map an API failure to a plugin result. Branch on `code`; the human string comes from the
	 * API itself and is written to be shown to end users verbatim.
	 *
	 * @param OpenQR_Api_Response $res Failed response.
	 * @return array<string, mixed>
	 */
	private static function error_result( OpenQR_Api_Response $res ): array {
		if ( $res->is_unreachable() ) {
			return array(
				'ok'    => false,
				'error' => __( 'OpenQR is unreachable right now. Your saved codes keep working; try again shortly.', 'openqr' ),
				'code'  => 'unreachable',
			);
		}
		$out = array(
			'ok'    => false,
			'error' => $res->message(),
			'code'  => '' !== $res->code() ? $res->code() : 'invalid_request',
		);
		if ( $res->is_auth_error() ) {
			$out['code']  = 'reconnect';
			$out['error'] = __( 'Your OpenQR connection was rejected. Reconnect your account.', 'openqr' );
		}
		if ( $res->is_plan_limit() ) {
			$out['upsell_url'] = OpenQR_Marketing_Link::build( '/pricing', 'plan-limit' );
		}
		return $out;
	}
}

<?php
/**
 * The ONLY place wp_remote_* is called and the ONLY reader of the API key option.
 *
 * Contract with openqr.uk:
 * - Bearer `oqr_…` in the Authorization header, never a query parameter.
 * - User-Agent `OpenQR-WordPress/{ver}; WordPress/{wpver}; PHP/{phpver}` — deliberately NO
 *   `+https://` fragment: on the OpenQR side a `+` marks declared bots, and while a valid key
 *   classifies as customer regardless, keyless discovery calls should not look like bots.
 * - Errors are `{ error, code }`; branch on code (see OpenQR_Api_Response), never on prose.
 * - The key is never logged, never written into exceptions, never sent anywhere but openqr.uk.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Typed client for the OpenQR REST API.
 */
final class OpenQR_Api_Client {

	/**
	 * Seconds. Renders must never stall a page; admin writes may take longer.
	 */
	const TIMEOUT_FAST = 5;
	const TIMEOUT_SLOW = 15;

	/**
	 * The UA string. Unit-tested to keep the `+` out (bot classification on the API side) and
	 * to keep WordPress + PHP versions in for support diagnostics.
	 *
	 * @return string
	 */
	public static function user_agent(): string {
		global $wp_version;
		return sprintf(
			'OpenQR-WordPress/%s; WordPress/%s; PHP/%s',
			OPENQR_VERSION,
			$wp_version ?? 'unknown',
			PHP_VERSION
		);
	}

	/**
	 * Generic request. The key rides the Authorization header only; redirects are never
	 * followed so the header cannot leak to a third-party host.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Path beginning with /v1.
	 * @param array<string, mixed> $body   JSON body (null = none).
	 * @param array<string, mixed> $args   Extra args: timeout, headers.
	 * @return OpenQR_Api_Response
	 */
	public static function request( string $method, string $path, ?array $body = null, array $args = array() ): OpenQR_Api_Response {
		$key = OpenQR_Settings::api_key();
		$url = OpenQR_Settings::api_base_url() . $path;

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => self::user_agent(),
		);
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		if ( isset( $args['headers'] ) && is_array( $args['headers'] ) ) {
			$headers = array_merge( $headers, $args['headers'] );
		}

		$request = array(
			'method'      => strtoupper( $method ),
			'timeout'     => $args['timeout'] ?? self::TIMEOUT_SLOW,
			'headers'     => $headers,
			'redirection' => 0,
			'decompress'  => true,
			'sslverify'   => true,
		);

		if ( null !== $body ) {
			$request['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $request );

		$out = OpenQR_Api_Response::from_http( $response );

		if ( $out->is_auth_error() ) {
			OpenQR_Settings::mark_auth_failed();
		}

		return $out;
	}

	/**
	 * GET /v1/me — the connect verification + identity probe.
	 *
	 * @return OpenQR_Api_Response
	 */
	public static function me(): OpenQR_Api_Response {
		return self::request( 'GET', '/v1/me' );
	}

	/**
	 * GET /v1 — keyless discovery index (API status on Settings; cached, never periodic).
	 *
	 * @return OpenQR_Api_Response
	 */
	public static function discovery(): OpenQR_Api_Response {
		$response = wp_remote_request(
			OpenQR_Settings::api_base_url() . '/v1',
			array(
				'method'      => 'GET',
				'timeout'     => self::TIMEOUT_FAST,
				'headers'     => array(
					'Accept'     => 'application/json',
					'User-Agent' => self::user_agent(),
				),
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new OpenQR_Api_Response( 0, array(), null );
		}
		return OpenQR_Api_Response::from_http( $response );
	}

	/**
	 * GET /v1/dynamic (optionally one page).
	 *
	 * @param int    $limit  Page size.
	 * @param string $cursor Opaque cursor.
	 * @param string $search Substring search.
	 * @return OpenQR_Api_Response
	 */
	public static function list_dynamic( int $limit = 200, string $cursor = '', string $search = '' ): OpenQR_Api_Response {
		$path = '/v1/dynamic?' . build_query(
			array_filter(
				array(
					'limit'  => $limit,
					'cursor' => $cursor,
					'search' => $search,
				),
				static function ( $v ) {
					return '' !== $v && null !== $v;
				}
			)
		);
		return self::request( 'GET', $path, null, array( 'timeout' => self::TIMEOUT_FAST ) );
	}

	/**
	 * GET /v1/codes — the superset library including statics.
	 *
	 * @param int    $limit  Page size.
	 * @param string $cursor Opaque cursor.
	 * @param string $search Substring search.
	 * @return OpenQR_Api_Response
	 */
	public static function list_codes( int $limit = 200, string $cursor = '', string $search = '' ): OpenQR_Api_Response {
		$path = '/v1/codes?' . build_query(
			array_filter(
				array(
					'limit'  => $limit,
					'cursor' => $cursor,
					'search' => $search,
				),
				static function ( $v ) {
					return '' !== $v && null !== $v;
				}
			)
		);
		return self::request( 'GET', $path, null, array( 'timeout' => self::TIMEOUT_FAST ) );
	}

	/**
	 * GET /v1/dynamic/{id}.
	 *
	 * @param string $id OpenQR code ID.
	 * @return OpenQR_Api_Response
	 */
	public static function get_code( string $id ): OpenQR_Api_Response {
		return self::request( 'GET', '/v1/dynamic/' . rawurlencode( $id ), null, array( 'timeout' => self::TIMEOUT_FAST ) );
	}

	/**
	 * POST /v1/dynamic. Idempotent: a lost response can be retried with the same key.
	 *
	 * @param string $destination     Public http(s) URL.
	 * @param string $label           Placement label (optional).
	 * @param string $idempotency_key UUID (generated when empty).
	 * @return OpenQR_Api_Response
	 */
	public static function create_dynamic( string $destination, string $label = '', string $idempotency_key = '' ): OpenQR_Api_Response {
		$idempotency_key = '' !== $idempotency_key ? $idempotency_key : self::new_idempotency_key();
		$body            = array( 'destination' => $destination );
		if ( '' !== $label ) {
			$body['label'] = $label;
		}
		return self::request(
			'POST',
			'/v1/dynamic',
			$body,
			array( 'headers' => array( 'Idempotency-Key' => $idempotency_key ) )
		);
	}

	/**
	 * POST /v1/codes — a static code. The API builds the payload from fields; the plugin never
	 * hand-rolls WIFI:/VCARD strings.
	 *
	 * @param string               $type   url|text|email|phone|sms|whatsapp|wifi|geo|vcard.
	 * @param array<string, mixed> $fields Type-specific field values.
	 * @param string               $label  Placement label.
	 * @return OpenQR_Api_Response
	 */
	public static function create_static( string $type, array $fields, string $label = '' ): OpenQR_Api_Response {
		$body = array(
			'type'   => $type,
			'fields' => $fields,
		);
		if ( '' !== $label ) {
			$body['label'] = $label;
		}
		return self::request(
			'POST',
			'/v1/codes',
			$body,
			array( 'headers' => array( 'Idempotency-Key' => self::new_idempotency_key() ) )
		);
	}

	/**
	 * PATCH /v1/dynamic/{id}. Destination and label only in v1: slug changes retire the old
	 * link (destructive to anything printed) and are deliberately not offered here.
	 *
	 * @param string               $id    OpenQR code ID.
	 * @param array<string, mixed> $patch destination / label / status / pause_until.
	 * @return OpenQR_Api_Response
	 */
	public static function update_code( string $id, array $patch ): OpenQR_Api_Response {
		return self::request( 'PATCH', '/v1/dynamic/' . rawurlencode( $id ), $patch );
	}

	/**
	 * DELETE /v1/dynamic/{id} — connection managers only, confirmed in the UI.
	 *
	 * @param string $id OpenQR code ID.
	 * @return OpenQR_Api_Response
	 */
	public static function delete_code( string $id ): OpenQR_Api_Response {
		return self::request( 'DELETE', '/v1/dynamic/' . rawurlencode( $id ) );
	}

	/**
	 * GET /v1/dynamic/{id}/scans?days=N.
	 *
	 * @param string $id   OpenQR code ID.
	 * @param int    $days Window in days (1-365, clamped server-side too).
	 * @return OpenQR_Api_Response
	 */
	public static function scans( string $id, int $days = 30 ): OpenQR_Api_Response {
		$days = max( 1, min( 365, $days ) );
		return self::request( 'GET', '/v1/dynamic/' . rawurlencode( $id ) . '/scans?days=' . $days, null, array( 'timeout' => self::TIMEOUT_FAST ) );
	}

	/**
	 * GET /v1/themes — the account's saved style themes.
	 *
	 * @return OpenQR_Api_Response
	 */
	public static function themes(): OpenQR_Api_Response {
		return self::request( 'GET', '/v1/themes', null, array( 'timeout' => self::TIMEOUT_FAST ) );
	}

	/**
	 * GET /v1/qr — stateless render (PNG for durable assets, SVG for downloads).
	 *
	 * @param string               $data Payload to encode.
	 * @param array<string, mixed> $args format / size / margin / dark / light / theme.
	 * @return OpenQR_Api_Response
	 */
	public static function render( string $data, array $args = array() ): OpenQR_Api_Response {
		// http_build_query, not build_query: the payload is arbitrary user content (Wi-Fi
		// separators, mailto params, ?p= permalinks) and an unencoded &, = or # used to
		// truncate it at the API, silently rendering the wrong code.
		$query = http_build_query(
			array_filter(
				array(
					'data'   => $data,
					'format' => $args['format'] ?? 'png',
					'size'   => $args['size'] ?? 1024,
					'margin' => $args['margin'] ?? 4,
					'dark'   => isset( $args['dark'] ) ? ltrim( (string) $args['dark'], '#' ) : null,
					'light'  => isset( $args['light'] ) ? ltrim( (string) $args['light'], '#' ) : null,
					'theme'  => $args['theme'] ?? null,
				),
				static function ( $v ) {
					return null !== $v && '' !== $v;
				}
			)
		);
		return self::request( 'GET', '/v1/qr?' . $query, null, array( 'timeout' => self::TIMEOUT_FAST ) );
	}

	/**
	 * A fresh retry key. No client-side state: any unique string works.
	 *
	 * @return string
	 */
	public static function new_idempotency_key(): string {
		return wp_generate_uuid4();
	}
}

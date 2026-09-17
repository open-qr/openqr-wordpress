<?php
/**
 * Mock transport for openqr.uk. Queues canned OpenQR_Api_Response-shaped HTTP replies and
 * records every outgoing request so tests can assert the load-bearing contract:
 * Bearer header present, UA has no '+', the key never appears in anything we would render.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

class MockHttp {

	/** @var array<int, array<string, mixed>> Every request the plugin made, in order. */
	public static $requests = array();

	/** @var array<int, array<string, mixed>> Queued responses (popped FIFO). */
	public static $queue = array();

	/** @var callable|null Fallback router: fn( string $method, string $url, array $request ): array|false. */
	public static $router = null;

	public static function install(): void {
		add_filter( 'pre_http_request', array( __CLASS__, 'intercept' ), 10, 3 );
	}

	public static function reset(): void {
		self::$requests = array();
		self::$queue    = array();
		self::$router   = null;
	}

	/**
	 * Queue a JSON response.
	 *
	 * @param int   $status HTTP status.
	 * @param mixed $body   JSON-encodable body.
	 * @param array $headers Extra headers.
	 */
	public static function queue_json( int $status, $body = null, array $headers = array() ): void {
		self::$queue[] = array(
			'status'  => $status,
			'raw'     => null === $body ? '' : (string) wp_json_encode( $body ),
			'headers' => array_merge( array( 'content-type' => 'application/json' ), $headers ),
		);
	}

	/** Queue binary bytes (a PNG / SVG render). */
	public static function queue_bytes( string $bytes, string $type = 'image/png' ): void {
		self::$queue[] = array(
			'status'  => 200,
			'raw'     => $bytes,
			'headers' => array( 'content-type' => $type ),
		);
	}

	/** Queue a network-level failure. */
	public static function queue_unreachable(): void {
		self::$queue[] = new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' );
	}

	public static function intercept( $response, $args, $url ) {
		$method           = strtoupper( $args['method'] ?? 'GET' );
		self::$requests[] = array(
			'method'  => $method,
			'url'     => $url,
			'headers' => array_change_key_case( (array) ( $args['headers'] ?? array() ), CASE_LOWER ),
			'body'    => $args['body'] ?? null,
		);

		$next = array_shift( self::$queue );
		if ( null === $next && null !== self::$router ) {
			$next = call_user_func( self::$router, $method, $url, $args );
		}
		if ( null === $next || false === $next ) {
			return array(
				'response' => array(
					'code'    => 599,
					'message' => 'MockHttp: no queued response',
				),
				'body'     => wp_json_encode(
					array(
						'error' => 'MockHttp exhausted',
						'code'  => 'invalid_request',
					)
				),
				'headers'  => array( 'content-type' => 'application/json' ),
			);
		}
		if ( is_wp_error( $next ) ) {
			return $next;
		}
		return array(
			'response' => array(
				'code'    => (int) $next['status'],
				'message' => '',
			),
			'body'     => $next['raw'],
			'headers'  => $next['headers'],
		);
	}

	/** The UA header sent on request N (1-based). */
	public static function ua_of( int $n ): string {
		return (string) ( self::$requests[ $n - 1 ]['headers']['user-agent'] ?? '' );
	}

	/** The Authorization header sent on request N (1-based), or ''. */
	public static function auth_of( int $n ): string {
		return (string) ( self::$requests[ $n - 1 ]['headers']['authorization'] ?? '' );
	}
}

<?php
/**
 * WP REST proxy (namespace openqr/v1). Cookie auth + nonce ONLY. The API key never reaches
 * the browser: no route returns it and no route accepts one on behalf of the browser.
 *
 * JS consumes `error.code` (openqr_reconnect, openqr_plan_limit, openqr_rate_limited,
 * openqr_slug_taken, openqr_invalid, openqr_unreachable), never message text.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Editor/admin REST surface.
 */
final class OpenQR_Rest_Proxy {

	/**
	 * REST namespace.
	 */
	const NAMESPACE_V1 = 'openqr/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'add_routes' ) );
	}

	/**
	 * Register every route with an explicit permission callback.
	 *
	 * @return void
	 */
	public static function add_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/status',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_connection' ),
				'callback'            => array( __CLASS__, 'route_status' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/connect',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_connection' ),
				'callback'            => array( __CLASS__, 'route_connect' ),
				'args'                => array(
					'api_key' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && 1 === preg_match( '/^oqr_[A-Za-z0-9]{10,120}$/', trim( $value ) );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/disconnect',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_connection' ),
				'callback'            => array( __CLASS__, 'route_disconnect' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/codes',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_codes' ),
					'callback'            => array( __CLASS__, 'route_list_codes' ),
					'args'                => array(
						'post_id' => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'scope'   => array(
							'required' => false,
							'type'     => 'string',
							'enum'     => array( 'site', 'account' ),
						),
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_codes' ),
					'callback'            => array( __CLASS__, 'route_create_code' ),
					'args'                => array(
						'destination' => array(
							'required' => true,
							'type'     => 'string',
						),
						'label'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'post_id'     => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'theme'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/static',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_codes' ),
				'callback'            => array( __CLASS__, 'route_create_static' ),
				'args'                => array(
					'type'    => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'url', 'text', 'email', 'phone', 'sms', 'whatsapp', 'wifi', 'geo', 'vcard' ),
					),
					'fields'  => array(
						'required' => true,
						'type'     => 'object',
					),
					'label'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'theme'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/codes/(?P<code_id>[A-Za-z0-9_-]+)',
			array(
				array(
					'methods'             => 'PATCH',
					'permission_callback' => array( __CLASS__, 'can_touch_route_code' ),
					'callback'            => array( __CLASS__, 'route_update_code' ),
					'args'                => array(
						'destination' => array(
							'required' => false,
							'type'     => 'string',
						),
						'label'       => array(
							'required'          => false,
							'type'              => array( 'string', 'null' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'      => array(
							'required' => false,
							'type'     => 'string',
							'enum'     => array( 'active', 'paused' ),
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_connection' ),
					'callback'            => array( __CLASS__, 'route_delete_code' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/codes/(?P<code_id>[A-Za-z0-9_-]+)/scans',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'can_touch_route_code' ),
				'callback'            => array( __CLASS__, 'route_scans' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/render',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( 'OpenQR_Capabilities', 'can_manage_codes' ),
				'callback'            => array( __CLASS__, 'route_render' ),
				'args'                => array(
					'data'  => array(
						'required'   => true,
						'type'       => 'string',
						'max_length' => 2000,
					),
					'size'  => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'dark'  => array(
						'required' => false,
						'type'     => 'string',
					),
					'light' => array(
						'required' => false,
						'type'     => 'string',
					),
					'theme' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Row-scoped gate: manage capability AND, when the row is linked to a post, edit rights
	 * on that post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_touch_route_code( WP_REST_Request $request ): bool {
		$row = OpenQR_Registry::get_by_code_id( (string) $request['code_id'] );
		return OpenQR_Capabilities::can_manage_row( $row );
	}

	/**
	 * GET /status: connection + entitlements + staging state. Last 4 of the key only.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_status(): WP_REST_Response {
		$account = OpenQR_Settings::account();
		return rest_ensure_response(
			array(
				'connected'      => null !== $account && '' !== OpenQR_Settings::api_key(),
				'email'          => $account['email'] ?? '',
				'key_last4'      => $account['key_last4'] ?? '',
				'plan'           => $account['plan'] ?? '',
				'enforced'       => $account['enforced'] ?? false,
				'limits'         => $account['limits'] ?? array(),
				'usage'          => $account['usage'] ?? array(),
				'features'       => $account['features'] ?? array(),
				'auth_failed_at' => OpenQR_Settings::auth_failed_at(),
				'staging'        => OpenQR_Lifecycle::is_staging(),
				'staging_locked' => (bool) OpenQR_Lifecycle::mutations_allowed(),
				'keys_url'       => OpenQR_Marketing_Link::keys_page(),
				'pricing_url'    => OpenQR_Marketing_Link::pricing(),
			)
		);
	}

	/**
	 * POST /connect: verify the SUBMITTED key against the live API before storing anything.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_connect( WP_REST_Request $request ): WP_REST_Response {
		$key      = trim( (string) $request['api_key'] );
		$response = self::verify_key( $key );
		if ( ! $response->is_ok() ) {
			if ( $response->is_unreachable() ) {
				return self::fail( 'openqr_unreachable', __( 'Could not reach openqr.uk. Check your server can make outbound HTTPS requests and try again.', 'openqr' ), 502 );
			}
			return self::fail( 'openqr_invalid_key', __( 'OpenQR rejected this key. Create a fresh key at openqr.uk/api and paste it again.', 'openqr' ), 401 );
		}
		OpenQR_Settings::store_connection( $key, $response->body() ?? array() );
		OpenQR_Capabilities::seed_roles();
		return rest_ensure_response( self::route_status()->get_data() );
	}

	/**
	 * GET /v1/me with an arbitrary key, without storing it.
	 *
	 * @param string $key Raw API key.
	 * @return OpenQR_Api_Response
	 */
	public static function verify_key( string $key ): OpenQR_Api_Response {
		$res = wp_remote_request(
			OpenQR_Settings::api_base_url() . '/v1/me',
			array(
				'method'      => 'GET',
				'timeout'     => OpenQR_Api_Client::TIMEOUT_SLOW,
				'headers'     => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'User-Agent'    => OpenQR_Api_Client::user_agent(),
					'Authorization' => 'Bearer ' . $key,
				),
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $res ) ) {
			return new OpenQR_Api_Response( 0, array(), null );
		}
		$headers = array();
		foreach ( (array) wp_remote_retrieve_headers( $res ) as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = implode( ', ', (array) $value );
		}
		return new OpenQR_Api_Response( (int) wp_remote_retrieve_response_code( $res ), $headers, (string) wp_remote_retrieve_body( $res ) );
	}

	/**
	 * POST /disconnect.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_disconnect(): WP_REST_Response {
		OpenQR_Lifecycle::clear_account_data();
		return rest_ensure_response( array( 'connected' => false ) );
	}

	/**
	 * GET /codes. scope=site (default, manage_codes): the local registry. scope=account
	 * (manage_connection): the full OpenQR library via the cached list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_list_codes( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$scope   = (string) $request->get_param( 'scope' );

		if ( 'account' === $scope ) {
			if ( ! OpenQR_Capabilities::can_manage_connection() ) {
				return self::fail( 'openqr_forbidden', __( 'Only connection managers can browse the whole account.', 'openqr' ), 403 );
			}
			$rows = OpenQR_Cache::remember(
				'codes',
				'account',
				static function () {
					$res = OpenQR_Api_Client::list_codes( 500 );
					if ( ! $res->is_ok() ) {
						return array(
							'unreachable' => true,
							'status'      => $res->status(),
						);
					}
					return $res->body() ?? array( 'codes' => array() );
				}
			);
			if ( ! is_array( $rows ) || ! empty( $rows['unreachable'] ) ) {
				$stale = OpenQR_Cache::stale( 'codes', 'account' );
				if ( is_array( $stale ) && empty( $stale['unreachable'] ) ) {
					return rest_ensure_response(
						array(
							'codes' => $stale['codes'] ?? array(),
							'stale' => true,
						)
					);
				}
				return self::fail( 'openqr_unreachable', __( 'OpenQR is unreachable right now.', 'openqr' ), 502 );
			}
			return rest_ensure_response(
				array(
					'codes'       => $rows['codes'] ?? array(),
					'next_cursor' => $rows['next_cursor'] ?? null,
				)
			);
		}

		$rows = $post_id > 0 ? OpenQR_Registry::for_post( $post_id ) : OpenQR_Registry::page( 100, 0 );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = self::row_projection( $row );
		}
		return rest_ensure_response( array( 'codes' => $out ) );
	}

	/**
	 * POST /codes: create a dynamic code, optionally linked to a post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_create_code( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 && ! OpenQR_Capabilities::can_manage_post_codes( $post_id ) ) {
			return self::fail( 'openqr_forbidden', __( 'You cannot manage QR codes on this content.', 'openqr' ), 403 );
		}
		$result = OpenQR_Codes::create_dynamic(
			(string) $request->get_param( 'destination' ),
			(string) $request->get_param( 'label' ),
			$post_id,
			(string) $request->get_param( 'theme' )
		);
		return self::service_response( $result );
	}

	/**
	 * POST /static: create a fixed-content code (the API builds the payload).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_create_static( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 && ! OpenQR_Capabilities::can_manage_post_codes( $post_id ) ) {
			return self::fail( 'openqr_forbidden', __( 'You cannot manage QR codes on this content.', 'openqr' ), 403 );
		}
		$fields = (array) $request->get_param( 'fields' );
		$clean  = array();
		foreach ( $fields as $k => $v ) {
			if ( is_string( $k ) && ( is_string( $v ) || is_numeric( $v ) || is_bool( $v ) ) ) {
				$clean[ sanitize_key( $k ) ] = $v;
			}
		}
		$result = OpenQR_Codes::create_static(
			(string) $request->get_param( 'type' ),
			$clean,
			(string) $request->get_param( 'label' ),
			$post_id,
			(string) $request->get_param( 'theme' )
		);
		return self::service_response( $result );
	}

	/**
	 * PATCH /codes/{id}: destination/label/status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_update_code( WP_REST_Request $request ): WP_REST_Response {
		$code_id = (string) $request['code_id'];
		$patch   = array();
		foreach ( array( 'destination', 'label', 'status' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$patch[ $field ] = $request->get_param( $field );
			}
		}
		if ( ! $patch ) {
			return self::fail( 'openqr_invalid', __( 'Nothing to update.', 'openqr' ), 400 );
		}
		return self::service_response( OpenQR_Codes::update( $code_id, $patch ) );
	}

	/**
	 * DELETE /codes/{id}: remote + registry + assets. Connection managers only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_delete_code( WP_REST_Request $request ): WP_REST_Response {
		return self::service_response( OpenQR_Codes::delete( (string) $request['code_id'] ) );
	}

	/**
	 * GET /codes/{id}/scans: headline counts + the entitlements-driven detailed flag.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_scans( WP_REST_Request $request ): WP_REST_Response {
		$summary = OpenQR_Codes::scan_summary( (string) $request['code_id'] );
		if ( empty( $summary['ok'] ) ) {
			return self::fail( 'openqr_unreachable', __( 'OpenQR is unreachable right now. Scan counts will refresh automatically.', 'openqr' ), 502 );
		}
		$detailed = OpenQR_Settings::feature_detailed_analytics();
		return rest_ensure_response(
			array(
				'ok'           => true,
				'total'        => $summary['total'] ?? 0,
				'last7'        => $summary['last7'] ?? 0,
				'top_country'  => $summary['top_country'] ?? '',
				'detailed'     => $detailed,
				'detailed_url' => $detailed ? OpenQR_Marketing_Link::build( '/dashboard', 'analytics' ) : '',
			)
		);
	}

	/**
	 * POST /render: editor preview (debounced client-side). Returns a PNG data URI.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_render( WP_REST_Request $request ): WP_REST_Response {
		$data = (string) $request->get_param( 'data' );
		if ( '' === $data || strlen( $data ) > 2000 ) {
			return self::fail( 'openqr_invalid', __( 'Nothing to render.', 'openqr' ), 400 );
		}
		if ( ! OpenQR_Settings::is_connected() ) {
			return self::fail( 'openqr_reconnect', __( 'Connect your OpenQR account first.', 'openqr' ), 401 );
		}
		$raw_size = $request->get_param( 'size' );
		$size = (int) ( $raw_size ? $raw_size : 512 );
		$args = array(
			'format' => 'png',
			'size'   => max( 96, min( 1024, $size ) ),
		);
		foreach ( array( 'dark', 'light', 'theme' ) as $opt ) {
			$value = $request->get_param( $opt );
			if ( $value ) {
				$args[ $opt ] = (string) $value;
			}
		}
		$res = OpenQR_Api_Client::render( $data, $args );
		if ( ! $res->is_ok() || false === strpos( $res->content_type(), 'image/' ) ) {
			return self::fail( 'openqr_unreachable', __( 'OpenQR could not render a preview right now.', 'openqr' ), 502 );
		}
		return rest_ensure_response(
			array(
				'data_uri' => 'data:image/png;base64,' . base64_encode( (string) $res->raw() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			)
		);
	}

	/**
	 * Safe projection of a registry row for JS: no payload bytes, no internal fields.
	 *
	 * @param array<string, mixed> $row Registry row.
	 * @return array<string, mixed>
	 */
	private static function row_projection( array $row ): array {
		return array(
			'code_id'     => $row['code_id'],
			'kind'        => $row['kind'],
			'label'       => $row['placement_label'],
			'short_url'   => $row['short_url'],
			'encoded_url' => 'dynamic' === $row['kind'] ? $row['encoded_url'] : '',
			'status'      => $row['status_mirror'],
			'post_id'     => (int) ( $row['post_id'] ?? 0 ),
			'asset_url'   => OpenQR_Assets::url( $row ),
			'created_at'  => $row['created_at'],
		);
	}

	/**
	 * Wrap a service result (or upstream failure) into a REST response. JS keys off `code`.
	 *
	 * @param array<string, mixed> $result Service result.
	 * @return WP_REST_Response
	 */
	private static function service_response( array $result ): WP_REST_Response {
		if ( ! empty( $result['ok'] ) ) {
			return rest_ensure_response( $result );
		}
		$map    = array(
			'reconnect'           => 401,
			'plan_limit_exceeded' => 403,
			'rate_limited'        => 429,
			'slug_taken'          => 409,
			'unreachable'         => 502,
			'staging_locked'      => 409,
			'throttled'           => 429,
			'invalid_request'     => 400,
		);
		$code   = (string) ( $result['code'] ?? 'invalid_request' );
		$code   = 0 === strpos( $code, 'openqr_' ) ? $code : 'openqr_' . $code;
		$bare   = str_replace( 'openqr_', '', $code );
		$status = $map[ $bare ] ?? 400;
		$body   = array(
			'error' => (string) $result['error'],
			'code'  => $code,
		);
		if ( ! empty( $result['upsell_url'] ) ) {
			$body['upsell_url'] = $result['upsell_url'];
		}
		return new WP_REST_Response( $body, $status );
	}

	/**
	 * A typed failure response.
	 *
	 * @param string $code    Machine-readable code.
	 * @param string $message Human message.
	 * @param int    $status  HTTP status.
	 * @return WP_REST_Response
	 */
	private static function fail( string $code, string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'error' => $message,
				'code'  => $code,
			),
			$status
		);
	}
}

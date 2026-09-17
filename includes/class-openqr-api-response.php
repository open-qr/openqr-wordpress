<?php
/**
 * Immutable API response value object.
 *
 * Callers branch on `code` (stable, machine-readable, emitted by openqr.uk) — never on
 * message text.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * One upstream response.
 */
final class OpenQR_Api_Response {

	/**
	 * Headers.
	 *
	 * @var array<string, string>
	 */
	private $headers = array();

	/**
	 * HTTP status (0 = unreachable).
	 *
	 * @var int
	 */
	private $status = 0;

	/**
	 * Decoded JSON body, when the content type is JSON.
	 *
	 * @var array<mixed>|null
	 */
	private $body;

	/**
	 * Raw body for non-JSON responses (images, HTML).
	 *
	 * @var string|null
	 */
	private $raw;

	/**
	 * Content type header.
	 *
	 * @var string
	 */
	private $content_type = '';

	/**
	 * Constructor.
	 *
	 * @param int                   $status  HTTP status.
	 * @param array<string, string> $headers Lowercased header map.
	 * @param string|null           $raw     Raw body.
	 */
	public function __construct( int $status, array $headers, ?string $raw ) {
		$this->status       = $status;
		$this->headers      = $headers;
		$this->raw          = $raw;
		$this->content_type = $headers['content-type'] ?? '';
		$this->body         = ( null !== $raw && false !== strpos( $this->content_type, 'json' ) ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $this->body ) ) {
			$this->body = null;
		}
	}

	/**
	 * HTTP status code (0 = network-level failure).
	 *
	 * @return int
	 */
	public function status(): int {
		return $this->status;
	}

	/**
	 * Decoded JSON body, or null for binary/absent bodies.
	 *
	 * @return array<mixed>|null
	 */
	public function body(): ?array {
		return $this->body;
	}

	/**
	 * Raw body bytes.
	 *
	 * @return string|null
	 */
	public function raw(): ?string {
		return $this->raw;
	}

	/**
	 * Content type header.
	 *
	 * @return string
	 */
	public function content_type(): string {
		return $this->content_type;
	}

	/**
	 * Stable machine-readable reason from the API (`unauthorized`, `plan_limit_exceeded`, …).
	 *
	 * @return string
	 */
	public function code(): string {
		$code = $this->body['code'] ?? '';
		return is_string( $code ) ? $code : '';
	}

	/**
	 * The API's human error string, empty when none.
	 *
	 * @return string
	 */
	public function error_message(): string {
		$error = $this->body['error'] ?? '';
		return is_string( $error ) ? $error : '';
	}

	/**
	 * Human message when the API sent one; a generic line otherwise. Never includes the key.
	 *
	 * @return string
	 */
	public function message(): string {
		$msg = $this->error_message();
		return '' !== $msg ? $msg : __( 'OpenQR returned an unexpected response.', 'openqr' );
	}

	/**
	 * Whether the response is 2xx.
	 *
	 * @return bool
	 */
	public function is_ok(): bool {
		return $this->status >= 200 && $this->status < 300;
	}

	/**
	 * Whether the key was rejected (401).
	 *
	 * @return bool
	 */
	public function is_auth_error(): bool {
		return 401 === $this->status;
	}

	/**
	 * Whether the caller hit the rate limit.
	 *
	 * @return bool
	 */
	public function is_rate_limited(): bool {
		return 429 === $this->status || 'rate_limited' === $this->code();
	}

	/**
	 * Whether a plan cap/feature gate blocked the call (code-driven, never status-driven).
	 *
	 * @return bool
	 */
	public function is_plan_limit(): bool {
		return 'plan_limit_exceeded' === $this->code();
	}

	/**
	 * Whether the short link is already taken.
	 *
	 * @return bool
	 */
	public function is_slug_taken(): bool {
		return 'slug_taken' === $this->code();
	}

	/**
	 * Whether openqr.uk could not be reached at all.
	 *
	 * @return bool
	 */
	public function is_unreachable(): bool {
		return 0 === $this->status;
	}

	/**
	 * Seconds to wait before retrying a 429 (Retry-After header, sane floor).
	 *
	 * @return int
	 */
	public function retry_after(): int {
		$v = $this->headers['retry-after'] ?? '';
		$s = is_numeric( $v ) ? (int) $v : 60;
		return max( 1, min( 3600, $s ) );
	}

	/**
	 * All headers, lowercased.
	 *
	 * @return array<string, string>
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * One header by name.
	 *
	 * @param string $name Header name (case-insensitive).
	 * @return string
	 */
	public function header( string $name ): string {
		return $this->headers[ strtolower( $name ) ] ?? '';
	}
}

<?php
/**
 * Response VO: the wire contract the plugin branches on.
 *
 * @package OpenQR
 */

class ApiResponseTest extends OpenQR_TestCase {

	private function response( int $status, ?string $raw = 'json', array $headers = array() ): OpenQR_Api_Response {
		if ( 'json' === $raw ) {
			$raw = '{"error":"x","code":"y"}';
		}
		if ( null !== $raw && empty( $headers['content-type'] ) ) {
			$headers['content-type'] = 'application/json';
		}
		return new OpenQR_Api_Response( $status, $headers, $raw );
	}

	public function test_ok_codes(): void {
		$this->assertTrue( $this->response( 201 )->is_ok() );
		$this->assertFalse( $this->response( 403 )->is_ok() );
	}

	public function test_auth_error_is_status_only(): void {
		$this->assertTrue( $this->response( 401, '{"error":"Unauthorized — …","code":"unauthorized"}' )->is_auth_error() );
		$this->assertFalse( $this->response( 403, '{"error":"cap","code":"plan_limit_exceeded"}' )->is_auth_error() );
	}

	public function test_plan_limit_is_code_driven(): void {
		// The whole point: a 400 with plan_limit_exceeded reads as a plan gate; a 403 without
		// the code does NOT.
		$this->assertTrue( $this->response( 400, '{"error":"cap","code":"plan_limit_exceeded"}' )->is_plan_limit() );
		$this->assertFalse( $this->response( 403, '{"error":"forbidden"}' )->is_plan_limit() );
	}

	public function test_slug_taken(): void {
		$this->assertTrue( $this->response( 409, '{"error":"taken","code":"slug_taken"}' )->is_slug_taken() );
	}

	public function test_rate_limited_from_status_and_code(): void {
		$this->assertTrue( $this->response( 429, '{"error":"slow down","code":"rate_limited"}' )->is_rate_limited() );
		$this->assertTrue( $this->response( 200, '{"error":"","code":"rate_limited"}' )->is_rate_limited() );
		$this->assertFalse( $this->response( 200, '{}' )->is_rate_limited() );
	}

	public function test_unreachable_is_status_zero(): void {
		$this->assertTrue( $this->response( 0, null )->is_unreachable() );
	}

	public function test_retry_after_clamped(): void {
		$this->assertSame( 60, $this->response( 429, null, array( 'retry-after' => '60' ) )->retry_after() );
		$this->assertSame( 1, $this->response( 429, null, array( 'retry-after' => '-5' ) )->retry_after() );
		$this->assertSame( 3600, $this->response( 429, null, array( 'retry-after' => '99999' ) )->retry_after() );
		$this->assertSame( 60, $this->response( 429, null, array() )->retry_after() );
	}

	public function test_binary_content_stays_raw(): void {
		$png = "\x89PNG\r\n\x1a\nbinary";
		$res = $this->response( 200, $png, array( 'content-type' => 'image/png' ) );
		$this->assertNull( $res->body() );
		$this->assertSame( $png, $res->raw() );
		$this->assertStringContainsString( 'image/png', $res->content_type() );
	}

	public function test_message_prefers_api_error_string(): void {
		$res = $this->response( 400, '{"error":"A human sentence.","code":"invalid_request"}' );
		$this->assertSame( 'A human sentence.', $res->message() );
		$this->assertSame( 'invalid_request', $res->code() );
	}
}

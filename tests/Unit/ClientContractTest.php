<?php
/**
 * The load-bearing client contract: UA classification safety, key handling, link builders,
 * SVG sanitising.
 *
 * @package OpenQR
 */

class ClientContractTest extends OpenQR_TestCase {

	public function test_user_agent_has_no_plus_marker(): void {
		// '+https://' or '(+…' in the UA would classify keyless calls as bots on the API side.
		$ua = OpenQR_Api_Client::user_agent();
		$this->assertStringStartsWith( 'OpenQR-WordPress/', $ua );
		$this->assertStringNotContainsString( '+', $ua );
		$this->assertStringContainsString( 'WordPress/', $ua );
		$this->assertStringContainsString( 'PHP/', $ua );
	}

	public function test_requests_carry_bearer_and_ua(): void {
		MockHttp::queue_json( 200, array( 'id' => 'u_1' ) );
		OpenQR_Api_Client::me();
		$this->assertCount( 1, MockHttp::$requests );
		$req = MockHttp::$requests[0];
		$this->assertSame( 'GET', $req['method'] );
		$this->assertSame( 'https://openqr.uk/v1/me', $req['url'] );
		$this->assertSame( 'Bearer oqr_testkey0000000000000000000000000000000000', $req['headers']['authorization'] );
		$this->assertStringStartsWith( 'OpenQR-WordPress/', $req['headers']['user-agent'] );
	}

	public function test_list_urls_include_cursor_and_search(): void {
		MockHttp::queue_json( 200, array( 'codes' => array() ) );
		OpenQR_Api_Client::list_dynamic( 200, 'abc123', 'menu' );
		$url = MockHttp::$requests[0]['url'];
		$this->assertStringContainsString( 'cursor=abc123', $url );
		$this->assertStringContainsString( 'search=menu', $url );

		MockHttp::queue_json( 200, array( 'codes' => array() ) );
		OpenQR_Api_Client::list_codes( 50 );
		$this->assertStringContainsString( '/v1/codes?', MockHttp::$requests[1]['url'] );
	}

	public function test_create_dynamic_sends_idempotency_key(): void {
		MockHttp::queue_json(
			201,
			array(
				'id'        => 'c1',
				'slug'      => 's',
				'short_url' => 'https://oqr.to/s',
			)
		);
		OpenQR_Api_Client::create_dynamic( 'https://example.com', 'Flyer', '11111111-2222-3333-4444-555555555555' );
		$headers = MockHttp::$requests[0]['headers'];
		$this->assertSame( '11111111-2222-3333-4444-555555555555', $headers['idempotency-key'] );
		$body = json_decode( MockHttp::$requests[0]['body'], true );
		$this->assertSame( 'https://example.com', $body['destination'] );
		$this->assertSame( 'Flyer', $body['label'] );
	}

	public function test_marketing_links_carry_acquisition_utms(): void {
		$url = OpenQR_Marketing_Link::build( '/pricing', 'plan-limit' );
		$this->assertStringStartsWith( 'https://openqr.uk/pricing?', $url );
		$this->assertStringContainsString( 'utm_source=wordpress', $url );
		$this->assertStringContainsString( 'utm_medium=plugin', $url );
		$this->assertStringContainsString( 'utm_campaign=plan-limit', $url );
	}

	public function test_svg_sanitiser_strips_scripts_and_events(): void {
		$dirty = '<svg xmlns="http://www.w3.org/2000/svg"><rect onclick="alert(1)" width="1" height="1"/>'
			. '<script>evil()</script><a xlink:href="javascript:alert(2)"><text>x</text></a>'
			. '<path d="M0 0" fill="#000"/></svg>';
		$clean = OpenQR_Assets::sanitise_svg( $dirty );
		$this->assertIsString( $clean );
		$this->assertStringNotContainsString( 'script', strtolower( $clean ) );
		$this->assertStringNotContainsString( 'onclick', strtolower( $clean ) );
		$this->assertStringNotContainsString( 'javascript:', strtolower( $clean ) );
		$this->assertStringContainsString( '<path', $clean );
	}

	public function test_svg_sanitiser_rejects_non_svg(): void {
		$this->assertNull( OpenQR_Assets::sanitise_svg( '' ) );
		$this->assertNull( OpenQR_Assets::sanitise_svg( '<html><body>not svg</body></html>' ) );
	}

	public function test_render_defaults_four_module_quiet_zone(): void {
		MockHttp::queue_bytes( $this->fake_png() );
		OpenQR_Api_Client::render( 'https://example.com', array( 'format' => 'png' ) );
		$url = MockHttp::$requests[0]['url'];
		$this->assertStringContainsString( 'margin=4', $url );
	}
}

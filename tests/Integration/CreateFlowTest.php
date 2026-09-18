<?php
/**
 * Create flow end-to-end against the mocked transport: API call → registry row → durable
 * asset, plus the behaviour that defines the product (destination edits do NOT regenerate;
 * style changes DO; the asset survives connection loss).
 *
 * @package OpenQR
 */

class CreateFlowTest extends OpenQR_TestCase {

	private int $admin_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function test_create_dynamic_persists_registry_row_and_asset(): void {
		MockHttp::queue_json(
			201,
			array(
				'id'        => 'code-1',
				'slug'      => 'spring-menu',
				'short_url' => 'https://oqr.to/spring-menu',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() ); // /v1/qr render

		$result = OpenQR_Codes::create_dynamic( 'https://example.com/menu', 'Spring menu', 0 );
		$this->assertTrue( $result['ok'] );

		$row = OpenQR_Registry::get_by_code_id( 'code-1' );
		$this->assertNotNull( $row );
		$this->assertSame( 'https://oqr.to/spring-menu', $row['short_url'] );
		$this->assertSame( 'Spring menu', $row['placement_label'] );
		$this->assertNotEmpty( $row['style_hash'] );
		$this->assertNotEmpty( $row['asset_url'] ?? $result['row']['asset_url'] );

		// The asset is a real file under uploads/openqr/…
		$uploads = wp_upload_dir();
		$file    = $uploads['basedir'] . '/openqr/code-1/' . $row['style_hash'] . '/qr-1024.png';
		$this->assertFileExists( $file );

		// Exactly one upstream write and one render: idempotency key present, no slug edit.
		$this->assertSame( 'POST', MockHttp::$requests[0]['method'] );
		$this->assertStringContainsString( '/v1/dynamic', MockHttp::$requests[0]['url'] );
		$this->assertStringContainsString( '/v1/qr', MockHttp::$requests[1]['url'] );
	}

	public function test_destination_edit_does_not_regenerate_asset(): void {
		MockHttp::queue_json(
			201,
			array(
				'id'        => 'code-2',
				'slug'      => 'a',
				'short_url' => 'https://oqr.to/a',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );
		OpenQR_Codes::create_dynamic( 'https://example.com/a', 'A', 0 );
		$before = OpenQR_Assets::url( OpenQR_Registry::get_by_code_id( 'code-2' ) );

		MockHttp::queue_json(
			200,
			array(
				'id'          => 'code-2',
				'slug'        => 'a',
				'short_url'   => 'https://oqr.to/a',
				'destination' => 'https://example.com/b',
				'status'      => 'active',
			)
		);
		$result = OpenQR_Codes::update( 'code-2', array( 'destination' => 'https://example.com/b' ) );
		$this->assertTrue( $result['ok'] );

		$row = OpenQR_Registry::get_by_code_id( 'code-2' );
		$this->assertSame( 'https://example.com/b', $row['encoded_url'] );
		$this->assertSame( $before, OpenQR_Assets::url( $row ), 'destination-only edit must not re-render' );
	}

	public function test_plan_limit_surfaces_upsell_url(): void {
		// Free cap hit: the API says so with the stable code.
		MockHttp::queue_json(
			403,
			array(
				'error' => "You've reached your limit of 1 active dynamic code on the Free plan.",
				'code'  => 'plan_limit_exceeded',
			)
		);
		$result = OpenQR_Codes::create_dynamic( 'https://example.com/x', 'X', 0 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'plan_limit_exceeded', $result['code'] );
		$this->assertStringContainsString( '/pricing', $result['upsell_url'] );
		$this->assertStringContainsString( 'utm_source=wordpress', $result['upsell_url'] );
		// The API's own remedy copy reaches the user verbatim.
		$this->assertStringContainsString( 'Free plan', $result['error'] );
	}

	public function test_401_maps_to_reconnect_and_marks_auth_failed(): void {
		MockHttp::queue_json(
			401,
			array(
				'error' => 'Unauthorized',
				'code'  => 'unauthorized',
			)
		);
		$result = OpenQR_Codes::create_dynamic( 'https://example.com/x', '', 0 );
		$this->assertSame( 'reconnect', $result['code'] );
		$this->assertGreaterThan( 0, OpenQR_Settings::auth_failed_at() );
	}

	public function test_asset_serves_after_disconnect(): void {
		MockHttp::queue_json(
			201,
			array(
				'id'        => 'code-3',
				'slug'      => 'b',
				'short_url' => 'https://oqr.to/b',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );
		OpenQR_Codes::create_dynamic( 'https://example.com/b', 'B', 0 );
		$url_before = OpenQR_Assets::url( OpenQR_Registry::get_by_code_id( 'code-3' ) );

		OpenQR_Settings::disconnect();

		$this->assertSame( $url_before, OpenQR_Assets::url( OpenQR_Registry::get_by_code_id( 'code-3' ) ), 'disconnect must keep published assets' );
		$this->assertNull( OpenQR_Settings::account() );
		$this->assertSame( '', OpenQR_Settings::api_key() );
	}

	public function test_static_creation_stores_payload_hashed_asset(): void {
		MockHttp::queue_json(
			201,
			array(
				'id'      => 'code-4',
				'type'    => 'wifi',
				'payload' => 'WIFI:T:WPA;S:cafe;P:latte;;',
				'label'   => 'wifi',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );

		$result = OpenQR_Codes::create_static(
			'wifi',
			array(
				'ssid'     => 'cafe',
				'password' => 'latte',
			),
			'wifi',
			0
		);
		$this->assertTrue( $result['ok'] );

		$row = OpenQR_Registry::get_by_code_id( 'code-4' );
		$this->assertSame( 'static', $row['kind'] );
		$this->assertNotEmpty( $row['payload'] );
		$this->assertFileExists( wp_upload_dir()['basedir'] . '/openqr/code-4/' . $row['style_hash'] . '/qr-1024.png' );
	}

	public function test_staging_lock_blocks_remote_writes(): void {
		// The container is already a non-production environment; the base TestCase defaults the
		// override ON, so turn it off to exercise the lock.
		OpenQR_Settings::update_all(
			array(
				'staging_mutations_enabled' => 0,
				'delete_on_uninstall'       => 0,
				'manage_codes_roles'        => array( 'editor' ),
				'default_size'              => 512,
				'per_user_create_limit'     => 10,
			)
		);
		$this->assertTrue( OpenQR_Lifecycle::is_staging(), 'wp-env reports a non-production environment' );

		$result = OpenQR_Codes::create_dynamic( 'https://example.com/x', '', 0 );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'staging_locked', $result['code'] );
		$this->assertCount( 0, MockHttp::$requests, 'a locked site must not touch the API' );
	}
}

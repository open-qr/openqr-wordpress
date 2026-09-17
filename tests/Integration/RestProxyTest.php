<?php
/**
 * REST proxy: the permission matrix and the key-never-to-browser guarantee.
 *
 * @package OpenQR
 */

class RestProxyTest extends OpenQR_TestCase {

	private int $admin;
	private int $editor;
	private int $contributor;

	public function set_up(): void {
		parent::set_up();
		do_action( 'rest_api_init' );

		$this->admin       = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );

		// Editors get codes capability only when the Settings toggle grants it.
		get_userdata( $this->editor )->add_cap( 'openqr_manage_codes' );
	}

	public function test_anonymous_gets_nothing(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', '/status' )->get_status() );
		$this->assertSame( 401, $this->rest( 'GET', '/codes' )->get_status() );
		$this->assertSame( 401, $this->rest( 'POST', '/codes', array( 'destination' => 'https://example.com' ) )->get_status() );
	}

	public function test_contributor_cannot_create_or_read(): void {
		wp_set_current_user( $this->contributor );
		$this->assertSame( 403, $this->rest( 'GET', '/codes' )->get_status() );
		$this->assertSame( 403, $this->rest( 'POST', '/codes', array( 'destination' => 'https://example.com' ) )->get_status() );
	}

	public function test_delegated_editor_creates_for_own_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->editor,
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( $this->editor );

		MockHttp::queue_json(
			201,
			array(
				'id'        => 'code-e1',
				'slug'      => 'e1',
				'short_url' => 'https://oqr.to/e1',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );

		$res = $this->rest(
			'POST',
			'/codes',
			array(
				'destination' => get_permalink( $post_id ),
				'label'       => 'L',
				'post_id'     => $post_id,
			)
		);
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( 'code-e1', $res->get_data()['row']['code_id'] );
	}

	public function test_delegated_editor_cannot_touch_unrelated_code(): void {
		// A code with no post link needs manage_codes (editor has it) — but a row linked to
		// someone else's post additionally needs edit_post on that post.
		$other_post = self::factory()->post->create(
			array(
				'post_author' => $this->admin,
				'post_status' => 'publish',
			)
		);
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-admin',
				'kind'        => 'dynamic',
				'slug'        => 'adm',
				'short_url'   => 'https://oqr.to/adm',
				'encoded_url' => 'https://example.com',
				'post_id'     => $other_post,
			)
		);

		// Give the editor edit rights on a DIFFERENT post only; contributor has none anywhere.
		wp_set_current_user( $this->contributor );
		$this->assertSame( 403, $this->rest( 'PATCH', '/codes/code-admin', array( 'label' => 'x' ) )->get_status() );
		$this->assertSame( 403, $this->rest( 'DELETE', '/codes/code-admin' )->get_status() );
	}

	public function test_delete_requires_connection_manager(): void {
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-d1',
				'kind'        => 'dynamic',
				'slug'        => 'd1',
				'short_url'   => 'https://oqr.to/d1',
				'encoded_url' => 'https://example.com',
			)
		);
		wp_set_current_user( $this->editor );
		$this->assertSame( 403, $this->rest( 'DELETE', '/codes/code-d1' )->get_status() );
	}

	public function test_account_scope_requires_connection_manager(): void {
		// Delegated editors read the SITE registry, but never the account library.
		wp_set_current_user( $this->editor );
		$this->assertSame( 200, $this->rest( 'GET', '/codes' )->get_status() );
		$this->assertSame( 403, $this->rest( 'GET', '/codes?scope=account' )->get_status() );

		wp_set_current_user( $this->admin );
		MockHttp::queue_json( 200, array( 'codes' => array( array( 'id' => 'x' ) ) ) );
		$res = $this->rest( 'GET', '/codes?scope=account' );
		$this->assertSame( 200, $res->get_status() );
	}

	public function test_key_never_appears_in_any_proxy_response(): void {
		$key = OpenQR_Settings::api_key();
		wp_set_current_user( $this->admin );

		$routes = array(
			array( 'GET', '/status', array() ),
			array( 'GET', '/codes', array() ),
			array( 'GET', '/codes?scope=account', array() ),
		);
		foreach ( $routes as $route ) {
			MockHttp::queue_json( 200, array( 'codes' => array() ) );
			$res  = $this->rest( $route[0], $route[1] );
			$blob = wp_json_encode( $res );
			$this->assertStringNotContainsString( $key, $blob, "the API key leaked through {$route[1]}" );
			$this->assertStringNotContainsString( 'oqr_testkey', $blob );
		}

		// Even a create response: row projection only.
		MockHttp::queue_json(
			201,
			array(
				'id'        => 'code-k',
				'slug'      => 'k',
				'short_url' => 'https://oqr.to/k',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );
		$res  = $this->rest( 'POST', '/codes', array( 'destination' => 'https://example.com' ) );
		$blob = wp_json_encode( $res );
		$this->assertStringNotContainsString( $key, $blob );
	}

	public function test_status_reports_entitlements_and_staging_state(): void {
		wp_set_current_user( $this->admin );
		$data = $this->rest( 'GET', '/status' )->get_data();
		$this->assertTrue( $data['connected'] );
		$this->assertSame( 'owner@example.com', $data['email'] );
		$this->assertSame( '0000', $data['key_last4'], 'status exposes last 4 only' );
		$this->assertSame( 1, $data['limits']['dynamic_codes'] );
		$this->assertSame( '0000', $data['key_last4'] );
		$this->assertFalse( $data['staging_locked'] );
	}

	public function test_connect_verifies_submitted_key_before_storing(): void {
		wp_set_current_user( $this->admin );

		MockHttp::queue_json(
			401,
			array(
				'error' => 'nope',
				'code'  => 'unauthorized',
			)
		);
		$res = $this->rest( 'POST', '/connect', array( 'api_key' => 'oqr_badbadbadbadbadbadbadbadbadbadbadbadbad' ) );
		$this->assertSame( 401, $res->get_status() );
		// The old connection is untouched.
		$this->assertSame( 'owner@example.com', OpenQR_Settings::account()['email'] );

		MockHttp::queue_json(
			200,
			array(
				'id'       => 'u_9',
				'email'    => 'new@example.com',
				'name'     => 'New',
				'plan'     => 'pro',
				'enforced' => true,
				'limits'   => array(),
				'usage'    => array(),
				'features' => array(),
			)
		);
		$res = $this->rest( 'POST', '/connect', array( 'api_key' => 'oqr_goodgoodgoodgoodgoodgoodgoodgoodgood' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertTrue( $res->get_data()['connected'] );
		$this->assertSame( 'new@example.com', OpenQR_Settings::account()['email'] );
	}

	public function test_render_requires_connected_account(): void {
		wp_set_current_user( $this->editor );
		OpenQR_Settings::disconnect();
		$res = $this->rest( 'POST', '/render', array( 'data' => 'https://example.com' ) );
		$this->assertSame( 401, $res->get_status() );
		$this->assertSame( 'openqr_reconnect', $res->get_data()['code'] );
	}
}

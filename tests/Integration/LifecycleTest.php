<?php
/**
 * Registry + lifecycle: permalink drift flags, wipe policy, shortcode/block fallback
 * behaviour.
 *
 * @package OpenQR
 */

class LifecycleTest extends OpenQR_TestCase {

	public function test_permalink_change_is_flagged_and_offered_not_auto_written(): void {
		// Permalink-change detection needs pretty permalinks (plain ?p=N never changes).
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules();

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'old-slug',
			)
		);
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-p1',
				'kind'        => 'dynamic',
				'slug'        => 'p1',
				'short_url'   => 'https://oqr.to/p1',
				'encoded_url' => 'https://example.com/old-slug',
				'post_id'     => $post_id,
			)
		);

		// Same publish cycle, renamed.
		global $wp_rewrite;
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'new-slug',
			)
		);

		$flag = get_post_meta( $post_id, OpenQR_Lifecycle::POST_FLAG_META, true );
		$this->assertIsArray( $flag, 'a permalink change on a linked post must raise the offer flag' );
		// And crucially: no remote PATCH was issued.
		foreach ( MockHttp::$requests as $req ) {
			$this->assertStringNotContainsString( '/v1/dynamic', $req['url'] );
		}
	}

	public function test_wipe_site_data_removes_everything_but_only_when_asked(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules();

		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-w1',
				'kind'        => 'dynamic',
				'slug'        => 'w1',
				'short_url'   => 'https://oqr.to/w1',
				'encoded_url' => 'https://example.com',
			)
		);

		// Default disconnect: registry + assets survive.
		OpenQR_Lifecycle::clear_account_data();
		$this->assertNotNull( OpenQR_Registry::get_by_code_id( 'code-w1' ) );

		// Full wipe (uninstall opt-in): gone.
		OpenQR_Lifecycle::wipe_site_data();
		$this->assertNull( OpenQR_Registry::get_by_code_id( 'code-w1' ) );
		$this->assertFalse( get_option( OpenQR_Settings::OPT_KEY ) );
		$this->assertFalse( get_option( OpenQR_Settings::OPT_ACCOUNT ) );
	}

	public function test_shortcode_renders_stored_code_with_durable_asset(): void {
		MockHttp::queue_json(
			201,
			array(
				'id'        => 'code-s1',
				'slug'      => 's1',
				'short_url' => 'https://oqr.to/s1',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );
		OpenQR_Codes::create_dynamic( 'https://example.com/s', 'S', 0 );

		$html = do_shortcode( '[openqr code="code-s1" size="240"]' );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'width="240"', $html );
		$this->assertStringContainsString( 'uploads/openqr/code-s1/', $html );

		// Once the asset exists, the shortcode renders with ZERO upstream calls.
		$req_count = count( MockHttp::$requests );
		$html      = do_shortcode( '[openqr code="code-s1" size="240"]' );
		$this->assertCount( $req_count, MockHttp::$requests, 'public render must never call the API' );
		$this->assertStringContainsString( '<img', $html );
	}

	public function test_shortcode_escapes_hostile_labels(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules();

		OpenQR_Registry::upsert(
			array(
				'code_id'         => 'code-x1',
				'kind'            => 'dynamic',
				'slug'            => 'x1',
				'short_url'       => 'https://oqr.to/x1',
				'encoded_url'     => 'https://example.com',
				'style_hash'      => OpenQR_Assets::style_hash( 'https://oqr.to/x1' ),
				'placement_label' => '"><script>alert(1)</script>',
			)
		);
		// Simulate an existing asset so the shortcode can render.
		$row  = OpenQR_Registry::get_by_code_id( 'code-x1' );
		$path = wp_upload_dir()['basedir'] . '/openqr/code-x1/' . $row['style_hash'];
		wp_mkdir_p( $path );
		file_put_contents( $path . '/qr-1024.png', $this->fake_png() );

		$html = do_shortcode( '[openqr code="code-x1"]' );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&quot;&gt;', $html );
	}

	public function test_shortcode_unknown_code_renders_nothing(): void {
		$this->assertSame( '', do_shortcode( '[openqr code="missing"]' ) );
	}

	public function test_block_render_serves_fresh_markup_while_active(): void {
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-b1',
				'kind'        => 'dynamic',
				'slug'        => 'b1',
				'short_url'   => 'https://oqr.to/b1',
				'encoded_url' => 'https://example.com',
				'style_hash'  => OpenQR_Assets::style_hash( 'https://oqr.to/b1' ),
			)
		);
		$row  = OpenQR_Registry::get_by_code_id( 'code-b1' );
		$path = wp_upload_dir()['basedir'] . '/openqr/code-b1/' . $row['style_hash'];
		wp_mkdir_p( $path );
		file_put_contents( $path . '/qr-1024.png', $this->fake_png() );

		$html = OpenQR_Blocks::render(
			array(
				'codeId'   => 'code-b1',
				'size'     => 280,
				'showLink' => true,
			)
		);
		$this->assertStringContainsString( '<figure', $html );
		$this->assertStringContainsString( 'uploads/openqr/code-b1/', $html );
		$this->assertStringContainsString( '<code>https://oqr.to/b1</code>', $html );
	}

	public function test_block_render_empty_without_asset(): void {
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-b2',
				'kind'        => 'dynamic',
				'slug'        => 'b2',
				'short_url'   => 'https://oqr.to/b2',
				'encoded_url' => 'https://example.com',
			)
		);
		$this->assertSame(
			'',
			OpenQR_Blocks::render(
				array(
					'codeId' => 'code-b2',
					'size'   => 280,
				)
			)
		);
	}

	public function test_cache_index_tracks_and_flushes(): void {
		$key = OpenQR_Cache::remember(
			'codes',
			'unit',
			static function () {
				return array( 'codes' => array( 1 ) );
			}
		);
		$this->assertSame( array( 'codes' => array( 1 ) ), $key );
		OpenQR_Cache::flush_codes();
		// Second call re-runs the callback after flush.
		$again = OpenQR_Cache::remember(
			'codes',
			'unit',
			static function () {
				return array( 'codes' => array( 2 ) );
			}
		);
		$this->assertSame( array( 'codes' => array( 2 ) ), $again );
	}
}

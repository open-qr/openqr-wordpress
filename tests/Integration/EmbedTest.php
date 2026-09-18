<?php
/**
 * The shared builder embed renderer: template resolution, the never-create-a-code rule,
 * honest analytics (buttons are plain links), and graceful degradation when disconnected.
 *
 * @package OpenQR
 */

class EmbedTest extends OpenQR_TestCase {

	/** Args for a custom static embed (overridable per test). */
	private function render_args( array $over = array() ): array {
		return array_merge(
			array(
				'source'       => 'custom',
				'type'         => 'url',
				'fields'       => array( 'url' => 'example.com/thing' ),
				'presentation' => OpenQR_Embed::QR_BUTTON,
				'heading'      => 'Take this with you',
				'instruction'  => 'Scan to open on your phone',
				'button_text'  => 'Open on your phone',
				'download'     => true,
			),
			$over
		);
	}

	public function test_custom_static_renders_cached_asset_without_creating_a_code(): void {
		MockHttp::queue_bytes( $this->fake_png() );

		$html = OpenQR_Embed::render( $this->render_args() );

		$this->assertStringContainsString( 'openqr-embed__qr', $html );
		$this->assertStringContainsString( '/openqr/builder/', $html, 'static embeds persist under the builder asset tree' );
		$this->assertStringContainsString( 'openqr-embed__button', $html );
		$this->assertStringContainsString( 'https://example.com/thing', $html, 'the button is a plain link to the destination' );
		$this->assertStringContainsString( 'download', $html );
		$this->assertStringNotContainsString( 'openqr-embed__instruction', $html, 'button presentation uses the button, not the caption' );

		$this->assertCount( 1, MockHttp::$requests, 'one /v1/qr render only' );
		$this->assertSame( 'GET', MockHttp::$requests[0]['method'] );
		$this->assertStringContainsString( '/v1/qr', MockHttp::$requests[0]['url'] );
		$this->assertSame( 0, OpenQR_Registry::count(), 'rendering a page must never create a cloud code' );

		// Second render: served from the stored asset, no API call at all.
		$calls = count( MockHttp::$requests );
		OpenQR_Embed::render( $this->render_args() );
		$this->assertSame( $calls, count( MockHttp::$requests ), 'the asset is durable: no re-render' );
	}

	public function test_current_source_resolves_the_queried_object_not_the_template(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Listing A' ) );
		MockHttp::queue_bytes( $this->fake_png() );

		$this->go_to( get_permalink( $post_id ) );
		$html = OpenQR_Embed::render( $this->render_args( array( 'source' => 'current' ) ) );

		$this->assertStringContainsString( '/openqr/builder/', $html );
		// The cached asset is keyed by payload: rendering for Listing A must have encoded
		// Listing A's permalink (whatever scheme the test install uses), not a template URL.
		$hash = OpenQR_Assets::style_hash( get_permalink( $post_id ), '#232E3A', '#FFFFFF' );
		$this->assertFileExists( wp_upload_dir()['basedir'] . '/openqr/builder/' . $hash . '/qr-220.png' );
	}

	public function test_existing_code_source_uses_the_short_url_and_links_the_destination(): void {
		// A realistic row: the create flow computes style_hash from the short URL.
		OpenQR_Registry::upsert(
			array(
				'code_id'         => 'code-e1',
				'kind'            => 'dynamic',
				'slug'            => 'e1',
				'short_url'       => 'https://oqr.to/e1',
				'encoded_url'     => 'https://example.com/destination',
				'placement_label' => 'Window poster',
				'style_hash'      => OpenQR_Assets::style_hash( 'https://oqr.to/e1', '', '' ),
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );

		$html = OpenQR_Embed::render(
			$this->render_args(
				array(
					'source'   => 'code',
					'code_id'  => 'code-e1',
					'download' => false,
				)
			)
		);

		// The asset path is keyed by a hash of the encoded content: the short URL, so scans
		// are counted by the redirect worker exactly like a printed code.
		$hash = OpenQR_Assets::style_hash( 'https://oqr.to/e1', '', '' );
		$this->assertStringContainsString( '/openqr/code-e1/' . $hash . '/', $html );
		$this->assertStringContainsString( 'href="https://example.com/destination"', $html, 'the button is a plain link, never a counted scan' );
		$this->assertStringNotContainsString( 'openqr-embed__inspect', $html, 'inspection is editor-only' );
	}

	public function test_editor_placeholder_inspects_the_destination(): void {
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-e2',
				'kind'        => 'dynamic',
				'short_url'   => 'https://oqr.to/e2',
				'encoded_url' => 'https://example.com/dest-2',
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );

		$html = OpenQR_Embed::render(
			$this->render_args(
				array(
					'source'  => 'code',
					'code_id' => 'code-e2',
					'editor'  => true,
				)
			)
		);

		$this->assertStringContainsString( 'openqr-embed__inspect', $html );
		$this->assertStringContainsString( 'https://example.com/dest-2', $html );
	}

	public function test_disconnected_renders_nothing_on_the_frontend_and_a_placeholder_in_the_editor(): void {
		// Fresh content: no cached asset exists, so with no connection there is nothing honest
		// to render. (Previously rendered assets keep serving when disconnected, by design.)
		$args = $this->render_args( array( 'fields' => array( 'url' => 'example.com/disconnected-probe' ) ) );
		OpenQR_Settings::disconnect();

		$this->assertSame( '', OpenQR_Embed::render( $args ), 'a public page never shows a broken QR' );
		$this->assertStringContainsString(
			'openqr-embed--placeholder',
			OpenQR_Embed::render(
				$this->render_args(
					array(
						'fields' => array( 'url' => 'example.com/disconnected-probe' ),
						'editor' => true,
					)
				)
			)
		);
	}

	public function test_unruly_colours_fall_back_to_scannable_defaults(): void {
		MockHttp::queue_bytes( $this->fake_png() );

		OpenQR_Embed::render(
			$this->render_args(
				array(
					'fields' => array( 'url' => 'example.com/colour-probe' ),
					'dark'   => 'javascript:alert(1)',
					'light'  => 'transparent',
				)
			)
		);

		$url = MockHttp::$requests[0]['url'];
		$this->assertStringContainsString( 'dark=232E3A', $url, 'dark falls back to the OpenQR ink default' );
		$this->assertStringContainsString( 'light=FFFFFF', $url, 'light falls back to white' );
		$this->assertStringContainsString( 'data=https%3A%2F%2Fexample.com%2Fcolour-probe', $url, 'the payload travels URL-encoded' );
	}
}

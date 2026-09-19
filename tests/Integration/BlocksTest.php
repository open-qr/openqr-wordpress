<?php
/**
 * The Gutenberg block's server render: legacy 1.0 attribute shapes keep their exact old
 * markup; new source shapes route through the shared embed renderer.
 *
 * @package OpenQR
 */

class BlocksTest extends OpenQR_TestCase {

	public function test_legacy_shape_keeps_v1_output(): void {
		OpenQR_Registry::upsert(
			array(
				'code_id'         => 'code-b1',
				'kind'            => 'dynamic',
				'slug'            => 'b1',
				'short_url'       => 'https://oqr.to/b1',
				'encoded_url'     => 'https://example.com/b1',
				'placement_label' => 'Poster',
				'style_hash'      => OpenQR_Assets::style_hash( 'https://oqr.to/b1', '', '' ),
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );

		// No `source` key: the legacy shape.
		$html = OpenQR_Blocks::render(
			array(
				'codeId'   => 'code-b1',
				'size'     => 280,
				'showLink' => true,
				'label'    => 'Poster',
			)
		);

		$this->assertStringContainsString( '<figure class="wp-block-openqr-qr openqr-figure">', $html );
		$this->assertStringContainsString( '<figcaption class="openqr-caption"><code>https://oqr.to/b1</code></figcaption>', $html );
	}

	public function test_new_source_shape_routes_through_the_shared_renderer(): void {
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-b2',
				'kind'        => 'dynamic',
				'slug'        => 'b2',
				'short_url'   => 'https://oqr.to/b2',
				'encoded_url' => 'https://example.com/b2-dest',
				'style_hash'  => OpenQR_Assets::style_hash( 'https://oqr.to/b2', '', '' ),
			)
		);
		MockHttp::queue_bytes( $this->fake_png() );

		$html = OpenQR_Blocks::render(
			array(
				'source'       => 'code',
				'codeId'       => 'code-b2',
				'presentation' => OpenQR_Embed::QR_BUTTON,
				'buttonText'   => 'Open it',
				'align'        => 'right',
			)
		);

		$this->assertStringContainsString( 'openqr-embed__qr', $html, 'the new shape uses the shared embed renderer' );
		$this->assertStringContainsString( 'href="https://example.com/b2-dest"', $html );
		$this->assertStringContainsString( 'openqr-embed--align-right', $html );
	}

	public function test_new_current_source_renders_a_builder_asset(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Block page' ) );
		MockHttp::queue_bytes( $this->fake_png() );

		$this->go_to( get_permalink( $post_id ) );
		$html = OpenQR_Blocks::render(
			array(
				'source'       => 'current',
				'presentation' => OpenQR_Embed::QR_CAPTION,
				'instruction'  => 'Scan to open this page',
			)
		);

		$this->assertStringContainsString( '/openqr/builder/', $html );
		$this->assertStringContainsString( 'Scan to open this page', $html );
	}

	public function test_legacy_and_new_both_render_nothing_without_a_connection(): void {
		OpenQR_Settings::disconnect();

		$this->assertSame(
			'',
			OpenQR_Blocks::render(
				array(
					'codeId' => 'code-nope',
					'url'    => 'https://example.com/fresh',
				)
			)
		);
		$this->assertSame(
			'',
			OpenQR_Blocks::render(
				array(
					'source' => 'custom',
					'cfType' => 'url',
					'cfUrl'  => 'https://example.com/fresh',
				)
			)
		);
	}
}

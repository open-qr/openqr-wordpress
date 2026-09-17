<?php
/**
 * The openqr/qr Gutenberg block. DYNAMIC block that ALSO SAVES fallback markup: while the
 * plugin is active the render_callback serves fresh output; after deactivation the saved
 * `<img>` keeps rendering because the asset is a durable file in uploads.
 *
 * Attribute changes after 1.0 must go through block.json `deprecated` — saved markup is a
 * commitment once pages carry it.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block registration + server render.
 */
final class OpenQR_Blocks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
	}

	/**
	 * Register the block from block.json with a PHP render callback.
	 *
	 * @return void
	 */
	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
			return;
		}
		register_block_type_from_metadata(
			OPENQR_DIR . '/src/blocks/qr',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	/**
	 * Render (or produce the fallback for) one QR figure.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$size      = max( 96, min( 1024, (int) ( $attributes['size'] ?? 280 ) ) );
		$align     = in_array( $attributes['align'] ?? '', array( 'left', 'center', 'right', 'wide', 'full' ), true ) ? (string) $attributes['align'] : '';
		$show_link = ! empty( $attributes['showLink'] );
		$label     = (string) ( $attributes['label'] ?? '' );

		$asset_url = '';
		$link_url  = '';
		$alt       = '';

		$code_id = (string) ( $attributes['codeId'] ?? '' );
		if ( '' !== $code_id ) {
			$row = OpenQR_Registry::get_by_code_id( $code_id );
			if ( $row ) {
				$asset_url = OpenQR_Assets::url( $row, OpenQR_Assets::EMBED_SIZE ) ?? '';
				$link_url  = (string) ( $row['short_url'] ?? '' );
				$alt       = '' !== $label ? $label : (string) ( $row['placement_label'] ?? $link_url );
			}
		} elseif ( ! empty( $attributes['url'] ) ) {
			$url       = esc_url_raw( (string) $attributes['url'] );
			$row       = array(
				'code_id'    => 'url-' . substr( md5( $url ), 0, 12 ),
				'kind'       => 'static',
				'payload'    => $url,
				'style_hash' => OpenQR_Assets::style_hash( $url ),
			);
			$asset_url = OpenQR_Assets::ensure( $row ) ?? '';
			$alt       = '' !== $label ? $label : $url;
		}

		if ( '' === $asset_url ) {
			// Cannot produce an asset right now (not connected / API down / not yet rendered).
			return '';
		}

		$classes = trim( 'wp-block-openqr-qr openqr-figure' . ( '' !== $align ? ' align' . $align : '' ) );
		$out     = '<figure class="' . esc_attr( $classes ) . '">';
		$out    .= sprintf(
			'<img class="openqr-qr" src="%s" width="%d" height="%d" alt="%s" decoding="async" />',
			esc_url( $asset_url ),
			$size,
			$size,
			esc_attr( wp_strip_all_tags( $alt ) )
		);
		if ( $show_link && '' !== $link_url ) {
			$out .= '<figcaption class="openqr-caption"><code>' . esc_html( $link_url ) . '</code></figcaption>';
		} elseif ( '' !== $label ) {
			$out .= '<figcaption class="openqr-caption">' . esc_html( $label ) . '</figcaption>';
		}
		$out .= '</figure>';
		return $out;
	}

	/**
	 * Editor bundle + connection state (NEVER the API key).
	 *
	 * @return void
	 */
	public static function editor_assets(): void {
		$asset_file = OPENQR_DIR . '/build/blocks/qr/index.asset.php';
		$deps       = array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-block-editor', 'wp-plugins', 'wp-edit-post' );
		$version    = OPENQR_VERSION;
		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $asset['dependencies'] ?? $deps;
			$version = $asset['version'] ?? $version;
		}
		wp_enqueue_script( 'openqr-blocks', OPENQR_URL . 'build/blocks/qr/index.js', $deps, $version, true );
		wp_enqueue_style( 'openqr-blocks-editor', OPENQR_URL . 'build/blocks/qr/index.css', array( 'wp-components' ), $version );
		$account = OpenQR_Settings::account();
		wp_localize_script(
			'openqr-blocks',
			'openqrEditor',
			array(
				'isConnected' => OpenQR_Settings::is_connected(),
				'email'       => $account['email'] ?? '',
				'pricingUrl'  => OpenQR_Marketing_Link::pricing(),
				'keysUrl'     => OpenQR_Marketing_Link::keys_page(),
				'defaultSize' => 280,
			)
		);
		wp_set_script_translations( 'openqr-blocks', 'openqr' );
	}
}

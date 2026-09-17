<?php
/**
 * [openqr] shortcode. Two forms:
 *
 *   [openqr code="CODE_ID" size="280"]   a saved code (dynamic or static): serves its durable asset
 *   [openqr url="https://…" size="280"]  ad-hoc URL: renders + persists an anonymous asset
 *
 * Both resolve server-side to an uploads URL; a public page load never calls openqr.uk.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode renderer.
 */
final class OpenQR_Shortcode {

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'openqr', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'code'  => '',
				'url'   => '',
				'size'  => 280,
				'align' => '',
				'alt'   => '',
			),
			$atts,
			'openqr'
		);

		$size = max( 96, min( 1024, (int) $atts['size'] ) );

		if ( '' !== (string) $atts['code'] ) {
			return self::render_code( (string) $atts['code'], $size, (string) $atts['align'], (string) $atts['alt'] );
		}
		if ( '' !== (string) $atts['url'] ) {
			return self::render_url( (string) $atts['url'], $size, (string) $atts['align'], (string) $atts['alt'] );
		}
		return '';
	}

	/**
	 * Render a saved code by ID.
	 *
	 * @param string $code_id OpenQR code ID.
	 * @param int    $size    Pixel size.
	 * @param string $align   Alignment.
	 * @param string $alt     Alt text override.
	 * @return string
	 */
	private static function render_code( string $code_id, int $size, string $align, string $alt ): string {
		$row = OpenQR_Registry::get_by_code_id( $code_id );

		// Not in the registry yet but the account knows it: import a lightweight row so the
		// asset renders (share-a-shortcode-to-a-colleague case).
		if ( ! $row && OpenQR_Settings::is_connected() ) {
			$res = OpenQR_Api_Client::get_code( $code_id );
			if ( $res->is_ok() ) {
				$body = $res->body() ?? array();
				$row  = OpenQR_Registry::upsert(
					array(
						'code_id'       => (string) ( $body['id'] ?? $code_id ),
						'kind'          => 'dynamic',
						'slug'          => (string) ( $body['slug'] ?? '' ),
						'short_url'     => (string) ( $body['short_url'] ?? '' ),
						'encoded_url'   => (string) ( $body['destination'] ?? '' ),
						'status_mirror' => (string) ( $body['status'] ?? 'active' ),
					)
				);
			}
		}
		if ( ! $row ) {
			return '';
		}
		$url = OpenQR_Assets::ensure( $row );
		if ( ! $url ) {
			return '';
		}
		$label = (string) ( $row['placement_label'] ?? '' );
		return self::img( $url, $size, $align, '' !== $alt ? $alt : ( '' !== $label ? $label : (string) $row['short_url'] ) );
	}

	/**
	 * Render an ad-hoc URL as a static, content-addressed asset.
	 *
	 * @param string $url   Destination URL.
	 * @param int    $size  Pixel size.
	 * @param string $align Alignment.
	 * @param string $alt   Alt text override.
	 * @return string
	 */
	private static function render_url( string $url, int $size, string $align, string $alt ): string {
		$validated = esc_url_raw( $url );
		if ( '' === $validated || ! preg_match( '#^https?://#i', $validated ) ) {
			return '';
		}
		$style_hash = OpenQR_Assets::style_hash( $validated );
		$code_id    = 'url-' . substr( md5( $validated ), 0, 12 );
		$row        = array(
			'code_id'    => $code_id,
			'kind'       => 'static',
			'payload'    => $validated,
			'style_hash' => $style_hash,
		);
		$asset      = OpenQR_Assets::ensure( $row );
		if ( ! $asset ) {
			return '';
		}
		return self::img( $asset, $size, $align, '' !== $alt ? $alt : $validated );
	}

	/**
	 * The escaped <img> element.
	 *
	 * @param string $src   Asset URL.
	 * @param int    $size  Pixel size.
	 * @param string $align Alignment class.
	 * @param string $alt   Alt text.
	 * @return string
	 */
	private static function img( string $src, int $size, string $align, string $alt ): string {
		$class = 'openqr-qr' . ( in_array( $align, array( 'left', 'right', 'center' ), true ) ? ' openqr-qr--' . $align : '' );
		return sprintf(
			'<img class="%s" src="%s" width="%d" height="%d" alt="%s" loading="lazy" decoding="async" />',
			esc_attr( $class ),
			esc_url( $src ),
			$size,
			$size,
			esc_attr( wp_strip_all_tags( $alt ) )
		);
	}
}

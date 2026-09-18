<?php
/**
 * Durable generated assets. A published QR image is CONTENT, not cache:
 *
 * - Rendered via /v1/qr (PNG) at create/save time, then stored under
 *   uploads/openqr/{code_id}/{style_hash}/qr-{size}.png.
 * - Served straight from uploads by the web server: a public page load touches ZERO PHP and
 *   ZERO openqr.uk. An API outage cannot break a rendered page, ever.
 * - A new style_hash directory is generated when the encoded content or appearance changes;
 *   the old files keep existing, so already-cached HTML keeps working. Destination-only edits
 *   do NOT regenerate (the encoded short URL is unchanged).
 * - Disconnecting the account never deletes assets: printed material keeps working.
 *
 * Two promises, kept distinct in the UI copy: (1) the IMAGE keeps serving from this site;
 * (2) the dynamic REDIRECT depends on openqr.uk. Assets solve only the first.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render + persist + serve durable QR images.
 */
final class OpenQR_Assets {

	/**
	 * Sizes rendered on demand: embed default + print downloads.
	 */
	const SIZES = array( 512, 1024, 2048, 4096 );

	/**
	 * The embed size.
	 */
	const EMBED_SIZE = 1024;

	/**
	 * What the QR image encodes for a registry row: the short URL (dynamic) or payload (static).
	 *
	 * @param array<string, mixed> $row Registry row.
	 * @return string
	 */
	public static function encoded_content( array $row ): string {
		return 'static' === $row['kind'] ? (string) ( $row['payload'] ?? '' ) : (string) ( $row['short_url'] ?? '' );
	}

	/**
	 * Content+appearance hash. Encoded content OR style change => new directory, old files
	 * intact.
	 *
	 * @param string $encoded_content Encoded content.
	 * @param string $dark            Foreground hex.
	 * @param string $light           Background hex.
	 * @param string $theme           Theme name.
	 * @return string
	 */
	public static function style_hash( string $encoded_content, string $dark = '', string $light = '', string $theme = '' ): string {
		return substr( md5( $encoded_content . '|' . $dark . '|' . $light . '|' . $theme ), 0, 32 );
	}

	/**
	 * Absolute directory for one hash version.
	 *
	 * @param string $code_id    OpenQR code ID.
	 * @param string $style_hash Style hash.
	 * @return string
	 */
	private static function dir( string $code_id, string $style_hash ): string {
		$uploads = wp_upload_dir();
		return $uploads['basedir'] . '/openqr/' . sanitize_file_name( $code_id ) . '/' . sanitize_file_name( $style_hash );
	}

	/**
	 * Public URL for one asset file (null when the file does not exist).
	 *
	 * @param array<string, mixed> $row  Registry row.
	 * @param int                  $size Asset size in px.
	 * @return string|null
	 */
	public static function url( array $row, int $size = self::EMBED_SIZE ): ?string {
		if ( empty( $row['style_hash'] ) || empty( $row['code_id'] ) ) {
			return null;
		}
		$uploads = wp_upload_dir();
		$path    = $uploads['basedir'] . '/openqr/' . sanitize_file_name( $row['code_id'] ) . '/' . sanitize_file_name( $row['style_hash'] ) . '/qr-' . $size . '.png';
		if ( ! file_exists( $path ) ) {
			return null;
		}
		return $uploads['baseurl'] . '/openqr/' . rawurlencode( $row['code_id'] ) . '/' . rawurlencode( $row['style_hash'] ) . '/qr-' . $size . '.png';
	}

	/**
	 * Ensure the embed asset exists (render + persist when missing) and return its URL.
	 * Returns null when it cannot be produced RIGHT NOW (API down, no connection). Existing
	 * files are served with no API dependency at all.
	 *
	 * @param array<string, mixed> $row Registry row.
	 * @return string|null Asset URL.
	 */
	public static function ensure( array $row ): ?string {
		$encoded = self::encoded_content( $row );
		if ( '' === $encoded ) {
			return null;
		}

		$existing = self::url( $row, self::EMBED_SIZE );
		if ( $existing ) {
			return $existing;
		}

		if ( ! OpenQR_Settings::is_connected() ) {
			return null;
		}

		$style = self::style_from_row( $row );
		$res   = OpenQR_Api_Client::render(
			$encoded,
			array(
				'format' => 'png',
				'size'   => self::EMBED_SIZE,
				'margin' => $style['margin'],
				'dark'   => $style['dark'],
				'light'  => $style['light'],
				'theme'  => $style['theme'],
			)
		);
		if ( ! $res->is_ok() || false === strpos( $res->content_type(), 'image/' ) ) {
			return null;
		}

		return self::store( $row['code_id'], $row['style_hash'], self::EMBED_SIZE, $res->raw() );
	}

	/**
	 * Style parameters for a registry row: theme by name, or explicit colours. Margin is
	 * always 4: the DENSO four-module quiet zone, never silently less.
	 *
	 * @param array<string, mixed> $row Registry row.
	 * @return array<string, mixed>
	 */
	private static function style_from_row( array $row ): array {
		return array(
			'dark'   => (string) ( $row['style_dark'] ?? '' ),
			'light'  => (string) ( $row['style_light'] ?? '' ),
			'theme'  => (string) ( $row['style_theme'] ?? '' ),
			'margin' => 4,
		);
	}

	/**
	 * Write PNG bytes for one size and return the public URL, null on failure.
	 *
	 * @param string      $code_id    OpenQR code ID.
	 * @param string      $style_hash Style hash.
	 * @param int         $size       Asset size.
	 * @param string|null $bytes      PNG bytes.
	 * @return string|null
	 */
	public static function store( string $code_id, string $style_hash, int $size, ?string $bytes ): ?string {
		if ( ! $bytes ) {
			return null;
		}
		$dir = self::dir( $code_id, $style_hash );
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		$file    = $dir . '/qr-' . $size . '.png';
		$written = file_put_contents( $file, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- generated binary asset, not a template write
		if ( false === $written ) {
			return null;
		}
		self::protect_directory( $dir );
		$uploads = wp_upload_dir();
		return $uploads['baseurl'] . '/openqr/' . rawurlencode( $code_id ) . '/' . rawurlencode( $style_hash ) . '/qr-' . $size . '.png';
	}

	/**
	 * An index.php keeps directory listings off; nothing executable is ever stored here.
	 *
	 * @param string $dir Asset directory.
	 * @return void
	 */
	private static function protect_directory( string $dir ): void {
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- best-effort guard file
			@file_put_contents( $index, "<?php // Silence is golden.\n" );
		}
	}

	/**
	 * Render every download size for a code (admin download action).
	 *
	 * @param array<string, mixed> $row Registry row.
	 * @return array<int|string, string|null> size => URL (null when not produced).
	 */
	public static function ensure_all_sizes( array $row ): array {
		$out     = array();
		$encoded = self::encoded_content( $row );
		if ( '' === $encoded || ! OpenQR_Settings::is_connected() ) {
			return $out;
		}
		$style = self::style_from_row( $row );
		foreach ( self::SIZES as $size ) {
			$url = self::url( $row, $size );
			if ( $url ) {
				$out[ $size ] = $url;
				continue;
			}
			$res          = OpenQR_Api_Client::render(
				$encoded,
				array(
					'format' => 'png',
					'size'   => $size,
					'margin' => $style['margin'],
					'dark'   => $style['dark'],
					'light'  => $style['light'],
					'theme'  => $style['theme'],
				)
			);
			$out[ $size ] = $res->is_ok() ? self::store( $row['code_id'], $row['style_hash'], $size, $res->raw() ) : null;
		}
		return $out;
	}

	/**
	 * Fetch + sanitise the SVG rendering of a row for DOWNLOAD. Served as an attachment by the
	 * admin controller (never linked as a public asset).
	 *
	 * @param array<string, mixed> $row Registry row.
	 * @return string|null Sanitised SVG markup.
	 */
	public static function svg_download( array $row ): ?string {
		$encoded = self::encoded_content( $row );
		if ( '' === $encoded || ! OpenQR_Settings::is_connected() ) {
			return null;
		}
		$style = self::style_from_row( $row );
		$res   = OpenQR_Api_Client::render(
			$encoded,
			array(
				'format' => 'svg',
				'size'   => self::EMBED_SIZE,
				'margin' => $style['margin'],
				'dark'   => $style['dark'],
				'light'  => $style['light'],
				'theme'  => $style['theme'],
			)
		);
		if ( ! $res->is_ok() ) {
			return null;
		}
		return self::sanitise_svg( (string) $res->raw() );
	}

	/**
	 * Allowlist sanitiser for machine-generated QR SVGs: strips script/foreignObject/media
	 * elements, event handlers and external references.
	 *
	 * @param string $svg Raw SVG from the API.
	 * @return string|null
	 */
	public static function sanitise_svg( string $svg ): ?string {
		if ( '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return null;
		}
		// Drop whole dangerous elements.
		$svg = preg_replace( '#<(script|foreignObject|image|use|animate|set)[^>]*>.*?</\1>#is', '', $svg ) ?? '';
		$svg = preg_replace( '#<(script|foreignObject|image|use|animate|set)[^>]*/?>#i', '', $svg ) ?? '';
		// Drop event handlers and non-fragment URLs anywhere they remain.
		$svg = preg_replace( '/\son[a-z]+\s*=\s*"[^"]*"/i', '', $svg ) ?? '';
		$svg = preg_replace( "/\son[a-z]+\s*=\s*'[^']*'/i", '', $svg ) ?? '';
		$svg = preg_replace( '/(href|xlink:href)\s*=\s*"(?!#)[^"]*"/i', '', $svg ) ?? '';
		if ( false !== stripos( $svg, '<script' ) ) {
			return null;
		}
		return $svg;
	}

	/**
	 * Remove every stored asset for a code (explicit delete).
	 *
	 * @param string $code_id OpenQR code ID.
	 * @return void
	 */
	public static function delete_code_assets( string $code_id ): void {
		$uploads = wp_upload_dir();
		$dir     = $uploads['basedir'] . '/openqr/' . sanitize_file_name( $code_id );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- asset cleanup
				@rmdir( $item->getPathname() );
			} else {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.unlink_unlink -- asset cleanup
				@unlink( $item->getPathname() );
			}
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- asset cleanup
		@rmdir( $dir );
	}

	/**
	 * Wipe uploads/openqr entirely (uninstall opt-in).
	 *
	 * @return void
	 */
	public static function wipe_all(): void {
		$uploads = wp_upload_dir();
		$root    = $uploads['basedir'] . '/openqr';
		if ( ! is_dir( $root ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall wipe
				@rmdir( $item->getPathname() );
			} else {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.unlink_unlink -- uninstall wipe
				@unlink( $item->getPathname() );
			}
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall wipe
		@rmdir( $root );
	}
}

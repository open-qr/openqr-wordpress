<?php
/**
 * Shared QR embed renderer for page builders (Elementor widget, Fusion element) — one
 * args-driven implementation so every builder surface renders identically and the
 * load-bearing rules live in exactly one place:
 *
 * - NEVER create a cloud code during rendering. Existing codes are looked up; static
 *   content (current page, custom) is built into a payload string locally and rendered via
 *   /v1/qr, then persisted under uploads/openqr/builder/ like any other durable asset.
 *   Rendering a page can therefore never spend the account's code allowance.
 * - Resolve the ACTUAL displayed content, not the template's URL: on a singular template the
 *   payload is the queried object's permalink, so a reusable property template produces a
 *   different code per listing.
 * - Ordinary button clicks are not scans: the QR encodes the short URL (counted by the
 *   redirect worker) but buttons link straight to the destination. Analytics stay honest.
 * - The image is a durable asset: once rendered it serves from uploads with zero API
 *   dependency, and it keeps serving when disconnected.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builder-agnostic embed renderer.
 */
final class OpenQR_Embed {

	/**
	 * Presentation modes.
	 */
	const QR_ONLY    = 'qr';
	const QR_CAPTION = 'qr_caption';
	const QR_BUTTON  = 'qr_button';

	/**
	 * Map builder-settings keys (flat cf_* controls shared by Elementor and Fusion) to render
	 * args. One mapping for every builder so a field never drifts between surfaces. Elementor
	 * control ids must be unique per widget, so per-type phone/message/email controls carry
	 * distinct ids that unify here.
	 *
	 * @param array<string, mixed> $s Builder settings.
	 * @return array<string, mixed> Render args (without source-specific extras the caller adds).
	 */
	public static function builder_args( array $s ): array {
		$pick = static function ( array $keys ) use ( $s ): string {
			foreach ( $keys as $k ) {
				if ( isset( $s[ $k ] ) && '' !== (string) $s[ $k ] ) {
					return (string) $s[ $k ];
				}
			}
			return '';
		};

		$fields = array(
			'url'        => (string) ( $s['cf_url'] ?? '' ),
			'text'       => (string) ( $s['cf_text'] ?? '' ),
			'email'      => $pick( array( 'cf_email', 'cf_vcard_email' ) ),
			'subject'    => (string) ( $s['cf_subject'] ?? '' ),
			'body'       => (string) ( $s['cf_body'] ?? '' ),
			'phone'      => $pick( array( 'cf_phone', 'cf_sms_phone', 'cf_wa_phone', 'cf_vcard_phone' ) ),
			'message'    => $pick( array( 'cf_sms_message', 'cf_wa_message' ) ),
			'ssid'       => (string) ( $s['cf_ssid'] ?? '' ),
			'password'   => (string) ( $s['cf_password'] ?? '' ),
			'encryption' => (string) ( $s['cf_encryption'] ?? '' ),
			'lat'        => (string) ( $s['cf_lat'] ?? '' ),
			'lng'        => (string) ( $s['cf_lng'] ?? '' ),
			'firstName'  => (string) ( $s['cf_first_name'] ?? '' ),
			'lastName'   => (string) ( $s['cf_last_name'] ?? '' ),
			'org'        => (string) ( $s['cf_org'] ?? '' ),
			'title'      => (string) ( $s['cf_title'] ?? '' ),
			'url2'       => (string) ( $s['cf_website'] ?? '' ),
			'address'    => (string) ( $s['cf_address'] ?? '' ),
		);
		// vCard's own URL field is `url`; the custom-URL source uses the same slot, so rename
		// here rather than in the payload builder.
		if ( isset( $fields['url2'] ) ) {
			if ( isset( $s['cf_type'] ) && 'vcard' === $s['cf_type'] ) {
				$fields['url'] = $fields['url2'];
			}
			unset( $fields['url2'] );
		}
		if ( ! empty( $s['cf_hidden'] ) ) {
			$fields['hidden'] = true;
		}

		return array(
			'source'       => (string) ( $s['source'] ?? 'current' ),
			'code_id'      => (string) ( $s['code_id'] ?? '' ),
			'type'         => (string) ( $s['cf_type'] ?? 'url' ),
			'fields'       => $fields,
			'presentation' => (string) ( $s['presentation'] ?? self::QR_CAPTION ),
			'heading'      => (string) ( $s['heading'] ?? '' ),
			'instruction'  => (string) ( $s['instruction'] ?? '' ),
			// Elementor's SLIDER control saves {size, unit}; plain numbers come from Fusion.
			'size'         => (int) ( is_array( $s['size'] ?? null ) ? ( $s['size']['size'] ?? 220 ) : ( $s['size'] ?? 220 ) ),
			'align'        => (string) ( $s['align'] ?? 'center' ),
			'dark'         => (string) ( $s['qr_dark'] ?? '' ),
			'light'        => (string) ( $s['qr_light'] ?? '' ),
			'button_text'  => (string) ( $s['button_text'] ?? '' ),
			'button_url'   => (string) ( $s['button_url']['url'] ?? $s['button_url'] ?? '' ),
			'download'     => ! empty( $s['download'] ),
		);
	}

	/**
	 * Render the embed. Returns '' when there is nothing honest to render (no code, empty
	 * payload); builders show their own empty-state in the editor.
	 *
	 * @param array<string, mixed> $args See the defaults below.
	 * @return string HTML.
	 */
	public static function render( array $args ): string {
		$args = array_merge(
			array(
				'source'       => 'current',       // code | current | custom.
				'code_id'      => '',              // source=code: registry code_id.
				'type'         => 'url',           // source=custom: payload type.
				'fields'       => array(),         // source=custom: payload fields.
				'presentation' => self::QR_CAPTION,
				'heading'      => '',
				'instruction'  => '',
				'size'         => 220,
				'align'        => 'center',
				'dark'         => '',
				'light'        => '',
				'button_text'  => '',
				'button_url'   => '',
				'download'     => false,
				'editor'       => false,           // builders pass true for inspection hints.
			),
			$args
		);

		$size = max( 96, min( 1024, (int) $args['size'] ) );
		$data = self::resolve( $args );

		if ( null === $data ) {
			return $args['editor'] ? self::editor_placeholder( $args ) : '';
		}

		[$src, $destination, $label] = $data;

		$align = in_array( $args['align'], array( 'left', 'center', 'right' ), true ) ? $args['align'] : 'center';
		$class = 'openqr-embed openqr-embed--align-' . $align;

		$out = '<div class="' . esc_attr( $class ) . '">';

		if ( '' !== (string) $args['heading'] ) {
			$out .= '<p class="openqr-embed__heading">' . esc_html( (string) $args['heading'] ) . '</p>';
		}

		$out .= '<img class="openqr-embed__qr" src="' . esc_url( $src ) . '" width="' . esc_attr( (string) $size ) . '" height="' . esc_attr( (string) $size ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" />';

		$instruction = (string) $args['instruction'];
		if ( self::QR_CAPTION === $args['presentation'] && '' !== $instruction ) {
			$out .= '<p class="openqr-embed__instruction">' . esc_html( $instruction ) . '</p>';
		}

		if ( self::QR_BUTTON === $args['presentation'] ) {
			$button_text = '' !== (string) $args['button_text'] ? (string) $args['button_text'] : ( '' !== $instruction ? $instruction : __( 'Open on your phone', 'openqr' ) );
			$button_url  = '' !== (string) $args['button_url'] ? (string) $args['button_url'] : (string) $destination;
			if ( '' !== $button_url ) {
				$out .= '<p class="openqr-embed__actions"><a class="openqr-embed__button" href="' . esc_url( $button_url ) . '">' . esc_html( $button_text ) . '</a>';
				if ( ! empty( $args['download'] ) && '' !== $src ) {
					$out .= ' <a class="openqr-embed__download" href="' . esc_url( $src ) . '" download>' . esc_html__( 'Download QR', 'openqr' ) . '</a>';
				}
				$out .= '</p>';
			} elseif ( ! empty( $args['download'] ) && '' !== $src ) {
				$out .= '<p class="openqr-embed__actions"><a class="openqr-embed__download" href="' . esc_url( $src ) . '" download>' . esc_html__( 'Download QR', 'openqr' ) . '</a></p>';
			}
		}

		if ( ! empty( $args['editor'] ) && '' !== $destination ) {
			$out .= '<p class="openqr-embed__inspect">' . esc_html__( 'Encodes:', 'openqr' ) . ' <code>' . esc_html( $destination ) . '</code></p>';
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Resolve a source to [image URL, encoded content, label]. Null when there is nothing to
	 * render yet.
	 *
	 * @param array<string, mixed> $args Widget args.
	 * @return array{0: string, 1: string, 2: string}|null
	 */
	private static function resolve( array $args ): ?array {
		if ( 'code' === $args['source'] ) {
			$row = OpenQR_Registry::get_by_code_id( (string) $args['code_id'] );
			if ( ! $row ) {
				return null;
			}
			$src = OpenQR_Assets::ensure( $row );
			if ( null === $src ) {
				return null;
			}
			$label = (string) ( $row['placement_label'] ?? '' );
			return array( $src, (string) ( $row['encoded_url'] ?? '' ), '' !== $label ? $label : __( 'QR code', 'openqr' ) );
		}

		if ( 'current' === $args['source'] ) {
			// The actual displayed content: the queried object's permalink on singular pages
			// (so reusable templates resolve per item), the current path elsewhere.
			$url = '';
			if ( is_singular() ) {
				$url = (string) get_permalink();
			} elseif ( ! empty( $GLOBALS['wp']->request ) ) {
				$url = home_url( '/' . ltrim( (string) $GLOBALS['wp']->request, '/' ) );
			}
			if ( '' === $url ) {
				$url = home_url( '/' );
			}
			return self::static_asset( 'url', array( 'url' => $url ), $args, $url );
		}

		// custom.
		$type    = (string) $args['type'];
		$fields  = is_array( $args['fields'] ) ? $args['fields'] : array();
		$payload = OpenQR_Payload::build( $type, $fields );
		if ( '' === $payload ) {
			return null;
		}
		$label = 'url' === $type ? (string) ( $fields['url'] ?? '' ) : (string) ( $fields['text'] ?? '' );
		return self::static_asset( $type, $fields, $args, $payload, $label );
	}

	/**
	 * Render a static payload to a durable asset (cached by payload + style). Returns
	 * [asset URL, payload, label].
	 *
	 * @param string               $type    Payload type.
	 * @param array<string, mixed> $fields  Payload fields.
	 * @param array<string, mixed> $args    Widget args (style).
	 * @param string               $payload Pre-built payload (current-page URL path).
	 * @param string               $label   Alt label override.
	 * @return array{0: string, 1: string, 2: string}|null
	 */
	private static function static_asset( string $type, array $fields, array $args, string $payload = '', string $label = '' ): ?array {
		$payload = '' !== $payload ? $payload : OpenQR_Payload::build( $type, $fields );
		if ( '' === $payload ) {
			return null;
		}
		$size    = max( 96, min( 1024, (int) $args['size'] ) );
		$dark    = self::hex_or_default( $args['dark'] ?? '', '#232E3A' );
		$light   = self::hex_or_default( $args['light'] ?? '', '#FFFFFF' );
		$hash    = OpenQR_Assets::style_hash( $payload, $dark, $light );
		$uploads = wp_upload_dir();
		$file    = $uploads['basedir'] . '/openqr/builder/' . $hash . '/qr-' . $size . '.png';
		$url     = $uploads['baseurl'] . '/openqr/builder/' . rawurlencode( $hash ) . '/qr-' . $size . '.png';

		if ( ! file_exists( $file ) ) {
			if ( ! OpenQR_Settings::is_connected() ) {
				return null;
			}
			$res = OpenQR_Api_Client::render(
				$payload,
				array(
					'format' => 'png',
					'size'   => $size,
					'margin' => 4,
					'dark'   => $dark,
					'light'  => $light,
				)
			);
			if ( ! $res->is_ok() || false === strpos( $res->content_type(), 'image/' ) ) {
				return null;
			}
			// Reuse the durable-asset store: 'builder' is a fixed, sanitized pseudo-id, so
			// every builder-rendered static lives under uploads/openqr/builder/{hash}/.
			if ( null === OpenQR_Assets::store( 'builder', $hash, $size, $res->raw() ) ) {
				return null;
			}
		}

		return array( $url, $payload, '' !== $label ? $label : __( 'QR code', 'openqr' ) );
	}

	/**
	 * A provided colour must be a hex value; anything else falls back to the default so a
	 * stray value can never produce an unscannable or broken image.
	 *
	 * @param mixed  $value    Provided colour.
	 * @param string $fallback Default.
	 * @return string
	 */
	private static function hex_or_default( $value, string $fallback ): string {
		$v = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? $v : $fallback;
	}

	/**
	 * Editor-only placeholder so a builder's canvas shows WHY nothing renders.
	 *
	 * @param array<string, mixed> $args Widget args.
	 * @return string
	 */
	private static function editor_placeholder( array $args ): string {
		$why = 'code' === $args['source']
			? __( 'Pick an OpenQR code, or connect the account: nothing to render yet.', 'openqr' )
			: __( 'Fill in the content (or connect the account): nothing to render yet.', 'openqr' );
		return '<div class="openqr-embed openqr-embed--placeholder"><p>' . esc_html__( 'OpenQR', 'openqr' ) . ' — ' . esc_html( $why ) . '</p></div>';
	}
}

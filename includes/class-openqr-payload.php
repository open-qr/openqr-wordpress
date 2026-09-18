<?php
/**
 * Static payload builder, mirroring lib/payloads.ts on openqr.uk field for field and escape
 * for escape: the same string a code made on the website would carry. Builders need payloads
 * at RENDER time (current page, custom content) and must never create cloud codes, so the
 * builder path is: payload string here, image via /v1/qr, no account object involved.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deterministic payload strings for the supported static types.
 */
final class OpenQR_Payload {

	/**
	 * Types the builder path supports (the website's list minus the free-form ones that make
	 * no sense in a widget; geo + text included).
	 *
	 * @return array<int, string>
	 */
	public static function types(): array {
		return array( 'url', 'text', 'email', 'phone', 'sms', 'whatsapp', 'wifi', 'geo', 'vcard' );
	}

	/**
	 * Trim like the TS builder: null-safe, coerced, trimmed.
	 *
	 * @param mixed $v Raw value.
	 * @return string
	 */
	private static function s( $v ): string {
		return trim( (string) ( $v ?? '' ) );
	}

	/**
	 * Wi-Fi field escaping: colon is a separator there.
	 *
	 * @param string $v Raw value.
	 * @return string
	 */
	private static function wifi_esc( string $v ): string {
		return preg_replace( '/([\\\\;,:"])/', '\\\\$1', $v );
	}

	/**
	 * Escape vCard 3.0 values: backslash, semicolon, comma and newline only (NOT colon, so
	 * URLs survive).
	 *
	 * @param string $v Raw value.
	 * @return string
	 */
	private static function vesc( string $v ): string {
		$v = preg_replace( '/([\\\\;,])/', '\\\\$1', $v );
		return preg_replace( '/\r?\n/', '\\n', $v );
	}

	/**
	 * Build the payload string for a type, or '' when the required fields are missing/empty
	 * (the caller then renders nothing, exactly like an empty payload on the website).
	 *
	 * @param string               $type   One of self::types().
	 * @param array<string, mixed> $fields Raw field values.
	 * @return string
	 */
	public static function build( string $type, array $fields ): string {
		if ( ! in_array( $type, self::types(), true ) ) {
			return '';
		}

		switch ( $type ) {
			case 'url':
				$v = self::s( $fields['url'] ?? '' );
				if ( '' === $v ) {
					return '';
				}
				return preg_match( '/^[a-z][\w+.-]*:\/\//i', $v ) || 0 === strpos( $v, 'mailto:' ) ? $v : 'https://' . $v;

			case 'text':
				return self::s( $fields['text'] ?? '' );

			case 'email':
				$to = self::s( $fields['email'] ?? '' );
				if ( '' === $to ) {
					return '';
				}
				// RFC 6068 percent-encoding, not form-encoding: in a mailto query a literal
				// '+' means a plus sign, so spaces must be %20.
				$params = array();
				if ( '' !== self::s( $fields['subject'] ?? '' ) ) {
					$params[] = 'subject=' . rawurlencode( self::s( $fields['subject'] ) );
				}
				if ( '' !== self::s( $fields['body'] ?? '' ) ) {
					$params[] = 'body=' . rawurlencode( self::s( $fields['body'] ) );
				}
				return 'mailto:' . $to . ( $params ? '?' . implode( '&', $params ) : '' );

			case 'phone':
				$v = self::s( $fields['phone'] ?? '' );
				return '' !== $v ? 'tel:' . $v : '';

			case 'sms':
				$n = self::s( $fields['phone'] ?? '' );
				if ( '' === $n ) {
					return '';
				}
				$msg = self::s( $fields['message'] ?? '' );
				return '' !== $msg ? 'SMSTO:' . $n . ':' . $msg : 'SMSTO:' . $n;

			case 'whatsapp':
				// The builder strips everything non-digit: '+44 7000 000000' works.
				$n = preg_replace( '/\D/', '', self::s( $fields['phone'] ?? '' ) );
				if ( '' === $n ) {
					return '';
				}
				$text = self::s( $fields['message'] ?? '' );
				return 'https://wa.me/' . $n . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' );

			case 'wifi':
				$ssid = self::s( $fields['ssid'] ?? '' );
				if ( '' === $ssid ) {
					return '';
				}
				$enc   = self::s( $fields['encryption'] ?? '' );
				$enc   = '' !== $enc ? $enc : 'WPA';
				$parts = array( 'T:' . ( 'nopass' === $enc ? 'nopass' : $enc ), 'S:' . self::wifi_esc( $ssid ) );
				if ( 'nopass' !== $enc ) {
					$parts[] = 'P:' . self::wifi_esc( self::s( $fields['password'] ?? '' ) );
				}
				if ( ! empty( $fields['hidden'] ) ) {
					$parts[] = 'H:true';
				}
				return 'WIFI:' . implode( ';', $parts ) . ';;';

			case 'geo':
				$lat = self::s( $fields['lat'] ?? '' );
				$lng = self::s( $fields['lng'] ?? '' );
				return ( '' !== $lat && '' !== $lng ) ? 'geo:' . $lat . ',' . $lng : '';

			case 'vcard':
				$first = self::s( $fields['firstName'] ?? '' );
				$last  = self::s( $fields['lastName'] ?? '' );
				$fn    = trim( $first . ' ' . $last );
				$org   = self::s( $fields['org'] ?? '' );
				$title = self::s( $fields['title'] ?? '' );
				$phone = self::s( $fields['phone'] ?? '' );
				$email = self::s( $fields['email'] ?? '' );
				if ( '' === $fn && '' === $phone && '' === $email ) {
					return '';
				}
				$lines   = array(
					'BEGIN:VCARD',
					'VERSION:3.0',
					'N:' . self::vesc( $last ) . ';' . self::vesc( $first ) . ';;;',
					'FN:' . self::vesc( '' !== $fn ? $fn : ( '' !== $first ? $first : $last ) ),
				);
				$url     = self::s( $fields['url'] ?? '' );
				$address = self::s( $fields['address'] ?? '' );
				if ( '' !== $org ) {
					$lines[] = 'ORG:' . self::vesc( $org );
				}
				if ( '' !== $title ) {
					$lines[] = 'TITLE:' . self::vesc( $title );
				}
				if ( '' !== $phone ) {
					$lines[] = 'TEL;TYPE=CELL:' . self::vesc( $phone );
				}
				if ( '' !== $email ) {
					$lines[] = 'EMAIL;TYPE=INTERNET:' . self::vesc( $email );
				}
				if ( '' !== $url ) {
					$lines[] = 'URL:' . self::vesc( preg_match( '/^[a-z][\w+.-]*:\/\//i', $url ) ? $url : 'https://' . $url );
				}
				if ( '' !== $address ) {
					$lines[] = 'ADR;TYPE=WORK:;;' . self::vesc( $address ) . ';;;;';
				}
				$lines[] = 'END:VCARD';
				return implode( "\n", $lines );
		}

		return '';
	}
}

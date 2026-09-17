<?php
/**
 * Print-reliability checks, computed locally (no API call): quiet-zone, contrast and payload
 * density. These are WARNINGS, never blocks: the user can always choose to proceed.
 *
 * DENSO specifies a four-module clear margin around a QR symbol. The API default is 4; the
 * plugin never silently renders below it.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Local print-safety assessors.
 */
final class OpenQR_Print_Checks {

	/**
	 * Assess a payload + style for print reliability.
	 *
	 * @param string $payload Encoded content.
	 * @param string $dark    Foreground hex.
	 * @param string $light   Background hex.
	 * @param int    $margin  Quiet zone in modules.
	 * @return array<int, array<string, mixed>> Warnings: level, key, message.
	 */
	public static function assess( string $payload, string $dark = '', string $light = '', int $margin = 4 ): array {
		$warnings = array();

		if ( $margin < 4 ) {
			$warnings[] = array(
				'level'   => 'warn',
				'key'     => 'quiet_zone',
				/* translators: %d: module count. */
				'message' => sprintf( __( 'The quiet zone is %d modules. Printers and scanners expect at least 4 clear modules around the code.', 'openqr' ), $margin ),
			);
		}

		if ( '' !== $dark && '' !== $light ) {
			$ratio = self::contrast_ratio( $dark, $light );
			if ( null !== $ratio && $ratio < 2.0 ) {
				$warnings[] = array(
					'level'   => 'strong',
					'key'     => 'contrast',
					/* translators: %s: contrast ratio. */
					'message' => sprintf( __( 'Contrast is %s:1. Most scanners will struggle below 2:1.', 'openqr' ), number_format_i18n( $ratio, 2 ) ),
				);
			} elseif ( null !== $ratio && $ratio < 3.0 ) {
				$warnings[] = array(
					'level'   => 'warn',
					'key'     => 'contrast',
					/* translators: %s: contrast ratio. */
					'message' => sprintf( __( 'Contrast is %s:1. Aim for 3:1 or more for reliable scans.', 'openqr' ), number_format_i18n( $ratio, 2 ) ),
				);
			}
		}

		$bytes = strlen( $payload );
		if ( $bytes > 1000 ) {
			$warnings[] = array(
				'level'   => 'warn',
				'key'     => 'density',
				/* translators: %s: byte count. */
				'message' => sprintf( __( 'This content is dense (%s bytes encoded). Print the code at a larger size and test-scan before printing.', 'openqr' ), number_format_i18n( $bytes ) ),
			);
		} elseif ( $bytes > 500 ) {
			$warnings[] = array(
				'level'   => 'info',
				'key'     => 'density',
				'message' => __( 'This content is fairly dense. Keep the printed code at least 3 cm across and test-scan it.', 'openqr' ),
			);
		}

		return $warnings;
	}

	/**
	 * WCAG relative-luminance contrast ratio, or null when either colour is unparseable.
	 *
	 * @param string $hex_a First hex colour.
	 * @param string $hex_b Second hex colour.
	 * @return float|null
	 */
	public static function contrast_ratio( string $hex_a, string $hex_b ): ?float {
		$la = self::luminance( $hex_a );
		$lb = self::luminance( $hex_b );
		if ( null === $la || null === $lb ) {
			return null;
		}
		$lighter = max( $la, $lb );
		$darker  = min( $la, $lb );
		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Relative luminance of a 3/4/6-digit hex colour, 0..1.
	 *
	 * @param string $hex Hex colour, # optional.
	 * @return float|null
	 */
	public static function luminance( string $hex ): ?float {
		$hex = ltrim( trim( $hex ), '#' );
		if ( 3 === strlen( $hex ) || 4 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return null;
		}
		$channels = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v          = hexdec( substr( $hex, $i, 2 ) ) / 255.0; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_hexdec
			$channels[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}

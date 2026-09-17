<?php
/**
 * Marketing link builder. Every link that points at openqr.uk (docs, pricing, signup) carries
 * acquisition attribution; QR payloads NEVER pass through here (the customer's destination
 * and campaign parameters are theirs, not ours).
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attributed openqr.uk URLs.
 */
final class OpenQR_Marketing_Link {

	/**
	 * Build an attributed openqr.uk URL.
	 *
	 * @param string                $path     Path beginning with /.
	 * @param string                $campaign Short campaign slug, e.g. `plan-limit`.
	 * @param array<string, string> $extra    Extra UTM parameters.
	 * @return string
	 */
	public static function build( string $path, string $campaign = '', array $extra = array() ): string {
		$url  = OpenQR_Settings::site_url() . $path;
		$args = array_merge(
			array(
				'utm_source'   => 'wordpress',
				'utm_medium'   => 'plugin',
				'utm_campaign' => '' !== $campaign ? $campaign : 'plugin',
			),
			$extra
		);
		return add_query_arg( $args, $url );
	}

	/**
	 * Where the "create a key" deep link lands (docs page with the mint button).
	 *
	 * @return string
	 */
	public static function keys_page(): string {
		return self::build( '/api', 'onboarding' );
	}

	/**
	 * Pricing, for plan-limit upsells.
	 *
	 * @return string
	 */
	public static function pricing(): string {
		return self::build( '/pricing', 'plan-limit' );
	}
}

<?php
/**
 * Print-reliability checks: contrast math and warning thresholds.
 *
 * @package OpenQR
 */

class PrintChecksTest extends OpenQR_TestCase {

	public function test_contrast_black_on_white_is_high(): void {
		$this->assertGreaterThan( 20.0, OpenQR_Print_Checks::contrast_ratio( '#000000', '#FFFFFF' ) );
	}

	public function test_contrast_brand_teal_on_white_matches_site_documented_value(): void {
		// The site's own figure: #0a7a79 on white is 5.16:1.
		$this->assertEqualsWithDelta( 5.16, OpenQR_Print_Checks::contrast_ratio( '#0a7a79', '#ffffff' ), 0.05 );
	}

	public function test_contrast_handles_short_hex(): void {
		$this->assertEqualsWithDelta( 21.0, OpenQR_Print_Checks::contrast_ratio( '#000', '#fff' ), 0.1 );
	}

	public function test_contrast_null_for_garbage(): void {
		$this->assertNull( OpenQR_Print_Checks::contrast_ratio( 'nope', '#ffffff' ) );
	}

	public function test_luminance_white_is_one_black_is_zero(): void {
		$this->assertEqualsWithDelta( 1.0, OpenQR_Print_Checks::luminance( '#ffffff' ), 0.001 );
		$this->assertEqualsWithDelta( 0.0, OpenQR_Print_Checks::luminance( '#000000' ), 0.001 );
	}

	public function test_low_contrast_strong_warning(): void {
		// Light grey on white: ratio ~1.6:1, well under 2:1.
		$warnings = OpenQR_Print_Checks::assess( 'https://example.com', '#cccccc', '#ffffff', 4 );
		$keys     = wp_list_pluck( $warnings, 'key' );
		$this->assertContains( 'contrast', $keys );
		$contrast = $warnings[ array_search( 'contrast', $keys, true ) ];
		$this->assertSame( 'strong', $contrast['level'] );
	}

	public function test_borderline_contrast_plain_warning(): void {
		// Ratio just under 3:1 (about 2.1:1).
		$warnings = OpenQR_Print_Checks::assess( 'https://example.com', '#9a9a9a', '#dedede', 4 );
		$keys     = wp_list_pluck( $warnings, 'key' );
		$this->assertContains( 'contrast', $keys );
		$contrast = $warnings[ array_search( 'contrast', $keys, true ) ];
		$this->assertSame( 'warn', $contrast['level'] );
	}

	public function test_good_contrast_no_warning(): void {
		$warnings = OpenQR_Print_Checks::assess( 'https://example.com', '#232e3a', '#ffffff', 4 );
		$this->assertSame( array(), $warnings );
	}

	public function test_thin_quiet_zone_warns(): void {
		$warnings = OpenQR_Print_Checks::assess( 'https://example.com', '#232e3a', '#ffffff', 0 );
		$keys     = wp_list_pluck( $warnings, 'key' );
		$this->assertContains( 'quiet_zone', $keys );
	}

	public function test_dense_payload_warns(): void {
		$payload  = str_repeat( 'x', 1200 );
		$warnings = OpenQR_Print_Checks::assess( $payload, '#232e3a', '#ffffff', 4 );
		$keys     = wp_list_pluck( $warnings, 'key' );
		$this->assertContains( 'density', $keys );
	}
}

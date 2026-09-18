<?php
/**
 * Static payload builder parity with lib/payloads.ts on openqr.uk: the same fields must
 * produce the same payload string on both surfaces, byte for byte.
 *
 * @package OpenQR
 */

class PayloadTest extends OpenQR_TestCase {

	public function test_url_adds_https_and_keeps_absolute(): void {
		$this->assertSame( 'https://example.com', OpenQR_Payload::build( 'url', array( 'url' => 'example.com' ) ) );
		$this->assertSame( 'https://example.com/x', OpenQR_Payload::build( 'url', array( 'url' => 'https://example.com/x' ) ) );
		$this->assertSame( 'http://example.com', OpenQR_Payload::build( 'url', array( 'url' => 'http://example.com' ) ) );
		$this->assertSame( '', OpenQR_Payload::build( 'url', array( 'url' => ' ' ) ) );
	}

	public function test_email_uses_rfc6068_percent_encoding(): void {
		$this->assertSame(
			'mailto:hi@example.com?subject=Two%20seats&body=Line%20one',
			OpenQR_Payload::build(
				'email',
				array(
					'email'   => 'hi@example.com',
					'subject' => 'Two seats',
					'body'    => 'Line one',
				)
			)
		);
		$this->assertSame( 'mailto:hi@example.com', OpenQR_Payload::build( 'email', array( 'email' => 'hi@example.com' ) ) );
		$this->assertSame( '', OpenQR_Payload::build( 'email', array() ) );
	}

	public function test_sms_and_phone(): void {
		$this->assertSame( 'tel:+447000000000', OpenQR_Payload::build( 'phone', array( 'phone' => '+447000000000' ) ) );
		$this->assertSame( 'SMSTO:+44', OpenQR_Payload::build( 'sms', array( 'phone' => '+44' ) ) );
		$this->assertSame(
			'SMSTO:+44:hello there',
			OpenQR_Payload::build(
				'sms',
				array(
					'phone'   => '+44',
					'message' => 'hello there',
				)
			)
		);
		$this->assertSame( '', OpenQR_Payload::build( 'sms', array( 'message' => 'no number' ) ) );
	}

	public function test_whatsapp_keeps_digits_only(): void {
		$this->assertSame( 'https://wa.me/447000000000', OpenQR_Payload::build( 'whatsapp', array( 'phone' => '+44 7000 000000' ) ) );
		$this->assertSame(
			'https://wa.me/447000000000?text=Hi%20there',
			OpenQR_Payload::build(
				'whatsapp',
				array(
					'phone'   => '44 7000 000000',
					'message' => 'Hi there',
				)
			)
		);
	}

	public function test_wifi_escapes_separators_and_honours_nopass(): void {
		$this->assertSame(
			'WIFI:T:WPA;S:cafe;P:latte;;',
			OpenQR_Payload::build(
				'wifi',
				array(
					'ssid'     => 'cafe',
					'password' => 'latte',
				)
			)
		);
		$this->assertSame(
			'WIFI:T:WPA;S:my\\;cafe;P:pa\\:ss;;',
			OpenQR_Payload::build(
				'wifi',
				array(
					'ssid'     => 'my;cafe',
					'password' => 'pa:ss',
				)
			),
			'semicolons and colons are separators: they must arrive escaped'
		);
		$this->assertSame(
			'WIFI:T:nopass;S:open;;',
			OpenQR_Payload::build(
				'wifi',
				array(
					'ssid'       => 'open',
					'encryption' => 'nopass',
				)
			)
		);
		$this->assertSame(
			'WIFI:T:WEP;S:x;P:y;H:true;;',
			OpenQR_Payload::build(
				'wifi',
				array(
					'ssid'       => 'x',
					'password'   => 'y',
					'encryption' => 'WEP',
					'hidden'     => true,
				)
			)
		);
	}

	public function test_geo_and_text(): void {
		$this->assertSame(
			'geo:51.5074,-0.1278',
			OpenQR_Payload::build(
				'geo',
				array(
					'lat' => '51.5074',
					'lng' => '-0.1278',
				)
			)
		);
		$this->assertSame( '', OpenQR_Payload::build( 'geo', array( 'lat' => '1' ) ) );
		$this->assertSame( 'Hello', OpenQR_Payload::build( 'text', array( 'text' => ' Hello ' ) ) );
	}

	public function test_vcard_structure_and_escaping(): void {
		$expected = "BEGIN:VCARD\nVERSION:3.0\nN:Doe;Jane;;;\nFN:Jane Doe\nORG:Acme\\;Ltd\nTITLE:Designer\nTEL;TYPE=CELL:+44 7000 000000\nEMAIL;TYPE=INTERNET:jane@example.com\nURL:https://jane.example.com\nADR;TYPE=WORK:;;123 High St\\, London;;;;\nEND:VCARD";
		$this->assertSame(
			$expected,
			OpenQR_Payload::build(
				'vcard',
				array(
					'firstName' => 'Jane',
					'lastName'  => 'Doe',
					'org'       => 'Acme;Ltd',
					'title'     => 'Designer',
					'phone'     => '+44 7000 000000',
					'email'     => 'jane@example.com',
					'url'       => 'jane.example.com',
					'address'   => '123 High St, London',
				)
			)
		);
		$this->assertSame( '', OpenQR_Payload::build( 'vcard', array( 'org' => 'only a company' ) ), 'name, phone or email is required' );
	}

	public function test_unknown_type_builds_nothing(): void {
		$this->assertSame( '', OpenQR_Payload::build( 'bank-transfer', array() ) );
	}
}

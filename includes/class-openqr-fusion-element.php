<?php
/**
 * Avada / Fusion Builder element. Same controls as the Elementor widget, mapped onto the
 * same OpenQR_Embed renderer. Loaded only when Fusion Builder is present (class
 * FusionBuilder); Avada is not part of the test environment, so this path is
 * syntax-checked and convention-reviewed rather than exercised end to end.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fusion Builder element registration + the render shortcode.
 */
final class OpenQR_Fusion_Element {

	/**
	 * The builder UI element and the shortcode that renders it.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'openqr_fusion', array( __CLASS__, 'render' ) );

		fusion_builder_map(
			array(
				'name'      => __( 'OpenQR', 'openqr' ),
				'shortcode' => 'openqr_fusion',
				'icon'      => 'fusiona-qrcode',
				'params'    => self::params(),
			)
		);
	}

	/**
	 * Builder controls. Mirrors the Elementor widget's set; values map through
	 * OpenQR_Embed::builder_args().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function params(): array {
		$dep_custom = array(
			'element' => 'source',
			'value'   => 'custom',
		);
		$url_dep    = array(
			'element' => 'cf_type',
			'value'   => 'url',
		);

		return array(
			array(
				'type'       => 'select',
				'heading'    => __( 'What should the code carry?', 'openqr' ),
				'param_name' => 'source',
				'default'    => 'current',
				'value'      => array(
					'current' => __( 'This page or product (updates itself)', 'openqr' ),
					'code'    => __( 'An existing OpenQR code', 'openqr' ),
					'custom'  => __( 'Custom content', 'openqr' ),
				),
			),
			array(
				'type'        => 'select',
				'heading'     => __( 'OpenQR code', 'openqr' ),
				'param_name'  => 'code_id',
				'value'       => self::code_options(),
				'dependency'  => array(
					'element' => 'source',
					'value'   => 'code',
				),
				'description' => __( 'Tracked and editable after printing.', 'openqr' ),
			),
			array(
				'type'       => 'select',
				'heading'    => __( 'Type', 'openqr' ),
				'param_name' => 'cf_type',
				'default'    => 'url',
				'value'      => array(
					'url'      => __( 'Website', 'openqr' ),
					'text'     => __( 'Text', 'openqr' ),
					'email'    => __( 'Email', 'openqr' ),
					'phone'    => __( 'Phone', 'openqr' ),
					'sms'      => __( 'SMS', 'openqr' ),
					'whatsapp' => __( 'WhatsApp', 'openqr' ),
					'wifi'     => __( 'Wi-Fi', 'openqr' ),
					'geo'      => __( 'Location', 'openqr' ),
					'vcard'    => __( 'Contact card (vCard)', 'openqr' ),
				),
				'dependency' => $dep_custom,
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'URL', 'openqr' ),
				'param_name' => 'cf_url',
				'dependency' => $url_dep,
			),
			array(
				'type'       => 'textarea',
				'heading'    => __( 'Text', 'openqr' ),
				'param_name' => 'cf_text',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'text',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Email address', 'openqr' ),
				'param_name' => 'cf_email',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'email',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Subject (optional)', 'openqr' ),
				'param_name' => 'cf_subject',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'email',
				),
			),
			array(
				'type'       => 'textarea',
				'heading'    => __( 'Message (optional)', 'openqr' ),
				'param_name' => 'cf_body',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'email',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Phone number', 'openqr' ),
				'param_name' => 'cf_phone',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'phone',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Phone number', 'openqr' ),
				'param_name' => 'cf_sms_phone',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'sms',
				),
			),
			array(
				'type'       => 'textarea',
				'heading'    => __( 'Message (optional)', 'openqr' ),
				'param_name' => 'cf_sms_message',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'sms',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Phone number (with country code)', 'openqr' ),
				'param_name' => 'cf_wa_phone',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'whatsapp',
				),
			),
			array(
				'type'       => 'textarea',
				'heading'    => __( 'Message (optional)', 'openqr' ),
				'param_name' => 'cf_wa_message',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'whatsapp',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Network name (SSID)', 'openqr' ),
				'param_name' => 'cf_ssid',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'wifi',
				),
			),
			array(
				'type'       => 'select',
				'heading'    => __( 'Security', 'openqr' ),
				'param_name' => 'cf_encryption',
				'default'    => 'WPA',
				'value'      => array(
					'WPA'    => __( 'WPA / WPA2 / WPA3', 'openqr' ),
					'WEP'    => __( 'WEP', 'openqr' ),
					'nopass' => __( 'No password', 'openqr' ),
				),
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'wifi',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Password', 'openqr' ),
				'param_name' => 'cf_password',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'wifi',
					'not_in'  => 'nopass',
				),
			),
			array(
				'type'       => 'select',
				'heading'    => __( 'Hidden network', 'openqr' ),
				'param_name' => 'cf_hidden',
				'default'    => '0',
				'value'      => array(
					'1' => __( 'Yes', 'openqr' ),
					'0' => __( 'No', 'openqr' ),
				),
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'wifi',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Latitude', 'openqr' ),
				'param_name' => 'cf_lat',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'geo',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Longitude', 'openqr' ),
				'param_name' => 'cf_lng',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'geo',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'First name', 'openqr' ),
				'param_name' => 'cf_first_name',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Last name', 'openqr' ),
				'param_name' => 'cf_last_name',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Phone number', 'openqr' ),
				'param_name' => 'cf_vcard_phone',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Email', 'openqr' ),
				'param_name' => 'cf_vcard_email',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Company (optional)', 'openqr' ),
				'param_name' => 'cf_org',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Job title (optional)', 'openqr' ),
				'param_name' => 'cf_title',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Website (optional)', 'openqr' ),
				'param_name' => 'cf_website',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Address (optional)', 'openqr' ),
				'param_name' => 'cf_address',
				'dependency' => array(
					'element' => 'cf_type',
					'value'   => 'vcard',
				),
			),
			array(
				'type'       => 'select',
				'heading'    => __( 'Presentation', 'openqr' ),
				'param_name' => 'presentation',
				'default'    => OpenQR_Embed::QR_CAPTION,
				'value'      => array(
					OpenQR_Embed::QR_ONLY    => __( 'QR only', 'openqr' ),
					OpenQR_Embed::QR_CAPTION => __( 'QR with instruction', 'openqr' ),
					OpenQR_Embed::QR_BUTTON  => __( 'QR with button', 'openqr' ),
				),
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Heading', 'openqr' ),
				'param_name' => 'heading',
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Instruction', 'openqr' ),
				'param_name' => 'instruction',
				'default'    => __( 'Scan to open on your phone', 'openqr' ),
				'dependency' => array(
					'element' => 'presentation',
					'not_in'  => OpenQR_Embed::QR_ONLY,
				),
			),
			array(
				'type'       => 'select',
				'heading'    => __( 'Alignment', 'openqr' ),
				'param_name' => 'align',
				'default'    => 'center',
				'value'      => array(
					'left'   => __( 'Left', 'openqr' ),
					'center' => __( 'Center', 'openqr' ),
					'right'  => __( 'Right', 'openqr' ),
				),
			),
			array(
				'type'        => 'textfield',
				'heading'     => __( 'QR size (px)', 'openqr' ),
				'param_name'  => 'size',
				'default'     => '220',
				'description' => __( '96 to 1024.', 'openqr' ),
			),
			array(
				'type'       => 'colorpickertd',
				'heading'    => __( 'Code colour', 'openqr' ),
				'param_name' => 'qr_dark',
			),
			array(
				'type'       => 'colorpickertd',
				'heading'    => __( 'Background colour', 'openqr' ),
				'param_name' => 'qr_light',
			),
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Button text', 'openqr' ),
				'param_name' => 'button_text',
				'default'    => __( 'Open on your phone', 'openqr' ),
				'dependency' => array(
					'element' => 'presentation',
					'value'   => OpenQR_Embed::QR_BUTTON,
				),
			),
			array(
				'type'        => 'textfield',
				'heading'     => __( 'Button link (optional)', 'openqr' ),
				'param_name'  => 'button_url',
				'description' => __( 'Empty: the button opens the code\'s destination.', 'openqr' ),
				'dependency'  => array(
					'element' => 'presentation',
					'value'   => OpenQR_Embed::QR_BUTTON,
				),
			),
			array(
				'type'       => 'select',
				'heading'    => __( 'Add a Download QR link', 'openqr' ),
				'param_name' => 'download',
				'default'    => '0',
				'value'      => array(
					'1' => __( 'Yes', 'openqr' ),
					'0' => __( 'No', 'openqr' ),
				),
				'dependency' => array(
					'element' => 'presentation',
					'value'   => OpenQR_Embed::QR_BUTTON,
				),
			),
		);
	}

	/**
	 * Code options for the picker (site registry).
	 *
	 * @return array<string, string>
	 */
	private static function code_options(): array {
		$options = array( '' => __( '- choose a code -', 'openqr' ) );
		foreach ( OpenQR_Registry::page( 100, 0 ) as $row ) {
			$label                               = (string) ( $row['placement_label'] ?? '' );
			$options[ (string) $row['code_id'] ] = ( '' !== $label ? $label : (string) $row['code_id'] );
		}
		return $options;
	}

	/**
	 * The render shortcode: straight through the shared embed implementation.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ): string {
		$atts = (array) $atts;
		$args = OpenQR_Embed::builder_args( $atts );
		return OpenQR_Embed::render( $args );
	}
}

<?php
/**
 * Elementor widget (Elementor Free compatible: only core widgets API). The controls collect
 * values; every behaviour lives in OpenQR_Embed, so the widget, the Fusion element and any
 * future builder surface share one implementation.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * The OpenQR Elementor widget.
 */
final class OpenQR_Elementor_Widget extends Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'openqr';
	}

	/**
	 * Display title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'OpenQR', 'openqr' );
	}

	/**
	 * Elementor icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-qrcode';
	}

	/**
	 * Categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'openqr' );
	}

	/**
	 * Editor search keywords.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'qr', 'qr code', 'qrcode', 'openqr', 'print' );
	}

	/**
	 * Content controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->register_source_controls();
		$this->register_layout_controls();
		$this->register_style_controls();
		$this->register_button_controls();
	}

	/**
	 * Content source + its conditional fields.
	 *
	 * @return void
	 */
	private function register_source_controls(): void {
		$this->start_controls_section(
			'section_source',
			array( 'label' => __( 'Content', 'openqr' ) )
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'What should the code carry?', 'openqr' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current' => __( 'This page or product (updates itself)', 'openqr' ),
					'code'    => __( 'An existing OpenQR code', 'openqr' ),
					'custom'  => __( 'Custom content', 'openqr' ),
				),
			)
		);

		$codes = array( '' => __( '- choose a code -', 'openqr' ) );
		foreach ( OpenQR_Registry::page( 100, 0 ) as $row ) {
			$label                             = (string) ( $row['placement_label'] ?? '' );
			$codes[ (string) $row['code_id'] ] = ( '' !== $label ? $label : (string) $row['code_id'] );
		}
		$this->add_control(
			'code_id',
			array(
				'label'       => __( 'OpenQR code', 'openqr' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $codes,
				'condition'   => array( 'source' => 'code' ),
				'description' => __( 'Tracked and editable after printing. Codes created after you opened this editor appear on reload.', 'openqr' ),
			)
		);

		$this->add_control(
			'cf_type',
			array(
				'label'     => __( 'Type', 'openqr' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'url',
				'options'   => array(
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
				'condition' => array( 'source' => 'custom' ),
			)
		);

		$this->custom_field_controls();

		$this->end_controls_section();
	}

	/**
	 * One control per payload field, shown conditionally for the chosen type. Values are
	 * mapped to payload field names in OpenQR_Embed::builder_args().
	 *
	 * @return void
	 */
	private function custom_field_controls(): void {
		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'url',
			),
		);
		$this->add_control(
			'cf_url',
			$t + array(
				'label'       => __( 'URL', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'example.com',
				/* translators: leave the leading space; it separates from the control label. */
				'description' => __( ' The https:// is added for you if you leave it off.', 'openqr' ),
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'text',
			),
		);
		$this->add_control(
			'cf_text',
			$t + array(
				'label'       => __( 'Text', 'openqr' ),
				'type'        => Controls_Manager::TEXTAREA,
				'placeholder' => __( 'Shorter scans better: the more data, the denser the code.', 'openqr' ),
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'email',
			),
		);
		$this->add_control(
			'cf_email',
			$t + array(
				'label'       => __( 'Email address', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'hello@example.com',
			)
		);
		$this->add_control(
			'cf_subject',
			$t + array(
				'label' => __( 'Subject (optional)', 'openqr' ),
				'type'  => Controls_Manager::TEXT,
			)
		);
		$this->add_control(
			'cf_body',
			$t + array(
				'label' => __( 'Message (optional)', 'openqr' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'phone',
			),
		);
		$this->add_control(
			'cf_phone',
			$t + array(
				'label'       => __( 'Phone number', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '+44 7000 000000',
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'sms',
			),
		);
		$this->add_control(
			'cf_sms_phone',
			$t + array(
				'label'       => __( 'Phone number', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '+44 7000 000000',
			)
		);
		$this->add_control(
			'cf_sms_message',
			$t + array(
				'label' => __( 'Message (optional)', 'openqr' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'whatsapp',
			),
		);
		$this->add_control(
			'cf_wa_phone',
			$t + array(
				'label'       => __( 'Phone number (with country code)', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '+44 7000 000000',
				'description' => __( ' Spaces and the + are fine: the code keeps only the digits.', 'openqr' ),
			)
		);
		$this->add_control(
			'cf_wa_message',
			$t + array(
				'label' => __( 'Message (optional)', 'openqr' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'wifi',
			),
		);
		$this->add_control(
			'cf_ssid',
			$t + array(
				'label'       => __( 'Network name (SSID)', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'My Wi-Fi',
			)
		);
		$this->add_control(
			'cf_encryption',
			$t + array(
				'label'   => __( 'Security', 'openqr' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'WPA',
				'options' => array(
					'WPA'    => __( 'WPA / WPA2 / WPA3', 'openqr' ),
					'WEP'    => __( 'WEP', 'openqr' ),
					'nopass' => __( 'No password', 'openqr' ),
				),
			)
		);
		$this->add_control(
			'cf_password',
			$t + array(
				'label'     => __( 'Password', 'openqr' ),
				'type'      => Controls_Manager::TEXT,
				'condition' => array(
					'source'         => 'custom',
					'cf_type'        => 'wifi',
					'cf_encryption!' => 'nopass',
				),
			)
		);
		$this->add_control(
			'cf_hidden',
			$t + array(
				'label'        => __( 'Hidden network', 'openqr' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'geo',
			),
		);
		$this->add_control(
			'cf_lat',
			$t + array(
				'label'       => __( 'Latitude', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '51.5074',
			)
		);
		$this->add_control(
			'cf_lng',
			$t + array(
				'label'       => __( 'Longitude', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '-0.1278',
			)
		);

		$t = array(
			'condition' => array(
				'source'  => 'custom',
				'cf_type' => 'vcard',
			),
		);
		$this->add_control(
			'cf_first_name',
			$t + array(
				'label'       => __( 'First name', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Jane',
			)
		);
		$this->add_control(
			'cf_last_name',
			$t + array(
				'label'       => __( 'Last name', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Doe',
			)
		);
		$this->add_control(
			'cf_vcard_phone',
			$t + array(
				'label'       => __( 'Phone number', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '+44 7000 000000',
			)
		);
		$this->add_control(
			'cf_vcard_email',
			$t + array(
				'label'       => __( 'Email', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'jane@example.com',
			)
		);
		$this->add_control(
			'cf_org',
			$t + array(
				'label'       => __( 'Company (optional)', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Acme Ltd',
			)
		);
		$this->add_control(
			'cf_title',
			$t + array(
				'label'       => __( 'Job title (optional)', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Designer',
			)
		);
		$this->add_control(
			'cf_website',
			$t + array(
				'label'       => __( 'Website (optional)', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'example.com',
			)
		);
		$this->add_control(
			'cf_address',
			$t + array(
				'label'       => __( 'Address (optional)', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '123 High St, London',
			)
		);
	}

	/**
	 * Presentation + layout. Typography, spacing and responsive visibility stay in Elementor's
	 * Advanced tab: no second design system.
	 *
	 * @return void
	 */
	private function register_layout_controls(): void {
		$this->start_controls_section(
			'section_layout',
			array( 'label' => __( 'Layout', 'openqr' ) )
		);

		$this->add_control(
			'presentation',
			array(
				'label'   => __( 'Presentation', 'openqr' ),
				'type'    => Controls_Manager::SELECT,
				'default' => OpenQR_Embed::QR_CAPTION,
				'options' => array(
					OpenQR_Embed::QR_ONLY    => __( 'QR only', 'openqr' ),
					OpenQR_Embed::QR_CAPTION => __( 'QR with instruction', 'openqr' ),
					OpenQR_Embed::QR_BUTTON  => __( 'QR with button', 'openqr' ),
				),
			)
		);

		$this->add_control(
			'heading',
			array(
				'label'       => __( 'Heading', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. Take this property with you', 'openqr' ),
			)
		);

		$this->add_control(
			'instruction',
			array(
				'label'       => __( 'Instruction', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Scan to open on your phone', 'openqr' ),
				'description' => __( 'Shown under the QR (instruction presentation) and used as the button label when no button text is set.', 'openqr' ),
				'condition'   => array( 'presentation!' => OpenQR_Embed::QR_ONLY ),
			)
		);

		$this->add_control(
			'align',
			array(
				'label'   => __( 'Alignment', 'openqr' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'center',
				'options' => array(
					'left'   => array(
						'title' => __( 'Left', 'openqr' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'openqr' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'openqr' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
			)
		);

		$this->add_control(
			'size',
			array(
				'label'      => __( 'QR size (px)', 'openqr' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 96,
						'max'  => 1024,
						'step' => 8,
					),
				),
				'default'    => array(
					'size' => 220,
					'unit' => 'px',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * QR appearance. Empty = the OpenQR default; colours are validated at render so a bad
	 * value can never produce an unscannable code.
	 *
	 * @return void
	 */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'section_qr_style',
			array( 'label' => __( 'QR appearance', 'openqr' ) )
		);

		$this->add_control(
			'qr_dark',
			array(
				'label'       => __( 'Code colour', 'openqr' ),
				'type'        => Controls_Manager::COLOR,
				/* translators: leave the leading space; it separates from the control label. */
				'description' => __( ' Dark, high-contrast colours scan most reliably.', 'openqr' ),
			)
		);

		$this->add_control(
			'qr_light',
			array(
				'label' => __( 'Background colour', 'openqr' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * The accessible alternative: a normal link or button alongside the QR.
	 *
	 * @return void
	 */
	private function register_button_controls(): void {
		$this->start_controls_section(
			'section_button',
			array(
				'label'     => __( 'Button', 'openqr' ),
				'condition' => array( 'presentation' => OpenQR_Embed::QR_BUTTON ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Button text', 'openqr' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Open on your phone', 'openqr' ),
				'description' => __( 'Mobile visitors should not have to scan their own screen: always offer the link.', 'openqr' ),
			)
		);

		$this->add_control(
			'button_url',
			array(
				'label'       => __( 'Button link (optional)', 'openqr' ),
				'type'        => Controls_Manager::URL,
				'description' => __( 'Empty: the button opens the code\'s destination.', 'openqr' ),
			)
		);

		$this->add_control(
			'download',
			array(
				'label'        => __( 'Add a Download QR link', 'openqr' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render through the shared embed implementation. The builder canvas also shows what the
	 * code encodes, so the wrong code is caught before publishing.
	 *
	 * @return void
	 */
	protected function render(): void {
		$args           = OpenQR_Embed::builder_args( $this->get_settings_for_display() );
		$args['editor'] = \Elementor\Plugin::$instance->editor->is_edit_mode();
		echo OpenQR_Embed::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped inside
	}
}

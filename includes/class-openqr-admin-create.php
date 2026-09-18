<?php
/**
 * Create QR screen. Two tabs: Dynamic (a URL that stays editable after printing) and Static
 * (nine payload types; the API builds the payload, the plugin only renders fields).
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create screen.
 */
final class OpenQR_Admin_Create {

	/**
	 * Hook registration placeholder (handlers are hooked from OpenQR_Admin).
	 *
	 * @return void
	 */
	public static function register(): void {}

	/**
	 * Per-type field definitions. Mirrors the website's generator field for field
	 * (components/generator/payload-fields.tsx on openqr.uk, built by lib/payloads.ts):
	 * the same labels, placeholders and Wi-Fi options, so muscle memory carries over.
	 *
	 * Field shape: label, input (text|url|tel|email|textarea|select|checkbox), required,
	 * placeholder, help, options (select), show_when (hide this field unless the named
	 * sibling select holds the given value).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function static_types(): array {
		return array(
			'url'      => array(
				'label'  => __( 'Website', 'openqr' ),
				'fields' => array(
					'url' => array(
						'label'       => __( 'URL', 'openqr' ),
						'input'       => 'url',
						'required'    => true,
						'placeholder' => 'example.com',
						'help'        => __( 'The https:// is added for you if you leave it off.', 'openqr' ),
					),
				),
			),
			'text'     => array(
				'label'  => __( 'Text', 'openqr' ),
				'fields' => array(
					'text' => array(
						'label'       => __( 'Text', 'openqr' ),
						'input'       => 'text',
						'required'    => true,
						'placeholder' => __( 'Shorter scans better: the more data, the denser the code.', 'openqr' ),
					),
				),
			),
			'email'    => array(
				'label'  => __( 'Email', 'openqr' ),
				'fields' => array(
					'email'   => array(
						'label'       => __( 'Email address', 'openqr' ),
						'input'       => 'email',
						'required'    => true,
						'placeholder' => 'hello@example.com',
					),
					'subject' => array(
						'label'       => __( 'Subject (optional)', 'openqr' ),
						'input'       => 'text',
						'required'    => false,
						'placeholder' => __( 'e.g. Table booking', 'openqr' ),
					),
					'body'    => array(
						'label'       => __( 'Message (optional)', 'openqr' ),
						'input'       => 'textarea',
						'required'    => false,
						'placeholder' => __( 'Pre-filled when someone scans', 'openqr' ),
					),
				),
			),
			'phone'    => array(
				'label'  => __( 'Phone', 'openqr' ),
				'fields' => array(
					'phone' => array(
						'label'       => __( 'Phone number', 'openqr' ),
						'input'       => 'tel',
						'required'    => true,
						'placeholder' => '+44 7000 000000',
					),
				),
			),
			'sms'      => array(
				'label'  => __( 'SMS', 'openqr' ),
				'fields' => array(
					'phone'   => array(
						'label'       => __( 'Phone number', 'openqr' ),
						'input'       => 'tel',
						'required'    => true,
						'placeholder' => '+44 7000 000000',
					),
					'message' => array(
						'label'       => __( 'Message (optional)', 'openqr' ),
						'input'       => 'textarea',
						'required'    => false,
						'placeholder' => __( 'Pre-filled when someone scans', 'openqr' ),
					),
				),
			),
			'whatsapp' => array(
				'label'  => __( 'WhatsApp', 'openqr' ),
				'fields' => array(
					'phone'   => array(
						'label'       => __( 'Phone number (with country code)', 'openqr' ),
						'input'       => 'tel',
						'required'    => true,
						'placeholder' => '+44 7000 000000',
						'help'        => __( 'Spaces and the + are fine: the code keeps only the digits.', 'openqr' ),
					),
					'message' => array(
						'label'       => __( 'Message (optional)', 'openqr' ),
						'input'       => 'textarea',
						'required'    => false,
						'placeholder' => __( 'Pre-filled when someone scans', 'openqr' ),
					),
				),
			),
			'wifi'     => array(
				'label'  => __( 'Wi-Fi', 'openqr' ),
				'fields' => array(
					'ssid'       => array(
						'label'       => __( 'Network name (SSID)', 'openqr' ),
						'input'       => 'text',
						'required'    => true,
						'placeholder' => 'My Wi-Fi',
					),
					'encryption' => array(
						'label'    => __( 'Security', 'openqr' ),
						'input'    => 'select',
						'required' => false,
						'options'  => array(
							'WPA'    => __( 'WPA / WPA2 / WPA3', 'openqr' ),
							'WEP'    => __( 'WEP', 'openqr' ),
							'nopass' => __( 'No password', 'openqr' ),
						),
					),
					'password'   => array(
						'label'       => __( 'Password', 'openqr' ),
						'input'       => 'text',
						'required'    => false,
						'placeholder' => '••••••••',
						'show_when'   => array( 'encryption', 'nopass' ),
					),
					'hidden'     => array(
						'label'    => __( 'Hidden network', 'openqr' ),
						'input'    => 'checkbox',
						'required' => false,
					),
				),
			),
			'geo'      => array(
				'label'  => __( 'Location', 'openqr' ),
				'fields' => array(
					'lat' => array(
						'label'       => __( 'Latitude', 'openqr' ),
						'input'       => 'text',
						'required'    => true,
						'placeholder' => '51.5074',
						'help'        => __( 'In Google Maps, right-click the spot and copy the coordinates.', 'openqr' ),
					),
					'lng' => array(
						'label'       => __( 'Longitude', 'openqr' ),
						'input'       => 'text',
						'required'    => true,
						'placeholder' => '-0.1278',
					),
				),
			),
			'vcard'    => array(
				'label'  => __( 'Contact card', 'openqr' ),
				'fields' => array(
					'firstName' => array(
						'label'       => __( 'First name', 'openqr' ),
						'input'       => 'text',
						'required'    => false,
						'placeholder' => 'Jane',
						'half'        => true,
					),
					'lastName'  => array(
						'label'       => __( 'Last name', 'openqr' ),
						'input'       => 'text',
						'required'    => false,
						'placeholder' => 'Doe',
						'half'        => true,
					),
					'phone'     => array(
						'label'       => __( 'Phone number', 'openqr' ),
						'input'       => 'tel',
						'required'    => false,
						'placeholder' => '+44 7000 000000',
					),
					'email'     => array(
						'label'       => __( 'Email', 'openqr' ),
						'input'       => 'email',
						'required'    => false,
						'placeholder' => 'jane@example.com',
					),
					'org'       => array(
						'label'       => __( 'Company (optional)', 'openqr' ),
						'input'       => 'text',
						'required'    => false,
						'placeholder' => 'Acme Ltd',
						'half'        => true,
					),
					'title'     => array(
						'label'       => __( 'Job title (optional)', 'openqr' ),
						'input'       => 'text',
						'required'    => false,
						'placeholder' => 'Designer',
						'half'        => true,
					),
					'url'       => array(
						'label'       => __( 'Website (optional)', 'openqr' ),
						'input'       => 'url',
						'required'    => false,
						'placeholder' => 'example.com',
					),
					'address'   => array(
						'label'       => __( 'Address (optional)', 'openqr' ),
						'input'       => 'text',
						'required'    => false,
						'placeholder' => '123 High St, London',
					),
				),
			),
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'openqr_manage_codes' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage OpenQR codes.', 'openqr' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- prefill only
		$prefill_url  = isset( $_GET['destination'] ) ? esc_url_raw( wp_unslash( (string) $_GET['destination'] ) ) : '';
		$prefill_post = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$error        = isset( $_GET['openqr_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['openqr_error'] ) ) : '';
		// phpcs:enable
		$types = self::static_types();
		?>
		<div class="wrap openqr-wrap" id="openqr-create-app"
			data-connected="<?php echo esc_attr( OpenQR_Settings::is_connected() ? '1' : '0' ); ?>"
			data-post-id="<?php echo esc_attr( (string) $prefill_post ); ?>"
			data-prefill-url="<?php echo esc_attr( $prefill_url ); ?>"
			data-keys-url="<?php echo esc_attr( OpenQR_Marketing_Link::keys_page() ); ?>">
			<?php
			OpenQR_Admin::hero(
				__( 'Create QR', 'openqr' ),
				__( 'Make a code, download it, print it. Editable codes can be re-pointed after printing; fixed content cannot.', 'openqr' ),
				array(
					array(
						'url'   => OpenQR_Admin::codes_url(),
						'label' => __( 'Your codes', 'openqr' ),
					),
				)
			);
			?>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! OpenQR_Settings::is_connected() ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'Connect your OpenQR account first.', 'openqr' ); ?>
					<a href="<?php echo esc_url( OpenQR_Admin::settings_url() ); ?>"><?php esc_html_e( 'Go to Settings', 'openqr' ); ?></a> ·
					<a href="<?php echo esc_url( OpenQR_Marketing_Link::keys_page() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Create a free account + key', 'openqr' ); ?></a>
				</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper openqr-tabs">
				<a href="#dynamic" class="nav-tab nav-tab-active" data-tab="dynamic"><?php esc_html_e( 'Editable code (recommended)', 'openqr' ); ?></a>
				<a href="#static" class="nav-tab" data-tab="static"><?php esc_html_e( 'Fixed content', 'openqr' ); ?></a>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="openqr-card openqr-tab-panel" id="openqr-tab-dynamic" data-form="dynamic">
				<input type="hidden" name="action" value="openqr_create_dynamic" />
				<?php wp_nonce_field( 'openqr_create_dynamic' ); ?>
				<p class="openqr-tab-lede"><?php esc_html_e( 'A dynamic code carries a short link you can re-point later: print it once, change the destination whenever you like. Scans are counted.', 'openqr' ); ?></p>
				<label class="openqr-field">
					<span><?php esc_html_e( 'Destination URL', 'openqr' ); ?></span>
					<input type="url" name="destination" required class="regular-text" placeholder="https://example.com/spring-offer" value="<?php echo esc_attr( $prefill_url ); ?>" />
					<p class="description"><?php esc_html_e( 'Where people land when they scan. Change it any time after printing, without reprinting.', 'openqr' ); ?></p>
				</label>
				<label class="openqr-field">
					<span><?php esc_html_e( 'Name this placement', 'openqr' ); ?></span>
					<input type="text" name="label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Shop window poster, Feb flyer', 'openqr' ); ?>" />
					<p class="description"><?php esc_html_e( 'A name you will recognise later, once it is printed.', 'openqr' ); ?></p>
				</label>
				<label class="openqr-field">
					<span><?php esc_html_e( 'Link to content (optional)', 'openqr' ); ?></span>
					<?php
					echo wp_dropdown_pages( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper output
						array(
							'name'             => 'post_id',
							'show_option_none' => __( '- none -', 'openqr' ),
							'echo'             => 0,
							'selected'         => (string) $prefill_post,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Links this code to a page so you can find it again from that page.', 'openqr' ); ?></p>
				</label>
				<p><button type="submit" class="button button-primary button-hero"<?php disabled( ! OpenQR_Settings::is_connected() ); ?>><?php esc_html_e( 'Create QR code', 'openqr' ); ?></button></p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="openqr-card openqr-tab-panel" id="openqr-tab-static" data-form="static" hidden>
				<input type="hidden" name="action" value="openqr_create_static" />
				<?php wp_nonce_field( 'openqr_create_static' ); ?>
				<p class="openqr-tab-lede"><?php esc_html_e( 'Fixed content baked into the image: a Wi-Fi card, a contact card, a plain link. No short link, no scan counts, and the content cannot change after printing.', 'openqr' ); ?></p>
				<label class="openqr-field openqr-field--narrow">
					<span><?php esc_html_e( 'Type', 'openqr' ); ?></span>
					<select name="type" id="openqr-static-type">
						<?php foreach ( $types as $id => $def ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) $def['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<div class="openqr-static-fields">
					<?php foreach ( $types as $id => $def ) : ?>
						<div class="openqr-type-fields" data-type="<?php echo esc_attr( $id ); ?>" hidden>
							<?php self::render_type_fields( $id, $def['fields'] ); ?>
						</div>
					<?php endforeach; ?>
				</div>
				<label class="openqr-field">
					<span><?php esc_html_e( 'Name this placement', 'openqr' ); ?></span>
					<input type="text" name="label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Guest wifi card', 'openqr' ); ?>" />
				</label>
				<p><button type="submit" class="button button-primary button-hero"<?php disabled( ! OpenQR_Settings::is_connected() ); ?>><?php esc_html_e( 'Create QR code', 'openqr' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * One static type's field controls, from the shared definition. Output only: the API
	 * builds the payload from these fields, exactly as the website generator does.
	 *
	 * @param string                              $type_id Type slug.
	 * @param array<string, array<string, mixed>> $fields  Field definitions.
	 * @return void
	 */
	private static function render_type_fields( string $type_id, array $fields ): void {
		foreach ( $fields as $name => $meta ) {
			$input       = (string) ( $meta['input'] ?? 'text' );
			$required    = ! empty( $meta['required'] );
			$placeholder = (string) ( $meta['placeholder'] ?? '' );
			$help        = (string) ( $meta['help'] ?? '' );
			$half        = ! empty( $meta['half'] );
			$dom_id      = 'openqr-f-' . $type_id . '-' . $name;
			$name_attr   = 'fields[' . esc_attr( $type_id ) . '][' . esc_attr( $name ) . ']';
			$class       = 'openqr-field' . ( $half ? ' openqr-field--half' : '' );

			if ( isset( $meta['show_when'] ) ) {
				$class .= ' openqr-field--conditional';
				printf(
					'<div class="%s" data-shows-when="%s" data-shows-not="%s">',
					esc_attr( $class ),
					esc_attr( (string) $meta['show_when'][0] ),
					esc_attr( (string) $meta['show_when'][1] )
				);
			} else {
				printf( '<div class="%s">', esc_attr( $class ) );
			}

			if ( 'checkbox' === $input ) {
				printf(
					'<input type="checkbox" value="1" id="%s" name="%s" /><label for="%s">%s</label>',
					esc_attr( $dom_id ),
					$name_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr parts above
					esc_attr( $dom_id ),
					esc_html( (string) $meta['label'] )
				);
			} else {
				echo '<label for="' . esc_attr( $dom_id ) . '"><span>' . esc_html( (string) $meta['label'] ) . '</span>';
				if ( 'select' === $input ) {
					printf( '<select id="%s" name="%s">', esc_attr( $dom_id ), $name_attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr parts above
					$first = true;
					foreach ( (array) ( $meta['options'] ?? array() ) as $value => $option_label ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( (string) $value ),
							$first ? ' selected' : '',
							esc_html( (string) $option_label )
						);
						$first = false;
					}
					echo '</select>';
				} elseif ( 'textarea' === $input ) {
					printf(
						'<textarea id="%s" name="%s" rows="2" class="regular-text" placeholder="%s"></textarea>',
						esc_attr( $dom_id ),
						$name_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr parts above
						esc_attr( $placeholder )
					);
				} else {
					printf(
						'<input type="%s" id="%s" name="%s" class="regular-text" placeholder="%s"%s />',
						esc_attr( $input ),
						esc_attr( $dom_id ),
						$name_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr parts above
						esc_attr( $placeholder ),
						$required ? ' data-required="1"' : ''
					);
				}
				echo '</label>';
			}

			if ( '' !== $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Allowlist pass from raw form input to API field values: only fields the type defines
	 * are forwarded, under their exact API names (the API's field keys are case-sensitive,
	 * e.g. firstName). Message bodies keep their newlines; single-line fields do not.
	 *
	 * @param string               $type Type slug.
	 * @param array<string, mixed> $raw  Raw submitted values for this type.
	 * @return array<string, string>
	 */
	public static function collect_static_fields( string $type, array $raw ): array {
		$defined = self::static_types()[ $type ]['fields'] ?? array();
		$fields  = array();
		foreach ( $defined as $key => $meta ) {
			if ( ! isset( $raw[ $key ] ) || '' === (string) $raw[ $key ] ) {
				continue;
			}
			$fields[ $key ] = 'textarea' === ( $meta['input'] ?? '' )
				? sanitize_textarea_field( (string) $raw[ $key ] )
				: sanitize_text_field( (string) $raw[ $key ] );
		}
		return $fields;
	}

	/**
	 * Admin_post handler: create dynamic from the screen.
	 *
	 * @return void
	 */
	public static function handle_create_dynamic(): void {
		if ( ! check_admin_referer( 'openqr_create_dynamic' ) || ! current_user_can( 'openqr_manage_codes' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'openqr' ) );
		}
		$destination = isset( $_POST['destination'] ) ? esc_url_raw( wp_unslash( (string) $_POST['destination'] ) ) : '';
		$label       = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['label'] ) ) : '';
		$post_id     = isset( $_POST['post_id'] ) ? absint( (string) $_POST['post_id'] ) : 0;

		$result = OpenQR_Codes::create_dynamic( $destination, $label, $post_id );
		if ( empty( $result['ok'] ) ) {
			$error = rawurlencode( (string) $result['error'] );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'         => 'openqr-new',
						'openqr_error' => $error,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		wp_safe_redirect(
			OpenQR_Admin::codes_url(
				array(
					'openqr_new'    => (string) $result['row']['code_id'],
					'openqr_result' => 'created',
				)
			)
		);
		exit;
	}

	/**
	 * Admin_post handler: create static from the screen.
	 *
	 * @return void
	 */
	public static function handle_create_static(): void {
		if ( ! check_admin_referer( 'openqr_create_static' ) || ! current_user_can( 'openqr_manage_codes' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'openqr' ) );
		}
		$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( (string) $_POST['type'] ) ) : '';
		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['label'] ) ) : '';
		$post_id = isset( $_POST['post_id'] ) ? absint( (string) $_POST['post_id'] ) : 0;
		$raw     = array();
		if ( isset( $_POST['fields'][ $type ] ) && is_array( $_POST['fields'][ $type ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below
			$raw = wp_unslash( $_POST['fields'][ $type ] );
		}
		$fields = self::collect_static_fields( $type, $raw );

		$result = OpenQR_Codes::create_static( $type, $fields, $label, $post_id );
		if ( empty( $result['ok'] ) ) {
			$error = rawurlencode( (string) $result['error'] );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'         => 'openqr-new',
						'openqr_error' => $error,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		wp_safe_redirect(
			OpenQR_Admin::codes_url(
				array(
					'openqr_new'    => (string) $result['row']['code_id'],
					'openqr_result' => 'created',
				)
			)
		);
		exit;
	}
}

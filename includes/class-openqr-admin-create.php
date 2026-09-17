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
	 * Per-type field definitions (mirror lib/payloads.ts on openqr.uk).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function static_types(): array {
		return array(
			'url'      => array(
				'label'  => __( 'Website', 'openqr' ),
				'fields' => array( 'url' => array( __( 'URL', 'openqr' ), 'url', true ) ),
			),
			'text'     => array(
				'label'  => __( 'Text', 'openqr' ),
				'fields' => array( 'text' => array( __( 'Text', 'openqr' ), 'text', true ) ),
			),
			'email'    => array(
				'label'  => __( 'Email', 'openqr' ),
				'fields' => array(
					'email'   => array( __( 'Email address', 'openqr' ), 'email', true ),
					'subject' => array( __( 'Subject (optional)', 'openqr' ), 'text', false ),
					'body'    => array( __( 'Body (optional)', 'openqr' ), 'text', false ),
				),
			),
			'phone'    => array(
				'label'  => __( 'Phone', 'openqr' ),
				'fields' => array( 'phone' => array( __( 'Phone number', 'openqr' ), 'tel', true ) ),
			),
			'sms'      => array(
				'label'  => __( 'SMS', 'openqr' ),
				'fields' => array(
					'phone'   => array( __( 'Phone number', 'openqr' ), 'tel', true ),
					'message' => array( __( 'Message (optional)', 'openqr' ), 'text', false ),
				),
			),
			'whatsapp' => array(
				'label'  => __( 'WhatsApp', 'openqr' ),
				'fields' => array(
					'phone'   => array( __( 'Number (country code, digits only)', 'openqr' ), 'tel', true ),
					'message' => array( __( 'Message (optional)', 'openqr' ), 'text', false ),
				),
			),
			'wifi'     => array(
				'label'  => __( 'Wi-Fi', 'openqr' ),
				'fields' => array(
					'ssid'       => array( __( 'Network name (SSID)', 'openqr' ), 'text', true ),
					'password'   => array( __( 'Password', 'openqr' ), 'text', false ),
					'encryption' => array( __( 'Security (WPA, WEP or nopass)', 'openqr' ), 'text', false ),
					'hidden'     => array( __( 'Hidden network (true/false)', 'openqr' ), 'text', false ),
				),
			),
			'geo'      => array(
				'label'  => __( 'Location', 'openqr' ),
				'fields' => array(
					'lat' => array( __( 'Latitude', 'openqr' ), 'text', true ),
					'lng' => array( __( 'Longitude', 'openqr' ), 'text', true ),
				),
			),
			'vcard'    => array(
				'label'  => __( 'Contact card', 'openqr' ),
				'fields' => array(
					'firstName' => array( __( 'First name', 'openqr' ), 'text', false ),
					'lastName'  => array( __( 'Last name', 'openqr' ), 'text', false ),
					'org'       => array( __( 'Organisation', 'openqr' ), 'text', false ),
					'title'     => array( __( 'Job title', 'openqr' ), 'text', false ),
					'phone'     => array( __( 'Phone', 'openqr' ), 'tel', false ),
					'email'     => array( __( 'Email', 'openqr' ), 'email', false ),
					'url'       => array( __( 'Website', 'openqr' ), 'url', false ),
					'address'   => array( __( 'Address', 'openqr' ), 'text', false ),
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
			<h1 class="openqr-title"><?php echo OpenQR_Admin::logo( 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Create QR', 'openqr' ); ?></h1>

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
					<input type="url" name="destination" required class="regular-text" placeholder="https://" value="<?php echo esc_attr( $prefill_url ); ?>" />
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
				<label class="openqr-field">
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
							<?php foreach ( $def['fields'] as $field => $meta ) : ?>
								<label class="openqr-field">
									<span><?php echo esc_html( (string) $meta[0] ); ?></span>
									<input type="<?php echo esc_attr( (string) $meta[1] ); ?>" class="regular-text" name="fields[<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $field ); ?>]"<?php echo $meta[2] ? ' data-required="1"' : ''; ?> />
								</label>
							<?php endforeach; ?>
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
		$fields = array();
		foreach ( $raw as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( '' !== $key && '' !== (string) $v ) {
				$fields[ $key ] = sanitize_text_field( (string) $v );
			}
		}

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

<?php
/**
 * Settings: connection (key paste + verify), delegation, defaults, safeguards, staging
 * override, uninstall policy, API status. Connection managers only.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
final class OpenQR_Admin_Settings {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_openqr_save_settings', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Where an interrupted task lands after connecting.
	 *
	 * @return string
	 */
	public static function connect_url(): string {
		return add_query_arg(
			array( 'page' => 'openqr-settings' ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! OpenQR_Capabilities::can_manage_connection() ) {
			wp_die( esc_html__( 'Only administrators can manage the OpenQR connection.', 'openqr' ) );
		}
		$account   = OpenQR_Settings::account();
		$connected = OpenQR_Settings::is_connected();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reflected notice only
		$error  = isset( $_GET['openqr_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['openqr_error'] ) ) : '';
		$status = OpenQR_Cache::remember(
			'status',
			'discovery',
			static function () {
				$res = OpenQR_Api_Client::discovery();
				if ( ! $res->is_ok() ) {
					return array( 'unreachable' => true );
				}
				$body = $res->body() ?? array();
				return array( 'version' => (string) ( $body['version'] ?? '' ) );
			}
		);
		?>
		<div class="wrap openqr-wrap">
			<?php
			OpenQR_Admin::hero(
				__( 'Settings', 'openqr' ),
				__( 'Your OpenQR connection, who can manage codes, and the safeguards that protect printed material.', 'openqr' )
			);
			?>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div class="card openqr-card">
				<h2><?php esc_html_e( 'Connection', 'openqr' ); ?></h2>
				<?php if ( $connected && $account ) : ?>
					<p>
						<span class="openqr-status openqr-status--active"><?php esc_html_e( 'Connected', 'openqr' ); ?></span>
						<strong><?php echo esc_html( $account['email'] ); ?></strong>
						· key …<code><?php echo esc_html( $account['key_last4'] ); ?></code>
						<?php if ( '' !== $account['plan'] ) : ?>
							· <?php echo esc_html( ucfirst( $account['plan'] ) ); ?>
						<?php endif; ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'The API key is stored in this site database and used only to talk to openqr.uk. It is never shown again here; rotate it from your OpenQR dashboard if needed.', 'openqr' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="openqr-inline-form">
						<input type="hidden" name="action" value="openqr_save_settings" />
						<?php wp_nonce_field( 'openqr_settings' ); ?>
						<button type="submit" name="openqr_do" value="disconnect" class="button" onclick="return confirm('<?php echo esc_js( __( 'Disconnect? Pages keep working and your QR images stay live; you will not be able to create or edit codes until you reconnect.', 'openqr' ) ); ?>');"><?php esc_html_e( 'Disconnect', 'openqr' ); ?></button>
					</form>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="openqr-connect-form" <?php echo $connected ? 'hidden' : ''; ?>>
					<input type="hidden" name="action" value="openqr_save_settings" />
					<?php wp_nonce_field( 'openqr_settings' ); ?>
					<p><?php esc_html_e( 'Create a free OpenQR account, generate an API key named after this website, and paste it below. The key stays on this server and is used only to talk to openqr.uk.', 'openqr' ); ?></p>
					<p>
						<a class="button" href="<?php echo esc_url( OpenQR_Marketing_Link::build( '/api', 'onboarding' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Create account + key', 'openqr' ); ?> <span aria-hidden="true">↗</span></a>
					</p>
					<label class="openqr-field">
						<span><?php esc_html_e( 'API key', 'openqr' ); ?></span>
						<input type="password" name="openqr_api_key" autocomplete="off" class="regular-text code" placeholder="oqr_…" required pattern="oqr_[A-Za-z0-9]{10,120}" />
					</label>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Connect', 'openqr' ); ?></button></p>
				</form>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="openqr_save_settings" />
				<?php wp_nonce_field( 'openqr_settings' ); ?>

				<div class="card openqr-card">
					<h2><?php esc_html_e( 'Who can manage codes', 'openqr' ); ?></h2>
					<p class="openqr-card-lede"><?php esc_html_e( 'Choose which roles can create and edit this site’s QR codes. Delegated roles only ever see codes linked to this site: connecting the account, browsing your whole OpenQR library and deleting codes stay with administrators.', 'openqr' ); ?></p>
					<fieldset class="openqr-role-picker">
						<legend class="screen-reader-text"><?php esc_html_e( 'Roles that can manage codes', 'openqr' ); ?></legend>
						<div class="openqr-role">
							<input type="checkbox" id="openqr-role-administrator" checked disabled />
							<label for="openqr-role-administrator"><?php esc_html_e( 'Administrator', 'openqr' ); ?></label>
							<span class="openqr-role-note"><?php esc_html_e( 'Always', 'openqr' ); ?></span>
						</div>
						<?php $codes_roles = OpenQR_Settings::codes_roles(); ?>
						<?php foreach ( OpenQR_Capabilities::grantable_roles() as $slug => $label ) : ?>
							<div class="openqr-role">
								<input type="checkbox" name="settings[manage_codes_roles][]" value="<?php echo esc_attr( $slug ); ?>" id="openqr-role-<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $codes_roles, true ) ); ?> />
								<label for="openqr-role-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></label>
							</div>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Applied as soon as you save. A role you untick keeps any codes it created, but can no longer create or edit.', 'openqr' ); ?></p>
				</div>

				<div class="card openqr-card">
					<h2><?php esc_html_e( 'Defaults and safeguards', 'openqr' ); ?></h2>
					<label class="openqr-field">
						<span><?php esc_html_e( 'Default embed size (px)', 'openqr' ); ?></span>
						<input type="number" name="settings[default_size]" min="96" max="2048" value="<?php echo esc_attr( (string) OpenQR_Settings::default_size() ); ?>" />
					</label>
					<label class="openqr-field">
						<span><?php esc_html_e( 'Per-user code creations per hour (safeguard)', 'openqr' ); ?></span>
						<input type="number" name="settings[per_user_create_limit]" min="0" max="300" value="<?php echo esc_attr( (string) OpenQR_Settings::per_user_create_limit() ); ?>" />
						<p class="description"><?php esc_html_e( 'Protects your OpenQR rate budget from runaway bulk actions. Set 0 to disable.', 'openqr' ); ?></p>
					</label>
				</div>

				<?php if ( OpenQR_Lifecycle::is_staging() ) : ?>
					<div class="card openqr-card openqr-staging-card">
						<h2><?php esc_html_e( 'Staging copy detected', 'openqr' ); ?></h2>
						<p><?php esc_html_e( 'Remote changes are locked on this environment so a clone cannot repoint your live codes.', 'openqr' ); ?></p>
						<p>
							<label>
								<input type="checkbox" name="settings[staging_mutations_enabled]" value="1" <?php checked( OpenQR_Settings::staging_mutations_enabled() ); ?> />
								<?php esc_html_e( 'Allow changes from this environment anyway', 'openqr' ); ?>
							</label>
						</p>
					</div>
				<?php endif; ?>

				<div class="card openqr-card">
					<h2><?php esc_html_e( 'Data on uninstall', 'openqr' ); ?></h2>
					<p>
						<label>
							<input type="checkbox" name="settings[delete_on_uninstall]" value="1" <?php checked( OpenQR_Settings::delete_on_uninstall() ); ?> />
							<?php esc_html_e( 'Delete everything when the plugin is uninstalled (API key, settings, stored QR images)', 'openqr' ); ?>
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'Unchecked: published QR images keep working after uninstall. Checked: everything, including the images your pages embed, is removed.', 'openqr' ); ?></p>
				</div>

				<?php submit_button(); ?>
			</form>

			<div class="card openqr-card">
				<h2><?php esc_html_e( 'API status', 'openqr' ); ?></h2>
				<p>
					<?php if ( is_array( $status ) && empty( $status['unreachable'] ) ) : ?>
						<span class="openqr-status openqr-status--active"><?php esc_html_e( 'openqr.uk reachable', 'openqr' ); ?></span>
						<?php if ( ! empty( $status['version'] ) ) : ?>
							· API v<?php echo esc_html( $status['version'] ); ?>
						<?php endif; ?>
					<?php else : ?>
						<span class="openqr-status openqr-status--paused"><?php esc_html_e( 'openqr.uk unreachable from this server', 'openqr' ); ?></span>
					<?php endif; ?>
				</p>
				<p class="description"><?php esc_html_e( 'Your codes and images keep working even when openqr.uk is unreachable. Only creating and editing needs the connection.', 'openqr' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Admin_post handler: save settings, connect, disconnect.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		if ( ! check_admin_referer( 'openqr_settings' ) || ! OpenQR_Capabilities::can_manage_connection() ) {
			wp_die( esc_html__( 'Invalid request.', 'openqr' ) );
		}

		// Disconnect is its own button on the same form.
		if ( isset( $_POST['openqr_do'] ) && 'disconnect' === $_POST['openqr_do'] ) {
			OpenQR_Lifecycle::clear_account_data();
			wp_safe_redirect( add_query_arg( 'page', 'openqr-settings', admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in update_all
			OpenQR_Settings::update_all( wp_unslash( $_POST['settings'] ) );
		}

		$connected_now = false;
		$connect_error = '';
		if ( isset( $_POST['openqr_api_key'] ) ) {
			$key = trim( sanitize_text_field( wp_unslash( (string) $_POST['openqr_api_key'] ) ) );
			if ( preg_match( '/^oqr_[A-Za-z0-9]{10,120}$/', $key ) ) {
				$response = OpenQR_Rest_Proxy::verify_key( $key );
				$account  = $response->body();
				if ( ! $response->is_ok() ) {
					$connect_error = sprintf(
						/* translators: %d: HTTP status code. */
						__( 'OpenQR could not verify that key (HTTP %d). No changes were saved. Check the key and try again.', 'openqr' ),
						$response->status()
					);
				} elseif ( empty( $account['email'] ) ) {
					$connect_error = __( 'OpenQR returned an account without an identity, so nothing was saved. This is a version mismatch between the plugin and the API; it has been logged.', 'openqr' );
					OpenQR_Cache::forget( 'status' );
				} else {
					OpenQR_Settings::store_connection( $key, $account );
					OpenQR_Capabilities::seed_roles();
					$connected_now = true;
				}
			}
		}

		// Land the user back on their interrupted task after connecting.
		$pending = get_user_meta( get_current_user_id(), 'openqr_pending', true );
		if ( $connected_now && is_array( $pending ) && isset( $pending['post_id'] ) ) {
			$post_id = (int) $pending['post_id'];
			delete_user_meta( get_current_user_id(), 'openqr_pending' );
			wp_safe_redirect(
				add_query_arg(
					array(
						'action'    => 'openqr_create_for_post',
						'post'      => $post_id,
						'post_type' => get_post_type( $post_id ) ? get_post_type( $post_id ) : 'page',
						'_wpnonce'  => wp_create_nonce( 'openqr_create_for_post_' . $post_id ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$redirect = array( 'page' => 'openqr-settings' );
		if ( '' !== $connect_error ) {
			$redirect['openqr_error'] = rawurlencode( $connect_error );
		}
		wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
		exit;
	}
}

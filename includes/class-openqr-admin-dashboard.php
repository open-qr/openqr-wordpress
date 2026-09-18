<?php
/**
 * Admin Dashboard: connection state, usage vs entitlements, recent site codes, quick actions.
 * First run = connect wizard.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard screen.
 */
final class OpenQR_Admin_Dashboard {

	/**
	 * Hook registration placeholder (kept for symmetry with the other screens).
	 *
	 * @return void
	 */
	public static function register(): void {}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'openqr_manage_codes' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage OpenQR codes.', 'openqr' ) );
		}
		$account = OpenQR_Settings::account();
		if ( ! $account ) {
			self::render_connect_wizard();
			return;
		}
		$pending = get_user_meta( get_current_user_id(), 'openqr_pending', true );
		?>
		<div class="wrap openqr-wrap">
			<?php
			OpenQR_Admin::hero(
				__( 'OpenQR', 'openqr' ),
				__( 'Every code on this site, ready for print: create one, download it, and change where it points without reprinting.', 'openqr' ),
				array(
					array(
						'url'     => OpenQR_Admin::create_url(),
						'label'   => __( 'New QR code', 'openqr' ),
						'primary' => true,
					),
					array(
						'url'   => OpenQR_Admin::codes_url(),
						'label' => __( 'Manage codes', 'openqr' ),
					),
				)
			);
			?>

			<?php if ( is_array( $pending ) && isset( $pending['post_id'] ) ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						$edit_link = get_edit_post_link( (int) $pending['post_id'] );
						printf(
							/* translators: %s: edit-post link. */
							esc_html__( 'You still have a QR code to create for: %s', 'openqr' ),
							$edit_link
								? sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html( (string) get_the_title( (int) $pending['post_id'] ) ) )
								: esc_html( (string) get_the_title( (int) $pending['post_id'] ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="openqr-stats-row">
				<div class="openqr-stat-card">
					<span class="openqr-stat-number"><?php echo esc_html( (string) OpenQR_Registry::count() ); ?></span>
					<span class="openqr-stat-label"><?php esc_html_e( 'Codes on this site', 'openqr' ); ?></span>
				</div>
				<?php
				$limit  = OpenQR_Settings::limit_dynamic_codes();
				$active = OpenQR_Settings::usage_active_dynamic();
				if ( null !== $limit ) :
					?>
					<div class="openqr-stat-card">
						<span class="openqr-stat-number">
							<?php
							printf(
								'%s / %s',
								esc_html( null !== $active ? (string) $active : '-' ),
								esc_html( 0 === $limit ? '0' : (string) $limit )
							);
							?>
						</span>
						<span class="openqr-stat-label"><?php esc_html_e( 'Active dynamic codes (account)', 'openqr' ); ?></span>
					</div>
				<?php endif; ?>
				<div class="openqr-stat-card">
					<span class="openqr-stat-number openqr-stat-number--small"><?php echo esc_html( $account['email'] ); ?></span>
					<span class="openqr-stat-label">
						<?php
						printf(
							/* translators: 1: plan name, 2: masked key. */
							esc_html__( 'Connected · %1$s · key …%2$s', 'openqr' ),
							esc_html( '' !== $account['plan'] ? ucfirst( $account['plan'] ) : __( 'Plan', 'openqr' ) ),
							esc_html( $account['key_last4'] )
						);
						?>
					</span>
				</div>
			</div>

			<h2><?php esc_html_e( 'Recent codes on this site', 'openqr' ); ?></h2>
			<?php
			$rows = OpenQR_Registry::page( 5, 0 );
			if ( ! $rows ) {
				echo '<p class="openqr-empty">' . esc_html__( 'No QR codes yet. Create one from any page, post or product - or right here.', 'openqr' ) . '</p>';
			} else {
				?>
			<div class="openqr-table-wrap">
			<table class="widefat striped openqr-table">
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td class="openqr-cell-thumb">
								<?php
								$thumb = OpenQR_Assets::url( $row );
								if ( $thumb ) {
									printf( '<img src="%s" width="48" height="48" alt="" />', esc_url( $thumb ) );
								} else {
									echo '<span class="openqr-thumb-placeholder"></span>';
								}
								?>
							</td>
							<td>
								<strong><?php echo esc_html( (string) ( $row['placement_label'] ?? __( 'Untitled', 'openqr' ) ) ); ?></strong>
								<?php if ( 'static' === $row['kind'] ) : ?>
									<span class="openqr-badge openqr-badge--static"><?php esc_html_e( 'static', 'openqr' ); ?></span>
								<?php elseif ( 'paused' === $row['status_mirror'] ) : ?>
									<span class="openqr-badge openqr-badge--paused"><?php esc_html_e( 'paused', 'openqr' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $row['short_url'] ) ) : ?>
									<div class="openqr-muted"><code><?php echo esc_html( (string) $row['short_url'] ); ?></code></div>
								<?php endif; ?>
							</td>
							<td class="openqr-cell-actions">
								<a href="<?php echo esc_url( OpenQR_Post_Actions::download_url( (string) $row['code_id'], 'png' ) ); ?>"><?php esc_html_e( 'Download', 'openqr' ); ?></a>
								|
								<a href="<?php echo esc_url( OpenQR_Admin::analytics_url( array( 'code' => (string) $row['code_id'] ) ) ); ?>"><?php esc_html_e( 'Activity', 'openqr' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
				<?php
			}
			?>

			<?php self::render_feedback_card(); ?>
		</div>
		<?php
	}

	/**
	 * Feedback and roadmap. Explicit user action only: the mailto link opens the user's own
	 * email client and nothing is sent until they send it. No phone-home, ever.
	 *
	 * @return void
	 */
	private static function render_feedback_card(): void {
		$subject = rawurlencode( 'OpenQR for WordPress - feature request' );
		$body    = rawurlencode(
			sprintf(
				"What would you like OpenQR to do?\n\n\n---\nPlugin: %s\nWordPress: %s\nPHP: %s\n",
				OPENQR_VERSION,
				get_bloginfo( 'version' ),
				PHP_VERSION
			)
		);
		?>
		<div class="card openqr-card openqr-feedback">
			<h2><?php esc_html_e( 'Help shape what ships next', 'openqr' ); ?></h2>
			<p><?php esc_html_e( 'OpenQR is built in the open, and the roadmap follows what you ask for. The requests currently at the top of the pile:', 'openqr' ); ?></p>
			<ul class="openqr-roadmap">
				<li><?php esc_html_e( 'Bulk creation: turn a list of links into a set of printed codes', 'openqr' ); ?></li>
				<li><?php esc_html_e( 'An Elementor widget alongside the Gutenberg block', 'openqr' ); ?></li>
				<li><?php esc_html_e( 'Scheduled destination changes for campaign windows', 'openqr' ); ?></li>
				<li><?php esc_html_e( 'More fixed-content types and code styling options', 'openqr' ); ?></li>
			</ul>
			<p class="openqr-feedback-actions">
				<a class="button button-primary" href="<?php echo esc_url( 'mailto:accounts@openqr.uk?subject=' . $subject . '&body=' . $body ); ?>"><?php esc_html_e( 'Request a feature', 'openqr' ); ?></a>
				<a class="button" href="<?php echo esc_url( OpenQR_Marketing_Link::build( '/blog', 'plugin-feedback' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See what’s new', 'openqr' ); ?> <span aria-hidden="true">&#8599;</span></a>
			</p>
			<p class="description"><?php esc_html_e( 'Request a feature opens your own email app with your versions filled in; nothing is sent until you send it.', 'openqr' ); ?></p>
		</div>
		<?php
	}

	/**
	 * First-run connect wizard.
	 *
	 * @return void
	 */
	private static function render_connect_wizard(): void {
		?>
		<div class="wrap openqr-wrap">
			<?php
			OpenQR_Admin::hero(
				__( 'OpenQR', 'openqr' ),
				__( 'Connect once and every code you print stays editable.', 'openqr' )
			);
			?>
			<div class="card openqr-card openqr-connect-card">
				<h2><?php esc_html_e( 'Connect your OpenQR account', 'openqr' ); ?></h2>
				<p><?php esc_html_e( 'Create, manage and print QR codes for your pages with a free OpenQR account. Dynamic codes stay editable after printing, and scans show up here.', 'openqr' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Create a free account and an API key named after this website.', 'openqr' ); ?></li>
					<li><?php esc_html_e( 'Paste the key into OpenQR Settings. It stays on this server.', 'openqr' ); ?></li>
				</ol>
				<p>
					<a class="button button-primary button-hero" href="<?php echo esc_url( OpenQR_Admin::settings_url() ); ?>"><?php esc_html_e( 'Connect OpenQR', 'openqr' ); ?></a>
					<a class="button button-hero" href="<?php echo esc_url( OpenQR_Marketing_Link::keys_page() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Create a free account + key', 'openqr' ); ?> <span aria-hidden="true">&#8599;</span></a>
				</p>
			</div>
		</div>
		<?php
	}
}

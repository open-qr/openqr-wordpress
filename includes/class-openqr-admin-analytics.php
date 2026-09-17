<?php
/**
 * Activity screen: headline scan counts per code, and the honest plan gate. The upgrade card
 * appears ONLY here, driven by entitlements, never by absent analytics fields.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Analytics screen.
 */
final class OpenQR_Admin_Analytics {

	/**
	 * Hook registration placeholder (kept for symmetry).
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
			wp_die( esc_html__( 'You do not have permission to view OpenQR analytics.', 'openqr' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only picker
		$code_id = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) : '';
		// phpcs:enable
		$rows = OpenQR_Registry::page( 100, 0 );
		?>
		<div class="wrap openqr-wrap">
			<h1 class="openqr-title"><?php echo OpenQR_Admin::logo( 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Activity', 'openqr' ); ?></h1>

			<form method="get">
				<input type="hidden" name="page" value="openqr-analytics" />
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Choose a code', 'openqr' ); ?></span>
					<select name="code">
						<option value=""><?php esc_html_e( '- choose a code -', 'openqr' ); ?></option>
						<?php foreach ( $rows as $row ) : ?>
							<option value="<?php echo esc_attr( (string) $row['code_id'] ); ?>" <?php selected( $code_id, (string) $row['code_id'] ); ?>>
								<?php echo esc_html( (string) ( $row['placement_label'] ? $row['placement_label'] : $row['code_id'] ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button class="button"><?php esc_html_e( 'Show', 'openqr' ); ?></button>
				</label>
			</form>

			<?php
			if ( '' === $code_id ) {
				echo '<p class="openqr-muted">' . esc_html__( 'Pick a code to see its scans. Codes you create from pages, posts and products are listed here.', 'openqr' ) . '</p>';
				return;
			}
			$row     = OpenQR_Registry::get_by_code_id( $code_id );
			$summary = OpenQR_Codes::scan_summary( $code_id );
			$asset   = $row ? OpenQR_Assets::url( $row ) : null;
			?>
			<div class="openqr-analytics-layout">
				<div class="openqr-card">
					<?php if ( $asset ) : ?>
						<img src="<?php echo esc_url( $asset ); ?>" width="140" height="140" alt="" class="openqr-analytics-thumb" />
					<?php endif; ?>
					<h2><?php echo esc_html( (string) ( $row['placement_label'] ?? $code_id ) ); ?></h2>
					<?php if ( ! empty( $row['short_url'] ) ) : ?>
						<p><code><?php echo esc_html( (string) $row['short_url'] ); ?></code></p>
					<?php endif; ?>
				</div>

				<div class="openqr-card openqr-stats-row">
					<?php if ( empty( $summary['ok'] ) ) : ?>
						<p><?php esc_html_e( 'Scan counts are unavailable right now. They will refresh automatically.', 'openqr' ); ?></p>
					<?php else : ?>
						<div class="openqr-stat-card">
							<span class="openqr-stat-number"><?php echo esc_html( number_format_i18n( (int) ( $summary['total'] ?? 0 ) ) ); ?></span>
							<span class="openqr-stat-label"><?php esc_html_e( 'Total scans', 'openqr' ); ?></span>
						</div>
						<div class="openqr-stat-card">
							<span class="openqr-stat-number"><?php echo esc_html( number_format_i18n( (int) ( $summary['last7'] ?? 0 ) ) ); ?></span>
							<span class="openqr-stat-label"><?php esc_html_e( 'Last 7 days', 'openqr' ); ?></span>
						</div>
						<?php if ( ! empty( $summary['top_country'] ) ) : ?>
							<div class="openqr-stat-card">
								<span class="openqr-stat-number"><?php echo esc_html( (string) $summary['top_country'] ); ?></span>
								<span class="openqr-stat-label"><?php esc_html_e( 'Top country', 'openqr' ); ?></span>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! OpenQR_Settings::feature_detailed_analytics() ) : ?>
				<div class="openqr-card openqr-upsell">
					<h3><?php esc_html_e( 'Devices, referrers, cities and hour-by-hour detail live in your OpenQR dashboard', 'openqr' ); ?></h3>
					<p><?php esc_html_e( 'The Pro plan adds day-by-day charts, devices, referrers, regions, towns and connection types, with 90 days of history.', 'openqr' ); ?></p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( OpenQR_Marketing_Link::build( '/dashboard', 'analytics' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the dashboard', 'openqr' ); ?></a>
						<a class="button" href="<?php echo esc_url( OpenQR_Marketing_Link::pricing() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See Pro', 'openqr' ); ?></a>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

<?php
/**
 * QR Codes screen: the site's registry, with row actions (edit destination/label, pause,
 * resume, download, activity, delete) handled through the REST proxy / admin_post.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Codes list screen.
 */
final class OpenQR_Admin_Codes {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_openqr_update_code', array( __CLASS__, 'handle_update' ) );
		add_action( 'admin_post_openqr_delete_code', array( __CLASS__, 'handle_delete' ) );
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$new_id  = isset( $_GET['openqr_new'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['openqr_new'] ) ) : '';
		$result  = isset( $_GET['openqr_result'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['openqr_result'] ) ) : '';
		// phpcs:enable

		$per_page = 20;
		$rows     = $post_id > 0 ? OpenQR_Registry::for_post( $post_id ) : OpenQR_Registry::page( $per_page, ( $paged - 1 ) * $per_page, $search );
		$total    = $post_id > 0 ? count( $rows ) : OpenQR_Registry::count( $search );
		$pages    = (int) ceil( $total / $per_page );
		?>
		<div class="wrap openqr-wrap">
			<h1 class="openqr-title"><?php echo OpenQR_Admin::logo( 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'QR Codes', 'openqr' ); ?>
				<a class="page-title-action" href="<?php echo esc_url( OpenQR_Admin::create_url() ); ?>"><?php esc_html_e( 'Create QR', 'openqr' ); ?></a>
			</h1>

			<?php if ( 'created' === $result ) : ?>
				<div class="notice notice-success"><p><strong><?php esc_html_e( 'Your QR code is ready.', 'openqr' ); ?></strong>
				<?php esc_html_e( 'Download it below for printing. You can change where it points at any time - even after it is printed.', 'openqr' ); ?></p></div>
			<?php elseif ( 'updated' === $result ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. Printed copies pick up the change the next time they are scanned.', 'openqr' ); ?></p></div>
			<?php elseif ( 'deleted' === $result ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Deleted.', 'openqr' ); ?></p></div>
			<?php elseif ( '' !== $result ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $result ); ?></p></div>
			<?php endif; ?>

			<?php if ( $post_id ) : ?>
				<p class="openqr-muted">
					<?php
					printf(
						/* translators: %s: post title. */
						esc_html__( 'Codes linked to: %s', 'openqr' ),
						esc_html( (string) get_the_title( $post_id ) )
					);
					?>
					- <a href="<?php echo esc_url( OpenQR_Admin::codes_url() ); ?>"><?php esc_html_e( 'show all', 'openqr' ); ?></a>
				</p>
			<?php endif; ?>

			<form method="get" class="openqr-search-form">
				<input type="hidden" name="page" value="openqr-codes" />
				<?php if ( $post_id ) : ?>
					<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="openqr-search"><?php esc_html_e( 'Search codes', 'openqr' ); ?></label>
					<input type="search" id="openqr-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
					<button class="button"><?php esc_html_e( 'Search', 'openqr' ); ?></button>
				</p>
			</form>

			<table class="widefat striped openqr-table">
				<thead>
					<tr>
						<th class="openqr-col-thumb"><?php esc_html_e( 'QR', 'openqr' ); ?></th>
						<th><?php esc_html_e( 'Code', 'openqr' ); ?></th>
						<th><?php esc_html_e( 'Points at', 'openqr' ); ?></th>
						<th><?php esc_html_e( 'Status', 'openqr' ); ?></th>
						<th><?php esc_html_e( 'Linked to', 'openqr' ); ?></th>
						<th class="openqr-col-actions"><?php esc_html_e( 'Actions', 'openqr' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No QR codes yet. Use "Create QR" on any page, post or product row, or the Create QR screen.', 'openqr' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php self::render_row( $row, (string) $row['code_id'] === $new_id ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 && ! $post_id ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * One table row + inline edit form.
	 *
	 * @param array<string, mixed> $row    Registry row.
	 * @param bool                 $is_new Highlight (just created).
	 * @return void
	 */
	private static function render_row( array $row, bool $is_new ): void {
		$code_id    = (string) $row['code_id'];
		$can_touch  = OpenQR_Capabilities::can_manage_row( $row );
		$can_delete = OpenQR_Capabilities::can_manage_connection();
		$asset      = OpenQR_Assets::url( $row );
		$post_id    = (int) ( $row['post_id'] ?? 0 );
		?>
		<tr<?php echo $is_new ? ' class="openqr-row-new"' : ''; ?>>
			<td class="openqr-cell-thumb">
				<?php if ( $asset ) : ?>
					<img src="<?php echo esc_url( $asset ); ?>" width="56" height="56" alt="" />
				<?php else : ?>
					<span class="openqr-thumb-placeholder" title="<?php esc_attr_e( 'Image renders on first download or page view', 'openqr' ); ?>"></span>
				<?php endif; ?>
			</td>
			<td>
				<strong><?php echo esc_html( (string) ( $row['placement_label'] ?? __( 'Untitled', 'openqr' ) ) ); ?></strong>
				<?php if ( 'static' === $row['kind'] ) : ?>
					<span class="openqr-badge openqr-badge--static"><?php esc_html_e( 'static', 'openqr' ); ?></span>
				<?php elseif ( 'paused' === $row['status_mirror'] ) : ?>
					<span class="openqr-badge openqr-badge--paused"><?php esc_html_e( 'paused', 'openqr' ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $row['short_url'] ) ) : ?>
					<div class="openqr-muted"><code class="openqr-copy" data-copy="<?php echo esc_attr( (string) $row['short_url'] ); ?>"><?php echo esc_html( (string) $row['short_url'] ); ?></code></div>
				<?php endif; ?>
			</td>
			<td class="openqr-cell-destination">
				<span class="openqr-destination-text"><?php echo esc_html( wp_html_excerpt( (string) ( $row['encoded_url'] ?? '' ), 60, '…' ) ); ?></span>
			</td>
			<td>
				<?php if ( 'static' === $row['kind'] ) : ?>
					<span aria-hidden="true">-</span>
				<?php else : ?>
					<span class="openqr-status openqr-status--<?php echo esc_attr( (string) $row['status_mirror'] ); ?>">
						<?php echo esc_html( 'active' === $row['status_mirror'] ? __( 'Active', 'openqr' ) : __( 'Paused', 'openqr' ) ); ?>
					</span>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $post_id && get_post( $post_id ) ) : ?>
					<a href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>"><?php echo esc_html( (string) get_the_title( $post_id ) ); ?></a>
				<?php else : ?>
					<span aria-hidden="true">-</span>
				<?php endif; ?>
			</td>
			<td class="openqr-cell-actions">
				<?php if ( $can_touch ) : ?>
					<button class="button button-small openqr-toggle-editor" data-target="openqr-edit-<?php echo esc_attr( $code_id ); ?>"><?php esc_html_e( 'Edit', 'openqr' ); ?></button>
					<?php if ( 'dynamic' === $row['kind'] ) : ?>
						<?php if ( 'active' === $row['status_mirror'] ) : ?>
							<button class="button button-small openqr-pause" data-code="<?php echo esc_attr( $code_id ); ?>" data-status="paused"><?php esc_html_e( 'Pause', 'openqr' ); ?></button>
						<?php else : ?>
							<button class="button button-small openqr-pause" data-code="<?php echo esc_attr( $code_id ); ?>" data-status="active"><?php esc_html_e( 'Resume', 'openqr' ); ?></button>
						<?php endif; ?>
					<?php endif; ?>
					<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( OpenQR_Post_Actions::download_url( $code_id, 'png' ) ); ?>"><?php esc_html_e( 'PNG', 'openqr' ); ?></a>
					<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( OpenQR_Post_Actions::download_url( $code_id, 'svg' ) ); ?>">SVG</a>
					<a class="button button-small" href="<?php echo esc_url( OpenQR_Admin::analytics_url( array( 'code' => $code_id ) ) ); ?>"><?php esc_html_e( 'Activity', 'openqr' ); ?></a>
					<?php if ( $can_delete ) : ?>
						<button class="button button-small openqr-delete" data-code="<?php echo esc_attr( $code_id ); ?>" data-label="<?php echo esc_attr( (string) ( $row['placement_label'] ?? '' ) ); ?>"><?php esc_html_e( 'Delete', 'openqr' ); ?></button>
					<?php endif; ?>

					<div class="openqr-edit-form" id="openqr-edit-<?php echo esc_attr( $code_id ); ?>" hidden>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="openqr_update_code" />
							<input type="hidden" name="code_id" value="<?php echo esc_attr( $code_id ); ?>" />
							<?php wp_nonce_field( 'openqr_update_' . $code_id ); ?>
							<?php if ( 'dynamic' === $row['kind'] ) : ?>
								<label>
									<span><?php esc_html_e( 'Points at', 'openqr' ); ?></span>
									<input type="url" class="regular-text" name="destination" value="<?php echo esc_attr( (string) ( $row['encoded_url'] ?? '' ) ); ?>" />
								</label>
								<p class="description"><?php esc_html_e( 'Changing this re-points every printed copy instantly. The printed code itself never changes.', 'openqr' ); ?></p>
							<?php endif; ?>
							<label>
								<span><?php esc_html_e( 'Label', 'openqr' ); ?></span>
								<input type="text" class="regular-text" name="label" value="<?php echo esc_attr( (string) ( $row['placement_label'] ?? '' ) ); ?>" />
							</label>
							<p>
								<button class="button button-primary button-small" type="submit"><?php esc_html_e( 'Save', 'openqr' ); ?></button>
								<button class="button button-small openqr-toggle-editor" data-target="openqr-edit-<?php echo esc_attr( $code_id ); ?>" type="button"><?php esc_html_e( 'Cancel', 'openqr' ); ?></button>
							</p>
						</form>
					</div>
				<?php else : ?>
					<span class="openqr-muted"><?php esc_html_e( 'Read only', 'openqr' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Admin_post handler: destination, label and status edits.
	 *
	 * @return void
	 */
	public static function handle_update(): void {
		$code_id = isset( $_POST['code_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['code_id'] ) ) : '';
		if ( ! $code_id || ! check_admin_referer( 'openqr_update_' . $code_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'openqr' ) );
		}
		if ( ! OpenQR_Capabilities::can_manage_row( OpenQR_Registry::get_by_code_id( $code_id ) ) ) {
			wp_die( esc_html__( 'You cannot edit this QR code.', 'openqr' ) );
		}

		$patch = array();
		if ( isset( $_POST['destination'] ) ) {
			$destination = esc_url_raw( wp_unslash( (string) $_POST['destination'] ) );
			if ( '' !== $destination ) {
				$patch['destination'] = $destination;
			}
		}
		if ( isset( $_POST['label'] ) ) {
			$patch['label'] = sanitize_text_field( wp_unslash( (string) $_POST['label'] ) );
		}
		if ( isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'active', 'paused' ), true ) ) {
			$patch['status'] = sanitize_key( wp_unslash( (string) $_POST['status'] ) );
		}

		$result = OpenQR_Codes::update( $code_id, $patch );
		$args   = $result['ok']
			? array( 'openqr_result' => 'updated' )
			: array( 'openqr_result' => rawurlencode( (string) $result['error'] ) );
		wp_safe_redirect( OpenQR_Admin::codes_url( $args ) );
		exit;
	}

	/**
	 * Admin_post handler: delete remote and local. Connection managers only, confirmed in JS.
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		$code_id = isset( $_POST['code_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['code_id'] ) ) : '';
		if ( ! $code_id || ! check_admin_referer( 'openqr_delete_' . $code_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'openqr' ) );
		}
		if ( ! OpenQR_Capabilities::can_manage_connection() ) {
			wp_die( esc_html__( 'Only connection managers can delete QR codes.', 'openqr' ) );
		}
		$result = OpenQR_Codes::delete( $code_id );
		$args   = $result['ok']
			? array( 'openqr_result' => 'deleted' )
			: array( 'openqr_result' => rawurlencode( (string) $result['error'] ) );
		wp_safe_redirect( OpenQR_Admin::codes_url( $args ) );
		exit;
	}
}

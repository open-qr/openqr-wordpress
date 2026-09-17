<?php
/**
 * Print-first entry points on the Pages, Posts and Products lists:
 * "Create QR" / "View linked codes" / "Download QR" as row actions, plus the admin_post
 * download and create-for-post controllers.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * List-row actions + admin_post handlers.
 */
final class OpenQR_Post_Actions {

	/**
	 * Post types the actions appear on. Products join automatically with WooCommerce.
	 *
	 * @return array<int, string>
	 */
	public static function supported_types(): array {
		$types = array( 'post', 'page' );
		if ( post_type_exists( 'product' ) ) {
			$types[] = 'product';
		}
		return apply_filters( 'openqr_post_types', $types );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::supported_types() as $type ) {
			add_filter( "{$type}_row_actions", array( __CLASS__, 'add_row_actions' ), 20, 2 );
		}
		add_action( 'admin_post_openqr_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_openqr_create_for_post', array( __CLASS__, 'handle_create_for_post' ) );
		add_action( 'admin_notices', array( __CLASS__, 'creation_result_notice' ) );
	}

	/**
	 * Add the OpenQR row actions to a list row.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @param WP_Post               $post    The row's post.
	 * @return array<string, string>
	 */
	public static function add_row_actions( array $actions, WP_Post $post ): array {
		if ( ! OpenQR_Capabilities::can_manage_post_codes( $post->ID ) ) {
			return $actions;
		}
		$linked = OpenQR_Registry::for_post( $post->ID );

		$create_url               = add_query_arg(
			array(
				'post_type' => $post->post_type,
				'post'      => $post->ID,
				'action'    => 'openqr_create_for_post',
				'_wpnonce'  => wp_create_nonce( 'openqr_create_for_post_' . $post->ID ),
			),
			admin_url( 'admin.php' )
		);
		$actions['openqr_create'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $create_url ),
			esc_html__( 'Create QR', 'openqr' )
		);

		if ( $linked ) {
			$actions['openqr_linked'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( OpenQR_Admin::codes_url( array( 'post_id' => $post->ID ) ) ),
				sprintf(
					/* translators: %d: number of linked codes. */
					_n( 'View linked code (%d)', 'View linked codes (%d)', count( $linked ), 'openqr' ),
					count( $linked )
				)
			);
			$first = $linked[0];
			$asset = OpenQR_Assets::url( $first );
			if ( $asset ) {
				$actions['openqr_download'] = sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( self::download_url( (string) $first['code_id'], 'png' ) ),
					esc_html__( 'Download QR', 'openqr' )
				);
			}
		}
		return $actions;
	}

	/**
	 * Signed download URL for a code.
	 *
	 * @param string $code_id OpenQR code ID.
	 * @param string $format  png|svg.
	 * @return string
	 */
	public static function download_url( string $code_id, string $format = 'png' ): string {
		return add_query_arg(
			array(
				'action'   => 'openqr_download',
				'code'     => $code_id,
				'format'   => $format,
				'_wpnonce' => wp_create_nonce( 'openqr_download_' . $code_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Download handoff: PNG redirects to the durable file; SVG is rendered on demand,
	 * sanitised and served as an attachment. Never a public route.
	 *
	 * @return void
	 */
	public static function handle_download(): void {
		$code_id = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) : '';
		$format  = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( (string) $_GET['format'] ) ) : 'png';
		if ( ! $code_id || ! check_admin_referer( 'openqr_download_' . $code_id ) ) {
			wp_die( esc_html__( 'Invalid download link.', 'openqr' ) );
		}
		if ( ! OpenQR_Capabilities::can_manage_row( OpenQR_Registry::get_by_code_id( $code_id ) ) ) {
			wp_die( esc_html__( 'You cannot download this QR code.', 'openqr' ) );
		}
		$row = OpenQR_Registry::get_by_code_id( $code_id );
		if ( ! $row ) {
			wp_die( esc_html__( 'Unknown QR code.', 'openqr' ) );
		}
		$slug_name = '' !== (string) ( $row['placement_label'] ?? '' ) ? sanitize_file_name( $row['placement_label'] ) : $code_id;

		if ( 'svg' === $format ) {
			$svg = OpenQR_Assets::svg_download( $row );
			if ( null === $svg ) {
				wp_die( esc_html__( 'OpenQR could not render the SVG right now. Try again shortly.', 'openqr' ) );
			}
			header( 'Content-Type: image/svg+xml; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $slug_name . '.svg"' );
			header( 'X-Content-Type-Options: nosniff' );
			// phpcs:ignore WordPress.Security.EscapeOutput -- sanitised SVG handed to a download.
			echo $svg;
			exit;
		}

		$size = isset( $_GET['size'] ) ? max( 512, min( 4096, (int) $_GET['size'] ) ) : OpenQR_Assets::EMBED_SIZE;
		$url  = OpenQR_Assets::url( $row, $size );
		if ( ! $url ) {
			$url = OpenQR_Assets::ensure( $row );
		}
		if ( ! $url ) {
			wp_die( esc_html__( 'OpenQR could not produce the image right now. Try again shortly.', 'openqr' ) );
		}
		header( 'Location: ' . $url );
		exit;
	}

	/**
	 * One-click "Create QR" from a list row: destination = permalink, label = title, then a
	 * redirect to the codes screen (the print moment) or Settings (if not connected, with the
	 * intent preserved so connecting returns the user to their task).
	 *
	 * @return void
	 */
	public static function handle_create_for_post(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id || ! check_admin_referer( 'openqr_create_for_post_' . $post_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'openqr' ) );
		}
		if ( ! OpenQR_Capabilities::can_manage_post_codes( $post_id ) ) {
			wp_die( esc_html__( 'You cannot manage QR codes on this content.', 'openqr' ) );
		}
		$permalink = get_permalink( $post_id );
		$title     = get_the_title( $post_id );
		$back      = get_edit_post_link( $post_id, 'raw' );
		if ( ! $back ) {
			$back = admin_url( 'index.php' );
		}

		if ( ! $permalink ) {
			wp_safe_redirect( add_query_arg( 'openqr_result', 'no_permalink', $back ) );
			exit;
		}
		if ( ! OpenQR_Settings::is_connected() ) {
			update_user_meta(
				get_current_user_id(),
				'openqr_pending',
				array(
					'action'  => 'create_for_post',
					'post_id' => $post_id,
				)
			);
			wp_safe_redirect( OpenQR_Admin_Settings::connect_url() );
			exit;
		}
		if ( OpenQR_Lifecycle::mutations_allowed() ) {
			wp_safe_redirect( add_query_arg( 'openqr_result', 'staging_locked', $back ) );
			exit;
		}

		$result = OpenQR_Codes::create_dynamic( $permalink, $title, $post_id );
		if ( empty( $result['ok'] ) ) {
			$error = rawurlencode( (string) ( $result['error'] ?? 'failed' ) );
			wp_safe_redirect( add_query_arg( 'openqr_result', $error, $back ) );
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'openqr-codes',
					'openqr_new'    => (string) $result['row']['code_id'],
					'openqr_result' => 'created',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Result notices for the list-row round trip.
	 *
	 * @return void
	 */
	public static function creation_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag
		if ( ! isset( $_GET['openqr_result'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = sanitize_text_field( wp_unslash( (string) $_GET['openqr_result'] ) );
		if ( 'created' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'QR code created and linked to this page. You can download it for printing below, and change its destination any time after printing.', 'openqr' ) .
				'</p></div>';
			return;
		}
		if ( 'no_permalink' === $result ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'This content has no permalink yet, so no QR code was created.', 'openqr' ) . '</p></div>';
			return;
		}
		if ( 'staging_locked' === $result ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'OpenQR changes are locked on this staging copy.', 'openqr' ) . '</p></div>';
			return;
		}
		if ( '' !== $result ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $result ) . '</p></div>';
		}
	}
}

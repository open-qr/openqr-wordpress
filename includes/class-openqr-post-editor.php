<?php
/**
 * Classic-editor metabox: codes pointing at this page + one-click create. The block editor's
 * sidebar panel is the equivalent surface (src/sidebar/panel.js).
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Editor metabox.
 */
final class OpenQR_Post_Editor {

	/**
	 * Register hooks. The metabox itself registers on add_meta_boxes: add_meta_box() is not
	 * available at plugins_loaded.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
	}

	/**
	 * Add the metabox on supported post types.
	 *
	 * @return void
	 */
	public static function add_meta_boxes(): void {
		foreach ( OpenQR_Post_Actions::supported_types() as $type ) {
			add_meta_box( 'openqr_codes', __( 'OpenQR', 'openqr' ), array( __CLASS__, 'render' ), $type, 'side', 'default' );
		}
	}

	/**
	 * Render the metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render( WP_Post $post ): void {
		if ( ! OpenQR_Capabilities::can_manage_post_codes( $post->ID ) ) {
			echo '<p class="description">' . esc_html__( 'You do not have permission to manage QR codes on this content.', 'openqr' ) . '</p>';
			return;
		}
		$rows  = OpenQR_Registry::for_post( $post->ID );
		$state = get_post_meta( $post->ID, OpenQR_Lifecycle::POST_FLAG_META, true );
		?>
		<?php if ( $rows ) : ?>
			<ul class="openqr-meta-list">
				<?php foreach ( $rows as $row ) : ?>
					<li>
						<strong><?php echo esc_html( (string) ( $row['placement_label'] ?? __( 'Untitled', 'openqr' ) ) ); ?></strong>
						<?php if ( ! empty( $row['short_url'] ) ) : ?>
							<div class="openqr-muted"><code><?php echo esc_html( (string) $row['short_url'] ); ?></code></div>
						<?php endif; ?>
						<a href="<?php echo esc_url( OpenQR_Admin::codes_url( array( 'openqr_new' => (string) $row['code_id'] ) ) ); ?>"><?php esc_html_e( 'Manage', 'openqr' ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No QR codes point at this page yet.', 'openqr' ); ?></p>
		<?php endif; ?>

		<?php if ( is_array( $state ) && ! empty( $state['old'] ) ) : ?>
			<div class="notice notice-warning inline" style="margin:8px 0 0">
				<p>
					<?php esc_html_e( 'The address of this page changed after a code was printed for it.', 'openqr' ); ?>
					<a href="<?php echo esc_url( OpenQR_Admin::codes_url( array( 'post_id' => $post->ID ) ) ); ?>"><?php esc_html_e( 'Update the codes', 'openqr' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<p>
			<a class="button button-primary" href="
			<?php
			echo esc_url(
				OpenQR_Admin::create_url(
					array(
						'post_id'     => $post->ID,
						'destination' => rawurlencode( (string) get_permalink( $post ) ),
					)
				)
			);
			?>
													">
				<?php esc_html_e( 'Create QR for this page', 'openqr' ); ?>
			</a>
		</p>
		<p class="description"><?php esc_html_e( 'Print it once, change the destination whenever you like.', 'openqr' ); ?></p>
		<?php
	}
}

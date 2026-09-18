<?php
/**
 * Lifecycle defaults. Nothing destructive happens automatically:
 *
 * - Staging clones (non-production environment or a clone marker) get remote mutations LOCKED
 *   until explicitly re-enabled in Settings, so a cloned site cannot repoint live codes.
 * - A post permalink change is flagged and OFFERED as a destination update; never auto-written.
 * - Post going draft/private/trashed marks the row; the printed code keeps working (that is
 *   the point of a printed code).
 * - Disconnect/account replacement preserves assets and clears private account data.
 * - Uninstall removes everything only when the Settings opt-in says so.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Staging lock, permalink-drift detection, data wipe policy.
 */
final class OpenQR_Lifecycle {

	/**
	 * Post meta key holding the permalink-change offer.
	 */
	const POST_FLAG_META = '_openqr_permalink_changed';

	/**
	 * Register lifecycle hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'post_updated', array( __CLASS__, 'detect_permalink_change' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'detect_post_state_change' ), 10, 3 );
		add_filter( 'post_row_actions', array( __CLASS__, 'maybe_flag_row' ), 900, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'maybe_flag_row' ), 900, 2 );
	}

	/**
	 * Are REMOTE mutations (create/edit/delete on openqr.uk) allowed right now?
	 *
	 * @return WP_Error|null Error with the reason when locked, null when allowed.
	 */
	public static function mutations_allowed(): ?WP_Error {
		if ( self::is_staging() && ! OpenQR_Settings::staging_mutations_enabled() ) {
			return new WP_Error(
				'openqr_staging_locked',
				__( 'This looks like a staging or development copy of your site. OpenQR changes are locked here so a clone cannot repoint your live codes. Re-enable them in Settings if this is really production.', 'openqr' )
			);
		}
		return null;
	}

	/**
	 * Staging detection: WP core environment, common clone markers. Conservative: when unsure,
	 * treat as production EXCEPT for explicit wp_get_environment_type() values.
	 *
	 * @return bool
	 */
	public static function is_staging(): bool {
		if ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'staging', 'development', 'local' ), true ) ) {
			return true;
		}
		if ( defined( 'IS_STAGING_SITE' ) && IS_STAGING_SITE ) {
			return true;
		}
		if ( get_option( 'staging', false ) || defined( 'STAGING' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Remember that a post's permalink changed while registry rows point at it.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post after the update.
	 * @param WP_Post $post_before Post before the update.
	 * @return void
	 */
	public static function detect_permalink_change( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$before = get_permalink( $post_before );
		$after  = get_permalink( $post_after );
		if ( $before && $after && $before !== $after && OpenQR_Registry::for_post( $post_id ) ) {
			update_post_meta(
				$post_id,
				self::POST_FLAG_META,
				array(
					'old' => $before,
					'new' => $after,
					'at'  => time(),
				)
			);
		}
	}

	/**
	 * Mark post-state changes so the codes list can show context (no remote action taken).
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public static function detect_post_state_change( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status === $old_status || wp_is_post_revision( $post->ID ) ) {
			return;
		}
		if ( in_array( $new_status, array( 'draft', 'pending', 'private', 'trash' ), true ) && OpenQR_Registry::for_post( $post->ID ) ) {
			update_post_meta( $post->ID, '_openqr_post_state', $new_status );
		} elseif ( 'publish' === $new_status ) {
			delete_post_meta( $post->ID, '_openqr_post_state' );
		}
	}

	/**
	 * Small flag on list rows whose permalink drifted from linked codes.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param WP_Post               $post    Post.
	 * @return array<string, string>
	 */
	public static function maybe_flag_row( array $actions, WP_Post $post ): array {
		if ( get_post_meta( $post->ID, self::POST_FLAG_META, true ) ) {
			$actions['openqr_flag'] = '<span style="color:#996800">' . esc_html__( 'OpenQR: permalink changed - update linked codes', 'openqr' ) . '</span>';
		}
		return $actions;
	}

	/**
	 * Account replaced/disconnected: clear private data, KEEP published assets and registry
	 * rows so pages keep rendering.
	 *
	 * @return void
	 */
	public static function clear_account_data(): void {
		OpenQR_Settings::disconnect();
		OpenQR_Cache::flush_all();
	}

	/**
	 * Full removal (uninstall opt-in): assets, registry, options, DB-backed transients.
	 *
	 * @return void
	 */
	public static function wipe_site_data(): void {
		global $wpdb;
		OpenQR_Assets::wipe_all();
		OpenQR_Registry::drop();
		delete_option( OpenQR_Settings::OPT_SETTINGS );
		delete_option( OpenQR_Settings::OPT_KEY );
		delete_option( OpenQR_Settings::OPT_ACCOUNT );
		delete_option( OpenQR_Settings::OPT_VERSION );
		delete_option( OpenQR_Settings::OPT_AUTH_FAIL );
		delete_option( OpenQR_Cache::INDEX_OPTION );
		// The index option covers object-cache stores; this sweep covers DB-backed transients.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_openqr:%' OR option_name LIKE '\\_transient\\_timeout\\_openqr:%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- transient sweep, no user input.
	}
}

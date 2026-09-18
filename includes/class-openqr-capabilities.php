<?php
/**
 * Capability model. Explicit, grantable, object-scoped.
 *
 * Two capabilities:
 *
 *   openqr_manage_connection - connect/disconnect, browse/import the whole OpenQR account,
 *                              delete remote codes. Administrators only, always.
 *   openqr_manage_codes      - create/edit THIS SITE's linked codes. Administrators always;
 *                              other roles when the Settings role picker grants it deliberately.
 *
 * Managing a code linked to post P additionally requires edit_post on P. Delegated users see
 * ONLY the local registry, never the account library: repointing a printed code is treated as
 * being as damaging as deletion.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability helpers.
 */
final class OpenQR_Capabilities {

	/**
	 * Capability names.
	 */
	const MANAGE_CONNECTION = 'openqr_manage_connection';
	const MANAGE_CODES      = 'openqr_manage_codes';

	/**
	 * Seed capabilities on activation (and after connecting, for late role changes).
	 *
	 * @return void
	 */
	public static function seed_roles(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::MANAGE_CONNECTION );
			$admin->add_cap( self::MANAGE_CODES );
		}
		self::sync_codes_caps( OpenQR_Settings::codes_roles() );
	}

	/**
	 * Roles that may be granted openqr_manage_codes: every role that edits content, minus
	 * administrator (which always has it). slug => display name.
	 *
	 * @return array<string, string>
	 */
	public static function grantable_roles(): array {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $data ) {
			if ( 'administrator' === $slug || empty( $data['capabilities']['edit_posts'] ) ) {
				continue;
			}
			$out[ $slug ] = (string) ( $data['name'] ?? $slug );
		}
		return $out;
	}

	/**
	 * Make the stored role list true on the roles themselves: grant listed roles, revoke the
	 * rest. Administrator and openqr_manage_connection are never touched here.
	 *
	 * @param array<int, string> $granted Role slugs that should hold the capability.
	 * @return void
	 */
	public static function sync_codes_caps( array $granted ): void {
		foreach ( array_keys( wp_roles()->roles ) as $slug ) {
			if ( 'administrator' === $slug ) {
				continue;
			}
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			if ( in_array( $slug, $granted, true ) ) {
				$role->add_cap( self::MANAGE_CODES );
			} else {
				$role->remove_cap( self::MANAGE_CODES );
			}
		}
	}

	/**
	 * Can the current user manage the connection (account-level)?
	 *
	 * @return bool
	 */
	public static function can_manage_connection(): bool {
		return current_user_can( self::MANAGE_CONNECTION );
	}

	/**
	 * Can the current user manage this site's codes?
	 *
	 * @return bool
	 */
	public static function can_manage_codes(): bool {
		return current_user_can( self::MANAGE_CODES );
	}

	/**
	 * Manage a code linked to a post = manage capability AND edit rights on that post.
	 *
	 * @param int $post_id Local post ID.
	 * @return bool
	 */
	public static function can_manage_post_codes( int $post_id ): bool {
		if ( ! self::can_manage_codes() ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Capability gate for a registry row (post-linked rows are scoped to that post).
	 *
	 * @param array<string, mixed>|null $row Registry row.
	 * @return bool
	 */
	public static function can_manage_row( ?array $row ): bool {
		if ( ! $row ) {
			return self::can_manage_codes();
		}
		$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
		if ( $post_id > 0 ) {
			return self::can_manage_post_codes( $post_id );
		}
		return self::can_manage_codes();
	}
}

<?php
/**
 * Local registry: the ownership and lifecycle layer. Maps site/post to OpenQR code, encoded
 * URL and generated asset. NOT a cache of the account library — it is what delegated users
 * are scoped to and what survives an openqr.uk outage.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registry table CRUD.
 */
final class OpenQR_Registry {

	/**
	 * Schema version.
	 */
	const DB_VERSION = '1.0.1';

	/**
	 * Option holding the installed schema version.
	 */
	const TABLE_OPTION = 'openqr_registry_db_version';

	/**
	 * Full table name for this site.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'openqr_codes';
	}

	/**
	 * Create/upgrade the table on activation and when the schema version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::TABLE_OPTION ) === self::DB_VERSION && self::table_exists() ) {
			return;
		}
		self::install();
		update_option( self::TABLE_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Create or upgrade the table via dbDelta.
	 *
	 * @return void
	 */
	private static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				code_id VARCHAR(64) NOT NULL,
				kind VARCHAR(10) NOT NULL DEFAULT 'dynamic',
				slug VARCHAR(64) NOT NULL DEFAULT '',
				short_url VARCHAR(255) NOT NULL DEFAULT '',
				encoded_url TEXT NULL,
				payload TEXT NULL,
				style_hash VARCHAR(40) NOT NULL DEFAULT '',
				style_dark VARCHAR(9) NOT NULL DEFAULT '',
				style_light VARCHAR(9) NOT NULL DEFAULT '',
				style_theme VARCHAR(64) NOT NULL DEFAULT '',
				asset_path VARCHAR(255) NOT NULL DEFAULT '',
				post_id BIGINT UNSIGNED NULL,
				placement_label VARCHAR(191) NULL,
				status_mirror VARCHAR(20) NOT NULL DEFAULT 'active',
				account_id VARCHAR(64) NOT NULL DEFAULT '',
				permalink VARCHAR(255) NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code_id (code_id),
				KEY post_id (post_id),
				KEY kind (kind)
			) {$charset};"
		);
	}

	/**
	 * Whether the table exists.
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.Schema, WordPress.DB.PreparedSQL.NotPrepared -- schema probe, no user input.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	/**
	 * One row by OpenQR code ID.
	 *
	 * @param string $code_id OpenQR code ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_code_id( string $code_id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- registry is the source of truth here.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE code_id = %s', $code_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * All codes linked to a post.
	 *
	 * @param int $post_id Local post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_post( int $post_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE post_id = %d ORDER BY id DESC', $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Paged registry listing for the admin table (site-linked codes).
	 *
	 * @param int    $per_page Page size.
	 * @param int    $offset   Offset.
	 * @param string $search   Substring search.
	 * @param string $kind     dynamic|static|'' for all.
	 * @return array<int, array<string, mixed>>
	 */
	public static function page( int $per_page = 20, int $offset = 0, string $search = '', string $kind = '' ): array {
		global $wpdb;
		list( $where, $params ) = self::where_clause( $search, $kind );
		$sql                    = 'SELECT * FROM ' . self::table() . " {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$params[]               = $per_page;
		$params[]               = max( 0, $offset );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Row count with the same filters as page().
	 *
	 * @param string $search Substring search.
	 * @param string $kind   dynamic|static|'' for all.
	 * @return int
	 */
	public static function count( string $search = '', string $kind = '' ): int {
		global $wpdb;
		list( $where, $params ) = self::where_clause( $search, $kind );
		$sql                    = 'SELECT COUNT(*) FROM ' . self::table() . " {$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql, $params ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Shared WHERE builder for page()/count().
	 *
	 * @param string $search Substring search.
	 * @param string $kind   dynamic|static|'' for all.
	 * @return array{0: string, 1: array<int, mixed>} WHERE fragment + params.
	 */
	private static function where_clause( string $search, string $kind ): array {
		global $wpdb;
		$where  = 'WHERE 1=1';
		$params = array();
		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (placement_label LIKE %s OR short_url LIKE %s OR encoded_url LIKE %s OR slug LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}
		if ( in_array( $kind, array( 'dynamic', 'static' ), true ) ) {
			$where   .= ' AND kind = %s';
			$params[] = $kind;
		}
		return array( $where, $params );
	}

	/**
	 * Insert or update a registry row from API data. Never overwrites a locally-observed post
	 * link with null on a remote-only refresh.
	 *
	 * @param array<string, mixed> $fields Row fields.
	 * @return array<string, mixed> The stored row.
	 */
	public static function upsert( array $fields ): array {
		global $wpdb;
		$now      = current_time( 'mysql', true );
		$existing = self::get_by_code_id( (string) $fields['code_id'] );
		$account  = OpenQR_Settings::account();
		$columns  = array(
			'code_id'         => (string) $fields['code_id'],
			'kind'            => ( 'static' === ( $fields['kind'] ?? 'dynamic' ) ) ? 'static' : 'dynamic',
			'slug'            => (string) ( $fields['slug'] ?? '' ),
			'short_url'       => (string) ( $fields['short_url'] ?? '' ),
			'encoded_url'     => isset( $fields['encoded_url'] ) ? (string) $fields['encoded_url'] : null,
			'payload'         => isset( $fields['payload'] ) ? (string) $fields['payload'] : null,
			'style_hash'      => (string) ( $fields['style_hash'] ?? '' ),
			'style_dark'      => (string) ( $fields['style_dark'] ?? '' ),
			'style_light'     => (string) ( $fields['style_light'] ?? '' ),
			'style_theme'     => (string) ( $fields['style_theme'] ?? '' ),
			'asset_path'      => (string) ( $fields['asset_path'] ?? '' ),
			'post_id'         => isset( $fields['post_id'] ) ? (int) $fields['post_id'] : null,
			'placement_label' => isset( $fields['placement_label'] ) ? (string) $fields['placement_label'] : null,
			'status_mirror'   => (string) ( $fields['status_mirror'] ?? 'active' ),
			'account_id'      => $account['account_id'] ?? '',
			'permalink'       => isset( $fields['post_id'] ) ? (string) get_permalink( (int) $fields['post_id'] ) : null,
		);

		if ( $existing ) {
			if ( empty( $columns['post_id'] ) && ! empty( $existing['post_id'] ) ) {
				$columns['post_id']   = (int) $existing['post_id'];
				$columns['permalink'] = $existing['permalink'];
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update( self::table(), array_merge( $columns, array( 'updated_at' => $now ) ), array( 'id' => $existing['id'] ) );
			return (array) self::get_by_code_id( $columns['code_id'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table(),
			array_merge(
				$columns,
				array(
					'created_at' => $now,
					'updated_at' => $now,
				)
			)
		);
		return (array) self::get_by_code_id( $columns['code_id'] );
	}

	/**
	 * Patch a row.
	 *
	 * @param string               $code_id OpenQR code ID.
	 * @param array<string, mixed> $fields  Columns to set.
	 * @return void
	 */
	public static function update( string $code_id, array $fields ): void {
		global $wpdb;
		$fields['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( self::table(), $fields, array( 'code_id' => $code_id ) );
	}

	/**
	 * Remove a row (the code was deleted).
	 *
	 * @param string $code_id OpenQR code ID.
	 * @return void
	 */
	public static function forget( string $code_id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( self::table(), array( 'code_id' => $code_id ) );
	}

	/**
	 * Drop the table (uninstall opt-in). Assets and options are handled separately.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.Schema, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- uninstall path, constant table name.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::TABLE_OPTION );
	}
}

<?php
/**
 * Transient cache for LISTS and ANALYTICS only. Published QR images are NOT cached here —
 * they are durable files under uploads/ (see OpenQR_Assets); WordPress explicitly warns that
 * transients can vanish before expiry, and a vanished image would break a printed page.
 *
 * Every key is registered in openqr_cache_index so the uninstall sweep works under an external
 * object cache, where transients have no _transient_ DB rows to DELETE.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fresh/stale transient cache with a registry index for sweeping.
 */
final class OpenQR_Cache {

	/**
	 * Option holding live cache keys.
	 */
	const INDEX_OPTION = 'openqr_cache_index';

	/**
	 * Fresh TTL, stale TTL (seconds), per group. Stale copies survive an openqr.uk outage.
	 *
	 * @var array<string, array<int, int>>
	 */
	const TTL = array(
		'codes'  => array( 60, 3600 ),
		'me'     => array( 43200, 604800 ),
		'scans'  => array( 300, 86400 ),
		'themes' => array( 600, 86400 ),
		'status' => array( 86400, 604800 ),
	);

	/**
	 * Results computed this request, so repeated calls hit once.
	 *
	 * @var array<string, mixed>
	 */
	private static $runtime = array();

	/**
	 * Remember a fetch. On upstream failure the stale copy is served by the caller via
	 * stale(); only cacheable values are written.
	 *
	 * @param string   $group One of the TTL keys.
	 * @param string   $key   Distinct key within the group (id, query hash).
	 * @param callable $cb    Returns the value to cache.
	 * @return mixed
	 */
	public static function remember( string $group, string $key, callable $cb ) {
		$cache_key = self::key( $group, $key );
		if ( array_key_exists( $cache_key, self::$runtime ) ) {
			return self::$runtime[ $cache_key ];
		}

		$fresh = get_transient( 'openqr:c:' . $cache_key );
		if ( false !== $fresh ) {
			self::$runtime[ $cache_key ] = $fresh;
			return $fresh;
		}

		$value = $cb();

		if ( self::is_cacheable( $value ) ) {
			$ttls = self::TTL[ $group ] ?? array( 300, 86400 );
			set_transient( 'openqr:c:' . $cache_key, $value, $ttls[0] );
			set_transient( 'openqr:s:' . $cache_key, $value, $ttls[1] );
			self::register( $cache_key );
		}

		self::$runtime[ $cache_key ] = $value;
		return $value;
	}

	/**
	 * Values worth keeping: anything except an unreachable upstream or an auth failure.
	 *
	 * @param mixed $value Produced value.
	 * @return bool
	 */
	private static function is_cacheable( $value ): bool {
		if ( $value instanceof OpenQR_Api_Response ) {
			return $value->is_ok();
		}
		if ( is_wp_error( $value ) ) {
			return false;
		}
		if ( is_array( $value ) && isset( $value['unreachable'] ) && $value['unreachable'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Last known-good value for a group/key, or null.
	 *
	 * @param string $group Cache group.
	 * @param string $key   Key within the group.
	 * @return mixed
	 */
	public static function stale( string $group, string $key ) {
		$cache_key = self::key( $group, $key );
		$stale     = get_transient( 'openqr:s:' . $cache_key );
		return false === $stale ? null : $stale;
	}

	/**
	 * Drop fresh + stale for one entry.
	 *
	 * @param string $group Cache group.
	 * @param string $key   Key within the group.
	 * @return void
	 */
	public static function forget( string $group, string $key ): void {
		$cache_key = self::key( $group, $key );
		delete_transient( 'openqr:c:' . $cache_key );
		delete_transient( 'openqr:s:' . $cache_key );
		unset( self::$runtime[ $cache_key ] );
		self::unregister( $cache_key );
	}

	/**
	 * Called after every successful create/patch/delete: lists are stale by definition.
	 *
	 * @return void
	 */
	public static function flush_codes(): void {
		self::flush_group( 'codes' );
		self::flush_group( 'scans' );
	}

	/**
	 * Drop every cached group.
	 *
	 * @return void
	 */
	public static function flush_all(): void {
		foreach ( array_keys( self::TTL ) as $group ) {
			self::flush_group( $group );
		}
	}

	/**
	 * Drop one group from transients + the index.
	 *
	 * @param string $group Cache group.
	 * @return void
	 */
	private static function flush_group( string $group ): void {
		self::$runtime = array();
		$index         = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			return;
		}
		$kept = array();
		foreach ( $index as $cache_key ) {
			if ( 0 === strpos( (string) $cache_key, $group . ':' ) ) {
				delete_transient( 'openqr:c:' . $cache_key );
				delete_transient( 'openqr:s:' . $cache_key );
			} else {
				$kept[] = $cache_key;
			}
		}
		update_option( self::INDEX_OPTION, $kept, false );
	}

	/**
	 * Internal md5 cache key.
	 *
	 * @param string $group Cache group.
	 * @param string $key   Key.
	 * @return string
	 */
	private static function key( string $group, string $key ): string {
		return $group . ':' . md5( $key );
	}

	/**
	 * Track a live key in the index option.
	 *
	 * @param string $cache_key Grouped md5 key.
	 * @return void
	 */
	private static function register( string $cache_key ): void {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		if ( ! in_array( $cache_key, $index, true ) ) {
			$index[] = $cache_key;
			if ( count( $index ) > 500 ) {
				$index = array_slice( $index, -500 );
			}
			update_option( self::INDEX_OPTION, $index, false );
		}
	}

	/**
	 * Remove one key from the index.
	 *
	 * @param string $cache_key Grouped md5 key.
	 * @return void
	 */
	private static function unregister( string $cache_key ): void {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( is_array( $index ) ) {
			$index = array_values( array_diff( $index, array( $cache_key ) ) );
			update_option( self::INDEX_OPTION, $index, false );
		}
	}
}

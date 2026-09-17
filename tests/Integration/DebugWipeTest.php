<?php

class DebugWipeTest extends OpenQR_TestCase {

	public function test_debug_wipe(): void {
		global $wpdb;
		OpenQR_Registry::upsert(
			array(
				'code_id'     => 'code-dbg',
				'kind'        => 'dynamic',
				'slug'        => 'dbg',
				'short_url'   => 'https://oqr.to/dbg',
				'encoded_url' => 'https://example.com',
			)
		);
		$row = OpenQR_Registry::get_by_code_id( 'code-dbg' );
		fwrite( STDERR, "DBG row: " . ( $row ? 'yes' : 'no' ) . "\n" );
		fwrite( STDERR, "DBG table: " . OpenQR_Registry::table() . "\n" );

		$result = OpenQR_Registry::drop();
		fwrite( STDERR, "DBG drop returned: " . var_export( $result, true ) . "\n" );
		fwrite( STDERR, "DBG last_error: " . $wpdb->last_error . "\n" );

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		fwrite( STDERR, "DBG tables now: " . implode( ',', $tables ) . "\n" );

		$direct = $wpdb->query( 'DROP TABLE IF EXISTS ' . OpenQR_Registry::table() );
		fwrite( STDERR, "DBG direct drop: " . var_export( $direct, true ) . " err=" . $wpdb->last_error . "\n" );
		fwrite( STDERR, "DBG database(): " . var_export( $wpdb->get_var( 'SELECT DATABASE()' ), true ) . "\n" );
		$still = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', OpenQR_Registry::table() ) );
		fwrite( STDERR, "DBG table after direct drop: " . var_export( $still, true ) . "\n" );
		fwrite( STDERR, "DBG db: " . DB_NAME . ' @ ' . DB_HOST . ' prefix=' . $wpdb->prefix . "\n" );

		$after = OpenQR_Registry::get_by_code_id( 'code-dbg' );
		fwrite( STDERR, "DBG after drop, row: " . ( $after ? 'STILL EXISTS' : 'gone' ) . "\n" );
		$count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . OpenQR_Registry::table() );
		fwrite( STDERR, "DBG count err=[" . $wpdb->last_error . "] val=" . var_export( $count, true ) . "\n" );
		$names = $wpdb->get_col( 'SHOW TABLES LIKE \'%openqr%\'' );
		foreach ( (array) $names as $n ) {
			fwrite( STDERR, "DBG found table hex=[" . bin2hex( $n ) . "] len=" . strlen( $n ) . " expected_hex=[" . bin2hex( OpenQR_Registry::table() ) . "] len=" . strlen( OpenQR_Registry::table() ) . "\n" );
		}
		foreach ( (array) $names as $n ) {
			$r = $wpdb->query( "DROP TABLE IF EXISTS `{$n}`" );
			fwrite( STDERR, "DBG drop {$n}: " . var_export( $r, true ) . "\n" );
		}
		$still2 = $wpdb->get_col( 'SHOW TABLES LIKE \'%openqr%\'' );
		fwrite( STDERR, "DBG openqr tables after targeted drop: " . implode( ',', (array) $still2 ) . "\n" );
		$schemas = $wpdb->get_results( "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_NAME LIKE '%openqr%'", ARRAY_A );
		fwrite( STDERR, "DBG information_schema: " . var_export( $schemas, true ) . "\n" );

		// Bypass wpdb entirely, with the same credentials wpdb uses.
		$m = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		var_dump( $m->query( 'DROP TABLE IF EXISTS wp_openqr_codes' ) );
		$res = $m->query( "SELECT COUNT(*) AS c FROM wp_openqr_codes" );
		fwrite( STDERR, "DBG raw-mysqli count after drop err=[" . $m->error . "] val=" . var_export( $res ? $res->fetch_assoc() : null, true ) . "\n" );
		$this->assertTrue( true );
	}
}

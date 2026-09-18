<?php
/**
 * Per-role permission picker: grantable role list, settings round-trip, capability sync and
 * the static-field allowlist that preserves the API's case-sensitive field names.
 *
 * @package OpenQR
 */

/**
 * Role picker + create-field tests.
 */
class RolePermissionsTest extends OpenQR_TestCase {

	public function test_grantable_roles_excludes_administrator_and_contentless_roles(): void {
		$roles = OpenQR_Capabilities::grantable_roles();

		$this->assertArrayHasKey( 'editor', $roles );
		$this->assertArrayHasKey( 'author', $roles );
		$this->assertArrayHasKey( 'contributor', $roles );
		$this->assertArrayNotHasKey( 'administrator', $roles, 'administrator always has the cap; it is not a picker option' );
		$this->assertArrayNotHasKey( 'subscriber', $roles, 'roles that cannot edit content are never offered' );
	}

	public function test_update_all_grants_and_revokes_the_picked_roles(): void {
		OpenQR_Settings::update_all( array( 'manage_codes_roles' => array( 'editor', 'shop_manager' ) ) );

		$this->assertNotNull( get_role( 'editor' )->capabilities[ OpenQR_Capabilities::MANAGE_CODES ] ?? null, 'editor granted' );
		$this->assertArrayNotHasKey( OpenQR_Capabilities::MANAGE_CODES, get_role( 'author' )->capabilities, 'unpicked role untouched' );
		$this->assertSame( array( 'editor' ), OpenQR_Settings::codes_roles(), 'unknown role slugs are dropped' );

		OpenQR_Settings::update_all( array( 'manage_codes_roles' => array( 'author' ) ) );

		$this->assertArrayNotHasKey( OpenQR_Capabilities::MANAGE_CODES, get_role( 'editor' )->capabilities, 'unticking revokes' );
		$this->assertNotNull( get_role( 'author' )->capabilities[ OpenQR_Capabilities::MANAGE_CODES ] ?? null, 'author granted' );
	}

	public function test_update_all_never_touches_administrator_or_the_connection_cap(): void {
		$admin = get_role( 'administrator' );
		$admin->add_cap( OpenQR_Capabilities::MANAGE_CONNECTION );
		$admin->add_cap( OpenQR_Capabilities::MANAGE_CODES );

		OpenQR_Settings::update_all( array( 'manage_codes_roles' => array() ) );

		$this->assertNotNull( $admin->capabilities[ OpenQR_Capabilities::MANAGE_CODES ] ?? null );
		$this->assertNotNull( $admin->capabilities[ OpenQR_Capabilities::MANAGE_CONNECTION ] ?? null );
	}

	public function test_seed_roles_restores_the_stored_picker(): void {
		OpenQR_Settings::update_all( array( 'manage_codes_roles' => array( 'editor' ) ) );
		get_role( 'editor' )->remove_cap( OpenQR_Capabilities::MANAGE_CODES );

		OpenQR_Capabilities::seed_roles();

		$this->assertNotNull( get_role( 'editor' )->capabilities[ OpenQR_Capabilities::MANAGE_CODES ] ?? null, 'late role changes are re-applied after connecting' );
		$this->assertArrayNotHasKey( OpenQR_Capabilities::MANAGE_CODES, get_role( 'author' )->capabilities );
	}

	public function test_collect_static_fields_keeps_api_field_names_exact(): void {
		$fields = OpenQR_Admin_Create::collect_static_fields(
			'vcard',
			array(
				'firstName'                => 'Jane',
				'lastName'                 => 'Doe',
				'organisation_not_defined' => 'dropped',
				'email'                    => '',
			)
		);

		$this->assertSame(
			array(
				'firstName' => 'Jane',
				'lastName'  => 'Doe',
			),
			$fields,
			'defined keys pass verbatim; unknown and empty fields are dropped'
		);
	}

	public function test_collect_static_fields_keeps_message_newlines(): void {
		$fields = OpenQR_Admin_Create::collect_static_fields(
			'whatsapp',
			array(
				'phone'   => '+44 7000 000000',
				'message' => "Line one\nLine two",
			)
		);

		$this->assertSame( '+44 7000 000000', $fields['phone'], 'digit-stripping is the API builder job, not the form' );
		$this->assertSame( "Line one\nLine two", $fields['message'], 'textarea fields must not lose newlines' );
	}
}

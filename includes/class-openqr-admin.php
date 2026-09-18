<?php
/**
 * Admin menu, shared assets, reconnect banner.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin chrome.
 */
final class OpenQR_Admin {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'reconnect_banner' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OPENQR_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_post_openqr_create_dynamic', array( 'OpenQR_Admin_Create', 'handle_create_dynamic' ) );
		add_action( 'admin_post_openqr_create_static', array( 'OpenQR_Admin_Create', 'handle_create_static' ) );
	}

	/**
	 * The admin menu tree. Non-connection managers get the codes screens only.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_menu_page(
			__( 'OpenQR', 'openqr' ),
			__( 'OpenQR', 'openqr' ),
			'openqr_manage_codes',
			'openqr',
			array( 'OpenQR_Admin_Dashboard', 'render' ),
			'dashicons-grid-view',
			58
		);
		$pages = array(
			array( __( 'Dashboard', 'openqr' ), __( 'Dashboard', 'openqr' ), 'openqr_manage_codes', 'openqr', array( 'OpenQR_Admin_Dashboard', 'render' ) ),
			array( __( 'QR Codes', 'openqr' ), __( 'QR Codes', 'openqr' ), 'openqr_manage_codes', 'openqr-codes', array( 'OpenQR_Admin_Codes', 'render' ) ),
			array( __( 'Create QR', 'openqr' ), __( 'Create QR', 'openqr' ), 'openqr_manage_codes', 'openqr-new', array( 'OpenQR_Admin_Create', 'render' ) ),
			array( __( 'Activity', 'openqr' ), __( 'Activity', 'openqr' ), 'openqr_manage_codes', 'openqr-analytics', array( 'OpenQR_Admin_Analytics', 'render' ) ),
			array( __( 'Settings', 'openqr' ), __( 'Settings', 'openqr' ), 'openqr_manage_connection', 'openqr-settings', array( 'OpenQR_Admin_Settings', 'render' ) ),
		);
		foreach ( $pages as $page ) {
			add_submenu_page( 'openqr', $page[0], $page[1], $page[2], $page[3], $page[4] );
		}
	}

	/**
	 * Enqueue admin assets, scoped to our screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function assets( string $hook ): void {
		$is_ours = false !== strpos( (string) $hook, 'openqr' );
		if ( $is_ours ) {
			wp_enqueue_style( 'openqr-admin', OPENQR_URL . 'build/admin.css', array(), OPENQR_VERSION );
			wp_enqueue_script( 'openqr-admin', OPENQR_URL . 'build/admin.js', array( 'wp-api-fetch' ), OPENQR_VERSION, true );
		}
	}

	/**
	 * Reconnect banner: shown on every admin screen while the last API call hit a 401.
	 *
	 * @return void
	 */
	public static function reconnect_banner(): void {
		if ( ! OpenQR_Settings::auth_failed_at() || ! OpenQR_Capabilities::can_manage_connection() ) {
			return;
		}
		// Clear stale flags older than 30 days: the account may have been fixed elsewhere.
		if ( OpenQR_Settings::auth_failed_at() < time() - 30 * DAY_IN_SECONDS ) {
			OpenQR_Settings::clear_auth_failed();
			return;
		}
		printf(
			'<div class="notice notice-warning openqr-reconnect-banner"><p><strong>%1$s</strong> %2$s <a href="%3$s" class="button button-small" style="margin-left:8px">%4$s</a></p></div>',
			esc_html__( 'OpenQR:', 'openqr' ),
			esc_html__( 'your connection was rejected. Existing codes and downloads keep working, but you need to reconnect to create or edit codes.', 'openqr' ),
			esc_url( admin_url( 'admin.php?page=openqr-settings' ) ),
			esc_html__( 'Reconnect', 'openqr' )
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array<int, string> $links Existing links.
	 * @return array<int, string>
	 */
	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=openqr-settings' ) ), esc_html__( 'Settings', 'openqr' ) )
		);
		return $links;
	}

	/**
	 * Branded page header shared by every OpenQR screen: logo, title, lede, right-aligned
	 * actions. Renders the screen's single h1.
	 *
	 * @param string                           $title   Page title.
	 * @param string                           $lede    One line under the title.
	 * @param array<int, array<string, mixed>> $actions Actions: url, label, primary, external.
	 * @return void
	 */
	public static function hero( string $title, string $lede = '', array $actions = array() ): void {
		echo '<div class="openqr-hero"><div class="openqr-hero-main">';
		echo '<h1 class="openqr-hero-title">' . self::logo( 28 ) . esc_html( $title ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG asset URL
		if ( '' !== $lede ) {
			echo '<p class="openqr-hero-lede">' . esc_html( $lede ) . '</p>';
		}
		echo '</div>';
		if ( $actions ) {
			echo '<div class="openqr-hero-actions">';
			foreach ( $actions as $action ) {
				$external = ! empty( $action['external'] );
				printf(
					'<a class="button%1$s" href="%2$s"%3$s>%4$s%5$s</a>',
					! empty( $action['primary'] ) ? ' button-primary button-hero' : ' button-hero',
					esc_url( (string) $action['url'] ),
					$external ? ' target="_blank" rel="noopener"' : '',
					esc_html( (string) $action['label'] ),
					$external ? ' <span aria-hidden="true">&#8599;</span>' : ''
				);
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * URL helpers shared by screens.
	 *
	 * @param array<string, mixed> $args Extra query args.
	 * @return string
	 */
	public static function codes_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => 'openqr-codes' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Create screen URL.
	 *
	 * @param array<string, mixed> $args Extra query args.
	 * @return string
	 */
	public static function create_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => 'openqr-new' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Analytics screen URL.
	 *
	 * @param array<string, mixed> $args Extra query args.
	 * @return string
	 */
	public static function analytics_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => 'openqr-analytics' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Settings screen URL.
	 *
	 * @return string
	 */
	public static function settings_url(): string {
		return admin_url( 'admin.php?page=openqr-settings' );
	}

	/**
	 * The brand mark, inlined into the page: the wp.org repo does not permit SVG files, and
	 * inline markup also means the admin never makes a request for the asset.
	 *
	 * @param int $size Pixel size.
	 * @return string
	 */
	public static function logo( int $size = 20 ): string {
		static $svg = null;
		if ( null === $svg ) {
			$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" role="img" aria-hidden="true" focusable="false" class="openqr-logo" %1$s>
  <g fill="none" stroke="#07B1B0" stroke-width="20" stroke-linecap="round" stroke-linejoin="round">
    <path d="M24,86 L24,58 Q24,24 58,24 L86,24"/>
    <path d="M154,24 L182,24 Q216,24 216,58 L216,86"/>
    <path d="M216,154 L216,182 Q216,216 182,216 L154,216"/>
    <path d="M86,216 L58,216 Q24,216 24,182 L24,154"/>
  </g>
  <g fill="#232E3A">
    <rect x="69" y="69" width="36" height="36" rx="5" fill="none" stroke="#232E3A" stroke-width="10"/>
    <rect x="79" y="79" width="16" height="16" rx="2.5"/>
    <rect x="135" y="69" width="36" height="36" rx="5" fill="none" stroke="#232E3A" stroke-width="10"/>
    <rect x="145" y="79" width="16" height="16" rx="2.5"/>
    <rect x="69" y="135" width="36" height="36" rx="5" fill="none" stroke="#232E3A" stroke-width="10"/>
    <rect x="79" y="145" width="16" height="16" rx="2.5"/>
    <rect x="130" y="130" width="16" height="16" rx="2.5"/>
    <rect x="160" y="130" width="16" height="16" rx="2.5"/>
    <rect x="145" y="145" width="16" height="16" rx="2.5"/>
    <rect x="130" y="160" width="16" height="16" rx="2.5"/>
    <rect x="160" y="160" width="16" height="16" rx="2.5"/>
  </g>
</svg>
SVG;
		}
		return sprintf( $svg, sprintf( 'width="%d" height="%d"', $size, $size ) );
	}
}

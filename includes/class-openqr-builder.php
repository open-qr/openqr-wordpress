<?php
/**
 * Page-builder integrations loader. Everything behavioural lives in OpenQR_Embed + OpenQR_Payload;
 * this file only detects which builder is present and hands it the widget classes.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detect Elementor / Fusion Builder and register the OpenQR surfaces.
 */
final class OpenQR_Builder {

	/**
	 * Register the detection hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Late on plugins_loaded: every plugin (Elementor included) has loaded by then, so
		// did_action('elementor/loaded') is answerable. The builder's own registration hooks
		// fire later still, so hooking them here is in time.
		add_action( 'plugins_loaded', array( __CLASS__, 'detect' ), 20 );
	}

	/**
	 * Hook into whichever builder is present. Neither is required; both are optional.
	 *
	 * @return void
	 */
	public static function detect(): void {
		if ( did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
			add_action( 'elementor/widgets/register', array( __CLASS__, 'widget' ) );
		}
		if ( class_exists( 'FusionBuilder' ) ) {
			add_action( 'fusion_builder_before_init', array( __CLASS__, 'fusion' ) );
		}
	}

	/**
	 * The OpenQR category in the Elementor panel.
	 *
	 * @param object $elements_manager Elementor elements manager.
	 * @return void
	 */
	public static function category( $elements_manager ): void {
		$elements_manager->add_category(
			'openqr',
			array( 'title' => __( 'OpenQR', 'openqr' ) )
		);
	}

	/**
	 * Register the Elementor widget.
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function widget( $widgets_manager ): void {
		$widgets_manager->register( new OpenQR_Elementor_Widget() );
	}

	/**
	 * Register the Fusion Builder element + its render shortcode.
	 *
	 * @return void
	 */
	public static function fusion(): void {
		require_once __DIR__ . '/class-openqr-fusion-element.php';
		OpenQR_Fusion_Element::register();
	}
}

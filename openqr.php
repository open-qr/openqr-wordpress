<?php
/**
 * Plugin Name: QR Code Generator: Dynamic QR Codes for Print
 * Plugin URI:  https://github.com/open-qr/openqr-wordpress
 * Description: Create and print QR codes for your pages, posts and products. Dynamic codes stay editable after printing: change the destination any time. Scan analytics included.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      OpenQR
 * Author URI:  https://openqr.uk
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: openqr
 *
 * Requires a free OpenQR account (https://openqr.uk/api) and connects to openqr.uk over HTTPS
 * using an API key you create and store yourself. Nothing is sent to openqr.uk until you connect.
 *
 * @package OpenQR
 */

defined( 'ABSPATH' ) || exit;

define( 'OPENQR_VERSION', '1.0.0' );
define( 'OPENQR_FILE', __FILE__ );
define( 'OPENQR_DIR', __DIR__ );
define( 'OPENQR_URL', plugin_dir_url( __FILE__ ) );

require_once OPENQR_DIR . '/includes/class-autoloader.php';

register_activation_hook( __FILE__, array( 'OpenQR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OpenQR_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'OpenQR_Plugin', 'boot' ) );

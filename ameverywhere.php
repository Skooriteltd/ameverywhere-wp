<?php
/**
 * Plugin Name: AmEveryWhere
 * Plugin URI: https://ameverywhere.com
 * Description: AmEveryWhere is a next-generation WordPress SEO and AEO plugin engineered for the AI-first search era.
 * Version: 1.0.0
 * Author: AmEveryWhere Team
 * Author URI: https://ameverywhere.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ameverywhere
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * @package AmEveryWhere
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
if ( ! defined( 'AMEVERYWHERE_VERSION' ) ) {
	define( 'AMEVERYWHERE_VERSION', '1.0.0' );
}
if ( ! defined( 'AMEVERYWHERE_PLUGIN_FILE' ) ) {
	define( 'AMEVERYWHERE_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'AMEVERYWHERE_PLUGIN_DIR' ) ) {
	define( 'AMEVERYWHERE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'AMEVERYWHERE_PLUGIN_URL' ) ) {
	define( 'AMEVERYWHERE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}


// Defensive PHP version guard.
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'AmEveryWhere', 'ameverywhere' ),
				sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
					esc_html__( 'AmEveryWhere requires PHP version %1$s or higher. Your server is running PHP %2$s. Please upgrade your PHP version.', 'ameverywhere' ),
					'8.2',
					esc_html( PHP_VERSION )
				)
			);
		}
	);
	return;
}

// Require the Composer autoloader.
if ( file_exists( AMEVERYWHERE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once AMEVERYWHERE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// Show an admin notice if composer is not installed.
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'AmEveryWhere', 'ameverywhere' ),
				wp_kses(
					__( 'Please run <code>composer install</code> in the plugin directory.', 'ameverywhere' ),
					array( 'code' => array() )
				)
			);
		}
	);
	return;
}

// Initialize the plugin.
function ameverywhere_init() {
	$plugin = \AmEveryWhere\Plugin::getInstance();
	$plugin->boot();
}

add_action( 'plugins_loaded', 'ameverywhere_init' );

// Global template helper for themes to render breadcrumbs.
if ( ! function_exists( 'ameverywhere_breadcrumbs' ) ) {
	function ameverywhere_breadcrumbs(): void {
		$renderer = new \AmEveryWhere\Modules\Breadcrumbs\BreadcrumbRenderer();
		echo $renderer->render();
	}
}

// Activation and Deactivation hooks.
register_activation_hook( __FILE__, array( \AmEveryWhere\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \AmEveryWhere\Plugin::class, 'deactivate' ) );

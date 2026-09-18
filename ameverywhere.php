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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('AMEVERYWHERE_VERSION', '1.0.0');
define('AMEVERYWHERE_PLUGIN_FILE', __FILE__);
define('AMEVERYWHERE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMEVERYWHERE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Backward compatibility constants for legacy add-ons or integrations.
if (!defined('RANKSAVVY_VERSION')) {
    define('RANKSAVVY_VERSION', AMEVERYWHERE_VERSION);
}
if (!defined('RANKSAVVY_PLUGIN_FILE')) {
    define('RANKSAVVY_PLUGIN_FILE', AMEVERYWHERE_PLUGIN_FILE);
}
if (!defined('RANKSAVVY_PLUGIN_DIR')) {
    define('RANKSAVVY_PLUGIN_DIR', AMEVERYWHERE_PLUGIN_DIR);
}
if (!defined('RANKSAVVY_PLUGIN_URL')) {
    define('RANKSAVVY_PLUGIN_URL', AMEVERYWHERE_PLUGIN_URL);
}

// Require the Composer autoloader.
if (file_exists(AMEVERYWHERE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once AMEVERYWHERE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Show an admin notice if composer is not installed.
    add_action('admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
            esc_html__('AmEveryWhere', 'ameverywhere'),
            wp_kses(
                __('Please run <code>composer install</code> in the plugin directory.', 'ameverywhere'),
                ['code' => []]
            )
        );
    });
    return;
}

// Initialize the plugin.
function ameverywhere_init() {
    $plugin = \AmEveryWhere\Plugin::getInstance();
    $plugin->boot();
}

// Legacy init function alias for backward compatibility.
if (!function_exists('ranksavvy_init')) {
    function ranksavvy_init() {
        ameverywhere_init();
    }
}

add_action('plugins_loaded', 'ameverywhere_init');

// Activation and Deactivation hooks.
register_activation_hook(__FILE__, [\AmEveryWhere\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\AmEveryWhere\Plugin::class, 'deactivate']);

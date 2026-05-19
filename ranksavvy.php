<?php
/**
 * Plugin Name: RankSavvy
 * Plugin URI: https://ranksavvy.com
 * Description: RankSavvy is a next-generation WordPress SEO and AEO plugin engineered for the AI-first search era.
 * Version: 1.0.0
 * Author: RankSavvy Team
 * Author URI: https://ranksavvy.com
 * License: GPL-3.0-or-later
 * Text Domain: ranksavvy
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * @package RankSavvy
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('RANKSAVVY_VERSION', '1.0.0');
define('RANKSAVVY_PLUGIN_FILE', __FILE__);
define('RANKSAVVY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RANKSAVVY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require the Composer autoloader.
if (file_exists(RANKSAVVY_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once RANKSAVVY_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Show an admin notice if composer is not installed.
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>RankSavvy:</strong> Please run <code>composer install</code> in the plugin directory.</p></div>';
    });
    return;
}

// Initialize the plugin.
function ranksavvy_init() {
    $plugin = \RankSavvy\Plugin::getInstance();
    $plugin->boot();
}

add_action('plugins_loaded', 'ranksavvy_init');

// Activation and Deactivation hooks.
register_activation_hook(__FILE__, ['\\RankSavvy\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\\RankSavvy\\Plugin', 'deactivate']);

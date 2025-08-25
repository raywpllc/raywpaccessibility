<?php
/**
 * Plugin Name: RayWP Accessibility Pro
 * Plugin URI: https://raywp.com
 * Description: Advanced accessibility toolkit with comprehensive ARIA support, form scanning, and WCAG compliance features.
 * Version: 1.0.0
 * Author: Adam Rosenkoetter
 * Author URI: https://raywp.com
 * License: GPL v2 or later
 * Text Domain: raywp-accessibility
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RAYWP_ACCESSIBILITY_VERSION', '1.0.0');
define('RAYWP_ACCESSIBILITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RAYWP_ACCESSIBILITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAYWP_ACCESSIBILITY_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the autoloader
require_once RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-autoloader.php';

// Initialize the autoloader
\RayWP\Accessibility\Autoloader::init();

// Initialize the plugin
add_action('plugins_loaded', function() {
    \RayWP\Accessibility\Core\Plugin::get_instance();
});

// Activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    \RayWP\Accessibility\Core\Activator::activate();
});

register_deactivation_hook(__FILE__, function() {
    \RayWP\Accessibility\Core\Deactivator::deactivate();
});
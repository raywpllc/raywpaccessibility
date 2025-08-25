<?php
/**
 * Settings Manager
 */

namespace RayWP\Accessibility\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Settings will be handled by Admin class
    }
    
    /**
     * Get setting
     */
    public function get($key, $default = null) {
        $settings = get_option('raywp_accessibility_settings', []);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update setting
     */
    public function update($key, $value) {
        $settings = get_option('raywp_accessibility_settings', []);
        $settings[$key] = $value;
        update_option('raywp_accessibility_settings', $settings);
    }
}
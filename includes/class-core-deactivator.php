<?php
/**
 * Plugin Deactivator
 */

namespace RayWP\Accessibility\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('raywp_accessibility_daily_scan');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
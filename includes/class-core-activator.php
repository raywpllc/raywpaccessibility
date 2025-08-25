<?php
/**
 * Plugin Activator
 */

namespace RayWP\Accessibility\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Create default options
        $default_settings = [
            'enable_aria' => 1,
            'enable_checker' => 0, // Widget disabled - use admin scanner instead
            'fix_empty_alt' => 1,
            'fix_lang_attr' => 1,
            'fix_form_labels' => 1,
            'add_skip_links' => 1,
            'fix_forms' => 1,
            'add_main_landmark' => 1,
            'fix_heading_hierarchy' => 1,
            'enhance_focus' => 1,
            'focus_outline_color' => '#0073aa',
            'focus_outline_width' => '2px',
            'fix_contrast' => 0, // Disabled by default - can override theme styles
            'enable_color_overrides' => 0 // Custom color overrides for advanced users
        ];
        
        // Only add options if they don't exist
        if (!get_option('raywp_accessibility_settings')) {
            add_option('raywp_accessibility_settings', $default_settings);
        }
        
        if (!get_option('raywp_accessibility_aria_rules')) {
            add_option('raywp_accessibility_aria_rules', []);
        }
        
        if (!get_option('raywp_accessibility_color_overrides')) {
            add_option('raywp_accessibility_color_overrides', []);
        }
        
        if (!get_option('raywp_accessibility_version')) {
            add_option('raywp_accessibility_version', RAYWP_ACCESSIBILITY_VERSION);
        }
        
        // Create database tables if needed
        self::create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for scan results
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_date datetime DEFAULT CURRENT_TIMESTAMP,
            page_url varchar(255) NOT NULL,
            issue_type varchar(100) NOT NULL,
            issue_severity varchar(20) NOT NULL,
            issue_description text NOT NULL,
            element_selector varchar(255),
            fixed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY page_url (page_url),
            KEY issue_type (issue_type),
            KEY scan_date (scan_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
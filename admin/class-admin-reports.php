<?php
/**
 * Reports Manager
 */

namespace RayWP\Accessibility\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Reports {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize reports functionality
    }
    
    /**
     * Get scan results
     */
    public function get_scan_results($limit = 100) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY scan_date DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Get issue summary
     */
    public function get_issue_summary() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return []; // Table doesn't exist yet
        }
        
        return $wpdb->get_results(
            "SELECT issue_type, issue_severity, COUNT(*) as count 
             FROM $table_name 
             WHERE fixed = 0 
             GROUP BY issue_type, issue_severity"
        );
    }
    
    /**
     * Calculate accessibility score
     */
    public function calculate_accessibility_score() {
        $summary = $this->get_issue_summary();
        
        // Check if we have any scan data at all
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return null; // Table doesn't exist yet
        }
        
        $has_data = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($has_data == 0) {
            return null; // No scans performed yet
        }
        
        if (empty($summary)) {
            return 100; // No issues found
        }
        
        $total_weight = 0;
        $severity_weights = [
            'critical' => 10,
            'high' => 5,
            'medium' => 3,
            'low' => 1
        ];
        
        foreach ($summary as $issue) {
            $weight = $severity_weights[$issue->issue_severity] ?? 1;
            $total_weight += $weight * $issue->count;
        }
        
        // Calculate score (100 - penalties, minimum 0)
        $score = max(0, 100 - $total_weight);
        
        return $score;
    }
}
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
     * Get last scan date
     */
    public function get_last_scan_date() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';

        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return null;
        }

        return $wpdb->get_var("SELECT MAX(scan_date) FROM $table_name");
    }

    /**
     * Calculate compliance assessment
     */
    public function calculate_compliance_assessment() {
        $score = $this->calculate_accessibility_score();

        if ($score === null) {
            return null;
        }

        if ($score >= 90) {
            return [
                'level' => 'excellent',
                'label' => 'Excellent',
                'description' => 'Your site meets high accessibility standards.'
            ];
        } elseif ($score >= 70) {
            return [
                'level' => 'good',
                'label' => 'Good',
                'description' => 'Your site has good accessibility with some areas for improvement.'
            ];
        } elseif ($score >= 50) {
            return [
                'level' => 'needs-work',
                'label' => 'Needs Work',
                'description' => 'Your site has accessibility issues that should be addressed.'
            ];
        } else {
            return [
                'level' => 'poor',
                'label' => 'Poor',
                'description' => 'Your site has significant accessibility issues requiring attention.'
            ];
        }
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
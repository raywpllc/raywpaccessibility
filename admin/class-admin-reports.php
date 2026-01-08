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
        $this->ensure_database_table();
    }

    /**
     * Ensure database table exists with all required columns
     */
    public function ensure_database_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

        if (!$table_exists) {
            // Create table with all columns
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                page_url varchar(500) NOT NULL DEFAULT '',
                issue_type varchar(100) NOT NULL DEFAULT '',
                issue_severity varchar(50) NOT NULL DEFAULT 'medium',
                issue_description text,
                element_selector text,
                wcag_criteria varchar(50) DEFAULT '',
                wcag_reference varchar(50) DEFAULT '',
                wcag_level varchar(10) DEFAULT '',
                auto_fixable tinyint(1) DEFAULT 0,
                page_type varchar(50) DEFAULT '',
                scan_session_id varchar(100) DEFAULT '',
                session_id varchar(100) DEFAULT '',
                wcag_criterion varchar(50) DEFAULT '',
                compliance_impact varchar(50) DEFAULT '',
                confidence_level varchar(20) DEFAULT '',
                fixed tinyint(1) DEFAULT 0,
                scan_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY page_url (page_url(191)),
                KEY issue_type (issue_type),
                KEY issue_severity (issue_severity),
                KEY scan_date (scan_date),
                KEY session_id (session_id(50))
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        } else {
            // Table exists - add any missing columns
            $columns = $wpdb->get_col("DESCRIBE $table_name", 0);

            $new_columns = [
                'wcag_criteria' => "ALTER TABLE $table_name ADD COLUMN wcag_criteria varchar(50) DEFAULT ''",
                'wcag_reference' => "ALTER TABLE $table_name ADD COLUMN wcag_reference varchar(50) DEFAULT ''",
                'wcag_level' => "ALTER TABLE $table_name ADD COLUMN wcag_level varchar(10) DEFAULT ''",
                'auto_fixable' => "ALTER TABLE $table_name ADD COLUMN auto_fixable tinyint(1) DEFAULT 0",
                'page_type' => "ALTER TABLE $table_name ADD COLUMN page_type varchar(50) DEFAULT ''",
                'scan_session_id' => "ALTER TABLE $table_name ADD COLUMN scan_session_id varchar(100) DEFAULT ''",
                'session_id' => "ALTER TABLE $table_name ADD COLUMN session_id varchar(100) DEFAULT ''",
                'wcag_criterion' => "ALTER TABLE $table_name ADD COLUMN wcag_criterion varchar(50) DEFAULT ''",
                'compliance_impact' => "ALTER TABLE $table_name ADD COLUMN compliance_impact varchar(50) DEFAULT ''",
                'confidence_level' => "ALTER TABLE $table_name ADD COLUMN confidence_level varchar(20) DEFAULT ''",
            ];

            foreach ($new_columns as $column => $sql) {
                if (!in_array($column, $columns)) {
                    $wpdb->query($sql);
                }
            }
        }

        // Update version option
        update_option('raywp_accessibility_db_version', '1.0.4');
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

    /**
     * Get detailed manual issues
     */
    public function get_detailed_manual_issues() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT * FROM $table_name WHERE fixed = 0 ORDER BY issue_severity DESC, scan_date DESC LIMIT 100"
        );
    }

    /**
     * Get WCAG compliance breakdown
     */
    public function get_wcag_compliance_breakdown() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return [];
        }

        // Group issues by WCAG criteria if available
        $results = $wpdb->get_results(
            "SELECT
                COALESCE(wcag_criteria, 'uncategorized') as criteria,
                COUNT(*) as total,
                SUM(CASE WHEN fixed = 1 THEN 1 ELSE 0 END) as fixed_count
             FROM $table_name
             GROUP BY wcag_criteria"
        );

        return $results ?: [];
    }

    /**
     * Get scan sessions
     */
    public function get_scan_sessions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT DISTINCT
                DATE(scan_date) as session_date,
                COUNT(*) as issue_count,
                MIN(scan_date) as start_time,
                MAX(scan_date) as end_time
             FROM $table_name
             GROUP BY DATE(scan_date)
             ORDER BY session_date DESC
             LIMIT 10"
        );
    }

    /**
     * Get filtered results
     */
    public function get_filtered_results($filters = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return [];
        }

        $where_clauses = ['1=1'];
        $params = [];

        if (!empty($filters['severity'])) {
            $where_clauses[] = 'issue_severity = %s';
            $params[] = $filters['severity'];
        }

        if (!empty($filters['type'])) {
            $where_clauses[] = 'issue_type = %s';
            $params[] = $filters['type'];
        }

        if (!empty($filters['page_url'])) {
            $where_clauses[] = 'page_url LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['page_url']) . '%';
        }

        if (isset($filters['fixed'])) {
            $where_clauses[] = 'fixed = %d';
            $params[] = (int) $filters['fixed'];
        }

        $where_sql = implode(' AND ', $where_clauses);
        $query = "SELECT * FROM $table_name WHERE $where_sql ORDER BY scan_date DESC LIMIT 200";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Export results to CSV
     */
    public function export_results_csv($filters = []) {
        $results = $this->get_filtered_results($filters);

        if (empty($results)) {
            return ['success' => false, 'message' => 'No results to export'];
        }

        $upload_dir = wp_upload_dir();
        $filename = 'accessibility-report-' . date('Y-m-d-His') . '.csv';
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $fp = fopen($file_path, 'w');
        if (!$fp) {
            return ['success' => false, 'message' => 'Could not create file'];
        }

        // CSV headers
        fputcsv($fp, ['Page URL', 'Issue Type', 'Severity', 'Description', 'Element', 'Fixed', 'Scan Date']);

        foreach ($results as $row) {
            fputcsv($fp, [
                $row->page_url ?? '',
                $row->issue_type ?? '',
                $row->issue_severity ?? '',
                $row->issue_description ?? '',
                $row->element_selector ?? '',
                $row->fixed ? 'Yes' : 'No',
                $row->scan_date ?? ''
            ]);
        }

        fclose($fp);

        return [
            'success' => true,
            'filename' => $filename,
            'file_path' => $file_path,
            'file_url' => $upload_dir['baseurl'] . '/' . $filename
        ];
    }

    /**
     * Save scan results
     */
    public function save_scan_results($scan_data, $session_id = null, $clear_previous = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';

        // Clear previous results if requested
        if ($clear_previous && $session_id) {
            $wpdb->delete($table_name, ['session_id' => $session_id]);
        }

        if (empty($scan_data) || !is_array($scan_data)) {
            return false;
        }

        $inserted = 0;
        foreach ($scan_data as $issue) {
            $result = $wpdb->insert(
                $table_name,
                [
                    'page_url' => sanitize_url($issue['page_url'] ?? ''),
                    'issue_type' => sanitize_text_field($issue['issue_type'] ?? ''),
                    'issue_severity' => sanitize_text_field($issue['issue_severity'] ?? 'medium'),
                    'issue_description' => sanitize_text_field($issue['issue_description'] ?? ''),
                    'element_selector' => sanitize_text_field($issue['element_selector'] ?? ''),
                    'wcag_criteria' => sanitize_text_field($issue['wcag_criteria'] ?? ''),
                    'session_id' => sanitize_text_field($session_id ?? ''),
                    'fixed' => 0,
                    'scan_date' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );

            if ($result) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Clear previous scan results
     */
    public function clear_previous_scan_results($session_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';

        if ($session_id) {
            return $wpdb->delete($table_name, ['session_id' => $session_id]);
        }

        return $wpdb->query("TRUNCATE TABLE $table_name");
    }
}
<?php
/**
 * Main plugin class
 */

namespace RayWP\Accessibility\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    private $components = [];
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->define_hooks();
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Load trait for ARIA validation
        require_once RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'includes/trait-aria-validator.php';
        
        // Load interfaces
        require_once RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'includes/interface-component.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Core components
        $this->components['settings'] = new \RayWP\Accessibility\Admin\Settings();
        $this->components['aria_manager'] = new \RayWP\Accessibility\Core\Aria_Manager();
        $this->components['form_scanner'] = new \RayWP\Accessibility\Core\Form_Scanner();
        
        // Always create accessibility checker (needed for reports)
        $this->components['accessibility_checker'] = new \RayWP\Accessibility\Frontend\Accessibility_Checker();
        
        // Admin components
        if (is_admin()) {
            $this->components['admin'] = new \RayWP\Accessibility\Admin\Admin();
            $this->components['reports'] = new \RayWP\Accessibility\Admin\Reports();
        }
        
        // Frontend components
        if (!is_admin()) {
            $this->components['frontend'] = new \RayWP\Accessibility\Frontend\Frontend();
            $this->components['dom_processor'] = new \RayWP\Accessibility\Frontend\Dom_Processor();
            
            // Inject ARIA manager to avoid circular dependency
            if (isset($this->components['aria_manager'])) {
                $this->components['dom_processor']->set_aria_manager($this->components['aria_manager']);
            }
        }
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        // Text domain
        add_action('init', [$this, 'load_textdomain']);
        
        // AJAX handlers
        add_action('wp_ajax_raywp_accessibility_validate_selector', [$this, 'ajax_validate_selector']);
        add_action('wp_ajax_raywp_accessibility_scan_forms', [$this, 'ajax_scan_forms']);
        add_action('wp_ajax_raywp_accessibility_add_aria_rule', [$this, 'ajax_add_aria_rule']);
        add_action('wp_ajax_raywp_accessibility_delete_aria_rule', [$this, 'ajax_delete_aria_rule']);
        add_action('wp_ajax_raywp_accessibility_fix_form', [$this, 'ajax_fix_form']);
        add_action('wp_ajax_raywp_accessibility_run_full_scan', [$this, 'ajax_run_full_scan']);
        add_action('wp_ajax_raywp_accessibility_enable_all_fixes', [$this, 'ajax_enable_all_fixes']);
        add_action('wp_ajax_raywp_accessibility_toggle_checker_widget', [$this, 'ajax_toggle_checker_widget']);
        add_action('wp_ajax_raywp_accessibility_add_color_override', [$this, 'ajax_add_color_override']);
        add_action('wp_ajax_raywp_accessibility_delete_color_override', [$this, 'ajax_delete_color_override']);
        
        // Process entire page output for better ARIA injection
        add_action('template_redirect', [$this, 'start_output_buffering'], 0);
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('raywp-accessibility', false, dirname(RAYWP_ACCESSIBILITY_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Start output buffering to process entire page
     */
    public function start_output_buffering() {
        if (!is_admin() && !wp_doing_ajax()) {
            ob_start([$this->components['dom_processor'], 'process_output']);
        }
    }
    
    /**
     * AJAX handler for selector validation
     */
    public function ajax_validate_selector() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        $selector = sanitize_text_field($_POST['selector'] ?? '');
        $is_valid = $this->components['aria_manager']->validate_css_selector($selector);
        
        wp_send_json_success(['valid' => $is_valid]);
    }
    
    /**
     * AJAX handler for form scanning
     */
    public function ajax_scan_forms() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $results = $this->components['form_scanner']->scan_all_forms();
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for adding ARIA rule
     */
    public function ajax_add_aria_rule() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $selector = sanitize_text_field($_POST['selector'] ?? '');
        $attribute = sanitize_text_field($_POST['attribute'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');
        
        if (empty($selector) || empty($attribute) || $value === '') {
            wp_send_json_error('All fields are required');
        }
        
        $rule = [
            'selector' => $selector,
            'attribute' => $attribute,
            'value' => $value
        ];
        
        $current_rules = $this->components['aria_manager']->get_aria_rules();
        $current_rules[] = $rule;
        
        $saved_count = $this->components['aria_manager']->save_aria_rules($current_rules);
        
        if ($saved_count > 0) {
            wp_send_json_success(['message' => 'ARIA rule added successfully']);
        } else {
            wp_send_json_error('Failed to add ARIA rule. Please check your inputs.');
        }
    }
    
    /**
     * AJAX handler for deleting ARIA rule
     */
    public function ajax_delete_aria_rule() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $index = intval($_POST['index'] ?? -1);
        
        if ($this->components['aria_manager']->remove_aria_rule($index)) {
            wp_send_json_success(['message' => 'ARIA rule deleted successfully']);
        } else {
            wp_send_json_error('Failed to delete ARIA rule');
        }
    }
    
    /**
     * AJAX handler for fixing forms
     */
    public function ajax_fix_form() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $plugin = sanitize_text_field($_POST['plugin'] ?? '');
        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        
        if (empty($plugin) || empty($form_id)) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Apply common fixes
        $fixes = ['add_labels', 'add_aria_required', 'add_fieldsets'];
        
        $result = $this->components['form_scanner']->apply_form_fixes($plugin, $form_id, $fixes);
        
        if ($result) {
            wp_send_json_success(['message' => 'Fixes applied successfully']);
        } else {
            wp_send_json_error('Failed to apply fixes');
        }
    }
    
    /**
     * AJAX handler for running full site scan
     */
    public function ajax_run_full_scan() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Extend execution time for larger scans
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '256M');
        
        // Get accessibility checker component
        $checker = $this->components['accessibility_checker'];
        
        // Get a list of pages to scan
        $pages_to_scan = $this->get_pages_for_scanning();
        $results = [];
        $total_issues = 0;
        $errors = [];
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RayWP Accessibility: Starting scan of ' . count($pages_to_scan) . ' pages');
        }
        
        foreach ($pages_to_scan as $page) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Scanning ' . $page['url']);
            }
            
            $report = $checker->generate_report($page['url']);
            if (!isset($report['error'])) {
                $results[] = [
                    'title' => $page['title'],
                    'url' => $page['url'],
                    'issues' => $report['issues'],
                    'issue_count' => count($report['issues'])
                ];
                $total_issues += count($report['issues']);
                
                // Store results in database for reports
                $this->store_scan_results($page['url'], $report['issues']);
            } else {
                $errors[] = $page['title'] . ': ' . $report['error'];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Error scanning ' . $page['url'] . ' - ' . $report['error']);
                }
            }
        }
        
        // Calculate accessibility score directly from scan results
        $accessibility_score = $this->calculate_scan_score($total_issues, count($results));
        
        // Store the score for use in compliance status
        delete_transient('raywp_accessibility_last_scan_score'); // Clear any cached value
        set_transient('raywp_accessibility_last_scan_score', $accessibility_score, HOUR_IN_SECONDS);
        
        // Also store as option for persistent display
        update_option('raywp_accessibility_current_score', $accessibility_score);
        
        wp_send_json_success([
            'pages_scanned' => count($results),
            'total_issues' => $total_issues,
            'accessibility_score' => $accessibility_score,
            'results' => $results,
            'errors' => $errors,
            'pages_to_scan' => $pages_to_scan, // Debug info
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * AJAX handler for enabling all accessibility fixes
     */
    public function ajax_enable_all_fixes() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get current settings with proper defaults
        $current_settings = get_option('raywp_accessibility_settings', []);
        if (!is_array($current_settings)) {
            $current_settings = [];
        }
        
        // Enable all accessibility fixes
        $fixes_to_enable = [
            'enable_aria' => 1,
            'fix_empty_alt' => 1,
            'fix_lang_attr' => 1,
            'fix_form_labels' => 1,
            'add_skip_links' => 1,
            'fix_forms' => 1,
            'add_main_landmark' => 1,
            'fix_heading_hierarchy' => 1,
            'fix_aria_controls' => 1,
            'enhance_focus' => 1,
            'fix_contrast' => 1 // Note: Enable with caution, can override theme styles
        ];
        
        // Merge with current settings to preserve other options
        $updated_settings = array_merge($current_settings, $fixes_to_enable);
        
        // Delete the option first to ensure a clean update
        delete_option('raywp_accessibility_settings');
        
        // Save the updated settings
        $success = add_option('raywp_accessibility_settings', $updated_settings);
        
        // If add_option fails (option might exist), try update_option
        if (!$success) {
            $success = update_option('raywp_accessibility_settings', $updated_settings);
        }
        
        // Verify the settings were actually saved
        $saved_settings = get_option('raywp_accessibility_settings', []);
        $all_enabled = true;
        foreach ($fixes_to_enable as $key => $value) {
            if (empty($saved_settings[$key])) {
                $all_enabled = false;
                break;
            }
        }
        
        if ($all_enabled) {
            wp_send_json_success([
                'message' => 'All accessibility fixes have been enabled successfully',
                'enabled_fixes' => array_keys($fixes_to_enable),
                'saved_settings' => $saved_settings
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Failed to update settings',
                'current_settings' => $current_settings,
                'attempted_settings' => $updated_settings,
                'saved_settings' => $saved_settings
            ]);
        }
    }
    
    /**
     * Get pages for scanning
     */
    private function get_pages_for_scanning() {
        $pages = [];
        $max_pages = 100; // Safety limit to prevent timeouts
        
        // Add homepage
        $pages[] = [
            'title' => 'Homepage',
            'url' => home_url()
        ];
        
        // Get all published posts (with limit)
        $posts = get_posts([
            'numberposts' => $max_pages,
            'post_status' => 'publish',
            'post_type' => 'post'
        ]);
        
        foreach ($posts as $post) {
            if (count($pages) >= $max_pages) break;
            $pages[] = [
                'title' => $post->post_title,
                'url' => get_permalink($post->ID)
            ];
        }
        
        // Get all published pages (with remaining limit)
        $remaining_slots = $max_pages - count($pages);
        if ($remaining_slots > 0) {
            $wp_pages = get_pages([
                'number' => $remaining_slots,
                'post_status' => 'publish'
            ]);
            
            foreach ($wp_pages as $page) {
                if (count($pages) >= $max_pages) break;
                $pages[] = [
                    'title' => $page->post_title,
                    'url' => get_permalink($page->ID)
                ];
            }
        }
        
        // Get custom post types (if any remaining slots)
        $remaining_slots = $max_pages - count($pages);
        if ($remaining_slots > 0) {
            $custom_post_types = get_post_types(['public' => true, '_builtin' => false]);
            
            foreach ($custom_post_types as $post_type) {
                if (count($pages) >= $max_pages) break;
                
                $custom_posts = get_posts([
                    'numberposts' => min(10, $remaining_slots), // Max 10 per custom post type
                    'post_status' => 'publish',
                    'post_type' => $post_type
                ]);
                
                foreach ($custom_posts as $post) {
                    if (count($pages) >= $max_pages) break;
                    $pages[] = [
                        'title' => $post->post_title . ' (' . $post_type . ')',
                        'url' => get_permalink($post->ID)
                    ];
                }
                
                $remaining_slots = $max_pages - count($pages);
            }
        }
        
        return $pages;
    }
    
    /**
     * Calculate accessibility score from scan results
     */
    private function calculate_scan_score($total_issues, $pages_scanned) {
        if ($pages_scanned === 0) {
            return 0; // No pages scanned
        }
        
        if ($total_issues === 0) {
            return 100; // No issues = perfect score
        }
        
        // Calculate average issues per page
        $issues_per_page = $total_issues / $pages_scanned;
        
        // Use a more reasonable scoring curve
        // 0 issues/page = 100%
        // 0.5 issues/page = 95%
        // 1 issue/page = 90%
        // 2 issues/page = 80%
        // 3 issues/page = 70%
        // 5 issues/page = 50%
        // 10+ issues/page = 0%
        
        if ($issues_per_page <= 0.5) {
            $score = 100 - ($issues_per_page * 10); // 95-100%
        } elseif ($issues_per_page <= 1) {
            $score = 95 - (($issues_per_page - 0.5) * 10); // 90-95%
        } elseif ($issues_per_page <= 2) {
            $score = 90 - (($issues_per_page - 1) * 10); // 80-90%
        } elseif ($issues_per_page <= 3) {
            $score = 80 - (($issues_per_page - 2) * 10); // 70-80%
        } elseif ($issues_per_page <= 5) {
            $score = 70 - (($issues_per_page - 3) * 10); // 50-70%
        } elseif ($issues_per_page <= 10) {
            $score = 50 - (($issues_per_page - 5) * 10); // 0-50%
        } else {
            $score = 0; // More than 10 issues per page
        }
        
        // Round to nearest integer and ensure within 0-100 range
        $score = round(max(0, min(100, $score)));
        
        return $score;
    }
    
    /**
     * Store scan results in database
     */
    private function store_scan_results($url, $issues) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        // Clear previous results for this URL
        $wpdb->delete($table_name, ['page_url' => $url]);
        
        // Insert new results
        foreach ($issues as $issue) {
            $wpdb->insert($table_name, [
                'scan_date' => current_time('mysql'),
                'page_url' => $url,
                'issue_type' => $issue['type'],
                'issue_severity' => $issue['severity'],
                'issue_description' => $issue['message'],
                'element_selector' => $issue['element'] ?? '',
                'fixed' => 0
            ]);
        }
    }
    
    /**
     * AJAX handler for toggling checker widget
     */
    public function ajax_toggle_checker_widget() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $enable = !empty($_POST['enable']) ? 1 : 0;
        $current_settings = get_option('raywp_accessibility_settings', []);
        $current_settings['enable_checker'] = $enable;
        
        $success = update_option('raywp_accessibility_settings', $current_settings);
        
        if ($success) {
            wp_send_json_success([
                'message' => $enable ? 'Checker widget enabled' : 'Checker widget disabled',
                'enabled' => $enable
            ]);
        } else {
            wp_send_json_error('Failed to update settings');
        }
    }
    
    /**
     * AJAX handler for adding color override
     */
    public function ajax_add_color_override() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $selector = sanitize_text_field($_POST['selector'] ?? '');
        // Allow various color formats (hex, rgb, rgba, color names)
        $color = $this->sanitize_color($_POST['color'] ?? '');
        $background = $this->sanitize_color($_POST['background'] ?? '');
        
        if (empty($selector)) {
            wp_send_json_error('Selector is required');
        }
        
        if (empty($color) && empty($background)) {
            wp_send_json_error('At least one color is required');
        }
        
        $override = [
            'selector' => $selector,
            'color' => $color,
            'background' => $background
        ];
        
        $current_overrides = get_option('raywp_accessibility_color_overrides', []);
        $current_overrides[] = $override;
        
        update_option('raywp_accessibility_color_overrides', $current_overrides);
        
        wp_send_json_success([
            'message' => 'Color override added successfully',
            'override' => $override,
            'index' => count($current_overrides) - 1
        ]);
    }
    
    /**
     * AJAX handler for deleting color override
     */
    public function ajax_delete_color_override() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $index = intval($_POST['index'] ?? -1);
        
        if ($index < 0) {
            wp_send_json_error('Invalid index');
        }
        
        $current_overrides = get_option('raywp_accessibility_color_overrides', []);
        
        if (isset($current_overrides[$index])) {
            array_splice($current_overrides, $index, 1);
            $current_overrides = array_values($current_overrides); // Re-index array
            update_option('raywp_accessibility_color_overrides', $current_overrides);
            wp_send_json_success(['message' => 'Color override removed successfully']);
        } else {
            wp_send_json_error('Override not found');
        }
    }
    
    /**
     * Sanitize color value
     */
    private function sanitize_color($color) {
        $color = trim($color);
        
        // If empty, return empty
        if (empty($color)) {
            return '';
        }
        
        // Check for hex color
        if (preg_match('/^#[a-f0-9]{3}([a-f0-9]{3})?$/i', $color)) {
            return $color;
        }
        
        // Check for rgb/rgba with proper validation
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $color, $matches)) {
            // Validate each RGB value is between 0-255
            if ($matches[1] <= 255 && $matches[2] <= 255 && $matches[3] <= 255) {
                return $color;
            }
        }
        
        // Check for rgba with proper validation
        if (preg_match('/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([0-9]*\.?[0-9]+)\s*\)$/i', $color, $matches)) {
            // Validate RGB values are between 0-255 and alpha is between 0-1
            if ($matches[1] <= 255 && $matches[2] <= 255 && $matches[3] <= 255 && $matches[4] <= 1) {
                return $color;
            }
        }
        
        // Check for named colors (basic list)
        $named_colors = ['white', 'black', 'red', 'blue', 'green', 'yellow', 'orange', 'purple', 
                        'gray', 'grey', 'brown', 'pink', 'cyan', 'magenta', 'navy', 'teal',
                        'silver', 'maroon', 'olive', 'lime', 'aqua', 'fuchsia'];
        
        if (in_array(strtolower($color), $named_colors)) {
            return strtolower($color);
        }
        
        // Default to empty if not valid
        return '';
    }
    
    /**
     * Get component
     */
    public function get_component($name) {
        return $this->components[$name] ?? null;
    }
}
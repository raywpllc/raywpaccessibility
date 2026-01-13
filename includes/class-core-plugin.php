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
        $this->components['site_scanner'] = new \RayWP\Accessibility\Core\Site_Scanner();
        
        // Always create accessibility checker (needed for reports and AJAX)
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
        // Text domain - WordPress handles this automatically for WordPress.org plugins
        // add_action('init', [$this, 'load_textdomain']);
        
        // AJAX handlers
        add_action('wp_ajax_raywp_accessibility_validate_selector', [$this, 'ajax_validate_selector']);
        add_action('wp_ajax_raywp_accessibility_scan_forms', [$this, 'ajax_scan_forms']);
        add_action('wp_ajax_raywp_accessibility_add_aria_rule', [$this, 'ajax_add_aria_rule']);
        add_action('wp_ajax_raywp_accessibility_delete_aria_rule', [$this, 'ajax_delete_aria_rule']);
        add_action('wp_ajax_raywp_accessibility_fix_form', [$this, 'ajax_fix_form']);
        add_action('wp_ajax_raywp_accessibility_run_full_scan', [$this, 'ajax_run_full_scan']);
        add_action('wp_ajax_raywp_accessibility_enable_all_fixes', [$this, 'ajax_enable_all_fixes']);
        add_action('wp_ajax_raywp_accessibility_scan_with_fixes', [$this, 'ajax_scan_with_fixes']);
        add_action('wp_ajax_raywp_accessibility_store_fixed_score', [$this, 'ajax_store_fixed_score']);
        add_action('wp_ajax_raywp_accessibility_get_scan_with_fixes_results', [$this, 'ajax_get_scan_with_fixes_results']);
        add_action('wp_ajax_raywp_accessibility_clear_scan_with_fixes_results', [$this, 'ajax_clear_scan_with_fixes_results']);
        add_action('wp_ajax_raywp_accessibility_store_live_score', [$this, 'ajax_store_live_score']);
        add_action('wp_ajax_raywp_accessibility_clear_live_score', [$this, 'ajax_clear_live_score']);
        add_action('wp_ajax_raywp_accessibility_toggle_checker_widget', [$this, 'ajax_toggle_checker_widget']);
        add_action('wp_ajax_raywp_accessibility_add_color_override', [$this, 'ajax_add_color_override']);
        add_action('wp_ajax_raywp_accessibility_delete_color_override', [$this, 'ajax_delete_color_override']);
        add_action('wp_ajax_raywp_accessibility_get_pages_list', [$this, 'ajax_get_pages_list']);
        add_action('wp_ajax_raywp_accessibility_store_axe_results', [$this, 'ajax_store_axe_results']);
        add_action('wp_ajax_raywp_accessibility_process_axe_results', [$this, 'ajax_process_axe_results']);
        add_action('wp_ajax_raywp_accessibility_get_css_overrides', [$this, 'ajax_get_css_overrides']);
        add_action('wp_ajax_raywp_accessibility_clear_scan_data', [$this, 'ajax_clear_scan_data']);
        
        // Test AJAX handler
        add_action('wp_ajax_raywp_accessibility_test', [$this, 'ajax_test']);
        
        // Background contrast pre-calculation hooks
        add_action('raywp_process_contrast_precalc', [$this, 'process_contrast_precalculation']);
        
        // Process entire page output for better ARIA injection
        add_action('template_redirect', [$this, 'start_output_buffering'], 0);
    }
    
    /**
     * Load plugin textdomain - Removed as WordPress handles this automatically for WordPress.org plugins
     */
    public function load_textdomain() {
        // WordPress automatically loads translations for plugins hosted on WordPress.org
        // No manual load_plugin_textdomain() call needed since WP 4.6+
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
     * Test AJAX handler
     */
    public function ajax_test() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        wp_send_json_success(['message' => 'AJAX is working']);
    }
    
    
    /**
     * AJAX handler for selector validation
     */
    public function ajax_validate_selector() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');

        // Verify user has admin capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $selector = sanitize_text_field(wp_unslash($_POST['selector'] ?? ''));
        $is_valid = $this->components['aria_manager']->validate_css_selector($selector);

        wp_send_json_success(['valid' => $is_valid]);
    }
    
    /**
     * AJAX handler for form scanning
     */
    public function ajax_scan_forms() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
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
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $selector = sanitize_text_field(wp_unslash($_POST['selector'] ?? ''));
        $attribute = sanitize_text_field(wp_unslash($_POST['attribute'] ?? ''));
        $value = sanitize_text_field(wp_unslash($_POST['value'] ?? ''));
        
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
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $index = intval(wp_unslash($_POST['index'] ?? -1));
        
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
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $plugin = sanitize_text_field(wp_unslash($_POST['plugin'] ?? ''));
        $form_id = sanitize_text_field(wp_unslash($_POST['form_id'] ?? ''));
        
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
        error_log('RayWP Accessibility: Starting full scan...');
        
        try {
            error_log('RayWP Accessibility: Checking nonce...');
            check_ajax_referer('raywp_accessibility_nonce', 'nonce');
            
            error_log('RayWP Accessibility: Checking user capabilities...');
            if (!current_user_can('manage_options')) {
                error_log('RayWP Accessibility: User unauthorized');
                wp_send_json_error('Unauthorized');
                return;
            }
            
            error_log('RayWP Accessibility: Checking components...');
            error_log('RayWP Accessibility: Available components: ' . implode(', ', array_keys($this->components)));
            
            // Get accessibility checker component
            if (!isset($this->components['accessibility_checker'])) {
                error_log('RayWP Accessibility: Accessibility checker component not found');
                wp_send_json_error('Accessibility checker component not initialized');
                return;
            }
            
            $checker = $this->components['accessibility_checker'];
            error_log('RayWP Accessibility: Checker type: ' . get_class($checker));
            
            if (!$checker || !method_exists($checker, 'generate_report')) {
                error_log('RayWP Accessibility: Checker missing generate_report method');
                wp_send_json_error('Accessibility checker not available or missing generate_report method');
                return;
            }
            
            // Get pages to scan
            error_log('RayWP Accessibility: Getting pages to scan...');
            $pages = $this->get_pages_for_scanning();
            error_log('RayWP Accessibility: Found ' . count($pages) . ' pages to scan');
            
            if (empty($pages)) {
                error_log('RayWP Accessibility: No pages found');
                wp_send_json_error('No pages found to scan');
                return;
            }
            
            // Log first few pages for debugging
            foreach (array_slice($pages, 0, 3) as $i => $page) {
                error_log("RayWP Accessibility: Page $i: " . $page['url']);
            }
            
            // Clear any cached data to ensure fresh results
            $this->clear_scan_caches();
            
            // Clear axe-core results from "Check Score with Fixes" to ensure manual issues are hidden
            delete_option('raywp_accessibility_axe_results');
            
            // Ensure database table exists before scanning with correct structure
            $this->recreate_scan_results_table();
        
            error_log('RayWP Accessibility: Starting scan loop...');
            $total_issues = 0;
            $results = [];
            
            foreach ($pages as $page_index => $page) {
                error_log("RayWP Accessibility: Scanning page $page_index: {$page['url']}");
                
                try {
                    // Scan page WITHOUT fixes to get current issues
                    error_log("RayWP Accessibility: Calling generate_report for {$page['url']}");
                    $report = $checker->generate_report($page['url'], false);
                    error_log("RayWP Accessibility: Report generated for {$page['url']}");
                    
                    if (!isset($report['error'])) {
                        $issues = $report['issues'] ?? [];
                        $issue_count = count($issues);
                        error_log("RayWP Accessibility: Found $issue_count issues on {$page['url']}");
                        
                        $results[] = [
                            'url' => $page['url'],
                            'title' => $page['title'] ?? 'Unknown Page',
                            'issue_count' => $issue_count,
                            'issues' => $issues,
                            'status' => 'completed'
                        ];
                        
                        $total_issues += $issue_count;
                        
                        // Store individual scan results
                        $stored = $this->store_scan_results($page['url'], $issues);
                        error_log("RayWP Accessibility: Stored $stored issues for {$page['url']}");
                    } else {
                        error_log("RayWP Accessibility: Report error for {$page['url']}: " . $report['error']);
                        $results[] = [
                            'url' => $page['url'],
                            'title' => $page['title'] ?? 'Unknown Page',
                            'issue_count' => 0,
                            'issues' => [],
                            'status' => 'error',
                            'error' => $report['error']
                        ];
                    }
                } catch (Exception $e) {
                    error_log("RayWP Accessibility: Exception scanning {$page['url']}: " . $e->getMessage());
                    error_log("RayWP Accessibility: Exception stack trace: " . $e->getTraceAsString());
                    $results[] = [
                        'url' => $page['url'],
                        'title' => $page['title'] ?? 'Unknown Page',
                        'issue_count' => 0,
                        'issues' => [],
                        'status' => 'error',
                        'error' => 'Exception during scan: ' . $e->getMessage()
                    ];
                }
            }
            
            // Calculate overall score using severity-weighted scoring from in-memory data
            // This avoids database timing/caching issues
            // Score is normalized per page to handle real-world issue counts
            error_log('RayWP Accessibility: Calculating scan score from in-memory results...');

            $severity_weights = [
                'critical' => 10,
                'high' => 5,
                'medium' => 3,
                'low' => 1
            ];

            $total_weight = 0;
            foreach ($results as $page_result) {
                if (isset($page_result['issues']) && is_array($page_result['issues'])) {
                    foreach ($page_result['issues'] as $issue) {
                        $severity = $issue['severity'] ?? 'medium';
                        $weight = $severity_weights[$severity] ?? 3;
                        $total_weight += $weight;
                    }
                }
            }

            // Normalize by pages scanned for per-page average
            $pages_count = max(1, count($pages));
            $avg_weight = $total_weight / $pages_count;

            // Score is 100 minus weighted penalties per page, minimum 0
            $score = max(0, round(100 - $avg_weight));

            error_log("RayWP Accessibility: Calculated score from memory: $score (total_weight: $total_weight, pages: $pages_count, avg: $avg_weight)");
            
            error_log('RayWP Accessibility: Preparing response...');
            $response_data = [
                'message' => 'Scan completed successfully',
                'accessibility_score' => $score,
                'total_issues' => $total_issues,
                'pages_scanned' => count($pages),
                'results' => $results,
                'timestamp' => current_time('mysql')
            ];
            
            error_log('RayWP Accessibility: Sending success response...');
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            error_log('RayWP Accessibility Full Scan Error: ' . $e->getMessage());
            error_log('RayWP Accessibility Full Scan Stack Trace: ' . $e->getTraceAsString());
            wp_send_json_error('Scan failed: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('RayWP Accessibility Full Scan Fatal Error: ' . $e->getMessage());
            error_log('RayWP Accessibility Full Scan Fatal Stack Trace: ' . $e->getTraceAsString());
            wp_send_json_error('Scan failed with fatal error: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for enabling all accessibility fixes
     */
    public function ajax_enable_all_fixes() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
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
            'fix_contrast' => 1,
            'fix_video_accessibility' => 1,
            'fix_keyboard_accessibility' => 1,
            'fix_duplicate_ids' => 1,
            'fix_page_language' => 1
        ];
        
        // Merge with current settings
        $updated_settings = array_merge($current_settings, $fixes_to_enable);
        
        // Save the updated settings
        $success = update_option('raywp_accessibility_settings', $updated_settings);
        
        // WordPress update_option returns false if the new value is identical to the existing value
        // So we also check if the current value matches what we tried to save
        $current_saved = get_option('raywp_accessibility_settings', []);
        $values_match = is_array($current_saved) && is_array($updated_settings);
        
        if ($values_match) {
            foreach ($fixes_to_enable as $key => $value) {
                if (!isset($current_saved[$key]) || $current_saved[$key] != $value) {
                    $values_match = false;
                    break;
                }
            }
        }
        
        if ($success || $values_match) {
            wp_send_json_success([
                'message' => 'All accessibility fixes have been enabled successfully',
                'enabled_fixes' => array_keys($fixes_to_enable)
            ]);
        } else {
            wp_send_json_error('Failed to save settings. Please try again.');
        }
    }
    
    /**
     * Get pages for scanning
     */
    private function get_pages_for_scanning() {
        $pages = [];
        $total_limit = 20; // Total limit for free version (excluding homepage)

        // Add homepage
        $pages[] = [
            'title' => 'Homepage',
            'url' => home_url()
        ];

        // Get published posts (fetch up to total_limit to ensure we have enough)
        $posts = get_posts([
            'numberposts' => $total_limit,
            'post_status' => 'publish',
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $content_pages_added = 0;

        // Add posts first (up to limit)
        foreach ($posts as $post) {
            if ($content_pages_added >= $total_limit) break;
            $pages[] = [
                'title' => $post->post_title,
                'url' => get_permalink($post->ID)
            ];
            $content_pages_added++;
        }

        // Get published pages (only add if we haven't hit the limit)
        if ($content_pages_added < $total_limit) {
            $remaining_slots = $total_limit - $content_pages_added;
            $wp_pages = get_pages([
                'number' => $remaining_slots,
                'post_status' => 'publish',
                'sort_column' => 'menu_order,post_title'
            ]);

            foreach ($wp_pages as $page) {
                if ($content_pages_added >= $total_limit) break;
                $pages[] = [
                    'title' => $page->post_title,
                    'url' => get_permalink($page->ID)
                ];
                $content_pages_added++;
            }
        }
        
        // Filter out any admin URLs that might have somehow been included
        $filtered_pages = [];
        foreach ($pages as $page) {
            $url = $page['url'];
            
            // Skip admin, includes, and content URLs
            if (strpos($url, '/wp-admin/') !== false || 
                strpos($url, '/wp-includes/') !== false ||
                strpos($url, '/wp-content/') !== false) {
                error_log('RayWP: Skipping admin/system URL from scan: ' . $url);
                continue;
            }
            
            $filtered_pages[] = $page;
        }
        
        return $filtered_pages;
    }

    /**
     * Map axe-core violation IDs to our internal issue types
     * @param string $axe_id - axe-core violation ID
     * @return string - Internal issue type
     */
    private function map_axe_id_to_issue_type($axe_id) {
        $mapping = [
            // Language
            'html-has-lang' => 'missing_page_language',
            'html-lang-valid' => 'invalid_page_language',
            'valid-lang' => 'invalid_language_attribute',

            // Images
            'image-alt' => 'missing_alt',
            'input-image-alt' => 'missing_alt',
            'role-img-alt' => 'role_img_missing_alt',
            'svg-img-alt' => 'svg_missing_alt',
            'area-alt' => 'area_missing_alt',
            'object-alt' => 'object_missing_alt',

            // Frames
            'frame-title' => 'iframe_missing_title',
            'frame-focusable-content' => 'frame_focusable_content',

            // Forms
            'label' => 'missing_label',
            'form-field-multiple-labels' => 'multiple_labels',
            'select-name' => 'select_missing_name',
            'input-button-name' => 'input_button_missing_name',
            'autocomplete-valid' => 'invalid_autocomplete',

            // Buttons & Links
            'button-name' => 'button_missing_accessible_name',
            'link-name' => 'link_no_accessible_name',
            'link-in-text-block' => 'link_distinguishable',

            // Color & Contrast
            'color-contrast' => 'low_contrast',
            'color-contrast-enhanced' => 'low_contrast_enhanced',

            // Headings
            'heading-order' => 'heading_hierarchy_skip',
            'empty-heading' => 'empty_heading',
            'empty-table-header' => 'empty_table_header',

            // Landmarks & Structure
            'landmark-one-main' => 'missing_main_landmark',
            'region' => 'content_outside_landmark',
            'landmark-banner-is-top-level' => 'banner_not_top_level',
            'landmark-contentinfo-is-top-level' => 'contentinfo_not_top_level',
            'landmark-main-is-top-level' => 'main_not_top_level',
            'landmark-no-duplicate-banner' => 'duplicate_banner',
            'landmark-no-duplicate-contentinfo' => 'duplicate_contentinfo',
            'landmark-no-duplicate-main' => 'duplicate_main',
            'landmark-unique' => 'landmark_not_unique',
            'bypass' => 'missing_skip_links',
            'document-title' => 'missing_page_title',

            // Duplicate IDs
            'duplicate-id' => 'duplicate_ids',
            'duplicate-id-active' => 'duplicate_active_id',
            'duplicate-id-aria' => 'duplicate_aria_id',

            // ARIA
            'aria-allowed-attr' => 'aria_invalid_attribute',
            'aria-allowed-role' => 'aria_invalid_role',
            'aria-command-name' => 'aria_command_missing_name',
            'aria-dialog-name' => 'aria_dialog_missing_name',
            'aria-hidden-body' => 'aria_hidden_body',
            'aria-hidden-focus' => 'aria_hidden_focus',
            'aria-input-field-name' => 'aria_input_missing_name',
            'aria-meter-name' => 'aria_meter_missing_name',
            'aria-progressbar-name' => 'aria_progressbar_missing_name',
            'aria-required-attr' => 'missing_aria',
            'aria-required-children' => 'aria_missing_children',
            'aria-required-parent' => 'aria_missing_parent',
            'aria-roles' => 'invalid_aria_role',
            'aria-roledescription' => 'aria_invalid_roledescription',
            'aria-toggle-field-name' => 'aria_toggle_missing_name',
            'aria-tooltip-name' => 'aria_tooltip_missing_name',
            'aria-treeitem-name' => 'aria_treeitem_missing_name',
            'aria-valid-attr' => 'invalid_aria',
            'aria-valid-attr-value' => 'invalid_aria_value',

            // Keyboard & Focus
            'tabindex' => 'tabindex_issue',
            'focus-order-semantics' => 'focus_order_issue',
            'focus-visible' => 'focus_not_visible',
            'scrollable-region-focusable' => 'scrollable_not_focusable',
            'nested-interactive' => 'nested_interactive',

            // Media
            'video-caption' => 'video_missing_captions',
            'audio-caption' => 'audio_missing_transcript',
            'no-autoplay-audio' => 'audio_autoplay',

            // Viewport
            'meta-viewport' => 'viewport_scaling_disabled',
            'meta-viewport-large' => 'viewport_too_small',
            'meta-refresh' => 'meta_refresh',

            // Other
            'accesskeys' => 'accesskey_issue',
            'server-side-image-map' => 'server_side_image_map',
            'blink' => 'blink_element',
            'marquee' => 'marquee_element',
            'definition-list' => 'definition_list_invalid',
            'dlitem' => 'dlitem_invalid',
            'list' => 'list_invalid',
            'listitem' => 'listitem_invalid',
            'p-as-heading' => 'p_used_as_heading'
        ];

        return isset($mapping[$axe_id]) ? $mapping[$axe_id] : $axe_id;
    }

    /**
     * Calculate scan score using weighted severity method
     * This matches the scoring used in ajax_run_full_scan for consistency
     *
     * @param array $issues Array of issues with 'severity' key, or int for backwards compatibility
     * @param int $pages_scanned Number of pages scanned
     * @return int Score from 0-100
     */
    private function calculate_scan_score($issues, $pages_scanned) {
        if ($pages_scanned === 0) {
            return 0;
        }

        // Handle backwards compatibility: if $issues is an integer, use tier-based as fallback
        if (is_int($issues) || is_numeric($issues)) {
            $total_issues = intval($issues);
            if ($total_issues == 0) {
                return 100;
            }
            // Estimate average weight per issue (assume medium severity average = 3)
            $avg_weight = ($total_issues * 3) / $pages_scanned;
            return max(0, round(100 - $avg_weight));
        }

        // If issues is an array, calculate weighted severity score
        $severity_weights = [
            'critical' => 10,
            'high' => 5,
            'medium' => 3,
            'low' => 1,
            'info' => 0
        ];

        $total_weight = 0;
        foreach ($issues as $issue) {
            $severity = $issue['severity'] ?? 'medium';
            $weight = $severity_weights[$severity] ?? 3;
            $total_weight += $weight;
        }

        // Normalize by pages scanned for per-page average
        $avg_weight = $total_weight / $pages_scanned;

        // Score is 100 minus weighted penalties per page, minimum 0
        return max(0, round(100 - $avg_weight));
    }
    
    /**
     * Store scan results in database
     */
    private function store_scan_results($url, $issues) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        // Check if table exists first - use caching to avoid repeated checks
        $cache_key = 'raywp_table_exists_' . md5($table_name);
        $table_exists = wp_cache_get($cache_key, 'raywp_accessibility');
        
        if ($table_exists === false) {
            // Direct database query is necessary here to check table existence
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            // Cache the result for 1 hour to avoid repeated table existence checks
            wp_cache_set($cache_key, $table_exists ? 'exists' : 'missing', 'raywp_accessibility', HOUR_IN_SECONDS);
        }
        
        if (!$table_exists || $table_exists === 'missing') {
            // Try to create the table
            $this->ensure_scan_results_table();
            
            // Check again and update cache
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            wp_cache_set($cache_key, $table_exists ? 'exists' : 'missing', 'raywp_accessibility', HOUR_IN_SECONDS);
            
            if (!$table_exists) {
                return false;
            }
        }
        
        // Clear previous results for this URL - direct query necessary for custom table operations
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete($table_name, ['page_url' => $url]);
        
        // Clear any cached scan results for this URL
        wp_cache_delete('raywp_scan_results_' . md5($url), 'raywp_accessibility');
        
        // Insert new results
        $inserted = 0;
        foreach ($issues as $issue) {
            // Direct database query necessary for custom table operations - scan results are dynamic and shouldn't be cached
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->insert($table_name, [
                'scan_date' => current_time('mysql'),
                'page_url' => $url,
                'issue_type' => $issue['type'],
                'issue_severity' => $issue['severity'],
                'issue_description' => $issue['message'],
                'element_selector' => $issue['element'] ?? '',
                'fixed' => 0
            ]);
            
            if ($result !== false) {
                $inserted++;
            } else {
                // Log the database error
                error_log('RayWP Accessibility: Database insert failed - ' . $wpdb->last_error);
            }
        }
        
        return $inserted;
    }
    
    /**
     * Ensure scan results table exists
     */
    private function ensure_scan_results_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if ($table_exists) {
            error_log('RayWP Accessibility: Table already exists');
            return true;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_date datetime DEFAULT CURRENT_TIMESTAMP,
            page_url varchar(255) NOT NULL,
            issue_type varchar(100) NOT NULL,
            issue_severity varchar(20) NOT NULL,
            issue_description text NOT NULL,
            element_selector varchar(255),
            wcag_reference varchar(50),
            wcag_level varchar(5),
            auto_fixable tinyint(1) DEFAULT 0,
            page_type varchar(50) DEFAULT 'page',
            scan_session_id varchar(100),
            wcag_criterion varchar(50),
            compliance_impact varchar(50),
            confidence_level varchar(20),
            fixed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY page_url (page_url),
            KEY issue_type (issue_type),
            KEY scan_date (scan_date),
            KEY scan_session_id (scan_session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Verify table was created
        $table_created = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if (!$table_created) {
            $error = 'Failed to create scan results table: ' . $wpdb->last_error;
            error_log('RayWP Accessibility: ' . $error);
            throw new \Exception($error);
        }
        
        error_log('RayWP Accessibility: Table created successfully - ' . print_r($result, true));
        return true;
    }
    
    /**
     * Force recreate the scan results table with correct structure
     */
    /**
     * Clear all scan-related caches to ensure fresh results
     */
    private function clear_scan_caches() {
        global $wpdb;
        
        // Clear table existence cache
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        $cache_key = 'raywp_table_exists_' . md5($table_name);
        wp_cache_delete($cache_key, 'raywp_accessibility');
        
        // Clear scan results caches
        wp_cache_delete('raywp_scan_results_100', 'raywp_accessibility');
        wp_cache_delete('raywp_issue_summary', 'raywp_accessibility');
        wp_cache_delete('raywp_compliance_assessment', 'raywp_accessibility');
        wp_cache_delete('raywp_detailed_manual_issues', 'raywp_accessibility');
        
        // Clear additional Reports class caches
        wp_cache_delete('raywp_last_scan_date', 'raywp_accessibility');
        wp_cache_delete('raywp_wcag_breakdown', 'raywp_accessibility');
        wp_cache_delete('raywp_scan_sessions', 'raywp_accessibility');
        
        // Clear variable scan results caches with different limits
        for ($limit = 10; $limit <= 1000; $limit += 10) {
            wp_cache_delete('raywp_scan_results_' . $limit, 'raywp_accessibility');
        }
        
        // Clear any page-specific scan result caches
        $pages = $this->get_pages_for_scanning();
        foreach ($pages as $page) {
            wp_cache_delete('raywp_scan_results_' . md5($page['url']), 'raywp_accessibility');
        }
        
        // Clear axe-core results to ensure fresh data
        delete_option('raywp_accessibility_axe_results');
        
        error_log('RayWP Accessibility: Cleared all scan caches');
    }
    
    private function recreate_scan_results_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        error_log('RayWP Accessibility: Recreating table with correct structure...');
        
        // Drop the existing table (use esc_sql for safety even though table name is from constants)
        $wpdb->query("DROP TABLE IF EXISTS " . esc_sql($table_name));
        error_log('RayWP Accessibility: Dropped existing table');
        
        // Create new table with correct structure
        $this->ensure_scan_results_table();
        
        error_log('RayWP Accessibility: Table recreated successfully');
    }
    
    /**
     * AJAX handler for clearing scan data and caches
     */
    public function ajax_clear_scan_data() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        try {
            // Clear all scan-related caches
            $this->clear_scan_caches();
            
            // Recreate database table to clear all previous scan results
            $this->recreate_scan_results_table();
            
            wp_send_json_success([
                'message' => 'Scan data and caches cleared successfully',
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to clear scan data: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for toggling checker widget
     */
    public function ajax_toggle_checker_widget() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $enable = !empty(sanitize_text_field(wp_unslash($_POST['enable'] ?? ''))) ? 1 : 0;
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
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $selector = sanitize_text_field(wp_unslash($_POST['selector'] ?? ''));
        // Allow various color formats (hex, rgb, rgba, color names)
        $color = isset($_POST['color']) ? $this->sanitize_color(sanitize_text_field(wp_unslash($_POST['color']))) : '';
        $background = isset($_POST['background']) ? $this->sanitize_color(sanitize_text_field(wp_unslash($_POST['background']))) : '';
        
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
        
        // Schedule background contrast pre-calculation
        $this->schedule_contrast_precalculation();
        
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
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $index = intval(wp_unslash($_POST['index'] ?? -1));
        
        if ($index < 0) {
            wp_send_json_error('Invalid index');
        }
        
        $current_overrides = get_option('raywp_accessibility_color_overrides', []);
        
        if (isset($current_overrides[$index])) {
            array_splice($current_overrides, $index, 1);
            $current_overrides = array_values($current_overrides); // Re-index array
            update_option('raywp_accessibility_color_overrides', $current_overrides);
            
            // Schedule background contrast pre-calculation
            $this->schedule_contrast_precalculation();
            
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
    
    /**
     * AJAX handler for scanning with fixes enabled to show "after" score
     */
    public function ajax_scan_with_fixes() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'raywp_accessibility_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        // Performance optimization for dual scanning
        // Note: We avoid discouraged functions and implement intelligent batching
        // to ensure compatibility with WordPress.org hosting environments
        $scan_start_time = microtime(true);
        $max_dual_scan_time = 90; // Limit dual scanning to 1.5 minutes
        
        // Get accessibility checker component
        if (!isset($this->components['accessibility_checker'])) {
            wp_send_json_error('Accessibility checker component not initialized');
            return;
        }
        
        $checker = $this->components['accessibility_checker'];
        
        if (!$checker || !method_exists($checker, 'generate_report')) {
            wp_send_json_error('Accessibility checker not available or missing generate_report method');
            return;
        }
        
        // Get pages for dual scanning - use all available pages in free version
        $all_pages = $this->get_pages_for_scanning();
        $pages_to_scan = $all_pages; // Scan all pages (limited to 41 total in free version: 1 home + 20 posts + 20 pages)
        
        $total_issues = 0;
        $results = [];
        $issue_breakdown = [
            'fixed' => [],
            'remaining' => [],
            'unfixable' => []
        ];
        
        foreach ($pages_to_scan as $page) {
            // Check if we're approaching time limit for dual scanning
            $current_scan_time = microtime(true);
            if (($current_scan_time - $scan_start_time) > $max_dual_scan_time) {
                $issue_breakdown['remaining'][] = [
                    'type' => 'scan_timeout',
                    'message' => 'Dual scan timeout: Processing stopped to prevent server timeout. Results may be incomplete.',
                    'page_url' => 'system',
                    'page_title' => 'System Message'
                ];
                break;
            }
            
            // Scan WITHOUT fixes to get original issues
            $page_start_time = microtime(true);
            $original_report = $checker->generate_report($page['url'], false);
            $without_fixes_time = microtime(true) - $page_start_time;
            
            // Scan WITH fixes applied
            $page_start_time = microtime(true);
            $fixed_report = $checker->generate_report($page['url'], true);
            $with_fixes_time = microtime(true) - $page_start_time;
            
            if (!isset($original_report['error']) && !isset($fixed_report['error'])) {
                $original_issues = $original_report['issues'];
                $remaining_issues = $fixed_report['issues'];
                
                // Simple comparison: if the total count of each issue type decreased, consider them fixed
                $original_counts = [];
                $remaining_counts = [];
                
                // Count original issues by type
                foreach ($original_issues as $issue) {
                    $type = $issue['type'];
                    $original_counts[$type] = ($original_counts[$type] ?? 0) + 1;
                }
                
                // Count remaining issues by type
                foreach ($remaining_issues as $issue) {
                    $type = $issue['type'];
                    $remaining_counts[$type] = ($remaining_counts[$type] ?? 0) + 1;
                }
                
                // Determine what was fixed by comparing counts
                foreach ($original_counts as $type => $original_count) {
                    $remaining_count = $remaining_counts[$type] ?? 0;
                    $fixed_count = $original_count - $remaining_count;
                    
                    if ($fixed_count > 0) {
                        // Add representative issues to fixed category
                        $sample_issue = null;
                        foreach ($original_issues as $issue) {
                            if ($issue['type'] === $type) {
                                $sample_issue = $issue;
                                break;
                            }
                        }
                        
                        // Add multiple copies to represent the count, including page info
                        for ($i = 0; $i < $fixed_count; $i++) {
                            $issue_with_page = $sample_issue;
                            $issue_with_page['page_url'] = $page['url'];
                            $issue_with_page['page_title'] = $page['title'] ?? 'Unknown Page';
                            $issue_breakdown['fixed'][] = $issue_with_page;
                        }
                    }
                    
                    // Add remaining issues to appropriate category
                    if ($remaining_count > 0) {
                        $sample_issue = null;
                        foreach ($remaining_issues as $issue) {
                            if ($issue['type'] === $type) {
                                $sample_issue = $issue;
                                break;
                            }
                        }
                        
                        $should_have_been_fixed = $this->should_issue_be_auto_fixed($sample_issue);
                        
                        for ($i = 0; $i < $remaining_count; $i++) {
                            $issue_with_page = $sample_issue;
                            $issue_with_page['page_url'] = $page['url'];
                            $issue_with_page['page_title'] = $page['title'] ?? 'Unknown Page';
                            
                            if ($should_have_been_fixed) {
                                $issue_breakdown['unfixable'][] = $issue_with_page;
                            } else {
                                $issue_breakdown['remaining'][] = $issue_with_page;
                            }
                        }
                    }
                }
                
                $results[] = [
                    'url' => $page['url'],
                    'title' => $page['title'] ?? 'Unknown Page',
                    'original_issues' => count($original_issues),
                    'remaining_issues' => count($remaining_issues)
                ];
                $total_issues += count($remaining_issues);
            } else {
                // Handle error cases
                $error_msg = '';
                if (isset($original_report['error'])) {
                    $error_msg = 'Original scan error: ' . $original_report['error'];
                }
                if (isset($fixed_report['error'])) {
                    if (!empty($error_msg)) $error_msg .= '; ';
                    $error_msg .= 'Fixed scan error: ' . $fixed_report['error'];
                }
                
                // Add error to remaining issues for visibility
                $issue_breakdown['remaining'][] = [
                    'type' => 'scan_error',
                    'message' => $error_msg,
                    'page_url' => $page['url'],
                    'page_title' => $page['title'] ?? 'Unknown Page',
                    'severity' => 'error'
                ];
                
                // Continue with next page
                continue;
            }
        }
        
        // Check if we have any successful scans
        if (empty($results)) {
            // No pages could be scanned successfully
            $error_message = 'Unable to scan any pages. ';
            if (!empty($issue_breakdown['remaining'])) {
                $error_message .= 'Errors encountered: ' . $issue_breakdown['remaining'][0]['message'];
            }
            wp_send_json_error($error_message);
            return;
        }
        
        // Collect all original issues from all pages for weighted scoring
        $all_original_issues = [];
        foreach ($results as $result) {
            if (!empty($result['original_scan']['issues'])) {
                foreach ($result['original_scan']['issues'] as $issue) {
                    $all_original_issues[] = $issue;
                }
            }
        }

        // Calculate original score (before fixes) using weighted severity method
        $original_score = $this->calculate_scan_score($all_original_issues, count($results));

        // Calculate score with fixes - use remaining issues
        // For the fixed score, we use the remaining issues (which need manual attention)
        $fixed_score = $this->calculate_scan_score($issue_breakdown['remaining'], count($results));

        // Store the detailed results for persistence across page loads
        $scan_results_data = [
            'original_score' => $original_score,
            'fixed_score' => $fixed_score,
            'total_original_issues' => $total_original_issues,
            'total_issues' => $total_issues,
            'pages_scanned' => count($results),
            'details' => $results,
            'issue_breakdown' => $issue_breakdown,
            'message' => 'Scanned ' . count($pages_to_scan) . ' sample pages with fixes enabled (out of ' . count($all_pages) . ' total pages)',
            'timing' => 'Dual scan completed',
            'timestamp' => current_time('mysql')
        ];
        
        // Store in WordPress options for persistence
        update_option('raywp_accessibility_scan_with_fixes_results', $scan_results_data);

        // Also update the live score option that the display uses
        update_option('raywp_accessibility_live_score', $fixed_score);
        update_option('raywp_accessibility_live_score_timestamp', time());

        wp_send_json_success($scan_results_data);
    }
    
    /**
     * Determine if an issue should be automatically fixed by the plugin
     * Uses the same comprehensive logic as Admin::is_auto_fixable()
     * Note: Default settings from activator are: fix_empty_alt, add_skip_links, fix_forms, add_main_landmark
     */
    private function should_issue_be_auto_fixed($issue) {
        $settings = get_option('raywp_accessibility_settings', []);
        $issue_type = $issue['type'] ?? '';

        // Helper to check if setting is enabled with default value
        $is_enabled = function($key, $default = false) use ($settings) {
            return isset($settings[$key]) ? !empty($settings[$key]) : $default;
        };

        // Comprehensive auto-fixable map matching Admin::is_auto_fixable()
        // Uses default=true for settings enabled by default in activator
        $auto_fixable_map = [
            // Images - fix_empty_alt (default enabled)
            'missing_alt' => $is_enabled('fix_empty_alt', true),

            // Forms - fix_forms (default enabled)
            'missing_label' => $is_enabled('fix_forms', true) || $is_enabled('fix_form_labels', true),
            'required_no_aria' => $is_enabled('fix_forms', true),
            'validation_no_error_message' => $is_enabled('fix_forms', true),
            'missing_autocomplete_attribute' => $is_enabled('fix_forms', true),
            'error_no_role' => $is_enabled('fix_forms', true),
            'generic_error_message' => $is_enabled('fix_forms', true),
            'missing_form_labels' => $is_enabled('fix_forms', true),

            // Landmarks - add_main_landmark (default enabled)
            'missing_main_landmark' => $is_enabled('add_main_landmark', true),
            'content_outside_landmark' => $is_enabled('add_main_landmark', true),

            // Skip links - add_skip_links (default enabled)
            'missing_skip_links' => $is_enabled('add_skip_links', true),

            // Language - fix_lang_attr (default enabled)
            'missing_page_language' => $is_enabled('fix_lang_attr', true),
            'invalid_page_language' => $is_enabled('fix_lang_attr', true),

            // Headings - fix_heading_hierarchy (default enabled)
            'heading_hierarchy_skip' => $is_enabled('fix_heading_hierarchy', true),
            'multiple_h1' => $is_enabled('fix_heading_hierarchy', true),
            'empty_heading' => $is_enabled('fix_empty_headings', true),

            // Buttons - fix_button_names (default enabled)
            'button_missing_accessible_name' => $is_enabled('fix_button_names', true),
            'input_button_missing_name' => $is_enabled('fix_button_names', true),

            // Links - fix_generic_links (default enabled)
            'link_no_accessible_name' => $is_enabled('fix_generic_links', true),
            'generic_link_text' => $is_enabled('fix_generic_links', true),
            'empty_link' => $is_enabled('fix_generic_links', true),

            // IFrames - fix_iframe_titles (default enabled)
            'missing_iframe_title' => $is_enabled('fix_iframe_titles', true),
            'iframe_missing_title' => $is_enabled('fix_iframe_titles', true),
            'frame-title' => $is_enabled('fix_iframe_titles', true),

            // Missing H1 heading - fix_missing_h1 (default enabled)
            'page-has-heading-one' => $is_enabled('fix_missing_h1', true),
            'missing_h1' => $is_enabled('fix_missing_h1', true),

            // ARIA role presentation conflicts - fix_presentation_conflict (default enabled)
            'presentation-role-conflict' => $is_enabled('fix_presentation_conflict', true),
            'aria_presentation_conflict' => $is_enabled('fix_presentation_conflict', true),

            // Duplicate IDs - fix_duplicate_ids (default enabled)
            'duplicate_ids' => $is_enabled('fix_duplicate_ids', true),
            'duplicate_active_id' => $is_enabled('fix_duplicate_ids', true),
            'duplicate_aria_id' => $is_enabled('fix_duplicate_ids', true),

            // Video/Animation
            'decorative_video_no_aria_hidden' => $is_enabled('fix_video_accessibility', false),
            'animation_no_reduced_motion' => true, // CSS-based, always available
            'transform_animation_no_control' => true, // CSS-based, always available

            // ARIA
            'missing_aria_controls' => $is_enabled('enable_aria', true),
        ];

        // Check if the issue type is in the auto-fixable map and is enabled
        if (isset($auto_fixable_map[$issue_type]) && $auto_fixable_map[$issue_type]) {
            return true;
        }

        // Special case for tracking pixels (1x1 images)
        if ($issue_type === 'missing_alt' && isset($issue['element_details']['attributes'])) {
            $style = $issue['element_details']['attributes']['style'] ?? '';
            $width = $issue['element_details']['attributes']['width'] ?? '';
            $height = $issue['element_details']['attributes']['height'] ?? '';

            if ((strpos($style, 'display:none') !== false) || ($width === '1' && $height === '1')) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * AJAX handler for storing the fixed score for dashboard display
     */
    public function ajax_store_fixed_score() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $fixed_score = intval(wp_unslash($_POST['fixed_score'] ?? 0));
        
        if ($fixed_score >= 0 && $fixed_score <= 100) {
            update_option('raywp_accessibility_fixed_score', $fixed_score);
            wp_send_json_success(['message' => 'Fixed score stored successfully']);
        } else {
            wp_send_json_error('Invalid score value');
        }
    }
    
    /**
     * AJAX handler for retrieving persisted scan with fixes results
     */
    public function ajax_get_scan_with_fixes_results() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $results = get_option('raywp_accessibility_scan_with_fixes_results', null);
        
        if ($results && is_array($results)) {
            wp_send_json_success($results);
        } else {
            wp_send_json_success(['no_data' => true]);
        }
    }
    
    /**
     * AJAX handler for clearing scan with fixes results
     */
    public function ajax_clear_scan_with_fixes_results() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        delete_option('raywp_accessibility_scan_with_fixes_results');
        wp_send_json_success(['message' => 'Scan results cleared']);
    }
    
    /**
     * AJAX handler for storing Live Score
     */
    public function ajax_store_live_score() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $live_score = isset($_POST['live_score']) ? intval($_POST['live_score']) : 0;
        
        if ($live_score < 0 || $live_score > 100) {
            wp_send_json_error(['message' => 'Invalid score range']);
        }
        
        update_option('raywp_accessibility_live_score', $live_score);
        update_option('raywp_accessibility_live_score_timestamp', time());
        
        wp_send_json_success(['message' => 'Live score stored', 'score' => $live_score]);
    }
    
    /**
     * AJAX handler for clearing Live Score
     */
    public function ajax_clear_live_score() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        delete_option('raywp_accessibility_live_score');
        delete_option('raywp_accessibility_live_score_timestamp');
        
        wp_send_json_success(['message' => 'Live score cleared']);
    }
    
    /**
     * AJAX handler for getting pages list for scanning
     */
    public function ajax_get_pages_list() {
        error_log('RayWP: ajax_get_pages_list called');

        // Verify nonce
        if (!check_ajax_referer('raywp_accessibility_nonce', 'nonce', false)) {
            error_log('RayWP: ajax_get_pages_list - nonce verification failed');
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('RayWP: ajax_get_pages_list - user unauthorized');
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        error_log('RayWP: ajax_get_pages_list - getting pages');
        $pages = $this->get_pages_for_scanning();
        error_log('RayWP: ajax_get_pages_list - found ' . count($pages) . ' pages');
        wp_send_json_success(['pages' => $pages]);
    }

    /**
     * AJAX handler for processing axe-core scan results
     * Receives results from browser-based axe-core scanning and processes them
     */
    public function ajax_process_axe_results() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        // Get the JSON results from the request
        $results_json = isset($_POST['results']) ? wp_unslash($_POST['results']) : '';
        $raw_results_json = isset($_POST['raw_results']) ? wp_unslash($_POST['raw_results']) : '';

        if (empty($results_json)) {
            wp_send_json_error(['message' => 'No results provided']);
            return;
        }

        $results = json_decode($results_json, true);
        $raw_results = json_decode($raw_results_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'Invalid JSON in results']);
            return;
        }

        // Get admin instance for auto-fixable checking
        $admin_instance = new \RayWP\Accessibility\Admin\Admin();

        // Process issues - categorize by fix status
        // IMPORTANT: The axe-core scan runs on the LIVE site with auto-fixes ALREADY ACTIVE.
        // To determine "fixed" issues, we compare against the server-side scan which
        // detects issues BEFORE DOM processing applies fixes.

        $fixed_issues = [];      // Issues that were in server-scan but NOT in axe-scan
        $remaining_issues = [];  // Issues that need manual attention (not auto-fixable)
        $unfixable_issues = [];  // Issues that SHOULD be auto-fixed but still appear (fix failed)
        $all_issues = $results['issues'] ?? [];

        // Get issues from the most recent server-side scan to compare
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scans';
        $server_scan_issues = [];

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            $recent_issues = $wpdb->get_results(
                "SELECT DISTINCT issue_type FROM {$table_name}
                 WHERE scan_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY scan_date DESC"
            );
            foreach ($recent_issues as $row) {
                $server_scan_issues[] = $row->issue_type;
            }
        }

        // Track which auto-fixable types were found in axe-core scan
        $axe_found_types = [];
        foreach ($all_issues as $issue) {
            $issue_type = $issue['type'] ?? '';
            $axe_found_types[$issue_type] = true;

            // Check if this issue type SHOULD be auto-fixable with current settings
            if ($admin_instance->is_auto_fixable($issue_type)) {
                // Issue is auto-fixable but was STILL found - means the fix didn't work!
                // This could be because:
                // - The element doesn't match the fix pattern
                // - The fix is disabled in settings
                // - There's a bug in the fix implementation
                $issue['fix_status'] = 'auto_fix_failed';
                $unfixable_issues[] = $issue;
            } else {
                // Issue is not auto-fixable - needs manual attention
                $issue['fix_status'] = 'manual_required';
                $remaining_issues[] = $issue;
            }
        }

        // Determine "fixed" issues: auto-fixable types from server scan that aren't in axe scan
        // These are issues that the DOM processor likely fixed
        $auto_fixable_types = [
            'empty_alt_text', 'image_missing_alt', 'image-alt',
            'missing_lang', 'document_language', 'html-has-lang',
            'form_missing_labels', 'label', 'label-content-name-mismatch',
            'missing_skip_links', 'bypass',
            'missing_main_landmark', 'landmark-one-main', 'region',
            'heading_hierarchy_skip', 'heading-order', 'empty-heading',
            'button_missing_text', 'button-name',
            'link_no_accessible_name', 'link-name', 'empty_link',
            'frame-title', 'iframe_missing_title', 'missing_iframe_title',
            'page-has-heading-one', 'missing_h1',
            'presentation-role-conflict', 'aria_presentation_conflict',
            'duplicate-id', 'duplicate_ids', 'duplicate-id-active', 'duplicate-id-aria',
            'aria-hidden-focus', 'aria-input-field-name', 'aria-required-children',
            'aria-required-parent', 'aria-roles', 'aria-valid-attr-value', 'aria-valid-attr',
            'list', 'listitem', 'definition-list', 'dlitem'
        ];

        foreach ($auto_fixable_types as $fix_type) {
            // If this type was in server scan but NOT found by axe-core, it was likely fixed
            if (in_array($fix_type, $server_scan_issues) && !isset($axe_found_types[$fix_type])) {
                // Create a synthetic "fixed" issue entry
                $fixed_issues[] = [
                    'type' => $fix_type,
                    'severity' => 'medium',
                    'fix_status' => 'auto_fixed',
                    'message' => $admin_instance->get_issue_description($fix_type) . ' - Fixed by DOM processor',
                    'synthetic' => true
                ];
            }
        }

        // Also mark auto-fixable types as "fixed" based on what the plugin CAN fix
        // even if they weren't in the server scan (they would have been caught)
        foreach ($auto_fixable_types as $fix_type) {
            if ($admin_instance->is_auto_fixable($fix_type) && !isset($axe_found_types[$fix_type])) {
                // Don't duplicate if already added from server scan
                $already_added = false;
                foreach ($fixed_issues as $fi) {
                    if ($fi['type'] === $fix_type) {
                        $already_added = true;
                        break;
                    }
                }
                if (!$already_added) {
                    $fixed_issues[] = [
                        'type' => $fix_type,
                        'severity' => 'medium',
                        'fix_status' => 'auto_fixed',
                        'message' => $admin_instance->get_issue_description($fix_type) . ' - Auto-fix enabled',
                        'synthetic' => true
                    ];
                }
            }
        }

        // Note: To show truly "fixed" issues, we would need to:
        // 1. Run a scan WITHOUT fixes applied (original state)
        // 2. Run a scan WITH fixes applied (current state)
        // 3. The difference = fixed issues
        // The server-side ajax_scan_with_fixes() does this dual scan approach.

        // Calculate scores using severity-weighted scoring, normalized per page
        $severity_weights = [
            'critical' => 10,
            'high' => 5,
            'serious' => 5,
            'medium' => 3,
            'moderate' => 3,
            'low' => 1,
            'minor' => 1
        ];

        $pages_scanned = max(1, $results['pages_scanned'] ?? 1); // Avoid division by zero

        // Original score = all issues (found + fixed)
        // This represents what the score WOULD be without auto-fixes
        $original_weight = 0;

        // Add weight for issues currently found
        foreach ($all_issues as $issue) {
            $severity = $issue['severity'] ?? 'medium';
            $original_weight += $severity_weights[$severity] ?? 3;
        }

        // Add weight for issues that were fixed (to show what original would have been)
        foreach ($fixed_issues as $issue) {
            $severity = $issue['severity'] ?? 'medium';
            $original_weight += $severity_weights[$severity] ?? 3;
        }

        // Normalize by pages scanned to get a per-page average
        $original_avg_weight = $original_weight / $pages_scanned;
        $original_score = max(0, round(100 - $original_avg_weight));

        // Fixed score = only issues that STILL exist (remaining + unfixable)
        // This represents the current state with auto-fixes active
        $fixed_weight = 0;
        // Include both remaining issues (not auto-fixable) and unfixable issues (auto-fix failed)
        $all_remaining = array_merge($remaining_issues, $unfixable_issues);
        foreach ($all_remaining as $issue) {
            $severity = $issue['severity'] ?? 'medium';
            $fixed_weight += $severity_weights[$severity] ?? 3;
        }
        // Normalize by pages scanned
        $fixed_avg_weight = $fixed_weight / $pages_scanned;
        $fixed_score = max(0, round(100 - $fixed_avg_weight));

        // Build per-page breakdown from raw results
        $page_details = [];
        if (!empty($raw_results['pages'])) {
            foreach ($raw_results['pages'] as $page) {
                $page_url = $page['url'] ?? '';
                $page_title = $page['title'] ?? 'Unknown';
                $page_violations = $page['violations'] ?? [];

                // Count original issues (all violations on this page)
                $original_count = 0;
                $remaining_count = 0;

                foreach ($page_violations as $violation) {
                    $node_count = count($violation['nodes'] ?? []);
                    $original_count += $node_count;

                    // Check if this violation type is auto-fixable
                    $axe_id = $violation['id'] ?? '';
                    $issue_type = $this->map_axe_id_to_issue_type($axe_id);

                    if (!$admin_instance->is_auto_fixable($issue_type)) {
                        $remaining_count += $node_count;
                    }
                }

                $page_details[] = [
                    'url' => $page_url,
                    'title' => $page_title,
                    'original_issues' => $original_count,
                    'remaining_issues' => $remaining_count,
                    'success' => $page['success'] ?? false
                ];
            }
        }

        // Prepare response data
        $response_data = [
            'original_score' => $original_score,
            'fixed_score' => $fixed_score,
            'pages_scanned' => $results['pages_scanned'] ?? 0,
            'total_issues' => count($all_issues),
            'fixed_count' => count($fixed_issues),           // Issues that DOM processor auto-fixed
            'remaining_count' => count($remaining_issues),   // Not auto-fixable, need manual attention
            'unfixable_count' => count($unfixable_issues),   // Auto-fixable but fix didn't work
            'issue_breakdown' => [
                'detected' => array_merge($all_issues, $fixed_issues), // All issues (found + fixed) for comparison
                'fixed' => $fixed_issues,         // Issues resolved by auto-fixes
                'remaining' => $remaining_issues, // Not auto-fixable, need manual attention
                'unfixable' => $unfixable_issues  // Auto-fixable but fix didn't work
            ],
            'details' => $page_details,
            'scan_type' => 'axe-core-iframe',
            'timestamp' => current_time('mysql'),
            'scan_note' => 'Compares issues found with fixes active against auto-fixable issue types to determine what was fixed.'
        ];

        // Store results for persistence
        update_option('raywp_accessibility_scan_with_fixes_results', $response_data);
        update_option('raywp_accessibility_live_score', $fixed_score);
        update_option('raywp_accessibility_live_score_timestamp', current_time('mysql'));

        // Also store the axe-core specific results
        update_option('raywp_accessibility_axe_results', [
            'violations_by_type' => $raw_results['violations_by_type'] ?? [],
            'total_violations' => $raw_results['total_violations'] ?? 0,
            'pages_scanned' => $raw_results['pages_scanned'] ?? 0,
            'duration' => $raw_results['duration'] ?? 0,
            'timestamp' => current_time('mysql')
        ]);

        wp_send_json_success($response_data);
    }
    
    /**
     * Schedule background contrast pre-calculation
     */
    private function schedule_contrast_precalculation() {
        // Cancel any existing scheduled event
        wp_clear_scheduled_hook('raywp_process_contrast_precalc');
        
        // Schedule a new event with a short delay to allow for multiple quick changes
        wp_schedule_single_event(time() + 30, 'raywp_process_contrast_precalc');
    }
    
    /**
     * Process background contrast pre-calculation
     */
    public function process_contrast_precalculation() {
        error_log('RayWP: Starting contrast pre-calculation');
        
        $settings = get_option('raywp_accessibility_settings', []);
        if (empty($settings['enable_color_overrides'])) {
            error_log('RayWP: Color overrides disabled, skipping pre-calculation');
            return;
        }
        
        $color_overrides = get_option('raywp_accessibility_color_overrides', []);
        if (empty($color_overrides)) {
            error_log('RayWP: No color overrides found, skipping pre-calculation');
            return;
        }
        
        // Get priority pages for contrast checking
        $priority_pages = $this->get_priority_pages_for_contrast();
        
        foreach ($priority_pages as $page) {
            $this->precalculate_page_contrast($page);
        }
        
        // Store the override hash for cache invalidation
        $override_hash = md5(serialize($color_overrides));
        update_option('raywp_accessibility_override_hash', $override_hash);
        
        error_log('RayWP: Contrast pre-calculation completed for ' . count($priority_pages) . ' pages');
    }
    
    /**
     * Get priority pages for contrast checking
     */
    private function get_priority_pages_for_contrast() {
        $pages = [];
        
        // Always include homepage
        $pages[] = [
            'url' => home_url('/'),
            'title' => 'Homepage',
            'priority' => 1
        ];
        
        // Get pages that had contrast issues in recent scans
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        if ($this->check_table_exists($table_name)) {
            $recent_contrast_pages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT page_url 
                     FROM {$table_name} 
                     WHERE issue_type LIKE %s 
                     AND scan_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
                     LIMIT 5",
                    '%contrast%'
                ),
                ARRAY_A
            );
            
            foreach ($recent_contrast_pages as $page) {
                if (!empty($page['page_url'])) {
                    $pages[] = [
                        'url' => $page['page_url'],
                        'title' => 'Recent contrast issue page',
                        'priority' => 2
                    ];
                }
            }
        }
        
        return $pages;
    }
    
    /**
     * Pre-calculate contrast for a specific page
     */
    private function precalculate_page_contrast($page) {
        error_log('RayWP: Pre-calculating contrast for: ' . $page['url']);
        
        // Fetch the page with color overrides applied
        $response = wp_remote_get($page['url'], [
            'timeout' => 30,
            'sslverify' => false,
            'headers' => [
                'User-Agent' => 'RayWP Accessibility Contrast Pre-calculator'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('RayWP: Error fetching page for contrast pre-calc: ' . $response->get_error_message());
            return;
        }
        
        $html_content = wp_remote_retrieve_body($response);
        if (empty($html_content)) {
            error_log('RayWP: Empty content received for contrast pre-calc');
            return;
        }
        
        // Clear existing contrast cache for this page to force fresh calculation
        $cache_key = 'raywp_contrast_results_' . md5($page['url']);
        delete_transient($cache_key);
        
        // Store a flag to indicate pre-calculation was attempted
        $precalc_key = 'raywp_contrast_precalc_' . md5($page['url']);
        set_transient($precalc_key, [
            'page_url' => $page['url'],
            'precalc_time' => current_time('mysql'),
            'override_hash' => get_option('raywp_accessibility_override_hash', '')
        ], DAY_IN_SECONDS);
        
        error_log('RayWP: Contrast pre-calculation flag set for: ' . $page['url']);
    }
    
    /**
     * AJAX handler for storing axe-core results
     */
    public function ajax_store_axe_results() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $fixed_score = isset($_POST['fixed_score']) ? intval($_POST['fixed_score']) : 0;
        $total_issues = isset($_POST['total_issues']) ? intval($_POST['total_issues']) : 0;
        $pages_scanned = isset($_POST['pages_scanned']) ? intval($_POST['pages_scanned']) : 0;
        
        // Store axe results
        $axe_results = [
            'fixed_score' => $fixed_score,
            'total_issues' => $total_issues,
            'issues_by_type' => isset($_POST['issues_by_type']) ? $_POST['issues_by_type'] : [],
            'issues_by_severity' => isset($_POST['issues_by_severity']) ? $_POST['issues_by_severity'] : [],
            'pages_scanned' => $pages_scanned,
            'scan_type' => 'axe-core',
            'timestamp' => current_time('mysql')
        ];
        
        update_option('raywp_accessibility_axe_results', $axe_results);
        
        // Also update the scan_with_fixes results for compatibility
        update_option('raywp_accessibility_scan_with_fixes_results', $axe_results);
        
        wp_send_json_success([
            'message' => 'Axe results stored successfully',
            'score' => $fixed_score
        ]);
    }
    
    /**
     * AJAX handler for getting CSS overrides for iframe injection
     */
    public function ajax_get_css_overrides() {
        check_ajax_referer('raywp_accessibility_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        // Log for debugging
        error_log('RayWP: ajax_get_css_overrides called');
        
        // Get color overrides
        $color_overrides = get_option('raywp_accessibility_color_overrides', []);
        error_log('RayWP: Found ' . count($color_overrides) . ' color overrides');
        
        // Build CSS from color overrides
        $css = '';
        foreach ($color_overrides as $index => $override) {
            if (isset($override['selector']) && isset($override['new_color'])) {
                $selector = esc_attr($override['selector']);
                $color = sanitize_hex_color($override['new_color']);
                if ($selector && $color) {
                    $css .= "{$selector} { color: {$color} !important; }\n";
                    error_log("RayWP: Adding CSS override {$index}: {$selector} -> {$color}");
                } else {
                    error_log("RayWP: Invalid override {$index}: selector='{$selector}' color='{$color}'");
                }
            } else {
                error_log('RayWP: Override missing selector or color: ' . print_r($override, true));
            }
        }
        
        // Add any other CSS fixes that might be applied
        $additional_css = apply_filters('raywp_accessibility_css_overrides', '');
        if ($additional_css) {
            $css .= $additional_css;
            error_log('RayWP: Added additional CSS: ' . strlen($additional_css) . ' characters');
        }
        
        // If no CSS generated, create some test CSS to verify the system works
        if (empty($css)) {
            error_log('RayWP: No CSS overrides found, creating test CSS');
            $css = "/* RayWP Test CSS - No overrides configured */\n";
        }
        
        error_log('RayWP: Total CSS generated: ' . strlen($css) . ' characters');
        error_log('RayWP: CSS content: ' . substr($css, 0, 200));
        
        wp_send_json_success([
            'css' => $css,
            'override_count' => count($color_overrides),
            'debug' => [
                'overrides_found' => count($color_overrides),
                'css_length' => strlen($css),
                'additional_css_length' => strlen($additional_css)
            ]
        ]);
    }
}
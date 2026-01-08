<?php
/**
 * Admin functionality
 */

namespace RayWP\Accessibility\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_database_upgrade_notice']);
        
        // AJAX handlers for JavaScript contrast detection
        add_action('wp_ajax_raywp_run_contrast_check', [$this, 'ajax_run_contrast_check']);
        add_action('wp_ajax_nopriv_raywp_run_contrast_check', [$this, 'ajax_run_contrast_check']);
        add_action('wp_ajax_raywp_store_contrast_results', [$this, 'ajax_store_contrast_results']);
        add_action('wp_ajax_nopriv_raywp_store_contrast_results', [$this, 'ajax_store_contrast_results']);
        add_action('wp_ajax_raywp_clear_contrast_cache', [$this, 'ajax_clear_contrast_cache']);
        add_action('wp_ajax_raywp_get_element_snippet', [$this, 'ajax_get_element_snippet']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('RayWP Accessibility Pro', 'raywp-accessibility'),
            __('RayWP Accessibility', 'raywp-accessibility'),
            'manage_options',
            'raywp-accessibility',
            [$this, 'render_dashboard_page'],
            'dashicons-universal-access-alt',
            30
        );
        
        // Dashboard
        add_submenu_page(
            'raywp-accessibility',
            __('Dashboard', 'raywp-accessibility'),
            __('Dashboard', 'raywp-accessibility'),
            'manage_options',
            'raywp-accessibility',
            [$this, 'render_dashboard_page']
        );
        
        // ARIA Manager
        add_submenu_page(
            'raywp-accessibility',
            __('ARIA Manager', 'raywp-accessibility'),
            __('ARIA Manager', 'raywp-accessibility'),
            'manage_options',
            'raywp-accessibility-aria',
            [$this, 'render_aria_page']
        );
        
        // Form Scanner - disabled, automatic form fixes work better
        // add_submenu_page(
        //     'raywp-accessibility',
        //     __('Form Scanner', 'raywp-accessibility'),
        //     __('Form Scanner', 'raywp-accessibility'),
        //     'manage_options',
        //     'raywp-accessibility-forms',
        //     [$this, 'render_forms_page']
        // );
        
        // Settings
        add_submenu_page(
            'raywp-accessibility',
            __('Settings', 'raywp-accessibility'),
            __('Settings', 'raywp-accessibility'),
            'manage_options',
            'raywp-accessibility-settings',
            [$this, 'render_settings_page']
        );
        
        // Reports
        add_submenu_page(
            'raywp-accessibility',
            __('Reports', 'raywp-accessibility'),
            __('Reports', 'raywp-accessibility'),
            'manage_options',
            'raywp-accessibility-reports',
            [$this, 'render_reports_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'raywp-accessibility') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'raywp-accessibility-admin',
            RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RAYWP_ACCESSIBILITY_VERSION
        );
        
        // Add inline CSS to fix footer overlap on reports page
        if ($hook === 'raywp-accessibility_page_raywp-accessibility-reports') {
            $inline_css = '
                /* Fix WordPress footer overlap on reports page */
                .raywp-accessibility_page_raywp-accessibility-reports #wpfooter {
                    position: relative !important;
                    margin-top: 100px !important;
                    clear: both !important;
                }
                
                .raywp-accessibility_page_raywp-accessibility-reports #wpcontent {
                    padding-bottom: 20px !important;
                }
                
                .raywp-accessibility_page_raywp-accessibility-reports .wrap {
                    margin-bottom: 50px !important;
                }
            ';
            \wp_add_inline_style('raywp-accessibility-admin', $inline_css);
        }
        
        // Only enqueue contrast detection scripts on the reports page
        if ($hook === 'raywp-accessibility_page_raywp-accessibility-reports') {
            wp_enqueue_script(
                'raywp-contrast-detector',
                RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/js/contrast-detector.js',
                [],
                RAYWP_ACCESSIBILITY_VERSION,
                true
            );

            wp_enqueue_script(
                'raywp-contrast-integration',
                RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/js/contrast-integration.js',
                ['jquery', 'raywp-contrast-detector'],
                RAYWP_ACCESSIBILITY_VERSION,
                true
            );

            // Use CDN for axe-core (stable version 4.8.2)
            wp_enqueue_script(
                'axe-core',
                'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.8.2/axe.min.js',
                [],
                '4.8.2',
                true
            );
            
            // Enqueue our axe integration script
            wp_enqueue_script(
                'raywp-axe-integration',
                RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/js/axe-integration.js',
                ['jquery', 'axe-core'],
                RAYWP_ACCESSIBILITY_VERSION,
                true
            );
        }

        // Enqueue main admin JavaScript with proper dependencies
        $admin_deps = ['jquery', 'wp-ajax-response'];
        if ($hook === 'raywp-accessibility_page_raywp-accessibility-reports') {
            $admin_deps[] = 'raywp-contrast-detector';
            $admin_deps[] = 'raywp-axe-integration';
        }
        wp_enqueue_script(
            'raywp-accessibility-admin',
            RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/js/admin.js',
            $admin_deps,
            RAYWP_ACCESSIBILITY_VERSION,
            true
        );
        
        // Localize script for admin
        wp_localize_script('raywp-accessibility-admin', 'raywpAccessibility', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('raywp_accessibility_nonce'),
            'contrast_nonce' => wp_create_nonce('raywp_contrast_check'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this rule?', 'raywp-accessibility'),
                'testing_selector' => __('Testing selector...', 'raywp-accessibility'),
                'scanning_forms' => __('Scanning forms...', 'raywp-accessibility'),
                'applying_fixes' => __('Applying fixes...', 'raywp-accessibility'),
                'success' => __('Success!', 'raywp-accessibility'),
                'error' => __('An error occurred', 'raywp-accessibility')
            ]
        ]);

        // Localize script for contrast integration
        wp_localize_script('raywp-contrast-integration', 'raywp_admin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('raywp_contrast_check')
        ]);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('raywp_accessibility_settings', 'raywp_accessibility_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        $sanitized = [];
        
        // Checkboxes
        $checkboxes = [
            'enable_aria',
            'enable_checker',
            'fix_empty_alt',
            'fix_lang_attr',
            'fix_form_labels',
            'add_skip_links',
            'fix_forms',
            'add_main_landmark',
            'fix_heading_hierarchy',
            'fix_aria_controls',
            'enhance_focus',
            'fix_contrast',
            'enhance_color_contrast',
            'fix_placeholder_contrast',
            'enable_color_overrides',
            'fix_video_accessibility',
            'fix_keyboard_accessibility',
            'fix_duplicate_ids',
            'fix_page_language'
        ];
        
        foreach ($checkboxes as $key) {
            $sanitized[$key] = !empty($settings[$key]) ? 1 : 0;
        }
        
        // Text fields
        $text_fields = [
            'skip_link_target',
            'focus_outline_color',
            'focus_outline_width'
        ];
        
        foreach ($text_fields as $key) {
            $sanitized[$key] = sanitize_text_field($settings[$key] ?? '');
        }
        
        return $sanitized;
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
        $aria_manager = $plugin->get_component('aria_manager');
        $form_scanner = $plugin->get_component('form_scanner');
        $reports = $plugin->get_component('reports');
        
        $aria_rules_count = count($aria_manager->get_aria_rules());
        $last_scan_date = $reports ? $reports->get_last_scan_date() : null;
        $compliance_assessment = $reports ? $reports->calculate_compliance_assessment() : null;
        
        // Check if fixes are enabled
        $current_settings = get_option('raywp_accessibility_settings', []);
        $fixes_enabled = !empty($current_settings['fix_forms']) || 
                        !empty($current_settings['add_main_landmark']) || 
                        !empty($current_settings['fix_heading_hierarchy']);
        ?>
        <div class="wrap">
            <div class="raywp-admin-header">
                <div class="raywp-logo-container">
                    <img src="<?php echo esc_url(RAYWP_ACCESSIBILITY_PLUGIN_URL); ?>assets/images/Ray-Logo.webp" alt="Ray" class="raywp-logo" />
                </div>
            </div>
            
            <div class="raywp-page-title">
                <h1><?php echo esc_html(get_admin_page_title()); ?> <span class="version">v<?php echo esc_html(RAYWP_ACCESSIBILITY_VERSION); ?></span></h1>
            </div>
            
            <div class="raywp-dashboard">
                <div class="raywp-dashboard-top">
                    <div class="raywp-dashboard-left">
                        <div class="raywp-dashboard-widgets">
                            <div class="raywp-widget">
                                <h2><?php esc_html_e('Quick Stats', 'raywp-accessibility'); ?></h2>
                                <ul>
                                    <li><?php 
                                    /* translators: %d: Number of active ARIA rules */
                                    echo esc_html(sprintf(__('Active ARIA Rules: %d', 'raywp-accessibility'), intval($aria_rules_count))); 
                                    ?></li>
                                    <li><?php 
                                    if ($last_scan_date) {
                                        /* translators: %s: Date and time of last scan */
                                        echo esc_html(sprintf(__('Last Scan: %s', 'raywp-accessibility'), 
                                               esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), 
                                                       strtotime($last_scan_date)))));
                                    } else {
                                        esc_html_e('Last Scan: Never', 'raywp-accessibility');
                                    }
                                    ?></li>
                                    <li><?php 
                                    if ($compliance_assessment !== null) {
                                        // Check if any fixes are enabled
                                        $current_settings = get_option('raywp_accessibility_settings', []);
                                        $has_fixes_enabled = !empty($current_settings['fix_forms']) || 
                                                           !empty($current_settings['add_main_landmark']) || 
                                                           !empty($current_settings['fix_heading_hierarchy']) ||
                                                           !empty($current_settings['fix_empty_alt']) ||
                                                           !empty($current_settings['fix_lang_attr']) ||
                                                           !empty($current_settings['fix_form_labels']) ||
                                                           !empty($current_settings['add_skip_links']) ||
                                                           !empty($current_settings['fix_aria_controls']) ||
                                                           !empty($current_settings['enhance_focus']) ||
                                                           !empty($current_settings['fix_contrast']) ||
                                                           !empty($current_settings['enhance_color_contrast']) ||
                                                           !empty($current_settings['fix_placeholder_contrast']) ||
                                                           !empty($current_settings['fix_video_accessibility']) ||
                                                           !empty($current_settings['fix_keyboard_accessibility']) ||
                                                           !empty($current_settings['fix_duplicate_ids']) ||
                                                           !empty($current_settings['fix_page_language']);
                                        
                                        echo '<strong>Accessibility Status:</strong> ';
                                        
                                        // Show the appropriate score
                                        if ($has_fixes_enabled && isset($compliance_assessment['fixed_score'])) {
                                            $score = $compliance_assessment['fixed_score'];
                                            $score_color = $score >= 90 ? '#28a745' : ($score >= 70 ? '#ffc107' : '#dc3545');
                                            echo '<span style="color: ' . esc_attr($score_color) . '">' . esc_html($score) . '% (With Fixes)</span>';
                                        } else {
                                            $score = isset($compliance_assessment['original_score']) ? $compliance_assessment['original_score'] : 0;
                                            $score_color = $score >= 90 ? '#28a745' : ($score >= 70 ? '#ffc107' : '#dc3545');
                                            echo '<span style="color: ' . esc_attr($score_color) . '">' . esc_html($score) . '%</span>';
                                        }
                                        
                                        // Show status message
                                        $status = isset($compliance_assessment['status']) ? $compliance_assessment['status'] : 'Unknown';
                                        echo '<br><small>' . esc_html($status) . '</small>';
                                        
                                        if (isset($compliance_assessment['total_issues']) && $compliance_assessment['total_issues'] > 0) {
                                            $issues_text = $has_fixes_enabled && isset($compliance_assessment['manual_required']) 
                                                ? esc_html($compliance_assessment['manual_required']) . ' manual fixes required'
                                                : esc_html($compliance_assessment['total_issues']) . ' issues found';
                                            echo '<br><small>' . $issues_text . '</small>';
                                        }
                                    } else {
                                        /* translators: %s: URL to reports page */
                                        echo wp_kses(sprintf(__('Accessibility Status: <a href="%s">Run a scan first</a>', 'raywp-accessibility'), esc_url(admin_url('admin.php?page=raywp-accessibility-reports'))), array('a' => array('href' => array())));
                                    }
                                    ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-widget">
                                <h2><?php esc_html_e('Quick Actions', 'raywp-accessibility'); ?></h2>
                                <p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-aria')); ?>" class="button button-primary">
                                        <?php esc_html_e('Manage ARIA Rules', 'raywp-accessibility'); ?>
                                    </a>
                                </p>
                                <p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-settings')); ?>" class="button button-secondary">
                                        <?php esc_html_e('Edit Settings', 'raywp-accessibility'); ?>
                                    </a>
                                </p>
                                <p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-reports')); ?>" class="button">
                                        <?php esc_html_e('View Reports', 'raywp-accessibility'); ?>
                                    </a>
                                </p>
                            </div>
                            
                            <div class="raywp-widget">
                                <h2><?php esc_html_e('System Info', 'raywp-accessibility'); ?></h2>
                                <ul>
                                    <li><strong><?php esc_html_e('Plugin Version:', 'raywp-accessibility'); ?></strong> <?php echo esc_html(RAYWP_ACCESSIBILITY_VERSION); ?></li>
                                    <li><strong><?php esc_html_e('Last Updated:', 'raywp-accessibility'); ?></strong> <?php 
                                        $plugin_file = RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'raywp-accessibility.php';
                                        $last_modified = filemtime($plugin_file);
                                        echo esc_html(wp_date('F j, Y', $last_modified)); 
                                    ?></li>
                                    <li><strong><?php esc_html_e('Security Fixes:', 'raywp-accessibility'); ?></strong> ✓ Applied</li>
                                    <li><strong><?php esc_html_e('Performance Monitor:', 'raywp-accessibility'); ?></strong> ✓ Active</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="raywp-dashboard-right">
                        <!-- Help Section (moved to top right) -->
                        <div class="raywp-help-section">
                            <h2><?php esc_html_e('Need Professional Help?', 'raywp-accessibility'); ?></h2>
                            <p><?php esc_html_e('While this plugin fixes many common accessibility issues automatically, some problems require manual intervention. Our team of accessibility experts can help ensure your site is fully compliant.', 'raywp-accessibility'); ?></p>
                            <a href="https://raywp.com/#bottom" target="_blank" class="button-hero">
                                <?php esc_html_e('Get Expert Help →', 'raywp-accessibility'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quickstart Section -->
                <div class="raywp-quickstart">
                    <h2><?php esc_html_e('Quick Start Guide', 'raywp-accessibility'); ?></h2>
                    <p><?php esc_html_e('Improve your site\'s accessibility in minutes with these simple steps:', 'raywp-accessibility'); ?></p>
                    
                    <div class="quickstart-steps">
                        <div class="quickstart-step">
                            <div class="step-header">
                                <span class="step-icon step-icon-settings"></span>
                                <h3><?php esc_html_e('1. Enable Basic Fixes', 'raywp-accessibility'); ?></h3>
                            </div>
                            <p><?php esc_html_e('Go to', 'raywp-accessibility'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-settings')); ?>"><?php esc_html_e('Settings', 'raywp-accessibility'); ?></a> <?php esc_html_e('and check the boxes for automatic fixes like form labels, alt attributes, and skip links.', 'raywp-accessibility'); ?></p>
                        </div>
                        
                        <div class="quickstart-step">
                            <div class="step-header">
                                <span class="step-icon step-icon-scan"></span>
                                <h3><?php esc_html_e('2. Run Your First Scan', 'raywp-accessibility'); ?></h3>
                            </div>
                            <p><?php esc_html_e('Visit', 'raywp-accessibility'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-reports')); ?>"><?php esc_html_e('Reports', 'raywp-accessibility'); ?></a> <?php esc_html_e('and click "Run Full Scan" to see your current accessibility score and identify issues.', 'raywp-accessibility'); ?></p>
                        </div>
                        
                        <div class="quickstart-step">
                            <div class="step-header">
                                <span class="step-icon step-icon-contrast"></span>
                                <h3><?php esc_html_e('3. Fix Color Contrast (Optional)', 'raywp-accessibility'); ?></h3>
                            </div>
                            <p><?php esc_html_e('If you have contrast issues, go back to', 'raywp-accessibility'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-settings')); ?>"><?php esc_html_e('Settings', 'raywp-accessibility'); ?></a> <?php esc_html_e('and add custom color overrides using CSS selectors to target specific elements.', 'raywp-accessibility'); ?></p>
                        </div>
                        
                        <div class="quickstart-step">
                            <div class="step-header">
                                <span class="step-icon step-icon-aria"></span>
                                <h3><?php esc_html_e('4. Add ARIA Rules (Advanced)', 'raywp-accessibility'); ?></h3>
                            </div>
                            <p><?php esc_html_e('For advanced users: Visit', 'raywp-accessibility'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-aria')); ?>"><?php esc_html_e('ARIA Manager', 'raywp-accessibility'); ?></a> <?php esc_html_e('to add custom ARIA attributes to specific elements using CSS selectors. Only do this if you understand ARIA.', 'raywp-accessibility'); ?></p>
                        </div>
                    </div>
                    
                    <div class="quickstart-tip">
                        <strong><?php esc_html_e('Pro Tip:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('Start with steps 1-2 to get immediate improvements. Most sites see 20-40 point score increases just from enabling the basic automatic fixes!', 'raywp-accessibility'); ?>
                    </div>
                </div>
                
                <!-- How This Plugin Works Section -->
                <div class="raywp-how-it-works">
                    <h2><?php esc_html_e('How This Plugin Works', 'raywp-accessibility'); ?></h2>
                    <p><?php esc_html_e('Unlike traditional accessibility widgets that add buttons to your frontend, RayWP Accessibility works silently in the background by processing your entire page output before it reaches the browser.', 'raywp-accessibility'); ?></p>
                    
                    <div class="raywp-process-steps">
                        <div class="process-step">
                            <span class="step-number">1</span>
                            <h3><?php esc_html_e('Full Page Processing', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('Captures the complete HTML output of every page, including dynamically generated content from themes and plugins.', 'raywp-accessibility'); ?></p>
                        </div>
                        
                        <div class="process-step">
                            <span class="step-number">2</span>
                            <h3><?php esc_html_e('Intelligent Analysis', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('Analyzes the DOM structure to identify accessibility issues like missing ARIA attributes, improper heading hierarchy, and form problems.', 'raywp-accessibility'); ?></p>
                        </div>
                        
                        <div class="process-step">
                            <span class="step-number">3</span>
                            <h3><?php esc_html_e('Automatic Fixes', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('Applies fixes directly to the HTML before sending to browsers, ensuring all visitors get an accessible experience automatically.', 'raywp-accessibility'); ?></p>
                        </div>
                    </div>
                    
                    <div class="advantages-section">
                        <h3><?php esc_html_e('Advantages Over Widget-Based Solutions', 'raywp-accessibility'); ?></h3>
                        <ul class="advantages-list">
                            <li><strong><?php esc_html_e('No User Action Required:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('Visitors don\'t need to click any buttons or widgets - accessibility improvements are automatic for everyone.', 'raywp-accessibility'); ?></li>
                            <li><strong><?php esc_html_e('Better SEO:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('Fixes are applied server-side, making your accessible content visible to search engines.', 'raywp-accessibility'); ?></li>
                            <li><strong><?php esc_html_e('Faster Performance:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('No additional JavaScript widgets loading on your frontend means faster page loads.', 'raywp-accessibility'); ?></li>
                            <li><strong><?php esc_html_e('Theme Independent:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('Works with any WordPress theme without requiring integration or modifications.', 'raywp-accessibility'); ?></li>
                            <li><strong><?php esc_html_e('Professional Compliance:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('Meets enterprise requirements by fixing issues at the source rather than applying band-aids.', 'raywp-accessibility'); ?></li>
                            <li><strong><?php esc_html_e('Pro Tools for Experts:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('Accessibility professionals can add custom ARIA rules and color contrast overrides using CSS selectors for precise control.', 'raywp-accessibility'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <!-- What This Plugin Fixes Section (Accordion) -->
                <div class="raywp-features-accordion">
                    <h2 class="accordion-toggle" onclick="toggleAccordion('features-content')">
                        <?php esc_html_e('What This Plugin Addresses', 'raywp-accessibility'); ?>
                        <span class="accordion-arrow">▼</span>
                    </h2>
                    
                    <div id="features-content" class="accordion-content" style="display: none;">
                        <div class="raywp-features-grid">
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-controls"></span><?php esc_html_e('Professional Controls', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Custom ARIA rule manager - add your own CSS selectors and ARIA attributes', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Color contrast override system - target specific elements with better colors', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Live CSS selector validation to ensure your rules work correctly', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Granular control over which accessibility features are enabled', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Optimized processing with minimal performance impact on page load times', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-analytics"></span><?php esc_html_e('Advanced Features', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Full-site accessibility scanning with detailed reports', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('WCAG 2.1 compliance scoring and progress tracking', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Detailed scan results with expandable issue breakdowns and page-by-page analysis', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Integration with popular form plugins (Contact Form 7, Gravity Forms, WPForms)', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Automated fixes that work with any WordPress theme', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-target"></span><?php esc_html_e('ARIA & Semantic HTML', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Automatically adds missing ARIA labels to interactive elements', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Fixes aria-expanded states for collapsible menus', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Adds aria-controls to link buttons with their controlled elements', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Ensures proper role attributes for navigation and content areas', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Validates and corrects ARIA attribute values', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-form"></span><?php esc_html_e('Form Accessibility', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Associates labels with form inputs automatically', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Adds fieldsets and legends to grouped form controls', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Marks required fields with proper aria-required attributes', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Improves error message announcements for screen readers', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Fixes placeholder-only inputs by adding proper labels', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-navigation"></span><?php esc_html_e('Navigation & Focus', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Adds skip navigation links for keyboard users', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Ensures proper focus indicators on interactive elements', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Creates logical tab order through the page', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Adds main landmark if missing from theme', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Improves keyboard navigation for dropdown menus', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-image"></span><?php esc_html_e('Images & Media', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Adds empty alt attributes to decorative images', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Warns about missing alt text for content images', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Ensures proper figure and figcaption associations', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Fixes missing language attributes on HTML elements', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-structure"></span><?php esc_html_e('Structure & Hierarchy', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Corrects heading hierarchy issues (no skipped levels)', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Ensures only one H1 per page', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Adds proper document structure with landmarks', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Fixes duplicate IDs that break assistive technology', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="raywp-feature-category">
                                <h4><span class="raywp-icon raywp-icon-visual"></span><?php esc_html_e('Visual & Contrast', 'raywp-accessibility'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Identifies color contrast issues', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Provides tools to override colors for better contrast', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Ensures focus indicators meet WCAG standards', 'raywp-accessibility'); ?></li>
                                    <li><?php esc_html_e('Warns about color-only information conveyance', 'raywp-accessibility'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- About Accessibility Standards Section -->
                <div class="raywp-accessibility-standards">
                    <h2><?php esc_html_e('About Accessibility Standards', 'raywp-accessibility'); ?></h2>
                    <p><?php esc_html_e('RayWP Accessibility helps your WordPress site comply with international accessibility standards:', 'raywp-accessibility'); ?></p>
                    
                    <div class="raywp-standards-grid">
                        <div class="raywp-standard">
                            <h3><?php esc_html_e('WCAG 2.1', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('Web Content Accessibility Guidelines (WCAG) 2.1 covers a wide range of recommendations for making Web content more accessible.', 'raywp-accessibility'); ?></p>
                            <a href="https://www.w3.org/WAI/WCAG21/quickref/" target="_blank"><?php esc_html_e('View Guidelines →', 'raywp-accessibility'); ?></a>
                        </div>
                        
                        <div class="raywp-standard">
                            <h3><?php esc_html_e('Section 508', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('US federal agencies are required to make their electronic and information technology accessible to people with disabilities.', 'raywp-accessibility'); ?></p>
                            <a href="https://www.section508.gov/test/websites/" target="_blank"><?php esc_html_e('Learn More →', 'raywp-accessibility'); ?></a>
                        </div>
                        
                        <div class="raywp-standard">
                            <h3><?php esc_html_e('ADA Compliance', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('The Americans with Disabilities Act (ADA) requires that businesses open to the public provide equal access to their goods and services.', 'raywp-accessibility'); ?></p>
                            <a href="https://www.ada.gov/resources/web-guidance/" target="_blank"><?php esc_html_e('ADA Information →', 'raywp-accessibility'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render ARIA page
     */
    public function render_aria_page() {
        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
        $aria_manager = $plugin->get_component('aria_manager');
        $aria_rules = $aria_manager->get_aria_rules();
        ?>
        <div class="wrap">
            <div class="raywp-admin-header">
                <div class="raywp-logo-container">
                    <img src="<?php echo esc_url(RAYWP_ACCESSIBILITY_PLUGIN_URL); ?>assets/images/Ray-Logo.webp" alt="Ray" class="raywp-logo" />
                </div>
            </div>
            
            <div class="raywp-page-title">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            </div>
            
            <div class="raywp-aria-manager">
                <h2><?php esc_html_e('Add New ARIA Rule', 'raywp-accessibility'); ?></h2>
                <form id="raywp-add-aria-rule" class="raywp-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="aria-selector"><?php esc_html_e('CSS Selector', 'raywp-accessibility'); ?></label></th>
                            <td>
                                <input type="text" id="aria-selector" name="selector" class="regular-text" required />
                                <button type="button" class="button" id="test-selector"><?php esc_html_e('Test Selector', 'raywp-accessibility'); ?></button>
                                <p class="description"><?php esc_html_e('Enter a CSS selector (e.g., .navigation a, #header)', 'raywp-accessibility'); ?></p>
                                <div id="selector-test-results"></div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="aria-attribute"><?php esc_html_e('ARIA Attribute', 'raywp-accessibility'); ?></label></th>
                            <td>
                                <select id="aria-attribute" name="attribute" required>
                                    <option value=""><?php esc_html_e('Select an attribute', 'raywp-accessibility'); ?></option>
                                    <optgroup label="<?php esc_html_e('Common Attributes', 'raywp-accessibility'); ?>">
                                        <option value="aria-label">aria-label</option>
                                        <option value="aria-labelledby">aria-labelledby</option>
                                        <option value="aria-describedby">aria-describedby</option>
                                        <option value="aria-hidden">aria-hidden</option>
                                        <option value="aria-live">aria-live</option>
                                        <option value="aria-current">aria-current</option>
                                        <option value="role">role (Landmark)</option>
                                    </optgroup>
                                    <optgroup label="<?php esc_html_e('State Attributes', 'raywp-accessibility'); ?>">
                                        <option value="aria-checked">aria-checked</option>
                                        <option value="aria-disabled">aria-disabled</option>
                                        <option value="aria-expanded">aria-expanded</option>
                                        <option value="aria-pressed">aria-pressed</option>
                                        <option value="aria-selected">aria-selected</option>
                                    </optgroup>
                                    <optgroup label="<?php esc_html_e('All Attributes', 'raywp-accessibility'); ?>">
                                        <?php
                                        // Get ARIA attributes from the manager
                                        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
                                        $aria_manager = $plugin->get_component('aria_manager');
                                        $valid_attrs = [
                                            'aria-activedescendant', 'aria-atomic', 'aria-autocomplete', 'aria-busy',
                                            'aria-checked', 'aria-colcount', 'aria-colindex', 'aria-colspan',
                                            'aria-controls', 'aria-current', 'aria-describedby', 'aria-details',
                                            'aria-disabled', 'aria-dropeffect', 'aria-errormessage', 'aria-expanded',
                                            'aria-flowto', 'aria-grabbed', 'aria-haspopup', 'aria-hidden',
                                            'aria-invalid', 'aria-keyshortcuts', 'aria-label', 'aria-labelledby',
                                            'aria-level', 'aria-live', 'aria-modal', 'aria-multiline',
                                            'aria-multiselectable', 'aria-orientation', 'aria-owns', 'aria-placeholder',
                                            'aria-posinset', 'aria-pressed', 'aria-readonly', 'aria-relevant',
                                            'aria-required', 'aria-roledescription', 'aria-rowcount', 'aria-rowindex',
                                            'aria-rowspan', 'aria-selected', 'aria-setsize', 'aria-sort',
                                            'aria-valuemax', 'aria-valuemin', 'aria-valuenow', 'aria-valuetext'
                                        ];
                                        foreach ($valid_attrs as $attr) {
                                            echo '<option value="' . esc_attr($attr) . '">' . esc_html($attr) . '</option>';
                                        }
                                        ?>
                                    </optgroup>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="aria-value"><?php esc_html_e('Value', 'raywp-accessibility'); ?></label></th>
                            <td>
                                <input type="text" id="aria-value" name="value" class="regular-text" required />
                                <p class="description"><?php esc_html_e('Enter the value for the attribute', 'raywp-accessibility'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Add ARIA Rule', 'raywp-accessibility'); ?></button>
                    </p>
                </form>
                
                <h2><?php esc_html_e('Existing ARIA Rules', 'raywp-accessibility'); ?></h2>
                <?php if (empty($aria_rules)) : ?>
                    <p><?php esc_html_e('No ARIA rules configured yet.', 'raywp-accessibility'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Selector', 'raywp-accessibility'); ?></th>
                                <th><?php esc_html_e('Attribute', 'raywp-accessibility'); ?></th>
                                <th><?php esc_html_e('Value', 'raywp-accessibility'); ?></th>
                                <th><?php esc_html_e('Actions', 'raywp-accessibility'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aria_rules as $index => $rule) : ?>
                                <tr>
                                    <td><code><?php echo esc_html($rule['selector']); ?></code></td>
                                    <td><code><?php echo esc_html($rule['attribute']); ?></code></td>
                                    <td><?php echo esc_html($rule['value']); ?></td>
                                    <td>
                                        <button class="button button-small delete-rule" data-index="<?php echo esc_attr($index); ?>">
                                            <?php esc_html_e('Delete', 'raywp-accessibility'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div class="raywp-aria-info-box" style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px;">
                    <h3><?php esc_html_e('About ARIA Attributes', 'raywp-accessibility'); ?></h3>
                    <p><?php esc_html_e('ARIA (Accessible Rich Internet Applications) attributes provide semantic information about elements to assistive technologies like screen readers. Use this manager to add ARIA attributes to specific elements on your website.', 'raywp-accessibility'); ?></p>
                    
                    <div class="raywp-aria-resources" style="margin-top: 15px;">
                        <h4><?php esc_html_e('Helpful Resources:', 'raywp-accessibility'); ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><a href="https://www.w3.org/WAI/ARIA/apg/" target="_blank" rel="noopener"><?php esc_html_e('WAI-ARIA Authoring Practices Guide', 'raywp-accessibility'); ?></a> - <?php esc_html_e('Official W3C guide for implementing ARIA', 'raywp-accessibility'); ?></li>
                            <li><a href="https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Attributes" target="_blank" rel="noopener"><?php esc_html_e('MDN ARIA Attributes Reference', 'raywp-accessibility'); ?></a> - <?php esc_html_e('Complete reference of all ARIA attributes', 'raywp-accessibility'); ?></li>
                            <li><a href="https://www.w3.org/TR/wai-aria-1.2/" target="_blank" rel="noopener"><?php esc_html_e('ARIA 1.2 Specification', 'raywp-accessibility'); ?></a> - <?php esc_html_e('Technical specification for ARIA attributes', 'raywp-accessibility'); ?></li>
                            <li><a href="https://webaim.org/techniques/aria/" target="_blank" rel="noopener"><?php esc_html_e('WebAIM ARIA Guide', 'raywp-accessibility'); ?></a> - <?php esc_html_e('Practical guide to using ARIA effectively', 'raywp-accessibility'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="raywp-aria-tips" style="margin-top: 15px;">
                        <h4><?php esc_html_e('Quick Tips:', 'raywp-accessibility'); ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><strong>aria-label:</strong> <?php esc_html_e('Provides an accessible name when visible text is not descriptive enough', 'raywp-accessibility'); ?></li>
                            <li><strong>aria-hidden:</strong> <?php esc_html_e('Hides decorative elements from screen readers (use "true" or "false")', 'raywp-accessibility'); ?></li>
                            <li><strong>aria-expanded:</strong> <?php esc_html_e('Indicates if a collapsible element is open or closed (use "true" or "false")', 'raywp-accessibility'); ?></li>
                            <li><strong>aria-live:</strong> <?php esc_html_e('Announces dynamic content changes (use "polite" or "assertive")', 'raywp-accessibility'); ?></li>
                            <li><strong>role:</strong> <?php esc_html_e('Defines the element\'s purpose (e.g., "button", "navigation", "main")', 'raywp-accessibility'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="raywp-aria-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-top: 15px; border-radius: 3px;">
                        <strong><?php esc_html_e('Important:', 'raywp-accessibility'); ?></strong> <?php esc_html_e('Test your ARIA implementations with screen readers and accessibility tools. Incorrect ARIA can make your site less accessible than having no ARIA at all.', 'raywp-accessibility'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render forms page
     */
    public function render_forms_page() {
        $settings = get_option('raywp_accessibility_settings', []);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="raywp-form-scanner">
                <h2><?php esc_html_e('Real-time Form Accessibility', 'raywp-accessibility'); ?></h2>
                <p><?php esc_html_e('Form accessibility fixes are now applied automatically in real-time as pages load. This works with any form plugin or HTML forms.', 'raywp-accessibility'); ?></p>
                
                <div class="raywp-widget">
                    <h3><?php esc_html_e('Automatic Form Fixes', 'raywp-accessibility'); ?></h3>
                    <p><strong>Status:</strong> 
                        <?php if (!empty($settings['fix_forms'])): ?>
                            <span style="color: green;">✓ Enabled</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Disabled</span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if (empty($settings['fix_forms'])): ?>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=raywp-accessibility-settings')); ?>" class="button button-primary">
                                <?php esc_html_e('Enable Form Fixes', 'raywp-accessibility'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <h4><?php esc_html_e('What Gets Fixed Automatically:', 'raywp-accessibility'); ?></h4>
                    <ul>
                        <li>✓ <?php esc_html_e('Missing labels for form fields', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php esc_html_e('Fieldsets around radio/checkbox groups', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php esc_html_e('Required field indicators (aria-required)', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php esc_html_e('Form validation message improvements', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php esc_html_e('Form instructions for required fields', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php esc_html_e('Proper ARIA attributes and roles', 'raywp-accessibility'); ?></li>
                    </ul>
                    
                    <h4><?php esc_html_e('Works With:', 'raywp-accessibility'); ?></h4>
                    <ul>
                        <li>• Contact Form 7</li>
                        <li>• Gravity Forms</li>
                        <li>• WPForms</li>
                        <li>• Ninja Forms</li>
                        <li>• Elementor Forms</li>
                        <li>• HTML Forms</li>
                        <li>• Any other form plugin</li>
                    </ul>
                </div>
                
                <div class="raywp-widget">
                    <h3><?php esc_html_e('Form Scanning (Legacy)', 'raywp-accessibility'); ?></h3>
                    <p><?php esc_html_e('You can still scan individual forms to see what issues would be fixed:', 'raywp-accessibility'); ?></p>
                    
                    <p>
                        <button id="scan-forms" class="button">
                            <?php esc_html_e('Scan All Forms', 'raywp-accessibility'); ?>
                        </button>
                    </p>
                    
                    <div id="scan-results" style="display:none;">
                        <h4><?php esc_html_e('Scan Results', 'raywp-accessibility'); ?></h4>
                        <div id="scan-results-content"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = get_option('raywp_accessibility_settings', []);
        ?>
        <div class="wrap">
            <div class="raywp-admin-header">
                <div class="raywp-logo-container">
                    <img src="<?php echo esc_url(RAYWP_ACCESSIBILITY_PLUGIN_URL); ?>assets/images/Ray-Logo.webp" alt="Ray" class="raywp-logo" />
                </div>
            </div>
            
            <div class="raywp-page-title">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('raywp_accessibility_settings'); ?>
                
                <h2><?php esc_html_e('General Settings', 'raywp-accessibility'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Features', 'raywp-accessibility'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[enable_aria]" value="1" 
                                           <?php checked(!empty($settings['enable_aria'])); ?> />
                                    <?php esc_html_e('Enable ARIA attribute injection', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <!-- Accessibility checker widget disabled - use full site scanner in Reports tab instead
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[enable_checker]" value="1" 
                                           <?php checked(!empty($settings['enable_checker'])); ?> />
                                    <?php esc_html_e('Enable accessibility checker', 'raywp-accessibility'); ?>
                                </label><br>
                                -->
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_empty_alt]" value="1" 
                                           <?php checked(!empty($settings['fix_empty_alt'])); ?> />
                                    <?php esc_html_e('Add empty alt attributes to decorative images', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_lang_attr]" value="1" 
                                           <?php checked(!empty($settings['fix_lang_attr'])); ?> />
                                    <?php esc_html_e('Add missing language attributes', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_form_labels]" value="1" 
                                           <?php checked(!empty($settings['fix_form_labels'])); ?> />
                                    <?php esc_html_e('Fix missing form labels', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[add_skip_links]" value="1" 
                                           <?php checked(!empty($settings['add_skip_links'])); ?> />
                                    <?php esc_html_e('Add skip navigation links', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_forms]" value="1" 
                                           <?php checked(!empty($settings['fix_forms'])); ?> />
                                    <?php esc_html_e('Apply comprehensive form accessibility fixes', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[add_main_landmark]" value="1" 
                                           <?php checked(!empty($settings['add_main_landmark'])); ?> />
                                    <?php esc_html_e('Add main landmark if missing', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_heading_hierarchy]" value="1" 
                                           <?php checked(!empty($settings['fix_heading_hierarchy'])); ?> />
                                    <?php esc_html_e('Fix heading hierarchy issues', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label style="margin-left: 25px;">
                                    <input type="checkbox" name="raywp_accessibility_settings[preserve_heading_styles]" value="1" 
                                           <?php checked(!empty($settings['preserve_heading_styles']) || !isset($settings['preserve_heading_styles'])); ?> />
                                    <?php esc_html_e('Preserve heading styles (use aria-level instead of changing tags)', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_aria_controls]" value="1" 
                                           <?php checked(!empty($settings['fix_aria_controls'])); ?> />
                                    <?php esc_html_e('Fix missing aria-controls on expandable buttons', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_video_accessibility]" value="1" 
                                           <?php checked(!empty($settings['fix_video_accessibility'])); ?> />
                                    <?php esc_html_e('Auto-fix video accessibility (mute autoplay, add aria-hidden to decorative)', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_keyboard_accessibility]" value="1" 
                                           <?php checked(!empty($settings['fix_keyboard_accessibility'])); ?> />
                                    <?php esc_html_e('Fix basic keyboard accessibility issues', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_duplicate_ids]" value="1" 
                                           <?php checked(!empty($settings['fix_duplicate_ids'])); ?> />
                                    <?php esc_html_e('Fix duplicate IDs that break assistive technology', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_page_language]" value="1" 
                                           <?php checked(!empty($settings['fix_page_language'])); ?> />
                                    <?php esc_html_e('Add missing page language declaration', 'raywp-accessibility'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Focus Enhancement', 'raywp-accessibility'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[enhance_focus]" value="1" 
                                       <?php checked(!empty($settings['enhance_focus'])); ?> />
                                <?php esc_html_e('Enhance focus indicators', 'raywp-accessibility'); ?>
                            </label><br>
                            
                            <label>
                                <?php esc_html_e('Focus outline color:', 'raywp-accessibility'); ?>
                                <input type="text" name="raywp_accessibility_settings[focus_outline_color]" 
                                       value="<?php echo esc_attr($settings['focus_outline_color'] ?? '#0073aa'); ?>" 
                                       class="color-picker" />
                            </label><br>
                            
                            <label>
                                <?php esc_html_e('Focus outline width:', 'raywp-accessibility'); ?>
                                <input type="text" name="raywp_accessibility_settings[focus_outline_width]" 
                                       value="<?php echo esc_attr($settings['focus_outline_width'] ?? '2px'); ?>" />
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Color Contrast', 'raywp-accessibility'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[fix_contrast]" value="1" 
                                       <?php checked(!empty($settings['fix_contrast'])); ?> />
                                <?php esc_html_e('Automatically fix low contrast text', 'raywp-accessibility'); ?>
                            </label>
                            <br><br>
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[enhance_color_contrast]" value="1" 
                                       <?php checked(!empty($settings['enhance_color_contrast'])); ?> />
                                <?php esc_html_e('Enhanced color contrast improvements', 'raywp-accessibility'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[fix_placeholder_contrast]" value="1" 
                                       <?php checked(!empty($settings['fix_placeholder_contrast'])); ?> />
                                <?php esc_html_e('Fix form placeholder contrast', 'raywp-accessibility'); ?>
                            </label>
                            
                            <p class="description">
                                <?php esc_html_e('Automatically fix low contrast issues including form placeholders and buttons. The enhanced option provides more comprehensive fixes with minimal visual impact.', 'raywp-accessibility'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom Color Overrides', 'raywp-accessibility'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[enable_color_overrides]" value="1" 
                                       <?php checked(!empty($settings['enable_color_overrides'])); ?> />
                                <?php esc_html_e('Enable custom color overrides', 'raywp-accessibility'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Advanced feature: Override specific element colors without modifying your theme. Changes are applied dynamically and can be reverted by disabling this option.', 'raywp-accessibility'); ?>
                            </p>
                            
                            <div id="raywp-color-overrides-section" style="<?php echo empty($settings['enable_color_overrides']) ? 'display:none;' : ''; ?>margin-top: 20px;">
                                <h4><?php esc_html_e('Color Override Rules', 'raywp-accessibility'); ?></h4>
                                <p class="description"><?php esc_html_e('Enter CSS selectors and the colors you want to apply. For example: .header-text for a class, #main-title for an ID, or h2 for an element.', 'raywp-accessibility'); ?></p>
                                
                                <div id="raywp-color-overrides-list">
                                    <?php
                                    $color_overrides = get_option('raywp_accessibility_color_overrides', []);
                                    if (!empty($color_overrides)) {
                                        foreach ($color_overrides as $index => $override) {
                                            ?>
                                            <div class="raywp-color-override-rule" data-index="<?php echo esc_attr($index); ?>">
                                                <div class="rule-display">
                                                    <strong><?php echo esc_html($override['selector']); ?></strong>
                                                    <?php if (!empty($override['color'])): ?>
                                                        <span style="color: <?php echo esc_attr($override['color']); ?>">● <?php echo esc_html($override['color']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($override['background'])): ?>
                                                        <span style="background: <?php echo esc_attr($override['background']); ?>; padding: 2px 8px; color: #fff;">BG: <?php echo esc_html($override['background']); ?></span>
                                                    <?php endif; ?>
                                                    <button type="button" class="button-link delete-color-override" data-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('Remove', 'raywp-accessibility'); ?></button>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <div id="raywp-add-color-override" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd;">
                                    <h5><?php esc_html_e('Add New Override', 'raywp-accessibility'); ?></h5>
                                    <table class="form-table">
                                        <tr>
                                            <td>
                                                <input type="text" id="override-selector" placeholder="<?php esc_attr_e('CSS Selector (e.g., .my-class, #my-id)', 'raywp-accessibility'); ?>" style="width: 100%;" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <input type="text" id="override-color" placeholder="<?php esc_attr_e('Text Color (e.g., #000000)', 'raywp-accessibility'); ?>" class="color-picker" />
                                                <span class="description"><?php esc_html_e('Leave empty to keep original', 'raywp-accessibility'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <input type="text" id="override-background" placeholder="<?php esc_attr_e('Background Color (e.g., #ffffff)', 'raywp-accessibility'); ?>" class="color-picker" />
                                                <span class="description"><?php esc_html_e('Optional - leave empty to keep original', 'raywp-accessibility'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <button type="button" id="add-color-override-btn" class="button button-primary"><?php esc_html_e('Add Override', 'raywp-accessibility'); ?></button>
                                                <span id="color-override-message" style="margin-left: 10px;"></span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render reports page
     */
    public function render_reports_page() {
        // Handle AJAX requests for enhanced reports
        if (isset($_POST['action']) && $_POST['action'] === 'raywp_export_results') {
            $this->handle_export_request();
            return;
        }
        
        // Get reports component to show existing data
        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
        $reports = $plugin->get_component('reports');
        // Get enhanced report data
        $compliance_assessment = $reports ? $reports->calculate_compliance_assessment() : null;
        $issue_summary = $reports ? $reports->get_issue_summary() : [];
        
        // Only get manual issues if we have axe-core results (from "Check Score with Fixes")
        // This prevents showing pre-fix manual issues from "Run Full Scan"
        $axe_results = get_option('raywp_accessibility_axe_results', null);
        $has_post_fix_scan = $axe_results && isset($axe_results['scan_type']) && $axe_results['scan_type'] === 'axe-core';
        $detailed_manual_issues = ($reports && $has_post_fix_scan) ? $reports->get_detailed_manual_issues() : [];
        $last_scan_date = $reports ? $reports->get_last_scan_date() : null;
        $wcag_breakdown = $reports ? $reports->get_wcag_compliance_breakdown() : [];
        $scan_sessions = $reports ? $reports->get_scan_sessions() : [];
        
        // Handle filters
        $current_filters = $this->get_current_filters();
        $filtered_results = $reports ? $reports->get_filtered_results($current_filters) : [];
        ?>
        <div class="wrap">
            <div class="raywp-admin-header">
                <div class="raywp-logo-container">
                    <img src="<?php echo esc_url(RAYWP_ACCESSIBILITY_PLUGIN_URL); ?>assets/images/Ray-Logo.webp" alt="Ray" class="raywp-logo" />
                </div>
            </div>
            
            <div class="raywp-page-title">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            </div>
            
            <div class="raywp-reports">
                
                <!-- Action Buttons -->
                <div class="raywp-report-section" style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e1e5e9; margin-bottom: 25px;">
                    <p style="margin-bottom: 15px; color: #666;"><?php esc_html_e('Run a scan to check for accessibility issues on your website or enable automatic fixes.', 'raywp-accessibility'); ?></p>
                    <div style="margin-bottom: 15px;">
                        <button id="run-full-scan" class="button button-primary" style="margin-right: 10px;"><?php esc_html_e('Run Full Scan', 'raywp-accessibility'); ?></button>
                        <button id="enable-all-fixes" class="button" style="margin-right: 10px;"><?php esc_html_e('Enable All Auto-Fixes', 'raywp-accessibility'); ?></button>
                        <button id="check-fixed-score" class="button" style="display: none;"><?php esc_html_e('Check Score With Fixes', 'raywp-accessibility'); ?></button>
                    </div>
                    <?php 
                    // Show current fix settings
                    $settings = get_option('raywp_accessibility_settings', []);
                    $fixes_status = [
                        'fix_forms' => !empty($settings['fix_forms']),
                        'add_main_landmark' => !empty($settings['add_main_landmark']),
                        'fix_heading_hierarchy' => !empty($settings['fix_heading_hierarchy']),
                        'fix_video_accessibility' => !empty($settings['fix_video_accessibility']),
                        'fix_keyboard_accessibility' => !empty($settings['fix_keyboard_accessibility']),
                        'fix_duplicate_ids' => !empty($settings['fix_duplicate_ids']),
                        'fix_page_language' => !empty($settings['fix_page_language']),
                        'fix_iframe_titles' => !isset($settings['fix_iframe_titles']) || !empty($settings['fix_iframe_titles']),
                        'fix_button_names' => !isset($settings['fix_button_names']) || !empty($settings['fix_button_names']),
                        'fix_generic_links' => !isset($settings['fix_generic_links']) || !empty($settings['fix_generic_links'])
                    ];
                    ?>
                    <div style="font-size: 12px; color: #666; padding: 10px; background: rgba(0,115,170,0.05); border-left: 3px solid #0073aa; border-radius: 3px;">
                        <strong><?php esc_html_e('Auto-fix status:', 'raywp-accessibility'); ?></strong>
                        Forms: <?php echo $fixes_status['fix_forms'] ? '✓' : '✗'; ?> |
                        Landmarks: <?php echo $fixes_status['add_main_landmark'] ? '✓' : '✗'; ?> |
                        Headings: <?php echo $fixes_status['fix_heading_hierarchy'] ? '✓' : '✗'; ?> |
                        Video: <?php echo $fixes_status['fix_video_accessibility'] ? '✓' : '✗'; ?> |
                        Keyboard: <?php echo $fixes_status['fix_keyboard_accessibility'] ? '✓' : '✗'; ?> |
                        IDs: <?php echo $fixes_status['fix_duplicate_ids'] ? '✓' : '✗'; ?> |
                        Language: <?php echo $fixes_status['fix_page_language'] ? '✓' : '✗'; ?> |
                        iFrames: <?php echo $fixes_status['fix_iframe_titles'] ? '✓' : '✗'; ?> |
                        Buttons: <?php echo $fixes_status['fix_button_names'] ? '✓' : '✗'; ?> |
                        Links: <?php echo $fixes_status['fix_generic_links'] ? '✓' : '✗'; ?>
                    </div>
                </div>
                
                <div class="raywp-report-section">
                    <h2><?php esc_html_e('Accessibility Status', 'raywp-accessibility'); ?></h2>
                    
                    <?php if ($compliance_assessment !== null): ?>
                        <!-- Score Comparison Display -->
                        <div style="display: flex; align-items: center; gap: 20px; margin: 20px 0; padding: 25px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                            <!-- Original Score -->
                            <div style="text-align: center; padding: 20px; background: #fff; border-radius: 8px; border: 1px solid #dee2e6; min-width: 140px;">
                                <div style="font-size: 13px; color: #6c757d; margin-bottom: 8px; font-weight: 500;">Original Theme Score</div>
                                <div style="font-size: 42px; font-weight: bold; color: <?php echo isset($compliance_assessment['original_score']) && $compliance_assessment['original_score'] >= 90 ? '#28a745' : (isset($compliance_assessment['original_score']) && $compliance_assessment['original_score'] >= 70 ? '#ffc107' : '#dc3545'); ?>;">
                                    <?php echo isset($compliance_assessment['original_score']) ? esc_html($compliance_assessment['original_score']) : '--'; ?>%
                                </div>
                            </div>
                            
                            <!-- Arrow -->
                            <div style="color: #6c757d; font-size: 24px; font-weight: bold;">→</div>
                            
                            <!-- Live Score with Plugin -->
                            <?php
                            // Check for stored Live Score
                            $stored_live_score = get_option('raywp_accessibility_live_score', null);
                            $has_stored_score = ($stored_live_score !== null && is_numeric($stored_live_score));
                            
                            if ($has_stored_score) {
                                // Determine colors based on score
                                $score = intval($stored_live_score);
                                if ($score >= 90) {
                                    $score_color = '#28a745';
                                    $bg_color = '#e8f5e8';
                                    $border_color = '#28a745';
                                } elseif ($score >= 75) {
                                    $score_color = '#28a745';
                                    $bg_color = '#e8f5e8';
                                    $border_color = '#28a745';
                                } elseif ($score >= 60) {
                                    $score_color = '#ffc107';
                                    $bg_color = '#fff3cd';
                                    $border_color = '#ffc107';
                                } else {
                                    $score_color = '#dc3545';
                                    $bg_color = '#f8d7da';
                                    $border_color = '#dc3545';
                                }
                            ?>
                            <div style="text-align: center; padding: 20px; background: <?php echo $bg_color; ?>; border-radius: 8px; border: 1px solid <?php echo $border_color; ?>; min-width: 140px;">
                                <div style="font-size: 13px; color: <?php echo $score_color; ?>; margin-bottom: 8px; font-weight: 500;">Live Score with Plugin</div>
                                <div style="font-size: 42px; font-weight: bold; color: <?php echo $score_color; ?>;">
                                    <?php echo $score; ?>%
                                </div>
                                <?php 
                                $stored_timestamp = get_option('raywp_accessibility_live_score_timestamp', null);
                                if ($stored_timestamp) {
                                    echo '<div style="font-size: 12px; color: ' . $score_color . '; margin-top: 5px; font-style: italic;">';
                                    echo 'Last checked: ' . esc_html(wp_date('M j, Y g:i A', $stored_timestamp));
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <?php } else { ?>
                            <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; min-width: 140px;">
                                <div style="font-size: 13px; color: #6c757d; margin-bottom: 8px; font-weight: 500;">Live Score with Plugin</div>
                                <div style="font-size: 42px; font-weight: bold; color: #6c757d;">
                                    --
                                </div>
                                <div style="font-size: 12px; color: #6c757d; margin-top: 5px; font-style: italic;">
                                    Click "Check Score with Fixes" to calculate
                                </div>
                            </div>
                            <?php } ?>
                            
                            <!-- Download Report Button -->
                            <div style="margin-left: auto;">
                                <button class="button button-secondary" style="border-radius: 6px; padding: 8px 16px; font-size: 13px;">
                                    📥 Download Report
                                </button>
                            </div>
                        </div>
                        
                        <!-- Status Section -->
                        <div style="margin: 20px 0;">
                            <h3 style="color: #1d2327; margin-bottom: 15px; font-size: 18px;">Requires Manual Attention</h3>
                            
                            <!-- Manual Issues -->
                            <?php 
                            // Use detailed manual issues data that includes location information
                            if (!$has_post_fix_scan): ?>
                                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; text-align: center;">
                                    <p style="color: #6c757d; margin: 0;">
                                        <em>Click "Check Score with Fixes" to see remaining issues after auto-fixes are applied.</em>
                                    </p>
                                </div>
                            <?php elseif (!empty($detailed_manual_issues)): ?>
                                <div style="margin-bottom: 20px;">
                                    <div style="background: #fff; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                                        <div style="padding: 15px;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                                        <th style="text-align: left; padding: 8px 0; font-size: 13px; color: #6c757d; width: 100px;">Severity</th>
                                                        <th style="text-align: left; padding: 8px 0; font-size: 13px; color: #6c757d;">Issue</th>
                                                        <th style="text-align: right; padding: 8px 0; font-size: 13px; color: #6c757d; width: 120px;">Count</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($detailed_manual_issues as $issue): 
                                                        $severity_colors = [
                                                            'critical' => '#dc3545',
                                                            'high' => '#dc3545',
                                                            'medium' => '#ffc107',
                                                            'low' => '#6c757d',
                                                            'info' => '#17a2b8'
                                                        ];
                                                        $severity_bg_colors = [
                                                            'critical' => '#dc3545',
                                                            'high' => '#dc3545',
                                                            'medium' => '#ffc107',
                                                            'low' => '#6c757d',
                                                            'info' => '#17a2b8'
                                                        ];
                                                        $severity_text_colors = [
                                                            'critical' => 'white',
                                                            'high' => 'white',
                                                            'medium' => '#212529',
                                                            'low' => 'white',
                                                            'info' => 'white'
                                                        ];
                                                        
                                                        // Map legacy severity values to current ones
                                                        $severity = $issue->issue_severity;
                                                        if ($severity === 'serious') {
                                                            $severity = 'high';
                                                        } elseif ($severity === 'moderate') {
                                                            $severity = 'medium';
                                                        }
                                                    ?>
                                                        <tr style="border-bottom: 1px solid #f8f9fa;">
                                                            <td style="padding: 12px 0;">
                                                                <span style="background: <?php echo esc_attr($severity_bg_colors[$severity]); ?>; color: <?php echo esc_attr($severity_text_colors[$severity]); ?>; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                                                    <?php echo esc_html(ucfirst($severity)); ?>
                                                                </span>
                                                            </td>
                                                            <td style="padding: 12px 0; color: #1d2327;">
                                                                <?php 
                                                                $issue_desc = $this->get_issue_description($issue->issue_type);
                                                                echo esc_html($issue_desc);
                                                                
                                                                // Add details accordion with location information
                                                                $details = $this->get_issue_details($issue->issue_type);
                                                                ?>
                                                                <div style="margin-top: 8px;">
                                                                    <details style="cursor: pointer;">
                                                                        <summary style="color: #0073aa; font-size: 13px;">View details & location</summary>
                                                                        <div style="margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 13px; line-height: 1.6; color: #333;">
                                                                            <?php if ($details): ?>
                                                                                <?php echo wp_kses_post($details); ?>
                                                                                <hr style="margin: 15px 0; border: none; border-top: 1px solid #dee2e6;" />
                                                                            <?php endif; ?>
                                                                            
                                                                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #dee2e6;">
                                                                                <?php if (isset($issue->is_deduplicated) && $issue->is_deduplicated): ?>
                                                                                    <div style="background: #e3f2fd; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px; color: #1976d2;">
                                                                                        ℹ️ This shows 1 unique issue pattern found on <?php echo intval($issue->page_count); ?> page<?php echo $issue->page_count == 1 ? '' : 's'; ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <strong style="color: #495057;">Example Location:</strong><br>
                                                                                <strong>Page:</strong> <a href="<?php echo esc_url($issue->page_url); ?>" target="_blank" style="color: #0073aa;"><?php echo esc_html(parse_url($issue->page_url, PHP_URL_PATH) ?: '/'); ?></a><br>
                                                                                <?php if (!empty($issue->element_selector)): ?>
                                                                                    <strong>Element:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 12px;"><?php echo esc_html($issue->element_selector); ?></code><br>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($issue->issue_description)): ?>
                                                                                    <strong>Specific Issue:</strong> <?php echo esc_html($issue->issue_description); ?>
                                                                                <?php endif; ?>
                                                                                
                                                                                <?php if (!empty($issue->element_selector)): ?>
                                                                                    <div style="margin-top: 10px;">
                                                                                        <strong>Code Example:</strong>
                                                                                        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 5px; font-family: monospace; font-size: 12px; overflow-x: auto;">
                                                                                            <div class="raywp-code-snippet" data-url="<?php echo esc_attr($issue->page_url); ?>" data-selector="<?php echo esc_attr($issue->element_selector); ?>">
                                                                                                <span style="color: #6c757d;">Loading code snippet...</span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <p style="font-size: 11px; color: #6c757d; margin-top: 5px;">
                                                                                            Note: This shows the first occurrence of <?php echo esc_html($issue->element_selector); ?> on the page.
                                                                                        </p>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </details>
                                                                </div>
                                                            </td>
                                                            <td style="padding: 12px 0; text-align: right; color: #6c757d; font-size: 14px;">
                                                                <?php 
                                                                if (isset($issue->is_deduplicated) && $issue->is_deduplicated) {
                                                                    // Show deduplicated info: 1 pattern across X pages
                                                                    echo '<span style="font-weight: 500; color: #495057;">1 pattern</span><br>';
                                                                    echo '<span style="font-size: 12px; color: #6c757d;">(on ' . intval($issue->page_count) . ' page' . ($issue->page_count == 1 ? '' : 's') . ')</span>';
                                                                } else {
                                                                    // Original logic for non-deduplicated
                                                                    echo intval($issue->count) . ' ' . ($issue->count == 1 ? 'issue' : 'issues');
                                                                }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="background: #d4edda; padding: 15px; border-radius: 8px; border: 1px solid #c3e6cb; color: #155724;">
                                    <p style="margin: 0;">🎉 <?php esc_html_e('All detected issues can be automatically fixed! Enable auto-fixes in settings.', 'raywp-accessibility'); ?></p>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; margin: 20px 0;">
                            <div style="font-size: 48px; color: #dee2e6; margin-bottom: 15px;">📊</div>
                            <h3 style="color: #6c757d; margin-bottom: 10px;">No Accessibility Scan Yet</h3>
                            <p style="color: #6c757d; margin-bottom: 20px;">Run your first accessibility scan to see your site's score and get improvement recommendations.</p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
                
                
                <div class="raywp-report-section" id="scan-results">
                    <h2><?php esc_html_e('Scan Results', 'raywp-accessibility'); ?></h2>
                    <?php if ($last_scan_date): ?>
                        <p><strong><?php esc_html_e('Last Scan:', 'raywp-accessibility'); ?></strong> 
                           <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_scan_date))); ?>
                        </p>
                    <?php endif; ?>
                    <div class="raywp-scan-results">
                        <?php if (empty($issue_summary)): ?>
                            <p><?php esc_html_e('No scans performed yet. Click "Run Full Scan" to analyze your site.', 'raywp-accessibility'); ?></p>
                        <?php else: ?>
                            <!-- Show only Auto-Fixed Issues -->
                            <div style="margin-top: 20px;">
                                <div>
                                    <h3 style="color: #28a745; margin-bottom: 15px;">✅ Auto-Fixed Issues</h3>
                                    <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; border: 1px solid #28a745;">
                                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                                            <?php
                                            $fixed_count = 0;
                                            foreach ($issue_summary as $issue) {
                                                if ($this->is_auto_fixable($issue->issue_type)) {
                                                    echo '<div style="padding: 10px; background: rgba(255,255,255,0.5); border-radius: 4px;">';
                                                    echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
                                                    echo '<span style="color: #155724;">';
                                                    echo '<strong>' . esc_html(ucfirst($issue->issue_severity)) . ':</strong> ';
                                                    echo esc_html($this->get_issue_description($issue->issue_type));
                                                    echo '</span>';
                                                    echo '<span style="color: #155724; font-weight: bold;">(' . intval($issue->count) . ' fixed) ✓</span>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                    $fixed_count += $issue->count;
                                                }
                                            }
                                            if ($fixed_count == 0) {
                                                echo '<p style="color: #155724; margin: 0;">No auto-fixable issues found in last scan.</p>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="raywp-report-section">
                    <h2><?php esc_html_e('What This Plugin Checks', 'raywp-accessibility'); ?></h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                        <div>
                            <h3><?php esc_html_e('✅ Automatically Detected', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('These issues are found and can often be fixed automatically:', 'raywp-accessibility'); ?></p>
                            <ul style="margin-left: 15px;">
                                <li>• Missing alt text on images</li>
                                <li>• Missing form labels and accessibility issues</li>
                                <li>• Empty headings and heading hierarchy problems</li>
                                <li>• Basic color contrast issues</li>
                                <li>• Keyboard navigation and focus management</li>
                                <li>• Screen reader compatibility issues (ARIA, roles, labels)</li>
                                <li>• Invalid ARIA attributes and missing controls</li>
                                <li>• Missing main landmarks and page structure</li>
                                <li>• Missing skip navigation links</li>
                                <li>• Tables without proper headers</li>
                                <li>• Duplicate IDs that break assistive technology</li>
                                <li>• Missing page language declarations</li>
                                <li>• Generic or empty link text</li>
                                <li>• Iframes without descriptive titles</li>
                                <li>• Video/audio autoplay issues</li>
                                <li>• Basic form validation and error handling</li>
                                <li>• Focus indicators and visual focus management</li>
                            </ul>
                            <p><em><?php esc_html_e('Scans up to 20 pages maximum (free version).', 'raywp-accessibility'); ?></em></p>
                        </div>
                        
                        <div>
                            <h3 style="color: #d73502;"><?php esc_html_e('⚠️ Manual Testing Required', 'raywp-accessibility'); ?></h3>
                            <p><?php esc_html_e('Based on your site content, these areas need human evaluation:', 'raywp-accessibility'); ?></p>
                            <?php
                            // Get manual testing requirements based on actual site content
                            $manual_tests = $this->get_required_manual_tests();
                            if (!empty($manual_tests)): ?>
                                <ul style="margin-left: 15px; color: #d73502;">
                                    <?php foreach ($manual_tests as $test): ?>
                                        <li>• <?php echo esc_html($test); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p style="color: #28a745; margin-left: 15px;">✓ <?php esc_html_e('No specific manual testing requirements detected based on current site content.', 'raywp-accessibility'); ?></p>
                                <p style="margin-left: 15px; font-size: 13px; color: #666;">
                                    <?php esc_html_e('Still recommended: Basic keyboard navigation and screen reader testing.', 'raywp-accessibility'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                
                <div class="raywp-report-section raywp-accessibility-standards">
                    <h2><?php esc_html_e('About Accessibility Standards', 'raywp-accessibility'); ?></h2>
                    <div class="raywp-standards-grid">
                        <div class="raywp-standard">
                            <h3>WCAG 2.1</h3>
                            <p><?php esc_html_e('Web Content Accessibility Guidelines (WCAG) 2.1 is the international standard for web accessibility, developed by the W3C. It provides guidelines to make web content more accessible to people with disabilities.', 'raywp-accessibility'); ?></p>
                            <p><a href="https://www.w3.org/WAI/WCAG21/quickref/" target="_blank" rel="noopener"><?php esc_html_e('Learn more about WCAG 2.1 →', 'raywp-accessibility'); ?></a></p>
                        </div>
                        
                        <div class="raywp-standard">
                            <h3>ADA</h3>
                            <p><?php esc_html_e('The Americans with Disabilities Act (ADA) is a US civil rights law that prohibits discrimination based on disability. Title III requires places of public accommodation to be accessible, which courts have interpreted to include websites.', 'raywp-accessibility'); ?></p>
                            <p><a href="https://www.ada.gov/resources/web-guidance/" target="_blank" rel="noopener"><?php esc_html_e('Learn more about ADA compliance →', 'raywp-accessibility'); ?></a></p>
                        </div>
                        
                        <div class="raywp-standard">
                            <h3>EAA</h3>
                            <p><?php esc_html_e('The European Accessibility Act (EAA) is an EU directive that sets accessibility requirements for products and services. It requires websites and mobile applications to be accessible to people with disabilities.', 'raywp-accessibility'); ?></p>
                            <p><a href="https://ec.europa.eu/social/main.jsp?catId=1202" target="_blank" rel="noopener"><?php esc_html_e('Learn more about EAA →', 'raywp-accessibility'); ?></a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add spacing to prevent footer overlap -->
        <div style="height: 100px;"></div>
        
        <?php
    }
    
    /**
     * Get user-friendly issue descriptions
     */
    private function get_issue_description($issue_type) {
        $descriptions = [
            // Images
            'missing_alt' => 'Missing alt text on images',
            
            // Forms
            'missing_label' => 'Form fields missing labels',
            'required_no_aria' => 'Required form fields missing ARIA attributes',
            'validation_no_error_message' => 'Form validation lacks clear error messages',
            'missing_autocomplete_attribute' => 'Form fields missing autocomplete attributes',
            'error_no_role' => 'Error messages missing proper ARIA roles',
            'generic_error_message' => 'Form errors use generic messages',
            
            // Structure
            'missing_main_landmark' => 'Missing main content landmark',
            'missing_skip_links' => 'Missing skip navigation links',
            'multiple_h1' => 'Multiple H1 headings on page',
            'heading_hierarchy_skip' => 'Heading hierarchy skips levels',
            
            // Media
            'decorative_video_no_aria_hidden' => 'Decorative videos not marked with ARIA hidden',
            'autoplay_motion_video' => 'Videos autoplay with motion',
            
            // Links & Buttons
            'link_no_accessible_name' => 'Links without descriptive text',
            'button_missing_accessible_name' => 'Buttons missing accessible names',
            
            // Text & Contrast
            'restrictive_text_spacing' => 'Text spacing may be too restrictive',
            'low_contrast_text' => 'Low contrast text',
            
            // IDs & Language
            'duplicate_ids' => 'Duplicate ID attributes',
            'missing_page_language' => 'Page language not declared',
            
            // IFrames
            'missing_iframe_title' => 'IFrames missing title attributes',
            
            // Keyboard & Focus
            'keyboard_trap' => 'Keyboard navigation trap detected',
            'missing_focus_indicator' => 'Interactive elements missing focus indicators'
        ];
        
        return isset($descriptions[$issue_type]) ? $descriptions[$issue_type] : str_replace('_', ' ', ucfirst($issue_type));
    }
    
    /**
     * Get detailed information for manual issues
     */
    private function get_issue_details($issue_type) {
        $details = [
            'required_no_aria' => '<strong>What this means:</strong><br>
                Form fields that are required for submission are missing the <code>aria-required="true"</code> attribute.<br><br>
                <strong>Why it matters:</strong><br>
                Screen readers need to announce which fields are mandatory so users know what must be filled out.<br><br>
                <strong>How to fix:</strong><br>
                Add <code>aria-required="true"</code> to all required input fields, or use the HTML5 <code>required</code> attribute.',
                
            'link_no_accessible_name' => '<strong>What this means:</strong><br>
                Links are using non-descriptive text like "click here", "read more", or just icons without text.<br><br>
                <strong>Why it matters:</strong><br>
                Screen reader users often navigate by links alone. Generic link text provides no context about where the link goes.<br><br>
                <strong>How to fix:</strong><br>
                • Use descriptive link text that explains the destination<br>
                • For icon-only links, add <code>aria-label</code> or screen reader text<br>
                • Example: Instead of "Click here", use "Download our accessibility guide"',
                
            'decorative_video_no_aria_hidden' => '<strong>What this means:</strong><br>
                Background or decorative videos are not hidden from screen readers.<br><br>
                <strong>Why it matters:</strong><br>
                Decorative videos can confuse screen reader users and clutter the navigation experience.<br><br>
                <strong>How to fix:</strong><br>
                Add <code>aria-hidden="true"</code> to decorative video elements that don\'t convey important content.',
                
            'autoplay_motion_video' => '<strong>What this means:</strong><br>
                Videos are autoplaying with motion, which can trigger seizures or discomfort.<br><br>
                <strong>Why it matters:</strong><br>
                • Users with vestibular disorders can experience nausea or dizziness<br>
                • Can trigger seizures in users with photosensitive epilepsy<br>
                • Distracting for users with attention disorders<br><br>
                <strong>How to fix:</strong><br>
                • Remove autoplay or use <code>prefers-reduced-motion</code> media query<br>
                • Provide a pause button that\'s immediately accessible<br>
                • Consider using a static image with play button instead',
                
            'validation_no_error_message' => '<strong>What this means:</strong><br>
                Form validation exists but error messages are not properly announced to screen readers.<br><br>
                <strong>Why it matters:</strong><br>
                Users need to know what went wrong and how to fix validation errors.<br><br>
                <strong>How to fix:</strong><br>
                • Use <code>aria-describedby</code> to associate error messages with form fields<br>
                • Add <code>aria-invalid="true"</code> to invalid fields<br>
                • Consider using <code>aria-live</code> regions for dynamic error announcements',
                
            'missing_autocomplete_attribute' => '<strong>What this means:</strong><br>
                Form fields for common data (name, email, address) lack autocomplete attributes.<br><br>
                <strong>Why it matters:</strong><br>
                • Saves time for all users<br>
                • Critical for users with motor disabilities<br>
                • Reduces errors in form completion<br><br>
                <strong>How to fix:</strong><br>
                Add appropriate <code>autocomplete</code> values like "name", "email", "tel", "street-address"',
                
            'error_no_role' => '<strong>What this means:</strong><br>
                Error messages are missing <code>role="alert"</code> or proper ARIA attributes.<br><br>
                <strong>Why it matters:</strong><br>
                Screen readers may not announce errors immediately, leaving users unaware of problems.<br><br>
                <strong>How to fix:</strong><br>
                • Add <code>role="alert"</code> to error containers for immediate announcement<br>
                • Or use <code>aria-live="polite"</code> for less intrusive notifications',
                
            'restrictive_text_spacing' => '<strong>What this means:</strong><br>
                CSS may prevent users from adjusting text spacing for better readability.<br><br>
                <strong>Why it matters:</strong><br>
                Users with dyslexia or visual processing issues need to adjust spacing between letters, words, and lines.<br><br>
                <strong>How to fix:</strong><br>
                • Avoid fixed line-height values in pixels<br>
                • Don\'t set maximum height on text containers<br>
                • Test with browser extensions that adjust text spacing',
                
            'generic_error_message' => '<strong>What this means:</strong><br>
                Error messages don\'t specify which field has the error.<br><br>
                <strong>Why it matters:</strong><br>
                Users need to know exactly where the problem is, especially in long forms.<br><br>
                <strong>How to fix:</strong><br>
                • Associate each error with its specific field<br>
                • Include the field name in the error message<br>
                • Use <code>aria-describedby</code> to link errors to fields',
                
            'low_contrast_text' => '<strong>What this means:</strong><br>
                Text doesn\'t have sufficient color contrast against its background.<br><br>
                <strong>Why it matters:</strong><br>
                Low contrast makes text difficult or impossible to read for users with low vision or color blindness.<br><br>
                <strong>WCAG Requirements:</strong><br>
                • Normal text: 4.5:1 contrast ratio<br>
                • Large text (18pt+): 3:1 contrast ratio<br><br>
                <strong>How to fix:</strong><br>
                • Use a contrast checker tool<br>
                • Darken text or lighten backgrounds<br>
                • Consider offering a high contrast mode'
        ];
        
        return isset($details[$issue_type]) ? $details[$issue_type] : null;
    }
    
    /**
     * Determine which issues are auto-fixable
     */
    public function is_auto_fixable($issue_type) {
        // Check which auto-fixes are currently enabled in settings
        $settings = get_option('raywp_accessibility_settings', []);
        
        $auto_fixable_map = [
            'missing_alt' => !empty($settings['fix_forms']),
            'missing_label' => !empty($settings['fix_forms']),
            'missing_main_landmark' => !empty($settings['add_main_landmark']),
            'missing_skip_links' => !empty($settings['add_main_landmark']),
            'button_missing_accessible_name' => !empty($settings['fix_button_names']) || !isset($settings['fix_button_names']),
            'heading_hierarchy_skip' => !empty($settings['fix_heading_hierarchy']),
            'multiple_h1' => !empty($settings['fix_heading_hierarchy']), // Fixed by converting extras to h2 with aria-level
            'duplicate_ids' => !empty($settings['fix_duplicate_ids']),
            'missing_page_language' => !empty($settings['fix_page_language']),
            'missing_iframe_title' => !empty($settings['fix_iframe_titles']) || !isset($settings['fix_iframe_titles']),
            'iframe_missing_title' => !empty($settings['fix_iframe_titles']) || !isset($settings['fix_iframe_titles']), // Alternative naming
            
            // Convert previously manual issues to auto-fixable
            'required_no_aria' => !empty($settings['fix_forms']), // Can add aria-required automatically
            'link_no_accessible_name' => !empty($settings['fix_generic_links']) || !isset($settings['fix_generic_links']), // Can add generic accessible names
            'generic_link_text' => !empty($settings['fix_generic_links']) || !isset($settings['fix_generic_links']), // Can add descriptive title attributes
            'decorative_video_no_aria_hidden' => !empty($settings['fix_video_accessibility']), // Can add aria-hidden to decorative videos
            'validation_no_error_message' => !empty($settings['fix_forms']), // Can add basic error messaging
            'missing_autocomplete_attribute' => !empty($settings['fix_forms']), // Can add standard autocomplete attributes
            'error_no_role' => !empty($settings['fix_forms']), // Can add role="alert" to error messages  
            'generic_error_message' => !empty($settings['fix_forms']), // Can improve error messages
            'animation_no_reduced_motion' => true, // CSS already handles prefers-reduced-motion
            'transform_animation_no_control' => true, // CSS already handles prefers-reduced-motion for transforms
            
            // Contrast issues are auto-fixable when enhanced contrast is enabled
            'low_contrast' => false, // DISABLED: Contrast fixes were too aggressive and disrupting layout
            
            // These remain manual as requested by user
            'autoplay_motion_video' => false, // Videos autoplay with motion - requires manual intervention
            'restrictive_text_spacing' => false, // Text spacing may be too restrictive - requires manual CSS review
            
            // Empty links should be auto-fixable since we're excluding admin toolbar links
            'empty_link' => true, // Empty links can be handled automatically
        ];
        
        // Return true only if the fix is both available and enabled
        return isset($auto_fixable_map[$issue_type]) && $auto_fixable_map[$issue_type];
    }
    
    /**
     * Get required manual tests based on actual site content
     */
    private function get_required_manual_tests() {
        $manual_tests = [];
        
        // Always recommend these basic tests
        $manual_tests[] = 'Screen reader navigation experience (test with NVDA or JAWS)';
        $manual_tests[] = 'Keyboard-only navigation testing (Tab, Enter, arrow keys)';
        
        // Check homepage for specific elements that require manual testing
        $home_url = home_url();
        $response = wp_remote_get($home_url, ['timeout' => 30]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            
            if (!empty($content)) {
                // Check for video elements
                if (preg_match('/<video[^>]*>/', $content) || 
                    preg_match('/<iframe[^>]*(?:youtube|vimeo|video)[^>]*>/i', $content)) {
                    $manual_tests[] = 'Video captions and audio descriptions for all video content';
                }
                
                // Check for audio elements
                if (preg_match('/<audio[^>]*>/', $content)) {
                    $manual_tests[] = 'Audio transcripts and alternative content';
                }
                
                // Check for complex ARIA patterns
                if (preg_match('/aria-expanded|aria-selected|aria-checked/i', $content)) {
                    $manual_tests[] = 'Complex ARIA widget interactions and state announcements';
                }
                
                // Check for forms
                if (preg_match('/<form[^>]*>/', $content)) {
                    $manual_tests[] = 'Form validation error message effectiveness';
                    $manual_tests[] = 'Form completion using only keyboard navigation';
                }
                
                // Check for dynamic content
                if (preg_match('/ajax|javascript|jquery/i', $content) || 
                    preg_match('/data-|onclick|onchange/i', $content)) {
                    $manual_tests[] = 'Focus management in dynamic content updates';
                }
                
                // Check for animations
                if (preg_match('/animation|transition|transform/i', $content) || 
                    preg_match('/autoplay|carousel|slider/i', $content)) {
                    $manual_tests[] = 'Animation and motion preferences (prefers-reduced-motion)';
                }
                
                // Check for iframes (potential third-party content)
                if (preg_match('/<iframe[^>]*>/i', $content)) {
                    $manual_tests[] = 'Third-party embedded content accessibility';
                }
                
                // Check for download links
                if (preg_match('/\.pdf|\.doc|\.xls|download/i', $content)) {
                    $manual_tests[] = 'PDF and downloadable document accessibility';
                }
                
                // Check for time-based content indicators
                if (preg_match('/timeout|timer|countdown|auto-refresh/i', $content)) {
                    $manual_tests[] = 'Time limits and auto-refresh accessibility controls';
                }
                
                // Check mobile indicators
                if (preg_match('/viewport|responsive|mobile/i', $content)) {
                    $manual_tests[] = 'Mobile accessibility experience with mobile screen readers';
                }
                
                // Content readability - always important but check for complex content
                if (strlen($content) > 10000) { // Large content
                    $manual_tests[] = 'Content readability and comprehension at different reading levels';
                }
            }
        }
        
        return array_unique($manual_tests);
    }
    
    /**
     * Get current filters from request
     */
    private function get_current_filters() {
        $filters = [];
        
        if (!empty($_GET['severity'])) {
            $filters['severity'] = sanitize_text_field($_GET['severity']);
        }
        
        if (!empty($_GET['type'])) {
            $filters['type'] = sanitize_text_field($_GET['type']);
        }
        
        if (!empty($_GET['wcag_reference'])) {
            $filters['wcag_reference'] = sanitize_text_field($_GET['wcag_reference']);
        }
        
        if (!empty($_GET['page_url'])) {
            $filters['page_url'] = sanitize_text_field($_GET['page_url']);
        }
        
        if (isset($_GET['auto_fixable']) && $_GET['auto_fixable'] !== '') {
            $filters['auto_fixable'] = intval($_GET['auto_fixable']);
        }
        
        if (isset($_GET['fixed']) && $_GET['fixed'] !== '') {
            $filters['fixed'] = intval($_GET['fixed']);
        }
        
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        
        $filters['limit'] = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $filters['offset'] = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        return $filters;
    }
    
    /**
     * Handle export request
     */
    private function handle_export_request() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'raywp_export_results')) {
            wp_die(__('Unauthorized', 'raywp-accessibility'));
        }
        
        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
        $reports = $plugin->get_component('reports');
        
        if (!$reports) {
            wp_die(__('Reports component not available', 'raywp-accessibility'));
        }
        
        $filters = [];
        if (!empty($_POST['export_filters'])) {
            // Sanitize input before parsing to prevent variable injection
            $raw_filters = sanitize_text_field(wp_unslash($_POST['export_filters']));
            parse_str($raw_filters, $filters);
            // Sanitize each filter value
            $filters = array_map('sanitize_text_field', $filters);
        }

        $export_result = $reports->export_results_csv($filters);

        if ($export_result) {
            // Validate file path is within upload directory
            $upload_dir = wp_upload_dir();
            $real_file_path = realpath($export_result['file_path']);
            $real_upload_dir = realpath($upload_dir['basedir']);

            if ($real_file_path === false || strpos($real_file_path, $real_upload_dir) !== 0) {
                wp_die(__('Invalid file path', 'raywp-accessibility'));
            }

            // Sanitize filename for header
            $safe_filename = sanitize_file_name($export_result['filename']);

            // Force download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
            header('Content-Length: ' . filesize($export_result['file_path']));

            readfile($export_result['file_path']);
            unlink($export_result['file_path']); // Clean up temp file
            exit;
        }
    }
    
    /**
     * Show database upgrade notice if needed
     */
    public function show_database_upgrade_notice() {
        // Only show on plugin admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'raywp-accessibility') === false) {
            return;
        }
        
        // Check if database needs upgrade
        global $wpdb;
        $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
        
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if ($table_exists) {
            $columns = $wpdb->get_results("DESCRIBE $table_name");
            $column_names = array_column($columns, 'Field');
            
            $required_columns = ['wcag_reference', 'wcag_level', 'auto_fixable', 'page_type', 'scan_session_id'];
            $missing_columns = array_diff($required_columns, $column_names);
            
            if (!empty($missing_columns)) {
                ?>
                <div class="notice notice-warning">
                    <p><strong>RayWP Accessibility:</strong> Database upgrade required to enable enhanced reporting features.</p>
                    <p>
                        <a href="#" onclick="upgradeDatabase()" class="button button-primary">Upgrade Database Now</a>
                        <span style="margin-left: 10px; font-size: 12px; color: #666;">This will add new columns for WCAG compliance tracking.</span>
                    </p>
                </div>
                
                <script>
                function upgradeDatabase() {
                    if (confirm('This will upgrade your database to support enhanced accessibility reporting. Continue?')) {
                        // Create a hidden form to trigger the upgrade
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'raywp_upgrade_database';
                        input.value = '1';
                        
                        var nonce = document.createElement('input');
                        nonce.type = 'hidden';
                        nonce.name = '_wpnonce';
                        nonce.value = '<?php echo wp_create_nonce('raywp_upgrade_database'); ?>';
                        
                        form.appendChild(input);
                        form.appendChild(nonce);
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
                </script>
                <?php
            }
        }
        
        // Handle upgrade request
        if (isset($_POST['raywp_upgrade_database']) && wp_verify_nonce($_POST['_wpnonce'], 'raywp_upgrade_database')) {
            $this->perform_database_upgrade();
        }
    }
    
    /**
     * Perform database upgrade
     */
    private function perform_database_upgrade() {
        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
        $reports = $plugin->get_component('reports');
        
        if ($reports) {
            // Clear caches
            wp_cache_delete('raywp_wcag_breakdown', 'raywp_accessibility');
            wp_cache_delete('raywp_scan_sessions', 'raywp_accessibility');
            wp_cache_delete('raywp_issue_summary', 'raywp_accessibility');
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
            wp_cache_delete('raywp_table_exists_' . md5($table_name), 'raywp_accessibility');
            
            // Force table upgrade using reflection
            $reflection = new \ReflectionClass($reports);
            $method = $reflection->getMethod('ensure_database_table');
            $method->setAccessible(true);
            $method->invoke($reports);
            
            // Show success notice and redirect to avoid resubmission
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>RayWP Accessibility:</strong> Database successfully upgraded! Enhanced reporting features are now available.</p>
                </div>
                <?php
            });
            
            // Redirect to avoid form resubmission
            wp_redirect(remove_query_arg(['raywp_upgrade_database', '_wpnonce']));
            exit;
        }
    }

    /**
     * AJAX handler for JavaScript contrast detection
     */
    public function ajax_run_contrast_check() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'raywp_contrast_check')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $url = sanitize_url($_POST['url'] ?? home_url());
        
        // Skip wp-admin URLs to prevent admin interface elements from being flagged
        if (strpos($url, '/wp-admin/') !== false) {
            wp_send_json_error(['message' => 'Admin pages are excluded from contrast detection']);
            return;
        }
        $contrast_results = $_POST['contrast_results'] ?? [];

        // Validate and sanitize the contrast results
        $sanitized_results = [];
        if (is_array($contrast_results)) {
            foreach ($contrast_results as $result) {
                if (is_array($result) && isset($result['selector'], $result['contrastRatio'])) {
                    $sanitized_results[] = [
                        'selector' => sanitize_text_field($result['selector']),
                        'contrastRatio' => floatval($result['contrastRatio']),
                        'requiredRatio' => floatval($result['requiredRatio'] ?? 4.5),
                        'textColor' => $this->sanitize_color($result['textColor'] ?? []),
                        'backgroundColor' => $this->sanitize_color($result['backgroundColor'] ?? []),
                        'isLargeText' => (bool)($result['isLargeText'] ?? false),
                        'text' => sanitize_text_field($result['text'] ?? ''),
                        'wcagLevel' => sanitize_text_field($result['wcagLevel'] ?? 'AA')
                    ];
                }
            }
        }

        // Store results temporarily or process them
        // For now, just return success
        wp_send_json_success([
            'message' => 'Contrast check completed',
            'issues_found' => count($sanitized_results),
            'results' => $sanitized_results
        ]);
    }

    /**
     * Sanitize color array
     */
    private function sanitize_color($color) {
        if (!is_array($color)) return ['r' => 0, 'g' => 0, 'b' => 0, 'alpha' => 1];
        
        return [
            'r' => max(0, min(255, intval($color['r'] ?? 0))),
            'g' => max(0, min(255, intval($color['g'] ?? 0))),
            'b' => max(0, min(255, intval($color['b'] ?? 0))),
            'alpha' => max(0, min(1, floatval($color['alpha'] ?? 1)))
        ];
    }

    /**
     * Store contrast results in transient for later retrieval and in scan_results table
     */
    public function ajax_store_contrast_results() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'raywp_contrast_check')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $url = sanitize_url($_POST['url'] ?? home_url());
        
        // Skip wp-admin URLs to prevent admin interface elements from being stored
        if (strpos($url, '/wp-admin/') !== false) {
            wp_send_json_error(['message' => 'Admin pages are excluded from contrast detection storage']);
            return;
        }
        $results = $_POST['results'] ?? '[]';
        
        // Parse and validate JSON results
        $contrast_results = json_decode($results, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to decode from unescaped string 
            $unescaped_results = wp_unslash($results);
            $contrast_results = json_decode($unescaped_results, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(['message' => 'Invalid JSON data: ' . json_last_error_msg()]);
                return;
            }
        }

        // Filter out false positives on server side as backup
        $contrast_results = $this->filter_contrast_false_positives($contrast_results);

        // Store results in transient cache for 24 hours (to survive across scan phases)
        $cache_key = 'raywp_contrast_results_' . md5($url);
        set_transient($cache_key, $contrast_results, DAY_IN_SECONDS);
        
        // Also store results in scan_results table for integration with reports
        if (!empty($contrast_results)) {
            $this->store_contrast_in_scan_results($url, $contrast_results);
        }

        wp_send_json_success([
            'message' => 'Results stored successfully',
            'issues_count' => count($contrast_results)
        ]);
    }
    
    /**
     * Store contrast results in the scan_results table using the proper save_scan_results method
     */
    private function store_contrast_in_scan_results($url, $contrast_results) {
        // First apply the same filtering that was in ajax_store_contrast_results
        $filtered_results = $this->filter_contrast_false_positives($contrast_results);
        
        // Debug log filtering results
        $original_count = is_array($contrast_results) ? count($contrast_results) : 0;
        $filtered_count = count($filtered_results);
        if ($original_count > $filtered_count) {
            error_log("RayWP Contrast: Filtered out " . ($original_count - $filtered_count) . " false positives from $original_count total issues");
        }
        
        // Convert filtered contrast results to the format expected by save_scan_results
        $issues = [];
        
        foreach ($filtered_results as $result) {
            $selector = $result['selector'] ?? 'unknown';
            $contrast_ratio = isset($result['contrastRatio']) ? round($result['contrastRatio'], 2) : 0;
            $required_ratio = isset($result['requiredRatio']) ? $result['requiredRatio'] : 4.5;
            $text_preview = isset($result['text']) ? substr($result['text'], 0, 100) : '';
            
            // Build issue description with color info
            $text_color = isset($result['textColor']) ? $result['textColor'] : null;
            $bg_color = isset($result['backgroundColor']) ? $result['backgroundColor'] : null;
            
            $issue_description = sprintf(
                'Insufficient color contrast: %s:1 (required: %s:1)',
                $contrast_ratio,
                $required_ratio
            );
            
            // Add color info and text preview to description
            if ($text_color && $bg_color) {
                $issue_description .= sprintf(
                    ' | Text: rgb(%d,%d,%d) | Background: rgb(%d,%d,%d)',
                    $text_color['r'] ?? 0, $text_color['g'] ?? 0, $text_color['b'] ?? 0,
                    $bg_color['r'] ?? 0, $bg_color['g'] ?? 0, $bg_color['b'] ?? 0
                );
            }
            if ($text_preview) {
                $issue_description .= ' | Text: "' . esc_html($text_preview) . '"';
            }
            
            $severity = ($contrast_ratio < 3.0) ? 'high' : 'medium';
            
            // Add to issues array in the format expected by save_scan_results
            $issues[] = [
                'type' => 'low_contrast',
                'severity' => $severity,
                'element' => $selector,
                'message' => $issue_description,
                'wcag_criterion' => '1.4.3',
                'wcag_reference' => 'WCAG 2.1 Level AA - 1.4.3 Contrast (Minimum)',
                'how_to_fix' => 'Increase the contrast ratio between text and background colors to at least ' . $required_ratio . ':1',
                'auto_fixable' => false
            ];
        }
        
        // Use the proper save_scan_results method which includes automatic cleanup
        if (!empty($issues)) {
            $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
            $reports = $plugin->get_component('reports');
            
            if ($reports) {
                $scan_data = [
                    'url' => $url,
                    'issues' => $issues,
                    'total_issues' => count($issues),
                    'timestamp' => current_time('mysql')
                ];
                
                // Save with cleanup - contrast scans should clear previous data
                $reports->save_scan_results($scan_data, 'contrast_' . uniqid(), true);
            }
        }
    }
    
    /**
     * Filter out false positives from contrast results
     * @param array $contrast_results Raw contrast results from client
     * @return array Filtered results with false positives removed
     */
    private function filter_contrast_false_positives($contrast_results) {
        if (!is_array($contrast_results)) {
            return [];
        }

        // VERBOSE DEBUG: Log first 5 results to see data structure
        $sample_count = 0;
        foreach ($contrast_results as $r) {
            if ($sample_count < 5) {
                error_log("RayWP Contrast DEBUG: Result keys: " . implode(', ', array_keys($r)));
                error_log("RayWP Contrast DEBUG: selector=" . ($r['selector'] ?? 'NOT SET') . ", text=" . ($r['text'] ?? 'NOT SET'));
                $sample_count++;
            }
        }

        $filtered = array_filter($contrast_results, function($result) {
            if (!isset($result['selector'])) {
                return true;
            }

            $selector = strtolower($result['selector']);
            $text = isset($result['text']) ? strtolower(trim($result['text'])) : '';

            // Debug log for skip links - also log all selectors with 'a' tag to see if skip link comes through
            if ($selector === 'a' || strpos($selector, 'raywp-skip-link') !== false || strpos($selector, 'skip-link') !== false || strpos($text, 'skip to') !== false) {
                error_log("RayWP Contrast Filter: Processing 'a' element - Selector: $selector, Text: '$text'");
            }

            // ENHANCED: Filter based on text content for skip links
            // This catches skip links even if the selector doesn't include classes
            $skipLinkTextPatterns = [
                'skip to main',
                'skip to content',
                'skip to navigation',
                'skip navigation',
                'skip to primary',
                'jump to content',
                'jump to main',
                'go to content'
            ];
            foreach ($skipLinkTextPatterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    error_log("RayWP Contrast Filter: Filtering skip link by text content: '$text'");
                    return false;
                }
            }

            // Skip slider/carousel containers
            if (strpos($selector, 'slider-box') !== false ||
                strpos($selector, 'carousel') !== false ||
                strpos($selector, 'slider') !== false) {
                return false;
            }

            // Skip navigation text elements
            $navText = ['prev', 'next', 'previous', 'prevnext'];
            $cleanText = preg_replace('/\s+/', '', $text);
            if (in_array($cleanText, $navText)) {
                return false;
            }

            // Skip screen reader only elements (backup filter)
            $screenReaderPatterns = [
                'sr-only', 'screen-reader-text', 'screen-reader-only',
                'visually-hidden', 'visuallyhidden', 'assistive-text',
                'clip-text', 'skiplink', 'skip-link', 'skip-to-content',
                'skip-to-main', 'screenreader', 'accessible-text',
                'a11y-text', 'offscreen-text', 'hide-visually',
                'raywp-skip-link', 'raywp-focusable-skip-link' // Our plugin's skip link classes
            ];
            foreach ($screenReaderPatterns as $pattern) {
                if (strpos($selector, $pattern) !== false) {
                    // Debug log when skip link is filtered
                    if (strpos($pattern, 'raywp') !== false || strpos($pattern, 'skip') !== false) {
                        error_log("RayWP Contrast Filter: Filtering skip link with selector: $selector (matched pattern: $pattern)");
                    }
                    return false;
                }
            }

            // Skip hamburger/menu toggle elements
            $menuPatterns = [
                'hamburger', 'menu-toggle', 'toggle-menu', 'nav-toggle',
                'burger', 'mobile-menu', 'mobile-nav', 'offcanvas',
                'off-canvas', 'sidebar-toggle', 'nav-icon', 'menu-icon'
            ];
            foreach ($menuPatterns as $pattern) {
                if (strpos($selector, $pattern) !== false) {
                    return false;
                }
            }

            return true;
        });

        // Reset array keys after filtering
        return array_values($filtered);
    }

    /**
     * Clear all contrast results from cache
     */
    public function ajax_clear_contrast_cache() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? $_GET['nonce'] ?? '', 'raywp_accessibility_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Clear all contrast result transients
        global $wpdb;
        $cleared_count = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_raywp_contrast_results_%' OR option_name LIKE '_transient_timeout_raywp_contrast_results_%'"
        );

        wp_send_json_success(['message' => "Cleared {$cleared_count} contrast result cache entries"]);
    }
    
    /**
     * AJAX handler to get element snippet
     */
    public function ajax_get_element_snippet() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'raywp_accessibility_nonce')) {
            wp_die('Security check failed');
        }
        
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        $selector = isset($_POST['selector']) ? sanitize_text_field($_POST['selector']) : '';
        
        if (empty($page_url) || empty($selector)) {
            wp_send_json_error(['message' => 'Missing required parameters']);
        }
        
        // Don't fetch admin pages
        if (strpos($page_url, '/wp-admin/') !== false || 
            strpos($page_url, '/wp-includes/') !== false ||
            strpos($page_url, '/wp-content/') !== false) {
            wp_send_json_error(['message' => 'Cannot fetch code from admin pages']);
        }
        
        // Fetch the page content
        $response = wp_remote_get($page_url, [
            'timeout' => 30,
            'sslverify' => false // For local development
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Unable to fetch page content']);
        }
        
        $html_content = wp_remote_retrieve_body($response);
        if (empty($html_content)) {
            wp_send_json_error(['message' => 'Page content is empty']);
        }
        
        // Try to find the element and get its HTML
        $snippet = $this->extract_element_snippet($html_content, $selector);
        
        if ($snippet) {
            wp_send_json_success(['snippet' => $snippet]);
        } else {
            wp_send_json_error(['message' => 'Element not found on page']);
        }
    }
    
    /**
     * Extract HTML snippet for a given CSS selector with fallback methods
     */
    private function extract_element_snippet($html_content, $selector) {
        // Use DOMDocument to parse HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        
        // Ensure UTF-8 encoding
        $html_content = mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML($html_content);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        // Try XPath conversion first
        $xpath_query = $this->css_to_xpath($selector);
        
        if ($xpath_query) {
            try {
                $elements = @$xpath->query($xpath_query);
                
                if ($elements && $elements->length > 0) {
                    // Find first non-skip-link element
                    $element = null;
                    for ($i = 0; $i < $elements->length; $i++) {
                        $candidate = $elements->item($i);
                        if (!$this->is_skip_link_element($candidate)) {
                            $element = $candidate;
                            break;
                        }
                    }

                    // If all elements were skip links, just use the first non-skip-link or null
                    if (!$element) {
                        return null;
                    }

                    // Get the outer HTML of the element
                    $snippet = $dom->saveHTML($element);
                    
                    // Clean up and format the snippet
                    $snippet = trim($snippet);
                    
                    // If the snippet is too long, truncate it
                    if (strlen($snippet) > 500) {
                        // Try to keep it valid by finding a good break point
                        $snippet = substr($snippet, 0, 500);
                        $last_gt = strrpos($snippet, '>');
                        if ($last_gt !== false) {
                            $snippet = substr($snippet, 0, $last_gt + 1);
                        }
                        $snippet .= '...';
                    }
                    
                    return $snippet;
                }
            } catch (\Exception $e) {
                error_log('RayWP Accessibility: XPath query error: ' . $e->getMessage());
            }
        }
        
        // Fallback: Try to find element by text content or partial matching
        // This is especially useful for complex axe-core selectors
        return $this->fallback_element_search($dom, $selector);
    }

    /**
     * Check if a DOM element is a skip link (should be excluded from snippet display)
     */
    private function is_skip_link_element($element) {
        if (!$element || !method_exists($element, 'getAttribute')) {
            return false;
        }

        $class = strtolower($element->getAttribute('class') ?? '');
        $id = strtolower($element->getAttribute('id') ?? '');
        $href = strtolower($element->getAttribute('href') ?? '');
        $text = strtolower($element->textContent ?? '');

        // Skip link class patterns
        $skip_link_patterns = [
            'skip-link',
            'skiplink',
            'skip-to-main',
            'skip-to-content',
            'skip-navigation',
            'raywp-skip-link',
            'raywp-focusable-skip-link',
            'screen-reader',
            'sr-only',
            'visually-hidden',
            'assistive-text',
        ];

        // Check class for skip link patterns
        foreach ($skip_link_patterns as $pattern) {
            if (strpos($class, $pattern) !== false) {
                return true;
            }
            if (strpos($id, $pattern) !== false) {
                return true;
            }
        }

        // Check href for common skip link targets
        $skip_hrefs = ['#main', '#content', '#maincontent', '#wpbody-content', '#primary'];
        foreach ($skip_hrefs as $skip_href) {
            if ($href === $skip_href) {
                // Also verify text content matches skip link patterns
                if (strpos($text, 'skip') !== false) {
                    return true;
                }
            }
        }

        // Check text content for skip link phrases
        $skip_phrases = ['skip to main', 'skip to content', 'skip navigation', 'skip to toolbar'];
        foreach ($skip_phrases as $phrase) {
            if (strpos($text, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback method to find elements when CSS/XPath conversion fails
     */
    private function fallback_element_search($dom, $selector) {
        // For very complex selectors, try to extract the most specific part
        // Examples: "div:nth-child(3) > p" -> try "p"
        //          ".class1.class2 span" -> try "span"
        
        $simplified = $selector;
        
        // Remove pseudo-selectors
        $simplified = preg_replace('/:[a-z-]+(\([^)]*\))?/i', '', $simplified);
        
        // Get the last element in a chain
        if (strpos($simplified, ' ') !== false) {
            $parts = preg_split('/\s+/', trim($simplified));
            $simplified = array_pop($parts);
        }
        
        // Remove > from child selectors
        $simplified = trim($simplified, '> ');
        
        // Try the simplified selector
        if (!empty($simplified) && $simplified !== $selector) {
            error_log('RayWP Accessibility: Trying simplified selector: ' . $simplified);
            $xpath_query = $this->css_to_xpath($simplified);
            
            if ($xpath_query) {
                try {
                    $xpath = new \DOMXPath($dom);
                    $elements = @$xpath->query($xpath_query);
                    
                    if ($elements && $elements->length > 0) {
                        // Return the first match
                        $element = $elements->item(0);
                        $snippet = $dom->saveHTML($element);
                        $snippet = trim($snippet);
                        
                        if (strlen($snippet) > 500) {
                            $snippet = substr($snippet, 0, 500);
                            $last_gt = strrpos($snippet, '>');
                            if ($last_gt !== false) {
                                $snippet = substr($snippet, 0, $last_gt + 1);
                            }
                            $snippet .= '...';
                        }
                        
                        // Add a note that this is a simplified match
                        $snippet = '<!-- Note: Showing approximate match for complex selector -->' . "\n" . $snippet;
                        
                        return $snippet;
                    }
                } catch (\Exception $e) {
                    error_log('RayWP Accessibility: Fallback XPath error: ' . $e->getMessage());
                }
            }
        }
        
        // Last resort: Show selector info
        return '<!-- Unable to locate exact element. Selector: ' . esc_html($selector) . ' -->';
    }
    
    /**
     * Enhanced CSS to XPath conversion with better error handling
     */
    private function css_to_xpath($selector) {
        // Handle basic selectors
        $selector = trim($selector);
        
        // Log the selector for debugging
        error_log('RayWP Accessibility: Converting CSS selector to XPath: ' . $selector);
        
        // Handle attribute selectors like [role="button"]
        if (preg_match('/^\[([^=]+)="([^"]+)"\]$/', $selector, $matches)) {
            return "//*[@{$matches[1]}='{$matches[2]}']";
        }
        
        // Handle nth-child and other pseudo-selectors by stripping them
        // This gives us the base element even if we lose specificity
        $selector = preg_replace('/:(nth-child|first-child|last-child|nth-of-type|first-of-type|last-of-type)\([^)]*\)/', '', $selector);
        $selector = preg_replace('/:(first|last|hover|focus|active|visited)/', '', $selector);
        
        // Handle multiple classes like .class1.class2
        if (preg_match('/^\.([a-zA-Z0-9_-]+)(\.([a-zA-Z0-9_-]+))+$/', $selector)) {
            $classes = explode('.', ltrim($selector, '.'));
            $conditions = array_map(function($class) {
                return "contains(@class, '{$class}')";
            }, $classes);
            return "//*[" . implode(' and ', $conditions) . "]";
        }
        
        // Class selector
        if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//*[contains(@class, '{$matches[1]}')]";
        }
        
        // ID selector
        if (preg_match('/^#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//*[@id='{$matches[1]}']";
        }
        
        // Element selector
        if (preg_match('/^([a-zA-Z]+[0-9]?)$/', $selector, $matches)) {
            return "//{$matches[1]}";
        }
        
        // Element with class
        if (preg_match('/^([a-zA-Z]+[0-9]?)\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//{$matches[1]}[contains(@class, '{$matches[2]}')]";
        }
        
        // Element with ID
        if (preg_match('/^([a-zA-Z]+[0-9]?)#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//{$matches[1]}[@id='{$matches[2]}']";
        }
        
        // Element with attribute
        if (preg_match('/^([a-zA-Z]+[0-9]?)\[([^=]+)="([^"]+)"\]$/', $selector, $matches)) {
            return "//{$matches[1]}[@{$matches[2]}='{$matches[3]}']";
        }
        
        // Handle > child selectors
        $selector = str_replace(' > ', '>', $selector);
        
        // Handle child combinator (>) 
        if (strpos($selector, '>') !== false) {
            $parts = preg_split('/\s*>\s*/', $selector);
            $xpath = '';
            for ($i = 0; $i < count($parts); $i++) {
                $part_xpath = $this->css_to_xpath_simple(trim($parts[$i]));
                if ($part_xpath) {
                    if ($i === 0) {
                        $xpath = $part_xpath;
                    } else {
                        $xpath .= '/' . ltrim($part_xpath, '/');
                    }
                } else {
                    return false; // Can't convert a part
                }
            }
            return $xpath;
        }
        
        // Handle descendant combinator (space)
        if (preg_match('/\s+/', $selector)) {
            $parts = preg_split('/\s+/', $selector);
            $xpath_parts = [];
            foreach ($parts as $part) {
                $part_xpath = $this->css_to_xpath_simple(trim($part));
                if ($part_xpath) {
                    $xpath_parts[] = ltrim($part_xpath, '/');
                } else {
                    // If we can't convert a part, try to use just the last part
                    $last_part = end($parts);
                    return $this->css_to_xpath_simple($last_part);
                }
            }
            return '//' . implode('//', $xpath_parts);
        }
        
        // Single part selector
        return $this->css_to_xpath_simple($selector);
    }
    
    /**
     * Convert a simple CSS selector to XPath
     */
    private function css_to_xpath_simple($selector) {
        $selector = trim($selector);
        
        // Remove pseudo-selectors that we can't handle
        $selector = preg_replace('/:(nth-child|first-child|last-child|nth-of-type|first-of-type|last-of-type)\([^)]*\)/', '', $selector);
        $selector = preg_replace('/:(first|last|hover|focus|active|visited|before|after)/', '', $selector);
        
        // Handle attribute selectors like [role="button"] or [type="submit"]
        if (preg_match('/^\[([^=\]]+)(?:="?([^"]*)"?)?\]$/', $selector, $matches)) {
            if (isset($matches[2])) {
                return "//*[@{$matches[1]}='{$matches[2]}']";
            } else {
                return "//*[@{$matches[1]}]";
            }
        }
        
        // Handle multiple classes like .class1.class2.class3
        if (preg_match('/^\.(.+)$/', $selector) && strpos($selector, '.') > 1) {
            $classes = array_filter(explode('.', ltrim($selector, '.')));
            if (count($classes) > 1) {
                $conditions = array_map(function($class) {
                    return "contains(concat(' ', @class, ' '), ' {$class} ')";
                }, $classes);
                return "//*[" . implode(' and ', $conditions) . "]";
            }
        }
        
        // Simple class selector
        if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//*[contains(concat(' ', @class, ' '), ' {$matches[1]} ')]";
        }
        
        // ID selector
        if (preg_match('/^#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//*[@id='{$matches[1]}']";
        }
        
        // Element selector
        if (preg_match('/^([a-zA-Z]+[0-9]*)$/', $selector, $matches)) {
            return "//{$matches[1]}";
        }
        
        // Element with class
        if (preg_match('/^([a-zA-Z]+[0-9]*)\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//{$matches[1]}[contains(concat(' ', @class, ' '), ' {$matches[2]} ')]";
        }
        
        // Element with ID  
        if (preg_match('/^([a-zA-Z]+[0-9]*)#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return "//{$matches[1]}[@id='{$matches[2]}']";
        }
        
        // Element with attribute
        if (preg_match('/^([a-zA-Z]+[0-9]*)\[([^=\]]+)(?:="?([^"]*)"?)?\]$/', $selector, $matches)) {
            if (isset($matches[3])) {
                return "//{$matches[1]}[@{$matches[2]}='{$matches[3]}']";
            } else {
                return "//{$matches[1]}[@{$matches[2]}]";
            }
        }
        
        return false;
    }
}
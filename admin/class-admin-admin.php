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
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'raywp-accessibility-admin',
            RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-ajax-response'],
            RAYWP_ACCESSIBILITY_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('raywp-accessibility-admin', 'raywpAccessibility', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('raywp_accessibility_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this rule?', 'raywp-accessibility'),
                'testing_selector' => __('Testing selector...', 'raywp-accessibility'),
                'scanning_forms' => __('Scanning forms...', 'raywp-accessibility'),
                'applying_fixes' => __('Applying fixes...', 'raywp-accessibility'),
                'success' => __('Success!', 'raywp-accessibility'),
                'error' => __('An error occurred', 'raywp-accessibility')
            ]
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
            'enable_color_overrides'
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
        
        $aria_rules_count = count($aria_manager->get_aria_rules());
        ?>
        <div class="wrap">
            <div class="raywp-dashboard-header">
                <img src="<?php echo RAYWP_ACCESSIBILITY_PLUGIN_URL; ?>assets/images/Ray-Logo.webp" alt="Ray" class="raywp-logo" />
                <h1><?php echo esc_html(get_admin_page_title()); ?> <span style="font-size: 0.5em; color: #666;">v<?php echo RAYWP_ACCESSIBILITY_VERSION; ?></span></h1>
            </div>
            
            <div class="raywp-dashboard">
                <div class="raywp-dashboard-widgets">
                    <div class="raywp-widget">
                        <h2><?php _e('Quick Stats', 'raywp-accessibility'); ?></h2>
                        <ul>
                            <li><?php printf(__('Active ARIA Rules: %d', 'raywp-accessibility'), $aria_rules_count); ?></li>
                            <li><?php _e('Last Scan: Never', 'raywp-accessibility'); ?></li>
                            <li><?php _e('Accessibility Score: Not calculated', 'raywp-accessibility'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="raywp-widget">
                        <h2><?php _e('Quick Actions', 'raywp-accessibility'); ?></h2>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=raywp-accessibility-aria'); ?>" class="button button-primary">
                                <?php _e('Manage ARIA Rules', 'raywp-accessibility'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=raywp-accessibility-reports'); ?>" class="button">
                                <?php _e('View Reports', 'raywp-accessibility'); ?>
                            </a>
                        </p>
                    </div>
                    
                    <div class="raywp-widget">
                        <h2><?php _e('System Info', 'raywp-accessibility'); ?></h2>
                        <ul>
                            <li><strong><?php _e('Plugin Version:', 'raywp-accessibility'); ?></strong> <?php echo RAYWP_ACCESSIBILITY_VERSION; ?></li>
                            <li><strong><?php _e('Last Updated:', 'raywp-accessibility'); ?></strong> <?php echo date('F j, Y', strtotime('2025-08-25')); ?></li>
                            <li><strong><?php _e('Security Fixes:', 'raywp-accessibility'); ?></strong> ✓ Applied</li>
                            <li><strong><?php _e('Performance Monitor:', 'raywp-accessibility'); ?></strong> ✓ Active</li>
                        </ul>
                    </div>
                    
                    <div class="raywp-widget">
                        <h2><?php _e('Features', 'raywp-accessibility'); ?></h2>
                        <ul>
                            <li>✓ <?php _e('Automatic ARIA attribute injection', 'raywp-accessibility'); ?></li>
                            <li>✓ <?php _e('Skip links & main landmarks', 'raywp-accessibility'); ?></li>
                            <li>✓ <?php _e('Form accessibility fixes', 'raywp-accessibility'); ?></li>
                            <li>✓ <?php _e('Heading hierarchy correction', 'raywp-accessibility'); ?></li>
                            <li>✓ <?php _e('Full site accessibility scanning', 'raywp-accessibility'); ?></li>
                            <li>✓ <?php _e('WCAG 2.1 compliance reporting', 'raywp-accessibility'); ?></li>
                        </ul>
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
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="raywp-aria-manager">
                <h2><?php _e('Add New ARIA Rule', 'raywp-accessibility'); ?></h2>
                <form id="raywp-add-aria-rule" class="raywp-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="aria-selector"><?php _e('CSS Selector', 'raywp-accessibility'); ?></label></th>
                            <td>
                                <input type="text" id="aria-selector" name="selector" class="regular-text" required />
                                <button type="button" class="button" id="test-selector"><?php _e('Test Selector', 'raywp-accessibility'); ?></button>
                                <p class="description"><?php _e('Enter a CSS selector (e.g., .navigation a, #header)', 'raywp-accessibility'); ?></p>
                                <div id="selector-test-results"></div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="aria-attribute"><?php _e('ARIA Attribute', 'raywp-accessibility'); ?></label></th>
                            <td>
                                <select id="aria-attribute" name="attribute" required>
                                    <option value=""><?php _e('Select an attribute', 'raywp-accessibility'); ?></option>
                                    <optgroup label="<?php _e('Common Attributes', 'raywp-accessibility'); ?>">
                                        <option value="aria-label">aria-label</option>
                                        <option value="aria-labelledby">aria-labelledby</option>
                                        <option value="aria-describedby">aria-describedby</option>
                                        <option value="aria-hidden">aria-hidden</option>
                                        <option value="aria-live">aria-live</option>
                                        <option value="aria-current">aria-current</option>
                                        <option value="role">role (Landmark)</option>
                                    </optgroup>
                                    <optgroup label="<?php _e('State Attributes', 'raywp-accessibility'); ?>">
                                        <option value="aria-checked">aria-checked</option>
                                        <option value="aria-disabled">aria-disabled</option>
                                        <option value="aria-expanded">aria-expanded</option>
                                        <option value="aria-pressed">aria-pressed</option>
                                        <option value="aria-selected">aria-selected</option>
                                    </optgroup>
                                    <optgroup label="<?php _e('All Attributes', 'raywp-accessibility'); ?>">
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
                            <th><label for="aria-value"><?php _e('Value', 'raywp-accessibility'); ?></label></th>
                            <td>
                                <input type="text" id="aria-value" name="value" class="regular-text" required />
                                <p class="description"><?php _e('Enter the value for the attribute', 'raywp-accessibility'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Add ARIA Rule', 'raywp-accessibility'); ?></button>
                    </p>
                </form>
                
                <h2><?php _e('Existing ARIA Rules', 'raywp-accessibility'); ?></h2>
                <?php if (empty($aria_rules)) : ?>
                    <p><?php _e('No ARIA rules configured yet.', 'raywp-accessibility'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Selector', 'raywp-accessibility'); ?></th>
                                <th><?php _e('Attribute', 'raywp-accessibility'); ?></th>
                                <th><?php _e('Value', 'raywp-accessibility'); ?></th>
                                <th><?php _e('Actions', 'raywp-accessibility'); ?></th>
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
                                            <?php _e('Delete', 'raywp-accessibility'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
                <h2><?php _e('Real-time Form Accessibility', 'raywp-accessibility'); ?></h2>
                <p><?php _e('Form accessibility fixes are now applied automatically in real-time as pages load. This works with any form plugin or HTML forms.', 'raywp-accessibility'); ?></p>
                
                <div class="raywp-widget">
                    <h3><?php _e('Automatic Form Fixes', 'raywp-accessibility'); ?></h3>
                    <p><strong>Status:</strong> 
                        <?php if (!empty($settings['fix_forms'])): ?>
                            <span style="color: green;">✓ Enabled</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Disabled</span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if (empty($settings['fix_forms'])): ?>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=raywp-accessibility-settings'); ?>" class="button button-primary">
                                <?php _e('Enable Form Fixes', 'raywp-accessibility'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <h4><?php _e('What Gets Fixed Automatically:', 'raywp-accessibility'); ?></h4>
                    <ul>
                        <li>✓ <?php _e('Missing labels for form fields', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php _e('Fieldsets around radio/checkbox groups', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php _e('Required field indicators (aria-required)', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php _e('Form validation message improvements', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php _e('Form instructions for required fields', 'raywp-accessibility'); ?></li>
                        <li>✓ <?php _e('Proper ARIA attributes and roles', 'raywp-accessibility'); ?></li>
                    </ul>
                    
                    <h4><?php _e('Works With:', 'raywp-accessibility'); ?></h4>
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
                    <h3><?php _e('Form Scanning (Legacy)', 'raywp-accessibility'); ?></h3>
                    <p><?php _e('You can still scan individual forms to see what issues would be fixed:', 'raywp-accessibility'); ?></p>
                    
                    <p>
                        <button id="scan-forms" class="button">
                            <?php _e('Scan All Forms', 'raywp-accessibility'); ?>
                        </button>
                    </p>
                    
                    <div id="scan-results" style="display:none;">
                        <h4><?php _e('Scan Results', 'raywp-accessibility'); ?></h4>
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
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('raywp_accessibility_settings'); ?>
                
                <h2><?php _e('General Settings', 'raywp-accessibility'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Features', 'raywp-accessibility'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[enable_aria]" value="1" 
                                           <?php checked(!empty($settings['enable_aria'])); ?> />
                                    <?php _e('Enable ARIA attribute injection', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <!-- Accessibility checker widget disabled - use full site scanner in Reports tab instead
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[enable_checker]" value="1" 
                                           <?php checked(!empty($settings['enable_checker'])); ?> />
                                    <?php _e('Enable accessibility checker', 'raywp-accessibility'); ?>
                                </label><br>
                                -->
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_empty_alt]" value="1" 
                                           <?php checked(!empty($settings['fix_empty_alt'])); ?> />
                                    <?php _e('Add empty alt attributes to decorative images', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_lang_attr]" value="1" 
                                           <?php checked(!empty($settings['fix_lang_attr'])); ?> />
                                    <?php _e('Add missing language attributes', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_form_labels]" value="1" 
                                           <?php checked(!empty($settings['fix_form_labels'])); ?> />
                                    <?php _e('Fix missing form labels', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[add_skip_links]" value="1" 
                                           <?php checked(!empty($settings['add_skip_links'])); ?> />
                                    <?php _e('Add skip navigation links', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_forms]" value="1" 
                                           <?php checked(!empty($settings['fix_forms'])); ?> />
                                    <?php _e('Apply comprehensive form accessibility fixes', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[add_main_landmark]" value="1" 
                                           <?php checked(!empty($settings['add_main_landmark'])); ?> />
                                    <?php _e('Add main landmark if missing', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_heading_hierarchy]" value="1" 
                                           <?php checked(!empty($settings['fix_heading_hierarchy'])); ?> />
                                    <?php _e('Fix heading hierarchy issues', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label style="margin-left: 25px;">
                                    <input type="checkbox" name="raywp_accessibility_settings[preserve_heading_styles]" value="1" 
                                           <?php checked(!empty($settings['preserve_heading_styles']) || !isset($settings['preserve_heading_styles'])); ?> />
                                    <?php _e('Preserve heading styles (use aria-level instead of changing tags)', 'raywp-accessibility'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="raywp_accessibility_settings[fix_aria_controls]" value="1" 
                                           <?php checked(!empty($settings['fix_aria_controls'])); ?> />
                                    <?php _e('Fix missing aria-controls on expandable buttons', 'raywp-accessibility'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Focus Enhancement', 'raywp-accessibility'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[enhance_focus]" value="1" 
                                       <?php checked(!empty($settings['enhance_focus'])); ?> />
                                <?php _e('Enhance focus indicators', 'raywp-accessibility'); ?>
                            </label><br>
                            
                            <label>
                                <?php _e('Focus outline color:', 'raywp-accessibility'); ?>
                                <input type="text" name="raywp_accessibility_settings[focus_outline_color]" 
                                       value="<?php echo esc_attr($settings['focus_outline_color'] ?? '#0073aa'); ?>" 
                                       class="color-picker" />
                            </label><br>
                            
                            <label>
                                <?php _e('Focus outline width:', 'raywp-accessibility'); ?>
                                <input type="text" name="raywp_accessibility_settings[focus_outline_width]" 
                                       value="<?php echo esc_attr($settings['focus_outline_width'] ?? '2px'); ?>" />
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Color Contrast', 'raywp-accessibility'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[fix_contrast]" value="1" 
                                       <?php checked(!empty($settings['fix_contrast'])); ?> />
                                <?php _e('Automatically fix low contrast text', 'raywp-accessibility'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, text with poor contrast will be automatically adjusted to darker colors. Disable this if you prefer to manually adjust colors in your theme.', 'raywp-accessibility'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Custom Color Overrides', 'raywp-accessibility'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raywp_accessibility_settings[enable_color_overrides]" value="1" 
                                       <?php checked(!empty($settings['enable_color_overrides'])); ?> />
                                <?php _e('Enable custom color overrides', 'raywp-accessibility'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Advanced feature: Override specific element colors without modifying your theme. Changes are applied dynamically and can be reverted by disabling this option.', 'raywp-accessibility'); ?>
                            </p>
                            
                            <div id="raywp-color-overrides-section" style="<?php echo empty($settings['enable_color_overrides']) ? 'display:none;' : ''; ?>margin-top: 20px;">
                                <h4><?php _e('Color Override Rules', 'raywp-accessibility'); ?></h4>
                                <p class="description"><?php _e('Enter CSS selectors and the colors you want to apply. For example: .header-text for a class, #main-title for an ID, or h2 for an element.', 'raywp-accessibility'); ?></p>
                                
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
                                                    <button type="button" class="button-link delete-color-override" data-index="<?php echo esc_attr($index); ?>"><?php _e('Remove', 'raywp-accessibility'); ?></button>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <div id="raywp-add-color-override" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd;">
                                    <h5><?php _e('Add New Override', 'raywp-accessibility'); ?></h5>
                                    <table class="form-table">
                                        <tr>
                                            <td>
                                                <input type="text" id="override-selector" placeholder="<?php esc_attr_e('CSS Selector (e.g., .my-class, #my-id)', 'raywp-accessibility'); ?>" style="width: 100%;" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <input type="text" id="override-color" placeholder="<?php esc_attr_e('Text Color (e.g., #000000)', 'raywp-accessibility'); ?>" class="color-picker" />
                                                <span class="description"><?php _e('Leave empty to keep original', 'raywp-accessibility'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <input type="text" id="override-background" placeholder="<?php esc_attr_e('Background Color (e.g., #ffffff)', 'raywp-accessibility'); ?>" class="color-picker" />
                                                <span class="description"><?php _e('Optional - leave empty to keep original', 'raywp-accessibility'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <button type="button" id="add-color-override-btn" class="button button-primary"><?php _e('Add Override', 'raywp-accessibility'); ?></button>
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
        // Get reports component to show existing data
        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
        $reports = $plugin->get_component('reports');
        // Try multiple sources for the current score
        $recent_scan_score = get_transient('raywp_accessibility_last_scan_score');
        $stored_score = get_option('raywp_accessibility_current_score');
        $current_score = $recent_scan_score !== false ? $recent_scan_score : 
                        ($stored_score ? $stored_score : 
                        ($reports ? $reports->calculate_accessibility_score() : null));
        
        // Debug the compliance status issue
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RayWP Accessibility Debug - Recent scan score: ' . var_export($recent_scan_score, true));
            error_log('RayWP Accessibility Debug - Current score: ' . var_export($current_score, true));
        }
        $issue_summary = $reports ? $reports->get_issue_summary() : [];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="raywp-reports">
                <p><?php _e('Comprehensive accessibility reports and analytics for your website.', 'raywp-accessibility'); ?></p>
                
                <div class="raywp-report-section">
                    <h2><?php _e('Accessibility Score', 'raywp-accessibility'); ?></h2>
                    <div class="raywp-accessibility-score" style="font-size: 48px; font-weight: bold; color: #0073aa;">
                        <?php 
                        if ($current_score === null) {
                            echo '--';
                        } else {
                            echo $current_score . '%';
                        }
                        ?>
                    </div>
                    <p><?php _e('Run a full site scan to calculate your current accessibility score.', 'raywp-accessibility'); ?></p>
                    <div style="margin-top: 10px;">
                        <button id="run-full-scan" class="button button-primary"><?php _e('Run Full Scan', 'raywp-accessibility'); ?></button>
                        <button id="enable-all-fixes" class="button" style="margin-left: 10px;"><?php _e('Enable All Auto-Fixes', 'raywp-accessibility'); ?></button>
                    </div>
                    <?php 
                    // Debug: Show current fix settings
                    $settings = get_option('raywp_accessibility_settings', []);
                    $fixes_status = [
                        'fix_forms' => !empty($settings['fix_forms']),
                        'add_main_landmark' => !empty($settings['add_main_landmark']),
                        'fix_heading_hierarchy' => !empty($settings['fix_heading_hierarchy'])
                    ];
                    ?>
                    <p style="margin-top: 15px; font-size: 12px; color: #666;">
                        <?php _e('Auto-fix status:', 'raywp-accessibility'); ?>
                        Forms: <?php echo $fixes_status['fix_forms'] ? '✓' : '✗'; ?> |
                        Landmarks: <?php echo $fixes_status['add_main_landmark'] ? '✓' : '✗'; ?> |
                        Headings: <?php echo $fixes_status['fix_heading_hierarchy'] ? '✓' : '✗'; ?>
                    </p>
                </div>
                
                <div class="raywp-report-section">
                    <h2><?php _e('Scan Results', 'raywp-accessibility'); ?></h2>
                    <div class="raywp-scan-results">
                        <?php if (empty($issue_summary)): ?>
                            <p><?php _e('No scans performed yet. Click "Run Full Scan" to analyze your site.', 'raywp-accessibility'); ?></p>
                        <?php else: ?>
                            <h3><?php _e('Current Issues', 'raywp-accessibility'); ?></h3>
                            <ul>
                                <?php foreach ($issue_summary as $issue): ?>
                                    <li>
                                        <strong><?php echo esc_html(ucfirst($issue->issue_severity)); ?>:</strong> 
                                        <?php echo esc_html($issue->issue_type); ?> 
                                        (<?php echo intval($issue->count); ?> issues)
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="raywp-two-column-layout">
                    <div class="raywp-column-left">
                        <div class="raywp-report-section">
                            <h2><?php _e('What Gets Scanned', 'raywp-accessibility'); ?></h2>
                            <p><?php _e('The full scan analyzes up to 100 pages from your site:', 'raywp-accessibility'); ?></p>
                            <ul>
                                <li>• <?php _e('Homepage', 'raywp-accessibility'); ?></li>
                                <li>• <?php _e('All published blog posts', 'raywp-accessibility'); ?></li>
                                <li>• <?php _e('All published pages', 'raywp-accessibility'); ?></li>
                                <li>• <?php _e('Custom post types (if any)', 'raywp-accessibility'); ?></li>
                            </ul>
                            <p><em><?php _e('Limited to 100 pages maximum to prevent timeouts on large sites.', 'raywp-accessibility'); ?></em></p>
                            <p><?php _e('Each page is checked for:', 'raywp-accessibility'); ?></p>
                            <ul>
                                <li>• Missing alt text on images</li>
                                <li>• Missing form labels</li>
                                <li>• Empty headings</li>
                                <li>• Color contrast issues</li>
                                <li>• Keyboard accessibility problems</li>
                                <li>• Invalid ARIA attributes</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="raywp-column-right">
                        <div class="raywp-report-section">
                            <h2><?php _e('Compliance Status', 'raywp-accessibility'); ?></h2>
                            <?php
                            $compliance_status = 'not-tested';
                            $compliance_message = __('Not tested', 'raywp-accessibility');
                            
                            if ($current_score !== null) {
                                // Ensure we're working with a numeric value
                                $score = (int) $current_score;
                                
                                if ($score >= 95) {
                                    $compliance_status = 'excellent';
                                    $compliance_message = __('Excellent compliance', 'raywp-accessibility');
                                } elseif ($score >= 85) {
                                    $compliance_status = 'good';
                                    $compliance_message = __('Good compliance', 'raywp-accessibility');
                                } elseif ($score >= 70) {
                                    $compliance_status = 'needs-improvement';
                                    $compliance_message = __('Needs improvement', 'raywp-accessibility');
                                } else {
                                    $compliance_status = 'poor';
                                    $compliance_message = __('Poor compliance', 'raywp-accessibility');
                                }
                                
                                // Debug output
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log('RayWP Accessibility Debug - Score: ' . $score . ', Status: ' . $compliance_status);
                                }
                            }
                            ?>
                            <ul>
                                <li>WCAG 2.1 Level A: <?php echo esc_html($compliance_message); ?></li>
                                <li>WCAG 2.1 Level AA: <?php echo esc_html($compliance_message); ?></li>
                                <li>ADA Compliance: <?php echo esc_html($compliance_message); ?></li>
                                <li>EAA Compliance: <?php echo esc_html($compliance_message); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="raywp-report-section raywp-accessibility-standards">
                    <h2><?php _e('About Accessibility Standards', 'raywp-accessibility'); ?></h2>
                    <div class="raywp-standards-grid">
                        <div class="raywp-standard">
                            <h3>WCAG 2.1</h3>
                            <p><?php _e('Web Content Accessibility Guidelines (WCAG) 2.1 is the international standard for web accessibility, developed by the W3C. It provides guidelines to make web content more accessible to people with disabilities.', 'raywp-accessibility'); ?></p>
                            <p><a href="https://www.w3.org/WAI/WCAG21/quickref/" target="_blank" rel="noopener"><?php _e('Learn more about WCAG 2.1 →', 'raywp-accessibility'); ?></a></p>
                        </div>
                        
                        <div class="raywp-standard">
                            <h3>ADA</h3>
                            <p><?php _e('The Americans with Disabilities Act (ADA) is a US civil rights law that prohibits discrimination based on disability. Title III requires places of public accommodation to be accessible, which courts have interpreted to include websites.', 'raywp-accessibility'); ?></p>
                            <p><a href="https://www.ada.gov/resources/web-guidance/" target="_blank" rel="noopener"><?php _e('Learn more about ADA compliance →', 'raywp-accessibility'); ?></a></p>
                        </div>
                        
                        <div class="raywp-standard">
                            <h3>EAA</h3>
                            <p><?php _e('The European Accessibility Act (EAA) is an EU directive that sets accessibility requirements for products and services. It requires websites and mobile applications to be accessible to people with disabilities.', 'raywp-accessibility'); ?></p>
                            <p><a href="https://ec.europa.eu/social/main.jsp?catId=1202" target="_blank" rel="noopener"><?php _e('Learn more about EAA →', 'raywp-accessibility'); ?></a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
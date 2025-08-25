<?php
/**
 * Frontend Manager
 */

namespace RayWP\Accessibility\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {
    
    /**
     * Settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('raywp_accessibility_settings', []);
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_head', [$this, 'add_inline_styles']);
        add_action('wp_footer', [$this, 'add_inline_scripts'], 999);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Main frontend CSS
        wp_enqueue_style(
            'raywp-accessibility-frontend',
            RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            RAYWP_ACCESSIBILITY_VERSION
        );
        
        // Main frontend JS
        wp_enqueue_script(
            'raywp-accessibility-frontend',
            RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            RAYWP_ACCESSIBILITY_VERSION,
            true
        );
        
        // Localize script with ARIA rules
        // Get ARIA rules directly from options to avoid circular dependency
        $aria_rules = get_option('raywp_accessibility_aria_rules', []);
        $js_rules = [];
        
        foreach ($aria_rules as $rule) {
            if (isset($rule['selector'], $rule['attribute'], $rule['value'])) {
                $js_rules[] = [
                    'selector' => $rule['selector'],
                    'attribute' => $rule['attribute'],
                    'value' => $rule['value']
                ];
            }
        }
        
        wp_localize_script('raywp-accessibility-frontend', 'raywpAccessibilityFrontend', [
            'aria_rules' => $js_rules,
            'settings' => $this->settings
        ]);
        
        // Accessibility checker widget disabled - use admin scanner instead
        // if (!empty($this->settings['enable_checker'])) {
        //     wp_enqueue_style(
        //         'raywp-accessibility-checker',
        //         RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/css/checker.css',
        //         [],
        //         RAYWP_ACCESSIBILITY_VERSION
        //     );
        //     
        //     wp_enqueue_script(
        //         'raywp-accessibility-checker',
        //         RAYWP_ACCESSIBILITY_PLUGIN_URL . 'assets/js/checker.js',
        //         ['jquery', 'raywp-accessibility-frontend'],
        //         RAYWP_ACCESSIBILITY_VERSION,
        //         true
        //     );
        // }
    }
    
    /**
     * Add inline styles
     */
    public function add_inline_styles() {
        $styles = '';
        
        // Skip links styles
        if (!empty($this->settings['add_skip_links'])) {
            $styles .= '
                .raywp-skip-links {
                    position: absolute;
                    left: -9999px;
                    top: 0;
                    z-index: 999999;
                }
                .raywp-skip-links a {
                    position: absolute;
                    left: 9999px;
                    padding: 10px 20px;
                    background: #000;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 0 0 4px 0;
                }
                .raywp-skip-links a:focus {
                    left: 0;
                }
            ';
        }
        
        // Enhanced focus styles
        if (!empty($this->settings['enhance_focus'])) {
            $outline_color = $this->settings['focus_outline_color'] ?? '#0073aa';
            $outline_width = $this->settings['focus_outline_width'] ?? '2px';
            
            $styles .= "
                *:focus {
                    outline: $outline_width solid $outline_color !important;
                    outline-offset: 2px !important;
                }
                a:focus,
                button:focus,
                input:focus,
                select:focus,
                textarea:focus {
                    outline: $outline_width solid $outline_color !important;
                    outline-offset: 2px !important;
                }
            ";
        }
        
        if (!empty($styles)) {
            echo '<style id="raywp-accessibility-inline-styles">' . $styles . '</style>';
        }
        
        // Add custom color overrides if enabled
        if (!empty($this->settings['enable_color_overrides'])) {
            $color_overrides = get_option('raywp_accessibility_color_overrides', []);
            if (!empty($color_overrides)) {
                $override_styles = '';
                foreach ($color_overrides as $override) {
                    if (!empty($override['selector'])) {
                        $override_styles .= $override['selector'] . ' {';
                        if (!empty($override['color'])) {
                            $override_styles .= ' color: ' . $override['color'] . ' !important;';
                        }
                        if (!empty($override['background'])) {
                            $override_styles .= ' background-color: ' . $override['background'] . ' !important;';
                        }
                        $override_styles .= ' }' . "\n";
                    }
                }
                
                if (!empty($override_styles)) {
                    echo '<style id="raywp-accessibility-color-overrides">' . "\n";
                    echo '/* RayWP Accessibility Custom Color Overrides */' . "\n";
                    echo $override_styles;
                    echo '</style>' . "\n";
                }
            }
        }
    }
    
    /**
     * Add inline scripts
     */
    public function add_inline_scripts() {
        // Add any necessary inline scripts
        if (!empty($this->settings['enable_aria'])) {
            ?>
            <script>
            // Fallback for dynamic content
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof raywpAccessibilityFrontend !== 'undefined' && raywpAccessibilityFrontend.aria_rules) {
                    // Observe DOM changes for dynamic content
                    const observer = new MutationObserver(function(mutations) {
                        raywpAccessibilityApplyAriaRules();
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
            });
            </script>
            <?php
        }
    }
}
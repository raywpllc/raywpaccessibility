<?php
/**
 * Accessibility Checker
 */

namespace RayWP\Accessibility\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Accessibility_Checker {
    
    /**
     * Current URL being scanned
     */
    private $current_url = '';
    private $apply_fixes = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize checker functionality
    }
    
    /**
     * Run accessibility check on content
     */
    public function check_content($content, $url = '') {
        $issues = [];
        
        // Store URL for contrast detection
        $this->current_url = $url;
        
        // Create DOM
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        // Check for missing alt attributes
        $images = $xpath->query('//img[not(@alt)]');
        foreach ($images as $img) {
            $details = $this->get_element_details($img);
            $issues[] = [
                'type' => 'missing_alt',
                'severity' => 'high',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Image missing alt attribute',
                'description' => 'This image does not have an alt attribute, making it inaccessible to screen readers.',
                'suggestion' => 'Add alt="" for decorative images or alt="descriptive text" for informative images.',
                'wcag_reference' => 'WCAG 2.1 Level A - 1.1.1 Non-text Content',
                'wcag_criterion' => '1.1.1',
                'wcag_level' => 'A',
                'compliance_impact' => 'VIOLATION',
                'how_to_fix' => 'Add alt attribute to the img element: <img src="..." alt="description of image">',
                'auto_fixable' => true
            ];
        }
        
        // Check for empty headings
        $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        foreach ($headings as $heading) {
            if (trim($heading->textContent) === '') {
                $details = $this->get_element_details($heading);
                $issues[] = [
                    'type' => 'empty_heading',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Empty heading found',
                    'description' => 'This heading element has no text content, which can confuse screen reader users.',
                    'suggestion' => 'Either add meaningful text to the heading or remove it entirely.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 2.4.6 Headings and Labels',
                    'wcag_criterion' => '2.4.6',
                    'wcag_level' => 'AA',
                    'compliance_impact' => 'WARNING',
                    'how_to_fix' => 'Add text content to the heading: <' . $heading->tagName . '>Meaningful heading text</' . $heading->tagName . '>',
                    'auto_fixable' => false
                ];
            }
        }
        
        // Check for missing form labels
        $inputs = $xpath->query('//input[@type!="hidden" and @type!="submit" and @type!="button"]');
        foreach ($inputs as $input) {
            $id = $input->getAttribute('id');
            $has_label = false;
            $input_type = $input->getAttribute('type') ?: 'text';
            
            // For image inputs, alt attribute serves as the label
            if ($input_type === 'image' && $input->hasAttribute('alt') && !empty(trim($input->getAttribute('alt')))) {
                continue; // Alt text is sufficient for image inputs
            }
            
            if ($id) {
                $labels = $xpath->query("//label[@for='$id']");
                $has_label = $labels->length > 0;
            }
            
            if (!$has_label && !$input->hasAttribute('aria-label') && !$input->hasAttribute('aria-labelledby')) {
                $details = $this->get_element_details($input);
                $input_name = $input->getAttribute('name') ?: 'unnamed';
                
                $issues[] = [
                    'type' => 'missing_label',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Form input missing label',
                    'description' => "This {$input_type} input field (name: {$input_name}) has no accessible label, making it impossible for screen reader users to understand its purpose.",
                    'suggestion' => $input_type === 'image' ? 
                        'Add alt attribute with descriptive text for this image button.' :
                        'Add a <label> element associated with this input, or use aria-label attribute.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 3.3.2 Labels or Instructions',
                    'wcag_criterion' => '3.3.2',
                    'wcag_level' => 'A',
                    'compliance_impact' => 'VIOLATION',
                    'how_to_fix' => $input_type === 'image' ?
                        'Add alt="Submit" or more descriptive text to the input element' :
                        ($id ? "Add: <label for=\"{$id}\">Field Description</label>" : 
                        'Add an id to the input and create: <label for="field-id">Field Description</label>'),
                    'auto_fixable' => true
                ];
            }
        }
        
        // Check color contrast (simplified check)
        $this->check_color_contrast($xpath, $issues);
        
        // Check keyboard accessibility
        $this->check_keyboard_accessibility($xpath, $issues);
        
        // Check ARIA attributes
        $this->scan_aria_attributes($xpath, $issues);
        
        // Check semantic structure
        $this->check_semantic_structure($xpath, $issues);
        
        // Check advanced form issues
        $this->check_advanced_form_issues($xpath, $issues);
        
        // Additional comprehensive checks
        $this->check_duplicate_ids($xpath, $issues);
        $this->check_page_language($xpath, $issues);
        $this->check_link_purposes($xpath, $issues);
        $this->check_iframe_accessibility($xpath, $issues);
        $this->check_media_accessibility($xpath, $issues);
        
        // Screen reader compatibility checks
        $this->check_screen_reader_compatibility($xpath, $issues);
        
        // Additional WCAG AA Level checks
        $this->check_text_spacing_compliance($xpath, $issues);
        $this->check_resize_text_compliance($xpath, $issues);
        $this->check_motion_animation_controls($xpath, $issues);
        $this->check_enhanced_focus_order($xpath, $issues);
        $this->check_enhanced_error_identification($xpath, $issues);
        $this->check_target_size_compliance($xpath, $issues);
        $this->check_input_purpose_identification($xpath, $issues);
        
        return $issues;
    }
    
    /**
     * Get detailed element information
     */
    private function get_element_details($element, $context = '') {
        $selector = $element->tagName;
        
        if ($element->hasAttribute('id')) {
            $selector .= '#' . $element->getAttribute('id');
        } elseif ($element->hasAttribute('class')) {
            $classes = explode(' ', $element->getAttribute('class'));
            $selector .= '.' . implode('.', array_filter($classes));
        }
        
        // Get element's outer HTML (truncated for display)
        $html_snippet = $element->ownerDocument->saveHTML($element);
        if (strlen($html_snippet) > 500) {
            $html_snippet = substr($html_snippet, 0, 500) . '...';
        }
        
        // Get text content (truncated)
        $text_content = trim($element->textContent);
        if (strlen($text_content) > 100) {
            $text_content = substr($text_content, 0, 100) . '...';
        }
        
        // For contrast issues, create a more descriptive selector that includes text content
        if ($context === 'contrast' && !empty($text_content)) {
            $clean_text = preg_replace('/\s+/', ' ', $text_content);
            if (strlen($clean_text) > 50) {
                $clean_text = substr($clean_text, 0, 47) . '...';
            }
            $selector = $selector . ' ("' . $clean_text . '")';
        }
        
        // Get element position in DOM (rough estimate)
        $xpath = new \DOMXPath($element->ownerDocument);
        $position = $xpath->evaluate('count(preceding::*)', $element) + 1;
        
        return [
            'selector' => $selector,
            'html_snippet' => $html_snippet,
            'text_content' => $text_content,
            'tag_name' => $element->tagName,
            'attributes' => $this->get_element_attributes($element),
            'position' => $position
        ];
    }
    
    /**
     * Get element attributes
     */
    private function get_element_attributes($element) {
        $attributes = [];
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }
        return $attributes;
    }
    
    /**
     * Get element selector (legacy - kept for compatibility)
     */
    private function get_element_selector($element) {
        $details = $this->get_element_details($element);
        return $details['selector'];
    }
    
    /**
     * Check color contrast using WCAG 2.1 standards - Enhanced like WAVE
     */
    private function check_color_contrast($xpath, &$issues) {
        // Use JavaScript-based contrast detection for accuracy
        $contrast_issues = $this->detect_contrast_with_javascript();
        
        if (!empty($contrast_issues)) {
            foreach ($contrast_issues as $contrast_issue) {
                $this->add_javascript_contrast_issue($contrast_issue, $issues);
            }
        }
        
        // Only use fallback if we don't have JavaScript results
        // This prevents duplicate contrast issues
        if (empty($contrast_issues)) {
            // Fallback to PHP-based detection for non-JavaScript environments
            $this->check_color_contrast_fallback($xpath, $issues);
        }
    }

    /**
     * Detect contrast issues using JavaScript in browser context
     */
    private function detect_contrast_with_javascript() {
        // Try to run JavaScript-based contrast detection
        $js_results = $this->run_browser_contrast_analysis();
        return $js_results ?: [];
    }

    /**
     * Run JavaScript contrast analysis using browser automation
     */
    private function run_browser_contrast_analysis() {
        // Check if we can run JavaScript (e.g., through headless browser or AJAX)
        // For now, we'll implement a simple approach using file-based communication
        // In a real implementation, this could use Puppeteer, Chrome DevTools, etc.
        
        // Store current URL for JS analysis
        $current_url = $_SERVER['REQUEST_URI'] ?? '/';
        $current_url = home_url($current_url);
        
        // Always check for cached contrast results (from JavaScript)
        // This allows integration whether we're in AJAX context or regular scan
        return $this->get_ajax_contrast_results();
    }

    /**
     * Get contrast results from AJAX request (when JS has run)
     */
    private function get_ajax_contrast_results() {
        // Check if contrast results were passed via POST parameters (sanitized)
        if (isset($_POST['contrast_results']) && is_array($_POST['contrast_results'])) {
            // Sanitize each result in the array
            return array_map(function($result) {
                return [
                    'selector' => sanitize_text_field($result['selector'] ?? ''),
                    'contrastRatio' => floatval($result['contrastRatio'] ?? 0),
                    'foreground' => sanitize_hex_color($result['foreground'] ?? '#000000') ?: '#000000',
                    'background' => sanitize_hex_color($result['background'] ?? '#ffffff') ?: '#ffffff',
                    'text' => sanitize_text_field($result['text'] ?? ''),
                    'wcagLevel' => sanitize_text_field($result['wcagLevel'] ?? ''),
                ];
            }, $_POST['contrast_results']);
        }

        // Use the current URL being scanned, or fall back to REQUEST_URI (sanitized)
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $url_to_check = $this->current_url ?? home_url($request_uri);
        
        // Check if there are stored results in a temp file or cache
        // Match the cache key generation from AJAX handler (uses full URL)
        $cache_key = 'raywp_contrast_results_' . md5($url_to_check);
        $cached_results = get_transient($cache_key);
        
        // Check if we have pre-calculated results when scanning with fixes
        if ($this->apply_fixes) {
            $precalc_results = $this->get_precalculated_contrast_results($url_to_check);
            if ($precalc_results !== false) {
                error_log('RayWP: Using pre-calculated contrast results for: ' . $url_to_check);
                return $precalc_results;
            }
        }
        
        // Always apply color override filters if color overrides are enabled
        // This ensures that elements with custom colors don't show false contrast issues
        if ($cached_results !== false && is_array($cached_results)) {
            $cached_results = $this->apply_color_override_filters($cached_results);
        }
        
        if ($cached_results !== false && is_array($cached_results)) {
            // Apply server-side filtering as additional safety check
            $filtered_results = array_filter($cached_results, function($result) {
                if (!isset($result['selector'])) {
                    return true;
                }
                
                $selector = strtolower($result['selector']);
                $text = isset($result['text']) ? strtolower(trim($result['text'])) : '';
                
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
                
                return true;
            });
            
            return array_values($filtered_results);
        }
        
        return [];
    }

    /**
     * Run JavaScript contrast detector on a page (public method for AJAX)
     */
    public function run_javascript_contrast_detector($url = '') {
        if (empty($url)) {
            $url = home_url();
        }

        // Generate JavaScript code to run the contrast detector
        $js_code = "
            if (typeof ContrastDetector !== 'undefined') {
                const detector = new ContrastDetector();
                const results = detector.detectContrastIssues();
                return results;
            }
            return [];
        ";

        // For now, return empty array - this will be properly implemented
        // when we integrate with a headless browser or browser automation
        return [];
    }

    /**
     * Add contrast issue detected by JavaScript
     */
    private function add_javascript_contrast_issue($js_issue, &$issues) {
        $required_ratio = $js_issue['requiredRatio'];
        $actual_ratio = round($js_issue['contrastRatio'], 2);
        
        $rgb_text = "rgb({$js_issue['textColor']['r']}, {$js_issue['textColor']['g']}, {$js_issue['textColor']['b']})";
        $rgb_bg = "rgb({$js_issue['backgroundColor']['r']}, {$js_issue['backgroundColor']['g']}, {$js_issue['backgroundColor']['b']})";
        
        $description = "Insufficient color contrast detected by accurate browser analysis. Text color: {$rgb_text}, Background color: {$rgb_bg}, Contrast ratio: {$actual_ratio}:1 (required: {$required_ratio}:1)";
        
        $issues[] = [
            'type' => 'low_contrast',
            'severity' => 'medium',
            'element' => $js_issue['selector'],
            'element_details' => [
                'selector' => $js_issue['selector'],
                'text_content' => substr($js_issue['text'], 0, 50),
                'page_url' => $this->current_url ?? $_SERVER['REQUEST_URI'] ?? '/',
            ],
            'message' => "Insufficient color contrast: {$actual_ratio}:1 (required: {$required_ratio}:1)",
            'description' => $description,
            'suggestion' => 'Increase contrast between text and background colors to meet WCAG AA standards.',
            'wcag_reference' => 'WCAG 2.1 Level AA - 1.4.3 Contrast (Minimum)',
            'wcag_criterion' => '1.4.3',
            'wcag_level' => $js_issue['wcagLevel'],
            'compliance_impact' => 'WARNING',
            'how_to_fix' => 'Modify the text color or background color to achieve the required contrast ratio. Use online contrast checkers to verify compliance.',
            'auto_fixable' => false,
            'contrast_ratio' => $actual_ratio,
            'text_color' => $rgb_text,
            'background_color' => $rgb_bg,
            'is_large_text' => $js_issue['isLargeText']
        ];
    }

    /**
     * Apply color override filters to contrast results
     * Remove contrast issues for elements that have color overrides applied
     */
    private function apply_color_override_filters($contrast_results) {
        $settings = get_option('raywp_accessibility_settings', []);
        if (empty($settings['enable_color_overrides'])) {
            return $contrast_results;
        }
        
        $color_overrides = get_option('raywp_accessibility_color_overrides', []);
        if (empty($color_overrides)) {
            return $contrast_results;
        }
        
        // Build a list of selectors that have color overrides
        $override_selectors = [];
        foreach ($color_overrides as $override) {
            if (!empty($override['selector']) && (!empty($override['color']) || !empty($override['background']))) {
                $override_selectors[] = $override['selector'];
            }
        }
        
        error_log('RayWP: Color overrides found: ' . print_r($override_selectors, true));
        
        if (empty($override_selectors)) {
            error_log('RayWP: No color override selectors found, returning original contrast results');
            return $contrast_results;
        }
        
        // Filter out contrast issues for elements that match override selectors
        $filtered_results = array_filter($contrast_results, function($result) use ($override_selectors) {
            if (empty($result['selector'])) {
                return true;
            }
            
            // Check if this element matches any override selector
            foreach ($override_selectors as $override_selector) {
                // Simple matching - in a real implementation, we'd use proper CSS selector matching
                // For now, check if the selectors match exactly or if the override applies to the element
                if ($result['selector'] === $override_selector || 
                    $this->element_matches_selector($result['selector'], $override_selector)) {
                    // This element has a color override, so skip the contrast issue
                    error_log('RayWP: Filtering contrast issue for element "' . $result['selector'] . '" because it matches override "' . $override_selector . '"');
                    return false;
                }
            }
            
            return true;
        });
        
        // Log the filtering results
        $original_count = count($contrast_results);
        $filtered_count = count($filtered_results);
        if ($original_count !== $filtered_count) {
            error_log('RayWP: Contrast filtering applied - Original: ' . $original_count . ', After filtering: ' . $filtered_count);
        }
        
        return $filtered_results;
    }
    
    /**
     * Check if an element selector matches an override selector
     * Enhanced version with better matching for various selector types
     */
    private function element_matches_selector($element_selector, $override_selector) {
        // Normalize selectors for comparison
        $element_selector = strtolower(trim($element_selector));
        $override_selector = strtolower(trim($override_selector));
        
        // Direct match
        if ($element_selector === $override_selector) {
            return true;
        }
        
        // Handle class selectors (e.g., .button)
        if (strpos($override_selector, '.') === 0) {
            $class = substr($override_selector, 1);
            
            // Check various ways a class might appear in selectors
            return strpos($element_selector, '.' . $class) !== false || 
                   strpos($element_selector, 'class="' . $class . '"') !== false ||
                   strpos($element_selector, 'class=\'' . $class . '\'') !== false ||
                   // Check for a.button matching .button
                   preg_match('/^[a-z]+\.' . preg_quote($class, '/') . '/i', $element_selector) ||
                   // Check for button-specific matching
                   ($class === 'button' && (strpos($element_selector, 'a.button') !== false || 
                                          strpos($element_selector, '[class="button"]') !== false));
        }
        
        // Handle element type selectors (e.g., 'a', 'button')
        if (preg_match('/^[a-z]+$/i', $override_selector)) {
            // Check if the element selector starts with the element type
            return strpos($element_selector, $override_selector) === 0 ||
                   strpos($element_selector, $override_selector . '.') === 0 ||
                   strpos($element_selector, $override_selector . '#') === 0 ||
                   strpos($element_selector, $override_selector . '[') === 0;
        }
        
        // Handle ID selectors
        if (strpos($override_selector, '#') === 0) {
            $id = substr($override_selector, 1);
            return strpos($element_selector, '#' . $id) !== false ||
                   strpos($element_selector, 'id="' . $id . '"') !== false;
        }
        
        // Handle compound selectors (e.g., 'a.button', '.posts-callout .button')
        if (strpos($override_selector, '.') !== false || strpos($override_selector, ' ') !== false) {
            // Split and check each part
            if (strpos($override_selector, ' ') !== false) {
                // Space-separated selectors - check if element matches the last part
                $parts = explode(' ', $override_selector);
                $last_part = array_pop($parts);
                return $this->element_matches_selector($element_selector, $last_part);
            } else {
                // Combined selectors like a.button
                if (preg_match('/^([a-z]+)\.([a-z-_]+)$/i', $override_selector, $matches)) {
                    $element_type = $matches[1];
                    $class_name = $matches[2];
                    
                    // Check if element selector matches this pattern
                    return strpos($element_selector, $element_type . '.' . $class_name) !== false ||
                           (strpos($element_selector, $element_type) === 0 && strpos($element_selector, $class_name) !== false);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get pre-calculated contrast results for a URL
     */
    private function get_precalculated_contrast_results($url) {
        // Check if pre-calculation was done for this URL
        $precalc_key = 'raywp_contrast_precalc_' . md5($url);
        $precalc_data = get_transient($precalc_key);
        
        if ($precalc_data === false) {
            return false;
        }
        
        // Check if the override hash matches current overrides
        $current_override_hash = get_option('raywp_accessibility_override_hash', '');
        if (empty($precalc_data['override_hash']) || $precalc_data['override_hash'] !== $current_override_hash) {
            // Override configuration has changed, pre-calculated results are stale
            delete_transient($precalc_key);
            return false;
        }
        
        // Get the actual contrast results (cached after real browser analysis)
        $cache_key = 'raywp_contrast_results_' . md5($url);
        $cached_results = get_transient($cache_key);
        
        if ($cached_results !== false && is_array($cached_results)) {
            // Apply color override filters to the results
            // Always apply color override filters to ensure accuracy
            return $this->apply_color_override_filters($cached_results);
        }
        
        return false;
    }

    /**
     * Fallback contrast detection using original PHP method (conservative)
     */
    private function check_color_contrast_fallback($xpath, &$issues) {
        // Initialize tracking array to prevent duplicate contrast issues for the same element
        $processed_elements = [];
        
        // Only check background images and very explicit contrast issues as fallback
        $this->check_text_over_background_images($xpath, $issues, $processed_elements);
        
        // Check only inline styled elements with explicit colors (conservative)
        $this->check_inline_styled_elements($xpath, $issues, $processed_elements);
    }
    
    /**
     * Generate unique identifier for DOM element to prevent duplicate processing
     */
    private function get_element_id($element) {
        $xpath = new \DOMXPath($element->ownerDocument);
        $position = $xpath->evaluate('count(preceding::*)', $element) + 1;
        return $element->tagName . '_' . $position . '_' . md5($element->textContent);
    }
    
    /**
     * Check elements with inline color/background styles
     */
    private function check_inline_styled_elements($xpath, &$issues, &$processed_elements) {
        $text_elements = $xpath->query('
            //p[@style and text() and normalize-space(text()) != ""] | 
            //h1[@style and text() and normalize-space(text()) != ""] | 
            //h2[@style and text() and normalize-space(text()) != ""] | 
            //h3[@style and text() and normalize-space(text()) != ""] | 
            //h4[@style and text() and normalize-space(text()) != ""] | 
            //h5[@style and text() and normalize-space(text()) != ""] | 
            //h6[@style and text() and normalize-space(text()) != ""] | 
            //a[@style and text() and normalize-space(text()) != ""] |
            //button[@style and text() and normalize-space(text()) != ""] |
            //span[@style and text() and normalize-space(text()) != ""] |
            //div[@style and text() and normalize-space(text()) != "" and not(descendant::*[text()])]
        ');
        
        foreach ($text_elements as $element) {
            $element_id = $this->get_element_id($element);
            if (!isset($processed_elements[$element_id])) {
                $processed_elements[$element_id] = true;
                $this->check_element_contrast($element, $xpath, $issues, 'inline-styled');
            }
        }
    }
    
    /**
     * Check text over background images (improved WAVE-like detection)
     */
    private function check_text_over_background_images($xpath, &$issues, &$processed_elements) {
        // Find all text elements first, then check if they have background images without fallback colors
        $text_elements = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //p | //a | //span | //div[string-length(normalize-space(text())) > 0]');
        
        foreach ($text_elements as $element) {
            $element_id = $this->get_element_id($element);
            if (!isset($processed_elements[$element_id])) {
                $text_content = trim($element->textContent);
                
                // Only check meaningful text content
                if (strlen($text_content) > 5 && !empty(trim($text_content))) {
                    // Only check if element or ancestors have actual background-image in inline styles
                    $has_bg_image = false;
                    $current = $element;
                    $depth = 0;
                    
                    while ($current && $depth < 5) {
                        if ($current->nodeType === XML_ELEMENT_NODE) {
                            $style = $current->getAttribute('style');
                            
                            // Check for background-image in inline styles only
                            if (preg_match('/background-image:\s*url\(/i', $style) || 
                                preg_match('/background:\s*[^;]*url\(/i', $style)) {
                                
                                // Check if there's also a background-color
                                $has_bg_color = false;
                                if (preg_match('/background-color:\s*(?!transparent)[^;]+/i', $style) ||
                                    preg_match('/background:\s*(?!transparent)[^;]*(?:rgb|rgba|#|[a-z]+)(?![^;]*url)/i', $style)) {
                                    $has_bg_color = true;
                                }
                                
                                if (!$has_bg_color) {
                                    $has_bg_image = true;
                                    break;
                                }
                            }
                        }
                        $current = $current->parentNode;
                        $depth++;
                    }
                    
                    if ($has_bg_image) {
                        $processed_elements[$element_id] = true;
                        
                        // This is a real contrast issue: text over background image without fallback color
                        $this->add_contrast_issue(
                            $element, 
                            'unknown (text over image)', 
                            'background image without fallback', 
                            $issues, 
                            'Text over background image requires a fallback background color (WCAG requirement)'
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Comprehensive text contrast analysis - checks ALL text elements like WAVE does
     */
    private function check_all_text_elements($xpath, &$issues, &$processed_elements) {
        // Get ALL text-containing elements (comprehensive like WAVE)
        $all_text_elements = $xpath->query('
            //p[text() and normalize-space(text()) != ""] | 
            //h1[text() and normalize-space(text()) != ""] | 
            //h2[text() and normalize-space(text()) != ""] | 
            //h3[text() and normalize-space(text()) != ""] | 
            //h4[text() and normalize-space(text()) != ""] | 
            //h5[text() and normalize-space(text()) != ""] | 
            //h6[text() and normalize-space(text()) != ""] | 
            //a[text() and normalize-space(text()) != ""] |
            //button[text() and normalize-space(text()) != ""] |
            //span[text() and normalize-space(text()) != ""] |
            //div[text() and normalize-space(text()) != "" and not(descendant::p or descendant::h1 or descendant::h2 or descendant::h3 or descendant::h4 or descendant::h5 or descendant::h6)] |
            //label[text() and normalize-space(text()) != ""] |
            //td[text() and normalize-space(text()) != ""] |
            //th[text() and normalize-space(text()) != ""] |
            //li[text() and normalize-space(text()) != "" and not(descendant::p or descendant::h1 or descendant::h2 or descendant::h3 or descendant::h4 or descendant::h5 or descendant::h6)]
        ');
        
        foreach ($all_text_elements as $element) {
            $element_id = $this->get_element_id($element);
            
            // Skip if already processed
            if (isset($processed_elements[$element_id])) {
                continue;
            }
            
            $text_content = trim($element->textContent);
            
            // Skip very short text (not meaningful for contrast)
            if (strlen($text_content) < 3) {
                continue;
            }
            
            // Skip hidden elements
            $style = $element->getAttribute('style');
            if (preg_match('/display:\s*none|visibility:\s*hidden/i', $style)) {
                continue;
            }
            
            // Mark as processed
            $processed_elements[$element_id] = true;
            
            // Determine context for better defaults
            $tag_name = strtolower($element->tagName);
            $class = $element->getAttribute('class');
            $parent_element = $element->parentNode;
            $parent_tag = $parent_element && $parent_element->nodeType === XML_ELEMENT_NODE ? 
                         strtolower($parent_element->tagName) : '';
            
            $context = 'general';
            if ($parent_tag === 'nav' || preg_match('/\bnav\b/i', $class)) {
                $context = 'navigation-link';
            } elseif ($parent_tag === 'footer' || preg_match('/\bfooter\b/i', $class)) {
                $context = 'footer-text';
            } elseif ($tag_name === 'button' || preg_match('/\b(btn|button)\b/i', $class)) {
                $context = 'button-text';
            } elseif (preg_match('/\b(muted|subtle|secondary|light)\b/i', $class)) {
                $context = 'potentially-low-contrast-class';
            }
            
            // Check contrast for this element
            $this->check_element_contrast($element, $xpath, $issues, $context);
        }
    }
    
    /**
     * Check common contrast scenarios that WAVE would detect
     */
    private function check_common_contrast_scenarios($xpath, &$issues, &$processed_elements) {
        // 1. Check navigation links (often have contrast issues)
        $nav_links = $xpath->query('//nav//a[text() and normalize-space(text()) != ""]');
        foreach ($nav_links as $link) {
            $element_id = $this->get_element_id($link);
            if (!isset($processed_elements[$element_id])) {
                $processed_elements[$element_id] = true;
                $this->check_element_contrast($link, $xpath, $issues, 'navigation-link');
            }
        }
        
        // 2. Check footer text (often gray on light background)
        $footer_text = $xpath->query('//footer//p | //footer//span | //footer//div[text() and not(descendant::*[text()])]');
        foreach ($footer_text as $element) {
            $element_id = $this->get_element_id($element);
            if (!isset($processed_elements[$element_id])) {
                $text_content = trim($element->textContent);
                if (strlen($text_content) > 5) {
                    $processed_elements[$element_id] = true;
                    $this->check_element_contrast($element, $xpath, $issues, 'footer-text');
                }
            }
        }
        
        // 3. Check elements with common low-contrast class names
        $potentially_low_contrast = $xpath->query('//*[
            contains(@class, "text-gray") or 
            contains(@class, "text-muted") or 
            contains(@class, "text-light") or 
            contains(@class, "secondary") or
            contains(@class, "subtitle") or
            contains(@class, "meta")
        ][text() and normalize-space(text()) != ""]');
        
        foreach ($potentially_low_contrast as $element) {
            $element_id = $this->get_element_id($element);
            if (!isset($processed_elements[$element_id])) {
                $processed_elements[$element_id] = true;
                $this->check_element_contrast($element, $xpath, $issues, 'potentially-low-contrast-class');
            }
        }
        
        // 4. Check button text (often has contrast issues)
        $buttons = $xpath->query('//button[text() and normalize-space(text()) != ""] | //input[@type="submit"] | //input[@type="button"][@value]');
        foreach ($buttons as $button) {
            $element_id = $this->get_element_id($button);
            if (!isset($processed_elements[$element_id])) {
                $processed_elements[$element_id] = true;
                $this->check_element_contrast($button, $xpath, $issues, 'button-text');
            }
        }
    }
    
    /**
     * Check individual element contrast with better defaults
     */
    private function check_element_contrast($element, $xpath, &$issues, $context = 'general') {
        $text_content = trim($element->textContent);
        
        // Skip very short text
        if (strlen($text_content) < 3) {
            return;
        }
        
        // Skip if element is hidden
        $style = $element->getAttribute('style');
        if (preg_match('/display:\s*none|visibility:\s*hidden/i', $style)) {
            return;
        }
        
        // Get text and background colors with context-aware defaults
        $text_color = $this->get_text_color_enhanced($element, $context);
        $bg_color = $this->get_background_color_enhanced($element, $xpath, $context);
        
        // If we can't determine either color, skip the check
        if ($text_color === null || $bg_color === null) {
            // Don't flag as an issue if we simply can't determine the colors
            return;
        }
        
        // Calculate contrast ratio for solid backgrounds
        $contrast_ratio = $this->calculate_contrast_ratio($text_color, $bg_color);
        
        if ($contrast_ratio === null) {
            return; // Skip if we can't calculate
        }
        
        // Check if it meets WCAG standards
        $is_large = $this->is_large_text($element);
        if (!$this->meets_wcag_contrast($contrast_ratio, $is_large)) {
            $required_ratio = $is_large ? '3:1' : '4.5:1';
            $actual_ratio = round($contrast_ratio, 2) . ':1';
            
            $this->add_contrast_issue(
                $element, 
                $text_color, 
                $bg_color, 
                $issues, 
                "Insufficient color contrast: {$actual_ratio} (required: {$required_ratio})"
            );
        }
    }
    
    // REMOVED: check_element_contrast_from_style - was too aggressive
    
    // REMOVED: check_common_contrast_patterns - was too aggressive
    // REMOVED: check_text_elements_contrast - was too aggressive
    
    /**
     * Calculate relative luminance for WCAG contrast ratio
     * Based on WCAG 2.1 specification
     */
    private function calculate_relative_luminance($color) {
        $rgb = $this->parse_color_to_rgb($color);
        if (!$rgb) {
            return null;
        }
        
        // Normalize RGB values to 0-1 range
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;
        
        // Apply gamma correction as per WCAG formula
        $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        // Calculate relative luminance
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
    
    /**
     * Calculate contrast ratio between two colors using WCAG formula
     */
    private function calculate_contrast_ratio($color1, $color2) {
        $l1 = $this->calculate_relative_luminance($color1);
        $l2 = $this->calculate_relative_luminance($color2);
        
        if ($l1 === null || $l2 === null) {
            return null;
        }
        
        // Ensure L1 is the lighter color
        if ($l1 < $l2) {
            $temp = $l1;
            $l1 = $l2;
            $l2 = $temp;
        }
        
        // WCAG contrast ratio formula
        return ($l1 + 0.05) / ($l2 + 0.05);
    }
    
    /**
     * Check if contrast ratio meets WCAG AA standards
     */
    private function meets_wcag_contrast($contrast_ratio, $is_large_text = false) {
        if ($contrast_ratio === null) {
            return true; // Can't determine, assume it's okay
        }
        
        // WCAG AA requirements
        $required_ratio = $is_large_text ? 3.0 : 4.5;
        return $contrast_ratio >= $required_ratio;
    }
    
    /**
     * Parse color string to RGB values
     * Handles hex, RGB, RGBA, HSL, and named colors
     */
    private function parse_color_to_rgb($color) {
        if (!$color || $color === 'transparent') {
            return null;
        }
        
        $color = strtolower(trim($color));
        
        // Handle hex colors (#fff, #ffffff)
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            $hex = substr($color, 1);
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            return [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2))
            ];
        }
        
        // Handle rgb() and rgba() colors
        if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d.]+)?\s*\)/i', $color, $matches)) {
            return [
                'r' => intval($matches[1]),
                'g' => intval($matches[2]),
                'b' => intval($matches[3])
            ];
        }
        
        // Handle HSL colors - convert to RGB
        if (preg_match('/hsla?\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%(?:\s*,\s*[\d.]+)?\s*\)/i', $color, $matches)) {
            $h = intval($matches[1]) / 360;
            $s = intval($matches[2]) / 100;
            $l = intval($matches[3]) / 100;
            
            return $this->hsl_to_rgb($h, $s, $l);
        }
        
        // Handle named colors
        $named_colors = [
            'white' => ['r' => 255, 'g' => 255, 'b' => 255],
            'black' => ['r' => 0, 'g' => 0, 'b' => 0],
            'red' => ['r' => 255, 'g' => 0, 'b' => 0],
            'green' => ['r' => 0, 'g' => 128, 'b' => 0],
            'blue' => ['r' => 0, 'g' => 0, 'b' => 255],
            'gray' => ['r' => 128, 'g' => 128, 'b' => 128],
            'grey' => ['r' => 128, 'g' => 128, 'b' => 128],
            'yellow' => ['r' => 255, 'g' => 255, 'b' => 0],
            'cyan' => ['r' => 0, 'g' => 255, 'b' => 255],
            'magenta' => ['r' => 255, 'g' => 0, 'b' => 255],
            'silver' => ['r' => 192, 'g' => 192, 'b' => 192],
            'maroon' => ['r' => 128, 'g' => 0, 'b' => 0],
            'olive' => ['r' => 128, 'g' => 128, 'b' => 0],
            'lime' => ['r' => 0, 'g' => 255, 'b' => 0],
            'aqua' => ['r' => 0, 'g' => 255, 'b' => 255],
            'teal' => ['r' => 0, 'g' => 128, 'b' => 128],
            'navy' => ['r' => 0, 'g' => 0, 'b' => 128],
            'fuchsia' => ['r' => 255, 'g' => 0, 'b' => 255],
            'purple' => ['r' => 128, 'g' => 0, 'b' => 128]
        ];
        
        if (isset($named_colors[$color])) {
            return $named_colors[$color];
        }
        
        return null; // Unknown format
    }
    
    /**
     * Convert HSL to RGB
     */
    private function hsl_to_rgb($h, $s, $l) {
        if ($s == 0) {
            $r = $g = $b = $l; // Achromatic
        } else {
            $hue2rgb = function($p, $q, $t) {
                if ($t < 0) $t += 1;
                if ($t > 1) $t -= 1;
                if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
                if ($t < 1/2) return $q;
                if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
                return $p;
            };
            
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $hue2rgb($p, $q, $h + 1/3);
            $g = $hue2rgb($p, $q, $h);
            $b = $hue2rgb($p, $q, $h - 1/3);
        }
        
        return [
            'r' => round($r * 255),
            'g' => round($g * 255),
            'b' => round($b * 255)
        ];
    }
    
    /**
     * Extract text color from element with CSS inheritance - Enhanced for comprehensive detection
     */
    private function get_text_color_enhanced($element, $context = 'general') {
        $current = $element;
        $depth = 0;
        
        // Check element and ancestors for text color (CSS inheritance)
        while ($current && $depth < 6) { // Check up to 6 levels for color inheritance
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $style = $current->getAttribute('style');
                $class = $current->getAttribute('class');
                $tag = strtolower($current->tagName);
                
                // 1. Check inline styles first (highest priority)
                $text_color = $this->extract_text_color_from_style($style);
                if ($text_color !== null) {
                    return $text_color;
                }
                
                // 2. Only check for very specific, well-defined text color classes
                // Limited to Bootstrap's explicit text color utilities
                if (preg_match('/\btext-(primary|secondary|success|danger|warning|info|light|dark|white|black|muted)\b/', $class, $matches)) {
                    $bootstrap_colors = [
                        'primary' => '#007bff',
                        'secondary' => '#6c757d',
                        'success' => '#28a745',
                        'danger' => '#dc3545',
                        'warning' => '#ffc107',
                        'info' => '#17a2b8',
                        'light' => '#f8f9fa',
                        'dark' => '#343a40',
                        'white' => '#ffffff',
                        'black' => '#000000',
                        'muted' => '#6c757d'
                    ];
                    if (isset($bootstrap_colors[$matches[1]])) {
                        return $bootstrap_colors[$matches[1]];
                    }
                }
                
                // 3. Check for semantic elements with known text color patterns
                $text_color = $this->get_semantic_text_color($tag, $context);
                if ($text_color !== null) {
                    return $text_color;
                }
            }
            
            $current = $current->parentNode;
            $depth++;
        }
        
        // Context-aware text color defaults (fallback)
        return $this->get_context_default_text_color($context);
    }
    
    /**
     * Extract text color from inline style attribute
     */
    private function extract_text_color_from_style($style) {
        if (empty($style)) {
            return null;
        }
        
        // Check for color property
        if (preg_match('/color:\s*([^;]+)/i', $style, $matches)) {
            $text_color = trim($matches[1]);
            if ($text_color !== 'transparent' && $text_color !== 'inherit' && $text_color !== 'initial') {
                return $this->normalize_color($text_color);
            }
        }
        
        return null;
    }
    
    /**
     * Guess text color from CSS classes (common patterns)
     */
    private function guess_text_color_from_classes($class, $tag, $context) {
        if (empty($class)) {
            return null;
        }
        
        // Common text color class patterns
        $text_patterns = [
            // Bootstrap text colors
            '/\btext-primary\b/i' => '#007bff',
            '/\btext-secondary\b/i' => '#6c757d',
            '/\btext-success\b/i' => '#28a745',
            '/\btext-danger\b/i' => '#dc3545',
            '/\btext-warning\b/i' => '#ffc107',
            '/\btext-info\b/i' => '#17a2b8',
            '/\btext-light\b/i' => '#f8f9fa',
            '/\btext-dark\b/i' => '#343a40',
            '/\btext-white\b/i' => '#ffffff',
            '/\btext-black\b/i' => '#000000',
            '/\btext-muted\b/i' => '#6c757d',
            
            // Common theme patterns
            '/\btext-blue\b/i' => '#0066cc',
            '/\btext-red\b/i' => '#cc0000',
            '/\btext-green\b/i' => '#00cc00',
            '/\btext-gray\b/i' => '#808080',
            '/\btext-grey\b/i' => '#808080',
            
            // Common low-contrast patterns (problematic)
            '/\b(muted|subtle|light|faded)\b/i' => '#999999',
            '/\b(disabled|inactive)\b/i' => '#cccccc',
            
            // Button text colors
            '/\bbtn-outline-/i' => '#007bff', // Outline buttons typically have colored text
        ];
        
        foreach ($text_patterns as $pattern => $color) {
            if (preg_match($pattern, $class)) {
                return $color;
            }
        }
        
        return null;
    }
    
    /**
     * Get text color for semantic elements
     */
    private function get_semantic_text_color($tag, $context) {
        // Common semantic element text colors
        switch ($tag) {
            case 'a':
                return '#0066cc'; // Default link color (blue)
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                return '#000000'; // Headings typically black
            case 'button':
                return '#ffffff'; // Button text often white
            case 'code':
            case 'pre':
                return '#d63384'; // Code text often colored
        }
        
        return null;
    }
    
    /**
     * Get context-aware default text colors
     */
    private function get_context_default_text_color($context) {
        // Return null to indicate we don't know the actual color
        // This prevents false positives from assuming colors
        return null;
    }
    
    /**
     * Check if element or its ancestors have background image without solid background color
     */
    private function has_background_image_without_color($element) {
        $current = $element;
        $depth = 0;
        
        while ($current && $depth < 5) {
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $style = $current->getAttribute('style');
                $class = $current->getAttribute('class');
                
                // Check for background image
                $has_bg_image = false;
                if (preg_match('/background-image:\s*url\(/i', $style)) {
                    $has_bg_image = true;
                }
                
                // Check for background shorthand with image
                if (preg_match('/background:\s*[^;]*url\(/i', $style)) {
                    $has_bg_image = true;
                }
                
                // Only check actual style attributes for background images, not class names
                // This prevents false positives from assuming classes like 'hero' or 'banner' have images
                
                if ($has_bg_image) {
                    // Now check if there's also a solid background color
                    $has_bg_color = false;
                    
                    // Check for explicit background-color
                    if (preg_match('/background-color:\s*([^;]+)/i', $style, $matches)) {
                        $bg_color = trim($matches[1]);
                        if ($bg_color !== 'transparent' && $bg_color !== 'inherit' && $bg_color !== '') {
                            $has_bg_color = true;
                        }
                    }
                    
                    // Check for background shorthand with color
                    if (preg_match('/background:\s*([^;]+)/i', $style, $matches)) {
                        $bg_value = trim($matches[1]);
                        // Look for color in background shorthand (exclude just 'url(...)' or 'none')
                        if (preg_match('/(#[0-9a-f]{3,6}|rgba?\([^)]+\)|hsla?\([^)]+\)|(?:white|black|red|blue|green|yellow|orange|purple|gray|grey)(?!\w))/i', $bg_value, $color_matches)) {
                            $bg_color = $color_matches[1];
                            if ($bg_color !== 'transparent' && $bg_color !== 'inherit') {
                                $has_bg_color = true;
                            }
                        }
                    }
                    
                    // Background image found but no solid color = potential contrast issue
                    return !$has_bg_color;
                }
            }
            
            $current = $current->parentNode;
            $depth++;
        }
        
        return false;
    }
    
    /**
     * Extract background color from element and ancestors - Enhanced for comprehensive CSS detection
     */
    private function get_background_color_enhanced($element, $xpath, $context = 'general') {
        $current = $element;
        $depth = 0;
        
        // Check element and ancestors for background color
        while ($current && $depth < 8) { // Increased depth for better traversal
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $style = $current->getAttribute('style');
                $class = $current->getAttribute('class');
                $tag = strtolower($current->tagName);
                
                // 1. Check inline styles first (highest priority)
                $bg_color = $this->extract_background_color_from_style($style);
                if ($bg_color !== null) {
                    return $bg_color;
                }
                
                // 2. Only check for very specific, well-defined background color classes
                // Limited to Bootstrap's explicit background color utilities
                if (preg_match('/\bbg-(primary|secondary|success|danger|warning|info|light|dark|white|black)\b/', $class, $matches)) {
                    $bootstrap_colors = [
                        'primary' => '#007bff',
                        'secondary' => '#6c757d',
                        'success' => '#28a745',
                        'danger' => '#dc3545',
                        'warning' => '#ffc107',
                        'info' => '#17a2b8',
                        'light' => '#f8f9fa',
                        'dark' => '#343a40',
                        'white' => '#ffffff',
                        'black' => '#000000'
                    ];
                    if (isset($bootstrap_colors[$matches[1]])) {
                        return $bootstrap_colors[$matches[1]];
                    }
                }
                
                // 3. Check for background images without fallback colors (problematic case)
                if ($this->has_background_image_in_style($style) || $this->has_background_image_in_classes($class)) {
                    // If background image exists without solid color, return null to indicate problematic case
                    if (!$this->has_background_color_in_style($style)) {
                        return null;
                    }
                }
                
                // 4. Check for semantic elements with known background patterns
                $bg_color = $this->get_semantic_background($tag, $context);
                if ($bg_color !== null) {
                    return $bg_color;
                }
            }
            
            $current = $current->parentNode;
            $depth++;
        }
        
        // Context-aware background defaults (fallback)
        return $this->get_context_default_background($context);
    }
    
    /**
     * Extract background color from inline style attribute
     */
    private function extract_background_color_from_style($style) {
        if (empty($style)) {
            return null;
        }
        
        // Check for background-color
        if (preg_match('/background-color:\s*([^;]+)/i', $style, $matches)) {
            $bg_color = trim($matches[1]);
            if ($bg_color !== 'transparent' && $bg_color !== 'inherit' && $bg_color !== 'initial') {
                return $this->normalize_color($bg_color);
            }
        }
        
        // Check for background shorthand property
        if (preg_match('/background:\s*([^;]+)/i', $style, $matches)) {
            $bg_value = trim($matches[1]);
            // Extract color from background shorthand
            if (preg_match('/(#[0-9a-f]{3,6}|rgba?\([^)]+\)|hsla?\([^)]+\)|(?:white|black|red|blue|green|yellow|orange|purple|gray|grey|darkgray|lightgray|navy|maroon|olive|lime|aqua|teal|silver|fuchsia)(?!\w))/i', $bg_value, $color_matches)) {
                $bg_color = $color_matches[1];
                if ($bg_color !== 'transparent' && $bg_color !== 'inherit' && $bg_color !== 'initial') {
                    return $this->normalize_color($bg_color);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Guess background color from CSS classes (common patterns)
     */
    private function guess_background_from_classes($class, $tag, $context) {
        if (empty($class)) {
            return null;
        }
        
        // Common background class patterns
        $bg_patterns = [
            // Bootstrap classes
            '/\bbg-primary\b/i' => '#007bff',
            '/\bbg-secondary\b/i' => '#6c757d', 
            '/\bbg-success\b/i' => '#28a745',
            '/\bbg-danger\b/i' => '#dc3545',
            '/\bbg-warning\b/i' => '#ffc107',
            '/\bbg-info\b/i' => '#17a2b8',
            '/\bbg-light\b/i' => '#f8f9fa',
            '/\bbg-dark\b/i' => '#343a40',
            '/\bbg-white\b/i' => '#ffffff',
            '/\bbg-black\b/i' => '#000000',
            
            // Common theme patterns
            '/\bbg-blue\b/i' => '#0066cc',
            '/\bbg-red\b/i' => '#cc0000',
            '/\bbg-green\b/i' => '#00cc00',
            '/\bbg-gray\b/i' => '#808080',
            '/\bbg-grey\b/i' => '#808080',
            
            // Button classes (when context is button)
            '/\bbtn-primary\b/i' => '#007bff',
            '/\bbtn-secondary\b/i' => '#6c757d',
            '/\bbtn-success\b/i' => '#28a745',
            '/\bbtn-danger\b/i' => '#dc3545',
            '/\bbtn-warning\b/i' => '#ffc107',
            '/\bbtn-info\b/i' => '#17a2b8',
            '/\bbtn-light\b/i' => '#f8f9fa',
            '/\bbtn-dark\b/i' => '#343a40',
        ];
        
        foreach ($bg_patterns as $pattern => $color) {
            if (preg_match($pattern, $class)) {
                return $color;
            }
        }
        
        return null;
    }
    
    /**
     * Check if element has background image in style
     */
    private function has_background_image_in_style($style) {
        return !empty($style) && (
            preg_match('/background-image:\s*url\(/i', $style) || 
            preg_match('/background:\s*[^;]*url\(/i', $style)
        );
    }
    
    /**
     * Check if element has background image based on classes
     */
    private function has_background_image_in_classes($class) {
        return !empty($class) && preg_match('/\b(hero|banner|bg-image|background|cover|jumbotron)\b/i', $class);
    }
    
    /**
     * Check if element has background color in style
     */
    private function has_background_color_in_style($style) {
        if (empty($style)) {
            return false;
        }
        
        return (preg_match('/background-color:\s*([^;]+)/i', $style) || 
                preg_match('/background:\s*(?:[^;]*(?:#[0-9a-f]{3,6}|rgba?\([^)]+\)|hsla?\([^)]+\)|(?:white|black|red|blue|green|yellow|orange|purple|gray|grey)(?!\w)))/i', $style));
    }
    
    /**
     * Get background color for semantic elements
     */
    private function get_semantic_background($tag, $context) {
        // Common semantic element backgrounds
        switch ($tag) {
            case 'body':
                return '#ffffff'; // Default body background
            case 'header':
            case 'nav':
                return '#f8f9fa'; // Light header/nav background
            case 'footer':
                return '#343a40'; // Dark footer background
            case 'main':
            case 'article':
            case 'section':
                return '#ffffff'; // Content area background
        }
        
        return null;
    }
    
    /**
     * Get context-aware default backgrounds
     */
    private function get_context_default_background($context) {
        // Return null to indicate we don't know the actual color
        // This prevents false positives from assuming colors
        return null;
    }
    
    /**
     * Normalize color values to consistent format
     */
    private function normalize_color($color) {
        $color = trim(strtolower($color));
        
        // Convert named colors to hex
        $named_colors = [
            'white' => '#ffffff',
            'black' => '#000000',
            'red' => '#ff0000',
            'green' => '#008000',
            'blue' => '#0000ff',
            'yellow' => '#ffff00',
            'orange' => '#ffa500',
            'purple' => '#800080',
            'gray' => '#808080',
            'grey' => '#808080',
            'darkgray' => '#a9a9a9',
            'lightgray' => '#d3d3d3',
            'navy' => '#000080',
            'maroon' => '#800000',
            'olive' => '#808000',
            'lime' => '#00ff00',
            'aqua' => '#00ffff',
            'teal' => '#008080',
            'silver' => '#c0c0c0',
            'fuchsia' => '#ff00ff'
        ];
        
        if (isset($named_colors[$color])) {
            return $named_colors[$color];
        }
        
        return $color; // Return as-is for hex, rgb, rgba, hsl, hsla
    }
    
    /**
     * Legacy functions for backward compatibility
     */
    private function get_text_color($element) {
        return $this->get_text_color_enhanced($element, 'general');
    }
    
    private function get_background_color($element, $xpath) {
        return $this->get_background_color_enhanced($element, $xpath, 'general');
    }
    
    /**
     * Determine if text is considered large (18pt or 14pt bold)
     */
    private function is_large_text($element) {
        $tag_name = strtolower($element->tagName);
        $style = $element->getAttribute('style');
        
        // Check if it's a heading (considered large)
        if (in_array($tag_name, ['h1', 'h2', 'h3'])) {
            return true;
        }
        
        // Check font-size in style attribute
        if (preg_match('/font-size:\s*(\d+)(px|pt|em|rem)/i', $style, $matches)) {
            $size = intval($matches[1]);
            $unit = strtolower($matches[2]);
            
            switch ($unit) {
                case 'pt':
                    return $size >= 18;
                case 'px':
                    return $size >= 24; // 18pt ≈ 24px
                case 'em':
                case 'rem':
                    return $size >= 1.5; // 1.5em ≈ 18pt
            }
        }
        
        // Check font-weight for bold text
        $is_bold = false;
        if (preg_match('/font-weight:\s*(bold|[6-9]\d\d)/i', $style) || $tag_name === 'b' || $tag_name === 'strong') {
            $is_bold = true;
        }
        
        // For bold text, 14pt (≈19px) is considered large
        if ($is_bold && preg_match('/font-size:\s*(\d+)(px|pt)/i', $style, $matches)) {
            $size = intval($matches[1]);
            $unit = strtolower($matches[2]);
            
            if ($unit === 'pt') {
                return $size >= 14;
            } else if ($unit === 'px') {
                return $size >= 19; // 14pt ≈ 19px
            }
        }
        
        return false;
    }
    
    /**
     * Add a contrast issue to the issues array
     */
    private function add_contrast_issue($element, $text_color, $bg_color, &$issues, $custom_message = null) {
        $details = $this->get_element_details($element, 'contrast');
        $message = $custom_message ?: 'Potential low color contrast detected';

        // Convert colors to hex for better readability
        $text_hex = $this->color_to_hex($text_color);
        $bg_hex = $this->color_to_hex($bg_color);

        // Determine more specific description and fix instructions based on issue type
        if ($bg_color === 'background image without fallback') {
            $description = "This text appears over a background image without a fallback background color (text: {$text_hex}). WCAG requires a solid background color for text over images.";
            $how_to_fix = 'Add a background-color CSS property to provide fallback contrast. Example: background: url(image.jpg); background-color: #000000; or use a semi-transparent background overlay.';
            $suggestion = 'Provide a solid background color as fallback when using background images with text overlay.';
            $contrast_info = "Text: {$text_hex} on background image";
        } else if (strpos($bg_color, 'image') !== false) {
            $description = "This text may have insufficient contrast over a background image (text: {$text_hex}, background: {$bg_hex}).";
            $how_to_fix = 'Ensure adequate contrast by adding a background color overlay or text shadow. Test with a color contrast checker.';
            $suggestion = 'Add a background color overlay or increase text contrast when displaying text over images.';
            $contrast_info = "Text: {$text_hex} on {$bg_hex}";
        } else {
            $description = "This text has insufficient color contrast (text: {$text_hex}, background: {$bg_hex}).";
            $how_to_fix = 'Modify the text color or background color to achieve the required contrast ratio. Use online contrast checkers to verify compliance.';
            $suggestion = 'Ensure color contrast ratio meets WCAG AA standards (4.5:1 for normal text, 3:1 for large text).';
            $contrast_info = "Text: {$text_hex} on {$bg_hex}";
        }

        // Build enhanced HTML snippet with contrast details
        $enhanced_snippet = $details['html_snippet'];
        if (!empty($contrast_info)) {
            $enhanced_snippet = "[Contrast: {$contrast_info}] " . $enhanced_snippet;
        }

        // Override element_details with enhanced snippet
        $details['html_snippet'] = $enhanced_snippet;
        $details['contrast_info'] = $contrast_info;
        $details['text_color'] = $text_hex;
        $details['bg_color'] = $bg_hex;

        $issues[] = [
            'type' => 'low_contrast',
            'severity' => 'medium',
            'element' => $details['selector'],
            'element_details' => $details,
            'message' => $message,
            'description' => $description,
            'suggestion' => $suggestion,
            'wcag_reference' => 'WCAG 2.1 Level AA - 1.4.3 Contrast (Minimum)',
            'wcag_criterion' => '1.4.3',
            'wcag_level' => 'AA',
            'compliance_impact' => 'WARNING',
            'how_to_fix' => $how_to_fix,
            'auto_fixable' => false,
            'contrast_details' => [
                'text_color' => $text_hex,
                'bg_color' => $bg_hex,
                'info' => $contrast_info
            ]
        ];
    }

    /**
     * Convert a color value to hex format for readability
     */
    private function color_to_hex($color) {
        if (empty($color) || strpos($color, 'image') !== false || strpos($color, 'unknown') !== false) {
            return $color; // Return as-is for non-color values
        }

        // If already hex, return as-is
        if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $color)) {
            return strtoupper($color);
        }

        // Parse rgb/rgba format
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $color, $matches)) {
            $r = intval($matches[1]);
            $g = intval($matches[2]);
            $b = intval($matches[3]);
            return sprintf('#%02X%02X%02X', $r, $g, $b);
        }

        return $color; // Return original if can't parse
    }
    
    /**
     * Generate report
     */
    public function generate_report($url = '', $apply_fixes = false) {
        error_log('RayWP Accessibility: generate_report called for URL: ' . $url);
        
        // Store whether we're applying fixes for use in contrast detection
        $this->apply_fixes = $apply_fixes;
        
        if (empty($url)) {
            $url = home_url();
        }
        
        // Skip scanning admin pages
        if (strpos($url, '/wp-admin/') !== false || 
            strpos($url, '/wp-includes/') !== false ||
            strpos($url, '/wp-content/') !== false) {
            return [
                'error' => 'Cannot scan admin or system pages',
                'url' => $url,
                'issues' => []
            ];
        }
        
        error_log('RayWP Accessibility: Processing URL: ' . $url);
        
        // Fetch page content with SSL verification disabled for local development
        $headers = [
            'User-Agent' => 'RayWP Accessibility Scanner'
        ];
        
        // Only bypass fixes if we're scanning without fixes
        if (!$apply_fixes) {
            $headers['X-RayWP-Scanner'] = '1'; // Signal to bypass fixes during scan
        } else {
        }
        
        // Add cache-busting parameter to ensure fresh content
        $cache_bust = $apply_fixes ? 'with_fixes_' . time() : 'without_fixes_' . time();
        $url_with_timestamp = add_query_arg('raywp_cache_bust', $cache_bust, $url);
        
        // Always add parameter to trigger contrast detection on all scans
        // This ensures we catch all contrast issues regardless of fix status
        $url_with_timestamp = add_query_arg('raywp_scan_contrast', '1', $url_with_timestamp);
        
        $args = [
            'timeout' => 60, // Increased timeout for slow pages
            'sslverify' => false, // Disable SSL verification for local sites
            'headers' => $headers
        ];
        
        $response = wp_remote_get($url_with_timestamp, $args);
        
        if (is_wp_error($response)) {
            $error_message = 'Failed to fetch page: ' . $response->get_error_message();
            return ['error' => $error_message];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = 'HTTP ' . $response_code . ' error for ' . $url;
            return ['error' => $error_message];
        }
        
        $content = wp_remote_retrieve_body($response);
        $issues = $this->check_content($content, $url);
        
        // Save scan results to database if we have a Reports instance
        $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
        $reports = $plugin->get_component('reports');
        
        $scan_data = [
            'url' => $url,
            'issues' => $issues,
            'total_issues' => count($issues),
            'timestamp' => current_time('mysql')
        ];
        
        if ($reports && count($issues) > 0) {
            $save_result = $reports->save_scan_results($scan_data);
            $scan_data['session_id'] = $save_result['session_id'];
            $scan_data['saved_count'] = $save_result['inserted_count'];
        }
        
        return $scan_data;
    }
    
    /**
     * Check keyboard accessibility with advanced detection
     */
    private function check_keyboard_accessibility($xpath, &$issues) {
        // Check for interactive elements without proper keyboard access
        $this->check_interactive_elements($xpath, $issues);
        
        // Check for keyboard traps
        $this->check_keyboard_traps($xpath, $issues);
        
        // Check tab order issues
        $this->check_tab_order($xpath, $issues);
        
        // Check for missing skip links
        $this->check_skip_links($xpath, $issues);
        
        // Check focus indicators
        $this->check_focus_indicators($xpath, $issues);
    }
    
    /**
     * Check for interactive elements without keyboard access
     */
    private function check_interactive_elements($xpath, &$issues) {
        $interactive_elements = $xpath->query('//div[@onclick] | //span[@onclick] | //img[@onclick]');
        foreach ($interactive_elements as $element) {
            if (!$element->hasAttribute('tabindex') && !$element->hasAttribute('role')) {
                $details = $this->get_element_details($element);
                
                $issues[] = [
                    'type' => 'keyboard_inaccessible',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Interactive element not keyboard accessible',
                    'description' => "This {$element->tagName} element has click functionality but cannot be accessed via keyboard, making it unusable for keyboard-only users.",
                    'suggestion' => 'Add tabindex="0" and appropriate keyboard event handlers, or use a proper button/link element instead.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 2.1.1 Keyboard',
                    'wcag_criterion' => '2.1.1',
                    'wcag_level' => 'A',
                    'compliance_impact' => 'VIOLATION',
                    'how_to_fix' => 'Replace with <button> or add: tabindex="0" and onkeydown="if(event.key===\'Enter\'||event.key===\' \'){/* action */}"',
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Check for potential keyboard traps
     */
    private function check_keyboard_traps($xpath, &$issues) {
        // Look for elements that might trap keyboard focus
        $potential_traps = $xpath->query('//*[@tabindex="-1" and (@onclick or @onkeydown)]');
        foreach ($potential_traps as $element) {
            $details = $this->get_element_details($element);
            $issues[] = [
                'type' => 'potential_keyboard_trap',
                'severity' => 'medium',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Potential keyboard trap detected',
                'description' => 'This element has tabindex="-1" with event handlers, which might trap keyboard focus.',
                'suggestion' => 'Ensure keyboard users can navigate away from this element using Tab, Shift+Tab, or Escape.',
                'wcag_reference' => 'WCAG 2.1 Level A - 2.1.2 No Keyboard Trap',
                'wcag_criterion' => '2.1.2',
                'wcag_level' => 'A',
                'compliance_impact' => 'WARNING',
                'how_to_fix' => 'Add proper keyboard event handlers to allow users to escape focus.',
                'auto_fixable' => false
            ];
        }
        
        // Check for modals without proper focus management
        $modals = $xpath->query('//*[contains(@class, "modal") or contains(@class, "dialog") or contains(@class, "fancybox") or contains(@class, "lightbox") or @role="dialog"]');
        foreach ($modals as $modal) {
            // Enhanced close button detection for modern modal libraries
            $close_button_patterns = [
                // Traditional close button patterns
                './/*[contains(@class, "close")]',
                './/*[contains(text(), "close")]', 
                './/*[contains(@aria-label, "close")]',
                
                // Common button patterns with close functionality
                './/button[contains(@class, "btn") and (contains(@class, "close") or contains(text(), "×") or contains(@aria-label, "close"))]',
                './/a[contains(@class, "close") or contains(text(), "×") or @title="close" or @title="Close"]',
                
                // Modal content area close buttons (check parent of modal-content for close buttons)
                '..//*[contains(@class, "close")]',
                '../..//*[contains(@class, "close")]',
                
                // Fancybox patterns
                './/*[contains(@class, "fancybox-close")]',
                './/*[contains(@class, "fancybox-button--close")]',
                './/*[@data-fancybox-close]',
                
                // Bootstrap modal patterns
                './/*[@data-dismiss="modal"]',
                './/*[@data-bs-dismiss="modal"]',
                
                // Generic data attribute patterns
                './/*[@data-close]',
                './/*[@data-dismiss]',
                
                // Common modal framework patterns
                './/*[contains(@class, "modal-close")]',
                './/*[contains(@class, "dialog-close")]',
                './/*[contains(@class, "overlay-close")]',
                
                // ARIA and icon-based patterns (case insensitive)
                './/*[contains(translate(@aria-label, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "close")]',
                './/*[contains(translate(@aria-label, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "dismiss")]',
                './/*[contains(translate(@title, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "close")]',
                
                // Unicode close symbols and common close text
                './/*[contains(text(), "×")]',
                './/*[contains(text(), "✕")]',
                './/*[contains(text(), "⨯")]',
                './/*[contains(text(), "Close")]',
                './/*[contains(text(), "CLOSE")]'
            ];
            
            $close_buttons_found = false;
            foreach ($close_button_patterns as $pattern) {
                $close_buttons = $xpath->query($pattern, $modal);
                if ($close_buttons->length > 0) {
                    $close_buttons_found = true;
                    break;
                }
            }
            
            if (!$close_buttons_found) {
                $details = $this->get_element_details($modal);
                
                // Calculate confidence score based on modal characteristics
                $confidence = $this->calculate_modal_confidence($modal, $details);
                
                // Only report high-confidence issues to reduce false positives
                if ($confidence >= 0.7) {
                    $issues[] = [
                        'type' => 'modal_no_close_button',
                        'severity' => 'high',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => 'Modal/dialog missing keyboard-accessible close method',
                        'description' => 'This modal or dialog lacks a clear way for keyboard users to close it, potentially trapping focus.',
                        'suggestion' => 'Add a visible close button or ensure Escape key closes the modal.',
                        'wcag_reference' => 'WCAG 2.1 Level A - 2.1.2 No Keyboard Trap',
                        'wcag_criterion' => '2.1.2',
                        'wcag_level' => 'A',
                        'compliance_impact' => 'VIOLATION',
                        'how_to_fix' => 'Add a close button with proper keyboard handling and aria-label.',
                        'auto_fixable' => false,
                        'confidence' => $confidence // Add confidence score for future analysis
                    ];
                }
            }
        }
    }
    
    /**
     * Calculate confidence score for modal close button detection
     * Helps reduce false positives by analyzing modal characteristics
     */
    private function calculate_modal_confidence($modal, $details) {
        $confidence = 0.8; // Start with reduced confidence to be more conservative
        
        // Check for known problematic patterns that often have false positives
        $class_attr = $modal->getAttribute('class');
        $id_attr = $modal->getAttribute('id');
        
        // Be more strict with common false positive patterns
        
        // Reduce confidence for fancybox modals (often have external close buttons)
        if (strpos($class_attr, 'fancybox') !== false) {
            $confidence -= 0.5; // Increase reduction from 0.4 to 0.5
        }
        
        // Reduce confidence for lightbox modals
        if (strpos($class_attr, 'lightbox') !== false) {
            $confidence -= 0.4; // Increase reduction from 0.3 to 0.4
        }
        
        // Heavily penalize modals with "content" in class name (often wrapped/inner elements)
        if (strpos($class_attr, 'modal-content') !== false || strpos($class_attr, 'content') !== false) {
            $confidence -= 0.4; // Increase reduction from 0.2 to 0.4
        }
        
        // Reduce confidence for modals that seem to be components or cards (common false positives)
        if (strpos($class_attr, 'card') !== false || strpos($class_attr, 'bio') !== false || 
            strpos($class_attr, 'profile') !== false || strpos($class_attr, 'item') !== false) {
            $confidence -= 0.5;
        }
        
        // Reduce confidence for very small modals (likely not true modals)
        $style = $modal->getAttribute('style');
        if (preg_match('/width\s*:\s*[0-9]+px/i', $style) && preg_match('/height\s*:\s*[0-9]+px/i', $style)) {
            $confidence -= 0.3;
        }
        
        // Only increase confidence for truly modal elements
        if ($modal->getAttribute('role') === 'dialog') {
            $confidence += 0.2; // Increase from 0.1 to 0.2
        }
        
        // Strong indicator of true modal
        if ($modal->getAttribute('aria-modal') === 'true') {
            $confidence += 0.3; // Increase from 0.1 to 0.3
        }
        
        // Strong penalty for hidden modals (likely templates)
        if (strpos($modal->getAttribute('style'), 'display:none') !== false || 
            strpos($modal->getAttribute('style'), 'display: none') !== false ||
            strpos($class_attr, 'hidden') !== false) {
            $confidence -= 0.6; // Increase from 0.3 to 0.6
        }
        
        // Ensure confidence stays within bounds
        return max(0.0, min(1.0, $confidence));
    }
    
    /**
     * Check tab order issues
     */
    private function check_tab_order($xpath, &$issues) {
        // Find all elements with positive tabindex values
        $positive_tabindex = $xpath->query('//*[@tabindex > 0]');
        if ($positive_tabindex->length > 0) {
            foreach ($positive_tabindex as $element) {
                $details = $this->get_element_details($element);
                $tabindex_value = $element->getAttribute('tabindex');
                
                $issues[] = [
                    'type' => 'positive_tabindex',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => "Positive tabindex value: {$tabindex_value}",
                    'description' => 'Positive tabindex values can disrupt natural tab order and make navigation unpredictable for keyboard users.',
                    'suggestion' => 'Use tabindex="0" for focusable elements or rely on natural DOM order instead of positive values.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 2.4.3 Focus Order',
                    'wcag_criterion' => '2.4.3',
                    'wcag_level' => 'A',
                    'compliance_impact' => 'WARNING',
                    'how_to_fix' => 'Change tabindex="' . $tabindex_value . '" to tabindex="0" or remove entirely.',
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Check for missing skip links
     */
    private function check_skip_links($xpath, &$issues) {
        $skip_links = $xpath->query('//a[contains(@href, "#") and (contains(translate(text(), "SKIP", "skip"), "skip") or contains(@class, "skip"))]');
        if ($skip_links->length === 0) {
            // Look for potential main content to suggest skip link target
            $main_content = $xpath->query('//main | //*[@role="main"] | //div[@id="content"] | //div[@id="main"]');
            $suggestion_target = $main_content->length > 0 ? '#' . ($main_content->item(0)->getAttribute('id') ?: 'main') : '#main-content';
            
            $issues[] = [
                'type' => 'missing_skip_links',
                'severity' => 'medium',
                'element' => 'body',
                'element_details' => ['selector' => 'body', 'html_snippet' => '<body>...', 'text_content' => '', 'tag_name' => 'body', 'attributes' => [], 'position' => 1],
                'message' => 'Page missing skip navigation links',
                'description' => 'The page lacks skip navigation links, making it difficult for keyboard users to bypass repetitive navigation and jump directly to main content.',
                'suggestion' => "Add skip links at the beginning of the page that allow users to jump to main content areas.",
                'wcag_reference' => 'WCAG 2.1 Level A - 2.4.1 Bypass Blocks',
                'wcag_criterion' => '2.4.1',
                'wcag_level' => 'A',
                'compliance_impact' => 'WARNING',
                'how_to_fix' => "Add at the start of <body>: <a href=\"{$suggestion_target}\" class=\"skip-link\">Skip to main content</a>",
                'auto_fixable' => true
            ];
        }
    }
    
    /**
     * Check focus indicators
     */
    private function check_focus_indicators($xpath, &$issues) {
        // Look for CSS that removes focus indicators
        $style_elements = $xpath->query('//style');
        foreach ($style_elements as $style) {
            $css_content = $style->textContent;
            if (preg_match('/\*\s*:\s*focus\s*\{\s*outline\s*:\s*none/i', $css_content) ||
                preg_match('/\*\s*\{\s*outline\s*:\s*none/i', $css_content)) {
                
                $issues[] = [
                    'type' => 'focus_indicators_removed',
                    'severity' => 'high',
                    'element' => 'style',
                    'element_details' => ['selector' => 'style', 'html_snippet' => '<style>...', 'text_content' => '', 'tag_name' => 'style', 'attributes' => [], 'position' => 1],
                    'message' => 'Focus indicators globally removed with CSS',
                    'description' => 'CSS removes focus indicators for all elements, making keyboard navigation impossible for many users.',
                    'suggestion' => 'Provide alternative focus indicators instead of removing them entirely.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 2.4.7 Focus Visible',
                    'wcag_criterion' => '2.4.7',
                    'wcag_level' => 'AA',
                    'compliance_impact' => 'VIOLATION',
                    'how_to_fix' => 'Replace outline:none with custom focus styles that meet contrast requirements.',
                    'auto_fixable' => false
                ];
            }
        }
    }
    
    /**
     * Scan ARIA attributes
     */
    private function scan_aria_attributes($xpath, &$issues) {
        // Valid ARIA attributes (subset of most common ones)
        $valid_aria_attrs = [
            'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden',
            'aria-expanded', 'aria-required', 'aria-checked', 'aria-selected',
            'aria-current', 'aria-live', 'aria-atomic', 'aria-busy'
        ];
        
        // Find all elements with aria attributes
        $aria_elements = $xpath->query('//*[starts-with(@*, "aria-")]');
        foreach ($aria_elements as $element) {
            $attributes = $element->attributes;
            for ($i = 0; $i < $attributes->length; $i++) {
                $attr = $attributes->item($i);
                if (strpos($attr->name, 'aria-') === 0) {
                    if (!in_array($attr->name, $valid_aria_attrs)) {
                        $details = $this->get_element_details($element);
                        $issues[] = [
                            'type' => 'invalid_aria',
                            'severity' => 'medium',
                            'element' => $details['selector'],
                            'element_details' => $details,
                            'message' => 'Invalid ARIA attribute: ' . $attr->name,
                            'description' => "The attribute '{$attr->name}' is not a valid ARIA attribute and should be removed or replaced with a valid one.",
                            'suggestion' => 'Remove invalid ARIA attributes and use only standard ARIA attributes.',
                            'wcag_reference' => 'WCAG 2.1 Level A - 4.1.2 Name, Role, Value',
                            'wcag_criterion' => '4.1.2',
                            'wcag_level' => 'A',
                            'compliance_impact' => 'WARNING',
                            'how_to_fix' => "Remove or replace the '{$attr->name}' attribute with a valid ARIA attribute.",
                            'auto_fixable' => false
                        ];
                    }
                }
            }
        }
        
        // Check for required ARIA properties
        $buttons = $xpath->query('//button[@aria-expanded and not(@aria-controls)]');
        foreach ($buttons as $button) {
            $details = $this->get_element_details($button);
            $issues[] = [
                'type' => 'missing_aria_controls',
                'severity' => 'medium',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Button with aria-expanded missing aria-controls',
                'description' => 'This button has aria-expanded but lacks aria-controls, making it unclear what element is being controlled.',
                'suggestion' => 'Add aria-controls attribute pointing to the ID of the element being expanded/collapsed.',
                'wcag_reference' => 'WCAG 2.1 Level A - 4.1.2 Name, Role, Value',
                'wcag_criterion' => '4.1.2',
                'wcag_level' => 'A',
                'compliance_impact' => 'WARNING',
                'how_to_fix' => 'Add aria-controls="target-element-id" to specify what this button controls.',
                'auto_fixable' => false
            ];
        }
    }
    
    /**
     * Check semantic structure
     */
    private function check_semantic_structure($xpath, &$issues) {
        // Check for missing landmarks
        $main_elements = $xpath->query('//main | //*[@role="main"]');
        if ($main_elements->length === 0) {
            // Try to find likely main content areas to suggest
            $content_candidates = $xpath->query('//div[@id="content"] | //div[@id="main"] | //div[contains(@class, "content")] | //div[contains(@class, "main")]');
            $suggestion_text = 'Add a <main> element or role="main" to identify the main content area.';
            
            if ($content_candidates->length > 0) {
                $candidate = $content_candidates->item(0);
                $candidate_selector = $this->get_element_selector($candidate);
                $suggestion_text = "Consider adding role=\"main\" to {$candidate_selector} or wrap main content in <main> element.";
            }
            
            $issues[] = [
                'type' => 'missing_main_landmark',
                'severity' => 'medium',
                'element' => 'body',
                'element_details' => ['selector' => 'body', 'html_snippet' => '<body>...', 'text_content' => '', 'tag_name' => 'body', 'attributes' => [], 'position' => 1],
                'message' => 'Page missing main landmark',
                'description' => 'The page lacks a main landmark (main element or role="main"), making it difficult for screen reader users to navigate to the primary content.',
                'suggestion' => $suggestion_text,
                'wcag_reference' => 'WCAG 2.1 Level AA - 2.4.1 Bypass Blocks',
                'wcag_criterion' => '2.4.1',
                'wcag_level' => 'AA',
                'compliance_impact' => 'WARNING',
                'how_to_fix' => 'Wrap your main content in <main>content here</main> or add role="main" to your content container.',
                'auto_fixable' => true
            ];
        }
        
        // Check heading hierarchy (respecting aria-level)
        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $previous_level = 0;
        foreach ($headings as $heading) {
            // Check for aria-level first, fall back to tag level
            if ($heading->hasAttribute('aria-level')) {
                $current_level = intval($heading->getAttribute('aria-level'));
            } else {
                $current_level = intval(substr($heading->tagName, 1));
            }
            
            // Don't report issues for headings we've already fixed
            if (!$heading->hasAttribute('data-raywp-heading-fixed')) {
                if ($previous_level > 0 && $current_level > $previous_level + 1) {
                    $details = $this->get_element_details($heading);
                    $issues[] = [
                        'type' => 'heading_hierarchy_skip',
                        'severity' => 'medium',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => 'Heading level skipped from h' . $previous_level . ' to h' . $current_level,
                        'description' => "This heading jumps from level {$previous_level} to level {$current_level}, skipping intermediate levels. This creates confusion for screen reader users who rely on heading structure for navigation.",
                        'suggestion' => "Change this heading to h" . ($previous_level + 1) . " or add intermediate heading levels.",
                        'wcag_reference' => 'WCAG 2.1 Level AA - 2.4.6 Headings and Labels',
                        'wcag_criterion' => '2.4.6',
                        'wcag_level' => 'AA',
                        'compliance_impact' => 'WARNING',
                        'how_to_fix' => "Change <{$heading->tagName}> to <h" . ($previous_level + 1) . "> or add missing heading levels in between.",
                        'auto_fixable' => true
                    ];
                }
            }
            $previous_level = $current_level;
        }
        
        // Check for multiple h1s (excluding those with aria-level)
        $h1s = $xpath->query('//h1[not(@aria-level)]');
        if ($h1s->length > 1) {
            $h1_texts = [];
            $actual_h1_count = 0;
            
            // Count h1s that are actually level 1 (not modified with aria-level)
            foreach ($xpath->query('//h1') as $h1) {
                if (!$h1->hasAttribute('aria-level') || $h1->getAttribute('aria-level') == '1') {
                    $actual_h1_count++;
                    $h1_texts[] = '"' . trim(substr($h1->textContent, 0, 50)) . '"';
                }
            }
            
            if ($actual_h1_count > 1) {
                $issues[] = [
                    'type' => 'multiple_h1',
                    'severity' => 'low',
                    'element' => 'body',
                    'element_details' => ['selector' => 'body', 'html_snippet' => '<body>...', 'text_content' => '', 'tag_name' => 'body', 'attributes' => [], 'position' => 1],
                    'message' => 'Multiple h1 elements found (' . $actual_h1_count . ')',
                    'description' => 'The page contains ' . $actual_h1_count . ' h1 elements: ' . implode(', ', $h1_texts) . '. While not strictly forbidden, having multiple h1s can be confusing for navigation.',
                    'suggestion' => 'Consider using only one h1 per page for the main page title, and convert others to h2 or lower levels.',
                    'wcag_reference' => 'Best Practice - Single h1 per page',
                    'wcag_criterion' => '2.4.6',
                    'wcag_level' => 'AA',
                    'compliance_impact' => 'BEST_PRACTICE',
                    'how_to_fix' => 'Keep the most important h1 and change others to appropriate heading levels (h2, h3, etc.)',
                    'auto_fixable' => true
                ];
            }
        }
        
        // Check tables without headers
        $tables = $xpath->query('//table[not(.//th)]');
        foreach ($tables as $table) {
            $details = $this->get_element_details($table);
            $issues[] = [
                'type' => 'table_no_headers',
                'severity' => 'high',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Data table missing header cells',
                'description' => 'This table has no header cells (th elements), making it impossible for screen reader users to understand the table structure and data relationships.',
                'suggestion' => 'Add header cells using <th> elements in the first row or column.',
                'wcag_reference' => 'WCAG 2.1 Level A - 1.3.1 Info and Relationships',
                'wcag_criterion' => '1.3.1',
                'wcag_level' => 'A',
                'compliance_impact' => 'VIOLATION',
                'how_to_fix' => 'Replace <td> elements in header row with <th> elements: <th>Header 1</th><th>Header 2</th>',
                'auto_fixable' => true
            ];
        }
    }
    
    /**
     * Check advanced form issues
     */
    private function check_advanced_form_issues($xpath, &$issues) {
        // Check for radio groups without fieldsets
        $radio_groups = [];
        $radios = $xpath->query('//input[@type="radio"]');
        foreach ($radios as $radio) {
            $name = $radio->getAttribute('name');
            if ($name) {
                if (!isset($radio_groups[$name])) {
                    $radio_groups[$name] = [];
                }
                $radio_groups[$name][] = $radio;
            }
        }
        
        foreach ($radio_groups as $name => $group) {
            if (count($group) > 1) {
                $first_radio = $group[0];
                $fieldset = $xpath->query('.//ancestor::fieldset', $first_radio);
                if ($fieldset->length === 0) {
                    $details = $this->get_element_details($first_radio);
                    
                    $issues[] = [
                        'type' => 'radio_no_fieldset',
                        'severity' => 'medium',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => 'Radio group "' . $name . '" not wrapped in fieldset',
                        'description' => "This radio button group ({$name}) with " . count($group) . " options is not wrapped in a fieldset, making it difficult for screen reader users to understand the relationship between options.",
                        'suggestion' => 'Wrap the radio group in a <fieldset> with a <legend> describing the group.',
                        'wcag_reference' => 'WCAG 2.1 Level A - 1.3.1 Info and Relationships',
                        'wcag_criterion' => '1.3.1',
                        'wcag_level' => 'A',
                        'compliance_impact' => 'WARNING',
                        'how_to_fix' => '<fieldset><legend>Group Description</legend><!-- radio buttons here --></fieldset>',
                        'auto_fixable' => true
                    ];
                }
            }
        }
        
        // Check for required fields without indication
        $required_inputs = $xpath->query('//input[@required and not(@aria-required)]');
        foreach ($required_inputs as $input) {
            $details = $this->get_element_details($input);
            $field_name = $input->getAttribute('name') ?: $input->getAttribute('id') ?: 'unnamed';
            
            $issues[] = [
                'type' => 'required_no_aria',
                'severity' => 'medium',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Required field missing aria-required attribute',
                'description' => "This required field ({$field_name}) lacks the aria-required attribute, making it unclear to screen reader users that the field is mandatory.",
                'suggestion' => 'Add aria-required="true" to clearly indicate this field is required.',
                'wcag_reference' => 'WCAG 2.1 Level AA - 3.3.2 Labels or Instructions',
                'wcag_criterion' => '3.3.2',
                'wcag_level' => 'AA',
                'compliance_impact' => 'WARNING',
                'how_to_fix' => 'Add aria-required="true" to the input element alongside the required attribute.',
                'auto_fixable' => true
            ];
        }
        
        // Check for form validation errors not associated with inputs
        $error_messages = $xpath->query('//*[contains(@class, "error") or contains(@class, "invalid")]');
        foreach ($error_messages as $error) {
            if (!$error->hasAttribute('role') && !empty(trim($error->textContent))) {
                $details = $this->get_element_details($error);
                $error_text = trim(substr($error->textContent, 0, 100));
                
                $issues[] = [
                    'type' => 'error_no_role',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Error message missing role="alert"',
                    'description' => "This error message (\"{$error_text}\") lacks the role=\"alert\" attribute, so screen readers may not announce it when it appears.",
                    'suggestion' => 'Add role="alert" to make sure screen readers announce the error message.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 4.1.3 Status Messages',
                    'wcag_criterion' => '4.1.3',
                    'wcag_level' => 'AA',
                    'compliance_impact' => 'WARNING',
                    'how_to_fix' => 'Add role="alert" and optionally aria-live="polite" to the error message element.',
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Check for duplicate IDs
     */
    private function check_duplicate_ids($xpath, &$issues) {
        // Get all elements with ID attributes
        $id_elements = $xpath->query('//*[@id]');
        $id_count = [];
        
        foreach ($id_elements as $element) {
            $id = $element->getAttribute('id');
            if (!isset($id_count[$id])) {
                $id_count[$id] = [];
            }
            $id_count[$id][] = $element;
        }
        
        foreach ($id_count as $id => $elements) {
            if (count($elements) > 1) {
                $first_element = $elements[0];
                $details = $this->get_element_details($first_element);
                
                $issues[] = [
                    'type' => 'duplicate_id',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Duplicate ID found: ' . $id,
                    'description' => "The ID '{$id}' is used on " . count($elements) . " elements. IDs must be unique within a page as they're used by assistive technology for navigation and form relationships.",
                    'suggestion' => 'Ensure each ID is unique. Use classes for styling multiple elements.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 4.1.1 Parsing',
                    'wcag_criterion' => '4.1.1',
                    'wcag_level' => 'A',
                    'compliance_impact' => 'VIOLATION',
                    'how_to_fix' => 'Change duplicate IDs to unique values or use class attributes instead.',
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Check page language
     */
    private function check_page_language($xpath, &$issues) {
        $html_element = $xpath->query('//html')->item(0);
        
        if (!$html_element || !$html_element->hasAttribute('lang')) {
            $issues[] = [
                'type' => 'missing_page_language',
                'severity' => 'high',
                'element' => 'html',
                'element_details' => ['selector' => 'html', 'html_snippet' => '<html>', 'text_content' => '', 'tag_name' => 'html', 'attributes' => [], 'position' => 1],
                'message' => 'Page missing language declaration',
                'description' => 'The HTML element lacks a lang attribute, making it impossible for screen readers to announce content in the correct language.',
                'suggestion' => 'Add lang="en" (or appropriate language code) to the HTML element.',
                'wcag_reference' => 'WCAG 2.1 Level A - 3.1.1 Language of Page',
                'wcag_criterion' => '3.1.1',
                'wcag_level' => 'A',
                'compliance_impact' => 'VIOLATION',
                'how_to_fix' => 'Add lang="en" to your <html> element: <html lang="en">',
                'auto_fixable' => true
            ];
        } else {
            $lang = $html_element->getAttribute('lang');
            if (empty(trim($lang)) || strlen($lang) < 2) {
                $details = $this->get_element_details($html_element);
                $issues[] = [
                    'type' => 'invalid_page_language',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Invalid language code: ' . $lang,
                    'description' => "The lang attribute value '{$lang}' appears to be invalid. Language codes should follow ISO standards (e.g., 'en' for English, 'es' for Spanish).",
                    'suggestion' => 'Use a valid ISO language code like "en" for English.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 3.1.1 Language of Page',
                    'how_to_fix' => 'Change to a valid language code: <html lang="en">'
                ];
            }
        }
    }
    
    /**
     * Check link purposes
     */
    private function check_link_purposes($xpath, &$issues) {
        // Get all links first, then filter out admin-only ones
        $all_links = $xpath->query('//a[@href]');
        $links = [];
        
        foreach ($all_links as $link) {
            // Skip WordPress admin toolbar links - they're only visible to logged-in admins
            $is_admin_link = false;
            
            // Check if the link or its ancestors are part of admin toolbar
            $current = $link;
            while ($current && !$is_admin_link) {
                if ($current->nodeType === XML_ELEMENT_NODE) {
                    $id = $current->getAttribute('id');
                    $class = $current->getAttribute('class');
                    
                    if ($id === 'wpadminbar' || 
                        strpos($class, 'ab-item') !== false || 
                        strpos($class, 'ab-top-menu') !== false ||
                        strpos($class, 'wp-admin') !== false) {
                        $is_admin_link = true;
                        break;
                    }
                }
                $current = $current->parentNode;
            }
            
            // Also check the link's own classes and href
            $link_classes = $link->getAttribute('class');
            $href = $link->getAttribute('href');
            if (strpos($link_classes, 'ab-item') !== false || 
                strpos($href, 'wp-admin') !== false) {
                $is_admin_link = true;
            }
            
            if (!$is_admin_link) {
                $links[] = $link;
            }
        }
        
        foreach ($links as $link) {
            $link_text = trim($link->textContent);
            $href = $link->getAttribute('href');
            $has_aria_label = $link->hasAttribute('aria-label');
            $has_aria_labelledby = $link->hasAttribute('aria-labelledby');
            $has_title = $link->hasAttribute('title');
            
            
            // Check for generic link text
            $generic_texts = ['click here', 'read more', 'more', 'here', 'link', 'continue'];
            $is_generic = in_array(strtolower($link_text), $generic_texts);
            
            if (empty($link_text) && !$has_aria_label && !$has_aria_labelledby) {
                // Check if link contains only images
                $images = $xpath->query('.//img', $link);
                if ($images->length > 0) {
                    $has_alt_text = false;
                    foreach ($images as $img) {
                        if ($img->hasAttribute('alt') && !empty(trim($img->getAttribute('alt')))) {
                            $has_alt_text = true;
                            break;
                        }
                    }
                    
                    if (!$has_alt_text) {
                        $details = $this->get_element_details($link);
                        $issues[] = [
                            'type' => 'link_no_accessible_name',
                            'severity' => 'high',
                            'element' => $details['selector'],
                            'element_details' => $details,
                            'message' => 'Link has no accessible name',
                            'description' => 'This link contains only images without alt text, making its purpose unclear to screen reader users.',
                            'suggestion' => 'Add alt text to the image(s) or an aria-label to the link.',
                            'wcag_reference' => 'WCAG 2.1 Level A - 2.4.4 Link Purpose (In Context)',
                            'how_to_fix' => 'Add alt="descriptive text" to images or aria-label="link purpose" to the link element.',
                            'auto_fixable' => true
                        ];
                    }
                } else {
                    $details = $this->get_element_details($link);
                    $issues[] = [
                        'type' => 'empty_link',
                        'severity' => 'high',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => 'Empty link with no text or accessible name',
                        'description' => 'This link has no text content or accessible name, making it impossible for users to understand its purpose.',
                        'suggestion' => 'Add descriptive text or an aria-label to the link.',
                        'wcag_reference' => 'WCAG 2.1 Level A - 2.4.4 Link Purpose (In Context)',
                        'how_to_fix' => 'Add text content or aria-label="descriptive purpose" to the link.'
                    ];
                }
            } elseif ($is_generic && !$has_aria_label && !$has_title) {
                $details = $this->get_element_details($link);
                $issues[] = [
                    'type' => 'generic_link_text',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Generic link text: "' . $link_text . '"',
                    'description' => "The link text '{$link_text}' is too generic and doesn't clearly indicate the link's purpose or destination.",
                    'suggestion' => 'Use more descriptive link text that explains where the link goes or what it does.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 2.4.4 Link Purpose (In Context)',
                    'how_to_fix' => 'Replace with descriptive text like "Read the full article about [topic]" instead of "Read more".'
                ];
            }
        }
    }
    
    /**
     * Check iframe accessibility
     */
    private function check_iframe_accessibility($xpath, &$issues) {
        $iframes = $xpath->query('//iframe');
        
        foreach ($iframes as $iframe) {
            $has_title = $iframe->hasAttribute('title');
            $title_text = $has_title ? trim($iframe->getAttribute('title')) : '';
            $src = $iframe->getAttribute('src');
            
            if (!$has_title || empty($title_text)) {
                $details = $this->get_element_details($iframe);
                $issues[] = [
                    'type' => 'iframe_missing_title',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Iframe missing title attribute',
                    'description' => "This iframe (source: {$src}) lacks a title attribute, making it difficult for screen reader users to understand the iframe's content or purpose.",
                    'suggestion' => 'Add a descriptive title attribute that explains the iframe content.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 2.4.1 Bypass Blocks',
                    'how_to_fix' => 'Add title="Description of iframe content" to the iframe element.'
                ];
            }
        }
    }
    
    /**
     * Check media accessibility with intelligent classification
     */
    private function check_media_accessibility($xpath, &$issues) {
        // Check video elements with smart analysis
        $videos = $xpath->query('//video');
        foreach ($videos as $video) {
            $this->analyze_video_accessibility($video, $issues, $xpath);
        }
        
        // Check audio elements
        $audios = $xpath->query('//audio');
        foreach ($audios as $audio) {
            $this->analyze_audio_accessibility($audio, $issues);
        }
    }
    
    /**
     * Intelligent video analysis to reduce false positives
     */
    private function analyze_video_accessibility($video, &$issues, $xpath) {
        $has_controls = $video->hasAttribute('controls');
        $autoplay = $video->hasAttribute('autoplay');
        $muted = $video->hasAttribute('muted');
        $loop = $video->hasAttribute('loop');
        $has_captions = $xpath->query('.//track[@kind="captions" or @kind="subtitles"]', $video)->length > 0;
        
        // Get video context to determine if it's decorative
        $is_decorative = $this->is_video_decorative($video);
        
        // Handle autoplay issues intelligently
        if ($autoplay) {
            if ($is_decorative && $muted) {
                // Decorative muted autoplay video - this is usually acceptable
                // But suggest aria-hidden for better screen reader handling
                $details = $this->get_element_details($video);
                if (!$video->hasAttribute('aria-hidden')) {
                    $issues[] = [
                        'type' => 'decorative_video_no_aria_hidden',
                        'severity' => 'low',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => 'Decorative background video should have aria-hidden',
                        'description' => 'This appears to be a decorative background video. Adding aria-hidden="true" will help screen readers skip over it.',
                        'suggestion' => 'Add aria-hidden="true" to decorative videos to improve screen reader experience.',
                        'wcag_reference' => 'WCAG 2.1 Level A - 4.1.2 Name, Role, Value',
                        'how_to_fix' => 'Add aria-hidden="true" to the video element.',
                        'auto_fixable' => true
                    ];
                }
            } else if (!$muted) {
                // Autoplay with sound - this is problematic
                $details = $this->get_element_details($video);
                $issues[] = [
                    'type' => 'video_autoplay_with_sound',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Video autoplays with sound',
                    'description' => 'Auto-playing videos with sound interfere with screen readers and can be startling for users.',
                    'suggestion' => 'Add muted attribute or remove autoplay to let users control video playback.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 2.2.2 Pause, Stop, Hide',
                    'how_to_fix' => 'Add muted attribute: <video autoplay muted> or remove autoplay entirely.',
                    'auto_fixable' => true
                ];
            }
        }
        
        // Handle caption requirements intelligently
        if (!$has_captions && !$is_decorative) {
            // Only flag content videos for missing captions
            $details = $this->get_element_details($video);
            $issues[] = [
                'type' => 'video_no_captions',
                'severity' => 'high',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Content video missing captions/subtitles',
                'description' => 'This video contains content and lacks caption tracks, making audio content inaccessible to deaf or hard-of-hearing users.',
                'suggestion' => 'Add caption or subtitle tracks using <track> elements, or add aria-label describing the video content.',
                'wcag_reference' => 'WCAG 2.1 Level A - 1.2.2 Captions (Prerecorded)',
                'how_to_fix' => 'Add: <track kind="captions" src="captions.vtt" srclang="en" label="English captions">',
                'auto_fixable' => false
            ];
        }
        
        // Check for missing controls on content videos
        if (!$has_controls && !$is_decorative && !$autoplay) {
            $details = $this->get_element_details($video);
            $issues[] = [
                'type' => 'video_no_controls',
                'severity' => 'medium',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Video missing user controls',
                'description' => 'Users need controls to pause, play, and adjust volume for content videos.',
                'suggestion' => 'Add controls attribute to provide standard video controls.',
                'wcag_reference' => 'WCAG 2.1 Level A - 2.2.2 Pause, Stop, Hide',
                'how_to_fix' => 'Add controls attribute: <video controls>',
                'auto_fixable' => true
            ];
        }
    }
    
    /**
     * Determine if a video is decorative based on context
     */
    private function is_video_decorative($video) {
        // Check various indicators that suggest a video is decorative
        $parent = $video->parentNode;
        
        // Check CSS classes that suggest decorative use
        $classes = $video->getAttribute('class');
        $decorative_classes = ['background', 'hero', 'banner', 'decoration', 'ambient'];
        foreach ($decorative_classes as $dec_class) {
            if (strpos(strtolower($classes), $dec_class) !== false) {
                return true;
            }
        }
        
        // Check parent container classes
        if ($parent && $parent->hasAttribute('class')) {
            $parent_classes = $parent->getAttribute('class');
            foreach ($decorative_classes as $dec_class) {
                if (strpos(strtolower($parent_classes), $dec_class) !== false) {
                    return true;
                }
            }
        }
        
        // Check if video is muted, looped, and has no controls (typical for decorative)
        if ($video->hasAttribute('muted') && 
            $video->hasAttribute('loop') && 
            !$video->hasAttribute('controls')) {
            return true;
        }
        
        // Check positioning - videos that are positioned as background
        $style = $video->getAttribute('style');
        if (preg_match('/position:\s*(?:absolute|fixed)/', $style) && 
            preg_match('/z-index:\s*-?\d+/', $style)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Analyze audio accessibility
     */
    private function analyze_audio_accessibility($audio, &$issues) {
        $autoplay = $audio->hasAttribute('autoplay');
        $has_controls = $audio->hasAttribute('controls');
        
        if ($autoplay) {
            $details = $this->get_element_details($audio);
            $issues[] = [
                'type' => 'audio_autoplay',
                'severity' => 'high',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Audio has autoplay enabled',
                'description' => 'Auto-playing audio interferes with screen readers and can be startling for users.',
                'suggestion' => 'Remove autoplay or provide immediate pause/stop controls.',
                'wcag_reference' => 'WCAG 2.1 Level A - 2.2.2 Pause, Stop, Hide',
                'how_to_fix' => 'Remove autoplay attribute and let users choose to play audio.',
                'auto_fixable' => true
            ];
        }
        
        if (!$has_controls) {
            $details = $this->get_element_details($audio);
            $issues[] = [
                'type' => 'audio_no_controls',
                'severity' => 'medium',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Audio missing user controls',
                'description' => 'Users need controls to pause, play, and adjust volume for audio content.',
                'suggestion' => 'Add controls attribute to provide standard audio controls.',
                'wcag_reference' => 'WCAG 2.1 Level A - 2.2.2 Pause, Stop, Hide',
                'how_to_fix' => 'Add controls attribute: <audio controls>',
                'auto_fixable' => true
            ];
        }
    }
    
    /**
     * Check screen reader compatibility
     */
    private function check_screen_reader_compatibility($xpath, &$issues) {
        // Check for elements that might confuse screen readers
        
        // Check for elements with conflicting roles
        $elements_with_role = $xpath->query('//*[@role]');
        foreach ($elements_with_role as $element) {
            $role = $element->getAttribute('role');
            $tag_name = strtolower($element->tagName);
            
            // Check for semantic conflicts
            if (($tag_name === 'button' && $role === 'link') || 
                ($tag_name === 'a' && $role === 'button')) {
                $details = $this->get_element_details($element);
                $issues[] = [
                    'type' => 'conflicting_semantic_role',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => "Conflicting semantic role on {$tag_name} element",
                    'description' => "This {$tag_name} element has role=\"{$role}\" which conflicts with its semantic meaning and may confuse screen reader users.",
                    'suggestion' => "Use the appropriate HTML element instead of overriding with role, or ensure the role is semantically appropriate.",
                    'wcag_reference' => 'WCAG 2.1 Level A - 4.1.2 Name, Role, Value',
                    'how_to_fix' => "Remove conflicting role attribute or use the correct HTML element for the intended purpose."
                ];
            }
        }
        
        // Check for hidden content that might be announced
        $hidden_elements = $xpath->query('//*[@aria-hidden="true"]');
        foreach ($hidden_elements as $element) {
            if ($element->hasAttribute('tabindex') && $element->getAttribute('tabindex') !== '-1') {
                $details = $this->get_element_details($element);
                $issues[] = [
                    'type' => 'hidden_focusable_element',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Hidden element is focusable',
                    'description' => 'This element is hidden from screen readers (aria-hidden="true") but is still focusable, which creates confusion.',
                    'suggestion' => 'Either remove aria-hidden or make the element non-focusable with tabindex="-1".',
                    'wcag_reference' => 'WCAG 2.1 Level A - 4.1.2 Name, Role, Value',
                    'how_to_fix' => 'Remove aria-hidden="true" or add tabindex="-1" to make it non-focusable.'
                ];
            }
        }
        
        // Check for missing ARIA labels on interactive elements
        $interactive_without_names = $xpath->query('//button[not(@aria-label) and not(@aria-labelledby) and (not(text()) or normalize-space(text()) = "")]');
        foreach ($interactive_without_names as $button) {
            $details = $this->get_element_details($button);
            $issues[] = [
                'type' => 'button_missing_accessible_name',
                'severity' => 'high',
                'element' => $details['selector'],
                'element_details' => $details,
                'message' => 'Button missing accessible name',
                'description' => 'This button has no text content or accessible name, making it impossible for screen reader users to understand its purpose.',
                'suggestion' => 'Add text content, aria-label, or aria-labelledby to provide an accessible name.',
                'wcag_reference' => 'WCAG 2.1 Level A - 4.1.2 Name, Role, Value',
                'how_to_fix' => 'Add descriptive text content or aria-label="button purpose".'
            ];
        }
    }
    
    /**
     * Check text spacing compliance (WCAG 2.1 AA - 1.4.12)
     */
    private function check_text_spacing_compliance($xpath, &$issues) {
        // Check for CSS that might interfere with text spacing adjustments
        $style_elements = $xpath->query('//style');
        foreach ($style_elements as $style) {
            $css_content = $style->textContent;
            
            // Skip WordPress auto-generated styles that don't affect text spacing
            // These include: auto-sizes, lazy loading, responsive images, etc.
            $style_id = $style->getAttribute('id');
            if ($style_id) {
                // Skip known WordPress/plugin auto-generated inline styles
                $skip_patterns = [
                    'wp-img-auto-sizes',
                    'wp-block-library',
                    'wp-block-',
                    'global-styles-inline',
                    'classic-theme-styles',
                    '-inline-css',
                    'elementor-',
                    'gutenberg-',
                ];
                $should_skip = false;
                foreach ($skip_patterns as $pattern) {
                    if (strpos($style_id, $pattern) !== false) {
                        $should_skip = true;
                        break;
                    }
                }
                if ($should_skip) {
                    continue;
                }
            }
            
            // Look for restrictive line-height, letter-spacing, word-spacing, or margin settings
            // Explicitly exclude contain-intrinsic-size and other layout properties
            
            // Skip style blocks that contain layout-only CSS (no text properties)
            // These include: contain-intrinsic-size, sizes, grid, flex, etc.
            if (strpos($css_content, 'contain-intrinsic-size') !== false) {
                continue; // Skip this style block entirely if it contains contain-intrinsic-size
            }
            
            // Skip if CSS content is mostly layout or image-related (not text-related)
            if (preg_match('/^\s*(?:img|svg|video|canvas|iframe)/i', $css_content)) {
                continue;
            }
            
            // Now check for actual text spacing restrictions with more specific regex
            // Must be applied to text-containing selectors (body, p, span, etc.)
            // Only flag if the CSS contains actual restrictive text spacing that affects readability
            $has_restrictive_spacing = false;
            
            // Check for restrictive line-height (unitless values below 1.5 are problematic)
            if (preg_match('/(?:^|[{;,\s])line-height\s*:\s*([0-9.]+)(?:\s*[;}\s]|$)/i', $css_content, $matches)) {
                $value = floatval($matches[1]);
                // Only flag if line-height is restrictive (below 1.2 is definitely problematic)
                if ($value > 0 && $value < 1.2) {
                    $has_restrictive_spacing = true;
                }
            }
            
            // Check for negative letter-spacing (definitely restricts text spacing)
            if (preg_match('/(?:^|[{;,\s])letter-spacing\s*:\s*-[0-9.]+(?:px|em|rem|ch)/i', $css_content)) {
                $has_restrictive_spacing = true;
            }
            
            // Check for negative word-spacing
            if (preg_match('/(?:^|[{;,\s])word-spacing\s*:\s*-[0-9.]+(?:px|em|rem|ch)/i', $css_content)) {
                $has_restrictive_spacing = true;
            }
            
            if ($has_restrictive_spacing) {
                $issues[] = [
                    'type' => 'restrictive_text_spacing',
                    'severity' => 'medium',
                    'element' => 'style',
                    'element_details' => $this->get_element_details($style),
                    'message' => 'CSS may interfere with text spacing adjustments',
                    'description' => 'CSS styles may prevent users from adjusting text spacing to meet their needs (1.5x line height, 0.12x letter spacing, 0.16x word spacing, 2x paragraph spacing).',
                    'suggestion' => 'Ensure text spacing can be adjusted up to required minimums without loss of content or functionality.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 1.4.12 Text Spacing',
                    'how_to_fix' => 'Use relative units (em, %) and avoid restrictive spacing constraints.',
                    'auto_fixable' => false
                ];
            }
        }
        
        // Check for elements with inline styles that might restrict spacing
        $inline_style_elements = $xpath->query('//*[@style]');
        foreach ($inline_style_elements as $element) {
            $style = $element->getAttribute('style');
            
            // Skip elements that have contain-intrinsic-size in their style
            if (strpos($style, 'contain-intrinsic-size') !== false) {
                continue;
            }
            
            if (preg_match('/\b(?:line-height|font-line-height)\s*:\s*[0-9.]+(?!em|rem|%|vh|vw|ch|ex)(?:\s*[;}\s]|$)/i', $style) ||
                preg_match('/\b(?:letter-spacing|font-letter-spacing)\s*:\s*-[0-9.]+(?:px|em|rem|ch)?/i', $style)) {
                
                $details = $this->get_element_details($element);
                $issues[] = [
                    'type' => 'inline_spacing_restriction',
                    'severity' => 'low',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Inline styles may restrict text spacing adjustments',
                    'description' => 'This element has inline styles that may prevent users from adjusting text spacing.',
                    'suggestion' => 'Use CSS classes instead of inline styles for text spacing.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 1.4.12 Text Spacing',
                    'how_to_fix' => 'Move spacing styles to CSS and ensure they support user adjustments.',
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Check resize text compliance (WCAG 2.1 AA - 1.4.4)
     */
    private function check_resize_text_compliance($xpath, &$issues) {
        // Check viewport meta tag for zoom restrictions
        $viewport_meta = $xpath->query('//meta[@name="viewport"]');
        foreach ($viewport_meta as $meta) {
            $content = $meta->getAttribute('content');
            if (preg_match('/user-scalable\s*=\s*no/i', $content) ||
                preg_match('/maximum-scale\s*=\s*1(?:\.0)?(?:\s|,|$)/i', $content)) {
                
                $details = $this->get_element_details($meta);
                $issues[] = [
                    'type' => 'zoom_disabled',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Viewport prevents text resizing/zooming',
                    'description' => 'The viewport meta tag prevents users from zooming or scaling text, making content inaccessible to users who need larger text.',
                    'suggestion' => 'Remove user-scalable=no or maximum-scale=1.0 to allow users to zoom up to 200%.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 1.4.4 Resize Text',
                    'how_to_fix' => 'Change viewport content to allow scaling: <meta name="viewport" content="width=device-width, initial-scale=1.0">',
                    'auto_fixable' => true
                ];
            }
        }
        
        // Check for CSS that might prevent text scaling
        $style_elements = $xpath->query('//style');
        foreach ($style_elements as $style) {
            $css_content = $style->textContent;
            if (preg_match('/font-size\s*:\s*[0-9]+px\s*!important/i', $css_content)) {
                $issues[] = [
                    'type' => 'fixed_font_sizes',
                    'severity' => 'medium',
                    'element' => 'style',
                    'element_details' => $this->get_element_details($style),
                    'message' => 'CSS uses fixed pixel font sizes with !important',
                    'description' => 'Fixed pixel font sizes with !important may prevent users from scaling text effectively.',
                    'suggestion' => 'Use relative units (em, rem, %) for font sizes instead of fixed pixels.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 1.4.4 Resize Text',
                    'how_to_fix' => 'Replace px with em or rem units and remove !important declarations.',
                    'auto_fixable' => false
                ];
            }
        }
    }
    
    /**
     * Check motion and animation controls (WCAG 2.1 AA - 2.3.3)
     */
    private function check_motion_animation_controls($xpath, &$issues) {
        // Check for CSS animations without user control
        $style_elements = $xpath->query('//style');
        foreach ($style_elements as $style) {
            $css_content = $style->textContent;
            
            // Look for animations that might cause vestibular disorders
            if (preg_match('/@keyframes|animation\s*:|transition\s*:/i', $css_content)) {
                // Check if prefers-reduced-motion is respected
                if (!preg_match('/@media\s*\(\s*prefers-reduced-motion/i', $css_content)) {
                    $issues[] = [
                        'type' => 'animation_no_reduced_motion',
                        'severity' => 'medium',
                        'element' => 'style',
                        'element_details' => $this->get_element_details($style),
                        'message' => 'Animations present without prefers-reduced-motion support',
                        'description' => 'CSS animations are used but prefers-reduced-motion media query is not implemented to respect user preferences.',
                        'suggestion' => 'Add @media (prefers-reduced-motion: reduce) to provide non-animated alternatives.',
                        'wcag_reference' => 'WCAG 2.1 Level AA - 2.3.3 Animation from Interactions',
                        'how_to_fix' => 'Plugin automatically adds comprehensive prefers-reduced-motion support via frontend.css.',
                        'auto_fixable' => true
                    ];
                }
            }
            
            // Check for problematic transforms that might cause motion sensitivity
            if (preg_match('/transform\s*:\s*[^;]*rotate|transform\s*:\s*[^;]*scale/i', $css_content)) {
                if (!preg_match('/@media\s*\(\s*prefers-reduced-motion/i', $css_content)) {
                    $issues[] = [
                        'type' => 'transform_animation_no_control',
                        'severity' => 'medium',
                        'element' => 'style',
                        'element_details' => $this->get_element_details($style),
                        'message' => 'Transform animations without motion preference controls',
                        'description' => 'CSS transforms that rotate or scale elements may cause vestibular disorders for some users.',
                        'suggestion' => 'Provide controls or respect prefers-reduced-motion for transform animations.',
                        'wcag_reference' => 'WCAG 2.1 Level AA - 2.3.3 Animation from Interactions',
                        'how_to_fix' => 'Plugin automatically reduces animation and transition durations via prefers-reduced-motion CSS.',
                        'auto_fixable' => true
                    ];
                }
            }
        }
        
        // Check for video/gif content that might auto-play with motion
        $videos = $xpath->query('//video[@autoplay] | //img[contains(@src, ".gif")]');
        
        // Check if there's CSS handling for prefers-reduced-motion
        $has_motion_css_handling = false;
        $style_elements = $xpath->query('//style | //link[@rel="stylesheet"]');
        foreach ($style_elements as $style) {
            $css_content = '';
            if ($style->tagName === 'style') {
                $css_content = $style->textContent;
            } elseif ($style->tagName === 'link') {
                // Check if this is the RayWP accessibility CSS file which contains motion handling
                $href = $style->getAttribute('href');
                if (strpos($href, 'raywp-accessibility') !== false && strpos($href, 'frontend.css') !== false) {
                    $has_motion_css_handling = true;
                    break;
                }
            }
            // Check for prefers-reduced-motion media query handling videos
            if (preg_match('/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\).*video.*display\s*:\s*none/s', $css_content)) {
                $has_motion_css_handling = true;
                break;
            }
        }
        
        foreach ($videos as $media) {
            if ($media->tagName === 'video' && $media->hasAttribute('autoplay')) {
                $details = $this->get_element_details($media);
                
                if ($has_motion_css_handling) {
                    // Video has proper motion-reduction handling, so it's compliant
                    $issues[] = [
                        'type' => 'autoplay_motion_video_handled',
                        'severity' => 'info',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => 'Auto-playing video properly handles motion sensitivity',
                        'description' => 'This auto-playing video is properly configured with CSS to respect users\' motion preferences.',
                        'suggestion' => 'No action needed - video already complies with motion sensitivity guidelines.',
                        'wcag_reference' => 'WCAG 2.1 Level AA - 2.3.3 Animation from Interactions',
                        'how_to_fix' => 'No fix required - proper prefers-reduced-motion handling detected.',
                        'auto_fixable' => true
                    ];
                } else {
                    $issues[] = [
                        'type' => 'autoplay_motion_video',
                        'severity' => 'high',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => 'Auto-playing video may contain motion without user control',
                        'description' => 'Auto-playing videos with motion can trigger vestibular disorders and should provide user controls.',
                        'suggestion' => 'Remove autoplay or provide pause controls and respect prefers-reduced-motion.',
                        'wcag_reference' => 'WCAG 2.1 Level AA - 2.3.3 Animation from Interactions',
                        'how_to_fix' => 'Add CSS: @media (prefers-reduced-motion: reduce) { video[autoplay] { display: none; } }',
                        'auto_fixable' => false
                    ];
                }
            }
        }
    }
    
    /**
     * Check enhanced focus order (WCAG 2.1 AA - 2.4.3)
     */
    private function check_enhanced_focus_order($xpath, &$issues) {
        // Get all focusable elements in DOM order (exclude WordPress admin toolbar)
        $focusable_elements = $xpath->query('//a[@href and not(ancestor::*[@id="wpadminbar"]) and not(contains(@class, "ab-item"))] | //button[not(ancestor::*[@id="wpadminbar"])] | //input[not(@type="hidden") and not(ancestor::*[@id="wpadminbar"])] | //select[not(ancestor::*[@id="wpadminbar"])] | //textarea[not(ancestor::*[@id="wpadminbar"])] | //details[not(ancestor::*[@id="wpadminbar"])] | //*[@tabindex and @tabindex != "-1" and not(ancestor::*[@id="wpadminbar"])]');
        
        $tab_order = [];
        $visual_order_issues = [];
        
        foreach ($focusable_elements as $element) {
            $tabindex = $element->hasAttribute('tabindex') ? intval($element->getAttribute('tabindex')) : 0;
            $position = $this->get_element_details($element)['position'];
            
            $tab_order[] = [
                'element' => $element,
                'tabindex' => $tabindex,
                'position' => $position
            ];
            
            // Check for elements that might be visually out of order
            if ($tabindex > 0) {
                $details = $this->get_element_details($element);
                $issues[] = [
                    'type' => 'positive_tabindex_focus_order',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => "Positive tabindex disrupts natural focus order",
                    'description' => "This element has tabindex=\"{$tabindex}\" which can create an unpredictable focus order that doesn't match the visual layout.",
                    'suggestion' => 'Use tabindex="0" for focusable elements or rely on natural DOM order.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 2.4.3 Focus Order',
                    'how_to_fix' => 'Remove positive tabindex and arrange elements in logical DOM order.',
                    'auto_fixable' => true
                ];
            }
        }
        
        // Check for hidden focusable elements that might trap focus (exclude admin toolbar)
        $hidden_focusable = $xpath->query('//*[(@style and contains(@style, "display: none")) or (@style and contains(@style, "visibility: hidden"))]//a[@href and not(ancestor::*[@id="wpadminbar"])] | //*[(@style and contains(@style, "display: none")) or (@style and contains(@style, "visibility: hidden"))]//button[not(ancestor::*[@id="wpadminbar"])] | //*[(@style and contains(@style, "display: none")) or (@style and contains(@style, "visibility: hidden"))]//input[not(@type="hidden") and not(ancestor::*[@id="wpadminbar"])]');
        
        foreach ($hidden_focusable as $element) {
            if (!$element->hasAttribute('tabindex') || $element->getAttribute('tabindex') !== '-1') {
                $details = $this->get_element_details($element);
                $issues[] = [
                    'type' => 'hidden_focusable_element',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Hidden element is focusable',
                    'description' => 'This focusable element is visually hidden but still accessible via keyboard navigation, creating confusion.',
                    'suggestion' => 'Add tabindex="-1" to hidden focusable elements or ensure proper focus management.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 2.4.3 Focus Order',
                    'how_to_fix' => 'Add tabindex="-1" or use proper visibility management for interactive elements.',
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Check enhanced error identification (WCAG 2.1 AA - 3.3.1)
     */
    private function check_enhanced_error_identification($xpath, &$issues) {
        // Check for form validation that might not be accessible
        $forms = $xpath->query('//form');
        
        foreach ($forms as $form) {
            // Check for inputs with validation attributes but no error messaging
            $validated_inputs = $xpath->query('.//input[@required] | .//input[@pattern] | .//input[@min] | .//input[@max]', $form);
            
            foreach ($validated_inputs as $input) {
                $input_name = $input->getAttribute('name') ?: $input->getAttribute('id') ?: 'unnamed';
                $has_error_message = false;
                
                // Check for associated error message elements
                if ($input->hasAttribute('aria-describedby')) {
                    $describedby_ids = explode(' ', $input->getAttribute('aria-describedby'));
                    foreach ($describedby_ids as $id) {
                        $error_element = $xpath->query("//*[@id='{$id}']")->item(0);
                        if ($error_element && (
                            strpos(strtolower($error_element->getAttribute('class')), 'error') !== false ||
                            $error_element->hasAttribute('role') && $error_element->getAttribute('role') === 'alert'
                        )) {
                            $has_error_message = true;
                            break;
                        }
                    }
                }
                
                if (!$has_error_message) {
                    $details = $this->get_element_details($input);
                    $issues[] = [
                        'type' => 'validation_no_error_message',
                        'severity' => 'medium',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => "Form validation present but no accessible error messaging for {$input_name}",
                        'description' => 'This input has validation constraints but lacks accessible error messaging for when validation fails.',
                        'suggestion' => 'Add aria-describedby pointing to error message elements with role="alert".',
                        'wcag_reference' => 'WCAG 2.1 Level AA - 3.3.1 Error Identification',
                        'how_to_fix' => 'Create error message element and link with aria-describedby="error-msg-id".',
                        'auto_fixable' => true
                    ];
                }
            }
        }
        
        // Check for generic error messages without specific field association
        $generic_errors = $xpath->query('//*[contains(@class, "error") and not(@aria-describedby) and not(@for)]');
        foreach ($generic_errors as $error) {
            $error_text = trim($error->textContent);
            if (!empty($error_text) && strlen($error_text) > 10) {
                $details = $this->get_element_details($error);
                $issues[] = [
                    'type' => 'generic_error_message',
                    'severity' => 'low',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Generic error message not associated with specific fields',
                    'description' => "This error message (\"{$error_text}\") is not programmatically associated with the specific form fields that have errors.",
                    'suggestion' => 'Associate error messages with specific fields using aria-describedby or for attributes.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 3.3.1 Error Identification',
                    'how_to_fix' => 'Link error messages to specific fields or provide field-specific error messages.',
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Check target size compliance (WCAG 2.1 AA - 2.5.5)
     */
    private function check_target_size_compliance($xpath, &$issues) {
        // Check for small interactive elements that might be hard to tap/click (exclude admin toolbar)
        $interactive_elements = $xpath->query('//a[@href and not(ancestor::*[@id="wpadminbar"])] | //button[not(ancestor::*[@id="wpadminbar"])] | //input[@type="button" and not(ancestor::*[@id="wpadminbar"])] | //input[@type="submit" and not(ancestor::*[@id="wpadminbar"])] | //input[@type="checkbox" and not(ancestor::*[@id="wpadminbar"])] | //input[@type="radio" and not(ancestor::*[@id="wpadminbar"])] | //*[@onclick and not(ancestor::*[@id="wpadminbar"])] | //*[@role="button" and not(ancestor::*[@id="wpadminbar"])]');
        
        foreach ($interactive_elements as $element) {
            // Get element's computed size from style attribute (approximation)
            $style = $element->getAttribute('style');
            $classes = $element->getAttribute('class');
            
            // Check for explicitly small sizes
            if (preg_match('/width\s*:\s*([0-9]+)px/i', $style, $width_match) ||
                preg_match('/height\s*:\s*([0-9]+)px/i', $style, $height_match)) {
                
                $width = isset($width_match[1]) ? intval($width_match[1]) : 44;
                $height = isset($height_match[1]) ? intval($height_match[1]) : 44;
                
                if ($width < 44 || $height < 44) {
                    $details = $this->get_element_details($element);
                    $issues[] = [
                        'type' => 'small_target_size',
                        'severity' => 'medium',
                        'element' => $details['selector'],
                        'element_details' => $details,
                        'message' => "Interactive target smaller than minimum 44px",
                        'description' => "This interactive element appears to be smaller than the minimum 44x44 pixel target size, making it difficult for users with motor impairments to activate.",
                        'suggestion' => 'Ensure interactive targets are at least 44x44 pixels or provide adequate spacing.',
                        'wcag_reference' => 'WCAG 2.1 Level AA - 2.5.5 Target Size',
                        'how_to_fix' => 'Increase target size to minimum 44x44px or add sufficient padding.',
                        'auto_fixable' => true
                    ];
                }
            }
            
            // Check for common small element indicators
            if (strpos(strtolower($classes), 'small') !== false || 
                strpos(strtolower($classes), 'tiny') !== false ||
                strpos(strtolower($classes), 'xs') !== false) {
                
                $details = $this->get_element_details($element);
                $issues[] = [
                    'type' => 'potentially_small_target',
                    'severity' => 'low',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Interactive element may have small target size',
                    'description' => 'This element has CSS classes suggesting a small size. Verify it meets minimum 44x44 pixel target size requirements.',
                    'suggestion' => 'Check that this interactive element is at least 44x44 pixels in size.',
                    'wcag_reference' => 'WCAG 2.1 Level AA - 2.5.5 Target Size',
                    'how_to_fix' => 'Ensure minimum 44x44px size or adequate spacing between targets.',
                    'auto_fixable' => false
                ];
            }
        }
    }
    
    /**
     * Check input purpose identification (WCAG 2.1 AA - 1.3.5)
     */
    private function check_input_purpose_identification($xpath, &$issues) {
        // Define common input purposes that should have autocomplete attributes
        $purpose_patterns = [
            'name' => ['name', 'full-name', 'fullname', 'your-name'],
            'given-name' => ['first-name', 'firstname', 'given-name', 'fname'],
            'family-name' => ['last-name', 'lastname', 'family-name', 'surname', 'lname'],
            'email' => ['email', 'e-mail', 'mail'],
            'tel' => ['phone', 'telephone', 'tel', 'mobile', 'cell'],
            'organization' => ['company', 'organization', 'organisation', 'business'],
            'street-address' => ['address', 'street', 'street-address'],
            'address-line1' => ['address1', 'address-1', 'addr1'],
            'address-line2' => ['address2', 'address-2', 'addr2'],
            'locality' => ['city', 'town', 'locality'],
            'region' => ['state', 'province', 'region'],
            'postal-code' => ['zip', 'postal', 'postcode', 'postal-code'],
            'country' => ['country'],
            'bday' => ['birthday', 'birthdate', 'dob', 'date-of-birth'],
            'sex' => ['gender', 'sex'],
            'url' => ['website', 'url', 'homepage'],
            'username' => ['username', 'user-name', 'login'],
            'new-password' => ['password', 'pass', 'pwd'],
            'current-password' => ['current-password', 'old-password']
        ];
        
        $input_elements = $xpath->query('//input[@type="text"] | //input[@type="email"] | //input[@type="tel"] | //input[@type="url"] | //input[@type="password"] | //input[not(@type)]');
        
        foreach ($input_elements as $input) {
            $name = strtolower($input->getAttribute('name') ?: '');
            $id = strtolower($input->getAttribute('id') ?: '');
            $placeholder = strtolower($input->getAttribute('placeholder') ?: '');
            $autocomplete = $input->getAttribute('autocomplete');
            
            // Find potential purpose match
            $suggested_purpose = null;
            foreach ($purpose_patterns as $purpose => $patterns) {
                foreach ($patterns as $pattern) {
                    if (strpos($name, $pattern) !== false || 
                        strpos($id, $pattern) !== false || 
                        strpos($placeholder, $pattern) !== false) {
                        $suggested_purpose = $purpose;
                        break 2;
                    }
                }
            }
            
            // If we found a match but no autocomplete attribute
            if ($suggested_purpose && empty($autocomplete)) {
                $field_identifier = $name ?: $id ?: 'unnamed';
                $details = $this->get_element_details($input);
                
                $issues[] = [
                    'type' => 'missing_autocomplete_attribute',
                    'severity' => 'medium',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => "Input field '{$field_identifier}' missing autocomplete attribute",
                    'description' => "This input field appears to collect {$suggested_purpose} information but lacks an autocomplete attribute to help users with auto-filling.",
                    'suggestion' => "Add autocomplete=\"{$suggested_purpose}\" to help users complete forms more easily.",
                    'wcag_reference' => 'WCAG 2.1 Level AA - 1.3.5 Identify Input Purpose',
                    'how_to_fix' => "Add autocomplete=\"{$suggested_purpose}\" attribute to the input element.",
                    'auto_fixable' => true
                ];
            }
        }
    }
    
    /**
     * Add compliance metadata to an issue array
     */
    private function add_compliance_metadata($issue, $wcag_criterion, $wcag_level, $compliance_impact) {
        $issue['wcag_criterion'] = $wcag_criterion;
        $issue['wcag_level'] = $wcag_level;
        $issue['compliance_impact'] = $compliance_impact;
        return $issue;
    }
    
    /**
     * Get WCAG 2.1 success criteria that require manual testing
     */
    public function get_manual_testing_criteria() {
        return [
            // Level A criteria requiring manual testing
            '1.2.1' => 'Audio-only and Video-only (Prerecorded)',
            '1.2.2' => 'Captions (Prerecorded)', 
            '1.2.3' => 'Audio Description or Media Alternative (Prerecorded)',
            '1.4.2' => 'Audio Control',
            '2.1.1' => 'Keyboard',
            '2.1.2' => 'No Keyboard Trap',
            '2.2.1' => 'Timing Adjustable',
            '2.2.2' => 'Pause, Stop, Hide',
            '2.3.1' => 'Three Flashes or Below Threshold',
            '2.4.1' => 'Bypass Blocks (partial - need manual verification)',
            '2.4.2' => 'Page Titled',
            '3.1.1' => 'Language of Page (partial - need manual verification)',
            '3.2.1' => 'On Focus',
            '3.2.2' => 'On Input',
            '3.3.1' => 'Error Identification (partial)',
            '3.3.2' => 'Labels or Instructions (partial)',
            
            // Level AA criteria requiring manual testing
            '1.2.4' => 'Captions (Live)',
            '1.2.5' => 'Audio Description (Prerecorded)',
            '1.4.3' => 'Contrast (Minimum) - requires manual verification',
            '1.4.4' => 'Resize text (partial - browser testing needed)',
            '1.4.5' => 'Images of Text',
            '2.4.5' => 'Multiple Ways',
            '2.4.6' => 'Headings and Labels (partial)',
            '2.4.7' => 'Focus Visible',
            '3.1.2' => 'Language of Parts',
            '3.2.3' => 'Consistent Navigation',
            '3.2.4' => 'Consistent Identification',
            '3.3.3' => 'Error Suggestion',
            '3.3.4' => 'Error Prevention (Legal, Financial, Data)'
        ];
    }
    
    /**
     * Get WCAG 2.1 success criteria that can be automatically tested
     */
    public function get_automated_testing_criteria() {
        return [
            // Level A
            '1.1.1' => 'Non-text Content',
            '1.3.1' => 'Info and Relationships (partial)',
            '1.3.2' => 'Meaningful Sequence (partial)', 
            '1.3.3' => 'Sensory Characteristics (partial)',
            '1.4.1' => 'Use of Color (partial)',
            '2.4.3' => 'Focus Order (partial)',
            '2.4.4' => 'Link Purpose (In Context)',
            '4.1.1' => 'Parsing',
            '4.1.2' => 'Name, Role, Value',
            
            // Level AA  
            '1.3.4' => 'Orientation (partial)',
            '1.3.5' => 'Identify Input Purpose',
            '1.4.10' => 'Reflow (partial)',
            '1.4.11' => 'Non-text Contrast (partial)',
            '1.4.12' => 'Text Spacing',
            '1.4.13' => 'Content on Hover or Focus (partial)',
            '2.5.1' => 'Pointer Gestures (partial)',
            '2.5.2' => 'Pointer Cancellation (partial)',
            '2.5.3' => 'Label in Name (partial)',
            '2.5.4' => 'Motion Actuation (partial)'
        ];
    }
    
    /**
     * Calculate WCAG 2.1 AA coverage percentage
     */
    public function calculate_coverage_percentage($issues) {
        $automated_criteria = array_keys($this->get_automated_testing_criteria());
        $manual_criteria = array_keys($this->get_manual_testing_criteria());
        $total_criteria = count($automated_criteria) + count($manual_criteria);
        
        // Count criteria that were tested (appear in issues or are implicitly tested)
        $tested_criteria = [];
        foreach ($issues as $issue) {
            if (!empty($issue['wcag_criterion'])) {
                $tested_criteria[$issue['wcag_criterion']] = true;
            }
        }
        
        $automated_coverage = count($tested_criteria);
        $manual_coverage = 0; // Manual criteria require manual testing
        
        return [
            'automated_coverage' => round(($automated_coverage / count($automated_criteria)) * 100),
            'total_coverage' => round((($automated_coverage + $manual_coverage) / $total_criteria) * 100),
            'automated_criteria_tested' => count($tested_criteria),
            'automated_criteria_total' => count($automated_criteria),
            'manual_criteria_total' => count($manual_criteria),
            'requires_manual_testing' => $manual_criteria
        ];
    }
}
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
     * Constructor
     */
    public function __construct() {
        // Initialize checker functionality
    }
    
    /**
     * Run accessibility check on content
     */
    public function check_content($content) {
        $issues = [];
        
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
                'how_to_fix' => 'Add alt attribute to the img element: <img src="..." alt="description of image">'
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
                    'how_to_fix' => 'Add text content to the heading: <' . $heading->tagName . '>Meaningful heading text</' . $heading->tagName . '>'
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
                    'how_to_fix' => $input_type === 'image' ?
                        'Add alt="Submit" or more descriptive text to the input element' :
                        ($id ? "Add: <label for=\"{$id}\">Field Description</label>" : 
                        'Add an id to the input and create: <label for="field-id">Field Description</label>')
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
        
        return $issues;
    }
    
    /**
     * Get detailed element information
     */
    private function get_element_details($element) {
        $selector = $element->tagName;
        
        if ($element->hasAttribute('id')) {
            $selector .= '#' . $element->getAttribute('id');
        } elseif ($element->hasAttribute('class')) {
            $classes = explode(' ', $element->getAttribute('class'));
            $selector .= '.' . implode('.', array_filter($classes));
        }
        
        // Get element's outer HTML (truncated for display)
        $html_snippet = $element->ownerDocument->saveHTML($element);
        if (strlen($html_snippet) > 200) {
            $html_snippet = substr($html_snippet, 0, 200) . '...';
        }
        
        // Get text content (truncated)
        $text_content = trim($element->textContent);
        if (strlen($text_content) > 100) {
            $text_content = substr($text_content, 0, 100) . '...';
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
     * Check color contrast
     */
    private function check_color_contrast($xpath, &$issues) {
        // This is a simplified check - real contrast checking would require
        // computing actual color values and contrast ratios
        
        $elements = $xpath->query('//*[@style]');
        foreach ($elements as $element) {
            $style = $element->getAttribute('style');
            
            // Check for low contrast indicators
            if (preg_match('/color:\s*#[cdef]/i', $style) && preg_match('/background-color:\s*#[cdef]/i', $style)) {
                $issues[] = [
                    'type' => 'low_contrast',
                    'severity' => 'medium',
                    'element' => $this->get_element_selector($element),
                    'message' => 'Potential low color contrast'
                ];
            }
        }
    }
    
    /**
     * Generate report
     */
    public function generate_report($url = '') {
        if (empty($url)) {
            $url = home_url();
        }
        
        // Fetch page content with SSL verification disabled for local development
        $args = [
            'timeout' => 30,
            'sslverify' => false, // Disable SSL verification for local sites
            'headers' => [
                'User-Agent' => 'RayWP Accessibility Scanner'
            ]
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return ['error' => 'Failed to fetch page: ' . $response->get_error_message()];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return ['error' => 'HTTP ' . $response_code . ' error'];
        }
        
        $content = wp_remote_retrieve_body($response);
        $issues = $this->check_content($content);
        
        return [
            'url' => $url,
            'issues' => $issues,
            'total_issues' => count($issues),
            'timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Check keyboard accessibility
     */
    private function check_keyboard_accessibility($xpath, &$issues) {
        // Check for interactive elements without proper keyboard access
        $interactive_elements = $xpath->query('//div[@onclick] | //span[@onclick] | //img[@onclick]');
        foreach ($interactive_elements as $element) {
            if (!$element->hasAttribute('tabindex') && !$element->hasAttribute('role')) {
                $details = $this->get_element_details($element);
                $onclick = $element->getAttribute('onclick');
                
                $issues[] = [
                    'type' => 'keyboard_inaccessible',
                    'severity' => 'high',
                    'element' => $details['selector'],
                    'element_details' => $details,
                    'message' => 'Interactive element not keyboard accessible',
                    'description' => "This {$element->tagName} element has click functionality but cannot be accessed via keyboard, making it unusable for keyboard-only users.",
                    'suggestion' => 'Add tabindex="0" and appropriate keyboard event handlers (onkeydown/onkeyup), or use a proper button/link element instead.',
                    'wcag_reference' => 'WCAG 2.1 Level A - 2.1.1 Keyboard',
                    'how_to_fix' => 'Replace with <button> or add: tabindex="0" and onkeydown="if(event.key===\'Enter\'||event.key===\' \'){/* action */}"'
                ];
            }
        }
        
        // Check for missing skip links
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
                'how_to_fix' => "Add at the start of <body>: <a href=\"{$suggestion_target}\" class=\"skip-link\">Skip to main content</a>"
            ];
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
                        $issues[] = [
                            'type' => 'invalid_aria',
                            'severity' => 'medium',
                            'element' => $this->get_element_selector($element),
                            'message' => 'Invalid ARIA attribute: ' . $attr->name
                        ];
                    }
                }
            }
        }
        
        // Check for required ARIA properties
        $buttons = $xpath->query('//button[@aria-expanded and not(@aria-controls)]');
        foreach ($buttons as $button) {
            $issues[] = [
                'type' => 'missing_aria_controls',
                'severity' => 'medium',
                'element' => $this->get_element_selector($button),
                'message' => 'Button with aria-expanded missing aria-controls'
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
                'how_to_fix' => 'Wrap your main content in <main>content here</main> or add role="main" to your content container.'
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
                        'how_to_fix' => "Change <{$heading->tagName}> to <h" . ($previous_level + 1) . "> or add missing heading levels in between."
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
                    'how_to_fix' => 'Keep the most important h1 and change others to appropriate heading levels (h2, h3, etc.)'
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
                'how_to_fix' => 'Replace <td> elements in header row with <th> elements: <th>Header 1</th><th>Header 2</th>'
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
                        'how_to_fix' => '<fieldset><legend>Group Description</legend><!-- radio buttons here --></fieldset>'
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
                'how_to_fix' => 'Add aria-required="true" to the input element alongside the required attribute.'
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
                    'how_to_fix' => 'Add role="alert" and optionally aria-live="polite" to the error message element.'
                ];
            }
        }
    }
}
<?php
/**
 * DOM Processor - Processes entire page output
 */

namespace RayWP\Accessibility\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Dom_Processor {
    
    /**
     * ARIA Manager instance
     */
    private $aria_manager;
    
    /**
     * Settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Don't get plugin instance here to avoid circular dependency
        // ARIA manager will be injected when needed
        $this->settings = get_option('raywp_accessibility_settings', []);
    }
    
    /**
     * Set ARIA manager (dependency injection)
     */
    public function set_aria_manager($aria_manager) {
        $this->aria_manager = $aria_manager;
    }
    
    /**
     * Process output buffer
     */
    public function process_output($html) {
        // Don't process if disabled
        if (empty($this->settings['enable_aria'])) {
            return $html;
        }
        
        // Don't process admin pages or AJAX requests
        if (is_admin() || wp_doing_ajax()) {
            return $html;
        }
        
        // Start performance monitoring
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        // Debug logging (remove in production)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RayWP Accessibility: Processing page output');
        }
        
        // Apply ARIA attributes if manager is available
        if ($this->aria_manager) {
            $html = $this->aria_manager->apply_aria_to_html($html);
        }
        
        // Apply other accessibility fixes
        $html = $this->apply_accessibility_fixes($html);
        
        // Accessibility checker widget disabled - use admin scanner instead
        // if (!empty($this->settings['enable_checker'])) {
        //     $html = $this->inject_accessibility_checker($html);
        // }
        
        // Log performance metrics
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        $processing_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        $memory_used = ($end_memory - $start_memory) / 1024 / 1024; // Convert to MB
        
        // Store performance data for monitoring
        $this->log_performance_metrics($processing_time, $memory_used);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'RayWP Accessibility: Page processed in %.2f ms, using %.2f MB',
                $processing_time,
                $memory_used
            ));
        }
        
        return $html;
    }
    
    /**
     * Apply general accessibility fixes
     */
    private function apply_accessibility_fixes($html) {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        
        // Load HTML
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        $modified = false;
        
        // Fix images without alt attributes
        if (!empty($this->settings['fix_empty_alt'])) {
            $images = $xpath->query('//img[not(@alt)]');
            foreach ($images as $img) {
                $img->setAttribute('alt', '');
                $modified = true;
            }
        }
        
        // Add lang attribute if missing
        if (!empty($this->settings['fix_lang_attr'])) {
            $html_elements = $xpath->query('//html[not(@lang)]');
            foreach ($html_elements as $html_elem) {
                $html_elem->setAttribute('lang', get_locale());
                $modified = true;
            }
        }
        
        // Fix form labels
        if (!empty($this->settings['fix_form_labels'])) {
            $this->fix_form_labels($xpath);
            $modified = true;
        }
        
        // Apply form accessibility fixes
        if (!empty($this->settings['fix_forms'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Applying form fixes');
            }
            $this->apply_form_fixes($xpath);
            $modified = true;
        }
        
        // Add skip links
        if (!empty($this->settings['add_skip_links'])) {
            $this->add_skip_links($dom, $xpath);
            $modified = true;
        }
        
        // Add main landmark if missing
        if (!empty($this->settings['add_main_landmark'])) {
            $this->add_main_landmark($dom, $xpath);
            $modified = true;
        }
        
        // Fix heading hierarchy
        if (!empty($this->settings['fix_heading_hierarchy'])) {
            $this->fix_heading_hierarchy($xpath);
            $modified = true;
        }
        
        // Fix missing aria-controls on expandable buttons (enabled by default)
        if (!isset($this->settings['fix_aria_controls']) || !empty($this->settings['fix_aria_controls'])) {
            $this->fix_aria_controls($xpath);
            $modified = true;
        }
        
        // Fix empty headings from plugins (enabled by default)
        if (!isset($this->settings['fix_empty_headings']) || !empty($this->settings['fix_empty_headings'])) {
            $this->fix_empty_headings($xpath);
            $modified = true;
        }
        
        // Fix Lighthouse-specific accessibility issues (enabled by default)
        if (!isset($this->settings['fix_lighthouse_issues']) || !empty($this->settings['fix_lighthouse_issues'])) {
            $this->fix_lighthouse_issues($xpath);
            $modified = true;
        }
        
        if ($modified) {
            $html = $dom->saveHTML();
            $html = str_replace('<?xml encoding="UTF-8">', '', $html);
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Applied accessibility fixes to page');
            }
        }
        
        return $html;
    }
    
    /**
     * Fix form labels
     */
    private function fix_form_labels($xpath) {
        // Find input elements without labels
        $inputs = $xpath->query('//input[@type!="hidden" and @type!="submit" and @type!="button"][not(@aria-label or @aria-labelledby)]');
        
        foreach ($inputs as $input) {
            $id = $input->getAttribute('id');
            
            if (empty($id)) {
                // Generate ID if missing
                $id = 'raywp-input-' . wp_generate_password(8, false);
                $input->setAttribute('id', $id);
            }
            
            // Check if label exists
            $labels = $xpath->query("//label[@for='$id']");
            
            if ($labels->length === 0) {
                // Try to find placeholder or name
                $placeholder = $input->getAttribute('placeholder');
                $name = $input->getAttribute('name');
                
                if (!empty($placeholder)) {
                    $input->setAttribute('aria-label', $placeholder);
                } elseif (!empty($name)) {
                    // Clean up name for label
                    $label = ucfirst(str_replace(['_', '-'], ' ', $name));
                    $input->setAttribute('aria-label', $label);
                }
            }
        }
        
        // Handle select elements
        $selects = $xpath->query('//select[not(@aria-label or @aria-labelledby)]');
        foreach ($selects as $select) {
            $id = $select->getAttribute('id');
            if (!empty($id)) {
                $labels = $xpath->query("//label[@for='$id']");
                if ($labels->length === 0) {
                    $name = $select->getAttribute('name');
                    if (!empty($name)) {
                        $label = ucfirst(str_replace(['_', '-'], ' ', $name));
                        $select->setAttribute('aria-label', $label);
                    }
                }
            }
        }
        
        // Handle textareas
        $textareas = $xpath->query('//textarea[not(@aria-label or @aria-labelledby)]');
        foreach ($textareas as $textarea) {
            $id = $textarea->getAttribute('id');
            if (!empty($id)) {
                $labels = $xpath->query("//label[@for='$id']");
                if ($labels->length === 0) {
                    $placeholder = $textarea->getAttribute('placeholder');
                    $name = $textarea->getAttribute('name');
                    
                    if (!empty($placeholder)) {
                        $textarea->setAttribute('aria-label', $placeholder);
                    } elseif (!empty($name)) {
                        $label = ucfirst(str_replace(['_', '-'], ' ', $name));
                        $textarea->setAttribute('aria-label', $label);
                    }
                }
            }
        }
    }
    
    /**
     * Add skip links
     */
    private function add_skip_links($dom, $xpath) {
        // Find body element
        $body = $xpath->query('//body')->item(0);
        
        if (!$body) {
            return;
        }
        
        // Create skip link container
        $skip_container = $dom->createElement('div');
        $skip_container->setAttribute('class', 'raywp-skip-links screen-reader-text');
        
        // Add skip to main content
        $main_elements = $xpath->query('//main | //*[@role="main"] | //*[@id="main"] | //*[@id="content"]');
        if ($main_elements->length > 0) {
            $main_elem = $main_elements->item(0);
            $main_id = $main_elem->getAttribute('id');
            
            if (empty($main_id)) {
                $main_id = 'raywp-main-content';
                $main_elem->setAttribute('id', $main_id);
            }
            
            $skip_link = $dom->createElement('a', __('Skip to main content', 'raywp-accessibility'));
            $skip_link->setAttribute('href', '#' . $main_id);
            $skip_link->setAttribute('class', 'raywp-skip-link');
            $skip_container->appendChild($skip_link);
        }
        
        // Add skip to navigation if exists
        $nav_elements = $xpath->query('//nav | //*[@role="navigation"]');
        if ($nav_elements->length > 0) {
            $nav_elem = $nav_elements->item(0);
            $nav_id = $nav_elem->getAttribute('id');
            
            if (empty($nav_id)) {
                $nav_id = 'raywp-navigation';
                $nav_elem->setAttribute('id', $nav_id);
            }
            
            $skip_link = $dom->createElement('a', __('Skip to navigation', 'raywp-accessibility'));
            $skip_link->setAttribute('href', '#' . $nav_id);
            $skip_link->setAttribute('class', 'raywp-skip-link');
            $skip_container->appendChild($skip_link);
        }
        
        // Insert at the beginning of body
        if ($skip_container->hasChildNodes() && $body->firstChild) {
            $body->insertBefore($skip_container, $body->firstChild);
        }
    }
    
    /**
     * Inject accessibility checker
     */
    private function inject_accessibility_checker($html) {
        $checker_html = '<div id="raywp-checker-container"></div>';
        
        // Inject before closing body tag
        $html = str_replace('</body>', $checker_html . '</body>', $html);
        
        return $html;
    }
    
    /**
     * Apply comprehensive form accessibility fixes
     */
    private function apply_form_fixes($xpath) {
        // Fix missing fieldsets for radio/checkbox groups
        $this->add_fieldsets_to_groups($xpath);
        
        // Add required indicators
        $this->add_required_indicators($xpath);
        
        // Fix form validation messages
        $this->improve_form_validation($xpath);
        
        // Add form instructions
        $this->add_form_instructions($xpath);
    }
    
    /**
     * Add fieldsets to radio/checkbox groups
     */
    private function add_fieldsets_to_groups($xpath) {
        // Find radio button groups
        $radio_groups = [];
        $radios = $xpath->query('//input[@type="radio"]');
        
        foreach ($radios as $radio) {
            $name = $radio->getAttribute('name');
            if ($name && !isset($radio_groups[$name])) {
                $radio_groups[$name] = [];
            }
            if ($name) {
                $radio_groups[$name][] = $radio;
            }
        }
        
        // Wrap each group in fieldset if not already wrapped
        foreach ($radio_groups as $name => $radios) {
            if (count($radios) > 1) {
                $first_radio = $radios[0];
                
                // Check if already in a fieldset
                $fieldset = $xpath->query('.//ancestor::fieldset', $first_radio);
                if ($fieldset->length === 0) {
                    $this->wrap_in_fieldset($radios, ucfirst(str_replace(['_', '-'], ' ', $name)));
                }
            }
        }
        
        // Do the same for checkbox groups (if they share a name pattern)
        $checkbox_groups = [];
        $checkboxes = $xpath->query('//input[@type="checkbox"]');
        
        foreach ($checkboxes as $checkbox) {
            $name = $checkbox->getAttribute('name');
            if ($name && strpos($name, '[]') !== false) {
                $base_name = str_replace('[]', '', $name);
                if (!isset($checkbox_groups[$base_name])) {
                    $checkbox_groups[$base_name] = [];
                }
                $checkbox_groups[$base_name][] = $checkbox;
            }
        }
        
        foreach ($checkbox_groups as $name => $checkboxes) {
            if (count($checkboxes) > 1) {
                $first_checkbox = $checkboxes[0];
                $fieldset = $xpath->query('.//ancestor::fieldset', $first_checkbox);
                if ($fieldset->length === 0) {
                    $this->wrap_in_fieldset($checkboxes, ucfirst(str_replace(['_', '-'], ' ', $name)));
                }
            }
        }
    }
    
    /**
     * Wrap form elements in fieldset
     */
    private function wrap_in_fieldset($elements, $legend_text) {
        if (empty($elements)) return;
        
        $doc = $elements[0]->ownerDocument;
        $fieldset = $doc->createElement('fieldset');
        $legend = $doc->createElement('legend', $legend_text);
        $fieldset->appendChild($legend);
        
        // Find common parent
        $parent = $elements[0]->parentNode;
        
        // Insert fieldset before first element
        $parent->insertBefore($fieldset, $elements[0]);
        
        // Move all elements into fieldset
        foreach ($elements as $element) {
            // Also move associated labels
            $id = $element->getAttribute('id');
            if ($id) {
                $labels = $element->ownerDocument->getElementsByTagName('label');
                foreach ($labels as $label) {
                    if ($label->getAttribute('for') === $id) {
                        $fieldset->appendChild($label);
                        break;
                    }
                }
            }
            $fieldset->appendChild($element);
        }
    }
    
    /**
     * Add required field indicators
     */
    private function add_required_indicators($xpath) {
        $required_fields = $xpath->query('//input[@required] | //textarea[@required] | //select[@required]');
        
        foreach ($required_fields as $field) {
            // Add aria-required if missing
            if (!$field->hasAttribute('aria-required')) {
                $field->setAttribute('aria-required', 'true');
            }
            
            // Add visual indicator to associated label
            $id = $field->getAttribute('id');
            if ($id) {
                $labels = $xpath->query("//label[@for='$id']");
                foreach ($labels as $label) {
                    // Check if asterisk already exists
                    $asterisk_check = $xpath->query('.//span[contains(@class, "required")] | .//*[contains(text(), "*")]', $label);
                    if ($asterisk_check->length === 0) {
                        $asterisk = $label->ownerDocument->createElement('span', ' *');
                        $asterisk->setAttribute('class', 'required-indicator');
                        $asterisk->setAttribute('aria-hidden', 'true');
                        $label->appendChild($asterisk);
                    }
                }
            }
        }
    }
    
    /**
     * Improve form validation messages
     */
    private function improve_form_validation($xpath) {
        // Find elements that might be validation messages
        $error_elements = $xpath->query('//*[contains(@class, "error") or contains(@class, "invalid") or contains(@class, "validation")]');
        
        foreach ($error_elements as $error) {
            $text = trim($error->textContent);
            if (!empty($text)) {
                // Make sure it has proper ARIA attributes
                if (!$error->hasAttribute('role')) {
                    $error->setAttribute('role', 'alert');
                }
                
                if (!$error->hasAttribute('aria-live')) {
                    $error->setAttribute('aria-live', 'polite');
                }
                
                // Try to associate with nearby form field
                $nearby_inputs = $xpath->query('.//preceding-sibling::input[1] | .//following-sibling::input[1] | .//parent::*//input', $error);
                foreach ($nearby_inputs as $input) {
                    $input_id = $input->getAttribute('id');
                    if ($input_id && !$input->hasAttribute('aria-describedby')) {
                        $error_id = 'error-' . $input_id;
                        $error->setAttribute('id', $error_id);
                        $input->setAttribute('aria-describedby', $error_id);
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Add form instructions
     */
    private function add_form_instructions($xpath) {
        $forms = $xpath->query('//form');
        
        foreach ($forms as $form) {
            // Check if form already has instructions
            $existing_instructions = $xpath->query('.//p[contains(@class, "form-instructions")] | .//*[contains(@class, "description")]', $form);
            
            if ($existing_instructions->length === 0) {
                // Look for required fields in this form
                $required_fields = $xpath->query('.//input[@required] | .//textarea[@required] | .//select[@required]', $form);
                
                if ($required_fields->length > 0) {
                    $instructions = $form->ownerDocument->createElement('p', 'Fields marked with * are required.');
                    $instructions->setAttribute('class', 'form-instructions');
                    $instructions->setAttribute('id', 'form-instructions-' . uniqid());
                    
                    // Add to beginning of form
                    if ($form->firstChild) {
                        $form->insertBefore($instructions, $form->firstChild);
                    } else {
                        $form->appendChild($instructions);
                    }
                    
                    // Associate with form using aria-describedby
                    $form->setAttribute('aria-describedby', $instructions->getAttribute('id'));
                }
            }
        }
    }
    
    /**
     * Add main landmark if missing
     */
    private function add_main_landmark($dom, $xpath) {
        // Check if main element or role="main" already exists
        $main_elements = $xpath->query('//main | //*[@role="main"]');
        
        if ($main_elements->length === 0) {
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Adding main landmark');
            }
            
            // Find content area to wrap in main - expanded selectors
            $content_selectors = [
                // Common WordPress selectors
                '//div[@id="content"]',
                '//div[@id="main"]',
                '//div[@id="primary"]',
                '//div[@class="content"]',
                '//div[contains(@class, "site-content")]',
                '//div[contains(@class, "main-content")]',
                '//div[contains(@class, "page-content")]',
                '//div[contains(@class, "entry-content")]',
                
                // Page builders
                '//div[contains(@class, "elementor-section-wrap")]',
                '//div[contains(@class, "fl-builder-content")]',
                '//div[contains(@class, "et_builder_inner_content")]',
                '//div[contains(@class, "breakdance")][@data-type="section"]',
                
                // Article containers
                '//article',
                '//div[contains(@class, "post")]',
                '//div[contains(@class, "page")]',
                
                // Generic containers
                '//div[@class="container"]//div[contains(@class, "content")]',
                '//div[@class="wrapper"]//div[contains(@class, "content")]',
                
                // More aggressive - find the main content wrapper after header
                '//header/following-sibling::div[1]',
                '//div[contains(@class, "header")]/following-sibling::div[1]'
            ];
            
            $content_element = null;
            foreach ($content_selectors as $selector) {
                $elements = $xpath->query($selector);
                if ($elements->length > 0) {
                    $content_element = $elements->item(0);
                    // Skip if it's a header, nav, or footer
                    $tag = strtolower($content_element->tagName);
                    $class = $content_element->getAttribute('class');
                    if ($tag === 'header' || $tag === 'nav' || $tag === 'footer' ||
                        strpos($class, 'header') !== false || 
                        strpos($class, 'navigation') !== false ||
                        strpos($class, 'footer') !== false) {
                        continue;
                    }
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('RayWP Accessibility: Found content element with selector: ' . $selector);
                    }
                    break;
                }
            }
            
            // If still not found, try a different approach - add role="main" to most likely candidate
            if (!$content_element) {
                // Find the largest content div between header and footer
                $candidate_divs = $xpath->query('//body/div[not(contains(@class, "header") and not(contains(@class, "footer")))]');
                $largest_div = null;
                $largest_size = 0;
                
                foreach ($candidate_divs as $div) {
                    // Count child elements as a proxy for content size
                    $children = $xpath->query('.//*', $div);
                    if ($children->length > $largest_size) {
                        $largest_size = $children->length;
                        $largest_div = $div;
                    }
                }
                
                if ($largest_div && $largest_size > 10) { // Minimum complexity threshold
                    $content_element = $largest_div;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('RayWP Accessibility: Using largest content div as main');
                    }
                }
            }
            
            if ($content_element) {
                // Instead of wrapping (which can break layouts), add role="main"
                $content_element->setAttribute('role', 'main');
                
                // Also add an ID if it doesn't have one
                if (!$content_element->hasAttribute('id')) {
                    $content_element->setAttribute('id', 'main-content');
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Added role="main" to content element');
                }
            } else {
                // Last resort - create a main element after the header
                $body = $xpath->query('//body')->item(0);
                if ($body) {
                    $headers = $xpath->query('//header | //div[contains(@class, "header")]');
                    if ($headers->length > 0) {
                        $header = $headers->item(0);
                        $main = $dom->createElement('main');
                        $main->setAttribute('id', 'main-content');
                        $main->setAttribute('class', 'raywp-main-landmark');
                        
                        // Insert after header
                        if ($header->nextSibling) {
                            $body->insertBefore($main, $header->nextSibling);
                        } else {
                            $body->appendChild($main);
                        }
                        
                        // Move all content after header into main (except footer)
                        $node = $main->nextSibling;
                        while ($node) {
                            $next = $node->nextSibling;
                            if ($node->nodeType === XML_ELEMENT_NODE) {
                                $tag = strtolower($node->tagName);
                                $class = $node->getAttribute ? $node->getAttribute('class') : '';
                                if ($tag === 'footer' || strpos($class, 'footer') !== false) {
                                    break;
                                }
                                $main->appendChild($node);
                            }
                            $node = $next;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Fix heading hierarchy issues
     */
    private function fix_heading_hierarchy($xpath) {
        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $previous_level = 0;
        
        foreach ($headings as $heading) {
            $current_level = intval(substr($heading->tagName, 1));
            
            // Fix skipped levels using aria-level
            if ($previous_level > 0 && $current_level > $previous_level + 1) {
                // Use aria-level to fix hierarchy without changing visual appearance
                $proper_level = $previous_level + 1;
                $heading->setAttribute('aria-level', $proper_level);
                $heading->setAttribute('data-raywp-heading-fixed', 'true');
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Fixed heading skip from h' . $previous_level . ' to h' . $current_level . ' using aria-level="' . $proper_level . '"');
                }
                
                $current_level = $proper_level;
            }
            
            $previous_level = $current_level;
        }
        
        // Handle multiple h1s - use aria-level to preserve styling
        $h1s = $xpath->query('//h1');
        if ($h1s->length > 1) {
            // Keep first h1, add aria-level="2" to others
            for ($i = 1; $i < $h1s->length; $i++) {
                $h1 = $h1s->item($i);
                // Use aria-level instead of changing the tag to preserve CSS styles
                $h1->setAttribute('aria-level', '2');
                
                // Add a data attribute to track this was modified
                $h1->setAttribute('data-raywp-heading-fixed', 'true');
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Added aria-level="2" to additional h1');
                }
            }
        }
    }
    
    /**
     * Fix missing aria-controls on buttons with aria-expanded
     */
    private function fix_aria_controls($xpath) {
        // Find buttons with aria-expanded but missing aria-controls
        $buttons = $xpath->query('//button[@aria-expanded and not(@aria-controls)]');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RayWP Accessibility: Found ' . $buttons->length . ' buttons needing aria-controls fix');
        }
        
        foreach ($buttons as $button) {
            $button_classes = $button->getAttribute('class');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Processing button with classes: ' . $button_classes);
            }
            
            // Try to find the element this button controls
            $controlled_element = $this->find_controlled_element($button, $xpath);
            
            if ($controlled_element) {
                // Make sure the controlled element has an ID
                $controlled_id = $controlled_element->getAttribute('id');
                if (empty($controlled_id)) {
                    $controlled_id = 'raywp-controlled-' . wp_generate_password(8, false);
                    $controlled_element->setAttribute('id', $controlled_id);
                }
                
                // Add aria-controls to the button
                $button->setAttribute('aria-controls', $controlled_id);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Added aria-controls="' . $controlled_id . '" to button with classes: ' . $button_classes);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Could not find controlled element for button with classes: ' . $button_classes);
                }
            }
        }
    }
    
    /**
     * Find the element controlled by an expandable button
     */
    private function find_controlled_element($button, $xpath) {
        $button_classes = $button->getAttribute('class');
        
        // Strategy 1: Handle Max Mega Menu specifically
        if (strpos($button_classes, 'mega-toggle') !== false) {
            // Look for mega menu panels
            $mega_panels = $xpath->query('//div[contains(@class, "mega-sub-menu")] | //div[contains(@class, "mega-menu-wrap")] | //ul[contains(@class, "mega-menu")]');
            if ($mega_panels->length > 0) {
                return $mega_panels->item(0);
            }
            
            // Look for parent's mega menu content
            $parent = $button->parentNode;
            if ($parent) {
                $mega_content = $xpath->query('.//*[contains(@class, "mega-menu") or contains(@class, "mega-sub")]', $parent);
                if ($mega_content->length > 0) {
                    return $mega_content->item(0);
                }
            }
        }
        
        // Strategy 2: Look for next sibling that might be a dropdown/menu
        $next_sibling = $button->nextSibling;
        while ($next_sibling && $next_sibling->nodeType !== XML_ELEMENT_NODE) {
            $next_sibling = $next_sibling->nextSibling;
        }
        
        if ($next_sibling && $this->is_likely_controlled_element($next_sibling)) {
            return $next_sibling;
        }
        
        // Strategy 3: Look for child elements that might be controlled
        $children = $xpath->query('.//*[contains(@class, "menu") or contains(@class, "dropdown") or contains(@class, "submenu") or contains(@class, "nav")]', $button);
        if ($children->length > 0) {
            return $children->item(0);
        }
        
        // Strategy 4: Look for parent container's submenu
        $parent = $button->parentNode;
        if ($parent) {
            $submenus = $xpath->query('.//*[contains(@class, "sub-menu") or contains(@class, "dropdown-menu") or contains(@class, "submenu") or contains(@class, "mega")]', $parent);
            if ($submenus->length > 0) {
                return $submenus->item(0);
            }
        }
        
        // Strategy 5: Look for following sibling lists (common in menus)
        $following_elements = $xpath->query('./following-sibling::ul | ./following-sibling::div[contains(@class, "menu")]', $button);
        if ($following_elements->length > 0) {
            return $following_elements->item(0);
        }
        
        // Strategy 6: For mega menu buttons, look anywhere in the document
        if (strpos($button_classes, 'mega') !== false || strpos($button_classes, 'toggle') !== false) {
            $mega_elements = $xpath->query('//ul[contains(@class, "menu")] | //div[contains(@class, "navigation")] | //nav[contains(@class, "menu")]');
            if ($mega_elements->length > 0) {
                return $mega_elements->item(0);
            }
        }
        
        // Strategy 7: If no controlled element found, log and return null
        // Don't inject new elements as this can break page layouts
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RayWP Accessibility: Could not find controlled element for button with classes: ' . $button_classes);
        }
        
        // Instead of creating an element, just return null
        // The calling function should handle this gracefully
        return null;
    }
    
    /**
     * Check if an element is likely to be controlled by a button
     */
    private function is_likely_controlled_element($element) {
        $tag = strtolower($element->tagName);
        $class = strtolower($element->getAttribute('class'));
        
        // Common patterns for controlled elements
        $controlled_patterns = [
            'menu', 'dropdown', 'submenu', 'sub-menu', 'nav', 'navigation',
            'collapse', 'accordion', 'panel', 'content', 'popup', 'modal',
            'mega', 'mega-menu', 'mega-sub', 'mega-wrap'
        ];
        
        // Check if it's a list (common for menus)
        if ($tag === 'ul' || $tag === 'ol') {
            return true;
        }
        
        // Check for common class patterns
        foreach ($controlled_patterns as $pattern) {
            if (strpos($class, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Fix empty headings by removing them or adding appropriate content
     */
    private function fix_empty_headings($xpath) {
        // Find empty headings
        $empty_headings = $xpath->query('//h1[not(text()) and not(*)] | //h2[not(text()) and not(*)] | //h3[not(text()) and not(*)] | //h4[not(text()) and not(*)] | //h5[not(text()) and not(*)] | //h6[not(text()) and not(*)]');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RayWP Accessibility: Found ' . $empty_headings->length . ' empty headings to fix');
        }
        
        foreach ($empty_headings as $heading) {
            $class = $heading->getAttribute('class');
            
            // Handle specific plugin patterns
            if (strpos($class, 'tribe_event-location') !== false) {
                // This is from The Events Calendar - remove it entirely as it's meant for location but is empty
                $heading->parentNode->removeChild($heading);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Removed empty tribe_event-location heading');
                }
            } elseif (strpos($class, 'event') !== false || strpos($class, 'calendar') !== false) {
                // Other event-related empty headings - hide with screen reader text
                $heading->setAttribute('class', $class . ' screen-reader-text');
                $heading->setAttribute('aria-hidden', 'true');
                $heading->textContent = 'Event details';
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Hidden empty event heading with screen reader text');
                }
            } else {
                // Generic empty headings - remove them
                $heading->parentNode->removeChild($heading);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Removed empty heading with class: ' . $class);
                }
            }
        }
    }
    
    /**
     * Fix Lighthouse-specific accessibility issues
     */
    private function fix_lighthouse_issues($xpath) {
        // Fix invalid ARIA roles
        $this->fix_invalid_aria_roles($xpath);
        
        // Fix duplicate ARIA IDs
        $this->fix_duplicate_aria_ids($xpath);
        
        // Fix links without discernible names
        $this->fix_unnamed_links($xpath);
        
        // Fix skip links not being focusable
        $this->fix_skip_link_focus($xpath);
        
        // Fix invalid list structures
        $this->fix_invalid_lists($xpath);
        
        // Fix low contrast issues (if enabled)
        if (!empty($this->settings['fix_contrast'])) {
            $this->fix_low_contrast($xpath);
        }
        
        // Fix multiple form labels
        $this->fix_multiple_form_labels($xpath);
    }
    
    /**
     * Fix invalid ARIA roles
     */
    private function fix_invalid_aria_roles($xpath) {
        // Fix role="carousel" which is not a valid ARIA role
        $carousel_elements = $xpath->query('//*[@role="carousel"]');
        foreach ($carousel_elements as $element) {
            $element->setAttribute('role', 'region');
            $element->setAttribute('aria-label', 'Image carousel');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Fixed invalid role="carousel" to role="region"');
            }
        }
        
        // Check for other invalid roles and fix them
        $elements_with_roles = $xpath->query('//*[@role]');
        $valid_roles = [
            'alert', 'alertdialog', 'application', 'article', 'banner', 'button', 'cell', 'checkbox',
            'columnheader', 'combobox', 'complementary', 'contentinfo', 'definition', 'dialog',
            'directory', 'document', 'feed', 'figure', 'form', 'grid', 'gridcell', 'group', 'heading',
            'img', 'link', 'list', 'listbox', 'listitem', 'log', 'main', 'marquee', 'math', 'menu',
            'menubar', 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'navigation', 'none', 'note',
            'option', 'presentation', 'progressbar', 'radio', 'radiogroup', 'region', 'row',
            'rowgroup', 'rowheader', 'scrollbar', 'search', 'searchbox', 'separator', 'slider',
            'spinbutton', 'status', 'switch', 'tab', 'table', 'tablist', 'tabpanel', 'term',
            'textbox', 'timer', 'toolbar', 'tooltip', 'tree', 'treegrid', 'treeitem'
        ];
        
        foreach ($elements_with_roles as $element) {
            $role = $element->getAttribute('role');
            if (!in_array($role, $valid_roles)) {
                // Remove invalid role or replace with appropriate one
                if (strpos($role, 'carousel') !== false || strpos($role, 'slider') !== false) {
                    $element->setAttribute('role', 'region');
                    $element->setAttribute('aria-label', 'Content slider');
                } else {
                    $element->removeAttribute('role');
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Fixed invalid role="' . $role . '"');
                }
            }
        }
    }
    
    /**
     * Fix duplicate ARIA IDs
     */
    private function fix_duplicate_aria_ids($xpath) {
        $aria_id_attributes = ['aria-labelledby', 'aria-describedby', 'aria-controls'];
        $used_ids = [];
        
        foreach ($aria_id_attributes as $attr) {
            $elements = $xpath->query('//*[@' . $attr . ']');
            foreach ($elements as $element) {
                $aria_ids = explode(' ', $element->getAttribute($attr));
                $unique_ids = [];
                
                foreach ($aria_ids as $id) {
                    $id = trim($id);
                    if (empty($id)) continue;
                    
                    // Check if this ID is already used
                    if (isset($used_ids[$id])) {
                        // Create a unique version
                        $counter = 1;
                        $new_id = $id . '-' . $counter;
                        while (isset($used_ids[$new_id])) {
                            $counter++;
                            $new_id = $id . '-' . $counter;
                        }
                        
                        // Update the target element's ID if it exists
                        $target_element = $xpath->query('//*[@id="' . $id . '"]')->item(0);
                        if ($target_element) {
                            $target_element->setAttribute('id', $new_id);
                        }
                        
                        $unique_ids[] = $new_id;
                        $used_ids[$new_id] = true;
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('RayWP Accessibility: Fixed duplicate ARIA ID "' . $id . '" to "' . $new_id . '"');
                        }
                    } else {
                        $unique_ids[] = $id;
                        $used_ids[$id] = true;
                    }
                }
                
                if (!empty($unique_ids)) {
                    $element->setAttribute($attr, implode(' ', $unique_ids));
                }
            }
        }
    }
    
    /**
     * Fix links without discernible names
     */
    private function fix_unnamed_links($xpath) {
        $links = $xpath->query('//a[not(@aria-label) and not(@aria-labelledby) and (not(text()) or normalize-space(text())="")]');
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $title = $link->getAttribute('title');
            
            // Try to get descriptive text from images, icons, or other child elements
            $images = $xpath->query('.//img[@alt]', $link);
            $icons = $xpath->query('.//*[contains(@class, "icon") or contains(@class, "fa-")]', $link);
            
            if ($images->length > 0 && $images->item(0)->getAttribute('alt')) {
                $link->setAttribute('aria-label', $images->item(0)->getAttribute('alt'));
            } elseif (!empty($title)) {
                $link->setAttribute('aria-label', $title);
            } elseif ($icons->length > 0) {
                // Try to determine icon meaning from classes
                $icon_class = $icons->item(0)->getAttribute('class');
                if (strpos($icon_class, 'home') !== false) {
                    $link->setAttribute('aria-label', 'Home');
                } elseif (strpos($icon_class, 'menu') !== false) {
                    $link->setAttribute('aria-label', 'Menu');
                } elseif (strpos($icon_class, 'search') !== false) {
                    $link->setAttribute('aria-label', 'Search');
                } elseif (strpos($icon_class, 'close') !== false) {
                    $link->setAttribute('aria-label', 'Close');
                } else {
                    $link->setAttribute('aria-label', 'Link');
                }
            } elseif (!empty($href)) {
                // Use href as fallback, cleaned up
                $clean_href = str_replace(['http://', 'https://', 'www.'], '', $href);
                $clean_href = trim($clean_href, '/');
                if (!empty($clean_href) && $clean_href !== '#') {
                    $link->setAttribute('aria-label', 'Visit ' . $clean_href);
                } else {
                    $link->setAttribute('aria-label', 'Link');
                }
            } else {
                $link->setAttribute('aria-label', 'Link');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Added aria-label to unnamed link');
            }
        }
    }
    
    /**
     * Fix skip links not being focusable
     */
    private function fix_skip_link_focus($xpath) {
        $skip_links = $xpath->query('//a[contains(@class, "skip") or (contains(@href, "#") and (contains(translate(text(), "SKIP", "skip"), "skip") or contains(@class, "skip")))]');
        
        foreach ($skip_links as $link) {
            // Remove screen-reader-text class that may hide the link
            $current_class = $link->getAttribute('class');
            $current_class = str_replace('screen-reader-text', '', $current_class);
            
            // Ensure skip links are focusable
            if (!$link->hasAttribute('tabindex') || $link->getAttribute('tabindex') === '-1') {
                $link->setAttribute('tabindex', '0');
            }
            
            // Add proper CSS classes for skip link functionality
            if (strpos($current_class, 'raywp-skip-link') === false) {
                $current_class .= ' raywp-skip-link';
            }
            
            // Add skip link specific class for proper styling
            $current_class .= ' raywp-focusable-skip-link';
            
            $link->setAttribute('class', trim($current_class));
            
            // Ensure the target element exists and is accessible
            $href = $link->getAttribute('href');
            if ($href && strpos($href, '#') === 0) {
                $target_id = substr($href, 1);
                $target_elements = $xpath->query('//*[@id="' . $target_id . '"]');
                if ($target_elements->length === 0) {
                    // Target doesn't exist, find a suitable main content area
                    $main_content = $xpath->query('//main | //*[@role="main"] | //*[@id="content"] | //*[@id="main"]');
                    if ($main_content->length > 0) {
                        $main_element = $main_content->item(0);
                        if (!$main_element->hasAttribute('id')) {
                            $main_element->setAttribute('id', $target_id);
                        } else {
                            // Update the link to point to existing main content
                            $link->setAttribute('href', '#' . $main_element->getAttribute('id'));
                        }
                    }
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RayWP Accessibility: Fixed skip link focus for: ' . $link->getAttribute('href'));
            }
        }
    }
    
    /**
     * Fix invalid list structures
     */
    private function fix_invalid_lists($xpath) {
        $lists = $xpath->query('//ul | //ol');
        
        foreach ($lists as $list) {
            $children = $xpath->query('./child::*', $list);
            
            foreach ($children as $child) {
                $tag_name = strtolower($child->tagName);
                
                // Only li, script, and template elements should be direct children of lists
                if (!in_array($tag_name, ['li', 'script', 'template'])) {
                    // Wrap non-li elements in li
                    $li_wrapper = $child->ownerDocument->createElement('li');
                    $child->parentNode->insertBefore($li_wrapper, $child);
                    $li_wrapper->appendChild($child);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('RayWP Accessibility: Wrapped invalid list child "' . $tag_name . '" in li element');
                    }
                }
            }
        }
    }
    
    /**
     * Fix low contrast issues (simplified approach)
     */
    private function fix_low_contrast($xpath) {
        // This is a basic implementation - full contrast fixing would require color analysis
        // For now, we'll add a CSS class that can be styled to improve contrast
        
        $low_contrast_selectors = [
            '//h2[@aria-level="2"]', // Specific headings mentioned in Lighthouse
            '//h3[@aria-level="3"]',
            '//*[contains(@class, "content-box")]'
        ];
        
        foreach ($low_contrast_selectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $current_class = $element->getAttribute('class');
                if (strpos($current_class, 'raywp-contrast-fix') === false) {
                    $element->setAttribute('class', trim($current_class . ' raywp-contrast-fix'));
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RayWP Accessibility: Added contrast fix classes');
        }
    }
    
    /**
     * Fix multiple form labels (common with Gravity Forms)
     */
    private function fix_multiple_form_labels($xpath) {
        // Find all form inputs
        $inputs = $xpath->query('//input[@id] | //textarea[@id] | //select[@id]');
        
        foreach ($inputs as $input) {
            $input_id = $input->getAttribute('id');
            if (empty($input_id)) continue;
            
            // Find all labels pointing to this input
            $labels = $xpath->query('//label[@for="' . $input_id . '"]');
            
            if ($labels->length > 1) {
                // Multiple labels found - keep the first, remove duplicates
                $kept_label = null;
                $labels_to_remove = [];
                
                foreach ($labels as $index => $label) {
                    if ($index === 0) {
                        // Keep the first label
                        $kept_label = $label;
                    } else {
                        // Mark others for removal
                        $labels_to_remove[] = $label;
                    }
                }
                
                // Remove duplicate labels
                foreach ($labels_to_remove as $label) {
                    // Before removing, check if it has unique content we should preserve
                    $label_text = trim($label->textContent);
                    $kept_label_text = trim($kept_label->textContent);
                    
                    if ($label_text !== $kept_label_text && !empty($label_text)) {
                        // Labels have different content - combine them with aria-describedby instead
                        $description_id = 'desc-' . $input_id;
                        $description_element = $label->ownerDocument->createElement('div');
                        $description_element->setAttribute('id', $description_id);
                        $description_element->setAttribute('class', 'raywp-field-description');
                        $description_element->textContent = $label_text;
                        
                        // Insert description after the input
                        if ($input->nextSibling) {
                            $input->parentNode->insertBefore($description_element, $input->nextSibling);
                        } else {
                            $input->parentNode->appendChild($description_element);
                        }
                        
                        // Update input's aria-describedby
                        $current_described_by = $input->getAttribute('aria-describedby');
                        if (empty($current_described_by)) {
                            $input->setAttribute('aria-describedby', $description_id);
                        } else {
                            $input->setAttribute('aria-describedby', $current_described_by . ' ' . $description_id);
                        }
                    }
                    
                    // Remove the duplicate label
                    $label->parentNode->removeChild($label);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RayWP Accessibility: Fixed multiple labels for input ' . $input_id);
                }
            }
        }
    }
    
    /**
     * Log performance metrics for monitoring
     */
    private function log_performance_metrics($processing_time, $memory_used) {
        // Get current performance data
        $performance_data = get_transient('raywp_accessibility_performance_metrics');
        if (!is_array($performance_data)) {
            $performance_data = [
                'samples' => [],
                'total_time' => 0,
                'total_memory' => 0,
                'count' => 0
            ];
        }
        
        // Add current sample (keep last 100 samples)
        $performance_data['samples'][] = [
            'time' => $processing_time,
            'memory' => $memory_used,
            'timestamp' => current_time('timestamp')
        ];
        
        if (count($performance_data['samples']) > 100) {
            array_shift($performance_data['samples']);
        }
        
        // Update totals
        $performance_data['total_time'] += $processing_time;
        $performance_data['total_memory'] += $memory_used;
        $performance_data['count']++;
        
        // Calculate averages
        $performance_data['avg_time'] = $performance_data['total_time'] / $performance_data['count'];
        $performance_data['avg_memory'] = $performance_data['total_memory'] / $performance_data['count'];
        
        // Store for 24 hours
        set_transient('raywp_accessibility_performance_metrics', $performance_data, DAY_IN_SECONDS);
        
        // Log warning if performance is degraded
        if ($processing_time > 100) { // More than 100ms
            error_log(sprintf(
                'RayWP Accessibility Warning: Slow page processing detected - %.2f ms',
                $processing_time
            ));
        }
    }
}
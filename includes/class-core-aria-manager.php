<?php
/**
 * ARIA Manager - Handles all ARIA attribute operations
 */

namespace RayWP\Accessibility\Core;

use RayWP\Accessibility\Traits\Aria_Validator;

if (!defined('ABSPATH')) {
    exit;
}

class Aria_Manager {
    
    use Aria_Validator;
    
    /**
     * ARIA rules storage
     */
    private $aria_rules = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_aria_rules();
    }
    
    /**
     * Load ARIA rules from database
     */
    private function load_aria_rules() {
        $this->aria_rules = get_option('raywp_accessibility_aria_rules', []);
    }
    
    /**
     * Save ARIA rules
     */
    public function save_aria_rules($rules) {
        $validated_rules = [];
        
        foreach ($rules as $rule) {
            if ($this->validate_aria_rule($rule)) {
                $validated_rules[] = $rule;
            }
        }
        
        update_option('raywp_accessibility_aria_rules', $validated_rules);
        $this->aria_rules = $validated_rules;
        
        return count($validated_rules);
    }
    
    /**
     * Validate a single ARIA rule
     */
    private function validate_aria_rule($rule) {
        // Check required fields
        if (empty($rule['selector']) || empty($rule['attribute']) || !isset($rule['value'])) {
            return false;
        }
        
        // Validate selector
        if (!$this->validate_css_selector($rule['selector'])) {
            return false;
        }
        
        // Validate ARIA attribute
        if (!$this->is_valid_aria_attribute($rule['attribute'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get ARIA rules
     */
    public function get_aria_rules() {
        return $this->aria_rules;
    }
    
    /**
     * Apply ARIA attributes to HTML content
     */
    public function apply_aria_to_html($html) {
        if (empty($this->aria_rules) || empty($html)) {
            return $html;
        }
        
        // Use DOMDocument for reliable HTML manipulation
        $dom = new \DOMDocument();
        
        // Suppress warnings for HTML5 tags
        libxml_use_internal_errors(true);
        
        // Load HTML with UTF-8 encoding
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Clear errors
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        // Apply each ARIA rule
        foreach ($this->aria_rules as $rule) {
            $this->apply_single_rule($xpath, $rule);
        }
        
        // Save and return modified HTML
        $modified_html = $dom->saveHTML();
        
        // Remove the XML declaration we added
        $modified_html = str_replace('<?xml encoding="UTF-8">', '', $modified_html);
        
        return $modified_html;
    }
    
    /**
     * Apply a single ARIA rule
     */
    private function apply_single_rule($xpath, $rule) {
        try {
            // Convert CSS selector to XPath
            $xpath_selector = $this->enhanced_css_to_xpath($rule['selector']);
            
            // Find matching elements
            $elements = $xpath->query($xpath_selector);
            
            if ($elements === false) {
                return;
            }
            
            // Apply attribute to each matching element
            foreach ($elements as $element) {
                if ($element->nodeType === XML_ELEMENT_NODE) {
                    $element->setAttribute($rule['attribute'], $rule['value']);
                }
            }
        } catch (\Exception $e) {
            // Log error if needed
            error_log('RayWP Accessibility: Failed to apply ARIA rule - ' . $e->getMessage());
        }
    }
    
    /**
     * Get ARIA attributes for JavaScript application
     */
    public function get_aria_rules_for_js() {
        $js_rules = [];
        
        foreach ($this->aria_rules as $rule) {
            $js_rules[] = [
                'selector' => $rule['selector'],
                'attribute' => $rule['attribute'],
                'value' => $rule['value']
            ];
        }
        
        return $js_rules;
    }
    
    /**
     * Add landmark role
     */
    public function add_landmark_role($selector, $role) {
        if (!$this->is_valid_aria_role($role)) {
            return false;
        }
        
        $rule = [
            'selector' => $selector,
            'attribute' => 'role',
            'value' => $role
        ];
        
        $this->aria_rules[] = $rule;
        $this->save_aria_rules($this->aria_rules);
        
        return true;
    }
    
    /**
     * Remove ARIA rule
     */
    public function remove_aria_rule($index) {
        if (isset($this->aria_rules[$index])) {
            unset($this->aria_rules[$index]);
            $this->aria_rules = array_values($this->aria_rules);
            update_option('raywp_accessibility_aria_rules', $this->aria_rules);
            return true;
        }
        return false;
    }
    
    /**
     * Test selector on current page
     */
    public function test_selector($selector, $url = '') {
        if (empty($url)) {
            $url = home_url();
        }
        
        // Fetch the page content
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return ['error' => 'Failed to fetch page'];
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Parse with DOMDocument
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        try {
            $xpath_selector = $this->enhanced_css_to_xpath($selector);
            $elements = $xpath->query($xpath_selector);
            
            if ($elements === false) {
                return ['error' => 'Invalid selector'];
            }
            
            $matches = [];
            foreach ($elements as $element) {
                $matches[] = [
                    'tag' => $element->tagName,
                    'id' => $element->getAttribute('id'),
                    'class' => $element->getAttribute('class'),
                    'text' => substr($element->textContent, 0, 50)
                ];
            }
            
            return [
                'count' => $elements->length,
                'matches' => array_slice($matches, 0, 5) // Return first 5 matches
            ];
            
        } catch (\Exception $e) {
            return ['error' => 'Failed to parse selector'];
        }
    }
}
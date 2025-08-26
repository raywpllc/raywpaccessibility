<?php
/**
 * ARIA Validation Trait
 */

namespace RayWP\Accessibility\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait Aria_Validator {
    
    /**
     * Valid ARIA attributes
     */
    protected $valid_aria_attributes = [
        'aria-activedescendant',
        'aria-atomic',
        'aria-autocomplete',
        'aria-braillelabel',
        'aria-brailleroledescription',
        'aria-busy',
        'aria-checked',
        'aria-colcount',
        'aria-colindex',
        'aria-colindextext',
        'aria-colspan',
        'aria-controls',
        'aria-current',
        'aria-describedby',
        'aria-description',
        'aria-details',
        'aria-disabled',
        'aria-dropeffect',
        'aria-errormessage',
        'aria-expanded',
        'aria-flowto',
        'aria-grabbed',
        'aria-haspopup',
        'aria-hidden',
        'aria-invalid',
        'aria-keyshortcuts',
        'aria-label',
        'aria-labelledby',
        'aria-level',
        'aria-live',
        'aria-modal',
        'aria-multiline',
        'aria-multiselectable',
        'aria-orientation',
        'aria-owns',
        'aria-placeholder',
        'aria-posinset',
        'aria-pressed',
        'aria-readonly',
        'aria-relevant',
        'aria-required',
        'aria-roledescription',
        'aria-rowcount',
        'aria-rowindex',
        'aria-rowindextext',
        'aria-rowspan',
        'aria-selected',
        'aria-setsize',
        'aria-sort',
        'aria-valuemax',
        'aria-valuemin',
        'aria-valuenow',
        'aria-valuetext'
    ];
    
    /**
     * Valid ARIA roles
     */
    protected $valid_aria_roles = [
        // Document structure roles
        'article',
        'document',
        'feed',
        'figure',
        'img',
        'list',
        'listitem',
        'main',
        'math',
        'none',
        'note',
        'presentation',
        'region',
        'separator',
        'table',
        'term',
        'tooltip',
        
        // Landmark roles
        'banner',
        'complementary',
        'contentinfo',
        'form',
        'navigation',
        'search',
        
        // Widget roles
        'alert',
        'alertdialog',
        'button',
        'checkbox',
        'dialog',
        'gridcell',
        'link',
        'log',
        'marquee',
        'menuitem',
        'menuitemcheckbox',
        'menuitemradio',
        'option',
        'progressbar',
        'radio',
        'scrollbar',
        'searchbox',
        'slider',
        'spinbutton',
        'status',
        'switch',
        'tab',
        'tabpanel',
        'textbox',
        'timer',
        'treegrid',
        'treeitem',
        
        // Composite roles
        'combobox',
        'grid',
        'listbox',
        'menu',
        'menubar',
        'radiogroup',
        'tablist',
        'tree',
        'toolbar',
        
        // Application roles
        'application'
    ];
    
    /**
     * Validate ARIA attribute
     */
    public function is_valid_aria_attribute($attribute) {
        return in_array(strtolower($attribute), $this->valid_aria_attributes);
    }
    
    /**
     * Validate ARIA role
     */
    public function is_valid_aria_role($role) {
        return in_array(strtolower($role), $this->valid_aria_roles);
    }
    
    /**
     * Validate CSS selector
     */
    public function validate_css_selector($selector) {
        if (empty($selector)) {
            return false;
        }
        
        // Basic validation - check for common invalid patterns
        $invalid_patterns = [
            '/^\d/', // Starts with number
            '/\s{2,}/', // Multiple spaces
            '/[<>]/', // HTML tags
            '/\{\}/', // CSS rules
        ];
        
        foreach ($invalid_patterns as $pattern) {
            if (preg_match($pattern, $selector)) {
                return false;
            }
        }
        
        // Try to parse with DOMXPath to ensure it's valid
        try {
            $doc = new \DOMDocument();
            $xpath = new \DOMXPath($doc);
            
            // Convert CSS to XPath for validation
            $xpath_selector = $this->enhanced_css_to_xpath($selector);
            @$xpath->evaluate($xpath_selector);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Enhanced CSS to XPath converter
     */
    protected function enhanced_css_to_xpath($css) {
        // Start with the CSS selector
        $xpath = $css;
        
        // Handle attribute selectors with quotes
        // [attr="value"] -> [@attr="value"]
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)="([^"]+)"\]/', '[@$1="$2"]', $xpath);
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)=\'([^\']+)\'\]/', '[@$1=\'$2\']', $xpath);
        
        // Handle attribute selectors without quotes
        // [attr=value] -> [@attr="value"]
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)=([^\]]+)\]/', '[@$1="$2"]', $xpath);
        
        // Handle attribute existence selectors
        // [attr] -> [@attr]
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)\]/', '[@$1]', $xpath);
        
        // Handle multiple classes (must be done before single class handling)
        // .classA.classB -> [contains(@class,"classA") and contains(@class,"classB")]
        while (preg_match('/\.([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_-]+)/', $xpath, $matches)) {
            $xpath = preg_replace(
                '/\.([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_-]+)/',
                '[contains(concat(" ", normalize-space(@class), " "), " $1 ") and contains(concat(" ", normalize-space(@class), " "), " $2 ")]',
                $xpath,
                1
            );
        }
        
        // Handle single class selectors
        // .class -> [contains(@class,"class")]
        $xpath = preg_replace('/\.([a-zA-Z0-9_-]+)/', '[contains(concat(" ", normalize-space(@class), " "), " $1 ")]', $xpath);
        
        // Handle ID selectors
        // #id -> [@id="id"]
        $xpath = preg_replace('/#([a-zA-Z0-9_-]+)/', '[@id="$1"]', $xpath);
        
        // Handle pseudo-selectors
        $xpath = preg_replace('/:first-child/', '[1]', $xpath);
        $xpath = preg_replace('/:last-child/', '[last()]', $xpath);
        $xpath = preg_replace('/:nth-child\((\d+)\)/', '[$1]', $xpath);
        
        // Handle combinators (order matters!)
        // Child combinator
        $xpath = preg_replace('/\s*>\s*/', '/', $xpath);
        // Adjacent sibling
        $xpath = preg_replace('/\s*\+\s*/', '/following-sibling::*[1]/self::', $xpath);
        // General sibling
        $xpath = preg_replace('/\s*~\s*/', '/following-sibling::', $xpath);
        // Descendant combinator (space)
        $xpath = preg_replace('/\s+/', '//', $xpath);
        
        // If xpath doesn't start with /, add // for anywhere in document
        if (strpos($xpath, '/') !== 0) {
            $xpath = '//' . $xpath;
        }
        
        return $xpath;
    }
}
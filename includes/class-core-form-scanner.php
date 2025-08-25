<?php
/**
 * Form Scanner - Scans and fixes forms for accessibility
 */

namespace RayWP\Accessibility\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Form_Scanner {
    
    /**
     * Supported form plugins
     */
    private $supported_plugins = [
        'contact-form-7' => 'Contact Form 7',
        'wpforms' => 'WPForms',
        'gravity-forms' => 'Gravity Forms',
        'ninja-forms' => 'Ninja Forms',
        'formidable' => 'Formidable Forms',
        'elementor-forms' => 'Elementor Forms',
        'fluent-forms' => 'Fluent Forms'
    ];
    
    /**
     * Scan all forms on the site
     */
    public function scan_all_forms() {
        $results = [
            'total_forms' => 0,
            'issues_found' => 0,
            'forms' => []
        ];
        
        // Scan each form plugin
        foreach ($this->supported_plugins as $plugin_slug => $plugin_name) {
            if ($this->is_plugin_active($plugin_slug)) {
                $forms = $this->scan_plugin_forms($plugin_slug);
                $results['forms'][$plugin_slug] = $forms;
                $results['total_forms'] += count($forms);
                
                foreach ($forms as $form) {
                    $results['issues_found'] += count($form['issues']);
                }
            }
        }
        
        // Scan generic HTML forms
        $html_forms = $this->scan_html_forms();
        $results['forms']['html'] = $html_forms;
        $results['total_forms'] += count($html_forms);
        
        foreach ($html_forms as $form) {
            $results['issues_found'] += count($form['issues']);
        }
        
        return $results;
    }
    
    /**
     * Check if plugin is active
     */
    private function is_plugin_active($plugin_slug) {
        switch ($plugin_slug) {
            case 'contact-form-7':
                return class_exists('WPCF7');
            case 'wpforms':
                return function_exists('wpforms');
            case 'gravity-forms':
                return class_exists('GFForms');
            case 'ninja-forms':
                return function_exists('Ninja_Forms');
            case 'formidable':
                return class_exists('FrmAppHelper');
            case 'elementor-forms':
                return defined('ELEMENTOR_VERSION');
            case 'fluent-forms':
                return defined('FLUENTFORM_VERSION');
            default:
                return false;
        }
    }
    
    /**
     * Scan forms from specific plugin
     */
    private function scan_plugin_forms($plugin_slug) {
        $forms = [];
        
        switch ($plugin_slug) {
            case 'contact-form-7':
                $forms = $this->scan_cf7_forms();
                break;
            case 'wpforms':
                $forms = $this->scan_wpforms();
                break;
            case 'gravity-forms':
                $forms = $this->scan_gravity_forms();
                break;
            // Add other plugins as needed
        }
        
        return $forms;
    }
    
    /**
     * Scan Contact Form 7 forms
     */
    private function scan_cf7_forms() {
        $forms = [];
        
        if (!class_exists('WPCF7_ContactForm')) {
            return $forms;
        }
        
        $cf7_forms = \WPCF7_ContactForm::find();
        
        foreach ($cf7_forms as $form) {
            $issues = [];
            $form_data = [
                'id' => $form->id(),
                'title' => $form->title(),
                'issues' => []
            ];
            
            // Get form content
            $form_content = $form->prop('form');
            
            // Check for labels
            if (strpos($form_content, '[text') !== false || strpos($form_content, '[email') !== false) {
                if (strpos($form_content, '<label') === false) {
                    $issues[] = [
                        'type' => 'missing_labels',
                        'severity' => 'high',
                        'message' => 'Form fields are missing explicit labels'
                    ];
                }
            }
            
            // Check for required field indicators
            if (strpos($form_content, '*') !== false) {
                if (strpos($form_content, 'aria-required') === false) {
                    $issues[] = [
                        'type' => 'missing_required_aria',
                        'severity' => 'medium',
                        'message' => 'Required fields missing aria-required attribute'
                    ];
                }
            }
            
            // Check for fieldsets in radio/checkbox groups
            if (strpos($form_content, '[radio') !== false || strpos($form_content, '[checkbox') !== false) {
                if (strpos($form_content, '<fieldset') === false) {
                    $issues[] = [
                        'type' => 'missing_fieldset',
                        'severity' => 'medium',
                        'message' => 'Radio/checkbox groups should be wrapped in fieldsets'
                    ];
                }
            }
            
            $form_data['issues'] = $issues;
            $forms[] = $form_data;
        }
        
        return $forms;
    }
    
    /**
     * Scan WPForms
     */
    private function scan_wpforms() {
        $forms = [];
        
        if (!function_exists('wpforms')) {
            return $forms;
        }
        
        $wpforms = wpforms()->form->get();
        
        foreach ($wpforms as $form) {
            $issues = [];
            $form_data = [
                'id' => $form->ID,
                'title' => $form->post_title,
                'issues' => []
            ];
            
            $form_content = json_decode($form->post_content, true);
            
            if (isset($form_content['fields'])) {
                foreach ($form_content['fields'] as $field) {
                    // Check for missing labels
                    if (empty($field['label']) && $field['type'] !== 'html') {
                        $issues[] = [
                            'type' => 'missing_label',
                            'severity' => 'high',
                            'message' => sprintf('Field "%s" is missing a label', $field['type'])
                        ];
                    }
                    
                    // Check for missing descriptions on complex fields
                    if (in_array($field['type'], ['select', 'radio', 'checkbox']) && empty($field['description'])) {
                        $issues[] = [
                            'type' => 'missing_description',
                            'severity' => 'low',
                            'message' => sprintf('Complex field "%s" could benefit from a description', $field['type'])
                        ];
                    }
                }
            }
            
            $form_data['issues'] = $issues;
            $forms[] = $form_data;
        }
        
        return $forms;
    }
    
    /**
     * Scan Gravity Forms
     */
    private function scan_gravity_forms() {
        $forms = [];
        
        if (!class_exists('GFAPI')) {
            return $forms;
        }
        
        $gf_forms = \GFAPI::get_forms();
        
        foreach ($gf_forms as $form) {
            $issues = [];
            $form_data = [
                'id' => $form['id'],
                'title' => $form['title'],
                'issues' => []
            ];
            
            foreach ($form['fields'] as $field) {
                // Check for missing labels
                if (empty($field->label) && $field->type !== 'html') {
                    $issues[] = [
                        'type' => 'missing_label',
                        'severity' => 'high',
                        'message' => sprintf('Field %d is missing a label', $field->id)
                    ];
                }
                
                // Check for missing aria-describedby for fields with descriptions
                if (!empty($field->description) && empty($field->ariaDescribedBy)) {
                    $issues[] = [
                        'type' => 'missing_aria_describedby',
                        'severity' => 'medium',
                        'message' => sprintf('Field %d has description but missing aria-describedby', $field->id)
                    ];
                }
            }
            
            $form_data['issues'] = $issues;
            $forms[] = $form_data;
        }
        
        return $forms;
    }
    
    /**
     * Scan generic HTML forms
     */
    private function scan_html_forms() {
        $forms = [];
        
        // This would scan pages for generic HTML forms
        // For now, return empty array
        
        return $forms;
    }
    
    /**
     * Apply fixes to a form
     */
    public function apply_form_fixes($plugin_slug, $form_id, $fixes) {
        switch ($plugin_slug) {
            case 'contact-form-7':
                return $this->fix_cf7_form($form_id, $fixes);
            case 'wpforms':
                return $this->fix_wpforms_form($form_id, $fixes);
            case 'gravity-forms':
                return $this->fix_gravity_form($form_id, $fixes);
            default:
                return false;
        }
    }
    
    /**
     * Fix Contact Form 7 form
     */
    private function fix_cf7_form($form_id, $fixes) {
        $form = \WPCF7_ContactForm::get_instance($form_id);
        
        if (!$form) {
            return false;
        }
        
        $form_content = $form->prop('form');
        $modified = false;
        
        foreach ($fixes as $fix) {
            switch ($fix) {
                case 'add_labels':
                    // Add labels to form fields
                    $form_content = preg_replace_callback(
                        '/\[(\w+)\*?\s+([^\]]+)\]/',
                        function($matches) {
                            $field_type = $matches[1];
                            $field_attrs = $matches[2];
                            
                            // Extract field name
                            preg_match('/(?:^|\s)(\w+)(?:\s|$)/', $field_attrs, $name_match);
                            $field_name = $name_match[1] ?? '';
                            
                            if ($field_name && !in_array($field_type, ['submit', 'hidden'])) {
                                $label = ucfirst(str_replace(['_', '-'], ' ', $field_name));
                                return '<label for="' . $field_name . '">' . $label . '</label>' . "\n" . $matches[0];
                            }
                            
                            return $matches[0];
                        },
                        $form_content
                    );
                    $modified = true;
                    break;
                    
                case 'add_aria_required':
                    // Add aria-required to required fields
                    $form_content = preg_replace(
                        '/\[(\w+)\*\s+([^\]]+)\]/',
                        '[$1* $2 aria-required:true]',
                        $form_content
                    );
                    $modified = true;
                    break;
            }
        }
        
        if ($modified) {
            $form->set_properties(['form' => $form_content]);
            $form->save();
            return true;
        }
        
        return false;
    }
    
    /**
     * Fix WPForms form
     */
    private function fix_wpforms_form($form_id, $fixes) {
        // Implementation for WPForms fixes
        return false;
    }
    
    /**
     * Fix Gravity Forms form
     */
    private function fix_gravity_form($form_id, $fixes) {
        // Implementation for Gravity Forms fixes
        return false;
    }
}
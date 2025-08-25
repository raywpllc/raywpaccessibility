<?php
/**
 * Autoloader for the plugin
 */

namespace RayWP\Accessibility;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader {
    
    /**
     * Initialize the autoloader
     */
    public static function init() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    /**
     * Autoload classes
     */
    public static function autoload($class) {
        // Check if the class belongs to our namespace
        if (strpos($class, 'RayWP\\Accessibility\\') !== 0) {
            return;
        }
        
        // Remove namespace prefix
        $class_name = str_replace('RayWP\\Accessibility\\', '', $class);
        
        // Split by namespace separator
        $parts = explode('\\', $class_name);
        
        if (count($parts) === 2) {
            // Format: Namespace\ClassName (e.g., Core\Activator)
            $namespace = strtolower($parts[0]);
            $class_name = $parts[1];
            
            // Convert CamelCase to hyphenated
            $file_name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));
            $file_name = str_replace('_', '-', $file_name);
            
            // Build file path based on namespace
            if ($namespace === 'core') {
                $file_path = RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-' . $namespace . '-' . $file_name . '.php';
            } elseif ($namespace === 'admin') {
                $file_path = RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'admin/class-' . $namespace . '-' . $file_name . '.php';
            } elseif ($namespace === 'frontend') {
                $file_path = RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'frontend/class-' . $namespace . '-' . $file_name . '.php';
            } else {
                $file_path = RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-' . $namespace . '-' . $file_name . '.php';
            }
            
        } else {
            // Single class name (e.g., Autoloader)
            $file_name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));
            $file_name = str_replace('_', '-', $file_name);
            $file_path = RAYWP_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-' . $file_name . '.php';
        }
        
        // Load the file if it exists
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}
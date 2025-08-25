<?php
/**
 * Component Interface
 */

namespace RayWP\Accessibility\Interfaces;

if (!defined('ABSPATH')) {
    exit;
}

interface Component {
    /**
     * Initialize the component
     */
    public function init();
    
    /**
     * Register hooks
     */
    public function register_hooks();
}
<?php
/**
 * Database upgrade script
 * 
 * This file can be accessed directly to force a database upgrade
 * URL: /wp-content/plugins/raywp-accessibility/upgrade-database.php
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('Could not find WordPress installation');
}

// Check if user has permission
if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

// Load the plugin
if (!class_exists('RayWP\\Accessibility\\Core\\Plugin')) {
    require_once __DIR__ . '/includes/class-autoloader.php';
    \RayWP\Accessibility\Autoloader::init();
}

// Get reports component and force table update
$plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
$reports = $plugin->get_component('reports');

if ($reports) {
    // Clear all related caches first
    wp_cache_delete('raywp_wcag_breakdown', 'raywp_accessibility');
    wp_cache_delete('raywp_scan_sessions', 'raywp_accessibility');
    wp_cache_delete('raywp_issue_summary', 'raywp_accessibility');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'raywp_accessibility_scan_results';
    
    // Clear table existence cache
    wp_cache_delete('raywp_table_exists_' . md5($table_name), 'raywp_accessibility');
    
    echo "<h2>RayWP Accessibility Database Upgrade</h2>";
    echo "<p>Starting database upgrade...</p>";
    
    // Force table check and upgrade
    $reflection = new \ReflectionClass($reports);
    $method = $reflection->getMethod('ensure_database_table');
    $method->setAccessible(true);
    $method->invoke($reports);
    
    echo "<p style='color: green;'><strong>✓ Database upgrade completed successfully!</strong></p>";
    echo "<p>The following columns have been added to the scan results table:</p>";
    echo "<ul>";
    echo "<li>wcag_reference - For WCAG guideline references</li>";
    echo "<li>wcag_level - For WCAG compliance level (A, AA, AAA)</li>";
    echo "<li>auto_fixable - To track which issues can be automatically fixed</li>";
    echo "<li>page_type - To categorize page types (post, page, archive, etc.)</li>";
    echo "<li>scan_session_id - For grouping scan results by session</li>";
    echo "<li>wcag_criterion - For specific WCAG success criteria mapping</li>";
    echo "<li>compliance_impact - For professional compliance classification (VIOLATION, WARNING, etc.)</li>";
    echo "<li>confidence_level - For detection confidence assessment (HIGH, MEDIUM, LOW)</li>";
    echo "</ul>";
    
    echo "<p><a href='" . admin_url('admin.php?page=raywp-accessibility-reports') . "'>← Back to Reports</a></p>";
    
    // Clean up this file after successful upgrade
    echo "<script>setTimeout(() => window.close(), 3000);</script>";
    
} else {
    echo "<p style='color: red;'>Error: Could not access reports component</p>";
}
?>
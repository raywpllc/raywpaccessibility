<?php
/**
 * Site Scanner - Comprehensive multi-page accessibility scanning
 */

namespace RayWP\Accessibility\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Site_Scanner {
    
    /**
     * Queue name for background processing
     */
    const QUEUE_NAME = 'raywp_scan_queue';
    
    /**
     * Maximum pages to scan in one session
     */
    const MAX_PAGES_PER_SCAN = 20;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize site scanning functionality
        add_action('wp_ajax_raywp_start_site_scan', [$this, 'handle_start_site_scan']);
        add_action('wp_ajax_raywp_check_scan_progress', [$this, 'handle_check_scan_progress']);
        add_action('wp_ajax_raywp_cancel_scan', [$this, 'handle_cancel_scan']);
        
        // Background processing hooks
        add_action('raywp_process_scan_queue', [$this, 'process_scan_queue']);
    }
    
    /**
     * Start comprehensive site scan
     */
    public function start_site_scan($options = []) {
        // Default options
        $default_options = [
            'scan_type' => 'comprehensive', // comprehensive, posts, pages, custom
            'max_pages' => self::MAX_PAGES_PER_SCAN,
            'include_posts' => true,
            'include_pages' => true,
            'include_archives' => false,
            'include_custom_post_types' => [],
            'exclude_urls' => [],
            'respect_noindex' => true
        ];
        
        $options = array_merge($default_options, $options);
        
        // Generate session ID
        $session_id = 'site_scan_' . uniqid();
        
        // Discover URLs to scan
        $urls_to_scan = $this->discover_urls($options);
        
        if (empty($urls_to_scan)) {
            return [
                'success' => false,
                'message' => 'No URLs found to scan',
                'session_id' => null
            ];
        }
        
        // Limit URLs if needed
        if (count($urls_to_scan) > $options['max_pages']) {
            $urls_to_scan = array_slice($urls_to_scan, 0, $options['max_pages']);
        }
        
        // Save scan session info
        $scan_info = [
            'session_id' => $session_id,
            'start_time' => current_time('mysql'),
            'total_urls' => count($urls_to_scan),
            'completed_urls' => 0,
            'failed_urls' => 0,
            'status' => 'running',
            'options' => $options,
            'urls' => $urls_to_scan,
            'current_url_index' => 0
        ];
        
        update_option('raywp_scan_session_' . $session_id, $scan_info);
        
        // Schedule background processing
        wp_schedule_single_event(time() + 5, 'raywp_process_scan_queue', [$session_id]);
        
        return [
            'success' => true,
            'session_id' => $session_id,
            'total_urls' => count($urls_to_scan),
            'message' => sprintf('Started scanning %d URLs', count($urls_to_scan))
        ];
    }
    
    /**
     * Discover URLs to scan based on options
     */
    private function discover_urls($options) {
        $urls = [];
        
        // Always include home page
        $urls[] = [
            'url' => home_url(),
            'type' => 'home',
            'title' => get_bloginfo('name') . ' - Home'
        ];
        
        // Include posts
        if ($options['include_posts']) {
            $posts = get_posts([
                'numberposts' => $options['max_pages'],
                'post_status' => 'publish',
                'post_type' => 'post'
            ]);
            
            foreach ($posts as $post) {
                if ($this->should_include_url(get_permalink($post), $options)) {
                    $urls[] = [
                        'url' => get_permalink($post),
                        'type' => 'post',
                        'title' => $post->post_title,
                        'id' => $post->ID
                    ];
                }
            }
        }
        
        // Include pages
        if ($options['include_pages']) {
            $pages = get_posts([
                'numberposts' => $options['max_pages'],
                'post_status' => 'publish',
                'post_type' => 'page'
            ]);
            
            foreach ($pages as $page) {
                if ($this->should_include_url(get_permalink($page), $options)) {
                    $urls[] = [
                        'url' => get_permalink($page),
                        'type' => 'page',
                        'title' => $page->post_title,
                        'id' => $page->ID
                    ];
                }
            }
        }
        
        // Include custom post types
        if (!empty($options['include_custom_post_types'])) {
            foreach ($options['include_custom_post_types'] as $post_type) {
                $posts = get_posts([
                    'numberposts' => 5, // Limit custom post types (reduced to fit 20 total limit)
                    'post_status' => 'publish',
                    'post_type' => $post_type
                ]);
                
                foreach ($posts as $post) {
                    if ($this->should_include_url(get_permalink($post), $options)) {
                        $urls[] = [
                            'url' => get_permalink($post),
                            'type' => $post_type,
                            'title' => $post->post_title,
                            'id' => $post->ID
                        ];
                    }
                }
            }
        }
        
        // Include archives
        if ($options['include_archives']) {
            // Category archives
            $categories = get_categories(['hide_empty' => true, 'number' => 10]);
            foreach ($categories as $category) {
                $archive_url = get_category_link($category->term_id);
                if ($this->should_include_url($archive_url, $options)) {
                    $urls[] = [
                        'url' => $archive_url,
                        'type' => 'category_archive',
                        'title' => 'Category: ' . $category->name,
                        'id' => $category->term_id
                    ];
                }
            }
            
            // Tag archives
            $tags = get_tags(['hide_empty' => true, 'number' => 5]);
            foreach ($tags as $tag) {
                $archive_url = get_tag_link($tag->term_id);
                if ($this->should_include_url($archive_url, $options)) {
                    $urls[] = [
                        'url' => $archive_url,
                        'type' => 'tag_archive',
                        'title' => 'Tag: ' . $tag->name,
                        'id' => $tag->term_id
                    ];
                }
            }
        }
        
        // Remove duplicates and apply exclusions
        $unique_urls = [];
        $seen_urls = [];
        
        foreach ($urls as $url_data) {
            $url = $url_data['url'];
            if (!in_array($url, $seen_urls) && $this->should_include_url($url, $options)) {
                $seen_urls[] = $url;
                $unique_urls[] = $url_data;
            }
        }
        
        return $unique_urls;
    }
    
    /**
     * Check if URL should be included in scan
     */
    private function should_include_url($url, $options) {
        // Check exclusions
        foreach ($options['exclude_urls'] as $exclude_pattern) {
            if (strpos($url, $exclude_pattern) !== false) {
                return false;
            }
        }
        
        // Check for noindex if respecting it
        if ($options['respect_noindex']) {
            // This would require fetching the page to check meta robots
            // For now, skip admin URLs and known system URLs
            if (strpos($url, '/wp-admin/') !== false || 
                strpos($url, '/wp-includes/') !== false ||
                strpos($url, '/wp-content/') !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Process scan queue in background
     */
    public function process_scan_queue($session_id) {
        $scan_info = get_option('raywp_scan_session_' . $session_id);
        
        if (!$scan_info || $scan_info['status'] !== 'running') {
            return;
        }
        
        $accessibility_checker = new \RayWP\Accessibility\Frontend\Accessibility_Checker();
        $batch_size = 3; // Process 3 URLs per batch to avoid timeouts
        $processed = 0;
        
        // Process next batch of URLs
        for ($i = $scan_info['current_url_index']; $i < count($scan_info['urls']) && $processed < $batch_size; $i++) {
            $url_data = $scan_info['urls'][$i];
            
            try {
                // Scan the URL
                $scan_result = $accessibility_checker->generate_report($url_data['url']);
                
                if (isset($scan_result['error'])) {
                    $scan_info['failed_urls']++;
                    error_log("RayWP Site Scanner: Failed to scan {$url_data['url']}: {$scan_result['error']}");
                } else {
                    $scan_info['completed_urls']++;
                    
                    // Save results with enhanced metadata
                    $plugin = \RayWP\Accessibility\Core\Plugin::get_instance();
                    $reports = $plugin->get_component('reports');
                    
                    if ($reports) {
                        // Add page metadata to scan result
                        $scan_result['page_type'] = $url_data['type'];
                        $scan_result['page_title'] = $url_data['title'];
                        $scan_result['page_id'] = $url_data['id'] ?? null;
                        
                        $reports->save_scan_results($scan_result, $session_id);
                    }
                }
                
                $processed++;
                
            } catch (Exception $e) {
                $scan_info['failed_urls']++;
                error_log("RayWP Site Scanner: Exception scanning {$url_data['url']}: " . $e->getMessage());
            }
            
            $scan_info['current_url_index'] = $i + 1;
        }
        
        // Update scan info
        if ($scan_info['current_url_index'] >= count($scan_info['urls'])) {
            // Scan complete
            $scan_info['status'] = 'completed';
            $scan_info['end_time'] = current_time('mysql');
            $scan_info['completion_percentage'] = 100;
        } else {
            // Continue processing
            $scan_info['completion_percentage'] = round(($scan_info['current_url_index'] / count($scan_info['urls'])) * 100);
            
            // Schedule next batch
            wp_schedule_single_event(time() + 10, 'raywp_process_scan_queue', [$session_id]);
        }
        
        update_option('raywp_scan_session_' . $session_id, $scan_info);
        
        // Clear caches after processing
        wp_cache_flush();
    }
    
    /**
     * Get scan progress
     */
    public function get_scan_progress($session_id) {
        $scan_info = get_option('raywp_scan_session_' . $session_id);
        
        if (!$scan_info) {
            return [
                'found' => false,
                'message' => 'Scan session not found'
            ];
        }
        
        return [
            'found' => true,
            'session_id' => $session_id,
            'status' => $scan_info['status'],
            'total_urls' => $scan_info['total_urls'],
            'completed_urls' => $scan_info['completed_urls'],
            'failed_urls' => $scan_info['failed_urls'],
            'completion_percentage' => $scan_info['completion_percentage'] ?? 0,
            'current_url_index' => $scan_info['current_url_index'] ?? 0,
            'current_url' => isset($scan_info['urls'][$scan_info['current_url_index']]) ? 
                            $scan_info['urls'][$scan_info['current_url_index']]['url'] : null,
            'start_time' => $scan_info['start_time'],
            'end_time' => $scan_info['end_time'] ?? null
        ];
    }
    
    /**
     * Cancel scan
     */
    public function cancel_scan($session_id) {
        $scan_info = get_option('raywp_scan_session_' . $session_id);
        
        if ($scan_info) {
            $scan_info['status'] = 'cancelled';
            $scan_info['end_time'] = current_time('mysql');
            update_option('raywp_scan_session_' . $session_id, $scan_info);
            
            // Clear any scheduled events
            wp_clear_scheduled_hook('raywp_process_scan_queue', [$session_id]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * AJAX handler for starting site scan
     */
    public function handle_start_site_scan() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'raywp_accessibility_nonce')) {
            wp_die(json_encode(['success' => false, 'message' => 'Unauthorized']));
        }
        
        $options = [];
        
        if (!empty($_POST['scan_type'])) {
            $options['scan_type'] = sanitize_text_field($_POST['scan_type']);
        }
        
        if (!empty($_POST['max_pages'])) {
            $options['max_pages'] = intval($_POST['max_pages']);
        }
        
        if (isset($_POST['include_posts'])) {
            $options['include_posts'] = (bool) $_POST['include_posts'];
        }
        
        if (isset($_POST['include_pages'])) {
            $options['include_pages'] = (bool) $_POST['include_pages'];
        }
        
        if (isset($_POST['include_archives'])) {
            $options['include_archives'] = (bool) $_POST['include_archives'];
        }
        
        $result = $this->start_site_scan($options);
        
        wp_die(json_encode($result));
    }
    
    /**
     * AJAX handler for checking scan progress
     */
    public function handle_check_scan_progress() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'raywp_accessibility_nonce')) {
            wp_die(json_encode(['success' => false, 'message' => 'Unauthorized']));
        }
        
        if (empty($_POST['session_id'])) {
            wp_die(json_encode(['success' => false, 'message' => 'Session ID required']));
        }
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $progress = $this->get_scan_progress($session_id);
        
        wp_die(json_encode($progress));
    }
    
    /**
     * AJAX handler for canceling scan
     */
    public function handle_cancel_scan() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'raywp_accessibility_nonce')) {
            wp_die(json_encode(['success' => false, 'message' => 'Unauthorized']));
        }
        
        if (empty($_POST['session_id'])) {
            wp_die(json_encode(['success' => false, 'message' => 'Session ID required']));
        }
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $success = $this->cancel_scan($session_id);
        
        wp_die(json_encode(['success' => $success]));
    }
    
    /**
     * Get available post types for scanning
     */
    public function get_available_post_types() {
        $post_types = get_post_types(['public' => true], 'objects');
        $available = [];
        
        foreach ($post_types as $post_type) {
            if (!in_array($post_type->name, ['attachment', 'revision'])) {
                $available[$post_type->name] = $post_type->label;
            }
        }
        
        return $available;
    }
    
    /**
     * Clean up old scan sessions (keep only last 10)
     */
    public function cleanup_old_sessions() {
        global $wpdb;
        
        // Get all scan session options
        $scan_options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE 'raywp_scan_session_%' 
             ORDER BY option_id DESC"
        );
        
        if (count($scan_options) > 10) {
            // Keep only the 10 most recent
            $options_to_delete = array_slice($scan_options, 10);
            
            foreach ($options_to_delete as $option) {
                delete_option($option->option_name);
            }
        }
    }
}
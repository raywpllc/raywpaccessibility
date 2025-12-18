/**
 * Automatic contrast detection integration
 * Runs on page load and sends results to WordPress backend
 */
(function($) {
    'use strict';

    // Auto-run contrast detection when the page loads
    $(document).ready(function() {
        try {
            // Only run if we have the contrast detector available
            if (typeof ContrastDetector === 'undefined') {
                return;
            }

            // Only run on scanning requests or admin pages with special parameter
            const urlParams = new URLSearchParams(window.location.search);
            const shouldScan = urlParams.get('raywp_scan_contrast') === '1' ||
                              window.location.pathname.indexOf('wp-admin') !== -1;

            if (!shouldScan) {
                return;
            }

            // Run contrast detection after page is fully rendered
            setTimeout(function() {
                runContrastDetection();
            }, 1000);

        } catch (error) {
            console.warn('Error initializing contrast detection:', error);
        }
    });

    function runContrastDetection() {
        try {
            console.log('Running contrast detection...');
            const detector = new ContrastDetector();
            const results = detector.detectContrastIssues();
            console.log('Contrast detection results:', results);

            // If we found issues, send them to the backend
            if (results && results.length > 0) {
                console.log('Found', results.length, 'contrast issues, sending to backend...');
                sendContrastResults(results);
            } else {
                console.log('No contrast issues found');
                // Store empty results to indicate scan was performed
                storeContrastResults([]);
            }

        } catch (error) {
            console.warn('Error running contrast detection:', error);
        }
    }

    function sendContrastResults(results) {
        // Check if we have AJAX settings available
        if (typeof raywp_admin_ajax === 'undefined') {
            // Store results in sessionStorage as fallback
            storeContrastResults(results);
            return;
        }

        // Send results via AJAX
        $.ajax({
            url: raywp_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'raywp_run_contrast_check',
                nonce: raywp_admin_ajax.nonce,
                url: window.location.href,
                contrast_results: results
            },
            success: function(response) {
                console.log('Contrast results sent successfully:', response);
                
                // Also store in cache for immediate retrieval
                storeContrastResults(results);
            },
            error: function(xhr, status, error) {
                console.warn('Failed to send contrast results:', error);
                
                // Fallback to storage
                storeContrastResults(results);
            }
        });
    }

    function storeContrastResults(results) {
        // Store in sessionStorage for immediate access
        try {
            const storageKey = 'raywp_contrast_results_' + encodeURIComponent(window.location.pathname);
            sessionStorage.setItem(storageKey, JSON.stringify(results));
        } catch (e) {
            console.warn('Could not store contrast results in sessionStorage:', e);
        }

        // Also send to backend cache via a simple POST
        if (typeof raywp_admin_ajax !== 'undefined') {
            navigator.sendBeacon(raywp_admin_ajax.ajax_url, new URLSearchParams({
                action: 'raywp_store_contrast_results',
                nonce: raywp_admin_ajax.nonce,
                url: window.location.href,
                results: JSON.stringify(results)
            }));
        }
    }

})(jQuery);
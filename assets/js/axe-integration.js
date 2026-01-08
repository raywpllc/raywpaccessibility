/**
 * RayWP Accessibility - Axe Integration
 * Handles "Check Score With Fixes" functionality
 *
 * This script manages dual scanning: comparing accessibility scores
 * with and without the plugin's auto-fixes applied.
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        selectors: {
            checkFixedScoreBtn: '#check-fixed-score',
            runFullScanBtn: '#run-full-scan',
            scanResultsContainer: '.raywp-scan-results',
            liveScoreDisplay: '.raywp-live-score-value',
            originalScoreDisplay: '.raywp-original-score-value',
            progressContainer: '#scan-progress-container',
            fixedIssuesContainer: '#fixed-issues-breakdown',
            remainingIssuesContainer: '#remaining-issues-breakdown'
        },
        ajaxActions: {
            scanWithFixes: 'raywp_accessibility_scan_with_fixes',
            storeLiveScore: 'raywp_accessibility_store_live_score',
            getPagesList: 'raywp_accessibility_get_pages_list'
        }
    };

    /**
     * Initialize the axe integration
     */
    function init() {
        // Show the "Check Score With Fixes" button
        $(CONFIG.selectors.checkFixedScoreBtn).show();

        // Bind event handlers
        bindEvents();

        // Check for stored results and display them
        loadStoredResults();
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Check Score With Fixes button click
        $(document).on('click', CONFIG.selectors.checkFixedScoreBtn, handleCheckFixedScore);
    }

    /**
     * Handle "Check Score With Fixes" button click
     */
    function handleCheckFixedScore(e) {
        e.preventDefault();

        const $button = $(this);
        const originalText = $button.text();

        // Disable button and show progress
        $button.prop('disabled', true);
        showProgress('Initializing dual scan...', 0);

        // Call the AJAX endpoint
        $.ajax({
            url: raywpAccessibility.ajaxurl,
            type: 'POST',
            data: {
                action: CONFIG.ajaxActions.scanWithFixes,
                nonce: raywpAccessibility.nonce
            },
            timeout: 180000, // 3 minute timeout for dual scan
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                // Progress tracking (if server supports it)
                xhr.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        updateProgress('Scanning...', percentComplete);
                    }
                });
                return xhr;
            },
            beforeSend: function() {
                showProgress('Scanning pages with and without fixes...', 10);
            },
            success: function(response) {
                hideProgress();
                $button.prop('disabled', false).text(originalText);

                if (response.success) {
                    displayScanResults(response.data);
                    showSuccessMessage('Scan complete! Found ' + response.data.total_issues + ' remaining issues.');
                } else {
                    showErrorMessage('Scan failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                hideProgress();
                $button.prop('disabled', false).text(originalText);

                let errorMsg = 'Scan request failed';
                if (status === 'timeout') {
                    errorMsg = 'Scan timed out. Try scanning fewer pages.';
                } else if (error) {
                    errorMsg += ': ' + error;
                }
                showErrorMessage(errorMsg);
            }
        });
    }

    /**
     * Display scan results in the UI
     */
    function displayScanResults(data) {
        console.log('Scan results:', data);

        // Update Live Score display
        updateLiveScoreDisplay(data.fixed_score);

        // Build and display the results HTML
        const resultsHtml = buildResultsHtml(data);

        // Find or create results container
        let $container = $(CONFIG.selectors.scanResultsContainer);
        if ($container.length === 0) {
            $container = $('<div class="raywp-scan-results"></div>');
            $('.raywp-reports').append($container);
        }

        $container.html(resultsHtml);

        // Store the live score for persistence
        storeLiveScore(data.fixed_score);
    }

    /**
     * Build the HTML for displaying results
     */
    function buildResultsHtml(data) {
        let html = '';

        // Summary section
        html += '<div class="raywp-scan-summary" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e9ecef;">';
        html += '<h3 style="margin-top: 0;">Scan Results</h3>';
        html += '<p><strong>Live Score (with fixes):</strong> ' + data.fixed_score + '%</p>';
        html += '<p><strong>Pages Scanned:</strong> ' + data.pages_scanned + '</p>';
        html += '<p><strong>Remaining Issues:</strong> ' + data.total_issues + '</p>';
        if (data.timestamp) {
            html += '<p><small>Last scan: ' + data.timestamp + '</small></p>';
        }
        html += '</div>';

        // Issue breakdown
        if (data.issue_breakdown) {
            // Fixed issues (auto-fixed by plugin)
            if (data.issue_breakdown.fixed && data.issue_breakdown.fixed.length > 0) {
                html += buildIssueSection(
                    'Auto-Fixed Issues',
                    data.issue_breakdown.fixed,
                    'fixed',
                    'These issues were automatically fixed by the plugin.'
                );
            }

            // Remaining issues (need manual attention)
            if (data.issue_breakdown.remaining && data.issue_breakdown.remaining.length > 0) {
                html += buildIssueSection(
                    'Remaining Issues',
                    data.issue_breakdown.remaining,
                    'remaining',
                    'These issues require manual attention.'
                );
            }

            // Unfixable issues (should have been fixed but weren\'t)
            if (data.issue_breakdown.unfixable && data.issue_breakdown.unfixable.length > 0) {
                html += buildIssueSection(
                    'Unfixable Issues',
                    data.issue_breakdown.unfixable,
                    'unfixable',
                    'These issues could not be automatically fixed.'
                );
            }
        }

        // Page-by-page breakdown
        if (data.details && data.details.length > 0) {
            html += '<div class="raywp-page-breakdown" style="margin-top: 20px;">';
            html += '<h4>Page-by-Page Breakdown</h4>';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>Page</th><th>Original Issues</th><th>Remaining Issues</th><th>Fixed</th></tr></thead>';
            html += '<tbody>';

            data.details.forEach(function(page) {
                const fixed = page.original_issues - page.remaining_issues;
                const fixedClass = fixed > 0 ? 'style="color: #28a745;"' : '';
                html += '<tr>';
                html += '<td><a href="' + escapeHtml(page.url) + '" target="_blank">' + escapeHtml(page.title || page.url) + '</a></td>';
                html += '<td>' + page.original_issues + '</td>';
                html += '<td>' + page.remaining_issues + '</td>';
                html += '<td ' + fixedClass + '>' + (fixed > 0 ? '+' + fixed + ' fixed' : '0') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '</div>';
        }

        return html;
    }

    /**
     * Build a collapsible section for a category of issues
     */
    function buildIssueSection(title, issues, type, description) {
        const bgColors = {
            fixed: '#d4edda',
            remaining: '#fff3cd',
            unfixable: '#f8d7da'
        };
        const borderColors = {
            fixed: '#28a745',
            remaining: '#ffc107',
            unfixable: '#dc3545'
        };
        const icons = {
            fixed: '✓',
            remaining: '⚠',
            unfixable: '✗'
        };

        // Group issues by type for summary
        const issuesByType = {};
        issues.forEach(function(issue) {
            const issueType = issue.type || 'unknown';
            if (!issuesByType[issueType]) {
                issuesByType[issueType] = {
                    count: 0,
                    sample: issue
                };
            }
            issuesByType[issueType].count++;
        });

        let html = '<div class="raywp-issue-section" style="margin: 20px 0; padding: 15px; background: ' + bgColors[type] + '; border-left: 4px solid ' + borderColors[type] + '; border-radius: 4px;">';
        html += '<h4 style="margin: 0 0 10px 0; cursor: pointer;" onclick="raywpToggleSection(this)">';
        html += '<span>' + icons[type] + '</span> ' + title + ' (' + issues.length + ' total)';
        html += ' <span class="toggle-indicator">▼</span>';
        html += '</h4>';
        html += '<p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">' + description + '</p>';

        html += '<div class="issue-details-list" style="display: none;">';

        // Show summary by type
        html += '<table class="wp-list-table widefat fixed striped" style="background: #fff;">';
        html += '<thead><tr><th>Issue Type</th><th>Count</th><th>Sample Page</th></tr></thead>';
        html += '<tbody>';

        Object.keys(issuesByType).forEach(function(issueType) {
            const data = issuesByType[issueType];
            const displayType = formatIssueType(issueType);
            html += '<tr>';
            html += '<td>' + escapeHtml(displayType) + '</td>';
            html += '<td>' + data.count + '</td>';
            html += '<td>';
            if (data.sample.page_title) {
                html += '<a href="' + escapeHtml(data.sample.page_url || '#') + '" target="_blank">' + escapeHtml(data.sample.page_title) + '</a>';
            } else if (data.sample.page_url) {
                html += '<a href="' + escapeHtml(data.sample.page_url) + '" target="_blank">' + escapeHtml(data.sample.page_url) + '</a>';
            } else {
                html += '-';
            }
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '</div>';
        html += '</div>';

        return html;
    }

    /**
     * Format issue type for display
     */
    function formatIssueType(type) {
        return type
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }

    /**
     * Update the Live Score display in the UI
     */
    function updateLiveScoreDisplay(score) {
        // Find the live score display element
        const $liveScore = $(CONFIG.selectors.liveScoreDisplay);
        if ($liveScore.length > 0) {
            $liveScore.text(score + '%');

            // Update color based on score
            let color = '#dc3545'; // red
            if (score >= 90) {
                color = '#28a745'; // green
            } else if (score >= 70) {
                color = '#ffc107'; // yellow
            }
            $liveScore.css('color', color);
        }

        // Also update any other score displays on the page
        $('.raywp-live-score').text(score + '%');
    }

    /**
     * Store the live score via AJAX for persistence
     */
    function storeLiveScore(score) {
        $.ajax({
            url: raywpAccessibility.ajaxurl,
            type: 'POST',
            data: {
                action: CONFIG.ajaxActions.storeLiveScore,
                nonce: raywpAccessibility.nonce,
                live_score: score
            },
            success: function(response) {
                if (response.success) {
                    console.log('Live score stored successfully');
                }
            },
            error: function() {
                console.warn('Failed to store live score');
            }
        });
    }

    /**
     * Load and display any stored results
     */
    function loadStoredResults() {
        // Results are loaded via PHP, but we can enhance the display here
        // The PHP already outputs stored results, this function can add interactivity

        // Add click handlers for collapsible sections
        initCollapsibleSections();
    }

    /**
     * Initialize collapsible sections
     */
    function initCollapsibleSections() {
        // Make issue section headers clickable to expand/collapse
        $(document).on('click', '.raywp-issue-section h4', function() {
            const $details = $(this).siblings('.issue-details-list');
            const $indicator = $(this).find('.toggle-indicator');

            $details.slideToggle(200);
            $indicator.text($details.is(':visible') ? '▲' : '▼');
        });
    }

    /**
     * Show progress indicator
     */
    function showProgress(message, percent) {
        let $container = $(CONFIG.selectors.progressContainer);

        if ($container.length === 0) {
            $container = $('<div id="scan-progress-container" style="margin: 15px 0; padding: 15px; background: #e7f3ff; border-radius: 8px; border: 1px solid #b8daff;"></div>');
            $(CONFIG.selectors.checkFixedScoreBtn).after($container);
        }

        let progressHtml = '<div class="scan-progress">';
        progressHtml += '<p style="margin: 0 0 10px 0;"><strong>' + escapeHtml(message) + '</strong></p>';
        progressHtml += '<div class="progress-bar-container" style="background: #dee2e6; border-radius: 4px; height: 20px; overflow: hidden;">';
        progressHtml += '<div class="progress-bar" style="background: #007cba; height: 100%; width: ' + percent + '%; transition: width 0.3s;"></div>';
        progressHtml += '</div>';
        progressHtml += '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">This may take a minute as we scan each page twice (with and without fixes)...</p>';
        progressHtml += '</div>';

        $container.html(progressHtml).show();
    }

    /**
     * Update progress indicator
     */
    function updateProgress(message, percent) {
        const $container = $(CONFIG.selectors.progressContainer);
        if ($container.length > 0) {
            $container.find('.progress-bar').css('width', percent + '%');
            $container.find('p:first strong').text(message);
        }
    }

    /**
     * Hide progress indicator
     */
    function hideProgress() {
        $(CONFIG.selectors.progressContainer).slideUp(200, function() {
            $(this).remove();
        });
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        showNotice(message, 'success');
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        showNotice(message, 'error');
    }

    /**
     * Show a notice message
     */
    function showNotice(message, type) {
        const bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
        const borderColor = type === 'success' ? '#28a745' : '#dc3545';
        const textColor = type === 'success' ? '#155724' : '#721c24';

        const $notice = $('<div class="raywp-notice" style="padding: 12px 15px; margin: 15px 0; background: ' + bgColor + '; border-left: 4px solid ' + borderColor + '; color: ' + textColor + '; border-radius: 4px;">' + escapeHtml(message) + '</div>');

        $(CONFIG.selectors.checkFixedScoreBtn).closest('div').after($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Global function to toggle issue sections (called from onclick)
     */
    window.raywpToggleSection = function(header) {
        const $header = $(header);
        const $details = $header.siblings('.issue-details-list');
        const $indicator = $header.find('.toggle-indicator');

        $details.slideToggle(200);

        // Update indicator after animation
        setTimeout(function() {
            $indicator.text($details.is(':visible') ? '▲' : '▼');
        }, 200);
    };

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);

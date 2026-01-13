/**
 * RayWP Accessibility - Axe Integration
 * Handles "Check Score With Fixes" functionality
 *
 * This script uses real browser-based axe-core scanning via iframes
 * to accurately detect accessibility issues in the rendered DOM.
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
            getPagesList: 'raywp_accessibility_get_pages_list',
            processAxeResults: 'raywp_accessibility_process_axe_results',
            storeLiveScore: 'raywp_accessibility_store_live_score'
        }
    };

    // Scanner instance
    let scanner = null;

    /**
     * Initialize the axe integration
     */
    function init() {
        // Show the "Check Score With Fixes" button
        $(CONFIG.selectors.checkFixedScoreBtn).show();

        // Initialize the iframe scanner
        if (window.RayWPIframeScanner) {
            scanner = new window.RayWPIframeScanner();
            scanner.init();
        } else {
            console.error('RayWP: IframeScanner not loaded');
        }

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
     * Uses browser-based axe-core scanning via iframes
     */
    async function handleCheckFixedScore(e) {
        e.preventDefault();

        if (!scanner) {
            showErrorMessage('Scanner not initialized. Please refresh the page and try again.');
            return;
        }

        const $button = $(this);
        const originalText = $button.text();

        // Disable button and show progress
        $button.prop('disabled', true);
        showProgress('Getting list of pages to scan...', 0);

        try {
            // Step 1: Get list of pages to scan from server
            const pages = await getPagesList();

            if (!pages || pages.length === 0) {
                throw new Error('No pages found to scan');
            }

            showProgress(`Found ${pages.length} pages to scan with axe-core...`, 5);

            // Step 2: Scan each page using iframe + axe-core
            const scanResults = await scanner.scanMultiplePages(pages, (current, total, title, status) => {
                const percent = 5 + ((current / total) * 85); // 5-90%
                let message = `Scanning page ${current} of ${total}: ${title}`;
                if (status && status !== 'scanning') {
                    message += ` (${status})`;
                }
                showProgress(message, percent);
            });

            showProgress('Processing results...', 92);

            // DEBUG: Log raw axe-core results
            console.group('RayWP Accessibility Scan Debug');
            console.log('Raw scanResults:', scanResults);
            console.log('Violations by type:', scanResults.violationsByType);
            console.log('Pages scanned:', scanResults.pages.length);
            console.log('Total violations count:', scanResults.totalViolations);

            // Step 3: Convert results to our internal format
            const convertedResults = scanner.convertToInternalFormat(scanResults);

            // DEBUG: Log converted results
            console.log('Converted results (internal format):', convertedResults);
            if (convertedResults.issues) {
                console.log('Issue types found:', [...new Set(convertedResults.issues.map(i => i.type))]);
            }
            console.groupEnd();

            // Step 4: Send results to server for processing
            showProgress('Saving results...', 95);
            const processedResults = await processAxeResults(convertedResults, scanResults);

            hideProgress();
            $button.prop('disabled', false).text(originalText);

            // Display results
            displayScanResults(processedResults);
            showSuccessMessage(`Scan complete! Scanned ${scanResults.pages.length} pages with axe-core.`);

        } catch (error) {
            hideProgress();
            $button.prop('disabled', false).text(originalText);
            showErrorMessage('Scan failed: ' + error.message);
            console.error('Scan error:', error);
        }
    }

    /**
     * Get list of pages to scan from server
     */
    function getPagesList() {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: raywpAccessibility.ajaxurl,
                type: 'POST',
                data: {
                    action: CONFIG.ajaxActions.getPagesList,
                    nonce: raywpAccessibility.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.pages) {
                        resolve(response.data.pages);
                    } else {
                        reject(new Error(response.data || 'Failed to get pages list'));
                    }
                },
                error: function(xhr, status, error) {
                    reject(new Error('AJAX error: ' + error));
                }
            });
        });
    }

    /**
     * Send axe-core results to server for processing
     */
    function processAxeResults(convertedResults, rawResults) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: raywpAccessibility.ajaxurl,
                type: 'POST',
                data: {
                    action: CONFIG.ajaxActions.processAxeResults,
                    nonce: raywpAccessibility.nonce,
                    results: JSON.stringify(convertedResults),
                    raw_results: JSON.stringify({
                        pages_scanned: rawResults.pages.length,
                        total_violations: rawResults.totalViolations,
                        violations_by_type: rawResults.violationsByType,
                        duration: rawResults.duration
                    })
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Failed to process results'));
                    }
                },
                error: function(xhr, status, error) {
                    reject(new Error('AJAX error: ' + error));
                }
            });
        });
    }

    /**
     * Display scan results in the UI
     */
    function displayScanResults(data) {
        console.log('Scan results:', data);

        // Update Live Score display
        updateLiveScoreDisplay(data.fixed_score);

        // Also update Original Score if provided
        if (data.original_score !== undefined) {
            updateOriginalScoreDisplay(data.original_score);
        }

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

        // Reload page to show updated "Requires Manual Attention" section
        setTimeout(() => {
            if (confirm('Scan complete! Reload page to see updated results?')) {
                window.location.reload();
            }
        }, 500);
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
        if (data.original_score !== undefined) {
            html += '<p><strong>Original Score (before fixes):</strong> ' + data.original_score + '%</p>';
        }
        html += '<p><strong>Pages Scanned:</strong> ' + data.pages_scanned + '</p>';
        html += '<p><strong>Total Issues Found:</strong> ' + data.total_issues + '</p>';
        if (data.timestamp) {
            html += '<p><small>Last scan: ' + data.timestamp + '</small></p>';
        }
        html += '<p style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-radius: 4px; font-size: 13px;">';
        html += '<strong>Note:</strong> This scan used axe-core to analyze the actual rendered pages, ';
        html += 'including all JavaScript and CSS. Results reflect real accessibility issues in the live site.';
        html += '</p>';
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
                    'Remaining Issues (Require Manual Attention)',
                    data.issue_breakdown.remaining,
                    'remaining',
                    'These issues were found by axe-core and require manual fixes.'
                );
            }
        }

        // Page-by-page breakdown
        if (data.details && data.details.length > 0) {
            html += '<div class="raywp-page-breakdown" style="margin-top: 20px;">';
            html += '<h4>Page-by-Page Breakdown</h4>';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>Page</th><th>Issues</th><th>Status</th></tr></thead>';
            html += '<tbody>';

            data.details.forEach(function(page) {
                const statusClass = page.success ? 'style="color: #28a745;"' : 'style="color: #dc3545;"';
                html += '<tr>';
                html += '<td><a href="' + escapeHtml(page.url) + '" target="_blank">' + escapeHtml(page.title || page.url) + '</a></td>';
                html += '<td>' + (page.violations ? page.violations.length : 0) + '</td>';
                html += '<td ' + statusClass + '>' + (page.success ? 'Scanned' : 'Error: ' + escapeHtml(page.error || 'Unknown')) + '</td>';
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
            const issueType = issue.type || issue.axe_id || 'unknown';
            if (!issuesByType[issueType]) {
                issuesByType[issueType] = {
                    count: 0,
                    sample: issue,
                    severity: issue.severity || 'medium',
                    help_url: issue.help_url || null
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
        html += '<thead><tr><th>Issue Type</th><th>Severity</th><th>Count</th><th>Help</th></tr></thead>';
        html += '<tbody>';

        Object.keys(issuesByType).forEach(function(issueType) {
            const data = issuesByType[issueType];
            const displayType = formatIssueType(issueType);
            const severityBadge = getSeverityBadge(data.severity);

            html += '<tr>';
            html += '<td>' + escapeHtml(displayType) + '</td>';
            html += '<td>' + severityBadge + '</td>';
            html += '<td>' + data.count + '</td>';
            html += '<td>';
            if (data.help_url) {
                html += '<a href="' + escapeHtml(data.help_url) + '" target="_blank" rel="noopener">Learn more</a>';
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
     * Get severity badge HTML
     */
    function getSeverityBadge(severity) {
        const colors = {
            critical: { bg: '#dc3545', text: '#fff' },
            high: { bg: '#dc3545', text: '#fff' },
            serious: { bg: '#dc3545', text: '#fff' },
            medium: { bg: '#ffc107', text: '#212529' },
            moderate: { bg: '#ffc107', text: '#212529' },
            low: { bg: '#6c757d', text: '#fff' },
            minor: { bg: '#6c757d', text: '#fff' }
        };
        const color = colors[severity] || colors.medium;
        return '<span style="background: ' + color.bg + '; color: ' + color.text + '; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">' + escapeHtml(severity.charAt(0).toUpperCase() + severity.slice(1)) + '</span>';
    }

    /**
     * Format issue type for display
     */
    function formatIssueType(type) {
        return type
            .replace(/_/g, ' ')
            .replace(/-/g, ' ')
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
     * Update the Original Score display in the UI
     */
    function updateOriginalScoreDisplay(score) {
        const $originalScore = $(CONFIG.selectors.originalScoreDisplay);
        if ($originalScore.length > 0) {
            $originalScore.text(score + '%');

            let color = '#dc3545';
            if (score >= 90) {
                color = '#28a745';
            } else if (score >= 70) {
                color = '#ffc107';
            }
            $originalScore.css('color', color);
        }
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
        initCollapsibleSections();
    }

    /**
     * Initialize collapsible sections
     */
    function initCollapsibleSections() {
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
        progressHtml += '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">Using axe-core to scan the actual rendered pages...</p>';
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

        setTimeout(function() {
            $indicator.text($details.is(':visible') ? '▲' : '▼');
        }, 200);
    };

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);

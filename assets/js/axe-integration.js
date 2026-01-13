/**
 * RayWP Accessibility - Axe Integration
 * Handles unified accessibility scanning with before/after comparison
 *
 * This script uses real browser-based axe-core scanning via iframes
 * to accurately detect accessibility issues in the rendered DOM.
 * It performs a two-pass scan: baseline (without fixes) then with fixes applied.
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        selectors: {
            runFullScanBtn: '#run-full-scan',
            checkFixedScoreBtn: '#check-fixed-score', // Will be hidden
            scanResultsContainer: '.raywp-scan-results',
            baselineScoreDisplay: '.raywp-baseline-score-value',
            fixedScoreDisplay: '.raywp-fixed-score-value',
            progressContainer: '#scan-progress-container',
            fixedIssuesContainer: '#fixed-issues-breakdown',
            remainingIssuesContainer: '#remaining-issues-breakdown'
        },
        ajaxActions: {
            getPagesList: 'raywp_accessibility_get_pages_list',
            saveComparisonScan: 'raywp_accessibility_save_comparison_scan'
        }
    };

    // Scanner instance
    let scanner = null;

    /**
     * Initialize the axe integration
     */
    function init() {
        // Hide the old "Check Score With Fixes" button - we now use a single unified scan
        $(CONFIG.selectors.checkFixedScoreBtn).hide();

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
        // Run Full Scan button - now uses axe-core two-pass scanning
        $(document).on('click', CONFIG.selectors.runFullScanBtn, handleRunFullScan);
    }

    /**
     * Handle "Run Full Scan" button click (renamed from handleCheckFixedScore)
     * Uses browser-based axe-core scanning via iframes with two passes:
     * 1. Baseline scan (without fixes) to detect all issues
     * 2. Fixed scan (with fixes) to see remaining issues
     */
    async function handleRunFullScan(e) {
        e.preventDefault();

        if (!scanner) {
            showErrorMessage('Scanner not initialized. Please refresh the page and try again.');
            return;
        }

        const $button = $(this);
        const originalText = $button.text();

        // Disable button and show progress
        $button.prop('disabled', true);
        showProgress('Getting list of pages to scan...', 0, 1, 0);

        try {
            // Step 1: Get list of pages to scan from server
            const pages = await getPagesList();

            if (!pages || pages.length === 0) {
                throw new Error('No pages found to scan');
            }

            showProgress(`Found ${pages.length} pages. Starting two-pass axe-core scan...`, 2, 1, 0);

            // Step 2: Run the full comparison scan (baseline + with fixes)
            const comparisonResults = await scanner.runFullComparisonScan(pages, (phase, current, total, title, status) => {
                const phaseLabel = phase === 1 ? 'Baseline (without fixes)' : 'With fixes applied';
                const phasePercent = phase === 1 ? 0 : 50;
                const percent = phasePercent + ((current / total) * 45);

                let message = `Phase ${phase}/2: ${phaseLabel}`;
                if (current > 0) {
                    message += ` - Page ${current}/${total}: ${title}`;
                    if (status && status !== 'scanning') {
                        message += ` (${status})`;
                    }
                }
                showProgress(message, percent, phase, current);
            });

            showProgress('Processing results...', 95, 2, pages.length);

            // DEBUG: Log comparison results
            console.group('RayWP Accessibility Comparison Scan Debug');
            console.log('Comparison Results:', comparisonResults);
            console.log('Baseline Score:', comparisonResults.improvement.baselineScore);
            console.log('Fixed Score:', comparisonResults.improvement.fixedScore);
            console.log('Issues Fixed:', comparisonResults.improvement.issuesFixed);
            console.groupEnd();

            // Step 3: Save results to server
            showProgress('Saving results...', 98, 2, pages.length);
            await saveComparisonScan(comparisonResults);

            hideProgress();
            $button.prop('disabled', false).text(originalText);

            // Display results
            displayComparisonResults(comparisonResults);
            showSuccessMessage(`Scan complete! Scanned ${pages.length} pages twice (baseline + with fixes).`);

            // Reload page after brief delay to show updated UI
            setTimeout(() => {
                if (confirm('Scan complete! Reload page to see updated results?')) {
                    window.location.reload();
                }
            }, 500);

        } catch (error) {
            hideProgress();
            $button.prop('disabled', false).text(originalText);
            showErrorMessage('Scan failed: ' + error.message);
            console.error('Scan error:', error);
        }
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
     * Send axe-core results to server for processing (legacy - for single scan)
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
     * Save comparison scan results (baseline + with fixes) to server
     */
    function saveComparisonScan(comparisonResults) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: raywpAccessibility.ajaxurl,
                type: 'POST',
                data: {
                    action: CONFIG.ajaxActions.saveComparisonScan,
                    nonce: raywpAccessibility.nonce,
                    baseline: JSON.stringify({
                        score: comparisonResults.improvement.baselineScore,
                        total_issues: comparisonResults.baseline.totalViolations,
                        pages_scanned: comparisonResults.baseline.pages.length,
                        violations_by_type: comparisonResults.baseline.violationsByType,
                        duration: comparisonResults.baseline.duration
                    }),
                    with_fixes: JSON.stringify({
                        score: comparisonResults.improvement.fixedScore,
                        total_issues: comparisonResults.withFixes.totalViolations,
                        pages_scanned: comparisonResults.withFixes.pages.length,
                        violations_by_type: comparisonResults.withFixes.violationsByType,
                        duration: comparisonResults.withFixes.duration
                    }),
                    improvement: JSON.stringify(comparisonResults.improvement)
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        // Handle error response - could be string or object
                        let errorMsg = 'Failed to save comparison results';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            } else if (response.data.message) {
                                errorMsg = response.data.message;
                            } else {
                                errorMsg = JSON.stringify(response.data);
                            }
                        }
                        console.error('Save comparison scan failed:', response);
                        reject(new Error(errorMsg));
                    }
                },
                error: function(xhr, status, error) {
                    reject(new Error('AJAX error: ' + error));
                }
            });
        });
    }

    /**
     * Display comparison scan results (before/after)
     */
    function displayComparisonResults(comparisonResults) {
        const { baseline, withFixes, improvement } = comparisonResults;

        // Build comparison display HTML
        let html = '<div class="raywp-comparison-results" style="margin: 20px 0;">';

        // Score comparison header
        html += '<div class="raywp-score-comparison" style="display: flex; align-items: center; justify-content: center; gap: 40px; padding: 30px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; margin-bottom: 20px;">';

        // Before score
        const beforeColor = getScoreColor(improvement.baselineScore);
        html += '<div class="before-score" style="text-align: center;">';
        html += '<div style="font-size: 14px; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;">Before Auto-Fixes</div>';
        html += '<div style="font-size: 48px; font-weight: bold; color: ' + beforeColor + ';">' + improvement.baselineScore + '%</div>';
        html += '<div style="font-size: 13px; color: #888;">(' + baseline.totalViolations + ' issues)</div>';
        html += '</div>';

        // Arrow
        html += '<div style="font-size: 32px; color: #28a745;">→</div>';

        // After score
        const afterColor = getScoreColor(improvement.fixedScore);
        html += '<div class="after-score" style="text-align: center;">';
        html += '<div style="font-size: 14px; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;">After Auto-Fixes</div>';
        html += '<div style="font-size: 48px; font-weight: bold; color: ' + afterColor + ';">' + improvement.fixedScore + '%</div>';
        html += '<div style="font-size: 13px; color: #888;">(' + withFixes.totalViolations + ' remaining)</div>';
        html += '</div>';

        html += '</div>';

        // Improvement summary
        if (improvement.issuesFixed > 0) {
            html += '<div class="raywp-improvement-summary" style="text-align: center; padding: 20px; background: #d4edda; border-radius: 8px; border: 1px solid #c3e6cb;">';
            html += '<span style="font-size: 24px;">✅</span> ';
            html += '<strong style="font-size: 18px; color: #155724;">' + improvement.issuesFixed + ' issues auto-fixed</strong>';
            html += '<span style="font-size: 18px; color: #28a745;"> (+' + improvement.scoreDiff + '% improvement)</span>';
            html += '</div>';
        }

        // Issue type breakdown - what was fixed
        if (improvement.issuesFixed > 0) {
            html += '<div class="raywp-fixed-breakdown" style="margin-top: 20px;">';
            html += '<h4 style="margin-bottom: 10px;">Issues Fixed by Auto-Fixes</h4>';
            html += buildIssueComparisonTable(baseline.violationsByType, withFixes.violationsByType);
            html += '</div>';
        }

        // Remaining issues requiring manual attention
        if (withFixes.totalViolations > 0) {
            html += '<div class="raywp-remaining-issues" style="margin-top: 20px;">';
            html += '<h4 style="margin-bottom: 10px; color: #856404;">Issues Requiring Manual Attention (' + withFixes.totalViolations + ')</h4>';
            html += buildRemainingIssuesTable(withFixes.violationsByType);
            html += '</div>';
        }

        html += '</div>';

        // Find or create results container
        let $container = $(CONFIG.selectors.scanResultsContainer);
        if ($container.length === 0) {
            $container = $('<div class="raywp-scan-results"></div>');
            $(CONFIG.selectors.runFullScanBtn).closest('div').after($container);
        }

        $container.html(html);
    }

    /**
     * Get color based on score value
     */
    function getScoreColor(score) {
        if (score >= 90) return '#28a745';
        if (score >= 70) return '#ffc107';
        return '#dc3545';
    }

    /**
     * Build comparison table showing before/after issue counts
     */
    function buildIssueComparisonTable(beforeViolations, afterViolations) {
        // Get all unique issue types
        const allTypes = new Set([
            ...Object.keys(beforeViolations || {}),
            ...Object.keys(afterViolations || {})
        ]);

        let html = '<table class="wp-list-table widefat fixed striped" style="background: #fff;">';
        html += '<thead><tr><th>Issue Type</th><th>Before</th><th>After</th><th>Fixed</th></tr></thead>';
        html += '<tbody>';

        let totalBefore = 0;
        let totalAfter = 0;
        let totalFixed = 0;

        allTypes.forEach(function(type) {
            const before = (beforeViolations && beforeViolations[type]) ? beforeViolations[type].count || 0 : 0;
            const after = (afterViolations && afterViolations[type]) ? afterViolations[type].count || 0 : 0;
            const fixed = before - after;

            totalBefore += before;
            totalAfter += after;
            totalFixed += Math.max(0, fixed);

            if (fixed > 0) {
                const displayType = formatIssueType(type);
                html += '<tr>';
                html += '<td>' + escapeHtml(displayType) + '</td>';
                html += '<td>' + before + '</td>';
                html += '<td>' + after + '</td>';
                html += '<td style="color: #28a745; font-weight: bold;">-' + fixed + '</td>';
                html += '</tr>';
            }
        });

        html += '</tbody>';
        html += '<tfoot style="background: #e9ecef;">';
        html += '<tr><th>Total</th><th>' + totalBefore + '</th><th>' + totalAfter + '</th><th style="color: #28a745; font-weight: bold;">-' + totalFixed + '</th></tr>';
        html += '</tfoot>';
        html += '</table>';

        return html;
    }

    /**
     * Build table showing remaining issues that need manual attention
     */
    function buildRemainingIssuesTable(violations) {
        if (!violations || Object.keys(violations).length === 0) {
            return '<p>No remaining issues found.</p>';
        }

        let html = '<table class="wp-list-table widefat fixed striped" style="background: #fff;">';
        html += '<thead><tr><th>Issue Type</th><th>Severity</th><th>Count</th><th>Help</th></tr></thead>';
        html += '<tbody>';

        Object.keys(violations).forEach(function(type) {
            const issue = violations[type];
            const displayType = formatIssueType(type);
            const severityBadge = getSeverityBadge(issue.severity || 'moderate');

            html += '<tr>';
            html += '<td>' + escapeHtml(displayType) + '</td>';
            html += '<td>' + severityBadge + '</td>';
            html += '<td>' + (issue.count || 1) + '</td>';
            html += '<td>';
            if (issue.helpUrl) {
                html += '<a href="' + escapeHtml(issue.helpUrl) + '" target="_blank" rel="noopener">Learn more</a>';
            } else {
                html += '-';
            }
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        return html;
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
     * Show progress indicator with two-phase support
     */
    function showProgress(message, percent, phase, current) {
        let $container = $(CONFIG.selectors.progressContainer);

        if ($container.length === 0) {
            $container = $('<div id="scan-progress-container" style="margin: 15px 0; padding: 15px; background: #e7f3ff; border-radius: 8px; border: 1px solid #b8daff;"></div>');
            // Try to insert after the run full scan button first, fall back to check fixed score button
            const $runFullScan = $(CONFIG.selectors.runFullScanBtn);
            if ($runFullScan.length > 0) {
                $runFullScan.after($container);
            } else {
                $(CONFIG.selectors.checkFixedScoreBtn).after($container);
            }
        }

        // Build phase indicator if we have phase info
        let phaseIndicatorHtml = '';
        if (phase && phase >= 1 && phase <= 2) {
            const phase1Color = phase >= 1 ? '#007cba' : '#dee2e6';
            const phase2Color = phase >= 2 ? '#28a745' : '#dee2e6';
            phaseIndicatorHtml = '<div class="phase-indicators" style="display: flex; gap: 10px; margin-bottom: 10px;">';
            phaseIndicatorHtml += '<div style="flex: 1; padding: 8px; background: ' + (phase === 1 ? '#e7f3ff' : '#f8f9fa') + '; border-radius: 4px; border: 2px solid ' + phase1Color + '; text-align: center;">';
            phaseIndicatorHtml += '<small style="color: ' + phase1Color + '; font-weight: ' + (phase === 1 ? 'bold' : 'normal') + ';">Phase 1: Baseline</small>';
            phaseIndicatorHtml += '</div>';
            phaseIndicatorHtml += '<div style="flex: 1; padding: 8px; background: ' + (phase === 2 ? '#d4edda' : '#f8f9fa') + '; border-radius: 4px; border: 2px solid ' + phase2Color + '; text-align: center;">';
            phaseIndicatorHtml += '<small style="color: ' + phase2Color + '; font-weight: ' + (phase === 2 ? 'bold' : 'normal') + ';">Phase 2: With Fixes</small>';
            phaseIndicatorHtml += '</div>';
            phaseIndicatorHtml += '</div>';
        }

        // Progress bar color based on phase
        const barColor = phase === 2 ? '#28a745' : '#007cba';

        let progressHtml = '<div class="scan-progress">';
        progressHtml += phaseIndicatorHtml;
        progressHtml += '<p style="margin: 0 0 10px 0;"><strong>' + escapeHtml(message) + '</strong></p>';
        progressHtml += '<div class="progress-bar-container" style="background: #dee2e6; border-radius: 4px; height: 20px; overflow: hidden;">';
        progressHtml += '<div class="progress-bar" style="background: ' + barColor + '; height: 100%; width: ' + percent + '%; transition: width 0.3s;"></div>';
        progressHtml += '</div>';
        progressHtml += '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">Two-pass axe-core scan: baseline (no fixes) → with fixes applied</p>';
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

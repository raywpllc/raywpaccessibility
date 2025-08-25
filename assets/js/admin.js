/**
 * RayWP Accessibility Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // ARIA Rule Management
    $('#raywp-add-aria-rule').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const data = {
            action: 'raywp_accessibility_add_aria_rule',
            nonce: raywpAccessibility.nonce,
            selector: $('#aria-selector').val(),
            attribute: $('#aria-attribute').val(),
            value: $('#aria-value').val()
        };
        
        form.addClass('raywp-loading');
        
        $.post(raywpAccessibility.ajaxurl, data, function(response) {
            form.removeClass('raywp-loading');
            
            if (response.success) {
                alert(raywpAccessibility.strings.success);
                location.reload();
            } else {
                alert(response.data || raywpAccessibility.strings.error);
            }
        });
    });
    
    // Test Selector
    $('#test-selector').on('click', function() {
        const selector = $('#aria-selector').val();
        const button = $(this);
        const results = $('#selector-test-results');
        
        if (!selector) {
            alert('Please enter a selector first');
            return;
        }
        
        button.prop('disabled', true).text(raywpAccessibility.strings.testing_selector);
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_validate_selector',
            nonce: raywpAccessibility.nonce,
            selector: selector
        }, function(response) {
            button.prop('disabled', false).text('Test Selector');
            
            if (response.success && response.data.valid) {
                results.removeClass('error').addClass('success')
                    .html('<strong>Valid selector!</strong>')
                    .show();
            } else {
                results.removeClass('success').addClass('error')
                    .html('<strong>Invalid selector.</strong> Please check your CSS selector syntax.')
                    .show();
            }
        });
    });
    
    // Delete ARIA Rule
    $('.delete-rule').on('click', function() {
        if (!confirm(raywpAccessibility.strings.confirm_delete)) {
            return;
        }
        
        const button = $(this);
        const index = button.data('index');
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_delete_aria_rule',
            nonce: raywpAccessibility.nonce,
            index: index
        }, function(response) {
            if (response.success) {
                button.closest('tr').fadeOut(function() {
                    $(this).remove();
                });
            }
        });
    });
    
    // Form Scanner
    $('#scan-forms').on('click', function() {
        const button = $(this);
        const results = $('#scan-results');
        const resultsContent = $('#scan-results-content');
        
        button.prop('disabled', true)
            .html(raywpAccessibility.strings.scanning_forms + ' <span class="raywp-spinner"></span>');
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_scan_forms',
            nonce: raywpAccessibility.nonce
        }, function(response) {
            button.prop('disabled', false).text('Scan All Forms');
            
            if (response.success) {
                const data = response.data;
                let html = '';
                
                html += '<p><strong>Total Forms Found:</strong> ' + data.total_forms + '</p>';
                html += '<p><strong>Total Issues:</strong> ' + data.issues_found + '</p>';
                
                if (data.total_forms > 0) {
                    html += '<div class="form-results">';
                    
                    for (const plugin in data.forms) {
                        const forms = data.forms[plugin];
                        
                        if (forms.length > 0) {
                            html += '<h3>' + plugin.charAt(0).toUpperCase() + plugin.slice(1) + ' Forms</h3>';
                            
                            forms.forEach(function(form) {
                                html += '<div class="form-scan-result">';
                                html += '<h4>' + form.title + ' (ID: ' + form.id + ')</h4>';
                                
                                if (form.issues.length > 0) {
                                    html += '<ul class="issue-list">';
                                    
                                    form.issues.forEach(function(issue) {
                                        html += '<li class="issue-item severity-' + issue.severity + '">';
                                        html += '<strong>' + issue.type + ':</strong> ' + issue.message;
                                        html += '</li>';
                                    });
                                    
                                    html += '</ul>';
                                    html += '<button class="button fix-form-issues" data-plugin="' + plugin + '" data-form-id="' + form.id + '">Apply Fixes</button>';
                                } else {
                                    html += '<p>No issues found!</p>';
                                }
                                
                                html += '</div>';
                            });
                        }
                    }
                    
                    html += '</div>';
                }
                
                resultsContent.html(html);
                results.show();
            } else {
                alert(raywpAccessibility.strings.error);
            }
        });
    });
    
    // Apply Form Fixes
    $(document).on('click', '.fix-form-issues', function() {
        const button = $(this);
        const plugin = button.data('plugin');
        const formId = button.data('form-id');
        
        if (!confirm('Are you sure you want to apply fixes to this form?')) {
            return;
        }
        
        button.prop('disabled', true).text(raywpAccessibility.strings.applying_fixes);
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_fix_form',
            nonce: raywpAccessibility.nonce,
            plugin: plugin,
            form_id: formId
        }, function(response) {
            if (response.success) {
                button.text('Fixes Applied!').addClass('button-primary');
            } else {
                button.prop('disabled', false).text('Apply Fixes');
                alert('Failed to apply fixes');
            }
        });
    });
    
    // ARIA Attribute Change Handler
    $('#aria-attribute').on('change', function() {
        const attribute = $(this).val();
        const valueField = $('#aria-value');
        const valueRow = valueField.closest('tr');
        
        if (attribute === 'aria-hidden') {
            valueField.val('true');
            valueRow.find('.description').text('Use "true" or "false"');
        } else if (attribute === 'aria-live') {
            valueRow.find('.description').text('Use "polite", "assertive", or "off"');
        } else if (attribute === 'role') {
            valueRow.find('.description').text('Enter a valid ARIA role (e.g., navigation, main, banner)');
        } else {
            valueRow.find('.description').text('Enter the value for the attribute');
        }
    });
    
    // Enable All Auto-Fixes
    $(document).on('click', '#enable-all-fixes', function() {
        const button = $(this);
        const originalText = button.text();
        
        if (!confirm('This will enable all automatic accessibility fixes. Continue?')) {
            return;
        }
        
        button.prop('disabled', true).text('Enabling...');
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_enable_all_fixes',
            nonce: raywpAccessibility.nonce
        }, function(response) {
            button.prop('disabled', false).text(originalText);
            
            if (response.success) {
                alert('All accessibility fixes have been enabled! Run a new scan to see the improvements.');
                location.reload();
            } else {
                console.error('Enable fixes error:', response);
                let errorMsg = 'Failed to enable fixes: ';
                if (typeof response.data === 'object' && response.data.message) {
                    errorMsg += response.data.message;
                    console.log('Debug info:', response.data);
                } else {
                    errorMsg += response.data || 'Unknown error';
                }
                alert(errorMsg);
            }
        }).fail(function() {
            button.prop('disabled', false).text(originalText);
            alert('Request failed. Please try again.');
        });
    });
    
    // Run Full Scan
    $(document).on('click', '#run-full-scan', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true)
            .html('Scanning... <span class="raywp-spinner"></span>');
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_run_full_scan',
            nonce: raywpAccessibility.nonce
        }, function(response) {
            button.prop('disabled', false).text(originalText);
            
            if (response.success) {
                const data = response.data;
                
                // Update the reports page with results
                updateScanResults(data);
                
                let message = `Scan Complete!\nPages Scanned: ${data.pages_scanned}\nTotal Issues: ${data.total_issues}\nAccessibility Score: ${data.accessibility_score}%`;
                
                if (data.errors && data.errors.length > 0) {
                    message += '\n\nErrors:\n' + data.errors.join('\n');
                }
                
                // Debug info
                console.log('Scan results:', data);
                
                alert(message);
            } else {
                alert('Scan failed: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).text(originalText);
            alert('Scan request failed. Please try again.');
        });
    });
    
    function updateScanResults(data) {
        // Update accessibility score
        $('.raywp-accessibility-score').text(data.accessibility_score + '%');
        
        // Update compliance status based on score
        updateComplianceStatus(data.accessibility_score);
        
        // Update issue summary
        const issuesByType = {};
        const issuesBySeverity = {};
        
        data.results.forEach(function(page) {
            page.issues.forEach(function(issue) {
                issuesByType[issue.type] = (issuesByType[issue.type] || 0) + 1;
                issuesBySeverity[issue.severity] = (issuesBySeverity[issue.severity] || 0) + 1;
            });
        });
        
        // Create detailed results HTML
        let summaryHtml = '<h3>Scan Results (' + data.timestamp + ')</h3>';
        summaryHtml += '<p><strong>Pages Scanned:</strong> ' + data.pages_scanned + '</p>';
        summaryHtml += '<p><strong>Total Issues:</strong> ' + data.total_issues + '</p>';
        
        if (data.total_issues > 0) {
            summaryHtml += '<h4>Issues by Severity:</h4><ul>';
            Object.keys(issuesBySeverity).forEach(function(severity) {
                summaryHtml += '<li><span class="severity-' + severity + '">' + severity.charAt(0).toUpperCase() + severity.slice(1) + ': ' + issuesBySeverity[severity] + '</span></li>';
            });
            summaryHtml += '</ul>';
            
            // Detailed issues by page
            summaryHtml += '<div class="detailed-scan-results">';
            data.results.forEach(function(page) {
                if (page.issue_count > 0) {
                    summaryHtml += '<div class="page-results">';
                    summaryHtml += '<h4><a href="' + page.url + '" target="_blank">' + page.title + '</a> (' + page.issue_count + ' issues)</h4>';
                    
                    page.issues.forEach(function(issue, index) {
                        summaryHtml += createIssueHTML(issue, page.url, index);
                    });
                    
                    summaryHtml += '</div>';
                }
            });
            summaryHtml += '</div>';
        }
        
        $('.raywp-scan-results').html(summaryHtml);
    }
    
    function createIssueHTML(issue, pageUrl, index) {
        const issueId = 'issue-' + btoa(pageUrl).slice(0, 10) + '-' + index;
        const severityClass = 'severity-' + issue.severity;
        const issueTypeDisplay = issue.type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        
        let html = '<div class="accessibility-issue ' + severityClass + '">';
        html += '<div class="issue-header" onclick="toggleIssueDetails(\'' + issueId + '\')">';
        html += '<span class="severity-badge severity-' + issue.severity + '">' + issue.severity.toUpperCase() + '</span>';
        html += '<span class="issue-type">' + issueTypeDisplay + '</span>';
        html += '<span class="issue-message">' + issue.message + '</span>';
        html += '<span class="toggle-arrow">▼</span>';
        html += '</div>';
        
        html += '<div class="issue-details" id="' + issueId + '" style="display: none;">';
        
        // Description
        if (issue.description) {
            html += '<div class="issue-section"><strong>Description:</strong><p>' + issue.description + '</p></div>';
        }
        
        // Element details
        if (issue.element_details) {
            html += '<div class="issue-section"><strong>Element Location:</strong>';
            html += '<p><code>' + issue.element_details.selector + '</code>';
            if (issue.element_details.text_content) {
                html += ' - "' + issue.element_details.text_content + '"';
            }
            html += '</p>';
            
            if (issue.element_details.html_snippet) {
                html += '<details><summary>View HTML</summary><pre><code>' + 
                        escapeHtml(issue.element_details.html_snippet) + '</code></pre></details>';
            }
            html += '</div>';
        }
        
        // Suggestion
        if (issue.suggestion) {
            html += '<div class="issue-section"><strong>How to Fix:</strong><p>' + issue.suggestion + '</p></div>';
        }
        
        // Code example
        if (issue.how_to_fix) {
            html += '<div class="issue-section"><strong>Code Example:</strong><pre><code>' + 
                    escapeHtml(issue.how_to_fix) + '</code></pre></div>';
        }
        
        // WCAG reference
        if (issue.wcag_reference) {
            html += '<div class="issue-section"><strong>WCAG Reference:</strong><p>' + issue.wcag_reference + '</p></div>';
        }
        
        // Quick action buttons
        html += '<div class="issue-actions">';
        html += '<a href="' + pageUrl + '" target="_blank" class="button">View Page</a>';
        if (issue.element_details && issue.element_details.selector !== 'body') {
            const inspectorUrl = pageUrl + '#' + (issue.element_details.attributes && issue.element_details.attributes.id ? issue.element_details.attributes.id : '');
            html += '<a href="' + inspectorUrl + '" target="_blank" class="button">Inspect Element</a>';
        }
        html += '</div>';
        
        html += '</div></div>';
        
        return html;
    }
    
    function updateComplianceStatus(score) {
        let complianceMessage = '';
        
        if (score >= 95) {
            complianceMessage = 'Excellent compliance';
        } else if (score >= 85) {
            complianceMessage = 'Good compliance';
        } else if (score >= 70) {
            complianceMessage = 'Needs improvement';
        } else {
            complianceMessage = 'Poor compliance';
        }
        
        // Update all compliance status elements
        $('.raywp-report-section ul li').each(function() {
            const text = $(this).text();
            if (text.includes('WCAG') || text.includes('ADA') || text.includes('EAA')) {
                $(this).html(text.split(':')[0] + ': ' + complianceMessage);
            }
        });
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Global function to toggle issue details
    window.toggleIssueDetails = function(issueId) {
        const details = document.getElementById(issueId);
        const header = details.previousElementSibling;
        const arrow = header.querySelector('.toggle-arrow');
        
        if (details.style.display === 'none') {
            details.style.display = 'block';
            arrow.textContent = '▲';
        } else {
            details.style.display = 'none';
            arrow.textContent = '▼';
        }
    }
    
    // Color Override Management
    
    // Toggle color overrides section
    $('input[name="raywp_accessibility_settings[enable_color_overrides]"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#raywp-color-overrides-section').slideDown();
        } else {
            $('#raywp-color-overrides-section').slideUp();
        }
    });
    
    // Add color override
    $('#add-color-override-btn').on('click', function() {
        const selector = $('#override-selector').val().trim();
        const color = $('#override-color').val().trim();
        const background = $('#override-background').val().trim();
        const message = $('#color-override-message');
        
        if (!selector) {
            message.text(raywpAccessibility.messages.selector_required).css('color', 'red');
            return;
        }
        
        if (!color && !background) {
            message.text('Please specify at least one color').css('color', 'red');
            return;
        }
        
        message.text('Adding...').css('color', '');
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_add_color_override',
            nonce: raywpAccessibility.nonce,
            selector: selector,
            color: color,
            background: background
        }, function(response) {
            if (response.success) {
                // Clear inputs
                $('#override-selector').val('');
                $('#override-color').val('').trigger('change');
                $('#override-background').val('').trigger('change');
                message.text('Added successfully!').css('color', 'green');
                
                // Add to list
                const override = response.data.override;
                const index = response.data.index;
                let html = '<div class="raywp-color-override-rule" data-index="' + index + '">';
                html += '<div class="rule-display">';
                html += '<strong>' + escapeHtml(override.selector) + '</strong> ';
                if (override.color) {
                    html += '<span style="color: ' + override.color + '">● ' + override.color + '</span> ';
                }
                if (override.background) {
                    html += '<span style="background: ' + override.background + '; padding: 2px 8px; color: #fff;">BG: ' + override.background + '</span> ';
                }
                html += '<button type="button" class="button-link delete-color-override" data-index="' + index + '">Remove</button>';
                html += '</div></div>';
                
                $('#raywp-color-overrides-list').append(html);
                
                setTimeout(function() {
                    message.text('');
                }, 3000);
            } else {
                message.text('Error: ' + response.data).css('color', 'red');
            }
        });
    });
    
    // Delete color override
    $(document).on('click', '.delete-color-override', function() {
        const button = $(this);
        const index = button.data('index');
        const rule = button.closest('.raywp-color-override-rule');
        
        if (!confirm('Remove this color override?')) {
            return;
        }
        
        $.post(raywpAccessibility.ajaxurl, {
            action: 'raywp_accessibility_delete_color_override',
            nonce: raywpAccessibility.nonce,
            index: index
        }, function(response) {
            if (response.success) {
                rule.fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert('Error removing override: ' + response.data);
            }
        });
    });
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Color Picker
    if ($.fn.wpColorPicker) {
        $('.color-picker').wpColorPicker();
    }
});
/**
 * RayWP Accessibility Checker JavaScript
 */

(function($) {
    'use strict';
    
    let checkerInitialized = false;
    let currentIssues = [];
    
    // Initialize checker when DOM is ready
    $(document).ready(function() {
        // Wait a bit for the DOM to be fully processed
        setTimeout(function() {
            if (typeof raywpAccessibilityFrontend !== 'undefined' && raywpAccessibilityFrontend.settings.enable_checker) {
                initAccessibilityChecker();
            }
        }, 500);
    });
    
    /**
     * Initialize the accessibility checker
     */
    function initAccessibilityChecker() {
        if (checkerInitialized) {
            return;
        }
        
        createCheckerInterface();
        runInitialScan();
        
        checkerInitialized = true;
    }
    
    /**
     * Create the checker interface
     */
    function createCheckerInterface() {
        const checkerHTML = `
            <button class="raywp-checker-toggle" title="Toggle Accessibility Checker">
                🛠️
            </button>
            
            <div class="raywp-checker-header">
                <h3 class="raywp-checker-title">Accessibility Checker</h3>
                <button class="raywp-checker-close" title="Close">&times;</button>
            </div>
            
            <div class="raywp-checker-content">
                <div class="raywp-checker-stats">
                    <div class="raywp-score" id="raywp-score">-</div>
                    <div class="raywp-score-label">Accessibility Score</div>
                </div>
                
                <div class="raywp-issues-section">
                    <h4>Issues Found</h4>
                    <ul class="raywp-issues-list" id="raywp-issues-list">
                        <li>Scanning...</li>
                    </ul>
                </div>
            </div>
            
            <div class="raywp-checker-footer">
                <button class="raywp-btn" id="raywp-rescan">Rescan</button>
                <button class="raywp-btn primary" id="raywp-fix-auto">Auto Fix</button>
            </div>
        `;
        
        // Populate the existing container instead of creating a new one
        $('#raywp-checker-container').html(checkerHTML);
        
        // Bind events
        $('.raywp-checker-toggle').on('click', toggleChecker);
        $('.raywp-checker-close').on('click', closeChecker);
        $('#raywp-rescan').on('click', runScan);
        $('#raywp-fix-auto').on('click', applyAutoFixes);
        
        // Issue click handler
        $(document).on('click', '.raywp-issue-item', function() {
            highlightIssueElement($(this));
        });
    }
    
    /**
     * Toggle checker visibility
     */
    function toggleChecker() {
        $('#raywp-checker-container').toggleClass('open');
    }
    
    /**
     * Close checker
     */
    function closeChecker() {
        $('#raywp-checker-container').removeClass('open');
    }
    
    /**
     * Run initial scan
     */
    function runInitialScan() {
        setTimeout(runScan, 1000); // Delay to allow page to fully load
    }
    
    /**
     * Run accessibility scan
     */
    function runScan() {
        const issues = [];
        
        // Clear previous highlights
        $('.raywp-issue-highlight').removeClass('raywp-issue-highlight').removeAttr('data-raywp-issue');
        
        // Scan for various accessibility issues
        scanMissingAltText(issues);
        scanMissingLabels(issues);
        scanEmptyHeadings(issues);
        scanColorContrast(issues);
        scanKeyboardAccessibility(issues);
        scanAriaAttributes(issues);
        
        currentIssues = issues;
        updateCheckerInterface(issues);
    }
    
    /**
     * Scan for missing alt text
     */
    function scanMissingAltText(issues) {
        $('img:not([alt])').each(function() {
            const $img = $(this);
            issues.push({
                type: 'missing_alt',
                severity: 'high',
                element: $img[0],
                selector: getElementSelector($img[0]),
                message: 'Image missing alt attribute',
                fixable: true
            });
        });
        
        // Check for empty alt text on non-decorative images
        $('img[alt=""]').each(function() {
            const $img = $(this);
            if (!isDecorativeImage($img)) {
                issues.push({
                    type: 'empty_alt',
                    severity: 'medium',
                    element: $img[0],
                    selector: getElementSelector($img[0]),
                    message: 'Image has empty alt text but may not be decorative',
                    fixable: false
                });
            }
        });
    }
    
    /**
     * Scan for missing form labels
     */
    function scanMissingLabels(issues) {
        $('input[type="text"], input[type="email"], input[type="tel"], input[type="password"], textarea, select').each(function() {
            const $input = $(this);
            const id = $input.attr('id');
            let hasLabel = false;
            
            // Check for explicit label
            if (id && $('label[for="' + id + '"]').length > 0) {
                hasLabel = true;
            }
            
            // Check for ARIA labeling
            if ($input.attr('aria-label') || $input.attr('aria-labelledby')) {
                hasLabel = true;
            }
            
            // Check for parent label
            if ($input.closest('label').length > 0) {
                hasLabel = true;
            }
            
            if (!hasLabel) {
                issues.push({
                    type: 'missing_label',
                    severity: 'high',
                    element: $input[0],
                    selector: getElementSelector($input[0]),
                    message: 'Form control missing accessible label',
                    fixable: true
                });
            }
        });
    }
    
    /**
     * Scan for empty headings
     */
    function scanEmptyHeadings(issues) {
        $('h1, h2, h3, h4, h5, h6').each(function() {
            const $heading = $(this);
            const text = $heading.text().trim();
            
            if (!text) {
                issues.push({
                    type: 'empty_heading',
                    severity: 'medium',
                    element: $heading[0],
                    selector: getElementSelector($heading[0]),
                    message: 'Heading element is empty',
                    fixable: false
                });
            }
        });
    }
    
    /**
     * Scan color contrast (basic check)
     */
    function scanColorContrast(issues) {
        // This is a simplified check - real contrast analysis requires more complex calculations
        $('*').each(function() {
            const $el = $(this);
            const style = window.getComputedStyle(this);
            const color = style.color;
            const backgroundColor = style.backgroundColor;
            
            if (isPotentiallyLowContrast(color, backgroundColor)) {
                issues.push({
                    type: 'low_contrast',
                    severity: 'medium',
                    element: this,
                    selector: getElementSelector(this),
                    message: 'Potential low color contrast',
                    fixable: false
                });
            }
        });
    }
    
    /**
     * Scan keyboard accessibility
     */
    function scanKeyboardAccessibility(issues) {
        // Check for interactive elements without proper focus management
        $('div[onclick], span[onclick]').each(function() {
            const $el = $(this);
            
            if (!$el.attr('tabindex') && !$el.attr('role')) {
                issues.push({
                    type: 'keyboard_inaccessible',
                    severity: 'high',
                    element: this,
                    selector: getElementSelector(this),
                    message: 'Interactive element not keyboard accessible',
                    fixable: true
                });
            }
        });
    }
    
    /**
     * Scan ARIA attributes
     */
    function scanAriaAttributes(issues) {
        // Check for invalid ARIA attributes
        $('[class*="aria-"], [id*="aria-"]').each(function() {
            const attributes = this.attributes;
            
            for (let i = 0; i < attributes.length; i++) {
                const attr = attributes[i];
                
                if (attr.name.startsWith('aria-') && !isValidAriaAttribute(attr.name)) {
                    issues.push({
                        type: 'invalid_aria',
                        severity: 'medium',
                        element: this,
                        selector: getElementSelector(this),
                        message: `Invalid ARIA attribute: ${attr.name}`,
                        fixable: false
                    });
                }
            }
        });
    }
    
    /**
     * Update checker interface with scan results
     */
    function updateCheckerInterface(issues) {
        const $scoreEl = $('#raywp-score');
        const $issuesList = $('#raywp-issues-list');
        
        // Calculate score
        const score = calculateAccessibilityScore(issues);
        $scoreEl.text(score + '%');
        
        // Update score color
        $scoreEl.removeClass('warning error');
        if (score < 70) {
            $scoreEl.addClass('error');
        } else if (score < 90) {
            $scoreEl.addClass('warning');
        }
        
        // Update issues list
        $issuesList.empty();
        
        if (issues.length === 0) {
            $issuesList.append('<li>No issues found!</li>');
        } else {
            issues.forEach(function(issue, index) {
                const issueHTML = `
                    <li class="raywp-issue-item severity-${issue.severity}" data-issue-index="${index}">
                        <div class="raywp-issue-type">${formatIssueType(issue.type)}</div>
                        <div class="raywp-issue-message">${issue.message}</div>
                        <div class="raywp-issue-element">${issue.selector}</div>
                    </li>
                `;
                $issuesList.append(issueHTML);
            });
        }
        
        // Update auto-fix button
        const fixableIssues = issues.filter(issue => issue.fixable);
        $('#raywp-fix-auto').prop('disabled', fixableIssues.length === 0);
    }
    
    /**
     * Highlight issue element
     */
    function highlightIssueElement($issueItem) {
        const issueIndex = $issueItem.data('issue-index');
        const issue = currentIssues[issueIndex];
        
        if (issue && issue.element) {
            // Clear previous highlights
            $('.raywp-issue-highlight').removeClass('raywp-issue-highlight').removeAttr('data-raywp-issue');
            
            // Highlight current issue
            $(issue.element)
                .addClass('raywp-issue-highlight')
                .attr('data-raywp-issue', issue.message);
            
            // Scroll to element
            issue.element.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            
            // Remove highlight after 5 seconds
            setTimeout(function() {
                $(issue.element).removeClass('raywp-issue-highlight').removeAttr('data-raywp-issue');
            }, 5000);
        }
    }
    
    /**
     * Apply automatic fixes
     */
    function applyAutoFixes() {
        let fixedCount = 0;
        
        currentIssues.forEach(function(issue) {
            if (issue.fixable) {
                switch (issue.type) {
                    case 'missing_alt':
                        $(issue.element).attr('alt', '');
                        fixedCount++;
                        break;
                        
                    case 'missing_label':
                        const $input = $(issue.element);
                        const placeholder = $input.attr('placeholder');
                        const name = $input.attr('name');
                        
                        if (placeholder) {
                            $input.attr('aria-label', placeholder);
                            fixedCount++;
                        } else if (name) {
                            const label = name.replace(/[-_]/g, ' ').replace(/\b\w/g, function(l) {
                                return l.toUpperCase();
                            });
                            $input.attr('aria-label', label);
                            fixedCount++;
                        }
                        break;
                        
                    case 'keyboard_inaccessible':
                        $(issue.element).attr('tabindex', '0').attr('role', 'button');
                        fixedCount++;
                        break;
                }
            }
        });
        
        if (fixedCount > 0) {
            // Announce to screen readers if function exists
            if (typeof raywpAccessibilityAnnounce === 'function') {
                raywpAccessibilityAnnounce(`Fixed ${fixedCount} accessibility issues`);
            }
            setTimeout(runScan, 500); // Re-scan after fixes
        }
    }
    
    // Utility functions
    
    function getElementSelector(element) {
        if (element.id) {
            return '#' + element.id;
        }
        
        let selector = element.tagName.toLowerCase();
        
        if (element.className) {
            const classes = element.className.split(' ').filter(c => c.trim());
            if (classes.length > 0) {
                selector += '.' + classes.join('.');
            }
        }
        
        return selector;
    }
    
    function isDecorativeImage($img) {
        // Basic heuristic to determine if image might be decorative
        const parent = $img.parent();
        return parent.hasClass('decoration') || 
               parent.hasClass('background') || 
               $img.hasClass('decoration') ||
               $img.hasClass('icon');
    }
    
    function isPotentiallyLowContrast(color, backgroundColor) {
        // Very simplified contrast check
        // Real implementation would calculate actual contrast ratios
        return color && backgroundColor && 
               color !== 'rgba(0, 0, 0, 0)' && 
               backgroundColor !== 'rgba(0, 0, 0, 0)';
    }
    
    function isValidAriaAttribute(attrName) {
        const validAttrs = [
            'aria-activedescendant', 'aria-atomic', 'aria-autocomplete', 'aria-busy',
            'aria-checked', 'aria-colcount', 'aria-colindex', 'aria-colspan',
            'aria-controls', 'aria-current', 'aria-describedby', 'aria-details',
            'aria-disabled', 'aria-dropeffect', 'aria-errormessage', 'aria-expanded',
            'aria-flowto', 'aria-grabbed', 'aria-haspopup', 'aria-hidden',
            'aria-invalid', 'aria-keyshortcuts', 'aria-label', 'aria-labelledby',
            'aria-level', 'aria-live', 'aria-modal', 'aria-multiline',
            'aria-multiselectable', 'aria-orientation', 'aria-owns', 'aria-placeholder',
            'aria-posinset', 'aria-pressed', 'aria-readonly', 'aria-relevant',
            'aria-required', 'aria-roledescription', 'aria-rowcount', 'aria-rowindex',
            'aria-rowspan', 'aria-selected', 'aria-setsize', 'aria-sort',
            'aria-valuemax', 'aria-valuemin', 'aria-valuenow', 'aria-valuetext'
        ];
        
        return validAttrs.includes(attrName.toLowerCase());
    }
    
    function calculateAccessibilityScore(issues) {
        if (issues.length === 0) {
            return 100;
        }
        
        const severityWeights = {
            'critical': 15,
            'high': 10,
            'medium': 5,
            'low': 2
        };
        
        let totalWeight = 0;
        issues.forEach(function(issue) {
            totalWeight += severityWeights[issue.severity] || 1;
        });
        
        // Calculate score (100 - penalties, minimum 0)
        return Math.max(0, 100 - totalWeight);
    }
    
    function formatIssueType(type) {
        return type.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
            return l.toUpperCase();
        });
    }
    
})(jQuery);
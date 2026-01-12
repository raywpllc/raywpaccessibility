/**
 * RayWP Accessibility - Iframe Scanner
 *
 * Scans pages using axe-core in an iframe for accurate browser-based
 * accessibility testing. This sees the actual rendered DOM including
 * all JavaScript modifications and plugin fixes.
 */

(function($) {
    'use strict';

    /**
     * IframeScanner class - handles scanning pages via hidden iframes
     */
    class IframeScanner {
        constructor(options = {}) {
            this.options = {
                timeout: 30000, // 30 second timeout per page
                axeConfig: {
                    runOnly: {
                        type: 'tag',
                        values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice']
                    },
                    resultTypes: ['violations', 'incomplete']
                },
                ...options
            };

            this.iframeContainer = null;
            this.currentIframe = null;
            this.isScanning = false;
        }

        /**
         * Initialize the scanner - create container for iframes
         */
        init() {
            // Create a hidden container for iframes
            this.iframeContainer = document.createElement('div');
            this.iframeContainer.id = 'raywp-iframe-scanner-container';
            this.iframeContainer.style.cssText = 'position: fixed; left: -9999px; top: 0; width: 1024px; height: 768px; overflow: hidden;';
            document.body.appendChild(this.iframeContainer);
        }

        /**
         * Scan a single page
         * @param {string} url - The URL to scan
         * @param {function} progressCallback - Optional callback for progress updates
         * @returns {Promise<Object>} - Scan results
         */
        async scanPage(url, progressCallback = null) {
            if (this.isScanning) {
                throw new Error('Scanner is already running');
            }

            this.isScanning = true;

            try {
                // Create iframe
                const iframe = document.createElement('iframe');
                iframe.id = 'raywp-scan-iframe-' + Date.now();
                iframe.style.cssText = 'width: 1024px; height: 768px; border: none;';

                // Add sandbox attribute to allow same-origin scripts
                iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts');

                this.currentIframe = iframe;
                this.iframeContainer.appendChild(iframe);

                if (progressCallback) {
                    progressCallback('Loading page...');
                }

                // Load the page
                await this.loadPage(iframe, url);

                if (progressCallback) {
                    progressCallback('Running accessibility scan...');
                }

                // Run axe-core in the iframe
                const results = await this.runAxeInIframe(iframe);

                // Clean up
                this.iframeContainer.removeChild(iframe);
                this.currentIframe = null;

                return {
                    url: url,
                    success: true,
                    violations: results.violations || [],
                    incomplete: results.incomplete || [],
                    passes: results.passes || [],
                    timestamp: new Date().toISOString()
                };

            } catch (error) {
                // Clean up on error
                if (this.currentIframe && this.currentIframe.parentNode) {
                    this.currentIframe.parentNode.removeChild(this.currentIframe);
                }
                this.currentIframe = null;

                return {
                    url: url,
                    success: false,
                    error: error.message,
                    violations: [],
                    incomplete: [],
                    passes: [],
                    timestamp: new Date().toISOString()
                };
            } finally {
                this.isScanning = false;
            }
        }

        /**
         * Load a page into an iframe
         * @param {HTMLIFrameElement} iframe - The iframe element
         * @param {string} url - The URL to load
         * @returns {Promise<void>}
         */
        loadPage(iframe, url) {
            return new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('Page load timeout'));
                }, this.options.timeout);

                iframe.onload = () => {
                    clearTimeout(timeout);
                    // Give the page a moment to finish rendering
                    setTimeout(resolve, 1000);
                };

                iframe.onerror = () => {
                    clearTimeout(timeout);
                    reject(new Error('Failed to load page'));
                };

                // Add cache buster and scan parameter
                const scanUrl = new URL(url);
                scanUrl.searchParams.set('raywp_iframe_scan', '1');
                scanUrl.searchParams.set('_', Date.now());

                iframe.src = scanUrl.toString();
            });
        }

        /**
         * Run axe-core analysis inside the iframe
         * @param {HTMLIFrameElement} iframe - The iframe to analyze
         * @returns {Promise<Object>} - axe-core results
         */
        runAxeInIframe(iframe) {
            return new Promise((resolve, reject) => {
                try {
                    const iframeWindow = iframe.contentWindow;
                    const iframeDoc = iframe.contentDocument || iframeWindow.document;

                    // Check if we can access the iframe (same-origin check)
                    if (!iframeDoc || !iframeDoc.body) {
                        reject(new Error('Cannot access iframe content - possible cross-origin restriction'));
                        return;
                    }

                    // Inject axe-core if not already present
                    if (!iframeWindow.axe) {
                        const script = iframeDoc.createElement('script');
                        script.src = raywpAccessibility.pluginUrl + 'assets/js/axe.min.js';

                        script.onload = () => {
                            this.executeAxeScan(iframeWindow, resolve, reject);
                        };

                        script.onerror = () => {
                            reject(new Error('Failed to load axe-core in iframe'));
                        };

                        iframeDoc.head.appendChild(script);
                    } else {
                        this.executeAxeScan(iframeWindow, resolve, reject);
                    }

                } catch (error) {
                    reject(new Error('Failed to access iframe: ' + error.message));
                }
            });
        }

        /**
         * Execute axe.run() in the iframe context
         * @param {Window} iframeWindow - The iframe's window object
         * @param {function} resolve - Promise resolve function
         * @param {function} reject - Promise reject function
         */
        executeAxeScan(iframeWindow, resolve, reject) {
            try {
                // Configure axe options
                const axeOptions = {
                    runOnly: this.options.axeConfig.runOnly,
                    resultTypes: this.options.axeConfig.resultTypes,
                    // Exclude admin bar and other WordPress admin elements
                    exclude: [
                        ['#wpadminbar'],
                        ['.wp-admin-bar'],
                        ['#adminmenuwrap'],
                        ['#adminmenu'],
                        ['#wpfooter']
                    ]
                };

                // Run axe
                iframeWindow.axe.run(iframeWindow.document, axeOptions)
                    .then(results => {
                        resolve(results);
                    })
                    .catch(error => {
                        reject(new Error('axe.run() failed: ' + error.message));
                    });

            } catch (error) {
                reject(new Error('Failed to execute axe scan: ' + error.message));
            }
        }

        /**
         * Scan multiple pages sequentially
         * @param {Array<Object>} pages - Array of {url, title} objects
         * @param {function} progressCallback - Progress callback(current, total, pageTitle, status)
         * @returns {Promise<Object>} - Combined results
         */
        async scanMultiplePages(pages, progressCallback = null) {
            const results = {
                pages: [],
                totalViolations: 0,
                totalIncomplete: 0,
                violationsByType: {},
                startTime: Date.now(),
                endTime: null
            };

            for (let i = 0; i < pages.length; i++) {
                const page = pages[i];

                if (progressCallback) {
                    progressCallback(i + 1, pages.length, page.title || page.url, 'scanning');
                }

                const pageResult = await this.scanPage(page.url, (status) => {
                    if (progressCallback) {
                        progressCallback(i + 1, pages.length, page.title || page.url, status);
                    }
                });

                pageResult.title = page.title || 'Unknown';
                results.pages.push(pageResult);

                if (pageResult.success) {
                    results.totalViolations += pageResult.violations.length;
                    results.totalIncomplete += pageResult.incomplete.length;

                    // Group violations by type
                    pageResult.violations.forEach(violation => {
                        const id = violation.id;
                        if (!results.violationsByType[id]) {
                            results.violationsByType[id] = {
                                id: id,
                                description: violation.description,
                                help: violation.help,
                                helpUrl: violation.helpUrl,
                                impact: violation.impact,
                                tags: violation.tags,
                                count: 0,
                                nodes: []
                            };
                        }
                        results.violationsByType[id].count += violation.nodes.length;
                        // Store first few nodes as examples
                        if (results.violationsByType[id].nodes.length < 3) {
                            results.violationsByType[id].nodes.push({
                                page: page.title || page.url,
                                pageUrl: page.url,
                                target: violation.nodes[0]?.target || []
                            });
                        }
                    });
                }

                // Small delay between pages to prevent overwhelming
                if (i < pages.length - 1) {
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            }

            results.endTime = Date.now();
            results.duration = results.endTime - results.startTime;

            return results;
        }

        /**
         * Calculate accessibility score from axe-core results
         * @param {Object} results - Results from scanMultiplePages
         * @returns {number} - Score from 0-100
         */
        calculateScore(results) {
            // Impact weights matching WCAG severity
            const impactWeights = {
                'critical': 10,
                'serious': 5,
                'moderate': 3,
                'minor': 1
            };

            let totalWeight = 0;

            // Calculate weight from violations
            Object.values(results.violationsByType).forEach(violation => {
                const weight = impactWeights[violation.impact] || 1;
                totalWeight += weight * violation.count;
            });

            // Score is 100 minus penalties, minimum 0
            return Math.max(0, 100 - totalWeight);
        }

        /**
         * Map axe-core violation IDs to our internal issue types
         * @param {string} axeId - axe-core violation ID
         * @returns {string} - Internal issue type
         */
        mapAxeIdToIssueType(axeId) {
            const mapping = {
                'html-has-lang': 'missing_page_language',
                'html-lang-valid': 'invalid_page_language',
                'frame-title': 'iframe_missing_title',
                'image-alt': 'missing_alt',
                'button-name': 'button_missing_accessible_name',
                'link-name': 'link_no_accessible_name',
                'label': 'missing_label',
                'color-contrast': 'low_contrast',
                'heading-order': 'heading_hierarchy_skip',
                'duplicate-id': 'duplicate_ids',
                'landmark-one-main': 'missing_main_landmark',
                'region': 'missing_main_landmark',
                'bypass': 'missing_skip_links',
                'document-title': 'missing_page_title',
                'form-field-multiple-labels': 'multiple_labels',
                'input-image-alt': 'missing_alt',
                'video-caption': 'video_missing_captions',
                'audio-caption': 'audio_missing_transcript',
                'tabindex': 'tabindex_issue',
                'aria-allowed-attr': 'invalid_aria',
                'aria-required-attr': 'missing_aria',
                'aria-valid-attr': 'invalid_aria',
                'aria-valid-attr-value': 'invalid_aria_value',
                'empty-heading': 'empty_heading',
                'link-in-text-block': 'link_distinguishable',
                'meta-viewport': 'viewport_scaling_disabled',
                'accesskeys': 'accesskey_issue',
                'focus-visible': 'focus_not_visible',
                'scrollable-region-focusable': 'scrollable_not_focusable'
            };

            return mapping[axeId] || axeId;
        }

        /**
         * Convert axe-core results to our internal format
         * @param {Object} results - Results from scanMultiplePages
         * @returns {Object} - Converted results
         */
        convertToInternalFormat(results) {
            const converted = {
                pages_scanned: results.pages.length,
                total_issues: results.totalViolations,
                score: this.calculateScore(results),
                issues: [],
                issue_breakdown: {
                    fixed: [],
                    remaining: [],
                    unfixable: []
                }
            };

            // Convert each violation type
            Object.values(results.violationsByType).forEach(violation => {
                const issueType = this.mapAxeIdToIssueType(violation.id);

                // Map axe impact to our severity
                const severityMap = {
                    'critical': 'critical',
                    'serious': 'high',
                    'moderate': 'medium',
                    'minor': 'low'
                };
                const severity = severityMap[violation.impact] || 'medium';

                // Add to issues array
                for (let i = 0; i < violation.count; i++) {
                    const issue = {
                        type: issueType,
                        axe_id: violation.id,
                        severity: severity,
                        message: violation.help,
                        description: violation.description,
                        help_url: violation.helpUrl,
                        wcag_tags: violation.tags.filter(tag => tag.startsWith('wcag')),
                        page_url: violation.nodes[0]?.pageUrl || '',
                        page_title: violation.nodes[0]?.page || ''
                    };

                    converted.issues.push(issue);

                    // All issues go to remaining (manual attention) by default
                    // The PHP backend will filter out auto-fixable ones
                    converted.issue_breakdown.remaining.push(issue);
                }
            });

            return converted;
        }

        /**
         * Destroy the scanner and clean up
         */
        destroy() {
            if (this.iframeContainer && this.iframeContainer.parentNode) {
                this.iframeContainer.parentNode.removeChild(this.iframeContainer);
            }
            this.iframeContainer = null;
            this.currentIframe = null;
        }
    }

    // Export to window
    window.RayWPIframeScanner = IframeScanner;

})(jQuery);

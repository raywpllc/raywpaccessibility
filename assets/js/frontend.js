/**
 * RayWP Accessibility Frontend JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        raywpAccessibilityInit();
    });
    
    /**
     * Initialize WP Accessibility Pro
     */
    function raywpAccessibilityInit() {
        if (typeof raywpAccessibilityFrontend === 'undefined') {
            return;
        }
        
        // Apply ARIA rules
        if (raywpAccessibilityFrontend.aria_rules && raywpAccessibilityFrontend.aria_rules.length > 0) {
            raywpAccessibilityApplyAriaRules();
        }
        
        // Initialize accessibility enhancements
        raywpAccessibilityEnhanceAccessibility();
        
        // Set up DOM observers for dynamic content
        raywpAccessibilityObserveDOMChanges();
    }
    
    /**
     * Apply ARIA rules to the page
     */
    function raywpAccessibilityApplyAriaRules() {
        if (!raywpAccessibilityFrontend.aria_rules) {
            return;
        }
        
        raywpAccessibilityFrontend.aria_rules.forEach(function(rule) {
            try {
                const elements = document.querySelectorAll(rule.selector);
                elements.forEach(function(element) {
                    element.setAttribute(rule.attribute, rule.value);
                });
            } catch (error) {
                console.warn('WP Accessibility Pro: Invalid selector:', rule.selector);
            }
        });
    }
    
    // Make function globally available
    window.raywpAccessibilityApplyAriaRules = raywpAccessibilityApplyAriaRules;
    
    /**
     * Enhance general accessibility
     */
    function raywpAccessibilityEnhanceAccessibility() {
        // Add missing alt attributes
        $('img:not([alt])').each(function() {
            $(this).attr('alt', '');
        });
        
        // Enhance form labels
        raywpAccessibilityEnhanceFormLabels();
        
        // Add ARIA live region for dynamic content
        if ($('#raywp-live-region').length === 0) {
            $('body').append('<div id="raywp-live-region" class="raywp-aria-live" aria-live="polite" aria-atomic="true"></div>');
        }
        
        // Enhance keyboard navigation
        raywpAccessibilityEnhanceKeyboardNav();
        
        // Fix heading structure
        raywpAccessibilityFixHeadingStructure();
    }
    
    /**
     * Enhance form labels
     */
    function raywpAccessibilityEnhanceFormLabels() {
        // Find inputs without labels
        $('input[type="text"], input[type="email"], input[type="tel"], input[type="url"], input[type="password"], textarea, select').each(function() {
            const $input = $(this);
            const id = $input.attr('id');
            
            // Skip if already has label or aria-label
            if ($input.attr('aria-label') || $input.attr('aria-labelledby')) {
                return;
            }
            
            // Check for associated label
            let hasLabel = false;
            if (id) {
                hasLabel = $('label[for="' + id + '"]').length > 0;
            }
            
            if (!hasLabel) {
                // Try to create label from placeholder or name
                const placeholder = $input.attr('placeholder');
                const name = $input.attr('name');
                
                if (placeholder) {
                    $input.attr('aria-label', placeholder);
                } else if (name) {
                    const label = name.replace(/[-_]/g, ' ').replace(/\b\w/g, function(l) {
                        return l.toUpperCase();
                    });
                    $input.attr('aria-label', label);
                }
            }
        });
        
        // Mark required fields
        $('input[required], textarea[required], select[required]').each(function() {
            const $field = $(this);
            if (!$field.attr('aria-required')) {
                $field.attr('aria-required', 'true');
            }
            
            // Add visual indicator if not present
            const $label = $('label[for="' + $field.attr('id') + '"]');
            if ($label.length && $label.find('.raywp-required-indicator').length === 0) {
                $label.append('<span class="raywp-required-indicator" aria-hidden="true">*</span>');
            }
        });
    }
    
    /**
     * Enhance keyboard navigation
     */
    function raywpAccessibilityEnhanceKeyboardNav() {
        // Skip link functionality
        $('.raywp-skip-link').on('click', function(e) {
            const target = $(this).attr('href');
            const $target = $(target);
            
            if ($target.length) {
                e.preventDefault();
                
                // Make sure target is focusable
                if (!$target.attr('tabindex')) {
                    $target.attr('tabindex', '-1');
                }
                
                // Focus and scroll to target
                $target.focus();
                $target[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
        
        // Escape key handling for modals/dropdowns
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close dropdowns
                $('.raywp-dropdown.open').removeClass('open');
                
                // Close modals
                $('.raywp-modal.open').removeClass('open');
            }
        });
        
        // Arrow key navigation for menus
        $('.menu, nav ul').on('keydown', 'a', function(e) {
            const $current = $(this);
            const $items = $current.closest('ul').find('a');
            const currentIndex = $items.index($current);
            let $next;
            
            switch (e.key) {
                case 'ArrowDown':
                case 'ArrowRight':
                    e.preventDefault();
                    $next = $items.eq(currentIndex + 1);
                    if (!$next.length) {
                        $next = $items.first();
                    }
                    $next.focus();
                    break;
                    
                case 'ArrowUp':
                case 'ArrowLeft':
                    e.preventDefault();
                    $next = $items.eq(currentIndex - 1);
                    if (!$next.length) {
                        $next = $items.last();
                    }
                    $next.focus();
                    break;
                    
                case 'Home':
                    e.preventDefault();
                    $items.first().focus();
                    break;
                    
                case 'End':
                    e.preventDefault();
                    $items.last().focus();
                    break;
            }
        });
    }
    
    /**
     * Fix heading structure
     */
    function raywpAccessibilityFixHeadingStructure() {
        const headings = $('h1, h2, h3, h4, h5, h6');
        let previousLevel = 0;
        
        headings.each(function() {
            const $heading = $(this);
            const currentLevel = parseInt($heading.prop('tagName').charAt(1));
            
            // Check for skipped levels
            if (previousLevel > 0 && currentLevel > previousLevel + 1) {
                console.warn('WP Accessibility Pro: Heading level skipped from h' + previousLevel + ' to h' + currentLevel);
            }
            
            // Add aria-level if missing
            if (!$heading.attr('aria-level')) {
                $heading.attr('aria-level', currentLevel);
            }
            
            previousLevel = currentLevel;
        });
    }
    
    /**
     * Observe DOM changes for dynamic content
     */
    function raywpAccessibilityObserveDOMChanges() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }
        
        const observer = new MutationObserver(function(mutations) {
            let shouldReapply = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if any new elements were added that might need ARIA
                    for (let i = 0; i < mutation.addedNodes.length; i++) {
                        const node = mutation.addedNodes[i];
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            shouldReapply = true;
                            break;
                        }
                    }
                }
            });
            
            if (shouldReapply) {
                // Delay slightly to allow DOM to settle
                setTimeout(function() {
                    raywpAccessibilityApplyAriaRules();
                    raywpAccessibilityEnhanceFormLabels();
                }, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    /**
     * Announce to screen readers
     */
    function raywpAccessibilityAnnounce(message) {
        const $liveRegion = $('#raywp-live-region');
        if ($liveRegion.length) {
            $liveRegion.text(message);
            
            // Clear after announcement
            setTimeout(function() {
                $liveRegion.empty();
            }, 1000);
        }
    }
    
    // Make function globally available
    window.raywpAccessibilityAnnounce = raywpAccessibilityAnnounce;
    
})(jQuery);
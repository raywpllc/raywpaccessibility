/**
 * Browser-side contrast detection using computed styles
 * This provides accurate contrast ratios matching browser DevTools
 */
class ContrastDetector {
    constructor() {
        this.results = [];
        this.processedElements = new Set();
    }

    /**
     * Detect all contrast issues on the current page
     */
    detectContrastIssues() {
        this.results = [];
        this.processedElements.clear();

        // Get all text-containing elements
        const textElements = this.getTextElements();
        
        for (const element of textElements) {
            this.analyzeElement(element);
        }

        return this.results;
    }

    /**
     * Get all elements that contain meaningful text
     */
    getTextElements() {
        const textSelectors = [
            'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 
            'a', 'button', 'span', 'div', 'label', 
            'li', 'td', 'th', 'blockquote', 'cite',
            'strong', 'em', 'small', 'figcaption'
        ];

        const elements = [];
        
        for (const selector of textSelectors) {
            const nodeList = document.querySelectorAll(selector);
            for (const element of nodeList) {
                // Skip if already processed or has no meaningful text
                if (this.processedElements.has(element) || !this.hasValidText(element)) {
                    continue;
                }

                // Skip hidden elements
                if (this.isElementHidden(element)) {
                    continue;
                }

                // Skip if it's a container with block-level children (avoid double-checking)
                if (this.hasBlockChildren(element)) {
                    continue;
                }

                elements.push(element);
                this.processedElements.add(element);
            }
        }

        return elements;
    }

    /**
     * Check if element has valid text content
     */
    hasValidText(element) {
        const text = this.getElementText(element);
        const allText = element.textContent || '';
        
        // Skip elements with only navigation text (common in carousels)
        const navigationText = ['prev', 'next', 'previous', 'prevnext'];
        const lowerText = text.toLowerCase().replace(/\s/g, '');
        const lowerAllText = allText.toLowerCase().replace(/\s/g, '');
        
        if (navigationText.includes(lowerText) || navigationText.includes(lowerAllText)) {
            return false;
        }
        
        // Skip elements that are likely carousel/slider containers
        if (element.classList.contains('slider-box') || 
            element.classList.contains('carousel') ||
            element.classList.contains('slider') ||
            (element.tagName.toLowerCase() === 'div' && lowerAllText === 'prevnext')) {
            return false;
        }
        
        return text.length >= 3 && text.trim().length >= 3;
    }

    /**
     * Get clean text content from element
     */
    getElementText(element) {
        // Get direct text content, excluding children
        let text = '';
        for (const node of element.childNodes) {
            if (node.nodeType === Node.TEXT_NODE) {
                text += node.textContent;
            }
        }
        return text.trim();
    }

    /**
     * Check if element is hidden from users
     */
    isElementHidden(element) {
        const style = getComputedStyle(element);
        return (
            style.display === 'none' ||
            style.visibility === 'hidden' ||
            style.opacity === '0' ||
            element.hidden ||
            element.getAttribute('aria-hidden') === 'true'
        );
    }

    /**
     * Check if element has block-level children
     */
    hasBlockChildren(element) {
        const blockElements = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article', 'aside', 'nav'];
        
        for (const child of element.children) {
            if (blockElements.includes(child.tagName.toLowerCase())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Analyze an element for contrast issues
     */
    analyzeElement(element) {
        try {
            const textColor = this.getTextColor(element);
            const backgroundColor = this.getBackgroundColor(element);

            // Skip if we can't determine both colors accurately
            if (!textColor || !backgroundColor) {
                return;
            }

            const contrastRatio = this.calculateContrastRatio(textColor, backgroundColor);
            
            if (contrastRatio === null) {
                return;
            }

            const isLargeText = this.isLargeText(element);
            const meetsWCAG = this.meetsWCAGContrast(contrastRatio, isLargeText);

            if (!meetsWCAG) {
                this.results.push({
                    selector: this.generateSelector(element),
                    textColor: textColor,
                    backgroundColor: backgroundColor,
                    contrastRatio: contrastRatio,
                    isLargeText: isLargeText,
                    requiredRatio: isLargeText ? 3.0 : 4.5,
                    text: this.getElementText(element),
                    wcagLevel: 'AA'
                });
            }

        } catch (error) {
            console.warn('Error analyzing element contrast:', error, element);
        }
    }

    /**
     * Get the computed text color for an element
     */
    getTextColor(element) {
        const style = getComputedStyle(element);
        const color = style.color;
        
        if (!color || color === 'transparent') {
            return null;
        }

        return this.parseColor(color);
    }

    /**
     * Get the effective background color for an element
     */
    getBackgroundColor(element) {
        let currentElement = element;
        
        // Traverse up the DOM tree to find a non-transparent background
        while (currentElement && currentElement !== document.body.parentElement) {
            const style = getComputedStyle(currentElement);
            const bgColor = style.backgroundColor;
            
            // If we found a solid background color, use it
            if (bgColor && bgColor !== 'transparent' && bgColor !== 'rgba(0, 0, 0, 0)') {
                const parsed = this.parseColor(bgColor);
                if (parsed && parsed.alpha > 0) {
                    return parsed;
                }
            }

            // Check for background image without solid background
            if (style.backgroundImage && style.backgroundImage !== 'none') {
                // If there's a background image without a solid color, we can't accurately determine contrast
                return null;
            }

            currentElement = currentElement.parentElement;
        }

        // Default to white background if no background found
        return { r: 255, g: 255, b: 255, alpha: 1 };
    }

    /**
     * Parse a CSS color value to RGB
     */
    parseColor(colorStr) {
        if (!colorStr) return null;

        // Create a temporary element to let the browser parse the color
        const tempDiv = document.createElement('div');
        tempDiv.style.color = colorStr;
        document.body.appendChild(tempDiv);
        
        const computed = getComputedStyle(tempDiv).color;
        document.body.removeChild(tempDiv);

        // Parse rgb()/rgba() format
        const rgbaMatch = computed.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
        if (rgbaMatch) {
            return {
                r: parseInt(rgbaMatch[1]),
                g: parseInt(rgbaMatch[2]),
                b: parseInt(rgbaMatch[3]),
                alpha: rgbaMatch[4] ? parseFloat(rgbaMatch[4]) : 1
            };
        }

        return null;
    }

    /**
     * Calculate contrast ratio between two colors
     */
    calculateContrastRatio(color1, color2) {
        if (!color1 || !color2) return null;

        const l1 = this.getRelativeLuminance(color1);
        const l2 = this.getRelativeLuminance(color2);

        const lighter = Math.max(l1, l2);
        const darker = Math.min(l1, l2);

        return (lighter + 0.05) / (darker + 0.05);
    }

    /**
     * Calculate relative luminance of a color
     */
    getRelativeLuminance(color) {
        const { r, g, b } = color;

        // Convert to 0-1 range
        const rs = r / 255;
        const gs = g / 255;
        const bs = b / 255;

        // Apply gamma correction
        const rg = rs <= 0.03928 ? rs / 12.92 : Math.pow((rs + 0.055) / 1.055, 2.4);
        const gg = gs <= 0.03928 ? gs / 12.92 : Math.pow((gs + 0.055) / 1.055, 2.4);
        const bg = bs <= 0.03928 ? bs / 12.92 : Math.pow((bs + 0.055) / 1.055, 2.4);

        // Calculate luminance
        return 0.2126 * rg + 0.7152 * gg + 0.0722 * bg;
    }

    /**
     * Check if text is considered large (18pt+ or 14pt+ bold)
     */
    isLargeText(element) {
        const style = getComputedStyle(element);
        const fontSize = parseFloat(style.fontSize);
        const fontWeight = style.fontWeight;
        
        // Convert px to pt (approximate)
        const fontSizePt = fontSize * 0.75;
        
        // Large text: 18pt+ or 14pt+ bold
        return fontSizePt >= 18 || (fontSizePt >= 14 && (fontWeight === 'bold' || parseInt(fontWeight) >= 700));
    }

    /**
     * Check if contrast ratio meets WCAG AA standards
     */
    meetsWCAGContrast(ratio, isLargeText) {
        return isLargeText ? ratio >= 3.0 : ratio >= 4.5;
    }

    /**
     * Generate a CSS selector for an element
     */
    generateSelector(element) {
        const tagName = element.tagName.toLowerCase();
        const id = element.id ? `#${element.id}` : '';
        const classes = element.className ? `.${element.className.split(' ').filter(c => c).join('.')}` : '';
        
        let selector = tagName;
        if (id) selector += id;
        if (classes) selector += classes;
        
        // Truncate very long selectors
        if (selector.length > 100) {
            selector = selector.substring(0, 97) + '...';
        }
        
        return selector;
    }
}

// Export for use in other scripts
window.ContrastDetector = ContrastDetector;
window.ContrastDetector = ContrastDetector;
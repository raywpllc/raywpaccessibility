=== RayWP Accessibility ===
Contributors: raywpllc
Tags: accessibility, wcag, ada, aria, a11y
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan your entire WordPress site for WCAG accessibility issues, apply ARIA attributes automatically, and detect color contrast problems - all without external APIs.

== Description ==

RayWP Accessibility is a comprehensive WordPress plugin that helps improve your website's accessibility to better meet WCAG guidelines and accessibility standards. The plugin implements ARIA attributes and accessibility improvements throughout your entire page output, including navigation, headers, footers, and all page elements, not just post content. Unlike widget-based solutions, this plugin works automatically without requiring user intervention.

**Important:** This plugin helps identify and fix common accessibility issues, but no automated tool can guarantee full WCAG compliance. Manual testing with assistive technologies and expert review are still essential for true accessibility conformance.

**Automated Detection:**

* Site-wide accessibility scanning across multiple pages
* Color contrast ratio checking (WCAG 2.1 SC 1.4.3)
* Missing iframe title detection
* Auto-playing video detection
* Form accessibility issue scanning
* Detailed issue reporting with recommendations

**Automated Fixes:**

* Skip navigation links (WCAG 2.1 SC 2.4.1)
* Enhanced keyboard focus indicators (WCAG 2.1 SC 2.4.7)
* ARIA attribute injection via CSS selectors
* Color contrast overrides for problem elements
* Missing iframe titles added dynamically
* Video controls for auto-playing media

**Developer Tools:**

* Unlimited ARIA attribute rules using CSS selectors
* Complete selection of ARIA landmark roles
* Real-time CSS selector validation before applying
* Output buffer processing for entire page coverage

**Supported Form Plugins:**

* Contact Form 7 (full scanning and fixes)
* WPForms (scanning)
* Gravity Forms (basic scanning)

**Who This Plugin Helps:**

* Site owners needing to improve accessibility compliance
* Developers adding ARIA attributes without manual coding
* Content editors identifying accessibility issues before publishing
* Agencies managing accessibility across client sites


== Installation ==

1. Upload the plugin files to `/wp-content/plugins/raywp-accessibility/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'RayWP Accessibility' in the admin menu to configure settings

**Recommended First Steps:**

1. Run a site-wide scan from the Reports page to identify existing issues
2. Review contrast issues and configure color overrides if needed
3. Enable skip links and focus indicators in Settings
4. Add ARIA rules for your theme's navigation and landmark elements
5. Use the real-time selector validation to test rules before applying


== External services ==

This plugin does not use any external services - all analysis occurs within the plugin. 


== Frequently Asked Questions ==

= Is this an accessibility overlay? =

No. Unlike overlay widgets (AccessiBe, UserWay, AudioEye), RayWP Accessibility fixes actual code issues rather than masking problems with JavaScript widgets. It works server-side to add proper ARIA attributes and semantic markup that assistive technologies can interpret correctly. There are no floating buttons or popup menus added to your site.

= How does this plugin improve website accessibility? =

RayWP Accessibility works by processing your entire page output to apply ARIA attributes to navigation, headers, footers, and all page elements. The plugin includes comprehensive scanning to identify contrast issues, missing iframe titles, auto-playing videos, and form accessibility problems. Detected issues can be fixed automatically or addressed manually.

= Does this plugin fix all accessibility issues automatically? =

No plugin can fix all accessibility issues automatically. RayWP Accessibility provides tools to address common issues and helps you implement proper ARIA markup to improve accessibility. Manual testing with assistive technologies and expert review are essential for meeting accessibility standards. This plugin is a tool to assist in improving accessibility and helping meet WCAG guidelines, not a guarantee of conformance.

= Does this plugin send data to external servers? =

No. All scanning and processing happens entirely on your own server. No data is sent to external APIs, ensuring complete privacy and GDPR compliance. Your accessibility data stays under your control.

= Will this plugin slow down my website? =

Minimal impact. The plugin uses efficient output buffer processing that adds negligible server processing time. Scanning only runs when initiated from the admin panel, not on every page load. Front-end visitors experience no slowdown.

= Can I test my CSS selectors before applying them? =

Yes! The plugin includes a real-time selector testing feature that shows you exactly which elements will be affected by your ARIA rules before you apply them. This helps prevent unintended changes to your site.

= Which form plugins are supported for scanning? =

The plugin fully supports Contact Form 7 (scanning and fixes), WPForms (scanning), and Gravity Forms (basic scanning). Additional form plugins may be supported in future updates.

== Screenshots ==

1. Main admin interface showing ARIA rule configuration
2. Form scanning results with identified accessibility issues
3. Real-time CSS selector validation tool
4. Accessibility improvement reports dashboard

== Changelog ==

= 1.0.4 =
* Improved: More lenient accessibility scoring for repeating issues on multiple pages
* Enhanced: Better balance between accuracy and usability
* Security: Improved input sanitization in export functionality
* Security: Added capability checks to AJAX handlers
* Updated: Documentation to accurately reflect supported features

= 1.0.2 =
* Fixed: Use proper WordPress enqueue functions for inline styles and scripts instead of direct output
* Improved: Better sanitization of color values and CSS selectors

= 1.0.1 =
* Security improvements and code review fixes

= 1.0.0 =
* Initial release
* ARIA attribute management system
* Form scanning for major form plugins
* Real-time selector validation
* Enhanced focus indicators
* Skip navigation links
* Comprehensive admin interface

== Upgrade Notice ==

= 1.0.4 =
Security improvements and updated documentation. Recommended update for all users.

= 1.0.2 =
Important update: Fixes WordPress coding standards compliance and adds iFrame/video accessibility checks.

= 1.0.0 =
Initial release of RayWP Accessibility with comprehensive ARIA support and form accessibility scanning.
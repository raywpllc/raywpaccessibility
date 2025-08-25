# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is RayWP Accessibility, an advanced WordPress plugin that provides comprehensive accessibility features including ARIA attribute management, form scanning, and WCAG compliance tools. Unlike basic accessibility plugins, this plugin processes the entire page output to apply accessibility fixes site-wide.

## Architecture

The plugin uses a modern object-oriented architecture with namespaces and autoloading:

- **raywp-accessibility.php**: Main plugin file and entry point
- **includes/**: Core plugin classes and functionality
- **admin/**: Admin interface and dashboard components  
- **frontend/**: Frontend processing and DOM manipulation
- **assets/**: CSS and JavaScript files

Key architectural patterns:
- Autoloading with custom autoloader (`class-autoloader.php`)
- Component-based architecture with Plugin class as coordinator
- Trait-based ARIA validation (`trait-aria-validator.php`)
- Output buffer processing for entire page manipulation
- Comprehensive AJAX handlers for admin interface

## Development Commands

This is a traditional WordPress plugin without build tools:

- **No build process** - Assets are served directly
- **No package manager** - Self-contained with no external dependencies  
- **No test framework** - Manual testing required

To develop:
1. Edit PHP files directly in their respective directories
2. Modify CSS/JS in `assets/` folder
3. Changes take effect immediately after saving

## Key Implementation Details

**ARIA Management**: The `Aria_Manager` class handles all ARIA operations with proper validation using the `Aria_Validator` trait. Unlike other plugins, this processes the entire DOM using DOMDocument.

**Output Buffer Processing**: The `Dom_Processor` class captures and modifies entire page output via `ob_start()`, ensuring all page elements are processed, not just post content.

**Form Scanner**: Supports major form plugins (CF7, WPForms, Gravity Forms, etc.) with deep scanning and automated fixes.

**AJAX Integration**: Comprehensive AJAX handlers for real-time selector validation, form scanning, and rule management.

**Database Storage**: Uses WordPress options API for settings and creates custom table for scan results.

## WordPress Environment

- Plugin path: `/Users/arosenkoetter/Sites/City Park Dev/plugins/raywp-accessibility/`
- Follows WordPress coding standards and security practices
- All functions/classes use `raywp_accessibility_` or `RayWP\Accessibility\` prefixes
- Implements proper nonce verification and capability checks
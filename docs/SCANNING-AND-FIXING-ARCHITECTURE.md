# RayWP Accessibility - Scanning & Fixing Architecture

> **Last Updated:** January 12, 2026
> **Purpose:** Complete technical documentation of how the plugin detects, tracks, and fixes accessibility issues.

## Table of Contents

1. [Overview](#overview)
2. [Two Scan Types](#two-scan-types)
3. [Auto-Fix System (DOM Processor)](#auto-fix-system-dom-processor)
4. [Data Flow Architecture](#data-flow-architecture)
5. [Scoring Algorithm](#scoring-algorithm)
6. [Database & Options](#database--options)
7. [Key Files Reference](#key-files-reference)
8. [Known Logic Issues](#known-logic-issues)
9. [Complete User Journeys](#complete-user-journeys)

---

## Overview

The RayWP Accessibility plugin has three main components:

1. **Scanning** - Detects accessibility issues on pages
2. **Auto-Fixing** - Automatically fixes certain issues via DOM manipulation
3. **Reporting** - Displays results and tracks improvement

```
┌─────────────────────────────────────────────────────────────────┐
│                    ADMIN DASHBOARD                               │
│  ┌─────────────────┐    ┌──────────────────────┐                │
│  │ "Run Full Scan" │    │ "Check Score w/Fixes"│                │
│  └────────┬────────┘    └──────────┬───────────┘                │
│           │                        │                             │
│           ▼                        ▼                             │
│  ┌─────────────────┐    ┌──────────────────────┐                │
│  │ PHP Server-Side │    │ JS + axe-core iframe │                │
│  │ Scan (no fixes) │    │ Scan (WITH fixes)    │                │
│  └────────┬────────┘    └──────────┬───────────┘                │
│           │                        │                             │
│           ▼                        ▼                             │
│  ┌─────────────────────────────────────────────┐                │
│  │           Database Storage                   │                │
│  │  - wp_raywp_accessibility_scan_results      │                │
│  │  - raywp_accessibility_scan_with_fixes_results │             │
│  │  - raywp_accessibility_live_score           │                │
│  └─────────────────────────────────────────────┘                │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Visitor)                            │
│  ┌─────────────────┐                                            │
│  │ Page Request    │                                            │
│  └────────┬────────┘                                            │
│           ▼                                                      │
│  ┌─────────────────┐    ┌──────────────────────┐                │
│  │ Output Buffer   │───▶│ DOM Processor        │                │
│  │ Captures HTML   │    │ Applies Auto-Fixes   │                │
│  └─────────────────┘    └──────────┬───────────┘                │
│                                    ▼                             │
│                         ┌──────────────────────┐                │
│                         │ Fixed HTML to Browser│                │
│                         └──────────────────────┘                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Two Scan Types

### Scan Type A: "Run Full Scan" (Server-Side)

**Button Location:** Reports page, ID `#run-full-scan`

**What It Does:**
- Scans pages WITHOUT the plugin's auto-fixes applied
- Shows the "original" accessibility state of the theme
- Stores detailed results in a database table

**Technical Flow:**

```
[Admin clicks button]
       ↓
[JavaScript: admin.js line 238-274]
       ↓
$.post(ajaxurl, { action: 'raywp_accessibility_run_full_scan' })
       ↓
[PHP: class-core-plugin.php::ajax_run_full_scan() lines 274-442]
       ↓
  ├─ check_ajax_referer('raywp_accessibility_nonce')
  ├─ current_user_can('manage_options')
  ├─ get_pages_for_scanning() → max 20 pages
  ├─ For each page:
  │    └─ Accessibility_Checker::generate_report($url, false)
  │         └─ false = WITHOUT fixes
  ├─ store_scan_results() → database table
  ├─ calculate_scan_score($issues, $pages)
  └─ wp_send_json_success({ accessibility_score, total_issues, results })
```

**Data Stored:**
- Individual issues in `wp_raywp_accessibility_scan_results` table
- Each issue has: type, severity, page URL, element selector, WCAG reference

**Key Code Locations:**
| Function | File | Lines |
|----------|------|-------|
| `ajax_run_full_scan()` | class-core-plugin.php | 274-442 |
| `get_pages_for_scanning()` | class-core-plugin.php | 513-580 |
| `store_scan_results()` | class-core-plugin.php | 748-809 |
| JS handler | admin.js | 238-274 |

---

### Scan Type B: "Check Score With Fixes" (Browser-Based axe-core)

**Button Location:** Reports page, ID `#check-fixed-score`

**What It Does:**
- Scans pages WITH the plugin's auto-fixes applied (via iframe)
- Uses real browser + axe-core for accurate accessibility testing
- Shows the "improved" accessibility state

**Technical Flow:**

```
[Admin clicks button]
       ↓
[JavaScript: axe-integration.js lines 68-139]
       ↓
handleCheckFixedScore()
  ├─ getPagesList() → AJAX to ajax_get_pages_list
  │    └─ Returns up to 20 page URLs
  │
  ├─ scanner.scanMultiplePages(pages) [iframe-scanner.js]
  │    └─ For each page:
  │         ├─ Create hidden iframe (1024x768, position: -9999px)
  │         ├─ Load page in iframe (WITH DOM Processor active!)
  │         ├─ Wait for page load
  │         ├─ Run axe.run() with WCAG rules
  │         ├─ Collect violations array
  │         └─ Return results
  │
  ├─ scanner.convertToInternalFormat(scanResults)
  │    └─ Maps axe-core IDs to internal issue types
  │
  └─ processAxeResults() → AJAX to ajax_process_axe_results
       ↓
[PHP: class-core-plugin.php::ajax_process_axe_results() lines 1553-1698]
       ↓
  ├─ Parse JSON results
  ├─ For each issue:
  │    └─ is_auto_fixable(type) ? → fixed_issues : remaining_issues
  │         ⚠️ BUG: This logic is backwards! (see Known Issues)
  ├─ Calculate original_score (all issues)
  ├─ Calculate fixed_score (remaining issues only)
  ├─ update_option('raywp_accessibility_scan_with_fixes_results', $data)
  ├─ update_option('raywp_accessibility_live_score', $fixed_score)
  └─ wp_send_json_success($response_data)
```

**Why Use Browser-Based Scanning?**
- axe-core runs in real browser with JavaScript executed
- Sees the actual DOM after all scripts run
- Catches dynamic accessibility issues
- More accurate than server-side HTML parsing

**Key Code Locations:**
| Function | File | Lines |
|----------|------|-------|
| `handleCheckFixedScore()` | axe-integration.js | 68-139 |
| `RayWPIframeScanner.scanMultiplePages()` | iframe-scanner.js | ~50-150 |
| `ajax_process_axe_results()` | class-core-plugin.php | 1553-1698 |
| `map_axe_id_to_issue_type()` | class-core-plugin.php | 587-696 |

---

## Auto-Fix System (DOM Processor)

The DOM Processor is the heart of the plugin's automatic fixing capability.

### When It Runs

**Hook:** `template_redirect` at priority 0 (very early)
**Location:** `class-core-plugin.php` line 122

```php
add_action('template_redirect', [$this, 'start_output_buffering'], 0);
```

**Process:**
1. WordPress starts rendering a frontend page
2. `template_redirect` fires before any output
3. Plugin starts output buffering: `ob_start([$dom_processor, 'process_output'])`
4. WordPress renders entire page to buffer
5. Buffer callback captures complete HTML
6. `Dom_Processor::process_output($html)` runs
7. Modified HTML sent to browser

### What Gets Fixed

**File:** `class-frontend-dom-processor.php`

| Fix | Setting Key | What It Does | Lines |
|-----|-------------|--------------|-------|
| **Empty Alt Text** | `fix_empty_alt` | Adds `alt=""` to `<img>` tags without alt | 111-117 |
| **Page Language** | `fix_lang_attr` | Adds `lang="en-US"` to `<html>` | 120-126 |
| **Form Labels** | `fix_form_labels` | Associates labels with inputs | 129-132 |
| **Form ARIA** | `fix_forms` | Adds ARIA to form elements | 135-141 |
| **Skip Links** | `add_skip_links` | Adds "Skip to content" link | 144-147 |
| **Main Landmark** | `add_main_landmark` | Wraps content in `<main>` | 150-153 |
| **Heading Hierarchy** | `fix_heading_hierarchy` | Fixes skipped heading levels | 156-159 |
| **Empty Headings** | `fix_empty_headings` | Removes or fixes empty `<h1>`-`<h6>` | 168-171 |
| **Iframe Titles** | `fix_iframe_titles` | Adds title to iframes | ~180 |
| **Button Names** | `fix_button_names` | Adds accessible names to buttons | ~185 |
| **Link Names** | `fix_generic_links` | Fixes "Read more" link text | ~190 |
| **Duplicate IDs** | `fix_duplicate_ids` | Makes duplicate IDs unique | ~195 |
| **Custom ARIA** | `enable_aria` | Applies ARIA Manager rules | Via Aria_Manager |

### Settings Structure

```php
// Stored in wp_options as 'raywp_accessibility_settings'
$settings = [
    'fix_empty_alt' => true,        // Default: true
    'fix_forms' => true,            // Default: true
    'fix_form_labels' => true,
    'add_skip_links' => true,       // Default: true
    'add_main_landmark' => true,    // Default: true
    'fix_lang_attr' => true,
    'fix_heading_hierarchy' => true,
    'fix_empty_headings' => true,
    'fix_button_names' => true,
    'fix_generic_links' => true,
    'fix_iframe_titles' => true,
    'fix_duplicate_ids' => true,
    'enable_aria' => true,
    // ... more settings
];
```

### Default Settings (Set on Activation)

**File:** `class-core-activator.php`

These are enabled by default when plugin is first activated:
- `fix_empty_alt` = true
- `fix_forms` = true
- `add_skip_links` = true
- `add_main_landmark` = true

---

## Data Flow Architecture

### Complete Data Flow Diagram

```
┌────────────────────────────────────────────────────────────────────────┐
│                           USER ACTIONS                                  │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐  │
│  │ Run Full Scan   │     │ Check Score     │     │ Enable All      │  │
│  │ #run-full-scan  │     │ #check-fixed-   │     │ Fixes           │  │
│  │                 │     │ score           │     │ #enable-all-    │  │
│  └────────┬────────┘     └────────┬────────┘     │ fixes           │  │
│           │                       │              └────────┬────────┘  │
└───────────┼───────────────────────┼───────────────────────┼───────────┘
            │                       │                       │
            ▼                       ▼                       ▼
┌────────────────────────────────────────────────────────────────────────┐
│                         JAVASCRIPT LAYER                                │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  admin.js              axe-integration.js       admin.js               │
│  line 238-274          line 68-139              line 201-235           │
│      │                      │                       │                  │
│      │                      │                       │                  │
│      ▼                      ▼                       ▼                  │
│  ┌─────────┐          ┌─────────────┐         ┌─────────┐             │
│  │  AJAX   │          │iframe-scanner│         │  AJAX   │             │
│  │  POST   │          │ + axe-core  │         │  POST   │             │
│  └────┬────┘          └──────┬──────┘         └────┬────┘             │
│       │                      │                     │                   │
└───────┼──────────────────────┼─────────────────────┼───────────────────┘
        │                      │                     │
        ▼                      ▼                     ▼
┌────────────────────────────────────────────────────────────────────────┐
│                           PHP AJAX HANDLERS                             │
│                      (class-core-plugin.php)                           │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ajax_run_full_scan()    ajax_process_axe_results()  ajax_enable_all_  │
│  lines 274-442           lines 1553-1698             fixes() 447-508   │
│      │                          │                         │            │
│      │                          │                         │            │
│      ▼                          ▼                         ▼            │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    │
│  │ Accessibility_  │    │ Process issues  │    │ Update settings │    │
│  │ Checker::       │    │ Calculate score │    │ option          │    │
│  │ generate_report │    │ Store results   │    │                 │    │
│  └────────┬────────┘    └────────┬────────┘    └────────┬────────┘    │
│           │                      │                      │              │
└───────────┼──────────────────────┼──────────────────────┼──────────────┘
            │                      │                      │
            ▼                      ▼                      ▼
┌────────────────────────────────────────────────────────────────────────┐
│                         DATABASE STORAGE                                │
│                         (WordPress Options + Custom Table)              │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ┌──────────────────────────────────┐                                  │
│  │ wp_raywp_accessibility_          │  ← Full scan individual issues   │
│  │ scan_results (table)             │                                  │
│  └──────────────────────────────────┘                                  │
│                                                                        │
│  ┌──────────────────────────────────┐                                  │
│  │ raywp_accessibility_scan_with_   │  ← Dual scan results with        │
│  │ fixes_results (option)           │    issue_breakdown array         │
│  └──────────────────────────────────┘                                  │
│                                                                        │
│  ┌──────────────────────────────────┐                                  │
│  │ raywp_accessibility_live_score   │  ← Score displayed on dashboard  │
│  │ (option)                         │                                  │
│  └──────────────────────────────────┘                                  │
│                                                                        │
│  ┌──────────────────────────────────┐                                  │
│  │ raywp_accessibility_settings     │  ← Enable/disable each fix type  │
│  │ (option)                         │                                  │
│  └──────────────────────────────────┘                                  │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
            │
            ▼
┌────────────────────────────────────────────────────────────────────────┐
│                         ADMIN DISPLAY                                   │
│                    (class-admin-admin.php)                             │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Reports Page reads from:                                              │
│  - get_option('raywp_accessibility_scan_with_fixes_results')          │
│  - get_option('raywp_accessibility_live_score')                       │
│  - Database table for detailed issue list                              │
│                                                                        │
│  Displays:                                                             │
│  - Original Score vs Live Score                                        │
│  - "Requires Manual Attention" table                                   │
│  - Debug sections (if enabled)                                         │
│  - Scan Results breakdown by page                                      │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## Scoring Algorithm

### Severity Weights

```php
$severity_weights = [
    'critical' => 10,   // Most severe
    'high'     => 5,
    'serious'  => 5,    // axe-core terminology
    'medium'   => 3,
    'moderate' => 3,    // axe-core terminology
    'low'      => 1,
    'minor'    => 1     // axe-core terminology
];
```

### Score Calculation Formula

```
For each issue:
    weight += severity_weights[issue.severity]

average_weight_per_page = total_weight / pages_scanned
score = MAX(0, 100 - ROUND(average_weight_per_page))
```

**Example:**
- 20 pages scanned
- 100 medium issues (weight: 100 × 3 = 300)
- Average per page: 300 / 20 = 15
- Score: 100 - 15 = **85%**

**Implementation:** `class-core-plugin.php` lines 706-743

```php
public function calculate_scan_score($issues, $pages_scanned) {
    $severity_weights = [
        'critical' => 10, 'high' => 5, 'serious' => 5,
        'medium' => 3, 'moderate' => 3, 'low' => 1, 'minor' => 1
    ];

    $total_weight = 0;
    foreach ($issues as $issue) {
        $severity = $issue['severity'] ?? 'medium';
        $total_weight += $severity_weights[$severity] ?? 3;
    }

    $avg_weight = $total_weight / max(1, $pages_scanned);
    return max(0, round(100 - $avg_weight));
}
```

---

## Database & Options

### WordPress Options (wp_options table)

| Option Key | Type | Purpose | Set By |
|------------|------|---------|--------|
| `raywp_accessibility_settings` | array | Master settings for all fixes | Settings page, Enable All Fixes |
| `raywp_accessibility_scan_with_fixes_results` | array | Full scan results with breakdown | ajax_process_axe_results |
| `raywp_accessibility_live_score` | int (0-100) | Current accessibility score | ajax_store_live_score |
| `raywp_accessibility_live_score_timestamp` | string | When score was last updated | ajax_store_live_score |
| `raywp_accessibility_axe_results` | array | Raw axe-core violation data | ajax_process_axe_results |
| `raywp_accessibility_color_overrides` | array | Contrast fix CSS rules | ajax_add_color_override |
| `raywp_accessibility_db_version` | string | Schema version tracker | Activator |

### `raywp_accessibility_scan_with_fixes_results` Structure

```php
[
    'original_score' => 40,           // Score before fixes
    'fixed_score' => 85,              // Score after fixes
    'pages_scanned' => 20,
    'total_issues' => 241,
    'fixed_count' => 50,              // Issues auto-fixed
    'remaining_count' => 191,         // Issues needing manual fix
    'issue_breakdown' => [
        'detected' => [...],          // All issues found (SHOULD exist but doesn't!)
        'fixed' => [...],             // Auto-fixed issues
        'remaining' => [...],         // Manual attention needed
        'unfixable' => [...]          // Auto-fixable but failed
    ],
    'details' => [                    // Per-page breakdown
        [
            'url' => 'https://...',
            'title' => 'Homepage',
            'original_issues' => 15,
            'remaining_issues' => 10,
            'success' => true
        ],
        // ... more pages
    ],
    'scan_type' => 'axe-core-iframe',
    'timestamp' => '2026-01-12 18:21:26'
]
```

### Custom Database Table

**Table Name:** `{prefix}_raywp_accessibility_scan_results`

**Schema:**
```sql
CREATE TABLE wp_raywp_accessibility_scan_results (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    scan_date datetime DEFAULT CURRENT_TIMESTAMP,
    page_url varchar(255) NOT NULL,
    issue_type varchar(100) NOT NULL,
    issue_severity varchar(20) NOT NULL,
    issue_description text,
    element_selector varchar(255),
    wcag_reference varchar(50),
    wcag_level varchar(5),
    auto_fixable tinyint(1) DEFAULT 0,
    fixed tinyint(1) DEFAULT 0,
    scan_session_id varchar(100),
    PRIMARY KEY (id),
    KEY page_url (page_url),
    KEY issue_type (issue_type),
    KEY issue_severity (issue_severity),
    KEY scan_date (scan_date),
    KEY scan_session_id (scan_session_id)
);
```

---

## Key Files Reference

### Core Plugin Files

| File | Purpose | Key Functions |
|------|---------|---------------|
| `includes/class-core-plugin.php` | Main plugin class, AJAX handlers | `ajax_run_full_scan()`, `ajax_process_axe_results()`, `should_issue_be_auto_fixed()` |
| `frontend/class-frontend-dom-processor.php` | Applies auto-fixes | `process_output()`, `apply_accessibility_fixes()` |
| `frontend/class-frontend-accessibility-checker.php` | Detects issues | `generate_report()`, `check_*()` methods |
| `admin/class-admin-admin.php` | Admin UI, Reports page | `render_reports_page()` |

### JavaScript Files

| File | Purpose | Key Functions |
|------|---------|---------------|
| `assets/js/admin.js` | General admin, "Run Full Scan" | Event handlers for buttons |
| `assets/js/axe-integration.js` | "Check Score With Fixes" | `handleCheckFixedScore()`, `processAxeResults()` |
| `assets/js/iframe-scanner.js` | Browser scanning | `RayWPIframeScanner`, `scanMultiplePages()` |
| `assets/js/axe.min.js` | axe-core library | Accessibility testing engine |

### Function Line Numbers (class-core-plugin.php)

| Function | Lines | Purpose |
|----------|-------|---------|
| `ajax_run_full_scan()` | 274-442 | Server-side full scan |
| `ajax_enable_all_fixes()` | 447-508 | Enable all fix settings |
| `get_pages_for_scanning()` | 513-580 | Get up to 20 pages |
| `map_axe_id_to_issue_type()` | 587-696 | Convert axe IDs to internal types |
| `calculate_scan_score()` | 706-743 | Severity-weighted scoring |
| `store_scan_results()` | 748-809 | Save to database table |
| `ajax_scan_with_fixes()` | 1118-1334 | Old server-side dual scan (deprecated?) |
| `should_issue_be_auto_fixed()` | 1341-1425 | Check if issue can be auto-fixed |
| `ajax_get_pages_list()` | 1526-1547 | Return pages for JS scanning |
| `ajax_process_axe_results()` | 1553-1698 | Process browser scan results |

---

## Known Logic Issues

### Issue 1: Backwards Auto-Fix Classification (CRITICAL)

**Location:** `ajax_process_axe_results()` lines 1586-1595

**Current (BROKEN) Logic:**
```php
foreach ($all_issues as $issue) {
    $issue_type = $issue['type'] ?? '';
    if ($admin_instance->is_auto_fixable($issue_type)) {
        $fixed_issues[] = $issue;    // ❌ WRONG!
    } else {
        $remaining_issues[] = $issue;
    }
}
```

**Why It's Wrong:**
1. The axe-core scan runs on the LIVE site with auto-fixes ALREADY APPLIED
2. If an issue is FOUND by axe-core, it means the fix DIDN'T work
3. Marking found issues as "fixed" is backwards - they're clearly NOT fixed
4. This causes the debug section to show misleading data

**Correct Logic:**
```php
// ALL issues found by axe-core are "remaining" - they exist despite fixes
$remaining_issues = $all_issues;
$fixed_issues = []; // Can't determine from single scan
```

### Issue 2: Missing `detected` Array

**Location:** `ajax_process_axe_results()` line 1673-1677

The debug section expects `issue_breakdown['detected']` but it's never set:
```php
'issue_breakdown' => [
    'fixed' => $fixed_issues,
    'remaining' => $remaining_issues,
    'unfixable' => []
    // 'detected' => $all_issues  ← MISSING!
],
```

### Issue 3: Undefined Variable

**Location:** `ajax_scan_with_fixes()` line 1316

```php
'total_original_issues' => $total_original_issues,  // ❌ Never defined!
```

### Issue 4: Results Array Key Mismatch

**Location:** `ajax_scan_with_fixes()` lines 1295-1306

```php
foreach ($results as $result) {
    if (!empty($result['original_scan']['issues'])) {  // ❌ Wrong key!
        // $result has keys: 'url', 'original_issues', 'remaining_issues'
        // NOT 'original_scan'
    }
}
```

---

## Complete User Journeys

### Journey 1: First-Time Setup

```
1. User installs plugin
   └─ Activator runs, sets default settings:
      - fix_empty_alt = true
      - fix_forms = true
      - add_skip_links = true
      - add_main_landmark = true

2. User visits Reports page
   └─ No scan results yet
   └─ Shows "Run a scan to check for accessibility issues"

3. User clicks "Run Full Scan"
   └─ Scans 20 pages WITHOUT fixes
   └─ Gets baseline accessibility score (e.g., 40%)
   └─ Stores issues in database

4. User clicks "Enable All Auto-Fixes"
   └─ All fix settings set to true
   └─ DOM Processor will now apply all fixes

5. User clicks "Check Score With Fixes"
   └─ Scans 20 pages WITH fixes (via iframe + axe-core)
   └─ Gets improved score (e.g., 85%)
   └─ Shows comparison: 40% → 85%
```

### Journey 2: Frontend Visitor Experience

```
1. Visitor requests page: https://example.com/about/

2. WordPress loads, fires 'template_redirect' hook

3. Plugin's start_output_buffering() runs:
   └─ ob_start([$dom_processor, 'process_output'])

4. WordPress renders page normally to buffer:
   └─ Theme outputs HTML with accessibility issues:
      - <img src="photo.jpg">                    ← Missing alt
      - <form><input type="text"></form>         ← Missing label
      - No skip link
      - No main landmark

5. Output buffer closes, Dom_Processor::process_output() runs:
   └─ DOMDocument parses HTML
   └─ apply_accessibility_fixes():
      - Adds alt="" to images
      - Adds aria-label to form inputs
      - Injects skip link at top
      - Wraps content in <main>
   └─ Returns modified HTML

6. Browser receives accessible HTML:
   └─ <a class="skip-link" href="#main">Skip to content</a>
   └─ <img src="photo.jpg" alt="">
   └─ <main id="main">
   └─ <form><input type="text" aria-label="Input field"></form>
```

### Journey 3: Debugging an Issue

```
1. Admin sees "237 contrast issues" on Reports page

2. Admin expands debug sections:
   └─ "All Detected Issues" - Should show 241 total
   └─ "Auto-Fixed Issues" - Shows what was auto-fixed
   └─ "Issues After Auto-Fix" - Shows 237 contrast + others

3. Admin notes contrast issues can't be auto-fixed
   └─ Severity: High
   └─ Requires CSS changes (manual)

4. Admin uses Color Contrast Override feature:
   └─ Adds CSS rule to fix specific element colors
   └─ Stored in raywp_accessibility_color_overrides

5. Admin re-runs "Check Score With Fixes"
   └─ Contrast issues reduced
   └─ Score improves
```

---

## Appendix: axe-core ID to Internal Type Mapping

**Function:** `map_axe_id_to_issue_type()` (lines 587-696)

| axe-core ID | Internal Type | Description |
|-------------|---------------|-------------|
| `color-contrast` | `low_contrast` | Insufficient color contrast |
| `image-alt` | `missing_alt` | Image missing alt text |
| `label` | `missing_label` | Form field missing label |
| `button-name` | `button_missing_accessible_name` | Button missing accessible name |
| `link-name` | `link_no_accessible_name` | Link missing accessible name |
| `html-has-lang` | `missing_page_language` | Page missing lang attribute |
| `landmark-one-main` | `missing_main_landmark` | Missing main landmark |
| `bypass` | `missing_skip_links` | Missing skip links |
| `heading-order` | `heading_hierarchy_skip` | Heading levels skipped |
| `empty-heading` | `empty_heading` | Empty heading element |
| `frame-title` | `iframe_missing_title` | iFrame missing title |
| `duplicate-id` | `duplicate_ids` | Duplicate ID attribute |
| `page-has-heading-one` | `page-has-heading-one` | Page should have h1 |
| `presentation-role-conflict` | `presentation-role-conflict` | ARIA role conflict |

---

## Revision History

| Date | Author | Changes |
|------|--------|---------|
| 2026-01-12 | Claude Code | Initial comprehensive documentation |

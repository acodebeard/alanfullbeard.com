# Front Page Customizer Sections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the current WordPress front-page copy editable through the Customizer while preserving the existing front-end markup and layout.

**Architecture:** Keep the front-page template responsible for markup and move editable copy/defaults into small theme helper functions in `functions.php`. Register a Customizer panel with Hero, Bio, and Summary Cards sections that write theme mods consumed by the template.

**Tech Stack:** WordPress classic theme PHP, WordPress Customizer API, existing plain PHP setup tests, WP-CLI verification.

---

### Task 1: Add Regression Coverage

**Files:**
- Modify: `tests/WordPressSetupTest.php`

- [ ] **Step 1: Write the failing test**

Add checks that `functions.php` registers a Customizer panel/fields and that `front-page.php` calls helper functions for editable text and years.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/WordPressSetupTest.php`

Expected: FAIL because the Customizer helpers and template calls do not exist yet.

### Task 2: Add Theme Helpers And Customizer Registration

**Files:**
- Modify: `wp-content/themes/alanfullbeard/functions.php`

- [ ] **Step 1: Implement defaults and helpers**

Add `alanfullbeard_lcars_front_page_defaults()`, `alanfullbeard_lcars_front_page_text()`, `alanfullbeard_lcars_front_page_years()`, and Customizer sanitizers.

- [ ] **Step 2: Register controls**

Add `alanfullbeard_lcars_customize_front_page_sections()` on `customize_register`, with Hero, Bio, and Summary Card sections.

### Task 3: Wire The Template

**Files:**
- Modify: `wp-content/themes/alanfullbeard/front-page.php`

- [ ] **Step 1: Replace hardcoded copy**

Use the helper functions for all editable strings and generate odometer spans from the editable years value.

- [ ] **Step 2: Verify**

Run: `composer test`, `composer phpstan`, and a WP-CLI render probe with a temporary theme mod override.

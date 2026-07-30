# Odometer Once Per Years Value Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the WordPress homepage years odometer animate only once per browser for the current years value.

**Architecture:** The server renders the odometer in its final state by default. A small front-page-only script checks `localStorage` for a key that includes the years value, adds the animation class only when missing, and writes the key after the animation completes.

**Tech Stack:** WordPress theme PHP, vanilla JavaScript, CSS, existing PHP smoke tests.

---

### Task 1: Add Failing Theme Coverage

**Files:**
- Modify: `tests/WordPressSetupTest.php`

- [ ] **Step 1: Add checks for the WordPress odometer behavior**

Add assertions requiring a front-page-only odometer script enqueue, a data attribute containing the years value, final-by-default CSS, animation-only CSS, and localStorage reads/writes.

- [ ] **Step 2: Run the test and confirm it fails**

Run: `LD_LIBRARY_PATH=/home/alan/.local/lib/xampp-compat /opt/lampp/bin/php tests/WordPressSetupTest.php`

Expected: one failure for the missing odometer script/localStorage behavior.

### Task 2: Implement Odometer State

**Files:**
- Modify: `wp-content/themes/alanfullbeard/functions.php`
- Modify: `wp-content/themes/alanfullbeard/front-page.php`
- Modify: `wp-content/themes/alanfullbeard/style.css`
- Modify: `wp-content/themes/alanfullbeard/style.min.css`
- Create: `wp-content/themes/alanfullbeard/assets/js/odometer.js`

- [ ] **Step 1: Enqueue the script only on the front page**

Use `wp_enqueue_script()` inside `alanfullbeard_lcars_assets()` with a theme-relative `assets/js/odometer.js` URL, no dependencies, theme version, and footer loading.

- [ ] **Step 2: Add the years value to the odometer element**

Render `data-odometer-years="<?php echo esc_attr((string) $experienceYears); ?>"` on the `.odometer` element.

- [ ] **Step 3: Switch CSS to final by default**

Set `.odometer > div` to the final translate state by default, add `.odometer--animate > div` for the animation, and keep `.odometer--final > div` explicit.

- [ ] **Step 4: Add localStorage logic**

Use a namespaced key that includes the current years value. If storage has the key, apply `.odometer--final`; otherwise apply `.odometer--animate`, set the key on `animationend`, and fall back to final state if storage fails.

- [ ] **Step 5: Regenerate `style.min.css`**

Use the local PHP minifier command already used for this theme, preserving `calc()` spacing.

### Task 3: Verify Runtime Behavior

**Files:**
- No additional files.

- [ ] **Step 1: Run tests and static checks**

Run the WordPress setup test, individual project tests, PHP syntax checks, PHPStan, and `git diff --check`.

- [ ] **Step 2: Browser-render smoke check**

Fetch `http://localhost/alanfullbeard/` and confirm it includes the script, the `data-odometer-years` attribute, and the odometer starts without `.odometer--animate` in server HTML.

# Resume Site Skeleton Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the initial hardcoded PHP resume site skeleton and import the accessible demo form.

**Architecture:** A front controller resolves route config into PHP templates. Shared helpers escape output, render views, and expose security headers for both runtime and tests.

**Tech Stack:** PHP 8.4, Apache/XAMPP rewrites, Composer scripts, PHPStan, plain CSS/JS.

---

### Task 1: Tests And Imported Assets

**Files:**
- Create: `tests/SiteTest.php`
- Copy: old accessible form assets into `public/assets/images/` and `public/assets/images/icons/`

- [ ] Write a CLI test runner that fails until the skeleton exists.
- [ ] Run `php tests/SiteTest.php` and verify it fails because required app files/routes are missing.
- [ ] Copy only required image/icon assets from `/home/alan/Documents/afullbeardsingle/public_html/images`.

### Task 2: PHP Skeleton

**Files:**
- Create: `app/Site.php`
- Create: `app/bootstrap.php`
- Create: `app/content/pages.php`
- Create: `app/views/layout.php`
- Create: `app/views/partials/header.php`
- Create: `app/views/partials/footer.php`
- Create: `app/views/pages/*.php`
- Create: `public/index.php`
- Create: `public/.htaccess`

- [ ] Implement route normalization and lookup.
- [ ] Implement render helpers and escaping.
- [ ] Implement security header definitions.
- [ ] Add home, portfolio, plugins, contact, privacy, and accessible-form pages.

### Task 3: Styles, Scripts, And Tooling

**Files:**
- Create: `public/assets/css/site.css`
- Bring over/adapt: `public/assets/css/forms.css`
- Create: `public/assets/js/site.js`
- Create: `composer.json`
- Create: `phpstan.neon`
- Create: `scripts/phpstan.sh`
- Create: `.gitignore`

- [ ] Add the verified old-site theme foundation and utility classes needed by the imported form.
- [ ] Wire mobile nav behavior.
- [ ] Add Composer scripts for tests and PHPStan.
- [ ] Run `composer install` to create the lock file.

### Task 4: Verification

**Files:**
- Verify all source files

- [ ] Run `php tests/SiteTest.php`.
- [ ] Run `composer phpstan`.
- [ ] Run a PHP built-in server against `public/`.
- [ ] Fetch homepage and accessible-form route locally.
- [ ] Report exact verification results.

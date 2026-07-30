# alanfullbeard.com

WordPress site for `alanfullbeard.com`.

## Local Development

```bash
composer install
composer test
composer phpstan
```

The local XAMPP install serves this project from:

```text
http://localhost/alanfullbeard/
```

The local WordPress database is `alanfullbeard_wp`.

## WordPress

- WordPress core is installed locally at the repository root but is ignored by git.
- The repository tracks WordPress source under `wp-content/`.
- The custom theme lives at `wp-content/themes/alanfullbeard/`.
- Default bundled `twenty*` themes are intentionally removed.
- Local `wp-config.php`, uploads, cache, upgrade scratch files, and core files are ignored by git.

### WordPress.org Dependencies

The following production dependencies are installed and updated separately
rather than vendored in this repository:

- Contact Form 7 6.1.6
- Flamingo 2.6.3
- CF7 AntiSpam 0.7.6

## Source and Privacy Boundary

This is a source-only repository. It contains application code, tests,
documentation, and guarded deployment tooling. It must never contain:

- WordPress core or generated uploads
- production or local database exports
- API, SMTP, Turnstile, database, or WordPress secret values
- encrypted contact-message backups or contact-message content
- private FileHub files
- server rollback, quarantine, staging, or deployment directories

Example configuration may be committed only when it contains placeholders.
Production secrets remain outside the public webroot and outside git.

## Structure

- `index.php`, `wp-admin/`, `wp-includes/`: WordPress core
- `wp-content/themes/alanfullbeard/`: custom LCARS-inspired theme
- `wp-content/plugins/`: site-owned plugins and plugin demonstrations
- `wp-content/mu-plugins/`: security, encrypted contact storage, retention, and FileHub controls
- `deploy/`: guarded audit, activation, rollback, and migration tooling
- `docs/`: deployable site configuration and implementation records
- `tests/WordPressSetupTest.php`: WordPress source/setup checks
- `tests/WaypointsPluginTest.php`: local checks for the installed Waypoints: Trip Planner plugin
- `tests/FileHubTest.php`: administrator-only File Hub routing and filename checks
- `app/`, `public/`, `tests/SiteTest.php`: legacy hardcoded PHP site retained during migration

## Release Checks

```bash
composer test
composer phpstan
npm run test:e2e
```

The live-contact and live security-report tests are opt-in and remain skipped
unless their explicit production environment variables are provided.

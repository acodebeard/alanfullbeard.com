# Shared DigitalOcean Target Candidate

These are offline candidate configurations for migrating the `afullbeard`
HostGator account to the existing DKC Droplet at `165.227.84.103`.

They are not deployment authorization and must not be installed until:

1. A fresh encrypted application backup and full-Droplet snapshot or
   equivalent rollback points exist.
2. The exact DKC pre-change HTTP, service, configuration-hash, disk, and memory
   baselines have been recorded.
3. The candidate package and module installation is reviewed and explicitly
   approved.

## Protected production boundary

The migration must not modify:

- `/var/www/destinationkonacoast.org`
- the `dkc_prod` MariaDB database or its account
- either DKC Apache virtual host
- `/monitoring/`, Grafana, Prometheus, or Node Exporter configuration
- DKC certificates, credentials, or deployment keys
- Apache's current prefork or mod_php configuration

The new sites use a separate Unix account and PHP-FPM socket. DKC remains on
its current mod_php handler until its own separately approved migration.

## New namespaces

- Unix user/group: `afullbeard`
- SSH key fingerprint:
  `SHA256:HD29US3KuEJkwVM24w6X9OBBBm7EWX16EQt49vOiZxQ`
- Web roots: `/srv/www/<domain>/public`
- FileHub data: `/home/afullbeard/filehub_data`
- Shared PHP-FPM socket: `/run/php/php8.3-fpm-afullbeard.sock`
- Alan Fullbeard PHP-FPM socket: `/run/php/php8.3-fpm-alanfullbeard.sock`
- Logs: `/home/afullbeard/logs` and per-site Apache logs
- Temporary PHP data: `/home/afullbeard/tmp`

All migrated sites belong to the same owner and already share one HostGator
account. The shared FPM pool separates them from DKC, while alanfullbeard.com
uses a dedicated pool so its public and FileHub upload limits can be narrowed
without changing Photos by Alan or Red Meat.

## Required packages/modules

The target audit found `php8.3-fpm` not installed and no available
`proxy_fcgi` module configuration. Before any installation, confirm the
package candidate and exactly which Apache package supplies `mod_proxy_fcgi`.

Expected requirements:

- `php8.3-fpm`
- Apache `proxy` and `proxy_fcgi`
- existing `rewrite`, `headers`, `setenvif`, and `ssl`

Do not disable `libapache2-mod-php8.3` or change the active MPM.

## Guarded activation order

1. Create and verify a transaction-consistent encrypted DKC database/webroot
   backup.
2. Create a live Droplet snapshot without powering off DKC. Treat this as a
   system/configuration rollback rather than the authoritative database backup.
3. Record hashes of DKC vhosts and service configuration.
4. Install PHP-FPM and enable the existing FastCGI proxy module without
   enabling new vhosts.
5. Create the `afullbeard` account, home-private storage, logs, temporary
   directories, and empty webroots.
6. Install and syntax-check `afullbeard-fpm.conf`.
7. Start/reload PHP-FPM and verify only the new socket.
8. Install the individual HTTP vhost candidates but leave them disabled.
9. Recheck DKC before copying any site.
10. Copy `viazen.io` as the pilot and enable only its HTTP vhost.
11. Validate with forced resolution; do not change DNS.

TLS and HTTPS redirects are added per domain only after certificate material
is available and the HTTP pilot passes.

## Alan Fullbeard TLS staging

The current HostGator certificate for `alanfullbeard.com` and
`*.alanfullbeard.com` is valid through October 2, 2026. Its matching private
key is available to the account owner. For a no-downtime cutover, transfer
only that exact certificate and key into the root-owned
`/etc/ssl/alanfullbeard.com/` directory, with the directory mode `0700`,
`fullchain.pem` mode `0644`, and `privkey.pem` mode `0600`.

Install `alanfullbeard.com-ssl.conf`, syntax-check Apache, and test HTTPS with
forced resolution before changing public DNS. This imported certificate is a
cutover bridge only. After DNS points at the Droplet, obtain a fresh
Certbot-managed certificate using HTTP validation, replace the imported paths,
verify automated renewal, and only then retire the imported private key.

Do not enable HSTS or the HTTP-to-HTTPS redirect until HTTPS is confirmed
through the final public route.

## Remaining-site TLS staging

The offline SSL vhost candidates for Viazen, Photos by Alan, Red Meat, and
SetlistSites mirror the access controls and isolated FPM routing in their
reviewed HTTP vhosts. Each uses a dedicated root-owned
`/etc/ssl/<domain>/` directory so imported cutover certificates cannot be
mistaken for Certbot-managed certificates.

The current HostGator certificates for Viazen, Photos by Alan, and
SetlistSites cover both the root and `www` names and have matching keys
available to the account owner. They may be used as short-lived cutover
bridges, followed by fresh Certbot-managed certificates after DNS changes.

Red Meat is proxied through Cloudflare and its public edge certificate cannot
be reused at the origin. Before enabling `redmeat.com-ssl.conf`, install either
a Cloudflare Origin CA certificate that matches the configured Cloudflare SSL
mode or a publicly trusted certificate obtained through DNS validation. Do
not switch its Cloudflare origin while the target lacks a certificate
compatible with the active origin-validation mode.

## Photos by Alan payload

The checksum-exact HostGator source copy is retained locally without edits.
The deployment payload uses `photosbyalan-rsync-filter.txt` to omit:

- the older nested `/public_html/` site copy;
- the root `error_log`; and
- historical `/_logs/*.log` files; and
- the source `/includes/app-config.php`, which has `DEV_MODE` enabled.

The protected `/_logs/.htaccess` file and current `blocked-ips.txt` state stay
in the payload. After the filtered copy, install
`photosbyalan-app-config.php` as
`/srv/www/photosbyalan.com/public/includes/app-config.php` so production uses
the correct canonical URL and does not expose exception details through
`DEV_MODE`. The Photos vhost denies direct web access to `/includes/` and
denies alternate PHP-like extensions while continuing to route `.php` through
the isolated FPM socket.

## Red Meat payload

The checksum-exact HostGator source copy is retained locally without edits.
The deployment payload uses `redmeat-rsync-filter.txt` to omit HostGator
configuration, upgrader backups, historical error logs, the unused CLI image
downloader, and the dated RSS backup. Install `redmeat-htaccess.conf` as the
target `.htaccess`; it is source-equivalent except that the obsolete
`suPHP_ConfigPath` directive is absent.

The Red Meat vhost denies direct access to `/data/` and `/tpl/`, denies
alternate PHP-like extensions, and routes only `.php` through the isolated FPM
socket. The application sets its own Phoenix timezone, while the shared FPM
pool enforces hidden display errors and a private PHP error log.

## SetlistSites payload

The checksum-exact HostGator source copy is retained locally without edits,
including its historical PHP error log. The deployment payload uses
`setlistsites-rsync-filter.txt` to omit that log, the credential-bearing root
mailer, and an unused duplicate mailer.

Install `setlistsites-mail-service.php` as the target `MailService.php`. It
requires `MAILERSEND_API_KEY` through the isolated runtime environment and
contains no embedded credential. Apply `setlistsites-contact-security.patch`
to restore strict CSRF validation without logging token values. Apply
`setlistsites-http-status.patch` so the router's calculated status and
`X-Robots-Tag` are emitted before the response body. A fresh MailerSend key
must be installed outside the public webroot and tested before DNS cutover;
the source key should be rotated because it was embedded in the HostGator
code.

The SetlistSites vhost denies direct access to `/logs/` and `/site/`, denies
direct requests for root PHP templates and helpers, denies alternate PHP-like
extensions, and routes the front controller through the isolated FPM socket.

## Alan Fullbeard source payload

The fresh WordPress database export is stored separately with mode `0600`.
Use `alanfullbeard-source-rsync-filter.txt` for the checksum-exact local file
copy containing WordPress, APOD, and FileHub. It omits the separately backed-up
Photos by Alan, SetlistSites, and Red Meat trees, along with the broken demo,
CGI directory, and legacy Red Meat password file.

This source copy may retain HostGator-only configuration for audit and
rollback. A separate target filter and reviewed WordPress configuration are
required before any DigitalOcean transfer.

## Alan Fullbeard target payload

Use `alanfullbeard-target-rsync-filter.txt` for the DigitalOcean payload. It
omits HostGator PHP configuration, the source `wp-config.php`, the legacy
credential-bearing FileHub application, logs, and separately migrated sites.
Install `alanfullbeard-htaccess.conf` as the target `.htaccess`; it preserves
the active routing, caching, and access controls but excludes cPanel handlers,
cPanel session paths, and an HTTP-to-HTTPS redirect before the certificate
exists.

Install `alanfullbeard-wp-config.php` as `wp-config.php`. It loads the database
password and fresh WordPress salts from
`/home/afullbeard/private/wordpress-secrets.json`. The example file documents
the required shape only; real values must be generated directly into the
private mode-0600 target file and must not enter this package.

After a read-only database namespace preflight, run
`provision-wordpress-runtime.php` as root on the verified target. It generates
the private database password and fresh salts without printing them, reuses
the same values on an interrupted rerun, and creates or reconciles only the
fixed `afullbeard_wp` database and `afullbeard_wp@localhost` account. Import
the reviewed database dump separately and verify table counts before enabling
the vhost.

The standalone `/filehub/` application is retired and excluded in full from
the target payload. The root target `.htaccess` returns 404 for the old public
path as defense in depth. The previously staged standalone directory has been
removed manually and is not part of the deployment.

Install `wp-content/mu-plugins/alanfullbeard-filehub.php` from the project as a
must-use plugin. It adds Tools > File Hub for authenticated administrators
only, routes every operation through capability-checked `admin-post.php`
handlers, requires WordPress nonces, and exposes no logged-out or public file
route. The existing `/home/afullbeard/filehub_data` files remain outside the
webroot with mode-0700 directories and mode-0600 files. The dedicated
alanfullbeard.com PHP-FPM pool allows files up to 10 MB and POST bodies up to
12 MB; FileHub enforces the same 10 MB file ceiling.

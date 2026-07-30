# HostGator to DigitalOcean Migration

- Status: source and target audits complete; preparing isolated target configuration
- Audit date: 2026-07-25
- Source account: `afullbeard` on HostGator
- Production changes completed by this plan: none

## Objective

Move the remaining active websites and private application data from the
HostGator account to the existing DigitalOcean droplet at `165.227.84.103`
without disrupting DKC or losing FileHub uploads, WordPress data, DNS records,
or mail.

HostGator remains the rollback target until every migrated site has passed its
stabilization window. DNS and mail are separate workstreams from copying the
websites.

DKC is protected production on the shared target until it is migrated
elsewhere. Its webroot, database, virtual hosts, monitoring configuration,
runtime behavior, and credentials are outside the HostGator migration's write
scope.

## Audited production scope

| Service | HostGator source | Size | Runtime/data notes |
| --- | --- | ---: | --- |
| alanfullbeard.com | `/home2/afullbeard/public_html` | 1.2 GB including child paths | WordPress at the root; Apache rewrites and hardening |
| Alan Fullbeard database | `afullbea_afbwp` | about 5.8 MiB | Only confirmed active MySQL database |
| APOD | `/home2/afullbeard/public_html/apod` | 175 MB | File-based PHP; 12 PHP files; no database dependency detected |
| FileHub application | `/home2/afullbeard/public_html/filehub` | 20 KB | One PHP entrypoint; HTTP authentication response |
| FileHub private data | `/home2/afullbeard/filehub_data` | 478 MB | 68 files in 3 directories; outside the web root |
| Photos by Alan | `/home2/afullbeard/public_html/photosbyalan` | 227 MB | File-based PHP front controller |
| SetlistSites | `/home2/afullbeard/public_html/set-list-sites` | 470 MB | File-based PHP; 12 PHP files; no database dependency detected |
| Red Meat | `/home2/afullbeard/public_html/website_7c2f4efd` | 187 MB | File-based PHP; 13 PHP files; no database dependency detected |
| viazen.io | `/home2/afullbeard/viazen.io/public_html` | 28 KB | Static HTML; PHP execution denied |

The active website and private-data payload is roughly 1.7 GB before a fresh
WordPress database dump, deployment staging, and target-side backups.

All 38 audited custom PHP files passed syntax checks with HostGator's PHP
8.3.32 CLI.

## Excluded from the production migration

These items are not active production document roots and must not be copied
into the new web roots:

- `/home2/afullbeard/apod` — separate older APOD copy
- `/home2/afullbeard/photosbyalan` — separate older Photos by Alan copy
- `demo.setlistsites.com` — mapped document root is missing; public response is
  currently HTTP 500
- `anthonys-demo.viazen.io` — mapped document root is missing; public response
  is currently HTTP 404
- orphaned or retired MySQL databases, including old staging and demo
  databases
- public error logs, debug logs, old `.htaccess` backups, cPanel-generated
  runtime files, and certificate challenge leftovers

The exclusions remain untouched on HostGator until the migration is complete.

## Archive inventory

Archive data is not production data and must not consume the new production
droplet's disk:

| Archive area | Size/contents |
| --- | --- |
| `backups/home-2025-10-18.tar.gz` | 20 GB historical full-home backup |
| `security-quarantine` | 8.8 GB of retired sites and rollback copies |
| `deployments/alanfullbeard-20260724T224500Z` | 637 MB deployment staging |
| `security-quarantine/20260724T220500Z-alanfullbeard-prelaunch` | 1.9 GB prelaunch rollback |

Before HostGator cancellation, retain only the archives the owner wants,
transfer them to separate offline or object storage, record checksums, and
verify the copied archives. Do not place them in a publicly served directory.

## Mail and DNS dependencies

The active DNS zones are currently HostGator-authoritative. Web A records,
mail records, and nameserver migration must not be treated as one operation.

Current mail-related findings:

- Five cPanel mailbox accounts use only a few megabytes of stored mail.
- `privacy@alanfullbeard.com` is a forwarder, not a mailbox.
- The privacy forward currently passes through `alan@viazen.io` before its
  Gmail destination.
- alanfullbeard.com, viazen.io, and setlistsites.com have active mailbox or
  MailerSend dependencies.
- The alanfullbeard.com and setlistsites.com zones contain MailerSend SPF,
  DKIM/CNAME, or tracking records that must be preserved.

Web migration can proceed while MX and explicit `mail` records continue to
point to HostGator. HostGator must not be cancelled until a tested replacement
mail arrangement is in service.

## Selected DigitalOcean target

Use the existing DKC droplet:

- SSH alias: `dkc-final`
- Public IPv4: `165.227.84.103`
- Hostname: `ubuntu-s-2vcpu-4gb-nyc1`
- Ubuntu 24.04 LTS
- 2 vCPU, 4 GiB RAM, 80 GB SSD
- Apache 2.4 with prefork and DKC running under mod_php
- PHP 8.3.6
- MariaDB 10.11.14
- Grafana, Prometheus, and Node Exporter bound to localhost

Target audit results on 2026-07-25:

- 71 GB disk available and 98% of inodes available
- 3.0 GiB memory available at low load
- no swap configured
- DKC webroot uses 436 MB
- MariaDB, Grafana, and Prometheus data use about 508 MB combined
- only `dkc_prod` exists as an application database, using 5.4 MiB
- the `afullbeard` user/group and `/srv/www` and `/srv/private` namespaces are
  currently unused

The audited HostGator production payload is roughly 1.7 GB, so disk and memory
capacity are sufficient. Before migration, create a small swapfile and
isolated PHP-FPM pools for the new sites. Do not convert DKC away from mod_php
as part of this migration.

The project-specific SSH key was generated locally on 2026-07-25:

- Private key: `/home/alan/.ssh/afullbeard_web_prod_2026_07_25`
- Public key: `/home/alan/.ssh/afullbeard_web_prod_2026_07_25.pub`
- Fingerprint:
  `SHA256:HD29US3KuEJkwVM24w6X9OBBBm7EWX16EQt49vOiZxQ`

## Web-server compatibility

Apache is preferred over Nginx for this migration because the active sites
depend on Apache rewrite, redirect, header, expiration, compression, access
control, and file-matching directives.

Do not copy cPanel-specific directives blindly:

- replace the cPanel PHP handler with PHP-FPM configuration;
- move `php_flag` and `php_value` settings into PHP-FPM or per-site INI
  configuration;
- remove Red Meat's `suPHP_ConfigPath` after recreating its required PHP
  settings;
- preserve WordPress authorization forwarding;
- preserve APOD's pretty routes and private internal paths;
- preserve Red Meat's legacy redirects, traversal guards, and direct-PHP
  execution blocks;
- preserve FileHub's data outside the web root;
- continue denying executable uploads and sensitive file types.

## Proposed target layout

```text
/srv/www/alanfullbeard.com/public
/srv/www/photosbyalan.com/public
/srv/www/setlistsites.com/public
/srv/www/redmeat.com/public
/srv/www/viazen.io/public
/home/afullbeard/filehub_data
```

The alanfullbeard.com document root contains the WordPress site plus its APOD
and FileHub application paths. `/home/afullbeard/filehub_data` preserves the
application's existing `$HOME/filehub_data` contract and must be readable and
writable only by the isolated `afullbeard` PHP-FPM identity and administrators.

## Migration order

1. Create a fresh snapshot or equivalent full rollback point for the shared
   Droplet.
2. Add swap and create only new users, virtual hosts, PHP-FPM pools, MariaDB
   account, private FileHub storage, monitoring, and backups.
3. Confirm DKC remains unchanged and healthy before copying any site.
4. Copy and validate viazen.io as the smallest pilot without changing DNS.
5. Copy and validate Photos by Alan.
6. Copy and validate Red Meat.
7. Copy and validate SetlistSites.
8. Copy and validate alanfullbeard.com, APOD, WordPress, and the first FileHub
   data pass.
9. Lower only the required web-record TTLs.
10. Cut over one site's web records at a time while leaving mail records
   unchanged.
11. Freeze FileHub writes briefly, run its final delta sync, verify its file
    count and hashes, and then cut over alanfullbeard.com.
12. Migrate and test mail separately.
13. Move authoritative DNS only after web and mail are stable.
14. Keep HostGator intact through the stabilization window.
15. Export selected archives, verify them, then obtain separate approval
    before cancelling or deleting anything on HostGator.

## Post-migration task

- [ ] Run a full security audit of the shared DigitalOcean Droplet after the
  HostGator-to-DigitalOcean migration, DNS and mail cutovers, FileHub final
  delta, and stabilization window are complete.

Audit scope:

- DigitalOcean account, Droplet, network, snapshot, and backup settings;
- operating-system updates, repositories, packages, users, groups, SSH keys,
  authentication policy, privilege boundaries, listening ports, and firewall;
- enabled services, systemd units, timers, cron jobs, persistence mechanisms,
  and unexpected processes;
- Apache modules, virtual hosts, redirects, TLS, headers, logs, directory
  exposure, and per-site isolation;
- PHP-FPM pools, PHP configuration, filesystem ownership, writable paths,
  session/upload storage, and secret handling;
- MariaDB listeners, accounts, grants, databases, backups, and restore tests;
- application credentials, environment files, deployment keys, mail/API
  secrets, and rotation requirements;
- monitoring, alerting, log retention, intrusion prevention, malware and file
  integrity checks, and recovery readiness;
- explicit DKC regression and protected-boundary verification.

Start with a read-only evidence pass. Review findings and rollback points
before applying remediations, then apply changes one at a time with separately
scoped approval.

## Per-site validation

Before DNS changes:

- compare source and target file manifests;
- lint PHP on the target;
- test through a local host override or equivalent forced resolution;
- verify canonical redirects and representative routes;
- verify static caching and security headers;
- verify private/internal files are denied;
- verify public assets and sitemaps;
- review target error and access logs.

Additional alanfullbeard.com checks:

- compare WordPress database table counts;
- run WordPress core checksum verification;
- verify active plugins, theme, uploads, contact form, Flamingo storage,
  retention scheduling, MailerSend configuration, and Turnstile;
- verify WordPress emoji assets remain disabled;
- verify `/filehub_data/` is not publicly reachable.

Additional FileHub checks:

- preserve the authentication behavior;
- preserve all 68 current files and 3 directories;
- perform the initial copy while live;
- briefly disable writes;
- run the final delta copy;
- compare counts, total bytes, and cryptographic hashes without publishing
  private filenames;
- re-enable writes only after the DigitalOcean path passes an upload/download
  test.

## Rollback

For every site:

1. Keep the HostGator source unchanged.
2. Create a timestamped target backup before each apply.
3. Lower the relevant web-record TTL in advance.
4. Record the old and new DNS values.
5. If validation fails, restore the old web record and leave mail records
   unchanged.
6. Confirm public resolution and HTTP behavior after rollback.

HostGator cancellation is outside the migration apply and requires a separate
decision after the stabilization and archive-retention checks.

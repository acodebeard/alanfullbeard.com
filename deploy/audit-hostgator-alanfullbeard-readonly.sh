#!/usr/bin/env bash

# Read-only security audit for the currently public HostGator WordPress site.
# This script avoids printing credentials, contact-message content, private
# FileHub filenames, database rows, or access-log entries.

set -u

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_UPLOAD_ROOT="${AFB_WEB_ROOT}/wp-content/uploads"
readonly AFB_FILEHUB_ROOT="${AFB_ACCOUNT_ROOT}/filehub_data"

section() {
    printf '\n===== %s =====\n' "$1"
}

section "Host and account identity"
hostname
uname -a
id
uptime
df -h "$AFB_ACCOUNT_ROOT"
df -i "$AFB_ACCOUNT_ROOT"
getent passwd "$(id -un)"

section "SSH authentication boundary"
stat -c '%A %U:%G %n' \
    "$AFB_ACCOUNT_ROOT" \
    "$AFB_ACCOUNT_ROOT/.ssh" \
    "$AFB_ACCOUNT_ROOT/.ssh/authorized_keys" 2>/dev/null
ssh-keygen -lf "$AFB_ACCOUNT_ROOT/.ssh/authorized_keys" 2>/dev/null

section "Scheduled account jobs"
crontab -l

section "PHP runtime and configured request limits"
php -v
php --ini
grep -nE \
    'php_value (post_max_size|upload_max_filesize|memory_limit|max_execution_time|max_input_time|max_input_vars)|php_flag (display_errors|log_errors)' \
    "$AFB_WEB_ROOT/.htaccess"

section "WordPress runtime and core integrity"
cd "$AFB_WEB_ROOT" || exit 1
if command -v wp >/dev/null 2>&1; then
wp core version
wp core verify-checksums
wp db check

section "WordPress plugins and themes"
wp plugin list \
    --fields=name,status,version,update,update_version,auto_update \
    --format=table
wp theme list \
    --fields=name,status,version,update,update_version,auto_update \
    --format=table
wp plugin verify-checksums --all --strict

section "WordPress accounts and public configuration"
wp user list \
    --fields=ID,user_login,roles,user_registered \
    --format=table
for afb_option in home siteurl users_can_register default_role permalink_structure; do
    printf '%s=' "$afb_option"
    wp option get "$afb_option"
done
wp eval 'printf(
    "upload_max_filesize=%s\npost_max_size=%s\nmemory_limit=%s\nmax_execution_time=%s\n",
    ini_get("upload_max_filesize"),
    ini_get("post_max_size"),
    ini_get("memory_limit"),
    ini_get("max_execution_time")
);'
grep -nE \
    "DISALLOW_FILE_EDIT|DISALLOW_FILE_MODS|FORCE_SSL_ADMIN|WP_DEBUG|WP_DEBUG_LOG|WP_DEBUG_DISPLAY|AUTOMATIC_UPDATER_DISABLED|WP_AUTO_UPDATE_CORE" \
    wp-config.php
stat -c '%A %U:%G %s %n' \
    wp-config.php \
    .htaccess \
    wp-content \
    wp-content/plugins \
    wp-content/themes \
    wp-content/uploads

section "Database metadata without application content"
wp db query "
SELECT VERSION();
SHOW VARIABLES WHERE Variable_name IN (
  'bind_address',
  'local_infile',
  'secure_file_priv',
  'skip_name_resolve',
  'max_connections'
);
SELECT TABLE_SCHEMA, COALESCE(ENGINE, 'NONE'), COUNT(*)
FROM information_schema.tables
WHERE TABLE_SCHEMA = DATABASE()
GROUP BY TABLE_SCHEMA, ENGINE
ORDER BY ENGINE;
" --skip-column-names

section "Contact form, storage, and retention"
wp plugin list \
    --fields=name,status,version,update \
    --format=csv |
    grep -Ei '^(name|contact-form-7|flamingo|cf7-antispam|mailersend)'
wp option list \
    --search='*turnstile*' \
    --fields=option_name,autoload \
    --format=table
wp option list \
    --search='*mailersend*' \
    --fields=option_name,autoload \
    --format=table
wp cron event list \
    --fields=hook,next_run_gmt,recurrence \
    --format=csv |
    grep -Ei '^(hook|alanfullbeard|flamingo|wp_version_check|wp_update_plugins|wp_update_themes)'
printf 'flamingo_inbound_count='
wp post list \
    --post_type=flamingo_inbound \
    --post_status=any \
    --format=count
printf 'flamingo_contact_count='
wp post list \
    --post_type=flamingo_contact \
    --post_status=any \
    --format=count
else
    printf 'wp-cli: unavailable; WordPress, database, and contact-form CLI checks skipped\n'
fi

section "Webroot and upload execution boundary"
stat -c '%A %U:%G %s %n' \
    "$AFB_WEB_ROOT" \
    "$AFB_WEB_ROOT/.htaccess" \
    "$AFB_UPLOAD_ROOT" \
    "$AFB_UPLOAD_ROOT/.htaccess" 2>/dev/null
du -sh "$AFB_WEB_ROOT" "$AFB_UPLOAD_ROOT"
shopt -s globstar nullglob nocaseglob
afb_upload_executables=(
    "$AFB_UPLOAD_ROOT"/**/*.php
    "$AFB_UPLOAD_ROOT"/**/*.php[0-9]
    "$AFB_UPLOAD_ROOT"/**/*.phtml
    "$AFB_UPLOAD_ROOT"/**/*.phar
    "$AFB_UPLOAD_ROOT"/**/*.phps
)
printf 'uploads_php_like_count=%s\n' "${#afb_upload_executables[@]}"
if (( ${#afb_upload_executables[@]} > 0 )); then
    printf '%s\n' "${afb_upload_executables[@]}"
fi

section "Sensitive-path access controls"
grep -nE \
    'xmlrpc|wp-config|readme|license|filehub|FilesMatch|Require all denied|RewriteRule|Options -Indexes|SetHandler' \
    "$AFB_WEB_ROOT/.htaccess"
grep -nE \
    'FilesMatch|Require all denied|Options -Indexes|php|phtml|phar' \
    "$AFB_UPLOAD_ROOT/.htaccess" 2>/dev/null

section "Private FileHub boundary"
stat -c '%A %U:%G %s %n' \
    "$AFB_FILEHUB_ROOT" \
    "$AFB_FILEHUB_ROOT/storage" \
    "$AFB_FILEHUB_ROOT/favorites" 2>/dev/null
du -sh "$AFB_FILEHUB_ROOT"
if command -v rg >/dev/null 2>&1; then
    printf 'filehub_file_count='
    rg --files "$AFB_FILEHUB_ROOT" | wc -l
fi

section "Recent application errors"
grep -Ei \
    'fatal|warning|parse error|permission denied|undefined|uncaught' \
    "$AFB_WEB_ROOT/error_log" 2>/dev/null |
    tail -n 160

section "Rollback and quarantine inventory"
ls -ld \
    "$AFB_ACCOUNT_ROOT"/security-quarantine \
    "$AFB_ACCOUNT_ROOT"/security-quarantine/* \
    "$AFB_ACCOUNT_ROOT"/deployments \
    "$AFB_ACCOUNT_ROOT"/deployments/* 2>/dev/null

section "Audit complete"
printf 'read-only HostGator audit completed\n'

#!/usr/bin/env bash

# Read-only audit of the HostGator PHP and WordPress upload-size configuration.
# It does not create probes, upload files, or print WordPress credentials.

set -u

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"

section() {
    printf '\n===== %s =====\n' "$1"
}

section "Target identity"
hostname
id
printf 'web_root=%s\n' "$AFB_WEB_ROOT"

section "Live htaccess handler and PHP limits"
sha256sum "${AFB_WEB_ROOT}/.htaccess"
grep -nE \
    'AddHandler|SetHandler|<IfModule|php_value (post_max_size|upload_max_filesize)' \
    "${AFB_WEB_ROOT}/.htaccess"

section "Per-directory PHP configuration candidates"
for afb_config in \
    "${AFB_ACCOUNT_ROOT}/.user.ini" \
    "${AFB_ACCOUNT_ROOT}/php.ini" \
    "${AFB_WEB_ROOT}/.user.ini" \
    "${AFB_WEB_ROOT}/php.ini"; do
    if [[ -f "$afb_config" ]]; then
        stat -c '%A %U:%G %s %n' "$afb_config"
        grep -nE \
            '^[[:space:]]*(upload_max_filesize|post_max_size|memory_limit)[[:space:]]*=' \
            "$afb_config" || true
    else
        printf 'missing %s\n' "$afb_config"
    fi
done

section "CLI PHP runtime"
command -v php
php --ini
php -i |
    grep -E \
        '^(Server API|Loaded Configuration File|Scan this dir for additional .ini files|user_ini.filename|upload_max_filesize|post_max_size|memory_limit)'

section "WordPress CLI calculation"
cd "$AFB_WEB_ROOT" || exit 1
wp core version
wp eval '
printf(
    "php_sapi=%s\nupload_max_filesize=%s\npost_max_size=%s\nwp_max_upload_size_bytes=%d\nwp_max_upload_size_display=%s\n",
    PHP_SAPI,
    (string) ini_get("upload_max_filesize"),
    (string) ini_get("post_max_size"),
    (int) wp_max_upload_size(),
    size_format((int) wp_max_upload_size())
);
'

section "Audit complete"
printf 'No files or database values were changed.\n'

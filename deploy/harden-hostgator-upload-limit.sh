#!/usr/bin/env bash

# Applies the alanfullbeard.com PHP upload ceiling on the current HostGator site.
# This script is intentionally hash-guarded against the audited live .htaccess
# and creates a private rollback copy before replacing any values.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_HTACCESS="${AFB_WEB_ROOT}/.htaccess"
readonly AFB_EXPECTED_SHA256="3d786c32f781965e18fd6c83d30a536c948df016b02f5e1861ff84a22abecf8d"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups"
readonly AFB_BACKUP_DIR="${AFB_BACKUP_ROOT}/upload-limit-20260728"
readonly AFB_BACKUP_FILE="${AFB_BACKUP_DIR}/public_html.htaccess.before"
readonly AFB_CANDIDATE_FILE="${AFB_BACKUP_DIR}/public_html.htaccess.candidate"

current_hash="$(sha256sum "$AFB_HTACCESS" | cut -d ' ' -f 1)"
if [[ "$current_hash" != "$AFB_EXPECTED_SHA256" ]]; then
    printf 'Refusing change: live .htaccess hash is %s, expected %s\n' \
        "$current_hash" \
        "$AFB_EXPECTED_SHA256" >&2
    exit 1
fi

if [[ -e "$AFB_BACKUP_DIR" ]]; then
    printf 'Refusing change: rollback directory already exists: %s\n' \
        "$AFB_BACKUP_DIR" >&2
    exit 1
fi

umask 077
mkdir -p "$AFB_BACKUP_DIR"
cp -p "$AFB_HTACCESS" "$AFB_BACKUP_FILE"

post_count="$(grep -c '^[[:space:]]*php_value post_max_size 516M[[:space:]]*$' "$AFB_HTACCESS")"
upload_count="$(grep -c '^[[:space:]]*php_value upload_max_filesize 512M[[:space:]]*$' "$AFB_HTACCESS")"
if [[ "$post_count" != "2" || "$upload_count" != "2" ]]; then
    printf 'Refusing change: expected two post and two upload directives; found %s and %s\n' \
        "$post_count" \
        "$upload_count" >&2
    exit 1
fi

sed \
    -e 's/^\([[:space:]]*php_value post_max_size \)516M[[:space:]]*$/\112M/' \
    -e 's/^\([[:space:]]*php_value upload_max_filesize \)512M[[:space:]]*$/\110M/' \
    "$AFB_HTACCESS" > "$AFB_CANDIDATE_FILE"

chmod --reference="$AFB_HTACCESS" "$AFB_CANDIDATE_FILE"

candidate_post_count="$(grep -c '^[[:space:]]*php_value post_max_size 12M[[:space:]]*$' "$AFB_CANDIDATE_FILE")"
candidate_upload_count="$(grep -c '^[[:space:]]*php_value upload_max_filesize 10M[[:space:]]*$' "$AFB_CANDIDATE_FILE")"
if [[ "$candidate_post_count" != "2" || "$candidate_upload_count" != "2" ]]; then
    printf 'Refusing change: candidate validation failed\n' >&2
    exit 1
fi

mv "$AFB_CANDIDATE_FILE" "$AFB_HTACCESS"

printf 'Applied HostGator upload ceiling.\n'
printf 'Rollback copy: %s\n' "$AFB_BACKUP_FILE"
sha256sum "$AFB_HTACCESS" "$AFB_BACKUP_FILE"
grep -nE \
    'php_value (post_max_size|upload_max_filesize)' \
    "$AFB_HTACCESS"

#!/usr/bin/env bash

# Restores the pre-rollout .htaccess and moves the public security-header code
# out of the WordPress tree. Run only with separate rollback approval.

set -euo pipefail
umask 077

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_MU_ROOT="${AFB_WEB_ROOT}/wp-content/mu-plugins"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/public-security-headers-20260729"
readonly AFB_ROLLBACK_ROOT="${AFB_BACKUP_ROOT}/manual-rollback"
readonly AFB_HTACCESS="${AFB_WEB_ROOT}/.htaccess"
readonly AFB_HEADER_PLUGIN="${AFB_MU_ROOT}/alanfullbeard-security-headers.php"
readonly AFB_ASSET_ROOT="${AFB_MU_ROOT}/alanfullbeard-security-assets"
readonly AFB_REPORT_ONLY_OVERRIDE="${AFB_MU_ROOT}/000-alanfullbeard-security-report-only.php"
readonly AFB_DISABLED_OVERRIDE="${AFB_BACKUP_ROOT}/000-alanfullbeard-security-report-only.php.disabled"
readonly AFB_HTACCESS_BACKUP="${AFB_BACKUP_ROOT}/public_html.htaccess.before"

if [[ ! -f "$AFB_HTACCESS_BACKUP" ]]; then
    printf 'Refusing rollback: pre-rollout .htaccess backup is missing\n' >&2
    exit 1
fi

if [[ -e "$AFB_ROLLBACK_ROOT" ]]; then
    printf 'Refusing rollback: rollback target already exists\n' >&2
    exit 1
fi

afb_expected_htaccess_hash="$(
    sed \
        's/^  Header always set Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"$/  Header always setifempty Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"/' \
        "$AFB_HTACCESS_BACKUP" |
    sha256sum |
    cut -d ' ' -f 1
)"
afb_live_htaccess_hash="$(sha256sum "$AFB_HTACCESS" | cut -d ' ' -f 1)"
if [[ "$afb_live_htaccess_hash" != "$afb_expected_htaccess_hash" ]]; then
    printf 'Refusing rollback: live .htaccess changed after the rollout\n' >&2
    exit 1
fi

mkdir "$AFB_ROLLBACK_ROOT"
chmod 0700 "$AFB_ROLLBACK_ROOT"

cp -p "$AFB_HTACCESS" "$AFB_ROLLBACK_ROOT/public_html.htaccess.rolled-back"
cp -p "$AFB_HTACCESS_BACKUP" "$AFB_HTACCESS"

if [[ -f "$AFB_REPORT_ONLY_OVERRIDE" ]]; then
    mv "$AFB_REPORT_ONLY_OVERRIDE" "$AFB_ROLLBACK_ROOT/"
fi
if [[ -f "$AFB_DISABLED_OVERRIDE" ]]; then
    mv "$AFB_DISABLED_OVERRIDE" "$AFB_ROLLBACK_ROOT/"
fi
if [[ -f "$AFB_HEADER_PLUGIN" ]]; then
    mv "$AFB_HEADER_PLUGIN" "$AFB_ROLLBACK_ROOT/"
fi
if [[ -d "$AFB_ASSET_ROOT" ]]; then
    mv "$AFB_ASSET_ROOT" "$AFB_ROLLBACK_ROOT/"
fi

for afb_url in \
    "https://alanfullbeard.com/" \
    "https://alanfullbeard.com/contact/" \
    "https://alanfullbeard.com/privacy-policy/"; do
    afb_headers="$(
        curl \
            --fail \
            --silent \
            --show-error \
            --location \
            --max-time 20 \
            --dump-header - \
            --output /dev/null \
            "$afb_url" |
        tr -d '\r'
    )"

    printf '%s\n' "$afb_headers" |
        grep -Eqi '^content-security-policy:.*upgrade-insecure-requests'

    if printf '%s\n' "$afb_headers" |
        grep -Eqi \
            '^(content-security-policy-report-only:|cross-origin-opener-policy:)'; then
        printf 'Rollback header verification failed for %s\n' "$afb_url" >&2
        exit 1
    fi

    printf 'rollback_headers_verified=%s\n' "$afb_url"
done

printf 'Rolled back the public security-header rollout.\n'
printf 'Rolled-back files retained at: %s\n' "$AFB_ROLLBACK_ROOT"

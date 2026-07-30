#!/usr/bin/env bash

# Switches the already-validated public CSP from report-only to enforcement by
# moving the temporary override out of the WordPress mu-plugins directory.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_MU_ROOT="${AFB_WEB_ROOT}/wp-content/mu-plugins"
readonly AFB_STAGE_ROOT="/home2/afullbeard/security-staging/public-security-headers-20260729"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/public-security-headers-20260729"
readonly AFB_HTACCESS="${AFB_WEB_ROOT}/.htaccess"
readonly AFB_HTACCESS_BACKUP="${AFB_BACKUP_ROOT}/public_html.htaccess.before"
readonly AFB_HEADER_PLUGIN="${AFB_MU_ROOT}/alanfullbeard-security-headers.php"
readonly AFB_ASSET_ROOT="${AFB_MU_ROOT}/alanfullbeard-security-assets"
readonly AFB_REPORT_ONLY_OVERRIDE="${AFB_MU_ROOT}/000-alanfullbeard-security-report-only.php"
readonly AFB_DISABLED_OVERRIDE="${AFB_BACKUP_ROOT}/000-alanfullbeard-security-report-only.php.disabled"

if [[ ! -d "$AFB_BACKUP_ROOT" ]]; then
    printf 'Refusing enforcement: rollback directory is missing\n' >&2
    exit 1
fi

if [[ ! -f "$AFB_REPORT_ONLY_OVERRIDE" ]]; then
    printf 'Refusing enforcement: report-only override is missing\n' >&2
    exit 1
fi

if [[ -e "$AFB_DISABLED_OVERRIDE" ]]; then
    printf 'Refusing enforcement: disabled override target already exists\n' >&2
    exit 1
fi

cd "$AFB_STAGE_ROOT"
sha256sum -c public-security-headers-SHA256SUMS

for afb_path in \
    "$AFB_HEADER_PLUGIN" \
    "$AFB_ASSET_ROOT/purify.min.js" \
    "$AFB_ASSET_ROOT/trusted-types-policy.js" \
    "$AFB_REPORT_ONLY_OVERRIDE"; do
    if [[ ! -f "$afb_path" ]]; then
        printf 'Refusing enforcement: expected live file is missing: %s\n' \
            "$afb_path" >&2
        exit 1
    fi
done

cmp -s \
    "$AFB_STAGE_ROOT/alanfullbeard-security-headers.php" \
    "$AFB_HEADER_PLUGIN"
cmp -s \
    "$AFB_STAGE_ROOT/alanfullbeard-security-assets/purify.min.js" \
    "$AFB_ASSET_ROOT/purify.min.js"
cmp -s \
    "$AFB_STAGE_ROOT/alanfullbeard-security-assets/trusted-types-policy.js" \
    "$AFB_ASSET_ROOT/trusted-types-policy.js"
cmp -s \
    "$AFB_STAGE_ROOT/000-alanfullbeard-security-report-only.php" \
    "$AFB_REPORT_ONLY_OVERRIDE"

afb_expected_htaccess_hash="$(
    sed \
        's/^  Header always set Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"$/  Header always setifempty Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"/' \
        "$AFB_HTACCESS_BACKUP" |
    sha256sum |
    cut -d ' ' -f 1
)"
afb_live_htaccess_hash="$(sha256sum "$AFB_HTACCESS" | cut -d ' ' -f 1)"
if [[ "$afb_live_htaccess_hash" != "$afb_expected_htaccess_hash" ]]; then
    printf 'Refusing enforcement: live .htaccess changed after report-only activation\n' >&2
    exit 1
fi

rollback_needed=1
rollback() {
    if [[ "$rollback_needed" == "1" && -f "$AFB_DISABLED_OVERRIDE" ]]; then
        mv "$AFB_DISABLED_OVERRIDE" "$AFB_REPORT_ONLY_OVERRIDE"
    fi
}
trap rollback EXIT

mv "$AFB_REPORT_ONLY_OVERRIDE" "$AFB_DISABLED_OVERRIDE"

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
        grep -Eqi '^cross-origin-opener-policy:[[:space:]]*same-origin$'
    printf '%s\n' "$afb_headers" |
        grep -Eqi '^x-frame-options:[[:space:]]*SAMEORIGIN$'
    printf '%s\n' "$afb_headers" |
        grep -Eqi '^content-security-policy:.*require-trusted-types-for'

    if printf '%s\n' "$afb_headers" |
        grep -Eqi '^content-security-policy-report-only:'; then
        printf 'Unexpected report-only header remains for %s\n' "$afb_url" >&2
        exit 1
    fi

    printf 'enforced_headers_verified=%s\n' "$afb_url"
done

rollback_needed=0
trap - EXIT

printf 'Public security headers are now enforced.\n'
printf 'Disabled report-only override: %s\n' "$AFB_DISABLED_OVERRIDE"

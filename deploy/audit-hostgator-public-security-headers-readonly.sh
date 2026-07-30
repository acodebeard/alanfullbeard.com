#!/usr/bin/env bash

# Read-only preflight for the alanfullbeard.com public security-header rollout.
# It does not print WordPress content, form submissions, credentials, or keys.

set -u

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_MU_ROOT="${AFB_WEB_ROOT}/wp-content/mu-plugins"
readonly AFB_HEADER_PLUGIN="${AFB_MU_ROOT}/alanfullbeard-security-headers.php"
readonly AFB_ASSET_ROOT="${AFB_MU_ROOT}/alanfullbeard-security-assets"
readonly AFB_REPORT_ONLY_OVERRIDE="${AFB_MU_ROOT}/000-alanfullbeard-security-report-only.php"

printf '===== Runtime =====\n'
php -v | head -n 1
(
    cd "$AFB_WEB_ROOT" &&
    wp core version
)

printf '\n===== Relevant live files =====\n'
for afb_path in \
    "$AFB_WEB_ROOT/.htaccess" \
    "$AFB_HEADER_PLUGIN" \
    "$AFB_ASSET_ROOT/purify.min.js" \
    "$AFB_ASSET_ROOT/trusted-types-policy.js" \
    "$AFB_REPORT_ONLY_OVERRIDE"; do
    if [[ -e "$afb_path" ]]; then
        stat -c '%A %U:%G %s %n' "$afb_path"
        sha256sum "$afb_path"
    else
        printf 'missing %s\n' "$afb_path"
    fi
done

printf '\n===== Existing header directives =====\n'
grep -nE \
    'Strict-Transport-Security|Content-Security-Policy|Cross-Origin-Opener-Policy|X-Frame-Options|frame-ancestors|Trusted-Types|trusted-types' \
    "$AFB_WEB_ROOT/.htaccess" \
    "$AFB_MU_ROOT"/*.php 2>/dev/null || true

printf '\n===== Active standard plugins =====\n'
(
    cd "$AFB_WEB_ROOT" &&
    wp plugin list \
        --status=active \
        --fields=name,status,version \
        --format=table
)

printf '\n===== Public response headers =====\n'
for afb_url in \
    "https://alanfullbeard.com/" \
    "https://alanfullbeard.com/contact/" \
    "https://alanfullbeard.com/privacy-policy/"; do
    printf '%s\n' "$afb_url"
    curl \
        --fail \
        --silent \
        --show-error \
        --location \
        --max-time 20 \
        --dump-header - \
        --output /dev/null \
        "$afb_url" |
    tr -d '\r' |
    grep -Ei \
        '^(HTTP/|content-security-policy:|content-security-policy-report-only:|cross-origin-opener-policy:|x-frame-options:|x-content-type-options:|referrer-policy:|strict-transport-security:)'
done

printf '\nread-only public security-header audit completed\n'

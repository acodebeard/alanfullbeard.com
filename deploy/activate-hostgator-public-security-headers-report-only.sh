#!/usr/bin/env bash

# Activates the reviewed public security headers in report-only CSP mode.
# Existing .htaccess is hash-guarded and backed up. New code is moved out of
# service and .htaccess restored automatically if verification fails.

set -euo pipefail
umask 077

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_MU_ROOT="${AFB_WEB_ROOT}/wp-content/mu-plugins"
readonly AFB_STAGE_ROOT="/home2/afullbeard/security-staging/public-security-headers-20260729"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/public-security-headers-20260729"
readonly AFB_HTACCESS="${AFB_WEB_ROOT}/.htaccess"
readonly AFB_HEADER_PLUGIN="${AFB_MU_ROOT}/alanfullbeard-security-headers.php"
readonly AFB_ASSET_ROOT="${AFB_MU_ROOT}/alanfullbeard-security-assets"
readonly AFB_REPORT_ONLY_OVERRIDE="${AFB_MU_ROOT}/000-alanfullbeard-security-report-only.php"
readonly AFB_EXPECTED_LIVE_HTACCESS_SHA256="1145d2196ec98db791d33751d7d8783eac1c5de3c869f9f54519dd891847ecba"
readonly AFB_OLD_CSP_LINE='  Header always set Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"'
readonly AFB_NEW_CSP_LINE='  Header always setifempty Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"'

for afb_path in \
    "$AFB_HEADER_PLUGIN" \
    "$AFB_ASSET_ROOT" \
    "$AFB_REPORT_ONLY_OVERRIDE" \
    "$AFB_BACKUP_ROOT"; do
    if [[ -e "$afb_path" ]]; then
        printf 'Refusing activation: target already exists: %s\n' "$afb_path" >&2
        exit 1
    fi
done

cd "$AFB_STAGE_ROOT"
sha256sum -c public-security-headers-SHA256SUMS
php -l "$AFB_STAGE_ROOT/alanfullbeard-security-headers.php" >/dev/null
php -l "$AFB_STAGE_ROOT/000-alanfullbeard-security-report-only.php" >/dev/null
grep -Fq 'DOMPurify 3.4.12' \
    "$AFB_STAGE_ROOT/alanfullbeard-security-assets/purify.min.js"

afb_live_hash="$(sha256sum "$AFB_HTACCESS" | cut -d ' ' -f 1)"
if [[ "$afb_live_hash" != "$AFB_EXPECTED_LIVE_HTACCESS_SHA256" ]]; then
    printf 'Refusing activation: live .htaccess hash is %s, expected %s\n' \
        "$afb_live_hash" \
        "$AFB_EXPECTED_LIVE_HTACCESS_SHA256" >&2
    exit 1
fi

if [[ "$(grep -Fxc "$AFB_OLD_CSP_LINE" "$AFB_HTACCESS")" != "1" ]]; then
    printf 'Refusing activation: expected exactly one current CSP fallback line\n' >&2
    exit 1
fi

if [[ "$(grep -Fxc "$AFB_NEW_CSP_LINE" "$AFB_HTACCESS")" != "0" ]]; then
    printf 'Refusing activation: candidate CSP fallback already exists\n' >&2
    exit 1
fi

mkdir -p "$AFB_BACKUP_ROOT"
cp -p "$AFB_HTACCESS" "$AFB_BACKUP_ROOT/public_html.htaccess.before"

sed \
    's/^  Header always set Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"$/  Header always setifempty Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"/' \
    "$AFB_HTACCESS" > "$AFB_BACKUP_ROOT/public_html.htaccess.candidate"
chmod --reference="$AFB_HTACCESS" \
    "$AFB_BACKUP_ROOT/public_html.htaccess.candidate"

if [[ "$(grep -Fxc "$AFB_NEW_CSP_LINE" "$AFB_BACKUP_ROOT/public_html.htaccess.candidate")" != "1" ]]; then
    printf 'Refusing activation: generated .htaccess candidate failed validation\n' >&2
    exit 1
fi

cp "$AFB_STAGE_ROOT/alanfullbeard-security-headers.php" \
    "$AFB_BACKUP_ROOT/alanfullbeard-security-headers.php.candidate"
cp "$AFB_STAGE_ROOT/000-alanfullbeard-security-report-only.php" \
    "$AFB_BACKUP_ROOT/000-alanfullbeard-security-report-only.php.candidate"
mkdir "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.candidate"
cp "$AFB_STAGE_ROOT/alanfullbeard-security-assets/purify.min.js" \
    "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.candidate/purify.min.js"
cp "$AFB_STAGE_ROOT/alanfullbeard-security-assets/trusted-types-policy.js" \
    "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.candidate/trusted-types-policy.js"
chmod 0644 \
    "$AFB_BACKUP_ROOT/alanfullbeard-security-headers.php.candidate" \
    "$AFB_BACKUP_ROOT/000-alanfullbeard-security-report-only.php.candidate" \
    "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.candidate/purify.min.js" \
    "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.candidate/trusted-types-policy.js"
chmod 0755 "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.candidate"

rollback_needed=1
rollback() {
    if [[ "$rollback_needed" != "1" ]]; then
        return
    fi

    if [[ -e "$AFB_HTACCESS" ]]; then
        mv "$AFB_HTACCESS" "$AFB_BACKUP_ROOT/public_html.htaccess.failed"
    fi
    cp -p "$AFB_BACKUP_ROOT/public_html.htaccess.before" "$AFB_HTACCESS"

    if [[ -e "$AFB_REPORT_ONLY_OVERRIDE" ]]; then
        mv "$AFB_REPORT_ONLY_OVERRIDE" \
            "$AFB_BACKUP_ROOT/000-alanfullbeard-security-report-only.php.failed"
    fi
    if [[ -e "$AFB_HEADER_PLUGIN" ]]; then
        mv "$AFB_HEADER_PLUGIN" \
            "$AFB_BACKUP_ROOT/alanfullbeard-security-headers.php.failed"
    fi
    if [[ -e "$AFB_ASSET_ROOT" ]]; then
        mv "$AFB_ASSET_ROOT" \
            "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.failed"
    fi
}
trap rollback EXIT

mv "$AFB_BACKUP_ROOT/public_html.htaccess.candidate" "$AFB_HTACCESS"
mv "$AFB_BACKUP_ROOT/000-alanfullbeard-security-report-only.php.candidate" \
    "$AFB_REPORT_ONLY_OVERRIDE"
mv "$AFB_BACKUP_ROOT/alanfullbeard-security-headers.php.candidate" \
    "$AFB_HEADER_PLUGIN"
mv "$AFB_BACKUP_ROOT/alanfullbeard-security-assets.candidate" \
    "$AFB_ASSET_ROOT"

cd "$AFB_WEB_ROOT"
wp eval '
if (
    ! defined("ALANFULLBEARD_SECURITY_HEADERS_VERSION")
    || "1.0.0" !== ALANFULLBEARD_SECURITY_HEADERS_VERSION
    || ! has_action(
        "template_redirect",
        "alanfullbeard_security_send_public_headers"
    )
    || ! apply_filters(
        "alanfullbeard_security_csp_report_only",
        false
    )
) {
    fwrite(STDERR, "Security-header plugin load verification failed.\n");
    exit(1);
}

printf("security_headers_loaded=yes\ncsp_mode=report-only\n");
'

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
        grep -Eqi '^content-security-policy-report-only:.*require-trusted-types-for'
    printf '%s\n' "$afb_headers" |
        grep -Eqi '^content-security-policy:.*upgrade-insecure-requests'

    printf 'report_only_headers_verified=%s\n' "$afb_url"
done

rollback_needed=0
trap - EXIT

printf 'Activated public security headers in report-only CSP mode.\n'
printf 'Rollback directory: %s\n' "$AFB_BACKUP_ROOT"
sha256sum \
    "$AFB_HTACCESS" \
    "$AFB_REPORT_ONLY_OVERRIDE" \
    "$AFB_HEADER_PLUGIN" \
    "$AFB_ASSET_ROOT/purify.min.js" \
    "$AFB_ASSET_ROOT/trusted-types-policy.js"

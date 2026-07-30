#!/usr/bin/env bash

# Verifies the staged public security-header rollout without writing to the
# WordPress tree, .htaccess, backup area, or database.

set -euo pipefail

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

cd "$AFB_STAGE_ROOT"
sha256sum -c public-security-headers-SHA256SUMS

php -l "$AFB_STAGE_ROOT/alanfullbeard-security-headers.php" >/dev/null
php -l "$AFB_STAGE_ROOT/000-alanfullbeard-security-report-only.php" >/dev/null
grep -Fq 'DOMPurify 3.4.12' \
    "$AFB_STAGE_ROOT/alanfullbeard-security-assets/purify.min.js"

for afb_path in \
    "$AFB_HEADER_PLUGIN" \
    "$AFB_ASSET_ROOT" \
    "$AFB_REPORT_ONLY_OVERRIDE" \
    "$AFB_BACKUP_ROOT"; do
    if [[ -e "$afb_path" ]]; then
        printf 'Refusing rollout: target already exists: %s\n' "$afb_path" >&2
        exit 1
    fi
done

afb_live_hash="$(sha256sum "$AFB_HTACCESS" | cut -d ' ' -f 1)"
if [[ "$afb_live_hash" != "$AFB_EXPECTED_LIVE_HTACCESS_SHA256" ]]; then
    printf 'Refusing rollout: live .htaccess hash is %s, expected %s\n' \
        "$afb_live_hash" \
        "$AFB_EXPECTED_LIVE_HTACCESS_SHA256" >&2
    exit 1
fi

if [[ "$(grep -Fxc "$AFB_OLD_CSP_LINE" "$AFB_HTACCESS")" != "1" ]]; then
    printf 'Refusing rollout: expected exactly one current CSP fallback line\n' >&2
    exit 1
fi

if [[ "$(grep -Fxc "$AFB_NEW_CSP_LINE" "$AFB_HTACCESS")" != "0" ]]; then
    printf 'Refusing rollout: the candidate CSP fallback line is already present\n' >&2
    exit 1
fi

afb_candidate_hash="$(
    sed \
        's/^  Header always set Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"$/  Header always setifempty Content-Security-Policy "upgrade-insecure-requests; block-all-mixed-content"/' \
        "$AFB_HTACCESS" |
    sha256sum |
    cut -d ' ' -f 1
)"

printf 'dry_run=passed\n'
printf 'live_htaccess_sha256=%s\n' "$afb_live_hash"
printf 'candidate_htaccess_sha256=%s\n' "$afb_candidate_hash"
printf 'staged_header_plugin_sha256=%s\n' \
    "$(sha256sum "$AFB_STAGE_ROOT/alanfullbeard-security-headers.php" | cut -d ' ' -f 1)"
printf 'staged_dompurify_sha256=%s\n' \
    "$(sha256sum "$AFB_STAGE_ROOT/alanfullbeard-security-assets/purify.min.js" | cut -d ' ' -f 1)"
printf 'staged_trusted_types_policy_sha256=%s\n' \
    "$(sha256sum "$AFB_STAGE_ROOT/alanfullbeard-security-assets/trusted-types-policy.js" | cut -d ' ' -f 1)"
printf 'staged_report_only_override_sha256=%s\n' \
    "$(sha256sum "$AFB_STAGE_ROOT/000-alanfullbeard-security-report-only.php" | cut -d ' ' -f 1)"
printf 'planned_mode=report-only\n'
printf 'planned_database_writes=none\n'
printf 'planned_public_tree_writes=4 files plus one CSP fallback token\n'
printf 'planned_backup=%s\n' "$AFB_BACKUP_ROOT"

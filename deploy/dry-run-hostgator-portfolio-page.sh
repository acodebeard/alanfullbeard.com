#!/usr/bin/env bash

# Read-only preflight for creating the missing live Portfolio page from the
# reviewed candidate staged outside the public webroot.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_STAGE_ROOT="/home2/afullbeard/deployments/portfolio-page-20260729"
readonly AFB_CANDIDATE_ROOT="${AFB_STAGE_ROOT}/deploy"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/portfolio-page-20260729"

readonly -a AFB_CANDIDATES=(
    "contact-vault-backup-stream-lib.php|41741019514e93e2b2680f2e70bd5852adee3a6c021dbdb80050411f762ab777"
    "contact-vault-decrypt-backup.php|3e411b16ef1eba083116dc56608803ae4a8b89561fd4e78e7c1f1a1d1a47e156"
    "contact-vault-encrypt-backup.php|37162280afa6195f9aed8769a7dcf04972b7227474514261292a9131ac7a3c41"
    "hostgator-portfolio-page-content.html|660716cd0c61c0348c12bfc24e198264d9694df0622cd76721825e0d5f8d1a74"
    "hostgator-portfolio-page-lib.php|dbb9f8d8084e5627acb19010c14172f68d72ba0a8754886ebddd42404a3f5654"
    "dry-run-hostgator-portfolio-page.php|696d549f3de165fada9b9c3ee34f7d81f1541fa4607fc455957ae0320885d1dd"
    "create-hostgator-portfolio-page.php|9c5d1e36b10f1c60dc76831ece4a4d14ca9a348c711a6a2e206821c32978fc34"
    "rollback-hostgator-portfolio-page.php|b3c645de9f3498b4ce6fcb5c83c7fb7652a513278cd25b94f12dfc839f5dc868"
)

current_hash() {
    sha256sum "$1" | cut -d ' ' -f 1
}

printf '===== Target identity =====\n'
hostname
id
printf 'web_root=%s\n' "$AFB_WEB_ROOT"
printf 'candidate_root=%s\n' "$AFB_CANDIDATE_ROOT"

if [[ ! -d "$AFB_WEB_ROOT" || ! -d "$AFB_CANDIDATE_ROOT" ]]; then
    printf 'Preflight failed: web root or candidate root is missing\n' >&2
    exit 1
fi

if [[ -e "$AFB_BACKUP_ROOT" ]]; then
    printf 'Preflight failed: rollback root already exists: %s\n' \
        "$AFB_BACKUP_ROOT" >&2
    exit 1
fi

printf '\n===== Candidate hashes =====\n'
for afb_record in "${AFB_CANDIDATES[@]}"; do
    IFS='|' read -r afb_basename afb_expected <<< "$afb_record"
    afb_candidate="${AFB_CANDIDATE_ROOT}/${afb_basename}"

    if [[ ! -f "$afb_candidate" ]]; then
        printf 'Preflight failed: missing candidate %s\n' "$afb_candidate" >&2
        exit 1
    fi

    afb_actual="$(current_hash "$afb_candidate")"
    if [[ "$afb_actual" != "$afb_expected" ]]; then
        printf 'Preflight failed: %s hash is %s, expected %s\n' \
            "$afb_candidate" \
            "$afb_actual" \
            "$afb_expected" >&2
        exit 1
    fi

    printf '%s  %s\n' "$afb_actual" "$afb_basename"
done

printf '\n===== Candidate PHP syntax =====\n'
for afb_php in "$AFB_CANDIDATE_ROOT"/*.php; do
    php -l "$afb_php"
done

printf '\n===== WordPress Portfolio preflight =====\n'
cd "$AFB_WEB_ROOT"
wp eval-file "$AFB_CANDIDATE_ROOT/dry-run-hostgator-portfolio-page.php"

printf '\nDry run passed. No files, media, or database values were changed.\n'

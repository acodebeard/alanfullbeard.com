#!/usr/bin/env bash

# Creates the single missing live Portfolio page after a full database export.
# The page points only at five existing, hash-verified live attachments. A
# narrowly guarded rollback removes only the page created by this deployment.

set -Eeuo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_STAGE_ROOT="/home2/afullbeard/deployments/portfolio-page-20260729"
readonly AFB_CANDIDATE_ROOT="${AFB_STAGE_ROOT}/deploy"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/portfolio-page-20260729"
readonly AFB_DATABASE_BACKUP="${AFB_BACKUP_ROOT}/wordpress-before.sql.afbenc"
readonly AFB_DATABASE_BACKUP_HASH="${AFB_DATABASE_BACKUP}.sha256"
readonly AFB_ENCRYPT_REPORT="${AFB_BACKUP_ROOT}/encrypt-report.txt"
readonly AFB_DECRYPT_REPORT="${AFB_BACKUP_ROOT}/decrypt-report.txt"
readonly AFB_KEY_FILE="/home2/afullbeard/private-config/contact-vault-key.php"
readonly AFB_PHP="$(command -v php)"

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

afb_writes_started=false

current_hash() {
    sha256sum "$1" | cut -d ' ' -f 1
}

rollback() {
    local afb_status="$1"

    trap - ERR INT TERM
    set +e

    if [[ "$afb_writes_started" == true ]]; then
        cd "$AFB_WEB_ROOT" || true
        if wp eval-file "$AFB_CANDIDATE_ROOT/rollback-hostgator-portfolio-page.php"; then
            wp db check || true
            printf 'Activation failed; the created Portfolio page was removed.\n' >&2
        else
            printf 'CRITICAL: narrow Portfolio rollback failed.\n' >&2
            printf 'Full database rollback point: %s\n' "$AFB_DATABASE_BACKUP" >&2
        fi
    fi

    exit "$afb_status"
}

trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM

printf '===== Target identity =====\n'
hostname
id
printf 'web_root=%s\n' "$AFB_WEB_ROOT"
printf 'candidate_root=%s\n' "$AFB_CANDIDATE_ROOT"

if [[ ! -d "$AFB_WEB_ROOT" || ! -d "$AFB_CANDIDATE_ROOT" ]]; then
    printf 'Refusing activation: web root or candidate root is missing\n' >&2
    exit 1
fi

if [[ ! -f "$AFB_KEY_FILE" || -z "$AFB_PHP" ]]; then
    printf 'Refusing activation: encryption key or PHP CLI is unavailable\n' >&2
    exit 1
fi

if [[ -e "$AFB_BACKUP_ROOT" ]]; then
    printf 'Refusing activation: rollback root already exists: %s\n' \
        "$AFB_BACKUP_ROOT" >&2
    exit 1
fi

printf '\n===== Candidate hashes and PHP syntax =====\n'
for afb_record in "${AFB_CANDIDATES[@]}"; do
    IFS='|' read -r afb_basename afb_expected <<< "$afb_record"
    afb_candidate="${AFB_CANDIDATE_ROOT}/${afb_basename}"

    if [[ ! -f "$afb_candidate" ]]; then
        printf 'Refusing activation: missing candidate %s\n' \
            "$afb_candidate" >&2
        exit 1
    fi

    afb_actual="$(current_hash "$afb_candidate")"
    if [[ "$afb_actual" != "$afb_expected" ]]; then
        printf 'Refusing activation: %s hash is %s, expected %s\n' \
            "$afb_candidate" \
            "$afb_actual" \
            "$afb_expected" >&2
        exit 1
    fi

    printf '%s  %s\n' "$afb_actual" "$afb_basename"
done

for afb_php in "$AFB_CANDIDATE_ROOT"/*.php; do
    php -l "$afb_php"
done

cd "$AFB_WEB_ROOT"
wp eval-file "$AFB_CANDIDATE_ROOT/dry-run-hostgator-portfolio-page.php"

printf '\n===== Private database rollback point =====\n'
umask 077
mkdir -p "$AFB_BACKUP_ROOT"
wp db export - --add-drop-table |
    "$AFB_PHP" \
        "$AFB_CANDIDATE_ROOT/contact-vault-encrypt-backup.php" \
        "$AFB_KEY_FILE" \
        > "$AFB_DATABASE_BACKUP" \
        2> "$AFB_ENCRYPT_REPORT"
chmod 0600 "$AFB_DATABASE_BACKUP" "$AFB_ENCRYPT_REPORT"
sha256sum "$AFB_DATABASE_BACKUP" > "$AFB_DATABASE_BACKUP_HASH"
"$AFB_PHP" \
    "$AFB_CANDIDATE_ROOT/contact-vault-decrypt-backup.php" \
    "$AFB_KEY_FILE" \
    < "$AFB_DATABASE_BACKUP" \
    > /dev/null \
    2> "$AFB_DECRYPT_REPORT"
chmod 0600 "$AFB_DATABASE_BACKUP_HASH" "$AFB_DECRYPT_REPORT"
cmp "$AFB_ENCRYPT_REPORT" "$AFB_DECRYPT_REPORT"
sha256sum "$AFB_DATABASE_BACKUP"
wp db check

afb_writes_started=true

printf '\n===== Portfolio page creation =====\n'
afb_create_output="$(
    wp eval-file "$AFB_CANDIDATE_ROOT/create-hostgator-portfolio-page.php"
)"
printf '%s\n' "$afb_create_output"

afb_created_page_id="$(
    sed -nE \
        's/^Portfolio page created: page_id=([0-9]+).*$/\1/p' \
        <<< "$afb_create_output"
)"
if [[ ! "$afb_created_page_id" =~ ^[0-9]+$ ]]; then
    printf 'Activation failed: could not verify the created page ID\n' >&2
    exit 1
fi

afb_live_page_ids="$(
    wp post list \
        --post_type=page \
        --post_status=publish \
        --name=portfolio \
        --field=ID \
        --format=ids
)"
if [[ "$afb_live_page_ids" != "$afb_created_page_id" ]]; then
    printf 'Activation failed: live Portfolio page ID is unexpected\n' >&2
    exit 1
fi

wp db check

printf '\n===== Public health checks =====\n'
for afb_url in \
    "https://alanfullbeard.com/" \
    "https://alanfullbeard.com/portfolio/"; do
    curl \
        --fail \
        --silent \
        --show-error \
        --location \
        --max-time 20 \
        --output /dev/null \
        "$afb_url"
    printf 'HTTP_OK %s\n' "$afb_url"
done

afb_writes_started=false
trap - ERR INT TERM

printf '\nPortfolio page activation passed.\n'
printf 'Database rollback point: %s\n' "$AFB_DATABASE_BACKUP"

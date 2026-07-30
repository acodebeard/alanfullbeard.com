#!/usr/bin/env bash

# Activates encrypted Flamingo storage on HostGator, migrates the two existing
# inbound messages, and removes redundant plaintext Flamingo address-book rows.
# The CF7 form and Privacy Policy are intentionally updated in a later batch.

set -euo pipefail
umask 077

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_STAGE_ROOT="${AFB_ACCOUNT_ROOT}/security-staging/contact-vault-20260728"
readonly AFB_BACKUP_ROOT="${AFB_ACCOUNT_ROOT}/security-hardening-backups/contact-vault-20260728"
readonly AFB_PRIVATE_ROOT="${AFB_ACCOUNT_ROOT}/private-config"
readonly AFB_KEY_FILE="${AFB_PRIVATE_ROOT}/contact-vault-key.php"
readonly AFB_PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
readonly AFB_VAULT_TARGET="${AFB_WEB_ROOT}/wp-content/mu-plugins/alanfullbeard-contact-vault.php"
readonly AFB_LOADER_TARGET="${AFB_WEB_ROOT}/wp-content/mu-plugins/000-alanfullbeard-contact-vault-key.php"
readonly AFB_EXPECTED_INBOUND="2"
readonly AFB_EXPECTED_CONTACTS="4"

cd "$AFB_WEB_ROOT"

if [[ ! -x "$AFB_PHP" ]]; then
    printf 'Expected PHP runtime is unavailable: %s\n' "$AFB_PHP" >&2
    exit 1
fi

if [[ -e "$AFB_VAULT_TARGET" || -e "$AFB_LOADER_TARGET" || -e "$AFB_KEY_FILE" ]]; then
    printf 'Contact-vault target or key already exists; refusing to overwrite.\n' >&2
    exit 1
fi

(
    cd "$AFB_STAGE_ROOT"
    sha256sum -c contact-vault-SHA256SUMS
)

afb_db_prefix="$(wp db prefix)"
afb_inbound_count="$(
    wp db query --skip-column-names "
        SELECT COUNT(*)
        FROM ${afb_db_prefix}posts
        WHERE post_type = 'flamingo_inbound';
    "
)"
afb_contact_count="$(
    wp db query --skip-column-names "
        SELECT COUNT(*)
        FROM ${afb_db_prefix}posts
        WHERE post_type = 'flamingo_contact';
    "
)"

if [[ "$afb_inbound_count" != "$AFB_EXPECTED_INBOUND" ]]; then
    printf 'Inbound count changed: expected=%s actual=%s\n' \
        "$AFB_EXPECTED_INBOUND" \
        "$afb_inbound_count" >&2
    exit 1
fi

if [[ "$afb_contact_count" != "$AFB_EXPECTED_CONTACTS" ]]; then
    printf 'Contact count changed: expected=%s actual=%s\n' \
        "$AFB_EXPECTED_CONTACTS" \
        "$afb_contact_count" >&2
    exit 1
fi

mkdir -p "$AFB_BACKUP_ROOT" "$AFB_PRIVATE_ROOT"
chmod 700 "$AFB_BACKUP_ROOT" "$AFB_PRIVATE_ROOT"

"$AFB_PHP" \
    "$AFB_STAGE_ROOT/generate-hostgator-contact-vault-key.php" \
    "$AFB_KEY_FILE"

readonly AFB_DATABASE_BACKUP="${AFB_BACKUP_ROOT}/database-before.sql.afbenc"

wp db export - --add-drop-table |
    "$AFB_PHP" \
        "$AFB_STAGE_ROOT/contact-vault-encrypt-backup.php" \
        "$AFB_KEY_FILE" \
        > "$AFB_DATABASE_BACKUP" \
        2> "$AFB_BACKUP_ROOT/encrypt-report.txt"

chmod 600 \
    "$AFB_DATABASE_BACKUP" \
    "$AFB_BACKUP_ROOT/encrypt-report.txt"
sha256sum "$AFB_DATABASE_BACKUP" \
    > "$AFB_BACKUP_ROOT/database-before.sql.afbenc.sha256"

"$AFB_PHP" \
    "$AFB_STAGE_ROOT/contact-vault-decrypt-backup.php" \
    "$AFB_KEY_FILE" \
    < "$AFB_DATABASE_BACKUP" \
    > /dev/null \
    2> "$AFB_BACKUP_ROOT/decrypt-report.txt"

chmod 600 \
    "$AFB_BACKUP_ROOT/database-before.sql.afbenc.sha256" \
    "$AFB_BACKUP_ROOT/decrypt-report.txt"
cmp \
    "$AFB_BACKUP_ROOT/encrypt-report.txt" \
    "$AFB_BACKUP_ROOT/decrypt-report.txt"

install -m 0644 \
    "$AFB_STAGE_ROOT/hostgator-contact-vault-key-loader.php" \
    "$AFB_LOADER_TARGET"
install -m 0644 \
    "$AFB_STAGE_ROOT/alanfullbeard-contact-vault.php" \
    "$AFB_VAULT_TARGET"

wp eval-file "$AFB_STAGE_ROOT/migrate-hostgator-contact-vault.php"
wp eval-file "$AFB_STAGE_ROOT/purge-hostgator-flamingo-contacts.php"

printf 'post_migration_inbound='
wp db query --skip-column-names "
    SELECT COUNT(*)
    FROM ${afb_db_prefix}posts
    WHERE post_type = 'flamingo_inbound';
"
printf 'post_migration_encrypted='
wp db query --skip-column-names "
    SELECT COUNT(DISTINCT pm.post_id)
    FROM ${afb_db_prefix}postmeta AS pm
    INNER JOIN ${afb_db_prefix}posts AS p ON p.ID = pm.post_id
    WHERE p.post_type = 'flamingo_inbound'
      AND pm.meta_key = '_afb_contact_vault_payload';
"
printf 'post_migration_contacts='
wp db query --skip-column-names "
    SELECT COUNT(*)
    FROM ${afb_db_prefix}posts
    WHERE post_type = 'flamingo_contact';
"

stat -c '%A %U:%G %s %n' \
    "$AFB_KEY_FILE" \
    "$AFB_LOADER_TARGET" \
    "$AFB_VAULT_TARGET" \
    "$AFB_DATABASE_BACKUP"
sha256sum "$AFB_LOADER_TARGET" "$AFB_VAULT_TARGET"

printf 'Activated encrypted contact storage and migrated legacy records.\n'

#!/usr/bin/env bash

# Removes the obsolete storage checkbox and publishes the matching encrypted
# storage/privacy wording after the contact vault has been verified.

set -euo pipefail
umask 077

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_STAGE_ROOT="${AFB_ACCOUNT_ROOT}/security-staging/contact-vault-20260728"
readonly AFB_BACKUP_ROOT="${AFB_ACCOUNT_ROOT}/security-hardening-backups/contact-vault-20260728"
readonly AFB_KEY_FILE="${AFB_ACCOUNT_ROOT}/private-config/contact-vault-key.php"
readonly AFB_PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
readonly AFB_CONTACT_FORM_ID="61"
readonly AFB_PRIVACY_PAGE_ID="9"
readonly AFB_EXPECTED_FORM_HASH="5d3f2b3726474fdfd8f5f9433b0b27ce7f301c0c38cfeb316090e7e73c8c5ea0"
readonly AFB_EXPECTED_ADDITIONAL_HASH="e1274cca7558deed48004c22b3226b845e21579a8d0826dfd22799abbd5fc11b"
readonly AFB_EXPECTED_PRIVACY_HASH="1e83c62ac92dbb854a126371615a7e60e2b739b8f213df65d57ee582a43c9f93"
readonly AFB_CANDIDATE_FORM_HASH="95c32eef02a8e8f9ec71a613b7fa7ef4da5ed0eea0b40b54a6165b535fb5af8d"
readonly AFB_CANDIDATE_PRIVACY_HASH="b241ce0a7ddad3ce2ac01b5b8729111045a7ec7e05f87eaeff68c03de96cc49c"
readonly AFB_FORM_SOURCE="${AFB_STAGE_ROOT}/contact-form-7-form.txt"
readonly AFB_PRIVACY_SOURCE="${AFB_STAGE_ROOT}/privacy-policy.html"

cd "$AFB_WEB_ROOT"

afb_hash() {
    printf '%s' "$1" | sha256sum | cut -d ' ' -f 1
}

afb_live_form="$(wp post meta get "$AFB_CONTACT_FORM_ID" _form)"
afb_live_additional="$(
    wp post meta get "$AFB_CONTACT_FORM_ID" _additional_settings
)"
afb_live_privacy="$(
    wp post get "$AFB_PRIVACY_PAGE_ID" --field=post_content
)"

if [[ "$(afb_hash "$afb_live_form")" != "$AFB_EXPECTED_FORM_HASH" ]]; then
    printf 'Live contact form changed after review; refusing to overwrite.\n' >&2
    exit 1
fi

if [[ "$(afb_hash "$afb_live_additional")" != "$AFB_EXPECTED_ADDITIONAL_HASH" ]]; then
    printf 'Live CF7 additional settings changed; refusing to continue.\n' >&2
    exit 1
fi

if [[ "$(afb_hash "$afb_live_privacy")" != "$AFB_EXPECTED_PRIVACY_HASH" ]]; then
    printf 'Live Privacy Policy changed after review; refusing to overwrite.\n' >&2
    exit 1
fi

if [[ "$(afb_hash "$(< "$AFB_FORM_SOURCE")")" != "$AFB_CANDIDATE_FORM_HASH" ]]; then
    printf 'Staged contact-form candidate hash failed.\n' >&2
    exit 1
fi

if [[ "$(afb_hash "$(< "$AFB_PRIVACY_SOURCE")")" != "$AFB_CANDIDATE_PRIVACY_HASH" ]]; then
    printf 'Staged privacy-policy candidate hash failed.\n' >&2
    exit 1
fi

afb_db_prefix="$(wp db prefix)"
afb_encrypted_count="$(
    wp db query --skip-column-names "
        SELECT COUNT(DISTINCT pm.post_id)
        FROM ${afb_db_prefix}postmeta AS pm
        INNER JOIN ${afb_db_prefix}posts AS p ON p.ID = pm.post_id
        WHERE p.post_type = 'flamingo_inbound'
          AND pm.meta_key = '_afb_contact_vault_payload';
    "
)"
afb_contact_count="$(
    wp db query --skip-column-names "
        SELECT COUNT(*)
        FROM ${afb_db_prefix}posts
        WHERE post_type = 'flamingo_contact';
    "
)"

if [[ "$afb_encrypted_count" != "2" || "$afb_contact_count" != "0" ]]; then
    printf 'Encrypted storage is not in the expected verified state.\n' >&2
    exit 1
fi

readonly AFB_DATABASE_BACKUP="${AFB_BACKUP_ROOT}/database-before-form-policy.sql.afbenc"
readonly AFB_DATABASE_BACKUP_HASH="${AFB_DATABASE_BACKUP}.sha256"
readonly AFB_ENCRYPT_REPORT="${AFB_BACKUP_ROOT}/form-policy-encrypt-report.txt"
readonly AFB_DECRYPT_REPORT="${AFB_BACKUP_ROOT}/form-policy-decrypt-report.txt"

for afb_backup_artifact in \
    "$AFB_DATABASE_BACKUP" \
    "$AFB_DATABASE_BACKUP_HASH" \
    "$AFB_ENCRYPT_REPORT" \
    "$AFB_DECRYPT_REPORT"
do
    if [[ -e "$afb_backup_artifact" ]]; then
        printf 'Form-policy backup artifact already exists: %s\n' \
            "$afb_backup_artifact" >&2
        exit 1
    fi
done

wp db export - --add-drop-table |
    "$AFB_PHP" \
        "$AFB_STAGE_ROOT/contact-vault-encrypt-backup.php" \
        "$AFB_KEY_FILE" \
        > "$AFB_DATABASE_BACKUP" \
        2> "$AFB_ENCRYPT_REPORT"

chmod 600 \
    "$AFB_DATABASE_BACKUP" \
    "$AFB_ENCRYPT_REPORT"
sha256sum "$AFB_DATABASE_BACKUP" \
    > "$AFB_DATABASE_BACKUP_HASH"

"$AFB_PHP" \
    "$AFB_STAGE_ROOT/contact-vault-decrypt-backup.php" \
    "$AFB_KEY_FILE" \
    < "$AFB_DATABASE_BACKUP" \
    > /dev/null \
    2> "$AFB_DECRYPT_REPORT"

chmod 600 \
    "$AFB_DATABASE_BACKUP_HASH" \
    "$AFB_DECRYPT_REPORT"
cmp \
    "$AFB_ENCRYPT_REPORT" \
    "$AFB_DECRYPT_REPORT"

wp post meta update \
    "$AFB_CONTACT_FORM_ID" \
    _form \
    "$(< "$AFB_FORM_SOURCE")"
wp post update \
    "$AFB_PRIVACY_PAGE_ID" \
    --post_content="$(< "$AFB_PRIVACY_SOURCE")"
wp cache flush

afb_updated_form="$(wp post meta get "$AFB_CONTACT_FORM_ID" _form)"
afb_updated_privacy="$(
    wp post get "$AFB_PRIVACY_PAGE_ID" --field=post_content
)"

if [[ "$(afb_hash "$afb_updated_form")" != "$AFB_CANDIDATE_FORM_HASH" ]]; then
    printf 'Updated contact-form hash failed verification.\n' >&2
    exit 1
fi

if [[ "$(afb_hash "$afb_updated_privacy")" != "$AFB_CANDIDATE_PRIVACY_HASH" ]]; then
    printf 'Updated Privacy Policy hash failed verification.\n' >&2
    exit 1
fi

if grep -q 'acceptance message-storage' <<< "$afb_updated_form"; then
    printf 'The obsolete storage checkbox remains in the live form.\n' >&2
    exit 1
fi

curl -fsS https://alanfullbeard.com/contact/ |
    grep -Fq 'stores an encrypted copy in WordPress'
curl -fsS https://alanfullbeard.com/privacy-policy/ |
    grep -Fq 'encrypted at rest in WordPress with Sodium XChaCha20-Poly1305'

printf 'Published the encrypted-storage contact form and Privacy Policy.\n'

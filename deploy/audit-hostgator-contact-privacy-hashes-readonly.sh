#!/usr/bin/env bash

# Read-only hash and content inspection for the live CF7 form definition and
# Privacy Policy page. No submissions, mail settings, or credentials are read.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_CONTACT_FORM_ID="61"

cd "$AFB_WEB_ROOT"

afb_db_prefix="$(wp db prefix)"
afb_form="$(wp post meta get "$AFB_CONTACT_FORM_ID" _form)"
afb_additional_settings="$(
    wp post meta get "$AFB_CONTACT_FORM_ID" _additional_settings
)"

printf '===== Contact form guards =====\n'
printf 'contact_form_id=%s\n' "$AFB_CONTACT_FORM_ID"
printf 'contact_form_meta_sha256='
printf '%s' "$afb_form" | sha256sum | cut -d ' ' -f 1
printf 'additional_settings_sha256='
printf '%s' "$afb_additional_settings" | sha256sum | cut -d ' ' -f 1
printf 'storage_acceptance_count=%s\n' "$(
    printf '%s' "$afb_form" |
        grep -c 'acceptance message-storage' || true
)"

printf '\n===== Legacy encrypted-vault migration inventory =====\n'
wp db query --skip-column-names "
SELECT post_status, COUNT(*)
FROM ${afb_db_prefix}posts
WHERE post_type = 'flamingo_inbound'
GROUP BY post_status
ORDER BY post_status;
"

printf 'legacy_inbound_rows='
wp db query --skip-column-names "
SELECT COUNT(*)
FROM ${afb_db_prefix}posts
WHERE post_type = 'flamingo_inbound';
"

printf 'legacy_contact_rows='
wp db query --skip-column-names "
SELECT COUNT(*)
FROM ${afb_db_prefix}posts
WHERE post_type = 'flamingo_contact';
"

printf 'legacy_inbound_with_field_meta='
wp db query --skip-column-names "
SELECT COUNT(DISTINCT pm.post_id)
FROM ${afb_db_prefix}postmeta AS pm
INNER JOIN ${afb_db_prefix}posts AS p ON p.ID = pm.post_id
WHERE p.post_type = 'flamingo_inbound'
  AND pm.meta_key LIKE '\\\\_field\\\\_%';
"

printf 'already_encrypted_inbound='
wp db query --skip-column-names "
SELECT COUNT(DISTINCT pm.post_id)
FROM ${afb_db_prefix}postmeta AS pm
INNER JOIN ${afb_db_prefix}posts AS p ON p.ID = pm.post_id
WHERE p.post_type = 'flamingo_inbound'
  AND pm.meta_key = '_afb_contact_vault_payload';
"

printf '\n===== Privacy Policy page =====\n'
wp post list \
    --post_type=page \
    --name=privacy-policy \
    --post_status=any \
    --fields=ID,post_title,post_name,post_status,post_modified_gmt \
    --format=table

mapfile -t afb_privacy_ids < <(
    wp post list \
        --post_type=page \
        --name=privacy-policy \
        --post_status=any \
        --field=ID
)

printf 'privacy_page_count=%s\n' "${#afb_privacy_ids[@]}"
for afb_privacy_id in "${afb_privacy_ids[@]}"; do
    afb_privacy_content="$(wp post get "$afb_privacy_id" --field=post_content)"
    printf 'privacy_page_id=%s\n' "$afb_privacy_id"
    printf 'privacy_content_sha256='
    printf '%s' "$afb_privacy_content" | sha256sum | cut -d ' ' -f 1
    printf '\n[privacy_post_content]\n%s\n[/privacy_post_content]\n' \
        "$afb_privacy_content"
done

printf '\nread-only contact and privacy hash audit completed\n'

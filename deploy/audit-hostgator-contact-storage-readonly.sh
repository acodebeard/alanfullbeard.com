#!/usr/bin/env bash

# Read-only inspection of the live Contact Form 7 definition and Flamingo
# storage settings. This does not print submissions or mail credentials.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"

cd "$AFB_WEB_ROOT"

printf '===== Plugin state =====\n'
for afb_plugin in contact-form-7 flamingo cf7-antispam; do
    if wp plugin is-active "$afb_plugin"; then
        printf '%s=active\n' "$afb_plugin"
    else
        printf '%s=inactive\n' "$afb_plugin"
    fi
done

printf '\n===== Contact forms =====\n'
wp post list \
    --post_type=wpcf7_contact_form \
    --post_status=any \
    --fields=ID,post_title,post_status,post_modified_gmt \
    --format=table

mapfile -t afb_form_ids < <(
    wp post list \
        --post_type=wpcf7_contact_form \
        --post_status=any \
        --field=ID
)

printf 'contact_form_count=%s\n' "${#afb_form_ids[@]}"
for afb_form_id in "${afb_form_ids[@]}"; do
    afb_title="$(wp post get "$afb_form_id" --field=post_title)"
    afb_content="$(wp post get "$afb_form_id" --field=post_content)"
    afb_legacy_form="$(wp post meta get "$afb_form_id" _form 2>/dev/null || true)"
    afb_additional_settings="$(
        wp post meta get "$afb_form_id" _additional_settings 2>/dev/null || true
    )"

    printf '\n----- Form %s: %s -----\n' "$afb_form_id" "$afb_title"
    printf 'post_content_sha256='
    printf '%s' "$afb_content" | sha256sum | cut -d ' ' -f 1
    printf 'legacy_form_meta_present=%s\n' "$([[ -n "$afb_legacy_form" ]] && printf yes || printf no)"
    printf 'additional_settings_sha256='
    printf '%s' "$afb_additional_settings" | sha256sum | cut -d ' ' -f 1

    printf '\n[post_content]\n%s\n[/post_content]\n' "$afb_content"
    if [[ -n "$afb_legacy_form" ]]; then
        printf '\n[legacy_form_meta]\n%s\n[/legacy_form_meta]\n' "$afb_legacy_form"
    fi
    printf '\n[additional_settings]\n%s\n[/additional_settings]\n' \
        "$afb_additional_settings"
done

printf '\nread-only contact-storage audit completed\n'

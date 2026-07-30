#!/usr/bin/env bash

# Read-only inventory for the exact Portfolio page and five public screenshot
# attachments needed by the alanfullbeard.com launch. It does not print page
# content, credentials, contact messages, or private FileHub information.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_UPLOAD_ROOT="${AFB_WEB_ROOT}/wp-content/uploads/2026/07"

readonly -a AFB_MEDIA_FILES=(
    "aaa-screen.webp"
    "dkc-screen.webp"
    "laffs-screen.webp"
    "newtimes-screen.webp"
    "redmeat-screen.webp"
)

printf '===== Target identity =====\n'
hostname
id
printf 'web_root=%s\n' "$AFB_WEB_ROOT"

cd "$AFB_WEB_ROOT"

printf '\n===== Portfolio page =====\n'
afb_portfolio_ids="$(
    wp post list \
        --post_type=page \
        --post_status=any \
        --name=portfolio \
        --field=ID \
        --format=ids
)"

if [[ -z "$afb_portfolio_ids" ]]; then
    printf 'portfolio_page=missing\n'
else
    printf 'portfolio_page_ids=%s\n' "$afb_portfolio_ids"
    wp post list \
        --post_type=page \
        --post_status=any \
        --name=portfolio \
        --fields=ID,post_title,post_name,post_status,post_modified \
        --format=table
fi

printf '\n===== Exact media files and attachment records =====\n'
for afb_basename in "${AFB_MEDIA_FILES[@]}"; do
    afb_relative="2026/07/${afb_basename}"
    afb_live_file="${AFB_UPLOAD_ROOT}/${afb_basename}"

    if [[ -f "$afb_live_file" ]]; then
        stat -c '%A %U:%G %s %n' "$afb_live_file"
        sha256sum "$afb_live_file"
    else
        printf 'MISSING_FILE %s\n' "$afb_live_file"
    fi

    afb_attachment_ids="$(
        wp post list \
            --post_type=attachment \
            --post_status=any \
            --meta_key=_wp_attached_file \
            --meta_value="$afb_relative" \
            --field=ID \
            --format=ids
    )"

    if [[ -z "$afb_attachment_ids" ]]; then
        printf 'MISSING_ATTACHMENT %s\n' "$afb_relative"
    else
        printf 'ATTACHMENT %s ids=%s\n' \
            "$afb_relative" \
            "$afb_attachment_ids"
    fi
done

printf '\n===== Account owner for new public content =====\n'
wp user get alan --fields=ID,user_login,roles --format=table

printf '\nRead-only Portfolio content audit completed.\n'

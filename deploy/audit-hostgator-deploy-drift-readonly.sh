#!/usr/bin/env bash

# Read-only filename-and-hash inventory for the remaining alanfullbeard.com
# HostGator deployment work. It does not print file contents, WordPress
# credentials, contact messages, or private FileHub filenames.

set -u

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_THEME_ROOT="${AFB_WEB_ROOT}/wp-content/themes/alanfullbeard"
readonly AFB_MU_ROOT="${AFB_WEB_ROOT}/wp-content/mu-plugins"

section() {
    printf '\n===== %s =====\n' "$1"
}

manifest_tree() {
    local afb_label="$1"
    local afb_root="$2"
    local afb_item
    local afb_relative

    printf '%s\n' "$afb_label"

    if [[ ! -d "$afb_root" ]]; then
        printf 'MISSING_TREE %s\n' "$afb_root"
        return
    fi

    for afb_item in "$afb_root"/**; do
        afb_relative="${afb_item#"$afb_root"/}"

        if [[ -L "$afb_item" ]]; then
            printf 'SYMLINK  %s\n' "$afb_relative"
        elif [[ -f "$afb_item" ]]; then
            printf '%s  %s\n' \
                "$(sha256sum "$afb_item" | cut -d ' ' -f 1)" \
                "$afb_relative"
        fi
    done
}

shopt -s globstar nullglob dotglob

section "Target identity"
hostname
id
printf 'web_root=%s\n' "$AFB_WEB_ROOT"

section "Active PHP upload-limit configuration"
for afb_ini in \
    "${AFB_ACCOUNT_ROOT}/.user.ini" \
    "${AFB_ACCOUNT_ROOT}/php.ini" \
    "${AFB_WEB_ROOT}/.user.ini" \
    "${AFB_WEB_ROOT}/php.ini"; do
    if [[ -f "$afb_ini" ]]; then
        stat -c '%A %U:%G %s %n' "$afb_ini"
        sha256sum "$afb_ini"
        grep -nE \
            '^[[:space:]]*(upload_max_filesize|post_max_size|memory_limit|max_execution_time)[[:space:]]*=' \
            "$afb_ini" || true
    else
        printf 'missing %s\n' "$afb_ini"
    fi
done

section "Uploads execution guard"
stat -c '%A %U:%G %s %n' "$AFB_WEB_ROOT/wp-content/uploads/.htaccess"
sha256sum "$AFB_WEB_ROOT/wp-content/uploads/.htaccess"
grep -nE \
    'FilesMatch|SetHandler|Require all denied|Options -Indexes|php|phtml|phar' \
    "$AFB_WEB_ROOT/wp-content/uploads/.htaccess"

section "Theme manifest"
manifest_tree "alanfullbeard-theme" "$AFB_THEME_ROOT"

section "MU-plugin manifest"
manifest_tree "mu-plugins" "$AFB_MU_ROOT"

section "Audit complete"
printf 'No files or database values were changed.\n'

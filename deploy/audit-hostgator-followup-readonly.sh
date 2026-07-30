#!/usr/bin/env bash

# Narrow follow-up for the HostGator launch audit. No private FileHub filenames
# or file contents are printed.

set -u

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_FILEHUB_ROOT="${AFB_ACCOUNT_ROOT}/filehub_data"

printf '===== Live configuration hashes =====\n'
sha256sum \
    "$AFB_WEB_ROOT/.htaccess" \
    "$AFB_WEB_ROOT/wp-content/uploads/.htaccess" \
    "$AFB_WEB_ROOT/wp-config.php"

printf '\n===== FileHub implementation boundary =====\n'
stat -c '%A %U:%G %s %n' \
    "$AFB_WEB_ROOT/filehub" \
    "$AFB_WEB_ROOT/filehub/.htaccess" \
    "$AFB_WEB_ROOT/wp-content/mu-plugins/alanfullbeard-filehub.php" 2>/dev/null

printf '\n===== Private FileHub mode distribution =====\n'
declare -A afb_file_modes=()
declare -A afb_directory_modes=()
afb_file_count=0
afb_directory_count=0
afb_symlink_count=0

shopt -s globstar nullglob dotglob
for afb_item in "$AFB_FILEHUB_ROOT" "$AFB_FILEHUB_ROOT"/**; do
    if [[ -L "$afb_item" ]]; then
        ((afb_symlink_count += 1))
    elif [[ -f "$afb_item" ]]; then
        afb_mode="$(stat -c '%a' "$afb_item")"
        ((afb_file_modes["$afb_mode"] += 1))
        ((afb_file_count += 1))
    elif [[ -d "$afb_item" ]]; then
        afb_mode="$(stat -c '%a' "$afb_item")"
        ((afb_directory_modes["$afb_mode"] += 1))
        ((afb_directory_count += 1))
    fi
done

printf 'files=%s directories=%s symlinks=%s\n' \
    "$afb_file_count" \
    "$afb_directory_count" \
    "$afb_symlink_count"
for afb_mode in "${!afb_file_modes[@]}"; do
    printf 'file_mode_%s=%s\n' "$afb_mode" "${afb_file_modes[$afb_mode]}"
done | sort
for afb_mode in "${!afb_directory_modes[@]}"; do
    printf 'directory_mode_%s=%s\n' "$afb_mode" "${afb_directory_modes[$afb_mode]}"
done | sort

printf '\n===== Custom-code aggregate hashes =====\n'
hash_code_tree() {
    local afb_label="$1"
    local afb_root="$2"
    local afb_count=0

    if [[ ! -d "$afb_root" ]]; then
        printf '%s missing\n' "$afb_label"
        return
    fi

    for afb_code_file in "$afb_root"/**; do
        [[ -f "$afb_code_file" ]] || continue
        ((afb_count += 1))
    done

    printf '%s files=%s aggregate=' "$afb_label" "$afb_count"
    for afb_code_file in "$afb_root"/**; do
        [[ -f "$afb_code_file" ]] || continue
        sha256sum "$afb_code_file" | awk '{print $1}'
    done | sha256sum | awk '{print $1}'
}

hash_code_tree "basic-seo-meta" "$AFB_WEB_ROOT/wp-content/plugins/basic-seo-meta"
hash_code_tree "cheatjs" "$AFB_WEB_ROOT/wp-content/plugins/cheatjs"
hash_code_tree "viazen-mailersend-smtp" "$AFB_WEB_ROOT/wp-content/plugins/viazen-mailersend-smtp"
hash_code_tree "waypoints-trip-planner" "$AFB_WEB_ROOT/wp-content/plugins/waypoints-trip-planner"
hash_code_tree "alanfullbeard-theme" "$AFB_WEB_ROOT/wp-content/themes/alanfullbeard"
hash_code_tree "mu-plugins" "$AFB_WEB_ROOT/wp-content/mu-plugins"

printf '\nread-only follow-up completed\n'

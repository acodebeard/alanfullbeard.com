#!/usr/bin/env bash

# Read-only preflight for replacing the HostGator standalone FileHub with the
# administrator-only WordPress implementation. Private filenames and file
# contents are intentionally not printed.

set -u

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_PUBLIC_FILEHUB="${AFB_WEB_ROOT}/filehub"
readonly AFB_PRIVATE_FILEHUB="${AFB_ACCOUNT_ROOT}/filehub_data"
readonly AFB_MU_FILEHUB="${AFB_WEB_ROOT}/wp-content/mu-plugins/alanfullbeard-filehub.php"

section() {
    printf '\n===== %s =====\n' "$1"
}

section "Standalone application boundary"
stat -c '%A %U:%G %s %n' "$AFB_PUBLIC_FILEHUB" 2>/dev/null

shopt -s globstar nullglob dotglob
afb_public_files=0
afb_public_directories=0
afb_public_symlinks=0
afb_public_php_like=0
declare -A afb_public_file_modes=()

for afb_entry in "$AFB_PUBLIC_FILEHUB"/**; do
    if [[ -L "$afb_entry" ]]; then
        ((afb_public_symlinks += 1))
    elif [[ -f "$afb_entry" ]]; then
        ((afb_public_files += 1))
        afb_mode="$(stat -c '%a' "$afb_entry")"
        ((afb_public_file_modes["$afb_mode"] += 1))
        case "${afb_entry,,}" in
            *.php|*.php[0-9]|*.phtml|*.phar|*.phps)
                ((afb_public_php_like += 1))
                ;;
        esac
    elif [[ -d "$afb_entry" ]]; then
        ((afb_public_directories += 1))
    fi
done

printf 'public_file_count=%s\n' "$afb_public_files"
printf 'public_directory_count=%s\n' "$afb_public_directories"
printf 'public_symlink_count=%s\n' "$afb_public_symlinks"
printf 'public_php_like_count=%s\n' "$afb_public_php_like"
du -sh "$AFB_PUBLIC_FILEHUB" 2>/dev/null
for afb_mode in "${!afb_public_file_modes[@]}"; do
    printf 'public_file_mode_%s=%s\n' "$afb_mode" "${afb_public_file_modes[$afb_mode]}"
done | sort

printf 'public_aggregate_sha256='
for afb_entry in "$AFB_PUBLIC_FILEHUB"/**; do
    [[ -f "$afb_entry" && ! -L "$afb_entry" ]] || continue
    sha256sum "$afb_entry" | cut -d ' ' -f 1
done | sha256sum | cut -d ' ' -f 1

section "WordPress administrator implementation"
if [[ -f "$AFB_MU_FILEHUB" ]]; then
    stat -c '%A %U:%G %s %n' "$AFB_MU_FILEHUB"
    sha256sum "$AFB_MU_FILEHUB"
else
    printf 'mu_filehub=absent\n'
fi

for afb_constant in \
    ALANFULLBEARD_FILEHUB_ROOT \
    ALANFULLBEARD_FILEHUB_CAPABILITY \
    ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES
do
    if grep -q "$afb_constant" "$AFB_WEB_ROOT/wp-config.php"; then
        printf '%s=defined\n' "$afb_constant"
    else
        printf '%s=absent\n' "$afb_constant"
    fi
done

section "Private storage boundary"
stat -c '%A %U:%G %s %n' \
    "$AFB_PRIVATE_FILEHUB" \
    "$AFB_PRIVATE_FILEHUB/storage" \
    "$AFB_PRIVATE_FILEHUB/favorites" \
    "$AFB_PRIVATE_FILEHUB/tmp" 2>/dev/null

afb_private_files=0
afb_private_directories=0
afb_private_symlinks=0
declare -A afb_private_file_modes=()
declare -A afb_private_directory_modes=()

for afb_entry in "$AFB_PRIVATE_FILEHUB"/**; do
    if [[ -L "$afb_entry" ]]; then
        ((afb_private_symlinks += 1))
    elif [[ -f "$afb_entry" ]]; then
        ((afb_private_files += 1))
        afb_mode="$(stat -c '%a' "$afb_entry")"
        ((afb_private_file_modes["$afb_mode"] += 1))
    elif [[ -d "$afb_entry" ]]; then
        ((afb_private_directories += 1))
        afb_mode="$(stat -c '%a' "$afb_entry")"
        ((afb_private_directory_modes["$afb_mode"] += 1))
    fi
done

printf 'private_file_count=%s\n' "$afb_private_files"
printf 'private_directory_count=%s\n' "$afb_private_directories"
printf 'private_symlink_count=%s\n' "$afb_private_symlinks"
du -sh "$AFB_PRIVATE_FILEHUB" 2>/dev/null
for afb_mode in "${!afb_private_file_modes[@]}"; do
    printf 'private_file_mode_%s=%s\n' "$afb_mode" "${afb_private_file_modes[$afb_mode]}"
done | sort
for afb_mode in "${!afb_private_directory_modes[@]}"; do
    printf 'private_directory_mode_%s=%s\n' "$afb_mode" "${afb_private_directory_modes[$afb_mode]}"
done | sort

section "WordPress load check"
cd "$AFB_WEB_ROOT" || exit 1
wp eval '
printf(
    "filehub_function_loaded=%s\nfilehub_admin_handler_loaded=%s\n",
    function_exists("alanfullbeard_filehub_base_path") ? "yes" : "no",
    has_action("admin_post_alanfullbeard_filehub_upload") ? "yes" : "no"
);
'

section "Preflight complete"
printf 'read-only FileHub transition preflight completed\n'

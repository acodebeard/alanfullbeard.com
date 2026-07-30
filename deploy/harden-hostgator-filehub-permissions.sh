#!/usr/bin/env bash

# Tightens HostGator FileHub storage permissions without listing private
# filenames or changing file contents and locations.

set -euo pipefail

readonly AFB_PRIVATE_ROOT="/home2/afullbeard/filehub_data"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/filehub-admin-20260728"
readonly AFB_PERMISSION_RECORD="${AFB_BACKUP_ROOT}/permissions.before.txt"

if [[ ! -d "$AFB_PRIVATE_ROOT" ]]; then
    printf 'Refusing permission change: private FileHub root is missing\n' >&2
    exit 1
fi

private_realpath="$(realpath "$AFB_PRIVATE_ROOT")"
if [[ "$private_realpath" != "$AFB_PRIVATE_ROOT" ]]; then
    printf 'Refusing permission change: private FileHub root did not resolve exactly\n' >&2
    exit 1
fi

if [[ ! -d "$AFB_BACKUP_ROOT" || -e "$AFB_PERMISSION_RECORD" ]]; then
    printf 'Refusing permission change: rollback directory is missing or record already exists\n' >&2
    exit 1
fi

shopt -s globstar nullglob dotglob
afb_files=()
afb_directories=("$AFB_PRIVATE_ROOT")
afb_symlink_count=0

for afb_entry in "$AFB_PRIVATE_ROOT"/**; do
    if [[ -L "$afb_entry" ]]; then
        ((afb_symlink_count += 1))
    elif [[ -f "$afb_entry" ]]; then
        afb_files+=("$afb_entry")
    elif [[ -d "$afb_entry" ]]; then
        afb_directories+=("$afb_entry")
    fi
done

if [[ "$afb_symlink_count" != "0" ]]; then
    printf 'Refusing permission change: private storage contains %s symlink(s)\n' \
        "$afb_symlink_count" >&2
    exit 1
fi

umask 077
{
    for afb_entry in "${afb_directories[@]}" "${afb_files[@]}"; do
        stat -c '%a %n' "$afb_entry"
    done
} > "$AFB_PERMISSION_RECORD"
chmod 0600 "$AFB_PERMISSION_RECORD"

for afb_entry in "${afb_directories[@]}"; do
    chmod 0700 "$afb_entry"
done
for afb_entry in "${afb_files[@]}"; do
    chmod 0600 "$afb_entry"
done

afb_bad_file_modes=0
afb_bad_directory_modes=0
for afb_entry in "${afb_files[@]}"; do
    [[ "$(stat -c '%a' "$afb_entry")" == "600" ]] || ((afb_bad_file_modes += 1))
done
for afb_entry in "${afb_directories[@]}"; do
    [[ "$(stat -c '%a' "$afb_entry")" == "700" ]] || ((afb_bad_directory_modes += 1))
done

if [[ "$afb_bad_file_modes" != "0" || "$afb_bad_directory_modes" != "0" ]]; then
    printf 'Permission verification failed: files=%s directories=%s\n' \
        "$afb_bad_file_modes" \
        "$afb_bad_directory_modes" >&2
    exit 1
fi

printf 'Tightened private FileHub permissions.\n'
printf 'private_file_count=%s\n' "${#afb_files[@]}"
printf 'private_directory_count=%s\n' "${#afb_directories[@]}"
printf 'private_file_mode_600=%s\n' "${#afb_files[@]}"
printf 'private_directory_mode_700=%s\n' "${#afb_directories[@]}"
printf 'Private rollback record: %s\n' "$AFB_PERMISSION_RECORD"

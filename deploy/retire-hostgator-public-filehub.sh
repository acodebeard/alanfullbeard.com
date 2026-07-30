#!/usr/bin/env bash

# Retires the HostGator standalone FileHub by moving it intact outside the
# public webroot. The WordPress administrator implementation must remain loaded.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_PUBLIC_FILEHUB="${AFB_WEB_ROOT}/filehub"
readonly AFB_QUARANTINE_ROOT="/home2/afullbeard/security-quarantine"
readonly AFB_RETIRED_FILEHUB="${AFB_QUARANTINE_ROOT}/retired-filehub-20260728"
readonly AFB_EXPECTED_AGGREGATE_SHA256="dfc076a3906033874c1936b144502501f746db89c600af129818a064ab71d7d4"

if [[ ! -d "$AFB_PUBLIC_FILEHUB" ]]; then
    printf 'Refusing retirement: public FileHub directory is missing\n' >&2
    exit 1
fi

if [[ ! -d "$AFB_QUARANTINE_ROOT" || -e "$AFB_RETIRED_FILEHUB" ]]; then
    printf 'Refusing retirement: quarantine root is missing or target already exists\n' >&2
    exit 1
fi

shopt -s globstar nullglob dotglob
afb_files=()
afb_directories=("$AFB_PUBLIC_FILEHUB")
afb_symlink_count=0

for afb_entry in "$AFB_PUBLIC_FILEHUB"/**; do
    if [[ -L "$afb_entry" ]]; then
        ((afb_symlink_count += 1))
    elif [[ -f "$afb_entry" ]]; then
        afb_files+=("$afb_entry")
    elif [[ -d "$afb_entry" ]]; then
        afb_directories+=("$afb_entry")
    fi
done

if [[ "$afb_symlink_count" != "0" || "${#afb_files[@]}" != "1" ]]; then
    printf 'Refusing retirement: expected one regular file and no symlinks\n' >&2
    exit 1
fi

aggregate_hash="$(
    for afb_entry in "${afb_files[@]}"; do
        sha256sum "$afb_entry" | cut -d ' ' -f 1
    done | sha256sum | cut -d ' ' -f 1
)"
if [[ "$aggregate_hash" != "$AFB_EXPECTED_AGGREGATE_SHA256" ]]; then
    printf 'Refusing retirement: public FileHub hash differs from audit\n' >&2
    exit 1
fi

cd "$AFB_WEB_ROOT"
wp eval '
if (
    ! function_exists("alanfullbeard_filehub_base_path")
    || ! has_action("admin_post_alanfullbeard_filehub_upload")
    || alanfullbeard_filehub_base_path() !== "/home2/afullbeard/filehub_data"
) {
    fwrite(STDERR, "WordPress administrator FileHub verification failed.\n");
    exit(1);
}
'

mv "$AFB_PUBLIC_FILEHUB" "$AFB_RETIRED_FILEHUB"

shopt -s globstar nullglob dotglob
for afb_entry in "$AFB_RETIRED_FILEHUB" "$AFB_RETIRED_FILEHUB"/**; do
    if [[ -L "$afb_entry" ]]; then
        printf 'Unexpected symlink after retirement; manual review required\n' >&2
        exit 1
    elif [[ -f "$afb_entry" ]]; then
        chmod 0600 "$afb_entry"
    elif [[ -d "$afb_entry" ]]; then
        chmod 0700 "$afb_entry"
    fi
done

if [[ -e "$AFB_PUBLIC_FILEHUB" || ! -d "$AFB_RETIRED_FILEHUB" ]]; then
    printf 'Public FileHub retirement verification failed\n' >&2
    exit 1
fi

printf 'Retired the standalone public FileHub intact.\n'
printf 'Rollback directory: %s\n' "$AFB_RETIRED_FILEHUB"
printf 'retired_regular_file_count=%s\n' "${#afb_files[@]}"
printf 'retired_aggregate_sha256=%s\n' "$aggregate_hash"
stat -c '%A %U:%G %s %n' "$AFB_RETIRED_FILEHUB"

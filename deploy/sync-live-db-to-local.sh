#!/usr/bin/env bash

# Replaces only the local XAMPP alanfullbeard database with the reviewed
# production export, converts canonical production URLs to localhost, and
# restores the pre-sync local export automatically if any write step fails.

set -Eeuo pipefail

readonly AFB_PROJECT_ROOT="/opt/lampp/htdocs/alanfullbeard"
readonly AFB_SYNC_ROOT="/home/alan/Projects/alanfullbeard-db-sync-20260729"
readonly AFB_LIVE_DUMP="${AFB_SYNC_ROOT}/alanfullbeard-live.sql"
readonly AFB_LOCAL_BACKUP="${AFB_SYNC_ROOT}/alanfullbeard-local-before-sync.sql"
readonly AFB_LIVE_DUMP_SHA256="543f9f4b3d1aae1f3969b99137641ca4ab68f9aa379809d349e23e41ca8e1de0"
readonly AFB_LOCAL_BACKUP_SHA256="1283c1cf6402b2ce73dd9193136951ac564b4217808ace1b52f931815ab8825c"
readonly AFB_LIVE_URL="https://alanfullbeard.com"
readonly AFB_LOCAL_URL="http://localhost/alanfullbeard"

afb_mode="${1:-}"
if [[ -n "$afb_mode" && "$afb_mode" != "--dry-run" ]]; then
    printf 'Usage: %s [--dry-run]\n' "$0" >&2
    exit 2
fi

export PATH="/opt/lampp/bin:${PATH}"

cd "$AFB_PROJECT_ROOT"

if [[ "$PWD" != "$AFB_PROJECT_ROOT" ]]; then
    printf 'Refusing sync: unexpected project root: %s\n' "$PWD" >&2
    exit 1
fi

grep -Fq "define( 'DB_NAME', 'alanfullbeard_wp' );" wp-config.php
grep -Fq "define( 'DB_HOST', '127.0.0.1:3306' );" wp-config.php
grep -Fq "\$table_prefix = 'wp_';" wp-config.php

if [[ ! -f "$AFB_LIVE_DUMP" || ! -f "$AFB_LOCAL_BACKUP" ]]; then
    printf 'Refusing sync: one or both reviewed database exports are missing.\n' >&2
    exit 1
fi

if [[ "$(sha256sum "$AFB_LIVE_DUMP" | cut -d ' ' -f 1)" != "$AFB_LIVE_DUMP_SHA256" ]]; then
    printf 'Refusing sync: production export checksum changed.\n' >&2
    exit 1
fi

if [[ "$(sha256sum "$AFB_LOCAL_BACKUP" | cut -d ' ' -f 1)" != "$AFB_LOCAL_BACKUP_SHA256" ]]; then
    printf 'Refusing sync: local rollback export checksum changed.\n' >&2
    exit 1
fi

current_home="$(wp option get home)"
current_siteurl="$(wp option get siteurl)"
if [[ "$current_home" != "$AFB_LOCAL_URL" || "$current_siteurl" != "$AFB_LOCAL_URL" ]]; then
    printf 'Refusing sync: current WordPress URLs do not identify the expected local site.\n' >&2
    exit 1
fi

if [[ "$afb_mode" == "--dry-run" ]]; then
    printf 'Dry run passed; no database writes performed.\n'
    printf 'target_database=alanfullbeard_wp\n'
    printf 'target_host=127.0.0.1:3306\n'
    printf 'current_home=%s\n' "$current_home"
    printf 'current_siteurl=%s\n' "$current_siteurl"
    exit 0
fi

import_started=0
rollback() {
    original_status=$?
    trap - ERR INT TERM

    if [[ "$import_started" == "1" ]]; then
        printf 'Sync failed; restoring the pre-sync local database export.\n' >&2
        if wp db import "$AFB_LOCAL_BACKUP"; then
            wp cache flush >/dev/null
            printf 'Local database rollback completed.\n' >&2
        else
            printf 'CRITICAL: automatic local database rollback failed.\n' >&2
        fi
    fi

    exit "$original_status"
}
trap rollback ERR INT TERM

import_started=1
wp db import "$AFB_LIVE_DUMP"

wp search-replace \
    "$AFB_LIVE_URL" \
    "$AFB_LOCAL_URL" \
    --all-tables-with-prefix \
    --skip-columns=guid \
    --precise \
    --report-changed-only

synced_home="$(wp option get home)"
synced_siteurl="$(wp option get siteurl)"
if [[ "$synced_home" != "$AFB_LOCAL_URL" || "$synced_siteurl" != "$AFB_LOCAL_URL" ]]; then
    printf 'Sync verification failed: localhost URLs were not established.\n' >&2
    exit 1
fi

wp db check
wp cache flush

import_started=0
trap - ERR INT TERM

printf 'Local database sync completed.\n'
printf 'home=%s\n' "$synced_home"
printf 'siteurl=%s\n' "$synced_siteurl"

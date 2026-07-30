#!/usr/bin/env bash

# Reduces the active HostGator PHP/WordPress upload ceiling to 10 MB.
# The script is hash-guarded against the 2026-07-29 read-only audit, creates
# private rollback copies, and restores both changed files if validation fails.

set -Eeuo pipefail

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_HTACCESS="${AFB_WEB_ROOT}/.htaccess"
readonly AFB_USER_INI="${AFB_WEB_ROOT}/.user.ini"
readonly AFB_PHP_INI="${AFB_WEB_ROOT}/php.ini"
readonly AFB_EXPECTED_HTACCESS_SHA256="52a9b6f0bef8ffdee6ddbe09c4ac20140008a46caa7e623d39d3a233417802bc"
readonly AFB_EXPECTED_USER_INI_SHA256="5262c10a61a2dbfe591782f6fcf695ad8c24d8352cfde25a238b604174b8a0d0"
readonly AFB_EXPECTED_PHP_INI_SHA256="f98f187449d57c69365529ce80205355e91eb37ac9a23dd6ee46aabe0a785f26"
readonly AFB_BACKUP_DIR="${AFB_ACCOUNT_ROOT}/security-hardening-backups/upload-limit-configs-20260729"
readonly AFB_HTACCESS_BACKUP="${AFB_BACKUP_DIR}/public_html.htaccess.before"
readonly AFB_USER_INI_BACKUP="${AFB_BACKUP_DIR}/public_html.user.ini.before"
readonly AFB_PHP_INI_BACKUP="${AFB_BACKUP_DIR}/public_html.php.ini.before"
readonly AFB_USER_INI_CANDIDATE="${AFB_BACKUP_DIR}/public_html.user.ini.candidate"
readonly AFB_PHP_INI_CANDIDATE="${AFB_BACKUP_DIR}/public_html.php.ini.candidate"

afb_writes_started=false

current_hash() {
    sha256sum "$1" | cut -d ' ' -f 1
}

assert_hash() {
    local file="$1"
    local expected="$2"
    local actual

    actual="$(current_hash "$file")"
    if [[ "$actual" != "$expected" ]]; then
        printf 'Refusing change: %s hash is %s, expected %s\n' \
            "$file" \
            "$actual" \
            "$expected" >&2
        return 1
    fi
}

count_matches() {
    local pattern="$1"
    local file="$2"

    grep -cE "$pattern" "$file" || true
}

assert_count() {
    local expected="$1"
    local pattern="$2"
    local file="$3"
    local actual

    actual="$(count_matches "$pattern" "$file")"
    if [[ "$actual" != "$expected" ]]; then
        printf 'Validation failed: expected %s match(es) in %s, found %s\n' \
            "$expected" \
            "$file" \
            "$actual" >&2
        return 1
    fi
}

rollback() {
    local status="$1"

    trap - ERR INT TERM
    set +e

    if [[ "$afb_writes_started" == true ]]; then
        cp -p "$AFB_USER_INI_BACKUP" "$AFB_USER_INI"
        cp -p "$AFB_PHP_INI_BACKUP" "$AFB_PHP_INI"
        printf 'Apply failed; restored .user.ini and php.ini from %s\n' \
            "$AFB_BACKUP_DIR" >&2
    fi

    exit "$status"
}

trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM

for afb_file in "$AFB_HTACCESS" "$AFB_USER_INI" "$AFB_PHP_INI"; do
    if [[ ! -f "$afb_file" ]]; then
        printf 'Refusing change: missing %s\n' "$afb_file" >&2
        exit 1
    fi
done

assert_hash "$AFB_HTACCESS" "$AFB_EXPECTED_HTACCESS_SHA256"
assert_hash "$AFB_USER_INI" "$AFB_EXPECTED_USER_INI_SHA256"
assert_hash "$AFB_PHP_INI" "$AFB_EXPECTED_PHP_INI_SHA256"

if [[ -e "$AFB_BACKUP_DIR" ]]; then
    printf 'Refusing change: rollback directory already exists: %s\n' \
        "$AFB_BACKUP_DIR" >&2
    exit 1
fi

assert_count 2 \
    '^[[:space:]]*php_value post_max_size 12M[[:space:]]*$' \
    "$AFB_HTACCESS"
assert_count 2 \
    '^[[:space:]]*php_value upload_max_filesize 10M[[:space:]]*$' \
    "$AFB_HTACCESS"

for afb_ini in "$AFB_USER_INI" "$AFB_PHP_INI"; do
    assert_count 1 \
        '^[[:space:]]*post_max_size[[:space:]]*=[[:space:]]*516M[[:space:]]*$' \
        "$afb_ini"
    assert_count 1 \
        '^[[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*512M[[:space:]]*$' \
        "$afb_ini"
done

umask 077
mkdir -p "$AFB_BACKUP_DIR"
cp -p "$AFB_HTACCESS" "$AFB_HTACCESS_BACKUP"
cp -p "$AFB_USER_INI" "$AFB_USER_INI_BACKUP"
cp -p "$AFB_PHP_INI" "$AFB_PHP_INI_BACKUP"

sed \
    -e 's/^\([[:space:]]*post_max_size[[:space:]]*=[[:space:]]*\)516M[[:space:]]*$/\112M/' \
    -e 's/^\([[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*\)512M[[:space:]]*$/\110M/' \
    "$AFB_USER_INI" > "$AFB_USER_INI_CANDIDATE"

sed \
    -e 's/^\([[:space:]]*post_max_size[[:space:]]*=[[:space:]]*\)516M[[:space:]]*$/\112M/' \
    -e 's/^\([[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*\)512M[[:space:]]*$/\110M/' \
    "$AFB_PHP_INI" > "$AFB_PHP_INI_CANDIDATE"

chmod --reference="$AFB_USER_INI" "$AFB_USER_INI_CANDIDATE"
chmod --reference="$AFB_PHP_INI" "$AFB_PHP_INI_CANDIDATE"

for afb_candidate in "$AFB_USER_INI_CANDIDATE" "$AFB_PHP_INI_CANDIDATE"; do
    assert_count 1 \
        '^[[:space:]]*post_max_size[[:space:]]*=[[:space:]]*12M[[:space:]]*$' \
        "$afb_candidate"
    assert_count 1 \
        '^[[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*10M[[:space:]]*$' \
        "$afb_candidate"
    assert_count 0 \
        '^[[:space:]]*(post_max_size[[:space:]]*=[[:space:]]*516M|upload_max_filesize[[:space:]]*=[[:space:]]*512M)[[:space:]]*$' \
        "$afb_candidate"
done

afb_writes_started=true
mv "$AFB_USER_INI_CANDIDATE" "$AFB_USER_INI"
mv "$AFB_PHP_INI_CANDIDATE" "$AFB_PHP_INI"

assert_count 1 \
    '^[[:space:]]*post_max_size[[:space:]]*=[[:space:]]*12M[[:space:]]*$' \
    "$AFB_USER_INI"
assert_count 1 \
    '^[[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*10M[[:space:]]*$' \
    "$AFB_USER_INI"
assert_count 1 \
    '^[[:space:]]*post_max_size[[:space:]]*=[[:space:]]*12M[[:space:]]*$' \
    "$AFB_PHP_INI"
assert_count 1 \
    '^[[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*10M[[:space:]]*$' \
    "$AFB_PHP_INI"

afb_writes_started=false
trap - ERR INT TERM

printf 'Applied the 10 MB upload ceiling.\n'
printf 'Rollback directory: %s\n\n' "$AFB_BACKUP_DIR"

sha256sum \
    "$AFB_HTACCESS" \
    "$AFB_USER_INI" \
    "$AFB_PHP_INI" \
    "$AFB_HTACCESS_BACKUP" \
    "$AFB_USER_INI_BACKUP" \
    "$AFB_PHP_INI_BACKUP"

printf '\nActive PHP limits in configuration files:\n'
grep -nHE \
    '^[[:space:]]*(post_max_size|upload_max_filesize)[[:space:]]*=' \
    "$AFB_USER_INI" \
    "$AFB_PHP_INI"

printf '\nWordPress CLI calculation:\n'
cd "$AFB_WEB_ROOT"
wp eval 'printf("upload_max_filesize=%s\npost_max_size=%s\nwp_max_upload_size_bytes=%d\nwp_max_upload_size_display=%s\n", (string) ini_get("upload_max_filesize"), (string) ini_get("post_max_size"), (int) wp_max_upload_size(), size_format((int) wp_max_upload_size()));'

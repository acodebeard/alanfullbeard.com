#!/usr/bin/env bash

# Read-only preflight for reducing the HostGator PHP/WordPress upload ceiling.
# It validates the three active configuration files and prints the exact
# proposed changes without creating or modifying any server-side file.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_HTACCESS="${AFB_WEB_ROOT}/.htaccess"
readonly AFB_USER_INI="${AFB_WEB_ROOT}/.user.ini"
readonly AFB_PHP_INI="${AFB_WEB_ROOT}/php.ini"

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
        printf 'Preflight failed: expected %s match(es) in %s, found %s\n' \
            "$expected" \
            "$file" \
            "$actual" >&2
        exit 1
    fi
}

for afb_file in "$AFB_HTACCESS" "$AFB_USER_INI" "$AFB_PHP_INI"; do
    if [[ ! -f "$afb_file" ]]; then
        printf 'Preflight failed: missing %s\n' "$afb_file" >&2
        exit 1
    fi
done

# .htaccess is already at the desired limit and should remain unchanged.
assert_count 2 \
    '^[[:space:]]*php_value post_max_size 12M[[:space:]]*$' \
    "$AFB_HTACCESS"
assert_count 2 \
    '^[[:space:]]*php_value upload_max_filesize 10M[[:space:]]*$' \
    "$AFB_HTACCESS"
assert_count 0 \
    '^[[:space:]]*php_value (post_max_size 516M|upload_max_filesize 512M)[[:space:]]*$' \
    "$AFB_HTACCESS"

for afb_ini in "$AFB_USER_INI" "$AFB_PHP_INI"; do
    assert_count 1 \
        '^[[:space:]]*post_max_size[[:space:]]*=[[:space:]]*516M[[:space:]]*$' \
        "$afb_ini"
    assert_count 1 \
        '^[[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*512M[[:space:]]*$' \
        "$afb_ini"
    assert_count 0 \
        '^[[:space:]]*(post_max_size[[:space:]]*=[[:space:]]*12M|upload_max_filesize[[:space:]]*=[[:space:]]*10M)[[:space:]]*$' \
        "$afb_ini"
done

printf 'Current file hashes:\n'
sha256sum "$AFB_HTACCESS" "$AFB_USER_INI" "$AFB_PHP_INI"

for afb_ini in "$AFB_USER_INI" "$AFB_PHP_INI"; do
    printf '\nProposed diff for %s:\n' "$afb_ini"
    diff -u \
        "$afb_ini" \
        <(
            sed \
                -e 's/^\([[:space:]]*post_max_size[[:space:]]*=[[:space:]]*\)516M[[:space:]]*$/\112M/' \
                -e 's/^\([[:space:]]*upload_max_filesize[[:space:]]*=[[:space:]]*\)512M[[:space:]]*$/\110M/' \
                "$afb_ini"
        ) || true
done

printf '\nDry run complete. No files or database values were changed.\n'

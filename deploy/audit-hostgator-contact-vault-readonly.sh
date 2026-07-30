#!/usr/bin/env bash

# Read-only preflight for encrypted contact storage on alanfullbeard.com.
# It prints no contact content, addresses, credentials, keys, or database rows.

set -u

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"

printf '===== PHP Sodium capability =====\n'
declare -a afb_php_candidates=()

if command -v php >/dev/null 2>&1; then
    afb_php_candidates+=("$(command -v php)")
fi

for afb_php in \
    /opt/cpanel/ea-php*/root/usr/bin/php \
    /usr/local/bin/php \
    /usr/bin/php; do
    [[ -x "$afb_php" ]] || continue
    afb_php_candidates+=("$afb_php")
done

declare -A afb_seen_php=()
for afb_php in "${afb_php_candidates[@]}"; do
    [[ -n "${afb_seen_php[$afb_php]:-}" ]] && continue
    afb_seen_php["$afb_php"]=1

    "$afb_php" -r '
        printf(
            "%s version=%s sodium_extension=%s xchacha=%s sodium_library=%s\n",
            PHP_BINARY,
            PHP_VERSION,
            extension_loaded("sodium") ? "yes" : "no",
            function_exists("sodium_crypto_aead_xchacha20poly1305_ietf_encrypt")
                ? "yes"
                : "no",
            defined("SODIUM_LIBRARY_VERSION")
                ? SODIUM_LIBRARY_VERSION
                : "unavailable"
        );
    '
done

printf '\n===== Active web PHP handler =====\n'
grep -nE 'AddHandler application/x-httpd-ea-php|SetHandler.*php' \
    "$AFB_WEB_ROOT/.htaccess" 2>/dev/null || true

printf '\n===== WordPress contact runtime =====\n'
cd "$AFB_WEB_ROOT" || exit 1
wp core version
wp plugin get contact-form-7 --fields=name,status,version --format=json
wp plugin get flamingo --fields=name,status,version --format=json
wp plugin get cf7-antispam --fields=name,status,version --format=json

printf 'inbound_total='
wp post list \
    --post_type=flamingo_inbound \
    --post_status=any \
    --format=count
printf 'inbound_inbox='
wp post list \
    --post_type=flamingo_inbound \
    --post_status=publish \
    --format=count
printf 'inbound_spam='
wp post list \
    --post_type=flamingo_inbound \
    --post_status=flamingo-spam \
    --format=count
printf 'inbound_trash='
wp post list \
    --post_type=flamingo_inbound \
    --post_status=trash \
    --format=count
printf 'contact_total='
wp post list \
    --post_type=flamingo_contact \
    --post_status=any \
    --format=count

printf '\n===== Existing vault boundary =====\n'
if [[ -f "$AFB_WEB_ROOT/wp-content/mu-plugins/alanfullbeard-contact-vault.php" ]]; then
    printf 'vault_plugin=present hash='
    sha256sum \
        "$AFB_WEB_ROOT/wp-content/mu-plugins/alanfullbeard-contact-vault.php" |
        awk '{print $1}'
else
    printf 'vault_plugin=absent\n'
fi

if grep -q 'ALANFULLBEARD_CONTACT_VAULT_KEY' "$AFB_WEB_ROOT/wp-config.php"; then
    printf 'vault_key_loader=present\n'
else
    printf 'vault_key_loader=absent\n'
fi

if [[ -e "$AFB_ACCOUNT_ROOT/private-config/contact-vault-key.php" ]]; then
    stat -c 'vault_key_file=%A %U:%G %s %n' \
        "$AFB_ACCOUNT_ROOT/private-config/contact-vault-key.php"
else
    printf 'vault_key_file=absent\n'
fi

printf '\nread-only encrypted contact-vault preflight completed\n'

#!/usr/bin/env bash

# Restores only the database state immediately before the form/privacy update.
# Run only with a separate rollback approval.

set -euo pipefail

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_STAGE_ROOT="${AFB_ACCOUNT_ROOT}/security-staging/contact-vault-20260728"
readonly AFB_BACKUP_ROOT="${AFB_ACCOUNT_ROOT}/security-hardening-backups/contact-vault-20260728"
readonly AFB_KEY_FILE="${AFB_ACCOUNT_ROOT}/private-config/contact-vault-key.php"
readonly AFB_PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
readonly AFB_DATABASE_BACKUP="${AFB_BACKUP_ROOT}/database-before-form-policy.sql.afbenc"

cd "$AFB_WEB_ROOT"

if ! afb_mysql="$(command -v mysql)"; then
    printf 'The MySQL client required for rollback is unavailable.\n' >&2
    exit 1
fi

afb_db_name="$(wp config get DB_NAME --type=constant)"
afb_db_user="$(wp config get DB_USER --type=constant)"
afb_db_password="$(wp config get DB_PASSWORD --type=constant)"
afb_db_host_value="$(wp config get DB_HOST --type=constant)"
afb_db_host="${afb_db_host_value%%:*}"
afb_db_host_suffix=""

if [[ "$afb_db_host_value" == *:* ]]; then
    afb_db_host_suffix="${afb_db_host_value#*:}"
fi

afb_mysql_args=(
    "--host=$afb_db_host"
    "--user=$afb_db_user"
)

if [[ "$afb_db_host_suffix" == /* ]]; then
    afb_mysql_args+=("--socket=$afb_db_host_suffix")
elif [[ "$afb_db_host_suffix" =~ ^[0-9]+$ ]]; then
    afb_mysql_args+=("--port=$afb_db_host_suffix")
elif [[ -n "$afb_db_host_suffix" ]]; then
    printf 'Unsupported DB_HOST value: %s\n' "$afb_db_host_value" >&2
    exit 1
fi

sha256sum -c \
    "$AFB_BACKUP_ROOT/database-before-form-policy.sql.afbenc.sha256"
"$AFB_PHP" \
    "$AFB_STAGE_ROOT/contact-vault-decrypt-backup.php" \
    "$AFB_KEY_FILE" \
    < "$AFB_DATABASE_BACKUP" \
    > /dev/null

"$AFB_PHP" \
    "$AFB_STAGE_ROOT/contact-vault-decrypt-backup.php" \
    "$AFB_KEY_FILE" \
    < "$AFB_DATABASE_BACKUP" |
    MYSQL_PWD="$afb_db_password" \
        "$afb_mysql" \
        "${afb_mysql_args[@]}" \
        "$afb_db_name"

wp cache flush

printf 'Rolled back the contact-form and Privacy Policy update.\n'

#!/usr/bin/env bash

# Activates the administrator-only WordPress FileHub on HostGator from a
# private, pre-staged source directory. No database or wp-config changes occur.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_MU_ROOT="${AFB_WEB_ROOT}/wp-content/mu-plugins"
readonly AFB_STAGE_ROOT="/home2/afullbeard/deployments/filehub-admin-20260728"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/filehub-admin-20260728"
readonly AFB_PLUGIN_SOURCE="${AFB_STAGE_ROOT}/alanfullbeard-filehub.php"
readonly AFB_CONFIG_SOURCE="${AFB_STAGE_ROOT}/hostgator-filehub-config.php"
readonly AFB_PLUGIN_TARGET="${AFB_MU_ROOT}/alanfullbeard-filehub.php"
readonly AFB_CONFIG_TARGET="${AFB_MU_ROOT}/000-alanfullbeard-filehub-config.php"
readonly AFB_PLUGIN_SHA256="58ae518f055e6adef781866b4baa22d64f053084067002b53d77acf638545321"
readonly AFB_CONFIG_SHA256="b3d16799717bafa7092b77cc1b65e00a4303710d3c4d88569ce46ee8a6f75e8f"

for afb_path in "$AFB_PLUGIN_TARGET" "$AFB_CONFIG_TARGET" "$AFB_BACKUP_ROOT"; do
    if [[ -e "$afb_path" ]]; then
        printf 'Refusing activation: target already exists: %s\n' "$afb_path" >&2
        exit 1
    fi
done

plugin_hash="$(sha256sum "$AFB_PLUGIN_SOURCE" | cut -d ' ' -f 1)"
config_hash="$(sha256sum "$AFB_CONFIG_SOURCE" | cut -d ' ' -f 1)"
if [[ "$plugin_hash" != "$AFB_PLUGIN_SHA256" || "$config_hash" != "$AFB_CONFIG_SHA256" ]]; then
    printf 'Refusing activation: staged FileHub hashes do not match the reviewed sources\n' >&2
    exit 1
fi

php -l "$AFB_PLUGIN_SOURCE" >/dev/null
php -l "$AFB_CONFIG_SOURCE" >/dev/null

umask 077
mkdir -p "$AFB_BACKUP_ROOT"

rollback_needed=1
rollback() {
    if [[ "$rollback_needed" != "1" ]]; then
        return
    fi

    if [[ -e "$AFB_PLUGIN_TARGET" ]]; then
        mv "$AFB_PLUGIN_TARGET" "${AFB_BACKUP_ROOT}/alanfullbeard-filehub.php.failed"
    fi
    if [[ -e "$AFB_CONFIG_TARGET" ]]; then
        mv "$AFB_CONFIG_TARGET" "${AFB_BACKUP_ROOT}/000-alanfullbeard-filehub-config.php.failed"
    fi
}
trap rollback EXIT

cp "$AFB_CONFIG_SOURCE" "$AFB_CONFIG_TARGET"
cp "$AFB_PLUGIN_SOURCE" "$AFB_PLUGIN_TARGET"
chmod 0644 "$AFB_CONFIG_TARGET" "$AFB_PLUGIN_TARGET"

cd "$AFB_WEB_ROOT"
wp eval '
$expectedRoot = "/home2/afullbeard/filehub_data";
$loaded = function_exists("alanfullbeard_filehub_base_path")
    && has_action("admin_post_alanfullbeard_filehub_upload")
    && has_action("admin_post_alanfullbeard_filehub_download")
    && has_action("admin_post_alanfullbeard_filehub_rename")
    && has_action("admin_post_alanfullbeard_filehub_move")
    && has_action("admin_post_alanfullbeard_filehub_delete");

if (! $loaded || alanfullbeard_filehub_base_path() !== $expectedRoot) {
    fwrite(STDERR, "FileHub load verification failed.\n");
    exit(1);
}

printf(
    "filehub_loaded=yes\nfilehub_root=expected-private-root\nfilehub_capability=%s\nfilehub_max_upload_bytes=%s\n",
    alanfullbeard_filehub_capability(),
    (string) ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES
);
'

rollback_needed=0
trap - EXIT

printf 'Activated HostGator WordPress administrator FileHub.\n'
printf 'Rollback directory reserved at: %s\n' "$AFB_BACKUP_ROOT"
sha256sum "$AFB_CONFIG_TARGET" "$AFB_PLUGIN_TARGET"
stat -c '%A %U:%G %s %n' "$AFB_CONFIG_TARGET" "$AFB_PLUGIN_TARGET"

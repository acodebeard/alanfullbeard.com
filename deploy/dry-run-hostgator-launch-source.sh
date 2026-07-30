#!/usr/bin/env bash

# Read-only preflight for the source-only alanfullbeard.com launch deployment.
# It verifies that every intended live target is either at the audited
# production hash or already at the reviewed local-main hash. It also confirms
# that live-only keys and configuration will remain outside the payload.

set -euo pipefail

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_STAGE_ROOT="${AFB_ACCOUNT_ROOT}/deployments/launch-source-f807cb1-20260729"
readonly AFB_BACKUP_ROOT="${AFB_ACCOUNT_ROOT}/security-hardening-backups/launch-source-f807cb1-20260729"

readonly -a AFB_EXISTING_FILES=(
    "wp-content/themes/alanfullbeard/blocks/portfolio-card/block.json|4ab3ede38b7c1b86dc2f4ad5496ca93658520b9099bf9b9520aaabe335a7f3b1|54a016b112e04d19cb727f338e25fcf2eecbfc96ab2f8a5c0e3bcd9340c93d2d"
    "wp-content/themes/alanfullbeard/blocks/portfolio-card/editor.css|f957012a407c11644f1e96a59a3d5ce08087181533b3b6153ddccb79aa8f141e|9106d75b7c8b9a043126881723158a01701f32b9aa66ff192206299ede6ff99d"
    "wp-content/themes/alanfullbeard/blocks/portfolio-card/index.js|c765ca95bbcc145c893883230963a29a1f6110e950af7c538ec299e3ee628aec|5d1e0c1104f0ab4520fdc6bc33ee1a3e9d1d107340ee4cc36098a7566a1bed66"
    "wp-content/themes/alanfullbeard/blocks/portfolio-card/style.css|ec87eeb78547862a5dacfa0a80704b09c297bef63b7e0417bed317302fe8e97c|24f0426bf9fa74c6ec0bec10d82db41930ac23e355df1a51c5993ace017ad239"
    "wp-content/themes/alanfullbeard/front-page.php|7ca1ff0b97999ecffabf636d9a39d6d510a255830db2f1954dad008183df919a|ff4a54468bdd27e8577003ca4c0d74dda0011193801a8e7b6008ca1d405e14bf"
    "wp-content/themes/alanfullbeard/functions.php|7817dbb1b612c9461848410d9015eb132598ce6b6bf9ed934ad3bce9213f218c|4f267e94d7d15afe4e720bbde28bffacac2d5bb2fb104945ebc3fdceda6aa496"
    "wp-content/themes/alanfullbeard/inc/portfolio-blocks.php|7a22aa0e1006fd0918bdb9dcd17129313fb1d083519f7234d1fe1ad67163ee1f|3552d7680f4339e2e19b483f64077b9d8a1556c89348da72e59bc95d265fd429"
    "wp-content/themes/alanfullbeard/style.css|3a83c9585e8f7d049fd9b694e1e9f3dbe0575739db11826b75a9e1000b662012|904077b7bad30b667ce5a102ffd1e22fb8ee38d19d652396d4ed625b671aef8f"
    "wp-content/themes/alanfullbeard/style.min.css|e581156f1f066d4edfb0de6717e60de6f02094558adabc857ea11422e58f18ec|e38cf6ae5a1f863c1ac7bec98d96f979ed4dd60b9b2556329fdd38f59088eb06"
    "wp-content/mu-plugins/new-tab-dammit.php|9422eb782641257fd3303671b6f3ed51cea0b9c4159f433e9a0fd8ffc3b8f542|6f4403169c391e9138230ff56fef892e335cc13496bac24ea6ccb5215c8a6858"
    "wp-content/uploads/.htaccess|18c23370be99079bf26246e0c93d84f1f0ec150d88a44f13947b23441e03d6a3|4fe24ca9a21de76bac65e1470125a527633ed8ff568f213d74cd192d1b5187e8"
)

readonly -a AFB_NEW_FILES=(
    "wp-content/themes/alanfullbeard/assets/images/portfolio-screenshot-placeholder.svg|40ebeef2fc520d3f3258d709acec53e9af2ac8542214c10baf147f1186b4d33b"
    "wp-content/themes/alanfullbeard/blocks/portfolio-card/style.min.css|b5aad0d89e490b65c17d63e0c4e7eaab9039e88d253adeb1945fba507a2504f3"
)

current_hash() {
    sha256sum "$1" | cut -d ' ' -f 1
}

assert_ini_value() {
    local afb_file="$1"
    local afb_key="$2"
    local afb_value="$3"
    local afb_count

    afb_count="$(
        grep -cE \
            "^[[:space:]]*${afb_key}[[:space:]]*=[[:space:]]*${afb_value}[[:space:]]*$" \
            "$afb_file" || true
    )"

    if [[ "$afb_count" != "1" ]]; then
        printf 'Preflight failed: expected %s=%s exactly once in %s\n' \
            "$afb_key" \
            "$afb_value" \
            "$afb_file" >&2
        exit 1
    fi
}

printf '===== Target identity =====\n'
hostname
id
printf 'web_root=%s\n' "$AFB_WEB_ROOT"

if [[ ! -d "$AFB_WEB_ROOT" ]]; then
    printf 'Preflight failed: missing web root %s\n' "$AFB_WEB_ROOT" >&2
    exit 1
fi

printf '\n===== Existing deployment targets =====\n'
afb_replace_count=0
afb_current_count=0

for afb_record in "${AFB_EXISTING_FILES[@]}"; do
    IFS='|' read -r afb_relative afb_old_hash afb_new_hash <<< "$afb_record"
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"

    if [[ ! -f "$afb_live_file" ]]; then
        printf 'Preflight failed: missing existing target %s\n' "$afb_live_file" >&2
        exit 1
    fi

    afb_actual_hash="$(current_hash "$afb_live_file")"
    if [[ "$afb_actual_hash" == "$afb_old_hash" ]]; then
        printf 'REPLACE %s\n' "$afb_relative"
        ((afb_replace_count += 1))
    elif [[ "$afb_actual_hash" == "$afb_new_hash" ]]; then
        printf 'CURRENT %s\n' "$afb_relative"
        ((afb_current_count += 1))
    else
        printf 'Preflight failed: unexpected hash for %s: %s\n' \
            "$afb_live_file" \
            "$afb_actual_hash" >&2
        exit 1
    fi
done

printf '\n===== New deployment targets =====\n'
afb_create_count=0

for afb_record in "${AFB_NEW_FILES[@]}"; do
    IFS='|' read -r afb_relative afb_new_hash <<< "$afb_record"
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"

    if [[ ! -e "$afb_live_file" ]]; then
        printf 'CREATE %s\n' "$afb_relative"
        ((afb_create_count += 1))
    elif [[ -f "$afb_live_file" ]] &&
        [[ "$(current_hash "$afb_live_file")" == "$afb_new_hash" ]]; then
        printf 'CURRENT %s\n' "$afb_relative"
        ((afb_current_count += 1))
    else
        printf 'Preflight failed: new target already exists unexpectedly: %s\n' \
            "$afb_live_file" >&2
        exit 1
    fi
done

printf '\n===== Production-only files preserved =====\n'
for afb_private_file in \
    "${AFB_WEB_ROOT}/wp-content/mu-plugins/000-alanfullbeard-contact-vault-key.php" \
    "${AFB_WEB_ROOT}/wp-content/mu-plugins/000-alanfullbeard-filehub-config.php"; do
    if [[ ! -f "$afb_private_file" ]]; then
        printf 'Preflight failed: missing production-only file %s\n' \
            "$afb_private_file" >&2
        exit 1
    fi
    stat -c '%A %U:%G %s %n' "$afb_private_file"
done

printf '\n===== PHP upload configuration =====\n'
for afb_ini in \
    "${AFB_WEB_ROOT}/.user.ini" \
    "${AFB_WEB_ROOT}/php.ini"; do
    if [[ ! -f "$afb_ini" ]]; then
        printf 'Preflight failed: missing %s\n' "$afb_ini" >&2
        exit 1
    fi
    assert_ini_value "$afb_ini" "post_max_size" "12M"
    assert_ini_value "$afb_ini" "upload_max_filesize" "10M"
    grep -nE \
        '^[[:space:]]*(post_max_size|upload_max_filesize)[[:space:]]*=' \
        "$afb_ini"
done

printf '\n===== Required WordPress pages =====\n'
cd "$AFB_WEB_ROOT"
for afb_slug in contact privacy-policy portfolio; do
    afb_page_id="$(
        wp post list \
            --post_type=page \
            --post_status=any \
            --name="$afb_slug" \
            --field=ID \
            --format=ids
    )"

    if [[ -z "$afb_page_id" ]]; then
        printf 'Preflight failed: missing WordPress page slug %s\n' \
            "$afb_slug" >&2
        exit 1
    fi

    printf '%s page_id=%s\n' "$afb_slug" "$afb_page_id"
done

printf '\n===== Public route status =====\n'
for afb_url in \
    "https://alanfullbeard.com/" \
    "https://alanfullbeard.com/contact/" \
    "https://alanfullbeard.com/privacy-policy/" \
    "https://alanfullbeard.com/portfolio/"; do
    afb_http_status="$(
        curl \
            --silent \
            --show-error \
            --location \
            --max-time 20 \
            --output /dev/null \
            --write-out '%{http_code}' \
            "$afb_url"
    )"

    if [[ "$afb_http_status" != "200" ]]; then
        printf 'Preflight failed: %s returned HTTP %s\n' \
            "$afb_url" \
            "$afb_http_status" >&2
        exit 1
    fi

    printf '%s %s\n' "$afb_http_status" "$afb_url"
done

printf '\n===== Planned boundaries =====\n'
printf 'replace=%s create=%s already_current=%s\n' \
    "$afb_replace_count" \
    "$afb_create_count" \
    "$afb_current_count"
printf 'stage_root=%s\n' "$AFB_STAGE_ROOT"
printf 'rollback_root=%s\n' "$AFB_BACKUP_ROOT"
printf 'database_changes=0 uploads_content_changes=0 production_key_changes=0\n'

if [[ -e "$AFB_BACKUP_ROOT" ]]; then
    printf 'Preflight failed: rollback root already exists: %s\n' \
        "$AFB_BACKUP_ROOT" >&2
    exit 1
fi

printf '\nDry run passed. No files or database values were changed.\n'

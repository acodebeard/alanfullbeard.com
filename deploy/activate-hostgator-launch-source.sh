#!/usr/bin/env bash

# Activates the reviewed source-only alanfullbeard.com launch payload on
# HostGator. The script is hash-guarded, preserves production-only keys and
# configuration, creates private rollback copies, and restores changed targets
# automatically if validation or public health checks fail.

set -Eeuo pipefail

readonly AFB_ACCOUNT_ROOT="/home2/afullbeard"
readonly AFB_WEB_ROOT="${AFB_ACCOUNT_ROOT}/public_html"
readonly AFB_STAGE_ROOT="${AFB_ACCOUNT_ROOT}/deployments/launch-source-f807cb1-20260729"
readonly AFB_CANDIDATE_ROOT="${AFB_STAGE_ROOT}/candidate"
readonly AFB_BACKUP_ROOT="${AFB_ACCOUNT_ROOT}/security-hardening-backups/launch-source-f807cb1-20260729"
readonly AFB_BEFORE_ROOT="${AFB_BACKUP_ROOT}/before"
readonly AFB_FAILED_NEW_ROOT="${AFB_BACKUP_ROOT}/failed-created"

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

declare -a afb_replace_files=()
declare -a afb_create_files=()
declare -a afb_applied_existing=()
declare -a afb_applied_new=()
afb_writes_started=false

current_hash() {
    sha256sum "$1" | cut -d ' ' -f 1
}

assert_hash() {
    local afb_file="$1"
    local afb_expected="$2"
    local afb_label="$3"
    local afb_actual

    afb_actual="$(current_hash "$afb_file")"
    if [[ "$afb_actual" != "$afb_expected" ]]; then
        printf 'Validation failed: %s hash is %s, expected %s\n' \
            "$afb_label" \
            "$afb_actual" \
            "$afb_expected" >&2
        return 1
    fi
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
        printf 'Validation failed: expected %s=%s exactly once in %s\n' \
            "$afb_key" \
            "$afb_value" \
            "$afb_file" >&2
        return 1
    fi
}

rollback() {
    local afb_status="$1"
    local afb_relative
    local afb_live_file
    local afb_backup_file
    local afb_failed_file

    trap - ERR INT TERM
    set +e

    if [[ "$afb_writes_started" == true ]]; then
        for afb_relative in "${afb_applied_new[@]}"; do
            afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
            afb_failed_file="${AFB_FAILED_NEW_ROOT}/${afb_relative}"

            if [[ -e "$afb_live_file" ]]; then
                mkdir -p "$(dirname "$afb_failed_file")"
                mv "$afb_live_file" "$afb_failed_file"
            fi
        done

        for afb_relative in "${afb_applied_existing[@]}"; do
            afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
            afb_backup_file="${AFB_BEFORE_ROOT}/${afb_relative}"

            if [[ -f "$afb_backup_file" ]]; then
                cp -p "$afb_backup_file" "$afb_live_file"
            fi
        done

        printf 'Activation failed; restored live targets from %s\n' \
            "$AFB_BACKUP_ROOT" >&2
    fi

    exit "$afb_status"
}

trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM

printf '===== Target identity =====\n'
hostname
id
printf 'web_root=%s\n' "$AFB_WEB_ROOT"
printf 'candidate_root=%s\n' "$AFB_CANDIDATE_ROOT"

if [[ ! -d "$AFB_WEB_ROOT" || ! -d "$AFB_CANDIDATE_ROOT" ]]; then
    printf 'Refusing activation: web root or candidate root is missing\n' >&2
    exit 1
fi

if [[ -e "$AFB_BACKUP_ROOT" ]]; then
    printf 'Refusing activation: rollback root already exists: %s\n' \
        "$AFB_BACKUP_ROOT" >&2
    exit 1
fi

for afb_private_file in \
    "${AFB_WEB_ROOT}/wp-content/mu-plugins/000-alanfullbeard-contact-vault-key.php" \
    "${AFB_WEB_ROOT}/wp-content/mu-plugins/000-alanfullbeard-filehub-config.php"; do
    if [[ ! -f "$afb_private_file" ]]; then
        printf 'Refusing activation: missing production-only file %s\n' \
            "$afb_private_file" >&2
        exit 1
    fi
done

for afb_ini in \
    "${AFB_WEB_ROOT}/.user.ini" \
    "${AFB_WEB_ROOT}/php.ini"; do
    assert_ini_value "$afb_ini" "post_max_size" "12M"
    assert_ini_value "$afb_ini" "upload_max_filesize" "10M"
done

printf '\n===== Candidate and live preflight =====\n'
for afb_record in "${AFB_EXISTING_FILES[@]}"; do
    IFS='|' read -r afb_relative afb_old_hash afb_new_hash <<< "$afb_record"
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
    afb_candidate_file="${AFB_CANDIDATE_ROOT}/${afb_relative}"

    if [[ ! -f "$afb_live_file" || ! -f "$afb_candidate_file" ]]; then
        printf 'Refusing activation: missing live or candidate file for %s\n' \
            "$afb_relative" >&2
        exit 1
    fi

    assert_hash "$afb_candidate_file" "$afb_new_hash" "candidate ${afb_relative}"
    afb_actual_hash="$(current_hash "$afb_live_file")"

    if [[ "$afb_actual_hash" == "$afb_old_hash" ]]; then
        afb_replace_files+=("$afb_relative")
        printf 'REPLACE %s\n' "$afb_relative"
    elif [[ "$afb_actual_hash" == "$afb_new_hash" ]]; then
        printf 'CURRENT %s\n' "$afb_relative"
    else
        printf 'Refusing activation: unexpected live hash for %s: %s\n' \
            "$afb_relative" \
            "$afb_actual_hash" >&2
        exit 1
    fi
done

for afb_record in "${AFB_NEW_FILES[@]}"; do
    IFS='|' read -r afb_relative afb_new_hash <<< "$afb_record"
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
    afb_candidate_file="${AFB_CANDIDATE_ROOT}/${afb_relative}"

    if [[ ! -f "$afb_candidate_file" ]]; then
        printf 'Refusing activation: missing candidate file %s\n' \
            "$afb_candidate_file" >&2
        exit 1
    fi

    assert_hash "$afb_candidate_file" "$afb_new_hash" "candidate ${afb_relative}"

    if [[ ! -e "$afb_live_file" ]]; then
        afb_create_files+=("$afb_relative")
        printf 'CREATE %s\n' "$afb_relative"
    elif [[ -f "$afb_live_file" ]] &&
        [[ "$(current_hash "$afb_live_file")" == "$afb_new_hash" ]]; then
        printf 'CURRENT %s\n' "$afb_relative"
    else
        printf 'Refusing activation: new target exists unexpectedly: %s\n' \
            "$afb_live_file" >&2
        exit 1
    fi
done

printf '\n===== Candidate PHP syntax =====\n'
for afb_relative in \
    "wp-content/themes/alanfullbeard/front-page.php" \
    "wp-content/themes/alanfullbeard/functions.php" \
    "wp-content/themes/alanfullbeard/inc/portfolio-blocks.php" \
    "wp-content/mu-plugins/new-tab-dammit.php"; do
    php -l "${AFB_CANDIDATE_ROOT}/${afb_relative}"
done

if ! grep -Fq \
    '<FilesMatch "\.(?:php[0-9]?|phtml|phar)(?:\.|$)">' \
    "${AFB_CANDIDATE_ROOT}/wp-content/uploads/.htaccess"; then
    printf 'Refusing activation: candidate uploads guard is incomplete\n' >&2
    exit 1
fi

printf '\n===== Private rollback backup =====\n'
umask 077
mkdir -p "$AFB_BEFORE_ROOT" "$AFB_FAILED_NEW_ROOT"

for afb_relative in "${afb_replace_files[@]}"; do
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
    afb_backup_file="${AFB_BEFORE_ROOT}/${afb_relative}"
    mkdir -p "$(dirname "$afb_backup_file")"
    cp -p "$afb_live_file" "$afb_backup_file"
    assert_hash \
        "$afb_backup_file" \
        "$(current_hash "$afb_live_file")" \
        "rollback ${afb_relative}"
done

printf 'rollback_root=%s\n' "$AFB_BACKUP_ROOT"

afb_writes_started=true

printf '\n===== Activation =====\n'
for afb_relative in "${afb_replace_files[@]}"; do
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
    afb_candidate_file="${AFB_CANDIDATE_ROOT}/${afb_relative}"

    chmod --reference="$afb_live_file" "$afb_candidate_file"
    mv "$afb_candidate_file" "$afb_live_file"
    afb_applied_existing+=("$afb_relative")
    printf 'REPLACED %s\n' "$afb_relative"
done

for afb_relative in "${afb_create_files[@]}"; do
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
    afb_candidate_file="${AFB_CANDIDATE_ROOT}/${afb_relative}"

    mkdir -p "$(dirname "$afb_live_file")"
    chmod 0644 "$afb_candidate_file"
    mv "$afb_candidate_file" "$afb_live_file"
    afb_applied_new+=("$afb_relative")
    printf 'CREATED %s\n' "$afb_relative"
done

printf '\n===== Activated hashes =====\n'
for afb_record in "${AFB_EXISTING_FILES[@]}"; do
    IFS='|' read -r afb_relative afb_old_hash afb_new_hash <<< "$afb_record"
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
    assert_hash "$afb_live_file" "$afb_new_hash" "activated ${afb_relative}"
    printf '%s  %s\n' "$afb_new_hash" "$afb_relative"
done

for afb_record in "${AFB_NEW_FILES[@]}"; do
    IFS='|' read -r afb_relative afb_new_hash <<< "$afb_record"
    afb_live_file="${AFB_WEB_ROOT}/${afb_relative}"
    assert_hash "$afb_live_file" "$afb_new_hash" "activated ${afb_relative}"
    printf '%s  %s\n' "$afb_new_hash" "$afb_relative"
done

printf '\n===== Activated PHP syntax =====\n'
for afb_relative in \
    "wp-content/themes/alanfullbeard/front-page.php" \
    "wp-content/themes/alanfullbeard/functions.php" \
    "wp-content/themes/alanfullbeard/inc/portfolio-blocks.php" \
    "wp-content/mu-plugins/new-tab-dammit.php"; do
    php -l "${AFB_WEB_ROOT}/${afb_relative}"
done

printf '\n===== Public health checks =====\n'
for afb_url in \
    "https://alanfullbeard.com/" \
    "https://alanfullbeard.com/contact/" \
    "https://alanfullbeard.com/privacy-policy/" \
    "https://alanfullbeard.com/portfolio/"; do
    curl \
        --fail \
        --silent \
        --show-error \
        --location \
        --max-time 20 \
        --output /dev/null \
        "$afb_url"
    printf 'HTTP_OK %s\n' "$afb_url"
done

afb_writes_started=false
trap - ERR INT TERM

printf '\nActivation passed. Database, uploads content, and production keys were untouched.\n'
printf 'Rollback root: %s\n' "$AFB_BACKUP_ROOT"

#!/usr/bin/env bash

# Activates three reviewed Alan Fullbeard theme files on HostGator. Existing
# live files are hash-guarded and backed up before atomic replacement.

set -euo pipefail

readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"
readonly AFB_THEME_ROOT="${AFB_WEB_ROOT}/wp-content/themes/alanfullbeard"
readonly AFB_STAGE_ROOT="/home2/afullbeard/deployments/theme-update-20260728"
readonly AFB_BACKUP_ROOT="/home2/afullbeard/security-hardening-backups/theme-update-20260728"

readonly AFB_LIVE_FUNCTIONS_SHA256="27f34d130129fbc30740a032c8192165def0bfa2ef745387c95f705bf8b97a6a"
readonly AFB_LIVE_STYLE_SHA256="f515c9e36f6cc538f69e4a5ca18614ed4038c05774a689dae0c9274bcdc764e2"
readonly AFB_LIVE_STYLE_MIN_SHA256="951faeed946824fec3f82fae4fb810f6414bd0651c5933a68ac8660e6d003995"

readonly AFB_NEW_FUNCTIONS_SHA256="7817dbb1b612c9461848410d9015eb132598ce6b6bf9ed934ad3bce9213f218c"
readonly AFB_NEW_STYLE_SHA256="3a83c9585e8f7d049fd9b694e1e9f3dbe0575739db11826b75a9e1000b662012"
readonly AFB_NEW_STYLE_MIN_SHA256="e581156f1f066d4edfb0de6717e60de6f02094558adabc857ea11422e58f18ec"

sha_for() {
    sha256sum "$1" | cut -d ' ' -f 1
}

verify_hash() {
    local afb_path="$1"
    local afb_expected="$2"
    local afb_label="$3"
    local afb_actual

    afb_actual="$(sha_for "$afb_path")"
    if [[ "$afb_actual" != "$afb_expected" ]]; then
        printf 'Refusing activation: %s hash is %s, expected %s\n' \
            "$afb_label" \
            "$afb_actual" \
            "$afb_expected" >&2
        exit 1
    fi
}

if [[ -e "$AFB_BACKUP_ROOT" ]]; then
    printf 'Refusing activation: rollback directory already exists: %s\n' \
        "$AFB_BACKUP_ROOT" >&2
    exit 1
fi

verify_hash "$AFB_THEME_ROOT/functions.php" "$AFB_LIVE_FUNCTIONS_SHA256" "live functions.php"
verify_hash "$AFB_THEME_ROOT/style.css" "$AFB_LIVE_STYLE_SHA256" "live style.css"
verify_hash "$AFB_THEME_ROOT/style.min.css" "$AFB_LIVE_STYLE_MIN_SHA256" "live style.min.css"
verify_hash "$AFB_STAGE_ROOT/functions.php" "$AFB_NEW_FUNCTIONS_SHA256" "staged functions.php"
verify_hash "$AFB_STAGE_ROOT/style.css" "$AFB_NEW_STYLE_SHA256" "staged style.css"
verify_hash "$AFB_STAGE_ROOT/style.min.css" "$AFB_NEW_STYLE_MIN_SHA256" "staged style.min.css"

php -l "$AFB_STAGE_ROOT/functions.php" >/dev/null

if ! grep -q "transform-origin: bottom right;" "$AFB_STAGE_ROOT/style.css"; then
    printf 'Refusing activation: source CSS lacks the reviewed navigation transform origin\n' >&2
    exit 1
fi
if ! grep -q ".site-nav a span{transform-origin:bottom right" "$AFB_STAGE_ROOT/style.min.css"; then
    printf 'Refusing activation: production CSS lacks the reviewed navigation transform origin\n' >&2
    exit 1
fi
if grep -q "contact-form__field--storage" "$AFB_STAGE_ROOT/style.css" \
    || grep -q "contact-form__field--storage" "$AFB_STAGE_ROOT/style.min.css"; then
    printf 'Refusing activation: obsolete contact storage-control CSS remains\n' >&2
    exit 1
fi

umask 077
mkdir -p "$AFB_BACKUP_ROOT"
cp -p "$AFB_THEME_ROOT/functions.php" "$AFB_BACKUP_ROOT/functions.php.before"
cp -p "$AFB_THEME_ROOT/style.css" "$AFB_BACKUP_ROOT/style.css.before"
cp -p "$AFB_THEME_ROOT/style.min.css" "$AFB_BACKUP_ROOT/style.min.css.before"

cp "$AFB_STAGE_ROOT/functions.php" "$AFB_BACKUP_ROOT/functions.php.candidate"
cp "$AFB_STAGE_ROOT/style.css" "$AFB_BACKUP_ROOT/style.css.candidate"
cp "$AFB_STAGE_ROOT/style.min.css" "$AFB_BACKUP_ROOT/style.min.css.candidate"
chmod --reference="$AFB_THEME_ROOT/functions.php" "$AFB_BACKUP_ROOT/functions.php.candidate"
chmod --reference="$AFB_THEME_ROOT/style.css" "$AFB_BACKUP_ROOT/style.css.candidate"
chmod --reference="$AFB_THEME_ROOT/style.min.css" "$AFB_BACKUP_ROOT/style.min.css.candidate"

rollback_needed=1
rollback() {
    if [[ "$rollback_needed" != "1" ]]; then
        return
    fi

    for afb_file in functions.php style.css style.min.css; do
        if [[ -e "$AFB_THEME_ROOT/$afb_file" ]]; then
            mv "$AFB_THEME_ROOT/$afb_file" "$AFB_BACKUP_ROOT/$afb_file.failed"
        fi
        cp -p "$AFB_BACKUP_ROOT/$afb_file.before" "$AFB_THEME_ROOT/$afb_file"
    done
}
trap rollback EXIT

mv "$AFB_BACKUP_ROOT/style.css.candidate" "$AFB_THEME_ROOT/style.css"
mv "$AFB_BACKUP_ROOT/style.min.css.candidate" "$AFB_THEME_ROOT/style.min.css"
mv "$AFB_BACKUP_ROOT/functions.php.candidate" "$AFB_THEME_ROOT/functions.php"

cd "$AFB_WEB_ROOT"
wp eval '
if (
    ! function_exists("alanfullbeard_lcars_primary_nav_link_attributes")
    || false === has_filter(
        "nav_menu_link_attributes",
        "alanfullbeard_lcars_primary_nav_link_attributes"
    )
) {
    fwrite(STDERR, "Theme load verification failed.\n");
    exit(1);
}
printf("theme_loaded=yes\nprimary_nav_link_filter=loaded\n");
'

verify_hash "$AFB_THEME_ROOT/functions.php" "$AFB_NEW_FUNCTIONS_SHA256" "activated functions.php"
verify_hash "$AFB_THEME_ROOT/style.css" "$AFB_NEW_STYLE_SHA256" "activated style.css"
verify_hash "$AFB_THEME_ROOT/style.min.css" "$AFB_NEW_STYLE_MIN_SHA256" "activated style.min.css"

for afb_url in "https://alanfullbeard.com/" "https://alanfullbeard.com/contact/"; do
    afb_status="$(
        curl \
            --fail \
            --silent \
            --show-error \
            --location \
            --max-time 20 \
            --output /dev/null \
            --write-out '%{http_code}' \
            "$afb_url"
    )"
    if [[ "$afb_status" != "200" ]]; then
        printf 'Public health check failed for %s: %s\n' "$afb_url" "$afb_status" >&2
        exit 1
    fi
done

curl \
    --fail \
    --silent \
    --show-error \
    --location \
    --max-time 20 \
    "https://alanfullbeard.com/" |
php -r '
$html = stream_get_contents(STDIN);
if (
    ! is_string($html)
    || ! preg_match(
        "/<nav\\b[^>]*id=\"primary-navigation\"[^>]*>.*?<\\/nav>/si",
        $html,
        $navMatch
    )
) {
    fwrite(STDERR, "Primary navigation markup was not found.\n");
    exit(1);
}

preg_match_all(
    "/(<a\\b[^>]*>)(.*?)<\\/a>/si",
    $navMatch[0],
    $linkMatches,
    PREG_SET_ORDER
);
if ([] === $linkMatches) {
    fwrite(STDERR, "Primary navigation has no links.\n");
    exit(1);
}

foreach ($linkMatches as $linkMatch) {
    $tag = $linkMatch[1];
    $label = strtolower(trim(strip_tags($linkMatch[2])));
    preg_match("/\\bhref=\"([^\"]*)\"/i", $tag, $hrefMatch);
    $href = html_entity_decode($hrefMatch[1] ?? "", ENT_QUOTES | ENT_HTML5);
    $isTextMessageLink = str_starts_with(strtolower($href), "sms:")
        || "text me" === $label;

    if ($isTextMessageLink) {
        if (preg_match("/\\btarget=/i", $tag)) {
            fwrite(STDERR, "Text me unexpectedly opens a new context.\n");
            exit(1);
        }
        continue;
    }

    if (! preg_match("/\\btarget=\"_blank\"/i", $tag)) {
        fwrite(STDERR, "A non-SMS primary navigation link lacks target blank.\n");
        exit(1);
    }
    if (
        ! preg_match("/\\brel=\"[^\"]*\\bnoopener\\b[^\"]*\"/i", $tag)
        || ! preg_match("/\\brel=\"[^\"]*\\bnoreferrer\\b[^\"]*\"/i", $tag)
    ) {
        fwrite(STDERR, "A new-tab navigation link lacks rel protection.\n");
        exit(1);
    }
}

printf("public_nav_links_verified=%d\n", count($linkMatches));
'

rollback_needed=0
trap - EXIT

printf 'Activated the reviewed HostGator theme update.\n'
printf 'Rollback directory: %s\n' "$AFB_BACKUP_ROOT"
sha256sum \
    "$AFB_THEME_ROOT/functions.php" \
    "$AFB_THEME_ROOT/style.css" \
    "$AFB_THEME_ROOT/style.min.css"

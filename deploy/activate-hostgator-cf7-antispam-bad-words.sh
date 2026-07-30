#!/usr/bin/env bash

# Applies the reviewed high-confidence bad-word list while preserving every
# other CF7 AntiSpam option and creating an authenticated encrypted backup.

set -euo pipefail
umask 077

readonly AFB_STAGE_ROOT="/home2/afullbeard/security-staging/cf7-antispam-bad-words-20260729"
readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"

cd "$AFB_STAGE_ROOT"
sha256sum -c cf7-antispam-bad-words-SHA256SUMS

cd "$AFB_WEB_ROOT"
wp eval-file "$AFB_STAGE_ROOT/audit-hostgator-cf7-antispam-bad-words.php"
wp eval-file "$AFB_STAGE_ROOT/update-hostgator-cf7-antispam-bad-words.php"
wp cache flush

printf 'Activated the reviewed CF7 AntiSpam bad-word list.\n'

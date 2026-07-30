#!/usr/bin/env bash

# Restores the complete CF7 AntiSpam option from the authenticated encrypted
# pre-change backup. Run only with separate rollback approval.

set -euo pipefail

readonly AFB_STAGE_ROOT="/home2/afullbeard/security-staging/cf7-antispam-bad-words-20260729"
readonly AFB_WEB_ROOT="/home2/afullbeard/public_html"

cd "$AFB_STAGE_ROOT"
sha256sum -c cf7-antispam-bad-words-SHA256SUMS

cd "$AFB_WEB_ROOT"
wp eval-file "$AFB_STAGE_ROOT/rollback-hostgator-cf7-antispam-bad-words.php"
wp cache flush

printf 'Rolled back the CF7 AntiSpam bad-word list.\n'

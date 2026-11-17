#!/usr/bin/env bash
# A fresh Cadenza instance for recording: wipes the databases and uploads of the checkout in $CZ, starts it with the model
# configured in the environment (CADENZA_LLM_BASE_URL, CADENZA_LLM_MODEL, CADENZA_LLM_API_KEY) and creates the owner
# account through the first-run page.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
CZ="${CZ:?path to a cadenza checkout}"; CRED="${CRED:?file with email= and password= lines for the owner}"
: "${CADENZA_LLM_BASE_URL:?}" "${CADENZA_LLM_MODEL:?}" "${CADENZA_LLM_API_KEY:?}"
cd "$CZ"
./cadenza stop >/dev/null 2>&1 || true
rm -f demo-app/var/data_dev.db harness/var/data_dev.db
(cd demo-app && php bin/console doctrine:migrations:migrate --no-interaction --quiet --allow-no-migration && php bin/console cadenza:init "My site" --quiet || true)
(cd harness && php bin/console doctrine:migrations:migrate --no-interaction --quiet --allow-no-migration)
./cadenza start 2>&1 | grep -E "running|rror" || true
sleep 3
node "$HERE/setup-owner.mjs"

#!/usr/bin/env bash
# Like reset-env.sh but the owner account is NOT created: the recording shows the first run.
set -euo pipefail
CZ="${CZ:?}"; cd "$CZ"
./cadenza stop >/dev/null 2>&1 || true
rm -f demo-app/var/data_dev.db harness/var/data_dev.db; rm -rf harness/var/vault harness/var/uploads
(cd demo-app && php bin/console doctrine:migrations:migrate --no-interaction --quiet --allow-no-migration && php bin/console cadenza:init "My site" --quiet || true)
(cd harness && php bin/console doctrine:migrations:migrate --no-interaction --quiet --allow-no-migration)
export CADENZA_LLM_BASE_URL=https://api.deepseek.com CADENZA_LLM_MODEL='deepseek-flash?max_tokens=12000'
export CADENZA_LLM_API_KEY="$(secret get DEEPSEEK_API_KEY)"
export CADENZA_LLM_FALLBACK_BASE_URL=https://api.deepseek.com CADENZA_LLM_FALLBACK_MODEL='deepseek-flash?max_tokens=12000' CADENZA_LLM_FALLBACK_API_KEY="$CADENZA_LLM_API_KEY"
./cadenza start 2>&1 | grep -E "running|rror" || true
sleep 3

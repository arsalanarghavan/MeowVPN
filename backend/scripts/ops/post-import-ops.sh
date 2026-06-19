#!/usr/bin/env bash
# Post-import maintenance: closure rebuild + webhook registration + schedule list.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT/backend"

{
  echo "post-import-ops start $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  php artisan svp:rebuild-reseller-closure
  php artisan svp:register-webhooks
  php artisan schedule:list
  echo "post-import-ops complete $(date -u +%Y-%m-%dT%H:%M:%SZ)"
} 2>&1 | tee "${SVP_POST_IMPORT_LOG:-$ROOT/docs/evidence/post-import-$(date +%F).log}"

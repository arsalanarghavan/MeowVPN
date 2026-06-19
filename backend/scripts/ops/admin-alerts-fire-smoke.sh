#!/usr/bin/env bash
# Spec §18.6 — smoke AdminAlertsJob (panel down path uses mocked settings in CI).
set -euo pipefail

cd "$(dirname "$0")/../.."
php artisan tinker --execute='\App\Modules\Core\Jobs\AdminAlertsJob::dispatchSync();'
echo "[admin-alerts-fire-smoke] OK"

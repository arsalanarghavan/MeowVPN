#!/usr/bin/env bash
# Broadcast enqueue scale smoke via PHPUnit (default 100 targets).
set -euo pipefail
cd "$(dirname "$0")/../.."
php artisan test --filter=BroadcastLoadEnqueueTest
echo "[broadcast-load-smoke] OK"

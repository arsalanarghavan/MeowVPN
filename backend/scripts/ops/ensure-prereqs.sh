#!/usr/bin/env bash
# Operator prerequisites for v28 OPS evidence (php-xml, redis, wp-cli, optional gh).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
export PATH="$ROOT/bin:$PATH"
FAIL=0

check_ext() {
  if php -m 2>/dev/null | grep -qi "^${1}$"; then
    echo "OK   php ext $1"
  else
    echo "FAIL php ext $1 missing — install php-xml / php-redis or run PHPUnit via docker compose exec app"
    FAIL=1
  fi
}

echo "=== ensure-prereqs $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
for ext in dom xml xmlwriter redis bcmath; do
  check_ext "$ext"
done

if command -v wp >/dev/null 2>&1; then
  echo "OK   wp-cli $(wp --version 2>/dev/null | head -1)"
else
  echo "FAIL wp-cli not in PATH (expected $ROOT/bin/wp)"
  FAIL=1
fi

if command -v gh >/dev/null 2>&1; then
  echo "OK   gh $(gh --version | head -1)"
else
  echo "WARN gh not installed (optional for PR/tag)"
fi

if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
  echo "OK   docker $(docker --version)"
else
  echo "WARN docker not available — use remote host or install Docker for compose smoke"
fi

echo "env SVP_MYSQL_DSN=${SVP_MYSQL_DSN:-unset}"
echo "env SVP_WP_DUMP=${SVP_WP_DUMP:-unset}"
echo "env SVP_BASE_URL=${SVP_BASE_URL:-http://127.0.0.1:8080}"
echo "env https_proxy=${https_proxy:-unset}"

if [[ "$FAIL" -gt 0 ]]; then
  echo "=== ensure-prereqs FAILED — see backend/docker/Dockerfile for container with all extensions ==="
  exit 1
fi
echo "=== ensure-prereqs OK ==="

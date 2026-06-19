#!/usr/bin/env bash
# Run PHPUnit via host PHP or docker compose exec app (when host lacks dom/xml).
# Local extensions: apt install php8.3-xml php8.3-sqlite3 php8.3-bcmath  OR  use docker compose exec app
set -euo pipefail

FILTER="${1:?filter required}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

PHP_XML_EXT_DIR="${PHP_XML_EXT_DIR:-/tmp/php-xml-extract/usr/lib/php/20230831}"
PHP_INI_EXTRA_DIR="${PHP_INI_EXTRA_DIR:-$ROOT/.php-ini}"

run_host() {
  if php -m 2>/dev/null | grep -qi '^dom$'; then
    if [[ "$FILTER" == "Bot" ]]; then
      php artisan test tests/Feature/Bot
    else
      php artisan test --filter="$FILTER"
    fi
  elif [[ -f "${PHP_XML_EXT_DIR}/dom.so" ]]; then
    if [[ "$FILTER" == "Bot" ]]; then
      PHP_INI_SCAN_DIR="/etc/php/8.3/cli/conf.d:${PHP_INI_EXTRA_DIR}" php artisan test tests/Feature/Bot
    else
      PHP_INI_SCAN_DIR="/etc/php/8.3/cli/conf.d:${PHP_INI_EXTRA_DIR}" php artisan test --filter="$FILTER"
    fi
  else
    echo "ERROR: php dom/xml missing — extract php8.3-xml deb to ${PHP_XML_EXT_DIR} or install php8.3-xml"
    exit 1
  fi
}

run_docker() {
  docker compose exec -T app php artisan test --filter="$FILTER"
}

if php -m 2>/dev/null | grep -qi '^dom$' || [[ -f "${PHP_XML_EXT_DIR}/dom.so" ]]; then
  run_host
elif command -v docker >/dev/null 2>&1 && docker compose ps --status running app 2>/dev/null | grep -q app; then
  run_docker
elif command -v docker >/dev/null 2>&1; then
  docker compose run --rm --no-deps app php artisan test --filter="$FILTER"
else
  echo "ERROR: php dom/xml missing and docker unavailable — cannot run PHPUnit filter=$FILTER"
  exit 1
fi

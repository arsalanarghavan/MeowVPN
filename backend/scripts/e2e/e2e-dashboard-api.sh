#!/usr/bin/env bash
# API-level E2E smoke (login, CSRF, state, mutate, §7.1 session, alias) — complements browser E2E.
set -euo pipefail
BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

curl -sf -c "$COOKIE_JAR" "${BASE}/sanctum/csrf-cookie" >/dev/null
TOKEN="$(grep XSRF-TOKEN "$COOKIE_JAR" | awk '{print $7}' | tail -1 | python3 -c 'import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))')"

curl -sf -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: ${TOKEN}" \
  -H "Accept: application/json" \
  -d '{"username":"admin","password":"changeme"}' \
  "${BASE}/api/v1/auth/login" | grep -q '"ok":true'

curl -sf -b "$COOKIE_JAR" \
  -H "Accept: application/json" \
  "${BASE}/api/v1/me/state" | grep -q '"isLoggedIn":true'

curl -sf -b "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: ${TOKEN}" \
  -H "Accept: application/json" \
  -d '{"persona":"admin"}' \
  "${BASE}/api/v1/dashboard/persona" | grep -q '"ok":true'

curl -sf -b "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: ${TOKEN}" \
  -H "Accept: application/json" \
  -d '{"ui_theme":"dark","ui_accent":"blue"}' \
  "${BASE}/api/v1/dashboard/ui-preferences" | grep -q '"ok":true'

curl -sf -b "$COOKIE_JAR" \
  -H "Accept: application/json" \
  "${BASE}/api/v1/admin/state?tab=dashboard" | grep -q '"ok":true'

alias_code=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" "${BASE}/api/v1/dashboard/admin/state?tab=dashboard")
canon_code=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" "${BASE}/api/v1/admin/state?tab=dashboard")
test "$alias_code" = "$canon_code"

imp_start=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: ${TOKEN}" \
  -H "Accept: application/json" \
  -d '{"targetSvpUserId":100}' \
  "${BASE}/api/v1/dashboard/impersonate/start")
test "$imp_start" = "200" -o "$imp_start" = "403"
curl -sf -b "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: ${TOKEN}" \
  -H "Accept: application/json" \
  -d '{}' \
  "${BASE}/api/v1/dashboard/impersonate/stop" >/dev/null || true

echo "[e2e-dashboard-api] OK me/state persona ui-preferences alias=$alias_code canon=$canon_code impersonate=$imp_start"

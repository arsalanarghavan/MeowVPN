#!/usr/bin/env bash
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$REPO/frontend"
npm ci
npm run build
echo "Built: $REPO/frontend/dist"

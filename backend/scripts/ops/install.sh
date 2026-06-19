#!/usr/bin/env bash
# MeowVPN standard installer — thin wrapper.
# One-liner from GitHub: bash <(curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/install.sh)
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
exec bash "$ROOT/backend/scripts/ops/install/install.sh" "$@"

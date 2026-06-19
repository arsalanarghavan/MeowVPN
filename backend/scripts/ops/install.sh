#!/usr/bin/env bash
# MeowVPN standard installer — thin wrapper.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
exec bash "$ROOT/backend/scripts/ops/install/install.sh" "$@"

#!/usr/bin/env bash
# MeowVPN install — purple theme + cross-process progress counter.
set -euo pipefail

C_RESET='\033[0m'
C_PURPLE='\033[38;5;141m'
C_YELLOW='\033[38;5;220m'
C_RED='\033[31m'

if [[ -n "${NO_COLOR:-}" ]] || [[ ! -t 1 ]]; then
  C_RESET=''
  C_PURPLE=''
  C_YELLOW=''
  C_RED=''
fi

apply_purple_theme() {
  export NEWT_COLORS='
root=,magenta
window=magenta,white
border=magenta,white
title=magenta,white
button=black,magenta
actbutton=white,magenta
compactbutton=black,magenta
actselbutton=white,magenta
disbutton=gray,magenta
textbox=white,magenta
acttextbox=black,magenta
entry=white,magenta
actentry=black,magenta
listbox=white,magenta
actlistbox=black,magenta
actsellistbox=black,magenta
checkbutton=magenta,white
actcheckbutton=magenta,white
searchbox=white,magenta
actsearchbox=black,magenta
shadow=magenta,magenta
'
}

progress_file() {
  echo "${MEOWVPN_PROGRESS_FILE:-${STATE_DIR:-/tmp/meowvpn-install}/.progress}"
}

progress_reset() {
  local total="${1:-100}"
  export MEOWVPN_PROGRESS_TOTAL="$total"
  mkdir -p "$(dirname "$(progress_file)")"
  echo "0" >"$(progress_file)"
}

progress() {
  local label="$1"
  local total="${MEOWVPN_PROGRESS_TOTAL:-100}"
  local idx pct
  local pf
  pf="$(progress_file)"
  mkdir -p "$(dirname "$pf")"
  idx="$(cat "$pf" 2>/dev/null || echo 0)"
  idx=$((idx + 1))
  echo "$idx" >"$pf"
  pct=$((idx * 100 / total))
  if (( pct > 100 )); then pct=100; fi
  printf '%b[ %3d%% ]%b %s\n' "$C_PURPLE" "$pct" "$C_RESET" "$label"
}

bootstrap_progress() {
  local current="$1"
  local total="$2"
  local label="$3"
  local pct=0
  if (( total > 0 )); then
    pct=$((current * 100 / total))
  fi
  if (( pct > 100 )); then pct=100; fi
  printf '%b[ %3d%% ]%b %s\n' "$C_PURPLE" "$pct" "$C_RESET" "$label"
}

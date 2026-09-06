#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[windows-paths] check_windows_checkout_paths"
echo "- scans tracked paths for Windows checkout blockers"

failures=0
checks=0

report_matches() {
  local label="$1"
  local pattern="$2"
  local mode="${3:-E}"

  checks=$((checks + 1))
  local grep_flag="-E"
  if [[ "$mode" == "Ei" ]]; then
    grep_flag="-Ei"
  fi

  local matches
  matches="$(git ls-files | grep $grep_flag -- "$pattern" || true)"

  if [[ -n "$matches" ]]; then
    echo "  fail: $label"
    echo "$matches" | sed 's/^/    - /'
    failures=$((failures + 1))
  else
    echo "  ok: $label"
  fi
}

# Exact '....' or any segment named '....'.
report_matches "no exact-or-segment '....' paths" '(^|/)\.\.\.\.($|/)'

# Reserved DOS device names (with or without extension).
report_matches "no reserved Windows device names" '(^|/)(con|prn|aux|nul|com[1-9]|lpt[1-9])(\..*)?($|/)' "Ei"

# Any segment ending with dot or space.
report_matches "no trailing dot/space segments" '(^|/)[^/]*[. ]($|/)'

# Characters invalid in Windows file names.
report_matches "no invalid Windows filename characters" '[<>:"|?*]'

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($checks checks, $failures failure groups)"
  exit 1
fi

echo "RESULT: PASS ($checks checks)"

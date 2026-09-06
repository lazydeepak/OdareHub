#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

RG_BIN="${RG_BIN:-$(command -v rg || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"

if [[ -n "$RG_BIN" ]]; then
  SEARCH_TOOL="$RG_BIN"
  SEARCH_ARGS=(-n --no-heading -S)
else
  if [[ -z "$GREP_BIN" ]]; then
    echo "missing required binary: rg or grep" >&2
    exit 2
  fi
  SEARCH_TOOL="$GREP_BIN"
  SEARCH_ARGS=(-RInE)
fi

failures=0
passes=0

ok() {
  echo "  ok: $1"
  passes=$((passes + 1))
}

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

check_required_file() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" ]]; then
    ok "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

check_text_contains() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if grep -Fq -- "$needle" "$path"; then
    ok "$label"
  else
    fail "$label ($needle) not found in $path"
  fi
}

check_no_path() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" ]]; then
    fail "$label should not exist ($path)"
  else
    ok "$label ($path absent)"
  fi
}


echo "[architecture] check_platform_layer_boundaries"
echo "- read-only platform layer boundary contract diagnostics"

echo ""
echo "== Required contract file =="
check_required_file "docs/architecture/platform-layer-boundary-contract.md" "platform layer boundary contract"

echo ""
echo "== Contract content checks =="
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "app/" "mentions app/"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "apps/" "mentions apps/"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "apps/Platform/" "mentions apps/Platform/"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "platform/" "mentions platform/"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "plugins/" "mentions plugins/"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "resources/" "mentions resources/"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "public/" "mentions public/"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "storage/" "mentions storage/"

check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "apps/Studio/Tools/ReportDesigner" "locks Report Designer UI/tool placement"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "platform/Reports" "locks platform Reports placement"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "platform/Reports/Definitions" "locks platform Reports Definitions placement"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "Resources/report-sources" "locks business report sources placement"
check_text_contains "docs/architecture/platform-layer-boundary-contract.md" "Report Designer must not hardcode business" "includes anti-hardcoding rule"


echo ""
echo "== Existing platform capability layer =="
check_required_file "platform/Labels" "platform Labels capability layer"
check_required_file "platform/Style" "platform Style capability layer"


echo ""
echo "== Prohibited report placement =="
check_no_path "apps/Platform/modules/Reports" "platform/Reports must not live under apps/Platform/modules"
check_no_path "apps/Platform/Resources/reports" "platform/Reports must not live under apps/Platform/Resources"


echo ""
echo "== Summary =="
if [[ "$failures" -gt 0 ]]; then
  echo "[architecture] check_platform_layer_boundaries: FAILED ($failures failure(s))" >&2
  exit 1
fi

echo "[architecture] check_platform_layer_boundaries: PASS ($passes checks)"
exit 0

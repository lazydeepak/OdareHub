#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

RG_BIN="${RG_BIN:-$(command -v rg || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"

if [[ -n "$RG_BIN" ]]; then
  SEARCH_TOOL="$RG_BIN"
  SEARCH_ARGS=(-n -S)
else
  if [[ -z "$GREP_BIN" ]]; then
    echo "missing required binary: rg or grep" >&2
    exit 2
  fi
  SEARCH_TOOL="$GREP_BIN"
  SEARCH_ARGS=(-RInE)
fi

failures=0
warnings=0
passes=0

probe_contract_doc="docs/architecture/read-only-consumption-probe-contract.md"
future_probe_script="scripts/platform/probe_resolved_style_consumer.php"
aggregate_runner="scripts/architecture/run_architecture_gates.sh"
self_gate="scripts/architecture/check_read_only_consumption_probe_boundaries.sh"

ok() {
  echo "  ok: $1"
  passes=$((passes + 1))
}

warn() {
  echo "  warn: $1"
  warnings=$((warnings + 1))
}

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

check_required_path() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" ]]; then
    ok "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

require_contract_text() {
  local needle="$1"
  local label="$2"

  if [[ ! -f "$probe_contract_doc" ]]; then
    fail "$label (contract doc missing)"
    return
  fi

  if grep -Fq -- "$needle" "$probe_contract_doc"; then
    ok "$label"
  else
    fail "$label (expected text not found: $needle)"
  fi
}

check_no_matches() {
  local label="$1"
  local pattern="$2"
  shift 2
  local targets=("$@")

  if [[ "${#targets[@]}" -eq 0 ]]; then
    warn "$label (no files to scan)"
    return
  fi

  local tmp_matches
  tmp_matches="$(mktemp /tmp/read-only-consumption-probe-boundary-XXXXXX)"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    fail "$label"
    cat "$tmp_matches" >&2
  else
    ok "$label"
  fi

  rm -f "$tmp_matches"
}

check_runner_includes_gate() {
  local gate_path="$1"

  if [[ ! -f "$aggregate_runner" ]]; then
    fail "missing aggregate gate runner ($aggregate_runner)"
    return
  fi

  local count_expected
  count_expected="$(grep -F -c -- "\"$gate_path\"" "$aggregate_runner" 2>/dev/null || true)"
  count_expected="$(printf '%s' "$count_expected" | tr -d '[:space:]')"
  if [[ -z "$count_expected" ]]; then
    count_expected="0"
  fi
  if [[ "$count_expected" -ge 2 ]]; then
    ok "aggregate runner includes $gate_path in expected/scripts lists"
  else
    fail "aggregate runner must include $gate_path in both expected/scripts lists"
  fi
}

echo "[architecture] check_read_only_consumption_probe_boundaries"
echo "- read-only consumption probe boundary diagnostics (gate prep; probe script optional)"

echo ""
echo "== Contract exists =="
check_required_path "$probe_contract_doc" "Read-Only Consumption Probe contract doc"
require_contract_text "$future_probe_script" "contract documents future probe script path"
require_contract_text "$self_gate" "contract documents probe boundary gate path"

echo ""
echo "== Aggregate gate wiring =="
check_runner_includes_gate "$self_gate"

echo ""
echo "== Allowed read paths documented =="
require_contract_text "storage/platform/style-registry/approved-values/" "contract documents approved registry read path"
require_contract_text "apps/Shell/DesignSystem/Resources/socket-catalog/" "contract documents Shell socket catalog read path"

echo ""
echo "== Exit code model documented =="
require_contract_text "0 = PASS/WARN only" "contract documents exit code 0"
require_contract_text "1 = FAIL present" "contract documents exit code 1"
require_contract_text "2 = ERROR present" "contract documents exit code 2"

echo ""
echo "== Diagnostic codes documented =="
for code in RSC-P001 RSC-P002 RSC-W001 RSC-W002 RSC-F001 RSC-F002 RSC-E001 RSC-E002; do
  require_contract_text "$code" "contract documents diagnostic code $code"
done

echo ""
echo "== Future probe path policy =="
if [[ -f "$future_probe_script" ]]; then
  ok "future probe script exists and will be scanned ($future_probe_script)"
  probe_targets=("$future_probe_script")
else
  ok "future probe script absent; gate passes without probe implementation ($future_probe_script)"
  probe_targets=()
fi

if [[ "${#probe_targets[@]}" -gt 0 ]]; then
  echo ""
  echo "== Future probe: no Studio coupling =="
  check_no_matches \
    "future probe must not import or reference Studio namespaces/paths" \
    'Apps\\Studio|apps/Studio|StudioController|CustomizationStudio|VisualCustomizer|CssTokenEditor|ThemeTool' \
    "${probe_targets[@]}"

  echo ""
  echo "== Future probe: no writes =="
  check_no_matches \
    "future probe must not perform filesystem writes" \
    'file_put_contents|fwrite|unlink|rename|mkdir|rmdir|chmod|chown|copy\s*\(' \
    "${probe_targets[@]}"

  echo ""
  echo "== Future probe: no theme compilation or shelling =="
  check_no_matches \
    "future probe must not call theme compiler or shell out" \
    'compile_theme_sources|theme compiler|exec\s*\(|shell_exec\s*\(|proc_open\s*\(|passthru\s*\(|system\s*\(' \
    "${probe_targets[@]}"

  echo ""
  echo "== Future probe: no route registration =="
  check_no_matches \
    "future probe must not register or reference web routes" \
    'Route::|routes\.php|\bPOST\b|GET route' \
    "${probe_targets[@]}"

  echo ""
  echo "== Future probe: no DB or HTTP side effects =="
  check_no_matches \
    "future probe must not use database or HTTP side effects" \
    'PDO|mysqli|curl_|file_get_contents\s*\(\s*['\''"]https?://|stream_context_create' \
    "${probe_targets[@]}"
fi

echo ""
echo "== Invariant summary =="
echo "  passes: $passes"
echo "  warnings: $warnings"
echo "  failures: $failures"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Read-Only Consumption Probe boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS ($passes invariant(s) checked)"

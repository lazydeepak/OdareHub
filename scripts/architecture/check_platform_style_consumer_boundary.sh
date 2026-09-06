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
warnings=0
passes=0

consumer_file="platform/Style/ResolvedStyleConsumer.php"
contract_file="platform/Style/Contracts/ApprovedStyleReaderContract.php"
adapter_file="platform/Style/Adapters/ApprovedStyleReaderAdapter.php"

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

# Collect target files under platform/Style/
platform_style_files=()
while IFS= read -r file; do
  platform_style_files+=("$file")
done < <(find platform/Style -type f \( -name '*.php' -o -name '*.json' \) -print 2>/dev/null)

# Build non-adapter file list: all platform/Style/ files EXCEPT the adapter
non_adapter_files=()
for f in "${platform_style_files[@]}"; do
  if [[ "$f" != "$adapter_file" ]]; then
    non_adapter_files+=("$f")
  fi
done

# is_comment_line: check if a given line number in a file is a PHP comment
is_comment_line() {
  local file="$1"
  local line="$2"

  local content
  content="$(sed -n "${line}p" "$file" 2>/dev/null || true)"
  [[ -z "$content" ]] && return 0

  local trimmed
  trimmed="$(echo "$content" | sed 's/^[[:space:]]*//')"

  case "$trimmed" in
    \*\/*)  return 0 ;; # docblock start /** ... */
    \**)    return 0 ;; # docblock continuation * text
    \/\/*)  return 0 ;; # single-line comment //
    \#*)    return 0 ;; # shell-style comment #
    \/\**)  return 0 ;; # block comment /* ... */
  esac

  return 1
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

  local tmp_all
  tmp_all="$(mktemp /tmp/psc-all-XXXXXX)"
  local tmp_nc
  tmp_nc="$(mktemp /tmp/psc-nc-XXXXXX)"

  if ! "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_all" 2>/dev/null; then
    ok "$label"
    rm -f "$tmp_all" "$tmp_nc"
    return
  fi

  # Filter out matches on PHP comment lines
  while IFS='' read -r match_line; do
    local file_part=""
    local line_num=""

    case "$SEARCH_TOOL" in
      *rg)
        # rg --no-heading -n output: file:line:content
        file_part="$(echo "$match_line" | cut -d: -f1)"
        line_num="$(echo "$match_line" | cut -d: -f2)"
        ;;
      *)
        # grep -RInE output: file:line:content
        file_part="$(echo "$match_line" | cut -d: -f1)"
        line_num="$(echo "$match_line" | cut -d: -f2)"
        ;;
    esac

    if [[ -n "$file_part" && -n "$line_num" ]]; then
      if ! is_comment_line "$file_part" "$line_num"; then
        echo "$match_line" >> "$tmp_nc"
      fi
    else
      # If we can't parse the match line, keep it (safer to flag)
      echo "$match_line" >> "$tmp_nc"
    fi
  done < "$tmp_all"

  if [[ -s "$tmp_nc" ]]; then
    fail "$label"
    cat "$tmp_nc" >&2
  else
    ok "$label"
  fi

  rm -f "$tmp_all" "$tmp_nc"
}

echo "[architecture] check_platform_style_consumer_boundary"
echo "- read-only Platform Style consumer placeholder boundary diagnostics"

echo ""
echo "== Required file exists =="
check_required_path "$consumer_file" "Platform Style consumer placeholder"

echo ""
echo "== Placeholder remains disabled =="
if [[ -f "$consumer_file" ]]; then
  if grep -q 'isRuntimeConsumptionEnabled.*return.*true' "$consumer_file" 2>/dev/null; then
    fail "isRuntimeConsumptionEnabled() must not return true in placeholder phase"
  else
    ok "placeholder does not enable runtime consumption"
  fi

  if grep -Eq '\$runtimeConsumptionEnabled\s*=\s*true' "$consumer_file" 2>/dev/null; then
    fail "runtimeConsumptionEnabled property must not be set to true"
  else
    ok "runtimeConsumptionEnabled property remains false (or unset)"
  fi

  if grep -Eq "'runtime_consumption_enabled'\s*=>\s*true" "$consumer_file" 2>/dev/null; then
    fail "diagnostics must not report runtime_consumption_enabled as true"
  else
    ok "diagnostics report runtime_consumption_enabled as false"
  fi
else
  fail "consumer file missing; cannot check disabled state"
fi

echo ""
echo "== No Studio coupling (non-comment) =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not import or reference Studio namespaces/paths (outside docblock)" \
    'Apps\\Studio|apps/Studio|StudioController|CustomizationStudio|VisualCustomizer|CssTokenEditor|ThemeTool' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for Studio coupling"
fi

echo ""
echo "== No Shell coupling yet (non-comment) =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not import or reference Shell namespaces/paths (outside docblock)" \
    'Apps\\Shell|apps/Shell/styles|ShellStyleService|ShellRuntime' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for Shell coupling"
fi

echo ""
echo "== No writes =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not perform filesystem writes" \
    'file_put_contents|fwrite|unlink|rename|mkdir|rmdir|chmod|chown|copy\s*\(' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for writes"
fi

echo ""
echo "== No shell/compiler calls =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not call shell or compiler" \
    'exec\s*\(|shell_exec\s*\(|proc_open\s*\(|passthru\s*\(|system\s*\(|compile_theme_sources|ThemeCompiler' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for shell/compiler calls"
fi

echo ""
echo "== No routes =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not register or reference web routes" \
    'Route::|routes\.php|\bPOST\b|GET route' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for routes"
fi

echo ""
echo "== No DB/HTTP side effects =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not use database or HTTP side effects" \
    'PDO|mysqli|curl_|file_get_contents\s*\(\s*['\''"]https?://|stream_context_create' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for DB/HTTP"
fi

echo ""
echo "== Consumer reader diagnostics allowed (positive expectations) =="
# Forward-looking checks for the reader injection slice. These emit warnings
# until the consumer is updated to import and use ApprovedStyleReaderContract.
# After injection they become passing checks.
if [[ -f "$consumer_file" ]]; then
  if grep -q 'use Platform\\Style\\Contracts\\ApprovedStyleReaderContract' "$consumer_file" 2>/dev/null; then
    ok "consumer imports ApprovedStyleReaderContract"
  else
    warn "consumer does not yet import Platform\\Style\\Contracts\\ApprovedStyleReaderContract (expected until injection slice)"
  fi

  if grep -q 'ApprovedStyleReaderContract' "$consumer_file" 2>/dev/null; then
    ok "consumer references ApprovedStyleReaderContract type"
  else
    warn "consumer does not yet reference ApprovedStyleReaderContract type (expected until injection slice)"
  fi

  if grep -q 'isReachable(' "$consumer_file" 2>/dev/null; then
    ok "consumer calls isReachable()"
  else
    warn "consumer does not yet call isReachable() (expected until injection slice)"
  fi

  if grep -q 'readValue(' "$consumer_file" 2>/dev/null; then
    ok "consumer calls readValue()"
  else
    warn "consumer does not yet call readValue() (expected until injection slice)"
  fi

  if grep -q 'radius\.scale' "$consumer_file" 2>/dev/null; then
    ok "consumer references radius.scale socket"
  else
    warn "consumer does not yet reference radius.scale socket key (expected until injection slice)"
  fi
else
  warn "consumer file missing; reader injection checks deferred"
fi

echo ""
echo "== No public consumption API in consumer =="
if [[ -f "$consumer_file" ]]; then
  check_no_matches \
    "consumer must not declare approvedValuePreview method" \
    'function\s+approvedValuePreview\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare resolveValue method" \
    'function\s+resolveValue\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare resolvedValue method" \
    'function\s+resolvedValue\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare apply method" \
    'function\s+apply\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare consume method" \
    'function\s+consume\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare runtimeStyle method" \
    'function\s+runtimeStyle\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare styleMap method" \
    'function\s+styleMap\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare tokenMap method" \
    'function\s+tokenMap\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not declare css method" \
    'function\s+css\s*\(' \
    "$consumer_file"
else
  warn "consumer file missing; public API checks deferred"
fi

echo ""
echo "== No draft/approval access =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not access draft artifacts" \
    'storage/studio/customization|studio_draft|visual-customizer.*draft' \
    "${platform_style_files[@]}"

  check_no_matches \
    "must not access approval artifacts" \
    'approval-request|ApprovalRequest|pending_approval|studio_visual_customizer_requests' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for draft/approval access"
fi

echo ""
echo "== Registry read path — adapter-only exceptions =="

# 1. Only adapter may reference ApprovedStyleRegistry or getValue
if [[ "${#non_adapter_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "only adapter may reference ApprovedStyleRegistry (non-adapter files blocked)" \
    'ApprovedStyleRegistry|getValue\s*\(' \
    "${non_adapter_files[@]}"
else
  ok "no non-adapter files to scan for ApprovedStyleRegistry"
fi

# 2. Only adapter may reference registry storage paths
if [[ "${#non_adapter_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "only adapter may reference registry storage paths (non-adapter files blocked)" \
    'storage/platform/style-registry|approved-values' \
    "${non_adapter_files[@]}"
else
  ok "no non-adapter files to scan for registry storage paths"
fi

# 3. Adapter must not call setValue, isWritable, or reference draft/approval
if [[ -f "$adapter_file" ]]; then
  check_no_matches \
    "adapter must not call setValue" \
    'setValue\s*\(' \
    "$adapter_file"

  check_no_matches \
    "adapter must not call isWritable" \
    'isWritable\s*\(' \
    "$adapter_file"

  check_no_matches \
    "adapter must not reference draft or approval artifacts" \
    'draft|approval' \
    "$adapter_file"
else
  warn "adapter not yet created ($adapter_file); adapter-specific checks deferred"
fi

# 4. Consumer must not import ApprovedStyleRegistry, call getValue,
#    reference storage paths, or call setValue/isWritable
if [[ -f "$consumer_file" ]]; then
  check_no_matches \
    "consumer must not import ApprovedStyleRegistry directly" \
    'use Apps\\Platform\\StyleRegistry' \
    "$consumer_file"

  check_no_matches \
    "consumer must not call getValue directly" \
    'getValue\s*\(' \
    "$consumer_file"

  check_no_matches \
    "consumer must not reference storage paths" \
    'storage/platform/style-registry|approved-values' \
    "$consumer_file"

  check_no_matches \
    "consumer must not call setValue or isWritable" \
    'setValue\s*\(|isWritable\s*\(' \
    "$consumer_file"
else
  warn "consumer file missing; consumer-specific checks deferred"
fi

# 5. Reader contract must be pure — no write, governance, or destructive methods
if [[ -f "$contract_file" ]]; then
  check_no_matches \
    "reader contract must not contain setValue" \
    'setValue' \
    "$contract_file"

  check_no_matches \
    "reader contract must not contain isWritable" \
    'isWritable' \
    "$contract_file"

  check_no_matches \
    "reader contract must not contain save function" \
    'function\s+save' \
    "$contract_file"

  check_no_matches \
    "reader contract must not contain approve function" \
    'function\s+approve' \
    "$contract_file"

  check_no_matches \
    "reader contract must not contain delete function" \
    'function\s+delete' \
    "$contract_file"
else
  warn "contract not yet created ($contract_file); contract purity checks deferred"
fi

echo ""
echo "== Contract existence checks =="
check_required_path "docs/architecture/resolved-style-consumer-contract.md" "ResolvedStyleConsumer Contract"
check_required_path "docs/architecture/read-only-consumption-probe-contract.md" "Read-Only Consumption Probe Contract"
check_required_path "docs/architecture/resolved-style-consumer-registry-read-planning.md" "Registry Read Planning"
check_required_path "docs/architecture/registry-read-contract.md" "Registry Read Contract"
check_required_path "docs/architecture/registry-read-boundary-gate-update-plan.md" "Registry Read Boundary Gate Update Plan"
check_required_path "docs/architecture/resolved-style-consumer-reader-injection-plan.md" "ResolvedStyleConsumer Reader Injection Plan"

echo ""
echo "== Invariant summary =="
echo "  passes: $passes"
echo "  warnings: $warnings"
echo "  failures: $failures"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Platform Style consumer boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS ($passes invariant(s) checked)"

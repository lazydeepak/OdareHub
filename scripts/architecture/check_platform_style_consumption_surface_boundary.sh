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

surface_dir="platform/Style/Consumption"
consumption_contract="docs/architecture/platform-style-consumption-surface-contract.md"

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

check_optional_path() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" ]]; then
    ok "$label ($path)"
    return 0
  else
    warn "$label not yet created ($path)"
    return 1
  fi
}

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
    \*\/*)  return 0 ;;
    \**)    return 0 ;;
    \/\/*)  return 0 ;;
    \#*)    return 0 ;;
    \/\**)  return 0 ;;
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
  tmp_all="$(mktemp /tmp/pcs-all-XXXXXX)"
  local tmp_nc
  tmp_nc="$(mktemp /tmp/pcs-nc-XXXXXX)"

  if ! "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_all" 2>/dev/null; then
    ok "$label"
    rm -f "$tmp_all" "$tmp_nc"
    return
  fi

  while IFS='' read -r match_line; do
    local file_part=""
    local line_num=""

    case "$SEARCH_TOOL" in
      *rg)
        file_part="$(echo "$match_line" | cut -d: -f1)"
        line_num="$(echo "$match_line" | cut -d: -f2)"
        ;;
      *)
        file_part="$(echo "$match_line" | cut -d: -f1)"
        line_num="$(echo "$match_line" | cut -d: -f2)"
        ;;
    esac

    if [[ -n "$file_part" && -n "$line_num" ]]; then
      if ! is_comment_line "$file_part" "$line_num"; then
        echo "$match_line" >> "$tmp_nc"
      fi
    else
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

check_has_match() {
  local label="$1"
  local pattern="$2"
  local file="$3"

  if [[ ! -f "$file" ]]; then
    warn "$label (file not found)"
    return
  fi

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "$file" >/dev/null 2>/dev/null; then
    ok "$label"
  else
    fail "$label (expected pattern '$pattern' not found in $file)"
  fi
}

echo "[architecture] check_platform_style_consumption_surface_boundary"
echo "- read-only Platform Style consumption surface boundary diagnostics"

echo ""
echo "== Required contract existence =="
check_required_path "$consumption_contract" "Platform Style Consumption Surface Contract"

echo ""
echo "== Implementation directory (optional — absent is valid pre-implementation) =="
if check_optional_path "$surface_dir" "Consumption surface implementation directory"; then
  surface_implementation_exists=true

  # Collect surface files
  surface_files=()
  while IFS= read -r file; do
    surface_files+=("$file")
  done < <(find "$surface_dir" -type f -name '*.php' -print 2>/dev/null)

  # Only allowed file
  allowed_surface_file="$surface_dir/StyleConsumptionSurface.php"

  for f in "${surface_files[@]}"; do
    if [[ "$f" != "$allowed_surface_file" ]]; then
      fail "unexpected file in consumption surface directory: $f"
    fi
  done
  if [[ "${#surface_files[@]}" -gt 0 ]]; then
    ok "only allowed surface files present"
  fi
else
  surface_implementation_exists=false
fi

echo ""
echo "== Allowed public methods =="
if [[ "$surface_implementation_exists" == true ]] && [[ -f "$allowed_surface_file" ]]; then
  check_has_match "radiusScale() method present" 'function\s+radiusScale\s*\(' "$allowed_surface_file"
  check_has_match "diagnostics() method present" 'function\s+diagnostics\s*\(' "$allowed_surface_file"
  check_has_match "isRuntimeConsumptionEnabled() method present" 'function\s+isRuntimeConsumptionEnabled\s*\(' "$allowed_surface_file"
else
  warn "surface file not yet created; method checks deferred"
fi

echo ""
echo "== Blocked public methods =="
if [[ "$surface_implementation_exists" == true ]] && [[ -f "$allowed_surface_file" ]]; then
  check_no_matches \
    "must not declare generic readValue()" \
    'function\s+readValue\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare getValue()" \
    'function\s+getValue\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare valueFor()" \
    'function\s+valueFor\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare styleMap()" \
    'function\s+styleMap\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare tokenMap()" \
    'function\s+tokenMap\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare css()" \
    'function\s+css\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare apply()" \
    'function\s+apply\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare consume()" \
    'function\s+consume\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare resolve()" \
    'function\s+resolve\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare setValue()" \
    'function\s+setValue\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not declare isWritable()" \
    'function\s+isWritable\s*\(' \
    "$allowed_surface_file"
else
  warn "surface file not yet created; blocked method checks deferred"
fi

echo ""
echo "== Runtime consumption disabled =="
if [[ "$surface_implementation_exists" == true ]] && [[ -f "$allowed_surface_file" ]]; then
  if grep -q 'isRuntimeConsumptionEnabled.*return.*true' "$allowed_surface_file" 2>/dev/null; then
    fail "isRuntimeConsumptionEnabled() must not return true in Phase 1"
  else
    ok "isRuntimeConsumptionEnabled() does not return true"
  fi

  if grep -Eq "'runtime_consumption_enabled'\s*=>\s*true" "$allowed_surface_file" 2>/dev/null; then
    fail "diagnostics must not report runtime_consumption_enabled as true"
  else
    ok "diagnostics report runtime_consumption_enabled as false (or absent)"
  fi
else
  warn "surface file not yet created; runtime consumption checks deferred"
fi

echo ""
echo "== Dependency rules =="
if [[ "$surface_implementation_exists" == true ]] && [[ -f "$allowed_surface_file" ]]; then
  # May depend on ResolvedStyleConsumer
  if grep -q 'use Platform\\Style\\ResolvedStyleConsumer' "$allowed_surface_file" 2>/dev/null; then
    ok "may import Platform\\Style\\ResolvedStyleConsumer (allowed)"
  else
    warn "does not import Platform\\Style\\ResolvedStyleConsumer (optional)"
  fi

  # Must NOT depend on ApprovedStyleRegistry
  check_no_matches \
    "must not import Apps\\Platform\\StyleRegistry" \
    'use Apps\\Platform\\StyleRegistry' \
    "$allowed_surface_file"

  # Must NOT depend on ApprovedStyleReaderAdapter (in import)
  check_no_matches \
    "must not import ApprovedStyleReaderAdapter" \
    'use Platform\\Style\\Adapters\\ApprovedStyleReaderAdapter' \
    "$allowed_surface_file"

  # Must NOT depend on ApprovedStyleReaderContract (in import)
  check_no_matches \
    "must not import ApprovedStyleReaderContract" \
    'use Platform\\Style\\Contracts\\ApprovedStyleReaderContract' \
    "$allowed_surface_file"

  # Must NOT depend on Studio
  check_no_matches \
    "must not import Apps\\Studio" \
    'use Apps\\Studio' \
    "$allowed_surface_file"

  # Must NOT depend on Shell
  check_no_matches \
    "must not import Apps\\Shell" \
    'use Apps\\Shell' \
    "$allowed_surface_file"

  # Must NOT reference registry storage paths
  check_no_matches \
    "must not reference storage/platform/style-registry" \
    'storage/platform/style-registry' \
    "$allowed_surface_file"

  check_no_matches \
    "must not reference approved-values" \
    'approved-values' \
    "$allowed_surface_file"
else
  warn "surface file not yet created; dependency checks deferred"
fi

echo ""
echo "== Still-forbidden operations =="
if [[ "$surface_implementation_exists" == true ]] && [[ -f "$allowed_surface_file" ]]; then
  check_no_matches \
    "must not perform filesystem writes" \
    'file_put_contents\s*\(|fwrite\s*\(|mkdir\s*\(|unlink\s*\(|rename\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not call shell commands" \
    'exec\s*\(|shell_exec\s*\(|proc_open\s*\(|system\s*\(|passthru\s*\(' \
    "$allowed_surface_file"

  check_no_matches \
    "must not use database connections" \
    'PDO|mysqli\s*::|new\s+mysqli' \
    "$allowed_surface_file"

  check_no_matches \
    "must not make HTTP calls" \
    'curl_\w+\s*\(|stream_context_create' \
    "$allowed_surface_file"

  check_no_matches \
    "must not register routes" \
    'Route::' \
    "$allowed_surface_file"

  check_no_matches \
    "must not reference theme.css" \
    'theme\.css' \
    "$allowed_surface_file"

  check_no_matches \
    "must not reference resources/themes" \
    'resources/themes' \
    "$allowed_surface_file"

  check_no_matches \
    "must not reference public/assets" \
    'public/assets' \
    "$allowed_surface_file"

  check_no_matches \
    "must not reference apps/Shell/styles" \
    'apps/Shell/styles' \
    "$allowed_surface_file"

  check_no_matches \
    "must not reference draft or approval artifacts" \
    'draft|approval' \
    "$allowed_surface_file"
else
  warn "surface file not yet created; forbidden operations checks deferred"
fi

echo ""
echo "== Positive expectations (if implementation exists) =="
if [[ "$surface_implementation_exists" == true ]] && [[ -f "$allowed_surface_file" ]]; then
  check_has_match "namespace Platform\\Style\\Consumption" 'namespace Platform\\Style\\Consumption' "$allowed_surface_file"
  check_has_match "class StyleConsumptionSurface" 'class StyleConsumptionSurface' "$allowed_surface_file"
else
  warn "surface file not yet created; positive expectation checks deferred"
fi

echo ""
echo "== Shell gate coordination =="
echo "  note: gate #28 (check_shell_consumption_boundary.sh) currently blocks all Platform\\Style\\* imports in Shell"
echo "  note: when consumption surface exists and this gate passes, gate #28 may be relaxed to allow"
echo "  note:   Platform\\Style\\Consumption\\StyleConsumptionSurface in 3 allowlisted composer files:"
echo "  note:   - apps/Shell/Composers/AdminSurfaceComposer.php"
echo "  note:   - apps/Shell/Composers/OperatorSurfaceComposer.php"
echo "  note:   - apps/Shell/Composers/DisplaySurfaceComposer.php"
echo "  note: no Shell integration or runtime consumption is authorized by this gate"

echo ""
echo "== Invariant summary =="
echo "  passes: $passes"
echo "  warnings: $warnings"
echo "  failures: $failures"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Platform Style consumption surface boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS ($passes invariant(s) checked)"

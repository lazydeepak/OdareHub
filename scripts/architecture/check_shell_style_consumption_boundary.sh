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
  SEARCH_ARGS=(-RIn)
fi

failures=0
warnings=0

style_root="apps/Shell/DesignSystem"
consumption_plan_doc="apps/Shell/DesignSystem/Contracts/approved-style-consumption-boundary-plan.md"
architecture_boundary_doc="docs/architecture/shell-approved-style-consumption-boundary.md"

ok() {
  echo "  ok: $1"
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
  tmp_matches="$(mktemp /tmp/shell-style-consumption-boundary-XXXXXX)"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    fail "$label"
    cat "$tmp_matches" >&2
  else
    ok "$label"
  fi

  rm -f "$tmp_matches"
}

check_consumption_not_implemented() {
  local label="$1"
  local pattern="$2"
  shift 2
  local targets=("$@")

  if [[ "${#targets[@]}" -eq 0 ]]; then
    warn "$label (no files to scan)"
    return
  fi

  local tmp_matches
  tmp_matches="$(mktemp /tmp/shell-style-consumption-allowlist-XXXXXX)"

  # Allow documented planning docs, the preparation-phase consumer service,
  # and placeholder-only Shell Style service stubs. ResolvedStyleConsumer is
  # Phase 2B preparation only – NOT runtime consumption.
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    grep -v "$consumption_plan_doc" "$tmp_matches" \
      | grep -v "$architecture_boundary_doc" \
      | grep -Ev 'apps/Shell/DesignSystem/Services/(ResolvedStyleConsumer|StyleAttributeBuilder|StyleVariableBuilder)\.php' \
      > "${tmp_matches}.filtered" || true
    if [[ -s "${tmp_matches}.filtered" ]]; then
      fail "$label"
      cat "${tmp_matches}.filtered" >&2
    else
      ok "$label (only in consumption planning docs)"
    fi
  else
    ok "$label"
  fi

  rm -f "$tmp_matches" "${tmp_matches}.filtered"
}

echo "[architecture] check_shell_style_consumption_boundary"
echo "- read-only Shell approved style consumption boundary diagnostics"

shell_runtime_files=()
while IFS= read -r file; do
  shell_runtime_files+=("$file")
done < <(find apps/Shell -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -not -path '*/AppearanceReaderInventoryService.php' -print)

shell_style_runtime_files=()
while IFS= read -r file; do
  shell_style_runtime_files+=("$file")
done < <(find "$style_root" -type f \( -name '*.php' -o -name '*.json' -o -name '*.js' -o -name '*.css' \) -print 2>/dev/null)

core_runtime_files=()
while IFS= read -r file; do
  core_runtime_files+=("$file")
done < <(find app -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print)

public_assets_files=()
while IFS= read -r file; do
  public_assets_files+=("$file")
done < <(find public/assets -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print 2>/dev/null)

echo ""
echo "== Consumption planning docs exist =="
check_required_path "$consumption_plan_doc" "Shell consumption boundary plan doc"
check_required_path "$architecture_boundary_doc" "Architecture-level consumption boundary doc"

echo ""
echo "== Shell must not read Studio draft paths =="
check_no_matches \
  "Shell must not read Studio draft paths" \
  'storage/studio/customization/visual-customizer/drafts|studio_draft|studio-draft' \
  "${shell_runtime_files[@]}"

echo ""
echo "== Shell must not read approval request artifacts or tables =="
check_no_matches \
  "Shell must not read approval request artifacts" \
  'approval-requests|vc-req-|studio_visual_customizer_requests' \
  "${shell_runtime_files[@]}"

echo ""
echo "== Shell must not read snapshot artifacts or tables =="
check_no_matches \
  "Shell must not read snapshot artifacts" \
  'snapshots/vc-snap|vc-snap-|studio_visual_customizer_snapshots' \
  "${shell_runtime_files[@]}"

echo ""
echo "== Shell must not reference CustomizationStudio runtime internals =="
check_no_matches \
  "Shell must not reference CustomizationStudio runtime internals" \
  'CustomizationStudio|customization-studio|Tools/CustomizationStudio|preview-fixtures' \
  "${shell_runtime_files[@]}"

echo ""
echo "== Shell must not use public/assets as style source truth =="
check_no_matches \
  "Shell must not use public/assets as style source truth" \
  'public/assets[^\n]*(source[[:space:]]truth|registry|authoring|StyleRegistry|ApprovedStyle)' \
  "${shell_style_runtime_files[@]}"

check_no_matches \
  "public/assets must not contain Shell consumption or StyleRegistry references" \
  'StyleRegistry|ApprovedStyle|ConsumptionBoundary|shell_style_consumption' \
  "${public_assets_files[@]}"

echo ""
echo "== Shell must not mutate Core =="
check_no_matches \
  "Shell must not write files to Core path" \
  'file_put_contents[^\n]*(app/|/app/)|fwrite[^\n]*(app/|/app/)|fopen[[:space:]]*\([^,]*app/[^,]*,[[:space:]]*["'"'"'][wa]|unlink[^\n]*app/|rename[^\n]*app/|copy[^\n]*app/' \
  "${shell_runtime_files[@]}"

check_no_matches \
  "Core must not contain Shell consumption implementation" \
  'ApprovedStyleRegistry|Apps\\\\Platform\\\\StyleRegistry|ResolvedStyleConsumer|StyleAttributeBuilder|StyleVariableBuilder|StyleSocketContract|StyleConsumerContract' \
  "${core_runtime_files[@]}"

echo ""
echo "== Shell consumption implementation not present (except planning docs) =="
check_consumption_not_implemented \
  "Shell must not consume Platform StyleRegistry at runtime yet" \
  'ApprovedStyleRegistry::getValue|ApprovedStyleRegistry::setValue|ResolvedStyleConsumer|StyleAttributeBuilder|StyleVariableBuilder|ResolvedApprovedStyleContract' \
  "${shell_runtime_files[@]}"

echo ""
echo "== Future allowed source is Platform ApprovedStyleRegistry =="
# Positive check: the planning docs must reference ApprovedStyleRegistry as the source
if grep -Fq "ApprovedStyleRegistry" "$consumption_plan_doc" 2>/dev/null; then
  ok "Consumption plan doc references ApprovedStyleRegistry as allowed source"
else
  fail "Consumption plan doc must reference ApprovedStyleRegistry"
fi

if grep -Fq "ApprovedStyleRegistry" "$architecture_boundary_doc" 2>/dev/null; then
  ok "Architecture boundary doc references ApprovedStyleRegistry as allowed source"
else
  fail "Architecture boundary doc must reference ApprovedStyleRegistry"
fi

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Shell approved style consumption boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS"

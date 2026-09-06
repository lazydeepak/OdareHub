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

studio_root="apps/Studio/Tools/CustomizationStudio"
shell_style_root="apps/Shell/DesignSystem"
platform_registry_root="apps/Platform/StyleRegistry"

studio_diagnostic="scripts/architecture/check_customization_studio_boundaries.sh"
shell_diagnostic="scripts/architecture/check_shell_style_catalog_boundaries.sh"
platform_diagnostic="scripts/architecture/check_platform_style_registry_boundaries.sh"

aggregate_runner="scripts/architecture/run_architecture_gates.sh"

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
  tmp_matches="$(mktemp /tmp/style-chain-parity-XXXXXX)"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    fail "$label"
    cat "$tmp_matches" >&2
  else
    ok "$label"
  fi

  rm -f "$tmp_matches"
}

check_no_unapproved_matches() {
  local label="$1"
  local pattern="$2"
  local allow_pattern="$3"
  shift 3
  local targets=("$@")

  if [[ "${#targets[@]}" -eq 0 ]]; then
    warn "$label (no files to scan)"
    return
  fi

  local tmp_matches
  local tmp_unapproved
  tmp_matches="$(mktemp /tmp/style-chain-parity-XXXXXX)"
  tmp_unapproved="$(mktemp /tmp/style-chain-parity-unapproved-XXXXXX)"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    grep -Ev "$allow_pattern" "$tmp_matches" > "$tmp_unapproved" || true
    if [[ -s "$tmp_unapproved" ]]; then
      fail "$label"
      cat "$tmp_unapproved" >&2
    else
      ok "$label"
    fi
  else
    ok "$label"
  fi

  rm -f "$tmp_matches" "$tmp_unapproved"
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

echo "[architecture] check_style_chain_parity"
echo "- read-only cross-owner Style Chain parity diagnostics"

echo ""
echo "== Owner homes exist =="
check_required_path "$studio_root" "Customization Studio owner home"
check_required_path "$shell_style_root" "Shell Style owner home"
check_required_path "$platform_registry_root" "Platform Style Registry owner home"

echo ""
echo "== Owner diagnostics exist =="
check_required_path "$studio_diagnostic" "Customization Studio boundary diagnostic"
check_required_path "$shell_diagnostic" "Shell Style boundary diagnostic"
check_required_path "$platform_diagnostic" "Platform Style Registry boundary diagnostic"

echo ""
echo "== Aggregate gate parity =="
check_runner_includes_gate "$studio_diagnostic"
check_runner_includes_gate "$shell_diagnostic"
check_runner_includes_gate "$platform_diagnostic"

studio_runtime_files=()
while IFS= read -r file; do
  studio_runtime_files+=("$file")
done < <(find "$studio_root" -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' \) -print)

shell_runtime_files=()
while IFS= read -r file; do
  shell_runtime_files+=("$file")
done < <(find apps/Shell -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print)

platform_registry_runtime_files=()
while IFS= read -r file; do
  platform_registry_runtime_files+=("$file")
done < <(find "$platform_registry_root" -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print)

public_assets_files=()
while IFS= read -r file; do
  public_assets_files+=("$file")
done < <(find public/assets -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' -o -name '*.html' \) -print 2>/dev/null)

core_runtime_files=()
while IFS= read -r file; do
  core_runtime_files+=("$file")
done < <(find app -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print)

echo ""
echo "== Chain disconnection checks =="
check_no_unapproved_matches \
  "Studio runtime files must not write/apply Platform Style Registry outside first documented radius.scale Apply" \
  '(write|save|apply|activate|persist|publish)[^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)|(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)[^\n]*(write|save|apply|activate|persist|publish)|file_put_contents[^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)|(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)[^\n]*file_put_contents|fwrite[^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)|fopen[[:space:]]*\([^,]*(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)[^,]*,[[:space:]]*["'"'"'][wa]|unlink[[:space:]]*\([^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)|rename[[:space:]]*\([^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)|copy[[:space:]]*\([^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry|style_registry|theme_registry|StyleRegistry)' \
  'apps/Studio/Tools/CustomizationStudio/Views/visual-customizer-request-detail\.php:[0-9]+:.*Platform StyleRegistry|apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerApprovalRequestService\.php:[0-9]+:' \
  "${studio_runtime_files[@]}"

check_no_matches \
  "Shell runtime files must not read Studio draft/fixture paths" \
  'apps/Studio/Tools/CustomizationStudio/Resources/(drafts|preview-fixtures)|require(_once)?[[:space:]]*\(?[^\n]*Studio/Tools/CustomizationStudio|include(_once)?[[:space:]]*\(?[^\n]*Studio/Tools/CustomizationStudio|file_get_contents[^\n]*Studio/Tools/CustomizationStudio|scandir[^\n]*Studio/Tools/CustomizationStudio|glob[^\n]*Studio/Tools/CustomizationStudio|CustomizationStudio[^\n]*(draft|fixture)' \
  "${shell_runtime_files[@]}"

check_no_unapproved_matches \
  "Shell runtime files must not consume Platform Style Registry yet" \
  'apps/Platform/StyleRegistry|Apps\\\\Platform\\\\StyleRegistry|ApprovedStyleRegistry|ActiveDefaultStyleResolver|ResolvedApprovedStyleContract|StyleRegistrySnapshotService' \
  'apps/Shell/DesignSystem/Services/ResolvedStyleConsumer\.php' \
  "${shell_runtime_files[@]}"

echo ""
echo "== First Apply documentation guard =="
style_chain_checkpoint="docs/architecture/style-customization-chain-checkpoint.md"
apply_review="apps/Studio/Tools/CustomizationStudio/Contracts/visual-customizer-first-apply-implementation-review.md"
if grep -Fq "Apply to Platform StyleRegistry" "$style_chain_checkpoint" \
  && grep -Fq "Shell consumption boundary plan" "$style_chain_checkpoint" \
  && grep -Fq "radius.scale only" "$apply_review"; then
  ok "Style chain parity allows first Apply while keeping Shell consumption disconnected"
else
  fail "Style chain parity allowance requires first Apply and Shell disconnection documentation"
fi

echo ""
echo "== public/assets source-truth boundary =="
check_no_matches \
  "public/assets must not be treated as source/registry truth for style chain" \
  'style registry source|source[-_[:space:]]truth|StyleRegistry|CustomizationStudio draft|catalog_only_not_consumed|placeholder_only_not_runtime_truth' \
  "${public_assets_files[@]}"

echo ""
echo "== Core no-coupling boundary =="
check_no_matches \
  "Core runtime files must not couple to Studio/Shell Style/Platform Style Registry chain" \
  'Studio/Tools/CustomizationStudio|CustomizationStudio|apps/Shell/DesignSystem|StyleSocketCatalog|StyleAttributeBuilder|StyleVariableBuilder|apps/Platform/StyleRegistry|Apps\\\\Platform\\\\StyleRegistry|ApprovedStyleRegistry|ActiveDefaultStyleResolver|ResolvedApprovedStyleContract|StyleRegistrySnapshotService' \
  "${core_runtime_files[@]}"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Style Chain parity boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS"

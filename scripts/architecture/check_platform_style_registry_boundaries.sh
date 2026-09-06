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

registry_root="apps/Platform/StyleRegistry"

required_placeholders=(
  "apps/Platform/StyleRegistry/README.md"
  "apps/Platform/StyleRegistry/Contracts"
  "apps/Platform/StyleRegistry/Contracts/README.md"
  "apps/Platform/StyleRegistry/Contracts/ApprovedStyleRegistryContract.php"
  "apps/Platform/StyleRegistry/Contracts/ResolvedApprovedStyleContract.php"
  "apps/Platform/StyleRegistry/Services"
  "apps/Platform/StyleRegistry/Services/README.md"
  "apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry.php"
  "apps/Platform/StyleRegistry/Services/ActiveDefaultStyleResolver.php"
  "apps/Platform/StyleRegistry/Services/StyleRegistrySnapshotService.php"
  "apps/Platform/StyleRegistry/Resources"
  "apps/Platform/StyleRegistry/Resources/README.md"
  "apps/Platform/StyleRegistry/Resources/registry.placeholder.json"
  "apps/Platform/StyleRegistry/Diagnostics"
  "apps/Platform/StyleRegistry/Diagnostics/README.md"
)

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
  tmp_matches="$(mktemp /tmp/platform-style-registry-boundary-XXXXXX)"

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
  tmp_matches="$(mktemp /tmp/platform-style-registry-boundary-XXXXXX)"
  tmp_unapproved="$(mktemp /tmp/platform-style-registry-boundary-unapproved-XXXXXX)"

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

echo "[architecture] check_platform_style_registry_boundaries"
echo "- read-only Platform/System Style Registry boundary diagnostics"

echo ""
echo "== Required Platform Style Registry home =="
if [[ -d "$registry_root" ]]; then
  ok "Platform Style Registry root exists ($registry_root)"
else
  fail "missing Platform Style Registry root ($registry_root)"
fi

echo ""
echo "== Required placeholder contracts/services/resources =="
for path in "${required_placeholders[@]}"; do
  check_required_path "$path" "placeholder path"
done

echo ""
echo "== Placeholder JSON validity =="
registry_placeholder_path="$registry_root/Resources/registry.placeholder.json"
if [[ -f "$registry_placeholder_path" ]]; then
  if php -r '$path=$argv[1]; $data=json_decode((string)file_get_contents($path), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); }' "$registry_placeholder_path"; then
    ok "$registry_placeholder_path is valid JSON"
  else
    fail "$registry_placeholder_path must be valid JSON"
  fi
else
  fail "missing placeholder JSON ($registry_placeholder_path)"
fi

platform_registry_runtime_files=()
while IFS= read -r file; do
  platform_registry_runtime_files+=("$file")
done < <(find "$registry_root" -type f \( -name '*.php' -o -name '*.json' -o -name '*.js' -o -name '*.css' \) -print 2>/dev/null)

shell_runtime_files=()
while IFS= read -r file; do
  shell_runtime_files+=("$file")
done < <(find apps/Shell -type f \( -name '*.php' -o -name '*.json' -o -name '*.js' -o -name '*.css' \) -print)

customization_runtime_files=()
while IFS= read -r file; do
  customization_runtime_files+=("$file")
done < <(find apps/Studio/Tools/CustomizationStudio -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' \) -print 2>/dev/null)

core_runtime_files=()
while IFS= read -r file; do
  core_runtime_files+=("$file")
done < <(find app -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print)

echo ""
echo "== Registry not connected to Shell runtime (Phase 2B preparation allowed) =="
check_no_unapproved_matches \
  "Shell runtime files must not reference Platform Style Registry runtime homes yet" \
  'apps/Platform/StyleRegistry|Apps\\\\Platform\\\\StyleRegistry|ApprovedStyleRegistry|ActiveDefaultStyleResolver|StyleRegistrySnapshotService' \
  'apps/Shell/DesignSystem/Services/ResolvedStyleConsumer\.php' \
  "${shell_runtime_files[@]}"

check_no_matches \
  "Platform Style Registry runtime files must not depend on Shell runtime surfaces yet" \
  'apps/Shell|Apps\\\\Shell' \
  "${platform_registry_runtime_files[@]}"

echo ""
echo "== Customization Studio registry-write boundary =="
check_no_unapproved_matches \
  "Customization Studio runtime files must not include style-registry write/apply/activate flows outside first documented radius.scale Apply" \
  'Apps\\Platform\\StyleRegistry|require(_once)?[[:space:]]*\(?[^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry)|include(_once)?[[:space:]]*\(?[^\n]*(apps/Platform/StyleRegistry|Platform/StyleRegistry)|new[[:space:]]+(ApprovedStyleRegistry|ActiveDefaultStyleResolver|StyleRegistrySnapshotService)|::[[:space:]]*(write|save|apply|activate|persist|publish)[[:space:]]*\(|->[[:space:]]*(write|save|apply|activate|persist|publish)[[:space:]]*\(|registry_writes_enabled[^\n]*(=>|=)[^\n]*(true|1)|file_put_contents[^\n]*(registry|style_registry|theme_registry|StyleRegistry|Platform/StyleRegistry)|(registry|style_registry|theme_registry|StyleRegistry|Platform/StyleRegistry)[^\n]*file_put_contents|fwrite[[:space:]]*\(|fopen[[:space:]]*\([^,]+,[[:space:]]*["'"'"'][wa]|unlink[[:space:]]*\([^\n]*(registry|style_registry|theme_registry|StyleRegistry|Platform/StyleRegistry)|rename[[:space:]]*\([^\n]*(registry|style_registry|theme_registry|StyleRegistry|Platform/StyleRegistry)|copy[[:space:]]*\([^\n]*(registry|style_registry|theme_registry|StyleRegistry|Platform/StyleRegistry)' \
  'apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerApprovalRequestService\.php:[0-9]+:(use Apps\\Platform\\StyleRegistry\\Services\\ApprovedStyleRegistry;|require_once APP_ROOT \. '\''/apps/Platform/StyleRegistry/Contracts/ApprovedStyleRegistryContract\.php'\'';|require_once APP_ROOT \. '\''/apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry\.php'\'';|[[:space:]]*\$registry = new ApprovedStyleRegistry\(\);)' \
  "${customization_runtime_files[@]}"

echo ""
echo "== First Apply documentation guard =="
style_chain_checkpoint="docs/architecture/style-customization-chain-checkpoint.md"
apply_review="apps/Studio/Tools/CustomizationStudio/Contracts/visual-customizer-first-apply-implementation-review.md"
if grep -Fq "Apply to Platform StyleRegistry" "$style_chain_checkpoint" \
  && grep -Fq "radius.scale only" "$apply_review" \
  && grep -Fq "Call ApprovedStyleRegistry::setValue" "$apply_review"; then
  ok "Platform registry write allowance is documented as first radius.scale Apply only"
else
  fail "Platform registry write allowance requires first-apply documentation"
fi

echo ""
echo "== public/assets must not be registry/source truth =="
check_no_matches \
  "Platform Style Registry runtime files must not reference public/assets as source truth" \
  'public/assets' \
  "${platform_registry_runtime_files[@]}"

echo ""
echo "== Core lock boundary =="
check_no_matches \
  "Core runtime files must not contain Platform Style Registry coupling" \
  'apps/Platform/StyleRegistry|Apps\\\\Platform\\\\StyleRegistry|ApprovedStyleRegistry|ResolvedApprovedStyleContract|ActiveDefaultStyleResolver|StyleRegistrySnapshotService' \
  "${core_runtime_files[@]}"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Platform Style Registry boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS"

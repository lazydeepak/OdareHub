#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

checkpoint_doc="docs/architecture/css-token-editor-safety-checkpoint.md"
js_file="apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_editor.js"
save_service="apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Services/CssTokenEditorSaveService.php"
preview_file="apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Views/preview.php"

echo "[architecture] check_cte_safety"
echo "- verifying CSS Token Editor safety invariants"
echo ""

failures=0
passes=0

check() {
  local label="$1"
  local op="$2"
  local file="$3"
  local pattern="$4"

  if [[ ! -f "$file" ]]; then
    echo "  SKIP: $label — file not found ($file)"
    return
  fi

  if [[ "$op" == "present" ]]; then
    if grep -q -- "$pattern" "$file"; then
      echo "  ok: $label"
      passes=$((passes + 1))
    else
      echo "  FAIL: $label — expected pattern not found: $pattern" >&2
      failures=$((failures + 1))
    fi
  elif [[ "$op" == "absent" ]]; then
    if grep -q -- "$pattern" "$file"; then
      echo "  FAIL: $label — forbidden pattern found: $pattern" >&2
      failures=$((failures + 1))
    else
      echo "  ok: $label"
      passes=$((passes + 1))
    fi
  else
    echo "  FAIL: unknown op $op" >&2
    failures=$((failures + 1))
  fi
}

# ==== REQUIREMENTS ====

# Checkpoint doc exists
echo "== Checkpoint document =="
check "safety checkpoint document exists" "present" "$checkpoint_doc" "# CSS Token Editor Safety Checkpoint"

echo ""
echo "== Invariant 1: Source snapshot URL from server attribute =="
# JS: SOURCE_SNAPSHOT_URL comes from data-cte-source-snapshot-url, not hardcoded
check "JS uses data-cte-source-snapshot-url" "present" "$js_file" "data-cte-source-snapshot-url"
check "JS uses getAttribute for data-cte-source-snapshot-url" "present" "$js_file" "getAttribute.*data-cte-source-snapshot-url"
check "JS fallback snapshot endpoint is /apps/studio/tools/customization-studio/design-system/tokens/source-snapshot" "present" "$js_file" "/apps/studio/tools/customization-studio/design-system/tokens/source-snapshot"
# PHP: source snapshot URL is provided by preview model/template
check "PHP exposes source snapshot URL attribute" "present" "$preview_file" "data-cte-source-snapshot-url"

echo ""
echo "== Invariant 2: updateTokenStatus() must not write tokenValues =="
check "event listener writes tokenValues\[tokenName\]" "present" "$js_file" "tokenValues\[tokenName\] = target.value"
check "updateTokenStatus does not contain tokenValues write" "absent" "$js_file" "tokenValues\[name\] = input.value"

echo ""
echo "== Invariant 3: selectorField.value initialized on load =="
check "selectorField.value = firstKey on init" "present" "$js_file" "selectorField.value = firstKey"

echo ""
echo "== Invariant 4: (*NO_JIT) in save service regex =="
check "(*NO_JIT) in CssTokenEditorSaveService" "present" "$save_service" "(*NO_JIT)"

echo ""
echo "== Invariant 5: Save authority is server-side only =="
check "no client-side localStorage save in CTE JS" "absent" "$js_file" "localStorage"
check "no client-side AJAX PUT/PATCH save in CTE JS" "absent" "$js_file" ".put("
check "form POST to server save endpoint" "present" "$js_file" "/apps/studio/tools/customization-studio/design-system/tokens/save"

echo ""
echo "== Checkpoint self-reference =="
check "JS references checkpoint smoke test" "present" "$js_file" "Browser smoke checklist"

echo ""
echo "== Result =="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures invariant(s) broken)" >&2
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"

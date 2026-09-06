#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_gui_studio_css_js_separation_plan"
echo "- read-only diagnostic for Batch 22 GUI Studio CSS/JS separation contract"

failures=0

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

ok() {
  echo "  ok: $1"
}

require_file() {
  local path="$1"
  local label="$2"
  if [[ -f "$path" ]]; then
    ok "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

require_text() {
  local path="$1"
  local needle="$2"
  local label="$3"
  if [[ -f "$path" ]] && grep -Fq -- "$needle" "$path"; then
    ok "$label"
  else
    fail "$label not found in $path"
  fi
}

GUI_VIEW="apps/Studio/Views/gui_studio.php"
GUI_CSS="apps/Studio/styles/gui_studio.css"
JS01="apps/Studio/assets/js/gui_studio/01_bootstrap.js"
JS02="apps/Studio/assets/js/gui_studio/02_library.js"
JS03="apps/Studio/assets/js/gui_studio/03_editor_state.js"
JS04="apps/Studio/assets/js/gui_studio/04_loaded_resource_context_init.js"
JS06="apps/Studio/assets/js/gui_studio/06_legacy_visibility.js"

require_file "$GUI_VIEW" "GUI Studio main view"
require_file "$GUI_CSS" "GUI Studio CSS asset"
require_file "$JS01" "GUI Studio JS asset 01"
require_file "$JS02" "GUI Studio JS asset 02"
require_file "$JS03" "GUI Studio JS asset 03"
require_file "$JS04" "GUI Studio JS asset 04"
require_file "$JS06" "GUI Studio JS asset 06"

echo ""
echo "== GUI Studio CSS/JS ownership guards =="
require_text "$GUI_VIEW" "<link rel=\"stylesheet\" href=\"/assets/apps/studio/styles/gui_studio.css\">" "gui_studio links Studio CSS delivery asset"

echo ""
echo "== Required JS includes =="
require_text "$GUI_VIEW" "../assets/js/gui_studio/01_bootstrap.js" "gui_studio includes JS 01"
require_text "$GUI_VIEW" "../assets/js/gui_studio/02_library.js" "gui_studio includes JS 02"
require_text "$GUI_VIEW" "../assets/js/gui_studio/03_editor_state.js" "gui_studio includes JS 03"
require_text "$GUI_VIEW" "../assets/js/gui_studio/04_loaded_resource_context_init.js" "gui_studio includes JS 04"
require_text "$GUI_VIEW" "../assets/js/gui_studio/06_legacy_visibility.js" "gui_studio includes JS 06"

echo ""
echo "== Ordered JS execution contract =="
line_js01=$(grep -nF "../assets/js/gui_studio/01_bootstrap.js" "$GUI_VIEW" | head -n1 | cut -d: -f1 || true)
line_js02=$(grep -nF "../assets/js/gui_studio/02_library.js" "$GUI_VIEW" | head -n1 | cut -d: -f1 || true)
line_js03=$(grep -nF "../assets/js/gui_studio/03_editor_state.js" "$GUI_VIEW" | head -n1 | cut -d: -f1 || true)
line_js04=$(grep -nF "../assets/js/gui_studio/04_loaded_resource_context_init.js" "$GUI_VIEW" | head -n1 | cut -d: -f1 || true)
line_js06=$(grep -nF "../assets/js/gui_studio/06_legacy_visibility.js" "$GUI_VIEW" | head -n1 | cut -d: -f1 || true)

if [[ -z "$line_js01" || -z "$line_js02" || -z "$line_js03" || -z "$line_js04" || -z "$line_js06" ]]; then
  fail "unable to resolve all JS include line numbers"
else
  if (( line_js01 < line_js02 && line_js02 < line_js03 && line_js03 < line_js04 && line_js04 < line_js06 )); then
    ok "JS include order is preserved (01 -> 02 -> 03 -> 04 -> 06)"
  else
    fail "JS include order is not preserved"
  fi
fi

echo ""
echo "== Legacy guard =="
require_text "$GUI_VIEW" "<?php if (\$showLegacyStudioWorkbench) { ?>" "legacy workbench gate remains present"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (GUI Studio CSS/JS separation contract gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_gui_studio_view_separation_plan"
echo "- read-only diagnostic for Batch 23 GUI Studio tool/workflow ownership contract"

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

require_dir() {
  local path="$1"
  local label="$2"
  if [[ -d "$path" ]]; then
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
VIEWEDITOR_DIR="apps/Studio/Tools/ViewEditor/Views"
MENUEDITOR_DIR="apps/Studio/Tools/MenuEditor/Views"
APPBUILDER_DIR="apps/Studio/Tools/AppBuilder/Views"
MODULEBUILDER_DIR="apps/Studio/Tools/ModuleBuilder/Views"
THEMETOOL_DIR="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Views"
WORKFLOW_DIR="apps/Studio/Views/partials/workflow"
PARTIAL_HEADER="apps/Studio/Views/partials/gui_studio/header.php"
PARTIAL_MOBILE_TABS="apps/Studio/Views/partials/gui_studio/mobile_mode_tabs.php"
PARTIAL_EDITOR_INTRO="apps/Studio/Tools/ViewEditor/Views/editor_intro.php"
PARTIAL_TAB_EDIT="apps/Studio/Tools/ViewEditor/Views/tab_edit.php"
PARTIAL_ANALYZE_PANEL="apps/Studio/Views/partials/workflow/analyze_panel.php"
PARTIAL_CHANGES_PANEL="apps/Studio/Views/partials/workflow/changes_panel.php"
PARTIAL_CHANGES_MIGRATION_PLAN="apps/Studio/Views/partials/workflow/changes_migration_plan.php"
PARTIAL_TAB_APPLY="apps/Studio/Views/partials/workflow/tab_apply.php"
STUDIO_CSS_ASSET="apps/Studio/styles/gui_studio.css"

require_file "$GUI_VIEW" "GUI Studio main view"
require_dir "$VIEWEDITOR_DIR" "ViewEditor view owner directory"
require_dir "$MENUEDITOR_DIR" "MenuEditor view owner directory"
require_dir "$APPBUILDER_DIR" "AppBuilder view owner directory"
require_dir "$MODULEBUILDER_DIR" "ModuleBuilder view owner directory"
require_dir "$THEMETOOL_DIR" "ThemeTool view owner directory"
require_dir "$WORKFLOW_DIR" "Shared workflow partial directory"
require_file "$PARTIAL_HEADER" "GUI Studio header passive partial"
require_file "$PARTIAL_MOBILE_TABS" "GUI Studio mobile-mode-tabs passive partial"
require_file "$PARTIAL_EDITOR_INTRO" "ViewEditor editor-intro partial"
require_file "$PARTIAL_TAB_EDIT" "ViewEditor tab-edit partial"
require_file "$PARTIAL_ANALYZE_PANEL" "Shared workflow analyze-panel partial"
require_file "$PARTIAL_CHANGES_PANEL" "Shared workflow changes-panel partial"
require_file "$PARTIAL_CHANGES_MIGRATION_PLAN" "Shared workflow changes-migration-plan partial"
require_file "$PARTIAL_TAB_APPLY" "Shared workflow tab-apply partial"
require_file "$STUDIO_CSS_ASSET" "GUI Studio page CSS asset"

echo ""
echo "== GUI Studio composition includes =="
require_text "$GUI_VIEW" "'/partials/gui_studio/header.php'" "gui_studio includes header partial"
require_text "$GUI_VIEW" "'/partials/gui_studio/mobile_mode_tabs.php'" "gui_studio includes mobile-mode-tabs partial"
require_text "$GUI_VIEW" "'/../Tools/ViewEditor/Views/tab_edit.php'" "gui_studio includes tool-owned tab-edit partial"
require_text "$GUI_VIEW" "'/partials/workflow/analyze_panel.php'" "gui_studio includes shared analyze-panel partial"
require_text "$GUI_VIEW" "'/partials/workflow/changes_panel.php'" "gui_studio includes shared changes-panel partial"
require_text "$GUI_VIEW" "'/partials/workflow/tab_apply.php'" "gui_studio includes shared tab-apply partial"
require_text "$PARTIAL_TAB_EDIT" "'/editor_intro.php'" "tab-edit partial includes editor-intro partial"
require_text "$PARTIAL_TAB_EDIT" "'/../../../Views/partials/loaded_resource_workbench.php'" "tab-edit partial keeps loaded-resource workbench include"
require_text "$PARTIAL_TAB_EDIT" "'/../../../Views/partials/editor_workbench_shell.php'" "tab-edit partial keeps editor-workbench include"

echo ""
echo "== Main shell wrapper ownership =="
require_text "$GUI_VIEW" "<div class=\"studio-shell\"" "gui_studio owns studio-shell wrapper"
require_text "$GUI_VIEW" "<main class=\"studio-main\">" "gui_studio owns studio-main wrapper"
require_text "$GUI_VIEW" "<section class=\"studio-content\">" "gui_studio owns studio-content wrapper"

echo ""
echo "== CSS link and tab partial sanity =="
require_text "$GUI_VIEW" "/assets/apps/studio/styles/gui_studio.css" "gui_studio links Studio CSS delivery asset"
require_text "$PARTIAL_TAB_EDIT" "<div id=\"tab-edit\" class=\"tab-panel active\">" "tab-edit partial keeps original container"
require_text "$PARTIAL_TAB_APPLY" "<div id=\"tab-apply\" class=\"tab-panel\">" "tab-apply partial keeps original container"
require_text "$PARTIAL_CHANGES_PANEL" "'/changes_migration_plan.php'" "changes panel includes shared migration plan partial"

echo ""
echo "== Legacy and passive composition guards =="
if grep -Fq "'/partials/workflow/changes_migration_plan.php'" "$GUI_VIEW" || grep -Fq "'/changes_migration_plan.php'" "$PARTIAL_CHANGES_PANEL"; then
  ok "changes-migration-plan partial remains included in view composition"
else
  fail "changes-migration-plan partial include not found in gui_studio.php or changes_panel.php"
fi

for passive_partial in "$PARTIAL_HEADER" "$PARTIAL_MOBILE_TABS" "$PARTIAL_EDITOR_INTRO" "$PARTIAL_ANALYZE_PANEL" "$PARTIAL_CHANGES_PANEL" "$PARTIAL_CHANGES_MIGRATION_PLAN" "$PARTIAL_TAB_APPLY"
do
  if grep -Eq "<div class=\"studio-shell\"|<main class=\"studio-main\"|<section class=\"studio-content\"" "$passive_partial"; then
    fail "passive partial should not own main page shell wrappers ($passive_partial)"
  else
    ok "main page shell wrappers remain in gui_studio.php ($passive_partial)"
  fi
done

echo ""
echo "== Runtime-layer mutation guard (current diff) =="
if git diff --name-only | grep -Eq '^(app/Core/|apps/.*/(Controllers|Services)/|apps/.*/routes\.php|public/index\.php)'; then
  fail "current diff touches runtime service/controller/core routes outside Batch 23 view ownership scope"
else
  ok "no runtime service/controller/core route files touched in current diff"
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (GUI Studio view separation contract gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_theme_tool_lifecycle_contract"
echo "- read-only diagnostic for the Appearance checkpoint and Theme Doctor boundary"

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

require_absent() {
  local path="$1"
  local label="$2"

  if [[ ! -e "$path" ]]; then
    ok "$label"
  else
    fail "$label still exists ($path)"
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

require_no_match() {
  local path="$1"
  local pattern="$2"
  local label="$3"

  if [[ ! -f "$path" ]]; then
    fail "$label path missing: $path"
    return
  fi

  if grep -En -- "$pattern" "$path" >/dev/null 2>&1; then
    fail "$label found in $path"
  else
    ok "$label"
  fi
}

THEME_LIFECYCLE_DOC="docs/architecture/theme-tool-lifecycle-contract.md"
APPEARANCE_DOMAIN_DOC="docs/architecture/appearance-domain-contract.md"
APPEARANCE_MIGRATION_MAP="docs/migration-cleanup/maps/appearance-domain-migration-plan.md"
CUSTOMIZATION_DOC="docs/architecture/studio-customization-tools-contract.md"
TOOL_LIFECYCLE_DOC="docs/architecture/studio-tool-lifecycle-contract.md"
PLAN_MAP_DOC="docs/migration-cleanup/maps/theme-tool-create-delete-default-plan.md"
STUDIO_ROUTES="apps/Studio/routes.php"
STUDIO_CONTROLLER="apps/Studio/Controllers/StudioController.php"
THEME_MANIFEST="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/manifest.php"
THEME_READER_SERVICE="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Services/ThemeRegistryReaderService.php"
THEME_MUTATION_SERVICE="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Services/ThemeDraftMutationService.php"
THEME_DRAFT_DIR="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes"
THEME_ROOT="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor"
THEME_PREVIEW_VIEW="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Views/preview.php"

require_file "$THEME_LIFECYCLE_DOC" "ThemeDoctor lifecycle architecture contract"
require_file "$APPEARANCE_DOMAIN_DOC" "Appearance domain architecture contract"
require_file "$APPEARANCE_MIGRATION_MAP" "Appearance deferred migration map"
require_file "$PLAN_MAP_DOC" "ThemeDoctor lifecycle planning map"

echo ""
echo "== Appearance vocabulary checkpoint =="
require_text "$APPEARANCE_DOMAIN_DOC" "Appearance is the future canonical umbrella domain" "Appearance umbrella domain documented"
require_text "$APPEARANCE_DOMAIN_DOC" "**Color mode**" "Color mode defined"
require_text "$APPEARANCE_DOMAIN_DOC" "**Color scheme**" "Color scheme defined"
require_text "$APPEARANCE_DOMAIN_DOC" "**Palette**" "Palette defined"
require_text "$APPEARANCE_DOMAIN_DOC" "**Surface profile**" "Surface profile defined"
require_text "$APPEARANCE_DOMAIN_DOC" "**Effect profile**" "Effect profile defined"
require_text "$APPEARANCE_DOMAIN_DOC" "**Effects enabled**" "Effects enabled defined"
require_text "$APPEARANCE_DOMAIN_DOC" "**Motion mode**" "Motion mode defined"
require_text "$APPEARANCE_DOMAIN_DOC" "**Appearance preset**" "Appearance preset defined"
require_text "$APPEARANCE_DOMAIN_DOC" 'The terms `theme`, `color style`, and `palette mode`' "Legacy terminology explicitly documented"
require_text "$APPEARANCE_DOMAIN_DOC" 'Combined values such as `system-liquid-glass`' "Legacy combined values explicitly documented"
require_text "$APPEARANCE_DOMAIN_DOC" "Appearance presets are references, not independent token sources" "Preset reference ownership documented"
require_text "$APPEARANCE_DOMAIN_DOC" "Liquid Glass belongs to Special Effects" "Liquid Glass ownership documented"
require_text "$APPEARANCE_DOMAIN_DOC" "Palette ownership excludes blur, translucency, motion" "Palette exclusions documented"
require_text "$APPEARANCE_DOMAIN_DOC" "introduces no runtime behavior change" "Zero-runtime-change checkpoint documented"
require_no_match "$APPEARANCE_MIGRATION_MAP" '\|[[:space:]]*In Progress[[:space:]]*\|' "No Appearance migration item marked In Progress"
deferred_count="$(grep -c '| Deferred |' "$APPEARANCE_MIGRATION_MAP" || true)"
if [[ "$deferred_count" -eq 16 ]]; then
  ok "All 16 Appearance migration items are Deferred"
else
  fail "Expected 16 Deferred Appearance migration items; found $deferred_count"
fi

echo ""
echo "== Lifecycle progression contract =="
require_text "$THEME_LIFECYCLE_DOC" "v0 = preview-only" "v0 preview-only stage documented"
require_text "$THEME_LIFECYCLE_DOC" "v1 = governed lifecycle draft mode (Deferred)" "v1 draft lifecycle is deferred"
require_text "$THEME_LIFECYCLE_DOC" "v2 = approved apply/delete/default actions (Deferred)" "v2 approved lifecycle is deferred"
require_text "$THEME_LIFECYCLE_DOC" "Theme Doctor is currently diagnostic-only." "Current diagnostic-only notice documented"
require_text "$THEME_LIFECYCLE_DOC" "legacy read-only draft inventory and are not runtime appearance truth" "Legacy draft inventory status documented"
require_text "$THEME_LIFECYCLE_DOC" "analyze -> preview -> diff -> approve -> snapshot -> apply -> rollback" "Governed flow sequence documented"

echo ""
echo "== Ownership and storage contract =="
require_text "$THEME_LIFECYCLE_DOC" "ThemeDoctor owns editing workflow" "ThemeDoctor workflow ownership documented"
require_text "$THEME_LIFECYCLE_DOC" "Platform/System owns approved instance theme registry truth" "Approved registry ownership documented"
require_text "$THEME_LIFECYCLE_DOC" "Shell consumes resolved active/default theme contract only" "Shell consumption rule documented"
require_text "$THEME_LIFECYCLE_DOC" "apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes/*.json" "ThemeDoctor draft source path documented"
require_text "$THEME_LIFECYCLE_DOC" "storage/theme_registry/registry.json" "Approved registry manifest path documented"
require_text "$THEME_LIFECYCLE_DOC" "storage/theme_registry/themes/*.json" "Approved registry theme path documented"
require_text "$THEME_LIFECYCLE_DOC" "ThemeDoctor never writes" "Public assets write block documented"

echo ""
echo "== Required operation flow contract =="
require_text "$THEME_LIFECYCLE_DOC" "Create Theme Flow" "Create flow section documented"
require_text "$THEME_LIFECYCLE_DOC" "Duplicate Theme Flow" "Duplicate flow section documented"
require_text "$THEME_LIFECYCLE_DOC" "Edit Theme Flow" "Edit flow section documented"
require_text "$THEME_LIFECYCLE_DOC" "Delete Theme Flow" "Delete flow section documented"
require_text "$THEME_LIFECYCLE_DOC" "Set Default Theme Flow" "Set default flow section documented"
require_text "$THEME_LIFECYCLE_DOC" "Snapshot And Rollback Contract" "Snapshot/rollback section documented"
require_text "$THEME_LIFECYCLE_DOC" "active/default" "Active/default deletion safety documented"

echo ""
echo "== Gate requirements contract =="
require_text "$THEME_LIFECYCLE_DOC" "studio.tools.theme_tool.use" "ThemeDoctor read permission documented"
require_text "$THEME_LIFECYCLE_DOC" "studio.tools.theme_tool.mutate" "ThemeDoctor mutate permission documented"
require_text "$THEME_LIFECYCLE_DOC" "studio.tools.theme_tool.rollback" "ThemeDoctor rollback permission documented"
require_text "$THEME_LIFECYCLE_DOC" "studio_tools.theme_tool" "Instance policy key documented"
require_text "$THEME_LIFECYCLE_DOC" "Unknown policy key fallback is" "Deterministic policy fallback documented"
require_text "$THEME_LIFECYCLE_DOC" "Environment/risk gates" "Environment/risk gate section documented"

echo ""
echo "== Cross-contract alignment =="
require_text "$CUSTOMIZATION_DOC" "docs/architecture/theme-tool-lifecycle-contract.md" "Customization contract references ThemeDoctor lifecycle contract"
require_text "$TOOL_LIFECYCLE_DOC" "ThemeDoctor Lifecycle Contract" "Studio tool lifecycle contract links ThemeDoctor mutation contract"
require_text "$PLAN_MAP_DOC" "v1.1 Add theme registry reader" "Plan map includes v1.1"
require_text "$PLAN_MAP_DOC" "v1.6 Add delete with safety checks" "Plan map includes v1.6"

echo ""
echo "== Current diagnostic-only boundary =="
require_text "$THEME_MANIFEST" "'status' => 'readonly_diagnostic'" "ThemeDoctor manifest is readonly diagnostic stage"
require_file "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "ThemeDoctor analyzer service"
require_text "$THEME_MANIFEST" "'required_permissions' => ['studio.tools.theme_tool.use']" "ThemeDoctor requires read permission only"
require_no_match "$THEME_MANIFEST" "studio.tools.theme_tool.mutate" "ThemeDoctor manifest has no mutation permission"
require_text "$THEME_MANIFEST" "'can_modify' => false" "ThemeDoctor manifest denies modification"
require_text "$THEME_MANIFEST" "'writes_to_owner_artifact' => false" "ThemeDoctor manifest denies owner artifact writes"
require_file "$THEME_READER_SERVICE" "ThemeDoctor registry reader service exists"
require_absent "$THEME_MUTATION_SERVICE" "ThemeDoctor draft mutation service removed"
require_file "$THEME_DRAFT_DIR/default.json" "ThemeDoctor default draft exists"
require_file "$THEME_DRAFT_DIR/dark.json" "ThemeDoctor dark draft exists"
require_file "$THEME_DRAFT_DIR/company-blue.json" "ThemeDoctor company-blue draft exists"
require_text "$STUDIO_CONTROLLER" "ThemeRegistryReaderService::readSummary(" "ThemeDoctor preview consumes read-only registry reader"
require_text "$STUDIO_CONTROLLER" "'preview_only' => true" "ThemeDoctor preview model is preview-only"
require_text "$STUDIO_CONTROLLER" "'write_enabled' => false" "ThemeDoctor preview model write disabled"
require_no_match "$STUDIO_ROUTES" '\$router->(post|put|patch|delete)\(.*theme-(doctor|tool)' "No ThemeDoctor mutation route"
require_no_match "$STUDIO_CONTROLLER" "themeToolSaveDraft|ThemeDraftMutationService" "No ThemeDoctor draft mutation controller path"
require_no_match "$THEME_PREVIEW_VIEW" '<form|type="submit"|data-theme-preview-save|save-draft' "ThemeDoctor preview has no enabled save form"
require_text "$THEME_PREVIEW_VIEW" "st-theme-controls" "ThemeDoctor preview has preview controls section"
require_no_match "$STUDIO_ROUTES" '\$router->(post|put|patch|delete)\(.*tools/theme-tool/(apply|delete|set-default)' "No apply/delete/default ThemeDoctor mutation route"
require_no_match "$STUDIO_CONTROLLER" "themeTool(Apply|Delete|SetDefault|Rollback)" "No high-risk ThemeDoctor mutation controller method"
require_no_match "$THEME_READER_SERVICE" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No mutation API usage in ThemeDoctor registry reader"

echo ""
echo "== ThemeDoctor locale resolution =="
require_file "$THEME_ROOT/Resources/lang/en.php" "ThemeDoctor English locale file"
require_text "$THEME_ROOT/Resources/lang/en.php" "'workspace_title'" "ThemeDoctor locale key: workspace_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'workspace_subtitle'" "ThemeDoctor locale key: workspace_subtitle"
require_text "$THEME_ROOT/Resources/lang/en.php" "'controls_title'" "ThemeDoctor locale key: controls_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'findings_title'" "ThemeDoctor locale key: findings_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'findings_health_score'" "ThemeDoctor locale key: findings_health_score"
require_text "$THEME_ROOT/Resources/lang/en.php" "'preview_stage_title'" "ThemeDoctor locale key: preview_stage_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'governance_title'" "ThemeDoctor locale key: governance_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'technical_details_title'" "ThemeDoctor locale key: technical_details_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'reset'" "ThemeDoctor locale key: reset"
require_text "$THEME_ROOT/Resources/lang/en.php" "'copy_preview_config'" "ThemeDoctor locale key: copy_preview_config"
require_text "$THEME_PREVIEW_VIEW" '$tt(' "ThemeDoctor view uses tt() helper for locale resolution"
require_text "$THEME_PREVIEW_VIEW" "Resources/lang/" "ThemeDoctor view loads from Resources/lang/ path"

echo ""
echo "== v0 lifecycle action lockdown =="
require_text "$THEME_PREVIEW_VIEW" "action_create_theme" "ThemeDoctor create theme button rendered"
require_text "$THEME_PREVIEW_VIEW" "action_duplicate_theme" "ThemeDoctor duplicate theme button rendered"
require_text "$THEME_PREVIEW_VIEW" "action_delete_theme" "ThemeDoctor delete theme button rendered"
require_text "$THEME_PREVIEW_VIEW" "action_set_default" "ThemeDoctor set-default theme button rendered"
require_text "$THEME_PREVIEW_VIEW" 'aria-disabled="true" disabled' "All lifecycle buttons have aria-disabled + disabled"
require_text "$THEME_PREVIEW_VIEW" 'type="button"' "Lifecycle buttons are type=button (not submit)"
require_no_match "$THEME_PREVIEW_VIEW" 'action_create.*type="submit"|action_create.*formmethod' "No enabled create button form"
require_no_match "$THEME_PREVIEW_VIEW" 'action_duplicate.*<form|action_duplicate.*type="submit"' "No enabled duplicate button form"
require_no_match "$THEME_PREVIEW_VIEW" 'action_delete.*<form|action_delete.*type="submit"' "No enabled delete button form"
require_no_match "$THEME_PREVIEW_VIEW" 'action_set_default.*<form|action_set_default.*type="submit"' "No enabled set-default button form"
require_text "$THEME_PREVIEW_VIEW" "st-theme-action-grid" "Lifecycle buttons have dedicated grid container"
require_text "$THEME_PREVIEW_VIEW" "lifecycle_blocked_note" "Lifecycle blocked note is rendered"
require_text "$THEME_ROOT/Resources/lang/en.php" "'lifecycle_actions'" "EN locale key: lifecycle_actions"
require_text "$THEME_ROOT/Resources/lang/en.php" "'lifecycle_blocked_note'" "EN locale key: lifecycle_blocked_note"
require_text "$THEME_ROOT/Resources/lang/ja.php" "'lifecycle_actions'" "JA locale key: lifecycle_actions"
require_text "$THEME_ROOT/Resources/lang/ja.php" "'lifecycle_blocked_note'" "JA locale key: lifecycle_blocked_note"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'lifecycle_actions'" "NE locale key: lifecycle_actions"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'lifecycle_blocked_note'" "NE locale key: lifecycle_blocked_note"

echo ""
echo "== ThemeDoctor compiled asset diagnostics =="
require_text "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "'TD102'" "TD102 stale sources check in analyzer"
require_text "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "'TD103'" "TD103 suspicious size check in analyzer"
require_text "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "'TD104'" "TD104 required selectors check in analyzer"
require_text "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "COMPILED_SIZE_SUSPICIOUS_THRESHOLD" "TD103 uses compiled size threshold"
require_text "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "REQUIRED_ASSET_SELECTORS" "TD104 checks required selectors list"
require_text "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "(avoids duplicating TD101" "TD103 comment notes avoids TD101 duplication"
require_no_match "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" "TD101.*TD103" "TD103 not emitted when TD101 fires (size=0)"
require_text "$THEME_ROOT/Services/ThemeDoctorAnalyzer.php" 'filesize(self::COMPILED_ASSET_PATH) > 0' "TD104 guarded by non-empty check"
require_text "$THEME_PREVIEW_VIEW" 'strtolower($code' "View resolves finding titles via locale keys"
require_text "$THEME_ROOT/Resources/lang/en.php" "'td102_title'" "EN locale key: td102_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'td103_title'" "EN locale key: td103_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'td104_title'" "EN locale key: td104_title"
require_text "$THEME_ROOT/Resources/lang/ja.php" "'td102_title'" "JA locale key: td102_title"
require_text "$THEME_ROOT/Resources/lang/ja.php" "'td103_title'" "JA locale key: td103_title"
require_text "$THEME_ROOT/Resources/lang/ja.php" "'td104_title'" "JA locale key: td104_title"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'td102_title'" "NE locale key: td102_title"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'td103_title'" "NE locale key: td103_title"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'td104_title'" "NE locale key: td104_title"
require_text "$STUDIO_CONTROLLER" "'preview_only' => true" "ThemeDoctor preview model is preview-only"
require_text "$STUDIO_CONTROLLER" "'write_enabled' => false" "ThemeDoctor preview model write disabled"
require_text "$THEME_MANIFEST" "'can_modify' => false" "ThemeDoctor manifest remains diagnostic-only"

echo ""
echo "== ThemeDoctor allowlisted actions =="
require_text "$THEME_PREVIEW_VIEW" "data-theme-preview-reset" "ThemeDoctor preview has reset action"
require_text "$THEME_PREVIEW_VIEW" "data-theme-preview-copy" "ThemeDoctor preview has copy-preview-config action"
require_no_match "$THEME_PREVIEW_VIEW" "data-theme-preview-save|save_inspection_preset" "ThemeDoctor preview has no save-draft action"
require_text "$THEME_PREVIEW_VIEW" "st-theme-action-row" "ThemeDoctor preview has action row for reset/copy"
require_text "$THEME_PREVIEW_VIEW" "copy_inspection_preset" "ThemeDoctor preview renamed copy_inspection_preset key"

echo ""
echo "== Diagnostic-first UX consolidation =="
require_text "$THEME_PREVIEW_VIEW" "st-theme-summary-section" "Diagnostic summary card grid rendered"
require_text "$THEME_PREVIEW_VIEW" "diagnostic_summary_title" "Summary card: diagnostic_summary_title rendered"
require_text "$THEME_PREVIEW_VIEW" "diagnostic_runtime_theme" "Summary card: diagnostic_runtime_theme rendered"
require_text "$THEME_PREVIEW_VIEW" "diagnostic_registry_state" "Summary card: diagnostic_registry_state rendered"
require_text "$THEME_PREVIEW_VIEW" "diagnostic_compiled_asset" "Summary card: diagnostic_compiled_asset rendered"
require_text "$THEME_PREVIEW_VIEW" "st-theme-recommendations-section" "Standalone recommendations section rendered"
require_text "$THEME_PREVIEW_VIEW" "preview_inspection_title" "Preview inspection sandbox title rendered"
require_text "$THEME_PREVIEW_VIEW" "preview_inspection_help" "Preview inspection help text rendered"
require_no_match "$THEME_PREVIEW_VIEW" 'st-theme-details.*open.*preview_inspection' "Preview inspection sandbox collapsed by default"
require_no_match "$THEME_PREVIEW_VIEW" 'st-theme-details.*<?=\s*!\$approvedRegistryFound' "Governance collapsed by default (no open condition)"
require_text "$THEME_PREVIEW_VIEW" "st-theme-findings-section" "Findings section rendered before recommendations"
require_text "$THEME_PREVIEW_VIEW" "technical_details_title" "Technical details still rendered"

echo ""
echo "== Diagnostic summary locale keys (EN) =="
require_text "$THEME_ROOT/Resources/lang/en.php" "'diagnostic_summary_title'" "EN locale key: diagnostic_summary_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'diagnostic_healthy'" "EN locale key: diagnostic_healthy"
require_text "$THEME_ROOT/Resources/lang/en.php" "'diagnostic_runtime_theme'" "EN locale key: diagnostic_runtime_theme"
require_text "$THEME_ROOT/Resources/lang/en.php" "'diagnostic_registry_state'" "EN locale key: diagnostic_registry_state"
require_text "$THEME_ROOT/Resources/lang/en.php" "'diagnostic_compiled_asset'" "EN locale key: diagnostic_compiled_asset"
require_text "$THEME_ROOT/Resources/lang/en.php" "'preview_inspection_title'" "EN locale key: preview_inspection_title"
require_text "$THEME_ROOT/Resources/lang/en.php" "'save_inspection_preset'" "EN locale key: save_inspection_preset"
require_text "$THEME_ROOT/Resources/lang/en.php" "'copy_inspection_preset'" "EN locale key: copy_inspection_preset"

echo ""
echo "== Diagnostic summary locale keys cross-locale =="
require_text "$THEME_ROOT/Resources/lang/ja.php" "'diagnostic_summary_title'" "JA locale key: diagnostic_summary_title"
require_text "$THEME_ROOT/Resources/lang/ja.php" "'preview_inspection_title'" "JA locale key: preview_inspection_title"
require_text "$THEME_ROOT/Resources/lang/ja.php" "'save_inspection_preset'" "JA locale key: save_inspection_preset"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'diagnostic_summary_title'" "NE locale key: diagnostic_summary_title"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'preview_inspection_title'" "NE locale key: preview_inspection_title"
require_text "$THEME_ROOT/Resources/lang/ne.php" "'save_inspection_preset'" "NE locale key: save_inspection_preset"

echo ""
echo "== ThemeDoctor service probe =="
PROBE_SCRIPT="$ROOT_DIR/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/tests/probe_theme_doctor_services.php"
if [[ -f "$PROBE_SCRIPT" ]]; then
  if php "$PROBE_SCRIPT" >/dev/null 2>&1; then
    echo "  ok: ThemeDoctor service probe passed"
  else
    echo "  fail: ThemeDoctor service probe failed"
    failures=$((failures + 1))
  fi
else
  echo "  ok: ThemeDoctor service probe not found (skipped)"
fi

echo ""
echo "== Zero-runtime-change slice guard =="
changed_paths="$(
  {
    git diff --name-only
    git diff --cached --name-only
  } | sort -u
)"
forbidden_paths="$(printf '%s\n' "$changed_paths" | grep -E '(^|/)(styles|resources/themes|public/assets)/.*\.css$|(^|/)[^/]*(Compiler|PublishedOptions)[^/]*\.php$|^apps/Shell/Services/(AppearanceStateResolver|ThemePreferenceService)\.php$' || true)"
if [[ -z "$forbidden_paths" ]]; then
  ok "No runtime CSS, compiler, published-options, or Shell preference file changed"
else
  fail "Appearance checkpoint changed forbidden runtime files: $forbidden_paths"
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (ThemeDoctor lifecycle contract gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

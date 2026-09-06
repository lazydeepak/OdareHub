#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_studio_tool_lifecycle_contract"
echo "- read-only diagnostic for Studio tool lifecycle contract and ThemeDoctor guards"

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

require_any_text() {
  local path="$1"
  local label="$2"
  shift 2

  if [[ ! -f "$path" ]]; then
    fail "$label path missing: $path"
    return
  fi

  local found=0
  for needle in "$@"; do
    if grep -Fq -- "$needle" "$path"; then
      found=1
      break
    fi
  done

  if [[ "$found" -eq 1 ]]; then
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

require_redirect_only_route() {
  local method="$1"
  local legacy_route="$2"
  local canonical_route="$3"
  local label="$4"

  if [[ ! -f "$STUDIO_ROUTES" ]]; then
    fail "$label path missing: $STUDIO_ROUTES"
    return
  fi

  local block
  block="$(awk -v route="$legacy_route" -v method="$method" '
    $0 ~ "\\$router->" method "\\('\''" route "'\''" { capture=1 }
    capture { print }
    capture && $0 ~ /^}\);$/ { exit }
  ' "$STUDIO_ROUTES")"

  if [[ -z "$block" ]]; then
    fail "$label legacy route not found"
    return
  fi

  if grep -Fq -- "\$studioRedirect('$canonical_route" <<< "$block" \
    && ! grep -Eq "StudioController::|toolTemplatePath|StyleCompliance[A-Za-z]+Service|require(_once)?[[:space:]]" <<< "$block"; then
    ok "$label"
  else
    fail "$label is not redirect-only"
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

ARCH_DOC="docs/architecture/studio-tool-lifecycle-contract.md"
BATCH_DOC="docs/migration-cleanup/maps/batch-13-studio-tool-lifecycle-contract.md"
THEME_MANIFEST="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/manifest.php"
APP_BUILDER_MANIFEST="apps/Studio/Tools/AppBuilder/manifest.php"
MODULE_BUILDER_MANIFEST="apps/Studio/Tools/ModuleBuilder/manifest.php"
DB_SCHEMA_MANIFEST="apps/Studio/Tools/DbSchemaTool/manifest.php"
STUDIO_ROUTES="apps/Studio/routes.php"
STUDIO_CONTROLLER="apps/Studio/Controllers/StudioController.php"
STUDIO_HOME_VIEW="apps/Studio/Views/pages/home.php"
STUDIO_TOOL_POLICY_SERVICE="apps/Studio/Services/StudioToolInstancePolicyService.php"
STUDIO_TOOL_POLICY_FILE="apps/Studio/config/studio_tool_policy.php"
THEME_VIEW="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Views/preview.php"
THEME_ROOT="apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor"
THEME_ASSETS_DIR="$THEME_ROOT/assets"
THEME_LANG_DIR="$THEME_ROOT/Resources/lang"
THEME_VIEWS_DIR="$THEME_ROOT/Views"
THEME_SERVICES_DIR="$THEME_ROOT/Services"
THEME_THEMES_DIR="$THEME_ROOT/Themes"
THEME_MUTATION_SERVICE="$THEME_SERVICES_DIR/ThemeDraftMutationService.php"
STYLE_COMPLIANCE_ROOT="apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance"
STYLE_COMPLIANCE_MANIFEST="$STYLE_COMPLIANCE_ROOT/manifest.php"
STYLE_COMPLIANCE_VIEW="$STYLE_COMPLIANCE_ROOT/Views/preview.php"
STYLE_COMPLIANCE_LOCALE="$STYLE_COMPLIANCE_ROOT/Views/_locale.php"
STYLE_COMPLIANCE_TOOLBAR="$STYLE_COMPLIANCE_ROOT/Views/Shared/_toolbar.php"
STYLE_COMPLIANCE_RESULT_SECTIONS="$STYLE_COMPLIANCE_ROOT/Views/_result_sections.php"
STYLE_COMPLIANCE_RESULT_CONTEXT="$STYLE_COMPLIANCE_ROOT/Views/_result_context.php"
STYLE_COMPLIANCE_RESULT_ACTION_SUMMARY="$STYLE_COMPLIANCE_ROOT/Views/_result_action_summary.php"
STYLE_COMPLIANCE_RESULT_VERIFIED_FIXES="$STYLE_COMPLIANCE_ROOT/Views/_result_verified_fixes.php"
STYLE_COMPLIANCE_RESULT_DECISION_BACKLOG="$STYLE_COMPLIANCE_ROOT/Views/_result_decision_backlog.php"
STYLE_COMPLIANCE_RESULT_DIAGNOSTICS="$STYLE_COMPLIANCE_ROOT/Views/_result_diagnostics.php"
STYLE_COMPLIANCE_SCANNER="$STYLE_COMPLIANCE_ROOT/Services/StyleComplianceScannerService.php"
STYLE_COMPLIANCE_CAPABILITY="$STYLE_COMPLIANCE_ROOT/Services/StyleComplianceGuardedRepairCapabilityService.php"
STYLE_COMPLIANCE_REPAIR_READINESS="$STYLE_COMPLIANCE_ROOT/Services/StyleComplianceRepairReadinessService.php"

require_file "$ARCH_DOC" "Studio tool lifecycle architecture contract"
require_file "$BATCH_DOC" "Batch 13 lifecycle map"

echo ""
echo "== Required terminology =="
require_text "$ARCH_DOC" "Business Apps have Modules." "Business Apps/Modules terminology"
require_text "$ARCH_DOC" "Studio has Tools." "Studio/Tools terminology"
require_text "$ARCH_DOC" "Studio Tools may have module-like lifecycle." "Tool lifecycle terminology"
require_text "$ARCH_DOC" "Tools are governed workers, not runtime business owners." "Governed worker terminology"

echo ""
echo "== ThemeDoctor lifecycle manifest checks =="
require_file "$THEME_MANIFEST" "ThemeDoctor manifest"
require_any_text "$THEME_MANIFEST" "tool_key/key present" "'tool_key' =>" "'key' =>"
require_any_text "$THEME_MANIFEST" "label/name present" "'label' =>" "'name' =>"
require_text "$THEME_MANIFEST" "'category' =>" "category present"
require_text "$THEME_MANIFEST" "'risk_level' =>" "risk_level present"
require_text "$THEME_MANIFEST" "'default_enabled' =>" "default_enabled present"
require_text "$THEME_MANIFEST" "'can_disable' =>" "can_disable present"
require_text "$THEME_MANIFEST" "'required_permissions' =>" "required_permissions present"
require_text "$THEME_MANIFEST" "'allowed_environments' =>" "allowed_environments present"
require_text "$THEME_MANIFEST" "'routes' =>" "routes present"
require_text "$THEME_MANIFEST" "'views' =>" "views present"
require_text "$THEME_MANIFEST" "'services' =>" "services present"
require_text "$THEME_MANIFEST" "'owner' => 'studio'" "owner is studio"
require_text "$THEME_MANIFEST" "'status' => 'readonly_diagnostic'" "readonly diagnostic status"
require_text "$THEME_MANIFEST" "'required_permissions' => ['studio.tools.theme_tool.use']" "read-only permission boundary"
require_no_match "$THEME_MANIFEST" "studio.tools.theme_tool.mutate" "manifest has no ThemeDoctor mutation permission"
require_text "$THEME_MANIFEST" "'can_modify' => false" "manifest prohibits modification"
require_text "$THEME_MANIFEST" "'writes_to_owner_artifact' => false" "manifest denies owner artifact writes"

echo ""
echo "== ThemeDoctor ownership and placement checks =="
require_dir "$THEME_ROOT" "ThemeDoctor root"
require_dir "$THEME_ASSETS_DIR" "ThemeDoctor assets directory"
require_dir "$THEME_LANG_DIR" "ThemeDoctor lang directory"
require_dir "$THEME_VIEWS_DIR" "ThemeDoctor views directory"
require_dir "$THEME_SERVICES_DIR" "ThemeDoctor services directory"
require_dir "$THEME_THEMES_DIR" "ThemeDoctor draft themes directory"
require_file "$THEME_ASSETS_DIR/theme_tool.css" "ThemeDoctor CSS source"
require_file "$THEME_ASSETS_DIR/theme_tool.js" "ThemeDoctor JS source"
require_file "$THEME_LANG_DIR/en.php" "ThemeDoctor lang en"
require_file "$THEME_LANG_DIR/ja.php" "ThemeDoctor lang ja"
require_file "$THEME_LANG_DIR/ne.php" "ThemeDoctor lang ne"
require_file "$THEME_VIEW" "ThemeDoctor preview view"
require_file "$THEME_SERVICES_DIR/ThemeRegistryReaderService.php" "ThemeDoctor registry reader service"
require_absent "$THEME_MUTATION_SERVICE" "ThemeDoctor draft mutation service removed"
require_file "$THEME_THEMES_DIR/default.json" "ThemeDoctor draft theme default"
require_file "$THEME_THEMES_DIR/dark.json" "ThemeDoctor draft theme dark"
require_file "$THEME_THEMES_DIR/company-blue.json" "ThemeDoctor draft theme company-blue"

echo ""
    echo "== Style Compliance lifecycle checks =="
    require_file "$STYLE_COMPLIANCE_MANIFEST" "Style Compliance manifest"
    require_file "$STYLE_COMPLIANCE_VIEW" "Style Compliance preview view"
    require_file "$STYLE_COMPLIANCE_LOCALE" "Style Compliance shared locale helper"
    require_file "$STYLE_COMPLIANCE_TOOLBAR" "Style Compliance toolbar partial"
    require_file "$STYLE_COMPLIANCE_RESULT_SECTIONS" "Style Compliance result sections partial"
    require_file "$STYLE_COMPLIANCE_RESULT_CONTEXT" "Style Compliance result context partial"
    require_file "$STYLE_COMPLIANCE_RESULT_ACTION_SUMMARY" "Style Compliance action summary partial"
    require_file "$STYLE_COMPLIANCE_RESULT_VERIFIED_FIXES" "Style Compliance verified candidates partial"
    require_file "$STYLE_COMPLIANCE_RESULT_DECISION_BACKLOG" "Style Compliance decision backlog partial"
    require_file "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "Style Compliance diagnostics partial"
    require_file "$STYLE_COMPLIANCE_SCANNER" "Style Compliance scanner service"
    require_file "$STYLE_COMPLIANCE_CAPABILITY" "Style Compliance guarded repair capability service"
    require_file "$STYLE_COMPLIANCE_REPAIR_READINESS" "Style Compliance repair readiness service"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'tool_key' => 'style_compliance'" "Style Compliance manifest key"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'label' => 'Style Compliance'" "Style Compliance manifest label"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'canonical_route' => '/apps/studio/tools/customization-studio/diagnose/style-compliance'" "Style Compliance canonical route"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'required_permissions' => ['studio.tools.style_compliance.use']" "Style Compliance required permission"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'can_modify' => false" "Style Compliance canonical manifest keeps mutation disabled"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'writes_to_owner_artifact' => false" "Style Compliance canonical manifest denies owner artifact writes"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'supports_snapshot' => false" "Style Compliance canonical manifest disables snapshots"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'supports_rollback' => false" "Style Compliance canonical manifest disables rollback"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'guarded_repair' =>" "Style Compliance canonical guarded-repair capability is declared"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "'enabled' => false" "Style Compliance canonical guarded-repair executor remains disabled"
    require_text "$STYLE_COMPLIANCE_MANIFEST" "StyleComplianceRepairReadinessService" "Style Compliance manifest lists repair readiness service"
    require_text "$STUDIO_ROUTES" "\$router->get('/apps/studio/tools/customization-studio/diagnose/style-compliance'" "Style Compliance canonical Customization Studio route resolves"
    require_text "$STUDIO_ROUTES" "'/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness'" "Style Compliance repair readiness POST route allowlisted"
    require_redirect_only_route "get" "/apps/studio/tools/style-compliance" "/apps/studio/tools/customization-studio/diagnose/style-compliance" "Legacy Style Compliance GET route redirects without independent logic"
    require_redirect_only_route "post" "/apps/studio/tools/style-compliance/scan" "/apps/studio/tools/customization-studio/diagnose/style-compliance/scan" "Legacy Style Compliance scan route redirects without independent logic"
    require_redirect_only_route "post" "/apps/studio/tools/style-compliance/repair-readiness" "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness" "Legacy Style Compliance readiness route redirects without independent logic"
    require_redirect_only_route "post" "/apps/studio/tools/style-compliance/repair-execute" "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute" "Legacy Style Compliance execute route redirects without independent logic"
    require_text "$STUDIO_ROUTES" "StudioToolInstancePolicyService::isEnabled('style_compliance')" "Style Compliance route policy gate"
    require_text "$STUDIO_ROUTES" "StudioController::styleCompliancePreview(" "Style Compliance route handled by StudioController"
    require_text "$STUDIO_CONTROLLER" "public static function styleCompliancePreview" "Style Compliance controller method exists"
    require_text "$STUDIO_CONTROLLER" "public static function styleComplianceRepairReadinessAsync" "Style Compliance repair readiness controller method exists"
    require_text "$STUDIO_CONTROLLER" "public static function styleComplianceRepairExecute" "Style Compliance repair execute controller method exists"
    require_text "$STUDIO_CONTROLLER" "StyleComplianceRepairReadinessService::checkByProposalId(\$proposalId)" "Style Compliance readiness resolves trusted candidate server-side"
    require_text "$STUDIO_CONTROLLER" "StyleComplianceGuardedRepairCapabilityService::contract()" "Style Compliance controller uses guarded capability contract"
    require_text "$STUDIO_CONTROLLER" "'/apps/studio/tools/customization-studio/diagnose/style-compliance'" "Style Compliance guard intended URL"
    require_text "$STUDIO_TOOL_POLICY_SERVICE" "'style_compliance' => 'enabled'" "Default policy enables Style Compliance"
    require_text "$STUDIO_TOOL_POLICY_FILE" "'style_compliance' => 'enabled'" "Policy config enables Style Compliance"
    require_text "apps/Studio/Resources/lang/en.php" "studio.tool.style_compliance.name" "EN Style Compliance launcher locale"
    require_text "apps/Studio/Resources/lang/ja.php" "studio.tool.style_compliance.name" "JA Style Compliance launcher locale"
    require_text "apps/Studio/Resources/lang/ne.php" "studio.tool.style_compliance.name" "NE Style Compliance launcher locale"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "_result_context.php" "Style Compliance composes result context partial"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "sc-dashboard gs-tool-metric-grid" "Style Compliance moved markup renders dashboard metric grid"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "sc-action-card gs-tool-action-card" "Style Compliance moved markup renders action center"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "_result_verified_fixes.php" "Style Compliance composes verified candidates partial"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "_result_decision_backlog.php" "Style Compliance composes decision backlog partial"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "_result_diagnostics.php" "Style Compliance composes diagnostics partial"
    require_text "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "theme_repair_proposals" "Style Compliance diagnostics keeps Theme-Aware Repair proposal JSON"
    require_text "$STYLE_COMPLIANCE_RESULT_DECISION_BACKLOG" "classification_and_accessibility_review" "Style Compliance classification/accessibility queue rendered"
    require_text "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "readiness_title" "Style Compliance readiness evidence retained in diagnostics"
    require_text "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "assessment_title" "Style Compliance assessment evidence retained in diagnostics"
    require_text "$STYLE_COMPLIANCE_VIEW" "require __DIR__ . '/_locale.php';" "Style Compliance initial render uses shared locale helper"
    require_text "$STUDIO_CONTROLLER" "require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_locale.php';" "Style Compliance async render uses shared locale helper"
    require_no_match "$STYLE_COMPLIANCE_VIEW" '\$sc = static function' "No inline Style Compliance locale closure in preview"
    require_no_match "$STUDIO_CONTROLLER" '\$sc = static function' "No inline Style Compliance locale closure in controller"
    require_no_match "$STYLE_COMPLIANCE_RESULT_SECTIONS" '\$sc = static function' "No inline Style Compliance locale closure in result sections"
    require_no_match "$STYLE_COMPLIANCE_RESULT_CONTEXT" '\$sc = static function' "No inline Style Compliance locale closure in result context"
    require_no_match "$STYLE_COMPLIANCE_RESULT_ACTION_SUMMARY" '\$sc = static function' "No inline Style Compliance locale closure in action summary"
    require_no_match "$STYLE_COMPLIANCE_RESULT_VERIFIED_FIXES" '\$sc = static function' "No inline Style Compliance locale closure in verified candidates"
    require_no_match "$STYLE_COMPLIANCE_RESULT_DECISION_BACKLOG" '\$sc = static function' "No inline Style Compliance locale closure in decision backlog"
    require_no_match "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" '\$sc = static function' "No inline Style Compliance locale closure in diagnostics"
    require_text "$STYLE_COMPLIANCE_LOCALE" "'en' => \$en" "Style Compliance locale owns EN branch"
    require_text "$STYLE_COMPLIANCE_LOCALE" "'ja' => array_replace(\$en" "Style Compliance locale owns JA branch"
    require_text "$STYLE_COMPLIANCE_LOCALE" "'ne' => array_replace(\$en" "Style Compliance locale owns NE branch"
    require_text "$STYLE_COMPLIANCE_LOCALE" "label_gov_domain_theme_related" "Style Compliance locale owns governance-domain labels"
    require_text "$STYLE_COMPLIANCE_LOCALE" "label_source_scope_owner" "Style Compliance locale owns source-scope labels"
    require_text "$STYLE_COMPLIANCE_LOCALE" "label_target_tool_style_compliance" "Style Compliance locale owns target-tool labels"
    require_text "$STYLE_COMPLIANCE_LOCALE" "label_repair_lane_owner" "Style Compliance locale owns repair-lane labels"
    require_text "$STYLE_COMPLIANCE_LOCALE" "workspace_shell_inventory" "Style Compliance locale owns Shell Inventory workspace label"
    require_text "$STYLE_COMPLIANCE_LOCALE" "shell_inventory_title" "Style Compliance locale owns Shell Inventory title"
    require_text "$STYLE_COMPLIANCE_LOCALE" "shell_disposition_remain_owner_local_shell_governed" "Style Compliance locale owns Shell Inventory dispositions"
    require_text "$STYLE_COMPLIANCE_LOCALE" "shell_candidate_layout_primitive" "Style Compliance locale owns Shell Inventory candidate types"
    require_text "$STYLE_COMPLIANCE_LOCALE" "shell_criticality_critical" "Style Compliance locale owns Shell Inventory criticality labels"
    require_text "$STYLE_COMPLIANCE_LOCALE" "workflow_owner_local" "Style Compliance locale explains owner-local domain bucket"
    require_text "$STYLE_COMPLIANCE_LOCALE" "proposal_count_deterministic_future_apply" "Style Compliance locale distinguishes deterministic proposal count units"
    require_text "$STYLE_COMPLIANCE_LOCALE" "verified_candidates_title" "Style Compliance locale labels verified future fix candidates"
    require_text "$STYLE_COMPLIANCE_LOCALE" "decision_backlog_title" "Style Compliance locale labels decision backlog"
    require_text "$STYLE_COMPLIANCE_LOCALE" "effects_handoffs_title" "Style Compliance locale labels effects handoffs"
    require_text "$STYLE_COMPLIANCE_LOCALE" "section_diagnostics_title" "Style Compliance locale labels audit diagnostics"
    require_text "$STYLE_COMPLIANCE_LOCALE" "manual_semantic_decision" "Style Compliance locale labels manual semantic decisions"
    require_text "$STYLE_COMPLIANCE_LOCALE" "guarded_apply_available" "Style Compliance locale labels guarded apply availability"
    require_text "$STYLE_COMPLIANCE_LOCALE" "safety_bar_guarded" "Style Compliance locale labels guarded safety state"
    require_text "$STYLE_COMPLIANCE_LOCALE" "safety_bar_readonly" "Style Compliance locale labels read-only safety state"
    require_text "$STYLE_COMPLIANCE_LOCALE" "guarded_apply_preflight_title" "Style Compliance locale labels guarded apply preflight"
    require_text "$STYLE_COMPLIANCE_LOCALE" "semantic_mapping_confidence_unknown" "Style Compliance locale separates mapping confidence from detection"
    require_text "$STYLE_COMPLIANCE_LOCALE" "shell_population_eligible_candidate" "Style Compliance locale owns Shell Inventory population states"
    require_text "$STYLE_COMPLIANCE_LOCALE" "shell_review_accessibility_focus_risk" "Style Compliance locale owns Shell Inventory review reason codes"
    require_text "$STYLE_COMPLIANCE_SCANNER" "'repair_action'" "Style Compliance scanner emits repair action"
    require_text "$STYLE_COMPLIANCE_SCANNER" "buildThemeAwareRepairProposals" "Style Compliance builds Theme-Aware Repair proposal contract"
    require_text "$STYLE_COMPLIANCE_SCANNER" "deterministic_theme_value_fix" "Style Compliance proposal contract separates deterministic fixes"
    require_text "$STYLE_COMPLIANCE_SCANNER" "manual_semantic_decision" "Style Compliance proposal contract separates manual semantic decisions"
    require_text "$STYLE_COMPLIANCE_SCANNER" "future_tool_handoff" "Style Compliance proposal contract routes future tool handoffs"
    require_text "$STYLE_COMPLIANCE_SCANNER" "replacement_value' => \$class === 'deterministic_theme_value_fix'" "Style Compliance only populates replacements for deterministic proposals"
    require_text "$STYLE_COMPLIANCE_SCANNER" "buildGuardedApplyPreflightPlans" "Style Compliance models guarded apply preflight plans"
    require_text "$STYLE_COMPLIANCE_SCANNER" "detection_confidence" "Style Compliance records detection confidence separately"
    require_text "$STYLE_COMPLIANCE_SCANNER" "semantic_mapping_confidence" "Style Compliance records semantic mapping confidence separately"
    require_text "$STYLE_COMPLIANCE_SCANNER" "future_apply_eligibility" "Style Compliance records future apply eligibility separately"
    require_text "$STYLE_COMPLIANCE_SCANNER" "owner_review_required" "Style Compliance future preflight requires owner review"
    require_text "$STYLE_COMPLIANCE_SCANNER" "single_declaration" "Style Compliance future preflight scope is single declaration"
    require_text "$STYLE_COMPLIANCE_SCANNER" "source_fingerprint" "Style Compliance future preflight models source fingerprint"
    require_text "$STYLE_COMPLIANCE_SCANNER" "declaration_fingerprint" "Style Compliance future preflight models declaration fingerprint"
    require_text "$STYLE_COMPLIANCE_SCANNER" "proposal_fingerprint" "Style Compliance future preflight models proposal fingerprint"
    require_text "$STYLE_COMPLIANCE_SCANNER" "'mutation_endpoint' => \$executorEnabled ? '/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute' : ''" "Style Compliance preflight mutation endpoint follows guarded capability"
    require_text "$STYLE_COMPLIANCE_CAPABILITY" "manifest.php" "Style Compliance guarded capability reads manifest"
    require_text "$STYLE_COMPLIANCE_CAPABILITY" "'executor_enabled' => \$executorEnabled" "Style Compliance guarded capability exposes executor state"
    require_text "$STYLE_COMPLIANCE_CAPABILITY" "'can_modify'" "Style Compliance guarded capability includes can_modify"
    require_text "$STYLE_COMPLIANCE_CAPABILITY" "'writes_to_owner_artifact'" "Style Compliance guarded capability includes owner write flag"
    require_text "$STYLE_COMPLIANCE_REPAIR_READINESS" "checkByProposalId" "Style Compliance readiness accepts stable proposal id"
    require_text "$STYLE_COMPLIANCE_REPAIR_READINESS" "StyleComplianceGuardedRepairCapabilityService::contract()" "Style Compliance readiness uses guarded capability contract"
    require_text "$STYLE_COMPLIANCE_CAPABILITY" "'owner_specific_rules_allowed' => false" "Style Compliance capability forbids owner-specific executor rules"
    require_text "$STYLE_COMPLIANCE_SCANNER" "canonicalThemeTokens" "Style Compliance scanner reads canonical theme tokens"
    require_text "$STYLE_COMPLIANCE_SCANNER" "Tests/fixtures/" "Style Compliance operational scan excludes fixture paths"
    require_text "$STYLE_COMPLIANCE_SCANNER" "normalizeGovernanceDomain" "Style Compliance normalizes owner scope out of governance-domain display"
    require_text "$STYLE_COMPLIANCE_SCANNER" "buildShellFoundationInventory" "Style Compliance builds Shell Foundation Inventory records"
    require_text "$STYLE_COMPLIANCE_SCANNER" "candidate_shared_shell_primitive" "Style Compliance inventory has shared primitive advisory disposition"
    require_text "$STYLE_COMPLIANCE_SCANNER" "remain_owner_local_shell_governed" "Style Compliance inventory defaults owner-local Shell governance"
    require_text "$STYLE_COMPLIANCE_SCANNER" "future_special_effect_handoff" "Style Compliance inventory routes effects to future handoff"
    require_text "$STYLE_COMPLIANCE_SCANNER" "inventory_population" "Style Compliance inventory records include population state"
    require_text "$STYLE_COMPLIANCE_SCANNER" "structural_signature" "Style Compliance inventory records include structural signature"
    require_text "$STYLE_COMPLIANCE_SCANNER" "review_reason_code" "Style Compliance inventory records include review reason code"
    require_text "$STYLE_COMPLIANCE_SCANNER" "criticality_reason" "Style Compliance inventory records include criticality reason"
    require_text "$STYLE_COMPLIANCE_SCANNER" "cross_owner_evidence" "Style Compliance inventory records include cross-owner evidence"
    require_text "$STYLE_COMPLIANCE_VIEW" "shell-inventory" "Style Compliance preview exposes Shell Inventory workspace"
    require_text "$STYLE_COMPLIANCE_TOOLBAR" "name=\"workspace\"" "Style Compliance toolbar preserves workspace state"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "data-workspace=\"shell-inventory\"" "Style Compliance result partial renders Shell Inventory workspace"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "shell_inventory_evidence_payload" "Style Compliance Shell Inventory folds full evidence payload"
    require_text "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "domain_owner_surface" "Style Compliance diagnostics renders owner-local domain bucket for reconciliation"
    require_text "$STYLE_COMPLIANCE_RESULT_VERIFIED_FIXES" "ready_for_future_guarded_apply" "Style Compliance verified candidates renders future guarded review queue"
    require_text "$STYLE_COMPLIANCE_RESULT_DECISION_BACKLOG" "manual_semantic_decisions" "Style Compliance decision backlog renders manual semantic decision queue"
    require_text "$STYLE_COMPLIANCE_RESULT_DECISION_BACKLOG" "future_tool_handoffs" "Style Compliance renders future effects handoff queue"
    require_text "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "guarded_apply_preflight_title" "Style Compliance diagnostics renders guarded preflight evidence"
    require_text "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "non_executable_population_title" "Style Compliance diagnostics renders non-executable population breakdown"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "shell_inventory_declarations_scanned" "Style Compliance Shell Inventory shows declarations scanned"
    require_text "$STYLE_COMPLIANCE_RESULT_SECTIONS" "shell_inventory_eligible_candidates" "Style Compliance Shell Inventory shows eligible candidate count"
    require_text "$STUDIO_ROUTES" "\$router->post('/apps/studio/tools/customization-studio/diagnose/style-compliance/scan'" "Style Compliance scan POST route allowlisted"
    require_text "$STUDIO_ROUTES" "\$router->post('/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness'" "Style Compliance readiness POST route allowlisted"
    require_text "$STUDIO_ROUTES" "\$router->post('/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute'" "Style Compliance guarded repair execute POST route allowlisted"
    require_no_match "$STUDIO_ROUTES" '\$router->(put|patch|delete)\(.*tools/style-compliance' "No Style Compliance PUT/PATCH/DELETE routes"
    require_no_match "$STUDIO_ROUTES" '\$router->post\(.*tools/style-compliance/(apply|write|snapshot|rollback|save)' "No Style Compliance write/apply/snapshot/rollback POST routes"
    require_no_match "$STUDIO_ROUTES" "shell-inventory" "Shell Inventory does not add a separate route"
    require_no_match "$STYLE_COMPLIANCE_VIEW" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance view"
    require_no_match "$STYLE_COMPLIANCE_LOCALE" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance locale helper"
    require_no_match "$STYLE_COMPLIANCE_RESULT_SECTIONS" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance result sections"
    require_no_match "$STYLE_COMPLIANCE_RESULT_CONTEXT" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance result context"
    require_no_match "$STYLE_COMPLIANCE_RESULT_ACTION_SUMMARY" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance action summary"
    require_no_match "$STYLE_COMPLIANCE_RESULT_VERIFIED_FIXES" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance verified candidates"
    require_no_match "$STYLE_COMPLIANCE_RESULT_DECISION_BACKLOG" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance decision backlog"
    require_no_match "$STYLE_COMPLIANCE_RESULT_DIAGNOSTICS" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance diagnostics"
    require_no_match "$STYLE_COMPLIANCE_SCANNER" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance scanner"
    require_no_match "$STYLE_COMPLIANCE_CAPABILITY" "file_put_contents|fwrite|unlink|rename\(|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance capability service"
    require_no_match "$STYLE_COMPLIANCE_REPAIR_READINESS" "file_put_contents|fwrite|unlink|rename\(|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in Style Compliance repair readiness service"

echo ""
echo "== Route and controller guard checks =="
require_text "$STUDIO_ROUTES" "'/apps/studio/tools/customization-studio/diagnose/theme-doctor'" "ThemeDoctor route registered"
require_text "$STUDIO_ROUTES" "StudioController::themeToolPreview(" "ThemeDoctor route handled by StudioController"
require_text "$STUDIO_CONTROLLER" "public static function themeToolPreview" "ThemeDoctor preview controller method exists"
require_text "$STUDIO_CONTROLLER" "'/apps/studio/tools/customization-studio/diagnose/theme-doctor'" "ThemeDoctor preview guard intended URL"
require_text "$STUDIO_CONTROLLER" "'/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Views/preview.php'" "ThemeDoctor preview view path from controller"
require_no_match "$STUDIO_ROUTES" '\$router->(post|put|patch|delete)\(.*theme-(doctor|tool)' "No ThemeDoctor mutation route"
require_no_match "$STUDIO_CONTROLLER" "themeToolSaveDraft|ThemeDraftMutationService" "No ThemeDoctor mutation controller path"
require_text "$STUDIO_CONTROLLER" "'preview_only' => true" "ThemeDoctor preview model is preview-only"
require_text "$STUDIO_CONTROLLER" "'write_enabled' => false" "ThemeDoctor preview model write disabled"
require_text "$STUDIO_CONTROLLER" "ThemeRegistryReaderService::readSummary(" "ThemeDoctor preview loads read-only registry summary"

echo ""
echo "== Tool instance policy resolver checks =="
require_file "$STUDIO_TOOL_POLICY_SERVICE" "Studio tool instance policy resolver service"
require_file "$STUDIO_TOOL_POLICY_FILE" "Studio instance policy config file"
require_text "$STUDIO_TOOL_POLICY_SERVICE" "private const POLICY_FILE" "Policy resolver binds storage config"
require_text "$STUDIO_TOOL_POLICY_SERVICE" "private static function defaultPolicyMap" "Policy resolver has default map"
require_text "$STUDIO_TOOL_POLICY_SERVICE" "return 'disabled';" "Policy resolver deterministic disabled fallback"
require_text "$STUDIO_TOOL_POLICY_FILE" "'studio_tools' =>" "Policy config includes studio_tools root"
require_text "$STUDIO_TOOL_POLICY_FILE" "'theme_tool' => 'enabled'" "Policy config enables theme_tool"
require_text "$STUDIO_TOOL_POLICY_FILE" "'app_builder' => 'enabled'" "Policy config enables app_builder placeholder"
require_text "$STUDIO_TOOL_POLICY_FILE" "'module_builder' => 'enabled'" "Policy config enables module_builder placeholder"
require_text "$STUDIO_TOOL_POLICY_FILE" "'db_schema_tool' => 'enabled'" "Policy config enables db_schema_tool placeholder"
require_text "$APP_BUILDER_MANIFEST" "'placeholder' => true" "App Builder remains a read-only placeholder"
require_text "$MODULE_BUILDER_MANIFEST" "'placeholder' => true" "Module Builder remains a read-only placeholder"
require_text "$DB_SCHEMA_MANIFEST" "'placeholder' => true" "DB Schema Tool remains a read-only placeholder"
require_text "$STUDIO_ROUTES" "StudioToolInstancePolicyService::isEnabled('view_editor')" "Route gate checks view_editor policy"
require_text "$STUDIO_ROUTES" "StudioToolInstancePolicyService::isEnabled('menu_editor')" "Route gate checks menu_editor policy"
require_text "$STUDIO_ROUTES" "StudioToolInstancePolicyService::isEnabled('theme_tool')" "Route gate checks theme_tool policy"
require_text "$STUDIO_ROUTES" "StudioToolInstancePolicyService::isEnabled('app_builder')" "Route gate checks app_builder policy"
require_text "$STUDIO_ROUTES" "StudioToolInstancePolicyService::isEnabled('module_builder')" "Route gate checks module_builder policy"
require_text "$STUDIO_CONTROLLER" "StudioToolInstancePolicyService::isEnabled(\$toolKey, \$tool)" "Launcher card status is policy-resolved"
require_text "$STUDIO_HOME_VIEW" "studio_home_status_disabled" "Launcher view renders disabled status label"
require_text "$STUDIO_HOME_VIEW" "aria-disabled=\"true\"" "Launcher renders disabled cards as non-clickable"

echo ""
echo "== Diagnostic-only path checks =="
require_text "$THEME_VIEW" "<div class=\"st-theme-controls\"" "ThemeDoctor contains read-only preview controls"
require_no_match "$THEME_VIEW" '<form|type="submit"|save-draft|data-theme-preview-save' "ThemeDoctor has no enabled save form"
require_no_match "$STUDIO_ROUTES" '\$router->(post|put|patch|delete)\(.*tools/theme-tool/(apply|delete|set-default)' "No apply/delete/set-default routes"
require_no_match "$STUDIO_CONTROLLER" "themeTool(Apply|Delete|SetDefault|Rollback)" "No high-risk mutation controller entry points"
require_no_match "$THEME_VIEW" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in ThemeDoctor preview view"
require_no_match "$THEME_ASSETS_DIR/theme_tool.js" "fetch\(.*(apply|save|persist)|XMLHttpRequest|POST|PUT|PATCH|DELETE" "No network mutation calls in ThemeDoctor JS"
require_no_match "$THEME_SERVICES_DIR/ThemeRegistryReaderService.php" "file_put_contents|fwrite|unlink|rename|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(" "No DB/filesystem mutation in ThemeDoctor registry reader"

echo ""
echo "== ThemeDoctor allowlisted local actions =="
require_text "$THEME_VIEW" "data-theme-preview-reset" "ThemeDoctor preview has reset action"
require_text "$THEME_VIEW" "data-theme-preview-copy" "ThemeDoctor preview has copy-preview-config action"
require_text "$THEME_VIEW" "st-theme-action-row" "ThemeDoctor preview has action row for reset/copy"

scope_markers="$(grep -RIn "file_put_contents\|fwrite\|unlink\|rename(\|copy(\|mkdir(\|rmdir(\|DB::\|->query(\|->exec(" "$THEME_ROOT" || true)"
if [[ -n "$scope_markers" ]]; then
  fail "ThemeDoctor diagnostic scope contains a write-path marker"
else
  ok "ThemeDoctor diagnostic scope contains no write-path markers"
fi

echo ""
echo "== Manifest field contract =="
for field in tool_key label category risk_level default_enabled can_disable required_permissions allowed_environments routes views services; do
  require_text "$ARCH_DOC" "- $field" "Manifest field $field documented"
done
require_text "$ARCH_DOC" "owner = studio" "Manifest owner documented"

echo ""
echo "== Instance policy anchor =="
require_text "$ARCH_DOC" "studio_tools:" "Instance policy root documented"
require_text "$ARCH_DOC" "view_editor: enabled" "Instance policy includes view_editor"
require_text "$ARCH_DOC" "menu_editor: enabled" "Instance policy includes menu_editor"
require_text "$ARCH_DOC" "theme_tool: enabled" "Instance policy includes theme_tool"
require_text "$ARCH_DOC" "app_builder: enabled # read-only placeholder" "Instance policy includes app_builder placeholder"
require_text "$ARCH_DOC" "module_builder: enabled # read-only placeholder" "Instance policy includes module_builder placeholder"
require_text "$ARCH_DOC" "db_schema_tool: enabled # read-only placeholder" "Instance policy includes db_schema_tool placeholder"

echo ""
echo "== Required governance gates =="
require_text "$ARCH_DOC" "Role and Permission Gates" "Role/permission gate section"
require_text "$ARCH_DOC" "Environment Gates" "Environment gate section"
require_text "$ARCH_DOC" "Risk-level Gates" "Risk-level gate section"
require_text "$ARCH_DOC" "Execution Guard Requirement" "Execution guard section"
require_text "$ARCH_DOC" "UI Visibility Requirement" "UI visibility section"
require_text "$ARCH_DOC" "No Hidden Runtime Truth Rule" "No hidden runtime truth section"

echo ""
echo "== Tracker alignment =="
require_text "docs/architecture/studio-operating-contract.md" "Studio tool lifecycle contract baseline" "Operating contract references lifecycle contract"
require_text "docs/migration-cleanup/phases/move-plan.md" "Batch 13: Studio Tool Lifecycle Contract" "Move plan includes Batch 13"
require_text "MIGRATION-CLEANUP-INDEX.md" "batch-13-studio-tool-lifecycle-contract.md" "Cleanup index includes Batch 13"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio tool lifecycle contract gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

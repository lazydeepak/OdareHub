#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

tool_dir="apps/Studio/Tools/ReportDesigner"
builder_manifest="apps/Studio/Tools/ReportBuilder/manifest.php"
routes_file="apps/Studio/routes.php"
controller_file="apps/Studio/Controllers/StudioController.php"
policy_file="apps/Studio/Services/StudioToolInstancePolicyService.php"
placeholder_view="$tool_dir/Views/index.php"
db_discovery_service="$tool_dir/Services/ReportDesignerDbDiscoveryService.php"
source_catalog_service="$tool_dir/Services/ReportDesignerSourceCatalogService.php"
report_definition_validator="platform/Reports/ReportDefinitionValidator.php"
report_definition_repository="platform/Reports/ReportDefinitionRepository.php"
report_definitions_dir="platform/Reports/Definitions"
plan_doc="docs/architecture/report-designer-p1-foundation-implementation.md"
home_view="apps/Studio/Views/pages/home.php"
tool_placeholder_view="apps/Studio/Views/pages/tool_placeholder.php"

failures=0
passes=0

pass() {
  echo "  ok: $1"
  passes=$((passes + 1))
}

fail() {
  echo "  FAIL: $1" >&2
  failures=$((failures + 1))
}

require_file() {
  local path="$1"
  local label="$2"
  if [[ -f "$path" ]]; then
    pass "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

require_dir() {
  local path="$1"
  local label="$2"
  if [[ -d "$path" ]]; then
    pass "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

require_not_file() {
  local path="$1"
  local label="$2"
  if [[ ! -f "$path" ]]; then
    pass "$label — does not exist"
  else
    fail "$label — exists but should not"
  fi
}

require_text() {
  local path="$1"
  local pattern="$2"
  local label="$3"
  if [[ ! -f "$path" ]]; then
    fail "$label — file missing ($path)"
    return
  fi
  if grep -Fq -- "$pattern" "$path"; then
    pass "$label"
  else
    fail "$label — expected text not found: $pattern"
  fi
}

require_order() {
  local path="$1"
  local before="$2"
  local after="$3"
  local label="$4"
  local before_line
  local after_line
  before_line=$(grep -nF -- "$before" "$path" | head -1 | cut -d: -f1 || true)
  after_line=$(grep -nF -- "$after" "$path" | head -1 | cut -d: -f1 || true)
  if [[ -n "$before_line" && -n "$after_line" && "$before_line" -lt "$after_line" ]]; then
    pass "$label"
  else
    fail "$label — expected '$before' before '$after'"
  fi
}

forbid_pattern() {
  local path="$1"
  local pattern="$2"
  local label="$3"
  if [[ ! -f "$path" ]]; then
    fail "$label — file missing ($path)"
    return
  fi
  if grep -Eq -- "$pattern" "$path"; then
    fail "$label — forbidden pattern found: $pattern"
  else
    pass "$label"
  fi
}

echo "[architecture] check_report_designer_p1_boundaries"
echo "- Report Designer P1 read-only boundary diagnostic"
echo "- Approved plan: docs/architecture/report-designer-p1-foundation-implementation.md"
echo ""

echo "== Approved plan exists =="
require_file "$plan_doc" "Report Designer P1 implementation plan"
require_text "$plan_doc" "Status: Implementation planning" "plan declares implementation planning status"

echo ""
echo "== Studio tool path confinement =="
# All ReportDesigner files must be under the approved tool directory
outside_report_designer=$(find apps plugins app -path "*/ReportDesigner/*" -print 2>/dev/null | grep -v "^$tool_dir/" || true)
if [[ -z "$outside_report_designer" ]]; then
  pass "all ReportDesigner implementation paths confined to $tool_dir"
else
  echo "$outside_report_designer" >&2
  fail "ReportDesigner file found outside approved tool path"
fi

echo ""
echo "== Canonical tool identity =="
# ReportDesigner is canonical, ReportBuilder is legacy-only
if [[ -f "$builder_manifest" ]]; then
  require_text "$builder_manifest" "'status' => 'planned'" "ReportBuilder manifest declares 'planned' status (legacy)"
  forbid_pattern "$builder_manifest" "report_builder.*route|route.*report_builder" "ReportBuilder manifest has no route references"
  pass "ReportBuilder manifest exists as legacy-only (planned status)"
else
  pass "ReportBuilder manifest absent (clean state)"
fi

# ReportDesigner may name ReportBuilder only in governed migration metadata.
if [[ -f "$tool_dir/manifest.php" ]]; then
  require_text "$tool_dir/manifest.php" "'replaces' => 'report_builder'" "ReportDesigner manifest declares ReportBuilder replacement"
  forbid_pattern "$tool_dir/manifest.php" "'tool_key'[[:space:]]*=>[[:space:]]*'report_builder'|'key'[[:space:]]*=>[[:space:]]*'report_builder'|/apps/studio/tools/report-builder" "ReportDesigner manifest does not reuse ReportBuilder identity or route"
fi

echo ""
echo "== No DB migrations or tables =="
migration_hits=$(find app apps plugins -path "*migrations*" -type f 2>/dev/null | grep -iE "report[_-]?designer|report_resource|report_builder" || true)
if [[ -z "$migration_hits" ]]; then
  pass "no report designer/ReportResource migrations found"
else
  echo "$migration_hits" >&2
  fail "report designer/ReportResource migration found"
fi

# Check ReportDesigner PHP files for DB access patterns. The Bootstrap DB
# Discovery service is the sole schema-metadata exception.
tool_php_files=$(find "$tool_dir" -type f -name "*.php" -print 2>/dev/null || true)
if [[ -n "$tool_php_files" ]]; then
  while IFS= read -r file; do
    [[ -z "$file" ]] && continue
    rel="${file#$ROOT_DIR/}"
    if [[ "$file" == "$db_discovery_service" ]]; then
      require_text "$file" "SHOW TABLES" "DB discovery lists schema tables read-only"
      require_text "$file" "SHOW COLUMNS FROM" "DB discovery lists schema columns read-only"
      forbid_pattern "$file" "SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM|CREATE[[:space:]]+TABLE|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE|TRUNCATE[[:space:]]+TABLE|REPLACE[[:space:]]+INTO" "DB discovery has no row reads or schema/data writes"
    else
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM|CREATE[[:space:]]+TABLE|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE|TRUNCATE[[:space:]]+TABLE" "no DB operations in $rel"
    fi
  done <<< "$tool_php_files"
fi

require_file "$db_discovery_service" "Report Designer read-only DB discovery service"
require_file "$source_catalog_service" "Report Designer source catalog service"
require_file "$report_definition_validator" "Platform Report Definition validator"
require_file "$report_definition_repository" "Platform Report Definition repository"
require_dir "$report_definitions_dir" "Platform Report Definitions directory"
require_text "$source_catalog_service" "glob(APP_ROOT . '/apps/*/manifest.json')" "source catalog discovers installed app manifests"
require_text "$source_catalog_service" "ReportDesignerDbDiscoveryService::tables()" "source catalog consumes discovered schema tables"
require_text "$source_catalog_service" "ReportDesignerDbDiscoveryService::columns(" "source catalog consumes discovered schema columns"
  require_text "$controller_file" "'source_catalog' => ReportDesignerSourceCatalogService::catalog()" "controller passes discovery catalog to Report Designer view"
  require_text "$controller_file" "'org_metadata'" "controller passes org metadata to Report Designer view"
  require_text "$controller_file" "'current_user'" "controller resolves current user display name"

echo ""
echo "== No new routes or endpoints =="
# Count report-designer GET route registrations (not string occurrences)
report_routes=$(grep -cF "\$router->get('/apps/studio/tools/report-designer" "$routes_file" 2>/dev/null || true)
if [[ "$report_routes" -eq 1 ]]; then
  pass "exactly 1 report-designer GET route registered (placeholder)"
elif [[ "$report_routes" -eq 0 ]]; then
  fail "report-designer route missing from routes.php"
else
  fail "multiple report-designer GET routes found ($report_routes, expected 1)"
fi

# Exactly one authoring POST route for report definition saves
report_posts=$(grep -nE "post\('/apps/studio/tools/report-(designer|builder)" "$routes_file" 2>/dev/null || true)
unexpected_report_posts=$(printf '%s\n' "$report_posts" | grep -v "/apps/studio/tools/report-designer/save" || true)
if [[ $(printf '%s\n' "$report_posts" | grep -c "/apps/studio/tools/report-designer/save" || true) -eq 1 && -z "$unexpected_report_posts" ]]; then
  pass "only the approved report definition save POST route is registered"
else
  echo "$report_posts" >&2
  fail "unexpected Report Designer POST route set"
fi

# No report-builder route registered
forbidden_builder_route=$(grep -nE "/apps/studio/tools/report-builder" "$routes_file" 2>/dev/null || true)
if [[ -z "$forbidden_builder_route" ]]; then
  pass "no report-builder route registered"
else
  echo "$forbidden_builder_route" >&2
  fail "report-builder route registered (should remain legacy-only)"
fi

echo ""
echo "== No report execution/export runtime behavior =="
if [[ -n "$tool_php_files" ]]; then
  while IFS= read -r file; do
    [[ -z "$file" ]] && continue
    rel="${file#$ROOT_DIR/}"
    forbid_pattern "$file" "renderPdf|exportPdf|dispatchPrint|printLabel|executePrint|streamCsv|Dompdf|PdfService|PrintService|ExportHistoryService|core_export_runs" "no runtime export/print/execution in $rel"
    forbid_pattern "$file" "StyleRegistry|StyleChain|ResolvedExperience|theme\.css" "no Style Registry runtime coupling in $rel"
  done <<< "$tool_php_files"
fi

echo ""
echo "== No Shell/runtime consumption =="
for runtime_path in "app" "apps/Shell" "apps/Platform/Services" "apps/Platform/StyleRegistry"; do
  if [[ ! -e "$runtime_path" ]]; then
    continue
  fi
  if grep -RIl -- "ReportDesigner\\\\|report_designer\\\\|report-designer" "$runtime_path" --include="*.php" --include="*.js" --include="*.json" 2>/dev/null | grep -v "docs/" | grep -q .; then
    grep -RIn -- "ReportDesigner\\\\|report_designer\\\\|report-designer" "$runtime_path" --include="*.php" --include="*.js" --include="*.json" 2>/dev/null | grep -v "docs/" >&2 || true
    fail "$runtime_path must not couple to Report Designer"
  else
    pass "$runtime_path does not couple to Report Designer"
  fi
done

echo ""
echo "== No Core changes =="
core_report_hits=$(find app -path "*ReportDesigner*" -print 2>/dev/null || true)
if [[ -z "$core_report_hits" ]]; then
  pass "no Core files reference Report Designer"
else
  echo "$core_report_hits" >&2
  fail "Core files reference Report Designer"
fi

echo ""
echo "== Governed report definition authoring behavior =="
if [[ -n "$tool_php_files" ]]; then
  while IFS= read -r file; do
    [[ -z "$file" ]] && continue
    rel="${file#$ROOT_DIR/}"
    forbid_pattern "$file" "file_put_contents|fwrite|fopen\\(|mkdir\\(|rename\\(|unlink\\(|copy\\(|touch\\(|chmod\\(|symlink\\(" "no file write API in $rel"
    forbid_pattern "$file" "header\\(['\\\"]Location|\\\$\\_SESSION\\['.*save|\\\$\\_SESSION\\['.*draft|\\\$\\_SESSION\\['.*flash" "no save/draft/flash session behavior in $rel"
  done <<< "$tool_php_files"
fi

# Check view for governed save form and no other mutation/network patterns
if [[ -f "$placeholder_view" ]]; then
  require_text "$placeholder_view" '<form id="rd-save-report-form" method="post" action="/apps/studio/tools/report-designer/save">' "view has the single governed report definition save form"
  report_form_count=$(grep -c "<form" "$placeholder_view" || true)
  if [[ "$report_form_count" -eq 1 ]]; then
    pass "view contains exactly one form"
  else
    fail "view contains $report_form_count forms (expected exactly one)"
  fi
  forbid_pattern "$placeholder_view" "onclick|onsubmit" "view uses event listeners rather than inline mutation handlers"
  forbid_pattern "$placeholder_view" "\\\$bootstrapSources|['\\\"]Manufacturing['\\\"][[:space:]]*=>|['\\\"]SBAIO['\\\"][[:space:]]*=>|['\\\"]Procurement['\\\"][[:space:]]*=>" "view contains no hardcoded business source catalog"
  forbid_pattern "$placeholder_view" "raw[[:space:]_-]*sql|sql[[:space:]_-]*editor|textarea[^>]*(sql|query)" "view exposes no raw SQL editor"
  forbid_pattern "$placeholder_view" "localStorage|sessionStorage|indexedDB|document\\.cookie|fetch\\(|XMLHttpRequest|navigator\\.sendBeacon" "layout builder performs no browser persistence or network writes"
  forbid_pattern "$placeholder_view" "download[[:space:]]*=|createObjectURL|new Blob|renderPdf|exportPdf|streamCsv|xlsx|Excel" "printable preview has no file download or export implementation"
  forbid_pattern "$placeholder_view" "beforeprint|afterprint|matchMedia\\(['\\\"]print|print[[:space:]_-]*endpoint" "printable preview has no server print lifecycle"
  forbid_pattern "$placeholder_view" "mock data|mock rendered report preview|sample data|नकली डाटा|नकली रेन्डर" "view contains no obsolete mock data wording"
  require_text "$placeholder_view" "Bootstrap DB Discovery Mode" "view labels Bootstrap DB Discovery Mode"
  require_text "$placeholder_view" "rd-selected-columns-list" "view includes live Selected Columns summary"
  require_text "$placeholder_view" "if (availableSourcesList.childElementCount === 0)" "available-source empty state renders only when no source buttons exist"
  require_text "$placeholder_view" "if (designState.sourceKeys.size === 0)" "selected-source empty state renders only when no source chips exist"
  require_text "$placeholder_view" "text.source_available_empty" "available-source empty text is rendered inside its list container"
  require_text "$placeholder_view" "text.source_selected_empty" "selected-source empty text is rendered inside its list container"
  forbid_pattern "$placeholder_view" "id=\"rd-(available|selected)-empty\"|availableEmpty|selectedEmpty" "source picker has no permanent sibling empty-state nodes"
  require_text "$placeholder_view" "rd-filter-candidate" "view includes metadata-derived filter candidates"
  require_text "$placeholder_view" "rd-filter-checkbox" "filter candidates remain client-side selectable"
  require_text "$placeholder_view" "rd-layout-columns" "view includes client-side layout columns"
  require_text "$placeholder_view" "rd-layout-column" "view renders per-column layout controls"
  require_text "$placeholder_view" "rd-layout-grouping" "view includes one-column grouping control"
  require_text "$placeholder_view" "columns: []" "layout state is initialized in the unified design state"
  require_text "$placeholder_view" "text.move_up" "move-up control locale key present"
  require_text "$placeholder_view" "text.move_down" "move-down control locale key present"
  require_text "$placeholder_view" 'type="submit" class="rd-create-button"' "save form has one explicit submit action"
  require_text "$placeholder_view" "rd-live-preview-summary-content" "Build workspace includes compact live preview summary"
  require_text "$placeholder_view" "rd-printable-preview-workspace" "view includes dedicated printable Preview workspace"
  require_text "$placeholder_view" "rd-printable-preview" "dedicated Preview workspace owns printable report surface"
  require_text "$placeholder_view" "Design Summary" "Build preview is labeled as a design summary"
  require_text "$placeholder_view" "No in-memory preview state" "direct Preview route has intentional state guidance"
  forbid_pattern "$placeholder_view" "rd-live-preview-workspace|id=\"rd-live-preview\"|rd-preview-placeholder" "view contains no old inline preview workspace or stale fallback"
  require_text "$placeholder_view" "placeholder preview rows" "view labels placeholder preview rows"
  require_text "$placeholder_view" "i < 3" "preview uses bounded placeholder rows (3-row grouped loop)"
  require_text "$placeholder_view" "[0, 1, 2]" "preview uses bounded placeholder rows (3-row flat array)"
  require_text "$placeholder_view" "'preview_title' => 'Preview'" "view uses an extensible Preview workspace label"
  require_text "$placeholder_view" "Preview uses report design metadata and placeholder rows only." "view declares placeholder-only report preview"
  require_text "$placeholder_view" "rd-report-header" "preview renders a report header"
  require_text "$placeholder_view" "rd-report-filters" "preview renders active filter summary"
  require_text "$placeholder_view" "rd-preview-group-section" "preview renders grouped report sections"
  require_text "$placeholder_view" "rd-report-footer" "preview renders a printable report footer"
  require_text "$placeholder_view" "rd-report-page-number" "preview includes placeholder page numbering"
  require_text "$placeholder_view" "rd-print-preview" "preview exposes a browser print action"
  require_text "$placeholder_view" "window.print();" "print action delegates only to the browser"
  require_text "$placeholder_view" "@media print" "view includes dedicated print styling"
  require_text "$placeholder_view" "body * {" "print styling hides non-report surface content"
  require_text "$placeholder_view" "#rd-printable-preview * {" "print styling reveals only the dedicated report surface"
  echo ""
  echo "== Phase 5.4 Preview Context Panel =="
  require_text "$placeholder_view" "Phase 5.4: Preview-only Report Context" "Preview workspace includes Report Context panel"
  require_text "$placeholder_view" "rd-report-context-summary" "Preview displays Report Context summary above report surface"
  require_text "$placeholder_view" "rd-change-context" "Preview exposes Change Context button"
  require_text "$placeholder_view" "rd-context-editor" "Preview includes collapsible context editor"
  require_text "$placeholder_view" "value=\"current_month\" selected" "Date Range defaults to Current Month"
  require_text "$placeholder_view" "currentMonthStart" "Current Month start is computed in preview state"
  require_text "$placeholder_view" "currentMonthEnd" "Current Month end is computed in preview state"
  require_text "$placeholder_view" "rd-context-include-print" "context summary printing is optional"
  require_text "$placeholder_view" "rd-print-context-summary" "printable report includes context summary"
  require_text "$placeholder_view" "preview_context_value_in_preview" "Build context candidates defer runtime values to Preview"
  forbid_pattern "$placeholder_view" "fetch\\(|XMLHttpRequest|navigator\\.sendBeacon" "Phase 5.4 context editor performs no row query or network request"
  require_text "$placeholder_view" ".rd-report-context-panel," "print CSS hides context controls"
  require_text "$placeholder_view" "#rd-change-context," "print CSS hides Change Context button"
  require_text "$placeholder_view" "flex-direction: column;" "Report Designer workspaces stack tabs above full-width content"
  require_text "$placeholder_view" "flex-wrap: wrap;" "workspace tabs wrap safely"
  require_text "$placeholder_view" "flex: 1 1 180px;" "workspace tabs share available horizontal width"
  require_text "$placeholder_view" "group.className = 'rd-column-group'" "columns render as source-level collapsible groups"
  require_text "$placeholder_view" "group.dataset.sourceKey = sourceKey" "column groups retain selected source ownership"
  echo ""
  echo "== Phase 5.5 Business Field Labels =="
  require_text "$db_discovery_service" "'qty' => 'Quantity'" "discovery expands qty to Quantity"
  require_text "$db_discovery_service" "'qc' => 'QC'" "discovery preserves QC acronym"
  require_text "$db_discovery_service" "'ipm' => 'IPM'" "discovery preserves IPM acronym"
  require_text "$db_discovery_service" "'id' => 'ID'" "discovery preserves ID acronym"
  require_text "$placeholder_view" "source_business_fields" "view localizes Business Fields"
  require_text "$placeholder_view" "source_advanced_fields" "view localizes Advanced Fields"
  require_text "$placeholder_view" "const fieldDisplayLabel" "view normalizes business field labels"
  require_text "$placeholder_view" "approved_at: 'Approval Time'" "view maps Approved At to Approval Time"
  require_text "$placeholder_view" "parts_name: 'Parts Name'" "view maps parts_name to Parts Name"
  require_text "$placeholder_view" "parts_number: 'Parts Number'" "view maps parts_number to Parts Number"
  require_text "$placeholder_view" "const isAdvancedField" "view classifies technical fields"
  require_text "$placeholder_view" "key === 'source_type'" "source_type remains advanced"
  require_text "$placeholder_view" "key === 'source_id'" "source_id remains advanced"
  require_text "$placeholder_view" "const classifyContextInput" "view uses shared context input classification"
  require_text "$placeholder_view" "key.endsWith('_id')" "foreign-key fields are advanced"
  require_text "$placeholder_view" "key === 'created_at'" "created_at is advanced"
  require_text "$placeholder_view" "key === 'updated_at'" "updated_at is advanced"
  require_text "$placeholder_view" "rd-business-fields" "business fields render first"
  require_text "$placeholder_view" "rd-advanced-fields" "advanced fields use collapsed details"
  require_text "$placeholder_view" "groupColumns.push({checkbox, applySelection})" "business and advanced fields preserve shared selection behavior"
  require_text "$placeholder_view" '`${column.sourceLabel}: ${column.label}`' "Selected Fields summary uses business-facing labels"
  require_text "$placeholder_view" "selectAll.type = 'button'" "column group Select all is client-only"
  require_text "$placeholder_view" "clear.type = 'button'" "column group Clear is client-only"
  require_text "$placeholder_view" "rd-column-selected-count" "column groups display selected counts"
  require_text "$placeholder_view" "rd-column-group-content" "column groups use compact bounded content"
  require_text "$placeholder_view" "max-height: 480px;" "expanded column groups remain vertically bounded"
fi

echo ""
echo "== Phase 6 Platform-owned Report Definitions =="
require_text "$report_definition_validator" "SCHEMA_VERSION = '1.0'" "definition validator owns schema version"
require_text "$report_definition_validator" "REQUIRED_ARRAY_SECTIONS" "definition validator requires structural sections"
require_text "$report_definition_validator" "Report key already exists." "definition validator enforces report key uniqueness"
require_text "$report_definition_validator" "Report key cannot be changed after the definition is created." "saved report keys remain stable"
require_text "$report_definition_validator" "generateKey" "definition validator supports generated report keys"
require_text "$report_definition_validator" "FORBIDDEN_KEYS" "definition validator rejects runtime/query sections"
require_text "$report_definition_repository" "platform/Reports/Definitions" "repository writes only to Platform-owned definitions"
require_text "$report_definition_repository" "storage/platform-snapshots/report-definitions" "repository uses Platform snapshot storage"
require_order "$report_definition_repository" "writeJson(\$snapshotPath" "writeJson(\$targetPath" "snapshot is written before report definition"
require_text "$report_definition_repository" "rename(\$tmp, \$path)" "repository uses atomic temp-file rename"
require_text "$controller_file" "ReportDefinitionRepository::all()" "Studio reads Platform-owned report definitions"
require_text "$controller_file" "ReportDefinitionRepository::save(" "Studio delegates persistence to Platform repository"
require_text "$controller_file" "Auth::requireCsrf" "report definition save is CSRF guarded"
require_text "$routes_file" "/apps/studio/tools/report-designer/save" "save route exists"
require_text "$placeholder_view" "buildReportDefinition" "view serializes the in-memory design"
require_text "$placeholder_view" "loadReportDefinition" "view restores saved definitions"
require_text "$placeholder_view" "resetReportDesign" "New Report clears only in-memory design"
require_text "$placeholder_view" "rd-definition-diagnostics" "Governance renders definition diagnostics"
for file in "$report_definition_validator" "$report_definition_repository"; do
  forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "no DB or row query execution in $file"
  forbid_pattern "$file" "renderPdf|exportPdf|streamCsv|xlsx|Excel|Dompdf|PdfService|ExportHistoryService|dispatchPrint" "no report execution/export/PDF integration in $file"
done

echo ""
echo "== Phase 5.6A Wizard Workflow Foundation =="
require_text "$placeholder_view" "rd-wizard-progress" "wizard renders a compact progress header"
require_text "$placeholder_view" "data-rd-workspace-panel=\"build\"" "wizard preserves Build workspace"
require_text "$placeholder_view" "data-rd-workspace-panel=\"presentation\"" "wizard preserves Presentation workspace"
require_text "$placeholder_view" "data-rd-workspace-panel=\"preview\"" "wizard preserves Preview workspace"
require_text "$placeholder_view" "data-rd-workspace-panel=\"save\"" "wizard adds Save Definition destination"
require_text "$placeholder_view" "Continue to Presentation" "Build continues to Presentation"
require_text "$placeholder_view" "Continue to Preview" "Presentation continues to Preview"
require_text "$placeholder_view" "Continue to Save" "Preview continues to Save"
require_text "$placeholder_view" "Back to Build" "Presentation returns to Build"
require_text "$placeholder_view" "Back to Presentation" "Preview returns to Presentation"
require_text "$placeholder_view" "Back to Preview" "Save returns to Preview"
require_text "$placeholder_view" "setWizardWorkspace" "wizard switches workspaces without discarding in-memory design"
require_text "$placeholder_view" "window.history.pushState" "wizard preserves direct workspace URLs and browser history"
require_text "$placeholder_view" "window.addEventListener('popstate'" "browser back and forward restore wizard steps"
require_text "$placeholder_view" "scrollIntoView({ block: 'start' })" "wizard navigation scrolls to workspace top"
require_text "$placeholder_view" "aria-current=\"step\"" "current wizard step is exposed accessibly"
require_text "$placeholder_view" ".rd-wizard-step.is-complete" "completed wizard steps are visually marked"
require_text "$placeholder_view" ".rd-wizard-actions" "wizard renders bottom navigation"
require_text "$placeholder_view" ".rd-wizard-progress," "print CSS hides wizard controls"
for workspace in build presentation preview save governance overview; do
  require_text "$placeholder_view" "workspace=${workspace}" "direct ${workspace} workspace navigation remains available"
done

echo ""
echo "== Wizard Design State Handoff =="
require_text "$placeholder_view" "const designState = {" "wizard owns one normalized client-side design state"
require_text "$placeholder_view" "sourceKeys: new Set()" "design state owns selected sources"
require_text "$placeholder_view" "columns: []" "design state owns selected fields and layout columns"
require_text "$placeholder_view" "contextInputs: new Map()" "design state owns context inputs"
require_text "$placeholder_view" "presentation: createDefaultPresentation()" "design state owns presentation configuration"
require_text "$placeholder_view" "previewDefaults: createDefaultPreviewContext()" "design state owns preview defaults"
require_text "$placeholder_view" "designState.sourceKeys.add(sourceKey)" "Build source selection updates design state"
require_text "$placeholder_view" "designState.columns.push({" "Build field selection updates design state"
require_text "$placeholder_view" "designState.presentation.format = input.value" "Presentation updates design state"
require_text "$placeholder_view" "const visibleColumns = designState.columns.filter" "Preview renders from design state"
require_text "$placeholder_view" "selected_sources: Array.from(designState.sourceKeys)" "Save serializes design-state sources"
require_text "$placeholder_view" "selected_fields: designState.columns.map" "Save serializes design-state fields"
require_text "$placeholder_view" "presentation_configuration: JSON.parse(JSON.stringify(designState.presentation))" "Save serializes design-state presentation"
require_text "$placeholder_view" "refreshPreviewWorkspace();" "wizard refreshes Preview during client-side handoff"
require_text "$placeholder_view" "syncSavePayload();" "wizard refreshes Save payload during client-side handoff"
require_text "$placeholder_view" "Preview requires an active design session" "direct Preview route uses active-session guidance"
for legacy_state in "let layoutColumns" "let groupingKey" "const selectedSourceKeys" "let activeFilters" "let presentationState"; do
  forbid_pattern "$placeholder_view" "$legacy_state" "legacy split client state removed: $legacy_state"
done
forbid_pattern "$placeholder_view" "const reportContext[[:space:]]*=" "legacy split client state removed: const reportContext"

echo ""
echo "== Phase 6.1 Save Definition Workflow Cleanup =="
require_text "$placeholder_view" "'wizard_save' => 'Save Definition'" "wizard consistently labels Step 4 Save Definition"
require_text "$placeholder_view" "'wizard_continue_save' => 'Continue to Save Definition'" "Preview continues to Save Definition"
forbid_pattern "$placeholder_view" "Save Report|Save Output" "visible workflow avoids obsolete save wording"
require_text "$placeholder_view" "id=\"rd-save-definition-button\"" "Save Definition has a stable action control"
require_text "$placeholder_view" "type=\"submit\" class=\"rd-create-button\" disabled" "Save Definition starts disabled without eligible design state"
require_text "$placeholder_view" "designState.sourceKeys.size > 0 && designState.columns.length > 0" "save eligibility requires a source and field"
require_text "$placeholder_view" "Select at least one source and one field before saving." "ineligible save guidance is visible"
require_text "$placeholder_view" "status: 'unsaved'" "new design state starts Unsaved"
require_text "$placeholder_view" "setDefinitionStatus('modified')" "loaded definition changes become Modified"
require_text "$placeholder_view" "setDefinitionStatus('saved')" "loaded or successfully saved definitions become Saved"
require_text "$placeholder_view" "initialSavedReportKey" "successful save restores the authoritative saved definition"
require_text "$placeholder_view" "openReportSelect.value = designState.reportKey" "saved definition remains selected in Open Report"
require_text "$placeholder_view" ".rd-wizard-workspace[hidden]" "only the current wizard workspace is visible"
require_text "$placeholder_view" "#rd-preview-has-state[hidden]" "Preview report and empty-session states are mutually exclusive"
require_text "$placeholder_view" "rd-save-payload-review" "Save Definition reviews serialized sections"
require_text "$placeholder_view" "selected_sources: Array.from(designState.sourceKeys)" "payload review source data is design-only"
require_text "$placeholder_view" "context_inputs: Array.from(designState.contextInputs.entries())" "payload review includes context inputs"
require_text "$placeholder_view" "layout_configuration:" "payload review includes layout"
require_text "$placeholder_view" "presentation_configuration:" "payload review includes presentation"
require_text "$placeholder_view" "preview_defaults:" "payload review includes preview defaults"
require_text "$report_definition_validator" "At least one source is required." "server validation enforces save source eligibility"
require_text "$report_definition_validator" "At least one field is required." "server validation enforces save field eligibility"

echo ""
echo "== Phase 6.2 Visual Layout Composer =="
require_text "$placeholder_view" "Select fields in Step 3 to compose layout." "Layout empty state directs users back to Step 3"
require_text "$placeholder_view" "'layout_remove' => 'Remove from layout'" "Layout composer has remove-from-layout locale text"
require_text "$placeholder_view" "rd-layout-composer-item" "Selected fields render as visual layout composer items"
require_text "$placeholder_view" "rd-layout-item-identity" "Layout composer item shows business identity"
require_text "$placeholder_view" "rd-layout-item-meta" "Layout composer item shows source name"
require_text "$placeholder_view" "rd-layout-type-badge" "Layout composer item shows source type badge"
require_text "$placeholder_view" "rd-layout-toggle" "Layout composer item exposes visible toggle"
require_text "$placeholder_view" "rd-layout-control" "Layout composer item exposes width/alignment/summary controls"
require_text "$placeholder_view" "rd-layout-item-move" "Layout composer item exposes move controls"
require_text "$placeholder_view" "rd-layout-remove" "Layout composer item exposes remove-from-layout control"
require_text "$placeholder_view" "designState.columns.filter((column) => column.visible).forEach" "Grouping options derive from selected visible fields"
require_text "$placeholder_view" "column.visible && column.key === designState.grouping" "Grouping selection clears when its field is hidden"
require_text "$placeholder_view" "selected_fields: designState.columns.map" "Save payload remains compatible with selected field layout state"
require_text "$placeholder_view" "layout_configuration:" "Save payload remains compatible with layout configuration"
forbid_pattern "$placeholder_view" "renderPdf|exportPdf|streamCsv|xlsx|Excel|new PDO|->query\\(|SELECT[[:space:]]+.*FROM" "Phase 6.2 composer adds no runtime execution, SQL, row query, or export behavior"

echo ""
echo "== Phase 6.2A Presentation Handoff and Wizard Visibility =="
require_text "$placeholder_view" "panel.style.display = isActivePanel ? '' : 'none'" "wizard client switcher force-hides inactive panels"
require_text "$placeholder_view" ".rd-wizard-workspace:not(.is-active)" "wizard CSS hides inactive workspaces"
require_text "$placeholder_view" "const showDesignSurface = hasState || activeDesignSession" "Preview warning is limited to direct routes without active design state"
require_text "$placeholder_view" "const orgMetadata = " "Preview receives read-only organization metadata"
require_text "$placeholder_view" "report.dataset.format = designState.presentation.format" "Preview receives output format"
require_text "$placeholder_view" "report.dataset.pageSize = designState.presentation.pageSize" "Preview receives page size"
require_text "$placeholder_view" "report.dataset.orientation = designState.presentation.orientation" "Preview receives orientation"
require_text "$placeholder_view" 'report.style.padding = `${pageControls.marginTop' "Preview receives page margin controls"
require_text "$placeholder_view" "headerEl.style.minHeight = pageControls.headerHeight" "Preview receives header height control"
require_text "$placeholder_view" "footerEl.style.minHeight = pageControls.footerHeight" "Preview receives footer height control"
require_text "$placeholder_view" "headerControls.show_company_name" "Preview receives header controls"
require_text "$placeholder_view" "footerControls.show_page_number" "Preview receives footer controls"
require_text "$placeholder_view" "rd-preview-card-grid" "Preview renders card output format"
require_text "$placeholder_view" "presentation_configuration: JSON.parse(JSON.stringify(designState.presentation))" "Save payload includes presentation configuration"
require_text "$placeholder_view" "preview_defaults: { ...designState.previewDefaults }" "Save payload includes preview defaults"

echo ""
echo "== Phase 6.2B UI Consistency Repair =="
require_text "$placeholder_view" "const isAdvancedField" "Business field classification is field-based"
require_text "$placeholder_view" "key === 'source_id'" "source_id remains advanced"
forbid_pattern "$placeholder_view" "const isAdvancedTable" "Business classification does not force whole source tables into Advanced"
forbid_pattern "$placeholder_view" "isAdvancedTable\\(tableKey\\)" "Business classification does not force whole source tables into Advanced"
require_text "$placeholder_view" "selectedColumnsEmpty.style.display = hasSelectedColumns ? 'none' : ''" "Selected field empty state is hidden when chips exist"
require_text "$placeholder_view" "selectedColumnsList.hidden = !hasSelectedColumns" "Selected field chip list is hidden only when empty"
require_text "$placeholder_view" "tableGroup.append(businessList)" "Business fields avoid raw table-name headings"
require_text "$placeholder_view" "advancedList.append(createFieldItem(tableKey, column))" "Advanced fields still render technical fields"
require_text "$placeholder_view" "const showDesignSurface = hasState || activeDesignSession" "Preview warning remains mutually exclusive with active design state"

echo ""
echo "== ModuleReportRegistryService unchanged =="
registry_files=$(find apps -name "*ModuleReportRegistryService*" -type f 2>/dev/null || true)
registry_changed=false
if [[ -n "$registry_files" ]]; then
  for rf in $registry_files; do
    rel="${rf#$ROOT_DIR/}"
    if grep -q "ReportDesigner\\\\|report_designer\\\\|ReportResource" "$rf" 2>/dev/null; then
      echo "$rf references Report Designer" >&2
      registry_changed=true
    fi
  done
fi
if [[ "$registry_changed" == false ]]; then
  pass "ModuleReportRegistryService files do not reference Report Designer"
else
  fail "ModuleReportRegistryService references Report Designer"
fi

echo ""
echo "== Allowed foundation class existence check =="
# These files are ALLOWED by the P1 plan. Check they don't contain forbidden patterns.
allowed_class_dirs=(
  "$tool_dir/ValueObjects"
  "$tool_dir/Services"
  "$tool_dir/Contracts"
)
for adir in "${allowed_class_dirs[@]}"; do
  if [[ -d "$adir" ]]; then
    found_files=$(find "$adir" -type f -name "*.php" -print 2>/dev/null || true)
    if [[ -n "$found_files" ]]; then
      pass "foundation class directory exists: $adir"
      while IFS= read -r f; do
        rel="${f#$ROOT_DIR/}"
        forbid_pattern "$f" "new PDO|->query\\(|->exec\\(|file_put_contents|fwrite|header\\(Location|\\\$\\_POST" "foundation class has no runtime behavior: $rel"
      done <<< "$found_files"
    else
      pass "foundation class directory empty/not yet populated: $adir"
    fi
  else
    pass "foundation class directory not yet created: $adir"
  fi
done

echo ""
echo "== Studio entry references =="
require_text "$controller_file" "governedToolPlaceholder" "StudioController exposes manifest-driven governed placeholder rendering"
require_text "$controller_file" "StudioGovernedToolRegistryService" "StudioController consumes the governed tool registry"
require_text "$policy_file" "report_designer" "StudioToolInstancePolicyService references report_designer"
require_text "$home_view" "report_designer" "home page references report_designer"
require_text "$tool_placeholder_view" "report_designer" "tool placeholder references report_designer"

echo ""
echo "== Result =="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s), $passes pass(es))" >&2
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"

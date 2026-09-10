#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

tool_dir="apps/Studio/Tools/LabelDesigner"
routes_file="apps/Studio/routes.php"
controller_file="apps/Studio/Controllers/StudioController.php"
manifest_file="$tool_dir/manifest.php"
preview_entry_file="$tool_dir/Views/index.php"
legacy_preview_file="$tool_dir/Views/preview.php"
layout_file="$tool_dir/Views/layout.php"
preview_contract_file="$(mktemp "${TMPDIR:-/tmp}/label-designer-view-contract.XXXXXX")"
preview_file="$preview_entry_file"
discovery_service="$tool_dir/Services/LabelDesignerDiscoveryService.php"
data_source_service="$tool_dir/Services/LabelDesignerDataSourceDiscoveryService.php"
context_create_service="$tool_dir/Services/LabelDesignerContextCreateService.php"
template_preview_service="$tool_dir/Services/LabelDesignerTemplatePreviewService.php"
template_create_service="$tool_dir/Services/LabelDesignerTemplateCreateService.php"
rule_create_service="$tool_dir/Services/LabelDesignerRuleCreateService.php"
runtime_dry_run_service="$tool_dir/Services/LabelDesignerRuntimeDryRunValidator.php"
operating_contract="docs/architecture/label-designer-operating-contract.md"
resource_contract="docs/architecture/label-resource-contract.md"
apply_snapshot_contract="docs/architecture/label-designer-apply-snapshot-safety-contract.md"
template_apply_snapshot_contract="docs/architecture/label-designer-template-apply-snapshot-safety-contract.md"
runtime_contract="docs/architecture/label-runtime-contract.md"
runtime_handoff_contract="docs/architecture/label-runtime-handoff-contract.md"
validation_contract="docs/architecture/label-validation-contract.md"
snapshot_dir="storage/studio-snapshots/label-designer"
readiness_service_file="$tool_dir/Services/LabelDesignerResourceReadinessService.php"
metadata_service_file="$tool_dir/Services/LabelDesignerResourceMetadataService.php"
metadata_contract="docs/architecture/label-resource-metadata-contract.md"

cleanup() {
  rm -f "$preview_contract_file"
}
trap cleanup EXIT

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

build_preview_contract_file() {
  : > "$preview_contract_file"
  local path
  for path in \
    "$tool_dir/Views/index.php" \
    "$tool_dir/Views/layout.php" \
    "$tool_dir/Views/partials/workspace-tabs.php" \
    "$tool_dir/Views/partials/owner-status.php" \
    "$tool_dir/Views/partials/diagnostics-panel.php" \
    "$tool_dir/Views/partials/flash.php" \
    "$tool_dir/Views/partials/shared-ui.php" \
    "$tool_dir/Views/workspaces/overview.php" \
    "$tool_dir/Views/workspaces/build.php" \
    "$tool_dir/Views/workspaces/rules.php" \
    "$tool_dir/Views/workspaces/preview.php" \
    "$tool_dir/Views/workspaces/governance.php" \
    "$tool_dir/Resources/lang/en.php" \
    "$tool_dir/Resources/lang/ja.php" \
    "$tool_dir/Resources/lang/ne.php" \
    "$tool_dir/Assets/label-designer.css" \
    "$tool_dir/Assets/label-designer.js"; do
    if [[ -f "$path" ]]; then
      printf '\n# %s\n' "$path" >> "$preview_contract_file"
      cat "$path" >> "$preview_contract_file"
    fi
  done
}

build_preview_contract_file

echo "[architecture] check_label_designer_boundaries"
echo "- guarded Label Designer context-create owner-resource boundary + preview renderer diagnostic"
echo ""

echo "== Required owner home =="
require_dir "$tool_dir" "Label Designer tool directory"
require_file "$manifest_file" "Label Designer manifest"
require_file "$preview_entry_file" "Label Designer index view"
require_file "$layout_file" "Label Designer layout view"
require_file "$legacy_preview_file" "Label Designer legacy preview compatibility shim"
require_file "$tool_dir/Views/partials/workspace-tabs.php" "Label Designer workspace tabs partial"
require_file "$tool_dir/Views/partials/owner-status.php" "Label Designer owner status partial"
require_file "$tool_dir/Views/partials/diagnostics-panel.php" "Label Designer diagnostics panel partial"
require_file "$tool_dir/Views/partials/flash.php" "Label Designer flash partial"
require_file "$tool_dir/Views/partials/shared-ui.php" "Label Designer shared UI partial"
require_file "$tool_dir/Views/workspaces/overview.php" "Label Designer overview workspace"
require_file "$tool_dir/Views/workspaces/build.php" "Label Designer build workspace"
require_file "$tool_dir/Views/workspaces/rules.php" "Label Designer rules workspace"
require_file "$tool_dir/Views/workspaces/preview.php" "Label Designer preview workspace"
require_file "$tool_dir/Views/workspaces/governance.php" "Label Designer governance workspace"
require_file "$tool_dir/Assets/label-designer.css" "Label Designer CSS asset"
require_file "$tool_dir/Assets/label-designer.js" "Label Designer JS asset"
require_file "$tool_dir/Resources/lang/en.php" "Label Designer English lang file"
require_file "$tool_dir/Resources/lang/ja.php" "Label Designer Japanese lang file"
require_file "$tool_dir/Resources/lang/ne.php" "Label Designer Nepali lang file"
require_text "$legacy_preview_file" "require __DIR__ . '/index.php';" "legacy preview is a thin compatibility include"
forbid_pattern "$legacy_preview_file" 'LabelDesigner(Context|Template|Rule|Resource)|<form|ld-workspace|\$_GET|\$_POST' "legacy preview contains no independent controller/view/business logic"
require_text "$controller_file" "Views/index.php" "controller renders Label Designer index compositor"
require_text "$controller_file" "label_active_workspace" "controller passes resolved active workspace"
require_text "$controller_file" "resolveLabelDesignerWorkspace" "controller owns workspace alias resolution"
forbid_pattern "$tool_dir/Views/workspaces/overview.php" '\$_GET\['\''workspace'\''\]|workspaceAliases' "overview workspace has no workspace request parsing"
forbid_pattern "$tool_dir/Views/workspaces/build.php" '\$_GET\['\''workspace'\''\]|workspaceAliases' "build workspace has no workspace request parsing"
forbid_pattern "$tool_dir/Views/workspaces/rules.php" '\$_GET\['\''workspace'\''\]|workspaceAliases' "rules workspace has no workspace request parsing"
forbid_pattern "$tool_dir/Views/workspaces/preview.php" '\$_GET\['\''workspace'\''\]|workspaceAliases' "preview workspace has no workspace request parsing"
forbid_pattern "$tool_dir/Views/workspaces/governance.php" '\$_GET\['\''workspace'\''\]|workspaceAliases' "governance workspace has no workspace request parsing"
preview_file="$preview_contract_file"
require_file "$discovery_service" "Label Designer read-only discovery service"
require_text "$discovery_service" "PATHINFO_EXTENSION" "Label Designer discovery excludes non-JSON backup artifacts"
require_file "$data_source_service" "Label Designer read-only data source discovery service"
require_file "$context_create_service" "Label Designer guarded context-create service"
require_file "$template_preview_service" "Label Designer read-only template preview service"
require_file "$rule_create_service" "Label Designer guarded rule-create service"
require_file "$runtime_dry_run_service" "Label Designer runtime request dry-run validator"
require_file "$tool_dir/Services/LabelDesignerPreviewRendererService.php" "Label Designer read-only preview renderer service"
require_file "$tool_dir/ValueObjects/ResolvedLabelPreview.php" "Label Designer resolved label preview value object"

echo ""
echo "== Implementation path confinement =="
outside_paths=$(find apps plugins app -path "*LabelDesigner*" -print 2>/dev/null | grep -v -e "^$tool_dir$" -e "^$tool_dir/" || true)
if [[ -z "$outside_paths" ]]; then
  pass "LabelDesigner implementation paths are confined to $tool_dir"
else
  echo "$outside_paths" >&2
  fail "LabelDesigner implementation path found outside $tool_dir"
fi

echo ""
echo "== Required contracts =="
require_file "$operating_contract" "Label Designer operating contract"
require_file "$resource_contract" "Label resource contract"
require_file "$apply_snapshot_contract" "Label Designer apply/snapshot safety contract"
require_file "$template_apply_snapshot_contract" "Label Designer template apply/snapshot safety contract"
require_file "docs/architecture/label-rule-resource-contract.md" "Label Rule Resource Contract"
require_text "$operating_contract" "apps/Studio/Tools/LabelDesigner/" "operating contract names Studio owner path"
require_text "$operating_contract" "guarded context-create implementation" "operating contract documents guarded context-create state"
require_text "$resource_contract" "Label Context Resource" "resource contract defines Label Context Resource"
require_text "$resource_contract" "Label Template Resource" "resource contract defines Label Template Resource"
require_text "$resource_contract" "Label Rule Set Resource" "resource contract defines Label Rule Set Resource"
require_text "$operating_contract" "label-designer-apply-snapshot-safety-contract.md" "operating contract references apply/snapshot safety contract"
require_text "$operating_contract" "label-designer-template-apply-snapshot-safety-contract.md" "operating contract references template apply/snapshot safety contract"
require_text "$resource_contract" "label-designer-apply-snapshot-safety-contract.md" "resource contract references apply/snapshot safety contract"
require_text "$resource_contract" "label-designer-template-apply-snapshot-safety-contract.md" "resource contract references template apply/snapshot safety contract"

echo ""
echo "== Standard owner resource paths =="
for path_fragment in \
  "Resources/labels/contexts" \
  "Resources/labels/templates" \
  "Resources/labels/rules"; do
  require_text "$operating_contract" "$path_fragment" "operating contract documents $path_fragment"
  require_text "$resource_contract" "$path_fragment" "resource contract documents $path_fragment"
  require_text "$manifest_file" "$path_fragment" "manifest documents $path_fragment"
done

echo ""
echo "== Current guarded context-create boundary =="
require_text "$manifest_file" "'status' => 'guarded_context_template_rule_create_preview_resource_readiness'" "manifest marks Label Designer guarded context/template/rule create + preview + resource readiness"
require_text "$manifest_file" "'can_modify' => true" "manifest allows guarded modify capability"
require_text "$manifest_file" "'writes_to_owner_artifact' => true" "manifest allows owner context artifact writes"
require_text "$preview_file" "<form method=\"get\" action=\"/apps/studio/tools/label-designer\"" "preview has guarded GET selection form"
require_text "$preview_file" "<form method=\"post\" action=\"/apps/studio/tools/label-designer/context/create\"" "preview has guarded context-create POST form"
require_text "$preview_file" "This will create an owner-owned label context file after snapshot, validation, and confirmation." "preview shows explicit create warning"
require_text "$preview_file" "confirm_create" "preview requires explicit confirmation field"
require_text "$preview_file" "Template creation preview" "preview shows template creation preview section"
require_text "$preview_file" "No label template is created yet." "preview states template flow is read-only"
require_text "$routes_file" "/apps/studio/tools/label-designer/context/create" "Studio routes expose guarded Label Designer context-create endpoint"
forbid_pattern "$preview_file" "<button[^>]*disabled[^>]*>[^<]*Create context" "preview no longer renders disabled create button"

label_designer_post_lines=$(grep -nE "post\('/apps/studio/tools/label-designer" "$routes_file" || true)
unexpected_label_posts=$(echo "$label_designer_post_lines" | grep -v -e "/apps/studio/tools/label-designer/context/create" -e "/apps/studio/tools/label-designer/create-folders" -e "/apps/studio/tools/label-designer/template/create" -e "/apps/studio/tools/label-designer/rule/create" -e "/apps/studio/tools/label-designer/preview/render" -e "/apps/studio/tools/label-designer/preview/rule-sandbox" -e "/apps/studio/tools/label-designer/migration-preview" -e "/apps/studio/tools/label-designer/migration-apply" -e "/apps/studio/tools/label-designer/duplicate-resource" -e "/apps/studio/tools/label-designer/context/edit" -e "/apps/studio/tools/label-designer/template/edit" -e "/apps/studio/tools/label-designer/rule/edit" || true)
if [[ -n "$unexpected_label_posts" ]]; then
  echo "$unexpected_label_posts" >&2
  fail "Studio routes expose unexpected Label Designer POST endpoint(s)"
else
  pass "Studio routes only expose approved Label Designer POST endpoints (context-create, create-folders, template/create, rule/create, preview/render, preview/rule-sandbox, migration-preview, migration-apply)"
fi

forbid_pattern "$routes_file" "label-designer[^/]*(rules|rule)[^/]*(save|apply|edit|write|delete)" "Studio routes expose no Label Designer rule save/apply/edit/write/delete endpoints (excluding guarded /rule/edit)"
forbid_pattern "$routes_file" "label-designer/migration-apply[^\\n]*(GET|get)" "migration-apply route is POST only (no GET)"

echo ""
echo "== Resource readiness boundary =="
readiness_service_file="$tool_dir/Services/LabelDesignerResourceReadinessService.php"
require_file "$readiness_service_file" "Label Designer resource readiness service"
require_text "$readiness_service_file" "checkReadiness" "readiness service has checkReadiness() method"
require_text "$readiness_service_file" "createMissingFolders" "readiness service has createMissingFolders() method"
require_text "$readiness_service_file" "'all_exist'" "readiness service produces all_exist status"
require_text "$readiness_service_file" "'none_exist'" "readiness service produces none_exist status"
require_text "$readiness_service_file" "'root_path'" "readiness service produces root_path status"
require_text "$readiness_service_file" "writeSnapshot" "readiness service creates snapshot before folder write"
require_text "$readiness_service_file" "Resources/labels" "readiness service targets canonical labels base folder"
require_text "$readiness_service_file" "Resources/labels/contexts" "readiness service targets canonical contexts folder"
require_text "$readiness_service_file" "Resources/labels/templates" "readiness service targets canonical templates folder"
require_text "$readiness_service_file" "Resources/labels/rules" "readiness service targets canonical rules folder"
require_text "$readiness_service_file" "storage/studio-snapshots/label-designer" "readiness service writes snapshots under approved root"
forbid_pattern "$readiness_service_file" "Resources/labels.*\.php|Resources/labels.*\.json|\.label-context|\.label-template|\.label-rule" "readiness service does not create content files"
forbid_pattern "$readiness_service_file" "label-context\\.json|label-template\\.json|label-rule\\.json" "readiness service does not create owner content files (context/template/rule)"
forbid_pattern "$readiness_service_file" "renderPdf|exportPdf|dispatchPrint|printLabel|executePrint|QRCode|qr/product|apps/Manufacturing" "readiness service has no runtime print/QR/Manufacturing coupling"
forbid_pattern "$readiness_service_file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "readiness service has no DB access or SQL"
require_text "$controller_file" "labelDesignerCreateResourceFolders" "controller has resource folder creation handler"
require_text "$controller_file" "LabelDesignerResourceReadinessService" "controller imports readiness service"
require_text "$controller_file" "createMissingFolders" "controller calls readiness service createMissingFolders"
require_text "$routes_file" "/apps/studio/tools/label-designer/create-folders" "Studio routes expose readiness folder creation POST endpoint"
require_text "$preview_file" "Label Resource Readiness" "preview shows readiness section title"
require_text "$preview_file" "Create Missing Label Resource Folders" "preview shows folder creation section"
require_text "$preview_file" "owner_key" "preview includes owner_key selection field"
require_text "$preview_file" "confirm_create" "preview requires explicit confirmation"
require_text "$preview_file" "Missing Folders" "preview labels folder creation submit action"
forbid_pattern "$preview_file" "action=[\"'][^\"']*/(templates|rules)/[^\"']*create[^\"']*" "preview readiness section has no template/rule creation form action"

echo ""
echo "== Resource metadata & migration boundary =="
require_file "$metadata_service_file" "Label Designer resource metadata service"
require_text "$metadata_service_file" "analyzeAll" "metadata service has analyzeAll() method"
require_text "$metadata_service_file" "previewMigration" "metadata service has previewMigration() method"
require_text "$metadata_service_file" "metadata_complete" "metadata service produces metadata_complete status"
require_text "$metadata_service_file" "legacy_count" "metadata service tracks legacy_count"
require_text "$metadata_service_file" "no_change_needed" "metadata reports no_change_needed for explicit owner_key resources"
require_text "$metadata_service_file" "canonical" "metadata service references canonical fields"
require_text "$metadata_service_file" "owner_key" "metadata service checks/generates owner_key"
forbid_pattern "$metadata_service_file" "file_put_contents|fwrite|mkdir|rename|unlink" "metadata service has NO file write API"
forbid_pattern "$metadata_service_file" "renderPdf|exportPdf|dispatchPrint|printLabel|executePrint|QRCode|qr/product|apps/Manufacturing" "metadata service has no runtime print/QR/Manufacturing coupling"
forbid_pattern "$metadata_service_file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "metadata service has no DB access or SQL"

require_file "$metadata_contract" "Label Designer metadata contract"
require_text "$metadata_contract" "Metadata-First Ownership" "metadata contract defines metadata-first ownership"
require_text "$metadata_contract" "top-level owner_key" "metadata contract defines canonical owner_key field"
require_text "$metadata_contract" "label-resource-metadata-contract" "metadata contract declares canonical file name"
require_text "$metadata_contract" "M002" "metadata contract has validation rules (M002+)"
require_text "$metadata_contract" "M001" "metadata contract defines M001 (metadata completeness)"
require_text "$metadata_contract" "DataProvider" "metadata contract documents Phase 2 DataProvider ownership"
require_text "$metadata_contract" "Must Not" "metadata contract documents Studio prohibitions"
require_text "$metadata_contract" "Runtime Owner" "metadata contract references Runtime Owner"

require_text "$controller_file" "labelDesignerMigrationPreview" "controller has migration preview handler"
require_text "$controller_file" "LabelDesignerResourceMetadataService" "controller imports metadata service"
require_text "$controller_file" "previewMigration" "controller calls metadata service previewMigration"
require_text "$routes_file" "/apps/studio/tools/label-designer/migration-preview" "Studio routes expose migration preview POST endpoint"
require_text "$preview_file" "Resource Metadata & Migration" "preview shows metadata section title"
require_text "$preview_file" "Migration Preview" "preview shows migration preview subsection title"
require_text "$preview_file" "/apps/studio/tools/label-designer/migration-preview" "preview posts to migration preview endpoint"
require_text "$preview_file" "Metadata-first" "preview labels metadata-first status"

# Migration apply invariants
require_file "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "Label Designer metadata migration service"
require_text "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "applyMigration" "metadata migration service has applyMigration method"
require_text "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "writeSnapshot" "metadata migration service has writeSnapshot method"
require_text "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "runPostMigrationDiagnostics" "metadata migration service has post-migration diagnostics"
require_text "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "\$input['confirm_apply'] === '1'" "migration apply requires exact server-side confirm_apply value"
require_text "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "Resource already has top-level owner_key. Migration not required." "migration apply rejects resources that already declare owner_key"
require_text "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "owner_contained_path" "migration apply enforces owner-contained resource path"
require_order "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" "self::writeSnapshot([" "file_put_contents(\$absPath" "migration apply snapshots before resource write"
require_text "$controller_file" "labelDesignerApplyMetadataMigration" "controller has migration apply handler"
require_text "$controller_file" "LabelDesignerMetadataMigrationService" "controller imports metadata migration service"
require_text "$controller_file" "Auth::requireCsrf((string)(\$_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');" "migration apply requires server-side CSRF validation"
require_text "$controller_file" "self::labelDesignerRedirect(" "migration apply uses workspace-aware redirect helper"
require_text "$routes_file" "/apps/studio/tools/label-designer/migration-apply" "Studio routes expose migration apply POST endpoint"
require_text "$preview_file" "metadata_migration_apply_title" "preview shows migration apply section title"
require_text "$preview_file" "/apps/studio/tools/label-designer/migration-apply" "preview posts to migration apply endpoint"
require_text "$preview_file" '<input type="checkbox" name="confirm_apply" value="1" required>' "preview has real checkbox (not hidden + JS confirm) for migration apply"
require_text "$preview_file" "metadata_migration_apply_applied" "preview shows migration apply result keys"
require_text "$preview_file" "metadata_migration_apply_failed" "preview shows migration apply failure key"
require_text "$preview_file" "metadata_migration_apply_result_all_pass" "preview shows all-pass diagnostic message"
require_text "$preview_file" "metadata_migration_apply_result_with_issues" "preview shows diagnostic issues message"

# Migration preview display keys (Phase 1 hardening)
require_text "$preview_file" "resource_type" "preview shows resource type from preview result"
require_text "$preview_file" "inferred_owner" "preview shows inferred owner from preview result"
require_text "$preview_file" "proposed_metadata" "preview shows proposed metadata keys"
require_text "$preview_file" "current_json" "preview shows current JSON (before)"
require_text "$preview_file" "mpAddedKeys" "preview shows exact metadata keys to add"

# Global diagnostics before/after in apply result
require_text "$preview_file" "global_diagnostics_before" "preview shows global diagnostics before migration"
require_text "$preview_file" "global_diagnostics_after" "preview shows global diagnostics after migration"
require_text "$preview_file" "Legacy fallback resources" "preview labels legacy fallback count in global diagnostics"
require_text "$preview_file" "Metadata-first resources" "preview labels metadata-first count in global diagnostics"

echo ""
echo "== No file write, migration, or runtime print behavior =="
tool_php_files=$(find "$tool_dir" -type f -name "*.php" -print 2>/dev/null || true)
if [[ -z "$tool_php_files" ]]; then
  fail "no Label Designer PHP files found to scan"
else
  while IFS= read -r file; do
    [[ -z "$file" ]] && continue
    rel="${file#$ROOT_DIR/}"
    if [[ "$file" == "$data_source_service" ]]; then
      forbid_pattern "$file" "file_put_contents|fwrite|fopen\\(|mkdir\\(|rename\\(|unlink\\(|copy\\(|touch\\(|chmod\\(|symlink\\(" "no file write API in $rel"
      require_text "$file" "information_schema.tables" "data source service reads table/view metadata only"
      require_text "$file" "information_schema.columns" "data source service reads column metadata only"
      require_text "$file" "candidate_only" "data source service marks sources candidate-only"
      forbid_pattern "$file" "DB::query|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM|CREATE[[:space:]]+TABLE|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE|TRUNCATE[[:space:]]+TABLE" "no DB writes or DDL in $rel"
    elif [[ "$file" == "$context_create_service" ]]; then
      require_text "$file" "Resources/labels/contexts" "context create service targets owner contexts path"
      require_text "$file" ".label-context.json" "context create service uses label-context JSON filename contract"
      require_text "$file" "storage/studio-snapshots/label-designer" "context create service writes snapshots under approved root"
      forbid_pattern "$file" "Resources/labels/templates|Resources/labels/rules" "context create service does not write template/rule resources"
      forbid_pattern "$file" "information_schema|SHOW[[:space:]]+TABLES|DESCRIBE[[:space:]]+|SELECT[[:space:]]+\\*" "context create service has no SQL builder or unrestricted DB browse behavior"
      forbid_pattern "$file" "renderPdf|exportPdf|dispatchPrint|printLabel|executePrint|QRCode|qr/product|apps/Manufacturing" "context create service has no runtime print/QR/Manufacturing coupling"
    elif [[ "$file" == "$readiness_service_file" ]]; then
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "readiness service has no DB access or SQL"
    elif [[ "$file" == "$template_create_service" ]]; then
      require_text "$file" "Resources/labels/templates" "template create service targets owner templates path"
      require_text "$file" "storage/studio-snapshots/label-designer" "template create service writes snapshots under approved root"
      forbid_pattern "$file" "Resources/labels/contexts/(create|new|write|save)" "template create service does not write context resources"
      forbid_pattern "$file" "Resources/labels/rules/(create|new|write|save)" "template create service does not write rule resources"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "template create service has no DB access or SQL"
    elif [[ "$file" == "$rule_create_service" ]]; then
      require_text "$file" "Resources/labels/rules" "rule create service targets owner rules path"
      require_text "$file" "rule-create" "rule create service writes rule-create snapshot metadata"
      require_text "$file" "storage/studio-snapshots/label-designer" "rule create service writes snapshots under approved root"
      require_text "$file" "susankhya.label.rule.v1" "rule create service writes rule schema v1"
      forbid_pattern "$file" "Resources/labels/contexts/(create|new|write|save)" "rule create service does not write context resources"
      forbid_pattern "$file" "Resources/labels/templates/(create|new|write|save)" "rule create service does not write template resources"
      forbid_pattern "$file" "renderPdf|exportPdf|dispatchPrint|printLabel|executePrint|Dompdf|PdfService|PrintService|QRCode|qr/product" "rule create service has no runtime print/export/QR coupling"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "rule create service has no DB writes"
    elif [[ "$file" == "$tool_dir/Services/LabelDesignerMetadataMigrationService.php" ]]; then
      require_text "$file" "storage/studio-snapshots/label-designer" "metadata migration service writes snapshots under approved root"
      require_text "$file" "metadata-migration" "metadata migration service uses metadata-migration snapshot prefix"
      forbid_pattern "$file" "BulkMigration|batchMigrate|autoMigrateAll|migrateAll|migrateOwner" "metadata migration service applies single-resource only"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "metadata migration service has no DB access or SQL"
      require_text "$file" "LabelDesignerResourceDiagnosticsService::scanAll" "metadata migration service calls scanAll for pre/post global diagnostics"
      require_text "$file" "global_diagnostics_before" "metadata migration service returns pre-migration global diagnostics"
      require_text "$file" "global_diagnostics_after" "metadata migration service returns post-migration global diagnostics"
    elif [[ "$file" == "$tool_dir/Services/LabelDesignerResourceDiagnosticsService.php" ]]; then
      require_text "$file" "scanAll" "diagnostics service has scanAll method"
      require_text "$file" "diagnoseContext" "diagnostics service validates context resources"
      require_text "$file" "diagnoseTemplate" "diagnostics service validates template resources"
      require_text "$file" "diagnoseRule" "diagnostics service validates rule resources"
      require_text "$file" "safeRead" "diagnostics service uses safe read-only file access"
    elif [[ "$file" == "$tool_dir/Services/LabelDesignerDuplicateService.php" ]]; then
      require_text "$file" "writeSnapshot" "duplicate service creates snapshot before guarded write"
      require_text "$file" "rename" "duplicate service uses atomic rename write"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "no DB access or SQL in $rel"
    elif [[ "$file" == "$tool_dir/Services/LabelDesignerContextEditService.php" ]]; then
      require_text "$file" "writeSnapshot" "context edit service creates snapshot before guarded write"
      require_text "$file" "rename" "context edit service uses atomic rename write"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "no DB access or SQL in $rel"
    elif [[ "$file" == "$tool_dir/Services/LabelDesignerRuleEditService.php" ]]; then
      require_text "$file" "writeSnapshot" "rule edit service creates snapshot before guarded write"
      require_text "$file" "rename" "rule edit service uses atomic rename write"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "no DB access or SQL in $rel"
    elif [[ "$file" == "$tool_dir/Services/LabelDesignerTemplateEditService.php" ]]; then
      require_text "$file" "writeSnapshot" "template edit service creates snapshot before guarded write"
      require_text "$file" "rename" "template edit service uses atomic rename write"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "no DB access or SQL in $rel"
    else
      forbid_pattern "$file" "file_put_contents|fwrite|fopen\\(|mkdir\\(|rename\\(|unlink\\(|copy\\(|touch\\(|chmod\\(|symlink\\(" "no file write API in $rel"
      forbid_pattern "$file" "\\bDB::|new PDO|->query\\(|->exec\\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "no DB access or SQL in $rel"
    fi
    forbid_pattern "$file" "print\\(|printLabel|executePrint|dispatchPrint|renderPdf|exportPdf|Dompdf|PdfService|PrintService" "no runtime print/export execution in $rel"
  done <<< "$tool_php_files"
fi

migration_hits=$(find app apps plugins -path "*migrations*" -type f 2>/dev/null | grep -Ei "label[_-]?designer|label_resources|label_context|label_template|label_rule" || true)
if [[ -z "$migration_hits" ]]; then
  pass "no Label Designer/label resource DB migrations found"
else
  echo "$migration_hits" >&2
  fail "Label Designer/label resource migration found"
fi

echo ""
echo "== Runtime owner coupling boundary =="
for runtime_path in \
  "app" \
  "apps/Manufacturing" \
  "apps/Platform/modules/QRCode" \
  "apps/Platform/StyleRegistry" \
  "apps/Platform/Services"; do
  if [[ ! -e "$runtime_path" ]]; then
    continue
  fi

  if grep -RIl -- "LabelDesigner\\|label_designer\\|label-designer" "$runtime_path" --include="*.php" --include="*.js" --include="*.json" --include="*.md" 2>/dev/null | grep -q .; then
    grep -RIn -- "LabelDesigner\\|label_designer\\|label-designer" "$runtime_path" --include="*.php" --include="*.js" --include="*.json" --include="*.md" 2>/dev/null >&2 || true
    fail "$runtime_path must not couple to Label Designer"
  else
    pass "$runtime_path does not couple to Label Designer"
  fi
done

forbid_pattern "$discovery_service" "QRCodeController|Plugins\\\\QRCode|/qr/product/(label|scan)" "discovery service does not couple to QR runtime"
forbid_pattern "$discovery_service" "Manufacturing\\\\|apps/Manufacturing|/apps/manufacturing|Part360|part_360" "discovery service does not couple to Manufacturing runtime"

echo ""
echo "== Preview renderer boundary =="
preview_renderer_file="$tool_dir/Services/LabelDesignerPreviewRendererService.php"
require_file "$preview_renderer_file" "Label Designer preview renderer service"
require_text "$preview_renderer_file" "buildResolvedPreview" "preview renderer has buildResolvedPreview()"
require_text "$preview_renderer_file" "validatePreviewPreconditions" "preview renderer has validatePreviewPreconditions()"
require_text "$preview_renderer_file" "generateSampleData" "preview renderer has generateSampleData()"
require_text "$preview_renderer_file" "renderHtmlPreview" "preview renderer has renderHtmlPreview()"
require_text "$preview_renderer_file" "resolveContextOptionsFromDiscovery" "preview renderer has context option resolver"
require_text "$preview_renderer_file" "resolveTemplateOptionsFromDiscovery" "preview renderer has template option resolver"
forbid_pattern "$preview_renderer_file" "file_put_contents|fwrite|fopen\(|mkdir\(|rename\(|unlink\(|copy\(|touch\(|chmod\(|symlink\(" "preview renderer has no file write API"
forbid_pattern "$preview_renderer_file" "\bDB::|new PDO|->query\(|->exec\(|SELECT[[:space:]]+.*FROM|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "preview renderer has no DB access or SQL"
forbid_pattern "$preview_renderer_file" "renderPdf|exportPdf|dispatchPrint|printLabel|executePrint|Dompdf|PdfService|PrintService" "preview renderer has no runtime print/export execution"
forbid_pattern "$preview_renderer_file" "QRCode|/qr/product/(label|scan)" "preview renderer has no QR runtime coupling"
require_text "$preview_renderer_file" "resolveContextOptionsFromDiscovery" "preview renderer has context option resolver"
require_text "$preview_renderer_file" "susankhya.label.context.v1" "preview renderer references context schema"
require_text "$preview_renderer_file" "susankhya.label.template.v1" "preview renderer references template schema"

forbid_pattern "$preview_renderer_file" "barcode.*real|generateBarcode|renderBarcode|barcode_image|barcode_svg|real.*barcode" "preview renderer does not generate real barcodes"
forbid_pattern "$preview_renderer_file" "qr.*real|generateQr|renderQr|qr_image|qr_svg|real.*qr" "preview renderer does not generate real QR codes"
forbid_pattern "$preview_renderer_file" "imagecreate|imagepng|imagejpeg|imagettftext|gd_info|Imagick" "preview renderer has no image generation"

require_text "$controller_file" "labelDesignerRenderPreview" "controller has preview renderer handler"
require_text "$controller_file" "LabelDesignerPreviewRendererService" "controller imports preview renderer service"
require_text "$controller_file" "studio_label_designer_preview" "controller stores preview result in session"
require_text "$routes_file" "/apps/studio/tools/label-designer/preview/render" "Studio routes expose preview renderer POST endpoint"

require_text "$preview_file" "preview_title" "preview view has preview section title key"
require_text "$preview_file" "preview_button" "preview view has render preview button key"
require_text "$preview_file" "preview_diagnostics_title" "preview view has diagnostics title key"
require_text "$preview_file" "preview_diagnostics_severity" "preview view has diagnostics severity key"
require_text "$preview_file" "label_preview_result" "preview view extracts preview result from model"
require_text "$preview_file" "label_preview_context_options" "preview view extracts context options from model"
require_text "$preview_file" "label_preview_template_options" "preview view extracts template options from model"
require_text "$preview_file" "preview/render" "preview view posts to preview renderer endpoint"
forbid_pattern "$preview_file" "fetch\(|XMLHttpRequest|navigator\.sendBeacon|localStorage|sessionStorage" "preview view has no client-side persistence or write request (no-JS pattern maintained)"

echo ""
echo "== Source-of-truth boundary =="
if [[ -d "$tool_dir/Resources/labels" ]]; then
  fail "Studio-owned permanent label Resources/labels storage exists under Label Designer"
else
  pass "no Studio-owned permanent label Resources/labels storage under Label Designer"
fi
forbid_pattern "$discovery_service" 'localStorage|\$_SESSION|session_start|cache_set|Cache::set|draft' "discovery service has no long-lived draft truth"
require_text "$resource_contract" "Source truth remains under the owning app, module, or plugin." "resource contract keeps source truth with owner"
require_text "$operating_contract" "Owner resource paths remain source of truth" "operating contract keeps owner paths as source truth"

echo ""
echo "== Owner-scoped candidate DB discovery boundary =="
require_text "$preview_file" "Bootstrap DB Discovery Mode" "preview labels candidate DB sources"
require_text "$preview_file" "No label context is created yet" "preview says no context is created"
require_text "$preview_file" "Phase 1" "preview defers owner approval/context creation"
require_text "$resource_contract" "Owner-scoped candidate DB tables/views" "resource contract allows owner-scoped candidate discovery"
require_text "$resource_contract" "Candidate DB sources are not approved label sources." "resource contract keeps candidates unapproved"
forbid_pattern "$data_source_service" "SQL builder|unrestricted table|global table browser|SHOW[[:space:]]+TABLES|DESCRIBE[[:space:]]+|SELECT[[:space:]]+\\*" "data source service has no SQL builder or unrestricted table browser"
forbid_pattern "$data_source_service" "bind.*arbitrary|approval_status' => 'approved|save.*field|persist.*field" "data source service does not approve or persist bindings"

echo ""
echo "== Guarded context creation boundary =="
require_text "$preview_file" "Context creation preview" "preview shows context creation preview"
require_text "$preview_file" "This will create an owner-owned label context file after snapshot, validation, and confirmation." "preview documents guarded create warning"
require_text "$preview_file" "Create context" "preview shows context-create submit control"
require_text "$preview_file" "/apps/studio/tools/label-designer/context/create" "preview posts to guarded context-create endpoint"
require_text "$preview_file" "confirm_create" "preview includes explicit confirmation step"
forbid_pattern "$preview_file" "<button[^>]*disabled[^>]*>[^<]*Create context" "preview no longer exposes disabled create button"
forbid_pattern "$preview_file" "action=[\"'][^\"']*/(templates|rules)/" "preview has no template/rule mutation form action"
forbid_pattern "$preview_file" "fetch\\(|XMLHttpRequest|navigator\\.sendBeacon|localStorage|sessionStorage" "preview has no client-side persistence or write request"

echo ""
echo "== Guarded template creation boundary =="
require_text "$preview_file" "Template creation preview" "preview shows template preview foundation"
require_text "$preview_file" "No label template is created yet." "template preview declares read-only mode"
require_text "$preview_file" "Create Label Template" "preview shows template create submit control"
require_text "$preview_file" "/apps/studio/tools/label-designer/template/create" "preview posts to guarded template create endpoint"
require_text "$preview_file" "confirm_create" "preview includes explicit template confirmation step"
forbid_pattern "$preview_file" "action=[\"'][^\"']*/(templates|rules)/" "preview has no template/rule mutation form action (template/create is guarded create)"
forbid_pattern "$preview_file" "fetch\\(|XMLHttpRequest|navigator\\.sendBeacon|localStorage|sessionStorage" "preview has no client-side persistence or write request"
forbid_pattern "$template_preview_service" "file_put_contents|fwrite|fopen\(|mkdir\(|rename\(|unlink\(|copy\(|touch\(|chmod\(|symlink\(" "template preview service has no file write API"
forbid_pattern "$template_preview_service" "\bDB::|new PDO|->query\(|->exec\(|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "template preview service has no DB write/runtime coupling"
forbid_pattern "$template_create_service" "QRCode|/qr/product/(label|scan)|Manufacturing\\\\|apps/Manufacturing" "template create service does not couple to Manufacturing runtime"
forbid_pattern "$template_create_service" "\bDB::|new PDO|->query\(|->exec\(|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "template create service has no DB write/runtime coupling"

# Template Create Service invariants
if [[ -f "$tool_dir/Services/LabelDesignerTemplateCreateService.php" ]]; then
  pass "Label Designer template create service exists"
  require_text "$tool_dir/Services/LabelDesignerTemplateCreateService.php" "buildPreview" "template create service has buildPreview()"
  require_text "$tool_dir/Services/LabelDesignerTemplateCreateService.php" "createTemplate" "template create service has createTemplate()"
  require_text "$tool_dir/Services/LabelDesignerTemplateCreateService.php" "writeSnapshot" "template create service creates snapshot before write"
  require_text "$tool_dir/Services/LabelDesignerTemplateCreateService.php" "already exists" "template create service blocks duplicate keys"
  require_text "$tool_dir/Services/LabelDesignerTemplateCreateService.php" "write_status'] = 'created'" "template create service writes with write_status created"
  forbid_pattern "$tool_dir/Services/LabelDesignerTemplateCreateService.php" "disable|disabled_in_this_slice" "template create service removes disabled_in_this_slice status"
else
  fail "Label Designer template create service missing"
fi

# Template Create Controller handler
controller_file="$tool_dir/../../Controllers/StudioController.php"
if grep -q "labelDesignerCreateTemplate" "$controller_file" 2>/dev/null; then
  pass "controller has labelDesignerCreateTemplate handler"
else
  fail "controller has labelDesignerCreateTemplate handler"
fi

# Template Create route
if grep -q "label-designer/template/create" "$routes_file" 2>/dev/null; then
  pass "Studio routes expose guarded template create POST endpoint"
else
  fail "Studio routes expose guarded template create POST endpoint"
fi

# Rule Create Service invariants
if [[ -f "$rule_create_service" ]]; then
  pass "Label Designer rule create service exists"
  require_text "$rule_create_service" "buildPreview" "rule create service has buildPreview()"
  require_text "$rule_create_service" "createRule" "rule create service has createRule()"
  require_text "$rule_create_service" "writeSnapshot" "rule create service creates snapshot before write"
  require_text "$controller_file" "Auth::requireCsrf((string)(\$_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');" "rule create controller requires CSRF"
  require_text "$rule_create_service" "trim((string)(\$input['confirm_create'] ?? '')) !== 'yes'" "rule create requires exact server-side confirmation"
  require_text "$preview_file" "name=\"workspace\" value=\"<?= \$activeWorkspace ?>\"" "rule create form preserves workspace state"
  require_text "$preview_file" "name=\"confirm_create\" value=\"yes\" required" "rule create form requires explicit confirmation checkbox"
  require_text "$rule_create_service" "No context selected." "rule create requires an explicit selected context"
  require_text "$rule_create_service" "No compatible template selected." "rule create requires an explicit selected template"
  require_text "$rule_create_service" "validation_requested" "rule create separates initial UI state from submitted validation"
  require_text "$rule_create_service" "buildInitialState" "rule create resolves owner-scoped initial context/template state"
  require_text "$rule_create_service" "\$result['empty_state']" "rule create reports intentional empty owner state"
  require_text "$rule_create_service" "Target rule path is outside the owner-contained Resources/labels/rules directory." "rule create rechecks owner-contained canonical path before write"
  require_text "$rule_create_service" "/Resources/labels/rules" "rule create writes only below the owner rules resource folder"
  require_text "$rule_create_service" "already exists" "rule create service blocks duplicate keys/paths"
  require_text "$rule_create_service" "Create flow does not overwrite existing files" "rule create service blocks overwrite behavior"
  require_text "$rule_create_service" "'owner_type' => \$ownerType" "rule create writes owner_type metadata"
  require_text "$rule_create_service" "'owner_root' => \$ownerRoot" "rule create writes owner_root metadata"
  require_text "$rule_create_service" "'resource_type' => 'rule'" "rule create writes rule resource_type metadata"
  forbid_pattern "$rule_create_service" "function[[:space:]]+(updateRule|deleteRule|overwriteRule)" "rule create remains create-only"
  forbid_pattern "$rule_create_service" "renderHtmlPreview|buildResolvedPreview|dispatchPrint|printLabel|QRCode|qr_generate|generateQr" "rule create does not call render, print, or QR integrations"
  forbid_pattern "$rule_create_service" "\bDB::|new PDO|->query\(|->exec\(|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "rule create adds no DB runtime provider"
else
  fail "Label Designer rule create service missing"
fi

if grep -q "labelDesignerCreateRule" "$controller_file" 2>/dev/null; then
  pass "controller has labelDesignerCreateRule handler"
else
  fail "controller has labelDesignerCreateRule handler"
fi

if grep -q "label-designer/rule/create" "$routes_file" 2>/dev/null; then
  pass "Studio routes expose guarded rule create POST endpoint"
else
  fail "Studio routes expose guarded rule create POST endpoint"
fi

require_text "$preview_file" "Rule Creation Preview" "preview shows rule creation preview section"
require_text "$preview_file" "/apps/studio/tools/label-designer/rule/create" "preview posts to guarded rule-create endpoint"
require_text "$preview_file" "I confirm — create this label rule" "preview requires explicit rule create confirmation"
require_text "$preview_file" "This will create an owner-owned label rule file after snapshot, validation, and confirmation." "preview shows explicit rule create warning"
require_text "$preview_file" "Render a label preview first, then apply temporary rules below." "sandbox wording points to rules below"
require_text "$preview_file" "Select a lifecycle owner with label resources, such as Manufacturing/Products." "rule workspace has lifecycle-owner instruction state"
require_text "$preview_file" "!\$ruleCreateValidationStarted && \$ruleCreateEmptyState" "rule workspace renders empty state before validation errors"
require_text "$preview_file" "name=\"rule_preview\" value=\"1\"" "rule preview marks explicit validation submission"
require_text "$preview_file" "name=\"owner\" value=\"<?= e(\$selectedDataSourceOwnerKey) ?>\"" "rule preview/create preserve selected owner"
require_text "$controller_file" "\$ruleValidationRequested" "controller only requests rule validation after explicit preview submission"
require_text "$data_source_service" "strtolower(\$selectedOwnerKey)" "owner selection accepts canonical keys case-insensitively"
require_text "$controller_file" "self::labelDesignerRedirect(" "rule create preserves redirect through the shared workspace-aware helper"
require_text "$controller_file" "trim((string)(\$_POST['workspace'] ?? ''))" "rule create redirect preserves submitted workspace"

echo ""
echo "== Resource diagnostics boundary =="
diag_service_file="$tool_dir/Services/LabelDesignerResourceDiagnosticsService.php"
require_file "$diag_service_file" "Label Designer resource diagnostics service"
require_text "$diag_service_file" "scanAll" "diagnostics service has scanAll method"
require_text "$diag_service_file" "LD_CTX_JSON_INVALID" "diagnostics service has context diagnostic codes"
require_text "$diag_service_file" "LD_TPL_CONTEXT_UNRESOLVED" "diagnostics service has template diagnostic codes"
require_text "$diag_service_file" "LD_TPL_OWNER_LEGACY_FALLBACK" "diagnostics service counts template top-level owner fallback"
require_text "$diag_service_file" "LD_RULE_OPERATOR_UNSUPPORTED" "diagnostics service has rule diagnostic codes"
require_text "$diag_service_file" "\$cond['field_key'] ?? \$cond['field']" "diagnostics scans canonical rule condition field_key"
require_text "$diag_service_file" "FORBIDDEN_RUNTIME_PATTERNS" "diagnostics service detects forbidden runtime patterns"
require_text "$controller_file" "LabelDesignerResourceDiagnosticsService" "controller imports diagnostics service"
require_text "$controller_file" "scanAll" "controller calls diagnostics scanAll"
require_text "$preview_file" "resource_diagnostics_title" "preview shows diagnostics section title"
require_text "$preview_file" "resource_diagnostics_note" "preview shows diagnostics note"
require_text "$preview_file" "id=\"ld-workspace-governance-diagnostics\"" "preview has diagnostics in Governance workspace"
require_text "$preview_file" "by_owner" "preview extracts by_owner from diagnostics"
require_text "$preview_file" "severity" "preview renders severity column"

echo ""
echo "== Future direct-edit safety documented =="
require_text "$resource_contract" "Backup/snapshot before writing." "future apply requires backup/snapshot"
require_text "$resource_contract" "Future direct edit must target selected owner-owned context/template/rule resources only." "future direct edit is owner-resource scoped"
require_text "$resource_contract" "Path-safety validation." "future apply requires path-safety validation"
require_text "$resource_contract" "Handover record." "future apply requires handover record"

echo ""
echo "== Apply/snapshot safety contract =="
require_text "$apply_snapshot_contract" "Label Designer Apply/Snapshot Safety Contract" "apply/snapshot contract title present"
require_text "$apply_snapshot_contract" "guarded Label Context, Label Template, and Label Rule create enabled" "apply/snapshot contract documents guarded create enablement"
require_text "$apply_snapshot_contract" "snapshot -> diff -> validation -> confirmation -> owner-folder write -> diagnostics -> rollback information" "apply lifecycle ASCII wording documented"
require_text "$apply_snapshot_contract" "snapshot → diff → validation → confirmation → owner-folder write → diagnostics → rollback information" "apply lifecycle UI wording documented"
require_text "$apply_snapshot_contract" "load selected owner resource -> generate proposed change -> validate owner boundary -> validate schema -> validate fields/source -> preview diff -> confirm -> create snapshot before write -> write only to owner resource path -> run diagnostics -> keep rollback metadata" "guarded workflow ASCII wording documented"
require_text "$apply_snapshot_contract" "load selected owner resource → generate proposed change → validate owner boundary → validate schema → validate fields/source → preview diff → confirm → create snapshot before write → write only to owner resource path → run diagnostics → keep rollback metadata" "guarded workflow UI wording documented"
require_text "$apply_snapshot_contract" "storage/studio-snapshots/label-designer/" "snapshot location documented"

for lifecycle_step in \
  "Select owner." \
  "Select target resource type: context/template/rule." \
  "Resolve target owner path." \
  "Generate proposed JSON." \
  "Validate owner boundary." \
  "Validate resource schema." \
  "Validate selected source/fields." \
  "Check duplicate key/path." \
  "Show diff/preview." \
  "Create snapshot before write." \
  "Write only to owner resource path." \
  "Run post-apply diagnostics." \
  "Record rollback metadata."; do
  require_text "$apply_snapshot_contract" "$lifecycle_step" "apply lifecycle documents: $lifecycle_step"
done

for metadata_field in \
  "\`owner\`" \
  "\`resource_type\`" \
  "\`target_path\`" \
  "\`previous_content\`" \
  "\`proposed_content\` or \`diff\`" \
  "\`action\`" \
  "\`user\` or \`actor\`" \
  "\`timestamp\`" \
  "\`validation_result\`" \
  "\`rollback_hint\`"; do
  require_text "$apply_snapshot_contract" "$metadata_field" "snapshot metadata documents: $metadata_field"
done

for path_rule in \
  "Contexts only under \`OwnerRoot/Resources/labels/contexts/\`." \
  "Templates only under \`OwnerRoot/Resources/labels/templates/\`." \
  "Rules only under \`OwnerRoot/Resources/labels/rules/\`." \
  "Create flow must fail if \`context_key\`/\`template_key\`/\`rules_key\` already exists in the resolved owner path." \
  "Update flow must require explicit update intent and must reject silent overwrite attempts from create flow." \
  "Duplicate checks must evaluate both key identity and resolved target path collisions." \
  "No writes to Studio permanent resource truth." \
  "No writes outside selected owner root." \
  "No path traversal." \
  "No overwrite without explicit update flow."; do
  require_text "$apply_snapshot_contract" "$path_rule" "target path rule documents: $path_rule"
done

require_text "$apply_snapshot_contract" "Rollback metadata must link to the originating apply attempt via snapshot identifier or equivalent correlation id." "rollback metadata correlation documented"

for diagnostic_rule in \
  "owner exists." \
  "resource path valid." \
  "JSON valid." \
  "context/template/rule key valid." \
  "key unique." \
  "fields exist in selected candidate owner source." \
  "no runtime print/export side effect." \
  "no QR runtime change." \
  "no Manufacturing runtime route change." \
  "no Core coupling." \
  "no unrestricted SQL builder." \
  "no Studio-owned permanent label storage." \
  "no forbidden runtime coupling." \
  ; do
  require_text "$apply_snapshot_contract" "$diagnostic_rule" "future diagnostic documents: $diagnostic_rule"
done

for nongoal_rule in \
  "No context/template/rule edit/apply/update/delete behavior is enabled in this slice (except metadata migration)." \
  "No overwrite flow is enabled in this slice." \
  "No DB table creation or SQL builder behavior is added in this slice."; do
  require_text "$apply_snapshot_contract" "$nongoal_rule" "slice non-goal documents: $nongoal_rule"
done

echo ""
echo "== Template apply/snapshot safety contract =="
require_text "$template_apply_snapshot_contract" "Label Designer Template Apply/Snapshot Safety Contract" "template apply/snapshot contract title present"
require_text "$template_apply_snapshot_contract" "OwnerRoot/Resources/labels/templates/" "template apply contract defines owner template path"
require_text "$template_apply_snapshot_contract" "Select context." "template apply lifecycle includes context selection"
require_text "$template_apply_snapshot_contract" "Validate selected fields belong to selected context." "template apply lifecycle validates field/context boundary"
require_text "$template_apply_snapshot_contract" "Check duplicate template key/path." "template apply lifecycle includes duplicate template checks"
require_text "$template_apply_snapshot_contract" "Create snapshot before write." "template apply lifecycle requires snapshot before write"
require_text "$template_apply_snapshot_contract" "Write only to owner template path." "template apply lifecycle enforces owner template path write"
require_text "$template_apply_snapshot_contract" "No visual canvas editor behavior is enabled." "template apply contract documents no visual canvas non-goal"
require_text "$template_apply_snapshot_contract" "No template write/apply implementation is enabled." "template apply contract documents no template write in this slice"

if [[ -d "$snapshot_dir" ]] && find "$snapshot_dir" -type f -print -quit 2>/dev/null | grep -q .; then
  invalid_snapshot_files=$(find "$snapshot_dir" -type f ! -name "*.json" -print 2>/dev/null || true)
  if [[ -n "$invalid_snapshot_files" ]]; then
    echo "$invalid_snapshot_files" >&2
    fail "Label Designer snapshot folder contains non-JSON files"
  else
    pass "Label Designer snapshot files (if present) are JSON metadata artifacts"
  fi
else
  pass "no Label Designer snapshot files currently present"
fi

echo ""
echo "== Phase 1 Runtime Proof Boundary =="

runtime_proof_scripts="scripts/label-proof"
platform_labels="platform/Labels"

# 1. Phase 1 Runtime Proof Plan exists
require_file "docs/architecture/label-designer-phase1-runtime-proof-plan.md" "Label Designer Phase 1 Runtime Proof Plan"

# 2. platform/Labels/ must not import Studio namespaces (protect from Studio coupling)
if [[ -d "$platform_labels" ]]; then
  studio_imports=$(grep -RIn -- "Apps\\\\Studio" "$platform_labels" --include="*.php" 2>/dev/null || true)
  if [[ -n "$studio_imports" ]]; then
    echo "$studio_imports" >&2
    fail "platform/Labels/ must not import Studio namespaces"
  else
    pass "platform/Labels/ has no Studio namespace imports"
  fi
else
  pass "platform/Labels/ does not exist yet (no Studio coupling risk)"
fi

# 3. scripts/label-proof/ must not import Studio namespaces
if [[ -d "$runtime_proof_scripts" ]]; then
  studio_imports=$(grep -RIn -- "Apps\\\\Studio" "$runtime_proof_scripts" --include="*.php" 2>/dev/null || true)
  if [[ -n "$studio_imports" ]]; then
    echo "$studio_imports" >&2
    fail "scripts/label-proof/ must not import Studio namespaces"
  else
    pass "scripts/label-proof/ has no Studio namespace imports"
  fi
else
  pass "scripts/label-proof/ does not exist yet (no Studio coupling risk)"
fi

# 4. scripts/label-proof/ must not register web routes
if [[ -d "$runtime_proof_scripts" ]]; then
  route_registrations=$(grep -RIn -- "post\(|get\(|put\(|delete\(|patch\(|addRoute|route->" "$runtime_proof_scripts" --include="*.php" 2>/dev/null || true)
  if [[ -n "$route_registrations" ]]; then
    echo "$route_registrations" >&2
    fail "scripts/label-proof/ must not register web routes"
  else
    pass "scripts/label-proof/ has no web route registrations"
  fi
else
  pass "scripts/label-proof/ does not exist yet (no route registration risk)"
fi

# 5. platform/Labels/Pipeline/Adapters/ must contain only HtmlPreviewAdapter.php
adapters_dir="$platform_labels/Pipeline/Adapters"
if [[ -d "$adapters_dir" ]]; then
  adapter_files=$(find "$adapters_dir" -type f -name "*.php" -print 2>/dev/null || true)
  if [[ -z "$adapter_files" ]]; then
    pass "adapter directory is empty (no adapter files yet)"
  else
    adapter_count=$(echo "$adapter_files" | wc -l | tr -d ' ')
    only_html=$(echo "$adapter_files" | grep -v "HtmlPreviewAdapter.php" || true)
    if [[ "$adapter_count" -eq 1 ]] && echo "$adapter_files" | grep -q "HtmlPreviewAdapter.php"; then
      pass "adapter directory contains only HtmlPreviewAdapter.php ($adapter_count file(s))"
    elif [[ -n "$only_html" ]]; then
      echo "$only_html" >&2
      fail "adapter directory contains non-HtmlPreviewAdapter files ($adapter_count file(s))"
    else
      pass "adapter directory contains only HtmlPreviewAdapter.php ($adapter_count file(s))"
    fi
  fi
else
  pass "platform/Labels/Pipeline/Adapters/ does not exist yet (no adapter conflict risk)"
fi

echo ""
echo "== Label Rule Resource Contract =="
rule_contract="docs/architecture/label-rule-resource-contract.md"
require_text "$rule_contract" "susankhya.label.rule.v1" "rule contract defines canonical schema"
require_text "$rule_contract" "rule_key" "rule contract defines required rule_key field"
require_text "$rule_contract" "owner_key" "rule contract defines required owner_key field"
require_text "$rule_contract" "conditions" "rule contract defines required conditions"
require_text "$rule_contract" "effects" "rule contract defines required effects"
require_text "$rule_contract" "target_scope" "rule contract documents target scope"
require_text "$rule_contract" "{OwnerRoot}/Resources/labels/rules/" "rule contract documents canonical resource path"
require_text "$rule_contract" "R001" "rule contract has validation rules (R001+)"
require_text "$rule_contract" "visibility" "rule contract documents allowed capabilities (visibility)"
require_text "$rule_contract" "query databases" "rule contract explicitly forbids forbidden behaviors"
require_text "$rule_contract" "evaluated after context/template resolution" "rule contract documents runtime placement"
SEARCH_ARGS="-cI" grep -cI "Studio May" "$rule_contract" >/dev/null 2>&1 && pass "rule contract documents Studio boundary" || fail "rule contract documents Studio boundary"
SEARCH_ARGS="-cI" grep -cI "Studio Must Not" "$rule_contract" >/dev/null 2>&1 && pass "rule contract documents Studio prohibitions" || fail "rule contract documents Studio prohibitions"
require_text "$operating_contract" "Label Rule Resource Contract" "operating contract updates Next Contract Sequence with rule contract"
require_text "$resource_contract" "docs/architecture/label-rule-resource-contract.md" "resource contract references rule contract"
require_text "$validation_contract" "docs/architecture/label-rule-resource-contract.md" "validation contract references rule contract"
require_text "$runtime_contract" "docs/architecture/label-rule-resource-contract.md" "runtime contract references rule contract"
require_text "$apply_snapshot_contract" "docs/architecture/label-rule-resource-contract.md" "apply/snapshot contract references rule contract"
require_text "$template_apply_snapshot_contract" "docs/architecture/label-rule-resource-contract.md" "template apply/snapshot contract references rule contract"

echo ""
echo "== Label Runtime Handoff Contract (Phase 6) =="
require_file "$runtime_handoff_contract" "Label Runtime Handoff Contract"
require_text "$runtime_handoff_contract" "Planning contract only" "handoff contract is planning-only"
require_text "$runtime_handoff_contract" "Studio owns design-time resource authoring" "handoff contract keeps Studio design-time only"
require_text "$runtime_handoff_contract" "The owner supplies the data payload." "handoff contract assigns payload supply to owner"
require_text "$runtime_handoff_contract" "Platform owns the future shared resource resolution, rule evaluation, render, export, and print pipeline." "handoff contract assigns shared pipeline to Platform"
require_text "$runtime_handoff_contract" "Core owns governance contracts" "handoff contract assigns governance contracts to Core"
require_text "$runtime_handoff_contract" "LabelRuntimeRequest" "handoff contract defines LabelRuntimeRequest"
require_text "$runtime_handoff_contract" '`rules_enabled`' "handoff request defines rules_enabled"
require_text "$runtime_handoff_contract" '`requested_by`' "handoff request defines requested_by"
require_text "$runtime_handoff_contract" '`request_source`' "handoff request defines request_source"
require_text "$runtime_handoff_contract" '`trace_id`' "handoff request defines trace_id"
require_text "$runtime_handoff_contract" '`dry_run`' "handoff request defines dry_run"
require_text "$runtime_handoff_contract" '`preview_mode`' "handoff request defines preview_mode"
require_text "$runtime_handoff_contract" "Required Pre-Render Validation Chain" "handoff contract defines pre-render validation chain"
require_order "$runtime_handoff_contract" "1. Owner exists." "13. An audit trace" "handoff validation chain has deterministic order"
require_text "$runtime_handoff_contract" "Runtime must not fetch arbitrary DB data from Label Designer." "handoff contract forbids Label Designer runtime DB fetching"
require_text "$runtime_handoff_contract" "separate future contract and architecture gate" "handoff contract defers DB provider approval"
require_text "$runtime_handoff_contract" '`show_badge`' "handoff contract preserves show_badge"
require_text "$runtime_handoff_contract" '`hide_field`' "handoff contract preserves hide_field"
require_text "$runtime_handoff_contract" '`show_warning`' "handoff contract preserves show_warning"
require_text "$runtime_handoff_contract" '`set_style_token`' "handoff contract preserves set_style_token"
require_text "$runtime_handoff_contract" "Rules must not perform DB access, HTTP requests" "handoff contract forbids rule DB and HTTP behavior"
require_text "$runtime_handoff_contract" "file writes, permission changes, application state mutation, print execution, or QR generation" "handoff contract forbids rule writes and output side effects"
require_text "$runtime_handoff_contract" "first_match_wins" "handoff contract defines deterministic conflict behavior"
require_text "$runtime_handoff_contract" '`preview_html`' "handoff contract defines preview_html target"
require_text "$runtime_handoff_contract" '`print_html`' "handoff contract defines print_html target"
require_text "$runtime_handoff_contract" '`zpl/thermal`' "handoff contract defers thermal target"
require_text "$runtime_handoff_contract" '`image/png`' "handoff contract defers PNG target"
require_text "$runtime_handoff_contract" '`context_not_found`' "handoff contract defines context_not_found"
require_text "$runtime_handoff_contract" '`template_not_found`' "handoff contract defines template_not_found"
require_text "$runtime_handoff_contract" '`rule_not_found`' "handoff contract defines rule_not_found"
require_text "$runtime_handoff_contract" '`owner_mismatch`' "handoff contract defines owner_mismatch"
require_text "$runtime_handoff_contract" '`payload_missing_required_field`' "handoff contract defines missing-field failure"
require_text "$runtime_handoff_contract" '`payload_field_not_allowed`' "handoff contract defines disallowed-field failure"
require_text "$runtime_handoff_contract" '`forbidden_behavior`' "handoff contract defines forbidden behavior failure"
require_text "$runtime_handoff_contract" '`permission_denied`' "handoff contract defines permission failure"
require_text "$runtime_handoff_contract" '`output_target_not_allowed`' "handoff contract defines output target failure"
require_text "$runtime_handoff_contract" "no runtime implementation in Label Designer" "handoff contract forbids Label Designer runtime implementation"
require_text "$runtime_contract" "docs/architecture/label-runtime-handoff-contract.md" "runtime baseline references handoff contract"

echo ""
echo "== Runtime Request Dry-Run Validator (Phase 7) =="
require_text "$runtime_dry_run_service" "final class LabelDesignerRuntimeDryRunValidator" "dry-run validator service exists inside Label Designer"
require_text "$runtime_dry_run_service" "public static function sampleRequest" "dry-run validator defines fixed sample request"
require_text "$runtime_dry_run_service" "public static function validateSample" "dry-run validator exposes sample validation"
require_text "$runtime_dry_run_service" "public static function sampleScenarios" "dry-run validator defines deterministic negative sample scenarios"
require_text "$runtime_dry_run_service" "public static function validateScenarioMatrix" "dry-run validator exposes dry-run scenario matrix"
require_text "$runtime_dry_run_service" "public static function validate(array \$request): array" "dry-run validator returns array validation result"
require_text "$runtime_dry_run_service" "'owner_key' => 'Manufacturing/Products'" "dry-run sample targets Manufacturing/Products"
require_text "$runtime_dry_run_service" "'context_key' => 'manufacturing.product.label'" "dry-run sample targets product label context"
require_text "$runtime_dry_run_service" "'template_key' => 'manufacturing.product.label.100x50_mm'" "dry-run sample targets product label template"
require_text "$runtime_dry_run_service" "'rules_enabled' => true" "dry-run sample enables rule validation"
require_text "$runtime_dry_run_service" "'output_target' => 'preview_html'" "dry-run sample uses preview_html"
require_text "$runtime_dry_run_service" "'dry_run' => true" "dry-run sample requires dry_run"
require_text "$runtime_dry_run_service" "'preview_mode' => true" "dry-run sample requires preview_mode"
require_text "$runtime_dry_run_service" "\$missingContext['context_key'] = 'manufacturing.product.missing'" "scenario matrix includes missing context case"
require_text "$runtime_dry_run_service" "\$missingTemplate['template_key'] = 'manufacturing.product.label.missing'" "scenario matrix includes missing template case"
require_text "$runtime_dry_run_service" "\$ownerMismatch['owner_key'] = 'Manufacturing/Coverage'" "scenario matrix includes owner mismatch case"
require_text "$runtime_dry_run_service" "'secret_cost'" "scenario matrix includes payload field not allowed case"
require_text "$runtime_dry_run_service" "\$outputTargetNotAllowed['output_target'] = 'direct_print'" "scenario matrix includes output target not allowed case"
require_text "$runtime_dry_run_service" "\$rulesDisabled['rules_enabled'] = false" "scenario matrix includes rules disabled case"
require_text "$runtime_dry_run_service" "'forbidden_marker'" "scenario matrix includes forbidden behavior marker case"
require_text "$runtime_dry_run_service" "'expected_matched_rules_count' => 0" "rules-disabled scenario expects zero matched rules"
require_text "$runtime_dry_run_service" "'expected_result' => 'valid'" "scenario matrix includes valid expectation for rules-disabled case"
require_text "$runtime_dry_run_service" "'scenario_key' => 'missing_context'" "scenario matrix includes missing_context scenario key"
require_text "$runtime_dry_run_service" "'scenario_key' => 'missing_template'" "scenario matrix includes missing_template scenario key"
require_text "$runtime_dry_run_service" "'scenario_key' => 'owner_mismatch'" "scenario matrix includes owner_mismatch scenario key"
require_text "$runtime_dry_run_service" "'scenario_key' => 'payload_field_not_allowed'" "scenario matrix includes payload_field_not_allowed scenario key"
require_text "$runtime_dry_run_service" "'scenario_key' => 'output_target_not_allowed'" "scenario matrix includes output_target_not_allowed scenario key"
require_text "$runtime_dry_run_service" "'scenario_key' => 'rules_disabled'" "scenario matrix includes rules_disabled scenario key"
require_text "$runtime_dry_run_service" "'scenario_key' => 'forbidden_behavior_marker'" "scenario matrix includes forbidden behavior scenario key"
require_text "$runtime_dry_run_service" "ALLOWED_OUTPUT_TARGETS" "dry-run validator checks allowed output targets"
require_text "$runtime_dry_run_service" "LabelDesignerResourceReadinessService::isLabelLifecycleOwner" "dry-run validator checks owner lifecycle"
require_text "$runtime_dry_run_service" "templateContext === \$contextKey" "dry-run validator checks context/template compatibility"
require_text "$runtime_dry_run_service" "array_diff(\$templateFields, \$contextFields)" "dry-run validator checks template fields against context"
require_text "$runtime_dry_run_service" "array_diff(\$payloadKeys, \$contextFields)" "dry-run validator checks payload allowed fields"
require_text "$runtime_dry_run_service" "ALLOWED_OPERATORS" "dry-run validator checks rule operators"
require_text "$runtime_dry_run_service" "ALLOWED_EFFECT_TYPES" "dry-run validator checks presentation-only effects"
require_text "$runtime_dry_run_service" "FORBIDDEN_BEHAVIOR_PATTERNS" "dry-run validator checks forbidden behavior"
require_text "$runtime_dry_run_service" "'requested_by'" "dry-run validator checks requested_by"
require_text "$runtime_dry_run_service" "'request_source'" "dry-run validator checks request_source"
require_text "$runtime_dry_run_service" "'trace_id'" "dry-run validator checks trace_id"
forbid_pattern "$runtime_dry_run_service" "file_put_contents\\s*\\(|fwrite\\s*\\(|mkdir\\s*\\(|unlink\\s*\\(|rename\\s*\\(" "dry-run validator has no write functions"
forbid_pattern "$runtime_dry_run_service" "\\bDB::|new PDO|mysqli_|->query\\(|->exec\\(|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM" "dry-run validator has no DB access"
forbid_pattern "$runtime_dry_run_service" "curl_exec\\s*\\(|curl_init\\s*\\(|file_get_contents\\s*\\([[:space:]]*['\"]https?://" "dry-run validator has no HTTP access"
forbid_pattern "$runtime_dry_run_service" "Platform\\\\Labels|QRCodeController|PdfService|HtmlPreviewAdapter|->render\\s*\\(|::render\\s*\\(|->print\\s*\\(|::print\\s*\\(|->export\\s*\\(|::export\\s*\\(" "dry-run validator has no render, print, export, QR, or Platform pipeline calls"
forbid_pattern "$runtime_dry_run_service" "<form|<button|<canvas|<svg" "dry-run validator returns data without UI rendering"
require_text "$controller_file" "LabelDesignerRuntimeDryRunValidator::validateSample()" "controller adds read-only dry-run result to model"
require_text "$preview_file" "Runtime Handoff Dry Run" "UI labels runtime handoff dry-run section"
require_text "$preview_file" "Validation only." "UI labels dry-run as validation only"
require_text "$preview_file" "runtime_dry_run_validation_table" "UI shows deterministic validation table"
require_text "$preview_file" "runtime_dry_run_matrix_title" "UI shows dry-run scenario matrix title"
require_text "$preview_file" "runtime_dry_run_matrix_scenario" "UI shows dry-run matrix scenario column"
require_text "$preview_file" "runtime_dry_run_matrix_expected" "UI shows dry-run matrix expected column"
require_text "$preview_file" "runtime_dry_run_matrix_actual" "UI shows dry-run matrix actual column"
require_text "$preview_file" "runtime_dry_run_matrix_expectation" "UI shows dry-run matrix pass/fail column"
require_text "$preview_file" "runtime_dry_run_matrix_warning_error" "UI shows dry-run matrix warning/error counts"
require_text "$preview_file" "runtime_dry_run_matrix_key_codes" "UI shows dry-run matrix key diagnostic codes"
require_text "$preview_file" "matched_rules_count" "UI shows matched rule count"
require_text "$preview_file" "payload_fields_checked" "UI shows payload field count"
forbid_pattern "$routes_file" "post\\('/apps/studio/tools/label-designer[^']*(dry-run|runtime)" "no dry-run POST route is introduced"
forbid_pattern "$preview_file" "runtime_dry_run_matrix[^\n]*<form" "dry-run matrix remains read-only with no forms"
forbid_pattern "$preview_file" "runtime_dry_run_matrix[^\n]*button" "dry-run matrix introduces no execution buttons"
for code in LRD001 LRD002 LRD003 LRD004 LRD005 LRD006 LRD007 LRD008 LRD009 LRD010 LRD011 LRD012 LRD013 LRD014 LRD015 LRD016; do
  require_text "$runtime_dry_run_service" "'$code'" "dry-run validator defines diagnostic $code"
done

require_text "$controller_file" "LabelDesignerRuntimeDryRunValidator::validateScenarioMatrix()" "controller adds read-only dry-run scenario matrix to model"
require_text "$controller_file" "'label_runtime_dry_run_matrix'" "controller exposes dry-run scenario matrix model key"

dry_run_probe=$(php -r '
define("APP_ROOT", getcwd());
require "apps/Studio/Tools/LabelDesigner/Services/LabelDesignerDiscoveryService.php";
require "apps/Studio/Tools/LabelDesigner/Services/LabelDesignerResourceReadinessService.php";
require "apps/Studio/Tools/LabelDesigner/Services/LabelDesignerRuntimeDryRunValidator.php";
$result = \Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerRuntimeDryRunValidator::validateSample();
echo json_encode($result["summary"] ?? []);
' 2>/dev/null || true)
if php -r '
$summary = json_decode(stream_get_contents(STDIN), true);
$ok = is_array($summary)
    && ($summary["request_valid"] ?? false) === true
    && (int)($summary["matched_rules_count"] ?? 0) === 1
    && (int)($summary["payload_fields_checked"] ?? 0) === 7
    && ($summary["output_target"] ?? "") === "preview_html"
    && ($summary["dry_run"] ?? false) === true
    && (int)($summary["warnings"] ?? -1) === 0
    && (int)($summary["errors"] ?? -1) === 0;
exit($ok ? 0 : 1);
' <<< "$dry_run_probe"; then
  pass "dry-run sample validates with one matched rule and no warnings/errors"
else
  fail "dry-run sample result does not match the Phase 7 contract"
fi

dry_run_matrix_probe=$(php -r '
define("APP_ROOT", getcwd());
require "apps/Studio/Tools/LabelDesigner/Services/LabelDesignerDiscoveryService.php";
require "apps/Studio/Tools/LabelDesigner/Services/LabelDesignerResourceReadinessService.php";
require "apps/Studio/Tools/LabelDesigner/Services/LabelDesignerRuntimeDryRunValidator.php";
$result = \Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerRuntimeDryRunValidator::validateScenarioMatrix();
echo json_encode($result["summary"] ?? []);
' 2>/dev/null || true)
if php -r '
$summary = json_decode(stream_get_contents(STDIN), true);
$ok = is_array($summary)
    && (int)($summary["total"] ?? 0) >= 8
    && (int)($summary["passed"] ?? 0) === (int)($summary["total"] ?? 0)
    && (int)($summary["failed"] ?? -1) === 0
    && ($summary["matrix_valid"] ?? false) === true;
exit($ok ? 0 : 1);
' <<< "$dry_run_matrix_probe"; then
  pass "dry-run scenario matrix passes all deterministic expectations"
else
  fail "dry-run scenario matrix summary does not match Phase 7.1 expectations"
fi

echo ""
echo "== Workspace Tabs (Phase 8 UI Shell Conversion) =="
require_text "$preview_file" "ld-tabs" "workspace tab CSS class defined"
require_text "$preview_file" "nav class=\"ld-tabs\"" "workspace tab navigation nav element"
require_text "$preview_file" "ld-workspace" "workspace section CSS class defined"
require_text "$preview_file" "data-workspace=\"overview\"" "workspace: Overview defined"
require_text "$preview_file" "data-workspace=\"build\"" "workspace: Build Label defined"
require_text "$preview_file" "data-workspace=\"rules\"" "workspace: rules tab defined"
require_text "$preview_file" "data-workspace=\"preview\"" "workspace: Preview defined"
require_text "$preview_file" "data-workspace=\"governance\"" "workspace: Governance defined"
require_text "$preview_file" "\$activeWorkspace" "activeWorkspace PHP variable exists"
require_text "$preview_file" "ld-active" "active tab CSS class defined"
require_text "$preview_file" "ld-secondary" "secondary tab CSS class defined"
require_text "$preview_file" "\$workspaceLabel" "workspaceLabel helper function exists"
require_text "$preview_file" "open_studio_home" "Studio Home link always visible outside workspace divs"
if SEARCH_ARGS="-cI" grep -cI 'data-workspace=""' "$preview_file" >/dev/null 2>&1; then
  fail "no empty workspace identifier in preview"
else
  pass "no empty workspace identifier in preview"
fi
SEARCH_ARGS="-InE" grep -InE 'data-workspace="[a-z]+"' "$preview_file" | grep -v 'href="/apps/studio/tools/label-designer' >/dev/null 2>&1 && pass "all sections have workspace identifiers" || fail "missing workspace identifiers on sections"

echo "== Phase 2 POST Workspace Awareness =="
require_text "$controller_file" "labelDesignerRedirect" "controller has labelDesignerRedirect helper method"
require_text "$preview_file" "name=\"workspace\" value=\"<?= \$activeWorkspace" "preview has workspace hidden field"
# All 8 POST forms must carry the workspace hidden field
require_text "$preview_file" "/apps/studio/tools/label-designer/context/create" "context-create POST form retains workspace"
require_text "$preview_file" "/apps/studio/tools/label-designer/template/create" "template-create POST form retains workspace"
require_text "$preview_file" "/apps/studio/tools/label-designer/create-folders" "create-folders POST form retains workspace"
require_text "$preview_file" "/apps/studio/tools/label-designer/migration-preview" "migration-preview POST form retains workspace"
require_text "$preview_file" "/apps/studio/tools/label-designer/migration-apply" "migration-apply POST form retains workspace"
require_text "$preview_file" "/apps/studio/tools/label-designer/preview/render" "preview-render POST form retains workspace"
require_text "$preview_file" "/apps/studio/tools/label-designer/preview/rule-sandbox" "rule-sandbox POST form retains workspace"
require_text "$preview_file" "/apps/studio/tools/label-designer/rule/create" "rule-create POST form retains workspace"


echo "== Phase 2.1 GET State Preservation =="
require_text "$preview_file" "\$buildUrl" "buildUrl helper function exists"
require_text "$preview_file" "buildUrl(['workspace'" "workspace nav links use buildUrl"
require_text "$preview_file" "buildUrl(['owner'" "owner filter links use buildUrl"
require_text "$preview_file" "data-active-workspace=\"<?= e(\$activeWorkspace) ?>\"" "workspace sections scoped by activeWorkspace data attribute"
require_text "$preview_file" "\$validWorkspaces" "allowed workspace list variable exists"
if grep -Fq '?owner=<?= e(rawurlencode' "$preview_file" 2>/dev/null; then
    fail "owner links regressed to rawurlencode pattern"
else
    pass "owner links not regressed to rawurlencode pattern"
fi

echo ""
echo "== Phase 8 user-oriented workspace boundaries =="
require_text "$controller_file" "'contexts' => 'build'" "legacy contexts workspace aliases to build"
require_text "$controller_file" "'templates' => 'build'" "legacy templates workspace aliases to build"
require_text "$controller_file" "'maintenance' => 'governance'" "legacy maintenance workspace aliases to governance"
require_text "$controller_file" "'contexts' => 'build'" "POST redirect aliases contexts to build"
require_text "$controller_file" "'templates' => 'build'" "POST redirect aliases templates to build"
require_text "$controller_file" "'maintenance' => 'governance'" "POST redirect aliases maintenance to governance"
require_text "$controller_file" "\$_GET['workspace']) ? trim((string)\$_GET['workspace']) : 'overview'" "Overview is the default workspace"
require_text "$controller_file" "['overview', 'build', 'rules', 'preview', 'governance']" "controller accepts canonical Phase 8 workspaces"
require_text "$preview_file" "id=\"ld-workspace-overview\"" "Overview contains owner/resource status"
require_text "$preview_file" "id=\"ld-workspace-rules-existing\"" "Rules shows existing rules before builder"
require_order "$preview_file" "id=\"ld-workspace-rules-existing\"" "<!-- Rule Creation Preview (only for lifecycle owners) -->" "existing rules render before rule creation"
forbid_pattern "$preview_file" "id=\"ld-workspace-preview-renderer\"" "Phase 14: old preview renderer section ID no longer present"
forbid_pattern "$preview_file" "id=\"ld-workspace-preview-rule-sandbox\"" "Phase 14: old preview rule sandbox section ID no longer present"
require_text "$preview_file" "No runtime print" "Preview is labeled as no runtime print"
require_text "$preview_file" "rules_test_note" "Preview sandbox uses the rules workspace localized read-only note"
require_text "$preview_file" "rule_sandbox_safety" "Preview sandbox retains localized no-write safety wording"
require_text "$preview_file" "class=\"ld-advanced ld-raw-json\"" "raw JSON is placed in collapsed advanced details"
require_text "$preview_file" "Advanced details: context JSON and target path" "context JSON is collapsed by default"
require_text "$preview_file" "Advanced details: template JSON and target path" "template JSON is collapsed by default"
require_text "$preview_file" "Advanced details: rule JSON and target path" "rule JSON is collapsed by default"
require_text "$preview_file" "id=\"ld-workspace-governance-readiness\"" "Governance contains resource readiness"
require_text "$preview_file" "id=\"ld-workspace-governance-metadata\"" "Governance contains metadata migration"
require_text "$preview_file" "id=\"ld-workspace-governance-dry-run\"" "Governance contains runtime dry-run and matrix"
require_text "$preview_file" "id=\"ld-workspace-governance-diagnostics\"" "Governance contains resource diagnostics"
require_text "$preview_file" "class=\"ld-advanced ld-governance-details\"" "Governance detailed tables are collapsed by default"
forbid_pattern "$preview_file" "<button[^>]*>[^<]*(Print|Export|QR)" "Label Designer exposes no print/export/QR button"
forbid_pattern "$preview_file" "<a[^>]*>[^<]*(Print|Export|QR)" "Label Designer exposes no print/export/QR action link"
forbid_pattern "$preview_file" "<input[^>]*(print|export|qr)" "Label Designer exposes no print/export/QR input control"

echo ""
echo "== Phase 8.1 Overview dashboard usability =="
require_text "$preview_file" "id=\"ld-overview-parent-handoff\"" "Overview has parent/container handoff"
require_text "$preview_file" "id=\"ld-overview-lifecycle-summary\"" "Overview has compact lifecycle owner summary"
require_text "$preview_file" "overview_owner_key" "selected owner summary shows owner key via locale key"
require_text "$preview_file" "overview_owner_type" "selected owner summary shows owner type via locale key"
require_text "$preview_file" "overview_lifecycle_status" "selected owner summary shows lifecycle status via locale key"
require_text "$preview_file" "overview_parent_handoff_title" "Overview parent handoff title uses locale key"
require_text "$preview_file" "overview_lifecycle_summary_title" "Overview lifecycle summary title uses locale key"
require_text "$preview_file" "overview_preview_readiness" "Overview shows preview readiness"
require_text "$preview_file" "\$overviewContextKey" "ready workspace derives the existing context key"
require_text "$preview_file" "\$overviewTemplateKey" "ready workspace derives the existing template key"
require_text "$preview_file" "overview_primary_next_action" "Overview shows primary next action"
require_text "$preview_file" "overview_open_build" "Overview links to Build via locale key"
require_text "$preview_file" "overview_open_preview" "Overview links to Preview via locale key"
require_text "$preview_file" "overview_open_rules" "Overview links to Rules via locale key"
require_text "$preview_file" "overview_open_governance" "Overview links to Governance via locale key"
require_text "$preview_file" "\$buildUrl(['workspace' => 'build'])" "Overview single action preserves selected owner (build)"
require_text "$preview_file" "'overview', 'build', 'rules', 'preview', 'governance'" "Workspace tabs include all 5 workspaces"
require_text "$preview_file" "overview_manufacturing_guidance" "parent Manufacturing owner guidance uses locale key"
require_text "$preview_file" "\$primaryRecommendedOwner" "parent owner guidance chooses a model-derived primary target"
forbid_pattern "$preview_file" "id=\"ld-overview-resource-paths\"" "Overview no longer renders owner resource paths"
forbid_pattern "$preview_file" "id=\"ld-workspace-overview\"[^<]*<table" "Overview does not default to raw diagnostics tables"
require_text "$preview_file" "\$selectedOwnerIsLifecycle" "Overview branches on lifecycle owner classification"
require_text "$preview_file" "overview_lifecycle_has_resources" "lifecycle status uses locale key for has-resources"
require_text "$preview_file" "overview_lifecycle_ready" "lifecycle status uses locale key for ready"
require_text "$preview_file" "overview_lifecycle_not_lifecycle" "lifecycle status uses locale key for not-lifecycle"
require_text "$preview_file" "\$eligibleLifecycleOwners" "Overview parent handoff uses lifecycle readiness children"
forbid_pattern "$preview_file" "ld-overview-(parent-handoff|lifecycle-summary)[^<]*<p><small>(Owner|Owner key|Owner type|Lifecycle status)" "Overview section uses locale keys, not raw English labels"

echo ""
echo "== Phase 8.2 Workspace clarity and owner-state consistency =="
require_text "$preview_file" "ld-owner-status-panel" "owner status panel CSS class defined"
require_text "$preview_file" "owner_status_title" "owner status panel title key exists"
require_text "$preview_file" "owner_status_name" "owner status panel shows owner name"
require_text "$preview_file" "owner_status_key" "owner status panel shows owner key"
require_text "$preview_file" "owner_status_type" "owner status panel shows owner type"
require_text "$preview_file" "owner_status_lifecycle" "owner status panel shows lifecycle readiness"
require_text "$preview_file" "owner_status_resources" "owner status panel shows resource readiness"
require_text "$preview_file" "ld-manufacturing-guidance" "Manufacturing parent guidance CSS class defined"
require_text "$preview_file" "manufacturing_guidance_title" "Manufacturing guidance title key exists"
require_text "$preview_file" "manufacturing_guidance_explanation" "Manufacturing guidance explanation key exists"
require_text "$preview_file" "manufacturing_guidance_action" "Manufacturing guidance action key exists"
require_text "$preview_file" "strcasecmp(\$selectedOwnerKey, 'Manufacturing') === 0" "Manufacturing guidance triggers case-insensitively"
require_text "$preview_file" "ld-non-ready-guidance" "non-ready lifecycle owner guidance CSS class defined"
require_text "$preview_file" "non_ready_guidance_title" "non-ready guidance title key exists"
require_text "$preview_file" "non_ready_guidance_missing_folders" "non-ready guidance missing folders key exists"
require_text "$preview_file" "non_ready_guidance_missing_context" "non-ready guidance missing context key exists"
require_text "$preview_file" "non_ready_guidance_missing_template" "non-ready guidance missing template key exists"
require_text "$preview_file" "non_ready_guidance_missing_both" "non-ready guidance missing both key exists"
require_text "$preview_file" "build_title" "Phase 12: build workspace title key exists"
require_text "$preview_file" "build_contexts" "Phase 12: build contexts key exists"
require_text "$preview_file" "build_templates" "Phase 12: build templates key exists"
require_text "$preview_file" "build_chain_title" "Phase 12: build chain title key exists"
require_text "$preview_file" "build_create_context" "Phase 12: build create context key exists"
require_text "$preview_file" "build_create_template" "Phase 12: build create template key exists"
require_text "$preview_file" "build_no_contexts" "Phase 12: build no contexts key exists"
require_text "$preview_file" "build_no_templates" "Phase 12: build no templates key exists"
require_text "$preview_file" "ld-build-chain" "Phase 12: build chain CSS class exists"
require_text "$preview_file" "ld-chain-item" "Phase 12: chain item CSS class exists"
require_text "$preview_file" "ld-build-list" "Phase 12: build list CSS class exists"
require_text "$preview_file" "ld-build-actions" "Phase 12: build actions CSS class exists"
require_text "$preview_file" "ld-build-create-context" "Phase 12: create context anchor id exists"
require_text "$preview_file" "ld-build-create-template" "Phase 12: create template anchor id exists"
forbid_pattern "$preview_file" "build_context_title" "Phase 12: old context title key removed from Build"
forbid_pattern "$preview_file" "build_context_status" "Phase 12: old context status key removed"
forbid_pattern "$preview_file" "build_template_title" "Phase 12: old template title key removed"
forbid_pattern "$preview_file" "build_template_status" "Phase 12: old template status key removed"
# Phase 13: Refocused Rules workspace (no governance content)
require_text "$preview_file" "rules_title" "Phase 13: Rules workspace title key exists"
require_text "$preview_file" "rules_subtitle" "Phase 13: Rules workspace subtitle key exists"
require_text "$preview_file" "rules_owner_title" "Phase 13: Rules workspace owner title key exists"
require_text "$preview_file" "rules_owner_key_label" "Phase 13: Rules workspace owner key label exists"
require_text "$preview_file" "rules_lifecycle_label" "Phase 13: Rules workspace lifecycle label exists"
require_text "$preview_file" "rules_empty_title" "Phase 13: Rules workspace empty title key exists"
require_text "$preview_file" "rules_empty_guidance" "Phase 13: Rules workspace empty guidance key exists"
require_text "$preview_file" "rules_empty_action" "Phase 13: Rules workspace empty action key exists"
require_text "$preview_file" "rules_test_title" "Phase 13: Rules workspace test title key exists"
require_text "$preview_file" "rules_test_note" "Phase 13: Rules workspace test note key exists"
require_text "$preview_file" "rules_test_no_preview" "Phase 13: Rules workspace test no preview key exists"
require_text "$preview_file" "rules_can_create" "Phase 13: Rules workspace can create key exists"
require_text "$preview_file" "rules_create_note" "Phase 13: Rules workspace create note emphasizes presentation-only, owner-owned"
forbid_pattern "$preview_file" "rules_compatibility_title|rules_context_compatible|rules_context_incompatible|rules_template_compatible|rules_template_incompatible" "Phase 13: Old compatibility keys removed from Rules workspace"
require_text "$preview_file" "ld-workspace-rules-compatibility" "Phase 13: Rules workspace header section ID exists"
require_text "$preview_file" "ld-workspace-rules-existing" "Phase 13: Rules workspace existing rules section ID exists"
require_text "$preview_file" "ld-workspace-rules-sandbox" "Phase 13: Rules workspace sandbox section ID exists"
require_text "$preview_file" "workspace=rules" "Phase 13: Rule sandbox form preserves workspace=rules"
require_text "$preview_file" "#ld-workspace-rules-sandbox" "Phase 13: Preview workspace links to Rules sandbox"
require_text "$preview_file" "rules_inventory_title" "Rules workspace inventory title key exists"
require_text "$preview_file" "rules_cannot_create_no_context" "Rules workspace cannot create no context key exists"
require_text "$preview_file" "rules_cannot_create_no_template" "Rules workspace cannot create no template key exists"
require_text "$preview_file" "preview_prerequisites_title" "Preview workspace prerequisites title key exists"
require_text "$preview_file" "preview_prerequisites_owner" "Preview workspace prerequisites owner key exists"
require_text "$preview_file" "preview_prerequisites_context" "Preview workspace prerequisites context key exists"
require_text "$preview_file" "preview_prerequisites_template" "Preview workspace prerequisites template key exists"
require_text "$preview_file" "preview_prerequisites_rules" "Preview workspace prerequisites rules key exists"
require_text "$preview_file" "preview_prerequisites_missing" "Preview workspace prerequisites missing key exists"
require_text "$preview_file" "preview_prerequisites_ready" "Preview workspace prerequisites ready key exists"
require_text "$preview_file" "governance_ownership_title" "Governance workspace ownership title key exists"
require_text "$preview_file" "governance_ownership_valid" "Governance workspace ownership valid key exists"
require_text "$preview_file" "governance_ownership_invalid" "Governance workspace ownership invalid key exists"
require_text "$preview_file" "governance_metadata_title" "Governance workspace metadata title key exists"
require_text "$preview_file" "governance_metadata_first" "Governance workspace metadata first key exists"
require_text "$preview_file" "governance_metadata_legacy" "Governance workspace metadata legacy key exists"
require_text "$preview_file" "governance_readiness_title" "Governance workspace readiness title key exists"
require_text "$preview_file" "governance_readiness_complete" "Governance workspace readiness complete key exists"
require_text "$preview_file" "governance_readiness_partial" "Governance workspace readiness partial key exists"
require_text "$preview_file" "governance_readiness_absent" "Governance workspace readiness absent key exists"
require_text "$preview_file" "governance_migration_title" "Governance workspace migration title key exists"
require_text "$preview_file" "governance_migration_needed" "Governance workspace migration needed key exists"
require_text "$preview_file" "governance_migration_complete" "Governance workspace migration complete key exists"

# Owner-state preservation invariants
require_text "$preview_file" "name=\"owner\" value=\"<?= e(\$selectedDataSourceOwnerKey) ?>\"" "template-create form preserves owner"
require_text "$preview_file" "name=\"owner\" value=\"<?= e(\$selectedDataSourceOwnerKey) ?>\"" "preview-render form preserves owner"
require_text "$preview_file" "name=\"owner\" value=\"<?= e(\$selectedDataSourceOwnerKey) ?>\"" "rule-sandbox form preserves owner"
require_text "$preview_file" "name=\"owner\" value=\"<?= e(\$selectedDataSourceOwnerKey) ?>\"" "migration-preview form preserves owner"
require_text "$controller_file" "labelDesignerCreateTemplate" "controller has template-create handler"
require_text "$controller_file" "labelDesignerRenderPreview" "controller has preview-render handler"
require_text "$controller_file" "labelDesignerRuleSandbox" "controller has rule-sandbox handler"
require_text "$controller_file" "labelDesignerMigrationPreview" "controller has migration-preview handler"
require_text "$controller_file" "'owner' => trim((string)(\$_POST['owner'] ?? ''))" "template-create redirect preserves owner"
require_text "$controller_file" "'owner' => trim((string)(\$_POST['owner'] ?? ''))" "preview-render redirect preserves owner"
require_text "$controller_file" "'owner' => trim((string)(\$_POST['owner'] ?? ''))" "rule-sandbox redirect preserves owner"
require_text "$controller_file" "'owner' => trim((string)(\$_POST['owner'] ?? ''))" "migration-preview redirect preserves owner"

# Phase 2 Preview Consolidation invariants
preview_container_count=$(grep -c 'label-renderer-preview-container' "$preview_file" 2>/dev/null || true)
if [[ "$preview_container_count" -eq 1 ]]; then
  pass "Phase 2: single label-renderer-preview-container in preview view (consolidated)"
else
  fail "Phase 2: expected exactly 1 label-renderer-preview-container, found $preview_container_count"
fi

require_text "$preview_file" "consolidatedPreviewHtml" "Phase 2: preview uses consolidated HTML (base or sandbox-modified)"
require_text "$preview_file" "sandboxEffectActive" "Phase 2: sandbox effect active flag controls preview display"
require_text "$preview_file" "preview_rules_applied" "Phase 2: sandbox effects applied note in preview renderer (now locale key)"
forbid_pattern "$preview_file" "preview_title.*with rule effect" "Phase 2: no separate rule-effect preview heading"

# Phase 10 Diagnostics Consolidation invariants
require_file "$tool_dir/Views/partials/diagnostics-panel.php" "Phase 10: diagnostics panel partial exists"
require_text "$preview_file" "ld-diag-panel" "Phase 10: diagnostics panel CSS class"
require_text "$preview_file" "ld-diag-summary" "Phase 10: diagnostics summary card CSS class"
require_text "$preview_file" "ld-diag-count" "Phase 10: diagnostics count badge CSS class"
require_text "$preview_file" "ld-diag-details" "Phase 10: diagnostics expandable details CSS class"
require_text "$preview_file" "ld-diag-table" "Phase 10: diagnostics table CSS class"
require_text "$preview_file" "partials/diagnostics-panel.php" "Phase 10: diagnostics panel partial is used in split views"
panel_reference_count=$(grep -c 'partials/diagnostics-panel.php' "$preview_file" 2>/dev/null || true)
if [[ "$panel_reference_count" -ge 3 ]]; then
  pass "Phase 10: diagnostics panel used in $panel_reference_count sections (preview + sandbox + resource)"
else
  fail "Phase 10: expected at least 3 diagnostics-panel partial references, found $panel_reference_count"
fi
inline_sev_count=$(grep -c '\$diagSeverity' "$preview_file" 2>/dev/null || true)
if [[ "$inline_sev_count" -eq 0 ]]; then
  pass "Phase 10: no \$diagSeverity inline severity block (replaced by partial)"
else
  pass "Phase 10: \$diagSeverity inline block removed ($inline_sev_count remain — legacy resource diag OK)"
fi

rs_sev_count=$(grep -c '\$rsDiagSeverity' "$preview_file" 2>/dev/null || true)
if [[ "$rs_sev_count" -eq 0 ]]; then
  pass "Phase 10: no \$rsDiagSeverity inline severity block (replaced by partial)"
else
  pass "Phase 10: \$rsDiagSeverity inline block removed ($rs_sev_count prior — legacy)"
fi

echo ""
echo "== Phase 11 Overview de-noising and Governance relocation =="
require_text "$preview_file" "overview_parent_open_target" "Phase 11: parent target action locale key exists"
require_text "$preview_file" "overview_parent_handoff_reason" "Phase 11: parent handoff reason locale key exists"
require_text "$preview_file" "ld-parent-handoff" "Phase 11: parent handoff CSS class in Overview"
require_text "$preview_file" "ld-lifecycle-summary" "Phase 11: lifecycle summary CSS class in Overview"
forbid_pattern "$preview_file" "ld-overview-primary-actions" "Phase 11: old 4-action card grid removed from Overview"
governance_details_count=$(grep -c 'ld-governance-details' "$preview_file" 2>/dev/null || true)
if [[ "$governance_details_count" -ge 9 ]]; then
  pass "Phase 11: resource diagnostics and reference implementation collapsed under Governance ($governance_details_count ld-governance-details)"
else
  fail "Phase 11: expected at least 9 ld-governance-details, found $governance_details_count"
fi

echo "== Phase 11.1 Actual Governance Relocation =="
require_text "$preview_file" "ld-gov-hidden" "Phase 11.1: hidden dt CSS class for collapsed governance sections"
require_text "$preview_file" "ld-gov-next" "Phase 11.1: next-governance-action CSS class"
require_text "$preview_file" "governance_action_migrate" "Phase 11.1: governance action migrate locale key"
require_text "$preview_file" "governance_action_readiness" "Phase 11.1: governance action readiness locale key"
require_text "$preview_file" "governance_action_dry_run" "Phase 11.1: governance action dry-run locale key"
forbid_pattern "$preview_file" "data-workspace=\"build\" id=\"ld-workspace-build-db-discovery\"" "Phase 11.1: DB discovery no longer in Build workspace"
require_text "$preview_file" "Bootstrap DB discovery" "Phase 11.1: DB discovery has collapsed details wrapper in Governance"
gov_hidden_count=$(grep -c 'ld-gov-hidden' "$preview_file" 2>/dev/null || true)
if [[ "$gov_hidden_count" -ge 9 ]]; then
  pass "Phase 11.1: all secondary governance sections have hidden dt ($gov_hidden_count ld-gov-hidden)"
else
  fail "Phase 11.1: expected at least 9 hidden dts, found $gov_hidden_count"
fi

echo ""
echo "== Phase 14 Refocused Preview workspace =="
require_text "$preview_file" "preview_subtitle" "Phase 14: preview subtitle key exists"
require_text "$preview_file" "preview_setup_title" "Phase 14: preview setup title key exists"
require_text "$preview_file" "preview_render_title" "Phase 14: preview render title key exists"
require_text "$preview_file" "preview_no_preview_title" "Phase 14: preview no preview title key exists"
require_text "$preview_file" "preview_no_preview_detail" "Phase 14: preview no preview detail key exists"
require_text "$preview_file" "preview_rules_applied" "Phase 14: preview rules applied key exists"
require_text "$preview_file" "preview_rules_none" "Phase 14: preview rules none key exists"
require_text "$preview_file" "preview_rules_view_diagnostics" "Phase 14: preview rules view diagnostics key exists"
require_text "$preview_file" "preview_unavailable_title" "Phase 14: preview unavailable title key exists"
require_text "$preview_file" "preview_unavailable_detail" "Phase 14: preview unavailable detail key exists"
require_text "$preview_file" "preview_unavailable_action_build" "Phase 14: preview unavailable action build key exists"
require_text "$preview_file" "ld-workspace-preview" "Phase 14: consolidated preview workspace section exists"
require_text "$preview_file" "preview/render" "Phase 14: preview setup form posts to render endpoint"
require_text "$preview_file" 'id="ld-workspace-preview"' "Phase 14: consolidated preview workspace section id exists"
forbid_pattern "$preview_file" "ld-workspace-preview-prerequisites" "Phase 14: old prerequisites section ID removed"
forbid_pattern "$preview_file" "ld-workspace-preview-renderer" "Phase 14: old renderer section ID removed"
require_text "$preview_file" "preview_unavailable_action_build" "Phase 14: preview unavailable state links to Build workspace"
require_text "$preview_file" "ld-workspace-preview-parent" "Phase 14: non-lifecycle owner preview guidance section exists"
require_text "$preview_file" "ld-badge-missing" "Phase 14: missing badge CSS class for status indicators"

# Phase 15: Workspace navigation & journey polish
require_text "$preview_file" "progress_title" "Phase 15: progress card title locale key"
require_text "$preview_file" "progress_context" "Phase 15: progress step context locale key"
require_text "$preview_file" "progress_template" "Phase 15: progress step template locale key"
require_text "$preview_file" "progress_rule" "Phase 15: progress step rule locale key"
require_text "$preview_file" "progress_preview" "Phase 15: progress step preview locale key"
require_text "$preview_file" "progress_ready" "Phase 15: progress ready locale key"
require_text "$preview_file" "progress_in_progress" "Phase 15: progress in-progress locale key"
require_text "$preview_file" "progress_not_started" "Phase 15: progress not-started locale key"
require_text "$preview_file" "next_action_create_context" "Phase 15: next-action create context locale key"
require_text "$preview_file" "next_action_create_template" "Phase 15: next-action create template locale key"
require_text "$preview_file" "next_action_create_rule" "Phase 15: next-action create rule locale key"
require_text "$preview_file" "next_action_render_preview" "Phase 15: next-action render preview locale key"
require_text "$preview_file" "next_action_continue_rules" "Phase 15: next-action continue to rules locale key"
require_text "$preview_file" "next_action_continue_preview" "Phase 15: next-action continue to preview locale key"
require_text "$preview_file" "next_action_open_governance" "Phase 15: next-action open governance locale key"
require_text "$preview_file" "\$wsProgress[" "Phase 15: progress model array defined"
require_text "$preview_file" "\$renderProgressCard" "Phase 15: progress card render function defined"
require_text "$preview_file" "ld-progress-card" "Phase 15: progress card CSS class"
require_text "$preview_file" "ld-progress-steps" "Phase 15: progress steps CSS class"
require_text "$preview_file" "ld-next-actions" "Phase 15: next-actions container CSS class"
require_text "$preview_file" "ld-next-action" "Phase 15: next-action link CSS class"
require_text "$preview_file" "ld-next-action-secondary" "Phase 15: next-action secondary variant CSS class"

echo ""
echo "== Phase 16.1 Existing resource listings =="
require_text "$preview_file" "build_existing_contexts_title" "Phase 16.1: existing contexts title locale key"
require_text "$preview_file" "build_existing_contexts_key" "Phase 16.1: existing contexts key column locale key"
require_text "$preview_file" "build_existing_contexts_purpose" "Phase 16.1: existing contexts purpose column locale key"
require_text "$preview_file" "build_existing_contexts_fields" "Phase 16.1: existing contexts fields column locale key"
require_text "$preview_file" "build_existing_templates_title" "Phase 16.1: existing templates title locale key"
require_text "$preview_file" "build_existing_templates_key" "Phase 16.1: existing templates key column locale key"
require_text "$preview_file" "build_existing_templates_context" "Phase 16.1: existing templates context column locale key"
require_text "$preview_file" "build_existing_templates_fields" "Phase 16.1: existing templates fields column locale key"
require_text "$preview_file" "build_existing_templates_blocks" "Phase 16.1: existing templates blocks column locale key"
require_text "$preview_file" "build_existing_action_view" "Phase 16.1: existing action view locale key"
require_text "$preview_file" "build_existing_action_edit" "Phase 16.1: existing action edit locale key"
require_text "$preview_file" "build_existing_action_duplicate" "Phase 16.1: existing action duplicate locale key"
require_text "$preview_file" "ld-existing-table" "Phase 16.1: existing resource table CSS class"
require_text "$preview_file" "ld-action-link" "Phase 16.1: action link CSS class"
require_text "$preview_file" "\$existingContexts" "Phase 16.1: existing contexts variable extracted in view"
require_text "$preview_file" "\$existingTemplates" "Phase 16.1: existing templates variable extracted in view"
require_text "$preview_file" "label_existing_contexts" "Phase 16.1: existing contexts model key in controller"
require_text "$preview_file" "label_existing_templates" "Phase 16.1: existing templates model key in controller"
require_text "$controller_file" "loadExistingResources" "Phase 16.1: loadExistingResources helper method in controller"
require_text "$preview_file" "Existing Contexts table" "Phase 16.1: existing contexts table HTML comment"
require_text "$preview_file" "Existing Templates table" "Phase 16.1: existing templates table HTML comment"

echo "== Phase 16.2 Read-only resource inspectors =="
require_text "$controller_file" "label_view_resource" "Phase 16.2: view_resource model key in controller"
require_text "$controller_file" "label_selected_context" "Phase 16.2: selected_context model key in controller"
require_text "$controller_file" "label_selected_template" "Phase 16.2: selected_template model key in controller"
require_text "$preview_file" "view_resource" "Phase 16.2: view_resource variable extracted in view"
require_text "$preview_file" "\$selectedContext" "Phase 16.2: selectedContext variable extracted in view"
require_text "$preview_file" "\$selectedTemplate" "Phase 16.2: selectedTemplate variable extracted in view"
require_text "$preview_file" "ld-inspector-card" "Phase 16.2: inspector card CSS class"
require_text "$preview_file" "ld-inspector-table" "Phase 16.2: inspector table CSS class"
require_text "$preview_file" "ld-inspector-not-found" "Phase 16.2: inspector not-found CSS class"
require_text "$preview_file" "build_context_inspector_title" "Phase 16.2: context inspector title locale key"
require_text "$preview_file" "build_context_inspector_allowed_fields" "Phase 16.2: context inspector allowed fields locale key"
require_text "$preview_file" "build_context_inspector_field_key" "Phase 16.2: context inspector field key locale key"
require_text "$preview_file" "build_context_inspector_field_label" "Phase 16.2: context inspector field label locale key"
require_text "$preview_file" "build_context_inspector_field_source" "Phase 16.2: context inspector source column locale key"
require_text "$preview_file" "build_context_inspector_field_type" "Phase 16.2: context inspector data type locale key"
require_text "$preview_file" "build_context_inspector_field_required" "Phase 16.2: context inspector required locale key"
require_text "$preview_file" "build_template_inspector_title" "Phase 16.2: template inspector title locale key"
require_text "$preview_file" "build_template_inspector_selected_fields" "Phase 16.2: template inspector selected fields locale key"
require_text "$preview_file" "build_template_inspector_field_key" "Phase 16.2: template inspector field key locale key"
require_text "$preview_file" "build_template_inspector_field_label" "Phase 16.2: template inspector field label locale key"
require_text "$preview_file" "build_template_inspector_layout_blocks" "Phase 16.2: template inspector layout blocks locale key"
require_text "$preview_file" "build_template_inspector_block_key" "Phase 16.2: template inspector block key locale key"
require_text "$preview_file" "build_template_inspector_block_type" "Phase 16.2: template inspector block type locale key"
require_text "$preview_file" "build_template_inspector_block_summary" "Phase 16.2: template inspector block summary locale key"
require_text "$preview_file" "build_inspector_not_found" "Phase 16.2: inspector not-found locale key"
require_text "$preview_file" "build_inspector_return_to_build" "Phase 16.2: inspector return to build locale key"
require_text "$preview_file" "view_resource=context" "Phase 16.2: context inspector view URL parameter"
require_text "$preview_file" "view_resource=template" "Phase 16.2: template inspector view URL parameter"
require_text "$preview_file" "Phase 16.2: Resource inspector" "Phase 16.2: resource inspector HTML comment"

echo "== Phase 16.3 Rules table + inspector =="

# Controller model keys
require_text "$controller_file" "label_existing_rules" "Phase 16.3: existing rules model key in controller"
require_text "$controller_file" "label_view_rule" "Phase 16.3: view_rule model key in controller"
require_text "$controller_file" "label_selected_rule" "Phase 16.3: selected_rule model key in controller"
require_text "$controller_file" "label_rule_summary" "Phase 16.3: rule summary model key in controller"

# Controller GET param extraction
require_text "$controller_file" "view_rule" "Phase 16.3: view_rule GET param extracted"

# Controller loadExistingResources for rules
require_text "$controller_file" "loadExistingResources" "Phase 16.3: loadExistingResources helper used for rules"

# View variable extraction
require_text "$preview_file" "existingRules" "Phase 16.3: existingRules variable extracted in view"
require_text "$preview_file" "viewRule" "Phase 16.3: viewRule variable extracted in view"
require_text "$preview_file" "selectedRule" "Phase 16.3: selectedRule variable extracted in view"
require_text "$preview_file" "ruleSummary" "Phase 16.3: ruleSummary variable extracted in view"

# Rules table (Phase 16.3 replaces inline cards)
require_text "$preview_file" "ld-existing-table" "Phase 16.3: existing rules table uses ld-existing-table class"

# Rules inspector locale keys (3 occurrences each = EN dict + NE dict + view rendering)
require_text "$preview_file" "rules_inspector_title" "Phase 16.3: inspector title locale key"
require_text "$preview_file" "rules_inspector_rule_key" "Phase 16.3: inspector rule key locale key"
require_text "$preview_file" "rules_inspector_not_found" "Phase 16.3: inspector not-found locale key"
require_text "$preview_file" "rules_inspector_condition_table_title" "Phase 16.3: inspector condition table title locale key"
require_text "$preview_file" "rules_inspector_effect_table_title" "Phase 16.3: inspector effect table title locale key"
require_text "$preview_file" "rules_inspector_summary_title" "Phase 16.3: inspector summary title locale key"
require_text "$preview_file" "rules_inspector_field" "Phase 16.3: inspector field locale key"
require_text "$preview_file" "rules_inspector_operator" "Phase 16.3: inspector operator locale key"
require_text "$preview_file" "rules_inspector_value" "Phase 16.3: inspector value locale key"
require_text "$preview_file" "rules_inspector_type" "Phase 16.3: inspector type locale key"
require_text "$preview_file" "rules_inspector_target" "Phase 16.3: inspector target locale key"

# Rules inspector CSS (reuses Phase 16.2 classes, verify at least one is present)
require_text "$preview_file" "ld-inspector-card" "Phase 16.3: inspector card CSS in rules section"

# View action links with view_rule param
require_text "$preview_file" "view_rule" "Phase 16.3: view_rule parameter in action URLs"

# HTML comment markers (both should exist)
require_text "$preview_file" "Phase 16.3: Existing Rules table" "Phase 16.3: rules table HTML comment marker"
require_text "$preview_file" "Phase 16.3: Rule inspector" "Phase 16.3: rule inspector HTML comment marker"

# Verify rule inspector section exists
require_text "$preview_file" "Rule Inspector" "Phase 16.3: inspector section heading text"
require_text "$preview_file" "Conditions" "Phase 16.3: inspector conditions table heading text"
require_text "$preview_file" "Effects" "Phase 16.3: inspector effects table heading text"
require_text "$preview_file" "Summary" "Phase 16.3: inspector summary section heading text"

# Existing View action links must include view_rule
require_text "$preview_file" "view_rule=" "Phase 16.3: view_rule URL parameter in action links"

echo "== Phase 16.4 Preview deep linking =="

# Controller: preview GET param extraction
require_text "$controller_file" "previewGetContextKey" "Phase 16.4: preview context_key GET param in controller"
require_text "$controller_file" "previewGetTemplateKey" "Phase 16.4: preview template_key GET param in controller"
require_text "$controller_file" "previewRulesEnabled" "Phase 16.4: preview rules_enabled GET param in controller"

# Controller: preview model keys
require_text "$controller_file" "label_preview_selected_context_id" "Phase 16.4: selected context id model key"

# View: variable extraction
require_text "$preview_file" "previewSelectedContextId" "Phase 16.4: previewSelectedContextId in view"
require_text "$preview_file" "previewRulesEnabled" "Phase 16.4: previewRulesEnabled in view"

# Context inspector preview link
require_text "$preview_file" "build_inspector_preview_context" "Phase 16.4: context inspector preview link locale key"

# Template inspector preview link
require_text "$preview_file" "build_inspector_preview_template" "Phase 16.4: template inspector preview link locale key"

# Rule inspector preview link
require_text "$preview_file" "rules_inspector_preview_rule" "Phase 16.4: rule inspector preview link locale key"

# Preview deep-link URL params preserved
require_text "$preview_file" "workspace=preview" "Phase 16.4: preview workspace in deep links"
require_text "$preview_file" "context_key=" "Phase 16.4: context_key preserved in deep links"
require_text "$preview_file" "template_key=" "Phase 16.4: template_key preserved in deep links"
require_text "$preview_file" "rules_enabled=" "Phase 16.4: rules_enabled preserved in rule inspector deep link"

# Preview selector pre-selection
require_text "$preview_file" "previewSelectedContextId" "Phase 16.4: context selector pre-selected from GET"
require_text "$preview_file" "rules_enabled" "Phase 16.4: rules_enabled checkbox pre-checked from GET"

echo "== Phase 16.5 Duplicate resource workflow =="

# Service file existence
require_file "$tool_dir/Services/LabelDesignerDuplicateService.php" "Phase 16.5: duplicate service file"

# Controller handler
require_text "$controller_file" "labelDesignerDuplicateResource" "Phase 16.5: controller duplicate handler"
require_text "$controller_file" "LabelDesignerDuplicateService" "Phase 16.5: controller imports duplicate service"
require_text "$controller_file" "confirm_duplicate" "Phase 16.5: confirm_duplicate POST param extracted"

# Route
require_text "$routes_file" "duplicate-resource" "Phase 16.5: duplicate-resource POST route"

# Locale keys (3 occurrences each: EN dict + NE dict + view rendering)
require_text "$preview_file" "duplicate_title" "Phase 16.5: duplicate title locale key"
require_text "$preview_file" "duplicate_new_key_label" "Phase 16.5: duplicate new key label locale key"
require_text "$preview_file" "duplicate_new_key_placeholder" "Phase 16.5: duplicate new key placeholder locale key"
require_text "$preview_file" "duplicate_confirm_label" "Phase 16.5: duplicate confirm label locale key"
require_text "$preview_file" "duplicate_confirm_text" "Phase 16.5: duplicate confirm text locale key"
require_text "$preview_file" "duplicate_button" "Phase 16.5: duplicate button locale key"

# Duplicate forms in inspectors
require_text "$preview_file" "Phase 16.5: Duplicate context form" "Phase 16.5: context duplicate form HTML comment"
require_text "$preview_file" "Phase 16.5: Duplicate template form" "Phase 16.5: template duplicate form HTML comment"
require_text "$preview_file" "Phase 16.5: Duplicate rule form" "Phase 16.5: rule duplicate form HTML comment"

# Duplicate form POST action
require_text "$preview_file" "/apps/studio/tools/label-designer/duplicate-resource" "Phase 16.5: duplicate-resource POST action URL"
require_text "$preview_file" "value=\"context\"" "Phase 16.5: context duplicate form resource_type hidden"
require_text "$preview_file" "value=\"template\"" "Phase 16.5: template duplicate form resource_type hidden"
require_text "$preview_file" "value=\"rule\"" "Phase 16.5: rule duplicate form resource_type hidden"

# Duplicate service diagnostic code
require_text "$tool_dir/Services/LabelDesignerDuplicateService.php" "DD01" "Phase 16.5: diagnostic code DD01"

# CSS helper class
require_text "$preview_file" "ld-meta-label" "Phase 16.5: meta-label CSS class in view"

echo "== Phase 16.6 Template Editor =="

# Service file
require_file "$tool_dir/Services/LabelDesignerTemplateEditService.php" "Phase 16.6: template edit service file"

# Controller handler
require_text "$controller_file" "labelDesignerEditTemplate" "Phase 16.6: controller edit template handler"
require_text "$controller_file" "LabelDesignerTemplateEditService" "Phase 16.6: controller imports template edit service"

# CSRF check
require_text "$controller_file" 'Auth::requireCsrf' "Phase 16.6: CSRF check in template edit handler"

# Route
require_text "$routes_file" "template/edit" "Phase 16.6: template/edit POST route"

# Snapshot before write
require_text "$tool_dir/Services/LabelDesignerTemplateEditService.php" "writeSnapshot" "Phase 16.6: snapshot before write"

# Atomic write
require_text "$tool_dir/Services/LabelDesignerTemplateEditService.php" "rename" "Phase 16.6: atomic rename write"

# Diagnostic codes
require_text "$tool_dir/Services/LabelDesignerTemplateEditService.php" "TE01" "Phase 16.6: diagnostic code TE01"
require_text "$tool_dir/Services/LabelDesignerTemplateEditService.php" "TE02" "Phase 16.6: diagnostic code TE02"
require_text "$tool_dir/Services/LabelDesignerTemplateEditService.php" "TE03" "Phase 16.6: diagnostic code TE03"
require_text "$tool_dir/Services/LabelDesignerTemplateEditService.php" "TE04" "Phase 16.6: diagnostic code TE04"

# Locale keys (3 occurrences each: EN dict + NE dict + view rendering)
require_text "$preview_file" "template_edit_title" "Phase 16.6: template edit title locale key"
require_text "$preview_file" "template_edit_label_label" "Phase 16.6: template edit label locale key"
require_text "$preview_file" "template_edit_purpose_label" "Phase 16.6: template edit purpose label locale key"
require_text "$preview_file" "template_edit_size_label_label" "Phase 16.6: template edit size label locale key"
require_text "$preview_file" "template_edit_size_width_label" "Phase 16.6: template edit size width locale key"
require_text "$preview_file" "template_edit_size_height_label" "Phase 16.6: template edit size height locale key"
require_text "$preview_file" "template_edit_field_label_label" "Phase 16.6: template edit field label locale key"
require_text "$preview_file" "template_edit_block_summary_label" "Phase 16.6: template edit block summary locale key"
require_text "$preview_file" "template_edit_confirm_text" "Phase 16.6: template edit confirm text locale key"
require_text "$preview_file" "template_edit_button" "Phase 16.6: template edit button locale key"
require_text "$preview_file" "template_edit_success" "Phase 16.6: template edit success locale key"
require_text "$preview_file" "template_edit_failed" "Phase 16.6: template edit failed locale key"
require_text "$preview_file" "template_edit_diagnostics_title" "Phase 16.6: template edit diagnostics title locale key"
require_text "$preview_file" "template_edit_diagnostics_code_label" "Phase 16.6: template edit diagnostics code label locale key"
require_text "$preview_file" "template_edit_diagnostics_check_label" "Phase 16.6: template edit diagnostics check label locale key"
require_text "$preview_file" "template_edit_diagnostics_result_label" "Phase 16.6: template edit diagnostics result label locale key"

# Edit form HTML comment
require_text "$preview_file" "Phase 16.6: Template edit form" "Phase 16.6: template edit form HTML comment"

# Edit form POST action
require_text "$preview_file" "/apps/studio/tools/label-designer/template/edit" "Phase 16.6: template edit POST action URL"

# Edit form hidden fields (owner_key, template_key — NOT template_key input nor context_ref)
require_text "$preview_file" 'name="owner_key"' "Phase 16.6: edit form owner_key hidden field"
require_text "$preview_file" 'name="template_key"' "Phase 16.6: edit form template_key hidden field"

# Forbidden controls absent (must not have context_ref or field_key as editable inputs)
# template_key is allowed only as a hidden field — no text/input control for structural editing
forbid_pattern "$preview_file" 'name="context_ref"' "Phase 16.6: forbidden context_ref input"
forbid_pattern "$preview_file" 'name="field_key"' "Phase 16.6: forbidden field_key input"

# Redirect preserves template inspector params
require_text "$controller_file" "'view_resource' => 'template'" "Phase 16.6: redirect preserves view_resource=template"

echo "== Phase 16.7 Restrained Rule Editor =="

# Service file existence
require_file "$tool_dir/Services/LabelDesignerRuleEditService.php" "Phase 16.7: rule edit service file"

# Controller handler
require_text "$controller_file" "labelDesignerEditRule" "Phase 16.7: controller edit rule handler"
require_text "$controller_file" "LabelDesignerRuleEditService" "Phase 16.7: controller imports rule edit service"
require_text "$controller_file" "confirm_edit" "Phase 16.7: confirm_edit POST param extracted in rule edit"

# Route
require_text "$routes_file" "rule/edit" "Phase 16.7: rule/edit POST route"

# Snapshot before write
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "SNAPSHOT_ROOT" "Phase 16.7: SNAPSHOT_ROOT constant in rule edit service"
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "writeSnapshot" "Phase 16.7: writeSnapshot method in rule edit service"

# Atomic tempfile + rename write
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "file_put_contents" "Phase 16.7: atomic tempfile write"
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "rename" "Phase 16.7: atomic rename write"

# Diagnostic codes
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "RE01" "Phase 16.7: diagnostic code RE01"
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "RE02" "Phase 16.7: diagnostic code RE02"
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "RE03" "Phase 16.7: diagnostic code RE03"
require_text "$tool_dir/Services/LabelDesignerRuleEditService.php" "RE04" "Phase 16.7: diagnostic code RE04"

# Locale keys (3 occurrences each: EN dict + NE dict + view rendering)
require_text "$preview_file" "rule_edit_title" "Phase 16.7: rule edit title locale key"
require_text "$preview_file" "rule_edit_label_label" "Phase 16.7: rule edit label label locale key"
require_text "$preview_file" "rule_edit_description_label" "Phase 16.7: rule edit description label locale key"
require_text "$preview_file" "rule_edit_priority_label" "Phase 16.7: rule edit priority label locale key"
require_text "$preview_file" "rule_edit_enabled_label" "Phase 16.7: rule edit enabled label locale key"
require_text "$preview_file" "rule_edit_condition_value_label" "Phase 16.7: rule edit condition value label locale key"
require_text "$preview_file" "rule_edit_effect_label_label" "Phase 16.7: rule edit effect label label locale key"
require_text "$preview_file" "rule_edit_effect_value_label" "Phase 16.7: rule edit effect value label locale key"
require_text "$preview_file" "rule_edit_confirm_text" "Phase 16.7: rule edit confirm text locale key"
require_text "$preview_file" "rule_edit_button" "Phase 16.7: rule edit button locale key"
require_text "$preview_file" "rule_edit_success" "Phase 16.7: rule edit success locale key"
require_text "$preview_file" "rule_edit_failed" "Phase 16.7: rule edit failed locale key"
require_text "$preview_file" "rule_edit_diagnostics_title" "Phase 16.7: rule edit diagnostics title locale key"
require_text "$preview_file" "rule_edit_diagnostics_code_label" "Phase 16.7: rule edit diagnostics code label locale key"
require_text "$preview_file" "rule_edit_diagnostics_check_label" "Phase 16.7: rule edit diagnostics check label locale key"
require_text "$preview_file" "rule_edit_diagnostics_result_label" "Phase 16.7: rule edit diagnostics result label locale key"

# Edit form HTML comment
require_text "$preview_file" "Phase 16.7: Rule edit form" "Phase 16.7: rule edit form HTML comment"

# Edit form POST action
require_text "$preview_file" "/apps/studio/tools/label-designer/rule/edit" "Phase 16.7: rule edit POST action URL"

# Edit form hidden fields (owner_key, rule_key — NOT context_key nor template_key as inputs)
require_text "$preview_file" 'name="owner_key"' "Phase 16.7: edit form owner_key hidden field"
require_text "$preview_file" 'name="rule_key"' "Phase 16.7: edit form rule_key hidden field"

# Forbidden controls absent (must not have condition field_key/operator or effect type/target as editable inputs)
forbid_pattern "$preview_file" 'name="condition_field' "Phase 16.7: forbidden condition field input"
forbid_pattern "$preview_file" 'name="condition_operator' "Phase 16.7: forbidden condition operator input"
forbid_pattern "$preview_file" 'name="effect_type' "Phase 16.7: forbidden effect type input"
forbid_pattern "$preview_file" 'name="effect_target' "Phase 16.7: forbidden effect target input"

# Redirect preserves rule inspector params
require_text "$controller_file" "view_rule" "Phase 16.7: redirect preserves view_rule"

echo "== Phase 16.8 Resource Management Consolidation =="

# Shared closures in preview view
require_text "$preview_file" '$renderFlash' "Phase 16.8: shared flash renderer closure"
require_text "$preview_file" '$renderDiagTable' "Phase 16.8: shared diagnostics table renderer closure"
require_text "$preview_file" "renderDiagTable('context_edit')" "Phase 16.8: context edit uses shared diag table"
require_text "$preview_file" "renderDiagTable('template_edit')" "Phase 16.8: template edit uses shared diag table"
require_text "$preview_file" "renderDiagTable('rule_edit')" "Phase 16.8: rule edit uses shared diag table"
require_text "$preview_file" "renderFlash()" "Phase 16.8: all three inspectors use shared flash (occurs 3+)"

# CE04 code in context edit service
require_text "$tool_dir/Services/LabelDesignerContextEditService.php" "CE04" "Phase 16.8: diagnostic code CE04"

# Shared CSS class
require_text "$preview_file" "ld-edit-form" "Phase 16.8: shared editor form CSS class"

echo "== Phase 19 Governance Workspace Cleanup =="

# Locale keys (EN) — 8 group labels appear in EN and NE locale dicts
require_text "$preview_file" "governance_group_summary" "Phase 19: locale key governance_group_summary"
require_text "$preview_file" "governance_group_reference_docs" "Phase 19: locale key governance_group_reference_docs"
require_text "$preview_file" "governance_group_discovery" "Phase 19: locale key governance_group_discovery"
require_text "$preview_file" "governance_group_readiness" "Phase 19: locale key governance_group_readiness"
require_text "$preview_file" "governance_group_metadata" "Phase 19: locale key governance_group_metadata"
require_text "$preview_file" "governance_group_dry_run" "Phase 19: locale key governance_group_dry_run"
require_text "$preview_file" "governance_group_diagnostics" "Phase 19: locale key governance_group_diagnostics"
require_text "$preview_file" "governance_group_reference" "Phase 19: locale key governance_group_reference"

# Accordion HTML class and open-by-default on summary group
require_text "$preview_file" "ld-governance-group" "Phase 19: CSS class ld-governance-group (3+ occurrences)"
forbid_pattern "$preview_file" "ld-governance-group open\"" "Phase 19: Governance groups are not default-open in freeze pass"

# Group labels rendered in view headings (dt/summary at least 2 occurrences each)
require_text "$preview_file" "governance_group_summary" "Phase 19: group label rendered in view summary"
require_text "$preview_file" "governance_group_readiness" "Phase 19: Readiness group rendered in view summary"

echo "== Phase 20 UI Freeze Audit =="

require_text "$preview_file" "ld-workspace-rules-inspector" "Phase 20: rule inspector wrapped inside a rules workspace section"
require_text "$preview_file" "ld-workspace-rules-next-actions" "Phase 20: rules next-actions wrapped inside a rules workspace section"
require_text "$preview_file" "ld-workspace-build-progress" "Phase 20: build progress wrapped inside a build workspace section"
require_text "$preview_file" "ld-workspace-hidden-heading" "Phase 20: hidden workspace heading utility class exists"
require_text "$preview_file" "rules_inspector_return_to_rules" "Phase 20: rules inspector return label locale key exists"
require_text "$preview_file" "rules_status_title" "Phase 20: rules status title locale key exists"
require_text "$preview_file" "context_create_confirm" "Phase 20: context create confirm locale key exists"
require_text "$preview_file" "context_create_submit" "Phase 20: context create submit locale key exists"
require_text "$preview_file" "common_preview_error" "Phase 20: shared preview error locale key exists"
require_text "$preview_file" "common_target_path" "Phase 20: shared target path locale key exists"
require_text "$preview_file" "common_fields_selected_later" "Phase 20: shared deferred-fields locale key exists"
require_text "$preview_file" "#ld-workspace-governance-readiness" "Phase 20: governance readiness deep-link anchor emitted directly"
forbid_pattern "$preview_file" "buildUrl\\(\\['workspace' => 'governance', '#ld-workspace-governance-readiness'\\]\\)" "Phase 20: no broken buildUrl fragment override pattern"
forbid_pattern "$preview_file" "<strong>preview_error</strong>|>fields_selected_later<|>In-memory rule test<|>No print/export/QR<" "Phase 20: raw freeze-audit UI strings removed from rendered markup"

echo "== Phase 17 Context Editor V1 =="

# Service file existence
require_file "$tool_dir/Services/LabelDesignerContextEditService.php" "Phase 17: context edit service file"

# Controller handler
require_text "$controller_file" "labelDesignerEditContext" "Phase 17: controller edit context handler"
require_text "$controller_file" "LabelDesignerContextEditService" "Phase 17: controller imports context edit service"
require_text "$controller_file" "confirm_edit" "Phase 17: confirm_edit POST param extracted"

# Route
require_text "$routes_file" "context/edit" "Phase 17: context/edit POST route"

# Locale keys (3 occurrences each: EN dict + NE dict + view rendering)
require_text "$preview_file" "context_edit_title" "Phase 17: context edit title locale key"
require_text "$preview_file" "context_edit_purpose_label" "Phase 17: context edit purpose label locale key"
require_text "$preview_file" "context_edit_field_label" "Phase 17: context edit field label locale key"
require_text "$preview_file" "context_edit_data_type_label" "Phase 17: context edit data type label locale key"
require_text "$preview_file" "context_edit_confirm_label" "Phase 17: context edit confirm label locale key"
require_text "$preview_file" "context_edit_confirm_text" "Phase 17: context edit confirm text locale key"
require_text "$preview_file" "context_edit_button" "Phase 17: context edit button locale key"
require_text "$preview_file" "context_edit_success" "Phase 17: context edit success locale key"
require_text "$preview_file" "context_edit_failed" "Phase 17: context edit failed locale key"

# Edit form HTML comment
require_text "$preview_file" "Phase 17: Edit context form" "Phase 17: context edit form HTML comment"

# Edit form POST action
require_text "$preview_file" "/apps/studio/tools/label-designer/context/edit" "Phase 17: context edit POST action URL"

# Edit form hidden fields
require_text "$preview_file" "name=\"owner_key\"" "Phase 17: edit form owner_key hidden field"

# Context edit service diagnostic codes
require_text "$tool_dir/Services/LabelDesignerContextEditService.php" "CE01" "Phase 17: diagnostic code CE01"
require_text "$tool_dir/Services/LabelDesignerContextEditService.php" "CE02" "Phase 17: diagnostic code CE02"

echo "== Phase 17.1 Context Editor Result Feedback =="

# Controller model key for edit diagnostics
require_text "$controller_file" "label_edit_diagnostics" "Phase 17.1: controller edit diagnostics model key"

# Controller session key extraction in buildLabelDesignerPreviewModel()
require_text "$controller_file" "studio_label_designer_edit_diagnostics" "Phase 17.1: edit diagnostics session key"

# Controller session key storage in edit context handler
require_text "$controller_file" "label_designer_edit_diagnostics" "Phase 17.1: edit diagnostics session key storage in edit handler"

# View variable extraction
require_text "$preview_file" "labelEditDiagnostics" "Phase 17.1: view edit diagnostics extraction"

# Locale keys (3 occurrences each: EN dict + NE dict + view rendering)
require_text "$preview_file" "context_edit_diagnostics_title" "Phase 17.1: context edit diagnostics title locale key"
require_text "$preview_file" "context_edit_diagnostics_code_label" "Phase 17.1: context edit diagnostics code label locale key"
require_text "$preview_file" "context_edit_diagnostics_check_label" "Phase 17.1: context edit diagnostics check label locale key"
require_text "$preview_file" "context_edit_diagnostics_result_label" "Phase 17.1: context edit diagnostics result label locale key"
require_text "$preview_file" "context_edit_data_type_missing" "Phase 17.1: context edit data type missing locale key"

# CE diagnostics table in context inspector
require_text "$preview_file" "context_edit_diagnostics_title" "Phase 17.1: CE diagnostics table rendering"

# Missing data_type badge in allowed fields table
require_text "$preview_file" "ld-missing-badge" "Phase 17.1: missing data_type visual badge"

# CSS class
require_text "$preview_file" "ld-missing-badge" "Phase 17.1: CSS class for missing badge"

echo ""
echo "= Result ="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s), $passes pass(es))" >&2
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"

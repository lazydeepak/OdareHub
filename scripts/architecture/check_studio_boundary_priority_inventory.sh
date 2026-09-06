#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_studio_boundary_priority_inventory"
echo "- read-only diagnostic for Studio boundary priority inventory"

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

  if [[ -f "$path" ]] && grep -Fq "$needle" "$path"; then
    ok "$label"
  else
    fail "$label not found in $path"
  fi
}

echo ""
echo "== Studio optional integration contract =="
require_file "apps/Studio/manifest.json" "Studio manifest"
require_file "apps/Studio/bootstrap.php" "Studio bootstrap"
require_file "apps/Studio/routes.php" "Studio routes"
require_text "apps/Studio/manifest.json" '"can_disable": true' "Studio can be disabled"
require_text "apps/Studio/manifest.json" '"can_uninstall": true' "Studio can be uninstalled"
require_text "apps/Studio/bootstrap.php" 'Studio bootstrapping is intentionally empty' "Studio bootstrap stays empty in this phase"

echo ""
echo "== Current Studio folder ownership =="
for entry in \
  Controllers Routes Services Views Tools Workflow Authorization assets lang ActionHandlers Adapters Analytics DataProviders Repositories Templates; do
  if [[ -e "apps/Studio/$entry" ]]; then
    ok "found apps/Studio/$entry"
  else
    fail "missing apps/Studio/$entry"
  fi
done

echo ""
echo "== Studio runtime-adjacent hot spots =="
for file in \
  apps/Studio/Services/GuiStudioService.php \
  apps/Studio/Services/StudioRuntimeBindingService.php \
  apps/Studio/Services/StudioGovernanceService.php \
  apps/Studio/Services/StudioViewIntrospectionService.php \
  apps/Studio/Services/StudioDependencyGraphService.php \
  apps/Studio/Services/StudioNavLinkingService.php \
  apps/Studio/Services/StudioRouteLinkingService.php \
  apps/Studio/Services/StudioDataContractService.php \
  apps/Studio/Routes/gui_studio_routes.php \
  apps/Studio/Views/gui_studio.php; do
  require_file "$file" "Studio hot spot"
done

echo ""
echo "== Legacy /ops bridge points =="
require_file "apps/Platform/routes.php" "Platform routes"
require_file "plugins/Base/Views/ops/design_studio.php" "legacy design studio list view"
require_file "plugins/Base/Views/ops/design_studio_edit.php" "legacy design studio edit view"
require_text "apps/Platform/routes.php" '/ops/gui-studio' "Platform still exposes /ops/gui-studio compatibility bridge"
require_text "apps/Platform/routes.php" '/ops/design-studio' "Platform still exposes /ops/design-studio legacy bridge"

echo ""
echo "== Service restructuring candidates =="
for file in \
  apps/Studio/Services/AppStudioRegistryService.php \
  apps/Studio/Services/StudioGovernedToolRegistryService.php \
  apps/Studio/Services/StudioResourceTypeRegistryService.php \
  apps/Studio/Services/StudioNavCandidateProviderService.php \
  apps/Studio/Services/StudioNavLinkingService.php \
  apps/Studio/Services/StudioRouteLinkingService.php \
  apps/Studio/Services/HostSurfaceContributionService.php \
  apps/Studio/Services/StudioViewIntrospectionService.php \
  apps/Studio/Services/StudioDependencyGraphService.php \
  apps/Studio/Services/StudioDataContractService.php \
  apps/Studio/Services/StudioNotificationService.php; do
  require_file "$file" "Studio service candidate"
done

echo ""
echo "== Views extraction candidates =="
for file in \
  apps/Studio/Views/partials/library_explorer.php \
  apps/Studio/Views/partials/loaded_resource_workbench.php \
  apps/Studio/Views/partials/editor_workbench_shell.php \
  apps/Studio/Views/partials/workflow_status.php \
  apps/Studio/Views/partials/mode_panel.php \
  apps/Studio/Views/partials/apply_center.php \
  apps/Studio/Views/partials/governance_apply_zone.php \
  apps/Studio/Views/partials/governance_diagnostics_panel.php \
  apps/Studio/Views/partials/tool_navigation.php; do
  require_file "$file" "Studio view candidate"
done

echo ""
echo "== Tool boundary =="
for dir in \
  apps/Studio/Tools/AppBuilder \
  apps/Studio/Tools/ModuleBuilder \
  apps/Studio/Tools/ViewEditor \
  apps/Studio/Tools/NavMenuTool \
  apps/Studio/Tools/WidgetBuilder \
  apps/Studio/Tools/ReportBuilder \
  apps/Studio/Tools/CustomizationStudio/Diagnose/CssSelectorInspector \
  apps/Studio/Tools/DbSchemaTool \
  apps/Studio/Tools/PermissionProfileTool \
  apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor \
  apps/Studio/Tools/ValidationCenter \
  apps/Studio/Tools/AuditHistory \
  apps/Studio/Tools/PackageTool \
  apps/Studio/Tools/ResourceExplorer; do
  require_dir "$dir" "Studio tool folder"
  require_file "$dir/manifest.php" "Studio tool manifest"
done

echo ""
echo "== Generated app lifecycle boundary =="
require_file "storage/appstudio/apps_registry.json" "Studio generated-app registry"
require_dir "storage/appstudio/generated_data" "Studio generated-data store"
require_dir "storage/appstudio/snapshots" "Studio snapshots store"
require_dir "storage/appstudio/applies" "Studio apply evidence store"
require_dir "storage/appstudio/audit" "Studio audit store"
require_dir "storage/appstudio/packages" "Studio packages store"
require_dir "storage/appstudio/publish_decisions" "Studio publish-decision store"
require_dir "apps/Generated" "generated app owner tree"
require_dir "public/assets/apps" "published app asset delivery tree"

echo ""
echo "== Cleanup index alignment =="
require_file "docs/migration-cleanup/maps/batch-8-studio-boundary-priority-inventory.md" "Studio boundary inventory report"
require_text "MIGRATION-CLEANUP-INDEX.md" 'batch-8-studio-boundary-priority-inventory.md' "Migration cleanup index links the Studio boundary report"
require_text "docs/migration-cleanup/phases/move-plan.md" 'Batch 8: Studio boundary priority inventory' "Move plan includes the Studio boundary batch"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio boundary priority inventory gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

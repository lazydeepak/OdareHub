#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_studio_runtime_adjacent_separation_map"
echo "- read-only diagnostic for Studio runtime-adjacent separation mapping"

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
echo "== Studio optional contract and canonical routes =="
require_file "apps/Studio/manifest.json" "Studio manifest"
require_file "apps/Studio/routes.php" "Studio canonical routes"
require_text "apps/Studio/manifest.json" '"can_disable": true' "Studio can be disabled"
require_text "apps/Studio/manifest.json" '"can_uninstall": true' "Studio can be uninstalled"
require_text "apps/Studio/routes.php" "studio_register_gui_studio_routes" "Studio route registration bridge present"


echo ""
echo "== Runtime-adjacent Studio folders =="
for dir in ActionHandlers Analytics DataProviders Repositories Adapters; do
  require_dir "apps/Studio/$dir" "Studio runtime-adjacent folder"
done


echo ""
echo "== Runtime-adjacent Studio file anchors =="
require_file "apps/Studio/ActionHandlers/OrderSubmitHandler.php" "order action handler"
require_file "apps/Studio/Analytics/AnalyticsService.php" "analytics service"
require_file "apps/Studio/DataProviders/OrdersProvider.php" "orders data provider"
require_file "apps/Studio/Repositories/OrdersRepository.php" "orders repository"
require_file "apps/Studio/Services/GuiStudioService.php" "GuiStudioService"
require_file "apps/Studio/Services/StudioRuntimeBindingService.php" "StudioRuntimeBindingService"
require_file "apps/Studio/Routes/gui_studio_routes.php" "gui studio route hub"
require_text "apps/Studio/Analytics/AnalyticsService.php" "Manufacturing intelligence layer" "analytics service still contains business-facing intelligence marker"
require_text "apps/Studio/Analytics/AnalyticsService.php" "/ops/analytics/production/drilldown" "analytics service still links ops drilldown"


echo ""
echo "== Platform and Shell bridge anchors =="
require_file "apps/Platform/routes.php" "Platform routes"
require_text "apps/Platform/routes.php" "/ops/gui-studio" "Platform exposes /ops/gui-studio compatibility route"
require_text "apps/Platform/routes.php" "studio_register_gui_studio_routes" "Platform loads Studio compatibility route registration"
require_text "apps/Platform/routes.php" "/ops/design-studio" "Platform retains legacy design-studio bridge"
require_file "apps/Shell/Services/AdminLayerWrapperComposer.php" "AdminLayerWrapperComposer"
require_text "apps/Shell/Services/AdminLayerWrapperComposer.php" "isStudioSystemAppEnabled" "Shell composer keeps conditional Studio enablement gate"
require_text "apps/Shell/Services/AdminLayerWrapperComposer.php" "GuiStudioService::adminSidebarStudioEntries" "Shell composer keeps Studio sidebar discovery"
require_file "apps/Shell/Services/StyleRegistryService.php" "StyleRegistryService"
require_text "apps/Shell/Services/StyleRegistryService.php" "Studio-generated apps live outside core_apps" "Shell style registry keeps generated-app compatibility comment"


echo ""
echo "== Generated and evidence boundary anchors =="
require_dir "apps/Generated" "generated app tree"
require_dir "storage/appstudio" "studio evidence root"
require_dir "public/assets/apps" "published app asset tree"


echo ""
echo "== Cleanup index alignment =="
require_file "docs/migration-cleanup/maps/batch-9-studio-runtime-adjacent-separation-map.md" "Studio runtime-adjacent separation report"
require_text "MIGRATION-CLEANUP-INDEX.md" "batch-9-studio-runtime-adjacent-separation-map.md" "migration index links Batch 9 report"
require_text "docs/migration-cleanup/phases/move-plan.md" "Batch 9: Studio runtime-adjacent separation map" "move plan includes Batch 9 section"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio runtime-adjacent separation mapping gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

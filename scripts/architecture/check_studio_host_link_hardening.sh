#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_studio_host_link_hardening"
echo "- read-only diagnostic for Studio host-link hardening plan"

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

  if [[ -f "$path" ]] && grep -Fq "$needle" "$path"; then
    ok "$label"
  else
    fail "$label not found in $path"
  fi
}

echo ""
echo "== Platform host links and compatibility bridges =="
require_file "apps/Platform/routes.php" "Platform routes"
require_text "apps/Platform/routes.php" "get('/ops/gui-studio'" "Platform /ops/gui-studio compatibility route exists"
require_text "apps/Platform/routes.php" 'if (!$studioAliasEnabled || !is_file($studioRoutesFile))' "Platform /ops/gui-studio route has Studio optional guard"
require_text "apps/Platform/routes.php" "header('Location: /ops/platform-operations'" "Platform /ops/gui-studio route has no-op-safe fallback"
require_text "apps/Platform/routes.php" "studio_register_gui_studio_routes" "Platform registers Studio compatibility routes"
require_text "apps/Platform/routes.php" "SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']" "Platform contains Studio enabled-status guard"
require_text "apps/Platform/routes.php" "get('/ops/design-studio'" "Platform legacy /ops/design-studio route exists"
require_text "apps/Platform/routes.php" "OPS_ENABLE_LEGACY_DESIGN_STUDIO" "Platform legacy design-studio env guard exists"

require_file "apps/Platform/Views/ops/platform_operations.php" "Platform operations view"
require_text "apps/Platform/Views/ops/platform_operations.php" 'if ($studioAppEnabled)' "Platform operations card conditionally renders Studio link"

require_file "apps/Platform/Services/DashboardAggregatorService.php" "Dashboard aggregator bridge service"
require_text "apps/Platform/Services/DashboardAggregatorService.php" "loadStudioServiceIfEnabled" "Dashboard aggregator has Studio enabled loader guard"

require_file "apps/Platform/Services/RouteViewBridgeService.php" "Route view bridge service"
require_text "apps/Platform/Services/RouteViewBridgeService.php" "loadStudioServiceIfEnabled" "Route view bridge has Studio enabled loader guard"


echo ""
echo "== Shell Studio exposure and style/sidebar references =="
require_file "apps/Shell/Services/AdminLayerWrapperComposer.php" "Admin layer wrapper composer"
require_text "apps/Shell/Services/AdminLayerWrapperComposer.php" "isStudioSystemAppEnabled" "Shell admin wrapper has Studio enabled guard"
require_text "apps/Shell/Services/AdminLayerWrapperComposer.php" "GuiStudioService::adminSidebarStudioEntries" "Shell admin wrapper uses guarded Studio sidebar entries"

require_file "apps/Shell/Services/StyleRegistryService.php" "Style registry service"
require_text "apps/Shell/Services/StyleRegistryService.php" "Studio-generated apps live outside core_apps" "Style registry has Studio-generated app fallback reference"


echo ""
echo "== Base compatibility bridge surfaces =="
require_file "plugins/Base/Views/ops/design_studio.php" "Base legacy design-studio list view"
require_file "plugins/Base/Views/ops/design_studio_edit.php" "Base legacy design-studio edit view"
require_text "plugins/Base/Views/ops/design_studio.php" "/ops/design-studio" "Base legacy design-studio list uses /ops/design-studio"
require_text "plugins/Base/Views/ops/design_studio_edit.php" "/ops/design-studio" "Base legacy design-studio edit uses /ops/design-studio"
if grep -R --line-number --fixed-strings '/ops/gui-studio' plugins/Base/Views >/dev/null 2>&1; then
  fail "Base Views unexpectedly contain /ops/gui-studio direct reference"
else
  ok "Base Views contain no direct /ops/gui-studio reference"
fi


echo ""
echo "== Studio manifest hooks, routes, and navigation contribution =="
require_file "apps/Studio/manifest.json" "Studio manifest"
require_text "apps/Studio/manifest.json" '"can_disable": true' "Studio can be disabled"
require_text "apps/Studio/manifest.json" '"can_uninstall": true' "Studio can be uninstalled"
require_text "apps/Studio/manifest.json" '"type": "host_surface"' "Studio host-surface hook declaration exists"
require_text "apps/Studio/manifest.json" '"path": "/ops/gui-studio"' "Studio manifest keeps /ops/gui-studio alias metadata"

require_file "apps/Studio/navigation.php" "Studio navigation contribution"
require_text "apps/Studio/navigation.php" "'owner' => 'studio'" "Studio navigation owner tag exists"
require_text "apps/Studio/navigation.php" "'url' => '/apps/studio'" "Studio navigation contributes canonical /apps/studio link"


echo ""
echo "== Cleanup index alignment =="
require_file "docs/migration-cleanup/maps/batch-10-studio-host-link-hardening-plan.md" "Batch 10 host-link hardening plan"
require_text "MIGRATION-CLEANUP-INDEX.md" "batch-10-studio-host-link-hardening-plan.md" "Migration cleanup index links Batch 10 report"
require_text "docs/migration-cleanup/phases/move-plan.md" "Batch 10: Studio host-link hardening plan" "Move plan includes Batch 10 section"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio host-link hardening plan gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

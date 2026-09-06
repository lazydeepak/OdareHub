#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

owner_manifest="apps/Studio/Tools/OwnerStructureScan/manifest.php"
style_manifest="apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/manifest.php"
routes_file="apps/Studio/routes.php"

checks=0
failures=0

pass() {
  checks=$((checks + 1))
  echo "  ok: $1"
}

fail() {
  checks=$((checks + 1))
  failures=$((failures + 1))
  echo "  fail: $1" >&2
}

require_file() {
  local path="$1"
  local label="$2"
  if [[ -f "$path" ]]; then
    pass "$label"
  else
    fail "$label ($path missing)"
  fi
}

require_text() {
  local path="$1"
  local needle="$2"
  local label="$3"
  if grep -Fq -- "$needle" "$path"; then
    pass "$label"
  else
    fail "$label"
  fi
}

forbid_text() {
  local path="$1"
  local needle="$2"
  local label="$3"
  if grep -Fq -- "$needle" "$path"; then
    fail "$label"
  else
    pass "$label"
  fi
}

manifest_value_is_true() {
  local path="$1"
  local key="$2"
  local label="$3"
  if grep -Eq "^[[:space:]]*'${key}'[[:space:]]*=>[[:space:]]*true," "$path"; then
    pass "$label"
  else
    fail "$label"
  fi
}

echo "[architecture] check_studio_manifest_runtime_truth"
echo "- verifies selected Studio manifests match implemented routes and reusable capability boundaries"

echo ""
echo "== Required files =="
require_file "$owner_manifest" "Owner Structure Scan manifest exists"
require_file "$style_manifest" "Style Compliance manifest exists"
require_file "$routes_file" "Studio routes file exists"

echo ""
echo "== Owner Structure Scan truth =="
require_text "$owner_manifest" "'placeholder' => false" "Owner Structure Scan is declared implemented"
manifest_value_is_true "$owner_manifest" "can_modify" "Owner Structure Scan declares guarded mutation capability"
require_text "$owner_manifest" "'/apps/studio/tools/owner-structure-scan/initialize-workspace-artifacts'" "workspace initialization route is declared"
require_text "$routes_file" "\$router->post('/apps/studio/tools/owner-structure-scan/initialize-workspace-artifacts'" "workspace initialization POST route exists"
forbid_text "$owner_manifest" "::" "Owner Structure Scan services contain class names only"

echo ""
echo "== Style Compliance truth =="
require_text "$style_manifest" "'placeholder' => false" "Style Compliance is declared implemented"
manifest_value_is_true "$style_manifest" "can_modify" "Style Compliance declares mutation capability"
manifest_value_is_true "$style_manifest" "writes_to_owner_artifact" "Style Compliance declares owner-artifact writes"
forbid_text "$style_manifest" "::" "Style Compliance services contain class names only"

style_routes=(
  "/apps/studio/tools/customization-studio/diagnose/style-compliance"
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/scan"
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness"
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/fix-one"
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute"
)

for route in "${style_routes[@]}"; do
  require_text "$style_manifest" "'$route'" "Style Compliance manifest declares $route"
done

style_post_routes=(
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/scan"
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness"
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/fix-one"
  "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute"
)

for route in "${style_post_routes[@]}"; do
  require_text "$routes_file" "\$router->post('$route'" "Style Compliance POST route exists: $route"
done

style_services=(
  "StyleComplianceScannerService"
  "StyleComplianceGuardedRepairCapabilityService"
  "StyleComplianceRepairReadinessService"
  "StyleComplianceRepairEngineService"
)

for service in "${style_services[@]}"; do
  require_text "$style_manifest" "$service" "Style Compliance manifest registers $service"
done

require_file "apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php" "StyleComplianceScannerService implementation exists"
require_file "apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceGuardedRepairCapabilityService.php" "StyleComplianceGuardedRepairCapabilityService implementation exists"
require_file "apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairReadinessService.php" "StyleComplianceRepairReadinessService implementation exists"
require_file "apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairEngineService.php" "StyleComplianceRepairEngineService implementation exists"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "STUDIO MANIFEST RUNTIME TRUTH: FAIL ($checks checks, $failures failures)" >&2
  exit 1
fi

echo "STUDIO MANIFEST RUNTIME TRUTH: PASS ($checks checks)"

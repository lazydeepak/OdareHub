#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

tool_dir="apps/Studio/Tools/CustomizationStudio/Diagnose/TokenImpactExplorer"
manifest_file="$tool_dir/manifest.php"
service_file="$tool_dir/Services/TokenImpactDiscoveryService.php"
view_file="$tool_dir/Views/impact-explorer.php"
routes_file="apps/Studio/routes.php"
controller_file="apps/Studio/Controllers/StudioController.php"

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

require_text() {
  local path="$1"
  local pattern="$2"
  local label="$3"
  if [[ ! -f "$path" ]]; then
    fail "$label - file missing ($path)"
    return
  fi
  if grep -Fq -- "$pattern" "$path"; then
    pass "$label"
  else
    fail "$label - expected text not found: $pattern"
  fi
}

forbid_pattern() {
  local path="$1"
  local pattern="$2"
  local label="$3"
  if [[ ! -f "$path" ]]; then
    fail "$label - file missing ($path)"
    return
  fi
  if grep -Eq -- "$pattern" "$path"; then
    fail "$label - forbidden pattern found: $pattern"
  else
    pass "$label"
  fi
}

echo "[architecture] check_token_impact_explorer_boundaries"
echo "- Token Impact Explorer read-only boundary diagnostic"
echo ""

echo "== Required files =="
require_file "$manifest_file" "Token Impact Explorer manifest"
require_file "$service_file" "Token Impact Explorer discovery service"
require_file "$view_file" "Token Impact Explorer view"

echo ""
echo "== Read-only manifest contract =="
require_text "$manifest_file" "'can_modify' => false" "manifest declares read-only (can_modify false)"
require_text "$manifest_file" "'writes_to_owner_artifact' => false" "manifest declares no owner artifact writes"
require_text "$manifest_file" "'supports_diff' => false" "manifest declares no diff support"
require_text "$manifest_file" "'supports_snapshot' => false" "manifest declares no snapshot support"
require_text "$manifest_file" "'supports_rollback' => false" "manifest declares no rollback support"
require_text "$manifest_file" "'supports_rollback' => false" "manifest key-value pair"
require_text "$manifest_file" "'category' => 'inspection'" "manifest declares inspection category"
require_text "$manifest_file" "'risk_level' => 'low'" "manifest declares low risk level"

echo ""
echo "== No POST routes =="
tie_post_lines=$(grep -nE "post\('/apps/studio/tools/customization-studio/diagnose/token-impact-explorer" "$routes_file" || true)
if [[ -z "$tie_post_lines" ]]; then
  pass "no POST routes registered for Token Impact Explorer"
else
  echo "$tie_post_lines" >&2
  fail "POST routes found for Token Impact Explorer (read-only tool)"
fi

require_text "$routes_file" "/apps/studio/tools/customization-studio/diagnose/token-impact-explorer" "GET route registered for Token Impact Explorer"

echo ""
echo "== No file write or DB operations =="
forbid_pattern "$service_file" "file_put_contents|fwrite|fopen\\(|mkdir\\(|rename\\(|unlink\\(|copy\\(|touch\\(|chmod\\(|symlink\\(" "no file write API in discovery service"
forbid_pattern "$service_file" "\\bDB::|new PDO|->query\\(|->exec\\(|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM|CREATE[[:space:]]+TABLE|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE" "no DB operations in discovery service"

echo ""
echo "== No runtime coupling =="
forbid_pattern "$service_file" "StyleRegistry|theme\.css|shell\.css|operator\.css|StyleChain|ResolvedExperience" "no Style Registry runtime coupling in service"
forbid_pattern "$service_file" "header\\(['\\\"]Location" "no redirect mutation in service"

echo ""
echo "== Controller confinement =="
require_text "$controller_file" "tokenImpactExplorerPreview" "StudioController has tokenImpactExplorerPreview method"

echo ""
echo "== Result =="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s), $passes pass(es))" >&2
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"

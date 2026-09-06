#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

HOST="apps/Studio/Views/pages/tool_placeholder.php"
SERVICE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionPlanService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionPlanWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.deletion-plan.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_plan_workspace.php"
HOST_PROBE="apps/Studio/tests/probe_tool_postlude_composition.php"
PLAN_GATE="scripts/architecture/check_studio_deletion_plan_capability.sh"
IMPACT_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_impact_workspace.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_owner_structure_deletion_plan_workspace"

require_file "$HOST" "tool host wrapper"
require_file "$SERVICE" "Owner Structure deletion plan adapter"
require_file "$WORKSPACE" "Owner Structure deletion plan workspace service"
require_file "$VIEW" "Owner Structure deletion plan postlude"
require_file "$MANIFEST" "Owner Structure manifest"
require_file "$PROBE" "deletion plan workspace probe"
require_file "$HOST_PROBE" "composable postlude host probe"
require_file "$PLAN_GATE" "canonical deletion plan gate"
require_file "$IMPACT_WORKSPACE_GATE" "deletion impact workspace prerequisite gate"
require_text "$HOST" ".postlude.*.php" "tool host supports composable postludes"
require_text "$HOST" "sort(\$additionalPostludes" "additional postludes load deterministically"
require_text "$SERVICE" "StudioDeletionPlanService::compose" "adapter reuses current impact evidence"
require_text "$WORKSPACE" "OwnerStructureDeletionPlanService::plan" "workspace delegates to adapter"
require_text "$VIEW" "OwnerStructureDeletionPlanWorkspaceService::build" "view invokes workspace adapter"
require_text "$VIEW" "name=\"deletion_plan\"" "planning is explicitly on demand"
require_text "$VIEW" "method=\"get\"" "workspace remains GET-only"
require_text "$VIEW" "Planning only." "view declares non-executable boundary"
require_text "$MANIFEST" "StudioDeletionPlanService" "manifest registers canonical plan capability"
require_text "$MANIFEST" "OwnerStructureDeletionPlanWorkspaceService" "manifest registers workspace service"
require_text "$MANIFEST" "preview.postlude.deletion-plan.php" "manifest registers plan postlude"
require_no_pattern "$VIEW" '<form[^>]+method="post"|OwnerStructureDeletionPlanService::plan|StudioDeletionPlanService::|file_put_contents|unlink\s*\(|rename\s*\(' "view contains no mutation or domain-service bypass"
require_no_pattern "$WORKSPACE" 'file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace contains no mutation authority"

if command -v php >/dev/null 2>&1; then
  php -l "$HOST" >/dev/null && ok "host PHP lint" || fail "host PHP lint"
  php -l "$SERVICE" >/dev/null && ok "adapter PHP lint" || fail "adapter PHP lint"
  php -l "$WORKSPACE" >/dev/null && ok "workspace PHP lint" || fail "workspace PHP lint"
  php -l "$VIEW" >/dev/null && ok "view PHP lint" || fail "view PHP lint"
  php -l "$MANIFEST" >/dev/null && ok "manifest PHP lint" || fail "manifest PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php -l "$HOST_PROBE" >/dev/null && ok "host probe PHP lint" || fail "host probe PHP lint"
  php "$PROBE" || fail "deletion plan workspace behavior probe"
  php "$HOST_PROBE" || fail "composable postlude host probe"
else
  fail "php is required to certify deletion plan workspace"
fi

bash "$PLAN_GATE" || fail "canonical deletion plan gate"
bash "$IMPACT_WORKSPACE_GATE" || fail "deletion impact workspace prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Owner Structure deletion plan workspace FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Owner Structure deletion plan workspace PASS"

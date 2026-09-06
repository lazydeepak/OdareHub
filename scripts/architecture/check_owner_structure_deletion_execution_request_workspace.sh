#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionRequestService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionRequestWorkspaceService.php"
CONTROLLER="apps/Studio/Tools/OwnerStructureScan/Controllers/OwnerStructureDeletionExecutionRequestController.php"
ROUTES="apps/Studio/Routes/owner_structure_deletion_execution_request_routes.php"
BOOTSTRAP="apps/Studio/bootstrap.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzz-deletion-execution-request.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_execution_request_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_execution_request_capability.sh"
DRY_RUN_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_execution_dry_run_workspace.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_owner_structure_deletion_execution_request_workspace"

for pair in \
  "$ADAPTER|execution request adapter" \
  "$WORKSPACE|execution request workspace presenter" \
  "$CONTROLLER|execution request controller" \
  "$ROUTES|execution request routes" \
  "$BOOTSTRAP|Studio bootstrap" \
  "$VIEW|execution request postlude" \
  "$MANIFEST|Owner Structure manifest" \
  "$PROBE|execution request workspace probe" \
  "$CAPABILITY_GATE|canonical request gate" \
  "$DRY_RUN_WORKSPACE_GATE|dry-run workspace prerequisite gate"; do
  path="${pair%%|*}"; label="${pair#*|}"; require_file "$path" "$label"
done

require_text "$ADAPTER" "StudioDeletionExecutionRequestService::record" "adapter delegates canonical request capability"
require_text "$WORKSPACE" "OwnerStructureDeletionExecutionRequestService::latest" "workspace reads exact request history"
require_text "$CONTROLLER" "StudioDeletionChangeSetService::build" "controller recomputes change set server-side"
require_text "$CONTROLLER" "StudioDeletionExecutionReadinessService::assess" "controller recomputes readiness server-side"
require_text "$CONTROLLER" "StudioDeletionExecutionDryRunService::simulate" "controller recomputes dry run server-side"
require_text "$CONTROLLER" "Auth::requireCsrf" "request route requires CSRF"
require_text "$ROUTES" "deletion-execution-request" "POST route is registered"
require_text "$BOOTSTRAP" "owner_structure_deletion_execution_request_routes.php" "Studio bootstrap loads request route"
require_text "$VIEW" 'name="deletion_execution_request" value="1"' "request workspace is explicitly on demand"
require_text "$VIEW" 'method="post" action="/apps/studio/tools/owner-structure-scan/deletion-execution-request"' "workspace posts only immutable request provenance"
require_text "$VIEW" "This does not create a snapshot, archive files, delete the target, or authorize execution." "view declares non-execution boundary"
require_text "$MANIFEST" "StudioDeletionExecutionRequestService" "manifest registers canonical request capability"
require_text "$MANIFEST" "OwnerStructureDeletionExecutionRequestWorkspaceService" "manifest registers request workspace presenter"
require_text "$MANIFEST" "preview.postlude.zzzzzz-deletion-execution-request.php" "manifest registers ordered request postlude"
require_no_pattern "$CONTROLLER" 'unlink\s*\(|rename\s*\(|rmdir\s*\(|copy\s*\(|delete_target|archive_target|execute_deletion' "controller contains no deletion execution implementation"
require_no_pattern "$VIEW" 'execute_deletion|apply_changes|archive_target|delete_target' "view exposes no execution action"

if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$CONTROLLER" "$ROUTES" "$BOOTSTRAP" "$VIEW" "$MANIFEST" "$PROBE"; do
    php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"
  done
  php "$PROBE" || fail "execution request workspace behavior probe"
else
  fail "php is required to certify execution request workspace"
fi

bash "$CAPABILITY_GATE" || fail "canonical request capability gate"
bash "$DRY_RUN_WORKSPACE_GATE" || fail "dry-run workspace prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Owner Structure deletion execution request workspace FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Owner Structure deletion execution request workspace PASS"

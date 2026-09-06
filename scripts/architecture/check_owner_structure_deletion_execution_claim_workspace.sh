#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionClaimService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionClaimWorkspaceService.php"
CONTROLLER="apps/Studio/Tools/OwnerStructureScan/Controllers/OwnerStructureDeletionExecutionClaimController.php"
ROUTE="apps/Studio/Routes/owner_structure_deletion_execution_claim_routes.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzzzz-deletion-execution-claim.php"
BOOTSTRAP="apps/Studio/bootstrap.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_execution_claim_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_execution_claim_capability.sh"
READINESS_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_executor_readiness_workspace.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_owner_structure_deletion_execution_claim_workspace"
for pair in "$ADAPTER|claim adapter" "$WORKSPACE|claim workspace" "$CONTROLLER|claim controller" "$ROUTE|claim route" "$VIEW|claim postlude" "$BOOTSTRAP|Studio bootstrap" "$MANIFEST|Owner Structure manifest" "$PROBE|claim workspace probe" "$CAPABILITY_GATE|claim capability gate" "$READINESS_WORKSPACE_GATE|executor-readiness workspace gate"; do path="${pair%%|*}"; label="${pair#*|}"; req "$path" "$label"; done
text "$ADAPTER" "StudioDeletionExecutionClaimService::claim" "adapter delegates canonical claim capability"
text "$CONTROLLER" "StudioDeletionExecutorReadinessService::assess" "controller recomputes executor readiness server side"
text "$CONTROLLER" "Auth::requireCsrf" "claim route requires CSRF"
text "$ROUTE" "deletion-execution-claim" "claim POST route is registered"
text "$VIEW" 'method="post" action="/apps/studio/tools/owner-structure-scan/deletion-execution-claim"' "workspace exposes governed claim POST"
text "$VIEW" "does not create a snapshot, edit references, archive files, delete the target, or execute anything" "view declares non-execution boundary"
text "$BOOTSTRAP" "owner_structure_deletion_execution_claim_routes.php" "Studio bootstrap loads claim route"
text "$MANIFEST" "StudioDeletionExecutionClaimService" "manifest registers canonical claim capability"
text "$MANIFEST" "OwnerStructureDeletionExecutionClaimWorkspaceService" "manifest registers claim workspace"
text "$MANIFEST" "preview.postlude.zzzzzzzz-deletion-execution-claim.php" "manifest registers ordered claim postlude"
no_pat "$CONTROLLER" 'unlink\s*\(|rename\s*\(|rmdir\s*\(|copy\s*\(|delete_target|archive_target' "claim controller owns no owner mutation authority"
if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$CONTROLLER" "$ROUTE" "$VIEW" "$MANIFEST" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "claim workspace behavior probe"
else fail "php is required to certify claim workspace"; fi
bash "$CAPABILITY_GATE" || fail "claim capability gate"
bash "$READINESS_WORKSPACE_GATE" || fail "executor-readiness workspace prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Owner Structure deletion execution claim workspace FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Owner Structure deletion execution claim workspace PASS"

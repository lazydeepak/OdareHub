#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutorReadinessService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutorReadinessWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzzz-deletion-executor-readiness.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_executor_readiness_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_executor_readiness_capability.sh"
REQUEST_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_execution_request_workspace.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req_file(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
req_text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pattern(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_owner_structure_deletion_executor_readiness_workspace"
for pair in "$ADAPTER|executor-readiness adapter" "$WORKSPACE|executor-readiness workspace" "$VIEW|executor-readiness postlude" "$MANIFEST|Owner Structure manifest" "$PROBE|workspace probe" "$CAPABILITY_GATE|canonical capability gate" "$REQUEST_WORKSPACE_GATE|execution-request prerequisite gate"; do path="${pair%%|*}"; label="${pair#*|}"; req_file "$path" "$label"; done
req_text "$ADAPTER" "StudioDeletionExecutorReadinessService::assess" "adapter delegates canonical verifier"
req_text "$WORKSPACE" "StudioDeletionExecutionRequestStore::latest" "workspace reads exact request"
req_text "$WORKSPACE" "StudioDeletionExecutionRequestStore::latestForOwner" "workspace reads owner request history"
req_text "$WORKSPACE" "StudioDeletionExecutionRequestUseEvidenceStore::latest" "workspace reads single-use evidence"
req_text "$VIEW" 'name="deletion_executor_readiness" value="1"' "workspace is explicitly on demand"
req_text "$VIEW" 'method="get" action="/apps/studio/tools/owner-structure-scan"' "workspace is read-only GET"
req_text "$VIEW" "This workspace cannot claim the request" "view declares claim and execution boundary"
req_text "$MANIFEST" "StudioDeletionExecutorReadinessService" "manifest registers canonical verifier"
req_text "$MANIFEST" "OwnerStructureDeletionExecutorReadinessWorkspaceService" "manifest registers workspace presenter"
req_text "$MANIFEST" "preview.postlude.zzzzzzz-deletion-executor-readiness.php" "manifest registers ordered postlude"
no_pattern "$VIEW" 'method="post"|claim_request|execute_deletion|delete_target|archive_target|apply_changes' "view exposes no mutation path"
no_pattern "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "executor-readiness workspace probe"
else fail "php is required to certify executor-readiness workspace"; fi
bash "$CAPABILITY_GATE" || fail "canonical executor-readiness gate"
bash "$REQUEST_WORKSPACE_GATE" || fail "execution-request workspace prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Owner Structure deletion executor readiness workspace FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Owner Structure deletion executor readiness workspace PASS"

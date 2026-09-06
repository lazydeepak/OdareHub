#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionReadinessService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionReadinessWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzz-deletion-execution-readiness.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_execution_readiness_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_execution_readiness_capability.sh"
APPROVAL_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_approval_workspace.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_owner_structure_deletion_execution_readiness_workspace"

for pair in \
  "$ADAPTER|execution-readiness adapter" \
  "$WORKSPACE|execution-readiness workspace presenter" \
  "$VIEW|execution-readiness postlude" \
  "$MANIFEST|Owner Structure manifest" \
  "$PROBE|execution-readiness workspace probe" \
  "$CAPABILITY_GATE|canonical readiness gate" \
  "$APPROVAL_WORKSPACE_GATE|approval workspace prerequisite gate"; do
  path="${pair%%|*}"; label="${pair#*|}"; require_file "$path" "$label"
done

require_text "$ADAPTER" "StudioDeletionExecutionReadinessService::assess" "adapter delegates canonical readiness capability"
require_text "$WORKSPACE" "StudioDeletionApprovalRecordStore::latest" "workspace reads exact-packet approval"
require_text "$WORKSPACE" "StudioDeletionApprovalRecordStore::latestForOwner" "workspace reads owner-wide approval history"
require_text "$VIEW" 'name="deletion_execution_readiness" value="1"' "workspace is explicitly on demand"
require_text "$VIEW" 'method="get" action="/apps/studio/tools/owner-structure-scan"' "workspace is read-only GET"
require_text "$VIEW" "This workspace cannot apply, archive, delete, or execute the plan." "view declares non-execution boundary"
require_text "$MANIFEST" "StudioDeletionExecutionReadinessService" "manifest registers canonical readiness capability"
require_text "$MANIFEST" "OwnerStructureDeletionExecutionReadinessWorkspaceService" "manifest registers workspace presenter"
require_text "$MANIFEST" "preview.postlude.zzzz-deletion-execution-readiness.php" "manifest registers ordered readiness postlude"
require_no_pattern "$VIEW" 'method="post"|delete_target|archive_target|apply_changes|execute_deletion' "view exposes no mutation or execution path"
require_no_pattern "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace presenter owns no mutation authority"

if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE"; do
    php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"
  done
  php "$PROBE" || fail "execution-readiness workspace behavior probe"
else
  fail "php is required to certify execution-readiness workspace"
fi

bash "$CAPABILITY_GATE" || fail "canonical readiness gate"
bash "$APPROVAL_WORKSPACE_GATE" || fail "approval workspace prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Owner Structure deletion execution readiness workspace FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Owner Structure deletion execution readiness workspace PASS"

#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotReadinessService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotReadinessWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzzzzz-deletion-snapshot-readiness.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_snapshot_readiness_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_snapshot_readiness_capability.sh"
CLAIM_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_execution_claim_workspace.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_owner_structure_deletion_snapshot_readiness_workspace"
for pair in "$ADAPTER|snapshot-readiness adapter" "$WORKSPACE|snapshot-readiness workspace" "$VIEW|snapshot-readiness postlude" "$MANIFEST|Owner Structure manifest" "$PROBE|snapshot-readiness workspace probe" "$CAPABILITY_GATE|canonical snapshot-readiness gate" "$CLAIM_WORKSPACE_GATE|claim workspace prerequisite gate"; do path="${pair%%|*}"; label="${pair#*|}"; req "$path" "$label"; done
text "$ADAPTER" "StudioDeletionSnapshotReadinessService::assess" "adapter delegates canonical snapshot readiness"
text "$WORKSPACE" "StudioDeletionExecutionClaimStore::latest" "workspace reads exact claim evidence"
text "$VIEW" 'name="deletion_snapshot_readiness" value="1"' "workspace is explicitly on demand"
text "$VIEW" 'method="get" action="/apps/studio/tools/owner-structure-scan"' "workspace is read-only GET"
text "$VIEW" "cannot create a snapshot, copy files, edit references, archive files, delete the target, or execute anything" "view declares non-mutation boundary"
text "$MANIFEST" "StudioDeletionSnapshotReadinessService" "manifest registers canonical snapshot readiness"
text "$MANIFEST" "OwnerStructureDeletionSnapshotReadinessWorkspaceService" "manifest registers snapshot workspace"
text "$MANIFEST" "preview.postlude.zzzzzzzzz-deletion-snapshot-readiness.php" "manifest registers ordered snapshot-readiness postlude"
no_pat "$VIEW" 'method="post"|create_snapshot|copy_target|archive_target|delete_target|execute_deletion' "view exposes no snapshot or execution path"
no_pat "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace presenter owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "snapshot-readiness workspace behavior probe"
else fail "php is required to certify snapshot-readiness workspace"; fi
bash "$CAPABILITY_GATE" || fail "canonical snapshot-readiness gate"
bash "$CLAIM_WORKSPACE_GATE" || fail "claim workspace prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Owner Structure deletion snapshot readiness workspace FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Owner Structure deletion snapshot readiness workspace PASS"

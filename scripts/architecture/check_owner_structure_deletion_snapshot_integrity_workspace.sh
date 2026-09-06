#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotIntegrityService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotIntegrityWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzzzzzzz-deletion-snapshot-integrity.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_snapshot_integrity_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_snapshot_integrity_capability.sh"
SNAPSHOT_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_snapshot_workspace.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_owner_structure_deletion_snapshot_integrity_workspace"
for pair in "$ADAPTER|snapshot-integrity adapter" "$WORKSPACE|snapshot-integrity workspace" "$VIEW|snapshot-integrity postlude" "$MANIFEST|Owner Structure manifest" "$PROBE|snapshot-integrity workspace probe" "$CAPABILITY_GATE|canonical integrity gate" "$SNAPSHOT_WORKSPACE_GATE|snapshot workspace prerequisite gate"; do path="${pair%%|*}"; label="${pair#*|}"; req "$path" "$label"; done
text "$ADAPTER" "StudioDeletionSnapshotIntegrityService::assess" "adapter delegates canonical integrity capability"
text "$WORKSPACE" "StudioDeletionExecutionClaimStore::latest" "workspace reads exact claim evidence"
text "$WORKSPACE" "StudioDeletionSnapshotStore::read" "workspace re-reads immutable snapshot manifest"
text "$VIEW" 'name="deletion_snapshot_integrity" value="1"' "workspace is explicitly on demand"
text "$VIEW" 'method="get" action="/apps/studio/tools/owner-structure-scan"' "workspace is GET-only"
text "$VIEW" "does not edit references, archive or remove files, delete the target, or execute the deletion plan" "view declares read-only boundary"
text "$MANIFEST" "StudioDeletionSnapshotIntegrityService" "manifest registers canonical integrity capability"
text "$MANIFEST" "OwnerStructureDeletionSnapshotIntegrityWorkspaceService" "manifest registers integrity workspace"
text "$MANIFEST" "preview.postlude.zzzzzzzzzzz-deletion-snapshot-integrity.php" "manifest registers ordered integrity postlude"
no_pat "$VIEW" 'method="post"|delete_target|archive_target|execute_deletion|apply_changes' "view exposes no mutation or execution path"
no_pat "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace presenter owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "snapshot-integrity workspace behavior probe"
else fail "php is required to certify snapshot-integrity workspace"; fi
bash "$CAPABILITY_GATE" || fail "canonical snapshot-integrity gate"
bash "$SNAPSHOT_WORKSPACE_GATE" || fail "snapshot workspace prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Owner Structure snapshot integrity workspace FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Owner Structure snapshot integrity workspace PASS"

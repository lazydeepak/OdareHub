#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotWorkspaceService.php"
CONTROLLER="apps/Studio/Tools/OwnerStructureScan/Controllers/OwnerStructureDeletionSnapshotController.php"
ROUTE="apps/Studio/Routes/owner_structure_deletion_snapshot_routes.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzzzzzz-deletion-snapshot-creation.php"
BOOTSTRAP="apps/Studio/bootstrap.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_snapshot_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_snapshot_capability.sh"
READINESS_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_snapshot_readiness_workspace.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_owner_structure_deletion_snapshot_workspace"
for pair in "$ADAPTER|snapshot adapter" "$WORKSPACE|snapshot workspace" "$CONTROLLER|snapshot controller" "$ROUTE|snapshot route" "$VIEW|snapshot postlude" "$BOOTSTRAP|Studio bootstrap" "$MANIFEST|Owner Structure manifest" "$PROBE|snapshot workspace probe" "$CAPABILITY_GATE|snapshot capability gate" "$READINESS_WORKSPACE_GATE|snapshot-readiness workspace gate"; do path="${pair%%|*}"; label="${pair#*|}"; req "$path" "$label"; done
text "$ADAPTER" "StudioDeletionSnapshotService::create" "adapter delegates canonical snapshot capability"
text "$CONTROLLER" "StudioDeletionSnapshotReadinessService::assess" "controller recomputes snapshot readiness server side"
text "$CONTROLLER" "Auth::requireCsrf" "snapshot route requires CSRF"
text "$ROUTE" "deletion-snapshot-create" "snapshot POST route is registered"
text "$VIEW" 'method="post" action="/apps/studio/tools/owner-structure-scan/deletion-snapshot-create"' "workspace exposes governed snapshot POST"
text "$VIEW" "does not edit references, archive or remove the source, delete the target, or execute the deletion plan" "view declares bounded snapshot authority"
text "$BOOTSTRAP" "owner_structure_deletion_snapshot_routes.php" "Studio bootstrap loads snapshot route"
text "$MANIFEST" "StudioDeletionSnapshotStore" "manifest registers canonical snapshot store"
text "$MANIFEST" "StudioDeletionSnapshotService" "manifest registers canonical snapshot capability"
text "$MANIFEST" "OwnerStructureDeletionSnapshotWorkspaceService" "manifest registers snapshot workspace"
text "$MANIFEST" "preview.postlude.zzzzzzzzzz-deletion-snapshot-creation.php" "manifest registers ordered snapshot postlude"
no_pat "$CONTROLLER" 'delete_target|archive_target|remove_blocking_reference|cleanup_reference|unlink\s*\(|rmdir\s*\(' "snapshot controller owns no deletion-plan or cleanup authority"
no_pat "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(' "snapshot workspace presenter owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$CONTROLLER" "$ROUTE" "$VIEW" "$MANIFEST" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "snapshot workspace behavior probe"
else fail "php is required to certify snapshot workspace"; fi
bash "$CAPABILITY_GATE" || fail "snapshot capability gate"
bash "$READINESS_WORKSPACE_GATE" || fail "snapshot-readiness workspace prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Owner Structure deletion snapshot workspace FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Owner Structure deletion snapshot workspace PASS"

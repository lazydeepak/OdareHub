#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationReadinessService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzzzzzzzz-deletion-reference-remediation-readiness.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_reference_remediation_readiness_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_reference_remediation_readiness_capability.sh"
INTEGRITY_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_snapshot_integrity_workspace.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_owner_structure_deletion_reference_remediation_readiness_workspace"
for pair in "$ADAPTER|reference-remediation adapter" "$WORKSPACE|reference-remediation workspace" "$VIEW|reference-remediation postlude" "$MANIFEST|Owner Structure manifest" "$PROBE|workspace probe" "$CAPABILITY_GATE|canonical capability gate" "$INTEGRITY_WORKSPACE_GATE|snapshot-integrity workspace gate"; do path="${pair%%|*}"; label="${pair#*|}"; req "$path" "$label"; done
text "$ADAPTER" "StudioDeletionReferenceRemediationReadinessService::assess" "adapter delegates canonical capability"
text "$VIEW" 'name="deletion_reference_remediation_readiness" value="1"' "workspace is explicitly on demand"
text "$VIEW" 'method="get" action="/apps/studio/tools/owner-structure-scan"' "workspace uses read-only GET"
text "$VIEW" "does not edit files, apply patches, archive content, delete the target, or execute the plan" "view declares non-mutation boundary"
text "$MANIFEST" "StudioDeletionReferenceRemediationReadinessService" "manifest registers canonical capability"
text "$MANIFEST" "OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService" "manifest registers workspace presenter"
text "$MANIFEST" "preview.postlude.zzzzzzzzzzzz-deletion-reference-remediation-readiness.php" "manifest registers ordered postlude"
no_pat "$VIEW" 'method="post"|apply_patch|delete_target|archive_target' "view exposes no mutation route"
no_pat "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "reference-remediation workspace behavior probe"
else fail "php is required to certify reference-remediation workspace"; fi
bash "$CAPABILITY_GATE" || fail "canonical capability gate"
bash "$INTEGRITY_WORKSPACE_GATE" || fail "snapshot-integrity workspace prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Owner Structure reference-remediation readiness workspace FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Owner Structure reference-remediation readiness workspace PASS"

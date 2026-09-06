#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationPatchProposalService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzzzzzzzzzz-deletion-reference-patch-proposal.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_reference_remediation_patch_proposal_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_reference_remediation_patch_proposal_capability.sh"
READINESS_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_reference_remediation_readiness_workspace.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]]&&ok "$2"||fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1"&&ok "$3"||fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1"&&fail "$3"||ok "$3"; }
echo "[architecture] check_owner_structure_deletion_reference_remediation_patch_proposal_workspace"
for pair in "$ADAPTER|patch proposal adapter" "$WORKSPACE|patch proposal workspace" "$VIEW|patch proposal postlude" "$MANIFEST|Owner Structure manifest" "$PROBE|workspace probe" "$CAPABILITY_GATE|capability gate" "$READINESS_WORKSPACE_GATE|readiness workspace prerequisite gate";do path="${pair%%|*}";label="${pair#*|}";req "$path" "$label";done
text "$ADAPTER" "StudioDeletionReferenceRemediationPatchProposalService::propose" "adapter delegates canonical proposal capability"
text "$VIEW" 'name="deletion_reference_patch_proposal" value="1"' "workspace is explicitly on demand"
text "$VIEW" 'method="get" action="/apps/studio/tools/owner-structure-scan"' "workspace is GET only"
text "$VIEW" "does not edit files, apply patches, archive content, delete the target, or execute the plan" "view declares non-mutation boundary"
text "$MANIFEST" "StudioDeletionReferenceRemediationPatchProposalService" "manifest registers canonical proposal capability"
text "$MANIFEST" "OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService" "manifest registers proposal workspace"
text "$MANIFEST" "preview.postlude.zzzzzzzzzzzzz-deletion-reference-patch-proposal.php" "manifest registers ordered proposal postlude"
no_pat "$VIEW" 'method="post"|apply_patch|write_file|delete_target|archive_target' "view exposes no patch apply or execution path"
if command -v php >/dev/null 2>&1;then for file in "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE";do php -l "$file" >/dev/null&&ok "PHP lint: $file"||fail "PHP lint: $file";done;php "$PROBE"||fail "workspace behavior probe";else fail "php is required to certify proposal workspace";fi
bash "$CAPABILITY_GATE"||fail "proposal capability gate";bash "$READINESS_WORKSPACE_GATE"||fail "readiness workspace prerequisite gate"
if [[ "$failures" -ne 0 ]];then echo "[architecture] Owner Structure reference patch proposal workspace FAILED ($failures issue(s))" >&2;exit 1;fi
echo "[architecture] Owner Structure reference patch proposal workspace PASS"

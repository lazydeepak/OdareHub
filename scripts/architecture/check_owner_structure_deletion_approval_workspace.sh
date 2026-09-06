#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

BOOTSTRAP="apps/Studio/bootstrap.php"
ROUTES="apps/Studio/Routes/owner_structure_deletion_approval_routes.php"
CONTROLLER="apps/Studio/Tools/OwnerStructureScan/Controllers/OwnerStructureDeletionApprovalController.php"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionApprovalRecordService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionApprovalWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzz-deletion-approval.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_approval_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_approval_record_capability.sh"
CHANGE_SET_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_change_set_workspace.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_owner_structure_deletion_approval_workspace"

for pair in \
  "$BOOTSTRAP|Studio bootstrap" \
  "$ROUTES|approval route registration" \
  "$CONTROLLER|approval controller" \
  "$ADAPTER|approval adapter" \
  "$WORKSPACE|approval workspace presenter" \
  "$VIEW|approval postlude" \
  "$MANIFEST|Owner Structure manifest" \
  "$PROBE|approval workspace probe" \
  "$CAPABILITY_GATE|canonical approval gate" \
  "$CHANGE_SET_WORKSPACE_GATE|change-set workspace prerequisite gate"; do
  path="${pair%%|*}"; label="${pair#*|}"; require_file "$path" "$label"
done

require_text "$BOOTSTRAP" "owner_structure_deletion_approval_routes.php" "Studio bootstrap loads isolated route extension"
require_text "$ROUTES" "->post('/apps/studio/tools/owner-structure-scan/deletion-approval-record'" "approval recording is POST-only"
require_text "$ROUTES" "StudioToolInstancePolicyService::isEnabled('owner_structure_scan')" "route respects tool lifecycle"
require_text "$CONTROLLER" "Auth::requireCsrf" "controller enforces CSRF"
require_text "$CONTROLLER" "PlatformAuthority::resolveCurrentActor" "controller resolves current actor"
require_text "$CONTROLLER" "PlatformAuthority::canManageOwnerWorkspace" "controller enforces platform authority"
require_text "$CONTROLLER" "StudioDeletionChangeSetService::build" "controller recomputes current server-side change set"
require_text "$CONTROLLER" "OwnerStructureDeletionApprovalRecordService::record" "controller delegates approval recording"
require_text "$ADAPTER" "StudioDeletionApprovalRecordService::record" "tool adapter delegates canonical capability"
require_text "$WORKSPACE" "OwnerStructureDeletionApprovalRecordService::latest" "workspace reads exact-packet approval history"
require_text "$VIEW" 'method="post" action="/apps/studio/tools/owner-structure-scan/deletion-approval-record"' "view posts only to governed record endpoint"
require_text "$VIEW" 'name="change_set_fingerprint"' "view submits exact change-set fingerprint"
require_text "$VIEW" 'name="plan_fingerprint"' "view submits exact plan fingerprint"
require_text "$VIEW" 'name="decision" value="approved"' "view supports approval decision"
require_text "$VIEW" 'name="decision" value="rejected"' "view supports rejection decision"
require_text "$VIEW" "Approval does not apply changes" "view declares non-execution boundary"
require_text "$MANIFEST" "StudioDeletionApprovalRecordService" "manifest registers canonical approval capability"
require_text "$MANIFEST" "OwnerStructureDeletionApprovalController" "manifest registers approval controller"
require_text "$MANIFEST" "preview.postlude.zzz-deletion-approval.php" "manifest registers ordered approval postlude"
require_text "$MANIFEST" "/apps/studio/tools/owner-structure-scan/deletion-approval-record" "manifest registers approval route"
require_no_pattern "$VIEW" 'delete_target|archive_target|apply_changes|execute_deletion|method="get"[^>]*deletion-approval-record' "view exposes no deletion, apply, or state-changing GET path"
require_no_pattern "$CONTROLLER" 'unlink\s*\(|rmdir\s*\(|rename\s*\(|file_put_contents|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "controller owns no persistence or deletion implementation"
require_no_pattern "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace presenter owns no mutation authority"

if command -v php >/dev/null 2>&1; then
  for file in "$BOOTSTRAP" "$ROUTES" "$CONTROLLER" "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE"; do
    php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"
  done
  php "$PROBE" || fail "approval workspace behavior probe"
else
  fail "php is required to certify approval workspace"
fi

bash "$CAPABILITY_GATE" || fail "canonical approval record gate"
bash "$CHANGE_SET_WORKSPACE_GATE" || fail "change-set workspace prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Owner Structure deletion approval workspace FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Owner Structure deletion approval workspace PASS"

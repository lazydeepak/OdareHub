#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionDryRunService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionDryRunWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zzzzz-deletion-execution-dry-run.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_execution_dry_run_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_execution_dry_run_capability.sh"
READINESS_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_execution_readiness_workspace.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_owner_structure_deletion_execution_dry_run_workspace"

for pair in \
  "$ADAPTER|deletion execution dry-run adapter" \
  "$WORKSPACE|deletion execution dry-run workspace presenter" \
  "$VIEW|deletion execution dry-run postlude" \
  "$MANIFEST|Owner Structure manifest" \
  "$PROBE|deletion execution dry-run workspace probe" \
  "$CAPABILITY_GATE|canonical dry-run gate" \
  "$READINESS_WORKSPACE_GATE|execution-readiness workspace prerequisite gate"; do
  path="${pair%%|*}"; label="${pair#*|}"; require_file "$path" "$label"
done

require_text "$ADAPTER" "StudioDeletionExecutionDryRunService::simulate" "adapter delegates canonical dry-run capability"
require_text "$WORKSPACE" "OwnerStructureDeletionExecutionDryRunService::simulate" "workspace delegates through tool adapter"
require_text "$VIEW" 'name="deletion_execution_dry_run" value="1"' "dry-run workspace is explicitly on demand"
require_text "$VIEW" 'method="get" action="/apps/studio/tools/owner-structure-scan"' "dry-run workspace is read-only GET"
require_text "$VIEW" "No file is written, archived, changed, or deleted" "view declares non-mutation boundary"
require_text "$MANIFEST" "StudioDeletionExecutionDryRunService" "manifest registers canonical dry-run capability"
require_text "$MANIFEST" "OwnerStructureDeletionExecutionDryRunWorkspaceService" "manifest registers dry-run workspace presenter"
require_text "$MANIFEST" "preview.postlude.zzzzz-deletion-execution-dry-run.php" "manifest registers ordered dry-run postlude"
require_no_pattern "$VIEW" 'method="post"|delete_target|archive_target|apply_changes|execute_deletion' "view exposes no mutation or execution path"
require_no_pattern "$WORKSPACE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace presenter owns no mutation authority"

if command -v php >/dev/null 2>&1; then
  for file in "$ADAPTER" "$WORKSPACE" "$VIEW" "$MANIFEST" "$PROBE"; do
    php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"
  done
  php "$PROBE" || fail "deletion execution dry-run workspace behavior probe"
else
  fail "php is required to certify deletion execution dry-run workspace"
fi

bash "$CAPABILITY_GATE" || fail "canonical dry-run gate"
bash "$READINESS_WORKSPACE_GATE" || fail "execution-readiness workspace prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Owner Structure deletion execution dry-run workspace FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Owner Structure deletion execution dry-run workspace PASS"

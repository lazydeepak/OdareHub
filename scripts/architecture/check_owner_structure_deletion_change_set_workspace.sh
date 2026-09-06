#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

HOST="apps/Studio/Views/pages/tool_placeholder.php"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionChangeSetService.php"
WORKSPACE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionChangeSetWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.zz-deletion-change-set.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_change_set_workspace.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_change_set_capability.sh"
PLAN_WORKSPACE_GATE="scripts/architecture/check_owner_structure_deletion_plan_workspace.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_owner_structure_deletion_change_set_workspace"

require_file "$HOST" "composable Studio tool host"
require_file "$ADAPTER" "Owner Structure deletion change-set adapter"
require_file "$WORKSPACE" "Owner Structure deletion change-set workspace service"
require_file "$VIEW" "Owner Structure deletion change-set postlude"
require_file "$MANIFEST" "Owner Structure manifest"
require_file "$PROBE" "deletion change-set workspace probe"
require_file "$CAPABILITY_GATE" "canonical deletion change-set gate"
require_file "$PLAN_WORKSPACE_GATE" "deletion plan workspace prerequisite gate"
require_text "$HOST" "sort(\$additionalPostludes" "tool postludes load deterministically"
require_text "$ADAPTER" "StudioDeletionChangeSetService::compose" "adapter reuses the current deletion plan"
require_text "$WORKSPACE" "OwnerStructureDeletionChangeSetService::build" "workspace delegates to adapter"
require_text "$VIEW" "OwnerStructureDeletionChangeSetWorkspaceService::build" "view invokes workspace adapter"
require_text "$VIEW" "\$planWorkspace['plan']" "workspace consumes the current deletion plan"
require_text "$VIEW" "name=\"deletion_change_set\"" "review packet is explicitly on demand"
require_text "$VIEW" "name=\"deletion_plan\" value=\"1\"" "review packet preserves plan prerequisite"
require_text "$VIEW" "method=\"get\"" "workspace remains GET-only"
require_text "$VIEW" "Review packet only." "view declares non-executable review boundary"
require_text "$MANIFEST" "StudioDeletionChangeSetService" "manifest registers canonical change-set capability"
require_text "$MANIFEST" "OwnerStructureDeletionChangeSetWorkspaceService" "manifest registers workspace service"
require_text "$MANIFEST" "preview.postlude.zz-deletion-change-set.php" "manifest registers ordered review postlude"
require_no_pattern "$VIEW" '<form[^>]+method="post"|StudioDeletionChangeSetService::|OwnerStructureDeletionChangeSetService::build|file_put_contents|unlink\s*\(|rename\s*\(' "view contains no mutation or domain-service bypass"
require_no_pattern "$WORKSPACE" 'file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "workspace contains no mutation authority"

if command -v php >/dev/null 2>&1; then
  php -l "$ADAPTER" >/dev/null && ok "adapter PHP lint" || fail "adapter PHP lint"
  php -l "$WORKSPACE" >/dev/null && ok "workspace PHP lint" || fail "workspace PHP lint"
  php -l "$VIEW" >/dev/null && ok "view PHP lint" || fail "view PHP lint"
  php -l "$MANIFEST" >/dev/null && ok "manifest PHP lint" || fail "manifest PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "deletion change-set workspace behavior probe"
else
  fail "php is required to certify deletion change-set workspace"
fi

bash "$CAPABILITY_GATE" || fail "canonical deletion change-set gate"
bash "$PLAN_WORKSPACE_GATE" || fail "deletion plan workspace prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Owner Structure deletion change-set workspace FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Owner Structure deletion change-set workspace PASS"

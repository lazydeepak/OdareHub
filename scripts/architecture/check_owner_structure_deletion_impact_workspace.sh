#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

HOST="apps/Studio/Views/pages/tool_placeholder.php"
RENDERER="apps/Studio/Views/pages/tool_placeholder_renderer.php"
SERVICE="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionImpactWorkspaceService.php"
VIEW="apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_owner_structure_deletion_impact_workspace.php"
NAV_PROBE="apps/Studio/tests/probe_tool_placeholder_nav.php"
CAPABILITY_GATE="scripts/architecture/check_studio_deletion_impact_discovery_capability.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_owner_structure_deletion_impact_workspace"

require_file "$HOST" "tool host wrapper exists"
require_file "$RENDERER" "unchanged tool host renderer exists"
require_file "$SERVICE" "deletion impact workspace service exists"
require_file "$VIEW" "deletion impact postlude view exists"
require_file "$MANIFEST" "Owner Structure manifest exists"
require_file "$PROBE" "workspace probe exists"
require_file "$NAV_PROBE" "tool placeholder navigation probe exists"
require_file "$CAPABILITY_GATE" "deletion impact capability gate exists"

require_text "$HOST" "tool_placeholder_renderer.php" "host delegates existing rendering"
require_text "$HOST" ".postlude.php" "host supports optional tool-owned postlude"
require_text "$HOST" "report_designer" "host preserves placeholder identity compatibility"
require_text "$SERVICE" "OwnerStructureDeletionImpactService::assess" "workspace composes canonical deletion impact adapter"
require_text "$SERVICE" "public static function build" "workspace exposes stable build boundary"
require_text "$VIEW" "OwnerStructureDeletionImpactWorkspaceService::build" "postlude invokes workspace adapter"
require_text "$VIEW" "deletion_impact" "assessment is explicitly on demand"
require_text "$VIEW" "method=\"get\"" "workspace uses read-only GET request"
require_text "$MANIFEST" "OwnerStructureDeletionImpactWorkspaceService" "manifest registers workspace service"
require_text "$MANIFEST" "preview.postlude.php" "manifest registers postlude view"

require_no_pattern "$VIEW" '<form[^>]+method="post"|OwnerStructureDeletionImpactService::assess|StudioDeletionImpactDiscoveryService::discover' "view contains no mutation form or domain-service bypass"
require_no_pattern "$SERVICE" 'file_put_contents|unlink\(|rename\(|mkdir\(|rmdir\(|PDO|INSERT|UPDATE|DELETE FROM' "workspace service contains no mutation authority"

bash "$CAPABILITY_GATE" || fail "canonical deletion impact capability gate"

if command -v php >/dev/null 2>&1; then
  php -l "$HOST" >/dev/null && ok "host PHP lint" || fail "host PHP lint"
  php -l "$RENDERER" >/dev/null && ok "renderer PHP lint" || fail "renderer PHP lint"
  php -l "$SERVICE" >/dev/null && ok "workspace service PHP lint" || fail "workspace service PHP lint"
  php -l "$VIEW" >/dev/null && ok "postlude view PHP lint" || fail "postlude view PHP lint"
  php -l "$MANIFEST" >/dev/null && ok "manifest PHP lint" || fail "manifest PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php -l "$NAV_PROBE" >/dev/null && ok "navigation probe PHP lint" || fail "navigation probe PHP lint"
  php "$PROBE" || fail "workspace behavior probe"
  php "$NAV_PROBE" || fail "tool placeholder navigation regression probe"
else
  fail "php is required to certify deletion impact workspace"
fi

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Owner Structure deletion impact workspace FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Owner Structure deletion impact workspace PASS"

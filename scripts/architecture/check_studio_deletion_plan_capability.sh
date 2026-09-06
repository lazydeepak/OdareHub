#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

SERVICE="apps/Studio/Services/StudioDeletionPlanService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_plan_capability.php"
IMPACT_GATE="scripts/architecture/check_studio_deletion_impact_discovery_capability.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_studio_deletion_plan_capability"

require_file "$SERVICE" "canonical deletion plan service"
require_file "$PROBE" "deletion plan capability probe"
require_file "$IMPACT_GATE" "deletion impact prerequisite gate"
require_text "$SERVICE" "final class StudioDeletionPlanService" "canonical service class exists"
require_text "$SERVICE" "public const EFFECT = 'plan'" "capability declares plan effect"
require_text "$SERVICE" "public static function plan" "capability exposes direct planning"
require_text "$SERVICE" "public static function compose" "capability composes existing impact evidence"
require_text "$SERVICE" "StudioDeletionImpactDiscoveryService::discover" "capability composes deletion impact discovery"
require_text "$SERVICE" "'can_execute' => 'no'" "V1 is explicitly non-executable"
require_text "$SERVICE" "'requires_approval' => 'yes'" "approval requirement is explicit"
require_text "$SERVICE" "'requires_snapshot' => 'yes'" "snapshot requirement is explicit"
require_text "$SERVICE" "verify_no_inbound_references" "post-deletion reference verification exists"
require_text "$SERVICE" "verify_owner_unregistered" "owner removal verification exists"
require_no_pattern "$SERVICE" 'file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "capability contains no mutation authority"

if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "deletion plan behavior probe"
else
  fail "php is required to certify deletion planning"
fi

bash "$IMPACT_GATE" || fail "deletion impact prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio deletion plan capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio deletion plan capability PASS"

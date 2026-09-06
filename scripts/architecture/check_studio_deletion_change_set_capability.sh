#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

SERVICE="apps/Studio/Services/StudioDeletionChangeSetService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_change_set_capability.php"
PLAN_GATE="scripts/architecture/check_studio_deletion_plan_capability.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_studio_deletion_change_set_capability"

require_file "$SERVICE" "canonical deletion change-set service"
require_file "$PROBE" "deletion change-set capability probe"
require_file "$PLAN_GATE" "deletion plan prerequisite gate"
require_text "$SERVICE" "final class StudioDeletionChangeSetService" "canonical service class exists"
require_text "$SERVICE" "public const EFFECT = 'plan'" "capability declares plan effect"
require_text "$SERVICE" "public static function build" "capability exposes direct build"
require_text "$SERVICE" "public static function compose" "capability composes existing deletion plan"
require_text "$SERVICE" "StudioDeletionPlanService::plan" "capability composes canonical deletion plan"
require_text "$SERVICE" "'immutable' => 'yes'" "packet declares immutable review boundary"
require_text "$SERVICE" "'can_execute' => 'no'" "packet is non-executable"
require_text "$SERVICE" "'can_apply' => 'no'" "packet cannot apply changes"
require_text "$SERVICE" "'requires_approval' => 'yes'" "approval requirement is explicit"
require_text "$SERVICE" "'requires_snapshot' => 'yes'" "snapshot requirement is explicit"
require_text "$SERVICE" "'source_plan_fingerprint'" "snapshot and integrity contracts bind to plan fingerprint"
require_text "$SERVICE" "'recompute_before_approval' => 'yes'" "fingerprint must be recomputed before approval"
require_text "$SERVICE" "'reject_on_mismatch' => 'yes'" "fingerprint mismatch fails closed"
require_no_pattern "$SERVICE" 'file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "capability contains no mutation authority"

if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "deletion change-set behavior probe"
else
  fail "php is required to certify deletion change-set generation"
fi

bash "$PLAN_GATE" || fail "deletion plan prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio deletion change-set capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio deletion change-set capability PASS"

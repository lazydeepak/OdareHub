#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

SERVICE="apps/Studio/Services/StudioDeletionExecutionDryRunService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_execution_dry_run_capability.php"
READINESS_GATE="scripts/architecture/check_studio_deletion_execution_readiness_capability.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_studio_deletion_execution_dry_run_capability"

require_file "$SERVICE" "canonical deletion execution dry-run capability"
require_file "$PROBE" "deletion execution dry-run probe"
require_file "$READINESS_GATE" "execution-readiness prerequisite gate"
require_text "$SERVICE" "public const EFFECT = 'simulate'" "dry run declares simulate effect"
require_text "$SERVICE" "STATE_READY = 'ready'" "ready state is explicit"
require_text "$SERVICE" "STATE_BLOCKED = 'blocked'" "blocked state is explicit"
require_text "$SERVICE" "STATE_STALE = 'stale'" "stale state is explicit"
require_text "$SERVICE" "would_execute' => 'no'" "dry run executes nothing"
require_text "$SERVICE" "would_write' => 'no'" "dry run writes nothing"
require_text "$SERVICE" "would_archive' => 'no'" "dry run archives nothing"
require_text "$SERVICE" "would_delete' => 'no'" "dry run deletes nothing"
require_text "$SERVICE" "grants_execution_authority' => 'no'" "dry run grants no execution authority"
require_text "$SERVICE" "deletion-execution-snapshots" "snapshot destination is Studio confined"
require_text "$SERVICE" "OPERATION_DEPENDENCY_ORDER_INVALID" "operation dependency order is validated"
require_text "$SERVICE" "UNSUPPORTED_OPERATION:" "unsupported operations fail closed"
require_no_pattern "$SERVICE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "dry-run capability owns no mutation implementation"

if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "deletion execution dry-run behavior probe"
else
  fail "php is required to certify deletion execution dry run"
fi

bash "$READINESS_GATE" || fail "execution-readiness prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio deletion execution dry-run capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio deletion execution dry-run capability PASS"

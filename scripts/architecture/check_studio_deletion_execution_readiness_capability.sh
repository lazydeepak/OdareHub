#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

SERVICE="apps/Studio/Services/StudioDeletionExecutionReadinessService.php"
STORE="apps/Studio/Services/StudioDeletionApprovalRecordStore.php"
PROBE="apps/Studio/tests/probe_studio_deletion_execution_readiness_capability.php"
APPROVAL_GATE="scripts/architecture/check_studio_deletion_approval_record_capability.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_studio_deletion_execution_readiness_capability"

require_file "$SERVICE" "canonical execution-readiness capability"
require_file "$STORE" "approval provenance store"
require_file "$PROBE" "execution-readiness capability probe"
require_file "$APPROVAL_GATE" "approval prerequisite gate"
require_text "$SERVICE" "public const EFFECT = 'verify'" "readiness capability declares verify effect"
require_text "$SERVICE" "STATE_READY = 'ready'" "ready state is explicit"
require_text "$SERVICE" "STATE_NOT_APPROVED = 'not_approved'" "not-approved state is explicit"
require_text "$SERVICE" "STATE_REJECTED = 'rejected'" "rejected state is explicit"
require_text "$SERVICE" "STATE_STALE = 'stale'" "stale state is explicit"
require_text "$SERVICE" "STATE_BLOCKED = 'blocked'" "blocked state is explicit"
require_text "$SERVICE" "APPROVAL_RECORD_FINGERPRINT_INVALID" "approval integrity is verified"
require_text "$SERVICE" "requires_separate_execution_capability' => 'yes'" "readiness grants no execution capability"
require_text "$SERVICE" "grants_execution_authority' => 'no'" "readiness grants no authority"
require_text "$STORE" "public static function latestForOwner" "store supports owner-wide stale approval lookup"
require_no_pattern "$SERVICE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "readiness capability owns no mutation authority"

if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$STORE" >/dev/null && ok "store PHP lint" || fail "store PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "execution-readiness behavior probe"
else
  fail "php is required to certify execution readiness"
fi

bash "$APPROVAL_GATE" || fail "approval record prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio deletion execution readiness capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio deletion execution readiness capability PASS"

#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

STORE="apps/Studio/Services/StudioDeletionExecutionRequestStore.php"
SERVICE="apps/Studio/Services/StudioDeletionExecutionRequestService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_execution_request_capability.php"
DRY_RUN_GATE="scripts/architecture/check_studio_deletion_execution_dry_run_capability.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_studio_deletion_execution_request_capability"

require_file "$STORE" "execution request provenance store"
require_file "$SERVICE" "canonical execution request capability"
require_file "$PROBE" "execution request capability probe"
require_file "$DRY_RUN_GATE" "dry-run prerequisite gate"
require_text "$SERVICE" "public const EFFECT = 'mutate'" "request declares provenance mutation effect"
require_text "$SERVICE" "MIN_TTL_SECONDS = 300" "minimum request lifetime is explicit"
require_text "$SERVICE" "MAX_TTL_SECONDS = 86400" "maximum request lifetime is explicit"
require_text "$SERVICE" "executor_must_differ_from_requester' => 'yes'" "request enforces separation of duties"
require_text "$SERVICE" "required_authority_role' => 'platform_admin'" "executor authority policy is explicit"
require_text "$SERVICE" "execution_authorized' => 'no'" "request does not authorize execution"
require_text "$SERVICE" "grants_execution_authority' => 'no'" "request grants no execution authority"
require_text "$SERVICE" "requires_separate_execution_capability' => 'yes'" "separate execution capability remains required"
require_text "$STORE" "deletion-execution-requests" "request storage is Studio confined"
require_text "$STORE" "public static function latestForOwner" "owner request history is queryable"
require_no_pattern "$SERVICE" 'delete_target|archive_target|copy\s*\(|rename\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "request capability owns no execution authority"

if command -v php >/dev/null 2>&1; then
  php -l "$STORE" >/dev/null && ok "store PHP lint" || fail "store PHP lint"
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "execution request behavior probe"
else
  fail "php is required to certify execution request capability"
fi

bash "$DRY_RUN_GATE" || fail "dry-run prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio deletion execution request capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio deletion execution request capability PASS"

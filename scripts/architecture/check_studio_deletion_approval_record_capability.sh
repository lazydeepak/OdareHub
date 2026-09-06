#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

SERVICE="apps/Studio/Services/StudioDeletionApprovalRecordService.php"
STORE="apps/Studio/Services/StudioDeletionApprovalRecordStore.php"
PROBE="apps/Studio/tests/probe_studio_deletion_approval_record_capability.php"
CHANGE_SET_GATE="scripts/architecture/check_studio_deletion_change_set_capability.sh"

failures=0
fail() { echo "  fail: $1" >&2; failures=$((failures + 1)); }
ok() { echo "  ok: $1"; }
require_file() { [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
require_text() { grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
require_no_pattern() { grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }

echo "[architecture] check_studio_deletion_approval_record_capability"

require_file "$SERVICE" "canonical approval record capability"
require_file "$STORE" "append-only approval record store"
require_file "$PROBE" "approval record capability probe"
require_file "$CHANGE_SET_GATE" "deletion change-set prerequisite gate"
require_text "$SERVICE" "public const EFFECT = 'mutate'" "approval record declares mutation effect"
require_text "$SERVICE" "DECISION_APPROVED = 'approved'" "approval decision is explicit"
require_text "$SERVICE" "DECISION_REJECTED = 'rejected'" "rejection decision is explicit"
require_text "$SERVICE" "DELETION_APPROVAL_CHANGE_SET_STALE" "stale change sets are rejected"
require_text "$SERVICE" "DELETION_APPROVAL_PLAN_STALE" "stale plans are rejected"
require_text "$SERVICE" "DELETION_APPROVAL_CONFIRMATIONS_MISSING" "required confirmations are enforced"
require_text "$SERVICE" "grants_execution_authority' => 'no'" "approval record grants no execution authority"
require_text "$SERVICE" "StudioDeletionApprovalRecordStore::append" "capability delegates persistence to append-only store"
require_text "$STORE" "storage' . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR . 'deletion-approvals'" "store is confined to Studio provenance path"
require_text "$STORE" "fopen(\$path, 'x+b')" "store uses exclusive immutable creation"
require_text "$STORE" "'append_only' => 'yes'" "store declares append-only evidence"
require_no_pattern "$SERVICE" 'delete_target|archive_target|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "approval capability owns no deletion or database execution"
require_no_pattern "$STORE" 'apps/|modules/|plugins/|public/|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "store does not target owner or database paths"

if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$STORE" >/dev/null && ok "store PHP lint" || fail "store PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "approval record behavior probe"
else
  fail "php is required to certify approval recording"
fi

bash "$CHANGE_SET_GATE" || fail "deletion change-set prerequisite gate"

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio deletion approval record capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio deletion approval record capability PASS"

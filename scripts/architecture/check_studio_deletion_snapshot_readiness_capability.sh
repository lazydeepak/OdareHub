#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
SERVICE="apps/Studio/Services/StudioDeletionSnapshotReadinessService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_snapshot_readiness_capability.php"
CLAIM_GATE="scripts/architecture/check_studio_deletion_execution_claim_capability.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_studio_deletion_snapshot_readiness_capability"
req "$SERVICE" "canonical snapshot-readiness capability"
req "$PROBE" "snapshot-readiness capability probe"
req "$CLAIM_GATE" "execution-claim prerequisite gate"
text "$SERVICE" "public const EFFECT = 'verify'" "snapshot readiness declares verify effect"
text "$SERVICE" "STATE_NOT_CLAIMED = 'not_claimed'" "not-claimed state is explicit"
text "$SERVICE" "STATE_SNAPSHOT_EXISTS = 'snapshot_exists'" "existing-snapshot state is explicit"
text "$SERVICE" "claim_fingerprint" "claim fingerprint is validated"
text "$SERVICE" "overwrite_allowed'=>'no'" "snapshot overwrite is prohibited"
text "$SERVICE" "requires_separate_snapshot_capability'=>'yes'" "separate snapshot capability remains required"
text "$SERVICE" "grants_execution_authority'=>'no'" "snapshot readiness grants no execution authority"
no_pat "$SERVICE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "snapshot readiness owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "snapshot-readiness behavior probe"
else fail "php is required to certify snapshot readiness"; fi
bash "$CLAIM_GATE" || fail "execution-claim prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Studio deletion snapshot readiness capability FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Studio deletion snapshot readiness capability PASS"

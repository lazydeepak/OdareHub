#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
SERVICE="apps/Studio/Services/StudioDeletionSnapshotIntegrityService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_snapshot_integrity_capability.php"
SNAPSHOT_GATE="scripts/architecture/check_studio_deletion_snapshot_capability.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_studio_deletion_snapshot_integrity_capability"
req "$SERVICE" "canonical snapshot-integrity capability"
req "$PROBE" "snapshot-integrity capability probe"
req "$SNAPSHOT_GATE" "snapshot-creation prerequisite gate"
text "$SERVICE" "public const EFFECT = 'verify'" "integrity capability declares verify effect"
text "$SERVICE" "STATE_CHECKSUM_MISMATCH = 'checksum_mismatch'" "checksum mismatch state is explicit"
text "$SERVICE" "STATE_SOURCE_CHANGED = 'source_changed'" "source-changed state is explicit"
text "$SERVICE" "post_snapshot_execution_ready' => \$ready ? 'yes' : 'no'" "post-snapshot readiness is explicit"
text "$SERVICE" "requires_separate_execution_capability' => 'yes'" "integrity capability requires separate execution authority"
text "$SERVICE" "grants_execution_authority' => 'no'" "integrity capability grants no execution authority"
text "$SERVICE" "MANIFEST_FINGERPRINT_INVALID" "manifest fingerprint is verified"
text "$SERVICE" "SNAPSHOT_TREE_CHECKSUM_MISMATCH" "snapshot tree checksum is verified"
text "$SERVICE" "SOURCE_FILE_CHANGED:" "live source is compared to snapshot"
no_pat "$SERVICE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "integrity capability owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "snapshot-integrity behavior probe"
else fail "php is required to certify snapshot integrity"; fi
bash "$SNAPSHOT_GATE" || fail "snapshot-creation prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Studio deletion snapshot integrity capability FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Studio deletion snapshot integrity capability PASS"

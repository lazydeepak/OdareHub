#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
SERVICE="apps/Studio/Services/StudioDeletionSnapshotService.php"
STORE="apps/Studio/Services/StudioDeletionSnapshotStore.php"
PROBE="apps/Studio/tests/probe_studio_deletion_snapshot_capability.php"
READINESS_GATE="scripts/architecture/check_studio_deletion_snapshot_readiness_capability.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_studio_deletion_snapshot_capability"
req "$SERVICE" "canonical snapshot capability"
req "$STORE" "exclusive snapshot store"
req "$PROBE" "snapshot capability probe"
req "$READINESS_GATE" "snapshot-readiness prerequisite gate"
text "$SERVICE" "public const EFFECT = 'mutate'" "snapshot capability declares mutation effect"
text "$SERVICE" "snapshot_is_execution_authority' => 'no'" "snapshot is not execution authority"
text "$SERVICE" "grants_execution_authority' => 'no'" "snapshot grants no execution authority"
text "$STORE" "MANIFEST_VERSION = 'studio.deletion-snapshot-manifest.v1'" "immutable manifest version is explicit"
text "$STORE" "snapshot-lock" "snapshot store uses exclusive destination lock"
text "$STORE" "'.tmp-'" "snapshot store uses staging directory"
text "$STORE" "rename(\$stagingPath, \$destinationAbsolute)" "snapshot store atomically publishes staging directory"
text "$STORE" "hash_file('sha256'" "snapshot store verifies SHA-256 checksums"
text "$STORE" "overwrite_allowed' => 'no'" "snapshot overwrite is prohibited"
text "$STORE" "deletion-execution-snapshots/" "writes are confined to Studio snapshot storage"
no_pat "$SERVICE" 'delete_target|archive_target|remove_blocking_reference|cleanup_reference|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "snapshot capability owns no deletion-plan or database authority"
if command -v php >/dev/null 2>&1; then
  for file in "$SERVICE" "$STORE" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "snapshot capability behavior probe"
else fail "php is required to certify snapshot capability"; fi
bash "$READINESS_GATE" || fail "snapshot-readiness prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Studio deletion snapshot capability FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Studio deletion snapshot capability PASS"

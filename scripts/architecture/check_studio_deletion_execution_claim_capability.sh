#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
SERVICE="apps/Studio/Services/StudioDeletionExecutionClaimService.php"
STORE="apps/Studio/Services/StudioDeletionExecutionClaimStore.php"
USE_STORE="apps/Studio/Services/StudioDeletionExecutionRequestUseEvidenceStore.php"
PROBE="apps/Studio/tests/probe_studio_deletion_execution_claim_capability.php"
READINESS_GATE="scripts/architecture/check_studio_deletion_executor_readiness_capability.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_studio_deletion_execution_claim_capability"
req "$SERVICE" "canonical execution claim capability"
req "$STORE" "atomic claim store"
req "$USE_STORE" "single-use evidence reader"
req "$PROBE" "execution claim probe"
req "$READINESS_GATE" "executor-readiness prerequisite gate"
text "$SERVICE" "public const EFFECT = 'mutate'" "claim declares provenance mutation effect"
text "$SERVICE" "atomic_single_use' => 'yes'" "claim declares atomic single use"
text "$SERVICE" "claim_is_execution_authority' => 'no'" "claim is not execution authority"
text "$SERVICE" "grants_execution_authority' => 'no'" "claim grants no execution authority"
text "$STORE" "fopen(\$path, 'x+b')" "claim store uses atomic exclusive creation"
text "$STORE" "deletion-execution-uses" "claim writes canonical single-use evidence namespace"
text "$STORE" "claim.json" "one deterministic claim file exists per request"
no_pat "$SERVICE" 'delete_target|archive_target|copy\s*\(|rename\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "claim capability owns no execution or database authority"
if command -v php >/dev/null 2>&1; then
  for file in "$SERVICE" "$STORE" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "execution claim behavior probe"
else fail "php is required to certify execution claim"; fi
bash "$READINESS_GATE" || fail "executor-readiness prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Studio deletion execution claim capability FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Studio deletion execution claim capability PASS"

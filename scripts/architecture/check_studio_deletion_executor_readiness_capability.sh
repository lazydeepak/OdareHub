#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
SERVICE="apps/Studio/Services/StudioDeletionExecutorReadinessService.php"
USE_STORE="apps/Studio/Services/StudioDeletionExecutionRequestUseEvidenceStore.php"
PROBE="apps/Studio/tests/probe_studio_deletion_executor_readiness_capability.php"
REQUEST_GATE="scripts/architecture/check_studio_deletion_execution_request_capability.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req_file(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
req_text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pattern(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_studio_deletion_executor_readiness_capability"
req_file "$SERVICE" "canonical executor-readiness capability"
req_file "$USE_STORE" "single-use evidence store"
req_file "$PROBE" "executor-readiness probe"
req_file "$REQUEST_GATE" "execution-request prerequisite gate"
req_text "$SERVICE" "public const EFFECT = 'verify'" "capability declares verify effect"
for state in READY NOT_REQUESTED STALE EXPIRED WRONG_EXECUTOR CONSUMED BLOCKED UNKNOWN; do req_text "$SERVICE" "STATE_${state}" "state ${state} is explicit"; done
req_text "$SERVICE" "requires_separate_claim_capability' => 'yes'" "claim authority remains separate"
req_text "$SERVICE" "grants_execution_authority' => 'no'" "verifier grants no authority"
req_text "$SERVICE" "EXECUTION_REQUEST_FINGERPRINT_INVALID" "request integrity is verified"
req_text "$SERVICE" "EXECUTION_REQUEST_ALREADY_USED" "single-use policy is enforced"
req_text "$USE_STORE" "deletion-execution-uses" "single-use evidence is Studio confined"
no_pattern "$SERVICE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "verifier owns no mutation authority"
no_pattern "$USE_STORE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "use-evidence store is read-only"
if command -v php >/dev/null 2>&1; then
  for file in "$SERVICE" "$USE_STORE" "$PROBE"; do php -l "$file" >/dev/null && ok "PHP lint: $file" || fail "PHP lint: $file"; done
  php "$PROBE" || fail "executor-readiness behavior probe"
else fail "php is required to certify executor readiness"; fi
bash "$REQUEST_GATE" || fail "execution-request prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Studio deletion executor readiness capability FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Studio deletion executor readiness capability PASS"

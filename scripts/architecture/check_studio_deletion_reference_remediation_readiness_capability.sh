#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
SERVICE="apps/Studio/Services/StudioDeletionReferenceRemediationReadinessService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_reference_remediation_readiness_capability.php"
INTEGRITY_GATE="scripts/architecture/check_studio_deletion_snapshot_integrity_capability.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]] && ok "$2" || fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1" && ok "$3" || fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1" && fail "$3" || ok "$3"; }
echo "[architecture] check_studio_deletion_reference_remediation_readiness_capability"
req "$SERVICE" "canonical reference-remediation readiness capability"
req "$PROBE" "reference-remediation readiness probe"
req "$INTEGRITY_GATE" "snapshot-integrity prerequisite gate"
text "$SERVICE" "public const EFFECT = 'verify'" "capability declares verify effect"
text "$SERVICE" "STATE_DRIFTED = 'drifted'" "drifted state is explicit"
text "$SERVICE" "compare_exact_file_sha256_before_apply' => 'yes'" "exact file-hash precondition is required"
text "$SERVICE" "compare_exact_line_sha256_before_apply' => 'yes'" "exact line-hash precondition is required"
text "$SERVICE" "compare_context_sha256_before_apply' => 'yes'" "context-hash precondition is required"
text "$SERVICE" "fuzzy_apply_allowed' => 'no'" "fuzzy apply is prohibited"
text "$SERVICE" "requires_separate_patch_capability' => 'yes'" "separate patch capability remains required"
text "$SERVICE" "grants_execution_authority' => 'no'" "readiness grants no execution authority"
no_pat "$SERVICE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "readiness capability owns no mutation authority"
if command -v php >/dev/null 2>&1; then
  php -l "$SERVICE" >/dev/null && ok "service PHP lint" || fail "service PHP lint"
  php -l "$PROBE" >/dev/null && ok "probe PHP lint" || fail "probe PHP lint"
  php "$PROBE" || fail "reference-remediation readiness behavior probe"
else fail "php is required to certify reference-remediation readiness"; fi
bash "$INTEGRITY_GATE" || fail "snapshot-integrity prerequisite gate"
if [[ "$failures" -ne 0 ]]; then echo "[architecture] Studio deletion reference-remediation readiness FAILED ($failures issue(s))" >&2; exit 1; fi
echo "[architecture] Studio deletion reference-remediation readiness PASS"

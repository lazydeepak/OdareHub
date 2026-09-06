#!/bin/bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
SERVICE="apps/Studio/Services/StudioDeletionReferenceRemediationPatchProposalService.php"
PROBE="apps/Studio/tests/probe_studio_deletion_reference_remediation_patch_proposal_capability.php"
READINESS_GATE="scripts/architecture/check_studio_deletion_reference_remediation_readiness_capability.sh"
failures=0
fail(){ echo "  fail: $1" >&2; failures=$((failures+1)); }
ok(){ echo "  ok: $1"; }
req(){ [[ -f "$1" ]]&&ok "$2"||fail "$2 missing: $1"; }
text(){ grep -Fq -- "$2" "$1"&&ok "$3"||fail "$3"; }
no_pat(){ grep -Eqi -- "$2" "$1"&&fail "$3"||ok "$3"; }
echo "[architecture] check_studio_deletion_reference_remediation_patch_proposal_capability"
req "$SERVICE" "canonical patch proposal capability";req "$PROBE" "patch proposal probe";req "$READINESS_GATE" "remediation-readiness prerequisite gate"
text "$SERVICE" "public const EFFECT = 'plan'" "proposal declares plan effect"
text "$SERVICE" "STATE_DECISION_REQUIRED = 'decision_required'" "ambiguous review state is explicit"
text "$SERVICE" "fuzzy_apply_allowed' => 'no'" "fuzzy apply is forbidden"
text "$SERVICE" "force_apply_allowed' => 'no'" "force apply is forbidden"
text "$SERVICE" "requires_separate_patch_apply_capability' => 'yes'" "proposal remains separate from apply"
text "$SERVICE" "grants_execution_authority' => 'no'" "proposal grants no execution authority"
no_pat "$SERVICE" 'file_put_contents|fwrite|unlink\s*\(|rename\s*\(|copy\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+.*SET|DELETE[[:space:]]+FROM' "proposal owns no mutation authority"
if command -v php >/dev/null 2>&1;then php -l "$SERVICE" >/dev/null&&ok "service PHP lint"||fail "service PHP lint";php -l "$PROBE" >/dev/null&&ok "probe PHP lint"||fail "probe PHP lint";php "$PROBE"||fail "patch proposal behavior probe";else fail "php is required to certify patch proposal";fi
bash "$READINESS_GATE"||fail "remediation-readiness prerequisite gate"
if [[ "$failures" -ne 0 ]];then echo "[architecture] Studio reference patch proposal capability FAILED ($failures issue(s))" >&2;exit 1;fi
echo "[architecture] Studio reference patch proposal capability PASS"

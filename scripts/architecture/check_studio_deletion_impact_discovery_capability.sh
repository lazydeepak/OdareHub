#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

CAPABILITY="apps/Studio/Services/StudioDeletionImpactDiscoveryService.php"
CORE_TRAIT="apps/Studio/Services/StudioDeletionImpactDiscoveryCoreTrait.php"
TARGET_TRAIT="apps/Studio/Services/StudioDeletionImpactDiscoveryTargetTrait.php"
POLICY_TRAIT="apps/Studio/Services/StudioDeletionImpactDiscoveryPolicyTrait.php"
CAPABILITY_GLOB="apps/Studio/Services/StudioDeletionImpactDiscovery"'*.php'
OWNER_CAPABILITY="apps/Studio/Services/StudioOwnerDiscoveryService.php"
REFERENCE_CAPABILITY="apps/Studio/Services/StudioReferenceDiscoveryService.php"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionImpactService.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_studio_deletion_impact_discovery_capability.php"
OWNER_PROBE="apps/Studio/tests/probe_studio_owner_discovery_capability.php"
REFERENCE_PROBE="apps/Studio/tests/probe_studio_reference_discovery_capability.php"

failures=0

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

ok() {
  echo "  ok: $1"
}

require_file() {
  local path="$1"
  local label="$2"
  if [[ -f "$path" ]]; then
    ok "$label"
  else
    fail "$label missing: $path"
  fi
}

require_text() {
  local path="$1"
  local needle="$2"
  local label="$3"
  if [[ -f "$path" ]] && grep -Fq -- "$needle" "$path"; then
    ok "$label"
  else
    fail "$label"
  fi
}

require_no_pattern() {
  local path="$1"
  local pattern="$2"
  local label="$3"
  if [[ -f "$path" ]] && grep -Eq -- "$pattern" "$path"; then
    fail "$label"
  else
    ok "$label"
  fi
}

echo "[architecture] check_studio_deletion_impact_discovery_capability"

require_file "$CAPABILITY" "canonical deletion impact capability exists"
require_file "$CORE_TRAIT" "deletion impact core trait exists"
require_file "$TARGET_TRAIT" "deletion impact target trait exists"
require_file "$POLICY_TRAIT" "deletion impact policy trait exists"
require_file "$OWNER_CAPABILITY" "canonical owner discovery capability exists"
require_file "$REFERENCE_CAPABILITY" "canonical reference discovery capability exists"
require_file "$ADAPTER" "Owner Structure deletion impact adapter exists"
require_file "$MANIFEST" "Owner Structure manifest exists"
require_file "$PROBE" "deletion impact capability probe exists"
require_file "$OWNER_PROBE" "owner discovery capability probe exists"
require_file "$REFERENCE_PROBE" "reference discovery capability probe exists"

require_text "$CAPABILITY" "public const EFFECT = 'read'" "deletion impact capability is explicitly read-only"
require_text "$CORE_TRAIT" "StudioOwnerDiscoveryService::discover" "deletion impact composes owner discovery"
require_text "$CORE_TRAIT" "StudioReferenceDiscoveryService::discover" "deletion impact composes reference discovery"
require_text "$CORE_TRAIT" "public static function compose" "pure evidence composition boundary declared"
require_text "$CORE_TRAIT" "deletion-impact:owner-key" "logical owner-key references are assessed"
require_text "$CORE_TRAIT" "DELETION_REFERENCE_EVIDENCE_MISSING" "missing evidence fails closed"
require_text "$ADAPTER" "StudioDeletionImpactDiscoveryService::discover" "Owner Structure delegates deletion impact assessment"
require_text "$MANIFEST" "Apps\Studio\Services\StudioDeletionImpactDiscoveryService" "manifest registers deletion impact capability"
require_text "$MANIFEST" "Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionImpactService" "manifest registers Owner Structure adapter"

require_no_pattern "$ADAPTER" 'scandir\(|file_get_contents\(|unlink\(|rmdir\(|rename\(|copy\(' "Owner Structure adapter contains no traversal or mutation authority"
if grep -Eq 'unlink\(|rmdir\(|rename\(|copy\(|file_put_contents\(' $CAPABILITY_GLOB; then
  fail "deletion impact capability contains mutation authority"
else
  ok "deletion impact capability contains no mutation authority"
fi

if command -v php >/dev/null 2>&1; then
  for capability_file in $CAPABILITY_GLOB; do
    php -l "$capability_file" >/dev/null && ok "deletion impact PHP lint: $capability_file" || fail "deletion impact PHP lint: $capability_file"
  done
  php -l "$ADAPTER" >/dev/null && ok "Owner Structure adapter PHP lint" || fail "Owner Structure adapter PHP lint"
  php -l "$PROBE" >/dev/null && ok "deletion impact probe PHP lint" || fail "deletion impact probe PHP lint"
  php "$OWNER_PROBE" || fail "owner discovery prerequisite probe"
  php "$REFERENCE_PROBE" || fail "reference discovery prerequisite probe"
  php "$PROBE" || fail "deletion impact capability probe"
else
  fail "php is required to run deletion impact certification"
fi

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio deletion impact discovery capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio deletion impact discovery capability PASS"

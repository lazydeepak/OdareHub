#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

CAPABILITY="apps/Studio/Services/StudioReferenceDiscoveryService.php"
CORE_TRAIT="apps/Studio/Services/StudioReferenceDiscoveryCoreTrait.php"
ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureReferenceDiscoveryService.php"
MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
PROBE="apps/Studio/tests/probe_studio_reference_discovery_capability.php"
LEGACY_PROBE="apps/Studio/tests/probe_owner_structure_reference_discovery.php"

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

echo "[architecture] check_studio_reference_discovery_capability"

require_file "$CAPABILITY" "canonical reference discovery capability exists"
require_file "$CORE_TRAIT" "canonical reference discovery core trait exists"
require_file "$ADAPTER" "Owner Structure reference adapter exists"
require_file "$MANIFEST" "Owner Structure manifest exists"
require_file "$PROBE" "canonical reference capability probe exists"
require_file "$LEGACY_PROBE" "legacy reference probe entrypoint exists"

require_text "$CAPABILITY" "final class StudioReferenceDiscoveryService" "canonical capability class declared"
require_text "$CORE_TRAIT" "public static function discover" "canonical discover boundary declared"
require_text "$CORE_TRAIT" "include_path_prefixes" "capability supports bounded reference scope"
require_text "$CORE_TRAIT" "REFERENCE_ROOT_UNAVAILABLE" "capability returns controlled root diagnostic"
require_text "$ADAPTER" "StudioReferenceDiscoveryService::discover" "Owner Structure delegates reference discovery"
require_text "$MANIFEST" "Apps\\Studio\\Services\\StudioReferenceDiscoveryService" "manifest registers canonical reference capability"
require_text "$LEGACY_PROBE" "probe_studio_reference_discovery_capability.php" "legacy probe delegates to canonical probe"

require_no_pattern "$ADAPTER" 'scandir\(|file_get_contents\(|sourceFiles\(|findReferences\(|function patterns\(|function proposedReplacement\(|function shouldPreferReference\(' "Owner Structure adapter contains no repository traversal or matching authority"

if command -v php >/dev/null 2>&1; then
  for capability_file in apps/Studio/Services/StudioReferenceDiscovery*.php; do
    php -l "$capability_file" >/dev/null && ok "capability PHP lint: $capability_file" || fail "capability PHP lint: $capability_file"
  done
  php -l "$ADAPTER" >/dev/null && ok "adapter PHP lint" || fail "adapter PHP lint"
  php -l "$PROBE" >/dev/null && ok "capability probe PHP lint" || fail "capability probe PHP lint"
  php -l "$LEGACY_PROBE" >/dev/null && ok "legacy probe PHP lint" || fail "legacy probe PHP lint"
  php "$PROBE" || fail "reference discovery capability probe"
else
  fail "php is required to run reference discovery certification"
fi

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio reference discovery capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio reference discovery capability PASS"

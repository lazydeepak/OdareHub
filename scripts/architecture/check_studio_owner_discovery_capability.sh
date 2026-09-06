#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

CAPABILITY="apps/Studio/Services/StudioOwnerDiscoveryService.php"
OWNER_STRUCTURE_ADAPTER="apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php"
OWNER_STRUCTURE_MANIFEST="apps/Studio/Tools/OwnerStructureScan/manifest.php"
REPOSITORY_ADAPTER="apps/Studio/Tools/HelperTool/Services/RepoTreeScannerService.php"
REPOSITORY_OWNER_TRAIT="apps/Studio/Tools/HelperTool/Services/RepoTreeScannerOwnerTrait.php"
REPOSITORY_MANIFEST="apps/Studio/Tools/HelperTool/manifest.php"
CAPABILITY_PROBE="apps/Studio/tests/probe_studio_owner_discovery_capability.php"
REPOSITORY_PROBE="apps/Studio/tests/probe_helper_tool_repository_scan.php"
REPOSITORY_SERVICE_GLOB="apps/Studio/Tools/HelperTool/Services/RepoTreeScanner"'*.php'

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

echo "[architecture] check_studio_owner_discovery_capability"

require_file "$CAPABILITY" "canonical owner discovery capability exists"
require_file "$OWNER_STRUCTURE_ADAPTER" "Owner Structure compatibility adapter exists"
require_file "$OWNER_STRUCTURE_MANIFEST" "Owner Structure manifest exists"
require_file "$REPOSITORY_ADAPTER" "Repository Scanner compatibility adapter exists"
require_file "$REPOSITORY_OWNER_TRAIT" "Repository Scanner owner adapter trait exists"
require_file "$REPOSITORY_MANIFEST" "Repository Scanner manifest exists"
require_file "$CAPABILITY_PROBE" "owner discovery capability probe exists"
require_file "$REPOSITORY_PROBE" "Repository Scanner full probe exists"

require_text "$CAPABILITY" "PROFILE_REPOSITORY_SCANNER" "repository scanner compatibility profile declared"
require_text "$CAPABILITY" "PROFILE_OWNER_STRUCTURE" "Owner Structure compatibility profile declared"
require_text "$CAPABILITY" "OWNER_KEY_COLLISION" "owner-key collision diagnostic declared"
require_text "$CAPABILITY" "Generated/" "generated owner discovery declared"
require_text "$CAPABILITY" "EW/" "engineering workspace owner discovery declared"
require_text "$OWNER_STRUCTURE_ADAPTER" "StudioOwnerDiscoveryService::discover" "Owner Structure delegates discovery"
require_text "$OWNER_STRUCTURE_ADAPTER" "StudioOwnerDiscoveryService::resolve" "Owner Structure delegates resolution"
require_text "$REPOSITORY_ADAPTER" "RepoTreeScannerOwnerTrait" "Repository Scanner composes owner adapter trait"
require_text "$REPOSITORY_OWNER_TRAIT" "StudioOwnerDiscoveryService::discover" "Repository Scanner delegates discovery"
require_text "$REPOSITORY_OWNER_TRAIT" "StudioOwnerDiscoveryService::PROFILE_REPOSITORY_SCANNER" "Repository Scanner requests compatibility profile"
require_text "$OWNER_STRUCTURE_MANIFEST" "Apps\\Studio\\Services\\StudioOwnerDiscoveryService" "Owner Structure manifest registers canonical capability"
require_text "$REPOSITORY_MANIFEST" "Apps\\Studio\\Services\\StudioOwnerDiscoveryService" "Repository Scanner manifest registers canonical capability"

require_no_pattern "$OWNER_STRUCTURE_ADAPTER" 'RecursiveDirectoryIterator|scandir\(|childDirectories\(|looksLikeOwnerRoot\(' "Owner Structure adapter contains no filesystem traversal authority"
if grep -Eq '_cachedOwners|collectEngineeringSubDirs|determineOwnerType|buildOwnerEntry|isExcludedSegment|looksLikeOwnerRoot|childDirectories' $REPOSITORY_SERVICE_GLOB; then
  fail "Repository Scanner contains duplicate owner traversal authority"
else
  ok "Repository Scanner contains no duplicate owner traversal authority"
fi

if command -v php >/dev/null 2>&1; then
  php -l "$CAPABILITY" >/dev/null && ok "capability PHP lint" || fail "capability PHP lint"
  php -l "$OWNER_STRUCTURE_ADAPTER" >/dev/null && ok "Owner Structure adapter PHP lint" || fail "Owner Structure adapter PHP lint"
  for repository_file in $REPOSITORY_SERVICE_GLOB; do
    php -l "$repository_file" >/dev/null && ok "Repository Scanner PHP lint: $repository_file" || fail "Repository Scanner PHP lint: $repository_file"
  done
  php -l "$CAPABILITY_PROBE" >/dev/null && ok "capability probe PHP lint" || fail "capability probe PHP lint"
  php -l "$REPOSITORY_PROBE" >/dev/null && ok "Repository Scanner probe PHP lint" || fail "Repository Scanner probe PHP lint"
  php "$CAPABILITY_PROBE" || fail "owner discovery capability probe"
  php "$REPOSITORY_PROBE" || fail "Repository Scanner full probe"
else
  fail "php is required to run owner discovery certification"
fi

if [[ "$failures" -ne 0 ]]; then
  echo "[architecture] Studio owner discovery capability FAILED ($failures issue(s))" >&2
  exit 1
fi

echo "[architecture] Studio owner discovery capability PASS"

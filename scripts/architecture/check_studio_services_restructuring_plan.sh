#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_studio_services_restructuring_plan"
echo "- read-only diagnostic for Studio services restructuring plan"

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
    ok "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

require_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if [[ -f "$path" ]] && grep -Fq "$needle" "$path"; then
    ok "$label"
  else
    fail "$label not found in $path"
  fi
}

PLAN_DOC="docs/migration-cleanup/maps/batch-12-studio-services-restructuring-plan.md"

require_file "$PLAN_DOC" "Batch 12 Studio services restructuring plan"

service_files=(
  "apps/Studio/Services/AppStudioRegistryService.php"
  "apps/Studio/Services/GuiStudioService.php"
  "apps/Studio/Services/HostSurfaceContributionService.php"
  "apps/Studio/Services/StudioDataContractService.php"
  "apps/Studio/Services/StudioDependencyGraphService.php"
  "apps/Studio/Services/StudioExperienceGovernanceService.php"
  "apps/Studio/Services/StudioGovernanceService.php"
  "apps/Studio/Services/StudioGovernedToolRegistryService.php"
  "apps/Studio/Services/StudioNavCandidateProviderService.php"
  "apps/Studio/Services/StudioNavLinkingService.php"
  "apps/Studio/Services/StudioNotificationService.php"
  "apps/Studio/Services/StudioResourceTypeRegistryService.php"
  "apps/Studio/Services/StudioRouteLinkingService.php"
  "apps/Studio/Services/StudioRuntimeBindingService.php"
  "apps/Studio/Services/StudioToolCatalogService.php"
  "apps/Studio/Services/StudioViewIntrospectionService.php"
)

echo ""
echo "== Studio services inventory completeness =="

for path in "${service_files[@]}"; do
  require_file "$path" "Studio service file"
  base_name="$(basename "$path")"
  require_text "$PLAN_DOC" "$base_name" "Plan inventories $base_name"
done

expected_count="${#service_files[@]}"
actual_count="$(ls apps/Studio/Services/*.php 2>/dev/null | wc -l | tr -d ' ')"
if [[ "$actual_count" == "$expected_count" ]]; then
  ok "Studio services count matches expected inventory ($actual_count)"
else
  fail "Studio services count mismatch: expected $expected_count got $actual_count"
fi

echo ""
echo "== Classification vocabulary coverage =="
for tag in \
  GOVERNANCE \
  REGISTRY \
  COMPOSITION \
  ANALYSIS \
  EXECUTION \
  GENERATION \
  PUBLISHING \
  SNAPSHOT \
  VALIDATION \
  RUNTIME_ADJACENT \
  COMPAT_BRIDGE \
  INVESTIGATE \
  DO_NOT_MOVE; do
  require_text "$PLAN_DOC" "\`$tag\`" "Plan contains classification tag $tag"
done

echo ""
echo "== Required direct answers and path questions =="
require_text "$PLAN_DOC" "Which services are safe future move candidates?" "Plan answers safe move candidates"
require_text "$PLAN_DOC" "Which services are runtime-adjacent and must stay until separated?" "Plan answers runtime-adjacent hold set"
require_text "$PLAN_DOC" "Which services write to \`apps/Generated\`?" "Plan answers generated writers"
require_text "$PLAN_DOC" "Which services write to \`storage/appstudio\`?" "Plan answers storage writers"
require_text "$PLAN_DOC" "Which services touch \`public/assets/apps\`?" "Plan answers public assets touch"
require_text "$PLAN_DOC" "Which services are compatibility bridges?" "Plan answers compatibility bridges"
require_text "$PLAN_DOC" "What should be the first actual service move batch?" "Plan answers first move batch"

echo ""
echo "== Index and move-plan alignment =="
require_text "MIGRATION-CLEANUP-INDEX.md" "batch-12-studio-services-restructuring-plan.md" "Migration cleanup index links Batch 12 report"
require_text "docs/migration-cleanup/phases/move-plan.md" "Batch 12: Studio services restructuring plan" "Move plan includes Batch 12 section"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio services restructuring plan gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

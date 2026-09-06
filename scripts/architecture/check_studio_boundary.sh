#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

RG_BIN="${RG_BIN:-$(command -v rg || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"

if [[ -n "$RG_BIN" ]]; then
  SEARCH_TOOL="$RG_BIN"
  SEARCH_ARGS=(-n -S)
else
  if [[ -z "$GREP_BIN" ]]; then
    echo "missing required binary: rg or grep" >&2
    exit 2
  fi
  SEARCH_TOOL="$GREP_BIN"
  SEARCH_ARGS=(-RIn)
fi

failures=0
tmp_diff="$(mktemp /tmp/studio-boundary-diff-XXXXXX)"
trap 'rm -f "$tmp_diff"' EXIT

expected_scan_anchors=(
  "apps/Studio"
  "apps/Studio/manifest.json"
  "apps/Studio/routes.php"
  "apps/Studio/AGENTS.md"
  "app"
  "apps/Shell"
  "apps/Platform"
  "apps/Manufacturing"
  "apps/SBAIO"
  "apps/Procurement"
  "apps/Payroll"
  "apps/ERP"
  "apps/LazyPOS"
  "core_pattern"
  "shell_bridge_allowlist_pattern"
  "platform_bridge_allowlist_pattern"
  "runtime_additions_studio_dependency_pattern"
  "studio_runtime_truth_pattern"
)

active_scan_anchors=(
  "apps/Studio"
  "apps/Studio/manifest.json"
  "apps/Studio/routes.php"
  "apps/Studio/AGENTS.md"
  "app"
  "apps/Shell"
  "apps/Platform"
  "apps/Manufacturing"
  "apps/SBAIO"
  "apps/Procurement"
  "apps/Payroll"
  "apps/ERP"
  "apps/LazyPOS"
  "core_pattern"
  "shell_bridge_allowlist_pattern"
  "platform_bridge_allowlist_pattern"
  "runtime_additions_studio_dependency_pattern"
  "studio_runtime_truth_pattern"
)

echo "[architecture] check_studio_boundary"

diff_additions() {
  git diff --unified=0 --diff-filter=ACMRT HEAD -- app apps plugins public 2>/dev/null \
    | awk '
      /^diff --git / {
        file = $4
        sub(/^b\//, "", file)
        next
      }
      /^\+\+\+ / { next }
      /^\+/ && $0 !~ /^\+\+\+/ {
        print file ":" substr($0, 2)
      }
    '
}

check_manifest_absence() {
  local manifest="$1"
  local label="$2"
  local pattern="$3"

  if [[ ! -f "$manifest" ]]; then
    echo "missing manifest for $label: $manifest"
    failures=$((failures + 1))
    return
  fi

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "$manifest" 2>/dev/null; then
    echo "$label manifest implies forbidden Studio ownership/dependency"
    failures=$((failures + 1))
  else
    echo "  ok: $label manifest avoids forbidden Studio ownership/dependency language"
  fi
}

diff_additions > "$tmp_diff"

echo "- verifying Studio boundary scan contract"
for i in "${!expected_scan_anchors[@]}"; do
  expected="${expected_scan_anchors[$i]}"
  actual="${active_scan_anchors[$i]:-}"
  if [[ "$actual" == "$expected" ]]; then
    echo "  ok: scan-anchor[$i] $expected"
  else
    echo "scan-anchor[$i] drift: expected '$expected' but found '${actual:-<missing>}'"
    failures=$((failures + 1))
  fi
done

if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "scan-anchor count drift: expected ${#expected_scan_anchors[@]} but found ${#active_scan_anchors[@]}"
  failures=$((failures + 1))
fi

echo "- verifying Studio is isolated as an optional app"
if [[ ! -d "apps/Studio" ]]; then
  echo "missing canonical apps/Studio owner directory"
  failures=$((failures + 1))
fi

echo "- scanning Core for Studio runtime dependencies"
core_pattern='Apps\\Studio|/apps/studio|/ops/design-studio|GuiStudioService|DesignStudioService|StudioAuthorizationService|StudioGovernanceService'
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$core_pattern" app 2>/dev/null; then
  failures=$((failures + 1))
fi

echo "- scanning business/reference apps for Studio runtime dependencies"
business_targets=()
for path in apps/Manufacturing apps/SBAIO apps/Procurement apps/Payroll apps/ERP apps/LazyPOS; do
  if [[ -e "$path" ]]; then
    business_targets+=("$path")
  fi
done

if [[ "${#business_targets[@]}" -gt 0 ]]; then
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$core_pattern" "${business_targets[@]}" 2>/dev/null; then
    failures=$((failures + 1))
  fi
fi

echo "- scanning Shell for non-bridge Studio ownership"
shell_tmp="$(mktemp /tmp/studio-shell-boundary-XXXXXX)"
platform_tmp="$(mktemp /tmp/studio-platform-boundary-XXXXXX)"
trap 'rm -f "$tmp_diff" "$shell_tmp" "$platform_tmp"' EXIT
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$core_pattern" apps/Shell 2>/dev/null > "$shell_tmp"; then
  shell_bridge_allowlist_pattern='AdminLayerWrapperComposer\.php|BrandIdentityService\.php|StyleRegistryService\.php|AGENTS\.md|conditional|enabled|adminSidebarStudioEntries|APP_STUDIO_NAME|Studio-generated|Studio drafts, previews, or runtime truth'
  if [[ -n "$RG_BIN" ]]; then
    "$RG_BIN" -n -v "$shell_bridge_allowlist_pattern" "$shell_tmp" || true
    filtered_count="$(("$RG_BIN" -n -v "$shell_bridge_allowlist_pattern" "$shell_tmp" || true) | wc -l | tr -d ' ')"
  else
    "$GREP_BIN" -n -vE "$shell_bridge_allowlist_pattern" "$shell_tmp" || true
    filtered_count="$(("$GREP_BIN" -n -vE "$shell_bridge_allowlist_pattern" "$shell_tmp" || true) | wc -l | tr -d ' ')"
  fi
  if [[ "$filtered_count" != "0" ]]; then
    failures=$((failures + 1))
  fi
fi

echo "- scanning Platform for governance-only Studio references"
platform_bridge_allowlist_pattern='manifest\.json|AGENTS\.md|RouteViewBridgeService\.php|DashboardAggregatorService\.php|DesignStudioService\.php|routes\.php|conditional|installed|enabled|Studio entry|Studio links|Studio installed/enabled|Studio editor/builder implementation|Studio logic|Studio truth|diagnostics|governance|governed|optional'
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$core_pattern" apps/Platform 2>/dev/null > "$platform_tmp"; then
  if [[ -n "$RG_BIN" ]]; then
    "$RG_BIN" -n -v "$platform_bridge_allowlist_pattern" "$platform_tmp" || true
    platform_filtered_count="$(("$RG_BIN" -n -v "$platform_bridge_allowlist_pattern" "$platform_tmp" || true) | wc -l | tr -d ' ')"
  else
    "$GREP_BIN" -n -vE "$platform_bridge_allowlist_pattern" "$platform_tmp" || true
    platform_filtered_count="$(("$GREP_BIN" -n -vE "$platform_bridge_allowlist_pattern" "$platform_tmp" || true) | wc -l | tr -d ' ')"
  fi
  if [[ "$platform_filtered_count" != "0" ]]; then
    failures=$((failures + 1))
  fi
fi

echo "- verifying manifests do not assign Studio runtime ownership outside Studio"
for manifest in apps/Shell/manifest.json apps/Platform/manifest.json; do
  check_manifest_absence "$manifest" "$manifest" 'Studio.*(owns runtime|owns business|source_of_truth|hard dependency|runtime dependency)|depends_on.*Studio|Studio.*bypass'
done

echo "- scanning runtime additions for Studio boundary regressions"
runtime_additions_studio_dependency_pattern='^(apps/(Shell|Manufacturing|SBAIO|Procurement|Payroll|ERP|LazyPOS)|plugins)/.*(Apps\\Studio|/apps/studio|GuiStudioService|DesignStudioService|StudioAuthorizationService|StudioGovernanceService|studio_apply|studio_runtime|studio_draft)'
studio_runtime_truth_pattern='^apps/Studio/.*(runtime truth|source of truth|owns business|owns route truth|owns schema truth|owns resolved|bypass approval|bypass snapshot|bypass rollback|skip approval|skip snapshot|skip rollback|without handover)'
if [[ -s "$tmp_diff" ]]; then
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$runtime_additions_studio_dependency_pattern" "$tmp_diff" 2>/dev/null \
      | grep -Ev 'AGENT-COMPLIANCE-CHECKLIST|docs/|check_studio_boundary|check_studio_enforcement_readiness|conditional|enabled|installed|bridge|compatibility|governance'; then
    echo "new runtime diff introduces direct Studio dependency outside Studio boundary"
    failures=$((failures + 1))
  fi

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$studio_runtime_truth_pattern" "$tmp_diff" 2>/dev/null \
      | grep -Ev 'AGENT-COMPLIANCE-CHECKLIST|docs/|check_studio_boundary|check_studio_enforcement_readiness|must not|not runtime truth|not source of truth|cannot bypass|must not bypass'; then
    echo "new Studio diff implies runtime/source-of-truth ownership or governance bypass"
    failures=$((failures + 1))
  fi
else
  echo "  no runtime diff additions to scan for Studio boundary regressions"
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio boundary violations found)" >&2
  exit 1
fi

echo "RESULT: PASS"

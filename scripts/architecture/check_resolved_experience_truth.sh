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
contract_doc="docs/architecture/resolved-runtime-contract-pipeline.md"
expected_scan_anchors=(
  "apps/Shell/routes.php"
  "apps/Shell/Services"
  "apps/Shell/Composers"
  "apps/Shell/Views"
  "apps/Platform/routes.php"
  "apps/Platform/Services"
  "apps/Platform/Views"
  "apps/Platform/modules/*/plugin.json"
  "apps/*/modules/*/plugin.json"
  "legacy_visibility_pattern"
  "runtime_additions_visibility_pattern"
  "runtime_additions_role_pattern"
)
active_scan_anchors=(
  "apps/Shell/routes.php"
  "apps/Shell/Services"
  "apps/Shell/Composers"
  "apps/Shell/Views"
  "apps/Platform/routes.php"
  "apps/Platform/Services"
  "apps/Platform/Views"
  "apps/Platform/modules/*/plugin.json"
  "apps/*/modules/*/plugin.json"
  "legacy_visibility_pattern"
  "runtime_additions_visibility_pattern"
  "runtime_additions_role_pattern"
)

echo "[architecture] check_resolved_experience_truth"

check_file() {
  local path="$1"
  local label="$2"

  if [[ -f "$path" ]]; then
    echo "  ok: $label ($path)"
  else
    echo "  fail: missing $label ($path)" >&2
    failures=$((failures + 1))
  fi
}

check_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if [[ ! -f "$path" ]]; then
    echo "  fail: cannot inspect $label; missing file $path" >&2
    failures=$((failures + 1))
    return
  fi

  if "$GREP_BIN" -Fq -- "$needle" "$path"; then
    echo "  ok: $label"
  else
    echo "  fail: $label" >&2
    failures=$((failures + 1))
  fi
}

append_existing_targets() {
  local out_name="$1"
  shift
  local candidate
  for candidate in "$@"; do
    for path in $candidate; do
      if [[ -e "$path" ]]; then
        eval "$out_name+=(\"$path\")"
      fi
    done
  done
}

scan_index_and_filter() {
  local index_file="$1"
  local pattern="$2"
  local allowlist="$3"
  local section_label="$4"
  local raw_file="$tmp_runtime_raw"
  local filtered_file="$tmp_runtime_filtered"
  local count

  : > "$raw_file"
  : > "$filtered_file"

  if [[ ! -s "$index_file" ]]; then
    echo "  note: no runtime diff additions to scan for $section_label"
    LAST_COUNT="0"
    return
  fi

  if "$GREP_BIN" -nEi -- "$pattern" "$index_file" > "$raw_file" 2>/dev/null; then
    if [[ -n "$allowlist" ]]; then
      "$GREP_BIN" -n -vE -- "$allowlist" "$raw_file" > "$filtered_file" || true
    else
      cat "$raw_file" > "$filtered_file"
    fi
  fi

  count="$("$GREP_BIN" -c . "$filtered_file" 2>/dev/null || true)"
  if [[ "$count" != "0" ]]; then
    echo "- findings: $section_label"
    cat "$filtered_file"
  fi
  LAST_COUNT="$count"
}

echo "- verifying resolved-experience scan contract"
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: resolved-experience scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
  failures=$((failures + 1))
else
  for index in "${!expected_scan_anchors[@]}"; do
    if [[ "${active_scan_anchors[$index]}" == "${expected_scan_anchors[$index]}" ]]; then
      echo "  ok: scan-anchor[$index] ${active_scan_anchors[$index]}"
    else
      echo "  fail: scan-anchor[$index] changed; expected ${expected_scan_anchors[$index]}, found ${active_scan_anchors[$index]}" >&2
      failures=$((failures + 1))
    fi
  done
fi

echo "- verifying resolved runtime contract baseline coverage"
check_file "$contract_doc" "resolved runtime contract pipeline baseline"
check_text "$contract_doc" "Compiler/resolver inputs are owner-owned source resources" "contract defines source resources"
check_text "$contract_doc" "Compiler/resolver is a preparation layer" "contract defines compiler/resolver"
check_text "$contract_doc" "Apply ACL authorization filter as hard allow/deny" "contract defines ACL authorization filter"
check_text "$contract_doc" "Apply Workspace Profile shaping after ACL constraints" "contract defines Workspace Profile shaping"
check_text "$contract_doc" "Apply user overrides only inside policy limits" "contract defines user override shaping"
check_text "$contract_doc" "Produce resolved runtime contracts consumable by Shell/runtime" "contract defines resolved/compiled runtime contract"
check_text "$contract_doc" "Consume resolved/compiled contracts only" "contract defines Shell/runtime consumption"
check_text "$contract_doc" "Studio edits source resources later through governance workflows; Studio does not own runtime truth" "contract defines Studio non-ownership"
check_text "$contract_doc" "System Tools validate/rebuild/diagnose contract artifacts; System Tools do not bypass policy or ownership boundaries" "contract defines System Tools validation-only role"
check_text "$contract_doc" "Compatibility fields such as" "contract documents compatibility-field diagnostic scope"
check_text "$contract_doc" "scripts/architecture/check_resolved_experience_truth.sh" "contract references this enforcement gate"

targets=(
  "apps/Shell/routes.php"
  "apps/Shell/Services"
  "apps/Shell/Composers"
  "apps/Shell/Views"
  "apps/Platform/routes.php"
  "apps/Platform/Services"
  "apps/Platform/Views"
)

forbidden_pattern='\[['\''"](assigned_apps|module_visibility|operator_views|dashboard_type)['\''"]\][^\n]{0,100}(in_array|count\(|===|!==|==|!=|\?|:)'
allowlist_pattern='active_assigned_apps|ResolvedExperienceConsumerService|allow_legacy_sidebar_module_visibility|resolved_experience\.parity|log.*Parity|compat|legacy|normalizeOperatorViews|resolveUserContext\(|context|ctx|workspaceData|data|row\['\''operator_views'\''|DashboardAggregatorService|DashboardService|DashboardPriorityService|DashboardActionService|PlatformUserAssignmentAdapter|PlatformUserRuntimeFacade|UserAssignmentContext|RoleDashboardsController|DesignStudioService|my_work\.php|context_prep\.php|apps/Platform/routes\.php:.*\$_POST\['\''dashboard_type'\'''

tmp_file="$(mktemp /tmp/resolved-truth-XXXXXX)"
tmp_filtered="$(mktemp /tmp/resolved-truth-filtered-XXXXXX)"
tmp_runtime_additions="$(mktemp /tmp/resolved-truth-runtime-additions-XXXXXX)"
tmp_runtime_raw="$(mktemp /tmp/resolved-truth-runtime-raw-XXXXXX)"
tmp_runtime_filtered="$(mktemp /tmp/resolved-truth-runtime-filtered-XXXXXX)"
trap 'rm -f "$tmp_file" "$tmp_filtered" "$tmp_runtime_additions" "$tmp_runtime_raw" "$tmp_runtime_filtered"' EXIT
LAST_COUNT="0"

"$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$forbidden_pattern" "${targets[@]}" 2>/dev/null > "$tmp_file" || true

if [[ -s "$tmp_file" ]]; then
  echo "- potential active legacy-field truth usage"
  if [[ -n "$RG_BIN" ]]; then
    "$RG_BIN" -n -v "$allowlist_pattern" "$tmp_file" > "$tmp_filtered" || true
  else
    "$GREP_BIN" -n -vE "$allowlist_pattern" "$tmp_file" > "$tmp_filtered" || true
  fi

  cat "$tmp_filtered"
  filtered_count="$("$GREP_BIN" -c . "$tmp_filtered" 2>/dev/null || true)"
  if [[ "$filtered_count" != "0" ]]; then
    failures=$((failures + 1))
  fi
fi

runtime_targets=()
append_existing_targets runtime_targets \
  "apps/Shell/routes.php" \
  "apps/Shell/Services" \
  "apps/Shell/Composers" \
  "apps/Shell/Views" \
  "apps/Platform/routes.php" \
  "apps/Platform/Services" \
  "apps/Platform/Views" \
  "apps/Platform/modules/*/plugin.json" \
  "apps/*/modules/*/plugin.json"

{
  git diff --unified=0 --diff-filter=ACMRT HEAD -- "${runtime_targets[@]}" 2>/dev/null \
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

  while IFS= read -r file; do
    case "$file" in
      apps/Shell/routes.php|apps/Shell/Services/*|apps/Shell/Composers/*|apps/Shell/Views/*|apps/Platform/routes.php|apps/Platform/Services/*|apps/Platform/Views/*|apps/Platform/modules/*/plugin.json|apps/*/modules/*/plugin.json)
        awk -v file="$file" '{ print file ":" FNR ":" $0 }' "$file"
        ;;
    esac
  done < <(git ls-files --others --exclude-standard -- apps/Shell/routes.php apps/Shell/Services apps/Shell/Composers apps/Shell/Views apps/Platform/routes.php apps/Platform/Services apps/Platform/Views apps/Platform/modules 'apps/*/modules/*/plugin.json' 2>/dev/null)
} > "$tmp_runtime_additions"

echo "- scanning runtime additions for hidden visibility-truth regressions"
visibility_runtime_pattern='(assigned_apps|module_visibility|operator_views|dashboard_type|me_dashboard_blocks|me_plugin_cards|display_surfaces|workspace_profile_key|landing_wrapper)[^[:cntrl:]]{0,140}(in_array|array_intersect|count\(|empty\(|isset\(|===|!==|==|!=|\?|:)'
visibility_runtime_allowlist='^scripts/architecture/|^docs/|^AGENT-COMPLIANCE-CHECKLIST\.md|active_assigned_apps|ResolvedExperienceConsumerService|resolved_experience\.parity|compat|legacy|debt|diagnostic|migration|normalizeOperatorViews|allow_legacy_sidebar_module_visibility|resolveUserContext\(|workspaceData|context|ctx|row|payload|schema|inventory'
scan_index_and_filter "$tmp_runtime_additions" "$visibility_runtime_pattern" "$visibility_runtime_allowlist" "new hidden visibility-truth field usage"
if [[ "$LAST_COUNT" != "0" ]]; then
  failures=$((failures + 1))
fi

echo "- scanning runtime additions for direct Shell render role checks"
role_runtime_pattern='(apps/Shell/Composers|apps/Shell/Views|apps/Shell/Services/(OperatorLayerSidebarService|OperatorSurfaceContributionRegistry|PlatformWidgetBlueprintRuntimeService|StyleRegistryService)\.php).*[^A-Za-z0-9_](role_|account_type|authority_role|dashboard_type)[^[:cntrl:]]{0,140}(in_array|===|!==|==|!=|switch|case|\?)'
role_runtime_allowlist='resolved|ResolvedExperience|compat|legacy|parity|diagnostic|migration|debt|context|ctx|workspaceData|operator\.overview\.context|role_sort|display operational_role|resolveUserContext\(|LandingPageService|AdminLayerService|OperatorLayerService|DisplayLayerService'
scan_index_and_filter "$tmp_runtime_additions" "$role_runtime_pattern" "$role_runtime_allowlist" "new direct role/account checks in Shell render paths"
if [[ "$LAST_COUNT" != "0" ]]; then
  failures=$((failures + 1))
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (resolved-experience single-source risks found)" >&2
  exit 1
fi

echo "RESULT: PASS"

#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

RG_BIN="${RG_BIN:-$(command -v rg || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"

if [[ -n "$RG_BIN" ]]; then
  SEARCH_TOOL="$RG_BIN"
  SEARCH_ARGS=(-n -i -S)
else
  if [[ -z "$GREP_BIN" ]]; then
    echo "missing required binary: rg or grep" >&2
    exit 2
  fi
  SEARCH_TOOL="$GREP_BIN"
  SEARCH_ARGS=(-nEi)
fi

tmp_file="$(mktemp /tmp/migration-debt-regressions-XXXXXX)"
filtered_file="$(mktemp /tmp/migration-debt-regressions-filtered-XXXXXX)"
trap 'rm -f "$tmp_file" "$filtered_file"' EXIT

echo "[architecture] check_migration_debt_regressions"

failures=0
contract_doc="docs/architecture/resolved-runtime-contract-pipeline.md"
expected_scan_anchors=(
  "app"
  "apps"
  "plugins"
  "public"
  "compat_alias_pattern"
  "acl_truth_pattern"
  "resolved_bypass_pattern"
  "studio_dependency_pattern"
  "legacy_artifact_pattern"
  "route_reinterpretation_pattern"
  "hidden_visibility_pattern"
)
active_scan_anchors=(
  "app"
  "apps"
  "plugins"
  "public"
  "compat_alias_pattern"
  "acl_truth_pattern"
  "resolved_bypass_pattern"
  "studio_dependency_pattern"
  "legacy_artifact_pattern"
  "route_reinterpretation_pattern"
  "hidden_visibility_pattern"
)

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

echo "- verifying migration-debt scan contract"
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: migration-debt scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
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

echo "- verifying migration-debt diagnostic baseline coverage"
check_text "$contract_doc" "check_migration_debt_regressions.sh" "resolved runtime contract references migration-debt gate"
check_text "$contract_doc" "Migration-debt regression diagnostics are diff-focused" "contract documents diff-focused migration-debt diagnostics"
check_text "$contract_doc" "new runtime additions must not deepen them" "contract documents no-new-debt rule"
check_text "$contract_doc" "primary links to compatibility aliases" "contract documents compatibility alias regression risk"
check_text "$contract_doc" "ACL presentation/layout fields used as composition truth" "contract documents ACL presentation/layout truth risk"
check_text "$contract_doc" "resolved-experience bypasses" "contract documents resolved-experience bypass risk"
check_text "$contract_doc" "dependencies on Studio runtime internals" "contract documents Studio dependency regression risk"
check_text "$contract_doc" "active usage of frozen legacy composition artifacts" "contract documents frozen artifact regression risk"
check_text "$contract_doc" "direct route reinterpretation behavior" "contract documents route reinterpretation regression risk"
check_text "$contract_doc" "hidden visibility-truth fields" "contract documents hidden visibility truth regression risk"

{
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

  while IFS= read -r file; do
    case "$file" in
      app/*|apps/*|plugins/*|public/*)
        awk -v file="$file" '{ print file ":" FNR ":" $0 }' "$file"
        ;;
    esac
  done < <(git ls-files --others --exclude-standard -- app apps plugins public 2>/dev/null)
} > "$tmp_file"

if [[ ! -s "$tmp_file" ]]; then
  echo "- no runtime diff additions to scan"
  if [[ "$failures" -gt 0 ]]; then
    echo "RESULT: FAIL (migration debt regression risks found)" >&2
    exit 1
  fi
  echo "RESULT: PASS"
  exit 0
fi

filter_matches() {
  local pattern="$1"
  local allowlist="$2"

  : > "$filtered_file"
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "$tmp_file" > "$filtered_file" 2>/dev/null; then
    if [[ -n "$allowlist" ]]; then
      if [[ -n "$RG_BIN" ]]; then
        "$RG_BIN" -n -v -i -S "$allowlist" "$filtered_file" || true
      else
        "$GREP_BIN" -nEiv "$allowlist" "$filtered_file" || true
      fi
    else
      cat "$filtered_file"
    fi
  fi
}

count_filtered_matches() {
  local pattern="$1"
  local allowlist="$2"
  local count

  count="$(
    filter_matches "$pattern" "$allowlist" | wc -l | tr -d ' '
  )"
  echo "$count"
}

echo "- scanning for new primary links to compatibility aliases"
compat_alias_pattern='(href=|action=|location:|redirect|redirectto|url|route|landing|home_template|default_landing_page|sidebar|nav|quick.?link|button|cta).*(["'\''`])(/me([/?#"'\'']|$)|/ops/(design-studio|gui-studio)|/manufacturing(/|["'\''`])|/assembly-plans|/production-plans|/qc-entries|/dispatch-entries)'
compat_alias_allowlist='legacy|compat|redirect route|alias|canonical|do not emit|deprecated|test|spec|diagnostic|migration|debt|check_migration_debt_regressions'
compat_alias_count="$(count_filtered_matches "$compat_alias_pattern" "$compat_alias_allowlist")"
if [[ "$compat_alias_count" != "0" ]]; then
  echo "new primary compatibility-alias emissions found"
  filter_matches "$compat_alias_pattern" "$compat_alias_allowlist"
  failures=$((failures + 1))
fi

echo "- scanning for ACL presentation/layout fields as runtime composition truth"
acl_truth_pattern='(assigned_apps|module_visibility|operator_views|display_surfaces|dashboard_type|me_dashboard_blocks|me_plugin_cards|workspace_profile_key|landing_wrapper).*(if[[:space:]]*\(|foreach[[:space:]]*\(|in_array[[:space:]]*\(|array_filter[[:space:]]*\(|count[[:space:]]*\(|compose|render|visible|visibility|nav|sidebar|widget|card|block|panel|layout|presentation)'
acl_truth_allowlist='active_assigned_apps|resolvedexperience|ResolvedExperience|compat|legacy|parity|diagnostic|normalize|context|ctx|row|payload|schema|migration|debt|select[[:space:]]|insert[[:space:]]|update[[:space:]]|check_migration_debt_regressions'
acl_truth_count="$(count_filtered_matches "$acl_truth_pattern" "$acl_truth_allowlist")"
if [[ "$acl_truth_count" != "0" ]]; then
  echo "new ACL presentation/layout runtime-truth usage found"
  filter_matches "$acl_truth_pattern" "$acl_truth_allowlist"
  failures=$((failures + 1))
fi

echo "- scanning for new resolved-experience bypasses"
resolved_bypass_pattern='(ResolvedExperience|resolved_experience|resolved runtime|resolved contract|compiled contract).*(bypass|skip|ignore|without|fallback|disable|short.?circuit)|((bypass|skip|ignore|without|fallback|disable|short.?circuit).*(ResolvedExperience|resolved_experience|resolved runtime|resolved contract|compiled contract))'
resolved_bypass_allowlist='legacy|compat|parity|diagnostic|migration|debt|docs|comment|check_resolved_experience_truth|check_migration_debt_regressions|must not|do not|prevent|fail|reject|not bypass|does not bypass|without changing runtime behavior'
resolved_bypass_count="$(count_filtered_matches "$resolved_bypass_pattern" "$resolved_bypass_allowlist")"
if [[ "$resolved_bypass_count" != "0" ]]; then
  echo "new resolved-experience bypass language in runtime additions found"
  filter_matches "$resolved_bypass_pattern" "$resolved_bypass_allowlist"
  failures=$((failures + 1))
fi

echo "- scanning for new dependencies on Studio runtime internals"
studio_dependency_pattern='^(app/|apps/Shell/|apps/(Manufacturing|SBAIO|Procurement|Payroll|ERP|LazyPOS|Generated)/|plugins/Base/).*(Apps\\Studio|Studio\\Services|Studio\\Controllers|GuiStudioService|DesignStudioService|StudioGovernanceService|StudioAuthorizationService|/apps/studio|/ops/(design-studio|gui-studio))'
studio_dependency_allowlist='legacy|compat|conditional|enabled|bridge|entry|diagnostic|positioning|branding|studio-generated|app_studio_name|check_migration_debt_regressions'
studio_dependency_count="$(count_filtered_matches "$studio_dependency_pattern" "$studio_dependency_allowlist")"
if [[ "$studio_dependency_count" != "0" ]]; then
  echo "new Core/Shell/business-app Studio runtime dependency found"
  filter_matches "$studio_dependency_pattern" "$studio_dependency_allowlist"
  failures=$((failures + 1))
fi

echo "- scanning for new active usage of frozen legacy composition artifacts"
legacy_artifact_pattern='(ShellCompositionService|SidebarBuilder|DashboardBuilder|plugins/Base/Views/ops/me\.php|renderMyWork|HostSurfaceRegistryService|MyWorkService)'
legacy_artifact_allowlist='legacy|compat|frozen|migration|inventory|debt|diagnostic|comment|prevent|do not|deep dashboardbuilder logic remains|check_migration_debt_regressions'
legacy_artifact_count="$(count_filtered_matches "$legacy_artifact_pattern" "$legacy_artifact_allowlist")"
if [[ "$legacy_artifact_count" != "0" ]]; then
  echo "new active usage of frozen legacy composition artifacts found"
  filter_matches "$legacy_artifact_pattern" "$legacy_artifact_allowlist"
  failures=$((failures + 1))
fi

echo "- scanning for new direct route reinterpretation behavior"
route_reinterpretation_pattern='(\$_SERVER\[['\''"]REQUEST_URI['\''"]\][[:space:]]*=|route_rewrite|rewrite.*route|reinterpret.*route|canonicalize.*route|legacy.*route.*fallback|compat.*route.*fallback|preg_replace\([^)]*(/apps/|/ops/|/admin/|/u/)|str_replace\([^)]*(/apps/|/ops/|/admin/|/u/).*(route|path|uri))'
route_reinterpretation_allowlist='legacy|compat|diagnostic|migration|debt|comment|preprocessor|canonical|redirect|test|spec|check_admin_route_contract|check_migration_debt_regressions'
route_reinterpretation_count="$(count_filtered_matches "$route_reinterpretation_pattern" "$route_reinterpretation_allowlist")"
if [[ "$route_reinterpretation_count" != "0" ]]; then
  echo "new direct route reinterpretation behavior found"
  filter_matches "$route_reinterpretation_pattern" "$route_reinterpretation_allowlist"
  failures=$((failures + 1))
fi

echo "- scanning for hidden visibility truth fields in runtime additions"
hidden_visibility_pattern='(assigned_apps|module_visibility|operator_views|dashboard_type)[^[:cntrl:]]{0,140}(if[[:space:]]*\(|foreach[[:space:]]*\(|in_array[[:space:]]*\(|array_intersect[[:space:]]*\(|array_filter[[:space:]]*\(|count[[:space:]]*\(|empty[[:space:]]*\(|isset[[:space:]]*\(|===|!==|==|!=|\?|visible|visibility|render|compose|nav|sidebar|widget|panel|card|block|layout)'
hidden_visibility_allowlist='active_assigned_apps|ResolvedExperience|resolvedexperience|resolved_experience\.parity|compat|legacy|diagnostic|migration|debt|normalizeOperatorViews|allow_legacy_sidebar_module_visibility|resolveUserContext|context|ctx|row|payload|schema|inventory|select[[:space:]]|insert[[:space:]]|update[[:space:]]|check_migration_debt_regressions'
hidden_visibility_count="$(count_filtered_matches "$hidden_visibility_pattern" "$hidden_visibility_allowlist")"
if [[ "$hidden_visibility_count" != "0" ]]; then
  echo "new hidden visibility-truth field usage found"
  filter_matches "$hidden_visibility_pattern" "$hidden_visibility_allowlist"
  failures=$((failures + 1))
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (migration debt regression risks found)" >&2
  exit 1
fi

echo "RESULT: PASS"

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
expected_scan_anchors=(
  "apps/Shell/Views/operator"
  "apps/Shell/Composers/OperatorSurfaceComposer.php"
  "apps/Shell/Composers/WorkEntryComposer.php"
  "apps/Shell/Services/Operator*.php"
  "apps/Manufacturing/Views/operator"
  "apps/Manufacturing/modules/*/Views/operator"
  "apps/SBAIO/Views/operator"
  "apps/SBAIO/modules/*/Views/operator"
  "apps/Platform/Views/operator"
  "apps/Platform/modules/*/Views/operator"
  "apps/Hospitality/Views/operator"
  "operator_raw_wrapper_escape_pattern"
  "operator_action_target_escape_pattern"
)
active_scan_anchors=(
  "apps/Shell/Views/operator"
  "apps/Shell/Composers/OperatorSurfaceComposer.php"
  "apps/Shell/Composers/WorkEntryComposer.php"
  "apps/Shell/Services/Operator*.php"
  "apps/Manufacturing/Views/operator"
  "apps/Manufacturing/modules/*/Views/operator"
  "apps/SBAIO/Views/operator"
  "apps/SBAIO/modules/*/Views/operator"
  "apps/Platform/Views/operator"
  "apps/Platform/modules/*/Views/operator"
  "apps/Hospitality/Views/operator"
  "operator_raw_wrapper_escape_pattern"
  "operator_action_target_escape_pattern"
)

echo "[architecture] check_operator_confinement"

target_patterns=(
  "apps/Shell/Views/operator"
  "apps/Shell/Composers/OperatorSurfaceComposer.php"
  "apps/Shell/Composers/WorkEntryComposer.php"
  "apps/Shell/Services/Operator*.php"
  "apps/Manufacturing/Views/operator"
  "apps/Manufacturing/modules/*/Views/operator"
  "apps/SBAIO/Views/operator"
  "apps/SBAIO/modules/*/Views/operator"
  "apps/Platform/Views/operator"
  "apps/Platform/modules/*/Views/operator"
  "apps/Hospitality/Views/operator"
)

targets=()
for target in "${target_patterns[@]}"; do
  for path in $target; do
    if [[ -e "$path" ]]; then
      targets+=("$path")
    fi
  done
done

echo "- verifying operator confinement scan contract"
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: operator scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
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

tmp_file="$(mktemp /tmp/operator-confinement-XXXXXX)"
tmp_filtered="$(mktemp /tmp/operator-confinement-filtered-XXXXXX)"
trap 'rm -f "$tmp_file" "$tmp_filtered"' EXIT

scan_and_filter() {
  local pattern="$1"
  local allowlist="$2"
  local label="$3"
  local count

  : > "$tmp_file"
  : > "$tmp_filtered"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" 2>/dev/null > "$tmp_file"; then
    if [[ -n "$RG_BIN" ]]; then
      "$RG_BIN" -n -v "$allowlist" "$tmp_file" > "$tmp_filtered" || true
    else
      "$GREP_BIN" -n -vE "$allowlist" "$tmp_file" > "$tmp_filtered" || true
    fi
  fi

  count="$(wc -l < "$tmp_filtered" | tr -d ' ')"
  if [[ "$count" != "0" ]]; then
    echo "  findings: $label"
    cat "$tmp_filtered"
    failures=$((failures + 1))
  fi
}

raw_escape_pattern='(href|action|formaction|data-href)=["\x27]/(apps|ops|admin)(/|\?|"|\x27|[[:space:]]|$)|Location:[[:space:]]*/(apps|ops|admin)(/|\?|[[:space:]]|$)|window\.location(\.href)?[[:space:]]*=[[:space:]]*["\x27]/(apps|ops|admin)(/|\?|[[:space:]]|$)'
raw_escape_allowlist='switch_to_admin|switch_admin_url|/admin/<\?php echo \$uenc; \?>|/admin/<\?php echo rawurlencode\(\(string\)\(\$data\['"'"'username'"'"'\] \?\? '"'"''"'"'\)\); \?>|\[\"switch_admin_url\"\]'
action_escape_pattern='(<form|<a|<button|data-href|Location:|window\.location).*\/(apps|ops|admin)(/|\?|[[:space:]]|["\x27]|$)'
action_escape_allowlist="$raw_escape_allowlist"

echo "- scanning operator-rendered paths for raw wrapper escapes"
scan_and_filter "$raw_escape_pattern" "$raw_escape_allowlist" "operator links/actions must not escape /u/{username} except documented admin switch affordances"

echo "- scanning operator action/form targets for admin/app route families"
scan_and_filter "$action_escape_pattern" "$action_escape_allowlist" "operator action/form targets must stay in /u/{username}"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (operator confinement risks found)" >&2
  exit 1
fi

echo "- scanned ${#targets[@]} operator confinement target(s)"
echo "RESULT: PASS"

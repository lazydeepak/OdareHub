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
  "apps/Shell/Views/display"
  "apps/Shell/Composers/DisplaySurfaceComposer.php"
  "apps/Shell/Services/DisplayLayerService.php"
  "apps/Manufacturing/Views/display"
  "apps/Manufacturing/modules/*/Views/display"
  "apps/SBAIO/Views/display"
  "apps/SBAIO/modules/*/Views/display"
  "apps/Platform/Views/display"
  "apps/Platform/modules/*/Views/display"
  "display_mutation_markup_pattern"
  "display_write_action_target_pattern"
  "display_wrapper_escape_pattern"
)
active_scan_anchors=(
  "apps/Shell/Views/display"
  "apps/Shell/Composers/DisplaySurfaceComposer.php"
  "apps/Shell/Services/DisplayLayerService.php"
  "apps/Manufacturing/Views/display"
  "apps/Manufacturing/modules/*/Views/display"
  "apps/SBAIO/Views/display"
  "apps/SBAIO/modules/*/Views/display"
  "apps/Platform/Views/display"
  "apps/Platform/modules/*/Views/display"
  "display_mutation_markup_pattern"
  "display_write_action_target_pattern"
  "display_wrapper_escape_pattern"
)

echo "[architecture] check_display_readonly"

target_patterns=(
  "apps/Shell/Views/display"
  "apps/Shell/Composers/DisplaySurfaceComposer.php"
  "apps/Shell/Services/DisplayLayerService.php"
  "apps/Manufacturing/Views/display"
  "apps/Manufacturing/modules/*/Views/display"
  "apps/SBAIO/Views/display"
  "apps/SBAIO/modules/*/Views/display"
  "apps/Platform/Views/display"
  "apps/Platform/modules/*/Views/display"
)

existing_targets=()
for target in "${target_patterns[@]}"; do
  for path in $target; do
    if [[ -e "$path" ]]; then
      existing_targets+=("$path")
    fi
  done
done

echo "- verifying display readonly scan contract"
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: display scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
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

if [[ "${#existing_targets[@]}" -eq 0 ]]; then
  echo "RESULT: PASS (no display surface paths found)"
  exit 0
fi

tmp_file="$(mktemp /tmp/display-readonly-XXXXXX)"
tmp_filtered="$(mktemp /tmp/display-readonly-filtered-XXXXXX)"
trap 'rm -f "$tmp_file" "$tmp_filtered"' EXIT

scan_display() {
  local pattern="$1"
  local allowlist="$2"
  local label="$3"
  local count

  : > "$tmp_file"
  : > "$tmp_filtered"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${existing_targets[@]}" 2>/dev/null > "$tmp_file"; then
    if [[ -n "$allowlist" ]]; then
      if [[ -n "$RG_BIN" ]]; then
        "$RG_BIN" -n -v "$allowlist" "$tmp_file" > "$tmp_filtered" || true
      else
        "$GREP_BIN" -n -vE "$allowlist" "$tmp_file" > "$tmp_filtered" || true
      fi
    else
      cat "$tmp_file" > "$tmp_filtered"
    fi
  fi

  count="$(wc -l < "$tmp_filtered" | tr -d ' ')"
  if [[ "$count" != "0" ]]; then
    echo "  findings: $label"
    cat "$tmp_filtered"
    failures=$((failures + 1))
  fi
}

mutation_markup_pattern='<form\b|method=["\x27]post["\x27]|type=["\x27]submit["\x27]|<button\b|onclick=|onsubmit=|fetch\(|XMLHttpRequest\('
write_action_pattern='(href|action|formaction|data-href)=["\x27][^"\x27]*(create|edit|update|delete|remove|destroy|save|submit)(/|\?|"|\x27|[[:space:]]|$)'
wrapper_escape_pattern='(href|action|formaction|data-href)=["\x27]/(apps|ops|admin|u)(/|\?|"|\x27|[[:space:]]|$)|Location:[[:space:]]*/(apps|ops|admin|u)(/|\?|[[:space:]]|$)'
readonly_allowlist='stylesheet|ipm-logo\.css|classList\.remove|removeClass|post-cutover safety probe'

echo "- scanning display surfaces for mutation-capable markup or handlers"
scan_display "$mutation_markup_pattern" "$readonly_allowlist" "display surfaces must not contain forms, submit buttons, inline mutation handlers, fetch, or XHR"

echo "- scanning display surfaces for write-action targets"
scan_display "$write_action_pattern" "$readonly_allowlist" "display surfaces must not link to create/edit/update/delete/save/submit targets"

echo "- scanning display surfaces for wrapper escapes"
scan_display "$wrapper_escape_pattern" "$readonly_allowlist" "display surfaces must remain self-contained and not escape to app/admin/operator wrappers"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (display readonly violations found)" >&2
  exit 1
fi

echo "- scanned ${#existing_targets[@]} display readonly target(s)"
echo "RESULT: PASS"

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
warnings=0
expected_scan_anchors=(
  "apps/Shell/styles"
  "apps/Shell/Views"
  "apps/Shell/manifest.json"
  "docs/architecture/surface-contribution-contract.md"
  "docs/architecture/business-app-module-ownership-contract.md"
  "public/assets/apps/shell"
  "inline-style-attribute-pattern"
  "inline-style-block-pattern"
  "hardcoded-color-pattern"
  "app-module-selector-prefix-pattern"
  "runtime-additions-selector-regression-pattern"
  "allowed-shell-css-category-contract"
)
active_scan_anchors=(
  "apps/Shell/styles"
  "apps/Shell/Views"
  "apps/Shell/manifest.json"
  "docs/architecture/surface-contribution-contract.md"
  "docs/architecture/business-app-module-ownership-contract.md"
  "public/assets/apps/shell"
  "inline-style-attribute-pattern"
  "inline-style-block-pattern"
  "hardcoded-color-pattern"
  "app-module-selector-prefix-pattern"
  "runtime-additions-selector-regression-pattern"
  "allowed-shell-css-category-contract"
)
allowed_shell_categories=(
  "shell"
  "layout"
  "wrapper"
  "topbar/header/sidebar"
  "tokens/theme variables"
  "utilities"
  "accessibility/focus states"
)
risky_domain_prefixes=(
  "manufacturing"
  "mfg"
  "qc"
  "dispatch"
  "assembly"
  "machine"
  "machines"
  "sbaio"
  "platform"
  "procurement"
  "studio"
  "payroll"
  "erp"
  "lazypos"
  "qr"
  "timecard"
)

echo "[architecture] check_shell_css_ownership"

tmp_colors="$(mktemp /tmp/shell-css-colors-XXXXXX)"
tmp_specific="$(mktemp /tmp/shell-css-specific-XXXXXX)"
tmp_filtered="$(mktemp /tmp/shell-css-filtered-XXXXXX)"
tmp_runtime_additions="$(mktemp /tmp/shell-css-runtime-additions-XXXXXX)"
trap 'rm -f "$tmp_colors" "$tmp_specific" "$tmp_filtered" "$tmp_runtime_additions"' EXIT

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

count_lines() {
  wc -l < "$1" | tr -d ' '
}

filter_matches() {
  local source_file="$1"
  local allowlist="$2"

  : > "$tmp_filtered"
  if [[ -n "$allowlist" ]]; then
    if [[ -n "$RG_BIN" ]]; then
      "$RG_BIN" -n -v "$allowlist" "$source_file" > "$tmp_filtered" || true
    else
      "$GREP_BIN" -n -vE "$allowlist" "$source_file" > "$tmp_filtered" || true
    fi
  else
    cat "$source_file" > "$tmp_filtered"
  fi
}

build_runtime_additions_index() {
  git diff --unified=0 --diff-filter=ACMRT HEAD -- apps/Shell/styles apps/Shell/Views 2>/dev/null \
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
    ' > "$tmp_runtime_additions"
}

echo "- verifying Shell CSS ownership scan contract"
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: Shell CSS scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
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

echo "- verifying Shell CSS ownership documentation alignment"
check_text "docs/architecture/surface-contribution-contract.md" "Shell styles may include only generic primitives, wrapper chrome, and shared tokens." "surface contract keeps Shell CSS generic"
check_text "docs/architecture/surface-contribution-contract.md" "App/module-specific selectors must not be added to Shell CSS." "surface contract documents Shell selector leakage risk"
check_text "docs/architecture/business-app-module-ownership-contract.md" "CSS/assets owned: app styles and assets under the app path; module-specific styling under the module path." "business app/module contract documents CSS owner paths"
check_text "apps/Shell/manifest.json" "generic Shell CSS and theme-token usage" "Shell manifest declares generic CSS ownership"
check_text "apps/Shell/manifest.json" "app/module page-specific CSS" "Shell manifest denies app/module page-specific CSS ownership"

echo "- verifying allowed Shell CSS categories stay generic"
for category in "${allowed_shell_categories[@]}"; do
  echo "  ok: allowed category: $category"
done

echo "- scanning for inline style attributes in Shell views"
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" 'style="|style=\x27' apps/Shell/Views 2>/dev/null; then
  failures=$((failures + 1))
fi

echo "- scanning for inline <style> blocks in Shell views"
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" '<style[[:space:]>]' apps/Shell/Views 2>/dev/null; then
  failures=$((failures + 1))
fi

echo "- scanning for hardcoded colors in Shell styles"
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" '#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(' apps/Shell/styles 2>/dev/null > "$tmp_colors"; then
  filter_matches "$tmp_colors" 'var\(--|color-mix\(|gradient\(|box-shadow|text-shadow|^apps/Shell/styles/components\.css:[0-9]+:[[:space:]]*--'
  cat "$tmp_filtered" || true
  filtered_count="$(count_lines "$tmp_filtered")"
  if [[ "$filtered_count" != "0" ]]; then
    failures=$((failures + 1))
  fi
fi

domain_prefix_pattern='(^|[,{}[:space:]])\.(mfg|manufacturing|qc|dispatch|assembly|machine|machines|sbaio|platform|procurement|studio|payroll|erp|lazypos|qr|timecard)-'
# .platform-mode-* accepted only for the exact Shell-owned mode selectors (ownership-specific, not broad prefix) — line-level filter applied via allowlist path anchor
known_shell_css_debt_allowlist='^apps/Shell/styles/[^:]+:[0-9]+:[[:space:]]*/?\*|data-dashboard-key=|\.qr-|\.timecard-|(^apps/Shell/styles/[^:]+:[0-9]+:[[:space:]]*\.platform-mode-(indicator(--production|--development|--demo|-dot)?|option-label)|^public/assets/apps/shell/styles/[^:]+:[0-9]+:[[:space:]]*\.platform-mode-(indicator(--production|--development|--demo|-dot)?|option-label))'

echo "- scanning for app/module-specific selectors in Shell styles"
echo "  risky prefixes: ${risky_domain_prefixes[*]}"
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$domain_prefix_pattern" apps/Shell/styles 2>/dev/null > "$tmp_specific"; then
  filter_matches "$tmp_specific" "$known_shell_css_debt_allowlist"
  filtered_count="$(count_lines "$tmp_filtered")"
  if [[ "$filtered_count" != "0" ]]; then
    cat "$tmp_filtered"
    failures=$((failures + 1))
  fi

  debt_count="$(count_lines "$tmp_specific")"
  non_debt_count="$filtered_count"
  if [[ "$debt_count" != "$non_debt_count" ]]; then
    allowed_debt_count=$((debt_count - non_debt_count))
    echo "  warning: $allowed_debt_count existing Shell CSS owner-specific selector(s) remain documented compatibility debt"
    warnings=$((warnings + 1))
  fi
fi

echo "- scanning runtime additions for Shell CSS ownership regressions"
build_runtime_additions_index
if [[ -s "$tmp_runtime_additions" ]]; then
  if "$GREP_BIN" -nE "$domain_prefix_pattern" "$tmp_runtime_additions" > "$tmp_specific" 2>/dev/null; then
    cat "$tmp_specific"
    failures=$((failures + 1))
  fi
else
  echo "  note: no Shell CSS/view runtime diff additions to scan"
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (potential Shell CSS ownership violations found)" >&2
  exit 1
fi

echo "warnings: $warnings"
echo "RESULT: PASS"

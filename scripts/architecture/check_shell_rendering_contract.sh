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
  SEARCH_ARGS=(-RInE)
fi

failures=0
warnings=0
tmp_dir="$(mktemp -d /tmp/shell-rendering-contract-XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

echo "[architecture] check_shell_rendering_contract"

echo "- checking overlay infrastructure"

overlay_checks=(
  "apps/Shell/styles/shell-surfaces.css:.shell-overlay"
  "apps/Shell/styles/shell-navigation.css:avatar-backdrop"
  "public/views/layouts/footer.php:shell-overlay"
  "apps/Shell/Services/ShellOverlayFramework.php:const DEFINITIONS = Object.freeze"
  "apps/Shell/Services/ShellOverlayFramework.php:const manager = {"
  "apps/Shell/Services/ShellOverlayFramework.php:function applyVisual()"
  "apps/Shell/Services/ShellOverlayFramework.php:function acquireScrollLock(payload)"
)

for entry in "${overlay_checks[@]}"; do
  file="${entry%%:*}"
  needle="${entry##*:}"
  if [[ ! -f "$file" ]]; then
    echo "  fail: missing file $file" >&2
    failures=$((failures + 1))
    continue
  fi
  if "$GREP_BIN" -Fq -- "$needle" "$file"; then
    echo "  ok: $needle exists in $file"
  else
    echo "  fail: $needle not found in $file" >&2
    failures=$((failures + 1))
  fi
done

echo "- checking Shell layer and breakpoint registries"

registry_checks=(
  "--z-base: 0"
  "--z-workspace: 10"
  "--z-navigation: 20"
  "--z-topbar: 30"
  "--z-dropdown: 40"
  "--z-drawer: 50"
  "--z-backdrop: 60"
  "--z-modal: 70"
  "--z-overlay: 80"
  "--z-scanner: 90"
  "--z-system-emergency: 100"
  "--bp-mobile: 480px"
  "--bp-tablet: 768px"
  "--bp-desktop: 1024px"
  "--bp-wide: 1366px"
  "--bp-ultrawide: 1920px"
  "--bp-display: 2560px"
)

for registry_entry in "${registry_checks[@]}"; do
  registry_count="$("$GREP_BIN" -RhsF -- "$registry_entry" apps/Shell/styles/shell-*.css | wc -l | tr -d ' ')"
  if [[ "$registry_count" == "1" ]] && "$GREP_BIN" -Fq -- "$registry_entry" apps/Shell/styles/shell-tokens.css; then
    :
  else
    echo "  fail: registry entry '$registry_entry' must exist exactly once in shell-tokens.css (found $registry_count)" >&2
    failures=$((failures + 1))
  fi
done
echo "  ok: canonical registries are centralized in shell-tokens.css"

echo "- checking theme boundary (no rendering selectors in theme sources)"

tmp_theme="$tmp_dir/theme"

theme_rendering_pattern='^\s*[^@/:}{][^:{}]*\b(z-index|position\s*:\s*(fixed|sticky|absolute)|overflow\s*:\s*(hidden|auto|scroll)|scroll-behavior|overscroll-behavior)\b'
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$theme_rendering_pattern" resources/themes/*.css resources/themes/semantic/*.css 2>/dev/null > "$tmp_theme"; then
  filtered_count="$(wc -l < "$tmp_theme" | tr -d ' ')"
  if [[ "$filtered_count" -gt 0 ]]; then
    cat "$tmp_theme"
    echo "  fail: theme sources contain rendering selectors ($filtered_count found)" >&2
    failures=$((failures + 1))
  else
    echo "  ok: theme sources are clean of rendering selectors"
  fi
else
  echo "  ok: theme sources are clean of rendering selectors"
fi

echo "- checking z-index regression (new hardcoded values in Shell CSS diff)"

tmp_zindex="$tmp_dir/zindex"

shell_css_files=(apps/Shell/styles/shell-*.css)

existing_raw=0
for css_file in "${shell_css_files[@]}"; do
  if [[ -f "$css_file" ]]; then
    file_raw="$("$GREP_BIN" -c 'z-index:[[:space:]]*[0-9]' "$css_file" 2>/dev/null || true)"
    file_raw="${file_raw:-0}"
    existing_raw=$((existing_raw + file_raw))
  fi
done
echo "  info: $existing_raw hardcoded z-index values across canonical Shell CSS"

if git diff --unified=0 HEAD -- "${shell_css_files[@]}" 2>/dev/null \
  | "$GREP_BIN" -E '^\+.*z-index:[[:space:]]*[0-9]' \
  | "$GREP_BIN" -Fv '+++' \
  > "$tmp_zindex"; then
  new_count="$(wc -l < "$tmp_zindex" | tr -d ' ')"
  if [[ "$new_count" -gt 0 ]]; then
    echo "  fail: $new_count new hardcoded z-index value(s) introduced" >&2
    cat "$tmp_zindex"
    failures=$((failures + 1))
  fi
else
  echo "  ok: no new hardcoded z-index values in working tree diff"
fi

tmp_diff="$tmp_dir/diff"

if git diff --unified=0 HEAD -- "${shell_css_files[@]}" 2>/dev/null | "$GREP_BIN" -E '^\+.*z-index:' | "$GREP_BIN" -Fv '+++' > "$tmp_diff"; then
  token_refs=0
  raw_refs=0
  while IFS= read -r line; do
    if echo "$line" | "$GREP_BIN" -q 'var(--z-'; then
      token_refs=$((token_refs + 1))
    else
      raw_refs=$((raw_refs + 1))
    fi
  done < "$tmp_diff"
  if [[ "$token_refs" -gt 0 ]]; then
    echo "  info: $token_refs tokenized z-index addition(s) in working tree (contract-compliant)"
  fi
fi

echo "- checking breakpoint ownership (new hardcoded px breakpoints in Shell CSS diff)"

tmp_bp="$tmp_dir/breakpoints"

if git diff --unified=0 HEAD -- "${shell_css_files[@]}" 2>/dev/null | "$GREP_BIN" -E '^\+.*@media[^(]*\(.*px' | "$GREP_BIN" -Fv '+++' > "$tmp_bp"; then
  new_bp="$(wc -l < "$tmp_bp" | tr -d ' ')"
  if [[ "$new_bp" -gt 0 ]]; then
    echo "  warning: $new_bp hardcoded px breakpoint addition(s) in working tree (expected: use canonical breakpoint tokens)" >&2
    cat "$tmp_bp"
    warnings=$((warnings + 1))
  fi
else
  echo "  ok: no new hardcoded px breakpoint values in working tree diff"
fi

tmp_external_bp="$tmp_dir/external-breakpoints"
git diff --unified=0 HEAD -- '*.css' 2>/dev/null | awk '
  /^\+\+\+ b\// { file = substr($0, 7); next }
  /^\+[^+].*@media[^(]*\(.*[0-9]+(\.[0-9]+)?px/ && file !~ /^apps\/Shell\/styles\// {
    print file ": " substr($0, 2)
  }
' > "$tmp_external_bp"
if [[ -s "$tmp_external_bp" ]]; then
  external_bp_count="$(wc -l < "$tmp_external_bp" | tr -d ' ')"
  echo "  warning: $external_bp_count new breakpoint definition(s) outside approved Shell locations" >&2
  cat "$tmp_external_bp"
  warnings=$((warnings + 1))
else
  echo "  ok: no new breakpoint definitions outside approved Shell locations"
fi

echo "- checking overlay ownership (no new overlay behavior outside Shell CSS)"

tmp_overlay="$tmp_dir/overlay"

if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" '\.(shell-overlay|avatar-backdrop|action-panel-backdrop)\b|\bshell-overlay\b' apps/Shell/styles/*.css 2>/dev/null > "$tmp_overlay"; then
  overlay_count="$(wc -l < "$tmp_overlay" | tr -d ' ')"
  echo "  ok: Shell CSS owns overlay class definitions ($overlay_count occurrences)"
else
  echo "  fail: cannot find overlay class definitions in Shell CSS" >&2
  failures=$((failures + 1))
fi

tmp_non_shell="$tmp_dir/non-shell-overlay"

non_shell_overlay_pattern='\.(shell-overlay|avatar-backdrop|action-panel-backdrop)\b|\bshell-overlay\b'
if [[ -n "$RG_BIN" ]]; then
  "$RG_BIN" -n -S --glob '*.css' "$non_shell_overlay_pattern" apps 2>/dev/null > "$tmp_non_shell" || true
else
  find apps -type f -name '*.css' -exec "$GREP_BIN" -HnE "$non_shell_overlay_pattern" {} + 2>/dev/null > "$tmp_non_shell" || true
fi
if [[ -s "$tmp_non_shell" ]]; then
  filtered="$("$GREP_BIN" -v '^apps/Shell/styles/' "$tmp_non_shell" 2>/dev/null || true)"
  if [[ -n "$filtered" ]]; then
    echo "  fail: overlay classes defined outside Shell CSS" >&2
    echo "$filtered"
    failures=$((failures + 1))
  fi
else
  echo "  ok: no overlay classes defined outside Shell CSS"
fi

tmp_external_overlay="$tmp_dir/external-overlay"
git diff --unified=0 HEAD -- '*.css' 2>/dev/null | awk '
  /^\+\+\+ b\// { file = substr($0, 7); next }
  /^\+[^+].*(overlay|backdrop|drawer|modal)/ && file !~ /^apps\/Shell\/styles\// {
    print file ": " substr($0, 2)
  }
' > "$tmp_external_overlay"
if [[ -s "$tmp_external_overlay" ]]; then
  external_overlay_count="$(wc -l < "$tmp_external_overlay" | tr -d ' ')"
  echo "  warning: $external_overlay_count possible local overlay addition(s) outside approved Shell ownership" >&2
  cat "$tmp_external_overlay"
  warnings=$((warnings + 1))
else
  echo "  ok: no possible local overlay additions outside approved Shell ownership"
fi

echo ""
echo "warnings: $warnings"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Shell rendering contract violations)" >&2
  exit 1
fi

echo "info: debt=$existing_raw raw z-index values remain un-tokenized (contract-documented, new additions blocked)"
echo "RESULT: PASS"

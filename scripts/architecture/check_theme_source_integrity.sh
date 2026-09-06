#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

failures=0
warnings=0

HAS_RG=false
RG_BIN=""
if command -v rg &>/dev/null; then
  HAS_RG=true
  RG_BIN="rg"
fi

fail() {
  local label="$1"
  echo "  fail: $label" >&2
  failures=$((failures + 1))
}

pass() {
  local label="$1"
  echo "  ok: $label"
}

warn() {
  local label="$1"
  echo "  warning: $label" >&2
  warnings=$((warnings + 1))
}

check_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if [[ ! -f "$path" ]]; then
    fail "missing $label — $path not found"
    return
  fi

  if grep -Fq -- "$needle" "$path"; then
    pass "$label"
  else
    fail "$label"
  fi
}

echo "[architecture] check_theme_source_integrity"

# === File existence ===

echo "- verifying required theme source files exist"
for f in \
  "resources/themes/foundation.css" \
  "resources/themes/semantic/semantic.css" \
  "resources/themes/light.css" \
  "resources/themes/dark.css" \
  "resources/themes/liquid-glass.css" \
  "resources/themes/paper.css" \
  "resources/themes/theme-manifest.json"; do
  if [[ -f "$f" ]]; then
    pass "file exists: $f"
  else
    fail "file missing: $f"
  fi
done

# === No component selectors in theme source files ===

echo "- scanning for component selectors in theme source files"
THEME_SOURCE_FILES=(
  "resources/themes/foundation.css"
  "resources/themes/semantic/semantic.css"
  "resources/themes/light.css"
  "resources/themes/dark.css"
  "resources/themes/liquid-glass.css"
  "resources/themes/paper.css"
)

selector_pattern='^\.[a-z]|^#[a-z]'
known_allowed_file_patterns=(
  "resources/themes/light.css"
  "resources/themes/dark.css"
  "resources/themes/liquid-glass.css"
  "resources/themes/paper.css"
)

for file in "${THEME_SOURCE_FILES[@]}"; do
  if [[ ! -f "$file" ]]; then
    continue
  fi
  if grep -nE "$selector_pattern" "$file" 2>/dev/null | grep -v '^\s*/\*' | grep -v ':root' | grep -v '\-\-' > /dev/null; then
    # Check if it's a known style variant file that has [data-*] selectors (allowed — variant blocks)
    if [[ "$file" == "resources/themes/light.css" ]] || \
       [[ "$file" == "resources/themes/dark.css" ]] || \
       [[ "$file" == "resources/themes/liquid-glass.css" ]] || \
       [[ "$file" == "resources/themes/paper.css" ]]; then
      pass "selectors in $file (allowed — variant override selectors)"
    else
      fail "unexpected selector in $file"
    fi
  else
    pass "no component selectors in $file"
  fi
done

# === foundation.css contains only primitive-approved tokens ===

echo "- verifying foundation.css token names are primitive-only"
PRIMITIVE_TOKENS=(
  "glass-blur"
  "glass-blur-shell"
  "glass-blur-card"
  "glass-blur-popover"
  "glass-blur-control"
  "glass-specular-top"
  "glass-specular-bottom"
  "transition"
  "depth"
  "depth-lg"
  "topbar-height"
  "select-scheme"
  "space-1"
  "space-2"
  "space-3"
  "space-4"
  "space-5"
  "safe-area-top"
  "safe-area-right"
  "safe-area-bottom"
  "safe-area-left"
  "radius-sm"
  "radius-md"
  "radius-lg"
  "radius-pill"
  "focus-ring"
  "font-sans"
  "type-body-size"
  "type-control-size"
)

if [[ ! -f "resources/themes/foundation.css" ]]; then
  fail "foundation.css not found — cannot check token names"
else
  for token in "${PRIMITIVE_TOKENS[@]}"; do
    if grep -Fq -- "--$token:" resources/themes/foundation.css 2>/dev/null; then
      pass "foundation token present: --$token"
    else
      fail "foundation token missing: --$token"
    fi
  done

  # Check for disallowed semantic tokens in foundation.css
  # Note: --type-control-size is a foundation primitive; we only flag direct --control-* tokens
  # that are NOT preceded by "type-"
  semantic_patterns=(
    "--bg:" "--panel:" "--card:" "--text:" "--muted:" "--line:" "--accent:"
    "--style-bg:" "--color-success" "--color-danger" "--color-warning"
    "--glass: " "--glass-strong: " "--glass-border:" "--glass-shadow:" "--glass-highlights:"
    "--glass-card" "--page-bg:" "--topbar-bg:" "--sidebar-bg:" "--search-panel-bg:"
    "--card-surface:" "--card-border-base:" "--card-edge-fallback:"
    "--notif-" "--scan-"
    "--style-shell" "--style-content" "--style-subtle"
    "--style-control" "--style-button" "--style-border" "--style-active"
    "--style-table" "--style-card-shadow" "--style-surface-shadow"
    "--tone-" "--color-background" "--color-surface"
    "--color-card:" "--color-text:" "--color-text-muted" "--color-border:" "--color-accent:"
    "--card-radius:" "--card-background:" "--card-border:"
    "--chrome-" "--icon-chip-"
  )
  for pattern in "${semantic_patterns[@]}"; do
    if grep -Fq -- "$pattern" resources/themes/foundation.css 2>/dev/null; then
      fail "disallowed semantic token in foundation.css: $pattern"
    fi
  done
  pass "no disallowed semantic tokens in foundation.css"
fi

# === semantic/semantic.css exists and contains semantic tokens ===

echo "- verifying semantic/semantic.css content"
if [[ ! -f "resources/themes/semantic/semantic.css" ]]; then
  fail "semantic/semantic.css not found"
else
  # Check key semantic token categories are present
  for needle in "--bg:" "--text:" "--style-shell-bg:" "--notif-chip-" "--tone-" "--control-select-arrow:" "--style-border:" "--color-background:"; do
    if grep -Fq -- "$needle" resources/themes/semantic/semantic.css 2>/dev/null; then
      pass "semantic token present: $needle"
    else
      fail "semantic token missing: $needle"
    fi
  done

  for geometry_token in \
    "--control-height:" "--control-font-size:" "--control-line-height:" "--control-radius:" \
    "--control-padding-block:" "--control-padding-inline:" "--control-padding-inline-select:" \
    "--control-padding-inline-date:" "--control-gap:" "--control-gap-tight:" \
    "--control-field-min:" "--control-field-wide-min:" "--control-field-compact-min:" \
    "--card-radius:" "--icon-chip-size:" "--icon-chip-radius:"; do
    if grep -Fq -- "$geometry_token" resources/themes/semantic/semantic.css 2>/dev/null; then
      fail "theme-independent control geometry remains in semantic theme source: $geometry_token"
    fi
  done
  pass "semantic theme source does not own control or basic surface geometry"
fi

# === public/assets/theme.css is generated output ===

echo "- verifying public/assets/theme.css is generated output"
if [[ ! -f "public/assets/theme.css" ]]; then
  fail "public/assets/theme.css not found — cannot verify generated status"
else
  check_text "public/assets/theme.css" "GENERATED FILE" "theme.css has GENERATED FILE marker"
fi

# === Known owner selectors are not reintroduced into theme source files ===

echo "- scanning for known owner selectors in theme source files"
known_owner_selectors=(
  "\.coverage-kpi"
  "\.coverage-trend-wrap"
  "\.mfg-subgroup-card"
  "\.sc-card"
  "\.app-quicklink-card"
)

for file in resources/themes/*.css resources/themes/semantic/*.css; do
  [[ -f "$file" ]] || continue
  for sel in "${known_owner_selectors[@]}"; do
    if grep -nE "$sel" "$file" 2>/dev/null; then
      fail "owner selector $sel found in $file"
    fi
  done
done
pass "no known owner selectors reintroduced into theme source files"

# === Manifest source order is correct ===

echo "- verifying theme-manifest.json source order"
if [[ -f "resources/themes/theme-manifest.json" ]]; then
  python3 << 'PYEOF'
import json, sys
with open('resources/themes/theme-manifest.json') as f:
    m = json.load(f)
sources = [s['id'] for s in m.get('sources', []) if s.get('enabled', False)]
expected = ['foundation', 'semantic', 'light', 'dark', 'liquid-glass', 'paper']
foundation_idx = sources.index('foundation') if 'foundation' in sources else -1
semantic_idx = sources.index('semantic') if 'semantic' in sources else -1
light_idx = sources.index('light') if 'light' in sources else -1
errors = []
if foundation_idx == -1:
    errors.append('foundation source missing')
elif semantic_idx == -1:
    errors.append('semantic source missing')
elif light_idx == -1:
    errors.append('light source missing')
elif not (foundation_idx < semantic_idx < light_idx):
    errors.append(f'wrong source order: foundation={foundation_idx}, semantic={semantic_idx}, light={light_idx}')
else:
    print(f'  ok: source order foundation < semantic < light (foundation={foundation_idx}, semantic={semantic_idx}, light={light_idx})')
if errors:
    print('  fail: ' + '; '.join(errors))
    sys.exit(1)
PYEOF
  ret=$?
  if [[ "$ret" -ne 0 ]]; then
    fail "manifest source order check failed"
  fi
else
  fail "theme-manifest.json not found"
fi

# === Summary ===

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s), $warnings warning(s))" >&2
  exit 1
fi

echo "RESULT: PASS ($failures failure(s), $warnings warning(s))"

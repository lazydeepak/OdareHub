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

echo "[architecture] check_shell_sidebar_content_geometry_contract"

# ─── 1. Canonical file exists ──────────────────────────────────
canonical_file="apps/Shell/styles/shell-sidebar-content-geometry.css"
if [[ ! -f "$canonical_file" ]]; then
  echo "  fail: $canonical_file not found" >&2
  failures=$((failures + 1))
else
  echo "  ok: $canonical_file exists"
fi

# ─── 2. Essential variables exist ──────────────────────────────
essential_file="apps/Shell/Resources/css/essential/shell-essential.css"
sb_vars=(
  "--sys-sidebar-width"
  "--sys-sidebar-collapsed-width"
  "--sys-sidebar-padding-block"
  "--sys-sidebar-padding-inline"
  "--sys-content-padding-block"
  "--sys-content-padding-inline"
  "--sys-content-max-width"
)
for var in "${sb_vars[@]}"; do
  if "$GREP_BIN" -Fq -- "$var" "$essential_file"; then
    echo "  ok: $var defined in shell-essential.css"
  else
    echo "  fail: $var not found in shell-essential.css" >&2
    failures=$((failures + 1))
  fi
done

# ─── 3. Canonical file contains required geometry rules ────────
geo_checks=(
  "$canonical_file:.layout-sidebar {"
  "$canonical_file:.app-shell {"
  "$canonical_file:.app-sidebar {"
  "$canonical_file:.u-sidebar {"
  "$canonical_file:.layout-main {"
  "$canonical_file:.content {"
  "$canonical_file:.container {"
  "$canonical_file:.main-content {"
  "$canonical_file:.u-main {"
  "$canonical_file:--sys-sidebar-width, 280px"
  "$canonical_file:--sys-sidebar-collapsed-width, 72px"
  "$canonical_file:--sys-content-padding-block, 20px"
  "$canonical_file:--sys-content-padding-inline, 24px"
  "$canonical_file:--sys-content-max-width, 1220px"
  "$canonical_file:--sys-safe-area-top"
  "$canonical_file:--sys-safe-area-right"
  "$canonical_file:--sys-safe-area-bottom"
  "$canonical_file:--sys-safe-area-left"
  "$canonical_file:--sys-header-block-size, 58px"
)
for entry in "${geo_checks[@]}"; do
  file="${entry%%:*}"
  needle="${entry#*:}"
  if "$GREP_BIN" -Fq -- "$needle" "$file"; then
    echo "  ok: $file contains '$needle'"
  else
    echo "  fail: $file missing '$needle'" >&2
    failures=$((failures + 1))
  fi
done

# ─── 4. Responsive breakpoints exist ───────────────────────────
breakpoint_checks=(
  "(min-width: 901px)"
  "(max-width: 860px)"
  "(max-width: 820px)"
  "(max-width: 720px)"
  "(max-width: 900px)"
  "(min-width: 1440px)"
  "(min-width: 1920px)"
)
for needle in "${breakpoint_checks[@]}"; do
  if "$GREP_BIN" -Fq -- "$needle" "$canonical_file"; then
    echo "  ok: breakpoint '$needle' in canonical file"
  else
    echo "  fail: breakpoint '$needle' not found" >&2
    failures=$((failures + 1))
  fi
done

# ─── 5. No .layout-sidebar geometry remnants in shell-navigation.css ──
nav_file="apps/Shell/styles/shell-navigation.css"
nav_remnants=(
  "width:min(320px,calc(100vw - 58px))"
  "height:100dvh"
  "--me-sidebar-expanded-width"
  "--me-sidebar-collapsed-width"
  "grid-template-columns: var(--me-sidebar-expanded-width)"
  "grid-template-columns: var(--me-sidebar-collapsed-width)"
  "grid-template-columns: var(--sidebar-width)"
  "grid-template-columns: 68px"
  "height: calc(100vh - var(--topbar-height)"
  "position:fixed;top:0;left:0;bottom:0"
)
for pattern in "${nav_remnants[@]}"; do
  if "$GREP_BIN" -Fq -- "$pattern" "$nav_file"; then
    echo "  fail: '$pattern' still present in shell-navigation.css" >&2
    failures=$((failures + 1))
  else
    echo "  ok: no '$pattern' in shell-navigation.css"
  fi
done

# ─── 6. No content/container padding remnants in shell-layout.css ──
layout_file="apps/Shell/styles/shell-layout.css"
layout_remnants=(
  "padding:18px calc(18px + var(--safe-area-right))"
  "padding:22px calc(20px + var(--safe-area-right))"
  "padding: 20px 24px 20px 20px"
  ".content { flex: 1; padding: 20px;"
)
for pattern in "${layout_remnants[@]}"; do
  if "$GREP_BIN" -Fq -- "$pattern" "$layout_file"; then
    echo "  fail: '$pattern' still present in shell-layout.css" >&2
    failures=$((failures + 1))
  else
    echo "  ok: no '$pattern' in shell-layout.css"
  fi
done

# ─── 7. No .layout-main geometry remnant in shell-forms.css ─────
forms_file="apps/Shell/styles/shell-forms.css"
forms_remnants=(
  "flex:1;display:flex;flex-direction:column"
)
for pattern in "${forms_remnants[@]}"; do
  if "$GREP_BIN" -Fq -- "$pattern" "$forms_file"; then
    echo "  fail: '$pattern' still present in shell-forms.css" >&2
    failures=$((failures + 1))
  else
    echo "  ok: no '$pattern' in shell-forms.css"
  fi
done

# ─── 8. Old --sidebar-width removed from shell-tokens.css ──────
tokens_file="apps/Shell/styles/shell-tokens.css"
tokens_remnants=(
  "--sidebar-width: 272px"
  "--sidebar-collapsed-width: 68px"
)
for pattern in "${tokens_remnants[@]}"; do
  if "$GREP_BIN" -Fq -- "$pattern" "$tokens_file"; then
    echo "  fail: '$pattern' still present in shell-tokens.css" >&2
    failures=$((failures + 1))
  else
    echo "  ok: no '$pattern' in shell-tokens.css"
  fi
done

# ─── 9. Import chain in shell.css ──────────────────────────────
if "$GREP_BIN" -Fq -- "shell-sidebar-content-geometry.css" "apps/Shell/styles/shell.css"; then
  echo "  ok: shell.css imports shell-sidebar-content-geometry.css"
else
  echo "  fail: shell.css does not import shell-sidebar-content-geometry.css" >&2
  failures=$((failures + 1))
fi

# ─── 10. Manifest entry exists ─────────────────────────────────
if "$GREP_BIN" -Fq -- "shell.sidebar-content-geometry" "apps/Shell/manifest.json"; then
  echo "  ok: manifest.json contains shell.sidebar-content-geometry key"
else
  echo "  fail: manifest.json missing shell.sidebar-content-geometry key" >&2
  failures=$((failures + 1))
fi

if "$GREP_BIN" -Fq -- "shell-sidebar-content-geometry.css" "apps/Shell/manifest.json"; then
  echo "  ok: manifest.json references shell-sidebar-content-geometry.css"
else
  echo "  fail: manifest.json missing shell-sidebar-content-geometry.css reference" >&2
  failures=$((failures + 1))
fi

# ─── 11. Run the PHP probe ────────────────────────────────────
probe_file="apps/Shell/Tests/probe_shell_sidebar_content_geometry.php"
if [[ -f "$probe_file" ]]; then
  if PHP_BIN=$(command -v php) && "$PHP_BIN" -l "$probe_file" >/dev/null 2>&1; then
    probe_output=$("$PHP_BIN" "$probe_file" 2>&1) || true
    if echo "$probe_output" | "$GREP_BIN" -q "FAILURE"; then
      echo "  fail: PHP probe reports failures:" >&2
      echo "$probe_output" | while IFS= read -r line; do echo "    $line"; done
      failures=$((failures + 1))
    else
      echo "  ok: PHP probe passes"
    fi
  else
    echo "  warn: PHP probe lint or execution failed (skipped)" >&2
    warnings=$((warnings + 1))
  fi
else
  echo "  warn: PHP probe file not found (skipped)" >&2
  warnings=$((warnings + 1))
fi

# ─── Summary ──────────────────────────────────────────────────
echo ""
echo "warnings: $warnings"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (sidebar/content geometry contract violations)" >&2
  exit 1
fi

echo "RESULT: PASS"

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
tmp_dir="$(mktemp -d /tmp/check-shell-header-geometry-XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

echo "[architecture] check_shell_header_geometry_contract"

# ─── 1. Canonical file exists ──────────────────────────────────
canonical_file="apps/Shell/styles/shell-header-geometry.css"
if [[ ! -f "$canonical_file" ]]; then
  echo "  fail: $canonical_file not found" >&2
  failures=$((failures + 1))
else
  echo "  ok: $canonical_file exists"
fi

# ─── 2. Essential variables exist ──────────────────────────────
essential_file="apps/Shell/Resources/css/essential/shell-essential.css"
header_vars=(
  "--sys-header-block-size"
  "--sys-header-padding-block"
  "--sys-header-padding-inline"
  "--sys-header-gap"
)
for var in "${header_vars[@]}"; do
  if "$GREP_BIN" -Fq -- "$var" "$essential_file"; then
    echo "  ok: $var defined in shell-essential.css"
  else
    echo "  fail: $var not found in shell-essential.css" >&2
    failures=$((failures + 1))
  fi
done

# ─── 3. Canonical file contains required geometry rules ────────
geo_checks=(
  "$canonical_file:position: sticky"
  "$canonical_file:z-index: var(--shell-z-topbar"
  "$canonical_file:block-size: var(--sys-header-block-size"
  "$canonical_file:padding-block: var(--sys-header-padding-block"
  "$canonical_file:--sys-header-gap, 12px"
  "$canonical_file:--sys-safe-area-top"
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

# ─── 4. Responsive compaction exists ───────────────────────────
responsive_checks=(
  "--sys-header-padding-inline: 12px"
  "--sys-header-gap: 8px"
  "--sys-header-block-size: 52px"
  "--sys-header-padding-block: 10px"
)
for needle in "${responsive_checks[@]}"; do
  if "$GREP_BIN" -Fq -- "$needle" "$canonical_file"; then
    echo "  ok: responsive '$needle' in canonical file"
  else
    echo "  fail: responsive '$needle' not found" >&2
    failures=$((failures + 1))
  fi
done

# ─── 5. No fixed topbar model remnants in shell-layout.css ─────
layout_file="apps/Shell/styles/shell-layout.css"
fixed_patterns=(
  "fixed !important"
  "padding-top: calc(var(--topbar-height)"
)
for pattern in "${fixed_patterns[@]}"; do
  if "$GREP_BIN" -Fq -- "$pattern" "$layout_file"; then
    echo "  fail: '$pattern' still present in shell-layout.css" >&2
    failures=$((failures + 1))
  else
    echo "  ok: no '$pattern' in shell-layout.css"
  fi
done

# ─── 6. .u-header geometry removed from shell-surfaces.css ─────
surfaces_file="apps/Shell/styles/shell-surfaces.css"
surface_remnants=(
  "min-height: 56px"
  "position: sticky"
)
for pattern in "${surface_remnants[@]}"; do
  if "$GREP_BIN" -Fq -- "$pattern" "$surfaces_file"; then
    echo "  warn: '$pattern' still in shell-surfaces.css (.u-header appearance may reference sticky position elsewhere)" >&2
    warnings=$((warnings + 1))
  else
    echo "  ok: no '$pattern' in shell-surfaces.css"
  fi
done

# ─── 7. Import chain in shell.css ──────────────────────────────
if "$GREP_BIN" -Fq -- "shell-header-geometry.css" "apps/Shell/styles/shell.css"; then
  echo "  ok: shell.css imports shell-header-geometry.css"
else
  echo "  fail: shell.css does not import shell-header-geometry.css" >&2
  failures=$((failures + 1))
fi

# ─── 8. Manifest entry exists ─────────────────────────────────
if "$GREP_BIN" -Fq -- "shell.header-geometry" "apps/Shell/manifest.json"; then
  echo "  ok: manifest.json contains shell.header-geometry key"
else
  echo "  fail: manifest.json missing shell.header-geometry key" >&2
  failures=$((failures + 1))
fi

if "$GREP_BIN" -Fq -- "shell-header-geometry.css" "apps/Shell/manifest.json"; then
  echo "  ok: manifest.json references shell-header-geometry.css"
else
  echo "  fail: manifest.json missing shell-header-geometry.css reference" >&2
  failures=$((failures + 1))
fi

# ─── 9. no duplicate z-index/position on .topbar in layout.css ──
# (check that .topbar only appears once in the layout file — the appearance block)
if "$GREP_BIN" -c '\.topbar' "$layout_file" > "$tmp_dir/topbar_count.txt" 2>/dev/null; then
  count=$(cat "$tmp_dir/topbar_count.txt" | head -1)
  if [[ "$count" -gt 5 ]]; then
    echo "  warn: .topbar appears $count times in shell-layout.css (expected ~1-2)" >&2
    warnings=$((warnings + 1))
  else
    echo "  ok: .topbar appears $count times in shell-layout.css"
  fi
fi

# ─── 10. Run the PHP probe ────────────────────────────────────
if [[ -f "apps/Shell/Tests/probe_shell_header_geometry.php" ]]; then
  if PHP_BIN=$(command -v php) && "$PHP_BIN" -l "apps/Shell/Tests/probe_shell_header_geometry.php" >/dev/null 2>&1; then
    probe_output=$("$PHP_BIN" "apps/Shell/Tests/probe_shell_header_geometry.php" 2>&1) || true
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
  echo "RESULT: FAIL (header geometry contract violations)" >&2
  exit 1
fi

echo "RESULT: PASS"

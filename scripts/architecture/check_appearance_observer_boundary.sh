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

failed=0
warn=0

check_no_matches() {
  local label="$1" pattern="$2" path="$3"
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "$path" 2>/dev/null | grep -vE '^\s*(\*|\/\/|<!--|#)\s*' >/dev/null 2>&1; then
    echo "  FAIL: ${label} — found forbidden pattern in ${path}"
    "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "$path" 2>/dev/null | grep -vE '^\s*(\*|\/\/|<!--|#)\s*' || true
    failed=$((failed + 1))
  else
    echo "  ok: ${label}"
  fi
}

check_has_matches() {
  local label="$1" pattern="$2" path="$3"
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "$path" 2>/dev/null | grep -vE '^\s*(\*|\/\/|<!--|#)\s*' >/dev/null 2>&1; then
    echo "  ok: ${label}"
  else
    echo "  FAIL: ${label} — required pattern not found in ${path}"
    failed=$((failed + 1))
  fi
}

echo "Appearance Observer Boundary Check"
echo "---"
echo ""

# ---------------------------------------------------------------------------
# 1. Passive observer in Studio tool view must never write to localStorage
# ---------------------------------------------------------------------------
OBSERVER_VIEW="apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Views/index.php"
if [[ -f "$OBSERVER_VIEW" ]]; then
  check_no_matches "Observer has no localStorage.setItem" 'localStorage\s*\.\s*setItem' "$OBSERVER_VIEW"
  check_no_matches "Observer has no fetch/network calls" '\bfetch\s*\(|\bXMLHttpRequest\b|navigator\.sendBeacon' "$OBSERVER_VIEW"
  check_no_matches "Observer has no XHR/axios" '\baxios\b|\.ajax\b|\$\.post\b' "$OBSERVER_VIEW"
  check_no_matches "Observer has no document.cookie write" 'document\.cookie\s*=' "$OBSERVER_VIEW"
  check_no_matches "Observer has no form submission" 'form\s*\.\s*submit|\.submit\s*\(' "$OBSERVER_VIEW"
  check_no_matches "Observer has no POST route references" 'method.*POST|action.*post' "$OBSERVER_VIEW"
  echo ""
fi

# ---------------------------------------------------------------------------
# 2. Observer must read KNOWN_MODES and SHORTHAND_MAP from server-provided
#    JSON, not hardcode them.
# ---------------------------------------------------------------------------
if [[ -f "$OBSERVER_VIEW" ]]; then
  check_has_matches "Observer uses KNOWN_MODES from server JSON" 'KNOWN_MODES\s*=' "$OBSERVER_VIEW"
  check_has_matches "Observer uses SHORTHAND_MAP from server JSON" 'SHORTHAND_MAP\s*=' "$OBSERVER_VIEW"
  echo ""
fi

# ---------------------------------------------------------------------------
# 3. browserNormalizationMap() must agree with canonical parser.
# ---------------------------------------------------------------------------
RESOLVER="apps/Shell/Services/AppearanceStateResolver.php"
if [[ -f "$RESOLVER" ]]; then
  check_has_matches "Resolver has browserNormalizationMap method" 'function browserNormalizationMap' "$RESOLVER"
  check_has_matches "Resolver known_modes includes 6 entries" 'liquid-glass.*paper' "$RESOLVER"
  check_has_matches "Resolver shorthand_map includes dark key" "'dark'\s*=>" "$RESOLVER"
  check_has_matches "Resolver shorthand_map includes light key" "'light'\s*=>" "$RESOLVER"
  check_has_matches "Resolver shorthand_map includes system key" "'system'\s*=>" "$RESOLVER"
  echo ""
fi

# ---------------------------------------------------------------------------
# 4. Probe must validate the map.
# ---------------------------------------------------------------------------
PROBE="apps/Shell/Tests/probe_appearance_state.php"
if [[ -f "$PROBE" ]]; then
  check_has_matches "Probe validates browserNormalizationMap" 'browserNormalizationMap' "$PROBE"
  check_has_matches "Probe tests known_modes count" 'count.*known_modes.*===.*6' "$PROBE"
  check_has_matches "Probe shorthand classification confirms runtime evidence" 'confirmed_runtime_shorthand' "$PROBE"
  check_has_matches "Probe references runtime shorthand evidence locations" 'header.php.*auth_header.php.*OperatorSurfaceComposer' "$PROBE"
fi

# ---------------------------------------------------------------------------
# 5. KNOWN_MODES and SHORTHAND_MAP must come from $browserNormMap (not
#    hardcoded arrays in inline JS).
# ---------------------------------------------------------------------------
if [[ -f "$OBSERVER_VIEW" ]]; then
  check_has_matches "KNOWN_MODES derived from browserNormMap" '\$normKnownModes\s*=' "$OBSERVER_VIEW"
  check_has_matches "SHORTHAND_MAP derived from browserNormMap" '\$normShorthand\s*=' "$OBSERVER_VIEW"
  check_has_matches "KNOWN_MODES is json_encoded from server" 'json_encode.*\$normKnownModes' "$OBSERVER_VIEW"
  check_has_matches "SHORTHAND_MAP is json_encoded from server" 'json_encode.*\$normShorthand' "$OBSERVER_VIEW"
  echo ""
fi

# ---------------------------------------------------------------------------
# 6. Observer must not call localStorage.removeItem or .clear.
# ---------------------------------------------------------------------------
if [[ -f "$OBSERVER_VIEW" ]]; then
  check_no_matches "Observer has no localStorage.removeItem" 'localStorage\s*\.\s*removeItem' "$OBSERVER_VIEW"
  check_no_matches "Observer has no localStorage.clear" 'localStorage\s*\.\s*clear' "$OBSERVER_VIEW"
  check_no_matches "Observer has no setAttribute on document/html" 'document\.documentElement\.setAttribute' "$OBSERVER_VIEW"
  check_no_matches "Observer has no .removeAttribute" '\.removeAttribute\s*\(' "$OBSERVER_VIEW"
  echo ""
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo "---"
echo "Appearance Observer Boundary Check: ${failed} failures, ${warn} warnings"
exit $(( failed > 0 ? 1 : 0 ))

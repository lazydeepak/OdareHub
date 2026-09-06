#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_localization_migration_guardrail"
echo "- blocks any legacy-path locale files (phase 4)"
echo ""

failures=0
warnings=0

record_fail() {
  local label="$1"
  echo "  FAIL: $label" >&2
  failures=$((failures + 1))
}

record_pass() {
  local label="$1"
  echo "  ok: $label"
}

# Legacy locale path patterns — must not appear anywhere in working tree
LEGACY_PATTERNS=(
  "apps/*/lang/*.php"
  "apps/*/modules/*/lang/*.php"
  "plugins/*/lang/*.php"
  "apps/Studio/Tools/*/lang/*.php"
)

# ── Check 1: No tracked files in legacy paths (staged + unstaged) ──
tracked_legacy=$(git ls-files -- \
  ':(glob)apps/*/lang/*.php' \
  ':(glob)apps/*/modules/*/lang/*.php' \
  ':(glob)plugins/*/lang/*.php' \
  ':(glob)apps/Studio/Tools/*/lang/*.php' \
  2>/dev/null || true)
if [[ -n "$tracked_legacy" ]]; then
  while IFS= read -r f; do
    [[ -z "$f" ]] && continue
    record_fail "tracked legacy-path locale file: $f (should be removed)"
  done <<< "$tracked_legacy"
else
  record_pass "no tracked legacy-path locale files"
fi

# ── Check 2: No untracked files in legacy paths ────────────────────
untracked_legacy=$(git ls-files --others --exclude-standard -- \
  ':(glob)apps/*/lang/*.php' \
  ':(glob)apps/*/modules/*/lang/*.php' \
  ':(glob)plugins/*/lang/*.php' \
  ':(glob)apps/Studio/Tools/*/lang/*.php' \
  2>/dev/null || true)
if [[ -n "$untracked_legacy" ]]; then
  while IFS= read -r f; do
    [[ -z "$f" ]] && continue
    record_fail "untracked legacy-path locale file: $f"
  done <<< "$untracked_legacy"
else
  record_pass "no untracked legacy-path locale files"
fi

# ── Result ────────────────────────────────────────────────────────

echo ""
echo "== Result =="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures error(s), $warnings warning(s))" >&2
  exit 1
fi

echo "RESULT: PASS ($warnings warning(s))"

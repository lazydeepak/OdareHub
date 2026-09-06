#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

contract_note="scripts/architecture/core-lock-gate-contract.md"

require_file() {
  local path="$1"
  local label="$2"

  if [[ -f "$path" ]]; then
    echo "  ok: $label ($path)"
  else
    echo "  fail: missing $label ($path)" >&2
    exit 1
  fi
}

require_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if grep -Fq "$needle" "$path"; then
    echo "  ok: $label"
  else
    echo "  fail: $label" >&2
    exit 1
  fi
}

echo "[architecture] check_core_lock_scope"

echo "- validating gate self-contract"
require_file "$contract_note" "core lock gate contract note"
require_text "$contract_note" "Required Scan Scope" "contract note documents required scan scope"
require_text "$contract_note" "ARCHITECTURE_GATE_ALLOW_CORE=1" "contract note documents explicit Core override"
require_text "$contract_note" "must not absorb" "contract note documents Core ownership boundaries"

echo "- scanning current git diff for Core Engine changes"
changed_core_files="$(
  {
    git diff --name-only HEAD -- app 2>/dev/null || true
    git ls-files --others --exclude-standard -- app 2>/dev/null || true
  } | sort -u
)"

if [[ -n "$changed_core_files" ]]; then
  echo "$changed_core_files"
  if [[ "${ARCHITECTURE_GATE_ALLOW_CORE:-0}" != "1" ]]; then
    echo "RESULT: FAIL (Core Engine changes require explicit approval; run with ARCHITECTURE_GATE_ALLOW_CORE=1 only for approved exceptional Core work)" >&2
    exit 1
  fi
  echo "RESULT: PASS (Core Engine changes explicitly allowed for this run via ARCHITECTURE_GATE_ALLOW_CORE=1)"
  exit 0
fi

echo "RESULT: PASS"

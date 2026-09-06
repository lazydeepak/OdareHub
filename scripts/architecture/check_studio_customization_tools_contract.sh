#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_studio_customization_tools_contract"
echo "- read-only diagnostic for Studio customization tools boundary contract"

failures=0

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

ok() {
  echo "  ok: $1"
}

require_file() {
  local path="$1"
  local label="$2"

  if [[ -f "$path" ]]; then
    ok "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

require_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if [[ -f "$path" ]] && grep -Fq -- "$needle" "$path"; then
    ok "$label"
  else
    fail "$label not found in $path"
  fi
}

ARCH_DOC="docs/architecture/studio-customization-tools-contract.md"
BATCH_DOC="docs/migration-cleanup/maps/batch-14-studio-customization-tools-contract.md"

require_file "$ARCH_DOC" "Studio customization tools architecture contract"
require_file "$BATCH_DOC" "Batch 14 customization tools map"


echo ""
echo "== Required boundary definitions =="
require_text "$ARCH_DOC" "Customization tools are Studio Tools, not System Tools." "Studio tool vs system tool boundary"
require_text "$ARCH_DOC" "System Tools validate/rebuild/repair/audit customization output." "System tool validation/rebuild boundary"
require_text "$ARCH_DOC" "Shell renders approved resolved theme and menu contracts." "Shell runtime rendering boundary"
require_text "$ARCH_DOC" "Platform controls permissions and instance policy." "Platform authorization boundary"
require_text "$ARCH_DOC" "Public assets are delivery output, not source truth." "Public assets delivery-output rule"
require_text "$ARCH_DOC" "Storage may hold snapshots, audit records, and history." "Storage evidence allowance"
require_text "$ARCH_DOC" "Storage must not hold hidden design truth" "No hidden storage design truth rule"


echo ""
echo "== Required future tool definitions =="
for tool in ThemeTool CssTool MenuEditor BrandingTool FontTool; do
  require_text "$ARCH_DOC" "###" "Customization tool section markers present"
  require_text "$ARCH_DOC" "$tool" "$tool section present"
  require_text "$ARCH_DOC" "owner = studio" "Owner field documented for $tool"
  require_text "$ARCH_DOC" "editable resources" "Editable resources anchor present for $tool"
  require_text "$ARCH_DOC" "forbidden resources" "Forbidden resources anchor present for $tool"
  require_text "$ARCH_DOC" "risk level" "Risk level anchor present for $tool"
  require_text "$ARCH_DOC" "default enabled policy" "Default enabled policy anchor present for $tool"
  require_text "$ARCH_DOC" "required permission" "Required permission anchor present for $tool"
  require_text "$ARCH_DOC" "output contract" "Output contract anchor present for $tool"
  require_text "$ARCH_DOC" "required validation" "Required validation anchor present for $tool"
  require_text "$ARCH_DOC" "rollback/snapshot requirement" "Rollback/snapshot anchor present for $tool"
done


echo ""
echo "== Required direct answers =="
require_text "$ARCH_DOC" "1. Which customization resources belong to Studio tools?" "Answer 1 present"
require_text "$ARCH_DOC" "2. Which validation/rebuild actions belong to System Tools?" "Answer 2 present"
require_text "$ARCH_DOC" "3. Which runtime rendering responsibilities belong to Shell?" "Answer 3 present"
require_text "$ARCH_DOC" "4. Which authorization/policy responsibilities belong to Platform?" "Answer 4 present"
require_text "$ARCH_DOC" "5. How do app/module-owned CSS and menu resources stay owner-owned?" "Answer 5 present"
require_text "$ARCH_DOC" "6. Why public/assets must not become source truth?" "Answer 6 present"
require_text "$ARCH_DOC" "7. What is the first safe implementation step after this contract?" "Answer 7 present"


echo ""
echo "== Tracker alignment =="
require_text "docs/architecture/studio-tool-lifecycle-contract.md" "studio-customization-tools-contract.md" "Tool lifecycle contract links customization contract"
require_text "docs/migration-cleanup/phases/move-plan.md" "Batch 14: Studio customization tool boundary contract" "Move plan includes Batch 14"
require_text "MIGRATION-CLEANUP-INDEX.md" "batch-14-studio-customization-tools-contract.md" "Cleanup index includes Batch 14"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio customization tools contract gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"

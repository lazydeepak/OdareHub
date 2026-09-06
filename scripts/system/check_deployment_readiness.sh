#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

SELF_PATH="scripts/system/check_deployment_readiness.sh"
STAGE_LINE_RESULT=""

find_stage_line() {
  local pattern="$1"
  local label="$2"
  local line=""

  line="$(grep -nE "$pattern" "$SELF_PATH" | head -n 1 | cut -d: -f1)"
  if [[ -n "$line" ]]; then
    echo "  ok: $label (line $line)"
    STAGE_LINE_RESULT="$line"
  else
    echo "  fail: readiness executable stage missing: $label" >&2
    exit 1
  fi
}

require_stage_order() {
  local previous_line="$1"
  local current_line="$2"
  local previous_label="$3"
  local current_label="$4"

  if [[ "$current_line" -gt "$previous_line" ]]; then
    echo "  ok: $current_label follows $previous_label"
  else
    echo "  fail: readiness stage order invalid: $current_label must follow $previous_label" >&2
    exit 1
  fi
}

echo "[system] check_deployment_readiness"
echo "- preparing generated delivery assets and running architecture validation"

echo ""
echo "== Readiness orchestration contract =="
find_stage_line '^[[:space:]]*bash scripts/system/check_system_tools_inventory\.sh[[:space:]]*$' "system tool inventory"
line_system_inventory="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*bash scripts/system/check_backfill_utility_aging\.sh[[:space:]]*$' "backfill utility aging"
line_backfill_aging="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*php -l scripts/assets/publish_registered_css\.php[[:space:]]*$' "CSS publisher PHP syntax"
line_php_syntax="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*php -l scripts/assets/compile_first_boot_css\.php[[:space:]]*$' "first-boot CSS compiler PHP syntax"
line_first_boot_php_syntax="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*php scripts/assets/publish_registered_css\.php --apply[[:space:]]*$' "registered CSS asset publishing"
line_css_publish="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*php scripts/assets/compile_first_boot_css\.php --apply[[:space:]]*$' "first-boot CSS asset publishing"
line_first_boot_publish="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*bash scripts/architecture/run_architecture_gates\.sh[[:space:]]*$' "architecture gates"
line_architecture_gates="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*git diff --check[[:space:]]*$' "diff hygiene"
line_diff_hygiene="$STAGE_LINE_RESULT"
find_stage_line '^[[:space:]]*if git diff --quiet -- public/assets/apps; then[[:space:]]*$' "generated asset cleanliness"
line_asset_cleanliness="$STAGE_LINE_RESULT"

require_stage_order "$line_system_inventory" "$line_backfill_aging" "system tool inventory" "backfill utility aging"
require_stage_order "$line_backfill_aging" "$line_php_syntax" "backfill utility aging" "CSS publisher PHP syntax"
require_stage_order "$line_php_syntax" "$line_first_boot_php_syntax" "CSS publisher PHP syntax" "first-boot CSS compiler PHP syntax"
require_stage_order "$line_first_boot_php_syntax" "$line_css_publish" "first-boot CSS compiler PHP syntax" "registered CSS asset publishing"
require_stage_order "$line_css_publish" "$line_first_boot_publish" "registered CSS asset publishing" "first-boot CSS asset publishing"
require_stage_order "$line_first_boot_publish" "$line_architecture_gates" "first-boot CSS asset publishing" "architecture gates"
require_stage_order "$line_architecture_gates" "$line_diff_hygiene" "architecture gates" "diff hygiene"
require_stage_order "$line_diff_hygiene" "$line_asset_cleanliness" "diff hygiene" "generated asset cleanliness"

echo ""
echo "== System tool inventory =="
bash scripts/system/check_system_tools_inventory.sh

echo ""
echo "== Backfill utility aging =="
bash scripts/system/check_backfill_utility_aging.sh

echo ""
echo "== PHP syntax =="
php -l scripts/assets/publish_registered_css.php
php -l scripts/assets/compile_first_boot_css.php

echo ""
echo "== Publish registered CSS assets =="
php scripts/assets/publish_registered_css.php --apply

echo ""
echo "== Publish first-boot CSS assets =="
php scripts/assets/compile_first_boot_css.php --apply

echo ""
echo "== Architecture gates =="
bash scripts/architecture/run_architecture_gates.sh

echo ""
echo "== Diff hygiene =="
git diff --check

echo ""
echo "== Generated asset cleanliness =="
if git diff --quiet -- public/assets/apps; then
  echo "RESULT: PASS"
else
  echo "RESULT: FAIL (generated public app assets changed during readiness)" >&2
  git diff --name-only -- public/assets/apps >&2
  exit 1
fi

echo ""
echo "DEPLOYMENT READINESS: PASS"

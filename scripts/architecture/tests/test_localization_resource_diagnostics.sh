#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
DIAGNOSTIC="$ROOT_DIR/scripts/architecture/check_localization_resource_diagnostics.sh"
FIXTURE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/localization-resource-diagnostic-fixture.XXXXXX")"
trap 'rm -rf "$FIXTURE_ROOT"' EXIT

mkdir -p \
  "$FIXTURE_ROOT/apps/Demo/Resources/lang" \
  "$FIXTURE_ROOT/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Resources/lang" \
  "$FIXTURE_ROOT/plugins/Example/Resources/lang"

cat > "$FIXTURE_ROOT/apps/Demo/Resources/lang/en.php" <<'PHP'
<?php
return [
    'safe.label' => 'Save',
    'bad-key' => 'A harmless value',
    'danger.label' => 'Use exec carefully',
];
PHP

cat > "$FIXTURE_ROOT/apps/Demo/Resources/lang/ja.php" <<'PHP'
<?php
return ['safe.label' => '保存'];
PHP

cat > "$FIXTURE_ROOT/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Resources/lang/en.php" <<'PHP'
<?php
return ['token.label' => 'Token'];
PHP

cat > "$FIXTURE_ROOT/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Resources/lang/ja.php" <<'PHP'
<?php
return ['token.label' => 'トークン'];
PHP

cat > "$FIXTURE_ROOT/plugins/Example/Resources/lang/en.php" <<'PHP'
<?php
return ['plugin.label' => 'Plugin'];
PHP

output=$(LOCALIZATION_DIAGNOSTIC_ROOT="$FIXTURE_ROOT" bash "$DIAGNOSTIC" 2>&1)

assert_contains() {
  local needle="$1"
  if ! grep -Fq "$needle" <<< "$output"; then
    echo "FAIL: expected diagnostic output to contain: $needle" >&2
    exit 1
  fi
}

assert_contains 'ok: found 5 locale file(s) to validate'
assert_contains 'warning accounting matched — emitted 3 warning(s)'
assert_contains 'dangerous: 1'
assert_contains 'naming: 1'
assert_contains 'parity: 1'
assert_contains 'Demo: 3'
assert_contains 'RESULT: PASS (3 warning(s))'

echo "PASS: localization resource diagnostic fixture regression"

#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

PROBE="apps/Shell/Tests/probe_operator_responsive_navigation_integrity.php"

echo "[architecture] check_operator_responsive_navigation_integrity"

if [[ ! -f "$PROBE" ]]; then
  echo "  fail: probe missing ($PROBE)" >&2
  exit 1
fi

echo "- running operator responsive navigation integrity probe"
if php "$PROBE"; then
  echo "RESULT: PASS"
else
  echo "RESULT: FAIL (navigation integrity violations detected)" >&2
  exit 1
fi

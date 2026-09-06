#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[system] check_backfill_utility_aging"
echo "- read-only check for task-specific root-level backfill utilities"

failures=0
backfills=()

while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  backfills+=("$path")
done < <(find scripts -maxdepth 1 -type f -name 'backfill_*.php' | sort)

check_not_referenced() {
  local path="$1"
  local file="$2"
  local label="$3"

  if [[ ! -f "$file" ]]; then
    echo "  ok: $label missing, no reference check needed"
    return
  fi

  if grep -Fq "$path" "$file"; then
    echo "  fail: $path is referenced by $label ($file)" >&2
    failures=$((failures + 1))
  else
    echo "  ok: $path is not referenced by $label"
  fi
}

check_not_registered() {
  local path="$1"
  local registry="scripts/system/tools.registry.json"

  if [[ ! -f "$registry" ]]; then
    echo "  ok: system tools registry missing, no registry reference check needed"
    return
  fi

  if php -r '
    $registry = json_decode((string)file_get_contents("scripts/system/tools.registry.json"), true);
    $needle = $argv[1];
    foreach (($registry["tools"] ?? []) as $tool) {
        if (($tool["path"] ?? "") === $needle) {
            exit(0);
        }
    }
    exit(1);
  ' "$path"; then
    echo "  fail: $path is registered as an active System Tool" >&2
    failures=$((failures + 1))
  else
    echo "  ok: $path is not registered as an active System Tool"
  fi
}

echo ""
echo "== Discovered backfill utilities =="
if [[ "${#backfills[@]}" -eq 0 ]]; then
  echo "  ok: no root-level backfill utilities discovered"
else
  for path in "${backfills[@]}"; do
    echo "  ok: task-specific utility present: $path"
  done
fi

echo ""
echo "== Aging policy checks =="
for path in "${backfills[@]}"; do
  check_not_referenced "$path" "scripts/system/check_deployment_readiness.sh" "deployment readiness script"
  check_not_referenced "$path" "scripts/system/README.md" "system active tool baseline"
  check_not_registered "$path"
done

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (backfill utility aging policy violation)" >&2
  exit 1
fi

echo "RESULT: PASS"

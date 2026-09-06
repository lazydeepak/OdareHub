#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_studio_enforcement_readiness"
echo "- read-only diagnostic for Studio apply/governance readiness"

failures=0
warnings=0
tmp_diff="$(mktemp /tmp/studio-enforcement-diff-XXXXXX)"
trap 'rm -f "$tmp_diff"' EXIT

expected_scan_anchors=(
  "apps/Studio/manifest.json"
  "apps/Studio/routes.php"
  "apps/Studio/AGENTS.md"
  "docs/architecture/studio-operating-contract.md"
  "docs/architecture/studio-resource-registry-baseline.md"
  "docs/architecture/studio-change-lifecycle-apply-contract.md"
  "docs/architecture/studio-change-record-schema-baseline.md"
  "docs/architecture/studio-approval-risk-validation-policy.md"
  "docs/architecture/resolved-runtime-contract-pipeline.md"
  "apps/Shell/manifest.json"
  "apps/Platform/manifest.json"
  "runtime_additions_bypass_pattern"
  "runtime_additions_handover_pattern"
)

active_scan_anchors=(
  "apps/Studio/manifest.json"
  "apps/Studio/routes.php"
  "apps/Studio/AGENTS.md"
  "docs/architecture/studio-operating-contract.md"
  "docs/architecture/studio-resource-registry-baseline.md"
  "docs/architecture/studio-change-lifecycle-apply-contract.md"
  "docs/architecture/studio-change-record-schema-baseline.md"
  "docs/architecture/studio-approval-risk-validation-policy.md"
  "docs/architecture/resolved-runtime-contract-pipeline.md"
  "apps/Shell/manifest.json"
  "apps/Platform/manifest.json"
  "runtime_additions_bypass_pattern"
  "runtime_additions_handover_pattern"
)

runtime_additions_bypass_pattern='^apps/Studio/.*(bypass|skip|without|no approval|no snapshot|no rollback|direct apply|force apply).*(approval|snapshot|rollback|validation|System Tools|migration|ACL|audit)'
runtime_additions_handover_pattern='^apps/Studio/.*(generated|modified|runtime artifact|business resource|owner artifact).*(Studio owns|owned by Studio|without handover|no handover|source of truth)'

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

warn() {
  echo "  warning: $1"
  warnings=$((warnings + 1))
}

ok() {
  echo "  ok: $1"
}

diff_additions() {
  git diff --unified=0 --diff-filter=ACMRT HEAD -- apps docs scripts public 2>/dev/null \
    | awk '
      /^diff --git / {
        file = $4
        sub(/^b\//, "", file)
        next
      }
      /^\+\+\+ / { next }
      /^\+/ && $0 !~ /^\+\+\+/ {
        print file ":" substr($0, 2)
      }
    '
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

  if [[ ! -f "$path" ]]; then
    fail "cannot check $label; file missing: $path"
    return
  fi

  if grep -Fq "$needle" "$path"; then
    ok "$label"
  else
    fail "$label not found in $path"
  fi
}

manifest_value() {
  local manifest="$1"
  local field="$2"

  php -r '
    $manifest = $argv[1];
    $field = $argv[2];
    $data = json_decode((string)file_get_contents($manifest), true);
    if (!is_array($data)) {
        fwrite(STDERR, "invalid json: {$manifest}\n");
        exit(2);
    }
    $value = $data[$field] ?? null;
    if (is_bool($value)) {
        echo $value ? "true" : "false";
    } elseif (is_array($value)) {
        echo count($value);
    } elseif ($value === null) {
        echo "";
    } else {
        echo (string)$value;
    }
  ' "$manifest" "$field"
}

manifest_has_route() {
  local manifest="$1"
  local path="$2"
  local kind="$3"

  php -r '
    $manifest = $argv[1];
    $path = $argv[2];
    $kind = $argv[3];
    $data = json_decode((string)file_get_contents($manifest), true);
    if (!is_array($data)) {
        exit(2);
    }
    foreach (($data["routes"] ?? []) as $route) {
        if (($route["path"] ?? "") === $path && ($route["kind"] ?? "") === $kind) {
            exit(0);
        }
    }
    exit(1);
  ' "$manifest" "$path" "$kind"
}

manifest_has_compatibility_alias() {
  local manifest="$1"
  local path="$2"
  local target="$3"

  php -r '
    $manifest = $argv[1];
    $path = $argv[2];
    $target = $argv[3];
    $data = json_decode((string)file_get_contents($manifest), true);
    if (!is_array($data)) {
        exit(2);
    }
    foreach (($data["routes"] ?? []) as $route) {
        if (($route["path"] ?? "") === $path
            && ($route["kind"] ?? "") === "alias"
            && ($route["canonical_target"] ?? "") === $target
            && (bool)($route["compatibility"] ?? false) === true) {
            exit(0);
        }
    }
    exit(1);
  ' "$manifest" "$path" "$target"
}

manifest_lacks_forbidden_text() {
  local manifest="$1"
  local label="$2"
  local pattern="$3"

  if [[ ! -f "$manifest" ]]; then
    fail "cannot inspect $label; file missing: $manifest"
    return
  fi

  if grep -Ei "$pattern" "$manifest" >/dev/null; then
    fail "$label implies forbidden Studio ownership or bypass authority"
  else
    ok "$label avoids forbidden Studio ownership/bypass language"
  fi
}

diff_additions > "$tmp_diff"

echo ""
echo "== Studio diagnostic scan contract =="
for i in "${!expected_scan_anchors[@]}"; do
  expected="${expected_scan_anchors[$i]}"
  actual="${active_scan_anchors[$i]:-}"
  if [[ "$actual" == "$expected" ]]; then
    ok "scan-anchor[$i] $expected"
  else
    fail "scan-anchor[$i] drift: expected '$expected' but found '${actual:-<missing>}'"
  fi
done

if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  fail "scan-anchor count drift: expected ${#expected_scan_anchors[@]} but found ${#active_scan_anchors[@]}"
fi

echo ""
echo "== Studio app boundary =="
require_file "apps/Studio/manifest.json" "Studio manifest"
require_file "apps/Studio/AGENTS.md" "Studio local ownership guidance"
require_file "apps/Studio/routes.php" "Studio route owner file"

studio_manifest="apps/Studio/manifest.json"
if [[ -f "$studio_manifest" ]]; then
  app_key="$(manifest_value "$studio_manifest" "app_key")"
  app_type="$(manifest_value "$studio_manifest" "type")"

  [[ "$app_key" == "studio" ]] && ok "Studio manifest app_key is studio" || fail "Studio manifest app_key should be studio, found ${app_key:-<missing>}"
  [[ "$app_type" == "system" ]] && ok "Studio manifest type is system" || fail "Studio manifest type should be system, found ${app_type:-<missing>}"

  if manifest_has_route "$studio_manifest" "/apps/studio" "canonical"; then
    ok "Studio canonical route is declared"
  else
    fail "Studio canonical /apps/studio route is missing or not canonical"
  fi

  if manifest_has_compatibility_alias "$studio_manifest" "/ops/gui-studio" "/apps/studio"; then
    ok "legacy /ops/gui-studio alias is compatibility-marked"
  else
    warn "legacy /ops/gui-studio compatibility alias is missing or not explicitly marked; confirm removal checkpoint before deleting bridge"
  fi
fi

echo ""
echo "== Studio manifest/guidance ownership language =="
manifest_lacks_forbidden_text "$studio_manifest" "Studio manifest" 'owns (Core|Shell|business|runtime truth|live runtime|schema truth|resolved)'
manifest_lacks_forbidden_text "apps/Shell/manifest.json" "Shell manifest" 'depends_on.*Studio|Studio.*hard dependency|Studio.*runtime dependency|Studio.*owns'
manifest_lacks_forbidden_text "apps/Platform/manifest.json" "Platform manifest" 'Studio.*owns runtime|Studio.*owns business|Studio.*bypass|Studio.*source_of_truth'
require_text "apps/Studio/AGENTS.md" "Studio is a governed tooling app hired by app/module owners" "Studio guidance keeps worker/tool ownership model"
require_text "apps/Studio/AGENTS.md" "Studio-generated or Studio-modified runtime artifacts must remain owned by their target app/module" "Studio guidance requires owner handover for generated runtime artifacts"
require_text "apps/Studio/AGENTS.md" "do not bypass analysis, diff, approval, rollback, or lifecycle gates" "Studio guidance blocks governance bypasses"

echo ""
echo "== Studio readiness contracts =="
require_file "docs/architecture/studio-operating-contract.md" "Studio operating contract"
require_file "docs/architecture/studio-resource-registry-baseline.md" "Studio resource registry baseline"
require_file "docs/architecture/studio-change-lifecycle-apply-contract.md" "Studio change lifecycle/apply contract"
require_file "docs/architecture/studio-change-record-schema-baseline.md" "Studio change record schema baseline"
require_file "docs/architecture/studio-approval-risk-validation-policy.md" "Studio approval/risk/validation policy"

policy_doc="docs/architecture/studio-approval-risk-validation-policy.md"
schema_doc="docs/architecture/studio-change-record-schema-baseline.md"
lifecycle_doc="docs/architecture/studio-change-lifecycle-apply-contract.md"
registry_doc="docs/architecture/studio-resource-registry-baseline.md"
operating_doc="docs/architecture/studio-operating-contract.md"

for level in read_only low medium high critical; do
  require_text "$policy_doc" "$level" "policy declares risk level: $level"
  require_text "$schema_doc" "$level" "change record schema declares risk level: $level"
done

for field in risk_level approval_required approval_status approved_by validation_gates validation_status snapshot_id rollback_strategy handover_status resolved_contract_rebuild_required; do
  require_text "$schema_doc" "$field" "change record schema declares field: $field"
done

require_text "$policy_doc" "System Tools may block apply" "policy allows System Tools blocking"
require_text "$policy_doc" "Apply cannot proceed without required validation evidence" "policy blocks apply without validation evidence"
require_text "$schema_doc" "Runtime must not consume draft change records as resource truth" "change records are not runtime truth"
require_text "$lifecycle_doc" "snapshot" "lifecycle contract includes snapshot concept"
require_text "$lifecycle_doc" "handover" "lifecycle contract includes handover concept"
require_text "$registry_doc" "owner" "resource registry requires owner context"
require_text "$operating_doc" "not an owner" "operating contract keeps Studio non-owner"
require_text "$operating_doc" "Studio is a governed worker/tool" "operating contract defines Studio as governed worker/tool"
require_text "$operating_doc" "own runtime truth" "operating contract denies Studio runtime ownership"
require_text "$operating_doc" "own live business logic semantics" "operating contract denies Studio business ownership"
require_text "$operating_doc" "Runtime consumes approved owner artifacts" "operating contract keeps runtime off Studio working state"
require_text "$lifecycle_doc" "Analyze current state" "lifecycle contract includes analyze stage"
require_text "$lifecycle_doc" "Compute diff" "lifecycle contract includes diff stage"
require_text "$lifecycle_doc" "Preview" "lifecycle contract includes preview stage"
require_text "$lifecycle_doc" "Approval" "lifecycle contract includes approval stage"
require_text "$lifecycle_doc" "Apply to owner-owned artifact" "lifecycle contract applies to owner artifacts"
require_text "$registry_doc" "Registry output is temporary working context" "registry baseline denies registry runtime truth"
require_text "$policy_doc" "Approval cannot bypass policy or validation requirements" "risk policy denies approval bypass"
require_text "docs/architecture/resolved-runtime-contract-pipeline.md" "Studio drafts are not compiler inputs for production runtime contracts" "resolved runtime contract excludes Studio drafts"

echo ""
echo "== Runtime diff regression scan =="
if [[ -s "$tmp_diff" ]]; then
  bypass_matches="$(
    grep -Ei "$runtime_additions_bypass_pattern" "$tmp_diff" \
      | grep -Ev 'AGENT-COMPLIANCE-CHECKLIST|docs/|check_studio_enforcement_readiness|check_studio_boundary|must not|cannot|disallowed|blocks|denies|without required validation|without rollback' \
      || true
  )"
  if [[ -n "$bypass_matches" ]]; then
    echo "$bypass_matches"
    fail "new Studio runtime diff appears to bypass approval/snapshot/rollback/validation governance"
  else
    ok "no new Studio governance-bypass runtime additions"
  fi

  handover_matches="$(
    grep -Ei "$runtime_additions_handover_pattern" "$tmp_diff" \
      | grep -Ev 'AGENT-COMPLIANCE-CHECKLIST|docs/|check_studio_enforcement_readiness|check_studio_boundary|must remain owned|handover|not by Studio' \
      || true
  )"
  if [[ -n "$handover_matches" ]]; then
    echo "$handover_matches"
    fail "new Studio runtime diff appears to claim generated/runtime resource ownership without owner handover"
  else
    ok "no new Studio owner-handover runtime additions"
  fi
else
  ok "no runtime diff additions to scan for Studio enforcement regressions"
fi

echo ""
echo "== Result =="
echo "warnings: $warnings"
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Studio enforcement readiness contract gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS (read-only readiness diagnostic complete)"

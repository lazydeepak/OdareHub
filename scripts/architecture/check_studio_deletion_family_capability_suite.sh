#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

capability_gates=(
  "scripts/architecture/check_studio_deletion_impact_discovery_capability.sh"
  "scripts/architecture/check_studio_deletion_plan_capability.sh"
  "scripts/architecture/check_studio_deletion_change_set_capability.sh"
  "scripts/architecture/check_studio_deletion_approval_record_capability.sh"
  "scripts/architecture/check_studio_deletion_execution_readiness_capability.sh"
  "scripts/architecture/check_studio_deletion_execution_dry_run_capability.sh"
  "scripts/architecture/check_studio_deletion_execution_request_capability.sh"
  "scripts/architecture/check_studio_deletion_executor_readiness_capability.sh"
  "scripts/architecture/check_studio_deletion_execution_claim_capability.sh"
  "scripts/architecture/check_studio_deletion_snapshot_readiness_capability.sh"
  "scripts/architecture/check_studio_deletion_snapshot_capability.sh"
  "scripts/architecture/check_studio_deletion_snapshot_integrity_capability.sh"
  "scripts/architecture/check_studio_deletion_reference_remediation_readiness_capability.sh"
  "scripts/architecture/check_studio_deletion_reference_remediation_patch_proposal_capability.sh"
)

workspace_gates=(
  "scripts/architecture/check_owner_structure_deletion_impact_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_plan_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_change_set_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_approval_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_execution_readiness_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_execution_dry_run_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_execution_request_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_executor_readiness_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_execution_claim_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_snapshot_readiness_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_snapshot_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_snapshot_integrity_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_reference_remediation_readiness_workspace.sh"
  "scripts/architecture/check_owner_structure_deletion_reference_remediation_patch_proposal_workspace.sh"
)

failures=0
failed_gates=()

echo "[architecture] check_studio_deletion_family_capability_suite"
echo "- aggregate certification for existing deletion capability/workspace gates"

run_gate_group() {
  local label="$1"
  shift
  local gates=("$@")
  local gate
  echo ""
  echo "== $label (${#gates[@]} gates) =="
  for gate in "${gates[@]}"; do
    if [[ ! -f "$gate" ]]; then
      echo "  fail: missing gate ($gate)" >&2
      failures=$((failures + 1))
      failed_gates+=("$gate:missing")
      continue
    fi
    echo ""
    echo "--- running $gate ---"
    if ! bash "$gate"; then
      failures=$((failures + 1))
      failed_gates+=("$gate")
    fi
  done
}

run_gate_group "Canonical deletion capabilities" "${capability_gates[@]}"
run_gate_group "OwnerStructure deletion workspaces" "${workspace_gates[@]}"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "DELETION FAMILY GATES: FAIL ($failures gate(s) failed)" >&2
  echo "Failed gates:" >&2
  printf ' - %s\n' "${failed_gates[@]}" >&2
  exit 1
fi

total=$(( ${#capability_gates[@]} + ${#workspace_gates[@]} ))
echo "DELETION FAMILY GATES: PASS ($total/$total)"

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

# The capability and workspace gates form one explicit dependency chain. Each
# child gate invokes its immediate predecessor and its matching capability gate.
# The suite must execute the terminal workspace gate once, not iterate every
# ancestor in a second explicit top-level pass. The dependency-chain assertions
# below preserve reachability of all 28 checks while avoiding the former full
# duplicate traversal of the same read-only probes and PHP lint.
workspace_dependencies=(
  ""
  "check_owner_structure_deletion_impact_workspace.sh"
  "check_owner_structure_deletion_plan_workspace.sh"
  "check_owner_structure_deletion_change_set_workspace.sh"
  "check_owner_structure_deletion_approval_workspace.sh"
  "check_owner_structure_deletion_execution_readiness_workspace.sh"
  "check_owner_structure_deletion_execution_dry_run_workspace.sh"
  "check_owner_structure_deletion_execution_request_workspace.sh"
  "check_owner_structure_deletion_executor_readiness_workspace.sh"
  "check_owner_structure_deletion_execution_claim_workspace.sh"
  "check_owner_structure_deletion_snapshot_readiness_workspace.sh"
  "check_owner_structure_deletion_snapshot_workspace.sh"
  "check_owner_structure_deletion_snapshot_integrity_workspace.sh"
  "check_owner_structure_deletion_reference_remediation_readiness_workspace.sh"
)
capability_dependencies=(
  ""
  "check_studio_deletion_impact_discovery_capability.sh"
  "check_studio_deletion_plan_capability.sh"
  "check_studio_deletion_change_set_capability.sh"
  "check_studio_deletion_approval_record_capability.sh"
  "check_studio_deletion_execution_readiness_capability.sh"
  "check_studio_deletion_execution_dry_run_capability.sh"
  "check_studio_deletion_execution_request_capability.sh"
  "check_studio_deletion_executor_readiness_capability.sh"
  "check_studio_deletion_execution_claim_capability.sh"
  "check_studio_deletion_snapshot_readiness_capability.sh"
  "check_studio_deletion_snapshot_capability.sh"
  "check_studio_deletion_snapshot_integrity_capability.sh"
  "check_studio_deletion_reference_remediation_readiness_capability.sh"
)

assert_chain_contract() {
  local index gate dependency capability
  for index in "${!workspace_gates[@]}"; do
    gate="${workspace_gates[$index]}"
    if [[ "${workspace_dependencies[$index]}" != "" ]] && ! grep -Fq -- "${workspace_dependencies[$index]}" "$gate"; then
      echo "  fail: workspace dependency missing: $gate -> ${workspace_dependencies[$index]}" >&2
      failures=$((failures + 1))
      failed_gates+=("$gate:dependency")
    fi
    capability="${capability_gates[$index]}"
    if ! grep -Fq -- "$capability" "$gate"; then
      echo "  fail: workspace/capability dependency missing: $gate -> $capability" >&2
      failures=$((failures + 1))
      failed_gates+=("$gate:capability-dependency")
    fi
  done
  for index in "${!capability_gates[@]}"; do
    gate="${capability_gates[$index]}"
    dependency="${capability_dependencies[$index]}"
    if [[ "$dependency" != "" ]] && ! grep -Fq -- "$dependency" "$gate"; then
      echo "  fail: capability dependency missing: $gate -> $dependency" >&2
      failures=$((failures + 1))
      failed_gates+=("$gate:dependency")
    fi
  done
}

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

assert_chain_contract
if [[ "$failures" -eq 0 ]]; then
  terminal_workspace_gate="${workspace_gates[$((${#workspace_gates[@]} - 1))]}"
  run_gate_group "OwnerStructure deletion dependency chain (28 gates)" "$terminal_workspace_gate"
fi

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "DELETION FAMILY GATES: FAIL ($failures gate(s) failed)" >&2
  echo "Failed gates:" >&2
  printf ' - %s\n' "${failed_gates[@]}" >&2
  exit 1
fi

total=$(( ${#capability_gates[@]} + ${#workspace_gates[@]} ))
echo "DELETION FAMILY GATES: PASS ($total/$total)"

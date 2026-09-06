#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

contract_note="scripts/architecture/gate-runner-contract.md"
coverage_index="docs/architecture/architecture-gate-coverage-index.md"
architecture_readme="scripts/architecture/README.md"

# expected_scripts lines 11-40
expected_scripts=(
  "scripts/architecture/check_core_lock_scope.sh"
  "scripts/architecture/check_system_app_contracts.sh"
  "scripts/architecture/check_shell_runtime_menu_boundary.sh"
  "scripts/architecture/check_shell_css_ownership.sh"
  "scripts/architecture/check_shell_rendering_contract.sh"
  "scripts/architecture/check_shell_sidebar_content_geometry_contract.sh"
  "scripts/architecture/check_asset_registry_integrity.sh"
  "scripts/architecture/check_operator_confinement.sh"
  "scripts/architecture/check_display_readonly.sh"
  "scripts/architecture/check_admin_route_contract.sh"
  "scripts/architecture/check_studio_boundary.sh"
  "scripts/architecture/check_studio_enforcement_readiness.sh"
  "scripts/architecture/check_cte_safety.sh"
  "scripts/architecture/check_localization_studio_boundaries.sh"
  "scripts/architecture/check_localization_resource_diagnostics.sh"
  "scripts/architecture/check_localization_migration_guardrail.sh"
  "scripts/architecture/check_localization_studio_v2_1_readiness.sh"
  "scripts/architecture/check_label_designer_boundaries.sh"
  "scripts/architecture/check_token_impact_explorer_boundaries.sh"
  "scripts/architecture/check_css_live_editor_preview_boundary.sh"
  "scripts/architecture/check_report_designer_p1_boundaries.sh"
  "scripts/architecture/check_customization_studio_boundaries.sh"
  "scripts/architecture/check_shell_style_catalog_boundaries.sh"
  "scripts/architecture/check_platform_style_registry_boundaries.sh"
  "scripts/architecture/check_style_chain_parity.sh"
  "scripts/architecture/check_shell_style_consumption_boundary.sh"
  "scripts/architecture/check_read_only_consumption_probe_boundaries.sh"
  "scripts/architecture/check_platform_style_consumer_boundary.sh"
  "scripts/architecture/check_shell_consumption_boundary.sh"
  "scripts/architecture/check_platform_style_consumption_surface_boundary.sh"
  "scripts/architecture/check_runtime_style_application_boundary.sh"
  "scripts/architecture/check_shell_insertion_boundary.sh"
  "scripts/architecture/check_rendered_admin_proof_boundary.sh"
  "scripts/architecture/check_resolved_experience_truth.sh"
  "scripts/architecture/check_surface_contribution_contracts.sh"
  "scripts/architecture/check_operator_focus_declaration_parity.sh"
  "scripts/architecture/check_navigation_composition_duplicates.sh"
  "scripts/architecture/check_migration_debt_regressions.sh"
  "scripts/architecture/check_capability_ownership_boundaries.sh"
  "scripts/architecture/check_business_app_module_contracts.sh"
  "scripts/architecture/check_shared_app_extension_contract.sh"
  "scripts/architecture/check_parties_contract.sh"
  "scripts/architecture/check_theme_source_integrity.sh"
  "scripts/architecture/check_theme_runtime_fallback_contract.sh"
  "scripts/architecture/check_style_compliance_css_ownership.sh"
  "scripts/architecture/check_windows_checkout_paths.sh"
  "scripts/architecture/check_first_boot_css_safety.sh"
  "scripts/architecture/check_operator_responsive_navigation_integrity.sh"
  "scripts/architecture/check_studio_deletion_family_capability_suite.sh"
)

scripts=(
  "scripts/architecture/check_core_lock_scope.sh"
  "scripts/architecture/check_system_app_contracts.sh"
  "scripts/architecture/check_shell_runtime_menu_boundary.sh"
  "scripts/architecture/check_shell_css_ownership.sh"
  "scripts/architecture/check_shell_rendering_contract.sh"
  "scripts/architecture/check_shell_sidebar_content_geometry_contract.sh"
  "scripts/architecture/check_asset_registry_integrity.sh"
  "scripts/architecture/check_operator_confinement.sh"
  "scripts/architecture/check_display_readonly.sh"
  "scripts/architecture/check_admin_route_contract.sh"
  "scripts/architecture/check_studio_boundary.sh"
  "scripts/architecture/check_studio_enforcement_readiness.sh"
  "scripts/architecture/check_cte_safety.sh"
  "scripts/architecture/check_localization_studio_boundaries.sh"
  "scripts/architecture/check_localization_resource_diagnostics.sh"
  "scripts/architecture/check_localization_migration_guardrail.sh"
  "scripts/architecture/check_localization_studio_v2_1_readiness.sh"
  "scripts/architecture/check_label_designer_boundaries.sh"
  "scripts/architecture/check_token_impact_explorer_boundaries.sh"
  "scripts/architecture/check_css_live_editor_preview_boundary.sh"
  "scripts/architecture/check_report_designer_p1_boundaries.sh"
  "scripts/architecture/check_customization_studio_boundaries.sh"
  "scripts/architecture/check_shell_style_catalog_boundaries.sh"
  "scripts/architecture/check_platform_style_registry_boundaries.sh"
  "scripts/architecture/check_style_chain_parity.sh"
  "scripts/architecture/check_shell_style_consumption_boundary.sh"
  "scripts/architecture/check_read_only_consumption_probe_boundaries.sh"
  "scripts/architecture/check_platform_style_consumer_boundary.sh"
  "scripts/architecture/check_shell_consumption_boundary.sh"
  "scripts/architecture/check_platform_style_consumption_surface_boundary.sh"
  "scripts/architecture/check_runtime_style_application_boundary.sh"
  "scripts/architecture/check_shell_insertion_boundary.sh"
  "scripts/architecture/check_rendered_admin_proof_boundary.sh"
  "scripts/architecture/check_resolved_experience_truth.sh"
  "scripts/architecture/check_surface_contribution_contracts.sh"
  "scripts/architecture/check_operator_focus_declaration_parity.sh"
  "scripts/architecture/check_navigation_composition_duplicates.sh"
  "scripts/architecture/check_migration_debt_regressions.sh"
  "scripts/architecture/check_capability_ownership_boundaries.sh"
  "scripts/architecture/check_business_app_module_contracts.sh"
  "scripts/architecture/check_shared_app_extension_contract.sh"
  "scripts/architecture/check_parties_contract.sh"
  "scripts/architecture/check_theme_source_integrity.sh"
  "scripts/architecture/check_theme_runtime_fallback_contract.sh"
  "scripts/architecture/check_style_compliance_css_ownership.sh"
  "scripts/architecture/check_windows_checkout_paths.sh"
  "scripts/architecture/check_first_boot_css_safety.sh"
  "scripts/architecture/check_operator_responsive_navigation_integrity.sh"
  "scripts/architecture/check_studio_deletion_family_capability_suite.sh"
)

failures=0

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

  if grep -Fq -- "$needle" "$path"; then
    echo "  ok: $label"
  else
    echo "  fail: $label" >&2
    exit 1
  fi
}

echo "[architecture] run_architecture_gates"
echo "- read-only aggregate gate runner"

echo ""
echo "== Gate runner contract =="
require_file "$contract_note" "gate runner contract note"
require_text "$contract_note" "Required Gate Order" "contract note documents required gate order"
require_text "$contract_note" "ARCHITECTURE GATES: PASS" "contract note documents validation result"
require_text "$contract_note" "$coverage_index" "contract note references architecture gate coverage index"

echo ""
echo "== Coverage index alignment =="
require_file "$coverage_index" "architecture gate coverage index"
require_text "$coverage_index" "## Aggregate Gate Order" "coverage index documents aggregate gate order"
require_text "$coverage_index" "## Coverage Map" "coverage index documents coverage map"
require_text "$coverage_index" "- Architecture law protected:" "coverage index includes protected-law section marker"
require_text "$coverage_index" "- Owner layer responsible:" "coverage index includes owner-layer section marker"
require_text "$coverage_index" "- Scope scanned:" "coverage index includes scan-scope section marker"
require_text "$coverage_index" "- Pass/fail meaning:" "coverage index includes pass/fail section marker"
require_text "$coverage_index" "- Intentionally does not do:" "coverage index includes non-goals section marker"
require_text "$coverage_index" "- Known warnings/debt:" "coverage index includes known warnings/debt section marker"

echo ""
echo "== README linkage =="
require_file "$architecture_readme" "architecture README"
require_text "$architecture_readme" "$coverage_index" "architecture README links architecture gate coverage index"

if [[ "${#scripts[@]}" -ne "${#expected_scripts[@]}" ]]; then
  echo "  fail: architecture gate list length changed; expected ${#expected_scripts[@]}, found ${#scripts[@]}" >&2
  exit 1
fi

for index in "${!expected_scripts[@]}"; do
  if [[ "${scripts[$index]}" == "${expected_scripts[$index]}" ]]; then
    echo "  ok: gate[$index] ${scripts[$index]}"
    require_text "$contract_note" "${scripts[$index]}" "contract note lists gate[$index] ${scripts[$index]}"
    require_text "$coverage_index" "${scripts[$index]}" "coverage index lists gate[$index] ${scripts[$index]}"
  else
    echo "  fail: architecture gate[$index] changed; expected ${expected_scripts[$index]}, found ${scripts[$index]}" >&2
    exit 1
  fi
done

for script in "${scripts[@]}"; do
  echo ""
  echo "=== running $script ==="
  if ! bash "$script"; then
    failures=$((failures + 1))
  fi
done

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "ARCHITECTURE GATES: FAIL ($failures script(s) failed)" >&2
  exit 1
fi

echo "ARCHITECTURE GATES: PASS"

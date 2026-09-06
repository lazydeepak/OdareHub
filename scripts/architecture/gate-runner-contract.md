# Architecture Gate Runner Contract

`bash scripts/architecture/run_architecture_gates.sh` is the aggregate read-only architecture validation runner.

The runner owns the standard gate order. Gates must not be removed, inserted, or reordered silently. Any intentional change to the gate list must update the runner contract and be validated through deployment readiness.

Coverage index alignment is mandatory. Any intentional gate-list change must also update:

- `docs/architecture/architecture-gate-coverage-index.md`

## Required Gate Order

The aggregate runner must execute these gates in this order:

```text
scripts/architecture/check_core_lock_scope.sh
scripts/architecture/check_system_app_contracts.sh
scripts/architecture/check_shell_runtime_menu_boundary.sh
scripts/architecture/check_shell_css_ownership.sh
scripts/architecture/check_shell_rendering_contract.sh
scripts/architecture/check_shell_sidebar_content_geometry_contract.sh
scripts/architecture/check_asset_registry_integrity.sh
scripts/architecture/check_operator_confinement.sh
scripts/architecture/check_display_readonly.sh
scripts/architecture/check_admin_route_contract.sh
scripts/architecture/check_studio_boundary.sh
scripts/architecture/check_studio_enforcement_readiness.sh
scripts/architecture/check_cte_safety.sh
scripts/architecture/check_localization_studio_boundaries.sh
scripts/architecture/check_localization_resource_diagnostics.sh
scripts/architecture/check_localization_migration_guardrail.sh
scripts/architecture/check_localization_studio_v2_1_readiness.sh
scripts/architecture/check_label_designer_boundaries.sh
scripts/architecture/check_token_impact_explorer_boundaries.sh
scripts/architecture/check_css_live_editor_preview_boundary.sh
scripts/architecture/check_report_designer_p1_boundaries.sh
scripts/architecture/check_customization_studio_boundaries.sh
scripts/architecture/check_shell_style_catalog_boundaries.sh
scripts/architecture/check_platform_style_registry_boundaries.sh
scripts/architecture/check_style_chain_parity.sh
scripts/architecture/check_shell_style_consumption_boundary.sh
scripts/architecture/check_read_only_consumption_probe_boundaries.sh
scripts/architecture/check_platform_style_consumer_boundary.sh
scripts/architecture/check_shell_consumption_boundary.sh
scripts/architecture/check_platform_style_consumption_surface_boundary.sh
scripts/architecture/check_runtime_style_application_boundary.sh
scripts/architecture/check_shell_insertion_boundary.sh
scripts/architecture/check_rendered_admin_proof_boundary.sh
scripts/architecture/check_resolved_experience_truth.sh
scripts/architecture/check_surface_contribution_contracts.sh
scripts/architecture/check_operator_focus_declaration_parity.sh
scripts/architecture/check_navigation_composition_duplicates.sh
scripts/architecture/check_migration_debt_regressions.sh
scripts/architecture/check_capability_ownership_boundaries.sh
scripts/architecture/check_business_app_module_contracts.sh
scripts/architecture/check_shared_app_extension_contract.sh
scripts/architecture/check_parties_contract.sh
scripts/architecture/check_theme_source_integrity.sh
scripts/architecture/check_theme_runtime_fallback_contract.sh
scripts/architecture/check_style_compliance_css_ownership.sh
scripts/architecture/check_windows_checkout_paths.sh
scripts/architecture/check_first_boot_css_safety.sh
scripts/architecture/check_operator_responsive_navigation_integrity.sh
scripts/architecture/check_studio_deletion_family_capability_suite.sh
```

## Why Order Matters

- Core lock scope runs first because Core changes require explicit approval before other architecture interpretation.
- System App contracts run early because Shell and Platform are foundational owners for later route, surface, and tooling checks.
- Shell runtime menu boundary check runs before Shell CSS and contribution checks to enforce Shell-owned composition entry and prevent direct Core invocation regressions in layout runtime.
- Shell CSS ownership check runs before Shell rendering contract check because CSS ownership boundaries (which selectors belong to Shell vs apps) must be validated before the rendering contract can assess Shell-specific z-index, breakpoint, and overlay ownership.
- Shell rendering contract check runs after Shell CSS ownership and before sidebar/content geometry contract checks because rendering contract invariants (overlay infrastructure, theme boundary, z-index regression, breakpoint ownership) are foundational to Shell's rendering layer.
- Shell sidebar/content geometry contract runs after Shell rendering contract and before asset registry checks because canonical geometry extraction (sidebar position/sizing, main-content padding, grid layouts) must be verified before published assets are validated. This gate enforces that `.layout-sidebar`, `.app-shell`, `.app-sidebar`, `.layout-main`, `.main-content`, and `.container` geometry rules live in the new canonical `shell-sidebar-content-geometry.css`, with no geometry remnants in `shell-navigation.css`, `shell-layout.css`, `shell-forms.css`, `shell-tokens.css`, or `shell-operator.css`.
- Shell CSS and asset registry checks run before operator/admin/display surface checks because styling ownership and published assets affect runtime surfaces.
- Operator, display, and admin route checks validate user-facing boundary surfaces before Studio and resolved-experience checks.
- Operator responsive navigation integrity check is self-contained (PHP lint, static source reads, probe execution) with no dependencies on other gates. It is appended at the end to avoid disrupting pre-existing gate ordering.
- Studio boundary/readiness checks run before resolved-experience and contribution checks because Studio must remain a governed worker, not runtime truth.
- CSS Token Editor safety check runs after Studio readiness and before Localization Studio boundary checks because CTE is a live-editing Studio tool with the highest invariants risk.
- Localization Studio boundary check runs after CTE safety and before Localization Studio resource diagnostic checks because the boundary gate validates Studio implementation invariants before the content diagnostic runs on locale files.
- Localization Studio resource diagnostics run after boundary checks and before Customization Studio boundary checks because locale file content validation (keys, naming, duplicates, boundary violations) extends the same ownership domain validated by the boundary gate. Content validation is subordinate to implementation-boundary validation — if the boundary gate fails, content diagnostics are irrelevant.
- Localization migration guardrail runs after resource diagnostics and before v2.1 readiness because it enforces the canonical-path migration policy — no new legacy-path locale files — which is a structural policy check that must be validated after content diagnostics and before v2 implementation readiness verification.
- Localization Studio v2.1 readiness gate runs after migration guardrail and before Customization Studio boundary checks to verify that no unauthorized early implementation patterns (save/apply/update/delete routes, form mutations, DB tables, migrations, runtime coupling, file downloads) have appeared in Localization Studio before the v2 implementation approval. This gate enforces the Level 2 readiness barrier — diagnostic implementation without feature implementation.
- Label Designer boundary checks run after Localization Studio readiness and before Customization Studio boundary checks because Label Designer now has owner-owned resource contracts and read-only discovery, but must remain protected from save/apply/create/edit behavior, DB discovery, runtime print execution, and owner-source-of-truth drift before stronger features begin.
- CSS Live Editor preview boundary check runs after Token Impact Explorer and before Report Designer P1 because it validates that the read-only click-inspector preview feed remains static-fixture-based, has no DB access, no save/apply/edit controls, no arbitrary template path loading, and is served through an iframe with restricted sandbox (`allow-same-origin` only, no scripts/popups/navigation/forms). The preview boundary diagnostic is narrower and more safety-critical than Report Designer P1 (which has no runtime or preview route at this stage), so it runs first.
- Customization Studio boundary checks run after Studio readiness to confirm Studio draft/preview workflows remain disconnected from runtime truth and apply behavior, with only the approved Studio-local `radius.scale` draft artifact write allowed.
- Shell Style catalog boundary checks run after Studio readiness to confirm Shell placeholder/catalog contracts stay read-only and disconnected from Studio draft/runtime coupling.
- Platform Style Registry boundary checks run after Shell Style catalog checks to confirm approved-registry placeholder ownership remains disconnected from Shell consumption and Studio apply flows.
- Cross-owner Style Chain parity checks run after the three owner-specific diagnostics to verify chain completeness while preserving disconnection boundaries end to end.
- Shell approved style consumption boundary checks run after style chain parity to verify Shell runtime files have no unapproved coupling to Studio artifacts, Platform StyleRegistry runtime consumption, public/assets source-truth, or Core mutations before consumption implementation begins.
- Read-only consumption probe boundary checks run after Shell approved style consumption boundary to enforce diagnostic-only guardrails for the future CLI probe at `scripts/platform/probe_resolved_style_consumer.php` before any probe implementation or Shell runtime registry consumption wiring. The probe script may be absent; the gate still passes while validating contract documentation and future side-effect prohibitions when the script appears.
- Platform Style consumer boundary checks run after the consumption probe gate because the Platform-owned `ResolvedStyleConsumer` placeholder is the companion diagnostic target for the probe — it must remain disabled, side-effect-free, and uncoupled to Studio/Shell/DB/HTTP/routes/registry/drafts before any runtime consumption is enabled. Both the probe and consumer are Platform-owned diagnostic infrastructure and should be validated as a group before moving on to surface contribution or navigation composition checks.
- Shell consumption boundary checks run after the Platform Style consumer boundary gate and before resolved-experience checks because the consumer boundary must be validated first (proving Platform/Style is safe) before Shell is checked for unauthorized imports of Platform/Style types, registry paths, or registry read methods. The Shell consumption gate is the Shell-side counterpart to the consumer gate — it protects Shell from importing or calling consumer internals before the consumption surface contract exists. Both gates must pass before any consumption implementation is authorized.
- Platform Style consumption surface boundary checks run after the Shell consumption boundary gate and before resolved-experience checks because the consumption surface is the Platform-owned typed accessor layer that bridges the consumer-to-Shell gap. It must be validated (contract exists, no premature implementation, no forbidden imports/methods/operations, runtime consumption disabled) before any Shell-facing consumption planning can proceed. The consumption surface gate is the Platform-side gate that proves the surface is safe before Shell may eventually import it.
- Runtime Style Application boundary checks run after the consumption surface boundary and before resolved-experience checks because the future Platform-owned application class may map only `radius.scale` to `--corner-radius`. The gate allows the implementation to remain absent, blocks generic CSS/token engines and forbidden dependencies, and prevents Shell or layout wiring until a later explicitly authorized integration slice.
- Shell insertion boundary checks run immediately after the Runtime Style Application boundary because the Platform mapping must be proven safe before the future Platform-to-Shell handoff is considered. The gate permits the `platform/Style/ShellInsertion/` implementation directory to remain absent, limits the future target to the admin `.layout-main` wrapper and `--corner-radius`, blocks direct registry/reader/consumer access, and keeps RuntimeStyleApplication disabled with no Shell wiring.
- Rendered Admin Proof boundary checks run immediately after Shell insertion because the disabled Platform handoff must remain compliant before a synthetic end-to-end proof can be considered. The gate allows both proof directories to remain absent, confines future proof artifacts to one Platform proof object and one CLI harness, preserves both global disabled flags, and blocks production Shell, route, CSS, theme, Registry, asset, DB, HTTP, and command coupling.
- Navigation composition duplicate checks run after surface contribution checks to enforce app/module/plugin navigation ownership truth without mutating runtime behavior.
- Migration-debt and capability-ownership checks run after boundary and composition checks so they can catch regressions and cross-domain ownership leaks.
- Business app/module contracts run after platform/system boundaries are validated.
- Shared App and App Extension contract runs immediately after business app/module contracts because the base ownership contract must be validated first; the gate then validates the narrow governance metadata (shared_contract and ownership_type) without introducing new runtime type, extensions directory, or loader.
- Parties contract runs immediately after Shared App and App Extension contract because the Parties Shared App skeleton must be validated for canonical ownership (no App-level scope, no findOrCreate, no company_id, no status) before theme and later gates. It is the first concrete Shared App and its gate proves the v1 canonical data owner without consumer migration. because the base ownership contract must be validated first; the gate then validates the narrow governance metadata (shared_contract and ownership_type) without introducing new runtime type, extensions directory, or loader.
- Theme source integrity runs last as the final v1 architecture hardening gate — it verifies theme source files remain tokens-only, foundation/semantic separation is preserved, known owner selectors are not reintroduced, and manifest ordering is correct after all other surface/ownership/contract gates pass.
- Theme runtime fallback contract runs after theme source integrity to validate post-setup preference normalization resilience (invalid/legacy/null values resolve into allowed built-in choices without recursive fallback drift).
- Style Compliance CSS ownership runs after theme runtime fallback to enforce Studio tool rendering ownership boundaries: no inline Style Compliance CSS in views, mandatory external owner stylesheet linkage, and required shared primitive consumption for tool action links.
- First-boot CSS safety runs after the late-stage style ownership and theme fallback checks because it verifies the setup-only static delivery contract without changing the existing runtime Theme Architecture.
- Studio deletion family capability suite runs last as a self-contained aggregate certification wrapper for the 14 canonical deletion capability gates and 14 OwnerStructure deletion workspace gates. It preserves existing gate ordering by appending a single orchestration gate instead of inserting 28 direct entries into the main runner.

## Enforcement

The runner contains a self-contract section that compares the active `scripts` list against the expected list before executing any gate.

The runner also enforces architecture gate coverage index alignment and fails if:

- coverage index file is missing
- coverage index does not list every active aggregate gate path
- coverage index does not include required section markers: protected law, owner layer, scan scope, pass/fail meaning, non-goals, known warnings/debt
- architecture README does not link the coverage index

It fails if:

- the number of gates changes
- a gate path changes
- a gate is removed
- a gate is inserted
- a gate is reordered

## Validation

Run:

```bash
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
```

Expected result:

```text
ARCHITECTURE GATES: PASS
DEPLOYMENT READINESS: PASS
```

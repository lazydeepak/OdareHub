# Architecture Gates

These scripts are read-only System Tools foundation checks for OdareHub architecture boundaries. They inspect files and the current git diff; they must not mutate runtime state, data, routes, permissions, or generated artifacts.

Run the aggregate gate suite from the repo root:

```bash
scripts/architecture/run_architecture_gates.sh
```

For law-to-gate ownership mapping, scan scope, pass/fail meaning, and known debt inventory, see:

- `docs/architecture/architecture-gate-coverage-index.md`

For checkpointed known debt posture, no-touch boundaries, and future safe work candidates, see:

- `docs/architecture/known-architecture-debt-and-next-safe-work.md`

## When To Run

Agents must run the gates before architecture-related runtime edits, especially work touching Core, Shell, operator/admin/display surfaces, Studio, ACL/Profile, routing, or UI/CSS ownership.

Run them again after any gate-driven tooling or documentation update. Run them before committing any architecture slice.

## Gates

- `check_core_lock_scope.sh`: Scans current git diff and untracked files under `/app` for Core Engine changes. Fails when Core changes are present unless explicitly approved with `ARCHITECTURE_GATE_ALLOW_CORE=1`, and prints visible override/failure reasons.
- `check_system_app_contracts.sh`: Verifies Shell and Platform system-app contract baselines: manifest/navigation/routes/AGENTS presence, explicit `system_app_contract` and `ownership_contract` metadata, and lifecycle policy clarity as read-only diagnostics.
- `check_shell_runtime_menu_boundary.sh`: Verifies layout runtime composes sidebar via Shell runtime composer and does not directly invoke Core SidebarBuilder from header chrome, while keeping compatibility metadata explicit in the Shell composer.
- `check_shell_css_ownership.sh`: Scans Shell views/styles for inline styles, page-local style blocks, hardcoded colors, and app-specific selectors that would make Shell own app/module UI.
- `check_asset_registry_integrity.sh`: Reads manifest-declared `styles` entries and reports malformed declarations, unsafe paths, missing owner source CSS, and missing published `/public/assets/apps/...` targets without creating dummy files or changing runtime behavior.
- `check_operator_confinement.sh`: Scans operator-rendered paths and contribution surfaces for raw `/apps/*`, `/ops/*`, or `/admin/*` links/actions that would break `/u/{username}/*` confinement.
- `check_display_readonly.sh`: Scans display surfaces for forms, POST handlers, submit controls, inline handlers, or script mutation patterns that would break readonly kiosk behavior.
- `check_admin_route_contract.sh`: Verifies `/admin/{username}` preprocessing, Shell `/admin` handling, `/me` legacy alias support, admin wrapper registry shape, and absence of primary `/me` link emissions from Shell operator/admin emitters.
- `check_studio_boundary.sh`: Verifies Studio exists as optional `apps/Studio`, Core has no Studio runtime dependency, business/reference apps do not depend on Studio runtime, and Shell contains only conditional bridge behavior.
- `check_studio_enforcement_readiness.sh`: Verifies Studio governance readiness contracts and manifest boundaries before live apply expansion: canonical Studio route, compatibility bridge marking, Studio operating/resource/change/approval docs, risk levels, required change-record fields, System Tools blocking language, validation evidence, snapshots, rollback, handover, owner context, and non-runtime-truth rules.
- `check_cte_safety.sh`: Verifies CSS Token Editor critical invariants: CSS fetch URL from server attribute, `updateTokenStatus()` does not write `tokenValues`, `selectorField.value` initialized on load, `(*NO_JIT)` in save service regex, and server-only save authority.
- `check_localization_studio_boundaries.sh`: Verifies Localization Studio v1 foundation invariants: placeholder folder/tool.json/README/architecture doc exist, tool.json is valid JSON, no save/apply/write behavior exists, runtime code does not reference Studio localization drafts, Core locale is untouched, locale files remain owner-owned, and no generated locale cache becomes source truth.
- `check_customization_studio_boundaries.sh`: Verifies Customization Studio owner-home confinement while allowing only the Studio-local `radius.scale` Visual Customizer draft artifact write and enforcing no Shell draft reads, no apply/runtime/registry-write routes or behaviors, no `public/assets` source-truth coupling, and no Core coupling.
- `check_shell_style_catalog_boundaries.sh`: Verifies Shell Style placeholder homes and socket catalog JSON validity/status while enforcing no Studio draft coupling, no premature Shell view/layout consumption markers, no `public/assets` source-truth coupling, and no Core Shell Style customization coupling.
- `check_platform_style_registry_boundaries.sh`: Verifies Platform Style Registry placeholder homes and placeholder JSON validity while enforcing no Shell runtime connection, no Customization Studio registry-write coupling, no `public/assets` source-truth coupling, and no Core StyleRegistry coupling.
- `check_style_chain_parity.sh`: Verifies all three style-chain owner homes/diagnostics exist together and remain disconnected: no Studio registry writes, no Shell draft reads, no Shell Platform registry consumption, no `public/assets` source-truth drift, and no Core coupling.
- `check_resolved_experience_truth.sh`: Scans Shell runtime layers for active legacy visibility fields becoming hidden source of truth instead of compatibility carriers behind resolved experience.
- `check_surface_contribution_contracts.sh`: Scans contribution-related Shell/app/module paths for wrapper-confinement risks, direct contribution URL/action rendering that bypasses normalization boundaries, display readonly violations, contribution-specific CSS leakage into Shell styles, and hidden visibility-truth field usage in contribution contexts.
- `check_navigation_composition_duplicates.sh`: Scans app/module/plugin navigation contributions for ownership-scope mismatches, duplicate URL/source/menu signals, suppressed nav inventory, and legacy `app/Navigation` bridge runtime URL regressions.
- `check_migration_debt_regressions.sh`: Scans current runtime diff additions for documented migration-debt regressions: new primary links to compatibility aliases, new ACL presentation/layout fields acting as runtime composition truth, new Core/Shell/business-app dependencies on Studio runtime internals, and new active usage of frozen legacy Shell/Base/Core composition artifacts.
- `check_capability_ownership_boundaries.sh`: Verifies QR remains declared as a Platform-owned integration module until approved migration, reports existing QR coupling/legacy namespace debt, checks that `PdfService` stays app-agnostic, verifies PDF templates remain with app/module surface owners, confines dompdf usage behind the technical renderer boundary, and prevents Packages from being treated as feature/runtime owners.
- `check_business_app_module_contracts.sh`: Verifies reference Business Apps and their module manifests expose the minimal ownership metadata needed for app/module route, contribution, permission, dependency, lifecycle, and must-not-own boundary clarity.

## Additional Diagnostics

- Additional read-only diagnostics may still exist outside the aggregate runner for targeted architecture slices.

## Interpreting Results

`ARCHITECTURE GATES: PASS` means the checked boundaries did not reveal a concrete architecture leak. Do not invent runtime patches when gates pass. If the task is to improve architecture quality after a pass, inspect whether the read-only gates or documentation are too weak, too noisy, or missing Charter v1 coverage.

`ARCHITECTURE GATES: FAIL` means at least one read-only check found a possible architecture rule violation. Review the specific script output before editing. Fix only the failing architecture rule and keep the patch scoped to the owner responsible for that rule.

These gates are heuristic. A failure is a blocker until reviewed, fixed, or explicitly approved as a documented exception. A pass is not permission to redesign routes, polish sample apps, or change runtime behavior without a concrete architecture need.

Diff-focused gates intentionally allow existing documented compatibility debt to remain in place. They fail on new runtime additions that deepen that debt. Do not respond by removing aliases, migrating runtime code, or redesigning routes unless a separate approved task names that runtime change.

Some gates print warnings for documented debt while still passing. Treat those warnings as inventory and next-safe-work guidance, not as permission to move runtime code.

## Failure Report

Report failures using Architecture Charter v1:

1. Failed gate/script.
2. Architecture law broken.
3. Owner layer responsible for the fix: Core, Shell, App, Plugin, Studio, System Tools, Theme, or Tooling.
4. Why the fix is not sample-app polish.
5. Validation that proves the fix.
6. Commit hash after push.

If a gate passes, report that no runtime architecture patch was made and name any read-only tooling/docs improvement instead.

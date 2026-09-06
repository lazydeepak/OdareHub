# Resolved Runtime Contract / Compiler-Resolver Pipeline Baseline

Status: Active architecture baseline.

Purpose: Define how owner-owned source resources are validated and compiled into safe, fast runtime contracts consumed by Shell/runtime surfaces.

This document is architecture guidance only. It does not authorize runtime feature work, route redesign, app loading changes, or Core changes.

## Charter Laws Protected

This baseline protects Charter v1 laws:

- Apps own business logic.
- Apps contribute; Shell composes.
- Runtime consumes resolved/compiled contracts.
- Studio edits but does not own runtime truth.
- System Tools maintain/validate/rebuild but do not bypass governance.
- No hidden duplicate source of truth.

## 1. Inputs To The Compiler / Resolver

Compiler/resolver inputs are owner-owned source resources:

- App manifests and app-level ownership contracts.
- Module manifests and module-level capability contracts.
- Surface contribution resources: navigation/sidebar, operator/admin/display cards/panels, reports, actions/links.
- Permission requirements from app/module capability declarations.
- ACL authorization state and role-permission assignments.
- Workspace Profile policy shaping resources.
- User override resources within governed limits.
- Theme/CSS token resources and shared style primitives.
- Navigation/menu resources and route-family metadata.
- Widget/card/report contribution declarations.

Input rules:

1. Owner resources remain owner-truth and must be read without ownership transfer.
2. Legacy compatibility fields may be ingested only as transitional inputs, never as final runtime truth.
3. Studio drafts are not compiler inputs for production runtime contracts.

## 2. Compiler / Resolver Responsibility

Compiler/resolver is a preparation layer between source resources and runtime rendering.

Responsibilities:

1. Validate owner resources for schema/shape integrity and ownership boundary compliance.
2. Merge only allowed contributions across app/module boundaries.
3. Apply ACL authorization filter as hard allow/deny.
4. Apply Workspace Profile shaping after ACL constraints.
5. Apply user overrides only inside policy limits.
6. Produce resolved runtime contracts consumable by Shell/runtime.
7. Preserve provenance so contract outputs map back to owner resources.
8. Prevent hidden duplicate truth by rejecting competing visibility/permission authority.

Compiler/resolver must not:

- invent business meaning,
- grant permissions,
- bypass ACL/profile policy,
- consume Studio drafts as runtime truth,
- become owner of app/module resources.

## 3. Runtime Responsibility

Runtime layer (Shell and route-wrapper renderers) responsibilities:

1. Consume resolved/compiled contracts only.
2. Avoid repeated expensive owner-resource discovery at render time.
3. Avoid reading Studio draft/edit artifacts as runtime truth.
4. Avoid using legacy compatibility fields as primary visibility/composition truth.
5. Render contract outputs inside wrapper confinement and route-family boundaries.

Runtime must treat unresolved owner resources as build-time/resolver-time inputs, not direct render-time truth.

## 4. Cache And Invalidation Model

Runtime contract caches are valid only while relevant source dimensions are unchanged.

Invalidation rules:

1. App/module manifest or capability changes:
   - invalidate affected app/module contracts and any dependent surface contracts.
2. Permission/ACL changes:
   - invalidate affected resolved experience and authorization-shaped contracts.
3. Workspace Profile or user override changes:
   - invalidate resolved experience and visibility/presentation contracts for affected users/scopes.
4. Theme/CSS token changes:
   - invalidate style/design runtime contracts and any token-derived view contracts.
5. Navigation/menu/contribution changes:
   - invalidate navigation and affected surface composition contracts.

Cache behavior goals:

- deterministic invalidation keys,
- bounded recomputation scope,
- no stale visibility/security state after policy updates,
- no broad full-cache flush unless dependency graph is unknown.

## 5. Ownership Rules

Ownership is explicit and non-overlapping:

1. App/module owners own source resources and business meaning.
2. Compiler/resolver layer prepares runtime contracts from owner resources.
3. Shell/runtime consumes resolved contracts and enforces wrapper-safe rendering.
4. Studio edits source resources later through governance workflows; Studio does not own runtime truth.
5. System Tools validate/rebuild/diagnose contract artifacts; System Tools do not bypass policy or ownership boundaries.

## 6. Current Transitional State

Current state in this repository:

1. ResolvedExperience exists and is transitional toward broader compiler-resolver coverage.
2. Legacy compatibility bridges and aliases remain documented migration debt.
3. Existing architecture gates protect important slices (resolved truth, migration debt, contribution boundaries, route confinement, readonly display) but do not yet enforce the full end-to-end compiler-resolver pipeline contract.
4. Transitional inputs may still exist; they must not be promoted as primary runtime truth.

## Minimal Runtime Contract Families (Baseline)

The target runtime contract families include:

- resolved experience contracts,
- navigation/runtime menu contracts,
- surface composition contracts (admin/operator/display),
- capability visibility contracts,
- style/token runtime contracts.

This baseline defines boundaries and responsibilities, not a migration implementation plan.

## Validation

Use current aggregate architecture gates:

- scripts/architecture/run_architecture_gates.sh

The resolved-experience truth diagnostic is:

- `scripts/architecture/check_resolved_experience_truth.sh`
- `scripts/architecture/check_migration_debt_regressions.sh`

The diagnostic is read-only. It checks Shell/runtime and Platform governance paths for concrete regressions where transitional ACL/profile fields or direct role checks become active presentation or visibility truth. It must not mutate runtime state, routes, database records, migrations, generated assets, or owner resources.

Current diagnostics validate stable truth boundaries only:

- Source resources remain owner-owned inputs to the compiler/resolver.
- Compiler/resolver responsibilities must include ACL authorization filtering, Workspace Profile shaping, and user override shaping.
- ACL authorization is hard allow/deny only, not presentation or layout truth.
- Workspace Profile and user override shaping must remain downstream of ACL and inside policy limits.
- Resolved/compiled runtime contracts are the final runtime consumption surface for Shell/renderers.
- Shell/runtime consumes resolved contracts and must not create independent visibility sources.
- Studio remains a governed editor/worker and does not own runtime truth.
- System Tools validate, diagnose, and rebuild contracts only; they do not bypass ownership or policy.
- Compatibility fields such as `assigned_apps`, `module_visibility`, `operator_views`, and `dashboard_type` may exist as transitional inputs or parity diagnostics, but must not become new primary visibility authority.

This baseline does not authorize an end-to-end compiler/resolver implementation. Any future enforcement beyond read-only diagnostics should be introduced as a separate approved tooling slice.

Migration-debt regression diagnostics are diff-focused. Existing documented compatibility aliases, transitional ACL/profile fields, Studio bridges, and legacy composition artifacts may remain, but new runtime additions must not deepen them. The migration-debt gate should fail only on concrete new runtime risks such as primary links to compatibility aliases, `/me` primary usage, ACL presentation/layout fields used as composition truth, resolved-experience bypasses, Shell or business-app dependencies on Studio runtime internals, active usage of frozen legacy composition artifacts, direct route reinterpretation behavior, or hidden visibility-truth fields.

Related Studio readiness baseline:

- `docs/architecture/studio-operating-contract.md`

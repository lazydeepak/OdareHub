# ThemeDoctor Lifecycle Contract

Status: Current Theme Doctor capability is read-only diagnostic. The authoring lifecycle described below is deferred architecture.

> **Current-state notice**
>
> Theme Doctor is currently diagnostic-only.
>
> The previously designed draft/apply lifecycle is deferred pending adoption
> of the Appearance domain contract. Existing ThemeDoctor/Themes JSON files
> are legacy read-only draft inventory and are not runtime appearance truth.

This contract preserves the future lifecycle design without claiming that authoring is currently available. Theme Doctor retains preview, reset, copy, diagnostics, inventory, and recommendations. It exposes no mutation permission, POST mutation route, controller mutation method, or enabled save form.

## 1. Lifecycle Stages

ThemeDoctor upgrade path:

1. v0 = preview-only (current)
2. v1 = governed lifecycle draft mode (Deferred)
3. v2 = approved apply/delete/default actions (Deferred)

Stage constraints:

1. v0 keeps all mutation blocked.
2. v1 may create and edit drafts only through governed flow.
3. v2 may perform approved apply/delete/default and rollback, still through governed flow.

## 2. Deferred Ownership Model

Theme lifecycle ownership split:

1. ThemeDoctor owns editing workflow, analysis, preview, diff, and apply-plan preparation.
2. Platform/System owns approved instance theme registry truth.
3. Shell consumes resolved active/default theme contract only.
4. Public assets remain delivery output only, never authoring truth.

Owner law:

1. ThemeDoctor is a governed worker surface.
2. ThemeDoctor does not become runtime source-of-truth owner.

## 3. Current Inventory And Deferred Source-of-Truth

Legacy draft inventory (read-only in the current state):

- `apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes/*.json`

Approved runtime registry source (Platform/System-owned):

- `storage/theme_registry/registry.json`
- `storage/theme_registry/themes/*.json`

Rationale:

1. Studio draft artifacts stay in Studio scope.
2. Approved instance runtime truth stays outside Studio tool workspace and is owned by Platform/System governance.
3. `public/assets/*` is generated delivery output and may be regenerated from approved registry.

## 4. Deferred Theme Definition Format

Canonical theme schema (JSON):

1. `key` (stable identifier)
2. `label` (localized display name key)
3. `extends` (optional base theme key)
4. `tokens` (CSS variable map)
5. `metadata` (author, created_at, updated_at, version, risk_level)
6. `status` (`draft` or `approved`)

Token contract:

1. Tokens map to CSS variables (`--bg`, `--text`, etc.).
2. Missing tokens are resolved by base theme inheritance.
3. Validation rejects unknown forbidden tokens and invalid values.

## 5. Deferred Governed Flow

All mutating operations must use:

`analyze -> preview -> diff -> approve -> snapshot -> apply -> rollback`

Rules:

1. No direct overwrite of public CSS artifacts.
2. No direct mutation without audit evidence.
3. No mutation route may skip snapshot creation.

## 6. Deferred Theme Lifecycle Flows

### 6.1 Create Theme Flow

1. Create draft JSON under ThemeDoctor draft source.
2. Run schema/token boundary validation.
3. Render preview against runtime token resolver (read-only).
4. Compute diff against base/selected reference.
5. Require approval gate and snapshot plan.
6. Apply writes approved theme into Platform/System registry source.
7. Record apply audit + rollback pointer.

### 6.2 Duplicate Theme Flow

1. Select approved or draft source theme.
2. Clone tokens/metadata into new draft key.
3. Enforce unique key and naming validation.
4. Continue through analyze -> preview -> diff -> approve -> snapshot -> apply.

### 6.3 Edit Theme Flow

1. Load target theme into draft workspace copy.
2. Apply token edits in draft only.
3. Validate schema, token safety, owner boundary.
4. Generate diff against current approved target.
5. Require approval and snapshot before apply.

### 6.4 Delete Theme Flow

1. Resolve references (active/default/use-sites).
2. Block delete if theme is active/default without explicit replacement plan.
3. Require replacement mapping for active/default dependencies.
4. Snapshot registry before delete apply.
5. Apply delete in approved registry and persist audit trail.

### 6.5 Set Default Theme Flow

1. Validate target theme exists and is approved.
2. Generate diff for registry default pointer change.
3. Snapshot current default pointer and registry state.
4. Require approval and apply guarded default switch.
5. Record rollback metadata for previous default.

## 7. Deferred Active/Default Deletion Safety

If active/default theme is requested for deletion:

1. Direct delete is blocked.
2. Operator must choose replacement approved theme.
3. System performs atomic plan: set replacement active/default then delete target.
4. If replacement validation fails, delete is denied.

## 8. Deferred Snapshot And Rollback Contract

Before every apply/delete/default change:

1. Snapshot registry manifest and affected theme JSON payloads.
2. Store snapshot metadata with actor, timestamp, reason, operation type.
3. Rollback restores previous registry pointer + prior theme payload set.
4. Rollback itself is governed and audited.

Suggested snapshot root:

- `storage/theme_registry/snapshots/`

## 9. Current Read Gate And Deferred Mutation Gates

Current permission requirement:

1. `studio.tools.theme_tool.use` for preview/read access.

Deferred permission design:

1. `studio.tools.theme_tool.mutate` for create/edit/duplicate/delete/default apply.
2. `studio.tools.theme_tool.rollback` for rollback execution.

Instance policy requirement:

1. `studio_tools.theme_tool` must be `enabled` before route dispatch.
2. Unknown policy key fallback is `disabled`.

Environment/risk gates:

1. Local/staging: draft + approved mutation allowed with approval gate.
2. Production: only approved operations with elevated approval policy and audit enforcement.
3. High-risk ops (delete/default/rollback) require explicit high-risk gate pass.

## 10. Deferred Public Asset Publishing Rule

1. ThemeDoctor never writes `public/assets` directly.
2. Approved registry becomes input for publish pipeline.
3. Publish pipeline generates delivery outputs (`public/assets/theme.css` etc.) as derived artifacts.
4. Derived assets can be rebuilt from approved registry at any time.

## 11. Current Shell Non-Consumption And Deferred Consumption Rule

1. Shell reads resolved active/default theme from approved Platform/System registry truth.
2. Shell does not consume ThemeDoctor private drafts.
3. Shell runtime behavior remains unchanged until dedicated rollout slice explicitly updates consumer integration.

## 12. Deferred Design Answers

1. Where are theme definitions stored?
   - Drafts/templates in `apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes/*.json`; approved runtime registry in `storage/theme_registry/registry.json` and `storage/theme_registry/themes/*.json`.

2. Who owns approved theme registry?
   - Platform/System governance owns approved registry truth; ThemeDoctor only proposes and applies through governed workflow.

3. How does ThemeDoctor create a new theme safely?
   - Create draft -> validate -> preview -> diff -> approve -> snapshot -> apply to approved registry -> audit.

4. How does ThemeDoctor delete a theme safely?
   - Resolve references -> block unsafe active/default delete -> require replacement -> snapshot -> approved delete apply -> audit.

5. How does ThemeDoctor set default theme safely?
   - Validate approved target -> diff pointer change -> snapshot -> approval gate -> apply -> audit.

6. What happens if active/default theme is deleted?
   - Direct deletion is blocked; replacement is mandatory and applied atomically before removal.

7. How rollback works?
   - Rollback restores snapshot of registry pointers and theme payloads under audited guarded execution.

8. What validation must run before apply?
   - JSON schema, token safety/boundary checks, owner boundary checks, reference integrity checks, policy/permission/environment/risk gates, diff generation, snapshot readiness.

9. What files/tables can ThemeDoctor write later?
   - ThemeDoctor draft artifacts in `apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes/`, approved registry artifacts in `storage/theme_registry/*`, snapshot/audit records in `storage/theme_registry/snapshots/*` (and optional governance audit table if introduced in a dedicated schema slice).

10. What must remain blocked?
   - Direct write to `public/assets`, any Core edits, unguarded mutation routes, runtime Shell behavior change, and any apply/delete/default action that bypasses `analyze -> preview -> diff -> approve -> snapshot -> apply -> rollback`.

## 13. Validation Anchor

Read-only diagnostic script:

- `scripts/architecture/check_theme_tool_lifecycle_contract.sh`

Current implementation note:

- Implemented: read-only registry and legacy draft inventory, diagnostics, recommendations, preview, reset, and copy.
- Blocked: draft save, create, edit, duplicate, apply, delete, default, rollback, and any write to Theme Doctor JSON, `storage/theme_registry/*`, or `public/assets/*`.
- Deferred migration map: `docs/migration-cleanup/maps/appearance-domain-migration-plan.md`.

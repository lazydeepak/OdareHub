# ERP App Studio Dry-Run Compile Contract

Status: foundation implementation boundary

Dry-run compile returns planned artifacts only.

It may:

- Read Studio draft JSON submitted through the existing `/ops/gui-studio` compatibility route.
- Validate schema and guardrail intent.
- Return a compile plan with target path, artifact type, change type, risk level, source template, content hash placeholder, and summary.
- Return deterministic artifact IDs, graph layers, dependency checks, conflict checks, and diff readiness metadata.
- Return ownership governance metadata, drift classifications, risk escalation decisions, grouped human summaries, and deterministic compile snapshot identity.

It must not:

- Write files.
- Create folders.
- Execute migrations.
- Mutate database rows.
- Publish packages.
- Roll back packages.
- Edit PHP or arbitrary code.

The current compatibility route remains `/ops/gui-studio`; future route renaming must preserve redirects and bookmarks.

## Compile Intelligence Layer

The compile intelligence layer is a read-only planner. It groups planned artifacts into app, module, view, navigation, permission, and package layers. It checks declared dependency relationships and target path safety, then marks conflicts for later review.

No conflict is resolved during dry-run compile. The output is shaped so a future diff preview can consume `before_hash`, `after_hash`, `target_exists`, and `diff_status` without changing the compile contract.

## Ownership Governance

Each planned artifact declares:

- `owning_app`
- `owning_module`
- `ownership_scope`
- `upgrade_safe`
- `customization_zone`
- `generated_by`
- `studio_project_id`
- `studio_draft_id`

`managed` artifacts sit inside the declared app/module scope and can be governed by Studio metadata. `unmanaged` artifacts are existing user-editable targets without Studio ownership metadata. `external` artifacts are outside the declared ownership boundary. `unknown` artifacts cannot be classified safely.

Customization zones describe future edit rules:

- `protected`: migration, package, permission, or other guarded lifecycle targets.
- `generated`: template-owned output that should remain reproducible.
- `user_editable`: generated starter output where human edits must be preserved.
- `none`: no safe customization rule is available.

## Drift And Risk

Drift detection is still non-writing. It classifies the target state as:

- `clean`
- `modified`
- `unknown`
- `external`
- `conflict`
- `unmanaged`

Risk is escalated to `blocked` for unsafe paths, missing dependency metadata, ownership conflicts, unmanaged targets, external artifacts, modified generated/protected artifacts, and blocked artifact types. Unknown ownership or drift escalates to `high`. The planner reports these states for review; it does not repair or overwrite anything.

## Diff Readiness

The compile output includes future diff inputs:

- `before_hash`
- `after_hash`
- `target_exists`
- `drift_status`
- `diff_status`
- `diff_summary`
- `human_diff_summary`
- `machine_diff_summary`

The current phase does not render a visual diff. Human summaries are grouped into added artifacts, changed artifacts, no-op artifacts, conflicts, blocked items, ownership warnings, and drift warnings.

## Compile Snapshot Identity

Dry-run compile returns a deterministic identity block:

- `compile_id`
- `bundle_hash`
- `artifact_count`
- `dependency_check_count`
- `conflict_check_count`
- `drift_check_count`
- `generated_at`
- `schema_version`

The dry-run compile contract still emits deterministic preview metadata for compatibility. Governed publish/apply flows now persist approval decisions, snapshot records, audit snapshots, rollback plans, exported package signatures, and post-publish verification results under `storage/appstudio/`.

Publish governance is now enforced through the staged Studio lifecycle (`preflight` -> `publish-gate` -> `apply-snapshot`) rather than being blocked by missing approval storage, snapshot persistence, rollback contracts, package signing, or post-publish verification. Future enhancements may deepen visual review and external trust/signing, but those are no longer absent lifecycle prerequisites.

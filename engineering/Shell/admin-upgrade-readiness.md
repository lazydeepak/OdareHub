# Admin Upgrade Readiness

Date: 2026-07-13

Catalog state: **17 promoted**, **0 implementing**. Both legacy admin dashboards have been removed after parity verification, and the last resilience monitoring candidate is now connected to authoritative owner histories.

## Placement completed in this pass

- Platform Mode: governed System Tools workspace, shared service/action, lock state, localized controls.
- Platform status: stable keyed Base-owner snapshot contract feeds the graduated Platform Admin Status component without controller or translated-label coupling; internal workbench names remain compatibility details.
- Health: Platform Health is localized and class-based; Module Health, Runtime Report, and Architecture Health retain distinct meanings.
- Logs: runtime evidence, export audit, and governed data audit are grouped under Logs, Reports & Audit.
- Apps: lifecycle manager, inventory, dependencies, exports, and recovery entry points are grouped without merging mutation handlers.
- Setup and Environment: the retained specialist state machine now has a localized, class-based review surface; snapshot, preview, scoped apply, cancellation, dependency handling, downloads, and history remain connected to their original owners.
- Configuration: environment visibility, validation, editing, import/export, and resolved previews now share a localized, class-based surface; sensitive database and resolved-preview values are masked while original save/import/export handlers remain authoritative.
- Release: readiness, blocker review, version context, preview-token-confined generation, cancellation, history, and downloads now use a localized, class-based specialist surface.
- Setup Overview: structured owner-provided statuses now use governed status classes instead of provider-supplied inline presentation, while setup-profile execution remains unchanged.
- Schema: Base Builder remains the mutation owner and canonical schema workspace; module registration, single/bulk synchronization, field metadata, soft-disable confirmation, and CSRF boundaries are preserved while the remaining visible form copy is localized.
- Governance: access, users, experience/visibility, notifications, and audit have separate Platform Operations lanes.
- Governance implementation: Access, User Control, Workspace Profiles, Audit, and Notifications remain separate owner workflows but now share governed presentation handoffs; compose/user/profile labels are localized, embedded notification CSS is Shell-owned, and destructive confirmations plus CSRF boundaries remain intact.
- Organization: retained as its own localized owner workspace and surfaced through Apps & Config; company, branch, fiscal, and branding handoffs remain distinct.
- Developer architecture: Route Registry remains the detailed route authority and now uses Shell-owned presentation plus localized runtime states; Architecture Health is the summarized contract/localization/surface diagnostic and includes EN, JA, and NE coverage.
- Developer authoring: Widget Builder retains draft/publish/archive/restore/clone and bulk-action ownership while its remaining inline presentation is moved to Shell classes.
- Data Control: retained as the governed browse/preview/approve/execute/audit workflow.
- Runtime and lifecycle presentation: Runtime Report boolean/state labels and App Manager lifecycle/status presentation are localized and class-based; text export, uploads, lifecycle actions, confirmations, bundle details, and exports remain connected.
- Platform dashboard parity: Platform Admin Status explicitly loads the Base-owned snapshot adapter, displays all nine live owner KPI values exactly (`5`, `27`, `0`, `0`, `2`, `0.01`, `1`, `0`, `0`), retains the three environment facts, and links to specialists without importing legacy mini/sync mutations. Missing or empty owner payloads render an explicit localized degraded state with no invented values. Migration evidence and source comparisons are visible only in Development mode.
- Data Control presentation: the canonical entry now accurately describes the implemented browse → preview → approve → execute → audit workflow, with governed action styling and unchanged permission boundaries.
- Backup/export/restore: the read-only System Tools resilience monitor merges authoritative export, import, restore, environment portability, and release histories by timestamp and links every record back to its specialist owner. Mutation, downloads, retention, and retry behavior stay in those specialist workflows; unavailable histories degrade explicitly without synthetic state.

## Sources intentionally retained

- `/admin/{username}` now owns both Platform Admin and App Admin role-aware homes. App Admin receives the full assigned-app payload, scope-safe empty state, and owner links directly in Unified Admin.
- All specialist Setup, Base, Access, User, Profile, Audit, Organization, App Manager, Data Control, and builder routes.

## Remaining parity evidence

1. Platform Mode failure decisions are isolated and probe-verified: expired/missing CSRF, invalid mode, and deployment lock cannot permit writes, while return paths remain allowlisted. Authorized Development, Demo, and Production switching plus wrong-method feedback are live-verified; Development was restored. A browser-level lock presentation check still requires a server process intentionally started with `PLATFORM_MODE_LOCK_ENABLED=true`.
2. App-admin deterministic full-payload and scope parity is complete. No App Admin account currently exists in the local database, so no identity was created or impersonated for an additional visual pass.
3. Legacy Platform Admin and App Admin dashboard retirement is complete.

No cleanup candidate is approved merely because its navigation destination is now clear.

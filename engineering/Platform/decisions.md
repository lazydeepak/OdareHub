# Platform — Decisions

## Decision Log

### 2026-08-19 — V1 recovery provider uses local MySQL/MariaDB dump plus filesystem archive
**Status:** Accepted

**Context**
OdareHub currently uses `mysqli` with MySQL/MariaDB configuration, has no complete database backup provider, and must support local Windows/client installs before installers or online updates. Existing suite export/restore is selected module/table transport, not product recovery.

**Decision**
Use one V1 local provider: a MySQL/MariaDB-compatible dump plus a filesystem ZIP archive, coordinated by a Platform-owned recovery capability and exercised through a governed CLI before any web mutation surface. Require checksum verification and an isolated restore rehearsal. Defer cloud snapshots and multi-provider abstraction until a second real deployment provider is needed.

**Consequences**
The first implementation can be small and testable on local installations while remaining compatible with later installer and private-update work. Update apply stays blocked until the provider creates verified recovery points and rehearsal evidence under the recovery contract.

**Evidence**
`docs/architecture/backup-provider-implementation-plan.md`; `docs/architecture/backup-restore-recovery-contract.md`; `README.md`; `app/Core/DB.php`; `app/Services/SuiteExportService.php`.

### 2026-08-19 — V1 update apply is blocked pending verified database and filesystem recovery
**Status:** Accepted

**Context**
The deployment audit confirmed that existing suite export/restore flows cover selected module packages and table data, while no complete database backup/recovery provider, whole-installation filesystem recovery point, or rehearsed product rollback exists.

**Decision**
Block V1 update apply, repair upgrade, installer upgrade, and private online update work until an implementation produces and verifies a full database recovery point plus a filesystem/configuration/customer-state recovery point. Restore must reach one explicit final state and pass the defined post-restore verification before a recovery point is accepted.

**Consequences**
Existing `SuiteExportService`, `SuiteRestoreService`, restore routes, history services, and migration snapshots remain useful local/domain mechanisms but cannot authorize product update rollback. The next design work is backup-provider selection/contract and read-only package compatibility preview.

**Evidence**
`docs/architecture/backup-restore-recovery-contract.md`; `docs/architecture/deployment-update-foundation-audit.md`; `app/Services/SuiteExportService.php`; `app/Services/SuiteRestoreService.php`.

### 2026-08-19 — V1 local release uses one vendor-included app-lane package
**Status:** Accepted

**Context**
The deployment audit found one PHP modular monolith, a committed `composer.lock` and `vendor/` tree, hosting scenarios where Composer cannot run, existing release ZIP generation without a channel identity/checksum contract, and no safe apply implementation.

**Decision**
Define V1 as one filesystem-backed `app`-lane OdareHub release package. Include `vendor/` with `composer.json` and `composer.lock`; preserve customer configuration/state outside the package; bind sidecar metadata and the future `channel.json` entry to an immutable SHA-256 and byte size; make preview read-only; and defer signing, entitlement, online distribution, and apply implementation.

**Consequences**
V1 client delivery does not require Composer at install/update time. A checksum is integrity evidence only, not a signature or authorization mechanism. Backup/restore proof, compatibility preview, and explicit operator approval remain mandatory before any apply slice can be authorized.

**Evidence**
`docs/architecture/local-release-channel-contract.md`; `docs/architecture/deployment-update-foundation-audit.md`; `README.md`; `composer.json`; `composer.lock`.

### 2026-08-19 — Freeze productization vocabulary and sequence before Hospitality
**Status:** Accepted

**Context**
Runtime discovery recognizes top-level app bundles through `apps/*/manifest.json` and `core_apps`, while module lifecycle still uses `plugin.json` and several lifecycle services use legacy suite vocabulary. Beginning Hospitality, installer, private updates, or large app/module refactors without one declared model would extend this ambiguity.

**Decision**
Keep OdareHub a modular monolith. Treat App as the current runtime/lifecycle unit; freeze System App, Domain App, Shared App, Module, App Extension, Plugin, and Package meanings; reserve Suite for commercial/product or documented legacy grouping terminology until first-class suite architecture is separately approved. Deliver local deployment/update, backup/restore, and installer readiness before Hospitality implementation. Defer private `update.susankhya.com`, and position `cloud.susankhya.com` first as a customer/license/control portal rather than business-data synchronization.

**Consequences**
Hospitality remains a future Domain App, not a new runtime suite. Current Manufacturing Products/Parts remains Manufacturing-owned. Studio remains a future governed diagnostic/proposal workbench, not an autonomous upgrader or Hospitality generator. Any future installer or updater must consume the approved app/module/package and backup/restore contracts.

**Evidence**
`docs/architecture/odarehub-productization-roadmap.md`; supporting baselines: `app-ownership-classification-and-hospitality-readiness.md` and `local-deployment-update-channel-plan.md`.

### 2026-08-19 — Local deployment and update channel comes before Hospitality
**Status:** Accepted

**Context**
Hospitality should not start before OdareHub has a repeatable local deployment and update path. The repository already contains readiness, release packaging, release history, upgrade assistant, and version catalog services, but no clear local update channel baseline has been frozen.

**Decision**
Prioritize local deployment first with a filesystem-backed local update channel. Keep cloud distribution, hosted update APIs, automatic background updates, marketplace distribution, and first-class Suite lifecycle out of V1.

**Consequences**
Hospitality planning remains valid, but implementation waits until local deployment/update channel work has a usable baseline. New deployment planning should use app/module/package/channel vocabulary and avoid reviving `suite` as deployment terminology except where documenting legacy code.

**Evidence**
Planning baseline recorded in `docs/architecture/local-deployment-update-channel-plan.md`.

### 2026-08-17 — App ownership model is the Hospitality readiness gate
**Status:** Accepted

**Context**
The repository has mixed historical terminology across app, bundle, suite, plugin, and module lifecycle code. Hospitality should not start by adding another partially recognized concept.

**Decision**
Use App as the current runtime and lifecycle truth. Use Suite only as product/composition language until a first-class suite registry, manifest, and lifecycle service exist. Classify all current and future capabilities by ownership type before Hospitality implementation: Core Engine, System App, Platform Engine, Shared App, Domain App, App Module, App Extension, Studio Tool, Plugin / Adapter, or Legacy Bridge.

Hospitality may be marketed as Hospitality Suite, but the initial runtime owner is Hospitality App at `apps/Hospitality` with app key `hospitality`.

**Consequences**
Hospitality implementation is gated by an ownership audit. Studio remains a future system doctor and upgrade workbench, but it must not be relied on to generate Hospitality until the repository model is clear enough for Studio to inspect and reason about it.

**Evidence**
Planning baseline recorded in `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`.

### 2026-07-03 — Engineering Workspace contract is mandatory for engineering operations
**Status:** Accepted

**Context**
Prompt-only reminders to read Engineering Workspace documents are too weak. Engineering agents, reviewers, planners, and implementation workflows need a platform contract that makes workspace resolution and contract loading procedural.

**Decision**
Every engineering operation begins by resolving its Engineering Workspace and loading the workspace contract before repository inspection. Engineering Workspace is the authoritative source of engineering intent and governance. Repository code is implementation truth, the current user request is task truth, and probes/tests/browser evidence are validation truth.

Unresolved implementation-capable work is blocked, including platform-scoped work. Platform implementation must resolve a platform Engineering Workspace such as `Platform` or another exact affected workspace.

**Consequences**
Agents no longer have a valid implementation path that skips the workspace contract. When workspace intent conflicts with repository truth or the current task, agents must report the conflict instead of silently choosing one source.

**Evidence**
Updated `platform/Engineering/EngineeringWorkspaceAgentProtocol.md`, bootstrap/gate instruction prefixes, execution gate decision behavior, and probes:
`probe_agent_bootstrap.php`, `probe_agent_execution_gate.php`, and `probe_agent_context_integration.php`.

### YYYY-MM-DD — Decision title
**Status:** Proposed / Accepted / Superseded

**Context**
Why the decision was needed.

**Decision**
What was decided.

**Consequences**
What this enables, constrains, or requires next.

**Evidence**
Relevant validation, source references, or implementation links.

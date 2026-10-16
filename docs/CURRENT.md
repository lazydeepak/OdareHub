# OdareHub OS Current Context

This file is the current-state router for agents working in OdareHub OS. Keep it short, factual, and updated when the active milestone or architectural baseline changes.

## Current State

OdareHub OS is a modular business operations platform built around a Core -> Apps -> Modules architecture. Plugins are extension/provider mechanisms, not primary business applications.

The active product direction is:

- workflow-first operations instead of static data-entry screens
- role-shaped work surfaces, especially `/me`
- assignment-driven access and dashboard visibility
- app-owned modules and owner-declared capabilities
- Shell, Platform, Studio, Manufacturing, SBAIO, Procurement, and Plugin boundaries kept explicit

The disposable release/update foundation is complete through OH-AUTO1-080.
Production installation, update, and apply remain unauthorized. Product
development has resumed on the Shell worker surface; OH-AUTO1-096 is the
completed scope-safe work/process summary milestone and OH-AUTO1-097 is the
completed restricted-scope My Work containment milestone.

## Active Focus

Completed execution milestone:

- Release/update foundation — isolated installation, signed update,
  post-update verification, and recovery proof. OH-AUTO1-080 final disposable
  safety closure is complete; production apply remains blocked.

Completed current product milestone:

- OH-AUTO1-093 — scoped the operator Processing projection to authorized parts
  and gated all four existing Processing mutations through Manufacturing-owned
  product, module, profile, and transition authority.
- OH-AUTO1-094 — preserved read-only denial on every Processing POST authority,
  separated existing Assembly/QC module authority, and suppressed unauthorized
  lane actions before Processing record lookup or writes. The existing
  caller-owned Base context resolver's transitive schema initialization remains
  unresolved and unchanged.

Approved execution sequence:

1. OH-AUTO1-067 — completed read-only release-signature diagnostics;
2. OH-AUTO1-068 — completed bounded disposable database requalification;
3. OH-AUTO1-069 — current governance reconciliation;
4. release authority, trust, installed build identity, and controlled migration
   integration;
5. isolated signed update, post-update verification, and recovery proof; then
6. broader business-feature expansion — now resumed with the worker surface;
   production installation/update remains blocked.

Evidence for the completed milestones is recorded in:

- `docs/architecture/oh-auto1-067-read-only-release-authenticity-preview-2026-10-15.md`
- `docs/architecture/oh-auto1-068-database-isolation-requalification-2026-10-15.md`

## Next Product Focus

- OH-AUTO1-095 — the read-only work/process-area authority inventory is
  complete. It found no independent area authorization dimension.
- OH-AUTO1-096 — the bounded Manufacturing-owned scope-safe work/process
  presentation projection is complete for existing Assembly/QC Processing
  lanes. Machine/production and broad My Work sources remain excluded pending
  equivalent scope evidence.
- OH-AUTO1-097 — the broad My Work projection now fails closed before limited
  queue collection for restricted operational scopes. Explicit empty scope
  retains its existing owner-defined unrestricted meaning; Core remains
  unchanged.
- OH-AUTO1-098 — the restricted-scope state is forwarded through Shell `/me`
  composition and rendered as a localized unavailable state. Notifications and
  role links remain suppressed because their current providers do not provide
  an independently proven, non-mutating, scope-safe read contract.
- OH-AUTO2-100 — restricted My Work now performs a read-only assignment/scope
  preflight before legacy schema/bootstrap paths. Restricted, malformed,
  denied, and unavailable evidence remains contained; explicit unrestricted
  scope preserves the established path. Unrestricted notification/escalation
  safety and parallel `/me` provider mutation remain deferred.
- OH-AUTO2-101 — My Work notification summary and recent-list reads no longer
  initialize notification schema or invoke escalation. Missing notification
  storage is surfaced as unavailable to the My Work projection. Explicit
  escalation service entry points remain separate; no scheduler or route was
  added.
- OH-AUTO2-104 — Material Management `/me` summaries now use a non-mutating
  read path with missing/incompatible storage suppression; ordinary setup and
  mutation consumers retain schema authority.
- OH-AUTO2-105 — Manufacturing host providers now require existing Platform
  app/module authority before operational collection. SBAIO personnel/payroll
  summaries and watchlists remain suppressed because no record-level
  authorization contract is available; route-authorized navigation remains.
  Implementation acceptance is provisional: OH-AUTO2-105R is open because an
  existing DB-backed SBAIO probe was accidentally run against the repository's
  configured local development target. No cleanup or further DB operation is
  authorized until the target and impact are independently assessed.
- OH-AUTO2-105S — the unguarded SBAIO DB-backed probe now requires the existing
  disposable entry-point attestation before any application autoload/bootstrap;
  the isolated source-root inventory contains 29 governed DB entry points.
- OH-AUTO2-106 — the independent, non-database host-provider capability
  governance design audit was accepted by Planner. Its findings authorize the
  bounded OH-AUTO2-107 implementation; OH-AUTO2-105B remains blocked.
- OH-AUTO2-107 — the bounded Platform-owned `/me` provider admission
  foundation is implemented. Six navigation-only providers are admitted from
  version-controlled app manifests; unsupported operational callbacks fail
  closed before provider loading. No Core or database change was made.
- OH-AUTO2-108 — suppressed operational providers were inventoried against
  their actual callbacks and read-safety evidence. No operational provider met
  the complete admission contract, so none was restored; Material Management
  read safety remains proven but its composite summary callback lacks complete
  record-scope evidence. SBAIO sensitive providers remain suppressed.
- OH-AUTO2-109 — extracted and admitted one bounded Manufacturing Material
  Management aggregate summary provider. It uses existing module authority and
  `summaryReadOnly()` only; the composite callback, monitoring providers, and
  SBAIO sensitive providers remain suppressed.
- OH-AUTO2-110 — record-scope audit proved that the Material aggregate ignores
  restricted scope and must not be admitted on module authority alone.
- OH-AUTO2-111 — the unsafe Material aggregate hook and descriptor are removed
  from executable Manufacturing `/me` metadata. The provider source and
  `summaryReadOnly()` remain preserved but inactive; six navigation providers
  remain admitted.
- OH-AUTO2-112 — the read-only record-scope investigation is complete and
  classified **OPTION C — BLOCKED**. Existing part/machine query filters do not
  map consistently to all Material aggregate sources; no whole-domain authority
  was found. OH-AUTO2-111 containment remains in force.
- OH-AUTO2-113 — closed the blocked Material workstream administratively and
  selected the next executable milestone: a bounded Shell
  `OperatorSurfaceComposer` account-panel data extraction. No runtime change is
  included in this selection record.

After the release/update cycle is proven, resume the product roadmap focus from
`README.md`:

- worker-focused `/me` composition with current work first and queue next
- role-shaped execution surfaces for worker, leader, admin, read-only, and display modes
- computed-scope aware worker UI for machines, parts, work areas, and process areas
- display-safe read-only surfaces
- dynamic worker navigation from effective access
- task-to-action navigation for specific assigned work on `/u/*`
- exact selected-task identity and existing status actions on `/u/*`

## Deferred Product Work

The Hospitality App Foundation remains complete and validated
(`docs/architecture/hospitality-foundation-release-readiness.md`), and its
operator/workspace composition plan remains preserved at
`docs/active/hospitality-operator-composition-plan.md`. It is deferred product
work, not the current project execution milestone.

Before implementation, verify whether a more recent active task exists under `docs/active/` or the relevant Engineering Workspace `work.md`.

## Context Routing

Use this file only to find the right context. Do not expand it into a full PRD, memory log, or session transcript.

Read these files first for broad context:

- `README.md` for product vision, architecture summary, status, and roadmap
- `ARCHITECTURE.md` for terminology and architecture rules
- `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md` for the mandatory OS charter
- `docs/architecture/CORE-LOCK-POLICY.md` before touching Core
- `docs/architecture/APP-CONTRACT.md` and `docs/architecture/MODULE-CONTRACT.md` before app/module boundary work
- `docs/BACKLOG.md` for queued future work
- `docs/discussions/` for useful non-official discussion notes that have not graduated into architecture law or active implementation
- `docs/architecture/markdown-freshness-authority-audit.md` before any doc move/archive/marking work — it classifies markdown authority buckets and lists review-required stale candidates

For owner-specific changes, use:

- Shell: `apps/Shell/AGENTS.md`, `engineering/Shell/`
- Platform: `apps/Platform/AGENTS.md`, `engineering/Platform/`
- Studio: `apps/Studio/AGENTS.md`, `engineering/Studio/`
- Manufacturing: `apps/Manufacturing/AGENTS.md`, `engineering/Manufacturing/`
- SBAIO: `apps/SBAIO/AGENTS.md`, `engineering/SBAIO/`
- Plugins: `plugins/AGENTS.md`, `engineering/Plugin/`
- Packages: `packages/AGENTS.md`

## Documentation Rules

- `AGENTS.md` is for rules and startup routing.
- `docs/CURRENT.md` is for current project state and context routing.
- `docs/active/` is for live task briefs and handoffs.
- `docs/discussions/` is for useful planning notes that are not yet official decisions.
- `docs/BACKLOG.md` is for future work that is not yet active.
- `engineering/<owner>/work.md` is for owner-specific active status and evidence.
- `engineering/<owner>/decisions.md` is for durable owner decisions.
- `docs/architecture/` is for cross-owner architecture contracts.
- `docs/runtime/`, `audit/`, and `docs/migration-cleanup/` preserve historical and validation material.

Do not create a generic `Memory.md`. Put information where it belongs by meaning.

Discussion notes may preserve uncertain ideas, but they must say what is confirmed, what is tentative, and what would make the note official.

## Working Safety

- Keep changes scoped to the active objective.
- Do not reorganize large documentation trees during product implementation unless the task is explicitly documentation migration.
- Do not move or delete historical docs without a reviewed migration map.
- Record validation evidence in the relevant active task or owner `work.md`.
- Treat Core changes as locked unless a current task explicitly authorizes them and the Core lock gate is run.

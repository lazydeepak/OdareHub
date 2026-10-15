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
development has resumed on the Shell worker surface; OH-AUTO1-090 is the
current scope-aware operator Parts workspace milestone.

## Active Focus

Completed execution milestone:

- Release/update foundation — isolated installation, signed update,
  post-update verification, and recovery proof. OH-AUTO1-080 final disposable
  safety closure is complete; production apply remains blocked.

Current product milestone:

- OH-AUTO1-090 — scope the operator Parts list and detail selection to the
  authenticated user's effective Manufacturing part permissions, preserving
  list/detail consistency and preventing unauthorized Part360 schema access.

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

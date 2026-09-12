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

## Active Focus

Active task:

- `docs/active/hospitality-operator-composition-plan.md`

The Hospitality App Foundation is complete and validated
(`docs/architecture/hospitality-foundation-release-readiness.md`). The current active
work is planning the operator/workspace composition slice that composes Foundation
surfaces into `/u/{username}/*` via the existing app-contribution mechanism.

## Next Product Focus

The current roadmap focus from `README.md` is worker-perspective UI creation:

- worker-focused `/me` composition with current work first and queue next
- role-shaped execution surfaces for worker, leader, admin, read-only, and display modes
- computed-scope aware worker UI for machines, parts, work areas, and process areas
- display-safe read-only surfaces
- dynamic worker navigation from effective access

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

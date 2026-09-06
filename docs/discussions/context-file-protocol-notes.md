# Context File Protocol Notes

Status: Discussion note. Not an implementation task by itself.

Date captured: 2026-08-23

## Context

A referenced coding-preparation pattern suggested creating six files before starting agent-driven development:

- `prd.md`
- `architecture.md`
- `rules.md`
- `phases.md`
- `design.md`
- `memory.md`

The useful principle is to prepare structured context before coding. The risk for Susankhya OS is duplication and drift across many agents, owners, and long-running milestones.

## Confirmed Direction

Susankhya OS should keep a leaner context system:

- `AGENTS.md` for startup rules and routing
- `docs/CURRENT.md` for current project state and context routing
- `docs/active/<task>.md` for live task briefs and handoffs
- `docs/BACKLOG.md` for future confirmed work
- `engineering/<owner>/overview.md` for owner purpose and scope
- `engineering/<owner>/rules.md` for owner working rules
- `engineering/<owner>/work.md` for owner-specific current work and evidence
- `engineering/<owner>/decisions.md` for owner durable decisions
- `docs/architecture/` for cross-owner architecture contracts
- `docs/discussions/` for useful unofficial planning notes

## Important Decision

Do not create a generic `Memory.md`.

Reason: a generic memory file tends to mix discoveries, stale progress, past decisions, temporary notes, future ideas, bugs, and agent summaries. That becomes difficult for future agents to trust.

Instead, store information by meaning:

| Meaning | Location |
|---|---|
| current state | `docs/CURRENT.md` |
| live task | `docs/active/<task>.md` |
| durable decision | `engineering/<owner>/decisions.md` or `docs/architecture/` |
| architecture truth | `docs/architecture/` |
| future idea | `docs/BACKLOG.md` |
| unofficial discussion | `docs/discussions/` |
| completed history | `docs/runtime/`, `audit/`, or an appropriate archive |
| code history | Git |

## Tentative Notes

- A full PRD can exist for a major product or app, but agents should not need to read a huge PRD for every coding session.
- Design docs should exist when UI/system design needs its own artifact, not as mandatory boilerplate for every task.
- Phases should be represented through `docs/CURRENT.md`, `docs/BACKLOG.md`, active task briefs, and owner `work.md` rather than duplicated across several files.

## Open Questions

- Should major future apps such as Hospitality have their own `docs/discussions/<app>-prd-notes.md` before becoming active tasks?
- Should completed discussion notes be moved to a formal archive, or left in place with status updated?

## Graduation Criteria

This discussion becomes official when:

- `AGENTS.md` and `docs/CURRENT.md` route future agents through this protocol
- active task briefs follow the same structure
- any additional rules are moved into official architecture or owner rule files


# /app/AGENTS.md

> This directory contains core/platform code.
> Core is locked and must not be modified without explicit approval.
> If this file is violated, the implementation is incorrect.

---

## Scope

Core includes:
- kernel / bootstrap
- routing / dispatch
- DB base layer
- auth / session / ACL
- renderer / layout engine
- hook / event / extension contracts
- app/plugin loader

---

## Hard Rules

Do NOT add:
- business logic (Manufacturing, SBAIO, etc.)
- app-specific routes
- app-specific UI (menus, widgets, dashboards)
- shortcuts or patches to “make features work”
- experience composition policy or Studio coupling to core

---

## Required Behavior

Before modifying anything in `/app`:

1. Attempt implementation in app/module/plugin first
2. If not possible, identify missing platform capability
3. Stop and request approval before changing core

---

## Allowed Work (only if appropriate)

- platform-level capability improvements
- generic routing/ACL/rendering improvements
- security fixes
- bug fixes in core behavior

Any generic ACL/rendering improvement related to Workspace Profile, Studio, `/u/*`, `/admin/*`, `/displays/*`, `/me`, or `ResolvedExperience` must also follow `../docs/experience-composition-architecture-plan.md`. Core remains locked unless explicitly approved.

---

## Prohibited Patterns

- hardcoding app names in core
- adding one-off exceptions
- bypassing ACL checks
- introducing duplicate or fallback logic

---

## Required References

You must also follow:
- `../AGENTS.md`
- `../docs/experience-composition-architecture-plan.md`
- `../docs/architecture/CORE-LOCK-POLICY.md`
- `../docs/architecture/SECURITY-POLICY.md`

---

## Completion Requirement

If `/app` is modified, report:
- why core change was necessary
- why app/plugin solution was insufficient
- exact files changed
- risk/impact assessment

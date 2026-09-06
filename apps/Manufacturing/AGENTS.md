# /apps/Manufacturing/AGENTS.md

> This directory owns Manufacturing business functionality.
> Keep Manufacturing logic in the app/module boundary, not in core.

---

## Scope

Manufacturing owns:
- demand and coverage workflows
- production planning and execution
- QC, assembly, and dispatch operations
- manufacturing dashboards, queues, and workboards
- Manufacturing capability catalogs and runtime meaning for manufacturing admin/operator/display contributions

---

## Route Rules

- Canonical business routes must stay under `/apps/manufacturing/...`.
- App routes own cross-stage orchestration and composition.
- Module feature routes own focused module pages while using the app URL namespace.
- Compatibility aliases may exist temporarily only for migration/bookmark continuity.
- Compatibility aliases must never be the primary route in new UI links.

---

## Boundary Rules

- Do NOT move Manufacturing business logic into core.
- Do NOT add Manufacturing UI directly into shell/core.
- Do NOT create duplicate route surfaces for the same feature.
- Do NOT create runtime dependencies on Susankhya Studio; Manufacturing must run normally when `/apps/Studio` is absent or disabled.
- Follow `../../docs/experience-composition-architecture-plan.md` when Manufacturing capabilities feed ACL, Workspace Profile, operator, display, admin, or Studio composition workflows.
- Studio may help create or modify Manufacturing artifacts, but ownership remains with Manufacturing.

---

## Required References

Follow these with priority:
- `../../AGENTS.md`
- `../../docs/experience-composition-architecture-plan.md`
- `../../docs/architecture/MODULE-CONTRACT.md`
- `../../docs/architecture/MODULE-COMPLETENESS-MATRIX.md`
- `../../docs/architecture/ROUTING-STANDARD.md`
- `../../docs/architecture/SECURITY-POLICY.md`
- `../../docs/architecture/CLEANUP-AND-LIFECYCLE.md`

---

## Completion Requirement

Report:
- routes updated/removed
- compatibility aliases retained (if any)
- cleanup performed
- whether core was touched (must be NO unless approved)

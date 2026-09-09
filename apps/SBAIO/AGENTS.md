# /apps/SBAIO/AGENTS.md

> This directory owns SBAIO business functionality.
> Keep SBAIO logic in app/modules, not in core or unrelated plugins.

---

## Scope

SBAIO owns:
- timecards and attendance workflows
- payroll-related business operations
- SBAIO dashboards and user flows
- SBAIO capability catalogs and runtime meaning for SBAIO admin/operator/display contributions

---

## Rules

- Use app-owned routes under `/apps/sbaio/...` for canonical SBAIO surfaces.
- Do NOT move SBAIO business logic into core.
- Do NOT duplicate SBAIO routes/surfaces outside app ownership.
- Do NOT create runtime dependencies on OdareHub Studio; SBAIO must run normally when `/apps/Studio` is absent or disabled.
- Follow `../../docs/experience-composition-architecture-plan.md` when SBAIO capabilities feed ACL, Workspace Profile, operator, display, admin, or Studio composition workflows.
- Studio may help create or modify SBAIO artifacts, but ownership remains with SBAIO.

---

## Required References

Follow these with priority:
- `../../AGENTS.md`
- `../../docs/experience-composition-architecture-plan.md`
- `../../docs/architecture/ROUTING-STANDARD.md`
- `../../docs/architecture/SECURITY-POLICY.md`
- `../../docs/architecture/CLEANUP-AND-LIFECYCLE.md`

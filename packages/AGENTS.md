# /packages/AGENTS.md

> Packages own install/export/import/release lifecycle workflows.
> Package logic must remain generic and architecture-safe.

---

## Scope

Packages may handle:
- package import/export/release pipelines
- lifecycle metadata and validation
- package-level compatibility checks

---

## Rules

- Do NOT add business-domain app logic in packages.
- Keep package behavior generic and reusable.
- Ensure package operations do not bypass security/policy checks.
- Remove package artifacts cleanly on lifecycle operations.
- Package import/export of app/module, Workspace Profile, ACL, Studio, or experience artifacts must preserve the ownership model in `../docs/experience-composition-architecture-plan.md`.

---

## Required References

Follow these with priority:
- `../AGENTS.md`
- `../docs/experience-composition-architecture-plan.md`
- `../docs/architecture/CLEANUP-AND-LIFECYCLE.md`
- `../docs/architecture/SECURITY-POLICY.md`

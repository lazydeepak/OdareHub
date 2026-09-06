# /plugins/AGENTS.md

> Plugins are cross-cutting extension mechanisms.
> Plugins must not become hidden business apps.

---

## Scope

Plugins may provide:
- shared utilities/integrations
- extension hooks/providers
- platform-level tooling with authorization

---

## Rules

- Do NOT place business-domain workflows in plugins.
- Do NOT create app-owned business routes in plugin scope.
- Enforce server-side authorization and input validation on plugin routes.
- Keep plugin behavior removable without orphan surfaces.
- Do NOT add new Susankhya Studio builder/editor logic to plugins. Studio belongs to the optional `/apps/Studio` System App; plugin-hosted GUI Studio code is legacy or temporary bridge code only.
- Do NOT add new independent ACL experience-shaping catalogs in plugins. Follow `../docs/experience-composition-architecture-plan.md` for ACL, Workspace Profile, Studio, and runtime experience composition work.

---

## Required References

Follow these with priority:
- `../AGENTS.md`
- `../docs/experience-composition-architecture-plan.md`
- `../docs/architecture/APP-CONTRACT.md`
- `../docs/architecture/SECURITY-POLICY.md`
- `../docs/architecture/CLEANUP-AND-LIFECYCLE.md`

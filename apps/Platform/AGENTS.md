# /apps/Platform/AGENTS.md

> This directory owns Platform app governance features.
> Platform is system-level capability, not business-domain workflow.

---

## Scope

Platform app owns:
- organization and platform settings
- access control and user-control operations
- platform governance dashboards/tools
- conditional Studio entry points/diagnostics only when Studio is installed/enabled

Platform governance may assign roles, permissions, profiles, and diagnostic entry points. It must not become the owner of app/module runtime experience catalogs.

---

## Rules

- Keep Platform features app-owned under `/apps/platform/...` when applicable.
- Do NOT implement platform governance logic in core without explicit approval.
- Do NOT hardcode platform UI into shell/core.
- Keep server-side authorization on all governance surfaces.
- Do NOT add new OdareHub Studio editor/builder services, views, routes, or composition logic to Platform.
- Platform may conditionally link to `/apps/studio/...` or show Studio diagnostics only when the Studio System App is installed/enabled.
- Existing Platform Studio/GUI Studio code is migration debt during extraction; treat it as legacy/current implementation to move or bridge into `/apps/Studio`, not as the desired ownership model.
- Follow `../../docs/experience-composition-architecture-plan.md` before changing ACL, Workspace Profile, experience layout, Studio diagnostics links, or `ResolvedExperience` diagnostics.
- ACL governance surfaces must not add new independent presentation/layout catalogs. Experience editing should move toward Studio-owned tooling and owner-owned artifacts.

---

## Required References

Follow these with priority:
- `../../AGENTS.md`
- `../../docs/experience-composition-architecture-plan.md`
- `../../docs/architecture/SECURITY-POLICY.md`
- `../../docs/architecture/ROUTING-STANDARD.md`
- `../../docs/architecture/CLEANUP-AND-LIFECYCLE.md`

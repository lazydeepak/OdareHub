# /plugins/Base/AGENTS.md

> Base is shared plugin infrastructure and legacy admin/runtime support.
> Base must not become the owner of Susankhya Studio.

---

## Scope

Base may own:
- shared plugin utilities
- compatibility surfaces explicitly retained during migrations
- legacy admin/runtime support that remains removable with the plugin

Base must not become the canonical owner of ACL experience composition. Access Control experience editing in Base is transitional profile assignment plus per-user override/summary until Studio-owned composition tooling and `ResolvedExperience` diagnostics take over.

---

## Studio Boundary

- Do NOT add new GUI Studio, builder, editor, analyzer, diff, apply, or no-code composition logic to Base.
- Existing Studio-related Base views/routes are migration debt unless explicitly retained as temporary compatibility bridges.
- Canonical Studio ownership belongs to the optional `/apps/Studio` System App with routes under `/apps/studio/...`.
- Any Base bridge to Studio must remain conditional on Studio being installed/enabled and must fail gracefully when Studio is absent.
- Base must not create hidden runtime dependencies from Core, Platform, or business apps back into Studio.

## Experience Composition Boundary

- Follow `../../docs/experience-composition-architecture-plan.md` before changing Access Control, Workspace Profile assignment, `/ops/access-control/detail`, or experience layout fields.
- ACL owns hard authorization and server-side allow/deny. Do not add new independent layout/presentation catalogs to ACL.
- Existing fields such as `me_dashboard_blocks`, `me_plugin_cards`, `display_surfaces`, and `operator_views` are transitional compatibility fields.
- Per-user experience edits must not grant permissions or expose capabilities denied by ACL.
- New composer/editor workflows belong in Studio, not Base or `/ops` except as temporary bridges/links.

---

## Required References

Follow these with priority:
- `../../AGENTS.md`
- `../AGENTS.md`
- `../../docs/experience-composition-architecture-plan.md`
- `../../docs/architecture/APP-CONTRACT.md`
- `../../docs/architecture/CLEANUP-AND-LIFECYCLE.md`

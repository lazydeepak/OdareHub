# /apps/Hospitality/modules/AGENTS.md

> This directory owns Hospitality module surfaces.
> Every module here is owned solely by the Hospitality app (`owner_app: hospitality`).

---

## Scope

Apply these rules to all existing and new Hospitality modules under this directory:

- `Rooms`, `Guests`, `Reservations`, `FrontDesk`, `Housekeeping` (v1 per
  `../../../docs/active/hospitality-app-foundation.md`)
- Future modules must be declared in `../manifest.json` (`modules[]`) and carry a
  contract-compliant `plugin.json`.

---

## Rules

- One parent app only. A module registers through the Hospitality app, never as a
  top-level surface.
- No shared Billing, Items, Inventory, Parties, Accounting, or Procurement dependencies.
  Local stand-ins (e.g. folio inside FrontDesk) must stay visibly local; label any future
  extension-shaped module with explicit ownership metadata instead of creating an
  `extensions/` directory (no loader support exists).
- Do not copy legacy `"suite"` metadata from older module manifests into new modules.
  Suite wording stays product/composition language only.
- Declare capabilities honestly: do not declare routes/views/controllers before they exist;
  missing capabilities must be intentional. `controllers` implies `routes`; `forms`
  implies `views`.
- All user-facing text goes through locale files under each module's `Resources/lang/`.
- Follow `../../../docs/experience-composition-architecture-plan.md` when module
  capabilities feed ACL, Workspace Profile, operator, display, admin, or Studio
  composition workflows.

---

## Required References

- `../../../AGENTS.md`
- `../../AGENTS.md`
- `../../../docs/architecture/MODULE-CONTRACT.md`
- `../../../docs/architecture/business-app-module-ownership-contract.md`

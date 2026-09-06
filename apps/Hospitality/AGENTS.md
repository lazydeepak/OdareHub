# /apps/Hospitality/AGENTS.md

> This directory owns Hospitality business functionality.
> Keep Hospitality logic in this app and its modules, not in core, Shell, Platform, or other apps.

---

## Scope

Hospitality owns (per `docs/active/hospitality-app-foundation.md`):

- rooms, guests, reservations
- front desk check-in/check-out flow, including the v1 local folio/charges records inside FrontDesk
- housekeeping status
- Hospitality capability catalogs and runtime meaning for admin/operator/display contributions

Product/composition name: **Hospitality Suite**. Runtime truth: this app (`hospitality`).
There is no suites layer, registry, or loader.

---

## Rules

- Use app-owned routes under `/apps/hospitality/...` only. No compatibility aliases.
- Do NOT move Hospitality logic into core.
- Do NOT depend on shared Billing, Items, Inventory, Parties, Accounting, or `apps/Procurement`.
  Local stand-ins must stay visibly local and must not masquerade as shared apps.
- Do NOT add a real `extensions/` directory. If an extension-shaped module ever appears,
  it is a normal module under `modules/` with explicit ownership metadata.
- Do NOT create runtime dependencies on Susankhya Studio; Hospitality must run normally when Studio is absent or disabled.
- All user-facing text goes through locale files under `Resources/lang/{en,ja,ne}.php`.
- Follow `../../docs/experience-composition-architecture-plan.md` when Hospitality capabilities feed ACL, Workspace Profile, operator, display, admin, or Studio composition workflows.

---

## Required References

Follow these with priority:

- `../../AGENTS.md`
- `../../docs/CURRENT.md`
- `../../docs/active/hospitality-app-foundation.md`
- `../../docs/architecture/hospitality-readiness-audit.md`
- `../../docs/architecture/MODULE-CONTRACT.md`
- `../../docs/architecture/ROUTING-STANDARD.md`

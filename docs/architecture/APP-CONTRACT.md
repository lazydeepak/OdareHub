# APP CONTRACT

Apps must declare features via structured contracts.

Apps compose business capabilities. Modules own focused feature surfaces. Platform owns governance and shared infrastructure. Core renders and runs only.

---

## Structure

App contracts may declare:

- routes
- navigation
- widgets
- permissions
- quick actions
- dashboards
- cross-module reports
- module dependencies

---

## Rules

- No manual UI injection
- One feature = one source
- No duplicate definitions
- No app-owned duplicate of a module-owned form, queue, report, or dashboard
- All business routes remain under `/apps/{app}/...`
- Module lifecycle must be respected when composing app surfaces

---

## Ownership

Apps own:

- business app composition
- cross-module workflows
- cross-stage dashboards
- app navigation and landing surfaces
- app-level route namespace
- app-level permissions that govern orchestration

Modules own:

- focused business features
- module routes under the app namespace
- module views and forms
- module schema and migrations
- module widgets
- module reports and exports
- module permissions
- module lifecycle hooks

Platform owns:

- lifecycle framework
- report/export infrastructure
- permission framework
- audit framework
- localization infrastructure
- shared governance services

Core renders only.

---

## Required References

- `docs/architecture/MODULE-CONTRACT.md`
- `docs/architecture/MODULE-COMPLETENESS-MATRIX.md`
- `docs/architecture/ROUTING-STANDARD.md`
- `docs/architecture/CLEANUP-AND-LIFECYCLE.md`

# ROUTING STANDARD

## Structure

Core:
- /login
- /setup
- /me
- /ops/*

Apps:
- /apps/{app}/...

Plugins:
- /tools/*
- /system/*
- /auth/*

---

## Rules

- All business routes must be under `/apps/{app}`
- No `/modules/...` URLs
- No legacy routes like `/manufacturing/...` as canonical primary routes
- One canonical route per feature
- Compatibility aliases are allowed only temporarily for migration and must not be used as primary links in new UI
- App-level routes compose cross-module workflows
- Module-owned routes still use the app URL namespace
- Active module lifecycle must control module-owned route availability

---

## Ownership

Apps own:

- app landing pages
- cross-module dashboards
- cross-stage workflows
- orchestration routes

Modules own:

- canonical feature pages
- module forms
- module queues
- module detail pages
- module reports and exports

Example:

```text
/apps/manufacturing/processing-operation  app-owned orchestration
/apps/manufacturing/assembly-plans        AssemblyPlans module-owned
/apps/manufacturing/assembly-entries      AssemblyEntries module-owned
/apps/manufacturing/assembly-queue        AssemblyEntries module-owned if queue belongs to execution
```

---

## Violations

- duplicate endpoints
- mixed route styles
- orphan routes
- module feature route registered while module is inactive
- app route duplicating module-owned business UI

Must be fixed immediately.

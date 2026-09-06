# ERP App Studio Module Manifest Contract

Schema: `studio.module-manifest.v1`

Status: draft contract only

Defines a future app-owned module. It does not create module code or migrations.

Required sections:

- `module`: app key, module key, display name, module type, route base intent.
- `contracts`: route, view, localization, health, and permission intent.
- `data_policy`: whether the module expects readonly, workflow, or mutation surfaces.
- `audit`: draft metadata only.

Module type examples:

- `crud`
- `dashboard`
- `queue_workflow`
- `reporting`

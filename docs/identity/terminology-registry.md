# OdareHub Terminology Registry

Status: Identity Stabilization baseline

This registry controls user-facing platform language after the ERP Engine to OdareHub migration. It does not rename technical routes, folders, namespaces, database keys, or compatibility aliases.

## Canonical Terms

| Term | Use For | Avoid |
|---|---|---|
| OdareHub | Platform product name and platform-wide runtime identity | ERP Engine |
| ERP App Studio | Governed app authoring, templates, manifests, artifacts, and publish preparation | GUI Studio |
| Manufacturing | Business app for production, QC, materials, assembly, and dispatch | Manufacturing portal as a product name |
| SBAIO | Business app for small-business workforce and operational records | Expanded ad hoc names in navigation |
| Platform Admin | Governance role and governance surface | System admin when the role is platform governance |
| Workspace | User or app working surface | Portal for active product surfaces |
| App | Installable top-level capability | Suite when describing runtime navigation |
| Module | App-owned internal feature area | Plugin for app-internal features |
| Surface | Route-addressed user experience or wrapper-owned composition area | Page when describing architecture contracts |
| View | Concrete rendered template or screen fragment | Surface when referring to a template file |

## Navigation Language

Use Workspace for durable working destinations: Operator Workspace, Manufacturing Workspace, SBAIO Workspace.

Use Dashboard only for metric-first summaries and legacy route names that remain visible for backward compatibility.

Use Operations for navigation groups and operational work areas.

Use Actions for buttons, forms, and executable controls.

Use Analytics for analysis surfaces. Use Reports for exportable, printable, or scheduled outputs.

## Intentional Legacy References

The following may remain until a later technical migration:

- Route segments such as `/dashboard`, `/ops/*`, and `/apps/*`.
- Translation keys and feature keys containing `dashboard`, `portal`, or `gui-studio`.
- Environment variable prefixes such as `ERP_DB_*`, `ERP_MAIL_*`, and `ERP_APP_URL`.
- Historical audit, release, and compliance documents that describe previous phases.

## Localization Contract

New UI strings must continue to use `t()`, `$this->tr()`, or equivalent helpers. Shell-level presentation may normalize legacy exact terms at render time, but locale files remain the source of translated labels until a later approved core-locale pass.

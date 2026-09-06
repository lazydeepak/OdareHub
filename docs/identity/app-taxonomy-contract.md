# App Taxonomy Contract

Status: Documentation-only preparation

This contract establishes official hierarchy names without restructuring folders.

## Runtime Hierarchy

1. System Apps

System Apps provide governance, setup, identity, security, integration, and platform administration capabilities. Platform Admin surfaces belong here.

2. Business Apps

Business Apps provide domain work. Current examples are Manufacturing and SBAIO. Business routes stay under `/apps/{app}` and operator-facing work stays confined to `/u/{username}/*`.

Business Apps own domain capability meaning. They should explicitly declare app key, type, owner, owned modules, routes, views, styles/assets, contribution points, permissions, allowed Shell surfaces, dependencies, and must-not-own boundaries. The minimal baseline is documented in `docs/architecture/business-app-module-ownership-contract.md`.

3. Extensions and Plugins

Extensions and Plugins provide cross-cutting lifecycle or integration capabilities. They must stay removable and must not become hidden business apps.

## Architectural Vocabulary

- App: top-level installable capability.
- Module: app-owned internal feature.
- Plugin: cross-cutting extension mechanism.
- Package: install, export, import, or release lifecycle mechanism.
- Surface: route-addressed composition area.
- View: concrete render template.

Ownership is decided by runtime meaning, not by legacy file names alone. A `plugin.json` file, `Plugins\...` namespace, or `package_type` value may be compatibility/packaging vocabulary during migration.

Use these distinctions when boundaries are blurry:

- Surface owner: owns the route, view, form, report, widget, or workflow meaning.
- Reusable capability owner: owns a reusable capability exposed through contracts.
- Technical engine: owns generic mechanics such as PDF conversion, QR encoding, report execution, package zip handling, or rendering infrastructure.
- Package: transports or validates artifacts; it does not become the owner of the artifact's runtime meaning.

## Migration Boundary

No folder, namespace, route, database, or manifest key rename is part of this phase. Future migrations must preserve compatibility aliases and update contracts before code movement.

For current QR/PDF boundary examples, see `docs/architecture/system-app-plugin-package-boundaries.md`.

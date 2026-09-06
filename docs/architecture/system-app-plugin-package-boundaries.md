# System App, Plugin, and Package Boundaries

Status: documentation-only checkpoint. Do not move runtime code from this document alone.

This note clarifies the current boundary confusion between System Apps, Plugins, and Packages. QR and PDF are useful examples because both cross app/module boundaries and both are easy to misclassify as "plugins" just because of legacy manifests, namespaces, or packaging code.

## Vocabulary

- **Surface owner**: owns the user-facing route, view, form, report, widget, or workflow meaning.
- **Reusable capability owner**: owns a reusable domain-adjacent capability exposed to other owners through explicit contracts.
- **Technical engine**: owns generic rendering, conversion, validation, packaging, transport, or infrastructure mechanics. It must not own business meaning.
- **Package**: lifecycle artifact or workflow for install, export, import, release, backup, restore, and compatibility validation. A package is not the owner of the feature it transports.
- **Plugin**: removable cross-cutting extension mechanism. Plugins must not become hidden apps or hidden business modules.

## Current Example: QR

Current files and declarations:

- `apps/Platform/modules/QRCode/plugin.json`
- `apps/Platform/modules/QRCode/routes.php`
- `apps/Platform/modules/QRCode/Controllers/QRCodeController.php`
- `apps/Platform/modules/QRCode/Views/*`
- `apps/Platform/modules/QRCode/migrations/001_qr_stock_updates.sql`

Current metadata:

- `owner_app`: `platform`
- `package_type`: `module`
- `module_type`: `integration`
- `type`: `engine`
- `suite`: `core`
- routes currently live under `/qr/*`

Current owner reading:

- Current surface owner for `/qr/product/label`, `/qr/product/scan`, `/qr/stock`, `/qr/report`, and `/qr/export` is the Platform-owned QRCode module.
- Current reusable capability owner is also the Platform-owned QRCode module: it provides QR label/scan integration and a QR stock update utility.
- QR currently consumes Manufacturing-domain tables and routes such as products, production entries, ledger, QC plans, and coverage recalculation. That is cross-app coupling debt and should be treated as integration debt, not as proof that QR owns Manufacturing.
- Manufacturing remains the owner of manufacturing product, production, ledger, QC, and coverage domain meaning.
- The `Plugins\QRCode` namespace and `plugin.json` filename are compatibility/packaging vocabulary. They do not make QR a cross-cutting Plugin in the target architecture.

Recommended QR boundary:

- QR code generation/encoding and scan-token parsing should be a reusable technical/integration capability.
- Product labels, stock update screens, scan landing choices, ledger posting, and production/QC action links should belong to the app/module whose domain data and workflow they operate on.
- A Platform-owned QR integration module may provide generic QR registration, scan dispatch, diagnostics, and shared QR utilities.
- App/module owners should contribute QR-enabled surfaces through explicit contracts instead of QR hardcoding business action links.
- Do not move QR code yet. First document capability contracts and identify which QR surfaces are generic integration versus Manufacturing-specific workflow.

## Current Example: PDF

Current files and usage:

- `app/Core/PdfService.php`
- `apps/Manufacturing/modules/ProductionPlans/Controllers/ProductionPlansController.php`
- `apps/Manufacturing/modules/ProductionPlans/Views/pdf_*.php`
- `apps/SBAIO/modules/Timecards/Controllers/TimecardsController.php`
- `apps/SBAIO/modules/Timecards/Views/pdf.php`

Current owner reading:

- The current technical engine is `App\Core\PdfService`, which wraps Dompdf availability, HTML rendering, paper options, and streaming headers.
- The surface owner for a Production Plans PDF is the Manufacturing `ProductionPlans` module because it owns the route, data query, template, filename semantics, audit target, and user workflow.
- The surface owner for a Timecards PDF is the SBAIO `Timecards` module for the same reason.
- Export audit/history services are Platform/System Tools-adjacent infrastructure; they do not own the document's business meaning.

Recommended PDF boundary:

- PDF infrastructure belongs to a technical engine or Platform export/report infrastructure.
- PDF routes, templates, permissions, labels, data access, filenames, and audit target keys belong to the app/module that owns the source data.
- A PDF engine must not contain Manufacturing, SBAIO, Payroll, or other business-specific layout or workflow rules.
- Module PDF declarations should live in module manifests or report contracts when promoted.
- Do not move `PdfService` from Core without explicit Core approval. The current documentation only clarifies target ownership.

## Package Boundary

Packages own lifecycle transport, not runtime meaning.

Package workflows may:

- export app/module bundles
- import packages
- validate manifests
- preserve compatibility metadata
- run lifecycle checks
- prepare release artifacts
- record package history

Package workflows must not:

- become the owner of QR, PDF, Manufacturing, SBAIO, Payroll, Shell, Platform, or Studio runtime behavior
- bypass Core registry, migrations, audit, permissions, or owner manifests
- turn a transported module into a plugin or system app merely because it is zipped
- create alternate source-of-truth metadata that conflicts with app/module manifests

`package_type` should describe lifecycle packaging shape. It is not sufficient by itself to decide runtime ownership.

## Boundary Rules

1. The smallest business owner wins.
   - If a route/view/form/report/PDF acts on one module's data, that module owns the surface.
   - If a route/view/report composes multiple modules in one business app, the app owns the surface.
   - If a surface governs users, profiles, permissions, diagnostics, setup, or operations, Platform owns the surface.

2. Technical engines own mechanics only.
   - QR encoding, PDF conversion, package zip handling, export conversion, report execution, and rendering engines must stay business-agnostic.
   - Engines may expose APIs/contracts; they must not hardcode app/module workflow choices.

3. Plugins are extension mechanisms, not hidden apps.
   - A cross-cutting plugin may provide hooks or integration behavior.
   - A plugin must remain removable and must not own business routes that should belong to an app/module.
   - Legacy `Plugins\...` namespaces in app modules are compatibility markers until migration, not target ownership proof.

4. Packages are lifecycle transport.
   - Package metadata can describe what is being transported.
   - The transported artifact keeps its owner: System App, Business App, Module, Plugin, Studio artifact, or Theme.

5. Manifests/contracts must carry ownership intent.
   - App/module manifests should distinguish `surface_owner`, `capability_owner`, `engine_dependency`, and `package_type` when a capability crosses boundaries.
   - Compatibility fields can remain during migration, but they must not become a hidden duplicate source of truth.

## Recommended Next Safe Work

Do not move QR, PDF, plugin namespaces, package folders, or route families yet.

Read-only ownership diagnostics are now available:

```bash
scripts/architecture/check_capability_ownership_boundaries.sh
```

This diagnostic:

- verifies QR is still declared as `owner_app=platform` and `package_type=module`
- reports current QR business-app references and legacy `Plugins\QRCode` namespace usage as warnings
- checks that `App\Core\PdfService` stays free of app/module-specific semantics
- verifies PDF templates/reports live under app/module `Views` ownership paths
- checks dompdf usage stays behind `PdfService` or another approved app-agnostic technical renderer
- checks that `packages/` remains lifecycle transport rather than feature runtime source

Capability ownership diagnostics are read-only and scan-scope guarded. The gate must keep explicit coverage for Platform QR metadata, business-app QR consumers, PDF technical engine boundaries, app/module PDF templates, plugin/package vocabulary, package transport directories, and diff-only runtime regression scans.

Diagnostic contract:

- QR remains a Platform-owned integration capability until an approved owner migration.
- Legacy `Plugins\QRCode` namespace and plugin vocabulary are compatibility debt, not proof of plugin ownership.
- QR must not own Manufacturing business meaning.
- Manufacturing QR references are consumer/integration debt warnings.
- PDF routes, templates, filenames, permissions, and report meaning remain app/module-owned.
- PDF rendering engines must remain app-agnostic.
- Packages remain lifecycle transport, not runtime feature owners.

Warnings identify documented debt. They are not permission to move QR, PDF, package, plugin, or route code.

Next safe steps remain documentation and read-only diagnostics:

- add optional manifest contract fields for cross-boundary capabilities
- inventory QR routes as generic integration versus Manufacturing-specific workflow surfaces
- inventory PDF exports by source module and technical engine dependency
- expand read-only checks that report ambiguous `type=engine`, `suite=core`, `owner_app=platform`, and `package_type=module/plugin` combinations without migrating runtime code

Any runtime migration should come later, with explicit owner approval and compatibility plans.

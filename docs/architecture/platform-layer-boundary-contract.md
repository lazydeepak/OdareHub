# Platform Layer Boundary Contract

Status: Architecture contract baseline. Docs-only. No runtime behavior. No file moves. No PHP or app/module changes.

This document defines the current repository tree ownership boundaries and locks the future placement of Report Designer and report platform assets.

## 1. `app/`

`app/` is legacy runtime and older framework service territory.

- Contains core runtime helpers, the historical application bootstrap, old framework services, and compatibility entrypoints.
- Owns legacy runtime engines, authentication bootstrap, and older Core service abstractions.
- Does not own new business features, apps, or report surfaces unless explicitly approved by architecture governance.
- New business feature ownership should not be introduced here as a default placement.

## 2. `apps/`

`apps/` is the installable application surface layer.

- Hosts installable application surfaces and business apps.
- Contains business apps such as `apps/Manufacturing/`, `apps/SBAIO/`, `apps/Procurement/`.
- Contains system-level apps such as `apps/Shell/`, `apps/Studio/`, and `apps/Platform/`.
- Studio tools live under `apps/Studio/Tools/`.
- App surfaces are the first-class runtime entrypoints for end-user features, governance tooling, and business workflows.

## 3. `apps/Platform/`

`apps/Platform/` is the Platform application surface layer.

- Hosts platform application modules such as `Organization`, `CompanySetup`, `QRCode`, and other Platform app features.
- Is a Platform app surface, not the same as the reusable `platform/` engine layer.
- Owns Platform UI surfaces, app-level routes, views, and governance workflows delivered through the Platform app.
- Does not own reusable platform engines or capability implementations.

## 4. `platform/`

`platform/` is the reusable platform capability engine layer.

- Hosts reusable platform engines and capability libraries.
- Current examples include `platform/Labels/` and `platform/Style/`.
- Future `Reports` engine belongs here as `platform/Reports/`.
- This layer is for shared runtime capability, not application surface rendering.

## 5. `plugins/`

`plugins/` contains optional extension packages.

- Hosts framework extension packages and optional plugin bundles.
- Can add optional features, hooks, adapters, or integration points.
- Is not the primary place for core business app or platform app ownership.

## 6. `resources/`

`resources/` is global source resource territory.

- Hosts global source resources such as theme sources, shared token files, and design-time assets.
- Contains source material, not compiled runtime output.
- Compiled web assets should not be authored here.
- Source files here are inputs to build and runtime publish steps.

## 7. `public/`

`public/` is served/compiled web asset territory only.

- Hosts static web assets that the web server serves directly.
- Contains compiled CSS, JS, images, and other published asset outputs.
- Does not own source editing or runtime business logic.
- No source assets or platform engine code belong here.

## 8. `storage/`

`storage/` is mutable runtime state.

- Hosts runtime generated state, snapshots, logs, caches, user-generated content, and backups.
- Is not source code or compiled asset source.
- Contains data that may change over time and must remain separate from repository-owned source assets.

## 9. Report Architecture Placement

The Report architecture is split between Studio tooling and the Platform engine.

- Report Designer UI/tool remains under:
  - `apps/Studio/Tools/ReportDesigner/`
- Future report platform engine belongs under:
  - `platform/Reports/`
- System-owned report definitions will later belong under:
  - `platform/Reports/Definitions/`
- Business apps and modules will later expose report sources dynamically from their own owner-owned resource folders such as:
  - `Resources/report-sources/`

## 10. Important Report Designer Rule

Report Designer must not hardcode business app or module identities.

- Must not hardcode `Manufacturing`, `Products`, `Payroll`, `Materials`, or any other business app/module.
- Must discover report sources dynamically from provider interfaces.
- Must remain a generic Studio tool, not a business-app-specific authoring surface.
- Must later consume report source metadata from providers rather than fixed app paths.

## 11. Validation

This is a docs-only contract change.

- No PHP/runtime changes.
- No file moves.
- No new code or behavior is introduced.
- The contract fixes the intended owner and placement boundaries for future Report Designer and report runtime work.

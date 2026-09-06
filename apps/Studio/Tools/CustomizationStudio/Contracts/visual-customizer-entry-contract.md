# Visual Customizer Entry Contract

Status: disabled preview entry available.

This contract defines the Visual Customizer entry surface for Customization Studio. The route and Studio home card may expose disabled preview skeleton pages only. It does not enable editing, Shell connection, save action, apply action, registry write, or runtime consumption.

## Planned Entry

- Entry label: Visual Customizer
- Owner: Studio / Customization Studio
- Route name: `studio.customization_studio.visual_customizer`
- Route path: `/apps/studio/tools/customization-studio/visual-customizer`
- Controller: `Apps\Studio\Tools\CustomizationStudio\Controllers\VisualCustomizerController`
- View: `apps/Studio/Tools/CustomizationStudio/Views/visual-customizer.php`
- Current state: disabled/read-only preview skeleton

## Planned Menu Location

The future menu entry may appear only inside the Studio-owned tool area:

- Primary location: Studio home tool grid, grouped under Customization Studio.
- Secondary location: Customization Studio subtool list, after Theme Manager and before Component Style Editor.

The entry must not appear in Shell navigation, Platform navigation, business app navigation, operator navigation, display navigation, or public runtime menus.

## Required Permission

The future entry must require a Studio-owned governance permission:

- Planned permission key: `studio.customization_studio.visual_customizer.view`
- Permission intent: read-only access to the disabled Visual Customizer preview skeleton.
- Minimum authority gate: `platform_admin`

This permission must not grant style approval, runtime activation, draft saving, registry writing, Shell consumption, or app/module CSS ownership.

## Disabled Read-Only State

The first exposed route must remain disabled/read-only:

- Controls disabled by default.
- No form submission.
- No AJAX.
- No draft save.
- No apply workflow.
- No registry write.
- No active style loading.
- No Shell draft reading.
- No public asset generation.
- No runtime style consumption.

The page must continue to show: `Preview skeleton only - not connected to runtime.`

## Lifecycle Gate

The future entry may be visible only when all of these are true:

- Studio app is installed and enabled.
- Customization Studio tool area is enabled.
- Visual Customizer subtool is enabled.
- Current user satisfies the Studio governance permission and authority gate.

If any lifecycle gate fails, the route and menu entry must behave as unavailable. Failure must not fall back to Shell, Platform, ThemeTool, public assets, or any runtime style truth.

## Source Of Truth Boundaries

- Customization Studio owns visual editing workflow planning, previews, inspectors, diffs, apply requests, provenance, and rollback metadata.
- Shell owns style sockets and future runtime consumption.
- Platform/System owns approved active/default style registry truth.
- Apps/modules own scoped CSS and consume shared sockets.
- Organization owns brand identity.
- `public/assets` is generated delivery output only.

## Forbidden In Entry Slice

Future implementation slices must not use this contract to justify:

- Adding the active route before the disabled route slice.
- Adding the menu entry before lifecycle gates are explicit.
- Connecting Customization Studio to Shell.
- Making Shell read Studio drafts.
- Writing style registry records.
- Saving drafts.
- Applying styles.
- Generating `public/assets` output.
- Editing Core.
- Redesigning Shell UI.
- Modifying, deleting, renaming, or replacing ThemeTool.

## Next Slice

The next safe implementation slice may add a disabled route only if it:

- Registers the route under `/apps/studio/...`.
- Keeps all controls disabled.
- Performs no writes.
- Exposes no save/apply endpoints.
- Uses the lifecycle and permission gates defined here.
- Keeps ThemeTool untouched.

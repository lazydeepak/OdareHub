# Navigation and Menu Naming Conventions

Status: Architecture naming baseline / docs-only
Owner: Shell for runtime menu composition; Studio for editing tools; apps/modules for default contributions

This document fixes terminology before any runtime refactor. The goal is to prevent duplicate “nav composer” meanings and to prepare a simple WordPress-like Menu Editor in Studio without making Studio, Shell, Core, or surface composers hidden sources of truth.

## 1. Core vocabulary

### Navigation

Use **navigation** as the broad concept of moving through the system.

Examples:

- navigation contract
- navigation contribution
- navigation diagnostics
- navigation visibility

Do not use `navigation` when the thing is specifically an editable menu collection. Use `menu` for that.

### Menu

Use **menu** for a named, editable collection of ordered menu items that can be assigned to one or more locations.

A menu is similar to a WordPress menu:

- it has a key and label
- it has ordered/nested items
- it can be assigned to locations
- it can be edited by Studio through governance
- it is rendered by Shell or a surface composer only after resolution

Target future contract name:

```txt
menu_contract.v1
resolved_menu.v1
```

### Menu item

Use **menu item** for one entry inside a menu.

Allowed future item types:

```txt
internal_route
external_url
app_link
module_link
surface_link
action
heading
separator
```

### Menu location

Use **menu location** for a Shell- or surface-defined placement slot where a resolved menu can be loaded.

Location keys must be dot-delimited and lower-case:

```txt
shell.sidebar.primary
shell.sidebar.secondary
shell.topbar.more
shell.mobile.bottom
admin.header_actions
admin.quick_links
operator.rail
operator.context_sidebar
footer.primary
```

Shell owns Shell locations. Admin/operator surface owners own their surface locations. Apps/modules do not invent Shell locations.

### Navigation contribution

Use **navigation contribution** for app/module/plugin-owned default navigation declarations, especially existing `navigation.php` files.

Current examples:

```txt
apps/Studio/navigation.php
apps/SBAIO/navigation.php
apps/Manufacturing/navigation.php
plugins/AdminTools/navigation.php
```

A navigation contribution is owner-owned source metadata. It is not the final resolved menu.

### Menu resource

Use **menu resource** for an editable menu definition that Studio can manage.

Future examples:

```txt
apps/{Owner}/menus/*.php
storage/instance/menus/*.json
```

Generated or instance-owned menu resources may be directly applied by Studio after approval. Code-owned app/module menu resources should be changed by governed patch/diff flow.

### Resolved menu

Use **resolved menu** for the runtime-safe output after combining:

- default app/module navigation contributions
- menu resources
- Shell location rules
- ACL/permissions
- Workspace Profile shaping
- user-specific constraints

Shell/runtime should consume resolved menus, not Studio drafts.

## 2. Composer naming

### Runtime Menu Composer

Use **Runtime Menu Composer** for the service that resolves and composes menus for runtime rendering.

Target conceptual name:

```txt
ShellRuntimeMenuComposer
```

Current compatibility implementation:

```txt
app/Core/SidebarBuilder.php
```

Do not create a second runtime menu composer until the old one is clearly deprecated or wrapped. Because Core is locked, `SidebarBuilder` may remain in place temporarily, but it should be classified as compatibility runtime menu composition.

### Surface Composer

Use **Surface Composer** for a service that composes a whole page/surface, not a menu source of truth.

Current examples:

```txt
apps/Shell/Composers/AdminSurfaceComposer.php
apps/Shell/Composers/OperatorSurfaceComposer.php
```

Surface composers may consume resolved menu/quick-link regions, but they must not become independent permanent menu truth.

### Surface Contribution Registry

Use **Surface Contribution Registry** for systems that load app-owned contributions into a surface region.

Current examples:

```txt
plugins/Base/Services/HostSurfaceRegistryService.php
apps/Shell/Services/OperatorSurfaceContributionRegistry.php
```

These are not global menu composers. They are contribution registries for admin/operator surfaces.

### Sidebar / rail / drawer

Use these words for visual behavior, not source of truth:

```txt
sidebar = vertical menu/drawer UI
rail = compact app/zone selector
context_sidebar = contextual links for selected workspace/app
mobile_drawer = mobile presentation of navigation
```

Do not name source-of-truth services after only a visual form unless the service only renders that visual form.

## 3. Studio tool naming

### Final tool name

Use:

```txt
Studio Menu Editor
Navigation / Menu Tool
```

The user-facing name may be **Menu Editor**. The technical governed tool key should be:

```txt
menu_editor
```

or, if keeping compatibility:

```txt
navigation_menu_tool
```

### Avoid ambiguous name

Avoid using **Nav Composer** as the final name. It is unclear whether it means:

- Shell runtime composer
- Studio editor
- generated nav linker
- admin quick-link composer
- operator sidebar composer

### Current service rename target

Current:

```txt
apps/Studio/Services/StudioNavLinkingService.php
```

Correct conceptual name:

```txt
GeneratedNavLinkingService
```

Reason: it safely links generated app navigation files only. It is not the full menu editor.

Current:

```txt
apps/Studio/Services/StudioNavCandidateProviderService.php
```

Correct conceptual name:

```txt
NavigationDiagnosticCandidateService
```

Reason: it detects linked/missing/mismatch/blocked navigation candidates. It is diagnostic, not a menu composer.

## 4. Tool registry naming

Target source of truth for Studio tools:

```txt
apps/Studio/Tools/{ToolName}/manifest.php
StudioGovernedToolRegistryService
```

Current legacy/static catalog:

```txt
StudioToolCatalogService
```

Classification:

```txt
legacy_static_tool_catalog
```

Do not add new tool truth to the static catalog unless it is explicitly marked transitional. New governed tools should be represented under `/apps/Studio/Tools/*/manifest.php`.

## 5. Key naming conventions

### Location keys

Use dot-delimited keys:

```txt
shell.sidebar.primary
admin.quick_links
operator.rail
```

### Source keys

Use dot-delimited owner/source keys:

```txt
apps.studio.workspace
apps.sbaio.dashboard
apps.manufacturing.production
plugins.admin_tools.system
```

### Resource keys

Use lower snake case:

```txt
studio_workspace
main_admin_menu
production_queue
```

### Routes

Use URL path style with kebab-case where practical:

```txt
/apps/studio/tools/menu-editor
/apps/manufacturing/production-queue
```

Existing routes may remain for compatibility.

### PHP classes

Use explicit responsibility names:

```txt
ShellRuntimeMenuComposer
MenuLocationRegistry
ResolvedMenuBuilder
StudioMenuEditorService
GeneratedNavLinkingService
NavigationDiagnosticCandidateService
```

Avoid generic names like:

```txt
NavComposer
MenuBuilder
SidebarManager
NavigationManager
```

unless the class is truly the one canonical owner for that responsibility.

## 6. Current-name classification

| Current name | Classification | Target meaning |
|---|---|---|
| `SidebarBuilder` | compatibility runtime menu composer | eventually Shell runtime menu composer/wrapper |
| `AdminSurfaceComposer` | admin surface composer | page/surface composition, not menu truth |
| `OperatorSurfaceComposer` | operator surface composer | page/surface composition, not menu truth |
| `OperatorLayerSidebarService` | operator workspace navigation presenter | operator rail/context sidebar, not global menu truth |
| `HostSurfaceRegistryService` | host surface contribution registry | admin/me region contribution registry |
| `OperatorSurfaceContributionRegistry` | operator surface contribution registry | operator region contribution registry |
| `StudioNavLinkingService` | generated nav linking helper | not full menu editor |
| `StudioNavCandidateProviderService` | navigation diagnostic candidate provider | read-only diagnostics |
| `StudioToolCatalogService` | legacy static tool catalog | transitional only |
| `StudioGovernedToolRegistryService` | governed Studio tool registry | target Studio tool truth |

## 7. Future Studio Menu Editor rule

The future Studio Menu Editor should edit menu resources, not runtime composer code.

Allowed operations after governance is added:

```txt
create menu
rename menu
add/remove item
reorder item
nest item
assign menu to location
set visibility/permission
set active behavior
preview resolved menu
show diff
approve/apply
rollback
```

Apply scope:

```txt
Generated or instance-owned menu resource:
  direct governed apply allowed after approval.

Code-owned app/module navigation resource:
  propose patch/diff to owner-owned artifact; do not silently mutate.

Shell/Core runtime composer:
  no edit from Menu Editor.
```

## 8. Non-negotiable naming rules

1. Do not call surface composers menu composers.
2. Do not call Studio's editor the runtime composer.
3. Do not call generated nav linking the full menu editor.
4. Do not create another Shell runtime menu composer while `SidebarBuilder` remains active without an explicit compatibility boundary.
5. Menu locations are placement slots; menu resources are editable definitions; resolved menus are runtime output.
6. Shell renders and composes runtime menus; apps/modules contribute defaults; Studio edits through governance; ACL filters; Workspace Profile shapes; System Tools validate.

## 9. Recommended next slice

After this naming baseline, the next read-only slice should be:

```txt
docs/architecture/navigation-composition-inventory.md
scripts/architecture/check_navigation_composition_duplicates.sh
```

The diagnostic should report duplicate/overlapping systems without changing runtime behavior.

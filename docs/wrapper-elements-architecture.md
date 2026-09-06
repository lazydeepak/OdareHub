# Wrapper Elements Architecture — Implementation Guide

## Overview

The wrapper element system normalizes UI composition across three Shell layers:
- **Operator Layer** (`/u/{username}/*`): Manual sidebar, unified styling
- **Admin Layer** (`/admin/{username}/*`): Auto-generated sidebar from routes
- **Display Layer** (`/displays/*`): Minimal header, readonly kiosk

## New Components (Created)

### Base Wrapper Class
- **`LayerWrapperComposer`** (abstract base)
  - Location: `apps/Shell/Services/LayerWrapperComposer.php`
  - Responsibilities:
    - Define wrapper rendering interface (`renderHeader()`, `renderSidebar()`, `renderBreadcrumbs()`, `renderFooter()`)
    - Build shared wrapper CSS with theme tokens
    - Provide translation and context helpers
  - Shared CSS classes:
    - `.wrapper-container`, `.wrapper-header`, `.wrapper-sidebar`, `.wrapper-breadcrumbs`, `.wrapper-main`, `.wrapper-content`, `.wrapper-footer`
    - All use CSS custom properties: `--shell-bg`, `--surface`, `--text-main`, `--border-soft`, `--accent-main`, etc.

### Layer-Specific Wrappers

#### 1. OperatorLayerWrapperComposer
- **Location**: `apps/Shell/Services/OperatorLayerWrapperComposer.php`
- **Sidebar Composition**: Manual (via `OperatorLayerSidebarService`)
  - Constructs sidebar from app assignments and manually configured links
  - Uses icons and labels from sidebar service
- **Header**: Logo, company name, search bar, profile avatar
- **Breadcrumbs**: Current focus route (e.g., Dashboard > Assembly)
- **Footer**: "Operator Workspace", "Switch to Admin" link, refresh timestamp
- **CSS**: Inherits base, uses light Gmail-like theme

#### 2. AdminLayerWrapperComposer
- **Location**: `apps/Shell/Services/AdminLayerWrapperComposer.php`
- **Sidebar Composition**: Auto-generated from manifest routes
  - Reads from apps registry and manifest.json
  - Auto-generates governance-focused menu items (Dashboard, Users, Access, Display Manager, Audit, Applications)
  - Active item tracking from current route
- **Header**: System status indicator, company name + "Admin", profile avatar
- **Breadcrumbs**: Admin Home > Current Section
- **Footer**: Platform admin quick links (sourced from `UserDashboardAssignmentService::meCoreQuickLinks()`)
- **CSS**: Inherits base, darker theme variant

#### 3. DisplayLayerWrapperComposer
- **Location**: `apps/Shell/Services/DisplayLayerWrapperComposer.php`
- **Sidebar Composition**: None (readonly kiosk)
- **Header**: Minimal (logo, company + branch name, live clock)
- **Breadcrumbs**: None
- **Footer**: "Floor Display — Readonly Kiosk", refresh indicator
- **CSS**: Special overrides for minimal display-only layout

### Shared Templates
- **Location**: `apps/Shell/Views/shared/wrapper-*.php`
  - `wrapper-header.php` — Renders header section
  - `wrapper-sidebar.php` — Renders sidebar section
  - `wrapper-breadcrumbs.php` — Renders breadcrumb navigation
  - `wrapper-footer.php` — Renders footer section
- **Purpose**: Modular template fragments, easily upgradeable

## Integration Pattern (For Future Refactoring)

### Phase 1: Incremental Adoption (Recommended)

Each Surface Composer can opt-in to the wrapper system:

```php
// In OperatorSurfaceComposer::render()
$wrapper = new OperatorLayerWrapperComposer(
    $this->view,
    [
        'username' => $this->username,
        'company_name' => $companyName,
        'company_logo' => $companyLogo,
        'assigned_apps' => $appAssignments,
        'current_focus' => $currentView,
    ]
);

// Build wrapper HTML
$wrapperCSS = $wrapper->buildWrapperCSS();
$headerHTML = $wrapper->renderHeader();
$sidebarHTML = $wrapper->renderSidebar();
$breadcrumbsHTML = $wrapper->renderBreadcrumbs();
$footerHTML = $wrapper->renderFooter();

// Compose final page
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php echo $wrapperCSS; ?>
        /* Existing Surface Composer CSS here */
    </style>
</head>
<body class="wrapper-container">
    <?php echo $headerHTML; ?>
    
    <div style="display: flex; flex: 1;">
        <?php echo $sidebarHTML; ?>
        <main class="wrapper-main">
            <?php echo $breadcrumbsHTML; ?>
            <div class="wrapper-content">
                <!-- Existing content sections -->
            </div>
        </main>
    </div>
    
    <?php echo $footerHTML; ?>
</body>
</html>
<?php
```

### Phase 2: Full Refactoring

Once all Surface Composers have adopted wrappers:
1. Move wrapper instantiation to orchestrator services (OperatorLayerService, AdminLayerService, DisplayLayerService)
2. Extract Surface Composers into content-only classes
3. Remove wrapper HTML generation from Surface Composers

## Localization Support

All wrapper elements are fully localized:
- **English (en)**: 46 new keys (23 operator + 20 admin + 3 display)
- **Japanese (ja)**: 46 keys (日本語対応済)
- **Nepali (ne)**: 46 keys (नेपाली समर्थित)

**Key Namespaces**:
- `wrapper.operator.*` — Operator layer strings
- `wrapper.admin.*` — Admin layer strings
- `wrapper.display.*` — Display layer strings

Example translation:
```php
$this->tr('wrapper.operator.header', 'Workspace')
$this->tr('wrapper.admin.sidebar.dashboard', 'Dashboard')
$this->tr('wrapper.display.footer', 'Floor Display — Readonly Kiosk')
```

## CSS Architecture

### Shared CSS Classes (Base)
```css
.wrapper-container       /* Main flex container */
.wrapper-header          /* Header 64px sticky */
.wrapper-sidebar         /* Sidebar 272px */
.wrapper-sidebar.active  /* Sidebar item active state */
.wrapper-breadcrumbs     /* Breadcrumb navigation */
.wrapper-main            /* Content area flex container */
.wrapper-content         /* Content scroll area */
.wrapper-footer          /* Footer sticky bottom */
```

### CSS Custom Properties
```css
--shell-bg               /* Page background */
--sidebar-bg             /* Sidebar background */
--surface                /* Card/surface background */
--text-main              /* Primary text color */
--text-muted             /* Secondary/muted text */
--border-soft            /* Soft border color */
--accent-main            /* Primary accent color */
--hover-bg               /* Hover state background */
--active-bg              /* Active state background */
```

All properties inherit from global theme tokens (`theme.css`).

## Sidebar Composition Rules

### Operator Layer (Manual)
- Sidebar items: Built by `OperatorLayerSidebarService`
- Source: App assignments + manually configured menu items
- Active detection: URL-based (current route)
- Use case: Operator-specific task navigation

### Admin Layer (Auto-Generated)
- Sidebar items: Generated from manifest routes
- Source: App registry + `apps/*/manifest.json` files
- Active detection: Route prefix matching
- Use case: Governance and system navigation

### Display Layer (None)
- No sidebar (readonly kiosk)
- Full-width content layout

## Future Enhancement Opportunities

1. **Dynamic Sidebar Configuration**: Allow admins to customize sidebar items per wrapper
2. **Breadcrumb Auto-Generation**: Extract breadcrumbs from route history
3. **Mobile Responsive Sidebar**: Hamburger drawer for smaller screens
4. **Wrapper Plugins**: Allow modules to contribute sidebar items
5. **Theme Variations**: Support dark/light/custom wrapper themes
6. **Accessibility Audit**: ARIA labels, keyboard navigation

## Testing Checklist

- [ ] **Operator Layer** (`/u/{username}/dashboard`): Header + sidebar + breadcrumbs + footer render
- [ ] **Admin Layer** (`/admin/{username}`): Auto-generated sidebar items appear
- [ ] **Display Layer** (`/displays/user/{username}`): Minimal header + footer, no sidebar
- [ ] **Responsive**: Sidebar collapses on mobile
- [ ] **i18n**: All wrapper text translations in en/ja/ne
- [ ] **Accessibility**: ARIA labels on navigation elements
- [ ] **CSS**: Shared classes applied without conflicts

## Files Modified/Created

### New Files (11)
- `apps/Shell/Services/LayerWrapperComposer.php` (base abstract class)
- `apps/Shell/Services/OperatorLayerWrapperComposer.php` (operator implementation)
- `apps/Shell/Services/AdminLayerWrapperComposer.php` (admin implementation)
- `apps/Shell/Services/DisplayLayerWrapperComposer.php` (display implementation)
- `apps/Shell/Views/shared/wrapper-header.php` (header template)
- `apps/Shell/Views/shared/wrapper-sidebar.php` (sidebar template)
- `apps/Shell/Views/shared/wrapper-breadcrumbs.php` (breadcrumb template)
- `apps/Shell/Views/shared/wrapper-footer.php` (footer template)
- `docs/wrapper-elements-architecture.md` (this file)

### Modified Files (3)
- `app/Locale/en.php` (+46 wrapper keys)
- `app/Locale/ja.php` (+46 wrapper keys)
- `app/Locale/ne.php` (+46 wrapper keys)

## Status

✅ **Infrastructure Complete**: Wrapper base classes, layer-specific implementations, shared templates, localization

⏳ **Integration Pending**: OperatorSurfaceComposer, AdminSurfaceComposer, DisplaySurfaceComposer adoption (Phase 2)

✅ **Localization**: Full en/ja/ne coverage

✅ **Shared CSS**: 400+ lines of unified wrapper CSS with theme tokens

# Admin Mode and Developer Surface — Retained Work Inventory

**Status:** Review required — preserve all listed runtime artifacts and source links
**Date:** 2026-07-13
**Decision owner:** Product/repository owner

## Preservation rule

The artifacts in this inventory must not be deleted, folded into another feature, or treated as dead code without an explicit owner decision. Some are incomplete presentation paths around active platform behavior; others are compatibility bridges or placeholders for intended work.

## 1. Sidebar Developer gateway

- **Location:** `public/views/layouts/sidebar.php`
- **Current state:** Development-only compact gateway showing the current mode and linking to the canonical Developer group on the unified admin home.
- **Supporting styles:** `.utility-dev` and `.utility-dev-actions` rules across Shell navigation, admin, component, and form stylesheets.
- **Intended job:** Provide cross-page access to the unified Developer group without duplicating its Route Registry, Catalog, Architecture Health, or Widget Builder links.
- **Runtime status:** Visible only when the current administrator and environment satisfy the developer-tools gate.
- **Decision:** Upgraded as a single gateway. It owns no separate tool list or mutation control and remains hidden outside Development visibility.

## 2. Header platform-mode indicator

- **Location:** `public/views/layouts/header.php`
- **Current state:** Renders a read-only Production/Development/Demo indicator for Platform Admin authority on admin-layer pages.
- **Intended job:** Keep behavior mode visible across admin work and link to the canonical Platform Mode workspace.
- **Runtime status:** Mode lookup is active; presentation variables are currently dormant.
- **Decision:** Platform-authority-only indicator; it never exposes a second selector and is absent from operator/display layers.

## 3. Platform mode selector

- **Locations:**
  - `app/Services/PlatformModeService.php`
  - `plugins/Base/Views/ops/platform_mode_selector.php`
  - `plugins/Base/Views/ops/role_dashboard.php`
  - `apps/Platform/routes.php` (`/ops/platform-mode-switch`)
- **Current state:** Functional Production/Development/Demo selector hosted by the legacy platform role dashboard.
- **Intended job:** Change the canonical behavior mode that controls developer-tool visibility, demo presentation, and production safety.
- **Runtime status:** Active but no longer prominent after `/admin/{username}` became the visible admin home.
- **Review question:** Keep it on the compatibility dashboard, place it in System Tools, place it in Setup/Environment, or expose a compact mode control elsewhere?
- **Decision:** Preserve service, selector, routes, and current redirects pending product review.

## 4. Developer Strip / Engineering Workspace links

- **Locations:**
  - `platform/Security/EngineeringWorkspacePageContextResolver.php`
  - `apps/Shell/Views/partials/developer_strip.php`
  - `apps/Studio/tests/probe_developer_strip_context.php`
- **Current state:** Active contextual strip providing Overview, Work, Rules, and Decisions for eligible engineering workspaces.
- **Intended job:** Connect developer-mode pages to governed Engineering Workspace documents.
- **Runtime status:** Active and tested; suppressed in Production and Demo modes and by the deployment kill switch.
- **Review question:** Should this remain a page strip, join the unified Developer group, or coordinate with the sidebar Developer placeholder?
- **Decision:** Preserve as active behavior.

## 5. Legacy role-dashboard and panel compatibility

- **Locations:**
  - `/admin/system-tools/platform-mode`
  - App Admin mode status now renders directly in `/admin/{username}`
  - `apps/Platform/Services/AdminDashboardPanelBlockService.php`
  - Base role-dashboard controller/views
- **Current state:** Compatibility dashboards and fallback panel composition remain registered while `/admin/{username}` is the primary visible admin home.
- **Intended job:** Preserve older links, role payloads, specialized panels, and mode-management surfaces during dashboard consolidation.
- **Runtime status:** Active compatibility/fallback path, not dead code.
- **Review question:** Which specialized panels should migrate to the unified home, and which routes should remain permanent diagnostic or compatibility surfaces?
- **Decision:** Retain all routes and services until parity is explicitly approved.
- **Migration matrix:** See `engineering/Shell/platform-admin-dashboard-migration-matrix.md`; the Platform Admin Dashboard link remains visible until that parity checklist is approved.

## 6. Mode-switch return routes

- **Location:** `apps/Platform/routes.php`
- **Current state:** Mode-switch success and error redirects return to `/admin/system-tools/platform-mode`; the deleted legacy dashboard is no longer an allowed return path.
- **Likely intended job:** Return the administrator to the page that currently hosts the selector and display feedback.
- **Runtime status:** Correct for the existing selector location, even though the destination is no longer primary navigation.
- **Review question:** Change only if the selector is moved; the return route should follow its final host.
- **Decision:** Retain unchanged until hosting is decided.

## Review checklist

- [x] Use the sidebar Developer control as a single gateway to the unified Developer group.
- [x] Display read-only mode status in the Platform Admin header and keep mutation in System Tools.
- [ ] Decide the permanent home of the mode selector.
- [ ] Decide whether Developer Strip and Developer navigation should remain separate.
- [ ] Identify legacy dashboard panels that must migrate to the unified home.
- [ ] Approve compatibility routes that can eventually become redirects or tombstones.
- [ ] Approve any removal only after live parity and role-matrix acceptance.

## Current recommendation

Keep every listed artifact. Treat the empty sidebar control and dormant header presentation values as reserved unfinished work, not deletion candidates. Continue consolidation around them only after the intended mode-management and developer-workspace experience is chosen.

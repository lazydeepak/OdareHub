<?php
declare(strict_types=1);

/**
 * Admin Wrapper — Open
 *
 * Canonical entry point for all admin-territory pages:
 *   /admin/{username}/*   admin home and sub-pages
 *   /apps/*               business module pages
 *   /ops/*                governance and platform pages
 *   /account, /login, /me, and every other non-/u/* route
 *
 * Chrome rendered by this wrapper (controlled by flags in header.php):
 *   - Left sidebar            ($showSidebar = true)
 *   - Topbar search box       ($showTopbarSearch = true)
 *   - Alerts/notification bell ($showAlerts = true)
 *   - Mobile bottom strip     ($showAdminMobileStrip — CSS class .admin-bottom-nav*)
 *     activated at ≤760 px viewport via media query in app.css
 *
 * Usage: every admin-territory view must open with this file, not header.php directly.
 *   <?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
 *
 * To update admin chrome for all admin pages in one place, edit this file.
 * The shared layout engine (header.php) must not be required directly from views.
 *
 * Operator layer (/u/*) uses its own wrapper: OperatorLayerWrapperComposer.php
 * Display layer (/displays/*) uses: display wrapper (WorkspaceWrapperRegistry::WRAPPER_DISPLAY)
 *
 * @see public/views/layouts/admin-wrapper-close.php
 * @see apps/Shell/Services/WorkspaceWrapperRegistry.php
 */

require __DIR__ . '/header.php';
?>

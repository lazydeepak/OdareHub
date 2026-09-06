<?php
declare(strict_types=1);

/**
 * Legacy route compatibility layer for Manufacturing app.
 *
 * These routes ensure old bookmarked / linked URLs continue to work
 * during and after the legacy-plugin → app-native migration.
 *
 * Each entry REDIRECTS to the canonical new URL so browser bookmarks
 * and navigation links are silently forwarded rather than 500/404.
 */

// /dispatch was a short-form URL used before DispatchEntries plugin
// introduced the /dispatch-entries namespace. Redirect to canonical path.
$router->get('/dispatch', function () {
    header('Location: /dispatch-entries', true, 301);
    exit;
});

// /qc was a historical shorthand sometimes referenced in older dashboards.
$router->get('/qc', function () {
    header('Location: /qc-entries', true, 301);
    exit;
});

// /production was an early alias before the queue-centric model.
$router->get('/production', function () {
    header('Location: /apps/manufacturing/production-queue', true, 301);
    exit;
});

// Canonical suite root should resolve to the manufacturing portal.
$router->get('/manufacturing', function () {
    header('Location: /apps/manufacturing', true, 301);
    exit;
});

// /admin/apps is still present in Base plugin, but /admin/system-tools/apps
// redirects there too. Keep a 301 forward here as a safety net since the
// old AdminTools widget linked /admin/apps directly.
$router->get('/mfg', function () {
    header('Location: /apps/manufacturing', true, 301);
    exit;
});

// Preserve old bookmarks that omit the /manufacturing prefix.
$router->get('/assembly-plans', function () {
    header('Deprecation: true');
    header('Sunset: Fri, 31 Jul 2026 23:59:59 GMT');
    header('Link: </apps/manufacturing/assembly-plans>; rel="successor-version"');
    header('Location: /apps/manufacturing/assembly-plans', true, 301);
    exit;
});

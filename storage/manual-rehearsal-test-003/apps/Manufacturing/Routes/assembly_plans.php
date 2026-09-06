<?php
declare(strict_types=1);

// Legacy compat redirects: only register when AssemblyPlans module is active so that
// redirects do not point to non-existent routes when the module is disabled.
if (isset($c) && $c->get('plugins')->status('AssemblyPlans') !== 'active') {
    return;
}

$legacyAssemblyRedirectHeaders = static function(string $sunset = 'Fri, 31 Jul 2026 23:59:59 GMT'): void {
    header('Deprecation: true');
    header('Sunset: ' . $sunset);
    header('Link: </apps/manufacturing/assembly-plans>; rel="successor-version"');
};

$router->get('/manufacturing/assembly-plans', function() use ($legacyAssemblyRedirectHeaders) {
    $legacyAssemblyRedirectHeaders();
    $query = http_build_query($_GET);
    header('Location: /apps/manufacturing/assembly-plans' . ($query !== '' ? ('?' . $query) : ''), true, 302);
    exit;
});

$router->get('/manufacturing/assembly-workbench', function() use ($legacyAssemblyRedirectHeaders) {
    $legacyAssemblyRedirectHeaders();
    header('Location: /apps/manufacturing/assembly-plans?only_open=1', true, 302);
    exit;
});

$router->get('/manufacturing/assembly-plans/detail', function() use ($legacyAssemblyRedirectHeaders) {
    $legacyAssemblyRedirectHeaders();
    $query = http_build_query($_GET);
    header('Location: /apps/manufacturing/assembly-plans/detail' . ($query !== '' ? ('?' . $query) : ''), true, 302);
    exit;
});

$router->post('/manufacturing/assembly-plans/update', function() use ($legacyAssemblyRedirectHeaders) {
    $legacyAssemblyRedirectHeaders();
    $query = http_build_query($_POST);
    header('Location: /apps/manufacturing/assembly-plans/update' . ($query !== '' ? ('?' . $query) : ''), true, 302);
    exit;
});

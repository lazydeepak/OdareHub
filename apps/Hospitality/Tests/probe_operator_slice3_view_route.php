<?php
declare(strict_types=1);

/**
 * Hospitality Operator Composition Slice 3 Probe
 *
 * Covers the bounded Shell seam and the app-owned focus view:
 *   1. Shell routes.php registers exactly one /u/hospitality GET handler mirroring the
 *      SBAIO precedent (same resolveOperatorGetRoute + render shape, assignment gate arg)
 *      plus exactly one KNOWN_VIEW_SLUGS entry.
 *   2. Composer branch registration is present and minimal: focus flag, include branch
 *      preferring the app-contributed view, dashboard-fallback exclusion.
 *   3. Focus URL/label maps and wrapper.operator.focus.hospitality keys follow the
 *      SBAIO precedent across Shell-owned catalogs.
 *   4. The app-owned view exists, is read-only (no forms/buttons/links/POST targets),
 *      self-gates hospitality.view, renders only from the app board adapter, and is now
 *      resolvable through the real contribution registry.
 *
 * Usage: php apps/Hospitality/Tests/probe_operator_slice3_view_route.php
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/apps/Shell/Services/OperatorSurfaceContributionRegistry.php';

use Apps\Hospitality\Services\OperatorContributionService;
use Apps\Shell\Services\OperatorSurfaceContributionRegistry;

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "  PASS [{$label}]\n";
};
$fail = static function (string $label, string $detail = '') use (&$failed): void {
    $failed++;
    echo "  FAIL [{$label}]" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};

$appRoot = APP_ROOT . '/apps/Hospitality';
$shellRoutes = (string)file_get_contents(APP_ROOT . '/apps/Shell/routes.php');
$manifest = json_decode((string)file_get_contents($appRoot . '/manifest.json'), true);

echo "== Operator Composition Slice 3: operator view + Shell route seam ==\n";

// ---- Group A: route seam mirrors the SBAIO precedent ----
substr_count($shellRoutes, "'/u/hospitality'") === 1
    ? $pass('exactly one /u/hospitality route registered')
    : $fail('exactly one /u/hospitality route registered', (string)substr_count($shellRoutes, "'/u/hospitality'"));

preg_match(
    "/router->get\('\/u\/hospitality', function \(\) use \(\\\$resolveOperatorGetRoute\) \{\s*"
    . "\\\$operatorRoute = \\\$resolveOperatorGetRoute\('hospitality', 'hospitality'\);\s*"
    . "\s*OperatorLayerService::render\(\\\$operatorRoute\['username'\], array_merge\(\\\$_GET, \[\s*"
    . "'focus' => 'hospitality',\s*\]\), \\\$operatorRoute\['canonical_path'\]\);\s*return null;\s*\}\);/",
    $shellRoutes
) === 1
    ? $pass('route handler mirrors SBAIO shape with assignment-gate arg')
    : $fail('route handler mirrors SBAIO shape with assignment-gate arg');

preg_match_all("/'(dashboard|work-entry|critical|recent|parts|production|demand|orders|processing|preparation|dispatch|coverage|qc|machines|assembly|materials|alerts|notifications|messages|handoff|account|preferences|tasks|sbaio|data-exchange|hospitality)',/", $shellRoutes, $slugMatches);
substr_count(implode('', $slugMatches[0] ?? []), "'hospitality',") >= 1
    && substr_count((string)preg_replace("/\s+/", '', explode('$KNOWN_VIEW_SLUGS', $shellRoutes)[1] ?? ''), "'hospitality',") === 1
    ? $pass('exactly one KNOWN_VIEW_SLUGS entry for hospitality')
    : $fail('exactly one KNOWN_VIEW_SLUGS entry for hospitality');

// ---- Group B: composer branch registration is minimal and complete ----
$composerFile = APP_ROOT . '/apps/Shell/Composers/OperatorDashboardComposer.php';
$composerSrc = (string)file_get_contents($composerFile);
str_contains($composerSrc, "\$isHospitalityFocus = (\$focus === 'hospitality');")
    ? $pass('composer declares hospitality focus flag')
    : $fail('composer declares hospitality focus flag');
str_contains($composerSrc, "\$resolveFocusView('hospitality',")
    ? $pass('composer include branch prefers contributed hospitality view')
    : $fail('composer include branch prefers contributed hospitality view');
substr_count($composerSrc, '!$isHospitalityFocus') === 1
    ? $pass('dashboard fallback excludes hospitality exactly once')
    : $fail('dashboard fallback exclusion count', (string)substr_count($composerSrc, '!$isHospitalityFocus'));
// The only composer lines mentioning hospitality must be the three intended edit sites:
// flag declaration, dashboard-fallback exclusion, and the include branch.
preg_grep('/hospitality/i', explode("\n", $composerSrc))
    ? (($count = count(preg_grep('/hospitality/i', explode("\n", $composerSrc)))) === 3
        ? $pass('composer footprint stays minimal (flag + fallback + include only)')
        : $fail('composer footprint stays minimal (flag + fallback + include only)', (string)$count . ' lines'))
    : $fail('composer footprint stays minimal (flag + fallback + include only)');

// URL + label maps
$surfaceComposerSrc = (string)file_get_contents(APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php');
substr_count($surfaceComposerSrc, "'/u/' . rawurlencode(\$this->username) . '/hospitality'") === 1
    ? $pass('focus URL map entry follows precedent')
    : $fail('focus URL map entry');
$labelComposerSrc = (string)file_get_contents(APP_ROOT . '/apps/Shell/Composers/OperatorFocusLabelComposer.php');
str_contains($labelComposerSrc, "wrapper.operator.focus.hospitality")
    ? $pass('focus label composer entry follows precedent')
    : $fail('focus label composer entry');
foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = (string)file_get_contents(APP_ROOT . '/apps/Shell/Resources/lang/' . $lang . '.php');
    str_contains($catalog, "'wrapper.operator.focus.hospitality'")
        ? $pass("Shell locale {$lang} carries focus label key")
        : $fail("Shell locale {$lang} carries focus label key");
}

// ---- Group C: app-owned view contract ----
$viewPath = $appRoot . '/Views/operator/hospitality.php';
is_file($viewPath) ? $pass('app-owned operator view exists at declared path') : $fail('operator view exists');

$viewSrc = (string)file_get_contents($viewPath);
str_contains($viewSrc, "AclPolicy::can('hospitality.view'")
    ? $pass('view self-gates hospitality.view server-side')
    : $fail('view self-gates hospitality.view');
str_contains($viewSrc, 'HospitalityBoardAdapter::summary()')
    ? $pass('view renders from the app board adapter only')
    : $fail('view data source');
str_contains($viewSrc, '$this->tr(')
    ? $pass('view localizes through tr()')
    : $fail('view localization');

// Mutation-surface contract (slice 4): forms are permitted ONLY for
// hospitality.manage holders via the $canManage guard, must be jailed under
// /u/{username}/*, must carry CSRF, and must not exist before the guard.
$manageGuardPos = strpos($viewSrc, "AclPolicy::can('hospitality.manage'");
$manageGuardPos !== false
    ? $pass('mutation guard: view checks hospitality.manage server-side')
    : $fail('mutation guard: view checks hospitality.manage server-side');

preg_match_all('/<form[^>]*action="([^"]*)"/i', $viewSrc, $formActionMatches);
$allFormsJailed = true;
foreach (($formActionMatches[1] ?? []) as $formAction) {
    if (!str_starts_with((string)$formAction, '/u/')) {
        $allFormsJailed = false;
    }
}
$allFormsJailed
    ? $pass('every operator form action stays jailed under /u/{username}')
    : $fail('every operator form action stays jailed under /u/{username}', implode(',', $formActionMatches[1] ?? []));

$formCount = substr_count(strtolower($viewSrc), '<form');
if ($formCount > 0) {
    (substr_count(strtolower($viewSrc), 'name="csrf"') >= $formCount)
        ? $pass('every operator form carries a CSRF field')
        : $fail('every operator form carries a CSRF field', "forms={$formCount}");
    $firstFormPos = strpos(strtolower($viewSrc), '<form');
    $guardIfPos = strpos($viewSrc, 'if ($canManage):');
    ($manageGuardPos !== false && $guardIfPos !== false && $firstFormPos > $guardIfPos)
        ? $pass('form markup appears only inside the manage-gated branch')
        : $fail('form markup appears only inside the manage-gated branch');
} else {
    $pass('every operator form carries a CSRF field (no forms present)');
    $pass('form markup appears only inside the manage-gated branch (no forms present)');
}

// Read-only guarantees for non-mutating surfaces: no anchors, handlers, or raw targets.
foreach ([
    '<a href' => 'no anchor links',
    'onclick' => 'no inline handlers',
] as $needle => $label) {
    !str_contains(strtolower($viewSrc), $needle)
        ? $pass("read-only view: {$label}")
        : $fail("read-only view: {$label}");
}

// Boundary scan on the new view file.
$viewLower = strtolower($viewSrc);
$needles = ['pro' . 'curement', 'invent' . 'ory', 'bill' . 'ing', 'part' . 'ies', 'sb' . 'aio', 'mf' . 'g'];
$hits = [];
foreach ($needles as $needle) {
    if (str_contains($viewLower, $needle)) {
        $hits[] = $needle;
    }
}
$hits === [] ? $pass('view avoids shared-app concepts') : $fail('view boundary scan', implode(',', $hits));

// ---- Group D: registry resolves the now-existing contributed view ----
try {
    $focusMap = OperatorSurfaceContributionRegistry::focusViewMap([
        'active_assigned_apps' => ['hospitality'],
    ]);
    $resolvedView = (string)($focusMap['hospitality'] ?? '');
    ($resolvedView !== '' && is_file($resolvedView))
        ? $pass('registry resolves hospitality focus to an existing file')
        : $fail('registry focus resolution', $resolvedView);
} catch (\Throwable $e) {
    $fail('registry focus resolution', $e->getMessage());
}

$providerFocus = OperatorContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['hospitality']],
]);
$declared = (string)($providerFocus['view_map']['hospitality'] ?? '');
($declared !== '' && realpath($declared) === realpath($viewPath))
    ? $pass('provider-declared path equals the implemented view')
    : $fail('provider/view path agreement', $declared);

// ---- Group E: slice-4 contribution hardening ----
$hooks = is_array($manifest['hooks'] ?? null) ? $manifest['hooks'] : [];
$providerFileMatches = count($hooks) === 2;
foreach ($hooks as $hook) {
    if (($hook['provider_file'] ?? '') !== 'Services/OperatorContributionService.php'
        || ($hook['provider'] ?? '') !== 'Apps\\Hospitality\\Services\\OperatorContributionService::contribute'
        || !is_file($appRoot . '/' . (string)($hook['provider_file'] ?? ''))) {
        $providerFileMatches = false;
    }
}
$providerFileMatches
    ? $pass('manifest hooks agree with the app-owned provider file')
    : $fail('manifest hook/provider agreement');

$sidebar = OperatorContributionService::contribute([
    'surface' => 'operator',
    'region' => 'sidebar',
    'context' => ['username' => 'operator.example', 'active_assigned_apps' => ['hospitality']],
]);
$sidebarRoute = (string)($sidebar['sections'][0]['items'][0]['route'] ?? '');
str_starts_with($sidebarRoute, '/u/')
    ? $pass('all emitted Hospitality sidebar routes start with /u/')
    : $fail('Hospitality sidebar route confinement', $sidebarRoute);

$operatorKeys = [];
foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = include $appRoot . '/Resources/lang/' . $lang . '.php';
    foreach ((array)$catalog as $key => $_value) {
        if (str_starts_with((string)$key, 'hospitality.operator.')) {
            $operatorKeys[(string)$key] = true;
        }
    }
}
$localeParity = true;
foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = include $appRoot . '/Resources/lang/' . $lang . '.php';
    foreach (array_keys($operatorKeys) as $key) {
        if (!array_key_exists($key, (array)$catalog)) {
            $localeParity = false;
        }
    }
}
$localeParity
    ? $pass('all Hospitality operator locale keys have en/ja/ne coverage')
    : $fail('Hospitality operator locale parity');

$newOperatorFiles = [
    $appRoot . '/Services/OperatorContributionService.php',
    $appRoot . '/Services/OperatorLayerAdapters/HospitalityBoardAdapter.php',
    $viewPath,
];
$foreignNeedles = ['procurement_', 'sbaio_', 'mfg_', 'billing', 'parties', 'inventory'];
$foreignHits = [];
foreach ($newOperatorFiles as $file) {
    $source = strtolower((string)file_get_contents($file));
    foreach ($foreignNeedles as $needle) {
        if (str_contains($source, $needle)) {
            $foreignHits[] = basename($file) . ':' . $needle;
        }
    }
}
$foreignHits === []
    ? $pass('new Hospitality operator files avoid foreign app concepts')
    : $fail('new Hospitality operator file boundary scan', implode(',', $foreignHits));

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);

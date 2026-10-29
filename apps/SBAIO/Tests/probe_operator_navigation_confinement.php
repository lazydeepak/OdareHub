<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/apps/SBAIO/Services/OperatorSurfaceContributionService.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
};

$viewSource = (string)file_get_contents(APP_ROOT . '/apps/SBAIO/Views/operator/sbaio.php');
$check(!str_contains($viewSource, '/apps/sbaio'), 'operator SBAIO view contains no administrative SBAIO destination');
$check(str_contains($viewSource, 'operator.sbaio.unavailable.message'), 'unsupported state uses a localized message key');
$check(str_contains($viewSource, 'aria-disabled="true"'), 'unsupported tabs are rendered non-actionable');
$check(!str_contains($viewSource, 'mysqli') && !str_contains($viewSource, 'SELECT '), 'operator navigation view has no database access');

final class SbaioOperatorNavigationProbeRenderer
{
    public function tr(string $key, string $fallback = '', array $params = []): string
    {
        $translations = [
            'operator.sbaio.unavailable.title' => 'SBAIO section unavailable',
            'operator.sbaio.unavailable.message' => 'This section is unavailable in the operator workspace.',
        ];
        $value = $translations[$key] ?? $fallback;
        foreach ($params as $name => $replacement) {
            $value = str_replace('{' . $name . '}', (string)$replacement, $value);
        }
        return $value;
    }

    public function render(string $tab): string
    {
        $data = [
            'username' => 'worker',
            'current_query' => ['tab' => $tab],
        ];
        ob_start();
        include APP_ROOT . '/apps/SBAIO/Views/operator/sbaio.php';
        return (string)ob_get_clean();
    }
}

$renderer = new SbaioOperatorNavigationProbeRenderer();
$overview = $renderer->render('overview');
$attendance = $renderer->render('attendance');

$check(str_contains($overview, 'href="/u/worker/sbaio?tab=overview"'), 'overview keeps the existing jailed operator destination');
$check(!preg_match('#href="/(?:apps|admin|ops)/#', $overview), 'overview has no administrative or ops links');
$check(substr_count($overview, '<a ') === 1, 'overview renders only one actionable operator link');
$overviewHrefs = [];
preg_match_all('#href="([^"]+)"#', $overview, $overviewHrefMatches);
foreach ((array)($overviewHrefMatches[1] ?? []) as $href) {
    $overviewHrefs[] = (string)$href;
}
$check($overviewHrefs !== [] && count(array_filter($overviewHrefs, static fn (string $href): bool => !str_starts_with($href, '/u/worker/'))) === 0, 'all rendered actionable links remain in the authenticated user namespace');
$check(str_contains($attendance, 'aria-disabled="true"'), 'unsupported attendance tab is non-actionable');
$check(str_contains($attendance, 'operator-subnav-item--unavailable') && substr_count($attendance, '<a ') === 1, 'unsupported attendance tab emits no additional href');
$check(str_contains($attendance, 'SBAIO section unavailable'), 'unsupported attendance selection renders unavailable state');
$check(!preg_match('#/(?:apps|admin|ops)/#', $attendance), 'unsupported state contains no administrative destination');

$assigned = \Apps\SBAIO\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['sbaio']],
]);
$denied = \Apps\SBAIO\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => []],
]);
$check(isset($assigned['view_map']['sbaio']), 'assigned SBAIO context retains operator view contribution');
$check($denied === [], 'unassigned context remains denied before view loading');

foreach (['en', 'ja', 'ne'] as $locale) {
    $catalog = require APP_ROOT . '/apps/SBAIO/Resources/lang/' . $locale . '.php';
    $check(isset($catalog['operator.sbaio.unavailable.title'], $catalog['operator.sbaio.unavailable.message']), "{$locale} has unavailable-state translations");
}

echo "SBAIO operator navigation confinement: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

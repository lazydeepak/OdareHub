<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/apps/Shell/Composers/OperatorHeaderComposer.php';
require_once APP_ROOT . '/apps/Shell/Composers/OperatorSidebarComposer.php';
require_once APP_ROOT . '/apps/Shell/Composers/OperatorAvatarMenuComposer.php';
require_once APP_ROOT . '/apps/Shell/Composers/OperatorDashboardComposer.php';
require_once APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php';
require_once APP_ROOT . '/apps/Shell/Composers/OperatorSearchComposer.php';
require_once APP_ROOT . '/apps/Shell/Composers/OperatorFocusLabelComposer.php';
require_once APP_ROOT . '/apps/Shell/Composers/OperatorDashboardInsightsComposer.php';

use Apps\Shell\Composers\OperatorAvatarMenuComposer;
use Apps\Shell\Composers\OperatorDashboardComposer;
use Apps\Shell\Composers\OperatorDashboardInsightsComposer;
use Apps\Shell\Composers\OperatorFocusLabelComposer;
use Apps\Shell\Composers\OperatorHeaderComposer;
use Apps\Shell\Composers\OperatorInteractionScriptComposer;
use Apps\Shell\Composers\OperatorSearchComposer;
use Apps\Shell\Composers\OperatorSidebarComposer;

$assertions = 0;

function op_extract_assert_true(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function op_extract_assert_contains(string $haystack, string $needle, string $message): void
{
    op_extract_assert_true(str_contains($haystack, $needle), $message);
}

$data = [
    'home_url' => '/u/demo/dashboard',
    'username' => 'demo',
    'user_email' => 'demo@example.test',
    'company_name' => 'Demo Co',
    'contextual_sidebar' => [
        'app_label' => 'Workspace',
        'sections' => [
            [
                'title' => 'Main',
                'items' => [
                    ['route' => '/u/demo/dashboard', 'icon' => 'D', 'label' => 'Dashboard', 'badge' => 0],
                    ['route' => '/u/demo/parts', 'icon' => 'P', 'label' => 'Parts', 'badge' => 2],
                ],
            ],
        ],
    ],
    'sidebar_active_route' => '/u/demo/dashboard',
    'my_account_url' => '/u/demo/account',
    'notifications_url' => '/u/demo/notifications',
    'messages_url' => '/u/demo/messages',
    'parts_table' => [
        'rows' => [
            ['id' => 42, 'parts_name' => 'Widget A', 'parts_number' => 'WA-42', 'model' => 'M1'],
        ],
    ],
    'daily_order_chart' => ['empty_label' => 'No results'],
    'dashboard_title' => 'Dashboard',
    'common_home_tiles' => [
        ['key' => 'home', 'label' => 'Home', 'value' => '1', 'primary' => true, 'subtitle' => 'Ready', 'items' => ['One']],
    ],
    'dashboard_tiles' => [
        ['key' => 'orders', 'label' => 'Orders', 'value' => '12', 'subtitle' => 'Today', 'items' => ['A (2)'], 'trends' => [['period' => 'Day', 'delta_label' => '+1', 'tone' => 'up']]],
    ],
    'manufacturing_available' => true,
    'overview_cards' => [
        ['title' => 'Pulse', 'tone' => 'accent', 'kind' => 'bar_chart', 'metric_label' => 'Parts', 'bars' => [['period' => 'Day', 'value_label' => '12', 'percent' => 40]]],
    ],
    'critical_title' => 'Critical Items',
    'critical_cards' => [
        ['key' => 'critical_parts', 'title' => 'Critical Parts', 'subtitle' => 'Now', 'rows' => []],
    ],
];

$i18n = [
    'menu' => 'Menu',
    'company_home' => 'Company Home',
    'search' => 'Search',
    'scan' => 'Scan',
    'notifications' => 'Notifications',
    'message' => 'Message',
    'profile' => 'Profile',
    'workspace' => 'Workspace',
    'my_account' => 'My Account',
    'language' => 'Language',
    'currency' => 'Currency',
    'theme' => 'Theme',
    'switch_to_me' => 'Switch to Admin',
    'sign_out_confirm' => 'Sign out?',
    'sign_out' => 'Sign out',
    'quick_actions' => 'Quick Actions',
    'search_no_route' => 'No matching route',
    'search_no_match' => 'No matching result',
    'scan_prompt_manual' => 'Enter scanned value',
    'scan_panel_title' => 'Scan code',
    'scan_starting_camera' => 'Starting camera...',
    'scan_enter_value' => 'Enter value',
    'scan_scanning' => 'Scanning...',
    'scan_unavailable' => 'Scanner unavailable',
    'scan_function_unavailable' => 'Scan function is unavailable',
];

$routeIndex = OperatorSearchComposer::buildRouteIndex($data, 'demo', 'Workspace', 'My Account');
$entityIndex = OperatorSearchComposer::buildEntityIndex($data, $routeIndex, 'demo', 'Part #{id}');
$focusLabel = OperatorFocusLabelComposer::resolveFromQuery(
    ['focus' => '  WORK-ENTRY  '],
    static fn (string $key, string $fallback): string => $fallback
);
$unknownFocusLabel = OperatorFocusLabelComposer::resolveFromQuery(
    ['focus' => 'unknown-focus'],
    static fn (string $key, string $fallback): string => $fallback
);

op_extract_assert_true(count($routeIndex) === 3, 'route search index includes home, account, and sidebar routes');
op_extract_assert_true((string)($routeIndex[0]['route'] ?? '') === '/u/demo/dashboard', 'home route remains first search route');
op_extract_assert_true((string)($entityIndex[0]['route'] ?? '') === '/u/demo/parts/detail?part_id=42', 'part entity search route preserves detail route');
op_extract_assert_true((string)($entityIndex[0]['text'] ?? '') === 'Widget A WA-42 M1 42', 'part entity search text preserves searchable terms');
op_extract_assert_true($focusLabel === 'Work Entry', 'focus label composer resolves normalized query focus');
op_extract_assert_true($unknownFocusLabel === '', 'focus label composer returns empty string for unknown focus');
op_extract_assert_true(
    OperatorFocusLabelComposer::normalizeFocus('  DISPATCH-DETAIL  ') === 'dispatch-detail',
    'focus label composer normalization is deterministic'
);

// --- OperatorDashboardInsightsComposer contract ---

$trSpyCalls = [];
$trSpy = static function (string $key, string $fallback, array $params = []) use (&$trSpyCalls): string {
    $trSpyCalls[] = ['key' => $key, 'fallback' => $fallback];
    return $fallback;
};

op_extract_assert_true(
    class_exists(OperatorDashboardInsightsComposer::class),
    'OperatorDashboardInsightsComposer class exists'
);
op_extract_assert_true(
    is_callable([OperatorDashboardInsightsComposer::class, 'buildOrdersInsights']),
    'buildOrdersInsights is a callable static method'
);
op_extract_assert_true(
    is_callable([OperatorDashboardInsightsComposer::class, 'buildPartsInsights']),
    'buildPartsInsights is a callable static method'
);
op_extract_assert_true(
    is_callable([OperatorDashboardInsightsComposer::class, 'buildOverstockInsights']),
    'buildOverstockInsights is a callable static method'
);
op_extract_assert_true(
    is_callable([OperatorDashboardInsightsComposer::class, 'buildWasteInsights']),
    'buildWasteInsights is a callable static method'
);
op_extract_assert_true(
    is_callable([OperatorDashboardInsightsComposer::class, 'buildZairyoInsights']),
    'buildZairyoInsights is a callable static method'
);
op_extract_assert_true(
    is_callable([OperatorDashboardInsightsComposer::class, 'buildDispatchInsights']),
    'buildDispatchInsights is a callable static method'
);

// Error-path contract: when DB is unavailable the catch block must return the correct array shapes
$ordersFallback = OperatorDashboardInsightsComposer::buildOrdersInsights($trSpy);
op_extract_assert_true(
    array_key_exists('today', $ordersFallback) && array_key_exists('trends', $ordersFallback),
    'buildOrdersInsights fallback contains today and trends keys'
);
op_extract_assert_true(
    $ordersFallback['today'] === '--' && $ordersFallback['trends'] === [],
    'buildOrdersInsights fallback returns -- and empty trends on DB error'
);

$partsFallback = OperatorDashboardInsightsComposer::buildPartsInsights();
op_extract_assert_true(
    array_key_exists('count', $partsFallback) && array_key_exists('items', $partsFallback),
    'buildPartsInsights fallback contains count and items keys'
);
op_extract_assert_true(
    $partsFallback['count'] === '--' && $partsFallback['items'] === [],
    'buildPartsInsights fallback returns -- and empty items on DB error'
);

$overstockFallback = OperatorDashboardInsightsComposer::buildOverstockInsights($trSpy);
op_extract_assert_true(
    array_key_exists('count', $overstockFallback) && array_key_exists('items', $overstockFallback),
    'buildOverstockInsights fallback contains count and items keys'
);

$wasteFallback = OperatorDashboardInsightsComposer::buildWasteInsights();
op_extract_assert_true(
    array_key_exists('total', $wasteFallback) && array_key_exists('items', $wasteFallback),
    'buildWasteInsights fallback contains total and items keys'
);
op_extract_assert_true(
    $wasteFallback['total'] === '--' && $wasteFallback['items'] === [],
    'buildWasteInsights fallback returns -- and empty items on DB error'
);

$zairyoFallback = OperatorDashboardInsightsComposer::buildZairyoInsights();
op_extract_assert_true(
    array_key_exists('count', $zairyoFallback) && array_key_exists('items', $zairyoFallback),
    'buildZairyoInsights fallback contains count and items keys'
);

$dispatchFallback = OperatorDashboardInsightsComposer::buildDispatchInsights($trSpy);
op_extract_assert_true(
    array_key_exists('total', $dispatchFallback) && array_key_exists('items', $dispatchFallback),
    'buildDispatchInsights fallback contains total and items keys'
);

// Verify $tr locale keys are wired in source (invocation requires live DB)
$composerSource = file_get_contents(APP_ROOT . '/apps/Shell/Composers/OperatorDashboardInsightsComposer.php');
$sourceTrKeys = [];
preg_match_all("/\\\$tr\('([^']+)'/", $composerSource, $sourceTrKeys);
$sourceTrKeys = $sourceTrKeys[1];
$expectedTrKeys = [
    'operator.dashboard.period_day',
    'operator.dashboard.period_week',
    'operator.dashboard.period_month',
    'operator.dashboard.period_year',
    'operator.dashboard.days',
    'operator.dashboard.dispatch_delayed',
    'operator.dashboard.dispatch_postponed',
    'operator.dashboard.dispatch_cancelled',
    'operator.dashboard.dispatch_preponned',
];
foreach ($expectedTrKeys as $expectedKey) {
    op_extract_assert_true(
        in_array($expectedKey, $sourceTrKeys, true),
        "locale key {$expectedKey} is wired in source via \$tr() calls"
    );
}
$uniqueSourceTrKeys = array_unique($sourceTrKeys);
$unexpectedKeys = array_diff($uniqueSourceTrKeys, $expectedTrKeys);
op_extract_assert_true(
    $unexpectedKeys === [],
    'no unexpected $tr() locale keys in source; got: ' . implode(', ', $unexpectedKeys)
);

$headerHtml = OperatorHeaderComposer::render($data, $i18n, ['en' => 'English'], 'en', 'usd', ['system-liquid-glass' => 'System']);
$sidebarHtml = OperatorSidebarComposer::render($data);
$avatarHtml = OperatorAvatarMenuComposer::render($data, $i18n, ['en' => 'English'], 'en', 'usd', ['system-liquid-glass' => 'System']);
$scriptHtml = OperatorInteractionScriptComposer::render(
    $data,
    $i18n,
    str_repeat('a', 36),
    'system-liquid-glass',
    ['system-liquid-glass' => 'System'],
    'demo'
);
$dashboardHtml = OperatorDashboardComposer::renderFocusStack(
    $data,
    $i18n,
    [],
    static fn (array $partRow): array => [
        'show_action' => false,
        'action_label' => '',
        'action_url' => '',
        'action_helper' => '',
        'show_status' => false,
        'status_label' => '',
        'status_url' => '',
        'status_helper' => '',
    ],
    static fn (array $panel): string => ''
);
$criticalData = $data;
$criticalData['current_query'] = ['focus' => 'critical'];
$criticalHtml = OperatorDashboardComposer::renderFocusStack(
    $criticalData,
    $i18n,
    [],
    static fn (array $partRow): array => [
        'show_action' => false,
        'action_label' => '',
        'action_url' => '',
        'action_helper' => '',
        'show_status' => false,
        'status_label' => '',
        'status_url' => '',
        'status_helper' => '',
    ],
    static fn (array $panel): string => ''
);

foreach ([
    'headerRouteSearch' => $headerHtml,
    'headerOriginalScanButton' => $headerHtml,
    'operatorSearchResults' => $headerHtml,
    'contextualSidebar' => $sidebarHtml,
    'headerAvatarPanel' => $avatarHtml,
    'avatarBackdrop' => $avatarHtml,
    'OPERATOR_SEARCH_SCOPE' => $scriptHtml,
    "fetch(url, { credentials: 'same-origin'" => $scriptHtml,
    'function createOperatorLaunchCameraScan()' => $scriptHtml,
    'function renderOperatorSearchResults(rawQuery)' => $scriptHtml,
    'mobileActionPanel' => $scriptHtml,
    'headerOriginalScanButton.addEventListener' => $scriptHtml,
    'dashboard-top' => $dashboardHtml,
    'data-dashboard-key="orders"' => $dashboardHtml,
    'dailyOrderModelFilter' => $dashboardHtml,
    'overview-card' => $dashboardHtml,
    'critical-focus' => $criticalHtml,
    'data-critical-key="critical_parts"' => $criticalHtml,
] as $needle => $html) {
    op_extract_assert_contains($html, (string)$needle, $needle . ' remains emitted by extracted composer');
}

echo '[probe] Operator extracted composers: ' . $assertions . '/' . $assertions . " assertions passed\n";

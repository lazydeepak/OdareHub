<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;

final class ManufacturingDashboardBlockService
{
    /**
     * @return array<string,string>
     */
    public static function dashboardBlockCatalog(): array
    {
        return [
            'operational_summary' => (string)t('dashboard.blocks.operational_summary'),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $dashboardItems
     * @param callable(string):string $moduleLabelFromKey
     * @return array<string,mixed>
     */
    public static function prepareOperationalSummary(array $dashboardItems, callable $moduleLabelFromKey): array
    {
        $dashboardModules = [];
        $manufacturingKpi = self::emptyKpi();

        foreach ($dashboardItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $moduleKey = (string)($item['_module_key'] ?? 'operations');
            if (!isset($dashboardModules[$moduleKey])) {
                $dashboardModules[$moduleKey] = [
                    'label' => $moduleLabelFromKey($moduleKey),
                    'items' => [],
                ];
            }
            $dashboardModules[$moduleKey]['items'][] = $item;

            $rows = is_array($item['rows'] ?? null) ? (array)$item['rows'] : [];
            $signals = self::signalsFromRows($rows);
            $kpiBucket = self::kpiBucketFromModuleKey($moduleKey);
            if (isset($manufacturingKpi[$kpiBucket])) {
                foreach (['total', 'active', 'critical', 'completed'] as $signalKey) {
                    $manufacturingKpi[$kpiBucket][$signalKey] += (int)($signals[$signalKey] ?? 0);
                }
            }
        }

        return [
            'dashboardModules' => $dashboardModules,
            'manufacturingKpi' => $manufacturingKpi,
            'kpiCards' => [
                'order' => ['label' => t('admin.dashboard.kpi.order')],
                'plans' => ['label' => t('admin.dashboard.kpi.plans')],
                'processing' => ['label' => t('admin.dashboard.kpi.processing')],
                'dispatch' => ['label' => t('admin.dashboard.kpi.dispatch')],
            ],
            'timelineRows' => self::timelineRows(),
        ];
    }

    public static function kpiBucketFromModuleKey(string $moduleKey): string
    {
        $key = strtolower(trim($moduleKey));

        if ($key !== '' && preg_match('/dispatch/', $key) === 1) {
            return 'dispatch';
        }

        if ($key !== '' && preg_match('/order|demand|coverage/', $key) === 1) {
            return 'order';
        }

        if ($key !== '' && preg_match('/plan/', $key) === 1) {
            return 'plans';
        }

        return 'processing';
    }

    /**
     * @param array<int,array<string,mixed>> $dashboardItems
     * @param array<string,array<string,mixed>> $dashboardModules
     * @param array<int,array<string,mixed>> $streamItems
     * @param array<string,int>|array<int,string> $enabledPluginCardOrder
     * @param callable(string):string $moduleLabelFromKey
     * @return array<string,mixed>
     */
    public static function preparePluginDashboardCharts(
        array $dashboardItems,
        array $dashboardModules,
        array $streamItems,
        array $enabledPluginCardOrder,
        callable $moduleLabelFromKey
    ): array {
        $recentOrderRows = [];
        $recentPartRows = [];
        $recentOrders = [];
        $recentOrderSeen = [];
        $recentPartSeen = [];
        $allOrdersUrl = '/apps/manufacturing/daily-orders';
        $itemLimit = 5;

        foreach ($dashboardItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $moduleKey = (string)($item['_module_key'] ?? 'operations');
            $moduleLabel = $moduleLabelFromKey($moduleKey);
            $itemTitle = trim((string)($item['title'] ?? $item['label'] ?? $moduleLabel));

            foreach (array_values(array_filter((array)($item['toolbar_actions'] ?? []), 'is_array')) as $action) {
                $actionUrl = trim((string)($action['url'] ?? ''));
                $actionLabel = trim((string)($action['label'] ?? ''));
                if ($actionUrl === '' || $actionLabel === '') {
                    continue;
                }

                if (self::kpiBucketFromModuleKey($moduleKey) === 'order' && $allOrdersUrl === '/apps/manufacturing/daily-orders') {
                    $allOrdersUrl = $actionUrl;
                }
            }

            $rows = is_array($item['rows'] ?? null) ? array_values((array)$item['rows']) : [];
            $bucket = self::kpiBucketFromModuleKey($moduleKey);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $statusText = trim((string)($row['status'] ?? $row['state'] ?? ''));
                $statusTone = strtolower(trim((string)($row['status_tone'] ?? 'info')));
                $cells = is_array($row['cells'] ?? null) ? array_values((array)$row['cells']) : [];
                $title = trim((string)($row['title'] ?? ($cells[0] ?? $itemTitle)));
                $subtitle = trim((string)($row['subtitle'] ?? ($cells[1] ?? '')));
                $meta = trim((string)($row['meta'] ?? ($cells[2] ?? '')));
                $url = trim((string)($row['url'] ?? ''));

                $hasSpecificOrderUrl = preg_match('/(order_id=|id=|\/detail|\/orders\/|\/360)/i', $url) === 1;
                if ($bucket === 'order' && (self::looksLikeSpecificOrder($moduleKey, $title, $subtitle, $cells) || $hasSpecificOrderUrl)) {
                    $orderCode = self::orderCodeFromRow($title, $subtitle, $cells);
                    $orderKey = strtolower(($orderCode !== '' ? $orderCode : $title) . '|' . $url);
                    if (!isset($recentOrderSeen[$orderKey])) {
                        $recentOrderSeen[$orderKey] = true;
                        $recentOrderRows[] = [
                            'code' => $orderCode !== '' ? $orderCode : t('admin.dashboard.orders.fallback_item'),
                            'party' => $subtitle !== '' ? $subtitle : $itemTitle,
                            'detail' => $meta !== '' ? $meta : $moduleLabel,
                            'status' => $statusText !== '' ? $statusText : t('admin.dashboard.orders.fallback_status'),
                            'status_tone' => self::normalizedTone($statusTone),
                            'amount' => self::amountLikeFromCells($cells),
                            'url' => $url,
                        ];
                    }
                }

                if (self::looksLikePartStatus($moduleKey, $title, $subtitle, $statusText)) {
                    $partCode = trim($subtitle) !== '' ? $subtitle : trim($title);
                    $partKey = strtolower($partCode . '|' . $url);
                    if (!isset($recentPartSeen[$partKey])) {
                        $recentPartSeen[$partKey] = true;
                        $recentPartRows[] = [
                            'code' => $partCode !== '' ? $partCode : t('admin.dashboard.parts.fallback_item'),
                            'party' => $title !== '' ? $title : $moduleLabel,
                            'detail' => $meta !== '' ? $meta : t('admin.dashboard.parts.fallback_detail'),
                            'status' => $statusText !== '' ? $statusText : t('admin.dashboard.parts.fallback_status'),
                            'status_tone' => self::normalizedTone($statusTone),
                            'amount' => '',
                            'url' => $url,
                        ];
                    }
                }
            }
        }

        if ($recentOrderRows !== []) {
            $recentOrders = array_slice($recentOrderRows, 0, $itemLimit);
        } elseif ($recentPartRows !== []) {
            $recentOrders = array_slice($recentPartRows, 0, $itemLimit);
        }

        return [
            'recentOrders' => $recentOrders,
            'allOrdersUrl' => $allOrdersUrl,
            'dispatchFeaturedItem' => self::dispatchFeaturedItem($dashboardModules),
            'quickLinkCards' => self::quickLinkCards($streamItems, $enabledPluginCardOrder),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $dashboardModules
     * @return array<int,array<string,mixed>>
     */
    public static function preparePrimaryWorkWidgets(array $dashboardModules): array
    {
        $preparedModules = [];
        foreach ($dashboardModules as $moduleKey => $module) {
            $moduleItems = array_values((array)($module['items'] ?? []));
            if ($moduleItems === []) {
                continue;
            }

            $viewTypeRank = [
                'my_work' => 10,
                'operational_queue' => 20,
                'operational' => 30,
                'watchlist' => 40,
                'reference' => 50,
            ];

            usort($moduleItems, static function (array $a, array $b) use ($viewTypeRank): int {
                $scoreCmp = self::moduleItemScore($b) <=> self::moduleItemScore($a);
                if ($scoreCmp !== 0) {
                    return $scoreCmp;
                }

                $aType = strtolower(trim((string)($a['_view_type'] ?? 'operational')));
                $bType = strtolower(trim((string)($b['_view_type'] ?? 'operational')));
                $typeCmp = ($viewTypeRank[$aType] ?? 90) <=> ($viewTypeRank[$bType] ?? 90);
                if ($typeCmp !== 0) {
                    return $typeCmp;
                }

                $priorityCmp = ((int)($a['_priority'] ?? 100)) <=> ((int)($b['_priority'] ?? 100));
                if ($priorityCmp !== 0) {
                    return $priorityCmp;
                }

                return strcmp(
                    strtolower(trim((string)($a['title'] ?? $a['label'] ?? ''))),
                    strtolower(trim((string)($b['title'] ?? $b['label'] ?? '')))
                );
            });

            $filteredItems = [];
            foreach ($moduleItems as $item) {
                if (self::isLowValueItem($item)) {
                    continue;
                }
                $filteredItems[] = $item;
            }

            if ($filteredItems === []) {
                continue;
            }

            $filteredItems = array_slice($filteredItems, 0, 2);

            $moduleScore = 0;
            foreach ($filteredItems as $item) {
                $moduleScore += self::moduleItemScore($item);
            }
            $moduleScore += self::moduleBusinessBoost((string)$moduleKey);

            $preparedModules[] = [
                'key' => (string)$moduleKey,
                'label' => (string)($module['label'] ?? t('admin.dashboard.fallback_operations')),
                'items' => $filteredItems,
                'score' => $moduleScore,
            ];
        }

        usort($preparedModules, static function (array $a, array $b): int {
            $scoreCmp = ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            $rankCmp = self::modulePriorityRankByKey((string)($a['key'] ?? '')) <=> self::modulePriorityRankByKey((string)($b['key'] ?? ''));
            if ($rankCmp !== 0) {
                return $rankCmp;
            }

            return strcmp(
                strtolower(trim((string)($a['label'] ?? ''))),
                strtolower(trim((string)($b['label'] ?? '')))
            );
        });

        $preparedModules = array_slice($preparedModules, 0, 8);
        self::coupleModules($preparedModules, '/production_plans|production_queue|plan/', '/production_entries|processing/');
        self::coupleModules($preparedModules, '/daily_orders|pre_orders|coverage|order/', '/\bqc\b|quality/');

        return $preparedModules;
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function renderOperationalSummary(array $context): string
    {
        return self::renderView(APP_ROOT . '/apps/Manufacturing/Views/admin_blocks/operational_summary.php', $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function renderPluginDashboardCharts(array $context): string
    {
        return self::renderView(APP_ROOT . '/apps/Manufacturing/Views/admin_blocks/plugin_dashboard_charts.php', $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function renderPrimaryWorkWidgets(array $context): string
    {
        return self::renderView(APP_ROOT . '/apps/Manufacturing/Views/admin_blocks/primary_work_widgets.php', $context);
    }

    /**
     * @return array<string,array<string,int>>
     */
    private static function emptyKpi(): array
    {
        return [
            'order' => ['total' => 0, 'active' => 0, 'critical' => 0, 'completed' => 0],
            'plans' => ['total' => 0, 'active' => 0, 'critical' => 0, 'completed' => 0],
            'processing' => ['total' => 0, 'active' => 0, 'critical' => 0, 'completed' => 0],
            'dispatch' => ['total' => 0, 'active' => 0, 'critical' => 0, 'completed' => 0],
        ];
    }

    /**
     * @param array<int,mixed> $rows
     * @return array<string,int>
     */
    private static function signalsFromRows(array $rows): array
    {
        $active = 0;
        $critical = 0;
        $completed = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $statusText = strtolower(trim((string)($row['status'] ?? $row['state'] ?? '')));
            $statusTone = strtolower(trim((string)($row['status_tone'] ?? '')));
            $cells = is_array($row['cells'] ?? null) ? array_values((array)$row['cells']) : [];
            $cellText = strtolower(trim(implode(' ', array_map(static fn($cell): string => trim((string)$cell), $cells))));
            $signalText = trim($statusText . ' ' . $cellText);

            $isCritical = $statusTone === 'danger'
                || preg_match('/\b(critical|blocked|delay|delayed|late|shortage|overdue|risk|hold)\b/', $signalText) === 1;
            $isCompleted = $statusTone === 'success'
                || preg_match('/\b(done|completed|closed|shipped|success|resolved|released|passed)\b/', $signalText) === 1;
            $isActive = !$isCompleted && (
                $statusTone === 'warning'
                || $statusTone === 'info'
                || preg_match('/\b(open|active|in[\s-]?progress|pending|planned|processing|queued|running|ready)\b/', $signalText) === 1
            );

            if ($isCritical) {
                $critical++;
            }
            if ($isCompleted) {
                $completed++;
            }
            if ($isActive) {
                $active++;
            }
        }

        return [
            'total' => count($rows),
            'active' => $active,
            'critical' => $critical,
            'completed' => $completed,
        ];
    }

    /**
     * @param array<int,mixed> $cells
     */
    private static function looksLikeSpecificOrder(string $moduleKey, string $title, string $subtitle, array $cells): bool
    {
        $key = strtolower(trim($moduleKey));
        if (preg_match('/order|daily_orders|pre_orders/', $key) !== 1) {
            return false;
        }

        $haystack = strtolower(trim($title . ' ' . $subtitle . ' ' . implode(' ', array_map(static fn($cell): string => trim((string)$cell), $cells))));
        return preg_match('/(#[a-z0-9\-]{3,}|\b(ord|so|po)[-_]?[0-9]{2,}\b|order\s*(id|no|number)?\s*[:#]\s*[a-z0-9\-]{3,})/i', $haystack) === 1;
    }

    private static function looksLikePartStatus(string $moduleKey, string $title, string $subtitle, string $status): bool
    {
        $key = strtolower(trim($moduleKey));
        if (preg_match('/product|part/', $key) !== 1) {
            return false;
        }

        return trim($status) !== '' && (trim($title) !== '' || trim($subtitle) !== '');
    }

    /**
     * @param array<int,mixed> $cells
     */
    private static function orderCodeFromRow(string $title, string $subtitle, array $cells): string
    {
        if (preg_match('/(#[A-Za-z0-9\-]+)/', $title, $match) === 1) {
            return $match[1];
        }

        $haystack = $title . ' ' . $subtitle . ' ' . implode(' ', array_map(static fn($cell): string => trim((string)$cell), $cells));
        if (preg_match('/\b(ORD[-_ ]?[A-Za-z0-9\-]+)/i', $haystack, $match) === 1) {
            return strtoupper(str_replace(' ', '-', $match[1]));
        }

        return $title;
    }

    /**
     * @param array<int,mixed> $cells
     */
    private static function amountLikeFromCells(array $cells): string
    {
        $amountSource = trim(implode(' ', array_map(static fn($cell): string => trim((string)$cell), $cells)));
        if (preg_match('/([0-9][0-9,\.]{1,12})/', $amountSource, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    private static function normalizedTone(string $tone): string
    {
        return in_array($tone, ['success', 'warning', 'danger', 'info', 'neutral'], true) ? $tone : 'info';
    }

    /**
     * @param array<int,mixed> $rows
     */
    private static function hasPositiveMagnitude(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $valuePool = [];
            $valuePool[] = (string)($row['status'] ?? '');
            $valuePool[] = (string)($row['state'] ?? '');
            $valuePool[] = (string)($row['title'] ?? '');
            $valuePool[] = (string)($row['subtitle'] ?? '');
            $valuePool[] = (string)($row['meta'] ?? '');
            foreach (array_values((array)($row['cells'] ?? [])) as $cell) {
                $valuePool[] = (string)$cell;
            }

            if (preg_match_all('/-?\d+(?:\.\d+)?/', implode(' ', $valuePool), $matches) === 1) {
                foreach ((array)($matches[0] ?? []) as $numericToken) {
                    if (abs((float)$numericToken) > 0.00001) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function moduleItemScore(array $item): int
    {
        $score = 0;
        $viewType = strtolower(trim((string)($item['_view_type'] ?? 'operational')));
        $widgetType = strtolower(trim((string)($item['widget_type'] ?? '')));
        $title = strtolower(trim((string)($item['title'] ?? $item['label'] ?? '')));
        $description = strtolower(trim((string)($item['description'] ?? '')));
        $rows = is_array($item['rows'] ?? null) ? array_values((array)$item['rows']) : [];
        $toolbarActions = array_values(array_filter((array)($item['toolbar_actions'] ?? []), 'is_array'));

        $score += match ($viewType) {
            'my_work' => 60,
            'operational_queue' => 52,
            'operational' => 44,
            'watchlist' => 34,
            'reference' => 8,
            default => 24,
        };

        if (in_array($widgetType, ['queue', 'watchlist'], true)) {
            $score += 14;
        }
        if ($toolbarActions !== []) {
            $score += 12;
        }

        $score += min(16, count($rows) * 2);

        $hasCriticalSignals = false;
        $hasActionableRows = false;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $statusTone = strtolower(trim((string)($row['status_tone'] ?? '')));
            $status = strtolower(trim((string)($row['status'] ?? $row['state'] ?? '')));
            $cells = is_array($row['cells'] ?? null) ? array_values((array)$row['cells']) : [];
            $signalText = strtolower(trim($status . ' ' . implode(' ', array_map(static fn($cell): string => trim((string)$cell), $cells))));

            if ($statusTone === 'danger' || preg_match('/\b(critical|blocked|delay|late|overdue|risk|shortage|hold)\b/', $signalText) === 1) {
                $hasCriticalSignals = true;
            }
            if (trim((string)($row['url'] ?? '')) !== '') {
                $hasActionableRows = true;
            }
        }

        if ($hasCriticalSignals) {
            $score += 26;
        }
        if ($hasActionableRows) {
            $score += 10;
        }

        $looksLikeGenericSnapshot = preg_match('/\bsnapshot\b/', $title) === 1
            && preg_match('/\brole-aware operational volume snapshot\b/', $description) === 1;
        if ($looksLikeGenericSnapshot && !$hasCriticalSignals && !$hasActionableRows) {
            $score -= 24;
            if (!self::hasPositiveMagnitude($rows)) {
                $score -= 18;
            }
        }

        return $score;
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function isLowValueItem(array $item): bool
    {
        $viewType = strtolower(trim((string)($item['_view_type'] ?? 'operational')));
        $title = strtolower(trim((string)($item['title'] ?? $item['label'] ?? '')));
        $description = strtolower(trim((string)($item['description'] ?? '')));
        $rows = is_array($item['rows'] ?? null) ? array_values((array)$item['rows']) : [];
        $toolbarActions = array_values(array_filter((array)($item['toolbar_actions'] ?? []), 'is_array'));
        $toggles = array_values(array_filter((array)($item['toggles'] ?? []), 'is_array'));
        $itemUrl = trim((string)($item['url'] ?? ''));

        if ($viewType === 'reference') {
            return true;
        }

        if ($rows === [] && $toolbarActions === [] && $toggles === [] && $itemUrl === '') {
            return true;
        }

        $looksLikeGenericSnapshot = preg_match('/\bsnapshot\b/', $title) === 1
            && preg_match('/\brole-aware operational volume snapshot\b/', $description) === 1;
        if ($looksLikeGenericSnapshot && $toolbarActions === [] && count($rows) <= 2) {
            return true;
        }

        if ($looksLikeGenericSnapshot && !self::hasPositiveMagnitude($rows) && $toolbarActions === [] && $itemUrl === '') {
            return true;
        }

        return self::moduleItemScore($item) < 28;
    }

    private static function moduleBusinessBoost(string $moduleKey): int
    {
        $key = strtolower(trim($moduleKey));
        if ($key === '') {
            return 0;
        }

        if (preg_match('/dispatch/', $key) === 1) {
            return 18;
        }
        if (preg_match('/\bqc\b|quality/', $key) === 1) {
            return 16;
        }
        if (preg_match('/production_entries|processing/', $key) === 1) {
            return 14;
        }
        if (preg_match('/production_plans|production_queue|plan/', $key) === 1) {
            return 13;
        }
        if (preg_match('/daily_orders|pre_orders|coverage|order/', $key) === 1) {
            return 12;
        }
        if (preg_match('/products|parts|part_machine_map/', $key) === 1) {
            return 10;
        }
        if (preg_match('/machines/', $key) === 1) {
            return 9;
        }
        if (preg_match('/assembly/', $key) === 1) {
            return 8;
        }

        return 0;
    }

    private static function modulePriorityRankByKey(string $moduleKey): int
    {
        $key = strtolower(trim($moduleKey));
        if ($key === '') {
            return 99;
        }

        if (preg_match('/dispatch/', $key) === 1) {
            return 10;
        }
        if (preg_match('/\bqc\b|quality/', $key) === 1) {
            return 20;
        }
        if (preg_match('/production_entries|processing/', $key) === 1) {
            return 30;
        }
        if (preg_match('/production_plans|production_queue|plan/', $key) === 1) {
            return 40;
        }
        if (preg_match('/daily_orders|pre_orders|coverage|order/', $key) === 1) {
            return 50;
        }
        if (preg_match('/products|parts|part_machine_map/', $key) === 1) {
            return 60;
        }
        if (preg_match('/machines/', $key) === 1) {
            return 70;
        }
        if (preg_match('/assembly/', $key) === 1) {
            return 80;
        }

        return 90;
    }

    /**
     * @param array<int,array<string,mixed>> $modules
     */
    private static function coupleModules(array &$modules, string $primaryPattern, string $secondaryPattern): void
    {
        $primaryIndex = null;
        $secondaryIndex = null;
        foreach ($modules as $moduleIndex => $moduleMeta) {
            $moduleKey = strtolower(trim((string)($moduleMeta['key'] ?? '')));
            if ($primaryIndex === null && preg_match($primaryPattern, $moduleKey) === 1) {
                $primaryIndex = $moduleIndex;
            }
            if ($secondaryIndex === null && preg_match($secondaryPattern, $moduleKey) === 1) {
                $secondaryIndex = $moduleIndex;
            }
        }

        if ($primaryIndex === null || $secondaryIndex === null || $primaryIndex === $secondaryIndex) {
            return;
        }

        $primaryItems = array_values((array)($modules[$primaryIndex]['items'] ?? []));
        $secondaryItems = array_values((array)($modules[$secondaryIndex]['items'] ?? []));
        $combinedItems = array_slice(array_merge($primaryItems, $secondaryItems), 0, 2);
        if ($combinedItems === []) {
            return;
        }

        $modules[$primaryIndex]['items'] = $combinedItems;
        array_splice($modules, $secondaryIndex, 1);
    }

    /**
     * @param array<string,array<string,mixed>> $dashboardModules
     * @return array<string,mixed>|null
     */
    private static function dispatchFeaturedItem(array $dashboardModules): ?array
    {
        foreach ($dashboardModules as $dispatchModuleKey => $dispatchModule) {
            if (preg_match('/dispatch/i', (string)$dispatchModuleKey) !== 1) {
                continue;
            }
            $dispatchItems = array_values((array)($dispatchModule['items'] ?? []));
            foreach ($dispatchItems as $dispatchItem) {
                if (is_array($dispatchItem)) {
                    return $dispatchItem;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $streamItems
     * @param array<string,int>|array<int,string> $enabledPluginCardOrder
     * @return array<int,array<string,mixed>>
     */
    private static function quickLinkCards(array $streamItems, array $enabledPluginCardOrder): array
    {
        if ($enabledPluginCardOrder === []) {
            return [];
        }

        $quickLinkCards = [];
        foreach ($streamItems as $streamItem) {
            if (!is_array($streamItem) || strtolower(trim((string)($streamItem['_region'] ?? ''))) !== 'quick_links') {
                continue;
            }
            $quickLinkUrl = trim((string)($streamItem['url'] ?? ''));
            $quickLinkLabel = trim((string)($streamItem['label'] ?? $streamItem['title'] ?? ''));
            if ($quickLinkUrl === '' || $quickLinkLabel === '') {
                continue;
            }
            $quickLinkCards[] = $streamItem;
        }

        return $quickLinkCards;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function renderView(string $viewPath, array $context): string
    {
        if (!is_file($viewPath)) {
            return '';
        }

        $bufferLevel = ob_get_level();
        ob_start();
        try {
            extract($context, EXTR_SKIP);
            include $viewPath;
            return (string)ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function timelineRows(): array
    {
        $timelineRows = [];
        $timelineDate = date('Y-m-d');

        if (!self::tableExists('machines') || !self::tableExists('production_plans') || !self::tableExists('products')) {
            return $timelineRows;
        }

        try {
            $machines = (array)(DB::fetchAll('SELECT id, machine_no, machine_name FROM machines WHERE is_active = 1 ORDER BY machine_no ASC') ?? []);
            $planRows = (array)(DB::fetchAll(
                "SELECT
                    pp.id,
                    pp.machine_id,
                    pp.sequence_no,
                    pp.plan_date,
                    pp.planned_qty,
                    pp.status,
                    pp.product_id,
                    p.parts_number,
                    p.parts_name,
                    COALESCE(pe_agg.good_qty, 0) AS good_qty
                 FROM production_plans pp
                 INNER JOIN products p ON p.id = pp.product_id
                 LEFT JOIN (
                   SELECT machine_id, product_id, production_date, SUM(good_qty) AS good_qty
                   FROM production_entries
                   WHERE production_date = ?
                   GROUP BY machine_id, product_id, production_date
                 ) pe_agg ON pe_agg.machine_id = pp.machine_id
                          AND pe_agg.product_id = pp.product_id
                          AND pe_agg.production_date = pp.plan_date
                 WHERE pp.plan_date = ?
                 ORDER BY pp.machine_id ASC, pp.sequence_no ASC, pp.id ASC",
                [$timelineDate, $timelineDate]
            ) ?? []);

            $planByMachine = [];
            foreach ($planRows as $planRow) {
                if (!is_array($planRow)) {
                    continue;
                }
                $machineId = (int)($planRow['machine_id'] ?? 0);
                if ($machineId <= 0) {
                    continue;
                }
                $planByMachine[$machineId][] = $planRow;
            }

            foreach ($machines as $machine) {
                if (!is_array($machine)) {
                    continue;
                }

                $machineId = (int)($machine['id'] ?? 0);
                if ($machineId <= 0) {
                    continue;
                }

                $queueRows = array_values((array)($planByMachine[$machineId] ?? []));
                if ($queueRows === []) {
                    continue;
                }

                $currentIndex = self::currentPlanIndex($queueRows);
                $currentPlan = ($currentIndex !== null && isset($queueRows[$currentIndex])) ? $queueRows[$currentIndex] : null;
                $prevPlan = ($currentIndex !== null && $currentIndex > 0 && isset($queueRows[$currentIndex - 1])) ? $queueRows[$currentIndex - 1] : null;
                $nextPlan = ($currentIndex !== null && isset($queueRows[$currentIndex + 1])) ? $queueRows[$currentIndex + 1] : null;

                $currentStatus = trim((string)($currentPlan['status'] ?? t('admin.dashboard.timeline.no_plan_status')));
                $currentPlanned = (float)($currentPlan['planned_qty'] ?? 0.0);
                $currentGood = (float)($currentPlan['good_qty'] ?? 0.0);
                $currentProgress = $currentPlanned > 0.0 ? max(0.0, min(100.0, round(($currentGood / $currentPlanned) * 100, 1))) : 0.0;

                $statusTone = 'neutral';
                if ($currentPlan !== null) {
                    if ($currentProgress >= 100.0) {
                        $statusTone = 'success';
                    } elseif ($currentProgress > 0.0) {
                        $statusTone = 'info';
                    } else {
                        $statusTone = 'warning';
                    }
                }

                $machineNo = trim((string)($machine['machine_no'] ?? ''));
                $machineName = trim((string)($machine['machine_name'] ?? ''));
                $timelineRows[] = [
                    'stage' => $machineNo !== '' ? $machineNo : t('nav.machines'),
                    'title' => $machineName !== '' ? $machineName : ($machineNo !== '' ? $machineNo : sprintf((string)t('admin.dashboard.timeline.machine_format'), $machineId)),
                    'subtitle' => '',
                    'meta' => '',
                    'past_plan' => $prevPlan !== null ? self::formatPlanSummary($prevPlan) : (string)t('admin.dashboard.timeline.no_previous'),
                    'current_plan' => self::formatPlanSummary($currentPlan),
                    'next_plan' => $nextPlan !== null ? self::formatPlanSummary($nextPlan) : (string)t('admin.dashboard.timeline.no_next'),
                    'status' => $currentPlan !== null ? ($currentStatus !== '' ? strtoupper($currentStatus) : strtoupper((string)t('admin.dashboard.timeline.fallback_status'))) : strtoupper((string)t('admin.dashboard.timeline.no_plan_status')),
                    'status_tone' => $statusTone,
                    'progress' => $currentProgress,
                    'url' => '/apps/manufacturing/production-queue?date=' . rawurlencode($timelineDate) . '&machine_id=' . $machineId,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $timelineRows;
    }

    private static function tableExists(string $table): bool
    {
        try {
            $escaped = DB::conn()->real_escape_string($table);
            return DB::fetchOne("SHOW TABLES LIKE '{$escaped}'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $queueRows
     */
    private static function currentPlanIndex(array $queueRows): ?int
    {
        foreach ($queueRows as $index => $queueRow) {
            $plannedQty = (float)($queueRow['planned_qty'] ?? 0.0);
            $goodQty = (float)($queueRow['good_qty'] ?? 0.0);
            $status = strtolower(trim((string)($queueRow['status'] ?? 'planned')));
            $isDone = in_array($status, ['done', 'completed', 'closed', 'dispatched'], true) || ($plannedQty > 0.0 && $goodQty >= $plannedQty);
            if (!$isDone) {
                return (int)$index;
            }
        }

        return $queueRows !== [] ? count($queueRows) - 1 : null;
    }

    /**
     * @param array<string,mixed>|null $plan
     */
    private static function formatPlanSummary(?array $plan): string
    {
        if (!is_array($plan)) {
            return (string)t('admin.dashboard.timeline.no_current');
        }

        $partNo = trim((string)($plan['parts_number'] ?? ''));
        $partName = trim((string)($plan['parts_name'] ?? ''));
        $plannedQty = (float)($plan['planned_qty'] ?? 0.0);
        $seqNo = (int)($plan['sequence_no'] ?? 0);

        $partLabel = trim($partNo . ' ' . $partName);
        if ($partLabel === '') {
            $partLabel = (string)t('admin.dashboard.parts.fallback_item');
        }

        return ($seqNo > 0 ? 'S' . $seqNo . ' ' : '') . $partLabel . ' x' . number_format($plannedQty, 0, '.', ',');
    }
}

<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorSearchComposer
{
    /**
     * @param array<string,mixed> $data
     * @return array<int,array{route:string,label:string}>
     */
    public static function buildRouteIndex(array $data, string $username, string $workspaceLabel, string $accountLabel): array
    {
        $prefix = '/u/' . rawurlencode($username);
        $indexed = [];

        $register = static function (array &$bucket, string $prefixRoute, string $route, string $label = ''): void {
            $trimmedRoute = trim($route);
            if ($trimmedRoute === '') {
                return;
            }

            $path = trim((string)parse_url($trimmedRoute, PHP_URL_PATH));
            if ($path === '') {
                return;
            }

            $isInPrefix = $path === $prefixRoute || strpos($path, $prefixRoute . '/') === 0;
            if (!$isInPrefix) {
                return;
            }

            $dedupeKey = strtolower($trimmedRoute);
            if (isset($bucket[$dedupeKey])) {
                return;
            }

            $bucket[$dedupeKey] = [
                'route' => $trimmedRoute,
                'label' => trim($label),
            ];
        };

        $register($indexed, $prefix, (string)($data['home_url'] ?? ''), $workspaceLabel);
        $register($indexed, $prefix, (string)($data['my_account_url'] ?? ''), $accountLabel);

        foreach ((array)($data['contextual_sidebar']['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            foreach ((array)($section['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $register(
                    $indexed,
                    $prefix,
                    (string)($item['route'] ?? ''),
                    (string)($item['label'] ?? '')
                );
            }
        }

        return array_values($indexed);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,array{route:string,label:string}> $routeIndex
     * @return array<int,array<string,string>>
     */
    public static function buildEntityIndex(array $data, array $routeIndex, string $username, string $partFallbackLabel): array
    {
        $partsRoute = self::resolvePartsRoute($routeIndex, $username);
        $partsDetailRoute = self::resolvePartsDetailRoute($username);
        $rows = (array)($data['parts_table']['rows'] ?? []);
        $indexed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $partId = (int)($row['id'] ?? 0);
            $partName = trim((string)($row['parts_name'] ?? ''));
            $partNumber = trim((string)($row['parts_number'] ?? ''));
            $model = trim((string)($row['model'] ?? ''));

            if ($partId <= 0 && $partName === '' && $partNumber === '' && $model === '') {
                continue;
            }

            $label = $partName !== ''
                ? $partName
                : ($partNumber !== '' ? $partNumber : strtr($partFallbackLabel, ['{id}' => (string)$partId]));

            $searchParts = array_filter([
                $partName,
                $partNumber,
                $model,
                $partId > 0 ? (string)$partId : '',
            ], static fn ($value): bool => trim((string)$value) !== '');

            $metaParts = array_filter([
                $partNumber,
                $model,
            ], static fn ($value): bool => trim((string)$value) !== '');

            $indexed[] = [
                'kind' => 'entity',
                'entity_type' => 'part',
                'entity_id' => $partId > 0 ? (string)$partId : '',
                'entity_name' => $partName,
                'entity_number' => $partNumber,
                'route' => $partId > 0
                    ? ($partsDetailRoute . '?part_id=' . rawurlencode((string)$partId))
                    : $partsDetailRoute,
                'label' => $label,
                'text' => trim(implode(' ', $searchParts)),
                'meta' => $metaParts !== [] ? implode(' | ', $metaParts) : $partsRoute,
            ];
        }

        return $indexed;
    }

    /**
     * @param array<int,array{route:string,label:string}> $routeIndex
     */
    private static function resolvePartsRoute(array $routeIndex, string $username): string
    {
        $prefix = '/u/' . rawurlencode($username);

        foreach ($routeIndex as $item) {
            if (!is_array($item)) {
                continue;
            }

            $route = trim((string)($item['route'] ?? ''));
            if ($route === '') {
                continue;
            }

            $path = trim((string)parse_url($route, PHP_URL_PATH));
            if ($path === '') {
                continue;
            }

            $inPrefix = $path === $prefix || strpos($path, $prefix . '/') === 0;
            if (!$inPrefix) {
                continue;
            }

            if (preg_match('#(^|/)parts(?:/|$)#i', $path) === 1) {
                return $route;
            }
        }

        return $prefix . '/parts';
    }

    private static function resolvePartsDetailRoute(string $username): string
    {
        return '/u/' . rawurlencode($username) . '/parts/detail';
    }
}

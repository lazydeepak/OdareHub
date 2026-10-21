<?php
declare(strict_types=1);

namespace Apps\Procurement\Services;

require_once __DIR__ . '/ProcurementOverviewService.php';

final class OperatorSurfaceContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public static function contribute(array $request): array
    {
        if (strtolower(trim((string)($request['surface'] ?? ''))) !== 'operator') {
            return [];
        }

        $region = strtolower(trim((string)($request['region'] ?? '')));
        $context = is_array($request['context'] ?? null) ? $request['context'] : [];
        if (!self::isAuthorized($context)) {
            return [];
        }

        return match ($region) {
            'sidebar' => [
                'sections' => [[
                    'title' => self::tr('proc.operator.sidebar.title', 'Procurement'),
                    'items' => [[
                        'icon' => '🧾',
                        'label' => self::tr('proc.operator.sidebar.requests', 'My Requests'),
                        'route' => '/u/{user}/procurement',
                        'badge' => null,
                    ]],
                ]],
            ],
            'focus_views' => [
                'view_map' => [
                    'procurement' => APP_ROOT . '/apps/Procurement/Views/operator/requests.php',
                ],
            ],
            'focus_labels' => [
                'labels' => [
                    'procurement' => self::tr('proc.operator.focus.requests', 'My Procurement Requests'),
                ],
            ],
            default => [],
        };
    }

    /** @param array<string,mixed> $context */
    private static function isAuthorized(array $context): bool
    {
        $apps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])
        );
        if (!in_array('procurement', $apps, true)) {
            return false;
        }

        $permissions = $context['permissions'] ?? [];
        if (is_string($permissions)) {
            $permissions = preg_split('/\s*,\s*/', strtolower(trim($permissions))) ?: [];
        }
        $permissions = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)$permissions
        );

        return in_array('procurement.view', $permissions, true);
    }

    private static function tr(string $key, string $fallback): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        return $fallback;
    }
}

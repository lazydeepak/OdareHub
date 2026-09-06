<?php
declare(strict_types=1);

namespace Apps\Hospitality\Services;

final class OperatorContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public static function contribute(array $request): array
    {
        $surface = strtolower(trim((string)($request['surface'] ?? '')));
        $region = strtolower(trim((string)($request['region'] ?? '')));
        $context = is_array($request['context'] ?? null) ? (array)$request['context'] : [];

        if ($surface !== 'operator' || !self::isHospitalityAssigned($context)) {
            return [];
        }

        return match ($region) {
            'sidebar' => [
                'sections' => [
                    [
                        'title' => self::tr('hospitality.operator.sidebar', 'Hospitality'),
                        'items' => [
                            [
                                'icon' => 'hotel',
                                'label' => self::tr('hospitality.operator.nav.focus', 'Hospitality Focus'),
                                'route' => '/u/{user}/hospitality',
                                'badge' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'focus_views' => [
                'view_map' => [
                    'hospitality' => APP_ROOT . '/apps/Hospitality/Views/operator/hospitality.php',
                ],
            ],
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function isHospitalityAssigned(array $context): bool
    {
        $apps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])
        );

        return in_array('hospitality', $apps, true);
    }

    /**
     * @param array<string,string|int|float> $params
     */
    private static function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        if ($params === []) {
            return $fallback;
        }
        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }
        return strtr($fallback, $replace);
    }
}

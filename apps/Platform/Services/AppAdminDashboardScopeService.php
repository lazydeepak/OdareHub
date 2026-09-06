<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

/** Resolves the application boundary for the App Admin compatibility dashboard. */
final class AppAdminDashboardScopeService
{
    /** @param array<string,mixed> $context @return array<int,string> */
    public static function assignedApps(array $context): array
    {
        $apps = self::list($context['active_assigned_apps'] ?? []);
        if ($apps === []) {
            $apps = self::list($context['assigned_apps'] ?? []);
        }
        if ($apps === []) {
            $defaultApp = self::normalize((string)($context['default_app'] ?? ''));
            if ($defaultApp !== '' && $defaultApp !== 'platform') {
                $apps[] = $defaultApp;
            }
        }

        return array_values(array_filter(array_unique($apps), static fn(string $app): bool => $app !== 'platform'));
    }

    /** @param array<string,mixed> $context */
    public static function allowsApp(array $context, string $appKey): bool
    {
        $appKey = self::normalize($appKey);
        return $appKey !== '' && in_array($appKey, self::assignedApps($context), true);
    }

    /** @return array<int,string> */
    private static function list(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string)$value);
        $resolved = [];
        foreach ($values as $item) {
            $app = self::normalize((string)$item);
            if ($app !== '') {
                $resolved[] = $app;
            }
        }
        return array_values(array_unique($resolved));
    }

    private static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}

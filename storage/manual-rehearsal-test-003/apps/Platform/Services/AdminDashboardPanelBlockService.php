<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use Plugins\Base\Controllers\RoleDashboardsController;

final class AdminDashboardPanelBlockService
{
    /**
     * @param array<string,mixed> $ctx
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public static function prepare(array $ctx, array $user): array
    {
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
        $dashboardTypes = match ($authorityRole) {
            // Unified Admin owns the Platform Admin home and status surface.
            'platform_admin' => [],
            'app_admin' => ['app_admin'],
            default => [],
        };
        $panels = [];
        foreach ($dashboardTypes as $dashboardType) {
            $payload = RoleDashboardsController::dashboardPayloadForContext($ctx, $dashboardType, $user);
            if ($payload === []) {
                continue;
            }
            $panels[] = self::composePanel($payload, $dashboardType);
        }

        return $panels;
    }

    /**
     * Pure parity adapter used by Unified Admin and deterministic probes.
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function composePanel(array $payload, string $dashboardType = 'app_admin'): array
    {
        return [
            'key' => $dashboardType,
            'title' => (string)($payload['title'] ?? ''),
            'subtitle' => (string)($payload['subtitle'] ?? ''),
            'url' => '',
            'accent' => '#5b8899',
            'cards' => array_values(array_filter((array)($payload['cards'] ?? []), 'is_array')),
            'quick_links' => array_values(array_filter(
                (array)($payload['quick_links'] ?? []),
                static fn($link): bool => is_array($link) && trim((string)($link['url'] ?? '')) !== ''
            )),
            'sections' => array_values(array_filter((array)($payload['sections'] ?? []), 'is_array')),
            'placeholders' => array_values(array_filter((array)($payload['placeholders'] ?? []), 'is_string')),
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function render(array $context): string
    {
        $viewPath = APP_ROOT . '/apps/Platform/Views/admin_blocks/admin_dashboard_panels.php';
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

}

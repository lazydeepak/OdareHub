<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use Plugins\MaterialManagement\Services\MaterialAccessService;
use Plugins\MaterialManagement\Services\MaterialManagementService;

/**
 * Isolated `/me` Material Management aggregate contribution.
 *
 * This deliberately does not call the composite HostSurfaceContributionService,
 * ModuleSurfaceWidgetFactory, or any unrelated module registry. The source is
 * the existing MaterialAccessService + summaryReadOnly() contract only.
 */
final class HostMaterialSummaryContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(array $request): array
    {
        if (trim((string)($request['surface'] ?? '')) !== 'me'
            || trim((string)($request['region'] ?? '')) !== 'summary_cards'
        ) {
            return [];
        }

        $context = is_array($request['context'] ?? null) ? (array)$request['context'] : [];
        $user = is_array($context['user'] ?? null) ? (array)$context['user'] : [];
        if ((int)($user['id'] ?? 0) <= 0) {
            return [];
        }

        if (!class_exists(MaterialAccessService::class) || !class_exists(MaterialManagementService::class)) {
            return [];
        }

        try {
            if (!function_exists('platform_user_access_policy_contract')) {
                return [];
            }
            $policy = platform_user_access_policy_contract();
            if (!is_object($policy) || !$policy->canAccessModule($user, 'materials')) {
                return [];
            }

            if (!MaterialAccessService::canAccessAny()) {
                return [];
            }

            $summary = MaterialManagementService::summaryReadOnly();
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($summary) || $summary === []) {
            return [];
        }

        $shortageRisk = (int)($summary['shortage_risk_count'] ?? 0);
        $incomingDelay = (int)($summary['incoming_delay_count'] ?? 0);
        $overflowRisk = (int)($summary['storage_overflow_count'] ?? 0);

        return [[
            'widget_key' => 'materials_summary',
            'surface_key' => 'me',
            'view_kind' => 'kpi',
            'widget_type' => ($shortageRisk > 0 || $incomingDelay > 0) ? 'alert' : 'informative',
            'placement_zone' => 'dashboard_summary',
            'interaction_profiles' => ['leader', 'admin', 'read_only'],
            'supports_empty_state' => true,
            'supports_clickthrough' => true,
            'title' => self::translate('mfg.nav.materials', 'Materials'),
            'value' => $shortageRisk,
            'meta' => self::translate('mfg.portal.material_risk.shortage_risk_materials', 'Shortage risk materials'),
            'url' => '/apps/manufacturing/materials',
            'tone' => ($shortageRisk > 0 || $incomingDelay > 0) ? 'warn' : 'info',
            'priority' => 32,
            'material_summary' => [
                'shortage_risk_count' => $shortageRisk,
                'incoming_delay_count' => $incomingDelay,
                'storage_overflow_count' => $overflowRisk,
            ],
        ]];
    }

    private static function translate(string $key, string $fallback): string
    {
        if (function_exists('t')) {
            $value = (string)t($key);
            if ($value !== '' && $value !== $key) {
                return $value;
            }
        }

        return $fallback;
    }
}

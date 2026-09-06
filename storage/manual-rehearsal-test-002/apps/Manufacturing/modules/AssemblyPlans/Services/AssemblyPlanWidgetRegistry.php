<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyPlans\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class AssemblyPlanWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        $items = [];

        if ($region === 'summary_cards') {
            $openPlans = self::openPlanCount();
            $items[] = [
                'widget_key' => 'assembly_plans',
                'view_kind' => 'kpi',
                'widget_type' => $openPlans > 0 ? 'queue' : 'informative',
                'placement_zone' => 'primary_work',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
                'surface_key' => 'me',
                'supports_empty_state' => true,
                'supports_clickthrough' => true,
                'title' => t('nav.assembly_plans'),
                'value' => $openPlans,
                'meta' => t('common.open_work'),
                'url' => '/apps/manufacturing/assembly-plans',
                'tone' => $openPlans > 0 ? 'warn' : 'success',
                'priority' => 14,
                'weight' => 14,
            ];
        }

        return array_merge($items, ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'assembly_plans',
            'title' => 'Assembly Plans',
            'url' => '/apps/manufacturing/assembly-plans',
            'report_url' => '/apps/manufacturing/assembly-plans/report',
            'export_url' => '/apps/manufacturing/assembly-plans/export',
            'table' => 'mfg_part_demands',
            'priority' => 27,
            'include_summary' => false,
        ]));
    }

    private static function openPlanCount(): int
    {
        if (!self::tableExists('mfg_part_demands')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM mfg_part_demands
             WHERE demand_type = 'assembly'
               AND LOWER(COALESCE(status,'')) IN ('calculated','adjusted','approved')"
        );

        return (int)($row['total'] ?? 0);
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

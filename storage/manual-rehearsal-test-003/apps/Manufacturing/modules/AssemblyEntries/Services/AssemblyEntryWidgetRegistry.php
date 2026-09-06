<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyEntries\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class AssemblyEntryWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        $items = [];

        if ($region === 'summary_cards') {
            $openEntries = self::openEntryCount();
            $items[] = [
                'widget_key' => 'assembly_entries',
                'view_kind' => 'kpi',
                'widget_type' => $openEntries > 0 ? 'alert' : 'informative',
                'placement_zone' => 'primary_work',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
                'surface_key' => 'me',
                'supports_empty_state' => true,
                'supports_clickthrough' => true,
                'title' => t('nav.assembly_queue'),
                'value' => $openEntries,
                'meta' => t('common.open_work'),
                'url' => '/apps/manufacturing/assembly-queue',
                'tone' => $openEntries > 0 ? 'warn' : 'success',
                'priority' => 15,
                'weight' => 15,
            ];
        }

        return array_merge($items, ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'assembly_entries',
            'title' => 'Assembly Queue',
            'url' => '/apps/manufacturing/assembly-queue',
            'report_url' => '/assembly-entries/report',
            'export_url' => '/assembly-entries/export',
            'table' => 'mfg_assembly_entries',
            'priority' => 28,
            'include_summary' => false,
        ]));
    }

    private static function openEntryCount(): int
    {
        if (!self::tableExists('mfg_assembly_entries')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM mfg_assembly_entries
             WHERE LOWER(COALESCE(status,'')) NOT IN ('completed','approved','cancelled','canceled')"
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

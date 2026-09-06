<?php
declare(strict_types=1);

namespace Plugins\ProductionQueue\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class ProductionQueueWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'production_queue',
            'title' => 'Production Queue',
            'url' => '/apps/manufacturing/production-queue',
            'form_action' => '/apps/manufacturing/production-queue',
            'report_url' => '/production-queue/report',
            'export_url' => '/production-queue/export',
            'table' => 'production_entries',
            'priority' => 25,
            'include_summary' => true,
            'worker_title' => 'Production Queue · Live Lanes',
            'worker_description' => 'Today\'s machine queues with progress and remaining load.',
            'worker_rows' => static fn (): array => self::workerQueueRows(),
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function workerQueueRows(): array
    {
        if (!self::tableExists('production_plans') || !self::tableExists('products') || !self::tableExists('machines')) {
            return [];
        }

        $today = date('Y-m-d');

        $rows = DB::fetchAll(
            "SELECT
                pp.id,
                pp.machine_id,
                pp.planned_qty,
                pp.status,
                m.machine_no,
                m.machine_name,
                p.parts_name,
                p.parts_number,
                COALESCE(pe.good_qty, 0) AS good_qty,
                COALESCE(pe.rejected_qty, 0) AS rejected_qty,
                COALESCE(pe.entry_count, 0) AS entry_count
            FROM production_plans pp
            JOIN machines m ON m.id = pp.machine_id
            JOIN products p ON p.id = pp.product_id
            LEFT JOIN (
                SELECT
                    machine_id,
                    product_id,
                    production_date,
                    SUM(good_qty) AS good_qty,
                    SUM(rejected_qty) AS rejected_qty,
                    COUNT(*) AS entry_count
                FROM production_entries
                WHERE production_date = ?
                GROUP BY machine_id, product_id, production_date
            ) pe ON pe.machine_id = pp.machine_id
                 AND pe.product_id = pp.product_id
                 AND pe.production_date = pp.plan_date
            WHERE pp.plan_date = ?
            ORDER BY m.machine_no ASC, pp.sequence_no ASC, pp.id ASC
            LIMIT 8",
            [$today, $today]
        );

        $mapped = [];
        foreach ($rows as $row) {
            $plannedQty = max(0.0, (float)($row['planned_qty'] ?? 0.0));
            $goodQty = max(0.0, (float)($row['good_qty'] ?? 0.0));
            $rejectedQty = max(0.0, (float)($row['rejected_qty'] ?? 0.0));
            $remainingQty = max(0.0, $plannedQty - $goodQty);
            $progressPct = $plannedQty > 0 ? min(100.0, round(($goodQty / $plannedQty) * 100, 1)) : 0.0;

            $machineLabel = trim((string)($row['machine_no'] ?? ''));
            $machineName = trim((string)($row['machine_name'] ?? ''));
            $partName = trim((string)($row['parts_name'] ?? ''));
            $partNumber = trim((string)($row['parts_number'] ?? ''));

            $title = $machineLabel !== '' ? $machineLabel : ('Machine #' . (int)($row['machine_id'] ?? 0));
            if ($machineName !== '') {
                $title .= ' - ' . $machineName;
            }

            $subtitle = $partName;
            if ($partNumber !== '') {
                $subtitle .= ($subtitle !== '' ? ' ' : '') . $partNumber;
            }

            $mapped[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'meta' => 'Plan #' . (int)($row['id'] ?? 0),
                'status' => self::normalizeStatus((string)($row['status'] ?? '')),
                'progress_pct' => $progressPct,
                'planned_qty' => round($plannedQty, 0),
                'good_qty' => round($goodQty, 0),
                'rejected_qty' => round($rejectedQty, 0),
                'remaining_qty' => round($remainingQty, 0),
                'entry_count' => (int)($row['entry_count'] ?? 0),
                'url' => '/apps/manufacturing/production-queue?date=' . rawurlencode($today) . '&machine_id=' . (int)($row['machine_id'] ?? 0),
                'cells' => [
                    $title,
                    $subtitle,
                    number_format($progressPct, 1) . '%',
                ],
            ];
        }

        return $mapped;
    }

    private static function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return match ($normalized) {
            'in progress', 'in_progress' => 'In Progress',
            'completed', 'complete' => 'Completed',
            'planned' => 'Planned',
            'released' => 'Released',
            default => $status !== '' ? ucfirst($status) : 'Queued',
        };
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

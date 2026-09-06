<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyPlans;

use App\Core\Auth;
use App\Core\DB;
use Apps\Manufacturing\Services\DemandEngineService;
use Apps\Manufacturing\Services\StageTransitionService;

final class AssemblyPlanService
{
    /**
     * @return array{ready:bool,message:string,missing:array<int,string>}
     */
    public static function availabilityStatus(): array
    {
        self::ensureSchema();

        $requiredTables = ['products', 'daily_orders', 'pre_orders', 'mfg_part_demands', 'mfg_assembly_entries'];
        $missing = [];
        foreach ($requiredTables as $table) {
            if (!self::tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing === []) {
            return [
                'ready' => true,
                'message' => '',
                'missing' => [],
            ];
        }

        return [
            'ready' => false,
            'message' => 'Assembly Plans are unavailable until the manufacturing schema is fully initialized. Missing tables: ' . implode(', ', $missing),
            'missing' => $missing,
        ];
    }

    public static function requireAvailability(): void
    {
        $status = self::availabilityStatus();
        if ($status['ready']) {
            return;
        }

        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Assembly Plans unavailable</title></head><body class="u-style-244abacef3">';
        echo '<h1 class="u-style-d462248a40">Assembly Plans unavailable</h1>';
        echo '<p>' . htmlspecialchars((string)$status['message'], ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><a href="/apps/manufacturing">Return to Manufacturing dashboard</a></p>';
        echo '</body></html>';
        exit;
    }

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        DemandEngineService::ensureSchema();
        StageTransitionService::ensureSchema();

        DB::query(
            "CREATE TABLE IF NOT EXISTS mfg_assembly_entries (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                assembly_plan_id BIGINT NULL,
                product_id INT NOT NULL,
                source_type VARCHAR(40) NULL,
                source_id BIGINT NULL,
                assembly_date DATE NOT NULL,
                planned_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                completed_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                rejected_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                status ENUM('draft','in_progress','completed','approved','blocked','cancelled') NOT NULL DEFAULT 'draft',
                note TEXT NULL,
                completed_by VARCHAR(190) NULL,
                approved_by VARCHAR(190) NULL,
                approved_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_mfg_assembly_plan (assembly_plan_id),
                KEY idx_mfg_assembly_product_date (product_id, assembly_date),
                KEY idx_mfg_assembly_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        $done = true;
    }

    public static function listPlans(string $date = '', string $status = '', int $productId = 0, bool $onlyOpen = false): array
    {
        self::ensureSchema();

        $sql = "SELECT d.id,
                       d.product_id,
                       d.demand_date,
                       d.system_qty,
                       d.adjusted_qty,
                       d.approved_qty,
                       d.status,
                       d.adjustment_note,
                       d.source_summary_json,
                       p.parts_name,
                       p.parts_number,
                       p.supply_mode,
                       p.requires_ipm_qc,
                       p.requires_assembly,
                       p.dispatch_as_is,
                       ROUND(COALESCE(ae.planned_qty, 0), 2) AS planned_qty,
                       ROUND(COALESCE(ae.completed_qty, 0), 2) AS completed_qty,
                       ROUND(COALESCE(ae.rejected_qty, 0), 2) AS rejected_qty,
                       COALESCE(ae.last_status, 'draft') AS execution_status,
                       msr_qc.override_status AS qc_override_status,
                       msr_qc.released_qty AS qc_released_qty,
                       msr_asm.override_status AS asm_override_status,
                       msr_asm.released_qty AS asm_released_qty
                FROM mfg_part_demands d
                INNER JOIN products p ON p.id=d.product_id
                LEFT JOIN (
                    SELECT assembly_plan_id,
                           ROUND(COALESCE(SUM(planned_qty),0), 2) AS planned_qty,
                           ROUND(COALESCE(SUM(completed_qty),0), 2) AS completed_qty,
                           ROUND(COALESCE(SUM(rejected_qty),0), 2) AS rejected_qty,
                           SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY id DESC), ',', 1) AS last_status
                    FROM mfg_assembly_entries
                    WHERE LOWER(status) <> 'cancelled'
                    GROUP BY assembly_plan_id
                ) ae ON ae.assembly_plan_id=d.id
                LEFT JOIN mfg_stage_readiness msr_qc
                    ON msr_qc.product_id=d.product_id
                    AND msr_qc.ref_date=d.demand_date
                    AND msr_qc.stage='qc'
                LEFT JOIN mfg_stage_readiness msr_asm
                    ON msr_asm.product_id=d.product_id
                    AND msr_asm.ref_date=d.demand_date
                    AND msr_asm.stage='assembly'
                WHERE d.demand_type='assembly'";
        $params = [];

        if ($date !== '') {
            $sql .= ' AND d.demand_date = ?';
            $params[] = $date;
        }
        if ($productId > 0) {
            $sql .= ' AND d.product_id = ?';
            $params[] = $productId;
        }
        if ($status !== '' && in_array($status, ['calculated', 'adjusted', 'approved'], true)) {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY d.demand_date DESC, p.parts_name ASC LIMIT 1200';
        $rows = DB::fetchAll($sql, $params);

        $dates = [];
        foreach ($rows as $r) {
            $d = (string)($r['demand_date'] ?? '');
            if ($d !== '') {
                $dates[$d] = true;
            }
        }
        $byDate = [];
        foreach (array_keys($dates) as $d) {
            $byDate[$d] = StageTransitionService::computeForDate($d);
        }

        foreach ($rows as &$row) {
            $effectiveQty = self::effectiveQty($row);
            $completedQty = round((float)($row['completed_qty'] ?? 0), 2);
            $remainingQty = round(max(0.0, $effectiveQty - $completedQty), 2);
            if ($onlyOpen && $remainingQty <= 0.0) {
                $row['__skip__'] = 1;
                continue;
            }

            $row['effective_qty'] = $effectiveQty;
            $row['remaining_qty'] = $remainingQty;
            $row['source_demand'] = self::sourceDemandLabel((string)($row['source_summary_json'] ?? ''));
            $row['qc_released'] = (float)($row['qc_released_qty'] ?? 0) > 0
                || (string)($row['qc_override_status'] ?? '') === 'released';
            $row['assembly_released'] = (float)($row['asm_released_qty'] ?? 0) > 0
                || (string)($row['asm_override_status'] ?? '') === 'released';

            $date = (string)$row['demand_date'];
            $pid = (int)$row['product_id'];
            $stage = $byDate[$date][$pid] ?? null;
            if (is_array($stage)) {
                $row['next_stage'] = $stage['next_stage'] ?? null;
                $row['block_reason'] = implode('; ', (array)($stage['block_reasons'] ?? []));
                $row['dispatch_stage_status'] = (string)($stage['stages']['dispatch']['status'] ?? 'pending');
            } else {
                $row['next_stage'] = null;
                $row['block_reason'] = '';
                $row['dispatch_stage_status'] = 'pending';
            }
        }
        unset($row);

        if (!$onlyOpen) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn(array $r): bool => empty($r['__skip__'])));
    }

    public static function planDetail(int $id): ?array
    {
        self::ensureSchema();

        $row = DB::fetchOne(
            "SELECT d.*, p.parts_name, p.parts_number, p.supply_mode,
                    p.requires_ipm_qc, p.requires_assembly, p.dispatch_as_is,
                    p.activity_type, p.fulfillment_mode
             FROM mfg_part_demands d
             INNER JOIN products p ON p.id=d.product_id
             WHERE d.id=? AND d.demand_type='assembly' LIMIT 1",
            [$id]
        );

        if (!$row) {
            return null;
        }

        $productId = (int)$row['product_id'];
        $date = (string)$row['demand_date'];

        $entries = DB::fetchAll(
            "SELECT * FROM mfg_assembly_entries
             WHERE assembly_plan_id=?
             ORDER BY id DESC LIMIT 300",
            [$id]
        );

        $agg = DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(planned_qty),0),2) AS planned_qty,
                    ROUND(COALESCE(SUM(completed_qty),0),2) AS completed_qty,
                    ROUND(COALESCE(SUM(rejected_qty),0),2) AS rejected_qty
             FROM mfg_assembly_entries
             WHERE assembly_plan_id=? AND LOWER(status) <> 'cancelled'",
            [$id]
        ) ?: [];

        $effectiveQty = self::effectiveQty($row);
        $completedQty = round((float)($agg['completed_qty'] ?? 0), 2);
        $remainingQty = round(max(0.0, $effectiveQty - $completedQty), 2);

        $linkedDailyOrders = DB::fetchAll(
            "SELECT id, order_date, required_date, qty, status
             FROM daily_orders
             WHERE product_id=?
               AND COALESCE(required_date, order_date, CURRENT_DATE())=?
             ORDER BY id DESC LIMIT 100",
            [$productId, $date]
        );

        $linkedPreOrders = DB::fetchAll(
            "SELECT id, required_date, planned_qty, balance_qty, planning_priority
             FROM pre_orders
             WHERE product_id=?
               AND COALESCE(required_date, CURRENT_DATE())=?
             ORDER BY id DESC LIMIT 100",
            [$productId, $date]
        );

        $stageMap = StageTransitionService::computeForDate($date);
        $stage = $stageMap[$productId] ?? null;

        return [
            'plan' => $row,
            'entries' => $entries,
            'metrics' => [
                'effective_qty' => $effectiveQty,
                'planned_qty' => round((float)($agg['planned_qty'] ?? 0), 2),
                'completed_qty' => $completedQty,
                'rejected_qty' => round((float)($agg['rejected_qty'] ?? 0), 2),
                'remaining_qty' => $remainingQty,
                'source_demand' => self::sourceDemandLabel((string)($row['source_summary_json'] ?? '')),
            ],
            'linked_daily_orders' => $linkedDailyOrders,
            'linked_pre_orders' => $linkedPreOrders,
            'stage_readiness' => $stage,
        ];
    }

    public static function updatePlan(int $id, float $adjustedQty, string $note): void
    {
        self::ensureSchema();
        $row = DB::fetchOne('SELECT * FROM mfg_part_demands WHERE id=? AND demand_type=\'assembly\' LIMIT 1', [$id]);
        if (!$row) {
            throw new \RuntimeException('Assembly plan not found.');
        }

        DemandEngineService::adjustDemand($id, $adjustedQty, $note);
    }

    public static function approvePlan(int $id, ?float $approvedQty): void
    {
        self::ensureSchema();
        $row = DB::fetchOne('SELECT * FROM mfg_part_demands WHERE id=? AND demand_type=\'assembly\' LIMIT 1', [$id]);
        if (!$row) {
            throw new \RuntimeException('Assembly plan not found.');
        }

        DemandEngineService::approveDemand($id, $approvedQty);
    }

    public static function addExecutionEntry(array $payload): int
    {
        self::ensureSchema();

        $assemblyPlanId = (int)($payload['assembly_plan_id'] ?? 0);
        $productId = (int)($payload['product_id'] ?? 0);
        $assemblyDate = trim((string)($payload['assembly_date'] ?? ''));
        $plannedQty = round(max(0.0, (float)($payload['planned_qty'] ?? 0)), 2);
        $completedQty = round(max(0.0, (float)($payload['completed_qty'] ?? 0)), 2);
        $rejectedQty = round(max(0.0, (float)($payload['rejected_qty'] ?? 0)), 2);
        $status = strtolower(trim((string)($payload['status'] ?? 'draft')));
        $note = trim((string)($payload['note'] ?? ''));
        $sourceType = trim((string)($payload['source_type'] ?? 'assembly_plan'));
        $sourceId = (int)($payload['source_id'] ?? $assemblyPlanId);

        if ($assemblyPlanId <= 0 || $productId <= 0 || $assemblyDate === '') {
            throw new \InvalidArgumentException('Missing assembly entry inputs.');
        }

        $validStatus = ['draft', 'in_progress', 'completed', 'approved', 'blocked', 'cancelled'];
        if (!in_array($status, $validStatus, true)) {
            $status = 'draft';
        }

        DB::query(
            'INSERT INTO mfg_assembly_entries (assembly_plan_id, product_id, source_type, source_id, assembly_date, planned_qty, completed_qty, rejected_qty, status, note, completed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $assemblyPlanId,
                $productId,
                $sourceType !== '' ? $sourceType : null,
                $sourceId > 0 ? $sourceId : null,
                $assemblyDate,
                $plannedQty,
                $completedQty,
                $rejectedQty,
                $status,
                $note !== '' ? $note : null,
                self::currentUserLabel(),
            ]
        );

        return (int)DB::conn()->insert_id;
    }

    public static function approveExecutionEntry(int $id): void
    {
        self::ensureSchema();
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid entry id.');
        }

        DB::query(
            "UPDATE mfg_assembly_entries
             SET status='approved', approved_by=?, approved_at=NOW(), updated_at=NOW()
             WHERE id=?",
            [self::currentUserLabel(), $id]
        );
    }

    public static function completedQtyByProductDate(int $productId, string $date): float
    {
        self::ensureSchema();

        return (float)(DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(completed_qty),0),2) AS qty
             FROM mfg_assembly_entries
             WHERE product_id=?
               AND assembly_date=?
               AND LOWER(status) <> 'cancelled'",
            [$productId, $date]
        )['qty'] ?? 0.0);
    }

    private static function effectiveQty(array $row): float
    {
        if (isset($row['approved_qty']) && $row['approved_qty'] !== null && (string)$row['approved_qty'] !== '') {
            return round((float)$row['approved_qty'], 2);
        }
        if (isset($row['adjusted_qty']) && $row['adjusted_qty'] !== null && (string)$row['adjusted_qty'] !== '') {
            return round((float)$row['adjusted_qty'], 2);
        }
        return round((float)($row['system_qty'] ?? 0), 2);
    }

    private static function sourceDemandLabel(string $sourceSummaryJson): string
    {
        $decoded = json_decode($sourceSummaryJson, true);
        if (!is_array($decoded)) {
            return 'Unknown';
        }

        $pre = round((float)($decoded['pre_orders_qty'] ?? 0), 2);
        $daily = round((float)($decoded['daily_orders_qty'] ?? 0), 2);
        return 'Pre ' . number_format($pre, 2) . ' + Daily ' . number_format($daily, 2);
    }

    private static function currentUserLabel(): string
    {
        $user = Auth::user();
        if (!is_array($user)) {
            return 'system';
        }
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'user#' . (string)($user['id'] ?? '0');
    }

    private static function tableExists(string $table): bool
    {
        return DB::fetchOne(
            'SELECT 1 AS present
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?
             LIMIT 1',
            [$table]
        ) !== null;
    }
}

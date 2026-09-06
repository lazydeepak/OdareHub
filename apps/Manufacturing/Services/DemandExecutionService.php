<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\AssemblyPlanService;
use Apps\Manufacturing\Services\StageTransitionService;

final class DemandExecutionService
{
    public static function productionDemandMapByDate(string $date): array
    {
        DemandEngineService::ensureSchema();

        $rows = DB::fetchAll(
            "SELECT d.*, p.supply_mode, p.requires_ipm_qc, p.requires_assembly, p.dispatch_as_is, p.fulfillment_mode, p.activity_type
             FROM mfg_part_demands d
             INNER JOIN products p ON p.id=d.product_id
             WHERE d.demand_type='production' AND d.demand_date=?",
            [$date]
        );

        $producedRows = DB::fetchAll(
            'SELECT product_id, ROUND(SUM(good_qty),2) AS produced_good_qty FROM production_entries WHERE production_date=? GROUP BY product_id',
            [$date]
        );
        $producedMap = [];
        foreach ($producedRows as $pr) {
            $producedMap[(int)$pr['product_id']] = round((float)$pr['produced_good_qty'], 2);
        }

        $qcPassRows = DB::fetchAll(
            "SELECT product_id, ROUND(SUM(pass_qty),2) AS pass_qty
             FROM qc_entries
             WHERE COALESCE(DATE(updated_at), DATE(created_at), CURRENT_DATE())=?
             GROUP BY product_id",
            [$date]
        );
        $qcPassMap = [];
        foreach ($qcPassRows as $qr) {
            $qcPassMap[(int)$qr['product_id']] = round((float)$qr['pass_qty'], 2);
        }

        $map = [];
        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];
            $effectiveQty = self::effectiveDemandQty($row);
            $producedQty = (float)($producedMap[$productId] ?? 0.0);
            $source = self::decodeSourceSummary((string)($row['source_summary_json'] ?? ''));

            $blockedReasons = [];
            if ((string)($row['supply_mode'] ?? 'in_house') === 'third_party') {
                $blockedReasons[] = 'Third-party source (not in-house production lane)';
            }
            if ((string)($row['activity_type'] ?? 'active') === 'passive') {
                $blockedReasons[] = 'Passive/replacement part';
            }
            if ((int)($row['dispatch_as_is'] ?? 0) === 1 && (int)($row['requires_assembly'] ?? 0) === 0) {
                $blockedReasons[] = 'Dispatch-as-is part (bypass production where possible)';
            }
            if ((string)($row['status'] ?? 'calculated') !== 'approved') {
                $blockedReasons[] = 'Demand not admin-approved yet';
            }

            $map[$productId] = [
                'demand_id' => (int)$row['id'],
                'system_qty' => round((float)$row['system_qty'], 2),
                'adjusted_qty' => isset($row['adjusted_qty']) ? round((float)$row['adjusted_qty'], 2) : null,
                'approved_qty' => isset($row['approved_qty']) ? round((float)$row['approved_qty'], 2) : null,
                'effective_qty' => $effectiveQty,
                'remaining_qty' => round(max(0.0, $effectiveQty - $producedQty), 2),
                'produced_good_qty' => $producedQty,
                'qc_pass_qty' => round((float)($qcPassMap[$productId] ?? 0.0), 2),
                'status' => (string)($row['status'] ?? 'calculated'),
                'source_pre_qty' => (float)($source['pre_orders_qty'] ?? 0.0),
                'source_daily_qty' => (float)($source['daily_orders_qty'] ?? 0.0),
                'blocked' => !empty($blockedReasons),
                'blocked_reasons' => $blockedReasons,
                'supply_mode' => (string)($row['supply_mode'] ?? 'in_house'),
                'requires_ipm_qc' => (int)($row['requires_ipm_qc'] ?? 0),
                'requires_assembly' => (int)($row['requires_assembly'] ?? 0),
                'dispatch_as_is' => (int)($row['dispatch_as_is'] ?? 0),
                'fulfillment_mode' => (string)($row['fulfillment_mode'] ?? 'company_to_destination'),
                'activity_type' => (string)($row['activity_type'] ?? 'active'),
            ];
        }

        return $map;
    }

    public static function qcDemandRows(string $fromDate = '', string $toDate = ''): array
    {
        DemandEngineService::ensureSchema();
        StageTransitionService::ensureSchema();

        $sql = "SELECT d.*, p.parts_name, p.parts_number, p.supply_mode,
                    p.requires_assembly, p.dispatch_as_is, p.fulfillment_mode, p.activity_type,
                    msr_prod.released_qty   AS prod_released_qty,
                    msr_prod.released_by    AS prod_released_by,
                    msr_prod.released_at    AS prod_released_at,
                    msr_prod.override_status AS prod_override_status,
                    msr_qc.released_qty     AS qc_released_qty,
                    msr_qc.override_status  AS qc_override_status
                FROM mfg_part_demands d
                INNER JOIN products p ON p.id=d.product_id
                LEFT JOIN mfg_stage_readiness msr_prod
                    ON msr_prod.product_id=d.product_id
                    AND msr_prod.ref_date=d.demand_date
                    AND msr_prod.stage='production'
                LEFT JOIN mfg_stage_readiness msr_qc
                    ON msr_qc.product_id=d.product_id
                    AND msr_qc.ref_date=d.demand_date
                    AND msr_qc.stage='qc'
                WHERE d.demand_type='qc' AND p.requires_ipm_qc=1";
        $params = [];
        if ($fromDate !== '') {
            $sql .= ' AND d.demand_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND d.demand_date <= ?';
            $params[] = $toDate;
        }
        $sql .= ' ORDER BY d.demand_date DESC, p.parts_name ASC LIMIT 1200';

        $rows = DB::fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $effective = self::effectiveDemandQty($row);
            $passQty = self::qcPassQty((int)$row['product_id'], (string)$row['demand_date']);
            $checkedQty = self::qcCheckedQty((int)$row['product_id'], (string)$row['demand_date']);
            $failQty = round(max(0.0, $checkedQty - $passQty), 2);
            $row['effective_qty'] = $effective;
            $row['pass_qty'] = $passQty;
            $row['checked_qty'] = $checkedQty;
            $row['fail_qty'] = $failQty;
            $row['open_qc_workload'] = round(max(0.0, $effective - $passQty), 2);
            $row['is_passive'] = ((string)($row['activity_type'] ?? 'active') === 'passive') ? 1 : 0;
            // Upstream production release status for QC
            $row['production_released'] = (float)($row['prod_released_qty'] ?? 0) > 0
                || (string)($row['prod_override_status'] ?? '') === 'released';
            $row['next_stage'] = self::nextStageForLane($row, 'qc');
            // QC release status (has it been explicitly released downstream?)
            $row['qc_explicitly_released'] = (float)($row['qc_released_qty'] ?? 0) > 0
                || (string)($row['qc_override_status'] ?? '') === 'released';
        }
        unset($row);

        self::attachStageContext($rows, 'qc');
        return $rows;
    }

    public static function assemblyDemandRows(string $fromDate = '', string $toDate = ''): array
    {
        DemandEngineService::ensureSchema();
        StageTransitionService::ensureSchema();
        AssemblyPlanService::ensureSchema();

        $sql = "SELECT d.*, p.parts_name, p.parts_number, p.supply_mode,
                    p.requires_ipm_qc, p.dispatch_as_is, p.fulfillment_mode, p.activity_type,
                    msr_prod.released_qty  AS prod_released_qty,
                    msr_prod.released_by   AS prod_released_by,
                    msr_prod.released_at   AS prod_released_at,
                    msr_prod.override_status AS prod_override_status,
                    msr_asm.released_qty   AS asm_released_qty,
                    msr_asm.override_status AS asm_override_status
                FROM mfg_part_demands d
                INNER JOIN products p ON p.id=d.product_id
                LEFT JOIN mfg_stage_readiness msr_prod
                    ON msr_prod.product_id=d.product_id
                    AND msr_prod.ref_date=d.demand_date
                    AND msr_prod.stage='production'
                LEFT JOIN mfg_stage_readiness msr_asm
                    ON msr_asm.product_id=d.product_id
                    AND msr_asm.ref_date=d.demand_date
                    AND msr_asm.stage='assembly'
                WHERE d.demand_type='assembly' AND p.requires_assembly=1";
        $params = [];
        if ($fromDate !== '') {
            $sql .= ' AND d.demand_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND d.demand_date <= ?';
            $params[] = $toDate;
        }
        $sql .= ' ORDER BY d.demand_date DESC, p.parts_name ASC LIMIT 1200';

        $rows = DB::fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $effective = self::effectiveDemandQty($row);
            $assemblyCompletedQty = self::assemblyCompletedQty((int)$row['product_id'], (string)$row['demand_date']);
            $dispatchCompletedQty = self::dispatchCompletedQty((int)$row['product_id'], (string)$row['demand_date']);
            $effectiveCompletedQty = $assemblyCompletedQty > 0 ? $assemblyCompletedQty : $dispatchCompletedQty;
            $row['effective_qty'] = $effective;
            $row['assembly_completed_qty'] = $assemblyCompletedQty;
            $row['dispatch_completed_qty'] = $dispatchCompletedQty;
            $row['effective_completed_qty'] = $effectiveCompletedQty;
            $row['completion_source'] = $assemblyCompletedQty > 0 ? 'assembly_entries' : 'dispatch_proxy';
            $row['open_assembly_workload'] = round(max(0.0, $effective - $effectiveCompletedQty), 2);
            $row['is_passive'] = ((string)($row['activity_type'] ?? 'active') === 'passive') ? 1 : 0;
            $row['production_released'] = (float)($row['prod_released_qty'] ?? 0) > 0
                || (string)($row['prod_override_status'] ?? '') === 'released';
            $row['production_released_by'] = (string)($row['prod_released_by'] ?? '');
            $row['production_released_at'] = (string)($row['prod_released_at'] ?? '');
            // Assembly-level explicit release status
            $row['asm_explicitly_released'] = (float)($row['asm_released_qty'] ?? 0) > 0
                || (string)($row['asm_override_status'] ?? '') === 'released';
            $row['next_stage'] = self::nextStageForLane($row, 'assembly');
        }
        unset($row);

        self::attachStageContext($rows, 'assembly');
        return $rows;
    }

    public static function pressureSummary(string $fromDate = '', string $toDate = ''): array
    {
        $prod = self::sumDemandByType('production', $fromDate, $toDate);
        $qc = self::sumDemandByType('qc', $fromDate, $toDate);
        $assembly = self::sumDemandByType('assembly', $fromDate, $toDate);

        $readyDispatch = (float)(DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(dispatchable_qty),0),2) AS qty
             FROM dispatch_entries
             WHERE LOWER(COALESCE(completion_status, 'draft')) IN ('ready','prepared')"
        )['qty'] ?? 0.0);

        $blockedDispatch = (float)(DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(dispatchable_qty),0),2) AS qty
             FROM dispatch_entries
             WHERE LOWER(COALESCE(completion_status, 'draft')) IN ('draft')"
        )['qty'] ?? 0.0);

        $thirdPartyFlow = (float)(DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(dispatchable_qty),0),2) AS qty
             FROM dispatch_entries
             WHERE source_type='third_party'"
        )['qty'] ?? 0.0);

        return [
            'production' => $prod,
            'qc' => $qc,
            'assembly' => $assembly,
            'dispatch_ready_qty' => $readyDispatch,
            'dispatch_blocked_qty' => $blockedDispatch,
            'third_party_flow_qty' => $thirdPartyFlow,
        ];
    }

    private static function sumDemandByType(string $type, string $fromDate, string $toDate): array
    {
        $sql = "SELECT
                    ROUND(COALESCE(SUM(system_qty),0),2) AS system_qty,
                    ROUND(COALESCE(SUM(COALESCE(adjusted_qty, system_qty)),0),2) AS adjusted_qty,
                    ROUND(COALESCE(SUM(COALESCE(approved_qty, 0)),0),2) AS approved_qty,
                    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_rows,
                    SUM(CASE WHEN status<>'approved' THEN 1 ELSE 0 END) AS pending_rows
                FROM mfg_part_demands
                WHERE demand_type=?";
        $params = [$type];
        if ($fromDate !== '') {
            $sql .= ' AND demand_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND demand_date <= ?';
            $params[] = $toDate;
        }

        $row = DB::fetchOne($sql, $params) ?: [];
        return [
            'system_qty' => (float)($row['system_qty'] ?? 0.0),
            'adjusted_qty' => (float)($row['adjusted_qty'] ?? 0.0),
            'approved_qty' => (float)($row['approved_qty'] ?? 0.0),
            'approved_rows' => (int)($row['approved_rows'] ?? 0),
            'pending_rows' => (int)($row['pending_rows'] ?? 0),
        ];
    }

    private static function qcPassQty(int $productId, string $date): float
    {
        return (float)(DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(pass_qty),0),2) AS qty
             FROM qc_entries
             WHERE product_id=? AND COALESCE(DATE(updated_at), DATE(created_at), CURRENT_DATE())=?",
            [$productId, $date]
        )['qty'] ?? 0.0);
    }

    private static function qcCheckedQty(int $productId, string $date): float
    {
        return (float)(DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(checked_qty),0),2) AS qty
             FROM qc_entries
             WHERE product_id=? AND COALESCE(DATE(updated_at), DATE(created_at), CURRENT_DATE())=?",
            [$productId, $date]
        )['qty'] ?? 0.0);
    }

    private static function dispatchCompletedQty(int $productId, string $date): float
    {
        return (float)(DB::fetchOne(
            "SELECT ROUND(COALESCE(SUM(dispatchable_qty),0),2) AS qty
             FROM dispatch_entries
             WHERE product_id=?
               AND dispatch_date=?
               AND LOWER(COALESCE(completion_status, 'draft'))='completed'",
            [$productId, $date]
        )['qty'] ?? 0.0);
    }

    private static function assemblyCompletedQty(int $productId, string $date): float
    {
        return AssemblyPlanService::completedQtyByProductDate($productId, $date);
    }

    private static function effectiveDemandQty(array $row): float
    {
        if (isset($row['approved_qty']) && $row['approved_qty'] !== null && (string)$row['approved_qty'] !== '') {
            return round((float)$row['approved_qty'], 2);
        }
        if (isset($row['adjusted_qty']) && $row['adjusted_qty'] !== null && (string)$row['adjusted_qty'] !== '') {
            return round((float)$row['adjusted_qty'], 2);
        }
        return round((float)($row['system_qty'] ?? 0), 2);
    }

    private static function decodeSourceSummary(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Adds cross-stage runtime hints to queue rows to reduce operator guesswork.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    private static function attachStageContext(array &$rows, string $lane): void
    {
        if (empty($rows)) {
            return;
        }

        $byDate = [];
        foreach ($rows as $idx => $row) {
            $date = (string)($row['demand_date'] ?? '');
            if ($date === '') {
                continue;
            }
            $byDate[$date][] = $idx;
        }

        foreach ($byDate as $date => $indexes) {
            $dateMap = StageTransitionService::computeForDate($date);

            foreach ($indexes as $idx) {
                $row = $rows[$idx];
                $productId = (int)($row['product_id'] ?? 0);
                $r = $dateMap[$productId] ?? null;
                if (!is_array($r)) {
                    continue;
                }

                $nextStage = (string)($r['next_stage'] ?? '');
                $blockReason = implode('; ', (array)($r['block_reasons'] ?? []));
                $upstreamStage = self::upstreamStageForLane($row, $lane);

                $rows[$idx]['next_stage_runtime'] = $nextStage !== '' ? $nextStage : null;
                $rows[$idx]['block_reason'] = $blockReason;
                $rows[$idx]['upstream_stage_key'] = $upstreamStage;
                $rows[$idx]['upstream_stage_status'] = $upstreamStage !== null
                    ? (string)($r['stages'][$upstreamStage]['status'] ?? 'pending')
                    : 'not_applicable';
                $rows[$idx]['upstream_stage_pct'] = $upstreamStage !== null
                    ? (float)($r['stages'][$upstreamStage]['pct'] ?? 0.0)
                    : 100.0;

                $targetStage = self::nextStageForLane($row, $lane);
                $rows[$idx]['next_stage'] = $targetStage;
                if ($targetStage !== null) {
                    $rows[$idx]['downstream_stage_status'] = (string)($r['stages'][$targetStage]['status'] ?? 'pending');
                    $rows[$idx]['downstream_releasable_qty'] = round((float)($r['stages'][$targetStage]['releasable_qty'] ?? 0.0), 2);
                    $rows[$idx]['downstream_next_action'] = (string)($r['stages'][$targetStage]['next_allowed_action'] ?? 'wait_upstream');
                    $rows[$idx]['downstream_block_reason'] = (string)($r['stages'][$targetStage]['blocked_reason'] ?? '');
                    $rows[$idx]['downstream_can_release'] = in_array($targetStage, (array)($r['can_release_to'] ?? []), true);
                } else {
                    $rows[$idx]['downstream_stage_status'] = 'complete';
                    $rows[$idx]['downstream_releasable_qty'] = 0.0;
                    $rows[$idx]['downstream_next_action'] = 'none';
                    $rows[$idx]['downstream_block_reason'] = '';
                    $rows[$idx]['downstream_can_release'] = false;
                }

                if ($blockReason !== '') {
                    $rows[$idx]['workflow_note'] = 'Blocked: ' . $blockReason;
                } elseif ($targetStage !== null && $nextStage === $targetStage) {
                    $rows[$idx]['workflow_note'] = 'Ready for ' . self::stageLabelForDisplay($targetStage);
                } elseif ($nextStage === '') {
                    $rows[$idx]['workflow_note'] = 'Downstream complete';
                } else {
                    $rows[$idx]['workflow_note'] = 'Awaiting ' . self::stageLabelForDisplay((string)$nextStage);
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function nextStageForLane(array $row, string $lane): ?string
    {
        $path = StageTransitionService::stagePathForProduct($row);
        $idx = array_search($lane, $path, true);
        if ($idx === false) {
            return null;
        }

        return $path[$idx + 1] ?? null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function upstreamStageForLane(array $row, string $lane): ?string
    {
        $path = StageTransitionService::stagePathForProduct($row);
        $idx = array_search($lane, $path, true);
        if ($idx === false || $idx === 0) {
            return null;
        }

        return $path[$idx - 1] ?? null;
    }

    private static function stageLabelForDisplay(string $stage): string
    {
        return match (strtolower(trim($stage))) {
            'packaging' => 'Preparation',
            'qc' => 'QC',
            default => ucfirst($stage),
        };
    }
}

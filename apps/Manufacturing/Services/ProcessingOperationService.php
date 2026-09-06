<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\Auth;
use App\Core\DB;
use Plugins\MaterialManagement\Services\MaterialManagementService;

require_once APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialManagementService.php';

final class ProcessingOperationService
{
    /**
     * @return array<string,mixed>
     */
    public static function build(string $date, ?array $user = null): array
    {
        $today = date('Y-m-d');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $today;
        $scope = self::scopeForUser($user);

        $coverageRows = self::coverageRows($date, $scope['part_ids']);
        $assemblyPlans = self::assemblyPlans($date, $scope['part_ids']);
        $qcPlans = self::qcPlans($date, $scope['part_ids']);

        return [
            'today' => $today,
            'date' => $date,
            'coverage_rows' => $coverageRows,
            'assembly_plans' => $assemblyPlans,
            'qc_plans' => $qcPlans,
            'summary' => [
                'coverage_count' => count($coverageRows),
                'assembly_count' => count($assemblyPlans),
                'qc_count' => count(array_filter($qcPlans, static fn (array $row): bool => (float)($row['remaining_qty'] ?? 0) > 0.0)),
            ],
            'quick_links' => [
                ['label' => 'Assembly Queue', 'url' => '/apps/manufacturing/assembly-queue?from_date=' . urlencode($date) . '&to_date=' . urlencode($date)],
                ['label' => 'QC Queue', 'url' => '/apps/manufacturing/qc-queue?from_date=' . urlencode($date) . '&to_date=' . urlencode($date)],
                ['label' => 'Assembly Plans', 'url' => '/apps/manufacturing/assembly-plans?date=' . urlencode($date) . '&only_open=1'],
                ['label' => 'QC Plans', 'url' => '/qc-plans?plan_date=' . urlencode($date)],
                ['label' => 'Stage Board', 'url' => '/apps/manufacturing/stage-board?date=' . urlencode($date)],
            ],
        ];
    }

    public static function updateAssemblyProgress(array $input, ?array $user = null): void
    {
        AssemblyPlanService::ensureSchema();

        $planId = (int)($input['assembly_plan_id'] ?? 0);
        $completedQty = round(max(0.0, (float)($input['completed_qty'] ?? 0)), 2);
        if ($planId <= 0) {
            throw new \RuntimeException('Assembly plan is required.');
        }

        $plan = DB::fetchOne(
            "SELECT id, product_id, demand_date,
                    COALESCE(approved_qty, adjusted_qty, system_qty, 0) AS effective_qty
             FROM mfg_part_demands
             WHERE id = ? AND demand_type = 'assembly'
             LIMIT 1",
            [$planId]
        );
        if (!$plan) {
            throw new \RuntimeException('Assembly plan not found.');
        }

        $planDate = (string)($plan['demand_date'] ?? date('Y-m-d'));
        $targetQty = round(max(0.0, (float)($plan['effective_qty'] ?? 0)), 2);
        $status = $completedQty <= 0
            ? 'draft'
            : ($targetQty > 0 && $completedQty >= $targetQty ? 'completed' : 'in_progress');
        $note = trim((string)($input['note'] ?? ''));
        $userLabel = self::currentUserLabel($user);

        $existing = DB::fetchOne(
            "SELECT id
             FROM mfg_assembly_entries
             WHERE assembly_plan_id = ?
               AND assembly_date = ?
               AND LOWER(status) <> 'cancelled'
             ORDER BY id DESC
             LIMIT 1",
            [$planId, $planDate]
        );

        if ($existing) {
            DB::query(
                "UPDATE mfg_assembly_entries
                 SET planned_qty = ?, completed_qty = ?, status = ?, note = ?, completed_by = ?, updated_at = NOW()
                 WHERE id = ?",
                [$targetQty, $completedQty, $status, $note !== '' ? $note : null, $userLabel, (int)$existing['id']]
            );
            return;
        }

        DB::query(
            "INSERT INTO mfg_assembly_entries
                (assembly_plan_id, product_id, source_type, source_id, assembly_date, planned_qty, completed_qty, rejected_qty, status, note, completed_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $planId,
                (int)($plan['product_id'] ?? 0),
                'processing_operation',
                $planId,
                $planDate,
                $targetQty,
                $completedQty,
                0,
                $status,
                $note !== '' ? $note : null,
                $userLabel,
            ]
        );
    }

    public static function updateQcProgress(array $input, ?array $user = null): void
    {
        $planId = (int)($input['qc_plan_id'] ?? 0);
        $checkedQty = round(max(0.0, (float)($input['checked_qty'] ?? 0)), 2);
        if ($planId <= 0) {
            throw new \RuntimeException('QC plan is required.');
        }

        $plan = DB::fetchOne(
            "SELECT id, plan_date, product_id, daily_order_id, planned_qty
             FROM qc_plans
             WHERE id = ?
             LIMIT 1",
            [$planId]
        );
        if (!$plan) {
            throw new \RuntimeException('QC plan not found.');
        }

        $planDate = (string)($plan['plan_date'] ?? date('Y-m-d'));
        $targetQty = round(max(0.0, (float)($plan['planned_qty'] ?? 0)), 2);
        $entryStatus = $checkedQty > 0 && $targetQty > 0 && $checkedQty >= $targetQty ? 'Approved' : 'Open';
        $planStatus = $checkedQty > 0 && $targetQty > 0 && $checkedQty >= $targetQty ? 'Completed' : 'Open';
        $note = trim((string)($input['note'] ?? ''));
        $anchorTs = $planDate . ' 12:00:00';
        $existing = DB::fetchOne(
            "SELECT id
             FROM qc_entries
             WHERE qc_plan_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$planId]
        );

        if ($existing) {
            DB::query(
                "UPDATE qc_entries
                 SET checked_qty = ?, pass_qty = ?, fail_qty = 0, status = ?, remarks = ?, updated_at = ?
                 WHERE id = ?",
                [$checkedQty, $checkedQty, $entryStatus, $note !== '' ? $note : null, $anchorTs, (int)$existing['id']]
            );
        } else {
            DB::query(
                "INSERT INTO qc_entries
                    (qc_plan_id, daily_order_id, production_plan_id, product_id, qc_type, checked_qty, pass_qty, fail_qty, status, remarks, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $planId,
                    (int)($plan['daily_order_id'] ?? 0) > 0 ? (int)$plan['daily_order_id'] : null,
                    null,
                    (int)($plan['product_id'] ?? 0),
                    'Final',
                    $checkedQty,
                    $checkedQty,
                    0,
                    $entryStatus,
                    $note !== '' ? $note : null,
                    $anchorTs,
                    $anchorTs,
                ]
            );
        }

        DB::query(
            "UPDATE qc_plans
             SET status = ?, updated_at = ?
             WHERE id = ?",
            [$planStatus, $anchorTs, $planId]
        );
    }

    /**
     * @param array<int,int> $scopePartIds
     * @return array<int,array<string,mixed>>
     */
    private static function coverageRows(string $date, array $scopePartIds = []): array
    {
        $dailyOrders = self::dailyOrderDemandRows($date, $scopePartIds);
        if ($dailyOrders === []) {
            return [];
        }

        $stageMap = StageTransitionService::computeForDate($date);
        $materialMap = [];
        foreach (MaterialManagementService::partStatusRowsForProduction($date) as $materialRow) {
            $materialMap[(int)($materialRow['product_id'] ?? 0)] = $materialRow;
        }
        $assemblyMap = self::assemblyPlanSummaryMap($date, $scopePartIds);
        $qcMap = self::qcPlanSummaryMap($date, $scopePartIds);

        $rows = [];
        foreach ($dailyOrders as $order) {
            $productId = (int)($order['product_id'] ?? 0);
            $stage = $stageMap[$productId] ?? [];
            $stageStates = (array)($stage['stages'] ?? []);
            $assemblyState = (array)($stageStates['assembly'] ?? []);
            $qcState = (array)($stageStates['qc'] ?? []);
            $material = $materialMap[$productId] ?? [];
            $assembly = $assemblyMap[$productId] ?? ['plan_id' => 0, 'target_qty' => 0.0, 'completed_qty' => 0.0];
            $qc = $qcMap[$productId] ?? ['plan_id' => 0, 'target_qty' => 0.0, 'checked_qty' => 0.0];

            $requiredQty = round((float)($order['required_qty'] ?? 0), 2);
            $focusStage = 'assembly';
            $targetQty = $requiredQty;
            $completedQty = round((float)($assembly['completed_qty'] ?? 0), 2);
            $openUrl = '/apps/manufacturing/assembly-queue?from_date=' . urlencode($date) . '&to_date=' . urlencode($date);
            $openLabel = 'Open Assembly';
            $detailUrl = (int)($assembly['plan_id'] ?? 0) > 0
                ? '/apps/manufacturing/assembly-plans/detail?id=' . (int)$assembly['plan_id']
                : '/apps/manufacturing/assembly-plans?date=' . urlencode($date) . '&product_id=' . $productId;

            if ((int)($order['requires_assembly'] ?? 0) !== 1 || (($stage['next_stage'] ?? '') === 'qc' && (int)($order['requires_ipm_qc'] ?? 0) === 1)) {
                $focusStage = 'qc';
                $targetQty = max($requiredQty, round((float)($qc['target_qty'] ?? 0), 2));
                $completedQty = round((float)($qc['checked_qty'] ?? 0), 2);
                $openUrl = '/apps/manufacturing/qc-queue?from_date=' . urlencode($date) . '&to_date=' . urlencode($date);
                $openLabel = 'Open QC';
                $detailUrl = (int)($qc['plan_id'] ?? 0) > 0
                    ? '/qc-plans/edit?id=' . (int)$qc['plan_id']
                    : '/qc-plans?plan_date=' . urlencode($date);
            } elseif ((int)($order['requires_assembly'] ?? 0) === 1) {
                $targetQty = max($requiredQty, round((float)($assembly['target_qty'] ?? 0), 2));
            }

            $gapQty = round(max(0.0, $targetQty - $completedQty), 2);
            $blockReasons = array_values(array_filter(array_map('strval', (array)($stage['block_reasons'] ?? []))));
            $materialStatus = (string)($material['coverage_status'] ?? 'Balanced');
            $materialRisk = !$material || !empty($material['material_ready']) ? false : true;
            $riskLabel = 'At Risk';

            if ($blockReasons !== []) {
                $riskLabel = 'Blocked';
            } elseif ($materialStatus === 'Delivery Delayed') {
                $riskLabel = 'Material Delayed';
            } elseif (in_array($materialStatus, ['Critical', 'Low'], true)) {
                $riskLabel = 'Material Short';
            } elseif ($focusStage === 'assembly' && $gapQty > 0) {
                $riskLabel = 'Assembly Short';
            } elseif ($focusStage === 'qc' && $gapQty > 0) {
                $riskLabel = 'QC Short';
            }

            $score = 0;
            if ($blockReasons !== []) {
                $score += 500;
            }
            if ($materialStatus === 'Delivery Delayed') {
                $score += 260;
            } elseif ($materialStatus === 'Critical') {
                $score += 240;
            } elseif ($materialStatus === 'Low') {
                $score += 180;
            }
            if ((string)($order['required_date'] ?? '') <= date('Y-m-d')) {
                $score += 80;
            }
            if ($focusStage === 'assembly') {
                $score += 40;
            }
            $score += (int)round($gapQty);

            if ($gapQty <= 0.0 && $blockReasons === [] && !$materialRisk) {
                continue;
            }

            $rows[] = [
                'product_id' => $productId,
                'parts_name' => (string)($order['parts_name'] ?? '-'),
                'parts_number' => (string)($order['parts_number'] ?? ''),
                'daily_order_id' => (int)($order['daily_order_id'] ?? 0),
                'required_date' => (string)($order['required_date'] ?? ''),
                'required_qty' => $requiredQty,
                'focus_stage' => strtoupper($focusStage),
                'target_qty' => round($targetQty, 2),
                'completed_qty' => round($completedQty, 2),
                'gap_qty' => $gapQty,
                'risk_label' => $riskLabel,
                'material_status' => $materialStatus,
                'material_top_id' => (int)($material['top_material_id'] ?? 0),
                'material_top_name' => (string)($material['top_material_name'] ?? ''),
                'block_reason' => $blockReasons !== [] ? $blockReasons[0] : '',
                'open_url' => $openUrl,
                'open_label' => $openLabel,
                'detail_url' => $detailUrl,
                'part_url' => '/apps/manufacturing/products/360?id=' . $productId,
                'order_url' => (int)($order['daily_order_id'] ?? 0) > 0 ? '/daily-orders/360?id=' . (int)$order['daily_order_id'] : '',
                'material_url' => (int)($material['top_material_id'] ?? 0) > 0
                    ? '/apps/manufacturing/materials/stock?material_id=' . (int)$material['top_material_id']
                    : '/apps/manufacturing/materials/stock',
                'score' => $score,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0))
                ?: strcmp((string)($a['required_date'] ?? ''), (string)($b['required_date'] ?? ''))
                ?: strcmp((string)($a['parts_name'] ?? ''), (string)($b['parts_name'] ?? ''));
        });

        return array_slice($rows, 0, 8);
    }

    /**
     * @param array<int,int> $scopePartIds
     * @return array<int,array<string,mixed>>
     */
    private static function assemblyPlans(string $date, array $scopePartIds = []): array
    {
        $rows = AssemblyPlanService::listPlans($date, '', 0, true);
        if ($scopePartIds !== []) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => in_array((int)($row['product_id'] ?? 0), $scopePartIds, true)));
        }

        return array_slice(array_map(static function (array $row): array {
            return [
                'id' => (int)($row['id'] ?? 0),
                'product_id' => (int)($row['product_id'] ?? 0),
                'parts_name' => (string)($row['parts_name'] ?? '-'),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'target_qty' => round((float)($row['effective_qty'] ?? 0), 2),
                'completed_qty' => round((float)($row['completed_qty'] ?? 0), 2),
                'remaining_qty' => round((float)($row['remaining_qty'] ?? 0), 2),
                'status' => (string)($row['execution_status'] ?? 'draft'),
                'detail_url' => '/apps/manufacturing/assembly-plans/detail?id=' . (int)($row['id'] ?? 0),
            ];
        }, $rows), 0, 16);
    }

    /**
     * @param array<int,int> $scopePartIds
     * @return array<int,array<string,mixed>>
     */
    private static function qcPlans(string $date, array $scopePartIds = []): array
    {
        $params = [$date];
        $partFilterSql = '';
        if ($scopePartIds !== []) {
            $placeholders = implode(',', array_fill(0, count($scopePartIds), '?'));
            $partFilterSql = " AND q.product_id IN ({$placeholders})";
            foreach ($scopePartIds as $partId) {
                $params[] = $partId;
            }
        }

        $rows = DB::fetchAll(
            "SELECT
                q.id,
                q.plan_date,
                COALESCE(q.required_date, q.plan_date) AS required_date,
                q.product_id,
                q.daily_order_id,
                q.planned_qty,
                q.status,
                p.parts_name,
                p.parts_number,
                ROUND(COALESCE(qe.checked_qty, 0), 2) AS checked_qty,
                ROUND(COALESCE(qe.pass_qty, 0), 2) AS pass_qty
             FROM qc_plans q
             INNER JOIN products p ON p.id = q.product_id
             LEFT JOIN (
                SELECT qc_plan_id, SUM(checked_qty) AS checked_qty, SUM(pass_qty) AS pass_qty
                FROM qc_entries
                GROUP BY qc_plan_id
             ) qe ON qe.qc_plan_id = q.id
             WHERE q.plan_date = ?
               AND LOWER(COALESCE(q.status, 'open')) NOT IN ('cancelled', 'canceled')
               {$partFilterSql}
             ORDER BY q.id ASC",
            $params
        );

        $items = [];
        foreach ($rows as $row) {
            $targetQty = round((float)($row['planned_qty'] ?? 0), 2);
            $checkedQty = round((float)($row['checked_qty'] ?? 0), 2);
            $remainingQty = round(max(0.0, $targetQty - $checkedQty), 2);
            $deadline = (string)($row['required_date'] ?? $row['plan_date'] ?? '');
            $isCompleted = $remainingQty <= 0.0;

            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'product_id' => (int)($row['product_id'] ?? 0),
                'parts_name' => (string)($row['parts_name'] ?? '-'),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'required_date' => $deadline,
                'target_qty' => $targetQty,
                'checked_qty' => $checkedQty,
                'remaining_qty' => $remainingQty,
                'status' => $isCompleted ? 'Completed' : (string)($row['status'] ?? 'Open'),
                'is_completed' => $isCompleted,
                'detail_url' => '/qc-plans/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $aCompleted = !empty($a['is_completed']);
            $bCompleted = !empty($b['is_completed']);
            if ($aCompleted !== $bCompleted) {
                return $aCompleted <=> $bCompleted;
            }

            $remainingCompare = ((float)($b['remaining_qty'] ?? 0)) <=> ((float)($a['remaining_qty'] ?? 0));
            if ($remainingCompare !== 0) {
                return $remainingCompare;
            }

            $deadlineCompare = strcmp((string)($a['required_date'] ?? ''), (string)($b['required_date'] ?? ''));
            if ($deadlineCompare !== 0) {
                return $deadlineCompare;
            }

            return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        });

        return array_slice($items, 0, 16);
    }

    /**
     * @param array<int,int> $scopePartIds
     * @return array<int,array<string,mixed>>
     */
    private static function dailyOrderDemandRows(string $date, array $scopePartIds = []): array
    {
        $params = [$date];
        $partSql = '';
        if ($scopePartIds !== []) {
            $placeholders = implode(',', array_fill(0, count($scopePartIds), '?'));
            $partSql = " AND d.product_id IN ({$placeholders})";
            foreach ($scopePartIds as $partId) {
                $params[] = $partId;
            }
        }

        return DB::fetchAll(
            "SELECT
                MIN(d.id) AS daily_order_id,
                d.product_id,
                MIN(COALESCE(d.required_date, d.order_date)) AS required_date,
                ROUND(SUM(COALESCE(d.qty, 0)), 2) AS required_qty,
                p.parts_name,
                p.parts_number,
                p.requires_assembly,
                p.requires_ipm_qc
             FROM daily_orders d
             INNER JOIN products p ON p.id = d.product_id
             WHERE COALESCE(d.required_date, d.order_date) = ?
               AND LOWER(COALESCE(d.status, 'open')) NOT IN ('cancelled', 'canceled', 'completed', 'closed')
               AND ((COALESCE(p.requires_assembly, 0) = 1) OR (COALESCE(p.requires_ipm_qc, 0) = 1))
               {$partSql}
             GROUP BY d.product_id, p.parts_name, p.parts_number, p.requires_assembly, p.requires_ipm_qc
             ORDER BY required_date ASC, p.parts_name ASC",
            $params
        );
    }

    /**
     * @param array<int,int> $scopePartIds
     * @return array<int,array<string,mixed>>
     */
    private static function assemblyPlanSummaryMap(string $date, array $scopePartIds = []): array
    {
        $map = [];
        foreach (AssemblyPlanService::listPlans($date, '', 0, false) as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            if ($scopePartIds !== [] && !in_array($productId, $scopePartIds, true)) {
                continue;
            }
            if (!isset($map[$productId])) {
                $map[$productId] = [
                    'plan_id' => (int)($row['id'] ?? 0),
                    'target_qty' => 0.0,
                    'completed_qty' => 0.0,
                ];
            }
            $map[$productId]['target_qty'] += (float)($row['effective_qty'] ?? 0);
            $map[$productId]['completed_qty'] += (float)($row['completed_qty'] ?? 0);
        }

        foreach ($map as $productId => $row) {
            $map[$productId]['target_qty'] = round((float)$row['target_qty'], 2);
            $map[$productId]['completed_qty'] = round((float)$row['completed_qty'], 2);
        }

        return $map;
    }

    /**
     * @param array<int,int> $scopePartIds
     * @return array<int,array<string,mixed>>
     */
    private static function qcPlanSummaryMap(string $date, array $scopePartIds = []): array
    {
        $params = [$date];
        $partFilterSql = '';
        if ($scopePartIds !== []) {
            $placeholders = implode(',', array_fill(0, count($scopePartIds), '?'));
            $partFilterSql = " AND q.product_id IN ({$placeholders})";
            foreach ($scopePartIds as $partId) {
                $params[] = $partId;
            }
        }

        $rows = DB::fetchAll(
            "SELECT
                q.product_id,
                MIN(q.id) AS plan_id,
                ROUND(SUM(q.planned_qty), 2) AS target_qty,
                ROUND(COALESCE(SUM(qe.checked_qty), 0), 2) AS checked_qty
             FROM qc_plans q
             LEFT JOIN (
                SELECT qc_plan_id, SUM(checked_qty) AS checked_qty
                FROM qc_entries
                GROUP BY qc_plan_id
             ) qe ON qe.qc_plan_id = q.id
             WHERE q.plan_date = ?
               {$partFilterSql}
             GROUP BY q.product_id",
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['product_id'] ?? 0)] = [
                'plan_id' => (int)($row['plan_id'] ?? 0),
                'target_qty' => round((float)($row['target_qty'] ?? 0), 2),
                'checked_qty' => round((float)($row['checked_qty'] ?? 0), 2),
            ];
        }

        return $map;
    }

    /**
     * @return array{part_ids:array<int,int>}
     */
    private static function scopeForUser(?array $user): array
    {
        if (!$user) {
            return ['part_ids' => []];
        }

        $context = platform_user_context_contract()->resolveUserContext($user);
        return [
            'part_ids' => array_values(array_filter(array_map('intval', (array)($context['scope']['part_ids'] ?? [])), static fn (int $id): bool => $id > 0)),
        ];
    }

    private static function currentUserLabel(?array $user): string
    {
        $user = $user ?? Auth::user();
        if (!is_array($user)) {
            return 'system';
        }
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'user#' . (string)($user['id'] ?? '0');
    }
}

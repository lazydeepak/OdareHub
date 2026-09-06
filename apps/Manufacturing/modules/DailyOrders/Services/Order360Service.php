<?php
declare(strict_types=1);

namespace Plugins\DailyOrders\Services;

use App\Core\DB;

require_once APP_ROOT . '/app/Core/helpers.php';

final class Order360Service
{
    /**
     * @var array<string,array{warn:int,breach:int,label:string}>
     */
    private const SLA_HOURS = [
        'production_to_qc' => ['warn' => 4, 'breach' => 12, 'label' => 'Waiting QC'],
        'qc_to_dispatch' => ['warn' => 2, 'breach' => 8, 'label' => 'QC Release'],
        'dispatch_to_completion' => ['warn' => 4, 'breach' => 12, 'label' => 'Dispatch Hold'],
        'order_risk' => ['warn' => 12, 'breach' => 24, 'label' => 'Order Risk'],
    ];

    /**
     * @return array<string,mixed>|null
     */
    public static function build(int $dailyOrderId): ?array
    {
        if ($dailyOrderId <= 0) {
            return null;
        }

        $order = DB::fetchOne(
            "SELECT d.*, p.parts_name, p.parts_number,
                    COALESCE(NULLIF(TRIM(p.supply_mode), ''), 'in_house') AS supply_mode,
                    COALESCE(NULLIF(TRIM(p.fulfillment_mode), ''), 'via_ipm') AS fulfillment_mode,
                    CASE WHEN p.requires_ipm_qc IS NULL THEN 1 ELSE p.requires_ipm_qc END AS requires_ipm_qc,
                    COALESCE(NULLIF(TRIM(p.default_supplier), ''), '') AS default_supplier,
                    CASE WHEN p.stocked_at_ipm IS NULL THEN 1 ELSE p.stocked_at_ipm END AS stocked_at_ipm
             FROM daily_orders d
             INNER JOIN products p ON p.id = d.product_id
             WHERE d.id = ? LIMIT 1",
            [$dailyOrderId]
        );

        if (!$order) {
            return null;
        }

        $productId = (int)($order['product_id'] ?? 0);
        $orderDate = trim((string)($order['order_date'] ?? ''));
        if ($orderDate === '') {
            $orderDate = date('Y-m-d');
        }

        $windowStart = self::shiftDate($orderDate, -15);
        $windowEnd = self::shiftDate((string)($order['required_date'] ?? $orderDate), 30);

        $coverage = self::buildCoverageBreakdown($order);

        $productionPlans = DB::fetchAll(
            "SELECT pp.*, m.machine_no, m.machine_name
             FROM production_plans pp
             LEFT JOIN machines m ON m.id = pp.machine_id
             WHERE pp.product_id = ?
               AND pp.plan_date BETWEEN ? AND ?
             ORDER BY pp.plan_date DESC, pp.id DESC
             LIMIT 120",
            [$productId, $windowStart, $windowEnd]
        );

        $productionEntries = DB::fetchAll(
            "SELECT pe.*, m.machine_no, m.machine_name
             FROM production_entries pe
             LEFT JOIN machines m ON m.id = pe.machine_id
             WHERE pe.product_id = ?
               AND pe.production_date BETWEEN ? AND ?
             ORDER BY pe.production_date DESC, pe.id DESC
             LIMIT 120",
            [$productId, $windowStart, $windowEnd]
        );

        $qcPlans = DB::fetchAll(
            "SELECT qp.*
             FROM qc_plans qp
             WHERE (qp.daily_order_id = ?)
                OR (qp.product_id = ? AND qp.plan_date BETWEEN ? AND ?)
             ORDER BY qp.plan_date DESC, qp.id DESC
             LIMIT 120",
            [$dailyOrderId, $productId, $windowStart, $windowEnd]
        );

        $qcEntries = DB::fetchAll(
            "SELECT q.*
             FROM qc_entries q
             WHERE (q.daily_order_id = ?)
                OR (q.product_id = ? AND DATE(q.created_at) BETWEEN ? AND ?)
             ORDER BY q.id DESC
             LIMIT 120",
            [$dailyOrderId, $productId, $windowStart, $windowEnd]
        );

        $dispatchEntries = DB::fetchAll(
            "SELECT d.*
             FROM dispatch_entries d
             WHERE (d.daily_order_id = ?)
                OR (d.product_id = ? AND d.dispatch_date BETWEEN ? AND ?)
             ORDER BY d.dispatch_date DESC, d.id DESC
             LIMIT 120",
            [$dailyOrderId, $productId, $windowStart, $windowEnd]
        );

        $handoff = self::buildHandoffSummary($dailyOrderId, $qcEntries, $dispatchEntries, $productionEntries);
        $notifications = self::buildNotifications($dailyOrderId, $qcEntries, $dispatchEntries, $productionEntries);
        $workflowState = self::buildWorkflowState($order, $coverage, $productionPlans, $productionEntries, $qcEntries, $dispatchEntries);
        $recommendedActions = self::buildRecommendedActions($order, $coverage, $workflowState);
        $timeline = self::buildTimeline($order, $productionPlans, $productionEntries, $qcPlans, $qcEntries, $dispatchEntries, $notifications);
        $relatedSummary = self::buildRelatedSummary($productionPlans, $productionEntries, $qcPlans, $qcEntries, $dispatchEntries);

        return [
            'order' => $order,
            'due' => self::dueState($order),
            'coverage' => $coverage,
            'workflow_state' => $workflowState,
            'recommended_actions' => $recommendedActions,
            'timeline' => $timeline,
            'related_summary' => $relatedSummary,
            'production_plans' => $productionPlans,
            'production_entries' => $productionEntries,
            'qc_plans' => $qcPlans,
            'qc_entries' => $qcEntries,
            'dispatch_entries' => $dispatchEntries,
            'handoff' => $handoff,
            'notifications' => $notifications,
        ];
    }

    /**
     * @param array<string,mixed> $order
     * @return array<string,mixed>
     */
    private static function buildCoverageBreakdown(array $order): array
    {
        $demand = (float)($order['qty'] ?? 0);
        $stock = (float)($order['usable_stock_qty'] ?? 0);
        $plan = (float)($order['planned_supply_qty'] ?? 0);
        $qc = (float)($order['qc_pass_qty'] ?? 0);
        $dispatched = (float)($order['dispatched_qty'] ?? 0);

        $netSupply = max(0.0, $stock + $plan + $qc - $dispatched);
        $covered = min($demand, $netSupply);
        $shortage = max(0.0, $demand - $covered);
        $coveragePct = $demand > 0 ? round(($covered / $demand) * 100, 2) : 0.0;
        $status = $coveragePct >= 100 ? 'Full' : ($coveragePct > 0 ? 'Partial' : 'Low');

        $reason = 'Low because net supply is ' . number_format($netSupply, 2, '.', '')
            . ' against demand ' . number_format($demand, 2, '.', '') . '.';
        if ($status === 'Full') {
            $reason = 'Full because net supply ' . number_format($netSupply, 2, '.', '')
                . ' meets or exceeds demand ' . number_format($demand, 2, '.', '') . '.';
        } elseif ($status === 'Partial') {
            $reason = 'Partial because net supply ' . number_format($netSupply, 2, '.', '')
                . ' only covers part of demand ' . number_format($demand, 2, '.', '') . '.';
        }

        return [
            'demand_qty' => $demand,
            'covered_qty' => $covered,
            'shortage_qty' => $shortage,
            'coverage_pct' => $coveragePct,
            'coverage_status' => $status,
            'stock_qty' => $stock,
            'plan_qty' => $plan,
            'qc_qty' => $qc,
            'dispatched_qty' => $dispatched,
            'net_supply_qty' => $netSupply,
            'open_demand_qty' => (float)($order['open_demand_qty'] ?? 0),
            'forecast_pressure_qty' => (float)($order['forecast_pressure_qty'] ?? 0),
            'reason' => $reason,
            'last_recalculated_at' => (string)($order['coverage_last_recalculated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $coverage
     * @param array<int,array<string,mixed>> $productionPlans
     * @param array<int,array<string,mixed>> $productionEntries
     * @param array<int,array<string,mixed>> $qcEntries
     * @param array<int,array<string,mixed>> $dispatchEntries
     * @return array<string,mixed>
     */
    private static function buildWorkflowState(array $order, array $coverage, array $productionPlans, array $productionEntries, array $qcEntries, array $dispatchEntries): array
    {
        $demandQty = (float)($order['qty'] ?? 0);
        $coveredQty = (float)($coverage['covered_qty'] ?? 0);
        $shortageQty = (float)($coverage['shortage_qty'] ?? 0);

        $plannedQty = 0.0;
        foreach ($productionPlans as $row) {
            $plannedQty += (float)($row['planned_qty'] ?? 0);
        }

        $producedQty = 0.0;
        foreach ($productionEntries as $row) {
            $producedQty += (float)($row['good_qty'] ?? 0);
        }

        $qcPassQty = 0.0;
        foreach ($qcEntries as $row) {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if (in_array($status, ['cancelled', 'canceled', 'void', 'rejected'], true)) {
                continue;
            }
            $qcPassQty += (float)($row['pass_qty'] ?? 0);
        }

        $releasedQty = 0.0;
        $readyDispatchQty = 0.0;
        $blockedDispatchQty = 0.0;
        foreach ($dispatchEntries as $row) {
            $status = strtolower(trim((string)($row['dispatch_status'] ?? '')));
            $qty = (float)($row['dispatchable_qty'] ?? 0);
            if (in_array($status, ['partial', 'dispatched', 'completed', 'closed', 'delivered'], true)) {
                $releasedQty += $qty;
            } elseif ($status === 'ready') {
                $readyDispatchQty += $qty;
            } elseif (in_array($status, ['hold', 'blocked'], true)) {
                $blockedDispatchQty += $qty;
            }
        }

        $remainingQty = max(0.0, round($demandQty - $releasedQty, 2));
        $coverageStatus = strtolower(trim((string)($coverage['coverage_status'] ?? 'low')));

        $planningNeeded = $shortageQty > 0.0001 || $plannedQty < $demandQty;
        $awaitingQc = $qcPassQty + 0.0001 < min($demandQty, max($producedQty, $coveredQty));
        $dispatchReady = $readyDispatchQty > 0.0001 || ($remainingQty > 0.0001 && $coverageStatus === 'full');
        $partiallyReleased = $releasedQty > 0.0001 && $remainingQty > 0.0001;
        $supplyBlocked = $shortageQty > 0.0001 || $blockedDispatchQty > 0.0001;

        $stageLabel = 'Planning Needed';
        if ($remainingQty <= 0.0001) {
            $stageLabel = 'Released / Fulfilled';
        } elseif ($supplyBlocked) {
            $stageLabel = 'Supply Blocked';
        } elseif ($awaitingQc) {
            $stageLabel = 'Awaiting QC';
        } elseif ($dispatchReady) {
            $stageLabel = $partiallyReleased ? 'Partially Released' : 'Ready for Dispatch';
        }

        return [
            'stage_label' => $stageLabel,
            'planning_needed' => $planningNeeded,
            'awaiting_qc' => $awaitingQc,
            'dispatch_ready' => $dispatchReady,
            'supply_blocked' => $supplyBlocked,
            'partially_released' => $partiallyReleased,
            'planned_qty' => round($plannedQty, 2),
            'produced_qty' => round($producedQty, 2),
            'qc_pass_qty' => round($qcPassQty, 2),
            'released_qty' => round($releasedQty, 2),
            'ready_dispatch_qty' => round($readyDispatchQty, 2),
            'blocked_dispatch_qty' => round($blockedDispatchQty, 2),
            'remaining_qty' => round($remainingQty, 2),
        ];
    }

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $coverage
     * @param array<string,mixed> $workflowState
     * @return array<int,array<string,mixed>>
     */
    private static function buildRecommendedActions(array $order, array $coverage, array $workflowState): array
    {
        $orderId = (int)($order['id'] ?? 0);
        $productId = (int)($order['product_id'] ?? 0);
        $requiredDate = trim((string)($order['required_date'] ?? date('Y-m-d')));
        $coverageStatus = strtolower(trim((string)($coverage['coverage_status'] ?? 'low')));
        $remainingQty = (float)($workflowState['remaining_qty'] ?? 0);
        $shortageQty = (float)($coverage['shortage_qty'] ?? 0);
        $coveredQty = (float)($coverage['covered_qty'] ?? 0);

        $actions = [
            [
                'label' => 'Open Part 360',
                'url' => '/apps/manufacturing/products/360?id=' . $productId,
                'method' => 'link',
                'roles' => ['all'],
                'intent' => 'View full part flow and risk context before making execution decisions.',
            ],
            [
                'label' => 'Open Dispatch Ops',
                'url' => '/apps/manufacturing/dispatch-ops',
                'method' => 'link',
                'roles' => ['admin', 'dispatch'],
                'intent' => 'Review dispatch execution workload and release blockers.',
            ],
        ];

        if ($coverageStatus === 'low' || $coverageStatus === 'partial') {
            $actions[] = [
                'label' => 'Create Plan Draft',
                'url' => '/production-plans/start-draft',
                'method' => 'post',
                'roles' => ['admin', 'planning', 'machine'],
                'intent' => 'Start planning to close shortage and rebalance supply.',
                'post' => [
                    'product_id' => (string)$productId,
                    'daily_order_id' => (string)$orderId,
                    'required_date' => $requiredDate,
                    'shortage_qty' => number_format(max(0.0, $shortageQty), 2, '.', ''),
                    'plan_type' => 'Recovery',
                    'reference_doctype' => 'DailyOrder',
                    'reference_name' => (string)$orderId,
                    'failure_fallback' => '/daily-orders/360?id=' . $orderId,
                ],
            ];
            $actions[] = [
                'label' => 'Review Shortage Context',
                'url' => '/apps/manufacturing/production-queue?date=' . urlencode($requiredDate),
                'method' => 'link',
                'roles' => ['all'],
                'intent' => 'Inspect queue pressure for this due window.',
            ];
        }

        if ((bool)($workflowState['awaiting_qc'] ?? false)) {
            $actions[] = [
                'label' => 'Create QC Draft',
                'url' => '/qc-entries/start-draft',
                'method' => 'post',
                'roles' => ['admin', 'qc'],
                'intent' => 'Open QC gate and capture validation for this order.',
                'post' => [
                    'product_id' => (string)$productId,
                    'daily_order_id' => (string)$orderId,
                    'required_date' => $requiredDate,
                    'covered_qty' => number_format(max(0.0, $coveredQty), 2, '.', ''),
                    'qc_type' => 'Final',
                    'failure_fallback' => '/daily-orders/360?id=' . $orderId,
                ],
            ];
        }

        if ((bool)($workflowState['dispatch_ready'] ?? false) || $coverageStatus === 'full') {
            $actions[] = [
                'label' => 'Create Dispatch Draft',
                'url' => '/dispatch-entries/start-draft',
                'method' => 'post',
                'roles' => ['admin', 'dispatch'],
                'intent' => 'Start dispatch transaction from full order context.',
                'post' => [
                    'product_id' => (string)$productId,
                    'daily_order_id' => (string)$orderId,
                    'required_date' => $requiredDate,
                    'covered_qty' => number_format(max(0.0, $remainingQty > 0 ? min($coveredQty, $remainingQty) : $coveredQty), 2, '.', ''),
                    'customer_name' => (string)($order['customer_name'] ?? ''),
                    'failure_fallback' => '/daily-orders/360?id=' . $orderId,
                ],
            ];
        }

        if ((bool)($workflowState['supply_blocked'] ?? false)) {
            $actions[] = [
                'label' => 'Create Hold Dispatch',
                'url' => '/dispatch-entries/add?daily_order_id=' . $orderId . '&product_id=' . $productId . '&dispatch_status=Hold&status_reason=Supply%20blocked',
                'method' => 'link',
                'roles' => ['admin', 'dispatch'],
                'intent' => 'Capture explicit hold state and reason for operational traceability.',
            ];
        }

        $actions[] = [
            'label' => 'Open Linked Records',
            'url' => '/daily-orders/edit?id=' . $orderId,
            'method' => 'link',
            'roles' => ['all'],
            'intent' => 'Review and adjust the demand record source details.',
        ];

        return $actions;
    }

    /**
     * @param array<string,mixed> $order
     * @param array<int,array<string,mixed>> $productionPlans
     * @param array<int,array<string,mixed>> $productionEntries
     * @param array<int,array<string,mixed>> $qcPlans
     * @param array<int,array<string,mixed>> $qcEntries
     * @param array<int,array<string,mixed>> $dispatchEntries
     * @param array<int,array<string,mixed>> $notifications
     * @return array<int,array<string,mixed>>
     */
    private static function buildTimeline(array $order, array $productionPlans, array $productionEntries, array $qcPlans, array $qcEntries, array $dispatchEntries, array $notifications): array
    {
        $events = [];

        foreach (array_slice($productionPlans, 0, 6) as $row) {
            $events[] = [
                'when' => (string)($row['plan_date'] ?? ''),
                'type' => 'Production Plan',
                'title' => 'Plan #' . (int)($row['id'] ?? 0) . ' • qty ' . number_format((float)($row['planned_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['status'] ?? ''),
                'url' => '/production-plans/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($productionEntries, 0, 6) as $row) {
            $events[] = [
                'when' => (string)($row['production_date'] ?? ''),
                'type' => 'Production Entry',
                'title' => 'Entry #' . (int)($row['id'] ?? 0) . ' • good ' . number_format((float)($row['good_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['status'] ?? ''),
                'url' => '/production-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($qcPlans, 0, 6) as $row) {
            $events[] = [
                'when' => (string)($row['plan_date'] ?? ''),
                'type' => 'QC Plan',
                'title' => 'QC Plan #' . (int)($row['id'] ?? 0) . ' • qty ' . number_format((float)($row['planned_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['status'] ?? ''),
                'url' => '/qc-plans/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($qcEntries, 0, 6) as $row) {
            $events[] = [
                'when' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
                'type' => 'QC Entry',
                'title' => 'QC #' . (int)($row['id'] ?? 0) . ' • pass ' . number_format((float)($row['pass_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['status'] ?? ''),
                'url' => '/qc-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($dispatchEntries, 0, 8) as $row) {
            $events[] = [
                'when' => (string)($row['dispatch_date'] ?? ''),
                'type' => 'Dispatch Entry',
                'title' => 'Dispatch #' . (int)($row['id'] ?? 0) . ' • qty ' . number_format((float)($row['dispatchable_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['dispatch_status'] ?? ''),
                'url' => '/dispatch-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($notifications, 0, 6) as $row) {
            $events[] = [
                'when' => (string)($row['created_at'] ?? ''),
                'type' => 'Workflow Notification',
                'title' => (string)($row['title'] ?? (string)($row['event_type'] ?? 'Event')),
                'status' => (string)($row['severity'] ?? ''),
                'url' => '/ops/notifications',
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string)($b['when'] ?? ''), (string)($a['when'] ?? ''));
        });

        return array_slice($events, 0, 20);
    }

    /**
     * @param array<int,array<string,mixed>> $productionPlans
     * @param array<int,array<string,mixed>> $productionEntries
     * @param array<int,array<string,mixed>> $qcPlans
     * @param array<int,array<string,mixed>> $qcEntries
     * @param array<int,array<string,mixed>> $dispatchEntries
     * @return array<string,int>
     */
    private static function buildRelatedSummary(array $productionPlans, array $productionEntries, array $qcPlans, array $qcEntries, array $dispatchEntries): array
    {
        return [
            'production_plans' => count($productionPlans),
            'production_entries' => count($productionEntries),
            'qc_plans' => count($qcPlans),
            'qc_entries' => count($qcEntries),
            'dispatch_entries' => count($dispatchEntries),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $qcEntries
     * @param array<int,array<string,mixed>> $dispatchEntries
     * @param array<int,array<string,mixed>> $productionEntries
     * @return array<string,mixed>
     */
    private static function buildHandoffSummary(int $dailyOrderId, array $qcEntries, array $dispatchEntries, array $productionEntries): array
    {
        if (!self::tableExists('handoff_tracking')) {
            return [
                'current' => null,
                'active' => [],
            ];
        }

        $qcIds = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $qcEntries)));
        $dispatchIds = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $dispatchEntries)));
        $productionIds = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $productionEntries)));

        $clauses = ['(entity_type = ? AND entity_id = ?)'];
        $params = ['daily_order', $dailyOrderId];

        self::appendEntityInClause($clauses, $params, 'qc_entry', $qcIds);
        self::appendEntityInClause($clauses, $params, 'dispatch_entry', $dispatchIds);
        self::appendEntityInClause($clauses, $params, 'production_entry', $productionIds);

        $rows = DB::fetchAll(
            'SELECT * FROM handoff_tracking WHERE released_since IS NULL AND (' . implode(' OR ', $clauses) . ') ORDER BY updated_at DESC, id DESC',
            $params
        );

        $active = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $stage = (string)($row['stage'] ?? '');
            $since = self::stageSinceAt($row);
            $age = self::hoursDiff($since, $now);
            $sla = self::evaluateSla($stage, $age);

            $ownerRole = trim((string)($row['owner_role'] ?? ''));
            if ($ownerRole === '') {
                $ownerRole = self::defaultOwnerForStage($stage);
            }

            $active[] = [
                'entity_type' => (string)($row['entity_type'] ?? ''),
                'entity_id' => (int)($row['entity_id'] ?? 0),
                'stage' => $stage,
                'stage_label' => (string)(self::SLA_HOURS[$stage]['label'] ?? $stage),
                'owner' => displayRole($ownerRole),
                'owner_display' => (string)($row['owner_display'] ?? ''),
                'age_hours' => $age,
                'sla_level' => (string)$sla['level'],
                'sla_label' => (string)$sla['label'],
                'escalation_level' => (string)($row['escalation_level'] ?? ''),
                'escalation_state' => (string)($row['escalation_state'] ?? ''),
                'escalated_at' => (string)($row['escalated_at'] ?? ''),
                'ready_since' => (string)($row['ready_since'] ?? ''),
                'blocked_since' => (string)($row['blocked_since'] ?? ''),
                'entered_stage_at' => (string)($row['entered_stage_at'] ?? ''),
            ];
        }

        usort($active, static function (array $a, array $b): int {
            $rank = ['breach' => 0, 'warning' => 1, 'ok' => 2];
            $aRank = $rank[(string)($a['sla_level'] ?? 'ok')] ?? 2;
            $bRank = $rank[(string)($b['sla_level'] ?? 'ok')] ?? 2;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
            return ((float)($b['age_hours'] ?? 0.0)) <=> ((float)($a['age_hours'] ?? 0.0));
        });

        return [
            'current' => $active[0] ?? null,
            'active' => array_slice($active, 0, 30),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $qcEntries
     * @param array<int,array<string,mixed>> $dispatchEntries
     * @param array<int,array<string,mixed>> $productionEntries
     * @return array<int,array<string,mixed>>
     */
    private static function buildNotifications(int $dailyOrderId, array $qcEntries, array $dispatchEntries, array $productionEntries): array
    {
        if (!self::tableExists('workflow_notifications')) {
            return [];
        }

        $clauses = ['(entity_type = ? AND entity_id = ?)'];
        $params = ['daily_order', $dailyOrderId];

        $qcIds = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $qcEntries)));
        $dispatchIds = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $dispatchEntries)));
        $productionIds = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $productionEntries)));

        self::appendEntityInClause($clauses, $params, 'qc_entry', $qcIds);
        self::appendEntityInClause($clauses, $params, 'dispatch_entry', $dispatchIds);
        self::appendEntityInClause($clauses, $params, 'production_entry', $productionIds);

        return DB::fetchAll(
            'SELECT id, severity, status, event_type, title, message, entity_type, entity_id, stage, created_at, read_at, dismissed_at
             FROM workflow_notifications
             WHERE ' . implode(' OR ', $clauses) . '
             ORDER BY created_at DESC, id DESC
             LIMIT 30',
            $params
        );
    }

    /**
     * @param array<string,mixed> $order
     * @return array<string,mixed>
     */
    private static function dueState(array $order): array
    {
        $dueRef = trim((string)($order['dispatch_deadline'] ?? ''));
        if ($dueRef === '') {
            $required = trim((string)($order['required_date'] ?? ''));
            if ($required !== '') {
                $dueRef = $required . ' 23:59:59';
            }
        }

        if ($dueRef === '') {
            return [
                'reference' => '',
                'hours_to_due' => null,
                'is_overdue' => false,
                'label' => 'No due timestamp',
            ];
        }

        try {
            $due = new \DateTime($dueRef);
            $now = new \DateTime(date('Y-m-d H:i:s'));
            $hours = (int)floor(($due->getTimestamp() - $now->getTimestamp()) / 3600);
            return [
                'reference' => $due->format('Y-m-d H:i:s'),
                'hours_to_due' => $hours,
                'is_overdue' => $hours < 0,
                'label' => $hours < 0 ? ('Overdue by ' . abs($hours) . 'h') : ($hours . 'h to due'),
            ];
        } catch (\Throwable $e) {
            return [
                'reference' => $dueRef,
                'hours_to_due' => null,
                'is_overdue' => false,
                'label' => 'Due timestamp invalid',
            ];
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function stageSinceAt(array $row): string
    {
        $candidates = [
            trim((string)($row['blocked_since'] ?? '')),
            trim((string)($row['ready_since'] ?? '')),
            trim((string)($row['entered_stage_at'] ?? '')),
            trim((string)($row['updated_at'] ?? '')),
            trim((string)($row['created_at'] ?? '')),
        ];

        foreach ($candidates as $ts) {
            if ($ts !== '') {
                return $ts;
            }
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * @return array<string,mixed>
     */
    private static function evaluateSla(string $stage, float $ageHours): array
    {
        $policy = self::SLA_HOURS[$stage] ?? ['warn' => 4, 'breach' => 12, 'label' => $stage];
        $warn = (int)$policy['warn'];
        $breach = (int)$policy['breach'];

        $level = 'ok';
        if ($ageHours >= $breach) {
            $level = 'breach';
        } elseif ($ageHours >= $warn) {
            $level = 'warning';
        }

        $label = 'Within SLA';
        if ($level === 'warning') {
            $label = 'SLA Warning';
        } elseif ($level === 'breach') {
            $label = 'SLA Breach';
        }

        return [
            'level' => $level,
            'label' => $label,
        ];
    }

    private static function defaultOwnerForStage(string $stage): string
    {
        if ($stage === 'production_to_qc') {
            return 'QC';
        }
        if ($stage === 'qc_to_dispatch' || $stage === 'dispatch_to_completion') {
            return 'Dispatch';
        }
        if ($stage === 'order_risk') {
            return 'Production';
        }
        return 'Unassigned / System issue';
    }

    private static function hoursDiff(string $fromTs, string $toTs): float
    {
        try {
            $from = new \DateTime($fromTs);
            $to = new \DateTime($toTs);
        } catch (\Throwable $e) {
            return 0.0;
        }

        $seconds = $to->getTimestamp() - $from->getTimestamp();
        if ($seconds <= 0) {
            return 0.0;
        }

        return round($seconds / 3600, 2);
    }

    private static function tableExists(string $tableName): bool
    {
        $escaped = DB::conn()->real_escape_string($tableName);
        return DB::fetchOne("SHOW TABLES LIKE '{$escaped}'") !== null;
    }

    /**
     * @param array<int,string> $clauses
     * @param array<int,mixed> $params
     * @param array<int,int> $ids
     */
    private static function appendEntityInClause(array &$clauses, array &$params, string $entityType, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $clauses[] = '(entity_type = ? AND entity_id IN (' . $placeholders . '))';
        $params[] = $entityType;
        foreach ($ids as $id) {
            $params[] = (int)$id;
        }
    }

    private static function shiftDate(string $date, int $days): string
    {
        try {
            $dt = new \DateTime($date);
            $dt->modify(($days >= 0 ? '+' : '') . $days . ' day');
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return date('Y-m-d');
        }
    }
}

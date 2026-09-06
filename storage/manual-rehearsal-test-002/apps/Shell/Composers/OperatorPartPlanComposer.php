<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use App\Core\DB;

final class OperatorPartPlanComposer
{
    /**
     * @param array<string,mixed> $partRow
     * @param array<string,mixed> $context
     * @param callable(string,string,array<string,mixed>):string $tr
     * @return array{show_action:bool,action_label:string,action_url:string,action_helper:string,show_status:bool,status_label:string,status_url:string,status_helper:string}
     */
    public static function resolveAction(array $partRow, array $context, string $username, callable $tr): array
    {
        $default = [
            'show_action' => false,
            'action_label' => '',
            'action_url' => '',
            'action_helper' => '',
            'show_status' => false,
            'status_label' => '',
            'status_url' => '',
            'status_helper' => '',
        ];

        $productId = (int)($partRow['id'] ?? 0);
        if ($productId <= 0) {
            return $default;
        }

        $roleKey = strtolower(trim((string)($context['dashboard_type'] ?? 'operator')));
        $roleKey = str_replace(['-', ' '], '_', $roleKey);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        if ($roleKey === 'production_leader') {
            $queued = self::hasQueuedProductionPlan($productId, $today);
            if ($queued) {
                return [
                    'show_action' => false,
                    'action_label' => '',
                    'action_url' => '',
                    'action_helper' => '',
                    'show_status' => true,
                    'status_label' => $tr('operator.parts.plan.already_queued', 'Already in Plan Queue', []),
                    'status_url' => '/u/' . $username . '/production',
                    'status_helper' => $tr('operator.parts.plan.production_already_queued', 'Production plan is already queued for this part.', []),
                ];
            }

            return [
                'show_action' => true,
                'action_label' => $tr('operator.parts.plan.add_production', 'Add Production Plan', []),
                'action_url' => '/u/' . $username . '/production?prefill_product_id=' . rawurlencode((string)$productId),
                'action_helper' => $tr('operator.parts.plan.production_helper', 'Visible for Production Leader because this part is not in the production plan queue.', []),
                'show_status' => false,
                'status_label' => '',
                'status_url' => '',
                'status_helper' => '',
            ];
        }

        if ($roleKey === 'qc_leader') {
            $queued = self::hasQueuedQcPlan($productId, $today);
            if ($queued) {
                return [
                    'show_action' => false,
                    'action_label' => '',
                    'action_url' => '',
                    'action_helper' => '',
                    'show_status' => true,
                    'status_label' => $tr('operator.parts.plan.already_queued', 'Already in Plan Queue', []),
                    'status_url' => '/u/' . $username . '/production',
                    'status_helper' => $tr('operator.parts.plan.qc_already_queued', 'QC plan is already queued for this part.', []),
                ];
            }

            return [
                'show_action' => true,
                'action_label' => $tr('operator.parts.plan.add_qc', 'Add QC Plan', []),
                'action_url' => '/u/' . $username . '/production?prefill_product_id=' . rawurlencode((string)$productId),
                'action_helper' => $tr('operator.parts.plan.qc_helper', 'Visible for QC Leader because this part is not in the QC plan queue.', []),
                'show_status' => false,
                'status_label' => '',
                'status_url' => '',
                'status_helper' => '',
            ];
        }

        return $default;
    }

    private static function hasQueuedProductionPlan(int $productId, string $fromDate): bool
    {
        try {
            $row = DB::fetchOne(
                "SELECT id
                 FROM production_plans
                 WHERE product_id = ?
                   AND plan_date >= ?
                   AND LOWER(COALESCE(status, 'planned')) NOT IN ('cancelled', 'canceled', 'completed', 'closed')
                 ORDER BY plan_date ASC, id ASC
                 LIMIT 1",
                [$productId, $fromDate]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return is_array($row) && (int)($row['id'] ?? 0) > 0;
    }

    private static function hasQueuedQcPlan(int $productId, string $fromDate): bool
    {
        try {
            $row = DB::fetchOne(
                "SELECT id
                 FROM qc_plans
                 WHERE product_id = ?
                   AND plan_date >= ?
                   AND LOWER(COALESCE(status, 'open')) NOT IN ('cancelled', 'canceled', 'completed', 'closed')
                 ORDER BY plan_date ASC, id ASC
                 LIMIT 1",
                [$productId, $fromDate]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return is_array($row) && (int)($row['id'] ?? 0) > 0;
    }
}

<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use Plugins\Products\Services\Part360Service;

final class OperatorPartDetailContributionService
{
    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function getData(array $query = [], string $username = ''): array
    {
        $defaults = self::defaults();

        try {
            $productId = self::resolveProductId($query);
            if ($productId <= 0) {
                $defaults['error'] = 'operator.parts.detail.not_found';
                return $defaults;
            }

            $servicePath = APP_ROOT . '/apps/Manufacturing/modules/Products/Services/Part360Service.php';
            if (!class_exists(Part360Service::class, false) && is_file($servicePath)) {
                require_once $servicePath;
            }

            if (!class_exists(Part360Service::class)) {
                throw new \RuntimeException('Part 360 service is not available.');
            }

            $payload = Part360Service::build($productId);
            if (!is_array($payload)) {
                $defaults['error'] = 'operator.parts.detail.not_found';
                return $defaults;
            }

            return self::shapePayload($payload, $username);
        } catch (\Throwable $e) {
            error_log('OperatorPartDetailContributionService::getData: ' . $e->getMessage());
            $defaults['error'] = 'operator.parts.detail.error.load';
            return $defaults;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'product' => null,
            'state' => ['state_label' => '', 'tone' => 'info', 'stage' => ''],
            'metrics' => [],
            'context' => [],
            'actions' => [],
            'orders' => [],
            'activity' => [],
            'empty' => true,
            'error' => '',
        ];
    }

    /**
     * @param array<string,mixed> $query
     */
    private static function resolveProductId(array $query): int
    {
        $productId = (int)($query['part_id'] ?? $query['product_id'] ?? $query['id'] ?? 0);
        if ($productId > 0) {
            return $productId;
        }

        $partNumber = trim((string)($query['part_number'] ?? ''));
        $search = trim((string)($query['q'] ?? $query['part_q'] ?? ''));
        if ($partNumber === '' && $search === '') {
            return 0;
        }

        $params = [];
        $where = '';
        if ($partNumber !== '') {
            $where = 'TRIM(parts_number) = ?';
            $params[] = $partNumber;
        } else {
            $where = '(parts_name LIKE ? OR parts_number LIKE ? OR model LIKE ?)';
            $like = '%' . substr($search, 0, 80) . '%';
            $params = [$like, $like, $like];
        }

        $row = DB::fetchOne(
            "SELECT id
             FROM products
             WHERE {$where}
             ORDER BY is_active DESC, parts_name ASC, id ASC
             LIMIT 1",
            $params
        );

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function shapePayload(array $payload, string $username): array
    {
        $product = (array)($payload['product'] ?? []);
        $productId = (int)($product['id'] ?? 0);
        $partNumber = trim((string)($product['parts_number'] ?? ''));
        $demand = (array)($payload['demand_summary'] ?? []);
        $stock = (array)($payload['stock_summary'] ?? []);
        $planned = (array)($payload['planned_supply_summary'] ?? []);
        $dispatch = (array)($payload['dispatch_summary'] ?? []);
        $risk = (array)($payload['risk_summary'] ?? []);
        $workflow = (array)($payload['workflow_context'] ?? []);
        $machineAssignment = (array)($payload['machine_assignment'] ?? []);
        $activeMachine = is_array($machineAssignment['active_machine'] ?? null) ? (array)$machineAssignment['active_machine'] : [];
        $responsibility = (array)($payload['responsibility'] ?? []);
        $assignedUser = is_array($responsibility['assigned_user'] ?? null) ? (array)$responsibility['assigned_user'] : [];
        $supply = (array)($payload['supply_profile'] ?? []);

        $prefix = '/u/' . rawurlencode($username);
        $searchPart = $partNumber !== '' ? $partNumber : (string)$productId;

        return [
            'product' => [
                'id' => $productId,
                'name' => (string)($product['parts_name'] ?? ''),
                'number' => $partNumber,
                'model' => (string)($product['model'] ?? ''),
                'status' => ((int)($product['is_active'] ?? 1) === 1) ? 'active' : 'inactive',
                'qty_per_case' => (int)($product['qty_per_case'] ?? 0),
                'case_type' => (string)($product['case_type'] ?? ''),
                'case_spec' => (string)($product['case_spec'] ?? ''),
                'cases_per_pallet' => (int)($product['cases_per_pallet'] ?? 0),
            ],
            'state' => [
                'state_label' => (string)($risk['label'] ?? ''),
                'tone' => self::tone((string)($risk['state'] ?? 'healthy')),
                'stage' => (string)($workflow['stage_label'] ?? ''),
            ],
            'metrics' => [
                ['key' => 'operator.parts.detail.metric.open_demand', 'value' => self::fmt((float)($demand['open_demand_qty'] ?? 0)), 'tone' => 'info'],
                ['key' => 'operator.parts.detail.metric.shortage', 'value' => self::fmt((float)($demand['shortage_qty'] ?? 0)), 'tone' => ((float)($demand['shortage_qty'] ?? 0) > 0 ? 'danger' : 'success')],
                ['key' => 'operator.parts.detail.metric.stock', 'value' => self::fmt((float)($stock['current_balance'] ?? $product['stock_balance'] ?? 0)), 'tone' => 'info'],
                ['key' => 'operator.parts.detail.metric.planned', 'value' => self::fmt((float)($planned['active_planned_qty'] ?? $planned['planned_qty'] ?? 0)), 'tone' => 'info'],
            ],
            'context' => [
                'next_due' => (string)($demand['next_due_at'] ?? ''),
                'low_coverage_orders' => (int)($demand['low_coverage_orders'] ?? 0),
                'due_within_48h' => (int)($demand['due_within_48h'] ?? 0),
                'ready_dispatch_qty' => self::fmt((float)($dispatch['ready_qty'] ?? 0)),
                'machine' => self::machineLabel($activeMachine),
                'machine_meta' => self::machineMeta($activeMachine),
                'owner' => self::ownerLabel($assignedUser),
                'owner_meta' => (string)($assignedUser['responsibility_label'] ?? ''),
                'supply_mode' => (string)($supply['supply_mode'] ?? 'in_house'),
                'fulfillment_mode' => (string)($supply['fulfillment_mode'] ?? ''),
                'requires_assembly' => !empty($workflow['requires_assembly']),
                'requires_qc' => !empty($supply['requires_ipm_qc']),
            ],
            'actions' => [
                ['key' => 'operator.parts.detail.action.plan', 'url' => $prefix . '/production?prefill_product_id=' . rawurlencode((string)$productId), 'tone' => 'ok'],
                ['key' => 'operator.parts.detail.action.orders', 'url' => $prefix . '/orders?q=' . rawurlencode($searchPart), 'tone' => 'default'],
                ['key' => 'operator.parts.detail.action.coverage', 'url' => $prefix . '/coverage', 'tone' => 'default'],
                ['key' => 'operator.parts.detail.action.dispatch', 'url' => $prefix . '/dispatch?product_id=' . rawurlencode((string)$productId), 'tone' => 'default'],
            ],
            'orders' => self::shapeOrders((array)($payload['order_timeline'] ?? [])),
            'activity' => self::shapeActivity((array)($payload['timeline'] ?? [])),
            'empty' => false,
            'error' => '',
        ];
    }

    private static function fmt(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }

    private static function tone(string $state): string
    {
        $state = strtolower(trim($state));
        return match ($state) {
            'blocked' => 'danger',
            'under_pressure' => 'warning',
            default => 'success',
        };
    }

    /**
     * @param array<string,mixed> $machine
     */
    private static function machineLabel(array $machine): string
    {
        $machineNo = trim((string)($machine['machine_no'] ?? ''));
        $machineName = trim((string)($machine['machine_name'] ?? ''));
        return trim($machineNo . ' ' . $machineName);
    }

    /**
     * @param array<string,mixed> $machine
     */
    private static function machineMeta(array $machine): string
    {
        return trim((string)($machine['section'] ?? '') . ((trim((string)($machine['status'] ?? '')) !== '') ? ' | ' . (string)$machine['status'] : ''));
    }

    /**
     * @param array<string,mixed> $user
     */
    private static function ownerLabel(array $user): string
    {
        return trim((string)($user['display_name'] ?? $user['email'] ?? ''));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,string>>
     */
    private static function shapeOrders(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || strtolower(trim((string)($row['source_type'] ?? 'daily_order'))) !== 'daily_order') {
                continue;
            }
            $out[] = [
                'date' => (string)($row['due_date'] ?? $row['required_date'] ?? $row['order_date'] ?? ''),
                'customer' => (string)($row['customer_name'] ?? ''),
                'qty' => self::fmt((float)($row['open_demand_qty'] ?? $row['qty'] ?? 0)),
                'coverage' => self::fmt((float)($row['coverage_pct'] ?? 0)) . '%',
                'status' => (string)($row['status'] ?? ''),
            ];
            if (count($out) >= 5) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,string>>
     */
    private static function shapeActivity(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'when' => (string)($row['when'] ?? ''),
                'type' => (string)($row['type'] ?? ''),
                'note' => (string)($row['title'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
            ];
            if (count($out) >= 5) {
                break;
            }
        }
        return $out;
    }
}

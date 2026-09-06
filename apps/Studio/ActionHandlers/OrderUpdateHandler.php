<?php
declare(strict_types=1);

namespace Apps\Studio\ActionHandlers;

use App\Core\DB;
use Apps\Studio\Authorization\StudioAuthorizationService;
use Apps\Studio\Repositories\OrdersRepository;
use Apps\Studio\Repositories\StudioSchemaGovernanceService;

require_once APP_ROOT . '/app/Core/DB.php';
require_once __DIR__ . '/../Authorization/StudioAuthorizationService.php';
require_once __DIR__ . '/../Repositories/OrdersRepository.php';
require_once __DIR__ . '/../Repositories/StudioSchemaGovernanceService.php';

final class OrderUpdateHandler implements StudioActionHandler
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function handle(array $payload, array $context): array
    {
        if (!StudioAuthorizationService::can('orders', 'update', $context)) {
            return ['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden', 'errors' => [], 'data' => []];
        }

        $orderNo = trim((string)($payload['order_no'] ?? ''));
        $customer = trim((string)($payload['customer'] ?? ''));
        $qtyRaw = trim((string)($payload['qty'] ?? ''));

        $errors = [];
        if ($orderNo === '') {
            $errors['order_no'] = 'required';
        }
        if ($customer !== '' && strlen($customer) > 120) {
            $errors['customer'] = 'too_long';
        }
        if ($qtyRaw !== '' && !is_numeric($qtyRaw)) {
            $errors['qty'] = 'invalid';
        }

        if ($errors !== []) {
            return ['ok' => false, 'code' => 'validation_failed', 'message' => 'validation_failed', 'errors' => $errors, 'data' => []];
        }

        $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($context['app_key'] ?? 'default'));
        StudioSchemaGovernanceService::ensureSchema($appKey);

        $update = [];
        if ($customer !== '') {
            $update['customer'] = $customer;
        }
        if ($qtyRaw !== '') {
            $update['qty'] = (float)$qtyRaw;
        }

        if ($update === []) {
            return ['ok' => false, 'code' => 'validation_failed', 'message' => 'validation_failed', 'errors' => ['payload' => 'empty'], 'data' => []];
        }

        try {
            return $this->withTransaction(function () use ($appKey, $context, $orderNo, $update): array {
                $authorizedContext = $context;
                $authorizedContext['_studio_authorized'] = true;
                $repo = new OrdersRepository($appKey, $authorizedContext);
                $affected = $repo->update(['order_no' => $orderNo], $update);
                if ($affected <= 0) {
                    return ['ok' => false, 'code' => 'lifecycle_blocked', 'message' => 'lifecycle_blocked', 'errors' => [], 'data' => []];
                }

                return ['ok' => true, 'code' => 'updated', 'message' => 'updated', 'errors' => [], 'data' => ['affected' => $affected, 'order_no' => $orderNo]];
            });
        } catch (\Throwable) {
            return ['ok' => false, 'code' => 'db_error', 'message' => 'db_error', 'errors' => [], 'data' => []];
        }
    }

    /**
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private function withTransaction(callable $fn)
    {
        $db = DB::conn();
        $db->begin_transaction();

        try {
            $result = $fn();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}

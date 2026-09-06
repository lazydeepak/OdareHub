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

final class OrderSubmitHandler implements StudioActionHandler
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function handle(array $payload, array $context): array
    {
        if (!StudioAuthorizationService::can('orders', 'create', $context)) {
            return [
                'ok' => false,
                'code' => 'forbidden',
                'message' => 'forbidden',
                'errors' => [],
                'data' => [],
            ];
        }

        $orderNo = trim((string)($payload['order_no'] ?? ''));
        $customer = trim((string)($payload['customer'] ?? ''));
        $qtyRaw = trim((string)($payload['qty'] ?? ''));
        $qty = is_numeric($qtyRaw) ? (float)$qtyRaw : null;

        $errors = [];
        if ($orderNo === '') {
            $errors['order_no'] = 'required';
        }
        if ($customer === '') {
            $errors['customer'] = 'required';
        }
        if ($qty === null || $qty <= 0) {
            $errors['qty'] = 'invalid';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'code' => 'validation_failed',
                'message' => 'validation_failed',
                'errors' => $errors,
                'data' => [],
            ];
        }

        if (strlen($orderNo) > 40) {
            $errors['order_no'] = 'too_long';
        }
        if (strlen($customer) > 120) {
            $errors['customer'] = 'too_long';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'code' => 'validation_failed',
                'message' => 'validation_failed',
                'errors' => $errors,
                'data' => [],
            ];
        }

        $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($context['app_key'] ?? 'default'));
        StudioSchemaGovernanceService::ensureSchema($appKey);

        try {
            return $this->withTransaction(function () use ($orderNo, $customer, $qty, $context, $appKey): array {
                $authorizedContext = $context;
                $authorizedContext['_studio_authorized'] = true;
                $repo = new OrdersRepository($appKey, $authorizedContext);
                $saved = $repo->insert([
                    'order_no' => $orderNo,
                    'customer' => $customer,
                    'qty' => $qty,
                    'status' => 'active',
                    'ignored_field' => 'ignored',
                ]);

                return [
                    'ok' => true,
                    'code' => 'accepted',
                    'message' => 'accepted',
                    'errors' => [],
                    'data' => [
                        'id' => (int)($saved['id'] ?? 0),
                        'order_no' => (string)($saved['order_no'] ?? ''),
                        'customer' => (string)($saved['customer'] ?? ''),
                        'qty' => (float)($saved['qty'] ?? 0),
                        'status' => (string)($saved['status'] ?? 'active'),
                        'handled_by' => 'OrderSubmitHandler',
                        'app_key' => $appKey,
                    ],
                ];
            });
        } catch (\Throwable) {
            return [
                'ok' => false,
                'code' => 'db_error',
                'message' => 'db_error',
                'errors' => [],
                'data' => [],
            ];
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

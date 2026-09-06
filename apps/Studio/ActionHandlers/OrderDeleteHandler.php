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

final class OrderDeleteHandler implements StudioActionHandler
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function handle(array $payload, array $context): array
    {
        if (!StudioAuthorizationService::can('orders', 'delete', $context)) {
            return ['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden', 'errors' => [], 'data' => []];
        }

        $orderNo = trim((string)($payload['order_no'] ?? ''));
        if ($orderNo === '') {
            return ['ok' => false, 'code' => 'validation_failed', 'message' => 'validation_failed', 'errors' => ['order_no' => 'required'], 'data' => []];
        }

        $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($context['app_key'] ?? 'default'));
        StudioSchemaGovernanceService::ensureSchema($appKey);

        try {
            return $this->withTransaction(function () use ($appKey, $context, $orderNo): array {
                $authorizedContext = $context;
                $authorizedContext['_studio_authorized'] = true;
                $repo = new OrdersRepository($appKey, $authorizedContext);
                $affected = $repo->softDelete(['order_no' => $orderNo]);
                return ['ok' => $affected > 0, 'code' => $affected > 0 ? 'deleted' : 'lifecycle_blocked', 'message' => $affected > 0 ? 'deleted' : 'lifecycle_blocked', 'errors' => [], 'data' => ['affected' => $affected, 'order_no' => $orderNo]];
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

<?php
declare(strict_types=1);

namespace Apps\Studio\DataProviders;

use Apps\Studio\Authorization\StudioAuthorizationService;
use Apps\Studio\Repositories\OrdersRepository;
use Apps\Studio\Repositories\StudioSchemaGovernanceService;
use Apps\Studio\Workflow\WorkflowService;

require_once __DIR__ . '/../Authorization/StudioAuthorizationService.php';
require_once __DIR__ . '/../Repositories/OrdersRepository.php';
require_once __DIR__ . '/../Repositories/StudioSchemaGovernanceService.php';
require_once __DIR__ . '/../Workflow/WorkflowService.php';

final class OrdersProvider implements StudioDataProvider
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function fetch(array $context): array
    {
        if (!StudioAuthorizationService::can('orders', 'view', $context)) {
            return [];
        }

        $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($context['app_key'] ?? 'default'));
        $authorizedContext = $context;
        $authorizedContext['_studio_authorized'] = true;

        try {
            $repo = new OrdersRepository($appKey, $authorizedContext);
            $rows = $repo->find([]);
            $rows = $this->decorateRowsWithWorkflow($rows, $context);
            return [
                'rows' => $rows,
                'schema_version' => $repo->schemaVersion(),
            ];
        } catch (\Throwable) {
            return ['rows' => [], 'schema_version' => 0];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private function decorateRowsWithWorkflow(array $rows, array $context): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $state = strtolower(trim((string)($row['state'] ?? 'draft')));
            if (!WorkflowService::isValidState('orders', $state)) {
                $state = 'draft';
            }

            $nextActions = WorkflowService::allowedNextStates('orders', $state, $context);
            $assignedTo = (int)($row['assigned_to'] ?? 0);
            $priority = strtolower(trim((string)($row['priority'] ?? 'medium')));
            if (!in_array($priority, ['high', 'medium', 'low'], true)) {
                $priority = 'medium';
            }
            $dueAt = trim((string)($row['due_at'] ?? ''));
            $isOverdue = false;
            if ($dueAt !== '' && $state !== 'completed') {
                $dueTs = strtotime($dueAt);
                $isOverdue = $dueTs !== false && $dueTs < time();
            }

            $row['state'] = $state;
            $row['assigned_to'] = $assignedTo > 0 ? $assignedTo : null;
            $row['priority'] = $priority;
            $row['due_at'] = $dueAt !== '' ? $dueAt : null;
            $row['is_overdue'] = $isOverdue;
            $row['next_actions'] = $nextActions;
            $row['next_actions_text'] = implode(',', $nextActions);
            $out[] = $row;
        }

        return $out;
    }
}

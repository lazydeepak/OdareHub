<?php
declare(strict_types=1);

namespace Apps\Studio\Repositories;

use App\Core\DB;
use Apps\Studio\Workflow\WorkflowService;

require_once APP_ROOT . '/app/Core/DB.php';
require_once __DIR__ . '/StudioRepository.php';
require_once __DIR__ . '/StudioSchemaGovernanceService.php';
require_once __DIR__ . '/../Workflow/WorkflowService.php';

final class OrdersRepository implements StudioRepository
{
    /** @var array<int,string> */
    private const ALLOWED_FIELDS = ['order_no', 'customer', 'qty', 'status', 'assigned_to', 'priority', 'due_at'];

    /** @var array<int,string> */
    private const ALLOWED_CRITERIA = ['id', 'order_no', 'customer', 'status', 'state', 'assigned_to'];

    private string $appKey;

    private string $table;

    /** @var array<string,mixed> */
    private array $context;

    private int $schemaVersion;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $appKey, array $context = [])
    {
        if (empty($context['_studio_authorized'])) {
            throw new \RuntimeException('forbidden');
        }

        $this->appKey = StudioSchemaGovernanceService::sanitizeAppKey($appKey);
        $this->context = $context;
        $schema = StudioSchemaGovernanceService::ensureSchema($this->appKey);
        $this->schemaVersion = (int)($schema['version'] ?? 0);
        $this->table = StudioSchemaGovernanceService::ordersTable($this->appKey);
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function insert(array $data): array
    {
        $sanitized = $this->sanitizeData($data);
        if ($sanitized === [] || !isset($sanitized['order_no'], $sanitized['customer'], $sanitized['qty'])) {
            throw new \RuntimeException('No allowed fields provided');
        }

        $sanitized['status'] = (string)($sanitized['status'] ?? 'active');
        $sanitized['state'] = 'draft';
        $sanitized['priority'] = (string)($sanitized['priority'] ?? 'medium');
        $sanitized['assigned_to'] = isset($sanitized['assigned_to']) ? (int)$sanitized['assigned_to'] : null;
        $sanitized['due_at'] = $sanitized['due_at'] ?? null;

        $columns = array_keys($sanitized);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

        $db = DB::conn();
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        $params = array_values($sanitized);
        $types = $this->paramTypes($params);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Insert failed');
        }

        $id = (int)$db->insert_id;
        $saved = [
            'id' => $id,
            'order_no' => (string)($sanitized['order_no'] ?? ''),
            'customer' => (string)($sanitized['customer'] ?? ''),
            'qty' => (float)($sanitized['qty'] ?? 0),
            'status' => (string)($sanitized['status'] ?? 'active'),
            'state' => (string)($sanitized['state'] ?? 'draft'),
            'assigned_to' => isset($sanitized['assigned_to']) ? (int)$sanitized['assigned_to'] : null,
            'priority' => (string)($sanitized['priority'] ?? 'medium'),
            'due_at' => isset($sanitized['due_at']) ? (string)$sanitized['due_at'] : null,
        ];

        StudioSchemaGovernanceService::logAudit($this->appKey, 'insert', 'orders', $saved, $this->context);
        return $saved;
    }

    /**
     * @param array<string,mixed> $criteria
     * @param array<string,mixed> $data
     */
    public function update(array $criteria, array $data): int
    {
        $safeCriteria = $this->sanitizeCriteria($criteria);
        $sanitized = $this->sanitizeData($data);
        if ($safeCriteria === [] || $sanitized === []) {
            return 0;
        }

        $set = [];
        $params = [];
        foreach ($sanitized as $column => $value) {
            $set[] = $column . ' = ?';
            $params[] = $value;
        }

        $where = [];
        foreach ($safeCriteria as $column => $value) {
            $where[] = $column . ' = ?';
            $params[] = $value;
        }
        $where[] = "status = 'active'";

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $where);
        $db = DB::conn();
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        $types = $this->paramTypes($params);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Update failed');
        }

        $affected = (int)$stmt->affected_rows;
        if ($affected > 0) {
            $audit = $sanitized;
            if (isset($safeCriteria['id'])) {
                $audit['entity_id'] = (int)$safeCriteria['id'];
            }
            StudioSchemaGovernanceService::logAudit($this->appKey, 'update', 'orders', $audit, $this->context);
        }

        return $affected;
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function find(array $criteria): array
    {
        $safeCriteria = $this->sanitizeCriteria($criteria);
        $includeArchived = !empty($criteria['include_archived']);

        $sql = 'SELECT id, order_no, customer, qty, status, state, assigned_to, priority, due_at, created_at, updated_at, deleted_at FROM ' . $this->table;
        $params = [];
        $where = [];

        if ($includeArchived) {
            $where[] = "status <> 'deleted'";
        } else {
            $where[] = "status IN ('active','inactive')";
        }

        if ($safeCriteria !== []) {
            foreach ($safeCriteria as $column => $value) {
                $where[] = $column . ' = ?';
                $params[] = $value;
            }
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT 200';

        $db = DB::conn();
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        if ($params !== []) {
            $types = $this->paramTypes($params);
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            throw new \RuntimeException('Find failed');
        }

        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * @param array<string,mixed> $criteria
     */
    public function softDelete(array $criteria): int
    {
        $safeCriteria = $this->sanitizeCriteria($criteria);
        if ($safeCriteria === []) {
            return 0;
        }

        $where = [];
        $params = [];
        foreach ($safeCriteria as $column => $value) {
            $where[] = $column . ' = ?';
            $params[] = $value;
        }
        $where[] = "status <> 'deleted'";

        $sql = "UPDATE {$this->table} SET status='deleted', deleted_at=NOW() WHERE " . implode(' AND ', $where);
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Delete failed');
        }

        if ($params !== []) {
            $types = $this->paramTypes($params);
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            throw new \RuntimeException('Delete failed');
        }

        $affected = (int)$stmt->affected_rows;
        if ($affected > 0) {
            $audit = ['entity_id' => (int)($safeCriteria['id'] ?? 0)] + $safeCriteria;
            StudioSchemaGovernanceService::logAudit($this->appKey, 'delete', 'orders', $audit, $this->context);
        }

        return $affected;
    }

    /**
     * @param array<string,mixed> $criteria
     */
    public function archive(array $criteria): int
    {
        return $this->setLifecycleStatus($criteria, 'archive', 'archived', "status IN ('active','inactive')", true);
    }

    /**
     * @param array<string,mixed> $criteria
     */
    public function restore(array $criteria): int
    {
        return $this->setLifecycleStatus($criteria, 'restore', 'active', "status IN ('archived','deleted')", false);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findOneByOrderNo(string $orderNo): ?array
    {
        $safeOrderNo = trim($orderNo);
        if ($safeOrderNo === '') {
            return null;
        }

        $sql = 'SELECT id, order_no, customer, qty, status, state, assigned_to, priority, due_at, created_at, updated_at, deleted_at FROM ' . $this->table . ' WHERE order_no = ? LIMIT 1';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        $stmt->bind_param('s', $safeOrderNo);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Find failed');
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return is_array($row) ? $row : null;
    }

    public function updateState(string $orderNo, string $newState): int
    {
        $safeOrderNo = trim($orderNo);
        $targetState = strtolower(trim($newState));
        if ($safeOrderNo === '' || !WorkflowService::isValidState('orders', $targetState)) {
            return 0;
        }

        $current = $this->findOneByOrderNo($safeOrderNo);
        if (!is_array($current)) {
            return 0;
        }

        $lifecycleStatus = strtolower(trim((string)($current['status'] ?? '')));
        if ($lifecycleStatus !== 'active') {
            return 0;
        }

        $fromState = strtolower(trim((string)($current['state'] ?? 'draft')));
        if (!WorkflowService::canTransition('orders', $fromState, $targetState)) {
            return 0;
        }

        $sql = 'UPDATE ' . $this->table . ' SET state = ? WHERE order_no = ? AND status = \'active\' AND state = ?';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        $stmt->bind_param('sss', $targetState, $safeOrderNo, $fromState);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Update failed');
        }

        return (int)$stmt->affected_rows;
    }

    public function claimTask(string $orderNo, int $userId): int
    {
        $safeOrderNo = trim($orderNo);
        if ($safeOrderNo === '' || $userId <= 0) {
            return 0;
        }

        $sql = 'UPDATE ' . $this->table . " SET assigned_to = ? WHERE order_no = ? AND status = 'active' AND (assigned_to IS NULL OR assigned_to = 0)";
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        $stmt->bind_param('is', $userId, $safeOrderNo);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Update failed');
        }

        return (int)$stmt->affected_rows;
    }

    public function assignTask(string $orderNo, int $assigneeUserId): int
    {
        $safeOrderNo = trim($orderNo);
        if ($safeOrderNo === '' || $assigneeUserId <= 0) {
            return 0;
        }

        $sql = 'UPDATE ' . $this->table . " SET assigned_to = ? WHERE order_no = ? AND status = 'active'";
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        $stmt->bind_param('is', $assigneeUserId, $safeOrderNo);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Update failed');
        }

        return (int)$stmt->affected_rows;
    }

    public function releaseTask(string $orderNo): int
    {
        $safeOrderNo = trim($orderNo);
        if ($safeOrderNo === '') {
            return 0;
        }

        $sql = 'UPDATE ' . $this->table . " SET assigned_to = NULL WHERE order_no = ? AND status = 'active' AND assigned_to IS NOT NULL";
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed');
        }

        $stmt->bind_param('s', $safeOrderNo);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Update failed');
        }

        return (int)$stmt->affected_rows;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function sanitizeData(array $input): array
    {
        $allowed = array_flip(self::ALLOWED_FIELDS);
        $safe = array_intersect_key($input, $allowed);

        $out = [];
        foreach ($safe as $key => $value) {
            if ($key === 'qty') {
                if (!is_numeric((string)$value)) {
                    continue;
                }
                $out[$key] = (float)$value;
                continue;
            }
            if ($key === 'assigned_to') {
                if ($value === null || $value === '' || (int)$value <= 0) {
                    $out[$key] = null;
                    continue;
                }
                $out[$key] = (int)$value;
                continue;
            }
            if ($key === 'priority') {
                $priority = strtolower(trim((string)$value));
                if (!in_array($priority, ['high', 'medium', 'low'], true)) {
                    $priority = 'medium';
                }
                $out[$key] = $priority;
                continue;
            }
            if ($key === 'due_at') {
                $dueAt = trim((string)$value);
                if ($dueAt === '') {
                    $out[$key] = null;
                    continue;
                }

                $timestamp = strtotime($dueAt);
                if ($timestamp === false) {
                    continue;
                }

                $out[$key] = gmdate('Y-m-d H:i:s', $timestamp);
                continue;
            }
            if ($key === 'status') {
                $state = strtolower(trim((string)$value));
                if (!StudioSchemaGovernanceService::isValidLifecycleState($state)) {
                    continue;
                }
                $out[$key] = $state;
                continue;
            }
            $out[$key] = trim((string)$value);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<string,mixed>
     */
    private function sanitizeCriteria(array $criteria): array
    {
        $allowed = array_flip(self::ALLOWED_CRITERIA);
        $safe = array_intersect_key($criteria, $allowed);
        $out = [];

        foreach ($safe as $key => $value) {
            if ($key === 'id') {
                $id = (int)$value;
                if ($id > 0) {
                    $out[$key] = $id;
                }
                continue;
            }
            if ($key === 'assigned_to') {
                $assignedTo = (int)$value;
                if ($assignedTo > 0) {
                    $out[$key] = $assignedTo;
                }
                continue;
            }
            $val = trim((string)$value);
            if ($val !== '') {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    /**
     * @param array<int,mixed> $params
     */
    private function paramTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        return $types;
    }

    /**
     * @param array<string,mixed> $criteria
     */
    private function setLifecycleStatus(array $criteria, string $auditAction, string $targetStatus, string $statusCondition, bool $setDeletedAt): int
    {
        $safeCriteria = $this->sanitizeCriteria($criteria);
        if ($safeCriteria === []) {
            return 0;
        }

        $where = [];
        $params = [];
        foreach ($safeCriteria as $column => $value) {
            $where[] = $column . ' = ?';
            $params[] = $value;
        }
        $where[] = $statusCondition;

        $setDeletedExpr = $setDeletedAt ? 'NOW()' : 'NULL';
        $sql = "UPDATE {$this->table} SET status='{$targetStatus}', deleted_at={$setDeletedExpr} WHERE " . implode(' AND ', $where);
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Lifecycle update failed');
        }

        if ($params !== []) {
            $types = $this->paramTypes($params);
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            throw new \RuntimeException('Lifecycle update failed');
        }

        $affected = (int)$stmt->affected_rows;
        if ($affected > 0) {
            $audit = ['status' => $targetStatus, 'entity_id' => (int)($safeCriteria['id'] ?? 0)] + $safeCriteria;
            StudioSchemaGovernanceService::logAudit($this->appKey, $auditAction, 'orders', $audit, $this->context);
        }

        return $affected;
    }
}

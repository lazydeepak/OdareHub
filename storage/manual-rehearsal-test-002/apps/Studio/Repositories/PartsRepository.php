<?php
declare(strict_types=1);

namespace Apps\Studio\Repositories;

use App\Core\DB;

require_once APP_ROOT . '/app/Core/DB.php';
require_once __DIR__ . '/StudioRepository.php';
require_once __DIR__ . '/StudioSchemaGovernanceService.php';

final class PartsRepository implements StudioRepository
{
    /** @var array<int,string> */
    private const ALLOWED_FIELDS = ['part_code', 'part_name', 'uom', 'on_hand'];

    /** @var array<int,string> */
    private const ALLOWED_CRITERIA = ['id', 'part_code'];

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
        $this->table = StudioSchemaGovernanceService::partsTable($this->appKey);
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
        if ($sanitized === [] || !isset($sanitized['part_code'], $sanitized['part_name'], $sanitized['uom'])) {
            throw new \RuntimeException('No allowed fields provided');
        }

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

        $saved = [
            'id' => (int)$db->insert_id,
            'part_code' => (string)($sanitized['part_code'] ?? ''),
            'part_name' => (string)($sanitized['part_name'] ?? ''),
            'uom' => (string)($sanitized['uom'] ?? ''),
            'on_hand' => (float)($sanitized['on_hand'] ?? 0),
        ];

        StudioSchemaGovernanceService::logAudit($this->appKey, 'insert', 'parts', $saved, $this->context);
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
            StudioSchemaGovernanceService::logAudit($this->appKey, 'update', 'parts', $audit, $this->context);
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

        $sql = 'SELECT id, part_code, part_name, uom, on_hand, status, deleted_at FROM ' . $this->table;
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
            StudioSchemaGovernanceService::logAudit($this->appKey, 'delete', 'parts', $audit, $this->context);
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
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function sanitizeData(array $input): array
    {
        $allowed = array_flip(self::ALLOWED_FIELDS);
        $safe = array_intersect_key($input, $allowed);

        $out = [];
        foreach ($safe as $key => $value) {
            if ($key === 'on_hand') {
                if (!is_numeric((string)$value)) {
                    continue;
                }
                $out[$key] = (float)$value;
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
            StudioSchemaGovernanceService::logAudit($this->appKey, $auditAction, 'parts', $audit, $this->context);
        }

        return $affected;
    }
}

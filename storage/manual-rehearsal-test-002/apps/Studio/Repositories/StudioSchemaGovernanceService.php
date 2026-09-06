<?php
declare(strict_types=1);

namespace Apps\Studio\Repositories;

use App\Core\DB;

require_once APP_ROOT . '/app/Core/DB.php';

final class StudioSchemaGovernanceService
{
    private const TARGET_VERSION = 5;

    /** @var array<int,string> */
    private const LIFECYCLE_STATES = ['active', 'inactive', 'archived', 'deleted'];

    private const REGISTRY_PATH = APP_ROOT . '/storage/appstudio/schema_registry.json';

    /**
     * @return array<string,mixed>
     */
    public static function ensureSchema(string $appKey): array
    {
        $safeAppKey = self::sanitizeAppKey($appKey);
        $registry = self::loadRegistry();
        $entryKey = self::registryEntryKey($safeAppKey);

        $entry = is_array($registry[$entryKey] ?? null) ? $registry[$entryKey] : null;
        $currentVersion = (int)($entry['version'] ?? 0);

        if ($currentVersion <= 0) {
            self::createBaseSchemaV1($safeAppKey);
            $currentVersion = 1;
            $entry = [
                'version' => 1,
                'tables' => ['orders', 'parts', 'audit_log'],
                'updated_at' => gmdate('c'),
            ];
        }

        if ($currentVersion < self::TARGET_VERSION) {
            self::migrateSchema($safeAppKey, $currentVersion, self::TARGET_VERSION);
            $currentVersion = self::TARGET_VERSION;
        }

        $entry = [
            'version' => $currentVersion,
            'tables' => ['orders', 'parts', 'audit_log'],
            'updated_at' => gmdate('c'),
        ];
        $registry[$entryKey] = $entry;
        self::persistRegistry($registry);

        return [
            'app_key' => $safeAppKey,
            'version' => $currentVersion,
            'tables' => $entry['tables'],
        ];
    }

    public static function migrateSchema(string $appKey, int $fromVersion, int $toVersion): void
    {
        $safeAppKey = self::sanitizeAppKey($appKey);
        if ($toVersion <= $fromVersion) {
            return;
        }

        for ($version = $fromVersion + 1; $version <= $toVersion; $version++) {
            if ($version === 2) {
                self::applyMigrationV2($safeAppKey);
            } elseif ($version === 3) {
                self::applyMigrationV3($safeAppKey);
            } elseif ($version === 4) {
                self::applyMigrationV4($safeAppKey);
            } elseif ($version === 5) {
                self::applyMigrationV5($safeAppKey);
            }
        }
    }

    public static function getSchemaVersion(string $appKey): int
    {
        $safeAppKey = self::sanitizeAppKey($appKey);
        $registry = self::loadRegistry();
        $entry = $registry[self::registryEntryKey($safeAppKey)] ?? null;
        if (!is_array($entry)) {
            return 0;
        }

        return (int)($entry['version'] ?? 0);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $context
     */
    public static function logAudit(string $appKey, string $action, string $entity, array $data, array $context): void
    {
        $safeAppKey = self::sanitizeAppKey($appKey);
        self::ensureSchema($safeAppKey);

        $auditTable = self::auditTable($safeAppKey);
        $safeAction = in_array($action, ['insert', 'update', 'archive', 'delete', 'restore', 'workflow_transition', 'claim_task', 'assign_task', 'release_task', 'sla_overdue'], true) ? $action : 'update';
        $safeEntity = in_array($entity, ['orders', 'parts'], true) ? $entity : 'orders';

        $payload = self::sanitizeAuditPayload($data);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson)) {
            $payloadJson = '{}';
        }

        $entityId = (int)($data['id'] ?? $data['entity_id'] ?? 0);
        $userId = (int)($context['user']['id'] ?? $context['user_id'] ?? 0);

        $sql = 'INSERT INTO ' . $auditTable . ' (action, entity, entity_id, payload_json, user_id) VALUES (?, ?, ?, ?, ?)';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('audit_log_error');
        }

        $stmt->bind_param('ssisi', $safeAction, $safeEntity, $entityId, $payloadJson, $userId);
        if (!$stmt->execute()) {
            throw new \RuntimeException('audit_log_error');
        }
    }

    public static function sanitizeAppKey(string $appKey): string
    {
        $safe = strtolower(trim($appKey));
        $safe = str_replace('-', '_', $safe);
        $safe = preg_replace('/[^a-z0-9_]/', '', $safe) ?? '';
        $safe = trim($safe, '_');
        if ($safe === '') {
            $safe = 'default';
        }

        return substr($safe, 0, 32);
    }

    public static function ordersTable(string $appKey): string
    {
        return 'studio_' . self::sanitizeAppKey($appKey) . '_orders';
    }

    public static function partsTable(string $appKey): string
    {
        return 'studio_' . self::sanitizeAppKey($appKey) . '_parts';
    }

    public static function auditTable(string $appKey): string
    {
        return 'studio_' . self::sanitizeAppKey($appKey) . '_audit_log';
    }

    private static function createBaseSchemaV1(string $appKey): void
    {
        self::createOrdersTableV1($appKey);
        self::createPartsTableV1($appKey);
        self::createAuditTable($appKey);
    }

    private static function createOrdersTableV1(string $appKey): void
    {
        $table = self::ordersTable($appKey);
        DB::query(
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_no VARCHAR(50) NOT NULL,
                customer VARCHAR(100) NOT NULL,
                qty DECIMAL(12,2) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_order_no (order_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private static function createPartsTableV1(string $appKey): void
    {
        $table = self::partsTable($appKey);
        DB::query(
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                part_code VARCHAR(50) NOT NULL,
                part_name VARCHAR(120) NOT NULL,
                uom VARCHAR(20) NOT NULL,
                on_hand DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_part_code (part_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private static function createAuditTable(string $appKey): void
    {
        $table = self::auditTable($appKey);
        DB::query(
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(20) NOT NULL,
                entity VARCHAR(40) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                payload_json LONGTEXT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_entity_created (entity, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private static function applyMigrationV2(string $appKey): void
    {
        $ordersTable = self::ordersTable($appKey);

        $hasStatus = DB::fetchOne('SHOW COLUMNS FROM ' . $ordersTable . " LIKE 'status'");
        if (!$hasStatus) {
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT \'open\' AFTER qty');
        }

        $hasUpdated = DB::fetchOne('SHOW COLUMNS FROM ' . $ordersTable . " LIKE 'updated_at'");
        if (!$hasUpdated) {
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at');
            DB::query('UPDATE ' . $ordersTable . ' SET updated_at = created_at WHERE updated_at IS NULL');
            DB::query('ALTER TABLE ' . $ordersTable . ' MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        }
    }

    private static function applyMigrationV3(string $appKey): void
    {
        $ordersTable = self::ordersTable($appKey);
        $partsTable = self::partsTable($appKey);

        $hasOrdersDeletedAt = DB::fetchOne('SHOW COLUMNS FROM ' . $ordersTable . " LIKE 'deleted_at'");
        if (!$hasOrdersDeletedAt) {
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at');
        }

        DB::query(
            'UPDATE ' . $ordersTable
            . " SET status = 'active' WHERE status NOT IN ('active','inactive','archived','deleted') OR status IS NULL OR status = ''"
        );

        $hasPartsStatus = DB::fetchOne('SHOW COLUMNS FROM ' . $partsTable . " LIKE 'status'");
        if (!$hasPartsStatus) {
            DB::query('ALTER TABLE ' . $partsTable . " ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER on_hand");
        }

        $hasPartsUpdatedAt = DB::fetchOne('SHOW COLUMNS FROM ' . $partsTable . " LIKE 'updated_at'");
        if (!$hasPartsUpdatedAt) {
            DB::query('ALTER TABLE ' . $partsTable . ' ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at');
            DB::query('UPDATE ' . $partsTable . ' SET updated_at = created_at WHERE updated_at IS NULL');
            DB::query('ALTER TABLE ' . $partsTable . ' MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        }

        $hasPartsDeletedAt = DB::fetchOne('SHOW COLUMNS FROM ' . $partsTable . " LIKE 'deleted_at'");
        if (!$hasPartsDeletedAt) {
            DB::query('ALTER TABLE ' . $partsTable . ' ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at');
        }

        DB::query(
            'UPDATE ' . $partsTable
            . " SET status = 'active' WHERE status NOT IN ('active','inactive','archived','deleted') OR status IS NULL OR status = ''"
        );
    }

    private static function applyMigrationV4(string $appKey): void
    {
        $ordersTable = self::ordersTable($appKey);

        $hasState = DB::fetchOne('SHOW COLUMNS FROM ' . $ordersTable . " LIKE 'state'");
        if (!$hasState) {
            DB::query('ALTER TABLE ' . $ordersTable . " ADD COLUMN state VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER status");
        }

        DB::query(
            'UPDATE ' . $ordersTable
            . " SET state = 'draft' WHERE state NOT IN ('draft','approved','processing','completed','cancelled') OR state IS NULL OR state = ''"
        );
    }

    private static function applyMigrationV5(string $appKey): void
    {
        $ordersTable = self::ordersTable($appKey);

        $hasAssignedTo = DB::fetchOne('SHOW COLUMNS FROM ' . $ordersTable . " LIKE 'assigned_to'");
        if (!$hasAssignedTo) {
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD COLUMN assigned_to BIGINT UNSIGNED NULL DEFAULT NULL AFTER state');
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD INDEX idx_assigned_to (assigned_to)');
        }

        $hasPriority = DB::fetchOne('SHOW COLUMNS FROM ' . $ordersTable . " LIKE 'priority'");
        if (!$hasPriority) {
            DB::query('ALTER TABLE ' . $ordersTable . " ADD COLUMN priority VARCHAR(10) NOT NULL DEFAULT 'medium' AFTER assigned_to");
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD INDEX idx_priority (priority)');
        }

        $hasDueAt = DB::fetchOne('SHOW COLUMNS FROM ' . $ordersTable . " LIKE 'due_at'");
        if (!$hasDueAt) {
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD COLUMN due_at TIMESTAMP NULL DEFAULT NULL AFTER priority');
            DB::query('ALTER TABLE ' . $ordersTable . ' ADD INDEX idx_due_at (due_at)');
        }

        DB::query(
            'UPDATE ' . $ordersTable
            . " SET priority = 'medium' WHERE priority NOT IN ('high','medium','low') OR priority IS NULL OR priority = ''"
        );
    }

    private static function registryEntryKey(string $safeAppKey): string
    {
        return 'studio_' . $safeAppKey;
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadRegistry(): array
    {
        $path = self::REGISTRY_PATH;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!is_file($path)) {
            file_put_contents($path, "{}\n", LOCK_EX);
        }

        $raw = (string)file_get_contents($path);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $registry
     */
    private static function persistRegistry(array $registry): void
    {
        $path = self::REGISTRY_PATH;
        $json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        $tmpPath = $path . '.tmp';
        file_put_contents($tmpPath, $json . "\n", LOCK_EX);
        rename($tmpPath, $path);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function sanitizeAuditPayload(array $data): array
    {
        $dropKeys = [
            'password',
            'password_hash',
            'token',
            'csrf',
            'secret',
        ];

        $out = [];
        foreach ($data as $key => $value) {
            $name = strtolower(trim((string)$key));
            if ($name === '' || in_array($name, $dropKeys, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $out[$key] = '[array]';
                continue;
            }
            $out[$key] = '[object]';
        }

        return $out;
    }

    public static function isValidLifecycleState(string $state): bool
    {
        return in_array(strtolower(trim($state)), self::LIFECYCLE_STATES, true);
    }
}

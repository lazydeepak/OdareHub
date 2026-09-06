<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

use App\Core\DB;

require_once APP_ROOT . '/app/Core/DB.php';

final class StudioNotificationService
{
    public static function ensureTable(): void
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS studio_notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(40) NOT NULL,
                message VARCHAR(255) NOT NULL,
                entity VARCHAR(64) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_unread_created (user_id, is_read, created_at),
                INDEX idx_entity (entity, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function notify(int $userId, string $message, string $entity, int $entityId = 0, string $type = 'workflow'): bool
    {
        self::ensureTable();

        if ($userId <= 0) {
            return false;
        }

        $safeMessage = trim($message);
        $safeEntity = trim($entity);
        $safeType = trim($type);
        if ($safeMessage === '' || $safeEntity === '' || $safeType === '') {
            return false;
        }

        $safeMessage = substr($safeMessage, 0, 255);
        $safeEntity = substr($safeEntity, 0, 64);
        $safeType = substr($safeType, 0, 40);

        $sql = 'INSERT INTO studio_notifications (user_id, type, message, entity, entity_id, is_read) VALUES (?, ?, ?, ?, ?, 0)';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('isssi', $userId, $safeType, $safeMessage, $safeEntity, $entityId);
        return (bool)$stmt->execute();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function getUserNotifications(int $userId, int $limit = 20): array
    {
        self::ensureTable();

        if ($userId <= 0) {
            return [];
        }

        $safeLimit = max(1, min(100, $limit));
        $sql = 'SELECT id, user_id, type, message, entity, entity_id, is_read, created_at FROM studio_notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . $safeLimit;
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            return [];
        }

        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function getUnreadCount(int $userId): int
    {
        self::ensureTable();

        if ($userId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS c FROM studio_notifications WHERE user_id = ? AND is_read = 0';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            return 0;
        }

        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['c'] ?? 0);
    }

    public static function markRead(int $notificationId, int $userId = 0): bool
    {
        self::ensureTable();

        if ($notificationId <= 0) {
            return false;
        }

        if ($userId > 0) {
            $sql = 'UPDATE studio_notifications SET is_read = 1 WHERE id = ? AND user_id = ? LIMIT 1';
            $stmt = DB::conn()->prepare($sql);
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('ii', $notificationId, $userId);
            if (!$stmt->execute()) {
                return false;
            }

            return (int)$stmt->affected_rows > 0;
        }

        $sql = 'UPDATE studio_notifications SET is_read = 1 WHERE id = ? LIMIT 1';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $notificationId);
        if (!$stmt->execute()) {
            return false;
        }

        return (int)$stmt->affected_rows > 0;
    }

    public static function notifyOncePerDay(int $userId, string $message, string $entity, int $entityId = 0, string $type = 'workflow'): bool
    {
        self::ensureTable();

        if ($userId <= 0) {
            return false;
        }

        $safeEntity = substr(trim($entity), 0, 64);
        $safeType = substr(trim($type), 0, 40);
        if ($safeEntity === '' || $safeType === '') {
            return false;
        }

        $sql = 'SELECT id FROM studio_notifications WHERE user_id = ? AND type = ? AND entity = ? AND entity_id = ? AND created_at >= UTC_DATE() LIMIT 1';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('issi', $userId, $safeType, $safeEntity, $entityId);
        if (!$stmt->execute()) {
            return false;
        }

        $existing = $stmt->get_result();
        if ($existing && $existing->fetch_assoc()) {
            return false;
        }

        return self::notify($userId, $message, $safeEntity, $entityId, $safeType);
    }

    /**
     * @return array<int,int>
     */
    public static function recipientsForRole(string $role, int $excludeUserId = 0): array
    {
        self::ensureTable();

        $targetRole = strtolower(trim($role));
        if (!in_array($targetRole, ['operator', 'manager', 'admin'], true)) {
            return [];
        }

        $where = '';
        if ($targetRole === 'operator') {
            $where = "u.authority_role = 'app_user'";
        } elseif ($targetRole === 'manager') {
            $where = "u.authority_role IN ('app_admin', 'platform_admin')";
        } else {
            $where = "u.authority_role IN ('platform_admin')";
        }

        $sql = 'SELECT u.id FROM users u WHERE u.account_status = ? AND ' . $where . ' ORDER BY u.id ASC LIMIT 200';
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $active = 'active';
        $stmt->bind_param('s', $active);
        if (!$stmt->execute()) {
            return [];
        }

        $result = $stmt->get_result();
        $ids = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if ($excludeUserId > 0 && $id === $excludeUserId) {
                    continue;
                }
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}

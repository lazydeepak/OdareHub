<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use App\Core\DB;

final class DesignStudioService
{
    // ── Catalog helpers ──────────────────────────────────────────────────────

    /** @return array<int,array<string,string>> */
    public static function targetRoles(): array
    {
        return [
            ['role_key' => 'operator',           'label_key' => 'ops.design_studio.role.operator'],
            ['role_key' => 'production_leader',   'label_key' => 'ops.design_studio.role.production_leader'],
            ['role_key' => 'qc_leader',           'label_key' => 'ops.design_studio.role.qc_leader'],
            ['role_key' => 'assembly_leader',     'label_key' => 'ops.design_studio.role.assembly_leader'],
            ['role_key' => 'dispatch_leader',     'label_key' => 'ops.design_studio.role.dispatch_leader'],
            ['role_key' => 'platform_admin',      'label_key' => 'ops.design_studio.role.platform_admin'],
        ];
    }

    /** @return array<int,array<string,string>> */
    public static function targetSurfaces(): array
    {
        return [
            ['surface_key' => 'operator_dashboard', 'label_key' => 'ops.design_studio.surface.operator_dashboard'],
            ['surface_key' => 'admin_dashboard',     'label_key' => 'ops.design_studio.surface.admin_dashboard'],
            ['surface_key' => 'display',             'label_key' => 'ops.design_studio.surface.display'],
        ];
    }

    // ── Table lifecycle ──────────────────────────────────────────────────────

    public static function ensureTables(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS platform_design_compositions (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name                 VARCHAR(190)    NOT NULL,
            description          VARCHAR(500)    DEFAULT NULL,
            target_role          VARCHAR(80)     NOT NULL,
            target_surface       VARCHAR(80)     NOT NULL,
            status               VARCHAR(20)     NOT NULL DEFAULT 'draft',
            config_json          JSON            DEFAULT NULL,
            created_by_user_id   BIGINT UNSIGNED DEFAULT NULL,
            created_by_email     VARCHAR(190)    DEFAULT NULL,
            updated_by_user_id   BIGINT UNSIGNED DEFAULT NULL,
            updated_by_email     VARCHAR(190)    DEFAULT NULL,
            published_at         DATETIME        DEFAULT NULL,
            archived_at          DATETIME        DEFAULT NULL,
            created_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
            updated_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pdc_status (status),
            KEY idx_pdc_role_surface (target_role, target_surface)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // ── Query ────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int,filtered:int,summary:array<string,int>}
     */
    public static function listCompositions(array $filters, int $page, int $perPage): array
    {
        $status        = trim((string)($filters['status']         ?? 'all'));
        $roleFilter    = trim((string)($filters['target_role']    ?? 'all'));
        $surfaceFilter = trim((string)($filters['target_surface'] ?? 'all'));
        $q             = trim((string)($filters['q']              ?? ''));

        $where  = [];
        $params = [];

        $validStatuses = ['draft', 'published', 'archived'];
        if (in_array($status, $validStatuses, true)) {
            $where[]  = 'status = ?';
            $params[] = $status;
        }

        $validRoles = array_column(self::targetRoles(), 'role_key');
        if (in_array($roleFilter, $validRoles, true)) {
            $where[]  = 'target_role = ?';
            $params[] = $roleFilter;
        }

        $validSurfaces = array_column(self::targetSurfaces(), 'surface_key');
        if (in_array($surfaceFilter, $validSurfaces, true)) {
            $where[]  = 'target_surface = ?';
            $params[] = $surfaceFilter;
        }

        if ($q !== '') {
            $where[]  = '(name LIKE ? OR description LIKE ?)';
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT id, name, description, target_role, target_surface, status,
                       created_by_email, updated_by_email, published_at, archived_at, created_at, updated_at
                FROM platform_design_compositions';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $countSql = 'SELECT COUNT(*) FROM platform_design_compositions'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '');
        $filtered = (int)DB::fetchOne($countSql, $params);

        $total = (int)DB::fetchOne('SELECT COUNT(*) FROM platform_design_compositions', []);

        $summaryRaw = DB::fetchAll('SELECT status, COUNT(*) AS cnt FROM platform_design_compositions GROUP BY status', []);
        $summary    = ['all' => $total, 'draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($summaryRaw as $row) {
            $s = (string)($row['status'] ?? '');
            if (isset($summary[$s])) {
                $summary[$s] = (int)($row['cnt'] ?? 0);
            }
        }

        $offset = max(0, ($page - 1) * $perPage);
        $sql   .= ' ORDER BY FIELD(status,"published","draft","archived"), updated_at DESC, id DESC'
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $rows = DB::fetchAll($sql, $params);

        return [
            'rows'     => is_array($rows) ? $rows : [],
            'total'    => $total,
            'filtered' => $filtered,
            'summary'  => $summary,
        ];
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $data
     */
    public static function createComposition(array $data, int $userId, string $email): int
    {
        $name          = trim((string)($data['name']           ?? ''));
        $description   = trim((string)($data['description']   ?? ''));
        $targetRole    = trim((string)($data['target_role']    ?? ''));
        $targetSurface = trim((string)($data['target_surface'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('ops.design_studio.error.name_required');
        }

        $validRoles = array_column(self::targetRoles(), 'role_key');
        if (!in_array($targetRole, $validRoles, true)) {
            throw new \InvalidArgumentException('ops.design_studio.error.invalid_role');
        }

        $validSurfaces = array_column(self::targetSurfaces(), 'surface_key');
        if (!in_array($targetSurface, $validSurfaces, true)) {
            throw new \InvalidArgumentException('ops.design_studio.error.invalid_surface');
        }

        DB::query(
            "INSERT INTO platform_design_compositions
                (name, description, target_role, target_surface, status, created_by_user_id, created_by_email, updated_by_user_id, updated_by_email)
             VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?)",
            [
                $name,
                $description !== '' ? $description : null,
                $targetRole,
                $targetSurface,
                $userId > 0 ? $userId : null,
                $email !== '' ? $email : null,
                $userId > 0 ? $userId : null,
                $email !== '' ? $email : null,
            ]
        );

        return (int)DB::lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $row = DB::fetchOne(
            'SELECT * FROM platform_design_compositions WHERE id = ? LIMIT 1',
            [$id]
        );
        return is_array($row) ? $row : null;
    }

    public static function changeStatus(int $id, string $newStatus, int $userId, string $email): void
    {
        $validStatuses = ['draft', 'published', 'archived'];
        if (!in_array($newStatus, $validStatuses, true)) {
            throw new \InvalidArgumentException('ops.design_studio.error.invalid_status');
        }

        $extra       = [];
        $extraParams = [];
        if ($newStatus === 'published') {
            $extra[] = ', published_at = NOW()';
            $extra[] = ', archived_at = NULL';
        } elseif ($newStatus === 'archived') {
            $extra[] = ', archived_at = NOW()';
        } elseif ($newStatus === 'draft') {
            $extra[] = ', published_at = NULL';
            $extra[] = ', archived_at = NULL';
        }

        DB::query(
            'UPDATE platform_design_compositions
             SET status = ?, updated_by_user_id = ?, updated_by_email = ?'
            . implode('', $extra)
            . ' WHERE id = ?',
            array_merge([$newStatus, $userId > 0 ? $userId : null, $email !== '' ? $email : null], $extraParams, [$id])
        );
    }

    /**
     * Update composition name, description, and slot assignments.
     *
     * @param array<string,mixed> $data  Keys: name, description, slots (array<zone,int[]>)
     */
    public static function updateComposition(int $id, array $data, int $userId, string $email): void
    {
        $name        = trim((string)($data['name']        ?? ''));
        $description = trim((string)($data['description'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('ops.design_studio.error.name_required');
        }

        $validZones = array_column(WidgetBuilderService::placementZones(), 'placement_zone');
        $slots      = [];
        $rawSlots   = isset($data['slots']) && is_array($data['slots']) ? $data['slots'] : [];
        foreach ($validZones as $zone) {
            $zoneIds = [];
            if (isset($rawSlots[$zone]) && is_array($rawSlots[$zone])) {
                foreach ($rawSlots[$zone] as $bpId) {
                    $bpIdInt = (int)$bpId;
                    if ($bpIdInt > 0) {
                        $zoneIds[] = $bpIdInt;
                    }
                }
            }
            $slots[$zone] = array_values(array_unique($zoneIds));
        }

        DB::query(
            'UPDATE platform_design_compositions
             SET name = ?, description = ?, config_json = ?,
                 updated_by_user_id = ?, updated_by_email = ?
             WHERE id = ?',
            [
                $name,
                $description !== '' ? $description : null,
                json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE),
                $userId > 0 ? $userId : null,
                $email !== '' ? $email : null,
                $id,
            ]
        );
    }

    /**
     * Return published blueprints grouped by placement_zone for the slot picker.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function publishedBlueprintsByZone(): array
    {
        $rows = DB::fetchAll(
            "SELECT id, widget_key, title_key, description_key, template_type, placement_zone, dataset_key
             FROM platform_widget_blueprints
             WHERE status = 'published'
             ORDER BY placement_zone, title_key, id",
            []
        );
        $grouped = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $zone = trim((string)($row['placement_zone'] ?? ''));
                if ($zone !== '') {
                    $grouped[$zone][] = $row;
                }
            }
        }
        return $grouped;
    }
}

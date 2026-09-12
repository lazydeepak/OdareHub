<?php

declare(strict_types=1);

namespace Apps\Manufacturing\Modules\Bom;

use App\Core\Auth;
use App\Core\DB;

/**
 * Manufacturing-owned BOM/Recipe foundation.
 *
 * consumed tables: manufacturing_bom + manufacturing_bom_line (owned by this module).
 * finished_item_ref / component_item_ref are OPAQUE canonical item references per
 * docs/shared-items-foundation/canonical-item-contract.md. No FK to `products` and no
 * runtime dependency on a Shared Items registry: refs are validated as positive
 * integers, uniqueness against products is enforced, and unresolvable refs are surfaced
 * (never silently dropped) via integrityIssues().
 */
final class BomService
{
    public const STATUSES = ['draft', 'released', 'superseded', 'archived'];

    /**
     * @return array{ready:bool,message:string,missing:array<int,string>}
     */
    public static function availabilityStatus(): array
    {
        self::ensureSchema();

        $requiredTables = ['manufacturing_bom', 'manufacturing_bom_line'];
        $missing = [];
        foreach ($requiredTables as $table) {
            if (!self::tableExists($table)) {
                $missing[] = $table;
            }
        }

        if ($missing === []) {
            return ['ready' => true, 'message' => '', 'missing' => []];
        }

        return [
            'ready' => false,
            'message' => 'BOM / Recipes are unavailable until the manufacturing schema is fully initialized. Missing tables: ' . implode(', ', $missing),
            'missing' => $missing,
        ];
    }

    public static function requireAvailability(): void
    {
        $status = self::availabilityStatus();
        if ($status['ready']) {
            return;
        }

        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>BOM / Recipes unavailable</title></head><body class="u-style-244abacef3">';
        echo '<h1 class="u-style-d462248a40">BOM / Recipes unavailable</h1>';
        echo '<p>' . htmlspecialchars((string)$status['message'], ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><a href="/apps/manufacturing">Return to Manufacturing dashboard</a></p>';
        echo '</body></html>';
        exit;
    }

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        DB::query(
            "CREATE TABLE IF NOT EXISTS manufacturing_bom (
                id INT AUTO_INCREMENT PRIMARY KEY,
                finished_item_ref INT NOT NULL,
                version VARCHAR(50) NOT NULL DEFAULT '1.0',
                revision INT NOT NULL DEFAULT 1,
                status ENUM('draft', 'released', 'superseded', 'archived') NOT NULL DEFAULT 'draft',
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_manufacturing_bom_reference (finished_item_ref, version, revision),
                KEY idx_manufacturing_bom_finished (finished_item_ref),
                KEY idx_manufacturing_bom_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS manufacturing_bom_line (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bom_id INT NOT NULL,
                component_item_ref INT NOT NULL,
                quantity DECIMAL(15, 4) NOT NULL DEFAULT 1,
                unit VARCHAR(50) NOT NULL DEFAULT 'each',
                sequence INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bom_id) REFERENCES manufacturing_bom(id) ON DELETE CASCADE,
                KEY idx_bom_line_bom (bom_id),
                KEY idx_bom_line_component (component_item_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        $done = true;
    }

    // ---------------------------------------------------------------- reads

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listBoms(string $status = '', int $finishedItemRef = 0, bool $onlyActive = false): array
    {
        self::ensureSchema();

        $sql = "SELECT b.id, b.finished_item_ref, b.version, b.revision, b.status, b.notes, b.created_at, b.updated_at,
                       (SELECT COUNT(*) FROM manufacturing_bom_line l WHERE l.bom_id = b.id) AS line_count,
                       p.parts_name, p.parts_number
                FROM manufacturing_bom b
                LEFT JOIN products p ON p.item_ref = b.finished_item_ref";
        $params = [];

        $where = [];
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }
        if ($finishedItemRef > 0) {
            $where[] = 'b.finished_item_ref = ?';
            $params[] = $finishedItemRef;
        }
        if ($onlyActive) {
            $where[] = "b.status IN ('draft','released')";
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY b.updated_at DESC, b.id DESC LIMIT 800';

        return DB::fetchAll($sql, $params);
    }

    /**
     * @return array{bom:array<string,mixed>,lines:array<int,array<string,mixed>>,resolved:array<string,mixed>}|null
     */
    public static function bomDetail(int $id): ?array
    {
        self::ensureSchema();

        $bom = DB::fetchOne(
            'SELECT b.*, p.parts_name, p.parts_number
             FROM manufacturing_bom b
             LEFT JOIN products p ON p.item_ref = b.finished_item_ref
             WHERE b.id=? LIMIT 1',
            [$id]
        );
        if (!$bom) {
            return null;
        }

        $lines = DB::fetchAll(
            "SELECT l.*, p.parts_name AS component_name, p.parts_number AS component_number
             FROM manufacturing_bom_line l
             LEFT JOIN products p ON p.item_ref = l.component_item_ref
             WHERE l.bom_id=?
             ORDER BY l.sequence ASC, l.id ASC",
            [$id]
        );

        return [
            'bom' => $bom,
            'lines' => $lines,
            'resolved' => [
                'finished_label' => self::itemLabel((int)$bom['finished_item_ref'], $bom['parts_name'] ?? '', $bom['parts_number'] ?? ''),
            ],
        ];
    }

    /**
     * Latest released BOM (highest revision of the chosen version) for an item.
     *
     * @return array<string,mixed>|null
     */
    public static function releasedBomForItem(int $finishedItemRef): ?array
    {
        self::ensureSchema();
        if ($finishedItemRef <= 0) {
            return null;
        }

        $row = DB::fetchOne(
            "SELECT b.*, p.parts_name, p.parts_number
             FROM manufacturing_bom b
             LEFT JOIN products p ON p.item_ref = b.finished_item_ref
             WHERE b.finished_item_ref=? AND b.status='released'
             ORDER BY b.revision DESC, b.id DESC LIMIT 1",
            [$finishedItemRef]
        );

        return $row ?: null;
    }

    /**
     * @return array<int,array<string,mixed>> products rows having item_ref (id, parts_name, parts_number, item_ref)
     */
    public static function referenceableItems(): array
    {
        self::ensureSchema();
        if (!self::tableExists('products') || !self::productHasColumn()) {
            return [];
        }

        return DB::fetchAll(
            'SELECT id, parts_name, parts_number, item_ref
             FROM products
             WHERE item_ref IS NOT NULL
             ORDER BY parts_name ASC LIMIT 1200'
        );
    }

    /**
     * Integrity audit: unresolved item refs, released BOMs with zero lines, duplicate active releases.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function integrityIssues(): array
    {
        self::ensureSchema();
        if (!self::tableExists('products') || !self::productHasColumn()) {
            return [];
        }

        $issues = [];

        $unresolved = DB::fetchAll(
            "SELECT DISTINCT b.finished_item_ref
             FROM manufacturing_bom b
             LEFT JOIN products p ON p.item_ref = b.finished_item_ref
             WHERE p.id IS NULL"
        );
        foreach ($unresolved as $row) {
            $issues[] = ['code' => 'unresolved_item_ref', 'item_ref' => (int)$row['finished_item_ref'], 'bom_id' => 0];
        }

        $empties = DB::fetchAll(
            "SELECT b.id
             FROM manufacturing_bom b
             LEFT JOIN manufacturing_bom_line l ON l.bom_id = b.id
             WHERE b.status = 'released' AND l.id IS NULL"
        );
        foreach ($empties as $row) {
            $issues[] = ['code' => 'released_without_lines', 'item_ref' => 0, 'bom_id' => (int)$row['id']];
        }

        $dups = DB::fetchAll(
            "SELECT finished_item_ref, version
             FROM manufacturing_bom
             WHERE status = 'released'
             GROUP BY finished_item_ref, version
             HAVING COUNT(*) > 1"
        );
        foreach ($dups as $row) {
            $issues[] = [
                'code' => 'duplicate_active_release',
                'item_ref' => (int)$row['finished_item_ref'],
                'bom_id' => 0,
            ];
        }

        return $issues;
    }

    // --------------------------------------------------------------- writes

    /**
     * @param array<string,mixed> $actor
     */
    public static function createBom(int $finishedItemRef, string $version, int $revision, string $notes, array $actor): int
    {
        self::ensureSchema();

        if ($finishedItemRef <= 0) {
            throw new \InvalidArgumentException('bom.error.invalid_item_ref');
        }
        $version = trim($version);
        if ($version === '') {
            $version = '1.0';
        }
        if (mb_strlen($version) > 50) {
            throw new \InvalidArgumentException('bom.error.invalid_version');
        }
        if ($revision <= 0) {
            throw new \InvalidArgumentException('bom.error.invalid_revision');
        }

        $dupe = DB::fetchOne(
            'SELECT id FROM manufacturing_bom WHERE finished_item_ref=? AND version=? AND revision=? LIMIT 1',
            [$finishedItemRef, $version, $revision]
        );
        if ($dupe) {
            throw new \RuntimeException('bom.error.duplicate_bom');
        }

        $active = DB::fetchOne(
            "SELECT id FROM manufacturing_bom
             WHERE finished_item_ref=? AND version=? AND status='released' LIMIT 1",
            [$finishedItemRef, $version]
        );
        if ($active) {
            throw new \RuntimeException('bom.error.active_release_exists');
        }

        DB::query(
            'INSERT INTO manufacturing_bom (finished_item_ref, version, revision, status, notes) VALUES (?,?,?,?,?)',
            [$finishedItemRef, $version, $revision, 'draft', $notes !== '' ? $notes : null]
        );

        return (int)DB::conn()->insert_id;
    }

    /**
     * @param array<string,mixed> $actor
     */
    public static function updateBomHeader(int $id, string $notes, array $actor): void
    {
        self::ensureSchema();
        $bom = self::requireBom($id);
        if (!BomPolicies::canEdit($bom)) {
            throw new \RuntimeException('bom.error.draft_only');
        }

        DB::query(
            'UPDATE manufacturing_bom SET notes=?, updated_at=NOW() WHERE id=?',
            [$notes !== '' ? $notes : null, $id]
        );
    }

    /**
     * release | supersede | archive transition.
     *
     * @param array<string,mixed> $actor
     */
    public static function transitionBom(int $id, string $action, array $actor): void
    {
        self::ensureSchema();
        $bom = self::requireBom($id);
        if (!BomPolicies::canTransition($bom, $action)) {
            throw new \RuntimeException('bom.error.invalid_transition');
        }

        if ($action === 'release') {
            $lines = (int)DB::fetchOne('SELECT COUNT(*) AS c FROM manufacturing_bom_line WHERE bom_id=?', [$id])['c'] ?? 0;
            if ($lines <= 0) {
                throw new \RuntimeException('bom.error.release_requires_lines');
            }
            $newStatus = 'released';
        } elseif ($action === 'supersede') {
            $newStatus = 'superseded';
        } else {
            $newStatus = 'archived';
        }

        DB::query(
            'UPDATE manufacturing_bom SET status=?, updated_at=NOW() WHERE id=?',
            [$newStatus, $id]
        );
    }

    /**
     * @param array<string,mixed> $actor
     */
    public static function addLine(int $bomId, int $componentItemRef, float $quantity, string $unit, int $sequence, array $actor): int
    {
        self::ensureSchema();
        $bom = self::requireBom($bomId);
        if (!BomPolicies::canEdit($bom)) {
            throw new \RuntimeException('bom.error.draft_only');
        }
        if ($componentItemRef <= 0) {
            throw new \InvalidArgumentException('bom.error.invalid_item_ref');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('bom.error.invalid_quantity');
        }
        $unit = trim($unit);
        if ($unit === '') {
            $unit = 'each';
        }
        if (mb_strlen($unit) > 50) {
            throw new \InvalidArgumentException('bom.error.invalid_unit');
        }

        DB::query(
            'INSERT INTO manufacturing_bom_line (bom_id, component_item_ref, quantity, unit, sequence)
             VALUES (?,?,?,?,?)',
            [(int)$bom['id'], $componentItemRef, $quantity, $unit, max(0, $sequence)]
        );

        return (int)DB::conn()->insert_id;
    }

    /**
     * @param array<string,mixed> $actor
     */
    public static function updateLine(int $lineId, int $bomId, float $quantity, string $unit, int $sequence, array $actor): void
    {
        self::ensureSchema();
        $bom = self::requireBom($bomId);
        if (!BomPolicies::canEdit($bom)) {
            throw new \RuntimeException('bom.error.draft_only');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('bom.error.invalid_quantity');
        }

        DB::query(
            'UPDATE manufacturing_bom_line SET quantity=?, unit=?, sequence=? WHERE id=? AND bom_id=?',
            [$quantity, trim($unit) !== '' ? trim($unit) : 'each', max(0, $sequence), $lineId, $bomId]
        );
    }

    /**
     * @param array<string,mixed> $actor
     */
    public static function deleteLine(int $lineId, int $bomId, array $actor): void
    {
        self::ensureSchema();
        $bom = self::requireBom($bomId);
        if (!BomPolicies::canEdit($bom)) {
            throw new \RuntimeException('bom.error.draft_only');
        }

        DB::query('DELETE FROM manufacturing_bom_line WHERE id=? AND bom_id=?', [$lineId, $bomId]);
    }

    // -------------------------------------------------------------- helpers

    /**
     * @return array<string,mixed>
     */
    private static function requireBom(int $id): array
    {
        $bom = DB::fetchOne('SELECT * FROM manufacturing_bom WHERE id=? LIMIT 1', [$id]);
        if (!$bom) {
            throw new \RuntimeException('bom.error.not_found');
        }
        return $bom;
    }

    private static function itemLabel(int $itemRef, string $name, string $number): string
    {
        if ($name !== '' || $number !== '') {
            $label = $name !== '' ? (string)$name : '';
            $label .= $number !== '' ? ($label !== '' ? ' (' . $number . ')' : $number) : '';
            return $label . ' · item#' . $itemRef;
        }
        return 'item#' . $itemRef;
    }

    public static function tableExists(string $table): bool
    {
        return DB::fetchOne(
            'SELECT 1 AS present
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?
             LIMIT 1',
            [$table]
        ) !== null;
    }

    private static function productHasColumn(): bool
    {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'item_ref'"
        );
        return (int)($row['c'] ?? 0) > 0;
    }

    public static function currentUserLabel(): string
    {
        $user = Auth::user();
        if (!is_array($user)) {
            return 'system';
        }
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'user#' . (string)($user['id'] ?? '0');
    }
}
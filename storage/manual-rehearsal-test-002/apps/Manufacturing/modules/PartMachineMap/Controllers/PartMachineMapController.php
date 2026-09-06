<?php
declare(strict_types=1);

namespace Plugins\PartMachineMap\Controllers;

use App\Core\DB;
use App\Core\View;

final class PartMachineMapController
{
    public static function index(View $view): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $active = (string)($_GET['active'] ?? 'all');

        $sql = "SELECT pm.*, p.parts_name, p.parts_number, m.machine_no, m.machine_name
                FROM part_machine_map pm
                INNER JOIN products p ON p.id = pm.product_id
                INNER JOIN machines m ON m.id = pm.machine_id
                WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (p.parts_name LIKE ? OR p.parts_number LIKE ? OR m.machine_no LIKE ? OR m.machine_name LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($active === '1' || $active === '0') {
            $sql .= ' AND pm.is_active = ?';
            $params[] = (int)$active;
        }

        $sql .= ' ORDER BY pm.updated_at DESC, pm.id DESC LIMIT 300';

        $view->render('PartMachineMap::index.php', [
            'pageTitle' => 'Part-Machine Map',
            'rows' => DB::fetchAll($sql, $params),
            'q' => $q,
            'active' => $active,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        $view->render('PartMachineMap::add.php', [
            'pageTitle' => 'Add Part-Machine Map',
            'products' => self::products(),
            'machines' => self::machines(),
            'old' => $_SESSION['pmm_old'] ?? [],
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['pmm_old']);
    }

    public static function create(array $input): void
    {
        $data = self::sanitize($input);
        $_SESSION['pmm_old'] = $data;

        if ($data['product_id'] <= 0 || $data['machine_id'] <= 0) {
            self::flash('err', 'Product and machine are required.');
            header('Location: /part-machine-map/add');
            exit;
        }

        if ($data['is_active'] === 1 && self::hasActivePair($data['product_id'], $data['machine_id'])) {
            self::flash('err', 'An active mapping already exists for this part and machine.');
            header('Location: /part-machine-map/add');
            exit;
        }

        try {
            DB::query(
                'INSERT INTO part_machine_map (product_id, machine_id, is_active, notes) VALUES (?,?,?,?)',
                [$data['product_id'], $data['machine_id'], $data['is_active'], $data['notes']]
            );
            unset($_SESSION['pmm_old']);
            self::flash('ok', 'Part-machine mapping created.');
        } catch (\Throwable $e) {
            self::flash('err', 'Create failed: ' . $e->getMessage());
            header('Location: /part-machine-map/add');
            exit;
        }
    }

    public static function editForm(View $view, int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid mapping id.');
            header('Location: /part-machine-map');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM part_machine_map WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Mapping not found.');
            header('Location: /part-machine-map');
            exit;
        }

        $view->render('PartMachineMap::edit.php', [
            'pageTitle' => 'Edit Part-Machine Map',
            'row' => $row,
            'products' => self::products(),
            'machines' => self::machines(),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function update(array $input): void
    {
        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['product_id'] <= 0 || $data['machine_id'] <= 0) {
            self::flash('err', 'Invalid mapping payload.');
            header('Location: /part-machine-map');
            exit;
        }

        if ($data['is_active'] === 1 && self::hasActivePair($data['product_id'], $data['machine_id'], $id)) {
            self::flash('err', 'An active mapping already exists for this part and machine.');
            header('Location: /part-machine-map/edit?id=' . $id);
            exit;
        }

        try {
            DB::query(
                'UPDATE part_machine_map SET product_id=?, machine_id=?, is_active=?, notes=?, updated_at=NOW() WHERE id=?',
                [$data['product_id'], $data['machine_id'], $data['is_active'], $data['notes'], $id]
            );
            self::flash('ok', 'Mapping updated.');
        } catch (\Throwable $e) {
            self::flash('err', 'Update failed: ' . $e->getMessage());
            header('Location: /part-machine-map/edit?id=' . $id);
            exit;
        }
    }

    public static function delete(int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid mapping id.');
            return;
        }

        DB::query('DELETE FROM part_machine_map WHERE id=? LIMIT 1', [$id]);
        self::flash('ok', 'Mapping deleted.');
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function machines(): array
    {
        return DB::fetchAll('SELECT id, machine_no, machine_name FROM machines WHERE is_active=1 ORDER BY machine_no ASC LIMIT 1000');
    }

    private static function hasActivePair(int $productId, int $machineId, int $excludeId = 0): bool
    {
        $sql = 'SELECT id FROM part_machine_map WHERE product_id=? AND machine_id=? AND is_active=1';
        $params = [$productId, $machineId];

        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $row = DB::fetchOne($sql . ' LIMIT 1', $params);
        return $row !== null;
    }

    private static function sanitize(array $input): array
    {
        return [
            'product_id' => (int)($input['product_id'] ?? 0),
            'machine_id' => (int)($input['machine_id'] ?? 0),
            'is_active' => (int)((string)($input['is_active'] ?? '1') === '0' ? 0 : 1),
            'notes' => trim((string)($input['notes'] ?? '')),
        ];
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['pmm_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'pmm_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }
}

<?php
declare(strict_types=1);

namespace Plugins\Machines\Controllers;

use App\Core\DB;
use App\Core\View;
use Plugins\Machines\Services\MachineLeaderDashboardService;

require_once __DIR__ . '/../Services/MachineLeaderDashboardService.php';

final class MachinesController
{
    public static function leaderDashboard(View $view, ?array $currentUser = null): void
    {
        $payload = MachineLeaderDashboardService::build($_GET, $currentUser);
        $view->render('Machines::leader.php', [
            'pageTitle' => 'Production Workspace',
            'dashboard' => $payload,
        ]);
    }

    public static function index(View $view): void
    {
        $sql = 'SELECT * FROM machines ORDER BY updated_at DESC, id DESC LIMIT 300';

        $view->render('Machines::index.php', [
            'pageTitle' => 'Machines',
            'rows' => DB::fetchAll($sql),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        $view->render('Machines::add.php', [
            'pageTitle' => 'Add Machine',
            'old' => $_SESSION['machines_old'] ?? [],
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['machines_old']);
    }

    public static function detail(View $view, int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid machine id.');
            header('Location: /machines');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM machines WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Machine not found.');
            header('Location: /machines');
            exit;
        }

        $partMappings = [];
        try {
            $partMappings = DB::fetchAll(
                "SELECT pm.*, p.parts_name, p.parts_number
                 FROM part_machine_map pm
                 INNER JOIN products p ON p.id = pm.product_id
                 WHERE pm.machine_id = ?
                 ORDER BY pm.is_active DESC, p.parts_name ASC, pm.id DESC
                 LIMIT 60",
                [$id]
            );
        } catch (\Throwable $e) {
            $partMappings = [];
        }

        $view->render('Machines::detail.php', [
            'pageTitle' => 'Machine Detail',
            'row' => $row,
            'partMappings' => $partMappings,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function create(array $input): void
    {
        $data = self::sanitize($input);
        $_SESSION['machines_old'] = $data;

        if ($data['machine_no'] === '' || $data['machine_name'] === '') {
            self::flash('err', 'Machine no and machine name are required.');
            header('Location: /machines/add');
            exit;
        }

        try {
            DB::query(
                'INSERT INTO machines (machine_no, machine_name, section, machine_group, machine_type, status, capacity_per_hour, clamping_force_ton, shot_capacity_g, tie_bar_spacing_mm, platen_size_mm, min_mold_height_mm, max_mold_height_mm, preferred_materials, notes, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $data['machine_no'],
                    $data['machine_name'],
                    $data['section'],
                    $data['machine_group'],
                    $data['machine_type'],
                    $data['status'],
                    $data['capacity_per_hour'],
                    $data['clamping_force_ton'],
                    $data['shot_capacity_g'],
                    $data['tie_bar_spacing_mm'],
                    $data['platen_size_mm'],
                    $data['min_mold_height_mm'],
                    $data['max_mold_height_mm'],
                    $data['preferred_materials'],
                    $data['notes'],
                    $data['is_active'],
                ]
            );
            unset($_SESSION['machines_old']);
            self::flash('ok', 'Machine created successfully.');
        } catch (\Throwable $e) {
            self::flash('err', 'Create failed: ' . $e->getMessage());
            header('Location: /machines/add');
            exit;
        }
    }

    public static function editForm(View $view, int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid machine id.');
            header('Location: /machines');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM machines WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Machine not found.');
            header('Location: /machines');
            exit;
        }

        $view->render('Machines::edit.php', [
            'pageTitle' => 'Edit Machine',
            'row' => $row,
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function update(array $input): void
    {
        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['machine_no'] === '' || $data['machine_name'] === '') {
            self::flash('err', 'Invalid machine payload.');
            header('Location: /machines');
            exit;
        }

        try {
            DB::query(
                'UPDATE machines SET machine_no=?, machine_name=?, section=?, machine_group=?, machine_type=?, status=?, capacity_per_hour=?, clamping_force_ton=?, shot_capacity_g=?, tie_bar_spacing_mm=?, platen_size_mm=?, min_mold_height_mm=?, max_mold_height_mm=?, preferred_materials=?, notes=?, is_active=?, updated_at=NOW() WHERE id=?',
                [
                    $data['machine_no'],
                    $data['machine_name'],
                    $data['section'],
                    $data['machine_group'],
                    $data['machine_type'],
                    $data['status'],
                    $data['capacity_per_hour'],
                    $data['clamping_force_ton'],
                    $data['shot_capacity_g'],
                    $data['tie_bar_spacing_mm'],
                    $data['platen_size_mm'],
                    $data['min_mold_height_mm'],
                    $data['max_mold_height_mm'],
                    $data['preferred_materials'],
                    $data['notes'],
                    $data['is_active'],
                    $id,
                ]
            );
            self::flash('ok', 'Machine updated.');
        } catch (\Throwable $e) {
            self::flash('err', 'Update failed: ' . $e->getMessage());
            header('Location: /machines/edit?id=' . $id);
            exit;
        }
    }

    public static function delete(int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid machine id.');
            return;
        }

        DB::query('DELETE FROM machines WHERE id=? LIMIT 1', [$id]);
        self::flash('ok', 'Machine deleted.');
    }

    private static function sanitize(array $input): array
    {
        $cphRaw = trim((string)($input['capacity_per_hour'] ?? ''));
        $clampRaw = trim((string)($input['clamping_force_ton'] ?? ''));
        $shotRaw = trim((string)($input['shot_capacity_g'] ?? ''));
        $minMoldRaw = trim((string)($input['min_mold_height_mm'] ?? ''));
        $maxMoldRaw = trim((string)($input['max_mold_height_mm'] ?? ''));
        return [
            'machine_no' => trim((string)($input['machine_no'] ?? '')),
            'machine_name' => trim((string)($input['machine_name'] ?? '')),
            'section' => trim((string)($input['section'] ?? '')),
            'machine_group' => trim((string)($input['machine_group'] ?? '')),
            'machine_type' => trim((string)($input['machine_type'] ?? '')),
            'status' => trim((string)($input['status'] ?? 'Available')),
            'capacity_per_hour' => $cphRaw === '' ? null : (float)$cphRaw,
            'clamping_force_ton' => $clampRaw === '' ? null : (float)$clampRaw,
            'shot_capacity_g' => $shotRaw === '' ? null : (float)$shotRaw,
            'tie_bar_spacing_mm' => trim((string)($input['tie_bar_spacing_mm'] ?? '')),
            'platen_size_mm' => trim((string)($input['platen_size_mm'] ?? '')),
            'min_mold_height_mm' => $minMoldRaw === '' ? null : (float)$minMoldRaw,
            'max_mold_height_mm' => $maxMoldRaw === '' ? null : (float)$maxMoldRaw,
            'preferred_materials' => trim((string)($input['preferred_materials'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')),
            'is_active' => (int)((string)($input['is_active'] ?? '1') === '0' ? 0 : 1),
        ];
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['machines_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'machines_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }
}

<?php
declare(strict_types=1);

namespace Plugins\QCPlans\Controllers;

use App\Core\DB;
use App\Core\PackageManager;
use App\Core\PdfService;
use App\Core\View;
use App\Services\ExportHistoryService;

final class QCPlansController
{
    /**
     * @return array<int,string>
     */
    private static function statusOptions(): array
    {
        return [
            'System Generated',
            'Verified by QC',
            'Adjusted by QC',
            'Approved by Authority',
        ];
    }

    public static function index(View $view): void
    {
        $priority = trim((string)($_GET['priority'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $planDate = trim((string)($_GET['plan_date'] ?? ''));
        $printFromDate = self::normalizeDate((string)($_GET['from_date'] ?? ''), date('Y-m-d'));
        $printToDate = self::normalizeDate((string)($_GET['to_date'] ?? ''), date('Y-m-d', strtotime('+13 days')));

        if ($printFromDate > $printToDate) {
            $tmp = $printFromDate;
            $printFromDate = $printToDate;
            $printToDate = $tmp;
        }

        $sql = "SELECT q.*, p.parts_name, p.parts_number
                FROM qc_plans q
                INNER JOIN products p ON p.id = q.product_id
                WHERE 1=1";
        $params = [];

        if ($priority !== '') {
            $sql .= ' AND q.priority = ?';
            $params[] = $priority;
        }
        if ($status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }
        if ($planDate !== '') {
            $sql .= ' AND q.plan_date = ?';
            $params[] = $planDate;
        }

        $sql .= ' ORDER BY q.plan_date DESC, q.id DESC LIMIT 350';

        $view->render('QCPlans::index.php', [
            'pageTitle' => 'QC Plans',
            'rows' => DB::fetchAll($sql, $params),
            'priority' => $priority,
            'status' => $status,
            'status_options' => self::statusOptions(),
            'plan_date' => $planDate,
            'print_from_date' => $printFromDate,
            'print_to_date' => $printToDate,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function printRangePdf(array $query): void
    {
        $fromDate = self::normalizeDate((string)($query['from_date'] ?? ''), date('Y-m-d'));
        $toDate = self::normalizeDate((string)($query['to_date'] ?? ''), date('Y-m-d', strtotime('+13 days')));

        if ($fromDate > $toDate) {
            $tmp = $fromDate;
            $fromDate = $toDate;
            $toDate = $tmp;
        }

        if (!self::pdfService()->isReady()) {
            self::recordPdfExportFailure('qc-plan:range', 'PDF rendering is not available.');
            self::flash('err', 'PDF rendering is not available.');
            header('Location: /qc-plans');
            exit;
        }

        try {
            $rows = DB::fetchAll(
                'SELECT q.*, p.parts_name, p.parts_number
                 FROM qc_plans q
                 INNER JOIN products p ON p.id = q.product_id
                 WHERE q.plan_date >= ? AND q.plan_date <= ?
                 ORDER BY q.plan_date ASC, q.priority ASC, q.id ASC',
                [$fromDate, $toDate]
            );

            $html = self::renderPdfTemplate('QCPlans::pdf_qc_plans_range.php', [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'rows' => $rows,
                'total_rows' => count($rows),
            ]);

            $pdfBinary = self::pdfService()->outputFromHtml($html, [
                'defaultFont' => 'DejaVu Sans',
                'isPhpEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'paper' => 'A4',
                'orientation' => 'landscape',
            ]);

            $filename = 'qc-plan-' . date('Ymd-His') . '.pdf';
            self::recordPdfExportSuccess('qc-plan:range', $filename, false, [
                'route' => '/qc-plans/print-pdf',
                'mode' => 'inline',
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'total_rows' => count($rows),
            ]);
            self::pdfService()->stream($pdfBinary, $filename, false);
        } catch (\Throwable $e) {
            self::recordPdfExportFailure('qc-plan:range', $e->getMessage());
            self::flash('err', 'PDF render failed: ' . $e->getMessage());
            header('Location: /qc-plans');
            exit;
        }
    }

    public static function addForm(View $view): void
    {
        $old = $_SESSION['qc_plans_old'] ?? [];
        if ((int)($old['product_id'] ?? 0) <= 0 && (int)($_GET['product_id'] ?? 0) > 0) {
            $old['product_id'] = (int)$_GET['product_id'];
        }
        if (($old['plan_date'] ?? '') === '') {
            $old['plan_date'] = date('Y-m-d');
        }
        $view->render('QCPlans::add.php', [
            'pageTitle' => 'Add QC Plan',
            'products' => self::products(),
            'daily_orders' => self::dailyOrders(),
            'production_entries' => self::productionEntries(),
            'status_options' => self::statusOptions(),
            'old' => $old,
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['qc_plans_old']);
    }

    public static function create(array $input): void
    {
        $data = self::sanitize($input);
        $_SESSION['qc_plans_old'] = $data;

        if ($data['plan_date'] === '' || $data['product_id'] <= 0 || $data['planned_qty'] <= 0) {
            self::flash('err', 'Plan date, product, and planned qty are required.');
            header('Location: /qc-plans/add');
            exit;
        }

        DB::query(
            'INSERT INTO qc_plans (plan_date, required_date, product_id, daily_order_id, production_entry_id, planned_qty, estimated_time_minutes, priority, status, notes, assigned_to, added_by, verified_by, approved_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['plan_date'],
                $data['required_date'] !== '' ? $data['required_date'] : null,
                $data['product_id'],
                $data['daily_order_id'] > 0 ? $data['daily_order_id'] : null,
                $data['production_entry_id'] > 0 ? $data['production_entry_id'] : null,
                $data['planned_qty'],
                $data['estimated_time_minutes'],
                $data['priority'],
                'System Generated',
                $data['notes'],
                $data['assigned_to'],
                $data['added_by'],
                $data['verified_by'],
                $data['approved_by'],
            ]
        );

        unset($_SESSION['qc_plans_old']);
        self::flash('ok', 'QC plan created.');
    }

    public static function editForm(View $view, int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid QC plan id.');
            header('Location: /qc-plans');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM qc_plans WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'QC plan not found.');
            header('Location: /qc-plans');
            exit;
        }

        $view->render('QCPlans::edit.php', [
            'pageTitle' => 'Edit QC Plan',
            'row' => $row,
            'products' => self::products(),
            'daily_orders' => self::dailyOrders(),
            'production_entries' => self::productionEntries(),
            'status_options' => self::statusOptions(),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function update(array $input): void
    {
        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['plan_date'] === '' || $data['product_id'] <= 0 || $data['planned_qty'] <= 0) {
            self::flash('err', 'Invalid payload.');
            header('Location: /qc-plans');
            exit;
        }

        DB::query(
            'UPDATE qc_plans SET plan_date=?, required_date=?, product_id=?, daily_order_id=?, production_entry_id=?, planned_qty=?, estimated_time_minutes=?, priority=?, status=?, notes=?, assigned_to=?, added_by=?, verified_by=?, approved_by=?, updated_at=NOW() WHERE id=?',
            [
                $data['plan_date'],
                $data['required_date'] !== '' ? $data['required_date'] : null,
                $data['product_id'],
                $data['daily_order_id'] > 0 ? $data['daily_order_id'] : null,
                $data['production_entry_id'] > 0 ? $data['production_entry_id'] : null,
                $data['planned_qty'],
                $data['estimated_time_minutes'],
                $data['priority'],
                $data['status'],
                $data['notes'],
                $data['assigned_to'],
                $data['added_by'],
                $data['verified_by'],
                $data['approved_by'],
                $id,
            ]
        );

        self::flash('ok', 'QC plan updated.');
    }

    public static function delete(int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid QC plan id.');
            return;
        }
        DB::query('DELETE FROM qc_plans WHERE id=? LIMIT 1', [$id]);
        self::flash('ok', 'QC plan deleted.');
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function dailyOrders(): array
    {
        return DB::fetchAll('SELECT id, customer_name, order_date FROM daily_orders ORDER BY id DESC LIMIT 300');
    }

    private static function productionEntries(): array
    {
        return DB::fetchAll('SELECT id, production_date, good_qty FROM production_entries ORDER BY id DESC LIMIT 300');
    }

    private static function sanitize(array $input): array
    {
        return [
            'plan_date' => trim((string)($input['plan_date'] ?? '')),
            'required_date' => trim((string)($input['required_date'] ?? '')),
            'product_id' => (int)($input['product_id'] ?? 0),
            'daily_order_id' => (int)($input['daily_order_id'] ?? 0),
            'production_entry_id' => (int)($input['production_entry_id'] ?? 0),
            'planned_qty' => (float)($input['planned_qty'] ?? 0),
            'estimated_time_minutes' => (int)($input['estimated_time_minutes'] ?? 0),
            'priority' => trim((string)($input['priority'] ?? 'Normal')),
            'status' => self::normalizeStatus((string)($input['status'] ?? 'System Generated')),
            'notes' => trim((string)($input['notes'] ?? '')),
            'assigned_to' => trim((string)($input['assigned_to'] ?? '')),
            'added_by' => trim((string)($input['added_by'] ?? '')),
            'verified_by' => trim((string)($input['verified_by'] ?? '')),
            'approved_by' => trim((string)($input['approved_by'] ?? '')),
        ];
    }

    private static function normalizeStatus(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'System Generated';
        }

        foreach (self::statusOptions() as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        return $value;
    }

    private static function normalizeDate(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $fallback;
        }

        return date('Y-m-d', $ts);
    }

    private static function pdfService(): PdfService
    {
        static $service = null;
        if ($service === null) {
            $service = new PdfService();
        }

        return $service;
    }

    private static function renderPdfTemplate(string $viewKey, array $data): string
    {
        ob_start();
        extract($data);
        [$namespace, $viewFile] = array_pad(explode('::', $viewKey, 2), 2, '');
        include(rtrim(PackageManager::pluginSourcePath($namespace), '/') . '/Views/' . $viewFile);
        return ob_get_clean();
    }

    private static function exportHistory(): ExportHistoryService
    {
        static $history = null;
        if (!$history instanceof ExportHistoryService) {
            $history = new ExportHistoryService();
        }
        return $history;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function recordPdfExportSuccess(string $targetKey, string $filename, bool $download, array $summary = []): void
    {
        self::exportHistory()->recordSuccess(
            'module',
            $targetKey,
            'manufacturing',
            'pdf',
            $filename,
            'stream://' . $filename,
            $summary,
            $download ? 'download' : ''
        );
    }

    private static function recordPdfExportFailure(string $targetKey, string $errorText): void
    {
        self::exportHistory()->recordFailure('module', $targetKey, 'manufacturing', 'pdf', $errorText);
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['qc_plans_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'qc_plans_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }
}

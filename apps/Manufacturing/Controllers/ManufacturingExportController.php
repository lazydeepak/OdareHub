<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Services\ExportHistoryService;
use App\Services\SuiteExportService;

final class ManufacturingExportController
{
    public static function index($view): void
    {
        $service = new SuiteExportService();
        $card = $service->suiteCard('manufacturing');
        $view->render('manufacturing::exports.php', [
            'pageTitle' => 'Manufacturing Exports',
            'suite' => $card,
            'modules' => $service->moduleCards('manufacturing'),
            'history' => (new ExportHistoryService())->recentRuns('manufacturing', null, 20),
            'flash_ok' => (string)($_SESSION['manufacturing_export_ok'] ?? ''),
            'flash_err' => (string)($_SESSION['manufacturing_export_err'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ]);
        unset($_SESSION['manufacturing_export_ok'], $_SESSION['manufacturing_export_err']);
    }

    public static function exportSuite(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $exportType = trim((string)($_POST['export_type'] ?? 'metadata_only'));
        try {
            $result = (new SuiteExportService())->exportSuite('manufacturing', $exportType);
            self::flash('ok', 'Manufacturing export created: ' . basename((string)$result['file_path']));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }
        header('Location: /apps/manufacturing/exports');
        exit;
    }

    public static function exportModule(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $moduleName = trim((string)($_POST['module_name'] ?? ''));
        $exportType = trim((string)($_POST['export_type'] ?? 'metadata_only'));
        try {
            $result = (new SuiteExportService())->exportModule('manufacturing', $moduleName, $exportType);
            self::flash('ok', $moduleName . ' export created: ' . basename((string)$result['file_path']));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }
        header('Location: /apps/manufacturing/exports');
        exit;
    }

    public static function download(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $row = (new ExportHistoryService())->findById($id);
        if (!is_array($row) || (string)($row['suite_key'] ?? '') !== 'manufacturing') {
            http_response_code(404);
            echo 'Export not found.';
            exit;
        }

        $path = (string)($row['file_path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Export file not found.';
            exit;
        }

        $downloadName = basename((string)($row['file_name'] ?? basename($path)));
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string)filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['manufacturing_export_' . $type] = $message;
    }
}

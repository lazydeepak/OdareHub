<?php
declare(strict_types=1);

namespace SBAIO\Controllers;

use App\Core\Auth;
use App\Services\ExportHistoryService;
use App\Services\SuiteExportService;

final class SbaioExportController
{
    public static function index($view): void
    {
        $service = new SuiteExportService();
        $card = $service->suiteCard('sbaio');
        $view->render('sbaio::exports.php', [
            'pageTitle' => t('sbaio.exports.title'),
            'suite' => $card,
            'modules' => $service->moduleCards('sbaio'),
            'history' => (new ExportHistoryService())->recentRuns('sbaio', null, 20),
            'flash_ok' => (string)($_SESSION['sbaio_export_ok'] ?? ''),
            'flash_err' => (string)($_SESSION['sbaio_export_err'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ]);
        unset($_SESSION['sbaio_export_ok'], $_SESSION['sbaio_export_err']);
    }

    public static function exportSuite(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $exportType = trim((string)($_POST['export_type'] ?? 'metadata_only'));
        try {
            $result = (new SuiteExportService())->exportSuite('sbaio', $exportType);
            self::flash('ok', t('sbaio.exports.created', ['file' => basename((string)$result['file_path'])]));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }
        header('Location: /apps/sbaio/exports');
        exit;
    }

    public static function exportModule(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $moduleName = trim((string)($_POST['module_name'] ?? ''));
        $exportType = trim((string)($_POST['export_type'] ?? 'metadata_only'));
        try {
            $result = (new SuiteExportService())->exportModule('sbaio', $moduleName, $exportType);
            self::flash('ok', t('sbaio.exports.module_created', ['module' => $moduleName, 'file' => basename((string)$result['file_path'])]));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }
        header('Location: /apps/sbaio/exports');
        exit;
    }

    public static function download(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $row = (new ExportHistoryService())->findById($id);
        if (!is_array($row) || (string)($row['suite_key'] ?? '') !== 'sbaio') {
            http_response_code(404);
            echo t('sbaio.exports.not_found');
            exit;
        }

        $path = (string)($row['file_path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo t('sbaio.exports.file_not_found');
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
        $_SESSION['sbaio_export_' . $type] = $message;
    }
}

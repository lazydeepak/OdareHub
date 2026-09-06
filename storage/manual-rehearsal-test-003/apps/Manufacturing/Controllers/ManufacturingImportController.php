<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Services\ImportHistoryService;
use Apps\Manufacturing\Services\ManufacturingLegacyImportService;

final class ManufacturingImportController
{
    public static function index($view): void
    {
        $history = new ImportHistoryService();
        $service = new ManufacturingLegacyImportService();
        $previewToken = trim((string)($_GET['preview'] ?? ($_SESSION['manufacturing_import_preview'] ?? '')));
        $preview = $previewToken !== '' ? $history->loadPreviewPayload($previewToken) : null;

        $view->render('manufacturing::imports.php', [
            'pageTitle' => 'Manufacturing Legacy Imports',
            'templates' => $service->templates(),
            'preview' => $preview,
            'preview_token' => $previewToken,
            'history' => $history->recentRuns('manufacturing', 12),
            'flash_ok' => (string)($_SESSION['manufacturing_import_ok'] ?? ''),
            'flash_err' => (string)($_SESSION['manufacturing_import_err'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ]);

        unset($_SESSION['manufacturing_import_ok'], $_SESSION['manufacturing_import_err']);
    }

    public static function previewImport(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $importType = trim((string)($_POST['import_type'] ?? ''));
        $file = $_FILES['import_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::flash('err', 'Choose a Manufacturing import file first.');
            header('Location: /apps/manufacturing/imports');
            exit;
        }

        $name = trim((string)($file['name'] ?? 'import.csv'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            self::flash('err', 'Use a .csv or .xlsx import file.');
            header('Location: /apps/manufacturing/imports');
            exit;
        }

        $previewToken = 'mfg_' . bin2hex(random_bytes(8));
        $uploadPath = self::persistUpload((string)($file['tmp_name'] ?? ''), $previewToken, $name);
        $history = new ImportHistoryService();
        $service = new ManufacturingLegacyImportService();

        try {
            $preview = $service->previewImport($importType, $uploadPath, $name);
            $runId = $history->startPreview('manufacturing', $importType, $name, $previewToken, $preview);
            $payload = [
                'preview_token' => $previewToken,
                'run_id' => $runId,
                'suite_key' => 'manufacturing',
                'import_type' => $importType,
                'source_file' => $name,
                'uploaded_path' => $uploadPath,
                'preview' => $preview,
            ];
            $history->savePreviewPayload($previewToken, $payload);
            $_SESSION['manufacturing_import_preview'] = $previewToken;
            self::flash('ok', 'Manufacturing import preview is ready. Review valid, invalid, duplicate, and skipped rows before commit.');
            header('Location: /apps/manufacturing/imports?preview=' . urlencode($previewToken));
            exit;
        } catch (\Throwable $e) {
            @unlink($uploadPath);
            $history->failRun(null, 'manufacturing', $importType, $name, $e->getMessage());
            self::flash('err', $e->getMessage());
            header('Location: /apps/manufacturing/imports');
            exit;
        }
    }

    public static function commitImport(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        $history = new ImportHistoryService();
        $payload = $history->loadPreviewPayload($previewToken);
        if (!is_array($payload)) {
            self::flash('err', 'The import preview expired. Preview the file again.');
            header('Location: /apps/manufacturing/imports');
            exit;
        }

        $service = new ManufacturingLegacyImportService();
        try {
            $result = $service->importPreview((array)($payload['preview'] ?? []));
            $history->completeImport((int)($payload['run_id'] ?? 0), $result);
            $history->deletePreviewPayload($previewToken);
            @unlink((string)($payload['uploaded_path'] ?? ''));
            unset($_SESSION['manufacturing_import_preview']);
            self::flash('ok', 'Manufacturing import committed: ' . (int)($result['imported_rows'] ?? 0) . ' row(s) applied.');
            header('Location: /apps/manufacturing/imports');
            exit;
        } catch (\Throwable $e) {
            $history->failRun((int)($payload['run_id'] ?? 0), 'manufacturing', (string)($payload['import_type'] ?? ''), (string)($payload['source_file'] ?? ''), $e->getMessage(), (array)($payload['preview'] ?? []));
            self::flash('err', $e->getMessage());
            header('Location: /apps/manufacturing/imports?preview=' . urlencode($previewToken));
            exit;
        }
    }

    public static function downloadTemplate(): void
    {
        $importType = trim((string)($_GET['import_type'] ?? ''));
        $payload = (new ManufacturingLegacyImportService())->templateDownloadPayload($importType);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $payload['filename'] . '"');
        echo (string)$payload['content'];
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['manufacturing_import_' . $type] = $message;
    }

    private static function persistUpload(string $tmpPath, string $token, string $sourceName): string
    {
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Upload failed. Please try again.');
        }

        $dir = APP_ROOT . '/storage/import_uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $sourceName) ?: 'import.csv';
        $target = $dir . '/' . $token . '_' . $safeName;
        if (!move_uploaded_file($tmpPath, $target)) {
            throw new \RuntimeException('Could not store the uploaded import file.');
        }

        return $target;
    }
}

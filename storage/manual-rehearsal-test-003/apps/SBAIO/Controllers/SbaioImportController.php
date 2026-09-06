<?php
declare(strict_types=1);

namespace SBAIO\Controllers;

use App\Core\Auth;
use App\Services\ImportHistoryService;
use SBAIO\Services\SbaioLegacyImportService;

final class SbaioImportController
{
    public static function index($view): void
    {
        $history = new ImportHistoryService();
        $previewToken = trim((string)($_GET['preview'] ?? ($_SESSION['sbaio_import_preview'] ?? '')));
        $preview = $previewToken !== '' ? $history->loadPreviewPayload($previewToken) : null;

        $view->render('sbaio::imports.php', [
            'pageTitle' => t('sbaio.imports.title'),
            'preview' => $preview,
            'preview_token' => $previewToken,
            'history' => $history->recentRuns('sbaio', 12),
            'flash_ok' => (string)($_SESSION['sbaio_import_ok'] ?? ''),
            'flash_err' => (string)($_SESSION['sbaio_import_err'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ]);

        unset($_SESSION['sbaio_import_ok'], $_SESSION['sbaio_import_err']);
    }

    public static function previewWorkbook(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $file = $_FILES['workbook'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::flash('err', t('sbaio.imports.error_choose_workbook'));
            header('Location: /apps/sbaio/imports');
            exit;
        }

        $name = trim((string)($file['name'] ?? 'legacy.xlsx'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            self::flash('err', t('sbaio.imports.error_extension'));
            header('Location: /apps/sbaio/imports');
            exit;
        }

        $previewToken = 'sbaio_' . bin2hex(random_bytes(8));
        $uploadPath = self::persistUpload((string)($file['tmp_name'] ?? ''), $previewToken, $name);
        $history = new ImportHistoryService();
        $service = new SbaioLegacyImportService();

        try {
            $preview = $service->previewWorkbook($uploadPath, $name);
            $runId = $history->startPreview('sbaio', 'legacy_workbook', $name, $previewToken, $preview);
            $payload = [
                'preview_token' => $previewToken,
                'run_id' => $runId,
                'suite_key' => 'sbaio',
                'import_type' => 'legacy_workbook',
                'source_file' => $name,
                'uploaded_path' => $uploadPath,
                'preview' => $preview,
            ];
            $history->savePreviewPayload($previewToken, $payload);
            $_SESSION['sbaio_import_preview'] = $previewToken;
            self::flash('ok', t('sbaio.imports.preview_ready'));
            header('Location: /apps/sbaio/imports?preview=' . urlencode($previewToken));
            exit;
        } catch (\Throwable $e) {
            @unlink($uploadPath);
            $history->failRun(null, 'sbaio', 'legacy_workbook', $name, $e->getMessage());
            self::flash('err', $e->getMessage());
            header('Location: /apps/sbaio/imports');
            exit;
        }
    }

    public static function commitWorkbook(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        $history = new ImportHistoryService();
        $payload = $history->loadPreviewPayload($previewToken);
        if (!is_array($payload)) {
            self::flash('err', t('sbaio.imports.preview_expired'));
            header('Location: /apps/sbaio/imports');
            exit;
        }

        $service = new SbaioLegacyImportService();
        try {
            $result = $service->importPreview((array)($payload['preview'] ?? []));
            $history->completeImport((int)($payload['run_id'] ?? 0), $result);
            $history->deletePreviewPayload($previewToken);
            @unlink((string)($payload['uploaded_path'] ?? ''));
            unset($_SESSION['sbaio_import_preview']);
            self::flash('ok', t('sbaio.imports.imported', [
                'salary_rows' => (int)($result['salary_rows_imported'] ?? 0),
                'fixed_profiles' => (int)($result['fixed_profiles_imported'] ?? 0),
            ]));
            header('Location: /apps/sbaio/imports');
            exit;
        } catch (\Throwable $e) {
            $history->failRun((int)($payload['run_id'] ?? 0), 'sbaio', 'legacy_workbook', (string)($payload['source_file'] ?? ''), $e->getMessage(), (array)($payload['preview'] ?? []));
            self::flash('err', $e->getMessage());
            header('Location: /apps/sbaio/imports?preview=' . urlencode($previewToken));
            exit;
        }
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['sbaio_import_' . $type] = $message;
    }

    private static function persistUpload(string $tmpPath, string $token, string $sourceName): string
    {
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException(t('sbaio.imports.upload_failed'));
        }

        $dir = APP_ROOT . '/storage/import_uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $sourceName) ?: 'upload.xlsx';
        $target = $dir . '/' . $token . '_' . $safeName;
        if (!move_uploaded_file($tmpPath, $target)) {
            throw new \RuntimeException(t('sbaio.imports.upload_store_failed'));
        }

        return $target;
    }
}

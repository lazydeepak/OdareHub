<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Services\RestoreHistoryService;
use App\Services\SuiteExportService;
use App\Services\SuiteRestoreService;

final class ManufacturingRestoreController
{
    public static function index($view): void
    {
        $history = new RestoreHistoryService();
        $previewToken = trim((string)($_GET['preview'] ?? ($_SESSION['manufacturing_restore_preview'] ?? '')));
        $preview = $previewToken !== '' ? $history->loadPreview($previewToken) : null;
        if (is_array($preview) && (string)($preview['suite_key'] ?? '') !== 'manufacturing') {
            $preview = null;
            $previewToken = '';
        }

        $exportService = new SuiteExportService();
        $view->render('manufacturing::restores.php', [
            'pageTitle' => 'Manufacturing Restore / Import-Back',
            'suite' => $exportService->suiteCard('manufacturing'),
            'modules' => $exportService->moduleCards('manufacturing'),
            'preview' => $preview,
            'preview_token' => $previewToken,
            'history' => $history->recentRuns('manufacturing', 20),
            'flash_ok' => (string)($_SESSION['manufacturing_restore_ok'] ?? ''),
            'flash_err' => (string)($_SESSION['manufacturing_restore_err'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ]);

        unset($_SESSION['manufacturing_restore_ok'], $_SESSION['manufacturing_restore_err']);
    }

    public static function previewRestore(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $file = $_FILES['backup_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::flash('err', 'Choose a Manufacturing backup file before previewing restore.');
            header('Location: /apps/manufacturing/restores');
            exit;
        }

        $name = trim((string)($file['name'] ?? 'backup.zip'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['zip', 'json'], true)) {
            self::flash('err', 'Use a Manufacturing backup export in .zip or .json format.');
            header('Location: /apps/manufacturing/restores');
            exit;
        }

        $previewToken = 'manufacturing_restore_' . bin2hex(random_bytes(8));
        $uploadPath = self::persistUpload((string)($file['tmp_name'] ?? ''), $previewToken, $name);
        $history = new RestoreHistoryService();
        $service = new SuiteRestoreService();

        try {
            $preview = $service->previewRestore('manufacturing', $uploadPath, $name);
            $runId = $history->startPreview(
                (string)($preview['target_type'] ?? 'suite'),
                (string)($preview['target_key'] ?? 'manufacturing'),
                'manufacturing',
                (string)($preview['restore_type'] ?? 'metadata_only'),
                $name,
                $previewToken,
                $preview
            );
            $payload = [
                'preview_token' => $previewToken,
                'run_id' => $runId,
                'suite_key' => 'manufacturing',
                'source_file' => $name,
                'uploaded_path' => $uploadPath,
                'preview' => $preview,
            ];
            $history->savePreview($previewToken, $payload);
            $_SESSION['manufacturing_restore_preview'] = $previewToken;
            self::flash('ok', 'Restore preview is ready. Review conflicts, dependencies, and overwrite impact before continuing.');
            header('Location: /apps/manufacturing/restores?preview=' . urlencode($previewToken));
            exit;
        } catch (\Throwable $e) {
            @unlink($uploadPath);
            $history->failRun(null, 'suite', 'manufacturing', 'manufacturing', 'preview', $name, $e->getMessage());
            self::flash('err', $e->getMessage());
            header('Location: /apps/manufacturing/restores');
            exit;
        }
    }

    public static function commitRestore(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        $history = new RestoreHistoryService();
        $payload = $history->loadPreview($previewToken);
        if (!is_array($payload) || (string)($payload['suite_key'] ?? '') !== 'manufacturing') {
            self::flash('err', 'The restore preview expired. Preview the backup again.');
            header('Location: /apps/manufacturing/restores');
            exit;
        }

        $options = [
            'restore_scope' => trim((string)($_POST['restore_scope'] ?? 'full')),
            'conflict_strategy' => trim((string)($_POST['conflict_strategy'] ?? 'cancel')),
            'install_missing_dependencies' => !empty($_POST['install_missing_dependencies']),
        ];

        $service = new SuiteRestoreService();
        try {
            $result = $service->restorePreview($payload, $options);
            $history->completeRestore((int)($payload['run_id'] ?? 0), $result, !empty($result['rollback_attempted']));
            $history->deletePreview($previewToken);
            @unlink((string)($payload['uploaded_path'] ?? ''));
            unset($_SESSION['manufacturing_restore_preview']);
            self::flash('ok', 'Restore completed for ' . (string)($result['target_type'] ?? 'target') . ': ' . (string)($result['target_key'] ?? 'manufacturing') . '.');
            header('Location: /apps/manufacturing/restores');
            exit;
        } catch (\Throwable $e) {
            $preview = (array)($payload['preview'] ?? []);
            $history->failRun(
                (int)($payload['run_id'] ?? 0),
                (string)($preview['target_type'] ?? 'suite'),
                (string)($preview['target_key'] ?? 'manufacturing'),
                'manufacturing',
                (string)($preview['restore_type'] ?? 'restore'),
                (string)($payload['source_file'] ?? ''),
                $e->getMessage(),
                $preview
            );
            self::flash('err', $e->getMessage());
            header('Location: /apps/manufacturing/restores?preview=' . urlencode($previewToken));
            exit;
        }
    }

    public static function cancelPreview(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        $history = new RestoreHistoryService();
        $payload = $history->loadPreview($previewToken);
        if (is_array($payload)) {
            @unlink((string)($payload['uploaded_path'] ?? ''));
        }
        $history->deletePreview($previewToken);
        unset($_SESSION['manufacturing_restore_preview']);
        self::flash('ok', 'Restore review cancelled. No live data or package state was changed.');
        header('Location: /apps/manufacturing/restores');
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['manufacturing_restore_' . $type] = $message;
    }

    private static function persistUpload(string $tmpPath, string $token, string $sourceName): string
    {
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Upload failed. Please try again.');
        }

        $dir = APP_ROOT . '/storage/restore_uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $sourceName) ?: 'backup.zip';
        $target = $dir . '/' . $token . '_' . $safeName;
        if (!move_uploaded_file($tmpPath, $target)) {
            throw new \RuntimeException('Could not store the uploaded backup for restore preview.');
        }

        return $target;
    }
}

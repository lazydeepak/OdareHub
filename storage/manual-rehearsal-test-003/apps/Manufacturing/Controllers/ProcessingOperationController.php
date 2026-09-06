<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Core\View;
use Apps\Manufacturing\Services\ProcessingOperationService;

final class ProcessingOperationController
{
    public static function index(View $view): void
    {
        Auth::bootSession();
        $date = trim((string)($_GET['date'] ?? date('Y-m-d')));

        $view->render('manufacturing::execution/processing_operation.php', [
            'pageTitle' => 'Processing Operation',
            'payload' => ProcessingOperationService::build($date, Auth::user()),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function updateAssembly(array $input): void
    {
        try {
            ProcessingOperationService::updateAssemblyProgress($input, Auth::user());
            self::flash('ok', 'Assembly progress updated.');
        } catch (\Throwable $e) {
            self::flash('err', 'Assembly update failed: ' . $e->getMessage());
        }
    }

    public static function updateQc(array $input): void
    {
        try {
            ProcessingOperationService::updateQcProgress($input, Auth::user());
            self::flash('ok', 'QC progress updated.');
        } catch (\Throwable $e) {
            self::flash('err', 'QC update failed: ' . $e->getMessage());
        }
    }

    private static function flash(string $key, string $message): void
    {
        Auth::bootSession();
        $_SESSION['mfg_processing_operation_flash_' . $key] = $message;
    }

    private static function pullFlash(string $key): string
    {
        Auth::bootSession();
        $sessionKey = 'mfg_processing_operation_flash_' . $key;
        $value = (string)($_SESSION[$sessionKey] ?? '');
        unset($_SESSION[$sessionKey]);
        return $value;
    }
}

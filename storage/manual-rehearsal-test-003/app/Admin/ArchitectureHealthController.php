<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\RouteRuntimeAuthority;
use App\Services\UiSurfaceRegistryDiagnosticsService;
use App\Services\AppRegistryService;
use App\Core\DB;

final class ArchitectureHealthController
{
    public static function show($view): void
    {
        Auth::requireAdmin();

        // Route diagnostics
        $routeDiagnostics = RouteRuntimeAuthority::diagnostics();

        // App contract diagnostics
        $apps = DB::fetchAll('SELECT app_key, status, manifest_json FROM core_apps ORDER BY app_key ASC');
        $appDiagnostics = [];
        $uiDiagnosticsService = new UiSurfaceRegistryDiagnosticsService();
        foreach ($apps as $row) {
            $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
            $manifest = is_array($manifest) ? $manifest : [];
            $contractWarnings = (array)($manifest['contract_warnings'] ?? []);
            $uiDiag = $uiDiagnosticsService->diagnosticsForApp((string)($row['app_key'] ?? ''));
            $appDiagnostics[] = [
                'app_key' => (string)($row['app_key'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'contract_warnings' => $contractWarnings,
                'ui_diag' => $uiDiag,
            ];
        }

        // Localization diagnostics for every core-supported admin locale.
        $en = self::loadLocale('en');
        $ja = self::loadLocale('ja');
        $ne = self::loadLocale('ne');
        $allKeys = array_unique(array_merge(array_keys($en), array_keys($ja), array_keys($ne)));
        $missingEn = [];
        $missingJa = [];
        $missingNe = [];
        foreach ($allKeys as $key) {
            if (!array_key_exists($key, $en)) {
                $missingEn[] = $key;
            }
            if (!array_key_exists($key, $ja)) {
                $missingJa[] = $key;
            }
            if (!array_key_exists($key, $ne)) {
                $missingNe[] = $key;
            }
        }

        $view->render('admin/architecture_health.php', [
            'routeDiagnostics' => $routeDiagnostics,
            'appDiagnostics' => $appDiagnostics,
            'missingEn' => $missingEn,
            'missingJa' => $missingJa,
            'missingNe' => $missingNe,
            'en' => $en,
            'ja' => $ja,
            'ne' => $ne,
        ]);
    }

    private static function loadLocale(string $lang): array
    {
        $lang = strtolower(trim($lang));
        $file = APP_ROOT . '/app/Locale/' . $lang . '.php';
        if (!is_file($file)) {
            return [];
        }
        $payload = require $file;
        return is_array($payload) ? $payload : [];
    }
}

<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;

final class ModuleReportRegistryService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function activeReports(): array
    {
        $manifests = glob(APP_ROOT . '/apps/Manufacturing/modules/*/plugin.json') ?: [];
        sort($manifests);

        $statusMap = self::statusMap();
        $reports = [];

        foreach ($manifests as $manifestPath) {
            $moduleDir = dirname($manifestPath);
            $moduleFolder = basename($moduleDir);
            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            $moduleName = trim((string)($manifest['name'] ?? $moduleFolder));
            if ($moduleName === '' || strtolower((string)($statusMap[$moduleName] ?? 'inactive')) !== 'active') {
                continue;
            }

            foreach ((array)($manifest['reports'] ?? []) as $report) {
                if (!is_array($report)) {
                    continue;
                }

                $lifecycle = strtolower(trim((string)($report['lifecycle'] ?? 'active_only')));
                if ($lifecycle !== '' && $lifecycle !== 'active_only') {
                    continue;
                }

                $reports[] = [
                    'module' => $moduleName,
                    'report_key' => trim((string)($report['report_key'] ?? '')),
                    'title' => trim((string)($report['title'] ?? '')),
                    'owner' => trim((string)($report['owner'] ?? 'module')),
                    'scope' => trim((string)($report['scope'] ?? '')),
                    'permission' => trim((string)($report['permission'] ?? '')),
                    'view' => trim((string)($report['view'] ?? '')),
                    'export_view' => trim((string)($report['export_view'] ?? '')),
                    'lifecycle' => $lifecycle,
                    'module_dir' => $moduleDir,
                ];
            }
        }

        return $reports;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findActiveReport(string $reportKey): ?array
    {
        $reportKey = trim($reportKey);
        if ($reportKey === '') {
            return null;
        }

        foreach (self::activeReports() as $report) {
            if (trim((string)($report['report_key'] ?? '')) === $reportKey) {
                return $report;
            }
        }

        return null;
    }

    /**
     * @return array<string,string>
     */
    private static function statusMap(): array
    {
        $rows = DB::fetchAll('SELECT name, status FROM installed_plugins');
        $map = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $map[$name] = trim((string)($row['status'] ?? 'inactive'));
        }

        return $map;
    }
}

<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-BULK-IO-CONTAINMENT-001 probe.
 *
 * Inventories suite-level import/export/restore/download routes and verifies
 * fail-closed admission precedes session, CSRF, controller, SQL, filesystem,
 * package, and restore dispatch. No application bootstrap or bulk I/O runs.
 */

$root = dirname(__DIR__, 4);
$routesPath = $root . '/apps/SBAIO/routes.php';
$routes = file_get_contents($routesPath) ?: '';
$servicePath = $root . '/apps/SBAIO/Services/SbaioBulkIoAdmissionService.php';
$service = file_get_contents($servicePath) ?: '';

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

require_once $servicePath;
$assert(\SBAIO\Services\SbaioBulkIoAdmissionService::isAvailable('suite_bulk_io') === false, 'suite bulk-I/O admission fails closed');
$assert(str_contains($service, 'return false;'), 'bulk-I/O admission has no implicit authorization fallback');

$inventory = [
    '/apps/sbaio/imports' => ['verb' => 'get', 'dispatch' => 'SbaioImportController::index($view)'],
    '/apps/sbaio/exports' => ['verb' => 'get', 'dispatch' => 'SbaioExportController::index($view)'],
    '/apps/sbaio/restores' => ['verb' => 'get', 'dispatch' => 'SbaioRestoreController::index($view)'],
    '/apps/sbaio/imports/preview' => ['verb' => 'post', 'dispatch' => 'SbaioImportController::previewWorkbook()'],
    '/apps/sbaio/imports/commit' => ['verb' => 'post', 'dispatch' => 'SbaioImportController::commitWorkbook()'],
    '/apps/sbaio/exports/suite' => ['verb' => 'post', 'dispatch' => 'SbaioExportController::exportSuite()'],
    '/apps/sbaio/exports/module' => ['verb' => 'post', 'dispatch' => 'SbaioExportController::exportModule()'],
    '/apps/sbaio/restores/preview' => ['verb' => 'post', 'dispatch' => 'SbaioRestoreController::previewRestore()'],
    '/apps/sbaio/restores/commit' => ['verb' => 'post', 'dispatch' => 'SbaioRestoreController::commitRestore()'],
    '/apps/sbaio/restores/cancel' => ['verb' => 'post', 'dispatch' => 'SbaioRestoreController::cancelPreview()'],
    '/apps/sbaio/exports/download' => ['verb' => 'get', 'dispatch' => 'SbaioExportController::download()'],
];

$assert(count($inventory) === 11, 'all eleven suite-level bulk-I/O routes are inventoried');
$assert(substr_count($routes, 'SbaioBulkIoAdmissionService::isAvailable(\'suite_bulk_io\')') === 1, 'central admission closure uses the app-owned guard');
$assert(substr_count($routes, '$requireBulkIoAdmission()') === 11, 'all eleven routes use centralized admission');

foreach ($inventory as $path => $definition) {
    $routeToken = '$router->' . $definition['verb'] . '(\'' . $path . '\'';
    $routeOffset = strpos($routes, $routeToken);
    $assert($routeOffset !== false, "{$path} route exists");
    $routeBlock = $routeOffset === false ? '' : substr($routes, $routeOffset, (int)(strpos($routes, "\n});", $routeOffset) - $routeOffset));
    $authOffset = strpos($routeBlock, "Auth::requireAppAccess('sbaio')");
    $gateOffset = strpos($routeBlock, '$requireBulkIoAdmission()');
    $bootOffset = strpos($routeBlock, 'Auth::bootSession()');
    $dispatchOffset = strpos($routeBlock, $definition['dispatch']);

    $assert($authOffset !== false && $gateOffset !== false && $authOffset < $gateOffset, "{$path} keeps existing app access first");
    $assert($gateOffset !== false && ($bootOffset === false || $gateOffset < $bootOffset), "{$path} gate precedes session boot");
    $assert($gateOffset !== false && $dispatchOffset !== false && $gateOffset < $dispatchOffset, "{$path} gate precedes controller dispatch");
}

$assert(str_contains($routes, 'require_once __DIR__ . \'/Services/SbaioBulkIoAdmissionService.php\''), 'admission service is app-owned and explicitly loaded');
$assert(str_contains($routes, "echo 'Bulk I/O unavailable';"), 'blocked routes return controlled unavailable response');

$exportService = (string)file_get_contents($root . '/app/Services/SuiteExportService.php');
$restoreService = (string)file_get_contents($root . '/app/Services/SuiteRestoreService.php');
$importService = (string)file_get_contents($root . '/apps/SBAIO/Services/SbaioLegacyImportService.php');
$exportController = (string)file_get_contents($root . '/apps/SBAIO/Controllers/SbaioExportController.php');
$importController = (string)file_get_contents($root . '/apps/SBAIO/Controllers/SbaioImportController.php');
$restoreController = (string)file_get_contents($root . '/apps/SBAIO/Controllers/SbaioRestoreController.php');

$assert(str_contains($exportService, 'file_put_contents') && str_contains($exportService, 'ZipArchive'), 'export service filesystem/package operations are identified');
$assert(str_contains($importService, 'begin_transaction') && str_contains($importService, 'DELETE FROM'), 'import service transaction and mutation operations are identified');
$assert(str_contains($restoreService, 'PackageManager::applyPackage') && str_contains($restoreService, 'restoreData'), 'restore service package/data operations are identified');
$assert(str_contains($exportController, 'readfile($path)'), 'backup download filesystem read is identified');
$assert(str_contains($importController, 'move_uploaded_file') && str_contains($restoreController, 'move_uploaded_file'), 'upload filesystem writes are identified');
$assert(str_contains($routes, 'Auth::requireAppAccess(\'sbaio\')'), 'existing app authorization remains present');
$assert(!str_contains($service, 'Auth::') && !str_contains($service, 'DB::') && !str_contains($service, 'PackageManager'), 'admission service adds no new auth, SQL, or package authority');

echo 'SBAIO bulk-I/O containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);

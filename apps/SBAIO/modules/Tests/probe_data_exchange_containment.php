<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-DATA-EXCHANGE-CONTAINMENT-001 probe.
 * No application bootstrap, actual upload, import, database, or queue action.
 */

$root = dirname(__DIR__, 4);
$servicePath = $root . '/apps/SBAIO/Services/SbaioDataExchangeAdmissionService.php';
$contributionPath = $root . '/apps/SBAIO/Services/OperatorSurfaceContributionService.php';
$dxPath = $root . '/apps/Shell/Services/DataExchangeService.php';
$routesPath = $root . '/apps/Shell/routes.php';

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$admissionSource = (string)file_get_contents($servicePath);
$contributionSource = (string)file_get_contents($contributionPath);
$dxSource = (string)file_get_contents($dxPath);
$routesSource = (string)file_get_contents($routesPath);

require_once $servicePath;
require_once $contributionPath;

$assert(\SBAIO\Services\SbaioDataExchangeAdmissionService::isAvailable('sbaio.attendance_daily') === false, 'attendance adapter admission fails closed');
$assert(\SBAIO\Services\SbaioDataExchangeAdmissionService::isAvailable('sbaio.timecard_weekly') === false, 'timecard adapter admission fails closed');
$assert(str_contains($admissionSource, 'return false;'), 'SBAIO data-exchange admission has no implicit authorization fallback');
$assert(str_contains($contributionSource, 'SbaioDataExchangeAdmissionService::isAvailable'), 'SBAIO definitions consult owner admission');
$assert(str_contains($contributionSource, "require_once __DIR__ . '/SbaioDataExchangeAdmissionService.php'"), 'SBAIO contribution loads owner admission without Shell ownership');

$definitions = \Apps\SBAIO\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'data_exchange',
    'context' => ['active_assigned_apps' => ['sbaio']],
]);
$assert($definitions === [] || ($definitions['definitions'] ?? []) === [], 'SBAIO data-exchange contribution exposes neither personnel adapter');

$blockedDefinition = [
    'key' => 'sbaio.attendance_daily',
    'supports_import' => false,
    'governance_mode' => 'audited',
];
require_once $root . '/apps/Shell/Services/DataExchangeService.php';
$upload = \Apps\Shell\Services\DataExchangeService::processImportUpload($blockedDefinition, [
    'name' => 'personnel.csv',
    'tmp_name' => '/definitely/not-an-upload',
    'size' => 10,
    'error' => UPLOAD_ERR_OK,
]);
$assert(($upload['ok'] ?? true) === false && ($upload['error'] ?? '') === 'import_disabled', 'blocked upload stops before is_uploaded_file and storage');
$execution = \Apps\Shell\Services\DataExchangeService::executeImportFromFile($blockedDefinition, '/definitely/not-a-real-stored-file', true);
$assert(($execution['ok'] ?? true) === false && ($execution['error'] ?? '') === 'import_disabled', 'blocked execution stops before stored-file read and SQL');

$processStart = strpos($dxSource, 'public static function processImportUpload');
$processGate = $processStart === false ? false : strpos($dxSource, "if (array_key_exists('supports_import', \$definition)", $processStart);
$processTmp = strpos($dxSource, '$tmp = (string)($file[\'tmp_name\'] ?? \'\');');
$executeStart = strpos($dxSource, 'public static function executeImportFromFile');
$executeGate = $executeStart === false ? false : strpos($dxSource, "if (array_key_exists('supports_import', \$definition)", $executeStart);
$executeFile = $executeStart === false ? false : strpos($dxSource, "if (\$storedPath === '' || !is_file(\$storedPath))", $executeStart);
$assert($processGate !== false && $processTmp !== false && $processGate < $processTmp, 'upload admission precedes filesystem upload inspection');
$assert($executeGate !== false && $executeFile !== false && $executeGate < $executeFile, 'execution admission precedes stored-file inspection');

$jsonField = "'supports_import' => (bool)(\$definition['supports_import'] ?? true)";
$assert(str_contains($dxSource, $jsonField), 'queued definition persists import capability state');
$approvalCall = strpos($dxSource, 'self::executeImportFromFile($definition');
$approvalLookup = strpos($dxSource, "SELECT * FROM data_exchange_jobs WHERE id = ? LIMIT 1");
$assert($approvalLookup !== false && $approvalCall !== false && $approvalLookup < $approvalCall, 'approval reads only queue metadata before governed execution');
$assert(str_contains($dxSource, 'self::findDefinition($context, (string)($definition[\'key\'] ?? \'\'))'), 'queued approval re-admits the current owner definition');
$assert(str_contains($dxSource, "'status' => 'pending'"), 'queued jobs remain pending when approval execution is blocked');

$importRoute = strpos($routesSource, "\$router->post('/u/data-exchange/import'");
$findDefinition = strpos($routesSource, 'DataExchangeService::findDefinition($ctx, $adapterKey)', $importRoute ?: 0);
$uploadCall = strpos($routesSource, 'DataExchangeService::processImportUpload', $importRoute ?: 0);
$assert($importRoute !== false && $findDefinition !== false && $uploadCall !== false && $findDefinition < $uploadCall, 'import route resolves an admitted definition before upload');
$assert(str_contains($routesSource, "if (empty(\$definition['supports_import']))"), 'route preserves generic import capability gate');
$assert(str_contains($routesSource, "\$router->post('/u/data-exchange/job/approve'"), 'queued approval route remains present for authorized non-SBAIO adapters');
$composerSource = (string)file_get_contents($root . '/apps/Shell/Composers/DataExchangeComposer.php');
$assert(str_contains($composerSource, '$definitions === []'), 'queue listing is suppressed when no current adapters are admitted');

$mfgSource = (string)file_get_contents($root . '/apps/Manufacturing/Services/OperatorSurfaceContributionService.php');
$assert(str_contains($mfgSource, "'key' => 'mfg.production_daily'"), 'Manufacturing production adapter remains declared');
$assert(str_contains($mfgSource, "'key' => 'mfg.daily_orders'"), 'Manufacturing orders adapter remains declared');
$assert(str_contains($mfgSource, "'key' => 'mfg.dispatch_manifest'"), 'Manufacturing dispatch adapter remains declared');
$assert(!str_contains($contributionSource, 'DB::') && !str_contains($admissionSource, 'DB::'), 'SBAIO adapter containment adds no database operation');

echo 'SBAIO data-exchange containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);

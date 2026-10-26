<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-NOTICES-EXPORT-PRIVACY-001 probe.
 *
 * Confirms that Notice CSV export retains the existing authorized metadata
 * contract while excluding body and Staff linkage fields. No application
 * bootstrap or installed/configured database is loaded.
 */

$registry = file_get_contents(__DIR__ . '/../../../Services/ModuleReportRegistryService.php') ?: '';
$routes = file_get_contents(__DIR__ . '/../routes.php') ?: '';
$report = file_get_contents(__DIR__ . '/probe_notices_report_ownership.php') ?: '';
$continuity = file_get_contents(__DIR__ . '/probe_notices_continuity.php') ?: '';

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$columns = "'columns' => ['id', 'title', 'notice_type', 'target_period', 'requires_ack', 'notice_level', 'is_active', 'created_at', 'updated_at']";
$assert(str_contains($registry, $columns), 'Notice CSV source declares the exact metadata-only column projection');
$assert(!str_contains($registry, "'columns' => ['id', 'title', 'body'") && !str_contains($registry, 'related_staff_id'), 'Notice CSV source does not authorize body or Staff linkage export');
$assert(str_contains($registry, 'SELECT %s FROM %s ORDER BY %s %s LIMIT 5000'), 'CSV engine executes the descriptor projection rather than forcing SELECT *');
$assert(str_contains($routes, "Auth::requireAppAccess('sbaio')") && str_contains($routes, 'streamCsvForReport($reportKey)'), 'Notice export remains behind existing SBAIO authorization and registry dispatch');
$assert(str_contains($report, "!preg_match('/\\bbody\\b/i"), 'Notice report presentation excludes body');
$assert(str_contains($continuity, '!array_key_exists(\'body\', $record)') && str_contains($continuity, '!str_contains(DB::$queries[0][\'sql\'], \'body\')'), 'Notice exact retrieval remains body-free');

echo 'Notice export privacy probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);

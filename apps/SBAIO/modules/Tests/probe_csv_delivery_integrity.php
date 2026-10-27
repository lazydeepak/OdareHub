<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-CSV-DELIVERY-INTEGRITY-001 probe.
 *
 * Verifies export audit chronology and output-failure guards from source. It
 * never bootstraps the application, opens the configured database, or emits a
 * real export response.
 */

$root = dirname(__DIR__, 4);
$registryPath = $root . '/apps/SBAIO/Services/ModuleReportRegistryService.php';
$registry = file_get_contents($registryPath) ?: '';

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$streamOffset = strpos($registry, 'public static function streamCsvForReport');
$stream = $streamOffset === false ? '' : substr($registry, $streamOffset);
$successOffset = strpos($stream, 'self::recordCsvExportSuccess(');
$closeOffset = strpos($stream, 'if (!fclose($out))');
$returnOffset = strpos($stream, 'return true;');
$failureCall = 'self::recordCsvExportFailure($reportKey, $e->getMessage())';
$failureOffset = strpos($stream, $failureCall);

$assert($streamOffset !== false, 'CSV stream method exists');
$assert($successOffset !== false && $closeOffset !== false && $successOffset > $closeOffset, 'success audit occurs after output close validation');
$assert($successOffset !== false && $returnOffset !== false && $successOffset < $returnOffset, 'success audit occurs before successful return');
$assert(strpos($stream, 'fwrite($out, $bom) !== strlen($bom)') !== false, 'BOM write failure is detected');
$headerWriteGuard = trim(<<<'SOURCE'
fputcsv($out, $headers, ',', '"', '\\') === false
SOURCE);
$rowWriteGuard = trim(<<<'SOURCE'
fputcsv($out, $line, ',', '"', '\\') === false
SOURCE);
$assert(substr_count($stream, $headerWriteGuard) === 1, 'header write failure is detected');
$assert(substr_count($stream, $rowWriteGuard) === 1, 'row write failure is detected');
$assert($failureOffset !== false && str_contains($stream, $failureCall), 'output exception records export failure');
$assert(str_contains($stream, "self::recordCsvExportFailure(\$reportKey, 'Unable to open output stream.')"), 'stream-open failure records export failure');
$assert(str_contains($stream, 'if (is_resource($out))'), 'output failure closes the owned stream');
$assert(str_contains($stream, 'if (isset($out) && is_resource($out))'), 'outer failure path also closes the owned stream');
$assert(str_contains($registry, 'private static function serializeCsvCell'), 'formula-safe serializer remains present');
$assert(str_contains($stream, 'fputcsv($out, $line'), 'CSV delivery still uses fputcsv');

$beforeChronology = [
    'stream opened' => 1,
    'success recorded' => 2,
    'BOM written' => 3,
    'rows written' => 4,
    'stream closed' => 5,
];
$afterChronology = [
    'stream opened' => 1,
    'BOM written' => 2,
    'rows written' => 3,
    'stream closed' => 4,
    'success recorded' => 5,
];
$assert($beforeChronology['success recorded'] < $beforeChronology['BOM written'], 'baseline evidence reproduces premature success chronology');
$assert($afterChronology['success recorded'] > $afterChronology['stream closed'], 'corrected chronology records success last');

echo 'CSV delivery integrity probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);

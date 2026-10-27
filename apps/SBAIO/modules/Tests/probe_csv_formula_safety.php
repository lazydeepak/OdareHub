<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-CSV-FORMULA-SAFETY-001 probe.
 *
 * Reproduces the prior raw-cell behavior, then verifies the centralized CSV
 * serializer neutralizes formula-like text without changing numeric, boolean,
 * null, or structured-value compatibility. No application bootstrap or
 * configured database is loaded.
 */

namespace App\Core {
    final class DB
    {
    }
}

namespace App\Services {
    final class ExportHistoryService
    {
    }
}

namespace {
    $root = dirname(__DIR__, 4);
    define('APP_ROOT', $root);
    require_once $root . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

    $assertions = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    $before = static function (mixed $value): string {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };

    $formulaCells = [
        '=SUM(A1:A2)',
        '+CMD|\' /C calc\'!A1',
        '-1+2',
        '@SUM(A1:A2)',
        "\t=HYPERLINK(\"https://example.test\",\"open\")",
    ];

    foreach ($formulaCells as $index => $value) {
        $raw = $before($value);
        $assert(in_array($raw[0], ['=', '+', '-', '@', "\t"], true), "before cell {$index} begins with an injection trigger");
    }

    $reflection = new \ReflectionClass(\Apps\SBAIO\Services\ModuleReportRegistryService::class);
    $method = $reflection->getMethod('serializeCsvCell');

    $serialize = static function (mixed $value) use ($method): string {
        return (string)$method->invoke(null, $value);
    };

    foreach ($formulaCells as $index => $value) {
        $safe = $serialize($value);
        $assert($safe === "'" . $value, "formula-like text {$index} receives one literal prefix");
        $assert(!in_array($safe[0], ['=', '+', '-', '@', "\t"], true), "formula-like text {$index} no longer begins with a trigger");
    }

    $csv = fopen('php://temp', 'w+b');
    if ($csv === false) {
        $assert(false, 'CSV temp stream opens');
    } else {
        fputcsv($csv, [$before('=SUM(A1,A2)'), 'normal'], ',', '"', '\\');
        rewind($csv);
        $rawLine = (string)fgets($csv);
        $assert($rawLine === "\"=SUM(A1,A2)\",normal\n", 'before serialization emits a formula candidate in CSV output');
        fclose($csv);
    }

    $csv = fopen('php://temp', 'w+b');
    if ($csv === false) {
        $assert(false, 'protected CSV temp stream opens');
    } else {
        fputcsv($csv, [$serialize('=SUM(A1,A2)'), $serialize('normal')], ',', '"', '\\');
        rewind($csv);
        $line = (string)fgets($csv);
        fclose($csv);
        $assert($line === "\"'=SUM(A1,A2)\",normal\n", 'protected formula text remains correctly quoted in CSV output');
    }

    $compatibility = [
        [42, '42'],
        [12.5, '12.5'],
        ['00123', '00123'],
        ['-42', '-42'],
        ['+7', '+7'],
        ['1.25e+3', '1.25e+3'],
        [true, '1'],
        [false, '0'],
        [null, ''],
        [['label' => '東京'], '{"label":"東京"}'],
    ];
    foreach ($compatibility as [$input, $expected]) {
        $actual = $serialize($input);
        $assert($actual === $expected, 'compatible cell serialization is preserved for ' . var_export($input, true));
    }

    $registry = (string)file_get_contents($root . '/apps/SBAIO/Services/ModuleReportRegistryService.php');
    $assert(str_contains($registry, 'private static function serializeCsvCell'), 'central serializer is private and centralized');
    $assert(str_contains($registry, '$line[] = self::serializeCsvCell('), 'CSV rows use the centralized serializer');
    $assert(str_contains($registry, 'is_numeric($value)'), 'numeric string compatibility is explicit');

    echo 'CSV formula safety probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}

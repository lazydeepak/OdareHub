<?php
declare(strict_types=1);

/**
 * Database-free Coverage active-interface localization probe.
 */

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$reportView = file_get_contents(__DIR__ . '/../Views/report.php') ?: '';
$assert(str_contains($reportView, "t('coverage.coverage_report_title')"), 'active Coverage report renders the localized title key');

$expected = [
    'ja' => 'カバレッジレポート',
    'ne' => 'कभरेज रिपोर्ट',
];
foreach ($expected as $locale => $translation) {
    $resource = require __DIR__ . '/../Resources/lang/' . $locale . '.php';
    $assert(array_key_exists('coverage.coverage_report_title', $resource), $locale . ' defines the active report title key');
    $assert(($resource['coverage.coverage_report_title'] ?? null) === $translation, $locale . ' has the exact Coverage report title translation');
    $assert($translation !== 'coverage.coverage_report_title', $locale . ' does not expose the localization key as UI text');
}

echo "Coverage localization probe: {$assertions} assertions, {$failures} failures\n";
exit($failures === 0 ? 0 : 1);

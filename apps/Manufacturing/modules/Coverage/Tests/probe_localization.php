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
$exportView = file_get_contents(__DIR__ . '/../Views/export.php') ?: '';
$indexView = file_get_contents(__DIR__ . '/../Views/coverage/index.php') ?: '';
$assert(str_contains($reportView, "t('coverage.coverage_report_title')"), 'active Coverage report renders the localized title key');
$assert(str_contains($exportView, "coverage.coverage_export_title"), 'active Coverage export renders a localized title key');
$assert(str_contains($indexView, "coverage.coverage_window_filter_action"), 'active Coverage dashboard renders a localized filter label');

$expected = [
    'ja' => [
        'common.status_label' => '状態',
        'coverage.coverage_export_title' => 'カバレッジエクスポート',
        'coverage.coverage_report_title' => 'カバレッジレポート',
        'coverage.coverage_snapshot_title' => 'カバレッジスナップショット',
        'coverage.coverage_window_filter_action' => 'カバレッジ期間フィルター',
        'coverage.export_active_message' => 'レポートキーのエクスポートが有効です',
        'coverage.export_unavailable_message' => 'モジュールが無効または登録されていないため、エクスポートを利用できません。',
        'coverage.open_action' => 'オープン注文',
        'coverage.open_link' => 'エクスポートを開く',
        'coverage.report_key_label' => 'レポートキー',
        'coverage.report_unavailable_message' => 'モジュールが無効または登録されていないため、このレポートは利用できません。',
        'mfg.cov.status.no_data' => 'データなし',
    ],
    'ne' => [
        'common.status_label' => 'स्थिति',
        'coverage.coverage_export_title' => 'कभरेज निर्यात',
        'coverage.coverage_report_title' => 'कभरेज रिपोर्ट',
        'coverage.coverage_snapshot_title' => 'कभरेज स्न्यापसट',
        'coverage.coverage_window_filter_action' => 'कभरेज विन्डो फिल्टर',
        'coverage.export_active_message' => 'रिपोर्ट कुञ्जीका लागि निर्यात सक्रिय छ',
        'coverage.export_unavailable_message' => 'मोड्युल निष्क्रिय वा दर्ता नभएकाले निर्यात उपलब्ध छैन।',
        'coverage.open_action' => 'खुला अर्डरहरू',
        'coverage.open_link' => 'निर्यात खोल्नुहोस्',
        'coverage.report_key_label' => 'रिपोर्ट कुञ्जी',
        'coverage.report_unavailable_message' => 'मोड्युल निष्क्रिय वा दर्ता नभएकाले यो रिपोर्ट उपलब्ध छैन।',
    ],
];
foreach ($expected as $locale => $translations) {
    $resource = require __DIR__ . '/../Resources/lang/' . $locale . '.php';
    foreach ($translations as $key => $translation) {
        $assert(array_key_exists($key, $resource), $locale . ' defines active key ' . $key);
        $assert(($resource[$key] ?? null) === $translation, $locale . ' has the exact translation for ' . $key);
        $assert($translation !== $key, $locale . ' does not expose ' . $key . ' as UI text');
    }
}

echo "Coverage localization probe: {$assertions} assertions, {$failures} failures\n";
exit($failures === 0 ? 0 : 1);

<?php
declare(strict_types=1);

/**
 * Database-free AssemblyEntries active-interface localization probe.
 */

$root = dirname(__DIR__, 5);
if (!defined('APP_NAME')) {
    define('APP_NAME', 'OdareHub');
}

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$activeSource = '';
foreach (['report.php', 'export.php', 'add.php', 'edit.php'] as $view) {
    $activeSource .= file_get_contents(__DIR__ . '/../Views/' . $view) ?: '';
}

$expected = [
    'ja' => [
        'assembly_entries.30_day_snapshot_title' => '30日間のスナップショット',
        'assembly_entries.add_title' => '組立実績を追加',
        'assembly_entries.assembly_entries_report_title' => '組立実績レポート',
        'assembly_entries.assembly_entry_export_title' => '組立実績エクスポート',
        'assembly_entries.by_status_title' => 'ステータス別',
        'assembly_entries.completed_column' => '完了',
        'assembly_entries.edit_title' => '組立実績を編集',
        'assembly_entries.export_active_message' => 'レポートキーのエクスポートが有効です',
        'assembly_entries.export_description' => '組立実績の数量、却下、承認、後続工程へのリリース状況をエクスポートします。',
        'assembly_entries.export_unavailable_message' => '所有モジュールが無効または登録されていないため、エクスポートを利用できません。',
        'assembly_entries.form_description' => '組立実行実績フォームは現在、組立計画詳細ワークフローから送信されます。',
        'assembly_entries.open_link' => 'エクスポートを開く',
        'assembly_entries.rejected_column' => '却下',
        'assembly_entries.report_key_label' => 'レポートキー',
        'assembly_entries.report_unavailable_message' => '所有モジュールが無効または登録されていないため、このレポートは利用できません。',
        'common.status_label' => '状態',
    ],
    'ne' => [
        'assembly_entries.30_day_snapshot_title' => '३०-दिने स्न्यापसट',
        'assembly_entries.add_title' => 'असेम्बली प्रविष्टि थप्नुहोस्',
        'assembly_entries.assembly_entries_report_title' => 'असेम्बली प्रविष्टिहरूको रिपोर्ट',
        'assembly_entries.assembly_entry_export_title' => 'असेम्बली प्रविष्टि निर्यात',
        'assembly_entries.by_status_title' => 'स्थितिअनुसार',
        'assembly_entries.completed_column' => 'पूरा भएको',
        'assembly_entries.edit_title' => 'असेम्बली प्रविष्टि सम्पादन गर्नुहोस्',
        'assembly_entries.export_active_message' => 'रिपोर्ट कुञ्जीका लागि निर्यात सक्रिय छ',
        'assembly_entries.export_description' => 'असेम्बली कार्यान्वयनका मात्रा, अस्वीकृति, स्वीकृति र पछिल्लो चरणमा रिलिज अवस्थाको निर्यात सतह।',
        'assembly_entries.export_unavailable_message' => 'मोड्युल निष्क्रिय वा दर्ता नभएकाले निर्यात उपलब्ध छैन।',
        'assembly_entries.form_description' => 'असेम्बली कार्यान्वयन प्रविष्टि फारम हाल असेम्बली योजनाको विवरण कार्यप्रवाहबाट पेश गरिन्छ।',
        'assembly_entries.open_link' => 'निर्यात खोल्नुहोस्',
        'assembly_entries.rejected_column' => 'अस्वीकृत',
        'assembly_entries.report_key_label' => 'रिपोर्ट कुञ्जी',
        'assembly_entries.report_unavailable_message' => 'मोड्युल निष्क्रिय वा दर्ता नभएकाले यो रिपोर्ट उपलब्ध छैन।',
        'common.status_label' => 'स्थिति',
    ],
];

foreach ($expected as $locale => $translations) {
    $resource = require __DIR__ . '/../Resources/lang/' . $locale . '.php';
    $global = require $root . '/app/Locale/' . $locale . '.php';
    foreach ($translations as $key => $translation) {
        $assert(str_contains($activeSource, $key), $key . ' is referenced by an active AssemblyEntries view');
        $assert(array_key_exists($key, $resource), $locale . ' defines active key ' . $key);
        $assert(($resource[$key] ?? null) === $translation, $locale . ' has the exact translation for ' . $key);
        $resolved = $resource[$key] ?? $global[$key] ?? $key;
        $assert($resolved === $translation, $locale . ' runtime resolves ' . $key . ' without literal fallback');
    }
}

echo "AssemblyEntries localization probe: {$assertions} assertions, {$failures} failures\n";
exit($failures === 0 ? 0 : 1);

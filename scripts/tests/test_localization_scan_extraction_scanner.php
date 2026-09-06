<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioEditService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanExtractionScanner.php';

use Apps\Studio\Tools\LocalizationScanExtraction\Services\LocalizationScanExtractionScanner;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\SuggestionQualityGateService;

$result = LocalizationScanExtractionScanner::scanFixture(
    'Manufacturing/Coverage',
    [
        'mfg.cov.title' => 'Coverage',
        'coverage.used' => 'Used Coverage Label',
        'coverage.unused' => 'Unused Coverage Label',
    ],
    [
        'apps/Manufacturing/modules/Coverage/Views/fixture.php' => <<<'PHP'
<h1>Coverage Report</h1>
<h2>Coverage Snapshot</h2>
<button>Open Export</button>
<label>Report Key:</label>
<th>Customer</th>
<p>No orders at critical shortage or below 25% coverage.</p>
<input aria-label="Coverage Window Filter" title="Open Export" placeholder="Search orders">
<input aria-label="Coverage Window Filter">
<?= t('mfg.cov.filter.today') ?>
<input title="Coverage Window Filter">
<?= t('mfg.cov.filter.coverage_window') ?>
<?= t('mfg.cov.title') ?>
<?= t('coverage.used') ?>
<span>Open Orders</span>
<?= t('mfg.cov.kpi.open_orders') ?>
<?= t('mfg.cov.kpi.open_orders') ?>
<?= t('mfg.cov.col.customer') ?>
<?= t('mfg.cov.noisy.data_row') ?>
<?= t('common.save') ?>
<?php // spacing: no rendered text near these missing-key calls ?>
<?= t('mfg.cov.missing') ?>
<?= t('mfg.cov.apply') ?>
<?= t('mfg.cov.created_at') ?>
<?= t('mfg.cov.default_value') ?>
<?= t('mfg.cov.metric.coverage_pct') ?>
<?= t('mfg.cov.metric.avg_coverage_pct') ?>
<?= t('mfg.cov.metric.demand_qty') ?>
<?= t('mfg.cov.metric.shortage_qty') ?>
<?= t('mfg.cov.metric.covered_qty') ?>
<?= t('mfg.cov.metric.fully_covered_count') ?>
<?= t('mfg.cov.metric.critical_count') ?>
<?= t('mfg.cov.priority.priorities_desc') ?>
<?= t('mfg.cov.window.3day') ?>
<?= t('mfg.cov.window.7day') ?>
<?= t('mfg.cov.generic.title') ?>
<?= t('mfg.cov.generic.subtitle') ?>
<?= t('mfg.cov.generic.empty') ?>
<?= t('mfg.cov.generic.empty_note') ?>
<?= t('mfg.cov.route.dashboard_path') ?>
<?= t('mfg.cov.ambiguous.this_key_name_is_too_long_to_apply_without_review') ?>
<?= t('mfg.cov.btn_apply') ?>
<?= t('mfg.cov.btn_register') ?>
<?= t('mfg.cov.mode_qc') ?>
<?= t('mfg.cov.mode_read_only') ?>
<?= t('mfg.cov.recover_module_desc') ?>
<?php echo " data-row=\"coverage\" data-required-date=\""; ?>
<?php echo " data-order-date=\""; ?>
<?php echo "class=\"card\" id=\"coverage-filter\" href=\"/apps/manufacturing/coverage\""; ?>
<?php $where[] = "0 AND COALESCE(shortage_qty, 0) > 0"; ?>
<?php $method = 'POST'; $field = 'created_at'; $mime = 'text/html'; ?>
<span>?key=value</span>
<span>name$variable</span>
PHP,
    ]
);

$findings = $result['findings'];

$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$findByDetected = static function (string $detected, ?string $category = null) use ($findings): array {
    $matches = [];
    foreach ($findings as $finding) {
        if (($finding['detected'] ?? '') !== $detected) {
            continue;
        }
        if ($category !== null && ($finding['category'] ?? '') !== $category) {
            continue;
        }
        $matches[] = $finding;
    }
    return $matches;
};

$hasHuman = static fn(string $text): bool => $findByDetected($text, 'human_facing_candidate') !== [];
$hasCategory = static fn(string $text, string $category): bool => $findByDetected($text, $category) !== [];

foreach (['Coverage Report', 'Coverage Snapshot', 'Open Export', 'Report Key:', 'Customer', 'No orders at critical shortage or below 25% coverage.'] as $text) {
    $assert($hasHuman($text), "visible UI text should be human-facing: {$text}");
}

foreach (['Coverage Window Filter', 'Search orders'] as $text) {
    $matches = $findByDetected($text, 'human_facing_candidate');
    $assert($matches !== [], "complete human-facing attribute should be candidate: {$text}");
    $assert(($matches[0]['confidence'] ?? '') === 'medium', "attribute candidate should be medium confidence: {$text}");
}

foreach (['Coverage Report', 'Coverage Snapshot', 'Open Export', 'Report Key:', 'Customer'] as $text) {
    $matches = $findByDetected($text, 'human_facing_candidate');
    $assert($matches !== [], "visible static text exists for confidence check: {$text}");
    $assert(($matches[0]['confidence'] ?? '') === 'high', "visible static text should be high confidence: {$text}");
}

$assert($hasCategory('mfg.cov.title', 'already_localized_usage'), 'existing mfg.cov key should be already localized');
$assert($hasCategory('coverage.used', 'already_localized_usage'), 'existing coverage key should be already localized');
$assert($hasCategory('mfg.cov.missing', 'missing_owner_key'), 'missing mfg.cov key should be missing owner key');
$assert($hasCategory('common.save', 'external_shared_key_usage'), 'common.* key should be shared/external');

$missingOwnerMatches = $findByDetected('mfg.cov.missing', 'missing_owner_key');
$missingOwnerFinding = $missingOwnerMatches[0] ?? [];
$assert(($missingOwnerFinding['action_kind'] ?? '') === 'open_localization_studio', 'missing owner key should expose Localization Studio handoff action');
$assert(str_contains((string)($missingOwnerFinding['handoff_url'] ?? ''), '/apps/studio/tools/localization-studio/edit'), 'missing owner key should link to Localization Studio editor');
$assert(str_contains((string)($missingOwnerFinding['handoff_url'] ?? ''), 'owner=Manufacturing%2FCoverage'), 'handoff URL should preserve owner');
$assert(str_contains((string)($missingOwnerFinding['handoff_url'] ?? ''), 'locale=en'), 'handoff URL should preserve locale');
$assert(str_contains((string)($missingOwnerFinding['handoff_url'] ?? ''), 'key=mfg.cov.missing'), 'handoff URL should preserve missing key');
$assert(str_contains((string)($missingOwnerFinding['handoff_url'] ?? ''), 'return_to=%2Fapps%2Fstudio%2Ftools%2Flocalization-scan-extraction'), 'handoff URL should preserve internal scan return path');

$assert(SuggestionQualityGateService::rejectionReason('', ';font-weight:700;font-size:.78em;padding:3px 10px;border-radius:3px;') === 'css_fragment', 'quality gate should reject CSS fragments');
$assert(SuggestionQualityGateService::rejectionReason('', '/apps/studio/tools/localization-scan-extraction?owner=Plugin%2FBase') === 'path_url', 'quality gate should reject route/query strings');
$assert(SuggestionQualityGateService::rejectionReason('', "'+fileStatus+'") === 'expression_fragment', 'quality gate should reject JS expression fragments');
$assert(SuggestionQualityGateService::generatedJunkReason('Btn Apply') !== '', 'quality gate should flag generated Btn labels');
$assert(SuggestionQualityGateService::generatedJunkReason('Mode Qc') !== '', 'quality gate should flag generated Mode labels');
$assert(SuggestionQualityGateService::intentionalTailValue('btn_apply') === 'Apply', 'quality gate should intentionally map btn_apply');
$assert(SuggestionQualityGateService::intentionalTailValue('mode_qc') === 'QC', 'quality gate should intentionally map mode_qc');
$assert(SuggestionQualityGateService::canonicalEnglishValue('base.db_control.btn_apply') === 'Apply Selected Sync', 'canonical app/Locale/en.php value should win for Base btn_apply');

$reviewPlan = isset($result['missing_key_review_plan']) && is_array($result['missing_key_review_plan']) ? $result['missing_key_review_plan'] : [];
$reviewRows = isset($reviewPlan['rows']) && is_array($reviewPlan['rows']) ? $reviewPlan['rows'] : [];
$reviewGroups = isset($reviewPlan['groups']) && is_array($reviewPlan['groups']) ? $reviewPlan['groups'] : [];
$reviewTsv = (string)($reviewPlan['tsv'] ?? '');

$reviewRowByKey = [];
foreach ($reviewRows as $row) {
    $reviewRowByKey[(string)($row['key'] ?? '')] = $row;
}
$reviewGroupNames = array_map(static fn(array $group): string => (string)($group['group'] ?? ''), $reviewGroups);

$assert(isset($reviewRowByKey['mfg.cov.kpi.open_orders']), 'review plan should include missing KPI key');
$assert(isset($reviewRowByKey['mfg.cov.col.customer']), 'review plan should include missing column key');
$assert(in_array('mfg.cov.kpi', $reviewGroupNames, true), 'missing KPI key should be grouped by prefix');
$assert(in_array('mfg.cov.col', $reviewGroupNames, true), 'missing column key should be grouped by prefix');
$assert((int)($reviewRowByKey['mfg.cov.kpi.open_orders']['usage_count'] ?? 0) === 2, 'duplicate missing key usage should be counted');
$assert(($reviewRowByKey['mfg.cov.kpi.open_orders']['suggested_english_value'] ?? '') === 'Open Orders', 'nearby rendered text should create high confidence suggestion');
$assert(in_array(($reviewRowByKey['mfg.cov.kpi.open_orders']['suggestion_confidence'] ?? ''), ['high', 'auto_safe'], true), 'nearby/canonical rendered text suggestion should be trusted');
$assert(($reviewRowByKey['mfg.cov.kpi.open_orders']['suggestion_ready'] ?? false) === true, 'high confidence suggestion should be ready');
$assert(($reviewRowByKey['mfg.cov.col.customer']['suggested_english_value'] ?? '') === 'Customer', 'natural key tail should create advisory suggestion');
$assert(in_array(($reviewRowByKey['mfg.cov.col.customer']['suggestion_confidence'] ?? ''), ['medium', 'auto_safe'], true), 'natural/canonical key tail suggestion should be trusted');
$assert(($reviewRowByKey['mfg.cov.col.customer']['suggestion_ready'] ?? false) === true, 'medium confidence suggestion that passes rejection should be ready');
$assert(($reviewRowByKey['mfg.cov.apply']['suggested_english_value'] ?? '') === 'Apply', 'auto-safe action key should produce English value');
$assert(($reviewRowByKey['mfg.cov.apply']['suggestion_confidence'] ?? '') === 'auto_safe', 'auto-safe action key should expose auto_safe confidence');
$assert(($reviewRowByKey['mfg.cov.apply']['suggestion_ready'] ?? false) === true, 'auto-safe action key should be ready');
$assert(($reviewRowByKey['mfg.cov.created_at']['suggested_english_value'] ?? '') === 'Created At', 'auto-safe timestamp key should produce English value');
$assert(($reviewRowByKey['mfg.cov.created_at']['suggestion_confidence'] ?? '') === 'auto_safe', 'auto-safe timestamp key should expose auto_safe confidence');
$assert(($reviewRowByKey['mfg.cov.default_value']['suggestion_ready'] ?? false) === true, 'auto-safe default value key should be ready');
$assert(($reviewRowByKey['mfg.cov.metric.coverage_pct']['suggested_english_value'] ?? '') === 'Coverage %', 'coverage_pct should use percent label');
$assert(($reviewRowByKey['mfg.cov.metric.avg_coverage_pct']['suggested_english_value'] ?? '') === 'Average Coverage %', 'avg_coverage_pct should use average percent label');
$assert(($reviewRowByKey['mfg.cov.metric.demand_qty']['suggested_english_value'] ?? '') === 'Demand Quantity', 'demand_qty should expand quantity');
$assert(($reviewRowByKey['mfg.cov.metric.shortage_qty']['suggested_english_value'] ?? '') === 'Shortage Quantity', 'shortage_qty should expand quantity');
$assert(($reviewRowByKey['mfg.cov.metric.covered_qty']['suggested_english_value'] ?? '') === 'Covered Quantity', 'covered_qty should expand quantity');
$assert(($reviewRowByKey['mfg.cov.metric.fully_covered_count']['suggested_english_value'] ?? '') === 'Fully Covered Orders', 'fully_covered_count should use domain wording');
$assert(($reviewRowByKey['mfg.cov.metric.critical_count']['suggested_english_value'] ?? '') === 'Critical Orders', 'critical_count should use domain wording');
$assert(($reviewRowByKey['mfg.cov.priority.priorities_desc']['suggested_english_value'] ?? '') === 'Priority Orders Requiring Attention', 'priorities_desc should use domain wording');
$assert(($reviewRowByKey['mfg.cov.window.3day']['suggested_english_value'] ?? '') === 'Next 3 Days', '3day should become Next 3 Days');
$assert(($reviewRowByKey['mfg.cov.window.7day']['suggested_english_value'] ?? '') === 'Next 7 Days', '7day should become Next 7 Days');
$assert(($reviewRowByKey['mfg.cov.generic.title']['suggested_english_value'] ?? '') === 'Coverage Dashboard', 'generic title should use owner context');
$assert(($reviewRowByKey['mfg.cov.generic.title']['suggestion_evidence_type'] ?? '') === 'Context Derived', 'generic title should expose context evidence');
$assert(($reviewRowByKey['mfg.cov.generic.subtitle']['suggested_english_value'] ?? '') === 'Monitor order coverage and shortages', 'generic subtitle should use domain context');
$assert(($reviewRowByKey['mfg.cov.generic.empty']['suggested_english_value'] ?? '') === 'No coverage records found', 'generic empty should use owner context');
$assert(($reviewRowByKey['mfg.cov.generic.empty_note']['suggested_english_value'] ?? '') === 'Try adjusting filters or date range', 'generic empty_note should use filter/date context');
$assert(in_array(($reviewRowByKey['mfg.cov.filter.today']['suggested_english_value'] ?? ''), ['Today', 'Today:'], true), 'today should use contextual or canonical calendar label');
$assert(($reviewRowByKey['mfg.cov.filter.today']['suggestion_ready'] ?? false) === true, 'today should be ready after contextual differentiation');
$assert(($reviewRowByKey['mfg.cov.filter.coverage_window']['suggested_english_value'] ?? '') === 'Coverage Window', 'coverage_window should differentiate from filter label');
$assert(($reviewRowByKey['mfg.cov.filter.coverage_window']['suggestion_ready'] ?? false) === true, 'coverage_window should be ready after contextual differentiation');
$assert(($reviewRowByKey['mfg.cov.route.dashboard_path']['suggestion_ready'] ?? true) === false, 'route/path key should not be ready');
$assert(($reviewRowByKey['mfg.cov.route.dashboard_path']['suggestion_rejection_reason'] ?? '') !== '', 'route/path key should explain rejection');
$assert(($reviewRowByKey['mfg.cov.ambiguous.this_key_name_is_too_long_to_apply_without_review']['suggestion_ready'] ?? true) === false, 'ambiguous long key should not be ready');
$assert(($reviewRowByKey['mfg.cov.ambiguous.this_key_name_is_too_long_to_apply_without_review']['suggestion_rejection_reason'] ?? '') === 'ambiguous_key', 'ambiguous long key should explain review reason');
$assert(($reviewRowByKey['mfg.cov.btn_apply']['suggested_english_value'] ?? '') === 'Apply', 'btn_apply should use intentional mapping');
$assert(($reviewRowByKey['mfg.cov.btn_apply']['suggestion_ready'] ?? false) === true, 'mapped btn_apply should be ready');
$assert(($reviewRowByKey['mfg.cov.btn_register']['suggested_english_value'] ?? '') === 'Register', 'btn_register should use intentional mapping');
$assert(($reviewRowByKey['mfg.cov.mode_qc']['suggested_english_value'] ?? '') === 'QC', 'mode_qc should use intentional acronym mapping');
$assert(($reviewRowByKey['mfg.cov.mode_read_only']['suggested_english_value'] ?? '') === 'Read Only', 'mode_read_only should use intentional mode mapping');
$assert(($reviewRowByKey['mfg.cov.recover_module_desc']['suggestion_ready'] ?? true) === false, 'identifier-derived description should not be ready without canonical value');
$assert(($reviewRowByKey['mfg.cov.recover_module_desc']['suggestion_rejection_reason'] ?? '') !== '', 'identifier-derived description should explain demotion');
$assert(($reviewRowByKey['mfg.cov.noisy.data_row']['suggested_english_value'] ?? 'unexpected') === '', 'noisy/internal key tail should not create suggested English value');
$assert(($reviewRowByKey['mfg.cov.noisy.data_row']['suggestion_confidence'] ?? '') === 'none', 'noisy/internal key tail should have no confidence');
$assert(($reviewRowByKey['mfg.cov.noisy.data_row']['suggestion_ready'] ?? true) === false, 'noisy/internal key tail should not be ready');
$assert(str_contains($reviewTsv, "Group\tKey\tFirst file\tFirst line\tUsage count\tSuggested English value\tConfidence\tAction"), 'copy plan TSV should include expected columns');
$assert(str_contains($reviewTsv, "mfg.cov.kpi\tmfg.cov.kpi.open_orders"), 'copy plan TSV should include grouped KPI key');
$assert(str_contains($reviewTsv, 'Open in Localization Studio'), 'copy plan TSV should include handoff action label');
$assert(!preg_match('/\b(Write|Create all|Generate translations)\b/i', $reviewTsv), 'review plan TSV should not contain write/apply actions');

$baseCanonicalResult = LocalizationScanExtractionScanner::scanFixture(
    'Plugin/Base',
    [],
    [
        'plugins/Base/Views/admin/fixture.php' => <<<'PHP'
<?= t('base.db_control.btn_apply') ?>
<?= t('base.module_detail.recover_module_desc') ?>
PHP,
    ]
);
$baseRows = (array)($baseCanonicalResult['missing_key_review_plan']['rows'] ?? []);
$baseRowsByKey = [];
foreach ($baseRows as $row) {
    $baseRowsByKey[(string)($row['key'] ?? '')] = $row;
}
$assert(($baseRowsByKey['base.db_control.btn_apply']['suggested_english_value'] ?? '') === 'Apply Selected Sync', 'Base btn_apply should prefer canonical app locale value over Apply');
$assert(($baseRowsByKey['base.db_control.btn_apply']['suggestion_ready'] ?? false) === true, 'canonical Base btn_apply should be ready');
$assert(($baseRowsByKey['base.module_detail.recover_module_desc']['suggested_english_value'] ?? '') === 'Stronger recovery path. Rebuilds installed runtime registration without purging business data.', 'Base recover_module_desc should prefer canonical app locale value over generated placeholder');
$assert(($baseRowsByKey['base.module_detail.recover_module_desc']['suggestion_ready'] ?? false) === true, 'canonical Base recover_module_desc should be ready');

$humanMatches = $findByDetected('Coverage Report', 'human_facing_candidate');
$humanFinding = $humanMatches[0] ?? [];
$assert(($humanFinding['action_kind'] ?? '') === 'view_details', 'human-facing candidates should remain view-details only');
$assert(!isset($humanFinding['handoff_url']), 'human-facing candidates should not expose handoff apply URL');

foreach ([' data-row="coverage" data-required-date="', ' data-order-date="', 'class="card" id="coverage-filter" href="/apps/manufacturing/coverage"', '0 AND COALESCE(shortage_qty, 0) > 0', 'POST', 'created_at', 'text/html'] as $text) {
    $assert(!$hasHuman($text), "internal fragment should not be human-facing: {$text}");
}

$unused = $findByDetected('coverage.unused', 'possibly_unused_key');
$assert(count($unused) === 1, 'unused locale key should produce one possibly-unused finding');
$assert(!$hasCategory('coverage.used', 'possibly_unused_key'), 'used locale key should not be possibly unused');

foreach ($findings as $finding) {
    if (($finding['category'] ?? '') !== 'human_facing_candidate') {
        $assert(($finding['suggested_key'] ?? '') === '', 'non-human finding should not have suggested key: ' . ($finding['detected'] ?? ''));
    }
    if (($finding['category'] ?? '') !== 'missing_owner_key') {
        $assert(!isset($finding['suggested_english_value']), 'suggested English values should only be attached to missing keys: ' . ($finding['detected'] ?? ''));
    }
    if (($finding['type'] ?? '') === 'inline_text') {
        $assert(isset($finding['semantic_category']), 'inline text finding should have semantic_category: ' . ($finding['detected'] ?? ''));
        $assert(isset($finding['element_type']), 'inline text finding should have element_type: ' . ($finding['detected'] ?? ''));
        $assert(isset($finding['relevance']), 'inline text finding should have relevance: ' . ($finding['detected'] ?? ''));
        $assert(in_array($finding['relevance'], ['high', 'medium', 'low'], true), 'relevance should be high/medium/low: ' . ($finding['detected'] ?? ''));
        $assert(isset($finding['occurrence_count']), 'inline text finding should have occurrence_count: ' . ($finding['detected'] ?? ''));
        $assert(isset($finding['occurrence_files']), 'inline text finding should have occurrence_files: ' . ($finding['detected'] ?? ''));
        $assert(is_array($finding['occurrence_files']), 'occurrence_files should be an array: ' . ($finding['detected'] ?? ''));
        $assert((int)($finding['occurrence_count'] ?? 0) >= 1, 'occurrence_count should be >= 1: ' . ($finding['detected'] ?? ''));
    }
}

$humanCoverageReport = $findByDetected('Coverage Report', 'human_facing_candidate');
$assert(count($humanCoverageReport) > 0, 'Coverage Report should exist');
$assert(($humanCoverageReport[0]['semantic_category'] ?? '') === 'heading', 'Coverage Report should be classified as heading semantic category');
$assert(($humanCoverageReport[0]['element_type'] ?? '') === 'heading', 'Coverage Report should have heading element type');
$assert(($humanCoverageReport[0]['relevance'] ?? '') === 'high', 'Coverage Report should have high relevance');

$humanOpenExport = $findByDetected('Open Export', 'human_facing_candidate');
$assert(count($humanOpenExport) > 0, 'Open Export should exist');
$assert(($humanOpenExport[0]['semantic_category'] ?? '') === 'action', 'Open Export should be classified as action semantic category');

$humanCoverageWindow = $findByDetected('Coverage Window Filter', 'human_facing_candidate');
$assert(count($humanCoverageWindow) > 0, 'Coverage Window Filter should exist');
$assert(($humanCoverageWindow[0]['element_type'] ?? '') === 'input', 'Coverage Window Filter should have input element type');

$assert(!$hasHuman('?key=value'), 'query string should be filtered as false positive');
$assert(!$hasHuman('{{variable}}'), 'template expression should be filtered as false positive');

$actualSummary = [
    'human_facing_candidates' => 0,
    'already_localized_usages' => 0,
    'missing_owner_keys' => 0,
    'external_shared_key_usages' => 0,
    'possibly_unused_keys' => 0,
    'internal_strings' => 0,
    'ambiguous_strings' => 0,
    'ignored_strings' => 0,
];
foreach ($findings as $finding) {
    $category = $finding['category'] ?? '';
    if ($category === 'human_facing_candidate') $actualSummary['human_facing_candidates']++;
    if ($category === 'already_localized_usage') $actualSummary['already_localized_usages']++;
    if ($category === 'missing_owner_key') $actualSummary['missing_owner_keys']++;
    if ($category === 'external_shared_key_usage') $actualSummary['external_shared_key_usages']++;
    if ($category === 'possibly_unused_key') $actualSummary['possibly_unused_keys']++;
    if ($category === 'internal_string') $actualSummary['internal_strings']++;
    if ($category === 'ambiguous_string') $actualSummary['ambiguous_strings']++;
}

foreach ($actualSummary as $key => $count) {
    $assert((int)($result['summary'][$key] ?? -1) === $count, "summary count should match findings for {$key}");
}

$safeReturn = '/apps/studio/tools/localization-scan-extraction?owner=Manufacturing%2FCoverage&scope=views&locale=en';
$assert(LocalizationScanExtractionScanner::safeInternalReturnTo($safeReturn) === $safeReturn, 'safe internal scan return_to should be accepted');
$assert(LocalizationScanExtractionScanner::safeInternalReturnTo('https://example.com/bad') === '/apps/studio/tools/localization-scan-extraction', 'absolute return_to should be rejected');
$assert(LocalizationScanExtractionScanner::safeInternalReturnTo('//example.com/bad') === '/apps/studio/tools/localization-scan-extraction', 'protocol-relative return_to should be rejected');
$assert(LocalizationScanExtractionScanner::safeInternalReturnTo("/apps/studio/tools/localization-scan-extraction\nbad") === '/apps/studio/tools/localization-scan-extraction', 'control-character return_to should be rejected');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Localization Scan & Extraction scanner fixture tests passed.\n";

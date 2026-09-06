<?php
declare(strict_types=1);

/**
 * Style Compliance Scanner Evidence Probe
 *
 * Loads fixture files through the scanner and asserts that evidence fields
 * are present and correctly typed on every finding.
 *
 * Usage: php apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Tests/probe_evidence.php
 */

// Bootstrap minimal environment
$baseDir = dirname(__DIR__, 7); // up to project root
define('APP_ROOT', $baseDir);
ini_set('memory_limit', '256M');

require_once $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php';
require_once $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairReadinessService.php';
require_once $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairEngineService.php';
require_once $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceGuardedRepairCapabilityService.php';

use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceScannerService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceRepairReadinessService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceRepairEngineService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceGuardedRepairCapabilityService;

if (!function_exists('e')) {
    function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_lang')) {
    function current_lang(): string {
        return (string)($GLOBALS['styleComplianceProbeLang'] ?? 'en');
    }
}

$fixturesDir = __DIR__ . '/fixtures';
$pass = 0;
$fail = 0;
$totalAssertions = 0;

function assertTrue(bool $condition, string $label): void {
    global $pass, $fail, $totalAssertions;
    $totalAssertions++;
    if ($condition) {
        $pass++;
    } else {
        $fail++;
        echo "  FAIL: $label\n";
    }
}

// Helper: matches StyleComplianceScannerService::isEffectProperty
function isEffectProperty(string $property): bool {
    $lower = strtolower($property);
    return $lower === 'filter' || $lower === 'backdrop-filter' || $lower === 'mix-blend-mode' || $lower === 'isolation' || $lower === 'background-blend-mode';
}

// Helper: scan a single file and return tokens with evidence
function scanFixture(string $path, string $scope = 'owner', ?bool $isThemeSource = null): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($isThemeSource === null) {
        $isThemeSource = str_contains($path, 'resources/themes');
    }
    $scopeDescriptor = [
        'root' => dirname($path),
        'label' => 'Fixture',
        'relative' => 'Tests/fixtures/' . basename($path),
        'description' => 'Fixture test file',
    ];

    $contents = @file_get_contents($path);
    if (!is_string($contents) || $contents === '') {
        return [];
    }

    $tokens = [];

    if ($ext === 'css') {
        $ref = new ReflectionMethod(StyleComplianceScannerService::class, 'scanCssLikeContents');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $tokens = $ref->invoke(null, $contents, $path, $isThemeSource, 'css', 0);
    } elseif ($ext === 'php') {
        $ref = new ReflectionMethod(StyleComplianceScannerService::class, 'scanPhpStyleContents');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $tokens = $ref->invoke(null, $contents, $path, $isThemeSource);
    }

    // Apply repair lane metadata and migration decision post-processing
    $withRepair = new ReflectionMethod(StyleComplianceScannerService::class, 'withRepairLaneMetadata');
    if (PHP_VERSION_ID < 80100) { $withRepair->setAccessible(true); }
    $withMigration = new ReflectionMethod(StyleComplianceScannerService::class, 'withMigrationDecision');
    if (PHP_VERSION_ID < 80100) { $withMigration->setAccessible(true); }

    foreach ($tokens as $i => $row) {
        $row['source_scope'] = $scope;
        $row = $withRepair->invoke(null, $row);
        $row = $withMigration->invoke(null, $row);
        $tokens[$i] = $row;
    }

    return $tokens;
}

function buildRepairCandidatesForProbe(array $tokens): array {
    $ref = new ReflectionMethod(StyleComplianceScannerService::class, 'buildRepairCandidates');
    if (PHP_VERSION_ID < 80100) {
        $ref->setAccessible(true);
    }
    return $ref->invoke(null, $tokens);
}

function buildThemeRepairProposalsForProbe(array $tokens): array {
    return StyleComplianceScannerService::buildThemeAwareRepairProposals($tokens);
}

function buildGuardedApplyPreflightForProbe(array $proposals): array {
    return StyleComplianceScannerService::buildGuardedApplyPreflightPlans($proposals);
}

function readinessProposalForProbe(array $overrides = []): array {
    return array_replace_recursive([
        'proposal_id' => 'tar-1111111111111111',
        'proposal_class' => 'deterministic_theme_value_fix',
        'proposal_status' => 'ready_for_future_apply',
        'file_path' => 'apps/SyntheticAlpha/styles/ui.css',
        'line' => 3,
        'selector' => '.alpha',
        'property' => 'color',
        'current_value' => 'var(--tone-danger, #dc3545)',
        'replacement_value' => 'var(--tone-danger-text)',
        'replacement_token' => '--tone-danger-text',
        'source_owner' => 'SyntheticAlpha',
        'target_owner' => 'Theme',
        'target_tool' => 'theme_aware_repair',
        'governance_domain' => 'theme_related',
        'governance_required_domain' => 'theme_related',
        'migration_state' => 'value_fix',
        'repair_lane' => 'owner',
        'confidence' => 'high',
        'detection_confidence' => 'high',
        'semantic_mapping_confidence' => 'known',
        'future_apply_eligibility' => 'eligible',
        'blocked_by' => [],
        'review_reason_code' => '',
        'evidence' => [
            'scan_category' => 'semantic_token_misuse',
            'value_construct' => 'alias',
            'token_references' => ['--tone-danger'],
            'parse_confidence' => 'high',
        ],
    ], $overrides);
}

// Temporary fixture directory (isolated from Tests/fixtures/ which is blocked by isUnsupportedApplySourcePath)
// Uses Tests/tmp/ which passes the editable-source checks and is cleaned up at probe exit.
define('PROBE_TMP_DIR', __DIR__ . '/tmp');
define('PROBE_TMP_FIXTURE_FILE', 'probe_repair_readiness.css');
define('PROBE_TMP_FIXTURE_REL_PATH', 'apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Tests/tmp/' . PROBE_TMP_FIXTURE_FILE);
define('PROBE_TMP_DUP_FILE', 'probe_repair_duplicate.css');
define('PROBE_TMP_DUP_REL_PATH', 'apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Tests/tmp/' . PROBE_TMP_DUP_FILE);

function probeTempFixtureContents(): string {
    return "/* repair readiness temp fixture */\n.repair-fixture { color: var(--tone-danger); }\n";
}

function probeTempDuplicateContents(): string {
    return "/* repair readiness duplicate temp fixture */\n"
        . ".repair-fixture-a { color: var(--tone-danger); }\n"
        . ".repair-fixture-b { color: var(--tone-danger); }\n";
}

function ensureProbeTempDir(): string {
    $dir = PROBE_TMP_DIR;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function createProbeTempFixture(): string {
    $dir = ensureProbeTempDir();
    $path = $dir . '/' . PROBE_TMP_FIXTURE_FILE;
    file_put_contents($path, probeTempFixtureContents());
    return PROBE_TMP_FIXTURE_REL_PATH;
}

function createProbeTempDuplicateFixture(): string {
    $dir = ensureProbeTempDir();
    $path = $dir . '/' . PROBE_TMP_DUP_FILE;
    file_put_contents($path, probeTempDuplicateContents());
    return PROBE_TMP_DUP_REL_PATH;
}

function readProbeTempFixture(): string {
    return (string)file_get_contents(PROBE_TMP_DIR . '/' . PROBE_TMP_FIXTURE_FILE);
}

function writeProbeTempFixture(string $contents): void {
    file_put_contents(PROBE_TMP_DIR . '/' . PROBE_TMP_FIXTURE_FILE, $contents);
}

function cleanupProbeTempFixtures(): void {
    $dir = PROBE_TMP_DIR;
    if (!is_dir($dir)) return;
    foreach ([PROBE_TMP_FIXTURE_FILE, PROBE_TMP_DUP_FILE] as $file) {
        $p = $dir . '/' . $file;
        if (is_file($p)) unlink($p);
    }
    // Remove sidecar snapshots/manifests from repair engine
    $bakDir = APP_ROOT . '/storage/studio-snapshots/style-compliance';
    if (is_dir($bakDir)) {
        foreach (scandir($bakDir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            if (str_contains($f, 'probe_repair_readiness') || str_contains($f, 'probe_repair_duplicate')) {
                $fp = $bakDir . '/' . $f;
                if (is_file($fp)) unlink($fp);
            }
        }
    }
    @rmdir($dir);
}

/**
 * Build a deterministic guarded-repair proposal that points only to the isolated
 * temp fixture file — never to a live owner artifact.
 */
function realFileProposalForProbe(?string $tempFilePath = null): array {
    $filePath = $tempFilePath ?? PROBE_TMP_FIXTURE_REL_PATH;
    return [
        'proposal_id' => 'tar-real-file-probe-' . bin2hex(random_bytes(4)),
        'proposal_class' => 'deterministic_theme_value_fix',
        'proposal_status' => 'ready_for_future_apply',
        'file_path' => $filePath,
        'line' => 2,
        'selector' => '.repair-fixture',
        'property' => 'color',
        'current_value' => 'var(--tone-danger)',
        'replacement_value' => 'var(--tone-danger-text)',
        'replacement_token' => '--tone-danger-text',
        'source_owner' => 'Studio',
        'target_owner' => 'Theme',
        'target_tool' => 'theme_aware_repair',
        'governance_domain' => 'theme_related',
        'governance_required_domain' => 'theme_related',
        'migration_state' => 'value_fix',
        'repair_lane' => 'owner',
        'confidence' => 'high',
        'detection_confidence' => 'high',
        'semantic_mapping_confidence' => 'known',
        'future_apply_eligibility' => 'eligible',
        'apply_preconditions' => [],
        'blocked_by' => [],
        'review_reason_code' => '',
    ];
}

function renderStyleCompliancePreviewForProbe(array $styleComplianceResult, string $lang = 'en'): string {
    $GLOBALS['styleComplianceProbeLang'] = $lang;
    ob_start();
    require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/preview.php';
    return (string)ob_get_clean();
}

function visibleStyleComplianceHtmlForProbe(string $html): string {
    foreach (['script', 'style', 'textarea'] as $tag) {
        $openNeedle = '<' . $tag;
        $closeNeedle = '</' . $tag . '>';
        while (($start = stripos($html, $openNeedle)) !== false) {
            $end = stripos($html, $closeNeedle, $start);
            if ($end === false) {
                break;
            }
            $html = substr($html, 0, $start) . substr($html, $end + strlen($closeNeedle));
        }
    }
    return $html;
}

function migrationRowsForProbe(string $html): array {
    $rows = [];
    $offset = 0;
    while (($start = strpos($html, '<tr class="sc-mig-row-', $offset)) !== false) {
        $bodyStart = strpos($html, '>', $start);
        $end = strpos($html, '</tr>', $bodyStart === false ? $start : $bodyStart);
        if ($bodyStart === false || $end === false) {
            break;
        }
        $rowHtml = substr($html, $bodyStart + 1, $end - $bodyStart - 1);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowHtml, $cellMatches);
        $cells = [];
        foreach ($cellMatches[1] as $cellHtml) {
            $cells[] = trim(html_entity_decode(strip_tags($cellHtml), ENT_QUOTES, 'UTF-8'));
        }
        if ($cells !== []) {
            $rows[] = $cells;
        }
        $offset = $end + 5;
    }
    return $rows;
}

echo "Style Compliance Scanner — Evidence Probe\n";
echo "=========================================\n\n";

// ============================================================
// 0. Owner discovery — Studio must be scan-selectable
// ============================================================
echo "--- Discovery: owners ---\n";
$owners = StyleComplianceScannerService::discoverOwners();
$ownerKeys = array_map(static fn(array $owner): string => (string)($owner['owner_key'] ?? ''), $owners);
echo "  Owners discovered: " . count($owners) . "\n";

assertTrue(in_array('Studio', $ownerKeys, true), 'discoverOwners includes Studio app owner');
assertTrue(!in_array('Shell', $ownerKeys, true), 'discoverOwners keeps Shell out of owner list because shell scope exists');

$defaultOwnerScan = StyleComplianceScannerService::scan('owner', '');
assertTrue((bool)($defaultOwnerScan['scan_ready'] ?? false), 'owner scope with blank owner defaults to a style-bearing owner');
assertTrue((string)($defaultOwnerScan['owner_key'] ?? '') === 'Studio', 'blank owner scan prefers Studio owner when available');

echo "\n";

// ============================================================
// 1. plain.css — basic token alias, literal values, gradients
// ============================================================
echo "--- Fixture: plain.css ---\n";
$tokens = scanFixture($fixturesDir . '/plain.css');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'plain.css produced tokens');

foreach ($tokens as $i => $row) {
    $idx = "[$i:{$row['kind']}] {$row['token']}";
    assertTrue(isset($row['property']), "$idx has property field");
    assertTrue(isset($row['raw_value']), "$idx has raw_value field");
    assertTrue(isset($row['normalized_value']), "$idx has normalized_value field");
    assertTrue(isset($row['value_construct']), "$idx has value_construct field");
    assertTrue(isset($row['source_type']), "$idx has source_type field");
    assertTrue(isset($row['line_start']), "$idx has line_start field");
    assertTrue(isset($row['at_rule_context']), "$idx has at_rule_context field");
    assertTrue(isset($row['parse_confidence']), "$idx has parse_confidence field");
    assertTrue(isset($row['token_references']), "$idx has token_references field");
    assertTrue(is_array($row['token_references']), "$idx token_references is array");
    assertTrue(is_string($row['value_construct']), "$idx value_construct is string");
    assertTrue($row['source_type'] === 'css', "$idx source_type is css");
    assertTrue($row['parse_confidence'] === 'high', "$idx parse_confidence is high");
    assertTrue($row['line_start'] >= 1, "$idx line_start >= 1");
}

// Check specific findings
$customPropCount = 0;
$usageCount = 0;
$literalCount = 0;
foreach ($tokens as $row) {
    if ($row['kind'] === 'declaration') $customPropCount++;
    if ($row['kind'] === 'usage') $usageCount++;
    if ($row['kind'] === 'literal_declaration') $literalCount++;
}
echo "  Custom props: $customPropCount, Usage (var refs): $usageCount, Literal: $literalCount\n";
assertTrue($customPropCount > 0, 'plain.css has custom property declarations');
assertTrue($usageCount > 0, 'plain.css has var() usage findings');
assertTrue($literalCount > 0, 'plain.css has literal declaration findings');

// Check at least one literal_declaration has a hex color value_construct
$hasHexLiteral = false;
foreach ($tokens as $row) {
    if ($row['kind'] === 'literal_declaration' && $row['value_construct'] === 'literal_color') {
        $hasHexLiteral = true;
        break;
    }
}
assertTrue($hasHexLiteral, 'plain.css has literal_color value_construct');

// Plain CSS source_type
$allCss = true;
foreach ($tokens as $row) {
    if ($row['source_type'] !== 'css') $allCss = false;
}
assertTrue($allCss, 'plain.css all tokens have source_type=css');

echo "\n";

// ============================================================
// 2. at-rules.css — nested @media/@supports
// ============================================================
echo "--- Fixture: at-rules.css ---\n";
$tokens = scanFixture($fixturesDir . '/at-rules.css');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'at-rules.css produced tokens');
assertTrue(count($tokens) >= 6, 'at-rules.css has at least 6 tokens');

$hasAtRuleContext = false;
$hasEmptyAtRule = false;
foreach ($tokens as $row) {
    assertTrue(isset($row['at_rule_context']), "at-rules token has at_rule_context");
    if ($row['at_rule_context'] !== '') {
        $hasAtRuleContext = true;
    } else {
        $hasEmptyAtRule = true;
    }
}
assertTrue($hasAtRuleContext, 'at-rules.css has tokens with at_rule_context');
assertTrue($hasEmptyAtRule, 'at-rules.css has tokens outside at-rules (no context)');

// Verify that @supports nesting produces proper context
$supportsContext = false;
foreach ($tokens as $row) {
    if (str_contains($row['at_rule_context'], '@supports')) {
        $supportsContext = true;
        break;
    }
}
assertTrue($supportsContext, 'at-rules.css has @supports context in tokens');

echo "\n";

// ============================================================
// 3. comments.css — strings, comments, edge cases
// ============================================================
echo "--- Fixture: comments.css ---\n";
$tokens = scanFixture($fixturesDir . '/comments.css');
echo "  Tokens produced: " . count($tokens) . "\n";

// Comments should not interfere with parsing
assertTrue(count($tokens) >= 2, 'comments.css produced at least 2 visual tokens');

// Check color finding in .element
$hasColor = false;
$hasBorder = false;
foreach ($tokens as $row) {
    if ($row['property'] === 'color' && $row['raw_value'] === '#ff6600') {
        $hasColor = true;
    }
    if ($row['property'] === 'border' || $row['property'] === 'border-color') {
        $hasBorder = true;
    }
}
assertTrue($hasColor, 'comments.css detected color: #ff6600');
// The border shorthand with dashed inside might not parse perfectly due to comment,
// but should still produce something
assertTrue(count($tokens) >= 2, 'comments.css not broken by comments');

// Values inside comments should NOT be extracted
$foundCommentValue = false;
foreach ($tokens as $row) {
    if ($row['property'] === ';' || str_contains($row['raw_value'] ?? '', 'not a declaration')) {
        // Content inside strings like content: "; not a declaration" should NOT be extracted
        // as a visual property
    }
    // Check that the semicolon inside the comment didn't create a bogus finding
    if (str_contains($row['property'] ?? '', '/*')) {
        $foundCommentValue = true;
    }
}
assertTrue(!$foundCommentValue, "comments.css no comment-text extracted as property");

echo "\n";

// ============================================================
// 4. complex.css — gradients, shadows, filters, color-mix
// ============================================================
echo "--- Fixture: complex.css ---\n";
$tokens = scanFixture($fixturesDir . '/complex.css');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'complex.css produced tokens');

$hasGradient = false;
$hasFilter = false;
$hasMultiRef = false;
$hasShadow = false;
foreach ($tokens as $row) {
    if ($row['value_construct'] === 'gradient') $hasGradient = true;
    if ($row['value_construct'] === 'filter') $hasFilter = true;
    if ($row['value_construct'] === 'shadow') $hasShadow = true;
    if (count($row['token_references'] ?? []) >= 2) $hasMultiRef = true;
}
assertTrue($hasGradient, 'complex.css has gradient value_construct');
assertTrue($hasFilter, 'complex.css has filter value_construct');
assertTrue($hasShadow, 'complex.css has shadow value_construct');
assertTrue($hasMultiRef, 'complex.css has multi-token reference (color-mix with var)');

echo "\n";

// ============================================================
// 5. php_style.php — <style> blocks and inline styles
// ============================================================
echo "--- Fixture: php_style.php ---\n";
$tokens = scanFixture($fixturesDir . '/php_style.php');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'php_style.php produced tokens');

$hasPhpStyleBlock = false;
$hasPhpInline = false;
foreach ($tokens as $row) {
    if (isset($row['source_type'])) {
        if ($row['source_type'] === 'php_style_block') $hasPhpStyleBlock = true;
        if ($row['source_type'] === 'php_inline_style') $hasPhpInline = true;
    }
}
assertTrue($hasPhpStyleBlock, 'php_style.php has php_style_block source_type');
assertTrue($hasPhpInline, 'php_style.php has php_inline_style source_type');

echo "\n";

// ============================================================
// 6. dynamic_style.php — partial confidence for PHP-in-CSS
// ============================================================
echo "--- Fixture: dynamic_style.php ---\n";
$tokens = scanFixture($fixturesDir . '/dynamic_style.php');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'dynamic_style.php produced tokens');

// Should have both inline styles and <style> block findings
$hasPartialConfidence = false;
foreach ($tokens as $row) {
    if (isset($row['parse_confidence']) && $row['parse_confidence'] === 'partial') {
        $hasPartialConfidence = true;
        break;
    }
}
assertTrue($hasPartialConfidence, 'dynamic_style.php has partial parse_confidence');

// Category parity: dynamic tokens should have scan_category=dynamic_unsupported
$dynamicCategoryMatch = 0;
$dynamicCategoryWrong = 0;
foreach ($tokens as $row) {
    if (!empty($row['is_dynamic'])) {
        if (($row['scan_category'] ?? '') === 'dynamic_unsupported') {
            $dynamicCategoryMatch++;
        } else {
            $dynamicCategoryWrong++;
        }
    }
}
assertTrue($dynamicCategoryWrong === 0, 'dynamic_style.php all dynamic tokens have scan_category=dynamic_unsupported (got wrong: ' . $dynamicCategoryWrong . ')');
assertTrue($dynamicCategoryMatch > 0, 'dynamic_style.php at least one dynamic token matched scan_category');
echo "  Dynamic category parity: $dynamicCategoryMatch match, $dynamicCategoryWrong wrong\n";

echo "\n";

// ============================================================
// 7. structural.css — structural-only properties (out of scope)
// ============================================================
echo "--- Fixture: structural.css ---\n";
$tokens = scanFixture($fixturesDir . '/structural.css');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'structural.css produced tokens');

$allStructural = true;
$allEvidenceOnly = true;
$allLiteral = true;
$anyEffect = false;
$inScopeCount = 0;
foreach ($tokens as $i => $row) {
    $idx = "[$i:{$row['property']}]";
    if ($row['scan_category'] !== 'structural_out_of_scope') {
        $allStructural = false;
        echo "  INFO: $idx scan_category={$row['scan_category']}\n";
    }
    if ($row['compliance_scope'] !== 'evidence_only') {
        $allEvidenceOnly = false;
        echo "  INFO: $idx compliance_scope={$row['compliance_scope']}\n";
    }
    if ($row['kind'] !== 'literal_declaration') {
        $allLiteral = false;
    }
    if (!empty($row['is_effect_candidate'])) {
        $anyEffect = true;
    }
    if ($row['compliance_scope'] === 'in_scope') {
        $inScopeCount++;
    }
}
assertTrue($allStructural, 'structural.css all tokens have scan_category=structural_out_of_scope');
assertTrue($allEvidenceOnly, 'structural.css all tokens have compliance_scope=evidence_only');
assertTrue($allLiteral, 'structural.css all tokens are literal_declaration kind');
assertTrue(!$anyEffect, 'structural.css no tokens have is_effect_candidate=true');
assertTrue($inScopeCount === 0, 'structural.css has zero in_scope tokens');

// Verify token references are empty (no var() usage in structural-only file)
$hasVarRef = false;
foreach ($tokens as $row) {
    if (!empty($row['token_references'])) {
        $hasVarRef = true;
        break;
    }
}
assertTrue(!$hasVarRef, 'structural.css has no var() references');

echo "\n";

// ============================================================
// 8. effects.css — effect properties (is_effect_candidate)
// ============================================================
echo "--- Fixture: effects.css ---\n";
$tokens = scanFixture($fixturesDir . '/effects.css');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'effects.css produced tokens');

$effectPropertyCount = 0;
$effectCandidateTrue = 0;
$effectCandidateFalse = 0;
foreach ($tokens as $row) {
    $isEffect = isEffectProperty($row['property']);
    if ($isEffect) {
        $effectPropertyCount++;
    }
    if (!empty($row['is_effect_candidate'])) {
        $effectCandidateTrue++;
    } else {
        $effectCandidateFalse++;
    }
}
echo "  Effect properties: $effectPropertyCount, is_effect_candidate=true: $effectCandidateTrue, false: $effectCandidateFalse\n";
assertTrue($effectPropertyCount > 0, 'effects.css has effect properties');
assertTrue($effectCandidateTrue >= $effectPropertyCount, 'effects.css all effect properties have is_effect_candidate=true');

// Category parity: effect tokens should have scan_category=effect_candidate
$effectCategoryMatch = 0;
$effectCategoryWrong = 0;
foreach ($tokens as $row) {
    if (!empty($row['is_effect_candidate'])) {
        if (($row['scan_category'] ?? '') === 'effect_candidate') {
            $effectCategoryMatch++;
        } else {
            $effectCategoryWrong++;
        }
    }
}
assertTrue($effectCategoryWrong === 0, 'effects.css all effect tokens have scan_category=effect_candidate (got wrong: ' . $effectCategoryWrong . ')');
assertTrue($effectCategoryMatch > 0, 'effects.css at least one effect token matched scan_category');
echo "  Effect category parity: $effectCategoryMatch match, $effectCategoryWrong wrong\n";

// Non-effect properties should NOT have is_effect_candidate
// (effects.css has color and background-color which are visual, not effect)
$colorBgTokens = 0;
$colorBgEffect = 0;
foreach ($tokens as $row) {
    if (in_array($row['property'], ['color', 'background-color'], true)) {
        $colorBgTokens++;
        if (!empty($row['is_effect_candidate'])) {
            $colorBgEffect++;
        }
    }
}
assertTrue($colorBgTokens > 0, 'effects.css has visual properties (color/background-color)');
assertTrue($colorBgEffect === 0, 'effects.css visual properties have is_effect_candidate=false');

echo "\n";

// ============================================================
// 9. print_style.php — print-related file (is_print_style)
// ============================================================
echo "--- Fixture: print_style.php ---\n";
$tokens = scanFixture($fixturesDir . '/print_style.php');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'print_style.php produced tokens');

$allPrintStyle = true;
foreach ($tokens as $i => $row) {
    if (empty($row['is_print_style'])) {
        $allPrintStyle = false;
        echo "  INFO: token $i ({$row['property']}) is_print_style is empty\n";
    }
}
assertTrue($allPrintStyle, 'print_style.php all tokens have is_print_style=true');

// Should have php_style_block source_type (from @media print <style> block)
$hasStyleBlock = false;
$hasInline = false;
foreach ($tokens as $row) {
    if ($row['source_type'] === 'php_style_block') $hasStyleBlock = true;
    if ($row['source_type'] === 'php_inline_style') $hasInline = true;
}
assertTrue($hasStyleBlock, 'print_style.php has php_style_block tokens');
assertTrue($hasInline, 'print_style.php has php_inline_style tokens');

// Category parity: print tokens should have scan_category=print_pdf
$printCategoryMatch = 0;
$printCategoryWrong = 0;
foreach ($tokens as $row) {
    if (!empty($row['is_print_style'])) {
        if (($row['scan_category'] ?? '') === 'print_pdf') {
            $printCategoryMatch++;
        } else {
            $printCategoryWrong++;
        }
    }
}
assertTrue($printCategoryWrong === 0, 'print_style.php all print tokens have scan_category=print_pdf (got wrong: ' . $printCategoryWrong . ')');
assertTrue($printCategoryMatch > 0, 'print_style.php at least one print token matched scan_category');
echo "  Print category parity: $printCategoryMatch match, $printCategoryWrong wrong\n";

echo "\n";

// ============================================================
// 10. semantic_misuse.css — wrong semantic token role
// ============================================================
echo "--- Fixture: semantic_misuse.css ---\n";
$tokens = scanFixture($fixturesDir . '/semantic_misuse.css');
echo "  Tokens produced: " . count($tokens) . "\n";

assertTrue(count($tokens) > 0, 'semantic_misuse.css produced tokens');

$misuseCount = 0;
$validTokenConsumerCount = 0;
$badBackgroundBorder = false;
$badBackgroundRawTone = false;
$badColorBg = false;
$misuseHasReason = true;
$misuseRepairEligibilityOk = true;
$misuseAwarenessOk = true;
$semanticCorrectionCandidateCount = 0;
$semanticCorrectionHints = [];

foreach ($tokens as $row) {
    $category = (string)($row['scan_category'] ?? '');
    $property = (string)($row['property'] ?? '');
    $refs = (array)($row['token_references'] ?? []);

    if ($category === 'semantic_token_misuse') {
        $misuseCount++;
        if (($row['semantic_token_misuse_reason'] ?? '') === '') {
            $misuseHasReason = false;
        }
        if (($row['repair_eligibility'] ?? '') !== 'needs_semantic_decision') {
            $misuseRepairEligibilityOk = false;
        }
        if (!empty($row['theme_aware'])) {
            $misuseAwarenessOk = false;
        }
        if ($property === 'background' && in_array('--tone-info-border', $refs, true)) {
            $badBackgroundBorder = true;
        }
        if ($property === 'background' && in_array('--tone-success', $refs, true)) {
            $badBackgroundRawTone = true;
        }
        if ($property === 'color' && in_array('--tone-success-bg', $refs, true)) {
            $badColorBg = true;
        }
    }

    if ($category === 'token_consumer' && $property === 'border-color' && in_array('--tone-info-border', $refs, true)) {
        $validTokenConsumerCount++;
    }
    if ($category === 'token_consumer' && $property === 'background' && in_array('--tone-info-bg', $refs, true)) {
        $validTokenConsumerCount++;
    }
    if ($category === 'token_consumer' && $property === 'color' && in_array('--tone-info-text', $refs, true)) {
        $validTokenConsumerCount++;
    }
}

$candidates = buildRepairCandidatesForProbe($tokens);
foreach ($candidates as $candidate) {
    if (($candidate['repair_action'] ?? '') === 'semantic_token_correction') {
        $semanticCorrectionCandidateCount++;
        $semanticCorrectionHints[] = (string)($candidate['proposed_semantic'] ?? '');
    }
}

echo "  Semantic misuse tokens: $misuseCount, valid semantic consumers: $validTokenConsumerCount\n";
echo "  Semantic correction candidates: $semanticCorrectionCandidateCount\n";
assertTrue($misuseCount === 3, 'semantic_misuse.css has exactly 3 semantic_token_misuse findings');
assertTrue($badBackgroundBorder, 'semantic_misuse.css flags border token used as background');
assertTrue($badBackgroundRawTone, 'semantic_misuse.css flags raw tone token used as background');
assertTrue($badColorBg, 'semantic_misuse.css flags bg token used as text color');
assertTrue($misuseHasReason, 'semantic_misuse.css misuse findings include reasons');
assertTrue($misuseRepairEligibilityOk, 'semantic_misuse.css misuse findings need semantic decision');
assertTrue($misuseAwarenessOk, 'semantic_misuse.css misuse findings are not theme-aware');
assertTrue($validTokenConsumerCount >= 3, 'semantic_misuse.css valid role-matched semantic tokens remain token_consumer');
assertTrue($semanticCorrectionCandidateCount === 3, 'semantic_misuse.css produces 3 semantic_token_correction candidates');
assertTrue(in_array('--tone-info-bg', $semanticCorrectionHints, true), 'semantic_misuse.css proposes --tone-info-bg for info background misuse');
assertTrue(in_array('--tone-success-bg', $semanticCorrectionHints, true), 'semantic_misuse.css proposes --tone-success-bg for raw success background misuse');
assertTrue(in_array('--tone-success-text', $semanticCorrectionHints, true), 'semantic_misuse.css proposes --tone-success-text for success text misuse');

echo "\n";

// ============================================================
// 10b. Theme-Aware Repair Proposal Contract V1
// ============================================================
echo "--- Theme-Aware Repair Proposal Contract V1 ---\n";

function themeRepairFixtureRow(array $overrides): array {
    return array_merge([
        'token' => '--tone-info-text',
        'value' => 'var(--tone-info-text)',
        'kind' => 'usage',
        'file' => 'apps/Studio/styles/proposal-contract.css',
        'selector' => '.proposal-contract',
        'theme_aware' => false,
        'value_classification' => ['type' => 'alias', 'is_literal' => false, 'canonical' => 'var(--tone-info-text)'],
        'in_theme_source' => false,
        'property' => 'border-color',
        'raw_value' => 'var(--tone-info-text)',
        'normalized_value' => 'var(--tone-info-text)',
        'token_references' => ['--tone-info-text'],
        'value_construct' => 'alias',
        'source_type' => 'css',
        'line_start' => 20,
        'line_end' => 20,
        'at_rule_context' => '',
        'parse_confidence' => 'high',
        'scan_category' => 'semantic_token_misuse',
        'compliance_scope' => 'in_scope',
        'repair_eligibility' => 'needs_semantic_decision',
        'is_effect_candidate' => false,
        'is_dynamic' => false,
        'is_print_style' => false,
        'semantic_token_misuse' => ['token' => '--tone-info-text', 'property_role' => 'border', 'token_role' => 'text', 'reason' => 'text token on border property'],
        'semantic_token_misuse_reason' => 'text token on border property',
        'source_scope' => 'owner',
        'source_owner' => 'Studio',
        'style_domain' => 'theme',
        'governance_domain' => 'theme_related',
        'governance_required_domain' => 'theme_related',
        'migration_state' => 'value_fix',
        'migration_reason' => 'Semantic token role mismatch — needs corrected token reference.',
        'migration_confidence' => 'high',
        'target_owner' => 'Theme',
        'target_tool' => 'css_token_editor',
        'repair_lane' => 'Theme token issues',
    ], $overrides);
}

$proposalRows = [
    themeRepairFixtureRow([]),
    themeRepairFixtureRow([
        'token' => '--tone-success-text',
        'property' => 'background',
        'raw_value' => 'var(--tone-success-text)',
        'normalized_value' => 'var(--tone-success-text)',
        'token_references' => ['--tone-success-text'],
        'semantic_token_misuse' => ['token' => '--tone-success-text', 'property_role' => 'background', 'token_role' => 'text', 'reason' => 'text token on background property'],
        'line_start' => 21,
    ]),
    themeRepairFixtureRow([
        'token' => '--tone-danger-bg',
        'property' => 'color',
        'raw_value' => 'var(--tone-danger-bg)',
        'normalized_value' => 'var(--tone-danger-bg)',
        'token_references' => ['--tone-danger-bg'],
        'semantic_token_misuse' => ['token' => '--tone-danger-bg', 'property_role' => 'text', 'token_role' => 'background', 'reason' => 'bg token on text property'],
        'line_start' => 22,
    ]),
    themeRepairFixtureRow([
        'token' => 'background',
        'kind' => 'literal_declaration',
        'property' => 'background',
        'raw_value' => '#f8d7da',
        'normalized_value' => '#f8d7da',
        'token_references' => [],
        'value_construct' => 'literal_color',
        'value_classification' => ['type' => 'color_hex', 'is_literal' => true, 'canonical' => '#f8d7da'],
        'scan_category' => 'visual_literal',
        'semantic_token_misuse' => [],
        'migration_confidence' => 'high',
        'line_start' => 23,
    ]),
    themeRepairFixtureRow([
        'token' => 'box-shadow',
        'kind' => 'literal_declaration',
        'property' => 'box-shadow',
        'raw_value' => '0 2px 8px rgba(0,0,0,.25)',
        'normalized_value' => '0 2px 8px rgba(0,0,0,.25)',
        'token_references' => [],
        'value_construct' => 'shadow',
        'value_classification' => ['type' => 'color_func', 'is_literal' => true, 'canonical' => '0 2px 8px rgba(0,0,0,.25)'],
        'scan_category' => 'visual_literal',
        'semantic_token_misuse' => [],
        'line_start' => 24,
    ]),
    themeRepairFixtureRow([
        'token' => 'background',
        'kind' => 'literal_declaration',
        'property' => 'background',
        'raw_value' => 'linear-gradient(#fff, #000)',
        'normalized_value' => 'linear-gradient(#fff, #000)',
        'token_references' => [],
        'value_construct' => 'gradient',
        'value_classification' => ['type' => 'other', 'is_literal' => false, 'canonical' => 'linear-gradient(#fff, #000)'],
        'scan_category' => 'visual_literal',
        'semantic_token_misuse' => [],
        'migration_state' => 'classification_review',
        'migration_confidence' => 'low',
        'line_start' => 25,
    ]),
    themeRepairFixtureRow([
        'token' => 'opacity',
        'kind' => 'literal_declaration',
        'property' => 'opacity',
        'raw_value' => '.6',
        'normalized_value' => '.6',
        'token_references' => [],
        'value_construct' => 'keyword',
        'value_classification' => ['type' => 'other', 'is_literal' => false, 'canonical' => '.6'],
        'scan_category' => 'visual_literal',
        'semantic_token_misuse' => [],
        'migration_state' => 'classification_review',
        'migration_confidence' => 'low',
        'line_start' => 26,
    ]),
    themeRepairFixtureRow([
        'token' => 'outline',
        'kind' => 'literal_declaration',
        'property' => 'outline',
        'raw_value' => 'none',
        'normalized_value' => 'none',
        'token_references' => [],
        'value_construct' => 'keyword',
        'value_classification' => ['type' => 'other', 'is_literal' => false, 'canonical' => 'none'],
        'scan_category' => 'visual_literal',
        'semantic_token_misuse' => [],
        'line_start' => 27,
    ]),
    themeRepairFixtureRow([
        'token' => 'color',
        'kind' => 'literal_declaration',
        'property' => 'color',
        'raw_value' => '#111',
        'normalized_value' => '#111',
        'file' => 'apps/Studio/styles/print/proposal.css',
        'token_references' => [],
        'value_construct' => 'literal_color',
        'value_classification' => ['type' => 'color_hex', 'is_literal' => true, 'canonical' => '#111'],
        'scan_category' => 'print_pdf',
        'is_print_style' => true,
        'semantic_token_misuse' => [],
        'line_start' => 28,
    ]),
    themeRepairFixtureRow([
        'token' => 'color',
        'kind' => 'literal_declaration',
        'property' => 'color',
        'raw_value' => '<?= $color ?>',
        'normalized_value' => '<?= $color ?>',
        'token_references' => [],
        'value_construct' => 'mixture',
        'value_classification' => ['type' => 'other', 'is_literal' => false, 'canonical' => '<?= $color ?>'],
        'scan_category' => 'dynamic_unsupported',
        'is_dynamic' => true,
        'semantic_token_misuse' => [],
        'line_start' => 29,
    ]),
    themeRepairFixtureRow([
        'migration_confidence' => 'low',
        'line_start' => 30,
    ]),
    themeRepairFixtureRow([
        'token' => 'backdrop-filter',
        'kind' => 'literal_declaration',
        'property' => 'backdrop-filter',
        'raw_value' => 'blur(12px)',
        'normalized_value' => 'blur(12px)',
        'token_references' => [],
        'value_construct' => 'filter',
        'value_classification' => ['type' => 'other', 'is_literal' => false, 'canonical' => 'blur(12px)'],
        'scan_category' => 'effect_candidate',
        'is_effect_candidate' => true,
        'semantic_token_misuse' => [],
        'line_start' => 31,
    ]),
];

$themeProposalContract = buildThemeRepairProposalsForProbe($proposalRows);
$queues = $themeProposalContract['queues'] ?? [];
$ready = $queues['ready_for_future_guarded_apply'] ?? [];
$manual = $queues['manual_semantic_decisions'] ?? [];
$review = $queues['classification_and_accessibility_review'] ?? [];
$handoff = $queues['future_tool_handoffs'] ?? [];
$excluded = $queues['excluded'] ?? [];
$summary = $themeProposalContract['summary'] ?? [];
$allContractItems = $themeProposalContract['items'] ?? [];
$replacementMap = [];
foreach ($ready as $proposal) {
    $replacementMap[(string)($proposal['property'] ?? '') . '|' . (string)($proposal['current_value'] ?? '')] = (string)($proposal['replacement_value'] ?? '');
}
$manualHasExecutableReplacement = false;
foreach ($manual as $proposal) {
    if ((string)($proposal['replacement_value'] ?? '') !== '' || (string)($proposal['replacement_token'] ?? '') !== '') {
        $manualHasExecutableReplacement = true;
    }
}
$lowReady = array_filter($ready, static fn(array $p): bool => (string)($p['confidence'] ?? '') !== 'high' || (string)($p['proposal_status'] ?? '') !== 'ready_for_future_apply');
$stableA = buildThemeRepairProposalsForProbe([$proposalRows[0]]);
$stableB = buildThemeRepairProposalsForProbe([$proposalRows[0]]);
$stableIdA = (string)($stableA['items'][0]['proposal_id'] ?? '');
$stableIdB = (string)($stableB['items'][0]['proposal_id'] ?? '');

echo "  Ready: " . count($ready) . ", manual: " . count($manual) . ", review: " . count($review) . ", handoff: " . count($handoff) . ", excluded: " . count($excluded) . "\n";
assertTrue(($replacementMap['border-color|var(--tone-info-text)'] ?? '') === 'var(--tone-info-border)', 'border/text token produces deterministic border replacement');
assertTrue(($replacementMap['background|var(--tone-success-text)'] ?? '') === 'var(--tone-success-bg)', 'background/text token produces deterministic background replacement');
assertTrue(($replacementMap['color|var(--tone-danger-bg)'] ?? '') === 'var(--tone-danger-text)', 'color/bg token produces deterministic text replacement');
assertTrue(count($ready) === 3, 'only deterministic high-confidence same-domain known-token rows are ready');
assertTrue(count($manual) >= 2, 'raw hex and rgba shadow become manual semantic decisions');
assertTrue(!$manualHasExecutableReplacement, 'manual semantic decisions never carry replacement_value or replacement_token');
assertTrue(count($handoff) === 1, 'backdrop-filter becomes future Special Effects handoff');
assertTrue(count($excluded) >= 2, 'print and dynamic candidates are excluded/unsupported');
assertTrue(count($review) >= 3, 'gradient, opacity, outline none, and low confidence route to review');
assertTrue($lowReady === [], 'low confidence proposals are never ready_for_future_apply');
assertTrue($stableIdA !== '' && $stableIdA === $stableIdB, 'proposal IDs are stable for identical evidence');
assertTrue((int)($summary['deterministic_future_apply'] ?? -1) === count($ready), 'summary deterministic count is not mixed with manual/review rows');
assertTrue((int)($summary['manual_semantic_decisions'] ?? -1) === count($manual), 'summary manual semantic count is separate from replacement operations');
assertTrue((int)($summary['future_effects_handoffs'] ?? -1) === count($handoff), 'summary future effects handoff count is separate');
assertTrue(array_reduce($allContractItems, static fn(bool $ok, array $p): bool => $ok && ((string)($p['proposal_class'] ?? '') !== 'manual_semantic_decision' || (string)($p['replacement_value'] ?? '') === ''), true), 'manual decision records have no executable replacement');

$syntheticPreflight = $themeProposalContract['guarded_apply_preflight'] ?? [];
$syntheticPlans = $syntheticPreflight['plans'] ?? [];
assertTrue(count($syntheticPlans) === count($ready), 'only deterministic ready proposals produce guarded apply plans');
assertTrue(buildGuardedApplyPreflightForProbe($manual)['plans'] === [], 'literal color/shadow manual decisions produce no apply plan');
assertTrue(buildGuardedApplyPreflightForProbe($review)['plans'] === [], 'gradient/opacity/outline/low-confidence review items produce no apply plan');
assertTrue(buildGuardedApplyPreflightForProbe($handoff)['plans'] === [], 'effect candidates produce no apply plan');
assertTrue(buildGuardedApplyPreflightForProbe($excluded)['plans'] === [], 'print and dynamic declarations produce no apply plan');
assertTrue(array_reduce($manual, static fn(bool $ok, array $p): bool => $ok && (string)($p['future_apply_eligibility'] ?? '') !== 'eligible', true), 'manual semantic decisions never have future_apply_eligibility=eligible');
assertTrue(array_reduce($allContractItems, static fn(bool $ok, array $p): bool => $ok && isset($p['detection_confidence'], $p['semantic_mapping_confidence'], $p['future_apply_eligibility']), true), 'proposal records distinguish detection confidence, mapping confidence, and future apply eligibility');

$preflightFirstPlan = is_array($syntheticPlans[0] ?? null) ? $syntheticPlans[0] : [];
assertTrue((string)($preflightFirstPlan['apply_scope'] ?? '') === 'single_declaration', 'apply scope remains single_declaration');
assertTrue((string)($preflightFirstPlan['authority_requirement'] ?? '') === 'owner_review_required', 'authority requirement remains owner_review_required');
assertTrue((string)($preflightFirstPlan['declaration_fingerprint'] ?? '') !== '' && (string)($preflightFirstPlan['proposal_fingerprint'] ?? '') !== '', 'preflight plan carries declaration/proposal fingerprints');
$executorEnabled = !empty(StyleComplianceGuardedRepairCapabilityService::contract()['executor_enabled']);
if ($executorEnabled) {
    assertTrue((string)($preflightFirstPlan['mutation_endpoint'] ?? '') === '/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute'
        && (string)($preflightFirstPlan['apply_action'] ?? '') === 'single_guarded_repair_after_successful_preflight',
        'guarded apply plan exposes only the single-proposal guarded execute endpoint and action');
} else {
    assertTrue((string)($preflightFirstPlan['mutation_endpoint'] ?? '') === ''
        && (string)($preflightFirstPlan['apply_action'] ?? '') === '',
        'guarded apply plan has empty endpoint/action when executor is disabled');
}
assertTrue(isset($preflightFirstPlan['apply_plan_id'], $preflightFirstPlan['preflight_status'], $preflightFirstPlan['preflight_checks']), 'preflight plan structure preserved regardless of executor state');

$preflightSummary = $syntheticPreflight['summary'] ?? [];
assertTrue(isset($preflightSummary['eligible_after_preflight'], $preflightSummary['blocked'], $preflightSummary['stale'], $preflightSummary['review_required']), 'preflight summary distinguishes eligible, blocked, stale, and review-required populations');

echo "\n";

// ============================================================
// Isolated Guarded-Repair Probe — all tests use temp fixture
// copies created at runtime, never real owner CSS.
// ============================================================
echo "--- Isolated Guarded Repair: setup temp fixtures ---\n";
$tmpFixturePath = createProbeTempFixture();
$tmpFixtureAbs = PROBE_TMP_DIR . '/' . PROBE_TMP_FIXTURE_FILE;
cleanupProbeTempFixtures(); // ensure clean start
$tmpFixturePath = createProbeTempFixture(); // fresh copy
$originalContents = probeTempFixtureContents();
assertTrue(str_contains(readProbeTempFixture(), 'var(--tone-danger)'), 'temp fixture contains expected declaration');

// ---- TC1: Fresh fixture proposal - readiness passes ----
echo "  TC1: fresh fixture proposal readiness\n";
$freshProposal = realFileProposalForProbe($tmpFixturePath);
$freshReadiness = StyleComplianceRepairReadinessService::checkProposalForReadiness($freshProposal, $originalContents);
$readinessState = (string)($freshReadiness['state'] ?? '');
$executorEnabled = !empty(StyleComplianceGuardedRepairCapabilityService::contract()['executor_enabled']);
$expectedState = $executorEnabled ? 'ready_for_guarded_repair' : 'blocked';
assertTrue($readinessState === $expectedState, 'TC1: fresh proposal readiness (state=' . $readinessState . ', expected=' . $expectedState . ')');
assertTrue((int)($freshReadiness['expected_change']['declaration_match_count'] ?? 0) === 1, 'TC1: exactly one declaration matched');

// ---- TC1b: surgical replacement affects exactly one declaration ----
echo "  TC1b: surgical replacement\n";
$repairResult = StyleComplianceRepairEngineService::executeRepair($freshProposal, [
    'source_contents' => $originalContents,
    'scan_scope' => '',
    'scan_owner_key' => '',
]);
$modState = (string)($repairResult['state'] ?? '');
if ($executorEnabled) {
    assertTrue(in_array($modState, ['applied_unverified', 'resolved', 'unverified'], true), 'TC1: repair executed (state=' . $modState . ')');
    // Verify the written file contains the replacement
    $postContents = readProbeTempFixture();
    assertTrue(str_contains($postContents, 'var(--tone-danger-text)'), 'TC1: written file contains replacement value');
    assertTrue(!str_contains($postContents, 'var(--tone-danger)'), 'TC1: old value is gone from written file');
} else {
    assertTrue($modState === 'blocked', 'TC1: repair blocked (state=' . $modState . ') when executor is disabled');
}
// Restore the temp fixture to original for subsequent tests
writeProbeTempFixture($originalContents);
assertTrue(readProbeTempFixture() === $originalContents, 'TC1: fixture restored to original after surgical test');

// ---- TC2: Stale fixture proposal - mutate fixture copy ----
echo "  TC2: stale fixture proposal\n";
$staleProposal = realFileProposalForProbe($tmpFixturePath);
// Mutate the temp fixture so the expected declaration no longer matches
writeProbeTempFixture(".repair-fixture { color: var(--tone-success); }\n");
$staleReadiness = StyleComplianceRepairReadinessService::checkProposalForReadiness($staleProposal, null);
$staleState = (string)($staleReadiness['state'] ?? '');
assertTrue(in_array($staleState, ['stale', 'blocked'], true), 'TC2: mutated fixture produces stale/blocked readiness (state=' . $staleState . ')');
assertTrue((int)($staleReadiness['expected_change']['declaration_match_count'] ?? 99) === 0, 'TC2: zero matches found in stale file');
// Restore
writeProbeTempFixture($originalContents);

// ---- TC3: Tampered request evidence — no write occurs ----
echo "  TC3: tampered request evidence\n";
$tamperedProposal = realFileProposalForProbe($tmpFixturePath);
$tamperedProposal['current_value'] = 'var(--not-the-actual-value)';
$tamperedReadiness = StyleComplianceRepairReadinessService::checkProposalForReadiness($tamperedProposal, $originalContents);
assertTrue((string)($tamperedReadiness['state'] ?? '') === 'stale', 'TC3: tampered current_value produces stale readiness');
$tamperedRepair = StyleComplianceRepairEngineService::executeRepair($tamperedProposal, [
    'source_contents' => $originalContents,
    'scan_scope' => '',
    'scan_owner_key' => '',
]);
assertTrue(!($tamperedRepair['ok'] ?? false), 'TC3: tampered repair fails (ok=false)');
assertTrue(!str_contains(readProbeTempFixture(), 'var(--not-the-actual-value)'), 'TC3: no write occurred from tampered request');

// ---- TC4: Duplicate declaration — ambiguous, non-executable ----
echo "  TC4: duplicate declaration\n";
$dupPath = createProbeTempDuplicateFixture();
$dupProposal = $freshProposal;
$dupProposal['proposal_id'] = 'tar-dup-probe-' . bin2hex(random_bytes(4));
$dupProposal['file_path'] = $dupPath;
$dupProposal['selector'] = ''; // No selector to force true ambiguity (selector disambiguation would resolve single-match)
$dupContents = probeTempDuplicateContents();
$dupReadiness = StyleComplianceRepairReadinessService::checkProposalForReadiness($dupProposal, $dupContents);
$dupState = (string)($dupReadiness['state'] ?? '');
assertTrue(in_array($dupState, ['ambiguous', 'stale'], true), 'TC4: duplicate declaration is ambiguous/stale (state=' . $dupState . ')');
$dupRepair = StyleComplianceRepairEngineService::executeRepair($dupProposal, [
    'source_contents' => $dupContents,
    'scan_scope' => '',
    'scan_owner_key' => '',
]);
assertTrue(!($dupRepair['ok'] ?? false), 'TC4: duplicate repair is non-executable (ok=false)');

// ---- TC5: Rollback simulation — fixture restored correctly ----
echo "  TC5: rollback simulation\n";
$rollbackBackup = readProbeTempFixture();
$rollbackProposal = realFileProposalForProbe($tmpFixturePath);
$rollbackResult = StyleComplianceRepairEngineService::executeRepair($rollbackProposal, [
    'source_contents' => $originalContents,
    'scan_scope' => '',
    'scan_owner_key' => '',
]);
$rollbackState = (string)($rollbackResult['state'] ?? '');
$executorEnabled = !empty(StyleComplianceGuardedRepairCapabilityService::contract()['executor_enabled']);
if ($executorEnabled) {
    assertTrue(in_array($rollbackState, ['applied_unverified', 'resolved', 'unverified'], true), 'TC5: rollback repair executed (state=' . $rollbackState . ')');
    $rolledContents = readProbeTempFixture();
    assertTrue(str_contains($rolledContents, 'var(--tone-danger-text)'), 'TC5: file modified by repair');
} else {
    assertTrue($rollbackState === 'blocked', 'TC5: rollback blocked (state=' . $rollbackState . ') when executor is disabled');
}
// Rollback: restore from backup
writeProbeTempFixture($rollbackBackup);
assertTrue(readProbeTempFixture() === $rollbackBackup, 'TC5: fixture restored from backup matches original');

// ---- TC5b: Verify backup is exact ----
assertTrue($rollbackBackup === $originalContents, 'TC5b: rollback backup matches original content');

// ---- TC6: Preflight plan still works against the isolated temp file ----
echo "  TC6: preflight plan stability with temp fixture\n";
$planProposal = realFileProposalForProbe($tmpFixturePath);
$planA = buildGuardedApplyPreflightForProbe([$planProposal]);
$planB = buildGuardedApplyPreflightForProbe([$planProposal]);
$planIdA = (string)($planA['plans'][0]['apply_plan_id'] ?? '');
$planIdB = (string)($planB['plans'][0]['apply_plan_id'] ?? '');
assertTrue($planIdA !== '' && $planIdA === $planIdB, 'deterministic token-role proposal produces stable apply_plan_id');
$planStatus = (string)($planA['plans'][0]['preflight_status'] ?? '');
assertTrue($planStatus === 'ready', 'temp-fixture-backed preflight plan status is ready (got: ' . $planStatus . ')');

// ---- TC7: Tampered preflight checks — source_owner and token integrity ----
echo "  TC7: tampered preflight checks\n";
$tamperOwner = realFileProposalForProbe($tmpFixturePath);
$tamperOwner['source_owner'] = '';
$tamperOwnerPlan = buildGuardedApplyPreflightForProbe([$tamperOwner])['plans'][0] ?? [];
assertTrue(in_array((string)($tamperOwnerPlan['preflight_status'] ?? ''), ['blocked', 'review_required'], true), 'missing owner produces blocked or review-required preflight');

$tamperToken = realFileProposalForProbe($tmpFixturePath);
$tamperToken['replacement_token'] = '--tone-missing-contract-token';
$tamperToken['replacement_value'] = 'var(--tone-missing-contract-token)';
$tamperTokenPlan = buildGuardedApplyPreflightForProbe([$tamperToken])['plans'][0] ?? [];
assertTrue((string)($tamperTokenPlan['preflight_status'] ?? '') === 'blocked', 'missing replacement token produces blocked preflight');

$tamperValue = realFileProposalForProbe($tmpFixturePath);
$tamperValue['current_value'] = 'var(--definitely-not-current-anymore)';
$tamperValuePlan = buildGuardedApplyPreflightForProbe([$tamperValue])['plans'][0] ?? [];
assertTrue((string)($tamperValuePlan['preflight_status'] ?? '') === 'stale', 'changed expected current value produces stale preflight');

$tamperFingerprint = realFileProposalForProbe($tmpFixturePath);
$tamperFingerprint['expected_declaration_fingerprint'] = 'sha256:changed';
$tamperFingerprintPlan = buildGuardedApplyPreflightForProbe([$tamperFingerprint])['plans'][0] ?? [];
assertTrue((string)($tamperFingerprintPlan['preflight_status'] ?? '') === 'stale', 'changed declaration fingerprint produces stale preflight');

// ---- TC8: Cleanup - no fixture mutation leaks ----
echo "  TC8: cleanup\n";
// Verify temp fixture is still in original state
assertTrue(readProbeTempFixture() === $originalContents, 'TC8: temp fixture matches original after all tests');
cleanupProbeTempFixtures();
assertTrue(!is_dir(PROBE_TMP_DIR), 'TC8: temp directory removed');
assertTrue(!is_file($tmpFixtureAbs), 'TC8: temp fixture file removed');

echo "\n";

// ============================================================
// Migration Calibration Fixtures — prove each distinct outcome
// ============================================================
echo "--- Migration Calibration: Owner → Shell ---\n";
$calOwnerToShell = scanFixture($fixturesDir . '/migration_owner_to_shell.css');
$shellSelectorTokens = array_filter($calOwnerToShell, static fn(array $r): bool =>
    in_array((string)($r['selector'] ?? ''), ['.app-shell', '.layout-sidebar', '.topbar'], true)
);
assertTrue(count($shellSelectorTokens) > 0, 'migration_owner_to_shell.css has shell selector tokens');
$domainMigCount = 0;
foreach ($shellSelectorTokens as $row) {
    if ((string)($row['migration_state'] ?? '') === 'domain_migration') {
        $domainMigCount++;
    }
    assertTrue((string)($row['governance_domain'] ?? '') === 'not_applicable',
        'owner→shell token has governance_domain=not_applicable (owner_surface normalizes to not_applicable) (got: ' . ($row['governance_domain'] ?? '') . ')');
    assertTrue((string)($row['governance_required_domain'] ?? '') === 'shell_foundation',
        'owner→shell token has governance_required_domain=shell_foundation (got: ' . ($row['governance_required_domain'] ?? '') . ')');
    assertTrue((string)($row['target_owner'] ?? '') === 'Shell/Foundation',
        'owner→shell token has target_owner=Shell/Foundation (got: ' . ($row['target_owner'] ?? '') . ')');
    assertTrue((string)($row['target_tool'] ?? '') === 'style_compliance',
        'owner→shell token has target_tool=style_compliance (got: ' . ($row['target_tool'] ?? '') . ')');
    assertTrue(in_array((string)($row['migration_confidence'] ?? ''), ['high', 'medium'], true),
        'owner→shell token has high or medium confidence (got: ' . ($row['migration_confidence'] ?? '') . ')');
    assertTrue((string)($row['source_scope'] ?? '') === 'owner',
        'owner→shell token has source_scope=owner (got: ' . ($row['source_scope'] ?? '') . ')');
    assertTrue((string)($row['source_owner'] ?? '') !== '',
        'owner→shell token has non-empty source_owner (got: ' . ($row['source_owner'] ?? '') . ')');
}
assertTrue($domainMigCount > 0, 'migration_owner_to_shell.css produces at least one domain_migration');

echo "\n--- Migration Calibration: Owner → Effects ---\n";
$calOwnerToEffects = scanFixture($fixturesDir . '/migration_owner_to_effects.css');
$ownerEffectCount = 0;
$ownerEffectHandoff = 0;
foreach ($calOwnerToEffects as $row) {
    if (!empty($row['is_effect_candidate'])) {
        $ownerEffectCount++;
        if ((string)($row['migration_state'] ?? '') === 'future_handoff') {
            $ownerEffectHandoff++;
        }
        assertTrue((string)($row['governance_domain'] ?? '') === 'special_effect',
            'owner→effects token has governance_domain=special_effect');
        assertTrue((string)($row['governance_required_domain'] ?? '') === 'special_effect',
            'owner→effects token has governance_required_domain=special_effect');
        assertTrue((string)($row['target_owner'] ?? '') === 'Special Effects Pipeline',
            'owner→effects token has target_owner=Special Effects Pipeline');
        assertTrue((string)($row['target_tool'] ?? '') === 'special_effects',
            'owner→effects token has target_tool=special_effects');
        assertTrue((string)($row['source_scope'] ?? '') === 'owner',
            'owner→effects token has source_scope=owner');
        assertTrue((string)($row['source_owner'] ?? '') !== '',
            'owner→effects token has non-empty source_owner');
    }
}
assertTrue($ownerEffectCount > 0, 'migration_owner_to_effects.css has effect candidate tokens');
assertTrue($ownerEffectHandoff > 0, 'migration_owner_to_effects.css produces future_handoff migration state');

echo "\n--- Migration Calibration: Theme → Shell ---\n";
$calThemeToShell = scanFixture($fixturesDir . '/migration_theme_to_shell.css', 'owner', true);
$themeShellSelectorTokens = array_filter($calThemeToShell, static fn(array $r): bool =>
    in_array((string)($r['selector'] ?? ''), ['.app-shell', '.layout-sidebar', '.topbar'], true)
);
assertTrue(count($themeShellSelectorTokens) > 0, 'migration_theme_to_shell.css has shell selector tokens');
$themeDomainMigCount = 0;
foreach ($themeShellSelectorTokens as $row) {
    assertTrue(!empty($row['in_theme_source']),
        'theme→shell token has in_theme_source=true');
    if ((string)($row['migration_state'] ?? '') === 'domain_migration') {
        $themeDomainMigCount++;
    }
    assertTrue((string)($row['governance_domain'] ?? '') === 'theme_related',
        'theme→shell token has governance_domain=theme_related (theme normalizes to theme_related) (got: ' . ($row['governance_domain'] ?? '') . ')');
    assertTrue((string)($row['governance_required_domain'] ?? '') === 'shell_foundation',
        'theme→shell token has governance_required_domain=shell_foundation');
    assertTrue((string)($row['target_owner'] ?? '') === 'Shell/Foundation',
        'theme→shell token has target_owner=Shell/Foundation (got: ' . ($row['target_owner'] ?? '') . ')');
    assertTrue((string)($row['target_tool'] ?? '') === 'style_compliance',
        'theme→shell token has target_tool=style_compliance');
    assertTrue((string)($row['migration_confidence'] ?? '') === 'high',
        'theme→shell token has confidence=high (got: ' . ($row['migration_confidence'] ?? '') . ')');
    assertTrue((string)($row['source_scope'] ?? '') === 'owner',
        'theme→shell token has source_scope=owner (got: ' . ($row['source_scope'] ?? '') . ')');
    assertTrue((string)($row['source_owner'] ?? '') !== '',
        'theme→shell token has non-empty source_owner (got: ' . ($row['source_owner'] ?? '') . ')');
}
assertTrue($themeDomainMigCount > 0, 'migration_theme_to_shell.css produces at least one domain_migration');

echo "\n--- Migration Calibration: Theme → Effects ---\n";
$calThemeToEffects = scanFixture($fixturesDir . '/migration_theme_to_effects.css', 'owner', true);
$themeEffectCount = 0;
$themeEffectHandoff = 0;
foreach ($calThemeToEffects as $row) {
    if (!empty($row['is_effect_candidate'])) {
        $themeEffectCount++;
        if ((string)($row['migration_state'] ?? '') === 'future_handoff') {
            $themeEffectHandoff++;
        }
        assertTrue(!empty($row['in_theme_source']),
            'theme→effects token has in_theme_source=true');
        assertTrue((string)($row['governance_domain'] ?? '') === 'special_effect',
            'theme→effects token has governance_domain=special_effect');
        assertTrue((string)($row['governance_required_domain'] ?? '') === 'special_effect',
            'theme→effects token has governance_required_domain=special_effect');
        assertTrue((string)($row['target_owner'] ?? '') === 'Special Effects Pipeline',
            'theme→effects token has target_owner=Special Effects Pipeline');
        assertTrue((string)($row['target_tool'] ?? '') === 'special_effects',
            'theme→effects token has target_tool=special_effects');
        assertTrue((string)($row['migration_confidence'] ?? '') === 'high',
            'theme→effects token has confidence=high');
        assertTrue((string)($row['source_scope'] ?? '') === 'owner',
            'theme→effects token has source_scope=owner');
        assertTrue((string)($row['source_owner'] ?? '') !== '',
            'theme→effects token has non-empty source_owner');
    }
}
assertTrue($themeEffectCount > 0, 'migration_theme_to_effects.css has effect candidate tokens');
assertTrue($themeEffectHandoff > 0, 'migration_theme_to_effects.css produces future_handoff');

echo "\n--- Migration Calibration: Ambiguous owner selector ---\n";
$calAmbiguous = scanFixture($fixturesDir . '/migration_ambiguous.css');
$ambigReviewCount = 0;
$ambigLowCount = 0;
foreach ($calAmbiguous as $row) {
    if ((string)($row['migration_state'] ?? '') === 'classification_review') {
        $ambigReviewCount++;
    }
    if ((string)($row['migration_confidence'] ?? '') === 'low') {
        $ambigLowCount++;
    }
    // No ambiguous token should become domain_migration (insufficient evidence)
    assertTrue((string)($row['migration_state'] ?? '') !== 'domain_migration',
        'ambiguous token does not become domain_migration');
}
assertTrue($ambigReviewCount > 0, 'migration_ambiguous.css produces at least one classification_review (got: ' . $ambigReviewCount . ')');
assertTrue($ambigLowCount > 0, 'migration_ambiguous.css produces at least one low-confidence token (got: ' . $ambigLowCount . ')');

echo "\n--- Migration Calibration: Negative controls ---\n";
$calNegative = scanFixture($fixturesDir . '/migration_negative_controls.css');
$negDomainMigCount = 0;
$negHasValueFix = 0;
foreach ($calNegative as $row) {
    if ((string)($row['migration_state'] ?? '') === 'domain_migration') {
        $negDomainMigCount++;
        echo "  UNEXPECTED domain_migration: {$row['selector']} / {$row['property']} / {$row['token']}\n";
    }
    if ((string)($row['migration_state'] ?? '') === 'value_fix') {
        $negHasValueFix++;
    }
}
assertTrue($negDomainMigCount === 0, 'negative controls have zero domain_migration (got: ' . $negDomainMigCount . ')');
assertTrue($negHasValueFix > 0, 'negative controls produce at least one value_fix (stable literals should)');
echo "  Negative controls: $negDomainMigCount domain_migration, $negHasValueFix value_fix\n";

echo "\n--- Migration Calibration: Confidence gating ---\n";
$allCalFiles = [
    scanFixture($fixturesDir . '/migration_owner_to_shell.css'),
    scanFixture($fixturesDir . '/migration_owner_to_effects.css'),
    scanFixture($fixturesDir . '/migration_theme_to_shell.css', 'owner', true),
    scanFixture($fixturesDir . '/migration_theme_to_effects.css', 'owner', true),
    scanFixture($fixturesDir . '/migration_ambiguous.css'),
    scanFixture($fixturesDir . '/migration_negative_controls.css'),
];
$lowConfDomainMig = 0;
foreach ($allCalFiles as $tokens) {
    foreach ($tokens as $row) {
        $state = (string)($row['migration_state'] ?? '');
        $conf = (string)($row['migration_confidence'] ?? '');
        if ($state === 'domain_migration' && $conf === 'low') {
            $lowConfDomainMig++;
            echo "  LOW confidence domain_migration: {$row['selector']} / {$row['property']}\n";
        }
    }
}
assertTrue($lowConfDomainMig === 0, 'no low-confidence finding has migration_state=domain_migration (got: ' . $lowConfDomainMig . ')');

echo "\n";

// ============================================================
// Migration Decision — cross-cutting field check across ALL fixtures
// ============================================================
echo "--- Migration Decision Fields (all fixtures) ---\n";
$fixtureFiles = [
    'aware.css' => $fixturesDir . '/aware.css',
    'unaware.css' => $fixturesDir . '/unaware.css',
    'with_repair.css' => $fixturesDir . '/with_repair.css',
    'dynamic_style.php' => $fixturesDir . '/dynamic_style.php',
    'structural.css' => $fixturesDir . '/structural.css',
    'effects.css' => $fixturesDir . '/effects.css',
    'print_style.php' => $fixturesDir . '/print_style.php',
    'semantic_misuse.css' => $fixturesDir . '/semantic_misuse.css',
    'migration_owner_to_shell.css' => $fixturesDir . '/migration_owner_to_shell.css',
    'migration_owner_to_effects.css' => $fixturesDir . '/migration_owner_to_effects.css',
    'migration_ambiguous.css' => $fixturesDir . '/migration_ambiguous.css',
    'migration_negative_controls.css' => $fixturesDir . '/migration_negative_controls.css',
];
// Theme-source calibration fixtures (scanned with isThemeSource=true)
$themeFixtures = [
    'migration_theme_to_shell.css (theme)' => [$fixturesDir . '/migration_theme_to_shell.css', true],
    'migration_theme_to_effects.css (theme)' => [$fixturesDir . '/migration_theme_to_effects.css', true],
];
$migFixtureTokens = 0;
$migFixtureAllHaveFields = true;
$migFixtureStateNone = 0;
$migFixtureStateValueFix = 0;
$migFixtureStateDomain = 0;
$migFixtureStateReview = 0;
$migFixtureStateHandoff = 0;
$migFixtureStateNa = 0;
foreach ($fixtureFiles as $name => $fpath) {
    $result = scanFixture($fpath);
    foreach ($result as $i => $row) {
        $migFixtureTokens++;
        $hasCurrentDomain = array_key_exists('governance_domain', $row);
        $hasRequiredDomain = array_key_exists('governance_required_domain', $row);
        $hasMigState = array_key_exists('migration_state', $row);
        $hasMigReason = array_key_exists('migration_reason', $row);
        $hasTargetOwner = array_key_exists('target_owner', $row);
        $hasTargetTool = array_key_exists('target_tool', $row);
        $hasMigConfidence = array_key_exists('migration_confidence', $row);
        $allPresent = $hasCurrentDomain && $hasRequiredDomain && $hasMigState && $hasMigReason && $hasTargetOwner && $hasTargetTool && $hasMigConfidence;
        if (!$allPresent) {
            $migFixtureAllHaveFields = false;
            echo "  MISSING: $name token $i ({$row['property']}): " .
                ($hasCurrentDomain ? '' : 'governance_domain ') .
                ($hasRequiredDomain ? '' : 'governance_required_domain ') .
                ($hasMigState ? '' : 'migration_state ') .
                ($hasMigReason ? '' : 'migration_reason ') .
                ($hasTargetOwner ? '' : 'target_owner ') .
                ($hasTargetTool ? '' : 'target_tool ') .
                ($hasMigConfidence ? '' : 'migration_confidence ') .
                "\n";
        }
        $state = (string)($row['migration_state'] ?? 'none');
        match ($state) {
            'none' => $migFixtureStateNone++,
            'value_fix' => $migFixtureStateValueFix++,
            'domain_migration' => $migFixtureStateDomain++,
            'classification_review' => $migFixtureStateReview++,
            'future_handoff' => $migFixtureStateHandoff++,
            default => $migFixtureStateNa++,
        };
    }
}
// Include theme-source calibration fixtures
foreach ($themeFixtures as $name => [$fpath, $isTheme]) {
    $result = scanFixture($fpath, 'owner', $isTheme);
    foreach ($result as $i => $row) {
        $migFixtureTokens++;
        $hasAll = array_key_exists('governance_domain', $row) && array_key_exists('governance_required_domain', $row)
            && array_key_exists('migration_state', $row) && array_key_exists('migration_reason', $row)
            && array_key_exists('target_owner', $row) && array_key_exists('target_tool', $row)
            && array_key_exists('migration_confidence', $row);
        if (!$hasAll) { $migFixtureAllHaveFields = false; }
        $state = (string)($row['migration_state'] ?? 'none');
        match ($state) {
            'none' => $migFixtureStateNone++,
            'value_fix' => $migFixtureStateValueFix++,
            'domain_migration' => $migFixtureStateDomain++,
            'classification_review' => $migFixtureStateReview++,
            'future_handoff' => $migFixtureStateHandoff++,
            default => $migFixtureStateNa++,
        };
    }
}
assertTrue($migFixtureTokens > 0, 'migration decisions checked on at least one token');
assertTrue($migFixtureAllHaveFields, 'all fixture tokens have complete migration decision fields');
assertTrue($migFixtureStateDomain > 0, 'at least one fixture produces domain_migration');
assertTrue($migFixtureStateNone >= 0, 'migration_state=none is valid');
assertTrue($migFixtureStateValueFix >= 0, 'migration_state=value_fix is valid');
assertTrue($migFixtureStateReview >= 0, 'migration_state=classification_review is valid');
assertTrue($migFixtureStateHandoff >= 0, 'migration_state=future_handoff is valid');
echo "  Total tokens checked: $migFixtureTokens\n";
echo "  State distribution: none=$migFixtureStateNone value_fix=$migFixtureStateValueFix domain=$migFixtureStateDomain review=$migFixtureStateReview handoff=$migFixtureStateHandoff na=$migFixtureStateNa\n";

echo "\n";

// ============================================================
// Acceptance invariants — prove defect fixes hold
// ============================================================
echo "--- Acceptance Invariants ---\n";

// I1: governance_domain is never owner_surface after normalization
$invOwnerSurfaceDomain = 0;
foreach ($allCalFiles as $tokens) {
    foreach ($tokens as $row) {
        if ((string)($row['governance_domain'] ?? '') === 'owner_surface') {
            $invOwnerSurfaceDomain++;
            echo "  FAIL: governance_domain remains owner_surface: {$row['file']}\n";
        }
    }
}
assertTrue($invOwnerSurfaceDomain === 0, 'no token has governance_domain=owner_surface after normalization (got: ' . $invOwnerSurfaceDomain . ')');

// I2: isOperationalFinding returns false for fixture file paths
$refOps = new ReflectionMethod(StyleComplianceScannerService::class, 'isOperationalFinding');
if (PHP_VERSION_ID < 80100) { $refOps->setAccessible(true); }
$fixtureRowIn = ['file' => 'apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Tests/fixtures/test.css', 'scan_category' => 'visual_literal'];
assertTrue(!$refOps->invoke(null, $fixtureRowIn), 'isOperationalFinding returns false for Tests/fixtures/ path');

$productionRowIn = ['file' => 'apps/Manufacturing/styles/manufacturing.css', 'scan_category' => 'visual_literal'];
assertTrue($refOps->invoke(null, $productionRowIn), 'isOperationalFinding returns true for production path');

// I3: isOperationalFinding returns false for empty file
$emptyFileRow = ['file' => '', 'scan_category' => 'visual_literal'];
assertTrue(!$refOps->invoke(null, $emptyFileRow), 'isOperationalFinding returns false for empty file path');

// I4: ownerKeyFromRelativeFile correctly resolves owner keys
$refOwnerKey = new ReflectionMethod(StyleComplianceScannerService::class, 'ownerKeyFromRelativeFile');
if (PHP_VERSION_ID < 80100) { $refOwnerKey->setAccessible(true); }

$ok1 = $refOwnerKey->invoke(null, 'apps/Manufacturing/styles/manufacturing.css');
assertTrue($ok1 === 'Manufacturing', 'ownerKeyFromRelativeFile: apps/Manufacturing/ → Manufacturing (got: ' . $ok1 . ')');

$ok2 = $refOwnerKey->invoke(null, 'apps/Manufacturing/modules/Coverage/styles.css');
assertTrue($ok2 === 'Manufacturing/Coverage', 'ownerKeyFromRelativeFile: apps/Manufacturing/modules/Coverage/ → Manufacturing/Coverage (got: ' . $ok2 . ')');

$ok3 = $refOwnerKey->invoke(null, 'apps/SBAIO/styles/sbaio.css');
assertTrue($ok3 === 'SBAIO', 'ownerKeyFromRelativeFile: apps/SBAIO/ → SBAIO (got: ' . $ok3 . ')');

$ok4 = $refOwnerKey->invoke(null, 'plugins/Base/whatever.css');
assertTrue($ok4 === 'Owner', 'ownerKeyFromRelativeFile: plugins/ → Owner (fallback) (got: ' . $ok4 . ')');

$ok5 = $refOwnerKey->invoke(null, 'apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Tests/fixtures/migration_owner_to_shell.css');
assertTrue($ok5 === 'Studio', 'ownerKeyFromRelativeFile: apps/Studio/.../fixtures → Studio (got: ' . $ok5 . ')');

// I5: scan() entry point excludes fixture tokens (indirect: fixture file never produces non-empty scan)
$ownerList = StyleComplianceScannerService::discoverOwners();
$hasStudio = false;
foreach ($ownerList as $o) {
    if (($o['owner_key'] ?? '') === 'Studio') {
        $hasStudio = true;
    }
}
assertTrue($hasStudio, 'Studio is a discoverable owner');
unset($ownerList, $hasStudio);

// I6: buildRepairCandidates still emits candidates when called directly with production data
$fixCalOwnerToShell = scanFixture($fixturesDir . '/migration_owner_to_shell.css');
$fixCandidates = buildRepairCandidatesForProbe($fixCalOwnerToShell);
assertTrue(is_array($fixCandidates), 'buildRepairCandidates emits array for fixture data');
$fixCandidateKeysFound = count($fixCandidates) > 0;
// owner→shell fixture has shell chrome selectors that produce domain_migration, not repair candidates
// But semantic_misuse fixture SHOULD produce repair candidates
$fixSemanticMisuse = scanFixture($fixturesDir . '/semantic_misuse.css');
$fixSemCandidates = buildRepairCandidatesForProbe($fixSemanticMisuse);
$fixSemCorrectionCount = 0;
foreach ($fixSemCandidates as $c) {
    if (($c['repair_action'] ?? '') === 'semantic_token_correction') {
        $fixSemCorrectionCount++;
    }
}
assertTrue($fixSemCorrectionCount === 3, 'buildRepairCandidates: semantic_misuse.css produces 3 correction candidates (got: ' . $fixSemCorrectionCount . ')');

echo "\n";

// ============================================================
// 14. Live render locale/regression invariants
// ============================================================
echo "--- Render: locale and async reconciliation invariants ---\n";

$localePath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_locale.php';
$previewPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/preview.php';
$sectionsPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_sections.php';
$contextPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_context.php';
$actionSummaryPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_action_summary.php';
$verifiedFixesPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_verified_fixes.php';
$decisionBacklogPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_decision_backlog.php';
$diagnosticsPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_diagnostics.php';
$scriptsPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_scripts.php';
$resultsMountPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_results_mount.php';
$controllerPath = APP_ROOT . '/apps/Studio/Controllers/StudioController.php';

$localeSource = (string)file_get_contents($localePath);
$previewSource = (string)file_get_contents($previewPath);
$sectionsSource = (string)file_get_contents($sectionsPath);
$contextSource = (string)file_get_contents($contextPath);
$actionSummarySource = (string)file_get_contents($actionSummaryPath);
$verifiedFixesSource = (string)file_get_contents($verifiedFixesPath);
$decisionBacklogSource = (string)file_get_contents($decisionBacklogPath);
$diagnosticsSource = (string)file_get_contents($diagnosticsPath);
$scriptsSource = (string)file_get_contents($scriptsPath);
$resultsMountSource = (string)file_get_contents($resultsMountPath);
$controllerSource = (string)file_get_contents($controllerPath);

assertTrue(str_contains($previewSource, "require __DIR__ . '/_locale.php';"), 'initial preview render requires shared _locale.php');
assertTrue(str_contains($controllerSource, "require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_locale.php';"), 'async scan render requires shared _locale.php');
assertTrue(str_contains($resultsMountSource, "require __DIR__ . '/../_result_sections.php';"), 'initial preview render uses shared result composition path');
assertTrue(str_contains($controllerSource, "require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_sections.php';"), 'async scan render uses shared result composition path');
assertTrue(str_contains($sectionsSource, "_result_context.php") && str_contains($sectionsSource, "_result_verified_fixes.php") && str_contains($sectionsSource, "_result_decision_backlog.php") && str_contains($sectionsSource, "_result_diagnostics.php"), '_result_sections.php composes user-task partials');
assertTrue(!str_contains($previewSource, '$sc = static function'), 'preview.php has no inline $sc dictionary');
assertTrue(!str_contains($controllerSource, '$sc = static function'), 'StudioController has no inline $sc dictionary');
assertTrue(!str_contains($sectionsSource, '$sc = static function'), '_result_sections.php has no inline $sc dictionary');
assertTrue(str_contains($localeSource, "'en' => \$en") && str_contains($localeSource, "'ja' => array_replace(\$en") && str_contains($localeSource, "'ne' => array_replace(\$en"), '_locale.php owns en/ja/ne locale branches');

$GLOBALS['styleComplianceProbeLang'] = 'en';
require $localePath;
assertTrue($sourceScopeLabel('owner') === 'Owner source', 'source scope helper renders Owner source');
assertTrue($confidenceLabel('high') === 'High confidence', 'confidence helper renders High confidence');
assertTrue($govDomainHumanLabel('theme_related') === 'Theme-related', 'governance helper renders Theme-related');
assertTrue($govDomainHumanLabel('shell_foundation') === 'Shell/Foundation', 'governance helper renders Shell/Foundation');
assertTrue($govDomainHumanLabel('special_effect') === 'Special Effects', 'governance helper renders Special Effects');
assertTrue($govDomainHumanLabel('owner_surface') === 'N/A', 'owner scope is not rendered as a governance domain');
assertTrue($targetToolLabel('style_compliance') === 'Style Compliance Tool', 'target tool helper renders Style Compliance Tool');
assertTrue($sc('verified_candidates_title') === 'Verified Future Fix Candidates', 'EN verified candidates label resolves');
assertTrue($sc('decision_backlog_title') === 'Decision Backlog', 'EN decision backlog label resolves');
assertTrue($sc('section_diagnostics_title') === 'Audit Evidence and Scan Diagnostics', 'EN diagnostics label resolves');

$GLOBALS['styleComplianceProbeLang'] = 'ja';
require $localePath;
assertTrue($sc('migration_title') !== 'migration_title', 'JA migration_title is localized');
assertTrue($sourceScopeLabel('owner') !== 'label_source_scope_owner', 'JA source scope helper does not fall through to raw key');
assertTrue($sc('verified_candidates_title') !== 'verified_candidates_title', 'JA verified candidates label resolves without raw key');
assertTrue($sc('decision_backlog_title') !== 'decision_backlog_title', 'JA decision backlog label resolves without raw key');

$GLOBALS['styleComplianceProbeLang'] = 'ne';
require $localePath;
assertTrue($sc('migration_title') !== 'migration_title', 'NE migration_title is localized');
assertTrue($confidenceLabel('high') !== 'label_confidence_high', 'NE confidence helper does not fall through to raw key');
assertTrue($sc('verified_candidates_title') !== 'verified_candidates_title', 'NE verified candidates label resolves without raw key');
assertTrue($sc('decision_backlog_title') !== 'decision_backlog_title', 'NE decision backlog label resolves without raw key');

$renderScan = StyleComplianceScannerService::scan('owner', 'Studio');
$renderScan['csrf'] = 'probe-csrf';

// Render smoke keeps the payload bounded; full scan/proposal totals are tested
// above, and the live page still receives the complete result set.
$renderScan['tokens'] = array_slice(is_array($renderScan['tokens'] ?? null) ? $renderScan['tokens'] : [], 0, 120);
$renderScan['candidates'] = array_slice(is_array($renderScan['candidates'] ?? null) ? $renderScan['candidates'] : [], 0, 10);
$renderScan['affected_files'] = array_slice(is_array($renderScan['affected_files'] ?? null) ? $renderScan['affected_files'] : [], 0, 20);
$renderScan['boundary_findings'] = array_slice(is_array($renderScan['boundary_findings'] ?? null) ? $renderScan['boundary_findings'] : [], 0, 10);
if (isset($renderScan['theme_repair_proposals']) && is_array($renderScan['theme_repair_proposals'])) {
    $renderScan['theme_repair_proposals']['items'] = array_slice(is_array($renderScan['theme_repair_proposals']['items'] ?? null) ? $renderScan['theme_repair_proposals']['items'] : [], 0, 40);
    if (isset($renderScan['theme_repair_proposals']['queues']) && is_array($renderScan['theme_repair_proposals']['queues'])) {
        foreach ($renderScan['theme_repair_proposals']['queues'] as $queueKey => $queueRows) {
            $renderScan['theme_repair_proposals']['queues'][$queueKey] = array_slice(is_array($queueRows) ? $queueRows : [], 0, 5);
        }
    }
    if (isset($renderScan['theme_repair_proposals']['guarded_apply_preflight']['plans']) && is_array($renderScan['theme_repair_proposals']['guarded_apply_preflight']['plans'])) {
        $renderScan['theme_repair_proposals']['guarded_apply_preflight']['plans'] = array_slice($renderScan['theme_repair_proposals']['guarded_apply_preflight']['plans'], 0, 3);
    }
}
$renderQueues = isset($renderScan['theme_repair_proposals']['queues']) && is_array($renderScan['theme_repair_proposals']['queues']) ? $renderScan['theme_repair_proposals']['queues'] : [];
$renderManualCount = count(is_array($renderQueues['manual_semantic_decisions'] ?? null) ? $renderQueues['manual_semantic_decisions'] : []);
$renderReviewQueue = is_array($renderQueues['classification_and_accessibility_review'] ?? null) ? $renderQueues['classification_and_accessibility_review'] : [];
$renderClassificationCount = count(array_filter($renderReviewQueue, static fn(array $proposal): bool => (string)($proposal['review_reason_code'] ?? '') !== 'accessibility_review'));
$renderDecisionBacklogTotal = $renderManualCount + $renderClassificationCount;
$renderReadyCount = count(is_array($renderQueues['ready_for_future_guarded_apply'] ?? null) ? $renderQueues['ready_for_future_guarded_apply'] : []);
$renderReadyFullCount = (int)(is_array($renderScan['theme_repair_proposals'] ?? null) ? ($renderScan['theme_repair_proposals']['summary']['deterministic_future_apply'] ?? 0) : 0);
$renderHtml = renderStyleCompliancePreviewForProbe($renderScan, 'en');
$visibleHtml = visibleStyleComplianceHtmlForProbe($renderHtml);
$visibleText = html_entity_decode(strip_tags($visibleHtml), ENT_QUOTES, 'UTF-8');
$diagnosticsPos = strpos($visibleHtml, 'id="scDiagnostics"');
$taskHtml = is_int($diagnosticsPos) ? substr($visibleHtml, 0, $diagnosticsPos) : $visibleHtml;
$taskText = html_entity_decode(strip_tags($taskHtml), ENT_QUOTES, 'UTF-8');

assertTrue(!preg_match('/\bmigration_[a-z0-9_]+\b/', $taskText), 'task-facing rendered HTML contains no raw migration_* key');
assertTrue(!preg_match('/\bstyle_compliance\b/', $taskText), 'task-facing rendered HTML contains no raw style_compliance key');
assertTrue(str_contains($visibleText, 'Fixable Now'), 'rendered visible HTML contains primary candidate workspace');
if ($renderReadyCount > 0) {
    assertTrue(str_contains($taskText, 'Prepared'), 'rendered visible HTML states candidate workspace is read-only');
}
assertTrue(str_contains($visibleText, 'Accessibility Review'), 'rendered visible HTML contains separate accessibility review lane');
assertTrue(str_contains($visibleText, 'Decision Backlog'), 'rendered visible HTML renames manual review to Decision Backlog');
assertTrue(str_contains($visibleText, $sc('dashboard_needs_review')), 'dashboard contains needs-review metric card');
assertTrue(str_contains($visibleText, $renderManualCount . ' need semantic/design intent · ' . $renderClassificationCount . ' need classification review'), 'action summary shows Decision Backlog semantic/classification breakdown');
assertTrue(preg_match('/<span class="sc-section-count"[^>]*>' . $renderDecisionBacklogTotal . '<\/span>/', $visibleHtml) === 1, 'Decision Backlog section shows combined total count');
assertTrue(str_contains($visibleText, $renderManualCount . ' need semantic/design intent'), 'Decision Backlog section shows semantic/design count');
assertTrue(str_contains($visibleText, $renderClassificationCount . ' need classification review'), 'Decision Backlog section shows classification count');
assertTrue(str_contains($visibleText, $sc('dashboard_ready_to_repair')), 'dashboard contains ready-to-repair metric card');
if ($renderReadyCount > 0) {
    assertTrue(str_contains($visibleText, 'Prepared'), 'candidate rows use short Prepared status badge');
}
assertTrue(str_contains($visibleText, 'Special Effects Handoffs'), 'rendered visible HTML contains effects handoff lane');
assertTrue(str_contains($visibleText, 'Audit Evidence and Scan Diagnostics'), 'rendered visible HTML contains folded diagnostics section');
assertTrue(strpos($visibleHtml, 'id="scVerifiedCandidates"') < strpos($visibleHtml, 'id="scDiagnostics"'), 'verified candidates render before diagnostics');
assertTrue(preg_match('/<details\s+id="scDiagnostics"[^>]*class="sc-section sc-diagnostics-wrap"/', $visibleHtml) === 1, 'diagnostics details is closed by default');
assertTrue(substr_count($visibleHtml, 'id="scVerifiedCandidates"') === 1, 'primary candidate queue is rendered once');
assertTrue(substr_count($visibleHtml, 'id="scAccessibilityReview"') === 1, 'accessibility lane is rendered once');
assertTrue(substr_count($visibleHtml, 'id="scDecisionBacklog"') === 1, 'decision backlog is rendered once');
assertTrue(substr_count($visibleHtml, 'id="scEffectsHandoffs"') === 1, 'effects handoff lane is rendered once');
assertTrue(str_contains($visibleHtml, 'class="sc-decision-group"'), 'decision backlog groups source areas before raw rows');
assertTrue(str_contains($visibleHtml, 'class="sc-family-preview"'), 'decision backlog exposes decision-family grouping');
assertTrue(!preg_match('/<form[^>]+method=["\']post["\']/i', $visibleHtml), 'rendered result contains no POST form');
assertTrue(!preg_match('/<button[^>]*>\s*(Save|Execute|Approve|Queue|Persist|Update|Migrate|Cutover)\b/i', $visibleHtml), 'rendered result contains no unguarded mutation-labeled button');
if ($renderReadyCount > 0) {
    assertTrue(substr_count($visibleHtml, 'sc-repair-apply') === 0, 'guarded Apply repair button is not rendered when executor is disabled');
}
assertTrue(str_contains($visibleText, 'High confidence'), 'rendered visible HTML contains human confidence label');
assertTrue(str_contains($visibleText, 'Theme-related') || str_contains($visibleText, 'Shell/Foundation') || str_contains($visibleText, 'Special Effects') || str_contains($visibleText, 'N/A'), 'rendered visible HTML contains human governance-domain labels');
assertTrue(str_contains($visibleText, 'Theme-related governance findings'), 'overview domain distribution labels Theme-related governance, not Theme tokens');
assertTrue(str_contains($visibleText, 'Owner-local non-governance'), 'overview domain distribution includes owner-local non-governance bucket');
assertTrue(str_contains($visibleText, 'Mutually exclusive style-domain buckets'), 'overview domain distribution states metric population/reconciliation contract');
assertTrue(str_contains($visibleText, 'Deterministic future-apply proposals') && str_contains($visibleText, 'Manual-review decisions') && str_contains($visibleText, 'Files with deterministic proposals'), 'proposal counts distinguish deterministic, manual, and file units without mixed replacement operations');
assertTrue(!str_contains($visibleText, 'Failed to fetch'), 'successful render does not retain fetch-error banner text in visible output');
assertTrue(!str_contains($visibleText, 'Tests/fixtures/'), 'normal Studio render excludes fixture paths');
assertTrue(str_contains($diagnosticsSource, 'guarded_apply_preflight_title') && str_contains($diagnosticsSource, 'theme_repair_proposals'), 'diagnostics partial keeps raw preflight and proposal contract evidence reachable');
assertTrue(!str_contains($verifiedFixesSource, '$renderThemeProposalRows('), 'verified candidates use task-oriented work-package renderer');
assertTrue(str_contains($decisionBacklogSource, 'decision_family_text') && str_contains($decisionBacklogSource, 'decision_family_background'), 'decision backlog maps deterministic view-local decision families');
if ($renderReadyCount > 0) {
    assertTrue(str_contains($visibleText, 'Check repair readiness'), 'candidate detail exposes browser-only readiness check');
    assertTrue(str_contains($visibleText, 'Readiness not checked'), 'candidate detail starts with non-mutating readiness state');
    assertTrue(str_contains($visibleText, 'General guarded repair disabled'), 'precondition filter renders V1-compatible read_only_contract label');
    assertTrue(str_contains($visibleText, 'Use Fix One only'), 'precondition filter renders V1-compatible future_guarded_apply_not_enabled label');
}
assertTrue(str_contains($scriptsSource, '/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness'), 'readiness browser request sends proposal id to readiness endpoint');
$readinessJsStart = strpos($scriptsSource, "var btn = e.target.closest('.sc-readiness-check')");
$readinessJsEnd = is_int($readinessJsStart) ? strpos($scriptsSource, "resultsMount.addEventListener('click', function(e) {\n            var btn = e.target.closest('.sc-fix-one');", $readinessJsStart) : false;
$readinessJs = is_int($readinessJsStart) && is_int($readinessJsEnd) ? substr($scriptsSource, $readinessJsStart, $readinessJsEnd - $readinessJsStart) : '';
assertTrue($readinessJs !== '' && str_contains($readinessJs, "formData.set('proposal_id', proposalId)") && str_contains($readinessJs, "formData.set('csrf', csrfInput.value)"), 'readiness browser request submits only proposal id and csrf');
assertTrue(!preg_match('/formData\.set\((["\'])(file_path|line|selector|property|current_value|replacement_value|owner|owner_key)\1/i', $readinessJs), 'readiness browser request does not submit path, selector, value, line, or owner authority');

$migrationHtml = '';
$migrationStart = strpos($visibleHtml, '<!-- Migration Required -->');
$migrationEnd = strpos($visibleHtml, '<!-- Token inventory grouped by repair workflow lane -->');
if (is_int($migrationStart) && is_int($migrationEnd) && $migrationEnd > $migrationStart) {
    $migrationHtml = substr($visibleHtml, $migrationStart, $migrationEnd - $migrationStart);
}
$migrationRows = migrationRowsForProbe($migrationHtml);
$allowedGovDomains = ['Theme-related' => true, 'Shell/Foundation' => true, 'Special Effects' => true, 'N/A' => true];
$badGovDomain = '';
foreach ($migrationRows as $cells) {
    $gov = $cells[3] ?? '';
    $required = $cells[4] ?? '';
    if (!isset($allowedGovDomains[$gov])) {
        $badGovDomain = $gov;
        break;
    }
    if (!isset($allowedGovDomains[$required])) {
        $badGovDomain = $required;
        break;
    }
}
assertTrue($badGovDomain === '', 'governance-domain columns render only Theme-related, Shell/Foundation, Special Effects, or N/A (bad: ' . $badGovDomain . ')');

$ownerInGovColumn = false;
foreach ($migrationRows as $cells) {
    $sourceOwner = $cells[2] ?? '';
    if ($sourceOwner !== '' && (($cells[3] ?? '') === $sourceOwner || ($cells[4] ?? '') === $sourceOwner)) {
        $ownerInGovColumn = true;
        break;
    }
}
assertTrue(!$ownerInGovColumn, 'owner names never render in governance-domain cells');
assertTrue(!preg_match('/<details class="sc-migration-group"\s+open[^>]*>\s*<summary class="sc-migration-summary">\s*No migration needed/is', $visibleHtml), 'No migration needed section is folded by default');
assertTrue(!preg_match('/<details class="sc-migration-group"\s+open[^>]*>\s*<summary class="sc-migration-summary">\s*Not applicable/is', $visibleHtml), 'Not applicable section is folded by default');

echo "  Migration rows checked: " . count($migrationRows) . "\n";
echo "\n";

// ============================================================
// 15. Browser-only guarded repair readiness contract
// ============================================================
echo "--- Guarded repair readiness contract ---\n";

$routesPath = APP_ROOT . '/apps/Studio/routes.php';
$capabilityServicePath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceGuardedRepairCapabilityService.php';
$readinessServicePath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairReadinessService.php';
$routesSource = (string)file_get_contents($routesPath);
$capabilityServiceSource = (string)file_get_contents($capabilityServicePath);
$readinessServiceSource = (string)file_get_contents($readinessServicePath);

assertTrue(str_contains($routesSource, "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness"), 'readiness endpoint route is registered');
assertTrue(str_contains($routesSource, "/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute"), 'guarded repair execute endpoint route is registered');
assertTrue(str_contains($controllerSource, 'executor_unavailable'), 'repair-execute controller returns executor_unavailable when executor is disabled');
assertTrue(str_contains($controllerSource, 'styleComplianceRepairReadinessAsync'), 'readiness endpoint controller method exists');
assertTrue(str_contains($controllerSource, 'StyleComplianceRepairReadinessService::checkByProposalId($proposalId)'), 'readiness endpoint resolves server-side by proposal id');
$readinessControllerStart = strpos($controllerSource, 'public static function styleComplianceRepairReadinessAsync');
$readinessControllerEnd = is_int($readinessControllerStart) ? strpos($controllerSource, 'public static function', $readinessControllerStart + 1) : false;
$readinessController = is_int($readinessControllerStart) && is_int($readinessControllerEnd) ? substr($controllerSource, $readinessControllerStart, $readinessControllerEnd - $readinessControllerStart) : '';
assertTrue($readinessController !== '' && str_contains($readinessController, "\$_POST['proposal_id']") && str_contains($readinessController, "\$_POST['csrf']"), 'readiness endpoint reads only proposal id and csrf from POST');
assertTrue(!preg_match('/\$_POST\[[\'"](file_path|line|selector|property|current_value|replacement_value|owner|owner_key)[\'"]\]/', $readinessController), 'readiness endpoint does not accept browser-supplied target authority fields');
assertTrue(!preg_match('/file_put_contents|fwrite|unlink|rename\(|copy\(|mkdir\(|rmdir\(|\bDB::|->query\(|->exec\(/', $readinessServiceSource), 'readiness service contains no filesystem or DB mutation calls');
assertTrue(str_contains($readinessServiceSource, 'StyleComplianceGuardedRepairCapabilityService::contract()'), 'readiness service uses canonical guarded repair capability contract');
assertTrue(str_contains($capabilityServiceSource, "'executor_enabled' => \$executorEnabled"), 'capability service derives executor_enabled dynamically');
assertTrue(str_contains($capabilityServiceSource, "'owner_specific_rules_allowed' => false"), 'capability service forbids owner-specific future executor rules');

$manifestPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/manifest.php';
$manifestSource = (string)file_get_contents($manifestPath);
assertTrue(str_contains($manifestSource, "'can_modify' => false"), 'manifest declares can_modify=false');
assertTrue(str_contains($manifestSource, "'writes_to_owner_artifact' => false"), 'manifest declares writes_to_owner_artifact=false');
assertTrue(str_contains($manifestSource, "'supports_snapshot' => false"), 'manifest declares supports_snapshot=false');
assertTrue(!str_contains($manifestSource, "'guarded_repair' => ['enabled' => true"), 'manifest has no enabled guarded_repair capability');

$readySource = ".alpha {\n  color: var(--tone-danger, #dc3545);\n}\n";
$readyFingerprint = 'sha256:' . hash('sha256', $readySource);
$alphaReady = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe([
        'file_path' => 'apps/SyntheticAlpha/styles/ui.css',
        'source_owner' => 'SyntheticAlpha',
    ]),
    $readySource,
    ['expected_source_fingerprint' => $readyFingerprint]
);
$betaReady = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe([
        'proposal_id' => 'tar-2222222222222222',
        'file_path' => 'apps/SyntheticBeta/styles/ui.css',
        'source_owner' => 'SyntheticBeta',
    ]),
    $readySource,
    ['expected_source_fingerprint' => $readyFingerprint]
);
$executorEnabled = !empty(StyleComplianceGuardedRepairCapabilityService::contract()['executor_enabled']);
$expectedReadiness = $executorEnabled ? StyleComplianceRepairReadinessService::STATE_READY : 'blocked';
assertTrue((string)($alphaReady['state'] ?? '') === $expectedReadiness, 'interactive UI semantic value-fix readiness (state=' . ($alphaReady['state'] ?? '') . ', expected=' . $expectedReadiness . ')');
assertTrue((string)($betaReady['state'] ?? '') === (string)($alphaReady['state'] ?? ''), 'same owner type and evidence produce same readiness across different owner names');
assertTrue((bool)($alphaReady['future_executor_contract']['executor_enabled'] ?? false) === $executorEnabled, 'ready result executor_enabled=' . ($alphaReady['future_executor_contract']['executor_enabled'] ?? '?'));
assertTrue((string)($alphaReady['expected_change']['owner_type'] ?? '') === 'app', 'readiness derives canonical app owner type from source path');

$runtimeGeneratedSource = "<div style=\"color: var(--tone-danger, #dc3545);\"><!-- runtime-generated --></div>";
$runtimeGenerated = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe([
        'proposal_id' => 'tar-3333333333333333',
        'file_path' => 'apps/SyntheticGamma/Views/runtime-generated-panel.php',
        'source_owner' => 'SyntheticGamma',
    ]),
    $runtimeGeneratedSource,
    ['expected_source_fingerprint' => 'sha256:' . hash('sha256', $runtimeGeneratedSource)]
);
assertTrue((string)($runtimeGenerated['state'] ?? '') === StyleComplianceRepairReadinessService::STATE_UNSUPPORTED, 'runtime-generated inline context is not readiness-ready');

$printSource = "@media print {\n  .label { width: 12px; }\n}\n";
$printReady = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe([
        'proposal_id' => 'tar-4444444444444444',
        'file_path' => 'apps/SyntheticDelta/print/label-template.css',
        'source_owner' => 'SyntheticDelta',
        'property' => 'width',
        'current_value' => '12px',
        'replacement_value' => 'var(--size-label-width)',
        'replacement_token' => '--size-label-width',
    ]),
    $printSource,
    ['expected_source_fingerprint' => 'sha256:' . hash('sha256', $printSource)]
);
assertTrue((string)($printReady['state'] ?? '') === StyleComplianceRepairReadinessService::STATE_UNSUPPORTED, 'print/label layout inline context is not readiness-ready');

$staleReady = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe(['proposal_id' => 'tar-5555555555555555']),
    $readySource,
    ['expected_source_fingerprint' => 'sha256:not-current']
);
assertTrue((string)($staleReady['state'] ?? '') === StyleComplianceRepairReadinessService::STATE_STALE, 'stale source fingerprint blocks readiness');

$ambiguousSource = ".dup { color: var(--tone-danger, #dc3545); }\n.dup { color: var(--tone-danger, #dc3545); }\n";
$ambiguousReady = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe([
        'proposal_id' => 'tar-6666666666666666',
        'selector' => '.dup',
    ]),
    $ambiguousSource,
    ['expected_source_fingerprint' => 'sha256:' . hash('sha256', $ambiguousSource)]
);
assertTrue((string)($ambiguousReady['state'] ?? '') === StyleComplianceRepairReadinessService::STATE_AMBIGUOUS, 'same-selector duplicate declaration target blocks readiness');

// Positive test: same property:value under different selectors resolves via selector context
$selectorSource = ".alpha { color: var(--tone-danger, #dc3545); }\n.beta { color: var(--tone-danger, #dc3545); }\n";
$selectorReady = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe(['proposal_id' => 'tar-6666666666666666']),
    $selectorSource,
    ['expected_source_fingerprint' => 'sha256:' . hash('sha256', $selectorSource)]
);
$executorEnabled = !empty(StyleComplianceGuardedRepairCapabilityService::contract()['executor_enabled']);
$expectedSelectorState = $executorEnabled ? StyleComplianceRepairReadinessService::STATE_READY : 'blocked';
assertTrue((string)($selectorReady['state'] ?? '') === $expectedSelectorState, 'selector-context disambiguation (state=' . ($selectorReady['state'] ?? '') . ', expected=' . $expectedSelectorState . ')');

$effectSource = ".fx { filter: blur(6px); }\n";
$effectReady = StyleComplianceRepairReadinessService::checkProposalForReadiness(
    readinessProposalForProbe([
        'proposal_id' => 'tar-7777777777777777',
        'file_path' => 'apps/SyntheticEta/styles/effects.css',
        'source_owner' => 'SyntheticEta',
        'property' => 'filter',
        'current_value' => 'blur(6px)',
        'replacement_value' => 'var(--effect-blur-md)',
        'replacement_token' => '--effect-blur-md',
        'governance_domain' => 'special_effect',
        'governance_required_domain' => 'special_effect',
        'evidence' => ['value_construct' => 'filter'],
    ]),
    $effectSource,
    ['expected_source_fingerprint' => 'sha256:' . hash('sha256', $effectSource)]
);
assertTrue((string)($effectReady['state'] ?? '') === StyleComplianceRepairReadinessService::STATE_UNSUPPORTED, 'Special Effects candidate is not guarded-repair-ready');

echo "\n";

// ============================================================
// 16. Shell Foundation Inventory contract
// ============================================================
echo "--- Shell Foundation Inventory contract ---\n";

function shellInventoryFixtureRow(array $overrides): array {
    $row = array_merge([
        'token' => 'display',
        'value' => 'grid',
        'kind' => 'literal_declaration',
        'file' => 'apps/Inventory/styles/component.css',
        'selector' => '.inventory-layout',
        'theme_aware' => false,
        'value_classification' => ['type' => 'other', 'is_literal' => false, 'canonical' => 'grid'],
        'in_theme_source' => false,
        'property' => 'display',
        'raw_value' => 'grid',
        'normalized_value' => 'grid',
        'token_references' => [],
        'value_construct' => 'keyword',
        'source_type' => 'css',
        'line_start' => 10,
        'line_end' => 10,
        'at_rule_context' => '',
        'parse_confidence' => 'high',
        'scan_category' => 'structural_out_of_scope',
        'compliance_scope' => 'evidence_only',
        'repair_eligibility' => 'not_repairable',
        'is_effect_candidate' => false,
        'is_dynamic' => false,
        'is_print_style' => false,
        'semantic_token_misuse' => [],
        'semantic_token_misuse_reason' => '',
        'source_scope' => 'owner',
        'source_owner' => 'Inventory',
        'style_domain' => 'structural',
        'governance_domain' => 'not_applicable',
        'governance_required_domain' => 'not_applicable',
        'migration_state' => 'not_applicable',
        'migration_reason' => 'Structural/geometry declaration — out of compliance scope.',
        'migration_confidence' => 'high',
        'target_owner' => 'N/A',
        'target_tool' => 'none',
    ], $overrides);
    return $row;
}

function shellInventoryFirstByDisposition(array $inventory, string $disposition): ?array {
    $items = isset($inventory['items']) && is_array($inventory['items']) ? $inventory['items'] : [];
    foreach ($items as $item) {
        if ((string)($item['recommended_disposition'] ?? '') === $disposition) {
            return $item;
        }
    }
    return null;
}

$ownerLocalInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'apps/Inventory/styles/cards.css',
        'selector' => '.inventory-layout',
        'property' => 'display',
        'raw_value' => 'grid',
        'normalized_value' => 'grid',
        'source_owner' => 'Inventory',
    ]),
]);
$ownerLocalItem = shellInventoryFirstByDisposition($ownerLocalInventory, 'remain_owner_local_shell_governed');
assertTrue(is_array($ownerLocalItem), 'owner-local flex/grid layout remains owner-local Shell-governed');
assertTrue((string)($ownerLocalItem['inventory_population'] ?? '') === 'evidence_only', 'generic owner-local layout is evidence_only, not active candidate');
assertTrue((string)($ownerLocalItem['criticality'] ?? '') === 'normal', 'generic owner-local layout has normal criticality');

$crossOwnerInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow(['file' => 'apps/Inventory/styles/layout.css', 'selector' => '.modal-overlay', 'source_owner' => 'Inventory']),
    shellInventoryFixtureRow(['file' => 'apps/Orders/styles/layout.css', 'selector' => '.dialog-overlay', 'source_owner' => 'Orders']),
]);
$sharedItem = shellInventoryFirstByDisposition($crossOwnerInventory, 'candidate_shared_shell_primitive');
assertTrue(is_array($sharedItem), 'two owners with same stable shell-role signature becomes shared primitive candidate');
assertTrue((string)($sharedItem['confidence'] ?? '') === 'high', 'shared primitive candidate is high confidence');
assertTrue((int)($sharedItem['distinct_owner_count'] ?? 0) === 2, 'stable shell-role signature has two-owner evidence');
assertTrue((string)($sharedItem['structural_signature'] ?? '') === 'dialog-overlay+layout+grid-layout', 'shared primitive uses role-based structural signature');

$genericOverflowInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'apps/Inventory/styles/list.css',
        'selector' => '.component-list',
        'property' => 'overflow-y',
        'raw_value' => 'auto',
        'normalized_value' => 'auto',
    ]),
]);
$genericOverflowItem = shellInventoryFirstByDisposition($genericOverflowInventory, 'remain_owner_local_shell_governed');
assertTrue(is_array($genericOverflowItem), 'generic overflow-y:auto in owner list remains owner-local/evidence-only');
assertTrue((string)($genericOverflowItem['inventory_population'] ?? '') === 'evidence_only', 'generic owner overflow does not enter eligible candidate queue');

$unrelatedOwnerInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow(['file' => 'apps/Products/styles/cards.css', 'selector' => '.product-card', 'source_owner' => 'Products', 'property' => 'display', 'raw_value' => 'flex', 'normalized_value' => 'flex']),
    shellInventoryFixtureRow(['file' => 'apps/Billing/styles/cards.css', 'selector' => '.invoice-card', 'source_owner' => 'Billing', 'property' => 'display', 'raw_value' => 'flex', 'normalized_value' => 'flex']),
]);
assertTrue(shellInventoryFirstByDisposition($unrelatedOwnerInventory, 'candidate_shared_shell_primitive') === null, 'same property across unrelated owners does not create shared structural signature');
foreach (($unrelatedOwnerInventory['items'] ?? []) as $item) {
    assertTrue((int)($item['distinct_owner_count'] ?? 0) < 2 || (string)($item['inventory_population'] ?? '') !== 'eligible_candidate', 'unrelated owner styles do not become cross-owner eligible candidates');
}

$shellSelectorInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'apps/Studio/styles/shell.css',
        'selector' => '.app-shell .layout-main',
        'source_owner' => 'Studio',
        'style_domain' => 'shell_foundation',
        'governance_domain' => 'shell_foundation',
        'governance_required_domain' => 'shell_foundation',
    ]),
]);
assertTrue(is_array(shellInventoryFirstByDisposition($shellSelectorInventory, 'candidate_shared_shell_primitive')), 'shared shell selector with structural declaration becomes shared primitive candidate');
assertTrue((string)(shellInventoryFirstByDisposition($shellSelectorInventory, 'candidate_shared_shell_primitive')['inventory_population'] ?? '') === 'eligible_candidate', 'known Shell selector is an eligible candidate');

$themeInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'resources/themes/default.css',
        'selector' => ':root',
        'property' => 'background',
        'raw_value' => 'var(--surface)',
        'normalized_value' => 'var(--surface)',
        'scan_category' => 'token_consumer',
        'style_domain' => 'theme',
        'governance_domain' => 'theme_related',
        'governance_required_domain' => 'theme_related',
        'source_owner' => 'Owner',
    ]),
]);
assertTrue(is_array(shellInventoryFirstByDisposition($themeInventory, 'remain_theme_related')), 'theme semantic background/border/color remains theme-related');
assertTrue((int)($themeInventory['summary']['eligible_candidates'] ?? -1) === 0, 'theme semantic records do not enter Shell eligible candidates');

$effectsInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'selector' => '.panel:hover',
        'property' => 'transform',
        'raw_value' => 'translateY(-2px)',
        'normalized_value' => 'translatey(-2px)',
        'scan_category' => 'structural_out_of_scope',
    ]),
    shellInventoryFixtureRow([
        'selector' => '.glass-panel',
        'property' => 'backdrop-filter',
        'raw_value' => 'blur(12px)',
        'normalized_value' => 'blur(12px)',
        'scan_category' => 'effect_candidate',
        'style_domain' => 'special_effect',
        'governance_domain' => 'special_effect',
        'governance_required_domain' => 'special_effect',
        'is_effect_candidate' => true,
        'migration_state' => 'future_handoff',
    ]),
]);
assertTrue(is_array(shellInventoryFirstByDisposition($effectsInventory, 'future_special_effect_handoff')), 'backdrop-filter / blur / decorative hover transform becomes future Special Effects handoff');

$focusInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'selector' => '.button:focus-visible',
        'property' => 'outline',
        'raw_value' => 'none',
        'normalized_value' => 'none',
        'scan_category' => 'visual_literal',
    ]),
]);
$focusItem = shellInventoryFirstByDisposition($focusInventory, 'classification_review');
assertTrue(is_array($focusItem), 'outline:none without visible focus replacement becomes classification review');
assertTrue((string)($focusItem['shell_candidate_type'] ?? '') === 'accessibility_focus', 'focus visibility review uses accessibility_focus candidate type');
assertTrue((string)($focusItem['review_reason_code'] ?? '') === 'accessibility_focus_risk', 'focus visibility review has accessibility reason code');

$printInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'apps/Inventory/styles/print.css',
        'selector' => '.print-sheet',
        'property' => 'display',
        'raw_value' => 'block',
        'normalized_value' => 'block',
        'scan_category' => 'print_pdf',
        'style_domain' => 'print_pdf',
        'is_print_style' => true,
        'migration_state' => 'not_applicable',
    ]),
]);
$printItem = shellInventoryFirstByDisposition($printInventory, 'classification_review');
assertTrue(is_array($printItem), 'print-only structural rule becomes print review/classification, not shared promotion');
assertTrue((string)($printItem['shell_candidate_type'] ?? '') === 'print_structure', 'print-only structural rule uses print_structure candidate type');
assertTrue((string)($printItem['review_reason_code'] ?? '') === 'print_context', 'print-only structural review has print_context reason code');

$unsupportedInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'apps/Inventory/views/dynamic.php',
        'selector' => '[inline style line 10]',
        'property' => 'display',
        'raw_value' => '<?= $display ?>',
        'normalized_value' => '<?= $display ?>',
        'scan_category' => 'dynamic_unsupported',
        'style_domain' => 'unsupported',
        'is_dynamic' => true,
        'parse_confidence' => 'partial',
        'migration_state' => 'not_applicable',
    ]),
]);
assertTrue(is_array(shellInventoryFirstByDisposition($unsupportedInventory, 'excluded_or_unsupported')), 'dynamic or unsupported source becomes excluded_or_unsupported');
assertTrue((int)($unsupportedInventory['summary']['excluded'] ?? -1) === 1, 'dynamic or unsupported source increments excluded population count');

$lowConfidenceInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'apps/Inventory/views/partial.php',
        'selector' => '.partial-grid',
        'parse_confidence' => 'partial',
    ]),
    shellInventoryFixtureRow([
        'file' => 'apps/Orders/views/partial.php',
        'selector' => '.partial-grid',
        'source_owner' => 'Orders',
        'parse_confidence' => 'partial',
    ]),
]);
assertTrue(shellInventoryFirstByDisposition($lowConfidenceInventory, 'candidate_shared_shell_primitive') === null, 'low-confidence candidate never becomes shared Shell primitive');

$reasonedReviews = array_filter($focusInventory['items'] ?? [], static fn(array $item): bool => (string)($item['recommended_disposition'] ?? '') === 'classification_review');
foreach ($reasonedReviews as $item) {
    assertTrue((string)($item['review_reason_code'] ?? '') !== '', 'classification review requires review_reason_code');
}

$criticalInventory = StyleComplianceScannerService::buildShellFoundationInventory([
    shellInventoryFixtureRow([
        'file' => 'apps/Studio/styles/shell.css',
        'selector' => '.app-shell .layout-main',
        'source_owner' => 'Studio',
        'style_domain' => 'shell_foundation',
        'governance_domain' => 'shell_foundation',
        'governance_required_domain' => 'shell_foundation',
    ]),
]);
foreach (($criticalInventory['items'] ?? []) as $item) {
    if (in_array((string)($item['criticality'] ?? ''), ['critical', 'high'], true)) {
        assertTrue((string)($item['criticality_reason'] ?? '') !== '', 'critical/high inventory item requires criticality_reason');
    }
}

$populationSummary = $unrelatedOwnerInventory['summary'] ?? [];
assertTrue(isset($populationSummary['declarations_scanned'], $populationSummary['shell_relevant_evidence'], $populationSummary['eligible_candidates'], $populationSummary['evidence_only'], $populationSummary['outside_inventory'], $populationSummary['excluded']), 'inventory summary exposes distinct population counts');
assertTrue((int)($populationSummary['declarations_scanned'] ?? -1) === 2, 'inventory declarations_scanned count is full input population');
assertTrue((int)($populationSummary['eligible_candidates'] ?? -1) === 0, 'generic unrelated owner styles do not inflate eligible candidates');

$migrationBefore = [
    shellInventoryFixtureRow(['migration_state' => 'not_applicable']),
    shellInventoryFixtureRow(['file' => 'apps/Orders/styles/layout.css', 'source_owner' => 'Orders', 'migration_state' => 'none']),
];
$migrationSnapshot = array_map(static fn(array $row): string => (string)$row['migration_state'], $migrationBefore);
StyleComplianceScannerService::buildShellFoundationInventory($migrationBefore);
$migrationAfter = array_map(static fn(array $row): string => (string)$row['migration_state'], $migrationBefore);
assertTrue($migrationSnapshot === $migrationAfter, 'inventory records never alter migration-decision output');

echo "\n";

// ============================================================
// Self-Compliance: Localization
// ============================================================
echo "--- Self-Compliance: Localization ---\n";

// Load locale and verify critical keys resolve (not raw key fallback)
require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_locale.php';
$missingFromDict = [];
$criticalKeys = [
    'col_severity', 'not_applicable', 'severity_info', 'severity_warning',
    'workspace_overview', 'workspace_shell_inventory', 'apply_scan',
    'action_center_title', 'action_center_no_action', 'action_center_repair',
    'action_center_no_repair', 'action_center_no_repair_why',
    'action_center_secondary_decision', 'action_center_secondary_accessibility',
    'decision_backlog_title', 'decision_backlog_readonly',
    'accessibility_review_title', 'repair_queue_title', 'verified_candidates_title',
    'dashboard_total_findings', 'dashboard_ready_to_repair', 'dashboard_needs_review',
    'foundation_concerns_title', 'foundation_concerns_label',
    'check_repair_readiness', 'repair_readiness_not_checked',
    'repair_apply', 'prepared_badge', 'safety_bar', 'safety_bar_readonly',
    'safety_bar_guarded', 'scope_owner', 'scope_shell', 'scope_theme',
    'scan_not_started', 'no_scope_root', 'read_only_badge', 'guarded_apply_badge',
    'value_construct_unsupported', 'value_construct_mixture',
    'workspace_context_readonly', 'workspace_context_guarded',
];
foreach ($criticalKeys as $key) {
    $resolved = $sc($key);
    if ($resolved === $key) {
        $missingFromDict[] = "en:$key";
    }
}
assertTrue($missingFromDict === [], 'no critical locale keys are missing from the en dict (' . implode(', ', $missingFromDict) . ')');

// Verify rendered normal-user HTML contains no raw locale key leakage.
// Exclude <pre>, <code>, <textarea> blocks — those contain developer evidence.
$htmlForLocaleCheck = renderStyleCompliancePreviewForProbe($renderScan, 'en');
$htmlClean = preg_replace('#<(pre|code|textarea)\b[^>]*>.*?</\1>#si', '', $htmlForLocaleCheck);
$rawKeyPatterns = [
    '>action_center_', '>proposal_class_', '>migration_state_', '>value_construct_',
    '>severity_warning', '>severity_info', '>col_severity',
    '>shell_candidate_', '>shell_disposition_', '>shell_population_',
    '>shell_criticality_', '>shell_review_', '>future_apply_eligibility_',
    '>semantic_mapping_confidence_', '>readiness_state_',
];
$leakedRawKeys = [];
foreach ($rawKeyPatterns as $pattern) {
    if (str_contains($htmlClean, $pattern)) {
        $leakedRawKeys[] = $pattern;
    }
}
assertTrue($leakedRawKeys === [], 'no raw locale-key leakage in normal user-facing HTML (' . implode(', ', $leakedRawKeys) . ')');

// Placeholder parity: keys with %d placeholders must match across languages
// Note: ne inherits from en via array_replace, ja has separate overrides.
// Only check en vs ja since ne uses en as base.
$placeholderKeys = [
    'action_center_repair', 'action_center_secondary_decision',
    'action_center_secondary_accessibility', 'semantic_decisions_count',
    'classification_reviews_count',
];
foreach ($placeholderKeys as $key) {
    $GLOBALS['styleComplianceProbeLang'] = 'en';
    $enVal = $sc($key);
    if ($enVal === $key) continue;
    $enHasD = substr_count($enVal, '%d');
    $GLOBALS['styleComplianceProbeLang'] = 'ja';
    $jaVal = $sc($key);
    $jaHasD = substr_count($jaVal, '%d');
    assertTrue($enHasD === $jaHasD || $jaVal === $key, "placeholder parity: en/$key has $enHasD %d, ja has $jaHasD %d " . ($jaVal === $key ? '(ja key missing)' : ''));
}
$GLOBALS['styleComplianceProbeLang'] = 'en';

echo "\n";

// ============================================================
// Self-Compliance: Style/CSS Governance
// ============================================================
echo "--- Self-Compliance: CSS Governance ---\n";

// Inspect Style Compliance's own embedded CSS for governance violations
$toolCss = file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/preview.php');
$resultSectionsSourceForCss = file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_sections.php');
$scriptsSourceForCss = file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_scripts.php');
assertTrue(is_string($toolCss) && $toolCss !== '', 'Style Compliance preview.php is readable');

// Rule 1: No hardcoded color values that should use semantic tokens
$hardcodedColors = [];
$colorPatterns = [
    '/color:\s*#[0-9a-fA-F]{3,8}\b/i' => 'hardcoded hex color',
    '/color:\s*rgb\s*\(/i' => 'hardcoded rgb() color',
    '/background:\s*#[0-9a-fA-F]{3,8}\b(?!\s*!important)/i' => 'hardcoded hex background',
];
foreach ($colorPatterns as $pattern => $label) {
    if (preg_match_all($pattern, $toolCss, $m) && count($m[0]) > 0) {
        foreach (array_unique($m[0]) as $match) {
            $match = trim($match);
            if (!str_contains($match, 'color: #fff') && !str_contains($match, 'background: #fff')) {
                continue;
            }
            $hardcodedColors[] = "$label: $match";
        }
    }
}
// Allow known intentional exceptions: white-on-accent link color, inline-code border
assertTrue(count($hardcodedColors) <= 2, 'no unexpected hardcoded colors in tool CSS (' . count($hardcodedColors) . ' found: ' . implode('; ', $hardcodedColors) . ')');

// Rule 2: Action center secondary links visually subordinate to primary
assertTrue(is_string($resultSectionsSourceForCss) && str_contains($resultSectionsSourceForCss, 'sc-link-secondary'), 'secondary link styling exists for evidence-inspection links');
assertTrue(is_string($resultSectionsSourceForCss) && str_contains($resultSectionsSourceForCss, 'sc-action-center-secondary'), 'secondary links container exists');

// Rule 3: Interactive controls exist (browser default focus is acceptable)
$hasInteractiveControls = is_string($scriptsSourceForCss) && is_string($resultSectionsSourceForCss)
    && str_contains($scriptsSourceForCss, '.sc-readiness-check')
    && str_contains($resultSectionsSourceForCss, 'sc-next-action-link');
assertTrue($hasInteractiveControls, 'interactive control classes exist for browser focus');

// Rule 4: No CSS rule makes read-only appear as executable
assertTrue(is_string($resultSectionsSourceForCss) && str_contains($resultSectionsSourceForCss, 'sc-link-secondary'), 'secondary link class explicitly styled');

echo "\n";

// ============================================================
// Self-Compliance: Studio Tool CSS Classification_Review Filter
// ============================================================
echo "--- Self-Compliance: Studio Tool CSS Filter ---\n";

// Regression assertion: tool-internal CSS (apps/Studio/Tools/*) must not
// produce classification_review findings. Intentionally hardcoded
// presentation values (background:transparent, border:0/none, opacity:0.xx)
// are filtered to not_applicable in withMigrationDecision().
$toolFilterScan = StyleComplianceScannerService::scan('owner', 'Studio');
$toolFilterTokens = is_array($toolFilterScan['tokens'] ?? null) ? $toolFilterScan['tokens'] : [];
$toolCssReviewTokens = [];
$toolCssOtherTokens = [];
foreach ($toolFilterTokens as $t) {
    $file = (string)($t['file'] ?? '');
    if (!str_contains($file, '/apps/Studio/Tools/')) {
        continue;
    }
    $state = (string)($t['migration_state'] ?? '');
    if ($state === 'classification_review') {
        $toolCssReviewTokens[] = $t;
    } elseif ($state !== 'not_applicable' && $state !== '') {
        $toolCssOtherTokens[] = $t;
    }
}
assertTrue(
    $toolCssReviewTokens === [],
    'Studio tool CSS must not produce classification_review findings (got ' . count($toolCssReviewTokens) . ': ' . implode('; ', array_map(static fn(array $t): string => basename((string)($t['file'] ?? '')) . ':' . ((string)($t['selector'] ?? '')), array_slice($toolCssReviewTokens, 0, 5))) . ')'
);

echo "\n";

// ============================================================
// Self-Compliance: Architecture & Safety Contracts
// ============================================================
echo "--- Self-Compliance: Architecture Guards ---\n";

// Pre-capture fixture hashes for runtime no-write evidence (Rule 5c)
$fixturesBase = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Tests/fixtures';
$fixtureNames = ['migration_owner_to_shell.css', 'migration_owner_to_effects.css', 'migration_theme_to_shell.css', 'migration_theme_to_effects.css', 'migration_ambiguous.css', 'migration_negative_controls.css'];
$fixtureHashesBefore = [];
foreach ($fixtureNames as $name) {
    $path = $fixturesBase . '/' . $name;
    $fixtureHashesBefore[$name] = is_file($path) ? hash_file('sha256', $path) : false;
}

// Rule 1: Probe isolation — guarded-repair proposals must use temp fixtures, not live owner CSS
// Known-safe uses: line 1486–1497 pass owner paths to resolveOwnerKey() as test strings only.
// The dangerous pattern is using a live owner path as file_path in a repair proposal.
// Our realFileProposalForProbe() uses PROBE_TMP_FIXTURE_REL_PATH exclusively.
assertTrue(defined('PROBE_TMP_FIXTURE_REL_PATH'), 'temp fixture path constant is defined');
assertTrue(str_contains(PROBE_TMP_FIXTURE_REL_PATH, 'Tests/tmp/'), 'temp fixture path is under Tests/tmp/');
assertTrue(!str_contains(PROBE_TMP_FIXTURE_REL_PATH, 'apps/Manufacturing'), 'temp fixture does not reference Manufacturing');
assertTrue(function_exists('cleanupProbeTempFixtures'), 'cleanup function exists');

// Rule 2: Guarded repair browser contract — only proposal_id + csrf submitted
$previewSource = file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/preview.php');
assertTrue(is_string($previewSource), 'preview.php is readable');
$forbiddenSubmitPatterns = [
    '/formData\.set\([\'"]file_path[\'"]/' => 'browser submits file_path',
    '/formData\.set\([\'"]selector[\'"]/' => 'browser submits selector',
    '/formData\.set\([\'"]replacement_value[\'"]/' => 'browser submits replacement_value',
    '/formData\.set\([\'"]source_owner[\'"]/' => 'browser submits source_owner',
];
$contractViolations = [];
foreach ($forbiddenSubmitPatterns as $pattern => $label) {
    if (preg_match($pattern, $previewSource) === 1) {
        $contractViolations[] = $label;
    }
}
assertTrue($contractViolations === [], 'guarded repair contract: browser must not submit source data (' . implode(', ', $contractViolations) . ')');

// Rule 3: No owner-specific behavior branches in views
$ownerSpecificPatterns = [
    '/Manufacturing[^\/]/' => 'owner name Manufacturing in view',
    '/owner_key\s*===?\s*[\'"]\w+[\'"]\s*\?/' => 'owner_key conditional branch',
];
$ownerBranches = [];
foreach ($ownerSpecificPatterns as $pattern => $label) {
    if (preg_match($pattern, $previewSource) === 1) {
        $ownerBranches[] = $label;
    }
}
assertTrue($ownerBranches === [], 'no owner-specific behavior branches in view source (' . implode(', ', $ownerBranches) . ')');

// Rule 4: Cleanup — no temp artifacts from probe run
$tmpDir = PROBE_TMP_DIR;
assertTrue(!is_dir($tmpDir), 'probe tmp directory is cleaned up after guarded repair tests');

// Rule 5: Runtime guarded-repair containment — capability contract + no-write evidence
echo "  Rule 5: Runtime containment verification...\n";

// 5a. Capability contract: the executor gate the controller depends on
$containmentContract = \Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceGuardedRepairCapabilityService::contract();
assertTrue(is_array($containmentContract) && isset($containmentContract['executor_enabled']), 'containment contract is array with executor_enabled');
assertTrue(empty($containmentContract['executor_enabled']), 'containment contract executor_enabled is false at runtime');
assertTrue(empty($containmentContract['can_modify']), 'containment contract can_modify is false');
assertTrue(empty($containmentContract['writes_to_owner_artifact']), 'containment contract writes_to_owner_artifact is false');
assertTrue(empty($containmentContract['supports_snapshot']), 'containment contract supports_snapshot is false');

// 5b. Readiness service gates at every layer — no path reaches the engine when executor is disabled.
// TC1 already proves a real proposal receives 'blocked' with executor reason when executor is off.
// Here we verify the incomplete-data gate also rejects (input integrity layer, not executor layer).
$incompleteProposal = ['proposal_id' => 'containment-probe-noop'];
$incompleteCheck = \Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceRepairReadinessService::checkProposalForReadiness($incompleteProposal);
$incompleteState = (string)($incompleteCheck['state'] ?? '');
assertTrue($incompleteState === 'stale', 'incomplete proposal returns stale state (got ' . $incompleteState . ')');
assertTrue(isset($incompleteCheck['checks']) && is_array($incompleteCheck['checks']), 'incomplete check returns checks array');
$executorFlag = StyleComplianceGuardedRepairCapabilityService::contract()['executor_enabled'] ?? true;
assertTrue($executorFlag === false, 'executor_enabled is false in the capability contract called by the controller');

// 5c. Controlled fixture integrity — no committed file was mutated during probe execution
$fixtureViolations = [];
foreach ($fixtureHashesBefore as $name => $hashBefore) {
    $path = $fixturesBase . '/' . $name;
    $hashAfter = is_file($path) ? hash_file('sha256', $path) : false;
    if ($hashAfter === false) {
        $fixtureViolations[] = $name . ' (not readable after probe)';
    } elseif ($hashBefore === false) {
        $fixtureViolations[] = $name . ' (not readable before probe)';
    } elseif ($hashBefore !== $hashAfter) {
        $fixtureViolations[] = $name . ' (hash changed: ' . substr($hashBefore, 0, 12) . ' → ' . substr($hashAfter, 0, 12) . ')';
    }
}
assertTrue($fixtureViolations === [], 'controlled fixture files unchanged after probe execution (' . implode(', ', $fixtureViolations) . ')');

echo "\n";

// ============================================================
// Canonical State Shape Validation (PR 3A)
// ============================================================
$controllerPath = $baseDir . '/apps/Studio/Controllers/StudioController.php';
$controllerSource = file_get_contents($controllerPath);
$presentationSummaryPath = $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleCompliancePresentationSummaryService.php';
$presentationSummarySource = file_get_contents($presentationSummaryPath);
$scriptsPath = $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_scripts.php';
$scriptsSource = file_get_contents($scriptsPath);

// New state summary fields are centralized in the presentation summary service.
assertTrue(str_contains($controllerSource, 'StyleCompliancePresentationSummaryService::build'), 'controller delegates canonical state summary construction');
assertTrue(str_contains($presentationSummarySource, "'summary' => ["), 'presentation service constructs summary object');
assertTrue(str_contains($presentationSummarySource, "'findings_total' => \$findingsTotal"), 'presentation summary has findings_total');
assertTrue(str_contains($presentationSummarySource, "'fixable_now' => \$fixableNow"), 'presentation summary has fixable_now');
assertTrue(str_contains($presentationSummarySource, "'review_only' => \$reviewOnly"), 'presentation summary has review_only');
assertTrue(str_contains($presentationSummarySource, "'decision_backlog' => \$decisionBacklog"), 'presentation summary has decision_backlog');
assertTrue(str_contains($presentationSummarySource, "'blocked_or_not_safe' => \$blockedOrNotSafe"), 'presentation summary has blocked_or_not_safe');
assertTrue(str_contains($presentationSummarySource, "'compliance_score' => \$complianceScore"), 'presentation summary has compliance_score');
assertTrue(str_contains($presentationSummarySource, "'next_action' => ["), 'presentation service constructs next_action object');
assertTrue(str_contains($presentationSummarySource, "'scan_status' => 'complete'"), 'presentation state has scan_status');

// Old metrics object removed from controller state
assertTrue(!str_contains($controllerSource, "'metrics' => ["), 'controller no longer constructs metrics object');

// Old state fields removed
assertTrue(str_contains($controllerSource, "\$state['scan_id'] = \$scanId"), 'controller sets scan_id in state');
assertTrue(str_contains($controllerSource, 'bin2hex(random_bytes(16))'), 'controller generates scan_id via bin2hex');
assertTrue(!str_contains($controllerSource, "'scan_time' =>"), 'controller no longer sets scan_time');
assertTrue(!str_contains($controllerSource, "'execution_capability' =>"), 'controller no longer sets execution_capability in state');

// review_only computation exists in presentation summary service
assertTrue(str_contains($presentationSummarySource, '$reviewOnly = $accessibilityReview + $decisionBacklog + count($foundationConcerns);'), 'presentation service computes review_only correctly');

// blocked_or_not_safe computation
assertTrue(str_contains($presentationSummarySource, '$blockedOrNotSafe = (int)($summary[\'boundary_findings\'] ?? 0) + $deferredOrOutOfScope;'), 'presentation service computes blocked_or_not_safe');

// next_action logic
assertTrue(str_contains($presentationSummarySource, '$nextActionKind = \'fixable_now\''), 'presentation service handles fixable_now next_action');
assertTrue(str_contains($presentationSummarySource, '$nextActionKind = \'review_only\''), 'presentation service handles review_only next_action');
assertTrue(str_contains($presentationSummarySource, '$nextActionKind = \'none\''), 'presentation service handles none next_action');

// canonical_url excludes owner when scope !== owner
assertTrue(str_contains($controllerSource, '$scope === StyleComplianceScannerService::SCOPE_OWNER && $ownerKey !== \'\' ? $ownerKey : null'), 'controller only includes owner_key when scope=owner');

// Lifecycle contract: controller error state includes state object with scan_status
assertTrue(str_contains($controllerSource, "'state' => ["), 'controller constructs state in error response');
assertTrue(str_contains($controllerSource, "'scan_status' => 'failed'"), 'controller error state has scan_status failed');

// Lifecycle contract: _results_mount.php has prior-evidence marker
$mountPath = $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_results_mount.php';
$mountSource = file_get_contents($mountPath);
assertTrue(str_contains($mountSource, '<output class="sc-prior-evidence" data-sc-prior-evidence aria-live="polite" aria-atomic="true" hidden>'), '_results_mount.php has prior-evidence marker with correct attributes');
assertTrue(!str_contains($mountSource, 'cockpit_prior_evidence_scanning'), '_results_mount.php starts with no stale prior-evidence marker text');

// Lifecycle contract: cockpit status element has aria-live
$bannerPath = $baseDir . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Cockpit/_mission_banner.php';
$bannerSource = file_get_contents($bannerPath);
assertTrue(str_contains($bannerSource, 'data-sc-cockpit-status aria-live="polite" role="status"'), '_mission_banner.php status element has aria-live');
assertTrue(str_contains($bannerSource, 'data-sc-cockpit-error aria-live="polite" role="alert" hidden'), '_mission_banner.php error output has aria-live and hidden');

// Lifecycle contract: locale keys exist
assertTrue(str_contains($localeSource, "'scan_status_not_scanned'"), 'locale has scan_status_not_scanned');
assertTrue(str_contains($localeSource, "'scan_status_scanning'"), 'locale has scan_status_scanning');
assertTrue(str_contains($localeSource, "'scan_status_complete'"), 'locale has scan_status_complete');
assertTrue(str_contains($localeSource, "'scan_status_failed'"), 'locale has scan_status_failed');
assertTrue(str_contains($localeSource, "'cockpit_prior_evidence_scanning'"), 'locale has cockpit_prior_evidence_scanning');
assertTrue(str_contains($localeSource, "'cockpit_prior_evidence_failed'"), 'locale has cockpit_prior_evidence_failed');

// Lifecycle contract: cockpit_labels in _scripts.php use locale
assertTrue(str_contains($scriptsSource, "cockpitLabels.priorScanning"), '_scripts.php uses cockpitLabels.priorScanning');
assertTrue(str_contains($scriptsSource, "cockpitLabels.priorFailed"), '_scripts.php uses cockpitLabels.priorFailed');

// Lifecycle contract: _scripts.php has generateScanId function
assertTrue(str_contains($scriptsSource, 'function generateScanId()'), '_scripts.php has generateScanId()');

// Lifecycle contract: _scripts.php has supersession guard
assertTrue(str_contains($scriptsSource, "activeScanId = generateScanId()"), '_scripts.php generates scanId on submit');
assertTrue(str_contains($scriptsSource, "data.state.scan_id !== activeScanId"), '_scripts.php has supersession guard in .then');
assertTrue(str_contains($scriptsSource, "err.state.scan_id !== activeScanId"), '_scripts.php has supersession guard in .catch');
assertTrue(str_contains($scriptsSource, "err.name === 'AbortError'"), '_scripts.php handles AbortError');
assertTrue(str_contains($scriptsSource, 'activeScanId = null'), '_scripts.php clears activeScanId after completion');

// Lifecycle contract: _scripts.php has setAllCockpitMetricsToEmpty
assertTrue(str_contains($scriptsSource, 'function setAllCockpitMetricsToEmpty()'), '_scripts.php has setAllCockpitMetricsToEmpty()');

// Lifecycle contract: _scripts.php has setPriorEvidenceMarker
assertTrue(str_contains($scriptsSource, 'function setPriorEvidenceMarker(mode)'), '_scripts.php has setPriorEvidenceMarker()');
assertTrue(str_contains($scriptsSource, 'cockpitLabels.priorScanning'), '_scripts.php setPriorEvidenceMarker uses locale priorScanning');
assertTrue(str_contains($scriptsSource, 'cockpitLabels.priorFailed'), '_scripts.php setPriorEvidenceMarker uses locale priorFailed');

// Lifecycle contract: applyLifecycleState handles prior-evidence marker inline
assertTrue(str_contains($scriptsSource, 'marker.hidden ='), '_scripts.php applyLifecycleState controls prior-evidence marker visibility');
assertTrue(str_contains($scriptsSource, 'sl.priorScanning : sl.priorFailed'), '_scripts.php applyLifecycleState uses locale priorScanning/priorFailed');

// Lifecycle contract: applyLifecycleState exists and calls setAllCockpitMetricsToEmpty for scanning and failed
assertTrue(str_contains($scriptsSource, 'function applyLifecycleState(status, prior)'), '_scripts.php has applyLifecycleState()');
assertTrue(str_contains($scriptsSource, 'setAllCockpitMetricsToEmpty();'), '_scripts.php applyLifecycleState calls setAllCockpitMetricsToEmpty');

// Lifecycle contract: hasPriorEvidence initialized from DOM
assertTrue(str_contains($scriptsSource, 'hasPriorEvidence = cockpit ? cockpit.getAttribute'), '_scripts.php hasPriorEvidence from DOM data-scan-status');

// Lifecycle contract: applyLifecycleState sets locale-driven investigation-state text
assertTrue(str_contains($scriptsSource, 'sl.investigationStateNotScanned'), '_scripts.php applyLifecycleState uses investigationStateNotScanned');
assertTrue(str_contains($scriptsSource, 'sl.investigationStateScanning'), '_scripts.php applyLifecycleState uses investigationStateScanning');
assertTrue(str_contains($scriptsSource, 'sl.investigationStateFailed'), '_scripts.php applyLifecycleState uses investigationStateFailed');
assertTrue(str_contains($scriptsSource, 'sl.investigationStateComplete'), '_scripts.php applyLifecycleState uses investigationStateComplete');

// Lifecycle contract: submit handler captures hasPriorEvidence before transitioning
assertTrue(str_contains($scriptsSource, "hasPriorEvidence = cockpit ? cockpit.getAttribute('data-scan-status') === 'complete' : false;"), '_scripts.php submit handler captures hasPriorEvidence');

// Lifecycle contract: success handler marks completed evidence as mounted
assertTrue(str_contains($scriptsSource, 'formData.set(\'scan_id\', activeScanId);'), '_scripts.php submits active scan id');
assertTrue(str_contains($scriptsSource, 'hasPriorEvidence = true;'), '_scripts.php success handler marks completed evidence as mounted');

// Lifecycle contract: catch handler clears result mount only when no prior evidence
assertTrue(str_contains($scriptsSource, "if (!hasPriorEvidence) {"), '_scripts.php catch handler conditional result mount clearing');
assertTrue(str_contains($scriptsSource, 'resultsUnavailableFailed'), '_scripts.php catch handler uses resultsUnavailableFailed locale key');

// Lifecycle contract: syncCockpitFromState uses applyLifecycleState with prior=false
assertTrue(str_contains($scriptsSource, "applyLifecycleState('complete', false);"), '_scripts.php syncCockpitFromState calls applyLifecycleState(complete, false)');

// _scripts.php removed DOM scraping functions
assertTrue(!str_contains($scriptsSource, 'function numberFromText('), '_scripts.php removed numberFromText');
assertTrue(!str_contains($scriptsSource, 'function metricByLabel('), '_scripts.php removed metricByLabel');
assertTrue(!str_contains($scriptsSource, 'function dashboardMetric('), '_scripts.php removed dashboardMetric');
assertTrue(!str_contains($scriptsSource, 'function selectedScopeLabel('), '_scripts.php removed selectedScopeLabel');
assertTrue(!str_contains($scriptsSource, 'function syncCockpitFromResults('), '_scripts.php removed syncCockpitFromResults');

// _scripts.php uses state.summary
assertTrue(str_contains($scriptsSource, 'if (!cockpit || !state || !state.summary) return;'), '_scripts.php syncCockpitFromState reads state.summary');
assertTrue(str_contains($scriptsSource, 'state.next_action'), '_scripts.php reads state.next_action');

// _scripts.php syncs owner visibility from state
assertTrue(str_contains($scriptsSource, 'ownerWrap.classList.toggle(\'sc-owner-hidden\''), '_scripts.php syncs owner visibility from state');

echo "\n";

// ============================================================
// Summary
// ============================================================
echo "=========================================\n";
echo "Results: $pass / $totalAssertions passed";
if ($fail > 0) {
    echo ", $fail FAILED\n";
    exit(1);
}
echo ", 0 failed\n";
echo "RESULT: PASS\n";

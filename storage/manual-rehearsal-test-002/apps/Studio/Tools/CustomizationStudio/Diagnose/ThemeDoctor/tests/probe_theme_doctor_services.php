<?php

declare(strict_types=1);

/**
 * ThemeDoctor Service Probe
 *
 * Covers ThemeDoctorAnalyzer and ThemeRegistryReaderService.
 *
 * Usage: php probe_theme_doctor_services.php
 * Exit code: 0 = all passed, 1 = failure
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 6));
}

require_once __DIR__ . '/../Services/ThemeDoctorAnalyzer.php';
require_once __DIR__ . '/../Services/ThemeRegistryReaderService.php';

use Apps\Studio\Tools\CustomizationStudio\Diagnose\ThemeDoctor\Services\ThemeDoctorAnalyzer;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\ThemeDoctor\Services\ThemeRegistryReaderService;

$passed = 0;
$failed = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
    } else {
        $failed++;
        fprintf(STDERR, "FAIL: %s\n  expected: %s\n  actual:   %s\n", $label, var_export($expected, true), var_export($actual, true));
    }
}

function assert_true(bool $value, string $label): void { assert_eq(true, $value, $label); }
function assert_false(bool $value, string $label): void { assert_eq(false, $value, $label); }
function assert_count(int $expected, array $actual, string $label): void { assert_eq($expected, count($actual), $label); }
function assert_has_key(string $key, array $array, string $label): void { assert_true(array_key_exists($key, $array), $label); }

// ═══════════════════════════════════════
// ThemeDoctorAnalyzer Tests
// ═══════════════════════════════════════

$emptyRegistry = [];
$fullRegistry = [
    'approved_registry_found' => true,
    'approved_themes' => [
        ['key' => 'liquid-glass', 'label' => 'Liquid Glass', 'status' => 'approved'],
        ['key' => 'paper', 'label' => 'Paper', 'status' => 'approved'],
    ],
    'active_theme' => 'liquid-glass',
    'active_theme_source' => 'approved_registry',
    'default_theme' => 'paper',
    'default_theme_source' => 'approved_registry',
    'degraded' => false,
    'degraded_reasons' => [],
    'draft_themes' => [],
    'runtime_detected_themes' => [['key' => 'liquid-glass', 'label' => 'Liquid Glass', 'status' => 'runtime-detected']],
];

$sourceFiles = [
    ['id' => 'foundation', 'path' => 'foundation.css', 'kind' => 'foundation', 'absolute_path' => __FILE__],
    ['id' => 'light', 'path' => 'light.css', 'kind' => 'variant', 'absolute_path' => __FILE__],
    ['id' => 'dark', 'path' => 'dark.css', 'kind' => 'variant', 'absolute_path' => __FILE__],
    ['id' => 'liquid-glass', 'path' => 'liquid-glass.css', 'kind' => 'style', 'absolute_path' => __FILE__],
    ['id' => 'paper', 'path' => 'paper.css', 'kind' => 'style', 'absolute_path' => __FILE__],
];

$availableStyles = [
    'liquid-glass' => 'Liquid Glass',
    'paper' => 'Paper',
];

// TA-01: analyzer returns expected structure
$result = ThemeDoctorAnalyzer::analyze($emptyRegistry, [], []);
assert_has_key('health_score', $result, 'TA-01: health_score key');
assert_has_key('findings', $result, 'TA-01: findings key');

// TA-02: empty registry → TD001 blocked
$result = ThemeDoctorAnalyzer::analyze($emptyRegistry, [], []);
$td001 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD001'));
assert_count(1, $td001, 'TA-02: TD001 emitted for missing registry');
assert_eq('blocked', $td001[0]['severity'], 'TA-02: TD001 severity=blocked');

// TA-03: registry found but no approved themes → TD003 warning
$registryFoundEmpty = ['approved_registry_found' => true, 'approved_themes' => [], 'draft_themes' => [], 'active_theme' => '', 'default_theme' => '', 'active_theme_source' => '', 'degraded' => true, 'degraded_reasons' => ['approved_registry_empty'], 'runtime_detected_themes' => []];
$result = ThemeDoctorAnalyzer::analyze($registryFoundEmpty, [], []);
$td003 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD003'));
assert_count(1, $td003, 'TA-03: TD003 emitted for empty approved themes');
assert_eq('warning', $td003[0]['severity'], 'TA-03: TD003 severity=warning');

// TA-04: runtime fallback in degraded reasons → TD002 warning
$registryFallback = ['approved_registry_found' => false, 'approved_themes' => [], 'draft_themes' => [], 'active_theme' => 'liquid-glass', 'active_theme_source' => 'runtime_detected_fallback', 'default_theme' => 'liquid-glass', 'default_theme_source' => 'runtime_detected_fallback', 'degraded' => true, 'degraded_reasons' => ['runtime_fallback_in_use'], 'runtime_detected_themes' => [['key' => 'liquid-glass', 'label' => 'Liquid Glass', 'status' => 'runtime-detected']]];
$result = ThemeDoctorAnalyzer::analyze($registryFallback, [], []);
$td002 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD002'));
assert_count(1, $td002, 'TA-04: TD002 emitted for runtime fallback');
assert_eq('warning', $td002[0]['severity'], 'TA-04: TD002 severity=warning');

// TA-05: active theme not approved → TD004 warning
$registryActiveNotApproved = ['approved_registry_found' => true, 'approved_themes' => [['key' => 'paper', 'label' => 'Paper', 'status' => 'approved']], 'draft_themes' => [], 'active_theme' => 'navy', 'active_theme_source' => 'runtime_detected_fallback', 'default_theme' => 'paper', 'default_theme_source' => 'approved_registry', 'degraded' => false, 'degraded_reasons' => [], 'runtime_detected_themes' => []];
$result = ThemeDoctorAnalyzer::analyze($registryActiveNotApproved, [], []);
$td004 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD004'));
assert_count(1, $td004, 'TA-05: TD004 emitted for unapproved active theme');
assert_eq('warning', $td004[0]['severity'], 'TA-05: TD004 severity=warning');

// TA-06: default theme not approved → TD005 info
$registryDefaultNotApproved = ['approved_registry_found' => true, 'approved_themes' => [['key' => 'liquid-glass', 'label' => 'Liquid Glass', 'status' => 'approved']], 'draft_themes' => [], 'active_theme' => 'liquid-glass', 'active_theme_source' => 'approved_registry', 'default_theme' => 'obsidian', 'default_theme_source' => 'runtime_detected_fallback', 'degraded' => false, 'degraded_reasons' => [], 'runtime_detected_themes' => []];
$result = ThemeDoctorAnalyzer::analyze($registryDefaultNotApproved, [], []);
$td005 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD005'));
assert_count(1, $td005, 'TA-06: TD005 emitted for unapproved default theme');
assert_eq('info', $td005[0]['severity'], 'TA-06: TD005 severity=info');

// TA-07: duplicate approved keys → TD200 warning
$registryDupes = ['approved_registry_found' => true, 'approved_themes' => [['key' => 'liquid-glass', 'label' => 'LG1'], ['key' => 'liquid-glass', 'label' => 'LG2'], ['key' => 'paper', 'label' => 'Paper']], 'draft_themes' => [], 'active_theme' => 'liquid-glass', 'active_theme_source' => 'approved_registry', 'default_theme' => 'paper', 'default_theme_source' => 'approved_registry', 'degraded' => false, 'degraded_reasons' => [], 'runtime_detected_themes' => []];
$result = ThemeDoctorAnalyzer::analyze($registryDupes, $sourceFiles, $availableStyles);
$td200 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD200'));
assert_count(1, $td200, 'TA-07: TD200 emitted for duplicate key');
assert_eq('warning', $td200[0]['severity'], 'TA-07: TD200 severity=warning');

// TA-08: draft without approval → TD201 info
$registryDraftNoApproval = ['approved_registry_found' => true, 'approved_themes' => [['key' => 'liquid-glass', 'label' => 'Liquid Glass', 'status' => 'approved']], 'draft_themes' => [['key' => 'custom-dark', 'label' => 'Custom Dark', 'status' => 'draft']], 'active_theme' => 'liquid-glass', 'active_theme_source' => 'approved_registry', 'default_theme' => 'liquid-glass', 'default_theme_source' => 'approved_registry', 'degraded' => false, 'degraded_reasons' => [], 'runtime_detected_themes' => []];
$result = ThemeDoctorAnalyzer::analyze($registryDraftNoApproval, $sourceFiles, $availableStyles);
$td201 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD201'));
assert_count(1, $td201, 'TA-08: TD201 emitted for draft without approval');
assert_eq('info', $td201[0]['severity'], 'TA-08: TD201 severity=info');

// TA-09: registry refs missing theme source → TD202 warning
$registryMissingRef = ['approved_registry_found' => true, 'approved_themes' => [['key' => 'nonexistent', 'label' => 'Nope', 'status' => 'approved']], 'draft_themes' => [], 'active_theme' => 'nonexistent', 'active_theme_source' => 'approved_registry', 'default_theme' => 'nonexistent', 'default_theme_source' => 'approved_registry', 'degraded' => false, 'degraded_reasons' => [], 'runtime_detected_themes' => []];
$result = ThemeDoctorAnalyzer::analyze($registryMissingRef, $sourceFiles, $availableStyles);
$td202 = array_values(array_filter($result['findings'], fn($f) => ($f['code'] ?? '') === 'TD202'));
assert_count(1, $td202, 'TA-09: TD202 emitted for missing theme ref');
assert_eq('warning', $td202[0]['severity'], 'TA-09: TD202 severity=warning');

// TA-10: health score is valid integer 0-100
$result = ThemeDoctorAnalyzer::analyze($emptyRegistry, [], []);
assert_true($result['health_score'] >= 0 && $result['health_score'] <= 100, 'TA-10: empty registry score in 0-100 range');
assert_true($result['health_score'] < 100, 'TA-10: empty registry score < 100 (deductions from TD001)');

// TA-11: health score never below 0
$manyFindings = ['approved_registry_found' => false, 'approved_themes' => [], 'draft_themes' => [], 'active_theme' => '', 'active_theme_source' => '', 'default_theme' => '', 'default_theme_source' => '', 'degraded' => true, 'degraded_reasons' => ['runtime_fallback_in_use'], 'runtime_detected_themes' => []];
$result = ThemeDoctorAnalyzer::analyze($manyFindings, [], []);
assert_true($result['health_score'] >= 0, 'TA-11: health score never negative');

// TA-12: score decreases with more findings vs healthy base
$healthyScore = ThemeDoctorAnalyzer::analyze($fullRegistry, $sourceFiles, $availableStyles)['health_score'];
$registryMulti = ['approved_registry_found' => true, 'approved_themes' => [['key' => 'liquid-glass', 'label' => 'Liquid Glass', 'status' => 'approved']], 'draft_themes' => [['key' => 'custom-dark', 'label' => 'Custom Dark', 'status' => 'draft']], 'active_theme' => 'liquid-glass', 'active_theme_source' => 'approved_registry', 'default_theme' => 'obsidian', 'default_theme_source' => 'runtime_detected_fallback', 'degraded' => false, 'degraded_reasons' => [], 'runtime_detected_themes' => []];
$multiScore = ThemeDoctorAnalyzer::analyze($registryMulti, $sourceFiles, $availableStyles)['health_score'];
assert_true($multiScore <= $healthyScore, 'TA-12: multi-finding score <= healthy score');

// TA-13: missing token sets → TD300-TD304 info
$partialSources = [
    ['id' => 'foundation', 'path' => 'foundation.css', 'kind' => 'foundation', 'absolute_path' => __FILE__],
];
$result = ThemeDoctorAnalyzer::analyze($fullRegistry, $partialSources, $availableStyles);
$tokenSetFindings = array_values(array_filter($result['findings'], fn($f) => str_starts_with($f['code'] ?? '', 'TD30')));
assert_count(5, $tokenSetFindings, 'TA-13: 5 missing token set findings');
foreach ($tokenSetFindings as $tsf) {
    assert_eq('info', $tsf['severity'], 'TA-13: TD30x severity=info for ' . $tsf['code']);
}

// ═══════════════════════════════════════
// ThemeRegistryReaderService Tests
// ═══════════════════════════════════════

// TR-01: readSummary with empty selectors returns expected structure
$summary = ThemeRegistryReaderService::readSummary([]);
assert_has_key('approved_registry_found', $summary, 'TR-01: approved_registry_found key');
assert_has_key('active_theme', $summary, 'TR-01: active_theme key');
assert_has_key('active_theme_source', $summary, 'TR-01: active_theme_source key');
assert_has_key('default_theme', $summary, 'TR-01: default_theme key');
assert_has_key('approved_themes', $summary, 'TR-01: approved_themes key');
assert_has_key('runtime_detected_themes', $summary, 'TR-01: runtime_detected_themes key');
assert_has_key('draft_themes', $summary, 'TR-01: draft_themes key');
assert_has_key('degraded', $summary, 'TR-01: degraded key');
assert_has_key('degraded_reasons', $summary, 'TR-01: degraded_reasons key');
assert_has_key('controls', $summary, 'TR-01: controls key');

// TR-02: runtime detected themes derived from token selectors
$tokenSelectors = [
    ['theme' => 'liquid-glass', 'style' => 'base', 'tokens' => ['accent' => '#77a7ff']],
    ['theme' => 'paper', 'style' => 'base', 'tokens' => ['accent' => '#e8eefc']],
];
$summary = ThemeRegistryReaderService::readSummary($tokenSelectors);
assert_count(2, $summary['runtime_detected_themes'], 'TR-02: 2 runtime themes from selectors');
$keys = array_map(fn($t) => $t['key'], $summary['runtime_detected_themes']);
sort($keys);
assert_eq(['liquid-glass', 'paper'], $keys, 'TR-02: correct theme keys');

// TR-03: empty selectors → no runtime detected themes
$summary = ThemeRegistryReaderService::readSummary([]);
assert_count(0, $summary['runtime_detected_themes'], 'TR-03: no themes with empty selectors');

// TR-04: controls structure
assert_has_key('create', $summary['controls'], 'TR-04: controls.create key');
assert_has_key('duplicate', $summary['controls'], 'TR-04: controls.duplicate key');
assert_has_key('delete', $summary['controls'], 'TR-04: controls.delete key');
assert_has_key('set_default', $summary['controls'], 'TR-04: controls.set_default key');
assert_false($summary['controls']['create']['enabled'], 'TR-04: create disabled');
assert_false($summary['controls']['delete']['enabled'], 'TR-04: delete disabled');

// ═══════════════════════════════════════
// Summary
// ═══════════════════════════════════════

echo "\nThemeDoctor Services Probe\n";
echo "──────────────────────────\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    echo "\nSome assertions failed. Review output above.\n";
}

exit($failed > 0 ? 1 : 0);

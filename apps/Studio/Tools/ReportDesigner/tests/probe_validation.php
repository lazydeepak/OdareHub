<?php

declare(strict_types=1);

/**
 * Report Designer P1 Phase 1.2 — Validation Foundation Probe
 *
 * Usage: php probe_validation.php
 * Exit code: 0 = all passed, 1 = failure
 */

require_once __DIR__ . '/../ValueObjects/Param.php';
require_once __DIR__ . '/../ValueObjects/ExportSource.php';
require_once __DIR__ . '/../ValueObjects/ReportResource.php';
require_once __DIR__ . '/../ValueObjects/ValidationStage.php';
require_once __DIR__ . '/../ValueObjects/ValidationMessage.php';
require_once __DIR__ . '/../ValueObjects/ValidationResult.php';
require_once __DIR__ . '/../Services/ReportValidationService.php';

use Apps\Studio\Tools\ReportDesigner\ValueObjects\Param;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ExportSource;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ReportResource;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationStage;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationMessage;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationResult;
use Apps\Studio\Tools\ReportDesigner\Services\ReportValidationService;

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
function assert_null(mixed $value, string $label): void { assert_eq(null, $value, $label); }
function assert_count(int $expected, array $actual, string $label): void { assert_eq($expected, count($actual), $label); }

// Helpers
function makeValidResource(): ReportResource
{
    return new ReportResource([
        'report_key' => 'manufacturing.coverage.daily',
        'owner' => 'module',
        'title' => 'Daily Coverage',
        'permission' => 'manufacturing.coverage.view',
        'lifecycle' => 'active_only',
        'view' => 'coverage/daily',
    ]);
}

// ═══════════════════════════════════════
// ValidationStage Tests
// ═══════════════════════════════════════

// VS-01: enum values
assert_eq('declaration_parse', ValidationStage::DECLARATION_PARSE->value, 'VS-01: DECLARATION_PARSE value');
assert_eq('compilation', ValidationStage::COMPILATION->value, 'VS-01: COMPILATION value');
assert_eq('system_tools', ValidationStage::SYSTEM_TOOLS->value, 'VS-01: SYSTEM_TOOLS value');
assert_eq('runtime', ValidationStage::RUNTIME->value, 'VS-01: RUNTIME value');

// ═══════════════════════════════════════
// ValidationMessage Tests
// ═══════════════════════════════════════

// VM-01: full constructor
$vm = new ValidationMessage('V-001', 'report_key', 'Must not be empty', 'error', 'declaration_parse', '');
assert_eq('V-001', $vm->rule_id, 'VM-01: rule_id');
assert_eq('report_key', $vm->field, 'VM-01: field');
assert_eq('Must not be empty', $vm->message, 'VM-01: message');
assert_eq('error', $vm->severity, 'VM-01: severity');
assert_eq('declaration_parse', $vm->stage, 'VM-01: stage');
assert_eq('', $vm->value, 'VM-01: value');

// VM-02: null value
$vm2 = new ValidationMessage('V-002', 'report_key', 'Convention', 'warning', 'declaration_parse');
assert_null($vm2->value, 'VM-02: null value default');

// VM-03: toArray round-trip
$arr = $vm->toArray();
assert_eq('V-001', $arr['rule_id'], 'VM-03: toArray rule_id');

// ═══════════════════════════════════════
// ValidationResult Tests
// ═══════════════════════════════════════

// VR-01: passed when no errors or criticals
$vr1 = new ValidationResult([], [], []);
assert_true($vr1->passed, 'VR-01: passed with no messages');
assert_false($vr1->hasErrors(), 'VR-01: hasErrors false');
assert_false($vr1->hasCritical(), 'VR-01: hasCritical false');
assert_count(0, $vr1->allMessages(), 'VR-01: allMessages empty');

// VR-02: not passed with errors
$err = new ValidationMessage('V-001', 'report_key', 'empty', 'error', 'declaration_parse');
$vr2 = new ValidationResult([$err], [], []);
assert_false($vr2->passed, 'VR-02: not passed with errors');
assert_true($vr2->hasErrors(), 'VR-02: hasErrors true');

// VR-03: not passed with critical
$crit = new ValidationMessage('V-000', 'report_key', 'critical', 'critical', 'declaration_parse');
$vr3 = new ValidationResult([$crit], [], []);
assert_false($vr3->passed, 'VR-03: not passed with critical');
assert_true($vr3->hasCritical(), 'VR-03: hasCritical true');

// VR-04: passed with warnings only
$warn = new ValidationMessage('V-002', 'report_key', 'convention', 'warning', 'declaration_parse');
$vr4 = new ValidationResult([], [$warn], []);
assert_true($vr4->passed, 'VR-04: passed with warnings');
assert_false($vr4->hasErrors(), 'VR-04: hasErrors false with warnings');

// VR-05: forField filtering
$vr5 = new ValidationResult([$err], [$warn], []);
assert_count(2, $vr5->forField('report_key'), 'VR-05: forField report_key');
assert_count(0, $vr5->forField('title'), 'VR-05: forField title (none)');

// VR-06: forSeverity filtering
assert_count(1, $vr5->forSeverity('error'), 'VR-06: forSeverity error');
assert_count(1, $vr5->forSeverity('warning'), 'VR-06: forSeverity warning');
assert_count(0, $vr5->forSeverity('info'), 'VR-06: forSeverity info (none)');

// VR-07: toArray
$arr = $vr5->toArray();
assert_false($arr['passed'], 'VR-07: toArray passed');
assert_count(1, $arr['errors'], 'VR-07: toArray errors');
assert_count(1, $arr['warnings'], 'VR-07: toArray warnings');
assert_count(0, $arr['info'], 'VR-07: toArray info');

// ═══════════════════════════════════════
// V-001: report_key presence
// ═══════════════════════════════════════

$svc = new ReportValidationService();
$valid1 = makeValidResource();
$result = $svc->validate($valid1);
assert_true($result->passed, 'V-001: valid resource passes');

$invalid1 = new ReportResource(['owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x']);
$msgs = $svc->checkReportKeyPresence($invalid1);
assert_count(1, $msgs, 'V-001: empty report_key');
assert_eq('V-001', $msgs[0]->rule_id, 'V-001: rule_id');
assert_eq('error', $msgs[0]->severity, 'V-001: severity');

// ═══════════════════════════════════════
// V-002: report_key convention
// ═══════════════════════════════════════

$msgs = $svc->checkReportKeyConvention($valid1);
assert_count(0, $msgs, 'V-002: valid report_key convention');

$badKey = new ReportResource(['report_key' => 'badkey', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x']);
$msgs = $svc->checkReportKeyConvention($badKey);
assert_count(1, $msgs, 'V-002: invalid convention');
assert_eq('warning', $msgs[0]->severity, 'V-002: severity warning');

// V-002: empty key skips check
$emptyKey = new ReportResource(['owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x']);
$msgs = $svc->checkReportKeyConvention($emptyKey);
assert_count(0, $msgs, 'V-002: empty key skips convention check');

// ═══════════════════════════════════════
// V-003: owner valid
// ═══════════════════════════════════════

$msgs = $svc->checkOwner($valid1);
assert_count(0, $msgs, 'V-003: valid owner');

$badOwner = new ReportResource(['report_key' => 'x', 'owner' => 'wrong', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x']);
$msgs = $svc->checkOwner($badOwner);
assert_count(1, $msgs, 'V-003: invalid owner');
assert_eq('error', $msgs[0]->severity, 'V-003: severity error');

// ═══════════════════════════════════════
// V-004: title presence
// ═══════════════════════════════════════

$msgs = $svc->checkTitle($valid1);
assert_count(0, $msgs, 'V-004: valid title');

$noTitle = new ReportResource(['report_key' => 'x', 'owner' => 'app', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x']);
$msgs = $svc->checkTitle($noTitle);
assert_count(1, $msgs, 'V-004: empty title');

// ═══════════════════════════════════════
// V-005: permission presence
// ═══════════════════════════════════════

$msgs = $svc->checkPermission($valid1);
assert_count(0, $msgs, 'V-005: valid permission');

$noPerm = new ReportResource(['report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'lifecycle' => 'always', 'view' => 'x']);
$msgs = $svc->checkPermission($noPerm);
assert_count(1, $msgs, 'V-005: empty permission');

// ═══════════════════════════════════════
// V-006: lifecycle valid
// ═══════════════════════════════════════

$msgs = $svc->checkLifecycle($valid1);
assert_count(0, $msgs, 'V-006: valid lifecycle');

$badLifecycle = new ReportResource(['report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'invalid', 'view' => 'x']);
$msgs = $svc->checkLifecycle($badLifecycle);
assert_count(1, $msgs, 'V-006: invalid lifecycle');

// ═══════════════════════════════════════
// V-007: view presence
// ═══════════════════════════════════════

$msgs = $svc->checkView($valid1);
assert_count(0, $msgs, 'V-007: valid view');

$noView = new ReportResource(['report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always']);
$msgs = $svc->checkView($noView);
assert_count(1, $msgs, 'V-007: empty view');

// ═══════════════════════════════════════
// V-008: parameter types
// ═══════════════════════════════════════

$goodParams = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'parameters' => [
        ['key' => 'date', 'type' => 'date', 'label' => 'Date'],
        ['key' => 'mode', 'type' => 'select', 'label' => 'Mode'],
    ],
]);
$msgs = $svc->checkParameterTypes($goodParams);
assert_count(0, $msgs, 'V-008: valid parameter types');

$badParams = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'parameters' => [
        ['key' => 'bad', 'type' => 'invalid_type', 'label' => 'Bad'],
    ],
]);
$msgs = $svc->checkParameterTypes($badParams);
assert_count(1, $msgs, 'V-008: invalid parameter type');
assert_eq('error', $msgs[0]->severity, 'V-008: severity error');

// ═══════════════════════════════════════
// V-009: parameter key uniqueness
// ═══════════════════════════════════════

$msgs = $svc->checkParameterKeyUniqueness($goodParams);
assert_count(0, $msgs, 'V-009: unique keys');

$dupParams = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'parameters' => [
        ['key' => 'dup', 'type' => 'text', 'label' => 'First'],
        ['key' => 'dup', 'type' => 'text', 'label' => 'Second'],
    ],
]);
$msgs = $svc->checkParameterKeyUniqueness($dupParams);
assert_count(1, $msgs, 'V-009: duplicate keys');

// ═══════════════════════════════════════
// V-010: view path exists
// ═══════════════════════════════════════

// Without moduleDir, check is skipped
$msgs = $svc->checkViewPathExists($valid1);
assert_count(0, $msgs, 'V-010: no moduleDir skips check');

// With moduleDir pointing to non-existent file
$msgs = $svc->checkViewPathExists($valid1, ['moduleDir' => '/nonexistent/path']);
assert_count(1, $msgs, 'V-010: view path not found');
assert_eq('error', $msgs[0]->severity, 'V-010: severity error');

// ═══════════════════════════════════════
// V-011: export view path exists
// ═══════════════════════════════════════

// No export_view set => skip
$msgs = $svc->checkExportViewPathExists($valid1, ['moduleDir' => '/nonexistent']);
assert_count(0, $msgs, 'V-011: no export_view skips');

// With export_view but no moduleDir => skip
$withExport = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'export_view' => 'some/export',
]);
$msgs = $svc->checkExportViewPathExists($withExport);
assert_count(0, $msgs, 'V-011: no moduleDir skips');

// With moduleDir and non-existent file
$msgs = $svc->checkExportViewPathExists($withExport, ['moduleDir' => '/nonexistent']);
assert_count(1, $msgs, 'V-011: export view not found');
assert_eq('warning', $msgs[0]->severity, 'V-011: severity warning');

// ═══════════════════════════════════════
// V-012: PDF view paths exist
// ═══════════════════════════════════════

// No pdf_views => skip
$msgs = $svc->checkPdfViewPathsExist($valid1, ['moduleDir' => '/nonexistent']);
assert_count(0, $msgs, 'V-012: no pdf_views skips');

// With pdf_views but no moduleDir => skip
$withPdf = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'pdf_views' => ['coverage/pdf1', 'coverage/pdf2'],
]);
$msgs = $svc->checkPdfViewPathsExist($withPdf);
assert_count(0, $msgs, 'V-012: no moduleDir skips');

// With moduleDir and non-existent files
$msgs = $svc->checkPdfViewPathsExist($withPdf, ['moduleDir' => '/nonexistent']);
assert_count(2, $msgs, 'V-012: 2 pdf views not found');
assert_eq('warning', $msgs[0]->severity, 'V-012: severity warning');

// ═══════════════════════════════════════
// V-013: export source table
// ═══════════════════════════════════════

// No export_sources => skip
$msgs = $svc->checkExportSourceTable($valid1);
assert_count(0, $msgs, 'V-013: no export_sources skips');

// With valid sources
$withSources = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'export_sources' => [['table' => 'valid_table']],
]);
$msgs = $svc->checkExportSourceTable($withSources);
assert_count(0, $msgs, 'V-013: valid sources');

// With empty table
$badSources = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'export_sources' => [['table' => '']],
]);
$msgs = $svc->checkExportSourceTable($badSources);
assert_count(1, $msgs, 'V-013: empty table');
assert_eq('error', $msgs[0]->severity, 'V-013: severity error');

// ═══════════════════════════════════════
// V-014: export formats
// ═══════════════════════════════════════

// No export_formats => skip
$msgs = $svc->checkExportFormats($valid1);
assert_count(0, $msgs, 'V-014: no export_formats skips');

// Valid formats
$goodFormats = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'export_formats' => ['csv', 'pdf', 'xlsx'],
]);
$msgs = $svc->checkExportFormats($goodFormats);
assert_count(0, $msgs, 'V-014: valid formats');

// Invalid formats
$badFormats = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'export_formats' => ['csv', 'html', 'xml'],
]);
$msgs = $svc->checkExportFormats($badFormats);
assert_count(2, $msgs, 'V-014: 2 invalid formats');
assert_eq('warning', $msgs[0]->severity, 'V-014: severity warning');

// ═══════════════════════════════════════
// V-015: description safety
// ═══════════════════════════════════════

// Empty description => skip
$msgs = $svc->checkDescriptionSafety($valid1);
assert_count(0, $msgs, 'V-015: empty description skips');

// Safe description
$safeDesc = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'description' => 'Just plain text with no HTML',
]);
$msgs = $svc->checkDescriptionSafety($safeDesc);
assert_count(0, $msgs, 'V-015: safe description');

// Unsafe description with HTML
$unsafeDesc = new ReportResource([
    'report_key' => 'x', 'owner' => 'app', 'title' => 'x', 'permission' => 'x', 'lifecycle' => 'always', 'view' => 'x',
    'description' => 'Some <b>bold</b> text',
]);
$msgs = $svc->checkDescriptionSafety($unsafeDesc);
assert_count(1, $msgs, 'V-015: HTML in description');
assert_eq('warning', $msgs[0]->severity, 'V-015: severity warning');

// ═══════════════════════════════════════
// Full validate() integration
// ═══════════════════════════════════════

// Fully valid resource
$result = $svc->validate($valid1);
assert_true($result->passed, 'INTEGRATION: valid resource passes');
assert_count(0, $result->errors, 'INTEGRATION: zero errors');
assert_count(0, $result->warnings, 'INTEGRATION: zero warnings');

// Resource with multiple issues
$multiIssue = new ReportResource([
    'owner' => 'wrong',
    'title' => 'OK',
    'lifecycle' => 'invalid',
    'view' => 'coverage/daily',
    'export_formats' => ['html', 'xml'],
]);
$result = $svc->validate($multiIssue);
assert_false($result->passed, 'INTEGRATION: multiple issues fails');
assert_true($result->hasErrors(), 'INTEGRATION: has errors');
assert_count(4, $result->errors, 'INTEGRATION: 4 errors (report_key, owner, permission, lifecycle)');
assert_count(2, $result->warnings, 'INTEGRATION: 2 warnings (html, xml formats)');

// Validate with export format warnings
$withWarnings = new ReportResource([
    'report_key' => 'app.module.report',
    'owner' => 'app',
    'title' => 'Warnings',
    'permission' => 'x',
    'lifecycle' => 'always',
    'view' => 'some/view',
    'export_formats' => ['html'],
]);
$result = $svc->validate($withWarnings);
assert_true($result->passed, 'INTEGRATION: warnings only passes');
assert_count(0, $result->errors, 'INTEGRATION: zero errors with warning-only');
assert_count(1, $result->warnings, 'INTEGRATION: warning present');

// ═══════════════════════════════════════
// validateStage()
// ═══════════════════════════════════════

$stageResult = $svc->validateStage($valid1, ValidationStage::DECLARATION_PARSE);
assert_true($stageResult->passed, 'STAGE: declaration_parse passes on valid');

$stageResult2 = $svc->validateStage($multiIssue, ValidationStage::DECLARATION_PARSE);
assert_false($stageResult2->passed, 'STAGE: declaration_parse fails on invalid');

$stageResult3 = $svc->validateStage($valid1, ValidationStage::SYSTEM_TOOLS);
assert_true($stageResult3->passed, 'STAGE: system_tools passes (empty)');
assert_count(0, $stageResult3->allMessages(), 'STAGE: system_tools has no messages');

$stageResult4 = $svc->validateStage($valid1, ValidationStage::RUNTIME);
assert_true($stageResult4->passed, 'STAGE: runtime passes (empty)');

// ═══════════════════════════════════════
// Summary
// ═══════════════════════════════════════

$total = $passed + $failed;
fprintf(STDERR, "\nResults: %d/%d passed, %d failed\n", $passed, $total, $failed);
exit($failed > 0 ? 1 : 0);

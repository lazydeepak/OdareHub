<?php

declare(strict_types=1);

/**
 * Report Designer P1 Phase 1.3 — Compilation Foundation Probe
 *
 * Usage: php probe_compilation.php
 * Exit code: 0 = all passed, 1 = failure
 */

require_once __DIR__ . '/../ValueObjects/Param.php';
require_once __DIR__ . '/../ValueObjects/ExportSource.php';
require_once __DIR__ . '/../ValueObjects/ReportResource.php';
require_once __DIR__ . '/../ValueObjects/ValidationStage.php';
require_once __DIR__ . '/../ValueObjects/ValidationMessage.php';
require_once __DIR__ . '/../ValueObjects/ValidationResult.php';
require_once __DIR__ . '/../ValueObjects/NavigationContract.php';
require_once __DIR__ . '/../ValueObjects/RenderContract.php';
require_once __DIR__ . '/../ValueObjects/ResolvedReportContract.php';
require_once __DIR__ . '/../ValueObjects/CompilationResult.php';
require_once __DIR__ . '/../Services/ReportValidationService.php';
require_once __DIR__ . '/../Services/ReportCompilationService.php';

use Apps\Studio\Tools\ReportDesigner\ValueObjects\NavigationContract;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\RenderContract;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ResolvedReportContract;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\CompilationResult;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ReportResource;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\Param;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ExportSource;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationResult;
use Apps\Studio\Tools\ReportDesigner\Services\ReportCompilationService;

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

function validResource(): ReportResource
{
    return new ReportResource([
        'report_key' => 'manufacturing.coverage.daily',
        'owner' => 'module',
        'owner_app' => 'Manufacturing',
        'owner_module' => 'Coverage',
        'module_dir' => '/apps/Manufacturing/modules/Coverage',
        'title' => 'Daily Coverage',
        'description' => 'Daily coverage report data',
        'category' => 'analytical',
        'visualization' => 'chart',
        'permission' => 'manufacturing.coverage.view',
        'lifecycle' => 'installed',
        'view' => 'coverage/daily',
        'export_view' => 'coverage/daily_export',
        'pdf_views' => ['coverage/daily_pdf'],
        'parameters' => [
            ['key' => 'date', 'type' => 'date', 'label' => 'Date'],
            ['key' => 'shift', 'type' => 'select', 'label' => 'Shift', 'options' => ['A', 'B']],
        ],
        'export_formats' => ['csv', 'pdf'],
        'export_sources' => [
            ['table' => 'coverage_daily', 'order_by' => 'date', 'direction' => 'DESC'],
        ],
        'scope' => 'factory_floor',
    ]);
}

function validData(): array
{
    return [
        'report_key' => 'manufacturing.coverage.daily',
        'owner' => 'module',
        'owner_app' => 'Manufacturing',
        'owner_module' => 'Coverage',
        'module_dir' => '/apps/Manufacturing/modules/Coverage',
        'title' => 'Daily Coverage',
        'permission' => 'manufacturing.coverage.view',
        'lifecycle' => 'installed',
        'view' => 'coverage/daily',
        'export_view' => 'coverage/daily_export',
        'pdf_views' => ['coverage/daily_pdf'],
        'parameters' => [
            ['key' => 'date', 'type' => 'date', 'label' => 'Date'],
            ['key' => 'shift', 'type' => 'select', 'label' => 'Shift', 'options' => ['A', 'B']],
        ],
        'export_formats' => ['csv', 'pdf'],
        'export_sources' => [
            ['table' => 'coverage_daily', 'order_by' => 'date', 'direction' => 'DESC'],
        ],
        'scope' => 'factory_floor',
    ];
}

$r = validResource();

// ═══════════════════════════════════════
// NavigationContract
// ═══════════════════════════════════════

// NC-01: fields from ReportResource
$nc = new NavigationContract($r);
assert_eq('manufacturing.coverage.daily', $nc->report_key, 'NC-01: report_key');
assert_eq('Daily Coverage', $nc->title, 'NC-01: title');
assert_eq('manufacturing.coverage.view', $nc->permission, 'NC-01: permission');
assert_eq('installed', $nc->lifecycle, 'NC-01: lifecycle');
assert_eq('analytical', $nc->category, 'NC-01: category');
assert_eq('chart', $nc->visualization, 'NC-01: visualization');

// NC-02: boundary — navigation never gets view, module_dir, export_sources, parameters
$navArr = $nc->toArray();
assert_true(!isset($navArr['view']), 'NC-02: nav omits view');
assert_true(!isset($navArr['module_dir']), 'NC-02: nav omits module_dir');
assert_true(!isset($navArr['export_sources']), 'NC-02: nav omits export_sources');
assert_true(!isset($navArr['parameters']), 'NC-02: nav omits parameters');

// ═══════════════════════════════════════
// RenderContract
// ═══════════════════════════════════════

// RC-01: with resolution data
$rc = new RenderContract($r, ['moduleDir' => '/resolved/path']);
assert_eq('manufacturing.coverage.daily', $rc->report_key, 'RC-01: report_key');
assert_eq('coverage/daily', $rc->view, 'RC-01: view');
assert_eq('/resolved/path', $rc->module_dir, 'RC-01: module_dir from resolution');
assert_eq('/resolved/path/Views/coverage/daily.php', $rc->resolved_view_path, 'RC-01: resolved_view_path');
assert_count(2, $rc->parameters, 'RC-01: parameters count');
assert_true($rc->parameters[0] instanceof Param, 'RC-01: parameters[0] is Param');

// RC-02: fallback to resource module_dir
$rc2 = new RenderContract($r, []);
assert_eq($r->module_dir, $rc2->module_dir, 'RC-02: fallback to resource module_dir');

// RC-03: render omits export fields
$renderArr = $rc->toArray();
assert_true(!isset($renderArr['export_formats']), 'RC-03: render omits export_formats');
assert_true(!isset($renderArr['export_sources']), 'RC-03: render omits export_sources');
assert_true(!isset($renderArr['pdf_views']), 'RC-03: render omits pdf_views');

// ═══════════════════════════════════════
// ResolvedReportContract
// ═══════════════════════════════════════

// RR-01: construction with resolution data
$rr = new ResolvedReportContract($r, ['moduleDir' => '/resolved/app', 'ownerApp' => 'ResolvedApp']);
assert_true($rr->resource instanceof ReportResource, 'RR-01: resource accessor');
assert_eq('/resolved/app', $rr->resolved_module_dir, 'RR-01: resolved_module_dir');
assert_eq('ResolvedApp', $rr->resolved_owner_app, 'RR-01: resolved_owner_app');
assert_eq('/resolved/app/Views/coverage/daily.php', $rr->resolved_view_path, 'RR-01: resolved_view_path');
assert_false($rr->view_exists, 'RR-01: view_exists false (P1)');
assert_count(1, $rr->resolved_export_sources, 'RR-01: resolved_export_sources count');

// RR-02: fallback to resource defaults
$rr2 = new ResolvedReportContract($r, []);
assert_eq($r->module_dir, $rr2->resolved_module_dir, 'RR-02: fallback module_dir');
assert_eq($r->owner_app, $rr2->resolved_owner_app, 'RR-02: fallback owner_app');

// RR-03: forNavigation boundary
$nav = $rr->forNavigation();
assert_eq('manufacturing.coverage.daily', $nav['report_key'], 'RR-03: nav report_key');
assert_eq('Daily Coverage', $nav['title'], 'RR-03: nav title');
assert_true(!isset($nav['view']), 'RR-03: nav omits view');
assert_true(!isset($nav['module_dir']), 'RR-03: nav omits module_dir');
assert_true(!isset($nav['export_sources']), 'RR-03: nav omits export_sources');
assert_true(!isset($nav['parameters']), 'RR-03: nav omits parameters');

// RR-04: forRender boundary
$render = $rr->forRender();
assert_eq('coverage/daily', $render['view'], 'RR-04: render has view');
assert_eq('/resolved/app', $render['module_dir'], 'RR-04: render has module_dir');
assert_true(!isset($render['export_formats']), 'RR-04: render omits export_formats');
assert_true(!isset($render['export_sources']), 'RR-04: render omits export_sources');
assert_true(!isset($render['pdf_views']), 'RR-04: render omits pdf_views');
assert_eq('factory_floor', $render['scope'], 'RR-04: render has scope');

// RR-05: forExport boundary
$export = $rr->forExport();
assert_eq('manufacturing.coverage.daily', $export['report_key'], 'RR-05: export report_key');
assert_eq(['csv', 'pdf'], $export['export_formats'], 'RR-05: export export_formats');
assert_count(1, $export['export_sources'], 'RR-05: export export_sources');
assert_true(!isset($export['view']), 'RR-05: export omits view');
assert_true(!isset($export['title']), 'RR-05: export omits title');
assert_true(!isset($export['parameters']), 'RR-05: export omits parameters');

// RR-06: toArray
$arr = $rr->toArray();
assert_eq('/resolved/app', $arr['resolved_module_dir'], 'RR-06: toArray resolved_module_dir');
assert_true(isset($arr['navigation']), 'RR-06: toArray has navigation');
assert_true(isset($arr['render']), 'RR-06: toArray has render');
assert_true(isset($arr['export']), 'RR-06: toArray has export');

// ═══════════════════════════════════════
// CompilationResult
// ═══════════════════════════════════════

// CR-01: success result
$successVal = new ValidationResult([], [], []);
$cr = new CompilationResult(true, $r, $rr, $successVal);
assert_true($cr->succeeded, 'CR-01: succeeded');
assert_true($cr->resource === $r, 'CR-01: resource ref');
assert_true($cr->contract === $rr, 'CR-01: contract ref');
assert_true($cr->validation->passed, 'CR-01: validation passed');

// CR-02: failure result
$errVal = new ValidationResult(
    [new \Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationMessage('V-001', 'report_key', 'empty', 'error', 'declaration_parse')],
    [],
    [],
);
$cr2 = new CompilationResult(false, null, null, $errVal);
assert_false($cr2->succeeded, 'CR-02: not succeeded');
assert_null($cr2->resource, 'CR-02: resource null');
assert_null($cr2->contract, 'CR-02: contract null');
assert_false($cr2->validation->passed, 'CR-02: validation not passed');

// CR-03: toArray round-trip
$arr = $cr->toArray();
assert_true($arr['succeeded'], 'CR-03: toArray succeeded');
assert_true(isset($arr['resource']), 'CR-03: toArray resource');
assert_true(isset($arr['contract']), 'CR-03: toArray contract');
assert_true(isset($arr['validation']), 'CR-03: toArray validation');

$arr2 = $cr2->toArray();
assert_false($arr2['succeeded'], 'CR-03: toArray not succeeded');
assert_null($arr2['resource'], 'CR-03: toArray resource null');
assert_null($arr2['contract'], 'CR-03: toArray contract null');

// ═══════════════════════════════════════
// ReportCompilationService
// ═══════════════════════════════════════

$svc = new ReportCompilationService();

// CS-01: compile valid data
$result = $svc->compile(validData());
assert_true($result->succeeded, 'CS-01: compile valid succeeded');
assert_true($result->resource instanceof ReportResource, 'CS-01: resource is ReportResource');
assert_true($result->contract instanceof ResolvedReportContract, 'CS-01: contract is ResolvedReportContract');
assert_true($result->validation->passed, 'CS-01: validation passed');

// CS-02: compile with simple data (no moduleDir to avoid V-010)
$result = $svc->compile([
    'report_key' => 'app.module.custom',
    'owner' => 'module',
    'title' => 'Custom',
    'permission' => 'x',
    'lifecycle' => 'always',
    'view' => 'custom/view',
]);
assert_true($result->succeeded, 'CS-02: compile with minimal valid data');
assert_true($result->resource instanceof ReportResource, 'CS-02: resource present');
assert_true($result->contract instanceof ResolvedReportContract, 'CS-02: contract present');

// CS-02b: resolve with options (options propagated to contract)
$resolved = $svc->resolve(validResource(), ['moduleDir' => '/custom/path', 'ownerApp' => 'CustomApp']);
assert_eq('/custom/path', $resolved->resolved_module_dir, 'CS-02b: resolve module_dir from options');
assert_eq('CustomApp', $resolved->resolved_owner_app, 'CS-02b: resolve owner_app from options');

// CS-03: compile invalid data
$result = $svc->compile([]);
assert_false($result->succeeded, 'CS-03: compile empty fails');
assert_null($result->resource, 'CS-03: resource null on fail');
assert_null($result->contract, 'CS-03: contract null on fail');
assert_false($result->validation->passed, 'CS-03: validation not passed');

// CS-04: compile with missing required fields
$result = $svc->compile(['title' => 'Only Title']);
assert_false($result->succeeded, 'CS-04: missing required fields fails');
assert_count(3, $result->validation->errors, 'CS-04: 3 errors (report_key, permission, view)');

// CS-05: compile with warnings (still passes)
$result = $svc->compile([
    'report_key' => 'badkey',
    'owner' => 'app',
    'title' => 'Warn Test',
    'permission' => 'x',
    'lifecycle' => 'always',
    'view' => 'some/view',
    'export_formats' => ['html'],
]);
assert_true($result->succeeded, 'CS-05: warnings still passes');
assert_count(0, $result->validation->errors, 'CS-05: zero errors');
assert_true(count($result->validation->warnings) >= 1, 'CS-05: at least 1 warning');

// CS-06: validateOnly
$val = $svc->validateOnly([]);
assert_false($val->passed, 'CS-06: validateOnly empty fails');

$val = $svc->validateOnly(validData());
assert_true($val->passed, 'CS-06: validateOnly valid passes');

// CS-07: resolve
$resolved = $svc->resolve(validResource());
assert_true($resolved instanceof ResolvedReportContract, 'CS-07: resolve returns contract');
assert_eq('manufacturing.coverage.daily', $resolved->resource->report_key, 'CS-07: resource preserved');

$resolved2 = $svc->resolve(validResource(), ['moduleDir' => '/override']);
assert_eq('/override', $resolved2->resolved_module_dir, 'CS-07: resolve with options');

// CS-08: deterministic compilation
$r1 = $svc->compile(validData());
$r2 = $svc->compile(validData());
assert_eq($r1->succeeded, $r2->succeeded, 'CS-08: deterministic succeeded');
assert_eq($r1->resource?->report_key, $r2->resource?->report_key, 'CS-08: deterministic resource');

// CS-09: default validator injected
$svc2 = new ReportCompilationService();
$result = $svc2->compile([]);
assert_false($result->succeeded, 'CS-09: default validator works');

// CS-10: consumer contract separation
$result = $svc->compile(validData());
$navArr = $result->contract->forNavigation();
assert_true(!isset($navArr['view']), 'CS-10: nav omits view');
assert_true(!isset($navArr['parameters']), 'CS-10: nav omits parameters');

$renderArr = $result->contract->forRender();
assert_true(isset($renderArr['view']), 'CS-10: render has view');
assert_true(isset($renderArr['parameters']), 'CS-10: render has parameters');
assert_true(!isset($renderArr['export_formats']), 'CS-10: render omits export_formats');

$exportArr = $result->contract->forExport();
assert_true(isset($exportArr['export_formats']), 'CS-10: export has export_formats');
assert_true(!isset($exportArr['view']), 'CS-10: export omits view');
assert_true(!isset($exportArr['title']), 'CS-10: export omits title');

// ═══════════════════════════════════════
// Summary
// ═══════════════════════════════════════

$total = $passed + $failed;
fprintf(STDERR, "\nResults: %d/%d passed, %d failed\n", $passed, $total, $failed);
exit($failed > 0 ? 1 : 0);

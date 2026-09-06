<?php

declare(strict_types=1);

/**
 * Report Designer P1 Phase 1.1 — Value Objects Probe
 *
 * Usage: php probe_value_objects.php
 * Exit code: 0 = all passed, 1 = failure
 */

require_once __DIR__ . '/../ValueObjects/Param.php';
require_once __DIR__ . '/../ValueObjects/ExportSource.php';
require_once __DIR__ . '/../ValueObjects/ReportResource.php';

use Apps\Studio\Tools\ReportDesigner\ValueObjects\Param;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ExportSource;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ReportResource;

$passed = 0;
$failed = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
    } else {
        $failed++;
        $expectedStr = var_export($expected, true);
        $actualStr = var_export($actual, true);
        fprintf(STDERR, "FAIL: %s\n  expected: %s\n  actual:   %s\n", $label, $expectedStr, $actualStr);
    }
}

function assert_true(bool $value, string $label): void
{
    assert_eq(true, $value, $label);
}

function assert_false(bool $value, string $label): void
{
    assert_eq(false, $value, $label);
}

function assert_null(mixed $value, string $label): void
{
    assert_eq(null, $value, $label);
}

function assert_throws(callable $fn, string $label): void
{
    global $passed, $failed;
    try {
        $fn();
        $failed++;
        fprintf(STDERR, "FAIL: %s — expected exception\n", $label);
    } catch (\Throwable $e) {
        $passed++;
    }
}

// ═══════════════════════════════════════
// Param Tests
// ═══════════════════════════════════════

// P-01: Param constructor with full data
$p = new Param(['key' => 'date_from', 'type' => 'date', 'label' => 'From Date', 'required' => true]);
assert_eq('date_from', $p->key, 'P-01: Param key');
assert_eq('date', $p->type, 'P-01: Param type');
assert_eq('From Date', $p->label, 'P-01: Param label');
assert_true($p->required, 'P-01: Param required');
assert_eq([], $p->options, 'P-01: Param options default');
assert_null($p->default, 'P-01: Param default default');

// P-02: Param constructor with explicit default
$p2 = new Param(['key' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['A', 'B'], 'default' => 'A']);
assert_eq('select', $p2->type, 'P-02: Param select type');
assert_eq(['A', 'B'], $p2->options, 'P-02: Param options');
assert_eq('A', $p2->default, 'P-02: Param default value');

// P-03: Param constructor default type
$p3 = new Param(['key' => 'q', 'label' => 'Query']);
assert_eq('text', $p3->type, 'P-03: Param default type');

// P-04: Param fromArray returns null on empty key
assert_null(Param::fromArray(['type' => 'text', 'label' => 'No Key']), 'P-04: fromArray null on empty key');

// P-05: Param fromArray returns object on valid
$p5 = Param::fromArray(['key' => 'ok', 'label' => 'OK']);
assert_true($p5 instanceof Param, 'P-05: fromArray returns Param');
if ($p5) {
    assert_eq('ok', $p5->key, 'P-05: Param fromArray key');
}

// ═══════════════════════════════════════
// ExportSource Tests
// ═══════════════════════════════════════

// ES-01: ExportSource constructor with full data
$es = new ExportSource(['table' => 'orders', 'order_by' => 'created_at', 'direction' => 'DESC', 'filename_prefix' => 'orders_export']);
assert_eq('orders', $es->table, 'ES-01: table');
assert_eq('created_at', $es->order_by, 'ES-01: order_by');
assert_eq('DESC', $es->direction, 'ES-01: direction');
assert_eq('orders_export', $es->filename_prefix, 'ES-01: filename_prefix');

// ES-02: ExportSource default direction
$es2 = new ExportSource(['table' => 'items']);
assert_eq('ASC', $es2->direction, 'ES-02: default direction');

// ES-03: ExportSource lowercase direction normalized
$es3 = new ExportSource(['table' => 'x', 'direction' => 'desc']);
assert_eq('DESC', $es3->direction, 'ES-03: direction normalized');

// ES-04: fromArray returns null on empty table
assert_null(ExportSource::fromArray(['order_by' => 'id']), 'ES-04: fromArray null on empty table');

// ES-05: fromArray returns object on valid
$es5 = ExportSource::fromArray(['table' => 'valid_table']);
assert_true($es5 instanceof ExportSource, 'ES-05: fromArray returns ExportSource');

// ═══════════════════════════════════════
// ReportResource Tests
// ═══════════════════════════════════════

// RR-01: Full constructor data round-trip
$data = [
    'report_key' => 'manufacturing.coverage.daily',
    'owner' => 'module',
    'owner_app' => 'Manufacturing',
    'owner_module' => 'Coverage',
    'module_dir' => '/apps/Manufacturing/modules/Coverage',
    'title' => 'Daily Coverage',
    'description' => 'Daily coverage report',
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
    'export_formats' => ['csv', 'pdf', 'xlsx'],
    'export_sources' => [
        ['table' => 'coverage_daily', 'order_by' => 'date', 'direction' => 'DESC'],
    ],
    'scope' => 'factory_floor',
];
$r = new ReportResource($data);
assert_eq('manufacturing.coverage.daily', $r->report_key, 'RR-01: report_key');
assert_eq('module', $r->owner, 'RR-01: owner');
assert_eq('Manufacturing', $r->owner_app, 'RR-01: owner_app');
assert_eq('Coverage', $r->owner_module, 'RR-01: owner_module');
assert_eq('/apps/Manufacturing/modules/Coverage', $r->module_dir, 'RR-01: module_dir');
assert_eq('Daily Coverage', $r->title, 'RR-01: title');
assert_eq('Daily coverage report', $r->description, 'RR-01: description');
assert_eq('analytical', $r->category, 'RR-01: category');
assert_eq('chart', $r->visualization, 'RR-01: visualization');
assert_eq('manufacturing.coverage.view', $r->permission, 'RR-01: permission');
assert_eq('installed', $r->lifecycle, 'RR-01: lifecycle');
assert_eq('coverage/daily', $r->view, 'RR-01: view');
assert_eq('coverage/daily_export', $r->export_view, 'RR-01: export_view');
assert_eq(['coverage/daily_pdf'], $r->pdf_views, 'RR-01: pdf_views');
assert_eq(2, count($r->parameters), 'RR-01: parameters count');
assert_true($r->parameters[0] instanceof Param, 'RR-01: parameters[0] is Param');
assert_eq('date', $r->parameters[0]->key, 'RR-01: parameters[0].key');
assert_eq(['csv', 'pdf', 'xlsx'], $r->export_formats, 'RR-01: export_formats');
assert_true($r->export_sources !== null, 'RR-01: export_sources not null');
assert_eq(1, count($r->export_sources), 'RR-01: export_sources count');
assert_eq('coverage_daily', $r->export_sources[0]->table, 'RR-01: export_sources[0].table');
assert_eq('factory_floor', $r->scope, 'RR-01: scope');

// RR-02: Default values for optional fields
$r2 = new ReportResource([
    'report_key' => 'minimal.report',
    'owner' => 'app',
    'title' => 'Minimal',
    'permission' => 'some.perm',
    'lifecycle' => 'active_only',
    'view' => 'some/view',
]);
assert_eq('', $r2->description, 'RR-02: description default');
assert_eq('operational', $r2->category, 'RR-02: category default');
assert_eq('table', $r2->visualization, 'RR-02: visualization default');
assert_eq([], $r2->parameters, 'RR-02: parameters default');
assert_eq([], $r2->export_formats, 'RR-02: export_formats default (no export_view)');
assert_null($r2->export_sources, 'RR-02: export_sources default');
assert_eq([], $r2->pdf_views, 'RR-02: pdf_views default');
assert_eq('', $r2->scope, 'RR-02: scope default');

// RR-03: export_formats derived from export_view
$r3 = new ReportResource([
    'report_key' => 'derived.export',
    'owner' => 'app',
    'title' => 'Derived',
    'permission' => 'some.perm',
    'lifecycle' => 'active_only',
    'view' => 'some/view',
    'export_view' => 'some/export',
]);
assert_eq(['csv'], $r3->export_formats, 'RR-03: export_formats derived from export_view');

// RR-04: owner defaults to 'module'
$r4 = new ReportResource([
    'report_key' => 'no.owner',
    'title' => 'No Owner',
    'permission' => 'x',
    'lifecycle' => 'always',
    'view' => 'x',
]);
assert_eq('module', $r4->owner, 'RR-04: owner defaults to module');

// RR-05: Invalid owner stored as-is (validation catches it)
$r5 = new ReportResource([
    'report_key' => 'bad.owner',
    'owner' => 'invalid_owner_type',
    'title' => 'Bad Owner',
    'permission' => 'x',
    'lifecycle' => 'always',
    'view' => 'x',
]);
assert_eq('invalid_owner_type', $r5->owner, 'RR-05: invalid owner stored as-is');

// RR-06: lifecycle defaults to 'active_only'
$r6 = new ReportResource([
    'report_key' => 'no.lifecycle',
    'owner' => 'app',
    'title' => 'No Lifecycle',
    'permission' => 'x',
    'view' => 'x',
]);
assert_eq('active_only', $r6->lifecycle, 'RR-06: lifecycle defaults to active_only');

// RR-07: fromArray returns ReportResource
$r7 = ReportResource::fromArray($data);
assert_true($r7 instanceof ReportResource, 'RR-07: fromArray returns ReportResource');
assert_eq('manufacturing.coverage.daily', $r7->report_key, 'RR-07: fromArray data integrity');

// ═══════════════════════════════════════
// ArrayAccess Tests
// ═══════════════════════════════════════

// AA-01: offsetGet known field
assert_eq('manufacturing.coverage.daily', $r['report_key'], 'AA-01: offsetGet report_key');

// AA-02: offsetGet unknown field returns null
assert_null($r['nonexistent_field'], 'AA-02: offsetGet unknown returns null');

// AA-03: offsetExists known non-null field
assert_true(isset($r['report_key']), 'AA-03: offsetExists known field');

// AA-04: offsetExists empty-string field returns true (exists, non-null)
$rEmpty = new ReportResource([
    'report_key' => 'empty.test',
    'owner' => 'app',
    'title' => 'Empty',
    'permission' => 'x',
    'lifecycle' => 'active_only',
    'view' => 'x',
]);
assert_true(isset($rEmpty['description']), 'AA-04: offsetExists empty string (non-null) returns true');

// AA-04b: offsetExists null field returns false
assert_false(isset($rEmpty['export_view']), 'AA-04b: offsetExists null field returns false');

// AA-05: offsetExists unknown field returns false
assert_false(isset($r['bogus']), 'AA-05: offsetExists unknown returns false');

// AA-06: offsetSet throws
assert_throws(fn() => $r['title'] = 'New Title', 'AA-06: offsetSet throws');

// AA-07: offsetUnset throws
assert_throws(function () use ($r) { unset($r['title']); }, 'AA-07: offsetUnset throws');

// AA-08: toArray round-trip
$arr = $r->toArray();
assert_eq('manufacturing.coverage.daily', $arr['report_key'], 'AA-08: toArray report_key');
assert_eq(2, count($arr['parameters']), 'AA-08: toArray parameters count');
assert_eq(['csv', 'pdf', 'xlsx'], $arr['export_formats'], 'AA-08: toArray export_formats');

// ═══════════════════════════════════════
// Summary
// ═══════════════════════════════════════

$total = $passed + $failed;
fprintf(STDERR, "\nResults: %d/%d passed, %d failed\n", $passed, $total, $failed);
exit($failed > 0 ? 1 : 0);

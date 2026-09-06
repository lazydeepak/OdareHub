<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/apps/Platform/Services/AdminDashboardPanelBlockService.php';

use Apps\Platform\Services\AdminDashboardPanelBlockService;

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$payload = [
    'title' => 'App Admin Dashboard',
    'subtitle' => 'Assigned application administration.',
    'cards' => [
        ['key' => 'assigned_app_count', 'value' => 2],
        ['key' => 'dispatch_followup', 'value' => 3],
        ['key' => 'covered_qty', 'value' => '4.00'],
        ['key' => 'dispatched_qty', 'value' => '5.00'],
        ['key' => 'production_qty', 'value' => '6.00'],
        ['key' => 'qc_qty', 'value' => '7.00'],
    ],
    'sections' => [
        ['key' => 'assigned_application_scope', 'items' => [['value' => 'manufacturing, sbaio']]],
        ['key' => 'finance_admin_control', 'items' => [['value' => '1.00']]],
    ],
    'quick_links' => [
        ['label' => 'Coverage', 'url' => '/manufacturing/coverage'],
        ['label' => 'Invalid', 'url' => ''],
    ],
    'placeholders' => ['Future assigned-app contribution.'],
];

$panel = AdminDashboardPanelBlockService::composePanel($payload);
$assert(($panel['key'] ?? '') === 'app_admin', 'panel remains role scoped');
$assert(($panel['url'] ?? 'legacy') === '', 'panel has no legacy dashboard handoff');
$assert(count((array)($panel['cards'] ?? [])) === 6, 'all KPI cards are preserved');
$assert(count((array)($panel['sections'] ?? [])) === 2, 'all scope and control sections are preserved');
$assert(count((array)($panel['quick_links'] ?? [])) === 1, 'valid owner quick links are preserved');
$assert(count((array)($panel['placeholders'] ?? [])) === 1, 'empty and planned-state notes are preserved');
$assert(($panel['title'] ?? '') === $payload['title'], 'title is preserved');
$assert(($panel['subtitle'] ?? '') === $payload['subtitle'], 'subtitle is preserved');

$emptyPanel = AdminDashboardPanelBlockService::composePanel([
    'title' => 'App Admin Dashboard',
    'cards' => [['key' => 'assigned_app_count', 'value' => 0]],
    'sections' => [['key' => 'assigned_application_scope', 'items' => [['value' => 'No applications assigned']]]],
    'quick_links' => [],
    'placeholders' => ['No assigned application has contributed a compatibility dashboard payload.'],
]);
$assert(count((array)$emptyPanel['cards']) === 1, 'empty assignment count remains explicit');
$assert(count((array)$emptyPanel['sections']) === 1, 'empty assignment scope remains explicit');
$assert(count((array)$emptyPanel['placeholders']) === 1, 'empty contribution state remains explicit');

echo "App Admin unified parity probe: {$checks}/{$checks} assertions passed\n";

<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tempRoot = sys_get_temp_dir() . '/vc-draft-shape-probe-' . bin2hex(random_bytes(6));

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $tempRoot);
}

require_once $root . '/apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerDraftStorageService.php';

use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerDraftStorageService;

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function probe_assert_same($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$message}\n");
        fwrite(STDERR, "Expected: " . json_encode($expected, JSON_UNESCAPED_SLASHES) . "\n");
        fwrite(STDERR, "Actual: " . json_encode($actual, JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }

    echo "ok: {$message}\n";
}

function probe_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child)) {
            probe_remove_tree($child);
        } else {
            @unlink($child);
        }
    }

    @rmdir($path);
}

$user = ['id' => 9001];
$draftPath = APP_ROOT . '/storage/studio/customization/visual-customizer/drafts/user-9001/visual-customizer-local-preview.json';
$draftDir = dirname($draftPath);

try {
    if (!is_dir($draftDir) && !mkdir($draftDir, 0775, true) && !is_dir($draftDir)) {
        throw new RuntimeException('Unable to create probe draft directory.');
    }

    $legacyDraft = [
        'draft_id' => 'legacy-id',
        'status' => 'legacy_status',
        'owner' => 'legacy_owner',
        'scope' => 'legacy_scope',
        'created_at' => '2026-05-01T00:00:00+00:00',
        'updated_at' => '2026-05-02T00:00:00+00:00',
        'selected_socket_id' => 'legacy.socket',
        'values' => [
            'radius.scale' => [
                'default_value' => 'soft',
                'current_value' => null,
                'proposed_value' => 'round',
                'source' => 'legacy_studio_local_draft',
                'legacy_note' => 'must be ignored',
            ],
            'color.primary' => [
                'proposed_value' => '#ff00ff',
            ],
        ],
        'constraints' => [
            'apply_enabled' => true,
            'runtime_activation_enabled' => true,
            'shell_consumption_enabled' => true,
            'platform_registry_io_enabled' => true,
        ],
    ];

    file_put_contents($draftPath, json_encode($legacyDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    $loaded = VisualCustomizerDraftStorageService::readDraftForUser($user);
    probe_assert_same($loaded['values'] ?? null, [
        'radius.scale' => [
            'proposed_value' => 'round',
        ],
    ], 'legacy read exposes only radius.scale proposed_value');
    probe_assert_same($loaded['selected_socket_id'] ?? null, 'radius.scale', 'legacy selected socket is normalized to radius.scale');
    probe_assert_same($loaded['constraints']['apply_enabled'] ?? null, false, 'legacy apply flag is not exposed');
    probe_assert_same($loaded['constraints']['shell_consumption_enabled'] ?? null, false, 'legacy shell consumption flag is not exposed');

    $saveResult = VisualCustomizerDraftStorageService::saveProposedValueForUser($user, 'radius.scale', 'soft', 'sharp');
    probe_assert_same($saveResult['ok'] ?? null, true, 'radius.scale save succeeds');

    $stored = json_decode((string)file_get_contents($draftPath), true);
    probe_assert_same($stored['values'] ?? null, [
        'radius.scale' => [
            'proposed_value' => 'sharp',
        ],
    ], 'write stores narrowed proposed_value-only shape');

    $badSaveResult = VisualCustomizerDraftStorageService::saveProposedValueForUser($user, 'color.primary', 'blue', 'round');
    probe_assert_same($badSaveResult, [
        'ok' => false,
        'error' => 'socket_not_allowed',
    ], 'non-radius socket cannot be persisted');

    $storedAfterBadSave = json_decode((string)file_get_contents($draftPath), true);
    probe_assert_same($storedAfterBadSave['values'] ?? null, [
        'radius.scale' => [
            'proposed_value' => 'sharp',
        ],
    ], 'non-radius save leaves stored draft unchanged');

    $discardResult = VisualCustomizerDraftStorageService::discardProposedValueForUser($user, 'radius.scale', 'soft');
    probe_assert_same($discardResult['ok'] ?? null, true, 'discard succeeds');

    $storedAfterDiscard = json_decode((string)file_get_contents($draftPath), true);
    probe_assert_same($storedAfterDiscard['values'] ?? null, [], 'discard removes radius.scale value and leaves empty narrowed draft');

    file_put_contents($draftPath, json_encode([
        'values' => [
            'radius.scale' => [
                'default_value' => 'soft',
                'source' => 'legacy_without_proposed_value',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    $loadedWithoutProposed = VisualCustomizerDraftStorageService::readDraftForUser($user);
    probe_assert_same($loadedWithoutProposed['values'] ?? null, [], 'legacy radius.scale without valid proposed_value is not exposed');

    echo "RESULT: PASS Visual Customizer draft shape probe\n";
} finally {
    probe_remove_tree($tempRoot);
}

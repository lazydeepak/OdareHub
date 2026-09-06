<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';

use Platform\Security\EngineeringWorkspaceResolver;

$passed = 0;
$failed = 0;

function ewe_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function ewe_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }

    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

$admin = ['authority_role' => 'platform_admin'];
$nonAdmin = ['authority_role' => 'app_admin'];
$workspace = 'Manufacturing/Products';
$document = 'work';
$path = APP_ROOT . '/engineering/Manufacturing/Products/work.md';
$original = (string)file_get_contents($path);
$originalFingerprint = EngineeringWorkspaceResolver::fingerprint($original);

try {
    foreach (EngineeringWorkspaceResolver::getAllowedDocumentTypes() as $documentKey) {
        $resolved = EngineeringWorkspaceResolver::resolve($workspace, $documentKey, $admin);
        ewe_assert_true(!empty($resolved['authorized']) && !empty($resolved['exists']), 'Platform Admin can open ' . $documentKey);
        ewe_assert_true(is_string($resolved['fingerprint'] ?? null) && strlen((string)$resolved['fingerprint']) === 64, 'fingerprint present for ' . $documentKey);
    }

    $denied = EngineeringWorkspaceResolver::resolve($workspace, $document, $nonAdmin);
    ewe_assert_eq(false, (bool)($denied['authorized'] ?? true), 'Non-Platform-Admin denied before read');
    ewe_assert_true(!isset($denied['content']), 'Non-Platform-Admin receives no content');

    ewe_assert_eq('Invalid workspace key', (string)(EngineeringWorkspaceResolver::resolve('Unknown/Workspace', $document, $admin)['error'] ?? ''), 'Unknown workspace rejected');
    ewe_assert_eq('Invalid workspace key', (string)(EngineeringWorkspaceResolver::resolve('../engineering/Studio', $document, $admin)['error'] ?? ''), 'Traversal workspace rejected');
    ewe_assert_eq('Invalid document type', (string)(EngineeringWorkspaceResolver::resolve($workspace, 'notes', $admin)['error'] ?? ''), 'Unsupported document rejected');

    $rawPathAttempt = EngineeringWorkspaceResolver::writeWithFingerprint('/tmp/outside.md', $document, 'nope', $originalFingerprint, $admin);
    ewe_assert_eq(false, (bool)($rawPathAttempt['ok'] ?? true), 'Raw absolute path workspace rejected');

    $noFingerprint = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, $document, $original, '', $admin);
    ewe_assert_eq(false, (bool)($noFingerprint['ok'] ?? true), 'Missing fingerprint rejects write');

    $invalidDocWrite = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, 'notes', $original, $originalFingerprint, $admin);
    ewe_assert_eq(false, (bool)($invalidDocWrite['ok'] ?? true), 'Unsupported document write rejected');

    $nonAdminWrite = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, $document, $original, $originalFingerprint, $nonAdmin);
    ewe_assert_eq(false, (bool)($nonAdminWrite['ok'] ?? true), 'Non-Platform-Admin write rejected');

    $firstDraft = $original . "\n<!-- editing probe first write -->\n";
    $validWrite = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, $document, $firstDraft, $originalFingerprint, $admin);
    ewe_assert_eq(true, (bool)($validWrite['ok'] ?? false), 'Valid current fingerprint writes');
    ewe_assert_eq($firstDraft, (string)($validWrite['content'] ?? ''), 'Saved content is re-read from storage');
    ewe_assert_eq($firstDraft, (string)file_get_contents($path), 'Only canonical document was written');
    ewe_assert_true(str_starts_with((string)($validWrite['path'] ?? ''), 'engineering/Manufacturing/Products/'), 'Write path remains under workspace');

    $secondDraft = $original . "\n<!-- editing probe stale write should not persist -->\n";
    $staleWrite = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, $document, $secondDraft, $originalFingerprint, $admin);
    ewe_assert_eq(false, (bool)($staleWrite['ok'] ?? true), 'Stale fingerprint rejects write');
    ewe_assert_eq(true, (bool)($staleWrite['stale'] ?? false), 'Stale write is marked stale');
    ewe_assert_eq($firstDraft, (string)file_get_contents($path), 'Stale write does not overwrite stored content');

    $restore = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, $document, $original, (string)($validWrite['fingerprint'] ?? ''), $admin);
    ewe_assert_eq(true, (bool)($restore['ok'] ?? false), 'Original content restored');
    ewe_assert_eq($original, (string)file_get_contents($path), 'Restored content matches original');
} finally {
    if ((string)file_get_contents($path) !== $original) {
        file_put_contents($path, $original, LOCK_EX);
    }
}

echo "EngineeringWorkspaceEditing: {$passed} passed, {$failed} failed" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

exit(0);

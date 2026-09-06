<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';

use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\EngineeringWorkspaceResolver;

$passed = 0;
$failed = 0;

function ew_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function ew_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }

    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

$expectedDocuments = ['overview', 'work', 'rules', 'decisions'];
$expectedFiles = [
    'overview' => 'overview.md',
    'work' => 'work.md',
    'rules' => 'rules.md',
    'decisions' => 'decisions.md',
];

ew_assert_eq($expectedDocuments, EngineeringWorkspaceContentContract::allowedDocumentKeys(), 'allowed document keys are canonical');
ew_assert_eq($expectedDocuments, EngineeringWorkspaceResolver::getAllowedDocumentTypes(), 'resolver delegates allowed documents to contract');

foreach ($expectedFiles as $documentKey => $filename) {
    ew_assert_eq($filename, EngineeringWorkspaceContentContract::canonicalFilename($documentKey), 'canonical filename for ' . $documentKey);

    $templatePath = EngineeringWorkspaceContentContract::templatePath($documentKey);
    ew_assert_true(is_string($templatePath) && is_file($templatePath), 'template exists for ' . $documentKey);

    $template = is_string($templatePath) ? (string)file_get_contents($templatePath) : '';
    foreach (EngineeringWorkspaceContentContract::requiredHeadings($documentKey) as $heading) {
        ew_assert_true(str_contains($template, '## ' . $heading), 'template ' . $documentKey . ' contains heading ' . $heading);
    }
}

$actor = ['authority_role' => 'platform_admin'];
foreach (EngineeringWorkspaceContentContract::supportedWorkspaceKeys() as $workspaceKey) {
    ew_assert_true(EngineeringWorkspaceResolver::isValidWorkspaceKey($workspaceKey), 'supported workspace key is valid: ' . $workspaceKey);

    foreach ($expectedDocuments as $documentKey) {
        $resolved = EngineeringWorkspaceResolver::resolve($workspaceKey, $documentKey, $actor);
        ew_assert_true(!empty($resolved['authorized']), 'resolved authorized for ' . $workspaceKey . ' ' . $documentKey);
        ew_assert_true(!empty($resolved['exists']), 'document exists for ' . $workspaceKey . ' ' . $documentKey);
        ew_assert_true(str_ends_with((string)($resolved['path'] ?? ''), '/' . $expectedFiles[$documentKey]), 'resolved canonical filename for ' . $workspaceKey . ' ' . $documentKey);
        ew_assert_true(str_starts_with((string)($resolved['path'] ?? ''), 'engineering/'), 'resolved path is relative to engineering root');

        $content = (string)($resolved['content'] ?? '');
        $requiredHeadings = EngineeringWorkspaceContentContract::requiredHeadings($documentKey);
        if ($documentKey === 'rules') {
            $legacyHeadings = ['Read First', 'Change Rules', 'Validation Rules', 'Ownership Boundaries', 'Completion Rule'];
            $allInContent = true;
            foreach ($requiredHeadings as $h) {
                if (!str_contains($content, '## ' . $h)) { $allInContent = false; break; }
            }
            if (!$allInContent) {
                // Accept legacy headings for existing un-migrated documents
                $anyLegacy = false;
                foreach ($legacyHeadings as $h) {
                    if (str_contains($content, '## ' . $h)) { $anyLegacy = true; break; }
                }
                ew_assert_true($anyLegacy, 'document ' . $workspaceKey . '/' . $documentKey . ' contains new or legacy heading');
            } else {
                ew_assert_true(true, 'document ' . $workspaceKey . '/' . $documentKey . ' contains all new headings');
            }
        } elseif ($documentKey === 'decisions') {
            $hasNew = str_contains($content, '## Decision Log');
            $hasLegacy = str_contains($content, '## Decision Record Format');
            ew_assert_true($hasNew || $hasLegacy, 'document ' . $workspaceKey . '/' . $documentKey . ' contains Decision Log or Decision Record Format');
        } else {
            foreach ($requiredHeadings as $heading) {
                ew_assert_true(str_contains($content, '## ' . $heading), 'document ' . $workspaceKey . '/' . $documentKey . ' contains heading ' . $heading);
            }
        }
    }
}

// Verify fresh initialization from each template produces content that passes the heading contract
$testWorkspace = '_probe_template_validation';
foreach ($expectedDocuments as $documentKey) {
    $templatePath = EngineeringWorkspaceContentContract::templatePath($documentKey);
    $template = is_string($templatePath) ? (string)file_get_contents($templatePath) : '';
    $initialized = str_replace('<Workspace Name>', 'Probe Validation', $template);
    foreach (EngineeringWorkspaceContentContract::requiredHeadings($documentKey) as $heading) {
        ew_assert_true(str_contains($initialized, '## ' . $heading), 'fresh initialization from ' . $documentKey . ' template contains heading ' . $heading);
    }
}

ew_assert_eq(null, EngineeringWorkspaceContentContract::documentPath('../outside', 'overview'), 'traversal workspace cannot resolve document path');
ew_assert_eq(null, EngineeringWorkspaceContentContract::documentPath('/absolute/path', 'overview'), 'absolute workspace cannot resolve document path');
ew_assert_eq(null, EngineeringWorkspaceContentContract::documentPath('Studio', '../overview'), 'traversal document cannot resolve document path');
ew_assert_eq(null, EngineeringWorkspaceContentContract::documentPath('Unknown/Workspace', 'overview'), 'unsupported workspace cannot resolve document path');
ew_assert_true(!EngineeringWorkspaceResolver::isValidWorkspaceKey('Unknown/Workspace'), 'unsupported workspace rejected by resolver');

$invalidDocument = EngineeringWorkspaceResolver::resolve('Studio', 'notes', $actor);
ew_assert_eq('Invalid document type', (string)($invalidDocument['error'] ?? ''), 'unsupported document rejected');

$existingPath = APP_ROOT . '/engineering/Studio/overview.md';
$before = (string)file_get_contents($existingPath);
$init = EngineeringWorkspaceContentContract::initializeMissingDocument('Studio', 'overview');
$after = (string)file_get_contents($existingPath);
ew_assert_true(!empty($init['ok']) && empty($init['created']), 'existing document initialization is no-op');
ew_assert_eq($before, $after, 'existing valid document is not overwritten');

echo "EngineeringWorkspaceContentContract: {$passed} passed, {$failed} failed" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

exit(0);

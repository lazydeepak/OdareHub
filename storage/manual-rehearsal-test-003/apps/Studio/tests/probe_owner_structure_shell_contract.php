<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureFilesystemScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureArtifactClassifierService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureContractV2DiagnosisService.php';

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureArtifactClassifierService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureContractV2DiagnosisService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureFilesystemScannerService;

$assertions = 0;

function oss_shell_assert_true(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function oss_shell_find_finding(array $diagnosis, string $status, string $path): ?array
{
    foreach ((array)($diagnosis['findings'] ?? []) as $finding) {
        if (!is_array($finding)) {
            continue;
        }
        if ((string)($finding['status'] ?? '') === $status && (string)($finding['current_path'] ?? '') === $path) {
            return $finding;
        }
    }
    return null;
}

function oss_shell_group_keys(array $classification): array
{
    $keys = [];
    foreach ((array)($classification['groups'] ?? []) as $group) {
        if (!is_array($group) || ((int)($group['file_count'] ?? 0) + (int)($group['folder_count'] ?? 0)) === 0) {
            continue;
        }
        $keys[(string)($group['key'] ?? '')] = true;
    }
    return $keys;
}

$shellScan = OwnerStructureFilesystemScannerService::scan('Shell');
$shellClassification = OwnerStructureArtifactClassifierService::classify((array)$shellScan['entries']);
$shellDiagnosis = OwnerStructureContractV2DiagnosisService::diagnose($shellScan);
$shellGroupKeys = oss_shell_group_keys($shellClassification);

oss_shell_assert_true((int)($shellClassification['unknown_count'] ?? -1) === 0, 'Shell classification has zero unknown entries');

$canonicalShellDomains = [
    'apps/Shell/styles' => 'shell_runtime_css',
    'apps/Shell/Resources/css/essential' => 'shell_boot_essential_css',
    'apps/Shell/Resources/rendering' => 'shell_rendering_foundation',
    'apps/Shell/Composers' => 'shell_composition',
    'apps/Shell/Overlay' => 'runtime',
    'apps/Shell/sidebar_sources' => 'shell_navigation_sources',
];

foreach ($canonicalShellDomains as $path => $groupKey) {
    oss_shell_assert_true(isset($shellGroupKeys[$groupKey]), "{$groupKey} classification group is populated");
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Native v2', $path) !== null, "{$path} is recognized as native Shell contract");
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Invalid/unknown', $path) === null, "{$path} is not invalid/unknown");
}

$hasLegacyStylePath = false;
$hasDesignSystemPath = false;
foreach ((array)($shellScan['entries'] ?? []) as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $ownerRelativePath = (string)($entry['owner_relative_path'] ?? '');
    if ($ownerRelativePath === 'Style') {
        $hasLegacyStylePath = true;
    }
    if ($ownerRelativePath === 'DesignSystem') {
        $hasDesignSystemPath = true;
    }
}

oss_shell_assert_true(isset($shellGroupKeys['shell_style_governance']), 'shell_style_governance classification group is populated');
if ($hasDesignSystemPath) {
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Native v2', 'apps/Shell/DesignSystem') !== null, 'apps/Shell/DesignSystem is recognized as native Shell contract');
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Invalid/unknown', 'apps/Shell/DesignSystem') === null, 'apps/Shell/DesignSystem is not invalid/unknown');
}
if ($hasLegacyStylePath) {
    $styleMigration = oss_shell_find_finding($shellDiagnosis, 'Migration required', 'apps/Shell/Style');
    oss_shell_assert_true($styleMigration !== null, 'apps/Shell/Style is flagged for migration to DesignSystem');
    oss_shell_assert_true((string)($styleMigration['target_path'] ?? '') === 'apps/Shell/DesignSystem', 'apps/Shell/Style migration target is apps/Shell/DesignSystem');
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Invalid/unknown', 'apps/Shell/Style') === null, 'apps/Shell/Style remains transitional, not invalid/unknown');
}
if (!$hasLegacyStylePath && $hasDesignSystemPath) {
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Migration required', 'apps/Shell/Style') === null, 'apps/Shell/Style migration finding is absent after DesignSystem physical migration');
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Invalid/unknown', 'apps/Shell/Style') === null, 'apps/Shell/Style is not reported as invalid after migration');
}

$shellDomainModel = isset($shellDiagnosis['shell_domain_model']) && is_array($shellDiagnosis['shell_domain_model'])
    ? $shellDiagnosis['shell_domain_model']
    : [];
oss_shell_assert_true($shellDomainModel !== [], 'Shell domain model payload is present');
oss_shell_assert_true((string)($shellDomainModel['model_version'] ?? '') === 'Shell Domain Model v1', 'Shell domain model version matches v1');

$domainRows = isset($shellDomainModel['domains']) && is_array($shellDomainModel['domains']) ? $shellDomainModel['domains'] : [];
$domainKeys = [];
foreach ($domainRows as $domainRow) {
    if (!is_array($domainRow)) {
        continue;
    }
    $domainKeys[(string)($domainRow['key'] ?? '')] = true;
}
oss_shell_assert_true(isset($domainKeys['runtime']), 'Shell domain model includes runtime domain');
oss_shell_assert_true(isset($domainKeys['styling']), 'Shell domain model includes styling domain');
oss_shell_assert_true(isset($domainKeys['contracts']), 'Shell domain model includes contracts domain');
oss_shell_assert_true(isset($domainKeys['quality']), 'Shell domain model includes quality domain');

$taxonomyDecisions = isset($shellDomainModel['taxonomy_decisions']) && is_array($shellDomainModel['taxonomy_decisions'])
    ? $shellDomainModel['taxonomy_decisions']
    : [];
oss_shell_assert_true((string)($taxonomyDecisions['design_system_vs_styles'] ?? '') !== '', 'Shell taxonomy includes design_system_vs_styles decision');
oss_shell_assert_true(str_contains((string)($taxonomyDecisions['design_system_vs_styles'] ?? ''), 'must not consume DesignSystem metadata directly'), 'Shell taxonomy enforces DesignSystem metadata runtime boundary');
oss_shell_assert_true((string)($taxonomyDecisions['runtime_domain_scope'] ?? '') !== '', 'Shell taxonomy includes runtime_domain_scope decision');
oss_shell_assert_true((string)($taxonomyDecisions['additional_domains'] ?? '') !== '', 'Shell taxonomy includes additional_domains decision');

$ownershipBoundaries = isset($shellDomainModel['ownership_boundaries']) && is_array($shellDomainModel['ownership_boundaries'])
    ? $shellDomainModel['ownership_boundaries']
    : [];
oss_shell_assert_true(count($ownershipBoundaries) >= 5, 'Shell domain model includes ownership boundaries for all canonical domains');
$boundaryByDomain = [];
foreach ($ownershipBoundaries as $boundary) {
    if (!is_array($boundary)) {
        continue;
    }
    $boundaryByDomain[(string)($boundary['domain'] ?? '')] = $boundary;
}
oss_shell_assert_true(isset($boundaryByDomain['Runtime']), 'Ownership boundaries include Runtime domain');
oss_shell_assert_true(isset($boundaryByDomain['DesignSystem']), 'Ownership boundaries include DesignSystem domain');
oss_shell_assert_true(isset($boundaryByDomain['styles']), 'Ownership boundaries include styles domain');
oss_shell_assert_true(isset($boundaryByDomain['Contracts']), 'Ownership boundaries include Contracts domain');
oss_shell_assert_true(isset($boundaryByDomain['Quality']), 'Ownership boundaries include Quality domain');

oss_shell_assert_true(
    oss_shell_find_finding($shellDiagnosis, 'Cleanup required', 'apps/Shell/DesignSystem/.DS_Store') === null,
    'Shell DesignSystem .DS_Store cleanup noise is absent after cleanup'
);
oss_shell_assert_true(
    oss_shell_find_finding($shellDiagnosis, 'Cleanup required', 'apps/Shell/.DS_Store') === null,
    'Shell root .DS_Store cleanup noise is absent after cleanup'
);
oss_shell_assert_true(
    oss_shell_find_finding($shellDiagnosis, 'Cleanup required', 'apps/Shell/Views/.DS_Store') === null,
    'Shell Views .DS_Store cleanup noise is absent after cleanup'
);

foreach ([
    'apps/Shell/AGENTS.md',
    'apps/Shell/dashboard_widgets.php',
    'apps/Shell/layout_contract.php',
    'apps/Shell/sidebar.php',
] as $path) {
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Native v2', $path) !== null, "{$path} is recognized as a native Shell root contract");
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Invalid/unknown', $path) === null, "{$path} is not invalid/unknown");
}

$decompositionPaths = [];
foreach ((array)($shellDiagnosis['decomposition_candidates'] ?? []) as $candidate) {
    if (is_array($candidate)) {
        $decompositionPaths[(string)($candidate['current_path'] ?? '')] = $candidate;
    }
}

foreach ([
    'apps/Shell/Composers/OperatorSurfaceComposer.php',
    'apps/Shell/Composers/WorkEntryComposer.php',
    'apps/Shell/routes.php',
    'apps/Shell/styles/shell-components.css',
] as $path) {
    oss_shell_assert_true(isset($decompositionPaths[$path]), "{$path} is reported as a decomposition candidate");
    oss_shell_assert_true(oss_shell_find_finding($shellDiagnosis, 'Invalid/unknown', $path) === null, "{$path} is not an owner-contract violation");
}

$syntheticNonShellDiagnosis = OwnerStructureContractV2DiagnosisService::diagnose([
    'owner_root_relative_path' => 'apps/SampleApp',
    'entries' => [
        [
            'type' => 'folder',
            'name' => 'styles',
            'physical_path' => APP_ROOT . '/apps/SampleApp/styles',
            'relative_path' => 'apps/SampleApp/styles',
            'owner_relative_path' => 'styles',
            'size' => 0,
            'error' => '',
        ],
    ],
]);

oss_shell_assert_true(
    oss_shell_find_finding($syntheticNonShellDiagnosis, 'Invalid/unknown', 'apps/SampleApp/styles') !== null,
    'Non-Shell root styles folder remains invalid without an owner-specific contract'
);

echo '[probe] Owner Structure Shell contract recognition: ' . $assertions . '/' . $assertions . " assertions passed\n";

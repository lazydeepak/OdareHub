<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 7));

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceDeterministicFixOneService.php';

use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceDeterministicFixOneService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceScannerService;

$pass = 0;
$fail = 0;

$assert = static function (bool $condition, string $label) use (&$pass, &$fail): void {
    if ($condition) {
        echo "  PASS: {$label}\n";
        $pass++;
        return;
    }
    echo "  FAIL: {$label}\n";
    $fail++;
};

$owner = 'ZzStyleComplianceFixOneProbe';
$root = APP_ROOT . '/apps/' . $owner;
$cssDir = $root . '/styles';
$cssPath = $cssDir . '/fix-one.css';
$relativeCss = 'apps/' . $owner . '/styles/fix-one.css';
$initialGitStatus = shell_exec('cd ' . escapeshellarg(APP_ROOT) . ' && git status --short -- ' . escapeshellarg('apps/' . $owner) . ' 2>/dev/null') ?: '';
$snapshots = [];

$cleanup = static function () use ($root, &$snapshots): void {
    foreach ($snapshots as $snapshotPath) {
        $absolute = APP_ROOT . $snapshotPath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        if (is_file($absolute . '.json')) {
            @unlink($absolute . '.json');
        }
    }
    if (is_dir($root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
};

$writeFixture = static function (string $css) use ($cssDir, $cssPath): void {
    if (!is_dir($cssDir)) {
        mkdir($cssDir, 0775, true);
    }
    file_put_contents($cssPath, $css);
};

$proposalId = static function () use ($owner): string {
    $scan = StyleComplianceScannerService::scan(StyleComplianceScannerService::SCOPE_OWNER, $owner);
    $queue = $scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply'] ?? [];
    return is_array($queue) && isset($queue[0]) && is_array($queue[0])
        ? (string)($queue[0]['proposal_id'] ?? '')
        : '';
};

try {
    echo "Style Compliance Deterministic Fix One Probe\n";
    echo "============================================\n";

    $cleanup();

    echo "\n--- Valid static CSS fixture ---\n";
    $writeFixture(".probe-alert { color: var(--tone-danger); }\n");
    $id = $proposalId();
    $assert($id !== '', 'scanner produced one deterministic proposal');
    $before = file_get_contents($cssPath);
    $result = StyleComplianceDeterministicFixOneService::fixByProposalId($id);
    $after = file_get_contents($cssPath);
    if (isset($result['evidence']['snapshot_path'])) {
        $snapshots[] = (string)$result['evidence']['snapshot_path'];
    }
    $assert(($result['state'] ?? '') === StyleComplianceDeterministicFixOneService::STATE_FIXED, 'valid fixture returns fixed');
    $assert(substr_count((string)$before, 'var(--tone-danger)') === 1, 'valid fixture started with one old declaration');
    $assert(substr_count((string)$after, 'var(--tone-danger-text)') === 1 && !str_contains((string)$after, 'var(--tone-danger);'), 'valid fixture changes exactly one declaration');
    $assert(!empty($result['evidence']['original_finding_disappeared']), 're-scan confirms original finding is gone');

    echo "\n--- Stale old value ---\n";
    $writeFixture(".probe-alert { color: var(--tone-danger); }\n");
    $staleId = $proposalId();
    $writeFixture(".probe-alert { color: var(--tone-warning); }\n");
    $staleBefore = file_get_contents($cssPath);
    $stale = StyleComplianceDeterministicFixOneService::fixByProposalId($staleId);
    $assert(($stale['state'] ?? '') === StyleComplianceDeterministicFixOneService::STATE_STALE, 'old value mismatch returns stale');
    $assert(file_get_contents($cssPath) === $staleBefore, 'stale result writes nothing');

    echo "\n--- Ambiguous duplicate target ---\n";
    $writeFixture(".probe-alert { color: var(--tone-danger); }\n.probe-alert-two { color: var(--tone-danger); }\n");
    $ambiguousId = $proposalId();
    $duplicateBefore = file_get_contents($cssPath);
    $ambiguous = StyleComplianceDeterministicFixOneService::fixByProposalId($ambiguousId);
    $assert(($ambiguous['state'] ?? '') === StyleComplianceDeterministicFixOneService::STATE_AMBIGUOUS, 'duplicate target returns ambiguous');
    $assert(file_get_contents($cssPath) === $duplicateBefore, 'ambiguous result writes nothing');

    echo "\n--- Excluded source types ---\n";
    $baseProposal = [
        'proposal_class' => 'deterministic_theme_value_fix',
        'proposal_status' => 'ready_for_future_apply',
        'source_scope' => StyleComplianceScannerService::SCOPE_OWNER,
        'file_path' => $relativeCss,
        'migration_state' => 'value_fix',
        'evidence' => ['scan_category' => 'semantic_token_misuse'],
        'property' => 'color',
        'replacement_token' => '--tone-danger-text',
        'replacement_value' => 'var(--tone-danger-text)',
        'current_value' => 'var(--tone-danger)',
        'selector' => '.probe-alert',
    ];
    $assert(StyleComplianceDeterministicFixOneService::eligibility(array_merge($baseProposal, ['file_path' => 'apps/Generated/X/styles.css']))['state'] === 'blocked', 'generated source is blocked');
    $assert(StyleComplianceDeterministicFixOneService::eligibility(array_merge($baseProposal, ['file_path' => 'apps/X/styles/print.css']))['state'] === 'blocked', 'print source is blocked');
    $assert(StyleComplianceDeterministicFixOneService::eligibility(array_merge($baseProposal, ['property' => 'filter']))['state'] === 'blocked', 'effect/filter source is blocked');
    $assert(StyleComplianceDeterministicFixOneService::eligibility(array_merge($baseProposal, ['selector' => '[inline style line 1]']))['state'] === 'blocked', 'inline source is blocked');

    echo "\n--- Browser/server authority contract ---\n";
    $scripts = file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_scripts.php') ?: '';
    $controller = file_get_contents(APP_ROOT . '/apps/Studio/Controllers/StudioController.php') ?: '';
    $assert(str_contains($scripts, "fetch('/apps/studio/tools/customization-studio/diagnose/style-compliance/fix-one'"), 'browser calls fix-one endpoint');
    $assert(str_contains($scripts, "formData.set('proposal_id', proposalId)") && str_contains($scripts, "formData.set('csrf', csrfInput.value)"), 'browser sends only proposal id and csrf authority');
    $fixHandler = '';
    if (preg_match('/public static function styleComplianceFixOneAsync\(\): void(.*?)public static function styleComplianceRepairExecute/s', $controller, $m)) {
        $fixHandler = (string)$m[1];
    }
    $assert($fixHandler !== '' && !preg_match('/\$_POST\[[\'"](file_path|path|selector|property|current_value|old_value|replacement_value|replacement|source|owner|owner_key)[\'"]\]/', $fixHandler), 'server ignores browser-supplied path/value/replacement override fields');

    echo "\n--- Cleanup ---\n";
    $cleanup();
    $finalGitStatus = shell_exec('cd ' . escapeshellarg(APP_ROOT) . ' && git status --short -- ' . escapeshellarg('apps/' . $owner) . ' 2>/dev/null') ?: '';
    $assert(!is_dir($root), 'temporary owner fixture removed');
    $assert($initialGitStatus === $finalGitStatus, 'fixture cleanup leaves committed files unchanged');
} finally {
    $cleanup();
}

echo "\n============================================\n";
echo "Results: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);

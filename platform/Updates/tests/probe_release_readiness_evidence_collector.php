<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\LocalReleaseChannelBuilderService;
use Platform\Updates\ReleaseReadinessEvidenceCollectorService;
use Platform\Updates\ReleaseReadinessEvidenceService;

$passed = 0;
$failed = 0;

function collector_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function collector_rm_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            collector_rm_tree($path . '/' . $item);
        }
    }
    @rmdir($path);
}

$tmp = sys_get_temp_dir() . '/susankhya-readiness-collector-' . bin2hex(random_bytes(6));
$source = $tmp . '/source';
mkdir($source . '/app', 0700, true);
foreach (['apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $root) {
    mkdir($source . '/' . $root, 0700, true);
    file_put_contents($source . '/' . $root . '/probe.txt', $root);
}
file_put_contents($source . '/app/runtime.php', '<?php return true;');
file_put_contents($source . '/composer.json', json_encode(['require' => []]));
file_put_contents($source . '/composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []]));

$commands = [];
$runner = static function (string $command, string $root) use (&$commands): array {
    $commands[] = [$command, $root];
    return [
        'executed' => true,
        'exit_code' => 0,
        'output_sha256' => str_repeat('a', 64),
        'output_excerpt' => 'PASS',
    ];
};
$revisionReader = static fn(string $root): array => [
    'ok' => true,
    'revision' => 'git:collector',
    'working_tree_status' => '',
];

try {
    $channel = $tmp . '/channel';
    $built = (new LocalReleaseChannelBuilderService())->build(
        $source,
        $channel,
        '1.0.0',
        'git:collector',
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock'],
        []
    );
    collector_assert($built['ok'] === true, 'collector fixture channel builds');

    $collector = new ReleaseReadinessEvidenceCollectorService($runner, $revisionReader);
    $result = $collector->collectForChannel($channel, $source);
    collector_assert($result['ok'] === true, 'collector returns structurally valid observations');
    collector_assert(($result['readiness']['status'] ?? '') === 'passed', 'successful command observations produce passed local evidence');
    collector_assert(count($commands) === 2, 'collector executes both required readiness commands');
    collector_assert($commands[0][0] === 'bash scripts/architecture/run_architecture_gates.sh', 'architecture command is fixed and explicit');
    collector_assert($commands[1][0] === 'bash scripts/system/check_deployment_readiness.sh', 'deployment command is fixed and explicit');
    collector_assert(($result['repository']['working_tree_clean'] ?? false) === true, 'collector records clean revision binding');
    collector_assert(($result['attestation']['available'] ?? true) === false, 'local evidence does not claim attestation');

    $artifact = (array)($result['artifact'] ?? []);
    $validated = (new ReleaseReadinessEvidenceService())->validate((array)$result['readiness'], $artifact);
    collector_assert($validated['ok'] === true, 'collected evidence satisfies readiness structure');
    collector_assert($validated['eligible'] === false, 'collected local evidence cannot authorize eligibility');

    $fabricated = $result['readiness'];
    $fabricated['evidence'][1]['evidence']['executed'] = false;
    collector_assert((new ReleaseReadinessEvidenceService())->validate($fabricated, $artifact)['ok'] === false, 'fabricated successful execution is rejected');
    $modifiedArtifact = $result['readiness'];
    $modifiedArtifact['evidence'][1]['artifact']['package_sha256'] = str_repeat('c', 64);
    collector_assert((new ReleaseReadinessEvidenceService())->validate($modifiedArtifact, $artifact)['ok'] === false, 'modified artifact binding is rejected');

    $failedCollector = new ReleaseReadinessEvidenceCollectorService(
        static fn(string $command, string $root): array => [
            'executed' => true,
            'exit_code' => 7,
            'output_sha256' => str_repeat('b', 64),
            'output_excerpt' => 'FAIL',
        ],
        $revisionReader
    );
    $failedResult = $failedCollector->collectForChannel($channel, $source);
    collector_assert(($failedResult['readiness']['status'] ?? '') === 'failed', 'failed command observation remains failed');
    collector_assert(($failedResult['readiness']['evidence'][0]['status'] ?? '') === 'failed', 'failed architecture gate is preserved');

    $skippedCollector = new ReleaseReadinessEvidenceCollectorService(
        static fn(string $command, string $root): array => ['executed' => false, 'error' => 'runner skipped command'],
        $revisionReader
    );
    $skippedResult = $skippedCollector->collectForChannel($channel, $source);
    collector_assert(($skippedResult['readiness']['status'] ?? '') === 'blocked', 'skipped verification remains blocked');

    $mismatchedRevision = new ReleaseReadinessEvidenceCollectorService($runner, static fn(string $root): array => [
        'ok' => true,
        'revision' => 'git:other-revision',
        'working_tree_status' => '',
    ]);
    $mismatchResult = $mismatchedRevision->collectForChannel($channel, $source);
    collector_assert(($mismatchResult['readiness']['status'] ?? '') === 'blocked', 'repository revision mismatch blocks evidence');
    collector_assert(($mismatchResult['readiness']['evidence'][0]['status'] ?? '') === 'unknown', 'revision-mismatched command result is not passed');

    $dirtyRevision = new ReleaseReadinessEvidenceCollectorService($runner, static fn(string $root): array => [
        'ok' => true,
        'revision' => 'git:collector',
        'working_tree_status' => " M app/runtime.php\n",
    ]);
    $dirtyResult = $dirtyRevision->collectForChannel($channel, $source);
    collector_assert(($dirtyResult['readiness']['status'] ?? '') === 'blocked', 'dirty repository blocks artifact binding');

    $missingChannel = $collector->collectForChannel($tmp . '/missing-channel', $source);
    collector_assert($missingChannel['ok'] === false, 'missing channel cannot produce readiness evidence');
} finally {
    collector_rm_tree($tmp);
}

echo "Release readiness evidence collector probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\ReleaseReadinessEvidenceService;

$passed = 0;
$failed = 0;

function readiness_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function readiness_artifact(): array
{
    return [
        'release_version' => '1.0.0',
        'build_id' => 'git:readiness',
        'package_sha256' => str_repeat('a', 64),
        'package_size_bytes' => 123,
    ];
}

function readiness_record(string $status = 'passed', ?array $checks = null, string $authenticity = 'unverified'): array
{
    $artifact = readiness_artifact();
    $checks ??= array_map(
        static fn(string $id): array => [
            'id' => $id,
            'status' => 'passed',
            'artifact' => $artifact,
            'evidence' => readiness_execution_evidence($id),
        ],
        ['package_contract', 'architecture_gates', 'deployment_readiness']
    );
    return ['status' => $status, 'authenticity' => $authenticity, 'evidence' => $checks];
}

function readiness_execution_evidence(string $id): array
{
    if ($id === 'package_contract') {
        return ['source' => 'fixture', 'observed' => true];
    }
    return [
        'source' => 'fixture',
        'executed' => true,
        'command' => $id === 'architecture_gates'
            ? 'bash scripts/architecture/run_architecture_gates.sh'
            : 'bash scripts/system/check_deployment_readiness.sh',
        'exit_code' => 0,
        'output_sha256' => str_repeat('a', 64),
        'source_revision' => 'git:readiness',
        'working_tree_status_sha256' => str_repeat('b', 64),
    ];
}

$service = new ReleaseReadinessEvidenceService();
$valid = $service->validate(readiness_record(), readiness_artifact());
readiness_assert($valid['ok'] === true, 'explicit evidence-backed readiness shape validates');
readiness_assert($valid['eligible'] === false, 'unattested readiness is not eligible');

$empty = $service->validate(['status' => 'passed', 'evidence' => []], readiness_artifact());
readiness_assert($empty['ok'] === false, 'empty evidence with passed status is rejected');

$missing = readiness_record();
array_pop($missing['evidence']);
readiness_assert($service->validate($missing, readiness_artifact())['ok'] === false, 'missing required check is rejected');

$unknown = readiness_record();
$unknown['evidence'][] = [
    'id' => 'invented_check',
    'status' => 'passed',
    'artifact' => readiness_artifact(),
    'evidence' => ['source' => 'fixture'],
];
readiness_assert($service->validate($unknown, readiness_artifact())['ok'] === false, 'unknown check identifier is rejected');

$failedReadiness = readiness_record('passed');
$failedReadiness['evidence'][1]['status'] = 'failed';
readiness_assert($service->validate($failedReadiness, readiness_artifact())['ok'] === false, 'failed required check is rejected');

$inconsistent = readiness_record('blocked');
readiness_assert($service->validate($inconsistent, readiness_artifact())['ok'] === false, 'inconsistent overall status is rejected');

$mismatched = readiness_record();
$mismatched['evidence'][0]['artifact']['package_sha256'] = str_repeat('b', 64);
readiness_assert($service->validate($mismatched, readiness_artifact())['ok'] === false, 'mismatched artifact identity is rejected');

$malformed = readiness_record();
$malformed['evidence'][0] = 'passed';
readiness_assert($service->validate($malformed, readiness_artifact())['ok'] === false, 'malformed evidence is rejected');

$callerInjected = ['status' => 'passed', 'passed' => true, 'evidence' => []];
readiness_assert($service->validate($callerInjected, readiness_artifact())['ok'] === false, 'caller-injected success flag cannot bypass evidence');

echo "Release readiness evidence probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

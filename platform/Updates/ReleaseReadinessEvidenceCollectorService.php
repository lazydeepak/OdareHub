<?php
declare(strict_types=1);

namespace Platform\Updates;

use Throwable;

/**
 * Executes the existing local readiness commands and records their observed
 * results. Local command execution is evidence of observation, not an
 * attestation or authorization decision.
 */
final class ReleaseReadinessEvidenceCollectorService
{
    /** @var callable */
    private $runner;
    /** @var callable */
    private $revisionReader;

    public function __construct(?callable $runner = null, ?callable $revisionReader = null)
    {
        $this->runner = $runner ?? static fn(string $command, string $root): array => self::runProcess($command, $root);
        $this->revisionReader = $revisionReader ?? static fn(string $root): array => self::readRevisionProcess($root);
    }

    /**
     * Resolve and bind the current channel artifact before running checks.
     *
     * @return array<string,mixed>
     */
    public function collectForChannel(string $channelDirectory, ?string $root = null): array
    {
        $root = $root !== null && trim($root) !== '' ? rtrim($root, '/') : dirname(__DIR__, 2);
        $channelDirectory = rtrim($channelDirectory, '/');
        $channel = $this->readJson($channelDirectory . '/channel.json');
        if (!is_array($channel)) {
            return $this->failure('channel.json is missing or invalid.');
        }
        $current = $channel['lanes']['app']['current'] ?? null;
        if (!is_array($current)) {
            return $this->failure('channel app lane current release is unavailable.');
        }
        $metadataPath = $this->containedPath($channelDirectory, (string)($current['metadata'] ?? ''));
        $packagePath = $this->containedPath($channelDirectory, (string)($current['package'] ?? ''));
        if ($metadataPath === null || $packagePath === null) {
            return $this->failure('channel artifact paths are not contained.');
        }
        $metadata = $this->readJson($metadataPath);
        if (!is_array($metadata)) {
            return $this->failure('release metadata is missing or invalid.');
        }
        if (!is_file($packagePath)) {
            return $this->failure('release package is missing or unreadable.');
        }

        $package = is_array($metadata['package'] ?? null) ? $metadata['package'] : [];
        $artifact = [
            'release_version' => (string)($metadata['release_version'] ?? ''),
            'build_id' => (string)($metadata['build_id'] ?? ''),
            'package_sha256' => (string)($package['sha256'] ?? ''),
            'package_size_bytes' => (int)($package['size_bytes'] ?? 0),
        ];
        foreach (['release_version', 'build_id', 'package_sha256'] as $field) {
            if (trim((string)$artifact[$field]) === '') {
                return $this->failure('release artifact identity is incomplete: ' . $field . '.');
            }
        }
        if (preg_match('/^[a-f0-9]{64}$/', $artifact['package_sha256']) !== 1) {
            return $this->failure('release artifact checksum is invalid.');
        }
        if ((hash_file('sha256', $packagePath) ?: '') !== $artifact['package_sha256']) {
            return $this->failure('release artifact checksum does not match the channel package.');
        }
        if ((int)(filesize($packagePath) ?: 0) !== $artifact['package_size_bytes']) {
            return $this->failure('release artifact size does not match the channel package.');
        }
        foreach (['release_version', 'build_id'] as $field) {
            if (($current[$field] ?? null) !== $artifact[$field]) {
                return $this->failure('channel and release metadata differ at ' . $field . '.');
            }
        }

        $inspection = (new ReleasePackageInspectionService())->inspect($packagePath, $metadata);
        if (($inspection['ok'] ?? false) !== true) {
            return $this->failure('release package inspection failed.', ['inspection' => $inspection]);
        }

        $result = $this->collect($artifact, $root);
        $result['artifact'] = $artifact;
        $result['package'] = [
            'path' => $packagePath,
            'entry_count' => $inspection['entry_count'] ?? 0,
            'entries_sha256' => $inspection['entries_sha256'] ?? '',
        ];
        return $result;
    }

    /**
     * Collect actual fixed-command observations for an already bound artifact.
     *
     * @param array<string,mixed> $artifact
     * @return array<string,mixed>
     */
    public function collect(array $artifact, string $root): array
    {
        $errors = [];
        foreach (['release_version', 'build_id', 'package_sha256', 'package_size_bytes'] as $field) {
            if (!array_key_exists($field, $artifact) || trim((string)$artifact[$field]) === '') {
                $errors[] = 'readiness artifact binding is incomplete: ' . $field . '.';
            }
        }
        if (preg_match('/^[a-f0-9]{64}$/', (string)($artifact['package_sha256'] ?? '')) !== 1) {
            $errors[] = 'readiness artifact package checksum is invalid.';
        }

        $revision = $this->readRevision($root);
        if (($revision['ok'] ?? false) !== true) {
            $errors[] = (string)($revision['error'] ?? 'repository revision is unavailable.');
        }
        $sourceRevision = (string)($revision['revision'] ?? '');
        $workingTreeStatus = (string)($revision['working_tree_status'] ?? '');
        $bindingErrors = [];
        if ($sourceRevision === '') {
            $bindingErrors[] = 'repository revision is unavailable.';
        }
        $buildId = (string)($artifact['build_id'] ?? '');
        if (!str_starts_with($buildId, 'git:') || $buildId !== $sourceRevision) {
            $bindingErrors[] = 'release build_id does not match the observed repository revision.';
        }
        if ($workingTreeStatus !== '') {
            $bindingErrors[] = 'repository working tree is not clean; artifact-to-revision binding is not authoritative.';
        }

        $checks = [];
        $commands = [
            'architecture_gates' => 'bash scripts/architecture/run_architecture_gates.sh',
            'deployment_readiness' => 'bash scripts/system/check_deployment_readiness.sh',
        ];
        foreach ($commands as $id => $command) {
            $observation = $this->run($command, $root);
            $status = ($observation['executed'] ?? false) !== true
                ? 'unknown'
                : ((int)($observation['exit_code'] ?? 1) === 0 ? 'passed' : 'failed');
            if ($bindingErrors !== [] && $status === 'passed') {
                $status = 'unknown';
            }
            $checks[] = [
                'id' => $id,
                'status' => $status,
                'artifact' => $artifact,
                'evidence' => array_merge($observation, [
                    'command' => $command,
                    'source_revision' => $sourceRevision,
                    'working_tree_status_sha256' => hash('sha256', $workingTreeStatus),
                    'binding_errors' => $bindingErrors,
                    'authority' => 'local-command-execution-unattested',
                ]),
            ];
        }

        $packageStatus = $errors === [] ? 'passed' : 'failed';
        $checks[] = [
            'id' => 'package_contract',
            'status' => $packageStatus,
            'artifact' => $artifact,
            'evidence' => [
                'source' => 'channel-bound-package-inspection',
                'executed' => true,
                'authority' => 'local-package-inspection-unattested',
            ],
        ];

        $allPassed = $errors === [] && $bindingErrors === [];
        foreach ($checks as $check) {
            if (($check['status'] ?? null) !== 'passed') {
                $allPassed = false;
            }
        }
        $hasFailure = false;
        foreach ($checks as $check) {
            if (($check['status'] ?? null) === 'failed') {
                $hasFailure = true;
                break;
            }
        }
        $status = $allPassed ? 'passed' : ($hasFailure ? 'failed' : 'blocked');

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique(array_merge($errors, $bindingErrors))),
            'warnings' => [],
            'readiness' => [
                'status' => $status,
                'authenticity' => 'local-command-execution-unattested',
                'evidence' => $checks,
            ],
            'repository' => [
                'revision' => $sourceRevision,
                'working_tree_status_sha256' => hash('sha256', $workingTreeStatus),
                'working_tree_clean' => $workingTreeStatus === '',
            ],
            'attestation' => [
                'available' => false,
                'reason' => 'V1 has no authorized readiness attestation provider.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function readRevision(string $root): array
    {
        try {
            $value = ($this->revisionReader)($root);
            return is_array($value) ? $value : ['ok' => false, 'error' => 'repository revision reader returned malformed data.'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => 'repository revision unavailable: ' . $exception->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    private function run(string $command, string $root): array
    {
        try {
            $value = ($this->runner)($command, $root);
            return is_array($value) ? $value : ['executed' => false, 'error' => 'readiness command runner returned malformed data.'];
        } catch (Throwable $exception) {
            return ['executed' => false, 'error' => 'readiness command could not execute: ' . $exception->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    private static function readRevisionProcess(string $root): array
    {
        $revision = self::runProcess('git rev-parse HEAD', $root);
        $status = self::runProcess('git status --porcelain --untracked-files=all', $root);
        if (($revision['executed'] ?? false) !== true || (int)($revision['exit_code'] ?? 1) !== 0) {
            return ['ok' => false, 'error' => 'git revision command failed.'];
        }
        if (($status['executed'] ?? false) !== true || (int)($status['exit_code'] ?? 1) !== 0) {
            return ['ok' => false, 'error' => 'git working-tree status command failed.'];
        }
        $hash = trim((string)($revision['output_excerpt'] ?? ''));
        return [
            'ok' => preg_match('/^[a-f0-9]{40,64}$/', $hash) === 1,
            'revision' => $hash !== '' ? 'git:' . $hash : '',
            'working_tree_status' => (string)($status['output_excerpt'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private static function runProcess(string $command, string $root): array
    {
        if (!function_exists('proc_open')) {
            return ['executed' => false, 'error' => 'proc_open is unavailable.'];
        }
        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (!is_resource($process)) {
            return ['executed' => false, 'error' => 'process could not be started.'];
        }
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        return [
            'executed' => true,
            'exit_code' => $exitCode,
            'output_sha256' => hash('sha256', $stdout . $stderr),
            'output_excerpt' => substr(trim($stdout . "\n" . $stderr), 0, 2000),
        ];
    }

    /** @return array<string,mixed>|null */
    private function readJson(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $value = json_decode((string)file_get_contents($path), true);
        return is_array($value) ? $value : null;
    }

    private function containedPath(string $root, string $relative): ?string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '..')) {
            return null;
        }
        return rtrim($root, '/') . '/' . $relative;
    }

    /** @return array<string,mixed> */
    private function failure(string $message, array $details = []): array
    {
        return ['ok' => false, 'errors' => [$message], 'warnings' => [], 'details' => $details];
    }
}

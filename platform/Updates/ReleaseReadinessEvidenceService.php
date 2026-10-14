<?php
declare(strict_types=1);

namespace Platform\Updates;

/**
 * Validates the shape and artifact binding of release-build readiness evidence.
 * It does not claim that a caller-supplied record is authentic; readiness
 * eligibility remains blocked until an independently authorized verifier is
 * integrated.
 */
final class ReleaseReadinessEvidenceService
{
    /** @var array<int,string> */
    private const REQUIRED_CHECKS = ['package_contract', 'architecture_gates', 'deployment_readiness'];

    /** @var array<int,string> */
    private const CHECK_IDS = ['package_contract', 'architecture_gates', 'deployment_readiness', 'compatibility_preflight', 'recovery_evidence'];

    /**
     * @param array<string,mixed> $readiness
     * @param array<string,mixed> $artifact
     * @return array<string,mixed>
     */
    public function validate(array $readiness, array $artifact): array
    {
        $errors = [];
        $status = $readiness['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['passed', 'failed', 'blocked', 'unknown'], true)) {
            $errors[] = 'readiness status is invalid or unsupported.';
        }
        $evidence = $readiness['evidence'] ?? null;
        if (!is_array($evidence) || $evidence === []) {
            $errors[] = 'readiness evidence must contain explicit checks.';
            $evidence = [];
        }

        $checks = [];
        foreach ($evidence as $check) {
            if (!is_array($check)) {
                $errors[] = 'readiness evidence contains a malformed check.';
                continue;
            }
            $id = (string)($check['id'] ?? '');
            $checkStatus = $check['status'] ?? null;
            if (!in_array($id, self::CHECK_IDS, true)) {
                $errors[] = 'readiness evidence contains an unknown check identifier: ' . $id . '.';
            }
            if (!is_string($checkStatus) || !in_array($checkStatus, ['passed', 'failed', 'blocked', 'unknown'], true)) {
                $errors[] = 'readiness check status is invalid for ' . ($id !== '' ? $id : 'unknown') . '.';
            }
            if ($id !== '' && isset($checks[$id])) {
                $errors[] = 'readiness evidence contains duplicate check identifier: ' . $id . '.';
            }
            if (!is_array($check['evidence'] ?? null) || $check['evidence'] === []) {
                $errors[] = 'readiness check evidence is missing for ' . ($id !== '' ? $id : 'unknown') . '.';
            }
            $checkEvidence = (array)($check['evidence'] ?? []);
            if (array_key_exists('executed', $checkEvidence) || array_key_exists('authority', $checkEvidence)) {
                $this->validateExecutionEvidence($id, $checkStatus, $checkEvidence, $artifact, $errors);
            }
            if (!$this->artifactMatches((array)($check['artifact'] ?? []), $artifact)) {
                $errors[] = 'readiness check artifact binding is missing or mismatched for ' . ($id !== '' ? $id : 'unknown') . '.';
            }
            if ($id !== '') {
                $checks[$id] = $checkStatus;
            }
        }

        foreach (self::REQUIRED_CHECKS as $required) {
            if (!isset($checks[$required])) {
                $errors[] = 'required readiness check is missing: ' . $required . '.';
            }
        }

        $allChecksPassed = $errors === [] && $checks !== [];
        foreach ($checks as $checkStatus) {
            if ($checkStatus !== 'passed') {
                $allChecksPassed = false;
            }
        }
        if ($status === 'passed' && !$allChecksPassed) {
            $errors[] = 'readiness status passed contradicts check results.';
        }
        if ($status !== 'passed' && $allChecksPassed) {
            $errors[] = 'readiness status contradicts all-passed check results.';
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => [],
            // Caller-controlled metadata is never release authentication.
            'eligible' => false,
            'status' => is_string($status) ? $status : 'unknown',
            'checks' => $checks,
        ];
    }

    /** @param array<string,mixed> $evidence @param array<string,mixed> $artifact @param array<int,string> $errors */
    private function validateExecutionEvidence(string $id, mixed $status, array $evidence, array $artifact, array &$errors): void
    {
        $commands = [
            'architecture_gates' => 'bash scripts/architecture/run_architecture_gates.sh',
            'deployment_readiness' => 'bash scripts/system/check_deployment_readiness.sh',
        ];
        if (!isset($commands[$id])) {
            return;
        }
        foreach (['executed', 'command', 'exit_code', 'output_sha256', 'source_revision', 'working_tree_status_sha256'] as $field) {
            if (!array_key_exists($field, $evidence)) {
                $errors[] = 'readiness execution evidence is incomplete for ' . $id . ': ' . $field . '.';
            }
        }
        if (($evidence['executed'] ?? null) !== true) {
            $errors[] = 'readiness command was not executed for ' . $id . '.';
        }
        if (($evidence['command'] ?? null) !== $commands[$id]) {
            $errors[] = 'readiness command identity is invalid for ' . $id . '.';
        }
        if (!is_int($evidence['exit_code'] ?? null)) {
            $errors[] = 'readiness command exit code is invalid for ' . $id . '.';
        }
        if (preg_match('/^[a-f0-9]{64}$/', (string)($evidence['output_sha256'] ?? '')) !== 1) {
            $errors[] = 'readiness command output digest is invalid for ' . $id . '.';
        }
        if (trim((string)($evidence['source_revision'] ?? '')) === '') {
            $errors[] = 'readiness source revision is missing for ' . $id . '.';
        } elseif (str_starts_with((string)($artifact['build_id'] ?? ''), 'git:')
            && (string)$evidence['source_revision'] !== (string)$artifact['build_id']) {
            $errors[] = 'readiness source revision does not match the release build for ' . $id . '.';
        }
        if (preg_match('/^[a-f0-9]{64}$/', (string)($evidence['working_tree_status_sha256'] ?? '')) !== 1) {
            $errors[] = 'readiness working-tree evidence digest is invalid for ' . $id . '.';
        }
        if ($status === 'passed' && (int)($evidence['exit_code'] ?? -1) !== 0) {
            $errors[] = 'passed readiness status contradicts the command exit code for ' . $id . '.';
        }
        if ($status === 'failed' && (int)($evidence['exit_code'] ?? 0) === 0) {
            $errors[] = 'failed readiness status contradicts the command exit code for ' . $id . '.';
        }
    }

    /** @param array<string,mixed> $actual @param array<string,mixed> $expected */
    private function artifactMatches(array $actual, array $expected): bool
    {
        foreach (['release_version', 'build_id', 'package_sha256', 'package_size_bytes'] as $field) {
            if (!array_key_exists($field, $actual) || (string)$actual[$field] !== (string)($expected[$field] ?? '')) {
                return false;
            }
        }
        return true;
    }
}

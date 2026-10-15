<?php
declare(strict_types=1);

namespace Platform\Health;

use RuntimeException;

/** Executes fixed, read-only health sources; caller health claims are ignored. */
final class PostUpdateHealthVerifier
{
    private const CHECKS = [
        'release_identity', 'target_schema', 'migration_ledger', 'bootstrap',
        'application_discovery', 'critical_connectivity', 'configuration_trust',
        'protected_paths', 'unresolved_state',
    ];

    /** @param array<string,callable> $sources */
    public function __construct(private readonly array $sources)
    {
        foreach (self::CHECKS as $id) {
            if (!isset($sources[$id]) || !is_callable($sources[$id])) {
                throw new RuntimeException('missing health source: ' . $id);
            }
        }
    }

    /** @param array<string,mixed> $expected */
    public function verify(string $transactionId, string $preflightFingerprint, array $expected): PostUpdateHealthVerificationResult
    {
        $binding = is_array($expected['health_binding'] ?? null) ? $expected['health_binding'] : null;
        if ($binding === null) {
            throw new RuntimeException('health verification requires transaction health binding');
        }
        if (($expected['release_identity']['release_version'] ?? null) !== ($binding['candidate_release_version'] ?? null)
            || ($expected['release_identity']['build_id'] ?? null) !== ($binding['candidate_build_id'] ?? null)
            || ($expected['target_schema']['identity'] ?? null) !== ($binding['target_schema_identity'] ?? null)
            || ($expected['configuration_trust']['identity_fingerprint'] ?? null) !== ($binding['installation_identity_fingerprint'] ?? null)
            || ($expected['configuration_trust']['trust_fingerprint'] ?? null) !== ($binding['trust_store_fingerprint'] ?? null)
            || ($expected['migration_ledger']['plan_fingerprint'] ?? null) !== ($binding['migration_plan_fingerprint'] ?? null)) {
            throw new RuntimeException('health expectations do not match transaction binding');
        }
        $checks = [];
        $hasFailure = false;
        $hasIndeterminate = false;
        foreach (self::CHECKS as $id) {
            $source = 'runner-owned:' . $id;
            try {
                $observed = ($this->sources[$id])();
                if (!is_array($observed)) {
                    throw new RuntimeException('source returned malformed observation');
                }
                $comparison = $this->compare($id, $observed, $expected);
                $result = $comparison['ok'] ? 'verified' : 'failed';
                $hasFailure = $hasFailure || !$comparison['ok'];
                $checks[] = ['id' => $id, 'source' => $source, 'execution_result' => $result, 'evidence_digest' => self::digest($observed), 'errors' => $comparison['errors']];
            } catch (\Throwable $exception) {
                $hasIndeterminate = true;
                $checks[] = ['id' => $id, 'source' => $source, 'execution_result' => 'indeterminate', 'evidence_digest' => null, 'errors' => [$exception->getMessage()]];
            }
        }
        $status = $hasIndeterminate ? 'indeterminate' : ($hasFailure ? 'failed' : 'verified');
        return PostUpdateHealthVerificationResult::fromVerification($transactionId, $preflightFingerprint, $status, $checks, self::digest($checks), self::digest($binding), self::digest($expected));
    }

    /** @param array<string,mixed> $observed @param array<string,mixed> $expected @return array{ok:bool,errors:array<int,string>} */
    private function compare(string $id, array $observed, array $expected): array
    {
        $expectedValue = $expected[$id] ?? null;
        if (!is_array($expectedValue)) {
            return ['ok' => false, 'errors' => ['expected health binding is missing for ' . $id]];
        }
        $errors = [];
        if ($id === 'application_discovery') {
            if (($observed['applications'] ?? null) !== ($expectedValue['applications'] ?? null)) $errors[] = 'required application/module discovery differs';
        } elseif ($id === 'migration_ledger') {
            foreach (['rows', 'checksums'] as $field) if (($observed[$field] ?? null) !== ($expectedValue[$field] ?? null)) $errors[] = 'migration ' . $field . ' differs';
        } elseif ($id === 'bootstrap') {
            if (($observed['available'] ?? null) !== true) $errors[] = 'bootstrap availability was not observed';
        } elseif ($id === 'critical_connectivity') {
            if (($observed['reachable'] ?? null) !== true) $errors[] = 'critical connectivity was not observed';
        } elseif ($id === 'protected_paths') {
            if (($observed['integrity'] ?? null) !== ($expectedValue['integrity'] ?? null)) $errors[] = 'protected-path integrity differs';
        } elseif ($id === 'unresolved_state') {
            if (($observed['unresolved_migrations'] ?? true) !== false || ($observed['unresolved_recovery'] ?? true) !== false) $errors[] = 'unresolved migration or recovery state observed';
        } else {
            foreach ($expectedValue as $field => $value) if (($observed[$field] ?? null) !== $value) $errors[] = $id . ' ' . $field . ' differs';
        }
        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /** @param mixed $value */
    private static function digest(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}

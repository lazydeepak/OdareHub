<?php
declare(strict_types=1);

namespace Platform\Updates;

use Platform\Recovery\RecoveryPointService;

/**
 * Platform-owned, non-executing release coordinator preflight.
 *
 * This service joins existing evidence authorities. It is not an installer,
 * migration runner, trust writer, approval authority, or health authority.
 * Caller-supplied flags and fingerprints are never treated as authority.
 */
final class TrustedReleaseCoordinatorPreflightService
{
    public const PREFLIGHT_VERIFIED = 'preflight_verified';
    public const PREFLIGHT_BLOCKED = 'preflight_blocked';
    public const PREFLIGHT_INDETERMINATE = 'preflight_indeterminate';

    public function __construct(
        private readonly InstallationOwnedTrustStore $trustStore,
        private readonly InstallationIdentityStore $identityStore,
        ?OfflineReleaseArtifactVerifier $artifactVerifier = null,
        ?InstalledPlatformIdentityVerifier $identityVerifier = null,
        ?InstalledSystemCompatibilityPreflightService $compatibility = null
    ) {
        $this->artifactVerifier = $artifactVerifier ?? new OfflineReleaseArtifactVerifier();
        $this->identityVerifier = $identityVerifier ?? new InstalledPlatformIdentityVerifier();
        $this->compatibility = $compatibility ?? new InstalledSystemCompatibilityPreflightService();
    }

    private OfflineReleaseArtifactVerifier $artifactVerifier;
    private InstalledPlatformIdentityVerifier $identityVerifier;
    private InstalledSystemCompatibilityPreflightService $compatibility;

    /**
     * @param array<string,mixed> $identityObservations
     * @param array<string,mixed> $installedState
     * @param array<string,mixed> $migrationBinding
     * @param array<string,mixed>|null $readinessEvidence
     * @return array<string,mixed>
     */
    public function preflight(
        string $packagePath,
        string $metadataPath,
        string $statementBytes,
        ?string $detachedSignature,
        ?string $keyId,
        string $verificationTimestamp,
        array $identityObservations,
        array $installedState,
        array $migrationBinding,
        ?string $recoveryPointDirectory = null,
        ?array $readinessEvidence = null
    ): array {
        $diagnostics = [];
        $provenance = [];

        $trust = $this->trustStore->read();
        $this->record($diagnostics, $provenance, 'trust', $trust, 'installation-owned trust store read');
        if (($trust['state'] ?? null) !== 'trust_available' || ($trust['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, 'trust evidence is unavailable or not installation-owned');
        }

        $artifact = $this->artifactVerifier->verify(
            $packagePath,
            $metadataPath,
            $statementBytes,
            $detachedSignature,
            $keyId,
            (array)$trust['records'],
            $verificationTimestamp
        );
        $this->record($diagnostics, $provenance, 'artifact', $artifact, 'exact ZIP, sidecar, statement, and signature verification');
        if (($artifact['ok'] ?? false) !== true || ($artifact['state'] ?? null) !== OfflineReleaseSignatureVerifier::VERIFIED) {
            return $this->blocked($diagnostics, $provenance, 'release artifact authenticity verification failed');
        }

        $trustAgain = $this->trustStore->read();
        if (($trustAgain['fingerprint'] ?? null) !== ($trust['fingerprint'] ?? null)) {
            return $this->indeterminate($diagnostics, $provenance, 'trust evidence changed during the inspection attempt');
        }

        $artifactEvidence = is_array($artifact['artifact'] ?? null) ? $artifact['artifact'] : [];
        $metadata = is_array($artifactEvidence['metadata'] ?? null) ? $artifactEvidence['metadata'] : [];
        $packageHash = (string)($artifactEvidence['package_sha256'] ?? '');
        $statementFingerprint = hash('sha256', $statementBytes);
        if ($metadata === [] || $packageHash === '') {
            return $this->indeterminate($diagnostics, $provenance, 'authenticated artifact metadata could not be bound');
        }

        $identityBefore = $this->identityStore->read();
        $this->record($diagnostics, $provenance, 'identity_store', $identityBefore, 'installation-owned identity read');
        if (($identityBefore['state'] ?? null) !== 'identity_available' || !is_array($identityBefore['identity'] ?? null)) {
            return $this->blocked($diagnostics, $provenance, 'installation-owned identity is unavailable');
        }
        $identityAfter = $this->identityStore->read();
        if (($identityAfter['fingerprint'] ?? null) !== ($identityBefore['fingerprint'] ?? null)) {
            return $this->indeterminate($diagnostics, $provenance, 'installation identity changed during the inspection attempt');
        }
        $identityResult = $this->identityVerifier->verify([$identityBefore['identity']], $identityObservations);
        $this->record($diagnostics, $provenance, 'identity_comparison', $identityResult, 'independent installed identity comparison');
        if (($identityResult['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, 'installed identity contradicts independent observations');
        }

        $installedState['product_version'] = (string)($installedState['product_version'] ?? ($identityBefore['identity']['release_version'] ?? ''));
        $compatibilityResult = $this->compatibility->validate((array)($metadata['compatibility'] ?? []), $installedState, (string)($metadata['release_version'] ?? ''));
        $this->record($diagnostics, $provenance, 'compatibility', $compatibilityResult, 'installed compatibility and downgrade checks');
        if (($compatibilityResult['ok'] ?? false) !== true || ($compatibilityResult['eligibility_blockers'] ?? []) !== []) {
            return $this->blocked($diagnostics, $provenance, 'release compatibility or migration history is not eligible');
        }

        $migrationResult = $this->validateMigrationBinding($migrationBinding, $metadata, $packageHash, $statementFingerprint);
        $this->record($diagnostics, $provenance, 'migration_plan', $migrationResult, 'validated and authenticated migration inventory binding');
        if (($migrationResult['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, 'migration plan is malformed, unbound, or contradictory');
        }

        $recovery = $recoveryPointDirectory !== null
            ? RecoveryPointService::verifyUpdateEvidence($recoveryPointDirectory, $installedState)
            : ['ok' => false, 'state' => 'recovery_missing', 'errors' => ['recovery point directory was not supplied']];
        $this->record($diagnostics, $provenance, 'recovery', $recovery, 'recovery integrity, identity binding, and restore rehearsal');
        if (($recovery['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, 'recovery evidence is missing, mismatched, or incomplete');
        }

        if ($readinessEvidence !== null) {
            $readiness = (new ReleaseReadinessEvidenceService())->validate($readinessEvidence, [
                'release_version' => (string)($metadata['release_version'] ?? ''),
                'build_id' => (string)($metadata['build_id'] ?? ''),
                'package_sha256' => $packageHash,
                'package_size_bytes' => (int)($artifactEvidence['package_bytes'] ?? 0),
            ]);
            $this->record($diagnostics, $provenance, 'readiness', $readiness, 'existing readiness evidence shape and artifact binding');
            if (($readiness['ok'] ?? false) !== true) {
                return $this->blocked($diagnostics, $provenance, 'readiness evidence is malformed or mismatched');
            }
        }

        return [
            'state' => self::PREFLIGHT_VERIFIED,
            'preflight_verified' => true,
            'preflight_blocked' => false,
            'preflight_indeterminate' => false,
            'installation_identity_fingerprint' => (string)$identityBefore['fingerprint'],
            'candidate_package_sha256' => $packageHash,
            'release_statement_fingerprint' => $statementFingerprint,
            'verified_signing_key_id' => (string)($artifact['key_id'] ?? $keyId ?? ''),
            'trust_store_fingerprint' => (string)$trust['fingerprint'],
            'migration_plan_fingerprint' => (string)$migrationResult['plan_fingerprint'],
            'target_schema_identity' => (string)$migrationResult['target_schema'],
            'recovery_point_identity' => (string)($recovery['recovery_point_id'] ?? ''),
            'diagnostics' => $diagnostics,
            'evidence_provenance' => $provenance,
            'missing_authorization_controls' => [
                'administrator_approval_authority',
                'post_install_health_authority',
                'release_execution_authority',
            ],
            'release_authority_granted' => false,
            'update_eligible' => false,
            'apply_eligible' => false,
            'execution_authorized' => false,
            'live_mutation_performed' => false,
            'execution_prohibited' => true,
        ];
    }

    /** @param array<string,mixed> $binding @param array<string,mixed> $metadata @return array<string,mixed> */
    private function validateMigrationBinding(array $binding, array $metadata, string $packageHash, string $statementFingerprint): array
    {
        foreach (['plan', 'release_version', 'build_id', 'package_sha256', 'statement_fingerprint', 'target_schema'] as $field) {
            if (!array_key_exists($field, $binding)) {
                return ['ok' => false, 'state' => 'migration_binding_missing', 'errors' => ['missing ' . $field]];
            }
        }
        if ($binding['release_version'] !== ($metadata['release_version'] ?? null)
            || $binding['build_id'] !== ($metadata['build_id'] ?? null)
            || $binding['package_sha256'] !== $packageHash
            || $binding['statement_fingerprint'] !== $statementFingerprint) {
            return ['ok' => false, 'state' => 'migration_binding_mismatch', 'errors' => ['release metadata and migration binding differ']];
        }
        $schemaTarget = $metadata['compatibility']['schema']['target'] ?? null;
        if (!is_string($schemaTarget) || $schemaTarget === '' || $binding['target_schema'] !== $schemaTarget) {
            return ['ok' => false, 'state' => 'migration_target_schema_mismatch', 'errors' => ['target schema is not bound to authenticated metadata']];
        }
        $validation = ReleaseMigrationPlanValidator::validate(is_array($binding['plan']) ? $binding['plan'] : []);
        if (($validation['ok'] ?? false) !== true || !($validation['evidence'] ?? null) instanceof ReleaseMigrationPlanEvidence) {
            return ['ok' => false, 'state' => 'migration_plan_invalid', 'errors' => [(string)($validation['message'] ?? 'migration plan validation failed')]];
        }
        return [
            'ok' => true,
            'state' => 'migration_plan_bound',
            'plan_fingerprint' => (string)$validation['plan_fingerprint'],
            'target_schema' => (string)$binding['target_schema'],
            'authority' => 'structural-plan-validation-plus-independent-coordinator-binding',
        ];
    }

    /** @param array<int,array<string,mixed>> $diagnostics @param array<string,mixed> $provenance @param array<string,mixed> $result */
    private function record(array &$diagnostics, array &$provenance, string $gate, array $result, string $description): void
    {
        $diagnostics[] = ['gate' => $gate, 'ok' => ($result['ok'] ?? false) === true, 'state' => (string)($result['state'] ?? 'unknown'), 'description' => $description];
        $provenance[$gate] = ['source' => $description, 'state' => $result['state'] ?? 'unknown', 'evidence_digest' => hash('sha256', serialize($result))];
    }

    /** @param array<int,array<string,mixed>> $diagnostics @param array<string,mixed> $provenance */
    private function blocked(array $diagnostics, array $provenance, string $reason): array
    {
        return $this->terminal(self::PREFLIGHT_BLOCKED, false, $diagnostics, $provenance, $reason);
    }

    /** @param array<int,array<string,mixed>> $diagnostics @param array<string,mixed> $provenance */
    private function indeterminate(array $diagnostics, array $provenance, string $reason): array
    {
        return $this->terminal(self::PREFLIGHT_INDETERMINATE, true, $diagnostics, $provenance, $reason);
    }

    /** @param array<int,array<string,mixed>> $diagnostics @param array<string,mixed> $provenance */
    private function terminal(string $state, bool $indeterminate, array $diagnostics, array $provenance, string $reason): array
    {
        $diagnostics[] = ['gate' => 'coordinator', 'ok' => false, 'state' => $state, 'description' => $reason];
        return [
            'state' => $state,
            'preflight_verified' => false,
            'preflight_blocked' => !$indeterminate,
            'preflight_indeterminate' => $indeterminate,
            'diagnostics' => $diagnostics,
            'evidence_provenance' => $provenance,
            'reason' => $reason,
            'missing_authorization_controls' => ['administrator_approval_authority', 'post_install_health_authority', 'release_execution_authority'],
            'release_authority_granted' => false,
            'update_eligible' => false,
            'apply_eligible' => false,
            'execution_authorized' => false,
            'live_mutation_performed' => false,
            'execution_prohibited' => true,
        ];
    }
}

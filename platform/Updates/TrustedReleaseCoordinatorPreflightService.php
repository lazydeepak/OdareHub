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
        ?InstalledSystemCompatibilityPreflightService $compatibility = null,
        ?InstalledSystemEvidenceProvider $installedEvidenceProvider = null
    ) {
        $this->artifactVerifier = $artifactVerifier ?? new OfflineReleaseArtifactVerifier();
        $this->identityVerifier = $identityVerifier ?? new InstalledPlatformIdentityVerifier();
        $this->compatibility = $compatibility ?? new InstalledSystemCompatibilityPreflightService();
        $this->installedEvidenceProvider = $installedEvidenceProvider;
    }

    private OfflineReleaseArtifactVerifier $artifactVerifier;
    private InstalledPlatformIdentityVerifier $identityVerifier;
    private InstalledSystemCompatibilityPreflightService $compatibility;
    private ?InstalledSystemEvidenceProvider $installedEvidenceProvider;

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

        if ($this->installedEvidenceProvider === null) {
            return $this->blocked($diagnostics, $provenance, 'installed_state_authority_unavailable');
        }
        $installedEvidence = $this->installedEvidenceProvider->collect();
        $this->record($diagnostics, $provenance, 'installed_state', $installedEvidence, 'independent installed application, module, schema, and migration evidence');
        if (($installedEvidence['ok'] ?? false) !== true || !is_array($installedEvidence['state'] ?? null)) {
            return $this->blocked($diagnostics, $provenance, 'installed_state_authority_unavailable');
        }
        $authoritativeInstalledState = $installedEvidence['state'];
        $stateBinding = $this->validateInstalledStateBinding($installedState, $authoritativeInstalledState, $identityBefore['identity']);
        $this->record($diagnostics, $provenance, 'installed_state_binding', $stateBinding, 'caller state compared to authoritative installed-state observation');
        if (($stateBinding['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, (string)($stateBinding['state'] ?? 'installed state contradicts authoritative evidence'));
        }

        $migrationResult = $this->validateMigrationBinding($migrationBinding, $metadata, $packageHash, $statementFingerprint, (array)($artifact['inspection'] ?? []));
        $this->record($diagnostics, $provenance, 'migration_plan', $migrationResult, 'validated and authenticated migration inventory binding');
        if (($migrationResult['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, 'migration plan is malformed, unbound, or contradictory');
        }

        // The target schema is authenticated by the sidecar inventory above;
        // the existing compatibility service still verifies the installed
        // ledger, failed rows, downgrade rules, and component constraints.
        $authoritativeInstalledState['migrations']['evidence']['target_schema_verified'] = true;
        $authoritativeInstalledState['migrations']['evidence']['target_schema'] = $migrationResult['target_schema'];
        $compatibilityResult = $this->compatibility->validate((array)($metadata['compatibility'] ?? []), $authoritativeInstalledState, (string)($metadata['release_version'] ?? ''));
        $this->record($diagnostics, $provenance, 'compatibility', $compatibilityResult, 'installed compatibility and downgrade checks');
        if (($compatibilityResult['ok'] ?? false) !== true || ($compatibilityResult['eligibility_blockers'] ?? []) !== []) {
            return $this->blocked($diagnostics, $provenance, 'release compatibility or migration history is not eligible');
        }

        $recovery = $recoveryPointDirectory !== null
            ? RecoveryPointService::verifyUpdateEvidence($recoveryPointDirectory, $authoritativeInstalledState)
            : ['ok' => false, 'state' => 'recovery_missing', 'errors' => ['recovery point directory was not supplied']];
        $this->record($diagnostics, $provenance, 'recovery', $recovery, 'recovery integrity, identity binding, and restore rehearsal');
        if (($recovery['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, 'recovery evidence is missing, mismatched, or incomplete');
        }
        $recoveryIdentity = $this->validateRecoveryIdentity($recovery, $identityBefore['identity']);
        $this->record($diagnostics, $provenance, 'recovery_identity', $recoveryIdentity, 'complete recovery-point binding to installation-owned identity');
        if (($recoveryIdentity['ok'] ?? false) !== true) {
            return $this->blocked($diagnostics, $provenance, 'recovery identity binding is incomplete or contradictory');
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
            'candidate_build_id' => (string)($metadata['build_id'] ?? ''),
            'candidate_release_version' => (string)($metadata['release_version'] ?? ''),
            'candidate_package_sha256' => $packageHash,
            'release_statement_fingerprint' => $statementFingerprint,
            'verified_signing_key_id' => (string)($artifact['key_id'] ?? $keyId ?? ''),
            'trust_store_fingerprint' => (string)$trust['fingerprint'],
            'migration_plan_fingerprint' => (string)$migrationResult['plan_fingerprint'],
            'target_schema_identity' => (string)$migrationResult['target_schema'],
            'source_schema_identity' => (string)(($authoritativeInstalledState['schema']['identity'] ?? $identityBefore['identity']['schema_state_identity']) ?? ''),
            'recovery_point_identity' => (string)($recovery['recovery_point_id'] ?? ''),
            'recovery_independent_authority' => false,
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
    private function validateMigrationBinding(array $binding, array $metadata, string $packageHash, string $statementFingerprint, array $inspection): array
    {
        foreach (['plan', 'source_entries', 'release_version', 'build_id', 'package_sha256', 'statement_fingerprint', 'target_schema'] as $field) {
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
        $inventory = $metadata['migration_inventory'] ?? null;
        if (!is_array($inventory)) {
            return ['ok' => false, 'state' => 'migration_inventory_uncommitted', 'errors' => ['authenticated sidecar has no migration inventory commitment']];
        }
        $expectedInventory = [
            'contract_version' => ReleaseMigrationPlanValidator::PLAN_VERSION,
            'plan_id' => (string)$validation['plan_id'],
            'target_schema' => (string)$binding['target_schema'],
            'plan_fingerprint' => (string)$validation['plan_fingerprint'],
            'steps' => (array)$validation['steps'],
            'source_entries' => is_array($binding['source_entries'] ?? null) ? $binding['source_entries'] : null,
        ];
        if ($inventory !== $expectedInventory || !is_array($expectedInventory['source_entries'])) {
            return ['ok' => false, 'state' => 'migration_inventory_mismatch', 'errors' => ['caller plan does not equal the authenticated sidecar migration inventory']];
        }
        $fileHashes = is_array($inspection['file_sha256'] ?? null) ? $inspection['file_sha256'] : [];
        $identities = [];
        foreach ($expectedInventory['steps'] as $step) {
            $identities[] = $step['app_key'] . ':' . $step['migration_name'];
        }
        if (count($expectedInventory['source_entries']) !== count($identities)) {
            return ['ok' => false, 'state' => 'migration_inventory_content_unbound', 'errors' => ['migration source-entry inventory count differs from ordered plan']];
        }
        foreach ($expectedInventory['source_entries'] as $index => $entry) {
            if (!is_array($entry) || ($entry['identity'] ?? null) !== $identities[$index]
                || !is_string($entry['source_path'] ?? null) || !is_string($entry['sha256'] ?? null)
                || ($fileHashes[$entry['source_path']] ?? null) !== $entry['sha256']) {
                return ['ok' => false, 'state' => 'migration_inventory_content_unbound', 'errors' => ['migration SQL source entry is absent or hash-mismatched in the exact ZIP']];
            }
        }
        return [
            'ok' => true,
            'state' => 'migration_plan_bound',
            'plan_fingerprint' => (string)$validation['plan_fingerprint'],
            'target_schema' => (string)$binding['target_schema'],
            'authority' => 'structural-plan-validation-plus-independent-coordinator-binding',
        ];
    }

    /** @param array<string,mixed> $supplied @param array<string,mixed> $authoritative @param array<string,mixed> $identity */
    private function validateInstalledStateBinding(array $supplied, array $authoritative, array $identity): array
    {
        foreach (['product_id', 'product_version'] as $field) {
            if (!array_key_exists($field, $supplied) || (string)$supplied[$field] !== (string)($authoritative[$field] ?? '')) {
                return ['ok' => false, 'state' => 'installed_state_contradiction', 'errors' => ['supplied ' . $field . ' differs from authoritative installed evidence']];
            }
        }
        $suppliedPlatform = is_array($supplied['platform'] ?? null) ? $supplied['platform'] : [];
        $authoritativePlatform = is_array($authoritative['platform'] ?? null) ? $authoritative['platform'] : [];
        foreach (['product_id', 'version', 'build_id'] as $field) {
            if (($suppliedPlatform[$field] ?? null) !== ($authoritativePlatform[$field] ?? null)) {
                return ['ok' => false, 'state' => 'installed_state_contradiction', 'errors' => ['supplied platform ' . $field . ' differs from authoritative installed evidence']];
            }
        }
        if (($identity['product_id'] ?? null) !== ($authoritative['product_id'] ?? null)
            || ($identity['release_version'] ?? null) !== ($authoritative['product_version'] ?? null)
            || ($identity['build_id'] ?? null) !== ($authoritativePlatform['build_id'] ?? null)) {
            return ['ok' => false, 'state' => 'installed_state_identity_mismatch', 'errors' => ['authoritative installed state differs from installation-owned identity']];
        }
        $suppliedSchema = is_array($supplied['schema'] ?? null) ? $supplied['schema'] : [];
        $authoritativeSchema = is_array($authoritative['schema'] ?? null) ? $authoritative['schema'] : [];
        if (($suppliedSchema['version'] ?? null) !== ($authoritativeSchema['version'] ?? null)
            || !is_string($authoritativeSchema['identity'] ?? null) || trim($authoritativeSchema['identity']) === ''
            || ($authoritativeSchema['identity'] ?? null) !== ($identity['schema_state_identity'] ?? null)
            || ($suppliedSchema['identity'] ?? null) !== ($identity['schema_state_identity'] ?? null)) {
            return ['ok' => false, 'state' => 'installed_state_schema_mismatch', 'errors' => ['installed schema evidence is not bound to installation identity']];
        }
        foreach (['apps', 'modules'] as $field) {
            if (($supplied[$field] ?? null) !== ($authoritative[$field] ?? null)) {
                return ['ok' => false, 'state' => 'installed_state_contradiction', 'errors' => ['supplied ' . $field . ' evidence differs from authoritative installed evidence']];
            }
        }
        $suppliedMigrations = is_array($supplied['migrations'] ?? null) ? $supplied['migrations'] : [];
        $authoritativeMigrations = is_array($authoritative['migrations'] ?? null) ? $authoritative['migrations'] : [];
        foreach (['pending', 'failed', 'order'] as $field) {
            if (($suppliedMigrations[$field] ?? null) !== ($authoritativeMigrations[$field] ?? null)) {
                return ['ok' => false, 'state' => 'installed_state_contradiction', 'errors' => ['supplied migrations.' . $field . ' evidence differs from authoritative installed evidence']];
            }
        }
        $suppliedEvidence = is_array($suppliedMigrations['evidence'] ?? null) ? $suppliedMigrations['evidence'] : [];
        $authoritativeEvidence = is_array($authoritativeMigrations['evidence'] ?? null) ? $authoritativeMigrations['evidence'] : [];
        foreach (['identities_verified', 'ownership_verified', 'checksums_verified', 'ordering_verified'] as $field) {
            if (($suppliedEvidence[$field] ?? null) !== ($authoritativeEvidence[$field] ?? null)) {
                return ['ok' => false, 'state' => 'installed_state_contradiction', 'errors' => ['supplied migrations.evidence.' . $field . ' differs from authoritative installed evidence']];
            }
        }
        return ['ok' => true, 'state' => 'installed_state_bound', 'errors' => []];
    }

    /** @param array<string,mixed> $recovery @param array<string,mixed> $identity */
    private function validateRecoveryIdentity(array $recovery, array $identity): array
    {
        $installation = is_array($recovery['metadata']['installation'] ?? null) ? $recovery['metadata']['installation'] : [];
        foreach (['product_id', 'release_version', 'build_id'] as $field) {
            if (!is_string($installation[$field] ?? null) || trim($installation[$field]) === '' || $installation[$field] !== ($identity[$field] ?? null)) {
                return ['ok' => false, 'state' => 'recovery_identity_incomplete', 'errors' => ['recovery installation.' . $field . ' is missing or does not match identity']];
            }
        }
        $schemaState = is_array($installation['schema_state'] ?? null) ? $installation['schema_state'] : [];
        if (($schemaState['identity'] ?? null) !== ($identity['schema_state_identity'] ?? null)) {
            return ['ok' => false, 'state' => 'recovery_identity_incomplete', 'errors' => ['recovery schema identity is missing or does not match identity']];
        }
        return ['ok' => true, 'state' => 'recovery_identity_bound', 'errors' => []];
    }

    /** @param array<int,array<string,mixed>> $diagnostics @param array<string,mixed> $provenance @param array<string,mixed> $result */
    private function record(array &$diagnostics, array &$provenance, string $gate, array $result, string $description): void
    {
        $resultState = is_string($result['state'] ?? null) ? $result['state'] : (($result['ok'] ?? false) === true ? 'ok' : 'unavailable');
        $diagnostics[] = ['gate' => $gate, 'ok' => ($result['ok'] ?? false) === true, 'state' => $resultState, 'description' => $description, 'errors' => array_values(array_map('strval', (array)($result['errors'] ?? [])))];
        $provenance[$gate] = ['source' => $description, 'state' => $resultState, 'evidence_digest' => hash('sha256', serialize($result))];
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

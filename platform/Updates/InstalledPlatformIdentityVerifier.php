<?php
declare(strict_types=1);

namespace Platform\Updates;

use DateTimeImmutable;
use Throwable;

/**
 * Read-only verifier for the future installation-owned platform identity.
 *
 * This component accepts caller-supplied evidence only. It does not read
 * application state, persist identity, grant update eligibility, or repair
 * contradictions.
 */
final class InstalledPlatformIdentityVerifier
{
    public const RECORD_VERSION = 'odarehub.installation-identity.v1';
    public const VERIFIED = 'verified';
    public const IDENTITY_MISSING = 'identity_missing';
    public const IDENTITY_AMBIGUOUS = 'identity_ambiguous';
    public const IDENTITY_PAYLOAD_MISMATCH = 'identity_payload_mismatch';
    public const IDENTITY_RELEASE_MISMATCH = 'identity_release_mismatch';
    public const IDENTITY_SCHEMA_UNKNOWN = 'identity_schema_unknown';
    public const IDENTITY_UNAVAILABLE = 'identity_unavailable';

    /** @var list<string> */
    private const RECORD_FIELDS = [
        'installation_id',
        'product_id',
        'release_version',
        'build_id',
        'lane',
        'package_sha256',
        'release_statement_fingerprint',
        'signing_key_id',
        'installed_at',
        'schema_state_identity',
        'record_version',
    ];

    /**
     * @param list<array<string,mixed>> $identityRecords
     * @param array<string,mixed> $observations
     * @return array<string,mixed>
     */
    public function verify(array $identityRecords, array $observations): array
    {
        if ($identityRecords === []) {
            return $this->failure(self::IDENTITY_MISSING, 'No installation-owned platform identity record was supplied.');
        }
        if (count($identityRecords) !== 1 || !is_array($identityRecords[0])) {
            return $this->failure(self::IDENTITY_AMBIGUOUS, 'Exactly one current installation-owned identity record is required.');
        }

        $record = $identityRecords[0];
        $shapeFailure = $this->validateRecordShape($record);
        if ($shapeFailure !== null) {
            return $this->failure($shapeFailure['state'], $shapeFailure['message'], ['field' => $shapeFailure['field'] ?? null]);
        }

        $observationFailure = $this->validateObservationShape($observations);
        if ($observationFailure !== null) {
            return $this->failure($observationFailure['state'], $observationFailure['message'], ['field' => $observationFailure['field'] ?? null]);
        }

        $comparisonFailure = $this->compareIndependentEvidence($record, $observations);
        if ($comparisonFailure !== null) {
            return $this->failure($comparisonFailure['state'], $comparisonFailure['message'], ['field' => $comparisonFailure['field'] ?? null]);
        }

        $registry = is_array($observations['application_registry'] ?? null)
            ? $observations['application_registry']
            : null;

        return [
            'ok' => true,
            'state' => self::VERIFIED,
            'authoritative' => true,
            'authoritative_source' => 'caller_supplied_installation_identity',
            'update_eligible' => false,
            'apply_eligible' => false,
            'identity' => $record,
            'comparison_evidence' => [
                'application_registry' => $registry,
                'application_registry_is_authority' => false,
            ],
            'diagnostics' => [[
                'state' => self::VERIFIED,
                'code' => 'RII-P001',
                'message' => 'Installation-owned identity is internally consistent with independent observations.',
            ]],
        ];
    }

    /** @param array<string,mixed> $record @return array<string,string>|null */
    private function validateRecordShape(array $record): ?array
    {
        $unknown = array_diff(array_keys($record), self::RECORD_FIELDS);
        if ($unknown !== []) {
            return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Identity record contains unsupported fields.', (string)reset($unknown));
        }
        foreach (self::RECORD_FIELDS as $field) {
            if (!array_key_exists($field, $record) || !is_string($record[$field]) || trim($record[$field]) === '') {
                $state = $field === 'schema_state_identity'
                    ? self::IDENTITY_SCHEMA_UNKNOWN
                    : self::IDENTITY_MISSING;
                return $this->failureDetail($state, 'Identity record requires a non-empty ' . $field . '.', $field);
            }
        }
        if ($record['record_version'] !== self::RECORD_VERSION) {
            return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Identity record version is unsupported.', 'record_version');
        }
        if ($record['product_id'] !== 'susankhya-os' || $record['lane'] !== 'app') {
            return $this->failureDetail(self::IDENTITY_RELEASE_MISMATCH, 'Identity product or release lane is contradictory.', 'product_id');
        }
        foreach (['installation_id', 'build_id', 'signing_key_id', 'schema_state_identity'] as $field) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $record[$field]) !== 1) {
                return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Identity ' . $field . ' format is invalid.', $field);
            }
        }
        foreach (['package_sha256', 'release_statement_fingerprint'] as $field) {
            if (preg_match('/\A[0-9a-f]{64}\z/D', $record[$field]) !== 1) {
                return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Identity ' . $field . ' must be lowercase SHA-256 hex.', $field);
            }
        }
        if (!$this->validVersion($record['release_version'])) {
            return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Identity release_version is invalid.', 'release_version');
        }
        if (!$this->validUtcTimestamp($record['installed_at'])) {
            return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Identity installed_at must be a canonical UTC timestamp.', 'installed_at');
        }
        return null;
    }

    /** @param array<string,mixed> $observations @return array<string,string>|null */
    private function validateObservationShape(array $observations): ?array
    {
        foreach (['package', 'signed_release', 'schema'] as $section) {
            if (!is_array($observations[$section] ?? null)) {
                $state = $section === 'schema' ? self::IDENTITY_SCHEMA_UNKNOWN : self::IDENTITY_UNAVAILABLE;
                return $this->failureDetail($state, 'Independent ' . $section . ' observation is unavailable.', $section);
            }
        }
        $package = $observations['package'];
        if (!is_string($package['sha256'] ?? null) || preg_match('/\A[0-9a-f]{64}\z/D', $package['sha256']) !== 1) {
            return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Independent package observation is malformed.', 'package.sha256');
        }
        $signed = $observations['signed_release'];
        foreach (['product_id', 'release_version', 'build_id', 'lane', 'statement_fingerprint', 'signing_key_id'] as $field) {
            if (!is_string($signed[$field] ?? null) || trim($signed[$field]) === '') {
                return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Signed release observation requires ' . $field . '.', 'signed_release.' . $field);
            }
        }
        if (preg_match('/\A[0-9a-f]{64}\z/D', $signed['statement_fingerprint']) !== 1) {
            return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Signed release statement fingerprint is malformed.', 'signed_release.statement_fingerprint');
        }
        $schema = $observations['schema'];
        if (!is_string($schema['identity'] ?? null) || trim($schema['identity']) === '') {
            return $this->failureDetail(self::IDENTITY_SCHEMA_UNKNOWN, 'Independent schema identity is unavailable.', 'schema.identity');
        }
        return null;
    }

    /** @param array<string,mixed> $record @param array<string,mixed> $observations @return array<string,string>|null */
    private function compareIndependentEvidence(array $record, array $observations): ?array
    {
        if ($record['package_sha256'] !== $observations['package']['sha256']) {
            return $this->failureDetail(self::IDENTITY_PAYLOAD_MISMATCH, 'Identity package checksum differs from independently observed package bytes.', 'package.sha256');
        }
        $signed = $observations['signed_release'];
        foreach ([
            'product_id' => 'product_id',
            'release_version' => 'release_version',
            'build_id' => 'build_id',
            'lane' => 'lane',
            'statement_fingerprint' => 'release_statement_fingerprint',
            'signing_key_id' => 'signing_key_id',
        ] as $observed => $identityField) {
            if ($record[$identityField] !== $signed[$observed]) {
                return $this->failureDetail(self::IDENTITY_RELEASE_MISMATCH, 'Identity differs from the independently observed signed release.', 'signed_release.' . $observed);
            }
        }
        if ($record['schema_state_identity'] !== $observations['schema']['identity']) {
            return $this->failureDetail(self::IDENTITY_SCHEMA_UNKNOWN, 'Identity schema state differs from independent schema evidence.', 'schema.identity');
        }
        if (is_array($observations['application_registry'] ?? null)) {
            $registry = $observations['application_registry'];
            if (isset($registry['build_id']) && (!is_string($registry['build_id']) || trim($registry['build_id']) === '')) {
                return $this->failureDetail(self::IDENTITY_UNAVAILABLE, 'Application-registry comparison evidence is malformed.', 'application_registry.build_id');
            }
            if (isset($registry['build_id']) && $registry['build_id'] !== $record['build_id']) {
                return $this->failureDetail(self::IDENTITY_RELEASE_MISMATCH, 'Application-registry comparison evidence contradicts the authoritative identity.', 'application_registry.build_id');
            }
            if (isset($registry['version']) && $registry['version'] !== $record['release_version']) {
                return $this->failureDetail(self::IDENTITY_RELEASE_MISMATCH, 'Application-registry version contradicts the authoritative identity.', 'application_registry.version');
            }
        }
        return null;
    }

    private function validVersion(string $value): bool
    {
        return preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $value) === 1;
    }

    private function validUtcTimestamp(string $value): bool
    {
        try {
            $date = new DateTimeImmutable($value);
            return $date->format('Y-m-d\TH:i:s\Z') === $value
                && in_array($date->getTimezone()->getName(), ['UTC', 'Z'], true);
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string,mixed> $details @return array<string,string> */
    private function failureDetail(string $state, string $message, string $field): array
    {
        return ['state' => $state, 'message' => $message, 'field' => $field];
    }

    /** @param array<string,mixed> $details @return array<string,mixed> */
    private function failure(string $state, string $message, array $details = []): array
    {
        return [
            'ok' => false,
            'state' => $state,
            'authoritative' => false,
            'authoritative_source' => null,
            'update_eligible' => false,
            'apply_eligible' => false,
            'identity' => null,
            'comparison_evidence' => [],
            'diagnostics' => [[
                'state' => $state,
                'code' => 'RII-' . strtoupper(str_replace('_', '-', $state)),
                'message' => $message,
                'field' => $details['field'] ?? null,
            ]],
        ];
    }
}

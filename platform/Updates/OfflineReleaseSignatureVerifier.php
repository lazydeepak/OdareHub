<?php
declare(strict_types=1);

namespace Platform\Updates;

use DateTimeImmutable;

/**
 * Offline, in-memory Ed25519 release-statement verifier.
 *
 * It has no package, filesystem, installed-state, network, signing, or trust
 * persistence authority. Trust records are supplied by the caller.
 */
final class OfflineReleaseSignatureVerifier
{
    public const VERIFIED = 'verified';
    public const UNSIGNED = 'unsigned';
    public const INVALID_SIGNATURE = 'invalid_signature';
    public const UNTRUSTED_KEY = 'untrusted_key';
    public const REVOKED_KEY = 'revoked_key';
    public const STATEMENT_MISMATCH = 'statement_mismatch';
    public const EXPIRED_OR_NOT_YET_VALID = 'expired_or_not_yet_valid';
    public const VERIFICATION_UNAVAILABLE = 'verification_unavailable';

    private ReleaseStatementCanonicalizer $canonicalizer;
    private ?bool $sodiumAvailableOverride;

    public function __construct(?ReleaseStatementCanonicalizer $canonicalizer = null, ?bool $sodiumAvailableOverride = null)
    {
        $this->canonicalizer = $canonicalizer ?? new ReleaseStatementCanonicalizer();
        $this->sodiumAvailableOverride = $sodiumAvailableOverride;
    }

    /**
     * @param list<array<string,mixed>> $trustedKeys
     * @param array<string,mixed>|null $artifactBinding
     * @return array<string,mixed>
     */
    public function verify(
        string $statementBytes,
        ?string $detachedSignature,
        ?string $keyId,
        array $trustedKeys,
        string $verificationTimestamp,
        ?array $artifactBinding = null
    ): array {
        $parsed = $this->canonicalizer->parse($statementBytes);
        if (($parsed['ok'] ?? false) !== true) {
            return $this->result(self::STATEMENT_MISMATCH, $parsed['diagnostics'] ?? []);
        }
        $fields = $parsed['fields'];
        $verificationAt = $this->canonicalizer->parseUtcTimestamp($verificationTimestamp);
        if ($verificationAt === null) {
            return $this->result(self::VERIFICATION_UNAVAILABLE, [$this->diagnostic('RAV-VERIFICATION-TIME', 'Verification timestamp is invalid.')]);
        }
        if ($artifactBinding !== null && !$this->matchesArtifact($fields, $artifactBinding)) {
            return $this->result(self::STATEMENT_MISMATCH, [$this->diagnostic('RAV-STATEMENT-BINDING', 'Statement does not match supplied artifact evidence.')]);
        }
        $createdAt = $this->canonicalizer->parseUtcTimestamp($fields['created_at']);
        if (!$createdAt || $createdAt > $verificationAt) {
            return $this->result(self::EXPIRED_OR_NOT_YET_VALID, [$this->diagnostic('RAV-STATEMENT-TIME', 'Statement is not yet valid at the verification timestamp.')]);
        }
        if ($detachedSignature === null || $detachedSignature === '') {
            return $this->result(self::UNSIGNED, [$this->diagnostic('RAV-UNSIGNED', 'No detached signature was supplied.')]);
        }
        if (!$this->sodiumAvailable()) {
            return $this->result(self::VERIFICATION_UNAVAILABLE, [$this->diagnostic('RAV-SODIUM-UNAVAILABLE', 'Ed25519 verification capability is unavailable.')]);
        }
        $trust = $this->validateTrustRecords($trustedKeys);
        if (($trust['ok'] ?? false) !== true) {
            return $this->result(self::VERIFICATION_UNAVAILABLE, $trust['diagnostics'] ?? []);
        }
        if ($keyId === null || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,63}\z/D', $keyId)) {
            return $this->result(self::UNTRUSTED_KEY, [$this->diagnostic('RAV-KEY-UNKNOWN', 'Signing key identifier is not trusted.')]);
        }
        $record = null;
        foreach ($trust['records'] as $candidate) {
            if ($candidate['key_id'] === $keyId) {
                $record = $candidate;
                break;
            }
        }
        if ($record === null) {
            return $this->result(self::UNTRUSTED_KEY, [$this->diagnostic('RAV-KEY-UNKNOWN', 'Signing key identifier is not trusted.')], $keyId);
        }
        if ($record['status'] === 'revoked') {
            return $this->result(self::REVOKED_KEY, [$this->diagnostic('RAV-KEY-REVOKED', 'Signing key is explicitly revoked.')], $keyId);
        }
        if ($verificationAt < $record['not_before'] || ($record['not_after'] !== null && $verificationAt > $record['not_after'])) {
            return $this->result(self::EXPIRED_OR_NOT_YET_VALID, [$this->diagnostic('RAV-KEY-TIME', 'Signing key is outside its validity interval.')], $keyId);
        }
        if (strlen($detachedSignature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return $this->result(self::INVALID_SIGNATURE, [$this->diagnostic('RAV-SIGNATURE-LENGTH', 'Detached signature length is invalid.')], $keyId);
        }
        try {
            $valid = sodium_crypto_sign_verify_detached($detachedSignature, $statementBytes, $record['public_key']);
        } catch (\Throwable) {
            $valid = false;
        }
        if (!$valid) {
            return $this->result(self::INVALID_SIGNATURE, [$this->diagnostic('RAV-SIGNATURE-INVALID', 'Detached signature does not verify.')], $keyId);
        }
        return $this->result(self::VERIFIED, [$this->diagnostic('RAV-VERIFIED', 'Canonical release statement and detached signature verified.')], $keyId, $fields);
    }

    private function sodiumAvailable(): bool
    {
        return $this->sodiumAvailableOverride ?? function_exists('sodium_crypto_sign_verify_detached');
    }

    /** @param array<string,mixed> $fields @param array<string,mixed> $binding */
    private function matchesArtifact(array $fields, array $binding): bool
    {
        return isset($binding['package_sha256'], $binding['package_bytes'], $binding['metadata_sha256'])
            && is_string($binding['package_sha256'])
            && is_string($binding['metadata_sha256'])
            && (string)$binding['package_sha256'] === $fields['package_sha256']
            && (string)$binding['metadata_sha256'] === $fields['metadata_sha256']
            && (string)$binding['package_bytes'] === $fields['package_bytes'];
    }

    /** @param list<array<string,mixed>> $records @return array<string,mixed> */
    private function validateTrustRecords(array $records): array
    {
        $seenIds = [];
        $seenFingerprints = [];
        $valid = [];
        foreach ($records as $record) {
            $required = ['key_id', 'algorithm', 'public_key', 'fingerprint', 'status', 'not_before', 'not_after'];
            if (!is_array($record) || array_diff($required, array_keys($record)) !== [] || array_diff(array_keys($record), $required) !== []) {
                return ['ok' => false, 'diagnostics' => [$this->diagnostic('RAV-TRUST-MALFORMED', 'Trusted key record shape is invalid.')]];
            }
            $keyId = $record['key_id'];
            $fingerprint = $record['fingerprint'];
            if (!is_string($keyId) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,63}\z/D', $keyId) !== 1
                || isset($seenIds[$keyId]) || $record['algorithm'] !== 'ed25519'
                || !is_string($record['public_key']) || strlen($record['public_key']) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                || !is_string($fingerprint) || preg_match('/\A[0-9a-f]{64}\z/D', $fingerprint) !== 1
                || isset($seenFingerprints[$fingerprint]) || !hash_equals($fingerprint, hash('sha256', $record['public_key']))
                || !in_array($record['status'], ['active', 'revoked'], true)) {
                return ['ok' => false, 'diagnostics' => [$this->diagnostic('RAV-TRUST-MALFORMED', 'Trusted key record is malformed or inconsistent.')]];
            }
            $notBefore = is_string($record['not_before']) ? $this->canonicalizer->parseUtcTimestamp($record['not_before']) : null;
            $notAfter = $record['not_after'] === null ? null : (is_string($record['not_after']) ? $this->canonicalizer->parseUtcTimestamp($record['not_after']) : null);
            if (!$notBefore || ($record['not_after'] !== null && !$notAfter) || ($notAfter !== null && $notAfter < $notBefore)) {
                return ['ok' => false, 'diagnostics' => [$this->diagnostic('RAV-TRUST-MALFORMED', 'Trusted key validity interval is malformed.')]];
            }
            $seenIds[$keyId] = true;
            $seenFingerprints[$fingerprint] = true;
            $record['not_before'] = $notBefore;
            $record['not_after'] = $notAfter;
            $valid[] = $record;
        }
        return ['ok' => true, 'records' => $valid, 'diagnostics' => []];
    }

    /** @return array<string,mixed> */
    private function result(string $state, array $diagnostics, ?string $keyId = null, ?array $fields = null): array
    {
        $result = [
            'state' => $state,
            'verified' => $state === self::VERIFIED,
            'key_id' => $keyId,
            'diagnostics' => $diagnostics,
        ];
        if ($fields !== null) {
            $result['statement_fields'] = $fields;
        }
        return $result;
    }

    /** @return array{code:string,message:string} */
    private function diagnostic(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}

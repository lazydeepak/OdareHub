<?php
declare(strict_types=1);

namespace Platform\Updates;

use RuntimeException;
use Throwable;

/**
 * Read-only adapter that binds an exact ZIP and sidecar byte snapshot to a
 * signed release statement. It does not grant readiness or apply authority.
 */
final class OfflineReleaseArtifactVerifier
{
    private ReleaseStatementCanonicalizer $canonicalizer;
    private OfflineReleaseSignatureVerifier $signatureVerifier;
    /** @var callable|null Test-only deterministic mutation seam. */
    private $afterSnapshot;

    public function __construct(
        ?ReleaseStatementCanonicalizer $canonicalizer = null,
        ?OfflineReleaseSignatureVerifier $signatureVerifier = null,
        ?callable $afterSnapshot = null
    ) {
        $this->canonicalizer = $canonicalizer ?? new ReleaseStatementCanonicalizer();
        $this->signatureVerifier = $signatureVerifier ?? new OfflineReleaseSignatureVerifier($this->canonicalizer);
        $this->afterSnapshot = $afterSnapshot;
    }

    /**
     * @param list<array<string,mixed>> $trustedKeys
     * @return array<string,mixed>
     */
    public function verify(
        string $packagePath,
        string $metadataPath,
        string $statementBytes,
        ?string $detachedSignature,
        ?string $keyId,
        array $trustedKeys,
        string $verificationTimestamp,
        ?array $channelRecord = null
    ): array {
        $packagePath = $this->validateArtifactPath($packagePath, 'zip');
        $metadataPath = $this->validateArtifactPath($metadataPath, 'json');
        if ($packagePath === null || $metadataPath === null || dirname($packagePath) !== dirname($metadataPath) || $packagePath === $metadataPath) {
            return $this->failure('RAV-ARTIFACT-PATH', 'Release package and sidecar paths are invalid or not co-located.');
        }

        $temporaryDirectory = null;
        try {
            $packageSnapshot = $this->readStableFile($packagePath);
            $metadataSnapshot = $this->readStableFile($metadataPath);
            if ($packageSnapshot === null || $metadataSnapshot === null) {
                return $this->failure('RAV-ARTIFACT-READ', 'Release artifact bytes could not be read as stable files.');
            }
            if ($this->afterSnapshot !== null) {
                ($this->afterSnapshot)();
            }

            try {
                $metadata = json_decode($metadataSnapshot['bytes'], true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return $this->failure('RAV-METADATA-JSON', 'Release sidecar metadata is not valid JSON.');
            }
            if (!is_array($metadata)) {
                return $this->failure('RAV-METADATA-JSON', 'Release sidecar metadata must be an object.');
            }
            $metadataError = $this->validateMetadata($metadata, basename($packagePath), $packageSnapshot);
            if ($metadataError !== null) {
                return $this->failure($metadataError['code'], $metadataError['message']);
            }
            if ($channelRecord !== null) {
                $channelError = $this->validateChannelRecord($channelRecord, basename($packagePath), basename($metadataPath), $metadata, $packageSnapshot);
                if ($channelError !== null) {
                    return $this->failure($channelError['code'], $channelError['message']);
                }
            }

            $statement = $this->canonicalizer->parse($statementBytes);
            if (($statement['ok'] ?? false) !== true) {
                return $this->failure('RAV-STATEMENT-MISMATCH', 'Signed release statement is not canonical.');
            }
            $statementFields = $statement['fields'];
            foreach (['product_id', 'release_version', 'build_id', 'lane'] as $field) {
                if (($statementFields[$field] ?? null) !== ($metadata[$field] ?? null)) {
                    return $this->failure('RAV-IDENTITY-MISMATCH', 'Signed statement and sidecar identity differ at ' . $field . '.');
                }
            }

            $temporaryDirectory = $this->temporaryDirectory();
            $temporaryPackage = $temporaryDirectory . '/' . basename($packagePath);
            if (file_put_contents($temporaryPackage, $packageSnapshot['bytes'], LOCK_EX) !== strlen($packageSnapshot['bytes'])) {
                return $this->failure('RAV-ARTIFACT-TEMPORARY', 'Unable to prepare an exact temporary package snapshot.');
            }
            $inspection = (new ReleasePackageInspectionService())->inspect($temporaryPackage, $metadata);
            if (($inspection['ok'] ?? false) !== true) {
                return $this->failure('RAV-PACKAGE-INSPECTION', 'Release package inspection failed.', ['inspection' => $inspection]);
            }

            $finalPackage = $this->readStableFile($packagePath);
            $finalMetadata = $this->readStableFile($metadataPath);
            if (!$this->sameSnapshot($packageSnapshot, $finalPackage) || !$this->sameSnapshot($metadataSnapshot, $finalMetadata)) {
                return $this->failure('RAV-ARTIFACT-MUTATED', 'Release artifact bytes or file identity changed during verification.');
            }

            $signature = $this->signatureVerifier->verify(
                $statementBytes,
                $detachedSignature,
                $keyId,
                $trustedKeys,
                $verificationTimestamp,
                [
                    'package_sha256' => $packageSnapshot['sha256'],
                    'package_bytes' => (string)$packageSnapshot['size'],
                    'metadata_sha256' => $metadataSnapshot['sha256'],
                ]
            );
            if (($signature['state'] ?? null) !== OfflineReleaseSignatureVerifier::VERIFIED) {
                return [
                    'ok' => false,
                    'state' => $signature['state'] ?? OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE,
                    'verified' => false,
                    'artifact' => $this->artifactEvidence($packageSnapshot, $metadataSnapshot, $metadata),
                    'inspection' => $inspection,
                    'diagnostics' => $signature['diagnostics'] ?? [],
                ];
            }
            return [
                'ok' => true,
                'state' => OfflineReleaseSignatureVerifier::VERIFIED,
                'verified' => true,
                'artifact' => $this->artifactEvidence($packageSnapshot, $metadataSnapshot, $metadata),
                'inspection' => $inspection,
                'key_id' => $signature['key_id'] ?? $keyId,
                'diagnostics' => $signature['diagnostics'] ?? [],
            ];
        } catch (Throwable $exception) {
            return $this->failure('RAV-VERIFICATION-UNAVAILABLE', 'Release artifact verification failed closed.', ['exception_class' => $exception::class]);
        } finally {
            if ($temporaryDirectory !== null) {
                $this->removeTree($temporaryDirectory);
            }
        }
    }

    /** @return array<string,mixed>|null */
    private function validateMetadata(array $metadata, string $packageFilename, array $package): ?array
    {
        foreach (['schema_version', 'product_id', 'release_version', 'build_id', 'channel', 'lane', 'created_at', 'package'] as $field) {
            if (!array_key_exists($field, $metadata)) {
                return ['code' => 'RAV-METADATA-SHAPE', 'message' => 'Release sidecar metadata is missing ' . $field . '.'];
            }
        }
        if ($metadata['schema_version'] !== 'susankhya.release.v1' || $metadata['product_id'] !== ReleaseStatementCanonicalizer::PRODUCT_ID || $metadata['channel'] !== 'local' || $metadata['lane'] !== ReleaseStatementCanonicalizer::LANE || !is_array($metadata['package'])) {
            return ['code' => 'RAV-METADATA-IDENTITY', 'message' => 'Release sidecar metadata identity is invalid.'];
        }
        $packageMetadata = $metadata['package'];
        if (($packageMetadata['filename'] ?? null) !== $packageFilename
            || ($packageMetadata['sha256'] ?? null) !== $package['sha256']
            || (string)($packageMetadata['size_bytes'] ?? '') !== (string)$package['size']) {
            return ['code' => 'RAV-METADATA-PACKAGE-BINDING', 'message' => 'Sidecar package binding does not match exact ZIP bytes.'];
        }
        if (preg_match('/\A[0-9a-f]{64}\z/D', (string)($packageMetadata['sha256'] ?? '')) !== 1
            || !is_int($packageMetadata['size_bytes']) && !is_string($packageMetadata['size_bytes'])
            || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', (string)$packageMetadata['size_bytes']) !== 1) {
            return ['code' => 'RAV-METADATA-PACKAGE-FORMAT', 'message' => 'Sidecar package hash or size format is invalid.'];
        }
        if ($metadata['release_version'] === '' || $metadata['build_id'] === '' || $metadata['created_at'] === '') {
            return ['code' => 'RAV-METADATA-SHAPE', 'message' => 'Release sidecar identity values must not be empty.'];
        }
        return null;
    }

    /** @param array<string,mixed> $record @param array<string,mixed> $metadata @param array<string,mixed> $package */
    private function validateChannelRecord(array $record, string $packageFilename, string $metadataFilename, array $metadata, array $package): ?array
    {
        foreach (['release_version', 'build_id', 'metadata', 'package', 'sha256', 'size_bytes'] as $field) {
            if (!array_key_exists($field, $record)) {
                return ['code' => 'RAV-CHANNEL-SHAPE', 'message' => 'Channel release record is incomplete.'];
            }
        }
        if ($record['release_version'] !== $metadata['release_version'] || $record['build_id'] !== $metadata['build_id']
            || $record['metadata'] !== 'releases/' . $metadataFilename || $record['package'] !== 'releases/' . $packageFilename
            || $record['sha256'] !== $package['sha256'] || (string)$record['size_bytes'] !== (string)$package['size']) {
            return ['code' => 'RAV-CHANNEL-BINDING', 'message' => 'Channel release record does not match exact package and sidecar identity.'];
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    private function validateArtifactPath(string $path, string $extension): ?string
    {
        if ($path === '' || !str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('#(^|/)\.{1,2}(/|$)#', $path) === 1 || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== $extension || is_link($path) || !is_file($path) || !is_readable($path)) {
            return null;
        }
        $real = realpath($path);
        if ($real === false) {
            return null;
        }
        return $real;
    }

    /** @return array<string,mixed>|null */
    private function readStableFile(string $path): ?array
    {
        $before = lstat($path);
        if (!is_array($before) || !is_file($path) || is_link($path)) {
            return null;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $handleStat = fstat($handle);
            $bytes = stream_get_contents($handle);
            $after = fstat($handle);
        } finally {
            fclose($handle);
        }
        if (!is_string($bytes) || !is_array($handleStat) || !is_array($after) || !$this->sameIdentity($before, $handleStat) || !$this->sameIdentity($before, $after) || (int)$after['size'] !== strlen($bytes)) {
            return null;
        }
        return ['bytes' => $bytes, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'identity' => $this->identity($before)];
    }

    /** @param array<string,mixed> $first @param array<string,mixed>|null $second */
    private function sameSnapshot(array $first, ?array $second): bool
    {
        return $second !== null && $first['size'] === $second['size'] && hash_equals($first['sha256'], $second['sha256']) && $first['identity'] === $second['identity'];
    }

    /** @param array<string,mixed> $stat @return array<string,int> */
    private function identity(array $stat): array
    {
        return ['dev' => (int)($stat['dev'] ?? -1), 'ino' => (int)($stat['ino'] ?? -1), 'size' => (int)($stat['size'] ?? -1), 'mtime' => (int)($stat['mtime'] ?? -1), 'ctime' => (int)($stat['ctime'] ?? -1)];
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private function sameIdentity(array $left, array $right): bool
    {
        return $this->identity($left) === $this->identity($right);
    }

    private function temporaryDirectory(): string
    {
        $path = rtrim(sys_get_temp_dir(), '/') . '/odarehub-release-verify-' . bin2hex(random_bytes(12));
        if (!mkdir($path, 0700, true)) {
            throw new RuntimeException('Unable to create temporary verification directory.');
        }
        return $path;
    }

    /** @param array<string,mixed> $package @param array<string,mixed> $metadata @return array<string,mixed> */
    private function artifactEvidence(array $package, array $metadata, array $metadataObject): array
    {
        return ['package_sha256' => $package['sha256'], 'package_bytes' => (string)$package['size'], 'metadata_sha256' => $metadata['sha256'], 'release_version' => $metadataObject['release_version'], 'build_id' => $metadataObject['build_id'], 'product_id' => $metadataObject['product_id'], 'lane' => $metadataObject['lane']];
    }

    /** @param array<string,mixed> $details @return array<string,mixed> */
    private function failure(string $code, string $message, array $details = []): array
    {
        return ['ok' => false, 'state' => OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE, 'verified' => false, 'diagnostics' => [['code' => $code, 'message' => $message]], 'details' => $details];
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
            $child = $entry->getPathname();
            if ($entry->isDir() && !$entry->isLink()) {
                $this->removeTree($child);
            } elseif ($entry->isFile() || $entry->isLink()) {
                unlink($child);
            }
        }
        rmdir($path);
    }
}

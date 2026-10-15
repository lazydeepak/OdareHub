<?php
declare(strict_types=1);

namespace Platform\Updates;

use Throwable;

/**
 * Read-only reader for the installation-owned release trust envelope.
 *
 * Packaged, channel, sidecar, and candidate-supplied trust are deliberately
 * outside this class. The only write path is the disposable provisioning seam
 * used by DisposableTrustProvisioner.
 */
final class InstallationOwnedTrustStore
{
    public const STORAGE_VERSION = 'odarehub.installation-trust-storage.v1';
    public const TRUST_SOURCE = 'installation-owned';
    public const TRUST_MISSING = 'trust_missing';
    public const TRUST_MALFORMED = 'trust_malformed';
    public const TRUST_UNSAFE_PATH = 'trust_path_unsafe';
    public const TRUST_CONFLICT = 'trust_conflict';
    public const TRUST_MUTATED = 'trust_mutated_during_read';
    public const TRUST_UNAVAILABLE = 'trust_unavailable';
    public const TRUST_NOT_INDEPENDENT = 'trust_source_not_independent';

    private const TRUST_FILE = 'trust.json';
    private const LOCK_FILE = '.trust.lock';

    private string $storageRoot;
    /** @var list<string> */
    private array $forbiddenRoots;
    /** @var callable|null */
    private $afterSnapshot;

    /**
     * @param list<string> $forbiddenRoots
     * @param callable|null $afterSnapshot Test-only mutation seam.
     */
    public function __construct(string $storageRoot, array $forbiddenRoots = [], ?callable $afterSnapshot = null)
    {
        $this->storageRoot = $storageRoot;
        $this->forbiddenRoots = $forbiddenRoots;
        $this->afterSnapshot = $afterSnapshot;
    }

    /** @return array<string,mixed> */
    public function read(): array
    {
        $pathFailure = $this->validateRoot(false);
        if ($pathFailure !== null) {
            return $pathFailure;
        }
        $path = $this->storageRoot . '/' . self::TRUST_FILE;
        if (!file_exists($path)) {
            return $this->failure(self::TRUST_MISSING, 'No installation-owned trust record exists.');
        }
        if (!$this->regularFile($path)) {
            return $this->failure(self::TRUST_MALFORMED, 'Installation-owned trust path is not a regular non-symlink file.');
        }

        $before = $this->stableBytes($path);
        if ($before === null) {
            return $this->failure(self::TRUST_UNAVAILABLE, 'Installation-owned trust record could not be read.');
        }
        if ($this->afterSnapshot !== null) {
            try {
                ($this->afterSnapshot)($path);
            } catch (Throwable $exception) {
                return $this->failure(self::TRUST_MUTATED, 'Trust verification mutation seam failed closed.', ['exception' => $exception::class]);
            }
        }
        $after = $this->stableBytes($path);
        if ($after === null || !$this->sameSnapshot($before, $after)) {
            return $this->failure(self::TRUST_MUTATED, 'Installation-owned trust bytes changed during verification.');
        }

        try {
            $envelope = json_decode($before['bytes'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->failure(self::TRUST_MALFORMED, 'Installation-owned trust record is not valid JSON.');
        }
        if (!is_array($envelope)) {
            return $this->failure(self::TRUST_MALFORMED, 'Installation-owned trust record must be an object.');
        }
        $expectedEnvelope = ['storage_record_version', 'trust_source', 'records'];
        if (array_diff($expectedEnvelope, array_keys($envelope)) !== [] || array_diff(array_keys($envelope), $expectedEnvelope) !== []) {
            return $this->failure(self::TRUST_MALFORMED, 'Installation-owned trust envelope shape is unsupported.');
        }
        if ($envelope['storage_record_version'] !== self::STORAGE_VERSION) {
            return $this->failure(self::TRUST_MALFORMED, 'Installation-owned trust envelope version is unsupported.');
        }
        if ($envelope['trust_source'] !== self::TRUST_SOURCE) {
            return $this->failure(self::TRUST_NOT_INDEPENDENT, 'Trust source is not installation-owned.');
        }
        $records = $this->decodeRecords($envelope['records'] ?? null);
        if ($records === null) {
            return $this->failure(self::TRUST_MALFORMED, 'Installation-owned trust records are malformed, duplicated, or contradictory.');
        }
        return [
            'ok' => true,
            'state' => 'trust_available',
            'trust_source' => self::TRUST_SOURCE,
            'storage_record_version' => self::STORAGE_VERSION,
            'records' => $records,
            'fingerprint' => $before['sha256'],
            'authority' => [
                'signature_authenticity' => true,
                'release_authority' => false,
                'update_eligible' => false,
                'apply_eligible' => false,
            ],
        ];
    }

    /**
     * Disposable-only provisioning seam. It requires an independently
     * supplied fingerprint and explicit simulated operator approval.
     *
     * @param array<string,mixed> $record Raw verifier-shaped record.
     * @return array<string,mixed>
     */
    public function provisionDisposable(array $record, string $expectedFingerprint, bool $operatorApproved, string $expectedSource): array
    {
        if ($expectedSource !== DisposableTrustProvisioner::INDEPENDENT_EXPECTED_FINGERPRINT) {
            return $this->failure(self::TRUST_NOT_INDEPENDENT, 'Expected trust fingerprint is not independently sourced.');
        }
        if (!$operatorApproved) {
            return $this->failure(self::TRUST_CONFLICT, 'Explicit operator trust approval is required.');
        }
        $pathFailure = $this->validateRoot(true);
        if ($pathFailure !== null) {
            return $pathFailure;
        }
        $temporaryRoot = realpath(sys_get_temp_dir());
        if ($temporaryRoot === false || !($this->storageRoot === $temporaryRoot || str_starts_with($this->storageRoot, rtrim($temporaryRoot, '/') . '/'))) {
            return $this->failure(self::TRUST_UNSAFE_PATH, 'Disposable trust enrollment is restricted to a runner-owned temporary root.');
        }
        $normalized = $this->normalizeRawRecord($record);
        if ($normalized === null || !hash_equals($expectedFingerprint, $normalized['fingerprint'])) {
            return $this->failure(self::TRUST_MALFORMED, 'Trust record or independently supplied fingerprint is invalid.');
        }
        $existing = $this->read();
        if (($existing['state'] ?? null) !== self::TRUST_MISSING) {
            return $this->failure(self::TRUST_CONFLICT, 'Disposable enrollment refuses to overwrite existing trust state.', ['existing_state' => $existing['state'] ?? null]);
        }
        $lock = $this->lock();
        if ($lock === null) {
            return $this->failure(self::TRUST_CONFLICT, 'Trust storage is locked by another writer.');
        }
        try {
            $insideLock = $this->read();
            if (($insideLock['state'] ?? null) !== self::TRUST_MISSING) {
                return $this->failure(self::TRUST_CONFLICT, 'Trust state changed before enrollment.');
            }
            $envelope = [
                'storage_record_version' => self::STORAGE_VERSION,
                'trust_source' => self::TRUST_SOURCE,
                'records' => [[
                    'key_id' => $normalized['key_id'],
                    'algorithm' => 'ed25519',
                    'public_key_base64' => base64_encode($normalized['public_key']),
                    'fingerprint' => $normalized['fingerprint'],
                    'status' => $normalized['status'],
                    'not_before' => $normalized['not_before'],
                    'not_after' => $normalized['not_after'],
                ]],
            ];
            $temporary = $this->storageRoot . '/.trust.tmp-' . bin2hex(random_bytes(12)) . '.json';
            $bytes = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $handle = @fopen($temporary, 'xb');
            if ($handle === false || @fwrite($handle, $bytes) !== strlen($bytes)) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                return $this->failure(self::TRUST_UNAVAILABLE, 'Trust temporary record could not be written.');
            }
            @fflush($handle);
            @fclose($handle);
            if (!@rename($temporary, $this->storageRoot . '/' . self::TRUST_FILE)) {
                return $this->failure(self::TRUST_UNAVAILABLE, 'Trust record could not be finalized atomically.');
            }
            $readback = $this->read();
            if (($readback['state'] ?? null) !== 'trust_available' || ($readback['records'][0]['fingerprint'] ?? null) !== $normalized['fingerprint']) {
                return $this->failure(self::TRUST_UNAVAILABLE, 'Trust record read-back verification failed.');
            }
            return [
                'ok' => true,
                'state' => 'trust_enrolled_disposable',
                'fingerprint' => $normalized['fingerprint'],
                'trust_source' => self::TRUST_SOURCE,
                'operator_approval_simulated' => true,
                'production_authorization' => false,
            ];
        } catch (Throwable $exception) {
            return $this->failure(self::TRUST_UNAVAILABLE, 'Disposable trust enrollment failed closed.', ['exception' => $exception::class]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param mixed $records @return list<array<string,mixed>>|null */
    private function decodeRecords($records): ?array
    {
        if (!is_array($records) || $records === []) {
            return null;
        }
        $ids = [];
        $fingerprints = [];
        $decoded = [];
        foreach ($records as $record) {
            if (!is_array($record) || array_diff(['key_id', 'algorithm', 'public_key_base64', 'fingerprint', 'status', 'not_before', 'not_after'], array_keys($record)) !== [] || count($record) !== 7) {
                return null;
            }
            $raw = $record;
            $encoded = $record['public_key_base64'];
            if (!is_string($encoded) || $encoded === '' || base64_encode((string)base64_decode($encoded, true)) !== $encoded) {
                return null;
            }
            $raw['public_key'] = base64_decode($encoded, true);
            unset($raw['public_key_base64']);
            $normalized = $this->normalizeRawRecord($raw);
            if ($normalized === null || isset($ids[$normalized['key_id']]) || isset($fingerprints[$normalized['fingerprint']])) {
                return null;
            }
            $ids[$normalized['key_id']] = true;
            $fingerprints[$normalized['fingerprint']] = true;
            $decoded[] = $normalized;
        }
        return $decoded;
    }

    /** @param array<string,mixed> $record @return array<string,mixed>|null */
    private function normalizeRawRecord(array $record): ?array
    {
        $required = ['key_id', 'algorithm', 'public_key', 'fingerprint', 'status', 'not_before', 'not_after'];
        if (array_diff($required, array_keys($record)) !== [] || array_diff(array_keys($record), $required) !== []) {
            return null;
        }
        if (!is_string($record['key_id']) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,63}\z/D', $record['key_id']) !== 1
            || $record['algorithm'] !== 'ed25519'
            || !is_string($record['public_key']) || strlen($record['public_key']) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !is_string($record['fingerprint']) || preg_match('/\A[0-9a-f]{64}\z/D', $record['fingerprint']) !== 1
            || !hash_equals($record['fingerprint'], hash('sha256', $record['public_key']))
            || !in_array($record['status'], ['active', 'revoked'], true)
            || !is_string($record['not_before']) || !$this->canonicalTimestamp($record['not_before'])
            || ($record['not_after'] !== null && (!is_string($record['not_after']) || !$this->canonicalTimestamp($record['not_after'])))
            || ($record['not_after'] !== null && $record['not_after'] < $record['not_before'])) {
            return null;
        }
        return $record;
    }

    private function canonicalTimestamp(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d\TH:i:s\Z') === $value;
    }

    /** @return array{bytes:string,sha256:string,identity:array<string,int>}|null */
    private function stableBytes(string $path): ?array
    {
        $stat = @lstat($path);
        if (!is_array($stat) || is_link($path) || !is_file($path)) {
            return null;
        }
        $bytes = @file_get_contents($path);
        $after = @lstat($path);
        if (!is_string($bytes) || !is_array($after) || $this->identity($stat) !== $this->identity($after)) {
            return null;
        }
        return ['bytes' => $bytes, 'sha256' => hash('sha256', $bytes), 'identity' => $this->identity($stat)];
    }

    /** @param array<string,mixed> $first @param array<string,mixed> $second */
    private function sameSnapshot(array $first, array $second): bool
    {
        return hash_equals($first['sha256'], $second['sha256']) && $first['identity'] === $second['identity'];
    }

    /** @param array<string,mixed> $stat @return array<string,int> */
    private function identity(array $stat): array
    {
        return ['dev' => (int)($stat['dev'] ?? -1), 'ino' => (int)($stat['ino'] ?? -1), 'size' => (int)($stat['size'] ?? -1), 'mtime' => (int)($stat['mtime'] ?? -1), 'ctime' => (int)($stat['ctime'] ?? -1)];
    }

    private function regularFile(string $path): bool
    {
        return file_exists($path) && !is_link($path) && is_file($path);
    }

    /** @return resource|null */
    private function lock()
    {
        $path = $this->storageRoot . '/' . self::LOCK_FILE;
        if (is_link($path) || (file_exists($path) && !$this->regularFile($path))) {
            return null;
        }
        $handle = @fopen($path, 'c');
        if ($handle === false || !@flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return null;
        }
        return $handle;
    }

    /** @return array<string,mixed>|null */
    private function validateRoot(bool $create): ?array
    {
        if ($this->storageRoot === '' || !str_starts_with($this->storageRoot, '/') || str_contains($this->storageRoot, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $this->storageRoot) === 1) {
            return $this->failure(self::TRUST_UNSAFE_PATH, 'Trust storage path is unsafe.');
        }
        if ($this->pathHasEscapeSymlink($this->storageRoot)) {
            return $this->failure(self::TRUST_UNSAFE_PATH, 'Trust storage path contains a symlink escape.');
        }
        $parent = $this->nearestExistingParent($this->storageRoot);
        $canonicalParent = $parent === null ? false : realpath($parent);
        if ($canonicalParent === false) {
            return $this->failure(self::TRUST_UNSAFE_PATH, 'Trust storage path cannot be canonicalized.');
        }
        $suffix = $parent === null ? '' : substr($this->storageRoot, strlen($parent));
        $canonical = $canonicalParent . $suffix;
        $roots = array_merge([defined('APP_ROOT') ? (string)APP_ROOT : getcwd()], $this->forbiddenRoots);
        foreach ($roots as $root) {
            $realRoot = realpath((string)$root);
            if ($realRoot !== false && ($canonical === $realRoot || str_starts_with($canonical, rtrim($realRoot, '/') . '/'))) {
                return $this->failure(self::TRUST_UNSAFE_PATH, 'Trust storage cannot target the repository or a forbidden root.');
            }
        }
        $this->storageRoot = $canonical;
        if (file_exists($canonical) && !is_dir($canonical)) {
            return $this->failure(self::TRUST_UNSAFE_PATH, 'Trust storage destination is not a directory.');
        }
        if ($create && !is_dir($canonical) && !@mkdir($canonical, 0700, true) && !is_dir($canonical)) {
            return $this->failure(self::TRUST_UNSAFE_PATH, 'Trust storage directory could not be created.');
        }
        return null;
    }

    private function pathHasEscapeSymlink(string $path): bool
    {
        $current = '/';
        foreach (array_filter(explode('/', trim($path, '/')), 'strlen') as $part) {
            $current .= $part;
            if (is_link($current) && !in_array($current, ['/var', '/tmp'], true)) {
                return true;
            }
            $current .= '/';
        }
        return false;
    }

    private function nearestExistingParent(string $path): ?string
    {
        $candidate = $path;
        while ($candidate !== '/' && !file_exists($candidate)) {
            $candidate = dirname($candidate);
        }
        return file_exists($candidate) ? $candidate : null;
    }

    /** @param array<string,mixed> $details */
    private function failure(string $state, string $message, array $details = []): array
    {
        return ['ok' => false, 'state' => $state, 'message' => $message, 'details' => $details, 'records' => [], 'authority' => ['release_authority' => false, 'update_eligible' => false, 'apply_eligible' => false]];
    }
}

<?php
declare(strict_types=1);

namespace Platform\Updates;

use Throwable;

/**
 * Runner-safe, installation-owned filesystem persistence for the platform
 * identity record.
 *
 * This store is deliberately not a release coordinator. It accepts an
 * identity only after independent OH-AUTO1-070 verification, performs no
 * eligibility calculation, and never grants update or apply authority.
 */
final class InstallationIdentityStore
{
    public const STORAGE_VERSION = 'odarehub.installation-identity-storage.v1';
    public const IDENTITY_MISSING = 'identity_missing';
    public const IDENTITY_CONFLICT = 'identity_conflict';
    public const IDENTITY_PATH_UNSAFE = 'identity_path_unsafe';
    public const IDENTITY_LOCK_UNAVAILABLE = 'identity_lock_unavailable';
    public const IDENTITY_WRITE_FAILED = 'identity_write_failed';
    public const IDENTITY_PREDECESSOR_FAILED = 'identity_predecessor_failed';
    public const IDENTITY_INTERRUPTED_WRITE = 'identity_interrupted_write';
    public const IDENTITY_READBACK_MISMATCH = 'identity_readback_mismatch';
    public const IDENTITY_UNAVAILABLE = 'identity_unavailable';
    public const IDENTITY_INSTALLATION_MISMATCH = 'identity_installation_mismatch';
    public const IDENTITY_PREVIOUS_RETAINED = 'previous_identity_retained';
    public const IDENTITY_CANDIDATE_COMMITTED = 'candidate_identity_committed';
    public const IDENTITY_INDETERMINATE = 'identity_state_indeterminate';

    private const CURRENT_FILE = 'identity.json';
    private const LOCK_FILE = '.identity.lock';
    private const PREDECESSOR_DIRECTORY = 'predecessors';
    private const TEMP_PREFIX = '.identity.tmp-';

    private string $storageRoot;
    /** @var list<string> */
    private array $forbiddenRoots;
    /** @var callable|null */
    private $faultInjector;
    private InstalledPlatformIdentityVerifier $verifier;

    /**
     * @param list<string> $forbiddenRoots Roots which may not contain storage.
     * @param callable|null $faultInjector Receives a stage name and may throw.
     */
    public function __construct(
        string $storageRoot,
        array $forbiddenRoots = [],
        ?callable $faultInjector = null,
        ?InstalledPlatformIdentityVerifier $verifier = null
    ) {
        $this->storageRoot = $storageRoot;
        $this->forbiddenRoots = $forbiddenRoots;
        $this->faultInjector = $faultInjector;
        $this->verifier = $verifier ?? new InstalledPlatformIdentityVerifier();
    }

    /** @return array<string,mixed> */
    public function read(): array
    {
        $pathFailure = $this->validateStoragePath(false);
        if ($pathFailure !== null) {
            return $pathFailure;
        }

        $temporary = $this->temporaryFiles();
        if ($temporary === null) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage directory is unsafe.');
        }
        if ($temporary !== []) {
            return $this->failure(self::IDENTITY_INTERRUPTED_WRITE, 'An incomplete identity write requires controlled recovery.', [
                'temporary_files' => $temporary,
            ]);
        }

        $currentPath = $this->storageRoot . '/' . self::CURRENT_FILE;
        if (!file_exists($currentPath)) {
            return $this->failure(self::IDENTITY_MISSING, 'No current installation identity exists.');
        }
        if (!$this->isRegularNonSymlink($currentPath)) {
            return $this->failure(self::IDENTITY_UNAVAILABLE, 'Current identity is not a regular non-symlink file.');
        }

        $payload = $this->readJsonFile($currentPath);
        if ($payload === null || !isset($payload['identity']) || !is_array($payload['identity'])) {
            return $this->failure(self::IDENTITY_UNAVAILABLE, 'Current identity storage record is malformed.');
        }
        if (($payload['storage_record_version'] ?? null) !== self::STORAGE_VERSION) {
            return $this->failure(self::IDENTITY_UNAVAILABLE, 'Current identity storage version is unsupported.');
        }

        return [
            'ok' => true,
            'state' => 'identity_available',
            'identity' => $payload['identity'],
            'storage_record_version' => self::STORAGE_VERSION,
            'fingerprint' => $this->fingerprint($payload['identity']),
            'predecessor' => $payload['predecessor'] ?? null,
            'authority' => [
                'update_eligible' => false,
                'apply_eligible' => false,
                'release_authority_granted' => false,
            ],
        ];
    }

    /**
     * Create the first identity only. Existing current state is never
     * overwritten implicitly.
     *
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $observations
     * @return array<string,mixed>
     */
    public function create(array $identity, array $observations): array
    {
        return $this->write($identity, $observations, null, false);
    }

    /**
     * Replace the current identity only when the caller supplies the exact
     * current fingerprint observed before coordination. This is a controlled
     * persistence operation, not release-completion authorization.
     *
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $observations
     * @return array<string,mixed>
     */
    public function replace(array $identity, array $observations, string $expectedCurrentFingerprint): array
    {
        if ($expectedCurrentFingerprint === '') {
            return $this->failure(self::IDENTITY_CONFLICT, 'Controlled replacement requires the expected current fingerprint.');
        }
        return $this->write($identity, $observations, $expectedCurrentFingerprint, true);
    }

    /** @return array<string,mixed> */
    private function write(array $identity, array $observations, ?string $expectedFingerprint, bool $replacement): array
    {
        $pathFailure = $this->validateStoragePath(true);
        if ($pathFailure !== null) {
            return $pathFailure;
        }

        $verification = $this->verifier->verify([$identity], $observations);
        if (($verification['ok'] ?? false) !== true) {
            return $this->failure((string)($verification['state'] ?? InstalledPlatformIdentityVerifier::IDENTITY_UNAVAILABLE), 'Identity evidence was rejected before persistence.', [
                'verification' => $verification,
            ]);
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            return $this->failure(self::IDENTITY_LOCK_UNAVAILABLE, 'Identity storage is currently locked by another writer.');
        }

        $finalizationOccurred = false;
        try {
            $current = $this->read();
            $currentExists = ($current['state'] ?? null) === 'identity_available';
            if (($current['state'] ?? null) === self::IDENTITY_INTERRUPTED_WRITE) {
                return $this->failure(self::IDENTITY_INTERRUPTED_WRITE, 'Incomplete identity write requires controlled recovery before another write.');
            }
            if (!$currentExists && ($current['state'] ?? null) !== self::IDENTITY_MISSING) {
                return $this->failure((string)($current['state'] ?? self::IDENTITY_UNAVAILABLE), 'Existing identity state is not safely interpretable; no write was attempted.', [
                    'current_state' => $current['state'] ?? null,
                ]);
            }
            if (!$replacement && $currentExists) {
                return $this->failure(self::IDENTITY_CONFLICT, 'Initial identity creation refuses to overwrite the current record.', [
                    'current_fingerprint' => $current['fingerprint'] ?? null,
                ]);
            }
            if ($replacement && !$currentExists) {
                return $this->failure(self::IDENTITY_CONFLICT, 'Controlled replacement requires an existing current identity.');
            }
            if ($replacement && ($current['fingerprint'] ?? null) !== $expectedFingerprint) {
                return $this->failure(self::IDENTITY_CONFLICT, 'Current identity changed before controlled replacement.');
            }
            if ($replacement && ($current['identity']['installation_id'] ?? null) !== ($identity['installation_id'] ?? null)) {
                return $this->failure(self::IDENTITY_INSTALLATION_MISMATCH, 'Replacement cannot change the authoritative installation ID.');
            }

            $previousPayload = $currentExists ? $this->readJsonFile($this->storageRoot . '/' . self::CURRENT_FILE) : null;
            $previousIdentity = $currentExists ? ($current['identity'] ?? null) : null;
            $previousFingerprint = $currentExists ? ($current['fingerprint'] ?? null) : null;
            $this->fault('before_predecessor');
            $predecessor = null;
            if ($currentExists && is_array($previousPayload) && is_array($previousIdentity) && is_string($previousFingerprint)) {
                $predecessor = $this->preservePredecessor($previousPayload, $previousFingerprint);
                if ($predecessor === null) {
                    return $this->failure(self::IDENTITY_PREDECESSOR_FAILED, 'Unable to preserve the predecessor identity before replacement.');
                }
            }

            $payload = [
                'storage_record_version' => self::STORAGE_VERSION,
                'identity' => $identity,
                'predecessor' => $predecessor,
                'written_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ];
            $temporaryPath = $this->storageRoot . '/' . self::TEMP_PREFIX . bin2hex(random_bytes(12)) . '.json';
            $this->fault('before_temp_write');
            if (!$this->writeJson($temporaryPath, $payload)) {
                return $this->failure(self::IDENTITY_WRITE_FAILED, 'Unable to write the temporary identity record.');
            }
            $this->fault('after_temp_write');
            $this->fault('before_finalize');
            if (!@rename($temporaryPath, $this->storageRoot . '/' . self::CURRENT_FILE)) {
                return $this->failure(self::IDENTITY_WRITE_FAILED, 'Unable to atomically finalize the identity record.', ['temporary_path' => $temporaryPath]);
            }
            $finalizationOccurred = true;
            $this->fault('after_finalize');

            $readback = $this->readJsonFile($this->storageRoot . '/' . self::CURRENT_FILE);
            if (!is_array($readback) || ($readback['identity'] ?? null) !== $identity) {
                $this->fault('before_rollback');
                $rollback = $this->rollback($previousPayload, $currentExists);
                if (!$rollback) {
                    return $this->failure(self::IDENTITY_INDETERMINATE, 'Read-back mismatch could not be rolled back; controlled recovery is required.', [
                        'rollback' => false,
                        'finalization_occurred' => true,
                    ]);
                }
                return $this->failure(self::IDENTITY_READBACK_MISMATCH, 'Post-write identity read-back did not match the requested record.', [
                    'rollback' => true,
                    'state_after_recovery' => $currentExists ? self::IDENTITY_PREVIOUS_RETAINED : self::IDENTITY_MISSING,
                ]);
            }
            $this->fault('after_readback');

            return [
                'ok' => true,
                'state' => $replacement ? 'identity_replaced' : 'identity_created',
                'identity' => $identity,
                'fingerprint' => $this->fingerprint($identity),
                'predecessor' => $predecessor,
                'authority' => [
                    'update_eligible' => false,
                    'apply_eligible' => false,
                    'release_authority_granted' => false,
                ],
            ];
        } catch (Throwable $exception) {
            if ($finalizationOccurred) {
                return $this->reconcileFinalization($identity, $exception->getMessage());
            }
            return $this->failure(self::IDENTITY_WRITE_FAILED, 'Identity persistence stopped before controlled completion: ' . $exception->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string,mixed> $candidate */
    private function reconcileFinalization(array $candidate, string $cause): array
    {
        $onDisk = $this->readJsonFile($this->storageRoot . '/' . self::CURRENT_FILE);
        if (is_array($onDisk)
            && ($onDisk['storage_record_version'] ?? null) === self::STORAGE_VERSION
            && ($onDisk['identity'] ?? null) === $candidate
        ) {
            return $this->failure(self::IDENTITY_CANDIDATE_COMMITTED, 'Candidate identity was atomically committed and verified after a post-finalization interruption.', [
                'finalization_occurred' => true,
                'cause' => $cause,
                'identity' => $candidate,
            ]);
        }

        return $this->failure(self::IDENTITY_INDETERMINATE, 'Finalization occurred but authoritative identity state could not be reconciled; controlled recovery is required.', [
            'finalization_occurred' => true,
            'cause' => $cause,
        ]);
    }

    /** @return resource|null */
    private function acquireLock()
    {
        $path = $this->storageRoot . '/' . self::LOCK_FILE;
        if (is_link($path) || (file_exists($path) && !$this->isRegularNonSymlink($path))) {
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

    /** @param array<string,mixed>|null $payload */
    private function preservePredecessor(array $payload, string $fingerprint): ?array
    {
        $directory = $this->storageRoot . '/' . self::PREDECESSOR_DIRECTORY;
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return null;
        }
        if (!$this->directoryHasNoSymlinkComponents($directory)) {
            return null;
        }
        $path = $directory . '/' . $fingerprint . '.json';
        if (file_exists($path)) {
            if (!$this->isRegularNonSymlink($path)) {
                return null;
            }
            $existing = $this->readJsonFile($path);
            if (!is_array($existing) || ($existing['identity'] ?? null) !== ($payload['identity'] ?? null)) {
                return null;
            }
            return ['path' => self::PREDECESSOR_DIRECTORY . '/' . $fingerprint . '.json', 'fingerprint' => $fingerprint];
        }
        if (!$this->writeJsonExclusive($path, $payload)) {
            return null;
        }
        return ['path' => self::PREDECESSOR_DIRECTORY . '/' . $fingerprint . '.json', 'fingerprint' => $fingerprint];
    }

    /** @param array<string,mixed>|null $previousPayload */
    private function rollback(?array $previousPayload, bool $hadPrevious): bool
    {
        $currentPath = $this->storageRoot . '/' . self::CURRENT_FILE;
        if (!$hadPrevious) {
            return !file_exists($currentPath) || @unlink($currentPath);
        }
        if ($previousPayload === null) {
            return false;
        }
        $temporary = $this->storageRoot . '/' . self::TEMP_PREFIX . 'rollback-' . bin2hex(random_bytes(8)) . '.json';
        if (!$this->writeJson($temporary, $previousPayload)) {
            return false;
        }
        return @rename($temporary, $currentPath);
    }

    /** @return array<string,mixed>|null */
    private function readJsonFile(string $path): ?array
    {
        if (!$this->isRegularNonSymlink($path)) {
            return null;
        }
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $payload */
    private function writeJson(string $path, array $payload): bool
    {
        $bytes = $this->encode($payload);
        if ($bytes === null) {
            return false;
        }
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            return false;
        }
        $written = @fwrite($handle, $bytes);
        @fflush($handle);
        @fclose($handle);
        return $written === strlen($bytes);
    }

    /** @param array<string,mixed> $payload */
    private function writeJsonExclusive(string $path, array $payload): bool
    {
        if (file_exists($path)) {
            return false;
        }
        return $this->writeJson($path, $payload);
    }

    /** @param array<string,mixed> $payload */
    private function encode(array $payload): ?string
    {
        try {
            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            return $encoded . "\n";
        } catch (Throwable) {
            return null;
        }
    }

    private function fingerprint(array $identity): string
    {
        return hash('sha256', $this->encode($identity) ?? '');
    }

    /** @return array<string,mixed>|null */
    private function validateStoragePath(bool $create): ?array
    {
        if ($this->storageRoot === '' || $this->storageRoot[0] !== '/' || str_contains($this->storageRoot, "\0")) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage path must be absolute and NUL-free.');
        }
        $normalized = $this->normalizePath($this->storageRoot);
        if ($normalized === null || $normalized !== $this->storageRoot) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage path contains traversal or non-canonical segments.');
        }
        if (!$this->requestedPathHasSafeSymlinks($normalized)) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage path contains a symlink escape.');
        }
        if (file_exists($normalized)) {
            if (is_link($normalized)) {
                return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage destination may not be a symlink.');
            }
            $canonical = realpath($normalized);
        } else {
            $parent = $this->nearestExistingParent($normalized);
            $canonicalParent = $parent === null ? false : realpath($parent);
            $suffix = $parent === null ? '' : substr($normalized, strlen($parent));
            $canonical = $canonicalParent === false ? false : $canonicalParent . $suffix;
        }
        if ($canonical === false || !is_string($canonical)) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage path cannot be canonicalized.');
        }
        $this->storageRoot = $canonical;
        $normalized = $canonical;
        $roots = array_merge([defined('APP_ROOT') ? (string)APP_ROOT : getcwd()], $this->forbiddenRoots);
        foreach ($roots as $root) {
            $rootNormalized = $this->canonicalExistingPath((string)$root);
            if ($rootNormalized !== null && $this->isWithin($normalized, $rootNormalized)) {
                return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage cannot target the repository or a forbidden installation root.');
            }
        }
        $existing = file_exists($normalized) ? $normalized : $this->nearestExistingParent($normalized);
        if ($existing === null || !$this->directoryHasNoSymlinkComponents($existing)) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage path contains a symlink escape.');
        }
        if (file_exists($normalized) && !is_dir($normalized)) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage destination is not a directory.');
        }
        if ($create && !is_dir($normalized) && !@mkdir($normalized, 0700, true) && !is_dir($normalized)) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage directory could not be created.');
        }
        if (is_dir($normalized) && !$this->directoryHasNoSymlinkComponents($normalized)) {
            return $this->failure(self::IDENTITY_PATH_UNSAFE, 'Identity storage directory contains a symlink escape.');
        }
        return null;
    }

    private function normalizePath(string $path): ?string
    {
        if (str_contains($path, "\0")) {
            return null;
        }
        $parts = explode('/', $path);
        $output = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($output === []) {
                    return null;
                }
                array_pop($output);
                continue;
            }
            $output[] = $part;
        }
        return '/' . implode('/', $output);
    }

    private function canonicalExistingPath(string $path): ?string
    {
        $real = realpath($path);
        return $real === false ? null : $real;
    }

    private function nearestExistingParent(string $path): ?string
    {
        $candidate = $path;
        while ($candidate !== '/' && !file_exists($candidate)) {
            $candidate = dirname($candidate);
        }
        return file_exists($candidate) ? $candidate : null;
    }

    private function directoryHasNoSymlinkComponents(string $path): bool
    {
        $absolute = $this->normalizePath($path);
        if ($absolute === null) {
            return false;
        }
        $current = '/';
        foreach (array_filter(explode('/', trim($absolute, '/')), 'strlen') as $part) {
            $current .= $part;
            if (is_link($current)) {
                return false;
            }
            $current .= '/';
        }
        return true;
    }

    private function requestedPathHasSafeSymlinks(string $path): bool
    {
        $current = '/';
        foreach (array_filter(explode('/', trim($path, '/')), 'strlen') as $part) {
            $current .= $part;
            if (is_link($current) && !in_array($current, ['/var', '/tmp'], true)) {
                return false;
            }
            $current .= '/';
        }
        return true;
    }

    private function isRegularNonSymlink(string $path): bool
    {
        return file_exists($path) && !is_link($path) && is_file($path);
    }

    private function isWithin(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }

    /** @return list<string>|null */
    private function temporaryFiles(): ?array
    {
        if (!is_dir($this->storageRoot)) {
            return [];
        }
        $entries = @scandir($this->storageRoot);
        if ($entries === false) {
            return null;
        }
        $files = [];
        foreach ($entries as $entry) {
            if (str_starts_with($entry, self::TEMP_PREFIX)) {
                $path = $this->storageRoot . '/' . $entry;
                if (!$this->isRegularNonSymlink($path)) {
                    return null;
                }
                $files[] = $entry;
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function fault(string $stage): void
    {
        if ($this->faultInjector !== null) {
            ($this->faultInjector)($stage, $this->storageRoot);
        }
    }

    /** @param array<string,mixed> $details */
    private function failure(string $state, string $message, array $details = []): array
    {
        return [
            'ok' => false,
            'state' => $state,
            'message' => $message,
            'details' => $details,
            'identity' => null,
            'authority' => [
                'update_eligible' => false,
                'apply_eligible' => false,
                'release_authority_granted' => false,
            ],
        ];
    }
}

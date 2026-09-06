<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Exclusive snapshot writer for claimed deletion targets.
 * It copies into a staging directory, verifies SHA-256 checksums, writes one
 * immutable manifest, then atomically renames the staging directory.
 */
final class StudioDeletionSnapshotStore
{
    public const MANIFEST_VERSION = 'studio.deletion-snapshot-manifest.v1';
    public const MANIFEST_FILENAME = '.studio-snapshot-manifest.json';

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    public static function create(array $metadata, ?string $root = null): array
    {
        $sourcePath = self::normalizePath((string)($metadata['snapshot']['source_path'] ?? ''));
        $destinationPath = self::normalizePath((string)($metadata['snapshot']['destination_path'] ?? ''));
        $snapshotId = trim((string)($metadata['snapshot_id'] ?? ''));
        if ($sourcePath === '' || $destinationPath === '' || $snapshotId === '') {
            throw new \RuntimeException('Snapshot storage requires source path, destination path, and snapshot id.');
        }
        if (!str_starts_with($destinationPath, 'storage/studio/deletion-execution-snapshots/')) {
            throw new \RuntimeException('Snapshot destination is outside Studio snapshot storage.');
        }
        if ($sourcePath === $destinationPath
            || str_starts_with($destinationPath . '/', $sourcePath . '/')
            || str_starts_with($sourcePath . '/', $destinationPath . '/')) {
            throw new \RuntimeException('Snapshot source and destination overlap.');
        }

        $repositoryRoot = self::repositoryRoot($root);
        $repositoryReal = realpath($repositoryRoot);
        if ($repositoryReal === false || !is_dir($repositoryReal)) {
            throw new \RuntimeException('Repository root is unavailable for snapshot creation.');
        }

        $sourceAbsolute = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourcePath);
        $sourceReal = realpath($sourceAbsolute);
        if ($sourceReal === false || !file_exists($sourceReal)) {
            throw new \RuntimeException('Snapshot source does not exist.');
        }
        if (!self::isWithin($sourceReal, $repositoryReal)) {
            throw new \RuntimeException('Snapshot source resolves outside the repository root.');
        }
        if (is_link($sourceAbsolute)) {
            throw new \RuntimeException('Snapshot source symlinks are not supported.');
        }
        if (!is_readable($sourceReal)) {
            throw new \RuntimeException('Snapshot source is not readable.');
        }

        $destinationAbsolute = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destinationPath);
        $destinationParent = dirname($destinationAbsolute);
        if (!is_dir($destinationParent) && !mkdir($destinationParent, 0775, true) && !is_dir($destinationParent)) {
            throw new \RuntimeException('Unable to create snapshot destination parent.');
        }
        $destinationParentReal = realpath($destinationParent);
        $snapshotRoot = $repositoryRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR . 'deletion-execution-snapshots';
        $snapshotRootReal = realpath($snapshotRoot);
        if ($destinationParentReal === false || $snapshotRootReal === false || !self::isWithin($destinationParentReal, $snapshotRootReal)) {
            throw new \RuntimeException('Snapshot destination parent resolves outside Studio snapshot storage.');
        }
        if (file_exists($destinationAbsolute) || is_link($destinationAbsolute)) {
            throw new \RuntimeException('Snapshot destination already exists and cannot be overwritten.');
        }

        $lockPath = $destinationAbsolute . '.snapshot-lock';
        if (!@mkdir($lockPath, 0700)) {
            throw new \RuntimeException('Snapshot destination is locked by another creation attempt.');
        }

        $stagingPath = $destinationAbsolute . '.tmp-' . bin2hex(random_bytes(8));
        try {
            if (file_exists($destinationAbsolute) || is_link($destinationAbsolute)) {
                throw new \RuntimeException('Snapshot destination already exists and cannot be overwritten.');
            }
            if (!mkdir($stagingPath, 0700)) {
                throw new \RuntimeException('Unable to create snapshot staging directory.');
            }

            $payloadRoot = $stagingPath . DIRECTORY_SEPARATOR . 'payload';
            if (!mkdir($payloadRoot, 0700)) {
                throw new \RuntimeException('Unable to create snapshot payload directory.');
            }
            $payloadName = basename($sourcePath);
            if ($payloadName === '' || $payloadName === '.' || $payloadName === '..') {
                $payloadName = 'target';
            }
            $payloadAbsolute = $payloadRoot . DIRECTORY_SEPARATOR . $payloadName;
            $stats = ['file_count' => 0, 'directory_count' => 0, 'total_bytes' => 0, 'files' => []];
            self::copyNode($sourceReal, $payloadAbsolute, 'payload/' . $payloadName, $stats);
            usort($stats['files'], static fn(array $left, array $right): int => strcmp((string)$left['path'], (string)$right['path']));
            $treeChecksum = hash('sha256', self::canonicalJson($stats['files']));

            $manifest = array_merge($metadata, [
                'status' => 'recorded',
                'snapshot_state' => 'created',
                'manifest_version' => self::MANIFEST_VERSION,
                'immutable' => 'yes',
                'append_only' => 'yes',
                'atomic_publish' => 'yes',
                'can_execute' => 'no',
                'can_apply' => 'no',
                'can_archive' => 'no',
                'can_delete' => 'no',
                'execution_authorized' => 'no',
                'grants_execution_authority' => 'no',
                'requires_separate_execution_capability' => 'yes',
                'snapshot' => array_merge(
                    is_array($metadata['snapshot'] ?? null) ? $metadata['snapshot'] : [],
                    [
                        'payload_path' => $destinationPath . '/payload/' . $payloadName,
                        'manifest_path' => $destinationPath . '/' . self::MANIFEST_FILENAME,
                        'copy_mode' => 'copy_only',
                        'overwrite_allowed' => 'no',
                        'checksum_algorithm' => 'sha256',
                    ]
                ),
                'summary' => [
                    'file_count' => (int)$stats['file_count'],
                    'directory_count' => (int)$stats['directory_count'],
                    'total_bytes' => (int)$stats['total_bytes'],
                    'tree_checksum_sha256' => $treeChecksum,
                ],
                'files' => $stats['files'],
            ]);
            $fingerprintMaterial = $manifest;
            unset($fingerprintMaterial['manifest_fingerprint'], $fingerprintMaterial['storage']);
            $manifest['manifest_fingerprint'] = 'deletion-snapshot-manifest:' . substr(sha1(self::canonicalJson($fingerprintMaterial)), 0, 24);

            $manifestPath = $stagingPath . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
            $manifestHandle = @fopen($manifestPath, 'x+b');
            if ($manifestHandle === false) {
                throw new \RuntimeException('Unable to create immutable snapshot manifest.');
            }
            $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded)) {
                fclose($manifestHandle);
                throw new \RuntimeException('Unable to encode snapshot manifest.');
            }
            $encoded .= "\n";
            try {
                self::writeAll($manifestHandle, $encoded);
                fflush($manifestHandle);
            } finally {
                fclose($manifestHandle);
            }

            if (!@rename($stagingPath, $destinationAbsolute)) {
                throw new \RuntimeException('Unable to atomically publish snapshot destination.');
            }

            $manifest['storage'] = [
                'storage_scope' => 'studio_snapshot',
                'relative_path' => $destinationPath,
                'manifest_relative_path' => $destinationPath . '/' . self::MANIFEST_FILENAME,
                'immutable' => 'yes',
                'append_only' => 'yes',
                'atomic_publish' => 'yes',
            ];
            return $manifest;
        } catch (\Throwable $exception) {
            if (is_dir($stagingPath)) {
                self::removeTree($stagingPath);
            }
            throw $exception;
        } finally {
            @rmdir($lockPath);
        }
    }

    /** @return array<string,mixed>|null */
    public static function read(string $destinationPath, ?string $root = null): ?array
    {
        $destinationPath = self::normalizePath($destinationPath);
        if ($destinationPath === '' || !str_starts_with($destinationPath, 'storage/studio/deletion-execution-snapshots/')) {
            return null;
        }
        $repositoryRoot = self::repositoryRoot($root);
        $manifestPath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destinationPath)
            . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($decoded)) {
            return null;
        }
        $decoded['storage'] = [
            'storage_scope' => 'studio_snapshot',
            'relative_path' => $destinationPath,
            'manifest_relative_path' => $destinationPath . '/' . self::MANIFEST_FILENAME,
            'immutable' => 'yes',
            'append_only' => 'yes',
            'atomic_publish' => 'yes',
        ];
        return $decoded;
    }

    /** @param array<string,mixed> $stats */
    private static function copyNode(string $source, string $destination, string $relative, array &$stats): void
    {
        if (is_link($source)) {
            throw new \RuntimeException('Snapshot source contains a symlink, which is not supported: ' . $relative);
        }
        if (is_dir($source)) {
            if (!mkdir($destination, 0700)) {
                throw new \RuntimeException('Unable to create snapshot directory: ' . $relative);
            }
            $stats['directory_count']++;
            $entries = scandir($source);
            if (!is_array($entries)) {
                throw new \RuntimeException('Unable to enumerate snapshot source directory: ' . $relative);
            }
            sort($entries, SORT_STRING);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                self::copyNode(
                    $source . DIRECTORY_SEPARATOR . $entry,
                    $destination . DIRECTORY_SEPARATOR . $entry,
                    $relative . '/' . $entry,
                    $stats
                );
            }
            return;
        }
        if (!is_file($source)) {
            throw new \RuntimeException('Snapshot source contains an unsupported filesystem node: ' . $relative);
        }
        if (!is_readable($source)) {
            throw new \RuntimeException('Snapshot source file is unreadable: ' . $relative);
        }

        $sourceHandle = @fopen($source, 'rb');
        $destinationHandle = @fopen($destination, 'x+b');
        if ($sourceHandle === false || $destinationHandle === false) {
            if (is_resource($sourceHandle)) {
                fclose($sourceHandle);
            }
            if (is_resource($destinationHandle)) {
                fclose($destinationHandle);
            }
            throw new \RuntimeException('Unable to copy snapshot file: ' . $relative);
        }
        try {
            $copied = stream_copy_to_stream($sourceHandle, $destinationHandle);
            if ($copied === false) {
                throw new \RuntimeException('Unable to copy complete snapshot file: ' . $relative);
            }
            fflush($destinationHandle);
        } finally {
            fclose($sourceHandle);
            fclose($destinationHandle);
        }

        $sourceHash = hash_file('sha256', $source);
        $destinationHash = hash_file('sha256', $destination);
        if (!is_string($sourceHash) || !is_string($destinationHash) || !hash_equals($sourceHash, $destinationHash)) {
            throw new \RuntimeException('Snapshot checksum verification failed: ' . $relative);
        }
        $size = filesize($destination);
        if ($size === false) {
            throw new \RuntimeException('Unable to determine snapshot file size: ' . $relative);
        }
        $stats['file_count']++;
        $stats['total_bytes'] += (int)$size;
        $stats['files'][] = [
            'path' => str_replace('\\', '/', $relative),
            'size_bytes' => (int)$size,
            'sha256' => $destinationHash,
        ];
    }

    /** @param resource $handle */
    private static function writeAll($handle, string $content): void
    {
        $length = strlen($content);
        $written = 0;
        while ($written < $length) {
            $chunk = fwrite($handle, substr($content, $written));
            if ($chunk === false || $chunk === 0) {
                throw new \RuntimeException('Unable to write complete snapshot manifest.');
            }
            $written += $chunk;
        }
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $entries = scandir($path);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                self::removeTree($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    }

    private static function isWithin(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $path === $root || str_starts_with($path . '/', $root . '/');
    }

    private static function repositoryRoot(?string $root): string
    {
        return $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
    }

    private static function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    private static function canonicalJson(array $material): string
    {
        $normalized = self::sortRecursively($material);
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : serialize($normalized);
    }

    private static function sortRecursively($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'sortRecursively'], $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursively($item);
        }
        return $value;
    }
}

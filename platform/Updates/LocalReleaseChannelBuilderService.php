<?php
declare(strict_types=1);

namespace Platform\Updates;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

final class LocalReleaseChannelBuilderService
{
    private const SCHEMA_VERSION = 'susankhya.release.v1';

    /**
     * @param array<int,string> $includeRoots
     * @param array<int,string> $excludePrefixes
     * @return array<string,mixed>
     */
    public function build(
        string $root,
        string $channelDirectory,
        string $releaseVersion,
        string $buildId,
        array $includeRoots,
        array $excludePrefixes = []
    ): array {
        $root = rtrim($root, '/');
        $channelDirectory = rtrim($channelDirectory, '/');
        $releasesDirectory = $channelDirectory . '/releases';
        if (!is_dir($releasesDirectory) && !mkdir($releasesDirectory, 0775, true)) {
            return $this->failure('Unable to create local channel releases directory.');
        }

        $safeBuildId = preg_replace('/[^A-Za-z0-9._-]+/', '-', $buildId) ?: 'build';
        $packageName = 'susankhya-os-' . $releaseVersion . '-' . $safeBuildId . '.zip';
        $packagePath = $releasesDirectory . '/' . $packageName;
        $zip = new ZipArchive();
        if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->failure('Unable to create local release package.');
        }

        $included = [];
        $skipped = [];
        foreach ($includeRoots as $relativeRoot) {
            $this->addPath($zip, $root, $relativeRoot, $excludePrefixes, $included, $skipped);
        }
        if (in_array('release_metadata.json', $included, true)) {
            $zip->close();
            return $this->failure('Source tree must not provide release_metadata.json; the builder owns the internal metadata entry.');
        }
        $internalMetadata = [
            'schema_version' => self::SCHEMA_VERSION,
            'product_id' => 'susankhya-os',
            'product_name' => 'Susankhya ERP',
            'release_version' => $releaseVersion,
            'build_id' => $buildId,
            'channel' => 'local',
            'lane' => 'app',
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'package' => [
                'filename' => $packageName,
                // The ZIP cannot contain its own final byte checksum.
                'sha256' => null,
                'size_bytes' => null,
            ],
        ];
        $internalEncoded = json_encode($internalMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($internalEncoded === false || !$zip->addFromString('release_metadata.json', $internalEncoded . "\n")) {
            $zip->close();
            return $this->failure('Unable to add internal release metadata.');
        }
        $zip->close();

        $inspection = (new ReleasePackageInspectionService())->inspect($packagePath, $internalMetadata);
        if (($inspection['ok'] ?? false) !== true) {
            return $this->failure('Generated release package does not satisfy the V1 package contract.', ['inspection' => $inspection]);
        }

        $sha = hash_file('sha256', $packagePath) ?: '';
        $size = filesize($packagePath) ?: 0;
        $metadata = $this->metadata($releaseVersion, $buildId, $packageName, $sha, $size, $included, $skipped, $inspection);
        $metadataName = 'susankhya-os-' . $releaseVersion . '-' . $safeBuildId . '.json';
        $metadataPath = $releasesDirectory . '/' . $metadataName;
        file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $channel = [
            'schema_version' => 'susankhya.local-channel.v1',
            'channel' => 'local',
            'product_id' => 'susankhya-os',
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'lanes' => [
                'app' => [
                    'current' => [
                        'release_version' => $releaseVersion,
                        'build_id' => $buildId,
                        'metadata' => 'releases/' . $metadataName,
                        'package' => 'releases/' . $packageName,
                        'sha256' => $sha,
                        'size_bytes' => $size,
                    ],
                ],
                'runtime' => ['current' => null],
                'support' => ['current' => null],
            ],
        ];
        file_put_contents($channelDirectory . '/channel.json', json_encode($channel, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'channel_dir' => $channelDirectory,
            'channel_path' => $channelDirectory . '/channel.json',
            'metadata_path' => $metadataPath,
            'package_path' => $packagePath,
            'package_sha256' => $sha,
            'package_size_bytes' => $size,
            'included_count' => count($included),
            'skipped_count' => count($skipped),
        ];
    }

    /**
     * @param array<int,string> $excludePrefixes
     * @param array<int,string> $included
     * @param array<int,array<string,string>> $skipped
     */
    private function addPath(ZipArchive $zip, string $root, string $relativeRoot, array $excludePrefixes, array &$included, array &$skipped): void
    {
        $relativeRoot = $this->normalize($relativeRoot);
        if ($relativeRoot === null) {
            $skipped[] = ['path' => '', 'reason' => 'invalid'];
            return;
        }
        if ($this->isExcluded($relativeRoot, $excludePrefixes)) {
            $skipped[] = ['path' => $relativeRoot, 'reason' => 'excluded'];
            return;
        }

        $source = $root . '/' . $relativeRoot;
        if (!file_exists($source)) {
            $skipped[] = ['path' => $relativeRoot, 'reason' => 'missing'];
            return;
        }
        if (is_file($source)) {
            if (!is_link($source)) {
                $zip->addFile($source, $relativeRoot);
                $included[] = $relativeRoot;
            }
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $absolute = (string)$file->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
            if ($this->isExcluded($relative, $excludePrefixes)) {
                $skipped[] = ['path' => $relative, 'reason' => 'excluded'];
                continue;
            }
            $zip->addFile($absolute, $relative);
            $included[] = $relative;
        }
    }

    /**
     * @param array<int,string> $included
     * @param array<int,array<string,string>> $skipped
     * @return array<string,mixed>
     */
    private function metadata(string $releaseVersion, string $buildId, string $packageName, string $sha, int $size, array $included, array $skipped, array $inspection): array
    {
        $artifact = [
            'release_version' => $releaseVersion,
            'build_id' => $buildId,
            'package_sha256' => $sha,
            'package_size_bytes' => $size,
        ];
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'product_id' => 'susankhya-os',
            'product_name' => 'Susankhya ERP',
            'release_version' => $releaseVersion,
            'build_id' => $buildId,
            'channel' => 'local',
            'lane' => 'app',
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'package' => [
                'filename' => $packageName,
                'sha256' => $sha,
                'size_bytes' => $size,
            ],
            'compatibility' => [
                'minimum_product_version' => '0.5.0',
                'maximum_product_version' => null,
                'core' => ['minimum' => '0.0.0'],
                'apps' => [],
                'modules' => [],
                'schema' => ['minimum' => null, 'target' => null],
                'php' => ['minimum' => '8.1.0'],
                'php_extensions' => ['mysqli', 'json', 'zip'],
            ],
            'readiness' => [
                'status' => 'blocked',
                'authenticity' => 'builder-package-checks-only',
                'evidence' => [
                    [
                        'id' => 'package_contract',
                        'status' => 'passed',
                        'artifact' => $artifact,
                        'evidence' => [
                            'source' => 'builder',
                            'entry_count' => (int)($inspection['entry_count'] ?? 0),
                            'entries_sha256' => (string)($inspection['entries_sha256'] ?? ''),
                        ],
                    ],
                    [
                        'id' => 'architecture_gates',
                        'status' => 'unknown',
                        'artifact' => $artifact,
                        'evidence' => ['source' => 'builder', 'reason' => 'architecture gates were not executed by the package builder.'],
                    ],
                    [
                        'id' => 'deployment_readiness',
                        'status' => 'unknown',
                        'artifact' => $artifact,
                        'evidence' => ['source' => 'builder', 'reason' => 'deployment readiness was not executed by the package builder.'],
                    ],
                ],
            ],
            'migration' => ['warnings' => [], 'requires_backup' => true],
            'assets' => ['regeneration_required' => false],
            'release_notes' => '',
            'apply_eligibility' => ['requires_preview' => true, 'requires_operator_approval' => true],
            'build_manifest' => [
                'included_count' => count($included),
                'skipped' => $skipped,
                'included_sha256' => hash('sha256', implode("\n", $included)),
            ],
        ];
    }

    private function normalize(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        return $path;
    }

    /** @param array<int,string> $excludePrefixes */
    private function isExcluded(string $relativePath, array $excludePrefixes): bool
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        foreach ($excludePrefixes as $prefix) {
            $prefix = trim(str_replace('\\', '/', $prefix), '/');
            if ($prefix !== '' && ($relativePath === $prefix || str_starts_with($relativePath, $prefix . '/'))) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed> */
    private function failure(string $message): array
    {
        return ['ok' => false, 'errors' => [$message], 'warnings' => []];
    }
}

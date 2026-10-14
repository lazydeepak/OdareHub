<?php
declare(strict_types=1);

namespace Platform\Updates;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use ZipArchive;

final class LocalReleaseChannelBuilderService
{
    private const SCHEMA_VERSION = 'susankhya.release.v1';

    /**
     * Vendor-owned development material is filtered at build time. The
     * package inspector remains strict, so a raw package containing these
     * paths is still rejected rather than implicitly trusted.
     *
     * @var array<int,string>
     */
    private const VENDOR_DEVELOPMENT_PATH_PATTERNS = [
        '~^vendor/(?:[^/]+/)+(?:tests?|docs?|examples?)(?:/|$)~i',
        '~^vendor/(?:[^/]+/)+\.github(?:/|$)~i',
        '~^vendor/bin(?:/|$)~i',
        '~^vendor/(?:[^/]+/)+phpunit\.xml[^/]*$~i',
        '~^vendor/(?:[^/]+/)+composer\.cache$~i',
    ];

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
        if (!is_dir($channelDirectory) && !mkdir($channelDirectory, 0775, true)) {
            return $this->failure('Unable to create local channel directory.');
        }
        $releasesDirectory = $channelDirectory . '/releases';
        if (!is_dir($releasesDirectory) && !mkdir($releasesDirectory, 0775, true)) {
            return $this->failure('Unable to create local channel releases directory.');
        }
        $safeBuildId = preg_replace('/[^A-Za-z0-9._-]+/', '-', $buildId) ?: 'build';
        $packageName = 'susankhya-os-' . $releaseVersion . '-' . $safeBuildId . '.zip';
        $packagePath = $releasesDirectory . '/' . $packageName;
        $metadataName = 'susankhya-os-' . $releaseVersion . '-' . $safeBuildId . '.json';
        $metadataPath = $releasesDirectory . '/' . $metadataName;
        $collision = $this->checkArtifactCollision($channelDirectory, $packagePath, $metadataPath, $packageName, $metadataName);
        if ($collision !== null) {
            return $this->failure($collision);
        }
        $prepared = $this->prepareProductionSource($root, $channelDirectory, $includeRoots, $excludePrefixes);
        if ($prepared === false) {
            return $this->failure('Unable to prepare production Composer autoload metadata.');
        }
        $buildRoot = $prepared['root'] ?? $root;
        $effectiveExcludePrefixes = array_merge($excludePrefixes, $prepared['exclude_prefixes'] ?? []);
        $temporaryDirectory = $releasesDirectory . '/.build-' . bin2hex(random_bytes(8));
        if (!mkdir($temporaryDirectory, 0700, true)) {
            if (isset($prepared['cleanup']) && is_callable($prepared['cleanup'])) {
                ($prepared['cleanup'])();
            }
            return $this->failure('Unable to create temporary release finalization directory.');
        }
        // Keep the final basename during inspection; the inspector binds the
        // internal package filename to the actual archive basename.
        $temporaryPackagePath = $temporaryDirectory . '/' . $packageName;
        $temporaryMetadataPath = $temporaryDirectory . '/' . $metadataName;
        $temporaryChannelPath = $channelDirectory . '/channel.json.tmp-' . bin2hex(random_bytes(8));
        try {
            $zip = new ZipArchive();
            if ($zip->open($temporaryPackagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return $this->failure('Unable to create local release package.');
            }

            $included = [];
            $skipped = [];
            foreach ($includeRoots as $relativeRoot) {
                $this->addPath($zip, $buildRoot, $relativeRoot, $effectiveExcludePrefixes, $included, $skipped);
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

        $inspection = (new ReleasePackageInspectionService())->inspect($temporaryPackagePath, $internalMetadata);
        if (($inspection['ok'] ?? false) !== true) {
            return $this->failure('Generated release package does not satisfy the V1 package contract.', ['inspection' => $inspection]);
        }

        $sha = hash_file('sha256', $temporaryPackagePath) ?: '';
        $size = filesize($temporaryPackagePath) ?: 0;
        $metadata = $this->metadata($releaseVersion, $buildId, $packageName, $sha, $size, $included, $skipped, $inspection);
        if (!$this->writeTemporaryJson($temporaryMetadataPath, $metadata)) {
            return $this->failure('Unable to write release metadata.');
        }
        if (!link($temporaryPackagePath, $packagePath)) {
            return $this->failure('Release artifact identity became occupied before finalization.');
        }
        unlink($temporaryPackagePath);
        if (!link($temporaryMetadataPath, $metadataPath)) {
            unlink($packagePath);
            return $this->failure('Unable to finalize release metadata.');
        }
        unlink($temporaryMetadataPath);

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
        if (!$this->writeTemporaryJson($temporaryChannelPath, $channel) || !rename($temporaryChannelPath, $channelDirectory . '/channel.json')) {
            if (is_file($temporaryChannelPath)) {
                unlink($temporaryChannelPath);
            }
            unlink($metadataPath);
            unlink($packagePath);
            return $this->failure('Unable to finalize local channel metadata.');
        }

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
        } finally {
            if (is_file($temporaryChannelPath)) {
                unlink($temporaryChannelPath);
            }
            if (is_dir($temporaryDirectory)) {
                self::removeTree($temporaryDirectory);
            }
            if (isset($prepared['cleanup']) && is_callable($prepared['cleanup'])) {
                ($prepared['cleanup'])();
            }
        }
    }

    /**
     * Prepare a disposable source copy when a real Composer vendor tree is
     * present. The repository root is never passed to Composer.
     *
     * @param array<int,string> $includeRoots
     * @param array<int,string> $excludePrefixes
     * @return array<string,mixed>|false
     */
    private function prepareProductionSource(string $root, string $channelDirectory, array $includeRoots, array $excludePrefixes): array|false
    {
        $composerJson = $root . '/composer.json';
        $composerLock = $root . '/composer.lock';
        $vendorAutoload = $root . '/vendor/autoload.php';
        if (!is_file($composerJson) || !is_file($composerLock) || !is_file($vendorAutoload)) {
            return ['root' => $root, 'exclude_prefixes' => []];
        }

        $installedJson = $root . '/vendor/composer/installed.json';
        if (!is_file($installedJson)) {
            // Small package fixtures may provide only a runtime loader. Treat
            // those as non-Composer fixtures; a real Composer vendor tree
            // with a missing inventory is incomplete and must fail closed.
            return is_dir($root . '/vendor/composer') ? false : ['root' => $root, 'exclude_prefixes' => []];
        }
        $installed = json_decode((string)file_get_contents($installedJson), true);
        if (!is_array($installed)) {
            return false;
        }
        $staging = $channelDirectory . '/.production-source-' . bin2hex(random_bytes(8));
        if (!mkdir($staging, 0700, true)) {
            return false;
        }
        $cleanup = static function () use ($staging): void {
            self::removeTree($staging);
        };
        try {
            foreach ($includeRoots as $relativeRoot) {
                $relativeRoot = $this->normalize($relativeRoot);
                if ($relativeRoot === null || $this->isExcluded($relativeRoot, $excludePrefixes)) {
                    continue;
                }
                $this->copyPath($root . '/' . $relativeRoot, $staging . '/' . $relativeRoot, $root, $excludePrefixes);
            }
            $composer = $this->findComposer();
            if ($composer === null) {
                $cleanup();
                return false;
            }
            $result = $this->runComposerDump($composer, $staging);
            if (($result['exit_code'] ?? 1) !== 0 || !$this->validateProductionAutoload($staging)) {
                $cleanup();
                return false;
            }
            return [
                'root' => $staging,
                // The current V1 contract filters development paths, but a
                // full dev-package closure requires an approved Composer
                // installation/metadata policy and is intentionally not
                // inferred from installed.json here.
                'exclude_prefixes' => [],
                'cleanup' => $cleanup,
            ];
        } catch (Throwable $e) {
            $cleanup();
            return false;
        }
    }

    /** @param array<int,string> $excludePrefixes */
    private function copyPath(string $source, string $destination, string $root, array $excludePrefixes): void
    {
        if (!file_exists($source) || is_link($source)) {
            return;
        }
        if (is_file($source)) {
            $parent = dirname($destination);
            if (!is_dir($parent)) {
                mkdir($parent, 0700, true);
            }
            copy($source, $destination);
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                continue;
            }
            $absolute = (string)$file->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
            if ($this->isExcluded($relative, $excludePrefixes)) {
                continue;
            }
            $target = $destination . '/' . substr($relative, strlen(str_replace('\\', '/', substr($source, strlen($root) + 1))) + 1);
            if ($file->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0700, true);
                }
                continue;
            }
            $parent = dirname($target);
            if (!is_dir($parent)) {
                mkdir($parent, 0700, true);
            }
            copy($absolute, $target);
        }
    }

    private function findComposer(): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }
        $pipes = [];
        $process = @proc_open('command -v composer', [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return null;
        }
        $path = trim((string)stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $path === '' || !is_file($path) || !is_executable($path)) {
            return null;
        }
        return realpath($path) ?: null;
    }

    /** @return array<string,mixed> */
    private function runComposerDump(string $composer, string $workingDirectory): array
    {
        if (!function_exists('proc_open')) {
            return ['exit_code' => 1, 'output' => 'proc_open unavailable'];
        }
        $command = escapeshellarg($composer) . ' dump-autoload --no-dev --no-scripts --no-plugins --no-interaction';
        $pipes = [];
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        $environment['COMPOSER_DISABLE_NETWORK'] = '1';
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory, $environment);
        if (!is_resource($process)) {
            return ['exit_code' => 1, 'output' => 'Composer process could not be started.'];
        }
        $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit_code' => proc_close($process), 'output' => substr($output, 0, 2000)];
    }

    private function validateProductionAutoload(string $root): bool
    {
        $composerRoot = $root . '/vendor/composer';
        foreach (['autoload_psr4.php', 'autoload_classmap.php', 'autoload_files.php'] as $file) {
            if (!is_file($composerRoot . '/' . $file)) {
                return false;
            }
        }
        $psr4 = require $composerRoot . '/autoload_psr4.php';
        if (isset($psr4['Tests\\'])) {
            return false;
        }
        foreach ([
            require $composerRoot . '/autoload_psr4.php',
            require $composerRoot . '/autoload_classmap.php',
            require $composerRoot . '/autoload_files.php',
        ] as $map) {
            if (!$this->autoloadPathsExist($map, $root)) {
                return false;
            }
        }
        return true;
    }

    private function autoloadPathsExist(mixed $value, string $root): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->autoloadPathsExist($item, $root)) {
                    return false;
                }
            }
            return true;
        }
        if (!is_string($value) || !str_starts_with($value, '/')) {
            return true;
        }
        $realRoot = realpath($root);
        $realValue = realpath($value);
        return $realRoot !== false && $realValue !== false
            && ($realValue === $realRoot || str_starts_with($realValue, $realRoot . '/'));
    }

    private function checkArtifactCollision(string $channelDirectory, string $packagePath, string $metadataPath, string $packageName, string $metadataName): ?string
    {
        foreach ([$packagePath, $metadataPath] as $path) {
            if (file_exists($path) || is_link($path)) {
                return 'Release identity or normalized build ID already has an occupied artifact path.';
            }
        }

        $channelPath = $channelDirectory . '/channel.json';
        if (!file_exists($channelPath)) {
            return null;
        }
        if (is_link($channelPath)) {
            return 'Existing channel metadata is a symlink and cannot be replaced.';
        }
        $channel = json_decode((string)file_get_contents($channelPath), true);
        if (!is_array($channel)) {
            return 'Existing channel metadata is invalid or incomplete.';
        }
        $current = $channel['lanes']['app']['current'] ?? null;
        if ($current !== null && !is_array($current)) {
            return 'Existing channel metadata is invalid or incomplete.';
        }
        if ($current === null) {
            return null;
        }
        $currentPackage = $this->channelReference($channelDirectory, $current['package'] ?? null);
        $currentMetadata = $this->channelReference($channelDirectory, $current['metadata'] ?? null);
        if ($currentPackage === null || $currentMetadata === null || !is_file($currentPackage) || !is_file($currentMetadata)) {
            return 'Existing channel metadata references an incomplete release record.';
        }
        if (($current['package'] ?? null) === 'releases/' . $packageName || ($current['metadata'] ?? null) === 'releases/' . $metadataName) {
            return 'Release identity or normalized build ID is already recorded by the channel.';
        }
        return null;
    }

    private function channelReference(string $channelDirectory, mixed $reference): ?string
    {
        if (!is_string($reference) || $reference === '' || str_starts_with($reference, '/') || str_contains($reference, '..')) {
            return null;
        }
        if (!str_starts_with($reference, 'releases/')) {
            return null;
        }
        return $channelDirectory . '/' . $reference;
    }

    /** @param array<string,mixed> $value */
    private function writeTemporaryJson(string $path, array $value): bool
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return false;
        }
        return file_put_contents($path, $encoded . "\n", LOCK_EX) !== false;
    }

    private static function removeTree(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            self::removeTree($entry->getPathname());
        }
        rmdir($path);
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
        foreach (self::VENDOR_DEVELOPMENT_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $relativePath) === 1) {
                return true;
            }
        }
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

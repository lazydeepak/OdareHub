<?php
declare(strict_types=1);

namespace Platform\Updates;

use ZipArchive;

/**
 * Read-only V1 app-lane package inspection shared by build, preview, and
 * apply-preparation boundaries.
 */
final class ReleasePackageInspectionService
{
    /** @var array<int,string> */
    private const REQUIRED_PREFIXES = [
        'app',
        'apps',
        'platform',
        'plugins',
        'public',
        'resources',
        'vendor',
        'bin',
    ];

    /** @var array<int,string> */
    private const REQUIRED_FILES = ['composer.json', 'composer.lock', 'release_metadata.json'];

    /** @var array<int,string> */
    private const PROTECTED_PREFIXES = [
        '.git',
        'node_modules',
        'packages',
        'storage',
        'tests',
        'backups',
        'restore-points',
        'uploads',
        'logs',
        'cache',
        'tmp',
        'temp',
    ];

    /**
     * @param array<string,mixed> $externalMetadata
     * @return array<string,mixed>
     */
    public function inspect(string $packagePath, array $externalMetadata = []): array
    {
        if (!is_file($packagePath) || !is_readable($packagePath)) {
            return $this->failure('Release package is missing or unreadable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            return $this->failure('Release package is not a valid ZIP archive.');
        }

        $entries = [];
        $normalizedKeys = [];
        $unsafe = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || $name === '') {
                $unsafe = 'Release package contains an unreadable ZIP entry.';
                break;
            }

            $normalized = $this->normalizeEntry($name);
            if ($normalized === null) {
                $unsafe = 'Release package contains an unsafe or ambiguous ZIP path: ' . $name;
                break;
            }

            $collisionKey = strtolower($normalized);
            if (isset($normalizedKeys[$collisionKey])) {
                $unsafe = 'Release package contains duplicate or normalization-colliding entries: ' . $name;
                break;
            }
            $normalizedKeys[$collisionKey] = true;

            if ($this->isProtectedPath($normalized)) {
                $unsafe = 'Release package contains a protected installation path: ' . $normalized;
                break;
            }

            if (!$this->isDirectoryEntry($name) && !$this->isRegularEntry($zip, $index)) {
                $unsafe = 'Release package contains a non-regular ZIP entry: ' . $normalized;
                break;
            }

            $entries[] = $normalized;
        }
        $zip->close();

        if ($unsafe !== null) {
            return $this->failure($unsafe);
        }
        if ($entries === []) {
            return $this->failure('Release package contains no entries.');
        }

        sort($entries);
        foreach (self::REQUIRED_PREFIXES as $prefix) {
            if (!$this->hasFileUnder($entries, $prefix)) {
                return $this->failure('Release package is missing required runtime root: ' . $prefix . '/');
            }
        }
        foreach (self::REQUIRED_FILES as $file) {
            if (!in_array($file, $entries, true)) {
                return $this->failure('Release package is missing required file: ' . $file);
            }
        }

        $consistency = $this->validateMetadataFiles($packagePath, $externalMetadata);
        if (!$consistency['ok']) {
            return $consistency;
        }

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'entry_count' => count($entries),
            'entries_sha256' => hash('sha256', implode("\n", $entries)),
            'entries' => $entries,
            'sample_entries' => array_slice($entries, 0, 20),
        ];
    }

    private function normalizeEntry(string $name): ?string
    {
        if (str_contains($name, "\0")) {
            return null;
        }
        $normalized = str_replace('\\', '/', $name);
        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return null;
        }
        $isDirectory = str_ends_with($normalized, '/');
        $normalized = trim($normalized, '/');
        if ($normalized === '') {
            return null;
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $normalized . ($isDirectory ? '/' : '');
    }

    /** @param array<int,string> $entries */
    private function hasFileUnder(array $entries, string $prefix): bool
    {
        foreach ($entries as $entry) {
            if ($entry === $prefix || str_starts_with($entry, $prefix . '/')) {
                if (!str_ends_with($entry, '/')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isProtectedPath(string $path): bool
    {
        $path = rtrim($path, '/');
        if ($path === '.DS_Store' || preg_match('/(^|\/)\.DS_Store$/', $path) === 1) {
            return true;
        }
        if (preg_match('/(^|\/)(phpunit\.xml[^\/]*|composer\.cache)(\/|$)/i', $path) === 1) {
            return true;
        }
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    private function isDirectoryEntry(string $name): bool
    {
        return str_ends_with(str_replace('\\', '/', $name), '/');
    }

    private function isRegularEntry(ZipArchive $zip, int $index): bool
    {
        if (!method_exists($zip, 'getExternalAttributesIndex')) {
            return true;
        }

        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes, ZipArchive::FL_UNCHANGED)) {
            return true;
        }
        // UNIX file type bits: reject symlinks, devices, FIFOs, and sockets.
        if ($opsys === 3) {
            $type = (($attributes >> 16) & 0170000);
            return $type === 0 || $type === 0100000;
        }
        return true;
    }

    /** @param array<string,mixed> $externalMetadata @return array<string,mixed> */
    private function validateMetadataFiles(string $packagePath, array $externalMetadata): array
    {
        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            return $this->failure('Release package cannot be reopened for metadata validation.');
        }
        $contents = $zip->getFromName('release_metadata.json');
        $composer = $zip->getFromName('composer.json');
        $lock = $zip->getFromName('composer.lock');
        $zip->close();

        $internal = is_string($contents) ? json_decode($contents, true) : null;
        if (!is_array($internal)) {
            return $this->failure('Internal release_metadata.json is missing or invalid.');
        }
        $composerData = is_string($composer) ? json_decode($composer, true) : null;
        $lockData = is_string($lock) ? json_decode($lock, true) : null;
        if (!is_array($composerData) || !is_array($lockData)) {
            return $this->failure('composer.json and composer.lock must contain valid JSON.');
        }
        if (isset($lockData['content-hash']) && preg_match('/^[a-f0-9]{32}$/', (string)$lockData['content-hash']) !== 1) {
            return $this->failure('composer.lock content-hash is invalid.');
        }
        if (!isset($lockData['packages']) || !is_array($lockData['packages'])) {
            return $this->failure('composer.lock packages array is required.');
        }
        $lockedPackages = [];
        foreach (array_merge($lockData['packages'], is_array($lockData['packages-dev'] ?? null) ? $lockData['packages-dev'] : []) as $locked) {
            if (is_array($locked) && is_string($locked['name'] ?? null)) {
                $lockedPackages[(string)$locked['name']] = true;
            }
        }
        foreach (['require', 'require-dev'] as $requireKey) {
            foreach ((array)($composerData[$requireKey] ?? []) as $name => $_constraint) {
                $name = (string)$name;
                if ($name === '' || str_starts_with($name, 'php') || str_starts_with($name, 'ext-') || isset($lockedPackages[$name])) {
                    continue;
                }
                return $this->failure('composer.json requirement is absent from composer.lock: ' . $name);
            }
        }

        foreach (['schema_version', 'product_id', 'product_name', 'release_version', 'build_id', 'channel', 'lane'] as $field) {
            if (($internal[$field] ?? null) !== ($externalMetadata[$field] ?? null)) {
                return $this->failure('Internal and external release metadata differ at ' . $field . '.');
            }
        }
        $internalFilename = $internal['package']['filename'] ?? null;
        if ($internalFilename !== basename($packagePath)) {
            return $this->failure('Internal release metadata package filename does not match the ZIP filename.');
        }
        $externalFilename = $externalMetadata['package']['filename'] ?? null;
        if ($externalFilename !== basename($packagePath)) {
            return $this->failure('External release metadata package filename does not match the ZIP filename.');
        }
        if (array_key_exists('sha256', (array)($internal['package'] ?? [])) && $internal['package']['sha256'] !== null) {
            return $this->failure('Internal release metadata must not claim a self-referential package checksum.');
        }

        return ['ok' => true, 'errors' => [], 'warnings' => []];
    }

    /** @param array<string,mixed> $details */
    private function failure(string $message, array $details = []): array
    {
        return ['ok' => false, 'errors' => [$message], 'warnings' => [], 'details' => $details];
    }
}

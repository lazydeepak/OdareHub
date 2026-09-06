<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class AppLocalDiscoveryService
{
    public function syncLocalApps(): void
    {
        $appsRoot = dirname(__DIR__, 2) . '/apps';
        if (!is_dir($appsRoot)) {
            return;
        }

        $dirs = glob($appsRoot . '/*', GLOB_ONLYDIR) ?: [];
        $candidates = [];

        foreach ($dirs as $dir) {
            $manifestFile = $dir . '/manifest.json';
            if (!is_file($manifestFile)) {
                continue;
            }

            $manifest = AppManifestService::loadFromFile($manifestFile);
            $appKey = (string)$manifest['id'];
            if ($appKey === '') {
                continue;
            }

            $canonicalDir = $this->canonicalInstallDir($appsRoot, $dir, $manifest);
            $existing = $candidates[$appKey] ?? null;
            $candidate = [
                'dir' => $dir,
                'canonical_dir' => $canonicalDir,
                'manifest' => $manifest,
                'matches_canonical' => $this->samePath($dir, $canonicalDir),
            ];

            if (!is_array($existing) || $this->shouldPreferCandidate($candidate, $existing)) {
                $candidates[$appKey] = $candidate;
            }
        }

        foreach ($candidates as $appKey => $candidate) {
            $dir = (string)$candidate['canonical_dir'];
            $manifest = (array)$candidate['manifest'];
            $existing = DB::fetchOne('SELECT app_key, status FROM core_apps WHERE app_key=? LIMIT 1', [$appKey]);

            $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES) ?: '{}';
            $manifestFile = $dir . '/manifest.json';
            $checksum = is_file($manifestFile) ? (hash_file('sha256', $manifestFile) ?: '') : '';

            if (!$existing) {
                $status = AppRegistryService::STATUS_UPLOADED;
                DB::query(
                    'INSERT INTO core_apps (app_key, app_name, version, app_type, status, install_path, manifest_json, checksum, installed_at, enabled_at, installed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $appKey,
                        (string)$manifest['name'],
                        (string)$manifest['version'],
                        (string)$manifest['type'],
                        $status,
                        $dir,
                        $manifestJson,
                        $checksum,
                        $status === AppRegistryService::STATUS_ENABLED ? date('Y-m-d H:i:s') : null,
                        $status === AppRegistryService::STATUS_ENABLED ? date('Y-m-d H:i:s') : null,
                        'system-bootstrap',
                    ]
                );
                AppPlatformLogger::lifecycle('local_discovery_registered', $appKey, ['status' => $status]);
            } else {
                DB::query(
                    'UPDATE core_apps SET app_name=?, version=?, app_type=?, install_path=?, manifest_json=?, checksum=?, updated_at=NOW() WHERE app_key=?',
                    [
                        (string)$manifest['name'],
                        (string)$manifest['version'],
                        (string)$manifest['type'],
                        $dir,
                        $manifestJson,
                        $checksum,
                        $appKey,
                    ]
                );
            }

            (new AppRuntimeRegistryService())->refreshAppRuntimeArtifacts($appKey, $manifest);

            $warnings = (array)($manifest['contract_warnings'] ?? []);
            if ($warnings !== []) {
                AppPlatformLogger::lifecycleDedup('manifest_contract_warning', $appKey, [
                    'warnings' => array_slice($warnings, 0, 50),
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function canonicalInstallDir(string $appsRoot, string $dir, array $manifest): string
    {
        $directoryName = trim((string)($manifest['directory_name'] ?? $manifest['bundle_dir'] ?? ''));
        if ($directoryName === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $directoryName)) {
            return $dir;
        }

        $canonicalDir = rtrim($appsRoot, '/') . '/' . $directoryName;
        $canonicalManifest = $canonicalDir . '/manifest.json';
        if (!is_file($canonicalManifest)) {
            return $dir;
        }

        $canonical = AppManifestService::loadFromFile($canonicalManifest);
        if ((string)($canonical['id'] ?? '') !== (string)($manifest['id'] ?? '')) {
            return $dir;
        }

        if (!$this->samePath($dir, $canonicalDir)) {
            AppPlatformLogger::lifecycleDedup('duplicate_local_app_path', (string)$manifest['id'], [
                'legacy_path' => $dir,
                'canonical_path' => $canonicalDir,
            ]);
        }

        return $canonicalDir;
    }

    /**
     * @param array<string,mixed> $candidate
     * @param array<string,mixed> $existing
     */
    private function shouldPreferCandidate(array $candidate, array $existing): bool
    {
        $candidateCanonical = (bool)($candidate['matches_canonical'] ?? false);
        $existingCanonical = (bool)($existing['matches_canonical'] ?? false);
        if ($candidateCanonical !== $existingCanonical) {
            return $candidateCanonical;
        }

        return strcmp((string)($candidate['dir'] ?? ''), (string)($existing['dir'] ?? '')) < 0;
    }

    private function samePath(string $a, string $b): bool
    {
        $realA = realpath($a) ?: $a;
        $realB = realpath($b) ?: $b;
        return $realA === $realB;
    }
}

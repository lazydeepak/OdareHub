<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationStudio\Services;

final class LocalizationStudioDiscoveryService
{
    private const SUPPORTED_LOCALES = ['en', 'ja', 'ne'];

    /**
     * Canonical and legacy scan patterns.
     * Legacy paths listed first, canonical paths second.
     * In analyzeOwner(), $filesByLocale[$locale] = $f overwrites with each
     * file found, so the later (canonical) entry takes priority when both
     * paths exist for the same owner during migration window.
     *
     * Core (app/Locale/) uses a separate legacy path pattern not listed here;
     * Core remains locked per architecture policy.
     */
    private const SCAN_PATTERNS = [
        // Legacy path (maintained for backward compatibility during migration window)
        'apps/*' => 'apps/*/Resources/lang/%s.php',
        'apps/*/modules/*' => 'apps/*/modules/*/Resources/lang/%s.php',
        'plugins/*' => 'plugins/*/Resources/lang/%s.php',
        'studio/tools/*' => 'apps/Studio/Tools/*/Resources/lang/%s.php',
    ];

    public static function scan(): array
    {
        $localeFiles = self::discoverFiles();
        $grouped = self::groupByOwner($localeFiles);
        $coverage = [];

        foreach ($grouped as $ownerKey => $files) {
            $coverage[$ownerKey] = self::analyzeOwner($ownerKey, $files);
        }

        ksort($coverage);

        return [
            'groups' => $coverage,
            'summary' => self::buildSummary($coverage),
            'supported_locales' => self::SUPPORTED_LOCALES,
            'is_read_only' => true,
        ];
    }

    private static function discoverFiles(): array
    {
        $files = [];

        foreach (self::SCAN_PATTERNS as $label => $pattern) {
            $globs = glob(str_replace('%s', '*', APP_ROOT . '/' . $pattern));
            if (!is_array($globs)) {
                continue;
            }

            foreach ($globs as $path) {
                $locale = self::detectLocale(basename($path));
                if ($locale === null) {
                    continue;
                }

                $relative = str_replace(APP_ROOT . '/', '', $path);
                preg_match('#^' . str_replace('%s', '([a-z]{2})', self::patternToRegex($pattern)) . '$#', $relative, $m);
                $ownerPath = $relative;

                $files[] = [
                    'path' => $relative,
                    'full_path' => $path,
                    'locale' => $locale,
                    'owner_label' => $label,
                ];
            }
        }

        return $files;
    }

    private static function detectLocale(string $filename): ?string
    {
        if (preg_match('/^([a-z]{2})\.php$/', $filename, $m) === 1) {
            $code = $m[1];
            if (in_array($code, self::SUPPORTED_LOCALES, true)) {
                return $code;
            }
        }
        return null;
    }

    private static function patternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '#');
        $escaped = str_replace('\*', '[^/]+', $escaped);
        $escaped = str_replace('%s', '[a-z]{2}', $escaped);
        return $escaped;
    }

    private static function groupByOwner(array $files): array
    {
        $grouped = [];

        foreach ($files as $f) {
            $owner = self::deriveOwnerKey($f['path'], $f['owner_label']);
            if (!isset($grouped[$owner])) {
                $grouped[$owner] = [];
            }
            $grouped[$owner][] = $f;
        }

        return $grouped;
    }

    private static function deriveOwnerKey(string $path, string $label): string
    {
        if ($label === 'app') {
            return 'Core';
        }

        if (preg_match('#^apps/([^/]+)/#', $path, $m) === 1) {
            $app = $m[1];
            if ($app === 'Studio' && preg_match('#^apps/Studio/Tools/([^/]+)/#', $path, $tm) === 1) {
                return 'Studio/Tools/' . $tm[1];
            }
            if (preg_match('#^apps/([^/]+)/modules/([^/]+)/#', $path, $mm) === 1) {
                return $mm[1] . '/' . $mm[2];
            }
            return $app;
        }

        if (preg_match('#^plugins/([^/]+)/#', $path, $m) === 1) {
            return 'Plugin/' . $m[1];
        }

        return $path;
    }

    private static function analyzeOwner(string $ownerKey, array $files): array
    {
        $filesByLocale = [];
        foreach ($files as $f) {
            $filesByLocale[$f['locale']] = $f;
        }

        $localeData = [];
        $missingLocales = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            if (isset($filesByLocale[$locale])) {
                $keys = self::readKeys($filesByLocale[$locale]['full_path']);
                $values = self::readKeyValues($filesByLocale[$locale]['full_path']);
                $localeData[$locale] = [
                    'exists' => true,
                    'key_count' => count($keys),
                    'keys' => $keys,
                    'values' => $values,
                    'path' => $filesByLocale[$locale]['path'],
                ];
            } else {
                $localeData[$locale] = [
                    'exists' => false,
                    'key_count' => 0,
                    'keys' => [],
                    'values' => [],
                    'path' => null,
                ];
                $missingLocales[] = $locale;
            }
        }

        $allKeys = self::collectAllKeys($localeData);
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $localeKeys = array_flip($localeData[$locale]['keys']);
            $missingKeys = [];
            foreach ($allKeys as $key) {
                if (!isset($localeKeys[$key])) {
                    $missingKeys[] = $key;
                }
            }
            $localeData[$locale]['missing_keys'] = $missingKeys;
            $localeData[$locale]['missing_key_count'] = count($missingKeys);
        }

        $mismatch = self::computeMismatch($localeData);

        return [
            'owner' => $ownerKey,
            'files' => $localeData,
            'key_count' => count($allKeys),
            'all_keys' => $allKeys,
            'missing_locales' => $missingLocales,
            'has_mismatch' => $mismatch['has_mismatch'],
            'mismatch_details' => $mismatch['details'],
        ];
    }

    private static function readKeys(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $data = @require $path;
        if (!is_array($data)) {
            return [];
        }

        $keys = self::flattenKeys($data);
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        return $keys;
    }

    private static function flattenKeys(array $data, string $prefix = ''): array
    {
        $keys = [];
        foreach ($data as $k => $v) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $k : (string)$k;
            if (is_array($v)) {
                $nested = self::flattenKeys($v, $fullKey);
                foreach ($nested as $nk) {
                    $keys[] = $nk;
                }
            } else {
                $keys[] = $fullKey;
            }
        }
        return $keys;
    }

    private static function readKeyValues(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $data = @require $path;
        if (!is_array($data)) {
            return [];
        }

        return self::flattenKeysWithValues($data);
    }

    private static function flattenKeysWithValues(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $k => $v) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $k : (string)$k;
            if (is_array($v)) {
                $nested = self::flattenKeysWithValues($v, $fullKey);
                foreach ($nested as $nk => $nv) {
                    $result[$nk] = $nv;
                }
            } else {
                $result[$fullKey] = $v;
            }
        }
        return $result;
    }

    private static function collectAllKeys(array $localeData): array
    {
        $allKeys = [];
        foreach ($localeData as $ld) {
            foreach ($ld['keys'] as $key) {
                $allKeys[$key] = true;
            }
        }

        $keys = array_keys($allKeys);
        sort($keys, SORT_STRING);

        return $keys;
    }

    private static function computeMismatch(array $localeData): array
    {
        $existingKeys = [];
        foreach ($localeData as $locale => $ld) {
            if ($ld['exists']) {
                $existingKeys[$locale] = array_unique($ld['keys']);
            }
        }

        $localeCodes = array_keys($existingKeys);
        $hasMismatch = false;
        $details = [];

        if (count($localeCodes) < 2) {
            return ['has_mismatch' => false, 'details' => []];
        }

        $reference = $localeCodes[0];

        for ($i = 1; $i < count($localeCodes); $i++) {
            $other = $localeCodes[$i];
            $refSet = array_flip($existingKeys[$reference]);
            $otherSet = array_flip($existingKeys[$other]);

            $missingInOther = [];
            foreach ($refSet as $k => $_) {
                if (!isset($otherSet[$k])) {
                    $missingInOther[] = $k;
                    $hasMismatch = true;
                }
            }

            $extraInOther = [];
            foreach ($otherSet as $k => $_) {
                if (!isset($refSet[$k])) {
                    $extraInOther[] = $k;
                    $hasMismatch = true;
                }
            }

            if ($missingInOther !== [] || $extraInOther !== []) {
                $details[] = [
                    'reference' => $reference,
                    'other' => $other,
                    'missing_in_other' => $missingInOther,
                    'extra_in_other' => $extraInOther,
                ];
            }
        }

        return [
            'has_mismatch' => $hasMismatch,
            'details' => $details,
        ];
    }

    private static function buildSummary(array $coverage): array
    {
        $totalOwners = count($coverage);
        $totalFiles = 0;
        $totalKeys = 0;
        $ownersWithMissing = 0;
        $ownersWithMismatch = 0;
        $localeCounts = array_fill_keys(self::SUPPORTED_LOCALES, 0);

        foreach ($coverage as $owner => $data) {
            $totalKeys += $data['key_count'];
            if ($data['missing_locales'] !== []) {
                $ownersWithMissing++;
            }
            if ($data['has_mismatch']) {
                $ownersWithMismatch++;
            }
            foreach (self::SUPPORTED_LOCALES as $locale) {
                if ($data['files'][$locale]['exists']) {
                    $localeCounts[$locale]++;
                    $totalFiles++;
                }
            }
        }

        return [
            'total_owners' => $totalOwners,
            'total_files' => $totalFiles,
            'total_keys' => $totalKeys,
            'owners_with_missing_locales' => $ownersWithMissing,
            'owners_with_key_mismatch' => $ownersWithMismatch,
            'locale_file_counts' => $localeCounts,
        ];
    }
}

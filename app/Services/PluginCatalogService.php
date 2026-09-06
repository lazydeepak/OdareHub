<?php
declare(strict_types=1);

namespace App\Services;

final class PluginCatalogService
{
    /**
     * @var array<string,array<string,string>>
     */
    private const SUITE_BUCKETS = [
        'core' => [
            'title' => 'Core / Platform Apps',
            'subtitle' => 'System-level foundation and reusable infrastructure.',
        ],
        'manufacturing' => [
            'title' => 'Manufacturing / IPM Apps',
            'subtitle' => 'Factory and planning domain modules.',
        ],
        'sbaio' => [
            'title' => 'SBAIO Apps',
            'subtitle' => 'Small-business suite modules for staff, attendance, timecards, payroll, and office workflows.',
        ],
        'future' => [
            'title' => 'Future / Experimental',
            'subtitle' => 'Templates and placeholder modules.',
        ],
        'uncategorized' => [
            'title' => 'Uncategorized Apps',
            'subtitle' => 'Modules without suite metadata.',
        ],
    ];

    /**
     * @var array<string,string>
     */
    private const SUITE_ALIASES = [
        'platform' => 'core',
        'core' => 'core',
        'platform' => 'core',
        'manufacturing' => 'manufacturing',
        'ipm' => 'manufacturing',
        'sbaio' => 'sbaio',
        'future' => 'future',
        'experimental' => 'future',
    ];

    /**
     * @var array<string,string>
     */
    private const DISPLAY_NAME_OVERRIDES = [
        'QRCode' => 'QR Code',
        'AdminTools' => 'System Tools',
    ];

    /**
     * @return array<string,array<string,string>>
     */
    public function suiteBuckets(): array
    {
        return self::SUITE_BUCKETS;
    }

    /**
     * @param array<string,array<string,mixed>> $manifests
     * @return array<string,array<string,mixed>>
     */
    public function visibleManifests(array $manifests): array
    {
        return array_filter($manifests, static function (array $manifest): bool {
            return empty($manifest['hidden_from_catalog']) && empty($manifest['compatibility_only']);
        });
    }

    /**
     * @param array<string,array<string,mixed>> $manifests
     * @return array<string,array<string,array<string,mixed>>>
     */
    public function groupedVisibleManifests(array $manifests): array
    {
        $grouped = array_fill_keys(array_keys(self::SUITE_BUCKETS), []);

        foreach ($manifests as $pluginName => $manifest) {
            if (!is_array($manifest)) {
                continue;
            }

            $bucketKey = $this->bucketKeyForManifest($manifest);
            if (!isset($grouped[$bucketKey])) {
                $bucketKey = 'uncategorized';
            }

            $grouped[$bucketKey][$pluginName] = $manifest;
        }

        foreach ($grouped as &$bucket) {
            if ($bucket === []) {
                continue;
            }

            uasort($bucket, static function (array $left, array $right): int {
                $leftOrder = (int)($left['display_order'] ?? 9999);
                $rightOrder = (int)($right['display_order'] ?? 9999);
                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                $leftName = strtolower((string)($left['display_name'] ?? $left['name'] ?? ''));
                $rightName = strtolower((string)($right['display_name'] ?? $right['name'] ?? ''));
                return $leftName <=> $rightName;
            });
        }
        unset($bucket);

        return $grouped;
    }

    /**
     * @param array<string,array<string,mixed>> $manifests
     * @return array<string,array<string,mixed>>
     */
    public function corePlatformManifests(array $manifests): array
    {
        $visible = $this->visibleManifests($manifests);
        $grouped = $this->groupedVisibleManifests($visible);
        return is_array($grouped['core'] ?? null) ? $grouped['core'] : [];
    }

    /**
     * @param array<string,mixed> $manifest
     */
    public function normalizedDisplayName(string $pluginName, array $manifest = []): string
    {
        $pluginName = trim($pluginName);
        if ($pluginName !== '' && isset(self::DISPLAY_NAME_OVERRIDES[$pluginName])) {
            return self::DISPLAY_NAME_OVERRIDES[$pluginName];
        }

        $label = trim((string)($manifest['display_name'] ?? $manifest['name'] ?? $pluginName));
        return $label !== '' ? $label : $pluginName;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function bucketKeyForManifest(array $manifest): string
    {
        $ownerApp = strtolower(trim((string)($manifest['owner_app'] ?? '')));
        $suite = strtolower(trim((string)($manifest['suite'] ?? '')));
        $group = strtolower(trim((string)($manifest['group'] ?? '')));

        foreach ([$ownerApp, $suite, $group] as $candidate) {
            if ($candidate !== '' && isset(self::SUITE_ALIASES[$candidate])) {
                return self::SUITE_ALIASES[$candidate];
            }
        }

        return 'uncategorized';
    }
}

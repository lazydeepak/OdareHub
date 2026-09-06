<?php
declare(strict_types=1);

namespace Plugins\Organization\Services;

use App\Core\DB;
use App\Services\BrandingVariantService;
use App\Services\CompanySettingsService;
use App\Services\LogoUploadService;

final class OrganizationService
{
    private static bool $legacySeedAttempted = false;

    /**
     * @return array<string,string>
     */
    public static function currencyOptions(): array
    {
        return [
            'JPY' => 'JPY',
            'USD' => 'USD',
            'EUR' => 'EUR',
            'NPR' => 'NPR',
            'GBP' => 'GBP',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function timezoneOptions(): array
    {
        return [
            'Asia/Tokyo' => 'Asia/Tokyo',
            'UTC' => 'UTC',
            'America/New_York' => 'America/New_York',
            'Europe/London' => 'Europe/London',
            'Asia/Kathmandu' => 'Asia/Kathmandu',
            'Asia/Kolkata' => 'Asia/Kolkata',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function taxModeOptions(): array
    {
        return [
            'exclusive' => 'Tax Exclusive',
            'inclusive' => 'Tax Inclusive',
            'exempt' => 'Tax Exempt',
            'none' => 'No Tax',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function landingSummary(): array
    {
        $company = self::primaryCompany();
        $branchStats = DB::fetchOne(
            'SELECT COUNT(*) AS total_count, COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_count FROM org_branches'
        ) ?: ['total_count' => 0, 'active_count' => 0];
        $fiscal = $company ? self::getFiscalSettings((int)$company['id']) : self::emptyFiscalSettings();
        $branding = $company ? self::brandingProfile((int)$company['id']) : self::emptyBrandingProfile();

        return [
            'company' => $company,
            'branch_stats' => [
                'total_count' => (int)($branchStats['total_count'] ?? 0),
                'active_count' => (int)($branchStats['active_count'] ?? 0),
            ],
            'fiscal' => $fiscal,
            'branding' => $branding,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listCompanies(): array
    {
        self::seedPrimaryCompanyFromLegacySettings();

        return DB::fetchAll(
            'SELECT * FROM org_companies ORDER BY is_primary DESC, is_active DESC, company_name ASC, id ASC'
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function primaryCompany(): ?array
    {
        $company = DB::fetchOne(
            'SELECT * FROM org_companies ORDER BY is_primary DESC, is_active DESC, updated_at DESC, id DESC LIMIT 1'
        ) ?: null;

        if ($company) {
            return $company;
        }

        self::seedPrimaryCompanyFromLegacySettings();

        return DB::fetchOne(
            'SELECT * FROM org_companies ORDER BY is_primary DESC, is_active DESC, updated_at DESC, id DESC LIMIT 1'
        ) ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function companyById(int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }

        return DB::fetchOne('SELECT * FROM org_companies WHERE id = ? LIMIT 1', [$companyId]) ?: null;
    }

    public static function primaryCompanyId(): int
    {
        return (int)(self::primaryCompany()['id'] ?? 0);
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    public static function uploadCompanyLogo(int $companyId, array $file): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);

        require_once APP_ROOT . '/app/Services/LogoUploadService.php';
        $result = \App\Services\LogoUploadService::processUpload($file);

        if (!(bool)($result['success'] ?? false)) {
            return $result;
        }

        DB::query(
            'UPDATE branding_assets
             SET is_active = 0, updated_at = NOW()
             WHERE company_id = ? AND asset_group = ? AND usage_key = ?',
            [$companyId, 'logo', 'primary']
        );

        $newPath = (string)($result['path'] ?? '');

        self::insertBrandingAssetRecord(
            $companyId,
            $newPath,
            [
                'display_name'    => (string)($file['name'] ?? ''),
                'mime_type'       => (string)($file['type'] ?? ''),
                'file_size_bytes' => (int)($file['size'] ?? 0),
            ],
            true
        );

        // Generate display and icon variants from the original PNG (no-op for SVG).
        require_once APP_ROOT . '/app/Services/BrandingVariantService.php';
        $variants = BrandingVariantService::generateVariants($newPath);
        foreach ($variants as $v) {
            self::insertBrandingAssetRecord(
                $companyId,
                (string)$v['file_path'],
                [
                    'display_name'    => basename((string)$v['file_path']),
                    'mime_type'       => (string)$v['mime_type'],
                    'file_size_bytes' => (int)$v['file_size_bytes'],
                    'pixel_width'     => (int)$v['pixel_width'],
                    'pixel_height'    => (int)$v['pixel_height'],
                    'variant_key'     => (string)$v['variant_key'],
                    'source_kind'     => 'generated',
                ],
                false
            );
        }

        DB::query(
            'UPDATE org_companies SET logo_path = ?, updated_at = NOW() WHERE id = ?',
            [(string)($result['path'] ?? ''), $companyId]
        );

        return [
            'success' => true,
            'path'    => $newPath,
            'error'   => null,
        ];
    }

    /**
     * Re-generate display and icon variants from the currently active original logo.
     * Deletes any prior generated variants for this company before creating fresh ones.
     *
     * @return array<string,mixed>
     */
    public static function regenerateVariants(int $companyId): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);

        $original = DB::fetchOne(
            "SELECT * FROM branding_assets
             WHERE company_id = ? AND asset_group = 'logo' AND usage_key = 'primary'
               AND variant_key = 'original' AND is_active = 1
             LIMIT 1",
            [$companyId]
        ) ?: null;

        if (!$original) {
            return [
                'success' => false,
                'error'   => (string)t('organization.error.no_active_logo'),
                'count'   => 0,
            ];
        }

        // Remove stored variant records (not files — old files remain in storage).
        DB::query(
            "DELETE FROM branding_assets
             WHERE company_id = ? AND asset_group = 'logo' AND usage_key = 'primary'
               AND variant_key != 'original'",
            [$companyId]
        );

        require_once APP_ROOT . '/app/Services/BrandingVariantService.php';
        $variants = BrandingVariantService::generateVariants((string)($original['file_path'] ?? ''));

        foreach ($variants as $v) {
            self::insertBrandingAssetRecord(
                $companyId,
                (string)$v['file_path'],
                [
                    'display_name'    => basename((string)$v['file_path']),
                    'mime_type'       => (string)$v['mime_type'],
                    'file_size_bytes' => (int)$v['file_size_bytes'],
                    'pixel_width'     => (int)$v['pixel_width'],
                    'pixel_height'    => (int)$v['pixel_height'],
                    'variant_key'     => (string)$v['variant_key'],
                    'source_kind'     => 'generated',
                ],
                false
            );
        }

        return [
            'success' => true,
            'error'   => null,
            'count'   => count($variants),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function removeCompanyLogo(int $companyId): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);
        $company = self::companyById($companyId);

        if (!$company || empty($company['logo_path'])) {
            return [
                'success' => true,
                'error' => null,
            ];
        }

        $logoPath = (string)$company['logo_path'];

        DB::query(
            'UPDATE org_companies SET logo_path = NULL, updated_at = NOW() WHERE id = ?',
            [$companyId]
        );

        DB::query(
            'UPDATE branding_assets SET is_active = 0, updated_at = NOW() WHERE company_id = ? AND file_path = ?',
            [$companyId, $logoPath]
        );

        return [
            'success' => true,
            'error' => null,
        ];
    }

    /**
     * Upload a generic branding asset (favicon, social image, etc.)
     *
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    public static function uploadBrandingAsset(int $companyId, string $usageKey, array $file): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);
        $usageKey = trim($usageKey);

        if ($usageKey === '' || $usageKey === 'primary') {
            // 'primary' is reserved for logos; use uploadCompanyLogo instead
            return ['success' => false, 'error' => (string)t('organization.error.invalid_usage_key'), 'path' => null];
        }

        require_once APP_ROOT . '/app/Services/LogoUploadService.php';
        $result = \App\Services\LogoUploadService::processUpload($file);

        if (!(bool)($result['success'] ?? false)) {
            return $result;
        }

        // Mark other active assets with this usage_key as inactive
        DB::query(
            'UPDATE branding_assets
             SET is_active = 0, updated_at = NOW()
             WHERE company_id = ? AND asset_group = ? AND usage_key = ?',
            [$companyId, 'logo', $usageKey]
        );

        $newPath = (string)($result['path'] ?? '');

        self::insertBrandingAssetRecord(
            $companyId,
            $newPath,
            [
                'display_name'    => (string)($file['name'] ?? ''),
                'mime_type'       => (string)($file['type'] ?? ''),
                'file_size_bytes' => (int)($file['size'] ?? 0),
                'usage_key'       => $usageKey,
            ],
            true
        );

        return [
            'success' => true,
            'path'    => $newPath,
            'error'   => null,
        ];
    }

    /**
     * Remove a branding asset by usage_key (favicon, social image, etc.)
     *
     * @return array<string,mixed>
     */
    public static function deleteAssetById(int $companyId, int $assetId): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);

        $asset = DB::fetchOne(
            'SELECT id, file_path, is_active FROM branding_assets WHERE id = ? AND company_id = ? LIMIT 1',
            [$assetId, $companyId]
        ) ?: null;

        if (!$asset) {
            return ['success' => false, 'error' => (string)t('organization.error.asset_not_found')];
        }

        if ((int)($asset['is_active'] ?? 0) === 1) {
            return ['success' => false, 'error' => (string)t('organization.error.cannot_delete_active_asset')];
        }

        $fp = trim((string)($asset['file_path'] ?? ''));
        if ($fp !== '') {
            // Only unlink if no other asset references this file
            $otherRefs = DB::fetchOne(
                'SELECT id FROM branding_assets WHERE file_path = ? AND id != ? AND is_active = 1 LIMIT 1',
                [$fp, $assetId]
            );
            if (!$otherRefs) {
                $fullPath = APP_ROOT . $fp;
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        DB::query('DELETE FROM branding_assets WHERE id = ? LIMIT 1', [$assetId]);

        return ['success' => true, 'error' => null];
    }

    public static function removeBrandingAsset(int $companyId, string $usageKey): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);
        $usageKey = trim($usageKey);

        if ($usageKey === 'primary') {
            // Use removeCompanyLogo for logo removal
            return ['success' => false, 'error' => (string)t('organization.error.cannot_remove_primary')];
        }

        DB::query(
            'UPDATE branding_assets SET is_active = 0, updated_at = NOW()
             WHERE company_id = ? AND asset_group = ? AND usage_key = ?',
            [$companyId, 'logo', $usageKey]
        );

        return ['success' => true, 'error' => null];
    }

    /**
     * Get the active favicon asset for a company (or empty array)
     *
     * @return array<string,mixed>
     */
    public static function getFaviconAsset(int $companyId = 0): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, false);
        if ($companyId <= 0) {
            return [];
        }

        $asset = DB::fetchOne(
            'SELECT * FROM branding_assets
             WHERE company_id = ? AND asset_group = ? AND usage_key = ? AND is_active = 1
             LIMIT 1',
            [$companyId, 'logo', 'favicon']
        ) ?: null;

        if (!$asset) {
            return [];
        }

        $asset['preview_url'] = LogoUploadService::getLogoUrl((string)($asset['file_path'] ?? ''));
        return (array)$asset;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listBrandingAssets(int $companyId = 0): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, false);
        if ($companyId <= 0) {
            return [];
        }

        self::syncBrandingAssetsFromCompany($companyId);

        $rows = DB::fetchAll(
            'SELECT * FROM branding_assets WHERE company_id = ? ORDER BY is_active DESC, created_at DESC, id DESC',
            [$companyId]
        );

        foreach ($rows as &$row) {
            $row['preview_url']       = LogoUploadService::getLogoUrl((string)($row['file_path'] ?? ''));
            $row['file_size_label']   = self::formatBytes((int)($row['file_size_bytes'] ?? 0));
            $width  = (int)($row['pixel_width'] ?? 0);
            $height = (int)($row['pixel_height'] ?? 0);
            $filePath = (string)($row['file_path'] ?? '');
            $isSvg = str_ends_with(strtolower($filePath), '.svg');

            // For SVG assets, provide inline content for the thumbnail (avoids CSS isolation of <img>)
            $row['svg_inline_content'] = '';
            if ($isSvg) {
                try {
                    $absPath = APP_ROOT . $filePath;
                    if (is_file($absPath)) {
                        $raw = (string)file_get_contents($absPath);
                        $raw = preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $raw);
                        $row['svg_inline_content'] = trim((string)$raw);
                    }
                } catch (\Throwable) {
                    // Non-critical
                }
            }

            // Dimensions: use pixel W×H if available; for SVG-only (no raster dims), extract from viewBox
            if ($width > 0 && $height > 0) {
                $row['dimensions_label'] = $width . 'x' . $height;
            } elseif ($isSvg && $row['svg_inline_content'] !== '') {
                if (preg_match('/viewBox\s*=\s*["\'][\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)["\']/', $row['svg_inline_content'], $vbm)) {
                    $vbW = (int)round((float)$vbm[1]);
                    $vbH = (int)round((float)$vbm[2]);
                    $row['dimensions_label'] = $vbW > 0 && $vbH > 0 ? 'SVG ' . $vbW . 'x' . $vbH : 'SVG';
                } else {
                    $row['dimensions_label'] = 'SVG';
                }
            } else {
                $row['dimensions_label'] = '';
            }

            $vk = (string)($row['variant_key'] ?? 'original');
            $surfaces = \App\Services\BrandingVariantService::VARIANT_SURFACES;
            $row['surface_label_key'] = $surfaces[$vk] ?? '';
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    public static function activateBrandingAsset(int $companyId, int $assetId): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);
        self::syncBrandingAssetsFromCompany($companyId);

        $asset = DB::fetchOne(
            'SELECT * FROM branding_assets WHERE id = ? AND company_id = ? LIMIT 1',
            [$assetId, $companyId]
        ) ?: null;

        if (!$asset) {
            return ['success' => false, 'error' => (string)t('organization.error.asset_not_found')];
        }

        DB::query(
            'UPDATE branding_assets
             SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END,
                 updated_at = NOW()
             WHERE company_id = ? AND asset_group = ? AND usage_key = ?',
            [$assetId, $companyId, 'logo', 'primary']
        );

        DB::query(
            'UPDATE org_companies SET logo_path = ?, updated_at = NOW() WHERE id = ?',
            [(string)($asset['file_path'] ?? ''), $companyId]
        );

        return ['success' => true, 'error' => null];
    }

    /**
     * Resolve the web-safe URL for a specific logo variant.
     *
     * Queries branding_assets for the active asset matching the given variant_key.
     * Falls back to $fallbackPath (the raw logo_path) when no variant record exists,
     * and finally returns an empty string if neither is available.
     *
     * @param  int    $companyId   0 = resolve primary company
     * @param  string $variantKey  'original' | 'display' | 'icon'
     * @param  string $fallbackPath  raw storage path, e.g. /storage/branding/logo.png
     * @return string  Web-safe URL or ''
     */
    public static function getVariantUrl(int $companyId, string $variantKey, string $fallbackPath = ''): string
    {
        $companyId = self::resolveCompanyId($companyId, false);
        if ($companyId > 0) {
            try {
                $row = DB::fetchOne(
                    "SELECT file_path FROM branding_assets
                     WHERE company_id = ? AND asset_group = 'logo' AND usage_key = 'primary'
                       AND variant_key = ?
                     ORDER BY created_at DESC, id DESC LIMIT 1",
                    [$companyId, $variantKey]
                );
                if ($row && trim((string)($row['file_path'] ?? '')) !== '') {
                    return (string)(LogoUploadService::getLogoUrl((string)$row['file_path']) ?? '');
                }
            } catch (\Throwable) {
                // Non-critical; fall through to fallback.
            }
        }

        if ($fallbackPath !== '') {
            return (string)(LogoUploadService::getLogoUrl($fallbackPath) ?? '');
        }

        return '';
    }

    public static function ensureLogoSvgThemeColumn(): void
    {
        try {
            $exists = DB::fetchOne(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'org_companies'
                    AND COLUMN_NAME = 'logo_svg_theme'
                  LIMIT 1"
            );
            if (!$exists) {
                DB::query("ALTER TABLE org_companies ADD COLUMN logo_svg_theme VARCHAR(40) NULL DEFAULT NULL");
            }
        } catch (\Throwable) {
            // Non-critical
        }
    }

    /**
     * @return array<string,string>
     */
    public static function svgThemeOptions(): array
    {
        return [
            ''            => (string)t('organization.branding.svg_theme_default'),
            'theme-auto'  => (string)t('organization.branding.svg_theme_auto'),
            'theme-flat'  => (string)t('organization.branding.svg_theme_flat'),
            'theme-ink'   => (string)t('organization.branding.svg_theme_ink'),
            'theme-ocean' => (string)t('organization.branding.svg_theme_ocean'),
            'theme-warm'  => (string)t('organization.branding.svg_theme_warm'),
            'theme-dawn'  => (string)t('organization.branding.svg_theme_dawn'),
        ];
    }

    public static function countOrphanedStorageFiles(int $companyId): int
    {
        $companyId = self::resolveCompanyId($companyId, false);

        // Build set of all actively referenced file paths
        $activeRows = DB::fetchAll('SELECT file_path FROM branding_assets WHERE is_active = 1', []);
        $activePaths = [];
        foreach ($activeRows as $ar) {
            $p = trim((string)($ar['file_path'] ?? ''));
            if ($p !== '') {
                $activePaths[$p] = true;
            }
        }
        if ($companyId > 0) {
            $companyRow = self::companyById($companyId);
            $currentLogoPath = trim((string)($companyRow['logo_path'] ?? ''));
            if ($currentLogoPath !== '') {
                $activePaths[$currentLogoPath] = true;
            }
        }

        // Count inactive DB rows
        $count = 0;
        if ($companyId > 0) {
            $inactiveRows = DB::fetchAll(
                'SELECT file_path FROM branding_assets WHERE company_id = ? AND is_active = 0',
                [$companyId]
            );
            foreach ($inactiveRows as $row) {
                $fp = trim((string)($row['file_path'] ?? ''));
                if ($fp !== '' && !isset($activePaths[$fp])) {
                    $count++;
                }
            }
        }

        // Count orphaned physical files
        $brandingDir = APP_ROOT . '/storage/branding';
        if (is_dir($brandingDir)) {
            foreach (new \DirectoryIterator($brandingDir) as $file) {
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }
                $relPath = '/storage/branding/' . $file->getFilename();
                if (!isset($activePaths[$relPath])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Delete all inactive branding asset records and their physical files for a company.
     *
     * @return array<string,mixed>
     */
    public static function purgeInactiveLogoAssets(int $companyId): array
    {
        self::ensureBrandingAssetSchema();
        $companyId = self::resolveCompanyId($companyId, true);

        // Collect all file_paths that are actively referenced (by any company)
        $activeRows = DB::fetchAll(
            'SELECT file_path FROM branding_assets WHERE is_active = 1',
            []
        );
        $activePaths = [];
        foreach ($activeRows as $ar) {
            $p = trim((string)($ar['file_path'] ?? ''));
            if ($p !== '') {
                $activePaths[$p] = true;
            }
        }
        // Also protect the path currently set on org_companies
        $companyRow = self::companyById($companyId);
        $currentLogoPath = trim((string)($companyRow['logo_path'] ?? ''));
        if ($currentLogoPath !== '') {
            $activePaths[$currentLogoPath] = true;
        }

        $deleted = 0;

        // 1. Delete inactive tracked assets
        $rows = DB::fetchAll(
            'SELECT id, file_path FROM branding_assets WHERE company_id = ? AND is_active = 0',
            [$companyId]
        );
        foreach ($rows as $row) {
            $fp = (string)($row['file_path'] ?? '');
            if ($fp !== '' && !isset($activePaths[$fp])) {
                $fullPath = APP_ROOT . $fp;
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
            DB::query('DELETE FROM branding_assets WHERE id = ? LIMIT 1', [(int)$row['id']]);
            $deleted++;
        }

        // 2. Sweep orphaned physical files in storage/branding/ not referenced by any active asset
        $brandingDir = APP_ROOT . '/storage/branding';
        if (is_dir($brandingDir)) {
            foreach (new \DirectoryIterator($brandingDir) as $file) {
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }
                $relPath = '/storage/branding/' . $file->getFilename();
                if (!isset($activePaths[$relPath])) {
                    @unlink($file->getPathname());
                    $deleted++;
                }
            }
        }

        return ['success' => true, 'deleted' => $deleted, 'error' => null];
    }

    public static function ensureBrandingAssetSchema(): void
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS branding_assets (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                asset_group VARCHAR(40) NOT NULL DEFAULT 'logo',
                usage_key VARCHAR(64) NOT NULL DEFAULT 'primary',
                variant_key VARCHAR(64) NOT NULL DEFAULT 'original',
                display_name VARCHAR(190) NULL,
                file_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NULL,
                file_size_bytes BIGINT NOT NULL DEFAULT 0,
                pixel_width INT NULL,
                pixel_height INT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                source_kind VARCHAR(40) NOT NULL DEFAULT 'upload',
                created_by VARCHAR(190) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_branding_assets_company (company_id),
                KEY idx_branding_assets_usage (company_id, asset_group, usage_key, is_active),
                KEY idx_branding_assets_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveCompany(array $input): int
    {
        $id = (int)($input['id'] ?? 0);
        $companyCode = self::normalizeCode((string)($input['company_code'] ?? ''));
        $companyName = trim((string)($input['company_name'] ?? ''));
        $existingCompany = $id > 0 ? self::companyById($id) : null;

        if ($companyName === '') {
            throw new \InvalidArgumentException((string)t('organization.error.company_name_required'));
        }
        if ($companyCode === '') {
            throw new \InvalidArgumentException((string)t('organization.error.company_code_required'));
        }

        $existing = DB::fetchOne('SELECT id FROM org_companies WHERE company_code = ? LIMIT 1', [$companyCode]);
        if ($existing && (int)($existing['id'] ?? 0) !== $id) {
            throw new \InvalidArgumentException((string)t('organization.error.company_code_not_unique'));
        }

        $payload = [
            'company_code' => $companyCode,
            'company_name' => $companyName,
            'legal_name' => self::nullIfBlank((string)($input['legal_name'] ?? '')),
            'registration_no' => self::nullIfBlank((string)($input['registration_no'] ?? '')),
            'tax_no' => self::nullIfBlank((string)($input['tax_no'] ?? '')),
            'base_currency' => self::normalizeCurrency((string)($input['base_currency'] ?? '')),
            'timezone' => self::normalizeTimezone((string)($input['timezone'] ?? '')),
            'email' => self::nullIfBlank((string)($input['email'] ?? '')),
            'phone' => self::nullIfBlank((string)($input['phone'] ?? '')),
            'website' => self::nullIfBlank((string)($input['website'] ?? '')),
            'address_line_1' => self::nullIfBlank((string)($input['address_line_1'] ?? '')),
            'address_line_2' => self::nullIfBlank((string)($input['address_line_2'] ?? '')),
            'city' => self::nullIfBlank((string)($input['city'] ?? '')),
            'state' => self::nullIfBlank((string)($input['state'] ?? '')),
            'postal_code' => self::nullIfBlank((string)($input['postal_code'] ?? '')),
            'country' => self::nullIfBlank((string)($input['country'] ?? '')),
            'logo_path' => array_key_exists('logo_path', $input)
                ? self::nullIfBlank((string)($input['logo_path'] ?? ''))
                : self::nullIfBlank((string)($existingCompany['logo_path'] ?? '')),
            'short_brand_name' => array_key_exists('short_brand_name', $input)
                ? self::nullIfBlank((string)($input['short_brand_name'] ?? ''))
                : self::nullIfBlank((string)($existingCompany['short_brand_name'] ?? '')),
            'report_header_text' => array_key_exists('report_header_text', $input)
                ? self::nullIfBlank((string)($input['report_header_text'] ?? ''))
                : self::nullIfBlank((string)($existingCompany['report_header_text'] ?? '')),
            'report_footer_text' => array_key_exists('report_footer_text', $input)
                ? self::nullIfBlank((string)($input['report_footer_text'] ?? ''))
                : self::nullIfBlank((string)($existingCompany['report_footer_text'] ?? '')),
            'is_primary' => 1,
            'is_active' => self::toBoolInt($input['is_active'] ?? '0'),
        ];

        if ($id > 0) {
            DB::query(
                'UPDATE org_companies
                 SET company_code = ?, company_name = ?, legal_name = ?, registration_no = ?, tax_no = ?, base_currency = ?, timezone = ?,
                     email = ?, phone = ?, website = ?, address_line_1 = ?, address_line_2 = ?, city = ?, state = ?, postal_code = ?,
                     country = ?, logo_path = ?, short_brand_name = ?, report_header_text = ?, report_footer_text = ?, is_primary = ?, is_active = ?,
                     updated_at = NOW()
                 WHERE id = ?',
                [
                    $payload['company_code'],
                    $payload['company_name'],
                    $payload['legal_name'],
                    $payload['registration_no'],
                    $payload['tax_no'],
                    $payload['base_currency'],
                    $payload['timezone'],
                    $payload['email'],
                    $payload['phone'],
                    $payload['website'],
                    $payload['address_line_1'],
                    $payload['address_line_2'],
                    $payload['city'],
                    $payload['state'],
                    $payload['postal_code'],
                    $payload['country'],
                    $payload['logo_path'],
                    $payload['short_brand_name'],
                    $payload['report_header_text'],
                    $payload['report_footer_text'],
                    $payload['is_primary'],
                    $payload['is_active'],
                    $id,
                ]
            );
        } else {
            DB::query(
                'INSERT INTO org_companies
                    (company_code, company_name, legal_name, registration_no, tax_no, base_currency, timezone, email, phone, website,
                     address_line_1, address_line_2, city, state, postal_code, country, logo_path, short_brand_name,
                     report_header_text, report_footer_text, is_primary, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $payload['company_code'],
                    $payload['company_name'],
                    $payload['legal_name'],
                    $payload['registration_no'],
                    $payload['tax_no'],
                    $payload['base_currency'],
                    $payload['timezone'],
                    $payload['email'],
                    $payload['phone'],
                    $payload['website'],
                    $payload['address_line_1'],
                    $payload['address_line_2'],
                    $payload['city'],
                    $payload['state'],
                    $payload['postal_code'],
                    $payload['country'],
                    $payload['logo_path'],
                    $payload['short_brand_name'],
                    $payload['report_header_text'],
                    $payload['report_footer_text'],
                    $payload['is_primary'],
                    $payload['is_active'],
                ]
            );
            $id = (int)(DB::conn()->insert_id ?? 0);
        }

        if ($id > 0) {
            DB::query('UPDATE org_companies SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END', [$id]);
            $savedCompany = self::companyById($id);
            if ($savedCompany) {
                (new CompanySettingsService())->syncFromOrganizationProfile($savedCompany);
            }
            $action = $existingCompany === null ? 'create' : 'update';
            self::logAuditChange($id, 'company', $id, $action, self::buildChangeset($existingCompany ?? [], $savedCompany ?? []));
        }

        return $id;
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveBranch(array $input): int
    {
        $id = (int)($input['id'] ?? 0);
        $companyId = self::resolveCompanyId((int)($input['company_id'] ?? 0), true);
        $branchCode = self::normalizeCode((string)($input['branch_code'] ?? ''));
        $branchName = trim((string)($input['branch_name'] ?? ''));
        $branchBefore = $id > 0 ? self::branchById($id) : null;

        if ($branchName === '') {
            throw new \InvalidArgumentException('Branch name is required.');
        }
        if ($branchCode === '') {
            throw new \InvalidArgumentException('Branch code is required.');
        }

        $existing = DB::fetchOne(
            'SELECT id FROM org_branches WHERE company_id = ? AND branch_code = ? LIMIT 1',
            [$companyId, $branchCode]
        );
        if ($existing && (int)($existing['id'] ?? 0) !== $id) {
            throw new \InvalidArgumentException('Branch code must be unique within the company.');
        }

        $payload = [
            'company_id' => $companyId,
            'branch_code' => $branchCode,
            'branch_name' => $branchName,
            'email' => self::nullIfBlank((string)($input['email'] ?? '')),
            'phone' => self::nullIfBlank((string)($input['phone'] ?? '')),
            'address_line_1' => self::nullIfBlank((string)($input['address_line_1'] ?? '')),
            'address_line_2' => self::nullIfBlank((string)($input['address_line_2'] ?? '')),
            'city' => self::nullIfBlank((string)($input['city'] ?? '')),
            'state' => self::nullIfBlank((string)($input['state'] ?? '')),
            'postal_code' => self::nullIfBlank((string)($input['postal_code'] ?? '')),
            'country' => self::nullIfBlank((string)($input['country'] ?? '')),
            'is_active' => self::toBoolInt($input['is_active'] ?? '0'),
        ];

        if ($id > 0) {
            DB::query(
                'UPDATE org_branches
                 SET company_id = ?, branch_code = ?, branch_name = ?, email = ?, phone = ?, address_line_1 = ?, address_line_2 = ?,
                     city = ?, state = ?, postal_code = ?, country = ?, is_active = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    $payload['company_id'],
                    $payload['branch_code'],
                    $payload['branch_name'],
                    $payload['email'],
                    $payload['phone'],
                    $payload['address_line_1'],
                    $payload['address_line_2'],
                    $payload['city'],
                    $payload['state'],
                    $payload['postal_code'],
                    $payload['country'],
                    $payload['is_active'],
                    $id,
                ]
            );
        } else {
            DB::query(
                'INSERT INTO org_branches
                    (company_id, branch_code, branch_name, email, phone, address_line_1, address_line_2, city, state, postal_code, country, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $payload['company_id'],
                    $payload['branch_code'],
                    $payload['branch_name'],
                    $payload['email'],
                    $payload['phone'],
                    $payload['address_line_1'],
                    $payload['address_line_2'],
                    $payload['city'],
                    $payload['state'],
                    $payload['postal_code'],
                    $payload['country'],
                    $payload['is_active'],
                ]
            );
            $id = (int)(DB::conn()->insert_id ?? 0);
        }

        $branchAfter = self::branchById($id);
        $branchAction = $branchBefore === null ? 'create' : 'update';
        self::logAuditChange($companyId, 'branch', $id, $branchAction, self::buildChangeset($branchBefore ?? [], $branchAfter ?? []));

        return $id;
    }

    public static function deleteBranch(int $branchId): void
    {
        if ($branchId <= 0) {
            throw new \InvalidArgumentException('Invalid branch selected.');
        }

        $branch = self::branchById($branchId);
        DB::query('DELETE FROM org_branches WHERE id = ? LIMIT 1', [$branchId]);
        if ($branch) {
            self::logAuditChange((int)$branch['company_id'], 'branch', $branchId, 'delete', []);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listBranches(int $companyId = 0, string $search = ''): array
    {
        $sql = 'SELECT b.*, c.company_name
                FROM org_branches b
                INNER JOIN org_companies c ON c.id = b.company_id
                WHERE 1 = 1';
        $params = [];

        if ($companyId > 0) {
            $sql .= ' AND b.company_id = ?';
            $params[] = $companyId;
        }

        $search = trim($search);
        if ($search !== '') {
            $sql .= ' AND (b.branch_name LIKE ? OR b.branch_code LIKE ? OR COALESCE(b.city, \'\') LIKE ? OR COALESCE(b.country, \'\') LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY b.branch_name ASC, b.id ASC';

        return DB::fetchAll($sql, $params);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function branchById(int $branchId): ?array
    {
        if ($branchId <= 0) {
            return null;
        }

        return DB::fetchOne('SELECT * FROM org_branches WHERE id = ? LIMIT 1', [$branchId]) ?: null;
    }

    /**
     * @return array<string,mixed>
     */
    public static function getFiscalSettings(int $companyId = 0): array
    {
        $companyId = self::resolveCompanyId($companyId, false);
        if ($companyId <= 0) {
            return self::emptyFiscalSettings();
        }

        $row = DB::fetchOne('SELECT * FROM org_fiscal_settings WHERE company_id = ? LIMIT 1', [$companyId]);
        if (!$row) {
            $row = self::emptyFiscalSettings();
            $row['company_id'] = $companyId;
            $row['is_configured'] = false;
            return $row;
        }

        $row['is_configured'] = true;
        return $row;
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveFiscalSettings(array $input): void
    {
        $companyId = self::resolveCompanyId((int)($input['company_id'] ?? 0), true);
        $fiscalYearStart = self::normalizeFiscalDay((string)($input['fiscal_year_start'] ?? ''));
        $fiscalYearEnd = self::normalizeFiscalDay((string)($input['fiscal_year_end'] ?? ''));

        if ($fiscalYearStart === null) {
            throw new \InvalidArgumentException('Fiscal year start must use MM-DD format.');
        }
        if ($fiscalYearEnd === null) {
            throw new \InvalidArgumentException('Fiscal year end must use MM-DD format.');
        }

        $payload = [
            'company_id' => $companyId,
            'fiscal_year_start' => $fiscalYearStart,
            'fiscal_year_end' => $fiscalYearEnd,
            'default_tax_mode' => self::normalizeTaxMode((string)($input['default_tax_mode'] ?? '')),
            'invoice_prefix' => self::nullIfBlank((string)($input['invoice_prefix'] ?? '')),
            'document_prefix_pattern' => self::nullIfBlank((string)($input['document_prefix_pattern'] ?? '')),
            'notes' => self::nullIfBlank((string)($input['notes'] ?? '')),
        ];

        $existing = DB::fetchOne('SELECT id FROM org_fiscal_settings WHERE company_id = ? LIMIT 1', [$companyId]);
        if ($existing) {
            DB::query(
                'UPDATE org_fiscal_settings
                 SET fiscal_year_start = ?, fiscal_year_end = ?, default_tax_mode = ?, invoice_prefix = ?, document_prefix_pattern = ?, notes = ?, updated_at = NOW()
                 WHERE company_id = ?',
                [
                    $payload['fiscal_year_start'],
                    $payload['fiscal_year_end'],
                    $payload['default_tax_mode'],
                    $payload['invoice_prefix'],
                    $payload['document_prefix_pattern'],
                    $payload['notes'],
                    $companyId,
                ]
            );
            return;
        }

        DB::query(
            'INSERT INTO org_fiscal_settings
                (company_id, fiscal_year_start, fiscal_year_end, default_tax_mode, invoice_prefix, document_prefix_pattern, notes)
             VALUES (?,?,?,?,?,?,?)',
            [
                $payload['company_id'],
                $payload['fiscal_year_start'],
                $payload['fiscal_year_end'],
                $payload['default_tax_mode'],
                $payload['invoice_prefix'],
                $payload['document_prefix_pattern'],
                $payload['notes'],
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function brandingProfile(int $companyId = 0): array
    {
        self::ensureBrandingAssetSchema();
        $company = $companyId > 0 ? self::companyById($companyId) : self::primaryCompany();
        if (!$company) {
            return self::emptyBrandingProfile();
        }

        self::ensureLogoSvgThemeColumn();
        self::syncBrandingAssetsFromCompany((int)($company['id'] ?? 0));

        return [
            'company_id' => (int)($company['id'] ?? 0),
            'company_name' => (string)($company['company_name'] ?? ''),
            'short_brand_name' => (string)($company['short_brand_name'] ?? ''),
            'logo_path' => (string)($company['logo_path'] ?? ''),
            'logo_svg_theme' => (string)($company['logo_svg_theme'] ?? ''),
            'report_header_text' => (string)($company['report_header_text'] ?? ''),
            'report_footer_text' => (string)($company['report_footer_text'] ?? ''),
            'is_configured' => trim((string)($company['short_brand_name'] ?? '')) !== ''
                || trim((string)($company['logo_path'] ?? '')) !== ''
                || trim((string)($company['report_header_text'] ?? '')) !== ''
                || trim((string)($company['report_footer_text'] ?? '')) !== '',
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveBranding(array $input): void
    {
        self::ensureLogoSvgThemeColumn();
        $companyId = self::resolveCompanyId((int)($input['company_id'] ?? 0), true);
        $existingCompany = self::companyById($companyId) ?? [];

        $allowedThemes = ['', 'theme-auto', 'theme-flat', 'theme-ink', 'theme-ocean', 'theme-warm', 'theme-dawn'];
        // Only apply submitted value if the field was actually in the POST; otherwise keep the stored value.
        if (array_key_exists('logo_svg_theme', $input)
            && in_array(trim((string)$input['logo_svg_theme']), $allowedThemes, true)
        ) {
            $svgTheme = trim((string)$input['logo_svg_theme']);
        } else {
            $svgTheme = (string)($existingCompany['logo_svg_theme'] ?? '');
        }

        DB::query(
            'UPDATE org_companies
             SET short_brand_name = ?, logo_path = ?, logo_svg_theme = ?, report_header_text = ?, report_footer_text = ?, updated_at = NOW()
             WHERE id = ?',
            [
                array_key_exists('short_brand_name', $input)
                    ? self::nullIfBlank((string)($input['short_brand_name'] ?? ''))
                    : self::nullIfBlank((string)($existingCompany['short_brand_name'] ?? '')),
                array_key_exists('logo_path', $input)
                    ? self::nullIfBlank((string)($input['logo_path'] ?? ''))
                    : self::nullIfBlank((string)($existingCompany['logo_path'] ?? '')),
                self::nullIfBlank($svgTheme),
                array_key_exists('report_header_text', $input)
                    ? self::nullIfBlank((string)($input['report_header_text'] ?? ''))
                    : self::nullIfBlank((string)($existingCompany['report_header_text'] ?? '')),
                array_key_exists('report_footer_text', $input)
                    ? self::nullIfBlank((string)($input['report_footer_text'] ?? ''))
                    : self::nullIfBlank((string)($existingCompany['report_footer_text'] ?? '')),
                $companyId,
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function emptyCompany(): array
    {
        return [
            'id' => 0,
            'company_code' => '',
            'company_name' => '',
            'legal_name' => '',
            'registration_no' => '',
            'tax_no' => '',
            'base_currency' => 'JPY',
            'timezone' => 'Asia/Tokyo',
            'email' => '',
            'phone' => '',
            'website' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
            'logo_path' => '',
            'short_brand_name' => '',
            'report_header_text' => '',
            'report_footer_text' => '',
            'is_active' => 1,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function emptyBranch(): array
    {
        return [
            'id' => 0,
            'company_id' => self::primaryCompanyId(),
            'branch_code' => '',
            'branch_name' => '',
            'email' => '',
            'phone' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
            'is_active' => 1,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function emptyFiscalSettings(): array
    {
        return [
            'company_id' => 0,
            'fiscal_year_start' => '04-01',
            'fiscal_year_end' => '03-31',
            'default_tax_mode' => 'exclusive',
            'invoice_prefix' => '',
            'document_prefix_pattern' => '',
            'notes' => '',
            'is_configured' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function emptyBrandingProfile(): array
    {
        return [
            'company_id' => 0,
            'company_name' => '',
            'short_brand_name' => '',
            'logo_path' => '',
            'report_header_text' => '',
            'report_footer_text' => '',
            'is_configured' => false,
        ];
    }

    private static function resolveCompanyId(int $companyId, bool $required): int
    {
        if ($companyId <= 0) {
            $companyId = self::primaryCompanyId();
        }

        if ($companyId <= 0 && $required) {
            throw new \InvalidArgumentException('Create the company profile first.');
        }

        if ($companyId > 0 && !self::companyById($companyId)) {
            throw new \InvalidArgumentException('Selected company was not found.');
        }

        return $companyId;
    }

    private static function seedPrimaryCompanyFromLegacySettings(): void
    {
        if (self::$legacySeedAttempted) {
            return;
        }
        self::$legacySeedAttempted = true;

        $existing = DB::fetchOne('SELECT id FROM org_companies LIMIT 1');
        if ($existing) {
            return;
        }

        $settings = (new CompanySettingsService())->currentSettings();
        if (!self::hasMeaningfulLegacyCompanySettings($settings)) {
            return;
        }

        $companyName = trim((string)($settings['company.name'] ?? $settings['system.name'] ?? ''));
        if ($companyName === '') {
            $companyName = APP_NAME;
        }

        $companyCode = self::normalizeCode((string)($settings['company.code'] ?? ''));
        if ($companyCode === '') {
            $companyCode = self::fallbackCompanyCode($companyName);
        }

        DB::query(
            'INSERT INTO org_companies
                (company_code, company_name, legal_name, registration_no, tax_no, base_currency, timezone, email, phone, website,
                 address_line_1, address_line_2, city, state, postal_code, country, is_primary, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $companyCode,
                $companyName,
                self::nullIfBlank((string)($settings['company.legal_name'] ?? '')),
                self::nullIfBlank((string)($settings['company.registration_number'] ?? '')),
                self::nullIfBlank((string)($settings['company.tax_id'] ?? '')),
                self::normalizeCurrency((string)($settings['system.default_currency'] ?? 'JPY')),
                self::normalizeTimezone((string)($settings['system.timezone'] ?? 'Asia/Tokyo')),
                self::nullIfBlank((string)($settings['company.email'] ?? '')),
                self::nullIfBlank((string)($settings['company.phone'] ?? '')),
                self::nullIfBlank((string)($settings['company.website'] ?? '')),
                self::nullIfBlank((string)($settings['company.address_line1'] ?? '')),
                self::nullIfBlank((string)($settings['company.address_line2'] ?? '')),
                self::nullIfBlank((string)($settings['company.city'] ?? '')),
                self::nullIfBlank((string)($settings['company.state'] ?? '')),
                self::nullIfBlank((string)($settings['company.postal_code'] ?? '')),
                self::nullIfBlank((string)($settings['company.country'] ?? '')),
                1,
                1,
            ]
        );
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function hasMeaningfulLegacyCompanySettings(array $settings): bool
    {
        $companyName = trim((string)($settings['company.name'] ?? ''));
        $systemName = trim((string)($settings['system.name'] ?? ''));
        if ($companyName !== '' && $companyName !== APP_NAME) {
            return true;
        }
        if ($systemName !== '' && $systemName !== APP_NAME) {
            return true;
        }
        if (strtoupper(trim((string)($settings['system.default_currency'] ?? 'JPY'))) !== 'JPY') {
            return true;
        }
        if (trim((string)($settings['system.timezone'] ?? 'Asia/Tokyo')) !== 'Asia/Tokyo') {
            return true;
        }

        foreach ([
            'company.legal_name',
            'company.code',
            'company.email',
            'company.phone',
            'company.website',
            'company.tax_id',
            'company.registration_number',
            'company.address_line1',
            'company.address_line2',
            'company.city',
            'company.state',
            'company.postal_code',
            'company.country',
        ] as $key) {
            if (trim((string)($settings[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function fallbackCompanyCode(string $companyName): string
    {
        $value = strtoupper(trim($companyName));
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        if ($value === '') {
            return 'PRIMARY';
        }

        return substr($value, 0, 40);
    }

    private static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        return $currency !== '' ? $currency : 'JPY';
    }

    private static function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        return $timezone !== '' ? $timezone : 'Asia/Tokyo';
    }

    private static function normalizeTaxMode(string $taxMode): string
    {
        $taxMode = strtolower(trim($taxMode));
        return array_key_exists($taxMode, self::taxModeOptions()) ? $taxMode : 'exclusive';
    }

    private static function normalizeCode(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_replace('/\s+/', '_', $value) ?? '';
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    /**
     * Returns MM-DD when valid, otherwise null.
     */
    private static function normalizeFiscalDay(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return substr($value, 5, 5);
        }

        if (preg_match('/^\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function toBoolInt($value): int
    {
        if (is_int($value)) {
            return $value > 0 ? 1 : 0;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    private static function syncBrandingAssetsFromCompany(int $companyId): void
    {
        if ($companyId <= 0) {
            return;
        }

        $company = self::companyById($companyId);
        if (!$company) {
            return;
        }

        $logoPath = trim((string)($company['logo_path'] ?? ''));
        if ($logoPath === '') {
            return;
        }

        $existing = DB::fetchOne(
            'SELECT id FROM branding_assets WHERE company_id = ? AND file_path = ? LIMIT 1',
            [$companyId, $logoPath]
        ) ?: null;

        if ($existing) {
            DB::query(
                'UPDATE branding_assets
                 SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END,
                     updated_at = NOW()
                 WHERE company_id = ? AND asset_group = ? AND usage_key = ?',
                [(int)($existing['id'] ?? 0), $companyId, 'logo', 'primary']
            );
            return;
        }

        DB::query(
            'UPDATE branding_assets
             SET is_active = 0, updated_at = NOW()
             WHERE company_id = ? AND asset_group = ? AND usage_key = ?',
            [$companyId, 'logo', 'primary']
        );

        self::insertBrandingAssetRecord(
            $companyId,
            $logoPath,
            ['display_name' => basename($logoPath), 'source_kind' => 'backfill'],
            true
        );
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private static function insertBrandingAssetRecord(int $companyId, string $filePath, array $metadata, bool $isActive): void
    {
        $inspected = self::inspectBrandingFile($filePath, $metadata);
        $usageKey = (string)($metadata['usage_key'] ?? 'primary');

        DB::query(
            'INSERT INTO branding_assets
                (company_id, asset_group, usage_key, variant_key, display_name, file_path, mime_type, file_size_bytes, pixel_width, pixel_height, is_active, source_kind, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $companyId,
                'logo',
                $usageKey,
                (string)($metadata['variant_key'] ?? 'original'),
                self::nullIfBlank((string)($inspected['display_name'] ?? basename($filePath))),
                $filePath,
                self::nullIfBlank((string)($inspected['mime_type'] ?? '')),
                (int)($inspected['file_size_bytes'] ?? 0),
                (int)($inspected['pixel_width'] ?? 0) ?: null,
                (int)($inspected['pixel_height'] ?? 0) ?: null,
                $isActive ? 1 : 0,
                (string)($inspected['source_kind'] ?? 'upload'),
                self::nullIfBlank((string)($inspected['created_by'] ?? '')),
            ]
        );
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    private static function inspectBrandingFile(string $filePath, array $metadata = []): array
    {
        $absolutePath = APP_ROOT . '/' . ltrim($filePath, '/');
        $mimeType = trim((string)($metadata['mime_type'] ?? ''));
        $fileSizeBytes = (int)($metadata['file_size_bytes'] ?? 0);
        $pixelWidth = (int)($metadata['pixel_width'] ?? 0);
        $pixelHeight = (int)($metadata['pixel_height'] ?? 0);

        if (is_file($absolutePath)) {
            if ($fileSizeBytes <= 0) {
                $fileSizeBytes = (int)(filesize($absolutePath) ?: 0);
            }

            if ($mimeType === '' && function_exists('mime_content_type')) {
                $mimeType = (string)(mime_content_type($absolutePath) ?: '');
            }

            $imageInfo = @getimagesize($absolutePath);
            if (is_array($imageInfo)) {
                $pixelWidth = (int)($imageInfo[0] ?? 0);
                $pixelHeight = (int)($imageInfo[1] ?? 0);
                if ($mimeType === '') {
                    $mimeType = (string)($imageInfo['mime'] ?? '');
                }
            }
        }

        return [
            'display_name' => (string)($metadata['display_name'] ?? basename($filePath)),
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSizeBytes,
            'pixel_width' => $pixelWidth,
            'pixel_height' => $pixelHeight,
            'source_kind' => (string)($metadata['source_kind'] ?? 'upload'),
            'created_by' => (string)($metadata['created_by'] ?? ''),
        ];
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int)floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));
        $value = $bytes / (1024 ** $power);

        return round($value, $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    // -------------------------------------------------------------------------
    // Hierarchy
    // -------------------------------------------------------------------------

    /**
     * Return a nested tree of all active hierarchy nodes for a company.
     * Each node: {id, hierarchy_code, hierarchy_name, hierarchy_type, level, sort_order, parent_id, children[]}
     *
     * @return array<int,array<string,mixed>>
     */
    public static function hierarchyTree(int $companyId): array
    {
        $rows = DB::fetchAll(
            'SELECT id, parent_id, hierarchy_code, hierarchy_name, hierarchy_type, level, sort_order
             FROM org_hierarchy
             WHERE company_id = ? AND is_active = 1
             ORDER BY level ASC, sort_order ASC, hierarchy_name ASC',
            [$companyId]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $indexed[(int)$row['id']] = $row;
        }

        $roots = [];
        foreach ($indexed as $id => $node) {
            $pid = (int)($node['parent_id'] ?? 0);
            if ($pid > 0 && isset($indexed[$pid])) {
                $indexed[$pid]['children'][] = &$indexed[$id];
            } else {
                $roots[] = &$indexed[$id];
            }
        }

        return $roots;
    }

    /**
     * Return a flat list of hierarchy nodes for a company (for dropdowns, etc.).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function hierarchyFlat(int $companyId): array
    {
        return DB::fetchAll(
            'SELECT id, parent_id, hierarchy_code, hierarchy_name, hierarchy_type, level, sort_order, is_active
             FROM org_hierarchy
             WHERE company_id = ?
             ORDER BY level ASC, sort_order ASC, hierarchy_name ASC',
            [$companyId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function hierarchyById(int $id): ?array
    {
        $row = DB::fetchOne('SELECT * FROM org_hierarchy WHERE id = ? LIMIT 1', [$id]);
        return $row ?: null;
    }

    /**
     * Save (insert or update) a hierarchy node.
     * Validates parent exists in same company and prevents ancestor cycles.
     *
     * @param array<string,mixed> $input
     * @throws \InvalidArgumentException
     */
    public static function saveHierarchy(int $companyId, array $input): int
    {
        $id        = (int)($input['id'] ?? 0);
        $parentId  = ($input['parent_id'] !== '' && $input['parent_id'] !== null)
                        ? (int)$input['parent_id'] : null;
        $code      = trim((string)($input['hierarchy_code'] ?? ''));
        $name      = trim((string)($input['hierarchy_name'] ?? ''));
        $type      = trim((string)($input['hierarchy_type'] ?? 'department'));
        $sort      = (int)($input['sort_order'] ?? 0);
        $isActive  = self::toBoolInt($input['is_active'] ?? '1');

        $allowedTypes = ['department', 'region', 'costcenter'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'department';
        }

        if ($name === '') {
            throw new \InvalidArgumentException((string)t('organization.hierarchy.error.name_required'));
        }
        if ($code === '') {
            throw new \InvalidArgumentException((string)t('organization.hierarchy.error.code_required'));
        }

        // Unique code per company
        $dupCheck = DB::fetchOne(
            'SELECT id FROM org_hierarchy WHERE company_id = ? AND hierarchy_code = ? LIMIT 1',
            [$companyId, $code]
        );
        if ($dupCheck && (int)($dupCheck['id'] ?? 0) !== $id) {
            throw new \InvalidArgumentException((string)t('organization.hierarchy.error.code_not_unique'));
        }

        // Validate parent belongs to same company
        $level = 0;
        if ($parentId !== null) {
            $parentRow = DB::fetchOne(
                'SELECT id, company_id, level FROM org_hierarchy WHERE id = ? LIMIT 1',
                [$parentId]
            );
            if (!$parentRow || (int)$parentRow['company_id'] !== $companyId) {
                throw new \InvalidArgumentException((string)t('organization.hierarchy.error.invalid_parent'));
            }
            $level = (int)$parentRow['level'] + 1;

            // Cycle detection: make sure $parentId is not a descendant of $id
            if ($id > 0 && self::isDescendant($id, $parentId)) {
                throw new \InvalidArgumentException((string)t('organization.hierarchy.error.cycle_detected'));
            }
        }

        $before = $id > 0 ? self::hierarchyById($id) : null;

        if ($id > 0) {
            DB::query(
                'UPDATE org_hierarchy
                 SET parent_id = ?, hierarchy_code = ?, hierarchy_name = ?, hierarchy_type = ?,
                     level = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ?',
                [$parentId, $code, $name, $type, $level, $sort, $isActive, $id, $companyId]
            );
        } else {
            DB::query(
                'INSERT INTO org_hierarchy
                    (company_id, parent_id, hierarchy_code, hierarchy_name, hierarchy_type, level, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$companyId, $parentId, $code, $name, $type, $level, $sort, $isActive]
            );
            $id = (int)(DB::conn()->insert_id ?? 0);
        }

        $after = self::hierarchyById($id);
        $action = $before === null ? 'create' : 'update';
        self::logAuditChange($companyId, 'hierarchy', $id, $action, self::buildChangeset($before ?? [], $after ?? []));

        return $id;
    }

    /**
     * Soft-delete a hierarchy node (marks it and all its active descendants inactive).
     */
    public static function deleteHierarchy(int $hierarchyId): bool
    {
        $node = self::hierarchyById($hierarchyId);
        if (!$node) {
            return false;
        }
        $companyId = (int)$node['company_id'];

        // Mark this node and all descendants inactive
        DB::query(
            'UPDATE org_hierarchy SET is_active = 0, updated_at = NOW()
             WHERE company_id = ? AND (id = ? OR parent_id IN (
                 SELECT id FROM (SELECT id FROM org_hierarchy WHERE company_id = ? AND is_active = 1) sub
             ))',
            [$companyId, $hierarchyId, $companyId]
        );

        self::logAuditChange($companyId, 'hierarchy', $hierarchyId, 'delete', []);

        return true;
    }

    /**
     * Returns true if $ancestorId is an ancestor of $nodeId (cycle detection).
     */
    private static function isDescendant(int $ancestorId, int $candidateParentId): bool
    {
        $visited = [];
        $current = $candidateParentId;
        while ($current > 0) {
            if ($current === $ancestorId) {
                return true;
            }
            if (isset($visited[$current])) {
                break; // already-broken tree, stop
            }
            $visited[$current] = true;
            $row = DB::fetchOne('SELECT parent_id FROM org_hierarchy WHERE id = ? LIMIT 1', [$current]);
            $current = $row ? (int)($row['parent_id'] ?? 0) : 0;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Audit log
    // -------------------------------------------------------------------------

    /**
     * Write one audit-log row. Silently swallows errors so it never breaks a save.
     *
     * @param array<string,mixed> $changeset
     */
    public static function logAuditChange(
        int $companyId,
        string $entityType,
        int $entityId,
        string $action,
        array $changeset
    ): void {
        try {
            $user      = \App\Core\Auth::user();
            $userId    = (int)($user['id'] ?? 0);
            $userEmail = (string)($user['email'] ?? '');
            $ip        = (string)($_SERVER['REMOTE_ADDR'] ?? '');

            DB::query(
                'INSERT INTO org_audit_log
                    (company_id, entity_type, entity_id, action, changed_fields, user_id, user_email, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $companyId,
                    $entityType,
                    $entityId,
                    $action,
                    $changeset !== [] ? json_encode($changeset, JSON_UNESCAPED_UNICODE) : null,
                    $userId > 0 ? $userId : null,
                    $userEmail !== '' ? $userEmail : null,
                    $ip !== '' ? $ip : null,
                ]
            );
        } catch (\Throwable $e) {
            error_log('OrganizationService::logAuditChange: ' . $e->getMessage());
        }
    }

    /**
     * Return paginated audit log for a company.
     *
     * @param array<string,mixed> $filters  keys: entity_type, action, user_id, date_from, date_to, page, per_page
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public static function auditLog(int $companyId, array $filters = []): array
    {
        $where  = ['company_id = ?'];
        $params = [$companyId];

        $entityType = trim((string)($filters['entity_type'] ?? ''));
        if ($entityType !== '') {
            $where[]  = 'entity_type = ?';
            $params[] = $entityType;
        }

        $action = trim((string)($filters['action'] ?? ''));
        if ($action !== '') {
            $where[]  = 'action = ?';
            $params[] = $action;
        }

        $userId = (int)($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[]  = 'user_id = ?';
            $params[] = $userId;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[]  = 'created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[]  = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $total = (int)(DB::fetchOne("SELECT COUNT(*) AS n FROM org_audit_log WHERE {$whereClause}", $params)['n'] ?? 0);

        $page    = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int)($filters['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        $rows = DB::fetchAll(
            "SELECT id, entity_type, entity_id, action, changed_fields, user_id, user_email, ip_address, created_at
             FROM org_audit_log
             WHERE {$whereClause}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Return all audit log rows for a single entity (company, branch, hierarchy, etc.).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function entityChangeHistory(string $entityType, int $entityId): array
    {
        return DB::fetchAll(
            'SELECT id, action, changed_fields, user_id, user_email, ip_address, created_at
             FROM org_audit_log
             WHERE entity_type = ? AND entity_id = ?
             ORDER BY created_at DESC
             LIMIT 200',
            [$entityType, $entityId]
        );
    }

    /**
     * Build a before/after changeset from two snapshots (nulls omitted).
     *
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array<string,array{before:mixed,after:mixed}>
     */
    private static function buildChangeset(array $before, array $after): array
    {
        $changeset = [];
        $skipKeys  = ['updated_at', 'created_at'];

        foreach ($after as $key => $newVal) {
            if (in_array($key, $skipKeys, true)) {
                continue;
            }
            $oldVal = $before[$key] ?? null;
            if ($oldVal !== $newVal) {
                $changeset[$key] = ['before' => $oldVal, 'after' => $newVal];
            }
        }

        return $changeset;
    }
}

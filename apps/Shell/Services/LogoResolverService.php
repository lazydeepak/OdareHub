<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

final class LogoResolverService
{
    public static function resolve(): array
    {
        $result = [
            'has_logo' => false,
            'logo_url' => '',
            'logo_svg' => '',
            'logo_svg_theme' => '',
            'logo_icon_url' => '',
            'fallback_text' => 'OdareHub OS',
            'fallback_text_compact' => 'S',
        ];

        try {
            $identity = BrandIdentityService::runtime();
            $platformName = trim((string)($identity['platform_name'] ?? ''));
            if ($platformName !== '') {
                $result['fallback_text'] = $platformName;
                $result['fallback_text_compact'] = mb_substr($platformName, 0, 1);
            }

            $logoRow = \App\Core\DB::fetchOne(
                'SELECT id, logo_path, logo_svg_theme FROM org_companies ORDER BY id ASC LIMIT 1'
            );
            $rawPath = trim((string)($logoRow['logo_path'] ?? ''));
            $cid = (int)($logoRow['id'] ?? 0);
            $theme = trim((string)($logoRow['logo_svg_theme'] ?? ''));

            if ($cid < 1 || $rawPath === '') {
                return $result;
            }

            $result['has_logo'] = true;
            $result['logo_svg_theme'] = $theme;

            if (!str_ends_with(strtolower($rawPath), '.svg')) {
                $orgClass = '\\Plugins\\Organization\\Services\\OrganizationService';
                if (!class_exists($orgClass) && is_file(APP_ROOT . '/apps/Platform/modules/Organization/Services/OrganizationService.php')) {
                    require_once APP_ROOT . '/apps/Platform/modules/Organization/Services/OrganizationService.php';
                }
                if (class_exists($orgClass)) {
                    $result['logo_url'] = (string)$orgClass::getVariantUrl($cid, 'display', $rawPath);
                    $result['logo_icon_url'] = (string)$orgClass::getVariantUrl($cid, 'icon', $rawPath);
                }
            } else {
                $svgFilePath = APP_ROOT . $rawPath;
                if (is_file($svgFilePath)) {
                    $svgContent = (string)file_get_contents($svgFilePath);
                    $svgContent = preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $svgContent);
                    $result['logo_svg'] = trim($svgContent);
                }
            }
        } catch (\Throwable) {
            // Logo is non-critical
        }

        return $result;
    }
}

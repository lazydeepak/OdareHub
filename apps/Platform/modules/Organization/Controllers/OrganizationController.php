<?php
declare(strict_types=1);

namespace Plugins\Organization\Controllers;

use App\Core\Auth;
use App\Services\LogoUploadService;
use App\Core\View;
use Plugins\Organization\Services\OrganizationService;

require_once __DIR__ . '/../Services/OrganizationService.php';

final class OrganizationController
{
    public static function index(View $view): void
    {
        $view->render('Organization::index.php', [
            'pageTitle' => (string)t('organization.page_title_index'),
            'currentTab' => 'overview',
            'summary' => OrganizationService::landingSummary(),
            'canManage' => self::canManage(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function company(View $view): void
    {
        $company = OrganizationService::primaryCompany() ?? OrganizationService::emptyCompany();

        $view->render('Organization::company.php', [
            'pageTitle' => (string)t('organization.page_title_company'),
            'currentTab' => 'company',
            'company' => $company,
            'currencyOptions' => OrganizationService::currencyOptions(),
            'timezoneOptions' => OrganizationService::timezoneOptions(),
            'canManage' => self::canManage(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveCompany(array $input): void
    {
        try {
            OrganizationService::saveCompany($input);
            self::flash('ok', (string)t('organization.feedback.company_saved'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/company');
    }

    public static function branches(View $view): void
    {
        $companies = OrganizationService::listCompanies();
        $selectedCompanyId = (int)($_GET['company_id'] ?? 0);
        $editing = OrganizationService::branchById((int)($_GET['edit'] ?? 0));
        if ($editing && $selectedCompanyId <= 0) {
            $selectedCompanyId = (int)($editing['company_id'] ?? 0);
        }
        if ($selectedCompanyId <= 0) {
            $selectedCompanyId = (int)($companies[0]['id'] ?? 0);
        }

        $view->render('Organization::branches.php', [
            'pageTitle' => (string)t('organization.page_title_branches'),
            'currentTab' => 'branches',
            'companies' => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'rows' => OrganizationService::listBranches($selectedCompanyId, (string)($_GET['q'] ?? '')),
            'editing' => $editing ?? OrganizationService::emptyBranch(),
            'search' => trim((string)($_GET['q'] ?? '')),
            'canManage' => self::canManage(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveBranch(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);

        try {
            OrganizationService::saveBranch($input);
            self::flash('ok', (string)t('organization.feedback.branch_saved'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branches?company_id=' . $companyId);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function deleteBranch(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);

        try {
            OrganizationService::deleteBranch((int)($input['id'] ?? 0));
            self::flash('ok', (string)t('organization.feedback.branch_deleted'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branches?company_id=' . $companyId);
    }

    public static function fiscal(View $view): void
    {
        $company = OrganizationService::primaryCompany();
        $fiscal = $company
            ? OrganizationService::getFiscalSettings((int)$company['id'])
            : OrganizationService::emptyFiscalSettings();

        $view->render('Organization::fiscal.php', [
            'pageTitle' => (string)t('organization.page_title_fiscal'),
            'currentTab' => 'fiscal',
            'company' => $company,
            'fiscal' => $fiscal,
            'taxModeOptions' => OrganizationService::taxModeOptions(),
            'canManage' => self::canManage(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveFiscal(array $input): void
    {
        try {
            OrganizationService::saveFiscalSettings($input);
            self::flash('ok', (string)t('organization.feedback.fiscal_saved'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/fiscal');
    }

    public static function branding(View $view): void
    {
        $company = OrganizationService::primaryCompany();
        $branding = $company
            ? OrganizationService::brandingProfile((int)$company['id'])
            : OrganizationService::emptyBrandingProfile();

        $rawLogoPath = trim((string)($branding['logo_path'] ?? ''));
        $logoIsSvg = str_ends_with(strtolower($rawLogoPath), '.svg');
        $brandingLogoUrl = (string)(LogoUploadService::getLogoUrl($rawLogoPath) ?? '');

        // Inline SVG content for preview on branding page (sanitised strip of XML declaration)
        $logoSvgContent = '';
        if ($logoIsSvg && $brandingLogoUrl !== '') {
            try {
                $filePath = APP_ROOT . $rawLogoPath;
                if (is_file($filePath)) {
                    $raw = (string)file_get_contents($filePath);
                    $raw = preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $raw);
                    $logoSvgContent = trim((string)$raw);
                }
            } catch (\Throwable) {
                // Non-critical
            }
        }

        $view->render('Organization::branding.php', [
            'pageTitle' => (string)t('organization.page_title_branding'),
            'currentTab' => 'branding',
            'company' => $company,
            'branding' => $branding,
            'brandingAssets' => $company ? OrganizationService::listBrandingAssets((int)$company['id']) : [],
            'brandingLogoUrl' => $brandingLogoUrl,
            'logoIsSvg' => $logoIsSvg,
            'logoSvgContent' => $logoSvgContent,
            'svgThemeOptions' => OrganizationService::svgThemeOptions(),
            'orphanedStorageCount' => $company ? OrganizationService::countOrphanedStorageFiles((int)$company['id']) : 0,
            'logoUploadMaxBytes' => LogoUploadService::maxFileSizeBytes(),
            'logoUploadMaxLabel' => LogoUploadService::maxFileSizeLabel(),
            'canManage' => self::canManage(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveBranding(array $input): void
    {
        try {
            OrganizationService::saveBranding($input);
            self::flash('ok', (string)t('organization.feedback.branding_saved'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branding');
    }

    public static function uploadLogo(): void
    {
        if (!self::canManage()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => (string)t('organization.error.unauthorized')]);
            exit;
        }

        $companyId = (int)($_POST['company_id'] ?? 0);
        if ($companyId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => (string)t('organization.error.invalid_company_id')]);
            exit;
        }

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => (string)t('organization.error.no_file_uploaded')]);
            exit;
        }

        try {
            $result = OrganizationService::uploadCompanyLogo($companyId, $_FILES['logo']);
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function removeLogo(): void
    {
        if (!self::canManage()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => (string)t('organization.error.unauthorized')]);
            exit;
        }

        $companyId = (int)($_POST['company_id'] ?? 0);
        if ($companyId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => (string)t('organization.error.invalid_company_id')]);
            exit;
        }

        try {
            $result = OrganizationService::removeCompanyLogo($companyId);
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function activateLogoAsset(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);
        $assetId = (int)($input['asset_id'] ?? 0);

        try {
            $result = OrganizationService::activateBrandingAsset($companyId, $assetId);
            if (!(bool)($result['success'] ?? false)) {
                throw new \RuntimeException((string)($result['error'] ?? t('organization.error.asset_not_found')));
            }
            self::flash('ok', (string)t('organization.feedback.logo_activated'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branding');
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function deleteLogoAsset(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);
        $assetId = (int)($input['asset_id'] ?? 0);

        try {
            $result = OrganizationService::deleteAssetById($companyId, $assetId);
            if (!(bool)($result['success'] ?? false)) {
                throw new \RuntimeException((string)($result['error'] ?? t('organization.error.asset_not_found')));
            }
            self::flash('ok', (string)t('organization.feedback.asset_deleted'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branding');
    }

    /**
     * Re-generate display and icon raster variants from the current active logo.
     *
     * @param array<string,mixed> $input
     */
    public static function regenerateVariants(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);

        try {
            $result = OrganizationService::regenerateVariants($companyId);
            if (!(bool)($result['success'] ?? false)) {
                throw new \RuntimeException((string)($result['error'] ?? t('organization.error.variants_failed')));
            }
            self::flash('ok', (string)t('organization.feedback.variants_regenerated', ['{count}' => (string)(int)($result['count'] ?? 0)]));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branding');
    }

    /**
     * Upload a favicon asset.
     *
     * @param array<string,mixed> $input
     */
    public static function uploadFavicon(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);
        $file = is_array($_FILES['favicon'] ?? null) ? $_FILES['favicon'] : [];

        try {
            if (empty($file)) {
                throw new \RuntimeException((string)t('organization.error.no_file_selected'));
            }

            $result = OrganizationService::uploadBrandingAsset($companyId, 'favicon', $file);
            if (!(bool)($result['success'] ?? false)) {
                throw new \RuntimeException((string)($result['error'] ?? t('organization.error.favicon_upload_failed')));
            }
            self::flash('ok', (string)t('organization.feedback.favicon_uploaded'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branding');
    }

    /**
     * Remove the favicon asset.
     *
     * @param array<string,mixed> $input
     */
    public static function purgeInactiveAssets(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);

        try {
            $result = OrganizationService::purgeInactiveLogoAssets($companyId);
            $count = (int)($result['deleted'] ?? 0);
            self::flash('ok', strtr((string)t('organization.feedback.inactive_assets_purged'), ['{count}' => $count]));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branding');
    }

    /**
     * Remove the favicon asset.
     *
     * @param array<string,mixed> $input
     */
    public static function removeFavicon(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);

        try {
            $result = OrganizationService::removeBrandingAsset($companyId, 'favicon');
            if (!(bool)($result['success'] ?? false)) {
                throw new \RuntimeException((string)($result['error'] ?? t('organization.error.favicon_remove_failed')));
            }
            self::flash('ok', (string)t('organization.feedback.favicon_removed'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        self::redirect('/ops/organization/branding');
    }

    private static function canManage(): bool
    {
        return function_exists('can') ? can('organization.manage') : false;
    }

    // -------------------------------------------------------------------------
    // Hierarchy
    // -------------------------------------------------------------------------

    public static function hierarchy(View $view): void
    {
        $companies = OrganizationService::listCompanies();
        $selectedCompanyId = (int)($_GET['company_id'] ?? 0);
        if ($selectedCompanyId <= 0) {
            $selectedCompanyId = (int)($companies[0]['id'] ?? 0);
        }

        $editing = null;
        $editId = (int)($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $editing = OrganizationService::hierarchyById($editId);
        }

        $view->render('Organization::hierarchy.php', [
            'pageTitle' => (string)t('organization.hierarchy.title'),
            'currentTab' => 'hierarchy',
            'companies' => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'tree' => OrganizationService::hierarchyTree($selectedCompanyId),
            'flat' => OrganizationService::hierarchyFlat($selectedCompanyId),
            'editing' => $editing,
            'canManage' => self::canManage(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function saveHierarchy(array $input): void
    {
        $companyId = (int)($input['company_id'] ?? 0);
        try {
            OrganizationService::saveHierarchy($companyId, $input);
            self::flash('ok', (string)t('organization.hierarchy.feedback.saved'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }
        self::redirect('/ops/organization/hierarchy?company_id=' . $companyId);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function deleteHierarchy(array $input): void
    {
        $hierarchyId = (int)($input['hierarchy_id'] ?? 0);
        $companyId = (int)($input['company_id'] ?? 0);
        try {
            OrganizationService::deleteHierarchy($hierarchyId);
            self::flash('ok', (string)t('organization.hierarchy.feedback.deleted'));
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }
        self::redirect('/ops/organization/hierarchy?company_id=' . $companyId);
    }

    // -------------------------------------------------------------------------
    // Audit log
    // -------------------------------------------------------------------------

    public static function audit(View $view): void
    {
        $companies = OrganizationService::listCompanies();
        $selectedCompanyId = (int)($_GET['company_id'] ?? 0);
        if ($selectedCompanyId <= 0) {
            $selectedCompanyId = (int)($companies[0]['id'] ?? 0);
        }

        $filters = [
            'entity_type' => trim((string)($_GET['entity_type'] ?? '')),
            'action'      => trim((string)($_GET['action'] ?? '')),
            'date_from'   => trim((string)($_GET['date_from'] ?? '')),
            'date_to'     => trim((string)($_GET['date_to'] ?? '')),
            'page'        => max(1, (int)($_GET['page'] ?? 1)),
            'per_page'    => 50,
        ];

        $log = OrganizationService::auditLog($selectedCompanyId, $filters);

        $view->render('Organization::audit.php', [
            'pageTitle' => (string)t('organization.audit.title'),
            'currentTab' => 'audit',
            'companies' => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'log' => $log,
            'filters' => $filters,
            'canManage' => self::canManage(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    private static function flash(string $type, string $message): void
    {
        Auth::bootSession();
        if (!isset($_SESSION['organization_flash']) || !is_array($_SESSION['organization_flash'])) {
            $_SESSION['organization_flash'] = [];
        }
        $_SESSION['organization_flash'][$type] = $message;
    }

    private static function pullFlash(string $type): string
    {
        Auth::bootSession();
        $bag = is_array($_SESSION['organization_flash'] ?? null) ? $_SESSION['organization_flash'] : [];
        $message = trim((string)($bag[$type] ?? ''));
        if ($message !== '') {
            unset($_SESSION['organization_flash'][$type]);
        }
        if (empty($_SESSION['organization_flash'])) {
            unset($_SESSION['organization_flash']);
        }
        return $message;
    }

    private static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}

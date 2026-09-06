<?php
declare(strict_types=1);

use App\Core\Auth;
use Plugins\Organization\Controllers\OrganizationController;

require_once __DIR__ . '/Controllers/OrganizationController.php';

$legacyOrganizationCompanyTarget = '/ops/organization/company';

$router->get('/ops/organization', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization');
    OrganizationController::index($view);
    return null;
});

$router->get('/ops/organization/company', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/company');
    OrganizationController::company($view);
    return null;
});

$router->post('/ops/organization/company', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/company');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::saveCompany($_POST);
    return null;
});

$router->get('/ops/organization/branches', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/branches');
    OrganizationController::branches($view);
    return null;
});

$router->post('/ops/organization/branches', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branches');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::saveBranch($_POST);
    return null;
});

$router->post('/ops/organization/branches/delete', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branches');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::deleteBranch($_POST);
    return null;
});

$router->get('/ops/organization/fiscal', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/fiscal');
    OrganizationController::fiscal($view);
    return null;
});

$router->post('/ops/organization/fiscal', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/fiscal');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::saveFiscal($_POST);
    return null;
});

$router->get('/ops/organization/branding', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/branding');
    OrganizationController::branding($view);
    return null;
});

$router->post('/ops/organization/branding', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::saveBranding($_POST);
    return null;
});

$router->post('/ops/organization/branding/upload-logo', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::uploadLogo();
    return null;
});

$router->post('/ops/organization/branding/remove-logo', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::removeLogo();
    return null;
});

$router->post('/ops/organization/branding/activate-asset', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::activateLogoAsset($_POST);
    return null;
});

$router->post('/ops/organization/branding/delete-asset', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::deleteLogoAsset($_POST);
    return null;
});

$router->post('/ops/organization/branding/regenerate-variants', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::regenerateVariants($_POST);
    return null;
});

$router->post('/ops/organization/branding/purge-inactive', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::purgeInactiveAssets($_POST);
    return null;
});

$router->post('/ops/organization/branding/upload-favicon', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::uploadFavicon($_POST);
    return null;
});

$router->post('/ops/organization/branding/remove-favicon', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/branding');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::removeFavicon($_POST);
    return null;
});

$router->get('/ops/organization/hierarchy', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/hierarchy');
    OrganizationController::hierarchy($view);
    return null;
});

$router->post('/ops/organization/hierarchy/save', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/hierarchy');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::saveHierarchy($_POST);
    return null;
});

$router->post('/ops/organization/hierarchy/delete', function () {
    Auth::bootSession();
    acl_require('organization.manage', '/ops/organization/hierarchy');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    OrganizationController::deleteHierarchy($_POST);
    return null;
});

$router->get('/ops/organization/audit', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/audit');
    OrganizationController::audit($view);
    return null;
});

$router->get('/admin/company', function () use ($legacyOrganizationCompanyTarget) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], $legacyOrganizationCompanyTarget);
    header('Location: ' . $legacyOrganizationCompanyTarget, true, 302);
    exit;
});

$router->post('/admin/company', function () use ($legacyOrganizationCompanyTarget) {
    Auth::bootSession();
    acl_require('organization.manage', $legacyOrganizationCompanyTarget);
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    header('Location: ' . $legacyOrganizationCompanyTarget, true, 302);
    exit;
});

$router->get('/ops/organization/report', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/report');
    $view->render('Organization::report.php');
    return null;
});

$router->get('/ops/organization/export', function () use ($view) {
    Auth::bootSession();
    acl_require_any(['organization.view', 'organization.manage'], '/ops/organization/export');
    $view->render('Organization::export.php');
    return null;
});

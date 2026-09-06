<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Services/WorkspaceWrapperRegistry.php';

use Apps\Shell\Services\WorkspaceWrapperRegistry;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$platform = ['authority_role' => 'platform_admin'];
$appAdmin = ['authority_role' => 'app_admin'];
$appUser = ['authority_role' => 'app_user'];

$assert(WorkspaceWrapperRegistry::defaultWrapperForContext($platform) === WorkspaceWrapperRegistry::WRAPPER_ADMIN, 'platform admin lands in admin wrapper');
$assert(WorkspaceWrapperRegistry::defaultWrapperForContext($appAdmin) === WorkspaceWrapperRegistry::WRAPPER_ADMIN, 'app admin lands in admin wrapper');
$assert(WorkspaceWrapperRegistry::defaultWrapperForContext($appUser) === WorkspaceWrapperRegistry::WRAPPER_U, 'app user lands in operator wrapper');
$assert(WorkspaceWrapperRegistry::canAccessWrapper($appAdmin, WorkspaceWrapperRegistry::WRAPPER_ADMIN), 'app admin may access admin wrapper');
$assert(!WorkspaceWrapperRegistry::canAccessWrapper($appUser, WorkspaceWrapperRegistry::WRAPPER_ADMIN), 'app user remains confined from admin wrapper');
$assert(WorkspaceWrapperRegistry::landingPath($appAdmin, 'App.Owner') === '/admin/app.owner', 'app admin canonical landing is username based');
$assert(WorkspaceWrapperRegistry::matchesIdentityHandle('App.Owner', ['username' => 'app.owner']), 'normalized matching identity handle is accepted');
$assert(!WorkspaceWrapperRegistry::matchesIdentityHandle('other.owner', ['username' => 'app.owner']), 'mismatched admin handle is rejected');
$assert(WorkspaceWrapperRegistry::matchesIdentityHandle('mail.owner', ['email' => 'mail.owner@example.test']), 'email local part remains a valid identity handle');

echo "Admin wrapper role probe: {$assertions}/{$assertions} assertions passed\n";

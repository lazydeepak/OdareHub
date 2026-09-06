<?php
declare(strict_types=1);

use App\Core\AclPolicy;
use App\Core\Auth;

if (!function_exists('current_role')) {
	function current_role(): string
	{
		$user = Auth::user();
		return (string)($user['role'] ?? 'anonymous');
	}
}

if (!function_exists('can')) {
	function can(string $permKey, ?array $user = null): bool
	{
		return AclPolicy::can($permKey, $user ?? Auth::user());
	}
}

if (!function_exists('acl_can_any')) {
	/**
	 * @param array<int,string> $permKeys
	 */
	function acl_can_any(array $permKeys, ?array $user = null): bool
	{
		return AclPolicy::canAny($permKeys, $user ?? Auth::user());
	}
}

if (!function_exists('acl_require')) {
	function acl_require(string $permKey, ?string $intendedUrl = null): void
	{
		Auth::bootSession();
		if (!Auth::isLoggedIn()) {
			Auth::rememberIntendedUrl($intendedUrl);
			header('Location: /login');
			exit;
		}

		if (!can($permKey)) {
			http_response_code(403);
			echo 'Access denied.';
			exit;
		}
	}
}

if (!function_exists('acl_require_any')) {
	/**
	 * @param array<int,string> $permKeys
	 */
	function acl_require_any(array $permKeys, ?string $intendedUrl = null): void
	{
		Auth::bootSession();
		if (!Auth::isLoggedIn()) {
			Auth::rememberIntendedUrl($intendedUrl);
			header('Location: /login');
			exit;
		}

		if (!acl_can_any($permKeys)) {
			http_response_code(403);
			echo 'Access denied.';
			exit;
		}
	}
}

if (function_exists('base_register_menus')) {
	base_register_menus([
		[
			'key' => 'admin.acl',
			'label' => 'ACL / RBAC',
			'url' => '/admin/acl',
			'parent' => 'admin.root',
			'order' => 35,
			'perm' => 'acl.manage',
		],
	]);
}

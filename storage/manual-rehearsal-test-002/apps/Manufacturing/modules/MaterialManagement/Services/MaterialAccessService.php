<?php
declare(strict_types=1);

namespace Plugins\MaterialManagement\Services;

use App\Core\AclPolicy;
use App\Core\Auth;

final class MaterialAccessService
{
    /**
     * @return array<string,mixed>|null
     */
    public static function user(): ?array
    {
        return Auth::user();
    }

    /**
     * @return array<string,mixed>
     */
    public static function context(): array
    {
        $user = self::user();
        if (!$user) {
            return [];
        }

        return platform_user_context_contract()->resolveUserContext($user);
    }

    public static function canManage(): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        if (AclPolicy::canAny([
            'materials.admin',
            'materials.master.manage',
            'materials.planning.manage',
            'materials.orders.manage',
            'materials.stock.adjust',
            'materials.capacity.manage',
            'materials.cost.manage',
        ], $user)) {
            return true;
        }

        $ctx = self::context();
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? $user['authority_role'] ?? '')));
        $assignedApps = array_map('strval', (array)($ctx['assigned_apps'] ?? []));
        if ($authorityRole === 'platform_admin') {
            return true;
        }

        return $authorityRole === 'app_admin' && in_array('manufacturing', $assignedApps, true);
    }

    public static function canViewStock(): bool
    {
        $user = self::user();
        return self::canManage() || ($user !== null && AclPolicy::can('materials.stock.view', $user));
    }

    public static function canViewCoverage(): bool
    {
        $user = self::user();
        return self::canManage() || ($user !== null && AclPolicy::can('materials.coverage.view', $user));
    }

    public static function canAccessAny(): bool
    {
        return self::canManage() || self::canViewStock() || self::canViewCoverage();
    }

    public static function canViewSection(string $section): bool
    {
        return match ($section) {
            'dashboard' => self::canAccessAny(),
            'master', 'mapping', 'planning', 'orders', 'capacity', 'cost', 'receipt' => self::canManage(),
            'stock' => self::canViewStock(),
            'coverage' => self::canViewCoverage(),
            default => false,
        };
    }

    /**
     * @return array<int,array<string,string>>
     */
    public static function navigation(): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Materials Workspace', 'url' => '/apps/manufacturing/materials'],
            ['key' => 'master', 'label' => 'Material Master', 'url' => '/apps/manufacturing/materials/master'],
            ['key' => 'stock', 'label' => 'Material Stock', 'url' => '/apps/manufacturing/materials/stock'],
            ['key' => 'coverage', 'label' => 'Coverage', 'url' => '/apps/manufacturing/materials/coverage'],
            ['key' => 'planning', 'label' => 'Planning', 'url' => '/apps/manufacturing/materials/planning'],
            ['key' => 'mapping', 'label' => 'Part Mapping', 'url' => '/apps/manufacturing/materials/mapping'],
            ['key' => 'capacity', 'label' => 'Storage Capacity', 'url' => '/apps/manufacturing/materials/capacity'],
            ['key' => 'cost', 'label' => 'Material Cost', 'url' => '/apps/manufacturing/materials/cost'],
            ['key' => 'receipt', 'label' => 'Material Receipt', 'url' => '/apps/manufacturing/materials/receipt'],
            ['key' => 'orders', 'label' => 'Material Orders', 'url' => '/apps/manufacturing/materials/orders'],
        ];

        return array_values(array_filter($items, static fn (array $item): bool => self::canViewSection((string)$item['key'])));
    }

    public static function requireAnyAccess(?string $intendedUrl = null): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl($intendedUrl);
            header('Location: /login');
            exit;
        }

        if (!self::canAccessAny()) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }

    public static function requireSection(string $section, ?string $intendedUrl = null): void
    {
        self::requireAnyAccess($intendedUrl);
        if (!self::canViewSection($section)) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }
}

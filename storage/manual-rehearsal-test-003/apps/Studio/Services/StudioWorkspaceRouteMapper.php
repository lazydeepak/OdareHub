<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

use Platform\Security\EngineeringWorkspacePageContextResolver;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspacePageContextResolver.php';

final class StudioWorkspaceRouteMapper
{
    /**
     * Resolve an engineering workspace key for the given request path.
     *
     * @return string|null Workspace key (e.g. "Studio/tools/CustomizationStudio") or null if unmapped.
     */
    public static function resolveWorkspaceKey(string $requestPath): ?string
    {
        $surface = EngineeringWorkspacePageContextResolver::resolveSurfaceForRequest($requestPath);
        $classification = (string)($surface['surface_classification'] ?? '');
        $state = (string)($surface['resolution_state'] ?? '');
        if (($classification !== 'workspace_root' && $classification !== 'workspace_child') ||
            $state === 'excluded' ||
            $state === 'unresolved') {
            return null;
        }

        $workspaceKey = trim((string)($surface['workspace_key'] ?? ''));
        return $workspaceKey !== '' && str_starts_with($workspaceKey . '/', 'Studio/')
            ? $workspaceKey
            : null;
    }
}

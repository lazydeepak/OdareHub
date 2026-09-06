<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Shared\Services;

final class OwnerResolver
{
    private function __construct()
    {
    }

    public static function resolveFromPath(string $realPath): string
    {
        $root = rtrim(APP_ROOT, '/') . '/';
        if (!str_starts_with($realPath, $root)) {
            return 'other';
        }

        $relative = substr($realPath, strlen($root));
        $parts = explode('/', $relative);

        if (isset($parts[0]) && $parts[0] === 'apps' && isset($parts[1])) {
            if (isset($parts[2]) && $parts[2] === 'modules' && isset($parts[3])) {
                return $parts[1] . '/' . $parts[3];
            }
            return $parts[1];
        }
        if (isset($parts[0]) && $parts[0] === 'plugins' && isset($parts[1])) {
            return 'plugin:' . $parts[1];
        }
        if (isset($parts[0]) && $parts[0] === 'public' && isset($parts[3])) {
            return $parts[3];
        }
        if (isset($parts[0]) && $parts[0] === 'resources' && isset($parts[1])) {
            return 'theme:' . $parts[1];
        }
        if (isset($parts[0]) && $parts[0] === 'platform' && isset($parts[1])) {
            return 'platform:' . $parts[1];
        }

        return 'other';
    }
}

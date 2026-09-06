<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorSourceResolver
{
    /**
     * @return array{owner:string,source_target:string,css_target:string}
     */
    public static function resolveRoute(string $target): array
    {
        $path = (string)(parse_url($target, PHP_URL_PATH) ?: '');

        if (str_starts_with($path, '/u/')) {
            return [
                'owner' => 'Shell / Operator Layer',
                'source_target' => 'apps/Shell/Views/operator/',
                'css_target' => 'apps/Shell/styles/shell-operator.css',
            ];
        }

        if (str_starts_with($path, '/admin/') || $path === '/admin' || $path === '/me') {
            return [
                'owner' => 'Shell / Admin Layer',
                'source_target' => 'apps/Shell/Views/admin/',
                'css_target' => '',
            ];
        }

        if (preg_match('#^/apps/([a-z0-9._-]+)(?:/|$)#i', $path, $matches)) {
            $appKey = strtolower((string)($matches[1] ?? ''));
            $ownerDir = self::resolveOwnerDirectory(APP_ROOT . '/apps', $appKey);
            if ($ownerDir !== '') {
                $stylesDir = $ownerDir . '/styles';
                return [
                    'owner' => basename($ownerDir),
                    'source_target' => 'apps/' . basename($ownerDir) . '/',
                    'css_target' => is_dir($stylesDir)
                        ? 'apps/' . basename($ownerDir) . '/styles/'
                        : '',
                ];
            }
        }

        return [
            'owner' => '',
            'source_target' => '',
            'css_target' => '',
        ];
    }

    private static function resolveOwnerDirectory(string $root, string $key): string
    {
        if ($key === '' || !is_dir($root)) {
            return '';
        }

        $needle = self::normalize($key);
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $root . '/' . $entry;
            if (is_dir($path) && self::normalize($entry) === $needle) {
                return $path;
            }
        }
        return '';
    }

    private static function normalize(string $value): string
    {
        return strtolower((string)preg_replace('/[^a-z0-9]+/i', '', $value));
    }
}

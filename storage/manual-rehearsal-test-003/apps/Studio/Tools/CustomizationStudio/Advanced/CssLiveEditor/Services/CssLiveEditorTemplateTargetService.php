<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorTemplateTargetService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function targets(): array
    {
        $targets = [];
        foreach (self::approvedViewRoots() as $root) {
            if (!is_dir($root['path'])) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root['path'], \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $realPath = $file->getRealPath();
                if ($realPath === false || !self::isWithinRoot($realPath, $root['path'])) {
                    continue;
                }

                $relativePath = self::relativePath($realPath);
                if ($relativePath === '') {
                    continue;
                }

                $parentFolder = dirname($relativePath);
                $ownerRoot = self::relativePath(dirname($root['path']));
                $targets[$relativePath] = [
                    'id' => $relativePath,
                    'filename' => $file->getFilename(),
                    'parent_folder' => $parentFolder === '.' ? '' : $parentFolder,
                    'owner' => $root['owner'],
                    'owner_root' => $ownerRoot,
                    'label' => $file->getFilename() . ' — ' . ($parentFolder === '.' ? '/' : $parentFolder),
                    'title' => $file->getFilename(),
                    'route' => '',
                    'source_hint' => $relativePath,
                    'target_type' => 'php_template',
                    'adapter_id' => 'studio.direct-php-template.v1',
                    'adapter_name' => 'Direct PHP Template Feed',
                    'eligible' => true,
                    'enabled' => true,
                    'status' => 'available',
                    'reason' => '',
                    'unavailable_reason' => '',
                    'data_mode' => 'dev_live_data_actions_blocked',
                    'search_text' => strtolower(implode(' ', [
                        $file->getFilename(),
                        $parentFolder,
                        $root['owner'],
                        $relativePath,
                    ])),
                ];
            }
        }

        ksort($targets, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($targets);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find(string $targetId): ?array
    {
        $needle = self::normalizeRelativePath($targetId);
        if ($needle === '') {
            return null;
        }

        foreach (self::targets() as $target) {
            if ((string)($target['id'] ?? '') === $needle) {
                return $target;
            }
        }

        return null;
    }

    public static function absolutePath(array $target): string
    {
        $registered = self::find((string)($target['id'] ?? ''));
        if ($registered === null) {
            throw new \RuntimeException('PHP template target is not registered.');
        }

        $absolutePath = realpath(APP_ROOT . '/' . (string)$registered['id']);
        if ($absolutePath === false || !is_file($absolutePath)) {
            throw new \RuntimeException('PHP template target is unavailable.');
        }

        foreach (self::approvedViewRoots() as $root) {
            if (self::isWithinRoot($absolutePath, $root['path'])) {
                return $absolutePath;
            }
        }

        throw new \RuntimeException('PHP template target is outside approved roots.');
    }

    /**
     * @return array<int,array{path:string,owner:string}>
     */
    private static function approvedViewRoots(): array
    {
        $roots = [];
        foreach (glob(APP_ROOT . '/apps/*/Views', GLOB_ONLYDIR) ?: [] as $path) {
            $app = basename(dirname($path));
            $roots[] = ['path' => $path, 'owner' => 'App / ' . $app];
        }
        foreach (glob(APP_ROOT . '/apps/Studio/Tools/*/Views', GLOB_ONLYDIR) ?: [] as $path) {
            $tool = basename(dirname($path));
            $roots[] = ['path' => $path, 'owner' => 'Studio Tool / ' . $tool];
        }
        foreach (glob(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/*/*/Views', GLOB_ONLYDIR) ?: [] as $path) {
            $workspace = basename(dirname($path));
            $section = basename(dirname(dirname($path)));
            $roots[] = ['path' => $path, 'owner' => 'Customization Studio / ' . $section . ' / ' . $workspace];
        }
        foreach (glob(APP_ROOT . '/apps/*/Modules/*/Views', GLOB_ONLYDIR) ?: [] as $path) {
            $module = basename(dirname($path));
            $app = basename(dirname(dirname(dirname($path))));
            $roots[] = ['path' => $path, 'owner' => 'Module / ' . $app . ' / ' . $module];
        }
        foreach (glob(APP_ROOT . '/plugins/*/Views', GLOB_ONLYDIR) ?: [] as $path) {
            $plugin = basename(dirname($path));
            $roots[] = ['path' => $path, 'owner' => 'Plugin / ' . $plugin];
        }

        return $roots;
    }

    private static function isWithinRoot(string $path, string $root): bool
    {
        $realRoot = realpath($root);
        $realPath = realpath($path);
        return $realRoot !== false
            && $realPath !== false
            && ($realPath === $realRoot || str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR));
    }

    private static function relativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', (string)realpath(APP_ROOT)), '/');
        $normalized = str_replace('\\', '/', $path);
        if ($root === '' || !str_starts_with($normalized, $root . '/')) {
            return '';
        }

        return substr($normalized, strlen($root) + 1);
    }

    private static function normalizeRelativePath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, "\0")) {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $normalized), static fn(string $part): bool => $part !== ''));
        if ($segments === [] || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return '';
        }

        return implode('/', $segments);
    }
}

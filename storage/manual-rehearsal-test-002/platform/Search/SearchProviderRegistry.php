<?php
declare(strict_types=1);

namespace Platform\Search;

final class SearchProviderRegistry
{
    /** @var array<int,SearchProviderInterface>|null */
    private static ?array $providers = null;

    /** @return array<int,SearchProviderInterface> */
    public static function providers(): array
    {
        if (self::$providers !== null) {
            return self::$providers;
        }

        $providers = [];
        $root = defined('APP_ROOT') ? (string)APP_ROOT : dirname(__DIR__, 2);
        $appsRoot = realpath($root . '/apps');
        if ($appsRoot === false) {
            return self::$providers = [];
        }
        $appsRoot = rtrim(str_replace('\\', '/', $appsRoot), '/');
        $files = glob($appsRoot . '/*/search.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $resolved = realpath($file);
            if ($resolved === false) {
                continue;
            }
            $resolved = str_replace('\\', '/', $resolved);
            $relative = ltrim(substr($resolved, strlen($appsRoot)), '/');
            if (!str_starts_with($resolved, $appsRoot . '/') || preg_match('#^[^/]+/search\.php$#', $relative) !== 1) {
                continue;
            }
            try {
                $declared = include $resolved;
            } catch (\Throwable) {
                continue;
            }
            foreach ((array)$declared as $providerClass) {
                $class = trim((string)$providerClass);
                if ($class === '' || !class_exists($class)) {
                    continue;
                }
                try {
                    $provider = new $class();
                } catch (\Throwable) {
                    continue;
                }
                if ($provider instanceof SearchProviderInterface) {
                    $providers[] = $provider;
                }
            }
        }

        return self::$providers = $providers;
    }

    public static function clear(): void
    {
        self::$providers = null;
    }
}

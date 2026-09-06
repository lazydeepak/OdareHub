<?php
declare(strict_types=1);

namespace App\Core;

final class ManufacturingBootstrapHelper
{
    private static array $registered = [];

    public static function registerEntity(string $modulePath, string $entityKey, string $registerFunction): void
    {
        if (isset(self::$registered[$entityKey])) {
            return;
        }

        $definitionFile = $modulePath . '/EntityDefinition.php';
        $policiesFile = $modulePath . '/' . ucfirst($entityKey) . 'Policies.php';
        $hooksFile = $modulePath . '/' . ucfirst($entityKey) . 'Hooks.php';
        $serviceFile = $modulePath . '/' . ucfirst($entityKey) . 'Service.php';

        if (is_file($definitionFile)) {
            require_once $definitionFile;
        }

        if (is_file($policiesFile)) {
            require_once $policiesFile;
        }

        if (is_file($hooksFile)) {
            require_once $hooksFile;
        }

        if (is_file($serviceFile)) {
            require_once $serviceFile;
        }

        if (function_exists($registerFunction)) {
            $registerFunction();
        }

        self::$registered[$entityKey] = true;
    }

    public static function reset(): void
    {
        self::$registered = [];
    }

    public static function isRegistered(string $entityKey): bool
    {
        return isset(self::$registered[$entityKey]);
    }
}

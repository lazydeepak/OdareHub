<?php

namespace App\Core;

class EntityRegistry
{
    private static array $entities = [];

    public static function register(string $entityKey, array $definition): void
    {
        self::$entities[$entityKey] = $definition;
    }

    public static function get(string $entityKey): ?array
    {
        return self::$entities[$entityKey] ?? null;
    }

    public static function has(string $entityKey): bool
    {
        return isset(self::$entities[$entityKey]);
    }

    public static function all(): array
    {
        return self::$entities;
    }

    public static function unregister(string $entityKey): void
    {
        unset(self::$entities[$entityKey]);
    }

    public static function reset(): void
    {
        self::$entities = [];
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

final class DependencyRequirementService
{
    /**
     * @return array{name:string,operator:string,version:string,raw:string}
     */
    public static function parse(string $rawRequirement): array
    {
        $rawRequirement = trim($rawRequirement);
        if ($rawRequirement === '') {
            return [
                'name' => '',
                'operator' => '',
                'version' => '',
                'raw' => '',
            ];
        }

        if (preg_match('/^([A-Za-z0-9_.-]+)\s*(>=|<=|>|<|=)?\s*(.*)$/', $rawRequirement, $matches) !== 1) {
            return [
                'name' => $rawRequirement,
                'operator' => '',
                'version' => '',
                'raw' => $rawRequirement,
            ];
        }

        return [
            'name' => trim((string)($matches[1] ?? '')),
            'operator' => trim((string)($matches[2] ?? '')),
            'version' => trim((string)($matches[3] ?? '')),
            'raw' => $rawRequirement,
        ];
    }

    public static function name(string $rawRequirement): string
    {
        return (string)(self::parse($rawRequirement)['name'] ?? '');
    }

    public static function isSatisfied(string $rawRequirement, string $actualVersion): bool
    {
        $parsed = self::parse($rawRequirement);
        $operator = (string)($parsed['operator'] ?? '');
        $requiredVersion = (string)($parsed['version'] ?? '');
        if ($operator === '' || $requiredVersion === '') {
            return true;
        }

        $actualVersion = trim($actualVersion);
        if ($actualVersion === '') {
            return false;
        }

        $comparator = $operator === '=' ? '==' : $operator;
        return version_compare($actualVersion, $requiredVersion, $comparator);
    }
}

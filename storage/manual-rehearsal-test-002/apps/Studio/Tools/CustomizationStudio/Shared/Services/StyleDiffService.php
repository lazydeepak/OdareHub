<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Shared\Services;

final class StyleDiffService
{
    private function __construct()
    {
    }

    /**
     * @return array{changed: bool, current_value: ?string, proposed_value: string, token_name: string}
     */
    public static function compute(string $tokenName, ?string $currentValue, string $proposedValue): array
    {
        $normalized = self::normalizeTokenName($tokenName);
        $trimmedProposed = trim($proposedValue);
        $trimmedCurrent = $currentValue !== null ? trim($currentValue) : null;

        return [
            'token_name' => $normalized,
            'current_value' => $trimmedCurrent,
            'proposed_value' => $trimmedProposed,
            'changed' => $trimmedCurrent !== null && $trimmedCurrent !== $trimmedProposed,
        ];
    }

    private static function normalizeTokenName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }
        if (str_starts_with($trimmed, '--')) {
            return $trimmed;
        }
        return '--' . ltrim($trimmed, '-');
    }
}

<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

use Apps\Studio\Services\StudioOwnerDiscoveryService;

final class OwnerStructureOwnerDiscoveryService
{
    private const DEFAULT_OWNER = 'Manufacturing/Products';

    /**
     * @return array<int,array<string,string>>
     */
    public static function discover(): array
    {
        $result = StudioOwnerDiscoveryService::discover(
            defined('APP_ROOT') ? (string)APP_ROOT : null,
            StudioOwnerDiscoveryService::PROFILE_OWNER_STRUCTURE
        );

        return is_array($result['owners'] ?? null) ? $result['owners'] : [];
    }

    /**
     * @return array<string,mixed>
     */
    public static function resolve(string $requestedOwnerKey): array
    {
        $owners = self::discover();
        $safeRequested = self::sanitizeOwnerKey($requestedOwnerKey);
        $resolution = StudioOwnerDiscoveryService::resolve($safeRequested, $owners, self::DEFAULT_OWNER);

        $invalidReason = '';
        if ($safeRequested === '') {
            $invalidReason = trim($requestedOwnerKey) !== ''
                ? 'Invalid owner key. No owner was selected.'
                : 'No owner selected.';
        } elseif ((string)($resolution['error_code'] ?? '') === 'unknown_owner_key') {
            $invalidReason = 'Unknown owner key. No owner was selected.';
        }

        return [
            'owners' => $owners,
            'selected_owner' => $invalidReason === '' ? ($resolution['selected_owner'] ?? null) : null,
            'selected_owner_key' => $invalidReason === '' ? (string)($resolution['selected_owner_key'] ?? '') : '',
            'default_owner_key' => (string)($resolution['default_owner_key'] ?? ''),
            'invalid_owner_key' => $invalidReason !== '',
            'error' => $invalidReason,
        ];
    }

    private static function sanitizeOwnerKey(string $ownerKey): string
    {
        $ownerKey = trim($ownerKey);
        if ($ownerKey === '' || str_starts_with($ownerKey, '/') || str_contains($ownerKey, '\\') || str_contains($ownerKey, '..')) {
            return '';
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*(\/[A-Za-z][A-Za-z0-9]*){0,3}$/', $ownerKey)) {
            return '';
        }
        return $ownerKey;
    }
}

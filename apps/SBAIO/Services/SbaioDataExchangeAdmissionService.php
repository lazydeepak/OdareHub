<?php
declare(strict_types=1);

namespace SBAIO\Services;

final class SbaioDataExchangeAdmissionService
{
    /**
     * SBAIO personnel imports have no approved record-level mutation contract.
     * Keep both operator adapters unavailable until that contract exists.
     */
    public static function isAvailable(string $adapterKey): bool
    {
        return false;
    }
}

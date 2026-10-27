<?php
declare(strict_types=1);

namespace SBAIO\Services;

final class SbaioBulkIoAdmissionService
{
    /**
     * Suite-level import, export, restore, and backup-download authority is
     * not established independently of SBAIO app access.
     */
    public static function isAvailable(string $operation): bool
    {
        return false;
    }
}

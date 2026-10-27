<?php
declare(strict_types=1);

namespace SBAIO\Services;

final class SbaioDashboardPrivacyAdmissionService
{
    /**
     * SBAIO app access and module visibility do not establish record-level
     * Staff, attendance, leave, timecard, or payroll visibility.
     */
    public static function isPersonnelDataAvailable(): bool
    {
        return false;
    }
}

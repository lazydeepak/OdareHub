<?php
declare(strict_types=1);

namespace SBAIO\Services;

final class SbaioOperatorWidgetPrivacyService
{
    /**
     * SBAIO app/module visibility does not establish record-level personnel or
     * payroll authority for operator widget data.
     */
    public static function isAvailable(string $region): bool
    {
        return false;
    }
}

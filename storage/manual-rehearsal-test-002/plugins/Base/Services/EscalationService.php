<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use Apps\Manufacturing\Services\EscalationContributionService;

final class EscalationService
{
    private static function contributionClass(): ?string
    {
        return class_exists(EscalationContributionService::class)
            ? EscalationContributionService::class
            : null;
    }

    public static function runSweep(bool $force = false): void
    {
        $service = self::contributionClass();
        if ($service === null) {
            return;
        }

        $service::runSweep($force);
    }
}

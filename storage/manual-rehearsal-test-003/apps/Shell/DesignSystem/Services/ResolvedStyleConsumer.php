<?php
declare(strict_types=1);

namespace Apps\Shell\DesignSystem\Services;

/**
 * Inert placeholder retained for the Shell Style directory contract.
 * Platform owns approved-style reads and resolution; Shell runtime must
 * eventually consume only an explicitly authorized Platform surface.
 */
final class ResolvedStyleConsumer
{
    /** @return array{runtime_status:string,owner:string} */
    public function status(): array
    {
        return [
            'runtime_status' => 'placeholder_only_not_consumed',
            'owner' => 'platform',
        ];
    }
}

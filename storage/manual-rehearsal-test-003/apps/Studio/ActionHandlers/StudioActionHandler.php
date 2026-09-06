<?php
declare(strict_types=1);

namespace Apps\Studio\ActionHandlers;

interface StudioActionHandler
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function handle(array $payload, array $context): array;
}

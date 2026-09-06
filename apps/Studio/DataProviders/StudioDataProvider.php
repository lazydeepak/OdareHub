<?php
declare(strict_types=1);

namespace Apps\Studio\DataProviders;

interface StudioDataProvider
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function fetch(array $context): array;
}

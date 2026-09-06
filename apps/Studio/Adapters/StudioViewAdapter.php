<?php
declare(strict_types=1);

namespace Apps\Studio\Adapters;

interface StudioViewAdapter
{
    /**
     * @param array<string,mixed> $viewManifest
     * @param array<string,mixed> $context
     */
    public function render(array $viewManifest, array $context): string;
}

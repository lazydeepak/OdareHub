<?php
declare(strict_types=1);

namespace Platform\Search;

interface SearchProviderInterface
{
    /** @return array<string,string> */
    public function groupLabels(): array;

    /** @return array<string,array<string,int>> intent => group type => priority */
    public function groupPriorities(): array;

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function search(string $query, SearchProviderContext $context): array;
}

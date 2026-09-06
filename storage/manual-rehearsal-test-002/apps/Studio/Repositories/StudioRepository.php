<?php
declare(strict_types=1);

namespace Apps\Studio\Repositories;

interface StudioRepository
{
    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function insert(array $data): array;

    /**
     * @param array<string,mixed> $criteria
     * @param array<string,mixed> $data
     */
    public function update(array $criteria, array $data): int;

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function find(array $criteria): array;
}

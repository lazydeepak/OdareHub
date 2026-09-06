<?php
declare(strict_types=1);

use App\Core\HandoffEngine;

HandoffEngine::setAssemblyResolvers(
    static function (int $productId, string $date): array {
        $map = \Apps\Manufacturing\Services\StageTransitionService::computeForDate($date);
        $row = is_array($map[$productId] ?? null) ? $map[$productId] : [];
        $assembly = is_array($row['stages']['assembly'] ?? null) ? $row['stages']['assembly'] : [];

        return [
            'block_reasons' => (array)($row['block_reasons'] ?? []),
            'waiting_upstream' => in_array((string)($assembly['status'] ?? ''), ['pending', 'blocked'], true),
        ];
    },
    static function (int $productId, string $date): float {
        return (float)\Apps\Manufacturing\Services\AssemblyPlanService::completedQtyByProductDate($productId, $date);
    }
);

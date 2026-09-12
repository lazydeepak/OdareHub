<?php

declare(strict_types=1);

namespace Apps\Manufacturing\Modules\Bom;

class BomPolicies
{
    /**
     * Draft BOMs are the only editable lifecycle state.
     *
     * @param array<string,mixed> $bom
     */
    public static function canEdit(array $bom): bool
    {
        return strtolower((string)($bom['status'] ?? 'draft')) === 'draft';
    }

    /**
     * Released -> superseded/archived; draft -> released/archived.
     *
     * @param array<string,mixed> $bom
     */
    public static function canTransition(array $bom, string $action): bool
    {
        $state = strtolower((string)($bom['status'] ?? 'draft'));
        $allowed = [
            'draft' => ['release', 'archive'],
            'released' => ['supersede', 'archive'],
            'superseded' => [],
            'archived' => [],
        ];
        return in_array($action, $allowed[$state] ?? [], true);
    }
}